<?php

namespace App\Services\Analytics;

use App\Models\AdvisoryFeedback;
use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\FieldReport;
use App\Models\HeatmapArea;
use App\Models\Notification as UserNotification;
use App\Models\PlantingAdvisory;
use App\Models\Report;
use App\Models\RiceProduction;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\PythonService;
use App\Services\Risk\BarangayRiskMapService;
use App\Support\Aggregates;
use App\Support\DashboardData;
use App\Support\LianBarangays;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Role-specific KPI strips for the Farmer, MAO and IT-expert dashboards.
 *
 * Every figure here is read straight from stored records — nothing is
 * estimated or padded. Where a comparison can't be made honestly (e.g. two
 * years with no barangay in common) the trend is null and the note says why,
 * rather than showing a misleading percentage.
 *
 * Each KPI is an array of:
 *   key, label, value (display string), unit, note,
 *   trend: null|{delta: float, label: string, good: 'up'|'down'},
 *   status: null|'good'|'warning'|'critical', status_label, href
 */
class DashboardKpiService
{
    // Long TTL is safe: a change to the data a value reads retires it at once (CacheScope).
    private const CACHE_SECONDS = 300;

    public function __construct(private readonly DashboardData $data) {}

    /* ------------------------------------------------------------------
     | Shared rice-yield math
     * ------------------------------------------------------------------ */

    /**
     * Per-year production totals with an AREA-WEIGHTED yield
     * (total tons / total hectares). A plain AVG(yield_per_hectare) lets a
     * 2-hectare plot count as much as a 40-hectare one, which misstates the
     * municipal yield.
     *
     * @return Collection<int, array{year: int, production: float, area: float, yield: float|null, barangays: Collection}>
     */
    public function yieldByYear(?string $barangay = null): Collection
    {
        return $this->data->riceRows()
            ->when($barangay, fn (Collection $rows) => $rows->where('barangay', $barangay))
            ->whereNotNull('year')
            ->groupBy('year')
            ->map(function (Collection $rows, $year): array {
                $area = (float) $rows->sum('area_hectares');
                $production = (float) $rows->sum('total_production');

                return [
                    'year' => (int) $year,
                    'production' => $production,
                    'area' => $area,
                    'yield' => $area > 0 ? $production / $area : null,
                    'barangays' => $rows->pluck('barangay')->filter()->unique()->values(),
                ];
            })
            ->sortKeysDesc()
            ->values();
    }

    /**
     * Year-over-year change in area-weighted yield between the two most
     * recent years on record. Municipal comparisons only use barangays that
     * reported in BOTH years — otherwise a change in which barangays
     * reported would show up as a fake yield swing.
     *
     * Keeps the array shape the dashboard views already consume.
     */
    public function yieldYoy(?string $barangay = null): ?array
    {
        $years = $this->yieldByYear($barangay);

        if ($years->count() < 2) {
            return null;
        }

        [$latest, $previous] = [$years[0], $years[1]];
        $common = $latest['barangays']->intersect($previous['barangays'])->values();

        if ($barangay === null) {
            if ($common->isEmpty()) {
                return null;
            }

            $latest = $this->weightedFor($latest['year'], $common);
            $previous = $this->weightedFor($previous['year'], $common);
        }

        if (! $latest['yield'] || ! $previous['yield']) {
            return null;
        }

        $deltaPercent = round((($latest['yield'] - $previous['yield']) / $previous['yield']) * 100, 1);

        return [
            'latest_year' => $latest['year'],
            'previous_year' => $previous['year'],
            'latest_avg_yield' => round($latest['yield'], 2),
            'previous_avg_yield' => round($previous['yield'], 2),
            'delta_percent' => $deltaPercent,
            'direction' => $deltaPercent > 0 ? 'up' : ($deltaPercent < 0 ? 'down' : 'flat'),
            'barangays_compared' => $common->count(),
        ];
    }

