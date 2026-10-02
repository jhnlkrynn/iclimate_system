<?php

namespace App\Services\Analytics;

use App\Models\AIChat;
use App\Models\AnalyticsRun;
use App\Models\BackupVerification;
use App\Models\ClimateRecord;
use App\Models\ExternalWeatherData;
use App\Models\FarmBoundary;
use App\Models\FarmerProfile;
use App\Models\MonthlyWeatherPrediction;
use App\Models\PlantingAdvisory;
use App\Models\RiceProduction;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\SystemHealthService;
use App\Support\BusinessClock;
use App\Support\SampleData;
use App\Support\SchemaCache;
use App\Support\SystemLogPresenter;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Everything the IT dashboard shows, from ONE place. The page answers four questions:
 * is the system working, is the data fresh and correct, is it secure, and is it being used.
 *
 * Rules this class keeps:
 *   - every figure comes from a query or a real check; when one cannot be computed it is null and the
 *     view prints "Not available" (never a made-up value);
 *   - the System Health table, the Attention Required list, the status badge and the KPI cards are all
 *     built from the SAME rows (see services()), so they can never disagree;
 *   - expensive queries are cached for 5 minutes (remember()), the Refresh button bypasses that cache.
 */
final class ItDashboardService
{
    public const TIMEZONE = 'Asia/Manila';

    public const CACHE_SECONDS = 300;

    public const LEVELS = ['ok' => 'Healthy', 'warn' => 'Warning', 'crit' => 'Critical', 'unk' => 'Unknown'];

    /** Freshness targets, in hours, for each data pipeline (shown on the page next to the real age). */
    public const TARGET_HOURS = ['weather' => 2, 'climate' => 24 * 45, 'rice' => 24 * 120, 'predictions' => SystemHealthService::PREDICTION_MAX_AGE_HOURS, 'advisories' => 24 * 14];

    private const PREDICTION_TYPES = ['rice_yield_prediction', 'weather_prediction'];

    private const CHECKS = ['database', 'weather', 'failed-jobs', 'queue', 'backup', 'backup-verification', 'scheduler', 'analytics', 'prediction'];

    /** Disk usage thresholds (percent used). */
    private const DISK_WARN = 85;

    private const DISK_CRIT = 95;

    private bool $fresh = false;

    public function __construct(private readonly SystemHealthService $health, private readonly ItDashboardInsights $insights) {}

    // ------------------------------------------------------------------ page payload

    /** @return array<string, mixed> */
    public function payload(bool $fresh = false): array
    {
        $this->fresh = $fresh;
        $this->stamps = [];
        $now = CarbonImmutable::now(self::TIMEZONE);
        $checks = $this->runChecks();

        $roles = $this->roles();
        $pipeline = $this->pipeline();
        $services = $this->services($checks, $pipeline, $now);
        $overall = $this->overall($services);
        $security = $this->security();
        $integrity = $this->integrity($roles);
        $weather = collect($pipeline)->firstWhere('key', 'weather');
        $extra = $this->extra();
        $resources = $this->resources();
        $cards = $this->kpiCards($overall, $pipeline, $roles, $checks, $extra);
        $alerts = $this->alerts($services, $pipeline, $integrity, $now);

        return [
            'checked_at' => $now,
            'weather_synced_at' => $weather['at'] ?? null,
            'overall' => $overall,
            'services' => $services,
            'pipeline' => $pipeline,
            'roles' => $roles,
            'security' => $security,
            'integrity' => $integrity,
            'resources' => $resources,
            'ai' => $this->ai(),
            'kpis' => $cards['primary'],
            'kpis_extra' => $cards['secondary'],
            'extra' => $extra,
            'alerts' => $alerts,
            'attention' => $this->attention($alerts),
            'recent' => $this->recentActivity(),
            'filters' => $this->filterOptions(),
            // When each cached block was built, shown as "Last updated" on its section. Live blocks use the page time.
            'stamps' => $this->stamps + ['now' => $now],
        ];
    }

    /** @var array<string, CarbonImmutable> */
    private array $stamps = [];

    /** Caches a block for 5 minutes together with the time it was built (the section's "Last updated"). */
    private function remember(string $key, callable $build): mixed
    {
        $cacheKey = 'dashboard:it:v2:'.$key;
        if ($this->fresh) {
            Cache::forget($cacheKey);
        }
        $hit = Cache::get($cacheKey);
        if (! is_array($hit) || ! isset($hit['at']) || ! array_key_exists('data', $hit)) {
            $hit = ['at' => now()->getTimestamp(), 'data' => $build()];
            Cache::put($cacheKey, $hit, now()->addSeconds(self::CACHE_SECONDS));
        }
        $this->stamps[$key] = CarbonImmutable::createFromTimestamp($hit['at'], self::TIMEZONE);

        return $hit['data'];
    }

    /** @return array<string, array<string, mixed>> */
    private function runChecks(): array
    {
        try {
            return collect($this->health->only(self::CHECKS))->keyBy('key')->all();
        } catch (Throwable) {
            return [];
        }
    }

    private static function parse(mixed $value): ?CarbonImmutable
    {
        return $value ? CarbonImmutable::parse($value)->timezone(self::TIMEZONE) : null;
    }

    // ------------------------------------------------------------------ users by role

