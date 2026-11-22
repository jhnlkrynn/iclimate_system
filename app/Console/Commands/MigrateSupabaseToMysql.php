<?php

namespace App\Console\Commands;

use App\Services\SupabaseToMysqlMigrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

class MigrateSupabaseToMysql extends Command
{
    protected $signature = 'iclimate:migrate-supabase
        {--connect-only : Run one harmless read-only query against the source and exit}
        {--audit-only : Inventory source schemas, tables, columns, and exact row counts without writing data}
        {--compare : Compare source and local target by primary-key sets and deterministic row hashes without writing data}
        {--migrate : Create the Laravel schema and import source records into a fresh local MySQL database}
        {--confirm : Required with --migrate; confirms the irreversible target-side import}
        {--report= : Optional JSON report path}';

    protected $description = 'Audit Supabase and, only with confirmation, migrate its application tables to fresh local XAMPP MySQL.';

    public function handle(SupabaseToMysqlMigrationService $migration): int
    {
        $modes = [(bool) $this->option('connect-only'), (bool) $this->option('audit-only'), (bool) $this->option('compare'), (bool) $this->option('migrate')];
        if (count(array_filter($modes)) !== 1) {
            $this->error('Choose exactly one of --connect-only, --audit-only, or --migrate.');
            return self::INVALID;
        }
        if ($this->option('migrate') && ! $this->option('confirm')) {
            $this->error('Refusing to import without --confirm. The target must be a fresh staging database.');
            return self::INVALID;
        }

        try {
            $this->validateExplicitSourceConfiguration();
            $this->configureConnections();
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
            return self::INVALID;
        }

        try {
            if ($this->option('connect-only')) {
                $probe = $migration->connectivityProbe();
                $this->info('Read-only PostgreSQL probe succeeded: database='.$probe['database'].' schema='.$probe['schema'].'.');
                return self::SUCCESS;
            } elseif ($this->option('audit-only')) {
                $report = $migration->inventory();
            } elseif ($this->option('compare')) {
                $report = $migration->compare();
            } else {
                $migration->assertSafeTarget();
                $this->line('Creating the Laravel schema on the local target...');
                config(['database.default' => $migration->targetConnection()]);
                DB::purge($migration->targetConnection());
                Artisan::call('migrate', ['--database' => $migration->targetConnection(), '--force' => true], $this->output);
                // Fresh Laravel schemas may contain migration-seeded defaults. Clear
                // only after assertSafeTarget proved this database had no prior data.
                $migration->clearFreshTargetRows();
                $report = $migration->migrate();
            }
        } catch (Throwable $exception) {
            \App\Support\SilentFailure::log($exception, 'MigrateSupabaseToMysql::handle');
            $this->error('Migration did not complete: '.$this->safeMessage($exception));
            return self::FAILURE;
        }

        $path = $this->reportPath();
        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $this->info('Migration report written to '.$path);
        return self::SUCCESS;
    }

    private function configureConnections(): void
    {
        $source = config('migration.source');
        $target = config('migration.target');
        config([
            'database.connections.migration_source' => [
                'driver' => $source['driver'], 'host' => $source['host'], 'port' => $source['port'],
                'database' => $source['database'], 'username' => $source['username'], 'password' => $source['password'],
                'charset' => 'utf8', 'prefix' => '', 'search_path' => $source['schema'], 'sslmode' => $source['sslmode'],
            ],
            'database.connections.migration_target' => [
                'driver' => $target['driver'], 'host' => $target['host'], 'port' => $target['port'],
                'database' => $target['database'], 'username' => $target['username'], 'password' => $target['password'],
                'charset' => $target['charset'], 'collation' => $target['collation'], 'prefix' => '', 'strict' => true,
            ],
        ]);
    }

    private function validateExplicitSourceConfiguration(): void
    {
        $source = config('migration.source');
        $missing = collect(['host', 'database', 'username', 'password'])
            ->filter(static fn (string $key): bool => ! is_string($source[$key] ?? null) || trim($source[$key]) === '')
            ->values()
            ->all();

        if ($missing !== []) {
            throw new \InvalidArgumentException('Migration source configuration is incomplete. Set MIGRATION_SOURCE_DB_HOST, MIGRATION_SOURCE_DB_DATABASE, MIGRATION_SOURCE_DB_USERNAME, and MIGRATION_SOURCE_DB_PASSWORD explicitly; active DB_* values are not used.');
        }
    }

    private function reportPath(): string
    {
        $path = $this->option('report') ?: rtrim((string) config('migration.report_directory'), '/\\').DIRECTORY_SEPARATOR.'supabase-to-mysql-'.now()->format('Ymd-His').'.json';
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0750, true);
        }
        return $path;
    }

    private function safeMessage(Throwable $exception): string
    {
        if (str_contains($exception->getMessage(), 'SQLSTATE') || str_contains($exception->getMessage(), 'could not translate host name')) {
            return 'Database connection failed. Check the migration source/target settings and that both servers are available.';
        }
        return preg_replace('/(password|passwd|secret|token)=?[^\s;]+/i', '$1=[redacted]', $exception->getMessage()) ?: 'See the application log.';
    }
}
