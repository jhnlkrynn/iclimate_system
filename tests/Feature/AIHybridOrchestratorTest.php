<?php

namespace Tests\Feature;

use App\Models\ClimateRecord;
use App\Models\HeatmapArea;
use App\Models\PlantingAdvisory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AIHybridOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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
        Http::assertNotSent(fn ($request) => str_contains((string) $request->url(), '127.0.0.1:5001/predict'));
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
            '127.0.0.1:5001/predict' => Http::response([
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
        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        HeatmapArea::query()->create([
            'barangay' => 'Malaruhatan',
            'latitude' => 14.0119,
            'longitude' => 120.6669,
            'risk_level' => 'High',
            'risk_type' => 'Heat',
            'risk_score' => 0.9,
            'predicted_yield' => 2.8,
            'rainfall_status' => 'Low rainfall',
            'planting_advisory' => 'Delay planting or use drought-tolerant practices.',
            'irrigation_recommendation' => 'Prioritize irrigation support within 7 days.',
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
