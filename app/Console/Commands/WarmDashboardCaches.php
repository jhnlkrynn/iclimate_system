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
                $this->line('Heat map: refreshed in '.number_format(microtime(true) - $started, 3).'s');
            } else {
                $this->line('Heat map: current');
            }

            // MAO dashboard, IT overview and MAO Operations: rebuilt only when the data they read changed or they are
            // getting old; a payload that fails keeps its last verified copy (and the run reports it).
            $failed = false;
            foreach ($dashboards->warmSharedDashboards() as $name => $result) {
                $this->line("{$name}: {$result}");
                $failed = $failed || str_starts_with($result, 'failed');
            }

            if ($failed) {
                return self::FAILURE;
            }
        } catch (Throwable $exception) {
            $this->error('Dashboard warm-up failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
