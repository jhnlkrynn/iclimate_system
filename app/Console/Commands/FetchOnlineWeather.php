<?php

namespace App\Console\Commands;

use App\Services\Weather\OpenMeteoService;
use Illuminate\Console\Command;

class FetchOnlineWeather extends Command
{
    protected $signature = 'iclimate:fetch-weather {--force : Ignore the short cache and call Open-Meteo now}';
    protected $description = 'Fetch and store the latest Open-Meteo seven-day forecast for iClimate.';

    public function handle(OpenMeteoService $weather): int
    {
        $result = $weather->fetchForecast((bool) $this->option('force'));

        if ($result['ok']) {
            $this->info('Weather Data Updated');
            $this->line('Records saved: '.$result['records_saved']);
            $this->line('Fetched at: '.$result['fetched_at']);

            return self::SUCCESS;
        }

        $this->warn('Unable to Update Weather Data');
        $this->line($result['message']);
        $this->line('Fallback records: '.$result['records']->count());

        return $result['records']->isNotEmpty() ? self::SUCCESS : self::FAILURE;
    }
}
