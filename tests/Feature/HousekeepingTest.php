<?php

namespace Tests\Feature;

use App\Models\ScheduledTaskRun;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class HousekeepingTest extends TestCase
{
    use RefreshDatabase;

    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheDir = sys_get_temp_dir().'/iclimate-housekeeping-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->cacheDir.'/ab/cd');
        config(['cache.stores.file.path' => $this->cacheDir]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->cacheDir);
        parent::tearDown();
    }

    private function taskRun(string $startedAt): ScheduledTaskRun
    {
        return ScheduledTaskRun::query()->create(['task_name' => 'x', 'started_at' => $startedAt, 'status' => ScheduledTaskRun::SUCCEEDED]);
    }

    public function test_old_task_runs_and_expired_cache_files_are_removed_and_the_rest_kept(): void
    {
        $old = $this->taskRun(now()->subDays(20)->toDateTimeString());
        $recent = $this->taskRun(now()->subDays(2)->toDateTimeString());
        File::put($this->cacheDir.'/ab/cd/expired', (time() - 60).serialize('a'));
        File::put($this->cacheDir.'/ab/cd/valid', (time() + 3600).serialize('b'));
        File::put($this->cacheDir.'/ab/cd/forever', '9999999999'.serialize('c'));
        File::put($this->cacheDir.'/ab/cd/other', 'not a cache entry');

        $this->artisan('iclimate:housekeeping')->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
        $this->assertFileDoesNotExist($this->cacheDir.'/ab/cd/expired');
        foreach (['valid', 'forever', 'other'] as $kept) {
            $this->assertFileExists($this->cacheDir.'/ab/cd/'.$kept);
        }
    }

    public function test_dry_run_changes_nothing(): void
    {
        $old = $this->taskRun(now()->subDays(20)->toDateTimeString());
        File::put($this->cacheDir.'/ab/cd/expired', (time() - 60).serialize('a'));

        $this->artisan('iclimate:housekeeping --dry-run')
            ->expectsOutputToContain('Would remove 1 scheduled-task run(s)')
            ->expectsOutputToContain('Would remove 1 expired cache file(s)')
            ->assertSuccessful();

        $this->assertModelExists($old);
        $this->assertFileExists($this->cacheDir.'/ab/cd/expired');
    }

    public function test_cleanup_jobs_are_scheduled_after_the_backup(): void
    {
        $events = collect(app(Schedule::class)->events());
        $expect = ['iclimate:housekeeping' => '40 3 * * *', 'queue:prune-failed --hours=720' => '45 3 * * *', 'auth:clear-resets' => '50 3 * * *'];

        foreach ($expect as $command => $cron) {
            $event = $events->first(fn ($e) => str_contains((string) $e->command, $command));
            $this->assertNotNull($event, $command.' is not scheduled');
            $this->assertSame($cron, $event->expression, $command);
        }
    }
}
