<?php

namespace App\Console\Commands;

use App\Services\BackupRestoreTestService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Manual only: deliberately not scheduled. Restores the newest backup into a
 * temporary, network-less local PostgreSQL container (never Supabase) and
 * reports whether the expected tables came back.
 */
class TestRestore extends Command
{
    protected $signature = 'iclimate:test-restore';

    protected $description = 'Restore the latest backup into a temporary local PostgreSQL Docker container and check the tables. Never touches Supabase; the container is always removed.';

    public function handle(BackupRestoreTestService $restoreTest): int
    {
        $this->line('Testing the restore in a temporary local container. This can take a few minutes...');

        try {
            $result = $restoreTest->run();
        } catch (Throwable) {
            $this->error('Restore test FAILED: it stopped unexpectedly. See the application log.');

            return self::FAILURE;
        }

        if ($result['passed']) {
            $this->info('Restore test PASSED for '.$result['backup'].'.');
            $this->line('  Tables in backup / restored: '.$result['tables_in_backup'].' / '.$result['tables_restored']);
            $this->line('  Core tables present: '.$result['core_tables_present'].' of '.$result['core_tables_expected']);

            foreach ($result['core_row_counts'] as $table => $rows) {
                $this->line('  Rows in '.$table.': '.number_format($rows));
            }
        } else {
            $this->error('Restore test FAILED: '.BackupRestoreTestService::REASONS[$result['reason']]);

            if ($result['backup'] !== null) {
                $this->line('  Backup tested: '.$result['backup']);
            }
        }

        if ($result['container'] !== null) {
            if ($result['cleanup_ok']) {
                $this->line('  Temporary container removed.');
            } else {
                $this->warn('  The temporary container could not be removed. Run: docker rm -f '.$result['container']);
            }
        }

        $this->line('  Finished in '.$result['seconds'].' s.');

        return $result['passed'] && $result['cleanup_ok'] ? self::SUCCESS : self::FAILURE;
    }
}
