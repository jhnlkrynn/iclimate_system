<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manuscript – Naicel (2026-10-08), Phase 2: the MAO validates climate and production records (verified_at /
 * verified_by), and a production record keeps the area harvested next to the area planted.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['climate_records', 'rice_productions'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'verified_at')) {
                    $blueprint->timestamp('verified_at')->nullable()->index();
                    $blueprint->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
                }
            });
        }

        Schema::table('rice_productions', function (Blueprint $table) {
            if (! Schema::hasColumn('rice_productions', 'area_harvested_hectares')) {
                $table->decimal('area_harvested_hectares', 10, 2)->nullable()->after('area_hectares');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rice_productions', function (Blueprint $table) {
            $table->dropColumn('area_harvested_hectares');
        });
        foreach (['climate_records', 'rice_productions'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('verified_by');
                $blueprint->dropColumn('verified_at');
            });
        }
    }
};
