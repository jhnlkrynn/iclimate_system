<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which NSIC variety a plan is for and how it was planted, so the expected harvest date follows that variety's
 * maturity (App\Support\VarietyHarvestEstimator) instead of the fixed 115 days.
 *
 * harvest_date_manual: the farmer typed their own harvest date; the variety's estimate then never replaces it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farmer_planting_schedules', function (Blueprint $table) {
            if (! Schema::hasColumn('farmer_planting_schedules', 'rice_variety_id')) {
                $table->foreignId('rice_variety_id')->nullable()->constrained('rice_varieties')->nullOnDelete();
            }
            if (! Schema::hasColumn('farmer_planting_schedules', 'crop_establishment')) {
                $table->enum('crop_establishment', ['Transplanted', 'Direct Wet Seeded', 'Direct Dry Seeded'])->nullable();
            }
            if (! Schema::hasColumn('farmer_planting_schedules', 'date_basis')) {
                $table->enum('date_basis', ['sowing', 'transplanting'])->nullable()->default('sowing');
            }
            if (! Schema::hasColumn('farmer_planting_schedules', 'seedling_age_days')) {
                $table->unsignedSmallInteger('seedling_age_days')->nullable()->default(21);
            }
            if (! Schema::hasColumn('farmer_planting_schedules', 'harvest_date_manual')) {
                $table->boolean('harvest_date_manual')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('farmer_planting_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('farmer_planting_schedules', 'rice_variety_id')) {
                $table->dropConstrainedForeignId('rice_variety_id');
            }
            $table->dropColumn(array_values(array_filter(
                ['crop_establishment', 'date_basis', 'seedling_age_days', 'harvest_date_manual'],
                fn (string $column): bool => Schema::hasColumn('farmer_planting_schedules', $column),
            )));
        });
    }
};
