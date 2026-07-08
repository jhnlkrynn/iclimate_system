<?php

namespace Tests\Unit;

use App\Services\WeatherApiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherApiServiceTest extends TestCase
{
    public function test_it_maps_openweather_forecast_to_planning_inputs(): void
    {
        config([
            'services.weather_api.enabled' => true,
            'services.weather_api.key' => 'test-key',
            'services.weather_api.forecast_days' => 2,
        ]);

        Http::fake([
            'api.openweathermap.org/data/2.5/forecast*' => Http::response([
                'list' => [
                    [
                        'dt_txt' => '2026-07-04 00:00:00',
                        'main' => ['temp' => 28, 'humidity' => 80],
                        'wind' => ['speed' => 2],
                        'rain' => ['3h' => 1.5],
                        'pop' => 0.4,
                    ],
                    [
                        'dt_txt' => '2026-07-04 03:00:00',
                        'main' => ['temp' => 30, 'humidity' => 82],
                        'wind' => ['speed' => 3],
                        'rain' => ['3h' => 2.5],
                        'pop' => 0.6,
                    ],
                    [
                        'dt_txt' => '2026-07-05 00:00:00',
                        'main' => ['temp' => 29, 'humidity' => 84],
                        'wind' => ['speed' => 4],
                        'pop' => 0.2,
                    ],
                ],
            ]),
            'api.openweathermap.org/data/2.5/weather*' => Http::response([
                'dt' => 1783123200,
                'main' => ['temp' => 29.4, 'humidity' => 83],
                'wind' => ['speed' => 2.5],
                'rain' => ['1h' => 0.8],
            ]),
        ]);

        $forecast = app(WeatherApiService::class)->forecast();

        $this->assertSame('OpenWeather', $forecast['source']);
        $this->assertSame(2, $forecast['forecast_days']);
        $this->assertSame(60.0, $forecast['monthly_rainfall_estimate_mm']);
        $this->assertSame(29.0, $forecast['temperature_c']);
        $this->assertSame(83.0, $forecast['humidity_percent']);
        $this->assertSame(9.0, $forecast['wind_speed_kmh']);
        $this->assertSame(['2026-07-04', '2026-07-05'], $forecast['daily_series']['labels']);
        $this->assertSame([4.0, 0.0], $forecast['daily_series']['rainfall']);
    }

    public function test_it_returns_null_when_openweather_key_is_missing(): void
    {
        config([
            'services.weather_api.enabled' => true,
            'services.weather_api.key' => '',
        ]);

        Http::fake();

        $this->assertNull(app(WeatherApiService::class)->forecast());

        Http::assertNothingSent();
    }
}
