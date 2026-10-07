<?php

namespace App\Console\Commands;

use App\Models\Farm;
use App\Models\User;
use App\Services\Analytics\FarmerDashboardService;
use App\Support\ActiveFarm;
use Illuminate\Console\Command;
use Throwable;

class WarmFarmerYields extends Command
{
    protected $signature = 'iclimate:warm-farmer-yields';

    protected $description = "Run each active farmer's rice-yield estimate so the Farmer dashboard only reads a stored result.";

    /** This many service failures in a row mean the model service is down: the rest wait for the next run. */
    private const STOP_AFTER_SERVICE_FAILURES = 3;

    public function handle(FarmerDashboardService $dashboard): int
    {
        $done = 0;
        $missing = 0;
        $failed = 0;
        $inARow = 0;
        $stopped = false;

        $prepare = function (User $farmer, ?Farm $farm) use ($dashboard, &$done, &$missing, &$failed, &$inARow, &$stopped): bool {
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

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
