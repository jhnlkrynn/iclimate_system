<?php

return [
    /*
    | The migration command keeps source and target credentials separate from
    | the application's active connection. Source settings are intentionally
    | required and never fall back to DB_*; this prevents an audit from using
    | the active application connection with the wrong driver or credentials.
    */
    'source' => [
        'driver' => env('MIGRATION_SOURCE_DB_CONNECTION', 'pgsql'),
        'host' => env('MIGRATION_SOURCE_DB_HOST'),
        'port' => env('MIGRATION_SOURCE_DB_PORT', '5432'),
        'database' => env('MIGRATION_SOURCE_DB_DATABASE'),
        'username' => env('MIGRATION_SOURCE_DB_USERNAME'),
        'password' => env('MIGRATION_SOURCE_DB_PASSWORD'),
        'schema' => env('MIGRATION_SOURCE_DB_SCHEMA', 'public'),
        'sslmode' => env('MIGRATION_SOURCE_DB_SSLMODE', 'require'),
    ],

    'target' => [
        'driver' => env('MIGRATION_TARGET_DB_CONNECTION', 'mysql'),
        'host' => env('MIGRATION_TARGET_DB_HOST', '127.0.0.1'),
        'port' => env('MIGRATION_TARGET_DB_PORT', '3306'),
        'database' => env('MIGRATION_TARGET_DB_DATABASE', 'iclimate'),
        'username' => env('MIGRATION_TARGET_DB_USERNAME', 'root'),
        'password' => env('MIGRATION_TARGET_DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],

    'report_directory' => env('MIGRATION_REPORT_DIRECTORY', storage_path('app/migration-reports')),
];