    /**
     * "Active" means a login or authenticated visit in the last 30 days (never "account is enabled").
     * A login is a Logged In audit entry; a visit is users.last_active_at.
     *
     * @return array<string, mixed>
     */
    private function roles(): array
    {
        return $this->remember('roles', function (): array {
            try {
                $since = now()->subDays(30);
                $active = $this->activeSql();
                $rows = User::query()->selectRaw('role, COUNT(*) as total, SUM(CASE WHEN '.$active.' THEN 1 ELSE 0 END) as active', [$since, $since])
                    ->groupBy('role')->get()->keyBy('role');
            } catch (Throwable) {
                return ['state' => 'error'];
            }

            $list = [];
            foreach ([['Farmer', User::ROLE_FARMER], ['MAO Personnel', User::ROLE_MAO], ['IT Expert', User::ROLE_IT_EXPERT]] as [$label, $role]) {
                $total = (int) ($rows[$role]->total ?? 0);
                $act = (int) ($rows[$role]->active ?? 0);
                $list[] = ['label' => $label, 'role' => $role, 'total' => $total, 'active' => $act, 'pct' => $total > 0 ? round($act / $total * 100, 1) : null];
            }
            $total = array_sum(array_column($list, 'total'));
            $act = array_sum(array_column($list, 'active'));

            return [
                'state' => $total === 0 ? 'empty' : 'ok',
                'rows' => $list,
                'total' => $total,
                'active' => $act,
                'pct' => $total > 0 ? round($act / $total * 100, 1) : null,
            ];
        });
    }

    /** SQL for "logged in or visited since ?" (two bindings: the same cut-off twice). */
    private function activeSql(string $users = 'users'): string
    {
        return '('.$users.'.last_active_at >= ? OR EXISTS (SELECT 1 FROM system_logs sl WHERE sl.user_id = '.$users.'.id AND sl.created_at >= ? AND LOWER(sl.action) IN (\'user login\', \'login\')))';
    }

    // ------------------------------------------------------------------ data pipelines

    /**
     * One row per source. A source with no data yet is "Unknown", not a failure.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pipeline(): array
    {
        return $this->remember('pipeline', function (): array {
            $rows = [];
            $add = function (string $key, string $name, int $targetHours, callable $build) use (&$rows) {
                try {
                    $r = $build();
                } catch (Throwable) {
                    $r = ['error' => true];
                }
                $at = $r['at'] ?? null;
                $age = $at ? (int) $at->diffInMinutes(CarbonImmutable::now(), true) : null;
                $level = isset($r['error']) ? 'unk' : ($at === null ? 'unk' : ($age <= $targetHours * 60 ? 'ok' : ($age <= $targetHours * 60 * 3 ? 'warn' : 'crit')));
                $rows[] = [
                    'key' => $key, 'name' => $name, 'at' => $at, 'age_minutes' => $age, 'level' => $level,
                    'processed' => $r['processed'] ?? null, 'rejected' => $r['rejected'] ?? null,
                    'target_hours' => $targetHours, 'date_only' => $r['date_only'] ?? false,
                    'note' => isset($r['error']) ? 'Unable to read this source.' : ($at === null ? 'No successful run recorded yet.' : null),
                ];
            };

            $add('weather', 'Weather Data', self::TARGET_HOURS['weather'], function () {
                $latest = ExternalWeatherData::query()->latestSuccessful()->first();

                return ['at' => $latest?->fetched_at ? self::parse($latest->fetched_at) : null,
                    'processed' => ExternalWeatherData::query()->where('source', 'Open-Meteo')->where('fetched_at', '>=', now()->subDay())->count()];
            });
            $add('climate', 'Climate Records', self::TARGET_HOURS['climate'], function () {
                $q = SampleData::without(ClimateRecord::query(), 'climate_records');

                return ['at' => self::parse((clone $q)->max('updated_at')), 'processed' => (clone $q)->count()];
            });
            $add('rice', 'Rice Production', self::TARGET_HOURS['rice'], function () {
                $q = SampleData::without(RiceProduction::query(), 'rice_productions');

                return ['at' => self::parse((clone $q)->max('updated_at')), 'processed' => (clone $q)->count()];
            });
            $add('predictions', 'Predictions', self::TARGET_HOURS['predictions'], function () {
                $ready = MonthlyWeatherPrediction::query()->where('status', MonthlyWeatherPrediction::STATUS_READY);
                $runs = SampleData::without(AnalyticsRun::query(), 'analytics_runs')->whereIn('type', self::PREDICTION_TYPES);

                return [
                    'at' => self::parse((clone $ready)->max('generated_at')),
                    'processed' => (clone $ready)->count() + (clone $runs)->where('status', AnalyticsRun::STATUS_COMPLETED)->count(),
                    'rejected' => MonthlyWeatherPrediction::query()->where('status', '!=', MonthlyWeatherPrediction::STATUS_READY)->count()
                        + (clone $runs)->where('status', AnalyticsRun::STATUS_FAILED)->count(),
                ];
            });
            $add('advisories', 'Planting Advisories', self::TARGET_HOURS['advisories'], function () {
                $q = PlantingAdvisory::query()->published();

                return ['at' => self::parse((clone $q)->max('created_at')), 'processed' => (clone $q)->count()];
            });

            return $rows;
        });
    }

    // ------------------------------------------------------------------ system health (single source of truth)

    /**
     * The seven services. Alerts, the overall badge and the Database Backup figure all read these rows.
     *
     * @return array<int, array<string, mixed>>
     */
    private function services(array $checks, array $pipeline, CarbonImmutable $now): array
    {
        $rows = [];
        $row = function (string $key, string $name, string $level, string $detail, ?CarbonInterface $checked = null, array $extra = []) use (&$rows, $now) {
            $rows[] = ['key' => $key, 'name' => $name, 'level' => $level, 'label' => self::LEVELS[$level], 'detail' => $detail, 'checked_at' => $checked ?? $now] + $extra;
        };
        $map = fn (?string $s) => match ($s) {
            SystemHealthService::HEALTHY => 'ok',
            SystemHealthService::NEEDS_ATTENTION => 'warn',
            default => 'unk',
        };

        // Serving this page proves the application is responding.
        $row('application', 'Application', 'ok', 'The application is responding.');

        $db = $checks['database'] ?? null;
        $row('database', 'Database', $db ? $map($db['status']) : 'crit', $db ? (string) $db['summary'].($db['details']['Response time'] ?? null ? ' ('.$db['details']['Response time'].')' : '') : 'The database check could not run.');
        if ($db && $db['status'] === SystemHealthService::UNAVAILABLE) {
            $rows[count($rows) - 1]['level'] = 'crit';
            $rows[count($rows) - 1]['label'] = self::LEVELS['crit'];
        }

        // Queue / background jobs: failed jobs and a backed-up queue are both real problems.
        $jobs = array_filter([$checks['failed-jobs'] ?? null, $checks['queue'] ?? null], fn ($c) => $c && $c['status'] !== SystemHealthService::UNAVAILABLE);
        $bad = collect($jobs)->firstWhere('status', SystemHealthService::NEEDS_ATTENTION);
        $row('jobs', 'Queue / Background Jobs', $jobs === [] ? 'unk' : ($bad ? 'warn' : 'ok'), $jobs === [] ? 'Job counts cannot be read on this queue driver.' : (string) ($bad['summary'] ?? 'No failed jobs and nothing is stuck in the queue.'));

        $sched = $checks['scheduler'] ?? null;
        $lastSched = $sched['details']['Last scheduled automation'] ?? null;
        $row('scheduler', 'Scheduler', $sched ? $map($sched['status']) : 'unk', $sched ? (string) $sched['summary'] : 'The scheduler check could not run.', null, ['evidence' => $lastSched]);

        // Weather API: last successful sync + status.
        $w = collect($pipeline)->firstWhere('key', 'weather');
        if ($w && $w['at']) {
            $row('weather', 'Weather API', $w['level'] === 'crit' ? 'crit' : $w['level'], 'Last successful sync '.$w['at']->format('M j, g:i A').' ('.$w['at']->diffForHumans().').', $w['at'], ['at' => $w['at']]);
        } else {
            $row('weather', 'Weather API', 'unk', 'No successful weather sync has been recorded yet.');
        }

        $row(...$this->storageRow($now));
        $row(...$this->backupRow($checks, $now));

        return $rows;
    }

