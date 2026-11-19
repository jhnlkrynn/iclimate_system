<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Columns Supabase refreshed on every update (its on_update_current_timestamp_* functions). */
    private const COLUMNS = [
        'external_weather_data' => 'fetched_at',
        'monthly_weather_predictions' => 'generated_at',
        'typhoon_safety_responses' => 'responded_at',
    ];

    /** Trigger names created by the first version of this migration. */
    private const LEGACY_TRIGGERS = [
        'iclimate_external_weather_data_timestamp',
        'iclimate_monthly_weather_predictions_timestamp',
        'iclimate_typhoon_safety_responses_timestamp',
    ];

    /**
     * Supabase's on_update_current_timestamp_* functions are the PostgreSQL translation of MySQL's native
     * "ON UPDATE CURRENT_TIMESTAMP" column attribute (the naming pgloader uses). On MySQL/MariaDB the faithful
     * equivalent is that attribute itself, not a trigger:
     *
     * - A BEFORE UPDATE trigger overwrites the value the application sets explicitly (the weather code writes the
     *   real fetch time), and it writes the database server's local time. On a server whose time zone is not UTC
     *   (XAMPP on a Philippine machine is +08:00) Laravel then reads the time 8 hours in the future, so stale
     *   weather looks fresh.
     * - The column attribute refreshes the value only when the update does not set it, and it is already present
     *   on MariaDB 10.4 (explicit_defaults_for_timestamp=OFF). Newer servers default that variable to ON and do not
     *   add it, so it is ensured here.
     *
     * Existing values are not changed: MODIFY keeps every stored timestamp, index and the column's nullability.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::LEGACY_TRIGGERS as $trigger) {
            DB::unprepared('DROP TRIGGER IF EXISTS `'.$trigger.'`');
        }

        $database = DB::connection()->getDatabaseName();

        foreach (self::COLUMNS as $table => $column) {
            $definition = DB::selectOne(
                // Aliased: MySQL 8 returns information_schema column names in upper case, MariaDB in lower case.
                'select is_nullable as is_nullable, extra as extra from information_schema.columns where table_schema = ? and table_name = ? and column_name = ?',
                [$database, $table, $column],
            );

            if ($definition === null || str_contains(strtolower((string) $definition->extra), 'on update current_timestamp')) {
                continue;
            }

            DB::statement(sprintf(
                'ALTER TABLE `%s` MODIFY `%s` TIMESTAMP %s DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                $table,
                $column,
                $definition->is_nullable === 'YES' ? 'NULL' : 'NOT NULL',
            ));
        }
    }

    public function down(): void
    {
        // The attribute matches the schema Supabase was migrated from and may predate this migration: keep it.
    }
};
