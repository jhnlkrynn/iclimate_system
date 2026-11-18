<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * On MariaDB a `json` column is LONGTEXT with a json_valid() check: the text is stored exactly as written.
     * MySQL 8's native JSON type instead re-sorts object keys and reformats values. iClimate relies on the stored
     * order (report column order and saved filters) and on comparing stored text (the rice-variety importer
     * detects "unchanged" rows), so on a fresh MySQL schema those features behaved differently.
     *
     * This converts every native JSON column to the MariaDB representation (LONGTEXT + json_valid check), keeping
     * nullability and the data. It does nothing on MariaDB and on a database imported from the MariaDB dump: there
     * are no native JSON columns there. New columns created later by `$table->json()` on MySQL would need the same.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $columns = DB::select(
            "select table_name as table_name, column_name as column_name, is_nullable as is_nullable
             from information_schema.columns where table_schema = ? and data_type = 'json' order by table_name, ordinal_position",
            [DB::connection()->getDatabaseName()],
        );

        foreach ($columns as $column) {
            $table = str_replace('`', '``', $column->table_name);
            $name = str_replace('`', '``', $column->column_name);
            DB::statement(sprintf(
                'ALTER TABLE `%s` MODIFY `%s` LONGTEXT %s CHECK (json_valid(`%s`))',
                $table,
                $name,
                $column->is_nullable === 'YES' ? 'NULL' : 'NOT NULL',
                $name,
            ));
        }
    }

    public function down(): void
    {
        // Converting back would re-sort stored JSON keys; keep the order-preserving representation.
    }
};
