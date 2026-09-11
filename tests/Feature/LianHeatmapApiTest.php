<?php

namespace Tests\Feature;

use App\Models\ClimateRecord;
use App\Models\HeatmapArea;
use App\Models\PlantingAdvisory;
use App\Models\RiceProduction;
use App\Services\Weather\LianBarangayWeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LianHeatmapApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('heatmap.refresh_seconds', 5);
        config()->set('heatmap.weather_cache_minutes', 10);
    }

    public function test_api_returns_calculated_sources_refresh_metadata_and_single_feature_per_barangay(): void
    {
        Http::fake([
            'portal.georisk.gov.ph/*' => Http::response($this->geojsonWithDuplicateBarangay(), 200),
            'api.open-meteo.com/*' => Http::response($this->openMeteoPayload(), 200),
            'wis.pagasa.dost.gov.ph/*' => Http::response($this->pagasaWisPayload(), 200),
        ]);

        HeatmapArea::factory()->create(['barangay' => 'Bagong Pook']);
        HeatmapArea::factory()->create(['barangay' => 'Bagong Pook']);
        ClimateRecord::factory()->create([
            'record_date' => now()->toDateString(),
            'rainfall' => 130,
            'temperature' => 33,
        ]);
        RiceProduction::factory()->create([
            'barangay' => 'Bagong Pook',
            'yield_per_hectare' => 2.7,
            'irrigation_type' => 'Rainfed',
            'year' => 2026,
        ]);
        PlantingAdvisory::factory()->create([
            'title' => 'PAGASA heavy rainfall advisory',
            'severity' => PlantingAdvisory::SEVERITY_HIGH,
            'source' => PlantingAdvisory::SOURCE_PAGASA_ONLINE_ADVISORY,
            'status' => PlantingAdvisory::STATUS_PUBLISHED,
            'target_barangay' => null,
            'valid_from' => now()->subHour(),
            'valid_until' => now()->addDay(),
        ]);

        $response = $this->getJson('/api/heatmaps/lian-barangays');

        $response->assertOk()
            ->assertJsonPath('refresh_seconds', 5)
            ->assertJsonPath('weather_cache_minutes', 10)
            ->assertJsonPath('pagasa_wis_cache_seconds', 60)
            ->assertJsonPath('data_sources.boundary', 'GeoRisk ArcGIS PSA Barangay Boundary API')
            ->assertJsonPath('features.0.properties.heatmap.status', 'calculated')
            ->assertJsonPath('features.0.properties.heatmap.data_sections.official_observed.available', true)
            ->assertJsonPath('features.0.properties.heatmap.data_sections.forecast.available', true)
            ->assertJsonPath('features.0.properties.heatmap.data_sections.observed.available', true)
            ->assertJsonPath('features.0.properties.heatmap.data_sections.pagasa.available', true);

        $this->assertCount(1, $response->json('features'));
        $this->assertSame('Bagong Pook', $response->json('features.0.properties.heatmap.barangay'));
        $this->assertSame('boundary centroid', $response->json('features.0.properties.heatmap.coordinate_source'));
        $this->assertSame(14.0188, $response->json('features.0.properties.heatmap.latitude'));
        $this->assertSame(120.6408, $response->json('features.0.properties.heatmap.longitude'));
        $this->assertNotNull($response->json('features.0.properties.heatmap.risk_score'));
    }

    public function test_api_shows_insufficient_data_when_no_verified_inputs_exist(): void
    {
        Http::fake([
            'portal.georisk.gov.ph/*' => Http::response($this->singleBarangayGeojson(), 200),
            'api.open-meteo.com/*' => Http::response([], 500),
            'wis.pagasa.dost.gov.ph/*' => Http::response([], 500),
        ]);

        HeatmapArea::factory()->create(['barangay' => 'Bagong Pook']);

        $response = $this->getJson('/api/heatmaps/lian-barangays?refresh_weather=1');

        $response->assertOk()
            ->assertJsonPath('features.0.properties.heatmap.status', 'insufficient_data')
            ->assertJsonPath('features.0.properties.heatmap.display_level', 'Insufficient data')
            ->assertJsonPath('features.0.properties.heatmap.risk_score', null);
    }

    public function test_weather_service_retains_cached_forecast_when_refresh_fails(): void
    {
        $service = app(LianBarangayWeatherService::class);

        Http::fake([
            'api.open-meteo.com/*' => Http::sequence()
                ->push($this->openMeteoPayload(rainfall: 12), 200)
                ->push([], 500)
                ->push([], 500)
                ->push([], 500),
        ]);

        $fresh = $service->forBarangay('Bagong Pook', 14.0182, 120.6408, refresh: true);

        $stale = $service->forBarangay('Bagong Pook', 14.0182, 120.6408, refresh: true);

        $this->assertSame('fresh', $fresh['status']);
        $this->assertSame('stale', $stale['status']);
        $this->assertTrue($stale['stale']);
        $this->assertSame($fresh['rainfall_24h_mm'], $stale['rainfall_24h_mm']);
    }

    private function singleBarangayGeojson(): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => [
                $this->feature('Bagong Pook'),
            ],
        ];
    }

    private function geojsonWithDuplicateBarangay(): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => [
                $this->feature('Bagong Pook'),
                $this->feature('Bagong Pook', 0.004),
            ],
        ];
    }

    private function feature(string $barangay, float $offset = 0): array
    {
        return [
            'type' => 'Feature',
            'properties' => ['brgy_name' => $barangay, 'city_name' => 'Lian', 'prov_name' => 'Batangas'],
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [[
                    [120.6400 + $offset, 14.0180 + $offset],
                    [120.6420 + $offset, 14.0180 + $offset],
                    [120.6420 + $offset, 14.0200 + $offset],
                    [120.6400 + $offset, 14.0200 + $offset],
                    [120.6400 + $offset, 14.0180 + $offset],
                ]],
            ],
        ];
    }

    private function openMeteoPayload(float $rainfall = 90): array
    {
        return [
            'current' => [
                'time' => '2026-09-11T10:00',
                'temperature_2m' => 34,
                'relative_humidity_2m' => 86,
                'precipitation' => 4,
                'rain' => 4,
                'wind_speed_10m' => 44,
            ],
            'hourly' => [
                'precipitation' => array_fill(0, 24, $rainfall / 24),
                'precipitation_probability' => array_fill(0, 24, 80),
            ],
            'daily' => [
                'precipitation_sum' => array_fill(0, 7, 30),
                'temperature_2m_max' => array_fill(0, 7, 34),
                'temperature_2m_min' => array_fill(0, 7, 25),
                'precipitation_probability_max' => array_fill(0, 7, 80),
            ],
        ];
    }

    private function pagasaWisPayload(): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => [
                $this->pagasaObservationFeature('rainfall', 36),
                $this->pagasaObservationFeature('air_temperature', 33),
                $this->pagasaObservationFeature('relative_humidity', 82),
                $this->pagasaObservationFeature('wind_speed', 38),
            ],
        ];
    }

    private function pagasaObservationFeature(string $name, float $value): array
    {
        return [
            'type' => 'Feature',
            'geometry' => ['type' => 'Point', 'coordinates' => [121.1667, 13.7833, 10]],
            'properties' => [
                'wigos_station_identifier' => '0-20000-0-98431',
                'station_name' => 'Ambulong',
                'name' => $name,
                'value' => $value,
                'phenomenonTime' => now()->toIso8601String(),
            ],
        ];
    }
}
