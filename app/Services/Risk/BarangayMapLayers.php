<?php

namespace App\Services\Risk;

use App\Models\FarmerOrganization;
use App\Services\Prediction\BarangayYieldForecastService;
use App\Services\Weather\LianBarangayWeatherService;
use App\Support\LianBarangays;
use App\Support\SchemaCache;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Manuscript – Naicel (2026-10-08), Phase 3: the Heat Map's extra layers next to This Week / 2 Weeks. Each layer has
 * the same shape as a HeatMapPanel range, so the page and resources/js/heat-map.js show it the same way:
 *  - flood / drought: the saved per-barangay forecast at each official Lian centroid, assessed separately for all 19;
 *  - yield: the shared rice-yield model run for each official Lian barangay. Its accuracy is not verified; the layer
 *    remains operationally available but must not be presented as a validated yield classification.
 * The weather service owns provider requests, retry/fallback behavior, and the last-valid cache. This service only
 * reads that Lian-only snapshot and derives the two layer assessments; yield results are cached by their source/model signature.
 */
class BarangayMapLayers
{
    public const KEYS = ['flood', 'drought', 'yield', 'irrigation'];

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

        $irrigationAssessments = self::irrigationAssessments();
        $irrigationUpdated = collect($irrigationAssessments)
            ->pluck('updated_at')
            ->filter()
            ->max();
        $layers['irrigation'] = $this->layer(
            'irrigation',
            $locale,
            fn (string $name): string => $irrigationAssessments[$name]['level'] ?? 'nodata',
            function (string $name, string $level) use ($irrigationAssessments, $locale): array {
                $info = $irrigationAssessments[$name] ?? null;
                if ($info === null || $level === 'nodata') {
                    return [
                        'meaning' => $this->t('irrigation_meaning_nodata', $locale),
                        'action' => $this->t('irrigation_action_nodata', $locale),
                    ];
                }

                $assocLines = array_map(function (array $a): string {
                    $parts = [$a['name']];
                    if ($a['area_hectares'] !== null) {
                        $parts[] = number_format((float) $a['area_hectares'], 2).' ha';
                    }
                    if ($a['farm_type']) {
                        $parts[] = $a['farm_type'];
                    }

                    return implode(' · ', $parts);
                }, $info['associations']);

                return [
                    'meaning' => $this->t('irrigation_meaning_data', $locale, [
                        'total' => number_format($info['total_ha'], 2),
                        'irrigated' => number_format($info['irrigated_ha'], 2),
                        'rainfed' => number_format($info['rainfed_ha'], 2),
                    ]),
                    'action' => $this->t('irrigation_action_data', $locale),
                    'also' => $assocLines,
                    'numbers' => array_filter([
                        $info['irrigated_ha'] > 0 ? $this->t('irrigation_irrigated_ha', $locale, ['ha' => number_format($info['irrigated_ha'], 2)]) : null => $info['irrigated_ha'] > 0 ? number_format($info['irrigated_ha'], 2).' ha' : null,
                        $info['rainfed_ha'] > 0 ? $this->t('irrigation_rainfed_ha', $locale, ['ha' => number_format($info['rainfed_ha'], 2)]) : null => $info['rainfed_ha'] > 0 ? number_format($info['rainfed_ha'], 2).' ha' : null,
                    ], fn ($v): bool => $v !== null),
                    'irrigation' => [
                        'irrigated_ha' => $info['irrigated_ha'],
                        'rainfed_ha' => $info['rainfed_ha'],
                        'total_ha' => $info['total_ha'],
                        'associations' => $info['associations'],
                        'inferred' => $info['inferred'],
                    ],
                ];
            },
            $this->t('irrigation_source', $locale),
            $irrigationUpdated,
        );
        $layers['irrigation']['has_inferred'] = collect($irrigationAssessments)->contains(fn (array $a): bool => (bool) ($a['inferred'] ?? false));

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
        $counts = array_fill_keys(array_keys($labels), 0);
        foreach (LianBarangays::all() as $name) {
            $level = $levelOf($name);
            $counts[$level] = ($counts[$level] ?? 0) + 1;
            $barangays[$name] = array_merge([
                'barangay' => $name,
                'name' => HeatMapPanel::displayName($name),
                'level' => $level,
                'label' => $labels[$level] ?? $level,
                'also' => [],
                'partial' => null,
                'numbers' => [],
            ], $text($name, $level));
        }

