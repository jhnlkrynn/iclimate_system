<?php

namespace App\Services\Analytics;

use App\Enums\PredictionType;
use App\Models\Farm;
use App\Models\PlantingAdvisory;
use App\Models\TyphoonSafetyResponse;
use App\Models\User;
use App\Services\Prediction\RiceYieldPredictionService;
use App\Services\Prediction\SeasonYield;
use App\Services\Risk\BarangayRiskMapService;
use App\Services\Risk\BarangayWeekRiskService;
use App\Services\TyphoonSafetyService;
use App\Services\Weather\FarmerDashboardWeatherService;
use App\Support\ActiveFarm;
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
 * A page never calls a provider or runs a model: weather is the stored reading, the yield is the last verified estimate
 * (prepared by iclimate:warm-farmer-yields, or after a response by PrepareFarmerYieldEstimate when it is missing or due).
 * Old data shows with its time; no data at all says "Walang datos pa".
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
    public static function cacheKey(User $user, ?string $locale = null, ?string $token = null, int|false|null $farmId = false): string
    {
        // Per farm too: the Home of each of the farmer's farms is its own page.
        $farmId = $farmId === false ? ActiveFarm::current($user)?->getKey() : $farmId;

        return 'dashboard:farmer:home:'.$user->id.($farmId ? ':farm'.$farmId : '').':'.($locale ?? app()->getLocale())
            .':u'.PageCache::userVersion((int) $user->id).':'.($token ?? CacheScope::token('farmer-dashboard'));
    }

    /** Drops the stored page of every farm in every language (after the farmer changes something it shows). */
    public static function forgetCached(User $user): void
    {
        foreach ([null, ...ActiveFarm::farms($user)->modelKeys()] as $farmId) {
            foreach (array_keys(FarmerLanguage::SUPPORTED) as $locale) {
                Cache::forget(self::cacheKey($user, $locale, farmId: $farmId));
            }
        }
    }

    /**
     * The last verified yield estimate of one farm (per farmer AND farm: one farm's estimate is never shown for another).
     * Without a farm id it is the farmer's primary farm (or, before the farms table exists, the farmer's single estimate).
     */
    public static function predictionCacheKey(int $userId, ?int $farmId = null): string
    {
        $farmId ??= self::primaryFarmId($userId);

        return 'dashboard:farmer-prediction:'.$userId.($farmId ? ':farm:'.$farmId : '');
    }

    /** A recent failed estimate run for that farm, so page loads do not call a failing model service again before it is due. */
    public static function predictionFailureKey(int $userId, ?int $farmId = null): string
    {
        $farmId ??= self::primaryFarmId($userId);

        return 'dashboard:farmer-prediction:failed:'.$userId.($farmId ? ':farm:'.$farmId : '');
    }

    private static function primaryFarmId(int $userId): ?int
    {
        if (! ActiveFarm::available()) {
            return null;
        }
        $id = Farm::query()->ownedBy($userId)->active()->orderByDesc('is_primary')->orderBy('id')->value('id');

        return $id !== null ? (int) $id : null;
    }

    /** A shown estimate this old is made again in the background (iclimate:warm-farmer-yields normally does it every 3 hours). */
    public const REFRESH_AFTER_HOURS = 6;

    /** How long a model service failure blocks another try. */
    public const FAILURE_RETRY_MINUTES = 5;

    /** How long missing farm or weather records block another try (they do not change soon). */
    public const MISSING_RECORDS_RETRY_MINUTES = 30;

    // ------------------------------------------------------------------ payload

    /**
     * @param  array<int, string>  $refresh  parts to rebuild instead of reading a cache: weather (a Refresh button), harvest (queue a new estimate)
     * @return array<string, mixed>
     */
    public function payload(User $user, bool $forceWeather = false, array $refresh = []): array
    {
        $now = CarbonImmutable::now(self::TIMEZONE);
        $profile = $user->farmerProfile;
        // The farm the farmer is viewing (My Farm selector); its barangay is the one these cards are for.
        $farm = ActiveFarm::current($user);
        $barangay = $user->registeredBarangay();

        $weather = $this->weather($forceWeather || in_array('weather', $refresh, true), $now);
        $week = $this->week($weather, $now);
        // A stored barangay that is not one official name (e.g. a bare "Poblacion": Lian has five) is never guessed.
        $stored = $farm !== null ? '' : trim((string) ($profile?->barangay ?: $user->barangay));
        $risk = $barangay === null && $stored !== '' ? ['state' => 'unverified', 'stored' => $stored] : $this->riskStatus($barangay);
        $harvest = $this->harvest($this->prediction($user, $farm, in_array('harvest', $refresh, true)), $farm, fn () => $profile, $now);
        // Farmer – Naicel (2026-10-09): without an estimate of their own (no farm size yet), the third card of the row
        // shows Lian's usual yield for this month from past records, so the row always has its three cards.
        if ($harvest['state'] !== 'ok' && ! isset($harvest['usual'])) {
            $harvest['usual'] = rescue(fn (): ?float => ($usual = $this->seasonYield->usual((int) $now->month)) !== null ? round($usual, 2) : null, null, false);
        }
        $typhoon = $this->typhoonCheckIn($user);
        $advisory = $this->latestPlantingAdvisory($barangay);
        // With two or more farms the farmer's records are read for the farm being viewed; otherwise all of them.
        $onlyFarm = ActiveFarm::farms($user)->count() > 1 ? $farm : null;
        // Farmer – Naicel (2026-10-08): the key figures and the yield comparison, from the parts above and saved records.
        $charts = app(\App\Services\Farmer\FarmerHomeCharts::class)->for($user, $onlyFarm, $barangay);
        $figures = app(\App\Services\Farmer\FarmerHomeKpis::class)->for($user, $farm, $onlyFarm, $harvest, $week, $now,
            ['risk' => $risk, 'barangay' => $barangay, 'forecast' => $charts['weather'],
                // The yield card shows Lian's usual yield itself when the farmer has no estimate (see harvest.blade.php).
                'usual_on_card' => $harvest['state'] !== 'ok' && ($harvest['needs_area'] ?? false) && is_numeric($harvest['usual'] ?? null)]);

        return [
            'generated_at' => $now,
            'locale' => app()->getLocale(),
            'greeting' => __('farmer.greeting.'.$this->period($now), ['name' => $this->displayName($user)]),
            'barangay' => $barangay,
            'place' => $barangay ? __('farmer.place', ['barangay' => $barangay]) : __('farmer.place_default'),
            // Which farm the page is about (null: no farms at all, or before the farms table exists).
            'farm' => $farm !== null ? ['id' => $farm->getKey(), 'name' => $farm->farm_name, 'barangay' => $farm->barangay] : null,
            'has_farms' => $farm !== null || ! ActiveFarm::available(),
            'message' => $this->message($week, $risk, $typhoon, $advisory),
            'weather' => $weather,
            'risk' => $risk,
            'harvest' => $harvest,
            'week' => $week,
            'advisory' => $advisory,
            'typhoon' => $typhoon,
            // Farmer – Naicel (2026-10): the two Home charts; one farm's harvests when the farmer has two or more farms.
            'charts' => $charts,
            'kpis' => $figures['tiles'],
            'compare' => $figures['compare'],
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
     * One farm's estimate for this season (the farm the farmer is viewing). A page never runs the model and never waits:
     *   - the farm's last verified estimate is shown at once with its own time, even while a newer one is being made;
     *   - one that is due (REFRESH_AFTER_HOURS old, made for an earlier month, or the farm/plan changed) is prepared
     *     again in the background (PrepareFarmerYieldEstimate::queueFor: once per farm, after the response);
     *   - with none yet, the card says it is being prepared and the same single background run makes it;
     *   - a farm without any size gets no estimate at all (state needs_size): no size is ever made up;
     *   - a failed run is remembered for a few minutes (FAILURE_RETRY_MINUTES, MISSING_RECORDS_RETRY_MINUTES), so page
     *     loads do not call a failing service again, and it never replaces a verified estimate.
     * Commands and jobs (no page waiting) may run the model when nothing is stored.
     *
     * @return array{state: string, result: ?array<string, mixed>, cause?: string}  state: ok | error | loading | needs_size
     */
    private function prediction(User $user, ?Farm $farm, bool $refresh): array
    {
        $farmId = $farm?->getKey();
        if ($farm !== null && $farm->modelArea() === null) {
            return ['state' => 'needs_size', 'result' => null];
        }

        $estimate = self::currentEstimate($user->id, $farmId);
        $failed = is_array(Cache::get(self::predictionFailureKey($user->id, $farmId)));

        if (! $failed && ($estimate === null || $refresh || self::isDue($estimate))) {
            if (\App\Support\InteractiveRequest::isPageRequest()) {
                \App\Jobs\PrepareFarmerYieldEstimate::queueFor($user->id, $farmId);
            } elseif ($estimate === null) {
                $this->runYieldModel($user, $farm);
            }
            $estimate = self::currentEstimate($user->id, $farmId);
        }

        if ($estimate !== null) {
            return ['state' => 'ok', 'result' => $estimate];
        }

        $failure = Cache::get(self::predictionFailureKey($user->id, $farmId));
        if (is_array($failure)) {
            return ['state' => 'error', 'result' => null, 'cause' => ($failure['lacks_records'] ?? false) ? 'records' : 'service'];
        }

        return ['state' => 'loading', 'result' => null];
    }

    /**
     * Runs the yield model for one farm now and stores the result (the background job and the warm-up command). Without
     * a farm it is the farmer's single estimate from the profile (before the farms table exists).
     */
    public function prepareYieldEstimate(User $user, ?Farm $farm = null): array
    {
        // No farm named (an older plan, a profile change): the farmer's primary farm, with that farm's own size.
        if ($farm === null && ActiveFarm::available()) {
            $farm = Farm::query()->ownedBy($user)->active()->orderByDesc('is_primary')->orderBy('id')->first();
        }
        $result = $this->runYieldModel($user, $farm);
        self::forgetCached($user);

        return $result;
    }

    /**
     * After a change to the farm's size, type or location, or to one of its planting plans: that farm's estimate is due
     * again (it stays on screen with its time until the new one replaces it) and a past failure no longer blocks the run.
     */
    public static function estimateInputsChanged(int $userId, ?int $farmId = null): void
    {
        $stored = Cache::get(self::predictionCacheKey($userId, $farmId));
        if (is_array($stored)) {
            Cache::forever(self::predictionCacheKey($userId, $farmId), ['inputs_changed' => true] + $stored);
        }
        Cache::forget(self::predictionFailureKey($userId, $farmId));
    }

    /**
     * @return array<string, mixed> the model's result with the time it ran, or ['failed' => true, 'lacks_records' => bool]
     */
    private function runYieldModel(User $user, ?Farm $farm): array
    {
        $farmId = $farm?->getKey();
        $profile = $user->farmerProfile;
        $farmType = $farm?->farm_type ?: ($profile?->farm_type ?: \App\Models\FarmerProfile::FARM_TYPE_RAINFED);
        // The farm's own area; never another farm's. A farm without a size is not run (see prediction()).
        $area = $farm?->modelArea();

        try {
            $prediction = $farm !== null && $area === null
                ? ['success' => false, 'missing_inputs' => ['area']]
                : $this->riceYield->predictForUser(\App\Support\BusinessClock::now()->toImmutable(), $farmType, $area, $user, [], PredictionType::RICE_YIELD->value);

            if (($prediction['success'] ?? false) && is_numeric($prediction['yield_tons_per_hectare'] ?? $prediction['predicted_yield'] ?? null)) {
                $prediction['ran_at'] = CarbonImmutable::now(self::TIMEZONE)->toIso8601String();
                // Which farm, and how its size was known: an estimate made from an estimated size says so on the card.
                $prediction['farm_id'] = $farmId;
                $prediction['farm_size_basis'] = $farm?->farm_size_method;
                $prediction['farm_size_range'] = $farm?->farm_size_method === Farm::SIZE_ESTIMATED ? $farm->farm_size_estimate_range : null;
                // Kept until a newer estimate replaces it: a later failure or a missed warm-up never empties the card.
                Cache::forever(self::predictionCacheKey($user->id, $farmId), $prediction);
                Cache::forget(self::predictionFailureKey($user->id, $farmId));

                return $prediction;
            }

            Log::warning('Farmer dashboard yield prediction was not produced.', ['source' => 'rice-yield model', 'user_id' => $user->id, 'farm_id' => $farmId, 'reason' => $prediction['api_error'] ?? null, 'missing_inputs' => $prediction['missing_inputs'] ?? []]);
            $lacksRecords = ! empty($prediction['missing_inputs']) || str_contains(strtolower((string) ($prediction['api_error'] ?? '')), 'not enough');
        } catch (Throwable $e) {
            Log::warning('Farmer dashboard yield prediction failed.', ['source' => 'rice-yield model', 'user_id' => $user->id, 'farm_id' => $farmId, 'exception' => $e::class, 'message' => $e->getMessage()]);
            $lacksRecords = false;
        }

        // Missing farm data means the last estimate no longer fits this farm (e.g. its size was removed), so it is
        // dropped and the card says what to add. A service that could not be reached keeps the last verified estimate.
        if ($lacksRecords) {
            Cache::forget(self::predictionCacheKey($user->id, $farmId));
        }
        // Missing records do not change soon (30 minutes); a service that could not be reached is tried again sooner.
        $failed = ['failed' => true, 'lacks_records' => $lacksRecords, 'at' => CarbonImmutable::now(self::TIMEZONE)->toIso8601String()];
        Cache::put(self::predictionFailureKey($user->id, $farmId), $failed, now()->addMinutes($lacksRecords ? self::MISSING_RECORDS_RETRY_MINUTES : self::FAILURE_RETRY_MINUTES));

        return $failed;
    }

    /** True when a shown estimate should be made again in the background. */
    private static function isDue(array $estimate): bool
    {
        if (($estimate['inputs_changed'] ?? false) === true) {
            return true;
        }
        $ranAt = rescue(fn () => CarbonImmutable::parse((string) ($estimate['ran_at'] ?? '')), null, false);
        $month = (string) ($estimate['target_month'] ?? '');

        return $ranAt === null || $ranAt->lt(CarbonImmutable::now()->subHours(self::REFRESH_AFTER_HOURS))
            || ($month !== '' && $month !== CarbonImmutable::now(self::TIMEZONE)->format('Y-m'));
    }

    /**
     * One number in t/ha for the current season (auto-detected, App\Support\RiceSeason) and how it compares with the
     * usual Lian yield: Normal, Mas mataas or Mas mababa. state: ok | loading | nodata (with a reason).
     *
     * @param  array<string, mixed>  $prediction
     * @param  callable(): mixed  $profile  the farmer's profile, read only when there is no farm and no estimate to show
     * @return array<string, mixed>
     */
    private function harvest(array $prediction, ?Farm $farm, callable $profile, CarbonImmutable $now): array
    {
        $seasonLabel = $this->seasonYield->seasonLabel($now);

        if ($prediction['state'] === 'needs_size') {
            return ['state' => 'nodata', 'season' => $seasonLabel, 'reason' => __('farms.yield.needs_size'),
                'needs_area' => true, 'farm_id' => $farm?->getKey()];
        }

        if ($prediction['state'] !== 'ok') {
            $missingArea = $farm !== null ? ! $farm->hasSize() : (float) ($profile()?->farm_area ?? 0) <= 0;
            // Without a farm size no estimate can be made, so the card asks for it at once instead of "being prepared".
            if ($prediction['state'] === 'loading' && ! $missingArea) {
                return ['state' => 'loading', 'season' => $seasonLabel];
            }

            return [
                'state' => 'nodata',
                'season' => $seasonLabel,
                'reason' => match (true) {
                    $missingArea => $farm !== null ? __('farms.yield.needs_size') : __('farmer.home.harvest.need_area'),
                    ($prediction['cause'] ?? null) === 'records' => __('farmer.home.harvest.need_records'),
                    default => __('farmer.home.harvest.unavailable'),
                },
                'needs_area' => $missingArea,
                'farm_id' => $farm?->getKey(),
            ];
        }

        $result = $prediction['result'];
        $month = ! empty($result['target_month']) ? CarbonImmutable::parse($result['target_month'].'-01') : $now;
        $range = ($result['farm_size_basis'] ?? null) === Farm::SIZE_ESTIMATED ? (Farm::ESTIMATE_RANGES[$result['farm_size_range'] ?? ''] ?? null) : null;

        return ['state' => 'ok', 'season' => $seasonLabel]
            + $this->seasonYield->describe((float) ($result['yield_tons_per_hectare'] ?? $result['predicted_yield']), (int) $month->month)
            + [
                'ran_at' => ! empty($result['ran_at']) ? CarbonImmutable::parse($result['ran_at'])->timezone(self::TIMEZONE) : null,
                // An estimate made from an estimated farm size says so, with the range the farmer picked.
                'size_note' => $range !== null ? __('farms.yield.estimated_size', ['range' => \App\Support\FarmSize::rangeLabel((string) $result['farm_size_range'])]) : null,
            ];
    }

    /**
     * The expected yield this season for one of the farmer's farms (the farm they are viewing when none is given): the
     * same card data Farmer Home shows, so the Predictions page shows the same number.
     */
    public function seasonEstimate(User $user, ?Farm $farm = null): array
    {
        $farm ??= ActiveFarm::current($user);

        return $this->harvest($this->prediction($user, $farm, false), $farm, fn () => $user->farmerProfile, CarbonImmutable::now(self::TIMEZONE));
    }

    /**
     * The stored estimate for one farm (the farmer's primary farm when no farm is given), or null when none is saved
     * for this season. The Predictions page and MAO Home average these.
     */
    public static function storedEstimate(int $userId, ?int $farmId = null): ?array
    {
        return self::currentEstimate($userId, $farmId);
    }

    /**
     * Every active farmer's saved estimates for this season, one per farm (a farmer with two farms counts twice, each
     * farm with its own barangay), optionally only the farms in one barangay. The farms are read in one query; before
     * the farms table exists it is one estimate per farmer, by the farmer's barangay.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public static function storedEstimates(?string $barangay = null): \Illuminate\Support\Collection
    {
        if (ActiveFarm::available()) {
            return Farm::query()->active()
                ->when($barangay !== null, fn ($q) => $q->where('barangay', $barangay))
                ->whereHas('user', fn ($q) => $q->where('role', User::ROLE_FARMER)->where('status', User::STATUS_ACTIVE))
                ->get(['id', 'user_id'])
                ->map(fn (Farm $farm): ?array => self::currentEstimate((int) $farm->user_id, (int) $farm->getKey()))
                ->filter()->values();
        }

        $farmers = User::query()->where('role', User::ROLE_FARMER)->where('status', User::STATUS_ACTIVE)
            ->with('farmerProfile:id,user_id,barangay')->get(['id', 'barangay', 'role']);
        if ($barangay !== null) {
            $farmers = $farmers->filter(fn (User $f): bool => $f->registeredBarangay() === $barangay);
        }

        return $farmers->map(fn (User $f): ?array => self::currentEstimate((int) $f->id))->filter()->values();
    }

    /**
     * The farm's last verified estimate when it was made for the current season (an estimate from an earlier season is
     * not this season's), else null.
     *
     * @return array<string, mixed>|null
     */
    private static function currentEstimate(int $userId, ?int $farmId = null): ?array
    {
        $stored = Cache::get(self::predictionCacheKey($userId, $farmId));
        $yield = is_array($stored) && ! ($stored['failed'] ?? false) ? ($stored['yield_tons_per_hectare'] ?? $stored['predicted_yield'] ?? null) : null;
        if (! is_numeric($yield) || (float) $yield <= 0) {
            return null;
        }

        $now = CarbonImmutable::now(self::TIMEZONE);
        $month = rescue(fn () => ! empty($stored['target_month']) ? CarbonImmutable::parse($stored['target_month'].'-01', self::TIMEZONE) : null, null, false);

        return $month === null || \App\Support\RiceSeason::window($month)['start']->equalTo(\App\Support\RiceSeason::window($now)['start']) ? $stored : null;
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
