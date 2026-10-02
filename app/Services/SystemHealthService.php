<?php

namespace App\Services;

use App\Models\AnalyticsRun;
use App\Models\ExternalWeatherData;
use App\Models\BackupVerification;
use App\Models\MlModel;
use App\Models\MonthlyWeatherPrediction;
use App\Services\MachineLearning\RuntimeModelClient;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Read-only health checks for the IT System Health page.
 *
 * Nothing here runs, trains or calls a model or an external service. It only
 * inspects the database, the queue tables, stored predictions, timestamps,
 * config values and whether model/Python files exist. Every check is wrapped so
 * a failure becomes an "Unavailable" card with a generic message; raw
 * exceptions, credentials, hosts, file paths and .env values are never returned.
 */
class SystemHealthService
{
    public const HEALTHY = 'Healthy';

    public const NEEDS_ATTENTION = 'Needs Attention';

    public const UNAVAILABLE = 'Unavailable';

    /** A query slower than this is reported as Needs Attention. */
    public const DATABASE_SLOW_MS = 1500;

    /** Oldest waiting job older than this means the queue worker is not keeping up. */
    public const QUEUE_OLDEST_WAITING_MINUTES = 15;

    /** More waiting jobs than this also means the queue is backed up. */
    public const QUEUE_WAITING_LIMIT = 100;

    /** iclimate:refresh-predictions runs daily, so allow two days. */
    public const PREDICTION_MAX_AGE_HOURS = 48;

    /** iclimate:automation-run queues dataset processing every 30 minutes. */
    public const ANALYTICS_MAX_AGE_HOURS = 6;

    /** A pending/processing run older than this is treated as stuck. */
    public const ANALYTICS_STUCK_MINUTES = 30;

    /** The scheduler queues automation every 30 minutes; allow three missed runs. */
    public const SCHEDULER_MAX_AGE_MINUTES = 90;

    private const TIMEZONE = 'Asia/Manila';

    /**
     * @return array<int, array{key: string, title: string, status: string, summary: string, details: array<string, string>}>
     */
    public function __construct(private readonly DatabaseBackupService $backups) {}

    public function checks(): array
    {
        return $this->only(array_keys($this->definitions()));
    }

    /**
     * Runs only the named checks (each still guarded), in their usual order, so a caller that needs a
     * few does not pay for all of them.
     *
     * @param  array<int, string>  $keys
     * @return array<int, array{key: string, title: string, status: string, summary: string, details: array<string, string>}>
     */
    public function only(array $keys): array
    {
        $results = [];

        foreach ($this->definitions() as $key => [$title, $check]) {
            if (in_array($key, $keys, true)) {
                $results[] = $this->guard($key, $title(), $check);
            }
        }

        return $results;
    }

    /** @return array<string, array{0: callable(): string, 1: callable(): array}> */
    private function definitions(): array
    {
        return [
            'database' => [fn () => $this->databaseTitle(), fn () => $this->database()],
            'backup' => [fn () => 'Database Backup', fn () => $this->databaseBackup()],
            'backup-verification' => [fn () => 'Backup Verification', fn () => $this->backupVerification()],
            'queue' => [fn () => 'Pending queue jobs', fn () => $this->pendingJobs()],
            'failed-jobs' => [fn () => 'Failed jobs', fn () => $this->failedJobs()],
            'weather' => [fn () => 'Latest weather update', fn () => $this->latestWeather()],
            'prediction' => [fn () => 'Latest monthly prediction', fn () => $this->latestPrediction()],
            'analytics' => [fn () => 'Latest dataset processing', fn () => $this->latestAnalytics()],
            'python' => [fn () => 'Python executable', fn () => $this->python()],
            'weather-model' => [fn () => 'Weather model', fn () => $this->modelFile('weather')],
            'rice-yield-model' => [fn () => 'Rice-yield model', fn () => $this->modelFile('rice_yield')],
            'model-sync' => [fn () => 'Model sync', fn () => $this->modelSync()],
            'scheduler' => [fn () => 'Scheduler freshness', fn () => $this->scheduler()],
        ];
    }
    /**
     * @param  array<int, array{status: string}>  $checks
     * @return array{healthy: int, needs_attention: int, unavailable: int, headline: string}
     */
    public function summarize(array $checks): array
    {
        $count = fn (string $status): int => count(array_filter($checks, fn (array $check): bool => $check['status'] === $status));

        $healthy = $count(self::HEALTHY);
        $attention = $count(self::NEEDS_ATTENTION);
        $unavailable = $count(self::UNAVAILABLE);

        return [
            'healthy' => $healthy,
            'needs_attention' => $attention,
            'unavailable' => $unavailable,
            'headline' => match (true) {
                $attention === 0 && $unavailable === 0 => 'Everything looks healthy.',
                $attention > 0 => $attention.' '.($attention === 1 ? 'check needs' : 'checks need').' attention.',
                default => $unavailable.' '.($unavailable === 1 ? 'check is' : 'checks are').' unavailable.',
            },
        ];
    }

