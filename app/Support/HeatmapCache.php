<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class HeatmapCache
{
    public const TTL_MINUTES = 10;

    private const VERSION_KEY = 'heatmap:lian:version';

    /** @param array<string, string> $filters */
    public static function key(string $layer, array $filters = []): string
    {
        ksort($filters);
        $filterKey = $filters === [] ? 'all' : substr(hash('sha256', json_encode($filters)), 0, 16);

        return sprintf(
            'heatmap:lian:%s:%s:v%s',
            $layer,
            $filterKey,
            (string) Cache::get(self::VERSION_KEY, 1),
        );
    }

    public static function invalidate(): void
    {
        if (! Cache::has(self::VERSION_KEY)) {
            Cache::forever(self::VERSION_KEY, 2);

            return;
        }

        Cache::increment(self::VERSION_KEY);
    }
}
