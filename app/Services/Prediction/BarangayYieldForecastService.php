<?php

namespace App\Services\Prediction;

use App\Services\Weather\LianBarangayWeatherService;
use App\Support\ClimateHistory;
use App\Support\LianBarangays;
use App\Support\LianFarmTypes;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Runs the deployed rice-yield model once for each official Lian barangay.
 *
 * This is deliberately an input-building/orchestration service only. The
 * prediction itself always goes through RiceYieldPredictionService's shared
 * trained-model request path; there is no Heat Map-specific yield formula or
 * hardcoded result.
 */
final class BarangayYieldForecastService
{
    private const CACHE_PREFIX = 'heat-map:barangay-yield:v2:';

    private const FAILURE_TTL_MINUTES = 10;

    public function __construct(
        private readonly RiceYieldPredictionService $riceYield,
        private readonly LianBarangayWeatherService $weather,
    ) {}

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        $target = CarbonImmutable::now('Asia/Manila')->startOfMonth();
        $forecasts = rescue(fn (): array => $this->weather->savedForecasts(), [], false);
        $modelVersion = rescue(fn (): string => $this->riceYield->modelVersion(), 'unknown', false);
        $modelSource = rescue(fn (): string => $this->riceYield->modelSource(), 'configured model', false);
        $history = ClimateHistory::months()->values();
        $municipalityArea = rescue(fn (): ?float => $this->riceYield->municipalityArea(), null, false);

        $results = [];
        foreach (LianBarangays::all() as $barangay) {
            $results[$barangay] = $this->forBarangay(
                $barangay,
                $forecasts[$barangay] ?? [],
                $target,
                $history,
                $municipalityArea,
                $modelVersion,
                $modelSource,
            );
        }

