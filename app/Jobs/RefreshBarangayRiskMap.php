<?php

namespace App\Jobs;

use App\Services\Risk\BarangayRiskMapService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Recomputes the 19-barangay risk map in the background (about 19 weather
 * lookups and 19 database writes). Queued by a page request that found the map
 * missing or very old, so the page itself never has to do this work.
 */
class RefreshBarangayRiskMap implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * One refresh waits in the queue at a time, however many pages asked for it: the unique lock is held until the job
     * has run (or failed), and for at most an hour when no worker picks it up.
     */
    public int $uniqueFor = 3600;

    public int $tries = 1;

    public int $timeout = 300;

    public function handle(BarangayRiskMapService $riskMap): void
    {
        try {
            $riskMap->riskMap(refresh: true);
        } finally {
            Cache::forget(BarangayRiskMapService::REFRESH_LOCK_KEY);
        }
    }
}
