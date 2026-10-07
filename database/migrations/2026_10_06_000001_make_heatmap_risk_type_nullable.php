<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A rating without a specific stress factor used to be stored as "Drought" because the column could not be
 * empty, so farmers were told to watch for a dry spell during rainy weeks. The main concern is now optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL: only drop NOT NULL. Laravel's change() would emit "TYPE varchar(255) CHECK (...)", which
        // PostgreSQL rejects, and would also change the column's type and constraints; this leaves both, and every
        // stored value, exactly as they are.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE heatmap_areas ALTER COLUMN risk_type DROP NOT NULL');

            return;
        }

        // SQLite (tests) cannot alter a column in place, and MySQL keeps its ENUM: Laravel's change() handles both.
        Schema::table('heatmap_areas', function (Blueprint $table): void {
            $table->enum('risk_type', ['Flood', 'Drought', 'Typhoon', 'Heat'])->nullable()->change();
        });
    }

    public function down(): void
    {
        // Left nullable: rows without a main concern cannot be given one truthfully.
    }
};
