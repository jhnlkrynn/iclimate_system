<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Geo\LianBarangayBoundaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LianBoundaryController extends Controller
{
    public function __invoke(Request $request, LianBarangayBoundaryService $boundaries): JsonResponse
    {
<<<<<<< Updated upstream
        [$geojson, $source] = $boundaries->geojson($request->boolean('refresh'));
=======
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

            return [['type' => 'FeatureCollection', 'features' => []], 'No verified boundary source available'];
        });
>>>>>>> Stashed changes

        $geojson['source'] = $source;
        $geojson['source_credit'] = $source === 'GeoRisk ArcGIS PSA Barangay Boundary API'
            ? 'Barangay boundary data retrieved online from the GeoRisk ArcGIS PSA Barangay Boundary API.'
<<<<<<< Updated upstream
            : 'Barangay boundary data loaded from the bundled iClimate fallback GeoJSON file.';
        $geojson['source_url'] = $boundaries->sourceUrl($source);
=======
            : 'No verified online barangay boundary source is currently available.';
        $geojson['source_url'] = $source === 'GeoRisk ArcGIS PSA Barangay Boundary API'
            ? self::GEORISK_QUERY_URL
            : null;
>>>>>>> Stashed changes
        $geojson['generated_at'] = now()->toDateTimeString();

        return response()->json($geojson);
    }
}
