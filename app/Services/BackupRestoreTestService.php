<?php

namespace App\Services;

use App\Exceptions\RestoreTestFailed;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

/**
 * Proves the newest backup can actually be restored, by restoring it into a
 * throwaway PostgreSQL container on this machine and checking what came back.
 *
 * Isolation: the container is created with --network none, so it cannot reach
 * Supabase or anything else, and this class never opens a connection to the
 * application database. The container's credentials are random, live only in
 * this process's environment (never on a command line), and are never returned
 * or logged. The container is removed in a finally block whatever happens.
 *
 * Output: only fixed sentences, counts, and the backup file name. Raw stderr,
 * exception messages and file paths are never returned or logged.
 */
class BackupRestoreTestService
{
    public const REASONS = [
        'no_valid_backup' => 'No valid backup file was found to test.',
        'docker_unavailable' => 'Docker is not available. Start Docker and try again.',
        'container_start_failed' => 'The temporary PostgreSQL container could not be started.',
        'startup_timeout' => 'The temporary PostgreSQL container did not become ready in time.',
        'copy_failed' => 'The backup could not be copied into the temporary container.',
        'restore_failed' => 'pg_restore reported an error while restoring the backup.',
        'restore_timeout' => 'The restore took too long and was stopped.',
        'query_failed' => 'The restored database could not be inspected.',
        'no_tables' => 'The restore finished but no application tables were found.',
        'tables_missing' => 'Some tables listed in the backup were not found after the restore.',
        'core_tables_missing' => 'Expected application tables were not found after the restore.',
        'unexpected_error' => 'The restore test stopped unexpectedly. See the application log.',
    ];

    private const IN_CONTAINER_FILE = '/tmp/restore-test.backup';

    public function __construct(private readonly DatabaseBackupService $backups) {}

    /**
     * @return array{
     *     passed: bool, reason: ?string, backup: ?string, tables_in_backup: ?int,
     *     tables_restored: ?int, core_tables_present: ?int, core_tables_expected: int,
     *     core_row_counts: array<string, int>, cleanup_ok: bool, container: ?string, seconds: int
     * }
     */
    public function run(): array
    {
        $started = microtime(true);
        $container = null;
        $containerCreated = false;

        $result = [
            'passed' => false,
            'reason' => null,
            'backup' => null,
            'tables_in_backup' => null,
            'tables_restored' => null,
            'core_tables_present' => null,
            'core_tables_expected' => count($this->coreTables()),
            'core_row_counts' => [],
            'cleanup_ok' => true,
            'container' => null,
            'seconds' => 0,
        ];

        try {
            $backup = $this->backups->latestValidBackup();

            if ($backup === null) {
                throw new RestoreTestFailed('no_valid_backup');
            }

            $result['backup'] = $backup['name'];

            $this->assertDockerAvailable();

            // Names and credentials are random and used once.
            $container = 'iclimate-restore-test-'.Str::lower(Str::random(10));
            $user = 'rt_'.Str::lower(Str::random(8));
            $database = 'rt_'.Str::lower(Str::random(8));
            $password = Str::random(40);
            $result['container'] = $container;

            $containerCreated = true; // from here on, cleanup must run even if `run` errors out
            $this->startContainer($container, $user, $database, $password);
            $this->waitUntilReady($container, $user, $database);
            $this->copyBackup($container, $backup['path']);

            $expected = $this->tablesInBackup($container);
            $result['tables_in_backup'] = count($expected);

            $this->restore($container, $user, $database);

            $restored = $this->restoredTables($container, $user, $database);
            $result['tables_restored'] = count($restored);

            if ($restored === []) {
                throw new RestoreTestFailed('no_tables');
            }

            $missing = array_diff($expected, $restored);

            $corePresent = array_filter($this->coreTables(), fn (string $table): bool => $this->findTable($restored, $table) !== null);
            $result['core_tables_present'] = count($corePresent);

            if (count($corePresent) !== count($this->coreTables())) {
                throw new RestoreTestFailed('core_tables_missing');
            }

            if ($missing !== []) {
                throw new RestoreTestFailed('tables_missing');
            }

            $result['core_row_counts'] = $this->coreRowCounts($container, $user, $database, $restored);
            $result['passed'] = true;

            Log::info('Backup restore test passed.', [
                'backup' => $backup['name'],
                'tables' => count($restored),
            ]);
        } catch (RestoreTestFailed $failure) {
            $result['reason'] = $failure->reason;
            $this->logFailure($failure->reason, $result['backup']);
        } catch (Throwable) {
            $result['reason'] = 'unexpected_error';
            $this->logFailure('unexpected_error', $result['backup']);
        } finally {
            if ($containerCreated && $container !== null) {
                $result['cleanup_ok'] = $this->removeContainer($container);
            }
        }

        $result['seconds'] = (int) round(microtime(true) - $started);

        return $result;
    }

