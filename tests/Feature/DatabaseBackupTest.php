<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DatabaseBackupService;
use App\Services\SystemHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'secret-pass-123';

    private const HOST = 'db.secret-host.example';

    private const USER = 'secret_user';

    private const DATABASE = 'secret_db';

    private string $root;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/backup-'.uniqid());
        $this->dir = $this->root.'/backups';
        File::ensureDirectoryExists($this->dir);

        // A PostgreSQL connection is configured only for the backup tools; the
        // application's own (test) database connection is left untouched.
        config([
            'backup.directory' => $this->dir,
            'backup.connection' => 'pg_backup_test',
            'backup.retention_days' => 14,
            'backup.max_age_hours' => 36,
            'database.connections.pg_backup_test' => [
                'driver' => 'pgsql',
                'host' => self::HOST,
                'port' => '5432',
                'database' => self::DATABASE,
                'username' => self::USER,
                'password' => self::PASSWORD,
                'search_path' => 'iclimate_laravel,public',
                'sslmode' => 'require',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    /** Fakes pg_dump: writes $contents to the --file argument (unless null) and returns the given result. */
    private function fakePgDump(?string $contents = "PGDMP\x01\x0e-dump-body", int $exitCode = 0, string $stderr = ''): void
    {
        Process::fake(function (PendingProcess $process) use ($contents, $exitCode, $stderr) {
            $fileArgument = collect($process->command)->first(fn ($argument) => str_starts_with((string) $argument, '--file='));

            if ($contents !== null && $fileArgument) {
                file_put_contents(substr((string) $fileArgument, 7), $contents);
            }

            return Process::result(output: '', errorOutput: $stderr, exitCode: $exitCode);
        });
    }

    private function backupFile(string $name, int $ageDays, string $contents = "PGDMP\x01-body"): string
    {
        $path = $this->dir.'/'.$name;
        file_put_contents($path, $contents);
        touch($path, now()->subDays($ageDays)->getTimestamp());

        return $path;
    }

    private function files(): array
    {
        return array_values(array_diff(scandir($this->dir), ['.', '..']));
    }

    // ---------------------------------------------------------------- command

    public function test_command_creates_a_valid_backup_file(): void
    {
        $this->fakePgDump();

        $this->artisan('iclimate:backup-database')->assertExitCode(0);

        $files = $this->files();
        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression(DatabaseBackupService::FILE_PATTERN, $files[0]);
        $this->assertGreaterThan(0, filesize($this->dir.'/'.$files[0]));
        $this->assertStringNotContainsString('.partial', implode(',', $files));
    }

    public function test_file_is_named_with_a_timestamp(): void
    {
        $this->fakePgDump();
        $this->travelTo(now()->setDate(2026, 9, 25)->setTime(10, 0));

        $this->artisan('iclimate:backup-database')->assertExitCode(0);

        $this->assertSame(['iclimate_2026-09-25_1000.backup'], $this->files());
    }

    public function test_pg_dump_uses_custom_format_the_configured_schema_and_env_credentials(): void
    {
        $this->fakePgDump();

        $this->artisan('iclimate:backup-database')->assertExitCode(0);

        Process::assertRan(function (PendingProcess $process): bool {
            $command = implode(' ', (array) $process->command);

            // Format and schema come from options/config; credentials only via PG* environment variables.
            $this->assertStringContainsString('--format=custom', $command);
            $this->assertStringContainsString('--schema=iclimate_laravel', $command);
            foreach ([self::PASSWORD, self::HOST, self::USER, self::DATABASE] as $secret) {
                $this->assertStringNotContainsString($secret, $command);
            }
            $this->assertSame(self::PASSWORD, $process->environment['PGPASSWORD']);
            $this->assertSame(self::HOST, $process->environment['PGHOST']);
            $this->assertSame('require', $process->environment['PGSSLMODE']);

            return true;
        });
    }

    public function test_empty_backup_is_rejected_and_not_kept(): void
    {
        $this->fakePgDump('');

        $this->artisan('iclimate:backup-database')
            ->expectsOutputToContain('empty')
            ->assertExitCode(1);

        $this->assertSame([], $this->files());
    }

    public function test_missing_backup_file_is_rejected(): void
    {
        $this->fakePgDump(null);

        $this->artisan('iclimate:backup-database')->assertExitCode(1);

        $this->assertSame([], $this->files());
    }

    public function test_a_file_that_is_not_a_postgres_backup_is_rejected(): void
    {
        $this->fakePgDump('this is not a pg_dump archive');

        $this->artisan('iclimate:backup-database')
            ->expectsOutputToContain('not a valid PostgreSQL backup')
            ->assertExitCode(1);

        $this->assertSame([], $this->files());
    }

    public function test_failed_pg_dump_gives_a_clear_error_and_keeps_existing_backups(): void
    {
        $existing = $this->backupFile('iclimate_2026-09-20_0315.backup', 3);
        $old = $this->backupFile('iclimate_2026-08-01_0315.backup', 40);
        $this->fakePgDump('PGDMP-partial', 1, 'pg_dump: error: server closed the connection');

        $this->artisan('iclimate:backup-database')
            ->expectsOutputToContain('pg_dump failed (exit code 1)')
            ->assertExitCode(1);

        $this->assertFileExists($existing);
        $this->assertFileExists($old, 'Nothing is pruned when the backup itself failed.');
        $this->assertCount(2, $this->files());
    }

    public function test_missing_pg_dump_program_gives_an_install_hint(): void
    {
        $this->fakePgDump(null, 127, "'pg_dump' is not recognized as an internal or external command");

        $this->artisan('iclimate:backup-database')
            ->expectsOutputToContain('pg_dump was not found')
            ->assertExitCode(1);
    }

    public function test_only_postgres_connections_can_be_backed_up(): void
    {
        config(['backup.connection' => null]); // falls back to the test SQLite connection
        Process::fake();

        $this->artisan('iclimate:backup-database')
            ->expectsOutputToContain('only available for a PostgreSQL database')
            ->assertExitCode(1);

        Process::assertNothingRan();
    }

    // ---------------------------------------------------------------- secrets

    public function test_failure_output_and_logs_never_contain_secrets(): void
    {
        Log::spy();
        $leaky = 'pg_dump: error: connection to server at "'.self::HOST.'" (10.1.2.3), port 5432 failed: '
            .'FATAL: password authentication failed for user "'.self::USER.'" password='.self::PASSWORD
            .' postgresql://'.self::USER.':'.self::PASSWORD.'@'.self::HOST.':5432/'.self::DATABASE;
        $this->fakePgDump(null, 1, $leaky);

        Artisan::call('iclimate:backup-database');
        $output = Artisan::output();

        foreach ([self::PASSWORD, self::HOST, self::USER, self::DATABASE, 'postgresql://', $this->dir, base_path()] as $secret) {
            $this->assertStringNotContainsString($secret, $output);
        }

        Log::shouldHaveReceived('error')->once()->withArgs(function (string $message, array $context): bool {
            $logged = $message.json_encode($context);
            foreach ([self::PASSWORD, self::HOST, self::USER, self::DATABASE, 'postgresql://'] as $secret) {
                $this->assertStringNotContainsString($secret, $logged);
            }

            return true;
        });
    }

    public function test_success_output_shows_only_the_file_name(): void
    {
        $this->fakePgDump();

        Artisan::call('iclimate:backup-database');
        $output = Artisan::output();

        $this->assertStringContainsString('Backup created: iclimate_', $output);
        foreach ([self::PASSWORD, self::HOST, self::USER, self::DATABASE, $this->dir] as $secret) {
            $this->assertStringNotContainsString($secret, $output);
        }
    }

    // -------------------------------------------------------------- retention

    public function test_old_backups_are_cleaned_up_and_recent_ones_kept(): void
    {
        $tooOld = $this->backupFile('iclimate_2026-08-01_0315.backup', 40);
        $alsoOld = $this->backupFile('iclimate_2026-09-05_0315.backup', 20);
        $keepTen = $this->backupFile('iclimate_2026-09-15_0315.backup', 10);
        $keepRecent = $this->backupFile('iclimate_2026-09-24_0315.backup', 1);
        $this->fakePgDump();

        $this->artisan('iclimate:backup-database')->assertExitCode(0);

        $this->assertFileDoesNotExist($tooOld);
        $this->assertFileDoesNotExist($alsoOld);
        $this->assertFileExists($keepTen);
        $this->assertFileExists($keepRecent);
        $this->assertCount(3, $this->files(), 'Two recent backups plus the new one.');
    }

    public function test_cleanup_only_touches_backup_files_inside_the_backup_directory(): void
    {
        $unrelated = $this->dir.'/notes.txt';
        $lookalike = $this->dir.'/other.backup';
        $partial = $this->dir.'/iclimate_2026-08-01_0315.backup.partial';
        foreach ([$unrelated, $lookalike, $partial] as $path) {
            file_put_contents($path, 'x');
            touch($path, now()->subDays(90)->getTimestamp());
        }
        mkdir($this->dir.'/iclimate_2026-08-01_0316.backup'); // a directory with a matching name
        $outside = $this->root.'/iclimate_2026-08-01_0315.backup';
        file_put_contents($outside, 'PGDMP');
        touch($outside, now()->subDays(90)->getTimestamp());
        $this->backupFile('iclimate_2026-09-24_0315.backup', 1);

        $deleted = app(DatabaseBackupService::class)->prune();

        $this->assertSame(0, $deleted);
        foreach ([$unrelated, $lookalike, $partial, $outside, $this->dir.'/iclimate_2026-08-01_0316.backup'] as $path) {
            $this->assertFileExists($path);
        }
    }

    public function test_newest_backup_is_never_deleted_even_when_old(): void
    {
        $only = $this->backupFile('iclimate_2026-07-01_0315.backup', 80);

        $this->assertSame(0, app(DatabaseBackupService::class)->prune());
        $this->assertFileExists($only);
    }

    public function test_retention_is_kept_between_7_and_14_days(): void
    {
        $service = app(DatabaseBackupService::class);

        config(['backup.retention_days' => 1]);
        $this->assertSame(7, $service->retentionDays());
        config(['backup.retention_days' => 90]);
        $this->assertSame(14, $service->retentionDays());
        config(['backup.retention_days' => 10]);
        $this->assertSame(10, $service->retentionDays());

        config(['backup.retention_days' => 1]);
        $sixDays = $this->backupFile('iclimate_2026-09-19_0315.backup', 6);
        $eightDays = $this->backupFile('iclimate_2026-09-17_0315.backup', 8);
        $this->backupFile('iclimate_2026-09-25_0315.backup', 0);

        $service->prune();

        $this->assertFileExists($sixDays);
        $this->assertFileDoesNotExist($eightDays);
    }

    // ------------------------------------------------------------ System Health

    private function backupCheck(): array
    {
        return collect(app(SystemHealthService::class)->checks())->firstWhere('key', 'backup');
    }

    public function test_recent_valid_backup_is_healthy(): void
    {
        $this->backupFile('iclimate_2026-09-25_0315.backup', 0);

        $check = $this->backupCheck();

        $this->assertSame('Database Backup', $check['title']);
        $this->assertSame('Healthy', $check['status']);
        $this->assertArrayHasKey('Last backup', $check['details']);
        $this->assertSame('1', $check['details']['Backups kept']);
    }

    public function test_stale_backup_needs_attention(): void
    {
        $this->backupFile('iclimate_2026-09-20_0315.backup', 5);

        $check = $this->backupCheck();

        $this->assertSame('Needs Attention', $check['status']);
        $this->assertArrayHasKey('Last backup', $check['details']);
    }

    public function test_missing_backup_folder_or_no_backups_needs_attention(): void
    {
        $this->assertSame('Needs Attention', $this->backupCheck()['status'], 'Empty folder');

        File::deleteDirectory($this->dir);
        $this->assertSame('Needs Attention', $this->backupCheck()['status'], 'Folder does not exist');
    }

    public function test_empty_or_invalid_backup_files_do_not_count_as_a_backup(): void
    {
        $this->backupFile('iclimate_2026-09-25_0315.backup', 0, '');
        $this->backupFile('iclimate_2026-09-25_0316.backup', 0, 'not a postgres archive');

        $this->assertSame('Needs Attention', $this->backupCheck()['status']);
    }

    public function test_backup_status_is_unavailable_when_the_folder_cannot_be_checked(): void
    {
        $notAFolder = $this->root.'/a-file';
        file_put_contents($notAFolder, 'x');
        config(['backup.directory' => $notAFolder]);

        $this->assertSame('Unavailable', $this->backupCheck()['status']);
    }

    public function test_opening_system_health_never_starts_or_changes_a_backup(): void
    {
        Process::fake();
        $before = $this->files();
        $it = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);

        $this->actingAs($it)->get(route('system-health.index'))->assertOk()->assertSee('Database Backup');

        Process::assertNothingRan();
        $this->assertSame($before, $this->files());
    }

    public function test_it_expert_sees_the_backup_card_with_last_backup_time(): void
    {
        $this->backupFile('iclimate_2026-09-25_0315.backup', 0);
        $it = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);

        $this->actingAs($it)->get(route('system-health.index'))
            ->assertOk()
            ->assertSee('Database Backup')
            ->assertSee('Last backup')
            ->assertSee('A recent backup is available.');
    }

    public function test_system_health_stays_it_expert_only(): void
    {
        $this->backupFile('iclimate_2026-09-25_0315.backup', 0);

        $this->actingAs(User::factory()->farmer()->create())->get(route('system-health.index'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_MAO]))->get(route('system-health.index'))->assertForbidden();
        $this->post('/logout');
        auth()->logout();
        $this->get(route('system-health.index'))->assertRedirect(route('login'));
    }

    public function test_system_health_page_does_not_expose_backup_paths_or_secrets(): void
    {
        $this->backupFile('iclimate_2026-09-25_0315.backup', 0);
        $it = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);

        $html = $this->actingAs($it)->get(route('system-health.index'))->assertOk()->getContent();

        foreach ([$this->dir, $this->root, base_path(), storage_path(), self::PASSWORD, self::HOST, self::USER, 'iclimate_2026-09-25_0315.backup', 'pg_dump'] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }
    }

    // --------------------------------------------------------------- schedule

    public function test_backup_is_scheduled_once_a_day(): void
    {
        Artisan::call('schedule:list');
        $list = Artisan::output();

        $this->assertStringContainsString('iclimate:backup-database', $list);
        $this->assertMatchesRegularExpression('/15\s+3\s+\*\s+\*\s+\*\s+php artisan iclimate:backup-database/', $list);
    }
}
