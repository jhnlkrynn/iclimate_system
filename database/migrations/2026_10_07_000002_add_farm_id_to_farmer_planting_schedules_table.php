<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Farmer – Naicel (2026-10): a planting / harvest record belongs to one farm. Existing records go to the farmer's
 * "Farm 1"; a farmer who has records but no farm yet gets a "Farm 1" made from their first record.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('farmer_planting_schedules') || ! Schema::hasTable('farms')) {
            return;
        }

        if (! Schema::hasColumn('farmer_planting_schedules', 'farm_id')) {
            Schema::table('farmer_planting_schedules', function (Blueprint $table) {
                $table->foreignId('farm_id')->nullable()->after('user_id')->constrained('farms')->nullOnDelete();
            });
        }

        $now = now();
        DB::table('farmer_planting_schedules')->whereNull('farm_id')->orderBy('id')->get()->groupBy('user_id')->each(function ($rows, $userId) use ($now): void {
            $farmId = DB::table('farms')->where('user_id', $userId)->orderBy('id')->value('id');
            if ($farmId === null) {
                $first = $rows->first();
                $farmId = DB::table('farms')->insertGetId([
                    'user_id' => $userId,
                    'name' => 'Farm 1',
                    'barangay' => $first->barangay ?: null,
                    'area_hectares' => $first->farm_area_hectares,
                    'farm_type' => $first->farm_type ?: 'Irrigated',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            DB::table('farmer_planting_schedules')->whereIn('id', $rows->pluck('id')->all())->update(['farm_id' => $farmId]);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('farmer_planting_schedules') && Schema::hasColumn('farmer_planting_schedules', 'farm_id')) {
            Schema::table('farmer_planting_schedules', function (Blueprint $table) {
                $table->dropConstrainedForeignId('farm_id');
            });
        }
    }
};