    // ---------------------------------------------------------------- checks

    private function database(): array
    {
        $started = hrtime(true);
        DB::select('select 1');
        $milliseconds = (int) round((hrtime(true) - $started) / 1_000_000);

        $slow = $milliseconds > self::DATABASE_SLOW_MS;

        return $this->result(
            $slow ? self::NEEDS_ATTENTION : self::HEALTHY,
            $slow ? 'The database answered, but slowly.' : 'The database is connected.',
            ['Type' => $this->driverLabel(), 'Response time' => $milliseconds.' ms'],
        );
    }

    /**
     * Only inspects existing backup files. Opening the page never starts a backup.
     */
    private function databaseBackup(): array
    {
        $status = $this->backups->status();

        if ($status['state'] === 'unavailable') {
            return $this->result(self::UNAVAILABLE, 'The backup folder could not be checked right now.');
        }

        if ($status['state'] === 'missing') {
            return $this->result(self::NEEDS_ATTENTION, 'No valid backup was found. Check that the daily backup is running.', [
                'Last backup' => 'None found',
            ]);
        }

        $details = [
            'Last backup' => $this->when($status['last']),
            'Size' => number_format($status['size'] / 1048576, 2).' MB',
            'Backups kept' => (string) $status['count'],
            'Kept for' => $this->backups->retentionDays().' days',
        ];

        return $status['state'] === 'stale'
            ? $this->result(self::NEEDS_ATTENTION, 'The latest backup is older than expected. Check that the daily backup is running.', $details)
            : $this->result(self::HEALTHY, 'A recent backup is available.', $details);
    }

    /**
     * Reads the recorded outcome of iclimate:verify-backup and compares it with
     * the newest backup on disk (by name and size only, so opening the page
     * never hashes a large file, runs pg_restore or writes anything).
     */
    private function backupVerification(): array
    {
        if (! \App\Support\SchemaCache::hasTable('backup_verifications')) {
            return $this->result(self::UNAVAILABLE, 'Backup verification has not been set up yet. Run the database migrations.');
        }

        $latest = BackupVerification::query()->latest('verified_at')->latest('id')->first();

        if ($latest === null) {
            return $this->result(self::UNAVAILABLE, 'No backup has been verified yet.');
        }

        $details = ['Last checked' => $this->when($latest->verified_at)];

        if ($latest->backup_filename !== null) {
            $details['Backup file'] = $latest->backup_filename;
        }

        if ($latest->status !== BackupVerification::PASSED) {
            $details['Result'] = 'Failed';
            $details['Reason'] = (string) ($latest->failure_reason ?? 'Unknown');

            return $this->result(self::NEEDS_ATTENTION, 'The latest backup check failed.', $details);
        }

        $details['Result'] = 'Passed';
        $details['Archive entries'] = number_format((int) $latest->archive_entry_count);
        $details['Size'] = number_format((int) $latest->backup_size_bytes / 1048576, 2).' MB';
        $details['SHA-256'] = substr((string) $latest->backup_sha256, 0, 12).'…';

        if ($latest->verified_at->lt(now()->subHours(app(BackupVerificationService::class)->maxAgeHours()))) {
            return $this->result(self::NEEDS_ATTENTION, 'The last successful check is older than expected. Check that the daily verification is running.', $details);
        }

        $newest = $this->backups->latestValidBackup();

        if ($newest === null || $newest['name'] !== $latest->backup_filename || $newest['size'] !== (int) $latest->backup_size_bytes) {
            return $this->result(self::NEEDS_ATTENTION, 'The newest backup has not been verified, or the verified file has changed.', $details);
        }

        return $this->result(self::HEALTHY, 'The latest backup was checked and is readable.', $details);
    }

