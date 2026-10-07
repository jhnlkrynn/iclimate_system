<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Farmer – Naicel (2026-10): a farmer can have more than one farm. Each farmer's current farm (the barangay, farm
 * size and farm type on farmer_profiles) becomes "Farm 1". farmer_profiles is left as it is: it keeps mirroring the
 * farmer's first active farm, so every page that reads it keeps working.
use Illuminate\Support\Facades\Schema;

/**
 * One row per farm a farmer owns or manages (a farmer may have several, in different barangays). Each farm keeps its own
 * location, size and how that size is known, so an estimated range is never shown as a measured size:
 *   farm_size_method          exact | estimated | mapped | unknown
 *   farm_size_hectares        the number when it is known (exact entry or measured from the farm map), else null
 *   farm_size_estimate_range  the range the farmer picked when they estimated (lt_0_5, 0_5_1, 1_2, 2_3, gt_3)
 *   location_method           barangay | pin | boundary (a barangay alone is not an exact location)
 * A farm is archived (archived_at), never deleted, so its plans, map and history stay.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('farms')) {
            Schema::create('farms', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('name', 100);
                $table->string('barangay')->nullable()->index();
                $table->decimal('area_hectares', 10, 2)->nullable();
                $table->string('farm_type', 20)->default('Irrigated');
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'archived_at'], 'farms_user_active_idx');
            });
        }

        if (! Schema::hasTable('farmer_profiles')) {
            return;
        }

        $now = now();
        $owners = DB::table('farms')->pluck('user_id')->all();
        DB::table('farmer_profiles')->whereNotNull('user_id')->orderBy('id')->get()->each(function ($profile) use ($now, $owners): void {
            if (in_array($profile->user_id, $owners)) {
                return;
            }
            DB::table('farms')->insert([
                'user_id' => $profile->user_id,
                'name' => 'Farm 1',
                'barangay' => $profile->barangay ?: null,
                'area_hectares' => $profile->farm_area,
                'farm_type' => $profile->farm_type ?: 'Irrigated',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        if (Schema::hasTable('farms')) {
            return;
        }

        Schema::create('farms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('farm_name', 80);
            $table->string('barangay')->nullable()->index();
            $table->string('location_description', 160)->nullable();
            $table->string('location_method', 16)->default('barangay');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('farm_size_hectares', 10, 4)->nullable();
            $table->string('farm_size_method', 16)->default('unknown');
            $table->string('farm_size_estimate_range', 16)->nullable();
            $table->string('farm_size_unit', 16)->nullable();
            $table->string('farm_type', 16)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farms');
    }
};
