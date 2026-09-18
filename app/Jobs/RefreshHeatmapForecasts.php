<?php

namespace App\Jobs;

use App\Services\HeatmapRiskService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class RefreshHeatmapForecasts implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 2;

    public int $uniqueFor = 1800;

    public function __construct(private readonly int $months = 6) {}

    public function uniqueId(): string
    {
        return 'lian-forecasts';
    }

    public function handle(HeatmapRiskService $risk): void
    {
        $risk->refreshForecasts($this->months);
        Cache::put('heatmap:lian:forecast-last-refresh', now()->toDateTimeString(), now()->addDays(2));
    }
}
