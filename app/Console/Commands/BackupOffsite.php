<?php

namespace App\Console\Commands;

use App\Services\OffsiteBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Encrypts the newest database backup, proves it decrypts to the original, and copies it off this host. */
class BackupOffsite extends Command
{
    protected $signature = 'iclimate:backup-offsite';

    protected $description = 'Encrypt the newest database backup and copy it to the off-host target (BACKUP_OFFSITE_TARGET).';

    public function handle(OffsiteBackupService $offsite): int
    {
        if (! OffsiteBackupService::configured()) {
            $this->warn('Off-host backups are not configured (BACKUP_OFFSITE_TARGET, BACKUP_ENCRYPTION_KEY_FILE). Nothing was copied.');

            return self::SUCCESS;
        }

        try {
            $result = $offsite->copyLatest();
        } catch (Throwable $e) {
            Log::error('Off-host backup failed.', ['reason' => $e->getMessage()]);
            $this->error('Off-host backup failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Encrypted copy sent: '.$result['file'].' ('.$result['target'].')');
        $this->line('SHA-256 of the encrypted file: '.$result['sha256']);
        $this->line('Older copies removed: '.$result['pruned']);

        return self::SUCCESS;
    }
}
