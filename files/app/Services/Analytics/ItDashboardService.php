<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsRun;
use App\Models\ClimateRecord;
use App\Models\ExternalWeatherData;
use App\Models\FarmerProfile;
use App\Models\MonthlyWeatherPrediction;
use App\Models\RiceProduction;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\FarmerOrganizationSummaryService;
use App\Services\Risk\BarangayRiskMapService;
use App\Services\SystemHealthService;
use App\Support\SampleData;
use App\Support\SystemLogPresenter;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Everything the IT dashboard overview shows, from ONE place, and only what is real and useful.
 *
 * The overview is deliberately calm. It shows a problem only when that problem is VERIFIED:
 *   - a value that is missing, empty, optional, not configured or not implemented is NOT a failure
 *     and is simply left out (its detail stays on the System Health page);
 *   - overall health is Critical only when a core function (the database) fails, and Warning only when
 *     a verified problem exists. It is never "Unknown", and never derived from optional diagnostics.
 * Prediction-service and model diagnostics are intentionally not part of the overview.
 */
final class ItDashboardService
{
    public const TIMEZONE = 'Asia/Manila';

    private const PREDICTION_TYPES = ['rice_yield_prediction', 'weather_prediction'];

    private const LEVEL_LABELS = ['ok' => 'Healthy', 'warn' => 'Warning', 'crit' => 'Critical'];

    /** Checks the overview needs. The scheduler check is used only as evidence, never displayed. */
    private const CHECKS = ['database', 'weather', 'failed-jobs', 'queue', 'backup', 'backup-verification', 'scheduler'];

    public function __construct(
        private readonly SystemHealthService $health,
        private readonly FarmerOrganizationSummaryService $farmers,
    ) {}

    // ------------------------------------------------------------------ page payload

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $now = CarbonImmutable::now(self::TIMEZONE);
        $checks = $this->runChecks();

        $users = $this->users();
        $freshness = $this->freshness($checks);
        $services = $this->services($checks, $now);
        $overall = $this->overall($services);

