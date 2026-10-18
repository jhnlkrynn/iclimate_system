<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The trained rice-yield model's current-season estimates, saved so they outlive cache clears and deployments:
 * one row per farm (scope farm, the estimate on the farmer's dashboard) and one per official Lian barangay (scope
 * barangay, the Heat Map's Expected yield layer: the area-weighted average of its farms, or the barangay's own model
 * run when it has no farms). Written by `php artisan iclimate:warm-farmer-yields`; a new season adds new rows, so
 * earlier seasons stay as history. App\Services\Prediction\YieldPredictionStore.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yield_predictions', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 16);                          // farm | barangay
            $table->string('subject_key', 96);                    // farm:{id} | barangay:{canonical name}
            $table->foreignId('farm_id')->nullable()->constrained('farms')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('barangay', 64)->nullable()->index();  // canonical Lian name; null = a farm name that matches none
            $table->decimal('predicted_yield', 6, 2);             // t/ha
            $table->string('classification', 8);                 // good (>= 4.5) | fair (3.5-4.5) | low (< 3.5)
            $table->string('basis', 24);                          // farm_model | farm_average | barangay_model
            $table->unsignedInteger('farms_used')->default(0);
            $table->decimal('area_hectares', 10, 4)->nullable();  // the farm's model area / the farms' total area
            $table->date('season_start')->index();                // first day of the iClimate season (RiceSeason::window)
            $table->string('season', 8);                          // Wet / Dry
            $table->string('season_label', 40)->nullable();       // "Wet season 2026"
            $table->char('target_month', 7)->nullable();          // YYYY-MM the model ran for
            $table->string('model_version', 120)->nullable();
            $table->json('model_input')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['subject_key', 'season_start'], 'yield_predictions_subject_season_unique');
            $table->index(['scope', 'season_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yield_predictions');
    }
};