    private function weightedFor(int $year, Collection $barangays): array
    {
        $rows = $this->data->riceRows()->where('year', $year)->whereIn('barangay', $barangays->all());
        $area = (float) $rows->sum('area_hectares');

        return [
            'year' => $year,
            'yield' => $area > 0 ? (float) $rows->sum('total_production') / $area : null,
        ];
    }

    /* ------------------------------------------------------------------
     | Farmer — scoped to the signed-in farmer and their barangay
     * ------------------------------------------------------------------ */

    public function farmer(User $user, ?string $barangay, ?array $barangayRisk): array
    {
        $cached = Cache::remember(
            // Shared data it reads (CacheScope) plus the farmer's own rows (plans, unread notifications, feedback).
            'dashboard:kpi:farmer:'.\App\Support\CacheScope::token('farmer-overview').':u'.\App\Support\PageCache::userVersion((int) $user->id).':'.$user->id.':'.md5((string) $barangay),
            now()->addSeconds(self::CACHE_SECONDS),
            fn (): array => $this->farmerFigures($user, $barangay)
        );

        return [
            $this->farmerRiskKpi($barangay, $barangayRisk),
            ...$cached,
        ];
    }

    private function farmerRiskKpi(?string $barangay, ?array $risk): array
    {
        $level = $risk['risk_level'] ?? null;
        $hasData = $level && $level !== 'No Data';

        return [
            'key' => 'farm-risk',
            'label' => 'Farm Risk',
            'value' => $hasData ? $level : 'No Data',
            'unit' => null,
            'note' => $barangay
                ? ($hasData ? 'For '.$barangay.' — a planning guide' : 'Not enough information for '.$barangay.' yet')
                : 'Add your barangay to your profile',
            'trend' => null,
            'status' => ! $hasData ? null : match ($level) {
                'Severe' => 'critical',
                'High' => 'warning',
                'Moderate' => 'warning',
                default => 'good',
            },
            'status_label' => $hasData ? $level : null,
            'href' => '#barangayRiskMap',
        ];
    }

    private function farmerFigures(User $user, ?string $barangay): array
    {
        $kpis = [];

        // Barangay rice yield, latest year on record.
        $years = $barangay ? $this->yieldByYear($barangay) : collect();
        $latest = $years->first();
        $yoy = $barangay ? $this->yieldYoy($barangay) : null;
        $kpis[] = [
            'key' => 'barangay-yield',
            'label' => 'Barangay Rice Yield',
            'value' => $latest && $latest['yield'] ? number_format($latest['yield'], 2) : '—',
            'unit' => $latest && $latest['yield'] ? 't/ha' : null,
            'note' => $latest
                ? 'Harvest records for '.$latest['year'].' · '.number_format($latest['area'], 1).' ha'
                : 'No harvest records for your barangay yet',
            'trend' => $yoy ? ['delta' => $yoy['delta_percent'], 'label' => 'vs '.$yoy['previous_year'], 'good' => 'up'] : null,
            'status' => null,
            'status_label' => null,
            'href' => null,
        ];

        // Advisories currently active for this farmer's barangay.
        $activeAdvisories = PlantingAdvisory::query()->active()->forBarangay($barangay);
        $activeAdvisoryCount = (clone $activeAdvisories)->count();
        $rated = \App\Support\SchemaCache::hasTable('advisory_feedback')
            ? AdvisoryFeedback::query()
                ->where('user_id', $user->id)
                ->whereIn('planting_advisory_id', (clone $activeAdvisories)->select('id'))
                ->whereNotNull('is_helpful')
                ->count()
            : 0;
        $kpis[] = [
            'key' => 'advisories',
            'label' => 'Active Advisories',
            'value' => number_format($activeAdvisoryCount),
            'unit' => null,
            'note' => $activeAdvisoryCount > 0
                ? 'You rated '.$rated.' of '.$activeAdvisoryCount
                : 'No advice posted for your area right now',
            'trend' => null,
            'status' => null,
            'status_label' => null,
            'href' => route('planting-advisories.index'),
        ];

        // Farmer's own planting schedules.
        if (\App\Support\SchemaCache::hasTable('farmer_planting_schedules')) {
            $schedules = FarmerPlantingSchedule::query()->where('user_id', $user->id)->get();
            $open = $schedules->reject(fn (FarmerPlantingSchedule $s) => $s->monitoringStatus() === 'harvested');
            $nextHarvest = $open
                ->map(fn (FarmerPlantingSchedule $s) => $s->harvestDateForPlanning())
                ->filter(fn (?Carbon $date) => $date && $date->gte(\App\Support\BusinessClock::today()))
                ->sort()
                ->first();
            $kpis[] = [
                'key' => 'schedules',
                'label' => 'My Planting Schedules',
                'value' => number_format($open->count()),
                'unit' => null,
                'note' => $nextHarvest
                    ? 'Next harvest: '.$nextHarvest->format('M j, Y').' ('.$nextHarvest->diffForHumans(\App\Support\BusinessClock::today(), ['parts' => 1, 'syntax' => Carbon::DIFF_RELATIVE_TO_NOW]).')'
                    : ($schedules->isEmpty() ? 'No schedule added yet' : 'No upcoming harvest date'),
                'trend' => null,
                'status' => null,
                'status_label' => null,
                'href' => route('calendar.index'),
            ];
        }

        // Unread notifications.
        $unread = UserNotification::query()->where('user_id', $user->id)->where('is_read', false);
        $unreadCount = (clone $unread)->count();
        $unreadWarnings = (clone $unread)->where('type', 'Warning')->count();
        $kpis[] = [
            'key' => 'alerts',
            'label' => 'Unread Alerts',
            'value' => number_format($unreadCount),
            'unit' => null,
            'note' => $unreadWarnings > 0 ? $unreadWarnings.' '.str('warning')->plural($unreadWarnings).' to read' : 'No unread warnings',
            'trend' => null,
            'status' => $unreadWarnings > 0 ? 'warning' : null,
            'status_label' => $unreadWarnings > 0 ? 'Check now' : null,
            'href' => route('notifications.index'),
        ];

        return $kpis;
    }

