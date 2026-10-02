<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The farmer's own recorded harvest for a planting: tons per hectare, and the date it was harvested. */
    public function up(): void
    {
        if (! Schema::hasTable('farmer_planting_schedules')) {
            return;
        }

        Schema::table('farmer_planting_schedules', function (Blueprint $table) {
            if (! Schema::hasColumn('farmer_planting_schedules', 'actual_yield')) {
                $table->decimal('actual_yield', 8, 2)->nullable()->after('farm_area_hectares');
            }
            if (! Schema::hasColumn('farmer_planting_schedules', 'harvested_on')) {
                $table->date('harvested_on')->nullable()->after('actual_yield');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('farmer_planting_schedules')) {
            return;
        }

        Schema::table('farmer_planting_schedules', function (Blueprint $table) {
            $table->dropColumn(['actual_yield', 'harvested_on']);
        });
    }
};
