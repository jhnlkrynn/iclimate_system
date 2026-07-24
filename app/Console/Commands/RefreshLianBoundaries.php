<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class RefreshLianBoundaries extends Command
{
    private const CACHE_KEY = 'heatmap:lian-boundaries-api';

    private const GEORISK_QUERY_URL = 'https://portal.georisk.gov.ph/arcgis/rest/services/PSA/Barangay/MapServer/4/query';

    protected $signature = 'iclimate:refresh-lian-boundaries';

    protected $description = 'Refresh official GeoRisk PSA barangay boundary GeoJSON for Lian, Batangas.';

    public function handle(): int
    {
        try {
            $response = Http::timeout(20)
                ->retry(2, 300)
                ->acceptJson()
                ->get(self::GEORISK_QUERY_URL, [
                    'f' => 'geojson',
                    'where' => "UPPER(prov_name)='BATANGAS' AND UPPER(city_name)='LIAN'",
                    'outFields' => 'brgy_name,city_name,prov_name,brgy_code,psgc_10d',
                    'returnGeometry' => 'true',
                    'outSR' => '4326',
                ])
                ->throw();

            $geojson = $response->json();
        } catch (\Throwable $exception) {
            $this->warn('GeoRisk boundary refresh failed: '.$exception->getMessage());
            $this->warn('The heatmap API will continue using the bundled fallback GeoJSON.');

            return self::FAILURE;
        }

        if (! is_array($geojson) || empty($geojson['features'])) {
            $this->error('GeoRisk returned no Lian barangay boundary features.');

            return self::FAILURE;
        }

        Cache::put(self::CACHE_KEY, [$geojson, 'GeoRisk ArcGIS PSA Barangay Boundary API'], now()->addDay());

        $this->info('Cached '.count($geojson['features']).' official Lian barangay boundary feature(s).');

        return self::SUCCESS;
    }
}
