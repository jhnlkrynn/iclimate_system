<?php

namespace App\Services\Heatmap;

use App\Models\ClimateRecord;
use App\Models\HeatmapArea;
use App\Models\PlantingAdvisory;
use Carbon\CarbonInterface;

class LianHeatmapRiskCalculator
{
    public function calculate(
        string $barangay,
        ?array $forecastWeather,
        ?array $pagasaObservation,
        ?ClimateRecord $latestClimate,
        ?PlantingAdvisory $pagasaAdvisory,
        array $productionStats,
        ?HeatmapArea $storedArea = null,
    ): array {
        $components = [];
        $reasons = [];
        $sources = [];
        $thresholds = config('heatmap.thresholds');
        $weights = config('heatmap.weights');

        if ($this->usableWeather($forecastWeather)) {
            $forecastRisk = $this->forecastWeatherRisk($forecastWeather, $thresholds);
            $components['forecast_weather'] = $forecastRisk['score'];
            $reasons[] = $forecastRisk['reason'];
            $sources[] = [
                'type' => 'forecast',
                'label' => $forecastWeather['source'] ?? 'Open-Meteo Forecast API',
                'url' => $forecastWeather['source_url'] ?? 'https://open-meteo.com/',
                'updated_at' => $forecastWeather['fetched_at'] ?? null,
                'scope' => $forecastWeather['scope'] ?? 'barangay-coordinate forecast',
                'stale' => (bool) ($forecastWeather['stale'] ?? false),
            ];
        }

        if ($this->usablePagasaObservation($pagasaObservation)) {
            $pagasaObservedRisk = $this->pagasaObservationRisk($pagasaObservation, $thresholds);
            $components['official_pagasa_observation'] = $pagasaObservedRisk['score'];
            $reasons[] = $pagasaObservedRisk['reason'];
            $sources[] = [
                'type' => 'official_observed',
                'label' => $pagasaObservation['source'] ?? 'PAGASA WIS/OAPI surface observation',
                'url' => $pagasaObservation['source_url'] ?? 'https://wis.pagasa.dost.gov.ph/oapi',
                'updated_at' => $pagasaObservation['observed_at'] ?? $pagasaObservation['fetched_at'] ?? null,
                'scope' => $pagasaObservation['scope'] ?? 'official station observation; not barangay-specific',
                'stale' => (bool) ($pagasaObservation['stale'] ?? false),
            ];
        }

        if ($latestClimate && $latestClimate->record_date) {
            $observedRisk = $this->observedClimateRisk($latestClimate, $thresholds);
            $components['observed_climate'] = $observedRisk['score'];
            $reasons[] = $observedRisk['reason'];
            $sources[] = [
                'type' => 'observed',
                'label' => $latestClimate->source ?: 'Stored climate record',
                'url' => null,
                'updated_at' => $latestClimate->record_date->toDateString(),
                'scope' => 'municipality/station record',
                'stale' => false,
            ];
        }

        if ($pagasaAdvisory) {
            $pagasaRisk = $this->pagasaRisk($pagasaAdvisory, $barangay);
            if ($pagasaRisk !== null) {
                $components['pagasa_advisory'] = $pagasaRisk['score'];
                $reasons[] = $pagasaRisk['reason'];
                $sources[] = [
                    'type' => 'pagasa',
                    'label' => $pagasaAdvisory->sourceLabel(),
                    'url' => $pagasaAdvisory->source_url,
                    'updated_at' => $this->dateTimeString($pagasaAdvisory->valid_from ?? $pagasaAdvisory->created_at),
                    'scope' => $pagasaAdvisory->target_barangay ? 'barangay advisory' : 'municipality/province advisory',
                    'stale' => false,
                ];
            }
        }

        $yield = $this->verifiedYield($productionStats, $storedArea);
        if ($yield !== null) {
            $yieldRisk = $this->yieldRisk($yield, $thresholds);
            $components['rice_yield'] = $yieldRisk['score'];
            $reasons[] = $yieldRisk['reason'];
            $sources[] = [
                'type' => 'observed',
                'label' => 'Rice production records',
                'url' => null,
                'updated_at' => $productionStats['recorded_yield_year'] ?? null,
                'scope' => 'barangay production record',
                'stale' => false,
            ];
        }

        if (($productionStats['farm_system'] ?? 'Unknown') !== 'Unknown') {
            $farmRisk = $this->farmSystemRisk((string) $productionStats['farm_system']);
            $components['farm_system'] = $farmRisk['score'];
            $reasons[] = $farmRisk['reason'];
            $sources[] = [
                'type' => 'estimated',
                'label' => $productionStats['farm_system_source'] ?? 'Farm profile / rice production records',
                'url' => null,
                'updated_at' => $productionStats['farm_system_year'] ?? null,
                'scope' => 'barangay farm system summary',
                'stale' => false,
            ];
        }

        if ($components === []) {
            return $this->insufficient($barangay, $forecastWeather, $pagasaObservation, $latestClimate, $productionStats, $pagasaAdvisory);
        }

        $score = $this->weightedScore($components, $weights);
        $level = $this->levelFromScore($score);
        $riskType = $this->riskType($components, $forecastWeather, $pagasaObservation, $pagasaAdvisory, $latestClimate);
        $updatedAt = $this->latestSourceTime($sources) ?? $this->dateTimeString($storedArea?->updated_at) ?? now()->toIso8601String();

        return [
            'status' => 'calculated',
            'display_level' => $level,
            'risk_level' => $level,
            'risk_type' => $riskType,
            'risk_score' => $score,
            'reason' => implode(' ', array_unique(array_filter($reasons))),
            'data_quality' => 'Calculated from verified available inputs',
            'weather_scope_note' => $this->weatherScopeNote($forecastWeather),
            'components' => $components,
            'sources' => $sources,
            'weather_values' => $this->weatherValues($forecastWeather, $pagasaObservation, $latestClimate),
            'data_sections' => $this->dataSections($forecastWeather, $pagasaObservation, $latestClimate, $storedArea, $productionStats, $pagasaAdvisory),
            'updated_at' => $updatedAt,
            'is_outdated' => (bool) data_get($forecastWeather, 'stale', false) || (bool) data_get($pagasaObservation, 'stale', false),
            'thresholds' => $thresholds,
        ];
    }

