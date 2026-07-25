<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LianBoundaryController extends Controller
{
    private const CACHE_KEY = 'heatmap:lian-boundaries-api';

    private const GEORISK_QUERY_URL = 'https://portal.georisk.gov.ph/arcgis/rest/services/PSA/Barangay/MapServer/4/query';

    public function __invoke(Request $request): JsonResponse
    {
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

        $geojson['source'] = $source;
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
}
