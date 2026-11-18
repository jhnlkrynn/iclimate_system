<?php

namespace Tests\Feature;

use App\Events\PredictionChanged;
use App\Models\AnalyticsRun;
use App\Models\User;
use App\Models\YieldPredictionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PredictionRealtimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_prediction_status_event_is_private_minimal_and_after_commit(): void
    {
        $farmer = User::factory()->farmer()->create();
        Event::fake([PredictionChanged::class]);

        DB::transaction(function () use ($farmer): void {
            AnalyticsRun::query()->create([
                'type' => 'rice_yield_prediction',
                'status' => AnalyticsRun::STATUS_PENDING,
                'requested_by' => $farmer->id,
                'input' => ['private' => 'do not broadcast'],
            ]);
        });

        Event::assertDispatched(PredictionChanged::class, function (PredictionChanged $event) use ($farmer): bool {
            $payload = $event->broadcastWith();
            $channels = array_map(fn ($channel) => $channel->name, $event->broadcastOn());

            return $event->recipientId === $farmer->id
                && $payload['kind'] === 'analytics_run'
                && $payload['status'] === 'requested'
                && in_array("private-user.{$farmer->id}", $channels, true)
                && ! isset($payload['input'], $payload['result'], $payload['error']);
        });
    }

    public function test_prediction_status_event_is_not_dispatched_after_rollback(): void
    {
        $farmer = User::factory()->farmer()->create();
        Event::fake([PredictionChanged::class]);

        try {
            DB::transaction(function () use ($farmer): void {
                YieldPredictionRequest::query()->create([
                    'user_id' => $farmer->id,
                    'scope' => YieldPredictionRequest::SCOPE_FARM,
                    'season' => 'Wet',
                    'farm_type' => 'Rainfed',
                    'target_month' => '2027-09-01',
                    'status' => YieldPredictionRequest::QUEUED,
                ]);
                throw new \RuntimeException('prediction rollback test');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('prediction rollback test', $exception->getMessage());
        }

        Event::assertNotDispatched(PredictionChanged::class);
    }

    public function test_prediction_frontend_refetches_authorized_endpoint_and_keeps_recovery_hooks(): void
    {
        $js = file_get_contents(resource_path('js/prediction-realtime.js'));
        $dashboardJs = file_get_contents(resource_path('js/dashboard-realtime.js'));

        $this->assertIsString($js);
        $this->assertIsString($dashboardJs);
        $this->assertStringContainsString('prediction.changed', $js);
        $this->assertStringContainsString('dataset.analyticsRun || element.dataset.predictionStatusUrl', $js);
        $this->assertStringContainsString('const seen = new Set()', $js);
        $this->assertStringContainsString('iclimate:realtime-state', $js);
        $this->assertStringContainsString('polling remains the recovery path', $js);
        $this->assertStringContainsString("'prediction.changed'].includes(normalized)", $dashboardJs);
        $this->assertStringContainsString('FALLBACK_POLL_MS = 60_000', $dashboardJs);
        $this->assertStringContainsString("state !== 'connected'", $dashboardJs);
    }

    public function test_yield_status_endpoint_is_private_to_the_owner_or_mao(): void
    {
        $farmer = User::factory()->farmer()->create();
        $other = User::factory()->farmer()->create();
        $request = YieldPredictionRequest::query()->create([
            'user_id' => $farmer->id,
            'scope' => YieldPredictionRequest::SCOPE_FARM,
            'season' => 'Wet',
            'farm_type' => 'Rainfed',
            'target_month' => '2027-09-01',
            'status' => YieldPredictionRequest::PROCESSING,
        ]);

        $this->actingAs($farmer)->getJson(route('analysis.yield.status', $request))->assertOk()->assertJsonPath('status', 'processing');
        $this->actingAs($other)->getJson(route('analysis.yield.status', $request))->assertForbidden();
        $this->actingAs(User::factory()->maoPersonnel()->create())->getJson(route('analysis.yield.status', $request))->assertForbidden();
    }
}
