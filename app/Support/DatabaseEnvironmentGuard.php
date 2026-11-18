<?php

namespace App\Support;

/**
 * Keeps the local and production databases apart. Checked once at boot, from configuration only, before any
 * connection is opened:
 *
 * - Outside production the application and backup connections must point at this machine (127.0.0.1, ::1,
 *   localhost or a Unix socket), so a copied .env cannot read or write the Hostinger database from a laptop.
 *   ICLIMATE_ALLOW_REMOTE_DATABASE=true lifts this for a deliberate, approved remote session.
 * - In production the database must not be a local working copy (staging, restore or test databases).
 * - Nowhere may the application connect to Supabase: it is decommissioned; only the migration tools read it,
 *   through their own migration_source connection.
 *
 * Warnings (not violations): production without DB_TIMEZONE, which shifts every TIMESTAMP read on a server whose
 * own zone differs from +08:00.
 */
final class DatabaseEnvironmentGuard
{
    /** Database names that only ever hold local working copies. */
    private const LOCAL_ONLY_DATABASES = '/^iclimate_(staging|restore|phpunit|fresh|utc_probe|mysql84_probe)(_|$)/i';

    private const SUPABASE_HOSTS = '/(^|\.)supabase\.(co|com|net)$/i';

    /**
     * @param  array<string, mixed>  $config  the full Laravel configuration
     * @return array{violations: list<string>, warnings: list<string>, connections: array<string, array<string, string>>}
     */
    public static function inspect(array $config, string $environment): array
    {
        $production = $environment === 'production';
        $allowRemote = filter_var($config['app']['allow_remote_database'] ?? false, FILTER_VALIDATE_BOOL);
        $violations = [];
        $warnings = [];
        $connections = [];

        $names = array_values(array_unique(array_filter([
            $config['database']['default'] ?? null,
            $config['backup']['connection'] ?? null,
        ])));

        foreach ($names as $name) {
            $connection = (array) ($config['database']['connections'][$name] ?? []);
            $driver = (string) ($connection['driver'] ?? '');
            if (! in_array($driver, ['mysql', 'mariadb', 'pgsql'], true)) {
                continue; // sqlite (tests) and anything else has no network host
            }

            $host = strtolower(trim((string) ($connection['host'] ?? '')));
            $socket = trim((string) ($connection['unix_socket'] ?? ''));
            $database = (string) ($connection['database'] ?? '');
            $category = self::category($host, $socket);
            $connections[$name] = ['driver' => $driver, 'host_category' => $category, 'database' => $database];

            if ($category === 'supabase') {
                $violations[] = "Connection '{$name}' points at Supabase, which is decommissioned. Use the MySQL/MariaDB database of this environment.";
            }

            if (! $production && $category !== 'local' && ! $allowRemote) {
                $violations[] = "Connection '{$name}' points at a remote database host while APP_ENV={$environment}. Local development may only use this machine's database (set ICLIMATE_ALLOW_REMOTE_DATABASE=true only for an approved remote session).";
            }

            if ($production && preg_match(self::LOCAL_ONLY_DATABASES, $database) === 1) {
                $violations[] = "Connection '{$name}' uses the local working copy '{$database}' in production. Production must use its own Hostinger database.";
            }
        }

        $default = (string) ($config['database']['default'] ?? '');
        $defaultDriver = (string) ($config['database']['connections'][$default]['driver'] ?? '');
        if ($production && in_array($defaultDriver, ['mysql', 'mariadb'], true) && empty($config['database']['connections'][$default]['timezone'])) {
            $warnings[] = 'DB_TIMEZONE is not set in production: on a server whose own zone is not +08:00 every TIMESTAMP reads back shifted. Set DB_TIMEZONE=+08:00.';
        }

        return ['violations' => $violations, 'warnings' => $warnings, 'connections' => $connections];
    }

    /** local | supabase | remote (never the host name itself, so the result is safe to print). */
    public static function category(string $host, string $socket = ''): string
    {
        if ($host === '' && $socket !== '') {
            return 'local';
        }
        if (preg_match(self::SUPABASE_HOSTS, $host) === 1 || str_contains($host, 'pooler.supabase')) {
            return 'supabase';
        }
        if (in_array($host, ['127.0.0.1', '::1', 'localhost', '[::1]'], true) || str_starts_with($host, '127.')) {
            return 'local';
        }

        return 'remote';
    }
}