    /** @return array{0: string, 1: string, 2: string, 3: string} */
    private function storageRow(CarbonImmutable $now): array
    {
        $disk = $this->disk();
        if ($disk === null) {
            return ['storage', 'Storage', 'unk', 'Disk usage could not be read.'];
        }
        $level = $disk['pct'] >= self::DISK_CRIT ? 'crit' : ($disk['pct'] >= self::DISK_WARN ? 'warn' : 'ok');

        return ['storage', 'Storage', $level, $disk['pct'].'% of the disk is used ('.$this->bytes($disk['free']).' free).'];
    }

    /**
     * ONE backup state. The "Database Backup" health row, the "Backup Verification Failed" alert and the
     * "Last Verified Backup" KPI are all read from the row built here.
     *
     * @return array{0: string, 1: string, 2: string, 3: string, 4: null, 5: array<string, mixed>}
     */
    private function backupRow(array $checks, CarbonImmutable $now): array
    {
        $file = $checks['backup'] ?? null;
        $ver = $checks['backup-verification'] ?? null;
        $verFailed = ($ver['status'] ?? null) === SystemHealthService::NEEDS_ATTENTION && ($ver['details']['Result'] ?? null) === 'Failed';
        $none = $file === null || ($file['details']['Last backup'] ?? null) === 'None found';

        $level = 'unk';
        $detail = 'No backup has been recorded yet.';
        $issue = null;
        if ($file !== null && ! $none) {
            $stale = $file['status'] === SystemHealthService::NEEDS_ATTENTION;
            // "Overdue" is only provable in production or when the scheduler is evidently running.
            $provable = app()->environment('production') || ($checks['scheduler']['status'] ?? null) === SystemHealthService::HEALTHY;
            $level = $stale ? ($provable ? 'warn' : 'unk') : ($file['status'] === SystemHealthService::HEALTHY ? 'ok' : 'unk');
            $detail = (string) $file['summary'];
            $issue = $stale && $provable ? 'overdue' : null;
        } elseif ($none && app()->environment('production')) {
            $level = 'warn';
            $detail = 'No valid backup was found. Check that the daily backup is running.';
            $issue = 'overdue';
        }
        if ($verFailed) {
            $level = 'warn';
            $detail = 'The latest backup check failed: '.($ver['details']['Reason'] ?? 'unknown reason').'.';
            $issue = 'verification';
        } elseif ($ver && ($ver['details']['Result'] ?? null) === 'Passed' && $level === 'ok') {
            $detail .= ' Last verified '.($ver['details']['Last checked'] ?? '').'.';
        }

        return ['backup', 'Database Backup', $level, $detail, null, ['issue' => $issue]];
    }

    /** @return array{level: string, label: string, issues: int, checks: int, text: string} */
    private function overall(array $services): array
    {
        $count = fn (string $lvl) => count(array_filter($services, fn ($s) => $s['level'] === $lvl));
        $crit = $count('crit');
        $warn = $count('warn');
        $level = $crit > 0 ? 'crit' : ($warn > 0 ? 'warn' : 'ok');

        return [
            'level' => $level, 'label' => self::LEVELS[$level], 'issues' => $crit + $warn, 'checks' => count($services),
            'unknown' => $count('unk'),
            'text' => match (true) {
                $crit + $warn === 0 => 'No issues · '.$count('ok').' of '.count($services).' checks passing',
                default => ($crit + $warn).' '.($crit + $warn === 1 ? 'issue' : 'issues'),
            },
        ];
    }

    // ------------------------------------------------------------------ extra analytics

    /** @return array<string, mixed> all of ItDashboardInsights, cached together for 5 minutes */
    private function extra(): array
    {
        return $this->remember('extra', fn (): array => [
            'failed_jobs' => $this->insights->failedJobsByClass(),
            'scheduler' => $this->insights->scheduledTasks(),
            'task_stats' => $this->insights->taskRunStats(),
            'errors' => $this->insights->applicationErrors(),
            'weather_fetch' => $this->insights->weatherFetchStats(),
            'per_day' => $this->insights->recordsPerDay(),
            'durations' => $this->insights->lastRunDurations(),
            'tables' => $this->insights->largestTables(),
            'uploads' => $this->insights->uploads(),
            'logins' => $this->insights->loginSecurity(),
            'sessions' => $this->insights->activeSessions(),
            'heatmap' => $this->insights->loginHeatmap(),
            'registrations' => $this->insights->registrations(),
            'never' => $this->insights->neverLoggedIn(),
            'ai_trend' => $this->insights->aiTrend(),
        ]);
    }

