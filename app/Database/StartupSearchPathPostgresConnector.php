<?php

namespace App\Database;

use Illuminate\Database\Connectors\PostgresConnector;

/**
 * Same as Laravel's Postgres connector, except the schema (search_path) is
 * given to the server as a connection startup option instead of a separate
 * "SET search_path" statement after connecting.
 *
 * Why: that statement is one extra network round trip on every request
 * (about 350 ms to Supabase), and it is the only thing left to pay once the
 * connection itself is reused. With the option, a request that reuses its
 * connection costs nothing before its first real query.
 *
 * It only applies when it is switched on ('startup_search_path') and every
 * schema name is a plain identifier; otherwise the standard SET statement is
 * used, so behaviour never depends on the option being accepted.
 */
class StartupSearchPathPostgresConnector extends PostgresConnector
{
    protected function getDsn(array $config)
    {
        $dsn = parent::getDsn($config);
        $searchPath = $this->startupSearchPath($config);

        return $searchPath === null ? $dsn : $dsn.";options='-c search_path=".$searchPath."'";
    }

    protected function configureSearchPath($connection, $config)
    {
        if ($this->startupSearchPath($config) !== null) {
            return; // the server already applied it when the connection was opened
        }

        parent::configureSearchPath($connection, $config);
    }

    private function startupSearchPath(array $config): ?string
    {
        if (! ($config['startup_search_path'] ?? false)) {
            return null;
        }

        $raw = $config['search_path'] ?? $config['schema'] ?? null;

        if (! is_string($raw) && ! is_array($raw)) {
            return null;
        }

        $schemas = $this->parseSearchPath($raw);

        if ($schemas === []) {
            return null;
        }

        foreach ($schemas as $schema) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $schema) !== 1) {
                return null;
            }
        }

        return implode(',', $schemas);
    }
}
