<?php

namespace App\Services;

use App\Models\BackupVerification;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Proves the newest backup is readable without restoring it. A PostgreSQL archive is listed with
 * `pg_restore --list` (its table of contents only); a MySQL/MariaDB dump is read as text: it must end with
 * mysqldump's completion line and define tables.
 *
 * pg_restore is given the file and nothing else. It is not given a database
 * connection or any PG* credentials, so it cannot reach or change the live
 * database. Failure reasons are fixed sentences; raw stderr, file paths and
 * exception messages are never stored, printed or logged.
 */
class BackupVerificationService
{
    public const REASONS = [
        'no_valid_backup' => 'No valid backup file was found to verify.',
        'file_unreadable' => 'The backup file could not be read.',
        'pg_restore_unavailable' => 'The pg_restore tool was not found or could not be started.',
        'timeout' => 'The check took too long and was stopped.',
        'archive_unreadable' => 'The backup archive could not be read by pg_restore.',
        'archive_empty' => 'The backup archive has no contents.',
        'dump_incomplete' => 'The backup file is incomplete: it does not end with the completion line.',
    ];

    public function __construct(private readonly DatabaseBackupService $backups) {}

    public function maxAgeHours(): int
    {
        return max(1, (int) config('backup.verification_max_age_hours', config('backup.max_age_hours', 36)));
    }

    /** Verifies the newest valid backup and records the outcome (pass or fail). */
    public function verify(): BackupVerification
    {
        $backup = $this->backups->latestValidBackup();

        if ($backup === null) {
            return $this->fail('no_valid_backup');
        }

        $identity = [
            'backup_filename' => $backup['name'],
            'backup_size_bytes' => $backup['size'],
        ];

        $sha256 = @hash_file('sha256', $backup['path']);

        if ($sha256 === false) {
            return $this->fail('file_unreadable', $identity);
        }

        $identity['backup_sha256'] = $sha256;

        if ($this->backups->isSqlDump($backup['path'])) {
            return $this->verifySqlDump($backup, $identity);
        }

        $binary = (string) config('backup.pg_restore_binary', 'pg_restore');

        // A configured location (not a bare PATH name) must exist.
        if (preg_match('#[\\\\/]#', $binary) === 1 && ! is_file($binary)) {
            return $this->fail('pg_restore_unavailable', $identity);
        }

        try {
            $result = Process::timeout(max(5, (int) config('backup.verify_timeout_seconds', 120)))
                ->run([$binary, '--list', $backup['path']]);
        } catch (ProcessTimedOutException) {
            return $this->fail('timeout', $identity);
        } catch (Throwable) {
            return $this->fail('pg_restore_unavailable', $identity);
        }

        if (! $result->successful()) {
            $missing = $result->exitCode() === 127
                || preg_match('/not recognized|not found|no such file|cannot find/i', $result->errorOutput()) === 1;

            return $this->fail($missing ? 'pg_restore_unavailable' : 'archive_unreadable', $identity);
        }

        $entries = $this->countEntries($result->output());

        if ($entries === 0) {
            return $this->fail('archive_empty', $identity);
        }

        $record = BackupVerification::create($identity + [
            'verified_at' => now(),
            'status' => BackupVerification::PASSED,
            'archive_entry_count' => $entries,
        ]);

        Log::info('Backup verification passed.', [
            'backup' => $backup['name'],
            'entries' => $entries,
        ]);

        return $record;
    }

    /**
     * A mysqldump file is plain SQL, so it is checked by reading it: complete, and with table definitions.
     *
     * @param  array{name: string, path: string, size: int, mtime: int}  $backup
     * @param  array<string, mixed>  $identity
     */
    private function verifySqlDump(array $backup, array $identity): BackupVerification
    {
        if (! $this->backups->sqlDumpIsComplete($backup['path'])) {
            return $this->fail('dump_incomplete', $identity);
        }

        $handle = @fopen($backup['path'], 'rb');
        if ($handle === false) {
            return $this->fail('file_unreadable', $identity);
        }

        $tables = 0;
        while (($line = fgets($handle)) !== false) {
            if (str_starts_with($line, 'CREATE TABLE ')) {
                $tables++;
            }
        }
        fclose($handle);

        if ($tables === 0) {
            return $this->fail('archive_empty', $identity);
        }

        $record = BackupVerification::create($identity + [
            'verified_at' => now(),
            'status' => BackupVerification::PASSED,
            'archive_entry_count' => $tables,
        ]);

        Log::info('Backup verification passed.', ['backup' => $backup['name'], 'entries' => $tables]);

        return $record;
    }

    /**
     * Archive entries are the numbered lines ("123; 1259 16400 TABLE ...");
     * the header and comments start with ";".
     */
    private function countEntries(string $listing): int
    {
        return preg_match_all('/^\d+;/m', $listing) ?: 0;
    }

    /** @param array<string, mixed> $identity */
    private function fail(string $reason, array $identity = []): BackupVerification
    {
        $record = BackupVerification::create($identity + [
            'verified_at' => now(),
            'status' => BackupVerification::FAILED,
            'failure_reason' => self::REASONS[$reason],
        ]);

        Log::error('Backup verification failed.', array_filter([
            'reason' => $reason,
            'backup' => $identity['backup_filename'] ?? null,
        ]));

        return $record;
    }
}
