<?php

namespace App\Services\Maintenance;

use App\Services\SystemHealthService;
use App\Support\PageCache;

/** Internal diagnostics prepared by the scheduler and read by server operators. */
class SystemHealthCache
{
    public const REUSE_SECONDS = 120;

    public static function runChecks(SystemHealthService $health): array
    {
        return ['checks' => $health->checks(), 'checkedAt' => now()->timezone('Asia/Manila')];
    }

    public function warm(SystemHealthService $health): void
    {
        PageCache::refreshShared('system-health', self::REUSE_SECONDS, fn (): array => self::runChecks($health));
    }
}
