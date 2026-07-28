<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HeatmapArea;
use App\Models\RiceProduction;
use App\Services\Weather\LianBarangayWeatherService;
use App\Support\LianFarmTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LianHeatmapController extends Controller
{
    private const GEORISK_QUERY_URL = 'https://portal.georisk.gov.ph/arcgis/rest/services/PSA/Barangay/MapServer/4/query';

    public function __invoke(Request $request, LianBarangayWeatherService $weather): JsonResponse
    {
        $refresh = ! $request->boolean('cached_weather');
        $weatherByBarangay = $weather->all(refresh: $refresh);
        $areas = HeatmapArea::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('barangay')
            ->get()
            ->unique('barangay')
            ->values();

        $riskByBarangay = $areas->mapWithKeys(fn (HeatmapArea $area): array => [
            $this->normalizeBarangay($area->barangay) => array_merge([
                'id' => $area->id,
                'barangay' => $area->barangay,
                'latitude' => (float) $area->latitude,
                'longitude' => (float) $area->longitude,
                'risk_level' => $area->risk_level,
                'risk_type' => $area->risk_type,
                'risk_score' => (float) $area->risk_score,
                'predicted_yield' => $area->predicted_yield !== null ? (float) $area->predicted_yield : null,
                'rainfall_status' => $area->rainfall_status,
                'planting_advisory' => $area->planting_advisory,
                'irrigation_recommendation' => $area->irrigation_recommendation,
                'description' => $area->description,
                'updated_at' => $area->updated_at?->format('M d, Y g:i A'),
                'live_weather' => $weatherByBarangay[$this->normalizeBarangay($area->barangay)] ?? null,
            ], $this->productionStats($area->barangay)),
        ])->all();

        [$boundaryGeojson, $boundarySource] = $this->boundaryGeojson(refresh: $request->boolean('refresh_boundaries'));
        $features = collect($boundaryGeojson['features'] ?? [])
            ->map(function (array $feature) use ($riskByBarangay): ?array {
                $name = $this->featureBarangayName($feature);
                $risk = $riskByBarangay[$this->normalizeBarangay($name)] ?? null;

                if (! $risk) {
                    return null;
                }

                $feature['properties'] = array_merge($feature['properties'] ?? [], [
                    'barangay' => $risk['barangay'],
                    'heatmap' => $risk,
                ]);

                return $feature;
            })
            ->filter()
            ->values()
            ->all();

        return response()->json([
            'type' => 'FeatureCollection',
            'name' => 'iClimate Lian Barangay Heatmap API',
            'source' => $boundarySource,
            'generated_at' => now()->toDateTimeString(),
            'features' => $features,
        ]);
    }

    private function boundaryGeojson(bool $refresh = false): array
    {
        if ($refresh) {
            Cache::forget('heatmap:lian-boundaries-api');
        }

        return Cache::remember('heatmap:lian-boundaries-api', now()->addHours(12), function (): array {
            try {
                $geojson = Http::timeout(12)
                    ->retry(2, 300)
                    ->acceptJson()
                    ->get(self::GEORISK_QUERY_URL, [
                        'f' => 'geojson',
                        'where' => "UPPER(prov_name)='BATANGAS' AND UPPER(city_name)='LIAN'",
                        'outFields' => 'brgy_name,city_name,prov_name,brgy_code,psgc_10d',
                        'returnGeometry' => 'true',
                        'outSR' => '4326',
                    ])
                    ->throw()
                    ->json();

                if (is_array($geojson) && ! empty($geojson['features'])) {
                    return [$geojson, 'GeoRisk ArcGIS PSA Barangay Boundary API'];
                }
            } catch (\Throwable) {
                // Fall through to the bundled boundary file.
            }

            return [$this->localBoundaryGeojson(), 'Bundled iClimate fallback GeoJSON'];
        });
    }

    private function localBoundaryGeojson(): array
    {
        $path = public_path('geojson/lian-barangays.geojson');

        if (! is_file($path)) {
            return ['type' => 'FeatureCollection', 'features' => []];
        }

        $contents = file_get_contents($path);
        $geojson = json_decode($contents ?: '', true);

        return is_array($geojson) ? $geojson : ['type' => 'FeatureCollection', 'features' => []];
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
