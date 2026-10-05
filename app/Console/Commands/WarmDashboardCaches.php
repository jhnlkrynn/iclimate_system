<?php

namespace App\Console\Commands;

use App\Http\Controllers\DashboardController;
use App\Services\Risk\BarangayRiskMapService;
use Illuminate\Console\Command;
use Throwable;

class WarmDashboardCaches extends Command
{
    protected $signature = 'iclimate:warm-dashboards';

    protected $description = 'Rebuild the shared MAO and IT dashboard caches so signed-in users get instant page loads.';

    public function handle(DashboardController $dashboards, BarangayRiskMapService $riskMap): int
    {
        try {
            // The risk map's own cache lapses every 20 minutes; whoever opened a
            // dashboard next used to wait ~20 s for the recompute. Refresh it here
            // (in place) once it is 10 minutes old instead.
            $age = $riskMap->cachedAgeMinutes();
            if ($age === null || $age >= 10) {
                $started = microtime(true);
                $riskMap->refreshCache();
                $this->line('Refreshed heat map in '.round(microtime(true) - $started, 1).'s');
            }

            foreach ($dashboards->warmSharedDashboards() as $name => $seconds) {
                $this->line("Warmed {$name} dashboard in {$seconds}s");
            }
        } catch (Throwable $exception) {
            $this->error('Dashboard warm-up failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
