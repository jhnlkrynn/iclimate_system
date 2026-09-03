<?php

namespace App\Services\Weather;

use App\Models\ExternalWeatherData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class FarmerDashboardWeatherService
{
    private const CURRENT_FIELDS = [
        'temperature_2m',
        'relative_humidity_2m',
        'apparent_temperature',
        'precipitation',
        'rain',
        'showers',
        'weather_code',
        'cloud_cover',
        'pressure_msl',
        'surface_pressure',
        'wind_speed_10m',
        'wind_direction_10m',
        'wind_gusts_10m',
        'is_day',
    ];

    private const HOURLY_FIELDS = [
        'temperature_2m',
        'relative_humidity_2m',
        'precipitation_probability',
        'precipitation',
        'rain',
        'weather_code',
    ];

    private const DAILY_FIELDS = [
        'weather_code',
        'temperature_2m_max',
        'temperature_2m_min',
        'apparent_temperature_max',
        'apparent_temperature_min',
        'sunrise',
        'sunset',
        'precipitation_sum',
        'rain_sum',
        'showers_sum',
        'precipitation_probability_max',
        'wind_speed_10m_max',
        'wind_gusts_10m_max',
    ];

    public function __construct(private readonly WeatherIconMapper $icons)
    {
    }

    public function current(bool $force = false): array
    {
        $cacheKey = $this->cacheKey();
        $lastSuccessfulKey = $cacheKey.'.last_successful';
        $ttl = now()->addMinutes(max(1, (int) config('services.weather.cache_minutes', 5)));

        if (! $force && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            $cached['cached'] = true;

            return $cached;
        }

        try {
            $payload = $this->requestOpenMeteo();
            $normalized = $this->normalizeOpenMeteo($payload);

            Cache::put($cacheKey, $normalized, $ttl);
            Cache::forever($lastSuccessfulKey, $normalized);
            $this->storeSnapshot($payload, $normalized);

            return $normalized;
        } catch (Throwable $exception) {
            Log::warning('Farmer dashboard weather fetch failed.', [
                'provider' => 'Open-Meteo',
                'message' => $exception->getMessage(),
            ]);

            $fallback = Cache::get($lastSuccessfulKey) ?? $this->latestStoredSnapshot();

            if (is_array($fallback)) {
                $fallback['cached'] = true;
                $fallback['stale'] = true;
                $fallback['message'] = 'Using latest available weather data.';

                return $fallback;
            }

            return $this->unavailable();
        }
    }

    public function guidance(array $weather): array
    {
        $current = $weather['current'] ?? [];
        $today = $weather['today'] ?? [];

        if (($current['humidity'] ?? 0) >= 85) {
            return [
                'title' => 'iClimate Weather Guidance',
                'message' => 'High humidity may increase fungal disease risk. Monitor rice crops closely for symptoms.',
            ];
        }

        if (($today['precipitation_probability'] ?? 0) >= 70 || ($today['rainfall'] ?? 0) >= 10) {
            return [
                'title' => 'iClimate Weather Guidance',
                'message' => 'Rain is likely today. Consider delaying fertilizer or pesticide application when possible.',
            ];
        }

        if (($current['wind_speed'] ?? 0) >= 20) {
            return [
                'title' => 'iClimate Weather Guidance',
                'message' => 'Wind conditions may reduce spraying effectiveness. Consider delaying chemical spraying.',
            ];
        }

        if (($current['temperature'] ?? 0) >= 34 && ($today['rainfall'] ?? 0) < 2) {
            return [
                'title' => 'iClimate Weather Guidance',
                'message' => 'Hot and dry conditions may increase water demand. Check soil moisture before irrigation.',
            ];
        }

        return [
            'title' => 'iClimate Weather Guidance',
            'message' => 'Weather conditions are within normal monitoring range. Keep checking advisories before field work.',
        ];
    }

    public function responsePayload(array $weather): array
    {
        return [
            ...$weather,
            'fetched_at' => $this->serializeTime($weather['fetched_at'] ?? null),
            'checked_at' => now($this->timezone())->toIso8601String(),
            'guidance' => $this->guidance($weather),
        ];
    }

    private function requestOpenMeteo(): array
    {
        $response = Http::timeout((int) config('services.open_meteo.timeout', 10))
            ->retry(2, 250)
            ->acceptJson()
            ->get(rtrim((string) config('services.open_meteo.base_url'), '/').'/forecast', [
                'latitude' => config('services.weather.latitude', config('services.open_meteo.latitude')),
                'longitude' => config('services.weather.longitude', config('services.open_meteo.longitude')),
                'current' => implode(',', self::CURRENT_FIELDS),
                'hourly' => implode(',', self::HOURLY_FIELDS),
                'daily' => implode(',', self::DAILY_FIELDS),
                'timezone' => $this->timezone(),
                'forecast_days' => (int) config('services.weather.forecast_days', 7),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Open-Meteo returned HTTP '.$response->status());
        }

        $payload = $response->json();

        if (! is_array($payload) || ! is_array($payload['current'] ?? null) || ! is_array($payload['daily'] ?? null)) {
            throw new RuntimeException('Open-Meteo response is missing current or daily weather data.');
        }

        return $payload;
    }

    private function normalizeOpenMeteo(array $payload): array
    {
        $current = $payload['current'] ?? [];
        $daily = $payload['daily'] ?? [];
        $weatherCode = $this->number($current['weather_code'] ?? $daily['weather_code'][0] ?? null);
        $isDay = isset($current['is_day']) ? (bool) $current['is_day'] : null;
        $mapped = $this->icons->map($weatherCode !== null ? (int) $weatherCode : null, $isDay);
        $fetchedAt = now($this->timezone());

        return [
            'success' => true,
            'provider' => 'Open-Meteo',
            'source' => 'Open-Meteo',
            'cached' => false,
            'stale' => false,
            'location' => [
                'name' => 'Lian',
                'province' => 'Batangas',
                'country' => 'Philippines',
                'latitude' => (float) config('services.weather.latitude', 14.033),
                'longitude' => (float) config('services.weather.longitude', 120.650),
                'timezone' => $this->timezone(),
            ],
            'current' => [
                'temperature' => $this->number($current['temperature_2m'] ?? null),
                'feels_like' => $this->number($current['apparent_temperature'] ?? null),
                'humidity' => $this->number($current['relative_humidity_2m'] ?? null),
                'precipitation' => $this->number($current['precipitation'] ?? null),
                'rain' => $this->number($current['rain'] ?? null),
                'showers' => $this->number($current['showers'] ?? null),
                'weather_code' => $weatherCode,
                'condition' => $mapped['condition'],
                'is_day' => $isDay,
                'cloud_cover' => $this->number($current['cloud_cover'] ?? null),
                'pressure_msl' => $this->number($current['pressure_msl'] ?? null),
                'surface_pressure' => $this->number($current['surface_pressure'] ?? null),
                'wind_speed' => $this->number($current['wind_speed_10m'] ?? null),
                'wind_direction' => $this->number($current['wind_direction_10m'] ?? null),
                'wind_gusts' => $this->number($current['wind_gusts_10m'] ?? null),
                'icon' => $mapped['icon'],
            ],
            'today' => [
                'temperature_max' => $this->number($daily['temperature_2m_max'][0] ?? null),
                'temperature_min' => $this->number($daily['temperature_2m_min'][0] ?? null),
                'feels_like_max' => $this->number($daily['apparent_temperature_max'][0] ?? null),
                'feels_like_min' => $this->number($daily['apparent_temperature_min'][0] ?? null),
                'rainfall' => $this->number($daily['precipitation_sum'][0] ?? $daily['rain_sum'][0] ?? null),
                'rain_sum' => $this->number($daily['rain_sum'][0] ?? null),
                'precipitation_sum' => $this->number($daily['precipitation_sum'][0] ?? null),
                'showers_sum' => $this->number($daily['showers_sum'][0] ?? null),
                'precipitation_probability' => $this->number($daily['precipitation_probability_max'][0] ?? null),
                'wind_speed_max' => $this->number($daily['wind_speed_10m_max'][0] ?? null),
                'wind_gusts_max' => $this->number($daily['wind_gusts_10m_max'][0] ?? null),
                'sunrise' => $daily['sunrise'][0] ?? null,
                'sunset' => $daily['sunset'][0] ?? null,
            ],
            'forecast' => $this->forecast($daily),
            'icon' => $mapped['icon'],
            'fetched_at' => $fetchedAt,
            'fetched_at_label' => $fetchedAt->format('M j, Y').' • '.$fetchedAt->format('g:i A'),
            'message' => 'Weather data fetched from Open-Meteo for Lian, Batangas.',
        ];
    }

    private function forecast(array $daily): array
    {
        $days = [];
        $times = array_slice($daily['time'] ?? [], 0, 7);

        foreach ($times as $index => $date) {
            $code = $this->number($daily['weather_code'][$index] ?? null);
            $mapped = $this->icons->map($code !== null ? (int) $code : null, true);
            $day = Carbon::parse((string) $date, $this->timezone());

            $days[] = [
                'date' => $day->toDateString(),
                'day' => $day->format('D'),
                'condition' => $mapped['condition'],
                'icon' => $mapped['icon'],
                'temperature_max' => $this->number($daily['temperature_2m_max'][$index] ?? null),
                'temperature_min' => $this->number($daily['temperature_2m_min'][$index] ?? null),
                'rainfall' => $this->number($daily['precipitation_sum'][$index] ?? $daily['rain_sum'][$index] ?? null),
                'precipitation_probability' => $this->number($daily['precipitation_probability_max'][$index] ?? null),
            ];
        }

        return $days;
    }

    private function storeSnapshot(array $payload, array $weather): void
    {
        $today = now($this->timezone())->toDateString();

        ExternalWeatherData::query()->updateOrCreate(
            [
                'source' => 'Open-Meteo',
                'forecast_date' => $today,
                'forecast_time' => null,
                'barangay_id' => null,
            ],
            [
                'location_name' => 'Lian, Batangas',
                'latitude' => (float) config('services.weather.latitude', 14.033),
                'longitude' => (float) config('services.weather.longitude', 120.650),
                'weather_code' => $weather['current']['weather_code'] ?? null,
                'temperature' => $weather['current']['temperature'] ?? null,
                'temperature_max' => $weather['today']['temperature_max'] ?? null,
                'temperature_min' => $weather['today']['temperature_min'] ?? null,
                'humidity' => $weather['current']['humidity'] ?? null,
                'rainfall_mm' => $weather['today']['rainfall'] ?? null,
                'precipitation_probability' => $weather['today']['precipitation_probability'] ?? null,
                'wind_speed' => $weather['current']['wind_speed'] ?? null,
                'raw_response' => $payload,
                'fetched_at' => $weather['fetched_at'],
            ],
        );
    }

    private function latestStoredSnapshot(): ?array
    {
        $record = ExternalWeatherData::query()
            ->where('source', 'Open-Meteo')
            ->latest('fetched_at')
            ->first();

        if (! $record) {
            return null;
        }

        $raw = is_array($record->raw_response) ? $record->raw_response : [];

        if (isset($raw['current'], $raw['daily'])) {
            $weather = $this->normalizeOpenMeteo($raw);
            $weather['cached'] = true;
            $weather['stale'] = true;
            $weather['fetched_at'] = $record->fetched_at?->timezone($this->timezone()) ?? now($this->timezone());
            $weather['fetched_at_label'] = $weather['fetched_at']->format('M j, Y').' • '.$weather['fetched_at']->format('g:i A');

            return $weather;
        }

        $mapped = $this->icons->map($record->weather_code, null);

        return [
            'success' => true,
            'provider' => 'Open-Meteo',
            'source' => 'Open-Meteo',
            'cached' => true,
            'stale' => true,
            'location' => [
                'name' => 'Lian',
                'province' => 'Batangas',
                'country' => 'Philippines',
                'latitude' => (float) $record->latitude,
                'longitude' => (float) $record->longitude,
                'timezone' => $this->timezone(),
            ],
            'current' => [
                'temperature' => $this->number($record->temperature),
                'feels_like' => null,
                'humidity' => $this->number($record->humidity),
                'precipitation' => null,
                'rain' => null,
                'weather_code' => $record->weather_code,
                'condition' => $mapped['condition'],
                'is_day' => null,
                'wind_speed' => $this->number($record->wind_speed),
                'wind_direction' => null,
                'cloud_cover' => null,
                'icon' => $mapped['icon'],
            ],
            'today' => [
                'temperature_max' => $this->number($record->temperature_max),
                'temperature_min' => $this->number($record->temperature_min),
                'rainfall' => $this->number($record->rainfall_mm),
                'precipitation_probability' => $this->number($record->precipitation_probability),
            ],
            'forecast' => [],
            'icon' => $mapped['icon'],
            'fetched_at' => $record->fetched_at?->timezone($this->timezone()) ?? now($this->timezone()),
            'fetched_at_label' => ($record->fetched_at?->timezone($this->timezone()) ?? now($this->timezone()))->format('M j, Y').' • '.($record->fetched_at?->timezone($this->timezone()) ?? now($this->timezone()))->format('g:i A'),
        ];
    }

    private function unavailable(): array
    {
        $mapped = $this->icons->map(null);

        return [
            'success' => false,
            'provider' => 'Open-Meteo',
            'source' => 'Open-Meteo',
            'cached' => false,
            'stale' => true,
            'location' => [
                'name' => 'Lian',
                'province' => 'Batangas',
                'country' => 'Philippines',
                'latitude' => (float) config('services.weather.latitude', 14.033),
                'longitude' => (float) config('services.weather.longitude', 120.650),
                'timezone' => $this->timezone(),
            ],
            'current' => [
                'temperature' => null,
                'feels_like' => null,
                'humidity' => null,
                'precipitation' => null,
                'rain' => null,
                'weather_code' => null,
                'condition' => $mapped['condition'],
                'is_day' => null,
                'wind_speed' => null,
                'wind_direction' => null,
                'cloud_cover' => null,
                'icon' => $mapped['icon'],
            ],
            'today' => [
                'temperature_max' => null,
                'temperature_min' => null,
                'rainfall' => null,
                'precipitation_probability' => null,
            ],
            'forecast' => [],
            'icon' => $mapped['icon'],
            'fetched_at' => null,
            'fetched_at_label' => 'Weather temporarily unavailable',
            'message' => 'Weather data temporarily unavailable.',
        ];
    }

    private function cacheKey(): string
    {
        return 'weather.current.lian.'.strtolower((string) config('services.weather.provider', 'openmeteo'));
    }

    private function timezone(): string
    {
        return (string) config('services.weather.timezone', config('services.open_meteo.timezone', 'Asia/Manila'));
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 1) : null;
    }

    private function serializeTime(mixed $time): ?string
    {
        if ($time instanceof Carbon) {
            return $time->toIso8601String();
        }

        return $time ? Carbon::parse($time, $this->timezone())->toIso8601String() : null;
    }
}
