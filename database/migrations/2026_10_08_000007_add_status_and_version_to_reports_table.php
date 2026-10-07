<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Manuscript – Naicel (2026-10-08), Phase 7: a report is a Draft until the MAO publishes it, and a report generated
 * again for the same type and filters is the next version of it (series_key groups the versions). Reports made before
 * this change count as published version 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            if (! Schema::hasColumn('reports', 'status')) {
                $table->string('status', 20)->default('draft')->index();
                $table->unsignedInteger('version')->default(1);
                $table->string('series_key', 64)->nullable()->index();
                $table->timestamp('published_at')->nullable();
            }
        });

        DB::table('reports')->update(['status' => 'published', 'published_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['status', 'version', 'series_key', 'published_at']);
        });
    }
};
