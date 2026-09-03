<?php

namespace Tests\Unit;

use App\Services\WeatherApiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherApiServiceTest extends TestCase
{
    public function test_it_maps_open_meteo_forecast_to_planning_inputs(): void
    {
        config([
            'services.weather_api.enabled' => true,
            'services.weather_api.forecast_days' => 2,
            'services.weather.timezone' => 'Asia/Manila',
        ]);

        Http::fake([
            'api.open-meteo.com/v1/forecast*' => Http::response([
                'current' => [
                    'time' => '2026-07-04T16:00',
                    'temperature_2m' => 29.4,
                    'relative_humidity_2m' => 83,
                    'precipitation' => 0.8,
                    'rain' => 0.8,
                    'weather_code' => 61,
                    'wind_speed_10m' => 9,
                ],
                'daily' => [
                    'time' => ['2026-07-04', '2026-07-05'],
                    'weather_code' => [61, 2],
                    'temperature_2m_max' => [30, 31],
                    'temperature_2m_min' => [28, 27],
                    'precipitation_sum' => [4, 0],
                    'rain_sum' => [4, 0],
                    'precipitation_probability_max' => [60, 20],
                    'wind_speed_10m_max' => [10, 8],
                ],
            ]),
        ]);

        $forecast = app(WeatherApiService::class)->forecast();

        $this->assertSame('Open-Meteo', $forecast['source']);
        $this->assertSame('Live/current weather and frequent dashboard updates', $forecast['source_role']);
        $this->assertSame(2, $forecast['forecast_days']);
        $this->assertSame(60.0, $forecast['monthly_rainfall_estimate_mm']);
        $this->assertSame(29.0, $forecast['temperature_c']);
        $this->assertSame(83.0, $forecast['humidity_percent']);
        $this->assertSame(9.0, $forecast['wind_speed_kmh']);
        $this->assertSame(['2026-07-04', '2026-07-05'], $forecast['daily_series']['labels']);
        $this->assertSame([4.0, 0.0], $forecast['daily_series']['rainfall']);
    }

    public function test_it_does_not_require_an_openweather_key(): void
    {
        config([
            'services.weather_api.enabled' => true,
            'services.weather_api.key' => '',
            'services.weather_api.forecast_days' => 1,
        ]);

        Http::fake([
            'api.open-meteo.com/v1/forecast*' => Http::response([
                'current' => [
                    'time' => '2026-07-04T16:00',
                    'temperature_2m' => 29.4,
                    'relative_humidity_2m' => 83,
                    'precipitation' => 0,
                    'wind_speed_10m' => 9,
                ],
                'daily' => [
                    'time' => ['2026-07-04'],
                    'temperature_2m_max' => [30],
                    'temperature_2m_min' => [28],
                    'precipitation_sum' => [0],
                    'precipitation_probability_max' => [20],
                    'wind_speed_10m_max' => [9],
                ],
            ]),
        ]);

        $this->assertSame('Open-Meteo', app(WeatherApiService::class)->forecast()['source']);

        Http::assertSentCount(1);
    }
}
