<?php

use App\Support\LianBarangays;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Manuscript – Naicel (2026-10-08), Phase 3: what the MAO knows about each of Lian's 19 barangays (location, rice
 * area, risk classification, flood- and drought-prone). The heat map's Flood-prone / Drought-prone / MAO
 * classification layers read it. One row per barangay, made here with the official coordinates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barangay_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('barangay')->unique();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('farm_area_hectares', 10, 2)->nullable();
            $table->string('risk_classification', 20)->nullable();   // Low / Moderate / High (the MAO's own rating)
            $table->boolean('flood_prone')->default(false);
            $table->boolean('drought_prone')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        DB::table('barangay_profiles')->insert(array_map(function (string $barangay) use ($now): array {
            $point = LianBarangays::coordinateFor($barangay);

            return [
                'barangay' => $barangay,
                'latitude' => $point[0] ?? null,
                'longitude' => $point[1] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, LianBarangays::all()));
    }

    public function down(): void
    {
        Schema::dropIfExists('barangay_profiles');
    }
};
