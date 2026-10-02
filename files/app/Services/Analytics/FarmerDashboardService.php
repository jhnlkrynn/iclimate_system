<?php

namespace App\Services\Analytics;

use App\Enums\PredictionType;
use App\Models\FarmerPlantingSchedule;
use App\Models\Notification as UserNotification;
use App\Models\PlantingAdvisory;
use App\Models\TyphoonSafetyResponse;
use App\Models\User;
use App\Services\Prediction\RiceYieldPredictionService;
use App\Services\Risk\BarangayRiskMapService;
use App\Services\TyphoonSafetyService;
use App\Services\Weather\FarmerDashboardWeatherService;
use App\Services\WeatherApiService;
use App\Support\DashboardData;
use App\Support\ProjectYieldDataset;
use App\Support\SampleData;
use App\Support\SchemaCache;
use App\Support\WeatherConditionLabel;
use Illuminate\Support\Collection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Everything the Farmer dashboard shows, from ONE place, in plain language.
 *
 * Rules that shape it:
 *   - every number is queried or computed here from a real source, never typed in;
 *   - a component without real data is left out (or, where the farmer can act, becomes a clear call to action);
 *   - technical failures never reach the farmer: they are logged for IT and the component is simply omitted;
 *   - genuine warnings (heavy rain, strong wind, heat, high farm risk, weather alerts, an open typhoon check-in)
 *     are never hidden;
 *   - Lian-wide values are labelled as Lian, never as "your farm"; predictions are labelled as estimates;
 *   - t/ha (yield) and tons (production) are never mixed up: production is shown only from the farmer's own farm area.
 */
final class FarmerDashboardService
{
    public const TIMEZONE = 'Asia/Manila';

    /** Same "High" thresholds the MAO overview and the weather calendar use. */
    public const HEAVY_RAIN_MM = MaoDashboardService::HEAVY_RAIN_MM;

    public const STRONG_WIND_KMH = MaoDashboardService::STRONG_WIND_KMH;

    public const VERY_HOT_C = MaoDashboardService::VERY_HOT_C;

    /** Wind is shown on a forecast day only when it is worth a farmer's attention. */
    private const RELEVANT_WIND_KMH = 20.0;

    /** How many forecast days ahead a warning looks. */
    private const WARNING_LOOKAHEAD_DAYS = 2;