    // ------------------------------------------------------------------ KPI cards

    private static function tabLink(string $tab, string $anchor): string
    {
        return route('admin.dashboard', ['tab' => $tab]).'#'.$anchor;
    }

    /** @return array{0: string, 1: string} number and unit for an age in minutes, e.g. [37, 'min'] */
    private function ageParts(int $minutes): array
    {
        return match (true) {
            $minutes < 60 => [(string) $minutes, 'min'],
            $minutes < 2880 => [(string) (float) round($minutes / 60, 1), 'h'],
            default => [(string) round($minutes / 1440), 'days'],
        };
    }

    /**
     * Every card has a label, value, unit, status (colour + icon + text), a comparison or target, a plain-language tip
     * and a link to where it can be fixed or investigated. Six cards in the first row, at most four in the second.
     *
     * @return array{primary: array<int, array<string, mixed>>, secondary: array<int, array<string, mixed>>}
     */
    private function kpiCards(array $overall, array $pipeline, array $roles, array $checks, array $x): array
    {
        $card = fn (array $c) => $c + ['unit' => null, 'compare' => null, 'good' => null, 'level' => 'unk', 'href' => null];

        // 1. System status
        $passing = $overall['checks'] - $overall['issues'] - $overall['unknown'];
        $status = $card(['key' => 'status', 'label' => 'System Status', 'value' => $overall['label'], 'level' => $overall['level'],
            'compare' => $passing.' of '.$overall['checks'].' checks passing'.($overall['issues'] ? ' · '.$overall['issues'].' '.($overall['issues'] === 1 ? 'issue' : 'issues') : ''),
            'tip' => 'The worst result of the seven live checks (application, database, jobs, scheduler, weather, storage, backup). Healthy means nothing needs action.',
            'href' => self::tabLink('overview', 'itd-health')]);

        // 2. Data freshness: the time-sensitive source furthest past its target.
        $sources = collect($pipeline)->filter(fn ($p) => $p['age_minutes'] !== null && in_array($p['key'], ['weather', 'predictions'], true));
        $worst = $sources->sortByDesc(fn ($p) => $p['age_minutes'] / ($p['target_hours'] * 60))->first();
        $freshTip = 'How old the newest weather or prediction data is, compared with its target. Old data means farmers and MAO staff see out-of-date forecasts.';
        if ($worst) {
            [$v, $u] = $this->ageParts($worst['age_minutes']);
            $fresh = $card(['key' => 'freshness', 'label' => 'Data Freshness', 'value' => $v, 'unit' => $u.' old', 'level' => $worst['level'],
                'compare' => $worst['name'].' · Target: < '.$this->hours($worst['target_hours']), 'tip' => $freshTip, 'href' => self::tabLink('overview', 'itd-pipeline')]);
        } else {
            $fresh = $card(['key' => 'freshness', 'label' => 'Data Freshness', 'value' => '—', 'compare' => 'No weather or prediction data yet', 'tip' => $freshTip, 'href' => self::tabLink('overview', 'itd-pipeline')]);
        }

        // 3. Last verified backup (same verification check the health table and alerts use).
        $ver = $checks['backup-verification'] ?? null;
        $passed = BackupVerification::query()->where('status', BackupVerification::PASSED)->latest('verified_at')->first();
        $maxHours = app(\App\Services\BackupVerificationService::class)->maxAgeHours();
        $backupTip = 'The last time a database backup was opened and checked to prove it can be restored. An unverified backup may be useless in an emergency.';
        if ($passed) {
            $ageMin = (int) self::parse($passed->verified_at)->diffInMinutes(CarbonImmutable::now(), true);
            [$v, $u] = $this->ageParts($ageMin);
            $failed = ($ver['details']['Result'] ?? null) === 'Failed';
            $backup = $card(['key' => 'backup', 'label' => 'Last Verified Backup', 'value' => $v, 'unit' => $u.' ago', 'level' => $failed || $ageMin > $maxHours * 60 ? 'warn' : 'ok',
                'compare' => ($failed ? 'Latest check failed · ' : '').'Target: within '.$maxHours.'h', 'tip' => $backupTip, 'href' => self::tabLink('overview', 'itd-health')]);
        } else {
            $backup = $card(['key' => 'backup', 'label' => 'Last Verified Backup', 'value' => 'None', 'level' => app()->environment('production') ? 'warn' : 'unk',
                'compare' => 'No backup has passed verification yet', 'tip' => $backupTip, 'href' => self::tabLink('overview', 'itd-health')]);
        }

        // 4. Active users
        $users = ($roles['state'] ?? 'error') === 'ok'
            ? $card(['key' => 'users', 'label' => 'Active Users (30 days)', 'value' => number_format($roles['active']), 'unit' => 'users', 'level' => 'ok',
                'compare' => ($roles['pct'] !== null ? rtrim(rtrim(number_format($roles['pct'], 1), '0'), '.') : '0').'% of '.number_format($roles['total']).' accounts',
                'tip' => 'Accounts that logged in or were used in the last 30 days. A low share can mean people are locked out or not using the system.', 'href' => route('users.index')])
            : $card(['key' => 'users', 'label' => 'Active Users (30 days)', 'value' => '—', 'compare' => 'User counts could not be read', 'tip' => 'Accounts used in the last 30 days.', 'href' => route('users.index')]);

        // 5. Login success rate
        $logins = $x['logins'];
        $loginTip = 'Successful logins as a share of all login attempts in the last 7 days. A sudden drop can mean a password problem or someone guessing passwords.';
        if (($logins['state'] ?? 'error') === 'ok' && $logins['rate'] !== null) {
            $t = $logins['prev_rate'] === null ? null : $this->trend($logins['rate'] - $logins['prev_rate'], 'pts', true);
            $rate = $card(['key' => 'login-rate', 'label' => 'Login Success Rate (7d)', 'value' => rtrim(rtrim(number_format($logins['rate'], 1), '0'), '.'), 'unit' => '%',
                'level' => $logins['rate'] >= 90 ? 'ok' : ($logins['rate'] >= 70 ? 'warn' : 'crit'),
                'compare' => $t ? $t['text'] : number_format($logins['success']).' successful · '.number_format($logins['failed']).' failed', 'good' => $t['good'] ?? null,
                'tip' => $loginTip, 'href' => self::tabLink('security', 'itd-security')]);
        } else {
            $rate = $card(['key' => 'login-rate', 'label' => 'Login Success Rate (7d)', 'value' => '—', 'compare' => 'No login attempts in the last 7 days', 'tip' => $loginTip, 'href' => self::tabLink('security', 'itd-security')]);
        }

        // 6. Application errors
        $errors = $x['errors'];
        $errTip = 'Errors and critical entries written to the application log in the last 24 hours. A rise usually means something broke after a change.';
        if (($errors['state'] ?? '') === 'ok') {
            $n = $errors['h24']['error'] + $errors['h24']['critical'];
            $t = $this->trend($n - $errors['prev24'], '', false);
            $err = $card(['key' => 'app-errors', 'label' => 'Application Errors (24h)', 'value' => number_format($n), 'unit' => $n === 1 ? 'error' : 'errors',
                'level' => $errors['h24']['critical'] > 0 ? 'crit' : ($n > 0 ? 'warn' : 'ok'), 'compare' => $t['text'], 'good' => $t['good'], 'tip' => $errTip, 'href' => self::tabLink('resources', 'itd-ops')]);
        } else {
            $err = $card(['key' => 'app-errors', 'label' => 'Application Errors (24h)', 'value' => '0', 'unit' => 'errors', 'level' => 'ok', 'compare' => 'No application log entries yet', 'tip' => $errTip, 'href' => self::tabLink('resources', 'itd-ops')]);
        }

        // Second row
        $overdue = collect($pipeline)->filter(fn ($p) => in_array($p['level'], ['warn', 'crit'], true));
        $second = [$card(['key' => 'overdue', 'label' => 'Data Sources Overdue', 'value' => (string) $overdue->count(), 'unit' => 'of '.count($pipeline).' sources',
            'level' => $overdue->contains('level', 'crit') ? 'crit' : ($overdue->isNotEmpty() ? 'warn' : 'ok'), 'compare' => 'Target: 0 overdue',
            'tip' => 'Data pipelines whose latest successful run is older than their freshness target. Overdue data means a feed has stopped updating.', 'href' => self::tabLink('overview', 'itd-pipeline')])];

        $sessions = $x['sessions'];
        if ($sessions['state'] === 'ok') {
            $second[] = $card(['key' => 'sessions', 'label' => 'Active Sessions', 'value' => number_format($sessions['count']), 'unit' => $sessions['count'] === 1 ? 'session' : 'sessions', 'level' => 'ok',
                'compare' => 'Active in the last 15 minutes', 'tip' => 'Browsers that used the system in the last 15 minutes. Useful to pick a quiet moment for maintenance.', 'href' => self::tabLink('users', 'itd-usage')]);
        }

        $w = $x['weather_fetch'];
        $weatherTip = 'How often the weather provider answered in the last 7 days. Repeated failures mean forecasts are going stale.';
        $second[] = ($w['total'] ?? 0) > 0
            ? $card(['key' => 'weather-api', 'label' => 'Weather API Success (7d)', 'value' => rtrim(rtrim(number_format($w['rate'], 1), '0'), '.'), 'unit' => '%',
                'level' => $w['streak'] >= 3 ? 'crit' : ($w['streak'] > 0 || $w['rate'] < 90 ? 'warn' : 'ok'),
                'compare' => 'Avg '.number_format($w['avg_ms']).' ms · '.$w['streak'].' failure streak', 'tip' => $weatherTip, 'href' => self::tabLink('overview', 'itd-pipeline')])
            : $card(['key' => 'weather-api', 'label' => 'Weather API Success (7d)', 'value' => '—', 'compare' => 'No fetch attempts logged yet', 'tip' => $weatherTip, 'href' => self::tabLink('overview', 'itd-pipeline')]);

        $ts = $x['task_stats'];
        $taskTip = 'Scheduled tasks (backups, weather refresh, predictions) that failed in the last 24 hours. A failing task means automatic work is not happening.';
        $second[] = ($ts['runs'] ?? 0) > 0
            ? $card(['key' => 'task-failures', 'label' => 'Scheduled Task Failures (24h)', 'value' => number_format($ts['failed']), 'unit' => 'of '.number_format($ts['runs']).' runs',
                'level' => $ts['failed'] > 0 ? 'warn' : 'ok', 'compare' => 'Target: 0 failures', 'tip' => $taskTip, 'href' => self::tabLink('resources', 'itd-ops')])
            : $card(['key' => 'task-failures', 'label' => 'Scheduled Task Failures (24h)', 'value' => '—', 'compare' => 'No scheduled runs recorded yet', 'tip' => $taskTip, 'href' => self::tabLink('resources', 'itd-ops')]);

        return ['primary' => [$status, $fresh, $backup, $users, $rate, $err], 'secondary' => $second];
    }

