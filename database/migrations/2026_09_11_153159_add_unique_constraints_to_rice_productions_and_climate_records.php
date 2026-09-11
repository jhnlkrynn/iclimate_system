<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rice_productions', function (Blueprint $table) {
            $table->unique(['barangay', 'season', 'year', 'irrigation_type'], 'rice_productions_unique_record');
        });

        Schema::table('climate_records', function (Blueprint $table) {
            $table->unique('record_date', 'climate_records_unique_date');
        });
    }

    public function down(): void
    {
        Schema::table('rice_productions', function (Blueprint $table) {
            $table->dropUnique('rice_productions_unique_record');
        });

        Schema::table('climate_records', function (Blueprint $table) {
            $table->dropUnique('climate_records_unique_date');
        });
    }
};
