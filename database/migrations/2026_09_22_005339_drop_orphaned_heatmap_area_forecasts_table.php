<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `heatmap_area_forecasts` was written by HeatmapRiskService::refreshForecasts(),
     * which was removed along with the rest of the legacy point-marker
     * heatmap system (superseded by BarangayRiskMapService's choropleth).
     * No model class, controller, view, job, or command references this
     * table anywhere in the current codebase (verified by the 2026-09 Heat
     * Map audit) — it is dead schema holding dead data. Dropping it here,
     * in a new migration, rather than editing the migration that created
     * it, so `php artisan migrate:fresh` still replays real history.
     */
    public function up(): void
    {
        Schema::dropIfExists('heatmap_area_forecasts');
    }

    /**
     * Reverse the migrations.
     *
     * Recreates the original schema (matching
     * 2026_09_16_081946_create_heatmap_area_forecasts_table.php exactly) so
     * a rollback is structurally safe, even though no code will write to it
     * again unless the forecast feature is deliberately revived.
     */
    public function down(): void
    {
        Schema::create('heatmap_area_forecasts', function (Blueprint $table) {
            $table->id();
            $table->string('barangay')->index();
            $table->date('forecast_month')->index();
            $table->enum('risk_level', ['Low', 'Moderate', 'High', 'Severe'])->index();
            $table->enum('risk_type', ['Flood', 'Drought', 'Typhoon', 'Heat'])->index();
            $table->decimal('risk_score', 5, 2)->default(0);
            $table->decimal('predicted_yield', 8, 2)->nullable();
            $table->string('rainfall_status')->nullable();
            $table->string('planting_advisory')->nullable();
            $table->string('irrigation_recommendation')->nullable();
            $table->text('description')->nullable();
            $table->text('main_risk_reason')->nullable();
            $table->text('suggested_action')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['barangay', 'forecast_month']);
        });
    }
};
