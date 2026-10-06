<?php

namespace App\Services\Analytics;

use App\Enums\PredictionType;
use App\Models\PlantingAdvisory;
use App\Models\TyphoonSafetyResponse;
use App\Models\User;
use App\Services\Prediction\RiceYieldPredictionService;
use App\Services\Prediction\SeasonYield;
use App\Services\Risk\BarangayRiskMapService;
use App\Services\Risk\BarangayWeekRiskService;
use App\Services\TyphoonSafetyService;
use App\Services\Weather\FarmerDashboardWeatherService;
use App\Support\CacheScope;
use App\Support\FarmerAdvisoryView;
use App\Support\FarmerLanguage;
use App\Support\PageCache;
use App\Support\SampleData;
use App\Support\SchemaCache;
use App\Support\WeatherConditionLabel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The Farmer dashboard (2026-10 simplification): five plain parts, all from saved data (all wording in
 * lang/{en,fil}/farmer.php, section "home"):
 *   1. message  - "Mensahe Ngayon": one main message, from the parts below by fixed rules
 *   2. weather  - today: icon, words and °C; rain and wind in words, never millimetres (FarmerDashboardWeatherService)
 *   3. risk     - the barangay's Heat Map rating for this week as Mababa / Katamtaman / Mataas (BarangayWeekRiskService)
 *   4. harvest  - this season's expected yield in t/ha and Normal / Mas mataas / Mas mababa (stored model result)
 *   5. week     - the next 7 days' weather icons, and the latest planting advisory for the farmer's area
 * plus the Typhoon Safety Check-In, only while a typhoon advisory is in effect.
 *
 * A page never calls a provider or runs a model: weather is the stored reading, the yield is the estimate
 * PrepareFarmerYieldEstimate stored. Old data shows with its time; no data at all says "Walang datos pa".
 */
final class FarmerDashboardService
{
    public const TIMEZONE = 'Asia/Manila';

    /** Same "High" thresholds the MAO overview and the weather calendar use. */
    public const HEAVY_RAIN_MM = MaoDashboardService::HEAVY_RAIN_MM;

    public const STRONG_WIND_KMH = MaoDashboardService::STRONG_WIND_KMH;

    public const VERY_HOT_C = MaoDashboardService::VERY_HOT_C;

    /** Daily rain from this many mm is "moderate"; from HEAVY_RAIN_MM it is "heavy"; from 1 mm it is "light". */
    private const MODERATE_RAIN_MM = 10.0;

    private const LIGHT_RAIN_MM = 1.0;

    /** With no rain amount, a chance of rain from this percent counts as light rain. */
    private const RAIN_CHANCE_PERCENT = 50.0;

    /** Days of forecast on the dashboard. */
    public const WEEK_DAYS = 7;

    /** How many days ahead a heavy-rain or strong-wind message looks (today and tomorrow). */
    private const MESSAGE_LOOKAHEAD_DAYS = 1;

    public function __construct(
        private readonly FarmerDashboardWeatherService $weather,
        private readonly BarangayRiskMapService $risk,
        private readonly BarangayWeekRiskService $weekRisk,
        private readonly RiceYieldPredictionService $riceYield,
        private readonly TyphoonSafetyService $typhoon,
        private readonly SeasonYield $seasonYield,
    ) {}

    /**
     * The page is cached per farmer AND per language: the numbers are the same, the words are not. The key changes when
     * the shared data the page reads changes (CacheScope 'farmer-dashboard') or when the farmer's own rows do
     * (notifications, profile, plans, farm outline: PageCache::flushUser).
     */
    public static function cacheKey(User $user, ?string $locale = null, ?string $token = null): string
    {
        return 'dashboard:farmer:home:'.$user->id.':'.($locale ?? app()->getLocale())
            .':u'.PageCache::userVersion((int) $user->id).':'.($token ?? CacheScope::token('farmer-dashboard'));
    }

    /** Drops the stored page in every language (after the farmer changes something it shows). */
    public static function forgetCached(User $user): void
    {
        foreach (array_keys(FarmerLanguage::SUPPORTED) as $locale) {
            Cache::forget(self::cacheKey($user, $locale));
        }
    }

