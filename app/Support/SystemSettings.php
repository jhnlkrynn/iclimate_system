<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Manuscript – Naicel (2026-10-08), Phase 8: the settings IT changes on System Settings, Security and Roles &
 * Permissions. Kept in one JSON file (storage/app/system-settings.json; tests use their own file) so reading a setting
 * never adds a database query to a page. A missing key reads its default below, so the system works the same as before
 * until IT changes something.
 */
class SystemSettings
{
    public const DEFAULTS = [
        // General
        'system_name' => 'iClimate',
        'office_name' => 'Municipal Agriculture Office · Lian, Batangas',
        'logo_path' => null,
        'weather_source_note' => 'Open-Meteo forecast for Lian; PAGASA bulletins; PAGASA Ambulong station record (history).',
        // Climate risk signals (Weather Analysis, Climate Trend report)
        'heavy_rain_month_mm' => 300,
        'dry_month_mm' => 50,
        'hot_month_c' => 29,
        // Weekly farm risk (Heat Map, farmer risk badge; BarangayWeekRiskService). null: the service's own limit.
        'risk_heavy_rain_mm' => null,
        'risk_very_heavy_rain_mm' => null,
        'risk_strong_wind_kmh' => null,
        'risk_storm_wind_kmh' => null,
        'risk_hot_day_c' => null,
        // Notifications
        'notify_farmers_on_publish' => true,
        'farmer_approval' => null,           // null: config iclimate.farmer_approval
        // Maintenance
        'maintenance_mode' => false,
        'maintenance_message' => 'iClimate is being updated. Please come back in a little while.',
        // Security
        'session_timeout_minutes' => null,   // null: config session.lifetime
        'password_min_length' => 8,
        'password_mixed_case' => true,
        'password_numbers' => true,
        'password_symbols' => true,
        'lockout_attempts' => 5,
        'lockout_minutes' => 1,
        // Roles & Permissions: module => roles allowed (only modules IT changed are stored)
        'permissions' => [],
        // ML Model Management: the training dataset IT uploaded last (relative to the project), null: the project's own
        'ml_dataset_path' => null,
    ];

    private static ?array $memo = null;

    public static function path(): string
    {
        return storage_path('app/'.(app()->environment('testing') ? 'testing-' : '').'system-settings.json');
    }

    public static function all(): array
    {
        if (self::$memo === null) {
            // Outside a running application (a plain unit test of a rule) there is no settings file: the defaults apply.
            try {
                $path = self::path();
            } catch (\Throwable) {
                return self::DEFAULTS;
            }
            $stored = is_file($path) ? json_decode((string) @file_get_contents($path), true) : null;
            self::$memo = array_replace(self::DEFAULTS, is_array($stored) ? $stored : []);
        }

        return self::$memo;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::all()[$key] ?? null;

        return $value ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    /** Saves the given keys (unknown keys are ignored) and returns what changed, as key => [old, new]. */
    public static function set(array $values, ?int $userId = null): array
    {
        $current = self::all();
        $changed = [];
        foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
            if (($current[$key] ?? null) !== $value) {
                $changed[$key] = [$current[$key] ?? null, $value];
                $current[$key] = $value;
            }
        }
        if ($changed !== []) {
            // Only what differs from the defaults is written, so a later change of a default still reaches everyone else.
            $stored = array_filter(array_intersect_key($current, self::DEFAULTS), fn ($value, $key) => $value !== self::DEFAULTS[$key], ARRAY_FILTER_USE_BOTH);
            $stored['_updated_at'] = now()->toIso8601String();
            $stored['_updated_by'] = $userId;
            File::ensureDirectoryExists(dirname(self::path()));
            file_put_contents(self::path(), json_encode($stored, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
            self::$memo = null;
        }

        return $changed;
    }

    /** Forget the copy read in this process (tests, and after a save from another process). */
    public static function flush(): void
    {
        self::$memo = null;
    }

    public static function maintenanceOn(): bool
    {
        return (bool) self::get('maintenance_mode', false);
    }

    public static function farmerApproval(): bool
    {
        $value = self::all()['farmer_approval'] ?? null;

        return $value === null ? (bool) config('iclimate.farmer_approval', true) : (bool) $value;
    }
}
