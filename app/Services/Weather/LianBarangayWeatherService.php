<?php

namespace App\Services\Weather;

use App\Support\LianBarangays;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The one source of current barangay weather for Lian, Batangas. The Heat Map (Farmer, MAO, IT), its
 * popups, barangay Advisories and Climora all read the records kept here, so they never disagree.
 *
 * Each of the 19 barangays is requested at its own polygon-verified centroid (LianBarangays). All 19 go to
 * Open-Meteo in ONE multi-coordinate request (~1.5 s); if that fails they are retried concurrently, one
 * request per barangay, so a single bad response only affects that barangay.
 *
 * Every successful reading is kept as that barangay's last valid record. A failed refresh never overwrites it:
 * freshness is worked out from fetched_at whenever the record is read —
 *   fresh   ≤ FRESH_MINUTES   ("Current")
 *   recent  ≤ RECENT_MINUTES  ("Recent")
 *   stale   older             ("Weather update is temporarily delayed", last values + their time)
 *   unavailable               no valid record at all
 * Pages read the records without calling the provider (snapshot()); a refresh that is due runs after the
 * response has been sent (refreshIfDue()), and the scheduler refreshes all 19 every REFRESH_MINUTES.
 */
class LianBarangayWeatherService
{
    public const PROVIDER = 'Open-Meteo';

    /** A record this old is due for a refresh (scheduler interval, and the page-triggered refresh). */
    public const REFRESH_MINUTES = 10;

    public const FRESH_MINUTES = 10;

    public const RECENT_MINUTES = 30;

    /** How long a last valid record is still shown as "last available"; older ones count as no reading. */
    public const KEEP_HOURS = 24;

    /** Kept for callers that still refer to the old name. */
    public const STALE_AFTER_HOURS = self::KEEP_HOURS;

    private const KEY_PREFIX = 'weather-api:lian-barangay:v2:';

    /**
     * All 19 records and the last refresh's status under ONE key, so a page reads the shared weather in a
     * single cache round trip (which matters when the cache store is the remote database).
     */
    public const STORE_KEY = self::KEY_PREFIX.'store';

    private const LOCK_KEY = self::KEY_PREFIX.'refresh-lock';

    /** At most one page-triggered attempt per minute, so a provider outage is not hammered by page loads. */
    private const ATTEMPT_THROTTLE_KEY = self::KEY_PREFIX.'attempt-throttle';

    /** Open-Meteo snaps a request to its model grid; a returned point farther than this is not this barangay. */
    private const MAX_GRID_OFFSET_DEGREES = 0.2;

    /** A "current" reading whose provider time is this far from now is rejected as not current. */
    private const MAX_OBSERVATION_AGE_HOURS = 3;

