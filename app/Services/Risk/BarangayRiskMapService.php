<?php

namespace App\Services\Risk;

use Carbon\CarbonInterface;
use App\Models\FarmerProfile;
use App\Models\HeatmapArea;
use App\Models\RiceProduction;
use App\Services\DecisionSupportService;
use App\Services\Prediction\PredictionOrchestrator;
use App\Services\Weather\LianBarangayWeatherService;
use App\Support\InteractiveRequest;
use App\Services\Weather\WeatherIconMapper;
use App\Support\LianBarangays;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Builds the barangay-level agricultural risk map for Lian, Batangas from
 * only real, already-verified data sources:
 *  - live/cached weather per barangay (LianBarangayWeatherService)
 *  - the most recent real recorded rice yield per barangay (RiceProduction)
 *  - the most common real farm type recorded for that barangay (FarmerProfile)
 *  - the existing AgriculturalRiskScorer / DecisionSupportService for scoring
 *    and advisory text (their formulas are never modified here).
 *
 * A barangay with no fresh weather reading is always reported as "No Data" —
 * it is never scored, and it is never persisted into HeatmapArea (whose
 * risk_level column is a legacy enum that only accepts Low/Moderate/High/
 * Severe). This keeps "No Data" honest without requiring a schema migration:
 * the live/cached API response is the source of truth for "No Data", while
 * HeatmapArea rows are only ever written for barangays with a real score and
 * continue to act as the last-known-good fallback store used elsewhere
 * (e.g. the Climora chatbot).
 */
class BarangayRiskMapService
{
    /**
     * v2: bumped from the original 'geo:lian-risk-map' key as part of the
     * 2026-09 fix that stopped feeding Open-Meteo's short-range (24h/7d)
     * forecast rainfall into AgriculturalRiskScorer's monthly-scale
     * thresholds. Any array cached under the old key was computed with the
     * old (incorrect) logic; changing the key guarantees it can never be
     * read by this class again, regardless of its remaining TTL — no manual
     * Cache::forget() needs to be remembered/run at deploy time.
     */
    public const CACHE_KEY = 'geo:lian-risk-map:v2';

    /** The last map that was fully computed, kept without expiry as what pages fall back to. */
    public const LATEST_KEY = 'geo:lian-risk-map:v2:latest';

    /** Set while a background refresh is queued, so a burst of page loads queues only one. */
    public const REFRESH_LOCK_KEY = 'geo:lian-risk-map:v2:refresh-lock';

    /** Held while one process rates the 19 barangays, so two triggers never compute the same weather twice. */
    private const COMPUTE_LOCK_KEY = 'geo:lian-risk-map:v2:compute-lock';

    private bool $refreshScheduled = false;

    /** A stored map older than this also triggers a background refresh. */
    private const REFRESH_AFTER_MINUTES = 60;

    public const CACHE_TTL_MINUTES = 20;

    private const GEOJSON_RELATIVE_PATH = 'geojson/lian-barangays.geojson';


    public function __construct(
        private readonly LianBarangayWeatherService $weatherService,
        private readonly DecisionSupportService $decisionSupport,
        private readonly PredictionOrchestrator $predictionOrchestrator,
        private readonly WeatherIconMapper $weatherIcons,
    ) {}

    /**
     * A small icon/condition label for a barangay's current weather, derived
     * from Open-Meteo's own weather_code (already fetched by
     * LianBarangayWeatherService, not a new data source) via the existing,
     * already-used-elsewhere WeatherIconMapper. Presentation only — never
     * consulted by evaluate()/AgriculturalRiskScorer.
     *
     * @param  array<string, mixed>  $weather
     * @return array{icon: string, condition: string}
     */
    private function weatherIconFields(array $weather): array
    {
        $code = isset($weather['weather_code']) ? (int) $weather['weather_code'] : null;
        $isDay = $weather['is_day'] ?? true;

        return $this->weatherIcons->map($code, $isDay === null ? true : (bool) $isDay);
    }

    /**
     * The display block for one barangay's shared weather record (LianBarangayWeatherService), with its own
     * freshness and timestamps. Values are the provider's; a missing value stays null.
     *
     * @param  array<string, mixed>  $weather
     * @return array<string, mixed>
     */
    public function weatherBlock(array $weather): array
    {
        $status = $weather['status'] ?? 'unavailable';
        $hasValues = $status !== 'unavailable' && is_numeric($weather['temperature_c'] ?? null);

        return [
            'status' => $hasValues ? $status : 'unavailable',
            'is_fresh' => $hasValues && ($weather['is_fresh'] ?? false) === true,
            'age_minutes' => $weather['age_minutes'] ?? null,
            'message' => $weather['message'] ?? null,
            'provider' => $weather['provider'] ?? LianBarangayWeatherService::PROVIDER,
            'temperature_c' => $weather['temperature_c'] ?? null,
            'humidity_percent' => $weather['humidity_percent'] ?? null,
            'precipitation_mm' => $weather['precipitation_mm'] ?? $weather['rainfall_now_mm'] ?? null,
            'rain_probability_percent' => $weather['rain_probability_percent'] ?? $weather['precip_probability_percent'] ?? null,
            'rainfall_24h_mm' => $weather['rainfall_24h_mm'] ?? null,
            'rainfall_7d_mm' => $weather['rainfall_7d_mm'] ?? null,
            'rainfall_status' => $weather['rainfall_status'] ?? null,
            'wind_speed_kmh' => $weather['wind_speed_kmh'] ?? $weather['wind_kmh'] ?? null,
            'observed_at' => $weather['observed_at'] ?? null,
            'fetched_at' => $weather['fetched_at'] ?? null,
            ...($hasValues ? $this->weatherIconFields($weather) : []),
        ];
    }

