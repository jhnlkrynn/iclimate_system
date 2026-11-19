<?php

namespace Tests\Feature;

use App\Events\FarmChanged;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class FarmRealtimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_farm_change_broadcasts_only_minimal_private_metadata_after_commit(): void
    {
        $farmer = User::factory()->farmer()->create();
        Event::fake([FarmChanged::class]);

        $farm = Farm::create([
            'user_id' => $farmer->id,
            'farm_name' => 'Private Test Farm',
            'barangay' => 'Binubusan',
            'farm_type' => 'Rainfed',
            'is_primary' => true,
        ]);

        Event::assertDispatched(FarmChanged::class, function (FarmChanged $event) use ($farmer, $farm): bool {
            $channels = array_map(fn ($channel) => $channel->name, $event->broadcastOn());
            $payload = $event->broadcastWith();

            return in_array("private-user.{$farmer->id}", $channels, true)
                && in_array("private-farm.{$farm->id}", $channels, true)
                && $payload['action'] === 'created'
                && $payload['farm_id'] === $farm->id
                && ! isset($payload['farm_name'], $payload['barangay'], $payload['location_description']);
        });
    }

    public function test_farm_change_is_not_broadcast_when_transaction_rolls_back(): void
    {
        $farmer = User::factory()->farmer()->create();
        Event::fake([FarmChanged::class]);

        try {
            DB::transaction(function () use ($farmer): void {
                Farm::create(['user_id' => $farmer->id, 'farm_name' => 'Rolled Back Farm', 'barangay' => 'Binubusan', 'is_primary' => true]);
                throw new \RuntimeException('farm rollback test');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('farm rollback test', $exception->getMessage());
        }

        Event::assertNotDispatched(FarmChanged::class);
    }

    public function test_farm_realtime_frontend_refetches_authorized_page_and_deduplicates(): void
    {
        $js = file_get_contents(resource_path('js/farm-realtime.js'));
        $view = file_get_contents(resource_path('views/farmer-records/my-farm.blade.php'));

        $this->assertIsString($js);
        $this->assertStringContainsString("farm.changed", $js);
        $this->assertStringContainsString("payload.event_id", $js);
        $this->assertStringContainsString("window.__iClimateFarmEvents", $js);
        $this->assertStringContainsString("credentials: 'same-origin'", $js);
        $this->assertStringContainsString('data-farm-realtime-user-id', $view);
        $this->assertStringContainsString('data-farm-realtime-farm-id', $view);
    }
}
