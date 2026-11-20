<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SystemHealthCommandTest extends TestCase
{
    use RefreshDatabase;

    private function healthyServices(): void
    {
        Http::fake([
            '*/health' => Http::response(['status' => 'ok', 'models_loaded' => ['rice', 'weather']]),
            '*/up' => Http::response('', 200),
        ]);
        DB::table('weather_fetch_logs')->insert(['created_at' => now()->subMinutes(10), 'updated_at' => now()->subMinutes(10)] + $this->fetchLogDefaults());
        // A fresh backup, the past-weather archive and the 18-month forecast (checks added 2026-11-20).
        $dir = storage_path('framework/testing/health-backups');
        @mkdir($dir, 0700, true);
        array_map('unlink', glob($dir.'/*') ?: []);
        file_put_contents($dir.'/iclimate_'.now()->format('Y-m-d_Hi').'.backup', "PGDMP\x01-body");
        config(['backup.directory' => $dir]);
        $this->yieldInputs();
    }

    private function yieldInputs(int $forecastMonths = 18): void
    {
        $now = \Carbon\CarbonImmutable::now('Asia/Manila')->startOfMonth();
        @unlink(\App\Services\Climate\OpenMeteoArchiveMonthly::path());
        \App\Services\Climate\OpenMeteoArchiveMonthly::forget();
        app(\App\Services\Climate\OpenMeteoArchiveMonthly::class)->store([$now->subMonthNoOverflow()->format('Y-m') => ['rainfall' => 100.0, 'temperature' => 27.0, 'humidity' => 80.0, 'wind_speed' => 2.0]]);
        foreach (range(0, $forecastMonths - 1) as $i) {
            $m = $now->addMonthsNoOverflow($i);
            \App\Models\MonthlyWeatherPrediction::query()->create(['prediction_year' => $m->year, 'prediction_month' => $m->month, 'rainfall' => 100.0, 'temperature_average' => 27.0,
                'weather_model' => 'test', 'status' => 'ready', 'generated_at' => now()]);
        }
    }

    protected function tearDown(): void
    {
        @unlink(\App\Services\Climate\OpenMeteoArchiveMonthly::path());
        \App\Services\Climate\OpenMeteoArchiveMonthly::forget();
        parent::tearDown();
    }

    public function test_a_stale_backup_or_missing_forecasts_fail_and_the_operator_is_emailed_once(): void
    {
        $this->healthyServices();
        $dir = (string) config('backup.directory');
        $file = glob($dir.'/*.backup')[0];
        touch($file, now()->subDays(3)->getTimestamp());
        \App\Models\MonthlyWeatherPrediction::query()->delete();
        config(['services.health.alert_email' => 'ops@example.test', 'mail.default' => 'array']);

        $this->artisan('iclimate:health', ['--log' => true])
            ->expectsOutputToContain('FAIL  backup')
            ->expectsOutputToContain('FAIL  yield-inputs 18 of 18 forecast months missing')
            ->expectsOutputToContain('WARN  backup-offsite off-host backups are not configured')
            ->assertFailed();
        $this->artisan('iclimate:health', ['--log' => true])->assertFailed();
        $sent = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent, 'the same failures alert once an hour');
        $this->assertStringContainsString('[iClimate] Health check FAILED: backup, yield-inputs', $sent->first()->getOriginalMessage()->getSubject());
    }

    public function test_a_partly_missing_forecast_horizon_only_warns(): void
    {
        $this->healthyServices();
        \App\Models\MonthlyWeatherPrediction::query()->where('prediction_month', \Carbon\CarbonImmutable::now('Asia/Manila')->startOfMonth()->addMonthsNoOverflow(17)->month)
            ->where('prediction_year', \Carbon\CarbonImmutable::now('Asia/Manila')->startOfMonth()->addMonthsNoOverflow(17)->year)->delete();

        $this->artisan('iclimate:health')->expectsOutputToContain('WARN  yield-inputs 1 of 18 forecast months missing')->assertSuccessful();
    }

    private function fetchLogDefaults(): array
    {
        $columns = \Illuminate\Support\Facades\Schema::getColumns('weather_fetch_logs');
        $row = [];
        foreach ($columns as $c) {
            if (in_array($c['name'], ['id', 'created_at', 'updated_at'], true) || $c['nullable'] || $c['default'] !== null) {
                continue;
            }
            $type = strtolower($c['type_name']);
            $row[$c['name']] = match (true) {
                str_contains($type, 'int') || str_contains($type, 'dec') || str_contains($type, 'float') || str_contains($type, 'double') => 0,
                str_contains($type, 'date') || str_contains($type, 'time') => now(),   // MariaDB strict mode rejects text here
                default => 'test',
            };
        }

        return $row;
    }

    public function test_all_healthy_exits_zero(): void
    {
        $this->healthyServices();
        $this->artisan('iclimate:health')->expectsOutputToContain('OK    database')->expectsOutputToContain('OK    ml-service')->assertSuccessful();
    }

    public function test_an_unreachable_ml_service_or_stopped_scheduler_fails_with_what_to_do_and_is_logged(): void
    {
        Http::fake(['*/health' => Http::response('down', 503), '*/up' => Http::response('', 200)]);
        Log::spy();

        $this->artisan('iclimate:health', ['--log' => true])
            ->expectsOutputToContain('FAIL  ml-service  ML service is not reachable')
            ->expectsOutputToContain('FAIL  scheduler   no scheduled weather fetch recorded')
            ->assertFailed();
        Log::shouldHaveReceived('log')->withArgs(fn ($level, $message) => $level === 'error' && str_contains($message, 'ml-service'));
    }

    public function test_a_stalled_queue_fails(): void
    {
        $this->healthyServices();
        config(['queue.default' => 'database']);
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => time() - 3600, 'created_at' => time() - 3600]);

        $this->artisan('iclimate:health')->expectsOutputToContain('FAIL  queue')->assertFailed();
    }

    public function test_json_output_never_contains_credentials(): void
    {
        $this->healthyServices();
        $this->artisan('iclimate:health', ['--json' => true])->doesntExpectOutputToContain((string) config('database.connections.mysql.password') ?: 'never-matches-anything')->assertSuccessful();
    }
}
