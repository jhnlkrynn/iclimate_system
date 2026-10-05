<?php

namespace Tests\Concerns;

use App\Models\HeatmapArea;

/**
 * The install migration seeds every barangay with a placeholder that is not a rating (HeatmapArea::isSeedPlaceholder()).
 * This writes a barangay's row the way BarangayRiskMapService::persist() does after a real computation.
 */
trait ComputesRiskRatings
{
    protected function computedRiskRating(string $barangay = 'Malaruhatan', string $level = 'High', array $attributes = []): HeatmapArea
    {
        $area = HeatmapArea::query()->firstOrNew(['barangay' => $barangay]);
        $area->fill($attributes + [
            'latitude' => $area->latitude ?? 14.0119,
            'longitude' => $area->longitude ?? 120.6669,
            'risk_level' => $level,
            'risk_type' => 'Drought',
            'risk_score' => ['Low' => 0.2, 'Moderate' => 0.5, 'High' => 0.8, 'Severe' => 0.95][$level] ?? 0.5,
            'rainfall_status' => 'Low rainfall',
            'description' => 'Computed from live barangay weather and last recorded rice yield via AgriculturalRiskScorer.',
        ])->save();

        return $area->refresh();
    }
}
