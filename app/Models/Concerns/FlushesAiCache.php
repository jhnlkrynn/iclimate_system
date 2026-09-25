<?php

namespace App\Models\Concerns;

use App\Support\AiCache;

/**
 * Any change to a vocabulary or moderation row invalidates the cached
 * dictionary, so edits by IT/MAO take effect on the next chat message.
 * The model names its group with aiCacheGroup().
 */
trait FlushesAiCache
{
    public static function bootFlushesAiCache(): void
    {
        $flush = fn (self $model) => AiCache::flush($model->aiCacheGroup());

        static::saved($flush);
        static::deleted($flush);
    }

    abstract public function aiCacheGroup(): string;
}
