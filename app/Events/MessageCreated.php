<?php

namespace App\Events;

use App\Models\ConversationMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageCreated implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public ConversationMessage $message)
    {
        $this->message->loadMissing('sender');
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("conversation.{$this->message->conversation_id}")];
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => (int) $this->message->conversation_id,
            'message' => [
                'id' => (int) $this->message->id,
                'body' => $this->message->body,
                'attachment_url' => $this->message->attachment_path
                    ? route('messages.attachment', [$this->message->conversation_id, $this->message])
                    : null,
                'attachment_name' => $this->message->attachment_name,
                'sender_id' => (int) $this->message->sender_id,
                'sender_name' => $this->message->sender?->name,
                'created_at' => $this->message->created_at?->toIso8601String(),
                'human_time' => $this->message->humanTimestamp(),
                'read_at' => $this->message->read_at?->toIso8601String(),
            ],
        ];
    }
}
