<?php

namespace App\Services\Weather;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class PagasaWisObservationService
{
    public function latest(bool $refresh = false): array
    {
        if (! (bool) config('services.pagasa_wis.enabled', true)) {
            return $this->unavailable('PAGASA WIS/OAPI observation feed is disabled.');
        }

        $cacheKey = 'pagasa-wis:latest-observation';
        $lastSuccessKey = $cacheKey.':last-success';

        if (! $refresh) {
            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                return $cached;
            }
        }

        try {
            $observation = $this->fetch();
            Cache::put($cacheKey, $observation, now()->addSeconds($this->cacheSeconds()));
            Cache::put($lastSuccessKey, $observation, now()->addHours(6));

            return $observation;
        } catch (Throwable $exception) {
            Log::warning('PAGASA WIS/OAPI observation fetch failed.', ['message' => $exception->getMessage()]);

            $lastSuccess = Cache::get($lastSuccessKey);

            if (is_array($lastSuccess)) {
                return array_merge($lastSuccess, [
                    'status' => 'stale',
                    'stale' => true,
                    'message' => 'PAGASA WIS/OAPI refresh failed. iClimate is retaining the latest official cached observation and labeling it as outdated.',
                    'fetch_failed_at' => now()->toIso8601String(),
                ]);
            }

            return $this->unavailable('PAGASA WIS/OAPI observation feed could not be refreshed.');
        }
    }

    public function cacheSeconds(): int
    {
        return max(30, (int) config('services.pagasa_wis.cache_seconds', 60));
    }

    private function fetch(): array
    {
        $baseUrl = rtrim((string) config('services.pagasa_wis.base_url'), '/');
        $collection = rawurlencode((string) config('services.pagasa_wis.collection'));
        $query = [
            'f' => 'json',
            'limit' => 100,
            'sortby' => '-phenomenonTime',
        ];
        $stationId = trim((string) config('services.pagasa_wis.station_id', ''));

        if ($stationId !== '') {
            $query['wigos_station_identifier'] = $stationId;
        } else {
            $query['bbox'] = (string) config('services.pagasa_wis.bbox');
        }

        $response = Http::timeout((int) config('services.pagasa_wis.timeout', 12))
            ->retry(2, 300)
            ->acceptJson()
            ->get("{$baseUrl}/collections/{$collection}/items", $query)
            ->throw();

        $json = $response->json();

        if (! is_array($json)) {
            throw new \RuntimeException('PAGASA WIS/OAPI returned an invalid response.');
        }

        $observation = $this->normalize($json);

        if (! $this->hasNumericWeather($observation)) {
            throw new \RuntimeException('PAGASA WIS/OAPI response did not include usable numeric weather observations.');
        }

        return $observation;
    }

    private function normalize(array $json): array
    {
        $features = collect($json['features'] ?? []);
        $values = [];
        $station = null;
        $observedAt = null;

        foreach ($features as $feature) {
            if (! is_array($feature)) {
                continue;
            }

            $properties = $feature['properties'] ?? [];
            if (! is_array($properties)) {
                continue;
            }

            $station ??= $this->stationFrom($feature, $properties);
            $observedAt ??= $properties['phenomenonTime'] ?? $properties['datetime'] ?? $properties['reportTime'] ?? null;
            $values = array_merge($values, $this->valuesFromProperties($properties));
        }

        $observedAt = $this->dateTimeString($observedAt);
        $stale = $observedAt
            ? Carbon::parse($observedAt)->lt(now()->subMinutes((int) config('services.pagasa_wis.stale_after_minutes', 30)))
            : true;

        return [
            'status' => $stale ? 'stale' : 'fresh',
            'stale' => $stale,
            'message' => $stale
                ? 'Official PAGASA WIS/OAPI observation is available but older than the configured freshness window.'
                : 'Official PAGASA WIS/OAPI observation is fresh.',
            'source' => 'PAGASA WIS/OAPI surface observation',
            'source_url' => rtrim((string) config('services.pagasa_wis.base_url'), '/'),
            'scope' => 'official station observation; not barangay-specific',
            'station_id' => $station['id'] ?? null,
            'station_name' => $station['name'] ?? null,
            'latitude' => $station['latitude'] ?? null,
            'longitude' => $station['longitude'] ?? null,
            'observed_at' => $observedAt,
            'rainfall_mm' => $values['rainfall_mm'] ?? null,
            'temperature_c' => $values['temperature_c'] ?? null,
            'humidity_percent' => $values['humidity_percent'] ?? null,
            'wind_speed_kmh' => $values['wind_speed_kmh'] ?? null,
            'fetched_at' => now()->toIso8601String(),
            'raw_feature_count' => $features->count(),
        ];
    }

    private function stationFrom(array $feature, array $properties): array
    {
        $coordinates = data_get($feature, 'geometry.coordinates', []);

        return [
            'id' => $properties['wigos_station_identifier'] ?? $properties['station_id'] ?? $properties['id'] ?? null,
            'name' => $properties['station_name'] ?? $properties['name'] ?? null,
            'longitude' => isset($coordinates[0]) && is_numeric($coordinates[0]) ? round((float) $coordinates[0], 7) : null,
            'latitude' => isset($coordinates[1]) && is_numeric($coordinates[1]) ? round((float) $coordinates[1], 7) : null,
        ];
    }

    private function valuesFromProperties(array $properties): array
    {
        $values = [];
        $name = Str::of((string) ($properties['name'] ?? $properties['observedProperty'] ?? ''))
            ->lower()
            ->replace(['-', ' '], '_')
            ->toString();
        $value = $this->number($properties['value'] ?? null);

        if ($value !== null && $name !== '') {
            if (Str::contains($name, ['precipitation', 'rainfall', 'rain'])) {
                $values['rainfall_mm'] = $value;
            } elseif (Str::contains($name, ['air_temperature', 'temperature'])) {
                $values['temperature_c'] = $value;
            } elseif (Str::contains($name, ['relative_humidity', 'humidity'])) {
                $values['humidity_percent'] = $value;
            } elseif (Str::contains($name, ['wind_speed', 'wind'])) {
                $values['wind_speed_kmh'] = $value;
            }
        }

        foreach ([
            'rainfall_mm' => ['rainfall_mm', 'rainfall', 'rain', 'precipitation', 'precipitation_amount'],
            'temperature_c' => ['temperature_c', 'temperature', 'air_temperature', 'dry_bulb_temperature'],
            'humidity_percent' => ['humidity_percent', 'relative_humidity', 'humidity'],
            'wind_speed_kmh' => ['wind_speed_kmh', 'wind_speed', 'wind_speed_10m'],
        ] as $target => $keys) {
            foreach ($keys as $key) {
                $number = $this->number($properties[$key] ?? null);

                if ($number !== null) {
                    $values[$target] = $number;
                    break;
                }
            }
        }

        return $values;
    }

    private function hasNumericWeather(array $observation): bool
    {
        return is_numeric($observation['rainfall_mm'] ?? null)
            || is_numeric($observation['temperature_c'] ?? null)
            || is_numeric($observation['humidity_percent'] ?? null)
            || is_numeric($observation['wind_speed_kmh'] ?? null);
    }

    private function unavailable(string $message): array
    {
        return [
            'status' => 'unavailable',
            'stale' => true,
            'message' => $message,
            'source' => 'PAGASA WIS/OAPI surface observation',
            'source_url' => rtrim((string) config('services.pagasa_wis.base_url', 'https://wis.pagasa.dost.gov.ph/oapi'), '/'),
            'scope' => 'official station observation; not barangay-specific',
            'observed_at' => null,
            'rainfall_mm' => null,
            'temperature_c' => null,
            'humidity_percent' => null,
            'wind_speed_kmh' => null,
            'fetched_at' => now()->toIso8601String(),
        ];
    }

    private function dateTimeString(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }
}
