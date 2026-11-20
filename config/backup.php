<?php

return [
    /*
    | Database connection to back up. Null means the application's default
    | connection. MySQL/MariaDB (mysqldump) and PostgreSQL (pg_dump) are supported.
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
    'pg_dump_binary' => \App\Support\PostgresToolLocator::find('pg_dump', env('PG_DUMP_BINARY')) ?: 'pg_dump',

    /*
    | The mysqldump program, used when the database is MySQL/MariaDB. XAMPP ships it in its mysql/bin folder,
    | which is normally not on PATH, so that location is used when it exists.
    */
    'mysqldump_binary' => \App\Support\MysqlDumpLocator::find(env('MYSQLDUMP_BINARY')) ?: 'mysqldump',

    /* Complete MySQL/MariaDB backups include triggers, routines, and events. */
    'mysql_include_routines' => (bool) env('MYSQLDUMP_INCLUDE_ROUTINES', true),
    'mysql_include_events' => (bool) env('MYSQLDUMP_INCLUDE_EVENTS', true),

    /*
    | The pg_restore program, used only by iclimate:verify-backup to LIST an
    | archive's contents (--list). Nothing is ever restored.
    */
    'pg_restore_binary' => \App\Support\PostgresToolLocator::find('pg_restore', env('PG_RESTORE_BINARY')) ?: 'pg_restore',

    /* Stop the archive check after this many seconds. */
    'verify_timeout_seconds' => (int) env('BACKUP_VERIFY_TIMEOUT_SECONDS', 120),

    /*
    | iclimate:test-restore (manual): restores the newest backup into a
    | throwaway, network-less PostgreSQL container. The image's major version
    | must be at least the pg_dump version that made the backups.
    */
    'docker_binary' => env('DOCKER_BINARY', 'docker'),
    'restore_test_image' => env('RESTORE_TEST_IMAGE', 'postgres:17'),
    'docker_timeout_seconds' => (int) env('RESTORE_TEST_DOCKER_TIMEOUT', 20),
    'restore_start_timeout_seconds' => (int) env('RESTORE_TEST_START_TIMEOUT', 300),   // includes a first-time image pull
    'restore_ready_timeout_seconds' => (int) env('RESTORE_TEST_READY_TIMEOUT', 90),
    'restore_ready_interval_ms' => 1000,
    'restore_timeout_seconds' => (int) env('RESTORE_TEST_RESTORE_TIMEOUT', 900),
    'restore_test_core_tables' => ['users', 'climate_records', 'rice_productions', 'farmer_profiles', 'system_logs', 'migrations'],

    /* A successful verification older than this is reported as stale. */
    'verification_max_age_hours' => (int) env('BACKUP_VERIFICATION_MAX_AGE_HOURS', 36),

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

    /*
    | Encrypted off-host copies (iclimate:backup-offsite, App\Services\OffsiteBackupService). target is a mounted
    | directory on another disk/share or an rsync/SSH target "user@host:/path". The key file holds at least 32 bytes
    | of random data (openssl rand -base64 48 > /etc/iclimate/backup.key; chmod 600) and is kept off this host too:
    | without it no copy can be restored. Both empty = off-host copies off (iclimate:health warns).
    */
    'offsite' => [
        'target' => env('BACKUP_OFFSITE_TARGET', ''),
        'key_file' => env('BACKUP_ENCRYPTION_KEY_FILE', ''),
        'retention_days' => (int) env('BACKUP_OFFSITE_RETENTION_DAYS', 30),
        'openssl_binary' => env('OPENSSL_BINARY', 'openssl'),
        'rsync_binary' => env('RSYNC_BINARY', 'rsync'),
        'ssh_command' => env('BACKUP_OFFSITE_SSH', 'ssh -o BatchMode=yes'),
        'status_file' => env('BACKUP_OFFSITE_STATUS_FILE'),
    ],
];
