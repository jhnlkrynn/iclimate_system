<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each farm has its own map and its own planting plans.
 *
 * farm_boundaries: a farmer may now have one boundary per farm, so the old "one boundary per farmer profile" rule is
 * removed (the profile column stays, with a plain index) and farm_id is added (one boundary per farm).
 * farmer_planting_schedules: farm_id says which of the farmer's farms a plan is for.
 *
 * The rule being removed was created with different names (Laravel's own, or idx_<n>_... in a database imported from
 * MySQL), and on PostgreSQL as a unique index or a unique constraint, so PostgreSQL drops whichever exists on that
 * column. Nothing else about the tables changes, and no existing value is modified.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('farm_boundaries', 'farm_id')) {
            Schema::table('farm_boundaries', function (Blueprint $table): void {
                // A plain index first: MySQL keeps the foreign key on it once the unique one is gone.
                $table->index('farmer_profile_id', 'farm_boundaries_farmer_profile_id_index');
                $table->foreignId('farm_id')->nullable()->after('farmer_profile_id')->constrained('farms')->cascadeOnDelete();
                $table->unique('farm_id');
            });

            $this->dropOneBoundaryPerProfileRule();
        }

        if (! Schema::hasColumn('farmer_planting_schedules', 'farm_id')) {
            Schema::table('farmer_planting_schedules', function (Blueprint $table): void {
                $table->foreignId('farm_id')->nullable()->after('user_id')->constrained('farms')->nullOnDelete();
                $table->index(['user_id', 'farm_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('farmer_planting_schedules', 'farm_id')) {
            Schema::table('farmer_planting_schedules', function (Blueprint $table): void {
                $table->dropIndex(['user_id', 'farm_id']);
                $table->dropConstrainedForeignId('farm_id');
            });
        }

        if (Schema::hasColumn('farm_boundaries', 'farm_id')) {
            Schema::table('farm_boundaries', function (Blueprint $table): void {
                $table->dropUnique(['farm_id']);
                $table->dropConstrainedForeignId('farm_id');
            });
            // The one-per-profile rule is not put back: a farmer may by then have mapped several farms.
        }
    }

    private function dropOneBoundaryPerProfileRule(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(<<<'SQL'
                DO $$
                DECLARE r record;
                BEGIN
                    FOR r IN
                        SELECT c.conname FROM pg_constraint c
                        JOIN pg_class t ON t.oid = c.conrelid
                        JOIN pg_namespace n ON n.oid = t.relnamespace AND n.nspname = current_schema()
                        JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY (c.conkey)
                        WHERE t.relname = 'farm_boundaries' AND c.contype = 'u' AND a.attname = 'farmer_profile_id' AND array_length(c.conkey, 1) = 1
                    LOOP
                        EXECUTE format('ALTER TABLE farm_boundaries DROP CONSTRAINT %I', r.conname);
                    END LOOP;
                    FOR r IN
                        SELECT i.indexname FROM pg_indexes i
                        WHERE i.schemaname = current_schema() AND i.tablename = 'farm_boundaries'
                          AND i.indexdef ILIKE 'CREATE UNIQUE INDEX%' AND i.indexdef ILIKE '%(farmer_profile_id)'
                    LOOP
                        EXECUTE format('DROP INDEX IF EXISTS %I', r.indexname);
                    END LOOP;
                END $$;
                SQL);

            return;
        }

        Schema::table('farm_boundaries', function (Blueprint $table): void {
            $table->dropUnique('farm_boundaries_farmer_profile_id_unique');
        });
    }
};
