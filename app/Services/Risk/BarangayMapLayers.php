<?php

namespace App\Services\Risk;

use App\Services\Prediction\BarangayYieldForecastService;
use App\Services\Weather\LianBarangayWeatherService;
use App\Support\LianBarangays;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Manuscript – Naicel (2026-10-08), Phase 3: the Heat Map's extra layers next to This Week / 2 Weeks. Each layer has
 * the same shape as a HeatMapPanel range, so the page and resources/js/heat-map.js show it the same way:
 *  - flood / drought: the saved per-barangay forecast at each official Lian centroid, assessed separately for all 19;
 *  - yield: the shared trained rice-yield model run for each official Lian barangay (low colour = good yield).
 * The weather service owns provider requests, retry/fallback behavior, and the last-valid cache. This service only
 * reads that Lian-only snapshot and derives the two layer assessments; yield results are cached by their source/model signature.
 */
class BarangayMapLayers
{
    public const KEYS = ['flood', 'drought', 'yield'];

    private const FORECAST_DAYS = 14;

    public const YIELD_GOOD = 4.5;

    public const YIELD_FAIR = 3.5;

    public function __construct(
        private readonly LianBarangayWeatherService $weather,
        private readonly BarangayYieldForecastService $yieldForecast,
    ) {}

    /** @return array<string, array<string, mixed>> */
    /** @param  string  $yieldPeriod  week | 2weeks: which saved Expected yield estimates the yield layer shows */
    public function layers(string $locale, string $period = '2weeks', string $yieldPeriod = 'week'): array
    {
        $forecasts = rescue(fn (): array => $this->weather->savedForecasts(), [], false);
        $yields = rescue(fn (): array => $this->yieldForecast->all($yieldPeriod === '2weeks' ? '2weeks' : 'week'), [], false);

        $layers = [];
        foreach (['flood', 'drought'] as $key) {
            $assessments = [];
            foreach (LianBarangays::all() as $name) {
                $assessments[$name] = $this->assess($key, $forecasts[$name] ?? [], $period === '2weeks' ? 14 : 7);
            }
            $updated = collect($forecasts)->map(fn (array $record): ?CarbonImmutable => rescue(
                fn () => ! empty($record['fetched_at']) ? CarbonImmutable::parse($record['fetched_at']) : null,
                null,
                false,
            ))->filter()->min();

            $layers[$key] = $this->layer($key, $locale, fn (string $name): string => $assessments[$name]['level'],
                fn (string $name, string $level): array => $this->dynamicText($key, $level, $assessments[$name], $locale),
                $this->t('source', $locale), $updated, self::FORECAST_DAYS);
        }

        $yieldUpdated = collect($yields)->map(fn (array $prediction): ?CarbonImmutable => rescue(
            fn () => ! empty($prediction['prediction_timestamp']) ? CarbonImmutable::parse($prediction['prediction_timestamp']) : null,
            null,
            false,
        ))->filter()->max();
        $yieldLayer = $this->layer('yield', $locale, function (string $name) use ($yields): string {
            $y = $yields[$name]['predicted_yield'] ?? null;

            return match (true) {
                $y === null => 'nodata',
                $y >= self::YIELD_GOOD => 'low',
                $y >= self::YIELD_FAIR => 'moderate',
                default => 'high',
            };
        }, fn (string $name, string $level) => [
            'meaning' => isset($yields[$name]) && $yields[$name]['predicted_yield'] !== null
                ? $this->t('yield_meaning', $locale, ['value' => number_format((float) $yields[$name]['predicted_yield'], 2)])
                : $this->t('yield_none', $locale),
            'action' => $this->t('yield_action.'.$level, $locale),
        ], $this->t('yield_source', $locale), $yieldUpdated);
        $layers['yield'] = $yieldLayer;

        foreach (LianBarangays::all() as $name) {
            $prediction = $yields[$name] ?? [];
            $row = &$layers['yield']['barangays'][$name];
            if (($prediction['predicted_yield'] ?? null) !== null) {
                $row['numbers'] = [
                    $this->t('yield_value', $locale) => number_format((float) $prediction['predicted_yield'], 2).' t/ha',
                    $this->t('yield_classification', $locale) => $row['label'],
                    $this->t('yield_model_version', $locale) => (string) ($prediction['model_version'] ?? 'unknown'),
                    $this->t('yield_data_source', $locale) => (string) ($prediction['model_source'] ?? 'Configured iClimate model'),
                ];
            } elseif (! empty($prediction['missing_inputs'])) {
                $row['partial'] = $this->t('yield_missing', $locale, ['inputs' => implode(', ', $prediction['missing_inputs'])]);
            }
            unset($row);
        }

        return $layers;
    }

