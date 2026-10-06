<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A rating without a specific stress factor used to be stored as "Drought" because the column could not be
 * empty, so farmers were told to watch for a dry spell during rainy weeks. The main concern is now optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('heatmap_areas', function (Blueprint $table): void {
            $table->enum('risk_type', ['Flood', 'Drought', 'Typhoon', 'Heat'])->nullable()->change();
        });
    }

    public function down(): void
    {
        // Left nullable: rows without a main concern cannot be given one truthfully.
    }
};
