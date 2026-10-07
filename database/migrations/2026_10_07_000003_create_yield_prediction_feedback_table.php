<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Farmer – Naicel (2026-10): the farmer's answer to "Ano ang tunay mong ani?" after a harvest, kept next to the yield
 * that was predicted for it. Stored data only, for a future retraining of the rice-yield model: nothing reads this
 * table to train or change a model automatically. The predicted_* columns are a snapshot taken when the farmer answers.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('yield_prediction_feedback')) {
            return;
        }

        Schema::create('yield_prediction_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->nullable()->constrained('farms')->nullOnDelete();
            $table->foreignId('farmer_planting_schedule_id')->unique('ypf_schedule_unique')->constrained('farmer_planting_schedules')->cascadeOnDelete();
            $table->string('season', 10);
            $table->unsignedSmallInteger('season_year');
            $table->string('barangay')->nullable();
            $table->string('farm_type', 20)->nullable();
            $table->decimal('predicted_yield', 10, 2)->nullable();
            $table->string('prediction_source')->nullable();
            $table->string('prediction_model_version')->nullable();
            $table->timestamp('prediction_generated_at')->nullable();
            $table->decimal('actual_yield', 8, 2);
            $table->date('harvested_on')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();
            $table->index(['season', 'season_year'], 'ypf_season_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yield_prediction_feedback');
    }
};