    /**
     * One layer in the HeatMapPanel range shape.
     *
     * @param  callable(string): string  $levelOf
     * @param  callable(string, string): array{meaning: string, action: string}  $text
     */
    private function layer(string $key, string $locale, callable $levelOf, callable $text, string $source, mixed $updatedAt, int $days = 0): array
    {
        $labels = $this->t($key.'_levels', $locale);
        $barangays = [];
        $counts = ['low' => 0, 'moderate' => 0, 'high' => 0, 'nodata' => 0];
        foreach (LianBarangays::all() as $name) {
            $level = $levelOf($name);
            $counts[$level]++;
            $barangays[$name] = [
                'barangay' => $name,
                'name' => HeatMapPanel::displayName($name),
                'level' => $level,
                'label' => $labels[$level] ?? $level,
                'also' => [],
                'partial' => null,
                'numbers' => [],
            ] + $text($name, $level);
        }

        return [
            'range' => $key,
            'layer' => true,
            'days' => $days,
            'state' => $counts['nodata'] === count($barangays) ? 'nodata' : 'ok',
            'updated_at' => $updatedAt,
            'delayed' => false,
            'title' => $this->t($key.'_title', $locale),
            'counts' => $counts,
            'count_lines' => collect(['high', 'moderate', 'low', 'nodata'])
                ->filter(fn (string $level) => $counts[$level] > 0 && isset($labels[$level]))
                ->map(fn (string $level) => ['level' => $level, 'text' => trans_choice('farmer.heat_map.layers.count', $counts[$level], ['count' => $counts[$level]], $locale).': '.$labels[$level]])
                ->values()->all(),
            'outlook' => [],
            'labels' => $labels,
            'source' => $source,
            'barangays' => $barangays,
        ];
    }

