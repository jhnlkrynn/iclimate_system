<?php

namespace App\Console\Commands;

use App\Exceptions\BackupFailedException;
use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'iclimate:backup-database
        {--no-prune : Keep old backups instead of removing the ones past the retention window}';

    protected $description = 'Back up the configured iClimate MySQL/MariaDB or PostgreSQL database and prune old backups.';

    public function handle(DatabaseBackupService $backups): int
    {
        try {
            $result = $backups->backup();
        } catch (BackupFailedException $exception) {
            $this->error('Backup failed: '.$exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $caught) {
            \App\Support\SilentFailure::log($caught, 'BackupDatabase::handle');
            // Unexpected errors are not printed: their messages can contain connection details.
            $this->error('Backup failed unexpectedly. See the application log.');

            return self::FAILURE;
        }

        $this->info('Backup created: '.$result['file'].' ('.number_format($result['bytes'] / 1048576, 2).' MB).');

        if (! $this->option('no-prune')) {
            try {
                $removed = $backups->prune();
                $this->line($removed.' old '.($removed === 1 ? 'backup' : 'backups').' removed (keeping the last '.$backups->retentionDays().' days).');
            } catch (Throwable $caught) {
                \App\Support\SilentFailure::log($caught, 'BackupDatabase::handle');
                $this->warn('The backup was saved, but old backups could not be cleaned up.');
            }
        }

        return self::SUCCESS;
    }
}
