<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farmer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('contact_number')->nullable();
            $table->text('address')->nullable();
            $table->string('barangay')->index();
            $table->decimal('farm_area', 10, 2)->nullable();
            $table->enum('farm_type', ['Rainfed', 'Irrigated']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farmer_profiles');
    }
};