    private function insufficient(string $barangay, ?array $forecastWeather, ?array $pagasaObservation, ?ClimateRecord $latestClimate, array $productionStats, ?PlantingAdvisory $pagasaAdvisory): array
    {
        return [
            'status' => 'insufficient_data',
            'display_level' => 'Insufficient data',
            'risk_level' => 'Low',
            'risk_type' => 'Insufficient data',
            'risk_score' => null,
            'reason' => 'Insufficient verified weather, PAGASA, climate, production, farm-location, or prediction data to calculate a barangay risk score.',
            'data_quality' => 'Insufficient data',
            'weather_scope_note' => $this->weatherScopeNote($forecastWeather),
            'components' => [],
            'sources' => [],
            'weather_values' => $this->weatherValues($forecastWeather, $pagasaObservation, $latestClimate),
            'data_sections' => $this->dataSections($forecastWeather, $pagasaObservation, $latestClimate, null, $productionStats, $pagasaAdvisory),
            'updated_at' => now()->toIso8601String(),
            'is_outdated' => (bool) data_get($forecastWeather, 'stale', false) || (bool) data_get($pagasaObservation, 'stale', false),
            'thresholds' => config('heatmap.thresholds'),
        ];
    }

    private function usableWeather(?array $weather): bool
    {
        return is_array($weather)
            && ($weather['status'] ?? null) !== 'unavailable'
            && (is_numeric($weather['rainfall_24h_mm'] ?? null)
                || is_numeric($weather['rainfall_7d_mm'] ?? null)
                || is_numeric($weather['temperature_c'] ?? null)
                || is_numeric($weather['wind_speed_kmh'] ?? null));
    }

