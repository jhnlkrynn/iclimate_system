<?php

namespace App\Console\Commands;

use App\Models\BackupVerification;
use App\Services\BackupVerificationService;
use Illuminate\Console\Command;
use Throwable;

class VerifyBackup extends Command
{
    protected $signature = 'iclimate:verify-backup';

    protected $description = 'Check that the latest database backup is a readable PostgreSQL archive (pg_restore --list). Never restores anything.';

    public function handle(BackupVerificationService $verifier): int
    {
        try {
            $result = $verifier->verify();
        } catch (Throwable) {
            // Messages can carry paths or connection details, so none are printed.
            $this->error('Backup verification could not run. See the application log.');

            return self::FAILURE;
        }

        if ($result->status !== BackupVerification::PASSED) {
            $this->error('Backup verification failed: '.$result->failure_reason);

            return self::FAILURE;
        }

        $this->info('Backup verified: '.$result->backup_filename.' ('.$result->archive_entry_count.' archive entries, SHA-256 '.substr((string) $result->backup_sha256, 0, 12).'…).');

        return self::SUCCESS;
    }
}
