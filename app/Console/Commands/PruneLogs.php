<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PruneLogs extends Command
{
    protected $signature = 'iclimate:prune-logs
        {--days=14 : Keep log files modified within the last N days}
        {--dry-run : Preview which log files would be deleted without removing them}';

    protected $description = 'Prune old log files in storage/logs/ beyond the retention window (default 14 days).';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');
        $logDir = storage_path('logs');

        if (! is_dir($logDir)) {
            $this->info('Log directory does not exist.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days)->getTimestamp();
        $pruned = 0;
        $freedBytes = 0;

        foreach (glob($logDir.DIRECTORY_SEPARATOR.'*.log') ?: [] as $file) {
            if (! is_file($file)) {
                continue;
            }
            // Never delete the active un-dated laravel.log unless it is stale and rotated logs are used.
            if (basename($file) === 'laravel.log') {
                continue;
            }
            $mtime = @filemtime($file);
            if ($mtime === false || $mtime >= $cutoff) {
                continue;
            }

            $size = (int) (@filesize($file) ?: 0);
            $freedBytes += $size;
            $pruned++;

            if ($dryRun) {
                $this->line('[DRY RUN] Would delete '.basename($file).' ('.number_format($size / 1024, 1).' KB)');
            } else {
                @unlink($file);
            }
        }

        $kb = number_format($freedBytes / 1024, 1);
        $this->info($dryRun
            ? "Dry run: {$pruned} log file(s) older than {$days} day(s) ({$kb} KB) would be removed."
            : "Pruned {$pruned} log file(s) older than {$days} day(s) ({$kb} KB freed).");

        return self::SUCCESS;
    }
}
