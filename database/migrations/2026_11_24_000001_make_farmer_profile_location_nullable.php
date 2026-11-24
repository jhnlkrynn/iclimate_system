<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farmer_profiles', function (Blueprint $table) {
            $table->string('barangay')->nullable()->change();
            $table->string('farm_type')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('farmer_profiles', function (Blueprint $table) {
            $table->string('barangay')->nullable(false)->change();
            $table->string('farm_type')->nullable(false)->change();
        });
    }
};