    private const NEXT_HOURS = 6;

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
        'weather_code',
        'precipitation_sum',
        'rain_sum',
        'temperature_2m_max',
        'temperature_2m_min',
        'precipitation_probability_max',
        'wind_speed_10m_max',
    ];

    /** Days of forecast kept on each record (the Predictions page's "Next 2 Weeks"); risk still uses the first 7. */
    public const FORECAST_DAYS = 16;

    private bool $refreshScheduled = false;

    /**
     * All 19 barangays, refreshing first when a record is due. For the CLI, jobs and the risk engine — a page
     * reads snapshot() and calls refreshIfDue() instead.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(bool $refresh = false): array
    {
        // A refresh attempted in the last minute (by the scheduler, a page or Refresh) is reused, not repeated: when the
        // provider is down, rating the barangays right after that attempt must not send the same 19 requests again.
        if ($refresh || ($this->isDue() && ! $this->attemptedWithin(60))) {
            return $this->refreshAll(wait: $refresh);
        }

        return $this->snapshot();
    }

    /**
     * The latest valid weather for all 19 barangays, keyed by canonical name, read from the cache only (never
     * an online request), each with its freshness worked out now.
     *
     * @return array<string, array<string, mixed>>
     */
    public function snapshot(): array
    {
        $stored = $this->store()['records'];
        $records = [];

        foreach (LianBarangays::coordinates() as $barangay => [$latitude, $longitude]) {
            $records[$barangay] = $this->readRecord($barangay, $latitude, $longitude, $stored[$barangay] ?? null);
        }

        return $records;
    }

    /**
     * Each barangay's last saved daily forecast and when it was fetched, from the cache only. Unlike snapshot(), a
     * record older than KEEP_HOURS still counts: its days that have not passed yet are the last forecast there is, so
     * a missed refresh shows that forecast with its own time instead of no forecast at all.
     *
     * @return array<string, array{daily: array<int, mixed>, fetched_at: ?string}>
     */
    public function savedForecasts(): array
    {
        $stored = $this->store()['records'];
        $forecasts = [];

        foreach (array_keys(LianBarangays::coordinates()) as $barangay) {
            $record = is_array($stored[$barangay] ?? null) ? $stored[$barangay] : [];
            $forecasts[$barangay] = [
                'daily' => is_array($record['daily'] ?? null) ? $record['daily'] : [],
                'fetched_at' => is_string($record['fetched_at'] ?? null) ? $record['fetched_at'] : null,
            ];
        }

        return $forecasts;
    }

    /**
     * One barangay's latest valid weather, from the cache only.
     *
     * @return array<string, mixed>|null null when the name is not one of Lian's 19 barangays
     */
    public function getCurrentWeather(?string $barangay): ?array
    {
        $canonical = LianBarangays::canonicalName($barangay);
        if ($canonical === null) {
            return null;
        }

        [$latitude, $longitude] = LianBarangays::coordinateFor($canonical);

        return $this->readRecord($canonical, $latitude, $longitude, $this->store()['records'][$canonical] ?? null);
    }

    /** Read prepared weather without making an online request during page rendering. */
    public function cachedForBarangay(?string $barangay): ?array
    {
        return $this->getCurrentWeather($barangay);
    }

    /**
     * One barangay's latest weather for a caller that may wait briefly (Climora, advisory guidance). When the
     * record is due, all 19 are refreshed in the same single batched request the Heat Map uses, so every module
     * shows the same reading.
     */
    public function latestForBarangay(string $barangay, bool $refresh = false): array
    {
        $canonical = LianBarangays::canonicalName($barangay);
        if ($canonical === null) {
            throw new \InvalidArgumentException('Barangay must be one of the official Lian barangays.');
        }

        $record = $this->getCurrentWeather($canonical);
        if ($refresh || $this->isRecordDue($record)) {
            $this->refreshAll($canonical, wait: $refresh);
            $record = $this->getCurrentWeather($canonical);
        }

        return $record;
    }

    public function refresh(): array
    {
        return $this->refreshAll(wait: true);
    }

    /** True when any barangay has no record or its record is at least REFRESH_MINUTES old. */
    public function isDue(): bool
    {
        foreach ($this->snapshot() as $record) {
            if ($this->isRecordDue($record)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Refresh all 19 after the current response has been sent, when a record is due. The page that asked
     * gets the cached records immediately; the next read gets the new ones.
     */
    public function refreshIfDue(?string $priorityBarangay = null): bool
    {
        if ($this->refreshScheduled || ! $this->isDue()) {
            return false;
        }

        if (! Cache::add(self::ATTEMPT_THROTTLE_KEY, true, now()->addMinute())) {
            return false;
        }

        $this->refreshScheduled = true;
        app()->terminating(function () use ($priorityBarangay): void {
            // Once only: an application that serves several requests calls every terminating callback again.
            if (! $this->refreshScheduled) {
                return;
            }
            rescue(fn () => $this->refreshAll($priorityBarangay), report: false);
            // Rate the barangays from the new readings too (skipped if already rated), so the risk follows the
            // weather even when no queue worker or scheduler is running.
            rescue(fn () => app(\App\Services\Risk\BarangayRiskMapService::class)->refreshForCurrentWeather(), report: false);
            $this->refreshScheduled = false;
        });

        return true;
    }

    /**
     * Fetch all 19 barangays from the provider and keep every valid reading. A barangay whose reading fails
     * keeps its previous record. Only one refresh runs at a time; a caller that finds one running gets the
     * current records (or, with $wait, waits briefly for that refresh to finish).
     *
     * @return array<string, array<string, mixed>>
     */
    public function refreshAll(?string $priorityBarangay = null, bool $wait = false): array
    {
        $lock = Cache::lock(self::LOCK_KEY, 60);

        try {
            $acquired = $wait ? $lock->block(20) : $lock->get();
        } catch (Throwable) {
            $acquired = false;
        }

        if (! $acquired) {
            return $this->snapshot();
        }

        try {
            $this->fetchAndStore($priorityBarangay);
        } finally {
            rescue(fn () => $lock->release(), report: false);
        }

        return $this->snapshot();
    }

    /**
     * Refresh Weather button: refresh now unless the provider was already asked in the last $seconds (a second
     * click, or another viewer's), in which case the records from that attempt are returned.
     *
     * @return array<string, array<string, mixed>>
     */
    public function refreshUnlessJustChecked(?string $priorityBarangay = null, int $seconds = 30): array
    {
        return $this->attemptedWithin($seconds) ? $this->snapshot() : $this->refreshAll($priorityBarangay);
    }

    /** True when the provider was asked (successfully or not) in the last $seconds. */
    private function attemptedWithin(int $seconds): bool
    {
        $lastAttempt = $this->store()['status']['last_attempt_at'] ?? null;

        return rescue(fn () => $lastAttempt !== null && Carbon::parse($lastAttempt)->gt(now()->subSeconds($seconds)), false, false);
    }

    /**
     * Health of the 19 records for the Current Conditions panel: how many are current, when the provider was
     * last asked, and which barangays are using an older or no reading.
     *
     * @param  array<string, array<string, mixed>>|null  $records
     * @return array<string, mixed>
     */
    public function summary(?array $records = null): array
    {
        $records ??= $this->snapshot();
        $status = $this->store()['status'];
        $byStatus = collect($records)->groupBy(fn (array $record): string => (string) $record['status']);
        $names = fn (string $key): array => $byStatus->get($key, collect())->pluck('barangay')->values()->all();
        $latestFetch = collect($records)->pluck('fetched_at')->filter()->max();

        return [
            'provider' => self::PROVIDER,
            'source_url' => 'https://open-meteo.com/',
            'total' => count($records),
            'current' => count($names('fresh')) + count($names('recent')),
            'fresh' => count($names('fresh')),
            'recent' => count($names('recent')),
            'delayed' => count($names('stale')),
            'unavailable' => count($names('unavailable')),
            'delayed_barangays' => $names('stale'),
            'unavailable_barangays' => $names('unavailable'),
            'latest_fetched_at' => $latestFetch,
            'last_checked_at' => $status['last_attempt_at'] ?? null,
            'last_success_at' => $status['last_success_at'] ?? null,
            'last_refresh_failed' => $status['failed'] ?? [],
            'last_refresh_method' => $status['method'] ?? null,
            'last_refresh_duration_ms' => $status['duration_ms'] ?? null,
            'refresh_minutes' => $this->refreshMinutes(),
            'fresh_minutes' => self::FRESH_MINUTES,
            'recent_minutes' => self::RECENT_MINUTES,
            'resolution_note' => 'Open-Meteo model estimate at each barangay\'s own map location. Nearby barangays can share a model grid cell, so their values may be very close.',
        ];
    }

    private function fetchAndStore(?string $priorityBarangay): void
    {
        $started = microtime(true);
        $targets = $this->orderedTargets($priorityBarangay);
        $method = 'batch';
        $results = [];
        $error = null;

        try {
            $results = $this->fetchBatch($targets);
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
            $method = 'concurrent';
            Log::info('Lian barangay weather: batched request failed, retrying each barangay concurrently.', ['message' => $error]);
            $results = $this->fetchConcurrently($targets);
        }

        $fetchedAt = now();
        $failed = [];
        // Runs under the refresh lock, so this read-modify-write never races another refresh.
        $store = $this->store();

        foreach ($targets as $barangay => [$latitude, $longitude]) {
            $payload = $results[$barangay] ?? null;
            $record = is_array($payload) ? $this->normalize($barangay, $latitude, $longitude, $payload, $fetchedAt) : null;

            if ($record === null) {
                // The barangay keeps its previous valid record, if it has one.
                $failed[] = $barangay;

                continue;
            }

            $store['records'][$barangay] = $record;
        }

        $succeeded = count($targets) - count($failed);
        $store['status'] = [
            'last_attempt_at' => $fetchedAt->toIso8601String(),
            'last_success_at' => $succeeded > 0 ? $fetchedAt->toIso8601String() : ($store['status']['last_success_at'] ?? null),
            'succeeded' => $succeeded,
            'failed' => $failed,
            'method' => $method,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'error' => $failed === [] ? null : $error,
        ];

        Cache::forever(self::STORE_KEY, $store);

        if ($failed !== []) {
            Log::warning('Lian barangay weather: some barangays kept their last available reading.', ['failed' => $failed]);
        }
    }

    /** @return array<string, array{0: float, 1: float}> the farmer's own barangay first, then the others */
    private function orderedTargets(?string $priorityBarangay): array
    {
        $targets = LianBarangays::coordinates();
        $priority = LianBarangays::canonicalName($priorityBarangay);

        if ($priority !== null && isset($targets[$priority])) {
            $targets = [$priority => $targets[$priority]] + $targets;
        }

        return $targets;
    }

    /**
     * One request for every barangay; Open-Meteo answers with a list in request order.
     *
     * @param  array<string, array{0: float, 1: float}>  $targets
     * @return array<string, array<string, mixed>>
     */
    private function fetchBatch(array $targets): array
    {
        $response = $this->client()->get($this->forecastUrl(), $this->query(
            implode(',', array_column($targets, 0)),
            implode(',', array_column($targets, 1)),
        ))->throw();

        $json = $response->json();
        if (! is_array($json) || ! array_is_list($json) || count($json) !== count($targets)) {
            throw new \RuntimeException('Open-Meteo did not return one result per barangay.');
        }

        return array_combine(array_keys($targets), $json);
    }

    /**
     * @param  array<string, array{0: float, 1: float}>  $targets
     * @return array<string, array<string, mixed>|null>
     */
    private function fetchConcurrently(array $targets): array
    {
        try {
            $responses = Http::pool(fn (Pool $pool) => collect($targets)->map(
                fn (array $point, string $barangay) => $pool->as($barangay)
                    ->timeout($this->timeoutSeconds())
                    ->connectTimeout(4)
                    ->acceptJson()
                    ->get($this->forecastUrl(), $this->query($point[0], $point[1]))
            )->values()->all());
        } catch (Throwable $exception) {
            Log::warning('Lian barangay weather: concurrent requests failed.', ['message' => $exception->getMessage()]);

            return [];
        }

        $results = [];
        foreach ($targets as $barangay => $point) {
            $response = $responses[$barangay] ?? null;
            $json = $response instanceof Response && $response->successful() ? $response->json() : null;
            $results[$barangay] = is_array($json) && ! array_is_list($json) ? $json : null;
        }

        return $results;
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout($this->timeoutSeconds())->connectTimeout(4)->acceptJson();
    }

    private function forecastUrl(): string
    {
        return rtrim((string) config('services.open_meteo.base_url', 'https://api.open-meteo.com/v1'), '/').'/forecast';
    }

    private function query(string|float $latitude, string|float $longitude): array
    {
        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'current' => implode(',', self::CURRENT),
            'hourly' => implode(',', self::HOURLY),
            'daily' => implode(',', self::DAILY),
            'timezone' => config('services.open_meteo.timezone', 'Asia/Manila'),
            'forecast_days' => self::FORECAST_DAYS,
        ];
    }

    /**
     * The provider's own values for one barangay, or null when the response is not a usable current reading
     * for this location (no temperature, no time, a time far from now, or a point outside this barangay's area).
     *
     * @return array<string, mixed>|null
     */
    private function normalize(string $barangay, float $latitude, float $longitude, array $payload, Carbon $fetchedAt): ?array
    {
        $current = is_array($payload['current'] ?? null) ? $payload['current'] : [];
        $hourly = is_array($payload['hourly'] ?? null) ? $payload['hourly'] : [];
        $daily = is_array($payload['daily'] ?? null) ? $payload['daily'] : [];
        $temperature = $this->number($current['temperature_2m'] ?? null);
        $observedAt = $this->localTime($current['time'] ?? null);

        if ($temperature === null || $observedAt === null || abs($observedAt->diffInHours($fetchedAt, false)) > self::MAX_OBSERVATION_AGE_HOURS) {
            return null;
        }

        $gridLatitude = $this->number($payload['latitude'] ?? null);
        $gridLongitude = $this->number($payload['longitude'] ?? null);
        if ($gridLatitude !== null && $gridLongitude !== null
            && (abs($gridLatitude - $latitude) > self::MAX_GRID_OFFSET_DEGREES || abs($gridLongitude - $longitude) > self::MAX_GRID_OFFSET_DEGREES)) {
            return null;
        }

        $rainSeries = $hourly['precipitation'] ?? $hourly['rain'] ?? [];
        $probabilitySeries = $hourly['precipitation_probability'] ?? [];
        $rain24h = $this->sum($this->next24Hours($hourly['time'] ?? [], $rainSeries, $current['time'] ?? null))
            ?? $this->number(($daily['precipitation_sum'] ?? $daily['rain_sum'] ?? [])[0] ?? null);
        $rain7d = $this->sum(array_slice($daily['precipitation_sum'] ?? $daily['rain_sum'] ?? [], 0, 7));
        $probability = $this->max($this->next24Hours($hourly['time'] ?? [], $probabilitySeries, $current['time'] ?? null))
            ?? $this->number(($daily['precipitation_probability_max'] ?? [])[0] ?? null);
        $code = isset($current['weather_code']) && is_numeric($current['weather_code']) ? (int) $current['weather_code'] : null;
        $isDay = isset($current['is_day']) ? (bool) $current['is_day'] : null;
        $condition = $code === null ? null : app(WeatherIconMapper::class)->map($code, $isDay)['condition'];
        $precipitationNow = $this->number($current['precipitation'] ?? $current['rain'] ?? null);
        $wind = $this->number($current['wind_speed_10m'] ?? null);

        return [
            'barangay' => $barangay,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'grid_latitude' => $gridLatitude,
            'grid_longitude' => $gridLongitude,
            'source' => 'Open-Meteo Forecast API',
            'provider' => self::PROVIDER,
            'source_url' => 'https://open-meteo.com/',
            'source_credit' => 'Weather data by Open-Meteo; interpreted by iClimate.',
            'temperature_c' => $temperature,
            'humidity_percent' => $this->number($current['relative_humidity_2m'] ?? null),
            'precipitation_mm' => $precipitationNow,
            'rainfall_now_mm' => $precipitationNow,
            'rain_probability_percent' => $probability,
            'precip_probability_percent' => $probability,
            'rainfall_24h_mm' => $rain24h,
            'rainfall_mm' => $rain24h,
            'rainfall_7d_mm' => $rain7d,
            'rainfall_status' => $this->rainfallStatus($rain24h, $rain7d),
            'wind_kmh' => $wind,
            'wind_speed_kmh' => $wind,
            // Raw WMO code + day/night flag, so callers can derive an icon without another request.
            'weather_code' => $code,
            'is_day' => $isDay,
            'weather_condition' => $condition,
            'condition' => $condition,
            'next_hours' => $this->nextHours($hourly, $current['time'] ?? null),
            'daily' => $this->dailyList($daily),
            'forecast_period' => 'next_24_hours',
            'observed_at' => $observedAt->toIso8601String(),
            'fetched_at' => $fetchedAt->toIso8601String(),
        ];
    }

    /** @return array{records: array<string, array<string, mixed>>, status: array<string, mixed>} */
    private function store(): array
    {
        $store = rescue(fn () => Cache::get(self::STORE_KEY), null, false);

        return [
            'records' => is_array($store['records'] ?? null) ? $store['records'] : [],
            'status' => is_array($store['status'] ?? null) ? $store['status'] : [],
        ];
    }

    /** @return array<string, mixed> */
    private function readRecord(string $barangay, float $latitude, float $longitude, mixed $record): array
    {
        $usable = is_array($record) && is_numeric($record['temperature_c'] ?? null) && ! empty($record['fetched_at'])
            && rescue(fn () => Carbon::parse($record['fetched_at'])->gt(now()->subHours(self::KEEP_HOURS)), false, false);

        return $usable ? $this->withFreshness($record) : $this->unavailableRecord($barangay, $latitude, $longitude);
    }

    /** @return array<string, mixed> */
    private function withFreshness(array $record): array
    {
        try {
            $age = max(0, (int) floor(Carbon::parse($record['fetched_at'])->diffInSeconds(now(), false) / 60));
        } catch (Throwable) {
            $age = PHP_INT_MAX;
        }

        $status = match (true) {
            $age <= self::FRESH_MINUTES => 'fresh',
            $age <= self::RECENT_MINUTES => 'recent',
            default => 'stale',
        };

        $record['status'] = $status;
        $record['freshness'] = $status;
        $record['is_fresh'] = $status !== 'stale';
        $record['stale'] = $status === 'stale';
        $record['age_minutes'] = $age === PHP_INT_MAX ? null : $age;
        $record['message'] = $status === 'stale'
            ? 'Weather update is temporarily delayed. Showing the last available barangay weather.'
            : 'Latest available weather for this barangay\'s location. Treat it as decision support, not a rain-gauge reading.';
        $record['next_refresh_at'] = rescue(fn () => Carbon::parse($record['fetched_at'])->addMinutes($this->refreshMinutes())->toIso8601String(), null, false);

        return $record;
    }

    /** @return array<string, mixed> */
    private function unavailableRecord(string $barangay, float $latitude, float $longitude): array
    {
        return [
            'barangay' => $barangay,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'source' => 'Open-Meteo Forecast API',
            'provider' => self::PROVIDER,
            'source_url' => 'https://open-meteo.com/',
            'source_credit' => 'Weather data by Open-Meteo; interpreted by iClimate.',
            'status' => 'unavailable',
            'freshness' => 'unavailable',
            'is_fresh' => false,
            'stale' => true,
            'age_minutes' => null,
            'message' => 'Latest weather is temporarily unavailable.',
            'observed_at' => null,
            'fetched_at' => null,
            'next_refresh_at' => null,
            'temperature_c' => null,
            'humidity_percent' => null,
            'precipitation_mm' => null,
            'rainfall_now_mm' => null,
            'rain_probability_percent' => null,
            'precip_probability_percent' => null,
            'rainfall_24h_mm' => null,
            'rainfall_mm' => null,
            'rainfall_7d_mm' => null,
            'rainfall_status' => null,
            'wind_kmh' => null,
            'wind_speed_kmh' => null,
            'weather_code' => null,
            'is_day' => null,
            'weather_condition' => null,
            'condition' => null,
            'next_hours' => [],
            'forecast_period' => 'next_24_hours',
        ];
    }

    /**
     * The barangay's day-by-day forecast (up to FORECAST_DAYS), in the shape the Predictions page reads.
     *
     * @return array<int, array<string, mixed>>
     */
    private function dailyList(array $daily): array
    {
        $days = [];
        foreach ((array) ($daily['time'] ?? []) as $i => $date) {
            $code = $this->number(($daily['weather_code'] ?? [])[$i] ?? null);
            $days[] = [
                'date' => (string) $date,
                'weather_code' => $code !== null ? (int) $code : null,
                'condition' => $code !== null ? app(WeatherIconMapper::class)->map((int) $code, true)['condition'] : null,
                'rainfall' => $this->number(($daily['precipitation_sum'] ?? $daily['rain_sum'] ?? [])[$i] ?? null),
                'precipitation_probability' => $this->number(($daily['precipitation_probability_max'] ?? [])[$i] ?? null),
                'temperature_max' => $this->number(($daily['temperature_2m_max'] ?? [])[$i] ?? null),
                'temperature_min' => $this->number(($daily['temperature_2m_min'] ?? [])[$i] ?? null),
                'wind_speed_max' => $this->number(($daily['wind_speed_10m_max'] ?? [])[$i] ?? null),
            ];
        }

        return $days;
    }

    private function isRecordDue(?array $record): bool
    {
        return ! is_array($record) || ($record['age_minutes'] ?? null) === null || $record['age_minutes'] >= $this->refreshMinutes();
    }

    /** @return array<int, array<string, mixed>> */
    private function nextHours(array $hourly, mixed $observedAt): array
    {
        $times = $hourly['time'] ?? [];
        if (! is_array($times) || ! is_string($observedAt)) {
            return [];
        }

        $start = $this->localTime($observedAt)?->startOfHour();
        if ($start === null) {
            return [];
        }

        $hours = [];
        foreach ($times as $index => $time) {
            $hour = $this->localTime($time);
            if ($hour === null || $hour->lt($start)) {
                continue;
            }

            $hours[] = [
                'time' => $hour->toIso8601String(),
                'temperature_c' => $this->number($hourly['temperature_2m'][$index] ?? null),
                'rain_probability_percent' => $this->number($hourly['precipitation_probability'][$index] ?? null),
                'precipitation_mm' => $this->number($hourly['precipitation'][$index] ?? $hourly['rain'][$index] ?? null),
            ];

            if (count($hours) >= self::NEXT_HOURS) {
                break;
            }
        }

        return $hours;
    }

    private function localTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value, (string) config('services.open_meteo.timezone', 'Asia/Manila'));
        } catch (Throwable) {
            return null;
        }
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
        return max(5, (int) config('services.open_meteo.barangay_refresh_minutes', self::REFRESH_MINUTES));
    }

    private function timeoutSeconds(): int
    {
        return max(2, min(8, (int) config('services.open_meteo.timeout', 8)));
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