    // ------------------------------------------------------------------ steps

    private function assertDockerAvailable(): void
    {
        try {
            $result = $this->docker(['version', '--format', '{{.Server.Version}}'], $this->timeout('docker_timeout_seconds', 20));
        } catch (Throwable) {
            throw new RestoreTestFailed('docker_unavailable');
        }

        if (! $result->successful()) {
            throw new RestoreTestFailed('docker_unavailable');
        }
    }

    private function startContainer(string $name, string $user, string $database, string $password): void
    {
        try {
            $result = $this->docker([
                'run', '-d',
                '--name', $name,
                '--network', 'none',
                '--label', 'iclimate.restore-test=1',
                '-e', 'POSTGRES_PASSWORD', // value comes from the environment, not the command line
                '-e', 'POSTGRES_USER='.$user,
                '-e', 'POSTGRES_DB='.$database,
                (string) config('backup.restore_test_image', 'postgres:17'),
            ], $this->timeout('restore_start_timeout_seconds', 300), ['POSTGRES_PASSWORD' => $password]);
        } catch (Throwable) {
            throw new RestoreTestFailed('container_start_failed');
        }

        if (! $result->successful()) {
            throw new RestoreTestFailed('container_start_failed');
        }
    }

    /**
     * pg_isready over TCP: during first-time setup the image runs a temporary
     * server that only listens on a unix socket, so a TCP answer means the
     * real server is up.
     */
    private function waitUntilReady(string $container, string $user, string $database): void
    {
        $deadline = microtime(true) + $this->timeout('restore_ready_timeout_seconds', 90);
        $interval = max(0, (int) config('backup.restore_ready_interval_ms', 1000));

        do {
            try {
                $result = $this->docker(['exec', $container, 'pg_isready', '-h', '127.0.0.1', '-U', $user, '-d', $database], 15);

                if ($result->successful()) {
                    return;
                }
            } catch (Throwable) {
                // Not ready yet; keep waiting until the deadline.
            }

            if ($interval > 0) {
                usleep($interval * 1000);
            }
        } while (microtime(true) < $deadline);

        throw new RestoreTestFailed('startup_timeout');
    }

    private function copyBackup(string $container, string $hostPath): void
    {
        try {
            $result = $this->docker(['cp', $hostPath, $container.':'.self::IN_CONTAINER_FILE], $this->timeout('restore_timeout_seconds', 900));
        } catch (Throwable) {
            throw new RestoreTestFailed('copy_failed');
        }

        if (! $result->successful()) {
            throw new RestoreTestFailed('copy_failed');
        }
    }

    /**
     * Tables the archive says it contains, as "schema.table". Read with the
     * container's own pg_restore so the version always matches the restore.
     *
     * @return list<string>
     */
    private function tablesInBackup(string $container): array
    {
        $result = $this->step(['exec', $container, 'pg_restore', '--list', self::IN_CONTAINER_FILE], 'query_failed');

        // "TABLE schema name owner" entries only; "TABLE DATA ..." lines are the rows, not the tables.
        preg_match_all('/^\d+;\s+\d+\s+\d+\s+TABLE\s+(?!DATA\s)(\S+)\s+(\S+)(?:\s|$)/m', $result->output(), $matches, PREG_SET_ORDER);

        return array_values(array_unique(array_map(fn (array $m): string => $m[1].'.'.$m[2], $matches)));
    }

