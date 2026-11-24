<?php

namespace App\Services\Prediction;

use App\Models\FarmerPlantingSchedule;
use App\Models\FarmTypeYieldOverride;
use App\Models\RiceProduction;
use App\Support\RiceSeason;
use App\Support\SampleData;
use App\Support\SchemaCache;
use Illuminate\Support\Collection;

/**
 * Computes an optional farm-type yield adjustment using only iClimate's own records
 * (recorded harvests on planting schedules + verified rice_productions rows with a farm type).
 *
 * factor = avg(actual_yield of farm_type in season) / avg(all harvested farms in season),
 * clamped to [0.7, 1.3], applied only when each farm type has >= 10 harvests across >= 2 seasons.
 * If insufficient data exists, an optional MAO percentage override may apply; computed records
 * automatically take priority once the threshold is reached.
 */
class YieldFarmTypeAdjustment
{
    public const MIN_HARVESTS_PER_TYPE = 10;

    public const MIN_SEASONS_PER_TYPE = 2;

    public const MIN_FACTOR = 0.7;

    public const MAX_FACTOR = 1.3;

    /**
     * @return array{
     *   model_value: float,
     *   factor: float,
     *   adjusted_yield: float,
     *   basis: ?string,
     *   source: string,
     *   counts: array{Rainfed: int, Irrigated: int},
     *   seasons: array{Rainfed: int, Irrigated: int},
     *   total_harvests: int,
     *   mao_note: ?string
     * }
     */
    public function forRequest(?string $farmType, ?string $season, float $adjustedYield): array
    {
        $normalizedType = $this->normalizeFarmType($farmType);
        $normalizedSeason = $this->normalizeSeason($season);
        $base = round($adjustedYield, 2);

        $records = $this->harvestRecords();
        $counts = [
            'Rainfed' => $records->where('farm_type', 'Rainfed')->count(),
            'Irrigated' => $records->where('farm_type', 'Irrigated')->count(),
        ];
        $seasons = [
            'Rainfed' => $records->where('farm_type', 'Rainfed')->pluck('season_key')->unique()->count(),
            'Irrigated' => $records->where('farm_type', 'Irrigated')->pluck('season_key')->unique()->count(),
        ];
        $totalHarvests = $counts['Rainfed'] + $counts['Irrigated'];

        $meetsThreshold = $counts['Rainfed'] >= self::MIN_HARVESTS_PER_TYPE
            && $counts['Irrigated'] >= self::MIN_HARVESTS_PER_TYPE
            && $seasons['Rainfed'] >= self::MIN_SEASONS_PER_TYPE
            && $seasons['Irrigated'] >= self::MIN_SEASONS_PER_TYPE;

        if ($meetsThreshold && $normalizedType !== null && $normalizedSeason !== null) {
            $seasonRows = $records->where('season', $normalizedSeason);
            $overallAvg = $seasonRows->avg('yield');
            $typeAvg = $seasonRows->where('farm_type', $normalizedType)->avg('yield');

            if (is_numeric($overallAvg) && (float) $overallAvg > 0 && is_numeric($typeAvg) && (float) $typeAvg > 0) {
                $rawFactor = (float) $typeAvg / (float) $overallAvg;
                $factor = round(max(self::MIN_FACTOR, min(self::MAX_FACTOR, $rawFactor)), 4);

                return [
                    'model_value' => $base,
                    'factor' => $factor,
                    'adjusted_yield' => round($base * $factor, 2),
                    'basis' => __('farmer.yield_farm_type.basis_records', ['count' => $totalHarvests]),
                    'source' => 'records',
                    'counts' => $counts,
                    'seasons' => $seasons,
                    'total_harvests' => $totalHarvests,
                    'mao_note' => null,
                ];
            }
        }

        $override = $this->currentOverride();
        if ($override !== null && $normalizedType !== null) {
            $factor = $normalizedType === 'Rainfed'
                ? round(max(self::MIN_FACTOR, min(self::MAX_FACTOR, 1.0 + ((float) $override->rainfed_diff_percent / 100.0))), 4)
                : 1.0;

            return [
                'model_value' => $base,
                'factor' => $factor,
                'adjusted_yield' => round($base * $factor, 2),
                'basis' => __('farmer.yield_farm_type.basis_mao'),
                'source' => 'mao',
                'counts' => $counts,
                'seasons' => $seasons,
                'total_harvests' => $totalHarvests,
                'mao_note' => (string) $override->note,
            ];
        }

        return [
            'model_value' => $base,
            'factor' => 1.0,
            'adjusted_yield' => $base,
            'basis' => null,
            'source' => 'none',
            'counts' => $counts,
            'seasons' => $seasons,
            'total_harvests' => $totalHarvests,
            'mao_note' => null,
        ];
    }

