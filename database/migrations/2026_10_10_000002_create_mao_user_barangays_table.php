<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panel revision 4.4: the barangays each MAO user covers (one user, many barangays; editable by the Municipal
 * Agriculturist). `barangay` is the official name used everywhere in iClimate; `barangay_profile_id` points at the
 * barangay's row in barangay_profiles. `sitio` narrows the coverage to part of a barangay
 * (e.g. Tan-ag, a sitio of San Diego).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mao_user_barangays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('barangay_profile_id')->nullable()->constrained('barangay_profiles')->nullOnDelete();
            $table->string('barangay')->index();
            $table->string('sitio', 120)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'barangay', 'sitio'], 'mao_user_barangay_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mao_user_barangays');
    }
};
