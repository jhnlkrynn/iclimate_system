<?php

namespace Tests\Feature;

use App\Events\RiskChanged;
use App\Events\WeatherChanged;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WeatherRiskRealtimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_weather_and_risk_events_only_expose_invalidation_metadata(): void
    {
        foreach ([new WeatherChanged(['Binubusan'], 'weather-1', '2026-11-07T00:00:00Z'), new RiskChanged(['Binubusan'], 'risk-1', '2026-11-07T00:00:00Z')] as $event) {
            $channels = array_map(fn (PrivateChannel $channel): string => $channel->name, $event->broadcastOn());
            $payload = $event->broadcastWith();

            $this->assertContains('private-barangay.Binubusan', $channels);
            $this->assertContains('private-dashboard.farmer', $channels);
            $this->assertContains('private-dashboard.mao', $channels);
            $this->assertContains('private-dashboard.it', $channels);
            $this->assertSame(['event_id', 'area', 'barangays', 'generated_at'], array_keys($payload));
            $this->assertSame(['Binubusan'], $payload['barangays']);
            $this->assertArrayNotHasKey('weather', $payload);
            $this->assertArrayNotHasKey('risk', $payload);
            $this->assertArrayNotHasKey('farmers', $payload);
        }
    }

    public function test_barangay_authorization_keeps_weather_and_risk_scoped(): void
    {
        $farmer = User::factory()->farmer()->create(['barangay' => 'Binubusan']);
        $otherFarmer = User::factory()->farmer()->create(['barangay' => 'Prenza']);
        $it = User::factory()->state(['role' => 'IT Expert'])->create();
        $authorize = Broadcast::connection()->getChannels()->get('barangay.{barangay}');

        $this->assertTrue($authorize($farmer, 'Binubusan'));
        $this->assertFalse($authorize($farmer, 'Prenza'));
        $this->assertTrue($authorize($otherFarmer, 'Prenza'));
        $this->assertFalse($authorize($it, 'Binubusan'));
    }

    public function test_risk_event_is_not_dispatched_when_its_transaction_rolls_back(): void
    {
        Event::fake([RiskChanged::class]);

        try {
            DB::transaction(function (): void {
                RiskChanged::dispatch(['Binubusan'], 'risk-rollback', '2026-11-07T00:00:00Z');
                throw new \RuntimeException('rollback risk event');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('rollback risk event', $exception->getMessage());
        }

        Event::assertNotDispatched(RiskChanged::class);
    }

    public function test_weather_and_risk_frontends_deduplicate_events_and_refetch_authoritative_endpoints(): void
    {
        $dashboardRealtime = file_get_contents(resource_path('js/dashboard-realtime.js'));
        $riskMap = file_get_contents(resource_path('js/barangay-risk-map.js'));
        $view = file_get_contents(resource_path('views/partials/barangay-risk-map.blade.php'));

        $this->assertStringContainsString('weather.changed', $dashboardRealtime);
        $this->assertStringContainsString('seenRealtimeEvents', $riskMap);
        $this->assertStringContainsString('risk.changed', $riskMap);
        $this->assertStringContainsString('updateRisk(false)', $riskMap);
        $this->assertStringContainsString('data-realtime-role', $view);
        $this->assertStringContainsString('data-risk-map-url', $view);
    }
}
