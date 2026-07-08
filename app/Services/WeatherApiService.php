<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class WeatherApiService
{
    public function forecast(): ?array
    {
        if (! config('services.weather_api.enabled', true)) {
            return null;
        }

        $apiKey = config('services.weather_api.key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            return null;
        }

        $latitude = config('services.weather_api.latitude', 14.04);
        $longitude = config('services.weather_api.longitude', 120.65);
        $timeout = (int) config('services.weather_api.timeout', 8);

        try {
            $forecastResponse = Http::timeout($timeout)
                ->acceptJson()
                ->get('https://api.openweathermap.org/data/2.5/forecast', [
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'appid' => $apiKey,
                    'units' => 'metric',
                    'cnt' => min(40, max(1, (int) config('services.weather_api.forecast_days', 5) * 8)),
                ]);

            $currentResponse = Http::timeout($timeout)
                ->acceptJson()
                ->get('https://api.openweathermap.org/data/2.5/weather', [
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'appid' => $apiKey,
                    'units' => 'metric',
                ]);
        } catch (Throwable) {
            return null;
        }

        if (! $forecastResponse->successful()) {
            return null;
        }

        $json = $forecastResponse->json();
        $items = data_get($json, 'list', []);

        if (! is_array($items) || $items === []) {
            return null;
        }

        $daily = $this->dailyForecast($items);
        $dates = array_keys($daily);
        $rain = array_values(array_map(fn (array $day): float => $day['rainfall'], $daily));
        $temperature = array_values(array_map(fn (array $day): float => $this->average($day['temperature']), $daily));
        $humidity = array_values(array_map(fn (array $day): float => $this->average($day['humidity']), $daily));
        $wind = array_values(array_map(fn (array $day): float => $this->average($day['wind_speed']), $daily));
        $firstForecast = $items[0];
        $current = $currentResponse->successful() ? $currentResponse->json() : [];
        $days = max(count($daily), 1);

        return [
            'source' => 'OpenWeather',
            'location' => config('services.weather_api.location_name', 'Lian, Batangas'),
            'current_time' => data_get($current, 'dt') ? date('Y-m-d H:i:s', (int) data_get($current, 'dt')) : data_get($firstForecast, 'dt_txt'),
            'current_rainfall_mm' => (float) (data_get($current, 'rain.1h') ?? data_get($current, 'rain.3h') ?? 0),
            'current_temperature_c' => (float) (data_get($current, 'main.temp') ?? data_get($firstForecast, 'main.temp', 0)),
            'humidity_percent' => (float) (data_get($current, 'main.humidity') ?? data_get($firstForecast, 'main.humidity', 0)),
            'wind_speed_kmh' => round($this->metersPerSecondToKmh((float) (data_get($current, 'wind.speed') ?? data_get($firstForecast, 'wind.speed', 0))), 2),
            'forecast_days' => $days,
            'daily_rainfall_mm' => round(array_sum($rain) / $days, 2),
            'monthly_rainfall_estimate_mm' => round((array_sum($rain) / $days) * 30, 2),
            'temperature_c' => round($this->average($temperature), 2),
            'precip_probability_percent' => round($this->average(array_map(fn (array $item): float => ((float) data_get($item, 'pop', 0)) * 100, $items)), 2),
            'daily_series' => [
                'labels' => array_map(fn ($date) => (string) $date, $dates),
                'rainfall' => array_map(fn ($value) => round((float) $value, 2), $rain),
                'temperature' => array_map(fn ($value) => round((float) $value, 2), $temperature),
                'humidity' => array_map(fn ($value) => round((float) $value, 2), $humidity),
                'windSpeed' => array_map(fn ($value) => round((float) $value, 2), $wind),
            ],
        ];
    }

    private function average(array $values): float
    {
        $values = array_map('floatval', $values);

        return $values === [] ? 0.0 : array_sum($values) / count($values);
    }

    private function dailyForecast(array $items): array
    {
        $daily = [];

        foreach ($items as $item) {
            $date = substr((string) data_get($item, 'dt_txt', ''), 0, 10);

            if ($date === '') {
                continue;
            }

            $daily[$date]['rainfall'] = ($daily[$date]['rainfall'] ?? 0) + (float) data_get($item, 'rain.3h', 0);
            $daily[$date]['temperature'][] = (float) data_get($item, 'main.temp', 0);
            $daily[$date]['humidity'][] = (float) data_get($item, 'main.humidity', 0);
            $daily[$date]['wind_speed'][] = $this->metersPerSecondToKmh((float) data_get($item, 'wind.speed', 0));
        }

        return $daily;
    }

    private function metersPerSecondToKmh(float $value): float
    {
        return $value * 3.6;
    }
}
