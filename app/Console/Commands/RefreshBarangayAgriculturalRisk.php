<?php

namespace App\Console\Commands;

use App\Services\Risk\BarangayRiskMapService;
use Illuminate\Console\Command;

class RefreshBarangayAgriculturalRisk extends Command
{
    protected $signature = 'iclimate:refresh-barangay-risk';

    protected $description = 'Recompute and persist the 19-barangay Lian agricultural heat map from live weather, real recorded yield, and AgriculturalRiskScorer.';

    public function handle(BarangayRiskMapService $riskMapService): int
    {
        // Re-rates from the shared barangay weather. It asks the provider only when that weather is due, so this
        // reconciliation never repeats the request iclimate:refresh-barangay-weather made a few minutes earlier.
        $map = $riskMapService->refreshForCurrentWeather(force: true);
        if ($map === null) {
            $this->warn('Another process is rating the barangays, or rating failed; the last verified ratings are kept.');

            return self::SUCCESS;
        }

        $counts = collect($map['barangays'])->countBy('risk_level');

        $this->info('Refreshed barangay agricultural heat map for '.count($map['barangays']).' barangay(s).');
        foreach ($counts as $level => $count) {
            $this->line("  {$level}: {$count}");
        }

        return self::SUCCESS;
    }
}
