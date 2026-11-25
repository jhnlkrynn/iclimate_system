<?php

namespace App\Console\Commands;

use App\Services\Risk\DroughtRiskService;
use App\Services\Risk\HeatMapLayerService;
use Illuminate\Console\Command;

class RefreshDroughtRisk extends Command
{
    protected $signature = 'iclimate:refresh-drought-risk';

    protected $description = 'Persist drought-layer ratings from the iClimate ML weather model, stored Open-Meteo forecasts, or last saved ratings.';

    public function handle(DroughtRiskService $drought, HeatMapLayerService $cache): int
    {
        $results = $drought->refreshAll();
        $cache->forgetCache();

        $this->info('Refreshed drought risk for '.count($results).' barangay(s).');

        return self::SUCCESS;
    }
}
