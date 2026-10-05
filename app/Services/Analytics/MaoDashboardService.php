<?php

namespace App\Services\Analytics;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\FieldReport;
use App\Models\HeatmapArea;
use App\Models\Notification;
use App\Models\PlantingAdvisory;
use App\Models\TyphoonSafetyResponse;
use App\Models\User;
use App\Services\FarmerOrganizationSummaryService;
use App\Services\Risk\BarangayRiskMapService;
use App\Services\TyphoonSafetyService;
use App\Services\Weather\FarmerDashboardWeatherService;
use App\Services\WeatherApiService;
use App\Support\BusinessClock;
use App\Support\DashboardData;
use App\Support\LianBarangays;
use App\Support\ProjectYieldDataset;
use App\Support\SampleData;
use App\Support\SchemaCache;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Everything the MAO overview shows, from ONE place: weather, farm risk, rice yield, planting, farmers and advisories.
 * The page is deliberately short: what Lian's rice farmers and the municipality need to decide, nothing else. Office
 * housekeeping (activity log, farmer weather feedback, field reports) lives on the MAO operations page.
 *
 * Rules that shape it:
 *   - every number is queried or computed here from a real source, never typed in;
 *   - a section with no meaningful data is simply absent (null / empty), never "0", "N/A" or "No data";
 *   - a source that FAILED gives an error state for that section only, never a fake zero;
 *   - a legitimate zero (for example 0.0 mm of rain, or 0 pending advisories) is kept when its record exists;
 *   - historical, recorded, live, forecast and calculated data are labelled and never mixed.
 */
final class MaoDashboardService
{
    public const TIMEZONE = 'Asia/Manila';

    /** Forecast days are flagged for attention only past these documented thresholds (same as the calendar's "High" band). */
    /**
     * PENDING MAO / METHODOLOGY CONFIRMATION. This 25 mm/day figure is NOT a validated
     * MAO/PAGASA threshold; do not describe it as official. All uses are listed in
     * docs/data-audit/phase-2-report.md so it can be replaced consistently.
     */
    public const HEAVY_RAIN_MM = 25.0;

    public const STRONG_WIND_KMH = 35.0;

    public const VERY_HOT_C = 35.0;

    /** A yield change is shown only when at least this many months are recorded in BOTH years. */
    public const MIN_COMPARABLE_MONTHS = 6;

    /** How many forecast days ahead a heavy-rain warning looks. */
    private const HEAVY_RAIN_LOOKAHEAD_DAYS = 3;

    public function __construct(
        private readonly FarmerDashboardWeatherService $weather,
        private readonly WeatherApiService $weatherApi,
        private readonly DashboardOverviewService $overview,
        private readonly FarmerOrganizationSummaryService $farmers,
        private readonly ProjectYieldDataset $yields,
        private readonly DashboardData $data,
        private readonly TyphoonSafetyService $typhoon,
        private readonly BarangayRiskMapService $riskMap,
    ) {}

    /** The four rated farm-risk levels, lowest first. */
    private const RISK_LEVELS = ['Low', 'Moderate', 'High', 'Severe'];

    // ------------------------------------------------------------------ payload

    /** @return array<string, mixed> shared by every MAO user (cacheable) */
    public function payload(bool $forceWeather = false): array
    {
        $now = CarbonImmutable::now(self::TIMEZONE);

        $weather = $this->weather($forceWeather);
        $risk = $this->risk();
        $advisories = $this->advisories();
        $yield = $this->yield();
        $farmers = $this->farmerCoverage();
        $climate = $this->climate();

        return [
            'generated_at' => $now,
            'weather' => $weather,
            'risk' => $risk,
            'advisories' => $advisories,
            'yield' => $yield,
            'farmers' => $farmers,
            'climate' => $climate,
            'production' => $this->production(),
            'planting' => $this->planting($risk),
            'watch' => $this->watchList($risk),
            'attention' => $this->attention($weather, $risk, $advisories),
            'kpis' => $this->kpis($yield, $risk, $advisories, $farmers),
            'sources' => $this->sources($weather, $yield, $risk, $climate, $farmers, $now),
        ];
    }

