<?php

namespace App\Support;

/**
 * Performance Phase 4. One dashboard build reads the same figure in several services (the MAO Operations build
 * counted published advisories four times). Inside BuildMemo::run() a read is made once and reused; outside it
 * nothing is remembered.
 *
 * The memo lasts for one build only and is emptied by any database write made during the build
 * (AppServiceProvider), so a figure is never older than the build that shows it.
 */
final class BuildMemo
{
    private static int $depth = 0;

    /** @var array<string, mixed> */
    private static array $values = [];

    /**
     * @template T
     *
     * @param  \Closure(): T  $build
     * @return T
     */
    public static function run(\Closure $build): mixed
    {
        self::$depth++;

        try {
            return $build();
        } finally {
            if (--self::$depth === 0) {
                self::$values = [];
            }
        }
    }

    public static function active(): bool
    {
        return self::$depth > 0;
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $read
     * @return T
     */
    public static function remember(string $key, \Closure $read): mixed
    {
        if (self::$depth === 0) {
            return $read();
        }

        if (! array_key_exists($key, self::$values)) {
            self::$values[$key] = $read();
        }

        return self::$values[$key];
    }

    public static function has(string $key): bool
    {
        return self::$depth > 0 && array_key_exists($key, self::$values);
    }

    public static function get(string $key): mixed
    {
        return self::$values[$key] ?? null;
    }

    public static function put(string $key, mixed $value): void
    {
        if (self::$depth > 0) {
            self::$values[$key] = $value;
        }
    }

    /** Something was written: every remembered read is made again. */
    public static function flush(): void
    {
        self::$values = [];
    }
}
