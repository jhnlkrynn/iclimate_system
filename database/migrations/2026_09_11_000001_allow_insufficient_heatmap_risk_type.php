<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('heatmap_areas') || ! Schema::hasColumn('heatmap_areas', 'risk_type')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE heatmap_areas MODIFY risk_type VARCHAR(64) NOT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('heatmap_areas') || ! Schema::hasColumn('heatmap_areas', 'risk_type')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE heatmap_areas MODIFY risk_type ENUM('Flood','Drought','Typhoon','Heat') NOT NULL");
        }
    }
};
