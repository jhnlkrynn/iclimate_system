<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
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
 * embeds a data version (a CacheScope token, see scoped()) is rebuilt right after a data change rather than shown stale.
 *
 * A build that throws is never hidden: on the page it propagates as before (500, or the 503 handler for a
 * dropped database connection); after the response it is logged and the last good copy stays in place.
 */
final class StaleCache
{
    /** Seconds a first, cold build may take on a page request (the scheduled warm-up normally does it). */
    public const COLD_BUILD_SECONDS = 120;

    private const LOCK_SECONDS = 300;

    /** How long a request waits for another request's build of the same payload before building it itself. */
    private const COLD_WAIT_SECONDS = 60;

    /** How long a last good copy is kept (longer than any stale window). */
    private const LATEST_KEEP_SECONDS = 86400;

    /**
     * > 0 while a payload is being built. Inside a build, a nested stale-while-revalidate cache is built fresh instead
     * of handing back its last copy: otherwise a prepared page would keep that stale part for its whole lifetime.
     */
    private static int $building = 0;

    private function __construct() {}

    /** How old the last good copy may be and still be shown while it is rebuilt. */
    public const MAX_STALE_SECONDS = 900;

    /**
     * @param  string|null  $latestKey  where the last good copy lives; a version-free key lets a monitoring view
     *                                  keep showing its last copy (with its time) right after a data change
     */
    public static function remember(string $key, int $seconds, Closure $build, ?string $latestKey = null, int $maxStale = self::MAX_STALE_SECONDS): mixed
    {
        return self::resolve($key, null, $seconds, $build, $latestKey, $maxStale);
    }

    /**
     * Same, for a value whose key carries a CacheScope token ($keyFor builds the key from a token). A build that
     * writes data the consumer reads (a weather snapshot) stores its value under the token that includes those
     * writes, so the very next request finds it instead of building it again.
     *
     * @param  Closure(string): string  $keyFor
     */
    public static function scoped(string $consumer, Closure $keyFor, int $seconds, Closure $build, ?string $latestKey = null, int $maxStale = self::MAX_STALE_SECONDS): mixed
    {
        $snapshot = CacheScope::snapshot($consumer);

        return self::resolve($keyFor(CacheScope::tokenAt($snapshot)), fn (): string => $keyFor(CacheScope::settledToken($snapshot)), $seconds, $build, $latestKey, $maxStale);
    }

    /** @param (Closure(): string)|null $storeKey  where a fresh build is stored (default: $key) */
    private static function resolve(string $key, ?Closure $storeKey, int $seconds, Closure $build, ?string $latestKey, int $maxStale): mixed
    {
        $fixedLatest = $latestKey;
        $latestKey ??= $key.':latest';
        $build = static fn () => self::building($build);
        $store = function (mixed $value) use ($key, $storeKey, $seconds, $fixedLatest): void {
            $target = $storeKey ? $storeKey() : $key;
            self::put($target, $seconds, $value, $fixedLatest ?? $target.':latest');
        };

        $fresh = Cache::get($key);
        if ($fresh !== null) {
            return $fresh;
        }

        $latest = Cache::get($latestKey);
        $usable = is_array($latest) && array_key_exists('value', $latest) && (int) ($latest['at'] ?? 0) >= now()->getTimestamp() - $maxStale;
        if ($usable && InteractiveRequest::isPageRequest() && self::$building === 0) {
            self::refreshAfterResponse($key, $build, $store);

            return $latest['value'];
        }

        if (InteractiveRequest::isPageRequest()) {
            self::allowTime(self::COLD_BUILD_SECONDS);
        }

        // Single flight: when several requests find no usable copy at once, the first builds and the others wait for
        // it and read what it stored. A wait that runs out builds anyway rather than leaving the page hanging.
        $lock = Cache::lock('stale-cache:building:'.$key, self::LOCK_SECONDS);
        $acquired = false;
        try {
            $acquired = (bool) $lock->block(self::COLD_WAIT_SECONDS);
        } catch (LockTimeoutException) {
        }

        try {
            if ($acquired && ($fresh = Cache::get($storeKey ? $storeKey() : $key)) !== null) {
                return $fresh;                                        // built by the request this one waited for
            }

            $started = microtime(true);
            $value = $build();
            $store($value);
        } finally {
            if ($acquired) {
                $lock->release();
            }
        }

        $took = microtime(true) - $started;
        if ($took > 5 && InteractiveRequest::isPageRequest()) {
            Log::info('Dashboard data built while a page waited (no earlier copy to show).', ['key' => $key, 'seconds' => round($took, 1)]);
        }

        return $value;
    }

