<?php

namespace App\Database;

use Illuminate\Database\Connectors\PostgresConnector;

/**
 * PostgreSQL connector that avoids the extra round trip Laravel spends on
 * `SET search_path` right after connecting.
 *
 * Every statement costs a full network round trip to Supabase (~300 ms from
 * the Philippines), and a new connection already costs several. Supabase's
 * pooler accepts start-up options, so the search path is passed in the DSN
 * instead and the post-connect statement is skipped.
 *
 * Opt-in through the `search_path_in_dsn` connection setting so any other
 * PostgreSQL host keeps Laravel's stock behaviour.
 */
class FastPostgresConnector extends PostgresConnector
{
    protected function getDsn(array $config)
    {
        $dsn = parent::getDsn($config);

        if (! empty($config['search_path_in_dsn']) && ! empty($config['search_path'])) {
            $schemas = array_map('trim', $this->parseSearchPath($config['search_path']));

            if ($schemas !== [] && preg_match('/^[A-Za-z0-9_,\-]+$/', implode(',', $schemas))) {
                $dsn .= ";options='-c search_path=".implode(',', $schemas)."'";
            }
        }

        return $dsn;
    }

    protected function configureSearchPath($connection, $config)
    {
        if (! empty($config['search_path_in_dsn'])) {
            return;
        }

        parent::configureSearchPath($connection, $config);
    }
}