    /**
     * Replace each barangay's weather with the latest shared reading, read from the cache (no online request).
     * The risk rating and its own calculation time are left exactly as computed: refreshed weather never gives
     * a risk a newer timestamp.
     *
     * @param  array<string, mixed>  $map
     * @return array<string, mixed>
     */
    public function withCurrentWeather(array $map): array
    {
        $records = $this->weatherService->snapshot();

        $map['barangays'] = collect($map['barangays'] ?? [])->map(function (array $entry) use ($records): array {
            $canonical = LianBarangays::canonicalName($entry['barangay'] ?? null);
            $entry['risk_calculated_at'] = ($entry['risk_level'] ?? 'No Data') === 'No Data' ? null : ($entry['updated_at'] ?? null);
            $entry['weather'] = $this->weatherBlock($canonical !== null ? ($records[$canonical] ?? []) : []);

            return $entry;
        })->values()->all();
        $map['weather_summary'] = $this->weatherService->summary($records);

        return $map;
    }

    /**
     * Barangays whose risk refresh failed today (weather lookup threw).
     * Read by the IT-expert dashboard.
     */
    public static function failuresToday(): int
    {
        return (int) Cache::get('metrics:risk-map:failures:'.\App\Support\BusinessClock::today()->toDateString(), 0);
    }

    private static function recordFailure(): void
    {
        $key = 'metrics:risk-map:failures:'.\App\Support\BusinessClock::today()->toDateString();

        if (! Cache::has($key)) {
            Cache::put($key, 0, now()->addDay());
        }

        Cache::increment($key);
    }

    /**
     * Full 19-barangay risk map, cached under self::CACHE_KEY.
     */
    public function riskMap(bool $refresh = false): array
    {
        if ($refresh) {
            Cache::forget(self::CACHE_KEY);
        }

        $cached = Cache::get(self::CACHE_KEY);

        // An earlier cold-cache response could contain an empty barangay list.
        // Never treat that as a valid map: the Leaflet layer still has 19
        // polygons and would otherwise count every one as No Data.
        if (is_array($cached) && count($cached['barangays'] ?? []) === count(LianBarangays::all())) {
            return $cached;
        }

        if (is_array($cached)) {
            Cache::forget(self::CACHE_KEY);
        }

        // A page a person is waiting on never computes the map (about 19 weather lookups and 19
        // database writes). It shows the last complete map and a background job refreshes it; the
        // scheduled refresh normally keeps it current before anyone notices.
        if (! $refresh && InteractiveRequest::isPageRequest()) {
            return $this->lastKnownMap();
        }

        $map = $this->retainLastKnownGood($this->compute($refresh));

        Cache::put(self::CACHE_KEY, $map, now()->addMinutes(self::CACHE_TTL_MINUTES));
        Cache::forever(self::LATEST_KEY, $map);

        return $map;
    }

