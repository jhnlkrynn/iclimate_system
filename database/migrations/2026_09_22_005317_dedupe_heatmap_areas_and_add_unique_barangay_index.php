<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Fixes the duplicate-barangay-row bug found by the 2026-09 Heat Map
     * audit: `heatmap_areas` had a plain index (not unique) on `barangay`,
     * and historical duplicate rows (e.g. from ad-hoc factory/seed runs)
     * accumulated for several barangays. Because RoleAssistantService and
     * AIOrchestratorService each query by `barangay` and pick a single row
     * (`->latest()` / `->orderByDesc('risk_score')`), a stale duplicate
     * could — and demonstrably did — win over the actively-refreshed row,
     * so the Climora chatbot answered with months-old risk data for some
     * barangays. This migration is driver-agnostic (works for both the
     * MySQL app database and the SQLite in-memory test database) since it
     * resolves winners in PHP rather than relying on MySQL-only SQL.
     */
    public function up(): void
    {
        $duplicateGroups = DB::table('heatmap_areas')
            ->select('barangay')
            ->groupBy('barangay')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('barangay');

        foreach ($duplicateGroups as $barangay) {
            $rows = DB::table('heatmap_areas')
                ->where('barangay', $barangay)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get(['id']);

            // Keep the row with the most recent updated_at (ties broken by
            // highest id) — i.e. the one the live refresh pipeline actually
            // maintains — and delete every older duplicate for this barangay.
            $idsToDelete = $rows->pluck('id')->skip(1);

            if ($idsToDelete->isNotEmpty()) {
                DB::table('heatmap_areas')->whereIn('id', $idsToDelete)->delete();
            }
        }

        Schema::table('heatmap_areas', function (Blueprint $table): void {
            $table->unique('barangay', 'heatmap_areas_barangay_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Only drops the unique index — the deleted duplicate rows are stale
     * data (superseded by the still-present, actively-refreshed row for
     * the same barangay) and are not restored on rollback.
     */
    public function down(): void
    {
        Schema::table('heatmap_areas', function (Blueprint $table): void {
            $table->dropUnique('heatmap_areas_barangay_unique');
        });
    }
};
