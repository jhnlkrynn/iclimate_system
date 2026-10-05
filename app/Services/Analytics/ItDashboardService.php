<?php

namespace App\Services\Analytics;

use App\Models\AIChat;
use App\Models\AnalyticsRun;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Everything the IT dashboard shows, from ONE place. The page answers four questions:
 * is the system working, is the data fresh and correct, is it secure, and is it being used.
 *
 * Rules this class keeps:
 *   - every figure comes from a query or a real check; when one cannot be computed it is null and the
 *     view prints "Not available" (never a made-up value);
 *   - the System Health card, the status badge in the header and the status cards are all
 *     built from the SAME rows (see services()), so they can never disagree;
 *   - expensive queries are cached for 5 minutes (remember()), the Refresh button bypasses that cache.
 */
final class ItDashboardService
{
    public const TIMEZONE = 'Asia/Manila';

    public const CACHE_SECONDS = 300;

    public const LEVELS = ['ok' => 'Healthy', 'warn' => 'Warning', 'crit' => 'Critical', 'unk' => 'Unknown'];

    /** Plain status words for the System Health card, by level; SERVICE_WORDS overrides them per service. */
    public const STATUS_WORDS = ['ok' => 'Healthy', 'warn' => 'Needs attention', 'crit' => 'Needs attention', 'unk' => 'Not set up yet'];

