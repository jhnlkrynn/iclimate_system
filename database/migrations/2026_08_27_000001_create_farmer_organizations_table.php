<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farmer_organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('barangay')->index();
            $table->enum('farm_type', ['Rainfed', 'Irrigated']);
            $table->decimal('area_hectares', 10, 2)->nullable();
            $table->unsignedInteger('farmer_count')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farmer_organizations');
    }
};
