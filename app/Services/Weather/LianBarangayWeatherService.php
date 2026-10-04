<?php

namespace App\Services\Weather;

use App\Support\LianBarangays;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class LianBarangayWeatherService
{
    public const CACHE_MINUTES = 10;

    public const STALE_AFTER_HOURS = 6;

    private const CURRENT = [
        'temperature_2m',
        'relative_humidity_2m',
        'precipitation',
        'rain',
        'weather_code',
        'is_day',
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
            $canonical = LianBarangays::canonicalName($barangay);
            if ($canonical !== null) {
                $weather[$canonical] = $this->forBarangay($canonical, $latitude, $longitude, $refresh);
            }
        }

        return $weather;
    }

    public function forBarangay(string $barangay, float $latitude, float $longitude, bool $refresh = false): array
    {
        $cacheKey = $this->cacheKey($barangay);
        $lastSuccessfulKey = $cacheKey.'.last-successful';

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        if (! $refresh && is_array($cached = Cache::get($cacheKey))) {
            return $this->withFreshness($cached);
        }

        $weather = $this->fetchBarangayWeather($barangay, $latitude, $longitude);
        if (($weather['status'] ?? null) === 'fresh') {
            Cache::put($cacheKey, $weather, now()->addMinutes($this->refreshMinutes()));
            Cache::put($lastSuccessfulKey, $weather, now()->addHours(self::STALE_AFTER_HOURS));

            return $weather;
        }

        if (is_array($lastSuccessful = Cache::get($lastSuccessfulKey))) {
            return $this->withFreshness($lastSuccessful, true);
        }

        Cache::put($cacheKey, $weather, now()->addMinutes(2));

        return $weather;
    }

    /** Resolve one canonical Lian barangay to its polygon-verified centroid. */
    public function latestForBarangay(string $barangay, bool $refresh = false): array
    {
        $canonical = LianBarangays::canonicalName($barangay);
        if ($canonical === null) {
            throw new \InvalidArgumentException('Barangay must be one of the official Lian barangays.');
        }

        [$latitude, $longitude] = LianBarangays::coordinateFor($canonical);

        return $this->forBarangay($canonical, $latitude, $longitude, $refresh);
    }

    /** Read prepared weather without making an online request during page rendering. */
    public function cachedForBarangay(?string $barangay): ?array
    {
        $canonical = LianBarangays::canonicalName($barangay);
        if ($canonical === null) {
            return null;
        }

        $cacheKey = $this->cacheKey($canonical);
        $weather = Cache::get($cacheKey) ?? Cache::get($cacheKey.'.last-successful');

        return is_array($weather) ? $this->withFreshness($weather) : null;
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
                'provider' => 'Open-Meteo',
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
                'weather_code' => null,
                'is_day' => null,
                'condition' => null,
                'forecast_period' => 'next_24_hours',
                'next_refresh_at' => now()->addMinutes($this->refreshMinutes())->toIso8601String(),
                'fetched_at' => now()->toDateTimeString(),
            ];
        }
    }

    private function summarize(string $barangay, float $latitude, float $longitude, array $payload): array
    {
        $current = $payload['current'] ?? [];
        $hourly = $payload['hourly'] ?? [];
        $daily = $payload['daily'] ?? [];

        $rainSeries = $hourly['precipitation'] ?? $hourly['rain'] ?? [];
        $probabilitySeries = $hourly['precipitation_probability'] ?? [];
        $rain24h = $this->sum($this->next24Hours($hourly['time'] ?? [], $rainSeries, $current['time'] ?? null))
            ?? $this->number(($daily['precipitation_sum'] ?? $daily['rain_sum'] ?? [])[0] ?? null);
        $rain7d = $this->sum($daily['precipitation_sum'] ?? $daily['rain_sum'] ?? []);
        $probability = $this->max($this->next24Hours($hourly['time'] ?? [], $probabilitySeries, $current['time'] ?? null))
            ?? $this->number(($daily['precipitation_probability_max'] ?? [])[0] ?? null);
        $code = isset($current['weather_code']) ? (int) $current['weather_code'] : null;
        $isDay = isset($current['is_day']) ? (bool) $current['is_day'] : null;
        $fetchedAt = now();

        return [
            'barangay' => $barangay,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'source' => 'Open-Meteo Forecast API',
            'provider' => 'Open-Meteo',
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
            'rain_probability_percent' => $probability,
            'rainfall_mm' => $rain24h,
            'temperature_c' => $this->number($current['temperature_2m'] ?? null),
            'humidity_percent' => $this->number($current['relative_humidity_2m'] ?? null),
            'wind_speed_kmh' => $this->number($current['wind_speed_10m'] ?? null),
            // Raw Open-Meteo WMO weather code + day/night flag — already
            // fetched above, exposed here so callers can derive a
            // condition/icon (e.g. via WeatherIconMapper) without a second
            // API call. Not used for risk scoring.
            'weather_code' => $code,
            'is_day' => $isDay,
            'condition' => app(WeatherIconMapper::class)->map($code, $isDay)['condition'],
            'forecast_period' => 'next_24_hours',
            'fetched_at' => $fetchedAt->toIso8601String(),
            'next_refresh_at' => $fetchedAt->copy()->addMinutes($this->refreshMinutes())->toIso8601String(),
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
        return max(self::CACHE_MINUTES, (int) config('services.open_meteo.refresh_minutes', self::CACHE_MINUTES));
    }

    private function cacheKey(string $barangay): string
    {
        return 'weather-api:lian-barangay:'.Str::slug($barangay);
    }

    private function withFreshness(array $weather, bool $forceStale = false): array
    {
        $fetchedAt = $weather['fetched_at'] ?? null;
        $stale = $forceStale || ! $fetchedAt;

        if ($fetchedAt) {
            try {
                $stale = $stale || Carbon::parse($fetchedAt)->lt(now()->subMinutes($this->refreshMinutes()));
            } catch (Throwable) {
                $stale = true;
            }
        }

        if ($stale && ($weather['status'] ?? null) !== 'unavailable') {
            $weather['status'] = 'stale';
            $weather['stale'] = true;
            $weather['message'] = 'Weather update is temporarily delayed. Showing the last available barangay forecast.';
        }

        return $weather;
    }

    private function next24Hours(array $times, array $values, mixed $observedAt): array
    {
        if ($times === [] || count($times) !== count($values) || ! is_string($observedAt) || $observedAt === '') {
            return array_slice($values, 0, 24);
        }

        try {
            $start = Carbon::parse($observedAt, 'Asia/Manila')->startOfHour();
            $end = $start->copy()->addHours(24);
        } catch (Throwable) {
            return array_slice($values, 0, 24);
        }

        return collect($times)->mapWithKeys(fn ($time, $index) => [$index => $time])
            ->filter(function ($time) use ($start, $end): bool {
                try {
                    $hour = Carbon::parse($time, 'Asia/Manila');

                    return $hour->gte($start) && $hour->lt($end);
                } catch (Throwable) {
                    return false;
                }
            })
            ->keys()
            ->map(fn (int $index) => $values[$index] ?? null)
            ->filter(fn ($value) => is_numeric($value))
            ->values()
            ->all();
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
