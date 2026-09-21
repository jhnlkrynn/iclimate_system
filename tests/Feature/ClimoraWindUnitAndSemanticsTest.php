<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regression coverage for the 2026-09 wind-unit audit:
 *  - Climora's monthly-prediction text must not assert an unverified "km/h"
 *    unit for the historical-dataset-derived wind figure.
 *  - Climora's current-weather text must keep asserting km/h, since that
 *    figure genuinely comes from Open-Meteo (confirmed km/h by that API's
 *    documented default).
 *  - Whatever label changed, the underlying number must be unchanged (no
 *    silent unit conversion was introduced by this fix).
 *  - Current-weather and monthly-prediction responses must remain clearly
 *    distinguishable (different intent/source framing, not conflated).
 */
class ClimoraWindUnitAndSemanticsTest extends TestCase
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

    public function test_monthly_prediction_wind_text_does_not_assert_kmh(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'What does the iClimate model predict for next month rainfall?',
        ]);

        $response->assertOk()->assertJsonPath('chat.intent', 'weather_prediction');

        $answer = (string) $response->json('chat.answer');
        $windValue = $response->json('chat.weather_prediction.wind_speed');

        $this->assertIsNumeric($windValue);
        $this->assertStringNotContainsString('km/h', $answer, 'Monthly-prediction wind text must not assert an unverified km/h unit.');

        // Value-preservation: the raw wind figure must still appear verbatim
        // in the answer text — only the unit CLAIM was removed, the number
        // itself must not be silently converted.
        $this->assertStringContainsString((string) $windValue, $answer);
    }

    public function test_current_weather_wind_text_still_asserts_kmh_because_it_is_confirmed(): void
    {
        Http::fake([
            '*/forecast.json*' => Http::response($this->weatherApiPayload(12.3), 200),
        ]);

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            "question" => "What's the weather right now?",
        ]);

        $response->assertOk()->assertJsonPath('chat.intent', 'current_weather');

        $answer = (string) $response->json('chat.answer');
        $windKph = $response->json('chat.prediction_result.data.wind_kph');

        $this->assertStringContainsString('km/h', $answer, 'Current (Open-Meteo) weather wind is confirmed km/h and should keep asserting it.');
        $this->assertStringContainsString((string) $windKph, $answer, 'The Open-Meteo wind value must not be unit-converted.');
    }

    public function test_current_weather_and_monthly_prediction_responses_stay_semantically_distinct(): void
    {
        Http::fake([
            '*/forecast.json*' => Http::response($this->weatherApiPayload(10), 200),
        ]);

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        $current = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            "question" => "What's the temperature right now?",
        ]);
        $prediction = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'What does the iClimate model predict for next month temperature?',
        ]);

        $current->assertOk()->assertJsonPath('chat.intent', 'current_weather')->assertJsonPath('chat.source_type', 'Live Weather');
        $prediction->assertOk()->assertJsonPath('chat.intent', 'weather_prediction')->assertJsonPath('chat.source_type', 'Trained Model');

        $currentAnswer = (string) $current->json('chat.answer');
        $predictionAnswer = (string) $prediction->json('chat.answer');

        $this->assertStringContainsString('current weather', strtolower($currentAnswer));
        $this->assertStringContainsString('random forest model', strtolower($predictionAnswer));
        $this->assertStringNotContainsString('current weather', strtolower($predictionAnswer));
    }

    private function weatherApiPayload(float $windKph): array
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
                'wind_kph' => $windKph,
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
                ],
            ],
        ];
    }
}
