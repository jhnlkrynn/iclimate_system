<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Support\LianBarangays;

class LianBoundaryController extends Controller
{
    private const CACHE_KEY = 'geo:lian-boundaries-api';

    private const LEGACY_CACHE_KEY = 'heatmap:lian-boundaries-api';

    private const GEORISK_QUERY_URL = 'https://portal.georisk.gov.ph/arcgis/rest/services/PSA/Barangay/MapServer/4/query';

    /** The finished 19-feature response, cached so the GeoJSON is not re-normalized on every request. */
    public const RESPONSE_CACHE_KEY = 'geo:lian-boundaries-api:response:v1';

    /**
     * Never calls GeoRisk: a request serves what iclimate:refresh-lian-boundaries stored (daily), or the bundled
     * file. Browsers keep it for a day and revalidate with an ETag, so after the first visit the map's largest
     * file usually costs a 304.
     */
    public function __invoke(Request $request): Response
    {
        // One-time cleanup: a stale value under the old pre-rename cache key
        // must never be served again now that the key has changed.
        if (Cache::has(self::LEGACY_CACHE_KEY)) {
            Cache::forget(self::LEGACY_CACHE_KEY);
        }

        if ($request->boolean('refresh')) {
            Cache::forget(self::RESPONSE_CACHE_KEY);
        }

        $body = Cache::remember(self::RESPONSE_CACHE_KEY, now()->addDay(), fn (): string => json_encode($this->buildGeojson(), JSON_UNESCAPED_SLASHES));
        $etag = '"'.sha1($body).'"';
        $headers = ['ETag' => $etag, 'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800'];

        if (in_array($etag, $request->getETags(), true)) {
            return response('', 304, $headers);
        }

        return response($body, 200, $headers + ['Content-Type' => 'application/json']);
    }

    /** @return array<string, mixed> */
    private function buildGeojson(): array
    {
        $stored = Cache::get(self::CACHE_KEY);
        [$geojson, $source] = is_array($stored) && count($stored) === 2
            ? $stored
            : [$this->localBoundaryGeojson(), 'Bundled iClimate fallback GeoJSON'];

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

        return $geojson;
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
