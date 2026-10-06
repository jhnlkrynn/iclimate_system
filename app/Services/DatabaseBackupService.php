<?php

namespace App\Services;

use App\Exceptions\BackupFailedException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Database backups, retention and inspection: mysqldump (plain SQL) for a MySQL/MariaDB database, pg_dump
 * (custom format) for PostgreSQL. The tool is chosen from the connection's driver.
 *
 * Secrets: connection details come from the Laravel database configuration and
 * are handed to pg_dump through PG* environment variables, never as command
 * arguments. Nothing returned, printed or logged here contains credentials,
 * hosts, connection strings or file paths.
 *
 * Files: only names matching FILE_PATTERN inside the configured directory are
 * ever created, read for status, or deleted.
 */
class DatabaseBackupService
{
    /** iclimate_2026-09-25_1000.backup (an optional -SS suffix avoids same-minute clashes). */
    public const FILE_PATTERN = '/^iclimate_\d{4}-\d{2}-\d{2}_\d{4}(?:-\d{2})?\.backup$/';

    /** Every pg_dump custom-format archive starts with these bytes. */
    private const MAGIC = 'PGDMP';

    public const MIN_RETENTION_DAYS = 7;

    public const MAX_RETENTION_DAYS = 14;

    public function directory(): string
    {
        return rtrim((string) config('backup.directory'), '/\\');
    }

    public function retentionDays(): int
    {
        return max(self::MIN_RETENTION_DAYS, min(self::MAX_RETENTION_DAYS, (int) config('backup.retention_days', 14)));
    }

    public function maxAgeHours(): int
    {
        return max(1, (int) config('backup.max_age_hours', 36));
    }

    // ---------------------------------------------------------------- backup

    /**
     * @return array{file: string, bytes: int}
     *
     * @throws BackupFailedException
     */
    public function backup(): array
    {
        $connection = $this->connectionSettings();
        $directory = $this->ensureDirectory();

        if ($connection['driver'] !== 'pgsql') {
            return $this->backupMysql($connection, $directory);
        }

        $name = $this->newFileName($directory);
        $final = $directory.DIRECTORY_SEPARATOR.$name;
        $partial = $final.'.partial';

        $secrets = array_filter([$connection['host'], $connection['username'], $connection['password'], $connection['database']]);

        try {
            $result = Process::env($this->environment($connection))
                ->timeout(max(30, (int) config('backup.timeout_seconds', 900)))
                ->run([
                    (string) config('backup.pg_dump_binary', 'pg_dump'),
                    '--format=custom',
                    '--no-owner',
                    '--no-privileges',
                    '--schema='.$connection['schema'],
                    '--file='.$partial,
                ]);
        } catch (Throwable $exception) {
            $this->discard($partial);
            $this->logFailure('process_error', null, $exception::class);

            throw new BackupFailedException('pg_dump could not be started. Check that the PostgreSQL client tools are installed (or set PG_DUMP_BINARY).');
        }

        if (! $result->successful()) {
            $this->discard($partial);
            $exitCode = $result->exitCode();
            $detail = $this->redact($result->errorOutput(), $secrets);
            $this->logFailure('pg_dump_failed', $exitCode, $detail);

            throw new BackupFailedException($this->looksLikeMissingBinary($exitCode, $result->errorOutput())
                ? 'pg_dump was not found. Install the PostgreSQL client tools or set PG_DUMP_BINARY in .env.'
                : 'pg_dump failed (exit code '.$exitCode.'). The database was not backed up. See the application log for safe details.');
        }

        try {
            $bytes = $this->verify($partial);
        } catch (BackupFailedException $exception) {
            $this->discard($partial);
            $this->logFailure('verification_failed', null, $exception->getMessage());

            throw $exception;
        }

        if (! @rename($partial, $final)) {
            $this->discard($partial);
            $this->logFailure('rename_failed');

            throw new BackupFailedException('The backup was created but could not be saved in the backup folder.');
        }

        return ['file' => $name, 'bytes' => $bytes];
    }

