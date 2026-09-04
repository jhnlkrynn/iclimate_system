<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_boundaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_profile_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('boundary_coordinates');
            $table->decimal('calculated_area_hectares', 12, 4);
            $table->decimal('calculated_perimeter_meters', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_boundaries');
    }
};