        return [
            'checked_at' => $now,
            'users' => $users,
            'overall' => $overall,
            'services' => $services,
            'kpis' => $this->kpis($users, $overall, $freshness),
            'alerts' => $this->alerts($checks, $services, $now),
            'distribution' => $this->distribution($users),
            'data' => $this->dataStatus(),
            'farmers' => $this->farmerSummary($users),
            'recent' => $this->recentActivity(),
            'reconciliation' => $users['notes'],
        ];
    }

    /** @return array<string, array<string, mixed>> health checks keyed by their key */
    private function runChecks(): array
    {
        try {
            return collect($this->health->only(self::CHECKS))->keyBy('key')->all();
        } catch (Throwable) {
            return [];
        }
    }

    // ------------------------------------------------------------------ users

    /** @return array<string, mixed> */
    private function users(): array
    {
        try {
            $row = User::query()
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active_total', [User::STATUS_ACTIVE])
                ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as inactive_total', [User::STATUS_INACTIVE])
                ->selectRaw('SUM(CASE WHEN role = ? THEN 1 ELSE 0 END) as farmer_total', [User::ROLE_FARMER])
                ->selectRaw('SUM(CASE WHEN role = ? THEN 1 ELSE 0 END) as mao_total', [User::ROLE_MAO])
                ->selectRaw('SUM(CASE WHEN role = ? THEN 1 ELSE 0 END) as it_total', [User::ROLE_IT_EXPERT])
                ->first();
        } catch (Throwable) {
            return ['state' => 'error', 'notes' => []];
        }

        $total = (int) $row->total;
        $active = (int) $row->active_total;
        $inactive = (int) $row->inactive_total;
        $roles = ['farmer' => (int) $row->farmer_total, 'mao' => (int) $row->mao_total, 'it' => (int) $row->it_total];

        // The related numbers must add up to the total; if not, say why instead of hiding it.
        $notes = [];
        if (array_sum($roles) !== $total) {
            $notes[] = ($total - array_sum($roles)).' account(s) have a role other than Farmer, MAO Personnel or IT Expert.';
        }
        if ($active + $inactive !== $total) {
            $notes[] = ($total - $active - $inactive).' account(s) have a status other than active or inactive.';
        }

        return [
            'state' => $total === 0 ? 'empty' : 'ok',
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            // Rounded DOWN so 99.96% never reads as 100%: "100%" only appears when every account is active.
            'active_pct' => $total > 0 ? floor($active / $total * 1000) / 10 : null,
            'roles' => $roles,
            'notes' => $notes,
        ];
    }

    /** @return array<string, mixed> */
    private function distribution(array $users): array
    {
        if (($users['state'] ?? 'error') === 'error') {
            return ['state' => 'error', 'message' => 'Unable to retrieve user counts.'];
        }

        $rows = [
            ['key' => 'farmer', 'label' => 'Farmers', 'count' => $users['roles']['farmer'], 'color' => '#2f9e6e'],
            ['key' => 'mao', 'label' => 'MAO Personnel', 'count' => $users['roles']['mao'], 'color' => '#2b7a9b'],
            ['key' => 'it', 'label' => 'IT Personnel', 'count' => $users['roles']['it'], 'color' => '#d9a21b'],
        ];
        $total = $users['total'];

        return [
            'state' => $total === 0 ? 'empty' : 'ok',
            'total' => $total,
            'rows' => array_map(fn (array $r) => $r + ['pct' => $total > 0 ? round($r['count'] / $total * 100, 1) : null], $rows),
        ];
    }

    // ------------------------------------------------------------------ verified services

    /**
     * Services the application can ACTUALLY verify. A service with no verifiable evidence is left out
     * instead of being shown as unknown or unavailable.
     *
     * @return array<int, array<string, mixed>>
     */
    private function services(array $checks, CarbonImmutable $now): array
    {
        $rows = [];
        $row = function (string $key, string $name, string $level, string $summary) use (&$rows, $now) {
            $rows[] = ['key' => $key, 'name' => $name, 'level' => $level, 'label' => self::LEVEL_LABELS[$level], 'summary' => $summary, 'checked_at' => $now];
        };
        $status = fn (string $key) => $checks[$key]['status'] ?? null;

        // Serving this dashboard is itself proof that the application is responding.
        $row('application', 'Application', 'ok', 'The application is responding.');

        if ($status('database') !== null) {
            $level = match ($status('database')) {
                SystemHealthService::HEALTHY => 'ok',
                SystemHealthService::NEEDS_ATTENTION => 'warn',
                default => 'crit',
            };
            $row('database', 'Database', $level, (string) $checks['database']['summary']);
        }

        $weather = $this->weatherLevel($checks);
        if ($weather !== null) {
            $row('weather', 'Weather Data', $weather, (string) $checks['weather']['summary']);
        }

        $jobs = $this->jobsLevel($checks);
        if ($jobs !== null) {
            $row('jobs', 'Background Jobs', $jobs['level'], $jobs['summary']);
        }

        $backup = $this->backupLevel($checks);
        if ($backup !== null) {
            $row('backup', 'Database Backup', $backup, (string) $checks['backup']['summary']);
        }

        return $rows;
    }

    /** Weather is verifiable only when at least one update was actually saved. */
    private function weatherLevel(array $checks): ?string
    {
        $check = $checks['weather'] ?? null;

        return match ($check['status'] ?? null) {
            SystemHealthService::HEALTHY => 'ok',
            SystemHealthService::NEEDS_ATTENTION => 'warn',
            default => null,   // no update saved yet: nothing to verify, not a failure
        };
    }

    /** @return array{level: string, summary: string}|null */
    private function jobsLevel(array $checks): ?array
    {
        $failed = $checks['failed-jobs'] ?? null;
        $queue = $checks['queue'] ?? null;
        $usable = fn (?array $c) => $c !== null && $c['status'] !== SystemHealthService::UNAVAILABLE;

        if (! $usable($failed) && ! $usable($queue)) {
            return null;
        }
        foreach ([$failed, $queue] as $c) {
            if ($usable($c) && $c['status'] === SystemHealthService::NEEDS_ATTENTION) {
                return ['level' => 'warn', 'summary' => (string) $c['summary']];
            }
        }

        return ['level' => 'ok', 'summary' => 'No failed jobs and nothing is stuck in the queue.'];
    }

    /**
     * A backup is judged only if it is implemented and scheduled (both true in this app: it is scheduled daily),
     * a backup exists, and overdue is provable: either this is production, or the scheduler is evidently
     * running (so a stale backup is a real miss, not just a scheduler that is switched off in development).
     */
    private function backupLevel(array $checks): ?string
    {
        $check = $checks['backup'] ?? null;
        if ($check === null || ($check['details']['Last backup'] ?? null) === 'None found') {
            return null;
        }

        return match ($check['status']) {
            SystemHealthService::HEALTHY => 'ok',
            SystemHealthService::NEEDS_ATTENTION => $this->overdueIsProvable($checks) ? 'warn' : null,
            default => null,
        };
    }

    private function overdueIsProvable(array $checks): bool
    {
        return app()->environment('production')
            || ($checks['scheduler']['status'] ?? null) === SystemHealthService::HEALTHY;
    }

    /** @return array{level: string, label: string, issues: int, checks: int, text: string} */
    private function overall(array $services): array
    {
        $count = fn (string $lvl) => count(array_filter($services, fn ($s) => $s['level'] === $lvl));
        $crit = $count('crit');
        $warn = $count('warn');

        // Critical only when a core function fails. Warning only for a verified problem. Never "unknown".
        $level = $crit > 0 ? 'crit' : ($warn > 0 ? 'warn' : 'ok');

        return [
            'level' => $level,
            'label' => self::LEVEL_LABELS[$level],
            'issues' => $crit + $warn,
            'checks' => count($services),
            'text' => match ($level) {
                'ok' => 'All '.count($services).' verified checks passing',
                'crit' => 'A core function needs immediate attention',
                default => $warn.' verified '.($warn === 1 ? 'issue needs' : 'issues need').' attention',
            },
        ];
    }

    // ------------------------------------------------------------------ freshness + KPIs

    /** @return array<string, mixed> */
    private function freshness(array $checks): array
    {
        try {
            $latest = ExternalWeatherData::query()->latestSuccessful()->first();
        } catch (Throwable) {
            return ['state' => 'error'];
        }

        if (! $latest || ! $latest->fetched_at) {
            return ['state' => 'empty'];     // no record yet: the card is simply not shown
        }

        return [
            'state' => 'ok',
            'at' => CarbonImmutable::instance($latest->fetched_at)->timezone(self::TIMEZONE),
            'level' => $latest->freshnessLabel() === 'fresh' ? 'ok' : 'warn',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function kpis(array $users, array $overall, array $freshness): array
    {
        $cards = [];

        if ($users['state'] === 'error') {
            $cards[] = ['key' => 'users', 'label' => 'Total Users', 'state' => 'error', 'message' => 'Unable to retrieve user counts.', 'level' => null];
            $cards[] = ['key' => 'active', 'label' => 'Active Accounts', 'state' => 'error', 'message' => 'Unable to retrieve user counts.', 'level' => null];
        } else {
            $cards[] = [
                'key' => 'users', 'label' => 'Total Users', 'state' => 'ok', 'level' => null,
                'tip' => 'All registered accounts. Farmers, MAO Personnel and IT Personnel add up to this total.',
                'value' => number_format($users['total']), 'caption' => 'Registered Users',
                'sub' => number_format($users['roles']['farmer']).' Farmers • '.number_format($users['roles']['mao']).' MAO • '.number_format($users['roles']['it']).' IT',
                'href' => route('users.index'),
            ];
            $cards[] = [
                'key' => 'active', 'label' => 'Active Accounts', 'state' => 'ok',
                // Amber only when some accounts are inactive; a zero-inactive count is normal, never red.
                'level' => $users['total'] > 0 && $users['inactive'] > 0 ? 'warn' : ($users['total'] > 0 ? 'ok' : null),
                'tip' => 'Accounts whose status is active. The percentage is rounded down, so 100% appears only if every account is active.',
                'value' => number_format($users['active']), 'caption' => $users['active_pct'] === null ? 'No accounts yet' : rtrim(rtrim(number_format($users['active_pct'], 1), '0'), '.').'% Active',
                'sub' => number_format($users['inactive']).' Inactive',
                'href' => route('users.index'),
            ];
        }

        $cards[] = [
            'key' => 'health', 'label' => 'System Health', 'state' => 'ok', 'level' => $overall['level'],
            'tip' => 'Overall status from verified checks only: application, database, weather data, background jobs and backups. Optional or unconfigured features are not counted.',
            'value' => $overall['label'], 'caption' => 'System Health', 'sub' => $overall['text'],
            'href' => route('system-health.index'),
        ];

        if ($freshness['state'] === 'ok') {
            $cards[] = [
                'key' => 'freshness', 'label' => 'Data Freshness', 'state' => 'ok', 'level' => $freshness['level'],
                'tip' => 'Time since the latest successful climate/weather data update.',
                'value' => 'Updated '.$freshness['at']->diffForHumans(['parts' => 1]), 'caption' => 'Latest climate/weather update',
                'sub' => $freshness['at']->format('M j, Y g:i A'), 'href' => route('climate-records.index'), 'compact' => true,
            ];
        } elseif ($freshness['state'] === 'error') {
            $cards[] = ['key' => 'freshness', 'label' => 'Data Freshness', 'state' => 'error', 'message' => 'Unable to retrieve data freshness.', 'level' => null];
        }
        // "empty" (no weather update ever saved) is neither a failure nor useful to show: the card is left out.

        return $cards;
    }

    // ------------------------------------------------------------------ attention required

    /**
     * Only REAL, verified problems, critical first. Missing records, optional or unconfigured features and
     * prediction-service diagnostics are not alerts here (they remain on the System Health page).
     *
     * @return array<int, array<string, mixed>>
     */
    private function alerts(array $checks, array $services, CarbonImmutable $now): array
    {
        $alerts = [];
        $add = function (string $severity, string $title, string $message, string $action, string $href, ?CarbonInterface $at = null) use (&$alerts, $now) {
            $alerts[] = compact('severity', 'title', 'message', 'action', 'href') + ['at' => $at ?? $now];
        };
        $health = route('system-health.index');
        $service = fn (string $key) => collect($services)->firstWhere('key', $key);

        if (($s = $service('database')) && $s['level'] !== 'ok') {
            $add($s['level'] === 'crit' ? 'critical' : 'warning', $s['level'] === 'crit' ? 'Database Connection Problem' : 'Database Responding Slowly', $s['summary'], 'View Details', $health);
        }

        foreach (['failed-jobs' => 'Failed Jobs Need Review', 'queue' => 'Queue Jobs Are Waiting'] as $key => $title) {
            if (($checks[$key]['status'] ?? null) === SystemHealthService::NEEDS_ATTENTION) {
                $add('warning', $title, (string) $checks[$key]['summary'], 'View Details', $health);
            }
        }

        if (($s = $service('weather')) && $s['level'] === 'warn') {
            $add('warning', 'Climate Data Outdated', $s['summary'], 'View Details', $health);
        }

        if (($s = $service('backup')) && $s['level'] === 'warn') {
            $add('warning', 'Database Backup Overdue', $s['summary'], 'Check Backup', $health);
        }

        // Only a verification that ran and FAILED is a problem; "never verified" is not.
        $verification = $checks['backup-verification'] ?? null;
        if (($verification['status'] ?? null) === SystemHealthService::NEEDS_ATTENTION && ($verification['details']['Result'] ?? null) === 'Failed') {
            $add('warning', 'Backup Verification Failed', (string) $verification['summary'], 'Check Backup', $health);
        }

        if ($run = $this->problemDatasetRun()) {
            $add('warning', $run['title'], $run['message'], 'View Details', $health, $run['at']);
        }

        try {
            $riskFailures = BarangayRiskMapService::failuresToday();
            if ($riskFailures > 0) {
                $add('warning', $riskFailures.' risk-map refresh '.str('failure')->plural($riskFailures).' today', 'One or more barangays failed to recompute risk. Check storage/logs/laravel.log.', 'View Logs', route('system-logs.index'));
            }
        } catch (Throwable) {
            // The counter is optional; its absence is not an alert.
        }

        usort($alerts, fn ($a, $b) => ($a['severity'] === 'critical' ? 0 : 1) <=> ($b['severity'] === 'critical' ? 0 : 1));

        return $alerts;
    }

    /**
     * Dataset processing is a problem only when its latest run FAILED or is stuck. No run at all,
     * or an old successful run, is normal and is not reported.
     *
     * @return array{title: string, message: string, at: CarbonImmutable}|null
     */
    private function problemDatasetRun(): ?array
    {
        try {
            $run = AnalyticsRun::query()->where('type', 'dataset_processing')->latest('created_at')->first();
        } catch (Throwable) {
            return null;
        }
        if (! $run) {
            return null;
        }

        $at = CarbonImmutable::instance($run->created_at)->timezone(self::TIMEZONE);

        return match (true) {
            $run->status === AnalyticsRun::STATUS_FAILED => ['title' => 'Dataset Processing Failed', 'message' => 'The latest dataset processing did not finish. Details are in the application log.', 'at' => $at],
            in_array($run->status, [AnalyticsRun::STATUS_PENDING, AnalyticsRun::STATUS_PROCESSING], true) && $at->lt(now()->subMinutes(SystemHealthService::ANALYTICS_STUCK_MINUTES))
                => ['title' => 'Dataset Processing Stuck', 'message' => 'Dataset processing has been waiting too long. Check the queue worker.', 'at' => $at],
            default => null,
        };
    }

    // ------------------------------------------------------------------ data status

    /**
     * Only datasets that actually hold usable records. Empty ones are left out (no empty cards), and
     * failures are never mixed in here: this shows saved data, not API requests or errors.
     *
     * @return array<int, array<string, mixed>>
     */
    private function dataStatus(): array
    {
        $items = [];
        $parse = fn ($v) => $v ? CarbonImmutable::parse($v)->timezone(self::TIMEZONE) : null;
        $guard = function (string $key, string $title, callable $build) use (&$items) {
            try {
                if ($item = $build()) {
                    $items[] = ['key' => $key, 'title' => $title, 'state' => 'ok'] + $item;
                }
            } catch (Throwable) {
                $items[] = ['key' => $key, 'title' => $title, 'state' => 'error', 'message' => 'Unable to retrieve '.strtolower($title).'.'];
            }
        };
        $records = fn (int $n) => number_format($n).' '.($n === 1 ? 'record' : 'records');

        $guard('forecasts', 'Weather Forecasts', function () use ($parse, $records) {
            $q = MonthlyWeatherPrediction::query()->where('status', MonthlyWeatherPrediction::STATUS_READY);
            $n = (clone $q)->count();

            return $n === 0 ? null : ['value' => $records($n), 'label' => 'Latest', 'at' => $parse((clone $q)->max('generated_at'))];
        });

        $guard('climate', 'Climate Records', function () use ($parse, $records) {
            $q = SampleData::without(ClimateRecord::query(), 'climate_records');
            $n = (clone $q)->count();

            return $n === 0 ? null : ['value' => $records($n), 'label' => 'Latest', 'at' => $parse((clone $q)->max('record_date')), 'date_only' => true];
        });

        $guard('rice', 'Rice Production Records', function () use ($parse, $records) {
            $q = SampleData::without(RiceProduction::query(), 'rice_productions');
            $n = (clone $q)->count();

            return $n === 0 ? null : ['value' => $records($n), 'label' => 'Latest update', 'at' => $parse((clone $q)->max('updated_at'))];
        });

        $guard('processing', 'Dataset Processing', function () use ($parse) {
            $run = AnalyticsRun::query()->where('type', 'dataset_processing')->where('status', AnalyticsRun::STATUS_COMPLETED)->latest('id')->first();

            return $run ? ['value' => null, 'label' => 'Latest successful processing', 'at' => $parse($run->completed_at ?? $run->created_at)] : null;
        });

        // Saved, completed prediction results only. Failed API calls are diagnostics, not prediction data.
        $guard('predictions', 'Saved Predictions', function () use ($parse, $records) {
            $q = SampleData::without(AnalyticsRun::query(), 'analytics_runs')->whereIn('type', self::PREDICTION_TYPES)->where('status', AnalyticsRun::STATUS_COMPLETED);
            $n = (clone $q)->count();

            return $n === 0 ? null : ['value' => $records($n), 'label' => 'Latest', 'at' => $parse((clone $q)->max('created_at'))];
        });

        return $items;
    }

    // ------------------------------------------------------------------ farmer database

    /** @return array<string, mixed> */
    private function farmerSummary(array $users): array
    {
        try {
            $summary = $this->farmers->summarize()['farmerOrganizationSummary'];
            // Average over farmers WITH a valid area only; farmers with NULL/0 area are not averaged in.
            $valid = FarmerProfile::query()->whereNotNull('farm_area')->where('farm_area', '>', 0)
                ->selectRaw('COUNT(*) as n, COALESCE(SUM(farm_area), 0) as total')->first();
        } catch (Throwable) {
            return ['state' => 'error', 'message' => 'Unable to retrieve farmer database summary.'];
        }

        $accounts = $users['roles']['farmer'] ?? null;
        $note = ($accounts !== null && $accounts !== $summary['totalFarmers'])
            ? number_format($accounts).' farmer accounts vs '.number_format($summary['totalFarmers']).' farmer profiles: some accounts have no profile yet, or the reverse.'
            : null;

        return [
            'state' => $summary['totalFarmers'] === 0 ? 'empty' : 'ok',
            'total_farmers' => $summary['totalFarmers'],
            'organizations' => $summary['totalOrganizations'],
            'without_organization' => $summary['farmersWithoutOrganization'],
            'farm_area' => $summary['totalFarmArea'],
            'farms_with_area' => (int) $valid->n,
            'average_area' => (int) $valid->n > 0 ? (float) $valid->total / (int) $valid->n : null,
            'note' => $note,
        ];
    }

    // ------------------------------------------------------------------ recent activity

    /** Audit-log entries only (who did what, when). The log has no success flag, so no status is shown. */
    private function recentActivity(): array
    {
        try {
            $rows = SystemLog::query()->with('user:id,name,role')->latest('id')->take(8)->get()->map(function (SystemLog $log): array {
                $p = SystemLogPresenter::present($log);

                return ['at' => $log->created_at, 'user' => $p['user'], 'role' => $p['role'], 'action' => $p['action']];
            });
        } catch (Throwable) {
            return ['state' => 'error', 'message' => 'Unable to retrieve recent activity.', 'rows' => []];
        }

        return ['state' => $rows->isEmpty() ? 'empty' : 'ok', 'rows' => $rows->all()];
    }

    // ------------------------------------------------------------------ activity chart

    /**
     * Daily counts per kind of event. Each series counts one kind only; a series with no events in the
     * selected period is left out. Failed runs are not charted (they are not reliably recorded as events).
     *
     * @return array<string, mixed>
     */
    public function activity(int $days): array
    {
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;

        return Cache::remember('dashboard:it:activity:v2:'.$days, now()->addSeconds(120), function () use ($days): array {
            try {
                return $this->buildActivity($days);
            } catch (Throwable) {
                return ['state' => 'error', 'days' => $days, 'message' => 'Unable to retrieve system activity.'];
            }
        });
    }

    private function buildActivity(int $days): array
    {
        $from = \App\Support\BusinessClock::today()->subDays($days - 1);
        $keys = [];
        $labels = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i);
            $keys[] = $day->toDateString();
            $labels[] = $day->format('M j');
        }
        $blank = fn () => array_fill_keys($keys, 0);

        $series = [
            'logins' => ['label' => 'Logins', 'color' => '#2f9e6e', 'data' => $blank()],
            'accounts' => ['label' => 'Account changes', 'color' => '#2b7a9b', 'data' => $blank()],
            'admin' => ['label' => 'Administrative actions', 'color' => '#d9a21b', 'data' => $blank()],
            'processing' => ['label' => 'Data processing', 'color' => '#7a6cc9', 'data' => $blank()],
            'jobs' => ['label' => 'System jobs', 'color' => '#6b7c72', 'data' => $blank()],
        ];

        // Audit log: logins, and everything else except logouts.
        $logs = SystemLog::query()->where('created_at', '>=', $from)
            ->selectRaw('' . \App\Support\BusinessClock::sqlDate('created_at') . ' as d, action, COUNT(*) as c')->groupBy('d', 'action')->get();
        $otherActions = $blank();
        foreach ($logs as $row) {
            $day = (string) $row->d;
            if (! isset($otherActions[$day])) {
                continue;
            }
            $action = (string) $row->action;
            if (SystemLogPresenter::isLogin($action)) {
                $series['logins']['data'][$day] += (int) $row->c;
            } elseif (SystemLogPresenter::actionKind($action) !== 'logout') {
                $otherActions[$day] += (int) $row->c;
            }
        }

        $accountRows = SystemLog::query()->where('created_at', '>=', $from)->where('details', 'like', '%"record_type":"User"%')
            ->selectRaw('' . \App\Support\BusinessClock::sqlDate('created_at') . ' as d, COUNT(*) as c')->groupBy('d')->get();
        foreach ($accountRows as $row) {
            if (isset($series['accounts']['data'][$row->d])) {
                $series['accounts']['data'][$row->d] += (int) $row->c;
            }
        }
        // Administrative actions = the other audit entries, not counting account changes twice.
        foreach ($otherActions as $day => $count) {
            $series['admin']['data'][$day] = max(0, $count - $series['accounts']['data'][$day]);
        }

        $runs = SampleData::without(AnalyticsRun::query(), 'analytics_runs')->where('created_at', '>=', $from)
            ->selectRaw('' . \App\Support\BusinessClock::sqlDate('created_at') . ' as d, type, COUNT(*) as c')->groupBy('d', 'type')->get();
        foreach ($runs as $row) {
            $day = (string) $row->d;
            if (! isset($series['processing']['data'][$day])) {
                continue;
            }
            $bucket = $row->type === 'dataset_processing' ? 'processing' : 'jobs';
            $series[$bucket]['data'][$day] += (int) $row->c;
        }

        $datasets = [];
        foreach ($series as $s) {
            $values = array_values($s['data']);
            if (array_sum($values) > 0) {                   // no all-zero series just to fill the legend
                $datasets[] = ['label' => $s['label'], 'color' => $s['color'], 'data' => $values];
            }
        }

        return [
            'state' => $datasets === [] ? 'empty' : 'ok',
            'days' => $days,
            'labels' => $labels,
            'dates' => $keys,
            'datasets' => $datasets,
            'notes' => [],
        ];
    }
}