    private function forecastWeatherRisk(array $weather, array $thresholds): array
    {
        $rain24h = $this->number($weather['rainfall_24h_mm'] ?? null);
        $rain7d = $this->number($weather['rainfall_7d_mm'] ?? null);
        $temperature = $this->number($weather['temperature_c'] ?? null);
        $wind = $this->number($weather['wind_speed_kmh'] ?? null);
        $scores = [];
        $reasons = [];

        if ($rain24h !== null || $rain7d !== null) {
            $rainScore = match (true) {
                $rain24h !== null && $rain24h >= $thresholds['rainfall_24h_high_mm'] => 0.90,
                $rain7d !== null && $rain7d >= $thresholds['rainfall_7d_high_mm'] => 0.90,
                $rain7d !== null && $rain7d < $thresholds['rainfall_7d_low_mm'] => 0.76,
                $rain24h !== null && $rain24h >= $thresholds['rainfall_24h_watch_mm'] => 0.55,
                $rain7d !== null && $rain7d >= $thresholds['rainfall_7d_watch_mm'] => 0.55,
                default => 0.20,
            };
            $scores[] = $rainScore;
            $reasons[] = sprintf('Forecast rainfall is %s mm in 24h and %s mm in 7d.', $this->valueText($rain24h), $this->valueText($rain7d));
        }

        if ($temperature !== null) {
            $scores[] = match (true) {
                $temperature >= $thresholds['temperature_high_c'] => 0.82,
                $temperature >= $thresholds['temperature_watch_c'] => 0.58,
                default => 0.18,
            };
            $reasons[] = sprintf('Forecast temperature is %s C.', $this->valueText($temperature));
        }

        if ($wind !== null) {
            $scores[] = match (true) {
                $wind >= $thresholds['wind_high_kmh'] => 0.82,
                $wind >= $thresholds['wind_watch_kmh'] => 0.55,
                default => 0.16,
            };
            $reasons[] = sprintf('Forecast wind speed is %s km/h.', $this->valueText($wind));
        }

        return [
            'score' => round(max($scores ?: [0]), 2),
            'reason' => implode(' ', $reasons),
        ];
    }

    private function usablePagasaObservation(?array $observation): bool
    {
        return is_array($observation)
            && ($observation['status'] ?? null) !== 'unavailable'
            && (is_numeric($observation['rainfall_mm'] ?? null)
                || is_numeric($observation['temperature_c'] ?? null)
                || is_numeric($observation['humidity_percent'] ?? null)
                || is_numeric($observation['wind_speed_kmh'] ?? null));
    }

    private function pagasaObservationRisk(array $observation, array $thresholds): array
    {
        $rain = $this->number($observation['rainfall_mm'] ?? null);
        $temperature = $this->number($observation['temperature_c'] ?? null);
        $wind = $this->number($observation['wind_speed_kmh'] ?? null);
        $scores = [];
        $reasons = [];

        if ($rain !== null) {
            $scores[] = match (true) {
                $rain >= $thresholds['rainfall_24h_high_mm'] => 0.90,
                $rain >= $thresholds['rainfall_24h_watch_mm'] => 0.58,
                default => 0.18,
            };
            $reasons[] = sprintf('Official PAGASA station rainfall observation is %s mm.', $this->valueText($rain));
        }

        if ($temperature !== null) {
            $scores[] = match (true) {
                $temperature >= $thresholds['temperature_high_c'] => 0.82,
                $temperature >= $thresholds['temperature_watch_c'] => 0.58,
                default => 0.18,
            };
            $reasons[] = sprintf('Official PAGASA station temperature observation is %s C.', $this->valueText($temperature));
        }

        if ($wind !== null) {
            $scores[] = match (true) {
                $wind >= $thresholds['wind_high_kmh'] => 0.82,
                $wind >= $thresholds['wind_watch_kmh'] => 0.55,
                default => 0.16,
            };
            $reasons[] = sprintf('Official PAGASA station wind observation is %s km/h.', $this->valueText($wind));
        }

        return [
            'score' => round(max($scores ?: [0]), 2),
            'reason' => implode(' ', $reasons),
        ];
    }

