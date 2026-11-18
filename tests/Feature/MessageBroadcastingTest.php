<?php

namespace Tests\Feature;

use App\Events\MessageCreated;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MessageBroadcastingTest extends TestCase
{
    use RefreshDatabase;

    public function test_created_message_broadcasts_after_commit_on_its_private_conversation_channel(): void
    {
        Event::fake([MessageCreated::class]);
        [$farmer, $mao, $conversation] = $this->conversation();

        $this->actingAs($farmer)
            ->postJson(route('messages.reply', $conversation), ['body' => 'Live update', 'client_message_id' => 'live-1'])
            ->assertOk();

        Event::assertDispatched(MessageCreated::class, function (MessageCreated $event) use ($conversation, $farmer): bool {
            $channel = $event->broadcastOn()[0];

            return $event->message->conversation_id === $conversation->id
                && $event->message->sender_id === $farmer->id
                && $channel->name === "private-conversation.{$conversation->id}"
                && $event->broadcastAs() === 'message.created'
                && $event->broadcastWith()['message']['body'] === 'Live update';
        });
    }

    public function test_idempotent_client_retry_does_not_broadcast_a_duplicate(): void
    {
        Event::fake([MessageCreated::class]);
        [$farmer, , $conversation] = $this->conversation();
        $payload = ['body' => 'Only once', 'client_message_id' => 'same-client-id'];

        $this->actingAs($farmer)->postJson(route('messages.reply', $conversation), $payload)->assertOk();
        $this->actingAs($farmer)->postJson(route('messages.reply', $conversation), $payload)->assertOk();

        Event::assertDispatchedTimes(MessageCreated::class, 1);
        $this->assertSame(1, ConversationMessage::query()->where('conversation_id', $conversation->id)->count());
    }

    public function test_message_broadcast_is_not_dispatched_when_the_transaction_rolls_back(): void
    {
        Event::fake([MessageCreated::class]);
        [, $mao, $conversation] = $this->conversation();

        try {
            DB::transaction(function () use ($conversation, $mao): void {
                $message = $conversation->messages()->create([
                    'sender_id' => $mao->id,
                    'body' => 'Rolled back',
                ]);

                MessageCreated::dispatch($message);
                throw new \RuntimeException('rollback test');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('rollback test', $exception->getMessage());
        }

        Event::assertNotDispatched(MessageCreated::class);
        $this->assertDatabaseMissing('conversation_messages', ['body' => 'Rolled back']);
    }

    private function conversation(): array
    {
        $farmer = User::factory()->farmer()->create();
        $mao = User::factory()->maoPersonnel()->create();
        $conversation = Conversation::query()->create([
            'participant_one_id' => min($farmer->id, $mao->id),
            'participant_two_id' => max($farmer->id, $mao->id),
        ]);

        return [$farmer, $mao, $conversation];
    }
}