    /**
     * An explicit Refresh: rebuild now and store the result, once. A request that arrives while another Refresh of the
     * same payload is running waits for it (up to $waitSeconds) and returns that result instead of rebuilding again. If
     * it cannot wait that long it gets the prepared copy, which carries its own build time.
     *
     * @param  Closure(string): string  $keyFor
     */
    public static function refreshScoped(string $consumer, Closure $keyFor, int $seconds, Closure $build, ?string $latestKey = null, int $waitSeconds = 60): mixed
    {
        $askedAt = now()->getTimestamp();
        $lock = Cache::lock(self::refreshLockName($keyFor), self::LOCK_SECONDS);

        try {
            $lock->block($waitSeconds);
        } catch (LockTimeoutException) {
            return self::scoped($consumer, $keyFor, $seconds, $build, $latestKey);
        }

        try {
            // A rebuild that finished while this request waited is the result it asked for.
            $current = $keyFor(CacheScope::token($consumer));
            $latest = Cache::get($latestKey ?? $current.':latest');
            if (Cache::has($current) && is_array($latest) && (int) ($latest['at'] ?? 0) >= $askedAt) {
                return $latest['value'];
            }

            return self::buildAndStore($consumer, $keyFor, $seconds, $build, $latestKey);
        } finally {
            $lock->release();
        }
    }

    /**
     * Background preparation (the warm-up command): rebuild and store unless a rebuild of the same payload is already
     * running. Returns false when it left the work to that other rebuild. A failed build throws and leaves the last good
     * copy in place.
     *
     * @param  Closure(string): string  $keyFor
     */
    public static function prepareScoped(string $consumer, Closure $keyFor, int $seconds, Closure $build, ?string $latestKey = null): bool
    {
        $lock = Cache::lock(self::refreshLockName($keyFor), self::LOCK_SECONDS);
        if (! $lock->get()) {
            return false;
        }

        try {
            self::buildAndStore($consumer, $keyFor, $seconds, $build, $latestKey);

            return true;
        } finally {
            $lock->release();
        }
    }

    /**
     * Whether a prepared payload needs building: there is none for the current data (never built, or the data it
     * reads changed), or the last one is at least $afterSeconds old.
     *
     * @param  Closure(string): string  $keyFor
     */
    public static function isDue(string $consumer, Closure $keyFor, int $afterSeconds, ?string $latestKey = null): bool
    {
        $current = $keyFor(CacheScope::token($consumer));
        if (! Cache::has($current)) {
            return true;
        }

        $latest = Cache::get($latestKey ?? $current.':latest');

        return ! is_array($latest) || (int) ($latest['at'] ?? 0) <= now()->getTimestamp() - $afterSeconds;
    }

    /** @param Closure(string): string $keyFor */
    private static function buildAndStore(string $consumer, Closure $keyFor, int $seconds, Closure $build, ?string $latestKey): mixed
    {
        $snapshot = CacheScope::snapshot($consumer);
        self::allowTime(self::COLD_BUILD_SECONDS);
        $value = self::building($build);
        $target = $keyFor(CacheScope::settledToken($snapshot));
        self::put($target, $seconds, $value, $latestKey ?? $target.':latest');

        return $value;
    }

    private static function building(Closure $build): mixed
    {
        self::$building++;
        try {
            return $build();
        } finally {
            self::$building--;
        }
    }

    /** One lock per payload, whatever the data version: two refreshes of the same payload never run at once. */
    private static function refreshLockName(Closure $keyFor): string
    {
        return 'stale-cache:refresh:'.$keyFor('any');
    }

    /** Store a freshly built value as both the fresh copy and the last good copy (warm-up commands, Refresh buttons). */
    public static function put(string $key, int $seconds, mixed $value, ?string $latestKey = null): void
    {
        Cache::put($key, $value, now()->addSeconds($seconds));
        // Kept a day, well past any stale window: a key per data version must not pile up in the cache forever.
        Cache::put($latestKey ?? $key.':latest', ['at' => now()->getTimestamp(), 'value' => $value], now()->addSeconds(self::LATEST_KEEP_SECONDS));
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

    /** @param Closure(mixed): void $store */
    private static function refreshAfterResponse(string $key, Closure $build, Closure $store): void
    {
        $lock = 'stale-cache:rebuilding:'.$key;
        if (! Cache::add($lock, true, now()->addSeconds(self::LOCK_SECONDS))) {
            return;                                                    // another request is already rebuilding it
        }

        app()->terminating(function () use ($key, $build, $store, $lock): void {
            try {
                self::allowTime(self::COLD_BUILD_SECONDS);
                $store($build());
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
