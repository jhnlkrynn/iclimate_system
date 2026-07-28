<?php

namespace App\Services\Weather;

use App\Support\LianBarangays;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class LianBarangayWeatherService
{
    private const CURRENT = [
        'temperature_2m',
        'relative_humidity_2m',
        'precipitation',
        'rain',
        'weather_code',
        'wind_speed_10m',
    ];

    private const HOURLY = [
        'precipitation_probability',
        'precipitation',
        'rain',
        'temperature_2m',
        'relative_humidity_2m',
        'wind_speed_10m',
    ];

    private const DAILY = [
        'precipitation_sum',
        'rain_sum',
        'temperature_2m_max',
        'temperature_2m_min',
        'precipitation_probability_max',
    ];

    public function all(bool $refresh = false): array
    {
        $weather = [];

        foreach (LianBarangays::coordinates() as $barangay => [$latitude, $longitude]) {
            $weather[$this->normalizeBarangay($barangay)] = $this->forBarangay($barangay, $latitude, $longitude, $refresh);
        }

        return $weather;
    }

    public function forBarangay(string $barangay, float $latitude, float $longitude, bool $refresh = false): array
    {
        $cacheKey = 'weather-api:lian-barangay:'.Str::slug($barangay);

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        if ($refresh) {
            return $this->fetchBarangayWeather($barangay, $latitude, $longitude);
        }

        return Cache::remember($cacheKey, now()->addMinutes($this->refreshMinutes()), fn (): array => $this->fetchBarangayWeather($barangay, $latitude, $longitude));
    }

    public function refresh(): array
    {
        return $this->all(refresh: true);
    }

    private function requestForecast(float $latitude, float $longitude): array
    {
        $baseUrl = rtrim((string) config('services.open_meteo.base_url', 'https://api.open-meteo.com/v1'), '/');
        $response = Http::timeout((int) config('services.open_meteo.timeout', 10))
            ->retry(2, 300)
            ->acceptJson()
            ->get($baseUrl.'/forecast', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'current' => implode(',', self::CURRENT),
                'hourly' => implode(',', self::HOURLY),
                'daily' => implode(',', self::DAILY),
                'timezone' => config('services.open_meteo.timezone', 'Asia/Manila'),
                'forecast_days' => 7,
            ])
            ->throw();

        $json = $response->json();

        if (! is_array($json)) {
            throw new \RuntimeException('Open-Meteo returned an invalid response.');
        }

        return $json;
    }

    private function fetchBarangayWeather(string $barangay, float $latitude, float $longitude): array
    {
        try {
            $payload = $this->requestForecast($latitude, $longitude);

            return $this->summarize($barangay, $latitude, $longitude, $payload);
        } catch (Throwable $exception) {
            Log::warning('Lian barangay weather fetch failed.', [
                'barangay' => $barangay,
                'message' => $exception->getMessage(),
            ]);

            return [
                'barangay' => $barangay,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'source' => 'Open-Meteo Forecast API',
                'source_url' => 'https://open-meteo.com/',
                'source_credit' => 'Weather forecast by Open-Meteo; interpreted by iClimate.',
                'status' => 'unavailable',
                'stale' => true,
                'message' => 'Live barangay weather could not be refreshed. Use stored iClimate risk data until the next scheduled update.',
                'observed_at' => null,
                'rainfall_status' => null,
                'rainfall_now_mm' => null,
                'rainfall_24h_mm' => null,
                'rainfall_7d_mm' => null,
                'precip_probability_percent' => null,
                'temperature_c' => null,
                'humidity_percent' => null,
                'wind_speed_kmh' => null,
                'fetched_at' => now()->toDateTimeString(),
            ];
        }
    }

    private function summarize(string $barangay, float $latitude, float $longitude, array $payload): array
    {
        $current = $payload['current'] ?? [];
        $hourly = $payload['hourly'] ?? [];
        $daily = $payload['daily'] ?? [];

        $rain24h = $this->sumFirst($hourly['precipitation'] ?? $hourly['rain'] ?? [], 24);
        $rain7d = $this->sum($daily['precipitation_sum'] ?? $daily['rain_sum'] ?? []);
        $probability = $this->max($daily['precipitation_probability_max'] ?? $hourly['precipitation_probability'] ?? []);

        return [
            'barangay' => $barangay,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'source' => 'Open-Meteo Forecast API',
            'source_url' => 'https://open-meteo.com/',
            'source_credit' => 'Weather forecast by Open-Meteo; interpreted by iClimate.',
            'status' => 'fresh',
            'stale' => false,
            'message' => 'Forecast is refreshed routinely from barangay coordinates. Treat it as decision support, not a rain-gauge reading.',
            'observed_at' => $current['time'] ?? null,
            'rainfall_status' => $this->rainfallStatus($rain24h, $rain7d),
            'rainfall_now_mm' => $this->number($current['precipitation'] ?? $current['rain'] ?? null),
            'rainfall_24h_mm' => $rain24h,
            'rainfall_7d_mm' => $rain7d,
            'precip_probability_percent' => $probability,
            'temperature_c' => $this->number($current['temperature_2m'] ?? null),
            'humidity_percent' => $this->number($current['relative_humidity_2m'] ?? null),
            'wind_speed_kmh' => $this->number($current['wind_speed_10m'] ?? null),
            'fetched_at' => now()->toDateTimeString(),
        ];
    }

    private function rainfallStatus(?float $rain24h, ?float $rain7d): string
    {
        $rain24h ??= 0.0;
        $rain7d ??= 0.0;

        return match (true) {
            $rain24h >= 80 || $rain7d >= 180 => 'High rainfall',
            $rain24h >= 25 || $rain7d >= 70 => 'Adequate rainfall',
            default => 'Low rainfall',
        };
    }

    private function refreshMinutes(): int
    {
        return max(10, (int) config('services.open_meteo.refresh_minutes', 30));
    }

    private function normalizeBarangay(string $barangay): string
    {
        $value = strtolower($barangay);
        $value = str_replace(['barangay', 'brgy', '(pob.)', 'pob.', 'poblacion', '-', '.'], ' ', $value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?: '';

        return trim(preg_replace('/\s+/', ' ', $value) ?: '');
    }

    private function sumFirst(array $values, int $limit): ?float
    {
        return $this->sum(array_slice($values, 0, $limit));
    }

    private function sum(array $values): ?float
    {
        $numbers = array_values(array_filter($values, fn ($value) => is_numeric($value)));

        return $numbers === [] ? null : round(array_sum(array_map('floatval', $numbers)), 2);
    }

    private function max(array $values): ?float
    {
        $numbers = array_values(array_filter($values, fn ($value) => is_numeric($value)));

        return $numbers === [] ? null : round(max(array_map('floatval', $numbers)), 2);
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }
}
