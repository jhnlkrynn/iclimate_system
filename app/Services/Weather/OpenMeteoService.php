<?php

namespace App\Services\Weather;

use App\Models\ExternalWeatherData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class OpenMeteoService
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
        'temperature_2m',
        'relative_humidity_2m',
        'precipitation_probability',
        'precipitation',
        'rain',
        'wind_speed_10m',
        'soil_temperature_0cm',
        'soil_moisture_0_to_1cm',
        'soil_moisture_1_to_3cm',
    ];

    private const DAILY = [
        'weather_code',
        'temperature_2m_max',
        'temperature_2m_min',
        'precipitation_sum',
        'rain_sum',
        'precipitation_probability_max',
        'wind_speed_10m_max',
        'et0_fao_evapotranspiration',
        'sunrise',
        'sunset',
    ];

    public function fetchForecast(bool $force = false): array
    {
        if (! $force && Cache::has('open-meteo:last-fetch-result')) {
            return Cache::get('open-meteo:last-fetch-result');
        }

        try {
            $payload = $this->requestForecast();
            $records = $this->storeForecast($payload);

            $result = [
                'ok' => true,
                'source' => 'Open-Meteo',
                'freshness' => 'fresh',
                'records' => $records,
                'records_saved' => $records->count(),
                'fetched_at' => now(),
                'message' => 'The latest seven-day forecast was retrieved successfully.',
            ];

            Cache::put('open-meteo:last-fetch-result', $result, now()->addMinutes((int) config('services.open_meteo.refresh_minutes', 10)));

            return $result;
        } catch (Throwable $exception) {
            Log::warning('Open-Meteo forecast fetch failed.', [
                'message' => $exception->getMessage(),
            ]);

            $fallback = $this->latestStoredForecast();

            return [
                'ok' => false,
                'source' => 'Open-Meteo',
                'freshness' => $fallback->first()?->freshnessLabel() ?? 'unavailable',
                'records' => $fallback,
                'records_saved' => 0,
                'fetched_at' => $fallback->first()?->fetched_at,
                'message' => 'The online forecast service is temporarily unavailable. The system is using the last successful weather update.',
                'error' => 'Unable to update weather data.',
            ];
        }
    }

    public function latestStoredForecast(): Collection
    {
        $latestFetch = ExternalWeatherData::query()
            ->where('source', 'Open-Meteo')
            ->max('fetched_at');

        if (! $latestFetch) {
            return collect();
        }

        return ExternalWeatherData::query()
            ->where('source', 'Open-Meteo')
            ->where('fetched_at', $latestFetch)
            ->orderBy('forecast_date')
            ->get();
    }

    private function requestForecast(): array
    {
        $baseUrl = rtrim((string) config('services.open_meteo.base_url'), '/');
        $response = Http::timeout((int) config('services.open_meteo.timeout', 10))
            ->retry(2, 300)
            ->acceptJson()
            ->get($baseUrl.'/forecast', [
                'latitude' => config('services.open_meteo.latitude'),
                'longitude' => config('services.open_meteo.longitude'),
                'current' => implode(',', self::CURRENT),
                'hourly' => implode(',', self::HOURLY),
                'daily' => implode(',', self::DAILY),
                'timezone' => config('services.open_meteo.timezone', 'Asia/Manila'),
                'forecast_days' => 7,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Open-Meteo returned HTTP '.$response->status());
        }

        $json = $response->json();

        if (! is_array($json) || ! isset($json['daily']['time']) || ! is_array($json['daily']['time'])) {
            throw new RuntimeException('Open-Meteo response is missing daily forecast data.');
        }

        return $json;
    }

    private function storeForecast(array $payload): Collection
    {
        $daily = $payload['daily'];
        $hourly = $payload['hourly'] ?? [];
        $fetchedAt = now();
        $records = collect();

        foreach ($daily['time'] as $index => $date) {
            $hourlyForDate = $this->hourlyForDate($hourly, (string) $date);

            $record = ExternalWeatherData::query()->updateOrCreate(
                [
                    'source' => 'Open-Meteo',
                    'forecast_date' => $date,
                    'forecast_time' => null,
                    'barangay_id' => null,
                ],
                [
                    'location_name' => config('services.open_meteo.location_name', 'Lian, Batangas'),
                    'latitude' => (float) config('services.open_meteo.latitude', 14.033),
                    'longitude' => (float) config('services.open_meteo.longitude', 120.650),
                    'weather_code' => $daily['weather_code'][$index] ?? null,
                    'temperature' => $this->average($hourlyForDate['temperature_2m'] ?? []),
                    'temperature_max' => $daily['temperature_2m_max'][$index] ?? null,
                    'temperature_min' => $daily['temperature_2m_min'][$index] ?? null,
                    'humidity' => $this->average($hourlyForDate['relative_humidity_2m'] ?? []),
                    'rainfall_mm' => $daily['precipitation_sum'][$index] ?? $daily['rain_sum'][$index] ?? null,
                    'precipitation_probability' => $daily['precipitation_probability_max'][$index] ?? null,
                    'wind_speed' => $daily['wind_speed_10m_max'][$index] ?? null,
                    'soil_temperature' => $this->average($hourlyForDate['soil_temperature_0cm'] ?? []),
                    'soil_moisture' => $this->average(array_merge(
                        $hourlyForDate['soil_moisture_0_to_1cm'] ?? [],
                        $hourlyForDate['soil_moisture_1_to_3cm'] ?? [],
                    )),
                    'evapotranspiration_mm' => $daily['et0_fao_evapotranspiration'][$index] ?? null,
                    'raw_response' => [
                        'daily' => collect($daily)->map(fn ($values) => is_array($values) ? ($values[$index] ?? null) : null)->all(),
                        'current' => $payload['current'] ?? [],
                        'hourly' => $hourlyForDate,
                        'source_payload_meta' => [
                            'latitude' => $payload['latitude'] ?? null,
                            'longitude' => $payload['longitude'] ?? null,
                            'timezone' => $payload['timezone'] ?? null,
                        ],
                    ],
                    'fetched_at' => $fetchedAt,
                ],
            );

            $records->push($record);
        }

        return $records;
    }

    private function hourlyForDate(array $hourly, string $date): array
    {
        $result = [];
        $times = $hourly['time'] ?? [];

        foreach ($times as $index => $time) {
            if (! str_starts_with((string) $time, $date)) {
                continue;
            }

            foreach (self::HOURLY as $key) {
                if (isset($hourly[$key][$index])) {
                    $result[$key][] = $hourly[$key][$index];
                }
            }
        }

        return $result;
    }

    private function average(array $values): ?float
    {
        $values = array_values(array_filter($values, fn ($value) => is_numeric($value)));

        return $values === [] ? null : round(array_sum($values) / count($values), 2);
    }
}