    /** The stored yield estimate is dropped whenever the farmer's profile or planting schedule changes (see AppServiceProvider). */
    public static function predictionCacheKey(int $userId): string
    {
        return 'dashboard:farmer-prediction:'.$userId;
    }

    // ------------------------------------------------------------------ payload

    /**
     * @param  array<int, string>  $refresh  parts to rebuild instead of reading a cache: weather (a Refresh button), harvest (queue a new estimate)
     * @return array<string, mixed>
     */
    public function payload(User $user, bool $forceWeather = false, array $refresh = []): array
    {
        $now = CarbonImmutable::now(self::TIMEZONE);
        $profile = $user->farmerProfile;
        $barangay = $user->registeredBarangay();

        $weather = $this->weather($forceWeather || in_array('weather', $refresh, true), $now);
        $week = $this->week($weather, $now);
        // A stored barangay that is not one official name (e.g. a bare "Poblacion": Lian has five) is never guessed.
        $stored = trim((string) ($profile?->barangay ?: $user->barangay));
        $risk = $barangay === null && $stored !== '' ? ['state' => 'unverified', 'stored' => $stored] : $this->riskStatus($barangay);
        $harvest = $this->harvest($this->prediction($user, in_array('harvest', $refresh, true)), $profile, $now);
        $typhoon = $this->typhoonCheckIn($user);
        $advisory = $this->latestPlantingAdvisory($barangay);

        return [
            'generated_at' => $now,
            'locale' => app()->getLocale(),
            'greeting' => __('farmer.greeting.'.$this->period($now), ['name' => $this->displayName($user)]),
            'barangay' => $barangay,
            'place' => $barangay ? __('farmer.place', ['barangay' => $barangay]) : __('farmer.place_default'),
            'message' => $this->message($week, $risk, $typhoon, $advisory),
            'weather' => $weather,
            'risk' => $risk,
            'harvest' => $harvest,
            'week' => $week,
            'advisory' => $advisory,
            'typhoon' => $typhoon,
        ];
    }

    private function period(CarbonImmutable $now): string
    {
        return match (true) {
            $now->hour >= 5 && $now->hour < 12 => 'morning',
            $now->hour >= 12 && $now->hour < 18 => 'afternoon',
            default => 'evening',
        };
    }