    public const SERVICE_WORDS = [
        'application' => ['ok' => 'Online'],
        'database' => ['ok' => 'Online', 'warn' => 'Slow', 'crit' => 'Unavailable'],
        'weather' => ['ok' => 'Operational', 'unk' => 'Waiting for first update'],
        'prediction-api' => ['ok' => 'Online'],
        'jobs' => ['ok' => 'Active', 'unk' => 'Not tracked'],
        'scheduler' => ['ok' => 'Active', 'unk' => 'Waiting for first run'],
        'storage' => ['unk' => 'Not available'],
    ];

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
            'kpis' => $this->kpiCards($overall, $services, $roles, $extra, $resources),
            'extra' => $extra,
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
        $cacheKey = 'dashboard:it:v3:'.$key;
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
                $rows = User::query()->selectRaw('role, COUNT(*) as total, SUM(CASE WHEN '.$active.' THEN 1 ELSE 0 END) as active, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as enabled', [$since, $since, User::STATUS_ACTIVE])
                    ->groupBy('role')->get()->keyBy('role');
            } catch (Throwable) {
                return ['state' => 'error'];
            }

            $list = [];
            foreach ([['Farmer', User::ROLE_FARMER], ['MAO Personnel', User::ROLE_MAO], ['IT Expert', User::ROLE_IT_EXPERT]] as [$label, $role]) {
                $total = (int) ($rows[$role]->total ?? 0);
                $act = (int) ($rows[$role]->active ?? 0);
                $enabled = (int) ($rows[$role]->enabled ?? 0);
                $list[] = ['label' => $label, 'role' => $role, 'total' => $total, 'active' => $act, 'pct' => $total > 0 ? round($act / $total * 100, 1) : null,
                    'enabled' => $enabled, 'disabled' => $total - $enabled];
            }
            $total = array_sum(array_column($list, 'total'));
            $act = array_sum(array_column($list, 'active'));
            $enabled = array_sum(array_column($list, 'enabled'));

            return [
                'state' => $total === 0 ? 'empty' : 'ok',
                'rows' => $list,
                'total' => $total,
                'active' => $act,
                'pct' => $total > 0 ? round($act / $total * 100, 1) : null,
                // Account status (users.status). Separate from "active": an enabled account may not have been used lately.
                'enabled' => $enabled,
                'disabled' => $total - $enabled,
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
     * The eight services on the System Health card, most essential first. The header badge and the System, Database
     * and API status cards all read these rows, so they can never disagree.
     *
     * Each row carries a plain status word: a positive one when the check passes (Online, Operational, Active,
     * Healthy), "Needs attention" when a real check failed, and a neutral one when there is nothing to check yet.
     *
     * @return array<int, array<string, mixed>>
     */
    private function services(array $checks, array $pipeline, CarbonImmutable $now): array
    {
        $rows = [];
        $row = function (string $key, string $name, string $level, string $detail, array $extra = []) use (&$rows, $now) {
            $rows[] = ['key' => $key, 'name' => $name, 'level' => $level, 'label' => self::SERVICE_WORDS[$key][$level] ?? self::STATUS_WORDS[$level],
                'detail' => $detail, 'checked_at' => $now] + $extra;
        };
        $map = fn (?string $s) => match ($s) {
            SystemHealthService::HEALTHY => 'ok',
            SystemHealthService::NEEDS_ATTENTION => 'warn',
            default => 'unk',
        };

        // Serving this page proves the application is responding.
        $row('application', 'Application', 'ok', 'The iClimate website is up and responding.');

        $db = $checks['database'] ?? null;
        $ms = $db['details']['Response time'] ?? null;
        $ms = $ms === '0 ms' ? 'under 1 ms' : $ms;   // the check rounds to whole milliseconds
        match (true) {
            $db === null || $db['status'] === SystemHealthService::UNAVAILABLE => $row('database', 'Database', 'crit', 'The database did not answer the last check.'),
            $db['status'] === SystemHealthService::NEEDS_ATTENTION => $row('database', 'Database', 'warn', 'Connected, but answering slowly'.($ms ? ' ('.$ms.')' : '').'.', ['response' => $ms]),
            default => $row('database', 'Database', 'ok', 'Connected and responding'.($ms ? ' in '.$ms : '').'.', ['response' => $ms]),
        };

        // Weather API: judged by the last successful sync.
        $w = collect($pipeline)->firstWhere('key', 'weather');
        if ($w && $w['at']) {
            $row('weather', 'Weather API', $w['level'], 'Live weather was last updated '.$w['at']->format('M j, g:i A').' ('.$w['at']->diffForHumans().').', ['at' => $w['at']]);
        } else {
            $row('weather', 'Weather API', 'unk', 'Live weather will appear here after the first update.');
        }

        $api = $this->predictionApi();
        match ($api['state']) {
            'online' => $row('prediction-api', 'Prediction API', 'ok', 'The forecasting service is answering'.($api['models'] > 0 ? ' with '.$api['models'].' '.($api['models'] === 1 ? 'model' : 'models').' loaded' : '').'.'),
            'offline' => $row('prediction-api', 'Prediction API', 'warn', 'The forecasting service did not answer. Start it so new forecasts can be made.'),
            default => $row('prediction-api', 'Prediction API', 'unk', 'No forecasting service address has been set.'),
        };

        // Background jobs: failed jobs and a backed-up queue are both real problems.
        $jobs = array_filter([$checks['failed-jobs'] ?? null, $checks['queue'] ?? null], fn ($c) => $c && $c['status'] !== SystemHealthService::UNAVAILABLE);
        $bad = collect($jobs)->firstWhere('status', SystemHealthService::NEEDS_ATTENTION);
        $row('jobs', 'Background Jobs', $jobs === [] ? 'unk' : ($bad ? 'warn' : 'ok'), $jobs === [] ? 'Job counts cannot be read on this queue setup.' : (string) ($bad['summary'] ?? 'No failed jobs and nothing is waiting too long.'));

        $sched = $checks['scheduler'] ?? null;
        $row('scheduler', 'Scheduled Tasks', $sched ? $map($sched['status']) : 'unk', match (true) {
            $sched === null => 'The scheduled tasks could not be checked.',
            $sched['status'] === SystemHealthService::HEALTHY => 'Automatic tasks ran recently.',
            $sched['status'] === SystemHealthService::NEEDS_ATTENTION => 'No automatic task has run recently. Check that the scheduler is set up to run.',
            default => 'Automatic tasks will show here after their first run.',
        }, ['evidence' => $sched['details']['Last scheduled automation'] ?? null]);

        $row(...$this->storageRow());
        $row(...$this->backupRow($checks));

        return $rows;
    }

    /** Asks the forecasting (machine-learning) service's own health endpoint; cached with the other blocks. */
    private function predictionApi(): array
    {
        return $this->remember('prediction-api', function (): array {
            $url = rtrim((string) config('services.farming_ai.url'), '/');
            if ($url === '') {
                return ['state' => 'unset', 'models' => 0];
            }
            try {
                $response = Http::connectTimeout(2)->timeout(3)->get($url.'/health');

                return ['state' => $response->successful() ? 'online' : 'offline', 'models' => count((array) $response->json('models_loaded', []))];
            } catch (Throwable) {
                return ['state' => 'offline', 'models' => 0];
            }
        });
    }

    /** @return array{0: string, 1: string, 2: string, 3: string} */
    private function storageRow(): array
    {
        $disk = $this->disk();
        if ($disk === null) {
            return ['storage', 'Storage', 'unk', 'Disk space could not be read on this server.'];
        }
        $level = $disk['pct'] >= self::DISK_CRIT ? 'crit' : ($disk['pct'] >= self::DISK_WARN ? 'warn' : 'ok');

        return ['storage', 'Storage', $level, $disk['pct'].'% of disk space is used, '.$this->bytes($disk['free']).' free.'];
    }

    /**
     * ONE backup state, from the backup file check and the backup verification check together.
     *
     * @return array{0: string, 1: string, 2: string, 3: string, 4: array<string, mixed>}
     */
    private function backupRow(array $checks): array
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
            $detail = 'No backup was found yet. Check that the daily backup is running.';
            $issue = 'overdue';
        }
        if ($verFailed) {
            $level = 'warn';
            $detail = 'The latest backup check did not pass: '.($ver['details']['Reason'] ?? 'unknown reason').'.';
            $issue = 'verification';
        } elseif ($ver && ($ver['details']['Result'] ?? null) === 'Passed' && $level === 'ok') {
            $detail .= ' Last verified '.($ver['details']['Last checked'] ?? '').'.';
        }

        return ['backup', 'Database Backup', $level, $detail, ['issue' => $issue]];
    }

    /**
     * The one status in the header and on the System Status card. "Healthy" when every check passes, "Operational"
     * when the application and database are up but another service wants a look, "Needs attention" otherwise.
     *
     * @return array{level: string, label: string, healthy: int, issues: int, unknown: int, checks: int, text: string}
     */
    private function overall(array $services): array
    {
        $count = fn (string ...$levels) => count(array_filter($services, fn ($s) => in_array($s['level'], $levels, true)));
        $issues = $count('warn', 'crit');
        $coreDown = collect($services)->whereIn('key', ['application', 'database'])->contains('level', 'crit');
        $level = $coreDown ? 'crit' : ($issues > 0 ? 'warn' : 'ok');

        return [
            'level' => $level, 'label' => ['ok' => 'Healthy', 'warn' => 'Operational', 'crit' => 'Needs attention'][$level],
            'healthy' => $count('ok'), 'issues' => $issues, 'unknown' => $count('unk'), 'checks' => count($services),
            'text' => $count('ok').' of '.count($services).' services healthy'.($issues > 0 ? ' · '.$issues.' to review' : ''),
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

    /**
     * The five cards at the top of the page: Total Users, Active Users, System Status, Database Status, API Status.
     * Each has a label, a value, a status level (its colour), one plain line of context and a link to the details.
     * The three status cards repeat the System Health rows; nothing here is computed a second time.
     *
     * @return array<int, array<string, mixed>>
     */
    private function kpiCards(array $overall, array $services, array $roles, array $x, array $resources): array
    {
        $service = fn (string $key) => collect($services)->firstWhere('key', $key);
        $health = self::tabLink('overview', 'itd-health');
        $percent = fn (?float $v) => $v === null ? '0' : rtrim(rtrim(number_format($v, 1), '0'), '.');

        if (! isset($roles['rows'])) {
            $total = ['key' => 'total-users', 'label' => 'Total Users', 'value' => '—', 'unit' => null, 'level' => 'unk', 'note' => 'User accounts could not be counted just now.', 'href' => route('users.index')];
            $active = ['key' => 'active-users', 'label' => 'Active Users', 'value' => '—', 'unit' => null, 'level' => 'unk', 'note' => 'Recent sign-ins could not be counted just now.', 'href' => self::tabLink('users', 'itd-roles')];
        } else {
            $short = [User::ROLE_FARMER => 'farmers', User::ROLE_MAO => 'MAO', User::ROLE_IT_EXPERT => 'IT'];
            $total = ['key' => 'total-users', 'label' => 'Total Users', 'value' => number_format($roles['total']), 'unit' => $roles['total'] === 1 ? 'account' : 'accounts', 'level' => 'ok',
                'note' => collect($roles['rows'])->map(fn ($r) => number_format($r['total']).' '.$short[$r['role']])->implode(' · '), 'href' => route('users.index')];
            $online = ($x['sessions']['state'] ?? null) === 'ok' ? ' · '.number_format($x['sessions']['count']).' online now' : '';
            $active = ['key' => 'active-users', 'label' => 'Active Users', 'value' => number_format($roles['active']), 'unit' => 'in 30 days', 'level' => 'ok',
                'note' => $percent($roles['pct']).'% of all accounts'.$online, 'href' => self::tabLink('users', 'itd-roles')];
        }

        $status = ['key' => 'status', 'label' => 'System Status', 'value' => $overall['label'], 'unit' => null, 'level' => $overall['level'] === 'crit' ? 'warn' : 'ok',
            'note' => $overall['text'], 'href' => $health];

        $db = $service('database');
        $size = $resources['db_size'] ?? null;
        $facts = array_filter([($db['response'] ?? null) ? 'Responds in '.$db['response'] : null, $size !== null ? $this->bytes($size).' stored' : null]);
        $database = ['key' => 'database', 'label' => 'Database Status', 'value' => $db['label'], 'unit' => null, 'level' => $db['level'] === 'ok' ? 'ok' : 'warn',
            'note' => $db['level'] === 'crit' || $facts === [] ? $db['detail'] : implode(' · ', $facts), 'href' => $health];

        // API Status: the two outside services the system talks to. One that is not set up yet is left out of the count.
        $apis = collect(['weather', 'prediction-api'])->map($service)->filter(fn ($s) => $s && $s['level'] !== 'unk');
        $up = $apis->where('level', 'ok')->count();
        $api = ['key' => 'api', 'label' => 'API Status', 'unit' => null, 'href' => $health] + match (true) {
            $apis->isEmpty() => ['value' => 'Not set up yet', 'level' => 'unk', 'note' => 'The weather and prediction services have not been used yet.'],
            $up === $apis->count() => ['value' => 'Online', 'level' => 'ok', 'note' => $apis->pluck('name')->implode(' and ').' '.($up === 1 ? 'is' : 'are').' responding'],
            default => ['value' => 'Needs attention', 'level' => 'warn', 'note' => $up.' of '.$apis->count().' services responding'],
        };

        return [$total, $active, $status, $database, $api];
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
}