    /** @return array{text: string, good: bool} a change versus the previous period */
    private function trend(float|int $delta, string $unit, bool $higherIsBetter): array
    {
        if ($delta == 0) {
            return ['text' => '— same as previous period', 'good' => true];
        }

        return ['text' => ($delta > 0 ? '▲ ' : '▼ ').rtrim(rtrim(number_format(abs($delta), 1), '0'), '.').($unit ? ' '.$unit : '').' vs previous period', 'good' => ($delta > 0) === $higherIsBetter];
    }

    // ------------------------------------------------------------------ attention required

    /**
     * Built from the same service rows as the health table, plus overdue pipelines and data-integrity warnings.
     * Sorted Critical first, then Warning. Each alert knows which tab/section shows it (for the summary bar chips).
     *
     * @return array<int, array<string, mixed>>
     */
    private function alerts(array $services, array $pipeline, array $integrity, CarbonImmutable $now): array
    {
        $alerts = [];
        $add = function (string $severity, string $title, string $short, string $message, string $action, string $href, string $tab, string $anchor, ?CarbonInterface $at = null) use (&$alerts, $now) {
            $alerts[] = compact('severity', 'title', 'short', 'message', 'action', 'href', 'tab', 'anchor') + ['at' => $at ?? $now];
        };
        $health = route('system-health.index');

        foreach ($services as $s) {
            if (! in_array($s['level'], ['warn', 'crit'], true)) {
                continue;
            }
            $sev = $s['level'] === 'crit' ? 'critical' : 'warning';
            match ($s['key']) {
                'database' => $add($sev, $sev === 'critical' ? 'Database Connection Problem' : 'Database Responding Slowly', $sev === 'critical' ? 'the database is not connecting' : 'the database is slow', $s['detail'], 'View Details', $health, 'overview', 'itd-health'),
                'jobs' => $add($sev, 'Background Jobs Need Review', 'background jobs need review', $s['detail'], 'View Details', $health, 'overview', 'itd-health'),
                'scheduler' => $add($sev, 'Scheduler Not Running', 'the scheduler is not running', $s['detail'], 'View Details', $health, 'resources', 'itd-ops'),
                'weather' => $add($sev, 'Weather Data Out of Date', 'weather data is '.$this->overdueBy($pipeline, 'weather').' overdue', $s['detail'], 'Retry', $health, 'overview', 'itd-pipeline', $s['at'] ?? null),
                'storage' => $add($sev, 'Disk Space Running Low', 'disk space is running low', $s['detail'], 'View Details', $health, 'resources', 'itd-resources'),
                'backup' => $add($sev, ($s['issue'] ?? null) === 'verification' ? 'Backup Verification Failed' : 'Database Backup Overdue', ($s['issue'] ?? null) === 'verification' ? 'backup verification failed' : 'the database backup is overdue', $s['detail'], 'Check Backup', $health, 'overview', 'itd-health'),
                default => null,
            };
        }

        // Pipelines past their freshness target (weather is already covered by its service row).
        $fix = ['climate' => route('climate-records.index'), 'rice' => route('rice-productions.index'), 'predictions' => route('weather-predictions.index'), 'advisories' => route('management.advisories.index')];
        foreach ($pipeline as $p) {
            if ($p['key'] === 'weather' || ! in_array($p['level'], ['warn', 'crit'], true)) {
                continue;
            }
            $add($p['level'] === 'crit' ? 'critical' : 'warning', $p['name'].' Overdue', strtolower($p['name']).' is '.$this->overdueBy($pipeline, $p['key']).' overdue',
                'Last successful run '.$p['at']->diffForHumans().'; the target is under '.$this->hours($p['target_hours']).'.', 'View Details', $fix[$p['key']] ?? $health, 'overview', 'itd-pipeline', $p['at']);
        }

        if (($integrity['state'] ?? null) === 'ok' && ($integrity['account_gap'] ?? 0) !== 0) {
            $add('warning', 'Farmer Accounts and Profiles Differ', 'farmer accounts and profiles do not match', number_format($integrity['farmer_accounts']).' farmer accounts vs '.number_format($integrity['farmer_profiles']).' farmer profile records.', 'Review', route('users.index', ['integrity' => 'farmers_without_profile']), 'resources', 'itd-integrity');
        }

        usort($alerts, fn ($a, $b) => ($a['severity'] === 'critical' ? 0 : 1) <=> ($b['severity'] === 'critical' ? 0 : 1));

        return $alerts;
    }

