<?php

namespace App\Console\Commands;

use App\Models\Farm;
use App\Models\User;
use App\Services\Analytics\FarmerDashboardService;
use App\Services\Prediction\BarangayYieldForecastService;
use App\Services\Prediction\SeasonYield;
use App\Services\Prediction\YieldPredictionStore;
use Carbon\CarbonImmutable;
use App\Support\ActiveFarm;
use Illuminate\Console\Command;
use Throwable;

class WarmFarmerYields extends Command
{
    protected $signature = 'iclimate:warm-farmer-yields';

    protected $description = "Run each active farmer's rice-yield estimate, then each Lian barangay's, for the season and for this week / the next 2 weeks, and save them (yield_predictions) so the Farmer dashboard and the Heat Map only read stored results.";

    /** This many service failures in a row mean the model service is down: the rest wait for the next run. */
    private const STOP_AFTER_SERVICE_FAILURES = 3;

    public function handle(FarmerDashboardService $dashboard, BarangayYieldForecastService $barangays, YieldPredictionStore $store, SeasonYield $seasonYield): int
    {
        if (! $store->available()) {
            $this->warn('The yield_predictions table does not exist yet (run the migration): estimates stay in the cache only.');
        }
        $seasonLabel = rescue(fn (): string => $seasonYield->seasonLabel(CarbonImmutable::now('Asia/Manila')), null, false);
        $done = 0;
        $missing = 0;
        $failed = 0;
        $inARow = 0;
        $stopped = false;

        $prepare = function (User $farmer, ?Farm $farm) use ($dashboard, $store, $seasonLabel, &$done, &$missing, &$failed, &$inARow, &$stopped): bool {
            // A farm with no size gets no estimate (nothing is made up), and the service is not asked.
            if ($farm !== null && $farm->modelArea() === null) {
                $missing++;

                return true;
            }
            try {
                $result = $dashboard->prepareYieldEstimate($farmer, $farm);
            } catch (Throwable $exception) {
                $result = ['failed' => true, 'lacks_records' => false];
                $this->warn("Farmer {$farmer->id}".($farm ? " farm {$farm->id}" : '').': '.$exception->getMessage());
            }

            if (! ($result['failed'] ?? false)) {
                if ($farm !== null) {
                    rescue(fn () => $store->saveFarm($farm, $result, $seasonLabel), report: true);
                }
                $done++;
                $inARow = 0;
            } elseif ($result['lacks_records'] ?? false) {
                $missing++;                                  // farm size or records missing: nothing the service can fix
                $inARow = 0;
            } else {
                $failed++;
                // Every farmer keeps their last verified estimate; the service is not asked 800 more times.
                if (++$inARow >= self::STOP_AFTER_SERVICE_FAILURES) {
                    $stopped = true;

                    return false;
                }
            }

            return true;
        };

        if (ActiveFarm::available()) {
            // Every farm in use of every active farmer: each farm has its own estimate.
            Farm::query()->active()
                ->whereHas('user', fn ($q) => $q->where('role', User::ROLE_FARMER)->where('status', User::STATUS_ACTIVE))
                ->with('user.farmerProfile')
                ->orderBy('id')
                ->each(fn (Farm $farm): bool => $prepare($farm->user, $farm));
        } else {
            User::query()
                ->where('role', User::ROLE_FARMER)
                ->where('status', User::STATUS_ACTIVE)
                ->with('farmerProfile')
                ->orderBy('id')
                ->each(fn (User $farmer): bool => $prepare($farmer, null));
        }

        $this->line("Yield estimates prepared: {$done}, missing farm data: {$missing}".($failed ? ", failed: {$failed}" : ''));
        if ($stopped) {
            $this->warn('The estimate service is not answering; the remaining farmers keep their last estimate until the next run.');
        }

        // The Heat Map's Expected yield layer: the farms' average where a barangay has farm estimates, else its own run.
        // A service that stopped answering is not asked again for the barangays without farms either.
        $barangayYields = collect($barangays->prepare(runModel: ! $stopped));
        // This Week / 2 Weeks on the Heat Map: the same runs with that window of the saved barangay forecast.
        if (! $stopped) {
            foreach (array_keys(BarangayYieldForecastService::PERIOD_DAYS) as $period) {
                $result = $barangays->preparePeriod($period);
                $estimates = collect($result['estimates']);
                $this->line(sprintf('%s yield estimates: %d farms, %d of %d barangays (%d model calls)', $period === 'week' ? 'This week' : 'Next 2 weeks',
                    $result['farm_runs'], $estimates->whereNotNull('predicted_yield')->count(), $estimates->count(), $result['model_calls']));
            }
        }
        app(\App\Services\Risk\HeatMapLayerService::class)->forgetCache();   // the map's 10-minute answer shows the new estimates
        $this->line('Barangay yield estimates: '.$barangayYields->whereNotNull('predicted_yield')->count().' of '.$barangayYields->count()
            .' ('.$barangayYields->where('basis', 'farm_average')->count().' from farm averages, '.$barangayYields->where('basis', 'barangay_model')->count().' from their own model run)');
        $unmatched = $barangays->unmatchedFarms();
        if ($unmatched->isNotEmpty()) {
            $this->warn($unmatched->count().' farm(s) have a barangay that is not one of the 19 and are left out of the barangay averages:');
            $unmatched->each(fn (Farm $farm) => $this->line(sprintf('  farm %d (farmer %d, "%s"): barangay %s', $farm->id, $farm->user_id, $farm->farm_name, json_encode($farm->barangay, JSON_UNESCAPED_UNICODE))));
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
