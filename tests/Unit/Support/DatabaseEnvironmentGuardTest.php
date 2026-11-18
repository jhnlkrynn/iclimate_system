<?php

namespace Tests\Unit\Support;

use App\Support\DatabaseEnvironmentGuard;
use PHPUnit\Framework\TestCase;

class DatabaseEnvironmentGuardTest extends TestCase
{
    private function config(string $host, string $database = 'iclimate_staging_20261103_fix3', array $extra = []): array
    {
        $connection = ['driver' => 'mysql', 'host' => $host, 'database' => $database, 'unix_socket' => ''] + $extra;

        return [
            'app' => ['allow_remote_database' => false],
            'database' => ['default' => 'mysql', 'connections' => ['mysql' => $connection, 'mysql_backup' => $connection]],
            'backup' => ['connection' => 'mysql_backup'],
        ];
    }

    public function test_local_xampp_configuration_is_accepted(): void
    {
        foreach (['127.0.0.1', 'localhost', '::1'] as $host) {
            $this->assertSame([], DatabaseEnvironmentGuard::inspect($this->config($host), 'local')['violations'], $host);
        }
    }

    public function test_local_development_cannot_point_at_a_remote_database(): void
    {
        $result = DatabaseEnvironmentGuard::inspect($this->config('mysql.hostinger.example', 'u123_iclimate'), 'local');

        $this->assertCount(2, $result['violations'], 'application and backup connections');
        $this->assertStringContainsString('remote database host', $result['violations'][0]);
        $this->assertSame('remote', $result['connections']['mysql']['host_category']);
    }

    public function test_an_approved_remote_session_can_be_allowed_explicitly(): void
    {
        $config = $this->config('mysql.hostinger.example', 'u123_iclimate');
        $config['app']['allow_remote_database'] = true;

        $this->assertSame([], DatabaseEnvironmentGuard::inspect($config, 'local')['violations']);
    }

    public function test_production_cannot_use_a_local_working_copy(): void
    {
        foreach (['iclimate_staging_20261103_fix3', 'iclimate_restore_20261110_041236', 'iclimate_phpunit'] as $database) {
            $this->assertNotSame([], DatabaseEnvironmentGuard::inspect($this->config('localhost', $database, ['timezone' => '+08:00']), 'production')['violations'], $database);
        }
    }

    public function test_production_on_its_own_database_is_accepted_and_warns_without_a_session_zone(): void
    {
        $ok = DatabaseEnvironmentGuard::inspect($this->config('localhost', 'u123_iclimate', ['timezone' => '+08:00']), 'production');
        $this->assertSame([], $ok['violations']);
        $this->assertSame([], $ok['warnings']);

        $noZone = DatabaseEnvironmentGuard::inspect($this->config('mysql.hostinger.example', 'u123_iclimate'), 'production');
        $this->assertSame([], $noZone['violations']);
        $this->assertStringContainsString('DB_TIMEZONE', $noZone['warnings'][0]);
    }

    public function test_supabase_is_refused_in_every_environment(): void
    {
        foreach (['local', 'production'] as $environment) {
            foreach (['aws-0-ap-northeast-1.pooler.supabase.com', 'db.abcdefgh.supabase.co'] as $host) {
                $violations = DatabaseEnvironmentGuard::inspect($this->config($host, 'postgres', ['timezone' => '+08:00']), $environment)['violations'];
                $this->assertNotEmpty(array_filter($violations, fn (string $v): bool => str_contains($v, 'Supabase')), "{$environment} {$host}");
            }
        }
    }

    public function test_sqlite_test_databases_are_not_checked(): void
    {
        $config = ['app' => [], 'database' => ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:']]], 'backup' => []];

        $this->assertSame([], DatabaseEnvironmentGuard::inspect($config, 'local')['violations']);
    }

    public function test_host_categories_never_expose_the_host(): void
    {
        $this->assertSame('local', DatabaseEnvironmentGuard::category('', '/tmp/mysql.sock'));
        $this->assertSame('remote', DatabaseEnvironmentGuard::category('srv123.hstgr.io'));
        $this->assertSame('supabase', DatabaseEnvironmentGuard::category('db.x.supabase.co'));
    }
}