    /** "3h" style text for how far a pipeline is past its target. */
    private function overdueBy(array $pipeline, string $key): string
    {
        $p = collect($pipeline)->firstWhere('key', $key);
        if (! $p || $p['age_minutes'] === null) {
            return 'long';
        }
        [$v, $u] = $this->ageParts(max(0, $p['age_minutes'] - $p['target_hours'] * 60));

        return $v.' '.$u;
    }

    /**
     * One plain sentence plus a clickable chip per issue (for the bar at the top of the page).
     *
     * @return array{level: string, text: string, chips: array<int, array<string, string>>}
     */
    private function attention(array $alerts): array
    {
        $n = count($alerts);
        if ($n === 0) {
            return ['level' => 'ok', 'text' => 'Everything is running normally.', 'chips' => []];
        }
        $shown = array_slice(array_column($alerts, 'short'), 0, 3);

        return [
            'level' => collect($alerts)->contains('severity', 'critical') ? 'crit' : 'warn',
            'text' => $n.' '.($n === 1 ? 'issue needs' : 'issues need').' action: '.implode(', ', $shown).($n > 3 ? ' and '.($n - 3).' more' : '').'.',
            'chips' => array_map(fn ($a) => ['label' => $a['title'], 'severity' => $a['severity'], 'tab' => $a['tab'], 'anchor' => $a['anchor']], $alerts),
        ];
    }

    // ------------------------------------------------------------------ security

