<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PDOException;
use Tests\TestCase;

/**
 * The custom "Data Service Unavailable" (503) page is for a database that cannot be reached, nothing else.
 * Regression cover for the 2026-10 incidents, where a dropped Supabase pooler connection showed this page,
 * and for the handler no longer disguising ordinary SQL errors as an outage.
 */
class DataServiceUnavailableTest extends TestCase
{
    use RefreshDatabase;

    private function queryException(string $message, string $sqlState = 'HY000'): QueryException
    {
        $pdo = new PDOException("SQLSTATE[{$sqlState}]: {$message}");
        // PDOException codes are SQLSTATE strings; the constructor only accepts ints, so set it directly.
        (new \ReflectionProperty(\Exception::class, 'code'))->setValue($pdo, $sqlState);

        return new QueryException('pgsql', 'select * from "users" where "id" = ? limit 1', [10], $pdo);
    }

    private function failingRoute(\Throwable $exception): string
    {
        Route::middleware('web')->get('/__test/fails', fn () => throw $exception);

        return '/__test/fails';
    }

    public function test_root_and_login_load_normally(): void
    {
        $this->get('/')->assertOk();
        $this->get('/login')->assertOk();
    }

    public function test_a_dropped_database_connection_shows_the_data_service_page(): void
    {
        config(['app.debug' => false]);
        $url = $this->failingRoute($this->queryException('General error: 7 server closed the connection unexpectedly'));

        $this->get($url)->assertStatus(503)->assertSee('Data Service Unavailable');
        $this->getJson($url)->assertStatus(503)->assertJson(['message' => 'The data service is temporarily unavailable. Please try again shortly.']);
    }

    public function test_a_refused_connection_shows_the_data_service_page(): void
    {
        config(['app.debug' => false]);
        $url = $this->failingRoute($this->queryException('[7] could not connect to server: Connection refused', '08006'));

        $this->get($url)->assertStatus(503);
    }

    public function test_an_ordinary_sql_error_is_a_500_not_a_data_service_outage(): void
    {
        config(['app.debug' => false]);
        $url = $this->failingRoute($this->queryException('Undefined column: 7 ERROR: column "nope" does not exist', '42703'));

        $this->get($url)->assertStatus(500)->assertDontSee('Data Service Unavailable');
    }

    public function test_debug_mode_shows_the_real_error_instead_of_the_custom_page(): void
    {
        config(['app.debug' => true]);
        $url = $this->failingRoute($this->queryException('General error: 7 server closed the connection unexpectedly'));

        // Laravel's own exception page with the real database error, not the custom outage screen.
        $this->get($url)->assertStatus(500)->assertSee('server closed the connection unexpectedly');
    }
}
