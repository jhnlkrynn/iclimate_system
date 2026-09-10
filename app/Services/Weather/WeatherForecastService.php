<?php

namespace App\Services\Weather;

use App\Models\ExternalWeatherData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class WeatherForecastService
{
    private const DAILY_FIELDS = [
        'weather_code',
        'temperature_2m_max',
        'temperature_2m_min',
        'precipitation_sum',
        'rain_sum',
        'precipitation_probability_max',
        'wind_speed_10m_max',
        'wind_gusts_10m_max',
    ];

    private const HOURLY_FIELDS = [
        'relative_humidity_2m',
    ];

    public function __construct(private readonly WeatherIconMapper $icons) {}

    public function forecast(bool $force = false): array
    {
        $cacheKey = 'weather.weekly.lian.open-meteo';
        $lastSuccessfulKey = $cacheKey.'.last_successful';

        if (! $force && Cache::has($cacheKey)) {
            $forecast = Cache::get($cacheKey);
            $forecast['cached'] = true;

            return $forecast;
        }

        try {
            $forecast = $this->fetch();
            $ttl = now($this->timezone())->addMinutes($this->cacheMinutes());

            Cache::put($cacheKey, $forecast, $ttl);
            Cache::forever($lastSuccessfulKey, $forecast);
            $this->storeForecast($forecast);

            return $forecast;
        } catch (Throwable $exception) {
            Log::warning('Weekly Open-Meteo forecast fetch failed.', [
                'message' => $exception->getMessage(),
            ]);

            $fallback = Cache::get($lastSuccessfulKey) ?? $this->latestStoredForecast();

            if (is_array($fallback) && ($fallback['days'] ?? []) !== []) {
                $fallback['success'] = true;
                $fallback['cached'] = true;
                $fallback['stale'] = true;
                $fallback['status'] = 'stale';
                $fallback['note'] = 'Showing the latest cached weekly forecast. It may not be newly updated.';

                return $fallback;
            }

            return [
                'success' => false,
                'cached' => false,
                'stale' => true,
                'status' => 'unavailable',
                'provider' => 'Open-Meteo',
                'source_name' => 'Open-Meteo Forecast API',
                'source_url' => 'https://open-meteo.com/',
                'endpoint' => $this->endpoint(),
                'location' => $this->location(),
                'fetched_at' => null,
                'days' => [],
                'summary' => [
                    'headline' => 'Weekly forecast temporarily unavailable.',
                    'advice' => ['Please check again later or review saved iClimate advisories before field work.'],
                    'best_planting_days' => [],
                    'avoid_planting_days' => [],
                ],
                'note' => 'Weekly forecast is temporarily unavailable.',
            ];
        }
    }

    private function fetch(): array
    {
        $response = Http::timeout((int) config('services.open_meteo.timeout', 10))
            ->retry(2, 300)
            ->acceptJson()
            ->get($this->endpoint(), [
                'latitude' => $this->latitude(),
                'longitude' => $this->longitude(),
                'daily' => implode(',', self::DAILY_FIELDS),
                'hourly' => implode(',', self::HOURLY_FIELDS),
                'timezone' => $this->timezone(),
                'forecast_days' => 7,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Open-Meteo returned HTTP '.$response->status());
        }

        $payload = $response->json();

        if (! is_array($payload) || ! is_array($payload['daily'] ?? null) || ! is_array($payload['daily']['time'] ?? null)) {
            throw new RuntimeException('Open-Meteo response is missing daily forecast data.');
        }

        $days = $this->normalizeDays($payload);

        return [
            'success' => true,
            'cached' => false,
            'stale' => false,
            'status' => 'fresh',
            'provider' => 'Open-Meteo',
            'source_name' => 'Open-Meteo Forecast API',
            'source_url' => 'https://open-meteo.com/',
            'endpoint' => $this->endpoint(),
            'location' => $this->location(),
            'fetched_at' => now($this->timezone())->toIso8601String(),
            'days' => $days,
            'summary' => $this->weeklySummary($days),
            'note' => 'Updated from Open-Meteo and cached for '.$this->cacheMinutes().' minutes.',
        ];
    }

    private function normalizeDays(array $payload): array
    {
        $daily = $payload['daily'];
        $humidityByDate = $this->dailyHumidity($payload['hourly'] ?? []);
        $days = [];

        foreach (array_slice($daily['time'] ?? [], 0, 7) as $index => $dateValue) {
            $date = Carbon::parse((string) $dateValue, $this->timezone());
            $code = $this->number($daily['weather_code'][$index] ?? null);
            $mapped = $this->icons->map($code !== null ? (int) $code : null, true);
            $rainfall = $this->number($daily['precipitation_sum'][$index] ?? $daily['rain_sum'][$index] ?? null);
            $rainChance = $this->number($daily['precipitation_probability_max'][$index] ?? null);
            $wind = $this->number($daily['wind_speed_10m_max'][$index] ?? null);
            $risk = $this->riskLevel($mapped['condition'], $rainfall, $rainChance, $wind);

            $days[] = [
                'date' => $date->toDateString(),
                'label' => $date->format('D, M j'),
                'day' => $date->format('D'),
                'weather_code' => $code,
                'condition' => $this->simpleCondition($mapped['condition']),
                'icon' => $mapped['icon'],
                'rainfall_mm' => $rainfall,
                'precipitation_probability' => $rainChance,
                'temperature_max_c' => $this->number($daily['temperature_2m_max'][$index] ?? null),
                'temperature_min_c' => $this->number($daily['temperature_2m_min'][$index] ?? null),
                'humidity_percent' => $humidityByDate[$date->toDateString()] ?? null,
                'wind_speed_kmh' => $wind,
                'wind_gusts_kmh' => $this->number($daily['wind_gusts_10m_max'][$index] ?? null),
                'risk_level' => $risk,
                'recommendation' => $this->dailyRecommendation($risk, $rainfall, $rainChance, $wind),
            ];
        }

        return $days;
    }

    private function dailyHumidity(array $hourly): array
    {
        $times = $hourly['time'] ?? [];
        $values = $hourly['relative_humidity_2m'] ?? [];
        $grouped = [];

        foreach ($times as $index => $time) {
            $date = substr((string) $time, 0, 10);
            $value = $this->number($values[$index] ?? null);

            if ($date !== '' && $value !== null) {
                $grouped[$date][] = $value;
            }
        }

        return collect($grouped)
            ->map(fn (array $items): float => round(array_sum($items) / max(count($items), 1), 1))
            ->all();
    }

    private function weeklySummary(array $days): array
    {
        $collection = collect($days);
        $bestPlanting = $collection
            ->filter(fn (array $day): bool => $day['risk_level'] === 'Low' && (float) ($day['rainfall_mm'] ?? 0) < 5 && (float) ($day['wind_speed_kmh'] ?? 0) < 20)
            ->pluck('label')
            ->take(3)
            ->values()
            ->all();
        $avoidPlanting = $collection
            ->filter(fn (array $day): bool => in_array($day['risk_level'], ['High', 'Severe'], true))
            ->pluck('label')
            ->values()
            ->all();
        $wetDays = $collection->filter(fn (array $day): bool => (float) ($day['rainfall_mm'] ?? 0) >= 10 || (float) ($day['precipitation_probability'] ?? 0) >= 70)->count();
        $windDays = $collection->filter(fn (array $day): bool => (float) ($day['wind_speed_kmh'] ?? 0) >= 25 || (float) ($day['wind_gusts_kmh'] ?? 0) >= 35)->count();
        $dryDays = $collection->filter(fn (array $day): bool => (float) ($day['rainfall_mm'] ?? 0) < 2 && (float) ($day['precipitation_probability'] ?? 0) < 40)->count();
        $advice = [];

        if ($bestPlanting !== []) {
            $advice[] = 'Best planting windows: '.implode(', ', $bestPlanting).'.';
        }

        if ($avoidPlanting !== []) {
            $advice[] = 'Avoid planting on '.implode(', ', $avoidPlanting).' because rain, flood, storm, or wind risk is elevated.';
        }

        if ($wetDays >= 3) {
            $advice[] = 'Several wet days are expected; clear drainage and avoid fertilizer or pesticide work before rain.';
        }

        if ($windDays > 0) {
            $advice[] = 'Strong wind is possible; secure seedlings, tools, and light farm materials.';
        }

        if ($dryDays >= 4) {
            $advice[] = 'Rain looks limited on several days; inspect water supply and plan irrigation early.';
        }

        if ($advice === []) {
            $advice[] = 'Weather looks workable overall; keep checking advisories before planting, spraying, or harvesting.';
        }

        return [
            'headline' => $this->headline($collection),
            'advice' => array_values(array_unique($advice)),
            'best_planting_days' => $bestPlanting,
            'avoid_planting_days' => $avoidPlanting,
        ];
    }

    private function headline(Collection $days): string
    {
        $highestRisk = $days->pluck('risk_level')->sortByDesc(fn (string $risk): int => match ($risk) {
            'Severe' => 4,
            'High' => 3,
            'Moderate' => 2,
            default => 1,
        })->first();

        return match ($highestRisk) {
            'Severe' => 'Severe weather risk appears in the 7-day outlook.',
            'High' => 'Some days need caution before planting or harvesting.',
            'Moderate' => 'Mixed weather is expected; plan field work by day.',
            default => 'The week looks generally workable for routine farm checks.',
        };
    }

    private function riskLevel(string $condition, ?float $rainfall, ?float $rainChance, ?float $wind): string
    {
        $condition = strtolower($condition);

        return match (true) {
            str_contains($condition, 'thunderstorm') || (float) $rainfall >= 40 || (float) $wind >= 40 => 'Severe',
            str_contains($condition, 'heavy rain') || (float) $rainfall >= 20 || (float) $rainChance >= 80 || (float) $wind >= 30 => 'High',
            str_contains($condition, 'light rain') || str_contains($condition, 'moderate rain') || (float) $rainfall >= 5 || (float) $rainChance >= 50 || (float) $wind >= 20 => 'Moderate',
            default => 'Low',
        };
    }

    private function dailyRecommendation(string $risk, ?float $rainfall, ?float $rainChance, ?float $wind): string
    {
        if ($risk === 'Severe') {
            return 'Avoid planting and harvesting; monitor advisories and drainage.';
        }

        if ($risk === 'High') {
            return 'Delay planting if possible; check flood-prone areas first.';
        }

        if ((float) $wind >= 20) {
            return 'Avoid spraying; wind may reduce application accuracy.';
        }

        if ((float) $rainfall < 2 && (float) $rainChance < 40) {
            return 'Good for field checks; prepare irrigation if soil is dry.';
        }

        return 'Workable for light farm tasks; watch rainfall timing.';
    }

    private function simpleCondition(string $condition): string
    {
        return match (true) {
            str_contains($condition, 'Thunderstorm') => 'Thunderstorm',
            str_contains($condition, 'Heavy Rain') => 'Heavy Rain',
            str_contains($condition, 'Rain') || str_contains($condition, 'Drizzle') => str_contains($condition, 'Light') ? 'Light Rain' : 'Moderate Rain',
            str_contains($condition, 'Cloudy') || str_contains($condition, 'Overcast') => 'Cloudy',
            str_contains($condition, 'Clear') => 'Clear',
            default => $condition,
        };
    }

    private function storeForecast(array $forecast): void
    {
        foreach ($forecast['days'] ?? [] as $day) {
            ExternalWeatherData::query()->updateOrCreate(
                [
                    'source' => 'Open-Meteo',
                    'forecast_date' => $day['date'],
                    'forecast_time' => null,
                    'barangay_id' => null,
                ],
                [
                    'location_name' => 'Lian, Batangas, Philippines',
                    'latitude' => $this->latitude(),
                    'longitude' => $this->longitude(),
                    'weather_code' => $day['weather_code'],
                    'temperature' => $this->average([$day['temperature_max_c'], $day['temperature_min_c']]),
                    'temperature_max' => $day['temperature_max_c'],
                    'temperature_min' => $day['temperature_min_c'],
                    'humidity' => $day['humidity_percent'],
                    'rainfall_mm' => $day['rainfall_mm'],
                    'precipitation_probability' => $day['precipitation_probability'],
                    'wind_speed' => $day['wind_speed_kmh'],
                    'raw_response' => ['weekly_forecast_day' => $day, 'summary' => $forecast['summary'] ?? []],
                    'fetched_at' => $forecast['fetched_at'] ? Carbon::parse($forecast['fetched_at'], $this->timezone()) : now($this->timezone()),
                ],
            );
        }
    }

    private function latestStoredForecast(): ?array
    {
        $latestFetch = ExternalWeatherData::query()
            ->where('source', 'Open-Meteo')
            ->max('fetched_at');

        if (! $latestFetch) {
            return null;
        }

        $days = ExternalWeatherData::query()
            ->where('source', 'Open-Meteo')
            ->where('fetched_at', $latestFetch)
            ->orderBy('forecast_date')
            ->take(7)
            ->get()
            ->map(fn (ExternalWeatherData $record): array => $this->dayFromRecord($record))
            ->values()
            ->all();

        if ($days === []) {
            return null;
        }

        return [
            'success' => true,
            'cached' => true,
            'stale' => true,
            'status' => 'stale',
            'provider' => 'Open-Meteo',
            'source_name' => 'Open-Meteo Forecast API',
            'source_url' => 'https://open-meteo.com/',
            'endpoint' => $this->endpoint(),
            'location' => $this->location(),
            'fetched_at' => Carbon::parse($latestFetch, $this->timezone())->toIso8601String(),
            'days' => $days,
            'summary' => $this->weeklySummary($days),
            'note' => 'Showing the latest stored weekly forecast. It may not be newly updated.',
        ];
    }

    private function dayFromRecord(ExternalWeatherData $record): array
    {
        $mapped = $this->icons->map($record->weather_code, true);
        $date = $record->forecast_date?->timezone($this->timezone()) ?? now($this->timezone());
        $risk = $this->riskLevel($mapped['condition'], $this->number($record->rainfall_mm), $this->number($record->precipitation_probability), $this->number($record->wind_speed));

        return [
            'date' => $date->toDateString(),
            'label' => $date->format('D, M j'),
            'day' => $date->format('D'),
            'weather_code' => $record->weather_code,
            'condition' => $this->simpleCondition($mapped['condition']),
            'icon' => $mapped['icon'],
            'rainfall_mm' => $this->number($record->rainfall_mm),
            'precipitation_probability' => $this->number($record->precipitation_probability),
            'temperature_max_c' => $this->number($record->temperature_max),
            'temperature_min_c' => $this->number($record->temperature_min),
            'humidity_percent' => $this->number($record->humidity),
            'wind_speed_kmh' => $this->number($record->wind_speed),
            'wind_gusts_kmh' => null,
            'risk_level' => $risk,
            'recommendation' => $this->dailyRecommendation($risk, $this->number($record->rainfall_mm), $this->number($record->precipitation_probability), $this->number($record->wind_speed)),
        ];
    }

    private function endpoint(): string
    {
        return rtrim((string) config('services.open_meteo.base_url', 'https://api.open-meteo.com/v1'), '/').'/forecast';
    }

    private function location(): array
    {
        return [
            'name' => 'Lian',
            'province' => 'Batangas',
            'country' => 'Philippines',
            'latitude' => $this->latitude(),
            'longitude' => $this->longitude(),
            'timezone' => $this->timezone(),
        ];
    }

    private function latitude(): float
    {
        return (float) config('services.weather.latitude', 13.98);
    }

    private function longitude(): float
    {
        return (float) config('services.weather.longitude', 120.65);
    }

    private function timezone(): string
    {
        return (string) config('services.weather.timezone', 'Asia/Manila');
    }

    private function cacheMinutes(): int
    {
        return max(30, min(60, (int) config('services.weather.cache_minutes', 45)));
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 1) : null;
    }

    private function average(array $values): ?float
    {
        $values = array_values(array_filter($values, fn ($value): bool => is_numeric($value)));

        return $values === [] ? null : round(array_sum($values) / count($values), 1);
    }
}