    /** @return array<string, mixed> each metric is an int, or null when it cannot be computed */
    private function security(): array
    {
        return $this->remember('security', function (): array {
            $count = function (callable $q): ?int {
                try {
                    return (int) $q();
                } catch (Throwable) {
                    return null;
                }
            };
            $logs = fn (string $action, int $days) => $count(fn () => SystemLog::query()->where('action', $action)->where('created_at', '>=', now()->subDays($days))->count());
            $cutoff = now()->subDays(90);
            $private = fn () => SystemLog::query()->where('created_at', '>=', now()->subDays(7))->where(function ($q) {
                foreach (['delete', 'archive', 'export', 'role'] as $word) {
                    $q->orWhereRaw('LOWER(action) LIKE ?', ['%'.$word.'%']);
                }
            });

            return [
                'failed_24h' => $logs('Failed Login', 1),
                'failed_7d' => $logs('Failed Login', 7),
                'lockouts_24h' => $logs('Login Lockout', 1),
                'resets_7d' => SchemaCache::hasTable('password_reset_tokens')
                    ? $count(fn () => DB::table('password_reset_tokens')->where('created_at', '>=', now()->subDays(7))->count()) : null,
                'privileged_7d' => $count(fn () => $private()->count()),
                'dormant' => $count(fn () => User::query()->where('created_at', '<', $cutoff)
                    ->whereRaw('NOT '.$this->activeSql(), [$cutoff, $cutoff])->count()),
            ];
        });
    }

    // ------------------------------------------------------------------ data integrity

    /** @return array<string, mixed> */
    private function integrity(array $roles): array
    {
        return $this->remember('integrity', function (): array {
            try {
                $accounts = User::query()->where('role', User::ROLE_FARMER)->count();
                $profiles = FarmerProfile::query()->count();
                $missing = FarmerProfile::query()->where(function ($q) {
                    $q->whereNull('barangay')->orWhere('barangay', '')->orWhereNull('full_name')->orWhere('full_name', '')
                        ->orWhereNull('farm_area')->orWhere('farm_area', '<=', 0)->orWhereNull('contact_number')->orWhere('contact_number', '');
                })->count();

                return [
                    'state' => 'ok',
                    'farmer_accounts' => $accounts,
                    'farmer_profiles' => $profiles,
                    'account_gap' => $accounts - $profiles,
                    'without_profile' => User::query()->where('role', User::ROLE_FARMER)->whereDoesntHave('farmerProfile')->count(),
                    'without_org' => FarmerProfile::query()->whereNull('farmer_organization_id')->count(),
                    'missing_fields' => $missing,
                    'orphaned' => FarmerProfile::query()->whereHas('user', fn ($q) => $q->where('role', '!=', User::ROLE_FARMER))->count()
                        + FarmBoundary::query()->whereDoesntHave('farmerProfile')->count(),
                ];
            } catch (Throwable) {
                return ['state' => 'error'];
            }
        });
    }

    // ------------------------------------------------------------------ resources

    /** @return array<string, mixed> */
    private function resources(): array
    {
        return $this->remember('resources', function (): array {
            $disk = $this->disk();
            $uploads = null;
            try {
                $dir = storage_path('app/public');
                if (is_dir($dir)) {
                    $uploads = 0;
                    foreach (File::allFiles($dir) as $f) {
                        $uploads += $f->getSize();
                    }
                }
            } catch (Throwable) {
                $uploads = null;
            }

            return [
                'db_size' => $this->databaseSize(),
                'disk' => $disk,
                'uploads' => $uploads,
                'version' => config('app.version') ?: null,
                'deployed_at' => self::parse(config('app.deployed_at') ?: null),
            ];
        });
    }

    /** @return array{pct: float, free: float, total: float}|null */
    private function disk(): ?array
    {
        $total = @disk_total_space(base_path());
        $free = @disk_free_space(base_path());

        return $total && $free !== false ? ['pct' => round(($total - $free) / $total * 100, 1), 'free' => $free, 'total' => $total] : null;
    }

