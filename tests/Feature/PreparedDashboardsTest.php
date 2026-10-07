<?php

namespace Tests\Feature;

use App\Jobs\GenerateMonthlyWeatherForecast;
use App\Jobs\PrepareFarmerYieldEstimate;
use App\Jobs\RefreshBarangayRiskMap;
use App\Jobs\RunPythonAnalytics;
use App\Models\AnalyticsRun;
use App\Models\ClimateRecord;
use App\Models\FarmerProfile;
use App\Models\FeedPost;
use App\Models\FeedReaction;
use App\Models\User;
use App\Services\Analytics\FarmerDashboardService;
use App\Services\Analytics\MaoDashboardService;
use App\Services\Analytics\PreparedDashboards;
use App\Services\Prediction\RiceYieldPredictionService;
use App\Services\Risk\BarangayRiskMapService;
use App\Support\CacheScope;
use App\Support\StaleCache;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 3: expensive dashboard work happens ahead of the person (warm-up, Refresh) and pages, timed refreshes and
 * repeated visits read the prepared result; queued refreshes are never duplicated.
 */
class PreparedDashboardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::fake(['*' => Http::response([], 500)]);
    }

    // ------------------------------------------------------------------ pages and timed refreshes read prepared data

    public function test_mao_operations_is_built_once_and_then_read_prepared(): void
    {
        $mao = User::factory()->maoPersonnel()->create();

        $cold = $this->queries(fn () => $this->actingAs($mao)->get(route('mao.operations'))->assertOk());
        $warm = $this->queries(fn () => $this->actingAs($mao)->get(route('mao.operations'))->assertOk()->assertViewHas('maoKpis'));

        $this->assertGreaterThan(10, $cold, 'the first visit assembles the page');
        $this->assertLessThanOrEqual(3, $warm, 'the next visit reads the prepared page (only the signed-in user\'s own lookups)');
    }

    public function test_mao_operations_filters_are_separate_prepared_copies(): void
    {
        $mao = User::factory()->maoPersonnel()->create();
        $this->actingAs($mao)->get(route('mao.operations'))->assertOk();

        $filtered = $this->queries(fn () => $this->actingAs($mao)->get(route('mao.operations', ['schedule_season' => 'Wet']))->assertOk());
        $this->assertGreaterThan(3, $filtered, 'a different filter is its own page');
        $this->assertSame('Wet', $this->actingAs($mao)->get(route('mao.operations', ['schedule_season' => 'Wet']))->viewData('scheduleFilters')['season']);
    }

    public function test_the_warm_up_prepares_mao_operations_so_the_first_visitor_reads_it(): void
    {
        $mao = User::factory()->maoPersonnel()->create();
        $this->artisan('iclimate:warm-dashboards')->assertExitCode(0);

        $this->assertLessThanOrEqual(3, $this->queries(fn () => $this->actingAs($mao)->get(route('mao.operations'))->assertOk()));
    }

    public function test_the_warm_up_rebuilds_only_what_is_due(): void
    {
        $this->artisan('iclimate:warm-dashboards')->assertExitCode(0);
        $results = app(PreparedDashboards::class)->warm();

        $this->assertSame(['MAO dashboard' => 'current', 'IT overview' => 'current', 'MAO Operations' => 'current'], $results,
            'nothing changed and nothing is about to expire: nothing is rebuilt');

        ClimateRecord::factory()->create();                     // data both MAO payloads read
        $after = app(PreparedDashboards::class)->warm();
        $this->assertStringEndsWith(' s', $after['MAO dashboard'], 'the MAO dashboard read climate data: rebuilt');
        $this->assertStringEndsWith(' s', $after['MAO Operations']);
    }

    public function test_the_mao_timed_refresh_reads_the_prepared_dashboard_and_only_refresh_rebuilds(): void
    {
        $mao = User::factory()->maoPersonnel()->create();
        $this->actingAs($mao)->get(route('mao.dashboard'))->assertOk();

        $timer = $this->queries(fn () => $this->actingAs($mao)->getJson(route('mao.dashboard.live'))->assertOk());
        $this->travel(2)->seconds();                             // a Refresh pressed after the copy was built
        $refresh = $this->queries(fn () => $this->actingAs($mao)->getJson(route('mao.dashboard.live', ['refresh' => 1]))->assertOk());

        $this->assertLessThanOrEqual(4, $timer, 'the 5-minute timer reads the prepared payload (plus the signed-in MAO\'s own counts)');
        $this->assertGreaterThan($timer + 5, $refresh, 'the Refresh button really rebuilds');
    }

    public function test_the_it_timed_refresh_reads_the_prepared_overview_and_only_refresh_rebuilds(): void
    {
        $it = User::factory()->itExpert()->create();
        $this->actingAs($it)->get(route('admin.dashboard'))->assertOk();

        $timer = $this->queries(fn () => $this->actingAs($it)->getJson(route('admin.dashboard.live'))->assertOk()->assertJsonStructure(['slots', 'refreshed_at']));
        $this->travel(2)->seconds();
        $refresh = $this->queries(fn () => $this->actingAs($it)->getJson(route('admin.dashboard.live', ['refresh' => 1]))->assertOk());

        $this->assertLessThanOrEqual(2, $timer, 'the 5-minute timer no longer rebuilds every system metric');
        $this->assertGreaterThan(10, $refresh);
    }

    public function test_the_farmer_timed_refresh_and_yield_check_read_the_prepared_home(): void
    {
        $farmer = $this->farmer();
        $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk();

        $timer = $this->queries(fn () => $this->actingAs($farmer)->getJson(route('farmer.dashboard.live'))->assertOk());
        $check = $this->queries(fn () => $this->actingAs($farmer)->getJson(route('farmer.dashboard.live', ['retry' => 'harvest-check']))->assertOk());
        $page = $this->queries(fn () => $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk());

        $this->assertLessThanOrEqual($page, $timer, 'the timer costs no more than opening the prepared page');
        $this->assertLessThanOrEqual($page, $check, 'the yield check does not rebuild the Home while the estimate is still pending');
    }

    public function test_the_yield_check_rebuilds_once_the_background_estimate_is_saved(): void
    {
        $farmer = $this->farmer();
        $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk();

        Cache::put(FarmerDashboardService::predictionFailureKey($farmer->id), ['failed' => true, 'lacks_records' => false], now()->addMinutes(5));
        $card = $this->actingAs($farmer)->getJson(route('farmer.dashboard.live', ['retry' => 'harvest-check']))->assertOk()->json('slots.harvest');

        $this->assertNotNull($card, 'the saved result replaces the placeholder');
    }

    // ------------------------------------------------------------------ Refresh: one rebuild, honest results

    public function test_two_refreshes_together_rebuild_once(): void
    {
        $builds = 0;
        $build = function () use (&$builds): array {
            $builds++;

            return ['built' => $builds, 'generated_at' => now()];
        };
        $keyFor = fn (string $token): string => 'probe:'.$token;

        $first = StaleCache::refreshScoped('mao-dashboard', $keyFor, 90, $build);
        $second = StaleCache::refreshScoped('mao-dashboard', $keyFor, 90, $build);   // pressed while/just after the first

        $this->assertSame(1, $builds, 'the second Refresh shares the result the first one produced');
        $this->assertSame($first, $second);
    }

    public function test_a_refresh_that_cannot_wait_gets_the_prepared_copy_not_another_build(): void
    {
        $builds = 0;
        $build = function () use (&$builds): array {
            return ['built' => ++$builds];
        };
        $keyFor = fn (string $token): string => 'probe:'.$token;
        StaleCache::scoped('mao-dashboard', $keyFor, 90, $build);       // the prepared copy

        $running = Cache::lock('stale-cache:refresh:probe:any', 300);   // another Refresh is rebuilding it right now
        $this->assertTrue($running->get());
        $result = StaleCache::refreshScoped('mao-dashboard', $keyFor, 90, $build, waitSeconds: 0);
        $running->release();

        $this->assertSame(1, $builds);
        $this->assertSame(['built' => 1], $result);
    }

    public function test_a_failed_preparation_keeps_the_last_verified_payload_with_its_real_time(): void
    {
        $keyFor = fn (string $token): string => 'probe:'.$token;
        $builtAt = now()->toIso8601String();
        StaleCache::prepareScoped('mao-dashboard', $keyFor, 90, fn (): array => ['generated_at' => $builtAt]);   // last verified copy

        $this->travel(95)->seconds();                                            // the copy is due
        try {
            StaleCache::prepareScoped('mao-dashboard', $keyFor, 90, fn () => throw new RuntimeException('database went away'));
            $this->fail('a failed build is reported, never stored');
        } catch (RuntimeException) {
        }

        \Illuminate\Support\Facades\Route::get('/_prepared-probe', fn () => StaleCache::scoped('mao-dashboard', $keyFor, 90,
            fn () => throw new RuntimeException('still down')))->middleware('web');
        $shown = $this->get('/_prepared-probe')->assertOk()->json('generated_at');

        $this->assertSame($builtAt, $shown, 'the page shows the last verified copy, with the time it was really built');
    }

    // ------------------------------------------------------------------ change-driven freshness (Phase 2 still holds)

    public function test_a_relevant_change_retires_mao_operations_and_an_unrelated_one_does_not(): void
    {
        $mao = User::factory()->maoPersonnel()->create();
        $post = FeedPost::query()->create(['user_id' => $mao->id, 'title' => 'Seed', 'body' => 'Ready', 'category' => 'Update']);
        $farmer = $this->farmer();
        $this->actingAs($mao)->get(route('mao.operations'))->assertOk();

        FeedReaction::query()->create(['feed_post_id' => $post->id, 'user_id' => $farmer->id, 'type' => 'Like']);
        $this->assertLessThanOrEqual(3, $this->queries(fn () => $this->actingAs($mao)->get(route('mao.operations'))->assertOk()),
            'a reaction is not on MAO Operations: still the prepared page');

        $before = $this->actingAs($mao)->get(route('mao.operations'))->viewData('totalClimateRecords');
        ClimateRecord::factory()->create();
        $after = $this->actingAs($mao)->get(route('mao.operations'))->viewData('totalClimateRecords');
        $this->assertSame($before + 1, $after, 'a new climate record shows on the very next visit');
    }

    // ------------------------------------------------------------------ queued work is never duplicated

    public function test_twenty_page_visits_queue_one_risk_map_refresh(): void
    {
        Queue::fake();

        for ($i = 0; $i < 20; $i++) {
            Cache::forget(BarangayRiskMapService::REFRESH_LOCK_KEY);   // the old 10-minute flag lapsing while the job waits
            app(BarangayRiskMapService::class)->queueRefresh();
        }

        Queue::assertPushed(RefreshBarangayRiskMap::class, 1);
    }

    public function test_monthly_forecasts_and_farmer_estimates_are_unique_per_month_and_per_farmer(): void
    {
        Queue::fake();

        foreach (['2026-11-01', '2026-11-01', '2026-12-01'] as $month) {
            GenerateMonthlyWeatherForecast::dispatch($month);
        }
        foreach ([7, 7, 8] as $userId) {
            Cache::forget(PrepareFarmerYieldEstimate::lockKey($userId));
            PrepareFarmerYieldEstimate::queueFor($userId);
        }

        Queue::assertPushed(GenerateMonthlyWeatherForecast::class, 2);
        Queue::assertPushed(PrepareFarmerYieldEstimate::class, 2);
    }

    public function test_a_failed_yield_estimate_is_shown_as_unavailable_and_retried_later_not_on_every_visit(): void
    {
        Queue::fake();
        $this->mock(RiceYieldPredictionService::class, fn ($mock) => $mock->shouldReceive('predictForUser')->andThrow(new RuntimeException('service down')));
        $farmer = $this->farmer();
        Queue::assertPushed(PrepareFarmerYieldEstimate::class, 1);               // saving the profile asked for an estimate

        // The worker runs that job: the service is down, so the failure is stored, and the job's unique lock is released.
        app(FarmerDashboardService::class)->prepareYieldEstimate($farmer);
        (new \Illuminate\Bus\UniqueLock(app(\Illuminate\Contracts\Cache\Repository::class)))->release(new PrepareFarmerYieldEstimate($farmer->id));
        Cache::forget(PrepareFarmerYieldEstimate::lockKey($farmer->id));

        $harvest = $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk()->viewData('f')['harvest'];
        $this->assertNotSame('ok', $harvest['state'], 'an honest "not available yet", never a made-up yield');
        $this->assertNull($harvest['result'] ?? null);
        Queue::assertPushed(PrepareFarmerYieldEstimate::class, 1);               // the failure is remembered: no retry on every visit

        $this->travel(6)->minutes();                                              // remembered for 5 minutes
        $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk();
        Queue::assertPushed(PrepareFarmerYieldEstimate::class, 2);               // tried again later
    }

    // ------------------------------------------------------------------ reports and the scheduler

    public function test_report_generation_stays_queue_based_and_shows_pending_until_a_worker_finishes(): void
    {
        Queue::fake();
        $mao = User::factory()->maoPersonnel()->create();

        $this->actingAs($mao)->post(route('reports.generate'), ['report_type' => 'rice_production'])->assertRedirect();

        Queue::assertPushed(RunPythonAnalytics::class, 1);
        $run = AnalyticsRun::query()->latest('id')->firstOrFail();
        $this->assertSame(AnalyticsRun::STATUS_PENDING, $run->status, 'never marked complete without a worker');
        $this->actingAs($mao)->get(route('reports.index', ['run' => $run->uuid]))->assertOk();
    }

    public function test_every_scheduled_task_is_protected_from_overlapping(): void
    {
        $events = collect(app(Schedule::class)->events());

        $this->assertNotEmpty($events);
        foreach ($events as $event) {
            $this->assertTrue($event->withoutOverlapping, ($event->command ?? $event->description).' must not overlap itself');
        }
    }

    // ------------------------------------------------------------------ helpers

    private function farmer(): User
    {
        $user = User::factory()->farmer()->create(['barangay' => 'Binubusan']);
        FarmerProfile::factory()->create(['user_id' => $user->id, 'barangay' => 'Binubusan', 'farm_area' => 1.2, 'farm_type' => 'Irrigated']);

        return $user->fresh();
    }

    private function queries(callable $work): int
    {
        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });
        $before = $count;
        $work();

        return $count - $before;
    }
}
