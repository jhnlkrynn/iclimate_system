<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('climate_records', function (Blueprint $table) {
            $table->id();
            $table->date('record_date')->index();
            $table->decimal('rainfall', 8, 2)->nullable();
            $table->decimal('temperature', 5, 2)->nullable();
            $table->decimal('humidity', 5, 2)->nullable();
            $table->decimal('wind_speed', 6, 2)->nullable();
            $table->enum('season', ['Wet', 'Dry'])->index();
            $table->string('source')->default('PAGASA Historical Climate Record');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('climate_records');
    }
};
