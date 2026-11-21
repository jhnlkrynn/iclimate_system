<?php

namespace App\Services\Prediction;

use App\Models\RiceProduction;
use App\Support\LianBarangays;
use App\Support\RiceSeason;
use App\Support\SampleData;

/**
 * Computes an optional local adjustment factor from MAO RiceProduction records
 * without modifying the trained Random Forest model.
 *
 * factor = avg(barangay, farm_type, season) / avg(Lian, season), clamped to [0.8, 1.2].
 * Falls back to 1.0 when fewer than 3 distinct seasons or 5 records exist.
 */
class YieldLocalAdjustment
{
    public const MIN_SEASONS = 3;

    public const MIN_RECORDS = 5;

    public const MIN_FACTOR = 0.8;

    public const MAX_FACTOR = 1.2;

    public const MIN_EFFECT = 0.02;

    /**
     * @return array{model_value: float, factor: float, adjusted_yield: float, record_count: int, seasons_used: int}
     */
    public function forRequest(?string $barangay, ?string $farmType, ?string $season, float $modelValue): array
    {
        $canonicalBarangay = $barangay ? (LianBarangays::canonicalName($barangay) ?: trim($barangay)) : null;
        $normalizedSeason = $this->normalizeSeason($season);
        $normalizedType = $this->normalizeFarmType($farmType);

        if ($canonicalBarangay === null || $normalizedSeason === null || $normalizedType === null) {
            return $this->unadjusted($modelValue, 0, 0);
        }

        $records = SampleData::without(RiceProduction::query(), 'rice_productions')
            ->whereNotNull('yield_per_hectare')
            ->where('yield_per_hectare', '>', 0)
            ->get(['barangay', 'irrigation_type', 'season', 'year', 'production_month', 'yield_per_hectare']);

        $seasonRecords = $records->filter(
            fn (RiceProduction $r): bool => $this->normalizeSeason($r->season) === $normalizedSeason
        );

        $lianAvg = $seasonRecords->avg(fn (RiceProduction $r): float => (float) $r->yield_per_hectare);

        $localRecords = $seasonRecords->filter(function (RiceProduction $r) use ($canonicalBarangay, $normalizedType): bool {
            $rowBarangay = LianBarangays::canonicalName((string) $r->barangay) ?: trim((string) $r->barangay);

            return strcasecmp($rowBarangay, $canonicalBarangay) === 0
                && $this->normalizeFarmType($r->irrigation_type) === $normalizedType;
        });

        $recordCount = $localRecords->count();
        $seasonsUsed = $localRecords
            ->map(fn (RiceProduction $r): ?int => $r->year ?: $r->production_month?->year)
            ->filter(fn (?int $y): bool => $y !== null && $y > 0)
            ->unique()
            ->count();

        if ($recordCount < self::MIN_RECORDS || $seasonsUsed < self::MIN_SEASONS || ! is_numeric($lianAvg) || (float) $lianAvg <= 0) {
            return $this->unadjusted($modelValue, $recordCount, $seasonsUsed);
        }

        $localAvg = (float) $localRecords->avg(fn (RiceProduction $r): float => (float) $r->yield_per_hectare);
        if ($localAvg <= 0) {
            return $this->unadjusted($modelValue, $recordCount, $seasonsUsed);
        }

        $rawFactor = $localAvg / (float) $lianAvg;
        $factor = round(max(self::MIN_FACTOR, min(self::MAX_FACTOR, $rawFactor)), 4);

        if (abs($factor - 1.0) < self::MIN_EFFECT) {
            return $this->unadjusted($modelValue, $recordCount, $seasonsUsed);
        }

        return [
            'model_value' => round($modelValue, 2),
            'factor' => $factor,
            'adjusted_yield' => round($modelValue * $factor, 2),
            'record_count' => $recordCount,
            'seasons_used' => $seasonsUsed,
        ];
    }

    /**
     * @return array{model_value: float, factor: float, adjusted_yield: float, record_count: int, seasons_used: int}
     */
    private function unadjusted(float $modelValue, int $recordCount, int $seasonsUsed): array
    {
        $rounded = round($modelValue, 2);

        return [
            'model_value' => $rounded,
            'factor' => 1.0,
            'adjusted_yield' => $rounded,
            'record_count' => $recordCount,
            'seasons_used' => $seasonsUsed,
        ];
    }

    private function normalizeSeason(?string $season): ?string
    {
        $value = strtolower(trim((string) $season));

        return match (true) {
            str_contains($value, 'dry'), str_contains($value, 'tag-init') => RiceSeason::DRY,
            str_contains($value, 'wet'), str_contains($value, 'rainy'), str_contains($value, 'tag-ulan') => RiceSeason::WET,
            default => null,
        };
    }

    private function normalizeFarmType(?string $farmType): ?string
    {
        $value = strtolower(trim((string) $farmType));

        return match (true) {
            str_contains($value, 'irrigated') => 'Irrigated',
            str_contains($value, 'rainfed') => 'Rainfed',
            default => null,
        };
    }
}
