<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Farmer "Was this forecast accurate?" answers. Additive and stand-alone:
     * it never touches climate_records, monthly_weather_predictions or any
     * model artifact. The forecast values the farmer was judging are copied
     * into the row as a snapshot (forecast_*) so the original prediction is
     * preserved even if the stored forecast is later regenerated or deleted.
     *
     * One answer per farmer per forecast (user, type, forecast date).
     */
    public function up(): void
    {
        Schema::create('weather_forecast_feedback', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('barangay', 100);

            // 'monthly' today; first day of the forecast month is forecast_date.
            $table->string('forecast_type', 20)->default('monthly');
            $table->date('forecast_date');
            $table->foreignId('monthly_weather_prediction_id')
                ->nullable()
                ->constrained('monthly_weather_predictions')
                ->nullOnDelete();

            // Snapshot of what was forecast.
            $table->string('forecast_condition', 40);
            $table->decimal('forecast_rainfall_mm', 10, 4)->nullable();
            $table->decimal('forecast_temperature_c', 8, 4)->nullable();
            $table->decimal('forecast_humidity_percent', 8, 4)->nullable();

            // accurate | partly | different
            $table->string('accuracy', 12);
            // Only set for partly/different. Null when "accurate".
            $table->string('observed_condition', 30)->nullable();

            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['user_id', 'forecast_type', 'forecast_date'], 'wff_farmer_forecast_unique');
            $table->index(['barangay', 'forecast_date'], 'wff_barangay_date_idx');
            $table->index(['forecast_date', 'accuracy'], 'wff_date_accuracy_idx');
            $table->index('observed_condition', 'wff_observed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weather_forecast_feedback');
    }
};
