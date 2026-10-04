<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Support\LianBarangays;

class LianBoundaryController extends Controller
{
    private const CACHE_KEY = 'geo:lian-boundaries-api';

    private const LEGACY_CACHE_KEY = 'heatmap:lian-boundaries-api';

    private const GEORISK_QUERY_URL = 'https://portal.georisk.gov.ph/arcgis/rest/services/PSA/Barangay/MapServer/4/query';

    public function __invoke(Request $request): JsonResponse
    {
        // One-time cleanup: a stale value under the old pre-rename cache key
        // must never be served again now that the key has changed.
        if (Cache::has(self::LEGACY_CACHE_KEY)) {
            Cache::forget(self::LEGACY_CACHE_KEY);
        }

        if ($request->boolean('refresh')) {
            Cache::forget(self::CACHE_KEY);
        }

        [$geojson, $source] = Cache::remember(self::CACHE_KEY, now()->addHours(12), function (): array {
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

        // Normalize and validate the source so every frontend receives one
        // feature for each of Lian's 19 canonical barangays. An incomplete or
        // duplicated provider response must never alter the mapped total.
        $normalized = $this->normalizeBoundaryGeojson($geojson);
        if ($normalized === null) {
            $geojson = $this->localBoundaryGeojson();
            $normalized = $this->normalizeBoundaryGeojson($geojson) ?? [];
            $source = 'Bundled iClimate fallback GeoJSON';
        }
        $geojson['features'] = $normalized;

        $geojson['source'] = $source;
        $geojson['source_credit'] = $source === 'GeoRisk ArcGIS PSA Barangay Boundary API'
            ? 'Barangay boundary data retrieved online from the GeoRisk ArcGIS PSA Barangay Boundary API.'
            : 'Barangay boundary data loaded from the bundled iClimate fallback GeoJSON file.';
        $geojson['source_url'] = $source === 'GeoRisk ArcGIS PSA Barangay Boundary API'
            ? self::GEORISK_QUERY_URL
            : null;
        $geojson['generated_at'] = now()->toDateTimeString();

        return response()->json($geojson);
    }

    private function localBoundaryGeojson(): array
    {
        $path = public_path('geojson/lian-barangays.geojson');

        if (! is_file($path)) {
            return ['type' => 'FeatureCollection', 'features' => []];
        }

        $geojson = json_decode(file_get_contents($path) ?: '', true);

        return is_array($geojson) ? $geojson : ['type' => 'FeatureCollection', 'features' => []];
    }

    /** Return a complete, unique, canonical 19-feature collection. */
    private function normalizeBoundaryGeojson(array $geojson): ?array
    {
        $features = [];
        $seen = [];

        foreach ($geojson['features'] ?? [] as $feature) {
            if (! is_array($feature)) {
                continue;
            }

            $properties = $feature['properties'] ?? [];
            $canonical = LianBarangays::canonicalName($properties['brgy_name'] ?? null);
            if ($canonical === null || isset($seen[$canonical])) {
                continue;
            }

            $properties['brgy_name'] = $canonical;
            $feature['properties'] = $properties;
            $features[] = $feature;
            $seen[$canonical] = true;
        }

        if (count($features) !== count(LianBarangays::all())
            || array_diff(LianBarangays::all(), array_keys($seen)) !== []) {
            return null;
        }

        return $features;
    }
}
