<?php

return [
    /*
    | Database connection to back up. Null means the application's default
    | connection. Only PostgreSQL connections are supported.
    */
    'connection' => env('BACKUP_DB_CONNECTION'),

    /*
    | Where backups are written. Nothing outside this directory is ever
    | created or deleted by the backup tools.
    */
    'directory' => env('BACKUP_DIRECTORY', storage_path('app/backups')),

    /*
    | The pg_dump program (PostgreSQL client tools). A bare name is looked up
    | on PATH; set the full location if it is not on PATH.
    */
    'pg_dump_binary' => env('PG_DUMP_BINARY', 'pg_dump'),

    /*
    | How many days of backups to keep. Kept between 7 and 14. The newest
    | backup is always kept, however old it is.
    */
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),

    /*
    | System Health reports "Needs Attention" when the newest valid backup is
    | older than this (the backup is scheduled once per day).
    */
    'max_age_hours' => (int) env('BACKUP_MAX_AGE_HOURS', 36),

    /* Give pg_dump this long (seconds) before it is stopped. */
    'timeout_seconds' => (int) env('BACKUP_TIMEOUT_SECONDS', 900),
];
