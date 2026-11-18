<?php

namespace App\Broadcasting;

use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcherContract;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Realtime delivery must never break the action that caused it.
 *
 * iClimate's events broadcast immediately (ShouldBroadcastNow) and only after the database transaction commits
 * (ShouldDispatchAfterCommit). Laravel's manager lets a broadcast failure escape: with Reverb stopped the
 * BroadcastException turned an already-saved notification or message into a 500, and it also stopped the
 * remaining after-commit callbacks. Here:
 *
 * - A delivery failure (BroadcastException: Reverb stopped, unreachable, timed out) is caught and the update is
 *   handed to the queue as a bounded RetryBroadcastEvent, so delivery resumes when Reverb is back.
 * - Circuit breaker: after a failure Reverb is treated as unavailable for a short window (shared through the
 *   cache). Within it no immediate attempt is made, so a notification sent to every farmer does not wait for
 *   ~900 connection timeouts, and only one warning is logged per window instead of one per update.
 * - Retries queued per window are capped; updates beyond the cap are dropped and counted (pages re-read the
 *   saved data by polling and on reconnect), so an outage cannot flood the jobs table.
 * - Any other exception (e.g. the Pusher client rejecting a channel name) fails the same way every time: it is
 *   logged as an error and not retried.
 *
 * Logs carry the event class and the reason only, never the payload (message text) or the request URL (it holds
 * the Reverb app key and signature).
 */
class ResilientBroadcastManager extends BroadcastManager
{
    private const OPEN_UNTIL_KEY = 'broadcasting:reverb-unavailable-until';

    private const WINDOW_RETRIES_KEY = 'broadcasting:retries-queued:';

    private const WINDOW_DROPPED_KEY = 'broadcasting:retries-dropped:';

    /** In-process copy of the circuit, so one request does not read the cache for every event. */
    private int $openUntil = 0;

    public function queue($event)
    {
        if (! $this->broadcastsImmediately($event)) {
            return parent::queue($event);
        }

        if ($this->circuitIsOpen()) {
            $this->deferRetry($event, null);

            return;
        }

        try {
            $this->app->make(BusDispatcherContract::class)->dispatchNow(new BroadcastEvent(clone $event));
        } catch (BroadcastException $exception) {
            $this->openCircuit($event, $exception);
            $this->deferRetry($event, $exception);
        } catch (Throwable $exception) {
            Log::error('Realtime update rejected before delivery; not retried. Fix the event or channel.', [
                'event' => $event::class, 'reason' => self::safeReason($exception),
            ]);
        }
    }

    /** Whether Reverb is currently treated as unavailable (also used by the retry job). */
    public function circuitIsOpen(): bool
    {
        $now = now()->getTimestamp();
        if ($this->openUntil > $now) {
            return true;
        }

        try {
            $this->openUntil = (int) $this->app['cache']->get(self::OPEN_UNTIL_KEY, 0);
        } catch (Throwable) {
            return false;
        }

        return $this->openUntil > $now;
    }

    private function openCircuit(object $event, Throwable $exception): void
    {
        $seconds = max(1, (int) $this->setting('circuit_seconds', 30));
        $this->openUntil = now()->getTimestamp() + $seconds;

        try {
            $this->app['cache']->put(self::OPEN_UNTIL_KEY, $this->openUntil, $seconds);
        } catch (Throwable) {
            // The in-process copy still protects this request.
        }

        Log::warning("Realtime updates could not be delivered; Reverb is treated as unavailable for {$seconds}s and updates are queued for retry.", [
            'event' => $event::class, 'reason' => self::safeReason($exception),
        ]);
    }

    private function deferRetry(object $event, ?Throwable $exception): void
    {
        if ($this->setting('enabled', true) === false) {
            return;
        }

        $window = (int) floor(now()->getTimestamp() / max(1, (int) $this->setting('circuit_seconds', 30)));
        $cap = max(0, (int) $this->setting('max_queued_per_window', 100));

        if ($this->countInWindow(self::WINDOW_RETRIES_KEY.$window) > $cap) {
            if ($this->countInWindow(self::WINDOW_DROPPED_KEY.$window) === 1) {
                Log::warning("Realtime retry limit ({$cap}) reached while Reverb is unavailable; further updates in this window are not queued. Pages recover by polling.", [
                    'event' => $event::class,
                ]);
            }

            return;
        }

        try {
            $this->app->make('queue')
                ->connection($this->setting('connection'))
                ->laterOn(
                    (string) $this->setting('queue', RetryBroadcastEvent::QUEUE),
                    max(0, (int) $this->setting('first_delay_seconds', 10)),
                    new RetryBroadcastEvent(clone $event),
                );
        } catch (Throwable $queueFailure) {
            Log::error('Realtime update could not be delivered or queued for retry; clients recover by polling.', [
                'event' => $event::class,
                'reason' => $exception ? self::safeReason($exception) : 'Reverb unavailable',
                'queue_reason' => self::safeReason($queueFailure),
            ]);
        }
    }

    /** Increments a short-lived counter; when the cache is unavailable every update counts as the first. */
    private function countInWindow(string $key): int
    {
        try {
            $cache = $this->app['cache'];
            $cache->add($key, 0, 120);

            return (int) $cache->increment($key);
        } catch (Throwable) {
            return 1;
        }
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        return $this->app['config']->get('broadcasting.retry.'.$key, $default);
    }

    private function broadcastsImmediately(mixed $event): bool
    {
        return $event instanceof ShouldBroadcastNow
            || (is_object($event) && method_exists($event, 'shouldBroadcastNow') && $event->shouldBroadcastNow());
    }

    /**
     * The exception class and the first part of its message, without the request URL (it carries the Reverb
     * app id, auth key and signature in its query string).
     */
    public static function safeReason(Throwable $exception): string
    {
        $message = (string) preg_replace('/\s+for\s+https?:\/\/\S+/i', '', $exception->getMessage());
        $message = (string) preg_replace('/https?:\/\/\S+/i', '[url]', $message);

        return class_basename($exception).': '.mb_substr(trim($message), 0, 180);
    }
}
