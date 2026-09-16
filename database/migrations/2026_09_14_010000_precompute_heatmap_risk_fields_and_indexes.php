<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('heatmap_areas', function (Blueprint $table): void {
            $table->text('main_risk_reason')->nullable()->after('description');
            $table->text('suggested_action')->nullable()->after('main_risk_reason');
            $table->timestamp('risk_computed_at')->nullable()->after('suggested_action');
            $table->index(['risk_level', 'updated_at'], 'heatmap_risk_level_updated_idx');
        });

        DB::table('heatmap_areas')->orderBy('id')->chunkById(100, function ($areas): void {
            foreach ($areas as $area) {
                DB::table('heatmap_areas')->where('id', $area->id)->update([
                    'main_risk_reason' => $area->description
                        ?: "{$area->risk_type} risk with a computed score of ".number_format((float) $area->risk_score, 2).'.',
                    'suggested_action' => $area->irrigation_recommendation
                        ?: $area->planting_advisory
                        ?: 'Continue routine field monitoring.',
                    'risk_computed_at' => $area->updated_at,
                ]);
            }
        });

        Schema::table('climate_records', function (Blueprint $table): void {
            $table->index('updated_at', 'climate_records_updated_at_idx');
        });

        Schema::table('rice_productions', function (Blueprint $table): void {
            $table->index(['barangay', 'updated_at'], 'rice_barangay_updated_idx');
        });

        Schema::table('farmer_profiles', function (Blueprint $table): void {
            $table->index(['barangay', 'updated_at'], 'farmer_profiles_barangay_updated_idx');
        });
    }

    public function down(): void
    {
        Schema::table('farmer_profiles', fn (Blueprint $table) => $table->dropIndex('farmer_profiles_barangay_updated_idx'));
        Schema::table('rice_productions', fn (Blueprint $table) => $table->dropIndex('rice_barangay_updated_idx'));
        Schema::table('climate_records', fn (Blueprint $table) => $table->dropIndex('climate_records_updated_at_idx'));
        Schema::table('heatmap_areas', function (Blueprint $table): void {
            $table->dropIndex('heatmap_risk_level_updated_idx');
            $table->dropColumn(['main_risk_reason', 'suggested_action', 'risk_computed_at']);
        });
    }
};
