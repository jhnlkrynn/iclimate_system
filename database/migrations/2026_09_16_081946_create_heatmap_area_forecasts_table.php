<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('heatmap_area_forecasts');
    }
};
