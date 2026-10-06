<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Analytics\FarmerDashboardService;
use Illuminate\Console\Command;
use Throwable;

class WarmFarmerYields extends Command
{
    protected $signature = 'iclimate:warm-farmer-yields';

    protected $description = "Run each active farmer's rice-yield estimate so the Farmer dashboard only reads a stored result.";

    public function handle(FarmerDashboardService $dashboard): int
    {
        $done = 0;
        $failed = 0;

        User::query()
            ->where('role', User::ROLE_FARMER)
            ->where('status', User::STATUS_ACTIVE)
            ->with('farmerProfile')
            ->orderBy('id')
            ->each(function (User $farmer) use ($dashboard, &$done, &$failed): void {
                try {
                    $dashboard->prepareYieldEstimate($farmer);
                    $done++;
                } catch (Throwable $exception) {
                    $failed++;
                    $this->warn("Farmer {$farmer->id}: ".$exception->getMessage());
                }
            });

        $this->line("Yield estimates prepared: {$done}".($failed ? ", failed: {$failed}" : ''));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
