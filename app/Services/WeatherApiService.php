<?php

namespace App\Services;

use App\Models\ExternalWeatherData;
use App\Services\Weather\WeatherIconMapper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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
        'is_day',
    ];

    private const HOURLY_FIELDS = [
        'relative_humidity_2m',
    ];

    public function __construct(
        private readonly WeatherIconMapper $icons,
    ) {}

    private const DAILY_FIELDS = [
        'weather_code',
        'temperature_2m_max',
        'temperature_2m_min',
        'precipitation_sum',
        'rain_sum',
        'precipitation_probability_max',
        'wind_speed_10m_max',
    ];

    public function forecast(
        bool $refresh = false,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $location = null,
    ): ?array {
        $latitude ??= (float) config('services.weather.latitude', config('services.open_meteo.latitude', config('services.weather_api.latitude', 14.04)));
        $longitude ??= (float) config('services.weather.longitude', config('services.open_meteo.longitude', config('services.weather_api.longitude', 120.65)));
        $location ??= (string) config('services.weather.location_name', config('services.weather_api.location_name', 'Lian, Batangas'));
        $cacheKey = 'weather-api:forecast:'.Str::slug($location).':'.sha1(number_format($latitude, 5).','.number_format($longitude, 5));
        $latestCacheKey = $cacheKey.':latest';
        $ttlMinutes = max(30, min(60, (int) config('services.weather_api.refresh_minutes', 45)));
        $ttl = now()->addMinutes($ttlMinutes);

        if ($refresh) {
            $fresh = $this->fetchForecast($latitude, $longitude, $location);

            if ($fresh !== null) {
                Cache::put($cacheKey, $fresh, $ttl);
                Cache::forever($latestCacheKey, $fresh);

                return $fresh;
            }

            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                return $cached;
            }

            return $this->cachedFallback(Cache::get($latestCacheKey))
                ?? $this->storedFallback($latitude, $longitude);
        }

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $fresh = $this->fetchForecast($latitude, $longitude, $location);

        if ($fresh !== null) {
            Cache::put($cacheKey, $fresh, $ttl);
            Cache::forever($latestCacheKey, $fresh);

            return $fresh;
        }

        return $this->cachedFallback(Cache::get($latestCacheKey))
            ?? $this->storedFallback($latitude, $longitude);
    }

    private function cachedFallback(mixed $weather): ?array
    {
        if (! is_array($weather)) {
            return null;
        }

        $weather['is_cached_fallback'] = true;
        $weather['freshness'] = 'latest cached update';
        $weather['source_name'] = 'Open-Meteo Forecast API (latest cached)';

        return $weather;
    }

    private function fetchForecast(float $latitude, float $longitude, string $location): ?array
    {
        if (! config('services.weather_api.enabled', true)) {
            return null;
        }

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
                    'hourly' => implode(',', self::HOURLY_FIELDS),
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
            return $this->normalizeOpenMeteo($response->json(), $forecastDays, $location);
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeOpenMeteo(mixed $payload, int $requestedDays, string $location): array
    {
        if (! is_array($payload) || ! is_array($payload['daily'] ?? null)) {
            throw new RuntimeException('Open-Meteo response is missing daily weather data.');
        }

        $current = is_array($payload['current'] ?? null) ? $payload['current'] : [];
        $daily = $payload['daily'];
        $hourly = is_array($payload['hourly'] ?? null) ? $payload['hourly'] : [];
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
        $humidity = array_map(
            fn (string $date): ?float => $this->hourlyAverageForDate($hourly, 'relative_humidity_2m', $date),
            $dates,
        );
        $days = max(count($dates), 1);
        $currentCode = isset($current['weather_code']) ? (int) $current['weather_code'] : (isset($daily['weather_code'][0]) ? (int) $daily['weather_code'][0] : null);
        $currentCondition = $this->icons->map($currentCode, isset($current['is_day']) ? (bool) $current['is_day'] : true);

        return [
            'source' => 'Open-Meteo',
            'source_name' => 'Open-Meteo Forecast API',
            'source_url' => 'https://open-meteo.com/',
            'source_credit' => 'Live and forecast weather data from Open-Meteo; official Philippine advisories remain sourced separately from DOST-PAGASA.',
            'source_role' => 'Live/current weather and frequent dashboard updates',
            'location' => $location,
            'current_time' => (string) data_get($current, 'time', now((string) config('services.weather.timezone', 'Asia/Manila'))->toDateTimeString()),
            'current_condition' => $currentCondition['condition'],
            'current_icon' => $currentCondition['icon'],
            'weather_code' => $currentCode,
            // null (never 0) when the source genuinely has no value for this
            // field — 0 is only ever shown when Open-Meteo actually reports
            // zero. See numericOrNull().
            'current_rainfall_mm' => $this->numericOrNull(data_get($current, 'precipitation') ?? data_get($current, 'rain')),
            'current_temperature_c' => $this->numericOrNull(data_get($current, 'temperature_2m') ?? ($temperature[0] ?? null)),
            'humidity_percent' => $this->numericOrNull(data_get($current, 'relative_humidity_2m')),
            'wind_speed_kmh' => $this->numericOrNull(data_get($current, 'wind_speed_10m') ?? ($wind[0] ?? null)),
            'forecast_days' => $days,
            'daily_rainfall_mm' => round(array_sum($rain) / $days, 2),
            'monthly_rainfall_estimate_mm' => round((array_sum($rain) / $days) * 30, 2),
            'temperature_c' => round($this->average($temperature), 2),
            'precip_probability_percent' => round($this->average($probability), 2),
            'fetched_at' => now((string) config('services.weather.timezone', 'Asia/Manila'))->toIso8601String(),
            'freshness' => 'fresh',
            'is_cached_fallback' => false,
            'daily_series' => [
                'labels' => $dates,
                'rainfall' => array_map(fn ($value) => round((float) $value, 2), $rain),
                'temperature' => array_map(fn ($value) => round((float) $value, 2), $temperature),
                'temperatureMax' => array_map(fn ($value) => round((float) $value, 2), $temperatureMax),
                'temperatureMin' => array_map(fn ($value) => round((float) $value, 2), $temperatureMin),
                'humidity' => $humidity,
                'windSpeed' => array_map(fn ($value) => round((float) $value, 2), $wind),
                'precipitationProbability' => array_map(fn ($value) => round((float) $value, 2), $probability),
                'conditions' => array_map(
                    fn ($code) => $this->icons->map(is_numeric($code) ? (int) $code : null, true)['condition'],
                    array_slice($daily['weather_code'] ?? [], 0, $days),
                ),
                'icons' => array_map(
                    fn ($code) => $this->icons->map(is_numeric($code) ? (int) $code : null, true)['icon'],
                    array_slice($daily['weather_code'] ?? [], 0, $days),
                ),
            ],
        ];
    }

    private function storedFallback(float $latitude, float $longitude): ?array
    {
        $locationQuery = ExternalWeatherData::query()
            ->where('source', 'Open-Meteo')
            ->whereBetween('latitude', [$latitude - 0.00001, $latitude + 0.00001])
            ->whereBetween('longitude', [$longitude - 0.00001, $longitude + 0.00001]);
        $latestFetch = (clone $locationQuery)->max('fetched_at');

        if (! $latestFetch) {
            return null;
        }

        $records = $locationQuery
            ->where('fetched_at', $latestFetch)
            ->orderBy('forecast_date')
            ->get();

        if ($records->isEmpty()) {
            return null;
        }

        $current = $records->first(fn (ExternalWeatherData $record) => $record->forecast_date?->isToday()) ?? $records->first();
        $mapped = $this->icons->map(is_numeric($current->weather_code) ? (int) $current->weather_code : null, true);
        $days = $records->count();

        return [
            'source' => 'Open-Meteo',
            'source_name' => 'Open-Meteo Forecast API (saved fallback)',
            'source_url' => 'https://open-meteo.com/',
            'source_credit' => 'Last successful Open-Meteo forecast saved by iClimate; official Philippine warnings remain sourced from DOST-PAGASA.',
            'source_role' => 'Cached fallback weather data',
            'location' => $current->location_name ?: 'Lian, Batangas',
            'current_time' => $current->forecast_date?->toDateString(),
            'current_condition' => $mapped['condition'],
            'current_icon' => $mapped['icon'],
            'weather_code' => $current->weather_code,
            'current_rainfall_mm' => $this->numericOrNull($current->rainfall_mm),
            'current_temperature_c' => $this->numericOrNull($current->temperature),
            'humidity_percent' => $this->numericOrNull($current->humidity),
            'wind_speed_kmh' => $this->numericOrNull($current->wind_speed),
            'forecast_days' => $days,
            'daily_rainfall_mm' => round((float) $records->avg('rainfall_mm'), 2),
            'monthly_rainfall_estimate_mm' => round((float) $records->avg('rainfall_mm') * 30, 2),
            'temperature_c' => round((float) $records->avg('temperature'), 2),
            'precip_probability_percent' => round((float) $records->avg('precipitation_probability'), 2),
            'fetched_at' => $current->fetched_at?->toIso8601String(),
            'freshness' => $current->freshnessLabel(),
            'is_cached_fallback' => true,
            'daily_series' => [
                'labels' => $records->map(fn (ExternalWeatherData $record) => $record->forecast_date?->toDateString())->all(),
                'rainfall' => $records->map(fn (ExternalWeatherData $record) => round((float) ($record->rainfall_mm ?? 0), 2))->all(),
                'temperature' => $records->map(fn (ExternalWeatherData $record) => round((float) ($record->temperature ?? 0), 2))->all(),
                'temperatureMax' => $records->map(fn (ExternalWeatherData $record) => is_numeric($record->temperature_max) ? round((float) $record->temperature_max, 2) : null)->all(),
                'temperatureMin' => $records->map(fn (ExternalWeatherData $record) => is_numeric($record->temperature_min) ? round((float) $record->temperature_min, 2) : null)->all(),
                'humidity' => $records->map(fn (ExternalWeatherData $record) => round((float) ($record->humidity ?? 0), 2))->all(),
                'windSpeed' => $records->map(fn (ExternalWeatherData $record) => round((float) ($record->wind_speed ?? 0), 2))->all(),
                'precipitationProbability' => $records->map(fn (ExternalWeatherData $record) => is_numeric($record->precipitation_probability) ? round((float) $record->precipitation_probability, 2) : null)->all(),
                'conditions' => $records->map(fn (ExternalWeatherData $record) => $this->icons->map(is_numeric($record->weather_code) ? (int) $record->weather_code : null, true)['condition'])->all(),
                'icons' => $records->map(fn (ExternalWeatherData $record) => $this->icons->map(is_numeric($record->weather_code) ? (int) $record->weather_code : null, true)['icon'])->all(),
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

    /**
     * Preserves "genuinely unavailable" as null instead of silently
     * defaulting a missing reading to 0 — a missing rainfall/temperature/
     * humidity/wind reading must never be displayed as if the model/API
     * actually reported zero.
     */
    private function numericOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function average(array $values): float
    {
        $values = array_map('floatval', $values);

        return $values === [] ? 0.0 : array_sum($values) / count($values);
    }

    private function hourlyAverageForDate(array $hourly, string $key, string $date): ?float
    {
        $values = [];

        foreach (($hourly['time'] ?? []) as $index => $time) {
            $value = $hourly[$key][$index] ?? null;

            if (str_starts_with((string) $time, $date) && is_numeric($value)) {
                $values[] = (float) $value;
            }
        }

        return $values === [] ? null : round(array_sum($values) / count($values), 2);
    }
}
