<?php

namespace App\Broadcasting;

use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A realtime broadcast that failed immediately (Reverb stopped or unreachable), retried by the queue worker.
 *
 * Bounded: a few attempts with growing back-off, then the job is marked failed (visible in failed_jobs and the
 * log). A retry that only gets picked up after the freshness window (the worker was stopped for a while) is
 * dropped instead of sent late: by then the page has re-read the authoritative data by polling or reconnecting.
 * The payload is rebuilt when the job runs (broadcastWith() reads the current unread count and message), and a
 * record deleted in the meantime makes Laravel discard the job ($deleteWhenMissingModels). Channels, event names
 * and payloads are those of the original event, so private-channel authorization is unchanged.
 */
class RetryBroadcastEvent extends BroadcastEvent
{
    use InteractsWithQueue;

    public const QUEUE = 'broadcasts';

    /** Unix time after which the update is too old to be worth sending. */
    public int $freshUntil;

    public function __construct($event)
    {
        parent::__construct($event);

        $settings = (array) config('broadcasting.retry', []);
        $this->tries = max(1, (int) ($settings['tries'] ?? 4));
        $this->backoff = array_values(array_map('intval', (array) ($settings['backoff'] ?? [15, 60, 180])));
        $this->timeout = 30;
        $this->freshUntil = now()->addMinutes(max(1, (int) ($settings['fresh_minutes'] ?? 15)))->getTimestamp();
    }

    public function handle(\Illuminate\Contracts\Broadcasting\Factory $manager)
    {
        if (now()->getTimestamp() > $this->freshUntil) {
            Log::info('Realtime retry dropped: the update is older than the freshness window.', ['event' => $this->event::class]);

            return;
        }

        // Reverb was just found unavailable: use up this attempt without waiting for a connection timeout.
        if ($manager instanceof ResilientBroadcastManager && $manager->circuitIsOpen()) {
            // A queue worker may come up before Reverb. Releasing the job preserves the saved update and avoids
            // turning a short startup outage into an exhausted failed job. Once the circuit closes, the normal
            // broadcast attempt (and its existing bounded failure handling) resumes.
            $delay = min(
                max(1, (int) config('broadcasting.retry.circuit_seconds', 30)),
                max(1, $this->freshUntil - now()->getTimestamp()),
            );
            $this->release($delay);

            return;
        }

        parent::handle($manager);
    }

    public function failed(?Throwable $e = null): void
    {
        Log::warning('Realtime retry gave up after its last attempt; clients recover by polling.', [
            'event' => $this->event::class,
            'reason' => $e ? ResilientBroadcastManager::safeReason($e) : null,
        ]);

        parent::failed($e);
    }

    public function displayName()
    {
        return 'RetryBroadcast:'.parent::displayName();
    }
}
