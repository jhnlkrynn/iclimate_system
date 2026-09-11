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
        Schema::create('usability_survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Role snapshot at submission time — a user's role could change
            // later, but the thesis needs to segment results by the role
            // the respondent actually evaluated the system as.
            $table->string('role');

            // The 10 standard System Usability Scale (SUS) items, each
            // scored 1 (Strongly Disagree) to 5 (Strongly Agree). See
            // UsabilitySurveyResponse::SUS_ITEMS for the exact wording and
            // which items are reverse-scored.
            for ($item = 1; $item <= 10; $item++) {
                $table->unsignedTinyInteger("item_{$item}");
            }

            $table->text('comments')->nullable();
            $table->timestamps();

            // One response per user — resubmitting updates their existing
            // response instead of accumulating duplicates.
            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usability_survey_responses');
    }
};
