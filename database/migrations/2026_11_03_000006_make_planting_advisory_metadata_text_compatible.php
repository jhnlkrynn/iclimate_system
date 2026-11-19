<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Supabase stores this field as text containing JSON, and legacy rows may
     * legitimately contain an empty value. MySQL's native JSON type rejects
     * that legacy value, so keep the cross-database representation compatible.
     *
     * MariaDB keeps the json_valid() check on the column itself, so MODIFY removes it. MySQL 8 keeps it as a
     * separate table constraint (e.g. planting_advisories_chk_1) that survives MODIFY, so it is dropped by name.
     * Only checks on this table that test the metadata column are touched.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `planting_advisories` MODIFY `metadata` LONGTEXT NULL');

        $database = DB::connection()->getDatabaseName();
        $checks = DB::select(
            "select constraint_name as constraint_name from information_schema.table_constraints
             where table_schema = ? and table_name = 'planting_advisories' and constraint_type = 'CHECK'",
            [$database],
        );

        foreach ($checks as $check) {
            $clauses = DB::select(
                'select check_clause as check_clause from information_schema.check_constraints where constraint_schema = ? and constraint_name = ?',
                [$database, $check->constraint_name],
            );
            $testsMetadata = collect($clauses)->contains(fn ($row): bool => str_contains((string) $row->check_clause, 'metadata'));

            if ($testsMetadata) {
                DB::statement('ALTER TABLE `planting_advisories` DROP CONSTRAINT `'.str_replace('`', '``', $check->constraint_name).'`');
            }
        }
    }

    public function down(): void
    {
        // Do not coerce legacy empty values back into native JSON.
    }
};
