<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class WeatherApiService
{
    private const CURRENT_FIELDS = [
        'temperature_2m',
        'relative_humidity_2m',
        'precipitation',
        'rain',
        'weather_code',
        'wind_speed_10m',
    ];

    private const DAILY_FIELDS = [
        'weather_code',
        'temperature_2m_max',
        'temperature_2m_min',
        'precipitation_sum',
        'rain_sum',
        'precipitation_probability_max',
        'wind_speed_10m_max',
    ];

    public function forecast(bool $refresh = false): ?array
    {
        $realTime = (bool) config('services.weather_api.realtime', true);

        if ($realTime || $refresh) {
            return $this->fetchForecast();
        }

        return Cache::remember('weather-api:forecast:lian', now()->addMinutes((int) config('services.weather_api.refresh_minutes', 10)), fn () => $this->fetchForecast());
    }

    private function fetchForecast(): ?array
    {
        if (! config('services.weather_api.enabled', true)) {
            return null;
        }

        $latitude = config('services.weather.latitude', config('services.open_meteo.latitude', config('services.weather_api.latitude', 14.04)));
        $longitude = config('services.weather.longitude', config('services.open_meteo.longitude', config('services.weather_api.longitude', 120.65)));
        $timezone = config('services.weather.timezone', config('services.open_meteo.timezone', config('services.weather_api.timezone', 'Asia/Manila')));
        $timeout = (int) config('services.weather_api.timeout', 8);
        $forecastDays = min(10, max(1, (int) config('services.weather_api.forecast_days', config('services.weather.forecast_days', 7))));

        try {
            $response = Http::timeout($timeout)
                ->retry(2, 250)
                ->acceptJson()
                ->get(rtrim((string) config('services.open_meteo.base_url', 'https://api.open-meteo.com/v1'), '/').'/forecast', [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'current' => implode(',', self::CURRENT_FIELDS),
                    'daily' => implode(',', self::DAILY_FIELDS),
                    'timezone' => $timezone,
                    'forecast_days' => $forecastDays,
                ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        try {
            return $this->normalizeOpenMeteo($response->json(), $forecastDays);
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeOpenMeteo(mixed $payload, int $requestedDays): array
    {
        if (! is_array($payload) || ! is_array($payload['daily'] ?? null)) {
            throw new RuntimeException('Open-Meteo response is missing daily weather data.');
        }

        $current = is_array($payload['current'] ?? null) ? $payload['current'] : [];
        $daily = $payload['daily'];
        $dates = array_slice(array_map('strval', $daily['time'] ?? []), 0, $requestedDays);

        if ($dates === []) {
            throw new RuntimeException('Open-Meteo response has no forecast dates.');
        }

        $rain = $this->series($daily, 'precipitation_sum', $dates, fallbackKey: 'rain_sum');
        $temperatureMax = $this->series($daily, 'temperature_2m_max', $dates);
        $temperatureMin = $this->series($daily, 'temperature_2m_min', $dates);
        $temperature = array_map(
            fn (float $max, float $min): float => round(($max + $min) / 2, 2),
            $temperatureMax,
            $temperatureMin,
        );
        $wind = $this->series($daily, 'wind_speed_10m_max', $dates);
        $probability = $this->series($daily, 'precipitation_probability_max', $dates);
        $days = max(count($dates), 1);

        return [
            'source' => 'Open-Meteo',
            'source_name' => 'Open-Meteo Forecast API',
            'source_url' => 'https://open-meteo.com/',
            'source_credit' => 'Live and forecast weather data from Open-Meteo; official Philippine advisories remain sourced separately from DOST-PAGASA.',
            'source_role' => 'Live/current weather and frequent dashboard updates',
            'location' => config('services.weather.location_name', config('services.weather_api.location_name', 'Lian, Batangas')),
            'current_time' => (string) data_get($current, 'time', now((string) config('services.weather.timezone', 'Asia/Manila'))->toDateTimeString()),
            'current_rainfall_mm' => round((float) (data_get($current, 'precipitation') ?? data_get($current, 'rain') ?? 0), 2),
            'current_temperature_c' => round((float) (data_get($current, 'temperature_2m') ?? ($temperature[0] ?? 0)), 2),
            'humidity_percent' => round((float) (data_get($current, 'relative_humidity_2m') ?? 0), 2),
            'wind_speed_kmh' => round((float) (data_get($current, 'wind_speed_10m') ?? ($wind[0] ?? 0)), 2),
            'forecast_days' => $days,
            'daily_rainfall_mm' => round(array_sum($rain) / $days, 2),
            'monthly_rainfall_estimate_mm' => round((array_sum($rain) / $days) * 30, 2),
            'temperature_c' => round($this->average($temperature), 2),
            'precip_probability_percent' => round($this->average($probability), 2),
            'fetched_at' => now((string) config('services.weather.timezone', 'Asia/Manila'))->toIso8601String(),
            'daily_series' => [
                'labels' => $dates,
                'rainfall' => array_map(fn ($value) => round((float) $value, 2), $rain),
                'temperature' => array_map(fn ($value) => round((float) $value, 2), $temperature),
                'humidity' => array_fill(0, $days, round((float) (data_get($current, 'relative_humidity_2m') ?? 0), 2)),
                'windSpeed' => array_map(fn ($value) => round((float) $value, 2), $wind),
            ],
        ];
    }

    private function series(array $daily, string $key, array $dates, ?string $fallbackKey = null): array
    {
        $values = $daily[$key] ?? ($fallbackKey ? ($daily[$fallbackKey] ?? []) : []);

        return array_map(
            fn (int $index): float => (float) ($values[$index] ?? 0),
            array_keys($dates),
        );
    }

    private function average(array $values): float
    {
        $values = array_map('floatval', $values);

        return $values === [] ? 0.0 : array_sum($values) / count($values);
    }
}
