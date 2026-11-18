<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Whether a queue worker is running right now. Every worker loop (AppServiceProvider: Queue::looping) writes the time
 * at most every BEAT_EVERY seconds; a beat newer than ALIVE_FOR means a worker is consuming jobs.
 *
 * Why (2026-11-11): with the scheduler running and the worker stopped, iclimate:automation-run queued two analytics
 * runs every 30 minutes and the stale-run cleanup failed each of them 5 minutes later (41 failed runs in 10 hours).
 * Scheduled producers ask this first and skip a round instead of filling the queue with work that cannot run.
 */
final class QueueWorkerHeartbeat
{
    private const KEY = 'queue-worker:heartbeat';

    /** Seconds between writes (a worker loops every few seconds; the cache is not written each time). */
    private const BEAT_EVERY = 20;

    /** A beat older than this means no worker (the worker sleeps at most 3 s between empty polls). */
    public const ALIVE_FOR = 120;

    private static int $lastBeat = 0;

    public static function beat(): void
    {
        $now = time();
        if ($now - self::$lastBeat < self::BEAT_EVERY) {
            return;
        }
        self::$lastBeat = $now;
        rescue(fn () => Cache::put(self::KEY, $now, self::ALIVE_FOR * 5), null, false);
    }

    /** Unix time of the last beat, or null when no worker has run recently enough to leave one. */
    public static function lastBeat(): ?int
    {
        $value = rescue(fn () => Cache::get(self::KEY), null, false);

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * True when queued work will be picked up: a worker beat recently, or the jobs run inline (sync connection, tests).
     */
    public static function alive(?string $connection = null): bool
    {
        $connection ??= (string) config('queue.default');
        if ((string) config("queue.connections.{$connection}.driver", $connection) === 'sync') {
            return true;
        }
        $last = self::lastBeat();

        return $last !== null && time() - $last <= self::ALIVE_FOR;
    }

    /**
     * True when a job of this class is already waiting (database queue only) whose payload contains $needle, so a
     * producer does not add the same work twice while the worker is behind or stopped.
     */
    public static function alreadyWaiting(string $jobClass, string $needle = '', ?string $connection = null): bool
    {
        $connection ??= (string) config('queue.default');
        if ((string) config("queue.connections.{$connection}.driver") !== 'database') {
            return false;
        }

        try {
            $table = (string) config("queue.connections.{$connection}.table", 'jobs');
            // The short class name: the payload JSON-escapes namespace backslashes, which LIKE treats differently per engine.
            $class = class_basename($jobClass);

            return DB::connection(config("queue.connections.{$connection}.connection"))->table($table)
                ->whereNull('reserved_at')
                ->where('payload', 'like', '%'.$class.'"%')   // "displayName":"App\\Jobs\\<Class>"
                ->when($needle !== '', fn ($q) => $q->where('payload', 'like', '%'.addcslashes($needle, '%_\\').'%'))
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
