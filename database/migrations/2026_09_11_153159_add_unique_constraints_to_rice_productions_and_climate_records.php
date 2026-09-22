<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * This migration has never successfully run against any environment
     * (blocked by pre-existing duplicate rice_productions rows), so the
     * dedupe step below is added directly here rather than in a follow-up
     * migration. It resolves winners in PHP (not MySQL-only SQL) so it
     * works identically against the MySQL app database and the SQLite
     * in-memory test database.
     */
    public function up(): void
    {
        $this->dedupeRiceProductions();
        $this->dedupeClimateRecords();

        Schema::table('rice_productions', function (Blueprint $table) {
            $table->unique(['barangay', 'season', 'year', 'irrigation_type'], 'rice_productions_unique_record');
        });

        Schema::table('climate_records', function (Blueprint $table) {
            $table->unique('record_date', 'climate_records_unique_date');
        });
    }

    /**
     * For each (barangay, season, year, irrigation_type) group with more
     * than one row, merge complementary (null) fields from older rows into
     * the row that will be kept, then delete the rest. The kept row is the
     * most recently updated one (ties broken by highest id), matching the
     * "most current record wins" rule already used by the heatmap_areas
     * dedupe migration.
     */
    private function dedupeRiceProductions(): void
    {
        $duplicateGroups = DB::table('rice_productions')
            ->select('barangay', 'season', 'year', 'irrigation_type')
            ->groupBy('barangay', 'season', 'year', 'irrigation_type')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $mergeableColumns = ['yield_per_hectare', 'area_hectares', 'total_production', 'production_month', 'remarks'];

        foreach ($duplicateGroups as $group) {
            $rows = DB::table('rice_productions')
                ->where('barangay', $group->barangay)
                ->where('season', $group->season)
                ->where('year', $group->year)
                ->where('irrigation_type', $group->irrigation_type)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get();

            $keeper = $rows->first();
            $others = $rows->slice(1);

            // Fill any null/empty field on the keeper with a non-null value
            // from an older duplicate before discarding it, so no data is
            // silently lost when the rows are complementary.
            $patch = [];
            foreach ($mergeableColumns as $column) {
                if (! empty($keeper->{$column})) {
                    continue;
                }
                foreach ($others as $other) {
                    if (! empty($other->{$column})) {
                        $patch[$column] = $other->{$column};
                        break;
                    }
                }
            }

            if ($patch !== []) {
                DB::table('rice_productions')->where('id', $keeper->id)->update($patch);
            }

            DB::table('rice_productions')->whereIn('id', $others->pluck('id'))->delete();
        }
    }

    private function dedupeClimateRecords(): void
    {
        $duplicateDates = DB::table('climate_records')
            ->select('record_date')
            ->groupBy('record_date')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('record_date');

        foreach ($duplicateDates as $recordDate) {
            $ids = DB::table('climate_records')
                ->where('record_date', $recordDate)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->pluck('id');

            DB::table('climate_records')->whereIn('id', $ids->skip(1))->delete();
        }
    }

    public function down(): void
    {
        Schema::table('rice_productions', function (Blueprint $table) {
            $table->dropUnique('rice_productions_unique_record');
        });

        Schema::table('climate_records', function (Blueprint $table) {
            $table->dropUnique('climate_records_unique_date');
        });
    }
};
