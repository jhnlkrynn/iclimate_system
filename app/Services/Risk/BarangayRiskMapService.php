<?php

namespace App\Services\Risk;

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

    /** A stored map older than this also triggers a background refresh. */
    private const REFRESH_AFTER_MINUTES = 60;

    public const CACHE_TTL_MINUTES = 20;

    private const GEOJSON_RELATIVE_PATH = 'geojson/lian-barangays.geojson';

    private const WET_SEASON_MONTHS = [5, 6, 7, 8, 9, 10];

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

        if (is_array($cached)) {
            return $cached;
        }

        // A page a person is waiting on never computes the map (about 19 weather lookups and 19
        // database writes). It shows the last complete map and a background job refreshes it; the
        // scheduled refresh normally keeps it current before anyone notices.
        if (! $refresh && InteractiveRequest::isPageRequest()) {
            return $this->lastKnownMap();
        }

        $map = $this->compute($refresh);

        Cache::put(self::CACHE_KEY, $map, now()->addMinutes(self::CACHE_TTL_MINUTES));
        Cache::forever(self::LATEST_KEY, $map);

        return $map;
    }

    /**
     * @return array{generated_at: ?string, source_note: string, barangays: array<int, array<string, mixed>>, stale?: bool}
     */
    private function lastKnownMap(): array
    {
        $latest = Cache::get(self::LATEST_KEY);
        $generatedAt = is_array($latest) ? ($latest['generated_at'] ?? null) : null;
        $old = $generatedAt === null || \Illuminate\Support\Carbon::parse($generatedAt)->lt(now()->subMinutes(self::REFRESH_AFTER_MINUTES));

        if ($old && Cache::add(self::REFRESH_LOCK_KEY, true, now()->addMinutes(10))) {
            \App\Jobs\RefreshBarangayRiskMap::dispatch()
                ->onConnection((string) config('services.farming_ai.queue_connection', 'database'))
                ->onQueue((string) config('services.farming_ai.queue', 'analytics'))
                ->afterCommit();
        }

        if (is_array($latest)) {
            return $latest + ['stale' => true];
        }

        // Nothing has ever been computed: an empty map (every barangay reads as "no data yet").
        return ['generated_at' => null, 'source_note' => AgriculturalRiskScorer::METHODOLOGY_CAVEAT, 'barangays' => [], 'stale' => true];
    }

    /**
     * Recomputes the map and swaps it into the cache in place. Called by the
     * scheduled warm-up so no signed-in user has to sit through a recompute
     * when the 20-minute cache entry lapses.
     */
    public function refreshCache(): void
    {
        Cache::put(self::CACHE_KEY, $this->compute(false), now()->addMinutes(self::CACHE_TTL_MINUTES));
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

        foreach (LianBarangays::coordinates() as $barangay => [$latitude, $longitude]) {
            $psgc = $psgcMap[$barangay] ?? null;

            try {
                $weather = $this->weatherService->forBarangay($barangay, $latitude, $longitude, $refresh);
            } catch (Throwable $exception) {
                Log::warning('Barangay risk map: weather lookup failed, treating as No Data.', [
                    'barangay' => $barangay,
                    'message' => $exception->getMessage(),
                ]);
                $weather = ['status' => 'unavailable', 'stale' => true];
                self::recordFailure();
            }

            $lastYield = $latestYields[$barangay] ?? null;
            // Temperature is a scored input: "fresh" weather without it is not usable (never read as 0 °C).
            $hasFreshWeather = ($weather['status'] ?? null) === 'fresh' && is_numeric($weather['temperature_c'] ?? null);
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
            $results[] = $this->realResult($barangay, $psgc, $weather, $lastYield, $farmType, $evaluation, $monthlyRainfall['value']);
            $this->persist($barangay, $latitude, $longitude, $weather, $evaluation, $existingAreas[$barangay] ?? null);
        }

        return [
            'generated_at' => now()->toIso8601String(),
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
            ? [
                'status' => 'fresh',
                'message' => $message,
                'rainfall_24h_mm' => $weather['rainfall_24h_mm'] ?? null,
                'rainfall_7d_mm' => $weather['rainfall_7d_mm'] ?? null,
                'rainfall_status' => $weather['rainfall_status'] ?? null,
                'temperature_c' => $weather['temperature_c'] ?? null,
                'humidity_percent' => $weather['humidity_percent'] ?? null,
                'wind_speed_kmh' => $weather['wind_speed_kmh'] ?? null,
                'observed_at' => $weather['observed_at'] ?? null,
                ...$this->weatherIconFields($weather),
            ]
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
            'weather' => [
                'status' => 'fresh',
                // Short-range Open-Meteo figures — display/advisory context
                // only, NOT what was scored (see 'monthly_rainfall' below).
                'rainfall_24h_mm' => $weather['rainfall_24h_mm'] ?? null,
                'rainfall_7d_mm' => $weather['rainfall_7d_mm'] ?? null,
                'rainfall_status' => $weather['rainfall_status'] ?? null,
                'temperature_c' => $weather['temperature_c'] ?? null,
                'humidity_percent' => $weather['humidity_percent'] ?? null,
                'wind_speed_kmh' => $weather['wind_speed_kmh'] ?? null,
                'observed_at' => $weather['observed_at'] ?? null,
                ...$this->weatherIconFields($weather),
            ],
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

    private function persist(string $barangay, float $latitude, float $longitude, array $weather, array $evaluation, ?HeatmapArea $existing = null): void
    {
        $risk = $evaluation['risk'];
        $stressLabels = array_map(fn (array $f): string => (string) ($f['label'] ?? ''), $evaluation['stress_factors'] ?? []);
        $temperature = is_numeric($weather['temperature_c'] ?? null) ? (float) $weather['temperature_c'] : null;

        $riskType = match (true) {
            in_array('Flooding risk', $stressLabels, true), in_array('Wet field risk', $stressLabels, true) => 'Flood',
            in_array('Drought stress', $stressLabels, true), in_array('Limited rainfall', $stressLabels, true) => 'Drought',
            in_array('Strong wind', $stressLabels, true) => 'Typhoon',
            $temperature !== null && $temperature >= 32 => 'Heat',
            default => 'Drought',
        };

        try {
            // Same effect as updateOrCreate(), minus its SELECT: the row was preloaded.
            ($existing ?? new HeatmapArea(['barangay' => $barangay]))->fill(
                [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'risk_level' => $risk['level'],
                    'risk_type' => $riskType,
                    'risk_score' => $risk['score'],
                    'predicted_yield' => $evaluation['yield']['predicted_yield'] ?? null,
                    'rainfall_status' => $weather['rainfall_status'] ?? null,
                    'planting_advisory' => $evaluation['planting']['recommendation'] ?? null,
                    'irrigation_recommendation' => $evaluation['irrigation']['recommendation'] ?? null,
                    'description' => 'Computed from live barangay weather and last recorded rice yield via AgriculturalRiskScorer.',
                ]
            )->save();
        } catch (Throwable $exception) {
            Log::warning('Barangay risk map: failed to persist HeatmapArea row.', [
                'barangay' => $barangay,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Latest recorded yield row per barangay (same ordering the per-barangay
     * query used, so null-month handling is unchanged).
     *
     * @return array<string, RiceProduction>
     */
    private function latestYieldsByBarangay(): array
    {
        try {
            return \App\Support\SampleData::without(RiceProduction::query(), 'rice_productions')
                ->orderByDesc('year')
                ->orderByDesc('production_month')
                ->get()
                ->unique('barangay')
                ->keyBy('barangay')
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
    private function dominantFarmTypes(): array
    {
        try {
            return FarmerProfile::query()
                ->whereNotNull('farm_type')
                ->selectRaw('barangay, farm_type, COUNT(*) as total')
                ->groupBy('barangay', 'farm_type')
                ->orderByDesc('total')
                ->get()
                ->groupBy('barangay')
                ->map(fn ($rows) => (string) $rows->first()->farm_type)
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
            return HeatmapArea::query()->get()->keyBy('barangay')->all();
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
        return in_array(\App\Support\BusinessClock::month(), self::WET_SEASON_MONTHS, true) ? 'Wet' : 'Dry';
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
