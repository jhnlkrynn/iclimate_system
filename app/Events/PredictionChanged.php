<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A private status signal only. Prediction inputs and results remain behind the
 * existing authorized HTTP endpoints.
 */
class PredictionChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $reference,
        public string $kind,
        public string $status,
        public int $recipientId,
        public string $changedAt,
        public string $scope,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->recipientId)];
    }

    public function broadcastAs(): string
    {
        return 'prediction.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->reference.':'.$this->status,
            'reference' => $this->reference,
            'kind' => $this->kind,
            'status' => $this->status,
            'scope' => $this->scope,
            'changed_at' => $this->changedAt,
        ];
    }

    public static function forAnalyticsRun(\App\Models\AnalyticsRun $run): void
    {
        if (! $run->requested_by) {
            return;
        }

        self::dispatch(
            (string) $run->uuid,
            'analytics_run',
            self::publicStatus((string) $run->status),
            (int) $run->requested_by,
            ($run->updated_at ?? now())->toIso8601String(),
            (string) $run->type,
        );
    }

    public static function forYieldRequest(\App\Models\YieldPredictionRequest $request): void
    {
        if (! $request->user_id) {
            return;
        }

        self::dispatch(
            (string) $request->getKey(),
            'yield_request',
            self::publicStatus((string) $request->status),
            (int) $request->user_id,
            ($request->updated_at ?? now())->toIso8601String(),
            (string) $request->scope,
        );
    }

    private static function publicStatus(string $status): string
    {
        return match ($status) {
            'pending', 'queued' => 'requested',
            'done' => 'completed',
            default => $status,
        };
    }
}
