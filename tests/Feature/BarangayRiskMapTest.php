<?php

namespace Tests\Feature;

use App\Models\ClimateRecord;
use App\Models\FarmBoundary;
use App\Models\FarmerProfile;
use App\Models\HeatmapArea;
use App\Models\RiceProduction;
use App\Models\User;
use App\Services\Climate\AmbulongHistoricalClimateDataset;
use App\Services\DecisionSupportService;
use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use App\Services\Risk\BarangayRiskMapService;
use App\Support\LianBarangays;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BarangayRiskMapTest extends TestCase
{
    use RefreshDatabase;

    private function fakeFreshWeather(float $rainfall24h, float $temperature, float $humidity = 70, float $wind = 10)
    {
        return Http::response([
            'current' => [
                'time' => now()->toIso8601String(),
                'temperature_2m' => $temperature,
                'relative_humidity_2m' => $humidity,
                'precipitation' => $rainfall24h / 24,
                'wind_speed_10m' => $wind,
            ],
            'hourly' => [
                'precipitation' => array_fill(0, 24, $rainfall24h / 24),
                'precipitation_probability' => array_fill(0, 24, 50),
            ],
            'daily' => [
                'precipitation_sum' => [$rainfall24h],
                'precipitation_probability_max' => [50],
            ],
        ], 200);
    }

    public function test_all_19_barangays_get_a_classification_or_no_data(): void
    {
        Http::fake(['api.open-meteo.com/*' => fn () => $this->fakeFreshWeather(200, 28)]);

        $service = app(BarangayRiskMapService::class);
        $map = $service->riskMap(refresh: true);

        $this->assertCount(19, $map['barangays']);
        $names = collect($map['barangays'])->pluck('barangay')->sort()->values()->all();
        $this->assertSame(LianBarangays::all(), collect($names)->sortBy(fn ($n) => array_search($n, LianBarangays::all()))->values()->all());

        foreach ($map['barangays'] as $entry) {
            $this->assertContains($entry['risk_level'], ['Low', 'Moderate', 'High', 'Severe', 'No Data']);
        }
    }

    /**
     * Seeds 6 months of ClimateRecord history so MonthlyWeatherRandomForest
     * reports ready => true (needs >= 4 monthly records and >= 3 usable
     * training samples).
     */
    private function seedReadyMonthlyClimateHistory(): void
    {
        foreach (range(1, 6) as $i) {
            ClimateRecord::factory()->create([
                'record_date' => now()->subMonths(7 - $i)->startOfMonth()->toDateString(),
                'rainfall' => 150 + ($i * 10),
                'temperature' => 27 + $i,
                'humidity' => 70 + $i,
                'wind_speed' => 8 + $i,
                'season' => 'Wet',
                'source' => 'Test PAGASA',
            ]);
        }
    }

    public function test_complete_real_weather_and_yield_scores_using_monthly_rainfall_not_24h_forecast(): void
    {
        // Corrected pipeline: AgriculturalRiskScorer's rainfall input is the
        // MUNICIPALITY-WIDE MONTHLY figure from MonthlyWeatherRandomForest,
        // never Open-Meteo's per-barangay 24h/7d forecast figure. Open-Meteo
        // returns 115mm/24h for Matabungkay here (an old fixture value that
        // used to feed the scorer directly and produce Severe); the
        // corrected pipeline must score using the monthly figure instead.
        Http::fake([
            'api.open-meteo.com/*' => function ($request) {
                $query = [];
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $lat = (float) ($query['latitude'] ?? 0);
                // Matabungkay coordinates from LianBarangays.
                if (abs($lat - 13.9593) < 0.01) {
                    return $this->fakeFreshWeather(115, 33, 95, 25);
                }

                return $this->fakeFreshWeather(200, 28);
            },
        ]);

        $this->seedReadyMonthlyClimateHistory();

        RiceProduction::factory()->create([
            'barangay' => 'Matabungkay',
            'yield_per_hectare' => 2.5,
            'year' => 2026,
            'production_month' => '2026-06',
            'season' => 'Wet',
            'irrigation_type' => 'Rainfed',
            'area_hectares' => 1,
            'total_production' => 2.5,
        ]);

        FarmerProfile::factory()->create(['barangay' => 'Matabungkay', 'farm_type' => 'Rainfed']);

        // Resolve the SAME monthly rainfall figure the fix uses, then
        // compute the expected evaluation independently via
        // DecisionSupportService, so this test doesn't hardcode a magic
        // score/level that would drift if the forest's algorithm changes.
        $forest = app(MonthlyWeatherRandomForest::class);
        $prediction = $forest->predict(now()->startOfMonth()->toImmutable());
        $this->assertTrue($prediction['ready'] ?? false, 'Forest should be ready with 6 seeded monthly climate records.');
        $monthlyRainfall = (float) $prediction['predictions']['rainfall'];

        // The value actually fed to the scorer must never equal Open-Meteo's
        // 24h/7d forecast figures for this barangay.
        $this->assertNotEquals(115.0, $monthlyRainfall);
        $this->assertNotEquals(115.0 / 24, $monthlyRainfall);

        $expected = app(DecisionSupportService::class)->evaluate([
            'rainfall' => $monthlyRainfall,
            'wind_speed' => 25,
            'humidity' => 95,
            'temperature' => 33,
            'predicted_yield' => 2.5,
            'season' => in_array((int) now()->month, [5, 6, 7, 8, 9, 10], true) ? 'Wet' : 'Dry',
            'farm_type' => 'Rainfed',
            'barangay' => 'Matabungkay',
        ]);

        $service = app(BarangayRiskMapService::class);
        $map = $service->riskMap(refresh: true);

        $entry = collect($map['barangays'])->firstWhere('barangay', 'Matabungkay');
        $this->assertNotNull($entry);
        $this->assertSame($expected['risk']['level'], $entry['risk_level']);
        $this->assertSame($expected['risk']['score'], $entry['risk_score']);
        $this->assertSame(round($monthlyRainfall, 2), $entry['monthly_rainfall']['value_mm']);
        // Display-only Open-Meteo figures are still present, and different
        // from the monthly figure used for scoring.
        $this->assertSame(115.0, $entry['weather']['rainfall_24h_mm']);

        // Also persisted into HeatmapArea for chatbot/fallback reuse.
        $row = HeatmapArea::query()->where('barangay', 'Matabungkay')->first();
        $this->assertNotNull($row);
        $this->assertSame($expected['risk']['level'], $row->risk_level);
    }

    /**
     * Regression coverage for the 2026-09 wind-unit audit: the heatmap path
     * must keep feeding DecisionSupportService's `wind_speed > 20` threshold
     * with Open-Meteo's confirmed-km/h wind_speed_10m figure — never the
     * historical CSV / MonthlyWeatherRandomForest wind output (whose unit is
     * unresolved). This proves that separation is undisturbed by this fix.
     */
    public function test_wind_advisory_threshold_receives_open_meteos_kmh_value_not_the_forests(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => function ($request) {
                $query = [];
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $lat = (float) ($query['latitude'] ?? 0);
                if (abs($lat - 13.9593) < 0.01) {
                    // Above the >20 threshold: should trigger the "Strong wind" stress factor.
                    return $this->fakeFreshWeather(200, 28, 70, 35);
                }

                return $this->fakeFreshWeather(200, 28, 70, 5);
            },
        ]);

        // Seed monthly climate history whose wind figure sits WELL BELOW the
        // >20 threshold (on the CSV's own ~1-3 scale) — if the forest's wind
        // ever leaked into this path instead of Open-Meteo's, the threshold
        // would never fire, and this assertion would catch it.
        foreach (range(1, 6) as $i) {
            ClimateRecord::factory()->create([
                'record_date' => now()->subMonths(7 - $i)->startOfMonth()->toDateString(),
                'rainfall' => 150 + ($i * 10),
                'temperature' => 27 + $i,
                'humidity' => 70 + $i,
                'wind_speed' => 1.5,
                'season' => 'Wet',
                'source' => 'Test PAGASA',
            ]);
        }

        $service = app(BarangayRiskMapService::class);
        $map = $service->riskMap(refresh: true);

        $windy = collect($map['barangays'])->firstWhere('barangay', 'Matabungkay');
        $calm = collect($map['barangays'])->firstWhere('barangay', 'Tuy'); // any other seeded barangay

        $this->assertNotNull($windy);
        $this->assertSame(35.0, $windy['weather']['wind_speed_kmh']);
        $this->assertContains('Strong wind advisory.', $windy['advisory']['warnings'] ?? []);

        if ($calm) {
            $this->assertSame(5.0, $calm['weather']['wind_speed_kmh']);
            $this->assertNotContains('Strong wind advisory.', $calm['advisory']['warnings'] ?? []);
        }
    }

    public function test_monthly_rainfall_unavailable_yields_no_data_even_with_fresh_weather(): void
    {
        // MonthlyWeatherRandomForest now trains from the Ambulong historical
        // CSV dataset, not ClimateRecord, so readiness must be driven by
        // dataset availability, independent of ClimateRecord's state. Bind a
        // dataset instance pointing at a nonexistent file to simulate the
        // CSV being unavailable. Even though Open-Meteo weather is fresh, the
        // barangay must be No Data — never scored with a fabricated/
        // placeholder rainfall.
        Http::fake(['api.open-meteo.com/*' => fn () => $this->fakeFreshWeather(200, 28)]);

        $this->app->instance(
            AmbulongHistoricalClimateDataset::class,
            new AmbulongHistoricalClimateDataset(database_path('data/does-not-exist.csv'))
        );

        $forest = app(MonthlyWeatherRandomForest::class);
        $prediction = $forest->predict(now()->startOfMonth()->toImmutable());
        $this->assertFalse($prediction['ready'] ?? true, 'Precondition: forest must not be ready when the historical dataset is unavailable.');

        // Snapshot whatever pre-existing HeatmapArea row exists for this
        // barangay (seeded by an older migration) before the service runs.
        $before = HeatmapArea::query()->where('barangay', 'Matabungkay')->first()?->only(['risk_level', 'risk_score', 'description']);

        $service = app(BarangayRiskMapService::class);
        $map = $service->riskMap(refresh: true);

        $entry = collect($map['barangays'])->firstWhere('barangay', 'Matabungkay');
        $this->assertNotNull($entry);
        $this->assertSame('No Data', $entry['risk_level']);
        $this->assertNull($entry['risk_score']);
        // Fresh weather is still surfaced for context/display even though
        // the barangay is No Data overall.
        $this->assertSame('fresh', $entry['weather']['status']);
        $this->assertNotNull($entry['weather']['temperature_c']);

        // A "No Data" barangay must never be written into HeatmapArea as a
        // fabricated score — any pre-existing row is left untouched.
        $after = HeatmapArea::query()->where('barangay', 'Matabungkay')->first()?->only(['risk_level', 'risk_score', 'description']);
        $this->assertSame($before, $after);
    }

    public function test_unavailable_weather_and_no_rice_production_yields_no_data_not_low(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response([], 500)]);

        // Snapshot whatever pre-existing HeatmapArea row exists for this
        // barangay (seeded by an older migration) before the service runs.
        $before = HeatmapArea::query()->where('barangay', 'San Diego')->first()?->only(['risk_level', 'risk_score', 'description']);

        $service = app(BarangayRiskMapService::class);
        $map = $service->riskMap(refresh: true);

        $entry = collect($map['barangays'])->firstWhere('barangay', 'San Diego');
        $this->assertNotNull($entry);
        $this->assertSame('No Data', $entry['risk_level']);
        $this->assertNull($entry['risk_score']);
        $this->assertNotSame('Low', $entry['risk_level']);

        // A "No Data" barangay must never be written into HeatmapArea as a
        // fabricated score — the row (if any pre-existed) is left untouched.
        $after = HeatmapArea::query()->where('barangay', 'San Diego')->first()?->only(['risk_level', 'risk_score', 'description']);
        $this->assertSame($before, $after);
    }

    public function test_polygon_and_multipolygon_barangays_both_join_via_psgc(): void
    {
        Http::fake(['api.open-meteo.com/*' => fn () => $this->fakeFreshWeather(200, 28)]);

        $service = app(BarangayRiskMapService::class);
        $map = $service->riskMap(refresh: true);

        // Bagong Pook is a Polygon feature, Lumaniag is a MultiPolygon feature
        // in the bundled GeoJSON — both must resolve a psgc_10d.
        $polygon = collect($map['barangays'])->firstWhere('barangay', 'Bagong Pook');
        $multiPolygon = collect($map['barangays'])->firstWhere('barangay', 'Lumaniag');

        $this->assertSame('0401013002', $polygon['psgc_10d']);
        $this->assertSame('0401013012', $multiPolygon['psgc_10d']);
    }

    public function test_unmatchable_barangay_name_is_excluded_not_misassigned(): void
    {
        $this->assertNull(LianBarangays::canonicalName('Totally Unknown Place'));
        $this->assertNull(LianBarangays::canonicalName(''));
        $this->assertSame('Bagong Pook', LianBarangays::canonicalName('bagong  pook'));
    }

    public function test_api_endpoint_returns_graceful_response_on_service_failure(): void
    {
        $this->mock(BarangayRiskMapService::class, function ($mock) {
            $mock->shouldReceive('riskMap')->andThrow(new \RuntimeException('boom'));
        });

        $response = $this->getJson('/api/lian/risk-map');

        $response->assertStatus(200);
        $response->assertJson(['status' => 'unavailable']);
        $response->assertJsonStructure(['status', 'message', 'legend', 'barangays']);
    }

    public function test_api_endpoint_returns_lean_list_by_default(): void
    {
        Http::fake(['api.open-meteo.com/*' => fn () => $this->fakeFreshWeather(200, 28)]);

        $response = $this->getJson('/api/lian/risk-map');

        $response->assertStatus(200)->assertJson(['status' => 'ok']);
        $response->assertJsonCount(19, 'barangays');
        $first = $response->json('barangays.0');
        $this->assertArrayNotHasKey('risk_breakdown', $first);
        $this->assertArrayNotHasKey('advisory', $first);
    }

    public function test_old_shaped_stale_cache_is_never_returned_after_key_bump(): void
    {
        Cache::flush();

        // Simulate an array computed under the OLD (pre-fix) logic — one that
        // fed Open-Meteo's 24h rainfall directly into the scorer — sitting
        // under the OLD cache key with plenty of TTL left.
        $staleOldKeyPayload = [
            'generated_at' => now()->subMinutes(5)->toIso8601String(),
            'source_note' => 'stale old-pipeline payload',
            'barangays' => [
                ['barangay' => 'Matabungkay', 'risk_level' => 'Severe', 'risk_score' => 0.99],
            ],
        ];
        Cache::put('geo:lian-risk-map', $staleOldKeyPayload, now()->addMinutes(15));

        // The fix bumped BarangayRiskMapService::CACHE_KEY to a new value, so
        // that old-keyed array can never be read by the service again,
        // regardless of its remaining TTL.
        $this->assertNotSame('geo:lian-risk-map', BarangayRiskMapService::CACHE_KEY);
        $this->assertTrue(Cache::has('geo:lian-risk-map'));

        Http::fake(['api.open-meteo.com/*' => fn () => $this->fakeFreshWeather(200, 28)]);

        $service = app(BarangayRiskMapService::class);
        $map = $service->riskMap(); // no explicit refresh — proves the NEW key is simply never populated with the old array.

        $this->assertNotSame($staleOldKeyPayload['source_note'], $map['source_note']);
        $this->assertCount(19, $map['barangays']);
        $this->assertTrue(Cache::has(BarangayRiskMapService::CACHE_KEY));
    }

    public function test_farmer_without_farm_boundary_sees_only_barangay(): void
    {
        Cache::flush();
        Http::fake(['api.open-meteo.com/*' => fn () => $this->fakeFreshWeather(200, 28)]);

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);
        FarmerProfile::factory()->create(['user_id' => $user->id, 'barangay' => 'Bagong Pook']);

        $response = $this->actingAs($user)->get(route('farmer.dashboard'));

        $response->assertOk();
        $response->assertSee('YOUR BARANGAY: Bagong Pook');
        $response->assertDontSee('YOUR FARM boundary is recorded');
    }

    public function test_farmer_with_farm_boundary_sees_your_farm(): void
    {
        Cache::flush();
        Http::fake(['api.open-meteo.com/*' => fn () => $this->fakeFreshWeather(200, 28)]);

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);
        $profile = FarmerProfile::factory()->create(['user_id' => $user->id, 'barangay' => 'Bagong Pook']);
        FarmBoundary::factory()->create([
            'farmer_profile_id' => $profile->id,
            'boundary_coordinates' => [[14.02, 120.64], [14.021, 120.641], [14.019, 120.642]],
            'calculated_area_hectares' => 1.5,
        ]);

        $response = $this->actingAs($user)->get(route('farmer.dashboard'));

        $response->assertOk();
        $response->assertSee('YOUR BARANGAY: Bagong Pook');
        $response->assertSee('YOUR FARM boundary is recorded');
    }

    public function test_mao_dashboard_loads_with_risk_map_section(): void
    {
        Cache::flush();
        Http::fake(['api.open-meteo.com/*' => fn () => $this->fakeFreshWeather(200, 28)]);

        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $response = $this->actingAs($mao)->get(route('mao.dashboard'));

        $response->assertOk();
        $response->assertSee('id="barangayRiskMap"', false);
        $response->assertSee('Barangay Agricultural Risk Map');
    }
}