    private function restore(string $container, string $user, string $database): void
    {
        try {
            $result = $this->docker([
                'exec', $container, 'pg_restore',
                '--no-owner', '--no-privileges',
                '-U', $user, '-d', $database,
                self::IN_CONTAINER_FILE,
            ], $this->timeout('restore_timeout_seconds', 900));
        } catch (ProcessTimedOutException) {
            throw new RestoreTestFailed('restore_timeout');
        } catch (Throwable) {
            throw new RestoreTestFailed('restore_failed');
        }

        if (! $result->successful()) {
            throw new RestoreTestFailed('restore_failed');
        }
    }

    /** @return list<string> "schema.table" for every user table in the temporary database */
    private function restoredTables(string $container, string $user, string $database): array
    {
        $sql = "select schemaname || '.' || tablename from pg_tables where schemaname not in ('pg_catalog','information_schema') order by 1";
        $result = $this->step(['exec', $container, 'psql', '-U', $user, '-d', $database, '-At', '-c', $sql], 'query_failed');

        return array_values(array_filter(array_map('trim', explode("\n", $result->output()))));
    }

    /**
     * Optional sanity information: row counts of the core tables. Reported,
     * never compared with the live database (which has moved on since the backup).
     *
     * @param  list<string>  $restored
     * @return array<string, int>
     */
    private function coreRowCounts(string $container, string $user, string $database, array $restored): array
    {
        $counts = [];

        foreach ($this->coreTables() as $table) {
            $qualified = $this->findTable($restored, $table);

            // Names come from pg_tables but are still checked before use in SQL.
            if ($qualified === null || preg_match('/^[A-Za-z0-9_]+\.[A-Za-z0-9_]+$/', $qualified) !== 1) {
                continue;
            }

            try {
                $result = $this->docker(['exec', $container, 'psql', '-U', $user, '-d', $database, '-At', '-c', 'select count(*) from '.$qualified], 60);
            } catch (Throwable) {
                continue;
            }

            if ($result->successful() && is_numeric(trim($result->output()))) {
                $counts[$table] = (int) trim($result->output());
            }
        }

        return $counts;
    }

    /** Removes the container. Returns whether it is gone. */
    private function removeContainer(string $container): bool
    {
        try {
            $this->docker(['rm', '-f', '-v', $container], $this->timeout('docker_timeout_seconds', 20) + 40);
            $check = $this->docker(['ps', '-a', '-q', '--filter', 'name=^'.$container.'$'], 30);

            $gone = $check->successful() && trim($check->output()) === '';
        } catch (Throwable) {
            $gone = false;
        }

        if (! $gone) {
            // The name is random and safe to show; it lets the person remove it by hand.
            Log::warning('Temporary restore-test container could not be removed.', ['container' => $container]);
        }

        return $gone;
    }

    // ---------------------------------------------------------------- helpers

    /** @return list<string> */
    private function coreTables(): array
    {
        return array_values((array) config('backup.restore_test_core_tables', []));
    }

    /**
     * @param  list<string>  $restored
     */
    private function findTable(array $restored, string $table): ?string
    {
        foreach ($restored as $qualified) {
            if (str_ends_with($qualified, '.'.$table)) {
                return $qualified;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $arguments
     * @param  array<string, string>  $environment
     *
     * @throws Throwable
     */
    private function docker(array $arguments, int $timeout, array $environment = []): ProcessResult
    {
        return Process::env($environment)
            ->timeout($timeout)
            ->run([(string) config('backup.docker_binary', 'docker'), ...$arguments]);
    }

    /**
     * Runs a docker step that must succeed; any failure becomes $reason.
     *
     * @param  list<string>  $arguments
     */
    private function step(array $arguments, string $reason): ProcessResult
    {
        try {
            $result = $this->docker($arguments, $this->timeout('restore_timeout_seconds', 900));
        } catch (Throwable) {
            throw new RestoreTestFailed($reason);
        }

        if (! $result->successful()) {
            throw new RestoreTestFailed($reason);
        }

        return $result;
    }

    private function timeout(string $key, int $default): int
    {
        return max(1, (int) config('backup.'.$key, $default));
    }

    private function logFailure(string $reason, ?string $backup): void
    {
        Log::error('Backup restore test failed.', array_filter(['reason' => $reason, 'backup' => $backup]));
    }
}
