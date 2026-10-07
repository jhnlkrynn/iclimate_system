<?php

use Illuminate\Support\Str;
use Pdo\Mysql;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                // Seconds to wait for the connection to open (see the pgsql connection).
                PDO::ATTR_TIMEOUT => max(1, (int) env('DB_CONNECT_TIMEOUT', 3)),
                (PHP_VERSION_ID >= 80500
                    ? Mysql::ATTR_SSL_CA
                    : PDO::MYSQL_ATTR_SSL_CA
                ) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                // Seconds to wait for the connection to open (see the pgsql connection).
                PDO::ATTR_TIMEOUT => max(1, (int) env('DB_CONNECT_TIMEOUT', 3)),
                (PHP_VERSION_ID >= 80500
                    ? Mysql::ATTR_SSL_CA
                    : PDO::MYSQL_ATTR_SSL_CA
                ) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'postgres'),
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,

            // Your migrated Supabase tables are inside
            // the "iclimate_laravel" PostgreSQL schema.
            'search_path' => env('DB_SEARCH_PATH', 'public'),

            // The one place the PDO options for PostgreSQL are defined (false options are left out).
            //
            // Disable prepares: each query is sent once with its values as separate, typed parameters
            // (PQexecParams), instead of prepare + execute + deallocate as three network round trips.
            // Against Supabase that is ~180 ms instead of ~500 ms per query (measured 2026-10-06), with the
            // same results for every column type. It is not "emulated prepares": values are never pasted
            // into the SQL text, so booleans, JSON and enums bind exactly as before.
            //
            // Persistent: reuse the database connection between requests in the same PHP
            // process (saves ~0.5 s of TLS/auth per request against Supabase, measured
            // 2026-10-04). Opt-in: with a session-mode pooler each PHP worker keeps one
            // connection open, so only enable it where the worker count is small.
            // When on, AppServiceProvider rolls back any transaction a crashed request
            // left open on a reused connection.
            //
            // Timeout: seconds to wait for the connection to open. Without it pdo_pgsql waits 30 s, which is
            // PHP's whole request time limit, so an unreachable database ended the request with a fatal
            // "Maximum execution time exceeded" instead of the 503 page. It has to be this PDO option:
            // pdo_pgsql writes its own connect_timeout after the DSN, so one put in the DSN is ignored
            // (measured 2026-10-07). libpq applies it to each address the host resolves to, one after the
            // other (Supabase's pooler has 3, or 6 on an IPv6 network), so keep addresses x seconds under 30.
            'options' => extension_loaded('pdo_pgsql') ? array_filter([
                PDO::ATTR_TIMEOUT => max(1, (int) env('DB_CONNECT_TIMEOUT', 3)),
                // PHP 8.4+ names it Pdo\Pgsql::ATTR_DISABLE_PREPARES (the PDO:: constant is deprecated in 8.5).
                (class_exists(\Pdo\Pgsql::class) ? \Pdo\Pgsql::ATTR_DISABLE_PREPARES : PDO::PGSQL_ATTR_DISABLE_PREPARES) => filter_var(env('DB_PGSQL_DISABLE_PREPARES', true), FILTER_VALIDATE_BOOLEAN),
                PDO::ATTR_PERSISTENT => filter_var(env('DB_PERSISTENT', false), FILTER_VALIDATE_BOOLEAN),
            ]) : [],

            // Supabase requires SSL.
            'sslmode' => env('DB_SSLMODE', 'require'),

            // Send the schema as a connection startup option instead of a separate SET statement
            // (one round trip less per request). See StartupSearchPathPostgresConnector.
            'startup_search_path' => filter_var(env('DB_STARTUP_SEARCH_PATH', true), FILTER_VALIDATE_BOOLEAN),
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env(
                'REDIS_PREFIX',
                Str::slug((string) env('APP_NAME', 'laravel')).'-database-'
            ),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env(
                'REDIS_BACKOFF_ALGORITHM',
                'decorrelated_jitter'
            ),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env(
                'REDIS_BACKOFF_ALGORITHM',
                'decorrelated_jitter'
            ),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
