<?php

namespace App\Services\Analytics;

use App\Enums\PredictionType;
use App\Models\ConversationMessage;
use App\Models\FarmerPlantingSchedule;
use App\Models\FeedPost;
use App\Models\HeatmapArea;
use App\Models\Notification as UserNotification;
use App\Models\PlantingAdvisory;
use App\Models\RiceProduction;
use App\Models\TyphoonSafetyResponse;
use App\Models\User;
use App\Services\Prediction\RiceYieldPredictionService;
use App\Services\Risk\BarangayRiskMapService;
use App\Services\TyphoonSafetyService;
use App\Services\Weather\FarmerDashboardWeatherService;
use App\Services\WeatherApiService;
use App\Support\DashboardData;
use App\Support\FarmerAdvisoryView;
use App\Support\FarmerLanguage;
use App\Support\LianGeography;
use App\Support\ProjectYieldDataset;
use App\Support\SampleData;
use App\Support\SchemaCache;
use App\Support\WeatherConditionLabel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Everything the Farmer dashboard shows, from ONE place, in plain language (all wording is in lang/{en,fil}/farmer.php).
 * The dashboard is a summary of the other Farmer pages, so every part reads the same source as its own page:
 *   weather      - FarmerDashboardWeatherService (the Lian reading the Predictions / weather pages show)
 *   farm risk    - BarangayRiskMapService (the Heat Map)
 *   yield        - RiceYieldPredictionService (the Predictions page)
 *   schedule     - the farmer's planting schedules (the Calendar)
 *   advisories   - published, in-effect advisories (the Advisories page), worded by FarmerAdvisoryView
 *   messages     - ConversationMessage::unreadFor (the Messages page)
 *   notifications, community posts, rice production records - their own tables, read the way their pages read them.
 *
 * Rules that shape it:
 *   - every number is queried or computed here from a real source, never typed in;
 *   - a card without real data says what is missing ("Not set", never 0) and how to fix it;
 *   - a card whose source fails shows its own "We can't show this right now" state; other cards keep working;
 *   - failures are logged for IT with the source name;
 *   - the current weather, today's rain and the forecast are all read from the SAME stored reading;
 *   - Lian-wide values are labelled as Lian, never as "your farm"; estimates are labelled as estimates; past records as records;
 *   - t/ha (yield) and tons (production) are never mixed up: tons are shown only from the farmer's own farm area.
 */
final class FarmerDashboardService
{
    public const TIMEZONE = 'Asia/Manila';

    /** Same "High" thresholds the MAO overview and the weather calendar use. */
    public const HEAVY_RAIN_MM = MaoDashboardService::HEAVY_RAIN_MM;

    public const STRONG_WIND_KMH = MaoDashboardService::STRONG_WIND_KMH;

    public const VERY_HOT_C = MaoDashboardService::VERY_HOT_C;

    /** How many forecast days ahead a warning looks. */
    private const WARNING_LOOKAHEAD_DAYS = 2;

    /** At most this many actions are suggested for today. */
    private const MAX_ACTIONS = 3;

    /** Today's Highlights: never more than this many cards. */
    private const MAX_HIGHLIGHTS = 4;

    /** Days of forecast shown on the dashboard (the full week is on the weather page). */
    public const PREVIEW_DAYS = 4;

    /** An advisory this recent is "new". */
    private const NEW_ADVISORY_DAYS = 3;

    /** The Reminders card mentions a farm activity this many days ahead. */
    private const REMINDER_DAYS = 7;

    public function __construct(
        private readonly FarmerDashboardWeatherService $weather,
        private readonly WeatherApiService $weatherApi,
        private readonly BarangayRiskMapService $risk,
        private readonly RiceYieldPredictionService $riceYield,
        private readonly TyphoonSafetyService $typhoon,
        private readonly ProjectYieldDataset $yields,
        private readonly FarmerCropPlanService $cropPlan,
        private readonly FarmerKpiService $kpi,
    ) {}

    /** The page is cached per farmer AND per language: the numbers are the same, the words are not. */
    public static function cacheKey(User $user, ?string $locale = null): string
    {
        return 'dashboard:farmer:overview:'.$user->id.':'.($locale ?? app()->getLocale()).':v'.DashboardData::version();
    }

    /** Drops the stored page in every language (after the farmer changes something it shows). */
    public static function forgetCached(User $user): void
    {
        foreach (array_keys(FarmerLanguage::SUPPORTED) as $locale) {
            Cache::forget(self::cacheKey($user, $locale));
        }
    }

    /** The cached yield prediction is dropped whenever the farmer's profile or planting schedule changes (see AppServiceProvider). */
    public static function predictionCacheKey(int $userId): string
    {
        return 'dashboard:farmer-prediction:'.$userId;
    }

    // ------------------------------------------------------------------ payload

    /**
     * @param  array<int, string>  $refresh  parts to rebuild from their source instead of a cache: weather, harvest, risk
     * @param  bool  $deferHarvest  do not wait for the slow harvest model: with nothing cached the card is a loading placeholder
     *                              that the page fills in with a second request
     * @return array<string, mixed>
     */
    public function payload(User $user, bool $forceWeather = false, array $refresh = [], bool $deferHarvest = false): array
    {
        $now = CarbonImmutable::now(self::TIMEZONE);
        $profile = $user->farmerProfile;
        $barangay = $user->registeredBarangay();
        $forceWeather = $forceWeather || in_array('weather', $refresh, true);

        $weather = $this->weather($forceWeather, $now);
        // A stored barangay that is not one official name (e.g. a bare "Poblacion": Lian has five) is never guessed; the
        // farmer is asked to confirm it instead of being told to add one.
        $stored = trim((string) ($profile?->barangay ?: $user->barangay));
        $risk = $barangay === null && $stored !== ''
            ? ['state' => 'unverified', 'stored' => $stored]
            : $this->riskStatus($barangay, in_array('risk', $refresh, true));
        $rows = $this->scheduleRows($user);
        $prediction = $this->prediction($user, $profile, $rows, in_array('harvest', $refresh, true), $deferHarvest);
        $advisories = $this->advisories($barangay, $now);
        $alerts = $this->alerts($user, $weather, $risk, $now);
        $actions = $this->actions($weather, $risk, $now);
        $plan = $this->plan($rows, $weather, $now);
        $season = $this->season($rows, $plan, $now);
        $schedule = $this->schedule($season, $now);
        $setup = $this->setup($profile, $barangay, $rows);
        $unread = $this->unread($user);
        $messages = $this->unreadMessages($user);
        $highlights = $this->highlights($alerts, $actions, $schedule, $advisories['items'], $weather, $risk, $now);
        $week = $this->week($weather, $now);
        $climate = $this->climate($now);
        $production = $this->production($barangay);

        return [
            'generated_at' => $now,
            'locale' => app()->getLocale(),
            'greeting' => __('farmer.greeting.'.$this->period($now), ['name' => $this->displayName($user)]),
            'name' => $this->displayName($user),
            'barangay' => $barangay,
            'place' => $barangay ? __('farmer.place', ['barangay' => $barangay]) : __('farmer.place_default'),
            'weather' => $weather,
            'alerts' => $alerts,
            'actions' => $actions,
            'week' => $week,
            'condition' => $this->condition($weather, $risk, $alerts),
            'season' => $season,
            'schedule' => $schedule,
            'climate' => $climate,
            // Shown only while no planting date is saved: the Heat Map's planting advice and the usual rice calendar.
            'planting_guide' => $schedule['state'] === 'ok' ? null : $this->plantingGuide($risk, $climate, $week, $now),
            'harvest' => $this->harvest($prediction, $profile, $rows),
            'production' => $production,
            'usual_yield' => $this->usualYield($now->month),
            'risk' => $risk,
            'profile' => $this->profile($profile, $barangay, $schedule),
            'setup' => $setup,
            'quick' => $this->quick($profile, $rows),
            'advisories' => $advisories['items'],
            'advisories_new' => $advisories['new'],
            'unread' => $unread,
            'messages' => $messages,
            'community' => $this->community(),
            'highlights' => $highlights,
            'reminders' => $this->reminders($schedule, $setup, $advisories['new'], $messages, $unread, $highlights),
        ];
    }

