<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Farmer – Naicel (2026-10): a farmer can have more than one farm. Each farmer's current farm (the barangay, farm
 * size and farm type on farmer_profiles) becomes "Farm 1". farmer_profiles is left as it is: it keeps mirroring the
 * farmer's first active farm, so every page that reads it keeps working.
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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farms');
    }
};