    /* ------------------------------------------------------------------
     | MAO — municipality-wide
     * ------------------------------------------------------------------ */

    public function mao(): array
    {
        return \App\Support\StaleCache::scoped('mao-dashboard', fn (string $token): string => 'dashboard:kpi:mao:'.$token, self::CACHE_SECONDS, function (): array {
            $totalBarangays = count(LianBarangays::coordinates());
            $kpis = [];

            // Rice production + yield for the latest year only — summing
            // every year on record would double-count hectares.
            $latest = $this->yieldByYear()->first();
            $yoy = $this->yieldYoy();
            $kpis[] = [
                'key' => 'rice-production',
                'label' => 'Rice Production'.($latest ? ' ('.$latest['year'].')' : ''),
                'value' => $latest ? number_format($latest['production'], 1) : '—',
                'unit' => $latest ? 'tons' : null,
                'note' => $latest
                    ? $latest['barangays']->count().' of '.$totalBarangays.' barangays reported · '.number_format($latest['area'], 1).' ha'
                    : 'No harvest records yet',
                'trend' => null,
                'status' => null,
                'status_label' => null,
                'href' => route('rice-productions.index'),
            ];
            $kpis[] = [
                'key' => 'yield',
                'label' => 'Average Yield'.($latest ? ' ('.$latest['year'].')' : ''),
                'value' => $latest && $latest['yield'] ? number_format($latest['yield'], 2) : '—',
                'unit' => $latest && $latest['yield'] ? 't/ha' : null,
                'note' => $yoy
                    ? 'Same '.$yoy['barangays_compared'].' '.str('barangay')->plural($yoy['barangays_compared']).' compared with '.$yoy['previous_year']
                    : 'Weighted by area · no earlier year with the same barangays to compare',
                'trend' => $yoy ? ['delta' => $yoy['delta_percent'], 'label' => 'vs '.$yoy['previous_year'], 'good' => 'up'] : null,
                'status' => null,
                'status_label' => null,
                'href' => route('rice-productions.index'),
            ];

            // Farm risk across the 19 barangays.
            $areas = $this->data->currentRiskRatings();
            $highRisk = $areas->whereIn('risk_level', ['High', 'Severe'])->count();
            $severe = $areas->where('risk_level', 'Severe')->count();
            $current = $areas->filter(fn (HeatmapArea $area) => $area->isCurrentlyComputed())->count();
            $kpis[] = [
                'key' => 'high-risk',
                'label' => 'Barangays at High Risk',
                'value' => number_format($highRisk),
                'unit' => 'of '.$totalBarangays,
                'note' => $current.' of '.$totalBarangays.' ratings updated in the last '.HeatmapArea::CURRENT_WINDOW_HOURS.' hours',
                'trend' => null,
                'status' => $severe > 0 ? 'critical' : ($highRisk > 0 ? 'warning' : ($current > 0 ? 'good' : null)),
                'status_label' => $severe > 0 ? $severe.' severe' : ($highRisk > 0 ? 'Watch' : ($current > 0 ? 'Normal' : null)),
                'href' => '#barangayRiskMap',
            ];

            // Every count below in one statement (Performance Phase 4; same queries, same values).
            $feedbackReady = \App\Support\SchemaCache::hasTable('advisory_feedback');
            $ratings = $feedbackReady ? AdvisoryFeedback::query()->whereNotNull('is_helpful') : null;
            $activeFarmers = User::query()->where('role', User::ROLE_FARMER)->where('status', User::STATUS_ACTIVE);
            $counts = Aggregates::fetch([
                'active_advisories' => Aggregates::count(PlantingAdvisory::query()->active()),
                'pending' => Aggregates::count(PlantingAdvisory::query()->pendingReview()),
                'open' => Aggregates::count(FieldReport::query()->where('status', '!=', FieldReport::STATUS_RESOLVED)),
                'new30' => Aggregates::count(FieldReport::query()->where('created_at', '>=', now()->subDays(30))),
                'new_prior30' => Aggregates::count(FieldReport::query()->whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])),
                ...($ratings ? [
                    'rated' => Aggregates::count(clone $ratings),
                    'helpful' => Aggregates::count((clone $ratings)->where('is_helpful', true)),
                    'awaiting' => Aggregates::count((clone $ratings)->whereNull('mao_response')),
                ] : []),
                'active_farmers' => Aggregates::count(clone $activeFarmers),
                'with_profile' => Aggregates::count((clone $activeFarmers)->whereHas('farmerProfile')),
            ]);

            // Advisory pipeline.
            $activeAdvisories = $counts['active_advisories'];
            $pending = $counts['pending'];
            $kpis[] = [
                'key' => 'advisories',
                'label' => 'Active Advisories',
                'value' => number_format($activeAdvisories),
                'unit' => null,
                'note' => $pending > 0 ? $pending.' waiting for review' : 'Nothing waiting for review',
                'trend' => null,
                'status' => $pending > 0 ? 'warning' : null,
                'status_label' => $pending > 0 ? 'Review' : null,
                'href' => route('planting-advisories.index'),
            ];

            // Field reports: open queue + 30-day resolution. MAO/IT – Naicel: only while the pages are shown (FIELD_REPORTS_UI).
            if (\App\Http\Middleware\FieldReportsUi::enabled()) {
                $open = $counts['open'];
                $new30 = $counts['new30'];
                $newPrior30 = $counts['new_prior30'];
                $resolvedRecently = FieldReport::query()
                    ->where('status', FieldReport::STATUS_RESOLVED)
                    ->whereNotNull('resolved_at')
                    ->where('resolved_at', '>=', now()->subDays(30))
                    ->get(['created_at', 'resolved_at']);
                $avgDays = $resolvedRecently->isNotEmpty()
                    ? $resolvedRecently->avg(fn (FieldReport $report) => $report->created_at->diffInHours($report->resolved_at) / 24)
                    : null;
                $kpis[] = [
                    'key' => 'field-reports',
                    'label' => 'Open Field Reports',
                    'value' => number_format($open),
                    'unit' => null,
                    'note' => $new30.' new in 30 days'
                        .($avgDays !== null ? ' · resolved in '.number_format($avgDays, 1).' days on average' : ''),
                    'trend' => $newPrior30 > 0
                        ? ['delta' => round((($new30 - $newPrior30) / $newPrior30) * 100, 1), 'label' => 'new vs prior 30 days', 'good' => 'down']
                        : null,
                    'status' => $open > 0 ? 'warning' : null,
                    'status_label' => $open > 0 ? 'Needs review' : null,
                    'href' => route('field-reports.index'),
                ];
            }

            // Farmer feedback on advisories.
            if ($feedbackReady) {
                $ratedCount = $counts['rated'];
                $helpful = $counts['helpful'];
                $awaiting = $counts['awaiting'];
                $kpis[] = [
                    'key' => 'feedback',
                    'label' => 'Advisories Rated Helpful',
                    'value' => $ratedCount > 0 ? number_format(($helpful / $ratedCount) * 100).'%' : '—',
                    'unit' => null,
                    'note' => $ratedCount > 0
                        ? $helpful.' of '.$ratedCount.' ratings · '.$awaiting.' awaiting reply'
                        : 'No farmer ratings yet',
                    'trend' => null,
                    'status' => null,
                    'status_label' => null,
                    'href' => route('planting-advisories.index'),
                ];
            }

            // Registry coverage: active farmer accounts that have a farm profile.
            $activeFarmerCount = $counts['active_farmers'];
            $withProfile = $counts['with_profile'];
            $kpis[] = [
                'key' => 'registry',
                'label' => 'Farmers With Farm Profile',
                'value' => $activeFarmerCount > 0 ? number_format(($withProfile / $activeFarmerCount) * 100).'%' : '—',
                'unit' => null,
                'note' => $withProfile.' of '.$activeFarmerCount.' active farmer accounts',
                'trend' => null,
                'status' => null,
                'status_label' => null,
                'href' => route('farmer-profiles.index'),
            ];

            return $kpis;
        });
    }

    /* ------------------------------------------------------------------
     | IT expert — platform health
     * ------------------------------------------------------------------ */

    public function itExpert(): array
    {
        return \App\Support\StaleCache::scoped('it-dashboard', fn (string $token): string => 'dashboard:kpi:it:'.$token, self::CACHE_SECONDS, function (): array {
            $totalBarangays = count(LianBarangays::coordinates());
            $kpis = [];

            $totalUsers = User::count();
            $active30 = User::query()->where('last_active_at', '>=', now()->subDays(30))->count();
            $onlineNow = User::query()->where('last_active_at', '>=', now()->subMinutes(User::ONLINE_WINDOW_MINUTES))->count();
            $kpis[] = [
                'key' => 'active-users',
                'label' => 'Active Users (30 days)',
                'value' => number_format($active30),
                'unit' => 'of '.number_format($totalUsers),
                'note' => $onlineNow.' online now',
                'trend' => null,
                'status' => null,
                'status_label' => null,
                'href' => route('users.index'),
            ];

            $new30 = User::query()->where('created_at', '>=', now()->subDays(30))->count();
            $newPrior30 = User::query()->whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])->count();
            $kpis[] = [
                'key' => 'new-accounts',
                'label' => 'New Accounts (30 days)',
                'value' => number_format($new30),
                'unit' => null,
                'note' => $newPrior30.' in the 30 days before',
                'trend' => $this->periodTrend($new30, $newPrior30, 'vs prior 30 days', 'up'),
                'status' => null,
                'status_label' => null,
                'href' => route('users.index'),
            ];

            $attempts = PythonService::attemptsToday();
            $failures = PythonService::failuresToday();
            $successRate = $attempts > 0 ? (($attempts - $failures) / $attempts) * 100 : null;
            $kpis[] = [
                'key' => 'ml-success',
                'label' => 'ML Service Success (today)',
                'value' => $successRate !== null ? number_format($successRate, 1).'%' : '—',
                'unit' => null,
                'note' => $attempts > 0 ? ($attempts - $failures).' of '.$attempts.' calls succeeded' : 'No calls yet today',
                'trend' => null,
                'status' => $successRate === null ? null : ($successRate < 70 ? 'critical' : ($successRate < 95 ? 'warning' : 'good')),
                'status_label' => $successRate === null ? null : ($successRate < 70 ? 'Failing' : ($successRate < 95 ? 'Degraded' : 'Healthy')),
                'href' => route('system-health.index'),
            ];

            $current = $this->data->currentRiskRatings()
                ->filter(fn (HeatmapArea $area) => $area->isCurrentlyComputed())->count();
            $riskFailures = BarangayRiskMapService::failuresToday();
            $kpis[] = [
                'key' => 'risk-freshness',
                'label' => 'Heat Map Freshness',
                'value' => number_format($current),
                'unit' => 'of '.$totalBarangays,
                'note' => 'Barangays refreshed in the last '.HeatmapArea::CURRENT_WINDOW_HOURS.' hours'
                    .($riskFailures > 0 ? ' · '.$riskFailures.' '.str('failure')->plural($riskFailures).' today' : ''),
                'trend' => null,
                'status' => $current === $totalBarangays && $riskFailures === 0 ? 'good' : ($current === 0 ? 'critical' : 'warning'),
                'status_label' => $current === $totalBarangays && $riskFailures === 0 ? 'Current' : ($current === 0 ? 'Stale' : 'Partial'),
                'href' => '#barangayRiskMap',
            ];

            $logs7 = SystemLog::query()->where('created_at', '>=', now()->subDays(7))->count();
            $logsPrior7 = SystemLog::query()->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])->count();
            $kpis[] = [
                'key' => 'audit-events',
                'label' => 'Audit Events (7 days)',
                'value' => number_format($logs7),
                'unit' => null,
                'note' => $logsPrior7.' in the 7 days before',
                'trend' => $this->periodTrend($logs7, $logsPrior7, 'vs prior 7 days', null),
                'status' => null,
                'status_label' => null,
                'href' => route('system-logs.index'),
            ];

            $reports30 = Report::query()->realGenerated()->where('created_at', '>=', now()->subDays(30))->count();
            $reportsPrior30 = Report::query()->realGenerated()->whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])->count();
            $kpis[] = [
                'key' => 'reports',
                'label' => 'Reports Generated (30 days)',
                'value' => number_format($reports30),
                'unit' => null,
                'note' => $reportsPrior30.' in the 30 days before',
                'trend' => $this->periodTrend($reports30, $reportsPrior30, 'vs prior 30 days', null),
                'status' => null,
                'status_label' => null,
                'href' => route('reports.index'),
            ];

            $farmerAccounts = User::query()->where('role', User::ROLE_FARMER)->count();
            $profiles = FarmerProfile::query()->whereNotNull('user_id')->distinct()->count('user_id');
            $kpis[] = [
                'key' => 'profile-completion',
                'label' => 'Farmer Profile Completion',
                'value' => $farmerAccounts > 0 ? number_format(min(100, ($profiles / $farmerAccounts) * 100)).'%' : '—',
                'unit' => null,
                'note' => $profiles.' of '.$farmerAccounts.' farmer accounts have a farm profile',
                'trend' => null,
                'status' => null,
                'status_label' => null,
                'href' => route('farmer-profiles.index'),
            ];

            return $kpis;
        });
    }

    /**
     * Percent change between two periods. Null when the prior period is
     * zero — "+∞%" isn't a useful trend, and the note already shows both counts.
     *
     * @param  'up'|'down'|null  $good  which direction is good; null = neutral
     */
    private function periodTrend(int $current, int $prior, string $label, ?string $good): ?array
    {
        if ($prior === 0) {
            return null;
        }

        return [
            'delta' => round((($current - $prior) / $prior) * 100, 1),
            'label' => $label,
            'good' => $good,
        ];
    }
}
