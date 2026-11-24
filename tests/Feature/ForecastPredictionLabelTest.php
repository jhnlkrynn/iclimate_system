<?php

namespace Tests\Feature;

use App\Models\FarmerProfile;
use App\Models\User;
use App\Services\Weather\FarmerDashboardWeatherService;
use App\Services\Weather\LianBarangayWeatherService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ForecastPredictionLabelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Queue::fake();
        config([
            'iclimate.python_binary' => '/nonexistent/python',
            'services.groq.enabled' => false,
        ]);
    }

    private function seedDailyForecast(int $days = 14): void
    {
        $today = CarbonImmutable::now('Asia/Manila')->startOfDay();
        Http::fake(['*' => Http::response([
            'current' => [
                'temperature_2m' => 30.0,
                'relative_humidity_2m' => 75,
                'precipitation' => 0.0,
                'weather_code' => 2,
                'wind_speed_10m' => 11.0,
            ],
            'daily' => [
                'time' => array_map(fn ($i) => $today->addDays($i)->toDateString(), range(0, $days - 1)),
                'weather_code' => array_fill(0, $days, 2),
                'temperature_2m_max' => array_fill(0, $days, 31.0),
                'temperature_2m_min' => array_fill(0, $days, 24.0),
                'precipitation_sum' => array_fill(0, $days, 2.0),
                'rain_sum' => array_fill(0, $days, 2.0),
                'precipitation_probability_max' => array_fill(0, $days, 40),
                'wind_speed_10m_max' => array_fill(0, $days, 12.0),
            ],
            'hourly' => ['time' => [], 'temperature_2m' => [], 'relative_humidity_2m' => []],
        ])]);
        app(FarmerDashboardWeatherService::class)->current(force: true);
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['*' => Http::response(['error' => 'offline'], 503)]);

        Cache::forever(LianBarangayWeatherService::STORE_KEY, [
            'records' => [
                'Bagong Pook' => [
                    'barangay' => 'Bagong Pook',
                    'temperature_c' => 29.0,
                    'fetched_at' => now()->toIso8601String(),
                    'observed_at' => now()->toIso8601String(),
                    'daily' => array_map(fn ($i) => [
                        'date' => $today->addDays($i)->toDateString(),
                        'weather_code' => 2,
                        'condition' => 'Partly Cloudy',
                        'rainfall' => 2.0,
                        'precipitation_probability' => 40,
                        'temperature_max' => 31.0,
                        'temperature_min' => 24.0,
                        'wind_speed_max' => 12.0,
                    ], range(0, 15)),
                ],
            ],
            'status' => [],
        ]);
    }

    public function test_farmer_dashboard_weather_shows_prediction_note_in_en_and_fil(): void
    {
        $this->seedDailyForecast(7);
        $farmer = User::factory()->farmer()->create(['preferred_language' => 'en']);
        FarmerProfile::factory()->create(['user_id' => $farmer->id, 'barangay' => 'Bagong Pook']);

        $this->actingAs($farmer)
            ->get(route('farmer.dashboard'))
            ->assertOk()
            ->assertSee('Forecast only — this is a prediction and the weather can still change.')
            ->assertSee('Source: Open-Meteo');

        $this->actingAs($farmer)->post(route('farmer.dashboard.language'), ['language' => 'fil']);

        $this->actingAs($farmer->fresh())
            ->get(route('farmer.dashboard'))
            ->assertOk()
            ->assertSee('Hula lamang ito — maaari pang magbago ang panahon.')
            ->assertSee('Pinagmulan: Open-Meteo');
    }

    public function test_weather_predictions_and_14_day_less_certain_badges_work_in_en_and_fil(): void
    {
        $this->seedDailyForecast(14);
        $mao = User::factory()->maoPersonnel()->create(['preferred_language' => 'en']);

        // 7-day view: note shown, no "Less certain" badges.
        $this->actingAs($mao)
            ->get(route('weather-predictions.index'))
            ->assertOk()
            ->assertSee('Forecast only — this is a prediction and the weather can still change.')
            ->assertSee('Source: Open-Meteo')
            ->assertDontSee('data-less-certain', false);

        // 14-day view: days 8-14 marked with "Less certain" and lighter .is-less-certain cards.
        $twoWeeks = $this->actingAs($mao)
            ->get(route('weather-predictions.index', ['range' => '2weeks']))
            ->assertOk()
            ->assertSee('Less certain')
            ->assertSee('is-less-certain', false)
            ->getContent();
        $this->assertSame(7, substr_count($twoWeeks, 'data-less-certain'));

        // MAO 14-day view in Filipino: Filipino note + "Hindi pa tiyak".
        $this->actingAs($mao)->post(route('mao.dashboard.language'), ['language' => 'fil']);
        $this->actingAs($mao->fresh())
            ->get(route('weather-predictions.index', ['range' => '2weeks']))
            ->assertOk()
            ->assertSee('Hula lamang ito — maaari pang magbago ang panahon.')
            ->assertSee('Hindi pa tiyak');
    }

    public function test_heat_map_shows_prediction_note_for_week_and_two_weeks(): void
    {
        $this->seedDailyForecast(14);
        $mao = User::factory()->maoPersonnel()->create(['preferred_language' => 'en']);

        $this->actingAs($mao)
            ->get(route('heat-map.index'))
            ->assertOk()
            ->assertSee('Forecast only — this is a prediction and the weather can still change.')
            ->assertSee('Source: Open-Meteo');
    }

    public function test_climora_forecast_reply_ends_with_prediction_disclaimer(): void
    {
        $this->seedDailyForecast(7);
        $farmer = User::factory()->farmer()->create(['preferred_language' => 'en']);
        FarmerProfile::factory()->create(['user_id' => $farmer->id, 'barangay' => 'Bagong Pook']);

        $en = $this->actingAs($farmer)->postJson(route('ai-chat.message'), [
            'question' => 'What is the 7-day weather forecast?',
        ])->assertOk();
        $this->assertStringContainsString('Note: This is a forecast only and the weather can still change.', (string) $en->json('chat.answer'));

        $fil = $this->actingAs($farmer)
            ->withHeaders(['X-Farmer-Lang' => 'fil'])
            ->postJson(route('ai-chat.message'), [
                'question' => 'Ano ang forecast ng panahon bukas?',
            ])->assertOk();
        $this->assertStringContainsString('Paalala: Hula lamang ito at maaari pang magbago ang panahon.', (string) $fil->json('chat.answer'));
    }
}