    /**
     * Summary for the yield card and heat map: both farm types' adjusted values, active source/basis, and counts.
     *
     * @return array{
     *   active: bool,
     *   source: string,
     *   basis: ?string,
     *   rainfed_t_ha: float,
     *   irrigated_t_ha: float,
     *   rainfed_count: int,
     *   irrigated_count: int,
     *   rainfed_seasons: int,
     *   irrigated_seasons: int,
     *   total_harvests: int,
     *   min_harvests: int
     * }
     */
    public function summaryForSeason(?string $season, float $baseYield): array
    {
        $rainfed = $this->forRequest('Rainfed', $season, $baseYield);
        $irrigated = $this->forRequest('Irrigated', $season, $baseYield);
        $source = $rainfed['source'] !== 'none' ? $rainfed['source'] : $irrigated['source'];
        $basis = $rainfed['basis'] ?? $irrigated['basis'];

        return [
            'active' => $source !== 'none',
            'source' => $source,
            'basis' => $basis,
            'rainfed_t_ha' => $rainfed['adjusted_yield'],
            'irrigated_t_ha' => $irrigated['adjusted_yield'],
            'rainfed_count' => $rainfed['counts']['Rainfed'],
            'irrigated_count' => $rainfed['counts']['Irrigated'],
            'rainfed_seasons' => $rainfed['seasons']['Rainfed'],
            'irrigated_seasons' => $rainfed['seasons']['Irrigated'],
            'total_harvests' => $rainfed['total_harvests'],
            'min_harvests' => self::MIN_HARVESTS_PER_TYPE,
        ];
    }

    public function currentOverride(): ?FarmTypeYieldOverride
    {
        if (! SchemaCache::hasTable('farm_type_yield_overrides')) {
            return null;
        }

        return FarmTypeYieldOverride::query()->latest('id')->first();
    }

    /** @return Collection<int, array{farm_type: string, season: string, season_key: string, yield: float}> */
    private function harvestRecords(): Collection
    {
        $rows = collect();

        if (SchemaCache::hasTable('farmer_planting_schedules') && SchemaCache::hasColumn('farmer_planting_schedules', 'actual_yield')) {
            $schedules = FarmerPlantingSchedule::query()
                ->whereNotNull('actual_yield')
                ->where('actual_yield', '>', 0)
                ->when(\App\Support\ActiveFarm::available() && SchemaCache::hasColumn('farms', 'verification_status'), function ($q): void {
                    $q->where(fn ($inner) => $inner->whereNull('farm_id')->orWhereHas('farm', fn ($fq) => $fq->verified()));
                })
                ->get(['farm_type', 'season', 'harvested_on', 'planting_date', 'actual_yield']);

            foreach ($schedules as $s) {
                $type = $this->normalizeFarmType($s->farm_type);
                $date = $s->harvested_on ?? $s->planting_date;
                $season = $this->normalizeSeason($s->season) ?? ($date ? RiceSeason::forMonth((int) $date->month) : null);
                $year = $date?->year;

                if ($type !== null && $season !== null) {
                    $rows->push([
                        'farm_type' => $type,
                        'season' => $season,
                        'season_key' => ($year ?: '0').'-'.$season,
                        'yield' => (float) $s->actual_yield,
                    ]);
                }
            }
        }

        if (SchemaCache::hasTable('rice_productions') && SchemaCache::hasColumn('rice_productions', 'verified_at')) {
            $productions = SampleData::without(RiceProduction::query(), 'rice_productions')
                ->whereNotNull('verified_at')
                ->whereNotNull('yield_per_hectare')
                ->where('yield_per_hectare', '>', 0)
                ->get(['irrigation_type', 'season', 'year', 'production_month', 'yield_per_hectare']);

            foreach ($productions as $p) {
                $type = $this->normalizeFarmType($p->irrigation_type);
                $season = $this->normalizeSeason($p->season) ?? ($p->production_month ? RiceSeason::forMonth((int) $p->production_month->month) : null);
                $year = $p->year ?: $p->production_month?->year;

                if ($type !== null && $season !== null) {
                    $rows->push([
                        'farm_type' => $type,
                        'season' => $season,
                        'season_key' => ($year ?: '0').'-'.$season,
                        'yield' => (float) $p->yield_per_hectare,
                    ]);
                }
            }
        }

        return $rows;
    }

    private function normalizeFarmType(?string $value): ?string
    {
        $clean = strtolower(trim((string) $value));

        return match ($clean) {
            'irrigated' => 'Irrigated',
            'rainfed' => 'Rainfed',
            default => null,
        };
    }

    private function normalizeSeason(?string $value): ?string
    {
        $clean = strtolower(trim((string) $value));

        return match (true) {
            str_contains($clean, 'dry') => 'Dry',
            str_contains($clean, 'wet') => 'Wet',
            default => null,
        };
    }
}