    public function __construct(
        private readonly FarmerDashboardWeatherService $weather,
        private readonly WeatherApiService $weatherApi,
        private readonly BarangayRiskMapService $risk,
        private readonly RiceYieldPredictionService $riceYield,
        private readonly TyphoonSafetyService $typhoon,
        private readonly DashboardOverviewService $overview,
        private readonly ProjectYieldDataset $yields,
        private readonly FarmerCropPlanService $cropPlan,
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

    /** @return array<string, mixed> */
    public function payload(User $user, bool $forceWeather = false): array
    {
        $now = CarbonImmutable::now(self::TIMEZONE);
        $profile = $user->farmerProfile;
        $barangay = $profile?->barangay ?? $user->barangay;

        $weather = $this->weather($forceWeather);
        $risk = $this->riskStatus($barangay);
        $prediction = $this->prediction($user, $profile);
        $advisories = $this->advisories($barangay);
        $rows = $this->scheduleRows($user);
        $schedule = $this->schedule($rows);
        $alerts = $this->alerts($user, $weather, $risk);
        $rainfall = $this->rainfall();

        return [
            'generated_at' => $now,
            'greeting' => $this->greeting($now).', '.$this->displayName($user),
            'name' => $this->displayName($user),
            'barangay' => $barangay,
            'weather' => $weather,
            'alerts' => $alerts,
            'risk' => $risk,
            'prediction' => $prediction,
            'schedule' => $schedule,
            'farm' => $this->farm($profile, $barangay, $schedule),
            'advisories' => $advisories,
            'action' => $this->action($weather, $risk, $advisories),
            'outlook' => $this->yieldOutlook($prediction, $rows),
            'plan' => $this->plan($rows, $weather),
            'setup' => $this->setup($profile, $barangay, $rows),
            'rainfall' => $rainfall,
            'kpis' => $this->kpis($prediction, $risk, $advisories, $alerts, $schedule),
            'unread' => $this->unread($user),
            'unavailable' => $this->unavailable($weather, $risk, $prediction, $barangay, $rainfall),
        ];
    }

    /**
     * Plain names of the parts of the dashboard that could not be loaded, so the farmer is told rather than left with a
     * silently shorter page. A farmer with no barangay on file is not told about the risk card: that is a profile gap.
     *
     * @return array<int, string>
     */
    private function unavailable(array $weather, ?array $risk, ?array $prediction, ?string $barangay, ?array $rainfall): array
    {
        return array_values(array_filter([
            $weather['state'] !== 'ok' ? 'live weather' : null,
            $barangay && ! $risk ? 'farm risk for your barangay' : null,
            ! $prediction ? 'expected rice yield' : null,
            ! $rainfall ? 'the rainfall chart' : null,
        ]));
    }

    private function greeting(CarbonImmutable $now): string
    {
        return match (true) {
            $now->hour < 12 => 'Good morning',
            $now->hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };
    }

    /** Given names only: a three-part name drops the last word (John Luke Reynon becomes John Luke). */
    private function displayName(User $user): string
    {
        $parts = preg_split('/\s+/', trim((string) $user->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return match (true) {
            count($parts) >= 3 => implode(' ', array_slice($parts, 0, -1)),
            count($parts) === 2 => $parts[0],
            default => $parts[0] ?? 'Farmer',
        };
    }

    // ------------------------------------------------------------------ weather (live, Lian-wide)

    /** @return array<string, mixed> */
    private function weather(bool $force): array
    {
        try {
            $w = $this->weather->current($force);
        } catch (Throwable $e) {
            Log::warning('Farmer dashboard weather failed.', ['exception' => $e::class]);

            return ['state' => 'error'];
        }

        $current = is_array($w['current'] ?? null) ? $w['current'] : [];
        if (! ($w['success'] ?? false) || ! is_numeric($current['temperature'] ?? null)) {
            return ['state' => 'error'];
        }
        $today = is_array($w['today'] ?? null) ? $w['today'] : [];

        // Wind by date comes from an optional second lookup: if it fails the forecast simply shows no wind.
        try {
            $series = (array) data_get($this->weatherApi->forecast(), 'daily_series', []);
        } catch (Throwable) {
            $series = [];
        }
        $windByDate = [];
        foreach ((array) ($series['labels'] ?? []) as $i => $date) {
            if (is_numeric($series['windSpeed'][$i] ?? null)) {
                $windByDate[$date] = (float) $series['windSpeed'][$i];
            }
        }

        $days = [];
        foreach ((array) ($w['forecast'] ?? []) as $day) {
            if (! is_array($day) || empty($day['date'])) {
                continue;
            }
            $rain = is_numeric($day['rainfall'] ?? null) ? (float) $day['rainfall'] : null;
            $high = is_numeric($day['temperature_max'] ?? null) ? (float) $day['temperature_max'] : null;
            $wind = $windByDate[$day['date']] ?? null;
            $flags = array_values(array_filter([
                $rain !== null && $rain >= self::HEAVY_RAIN_MM ? 'Heavy rain' : null,
                $wind !== null && $wind >= self::STRONG_WIND_KMH ? 'Strong wind' : null,
                $high !== null && $high >= self::VERY_HOT_C ? 'High heat' : null,
            ]));
            $days[] = [
                'date' => CarbonImmutable::parse($day['date'], self::TIMEZONE),
                'condition' => WeatherConditionLabel::display($day['condition'] ?? null),
                'icon' => $day['icon'] ?? null,
                'high' => $high,
                'low' => is_numeric($day['temperature_min'] ?? null) ? (float) $day['temperature_min'] : null,
                'chance' => is_numeric($day['precipitation_probability'] ?? null) ? (float) $day['precipitation_probability'] : null,
                'rain' => $rain,
                'wind' => $wind !== null && $wind >= self::RELEVANT_WIND_KMH ? $wind : null,
                'flags' => $flags,
            ];
        }

        $updated = data_get($w, 'timestamps.provider_updated_at') ?? data_get($w, 'timestamps.server_fetched_at') ?? ($w['fetched_at'] ?? null);

        return [
            'state' => 'ok',
            'condition' => (string) (WeatherConditionLabel::display($current['condition'] ?? null) ?: 'Weather'),
            'icon' => $w['icon'] ?? ($current['icon'] ?? null),
            'temperature' => (float) $current['temperature'],
            'feels_like' => is_numeric($current['feels_like'] ?? null) ? (float) $current['feels_like'] : null,
            'humidity' => is_numeric($current['humidity'] ?? null) ? (float) $current['humidity'] : null,
            'rain_today' => is_numeric($today['rainfall'] ?? null) ? (float) $today['rainfall'] : null,        // forecast total for today
            'wind' => is_numeric($current['wind_speed'] ?? null) ? (float) $current['wind_speed'] : null,
            'updated_at' => $updated ? CarbonImmutable::parse($updated)->timezone(self::TIMEZONE) : null,
            'stale' => (bool) ($w['stale'] ?? false),
            'guidance' => $this->weather->guidance($w),
            'days' => $days,
        ];
    }

    // ------------------------------------------------------------------ farm risk (barangay)

    /** @return array<string, mixed>|null */
    private function riskStatus(?string $barangay): ?array
    {
        if (! $barangay) {
            return null;
        }
        try {
            $r = $this->risk->detailFor($barangay);
        } catch (Throwable $e) {
            Log::warning('Farmer dashboard farm risk failed.', ['exception' => $e::class]);

            return null;
        }
        $level = $r['risk_level'] ?? null;
        if (! $r || ! $level || $level === 'No Data') {
            return null;
        }

        return [
            'level' => $level,
            'high' => in_array($level, ['High', 'Severe'], true),
            'meaning' => match ($level) {
                'High', 'Severe' => 'The weather in your barangay suggests a higher chance of crop trouble.',
                'Moderate' => 'Some conditions in your barangay need watching.',
                default => 'Conditions in your barangay look manageable for now.',
            },
            'planting' => $r['advisory']['planting'] ?? null,
            'irrigation' => $r['advisory']['irrigation'] ?? null,
            'warnings' => (array) ($r['advisory']['warnings'] ?? []),
            'updated_at' => ! empty($r['updated_at']) ? CarbonImmutable::parse($r['updated_at'])->timezone(self::TIMEZONE) : null,
        ];
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

    // ------------------------------------------------------------------ predicted yield

    /**
     * A prediction is shown only when it is valid. Production (tons) is shown only when the area comes from the farmer's
     * own profile: the model's fallback area is a documented default, so it would present a guess as this farm's harvest.
     *
     * @return array<string, mixed>|null
     */
    private function prediction(User $user, $profile): ?array
    {
        $farmType = $profile?->farm_type ?: \App\Models\FarmerProfile::FARM_TYPE_RAINFED;

        $result = Cache::remember(self::predictionCacheKey($user->id), now()->addHours(6), function () use ($user, $farmType) {
            try {
                $prediction = $this->riceYield->predictForUser(\App\Support\BusinessClock::now()->toImmutable(), $farmType, null, $user, [], PredictionType::RICE_YIELD->value);

                // A failed run is not cached (null is never stored), so the next load retries the model.
                return ($prediction['success'] ?? false) ? $prediction : null;
            } catch (Throwable $e) {
                // Logged for IT; the farmer just does not see this card. Cache::remember does not keep a null result.
                Log::warning('Farmer dashboard yield prediction failed.', ['user_id' => $user->id, 'exception' => $e::class]);

                return null;
            }
        });

        $yield = is_array($result) ? ($result['yield_tons_per_hectare'] ?? $result['predicted_yield'] ?? null) : null;
        if (! is_numeric($yield) || (float) $yield <= 0) {
            return null;
        }

        $profileArea = $profile?->farm_area;
        $usesOwnArea = ($result['input_sources']['area']['source'] ?? null) === 'farmer_profile' && is_numeric($profileArea) && (float) $profileArea > 0;
        $month = ! empty($result['target_month']) ? CarbonImmutable::parse($result['target_month'].'-01') : null;

        return [
            'yield' => (float) $yield,                                  // tons per hectare
            'period' => $month?->format('F Y'),
            'month' => $month?->month,
            'area' => $usesOwnArea ? (float) $profileArea : null,
            'production' => $usesOwnArea ? round((float) $yield * (float) $profileArea, 2) : null,   // tons = t/ha x hectares
            'reliability' => \App\Support\PredictionWording::reliability($result['api_confidence'] ?? $result['confidence'] ?? null),
        ];
    }

    /**
     * Lian's historical average next to the farmer's own predicted yield, labelled as two different scopes. Only when
     * both exist; the historical figure is never presented as the farmer's own history.
     *
     * @return array<string, mixed>|null
     */
    private function yieldOutlook(?array $prediction, Collection $scheduleRows): ?array
    {
        if (! $prediction) {
            return null;
        }
        try {
            $latest = $this->yields->years()->first();
            $rows = $latest ? $this->yields->rows()->where('year', '<=', (int) $latest) : collect();
        } catch (Throwable) {
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
            'months' => $basis->count(),
            'from' => $basis->min('year'),
            'to' => $basis->max('year'),
            'same_month' => $sameMonthBasis,
            'month_name' => $sameMonthBasis ? CarbonImmutable::create(2000, (int) $targetMonth, 1)->format('F') : null,
            'own' => $this->ownHarvests($scheduleRows, (float) $prediction['yield']),
            'history' => $this->yieldHistory($rows, round($prediction['yield'], 2)),
            'predicted' => round($prediction['yield'], 2),
            'difference' => round($prediction['yield'] - $mean, 2),
            'period' => $prediction['period'],
            'max' => max($mean, $prediction['yield']),
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

    /**
     * Lian's average yield for each of the latest years on record (the mean of the months recorded that year), for the small
     * chart under the outlook. The farmer's estimate rides along as a reference line: it is a different scope and is labelled so.
     *
     * @param  \Illuminate\Support\Collection<int, array{year: int, month: int, yield: float}>  $rows
     * @return array{labels: array<int, string>, values: array<int, float>, estimate: float}
     */
    private function yieldHistory($rows, float $estimate): array
    {
        $years = $rows->groupBy('year')->map(fn ($r) => round((float) $r->avg('yield'), 2))->sortKeys()->take(-6);

        return ['labels' => $years->keys()->map(fn ($y) => (string) $y)->values()->all(), 'values' => $years->values()->all(), 'estimate' => $estimate];
    }

    // ------------------------------------------------------------------ rainfall chart

    /** @return array<string, mixed>|null */
    private function rainfall(): ?array
    {
        try {
            return $this->overview->rainfallTrend();
        } catch (Throwable $e) {
            Log::warning('Farmer dashboard rainfall chart failed.', ['exception' => $e::class]);

            return null;
        }
    }

    // ------------------------------------------------------------------ advisories + notifications

    /** Only published, in-effect advisories for the farmer's area: never drafts, pending, expired or archived. @return array<int, array<string, mixed>> */
    private function advisories(?string $barangay): array
    {
        try {
            $rows = SampleData::without(PlantingAdvisory::query(), 'planting_advisories')->active()->forBarangay($barangay)->latest('valid_from')->latest('id')->take(3)->get();
        } catch (Throwable $e) {
            Log::warning('Farmer dashboard advisories failed.', ['exception' => $e::class]);

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
     * @return array<int, array{severity: string, title: string, message: string, href: string, icon: string}>
     */
    private function alerts(User $user, array $weather, ?array $risk): array
    {
        $alerts = [];

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
        } catch (Throwable) {
            // no alerts from notifications if they cannot be read
        }

        // Forecast warnings for today and the next couple of days.
        if ($weather['state'] === 'ok') {
            $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();
            $seen = [];
            foreach ($weather['days'] as $day) {
                $ahead = (int) $today->diffInDays($day['date']->startOfDay(), false);
                if ($ahead < 0 || $ahead > self::WARNING_LOOKAHEAD_DAYS) {
                    continue;
                }
                $when = $ahead === 0 ? 'today' : ($ahead === 1 ? 'tomorrow' : 'on '.$day['date']->format('l'));
                if ($day['rain'] !== null && $day['rain'] >= self::HEAVY_RAIN_MM && ! isset($seen['rain'])) {
                    $seen['rain'] = true;
                    $alerts[] = ['severity' => 'warning', 'icon' => 'rain', 'title' => 'Heavy rain expected '.$when,
                        'message' => number_format($day['rain'], 0).' mm of rain is forecast. Check field drainage and avoid spraying.', 'href' => route('weather-predictions.index', ['tab' => 'weather-forecast'])];
                }
                if ($day['wind'] !== null && $day['wind'] >= self::STRONG_WIND_KMH && ! isset($seen['wind'])) {
                    $seen['wind'] = true;
                    $alerts[] = ['severity' => 'watch', 'icon' => 'wind', 'title' => 'Strong wind expected '.$when,
                        'message' => 'Winds up to '.number_format($day['wind'], 0).' km/h are forecast. Avoid spraying.', 'href' => route('weather-predictions.index', ['tab' => 'weather-forecast'])];
                }
                if ($day['high'] !== null && $day['high'] >= self::VERY_HOT_C && ! isset($seen['heat'])) {
                    $seen['heat'] = true;
                    $alerts[] = ['severity' => 'watch', 'icon' => 'heat', 'title' => 'High heat expected '.$when,
                        'message' => 'Temperatures up to '.number_format($day['high'], 0).'°C are forecast. Check that your crop has enough water.', 'href' => route('weather-predictions.index', ['tab' => 'weather-forecast'])];
                }
            }
        }

        if ($risk && $risk['high']) {
            $alerts[] = ['severity' => $risk['level'] === 'Severe' ? 'critical' : 'warning', 'icon' => 'map',
                'title' => 'Farm risk in your barangay is '.$risk['level'], 'message' => $this->planningLine($risk), 'href' => '#farmRiskMap'];
        }

        if ($item = $this->typhoonAlert($user)) {
            $alerts[] = $item;
        }

        $rank = ['critical' => 0, 'warning' => 1, 'watch' => 2];
        usort($alerts, fn ($a, $b) => ($rank[$a['severity']] ?? 3) <=> ($rank[$b['severity']] ?? 3));

        return $alerts;
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

        return $answered ? null : ['severity' => 'critical', 'icon' => 'storm', 'title' => 'Typhoon safety check-in is open',
            'message' => 'Let the Municipal Agriculture Office know if you are safe.', 'href' => route('planting-advisories.index')];
    }

    // ------------------------------------------------------------------ what you should do today

    /**
     * Built only from defined rules: the weather guidance rules, the barangay risk advice, and MAO-published advisories.
     * Nothing here is generated.
     *
     * @return array<string, mixed>|null
     */
    private function action(array $weather, ?array $risk, array $advisories): ?array
    {
        $topic = $weather['state'] === 'ok' ? ($weather['guidance']['topic'] ?? 'normal') : null;
        $titles = ['humidity' => 'Very moist air', 'rain' => 'Rain expected today', 'wind' => 'Windy weather', 'heat' => 'Hot and dry weather', 'normal' => 'Normal weather'];
        // The same advice as the weather service gives, in plainer words for the farmer.
        $plain = [
            'humidity' => 'The air is very moist, so rice diseases can spread. Look at your plants often for spots or mold.',
            'rain' => 'Rain is likely today. If you can, wait before you put on fertilizer or spray.',
            'wind' => 'Strong wind makes spraying work less well. If you can, wait before you spray.',
            'heat' => 'It is hot and dry, so your rice will need more water. Check the soil before you water.',
            'normal' => 'The weather is normal. Check the advisories before you work in the field.',
        ];

        $lines = [];
        if ($topic) {
            $lines[] = $plain[$topic] ?? (string) $weather['guidance']['message'];
        }
        if ($risk && $risk['high'] && $risk['planting']) {
            $lines[] = (string) $risk['planting'];
        }
        $lead = $advisories[0] ?? null;
        if (! $topic && ! $lines && ! $lead) {
            return null;
        }

        return [
            'topic' => $topic ?? 'advisory',
            'title' => $topic ? $titles[$topic] : 'Latest advisory for your area',
            'lines' => $lines,
            'advisory' => $lead,
        ];
    }

    // ------------------------------------------------------------------ planting schedule + farm

    /**
     * All of the farmer's schedules (one per plot), read once for the schedule card, the crop plan, the outlook and the
     * setup prompts. Empty when the table is missing or cannot be read.
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
        } catch (Throwable) {
            return collect();
        }
    }

    /**
     * @param  Collection<int, FarmerPlantingSchedule>  $rows
     * @return array<string, mixed>|null
     */
    private function schedule(Collection $rows): ?array
    {
        $open = $rows->reject(fn (FarmerPlantingSchedule $r) => $r->monitoringStatus() === 'harvested');
        if ($open->isEmpty()) {
            return null;
        }

        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();
        $upcomingPlanting = $open->filter(fn (FarmerPlantingSchedule $r) => $r->planting_date && $r->planting_date->isFuture())
            ->sortBy(fn (FarmerPlantingSchedule $r) => $r->planting_date->timestamp)->first();
        $nextHarvest = $open->filter(fn (FarmerPlantingSchedule $r) => $r->harvestDateForPlanning() !== null)
            ->sortBy(fn (FarmerPlantingSchedule $r) => $r->harvestDateForPlanning()->timestamp)->first();

        $next = $upcomingPlanting ?? $nextHarvest;
        if (! $next) {
            return ['activity' => null, 'row' => $open->first(), 'stage' => $open->first()->monitoringStatusLabel()];
        }

        $planting = $next === $upcomingPlanting;
        $date = $planting ? CarbonImmutable::instance($next->planting_date) : CarbonImmutable::instance($next->harvestDateForPlanning());
        $days = (int) $today->diffInDays($date->startOfDay(), false);

        return [
            'activity' => [
                'kind' => $planting ? 'Planting' : 'Harvest',
                'date' => $date,
                'days' => $days,
                'when' => $days === 0 ? 'today' : ($days > 0 ? 'in '.$days.' '.($days === 1 ? 'day' : 'days') : abs($days).' '.(abs($days) === 1 ? 'day' : 'days').' overdue'),
            ],
            'row' => $next,
            'stage' => $next->monitoringStatusLabel(),
        ];
    }

    /**
     * Only fields that hold real data.
     *
     * @return array<int, array{label: string, value: string}>
     */
    private function farm($profile, ?string $barangay, ?array $schedule): array
    {
        $rows = [];
        if ($profile?->farm_area && (float) $profile->farm_area > 0) {
            $rows[] = ['label' => 'Farm Area', 'value' => number_format((float) $profile->farm_area, 2).' ha'];
        }
        if ($barangay) {
            $rows[] = ['label' => 'Barangay', 'value' => $barangay];
        }
        // Only when the farmer has actually mapped the farm: the saved outline and its area.
        try {
            $boundary = $profile?->farmBoundary;
            if ($boundary && (float) $boundary->calculated_area_hectares > 0) {
                $rows[] = ['label' => 'Farm Map', 'value' => 'Saved ('.number_format((float) $boundary->calculated_area_hectares, 2).' ha mapped)'];
            }
        } catch (Throwable) {
            // an unreadable boundary is simply not listed
        }
        if ($profile?->farm_type) {
            $rows[] = ['label' => 'Farm Type', 'value' => (string) $profile->farm_type];
        }
        $row = $schedule['row'] ?? null;
        if ($row) {
            $rows[] = ['label' => 'Crop Stage', 'value' => (string) $schedule['stage']];
            if ($row->planting_date) {
                $rows[] = ['label' => 'Planting Date', 'value' => $row->planting_date->format('M j, Y')];
            }
            if ($row->harvestDateForPlanning()) {
                $rows[] = ['label' => 'Expected Harvest', 'value' => $row->harvestDateForPlanning()->format('M j, Y')];
            }
        }

        return $rows;
    }

    // ------------------------------------------------------------------ crop plan + setup prompts

    /**
     * The crop calendar for every open plot, with reminders and weather advice. Null with no open schedule.
     *
     * @param  Collection<int, FarmerPlantingSchedule>  $rows
     * @return array<string, mixed>|null
     */
    private function plan(Collection $rows, array $weather): ?array
    {
        try {
            $open = $rows->reject(fn (FarmerPlantingSchedule $r) => $r->monitoringStatus() === 'harvested');

            return $this->cropPlan->build($open, $weather['state'] === 'ok' ? $weather['days'] : [], CarbonImmutable::now(self::TIMEZONE));
        } catch (Throwable $e) {
            Log::warning('Farmer dashboard crop plan failed.', ['exception' => $e::class]);

            return null;
        }
    }

    /**
     * What is missing from the farmer's profile and what filling it in would unlock. Each item is one real gap.
     *
     * @param  Collection<int, FarmerPlantingSchedule>  $rows
     * @return array{items: array<int, array<string, mixed>>, done: int, total: int}|null
     */
    private function setup($profile, ?string $barangay, Collection $rows): ?array
    {
        $hasBoundary = false;
        try {
            $hasBoundary = (float) ($profile?->farmBoundary?->calculated_area_hectares ?? 0) > 0;
        } catch (Throwable) {
            // an unreadable boundary counts as not mapped
        }
        $unrecorded = $rows->first(fn (FarmerPlantingSchedule $r) => $r->monitoringStatus() === 'harvested' && ! (is_numeric($r->getAttribute('actual_yield')) && (float) $r->getAttribute('actual_yield') > 0));

        $checks = [
            ['key' => 'barangay', 'ok' => (bool) $barangay, 'title' => 'Add your barangay', 'benefit' => 'to see farm risk and advisories for your area.', 'href' => route('profile.edit'), 'cta' => 'Update Profile'],
            ['key' => 'farm_type', 'ok' => (bool) $profile?->farm_type, 'title' => 'Choose your farm type', 'benefit' => 'so your expected harvest fits how your farm gets water (irrigated or rain only).', 'href' => route('profile.edit'), 'cta' => 'Update Profile'],
            ['key' => 'farm_area', 'ok' => (float) ($profile?->farm_area ?? 0) > 0, 'title' => 'Add your farm area', 'benefit' => 'to see how many tons your farm may produce, not just tons per hectare.', 'href' => route('profile.edit'), 'cta' => 'Update Profile'],
            ['key' => 'schedule', 'ok' => $rows->isNotEmpty(), 'title' => 'Add your planting plan', 'benefit' => 'to get a crop calendar, reminders and weather advice for each stage.', 'href' => route('farmer.planting-schedules.create'), 'cta' => 'Add Schedule'],
            ['key' => 'boundary', 'ok' => $hasBoundary, 'title' => 'Draw your farm on the map', 'benefit' => 'so your farm size comes from your own map.', 'href' => route('farmer.boundary.edit'), 'cta' => 'Map My Farm'],
        ];
        if ($unrecorded && SchemaCache::hasColumn('farmer_planting_schedules', 'actual_yield')) {
            $checks[] = ['key' => 'harvest', 'ok' => false, 'title' => 'Record your harvest', 'benefit' => 'so your harvest is compared with your own past harvests.', 'href' => route('farmer.planting-schedules.edit', $unrecorded->id), 'cta' => 'Record Harvest'];
        }

        $items = array_values(array_filter($checks, fn (array $c) => ! $c['ok']));
        if ($items === []) {
            return null;
        }

        return ['items' => $items, 'done' => count($checks) - count($items), 'total' => count($checks)];
    }

    // ------------------------------------------------------------------ KPI cards (today + my farm)

    /** @return array<int, array<string, mixed>> */
    private function kpis(?array $prediction, ?array $risk, array $advisories, array $alerts, ?array $schedule): array
    {
        $cards = [];

        if ($prediction) {
            $cards[] = [
                'key' => 'yield', 'label' => 'Expected Rice Harvest', 'value' => number_format($prediction['yield'], 2), 'unit' => 't/ha',
                'context' => ($prediction['period'] ? $prediction['period'].' best guess' : 'Expected harvest'),
                'note' => 'Best guess — not a real harvest record'.($prediction['production'] !== null ? ' · about '.number_format($prediction['production'], 2).' tons on your '.number_format($prediction['area'], 2).' ha' : ''),
                'href' => route('weather-predictions.index', ['tab' => 'rice-yield']), 'cta' => null,
            ];
        }

        if ($risk) {
            $label = ['Low' => 'Good conditions', 'Moderate' => 'Go with care', 'High' => 'Be careful', 'Severe' => 'Be very careful'][$risk['level']] ?? 'Check advisory';
            $cards[] = [
                'key' => 'guidance', 'label' => 'Planting Guidance', 'value' => $label, 'unit' => null, 'compact' => true,
                'context' => $this->planningLine($risk), 'note' => null,
                'href' => $advisories[0]['href'] ?? route('planting-advisories.index'), 'cta' => 'View Advisory',
            ];
        }

        $n = count($alerts);
        $cards[] = [
            'key' => 'alerts', 'label' => 'Farm Alerts', 'value' => (string) $n, 'unit' => null, 'level' => $n > 0 ? ($alerts[0]['severity'] === 'critical' ? 'critical' : 'warning') : 'ok',
            'context' => $n > 0 ? ($n === 1 ? '1 active alert' : $n.' active alerts') : '✓ No urgent alerts', 'note' => $n > 0 ? $alerts[0]['title'] : null,
            'href' => $n > 0 ? '#mfdAlerts' : route('notifications.index'), 'cta' => null,
        ];

        $activity = $schedule['activity'] ?? null;
        $cards[] = $activity
            ? ['key' => 'activity', 'label' => 'Next Farm Activity', 'value' => $activity['kind'].' '.$activity['when'], 'unit' => null, 'compact' => true,
                'context' => $activity['date']->format('l, F j'), 'note' => null, 'href' => route('calendar.index'), 'cta' => 'Open Calendar']
            : ['key' => 'activity', 'label' => 'Next Farm Activity', 'value' => 'Set your planting schedule', 'unit' => null, 'compact' => true,
                'context' => 'Add your planting date to track your season.', 'note' => null, 'href' => route('farmer.planting-schedules.create'), 'cta' => 'Add Schedule'];

        return $cards;
    }
}
