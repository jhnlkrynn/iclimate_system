<?php

namespace App\Events;

use App\Models\Notification;
use App\Support\SampleData;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $recipientId,
        public string $action,
        public ?Notification $notification = null,
    ) {
        $this->notification?->loadMissing('user');
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->recipientId}")];
    }

    public function broadcastAs(): string
    {
        return 'notification.changed';
    }

    public function broadcastWith(): array
    {
        $notification = $this->notification;
        $unread = SampleData::without(Notification::query(), 'notifications')
            ->where('user_id', $this->recipientId)
            ->where('is_read', false)
            ->count();

        return [
            'action' => $this->action,
            'notification_id' => $notification?->getKey(),
            'unread' => $unread,
            'notification' => $notification && $this->action !== 'deleted' ? [
                'id' => (int) $notification->getKey(),
                'title' => $notification->title,
                'message' => $notification->message,
                'type' => $notification->type,
                'is_read' => (bool) $notification->is_read,
                'created_at' => $notification->created_at?->toIso8601String(),
                'updated_at' => $notification->updated_at?->toIso8601String(),
            ] : null,
        ];
    }
}
