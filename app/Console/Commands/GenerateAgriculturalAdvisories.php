<?php

namespace App\Console\Commands;

use App\Services\Analytics\AnalyticsDispatcher;
use Illuminate\Console\Command;

class GenerateAgriculturalAdvisories extends Command
{
    protected $signature = 'iclimate:generate-advisories {--fetch-weather : Fetch weather before generating} {--force-weather : Force the weather API call}';

    protected $description = 'Queue Python advisory analysis using stored weather data.';

    public function handle(AnalyticsDispatcher $analytics): int
    {
        $run = $analytics->dispatch('advisory_ai', [
            'fetch_weather' => (bool) $this->option('fetch-weather'),
            'force_weather' => (bool) $this->option('force-weather'),
        ]);
        $this->info("Queued Python advisory generation: {$run->uuid}");

        return self::SUCCESS;
    }
}
