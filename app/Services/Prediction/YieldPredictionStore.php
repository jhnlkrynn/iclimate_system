<?php

namespace App\Services\Prediction;

use App\Models\Farm;
use App\Models\YieldPrediction;
use App\Services\Risk\BarangayMapLayers;
use App\Support\LianBarangays;
use App\Support\RiceSeason;
use App\Support\SchemaCache;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The saved current-season rice-yield estimates (table yield_predictions), so the Heat Map and anything else that
 * reads them survive cache clears and deployments. iclimate:warm-farmer-yields writes them; pages only read.
 * Each is saved per period: season (the farmer dashboard's estimate) and, for the Heat Map's This Week / 2 Weeks,
 * week and 2weeks (built with that window of the saved barangay forecast).
 * Before the table exists (a database not migrated yet) nothing is written and every read is empty; before its period
 * column exists only season estimates are kept.
 */
final class YieldPredictionStore
{
    public const PERIODS = ['season', 'week', '2weeks'];

    public function available(string $period = 'season'): bool
    {
        if (! SchemaCache::hasTable('yield_predictions')) {
            return false;
        }

        return $period === 'season' || SchemaCache::hasColumn('yield_predictions', 'period');
    }

    /** First day of the season in progress (Wet: May 1, Dry: Nov 1), the key every saved estimate is filed under. */
    public function seasonStart(?CarbonImmutable $now = null): CarbonImmutable
    {
        return RiceSeason::window($now ?? CarbonImmutable::now('Asia/Manila'))['start'];
    }

    public static function classification(float $yield): string
    {
        return match (true) {
            $yield >= BarangayMapLayers::YIELD_GOOD => 'good',
            $yield >= BarangayMapLayers::YIELD_FAIR => 'fair',
            default => 'low',
        };
    }

    /**
     * One farm's estimate as the farmer dashboard stored it (FarmerDashboardService::runYieldModel's result).
     *
     * @param  array<string, mixed>  $estimate
     */
    public function saveFarm(Farm $farm, array $estimate, ?string $seasonLabel = null, string $period = 'season'): void
    {
        $yield = $estimate['yield_tons_per_hectare'] ?? $estimate['predicted_yield'] ?? null;
        if (! $this->available($period) || ! is_numeric($yield)) {
            return;
        }
        $input = is_array($estimate['model_input'] ?? null) ? $estimate['model_input'] : null;
        $area = $input['area'] ?? $farm->modelArea();

        $this->save('farm:'.$farm->getKey(), [
            'scope' => YieldPrediction::SCOPE_FARM,
            'farm_id' => $farm->getKey(),
            'user_id' => $farm->user_id,
            'barangay' => LianBarangays::canonicalName($farm->barangay),
            'predicted_yield' => round((float) $yield, 2),
            'basis' => 'farm_model',
            'farms_used' => 1,
            'area_hectares' => is_numeric($area) ? (float) $area : null,
            'season_label' => $seasonLabel,
            'target_month' => $estimate['target_month'] ?? null,
            'model_version' => $estimate['model_version'] ?? null,
            'model_input' => $input,
            'generated_at' => $estimate['ran_at'] ?? null,
        ], $period);
    }

    /** @param  array<string, mixed>  $values */
    public function saveBarangay(string $barangay, array $values, string $period = 'season'): void
    {
        if (! $this->available($period) || ! is_numeric($values['predicted_yield'] ?? null)) {
            return;
        }

        $this->save('barangay:'.$barangay, ['scope' => YieldPrediction::SCOPE_BARANGAY, 'barangay' => $barangay] + $values, $period);
    }

    /** @return Collection<int, YieldPrediction> this season's farm rows for the period */
    public function farms(string $period = 'season'): Collection
    {
        return $this->available($period) ? $this->query(YieldPrediction::SCOPE_FARM, $period)->get() : collect();
    }

    /** @return Collection<string, YieldPrediction> this season's barangay rows for the period, by canonical name */
    public function barangays(string $period = 'season'): Collection
    {
        return $this->available($period) ? $this->query(YieldPrediction::SCOPE_BARANGAY, $period)->get()->keyBy('barangay') : collect();
    }

    /** @return \Illuminate\Database\Eloquent\Builder<YieldPrediction> */
    private function query(string $scope, string $period): \Illuminate\Database\Eloquent\Builder
    {
        return YieldPrediction::query()->where('scope', $scope)->whereDate('season_start', $this->seasonStart())
            ->when(SchemaCache::hasColumn('yield_predictions', 'period'), fn ($q) => $q->where('period', $period));
    }

    /** @param  array<string, mixed>  $values */
    private function save(string $subject, array $values, string $period): void
    {
        $start = $this->seasonStart();
        $key = ['subject_key' => $subject, 'season_start' => $start->toDateString()];
        if (SchemaCache::hasColumn('yield_predictions', 'period')) {
            $key['period'] = $period;
        }
        YieldPrediction::query()->updateOrCreate(
            $key,
            $values + [
                'classification' => self::classification((float) $values['predicted_yield']),
                'season' => RiceSeason::window($start)['season'],
            ],
        );
    }
}
