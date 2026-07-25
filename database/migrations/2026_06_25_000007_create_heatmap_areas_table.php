<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heatmap_areas', function (Blueprint $table) {
            $table->id();
            $table->string('barangay')->index();
            $table->enum('risk_level', ['Low', 'Moderate', 'High', 'Severe'])->index();
            $table->enum('risk_type', ['Flood', 'Drought', 'Typhoon', 'Heat'])->index();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heatmap_areas');
    }
};