    private function databaseSize(): ?int
    {
        try {
            $conn = DB::connection();

            return match ($conn->getDriverName()) {
                'pgsql' => (int) $conn->selectOne('select pg_database_size(current_database()) as s')->s,
                'mysql', 'mariadb' => (int) $conn->selectOne('select coalesce(sum(data_length + index_length), 0) as s from information_schema.tables where table_schema = database()')->s,
                'sqlite' => ($p = $conn->getDatabaseName()) && is_file($p) ? (int) filesize($p) : null,
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }

    // ------------------------------------------------------------------ Climaite AI

    /** @return array<string, mixed> */
    private function ai(): array
    {
        return $this->remember('ai', function (): array {
            try {
                $today = AIChat::query()->where('created_at', '>=', BusinessClock::todayUtc());

                return [
                    'state' => 'ok',
                    'queries' => (clone $today)->count(),
                    // The assistant records source "System" when it had to decline instead of answering.
                    'failed' => (clone $today)->where('source_type', 'System')->count(),
                    'avg_ms' => ($avg = (clone $today)->whereNotNull('response_time_ms')->avg('response_time_ms')) !== null ? (int) round($avg) : null,
                ];
            } catch (Throwable) {
                return ['state' => 'error'];
            }
        });
    }

    // ------------------------------------------------------------------ recent activity

    /** @return array<string, array<string, string>> */
    private function filterOptions(): array
    {
        return [
            'roles' => [User::ROLE_FARMER => 'Farmer', User::ROLE_MAO => 'MAO Personnel', User::ROLE_IT_EXPERT => 'IT Expert'],
            'actions' => ['login' => 'Logins', 'failed' => 'Failed logins', 'privileged' => 'Privileged actions', 'other' => 'Other'],
        ];
    }

    /**
     * Latest 10 audit entries, optionally filtered. Failed logins and privileged actions are flagged.
     *
     * @param  array{role?: ?string, action?: ?string, from?: ?string, to?: ?string}  $filters
     * @return array<string, mixed>
     */
    public function recentActivity(array $filters = []): array
    {
        try {
            $q = SystemLog::query()->with('user:id,name,role')->latest('id');
            $role = $filters['role'] ?? null;
            if (in_array($role, [User::ROLE_FARMER, User::ROLE_MAO, User::ROLE_IT_EXPERT], true)) {
                $q->where(fn ($w) => $w->where('details', 'like', '%"role":"'.$role.'"%')
                    ->orWhere(fn ($n) => $n->where(fn ($d) => $d->whereNull('details')->orWhere('details', 'not like', '%"role":"%'))
                        ->whereHas('user', fn ($u) => $u->where('role', $role))));
            }
            match ($filters['action'] ?? null) {
                'login' => $q->whereRaw("LOWER(action) IN ('user login', 'login')"),
                'failed' => $q->whereIn('action', ['Failed Login', 'Login Lockout']),
                'privileged' => $q->where(function ($w) {
                    foreach (['delete', 'archive', 'export', 'role'] as $word) {
                        $w->orWhereRaw('LOWER(action) LIKE ?', ['%'.$word.'%']);
                    }
                }),
                'other' => $q->whereRaw("LOWER(action) NOT IN ('user login', 'login', 'failed login', 'login lockout')"),
                default => null,
            };
            foreach (['from' => '>=', 'to' => '<'] as $key => $op) {
                $d = $filters[$key] ?? null;
                if ($d && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false) {
                    $day = CarbonImmutable::parse($d, self::TIMEZONE)->startOfDay();
                    $q->where('created_at', $op, ($key === 'to' ? $day->addDay() : $day)->utc());
                }
            }

            $rows = $q->take(10)->get()->map(function (SystemLog $log): array {
                $p = SystemLogPresenter::present($log);
                $flag = null;
                if (in_array($log->action, ['Failed Login', 'Login Lockout'], true)) {
                    $flag = 'failed';
                } elseif (preg_match('/delet|archiv|export|role/i', $log->action)) {
                    $flag = 'privileged';
                }

                return ['at' => CarbonImmutable::instance($log->created_at)->timezone(self::TIMEZONE), 'user' => $p['user'], 'role' => $p['role'],
                    'action' => $p['action'], 'ip' => $p['technical']['IP address'] ?? '—', 'flag' => $flag];
            });
        } catch (Throwable) {
            return ['state' => 'error', 'message' => 'Unable to retrieve recent activity.', 'rows' => []];
        }

        return ['state' => $rows->isEmpty() ? 'empty' : 'ok', 'rows' => $rows->all()];
    }

    // ------------------------------------------------------------------ activity chart

    /**
     * Daily counts per series (Logins, Admin actions, Data processing, System jobs, Errors).
     *
     * @return array<string, mixed>
     */
    public function activity(int $days): array
    {
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;

        return Cache::remember('dashboard:it:activity:v3:'.$days, now()->addSeconds(self::CACHE_SECONDS), function () use ($days): array {
            try {
                return $this->buildActivity($days);
            } catch (Throwable) {
                return ['state' => 'error', 'days' => $days, 'message' => 'Unable to retrieve system activity.'];
            }
        });
    }

    private function buildActivity(int $days): array
    {
        $from = BusinessClock::today()->subDays($days - 1);
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
            'admin' => ['label' => 'Admin actions', 'color' => '#d9a21b', 'data' => $blank()],
            'processing' => ['label' => 'Data processing', 'color' => '#7a6cc9', 'data' => $blank()],
            'jobs' => ['label' => 'System jobs', 'color' => '#2b7a9b', 'data' => $blank()],
            'errors' => ['label' => 'Errors', 'color' => '#c8483a', 'data' => $blank()],
        ];
        $date = fn (string $col) => BusinessClock::sqlDate($col);
        $bump = function (string $key, ?string $day, int $n) use (&$series) {
            if ($day !== null && isset($series[$key]['data'][$day])) {
                $series[$key]['data'][$day] += $n;
            }
        };

        foreach (SystemLog::query()->where('created_at', '>=', $from)->selectRaw($date('created_at').' as d, action, COUNT(*) as c')->groupBy('d', 'action')->get() as $r) {
            $action = (string) $r->action;
            $kind = match (true) {
                SystemLogPresenter::isLogin($action) => 'logins',
                in_array($action, ['Failed Login', 'Login Lockout'], true) => 'errors',
                SystemLogPresenter::actionKind($action) === 'logout' => null,
                default => 'admin',
            };
            $kind && $bump($kind, (string) $r->d, (int) $r->c);
        }

        foreach (SampleData::without(AnalyticsRun::query(), 'analytics_runs')->where('created_at', '>=', $from)->selectRaw($date('created_at').' as d, type, status, COUNT(*) as c')->groupBy('d', 'type', 'status')->get() as $r) {
            $bump($r->type === 'dataset_processing' ? 'processing' : 'jobs', (string) $r->d, (int) $r->c);
            if ($r->status === AnalyticsRun::STATUS_FAILED) {
                $bump('errors', (string) $r->d, (int) $r->c);
            }
        }

        $failedTable = (string) config('queue.failed.table', 'failed_jobs');
        if (SchemaCache::hasTable($failedTable)) {
            foreach (DB::table($failedTable)->where('failed_at', '>=', $from)->selectRaw($date('failed_at').' as d, COUNT(*) as c')->groupBy('d')->get() as $r) {
                $bump('errors', (string) $r->d, (int) $r->c);
            }
        }

        $datasets = [];
        foreach ($series as $s) {
            $datasets[] = ['label' => $s['label'], 'color' => $s['color'], 'data' => array_values($s['data'])];
        }
        $any = array_sum(array_map(fn ($d) => array_sum($d['data']), $datasets)) > 0;

        return ['state' => $any ? 'ok' : 'empty', 'days' => $days, 'labels' => $labels, 'dates' => $keys, 'datasets' => $datasets];
    }

    // ------------------------------------------------------------------ formatting helpers

    public function bytes(float|int|null $b): string
    {
        if ($b === null) {
            return 'Not available';
        }
        foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $i => $u) {
            if ($b < 1024 || $u === 'TB') {
                return ($i === 0 ? (string) (int) $b : number_format($b, 1)).' '.$u;
            }
            $b /= 1024;
        }

        return '';
    }

    private function minutes(int $m): string
    {
        return match (true) {
            $m < 60 => $m.' min',
            $m < 2880 => (float) round($m / 60, 1).' h',
            default => round($m / 1440).' days',
        };
    }

    private function hours(int $h): string
    {
        return $h < 48 ? $h.'h' : round($h / 24).' days';
    }
}