    /**
     * The map exactly as /api/lian/risk-map serves it now, without ever computing it. Dashboards build their
     * risk counts from this so the KPI, the Municipal Risk Overview and the map colours are one set of ratings.
     */
    public function snapshot(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached) && count($cached['barangays'] ?? []) === count(LianBarangays::all())) {
            return $cached;
        }

        return $this->lastKnownMap();
    }

    /**
     * Queue the slow 19-barangay refresh without making a web request wait for
     * 19 external weather calls. The current cached/stored map remains the
     * response until the queue worker swaps in the refreshed result.
     */
    public function queueRefresh(): bool
    {
        if (! Cache::add(self::REFRESH_LOCK_KEY, true, now()->addMinutes(10))) {
            return false;
        }

        try {
            \App\Jobs\RefreshBarangayRiskMap::dispatch()
                ->onConnection((string) config('services.farming_ai.queue_connection', 'database'))
                ->onQueue((string) config('services.farming_ai.queue', 'analytics'))
                ->afterCommit();
        } catch (Throwable $exception) {
            Cache::forget(self::REFRESH_LOCK_KEY);
            Log::warning('Barangay risk map: refresh could not be queued.', ['message' => $exception->getMessage()]);

            return false;
        }

        return true;
    }

    /**
     * @return array{generated_at: ?string, source_note: string, barangays: array<int, array<string, mixed>>, stale?: bool}
     */
    private function lastKnownMap(): array
    {
        $latest = Cache::get(self::LATEST_KEY);
        $generatedAt = is_array($latest) ? ($latest['generated_at'] ?? null) : null;
        $old = $generatedAt === null || \Illuminate\Support\Carbon::parse($generatedAt)->lt(now()->subMinutes(self::REFRESH_AFTER_MINUTES));

        // Also when the shared weather is newer than the weather these ratings were made from. The queue is only
        // an accelerator: the scheduled weather command rates the barangays itself after every refresh.
        if ($old || $this->isBehindWeather($latest)) {
            $this->queueRefresh();
        }

        if (is_array($latest) && count($latest['barangays'] ?? []) === count(LianBarangays::all())) {
            return $this->withStoredVerifiedRatings($latest) + ['stale' => true];
        }

        // On a cold install, use persisted verified risk rows immediately.
        // Computing live weather for all 19 barangays belongs in the
        // scheduler/after-response refresh, otherwise a normal page request can
        // exceed PHP's 30-second execution limit. Only a computed map is ever
        // kept as the long-lived LATEST map, so a fallback built while nothing
        // is rated yet can never stick.
        $map = $this->storedMap();
        Cache::put(self::CACHE_KEY, $map, now()->addMinutes(self::CACHE_TTL_MINUTES));

        return $map;
    }

    /**
     * A barangay's last verified rating as persisted by persist(), shown with its own calculation time while a
     * newer rating is being made. Install placeholders never get here (see storedVerifiedEntries()).
     *
     * @return array<string, mixed>
     */
    private function storedVerifiedEntry(string $barangay, HeatmapArea $area, ?string $psgc): array
    {
        return [
            'barangay' => $barangay,
            'psgc_10d' => $psgc,
            'risk_level' => $area->risk_level,
            'risk_score' => $area->risk_score !== null ? (float) $area->risk_score : null,
            'color' => $this->colorFor($area->risk_level),
            'status' => 'stale',
            'updated_at' => $area->risk_computed_at?->toIso8601String() ?? $area->updated_at?->toIso8601String(),
            'weather' => [
                'status' => 'unavailable',
                'message' => 'Live weather is refreshing. Showing the last verified risk result.',
                'temperature_c' => null,
                'rainfall_24h_mm' => null,
                'humidity_percent' => null,
                'wind_speed_kmh' => null,
            ],
            'monthly_rainfall' => [
                'value_mm' => null,
                'unit' => 'mm/calendar month',
                'available' => false,
                'message' => 'Live risk refresh is still in progress.',
            ],
            'last_recorded_yield' => null,
            'advisory' => null,
            'risk_breakdown' => $area->risk_components,
            'last_known_good' => true,
            'stale_reason' => 'Risk assessment is being refreshed. Showing the last verified rating.',
        ];
    }

    /**
     * Last verified (computed, never placeholder) rating per barangay from the database.
     *
     * @return array<string, array<string, mixed>>
     */
    private function storedVerifiedEntries(): array
    {
        $psgcMap = $this->safePsgcMap();
        $entries = [];

        foreach ($this->existingAreas() as $barangay => $area) {
            if (! $area->isSeedPlaceholder() && in_array($area->risk_level, ['Low', 'Moderate', 'High', 'Severe'], true)) {
                $entries[$barangay] = $this->storedVerifiedEntry($barangay, $area, $psgcMap[$barangay] ?? null);
            }
        }

        return $entries;
    }

    /**
     * A cached map that says "No Data" for a barangay whose verified rating is in the database shows that rating
     * (with its own time) instead: No Data is only shown when no verified rating has ever been made.
     *
     * @param  array<string, mixed>  $map
     * @return array<string, mixed>
     */
    private function withStoredVerifiedRatings(array $map): array
    {
        if (collect($map['barangays'] ?? [])->every(fn (array $entry): bool => ($entry['risk_level'] ?? 'No Data') !== 'No Data')) {
            return $map;
        }

        $stored = $this->storedVerifiedEntries();
        $map['barangays'] = collect($map['barangays'])->map(
            fn (array $entry): array => ($entry['risk_level'] ?? 'No Data') === 'No Data' && isset($stored[$entry['barangay']]) ? $stored[$entry['barangay']] : $entry
        )->values()->all();

        return $map;
    }

    /** True when the shared weather has a newer successful refresh than the one this map was rated from. */
    private function isBehindWeather(mixed $map): bool
    {
        $weatherGeneration = $this->weatherService->summary()['last_success_at'] ?? null;

        return $weatherGeneration !== null && (! is_array($map) || ($map['weather_generation'] ?? null) !== $weatherGeneration);
    }

    /**
     * Rate the 19 barangays from the current shared weather, unless these exact readings are already rated.
     * Called by the scheduled weather command after every refresh and after Refresh weather & risk, so the ratings
     * follow the weather without depending on a queue worker. Returns the new map, or null when nothing was done.
     */
    public function refreshForCurrentWeather(bool $force = false): ?array
    {
        $latest = Cache::get(self::LATEST_KEY);
        $complete = is_array($latest) && count($latest['barangays'] ?? []) === count(LianBarangays::all());
        if (! $force && $complete && ! $this->isBehindWeather($latest)) {
            return null;
        }

        $lock = Cache::lock(self::COMPUTE_LOCK_KEY, 120);
        if (! rescue(fn () => $lock->get(), false, false)) {
            return null;
        }

        try {
            $this->refreshCache();

            return Cache::get(self::LATEST_KEY);
        } catch (Throwable $exception) {
            // The previous verified ratings stay exactly as they were.
            Log::error('Barangay risk map: rating the current weather failed; the last verified ratings are kept.', ['message' => $exception->getMessage()]);

            return null;
        } finally {
            rescue(fn () => $lock->release(), report: false);
            Cache::forget(self::REFRESH_LOCK_KEY);
        }
    }

    /** Rate the barangays after the current response has been sent (Refresh weather & risk); no worker needed. */
    public function refreshAfterResponse(): bool
    {
        if ($this->refreshScheduled) {
            return false;
        }

        $this->refreshScheduled = true;
        app()->terminating(function (): void {
            // Once only: an application that serves several requests calls every terminating callback again.
            if (! $this->refreshScheduled) {
                return;
            }
            rescue(fn () => $this->refreshForCurrentWeather(), report: false);
            $this->refreshScheduled = false;
        });

        return true;
    }

    /** Build a complete, fast response from the last persisted risk rows. */
    private function storedMap(): array
    {
        $psgcMap = $this->safePsgcMap();
        $areas = $this->existingAreas();
        $rows = [];

        foreach (LianBarangays::all() as $barangay) {
            $area = $areas[$barangay] ?? null;

            if ($area === null || $area->isSeedPlaceholder() || ! in_array($area->risk_level, ['Low', 'Moderate', 'High', 'Severe'], true)) {
                $rows[] = $this->noDataResult(
                    $barangay,
                    $psgcMap[$barangay] ?? null,
                    ['status' => 'unavailable', 'message' => 'Live risk refresh is still in progress.'],
                    null,
                    false,
                    ['available' => false, 'value' => null, 'message' => 'Live risk refresh is still in progress.']
                );

                continue;
            }

            $rows[] = $this->storedVerifiedEntry($barangay, $area, $psgcMap[$barangay] ?? null);
        }

        $generatedAt = collect($rows)->pluck('updated_at')->filter()->max() ?: now()->toIso8601String();

        return [
            'generated_at' => $generatedAt,
            'source_note' => AgriculturalRiskScorer::METHODOLOGY_CAVEAT,
            'barangays' => $rows,
            'stale' => true,
        ];
    }

    /**
     * Recomputes the map and swaps it into the cache in place. Called by the
     * scheduled warm-up so no signed-in user has to sit through a recompute
     * when the 20-minute cache entry lapses.
     */
    public function refreshCache(): void
    {
        $map = $this->retainLastKnownGood($this->compute(false));
        Cache::put(self::CACHE_KEY, $map, now()->addMinutes(self::CACHE_TTL_MINUTES));
        Cache::forever(self::LATEST_KEY, $map);
    }

    /**
     * A provider outage must not replace a verified colored map with an all-grey
     * map. Keep the last verified risk result for only the affected barangays,
     * mark it stale, and let the UI explain that it is not a live reading.
     */
    private function retainLastKnownGood(array $map): array
    {
        if (collect($map['barangays'] ?? [])->every(fn (array $entry): bool => ($entry['risk_level'] ?? 'No Data') !== 'No Data')) {
            return $map;
        }

        $previous = Cache::get(self::LATEST_KEY);
        $previousByBarangay = collect(is_array($previous) && is_array($previous['barangays'] ?? null) ? $previous['barangays'] : [])->keyBy('barangay');
        // A verified rating persisted in the database counts too (the cached map can be missing or cleared).
        $stored = null;
        $fallbackCount = 0;
        $levels = ['Low', 'Moderate', 'High', 'Severe'];

        $map['barangays'] = collect($map['barangays'] ?? [])->map(function (array $entry) use ($previousByBarangay, $levels, &$fallbackCount, &$stored): array {
            if (($entry['risk_level'] ?? 'No Data') !== 'No Data') {
                return $entry;
            }

            $last = $previousByBarangay->get($entry['barangay']);
            if (! is_array($last) || ! in_array($last['risk_level'] ?? null, $levels, true)) {
                $stored ??= $this->storedVerifiedEntries();
                $last = $stored[$entry['barangay']] ?? null;
            }
            if (! is_array($last) || ! in_array($last['risk_level'] ?? null, $levels, true)) {
                return $entry;
            }

            $fallbackCount++;
            $last['status'] = 'stale';
            $last['last_known_good'] = true;
            $last['stale_reason'] = 'Live weather or supporting data could not be refreshed; showing the last verified risk result.';
            if (is_array($last['weather'] ?? null)) {
                $last['weather']['status'] = 'stale';
                $last['weather']['message'] = 'Live weather is temporarily unavailable. This is the last verified risk result.';
            }

            return $last;
        })->values()->all();

        if ($fallbackCount === 0) {
            return $map;
        }

        $map['stale'] = true;
        $map['stale_count'] = $fallbackCount;
        $map['source_note'] = trim((string) ($map['source_note'] ?? '')).' Live refresh was unavailable for '.$fallbackCount.' barangay(s); colored values are last verified results and should be refreshed when the source recovers.';

        return $map;
    }

    /** Age of the cached map in minutes, or null when nothing is cached. */
    public function cachedAgeMinutes(): ?int
    {
        $generatedAt = Cache::get(self::CACHE_KEY)['generated_at'] ?? null;

        return $generatedAt ? (int) \Illuminate\Support\Carbon::parse($generatedAt)->diffInMinutes(now()) : null;
    }

    /**
     * Detailed breakdown (risk factors + advisory) for a single barangay,
     * matched by canonical name. Returns null if the name doesn't resolve to
     * one of the 19 canonical Lian barangays.
     */
    public function detailFor(string $barangay, bool $refresh = false): ?array
    {
        $canonical = LianBarangays::canonicalName($barangay);

        if ($canonical === null) {
            return null;
        }

        $map = $this->riskMap($refresh);
        $entry = collect($map['barangays'])->firstWhere('barangay', $canonical);

        return $entry;
    }

    /**
     * @return array{generated_at: string, source_note: string, barangays: array<int, array<string, mixed>>}
     */
    private function compute(bool $refresh): array
    {
        $psgcMap = $this->safePsgcMap();
        // Monthly rainfall is municipality-wide (sourced from ClimateRecord
        // via MonthlyWeatherRandomForest), so it only needs to be resolved
        // once per compute() pass, not once per barangay. See
        // monthlyRainfall() for why this is deliberately NOT Open-Meteo.
        $monthlyRainfall = $this->monthlyRainfall();
        $results = [];

        // Three queries for all 19 barangays instead of three per barangay —
        // each round trip to Supabase is ~300 ms, so the old loop spent ~17 s
        // just reading rice, farm-type and heat-map rows one barangay at a time.
        $latestYields = $this->latestYieldsByBarangay();
        $farmTypes = $this->dominantFarmTypes();
        $existingAreas = $this->existingAreas();

        // The shared barangay weather records (one batched provider request for all 19 when they are due),
        // the same readings the Heat Map popups, Advisories and Climora show.
        // One calculation time for this pass: the map, the database rows and every dashboard show the same one.
        $computedAt = now();
        $writes = [];

        try {
            $allWeather = $this->weatherService->all($refresh);
        } catch (Throwable $exception) {
            Log::warning('Barangay risk map: weather lookup failed, treating as No Data.', ['message' => $exception->getMessage()]);
            $allWeather = [];
            self::recordFailure();
        }

        foreach (LianBarangays::coordinates() as $barangay => [$latitude, $longitude]) {
            $psgc = $psgcMap[$barangay] ?? null;
            $weather = $allWeather[$barangay] ?? ['status' => 'unavailable', 'stale' => true];

            $lastYield = $latestYields[$barangay] ?? null;
            // Only a current reading (fresh or recent) is scored; a delayed one is the last available value, not
            // today's conditions. Temperature is a scored input: a reading without it is never read as 0 °C.
            $hasFreshWeather = ($weather['is_fresh'] ?? false) === true && is_numeric($weather['temperature_c'] ?? null);
            $hasMonthlyRainfall = $monthlyRainfall['available'];

            // Two independent guards: temperature/humidity/wind context comes
            // from live per-barangay Open-Meteo, while the scorer's rainfall
            // input comes from the municipality-wide monthly forest. Both
            // required inputs must be real/available for a real score —
            // either one missing means this barangay is No Data.
            if (! $hasFreshWeather || ! $hasMonthlyRainfall) {
                $results[] = $this->noDataResult($barangay, $psgc, $weather, $lastYield, $hasFreshWeather, $monthlyRainfall);

                continue;
            }

            $farmType = $farmTypes[$barangay] ?? FarmerProfile::FARM_TYPE_RAINFED;
            $evaluation = $this->evaluate($barangay, $weather, $lastYield, $farmType, $monthlyRainfall['value']);
            $results[] = ['updated_at' => $computedAt->toIso8601String()] + $this->realResult($barangay, $psgc, $weather, $lastYield, $farmType, $evaluation, $monthlyRainfall['value']);
            $writes[] = compact('barangay', 'latitude', 'longitude', 'weather', 'evaluation') + ['existing' => $existingAreas[$barangay] ?? null];
        }

        $this->persistBatch($writes, $computedAt);

        return [
            'generated_at' => $computedAt->toIso8601String(),
            // Which shared weather refresh these ratings were made from (refreshForCurrentWeather() skips it next time).
            'weather_generation' => rescue(fn () => $this->weatherService->summary()['last_success_at'] ?? null, null, false),
            'source_note' => AgriculturalRiskScorer::METHODOLOGY_CAVEAT,
            'barangays' => $results,
        ];
    }

    /**
     * Resolves the monthly-scale rainfall figure fed to the scorer, once per
     * compute() pass.
     *
     * Boundary note (see also AgriculturalRiskScorer's class docblock):
     * AgriculturalRiskScorer's rainfall thresholds (e.g. 180-280mm "ideal",
     * <80mm "worst") are calendar-month totals. `LianBarangayWeatherService`
     * (Open-Meteo) only ever returns short-range figures — a 24h or 7-day
     * forecast sum, per barangay — which is correct for weather/advisory
     * display but is NOT a monthly figure under any transformation, and is
     * never multiplied/scaled into a fake month here.
     *
     * `MonthlyWeatherRandomForest` is the app's one existing, already-trusted
     * source of "monthly rainfall" (the same object PredictionEngine already
     * feeds into this exact scorer for the System Prediction / AI Assistant
     * pages). It trains live from `ClimateRecord`, a municipality-wide (not
     * per-barangay) table, so the value returned here is intentionally the
     * SAME across all 19 barangays for a given computation — barangay-level
     * differentiation still comes from AgriculturalRiskScorer's own
     * BARANGAY_EXPOSURE table and each barangay's last recorded yield.
     *
     * Returns ['available' => false, 'value' => null, ...] — never 0.0, never
     * a fabricated figure, never a raw/unvalidated ClimateRecord row — when
     * the forest is not "ready" (e.g. too few usable monthly observations, or
     * a training failure). Callers must treat that as No Data for every
     * barangay in this pass, exactly like missing fresh weather, rather than
     * calling the scorer with a placeholder rainfall value.
     *
     * @return array{available: bool, value: ?float, message: ?string}
     */
    private function monthlyRainfall(): array
    {
        try {
            // Reads through the same centralized, persisted/cached
            // orchestrator the weather-predictions page uses — this no
            // longer independently retrains MonthlyWeatherRandomForest on
            // every riskMap() cache miss (still at most once per 20-minute
            // riskMap() cache window either way, but now also benefits from
            // the longer-lived persisted table across separate cache windows).
            $result = $this->predictionOrchestrator->monthlyWeatherForecast(\App\Support\BusinessClock::now()->startOfMonth()->toImmutable());
        } catch (Throwable $exception) {
            Log::warning('Barangay risk map: monthly rainfall forest failed, treating monthly rainfall as unavailable.', [
                'message' => $exception->getMessage(),
            ]);

            return [
                'available' => false,
                'value' => null,
                'message' => 'Monthly rainfall prediction is not currently available.',
            ];
        }

        if (! ($result['ready'] ?? false)) {
            return [
                'available' => false,
                'value' => null,
                'message' => $result['message'] ?? 'Not enough monthly climate history is available yet to compute monthly rainfall.',
            ];
        }

        $value = $result['predictions']['rainfall'] ?? null;

        if (! is_numeric($value)) {
            return [
                'available' => false,
                'value' => null,
                'message' => 'Monthly rainfall prediction did not return a usable rainfall value.',
            ];
        }

        return ['available' => true, 'value' => (float) $value, 'message' => null];
    }

    private function evaluate(string $barangay, array $weather, ?RiceProduction $lastYield, string $farmType, float $monthlyRainfallMm): array
    {
        // Short-range Open-Meteo figures ($weather['rainfall_24h_mm'] /
        // ['rainfall_7d_mm'], mm/24h and mm/7d, per barangay) are used only
        // for display/advisory context elsewhere in this class (see
        // realResult()) — they are never fed to the scorer below.
        $input = [
            // Monthly rainfall (mm/calendar month, municipality-wide, from
            // MonthlyWeatherRandomForest) is the ONLY rainfall figure that
            // reaches DecisionSupportService/AgriculturalRiskScorer.
            'rainfall' => $monthlyRainfallMm,
            // Missing wind / humidity stay null (DecisionSupportService skips those rules); they are not 0.
            'wind_speed' => $weather['wind_speed_kmh'] ?? null,
            'humidity' => $weather['humidity_percent'] ?? null,
            // Temperature genuinely is a live/current per-barangay Open-Meteo
            // reading — the scorer's temperature thresholds are instantaneous,
            // not monthly-scale, so this is correct as-is.
            'temperature' => $weather['temperature_c'] ?? null,
            'predicted_yield' => $lastYield?->yield_per_hectare !== null ? (float) $lastYield->yield_per_hectare : null,
            'season' => $this->currentSeason(),
            'farm_type' => $farmType,
            'barangay' => $barangay,
        ];

        return $this->decisionSupport->evaluate($input);
    }

    private function noDataResult(string $barangay, ?string $psgc, array $weather, ?RiceProduction $lastYield, bool $hasFreshWeather, array $monthlyRainfall): array
    {
        $message = ! $hasFreshWeather
            ? ($weather['message'] ?? 'Live weather data is not currently available for this barangay.')
            : ($monthlyRainfall['message'] ?? 'Monthly rainfall data is not currently available.');

        // When fresh Open-Meteo weather IS available but the barangay is
        // still No Data (because monthly rainfall isn't), still surface the
        // real short-range weather fields for display/context — only the
        // scored result is missing, not the weather reading itself.
        $weatherBlock = $hasFreshWeather
            ? ['message' => $message] + $this->weatherBlock($weather)
            : [
                'status' => $weather['status'] ?? 'unavailable',
                'message' => $message,
            ];

        return [
            'barangay' => $barangay,
            'psgc_10d' => $psgc,
            'risk_level' => 'No Data',
            'risk_score' => null,
            'color' => 'grey',
            'status' => 'no_data',
            'updated_at' => null,
            'weather' => $weatherBlock,
            'no_data_reason' => $message,
            // No monthly rainfall figure was used to produce a score — this
            // barangay's No Data is (at least partly) because of that.
            'monthly_rainfall' => [
                'value_mm' => null,
                'unit' => 'mm/calendar month',
                'scope' => 'municipality-wide (Lian, Batangas) — same for all barangays',
                'source' => 'iClimate monthly Random Forest model (MonthlyWeatherRandomForest)',
                'available' => $monthlyRainfall['available'],
                'message' => $monthlyRainfall['message'],
            ],
            'last_recorded_yield' => $lastYield?->yield_per_hectare !== null ? [
                'value' => (float) $lastYield->yield_per_hectare,
                'unit' => 'tons/hectare',
                'label' => 'Last recorded yield',
                'year' => $lastYield->year,
                'season' => $lastYield->season,
            ] : null,
            'advisory' => null,
            'risk_breakdown' => null,
        ];
    }

    private function realResult(string $barangay, ?string $psgc, array $weather, ?RiceProduction $lastYield, string $farmType, array $evaluation, float $monthlyRainfallMm): array
    {
        $risk = $evaluation['risk'];

        return [
            'barangay' => $barangay,
            'psgc_10d' => $psgc,
            'risk_level' => $risk['level'],
            'risk_score' => $risk['score'],
            'color' => $this->colorFor($risk['level']),
            'status' => $weather['stale'] ?? false ? 'stale' : 'ok',
            'updated_at' => now()->toIso8601String(),
            // Short-range Open-Meteo figures — display/advisory context
            // only, NOT what was scored (see 'monthly_rainfall' below).
            'weather' => $this->weatherBlock($weather),
            // The monthly-scale rainfall figure actually fed to the scorer
            // (mm/calendar month, municipality-wide — see monthlyRainfall()).
            // Deliberately reported separately from 'weather' above so the UI
            // never conflates it with the per-barangay short-range figures.
            'monthly_rainfall' => [
                'value_mm' => round($monthlyRainfallMm, 2),
                'unit' => 'mm/calendar month',
                'scope' => 'municipality-wide (Lian, Batangas) — same for all barangays',
                'source' => 'iClimate monthly Random Forest model (MonthlyWeatherRandomForest)',
            ],
            'last_recorded_yield' => $lastYield?->yield_per_hectare !== null ? [
                'value' => (float) $lastYield->yield_per_hectare,
                'unit' => 'tons/hectare',
                'label' => 'Last recorded yield',
                'year' => $lastYield->year,
                'season' => $lastYield->season,
            ] : null,
            'farm_type_context' => $farmType,
            'advisory' => [
                'planting' => $evaluation['planting']['recommendation'] ?? null,
                'irrigation' => $evaluation['irrigation']['recommendation'] ?? null,
                'warnings' => $evaluation['weather']['warnings'] ?? [],
                'overall_recommendation' => $evaluation['overall_recommendation'] ?? null,
            ],
            'risk_breakdown' => $evaluation['risk_breakdown'] ?? null,
        ];
    }

    /** Persist all valid ratings in one short transaction; a failed batch falls back to isolated row saves. */
    private function persistBatch(array $writes, CarbonInterface $computedAt): void
    {
        $rows = [];
        $unchangedIds = [];
        $now = now();

        foreach ($writes as $write) {
            $attributes = $this->riskAttributes($write['barangay'], $write['latitude'], $write['longitude'], $write['weather'], $write['evaluation'], $computedAt);
            $existing = $write['existing'];
            if ($existing instanceof HeatmapArea) {
                $existing->fill(array_diff_key($attributes, ['risk_computed_at' => true]));
                if (! $existing->isDirty()) {
                    $unchangedIds[] = (int) $existing->getKey();
                    continue;
                }
            }
            $rows[] = $attributes + ['created_at' => $now, 'updated_at' => $now];
        }

        try {
            if ($rows !== []) {
                DB::transaction(fn () => HeatmapArea::query()->upsert(
                    $rows,
                    ['barangay'],
                    ['latitude', 'longitude', 'risk_level', 'risk_type', 'risk_score', 'predicted_yield', 'rainfall_status', 'planting_advisory', 'irrigation_recommendation', 'description', 'main_risk_reason', 'suggested_action', 'risk_computed_at', 'updated_at'],
                ));
                \App\Support\CacheScope::changed(HeatmapArea::class);
            }
        } catch (Throwable $exception) {
            Log::warning('Barangay risk map: bulk persistence failed; retrying ratings individually.', ['message' => $exception->getMessage()]);
            foreach ($writes as $write) {
                $this->persistOne($write, $computedAt);
            }
        }

        $this->stampUnchanged($unchangedIds, $computedAt);
    }

    /** @return array<string, mixed> */
    private function riskAttributes(string $barangay, float $latitude, float $longitude, array $weather, array $evaluation, CarbonInterface $computedAt): array
    {
        $risk = $evaluation['risk'];
        $stressLabels = array_map(fn (array $f): string => (string) ($f['label'] ?? ''), $evaluation['stress_factors'] ?? []);
        $temperature = is_numeric($weather['temperature_c'] ?? null) ? (float) $weather['temperature_c'] : null;
        $riskType = match (true) {
            in_array('Flooding risk', $stressLabels, true), in_array('Wet field risk', $stressLabels, true) => 'Flood',
            in_array('Drought stress', $stressLabels, true), in_array('Limited rainfall', $stressLabels, true) => 'Drought',
            in_array('Strong wind', $stressLabels, true) => 'Typhoon',
            $temperature !== null && $temperature >= 32 => 'Heat',
            default => null,
        };
        $description = 'Computed from live barangay weather and last recorded rice yield via AgriculturalRiskScorer.';

        return [
            'barangay' => $barangay, 'latitude' => $latitude, 'longitude' => $longitude,
            'risk_level' => $risk['level'], 'risk_type' => $riskType, 'risk_score' => $risk['score'],
            'predicted_yield' => $evaluation['yield']['predicted_yield'] ?? null,
            'rainfall_status' => $weather['rainfall_status'] ?? null,
            'planting_advisory' => $evaluation['planting']['recommendation'] ?? null,
            'irrigation_recommendation' => $evaluation['irrigation']['recommendation'] ?? null,
            'description' => $description, 'main_risk_reason' => $description,
            'suggested_action' => $evaluation['irrigation']['recommendation'] ?? $evaluation['planting']['recommendation'] ?? 'Continue routine field monitoring.',
            'risk_computed_at' => $computedAt,
        ];
    }

    private function persistOne(array $write, CarbonInterface $computedAt): void
    {
        try {
            ($write['existing'] ?? new HeatmapArea(['barangay' => $write['barangay']]))
                ->fill($this->riskAttributes($write['barangay'], $write['latitude'], $write['longitude'], $write['weather'], $write['evaluation'], $computedAt))
                ->save();
        } catch (Throwable $exception) {
            Log::warning('Barangay risk map: failed to persist HeatmapArea row.', ['barangay' => $write['barangay'], 'message' => $exception->getMessage()]);
        }
    }

    /** Restamp the calculation time of ratings that came out unchanged: one query for all of them. */
    private function stampUnchanged(array $ids, CarbonInterface $computedAt): void
    {
        if ($ids === []) {
            return;
        }

        try {
            HeatmapArea::query()->whereKey($ids)->update(['risk_computed_at' => $computedAt]);
        } catch (Throwable $exception) {
            Log::warning('Barangay risk map: could not restamp unchanged ratings.', ['message' => $exception->getMessage()]);
        }
    }

    /**
     * Latest recorded yield row per barangay (same ordering the per-barangay
     * query used, so null-month handling is unchanged).
     *
     * @return array<string, RiceProduction>
     */
    public function latestYieldsByBarangay(): array
    {
        try {
            $rows = \App\Support\SampleData::without(RiceProduction::query(), 'rice_productions')
                ->orderByDesc('year')
                ->orderByDesc('production_month')
                ->get();

            return $rows
                ->filter(fn (RiceProduction $row): bool => LianBarangays::canonicalName($row->barangay) !== null)
                ->unique(fn (RiceProduction $row): string => LianBarangays::canonicalName($row->barangay))
                ->mapWithKeys(fn (RiceProduction $row): array => [LianBarangays::canonicalName($row->barangay) => $row])
                ->all();
        } catch (Throwable $exception) {
            Log::warning('Barangay risk map: rice production lookup failed.', ['message' => $exception->getMessage()]);

            return [];
        }
    }

    /**
     * Most common real farm type per barangay; barangays without farmer data
     * are absent, and callers fall back to the documented Rainfed default.
     *
     * @return array<string, string>
     */
    public function dominantFarmTypes(): array
    {
        try {
            $rows = FarmerProfile::query()
                ->whereNotNull('farm_type')
                ->selectRaw('barangay, farm_type, COUNT(*) as total')
                ->groupBy('barangay', 'farm_type')
                ->orderByDesc('total')
                ->get();

            return $rows
                ->filter(fn ($row): bool => LianBarangays::canonicalName($row->barangay) !== null)
                ->groupBy(fn ($row): string => LianBarangays::canonicalName($row->barangay))
                ->map(fn ($rows): string => (string) $rows->first()->farm_type)
                ->all();
        } catch (Throwable $exception) {
            Log::warning('Barangay risk map: farm type lookup failed.', ['message' => $exception->getMessage()]);

            return [];
        }
    }

    /** @return array<string, HeatmapArea> */
    private function existingAreas(): array
    {
        try {
            // Several rows for one barangay: the most recently computed one wins (later keys overwrite earlier ones),
            // the same rule DashboardData::currentRiskRatings() uses, so the map and the dashboards never disagree.
            return HeatmapArea::query()->get()
                ->filter(fn (HeatmapArea $area): bool => LianBarangays::canonicalName($area->barangay) !== null)
                ->sortBy(fn (HeatmapArea $area): string => ($area->risk_computed_at?->format('YmdHis') ?? '0').str_pad((string) $area->id, 12, '0', STR_PAD_LEFT))
                ->mapWithKeys(fn (HeatmapArea $area): array => [LianBarangays::canonicalName($area->barangay) => $area])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function lastRecordedYield(string $barangay): ?RiceProduction
    {
        try {
            return RiceProduction::query()
                ->where('barangay', $barangay)
                ->orderByDesc('year')
                ->orderByDesc('production_month')
                ->first();
        } catch (Throwable $exception) {
            Log::warning('Barangay risk map: rice production lookup failed.', [
                'barangay' => $barangay,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Most-common real farm_type recorded for this barangay's farmers.
     * Falls back to the documented Rainfed default (FarmerProfile's own
     * canonical default) when there is no farmer data — never fabricated.
     */
    private function dominantFarmType(string $barangay): string
    {
        try {
            $counts = FarmerProfile::query()
                ->where('barangay', $barangay)
                ->whereNotNull('farm_type')
                ->selectRaw('farm_type, COUNT(*) as total')
                ->groupBy('farm_type')
                ->orderByDesc('total')
                ->pluck('total', 'farm_type');

            if ($counts->isNotEmpty()) {
                return (string) $counts->keys()->first();
            }
        } catch (Throwable $exception) {
            Log::warning('Barangay risk map: farm type lookup failed.', [
                'barangay' => $barangay,
                'message' => $exception->getMessage(),
            ]);
        }

        return FarmerProfile::FARM_TYPE_RAINFED;
    }

    private function currentSeason(): string
    {
        return \App\Models\ClimateRecord::seasonForMonth(\App\Support\BusinessClock::month());
    }

    private function colorFor(string $level): string
    {
        return match ($level) {
            'Severe' => 'darkred',
            'High' => 'red',
            'Moderate' => 'yellow',
            'Low' => 'green',
            default => 'grey',
        };
    }

    /**
     * Name -> psgc_10d map built by matching the bundled barangay GeoJSON's
     * brgy_name against the 19 canonical LianBarangays names. Anything that
     * doesn't confidently match a canonical name is skipped (logged), never
     * guessed. Missing/malformed GeoJSON degrades gracefully to an empty map
     * (barangays are still scored, just without a psgc_10d join key).
     */
    private function safePsgcMap(): array
    {
        try {
            $path = public_path(self::GEOJSON_RELATIVE_PATH);

            if (! is_file($path)) {
                Log::warning('Barangay risk map: barangay GeoJSON file not found.', ['path' => $path]);

                return [];
            }

            $contents = file_get_contents($path);
            $geojson = $contents !== false ? json_decode($contents, true) : null;

            if (! is_array($geojson) || ! isset($geojson['features']) || ! is_array($geojson['features'])) {
                Log::warning('Barangay risk map: barangay GeoJSON is malformed.', ['path' => $path]);

                return [];
            }

            $map = [];

            foreach ($geojson['features'] as $feature) {
                $name = (string) ($feature['properties']['brgy_name'] ?? '');
                $psgc = $feature['properties']['psgc_10d'] ?? null;
                $canonical = LianBarangays::canonicalName($name);

                if ($canonical === null || $psgc === null) {
                    if ($name !== '') {
                        Log::warning('Barangay risk map: GeoJSON feature did not confidently match a canonical barangay; skipped.', ['name' => $name]);
                    }

                    continue;
                }

                $map[$canonical] = (string) $psgc;
            }

            return $map;
        } catch (Throwable $exception) {
            Log::warning('Barangay risk map: failed to read/parse barangay GeoJSON.', ['message' => $exception->getMessage()]);

            return [];
        }
    }
}
