<?php

namespace Tests\Unit;

use App\Services\WeatherApiService;
use App\Support\CoordinateNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class WeatherApiServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
    }

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
        $this->assertSame('Light Rain', $forecast['current_condition']);
        $this->assertSame(['2026-07-04', '2026-07-05'], $forecast['daily_series']['labels']);
        $this->assertSame([4.0, 0.0], $forecast['daily_series']['rainfall']);
    }

    /**
     * Regression coverage for the "0.00 shown as if it were a real reading"
     * bug: a field genuinely absent from Open-Meteo's current block must
     * come back as null, never silently defaulted to 0.
     */
    public function test_missing_current_weather_fields_are_null_not_zero(): void
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
                    // temperature_2m, relative_humidity_2m, precipitation/
                    // rain, wind_speed_10m are all deliberately omitted.
                    'weather_code' => 61,
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

        $this->assertNull($forecast['current_rainfall_mm']);
        $this->assertNull($forecast['humidity_percent']);
        // current_temperature_c/wind_speed_kmh still fall back to the
        // daily series' first value when present, so they're only null if
        // that's ALSO missing — here the daily series does have values, so
        // confirm those are used rather than a fabricated 0.
        $this->assertSame(29.0, $forecast['current_temperature_c']);
        $this->assertSame(10.0, $forecast['wind_speed_kmh']);
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

    public function test_dashboard_forecast_is_cached_until_an_explicit_refresh(): void
    {
        config([
            'services.weather_api.enabled' => true,
            'services.weather_api.forecast_days' => 1,
            'services.weather_api.refresh_minutes' => 10,
        ]);

        Http::fake([
            'api.open-meteo.com/v1/forecast*' => Http::response([
                'current' => ['temperature_2m' => 29, 'relative_humidity_2m' => 80, 'wind_speed_10m' => 8],
                'daily' => [
                    'time' => ['2026-09-13'],
                    'temperature_2m_max' => [30],
                    'temperature_2m_min' => [28],
                    'precipitation_sum' => [2],
                    'precipitation_probability_max' => [30],
                    'wind_speed_10m_max' => [8],
                ],
            ]),
        ]);

        $service = app(WeatherApiService::class);
        $service->forecast();
        $service->forecast();

        Http::assertSentCount(1);

        $service->forecast(refresh: true);
        Http::assertSentCount(2);
    }

    public function test_failed_refresh_returns_the_cached_open_meteo_response(): void
    {
        config([
            'services.weather_api.enabled' => true,
            'services.weather_api.forecast_days' => 1,
            'services.weather_api.refresh_minutes' => 45,
        ]);

        Http::fakeSequence('api.open-meteo.com/v1/forecast*')
            ->push([
                'current' => ['temperature_2m' => 29, 'relative_humidity_2m' => 80, 'weather_code' => 2, 'wind_speed_10m' => 8],
                'daily' => [
                    'time' => ['2026-09-13'],
                    'weather_code' => [2],
                    'temperature_2m_max' => [30],
                    'temperature_2m_min' => [28],
                    'precipitation_sum' => [2],
                    'precipitation_probability_max' => [30],
                    'wind_speed_10m_max' => [8],
                ],
            ], 200)
            ->push(['message' => 'temporarily unavailable'], 503);

        $service = app(WeatherApiService::class);
        $first = $service->forecast();
        $fallback = $service->forecast(refresh: true);

        $this->assertSame('Partly Cloudy', $first['current_condition']);
        $this->assertSame($first, $fallback);
        Http::assertSentCount(2);
    }

    public function test_failed_request_uses_latest_cached_open_meteo_response_after_fresh_cache_expires(): void
    {
        config([
            'services.weather_api.enabled' => true,
            'services.weather_api.forecast_days' => 1,
            'services.weather_api.refresh_minutes' => 45,
        ]);
        Http::fakeSequence('api.open-meteo.com/v1/forecast*')
            ->push([
                'current' => ['temperature_2m' => 29, 'relative_humidity_2m' => 80, 'weather_code' => 2, 'wind_speed_10m' => 8],
                'daily' => [
                    'time' => ['2026-09-13'],
                    'weather_code' => [2],
                    'temperature_2m_max' => [30],
                    'temperature_2m_min' => [28],
                    'precipitation_sum' => [2],
                    'precipitation_probability_max' => [30],
                    'wind_speed_10m_max' => [8],
                ],
            ], 200)
            ->push(['message' => 'temporarily unavailable'], 503);

        $latitude = 14.0389;
        $longitude = 120.6503;
        $location = 'Lian, Batangas municipal preview';
        $cacheKey = 'weather-api:forecast:'.Str::slug($location).':'.sha1(CoordinateNormalizer::pair($latitude, $longitude));
        $service = app(WeatherApiService::class);

        $first = $service->forecast(latitude: $latitude, longitude: $longitude, location: $location);
        Cache::forget($cacheKey);
        $fallback = $service->forecast(latitude: $latitude, longitude: $longitude, location: $location);

        $this->assertSame($first['daily_series'], $fallback['daily_series']);
        $this->assertTrue($fallback['is_cached_fallback']);
        $this->assertSame('Open-Meteo Forecast API (latest cached)', $fallback['source_name']);
        Http::assertSentCount(2);
    }

    public function test_a_refresh_lock_prevents_a_second_forecast_provider_call(): void
    {
        config([
            'services.weather_api.enabled' => true,
            'services.weather_api.forecast_days' => 1,
        ]);
        Http::fake([
            'api.open-meteo.com/v1/forecast*' => Http::response([
                'current' => ['temperature_2m' => 29, 'relative_humidity_2m' => 80, 'weather_code' => 2, 'wind_speed_10m' => 8],
                'daily' => [
                    'time' => ['2026-09-13'],
                    'weather_code' => [2],
                    'temperature_2m_max' => [30],
                    'temperature_2m_min' => [28],
                    'precipitation_sum' => [0],
                    'precipitation_probability_max' => [0],
                    'wind_speed_10m_max' => [8],
                ],
            ], 200),
        ]);

        $location = 'Lian, Batangas lock test';
        $latitude = 14.033;
        $longitude = 120.650;
        $cacheKey = 'weather-api:forecast:'.Str::slug($location).':'.sha1(CoordinateNormalizer::pair($latitude, $longitude));
        $service = app(WeatherApiService::class);
        $first = $service->forecast(refresh: true, latitude: $latitude, longitude: $longitude, location: $location);
        $lock = Cache::lock($cacheKey.':refresh-lock', 60);
        $this->assertTrue($lock->get());

        try {
            $second = $service->forecast(refresh: true, latitude: $latitude, longitude: $longitude, location: $location);
        } finally {
            $lock->release();
        }

        $this->assertSame($first['daily_series'], $second['daily_series']);
        Http::assertSentCount(1);
    }
}
