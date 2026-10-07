<?php

namespace Tests\Feature;

use App\Jobs\GenerateMonthlyWeatherForecast;
use App\Jobs\PrepareFarmerYieldEstimate;
use App\Models\Farm;
use App\Models\FarmerProfile;
use App\Models\MonthlyWeatherPrediction;
use App\Models\User;
use App\Services\Analytics\FarmerDashboardService;
use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use App\Services\Prediction\RiceYieldPredictionService;
use App\Services\TyphoonSafetyService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

/**
 * Farmer Home → "Expected Yield This Season" (2026-10-07): the card shows the last verified estimate at once, with its
 * own time. A missing or due estimate is prepared once per farmer in the background (right after the farmer's own
 * response, so no queue worker is needed), a failed run is not repeated on every load, and a page visit never trains a
 * model. The bug: the estimate only came from a queued job (no worker ran) and expired after 6 hours, so the card stayed
 * on "Your estimate is being prepared." indefinitely.
 */
class FarmerYieldEstimateTest extends TestCase
{
    use RefreshDatabase;

    /** Calls the (mocked) model made. */
    private int $modelCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response([], 500)]);
        $this->mock(TyphoonSafetyService::class, fn ($mock) => $mock->shouldReceive('activeEvent')->andReturn(null));
    }

    /** The model answers $yield t/ha; null makes it fail the way an unreachable service does. */
    private function model(?float $yield): void
    {
        $this->mock(RiceYieldPredictionService::class, fn ($mock) => $mock->shouldReceive('predictForUser')->andReturnUsing(function () use ($yield) {
            $this->modelCalls++;
            if ($yield === null) {
                throw new RuntimeException('The Farming AI API could not be reached.');
            }

            return ['success' => true, 'yield_tons_per_hectare' => $yield, 'predicted_yield' => $yield, 'target_month' => now('Asia/Manila')->format('Y-m')];
        }));
    }

    private function farmer(?float $area = 2.0, string $email = 'farmer@example.test'): User
    {
        $user = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => 'Prenza', 'email' => $email]);
        FarmerProfile::factory()->create(['user_id' => $user->id, 'barangay' => 'Prenza', 'farm_area' => $area, 'farm_type' => 'Irrigated']);
        // Creating the profile started its estimate; each test starts from a clean store instead.
        Cache::flush();

        return $user->load('farmerProfile');
    }

    private function primaryFarmId(User $farmer): int
    {
        return (int) Farm::query()->where('user_id', $farmer->id)->where('is_primary', true)->value('id');
    }

    /** A verified estimate as the warm-up stores it. */
    private function saved(User $farmer, float $yield, ?CarbonImmutable $ranAt = null): void
    {
        Cache::forever(FarmerDashboardService::predictionCacheKey($farmer->id, $this->primaryFarmId($farmer)), [
            'success' => true, 'yield_tons_per_hectare' => $yield, 'predicted_yield' => $yield,
            'target_month' => now('Asia/Manila')->format('Y-m'),
            'ran_at' => ($ranAt ?? CarbonImmutable::now('Asia/Manila'))->toIso8601String(),
        ]);
    }

    /** The yield card's visible words. */
    private function card(string $html): string
    {
        $this->assertSame(1, preg_match('#<section class="fh-card"[^>]*data-fh-harvest[^>]*>(.*?)</section>#s', $html, $m), 'yield card');

        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($m[1]))));
    }

    private function home(User $farmer): string
    {
        return $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk()->getContent();
    }

    // ------------------------------------------------------------ the last verified estimate

    public function test_a_saved_estimate_is_on_the_first_load_with_its_time_and_nothing_is_started(): void
    {
        $this->model(4.0);
        $farmer = $this->farmer();
        Queue::fake();
        $this->saved($farmer, 4.2, CarbonImmutable::now('Asia/Manila')->subHour());

        $html = $this->home($farmer);

        $this->assertStringContainsString('4.20 t/ha', $this->card($html));
        $this->assertStringContainsString('Updated: '.CarbonImmutable::now('Asia/Manila')->subHour()->format('g:i A'), $this->card($html));
        $this->assertStringNotContainsString('data-mfd-load="harvest"', $html);
        Queue::assertNotPushed(PrepareFarmerYieldEstimate::class);
        $this->assertSame(0, $this->modelCalls, 'a page never runs the model');
    }

    public function test_a_due_estimate_stays_on_screen_while_one_refresh_starts(): void
    {
        $farmer = $this->farmer();
        Queue::fake();
        $ranAt = CarbonImmutable::now('Asia/Manila')->subHours(FarmerDashboardService::REFRESH_AFTER_HOURS + 1);
        $this->saved($farmer, 4.2, $ranAt);

        foreach (range(1, 3) as $_) {
            $card = $this->card($this->home($farmer));
            $this->assertStringContainsString('4.20 t/ha', $card);
            $this->assertStringContainsString('Updated: '.$ranAt->format($ranAt->isToday() ? 'g:i A' : 'M j, g:i A'), $card);
            $this->assertStringNotContainsString('being prepared', $card);
        }
        Queue::assertPushed(PrepareFarmerYieldEstimate::class, 1);
    }

    public function test_a_changed_farm_size_is_estimated_again_before_the_next_visit(): void
    {
        $this->model(4.6);
        $farmer = $this->farmer();
        $this->saved($farmer, 4.2);

        // Saving the farm size marks the estimate due and makes the new one right after that response.
        $this->actingAs($farmer)->postJson(route('farmer.dashboard.farm-size'), ['farm_area' => 3.0])->assertOk();
        $this->assertSame(1, $this->modelCalls);

        $card = $this->card($this->home($farmer));
        $this->assertStringContainsString('4.60 t/ha', $card, 'ready by the next visit');
        $this->assertSame(1, $this->modelCalls, 'the visit itself only reads');
    }

    public function test_while_the_new_estimate_is_being_made_the_old_one_stays_visible(): void
    {
        $farmer = $this->farmer();
        Queue::fake();                                          // holds the background run, to see the moment before it ends
        $this->saved($farmer, 4.2);

        $this->actingAs($farmer)->postJson(route('farmer.dashboard.farm-size'), ['farm_area' => 3.0])->assertOk();

        $this->assertTrue(Cache::get(FarmerDashboardService::predictionCacheKey($farmer->id, $this->primaryFarmId($farmer)))['inputs_changed']);
        $card = $this->card($this->home($farmer));
        $this->assertStringContainsString('4.20 t/ha', $card);
        $this->assertStringNotContainsString('being prepared', $card);
        Queue::assertPushed(PrepareFarmerYieldEstimate::class, 1);
    }

    public function test_another_farmers_change_leaves_this_farmers_estimate_alone(): void
    {
        $mine = $this->farmer(email: 'mine@example.test');
        $other = $this->farmer(email: 'other@example.test');
        Queue::fake();
        $this->saved($mine, 4.2);
        $this->saved($other, 3.9);

        $other->farmerProfile->update(['farm_area' => 0.75]);

        $mineFarmId = $this->primaryFarmId($mine);
        $otherFarmId = $this->primaryFarmId($other);
        $this->assertArrayNotHasKey('inputs_changed', Cache::get(FarmerDashboardService::predictionCacheKey($mine->id, $mineFarmId)));
        $this->assertTrue(Cache::get(FarmerDashboardService::predictionCacheKey($other->id, $otherFarmId))['inputs_changed']);
        // Only the other farmer's estimate is started again (here on the queue: the change was not that farmer's own request).
        $this->assertTrue(Cache::has(PrepareFarmerYieldEstimate::lockKey($other->id, $otherFarmId)));
        $this->assertFalse(Cache::has(PrepareFarmerYieldEstimate::lockKey($mine->id, $mineFarmId)));
        $this->assertSame(4.2, FarmerDashboardService::storedEstimate($mine->id)['yield_tons_per_hectare']);
    }

    // ------------------------------------------------------------ the first estimate

    public function test_ten_reloads_without_an_estimate_start_one_preparation(): void
    {
        $farmer = $this->farmer();
        Queue::fake();

        foreach (range(1, 10) as $_) {
            $html = $this->home($farmer);
            $this->assertStringContainsString('data-mfd-load="harvest"', $html);
            $this->assertStringContainsString('Your estimate is being prepared.', $this->card($html));
        }

        Queue::assertPushed(PrepareFarmerYieldEstimate::class, 1);
    }

    public function test_the_first_estimate_is_made_right_after_the_page_and_fills_only_the_card(): void
    {
        $this->model(4.35);
        $farmer = $this->farmer();

        // The page is sent with the placeholder; the estimate is made right after it (no queue worker involved).
        $this->assertStringContainsString('Your estimate is being prepared.', $this->card($this->home($farmer)));
        $this->assertSame(1, $this->modelCalls);
        $this->assertNotNull(FarmerDashboardService::storedEstimate($farmer->id));

        // The card's check gets that card alone, filled.
        $json = $this->actingAs($farmer)->getJson(route('farmer.dashboard.live', ['retry' => 'harvest-check']))->assertOk()->json();
        $this->assertSame(['harvest'], array_keys($json['slots']));
        $this->assertStringContainsString('4.35 t/ha', $this->card($json['slots']['harvest']));
        $this->assertStringNotContainsString('data-mfd-load', $json['slots']['harvest']);
        $this->assertSame(1, $this->modelCalls, 'the check only reads');
    }

    public function test_the_card_check_sends_nothing_while_the_estimate_is_still_being_prepared(): void
    {
        $farmer = $this->farmer();
        Queue::fake();

        $json = $this->actingAs($farmer)->getJson(route('farmer.dashboard.live', ['retry' => 'harvest-check']))->assertOk()->json();

        $this->assertSame([], $json['slots']);
        Queue::assertPushed(PrepareFarmerYieldEstimate::class, 1);
    }

    public function test_a_farm_without_a_size_is_asked_for_it_at_once(): void
    {
        $farmer = $this->farmer(area: null);
        Queue::fake();
        $card = $this->card($this->home($farmer));

        $this->assertStringContainsString('Add your farm size to see your estimate.', $card);
        $this->assertStringNotContainsString('being prepared', $card);
    }

    // ------------------------------------------------------------ failures

    public function test_a_failed_run_is_not_repeated_on_every_load_and_is_tried_again_later(): void
    {
        $this->model(null);
        $farmer = $this->farmer();

        $this->home($farmer);                                   // the first run fails right after this page
        $this->assertSame(1, $this->modelCalls);

        foreach (range(1, 5) as $_) {
            $card = $this->card($this->home($farmer));
            $this->assertStringContainsString('Estimate is temporarily unavailable. We’ll try again automatically.', $card);
        }
        $this->assertSame(1, $this->modelCalls, 'no new call while the failure is recent');
        foreach (['Python', 'API', 'service', 'model', 'queue', 'job'] as $word) {
            $this->assertStringNotContainsString($word, $card);
        }

        $this->travel(FarmerDashboardService::FAILURE_RETRY_MINUTES + 1)->minutes();
        Cache::forget(PrepareFarmerYieldEstimate::lockKey($farmer->id, $this->primaryFarmId($farmer)));    // released by the earlier run's handle()
        $this->home($farmer);
        $this->assertSame(2, $this->modelCalls, 'tried again once the short pause is over');
    }

    public function test_a_failed_refresh_keeps_the_last_verified_estimate(): void
    {
        $this->model(null);
        $farmer = $this->farmer();
        $ranAt = CarbonImmutable::now('Asia/Manila')->subHours(FarmerDashboardService::REFRESH_AFTER_HOURS + 1);
        $this->saved($farmer, 4.2, $ranAt);

        $this->home($farmer);                                   // due: the refresh runs after this page and fails
        $this->assertSame(1, $this->modelCalls);

        foreach (range(1, 3) as $_) {
            $this->assertStringContainsString('4.20 t/ha', $this->card($this->home($farmer)));
        }
        $this->assertSame(1, $this->modelCalls);
        $this->assertSame(4.2, FarmerDashboardService::storedEstimate($farmer->id)['yield_tons_per_hectare']);
    }

    // ------------------------------------------------------------ no model training on a page visit

    public function test_a_page_visit_never_trains_the_monthly_weather_model(): void
    {
        Queue::fake([GenerateMonthlyWeatherForecast::class]);
        $this->partialMock(MonthlyWeatherRandomForest::class, fn ($mock) => $mock->shouldNotReceive('predict'));
        Http::fake(['*/predict' => Http::response(['rice_yield_prediction' => ['predicted_yield' => 4.1]], 200), '*' => Http::response([], 500)]);
        $farmer = $this->farmer();
        $this->assertSame(0, MonthlyWeatherPrediction::query()->count(), 'no monthly forecast prepared yet');

        // The background run after the page reads prepared forecasts only: none yet, so it is a short "try again soon"
        // (the forecast itself is left to its own background job) instead of training the forest in the farmer's request.
        $this->home($farmer);

        $this->assertSame(0, MonthlyWeatherPrediction::query()->count());
        $this->assertFalse(Cache::get(FarmerDashboardService::predictionFailureKey($farmer->id))['lacks_records']);
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/predict'));
        $this->assertStringContainsString('temporarily unavailable', $this->card($this->home($farmer)));
    }

    // ------------------------------------------------------------ one estimate everywhere

    public function test_the_dashboard_and_the_predictions_page_show_the_same_estimate(): void
    {
        $farmer = $this->farmer();
        Queue::fake();
        $this->saved($farmer, 4.37);

        $this->assertStringContainsString('4.37 t/ha', $this->card($this->home($farmer)));
        $predictions = $this->actingAs($farmer)->get(route('weather-predictions.index'))->assertOk()->getContent();
        $this->assertStringContainsString('4.37 <small>t/ha</small>', $predictions);
    }

    // ------------------------------------------------------------ the scheduled warm-up

    public function test_the_warm_up_prepares_every_farmer_and_stops_asking_a_service_that_is_down(): void
    {
        $this->model(4.4);
        $farmers = collect(range(1, 5))->map(fn (int $i) => $this->farmer(email: "f{$i}@example.test"));
        $this->artisan('iclimate:warm-farmer-yields')->expectsOutputToContain('Yield estimates prepared: 5')->assertSuccessful();
        $farmers->each(fn (User $f) => $this->assertSame(4.4, FarmerDashboardService::storedEstimate($f->id)['yield_tons_per_hectare']));

        // The service goes down: three failures in a row stop the run; every farmer keeps their estimate.
        $this->model(null);
        $this->modelCalls = 0;
        $this->artisan('iclimate:warm-farmer-yields')->expectsOutputToContain('not answering')->assertFailed();
        $this->assertSame(3, $this->modelCalls);
        $farmers->each(fn (User $f) => $this->assertSame(4.4, FarmerDashboardService::storedEstimate($f->id)['yield_tons_per_hectare']));
    }
}
