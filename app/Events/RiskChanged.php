<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Minimal risk invalidation. Clients refetch their own authorized map data. */
class RiskChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /** @param list<string> $barangays */
    public function __construct(
        public array $barangays,
        public string $eventId,
        public string $generatedAt,
    ) {}

    public function broadcastOn(): array
    {
        return array_merge(
            array_map(fn (string $barangay): PrivateChannel => new PrivateChannel('barangay.'.\App\Support\LianBarangays::channelKey($barangay)), $this->barangays),
            [new PrivateChannel('dashboard.farmer'), new PrivateChannel('dashboard.mao'), new PrivateChannel('dashboard.it')],
        );
    }

    public function broadcastAs(): string
    {
        return 'risk.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->eventId,
            'area' => 'risk',
            'barangays' => $this->barangays,
            'generated_at' => $this->generatedAt,
        ];
    }
}