    private function observedClimateRisk(ClimateRecord $record, array $thresholds): array
    {
        $rain = $this->number($record->rainfall);
        $temperature = $this->number($record->temperature);
        $scores = [];

        if ($rain !== null) {
            $scores[] = match (true) {
                $rain >= $thresholds['rainfall_7d_high_mm'] => 0.78,
                $rain < $thresholds['rainfall_7d_low_mm'] => 0.66,
                default => 0.25,
            };
        }

        if ($temperature !== null) {
            $scores[] = match (true) {
                $temperature >= $thresholds['temperature_high_c'] => 0.76,
                $temperature >= $thresholds['temperature_watch_c'] => 0.52,
                default => 0.20,
            };
        }

        return [
            'score' => round(max($scores ?: [0]), 2),
            'reason' => sprintf('Latest observed climate record has %s mm rainfall and %s C temperature.', $this->valueText($rain), $this->valueText($temperature)),
        ];
    }

    private function pagasaRisk(PlantingAdvisory $advisory, string $barangay): ?array
    {
        if ($advisory->target_barangay && strcasecmp($advisory->target_barangay, $barangay) !== 0) {
            return null;
        }

        $score = match ($advisory->severity) {
            PlantingAdvisory::SEVERITY_CRITICAL => 0.95,
            PlantingAdvisory::SEVERITY_HIGH => 0.82,
            PlantingAdvisory::SEVERITY_MODERATE => 0.58,
            PlantingAdvisory::SEVERITY_LOW => 0.32,
            default => 0.20,
        };

        return [
            'score' => $score,
            'reason' => 'Active PAGASA advisory signal: '.($advisory->title ?: $advisory->summary ?: 'published advisory').'.',
        ];
    }

    private function yieldRisk(float $yield, array $thresholds): array
    {
        $score = match (true) {
            $yield < $thresholds['yield_high_risk_t_ha'] => 0.78,
            $yield < $thresholds['yield_watch_t_ha'] => 0.50,
            default => 0.18,
        };

        return [
            'score' => $score,
            'reason' => sprintf('Latest verified barangay yield is %.2f t/ha.', $yield),
        ];
    }

    private function farmSystemRisk(string $farmSystem): array
    {
        $lower = strtolower($farmSystem);
        $score = match (true) {
            str_contains($lower, 'rainfed') && str_contains($lower, 'irrigated') => 0.48,
            str_contains($lower, 'rainfed') => 0.62,
            str_contains($lower, 'irrigated') => 0.24,
            default => 0.35,
        };

        return [
            'score' => $score,
            'reason' => 'Farm system factor: '.$farmSystem.'.',
        ];
    }

    private function verifiedYield(array $productionStats, ?HeatmapArea $storedArea): ?float
    {
        if (isset($productionStats['recorded_yield_latest']) && is_numeric($productionStats['recorded_yield_latest'])) {
            return (float) $productionStats['recorded_yield_latest'];
        }

        $description = strtolower((string) $storedArea?->description);
        if ($storedArea?->predicted_yield !== null && str_contains($description, 'trained rice yield model')) {
            return (float) $storedArea->predicted_yield;
        }

        return null;
    }

    private function weightedScore(array $components, array $weights): float
    {
        $weighted = 0.0;
        $totalWeight = 0.0;

        foreach ($components as $key => $score) {
            $weight = (float) ($weights[$key] ?? 0);
            $weighted += (float) $score * $weight;
            $totalWeight += $weight;
        }

        return round($totalWeight > 0 ? $weighted / $totalWeight : 0, 2);
    }

    private function levelFromScore(float $score): string
    {
        $levels = config('heatmap.levels');

        return match (true) {
            $score >= $levels['severe'] => 'Severe',
            $score >= $levels['high'] => 'High',
            $score >= $levels['moderate'] => 'Moderate',
            default => 'Low',
        };
    }