    private function period(CarbonImmutable $now): string
    {
        return match (true) {
            $now->hour < 12 => 'morning',
            $now->hour < 18 => 'afternoon',
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

    /** "5:42 PM" for today, "Oct 1, 5:42 PM" for another day. */
    private function stamp(?CarbonImmutable $time, CarbonImmutable $now): ?string
    {
        if (! $time) {
            return null;
        }
        $time = $time->timezone(self::TIMEZONE);

        return $time->isSameDay($now) ? $time->format('g:i A') : $time->format('M j, g:i A');
    }

    /** "today", "tomorrow" or "in 3 days", in the farmer's language. */
    private function aheadText(int $days): string
    {
        return match (true) {
            $days <= 0 => __('farmer.alerts.today'),
            $days === 1 => __('farmer.alerts.tomorrow'),
            default => trans_choice('farmer.when.in_days', $days, ['count' => $days]),
        };
    }

    /** A weather condition as the farmer reads it ("Light Rain" / "Mahinang Ulan"); an unknown provider phrase stays as given. */
    public static function conditionText(string $condition): string
    {
        $key = 'farmer.conditions.'.str($condition)->lower()->replaceMatches('/[^a-z]+/', '_')->trim('_');

        return Lang::has($key) ? __($key) : $condition;
    }

    // ------------------------------------------------------------------ weather (live, Lian-wide)

    /**
     * One reading, one clock: "now", today's rain and the forecast all come from the same stored reading
     * (current conditions and the daily list in the same provider response), and are written by one formatter.
     * Conditions are matched in the provider's English; only the words shown are translated.
     *
     * @return array<string, mixed>
     */
    private function weather(bool $force, CarbonImmutable $now): array
    {
        try {
            $w = $this->weather->current($force);
        } catch (Throwable $e) {
            Log::warning('Farmer dashboard weather failed.', ['source' => 'weather service', 'exception' => $e::class, 'message' => $e->getMessage()]);

            return ['state' => 'error'];
        }

        $current = is_array($w['current'] ?? null) ? $w['current'] : [];
        if (! ($w['success'] ?? false) || ! is_numeric($current['temperature'] ?? null)) {
            Log::warning('Farmer dashboard weather had no usable reading.', ['source' => (string) ($w['provider'] ?? 'weather service'), 'status' => $w['status'] ?? null]);

            return ['state' => 'error'];
        }
        $today = is_array($w['today'] ?? null) ? $w['today'] : [];

        // Wind by date is a fallback read from the stored forecast (a page never waits on a weather provider for it).
        try {
            $series = (array) data_get($this->weatherApi->storedForecast(), 'daily_series', []);
        } catch (Throwable) {
            $series = [];
        }
        $windByDate = [];
        foreach ((array) ($series['labels'] ?? []) as $i => $date) {
            if (is_numeric($series['windSpeed'][$i] ?? null)) {
                $windByDate[$date] = (float) $series['windSpeed'][$i];
            }
        }

        $heavy = (float) config('farmer_dashboard.heavy_rain_mm');
        $days = [];
        foreach ((array) ($w['forecast'] ?? []) as $day) {
            if (! is_array($day) || empty($day['date'])) {
                continue;
            }
            $rain = is_numeric($day['rainfall'] ?? null) ? (float) $day['rainfall'] : null;
            $high = is_numeric($day['temperature_max'] ?? null) ? (float) $day['temperature_max'] : null;
            $wind = is_numeric($day['wind_speed_max'] ?? null) ? (float) $day['wind_speed_max'] : ($windByDate[$day['date']] ?? null);
            $condition = (string) (WeatherConditionLabel::display($day['condition'] ?? null) ?: '');
            $days[] = [
                'date' => CarbonImmutable::parse($day['date'], self::TIMEZONE),
                'condition' => $condition !== '' ? self::conditionText($condition) : __('farmer.weather.unknown'),
                'icon' => $this->iconFor($condition, true),
                'high' => $high,
                'low' => is_numeric($day['temperature_min'] ?? null) ? (float) $day['temperature_min'] : null,
                'chance' => is_numeric($day['precipitation_probability'] ?? null) ? (float) $day['precipitation_probability'] : null,
                'rain' => $rain,
                'wind' => $wind,
                'feels_like_max' => is_numeric($day['feels_like_max'] ?? null) ? (float) $day['feels_like_max'] : null,
                'humidity_avg' => is_numeric($day['humidity_avg'] ?? null) ? (float) $day['humidity_avg'] : null,
                'heavy' => $rain !== null && $rain >= $heavy,
                'strong_wind' => $wind !== null && $wind >= self::STRONG_WIND_KMH,
                'high_heat' => $high !== null && $high >= self::VERY_HOT_C,
            ];
        }

        // Today's day: the forecast entry for today's Manila date. With none (a stored fallback has no list), the reading's own daily totals.
        $todayKey = $now->toDateString();
        $todayDay = collect($days)->first(fn (array $d) => $d['date']->toDateString() === $todayKey);
        $todayRain = $todayDay['rain'] ?? (is_numeric($today['rainfall'] ?? null) ? (float) $today['rainfall'] : null);
        $todayChance = $todayDay['chance'] ?? (is_numeric($today['precipitation_probability'] ?? null) ? (float) $today['precipitation_probability'] : null);
        $todayHigh = $todayDay['high'] ?? (is_numeric($today['temperature_max'] ?? null) ? (float) $today['temperature_max'] : null);

        $conditionEn = (string) (WeatherConditionLabel::display($current['condition'] ?? null) ?: '');
        $conditionNow = $conditionEn !== '' ? self::conditionText($conditionEn) : __('farmer.weather.unknown');
        $humidity = is_numeric($current['humidity'] ?? null) ? (float) $current['humidity'] : null;
        $feels = is_numeric($current['feels_like'] ?? null) ? (float) $current['feels_like'] : null;
        $wind = is_numeric($current['wind_speed'] ?? null) ? (float) $current['wind_speed'] : null;
        $rainingNow = $this->isRaining($conditionEn, $current['precipitation'] ?? null);
        $rainExpected = $this->rainExpected($todayRain, $todayChance);

        // The time the reading was fetched (not the page load time); the provider's own time only when that is all we have.
        $fetched = data_get($w, 'timestamps.server_fetched_at') ?? data_get($w, 'timestamps.provider_updated_at') ?? ($w['fetched_at'] ?? null);
        $updated = $fetched ? CarbonImmutable::parse($fetched)->timezone(self::TIMEZONE) : null;
        $age = $updated ? max(0, (int) $updated->diffInMinutes($now)) : null;
        $stale = (bool) ($w['stale'] ?? false) || $age === null || $age > (int) config('farmer_dashboard.weather.stale_after_minutes', 60);

        // Heat: the provider's own highest "feels like" for today, else worked out from today's highest temperature and humidity.
        $heat = $this->kpi->heatToday(
            $todayDay['feels_like_max'] ?? (is_numeric($today['feels_like_max'] ?? null) ? (float) $today['feels_like_max'] : null),
            $todayHigh,
            $todayDay['humidity_avg'] ?? (is_numeric($today['humidity_avg'] ?? null) ? (float) $today['humidity_avg'] : $humidity),
        );

        return [
            'state' => 'ok',
            // The configured provider's own name, shown as the source (e.g. Open-Meteo).
            'provider' => (string) ($w['provider'] ?? $w['source'] ?? 'Weather'),
            'condition' => $conditionNow,
            'icon' => $this->iconFor($conditionEn, ($current['is_day'] ?? true) !== false),
            'temperature' => (float) $current['temperature'],
            'feels_like' => $feels,
            'feels_level' => $feels !== null ? $this->kpi->heatLevel($feels) : null,
            'humidity' => $humidity,
            'wind' => $wind,
            'raining_now' => $rainingNow,
            'rain_today' => $todayRain,
            'rain_chance' => $todayChance,
            'chance_level' => $todayChance === null ? null : match (true) {
                $todayChance >= 70 => 'very_likely',
                $todayChance >= 40 => 'possible',
                default => 'unlikely',
            },
            'rain_expected' => $rainExpected,
            'rain_line' => __('farmer.rain_today.'.match (true) {
                $rainingNow => 'now',
                $rainExpected => 'later',
                default => 'none',
            }),
            'now_summary' => $this->nowSummary($conditionNow, $rainingNow, $rainExpected, $todayRain),
            'updated_at' => $updated,
            'updated_label' => $this->stamp($updated, $now),
            'age_minutes' => $age,
            'age_text' => $age === null ? __('farmer.header.no_update') : self::ageText($age),
            'stale' => $stale,
            'humidity_meaning' => $humidity === null ? null : __('farmer.weather.humidity.'.match (true) {
                $humidity >= (float) config('farmer_dashboard.humid_percent') => 'very_wet',
                $humidity >= 70 => 'wet',
                default => 'comfortable',
            }),
            'wind_meaning' => $wind === null ? null : __('farmer.weather.wind.'.match (true) {
                $wind >= (float) config('farmer_dashboard.strong_wind_kmh') => 'strong',
                $wind >= (float) config('farmer_dashboard.good_work_day.max_wind_kmh') => 'breezy',
                default => 'light',
            }),
            'heat' => $heat,
            // One word each for today's temperature, rain and humidity (bands in config/farmer_dashboard.php).
            'levels' => $this->kpi->todayLevels((float) $current['temperature'], $todayRain, $humidity),
            'days' => $days,
        ];
    }

    /** "Updated 21 minutes ago" wording for a data age in minutes (the page script keeps it current with the same words). */
    public static function ageText(int $minutes): string
    {
        return __('farmer.header.updated', ['age' => $minutes < 60 ? trans_choice('farmer.header.minutes_ago', $minutes) : trans_choice('farmer.header.hours_ago', intdiv($minutes, 60))]);
    }

    /** Rain right now: the condition says so, or the reading measured some. */
    private function isRaining(string $condition, mixed $precipitationMm): bool
    {
        return (bool) preg_match('/rain|shower|storm|drizzle|thunder/i', $condition) || (is_numeric($precipitationMm) && (float) $precipitationMm > 0);
    }

    private function rainExpected(?float $mm, ?float $chance): bool
    {
        $rule = (array) config('farmer_dashboard.rain_expected');

        return ($mm !== null && $mm >= (float) $rule['min_mm']) || ($chance !== null && $chance >= (float) $rule['min_chance_percent']);
    }

    /** One plain sentence that cannot contradict itself: how it is now, and what rain is still to come today. */
    private function nowSummary(string $condition, bool $rainingNow, bool $rainExpected, ?float $rainTodayMm): string
    {
        $amount = $rainTodayMm !== null && $rainTodayMm >= 0.1 ? FarmerKpiService::formatRain($rainTodayMm) : null;

        return match (true) {
            $rainingNow && $amount !== null => __('farmer.now.raining_amount', ['mm' => $amount]),
            $rainingNow => __('farmer.now.raining'),
            $rainExpected && $amount !== null => __('farmer.now.later_amount', ['condition' => $condition, 'mm' => $amount]),
            $rainExpected => __('farmer.now.later', ['condition' => $condition]),
            default => __('farmer.now.dry', ['condition' => $condition]),
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

    // ------------------------------------------------------------------ what to do today

    /**
     * One to three actions, each tied to a real condition today. Nothing is generated: every action is one of these
     * fixed rules firing on the same reading shown in the weather card.
     *
     * @return array<int, array{key: string, icon: string, title: string, reason: string, ask: string}>
     */
    private function actions(array $weather, ?array $risk, CarbonImmutable $now): array
    {
        $actions = [];
        $push = function (string $icon, string $key, array $params = []) use (&$actions): void {
            $actions[] = [
                'key' => $key,
                'icon' => $icon,
                'title' => __('farmer.actions.'.$key.'.title', $params),
                'reason' => __('farmer.actions.'.$key.'.reason', $params),
                'ask' => __('farmer.actions.'.$key.'.ask', $params),
            ];
        };

        if ($weather['state'] === 'ok') {
            $rain = $weather['rain_today'];
            $mm = $rain !== null ? FarmerKpiService::formatRain($rain) : '';
            $humid = (float) config('farmer_dashboard.humid_percent');

            if ($rain !== null && $rain >= (float) config('farmer_dashboard.heavy_rain_mm')) {
                $push('rain', 'heavy_rain', ['mm' => $mm]);
            } elseif ($weather['rain_expected']) {
                $push('rain', 'rain', ['mm' => $mm, 'where' => $weather['raining_now'] ? __('farmer.actions.where_now') : __('farmer.actions.where_later')]);
            }
            if ($weather['humidity'] !== null && $weather['humidity'] >= $humid) {
                $push('humidity', 'humid', ['percent' => FarmerKpiService::formatPercent($weather['humidity'])]);
            }
            if ($weather['wind'] !== null && $weather['wind'] >= (float) config('farmer_dashboard.strong_wind_kmh') && ! $weather['rain_expected']) {
                $push('wind', 'wind', ['kmh' => number_format($weather['wind'], 0)]);
            }
            if (in_array($weather['heat']['level'] ?? null, ['extreme_caution', 'danger', 'extreme_danger'], true)) {
                $push('heat', 'heat', ['feels' => $weather['heat']['display'], 'level' => mb_strtolower((string) $weather['heat']['label'])]);
            }
        }
        if ($risk && ($risk['state'] ?? null) === 'ok' && $risk['high']) {
            $push('map', 'risk', ['level' => mb_strtolower($risk['level_label'])]);
        }
        if ($actions === []) {
            $push('check', $weather['state'] === 'ok' ? 'normal' : 'no_weather');
        }

        return array_slice($actions, 0, self::MAX_ACTIONS);
    }

    // ------------------------------------------------------------------ the next few days

    /**
     * The forecast days with "good day for spraying" marked by the same rule as FarmerKpiService::goodWorkDays, and one
     * sentence about the wettest day. The dashboard shows the first few; the weather page shows them all.
     *
     * @return array<string, mixed>
     */
    private function week(array $weather, CarbonImmutable $now): array
    {
        if ($weather['state'] !== 'ok' || $weather['days'] === []) {
            return ['state' => $weather['state'] === 'ok' ? 'empty' : 'error', 'days' => []];
        }

        $good = $this->kpi->goodWorkDays(
            array_map(fn (array $d) => ['date' => $d['date'], 'chance' => $d['chance'], 'wind_kmh' => $d['wind']], $weather['days']),
            $now,
        );
        $days = array_values(array_filter($weather['days'], fn (array $d) => $d['date']->toDateString() >= $now->toDateString()));
        $days = array_map(function (array $d) use ($good, $now): array {
            $key = $d['date']->toDateString();
            $d['is_today'] = $key === $now->toDateString();
            $d['good'] = $d['chance'] !== null && $d['wind'] !== null ? in_array($key, $good['dates'], true) : null;
            $d['flags'] = array_values(array_filter([
                $d['heavy'] ? 'heavy_rain' : null,
                $d['strong_wind'] ? 'strong_wind' : null,
                $d['high_heat'] ? 'high_heat' : null,
            ]));

            return $d;
        }, $days);

        return [
            'state' => $days === [] ? 'empty' : 'ok',
            'days' => $days,
            'good' => $good,
            'preview' => array_slice($days, 0, self::PREVIEW_DAYS),
            'takeaway' => $this->weekTakeaway($days),
        ];
    }

    /** One sentence for the coming days: the wettest day, or that no day is wet. */
    private function weekTakeaway(array $days): string
    {
        $withRain = array_values(array_filter($days, fn ($d) => $d['rain'] !== null));
        if ($withRain === []) {
            return __('farmer.week.takeaway_none');
        }
        usort($withRain, fn ($a, $b) => $b['rain'] <=> $a['rain']);
        $top = $withRain[0];
        if ($top['rain'] < 1) {
            return __('farmer.week.takeaway_dry');
        }

        return __('farmer.week.takeaway_wet', ['day' => $top['date']->format('l'), 'mm' => FarmerKpiService::formatRain($top['rain'])]);
    }

    // ------------------------------------------------------------------ farm risk (barangay) — the Heat Map's own rating

    /**
     * @return array<string, mixed> state: ok | error | nodata | nobarangay (payload() adds: unverified)
     */
    private function riskStatus(?string $barangay, bool $refresh = false): array
    {
        if (! $barangay) {
            return ['state' => 'nobarangay'];
        }
        try {
            $r = $this->risk->detailFor($barangay, $refresh);
        } catch (Throwable $e) {
            Log::warning('Farmer dashboard farm risk failed.', ['source' => 'barangay risk map', 'exception' => $e::class, 'message' => $e->getMessage()]);

            return ['state' => 'error'];
        }
        if ($r === null) {
            // The map has not been computed yet, or the barangay name is not one of Lian's 19: there is no rating, which is not an outage.
            Log::info('Farmer dashboard farm risk has no entry for the barangay.', ['source' => 'barangay risk map', 'barangay' => $barangay]);

            return ['state' => 'nodata'];
        }
        $level = $r['risk_level'] ?? null;
        if (! $level || in_array($level, ['No Data', 'Unknown'], true)) {
            return ['state' => 'nodata'];
        }

        $concern = null;
        try {
            $concern = HeatmapArea::query()->where('barangay', $r['barangay'] ?? $barangay)->value('risk_type');
        } catch (Throwable) {
            // the main concern is optional detail
        }
        $updated = ! empty($r['updated_at']) ? CarbonImmutable::parse($r['updated_at'])->timezone(self::TIMEZONE) : null;
        // The risk map writes its advice in English: it is shown as written in English, and in Filipino the plain meaning is used.
        $english = app()->getLocale() === 'en';

        $result = [
            'state' => 'ok',
            'barangay' => $r['barangay'] ?? $barangay,
            'level' => $level,
            'level_label' => __('farmer.risk.level.'.strtolower($level)),
            'level_full' => __('farmer.risk.level_full.'.strtolower($level)),
            'tone' => ['low' => 'good', 'moderate' => 'watch', 'high' => 'danger', 'severe' => 'danger'][strtolower($level)] ?? 'info',
            'high' => in_array($level, ['High', 'Severe'], true),
            'meaning' => __('farmer.risk.meaning.'.strtolower($level)),
            'concern' => $concern && Lang::has('farmer.risk.concern.'.strtolower($concern)) ? __('farmer.risk.concern.'.strtolower($concern)) : null,
            'planting' => $english ? ($r['advisory']['planting'] ?? null) : null,
            // The same planting rule as one key, so it can be said in either language (DecisionSupportService::plantingAdvisory).
            'planting_key' => $this->plantingKey((string) ($r['advisory']['planting'] ?? '')),
            'irrigation' => $english ? ($r['advisory']['irrigation'] ?? null) : null,
            'warnings' => $english ? array_values(array_filter((array) ($r['advisory']['warnings'] ?? []), 'is_string')) : [],
            'updated_at' => $updated,
        ];
        // The planting line shown with the rating (never a cheerful line next to a warning).
        $result['line'] = $this->planningLine($result);

        return $result;
    }

    /**
     * The one line of planting advice shown with a risk rating. The planting rule looks only at this month's rain, so it can
     * say "Ideal planting period." while the barangay is rated Moderate or worse; a cheerful line next to a warning would
     * mislead, so in that case the plain meaning of the rating is shown instead.
     *
     * @param  array<string, mixed>  $risk
     */
    private function planningLine(array $risk): string
    {
        $planting = trim((string) ($risk['planting'] ?? ''));
        $cheerful = str_starts_with($planting, 'Ideal');

        return $planting !== '' && ! ($cheerful && $risk['level'] !== 'Low') ? $planting : (string) $risk['meaning'];
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

    // ------------------------------------------------------------------ this month's climate — the Predictions page's monthly outlook

    /**
     * This month in Lian: the stored monthly weather estimate compared with past years of the same month, by the same rule
     * as the Predictions page (MonthlyWeatherOutlookService). Read-only: a month nothing has been prepared for is null,
     * the model is never run from the dashboard.
     *
     * @return array<string, mixed>|null
     */
    private function climate(CarbonImmutable $now): ?array
    {
        try {
            $month = $now->startOfMonth();
            $prepared = app(\App\Services\Prediction\PredictionOrchestrator::class)->preparedMonthlyWeatherForecasts([$month]);
            $calendar = app(\App\Services\Weather\MonthlyWeatherCalendarService::class)->build($month, null, $prepared[$month->format('Y-m')] ?? null);
            $outlook = app(\App\Services\Weather\MonthlyWeatherOutlookService::class)->build($calendar);
        } catch (Throwable $e) {
            Log::info('Farmer dashboard monthly outlook was not available.', ['source' => 'monthly weather outlook', 'exception' => $e::class]);

            return null;
        }
        $mp = $calendar['monthly_prediction'] ?? null;
        if (! is_array($mp) || $mp['rainfall_mm'] === null) {
            return null;
        }

        $cards = (array) data_get($outlook, 'model.cards', []);
        $rain = (array) ($cards['rainfall'] ?? []);
        $status = ($rain['available'] ?? false) ? ['Above Normal' => 'above', 'Below Normal' => 'below', 'Near Normal' => 'near'][$rain['status']] ?? null : null;
        $usual = data_get($outlook, 'historical.avg.rainfall');

        return [
            'month' => $month->translatedFormat('F'),
            'rain' => (float) $mp['rainfall_mm'],
            'rain_status' => $status,
            'usual_rain' => $status !== null && is_numeric($usual) ? (float) $usual : null,
            'span' => $status !== null ? ($rain['span'] ?? null) : null,
            'low' => $mp['tmin_c'],
            'high' => $mp['tmax_c'],
            'season' => strtolower((string) data_get($cards, 'season.active', '')) ?: null,
        ];
    }

    // ------------------------------------------------------------------ planting guide (before a planting date is saved)

    /**
     * What a farmer without a planting date can plan with: the Heat Map's planting advice for the barangay (the same rule
     * the map shows), this month's expected rain, good field-work days this week, and the usual rice calendar that the
     * Calendar follows once a date is saved. Nothing is presented as the farmer's own schedule.
     *
     * @return array<string, mixed>
     */
    private function plantingGuide(array $risk, ?array $climate, array $week, CarbonImmutable $now): array
    {
        $key = ($risk['state'] ?? null) === 'ok' ? $risk['planting_key'] : null;
        // The rain-only rule can say "ideal" while the barangay is rated Moderate or worse; a cheerful line next to a
        // warning would mislead (see planningLine), so the rating is said with it.
        if (in_array($key, ['good', 'possible'], true) && $risk['level'] !== 'Low') {
            $key = $risk['high'] ? 'check_first' : 'good_check';
        }
        $tone = match (true) {
            $key === null => 'info',
            in_array($key, ['good', 'possible'], true) => 'good',
            default => 'watch',
        };
        $duration = (int) config('farmer_dashboard.crop.default_duration_days');
        $good = array_values(array_filter($week['days'] ?? [], fn (array $d) => $d['good'] === true));

        $steps = [['key' => 'land_prep', 'when' => __('farmer.guide.before_planting', ['days' => FarmerCropPlanService::LAND_PREP_DAYS])]];
        foreach (FarmerCropPlanService::FERTILIZER_WINDOWS as $milestone => [$from, $to]) {
            $steps[] = ['key' => $milestone, 'when' => __('farmer.guide.days', ['from' => $from, 'to' => $to])];
        }
        $steps[] = ['key' => 'harvest', 'when' => __('farmer.guide.around_day', ['day' => $duration])];

        return [
            'key' => $key,
            'tone' => $tone,
            'title' => __('farmer.guide.title.'.($key ?? 'plan')),
            'note' => $key ? __('farmer.guide.note.'.$key) : __('farmer.guide.note.plan'),
            'rain' => $climate ? __('farmer.guide.rain', ['mm' => number_format($climate['rain'], 0), 'month' => $climate['month']]) : null,
            'good_days' => array_map(fn (array $d) => $d['is_today'] ? __('farmer.weather_card.today') : $d['date']->translatedFormat('D'), $good),
            'steps' => array_map(fn (array $s) => $s + ['label' => $this->milestoneLabel(['key' => $s['key'], 'label' => $s['key']])], $steps),
            'harvest_if_today' => $now->addDays($duration),
        ];
    }

    // ------------------------------------------------------------------ expected harvest — the Predictions page's own estimate

    /**
     * The model's raw result (cached 6 hours per farmer, with the time it ran), or why there is none.
     *
     * @param  Collection<int, FarmerPlantingSchedule>  $rows
     * @return array{state: string, result: ?array<string, mixed>}  state: ok | error | loading
     */
    private function prediction(User $user, $profile, Collection $rows, bool $refresh, bool $defer): array
    {
        $key = self::predictionCacheKey($user->id);
        if ($refresh) {
            Cache::forget($key);
        }
        if ($defer && ! Cache::has($key)) {
            return ['state' => 'loading', 'result' => null];
        }

        $farmType = $profile?->farm_type ?: \App\Models\FarmerProfile::FARM_TYPE_RAINFED;
        $lacksRecords = false;                                          // the model answered, but it has too few weather records to estimate from
        $result = Cache::remember($key, now()->addHours(6), function () use ($user, $farmType, &$lacksRecords) {
            try {
                $prediction = $this->riceYield->predictForUser(\App\Support\BusinessClock::now()->toImmutable(), $farmType, null, $user, [], PredictionType::RICE_YIELD->value);

                // A failed run is not cached (null is never stored), so the next load retries the model.
                if (! ($prediction['success'] ?? false)) {
                    Log::warning('Farmer dashboard yield prediction was not produced.', ['source' => 'rice-yield model', 'user_id' => $user->id, 'reason' => $prediction['api_error'] ?? null, 'missing_inputs' => $prediction['missing_inputs'] ?? []]);
                    $lacksRecords = ! empty($prediction['missing_inputs']) || str_contains(strtolower((string) ($prediction['api_error'] ?? '')), 'not enough');

                    return null;
                }
                $prediction['ran_at'] = CarbonImmutable::now(self::TIMEZONE)->toIso8601String();

                return $prediction;
            } catch (Throwable $e) {
                Log::warning('Farmer dashboard yield prediction failed.', ['source' => 'rice-yield model', 'user_id' => $user->id, 'exception' => $e::class, 'message' => $e->getMessage()]);

                return null;
            }
        });

        $yield = is_array($result) ? ($result['yield_tons_per_hectare'] ?? $result['predicted_yield'] ?? null) : null;
        if (! is_numeric($yield) || (float) $yield <= 0) {
            return ['state' => 'error', 'result' => null, 'cause' => $lacksRecords ? 'records' : 'service'];
        }

        return ['state' => 'ok', 'result' => $result];
    }

    /**
     * The Rice Yield Forecast. Production (tons) is shown only when the area comes from the farmer's own profile: the
     * model's fallback area is a documented default, so it would present a guess as this farm's harvest. The range
     * comes from the model's own uncertainty and is left out when the model gives none.
     *
     * @param  array{state: string, result: ?array<string, mixed>}  $prediction
     * @param  Collection<int, FarmerPlantingSchedule>  $rows
     * @return array<string, mixed>  state: ok | loading | error (error carries a reason and what is missing)
     */
    private function harvest(array $prediction, $profile, Collection $rows): array
    {
        // Only what the model really reads: the farm size. The planting date is not a model input, so it is never asked for here.
        $missing = (float) ($profile?->farm_area ?? 0) > 0 ? [] : ['area'];

        if ($prediction['state'] !== 'ok') {
            return [
                'state' => $prediction['state'],
                'missing' => $missing,
                'cause' => $prediction['cause'] ?? null,                        // records | service (a retry helps only the service)
                // What the farmer can fix comes first; otherwise say honestly whether the records or the service is the cause.
                'reason' => match (true) {
                    $missing !== [] => __('farmer.harvest.reason_missing'),
                    ($prediction['cause'] ?? null) === 'records' => __('farmer.harvest.reason_records'),
                    default => __('farmer.harvest.reason_service'),
                },
            ];
        }

        $result = $prediction['result'];
        $yield = (float) ($result['yield_tons_per_hectare'] ?? $result['predicted_yield']);
        $uncertainty = is_numeric($result['uncertainty'] ?? null) ? (float) $result['uncertainty'] : null;
        $profileArea = $profile?->farm_area;
        $usesOwnArea = ($result['input_sources']['area']['source'] ?? null) === 'farmer_profile' && is_numeric($profileArea) && (float) $profileArea > 0;
        $month = ! empty($result['target_month']) ? CarbonImmutable::parse($result['target_month'].'-01') : null;
        $ranAt = ! empty($result['ran_at']) ? CarbonImmutable::parse($result['ran_at'])->timezone(self::TIMEZONE) : null;

        $own = [
            'yield' => $yield,
            'range' => $this->kpi->harvestRange($yield, $uncertainty),
            'period' => $month?->format('F Y'),
            'month' => $month?->month,
            'area' => $usesOwnArea ? (float) $profileArea : null,
            'production' => $usesOwnArea ? round($yield * (float) $profileArea, 1) : null,
            'ran_at' => $ranAt,
        ];
        $outlook = $this->yieldOutlook($own, $rows);

        return ['state' => 'ok', 'missing' => $missing] + $own + ['outlook' => $outlook, 'typical' => $outlook ? $this->kpi->yieldAgainstTypical((float) $outlook['percent']) : null];
    }

    /**
     * Lian's historical average next to the farmer's own expected harvest, labelled as two different scopes. Only when
     * both exist; the historical figure is never presented as the farmer's own history.
     *
     * @param  array<string, mixed>  $prediction
     * @return array<string, mixed>|null
     */
    private function yieldOutlook(array $prediction, Collection $scheduleRows): ?array
    {
        $usual = $this->usualYield($prediction['month'] ?? null);
        if ($usual === null) {
            return null;
        }
        $mean = $usual['mean'];
        unset($usual['mean']);

        return $usual + [
            'own' => $this->ownHarvests($scheduleRows, (float) $prediction['yield']),
            'predicted' => round($prediction['yield'], 2),
            'difference' => round($prediction['yield'] - $mean, 2),
            'percent' => round(($prediction['yield'] - $mean) / $mean * 100),
        ];
    }

    /**
     * Lian's usual yield from the project yield records: the average of the same calendar month in past years, or of all
     * months when that month has no records (and says so). Null without records.
     *
     * @return array{historical: float, from: mixed, to: mixed, same_month: bool, month_name: ?string}|null
     */
    private function usualYield(?int $month): ?array
    {
        try {
            $latest = $this->yields->years()->first();
            $rows = $latest ? $this->yields->rows()->where('year', '<=', (int) $latest) : collect();
        } catch (Throwable $e) {
            Log::warning('Farmer dashboard yield history failed.', ['source' => 'project yield dataset', 'message' => $e->getMessage()]);

            return null;
        }

        // Compare like with like: a prediction is for one calendar month, so use the average of that same month in past years.
        $sameMonth = $month ? $rows->where('month', $month) : collect();
        $sameMonthBasis = $sameMonth->isNotEmpty();
        $basis = $sameMonthBasis ? $sameMonth : $rows;
        $mean = $basis->isEmpty() ? null : (float) $basis->avg('yield');
        if (! $mean || $mean <= 0) {
            return null;
        }

        return [
            'mean' => $mean,
            'historical' => round($mean, 2),
            'from' => $basis->min('year'),
            'to' => $basis->max('year'),
            'same_month' => $sameMonthBasis,
            'month_name' => $sameMonthBasis ? CarbonImmutable::create(2000, (int) $month, 1)->translatedFormat('F') : null,
        ];
    }

    /**
     * The farmer's own recorded harvests (tons per hectare), the honest comparison for their estimate. Null until at least
     * one harvest has been recorded, and always when the harvest column has not been migrated yet.
     *
     * @param  Collection<int, FarmerPlantingSchedule>  $rows
     * @return array{count: int, average: float, latest: float, latest_on: ?string, difference: float}|null
     */
    private function ownHarvests(Collection $rows, float $predicted): ?array
    {
        $done = $rows->filter(fn (FarmerPlantingSchedule $r) => is_numeric($r->getAttribute('actual_yield')) && (float) $r->getAttribute('actual_yield') > 0)
            ->sortBy(fn (FarmerPlantingSchedule $r) => ($r->harvested_on ?? $r->planting_date)?->timestamp ?? 0);
        if ($done->isEmpty()) {
            return null;
        }
        $average = (float) $done->avg(fn (FarmerPlantingSchedule $r) => (float) $r->actual_yield);
        $latest = $done->last();

        return [
            'count' => $done->count(),
            'average' => round($average, 2),
            'latest' => round((float) $latest->actual_yield, 2),
            'latest_on' => ($latest->harvested_on ?? $latest->planting_date)?->format('M Y'),
            'difference' => round($predicted - $average, 2),
        ];
    }

    // ------------------------------------------------------------------ rice production records — the Rice Production page

    /**
     * The latest recorded harvest for the farmer's barangay, read the way the Rice Production page lists records (newest
     * year first). It is a PAST record, labelled as such, never today's harvest. Null when there is none.
     *
     * @return array<string, mixed>|null
     */
    private function production(?string $barangay): ?array
    {
        if (! $barangay) {
            return null;
        }
        try {
            $row = RiceProduction::query()->where('barangay', $barangay)->whereNotNull('yield_per_hectare')
                ->latest('year')->latest('id')->first();
        } catch (Throwable $e) {
            Log::warning('Farmer dashboard rice production record failed.', ['source' => 'rice productions', 'exception' => $e::class]);

            return null;
        }
        if (! $row || (float) $row->yield_per_hectare <= 0) {
            return null;
        }

        return [
            'yield' => (float) $row->yield_per_hectare,
            'season' => $row->season ? __('farmer.production.season.'.strtolower((string) $row->season)) : null,
            'year' => $row->year,
            'when' => $row->production_month?->format('F Y') ?? (string) $row->year,
        ];
    }

    // ------------------------------------------------------------------ advisories, notifications, messages, community

    /**
     * Only published, in-effect advisories for the farmer's area (never drafts, pending, expired or archived), newest
     * first, worded the way the Advisories page words them. `new` counts those published in the last week.
     *
     * @return array{items: array<int, array<string, mixed>>, new: int}
     */
    private function advisories(?string $barangay, CarbonImmutable $now): array
    {
        try {
            $query = SampleData::without(PlantingAdvisory::query(), 'planting_advisories')->active()->forBarangay($barangay);
            $rows = (clone $query)->latest('valid_from')->latest('id')->take(3)->get();
            $new = (clone $query)->whereRaw('COALESCE(published_at, valid_from, created_at) >= ?', [$now->subDays(self::REMINDER_DAYS)->utc()->format('Y-m-d H:i:s')])->count();
        } catch (Throwable $e) {
            Log::warning('Farmer dashboard advisories failed.', ['source' => 'planting advisories', 'exception' => $e::class]);

            return ['items' => [], 'new' => 0];
        }

        $items = $rows->map(function (PlantingAdvisory $a) use ($now, $barangay): array {
            $when = ($a->published_at ?? $a->valid_from ?? $a->created_at);
            $when = $when ? CarbonImmutable::instance($when)->timezone(self::TIMEZONE) : null;
            $view = new FarmerAdvisoryView($a);

            return [
                'id' => $a->id,
                'title' => (string) $a->title,
                'summary' => (string) str($view->farmerEffect())->squish()->limit(140),
                'action' => (string) str($view->farmerAction())->squish()->limit(140),
                'source' => $view->sourceName(),
                'for_barangay' => $barangay && $a->target_barangay === $barangay,
                'tone' => $view->farmerRiskTone(),
                'when' => $when,
                'is_new' => $when !== null && $when->gte($now->subDays(self::NEW_ADVISORY_DAYS)),
                'href' => route('planting-advisories.show', $a),
            ];
        })
            // The farmer's own barangay first, then Lian-wide; newest first within each.
            ->sortBy(fn (array $a) => [$a['for_barangay'] ? 0 : 1, -($a['when']?->timestamp ?? 0)])
            ->values()->all();

        return ['items' => $items, 'new' => $new];
    }

    private function unread(User $user): ?int
    {
        try {
            return SampleData::without(UserNotification::query(), 'notifications')->where('user_id', $user->id)->where('is_read', false)->count();
        } catch (Throwable) {
            return null;
        }
    }

    /** Unread messages, counted by the same rule as the Messages page. Null when the messages could not be read. */
    private function unreadMessages(User $user): ?int
    {
        if (! SchemaCache::hasTable('conversation_messages')) {
            return null;
        }
        try {
            return ConversationMessage::unreadCountFor($user);
        } catch (Throwable $e) {
            Log::warning('Farmer dashboard unread messages failed.', ['source' => 'messages', 'exception' => $e::class]);

            return null;
        }
    }

    /**
     * The newest community post a farmer can see (the Community page shows farmers the posts that are not archived,
     * newest first). Null when there is none.
     *
     * @return array<string, mixed>|null
     */
    private function community(): ?array
    {
        try {
            $post = FeedPost::query()->with('author:id,name,role')->whereNull('archived_at')->latest()->latest('id')->first();
        } catch (Throwable $e) {
            Log::warning('Farmer dashboard community post failed.', ['source' => 'community feed', 'exception' => $e::class]);

            return null;
        }
        if (! $post) {
            return null;
        }

        return [
            'title' => (string) ($post->title ?: str($post->body)->squish()->limit(60)),
            'summary' => (string) str(strip_tags((string) $post->body))->squish()->limit(110),
            'author' => $post->author?->role === User::ROLE_MAO ? __('farmer.updates.mao') : ($post->author?->name ?: __('farmer.updates.mao')),
            'when' => $post->created_at ? CarbonImmutable::instance($post->created_at)->timezone(self::TIMEZONE) : null,
        ];
    }

    // ------------------------------------------------------------------ alerts (real warnings, never hidden)

    /**
     * Each alert has a `level` (danger = red, watch = amber) that the page shows with an icon AND a text label, a `kind`
     * (rain, wind, heat, risk, typhoon, notice), WHAT is happening and WHAT to do.
     *
     * @return array<int, array<string, string>>
     */
    private function alerts(User $user, array $weather, ?array $risk, CarbonImmutable $now): array
    {
        $alerts = [];
        $forecastHref = route('weather-predictions.index', ['tab' => 'weather-forecast']);

        // Weather alerts sent to this farmer (unread warnings about weather).
        try {
            $query = SampleData::without(UserNotification::query(), 'notifications')
                ->where('user_id', $user->id)->where('is_read', false)->where('type', 'Warning');
            if (SchemaCache::hasColumn('notifications', 'category')) {
                $query->where('category', UserNotification::CATEGORY_WEATHER);
            } else {
                // Until the category migration has run, fall back to guessing "weather" from the wording.
                $keywords = ['weather', 'rain', 'rainfall', 'storm', 'typhoon', 'flood', 'drought', 'heat', 'pagasa', 'climate'];
                $query->where(function ($q) use ($keywords): void {
                    foreach ($keywords as $keyword) {
                        $q->orWhere('title', 'like', '%'.$keyword.'%')->orWhere('message', 'like', '%'.$keyword.'%');
                    }
                });
            }
            $notes = $query->latest()->take(3)->get();
            foreach ($notes as $n) {
                $alerts[] = ['severity' => 'warning', 'kind' => 'notice', 'icon' => 'alert', 'title' => (string) $n->title,
                    'what' => (string) str($n->message)->squish()->limit(140), 'todo' => __('farmer.alerts.notice_todo'), 'href' => route('notifications.index')];
            }
        } catch (Throwable $e) {
            Log::warning('Farmer dashboard alert notifications failed.', ['source' => 'notifications', 'exception' => $e::class]);
        }

        // Weather warnings for today and the next couple of days.
        if ($weather['state'] === 'ok') {
            $today = $now->startOfDay();
            $seen = [];
            foreach ($weather['days'] as $day) {
                $ahead = (int) $today->diffInDays($day['date']->startOfDay(), false);
                if ($ahead < 0 || $ahead > self::WARNING_LOOKAHEAD_DAYS) {
                    continue;
                }
                $when = $ahead <= 1 ? $this->aheadText($ahead) : __('farmer.alerts.on_day', ['day' => $day['date']->format('l')]);
                if ($day['heavy'] && ! isset($seen['rain'])) {
                    $seen['rain'] = true;
                    $alerts[] = ['severity' => 'warning', 'kind' => 'rain', 'icon' => 'rain', 'title' => __('farmer.alerts.heavy_rain.title', ['when' => $when]),
                        'what' => __('farmer.alerts.heavy_rain.what', ['mm' => FarmerKpiService::formatRain($day['rain'])]), 'todo' => __('farmer.alerts.heavy_rain.todo'), 'href' => $forecastHref];
                }
                if ($day['strong_wind'] && ! isset($seen['wind'])) {
                    $seen['wind'] = true;
                    $alerts[] = ['severity' => 'watch', 'kind' => 'wind', 'icon' => 'wind', 'title' => __('farmer.alerts.strong_wind.title', ['when' => $when]),
                        'what' => __('farmer.alerts.strong_wind.what', ['kmh' => number_format($day['wind'], 0)]), 'todo' => __('farmer.alerts.strong_wind.todo'), 'href' => $forecastHref];
                }
                if ($day['high_heat'] && ! isset($seen['heat'])) {
                    $seen['heat'] = true;
                    $alerts[] = ['severity' => 'watch', 'kind' => 'heat', 'icon' => 'heat', 'title' => __('farmer.alerts.high_heat.title', ['when' => $when]),
                        'what' => __('farmer.alerts.high_heat.what', ['temp' => number_format($day['high'], 0)]), 'todo' => __('farmer.alerts.high_heat.todo'), 'href' => $forecastHref];
                }
            }
        }

        if ($risk && ($risk['state'] ?? null) === 'ok' && $risk['high']) {
            $alerts[] = ['severity' => $risk['level'] === 'Severe' ? 'critical' : 'warning', 'kind' => 'risk', 'icon' => 'map',
                'title' => __('farmer.alerts.risk.title', ['level' => $risk['level_label']]), 'what' => $this->planningLine($risk),
                'todo' => __('farmer.alerts.risk.todo'), 'href' => route('heat-map.index')];
        }

        if ($item = $this->typhoonAlert($user)) {
            $alerts[] = $item;
        }

        $rank = ['critical' => 0, 'warning' => 1, 'watch' => 2];
        usort($alerts, fn ($a, $b) => ($rank[$a['severity']] ?? 3) <=> ($rank[$b['severity']] ?? 3));

        return array_map(fn (array $a) => $a + [
            'level' => $a['severity'] === 'watch' ? 'watch' : 'danger',
            'message' => trim($a['what'].' '.$a['todo']),
        ], $alerts);
    }

    /** An open typhoon safety check-in the farmer has not answered yet. */
    private function typhoonAlert(User $user): ?array
    {
        try {
            $event = $this->typhoon->activeEvent();
            if (! $event) {
                return null;
            }
            $answered = SchemaCache::hasTable('typhoon_safety_responses')
                && TyphoonSafetyResponse::query()->where('user_id', $user->id)->where('event_key', $event['key'])->exists();
        } catch (Throwable) {
            return null;
        }

        return $answered ? null : ['severity' => 'critical', 'kind' => 'typhoon', 'icon' => 'storm', 'title' => __('farmer.alerts.typhoon.title'),
            'what' => __('farmer.alerts.typhoon.what'), 'todo' => __('farmer.alerts.typhoon.todo'), 'href' => route('planting-advisories.index')];
    }

    // ------------------------------------------------------------------ planting schedule — the Calendar's own records

    /**
     * All of the farmer's schedules (one per plot), read once for the schedule, the harvest card and the setup list.
     * Empty when the table is missing or cannot be read.
     *
     * @return Collection<int, FarmerPlantingSchedule>
     */
    private function scheduleRows(User $user): Collection
    {
        if (! SchemaCache::hasTable('farmer_planting_schedules')) {
            return collect();
        }
        try {
            return FarmerPlantingSchedule::query()->where('user_id', $user->id)->orderBy('planting_date')->limit(100)->get();
        } catch (Throwable $e) {
            Log::warning('Farmer dashboard planting schedules failed.', ['source' => 'planting schedules', 'exception' => $e::class]);

            return collect();
        }
    }

    /**
     * The crop calendar for every open plot, with weather advice. Null with no open schedule.
     *
     * @param  Collection<int, FarmerPlantingSchedule>  $rows
     * @return array<string, mixed>|null
     */
    private function plan(Collection $rows, array $weather, CarbonImmutable $now): ?array
    {
        try {
            $open = $rows->reject(fn (FarmerPlantingSchedule $r) => $r->monitoringStatus() === 'harvested');

            return $this->cropPlan->build($open, $weather['state'] === 'ok' ? $weather['days'] : [], $now);
        } catch (Throwable $e) {
            Log::warning('Farmer dashboard crop plan failed.', ['source' => 'crop plan', 'exception' => $e::class]);

            return null;
        }
    }

    /** A crop activity's name in the farmer's language ("First extra fertilizer" / "Unang dagdag na abono"). */
    private function milestoneLabel(array $milestone): string
    {
        $key = 'farmer.milestones.'.$milestone['key'];

        return Lang::has($key) ? __($key) : (string) $milestone['label'];
    }

    /** One crop-plan advice rule, said in the farmer's language from its key and numbers (FarmerCropPlanService::advice). */
    private function adviceText(array $advice): string
    {
        $key = 'farmer.crop_advice.'.($advice['key'] ?? '');
        if (empty($advice['key']) || ! Lang::has($key)) {
            return (string) $advice['text'];
        }
        $params = (array) ($advice['params'] ?? []);
        $params['when'] = $this->aheadText((int) ($params['ahead'] ?? 0));
        if (isset($params['activity'])) {
            $params['activity'] = mb_strtolower($this->milestoneLabel(['key' => $params['activity'], 'label' => $params['activity']]));
        }
        unset($params['ahead']);

        return __($key, $params);
    }

    /**
     * Each open plot: its day in the season, its stage, the activity due now and the next one, the expected harvest dates
     * and the weather advice for the activity. State `empty` with no open schedule.
     *
     * @param  Collection<int, FarmerPlantingSchedule>  $rows
     * @return array<string, mixed>
     */
    private function season(Collection $rows, ?array $plan, CarbonImmutable $now): array
    {
        $open = $rows->reject(fn (FarmerPlantingSchedule $r) => $r->monitoringStatus() === 'harvested' || ! $r->planting_date)
            ->sortBy(fn (FarmerPlantingSchedule $r) => $r->planting_date->timestamp)->values();
        if ($open->isEmpty()) {
            return ['state' => 'empty'];
        }

        $planPlots = collect($plan['plots'] ?? [])->keyBy('edit_id');
        $window = (int) FarmerCropPlanService::HARVEST_DAYS_BEFORE;
        $today = $now->startOfDay();
        $plots = [];
        foreach ($open->take(FarmerCropPlanService::MAX_PLOTS) as $row) {
            // Date columns are stored as UTC midnight: read them as calendar dates in Manila so "day 5" is not off by one near midnight.
            $planting = CarbonImmutable::parse($row->planting_date->toDateString(), self::TIMEZONE);
            $harvestDate = $row->harvestDateForPlanning();
            $harvestOn = $harvestDate ? CarbonImmutable::parse($harvestDate->toDateString(), self::TIMEZONE) : null;
            $duration = $harvestOn ? (int) $planting->diffInDays($harvestOn, false) : null;
            $crop = $this->kpi->cropAge($planting, $now, $duration !== null && $duration > 0 ? $duration : null);
            $expected = $harvestOn ?? $planting->addDays($crop['duration']);
            $planPlot = $planPlots->get($row->id);
            $milestones = collect($planPlot['milestones'] ?? []);
            $current = $milestones->firstWhere('state', 'current');
            $next = $milestones->firstWhere('state', 'upcoming');

            $plots[] = [
                'edit_id' => $row->id,
                'number' => count($plots) + 1,
                'planting_date' => $planting,
                'crop' => $crop,
                'harvest_from' => $expected->subDays($window),
                'harvest_to' => $expected->addDays(FarmerCropPlanService::HARVEST_DAYS_AFTER),
                'current' => $current ? ['key' => $current['key'], 'kind' => $current['kind'], 'label' => $this->milestoneLabel($current), 'start' => $current['start'], 'end' => $current['end']] : null,
                'next' => $next ? ['key' => $next['key'], 'kind' => $next['kind'], 'label' => $this->milestoneLabel($next), 'start' => $next['start'],
                    'in_days' => max(0, (int) $today->diffInDays($next['start']->startOfDay(), false))] : null,
                'advice' => array_map(fn (array $a) => ['severity' => $a['severity'], 'text' => $this->adviceText($a)], (array) ($planPlot['advice'] ?? [])),
            ];
        }

        return ['state' => 'ok', 'plots' => $plots, 'more' => max(0, $open->count() - count($plots))];
    }

    /**
     * "My Farm Schedule": the first open plot (the earliest planting), which is also what the Planting Status number shows.
     *
     * @param  array<string, mixed>  $season
     * @return array<string, mixed>  state: ok | empty
     */
    private function schedule(array $season, CarbonImmutable $now): array
    {
        if ($season['state'] !== 'ok') {
            return ['state' => 'empty'];
        }
        $plot = $season['plots'][0];
        $crop = $plot['crop'];
        $planted = $crop['value'] !== null;

        return [
            'state' => 'ok',
            'edit_id' => $plot['edit_id'],
            'planting_date' => $plot['planting_date'],
            'planted' => $planted,
            'day' => $crop['value'],
            'duration' => $crop['duration'],
            'percent' => $planted ? min(100, max(0, (int) round($crop['value'] / max(1, $crop['duration']) * 100))) : 0,
            'stage' => $planted ? ($crop['stage_label'] ?? null) : null,
            'until_planting' => $planted ? null : max(0, (int) $now->startOfDay()->diffInDays($plot['planting_date'], false)),
            'current' => $plot['current'],
            'next' => $plot['next'],
            'advice' => $plot['advice'],
            'harvest_from' => $plot['harvest_from'],
            'harvest_to' => $plot['harvest_to'],
            'plots' => count($season['plots']) + $season['more'],
        ];
    }

    // ------------------------------------------------------------------ my farm

    /**
     * The farmer's own farm details: barangay, size, how it gets water and the planting date. A null value means "not
     * saved yet" (the card says so and offers the pop-up that fixes it); nothing personal beyond that is shown.
     *
     * @param  array<string, mixed>  $schedule
     * @return array<string, mixed>
     */
    private function profile($profile, ?string $barangay, array $schedule): array
    {
        $area = (float) ($profile?->farm_area ?? 0);
        $mappedIn = null;
        try {
            $boundary = $profile?->farmBoundary;
            if ($boundary && (float) $boundary->calculated_area_hectares > 0) {
                $mappedIn = $boundary->getAttribute('barangay') ?: LianGeography::locate($boundary->boundary_coordinates ?? [])['barangay'];
            }
        } catch (Throwable) {
            // an unreadable boundary counts as not mapped
        }
        $type = $profile?->farm_type ? strtolower((string) $profile->farm_type) : null;

        return [
            'barangay' => $barangay,
            'area' => $area > 0 ? $area : null,
            'water' => $type && Lang::has('farmer.my_farm.water.'.$type) ? __('farmer.my_farm.water.'.$type) : null,
            'planting_date' => $schedule['state'] === 'ok' ? $schedule['planting_date'] : null,
            'mapped' => $this->hasBoundary($profile),
            'mapped_in' => $mappedIn ? LianGeography::barangayLabel($mappedIn) : null,
        ];
    }

    /**
     * "Farm Condition": Good, Watch or Needs Attention. It adds nothing of its own: it is the most serious of the
     * warnings shown in Today's Highlights and the barangay risk rating, so the card can never disagree with them.
     * With neither weather nor a rating there is no condition to report (never "Good" by default).
     *
     * @param  array<string, mixed>  $weather
     * @param  array<string, mixed>  $risk
     * @param  array<int, array<string, mixed>>  $alerts  most serious first
     * @return array{value: ?string, status?: string, note?: string}
     */
    private function condition(array $weather, array $risk, array $alerts): array
    {
        $rated = $risk['state'] === 'ok';
        $top = $alerts[0] ?? null;
        if ($weather['state'] !== 'ok' && ! $rated && $top === null) {
            return ['value' => null];
        }

        // Heavy rain today is a risk even when no warning carries it (a stored reading has no day-by-day list to warn from).
        $rainToday = $weather['state'] === 'ok' ? ($weather['levels']['rain'] ?? null) : null;
        $status = match (true) {
            ($top['level'] ?? null) === 'danger' || ($rated && $risk['high']) || $rainToday === 'heavy' => 'danger',
            $top !== null || ($rated && strtolower((string) $risk['level']) === 'moderate') => 'watch',
            default => 'good',
        };
        $note = match (true) {
            $top !== null => (string) str(rtrim((string) $top['title'], '.').'.')->squish()->limit(120),
            $rainToday === 'heavy' => __('farmer.condition.note_heavy'),
            // The rating's own meaning is already on the Barangay Farm Risk card: say what to do instead.
            $status !== 'good' => __('farmer.condition.note_check'),
            $rainToday !== null => __('farmer.condition.note_'.$rainToday),
            default => __('farmer.condition.note_default'),
        };

        return ['value' => __('farmer.condition.'.$status), 'status' => $status, 'note' => $note];
    }

    // ------------------------------------------------------------------ today's highlights + reminders

    /**
     * Two to four things to know today, each saying WHAT is happening and WHAT to do. Every card comes from data shown
     * elsewhere on the page (warnings, today's action, the crop calendar, the newest advisory); nothing is made up.
     *
     * @param  array<int, array<string, mixed>>  $alerts
     * @param  array<int, array<string, mixed>>  $actions
     * @param  array<string, mixed>  $schedule
     * @param  array<int, array<string, mixed>>  $advisories
     * @return array<int, array<string, mixed>>
     */
    private function highlights(array $alerts, array $actions, array $schedule, array $advisories, array $weather, array $risk, CarbonImmutable $now): array
    {
        $items = [];
        $covered = [];
        foreach (array_slice($alerts, 0, 2) as $alert) {
            $covered[] = $alert['kind'];
            $items[] = [
                'key' => 'alert', 'level' => $alert['level'], 'icon' => $alert['icon'],
                'kicker' => __('farmer.highlights.kicker.'.($alert['level'] === 'danger' ? 'warning' : 'watch')),
                'title' => $alert['title'], 'what' => $alert['what'], 'todo' => $alert['todo'],
                'href' => $alert['href'], 'link' => __('farmer.highlights.link.'.$alert['kind']),
            ];
        }

        // What to do today: the first action a warning above does not already say.
        $sameAs = ['heavy_rain' => 'rain', 'heat' => 'heat', 'wind' => 'wind', 'risk' => 'risk'];
        foreach ($actions as $action) {
            if (in_array($sameAs[$action['key']] ?? null, $covered, true)) {
                continue;
            }
            $items[] = [
                'key' => 'action', 'level' => in_array($action['key'], ['normal', 'no_weather'], true) ? 'good' : 'info', 'icon' => $action['icon'],
                'kicker' => __('farmer.highlights.kicker.today'), 'title' => $action['title'], 'what' => $action['reason'], 'todo' => null,
                'ask' => $action['ask'],
            ];
            break;
        }

        // The farm activity due now, or starting within the reminder window.
        if ($schedule['state'] === 'ok') {
            $current = $schedule['current'];
            $next = $schedule['next'];
            $advice = $schedule['advice'][0] ?? null;
            if ($current) {
                $items[] = [
                    'key' => 'activity', 'level' => $advice && $advice['severity'] === 'warning' ? 'watch' : 'info', 'icon' => 'calendar',
                    'kicker' => __('farmer.highlights.kicker.activity'),
                    'title' => __('farmer.highlights.activity_now', ['activity' => $current['label']]),
                    'what' => __('farmer.highlights.activity_until', ['date' => $current['end']->format('M j')]),
                    'todo' => $advice['text'] ?? __('farmer.highlights.activity_todo.'.$current['kind']),
                    'href' => route('calendar.index'), 'link' => __('farmer.highlights.link.calendar'),
                ];
            } elseif ($next && $next['in_days'] <= FarmerCropPlanService::REMIND_DAYS) {
                $items[] = [
                    'key' => 'activity', 'level' => 'info', 'icon' => 'calendar',
                    'kicker' => __('farmer.highlights.kicker.activity'),
                    'title' => trans_choice('farmer.highlights.activity_soon', $next['in_days'], ['activity' => $next['label'], 'count' => $next['in_days']]),
                    'what' => __('farmer.highlights.activity_on', ['date' => $next['start']->format('l, M j')]),
                    'todo' => $advice['text'] ?? __('farmer.highlights.activity_todo.'.$next['kind']),
                    'href' => route('calendar.index'), 'link' => __('farmer.highlights.link.calendar'),
                ];
            }
        }

        // Heavy rain later this week (the warnings above only look two days ahead).
        if (! in_array('rain', $covered, true) && $weather['state'] === 'ok') {
            $later = collect($weather['days'])->first(function (array $d) use ($now) {
                $ahead = (int) $now->startOfDay()->diffInDays($d['date']->startOfDay(), false);

                return $d['heavy'] && $ahead > self::WARNING_LOOKAHEAD_DAYS;
            });
            if ($later) {
                $items[] = [
                    'key' => 'week', 'level' => 'watch', 'icon' => 'rain',
                    'kicker' => __('farmer.highlights.kicker.week'),
                    'title' => __('farmer.highlights.week_rain', ['day' => $later['date']->format('l')]),
                    'what' => __('farmer.alerts.heavy_rain.what', ['mm' => FarmerKpiService::formatRain($later['rain'])]),
                    'todo' => __('farmer.highlights.week_todo'),
                    'href' => route('weather-predictions.index', ['tab' => 'weather-forecast']), 'link' => __('farmer.highlights.link.rain'),
                ];
            }
        }

        // A Moderate rating for the barangay (High and Severe are already warnings above): the Heat Map's own rating.
        if (($risk['state'] ?? null) === 'ok' && strtolower((string) $risk['level']) === 'moderate') {
            $items[] = [
                'key' => 'risk', 'level' => 'watch', 'icon' => 'map',
                'kicker' => __('farmer.highlights.kicker.watch'),
                'title' => __('farmer.highlights.risk_title', ['level' => $risk['level_full'], 'barangay' => $risk['barangay']]),
                'what' => trim($risk['meaning'].($risk['concern'] ? ' '.__('farmer.risk.watch').': '.$risk['concern'].'.' : '')),
                'todo' => __('farmer.learn.risk.todo'),
                'href' => route('heat-map.index'), 'link' => __('farmer.highlights.link.risk'),
            ];
        }

        // A new advisory from the last few days.
        $fresh = collect($advisories)->first(fn (array $a) => $a['is_new']);
        if ($fresh) {
            $items[] = [
                'key' => 'advisory', 'level' => 'info', 'icon' => 'advisory',
                'kicker' => __('farmer.highlights.kicker.advisory'),
                'title' => $fresh['title'], 'what' => $fresh['summary'], 'todo' => $fresh['action'],
                'href' => $fresh['href'], 'link' => __('farmer.highlights.link.advisory'), 'modal' => true,
            ];
        }

        return array_slice($items, 0, self::MAX_HIGHLIGHTS);
    }

    /**
     * Small "don't forget" lines: the next farm activity (when Today's Highlights does not already show it), farm details
     * still missing, new advisories, unread messages and notifications. Only what applies is listed.
     *
     * @param  array<string, mixed>  $schedule
     * @param  array<string, mixed>|null  $setup
     * @param  array<int, array<string, mixed>>  $highlights
     * @return array<int, array<string, mixed>>
     */
    private function reminders(array $schedule, ?array $setup, int $newAdvisories, ?int $messages, ?int $notifications, array $highlights): array
    {
        $items = [];
        $shown = array_column($highlights, 'key');

        if ($schedule['state'] === 'ok' && ! in_array('activity', $shown, true) && $schedule['next'] && $schedule['next']['in_days'] <= self::REMINDER_DAYS) {
            $items[] = ['key' => 'activity', 'icon' => 'calendar', 'href' => route('calendar.index'),
                'text' => trans_choice('farmer.reminders.activity', $schedule['next']['in_days'], ['activity' => $schedule['next']['label'], 'count' => $schedule['next']['in_days']])];
        }
        if ($setup) {
            $missing = count($setup['items']);
            $items[] = ['key' => 'setup', 'icon' => 'farmers', 'href' => '#mfdFarmTitle', 'anchor' => true,
                'text' => trans_choice('farmer.reminders.setup', $missing, ['count' => $missing, 'first' => mb_strtolower($setup['items'][0]['short'])])];
        }
        if ($newAdvisories > 0) {
            $items[] = ['key' => 'advisories', 'icon' => 'advisory', 'href' => route('planting-advisories.index'),
                'text' => trans_choice('farmer.reminders.advisories', $newAdvisories, ['count' => $newAdvisories])];
        }
        if ($messages) {
            $items[] = ['key' => 'messages', 'icon' => 'message', 'href' => route('messages.index'),
                'text' => trans_choice('farmer.reminders.messages', $messages, ['count' => $messages])];
        }
        if ($notifications) {
            $items[] = ['key' => 'notifications', 'icon' => 'bell', 'href' => route('notifications.index'),
                'text' => trans_choice('farmer.reminders.notifications', $notifications, ['count' => $notifications])];
        }

        return $items;
    }

    // ------------------------------------------------------------------ quick actions + setup

    /**
     * What the Quick Actions say: "Add" until the farmer has saved that thing, then "Update".
     *
     * @param  Collection<int, FarmerPlantingSchedule>  $rows
     * @return array{farm: bool, map: bool, plan: bool}
     */
    private function quick($profile, Collection $rows): array
    {
        return [
            'farm' => (float) ($profile?->farm_area ?? 0) > 0,
            'map' => $this->hasBoundary($profile),
            'plan' => $rows->contains(fn (FarmerPlantingSchedule $r) => $r->planting_date && $r->monitoringStatus() !== 'harvested'),
        ];
    }

    private function hasBoundary($profile): bool
    {
        try {
            return (float) ($profile?->farmBoundary?->calculated_area_hectares ?? 0) > 0;
        } catch (Throwable) {
            return false;                                               // an unreadable boundary counts as not mapped
        }
    }

    /**
     * What is missing from the farmer's farm information and what filling it in would unlock. Each item is one real gap.
     * The planting date is asked for by the schedule card, so it is not listed here.
     *
     * @param  Collection<int, FarmerPlantingSchedule>  $rows
     * @return array{items: array<int, array<string, mixed>>, done: int, total: int}|null
     */
    private function setup($profile, ?string $barangay, Collection $rows): ?array
    {
        $hasBoundary = $this->hasBoundary($profile);
        $unrecorded = $rows->first(fn (FarmerPlantingSchedule $r) => $r->monitoringStatus() === 'harvested' && ! (is_numeric($r->getAttribute('actual_yield')) && (float) $r->getAttribute('actual_yield') > 0));

        $checks = [
            ['key' => 'barangay', 'ok' => (bool) $barangay, 'href' => route('profile.edit')],
            ['key' => 'farm_type', 'ok' => (bool) $profile?->farm_type, 'href' => route('profile.edit')],
            ['key' => 'farm_area', 'ok' => (float) ($profile?->farm_area ?? 0) > 0, 'href' => route('profile.edit')],
            ['key' => 'boundary', 'ok' => $hasBoundary, 'href' => route('farmer.boundary.edit')],
        ];
        if ($unrecorded && SchemaCache::hasColumn('farmer_planting_schedules', 'actual_yield')) {
            $checks[] = ['key' => 'harvest', 'ok' => false, 'href' => route('farmer.planting-schedules.edit', $unrecorded->id)];
        }

        $items = array_values(array_filter($checks, fn (array $c) => ! $c['ok']));
        if ($items === []) {
            return null;
        }
        $items = array_map(fn (array $c) => $c + [
            'title' => __('farmer.setup.'.$c['key'].'.title'),
            'short' => __('farmer.setup.'.$c['key'].'.short'),
            'benefit' => __('farmer.setup.'.$c['key'].'.benefit'),
            'cta' => __('farmer.setup.'.$c['key'].'.cta'),
        ], $items);

        return ['items' => $items, 'done' => count($checks) - count($items), 'total' => count($checks)];
    }
}
