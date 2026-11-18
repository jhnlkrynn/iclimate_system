<?php

namespace Tests\Feature;

use App\Events\DashboardChanged;
use App\Models\User;
use App\Support\CacheScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DashboardRealtimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_change_targets_only_authorized_user_channels_and_has_no_private_data(): void
    {
        $farmer = User::factory()->farmer()->create();
        $mao = User::factory()->maoPersonnel()->create();
        $it = User::factory()->maoPersonnel()->create();
        $inactive = User::factory()->farmer()->create(['status' => User::STATUS_INACTIVE]);
        Event::fake([DashboardChanged::class]);

        CacheScope::changed(User::class, [$farmer->id]);

        Event::assertDispatched(DashboardChanged::class, function (DashboardChanged $event) use ($farmer, $mao, $it, $inactive): bool {
            $channels = array_map(fn ($channel) => $channel->name, $event->broadcastOn());
            $payload = $event->broadcastWith();

            return in_array("private-user.{$farmer->id}", $channels, true)
                && in_array('private-dashboard.mao', $channels, true)
                && ! in_array('private-dashboard.it', $channels, true)
                && ! in_array("private-user.{$inactive->id}", $channels, true)
                && isset($payload['change_id'], $payload['areas'])
                && ! isset($payload['users'], $payload['farmers'], $payload['records']);
        });
    }

    public function test_dashboard_change_is_dispatched_after_commit_but_not_after_rollback(): void
    {
        $farmer = User::factory()->farmer()->create();
        Event::fake([DashboardChanged::class]);

        DB::transaction(function () use ($farmer): void {
            CacheScope::changed(User::class, [$farmer->id]);
        });
        Event::assertDispatched(DashboardChanged::class);

        Event::fake([DashboardChanged::class]);
        try {
            DB::transaction(function () use ($farmer): void {
                CacheScope::changed(User::class, [$farmer->id]);
                throw new \RuntimeException('dashboard rollback test');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('dashboard rollback test', $exception->getMessage());
        }

        Event::assertNotDispatched(DashboardChanged::class);
    }

    public function test_dashboard_frontend_deduplicates_change_ids_and_keeps_polling_fallback(): void
    {
        $js = file_get_contents(resource_path('js/dashboard-realtime.js'));

        $this->assertIsString($js);
        $this->assertStringContainsString("listen('.dashboard.changed'", $js);
        $this->assertStringContainsString('const seen = new Set()', $js);
        $this->assertStringContainsString('queued = true', $js);
        $this->assertStringContainsString("iclimate:realtime-state", $js);
        $this->assertStringContainsString('refresh({ realtime: true })', $js);
        $this->assertStringContainsString('iClimateInitDashboardRealtime', $js);
        $this->assertStringContainsString('iClimateInitDashboardRealtime', file_get_contents(resource_path('views/dashboards/mao.blade.php')));
        // Farmer Home no longer keeps its own 5-minute poll: it registers with the shared listener, whose bounded
        // fallback (FALLBACK_POLL_MS, only while visible and disconnected) covers a missed event.
        $farmer = file_get_contents(resource_path('js/farmer-dashboard.js'));
        $this->assertStringContainsString('initDashboardRealtime({ root, refresh });', $farmer);
        $this->assertStringNotContainsString('REFRESH_EVERY_MS', $farmer);
        $this->assertStringContainsString("window.iClimateRealtime?.state !== 'connected'", $js);
    }
}