    /** @return array{level: string, metrics: array<string, mixed>} */
    private function assess(string $key, array $record, int $forecastDays = self::FORECAST_DAYS): array
    {
        $today = CarbonImmutable::now('Asia/Manila')->startOfDay();
        $last = $today->addDays($forecastDays - 1);
        $days = collect((array) ($record['daily'] ?? []))->filter(fn ($day): bool => is_array($day)
            && is_string($day['date'] ?? null)
            && $day['date'] >= $today->toDateString()
            && $day['date'] <= $last->toDateString())->values();
        $number = fn (mixed $value): ?float => is_numeric($value) ? (float) $value : null;
        $rain = $days->map(fn (array $day): ?float => $number($day['rainfall'] ?? $day['precipitation_mm'] ?? null))->filter(fn ($value) => $value !== null)->values();
        $humidity = $days->map(fn (array $day): ?float => $number($day['humidity_percent'] ?? null))->filter(fn ($value) => $value !== null)->values();
        $runoff = $days->map(fn (array $day): ?float => $number($day['runoff_mm'] ?? null))->filter(fn ($value) => $value !== null)->values();
        $soil = $days->map(fn (array $day): ?float => $number($day['soil_moisture'] ?? null))->filter(fn ($value) => $value !== null)->values();
        $evap = $days->map(fn (array $day): ?float => $number($day['evapotranspiration_mm'] ?? null))->filter(fn ($value) => $value !== null)->values();
        $metrics = [
            'days_covered' => $days->count(),
            'humidity_percent' => $humidity->isNotEmpty() ? round($humidity->avg(), 1) : null,
            'rain_total_mm' => $rain->isNotEmpty() ? round($rain->sum(), 1) : null,
            'wettest_day_mm' => $rain->isNotEmpty() ? round($rain->max(), 1) : null,
            'runoff_total_mm' => $runoff->isNotEmpty() ? round($runoff->sum(), 1) : null,
            'dry_days' => $rain->isNotEmpty() ? $rain->filter(fn (float $value): bool => $value < 1.0)->count() : null,
            'soil_moisture' => $soil->isNotEmpty() ? round($soil->avg(), 3) : null,
            'evapotranspiration_mm' => $evap->isNotEmpty() ? round($evap->sum(), 1) : null,
        ];

        $dryStreak = $wetStreak = $longestDry = $longestWet = 0;
        foreach ($rain as $value) {
            if ($value < 1.0) {
                $dryStreak++;
                $wetStreak = 0;
            } else {
                $wetStreak++;
                $dryStreak = 0;
            }
            $longestDry = max($longestDry, $dryStreak);
            $longestWet = max($longestWet, $wetStreak);
        }
        $metrics['longest_dry_streak'] = $longestDry;
        $metrics['longest_wet_streak'] = $longestWet;

        if ($days->isEmpty() || ($key === 'flood' && $rain->isEmpty() && $runoff->isEmpty()) || ($key === 'drought' && $rain->isEmpty() && $soil->isEmpty() && $evap->isEmpty())) {
            return ['level' => 'nodata', 'metrics' => $metrics];
        }

        $heavyThreshold = (float) config('weather_risk.flood_layer.moderate_daily_rain_mm', 25.0);
        $floodThreshold = (float) config('weather_risk.flood_layer.flood_prone_daily_rain_mm', 50.0);
        $floodTotal = (float) config('weather_risk.flood_layer.flood_prone_total_rain_mm', 200.0);
        $heavyDays = $rain->filter(fn (float $value): bool => $value >= $heavyThreshold)->count();
        $level = $key === 'flood'
            ? match (true) {
                ($metrics['wettest_day_mm'] ?? 0) >= $floodThreshold || ($metrics['rain_total_mm'] ?? 0) >= $floodTotal || $heavyDays >= 3 => 'high',
                ($metrics['wettest_day_mm'] ?? 0) >= $heavyThreshold || ($metrics['runoff_total_mm'] ?? 0) > 0 || $heavyDays > 0 => 'moderate',
                default => 'low',
            }
            : match (true) {
                ($metrics['dry_days'] ?? 0) >= 12 || (($metrics['soil_moisture'] ?? 1) < 0.15 && ($metrics['evapotranspiration_mm'] ?? 0) >= 40) => 'high',
                ($metrics['dry_days'] ?? 0) >= 5 || ($metrics['soil_moisture'] ?? 1) < 0.20 || (($metrics['evapotranspiration_mm'] ?? 0) >= 25 && ($metrics['rain_total_mm'] ?? 0) < 20) => 'moderate',
                default => 'low',
            };

        return ['level' => $level, 'metrics' => $metrics];
    }

    /** @return array{meaning: string, action: string} */
    private function dynamicText(string $key, string $level, array $assessment, string $locale): array
    {
        return [
            'meaning' => $this->t($key.'_meaning.'.$level, $locale, ['days' => $assessment['metrics']['days_covered'] ?? 0]),
            'action' => $this->t($key.'_action.'.$level, $locale),
        ];
    }

    private function t(string $key, string $locale, array $replace = []): mixed
    {
        return trans('farmer.heat_map.layers.'.$key, $replace, $locale);
    }
}
