<?php

namespace App\Console\Commands;

use App\Jobs\RefreshHeatmapForecasts as RefreshHeatmapForecastsJob;
use Illuminate\Console\Command;

class RefreshHeatmapForecasts extends Command
{
    protected $signature = 'iclimate:refresh-heatmap-forecasts {--months=6 : How many months ahead to forecast}';

    protected $description = 'Queue a job that precomputes barangay-level heat map risk forecasts for the next N months.';

    public function handle(): int
    {
        $months = max(1, (int) $this->option('months'));

        RefreshHeatmapForecastsJob::dispatch($months)
            ->onConnection((string) config('services.farming_ai.queue_connection', 'database'))
            ->onQueue((string) config('services.farming_ai.queue', 'analytics'));

        $this->info("Queued heat map forecast refresh for the next {$months} month(s).");

        return self::SUCCESS;
    }
}
