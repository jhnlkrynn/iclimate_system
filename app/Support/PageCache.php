<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Short-lived cache for page data, with two scopes that can never mix:
 *
 *  - shared():  the same for everyone who may see the page (counts, recent
 *               records, reference lists). One entry serves all users.
 *  - forUser(): private to one person (their notifications, their advisories).
 *               The user id is part of the key, so one person's data can
 *               never be served to another.
 *
 * Every scope has a version number inside its key. Bumping the version
 * (flushShared / flushUser) invalidates everything in the scope at once
 * without listing or deleting keys, so a data change shows up on the very next
 * page load instead of after the time-to-live.
 *
 * Why it exists: each database round trip to Supabase costs hundreds of
 * milliseconds, so a page that repeats the same queries on every load is
 * slow no matter how well the queries are written.
 */
class PageCache
{
    /** > 0 while a page is being built. Its own writes (a weather snapshot, a derived score) must not invalidate it. */
    private static int $building = 0;

    /**
     * @template T
     *
     * @param  array<string, mixed>  $vary  anything the result depends on (filters, dates)
     * @param  Closure(): T  $build
     * @return T
     */
    public static function shared(string $name, int $ttlSeconds, Closure $build, array $vary = []): mixed
    {
        $key = 'page:shared:v'.self::version('page:shared:version').':'.$name.self::suffix($vary);

        return self::remember($key, $ttlSeconds, $build);
    }

    /**
     * @template T
     *
     * @param  array<string, mixed>  $vary
     * @param  Closure(): T  $build
     * @return T
     */
    public static function forUser(int $userId, string $name, int $ttlSeconds, Closure $build, array $vary = []): mixed
    {
        // Also keyed by the shared version: a page for one person is built partly from shared data
        // (counts, recent records), so a shared change must rebuild it too. The reverse never happens.
        $key = 'page:user:'.$userId.':v'.self::version('page:user:'.$userId.':version').':s'.self::version('page:shared:version').':'.$name.self::suffix($vary);

        return self::remember($key, $ttlSeconds, $build);
    }

    /**
     * Rebuild one shared entry now and store the result for everyone (a manual "refresh" button),
     * without invalidating anything else.
     *
     * @template T
     *
     * @param  array<string, mixed>  $vary
     * @param  Closure(): T  $build
     * @return T
     */
    public static function refreshShared(string $name, int $ttlSeconds, Closure $build, array $vary = []): mixed
    {
        $key = 'page:shared:v'.self::version('page:shared:version').':'.$name.self::suffix($vary);
        $value = self::building($build);

        if ($ttlSeconds > 0) {
            Cache::put($key, ['v' => $value], $ttlSeconds);
        }

        return $value;
    }

    /** Everything cached with shared() is rebuilt on its next read. */
    public static function flushShared(): void
    {
        self::bump('page:shared:version');
    }

    /** Everything cached with forUser() for this person is rebuilt on its next read. */
    public static function flushUser(int $userId): void
    {
        self::bump('page:user:'.$userId.':version');
    }

    /**
     * The parts of a request's query string a page's result may depend on, made safe to use in a cache
     * key: only the named parameters, only short scalars, at most a dozen. A visitor can not fill the
     * cache with junk entries by inventing query strings.
     *
     * @param  list<string>  $allowed
     * @return array<string, string>
     */
    public static function queryVary(\Illuminate\Http\Request $request, array $allowed): array
    {
        $vary = [];

        foreach (array_slice($allowed, 0, 12) as $name) {
            $value = $request->query($name);

            if (is_scalar($value) && strlen((string) $value) <= 80 && (string) $value !== '') {
                $vary[$name] = (string) $value;
            }
        }

        return $vary;
    }

    private static function remember(string $key, int $ttlSeconds, Closure $build): mixed
    {
        if ($ttlSeconds <= 0) {
            return $build();
        }

        // Cache::remember can not tell "cached null" from "missing", so wrap the value.
        $hit = Cache::get($key);

        if (is_array($hit) && array_key_exists('v', $hit)) {
            return $hit['v'];
        }

        $value = self::building($build);
        Cache::put($key, ['v' => $value], $ttlSeconds);

        return $value;
    }

    private static function suffix(array $vary): string
    {
        return $vary === [] ? '' : ':'.md5(json_encode($vary));
    }

    private static function version(string $key): int
    {
        return (int) Cache::get($key, 1);
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

    private static function bump(string $key): void
    {
        if (self::$building > 0) {
            return;
        }

        Cache::add($key, 1);
        Cache::increment($key);
    }
}
