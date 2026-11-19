<?php

namespace App\Console\Commands;

use App\Support\DatabaseEnvironmentGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Read-only report of which database and services this environment uses. Prints host categories, never host
 * names, users or passwords. Exits 1 when the configuration is unsafe (see DatabaseEnvironmentGuard).
 */
class EnvironmentCheck extends Command
{
    protected $signature = 'iclimate:environment-check {--no-connect : Check the configuration only; do not open a database connection}';

    protected $description = 'Show the database, queue, cache, session and broadcast setup of this environment (no secrets).';

    public function handle(): int
    {
        $environment = (string) app()->environment();
        $result = DatabaseEnvironmentGuard::inspect(config()->all(), $environment);

        $this->line('Environment: '.$environment.' | debug='.var_export((bool) config('app.debug'), true).' | url scheme='.(parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: '?'));
        foreach ($result['connections'] as $name => $connection) {
            $this->line(sprintf('Connection %-13s driver=%s host=%s database=%s', $name, $connection['driver'], $connection['host_category'], $connection['database']));
        }
        $default = (string) config('database.default');
        $this->line('DB session time zone: '.(config("database.connections.{$default}.timezone") ?: '(server default)'));
        $this->line('queue='.config('queue.default').' session='.config('session.driver').' cache='.config('cache.default').' broadcast='.config('broadcasting.default').' filesystem='.config('filesystems.default'));

        if (! $this->option('no-connect')) {
            try {
                $row = DB::selectOne('select version() as v, @@session.time_zone as z');
                $this->line('Connectivity: PASS (server '.$row->v.', session zone '.$row->z.')');
            } catch (Throwable $exception) {
                $this->error('Connectivity: FAIL ('.class_basename($exception).')');
                $result['violations'][] = 'The database could not be reached.';
            }
        }

        foreach ($result['warnings'] as $warning) {
            $this->warn('WARNING: '.$warning);
        }
        foreach ($result['violations'] as $violation) {
            $this->error('UNSAFE: '.$violation);
        }

        if ($result['violations'] === []) {
            $this->info('Database environment: OK');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}
