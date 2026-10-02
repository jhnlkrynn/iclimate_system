<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** One row per attempt to fetch weather from the online provider (success or failure). */
    public function up(): void
    {
        Schema::create('weather_fetch_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40)->default('Open-Meteo');
            $table->timestamp('started_at')->index();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('status', 10);                           // success or failed
            $table->string('error_message', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weather_fetch_logs');
    }
};
