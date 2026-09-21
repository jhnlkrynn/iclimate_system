<?php

namespace App\Console\Commands;

use App\Services\Risk\BarangayRiskMapService;
use Illuminate\Console\Command;

class RefreshBarangayAgriculturalRisk extends Command
{
    protected $signature = 'iclimate:refresh-barangay-risk';

    protected $description = 'Recompute and persist the 19-barangay Lian agricultural risk map from live weather, real recorded yield, and AgriculturalRiskScorer.';

    public function handle(BarangayRiskMapService $riskMapService): int
    {
        $map = $riskMapService->riskMap(refresh: true);

        $counts = collect($map['barangays'])->countBy('risk_level');

        $this->info('Refreshed barangay agricultural risk map for '.count($map['barangays']).' barangay(s).');
        foreach ($counts as $level => $count) {
            $this->line("  {$level}: {$count}");
        }

        return self::SUCCESS;
    }
}