    private function pendingJobs(): array
    {
        if (config('queue.default') !== 'database') {
            return $this->result(self::UNAVAILABLE, 'Waiting jobs can only be counted on the database queue.');
        }

        $table = (string) config('queue.connections.database.table', 'jobs');

        if (! \App\Support\SchemaCache::hasTable($table)) {
            return $this->result(self::UNAVAILABLE, 'The queue table was not found. Run the database migrations.');
        }

        // One query for all three figures (each used to be its own database round trip).
        $queue = DB::table($table)->selectRaw(
            'COALESCE(SUM(CASE WHEN reserved_at IS NULL THEN 1 ELSE 0 END), 0) AS waiting, '
            .'COALESCE(SUM(CASE WHEN reserved_at IS NOT NULL THEN 1 ELSE 0 END), 0) AS running, '
            .'MIN(CASE WHEN reserved_at IS NULL THEN available_at END) AS oldest'
        )->first();
        $waiting = (int) $queue->waiting;
        $running = (int) $queue->running;
        $oldest = $queue->oldest;
        $oldestMinutes = $oldest === null ? 0 : max(0, (int) floor((now()->timestamp - (int) $oldest) / 60));

        $details = ['Waiting' => (string) $waiting, 'Running now' => (string) $running];
        if ($waiting > 0) {
            $details['Longest wait'] = $oldestMinutes.' min';
        }

        if ($oldestMinutes > self::QUEUE_OLDEST_WAITING_MINUTES || $waiting > self::QUEUE_WAITING_LIMIT) {
            return $this->result(self::NEEDS_ATTENTION, 'Jobs are waiting longer than expected. Check that the queue worker is running.', $details);
        }

        return $this->result(self::HEALTHY, $waiting === 0 ? 'No jobs are waiting.' : 'Jobs are being processed.', $details);
    }

    private function failedJobs(): array
    {
        $table = (string) config('queue.failed.table', 'failed_jobs');

        if (! \App\Support\SchemaCache::hasTable($table)) {
            return $this->result(self::UNAVAILABLE, 'The failed-jobs table was not found. Run the database migrations.');
        }

        $failed = DB::table($table)->selectRaw('COUNT(*) AS total, COALESCE(SUM(CASE WHEN failed_at >= ? THEN 1 ELSE 0 END), 0) AS recent, MAX(failed_at) AS latest', [now()->subDay()])->first();
        $total = (int) $failed->total;
        $recent = (int) $failed->recent;
        $latest = $failed->latest;

        if ($total === 0) {
            return $this->result(self::HEALTHY, 'No failed jobs.', ['Failed jobs' => '0']);
        }

        return $this->result(
            self::NEEDS_ATTENTION,
            $total.' failed '.($total === 1 ? 'job needs' : 'jobs need').' review.',
            [
                'Failed jobs' => (string) $total,
                'Failed in the last 24 hours' => (string) $recent,
                'Most recent failure' => $this->when($latest),
            ],
        );
    }

    private function latestWeather(): array
    {
        $latest = ExternalWeatherData::query()->latestSuccessful()->first();

        if (! $latest || ! $latest->fetched_at) {
            return $this->result(self::UNAVAILABLE, 'No weather updates have been saved yet.');
        }

        // Reuses the model's own freshness rule (fresh under 2h, delayed up to 6h).
        $freshness = $latest->freshnessLabel();

        return $this->result(
            $freshness === 'fresh' ? self::HEALTHY : self::NEEDS_ATTENTION,
            match ($freshness) {
                'fresh' => 'Weather data is up to date.',
                'delayed' => 'Weather data is a little behind.',
                default => 'Weather data is out of date. Check the scheduler and the weather connection.',
            },
            ['Last update' => $this->when($latest->fetched_at)],
        );
    }

    private function latestPrediction(): array
    {
        $latest = MonthlyWeatherPrediction::query()
            ->where('status', MonthlyWeatherPrediction::STATUS_READY)
            ->latest('generated_at')
            ->first();

        if (! $latest || ! $latest->generated_at) {
            $hasIncomplete = MonthlyWeatherPrediction::query()->exists();

            return $hasIncomplete
                ? $this->result(self::NEEDS_ATTENTION, 'Only incomplete monthly forecasts are stored.')
                : $this->result(self::UNAVAILABLE, 'No monthly forecasts have been saved yet.');
        }

        $ready = MonthlyWeatherPrediction::query()->where('status', MonthlyWeatherPrediction::STATUS_READY)->count();
        $stale = $latest->generated_at->lt(now()->subHours(self::PREDICTION_MAX_AGE_HOURS));

        return $this->result(
            $stale ? self::NEEDS_ATTENTION : self::HEALTHY,
            $stale ? 'Monthly forecasts have not been refreshed recently.' : 'Monthly forecasts are up to date.',
            ['Last generated' => $this->when($latest->generated_at), 'Ready forecasts saved' => (string) $ready],
        );
    }

