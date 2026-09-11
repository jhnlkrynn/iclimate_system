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
        [$geojson, $source] = $boundaries->geojson($request->boolean('refresh'));

        $geojson['source'] = $source;
        $geojson['source_credit'] = $source === 'GeoRisk ArcGIS PSA Barangay Boundary API'
            ? 'Barangay boundary data retrieved online from the GeoRisk ArcGIS PSA Barangay Boundary API.'
            : 'Barangay boundary data loaded from the bundled iClimate fallback GeoJSON file.';
        $geojson['source_url'] = $boundaries->sourceUrl($source);
        $geojson['generated_at'] = now()->toDateTimeString();

        return response()->json($geojson);
    }
}
