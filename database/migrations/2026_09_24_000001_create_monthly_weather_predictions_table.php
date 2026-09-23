<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persisted output of MonthlyWeatherRandomForest::predict() — one row per
     * (prediction_year, prediction_month, weather_model). This is what lets
     * PredictionOrchestrator serve a prediction page instantly (a DB/cache
     * read) instead of retraining a 100-tree x 7-target Random Forest on
     * every request. A unique constraint on the three columns above means a
     * re-generation for the same month/model version updates the existing
     * row rather than accumulating duplicates, and a model version bump
     * (e.g. v5 -> v6) naturally produces a new row instead of silently
     * overwriting a different model's output.
     */
    public function up(): void
    {
        Schema::create('monthly_weather_predictions', function (Blueprint $table): void {
            $table->id();

            $table->unsignedSmallInteger('prediction_year');
            $table->unsignedTinyInteger('prediction_month');

            $table->decimal('rainfall', 10, 4)->nullable();
            $table->decimal('tmax', 8, 4)->nullable();
            $table->decimal('tmin', 8, 4)->nullable();
            $table->decimal('temperature_average', 8, 4)->nullable();
            $table->decimal('humidity', 8, 4)->nullable();
            $table->decimal('wind_speed', 10, 4)->nullable();
            $table->decimal('wind_direction', 10, 4)->nullable();
            $table->string('season', 20)->nullable();

            $table->string('weather_model', 150);
            $table->date('training_from')->nullable();
            $table->date('training_to')->nullable();

            $table->timestamp('generated_at');
            $table->timestamp('expires_at')->nullable();

            // 'ready' = a real, usable prediction; 'unready' = the model ran
            // but reported it did not have enough history yet (never a row
            // of invented/zero values — see PredictionOrchestrator).
            $table->string('status', 20)->default('ready');

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(
                ['prediction_year', 'prediction_month', 'weather_model'],
                'monthly_weather_predictions_period_model_unique'
            );
            $table->index(['prediction_year', 'prediction_month'], 'mwp_year_month_idx');
            $table->index('expires_at', 'mwp_expires_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_weather_predictions');
    }
};