    private function latestAnalytics(): array
    {
        $run = AnalyticsRun::query()->where('type', 'dataset_processing')->latest('created_at')->first();

        if (! $run) {
            return $this->result(self::UNAVAILABLE, 'No dataset processing has been recorded yet.');
        }

        $details = ['Status' => ucfirst((string) $run->status), 'Started' => $this->when($run->created_at)];

        if ($run->status === AnalyticsRun::STATUS_FAILED) {
            return $this->result(self::NEEDS_ATTENTION, 'The latest dataset processing did not finish. Details are in the application log.', $details);
        }

        if (in_array($run->status, [AnalyticsRun::STATUS_PENDING, AnalyticsRun::STATUS_PROCESSING], true)) {
            $stuck = $run->created_at->lt(now()->subMinutes(self::ANALYTICS_STUCK_MINUTES));

            return $this->result(
                $stuck ? self::NEEDS_ATTENTION : self::HEALTHY,
                $stuck ? 'Dataset processing has been waiting too long. Check the queue worker.' : 'Dataset processing is in progress.',
                $details,
            );
        }

        $finished = $run->completed_at ?? $run->created_at;
        $details['Finished'] = $this->when($finished);
        $stale = $finished->lt(now()->subHours(self::ANALYTICS_MAX_AGE_HOURS));

        return $this->result(
            $stale ? self::NEEDS_ATTENTION : self::HEALTHY,
            $stale ? 'Dataset processing has not run recently.' : 'Dataset processing finished recently.',
            $details,
        );
    }

    private function python(): array
    {
        return $this->pythonBinaryExists()
            ? $this->result(self::HEALTHY, 'Python is installed for the analytics tools.')
            : $this->result(self::NEEDS_ATTENTION, 'Python was not found. Analytics and prediction jobs may fail.');
    }

    /**
     * @param  'weather'|'rice_yield'  $model  key under config('prediction')
     */
    private function modelFile(string $model): array
    {
        $path = $this->resolvePath((string) config("prediction.{$model}.model_path", ''));
        $name = (string) config("prediction.{$model}.model_name", '');
        $details = $name !== '' ? ['Model' => $name] : [];

        if ($path === null || ! is_file($path)) {
            return $this->result(self::NEEDS_ATTENTION, 'The model file was not found.', $details);
        }

        $size = @filesize($path);

        if (! is_readable($path) || $size === false || $size <= 0) {
            return $this->result(self::NEEDS_ATTENTION, 'The model file is empty or cannot be read.', $details);
        }

        $modified = @filemtime($path);
        $details['Last updated'] = $modified ? $this->when(\Illuminate\Support\Carbon::createFromTimestamp($modified)) : 'Unknown';
        $details['Size'] = number_format($size / 1048576, 2).' MB';

        return $this->result(self::HEALTHY, 'The model file is in place.', $details);
    }

    /**
     * Compares the registry's active rice-yield model with what the Python
     * service reports it has loaded (a read-only model-info request; nothing
     * is predicted, switched or reloaded). Only file names and versions are
     * shown, never paths.
     */
    private function modelSync(): array
    {
        $active = MlModel::query()
            ->where('purpose', MlModel::PURPOSE_RICE_YIELD)
            ->where('is_active', true)
            ->first();

        if ($active === null) {
            return $this->result(self::UNAVAILABLE, 'No active model is registered yet.', ['Sync state' => 'Unavailable']);
        }

        $details = [
            'Registry active model' => 'v'.$active->version.' ('.$active->fileName().')',
        ];

        $client = app(RuntimeModelClient::class);

        try {
            $info = $client->info();
        } catch (Throwable) {
            return $this->result(self::UNAVAILABLE, 'The prediction service did not report which model it is using.', $details + [
                'Python loaded model' => 'Unknown',
                'Sync state' => 'Unavailable',
            ]);
        }

        $details['Python loaded model'] = (string) ($info['filename'] ?? 'Unknown')
            .(! empty($info['version']) ? ' (v'.$info['version'].')' : '');
        $details['scikit-learn'] = (string) ($info['sklearn_version'] ?? 'Unknown');
        $details['Loaded at'] = $this->when($info['loaded_at'] ?? null);

        if ($client->matches($active, $info)) {
            return $this->result(self::HEALTHY, 'The prediction service is running the active registry model.', $details + ['Sync state' => 'Sync']);
        }

        return $this->result(self::NEEDS_ATTENTION, 'The prediction service is running a different model than the registry says is active.', $details + ['Sync state' => 'Mismatch']);
    }

