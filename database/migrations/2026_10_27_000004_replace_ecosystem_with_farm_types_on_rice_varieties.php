<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A variety's farm type follows the planting plans' farm types (FarmerPlantingSchedule::FARM_TYPES): Irrigated and / or
 * Rainfed, or neither ("Not specified"). It replaces the six-way "ecosystem"; saline, cool-elevated, upland and special
 * purpose stay in the adaptation text.
 *
 * Rows are converted with the importer's rule (App\Support\RiceVarieties\NsicCell::farmTypes): "irrigated" → Irrigated;
 * "rainfed" or "upland" → Rainfed. A row whose text says neither keeps what its old ecosystem said (varieties IT added).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rice_varieties') || Schema::hasColumn('rice_varieties', 'farm_irrigated')) {
            return;
        }

        Schema::table('rice_varieties', function (Blueprint $table) {
            $table->boolean('farm_irrigated')->default(false)->index();
            $table->boolean('farm_rainfed')->default(false)->index();
        });

        if (Schema::hasColumn('rice_varieties', 'ecosystem')) {
            DB::table('rice_varieties')->orderBy('id')->select(['id', 'adaptation', 'ecosystem'])->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $text = strtolower((string) $row->adaptation);
                    $irrigated = str_contains($text, 'irrigated');
                    $rainfed = str_contains($text, 'rainfed') || str_contains($text, 'upland');
                    if (! $irrigated && ! $rainfed) {
                        $irrigated = $row->ecosystem === 'Irrigated Lowland';
                        $rainfed = in_array($row->ecosystem, ['Rainfed Lowland', 'Upland'], true);
                    }
                    DB::table('rice_varieties')->where('id', $row->id)->update(['farm_irrigated' => $irrigated, 'farm_rainfed' => $rainfed]);
                }
            });

            Schema::table('rice_varieties', function (Blueprint $table) {
                $table->dropIndex(['ecosystem']);
            });
            Schema::table('rice_varieties', function (Blueprint $table) {
                $table->dropColumn('ecosystem');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('rice_varieties', 'farm_irrigated')) {
            return;
        }
        Schema::table('rice_varieties', function (Blueprint $table) {
            $table->string('ecosystem', 40)->nullable()->index();
        });
        DB::table('rice_varieties')->where('farm_irrigated', true)->update(['ecosystem' => 'Irrigated Lowland']);
        DB::table('rice_varieties')->where('farm_irrigated', false)->where('farm_rainfed', true)->update(['ecosystem' => 'Rainfed Lowland']);
        Schema::table('rice_varieties', function (Blueprint $table) {
            $table->dropIndex(['farm_irrigated']);
            $table->dropIndex(['farm_rainfed']);
        });
        Schema::table('rice_varieties', function (Blueprint $table) {
            $table->dropColumn(['farm_irrigated', 'farm_rainfed']);
        });
    }
};
