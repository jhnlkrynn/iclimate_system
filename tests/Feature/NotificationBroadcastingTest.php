<?php

namespace Tests\Feature;

use App\Events\NotificationChanged;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NotificationBroadcastingTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_notification_broadcasts_after_commit_to_only_the_recipient(): void
    {
        Event::fake([NotificationChanged::class]);
        $mao = User::factory()->maoPersonnel()->create();
        $farmer = User::factory()->farmer()->create();
        $outsider = User::factory()->farmer()->create();

        $this->actingAs($mao)->postJson(route('notifications.store'), [
            'recipient_scope' => 'specific',
            'user_id' => $farmer->id,
            'title' => 'Instant notice',
            'message' => 'This should arrive now.',
            'type' => 'Announcement',
        ])->assertOk();

        Event::assertDispatched(NotificationChanged::class, function (NotificationChanged $event) use ($farmer, $outsider): bool {
            $channel = $event->broadcastOn()[0];
            $payload = $event->broadcastWith();

            return $event->recipientId === $farmer->id
                && $event->recipientId !== $outsider->id
                && $event->action === 'created'
                && $channel->name === "private-user.{$farmer->id}"
                && $payload['notification_id'] === $event->notification?->id
                && $payload['unread'] === 1;
        });
    }

    public function test_read_and_mark_all_changes_broadcast_authoritative_unread_counts(): void
    {
        $farmer = User::factory()->farmer()->create();
        $first = Notification::factory()->create(['user_id' => $farmer->id, 'is_read' => false]);
        $second = Notification::factory()->create(['user_id' => $farmer->id, 'is_read' => false]);
        Event::fake([NotificationChanged::class]);

        $this->actingAs($farmer)->patchJson(route('notifications.mark-read', $first))->assertOk()->assertJsonPath('unread', 1);
        Event::assertDispatched(NotificationChanged::class, fn (NotificationChanged $event): bool => $event->action === 'updated' && $event->recipientId === $farmer->id);

        $this->actingAs($farmer)->postJson(route('notifications.mark-all-read'))->assertOk()->assertJsonPath('unread', 0);
        Event::assertDispatched(NotificationChanged::class, fn (NotificationChanged $event): bool => $event->action === 'sync' && $event->recipientId === $farmer->id && $event->broadcastWith()['unread'] === 0);
        $this->assertTrue($second->fresh()->is_read);
    }

    public function test_notification_broadcast_is_not_dispatched_when_creation_rolls_back(): void
    {
        Event::fake([NotificationChanged::class]);
        $farmer = User::factory()->farmer()->create();

        try {
            DB::transaction(function () use ($farmer): void {
                Notification::query()->create([
                    'user_id' => $farmer->id,
                    'title' => 'Rolled back',
                    'message' => 'Not delivered.',
                    'type' => 'Announcement',
                    'is_read' => false,
                ]);
                throw new \RuntimeException('notification rollback test');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('notification rollback test', $exception->getMessage());
        }

        Event::assertNotDispatched(NotificationChanged::class);
        $this->assertDatabaseMissing('notifications', ['title' => 'Rolled back']);
    }
}