    /**
     * The scheduler process itself is never inspected. The only evidence is
     * whether automation it would trigger left a recent row behind, so the
     * messages describe exactly that.
     */
    private function scheduler(): array
    {
        // Scheduled runs are created by iclimate:automation-run with no requesting user.
        $run = AnalyticsRun::query()
            ->whereNull('requested_by')
            ->whereIn('type', ['dataset_processing', 'advisory_ai'])
            ->latest('created_at')
            ->first();

        if (! $run) {
            return $this->result(self::UNAVAILABLE, 'No scheduled automation has been recorded yet.');
        }

        $stale = $run->created_at->lt(now()->subMinutes(self::SCHEDULER_MAX_AGE_MINUTES));

        return $this->result(
            $stale ? self::NEEDS_ATTENTION : self::HEALTHY,
            $stale
                ? 'No recent scheduled automation detected. Check that the scheduler is set up to run.'
                : 'Recent scheduled automation detected.',
            ['Last scheduled automation' => $this->when($run->created_at)],
        );
    }

    // --------------------------------------------------------------- helpers

    /**
     * @param  callable(): array{status: string, summary: string, details: array<string, string>}  $check
     * @return array{key: string, title: string, status: string, summary: string, details: array<string, string>}
     */
    private function guard(string $key, string $title, callable $check): array
    {
        try {
            $result = $check();
        } catch (Throwable $exception) {
            // Only the exception class is logged: messages can contain hosts or credentials.
            Log::warning('System health check failed.', ['check' => $key, 'exception' => $exception::class]);

            $result = $this->result(self::UNAVAILABLE, 'This check could not be completed right now.');
        }

        return ['key' => $key, 'title' => $title] + $result;
    }

    /**
     * @param  array<string, string>  $details
     * @return array{status: string, summary: string, details: array<string, string>}
     */
    private function result(string $status, string $summary, array $details = []): array
    {
        return ['status' => $status, 'summary' => $summary, 'details' => $details];
    }

    private function when(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Unknown';
        }

        $time = $value instanceof CarbonInterface ? $value : \Illuminate\Support\Carbon::parse($value);

        return $time->timezone(self::TIMEZONE)->format('M j, Y g:i A').' ('.$time->diffForHumans().')';
    }

    private function databaseTitle(): string
    {
        // Supabase is deliberately not named: nothing in the configuration confirms the host.
        return $this->driver() === 'pgsql' ? 'PostgreSQL Connection' : 'Database Connection';
    }

    private function driver(): string
    {
        $connection = (string) config('database.default');

        return (string) config("database.connections.{$connection}.driver", '');
    }

    private function driverLabel(): string
    {
        return match ($this->driver()) {
            'pgsql' => 'PostgreSQL',
            'mysql', 'mariadb' => 'MySQL',
            'sqlite' => 'SQLite',
            'sqlsrv' => 'SQL Server',
            default => 'Database',
        };
    }

    private function resolvePath(string $path): ?string
    {
        $path = trim($path);

        if ($path === '') {
            return null;
        }

        return preg_match('#^([A-Za-z]:[\\\\/]|/)#', $path) === 1 ? $path : base_path($path);
    }

    /**
     * Same lookup the existing candidate-model command uses: PYTHON_BINARY if
     * set, otherwise the project virtual environment.
     */
    private function pythonBinaryExists(): bool
    {
        $configured = trim((string) config('iclimate.python_binary', ''), " \t\n\r\0\x0B\"'");

        $candidates = $configured !== ''
            ? [$configured]
            : [base_path('.venv/Scripts/python.exe'), base_path('.venv/bin/python')];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return true;
            }
        }

        return false;
    }
}
