<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Heat Map's Expected yield layer follows This Week / 2 Weeks: besides the season estimate (period season, the
 * one on the farmer dashboard), iclimate:warm-farmer-yields saves one built with the next 7 days (week) and the next
 * 14 days (2weeks) of the saved barangay forecast. Existing rows are season estimates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yield_predictions', function (Blueprint $table) {
            $table->string('period', 8)->default('season')->after('basis');   // season | week | 2weeks
        });
        Schema::table('yield_predictions', function (Blueprint $table) {
            $table->dropUnique('yield_predictions_subject_season_unique');
            $table->unique(['subject_key', 'season_start', 'period'], 'yield_predictions_subject_season_period_unique');
            $table->index(['scope', 'season_start', 'period'], 'yield_predictions_scope_season_period_index');
        });
    }

    public function down(): void
    {
        Schema::table('yield_predictions', function (Blueprint $table) {
            $table->dropIndex('yield_predictions_scope_season_period_index');
            $table->dropUnique('yield_predictions_subject_season_period_unique');
        });
        Schema::table('yield_predictions', function (Blueprint $table) {
            $table->dropColumn('period');
            $table->unique(['subject_key', 'season_start'], 'yield_predictions_subject_season_unique');
        });
    }
};
