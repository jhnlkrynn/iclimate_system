<?php

namespace App\Console\Commands;

use App\Models\ScheduledTaskRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Daily cleanup of data that otherwise only grows (audit 2026-11-14, OPS-02 and OPS-03).
 *
 *  - scheduled_task_runs: one row per scheduled run (~2,400 a day). Rows older than the retention are removed;
 *    System Health only reads the runs still in progress.
 *  - File cache: Laravel's file store deletes an entry only when it is read after it expired, so entries that are
 *    never read again stay on disk (2.7 GB seen). Each cache file starts with its 10-digit expiry time; files whose
 *    time has passed are removed. Entries stored "forever" (expiry 9999999999) are kept.
 */
class Housekeeping extends Command
{
    protected $signature = 'iclimate:housekeeping
        {--days=14 : Keep scheduled-task history for this many days}
        {--dry-run : Only report what would be removed}';

    protected $description = 'Remove old scheduled-task history and expired file-cache entries.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $days = max(1, (int) $this->option('days'));

        $runs = 0;
        if (Schema::hasTable('scheduled_task_runs')) {
            $old = ScheduledTaskRun::query()->where('started_at', '<', now()->subDays($days));
            $runs = $dryRun ? $old->count() : $old->delete();
        }
        $this->info(($dryRun ? 'Would remove ' : 'Removed ').$runs." scheduled-task run(s) older than {$days} day(s).");

        [$files, $bytes] = $this->pruneFileCache($dryRun);
        $this->info(($dryRun ? 'Would remove ' : 'Removed ').$files.' expired cache file(s), '.round($bytes / 1048576, 1).' MB.');

        return self::SUCCESS;
    }

    /** @return array{int, int} files removed and their size in bytes */
    private function pruneFileCache(bool $dryRun): array
    {
        $path = (string) config('cache.stores.file.path', '');
        if ($path === '' || ! is_dir($path)) {
            return [0, 0];
        }

        $now = time();
        $files = 0;
        $bytes = 0;
        foreach (File::allFiles($path) as $file) {
            try {
                $handle = fopen($file->getPathname(), 'rb');
                $expiry = $handle ? fread($handle, 10) : false;
                if ($handle) {
                    fclose($handle);
                }
                if (! is_string($expiry) || ! ctype_digit($expiry) || (int) $expiry >= $now) {
                    continue; // not a cache entry, or still valid
                }
                $size = $file->getSize();
                if ($dryRun || @unlink($file->getPathname())) {
                    $files++;
                    $bytes += $size;
                }
            } catch (Throwable) {
                // A file in use or removed meanwhile is simply skipped.
            }
        }

        return [$files, $bytes];
    }
}