    /**
     * mysqldump of the whole application database. The password travels in MYSQL_PWD, never on the command line.
     *
     * @return array{file: string, bytes: int}
     *
     * @throws BackupFailedException
     */
    private function backupMysql(array $connection, string $directory): array
    {
        $name = $this->newFileName($directory);
        $final = $directory.DIRECTORY_SEPARATOR.$name;
        $partial = $final.'.partial';
        $secrets = array_filter([$connection['host'], $connection['username'], $connection['password'], $connection['database']]);

        try {
            $result = Process::env(array_filter(['MYSQL_PWD' => $connection['password']], fn (string $value): bool => $value !== ''))
                ->timeout(max(30, (int) config('backup.timeout_seconds', 900)))
                ->run([
                    (string) config('backup.mysqldump_binary', 'mysqldump'),
                    '--host='.$connection['host'],
                    '--port='.$connection['port'],
                    '--user='.$connection['username'],
                    '--single-transaction',
                    '--routines',
                    '--no-tablespaces',
                    '--default-character-set=utf8mb4',
                    '--result-file='.$partial,
                    $connection['database'],
                ]);
        } catch (Throwable $exception) {
            $this->discard($partial);
            $this->logFailure('process_error', null, $exception::class);

            throw new BackupFailedException('mysqldump could not be started. Check that the MySQL client tools are installed (or set MYSQLDUMP_BINARY).');
        }

        if (! $result->successful()) {
            $this->discard($partial);
            $exitCode = $result->exitCode();
            $this->logFailure('mysqldump_failed', $exitCode, $this->redact($result->errorOutput(), $secrets));

            throw new BackupFailedException($this->looksLikeMissingBinary($exitCode, $result->errorOutput())
                ? 'mysqldump was not found. Install the MySQL client tools or set MYSQLDUMP_BINARY in .env.'
                : 'mysqldump failed (exit code '.$exitCode.'). The database was not backed up. See the application log for safe details.');
        }

        try {
            $bytes = $this->verify($partial);
            if (! $this->sqlDumpIsComplete($partial)) {
                throw new BackupFailedException('The backup file is incomplete, so it was rejected.');
            }
        } catch (BackupFailedException $exception) {
            $this->discard($partial);
            $this->logFailure('verification_failed', null, $exception->getMessage());

            throw $exception;
        }

        if (! @rename($partial, $final)) {
            $this->discard($partial);
            $this->logFailure('rename_failed');

            throw new BackupFailedException('The backup was created but could not be saved in the backup folder.');
        }

        return ['file' => $name, 'bytes' => $bytes];
    }

    /** Whether the file is a mysqldump SQL file (as opposed to a pg_dump archive). */
    public function isSqlDump(string $path): bool
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $head = (string) fread($handle, 400);
        fclose($handle);

