<?php

namespace Tests\Feature;

use App\Services\BackupRestoreTestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyTimeout;
use Symfony\Component\Process\Process as SymfonyProcess;
use Tests\TestCase;

class BackupRestoreTestTest extends TestCase
{
    use RefreshDatabase;

    private const CORE = ['users', 'climate_records', 'rice_productions', 'farmer_profiles', 'system_logs', 'migrations'];

    private string $root;

    private string $dir;

    /** @var list<array{command: array<int, string>, env: array<string, string>}> */
    private array $calls = [];

    private bool $containerExists = false;

    /** @var array<string, mixed> */
    private array $mode = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/restore-'.uniqid());
        $this->dir = $this->root.'/backups';
        File::ensureDirectoryExists($this->dir);

        config([
            'backup.directory' => $this->dir,
            'backup.docker_binary' => 'docker',
            'backup.restore_test_image' => 'postgres:17',
            'backup.restore_ready_timeout_seconds' => 1,
            'backup.restore_ready_interval_ms' => 0,
            'backup.restore_test_core_tables' => self::CORE,
            // Supabase-style credentials that must never reach the temporary container.
            'database.connections.pgsql.host' => 'db.secret-host.example',
            'database.connections.pgsql.password' => 'supabase-secret-pw',
            'database.connections.pgsql.database' => 'supabase_secret_db',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    private function backup(string $name = 'iclimate_2026-09-25_0315.backup', string $contents = "PGDMP\x01\x0e-body"): string
    {
        $path = $this->dir.'/'.$name;
        file_put_contents($path, $contents);

        return $path;
    }

    /** @return list<string> "schema.table" names, as pg_tables prints them */
    private function allTables(): array
    {
        return [...array_map(fn ($t) => 'iclimate_laravel.'.$t, self::CORE), 'iclimate_laravel.ml_models'];
    }

    /**
     * Scripted fake Docker. $mode keys (all optional):
     *   docker: ok|down|throw   run: ok|fail   ready: ok|never   cp: ok|fail|throw
     *   restore: ok|fail|timeout   rm: ok|fail   listed: string[]   restored: string[]   rows: int
     */
    private function fakeDocker(array $mode = []): void
    {
        $this->mode = $mode + [
            'docker' => 'ok', 'run' => 'ok', 'ready' => 'ok', 'cp' => 'ok', 'restore' => 'ok', 'rm' => 'ok',
            'listed' => $this->allTables(), 'restored' => $this->allTables(), 'rows' => 42,
        ];

        Process::fake(function (PendingProcess $process) {
            $command = array_map('strval', $process->command);
            $this->calls[] = ['command' => $command, 'env' => $process->environment];
            $m = $this->mode;
            $verb = $command[1] ?? '';
            $ok = fn (string $out = '') => Process::result(output: $out);
            $fail = fn (string $err = 'raw docker stderr with secret-path') => Process::result(errorOutput: $err, exitCode: 1);

            return match ($verb) {
                'version' => match ($m['docker']) {
                    'ok' => $ok("27.0.1\n"),
                    'throw' => throw new \RuntimeException('docker: not recognized C:\\secret\\docker.exe'),
                    default => $fail('error during connect: //./pipe/dockerDesktopLinuxEngine'),
                },
                'run' => $m['run'] === 'ok' ? tap($ok('abc123'), fn () => $this->containerExists = true) : $fail(),
                'cp' => match ($m['cp']) {
                    'ok' => $ok(),
                    'throw' => throw new \RuntimeException('cannot copy from C:\\secret\\path'),
                    default => $fail(),
                },
                'rm' => $m['rm'] === 'ok' ? tap($ok(), fn () => $this->containerExists = false) : $fail(),
                'ps' => $ok($this->containerExists ? "abc123\n" : ''),
                'exec' => $this->fakeExec($command, $m, $ok, $fail),
                default => $fail(),
            };
        });
    }

    private function fakeExec(array $command, array $m, \Closure $ok, \Closure $fail): mixed
    {
        $tool = $command[3] ?? '';

        if ($tool === 'pg_isready') {
            return $m['ready'] === 'ok' ? $ok('accepting connections') : Process::result(exitCode: 2);
        }

        if ($tool === 'pg_restore' && in_array('--list', $command, true)) {
            $lines = [';', '; Archive created at 2026-09-25', ';'];
            foreach ($m['listed'] as $qualified) {
                [$schema, $table] = explode('.', $qualified);
                $lines[] = "215; 1259 16400 TABLE {$schema} {$table} postgres";
                $lines[] = "4120; 0 16400 TABLE DATA {$schema} {$table} postgres";
            }

            return $ok(implode("\n", $lines));
        }

        if ($tool === 'pg_restore') {
            return match ($m['restore']) {
                'ok' => $ok(),
                'timeout' => throw new ProcessTimedOutException(
                    new SymfonyTimeout(new SymfonyProcess(['docker']), SymfonyTimeout::TYPE_GENERAL),
                    Process::result(),
                ),
                default => $fail('pg_restore: error: password=hunter2 could not execute query'),
            };
        }

        if ($tool === 'psql') {
            $sql = (string) end($command);

            return str_starts_with($sql, 'select count(*)')
                ? $ok((string) $m['rows']."\n")
                : $ok(implode("\n", $m['restored'])."\n");
        }

        return $fail();
    }

    private function restoreTest(): array
    {
        return app(BackupRestoreTestService::class)->run();
    }

    /** @return list<string> the docker verbs run, e.g. ['version', 'run', 'exec', ...] */
    private function verbs(): array
    {
        return array_map(fn ($call) => $call['command'][1] ?? '', $this->calls);
    }

    private function assertCleanedUp(array $result): void
    {
        $this->assertContains('rm', $this->verbs(), 'the container must be removed');
        $this->assertFalse($this->containerExists, 'no container may be left behind');
        $this->assertTrue($result['cleanup_ok']);
    }

    // ---------------------------------------------------------------- success

    public function test_a_valid_backup_restores_into_an_isolated_temporary_container(): void
    {
        $this->backup();
        $this->fakeDocker();

        $result = $this->restoreTest();

        $this->assertTrue($result['passed']);
        $this->assertNull($result['reason']);
        $this->assertSame('iclimate_2026-09-25_0315.backup', $result['backup']);
        $this->assertSame(7, $result['tables_in_backup']);
        $this->assertSame(7, $result['tables_restored']);
        $this->assertSame(6, $result['core_tables_present']);
        $this->assertSame(42, $result['core_row_counts']['users']);
        $this->assertCleanedUp($result);

        $run = collect($this->calls)->first(fn ($c) => ($c['command'][1] ?? '') === 'run');
        $joined = implode(' ', $run['command']);

        // Isolated: no network, and it is a fresh local postgres image.
        $this->assertStringContainsString('--network none', $joined);
        $this->assertStringContainsString('postgres:17', $joined);
        // Credentials travel in the environment, never in an argument.
        $this->assertArrayHasKey('POSTGRES_PASSWORD', $run['env']);
        $this->assertSame(40, strlen($run['env']['POSTGRES_PASSWORD']));
        $this->assertStringNotContainsString($run['env']['POSTGRES_PASSWORD'], implode(' ', array_map(fn ($c) => implode(' ', $c['command']), $this->calls)));
    }

    public function test_the_restore_never_targets_supabase_or_the_application_database(): void
    {
        $this->backup();
        $this->fakeDocker();

        $this->restoreTest();

        $everything = json_encode($this->calls);
        foreach (['db.secret-host.example', 'supabase-secret-pw', 'supabase_secret_db'] as $applicationSecret) {
            $this->assertStringNotContainsString($applicationSecret, $everything);
        }

        // Every pg_restore/psql call runs inside the container, via docker exec, with the throwaway credentials.
        foreach ($this->calls as $call) {
            $this->assertSame('docker', $call['command'][0]);
            $this->assertNotContains('--host', $call['command']);
            $this->assertNotContains('-h', array_slice($call['command'], 0, 4));
        }
    }

    public function test_each_run_uses_a_fresh_random_container_and_password(): void
    {
        $this->backup();
        $this->fakeDocker();
        $first = $this->restoreTest()['container'];
        $firstPassword = collect($this->calls)->first(fn ($c) => ($c['command'][1] ?? '') === 'run')['env']['POSTGRES_PASSWORD'];

        $this->calls = [];
        $second = $this->restoreTest()['container'];
        $secondPassword = collect($this->calls)->first(fn ($c) => ($c['command'][1] ?? '') === 'run')['env']['POSTGRES_PASSWORD'];

        $this->assertNotSame($first, $second);
        $this->assertNotSame($firstPassword, $secondPassword);
        $this->assertStringStartsWith('iclimate-restore-test-', $first);
    }

    // ---------------------------------------------------------------- failures

    public function test_restore_failure_is_reported_safely_and_cleans_up(): void
    {
        $this->backup();
        $this->fakeDocker(['restore' => 'fail']);

        $result = $this->restoreTest();

        $this->assertFalse($result['passed']);
        $this->assertSame('restore_failed', $result['reason']);
        $this->assertCleanedUp($result);
    }

    public function test_restore_timeout_is_reported_and_cleans_up(): void
    {
        $this->backup();
        $this->fakeDocker(['restore' => 'timeout']);

        $result = $this->restoreTest();

        $this->assertSame('restore_timeout', $result['reason']);
        $this->assertCleanedUp($result);
    }

    public function test_docker_unavailable_fails_before_creating_anything(): void
    {
        $this->backup();

        foreach (['down', 'throw'] as $variant) {
            $this->calls = [];
            $this->fakeDocker(['docker' => $variant]);

            $result = $this->restoreTest();

            $this->assertFalse($result['passed']);
            $this->assertSame('docker_unavailable', $result['reason']);
            $this->assertNull($result['container']);
            $this->assertSame(['version'], $this->verbs(), 'nothing may be created or removed');
        }
    }

    public function test_postgres_startup_timeout_fails_and_cleans_up(): void
    {
        $this->backup();
        $this->fakeDocker(['ready' => 'never']);

        $result = $this->restoreTest();

        $this->assertSame('startup_timeout', $result['reason']);
        $this->assertNotContains('cp', $this->verbs(), 'nothing may be copied to a server that is not ready');
        $this->assertCleanedUp($result);
    }

    public function test_container_start_failure_still_attempts_cleanup(): void
    {
        $this->backup();
        $this->fakeDocker(['run' => 'fail']);

        $result = $this->restoreTest();

        $this->assertSame('container_start_failed', $result['reason']);
        $this->assertContains('rm', $this->verbs());
        $this->assertTrue($result['cleanup_ok']);
    }

    public function test_cleanup_runs_even_when_a_step_throws_unexpectedly(): void
    {
        $this->backup();
        $this->fakeDocker(['cp' => 'throw']);

        $result = $this->restoreTest();

        $this->assertSame('copy_failed', $result['reason']);
        $this->assertCleanedUp($result);
    }

    public function test_an_empty_restored_database_fails_and_cleans_up(): void
    {
        $this->backup();
        $this->fakeDocker();
        // Restore "succeeds" but leaves no tables behind.
        $this->mode['restored'] = [];

        $result = $this->restoreTest();

        $this->assertSame('no_tables', $result['reason']);
        $this->assertCleanedUp($result);
    }

    public function test_a_container_that_cannot_be_removed_is_reported_with_its_safe_name(): void
    {
        $this->backup();
        $this->fakeDocker(['rm' => 'fail']);
        $logs = [];
        Log::listen(function ($e) use (&$logs) {
            $logs[] = [$e->level, $e->message, $e->context];
        });

        $result = $this->restoreTest();

        $this->assertTrue($result['passed']);
        $this->assertFalse($result['cleanup_ok']);
        $warning = collect($logs)->first(fn ($l) => $l[0] === 'warning');
        $this->assertSame($result['container'], $warning[2]['container']);

        $this->artisan('iclimate:test-restore')
            ->expectsOutputToContain('could not be removed. Run: docker rm -f iclimate-restore-test-')
            ->assertExitCode(1);
    }

    public function test_missing_tables_after_restore_fail_the_test(): void
    {
        $this->backup();
        $this->fakeDocker(['restored' => array_slice($this->allTables(), 0, 6)]); // ml_models did not come back

        $result = $this->restoreTest();

        $this->assertSame('tables_missing', $result['reason']);
        $this->assertCleanedUp($result);
    }

    public function test_missing_core_tables_fail_the_test(): void
    {
        $this->backup();
        $withoutUsers = array_values(array_diff($this->allTables(), ['iclimate_laravel.users']));
        $this->fakeDocker(['listed' => $withoutUsers, 'restored' => $withoutUsers]);

        $result = $this->restoreTest();

        $this->assertSame('core_tables_missing', $result['reason']);
        $this->assertSame(5, $result['core_tables_present']);
        $this->assertCleanedUp($result);
    }

    // ------------------------------------------------------------- no backup

    public function test_no_backup_available_fails_without_touching_docker(): void
    {
        $this->fakeDocker();

        $result = $this->restoreTest();

        $this->assertSame('no_valid_backup', $result['reason']);
        $this->assertNull($result['container']);
        Process::assertNothingRan();
    }

    public function test_zero_byte_invalid_and_partial_files_do_not_count_as_a_backup(): void
    {
        $this->backup('iclimate_2026-09-25_0315.backup', '');
        $this->backup('iclimate_2026-09-25_0316.backup', 'not an archive');
        $this->backup('iclimate_2026-09-25_0317.backup.partial', "PGDMP\x01partial");
        $this->fakeDocker();

        $this->assertSame('no_valid_backup', $this->restoreTest()['reason']);
        Process::assertNothingRan();
    }

    public function test_the_latest_valid_backup_is_the_one_copied(): void
    {
        $this->backup('iclimate_2026-09-24_0315.backup');
        $newest = $this->backup('iclimate_2026-09-25_0315.backup');
        touch($this->dir.'/iclimate_2026-09-24_0315.backup', time() - 86400);
        $this->fakeDocker();

        $this->restoreTest();

        $copy = collect($this->calls)->first(fn ($c) => ($c['command'][1] ?? '') === 'cp');
        $this->assertSame(realpath($newest), $copy['command'][2]);
    }

    // ---------------------------------------------------------------- command

    public function test_command_reports_passed_and_never_prints_credentials_or_paths(): void
    {
        $this->backup();
        $this->fakeDocker();

        $exit = Artisan::call('iclimate:test-restore');
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Restore test PASSED for iclimate_2026-09-25_0315.backup', $output);
        $this->assertStringContainsString('Tables in backup / restored: 7 / 7', $output);
        $this->assertStringContainsString('Rows in users: 42', $output);
        $this->assertStringContainsString('Temporary container removed.', $output);

        $password = collect($this->calls)->first(fn ($c) => ($c['command'][1] ?? '') === 'run')['env']['POSTGRES_PASSWORD'];
        foreach ([$password, $this->dir, $this->root, base_path(), storage_path()] as $leak) {
            $this->assertStringNotContainsString($leak, $output);
        }
    }

    public function test_command_reports_failed_without_raw_stderr_or_paths(): void
    {
        $this->backup();
        $this->fakeDocker(['restore' => 'fail']);
        $logs = [];
        Log::listen(function ($e) use (&$logs) {
            $logs[] = $e->message.' '.json_encode($e->context);
        });

        $exit = Artisan::call('iclimate:test-restore');
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Restore test FAILED: pg_restore reported an error', $output);
        $this->assertStringContainsString('Temporary container removed.', $output);

        foreach (['hunter2', 'could not execute query', 'secret-path', $this->dir, $this->root, base_path(), storage_path()] as $leak) {
            $this->assertStringNotContainsString($leak, $output);
            $this->assertStringNotContainsString($leak, implode("\n", $logs));
        }
        $this->assertNotEmpty($logs);
    }

    public function test_command_reports_docker_unavailable(): void
    {
        $this->backup();
        $this->fakeDocker(['docker' => 'throw']);

        $this->artisan('iclimate:test-restore')
            ->expectsOutputToContain('Restore test FAILED: Docker is not available')
            ->assertExitCode(1);
    }

    // ----------------------------------------------------------------- manual

    public function test_the_restore_test_is_manual_only_and_the_backup_jobs_are_unchanged(): void
    {
        // Times of day in the schedule are Philippine time since 2026-10-06 (config app.schedule_timezone); list them that way.
        Artisan::call('schedule:list', ['--timezone' => 'Asia/Manila']);
        $list = Artisan::output();

        $this->assertStringNotContainsString('iclimate:test-restore', $list);
        $this->assertMatchesRegularExpression('/15\s+3\s+\*\s+\*\s+\*\s+php artisan iclimate:backup-database/', $list);
        $this->assertMatchesRegularExpression('/25\s+3\s+\*\s+\*\s+\*\s+php artisan iclimate:verify-backup/', $list);
    }
}
