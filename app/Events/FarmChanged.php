<?php

namespace App\Events;

use App\Models\Farm;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/** A private farm invalidation signal. It never carries farm names, locations, or other farm data. */
class FarmChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $farmId,
        public int $ownerId,
        public string $action,
        public string $eventId,
        public string $generatedAt,
    ) {}

    public static function forFarm(Farm $farm, string $action): self
    {
        return new self(
            (int) $farm->getKey(),
            (int) $farm->user_id,
            $action,
            (string) Str::uuid(),
            now()->toIso8601String(),
        );
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->ownerId}"),
            new PrivateChannel("farm.{$this->farmId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'farm.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->eventId,
            'area' => 'farms',
            'action' => $this->action,
            'farm_id' => $this->farmId,
            'generated_at' => $this->generatedAt,
        ];
    }
}
