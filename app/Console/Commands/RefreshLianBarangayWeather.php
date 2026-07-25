<?php

namespace App\Console\Commands;

use App\Services\Weather\LianBarangayWeatherService;
use Illuminate\Console\Command;

class RefreshLianBarangayWeather extends Command
{
    protected $signature = 'iclimate:refresh-lian-weather';

    protected $description = 'Refresh cached Open-Meteo weather/rainfall forecasts for every Lian barangay.';

    public function handle(LianBarangayWeatherService $weather): int
    {
        $records = $weather->refresh();
        $fresh = collect($records)->where('stale', false)->count();
        $stale = count($records) - $fresh;

        $this->info("Refreshed {$fresh} Lian barangay weather records.");

        if ($stale > 0) {
            $this->warn("{$stale} barangay weather record(s) are unavailable/stale.");
        }

        return self::SUCCESS;
    }
}