        // The first lines of every dump name the tool: "-- MySQL dump 10.13 ..." or "-- MariaDB dump 10.19 ...".
        return (str_starts_with($head, '--') || str_starts_with($head, '/*'))
            && preg_match('/-- (MySQL|MariaDB) dump /', $head) === 1;
    }

    /** mysqldump writes "-- Dump completed" as its last line; a file without it was cut short. */
    public function sqlDumpIsComplete(string $path): bool
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        fseek($handle, -min(400, (int) filesize($path)), SEEK_END);
        $tail = (string) fread($handle, 400);
        fclose($handle);

        return str_contains($tail, '-- Dump completed');
    }

    /** The file must exist, be non-empty and be a real backup (a pg_dump archive or a mysqldump SQL file). */
    private function verify(string $path): int
    {
        clearstatcache(true, $path);

        if (! is_file($path)) {
            throw new BackupFailedException('The backup file was not created.');
        }

        $size = (int) filesize($path);
        if ($size <= 0) {
            throw new BackupFailedException('The backup file is empty, so it was rejected.');
        }

        if (! $this->hasBackupHeader($path)) {
            throw new BackupFailedException('The backup file is not a valid database backup, so it was rejected.');
        }

        return $size;
    }

    // ------------------------------------------------------------- retention

    /**
     * Deletes backups older than the retention window. The newest backup is
     * always kept, and only files that match FILE_PATTERN directly inside the
     * backup directory can be removed.
     */
    public function prune(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $cutoff = $now->subDays($this->retentionDays())->getTimestamp();

        $realDirectory = realpath($this->directory());
        if ($realDirectory === false || ! is_dir($realDirectory)) {
            return 0;
        }

        $files = $this->backupFiles($realDirectory);
        if ($files === []) {
            return 0;
        }

        usort($files, fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime']);
        array_shift($files); // never delete the newest backup

        $deleted = 0;
        foreach ($files as $file) {
            if ($file['mtime'] >= $cutoff || ! $this->isInside($realDirectory, $file['path'])) {
                continue;
            }

            if (@unlink($file['path'])) {
                $deleted++;
            }
        }

        return $deleted;
    }

    // ------------------------------------------------------------ inspection

    /**
     * Read-only status for System Health. Never runs pg_dump or writes files.
     *
     * @return array{state: 'ok'|'stale'|'missing'|'unavailable', last: ?CarbonImmutable, size: ?int, count: int}
     */
    public function status(): array
    {
        $directory = $this->directory();

        if (! file_exists($directory)) {
            return ['state' => 'missing', 'last' => null, 'size' => null, 'count' => 0];
        }

        $real = realpath($directory);
        if ($real === false || ! is_dir($real) || ! is_readable($real)) {
            return ['state' => 'unavailable', 'last' => null, 'size' => null, 'count' => 0];
        }

        try {
            $valid = array_values(array_filter(
                $this->backupFiles($real),
                fn (array $file): bool => $file['size'] > 0 && $this->hasBackupHeader($file['path']),
            ));
        } catch (Throwable) {
            return ['state' => 'unavailable', 'last' => null, 'size' => null, 'count' => 0];
        }

        if ($valid === []) {
            return ['state' => 'missing', 'last' => null, 'size' => null, 'count' => 0];
        }

        usort($valid, fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime']);
        $latest = $valid[0];
        $last = CarbonImmutable::createFromTimestamp($latest['mtime']);

        return [
            'state' => $last->lt(now()->subHours($this->maxAgeHours())) ? 'stale' : 'ok',
            'last' => $last,
            'size' => $latest['size'],
            'count' => count($valid),
        ];
    }

    /**
     * The newest complete backup: a non-empty file with a real archive header
     * whose name matches FILE_PATTERN. Partial and temporary files never match.
     * Read-only. Ties on modification time go to the later file name.
     *
     * @return array{name: string, path: string, size: int, mtime: int}|null
     */
    public function latestValidBackup(): ?array
    {
        $real = realpath($this->directory());

        if ($real === false || ! is_dir($real) || ! is_readable($real)) {
            return null;
        }

        try {
            $valid = array_values(array_filter(
                $this->backupFiles($real),
                fn (array $file): bool => $file['size'] > 0 && $this->hasBackupHeader($file['path']),
            ));
        } catch (Throwable) {
            return null;
        }

        if ($valid === []) {
            return null;
        }

        usort($valid, fn (array $a, array $b): int => [$b['mtime'], basename($b['path'])] <=> [$a['mtime'], basename($a['path'])]);

        return ['name' => basename($valid[0]['path'])] + $valid[0];
    }

    // --------------------------------------------------------------- helpers

    /**
     * @return array{driver: string, host: string, port: string, database: string, username: string, password: string, sslmode: string, schema: string}
     *
     * @throws BackupFailedException
     */
    private function connectionSettings(): array
    {
        $name = config('backup.connection') ?: config('database.default');
        $config = DB::connection($name)->getConfig();

        $driver = (string) ($config['driver'] ?? '');

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $settings = [
                'driver' => $driver,
                'host' => (string) ($config['host'] ?? ''),
                'port' => (string) ($config['port'] ?? '3306'),
                'database' => (string) ($config['database'] ?? ''),
                'username' => (string) ($config['username'] ?? ''),
                'password' => (string) ($config['password'] ?? ''),
                'sslmode' => '',
                'schema' => '',
            ];

            if ($settings['host'] === '' || $settings['database'] === '' || $settings['username'] === '') {
                throw new BackupFailedException('The database connection is not fully configured, so no backup was made.');
            }

            return $settings;
        }

        if ($driver !== 'pgsql') {
            throw new BackupFailedException('Backups are only available for a MySQL/MariaDB or PostgreSQL database.');
        }

        // The first entry of the search path is the application schema (e.g. the iClimate schema).
        $schema = trim(explode(',', (string) ($config['search_path'] ?? $config['schema'] ?? ''))[0], " \t\"'");

        if ($schema === '' || preg_match('/^[A-Za-z0-9_]+$/', $schema) !== 1) {
            throw new BackupFailedException('The database schema to back up is not configured correctly (check DB_SEARCH_PATH).');
        }

        $settings = [
            'driver' => 'pgsql',
            'host' => (string) ($config['host'] ?? ''),
            'port' => (string) ($config['port'] ?? '5432'),
            'database' => (string) ($config['database'] ?? ''),
            'username' => (string) ($config['username'] ?? ''),
            'password' => (string) ($config['password'] ?? ''),
            'sslmode' => (string) ($config['sslmode'] ?? ''),
            'schema' => $schema,
        ];

        if ($settings['host'] === '' || $settings['database'] === '' || $settings['username'] === '') {
            throw new BackupFailedException('The database connection is not fully configured, so no backup was made.');
        }

        return $settings;
    }

    /** @return array<string, string> */
    private function environment(array $connection): array
    {
        return array_filter([
            'PGHOST' => $connection['host'],
            'PGPORT' => $connection['port'],
            'PGDATABASE' => $connection['database'],
            'PGUSER' => $connection['username'],
            'PGPASSWORD' => $connection['password'],
            'PGSSLMODE' => $connection['sslmode'],
        ], fn (string $value): bool => $value !== '');
    }

    private function ensureDirectory(): string
    {
        $directory = $this->directory();

        if ($directory === '') {
            throw new BackupFailedException('The backup folder is not configured.');
        }

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new BackupFailedException('The backup folder could not be created.');
        }

        if (! is_writable($directory)) {
            throw new BackupFailedException('The backup folder is not writable.');
        }

        return $directory;
    }

    private function newFileName(string $directory): string
    {
        $stamp = now();
        $name = 'iclimate_'.$stamp->format('Y-m-d_Hi').'.backup';

        if (file_exists($directory.DIRECTORY_SEPARATOR.$name)) {
            $name = 'iclimate_'.$stamp->format('Y-m-d_Hi-s').'.backup';
        }

        return $name;
    }

    /** @return array<int, array{path: string, size: int, mtime: int}> */
    private function backupFiles(string $realDirectory): array
    {
        $entries = @scandir($realDirectory);
        if ($entries === false) {
            throw new BackupFailedException('The backup folder could not be read.');
        }

        $files = [];
        foreach ($entries as $entry) {
            if (preg_match(self::FILE_PATTERN, $entry) !== 1) {
                continue;
            }

            $path = $realDirectory.DIRECTORY_SEPARATOR.$entry;
            if (! is_file($path) || is_link($path)) {
                continue;
            }

            $files[] = ['path' => $path, 'size' => (int) filesize($path), 'mtime' => (int) filemtime($path)];
        }

        return $files;
    }

    private function isInside(string $realDirectory, string $path): bool
    {
        $real = realpath($path);

        return $real !== false && dirname($real) === $realDirectory;
    }

    private function hasBackupHeader(string $path): bool
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $head = fread($handle, strlen(self::MAGIC));
        fclose($handle);

        return $head === self::MAGIC || $this->isSqlDump($path);
    }

    private function discard(string $partial): void
    {
        if (is_file($partial)) {
            @unlink($partial);
        }
    }

    private function looksLikeMissingBinary(?int $exitCode, string $stderr): bool
    {
        return $exitCode === 127
            || preg_match('/not recognized|not found|no such file|cannot find/i', $stderr) === 1;
    }

    /** @param array<int, string> $secrets */
    private function redact(string $text, array $secrets): string
    {
        foreach ($secrets as $secret) {
            if ($secret !== '') {
                $text = str_ireplace($secret, '***', $text);
            }
        }

        $text = preg_replace('#[a-z][a-z0-9+.-]*://\S+#i', '***', $text) ?? '';
        $text = preg_replace('/password\s*[=:]\s*\S+/i', 'password=***', $text) ?? '';

        return mb_substr(trim($text), 0, 300);
    }

    private function logFailure(string $reason, ?int $exitCode = null, ?string $detail = null): void
    {
        Log::error('Database backup failed.', array_filter([
            'reason' => $reason,
            'exit_code' => $exitCode,
            'detail' => $detail,
        ], fn ($value) => $value !== null && $value !== ''));
    }
}
