<?php

namespace App\Jobs;

use App\Services\HeatmapRiskService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class RefreshHeatmapRisk implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 180;

    public int $tries = 2;

    public int $uniqueFor = 600;

    public function uniqueId(): string
    {
        return 'lian';
    }

    public function handle(HeatmapRiskService $risk): void
    {
        Cache::forget('heatmap:lian:risk-refresh-pending');
        Cache::put('heatmap:lian:risk-refreshing', true, now()->addMinutes(5));

        try {
            $risk->refresh();
            Cache::put('heatmap:last-refresh', now()->toDateTimeString(), now()->addDay());
        } finally {
            Cache::forget('heatmap:lian:risk-refreshing');
        }
    }
}
