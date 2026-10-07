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
 * The name is a CacheScope consumer: its key carries the versions of the data
 * areas that page reads, so a change to that data shows up on the very next
 * load, while changes to unrelated data leave the entry warm. Private entries
 * also carry the person's own version (flushUser).
 *
 * Why it exists: each database round trip to Supabase costs hundreds of
 * milliseconds, so a page that repeats the same queries on every load is
 * slow no matter how well the queries are written.
 */
class PageCache
{
    /**
     * @template T
     *
     * @param  array<string, mixed>  $vary  anything the result depends on (filters, dates)
     * @param  Closure(): T  $build
     * @return T
     */
    public static function shared(string $name, int $ttlSeconds, Closure $build, array $vary = []): mixed
    {
        return self::remember(fn (string $token): string => 'page:shared:'.$name.':'.$token.self::suffix($vary), $name, $ttlSeconds, $build);
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
        // Also keyed by the shared data the page reads: a page for one person is built partly from shared data
        // (counts, recent records), so a change there must rebuild it too. The reverse never happens.
        // A farmer's page is also per farm (the farm picked in the My Farm selector), so switching farms never shows
        // another farm's barangay and switching back reads the stored page.
        $user = auth()->user();
        $farm = $user !== null && (int) $user->getKey() === $userId && $user->role === \App\Models\User::ROLE_FARMER
            ? \App\Support\ActiveFarm::current($user)?->getKey() : null;
        $prefix = 'page:user:'.$userId.':v'.self::userVersion($userId).($farm ? ':farm'.$farm : '').':'.$name.':';

        return self::remember(fn (string $token): string => $prefix.$token.self::suffix($vary), $name, $ttlSeconds, $build);
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
        $snapshot = CacheScope::snapshot($name);
        $value = $build();

        if ($ttlSeconds > 0) {
            Cache::put('page:shared:'.$name.':'.CacheScope::settledToken($snapshot).self::suffix($vary), ['v' => $value], $ttlSeconds);
        }

        return $value;
    }

    /** Everything cached with shared() is rebuilt on its next read (a deliberate full reset). */
    public static function flushShared(): void
    {
        CacheScope::bumpAll();
    }

    /** Everything cached with forUser() for this person is rebuilt on its next read. */
    public static function flushUser(int $userId): void
    {
        $key = 'page:user:'.$userId.':version';
        Cache::add($key, 1);
        Cache::increment($key);
    }

    /** The cache-key part for one person's private data (their own rows: notifications, profile, plans). */
    public static function userVersion(int $userId): int
    {
        return (int) Cache::get('page:user:'.$userId.':version', 1);
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

    /** @param Closure(string): string $keyFor  the key for a CacheScope token */
    private static function remember(Closure $keyFor, string $consumer, int $ttlSeconds, Closure $build): mixed
    {
        if ($ttlSeconds <= 0) {
            return $build();
        }

        $snapshot = CacheScope::snapshot($consumer);

        // Cache::remember can not tell "cached null" from "missing", so wrap the value.
        $hit = Cache::get($keyFor(CacheScope::tokenAt($snapshot)));

        if (is_array($hit) && array_key_exists('v', $hit)) {
            return $hit['v'];
        }

        $value = $build();
        // Stored under the token that includes the build's own writes (a weather snapshot), so the next request finds it.
        Cache::put($keyFor(CacheScope::settledToken($snapshot)), ['v' => $value], $ttlSeconds);

        return $value;
    }

    private static function suffix(array $vary): string
    {
        return $vary === [] ? '' : ':'.md5(json_encode($vary));
    }
}