    /**
     * Adds what depends on who is signed in (their unread messages) without touching the shared cache.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function forUser(array $payload, User $user): array
    {
        try {
            $unread = ConversationMessage::query()
                ->whereIn('conversation_id', Conversation::query()
                    ->where('participant_one_id', $user->id)->orWhere('participant_two_id', $user->id)->select('id'))
                ->where('sender_id', '!=', $user->id)
                ->whereNull('read_at')
                ->count();
        } catch (Throwable) {
            $unread = 0;      // a failed count adds no item; it is never shown as a number
        }

        $payload['unread_notifications'] = $this->unreadNotifications($user);
        if ($payload['unread_notifications'] > 0) {
            $n = $payload['unread_notifications'];
            $payload['attention'][] = [
                'severity' => 'action', 'icon' => 'bell',
                'title' => number_format($n).' Unread '.($n === 1 ? 'Notification' : 'Notifications'),
                'detail' => 'Announcements, advisories and warnings sent to you.',
                'action' => 'Open Notifications', 'href' => route('notifications.index'),
            ];
        }

        if ($unread > 0) {
            $payload['attention'][] = [
                'severity' => 'action', 'icon' => 'message',
                'title' => number_format($unread).' Unread '.($unread === 1 ? 'Message' : 'Messages'),
                'detail' => 'Waiting in your inbox.',
                'action' => 'Open Messages', 'href' => route('messages.index'),
            ];
        }
        $payload['attention'] = $this->sortAttention($payload['attention']);

        return $payload;
    }

    /** Same count as the sidebar badge (seeded sample rows left out). A failed count is 0, never a made-up number. */
    private function unreadNotifications(User $user): int
    {
        try {
            // The very cache entry the header bell and sidebar badge read, so all three always show one number.
            return (int) \Illuminate\Support\Facades\Cache::remember('sidebar:unread-notifications:'.$user->id, now()->addSeconds(20),
                fn () => SampleData::without(Notification::query(), 'notifications')->where('user_id', $user->id)->where('is_read', false)->count());
        } catch (Throwable) {
            return 0;
        }
    }

    // ------------------------------------------------------------------ weather

