<?php

namespace Tests\Feature;

use App\Events\DashboardChanged;
use App\Jobs\GenerateMonthlyWeatherForecast;
use App\Jobs\RunPythonAnalytics;
use App\Models\AnalyticsRun;
use App\Models\BarangayProfile;
use App\Models\MaintenanceRecord;
use App\Models\SystemLog;
use App\Models\User;
use App\Support\CacheScope;
use App\Support\QueueWorkerHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * 2026-11-11 real-time completion: the remaining pages signal and update while open, scheduled analytics are not
 * queued into a stopped worker, and the same queued work is never added twice.
 */
class RealtimeCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_newly_tracked_records_signal_the_roles_whose_pages_show_them(): void
    {
        $cases = [
            'barangays' => [fn () => BarangayProfile::query()->updateOrCreate(['barangay' => 'Kapito'], ['notes' => 'Realtime test '.uniqid()]), ['farmer', 'mao']],
            'maintenance' => [fn () => MaintenanceRecord::query()->create(['title' => 'Disk check', 'category' => 'Server', 'status' => 'Completed', 'performed_at' => now()]), []],
            'system-logs' => [fn () => SystemLog::query()->create(['action' => 'Login', 'details' => 'test']), []],
        ];

        foreach ($cases as $area => [$write, $roles]) {
            Event::fake([DashboardChanged::class]);
            $write();
            if ($roles === []) {
                Event::assertNotDispatched(DashboardChanged::class);
                continue;
            }
            Event::assertDispatched(DashboardChanged::class, function (DashboardChanged $event) use ($area, $roles): bool {
                $channels = array_map(fn ($channel) => $channel->name, $event->broadcastOn());
                $expected = array_map(fn (string $role) => "private-dashboard.{$role}", $roles);
                sort($channels);
                sort($expected);

                return in_array($area, $event->areas, true) && $channels === $expected
                    && array_keys($event->broadcastWith()) === ['change_id', 'areas'];
            });
        }
    }

    public function test_reading_an_alert_signals_the_mao_send_alert_history(): void
    {
        $this->assertContains('notification-reads', CacheScope::modelAreas(\App\Models\Notification::class, ['is_read', 'read_at']));
        $this->assertNotContains('notification-reads', CacheScope::modelAreas(\App\Models\Notification::class, ['title']));
    }

    public function test_bulk_signal_retires_caches_and_tells_open_pages_once(): void
    {
        Event::fake([DashboardChanged::class]);
        $before = CacheScope::token('heat-map-layers');

        CacheScope::signal('yield');

        $this->assertNotSame($before, CacheScope::token('heat-map-layers'));
        Event::assertDispatchedTimes(DashboardChanged::class, 1);
        Event::assertDispatched(DashboardChanged::class, fn (DashboardChanged $event): bool => $event->areas === ['yield']);
    }

    public function test_scheduled_analytics_are_skipped_while_no_queue_worker_runs(): void
    {
        config(['services.farming_ai.queue_connection' => 'database', 'queue.connections.database.driver' => 'database']);
        Cache::forget('queue-worker:heartbeat');
        Queue::fake();

        $this->artisan('iclimate:automation-run --force-weather')
            ->expectsOutputToContain('no queue worker is running')
            ->assertSuccessful();

        $this->assertSame(0, AnalyticsRun::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_scheduled_analytics_queue_once_while_the_worker_runs_and_never_twice(): void
    {
        config(['services.farming_ai.queue_connection' => 'database']);
        Cache::put('queue-worker:heartbeat', time(), 600);
        Queue::fake();

        $this->artisan('iclimate:automation-run --force-weather')->assertSuccessful();
        $this->assertSame(2, AnalyticsRun::query()->where('status', AnalyticsRun::STATUS_PENDING)->count());

        // The first round is still waiting: the next round adds nothing.
        $this->artisan('iclimate:automation-run --force-weather')
            ->expectsOutputToContain('still in progress')
            ->assertSuccessful();
        $this->assertSame(2, AnalyticsRun::query()->count());
        Queue::assertPushed(RunPythonAnalytics::class, 2);

        $log = SystemLog::query()->where('action', 'iclimate.automation')->latest('id')->get()
            ->first(fn (SystemLog $candidate): bool => count(data_get(json_decode((string) $candidate->details, true), 'context.queued', [])) === 2);
        $this->assertNotNull($log);
        $details = json_decode((string) $log->details, true);
        $this->assertTrue(data_get($details, 'context.ok'));
        $this->assertCount(2, data_get($details, 'context.queued'));
    }

    public function test_heartbeat_reports_a_worker_only_while_it_beats(): void
    {
        Cache::forget('queue-worker:heartbeat');
        $this->assertFalse(QueueWorkerHeartbeat::alive('database'));
        $this->assertTrue(QueueWorkerHeartbeat::alive('sync'));

        Cache::put('queue-worker:heartbeat', time() - QueueWorkerHeartbeat::ALIVE_FOR - 5, 600);
        $this->assertFalse(QueueWorkerHeartbeat::alive('database'));

        Cache::put('queue-worker:heartbeat', time(), 600);
        $this->assertTrue(QueueWorkerHeartbeat::alive('database'));
    }

    public function test_a_forecast_month_already_waiting_in_the_queue_is_not_added_again(): void
    {
        DB::table('jobs')->insert([
            'queue' => 'analytics',
            'payload' => json_encode(['displayName' => GenerateMonthlyWeatherForecast::class, 'data' => ['command' => serialize(new GenerateMonthlyWeatherForecast('2027-01-01'))]]),
            'attempts' => 0, 'reserved_at' => null, 'available_at' => time(), 'created_at' => time(),
        ]);

        $this->assertTrue(QueueWorkerHeartbeat::alreadyWaiting(GenerateMonthlyWeatherForecast::class, '2027-01-01', 'database'));
        $this->assertFalse(QueueWorkerHeartbeat::alreadyWaiting(GenerateMonthlyWeatherForecast::class, '2027-02-01', 'database'));
    }

    public function test_system_health_signals_only_when_a_check_result_changes(): void
    {
        $it = User::factory()->maoPersonnel()->create();
        $controller = app(\App\Services\Maintenance\SystemHealthCache::class);
        Cache::forget('system-health:fingerprint');

        Event::fake([DashboardChanged::class]);
        $controller->warm(app(\App\Services\SystemHealthService::class));
        Event::assertNotDispatched(DashboardChanged::class);

        Event::fake([DashboardChanged::class]);
        $controller->warm(app(\App\Services\SystemHealthService::class));
        Event::assertNotDispatched(DashboardChanged::class);
        $this->assertNotNull($it);
    }

    public function test_remaining_pages_mark_their_live_regions(): void
    {
        $mao = User::factory()->maoPersonnel()->create(['is_mao_head' => true]);
        $it = User::factory()->maoPersonnel()->create();

        foreach ([
            'mao.alerts.index' => 'data-live-areas="advisories,notification-reads"',
            'mao.barangays.index' => 'data-live-region="barangays"',
            'mao.activity-log.index' => 'data-live-areas="mao-activity"',
            'mao.planner.index' => 'data-live-region="feed-posts"',
            'analysis.weather' => 'data-live-region="weather-analysis"',
            'analysis.impact' => 'data-live-region="impact-history"',
            'analysis.forecast' => 'data-live-region="forecast-history"',
        ] as $route => $marker) {
            $this->actingAs($mao)->get(route($route))->assertOk()->assertSee($marker, false);
        }

    }

    public function test_dashboards_ignore_list_only_areas_and_lists_keep_unsaved_forms(): void
    {
        $realtime = file_get_contents(resource_path('js/dashboard-realtime.js'));
        $live = file_get_contents(resource_path('js/live-page.js'));
        $heatMap = file_get_contents(resource_path('js/heat-map.js'));

        $this->assertStringContainsString('LIST_ONLY_AREAS', $realtime);
        $this->assertStringContainsString("'system-logs'", $realtime);
        $this->assertStringContainsString('hasUnsavedInput(element)', $live);
        $this->assertStringContainsString('iclimate:live-region-updated', $live);
        $this->assertStringContainsString("initLiveRefresh({ root, refresh: liveRefresh, areas: ['risk', 'weather', 'barangays', 'planting', 'yield'] })", $heatMap);
    }
}
