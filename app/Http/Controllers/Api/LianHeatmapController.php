<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClimateRecord;
use App\Models\HeatmapArea;
use App\Models\PlantingAdvisory;
use App\Models\RiceProduction;
use App\Services\Geo\LianBarangayBoundaryService;
use App\Services\Heatmap\LianHeatmapRiskCalculator;
use App\Services\Weather\LianBarangayWeatherService;
use App\Services\Weather\PagasaWisObservationService;
use App\Support\LianFarmTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LianHeatmapController extends Controller
{
    public function __invoke(
        Request $request,
        LianBarangayWeatherService $weather,
        PagasaWisObservationService $pagasaWis,
        LianHeatmapRiskCalculator $riskCalculator,
        LianBarangayBoundaryService $boundaries
    ): JsonResponse
    {
        $refreshWeather = $request->boolean('refresh_weather');
        $refreshPagasa = $request->boolean('refresh_pagasa');
        $cacheKey = 'heatmap:lian-api:'.$this->dataVersion().':weather-'.($refreshWeather ? 'fresh' : 'cached');

        if (! $refreshWeather && ! $refreshPagasa && ! $request->boolean('refresh_boundaries')) {
            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                return response()->json($cached);
            }
        }

        $refreshBoundaries = $request->boolean('refresh_boundaries');
        [$boundaryGeojson, $boundarySource] = $boundaries->geojson(refresh: $refreshBoundaries);
        $centroids = $boundaries->centroids(refresh: $refreshBoundaries);
        $weatherByBarangay = $weather->all(refresh: $refreshWeather || $refreshBoundaries);
        $officialObservation = $pagasaWis->latest(refresh: $refreshPagasa);
        $latestClimate = ClimateRecord::query()
            ->whereDate('record_date', '<=', now()->toDateString())
            ->latest('record_date')
            ->latest('id')
            ->first();
        $latestPagasaAdvisory = PlantingAdvisory::query()
            ->active()
            ->fromPagasaOnline()
            ->latest('valid_from')
            ->latest('id')
            ->first();
        $areas = HeatmapArea::query()
            ->orderBy('barangay')
            ->get()
            ->unique('barangay')
            ->keyBy(fn (HeatmapArea $area): string => $this->normalizeBarangay($area->barangay));

        $seen = [];
        $features = collect($boundaryGeojson['features'] ?? [])
            ->map(function (array $feature) use ($areas, $weatherByBarangay, $officialObservation, $latestClimate, $latestPagasaAdvisory, $riskCalculator, $centroids, &$seen): ?array {
                $name = $this->featureBarangayName($feature);
                $key = $this->normalizeBarangay($name);

                if ($key === '' || isset($seen[$key])) {
                    return null;
                }

                $seen[$key] = true;
                $centroid = $centroids[$name] ?? null;
                $area = $areas->get($key);
                $risk = $this->areaPayload(
                    $name,
                    $centroid,
                    $area,
                    $weatherByBarangay[$key] ?? null,
                    $officialObservation,
                    $latestClimate,
                    $latestPagasaAdvisory,
                    $riskCalculator
                );
                $feature['properties'] = array_merge($feature['properties'] ?? [], [
                    'barangay' => $risk['barangay'],
                    'heatmap' => $risk,
                ]);

                return $feature;
            })
            ->filter()
            ->values()
            ->all();

        $payload = [
            'type' => 'FeatureCollection',
            'name' => 'iClimate Lian Barangay Heatmap API',
            'source' => $boundarySource,
            'source_credit' => 'Barangay boundaries come from GeoRisk ArcGIS PSA Barangay Boundary API when online, otherwise bundled iClimate GeoJSON is used.',
            'refresh_seconds' => (int) config('heatmap.refresh_seconds', 60),
            'weather_cache_minutes' => $weather->cacheMinutes(),
            'pagasa_wis_cache_seconds' => $pagasaWis->cacheSeconds(),
            'stale_after_minutes' => (int) config('heatmap.stale_after_minutes', 30),
            'data_sources' => [
                'boundary' => $boundarySource,
                'official_pagasa_observation' => ($officialObservation['status'] ?? null) === 'unavailable'
                    ? 'Insufficient data from PAGASA WIS/OAPI'
                    : 'PAGASA WIS/OAPI surface observation',
                'forecast_weather' => 'Open-Meteo Forecast API, requested for barangay coordinates and cached by iClimate',
                'observed_climate' => $latestClimate ? ($latestClimate->source ?: 'Stored iClimate climate record') : 'Insufficient data',
                'pagasa_advisory' => $latestPagasaAdvisory ? $latestPagasaAdvisory->sourceLabel() : 'No active stored PAGASA advisory',
                'farm_and_production' => 'Stored rice production, farm-location, and irrigation records',
                'ml_predicted' => 'Only included when an iClimate record identifies a trained rice yield model result',
            ],
            'generated_at' => now()->toIso8601String(),
            'features' => $features,
        ];

        if (! $refreshWeather && ! $refreshPagasa && ! $request->boolean('refresh_boundaries')) {
            Cache::put($cacheKey, $payload, now()->addSeconds((int) config('heatmap.refresh_seconds', 60)));
        }

        return response()->json($payload);
    }

    private function areaPayload(
        string $barangay,
        ?array $centroid,
        ?HeatmapArea $area,
        ?array $forecastWeather,
        ?array $officialObservation,
        ?ClimateRecord $latestClimate,
        ?PlantingAdvisory $latestPagasaAdvisory,
        LianHeatmapRiskCalculator $riskCalculator
    ): array {
        $productionStats = $this->productionStats($barangay);
        $risk = $riskCalculator->calculate(
            $barangay,
            $forecastWeather,
            $officialObservation,
            $latestClimate,
            $latestPagasaAdvisory,
            $productionStats,
            $area
        );

        return array_merge([
                'id' => $area?->id,
                'barangay' => $barangay,
                'latitude' => $centroid[0] ?? ($area?->latitude !== null ? (float) $area->latitude : null),
                'longitude' => $centroid[1] ?? ($area?->longitude !== null ? (float) $area->longitude : null),
                'coordinate_source' => $centroid ? 'boundary centroid' : ($area ? 'stored heatmap record' : 'unavailable'),
                'predicted_yield' => $area?->predicted_yield !== null ? (float) $area->predicted_yield : null,
                'rainfall_status' => $forecastWeather['rainfall_status'] ?? $area?->rainfall_status,
                'planting_advisory' => $area?->planting_advisory,
                'irrigation_recommendation' => $area?->irrigation_recommendation,
                'description' => $area?->description,
                'live_weather' => $forecastWeather,
                'source_credit' => 'Risk is calculated by iClimate from verified available weather, PAGASA, climate, farm, production, and validated prediction inputs. Missing inputs stay marked as insufficient data.',
            ], $productionStats, $risk);
    }

    private function dataVersion(): string
    {
        return hash('sha1', implode('|', [
            HeatmapArea::query()->count(),
            (string) (HeatmapArea::query()->max('updated_at') ?? 'none'),
            RiceProduction::query()->count(),
            (string) (RiceProduction::query()->max('updated_at') ?? 'none'),
            ClimateRecord::query()->count(),
            (string) (ClimateRecord::query()->max('updated_at') ?? 'none'),
            PlantingAdvisory::query()->active()->fromPagasaOnline()->count(),
            (string) (PlantingAdvisory::query()->active()->fromPagasaOnline()->max('updated_at') ?? 'none'),
            config('heatmap.refresh_seconds', 60),
        ]));
    }

    private function productionStats(string $barangay): array
    {
        $records = RiceProduction::query()
            ->where('barangay', $barangay)
            ->whereNotNull('yield_per_hectare')
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->get();
        $latest = $records->first();
        $irrigationRecords = RiceProduction::query()
            ->where('barangay', $barangay)
            ->whereNotNull('irrigation_type')
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->get();
        $latestIrrigation = $irrigationRecords->first();
        $rainfedCount = $irrigationRecords
            ->filter(fn (RiceProduction $record): bool => str_contains(strtolower((string) $record->irrigation_type), 'rainfed'))
            ->count();
        $irrigatedCount = $irrigationRecords
            ->filter(fn (RiceProduction $record): bool => str_contains(strtolower((string) $record->irrigation_type), 'irrigated'))
            ->count();
        $farmTypeSummary = LianFarmTypes::summaryFor($barangay);

        $stats = [
            'production_record_count' => $records->count(),
            'yield_basis' => $records->isNotEmpty() ? 'barangay_production_record' : 'no_barangay_production_record',
            'recorded_yield_avg' => $records->isNotEmpty() ? round((float) $records->avg('yield_per_hectare'), 2) : null,
            'recorded_yield_latest' => $latest?->yield_per_hectare !== null ? (float) $latest->yield_per_hectare : null,
            'recorded_yield_year' => $latest?->year,
            'recorded_yield_season' => $latest?->season,
            'recorded_area_hectares' => $latest?->area_hectares !== null ? (float) $latest->area_hectares : null,
            'recorded_total_production' => $latest?->total_production !== null ? (float) $latest->total_production : null,
            'farm_system' => $this->farmSystemLabel($rainfedCount, $irrigatedCount),
            'farm_system_basis' => $irrigationRecords->isNotEmpty() ? 'rice_production_irrigation_type' : 'no_irrigation_type_record',
            'farm_system_latest' => $latestIrrigation?->irrigation_type,
            'farm_system_year' => $latestIrrigation?->year,
            'farm_system_season' => $latestIrrigation?->season,
            'rainfed_record_count' => $rainfedCount,
            'irrigated_record_count' => $irrigatedCount,
            'rainfed_area_hectares' => null,
            'irrigated_area_hectares' => null,
            'farm_association_count' => 0,
            'farm_system_source' => $irrigationRecords->isNotEmpty() ? 'Rice production records' : null,
        ];

        return $farmTypeSummary ? array_merge($stats, $farmTypeSummary) : $stats;
    }

    private function farmSystemLabel(int $rainfedCount, int $irrigatedCount): string
    {
        if ($rainfedCount > 0 && $irrigatedCount > 0) {
            return 'Mixed rainfed and irrigated';
        }

        if ($rainfedCount > 0) {
            return 'Rainfed';
        }

        if ($irrigatedCount > 0) {
            return 'Irrigated';
        }

        return 'Unknown';
    }

    private function featureBarangayName(array $feature): string
    {
        $properties = $feature['properties'] ?? [];

        return trim((string) (
            $properties['barangay']
            ?? $properties['brgy_name']
            ?? $properties['BRGY_NAME']
            ?? $properties['name']
            ?? $properties['NAME']
            ?? ''
        ));
    }

    private function normalizeBarangay(string $barangay): string
    {
        $value = strtolower($barangay);
        $value = str_replace(['barangay', 'brgy', '(pob.)', 'pob.', 'poblacion', '-', '.'], ' ', $value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?: '';

        return trim(preg_replace('/\s+/', ' ', $value) ?: '');
    }
}
