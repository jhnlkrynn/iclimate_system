<?php

namespace App\Services\Risk;

use App\Models\ClimateRecord;
use App\Models\HeatmapArea;
use App\Services\PythonService;
use App\Services\Weather\FarmerDashboardWeatherService;
use App\Services\Weather\LianBarangayWeatherService;
use App\Support\BusinessClock;
use App\Support\LianBarangays;
use App\Support\RiceSeason;
use App\Support\SchemaCache;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class DroughtRiskService
{
    public function __construct(
        private readonly PythonService $python,
        private readonly LianBarangayWeatherService $barangayWeather,
        private readonly FarmerDashboardWeatherService $sharedWeather,
    ) {}

    /**
     * Refresh drought ratings for all 19 Lian barangays using:
     * (a) Python ML weather model, (b) stored Open-Meteo forecast (barangay or shared Lian),
     * (c) last saved drought ratings. Persists to heatmap_areas.risk_components['drought'].
     *
     * @return array<string, array<string, mixed>>
     */
    public function refreshAll(): array
    {
        $now = CarbonImmutable::now(BusinessClock::timezone());
        $barangayForecasts = rescue(fn (): array => $this->barangayWeather->savedForecasts(), [], false);
        $sharedForecast = $this->sharedForecastPayload();
        $savedAreas = $this->savedAreasByBarangay();

        $mlDownLogged = false;
        $mlHealthy = true;
        $results = [];

        foreach (LianBarangays::all() as $name) {
            $daily = $this->dailyRecordsFor($name, $barangayForecasts, $sharedForecast);
            $periodAssessments = [];

            foreach (['week' => 7, '2weeks' => 14] as $period => $daysCount) {
                $metrics = $this->extractMetrics($daily, $now, $daysCount);
                $assessed = null;

                // (a) Python ML service ("weather" model)
                if ($mlHealthy) {
                    $mlAttempt = $this->assessWithMl($name, $metrics, $daysCount, $now);
                    if ($mlAttempt['ml_reachable']) {
                        $assessed = $mlAttempt['assessment'];
                    } else {
                        $mlHealthy = false;
                        if (! $mlDownLogged) {
                            $mlDownLogged = true;
                            Log::warning('Drought risk refresh: Python ML service unavailable, falling back to stored Open-Meteo forecast.', [
                                'barangay' => $name,
                                'reason' => $mlAttempt['error'] ?? 'unreachable',
                            ]);
                        }
                    }
                }

                // (b) Stored Open-Meteo forecast (per-barangay, else shared Lian)
                if ($assessed === null && $metrics['days_covered'] > 0) {
                    $assessed = [
                        'level' => $this->rateFromMetrics($metrics, $daysCount),
                        'metrics' => $metrics,
                        'source' => 'iClimate forecast',
                        'updated_at' => $this->forecastUpdatedAt($name, $barangayForecasts, $sharedForecast, $now)->toIso8601String(),
                        'is_stale_fallback' => false,
                    ];
                }

                // (c) Last saved drought ratings
                if ($assessed === null) {
                    $assessed = $this->fromSavedArea($savedAreas->get($name), $period);
                }

                $periodAssessments[$period] = $assessed;
            }

            $primary = $periodAssessments['2weeks'];
            $results[$name] = $primary;

            $area = $savedAreas->get($name);
            if ($area !== null && ($primary['level'] ?? 'nodata') !== 'nodata') {
                $components = is_array($area->risk_components) ? $area->risk_components : [];
                $components['drought'] = [
                    'level' => $primary['level'],
                    'updated_at' => $primary['updated_at'],
                    'source' => $primary['source'],
                    'metrics' => $primary['metrics'],
                    'periods' => [
                        'week' => $periodAssessments['week'],
                        '2weeks' => $periodAssessments['2weeks'],
                    ],
                ];
                $area->forceFill(['risk_components' => $components])->save();
            }
        }

        return $results;
    }

    /**
     * Read-only assessment for page loads (never calls Python ML live).
     * Prefers saved ML/refresh ratings when available, else stored Open-Meteo forecast,
     * else last saved drought ratings marked with last updated time.
     *
     * @return array{barangays: array<string, array<string, mixed>>, source: string, updated_at: ?CarbonImmutable}
     */
    public function readForLayer(string $period = '2weeks'): array
    {
        $period = $period === 'week' ? 'week' : '2weeks';
        $daysCount = $period === '2weeks' ? 14 : 7;
        $now = CarbonImmutable::now(BusinessClock::timezone());
        $barangayForecasts = rescue(fn (): array => $this->barangayWeather->savedForecasts(), [], false);
        $sharedForecast = $this->sharedForecastPayload();
        $savedAreas = $this->savedAreasByBarangay();

        $rows = [];
        $sources = [];
        $updatedTimes = [];

        foreach (LianBarangays::all() as $name) {
            $saved = $this->fromSavedArea($savedAreas->get($name), $period);
            $daily = $this->dailyRecordsFor($name, $barangayForecasts, $sharedForecast);
            $metrics = $this->extractMetrics($daily, $now, $daysCount);

            $savedIsModel = $saved['level'] !== 'nodata' && str_contains(strtolower((string) $saved['source']), 'model');
            if ($savedIsModel) {
                $entry = $saved;
            } elseif ($metrics['days_covered'] > 0) {
                $entry = [
                    'level' => $this->rateFromMetrics($metrics, $daysCount),
                    'metrics' => $metrics,
                    'source' => 'iClimate forecast',
                    'updated_at' => $this->forecastUpdatedAt($name, $barangayForecasts, $sharedForecast, $now)->toIso8601String(),
                    'is_stale_fallback' => false,
                ];
            } else {
                $entry = $saved;
            }

            $rows[$name] = $entry;
            if ($entry['level'] !== 'nodata') {
                $sources[] = (string) $entry['source'];
                if (! empty($entry['updated_at'])) {
                    $parsed = rescue(fn () => CarbonImmutable::parse((string) $entry['updated_at']), null, false);
                    if ($parsed !== null) {
                        $updatedTimes[] = $parsed;
                    }
                }
            }
        }

        $sourceLabel = match (true) {
            in_array('iClimate model', $sources, true) => 'iClimate model',
            in_array('iClimate forecast', $sources, true) => 'iClimate forecast',
            $sources !== [] => 'Last saved drought ratings',
            default => 'iClimate forecast',
        };

        $latestUpdated = $updatedTimes !== [] ? collect($updatedTimes)->max() : null;

        return [
            'barangays' => $rows,
            'source' => $sourceLabel,
            'updated_at' => $latestUpdated,
        ];
    }

    /**
     * Rate drought risk (low / moderate / high) from total rain, dry days (<1 mm),
     * max temperature, and humidity using config('weather_risk.drought_layer').
     *
     * @param  array<string, mixed>  $metrics
     */
    public function rateFromMetrics(array $metrics, int $daysCount = 14): string
    {
        $cfg = (array) config('weather_risk.drought_layer', []);
        $rainTotal = $metrics['rain_total_mm'] ?? null;
        $dryDays = $metrics['dry_days'] ?? null;
        $tempMax = $metrics['temp_max_c'] ?? null;
        $humidity = $metrics['humidity_percent'] ?? null;

        if ($rainTotal === null && $dryDays === null && $tempMax === null && $humidity === null) {
            return 'nodata';
        }

        $highDryDays = (int) ($daysCount <= 7 ? ($cfg['high_dry_days_7d'] ?? 6) : ($cfg['high_dry_days_14d'] ?? 11));
        $modDryDays = (int) ($daysCount <= 7 ? ($cfg['moderate_dry_days_7d'] ?? 5) : ($cfg['moderate_dry_days_14d'] ?? 8));
        $highRainTotal = (float) ($cfg['high_total_rain_mm'] ?? 10.0);
        $modRainTotal = (float) ($cfg['moderate_total_rain_mm'] ?? 25.0);
        $highTemp = (float) ($cfg['high_max_temp_c'] ?? 34.5);
        $modTemp = (float) ($cfg['moderate_max_temp_c'] ?? 33.0);
        $lowHumidity = (float) ($cfg['low_humidity_percent'] ?? 68.0);

        $dryHotOrHumid = ($tempMax !== null && $tempMax >= $highTemp)
            || ($humidity !== null && $humidity <= $lowHumidity);

        if (
            ($dryDays !== null && $dryDays >= $highDryDays && ($rainTotal === null || $rainTotal <= $highRainTotal))
            || ($dryDays !== null && $dryDays >= $modDryDays && $dryHotOrHumid && ($rainTotal !== null && $rainTotal <= $highRainTotal))
        ) {
            return 'high';
        }

        if (
            ($dryDays !== null && $dryDays >= $modDryDays)
            || ($rainTotal !== null && $rainTotal < $modRainTotal && ($tempMax !== null && $tempMax >= $modTemp))
            || ($humidity !== null && $humidity <= $lowHumidity && ($rainTotal !== null && $rainTotal < $modRainTotal))
        ) {
            return 'moderate';
        }

        return 'low';
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array{ml_reachable: bool, assessment?: array<string, mixed>, error?: string}
     */
    private function assessWithMl(string $barangay, array $metrics, int $daysCount, CarbonImmutable $now): array
    {
        $season = RiceSeason::forMonth((int) $now->month);
        $prevRain = (float) ($metrics['rain_total_mm'] ?? 0.0);
        $prevTemp = (float) ($metrics['temp_max_c'] ?? 31.0);
        $prevHumidity = (float) ($metrics['humidity_percent'] ?? 75.0);
        $prevWind = (float) ($metrics['wind_max_kmh'] ?? 12.0);

        try {
            $response = $this->python->farmingAssistant([
                'prediction_type' => 'weather',
                'features' => [
                    'previous_rainfall' => $prevRain,
                    'previous_temp' => $prevTemp,
                    'previous_humidity' => $prevHumidity,
                    'previous_wind_speed' => $prevWind,
                    'month' => $now->month,
                    'season' => $season,
                    'barangay' => $barangay,
                ],
            ]);
        } catch (Throwable $exception) {
            return [
                'ml_reachable' => false,
                'error' => $exception->getMessage(),
            ];
        }

        $weatherPred = $response['weather_prediction'] ?? null;
        if (! ($response['success'] ?? false) || ! is_array($weatherPred) || empty($weatherPred['predicted_weather'])) {
            return [
                'ml_reachable' => false,
                'error' => (string) ($response['error'] ?? 'weather model failed'),
            ];
        }

        $label = strtolower((string) $weatherPred['predicted_weather']);
        $mlMetrics = $metrics;

        // If no daily forecast was available, synthesize baseline metrics from monthly ML or weather label.
        if ($mlMetrics['days_covered'] === 0) {
            $monthly = $this->monthlyMlFallback($now);
            $isDryLabel = str_contains($label, 'sun') || str_contains($label, 'clear') || str_contains($label, 'dry');
            $rain = $monthly['rainfall'] ?? ($isDryLabel ? 4.0 : 35.0);
            $mlMetrics = [
                'days_covered' => $daysCount,
                'rain_total_mm' => round((float) $rain * ($daysCount / 30), 1),
                'dry_days' => $isDryLabel ? max(1, $daysCount - 1) : (int) floor($daysCount / 2),
                'temp_max_c' => round((float) ($monthly['temperature'] ?? ($isDryLabel ? 34.0 : 31.0)), 1),
                'humidity_percent' => round((float) ($monthly['humidity'] ?? ($isDryLabel ? 66.0 : 78.0)), 1),
                'wind_max_kmh' => round((float) ($monthly['wind_speed'] ?? 12.0), 1),
                'ml_label' => (string) $weatherPred['predicted_weather'],
            ];
        } else {
            $mlMetrics['ml_label'] = (string) $weatherPred['predicted_weather'];
        }

        $level = $this->rateFromMetrics($mlMetrics, $daysCount);

        return [
            'ml_reachable' => true,
            'assessment' => [
                'level' => $level,
                'metrics' => $mlMetrics,
                'source' => 'iClimate model',
                'updated_at' => $now->toIso8601String(),
                'is_stale_fallback' => false,
            ],
        ];
    }

    /** @return array<string, float> */
    private function monthlyMlFallback(CarbonImmutable $now): array
    {
        if (! SchemaCache::hasTable('climate_records')) {
            return [];
        }
        $records = ClimateRecord::query()
            ->whereDate('record_date', '<=', $now->toDateString())
            ->orderByDesc('record_date')
            ->limit(24)
            ->get(['record_date', 'rainfall', 'temperature', 'humidity', 'wind_speed'])
            ->map(fn (ClimateRecord $r): array => [
                'record_date' => $r->record_date?->format('Y-m-d') ?? '',
                'rainfall' => (float) ($r->rainfall ?? 0),
                'temperature' => (float) ($r->temperature ?? 30),
                'humidity' => (float) ($r->humidity ?? 75),
                'wind_speed' => (float) ($r->wind_speed ?? 10),
            ])
            ->all();

        if ($records === []) {
            return [];
        }

        $res = $this->python->monthlyWeatherForecast($now, $records);

        return is_array($res['predictions'] ?? null) ? $res['predictions'] : [];
    }

    /**
     * @param  array<string, array<string, mixed>>  $barangayForecasts
     * @param  array<string, mixed>  $sharedForecast
     * @return array<int, array<string, mixed>>
     */
    private function dailyRecordsFor(string $barangay, array $barangayForecasts, array $sharedForecast): array
    {
        $daily = $barangayForecasts[$barangay]['daily'] ?? null;
        if (is_array($daily) && $daily !== []) {
            return $daily;
        }

        $extended = $sharedForecast['forecast_extended'] ?? $sharedForecast['forecast'] ?? [];

        return is_array($extended) ? $extended : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $daily
     * @return array<string, mixed>
     */
    private function extractMetrics(array $daily, CarbonImmutable $now, int $daysCount): array
    {
        $dryLimit = (float) config('weather_risk.drought_layer.dry_day_mm', 1.0);
        $today = $now->startOfDay();
        $last = $today->addDays($daysCount - 1);

        $days = collect($daily)->filter(fn ($day): bool => is_array($day)
            && is_string($day['date'] ?? null)
            && $day['date'] >= $today->toDateString()
            && $day['date'] <= $last->toDateString())->values();

        $num = fn (mixed $v): ?float => is_numeric($v) ? (float) $v : null;
        $rain = $days->map(fn (array $d): ?float => $num($d['rainfall'] ?? $d['precipitation_mm'] ?? null))->filter(fn ($v) => $v !== null)->values();
        $tempMax = $days->map(fn (array $d): ?float => $num($d['temp_max'] ?? $d['temperature_max'] ?? $d['temperature'] ?? null))->filter(fn ($v) => $v !== null)->values();
        $humidity = $days->map(fn (array $d): ?float => $num($d['humidity_percent'] ?? $d['humidity'] ?? null))->filter(fn ($v) => $v !== null)->values();
        $wind = $days->map(fn (array $d): ?float => $num($d['wind_speed_max'] ?? $d['wind_speed'] ?? null))->filter(fn ($v) => $v !== null)->values();

        return [
            'days_covered' => $days->count(),
            'rain_total_mm' => $rain->isNotEmpty() ? round((float) $rain->sum(), 1) : null,
            'dry_days' => $rain->isNotEmpty() ? $rain->filter(fn (float $v): bool => $v < $dryLimit)->count() : null,
            'temp_max_c' => $tempMax->isNotEmpty() ? round((float) $tempMax->max(), 1) : null,
            'humidity_percent' => $humidity->isNotEmpty() ? round((float) $humidity->avg(), 1) : null,
            'wind_max_kmh' => $wind->isNotEmpty() ? round((float) $wind->max(), 1) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function sharedForecastPayload(): array
    {
        try {
            $w = $this->sharedWeather->current(false);

            return is_array($w) && ($w['success'] ?? false) ? $w : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $barangayForecasts
     * @param  array<string, mixed>  $sharedForecast
     */
    private function forecastUpdatedAt(string $barangay, array $barangayForecasts, array $sharedForecast, CarbonImmutable $now): CarbonImmutable
    {
        $raw = $barangayForecasts[$barangay]['fetched_at']
            ?? data_get($sharedForecast, 'timestamps.server_fetched_at')
            ?? data_get($sharedForecast, 'timestamps.provider_updated_at')
            ?? null;

        return $raw
            ? rescue(fn () => CarbonImmutable::parse((string) $raw)->timezone(BusinessClock::timezone()), $now, false)
            : $now;
    }

    /** @return Collection<string, HeatmapArea> */
    private function savedAreasByBarangay(): Collection
    {
        if (! SchemaCache::hasTable('heatmap_areas')) {
            return collect();
        }

        return HeatmapArea::query()->get()
            ->keyBy(fn (HeatmapArea $area): string => LianBarangays::canonicalName($area->barangay) ?? $area->barangay);
    }

    /** @return array<string, mixed> */
    private function fromSavedArea(?HeatmapArea $area, string $period): array
    {
        $comp = is_array($area?->risk_components) ? ($area->risk_components['drought'] ?? null) : null;
        if (! is_array($comp)) {
            return ['level' => 'nodata', 'metrics' => ['days_covered' => 0], 'source' => 'Last saved drought ratings', 'updated_at' => null, 'is_stale_fallback' => true];
        }

        $entry = is_array($comp['periods'][$period] ?? null) ? $comp['periods'][$period] : $comp;
        $level = (string) ($entry['level'] ?? 'nodata');
        if (! in_array($level, ['low', 'moderate', 'high'], true)) {
            $level = 'nodata';
        }

        return [
            'level' => $level,
            'metrics' => is_array($entry['metrics'] ?? null) ? $entry['metrics'] : ['days_covered' => 0],
            'source' => (string) ($entry['source'] ?? $comp['source'] ?? 'Last saved drought ratings'),
            'updated_at' => $entry['updated_at'] ?? $comp['updated_at'] ?? $area?->updated_at?->toIso8601String(),
            'is_stale_fallback' => true,
        ];
    }
}
