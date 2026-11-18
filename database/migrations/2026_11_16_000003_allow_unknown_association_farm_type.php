<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql' && DB::getSchemaBuilder()->hasTable('farmer_organizations')) {
            DB::statement("ALTER TABLE farmer_organizations MODIFY farm_type ENUM('Rainfed','Irrigated') NULL");
        } elseif (DB::getDriverName() === 'sqlite' && Schema::hasTable('farmer_organizations')) {
            Schema::table('farmer_organizations', fn (Blueprint $table): \Illuminate\Support\Fluent => $table->string('farm_type')->nullable()->change());
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql' && DB::getSchemaBuilder()->hasTable('farmer_organizations')) {
            DB::statement("ALTER TABLE farmer_organizations MODIFY farm_type ENUM('Rainfed','Irrigated') NOT NULL");
        } elseif (DB::getDriverName() === 'sqlite' && Schema::hasTable('farmer_organizations')) {
            Schema::table('farmer_organizations', fn (Blueprint $table): \Illuminate\Support\Fluent => $table->string('farm_type')->nullable(false)->change());
        }
    }
};
