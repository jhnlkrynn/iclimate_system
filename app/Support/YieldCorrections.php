<?php

namespace App\Support;

use App\Models\FarmerPlantingSchedule;
use Illuminate\Support\Collection;

/**
 * Predicted against actual yield, from the harvests farmers recorded (farmer_planting_schedules.predicted_yield and
 * actual_yield, both in tons per hectare). Read-only: shown on the MAO Dashboard (Survey Results) and on IT's Model
 * Evaluation as data for future retraining. Nothing here retrains a model.
 */
class YieldCorrections
{
    /**
     * Newest harvest first.
     *
     * @return Collection<int, array{date: ?string, barangay: ?string, season: ?string, farm_type: ?string, predicted: float, actual: float, difference: float}>
     */
    public static function rows(): Collection
    {
        if (! SchemaCache::hasColumn('farmer_planting_schedules', 'actual_yield') || ! SchemaCache::hasColumn('farmer_planting_schedules', 'predicted_yield')) {
            return collect();
        }

        return FarmerPlantingSchedule::query()
            ->where('actual_yield', '>', 0)->where('predicted_yield', '>', 0)
            ->orderByDesc('harvested_on')->orderByDesc('id')
            ->get(['id', 'barangay', 'season', 'farm_type', 'harvested_on', 'predicted_yield', 'actual_yield'])
            ->map(fn (FarmerPlantingSchedule $plan): array => [
                'date' => $plan->harvested_on?->format('M j, Y'),
                'barangay' => LianBarangays::canonicalName($plan->barangay) ?? $plan->barangay,
                'season' => $plan->season,
                'farm_type' => $plan->farm_type,
                'predicted' => (float) $plan->predicted_yield,
                'actual' => (float) $plan->actual_yield,
                'difference' => round((float) $plan->actual_yield - (float) $plan->predicted_yield, 2),
            ]);
    }

    /**
     * @return array{count: int, predicted: ?float, actual: ?float, error: ?float, rows: Collection}
     */
    public static function summary(): array
    {
        $rows = self::rows();

        return [
            'count' => $rows->count(),
            'predicted' => $rows->isEmpty() ? null : round($rows->avg('predicted'), 2),
            'actual' => $rows->isEmpty() ? null : round($rows->avg('actual'), 2),
            // Average size of the miss, whichever direction.
            'error' => $rows->isEmpty() ? null : round($rows->avg(fn (array $row): float => abs($row['difference'])), 2),
            'rows' => $rows,
        ];
    }
}
