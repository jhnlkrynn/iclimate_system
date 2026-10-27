<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per run of php artisan rice-varieties:import, so the Rice Varieties page can say when the NSIC list was
 * last loaded ("Last imported: Oct 27, 2026, 526 varieties") on every machine that shares the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rice_variety_imports')) {
            return;
        }

        Schema::create('rice_variety_imports', function (Blueprint $table) {
            $table->id();
            $table->string('file', 255);
            $table->unsignedInteger('rows');
            $table->unsignedInteger('inserted');
            $table->unsignedInteger('updated');
            $table->unsignedInteger('unchanged');
            $table->unsignedInteger('skipped');
            $table->timestamp('imported_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rice_variety_imports');
    }
};
