<?php

namespace App\Services\Farmer;

use App\Models\Farm;
use App\Models\FarmerPlantingSchedule;
use App\Models\HistoricalRiceProduction;
use App\Support\RiceSeason;

/** Reads the imported seasonal benchmark without treating missing source cells as zero. */
final class HistoricalYieldBenchmark
{
    public function forFarmSeason(?Farm $farm, string $season, int $year): ?HistoricalRiceProduction
    {
        if ($farm?->barangay === null) {
            return null;
        }

        return HistoricalRiceProduction::query()
            ->whereRaw('upper(barangay) = ?', [mb_strtoupper(trim($farm->barangay))])
            ->where('season', $this->code($season))
            ->where('season_year', $year)
            ->first();
    }

    public function forPlan(?FarmerPlantingSchedule $plan, ?Farm $farm = null): ?HistoricalRiceProduction
    {
        if ($plan?->planting_date === null) {
            return null;
        }
        $window = RiceSeason::window($plan->planting_date);
        $farm ??= $plan->farm;

        return $this->forFarmSeason($farm, $window['season'], $window['start']->year);
    }

    public function code(string $season): string
    {
        return in_array(mb_strtolower($season), ['wet', 'ws'], true) ? 'WS' : 'DS';
    }
}
