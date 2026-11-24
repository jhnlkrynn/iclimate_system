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
        $eligibleStatuses = [User::STATUS_ACTIVE, User::STATUS_APPROVED];
        $this->reportFarmerFilter($eligibleStatuses);

        $counters = ['done' => 0, 'missing' => 0, 'failed' => 0, 'inARow' => 0, 'stopped' => false];

        if (ActiveFarm::available()) {
            // Every MAO-approved farm in use of every active/approved farmer: pending/rejected farms are skipped as unverified.
            $farms = Farm::query()->active()->verified()
                ->whereHas('user', fn ($q) => $q->where('role', User::ROLE_FARMER)->whereIn('status', $eligibleStatuses))
                ->with('user.farmerProfile')
                ->orderBy('id')
                ->cursor();
            foreach ($farms as $farm) {
                if (! $this->prepareFarmerEstimate($dashboard, $store, $seasonLabel, $farm->user, $farm, $counters)) {
                    break;
                }
            }
        } else {
            $farmers = User::query()
                ->where('role', User::ROLE_FARMER)
                ->whereIn('status', $eligibleStatuses)
                ->with('farmerProfile')
                ->orderBy('id')
                ->cursor();
            foreach ($farmers as $farmer) {
                if (! $this->prepareFarmerEstimate($dashboard, $store, $seasonLabel, $farmer, null, $counters)) {
                    break;
                }
            }
        }

        $this->line("Yield estimates prepared: {$counters['done']}, skipped: incomplete profile: {$counters['missing']}".($counters['failed'] ? ", failed: {$counters['failed']}" : ''));
        if ($counters['stopped']) {
            $this->warn('The estimate service is not answering; the remaining farmers keep their last estimate until the next run.');
        }

        // The Heat Map's Expected yield layer: always run the 19 barangay-level estimates even when 0 farmer estimates succeed.
        $barangayYields = collect($barangays->prepare(runModel: true));
        // This Week / 2 Weeks on the Heat Map: the same runs with that window of the saved barangay forecast.
        foreach (array_keys(BarangayYieldForecastService::PERIOD_DAYS) as $period) {
            $result = $barangays->preparePeriod($period);
            $estimates = collect($result['estimates']);
            $this->line(sprintf('%s yield estimates: %d farms, %d of %d barangays (%d model calls)', $period === 'week' ? 'This week' : 'Next 2 weeks',
                $result['farm_runs'], $estimates->whereNotNull('predicted_yield')->count(), $estimates->count(), $result['model_calls']));
        }
        app(\App\Services\Risk\HeatMapLayerService::class)->forgetCache();   // the map's 10-minute answer shows the new estimates
        // Open Heat Maps, dashboards and forecast pages re-read once, only when an estimate really changed.
        $changed = YieldPredictionStore::takeChangedCount();
        if ($changed > 0) {
            \App\Support\CacheScope::signal('yield');
            $this->line("{$changed} saved estimate(s) changed; open pages were told.");
        }
        $this->line('Barangay yield estimates: '.$barangayYields->whereNotNull('predicted_yield')->count().' of '.$barangayYields->count()
            .' ('.$barangayYields->where('basis', 'farm_average')->count().' from farm averages, '.$barangayYields->where('basis', 'barangay_model')->count().' from their own model run)');
        $unmatched = $barangays->unmatchedFarms();
        if ($unmatched->isNotEmpty()) {
            $this->warn($unmatched->count().' farm(s) have a barangay that is not one of the 19 and are left out of the barangay averages:');
            $unmatched->each(fn (Farm $farm) => $this->line(sprintf('  farm %d (farmer %d, "%s"): barangay %s', $farm->id, $farm->user_id, $farm->farm_name, json_encode($farm->barangay, JSON_UNESCAPED_UNICODE))));
        }

        return $counters['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @param array<int, string> $eligibleStatuses */
    private function reportFarmerFilter(array $eligibleStatuses): void
    {
        $statusCounts = User::query()
            ->where('role', User::ROLE_FARMER)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $eligibleFarmers = User::query()->where('role', User::ROLE_FARMER)->whereIn('status', $eligibleStatuses);
        $eligibleWithFarm = ActiveFarm::available()
            ? (clone $eligibleFarmers)->whereHas('farms', fn ($q) => $q->active())->count()
            : (clone $eligibleFarmers)->count();
        $eligibleWithoutFarm = ActiveFarm::available()
            ? (clone $eligibleFarmers)->whereDoesntHave('farms', fn ($q) => $q->active())->count()
            : 0;
        $totalActiveFarms = ActiveFarm::available()
            ? Farm::query()->active()->whereHas('user', fn ($q) => $q->where('role', User::ROLE_FARMER)->whereIn('status', $eligibleStatuses))->count()
            : $eligibleWithFarm;

        $this->line(sprintf(
            'Farmer filter: %d total (Active: %d, Approved: %d, Pending: %d, Inactive: %d, Rejected: %d); %d eligible with active farm (%d farms), %d without active farm.',
            (int) $statusCounts->sum(),
            (int) ($statusCounts[User::STATUS_ACTIVE] ?? 0),
            (int) ($statusCounts[User::STATUS_APPROVED] ?? 0),
            (int) ($statusCounts[User::STATUS_PENDING] ?? 0),
            (int) ($statusCounts[User::STATUS_INACTIVE] ?? 0),
            (int) ($statusCounts[User::STATUS_REJECTED] ?? 0),
            $eligibleWithFarm,
            $totalActiveFarms,
            $eligibleWithoutFarm,
        ));
    }

    /** @param array{done: int, missing: int, failed: int, inARow: int, stopped: bool} $counters */
    private function prepareFarmerEstimate(
        FarmerDashboardService $dashboard,
        YieldPredictionStore $store,
        ?string $seasonLabel,
        User $farmer,
        ?Farm $farm,
        array &$counters,
    ): bool {
        $rawBarangay = $farm !== null ? $farm->barangay : $farmer->registeredBarangay();
        $validBarangay = \App\Support\LianBarangays::canonicalName($rawBarangay) !== null;
        $validArea = $farm !== null
            ? $farm->modelArea() !== null
            : (is_numeric($farmer->farmerProfile?->farm_area) && (float) $farmer->farmerProfile->farm_area > 0);

        // Invalid/missing barangay or farm size gets no estimate (nothing is made up), and never counts as a service failure.
        if (! $validBarangay || ! $validArea) {
            $counters['missing']++;
            $counters['inARow'] = 0;
            $reason = ! $validBarangay
                ? 'invalid/missing barangay '.json_encode($rawBarangay, JSON_UNESCAPED_UNICODE)
                : 'missing/invalid farm area';
            if ($this->output->isVerbose()) {
                $this->line("Skipped incomplete profile (farmer {$farmer->id}".($farm ? ", farm {$farm->id}" : '')."): {$reason}");
            }

            return true;
        }

        try {
            $result = $dashboard->prepareYieldEstimate($farmer, $farm);
        } catch (Throwable $exception) {
            $cause = $exception::class.': '.$exception->getMessage();
            $result = ['failed' => true, 'lacks_records' => false, 'error' => $cause];
        }

        if (! ($result['failed'] ?? false)) {
            if ($farm !== null) {
                rescue(fn () => $store->saveFarm($farm, $result, $seasonLabel), report: true);
            }
            $counters['done']++;
            $counters['inARow'] = 0;
        } elseif ($result['lacks_records'] ?? false) {
            $counters['missing']++;                                  // farm size or records missing: nothing the service can fix
            $counters['inARow'] = 0;
            if ($this->output->isVerbose()) {
                $this->line("Skipped incomplete profile (farmer {$farmer->id}".($farm ? ", farm {$farm->id}" : '').'): '.($result['error'] ?? 'missing model inputs'));
            }
        } else {
            $cause = (string) ($result['error'] ?? 'Unknown service failure');
            \Illuminate\Support\Facades\Log::warning('WarmFarmerYields: farmer yield estimate failed.', [
                'user_id' => $farmer->id,
                'farm_id' => $farm?->id,
                'cause' => $cause,
            ]);
            if ($this->output->isVerbose()) {
                $this->warn("Farmer {$farmer->id}".($farm ? " farm {$farm->id}" : '').": {$cause}");
            }
            $counters['failed']++;
            // Every farmer keeps their last verified estimate; the service is not asked 800 more times.
            if (++$counters['inARow'] >= self::STOP_AFTER_SERVICE_FAILURES) {
                $counters['stopped'] = true;

                return false;
            }
        }

        return true;
    }
}
