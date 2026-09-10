<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Weather\WeatherForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeeklyWeatherForecastTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekly_forecast_service_normalizes_open_meteo_data_and_advice(): void
    {
        Cache::flush();
        $this->useOpenMeteoConfig();
        Http::fake([
            'api.open-meteo.com/*' => Http::response($this->openMeteoPayload(), 200),
        ]);

        $forecast = app(WeatherForecastService::class)->forecast(force: true);

        $this->assertTrue($forecast['success']);
        $this->assertSame('Open-Meteo', $forecast['provider']);
        $this->assertSame(7, count($forecast['days']));
        $this->assertSame('Heavy Rain', $forecast['days'][1]['condition']);
        $this->assertSame('High', $forecast['days'][1]['risk_level']);
        $this->assertSame(82.5, $forecast['days'][0]['humidity_percent']);
        $this->assertNotEmpty($forecast['summary']['advice']);
        Http::assertSentCount(1);
    }

    public function test_farmer_dashboard_shows_weekly_weather_forecast_section(): void
    {
        Cache::flush();
        $this->useOpenMeteoConfig();
        Http::fake([
            'api.open-meteo.com/*' => Http::response($this->openMeteoPayload(), 200),
        ]);

        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        $response = $this->actingAs($farmer)->get(route('farmer.dashboard'));

        $response->assertOk()
            ->assertSee('Weekly Weather Forecast')
            ->assertSee('Open-Meteo 7-day outlook')
            ->assertSee('Heavy Rain')
            ->assertSee('Avoid planting', false);
    }

    public function test_mao_dashboard_shows_weekly_weather_forecast_section(): void
    {
        Cache::flush();
        $this->useOpenMeteoConfig();
        Http::fake([
            'api.open-meteo.com/*' => Http::response($this->openMeteoPayload(), 200),
        ]);

        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $response = $this->actingAs($mao)->get(route('mao.dashboard'));

        $response->assertOk()
            ->assertSee('Weekly Weather Forecast')
            ->assertSee('Open-Meteo 7-day outlook')
            ->assertSee('Best planting windows', false);
    }

    public function test_weekly_forecast_uses_cached_fallback_when_api_fails(): void
    {
        Cache::flush();
        $this->useOpenMeteoConfig();
        Http::fake([
            'api.open-meteo.com/*' => Http::sequence()
                ->push($this->openMeteoPayload(), 200)
                ->push([], 503),
        ]);

        $service = app(WeatherForecastService::class);
        $fresh = $service->forecast(force: true);
        $fallback = $service->forecast(force: true);

        $this->assertFalse($fresh['stale']);
        $this->assertTrue($fallback['stale']);
        $this->assertSame(7, count($fallback['days']));
        $this->assertStringContainsString('latest cached weekly forecast', $fallback['note']);
    }

    private function useOpenMeteoConfig(): void
    {
        config([
            'services.weather.provider' => 'open_meteo',
            'services.weather.latitude' => 13.98,
            'services.weather.longitude' => 120.65,
            'services.weather.cache_minutes' => 45,
            'services.open_meteo.base_url' => 'https://api.open-meteo.com/v1',
        ]);
    }

    private function openMeteoPayload(): array
    {
        $dates = collect(range(0, 6))
            ->map(fn (int $day): string => now('Asia/Manila')->addDays($day)->toDateString())
            ->all();
        $hourlyTimes = [];
        $hourlyHumidity = [];

        foreach ($dates as $date) {
            foreach (range(0, 23) as $hour) {
                $hourlyTimes[] = $date.' '.str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00';
                $hourlyHumidity[] = $hour % 2 === 0 ? 80 : 85;
            }
        }

        return [
            'latitude' => 13.98,
            'longitude' => 120.65,
            'timezone' => 'Asia/Manila',
            'current' => [
                'time' => $dates[0].' 08:00',
                'temperature_2m' => 29.2,
                'relative_humidity_2m' => 82,
                'apparent_temperature' => 33.1,
                'precipitation' => 0.2,
                'rain' => 0.2,
                'weather_code' => 61,
                'wind_speed_10m' => 12.4,
                'is_day' => 1,
            ],
            'daily' => [
                'time' => $dates,
                'weather_code' => [2, 65, 95, 3, 61, 0, 1],
                'temperature_2m_max' => [31, 29, 28, 30, 31, 32, 33],
                'temperature_2m_min' => [24, 24, 23, 24, 25, 24, 24],
                'apparent_temperature_max' => [34, 33, 32, 33, 34, 35, 36],
                'apparent_temperature_min' => [25, 25, 24, 25, 26, 25, 25],
                'precipitation_sum' => [1.2, 25.5, 44.0, 4.0, 8.5, 0.2, 0.0],
                'rain_sum' => [1.2, 25.5, 44.0, 4.0, 8.5, 0.2, 0.0],
                'precipitation_probability_max' => [35, 85, 90, 45, 60, 20, 15],
                'wind_speed_10m_max' => [12, 22, 35, 18, 16, 10, 9],
                'wind_gusts_10m_max' => [20, 30, 45, 26, 24, 18, 16],
            ],
            'hourly' => [
                'time' => $hourlyTimes,
                'relative_humidity_2m' => $hourlyHumidity,
            ],
        ];
    }
}
