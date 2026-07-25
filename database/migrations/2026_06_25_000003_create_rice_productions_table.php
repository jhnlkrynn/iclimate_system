<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rice_productions', function (Blueprint $table) {
            $table->id();
            $table->string('barangay')->index();
            $table->string('season')->index();
            $table->string('irrigation_type')->index();
            $table->decimal('yield_per_hectare', 10, 2)->nullable();
            $table->decimal('area_hectares', 10, 2)->nullable();
            $table->decimal('total_production', 12, 2)->nullable();
            $table->year('year')->index();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rice_productions');
    }
};
