<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Encrypted off-host copies of the newest database backup (deployment audit 2026-11-19: "encrypted off-host backups,
 * retention"). Steps, each checked before the next:
 *
 *   1. the newest complete backup (DatabaseBackupService::latestValidBackup) and its SHA-256;
 *   2. encrypt with OpenSSL AES-256-CBC, PBKDF2 (200,000 iterations), key read from BACKUP_ENCRYPTION_KEY_FILE;
 *   3. decrypt the encrypted file again and require the same SHA-256 as the original (proves the copy can be restored);
 *   4. copy it to BACKUP_OFFSITE_TARGET: a mounted directory (another disk / network share) or an rsync/SSH target
 *      "user@host:/path" — then compare checksums (directory) or rsync's own --checksum transfer;
 *   5. keep BACKUP_OFFSITE_RETENTION_DAYS of copies in a directory target (the newest is always kept);
 *   6. record the copy (storage/app/backup-offsite-status.json) for iclimate:health.
 *
 * The key never appears on a command line or in output. Nothing is ever restored or deleted outside the target.
 */
final class OffsiteBackupService
{
    public const SUFFIX = '.enc';

    public function __construct(private readonly DatabaseBackupService $backups) {}

    public static function configured(): bool
    {
        return (string) config('backup.offsite.target') !== '' && (string) config('backup.offsite.key_file') !== '';
    }

    /** @return array{at: CarbonImmutable, file: string, sha256: string, target: string}|null */
    public static function lastCopy(): ?array
    {
        $path = self::statusPath();
        if (! is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data) || empty($data['at']) || empty($data['file'])) {
            return null;
        }

        return ['at' => CarbonImmutable::parse($data['at']), 'file' => (string) $data['file'], 'sha256' => (string) ($data['sha256'] ?? ''), 'target' => (string) ($data['target'] ?? '')];
    }

    public static function statusPath(): string
    {
        return (string) (config('backup.offsite.status_file') ?: storage_path('app/backup-offsite-status.json'));
    }

    /** @return array{file: string, sha256: string, target: string, pruned: int} */
    public function copyLatest(): array
    {
        if (! self::configured()) {
            throw new RuntimeException('Off-host backups are not configured: set BACKUP_OFFSITE_TARGET and BACKUP_ENCRYPTION_KEY_FILE.');
        }
        $keyFile = (string) config('backup.offsite.key_file');
        if (! is_file($keyFile) || ! is_readable($keyFile) || filesize($keyFile) < 32) {
            throw new RuntimeException('The backup encryption key file is missing, unreadable or shorter than 32 bytes.');
        }
        if ((fileperms($keyFile) & 0o077) !== 0) {
            throw new RuntimeException('The backup encryption key file must not be readable by group or others (chmod 600).');
        }
        $latest = $this->backups->latestValidBackup();
        if ($latest === null) {
            throw new RuntimeException('There is no complete backup to copy. Run iclimate:backup-database first.');
        }

        $plainSha = hash_file('sha256', $latest['path']);
        $work = rtrim(sys_get_temp_dir(), '/').'/iclimate-offsite-'.bin2hex(random_bytes(6));
        if (! mkdir($work, 0700) && ! is_dir($work)) {
            throw new RuntimeException('Could not create a private working directory.');
        }
        $name = $latest['name'].self::SUFFIX;
        $encrypted = $work.'/'.$name;

        try {
            $this->openssl(['-e', '-in', $latest['path'], '-out', $encrypted], $keyFile);
            $check = $work.'/check.plain';
            $this->openssl(['-d', '-in', $encrypted, '-out', $check], $keyFile);
            if (! hash_equals($plainSha, (string) hash_file('sha256', $check))) {
                throw new RuntimeException('The encrypted copy does not decrypt to the original backup; nothing was sent.');
            }
            @unlink($check);
            $cipherSha = (string) hash_file('sha256', $encrypted);
            // The manifest travels with the copy: both checksums, so a restore can verify before and after decrypting.
            file_put_contents($encrypted.'.sha256', $cipherSha.'  '.$name."\n".$plainSha.'  '.$latest['name']."\n");

            $target = (string) config('backup.offsite.target');
            $remote = preg_match('/^[^\/]+:/', $target) === 1;   // user@host:/path or host:/path
            if ($remote) {
                $this->run([(string) config('backup.offsite.rsync_binary', 'rsync'), '-a', '--checksum', '--chmod=F600',
                    '-e', (string) config('backup.offsite.ssh_command', 'ssh -o BatchMode=yes'), $encrypted, $encrypted.'.sha256', rtrim($target, '/').'/'], 'rsync');
                $pruned = 0;
            } else {
                if (! is_dir($target) && ! mkdir($target, 0700, true) && ! is_dir($target)) {
                    throw new RuntimeException('The off-host target directory does not exist and could not be created.');
                }
                if (realpath($target) !== false && str_starts_with((string) realpath($target), (string) realpath($this->backups->directory()))) {
                    throw new RuntimeException('The off-host target must not be inside the backup directory.');
                }
                $dest = rtrim($target, '/').'/'.$name;
                if (! copy($encrypted, $dest.'.partial') || ! copy($encrypted.'.sha256', $dest.'.sha256')) {
                    throw new RuntimeException('Could not write to the off-host target.');
                }
                if (! hash_equals($cipherSha, (string) hash_file('sha256', $dest.'.partial'))) {
                    @unlink($dest.'.partial');
                    throw new RuntimeException('The copy on the target does not match; it was removed.');
                }
                rename($dest.'.partial', $dest);
                @chmod($dest, 0600);
                @chmod($dest.'.sha256', 0600);
                $pruned = $this->prune($target, $name);
            }
        } finally {
            foreach (glob($work.'/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($work);
        }

        $record = ['at' => CarbonImmutable::now()->toIso8601String(), 'file' => $name, 'sha256' => $cipherSha, 'target' => $remote ? 'remote (rsync)' : 'directory'];
        @mkdir(dirname(self::statusPath()), 0775, true);
        file_put_contents(self::statusPath(), json_encode($record, JSON_PRETTY_PRINT));

        return ['file' => $name, 'sha256' => $cipherSha, 'target' => $record['target'], 'pruned' => $pruned];
    }

    /** Remove encrypted copies older than the retention window from a directory target; the newest is kept. */
    private function prune(string $target, string $keep): int
    {
        $days = max(7, (int) config('backup.offsite.retention_days', 30));
        $files = glob(rtrim($target, '/').'/*'.self::SUFFIX) ?: [];
        usort($files, fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
        $cutoff = time() - $days * 86400;
        $removed = 0;
        foreach (array_slice($files, 1) as $f) {
            if (basename($f) !== $keep && filemtime($f) < $cutoff) {
                @unlink($f);
                @unlink($f.'.sha256');
                $removed++;
            }
        }

        return $removed;
    }

    /** @param  list<string>  $args */
    private function openssl(array $args, string $keyFile): void
    {
        $this->run(array_merge([(string) config('backup.offsite.openssl_binary', 'openssl'), 'enc', '-aes-256-cbc', '-pbkdf2', '-iter', '200000', '-md', 'sha256', '-salt'],
            $args, ['-pass', 'file:'.$keyFile]), 'openssl');
    }

    /** @param  list<string>  $command */
    private function run(array $command, string $label): void
    {
        $process = new Process($command, null, null, null, (int) config('backup.timeout_seconds', 900));
        $process->run();
        if (! $process->isSuccessful()) {
            // Only the exit code: stderr can name paths or hosts.
            throw new RuntimeException($label.' failed (exit '.$process->getExitCode().').');
        }
    }
}
