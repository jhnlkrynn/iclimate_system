<?php

namespace Tests\Feature;

use App\Models\ClimateRecord;
use App\Models\HeatmapArea;
use App\Models\PlantingAdvisory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparesPredictions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AIHybridOrchestratorTest extends TestCase
{
    use PreparesPredictions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Chat only reads prepared predictions; stand in for the scheduled refresh.
        $this->preparePredictions();

        Cache::flush();
        Config::set('services.groq.enabled', false);
        Config::set('services.weather.provider', 'weatherapi');
        Config::set('services.weatherapi.key', 'test-key');
    }

    public function test_current_weather_question_routes_to_live_weather_service(): void
    {
        Http::fake([
            '*/forecast.json*' => Http::response($this->weatherApiPayload(), 200),
        ]);

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => "What's the temperature right now?",
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.intent', 'current_weather')
            ->assertJsonPath('chat.source_type', 'Live Weather')
            ->assertJsonPath('chat.prediction_result.tool', 'current_weather')
            ->assertJsonPath('chat.prediction_result.data.temperature_c', 29.4);

        Http::assertSent(fn ($request) => str_contains((string) $request->url(), 'forecast.json'));
        Http::assertNotSent(fn ($request) => str_contains((string) $request->url(), '127.0.0.1:19999/predict'));
    }

    public function test_next_month_rainfall_question_routes_to_trained_weather_model(): void
    {
        $this->seedClimateRecords();

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'What does the iClimate model predict for next month rainfall?',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.intent', 'weather_prediction')
            ->assertJsonPath('chat.source_type', 'Trained Model')
            ->assertJsonPath('chat.prediction_result.tool', 'weather_prediction');

        $this->assertIsNumeric($response->json('chat.weather_prediction.rainfall'));
        Http::assertNothingSent();
    }

    public function test_missing_rice_yield_area_triggers_clarification_and_follow_up_uses_area(): void
    {
        $this->seedClimateRecords();
        Http::fake([
            '127.0.0.1:19999/predict' => Http::response([
                'rice_yield_prediction' => ['predicted_yield' => 3.87, 'unit' => 'tons/hectare'],
                'confidence_score' => 88,
            ]),
        ]);

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        $first = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'Can you estimate my harvest?',
        ]);

        $first->assertOk()
            ->assertJsonPath('chat.intent', 'rice_yield_prediction')
            ->assertJsonPath('chat.prediction_result.requires_clarification', true)
            ->assertJsonPath('chat.memory.pending_intent', 'rice_yield_prediction');

        $second = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => '2 hectares',
        ]);

        $second->assertOk()
            ->assertJsonPath('chat.prediction_result.input_features.area', 2);

        $this->assertIsNumeric($second->json('chat.rice_yield_prediction.predicted_yield'));
        $this->assertFalse((bool) $second->json('chat.rice_yield_prediction.fallback'));
    }

    public function test_ambiguous_prediction_request_asks_for_clarification_without_model_call(): void
    {
        Http::fake();

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'Can you make a prediction for me?',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.intent', 'unknown_prediction')
            ->assertJsonPath('chat.prediction_result.requires_clarification', true)
            ->assertJsonPath('chat.prediction_result.missing.0', 'prediction_type');

        $this->assertStringContainsString('weather/rainfall prediction or a rice-yield prediction', $response->json('chat.answer'));
        Http::assertNothingSent();
    }

    public function test_bare_number_follow_up_is_used_as_hectares_for_pending_yield_prediction(): void
    {
        $this->seedClimateRecords();
        Http::fake([
            '127.0.0.1:19999/predict' => Http::response([
                'rice_yield_prediction' => ['predicted_yield' => 3.87, 'unit' => 'tons/hectare'],
                'confidence_score' => 88,
            ]),
        ]);

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'Predict my rice yield.',
        ])->assertOk()
            ->assertJsonPath('chat.prediction_result.requires_clarification', true);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => '2.5',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.prediction_result.input_features.area', 2.5)
            ->assertJsonPath('chat.rice_yield_prediction.farm_area_hectares', 2.5);
    }

    public function test_advisory_question_uses_active_advisory_records(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $user = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => 'Malaruhatan']);

        PlantingAdvisory::query()->create([
            'title' => 'Heavy Rainfall Advisory',
            'content' => 'Delay fertilizer application due to heavy rainfall.',
            'type' => 'Climate',
            'advisory_type' => 'climate',
            'summary' => 'Heavy rainfall may affect field work.',
            'severity' => PlantingAdvisory::SEVERITY_HIGH,
            'target_barangay' => 'Malaruhatan',
            'target_scope' => 'barangay',
            'posted_by' => $mao->id,
            'status' => PlantingAdvisory::STATUS_PUBLISHED,
            'published_at' => now(),
            'valid_until' => now()->addDay(),
        ]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'Are there any active advisories?',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.intent', 'weather_advisory')
            ->assertJsonPath('chat.source_type', 'iClimate Advisories')
            ->assertJsonPath('chat.prediction_result.data.0.title', 'Heavy Rainfall Advisory');
    }

    public function test_climate_risk_question_uses_heatmap_risk_records(): void
    {
        // The chatbot now consults BarangayRiskMapService::detailFor() to
        // decide CURRENT vs. STALE vs. NO DATA, which triggers a live
        // Open-Meteo lookup for every barangay on a cache miss — fake it so
        // the test never hits the network.
        Http::fake(['api.open-meteo.com/*' => Http::response([
            'current' => ['time' => now()->toIso8601String(), 'temperature_2m' => 28, 'relative_humidity_2m' => 70, 'wind_speed_10m' => 10],
            'hourly' => ['precipitation' => array_fill(0, 24, 8.3), 'precipitation_probability' => array_fill(0, 24, 40)],
            'daily' => ['precipitation_sum' => [200], 'precipitation_probability_max' => [40]],
        ], 200)]);

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        HeatmapArea::query()->updateOrCreate(['barangay' => 'Malaruhatan'], [
            'latitude' => 14.0119,
            'longitude' => 120.6669,
            'risk_level' => 'High',
            'risk_type' => 'Heat',
            'risk_score' => 0.9,
            'predicted_yield' => 2.8,
            'rainfall_status' => 'Low rainfall',
            'planting_advisory' => 'Delay planting or use drought-tolerant practices.',
            'irrigation_recommendation' => 'Prioritize irrigation support within 7 days.',
            'description' => 'Computed from live barangay weather and last recorded rice yield via AgriculturalRiskScorer.',
        ]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'Is Malaruhatan high risk right now?',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.intent', 'climate_risk')
            ->assertJsonPath('chat.source_type', 'iClimate Risk Records')
            ->assertJsonPath('chat.prediction_result.data.0.risk_level', 'High')
            ->assertJsonPath('chat.prediction_result.data.0.risk_type', 'Heat');
    }

    private function fakeFreshOpenMeteoWeather(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response([
            'current' => ['time' => now()->toIso8601String(), 'temperature_2m' => 28, 'relative_humidity_2m' => 70, 'wind_speed_10m' => 10],
            'hourly' => ['precipitation' => array_fill(0, 24, 8.3), 'precipitation_probability' => array_fill(0, 24, 40)],
            'daily' => ['precipitation_sum' => [200], 'precipitation_probability_max' => [40]],
        ], 200)]);
    }

    public function test_climate_risk_reports_current_for_a_fresh_row_with_available_monthly_rainfall(): void
    {
        $this->fakeFreshOpenMeteoWeather();
        // Seed enough monthly climate history so the live pipeline's monthly
        // rainfall input is available (MonthlyWeatherRandomForest ready)
        // and does not itself report No Data for Malaruhatan.
        $this->seedClimateRecords();

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        // A migration seeds a default row for every one of the 19 Lian
        // barangays, so upsert by barangay (as the real pipeline's persist()
        // does) rather than create() a second competing row.
        $row = HeatmapArea::query()->updateOrCreate(['barangay' => 'Malaruhatan'], [
            'latitude' => 14.0119,
            'longitude' => 120.6669,
            'risk_level' => 'High',
            'risk_type' => 'Heat',
            'risk_score' => 0.9,
            'predicted_yield' => 2.8,
            'rainfall_status' => 'Low rainfall',
            'planting_advisory' => 'Delay planting or use drought-tolerant practices.',
            'irrigation_recommendation' => 'Prioritize irrigation support within 7 days.',
            'description' => 'Computed from live barangay weather and last recorded rice yield via AgriculturalRiskScorer.',
            'description' => 'Computed from live barangay weather and last recorded rice yield via AgriculturalRiskScorer.',
        ]);
        $this->assertTrue($row->fresh()->isCurrentlyComputed());
        $this->assertSame(1, HeatmapArea::query()->where('barangay', 'Malaruhatan')->count());

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'Is Malaruhatan high risk right now?',
        ]);

        $response->assertOk()->assertJsonPath('chat.prediction_result.status', 'current');
        $this->assertStringNotContainsString('earlier date', (string) $response->json('chat.answer'));
    }

    public function test_climate_risk_reports_stale_for_an_old_row(): void
    {
        $this->fakeFreshOpenMeteoWeather();
        $this->seedClimateRecords();

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        $row = HeatmapArea::query()->updateOrCreate(['barangay' => 'Malaruhatan'], [
            'latitude' => 14.0119,
            'longitude' => 120.6669,
            'risk_level' => 'High',
            'risk_type' => 'Heat',
            'risk_score' => 0.9,
            'predicted_yield' => 2.8,
            'rainfall_status' => 'Low rainfall',
            'planting_advisory' => 'Delay planting or use drought-tolerant practices.',
            'irrigation_recommendation' => 'Prioritize irrigation support within 7 days.',
            'description' => 'Computed from live barangay weather and last recorded rice yield via AgriculturalRiskScorer.',
        ]);
        // Bypass the model's saving hook (which stamps risk_computed_at =
        // now()) with a direct query update, simulating a row computed well
        // outside HeatmapArea::CURRENT_WINDOW_HOURS.
        HeatmapArea::query()->where('id', $row->id)->update(['risk_computed_at' => now()->subHours(30)]);
        $this->assertFalse($row->fresh()->isCurrentlyComputed());
        $this->assertSame(1, HeatmapArea::query()->where('barangay', 'Malaruhatan')->count());

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'Is Malaruhatan high risk right now?',
        ]);

        $response->assertOk()->assertJsonPath('chat.prediction_result.status', 'stale');
        $this->assertStringContainsString('earlier', (string) $response->json('chat.answer'));
    }

    public function test_climate_risk_reports_no_current_data_when_no_row_exists(): void
    {
        $this->fakeFreshOpenMeteoWeather();

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        // Cumba has a default row from a migration seeder — remove it so
        // this test genuinely covers the "no row at all" case.
        HeatmapArea::query()->where('barangay', 'Cumba')->delete();
        $this->assertNull(HeatmapArea::query()->where('barangay', 'Cumba')->first());

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'Is Cumba high risk right now?',
        ]);

        $response->assertOk()->assertJsonPath('chat.prediction_result.status', 'no_data');
        $this->assertStringContainsString('No calculated farm-risk rating', (string) $response->json('chat.answer'));
    }

    private function seedClimateRecords(): void
    {
        foreach (range(1, 6) as $index) {
            ClimateRecord::query()->create([
                'record_date' => now()->subMonths(7 - $index)->startOfMonth()->toDateString(),
                'rainfall' => 110 + ($index * 12),
                'temperature' => 27 + $index,
                'humidity' => 76 + $index,
                'wind_speed' => 8 + $index,
                'season' => $index >= 3 ? 'Wet' : 'Dry',
                'source' => 'Test climate record',
            ]);
        }
    }

    private function weatherApiPayload(): array
    {
        return [
            'location' => ['name' => 'Lian'],
            'current' => [
                'temp_c' => 29.4,
                'feelslike_c' => 33.1,
                'humidity' => 82,
                'precip_mm' => 1.2,
                'condition' => ['text' => 'Overcast', 'code' => 1009],
                'last_updated' => now()->format('Y-m-d H:i'),
                'is_day' => 1,
                'cloud' => 90,
                'pressure_mb' => 1010,
                'wind_kph' => 12.4,
                'wind_dir' => 'SW',
                'wind_degree' => 220,
                'gust_kph' => 18.4,
                'vis_km' => 10,
                'uv' => 3,
            ],
            'forecast' => [
                'forecastday' => [
                    [
                        'date' => now()->toDateString(),
                        'day' => [
                            'maxtemp_c' => 31.2,
                            'mintemp_c' => 25.4,
                            'totalprecip_mm' => 4.5,
                            'daily_chance_of_rain' => 70,
                            'condition' => ['text' => 'Patchy rain nearby', 'code' => 1063],
                        ],
                    ],
                    [
                        'date' => now()->addDay()->toDateString(),
                        'day' => [
                            'maxtemp_c' => 30.1,
                            'mintemp_c' => 25.0,
                            'totalprecip_mm' => 8.2,
                            'daily_chance_of_rain' => 86,
                            'condition' => ['text' => 'Moderate rain', 'code' => 1189],
                        ],
                    ],
                ],
            ],
        ];
    }
}
