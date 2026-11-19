<?php

namespace Tests\Feature;

use App\Models\AnalyticsRun;
use App\Models\ExternalWeatherData;
use App\Models\MonthlyWeatherPrediction;
use App\Models\ScheduledTaskRun;
use App\Models\User;
use App\Services\Prediction\PredictionOrchestrator;
use App\Services\SystemHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = storage_path('framework/testing/system-health-'.uniqid());
        File::ensureDirectoryExists($this->tempDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    private function it(): User
    {
        return User::factory()->create(['role' => User::ROLE_MAO, 'is_mao_head' => true]);
    }

    /** @return array<string, array<string, mixed>> keyed by check key */
    private function checks(): array
    {
        return collect(app(SystemHealthService::class)->checks())->keyBy('key')->all();
    }

    private function modelFile(string $contents = 'model-bytes'): string
    {
        $path = $this->tempDir.'/model-'.uniqid().'.pkl';
        file_put_contents($path, $contents);

        return $path;
    }

    private function weatherRow(\DateTimeInterface $fetchedAt): void
    {
        ExternalWeatherData::query()->forceCreate([
            'source' => 'Open-Meteo',
            'location_name' => 'Lian, Batangas',
            'latitude' => 14.0389,
            'longitude' => 120.6503,
            'forecast_date' => now()->toDateString(),
            'fetched_at' => $fetchedAt,
        ]);
    }

    private function scheduledRun(\DateTimeInterface $createdAt, string $type = 'dataset_processing'): AnalyticsRun
    {
        return AnalyticsRun::query()->forceCreate([
            'type' => $type,
            'status' => AnalyticsRun::STATUS_COMPLETED,
            'requested_by' => null,
            'input' => ['schema' => 'rice_yield_v1'],
            'completed_at' => $createdAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function failedJob(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'connection' => 'database',
            'queue' => 'analytics',
            'payload' => '{"secret":"PAYLOAD-SECRET"}',
            'exception' => 'RuntimeException: TRACE-SECRET at /var/www/app/Job.php:42',
            'failed_at' => now()->subMinutes(10),
        ]);
    }

    // ---------------------------------------------------------------- access



    public function test_only_the_three_allowed_status_words_are_used(): void
    {
        foreach ($this->checks() as $check) {
            $this->assertContains($check['status'], ['Healthy', 'Needs Attention', 'Unavailable']);
        }
    }









    // -------------------------------------------------------------- database

    public function test_database_status_is_healthy_when_connected(): void
    {
        $database = $this->checks()['database'];

        $this->assertSame('Healthy', $database['status']);
        $this->assertSame(match (config('database.default')) {
            'mysql', 'mariadb' => 'MySQL',
            'pgsql' => 'PostgreSQL',
            default => 'SQLite',
        }, $database['details']['Type']);
        $this->assertArrayHasKey('Response time', $database['details']);
    }

    /**
     * Runs $callback with a different default connection, always restoring the
     * original one first so RefreshDatabase can clean up on the real test database.
     */
    private function withDefaultConnection(string $name, array $config, callable $callback): mixed
    {
        $original = config('database.default');
        config(["database.connections.{$name}" => $config, 'database.default' => $name]);
        DB::purge($name);

        try {
            return $callback();
        } finally {
            config(['database.default' => $original]);
            DB::purge($name);
        }
    }

    public function test_database_card_is_titled_for_postgres_when_that_driver_is_configured(): void
    {
        $title = $this->withDefaultConnection('pg_test', ['driver' => 'pgsql'], fn () => $this->checks()['database']['title']);

        $this->assertSame('PostgreSQL Connection', $title);
        $this->assertStringNotContainsString('Supabase', $title);
        $this->assertSame('Database Connection', $this->checks()['database']['title']);
    }

    public function test_database_failure_is_unavailable_and_leaks_nothing(): void
    {
        // A SQLite file that does not exist fails instantly; the fake path and
        // credentials below must never appear in what the service returns.
        $checks = $this->withDefaultConnection('broken', [
            'driver' => 'sqlite',
            'database' => '/secret-dir/secret_db_name.sqlite',
            'username' => 'secret_user',
            'password' => 'secret-pass-123',
            'foreign_key_constraints' => false,
        ], fn () => $this->checks());

        $this->assertSame('Unavailable', $checks['database']['status']);

        foreach (['queue', 'failed-jobs', 'weather', 'prediction', 'analytics', 'scheduler'] as $key) {
            $this->assertNotSame('Healthy', $checks[$key]['status'], "$key must not claim Healthy without a database");
        }

        $dump = json_encode($checks);
        foreach (['secret-pass-123', 'secret_user', 'secret_db_name', 'secret-dir', 'SQLSTATE', 'does not exist', 'PDOException', 'QueryException'] as $secret) {
            $this->assertStringNotContainsString($secret, $dump);
        }
    }

    // ------------------------------------------------------------------ queue

    public function test_pending_jobs_are_unavailable_when_the_queue_is_not_database_backed(): void
    {
        config(['queue.default' => 'sync']);

        $this->assertSame('Unavailable', $this->checks()['queue']['status']);
    }

    public function test_pending_jobs_are_healthy_when_none_or_recent(): void
    {
        config(['queue.default' => 'database']);
        $this->assertSame('Healthy', $this->checks()['queue']['status']);

        DB::table('jobs')->insert(['queue' => 'analytics', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => now()->subMinutes(2)->timestamp, 'created_at' => now()->timestamp]);
        $queue = $this->checks()['queue'];
        $this->assertSame('Healthy', $queue['status']);
        $this->assertSame('1', $queue['details']['Waiting']);
    }

    public function test_old_waiting_jobs_need_attention(): void
    {
        config(['queue.default' => 'database']);
        DB::table('jobs')->insert(['queue' => 'analytics', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => now()->subHour()->timestamp, 'created_at' => now()->subHour()->timestamp]);

        $this->assertSame('Needs Attention', $this->checks()['queue']['status']);
    }



    // ---------------------------------------------------------------- weather

    public function test_weather_is_unavailable_with_no_data_healthy_when_fresh_and_flagged_when_stale(): void
    {
        $this->assertSame('Unavailable', $this->checks()['weather']['status']);

        $this->weatherRow(now()->subMinutes(20));
        $this->assertSame('Healthy', $this->checks()['weather']['status']);

        ExternalWeatherData::query()->delete();
        $this->weatherRow(now()->subHours(10));
        $this->assertSame('Needs Attention', $this->checks()['weather']['status']);

        ExternalWeatherData::query()->delete();
        $this->weatherRow(now()->subHours(4));
        $this->assertSame('Needs Attention', $this->checks()['weather']['status'], 'A delayed update is flagged too.');
    }

    // ------------------------------------------------------------- prediction

    public function test_monthly_prediction_freshness(): void
    {
        $this->assertSame('Unavailable', $this->checks()['prediction']['status']);

        $make = fn (\DateTimeInterface $generatedAt, string $status = MonthlyWeatherPrediction::STATUS_READY) => MonthlyWeatherPrediction::query()->create([
            'prediction_year' => 2026, 'prediction_month' => 10, 'rainfall' => 150, 'temperature_average' => 28.5,
            'humidity' => 80, 'weather_model' => 'test', 'generated_at' => $generatedAt, 'status' => $status,
        ]);

        $make(now()->subHours(3));
        $this->assertSame('Healthy', $this->checks()['prediction']['status']);

        MonthlyWeatherPrediction::query()->delete();
        $make(now()->subDays(5));
        $this->assertSame('Needs Attention', $this->checks()['prediction']['status']);

        MonthlyWeatherPrediction::query()->delete();
        $make(now(), MonthlyWeatherPrediction::STATUS_UNREADY);
        $this->assertSame('Needs Attention', $this->checks()['prediction']['status']);
    }

    // -------------------------------------------------------------- analytics

    public function test_dataset_processing_status(): void
    {
        $this->assertSame('Unavailable', $this->checks()['analytics']['status']);

        $run = $this->scheduledRun(now()->subHour());
        $this->assertSame('Healthy', $this->checks()['analytics']['status']);

        $run->forceFill(['created_at' => now()->subDay(), 'completed_at' => now()->subDay()])->save();
        $this->assertSame('Needs Attention', $this->checks()['analytics']['status']);

        $run->forceFill(['status' => AnalyticsRun::STATUS_FAILED, 'error' => 'Traceback SECRET-ERROR', 'created_at' => now()])->save();
        $failed = $this->checks()['analytics'];
        $this->assertSame('Needs Attention', $failed['status']);
        $this->assertStringNotContainsString('SECRET-ERROR', json_encode($failed));

        $run->forceFill(['status' => AnalyticsRun::STATUS_PENDING, 'created_at' => now()->subHours(2)])->save();
        $this->assertSame('Needs Attention', $this->checks()['analytics']['status'], 'A run stuck in pending is flagged.');
    }

    // ----------------------------------------------------------------- models

    public function test_missing_weather_model_needs_attention(): void
    {
        config(['prediction.weather.model_path' => $this->tempDir.'/no-such-weather.pkl']);

        $check = $this->checks()['weather-model'];

        $this->assertSame('Needs Attention', $check['status']);
        $this->assertStringNotContainsString($this->tempDir, json_encode($check));
    }

    public function test_missing_rice_yield_model_needs_attention(): void
    {
        config(['prediction.rice_yield.model_path' => $this->tempDir.'/no-such-rice.pkl']);

        $this->assertSame('Needs Attention', $this->checks()['rice-yield-model']['status']);
    }

    public function test_present_models_are_healthy_and_empty_ones_are_not(): void
    {
        config([
            'prediction.weather.model_path' => $this->modelFile('weights'),
            'prediction.rice_yield.model_path' => $this->modelFile(''),
        ]);

        $checks = $this->checks();

        $this->assertSame('Healthy', $checks['weather-model']['status']);
        $this->assertSame('Needs Attention', $checks['rice-yield-model']['status']);
    }

    public function test_the_real_configured_model_paths_are_used(): void
    {
        $this->assertSame('storage/models/weather_model.pkl', config('prediction.weather.model_path'));
        $this->assertSame('storage/models/rice_yield_model_final.pkl', config('prediction.rice_yield.model_path'));
    }

    // -------------------------------------------------------------- scheduler

    public function test_scheduler_freshness_detection(): void
    {
        $this->assertSame('Unavailable', $this->checks()['scheduler']['status']);

        $run = $this->scheduledRun(now()->subMinutes(10), 'advisory_ai');
        $this->assertSame('Healthy', $this->checks()['scheduler']['status']);

        $run->forceFill(['created_at' => now()->subHours(3)])->save();
        $this->assertSame('Needs Attention', $this->checks()['scheduler']['status']);
    }

    public function test_scheduler_messages_describe_recent_automation_not_the_process(): void
    {
        $none = $this->checks()['scheduler'];
        $this->assertSame('No scheduled automation has been recorded yet.', $none['summary']);

        $run = $this->scheduledRun(now()->subMinutes(10));
        $fresh = $this->checks()['scheduler'];
        $this->assertSame('Recent scheduled automation detected.', $fresh['summary']);

        $run->forceFill(['created_at' => now()->subHours(3)])->save();
        $stale = $this->checks()['scheduler'];
        $this->assertStringStartsWith('No recent scheduled automation detected.', $stale['summary']);

        foreach ([$none, $fresh, $stale] as $check) {
            $this->assertStringNotContainsStringIgnoringCase('scheduler is running', $check['summary']);
            $this->assertStringNotContainsStringIgnoringCase('scheduler process', $check['summary']);
        }
    }

    public function test_a_stale_running_scheduled_task_needs_operator_attention(): void
    {
        ScheduledTaskRun::query()->create([
            'task_name' => 'iclimate:fetch-pagasa-advisories',
            'started_at' => now()->subMinutes(SystemHealthService::SCHEDULED_TASK_STUCK_MINUTES + 1),
            'status' => ScheduledTaskRun::RUNNING,
        ]);

        $scheduler = $this->checks()['scheduler'];

        $this->assertSame('Needs Attention', $scheduler['status']);
        $this->assertStringContainsString('running longer than expected', $scheduler['summary']);
        $this->assertSame('iclimate:fetch-pagasa-advisories', $scheduler['details']['Task']);
        $this->assertSame('1', $scheduler['details']['Running tasks']);
    }

    public function test_a_recent_running_scheduled_task_does_not_override_scheduler_freshness(): void
    {
        ScheduledTaskRun::query()->create([
            'task_name' => 'iclimate:refresh-barangay-weather',
            'started_at' => now()->subMinutes(SystemHealthService::SCHEDULED_TASK_STUCK_MINUTES - 1),
            'status' => ScheduledTaskRun::RUNNING,
        ]);

        $this->scheduledRun(now()->subMinutes(10));

        $this->assertSame('Healthy', $this->checks()['scheduler']['status']);
    }

    public function test_scheduler_and_weather_thresholds_are_at_the_documented_boundaries(): void
    {
        $this->assertSame(90, SystemHealthService::SCHEDULER_MAX_AGE_MINUTES);

        $run = $this->scheduledRun(now()->subMinutes(89));
        $this->assertSame('Healthy', $this->checks()['scheduler']['status']);
        $run->forceFill(['created_at' => now()->subMinutes(91)])->save();
        $this->assertSame('Needs Attention', $this->checks()['scheduler']['status']);

        $this->weatherRow(now()->subMinutes(119));
        $this->assertSame('Healthy', $this->checks()['weather']['status']);
        ExternalWeatherData::query()->delete();
        $this->weatherRow(now()->subMinutes(125));
        $this->assertSame('Needs Attention', $this->checks()['weather']['status']);
    }

    public function test_python_check_uses_the_configured_binary_without_executing_it(): void
    {
        // A file that is not a program: if the check tried to run it, it would fail or error.
        $fake = $this->modelFile('not an executable');
        // The app reads PYTHON_BINARY through config('iclimate.python_binary') (env() does not work once the
        // config is cached), so the setting is simulated there.
        $setBinary = function (?string $value): void {
            config(['iclimate.python_binary' => $value]);
        };

        try {
            $setBinary($fake);
            $this->assertSame('Healthy', $this->checks()['python']['status']);

            $setBinary($this->tempDir.'/missing-python');
            $missing = $this->checks()['python'];
            $this->assertSame('Needs Attention', $missing['status']);
            $this->assertStringNotContainsString($this->tempDir, json_encode($missing));
        } finally {
            $setBinary(null);
        }
    }

    public function test_runs_started_by_a_person_do_not_count_as_scheduler_activity(): void
    {
        $this->scheduledRun(now()->subMinutes(5))->forceFill(['requested_by' => $this->it()->id])->save();

        $this->assertSame('Unavailable', $this->checks()['scheduler']['status']);
    }

    // ------------------------------------------------------- safety / secrets





    public function test_summary_counts_and_headline(): void
    {
        $summary = app(SystemHealthService::class)->summarize([
            ['status' => 'Healthy'], ['status' => 'Healthy'], ['status' => 'Needs Attention'], ['status' => 'Unavailable'],
        ]);

        $this->assertSame(2, $summary['healthy']);
        $this->assertSame(1, $summary['needs_attention']);
        $this->assertSame(1, $summary['unavailable']);
        $this->assertSame('1 check needs attention.', $summary['headline']);
        $this->assertSame('Everything looks healthy.', app(SystemHealthService::class)->summarize([['status' => 'Healthy']])['headline']);
    }
}
