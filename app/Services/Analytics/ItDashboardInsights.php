<?php

namespace App\Services\Analytics;

use App\Models\AIChat;
use App\Models\AnalyticsRun;
use App\Models\ClimateRecord;
use App\Models\ExternalWeatherData;
use App\Models\MonthlyWeatherPrediction;
use App\Models\PlantingAdvisory;
use App\Models\ScheduledTaskRun;
use App\Models\WeatherFetchLog;
use App\Models\RiceProduction;
use App\Models\SystemLog;
use App\Models\User;
use App\Support\SchemaCache;
use App\Support\SystemLogPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;
use Throwable;

/**
 * The IT dashboard's extra KPIs and analytics. Every method reads real data (tables, logs, config) and returns
 * a plain array; a value that cannot be computed is null / state "unavailable", never a made-up number.
 * ItDashboardService caches these for 5 minutes and places them on the page.
 */
final class ItDashboardInsights
{
    private const TZ = ItDashboardService::TIMEZONE;

    private static function local(mixed $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, 'UTC')->timezone(self::TZ);
    }

    /** @return array<int, string> the last $days local dates, oldest first */
    private static function days(int $days): array
    {
        $today = CarbonImmutable::now(self::TZ)->startOfDay();

        return array_map(fn (int $i) => $today->subDays($days - 1 - $i)->toDateString(), range(0, $days - 1));
    }

    // ------------------------------------------------------------------ jobs and scheduler

    /** @return array{state: string, total: int, rows: array<int, array{class: string, count: int, latest: ?CarbonImmutable}>} */
    public function failedJobsByClass(): array
    {
        $table = (string) config('queue.failed.table', 'failed_jobs');
        if (! SchemaCache::hasTable($table)) {
            return ['state' => 'unavailable', 'total' => 0, 'rows' => []];
        }
        try {
            $groups = [];
            foreach (DB::table($table)->orderByDesc('id')->limit(1000)->get(['payload', 'failed_at']) as $job) {
                $class = (string) (json_decode((string) $job->payload, true)['displayName'] ?? 'Unknown job');
                $groups[$class]['count'] = ($groups[$class]['count'] ?? 0) + 1;
                $groups[$class]['latest'] ??= self::local($job->failed_at);
            }
        } catch (Throwable) {
            return ['state' => 'unavailable', 'total' => 0, 'rows' => []];
        }
        uasort($groups, fn ($a, $b) => $b['count'] <=> $a['count']);
        $rows = [];
        foreach (array_slice($groups, 0, 5, true) as $class => $g) {
            $rows[] = ['class' => $class] + $g;
        }

        return ['state' => $rows === [] ? 'empty' : 'ok', 'total' => array_sum(array_column($groups, 'count')), 'rows' => $rows];
    }

    /** UUIDs of failed jobs of one class, for the per-class Retry action. @return array<int, string> */
    public static function failedJobIds(string $class): array
    {
        $table = (string) config('queue.failed.table', 'failed_jobs');

        return DB::table($table)->orderByDesc('id')->limit(1000)->get(['uuid', 'payload'])
            ->filter(fn ($j) => (json_decode((string) $j->payload, true)['displayName'] ?? 'Unknown job') === $class)
            ->pluck('uuid')->filter()->values()->all();
    }

    /**
     * Every scheduled task with its next run (from the schedule) and its latest run (from scheduled_task_runs).
     *
     * @return array{state: string, rows: array<int, array<string, mixed>>}
     */
    public function scheduledTasks(): array
    {
        try {
            \Illuminate\Support\Facades\Artisan::all();      // loads routes/console.php, where the schedule is defined
            $events = Schedule::events();
            $names = array_map(fn ($e) => $e->getSummaryForDisplay(), $events);
            $latest = SchemaCache::hasTable('scheduled_task_runs')
                ? ScheduledTaskRun::query()->whereIn('task_name', $names)->orderByDesc('id')->limit(1000)->get()->unique('task_name')->keyBy('task_name')
                : collect();
            $rows = [];
            foreach ($events as $i => $event) {
                $run = $latest[$names[$i]] ?? null;
                $rows[] = [
                    'name' => self::taskLabel((string) $names[$i]),
                    'cron' => $event->expression,
                    'next' => CarbonImmutable::instance($event->nextRunDate())->timezone(self::TZ),
                    'last' => $run?->started_at ? self::local($run->started_at) : null,
                    'status' => $run?->status,                      // running, succeeded, failed, or null when never recorded
                    'ok' => $run === null ? null : $run->status === ScheduledTaskRun::SUCCEEDED,
                    'summary' => $run?->output_summary,
                ];
            }
        } catch (Throwable) {
            return ['state' => 'unavailable', 'rows' => []];
        }

        return ['state' => $rows === [] ? 'empty' : 'ok', 'rows' => $rows];
    }

    /** "iclimate:backup-database" from the raw command line the scheduler reports (quotes, log redirects and wrappers removed). */
    public static function taskLabel(string $summary): string
    {
        if (preg_match('/artisan[\'"]?\s+([^>;\n]+?)\s*(?:>>|>|;|2>&1|$)/i', $summary, $m)) {
            return trim(str_replace(["'", '"'], '', $m[1]));
        }

        return trim(mb_substr($summary, 0, 80));
    }

    /** Runs and failures of scheduled tasks in the last 24 hours. @return array{runs: int, failed: int} */
    public function taskRunStats(): array
    {
        try {
            if (! SchemaCache::hasTable('scheduled_task_runs')) {
                return ['runs' => 0, 'failed' => 0];
            }
            $q = ScheduledTaskRun::query()->where('started_at', '>=', now()->subDay());

            return ['runs' => (clone $q)->count(), 'failed' => (clone $q)->where('status', ScheduledTaskRun::FAILED)->count()];
        } catch (Throwable) {
            return ['runs' => 0, 'failed' => 0];
        }
    }

    /**
     * Weather provider health over the last 7 days, from weather_fetch_logs: success rate, average response time
     * and the current failure streak (consecutive failures, newest first).
     *
     * @return array{total: int, success: int, rate: ?float, avg_ms: ?int, streak: int, last_error: ?string}
     */
    public function weatherFetchStats(): array
    {
        $empty = ['total' => 0, 'success' => 0, 'rate' => null, 'avg_ms' => null, 'streak' => 0, 'last_error' => null];
        try {
            if (! SchemaCache::hasTable('weather_fetch_logs')) {
                return $empty;
            }
            $logs = WeatherFetchLog::query()->where('started_at', '>=', now()->subDays(7))->orderByDesc('started_at')->orderByDesc('id')->get(['status', 'duration_ms', 'error_message']);
            if ($logs->isEmpty()) {
                return $empty;
            }
            $streak = 0;
            foreach ($logs as $l) {
                if ($l->status !== WeatherFetchLog::FAILED) {
                    break;
                }
                $streak++;
            }
            $success = $logs->where('status', WeatherFetchLog::SUCCESS)->count();

            return [
                'total' => $logs->count(), 'success' => $success, 'rate' => round($success / $logs->count() * 100, 1),
                'avg_ms' => (int) round((float) $logs->whereNotNull('duration_ms')->avg('duration_ms')),
                'streak' => $streak, 'last_error' => $logs->firstWhere('status', WeatherFetchLog::FAILED)?->error_message,
            ];
        } catch (Throwable) {
            return $empty;
        }
    }

    // ------------------------------------------------------------------ application errors (storage/logs)

    /**
     * Counts ERROR / WARNING / CRITICAL entries in the Laravel log, last 24h and 7 days, plus the 5 most repeated
     * error messages. Reads at most the last 1 MB of each of the newest 7 daily log files.
     *
     * @return array<string, mixed>
     */
    public function applicationErrors(): array
    {
        // Daily channel: one file per day. Only the newest 7 are touched, and only the last 1 MB of each.
        $files = collect(File::glob(storage_path('logs/laravel*.log')) ?: [])->sortByDesc(fn ($f) => filemtime($f))->take(7);
        if ($files->isEmpty()) {
            return ['state' => 'empty', 'h24' => $this->levels(), 'd7' => $this->levels(), 'prev24' => 0, 'top' => []];
        }

        $now = CarbonImmutable::now('UTC');
        $h24 = $this->levels();
        $d7 = $this->levels();
        $prev = 0;
        $messages = [];
        foreach ($files as $file) {
            $size = filesize($file);
            $handle = @fopen($file, 'rb');
            if (! $handle) {
                continue;
            }
            $size > 1_000_000 && fseek($handle, -1_000_000, SEEK_END);
            $text = stream_get_contents($handle);
            fclose($handle);
            if (! preg_match_all('/^\[(\d{4}-\d\d-\d\d \d\d:\d\d:\d\d)[^\]]*\] \S+\.(ERROR|WARNING|CRITICAL|ALERT|EMERGENCY): (.*)$/m', (string) $text, $m, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($m as [, $at, $level, $message]) {
                $time = CarbonImmutable::parse($at, config('app.timezone'));
                $age = $time->diffInMinutes($now, false);
                if ($age < 0 || $age > 7 * 1440) {
                    continue;
                }
                $key = match ($level) { 'ERROR' => 'error', 'WARNING' => 'warning', default => 'critical' };
                $d7[$key]++;
                if ($age <= 1440) {
                    $h24[$key]++;
                } elseif ($age <= 2880 && $key !== 'warning') {
                    $prev++;
                }
                if ($key !== 'warning') {
                    $clean = mb_substr(trim(preg_replace('/\{.*$/', '', $message) ?: $message), 0, 140);
                    $messages[$clean] = ($messages[$clean] ?? 0) + 1;
                }
            }
        }
        arsort($messages);
        $top = [];
        foreach (array_slice($messages, 0, 5, true) as $message => $count) {
            $top[] = ['message' => (string) $message, 'count' => $count];
        }

        return ['state' => 'ok', 'h24' => $h24, 'd7' => $d7, 'prev24' => $prev, 'top' => $top];
    }

    /** @return array{error: int, warning: int, critical: int} */
    private function levels(): array
    {
        return ['error' => 0, 'warning' => 0, 'critical' => 0];
    }

    // ------------------------------------------------------------------ data pipelines

    /**
     * Records added per day over the last 7 days for each source (by created_at).
     *
     * @return array<string, array{dates: array<int, string>, counts: array<int, int>, total: int}>
     */
    public function recordsPerDay(int $days = 7): array
    {
        $dates = self::days($days);
        $from = CarbonImmutable::parse($dates[0], self::TZ)->utc();
        $sources = [
            'weather' => [ExternalWeatherData::query()->where('source', 'Open-Meteo'), 'created_at'],
            'climate' => [ClimateRecord::query(), 'created_at'],
            'rice' => [RiceProduction::query(), 'created_at'],
            'predictions' => [MonthlyWeatherPrediction::query(), 'created_at'],
            'advisories' => [PlantingAdvisory::query(), 'created_at'],
        ];
        $out = [];
        foreach ($sources as $key => [$query, $col]) {
            $counts = array_fill_keys($dates, 0);
            try {
                foreach ($query->where($col, '>=', $from)->pluck($col) as $at) {
                    $d = self::local($at)->toDateString();
                    isset($counts[$d]) && $counts[$d]++;
                }
                $out[$key] = ['dates' => $dates, 'counts' => array_values($counts), 'total' => array_sum($counts)];
            } catch (Throwable) {
                $out[$key] = ['dates' => $dates, 'counts' => [], 'total' => 0];
            }
        }

        return $out;
    }

    /**
     * Duration of the most recent completed run of each analytics job type.
     *
     * @return array<int, array{type: string, label: string, seconds: int, at: CarbonImmutable}>
     */
    public function lastRunDurations(): array
    {
        $labels = ['dataset_processing' => 'Dataset processing', 'rice_yield_prediction' => 'Rice yield prediction', 'weather_prediction' => 'Weather prediction'];
        $rows = [];
        try {
            foreach ($labels as $type => $label) {
                $run = AnalyticsRun::query()->where('type', $type)->where('status', AnalyticsRun::STATUS_COMPLETED)->whereNotNull('completed_at')->latest('id')->first();
                if ($run) {
                    $rows[] = ['type' => $type, 'label' => $label, 'seconds' => max(0, (int) $run->created_at->diffInSeconds($run->completed_at, false)), 'at' => self::local($run->completed_at)];
                }
            }
        } catch (Throwable) {
            return [];
        }

        return $rows;
    }

    // ------------------------------------------------------------------ database and storage

    /** Top 5 tables by row count (planner statistics on PostgreSQL / MySQL, exact counts on SQLite). @return array<int, array{table: string, rows: int}> */
    public function largestTables(): array
    {
        try {
            $conn = DB::connection();
            $rows = match ($conn->getDriverName()) {
                'pgsql' => collect($conn->select('select relname as t, n_live_tup as n from pg_stat_user_tables order by n_live_tup desc limit 5')),
                'mysql', 'mariadb' => collect($conn->select('select table_name as t, table_rows as n from information_schema.tables where table_schema = database() order by table_rows desc limit 5')),
                'sqlite' => collect($conn->select("select name as t from sqlite_master where type = 'table' and name not like 'sqlite_%'"))
                    ->map(fn ($r) => (object) ['t' => $r->t, 'n' => $conn->table($r->t)->count()])->sortByDesc('n')->take(5),
                default => collect(),
            };

            return $rows->map(fn ($r) => ['table' => (string) $r->t, 'rows' => (int) $r->n])->values()->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Files uploaded to Community Feed and Messages over the last 30 days.
     *
     * @return array<string, mixed>
     */
    public function uploads(int $days = 30): array
    {
        $dates = self::days($days);
        $from = CarbonImmutable::parse($dates[0], self::TZ)->utc();
        $counts = array_fill_keys($dates, 0);
        $feedFiles = $feedBytes = $messageFiles = 0;
        try {
            if (SchemaCache::hasTable('feed_media')) {
                foreach (DB::table('feed_media')->where('created_at', '>=', $from)->get(['created_at', 'size']) as $f) {
                    $feedFiles++;
                    $feedBytes += (int) $f->size;
                    $d = self::local($f->created_at)->toDateString();
                    isset($counts[$d]) && $counts[$d]++;
                }
            }
            if (SchemaCache::hasTable('conversation_messages')) {
                foreach (DB::table('conversation_messages')->whereNotNull('attachment_path')->where('created_at', '>=', $from)->pluck('created_at') as $at) {
                    $messageFiles++;
                    $d = self::local($at)->toDateString();
                    isset($counts[$d]) && $counts[$d]++;
                }
            }
        } catch (Throwable) {
            return ['state' => 'unavailable'];
        }

        return ['state' => $feedFiles + $messageFiles === 0 ? 'empty' : 'ok', 'dates' => $dates, 'counts' => array_values($counts),
            'feed_files' => $feedFiles, 'feed_bytes' => $feedBytes, 'message_files' => $messageFiles];
    }

    // ------------------------------------------------------------------ security

    /**
     * Login success vs failure (7 days, and the 7 days before for the trend), off-hours logins (10 PM-5 AM Manila),
     * top 5 IPs by failed logins, and admin actions by user.
     *
     * @return array<string, mixed>
     */
    public function loginSecurity(): array
    {
        try {
            $now = CarbonImmutable::now('UTC');
            $success = $failed = $prevSuccess = $prevFailed = $offHours = 0;
            $ips = [];
            foreach (SystemLog::query()->where('created_at', '>=', $now->subDays(14))
                ->where(fn ($q) => $q->whereRaw("LOWER(action) IN ('user login', 'login')")->orWhere('action', 'Failed Login'))
                ->get(['action', 'details', 'created_at']) as $log) {
                $current = self::local($log->created_at)->gte($now->subDays(7));
                if ($log->action === 'Failed Login') {
                    $current ? $failed++ : $prevFailed++;
                    if ($current) {
                        $ip = SystemLogPresenter::parse($log->details)['ip'] ?? null;
                        $ip && $ips[$ip] = ($ips[$ip] ?? 0) + 1;
                    }
                } elseif ($current) {
                    $success++;
                    $h = (int) self::local($log->created_at)->format('G');
                    ($h >= 22 || $h < 5) && $offHours++;
                } else {
                    $prevSuccess++;
                }
            }
            arsort($ips);
            $rate = fn (int $s, int $f) => $s + $f > 0 ? round($s / ($s + $f) * 100, 1) : null;

            $admin = SystemLog::query()->where('created_at', '>=', $now->subDays(7))->whereNotNull('user_id')
                ->whereRaw("LOWER(action) NOT IN ('user login', 'login', 'user logout', 'logout')")
                ->selectRaw('user_id, COUNT(*) as c')->groupBy('user_id')->orderByDesc('c')->limit(5)->get();
            $names = User::query()->whereIn('id', $admin->pluck('user_id'))->pluck('name', 'id');

            return [
                'state' => 'ok',
                'success' => $success, 'failed' => $failed, 'rate' => $rate($success, $failed), 'prev_rate' => $rate($prevSuccess, $prevFailed),
                'off_hours' => $offHours,
                'ips' => collect($ips)->take(5)->map(fn ($c, $ip) => ['ip' => (string) $ip, 'count' => $c])->values()->all(),
                'admins' => $admin->map(fn ($r) => ['id' => (int) $r->user_id, 'name' => $names[$r->user_id] ?? 'Deleted user', 'count' => (int) $r->c])->all(),
            ];
        } catch (Throwable) {
            return ['state' => 'error'];
        }
    }

    /**
     * People signed in and active in the last 15 minutes: one per account, however many browsers they use, and
     * never visitors who are not signed in (counting every session row showed more people online than accounts).
     * Only measurable on the database session driver. @return array{state: string, count: ?int, driver: string} */
    public function activeSessions(): array
    {
        $driver = (string) config('session.driver');
        if ($driver !== 'database') {
            return ['state' => 'unavailable', 'count' => null, 'driver' => $driver];
        }
        try {
            $table = (string) config('session.table', 'sessions');

            return ['state' => 'ok', 'driver' => $driver, 'count' => DB::table($table)->whereNotNull('user_id')->where('last_activity', '>=', now()->subMinutes(15)->getTimestamp())->distinct()->count('user_id')];
        } catch (Throwable) {
            return ['state' => 'unavailable', 'count' => null, 'driver' => $driver];
        }
    }

    // ------------------------------------------------------------------ users and adoption

    /**
     * Logins by day of week (0 = Monday) and hour (Manila time) over the last 30 days.
     *
     * @return array{state: string, grid: array<int, array<int, int>>, max: int, total: int, busiest: ?array{day: string, hour: int, count: int}}
     */
    public function loginHeatmap(int $days = 30): array
    {
        $grid = array_fill(0, 7, array_fill(0, 24, 0));
        $total = 0;
        try {
            foreach (SystemLog::query()->where('created_at', '>=', now()->subDays($days))->whereRaw("LOWER(action) IN ('user login', 'login')")->pluck('created_at') as $at) {
                $t = self::local($at);
                $grid[$t->dayOfWeekIso - 1][(int) $t->format('G')]++;
                $total++;
            }
        } catch (Throwable) {
            return ['state' => 'error', 'grid' => $grid, 'max' => 0, 'total' => 0, 'busiest' => null];
        }
        $max = max(array_map('max', $grid));
        $busiest = null;
        foreach ($grid as $d => $hours) {
            foreach ($hours as $h => $c) {
                if ($c === $max && $max > 0 && $busiest === null) {
                    $busiest = ['day' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'][$d], 'hour' => $h, 'count' => $c];
                }
            }
        }

        return ['state' => $total === 0 ? 'empty' : 'ok', 'grid' => $grid, 'max' => $max, 'total' => $total, 'busiest' => $busiest];
    }

    /** New accounts per week for the last 8 weeks (weeks start Monday, Manila time). @return array<string, mixed> */
    public function registrations(int $weeks = 8): array
    {
        $start = CarbonImmutable::now(self::TZ)->startOfWeek()->subWeeks($weeks - 1);
        $counts = [];
        for ($i = 0; $i < $weeks; $i++) {
            $counts[$start->addWeeks($i)->toDateString()] = 0;
        }
        try {
            foreach (User::query()->where('created_at', '>=', $start->utc())->pluck('created_at') as $at) {
                $week = self::local($at)->startOfWeek()->toDateString();
                isset($counts[$week]) && $counts[$week]++;
            }
        } catch (Throwable) {
            return ['state' => 'error', 'weeks' => [], 'counts' => []];
        }

        return ['state' => array_sum($counts) === 0 ? 'empty' : 'ok', 'weeks' => array_keys($counts), 'counts' => array_values($counts), 'total' => array_sum($counts)];
    }

    /** Accounts older than one day that have never logged in nor been used. */
    public function neverLoggedIn(): ?int
    {
        try {
            return User::query()->neverLoggedIn()->count();
        } catch (Throwable) {
            return null;
        }
    }

    // ------------------------------------------------------------------ Climaite AI

    /** Queries per day for the last 7 days. @return array<string, mixed> */
    public function aiTrend(int $days = 7): array
    {
        $dates = self::days($days);
        $counts = array_fill_keys($dates, 0);
        try {
            foreach (AIChat::query()->where('created_at', '>=', CarbonImmutable::parse($dates[0], self::TZ)->utc())->pluck('created_at') as $at) {
                $d = self::local($at)->toDateString();
                isset($counts[$d]) && $counts[$d]++;
            }
        } catch (Throwable) {
            return ['state' => 'error', 'dates' => $dates, 'counts' => []];
        }

        return ['state' => array_sum($counts) === 0 ? 'empty' : 'ok', 'dates' => $dates, 'counts' => array_values($counts), 'total' => array_sum($counts)];
    }
}