    /** @return array<string, mixed> */
    private function weather(bool $force): array
    {
        try {
            $w = $this->weather->current($force);
        } catch (Throwable) {
            return ['state' => 'error'];
        }

        // Wind by date comes from a second, optional lookup: if it fails the forecast simply shows no wind.
        try {
            $series = (array) data_get($this->weatherApi->forecast(), 'daily_series', []);
        } catch (Throwable) {
            $series = [];
        }

        $current = is_array($w['current'] ?? null) ? $w['current'] : [];
        if (! ($w['success'] ?? false) || ! is_numeric($current['temperature'] ?? null)) {
            return ['state' => 'error'];
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
                $high !== null && $high >= self::VERY_HOT_C ? 'Very hot' : null,
            ]));
            $days[] = [
                'date' => CarbonImmutable::parse($day['date'], self::TIMEZONE),
                'condition' => \App\Support\WeatherConditionLabel::display($day['condition'] ?? null),
                'icon' => $day['icon'] ?? null,
                'rain' => $rain, 'high' => $high,
                'low' => is_numeric($day['temperature_min'] ?? null) ? (float) $day['temperature_min'] : null,
                'chance' => is_numeric($day['precipitation_probability'] ?? null) ? (float) $day['precipitation_probability'] : null,
                'wind' => $wind,
                'flags' => $flags,
            ];
        }

        $updatedRaw = data_get($w, 'timestamps.provider_updated_at') ?? data_get($w, 'timestamps.server_fetched_at') ?? ($w['fetched_at'] ?? null);

        return [
            'state' => 'ok',
            'temperature' => (float) $current['temperature'],
            'humidity' => is_numeric($current['humidity'] ?? null) ? (float) $current['humidity'] : null,
            'rain' => is_numeric($current['precipitation'] ?? null) ? (float) $current['precipitation'] : null,    // 0.0 is a real reading
            'wind' => is_numeric($current['wind_speed'] ?? null) ? (float) $current['wind_speed'] : null,
            'condition' => \App\Support\WeatherConditionLabel::display($current['condition'] ?? null),
            'updated_at' => $updatedRaw ? CarbonImmutable::parse($updatedRaw)->timezone(self::TIMEZONE) : null,
            'source' => 'Online weather service',      // provider names stay on IT pages; MAO sees plain wording
            'location' => (string) data_get($w, 'location.name', 'Lian').', '.(string) data_get($w, 'location.province', 'Batangas'),
            'stale' => (bool) ($w['stale'] ?? false),
            'days' => $days,
        ];
    }

    // ------------------------------------------------------------------ farm risk

    /**
     * Farm risk from the one source the Lian Municipality Heat Map draws (BarangayRiskMapService::snapshot(), the
     * same ratings /api/lian/risk-map serves), so the KPI, the Municipal Risk Overview and the map colours can never
     * show different counts. "Monitored" is every barangay in that dataset; a barangay still without a rating is
     * counted as No Data, never left out.
     *
     * @return array<string, mixed>
     */
    private function risk(): array
    {
        try {
            $map = $this->riskMap->snapshot();
        } catch (Throwable) {
            return ['state' => 'error'];
        }
        $rows = collect($map['barangays'] ?? []);
        $rated = $rows->whereIn('risk_level', self::RISK_LEVELS);
        if ($rated->isEmpty()) {
            return ['state' => 'empty'];
        }

        // Plain-language reasons are stored with the ratings, not in the map payload; so is the planting advice, which
        // the map payload leaves out while it is showing its last saved ratings.
        try {
            $stored = $this->data->currentRiskRatings()->keyBy(fn (HeatmapArea $a): string => (string) LianBarangays::canonicalName($a->barangay));
        } catch (Throwable) {
            $stored = collect();
        }
        $reasons = $stored->map(fn (HeatmapArea $a): string => (string) $a->main_risk_reason);

        $counts = [];
        foreach (self::RISK_LEVELS as $level) {
            $counts[$level] = $rated->where('risk_level', $level)->count();
        }
        $computed = $rated->pluck('updated_at')->filter()->max();

        return [
            'state' => 'ok',
            'total' => $rows->count(),
            'rated' => $rated->count(),
            'no_data' => $rows->count() - $rated->count(),
            'counts' => $counts,
            'barangays' => $rows->map(fn (array $row): array => [
                'barangay' => $row['barangay'],
                'level' => in_array($row['risk_level'] ?? null, self::RISK_LEVELS, true) ? $row['risk_level'] : 'No Data',
                'score' => $row['risk_score'] ?? null,
                'planting' => (string) (data_get($row, 'advisory.planting') ?: $stored->get($row['barangay'])?->planting_advisory),
                'reason' => $this->plainReason((string) ($reasons[$row['barangay']] ?? '')),
            ])->values()->all(),
            'computed_at' => $computed ? CarbonImmutable::parse($computed)->timezone(self::TIMEZONE) : null,
            'barangays_total' => count(LianBarangays::all()),
        ];
    }

    /**
     * Barangays worth watching: High and Severe only. Nothing at all when there are no ratings.
     *
     * @return array{state: string, items: array<int, array<string, mixed>>}
     */
    private function watchList(array $risk): array
    {
        if (($risk['state'] ?? null) !== 'ok') {
            return ['state' => 'hidden', 'items' => []];
        }

        $items = collect($risk['barangays'])->whereIn('level', ['High', 'Severe'])->sortByDesc('score')->take(6)
            ->map(fn (array $b): array => ['barangay' => $b['barangay'], 'level' => $b['level'], 'reason' => $b['reason']])
            ->values()->all();

        return ['state' => $items === [] ? 'clear' : 'watch', 'items' => $items];
    }

    /**
     * A risk reason is shown only if it reads as plain agricultural wording. Text that uses model or scoring jargon
     * (which MAO-facing pages avoid) is left out; the barangay and its level are still listed.
     */
    private function plainReason(string $reason): string
    {
        $reason = trim($reason);

        return preg_match('/risk score|random forest|python|open-meteo|insufficient input|classification/i', $reason) ? '' : $reason;
    }

    // ------------------------------------------------------------------ advisories

    /** @return array<string, mixed> */
    private function advisories(): array
    {
        try {
            $total = PlantingAdvisory::query()->count();

            return [
                'state' => $total === 0 ? 'empty' : 'ok',
                'total' => $total,
                'active' => PlantingAdvisory::query()->active()->count(),
                'pending' => PlantingAdvisory::query()->pendingReview()->count(),
            ];
        } catch (Throwable) {
            return ['state' => 'error'];
        }
    }

    // ------------------------------------------------------------------ rice yield (historical)

    /**
     * Yield history is the project's modeling dataset (municipal monthly averages), always labelled historical.
     * A comparison is offered only when it is like-for-like (same months, enough of them) and never mixes sources.
     *
     * @return array<string, mixed>
     */
    private function yield(): array
    {
        try {
            $years = $this->yields->years()->sort()->values();
        } catch (Throwable) {
            return ['state' => 'error'];
        }
        if ($years->isEmpty()) {
            return ['state' => 'empty'];
        }

        $points = $years->map(fn (int $y): array => [
            'year' => $y,
            'yield' => round((float) $this->yields->yearlyMean($y), 2),
            'months' => $this->yields->monthsRecorded($y),
        ])->filter(fn (array $p) => $p['yield'] > 0)->values();
        if ($points->isEmpty()) {
            return ['state' => 'empty'];
        }

        $latest = $points->last();
        $cmp = $this->yields->compareWithPrevious($latest['year']);
        $comparable = $cmp && $cmp['months'] >= self::MIN_COMPARABLE_MONTHS;

        return [
            'state' => 'ok',
            'points' => $points->all(),
            'latest' => $latest,
            'comparison' => $comparable ? $cmp : null,        // omitted (not shown as a percentage) when not comparable
            'source' => ProjectYieldDataset::SOURCE_NAME,
            'records' => $points->sum('months'),
        ];
    }

    // ------------------------------------------------------------------ climate chart

    /** @return array<string, mixed>|null */
    private function climate(): ?array
    {
        try {
            return $this->overview->climateSnapshot();
        } catch (Throwable) {
            return null;
        }
    }

    // ------------------------------------------------------------------ farmers

    /** @return array<string, mixed> */
    private function farmerCoverage(): array
    {
        try {
            $s = $this->farmers->summarize()['farmerOrganizationSummary'];
            $covered = FarmerProfile::query()->whereNotNull('barangay')->distinct()->pluck('barangay')
                ->map(fn ($b) => LianBarangays::canonicalName((string) $b))->filter()->unique()->count();
        } catch (Throwable) {
            return ['state' => 'error'];
        }

        return [
            'state' => $s['totalFarmers'] === 0 ? 'empty' : 'ok',
            'farmers' => $s['totalFarmers'],
            'accounts' => $s['totalFarmerAccounts'] ?? null,
            'organizations' => $s['totalOrganizations'],
            'without_organization' => $s['farmersWithoutOrganization'],
            'farm_area' => $s['totalFarmArea'],
            'barangays_covered' => $covered,
            'barangays_total' => count(LianBarangays::all()),
        ];
    }

    // ------------------------------------------------------------------ conditional sections

    /**
     * Planting & production: only when real production records exist. Recorded and expected yield are kept apart.
     *
     * @return array<string, mixed>|null
     */
    private function production(): ?array
    {
        try {
            $all = $this->data->riceRows();
            // Only the most recent harvest year on file: mixing years would add the same land up twice.
            $year = $all->pluck('year')->filter()->max();
            $latest = $year ? $all->filter(fn ($r): bool => (int) $r->year === (int) $year) : $all;
            if ($latest->isEmpty()) {
                return null;
            }
            $isIrrigated = fn ($r): bool => str_contains(strtolower((string) $r->irrigation_type), 'irrig');
            $irrigated = $latest->filter($isIrrigated);
            $rainfed = $latest->reject($isIrrigated);
            $area = (float) $latest->sum('area_hectares');
            // Expected yield averages the same current, one-per-barangay ratings the risk panels use.
            $expected = $this->data->currentRiskRatings()->whereNotNull('predicted_yield')->avg('predicted_yield');
        } catch (Throwable) {
            return null;
        }

        return [
            'year' => $year ? (int) $year : null,
            'barangays' => $latest->pluck('barangay')->filter()->unique()->count(),
            'barangays_total' => count(LianBarangays::all()),
            'planted' => $area,
            'irrigated' => (float) $irrigated->sum('area_hectares'),
            'rainfed' => (float) $rainfed->sum('area_hectares'),
            'expected_yield' => $expected !== null ? (float) $expected : null,                                    // model estimate
            'recorded_yield' => $area > 0 ? (float) $latest->sum('total_production') / $area : null,              // recorded harvests
        ];
    }

    /**
     * Planting guide for today: how many barangays fall under each plain answer to "can we plant now?".
     *
     * It reads the planting sentence the Heat Map shows for each barangay (DecisionSupportService, from this month's
     * expected rain) together with that barangay's farm-risk rating, the way the farmer dashboard does: "Ideal planting
     * period." counts as a good time only where the rating is Low, and a High or Severe rating always means wait.
     * Farmers' own planting schedules are added only once some exist.
     *
     * @return array<string, mixed>|null
     */
    private function planting(array $risk): ?array
    {
        if (($risk['state'] ?? null) !== 'ok') {
            return null;
        }

        $groups = ['good' => [], 'care' => [], 'wait' => []];
        foreach ($risk['barangays'] as $b) {
            $text = (string) ($b['planting'] ?? '');
            if ($b['level'] === 'No Data' || $text === '') {
                continue;
            }
            $key = match (true) {
                in_array($b['level'], ['High', 'Severe'], true), str_contains($text, 'Delay planting'), str_contains($text, 'Wait for sufficient rainfall') => 'wait',
                str_starts_with($text, 'Ideal planting') && $b['level'] === 'Low' => 'good',
                default => 'care',
            };
            $groups[$key][] = $b['barangay'];
        }
        $rated = array_sum(array_map('count', $groups));
        if ($rated === 0) {
            return null;
        }

        return ['groups' => $groups, 'rated' => $rated, 'schedules' => $this->plantingSchedules()];
    }

    /** Farmers' recorded planting schedules; null when none exist or the count cannot be read. @return array<string, int>|null */
    private function plantingSchedules(): ?array
    {
        try {
            if (! SchemaCache::hasTable('farmer_planting_schedules')) {
                return null;
            }
            $total = FarmerPlantingSchedule::query()->count();
            if ($total === 0) {
                return null;
            }
            $today = BusinessClock::today()->toDateString();
            $soon = BusinessClock::today()->addDays(30)->toDateString();

            return [
                'total' => $total,
                'planting_soon' => FarmerPlantingSchedule::query()->whereBetween('planting_date', [$today, $soon])->count(),
                'harvest_soon' => FarmerPlantingSchedule::query()->whereNull('harvested_on')->whereBetween('scheduled_harvest_date', [$today, $soon])->count(),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    // ------------------------------------------------------------------ KPI cards

    /**
     * Four numbers only. Today's weather is not one of them: the weather card directly below shows the same reading.
     *
     * @return array<int, array<string, mixed>>
     */
    private function kpis(array $yield, array $risk, array $advisories, array $farmers): array
    {
        $cards = [];

        if (($farmers['state'] ?? null) === 'ok') {
            $cards[] = [
                'key' => 'farmers', 'icon' => 'farmers', 'label' => 'Farmers with Profiles', 'unit' => null,
                'value' => number_format($farmers['farmers']),
                'context' => number_format($farmers['organizations']).' Farmer '.($farmers['organizations'] === 1 ? 'Organization' : 'Organizations')
                    .($farmers['accounts'] !== null ? ' · '.number_format($farmers['accounts']).' farmer '.($farmers['accounts'] === 1 ? 'account' : 'accounts') : ''),
                'trend' => null, 'href' => route('farmer-profiles.index'),
                'tip' => 'Farmers whose farm profile (barangay, farm area, organization) is recorded in iClimate. Farmer accounts counts everyone who can sign in as a farmer, including those who have not completed a profile yet, so it can be higher.',
            ];
        }

        if (($yield['state'] ?? null) === 'ok') {
            $l = $yield['latest'];
            $cmp = $yield['comparison'];
            $cards[] = [
                'key' => 'yield', 'icon' => 'yield', 'label' => 'Average Rice Yield', 'unit' => 't/ha',
                'value' => number_format($l['yield'], 2),
                'context' => 'Historical · '.$l['year'].' · '.$l['months'].' monthly '.($l['months'] === 1 ? 'record' : 'records'),
                'trend' => $cmp ? [
                    'delta' => $cmp['change'],
                    'label' => 'vs same '.$cmp['months'].' months of '.$cmp['previous_year'],
                    'basis' => 'Based on '.$cmp['months'].' comparable monthly records',
                ] : null,
                'href' => route('rice-productions.index'),
                'tip' => 'Mean of the municipal monthly average yields on file for '.$l['year'].' ('.$l['months'].' of 12 months). Historical data from the iClimate modeling dataset, not a forecast. '
                    .'The change compares only the months recorded in both years.',
            ];
        }

        if (($risk['state'] ?? null) === 'ok') {
            $cards[] = [
                'key' => 'barangays', 'icon' => 'map', 'label' => 'Barangays Monitored', 'unit' => 'of '.$risk['barangays_total'],
                'value' => number_format($risk['total']),
                'context' => $risk['no_data'] > 0
                    ? number_format($risk['rated']).' rated · '.number_format($risk['no_data']).' awaiting data'
                    : 'Municipality-wide farm risk monitoring',
                'trend' => null, 'href' => '#mtdRiskMap', 'risk_category' => 'monitored',
                'tip' => 'Official Lian barangays in the municipal farm-risk dataset. The same ratings colour the Lian Municipality Heat Map.',
            ];
        }

        if (($advisories['state'] ?? null) === 'ok') {
            $cards[] = [
                'key' => 'advisories', 'icon' => 'advisory', 'label' => 'Active Advisories', 'unit' => null,
                'value' => number_format($advisories['active']),
                'context' => $advisories['pending'] > 0 ? number_format($advisories['pending']).' pending review' : 'None pending review',
                'trend' => null, 'href' => route('planting-advisories.index'),
                'tip' => 'Published advisories that are currently in effect. Advisories awaiting review are counted separately.',
            ];
        }

        return $cards;
    }

    // ------------------------------------------------------------------ attention center

    /** @return array<int, array<string, mixed>> */
    private function attention(array $weather, array $risk, array $advisories): array
    {
        $items = [];

        if (($advisories['pending'] ?? 0) > 0) {
            $n = $advisories['pending'];
            $items[] = ['severity' => 'action', 'icon' => 'advisory', 'title' => number_format($n).' '.($n === 1 ? 'Advisory' : 'Advisories').' Awaiting Review',
                'detail' => 'Review before farmers can see '.($n === 1 ? 'it' : 'them').'.', 'action' => 'Review', 'href' => route('management.advisories.index')];
        }

        if (($risk['state'] ?? null) === 'ok') {
            $severe = $risk['counts']['Severe'];
            $high = $risk['counts']['High'] + $severe;
            if ($high > 0) {
                $items[] = ['severity' => $severe > 0 ? 'urgent' : 'action', 'icon' => 'map',
                    'title' => number_format($high).' '.($high === 1 ? 'Barangay' : 'Barangays').' Under High Farm Risk',
                    'detail' => ($severe > 0 ? number_format($severe).' at Severe level. ' : '').'Planning indicator, not an official hazard assessment.',
                    'action' => 'View Heat Map', 'href' => '#mtdRiskMap'];
            }
        }

        if ($weather['state'] === 'ok') {
            $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();
            foreach ($weather['days'] as $day) {
                $ahead = (int) $today->diffInDays($day['date']->startOfDay(), false);
                if ($ahead >= 0 && $ahead <= self::HEAVY_RAIN_LOOKAHEAD_DAYS && $day['rain'] !== null && $day['rain'] >= self::HEAVY_RAIN_MM) {
                    $when = $ahead === 0 ? 'Today' : ($ahead === 1 ? 'Tomorrow' : $day['date']->format('l'));
                    $items[] = ['severity' => 'action', 'icon' => 'rain', 'title' => 'Heavy Rain Expected '.$when,
                        'detail' => number_format($day['rain'], 0).' mm forecast (planning threshold '.number_format(self::HEAVY_RAIN_MM, 0).' mm).', 'action' => 'View Forecast', 'href' => route('weather-predictions.index')];
                    break;
                }
            }
        }

        try {
            $reports = FieldReport::query()->where('status', '!=', FieldReport::STATUS_RESOLVED)->count();
            if ($reports > 0) {
                $items[] = ['severity' => 'action', 'icon' => 'report', 'title' => number_format($reports).' Field '.($reports === 1 ? 'Report' : 'Reports').' Unresolved',
                    'detail' => 'Issues reported from the field.', 'action' => 'Review', 'href' => route('field-reports.index')];
            }
        } catch (Throwable) {
            // an unreadable count adds no item
        }

        if ($item = $this->typhoonItem()) {
            $items[] = $item;
        }

        return $this->sortAttention($items);
    }

    /** An active typhoon safety check-in is a genuine safety warning: surfaced here, never hidden. */
    private function typhoonItem(): ?array
    {
        try {
            $event = $this->typhoon->activeEvent();
            if (! $event) {
                return null;
            }
            $needsHelp = SchemaCache::hasTable('typhoon_safety_responses')
                ? TyphoonSafetyResponse::query()->where('event_key', $event['key'])->where('status', TyphoonSafetyResponse::STATUS_NEEDS_HELP)->count()
                : 0;
        } catch (Throwable) {
            return null;
        }

        return [
            'severity' => $needsHelp > 0 ? 'urgent' : 'action', 'icon' => 'storm',
            'title' => $needsHelp > 0 ? number_format($needsHelp).' '.($needsHelp === 1 ? 'Farmer Needs' : 'Farmers Need').' Help (Typhoon Check-In)' : 'Typhoon Safety Check-In Is Active',
            'detail' => 'Farmer safety responses are being collected.', 'action' => 'Open Check-In', 'href' => route('mao.operations').'#typhoon-safety',
        ];
    }

    /** @param  array<int, array<string, mixed>>  $items */
    private function sortAttention(array $items): array
    {
        $rank = ['urgent' => 0, 'action' => 1];
        usort($items, fn ($a, $b) => ($rank[$a['severity']] ?? 2) <=> ($rank[$b['severity']] ?? 2));

        return $items;
    }

    // ------------------------------------------------------------------ data sources

    /** @return array<int, array<string, mixed>> */
    private function sources(array $weather, array $yield, array $risk, ?array $climate, array $farmers, CarbonImmutable $now): array
    {
        $rows = [];
        if ($weather['state'] === 'ok') {
            $rows[] = ['label' => 'Current weather', 'type' => 'LIVE', 'source' => $weather['source'].' · '.$weather['location'], 'updated' => $weather['updated_at']];
            if ($weather['days']) {
                $rows[] = ['label' => 'Weather outlook', 'type' => 'FORECAST', 'source' => $weather['source'].' daily forecast', 'updated' => $weather['updated_at']];
            }
        }
        if ($climate) {
            $rows[] = ['label' => 'Rainfall & temperature', 'type' => 'HISTORICAL', 'source' => 'Ambulong monthly climate records ('.$climate['year'].'); dashed lines are model forecasts', 'updated' => null];
        }
        if (($yield['state'] ?? null) === 'ok') {
            $rows[] = ['label' => 'Rice yield', 'type' => 'HISTORICAL', 'source' => $yield['source'], 'updated' => null];
        }
        if (($farmers['state'] ?? null) === 'ok') {
            $rows[] = ['label' => 'Farmer profiles', 'type' => 'RECORDED', 'source' => 'iClimate database', 'updated' => $now];
        }
        if (($risk['state'] ?? null) === 'ok') {
            $rows[] = ['label' => 'Farm risk', 'type' => 'CALCULATED', 'source' => 'Calculated by iClimate from weather, yield and barangay exposure', 'updated' => $risk['computed_at']];
        }

        return $rows;
    }
}
