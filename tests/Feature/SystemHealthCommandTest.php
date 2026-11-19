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
