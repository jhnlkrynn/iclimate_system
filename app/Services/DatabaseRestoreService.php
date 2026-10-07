<?php

namespace App\Services;

use App\Exceptions\BackupFailedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Manuscript – Naicel (2026-10-08), Phase 8: restores the database from one of the backups (IT, Backups page). A fresh
 * backup of the current database is always made first, so a wrong restore can be undone. MySQL/MariaDB backups (SQL
 * dumps) are restored with the mysql client; a PostgreSQL archive is not restored from the web page.
 */
class DatabaseRestoreService
{
    public function __construct(private readonly DatabaseBackupService $backups) {}

    /** Full path of a backup in the backup folder, or null when the name is not one of ours. */
    public function pathFor(string $name): ?string
    {
        if (! preg_match(DatabaseBackupService::FILE_PATTERN, $name)) {
            return null;
        }
        $path = rtrim($this->backups->directory(), '\\/').DIRECTORY_SEPARATOR.$name;

        return is_file($path) ? $path : null;
    }

    /** @return array{safety_backup: string, restored: string} */
    public function restore(string $name): array
    {
        $path = $this->pathFor($name) ?? throw new RuntimeException('That backup was not found.');
        $connection = DB::connection(config('backup.connection') ?: config('database.default'))->getConfig();
        if (! in_array($connection['driver'] ?? '', ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Restoring from this page works for MySQL/MariaDB. Restore a PostgreSQL archive with pg_restore.');
        }
        if (! $this->backups->isSqlDump($path) || ! $this->backups->sqlDumpIsComplete($path)) {
            throw new RuntimeException('That backup is not a complete SQL dump, so it was not restored.');
        }

        // Safety first: the database as it is now.
        try {
            $safety = $this->backups->backup();
        } catch (BackupFailedException $e) {
            throw new RuntimeException('A safety backup could not be made, so nothing was restored. '.$e->getMessage());
        }

        $handle = fopen($path, 'rb');
        try {
            $result = Process::env(array_filter(['MYSQL_PWD' => (string) ($connection['password'] ?? '')], fn ($v) => $v !== ''))
                ->timeout(max(60, (int) config('backup.timeout_seconds', 900)))
                ->input($handle)
                ->run([
                    (string) config('backup.mysql_binary', is_file('C:/xampp/mysql/bin/mysql.exe') ? 'C:/xampp/mysql/bin/mysql.exe' : 'mysql'),
                    '--host='.($connection['host'] ?? '127.0.0.1'),
                    '--port='.($connection['port'] ?? '3306'),
                    '--user='.($connection['username'] ?? ''),
                    '--default-character-set=utf8mb4',
                    (string) ($connection['database'] ?? ''),
                ]);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if (! $result->successful()) {
            Log::error('Database restore failed.', ['backup' => $name, 'exit_code' => $result->exitCode()]);
            throw new RuntimeException('The restore failed (exit code '.$result->exitCode().'). The safety backup '.$safety['file'].' holds the database as it was.');
        }

        return ['safety_backup' => $safety['file'], 'restored' => $name];
    }
}