        return $results;
    }

    /** @return array<string, mixed> */
    private function forBarangay(
        string $barangay,
        array $forecast,
        CarbonImmutable $target,
        \Illuminate\Support\Collection $history,
        ?float $municipalityArea,
        string $modelVersion,
        string $modelSource,
    ): array {
        $signature = sha1(json_encode([
            'barangay' => $barangay,
            'fetched_at' => $forecast['fetched_at'] ?? null,
            'target' => $target->format('Y-m'),
            'model' => $modelVersion,
            'area' => $municipalityArea,
            'history' => $history->map(fn (object $row): array => [
                $row->record_date ?? null,
                $row->rainfall ?? null,
                $row->temperature ?? null,
                $row->humidity ?? null,
                $row->wind_speed ?? null,
            ])->all(),
        ], JSON_THROW_ON_ERROR));
        $key = self::CACHE_PREFIX.$signature;
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        [$inputs, $missing, $inputSources] = $this->modelInputs($barangay, $forecast, $target, $history, $municipalityArea);
        $base = [
            'barangay' => $barangay,
            'predicted_yield' => null,
            'classification' => 'nodata',
            'period' => 'seasonal',
            'season' => $inputs['season'] ?? null,
            'model' => 'Random Forest',
            'model_source' => $modelSource,
            'model_version' => $modelVersion,
            'prediction_timestamp' => null,
            'data_freshness' => $this->freshness($forecast['fetched_at'] ?? null),
            'data_source' => $forecast['source'] ?? 'Saved Lian barangay forecast',
            'input_sources' => $inputSources,
            'model_input' => $inputs,
            'missing_inputs' => $missing,
            'error' => null,
        ];

        if ($missing !== []) {
            $base['error'] = 'Missing required model inputs: '.implode(', ', $missing).'.';
            Cache::put($key, $base, now()->addMinutes(self::FAILURE_TTL_MINUTES));

            return $base;
        }

        try {
            [$prediction, $error] = $this->riceYield->requestYieldFromModel($inputs);
            $value = is_array($prediction) && is_numeric($prediction['predicted_yield'] ?? null)
                ? round((float) $prediction['predicted_yield'], 2)
                : null;
            if ($value === null) {
                $base['error'] = $error ?? 'The trained rice-yield model returned no usable prediction.';
                Cache::put($key, $base, now()->addMinutes(self::FAILURE_TTL_MINUTES));

                return $base;
            }

            $result = $base + [
                'predicted_yield' => $value,
                'classification' => $this->classification($value),
                'prediction_timestamp' => CarbonImmutable::now('Asia/Manila')->toIso8601String(),
                'missing_inputs' => [],
                'error' => null,
            ];
            Cache::forever($key, $result);

            return $result;
        } catch (Throwable $exception) {
            $base['error'] = $exception->getMessage();
            Cache::put($key, $base, now()->addMinutes(self::FAILURE_TTL_MINUTES));

            return $base;
        }
    }

    /** @return array{0: array<string, mixed>, 1: list<string>, 2: array<string, array{value:mixed, source:string}>} */
    private function modelInputs(
        string $barangay,
        array $forecast,
        CarbonImmutable $target,
        \Illuminate\Support\Collection $history,
        ?float $municipalityArea,
    ): array {
        $days = collect((array) ($forecast['daily'] ?? []))
            ->filter(fn ($day): bool => is_array($day) && is_string($day['date'] ?? null))
            ->values();
        $number = fn (mixed $value): ?float => is_numeric($value) && is_finite((float) $value) ? (float) $value : null;
        $rain = $days->map(fn (array $day): ?float => $number($day['rainfall'] ?? $day['precipitation_mm'] ?? null))
            ->filter(fn (?float $value): bool => $value !== null)->values();
        $temperatures = $days->flatMap(fn (array $day): array => array_values(array_filter([
            $number($day['temperature_min'] ?? null),
            $number($day['temperature_max'] ?? null),
        ], fn (?float $value): bool => $value !== null)))->values();
        $tempAverage = $temperatures->isNotEmpty() ? round((float) $temperatures->avg(), 2) : null;
        $tempRange = $temperatures->isNotEmpty() ? round((float) $temperatures->max() - (float) $temperatures->min(), 2) : null;
        $recent = $history->sortByDesc('record_date')->values();
        $latest = $recent->first();
        $season = \App\Support\RiceSeason::forTrainedYieldModel($target->month);
        $seasonRows = $recent->filter(fn (object $row): bool => \App\Support\RiceSeason::forTrainedYieldModel((int) substr((string) ($row->record_date ?? ''), 5, 2)) === $season);
        $barangayArea = $this->barangayArea($barangay) ?? $municipalityArea;
        $has3 = $recent->count() >= 3;
        $has6 = $recent->count() >= 6;
        $inputs = [
            'rainfall' => $rain->isNotEmpty() ? round((float) $rain->sum(), 2) : null,
            'temp_avg' => $tempAverage,
            'temp_range' => $tempRange,
            'area' => $barangayArea,
            'previous_rainfall' => $number($latest?->rainfall ?? null),
            'previous_temp' => $number($latest?->temperature ?? null),
            'previous_humidity' => $number($latest?->humidity ?? null),
            'previous_wind_speed' => $number($latest?->wind_speed ?? null),
            'rainfall_6m' => $has6 ? round((float) $recent->take(6)->sum('rainfall'), 2) : null,
            'temp_3m' => $has3 ? round((float) $recent->take(3)->avg('temperature'), 2) : null,
            'temp_6m' => $has6 ? round((float) $recent->take(6)->avg('temperature'), 2) : null,
            'seasonal_rainfall' => $seasonRows->isNotEmpty() ? round((float) $seasonRows->sum('rainfall'), 2) : null,
            'seasonal_temp' => $seasonRows->isNotEmpty() ? round((float) $seasonRows->avg('temperature'), 2) : null,
            'season' => $season,
        ];
        $required = [
            'rainfall', 'temp_avg', 'temp_range', 'area', 'previous_rainfall', 'previous_temp',
            'rainfall_6m', 'temp_3m', 'temp_6m', 'seasonal_rainfall', 'seasonal_temp', 'season',
        ];
        $missing = array_values(array_filter($required, fn (string $key): bool => $inputs[$key] === null || $inputs[$key] === ''));
        $source = $forecast['source'] ?? 'Saved Lian barangay forecast';
        $inputSources = collect($inputs)->mapWithKeys(fn (mixed $value, string $key): array => [
            $key => ['value' => $value, 'source' => in_array($key, ['rainfall', 'temp_avg', 'temp_range'], true) ? $source : 'Verified iClimate climate/farm data'],
        ])->all();

        return [$inputs, $missing, $inputSources];
    }

    private function barangayArea(string $barangay): ?float
    {
        $summary = LianFarmTypes::summaryFor($barangay);
        if (is_array($summary) && ($count = (int) ($summary['rainfed_record_count'] ?? 0) + (int) ($summary['irrigated_record_count'] ?? 0)) > 0) {
            $area = (float) ($summary['rainfed_area_hectares'] ?? 0) + (float) ($summary['irrigated_area_hectares'] ?? 0);
            if ($area > 0) {
                return round($area / $count, 2);
            }
        }

        return null;
    }

    private function classification(float $yield): string
    {
        return match (true) {
            $yield >= 4.5 => 'good',
            $yield >= 3.5 => 'fair',
            default => 'low',
        };
    }

    private function freshness(?string $fetchedAt): string
    {
        if ($fetchedAt === null) {
            return 'unavailable';
        }

        try {
            $age = CarbonImmutable::parse($fetchedAt)->diffInHours(CarbonImmutable::now('Asia/Manila'));

            return $age <= 24 ? 'fresh' : 'stale';
        } catch (Throwable) {
            return 'unknown';
        }
    }
}
