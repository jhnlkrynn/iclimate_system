<?php

namespace App\Console\Commands;

use App\Jobs\GenerateMonthlyWeatherForecast;
use App\Jobs\RefreshBarangayRiskMap;
use App\Jobs\RunPythonAnalytics;
use App\Models\AnalyticsRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Tidies the queue after the worker was not running for a while. Without this the worker comes back to a pile
 * of repeated refresh jobs (the scheduler queues a new one every run) and to requests whose user gave up long ago.
 *
 *  - Requested predictions/reports nothing finished in time are closed as "timed out" and their jobs removed.
 *  - Repeated refresh jobs are reduced to the newest one (one per month for monthly forecasts).
 *
 * Jobs a worker is running right now are never touched. Every removed job is first written to a file in
 * storage/app/backups/queue so it can be inspected or put back.
 */
class CleanStaleQueue extends Command
{
    protected $signature = 'iclimate:clean-stale-queue {--dry-run : Show what would be removed without changing anything}';

    protected $description = 'Close timed-out prediction/report requests and remove repeated or abandoned queue jobs.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $table = (string) config('queue.connections.database.table', 'jobs');

        $expiredRuns = AnalyticsRun::query()
            ->whereIn('status', [AnalyticsRun::STATUS_PENDING, AnalyticsRun::STATUS_PROCESSING])
            ->get()
            ->filter(fn (AnalyticsRun $run): bool => $dryRun ? $run->isStale() : $run->expireIfStale());
        $closedRunIds = AnalyticsRun::query()
            ->whereIn('status', [AnalyticsRun::STATUS_FAILED, AnalyticsRun::STATUS_COMPLETED])
            ->pluck('id')->merge($expiredRuns->pluck('id'))->flip();

        $remove = [];
        $newest = [];
        // Newest first, so the first job seen of each kind is the one to keep.
        foreach (DB::table($table)->whereNull('reserved_at')->orderByDesc('id')->get() as $job) {
            $command = $this->command($job->payload);

            $duplicateOf = match (true) {
                $command instanceof RefreshBarangayRiskMap => RefreshBarangayRiskMap::class,
                $command instanceof GenerateMonthlyWeatherForecast => GenerateMonthlyWeatherForecast::class.'|'.$command->targetMonth,
                default => null,
            };

            if ($command instanceof RunPythonAnalytics && $closedRunIds->has($command->analyticsRunId)) {
                $remove[$job->id] = $job;
            } elseif ($duplicateOf !== null && isset($newest[$duplicateOf])) {
                $remove[$job->id] = $job;
            } elseif ($duplicateOf !== null) {
                $newest[$duplicateOf] = true;
            }
        }

        $this->info(($dryRun ? 'Would close ' : 'Closed ').$expiredRuns->count().' timed-out request(s).');
        $this->info(($dryRun ? 'Would remove ' : 'Removed ').count($remove).' repeated or abandoned job(s); '.(DB::table($table)->count() - count($remove)).' job(s) stay queued.');

        if ($dryRun || $remove === []) {
            return self::SUCCESS;
        }

        $directory = rtrim((string) config('backup.directory', storage_path('app/backups')), '/\\').DIRECTORY_SEPARATOR.'queue';
        File::ensureDirectoryExists($directory);
        // These safety copies are only useful for a short while.
        foreach (File::glob($directory.DIRECTORY_SEPARATOR.'removed-jobs-*.json') as $old) {
            if (File::lastModified($old) < now()->subDays(14)->getTimestamp()) {
                File::delete($old);
            }
        }
        $file = $directory.'/removed-jobs-'.now()->format('Y-m-d_His').'.json';
        File::put($file, json_encode(array_values($remove), JSON_PRETTY_PRINT));
        DB::table($table)->whereIn('id', array_keys($remove))->whereNull('reserved_at')->delete();
        $this->line('Removed jobs were saved to '.$file);

        return self::SUCCESS;
    }

    /** The job object inside a queue payload, or null when it cannot be read (such a job is left alone). */
    private function command(string $payload): ?object
    {
        try {
            $serialized = json_decode($payload, true)['data']['command'] ?? null;
            $command = is_string($serialized) ? unserialize($serialized, ['allowed_classes' => [
                RefreshBarangayRiskMap::class, GenerateMonthlyWeatherForecast::class, RunPythonAnalytics::class,
            ]]) : null;

            return is_object($command) && ! $command instanceof \__PHP_Incomplete_Class ? $command : null;
        } catch (Throwable) {
            return null;
        }
    }
}
