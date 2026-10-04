<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Stale-while-revalidate for the shared dashboard payloads that cost dozens of Supabase round trips to build
 * (~0.4 s each from a distant client, so 80+ queries outlast PHP's 30 s limit and the page dies with a 500).
 *
 *   fresh copy                            → used as is (same as Cache::remember())
 *   expired, last good copy < $maxStale   → that copy is used now and rebuilt after the response is sent
 *   no copy, or the last one is too old   → built now; this build may take longer than the normal request limit
 *
 * The last good copy is kept under $latestKey. By default that is the key itself plus ":latest", so a key that
 * embeds a data version (DashboardData::version()) is rebuilt right after a data change rather than shown stale.
 *
 * A build that throws is never hidden: on the page it propagates as before (500, or the 503 handler for a
 * dropped database connection); after the response it is logged and the last good copy stays in place.
 */
final class StaleCache
{
    /** Seconds a first, cold build may take on a page request (the scheduled warm-up normally does it). */
    public const COLD_BUILD_SECONDS = 120;

    private const LOCK_SECONDS = 300;

    private function __construct() {}

    /** How old the last good copy may be and still be shown while it is rebuilt. */
    public const MAX_STALE_SECONDS = 900;

    /**
     * @param  string|null  $latestKey  where the last good copy lives; a version-free key lets a monitoring view
     *                                  keep showing its last copy (with its time) right after a data change
     */
    public static function remember(string $key, int $seconds, Closure $build, ?string $latestKey = null, int $maxStale = self::MAX_STALE_SECONDS): mixed
    {
        $latestKey ??= $key.':latest';

        $fresh = Cache::get($key);
        if ($fresh !== null) {
            return $fresh;
        }

        $latest = Cache::get($latestKey);
        $usable = is_array($latest) && array_key_exists('value', $latest) && (int) ($latest['at'] ?? 0) >= now()->getTimestamp() - $maxStale;
        if ($usable && InteractiveRequest::isPageRequest()) {
            self::refreshAfterResponse($key, $latestKey, $seconds, $build);

            return $latest['value'];
        }

        if (InteractiveRequest::isPageRequest()) {
            self::allowTime(self::COLD_BUILD_SECONDS);
        }
        $started = microtime(true);
        $value = $build();
        self::put($key, $seconds, $value, $latestKey);

        $took = microtime(true) - $started;
        if ($took > 5 && InteractiveRequest::isPageRequest()) {
            Log::info('Dashboard data built while a page waited (no earlier copy to show).', ['key' => $key, 'seconds' => round($took, 1)]);
        }

        return $value;
    }

    /** Store a freshly built value as both the fresh copy and the last good copy (warm-up commands, Refresh buttons). */
    public static function put(string $key, int $seconds, mixed $value, ?string $latestKey = null): void
    {
        Cache::put($key, $value, now()->addSeconds($seconds));
        Cache::forever($latestKey ?? $key.':latest', ['at' => now()->getTimestamp(), 'value' => $value]);
    }

    /**
     * Give the current request $seconds from now for a known long build: set_time_limit() restarts the clock, so a
     * rebuild after the response gets its own allowance. Never shortens a longer or unlimited (0) limit.
     */
    public static function allowTime(int $seconds): void
    {
        $limit = (int) ini_get('max_execution_time');
        if ($limit !== 0 && $limit <= $seconds && function_exists('set_time_limit')) {
            @set_time_limit($seconds);
        }
    }

    private static function refreshAfterResponse(string $key, string $latestKey, int $seconds, Closure $build): void
    {
        $lock = 'stale-cache:rebuilding:'.$key;
        if (! Cache::add($lock, true, now()->addSeconds(self::LOCK_SECONDS))) {
            return;                                                    // another request is already rebuilding it
        }

        app()->terminating(function () use ($key, $latestKey, $seconds, $build, $lock): void {
            try {
                self::allowTime(self::COLD_BUILD_SECONDS);
                self::put($key, $seconds, $build(), $latestKey);
            } catch (Throwable $exception) {
                // The page was already answered from the last good copy; keep it and say why it is not newer.
                Log::warning('Dashboard data could not be rebuilt after the response; the last good copy stays.', [
                    'key' => $key,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            } finally {
                Cache::forget($lock);
            }
        });
    }
}