    /** Given names only: a three-part name drops the last word (John Luke Reynon becomes John Luke). */
    private function displayName(User $user): string
    {
        $parts = preg_split('/\s+/', trim((string) $user->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // A family name can be two words ("Dela Cruz", "De Leon", "San Jose"): both are left out of the greeting.
        if (count($parts) >= 3 && in_array(strtolower($parts[count($parts) - 2]), ['de', 'del', 'dela', 'delos', 'la', 'los', 'san', 'santa', 'santo', 'sta.', 'sto.'], true)) {
            array_pop($parts);
            if (count($parts) >= 3 && in_array(strtolower($parts[count($parts) - 2]), ['de'], true)) {
                array_pop($parts);                                      // "De La Cruz", "De Los Santos"
            }
        }

        return match (true) {
            count($parts) >= 3 => implode(' ', array_slice($parts, 0, -1)),
            count($parts) === 2 => $parts[0],
            default => $parts[0] ?? __('farmer.farmer'),
        };
    }

    /** A weather condition as the farmer reads it ("Light Rain" / "Mahinang Ulan"); an unknown provider phrase stays as given. */
    public static function conditionText(string $condition): string
    {
        $key = 'farmer.conditions.'.str($condition)->lower()->replaceMatches('/[^a-z]+/', '_')->trim('_');

        return Lang::has($key) ? __($key) : $condition;
    }

    // ------------------------------------------------------------------ 2. weather today

    /**
     * The stored Lian reading: icon, words and °C; rain and wind in words. state: ok | nodata.
     *
     * @return array<string, mixed>
     */
    private function weather(bool $force, CarbonImmutable $now): array
    {
        try {
            $w = $this->weather->current($force);
        } catch (Throwable $e) {
            Log::warning('Farmer dashboard weather failed.', ['source' => 'weather service', 'exception' => $e::class, 'message' => $e->getMessage()]);

            return ['state' => 'nodata'];
        }

        $current = is_array($w['current'] ?? null) ? $w['current'] : [];
        if (! ($w['success'] ?? false) || ! is_numeric($current['temperature'] ?? null)) {
            return ['state' => 'nodata'];
        }

        $days = [];
        foreach ((array) ($w['forecast'] ?? []) as $day) {
            if (! is_array($day) || empty($day['date'])) {
                continue;
            }
            $condition = (string) (WeatherConditionLabel::display($day['condition'] ?? null) ?: '');
            $rain = is_numeric($day['rainfall'] ?? null) ? (float) $day['rainfall'] : null;
            $chance = is_numeric($day['precipitation_probability'] ?? null) ? (float) $day['precipitation_probability'] : null;
            $wind = is_numeric($day['wind_speed_max'] ?? null) ? (float) $day['wind_speed_max'] : null;
            $rainKey = self::rainKey($rain, $chance);
            $words = self::dayWords($condition, $rainKey);
            $days[] = [
                'date' => CarbonImmutable::parse($day['date'], self::TIMEZONE),
                'condition' => $words['condition'],
                'rain_words' => $words['rain'],
                'icon' => $this->iconFor($condition, true),
                'rain_key' => $rainKey,
                'heavy' => $rain !== null && $rain >= self::HEAVY_RAIN_MM,
                'strong_wind' => $wind !== null && $wind >= (float) config('farmer_dashboard.strong_wind_kmh', self::STRONG_WIND_KMH),
            ];
        }

        $today = collect($days)->first(fn (array $d) => $d['date']->isSameDay($now));
        $todayTotals = is_array($w['today'] ?? null) ? $w['today'] : [];
        $conditionEn = (string) (WeatherConditionLabel::display($current['condition'] ?? null) ?: '');
        $rainKey = $today['rain_key'] ?? self::rainKey(
            is_numeric($todayTotals['rainfall'] ?? null) ? (float) $todayTotals['rainfall'] : null,
            is_numeric($todayTotals['precipitation_probability'] ?? null) ? (float) $todayTotals['precipitation_probability'] : null,
        );
        $wind = is_numeric($current['wind_speed'] ?? null) ? (float) $current['wind_speed'] : null;

        // The time the reading was fetched (not the page load time).
        $fetched = data_get($w, 'timestamps.server_fetched_at') ?? data_get($w, 'timestamps.provider_updated_at') ?? ($w['fetched_at'] ?? null);
        $updated = $fetched ? CarbonImmutable::parse($fetched)->timezone(self::TIMEZONE) : null;

        return [
            'state' => 'ok',
            'provider' => (string) ($w['provider'] ?? $w['source'] ?? 'Weather'),
            'condition' => $conditionEn !== '' ? self::conditionText($conditionEn) : __('farmer.weather.unknown'),
            'icon' => $this->iconFor($conditionEn, ($current['is_day'] ?? true) !== false),
            'temperature' => (float) $current['temperature'],
            'rain_key' => $rainKey,
            'rain' => $rainKey ? __('farmer.home.rain.'.$rainKey) : null,
            'wind' => self::windWords($wind),
            'updated_at' => $updated,
            'stale' => (bool) ($w['stale'] ?? false) || $updated === null
                || $updated->diffInMinutes($now) > (int) config('farmer_dashboard.weather.stale_after_minutes', 60),
            'days' => $days,
        ];
    }

    /** Rain in words for a day: none, light, moderate or heavy (from the amount, else the chance). Null without either. */
    public static function rainKey(?float $mm, ?float $chance): ?string
    {
        if ($mm !== null) {
            return match (true) {
                $mm >= self::HEAVY_RAIN_MM => 'heavy',
                $mm >= self::MODERATE_RAIN_MM => 'moderate',
                $mm >= self::LIGHT_RAIN_MM => 'light',
                $chance !== null && $chance >= self::RAIN_CHANCE_PERCENT => 'light',
                default => 'none',
            };
        }

        return $chance === null ? null : ($chance >= self::RAIN_CHANCE_PERCENT ? 'light' : 'none');
    }

    /**
     * A day's words: the provider's condition word, unless it is a kind of rain — then only the day's rain words
     * (from the day's total), so "Heavy Rain" never sits next to "Moderate rain".
     *
     * @return array{condition: string, rain: ?string}
     */
    public static function dayWords(string $conditionEn, ?string $rainKey): array
    {
        $rain = $rainKey ? __('farmer.home.rain.'.$rainKey) : null;
        if ($rain !== null && preg_match('/rain|shower|drizzle/i', $conditionEn)) {
            return ['condition' => $rain, 'rain' => null];
        }

        return ['condition' => $conditionEn !== '' ? self::conditionText($conditionEn) : __('farmer.weather.unknown'), 'rain' => $rain];
    }

    /** "Mahangin" or "Malakas na hangin"; calm wind needs no words. */
    public static function windWords(?float $kmh): ?string
    {
        return match (true) {
            $kmh === null => null,
            $kmh >= (float) config('farmer_dashboard.strong_wind_kmh', self::STRONG_WIND_KMH) => __('farmer.home.wind.strong'),
            $kmh >= (float) config('farmer_dashboard.good_work_day.max_wind_kmh', 15) => __('farmer.home.wind.windy'),
            default => null,
        };
    }

    /** One outline icon per weather, so the page never mixes icon styles. */
    private function iconFor(string $condition, bool $day): string
    {
        $text = strtolower($condition);

        return match (true) {
            str_contains($text, 'thunder') || str_contains($text, 'storm') => 'storm',
            str_contains($text, 'rain') || str_contains($text, 'shower') || str_contains($text, 'drizzle') => 'rain',
            str_contains($text, 'fog') || str_contains($text, 'mist') => 'fog',
            str_contains($text, 'partly') || str_contains($text, 'mostly clear') || str_contains($text, 'mostly sunny') => $day ? 'cloud-sun' : 'weather',
            str_contains($text, 'cloud') || str_contains($text, 'overcast') => 'weather',
            str_contains($text, 'clear') || str_contains($text, 'sunny') => $day ? 'sun' : 'moon',
            default => 'weather',
        };
    }

    // ------------------------------------------------------------------ 5a. the next 7 days

    /** @return array{state: string, days: array<int, array<string, mixed>>} */
    private function week(array $weather, CarbonImmutable $now): array
    {
        if ($weather['state'] !== 'ok') {
            return ['state' => 'nodata', 'days' => []];
        }
        $days = array_values(array_filter($weather['days'], fn (array $d) => $d['date']->toDateString() >= $now->toDateString()));
        $days = array_map(fn (array $d) => $d + [
            'is_today' => $d['date']->isSameDay($now),
            'label' => $d['date']->isSameDay($now) ? __('farmer.home.week.today') : $d['date']->locale(app()->getLocale())->translatedFormat('D'),
            'rain' => $d['rain_words'],
        ], array_slice($days, 0, self::WEEK_DAYS));

        return ['state' => $days === [] ? 'nodata' : 'ok', 'days' => $days];
    }

    // ------------------------------------------------------------------ 3. barangay risk (the Heat Map's [This Week] rating)

    /** @return array<string, mixed> state: ok | nodata | nobarangay (payload() adds: unverified) */
    private function riskStatus(?string $barangay): array
    {
        if (! $barangay) {
            return ['state' => 'nobarangay'];
        }
        // The planting rule still comes from the stored barangay record (reading it also queues that record's refresh
        // when it is missing or old); without it the main message simply skips the rule.
        $r = rescue(fn () => $this->risk->detailFor($barangay, false), null, false) ?? [];

        // The same rating the Heat Map opens with, so the badge here and the map there never disagree.
        try {
            $week = $this->weekRisk->forBarangay($barangay, 'week');
        } catch (Throwable $e) {
            Log::warning('Farmer dashboard farm risk failed.', ['source' => 'barangay week risk', 'exception' => $e::class, 'message' => $e->getMessage()]);

            return ['state' => 'nodata'];
        }
        $key = in_array($week['level'] ?? null, ['low', 'moderate', 'high'], true) ? $week['level'] : null;
        if ($key === null) {
            return ['state' => 'nodata'];
        }

        return [
            'state' => 'ok',
            'barangay' => $week['barangay'] ?? $barangay,
            'key' => $key,
            'label' => __('farmer.home.risk.level.'.$key),
            'tone' => ['low' => 'good', 'moderate' => 'watch', 'high' => 'danger'][$key],
            'meaning' => __('farmer.home.risk.meaning.'.$key),
            // The Heat Map's planting rule as one key (DecisionSupportService::plantingAdvisory), for the main message.
            'planting_key' => $this->plantingKey((string) ($r['advisory']['planting'] ?? '')),
            'updated_at' => $week['updated_at'] ?? null,
        ];
    }

    /** The risk map's planting sentence (English, from DecisionSupportService) as a key: its first rule decides. */
    private function plantingKey(string $planting): ?string
    {
        return match (true) {
            str_starts_with($planting, 'Ideal planting') => 'good',
            str_starts_with($planting, 'Planting is possible') => 'possible',
            str_starts_with($planting, 'Plant with caution') => 'caution',
            str_starts_with($planting, 'Delay planting due to flood') => 'wait_flood',
            str_starts_with($planting, 'Wait for sufficient rainfall'), str_starts_with($planting, 'Delay planting during dry season') => 'wait_rain',
            default => null,
        };
    }

    // ------------------------------------------------------------------ 1. Mensahe Ngayon

    /**
     * One main message, the most important first: typhoon, high risk, heavy rain or strong wind today or tomorrow, the
     * Heat Map's planting rule, else a calm reminder. A cheerful planting line is never shown next to a warning: not
     * next to a Moderate rating, and not when the latest planting advisory (shown below it) says to hold off.
     *
     * @return array{key: string, tone: string, icon: string, text: string}
     */
    private function message(array $week, array $risk, ?array $typhoon, ?array $advisory): array
    {
        $make = fn (string $key, string $tone, string $icon, array $params = []): array => ['key' => $key, 'tone' => $tone, 'icon' => $icon, 'text' => __('farmer.home.message.'.$key, $params)];

        if ($typhoon !== null) {
            return $make('typhoon', 'danger', 'storm');
        }
        if (($risk['key'] ?? null) === 'high') {
            return $make('risk_high', 'danger', 'map');
        }
        foreach (array_slice($week['days'], 0, self::MESSAGE_LOOKAHEAD_DAYS + 1) as $day) {
            $when = __('farmer.home.message.'.($day['is_today'] ? 'today' : 'tomorrow'));
            if ($day['heavy']) {
                return $make('heavy_rain', 'watch', 'rain', ['when' => $when]);
            }
            if ($day['strong_wind']) {
                return $make('strong_wind', 'watch', 'wind', ['when' => $when]);
            }
        }

        $planting = $risk['planting_key'] ?? null;
        if ($planting === 'good' && ($risk['key'] ?? null) !== 'low') {
            $planting = 'possible';                                    // never "good" next to a Moderate rating
        }
        if (in_array($planting, ['good', 'possible'], true) && ($advisory['cautions'] ?? false)) {
            $planting = 'caution';                                     // the planting advisory below says to wait
        }
        if ($planting !== null) {
            $tone = in_array($planting, ['good', 'possible'], true) ? 'good' : 'watch';

            return $make('plant_'.$planting, $tone, $tone === 'good' ? 'check' : 'alert');
        }

        return $make('normal', 'info', 'check');
    }

    // ------------------------------------------------------------------ 4. expected yield this season

    /**
     * The stored model result for this farmer, or why there is none. A page never runs the model: with nothing
     * stored it queues PrepareFarmerYieldEstimate and shows a placeholder. Successes are kept 6 hours, missing records
     * 30 minutes and an unreachable model service 5 minutes (so the card says why instead of never filling).
     *
     * @return array{state: string, result: ?array<string, mixed>, cause?: string}  state: ok | error | loading
     */
    private function prediction(User $user, bool $refresh): array
    {
        $key = self::predictionCacheKey($user->id);
        if ($refresh) {
            Cache::forget($key);
        }

        $stored = Cache::get($key);
        if (! is_array($stored)) {
            if (\App\Support\InteractiveRequest::isPageRequest()) {
                \App\Jobs\PrepareFarmerYieldEstimate::queueFor($user->id);
                // A synchronous queue has already stored it.
                $stored = Cache::get($key);
                if (! is_array($stored)) {
                    return ['state' => 'loading', 'result' => null];
                }
            } else {
                $stored = $this->runYieldModel($user, $user->farmerProfile);
            }
        }

        if ($stored['failed'] ?? false) {
            return ['state' => 'error', 'result' => null, 'cause' => ($stored['lacks_records'] ?? false) ? 'records' : 'service'];
        }

        $yield = $stored['yield_tons_per_hectare'] ?? $stored['predicted_yield'] ?? null;
        if (! is_numeric($yield) || (float) $yield <= 0) {
            return ['state' => 'error', 'result' => null, 'cause' => 'service'];
        }

        return ['state' => 'ok', 'result' => $stored];
    }

    /** Runs the yield model for this farmer now and stores the result (used by the background job and the warm-up command). */
    public function prepareYieldEstimate(User $user): void
    {
        Cache::forget(self::predictionCacheKey($user->id));
        $this->runYieldModel($user, $user->farmerProfile);
        self::forgetCached($user);
    }

    /** @return array<string, mixed> the model's result with the time it ran, or ['failed' => true, 'lacks_records' => bool] */
    private function runYieldModel(User $user, $profile): array
    {
        $key = self::predictionCacheKey($user->id);
        $farmType = $profile?->farm_type ?: \App\Models\FarmerProfile::FARM_TYPE_RAINFED;

        try {
            $prediction = $this->riceYield->predictForUser(\App\Support\BusinessClock::now()->toImmutable(), $farmType, null, $user, [], PredictionType::RICE_YIELD->value);

            if ($prediction['success'] ?? false) {
                $prediction['ran_at'] = CarbonImmutable::now(self::TIMEZONE)->toIso8601String();
                Cache::put($key, $prediction, now()->addHours(6));

                return $prediction;
            }

            Log::warning('Farmer dashboard yield prediction was not produced.', ['source' => 'rice-yield model', 'user_id' => $user->id, 'reason' => $prediction['api_error'] ?? null, 'missing_inputs' => $prediction['missing_inputs'] ?? []]);
            $lacksRecords = ! empty($prediction['missing_inputs']) || str_contains(strtolower((string) ($prediction['api_error'] ?? '')), 'not enough');
        } catch (Throwable $e) {
            Log::warning('Farmer dashboard yield prediction failed.', ['source' => 'rice-yield model', 'user_id' => $user->id, 'exception' => $e::class, 'message' => $e->getMessage()]);
            $lacksRecords = false;
        }

        // Missing records do not change soon (30 minutes); a service that could not be reached is tried again sooner.
        $failed = ['failed' => true, 'lacks_records' => $lacksRecords];
        Cache::put($key, $failed, now()->addMinutes($lacksRecords ? 30 : 5));

        return $failed;
    }

    /**
     * One number in t/ha for the current season (auto-detected, App\Support\RiceSeason) and how it compares with the
     * usual Lian yield: Normal, Mas mataas or Mas mababa. state: ok | loading | nodata (with a reason).
     *
     * @param  array<string, mixed>  $prediction
     * @return array<string, mixed>
     */
    private function harvest(array $prediction, $profile, CarbonImmutable $now): array
    {
        $seasonLabel = $this->seasonYield->seasonLabel($now);
        $missingArea = (float) ($profile?->farm_area ?? 0) <= 0;

        if ($prediction['state'] === 'loading') {
            return ['state' => 'loading', 'season' => $seasonLabel];
        }
        if ($prediction['state'] !== 'ok') {
            return [
                'state' => 'nodata',
                'season' => $seasonLabel,
                'reason' => match (true) {
                    $missingArea => __('farmer.home.harvest.need_area'),
                    ($prediction['cause'] ?? null) === 'records' => __('farmer.home.harvest.need_records'),
                    default => __('farmer.home.no_data_note'),
                },
                'needs_area' => $missingArea,
            ];
        }

        $result = $prediction['result'];
        $month = ! empty($result['target_month']) ? CarbonImmutable::parse($result['target_month'].'-01') : $now;

        return ['state' => 'ok', 'season' => $seasonLabel]
            + $this->seasonYield->describe((float) ($result['yield_tons_per_hectare'] ?? $result['predicted_yield']), (int) $month->month)
            + ['ran_at' => ! empty($result['ran_at']) ? CarbonImmutable::parse($result['ran_at'])->timezone(self::TIMEZONE) : null];
    }

    /** This farmer's expected yield this season, the same card data Farmer Home shows (the Predictions page uses it too). */
    public function seasonEstimate(User $user): array
    {
        return $this->harvest($this->prediction($user, false), $user->farmerProfile, CarbonImmutable::now(self::TIMEZONE));
    }

    /** The stored estimate for one farmer, or null when none is saved (the Predictions page reads farmers' estimates). */
    public static function storedEstimate(int $userId): ?array
    {
        $stored = Cache::get(self::predictionCacheKey($userId));
        $yield = is_array($stored) && ! ($stored['failed'] ?? false) ? ($stored['yield_tons_per_hectare'] ?? $stored['predicted_yield'] ?? null) : null;

        return is_numeric($yield) && (float) $yield > 0 ? $stored : null;
    }

    // ------------------------------------------------------------------ 5b. latest planting advisory

    /** The newest published, in-effect planting advisory for the farmer's barangay or all of Lian; null when there is none. */
    private function latestPlantingAdvisory(?string $barangay): ?array
    {
        try {
            $advisory = SampleData::without(PlantingAdvisory::query(), 'planting_advisories')
                ->active()->forBarangay($barangay)->ofType(PlantingAdvisory::TYPE_PLANTING)
                ->latest('valid_from')->latest('id')->first();
        } catch (Throwable $e) {
            Log::warning('Farmer dashboard advisories failed.', ['source' => 'planting advisories', 'exception' => $e::class]);

            return null;
        }
        if (! $advisory) {
            return null;
        }
        $view = new FarmerAdvisoryView($advisory);

        return [
            'title' => (string) $advisory->title,
            'line' => (string) str($view->farmerAction() ?: $view->farmerEffect())->squish()->limit(160),
            'tone' => $view->farmerRiskTone(),
            // An advisory that warns or tells farmers to hold off; the main message then never says "good weather to plant".
            'cautions' => $view->farmerRiskTone() !== 'good'
                || (bool) preg_match('/\b(delay|delaying|postpone|wait|avoid|hold off)\b/i', $advisory->title.' '.$view->farmerAction()),
            'href' => route('planting-advisories.show', $advisory),
        ];
    }

    // ------------------------------------------------------------------ typhoon safety check-in (only during an event)

    /** The open typhoon event with the farmer's own answer, or null when no typhoon advisory is in effect. */
    private function typhoonCheckIn(User $user): ?array
    {
        try {
            $event = $this->typhoon->activeEvent();
            if (! $event) {
                return null;
            }
            $response = SchemaCache::hasTable('typhoon_safety_responses')
                ? TyphoonSafetyResponse::query()->where('user_id', $user->id)->where('event_key', $event['key'])->first()
                : null;
        } catch (Throwable) {
            return null;
        }

        return [
            'key' => $event['key'],
            'title' => (string) $event['title'],
            'severity' => (string) $event['severity'],
            'status' => $response?->status,
            'status_label' => $response?->statusLabel(),
            'note' => $response?->note,
        ];
    }
}
