<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Versioned cache for the chatbot's vocabulary and moderation dictionary.
 * Each group ("vocab", "moderation") has a version number in the key;
 * flush() bumps it, so every worker sees fresh data on its next read without
 * having to enumerate or delete cache keys.
 */
class AiCache
{
    private const TTL_SECONDS = 86400;

    public static function remember(string $group, Closure $build): mixed
    {
        return Cache::remember(self::key($group), self::TTL_SECONDS, $build);
    }

    public static function flush(string $group): void
    {
        $versionKey = "ai:{$group}:version";

        Cache::add($versionKey, 1);
        Cache::increment($versionKey);
    }

    private static function key(string $group): string
    {
        return "ai:{$group}:v".(int) Cache::get("ai:{$group}:version", 1);
    }
}
