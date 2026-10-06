<?php

namespace Tests\Feature;

use App\Models\BackupVerification;
use App\Models\User;
use App\Services\BackupVerificationService;
use App\Services\SystemHealthService;
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

class BackupVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const LISTING = <<<'TXT'
;
; Archive created at 2026-09-25 03:15:00 UTC
;     dbname: secret_db
;     Format: CUSTOM
;
215; 1259 16400 TABLE iclimate_laravel users postgres
216; 1259 16410 TABLE iclimate_laravel climate_records postgres
4120; 0 16400 TABLE DATA iclimate_laravel users postgres
TXT;

    private string $root;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/verify-'.uniqid());
        $this->dir = $this->root.'/backups';
        File::ensureDirectoryExists($this->dir);

        config([
            'backup.directory' => $this->dir,
            'backup.pg_restore_binary' => 'pg_restore',
            'backup.max_age_hours' => 36,
            'backup.verification_max_age_hours' => 36,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    /** Writes a backup file; $age is how many hours old it is. */
    private function backup(string $name, string $contents = "PGDMP\x01\x0e-body", int $ageHours = 1): string
    {
        $path = $this->dir.'/'.$name;
        file_put_contents($path, $contents);
        touch($path, time() - $ageHours * 3600);

        return $path;
    }

    private function fakeRestore(string $output = self::LISTING, int $exitCode = 0, string $stderr = ''): void
    {
        Process::fake(fn (PendingProcess $p) => Process::result(output: $output, errorOutput: $stderr, exitCode: $exitCode));
    }

    private function verifier(): BackupVerificationService
    {
        return app(BackupVerificationService::class);
    }

    private function health(): array
    {
        return collect(app(SystemHealthService::class)->checks())->keyBy('key')['backup-verification'];
    }

    // ---------------------------------------------------------------- success

    public function test_a_valid_backup_is_verified_with_pg_restore_list_only(): void
    {
        $path = $this->backup('iclimate_2026-09-25_0315.backup');
        $this->fakeRestore();

        $this->artisan('iclimate:verify-backup')
            ->expectsOutputToContain('Backup verified: iclimate_2026-09-25_0315.backup (3 archive entries')
            ->assertExitCode(0);

        $row = BackupVerification::sole();
        $this->assertSame('passed', $row->status);
        $this->assertSame('iclimate_2026-09-25_0315.backup', $row->backup_filename);
        $this->assertSame(3, $row->archive_entry_count);
        $this->assertNull($row->failure_reason);
        $this->assertNotNull($row->verified_at);

        // Exactly one call, listing only: no database target, no restore flags, no credentials.
        Process::assertRanTimes(fn () => true, 1);
        Process::assertRan(function (PendingProcess $p) use ($path) {
            return $p->command === ['pg_restore', '--list', realpath($path)]
                && $p->timeout > 0
                && array_filter(array_keys($p->environment), fn ($k) => str_starts_with($k, 'PG')) === [];
        });
    }

    public function test_sha256_and_size_are_persisted(): void
    {
        $path = $this->backup('iclimate_2026-09-25_0315.backup', "PGDMP\x01\x0e-distinct-contents");
        $this->fakeRestore();

        $row = $this->verifier()->verify();

        $this->assertSame(hash_file('sha256', $path), $row->backup_sha256);
        $this->assertSame(64, strlen($row->backup_sha256));
        $this->assertSame(filesize($path), $row->backup_size_bytes);
        $this->assertSame($row->backup_sha256, BackupVerification::first()->backup_sha256);
    }

    // ------------------------------------------------------- backup selection

    public function test_the_latest_valid_backup_is_selected_and_junk_is_ignored(): void
    {
        $this->backup('iclimate_2026-09-23_0315.backup', "PGDMP\x01old", 72);
        $newest = $this->backup('iclimate_2026-09-25_0315.backup', "PGDMP\x01newest", 10);
        // Newer than every valid file, but not verifiable candidates:
        $this->backup('iclimate_2026-09-25_1000.backup', '', 1);                       // zero bytes
        $this->backup('iclimate_2026-09-25_1100.backup', 'not a pg archive', 1);       // no PGDMP header
        $this->backup('iclimate_2026-09-25_1200.backup.partial', "PGDMP\x01partial", 0); // in-progress
        $this->backup('notes.tmp', "PGDMP\x01tmp", 0);
        $this->fakeRestore();

        $row = $this->verifier()->verify();

        $this->assertSame('passed', $row->status);
        $this->assertSame('iclimate_2026-09-25_0315.backup', $row->backup_filename);
        $this->assertSame(hash_file('sha256', $newest), $row->backup_sha256);
    }

    // --------------------------------------------------------------- failures

    public function test_missing_backup_fails_without_running_anything(): void
    {
        $this->fakeRestore();

        $this->artisan('iclimate:verify-backup')
            ->expectsOutputToContain('No valid backup file was found')
            ->assertExitCode(1);

        $row = BackupVerification::sole();
        $this->assertSame('failed', $row->status);
        $this->assertNull($row->backup_filename);
        Process::assertNothingRan();
    }

    public function test_zero_byte_and_invalid_backups_fail_without_running_anything(): void
    {
        $this->backup('iclimate_2026-09-25_0315.backup', '');
        $this->backup('iclimate_2026-09-25_0316.backup', 'plain text, not an archive');
        $this->backup('iclimate_2026-09-25_0317.backup.partial', "PGDMP\x01partial");
        $this->fakeRestore();

        $row = $this->verifier()->verify();

        $this->assertSame('failed', $row->status);
        $this->assertStringContainsString('No valid backup', $row->failure_reason);
        Process::assertNothingRan();
    }

    public function test_missing_pg_restore_is_a_recorded_failure_and_keeps_the_checksum(): void
    {
        $this->backup('iclimate_2026-09-25_0315.backup');
        config(['backup.pg_restore_binary' => $this->root.'/missing/pg_restore.exe']);
        $this->fakeRestore();

        $this->artisan('iclimate:verify-backup')->assertExitCode(1);

        $row = BackupVerification::sole();
        $this->assertSame('failed', $row->status);
        $this->assertStringContainsString('pg_restore tool was not found', $row->failure_reason);
        $this->assertNotNull($row->backup_sha256);
        Process::assertNothingRan();
    }

    public function test_pg_restore_that_cannot_start_on_path_is_reported_as_unavailable(): void
    {
        $this->backup('iclimate_2026-09-25_0315.backup');
        Process::fake(fn () => throw new \RuntimeException('The command "pg_restore" is not recognized ('.$this->dir.')'));

        $row = $this->verifier()->verify();

        $this->assertStringContainsString('pg_restore tool was not found', $row->failure_reason);
        $this->assertStringNotContainsString($this->dir, $row->failure_reason);
    }

    public function test_process_failure_is_recorded_as_an_unreadable_archive(): void
    {
        $this->backup('iclimate_2026-09-25_0315.backup');
        $this->fakeRestore('', 1, 'pg_restore: error: could not read from input file: end of file');

        $row = $this->verifier()->verify();

        $this->assertSame('failed', $row->status);
        $this->assertStringContainsString('could not be read by pg_restore', $row->failure_reason);
        $this->assertNull($row->archive_entry_count);
    }

    public function test_pg_restore_missing_from_path_exit_code_is_reported_as_unavailable(): void
    {
        $this->backup('iclimate_2026-09-25_0315.backup');
        $this->fakeRestore('', 127, 'pg_restore: command not found');

        $this->assertStringContainsString('pg_restore tool was not found', $this->verifier()->verify()->failure_reason);
    }

    public function test_timeout_is_recorded_as_a_failure(): void
    {
        $this->backup('iclimate_2026-09-25_0315.backup');
        Process::fake(function () {
            throw new ProcessTimedOutException(
                new SymfonyTimeout(new SymfonyProcess(['pg_restore']), SymfonyTimeout::TYPE_GENERAL),
                Process::result(),
            );
        });

        $row = $this->verifier()->verify();

        $this->assertSame('failed', $row->status);
        $this->assertStringContainsString('took too long', $row->failure_reason);
    }

    public function test_an_archive_with_no_entries_fails(): void
    {
        $this->backup('iclimate_2026-09-25_0315.backup');
        $this->fakeRestore(";\n; Archive created at 2026-09-25\n;     Format: CUSTOM\n;\n");

        $row = $this->verifier()->verify();

        $this->assertSame('failed', $row->status);
        $this->assertStringContainsString('no contents', $row->failure_reason);
        $this->assertSame(0, BackupVerification::where('status', 'passed')->count());
    }

    // ---------------------------------------------------------------- leakage

    public function test_no_secret_path_or_raw_stderr_reaches_the_database_output_or_logs(): void
    {
        $this->backup('iclimate_2026-09-25_0315.backup');
        $stderr = 'pg_restore: error: password=hunter2 host=db.secret-host.example path='.$this->dir.'/iclimate_2026-09-25_0315.backup';
        $this->fakeRestore('', 1, $stderr);

        $logs = [];
        Log::listen(function ($event) use (&$logs) {
            $logs[] = $event->message.' '.json_encode($event->context);
        });

        $exit = Artisan::call('iclimate:verify-backup');
        $output = Artisan::output();
        $stored = json_encode(BackupVerification::sole()->toArray());

        $this->assertSame(1, $exit);
        $this->assertNotEmpty($logs);

        foreach (['hunter2', 'db.secret-host.example', 'could not read', $this->dir, $this->root, base_path(), storage_path()] as $leak) {
            $this->assertStringNotContainsString($leak, $output);
            $this->assertStringNotContainsString($leak, $stored);
            $this->assertStringNotContainsString($leak, implode("\n", $logs));
        }
    }

    public function test_pass_and_fail_events_are_logged_safely(): void
    {
        $this->backup('iclimate_2026-09-25_0315.backup');
        $logs = [];
        Log::listen(function ($event) use (&$logs) {
            $logs[] = [$event->level, $event->message, $event->context];
        });

        $this->fakeRestore();
        $this->verifier()->verify();
        $this->fakeRestore('', 1, 'boom');
        $this->verifier()->verify();

        $this->assertSame('info', $logs[0][0]);
        $this->assertSame('Backup verification passed.', $logs[0][1]);
        $this->assertSame(['backup' => 'iclimate_2026-09-25_0315.backup', 'entries' => 3], $logs[0][2]);
        $this->assertSame('error', $logs[1][0]);
        $this->assertSame('archive_unreadable', $logs[1][2]['reason']);
    }

    // ----------------------------------------------------------------- health

    private function record(array $overrides = []): BackupVerification
    {
        return BackupVerification::create($overrides + [
            'backup_filename' => 'iclimate_2026-09-25_0315.backup',
            'backup_size_bytes' => 16,
            'backup_sha256' => str_repeat('a', 64),
            'verified_at' => now(),
            'status' => 'passed',
            'archive_entry_count' => 3,
        ]);
    }

    public function test_health_is_unavailable_without_any_verification_data(): void
    {
        $check = $this->health();

        $this->assertSame('Unavailable', $check['status']);
    }

    public function test_health_is_healthy_for_a_recent_pass_that_matches_the_newest_backup(): void
    {
        $path = $this->backup('iclimate_2026-09-25_0315.backup');
        $this->record(['backup_size_bytes' => filesize($path)]);

        $check = $this->health();

        $this->assertSame('Healthy', $check['status']);
        $this->assertSame('Passed', $check['details']['Result']);
        $this->assertSame('iclimate_2026-09-25_0315.backup', $check['details']['Backup file']);
    }

    public function test_health_needs_attention_when_the_latest_verification_failed(): void
    {
        $this->backup('iclimate_2026-09-25_0315.backup');
        $this->record(['status' => 'failed', 'failure_reason' => BackupVerificationService::REASONS['archive_unreadable']]);

        $check = $this->health();

        $this->assertSame('Needs Attention', $check['status']);
        $this->assertSame('Failed', $check['details']['Result']);
        $this->assertStringContainsString('could not be read', $check['details']['Reason']);
    }

    public function test_health_needs_attention_when_the_verification_is_stale(): void
    {
        $path = $this->backup('iclimate_2026-09-25_0315.backup');
        $this->record(['backup_size_bytes' => filesize($path), 'verified_at' => now()->subHours(40)]);

        $this->assertSame('Needs Attention', $this->health()['status']);
    }

    public function test_health_needs_attention_when_a_newer_backup_is_unverified_or_the_file_changed(): void
    {
        $old = $this->backup('iclimate_2026-09-24_0315.backup', "PGDMP\x01old", 20);
        $this->record(['backup_filename' => 'iclimate_2026-09-24_0315.backup', 'backup_size_bytes' => filesize($old)]);
        $this->assertSame('Healthy', $this->health()['status']);

        // A newer backup exists that nobody has verified.
        $this->backup('iclimate_2026-09-25_0315.backup', "PGDMP\x01newer", 1);
        $this->assertSame('Needs Attention', $this->health()['status']);

        // Same name, different size than what was verified.
        BackupVerification::query()->delete();
        $this->record(['backup_filename' => 'iclimate_2026-09-25_0315.backup', 'backup_size_bytes' => 999999]);
        $this->assertSame('Needs Attention', $this->health()['status']);

        // Verified backup no longer on disk at all.
        File::cleanDirectory($this->dir);
        $this->assertSame('Needs Attention', $this->health()['status']);
    }

    public function test_health_uses_the_most_recent_verification(): void
    {
        $path = $this->backup('iclimate_2026-09-25_0315.backup');
        $this->record(['status' => 'failed', 'failure_reason' => 'x', 'verified_at' => now()->subHour()]);
        $this->record(['backup_size_bytes' => filesize($path)]);

        $this->assertSame('Healthy', $this->health()['status']);
    }

    public function test_health_card_is_separate_from_database_backup_and_leaks_nothing(): void
    {
        $path = $this->backup('iclimate_2026-09-25_0315.backup');
        $this->record(['backup_size_bytes' => filesize($path)]);
        Process::fake();

        $it = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);
        $html = $this->actingAs($it)->get(route('system-health.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-health-check="backup"', $html);
        $this->assertStringContainsString('data-health-check="backup-verification"', $html);
        $this->assertStringContainsString('Backup Verification', $html);
        foreach ([base_path(), storage_path(), $this->root, 'pg_restore.exe'] as $leak) {
            $this->assertStringNotContainsString($leak, $html);
        }
        // Opening the page reads recorded results only; it never runs pg_restore.
        Process::assertNothingRan();
    }

    // --------------------------------------------------------------- schedule

    public function test_verification_is_scheduled_daily_after_the_backup(): void
    {
        // Times of day in the schedule are Philippine time since 2026-10-06 (config app.schedule_timezone); list them that way.
        Artisan::call('schedule:list', ['--timezone' => 'Asia/Manila']);
        $list = Artisan::output();

        $this->assertMatchesRegularExpression('/25\s+3\s+\*\s+\*\s+\*\s+php artisan iclimate:verify-backup/', $list);
        $this->assertMatchesRegularExpression('/15\s+3\s+\*\s+\*\s+\*\s+php artisan iclimate:backup-database/', $list);
    }

    public function test_pg_restore_binary_is_configurable(): void
    {
        $this->assertNotNull(config('backup.pg_restore_binary'));
        $this->assertGreaterThan(0, config('backup.verify_timeout_seconds'));
    }
}