    private function riskType(array $components, ?array $weather, ?array $pagasaObservation, ?PlantingAdvisory $pagasa, ?ClimateRecord $climate): string
    {
        $text = strtolower(($pagasa?->title ?? '').' '.($pagasa?->summary ?? '').' '.($pagasa?->message ?? ''));

        if (str_contains($text, 'typhoon') || str_contains($text, 'storm') || str_contains($text, 'wind')) {
            return 'Typhoon';
        }

        $rain24h = $this->number(data_get($weather, 'rainfall_24h_mm'));
        $rain7d = $this->number(data_get($weather, 'rainfall_7d_mm'));
        $officialRain = $this->number(data_get($pagasaObservation, 'rainfall_mm'));
        $officialWind = $this->number(data_get($pagasaObservation, 'wind_speed_kmh'));
        $temperature = $this->number(data_get($pagasaObservation, 'temperature_c') ?? data_get($weather, 'temperature_c') ?? $climate?->temperature);

        if ($officialWind !== null && $officialWind >= config('heatmap.thresholds.wind_watch_kmh')) {
            return 'Typhoon';
        }

        if (($rain24h !== null && $rain24h >= config('heatmap.thresholds.rainfall_24h_high_mm'))
            || ($rain7d !== null && $rain7d >= config('heatmap.thresholds.rainfall_7d_high_mm'))
            || ($officialRain !== null && $officialRain >= config('heatmap.thresholds.rainfall_24h_watch_mm'))) {
            return 'Flood';
        }

        if ($temperature !== null && $temperature >= config('heatmap.thresholds.temperature_watch_c')) {
            return 'Heat';
        }

        if ($rain7d !== null && $rain7d < config('heatmap.thresholds.rainfall_7d_low_mm')) {
            return 'Drought';
        }

        return 'Drought';
    }

    private function dataSections(?array $forecastWeather, ?array $pagasaObservation, ?ClimateRecord $latestClimate, ?HeatmapArea $storedArea, array $productionStats, ?PlantingAdvisory $pagasaAdvisory): array
    {
        return [
            'official_observed' => [
                'label' => 'Official PAGASA station observation',
                'available' => $this->usablePagasaObservation($pagasaObservation),
                'rainfall_mm' => $this->number(data_get($pagasaObservation, 'rainfall_mm')),
                'temperature_c' => $this->number(data_get($pagasaObservation, 'temperature_c')),
                'humidity_percent' => $this->number(data_get($pagasaObservation, 'humidity_percent')),
                'wind_speed_kmh' => $this->number(data_get($pagasaObservation, 'wind_speed_kmh')),
                'source' => data_get($pagasaObservation, 'source'),
                'station_id' => data_get($pagasaObservation, 'station_id'),
                'station_name' => data_get($pagasaObservation, 'station_name'),
                'updated_at' => data_get($pagasaObservation, 'observed_at'),
                'scope' => data_get($pagasaObservation, 'scope', 'official station observation; not barangay-specific'),
                'outdated' => (bool) data_get($pagasaObservation, 'stale', false),
            ],
            'observed' => [
                'label' => 'Observed climate record',
                'available' => (bool) $latestClimate,
                'rainfall_mm' => $latestClimate?->rainfall !== null ? (float) $latestClimate->rainfall : null,
                'temperature_c' => $latestClimate?->temperature !== null ? (float) $latestClimate->temperature : null,
                'humidity_percent' => $latestClimate?->humidity !== null ? (float) $latestClimate->humidity : null,
                'wind_speed_kmh' => $latestClimate?->wind_speed !== null ? (float) $latestClimate->wind_speed : null,
                'source' => $latestClimate?->source,
                'updated_at' => $latestClimate?->record_date?->toDateString(),
                'scope' => 'municipality/station record; not barangay-specific',
            ],
            'forecast' => [
                'label' => 'Weather API forecast',
                'available' => $this->usableWeather($forecastWeather),
                'rainfall_24h_mm' => $this->number(data_get($forecastWeather, 'rainfall_24h_mm')),
                'rainfall_7d_mm' => $this->number(data_get($forecastWeather, 'rainfall_7d_mm')),
                'temperature_c' => $this->number(data_get($forecastWeather, 'temperature_c')),
                'humidity_percent' => $this->number(data_get($forecastWeather, 'humidity_percent')),
                'wind_speed_kmh' => $this->number(data_get($forecastWeather, 'wind_speed_kmh')),
                'source' => data_get($forecastWeather, 'source'),
                'updated_at' => data_get($forecastWeather, 'fetched_at'),
                'scope' => data_get($forecastWeather, 'scope', 'barangay-coordinate forecast'),
                'outdated' => (bool) data_get($forecastWeather, 'stale', false),
            ],
            'ml_predicted' => [
                'label' => 'Validated ML prediction',
                'available' => $storedArea?->predicted_yield !== null && str_contains(strtolower((string) $storedArea->description), 'trained rice yield model'),
                'predicted_yield_t_ha' => $storedArea?->predicted_yield !== null ? (float) $storedArea->predicted_yield : null,
                'source' => 'iClimate trained rice yield model',
                'updated_at' => $this->dateTimeString($storedArea?->updated_at),
            ],
            'estimated' => [
                'label' => 'Farm and production summary',
                'available' => ($productionStats['farm_system'] ?? 'Unknown') !== 'Unknown' || ($productionStats['recorded_yield_latest'] ?? null) !== null,
                'recorded_yield_latest' => $productionStats['recorded_yield_latest'] ?? null,
                'recorded_yield_avg' => $productionStats['recorded_yield_avg'] ?? null,
                'farm_system' => $productionStats['farm_system'] ?? 'Unknown',
                'source' => $productionStats['farm_system_source'] ?? 'Rice production records',
                'updated_at' => $productionStats['recorded_yield_year'] ?? $productionStats['farm_system_year'] ?? null,
            ],
            'pagasa' => [
                'label' => 'PAGASA advisory',
                'available' => (bool) $pagasaAdvisory,
                'title' => $pagasaAdvisory?->title,
                'severity' => $pagasaAdvisory?->severityLabel(),
                'source' => $pagasaAdvisory?->sourceLabel(),
                'url' => $pagasaAdvisory?->source_url,
                'updated_at' => $this->dateTimeString($pagasaAdvisory?->valid_from ?? $pagasaAdvisory?->created_at),
                'scope' => $pagasaAdvisory?->target_barangay ? 'barangay advisory' : 'municipality/province advisory',
            ],
        ];
    }

