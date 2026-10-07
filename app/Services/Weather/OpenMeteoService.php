<?php

namespace App\Services\Weather;

use App\Models\ExternalWeatherData;
use App\Support\CoordinateNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        $cacheKey = $this->providerCacheKey();

        if (! $force && is_array($cached = Cache::get($cacheKey))) {
            return $cached;
        }

        $lock = Cache::lock($cacheKey.':lock', 60);
        if (! $lock->get()) {
            return $this->fallbackResult('Another Open-Meteo refresh is already running.');
        }

        $startedAt = now();
        $clock = hrtime(true);

        try {
            // A second caller can arrive after the first caller acquired the
            // lock but before it released it. Recheck the shared result so it
            // does not make a second provider request.
            if (! $force && is_array($cached = Cache::get($cacheKey))) {
                return $cached;
            }
            $payload = $this->requestForecast();
            \App\Models\WeatherFetchLog::record($startedAt, (int) round((hrtime(true) - $clock) / 1_000_000), true);
            $records = $this->storeForecast($payload);

            $result = [
                'ok' => true,
                'source' => 'Open-Meteo',
                'source_name' => 'Open-Meteo Forecast API',
                'source_url' => 'https://open-meteo.com/',
                'source_note' => 'Online forecast data retrieved from Open-Meteo for iClimate decision support.',
                'freshness' => 'fresh',
                'records' => $records,
                'records_saved' => $records->count(),
                'fetched_at' => now(),
                'message' => 'The latest seven-day forecast was retrieved successfully.',
            ];

            $refreshMinutes = (int) config('services.open_meteo.refresh_minutes', 10);

            if ($refreshMinutes > 0) {
                Cache::put($cacheKey, $result, now()->addMinutes($refreshMinutes));
            }

            return $result;
        } catch (Throwable $exception) {
            // A failed request is logged here; a failure while saving (after a logged success) is not a provider failure.
            if (! \App\Models\WeatherFetchLog::query()->where('started_at', $startedAt)->exists()) {
                \App\Models\WeatherFetchLog::record($startedAt, (int) round((hrtime(true) - $clock) / 1_000_000), false, $exception->getMessage());
            }
            Log::warning('Open-Meteo forecast fetch failed.', [
                'message' => $exception->getMessage(),
            ]);

            $fallback = $this->latestStoredForecast();

            return [
                'ok' => false,
                'source' => 'Open-Meteo',
                'source_name' => 'Open-Meteo Forecast API',
                'source_url' => 'https://open-meteo.com/',
                'source_note' => 'Stored fallback from the latest successful Open-Meteo update.',
                'freshness' => $fallback->first()?->freshnessLabel() ?? 'unavailable',
                'records' => $fallback,
                'records_saved' => 0,
                'fetched_at' => $fallback->first()?->fetched_at,
                'message' => 'Latest weather is temporarily unavailable. Showing the last weather update.',
                'error' => 'Unable to update weather data.',
            ];
        } finally {
            rescue(fn () => $lock->release(), report: false);
        }
    }

    private function providerCacheKey(): string
    {
        return 'open-meteo:forecast:v2:'.sha1(implode('|', [
            CoordinateNormalizer::pair(
                (float) config('services.open_meteo.latitude', \App\Support\LianLocation::LATITUDE),
                (float) config('services.open_meteo.longitude', \App\Support\LianLocation::LONGITUDE),
            ),
            config('services.open_meteo.timezone', 'Asia/Manila'),
            (int) config('services.open_meteo.forecast_days', 16),
        ]));
    }

    private function fallbackResult(string $message): array
    {
        $fallback = $this->latestStoredForecast();

        return [
            'ok' => false,
            'source' => 'Open-Meteo',
            'source_name' => 'Open-Meteo Forecast API',
            'source_url' => 'https://open-meteo.com/',
            'source_note' => 'Stored fallback from the latest successful Open-Meteo update.',
            'freshness' => $fallback->first()?->freshnessLabel() ?? 'unavailable',
            'records' => $fallback,
            'records_saved' => 0,
            'fetched_at' => $fallback->first()?->fetched_at,
            'message' => $message.' Showing the last weather update where available.',
            'error' => 'Unable to update weather data.',
        ];
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
                'latitude' => CoordinateNormalizer::format((float) config('services.open_meteo.latitude')),
                'longitude' => CoordinateNormalizer::format((float) config('services.open_meteo.longitude')),
                'current' => implode(',', self::CURRENT),
                'hourly' => implode(',', self::HOURLY),
                'daily' => implode(',', self::DAILY),
                'timezone' => config('services.open_meteo.timezone', 'Asia/Manila'),
                'forecast_days' => (int) config('services.open_meteo.forecast_days', 16),
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
        $dates = array_values(array_map('strval', $daily['time'] ?? []));
        $existing = ExternalWeatherData::query()
            ->where('source', 'Open-Meteo')
            ->whereIn('forecast_date', $dates)
            ->whereNull('forecast_time')
            ->whereNull('barangay_id')
            ->get()
            ->keyBy(fn (ExternalWeatherData $record): string => $record->forecast_date?->toDateString() ?? '');
        $rows = [];
        $meaningfulChange = false;
        $now = now();

        foreach ($daily['time'] as $index => $date) {
            $hourlyForDate = $this->hourlyForDate($hourly, (string) $date);
            $raw = [
                'daily' => collect($daily)->map(fn ($values) => is_array($values) ? ($values[$index] ?? null) : null)->all(),
                'current' => $payload['current'] ?? [], 'hourly' => $hourlyForDate,
                'source_payload_meta' => ['source_name' => 'Open-Meteo Forecast API', 'source_url' => 'https://open-meteo.com/',
                    'latitude' => $payload['latitude'] ?? null, 'longitude' => $payload['longitude'] ?? null, 'timezone' => $payload['timezone'] ?? null],
            ];
            $attributes = [
                'source' => 'Open-Meteo', 'location_name' => config('services.open_meteo.location_name', 'Lian, Batangas'),
                'barangay_id' => null, 'latitude' => (float) config('services.open_meteo.latitude', \App\Support\LianLocation::LATITUDE),
                'longitude' => (float) config('services.open_meteo.longitude', \App\Support\LianLocation::LONGITUDE), 'forecast_date' => $date, 'forecast_time' => null,
                'weather_code' => $daily['weather_code'][$index] ?? null, 'temperature' => $this->average($hourlyForDate['temperature_2m'] ?? []),
                'temperature_max' => $daily['temperature_2m_max'][$index] ?? null, 'temperature_min' => $daily['temperature_2m_min'][$index] ?? null,
                'humidity' => $this->average($hourlyForDate['relative_humidity_2m'] ?? []), 'rainfall_mm' => $daily['precipitation_sum'][$index] ?? $daily['rain_sum'][$index] ?? null,
                'precipitation_probability' => $daily['precipitation_probability_max'][$index] ?? null, 'wind_speed' => $daily['wind_speed_10m_max'][$index] ?? null,
                'soil_temperature' => $this->average($hourlyForDate['soil_temperature_0cm'] ?? []),
                'soil_moisture' => $this->average(array_merge($hourlyForDate['soil_moisture_0_to_1cm'] ?? [], $hourlyForDate['soil_moisture_1_to_3cm'] ?? [])),
                'evapotranspiration_mm' => $daily['et0_fao_evapotranspiration'][$index] ?? null, 'raw_response' => $raw, 'fetched_at' => $fetchedAt,
            ];
            $record = $existing->get((string) $date);
            if ($record) {
                $record->fill($attributes);
                // Bulk SQL bypasses Eloquent's saved event, so materialize the dirty set first and let the
                // existing numeric/JSON equivalence rules decide whether this is a real weather change.
                $record->syncChanges();
                $meaningfulChange = $meaningfulChange || \App\Support\CacheScope::isMeaningfulChange($record);
                $attributes['id'] = $record->getKey();
                $attributes['created_at'] = $record->getRawOriginal('created_at') ?: $now;
            } else {
                $meaningfulChange = true;
                $attributes['created_at'] = $now;
            }
            $attributes['updated_at'] = $now;
            $rows[] = [...$attributes, 'raw_response' => json_encode($raw, JSON_THROW_ON_ERROR)];
            $records->push($record ?: new ExternalWeatherData($attributes));
        }

        try {
            DB::transaction(function () use ($rows): void {
                $updates = array_values(array_filter($rows, fn (array $row): bool => isset($row['id'])));
                $inserts = array_values(array_filter($rows, fn (array $row): bool => ! isset($row['id'])));
                $columns = ['source', 'location_name', 'barangay_id', 'latitude', 'longitude', 'forecast_date', 'forecast_time', 'weather_code', 'temperature', 'temperature_max', 'temperature_min', 'humidity', 'rainfall_mm', 'precipitation_probability', 'wind_speed', 'soil_temperature', 'soil_moisture', 'evapotranspiration_mm', 'raw_response', 'fetched_at', 'updated_at'];
                if ($updates !== []) {
                    DB::table('external_weather_data')->upsert($updates, ['id'], $columns);
                }
                if ($inserts !== []) {
                    DB::table('external_weather_data')->insert($inserts);
                }
            });
            if ($meaningfulChange) {
                \App\Support\CacheScope::changed(ExternalWeatherData::class);
            }
        } catch (Throwable $exception) {
            Log::warning('Open-Meteo forecast bulk persistence failed; retrying rows individually.', ['message' => $exception->getMessage()]);
            foreach ($rows as $row) {
                $key = ['source' => $row['source'], 'forecast_date' => $row['forecast_date'], 'forecast_time' => null, 'barangay_id' => null];
                $values = $row;
                unset($values['id'], $values['created_at'], $values['updated_at']);
                ExternalWeatherData::query()->updateOrCreate($key, $values);
            }
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
