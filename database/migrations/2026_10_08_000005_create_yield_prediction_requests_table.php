<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manuscript – Naicel (2026-10-08), Phase 5: a rice-yield prediction someone asked for — a farmer for their farm
 * (Yield Prediction), or the MAO for a barangay or the whole municipality (Yield Forecast). The page saves the request;
 * the model runs after the response (RunYieldPredictionRequest) and fills the result. Kept as the prediction history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yield_prediction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 20);                 // farm / barangay / municipal
            $table->string('barangay')->nullable();
            $table->string('season', 10);                // Wet / Dry
            $table->string('farm_type', 20);             // Irrigated / Rainfed
            $table->decimal('area_hectares', 10, 2)->nullable();
            $table->date('target_month');
            $table->string('status', 20)->default('queued')->index();   // queued / done / failed
            $table->decimal('predicted_yield', 6, 2)->nullable();       // t/ha
            $table->decimal('total_tons', 12, 2)->nullable();
            $table->decimal('historical_average', 6, 2)->nullable();    // t/ha, same month in past years
            $table->string('impact_level', 20)->nullable();             // Favorable / Normal / At Risk
            $table->string('error', 500)->nullable();
            $table->json('details')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yield_prediction_requests');
    }
};
