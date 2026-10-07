<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manuscript – Naicel (2026-10-08), Phase 4: a saved Weather Impact Analysis (which climate variable moved most with
 * rice yield, for the chosen seasons and years), kept so the MAO can compare runs later (Analysis history).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weather_impact_analyses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->json('filters');
            $table->json('results');
            $table->unsignedInteger('sample_size');
            $table->string('strongest_variable')->nullable();
            $table->string('impact_level', 20)->nullable();   // Low / Moderate / High
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weather_impact_analyses');
    }
};
