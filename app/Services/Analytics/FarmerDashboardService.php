<?php

namespace App\Services\Analytics;

use App\Enums\PredictionType;
use App\Models\FarmerPlantingSchedule;
use App\Models\HeatmapArea;
use App\Models\Notification as UserNotification;
use App\Models\PlantingAdvisory;
use App\Models\TyphoonSafetyResponse;
use App\Models\User;
use App\Services\Prediction\RiceYieldPredictionService;
use App\Services\Risk\BarangayRiskMapService;
use App\Services\TyphoonSafetyService;
use App\Services\Weather\FarmerDashboardWeatherService;
use App\Services\Weather\RecentRainService;
use App\Services\WeatherApiService;
use App\Support\DashboardData;
use App\Support\ProjectYieldDataset;
use App\Support\SampleData;
use App\Support\LianGeography;
use App\Support\SchemaCache;
use App\Support\WeatherConditionLabel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Everything the Farmer dashboard shows, from ONE place, in plain language (all wording is in lang/en/farmer.php).
 *
 * Rules that shape it:
 *   - every number is queried or computed here from a real source (the KPI maths is in FarmerKpiService), never typed in;
 *   - a card without real data says what is missing ("—" and a reason, never 0) and how to fix it;
 *   - a card whose source fails shows its own "We can't show this right now" state; other cards keep working;
 *   - failures are logged for IT with the source name;
 *   - the current weather, today's rain and today's weather card are all read from the SAME stored reading;
 *   - Lian-wide values are labelled as Lian, never as "your farm"; estimates are labelled as estimates;
 *   - t/ha (yield) and tons (production) are never mixed up: production is shown only from the farmer's own farm area.
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

    public function __construct(
        private readonly FarmerDashboardWeatherService $weather,
        private readonly WeatherApiService $weatherApi,
        private readonly BarangayRiskMapService $risk,
        private readonly RiceYieldPredictionService $riceYield,
        private readonly TyphoonSafetyService $typhoon,
        private readonly ProjectYieldDataset $yields,
        private readonly FarmerCropPlanService $cropPlan,
        private readonly FarmerKpiService $kpi,
        private readonly FarmerRainAnalyticsService $rainAnalytics,
        private readonly RecentRainService $recentRain,
    ) {}

    public static function cacheKey(User $user): string
    {
        return 'dashboard:farmer:overview:'.$user->id.':v'.DashboardData::version();
    }

    /** The cached yield prediction is dropped whenever the farmer's profile or planting schedule changes (see AppServiceProvider). */
    public static function predictionCacheKey(int $userId): string
    {
        return 'dashboard:farmer-prediction:'.$userId;
    }

    // ------------------------------------------------------------------ payload

    /**
     * @param  array<int, string>  $refresh  parts to rebuild from their source instead of a cache: weather, harvest, risk, rain
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
        $risk = $this->riskStatus($barangay, in_array('risk', $refresh, true));
        $rows = $this->scheduleRows($user);
        $prediction = $this->prediction($user, $profile, $rows, in_array('harvest', $refresh, true), $deferHarvest);
        $advisories = $this->advisories($barangay);
        $alerts = $this->alerts($user, $weather, $risk, $now);
        $rain = $this->rainHistory(in_array('rain', $refresh, true) || $forceWeather);
        $kpis = $this->kpiCards($weather, $rain, $alerts, $now);
        $plan = $this->plan($rows, $weather, $now);
        $season = $this->season($rows, $plan, $now);

        return [
            'generated_at' => $now,
            'greeting' => __('farmer.greeting.'.$this->period($now), ['name' => $this->displayName($user)]),
            'name' => $this->displayName($user),
            'barangay' => $barangay,
            'place' => $barangay ? __('farmer.place', ['barangay' => $barangay]) : __('farmer.place_default'),
            'weather' => $weather,
            'alerts' => $alerts,
            'actions' => $this->actions($weather, $risk, $now),
            'kpis' => $kpis,
            'week' => $this->week($weather, $now),
            'farm' => $this->farm($profile, $rows, $season, $risk, $weather, $alerts),
            'season' => $season,
            'harvest' => $this->harvest($prediction, $profile, $rows),
            'rain' => $this->rainSection($now),
            'risk' => $risk,
            'setup' => $this->setup($profile, $barangay, $rows),
            'quick' => $this->quick($profile, $rows),
            'advisories' => $advisories,
            'unread' => $this->unread($user),
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

    // ------------------------------------------------------------------ weather (live, Lian-wide)

    /**
     * One reading, one clock: "now", today's rain and today's weather card all come from the same stored reading
     * (current conditions and the daily list in the same provider response), and are written by one formatter.
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
                'condition' => $condition !== '' ? $condition : __('farmer.weather.unknown'),
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

        $conditionNow = (string) (WeatherConditionLabel::display($current['condition'] ?? null) ?: __('farmer.weather.unknown'));
        $humidity = is_numeric($current['humidity'] ?? null) ? (float) $current['humidity'] : null;
        $feels = is_numeric($current['feels_like'] ?? null) ? (float) $current['feels_like'] : null;
        $wind = is_numeric($current['wind_speed'] ?? null) ? (float) $current['wind_speed'] : null;
        $rainingNow = $this->isRaining($conditionNow, $current['precipitation'] ?? null);
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
            'provider' => (string) ($w['provider'] ?? $w['source'] ?? 'Weather'),
            'condition' => $conditionNow,
            'icon' => $this->iconFor($conditionNow, ($current['is_day'] ?? true) !== false),
            'temperature' => (float) $current['temperature'],
            'feels_like' => $feels,
            'feels_level' => $feels !== null ? $this->kpi->heatLevel($feels) : null,
            'humidity' => $humidity,
            'wind' => $wind,
            'raining_now' => $rainingNow,
            'rain_today' => $todayRain,
            'rain_chance' => $todayChance,
            'rain_expected' => $rainExpected,
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
     * @return array<int, array{icon: string, title: string, reason: string, ask: string}>
     */
    private function actions(array $weather, ?array $risk, CarbonImmutable $now): array
    {
        $actions = [];
        $push = function (string $icon, string $key, array $params = []) use (&$actions): void {
            $actions[] = [
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
                $push('heat', 'heat', ['feels' => $weather['heat']['display'], 'level' => strtolower((string) $weather['heat']['label'])]);
            }
        }
        if ($risk && ($risk['state'] ?? null) === 'ok' && $risk['high']) {
            $push('map', 'risk', ['level' => strtolower($risk['level_label'])]);
        }
        if ($actions === []) {
            $push('check', $weather['state'] === 'ok' ? 'normal' : 'no_weather');
        }

        return array_slice($actions, 0, self::MAX_ACTIONS);
    }

    // ------------------------------------------------------------------ weather this week

    /**
     * Seven day cards and the two small charts' data. A day is marked good for farm work with the same rule the
     * "good days" number uses (see FarmerKpiService::goodWorkDays).
     *
     * @return array<string, mixed>
     */
    private function week(array $weather, CarbonImmutable $now): array
    {
        if ($weather['state'] !== 'ok' || $weather['days'] === []) {
            return ['state' => $weather['state'] === 'ok' ? 'empty' : 'error', 'days' => []];
        }

        $good = $this->goodDays($weather['days'], $now);
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
        }, $weather['days']);

        $chart = fn (callable $pick) => array_map($pick, $days);

        return [
            'state' => 'ok',
            'days' => $days,
            'good' => $good,
            'takeaway' => $this->weekTakeaway($days),
            'temp_takeaway' => $this->tempTakeaway($days),
            'rain_chart' => [
                'labels' => $chart(fn ($d) => $d['date']->format('D j')),
                'rain' => $chart(fn ($d) => $d['rain']),
                'heavy_mm' => (float) config('farmer_dashboard.heavy_rain_mm'),
            ],
            'temp_chart' => [
                'labels' => $chart(fn ($d) => $d['date']->format('D j')),
                'high' => $chart(fn ($d) => $d['high']),
                'low' => $chart(fn ($d) => $d['low']),
            ],
        ];
    }

    /** @return array{key: string, value: ?int, display: string, unit: string, reason: ?string, dates: array<int, string>, judged: int} */
    private function goodDays(array $days, CarbonImmutable $now): array
    {
        return $this->kpi->goodWorkDays(
            array_map(fn (array $d) => ['date' => $d['date'], 'chance' => $d['chance'], 'wind_kmh' => $d['wind']], $days),
            $now,
        );
    }

    /** One sentence above the temperature chart: the hottest day. */
    private function tempTakeaway(array $days): ?string
    {
        $withHigh = array_values(array_filter($days, fn ($d) => $d['high'] !== null));
        if ($withHigh === []) {
            return null;
        }
        usort($withHigh, fn ($a, $b) => $b['high'] <=> $a['high']);

        return __('farmer.week.temp_takeaway', ['day' => $withHigh[0]['date']->format('l'), 'temp' => number_format($withHigh[0]['high'], 0)]);
    }

    /** One sentence above the weekly rain chart: the wettest day, or that no day is wet. */
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

    // ------------------------------------------------------------------ rain history + KPI cards

    /** @return array{daily: array<string, float>, fetched_at: ?CarbonImmutable} */
    private function rainHistory(bool $force): array
    {
        $history = $this->recentRain->history($force);

        return ['daily' => $history['daily'], 'fetched_at' => $history['fetched_at'] ? CarbonImmutable::instance($history['fetched_at'])->timezone(self::TIMEZONE) : null];
    }

    /**
     * The six numbers under "My farm at a glance". Each has a label, the number with its unit (or "—" and a reason),
     * what it means, a comparison, its source and update time, and the page it opens.
     *
     * @return array<int, array<string, mixed>>
     */
    private function kpiCards(array $weather, array $rain, array $alerts, CarbonImmutable $now): array
    {
        $weatherOk = $weather['state'] === 'ok';
        // Sources are named in plain words for the farmer (the provider's own name is logged and kept in the payload for IT).
        $weatherSource = __('farmer.source.weather');
        $weatherAt = $weatherOk ? $weather['updated_at'] : null;
        $rainSource = __('farmer.source.rain_records');
        $rainAt = $rain['fetched_at'];
        $weatherHref = route('weather-predictions.index', ['tab' => 'weather-forecast']);

        // 1. rain this past week (compared with the week before)
        $past = $this->kpi->rainLast7Days($rain['daily'], $now);
        $pastTrend = null;
        if ($past['value'] !== null && $past['previous'] !== null) {
            $dir = abs($past['value'] - $past['previous']) < 1 ? 'flat' : ($past['value'] > $past['previous'] ? 'up' : 'down');
            $pastTrend = ['dir' => $dir, 'text' => __('farmer.kpi.rain_past.trend_'.$dir, ['mm' => FarmerKpiService::formatRain($past['previous'])])];
        }

        // 2. rain coming (compared with the past week)
        $next = $this->kpi->rainNext7Days($weatherOk ? $weather['days'] : [], $now);
        $nextTrend = null;
        if ($next['value'] !== null && $past['value'] !== null) {
            $dir = abs($next['value'] - $past['value']) < 1 ? 'flat' : ($next['value'] > $past['value'] ? 'up' : 'down');
            $nextTrend = ['dir' => $dir, 'text' => __('farmer.kpi.rain_next.trend_'.$dir, ['mm' => FarmerKpiService::formatRain($past['value'])])];
        }

        // 3. rain compared with normal (this month so far)
        $normalMonthly = null;
        try {
            $normalMonthly = $this->rainAnalytics->monthlyNormal($now->month);
        } catch (Throwable $e) {
            Log::warning('Farmer dashboard long-term rain average failed.', ['source' => 'Ambulong monthly dataset', 'message' => $e->getMessage()]);
        }
        $vs = $this->kpi->rainComparedWithNormal($rain['daily'], $normalMonthly, $now);
        $vsTrend = $vs['value'] === null ? null : [
            'dir' => ['more' => 'up', 'less' => 'down', 'normal' => 'flat'][$vs['level']],
            'text' => __('farmer.kpi.rain_vs_normal.detail', ['mtd' => FarmerKpiService::formatRain($vs['month_to_date']), 'normal' => FarmerKpiService::formatRain($vs['normal_to_date']), 'days' => $vs['days']]),
        ];

        // 4. heat today
        $heat = $weather['heat'] ?? $this->kpi->heatToday(null, null, null);

        // 5. good work days
        $good = $weatherOk ? $this->goodDays($weather['days'], $now) : $this->kpi->goodWorkDays([], $now);

        // 6. alerts
        $alertKpi = $this->kpi->activeAlerts(count($alerts));

        $card = fn (array $kpi, array $x): array => $x + [
            'key' => $kpi['key'],
            'display' => $kpi['display'],
            'unit' => $kpi['unit'],
            'reason' => $kpi['reason'],
            'missing' => $kpi['value'] === null,
            'trend' => null,
            'label' => __('farmer.kpi.'.$kpi['key'].'.label'),
        ];

        return [
            $card($past, [
                'meaning' => $past['level'] ? __('farmer.kpi.rain_past.meaning_'.$past['level']) : null,
                'status' => $past['level'] === 'heavy' ? 'watch' : 'info',
                'trend' => $pastTrend,
                'source' => $rainSource, 'updated' => $this->stamp($rainAt, $now), 'href' => route('weather-predictions.index', ['tab' => 'weather-forecast']),
            ]),
            $card($next, [
                'label' => $next['days'] > 0 && $next['days'] < 7 ? __('farmer.kpi.rain_next.label_days', ['days' => $next['days']]) : __('farmer.kpi.rain_next.label'),
                'meaning' => $next['level'] ? __('farmer.kpi.rain_next.meaning_'.$next['level']) : null,
                'status' => $next['level'] === 'heavy' ? 'watch' : 'info',
                'trend' => $nextTrend,
                'source' => $weatherSource, 'updated' => $this->stamp($weatherAt, $now), 'href' => $weatherHref,
            ]),
            $card($vs, [
                'meaning' => $vs['label'],
                'status' => $vs['level'] === null ? 'info' : ($vs['level'] === 'normal' ? 'good' : 'watch'),
                'trend' => $vsTrend,
                'source' => __('farmer.source.rain_vs_normal'), 'updated' => $this->stamp($rainAt, $now), 'href' => route('weather-predictions.index', ['tab' => 'weather-forecast']),
            ]),
            $card($heat, [
                'meaning' => $heat['value'] !== null ? __('farmer.kpi.heat.meaning_'.$heat['level']) : null,
                'status' => match ($heat['level']) {
                    'normal' => 'good', 'caution', 'extreme_caution' => 'watch', 'danger', 'extreme_danger' => 'danger', default => 'info',
                },
                'headline' => $heat['value'] !== null ? __('farmer.kpi.heat.headline', ['level' => $heat['label']]) : null,
                'source' => $heat['basis'] === 'calculated' ? __('farmer.source.weather_calculated') : $weatherSource, 'updated' => $this->stamp($weatherAt, $now), 'href' => $weatherHref,
            ]),
            $card($good, [
                'meaning' => $good['value'] !== null ? __('farmer.kpi.work_days.meaning', ['rain' => (int) config('farmer_dashboard.good_work_day.max_rain_chance_percent'), 'wind' => (int) config('farmer_dashboard.good_work_day.max_wind_kmh')]) : null,
                'status' => $good['value'] === null ? 'info' : ($good['value'] === 0 ? 'watch' : 'good'),
                'trend' => $good['value'] !== null ? ['dir' => 'flat', 'text' => __('farmer.kpi.work_days.of', ['judged' => $good['judged']])] : null,
                'source' => $weatherSource, 'updated' => $this->stamp($weatherAt, $now), 'href' => route('calendar.index'),
            ]),
            $card($alertKpi, [
                'meaning' => $alertKpi['value'] === 0 ? __('farmer.kpi.alerts.none') : __('farmer.kpi.alerts.some'),
                'status' => $alertKpi['value'] === 0 ? 'good' : (($alerts[0]['severity'] ?? '') === 'watch' ? 'watch' : 'danger'),
                'source' => __('farmer.kpi.alerts.source'), 'updated' => $this->stamp($now, $now), 'href' => route('notifications.index'),
            ]),
        ];
    }

    // ------------------------------------------------------------------ farm risk (barangay)

    /**
     * @return array<string, mixed> state: ok | error | nodata | nobarangay
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

        $result = [
            'state' => 'ok',
            'level' => $level,
            'level_label' => __('farmer.risk.level.'.strtolower($level)),
            'high' => in_array($level, ['High', 'Severe'], true),
            'meaning' => __('farmer.risk.meaning.'.strtolower($level)),
            'concern' => $concern && \Illuminate\Support\Facades\Lang::has('farmer.risk.concern.'.strtolower($concern)) ? __('farmer.risk.concern.'.strtolower($concern)) : null,
            'planting' => $r['advisory']['planting'] ?? null,
            'irrigation' => $r['advisory']['irrigation'] ?? null,
            'warnings' => (array) ($r['advisory']['warnings'] ?? []),
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

    // ------------------------------------------------------------------ expected harvest

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
     * The Expected Harvest card. Production (tons) is shown only when the area comes from the farmer's own profile: the
     * model's fallback area is a documented default, so it would present a guess as this farm's harvest. The range
     * comes from the model's own uncertainty and is left out when the model gives none.
     *
     * @param  array{state: string, result: ?array<string, mixed>}  $prediction
     * @param  Collection<int, FarmerPlantingSchedule>  $rows
     * @return array<string, mixed>  state: ok | loading | error (error carries a reason and what is missing)
     */
    private function harvest(array $prediction, $profile, Collection $rows): array
    {
        $missing = array_values(array_filter([
            $rows->reject(fn (FarmerPlantingSchedule $r) => $r->monitoringStatus() === 'harvested')->isEmpty() ? 'planting' : null,
            (float) ($profile?->farm_area ?? 0) > 0 ? null : 'area',
        ]));

        if ($prediction['state'] !== 'ok') {
            return [
                'state' => $prediction['state'],
                'missing' => $missing,
                // What the farmer can fix comes first; otherwise say honestly whether the records or the service is the cause.
                'reason' => match (true) {
                    $missing !== [] => __('farmer.harvest.reason_missing_'.implode('_', $missing)),
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
        try {
            $latest = $this->yields->years()->first();
            $rows = $latest ? $this->yields->rows()->where('year', '<=', (int) $latest) : collect();
        } catch (Throwable $e) {
            Log::warning('Farmer dashboard yield history failed.', ['source' => 'project yield dataset', 'message' => $e->getMessage()]);

            return null;
        }

        // Compare like with like: the prediction is for one calendar month, so use the average of that same month in past
        // years. Only when that month has no records does it fall back to the all-months average (and says so).
        $targetMonth = $prediction['month'] ?? null;
        $sameMonth = $targetMonth ? $rows->where('month', $targetMonth) : collect();
        $sameMonthBasis = $sameMonth->isNotEmpty();
        $basis = $sameMonthBasis ? $sameMonth : $rows;
        $mean = $basis->isEmpty() ? null : (float) $basis->avg('yield');
        if (! $mean || $mean <= 0) {
            return null;
        }

        return [
            'historical' => round($mean, 2),
            'from' => $basis->min('year'),
            'to' => $basis->max('year'),
            'same_month' => $sameMonthBasis,
            'month_name' => $sameMonthBasis ? CarbonImmutable::create(2000, (int) $targetMonth, 1)->format('F') : null,
            'own' => $this->ownHarvests($scheduleRows, (float) $prediction['yield']),
            'predicted' => round($prediction['yield'], 2),
            'difference' => round($prediction['yield'] - $mean, 2),
            'percent' => round(($prediction['yield'] - $mean) / $mean * 100),
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

    // ------------------------------------------------------------------ monthly rain chart

    /**
     * Ambulong monthly rain for the latest year on record next to the long-term average, and one sentence about the
     * latest recorded month. State: ok | error | empty.
     *
     * @return array<string, mixed>
     */
    private function rainSection(CarbonImmutable $now): array
    {
        try {
            $monthly = $this->rainAnalytics->monthly($now);
        } catch (Throwable $e) {
            Log::warning('Farmer dashboard monthly rain failed.', ['source' => 'Ambulong monthly dataset', 'exception' => $e::class, 'message' => $e->getMessage()]);

            return ['state' => 'error'];
        }
        if ($monthly === null) {
            return ['state' => 'empty'];
        }

        $latest = $monthly['latest'];
        $month = $latest ? CarbonImmutable::create($monthly['year'], $latest['month'], 1)->format('F Y') : null;
        $summary = null;
        if ($latest) {
            $summary = $latest['percent'] === null
                ? __('farmer.rain.summary_no_normal', ['month' => $month, 'mm' => FarmerKpiService::formatRain($latest['rain'])])
                : __('farmer.rain.summary_'.$latest['level'], ['month' => $month, 'mm' => FarmerKpiService::formatRain($latest['rain']), 'percent' => FarmerKpiService::formatPercent(abs($latest['percent']))]);
        }

        return ['state' => 'ok', 'summary' => $summary, 'older' => ! $monthly['is_current_year']] + $monthly;
    }

    // ------------------------------------------------------------------ advisories + notifications

    /** Only published, in-effect advisories for the farmer's area: never drafts, pending, expired or archived. @return array<int, array<string, mixed>> */
    private function advisories(?string $barangay): array
    {
        try {
            $rows = SampleData::without(PlantingAdvisory::query(), 'planting_advisories')->active()->forBarangay($barangay)->latest('valid_from')->latest('id')->take(3)->get();
        } catch (Throwable $e) {
            Log::warning('Farmer dashboard advisories failed.', ['source' => 'planting advisories', 'exception' => $e::class]);

            return [];
        }

        return $rows->map(function (PlantingAdvisory $a): array {
            $when = ($a->valid_from ?? $a->published_at ?? $a->created_at);

            return [
                'id' => $a->id,
                'title' => (string) $a->title,
                'summary' => (string) str($a->displaySummary() ?: $a->content)->squish()->limit(130),
                'when' => $when ? CarbonImmutable::instance($when)->timezone(self::TIMEZONE) : null,
                'href' => route('planting-advisories.show', $a),
            ];
        })->all();
    }

    private function unread(User $user): int
    {
        try {
            return SampleData::without(UserNotification::query(), 'notifications')->where('user_id', $user->id)->where('is_read', false)->count();
        } catch (Throwable) {
            return 0;
        }
    }

    // ------------------------------------------------------------------ alerts (real warnings, never hidden)

    /**
     * Each alert has a `level` (danger = red, watch = amber) that the page shows with an icon AND a text label.
     *
     * @return array<int, array{severity: string, level: string, title: string, message: string, href: string, icon: string}>
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
                $alerts[] = ['severity' => 'warning', 'icon' => 'alert', 'title' => (string) $n->title, 'message' => (string) str($n->message)->squish()->limit(140), 'href' => route('notifications.index')];
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
                $when = $ahead === 0 ? __('farmer.alerts.today') : ($ahead === 1 ? __('farmer.alerts.tomorrow') : __('farmer.alerts.on_day', ['day' => $day['date']->format('l')]));
                if ($day['heavy'] && ! isset($seen['rain'])) {
                    $seen['rain'] = true;
                    $alerts[] = ['severity' => 'warning', 'icon' => 'rain', 'title' => __('farmer.alerts.heavy_rain.title', ['when' => $when]),
                        'message' => __('farmer.alerts.heavy_rain.message', ['mm' => FarmerKpiService::formatRain($day['rain'])]), 'href' => $forecastHref];
                }
                if ($day['strong_wind'] && ! isset($seen['wind'])) {
                    $seen['wind'] = true;
                    $alerts[] = ['severity' => 'watch', 'icon' => 'wind', 'title' => __('farmer.alerts.strong_wind.title', ['when' => $when]),
                        'message' => __('farmer.alerts.strong_wind.message', ['kmh' => number_format($day['wind'], 0)]), 'href' => $forecastHref];
                }
                if ($day['high_heat'] && ! isset($seen['heat'])) {
                    $seen['heat'] = true;
                    $alerts[] = ['severity' => 'watch', 'icon' => 'heat', 'title' => __('farmer.alerts.high_heat.title', ['when' => $when]),
                        'message' => __('farmer.alerts.high_heat.message', ['temp' => number_format($day['high'], 0)]), 'href' => $forecastHref];
                }
            }
        }

        if ($risk && ($risk['state'] ?? null) === 'ok' && $risk['high']) {
            $alerts[] = ['severity' => $risk['level'] === 'Severe' ? 'critical' : 'warning', 'icon' => 'map',
                'title' => __('farmer.alerts.risk.title', ['level' => $risk['level_label']]), 'message' => $this->planningLine($risk), 'href' => '#farmRiskMap'];
        }

        if ($item = $this->typhoonAlert($user)) {
            $alerts[] = $item;
        }

        $rank = ['critical' => 0, 'warning' => 1, 'watch' => 2];
        usort($alerts, fn ($a, $b) => ($rank[$a['severity']] ?? 3) <=> ($rank[$b['severity']] ?? 3));

        return array_map(fn (array $a) => $a + ['level' => $a['severity'] === 'watch' ? 'watch' : 'danger'], $alerts);
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

        return $answered ? null : ['severity' => 'critical', 'icon' => 'storm', 'title' => __('farmer.alerts.typhoon.title'),
            'message' => __('farmer.alerts.typhoon.message'), 'href' => route('planting-advisories.index')];
    }

    // ------------------------------------------------------------------ planting schedule + crop season

    /**
     * All of the farmer's schedules (one per plot), read once for the crop season, the harvest card and the setup list.
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
     * The crop calendar for every open plot, with reminders and weather advice. Null with no open schedule.
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

    /**
     * "My Crop Season": for each open plot the six crop stages, "Day 45 of about 115", the expected harvest dates and the
     * next task. With no open schedule the state is `empty`: one card with one button (the only place the farmer is asked
     * to add a planting date).
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
            $next = $planPlot['next'] ?? null;

            $plots[] = [
                'edit_id' => $row->id,
                'label' => $planPlot['label'] ?? __('farmer.season.plot', ['n' => count($plots) + 1]),
                'crop' => $crop,
                'harvest_from' => $expected->subDays($window),
                'harvest_to' => $expected->addDays(FarmerCropPlanService::HARVEST_DAYS_AFTER),
                'next' => $next ? ['label' => $next['label'], 'start' => $next['start']] : null,
                'reminders' => collect($plan['reminders'] ?? [])->where('plot', $planPlot['label'] ?? null)->values()->all(),
                'advice' => collect($plan['advice'] ?? [])->where('plot', $planPlot['label'] ?? null)->values()->all(),
            ];
        }

        return ['state' => 'ok', 'plots' => $plots, 'more' => max(0, $open->count() - count($plots))];
    }

    // ------------------------------------------------------------------ my farm at a glance

    /**
     * The farmer's own farm in four answers: how big it is, whether a crop is planted, the weather now and whether
     * anything needs attention. A null value means "not saved yet" or "not available yet" (the card then says what is
     * needed); nothing is made up. The weather is the same Lian reading every other card uses and is labelled as Lian.
     *
     * @param  Collection<int, FarmerPlantingSchedule>  $rows
     * @param  array<string, mixed>  $season
     * @param  array<string, mixed>  $risk
     * @param  array<string, mixed>  $weather
     * @param  array<int, array<string, mixed>>  $alerts
     * @return array<string, mixed>
     */
    private function farm($profile, Collection $rows, array $season, array $risk, array $weather, array $alerts): array
    {
        // The barangay of the mapped farm, read from the map when it was saved (not the farmer's home barangay).
        $farmBarangay = null;
        try {
            $boundary = $profile?->farmBoundary;
            if ($boundary && (float) $boundary->calculated_area_hectares > 0) {
                $farmBarangay = $boundary->getAttribute('barangay') ?: LianGeography::locate($boundary->boundary_coordinates ?? [])['barangay'];
            }
        } catch (Throwable) {
            // an unreadable boundary counts as not mapped
        }

        $area = (float) ($profile?->farm_area ?? 0);
        $plot = $season['plots'][0] ?? null;
        $row = $plot ? $rows->firstWhere('id', $plot['edit_id']) : null;
        $planted = ($plot['crop']['value'] ?? null) !== null;           // null until the planting day has come

        return [
            'size' => $area > 0
                ? ['value' => number_format($area, 2).' ha', 'note' => $farmBarangay
                    ? __('farmer.farm.size.note_mapped', ['barangay' => LianGeography::barangayLabel($farmBarangay)])
                    : __('farmer.farm.size.note')]
                : ['value' => null],
            'planting' => $row?->planting_date
                ? ['value' => __('farmer.farm.planting.'.($planted ? 'active' : 'upcoming')),
                    'note' => __('farmer.farm.planting.'.($planted ? 'note' : 'note_upcoming'), ['date' => $row->planting_date->format('F j, Y')])]
                : ['value' => null],
            'weather' => $weather['state'] === 'ok'
                ? ['value' => FarmerKpiService::formatTemperature($weather['temperature']).'°C', 'detail' => $weather['condition'], 'note' => __('farmer.farm.weather.note')]
                : ['value' => null],
            'condition' => $this->condition($weather, $risk, $alerts),
        ];
    }

    /**
     * "Farm Condition": Good, Needs Attention or High Weather Risk. It adds nothing of its own: it is the most serious of
     * the warnings shown under "What You Should Know Today" and the barangay risk rating, so the card can never disagree
     * with them. With neither weather nor a rating there is no condition to report (never "Good" by default).
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
            $top !== null => (string) str(rtrim((string) $top['title'], '.').'. '.$top['message'])->squish()->limit(150),
            $rainToday === 'heavy' => __('farmer.farm.condition.note_heavy'),
            $status !== 'good' => (string) $risk['meaning'],
            $rainToday !== null => __('farmer.farm.condition.note_'.$rainToday),
            default => __('farmer.farm.condition.note_default'),
        };

        return ['value' => __('farmer.farm.condition.'.$status), 'status' => $status, 'note' => $note];
    }

    // ------------------------------------------------------------------ quick actions

    /**
     * What the three Quick Actions cards say: "Add" until the farmer has saved that thing, then "Update".
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

    // ------------------------------------------------------------------ setup list

    /**
     * What is missing from the farmer's profile and what filling it in would unlock. Each item is one real gap.
     * The planting date is not listed here: the Crop Season card is where the farmer is asked for it, once.
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
            'benefit' => __('farmer.setup.'.$c['key'].'.benefit'),
            'cta' => __('farmer.setup.'.$c['key'].'.cta'),
        ], $items);

        return ['items' => $items, 'done' => count($checks) - count($items), 'total' => count($checks)];
    }
}
