<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Farmer Survey & Model Feedback – Naicel (2026-10-09).
 *
 * farmer_survey_responses: one farmer's answers about a prediction, the weather forecast, an advisory or the system
 * (useful / accurate / helpful, each 1 to 5), with the prediction or advisory it is about when there is one, and what
 * the IT Expert did with it (category, reviewed).
 *
 * model_feedback_decisions: what the IT Expert decided for the rice-yield model after reading the feedback ("Maintain
 * Current Model" or "Needs Retraining"), with the figures on screen at that moment. Nothing is retrained or replaced
 * without a row here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farmer_survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject', 20);                       // prediction / weather / advisory / system
            $table->string('category', 20);                      // ml_model / weather_data / predictions / advisories
            $table->foreignId('planting_advisory_id')->nullable()->constrained('planting_advisories')->nullOnDelete();
            $table->foreignId('yield_prediction_request_id')->nullable()->constrained('yield_prediction_requests')->nullOnDelete();
            $table->string('barangay')->nullable();
            $table->unsignedTinyInteger('useful');               // 1 (not at all) to 5 (very)
            $table->unsignedTinyInteger('accurate');
            $table->unsignedTinyInteger('helpful');
            $table->text('comment')->nullable();
            $table->string('model_version', 60)->nullable();     // the active rice-yield model when the answer was sent
            $table->date('answered_on');                         // Manila date: one answer per farmer, subject and item per day
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['category', 'created_at']);
            $table->index(['user_id', 'subject', 'answered_on']);
        });

        Schema::create('model_feedback_decisions', function (Blueprint $table) {
            $table->id();
            $table->string('decision', 30);                      // maintain / needs_retraining
            $table->string('recommended', 30);                   // what the feedback showed at that moment
            $table->string('model_version', 60)->nullable();
            $table->unsignedInteger('responses')->default(0);
            $table->unsignedInteger('positive')->default(0);
            $table->unsignedInteger('negative')->default(0);
            $table->string('retrain_algorithm', 20)->nullable(); // set when the IT Expert also started a retraining
            $table->text('note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_feedback_decisions');
        Schema::dropIfExists('farmer_survey_responses');
    }
};