        return [
            'range' => $key,
            'layer' => true,
            'days' => $days,
            'state' => ($counts['nodata'] ?? 0) === count($barangays) ? 'nodata' : 'ok',
            'updated_at' => $updatedAt,
            'delayed' => false,
            'title' => $this->t($key.'_title', $locale),
            'counts' => $counts,
            'count_lines' => collect(array_keys($labels))
                ->filter(fn (string $level): bool => ($counts[$level] ?? 0) > 0)
                ->map(fn (string $level): array => ['level' => $level, 'text' => trans_choice('farmer.heat_map.layers.count', $counts[$level], ['count' => $counts[$level]], $locale).': '.$labels[$level]])
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

    /**
     * Per-barangay irrigated vs rainfed summary from farmer_organizations.
     *
     * @return array<string, array{level: string, irrigated_ha: float, rainfed_ha: float, total_ha: float, inferred: bool, updated_at: ?CarbonImmutable, associations: list<array{name: string, area_hectares: ?float, farm_type: ?string, inferred: bool}>}>
     */
    public static function irrigationAssessments(): array
    {
        if (! SchemaCache::hasTable('farmer_organizations')) {
            return [];
        }

        $hasSource = Schema::hasColumn('farmer_organizations', 'farm_type_source');
        $columns = ['name', 'barangay', 'farm_type', 'area_hectares', 'updated_at'];
        if ($hasSource) {
            $columns[] = 'farm_type_source';
        }

        $grouped = FarmerOrganization::query()
            ->orderBy('name')
            ->get($columns)
            ->groupBy(fn (FarmerOrganization $org): string => LianBarangays::canonicalName($org->barangay) ?? $org->barangay);

        $result = [];
        foreach (LianBarangays::all() as $name) {
            $orgs = $grouped->get($name, collect());
            if ($orgs->isEmpty()) {
                $result[$name] = [
                    'level' => 'nodata',
                    'irrigated_ha' => 0.0,
                    'rainfed_ha' => 0.0,
                    'total_ha' => 0.0,
                    'inferred' => false,
                    'updated_at' => null,
                    'associations' => [],
                ];
                continue;
            }

            $irrigatedHa = round((float) $orgs->where('farm_type', 'Irrigated')->sum('area_hectares'), 2);
            $rainfedHa = round((float) $orgs->where('farm_type', 'Rainfed')->sum('area_hectares'), 2);
            $totalHa = round($irrigatedHa + $rainfedHa, 2);

            $hasIrrigated = $orgs->contains(fn (FarmerOrganization $o): bool => $o->farm_type === 'Irrigated');
            $hasRainfed = $orgs->contains(fn (FarmerOrganization $o): bool => $o->farm_type === 'Rainfed');
            $level = match (true) {
                $hasIrrigated && $hasRainfed => 'irrigated_and_rainfed',
                $hasIrrigated => 'irrigated',
                $hasRainfed => 'rainfed',
                default => 'nodata',
            };

            $result[$name] = [
                'level' => $level,
                'irrigated_ha' => $irrigatedHa,
                'rainfed_ha' => $rainfedHa,
                'total_ha' => $totalHa,
                'inferred' => $hasSource && $orgs->contains(fn (FarmerOrganization $o): bool => ! empty($o->farm_type_source)),
                'updated_at' => $orgs->pluck('updated_at')->filter()->map(fn ($dt) => CarbonImmutable::parse($dt))->max(),
                'associations' => $orgs->map(fn (FarmerOrganization $o): array => [
                    'name' => (string) $o->name,
                    'area_hectares' => $o->area_hectares !== null ? round((float) $o->area_hectares, 2) : null,
                    'farm_type' => $o->farm_type,
                    'inferred' => $hasSource && ! empty($o->farm_type_source),
                ])->values()->all(),
            ];
        }

        return $result;
    }

    private function t(string $key, string $locale, array $replace = []): mixed
    {
        return trans('farmer.heat_map.layers.'.$key, $replace, $locale);
    }
}