    private function weatherValues(?array $forecastWeather, ?array $pagasaObservation, ?ClimateRecord $latestClimate): array
    {
        return [
            'official_pagasa_rainfall_mm' => $this->number(data_get($pagasaObservation, 'rainfall_mm')),
            'official_pagasa_temperature_c' => $this->number(data_get($pagasaObservation, 'temperature_c')),
            'official_pagasa_humidity_percent' => $this->number(data_get($pagasaObservation, 'humidity_percent')),
            'official_pagasa_wind_speed_kmh' => $this->number(data_get($pagasaObservation, 'wind_speed_kmh')),
            'forecast_rainfall_24h_mm' => $this->number(data_get($forecastWeather, 'rainfall_24h_mm')),
            'forecast_rainfall_7d_mm' => $this->number(data_get($forecastWeather, 'rainfall_7d_mm')),
            'forecast_temperature_c' => $this->number(data_get($forecastWeather, 'temperature_c')),
            'forecast_humidity_percent' => $this->number(data_get($forecastWeather, 'humidity_percent')),
            'forecast_wind_speed_kmh' => $this->number(data_get($forecastWeather, 'wind_speed_kmh')),
            'observed_rainfall_mm' => $latestClimate?->rainfall !== null ? (float) $latestClimate->rainfall : null,
            'observed_temperature_c' => $latestClimate?->temperature !== null ? (float) $latestClimate->temperature : null,
            'observed_humidity_percent' => $latestClimate?->humidity !== null ? (float) $latestClimate->humidity : null,
            'observed_wind_speed_kmh' => $latestClimate?->wind_speed !== null ? (float) $latestClimate->wind_speed : null,
        ];
    }

    private function weatherScopeNote(?array $weather): string
    {
        return match (data_get($weather, 'scope')) {
            'municipality' => 'Weather API provides one municipality-wide reading; barangay weather values are intentionally identical and local differences come only from verified farm/production/advisory data.',
            default => 'Weather API values are requested for barangay coordinates when available. Treat them as forecast support, not official rain-gauge observations.',
        };
    }

    private function latestSourceTime(array $sources): ?string
    {
        $times = collect($sources)
            ->pluck('updated_at')
            ->filter()
            ->map(fn ($value) => strtotime((string) $value) ?: null)
            ->filter();

        return $times->isEmpty() ? null : date(DATE_ATOM, $times->max());
    }

    private function dateTimeString(?CarbonInterface $date): ?string
    {
        return $date?->toIso8601String();
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function valueText(?float $value): string
    {
        return $value === null ? 'unavailable' : number_format($value, 1);
    }
}
