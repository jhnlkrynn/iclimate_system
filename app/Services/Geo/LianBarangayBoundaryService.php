<?php

namespace App\Services\Geo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LianBarangayBoundaryService
{
    private const CACHE_KEY = 'heatmap:lian-boundaries-api';

    private const GEORISK_QUERY_URL = 'https://portal.georisk.gov.ph/arcgis/rest/services/PSA/Barangay/MapServer/4/query';

    public function geojson(bool $refresh = false): array
    {
        if ($refresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, now()->addHours(12), function (): array {
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
                // Fall through to bundled boundaries when the online source is unavailable.
            }

            return [$this->localGeojson(), 'Bundled iClimate fallback GeoJSON'];
        });
    }

    public function centroids(bool $refresh = false): array
    {
        [$geojson] = $this->geojson($refresh);

        $centroids = [];

        collect($geojson['features'] ?? [])
            ->each(function (array $feature) use (&$centroids): void {
                $name = $this->featureBarangayName($feature);
                $centroid = $this->centroid($feature['geometry'] ?? null);

                if ($name !== '' && $centroid && ! isset($centroids[$name])) {
                    $centroids[$name] = $centroid;
                }
            });

        return $centroids;
    }

    public function featureBarangayName(array $feature): string
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

    public function sourceUrl(string $source): ?string
    {
        return $source === 'GeoRisk ArcGIS PSA Barangay Boundary API' ? self::GEORISK_QUERY_URL : null;
    }

    private function localGeojson(): array
    {
        $path = public_path('geojson/lian-barangays.geojson');

        if (! is_file($path)) {
            return ['type' => 'FeatureCollection', 'features' => []];
        }

        $geojson = json_decode(file_get_contents($path) ?: '', true);

        return is_array($geojson) ? $geojson : ['type' => 'FeatureCollection', 'features' => []];
    }

    private function centroid(?array $geometry): ?array
    {
        $rings = match ($geometry['type'] ?? null) {
            'Polygon' => $geometry['coordinates'] ?? [],
            'MultiPolygon' => collect($geometry['coordinates'] ?? [])->flatMap(fn ($polygon) => $polygon)->all(),
            default => [],
        };

        $points = collect($rings)->flatten(1)->filter(fn ($point) => is_array($point) && count($point) >= 2)->values();

        if ($points->isEmpty()) {
            return null;
        }

        $lng = $points->avg(fn ($point) => (float) $point[0]);
        $lat = $points->avg(fn ($point) => (float) $point[1]);

        return [round($lat, 7), round($lng, 7)];
    }
}
