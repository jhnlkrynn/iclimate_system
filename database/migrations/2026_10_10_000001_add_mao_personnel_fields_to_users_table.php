<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panel revision 4.4: each MAO account is a real MAO staff member with a position, a station, an area covered and an
 * access level. The Municipal Agriculturist is the head (is_mao_head): municipality-wide data, MAO account management
 * and every MAO user's activity log. The others work on their assigned barangays (mao_user_barangays).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'position')) {
                $table->string('position', 160)->nullable()->after('role');
            }
            if (! Schema::hasColumn('users', 'station_type')) {
                $table->string('station_type', 40)->nullable()->after('position');   // Municipal / Provincial Paid
            }
            if (! Schema::hasColumn('users', 'area_covered_ha')) {
                $table->decimal('area_covered_ha', 10, 2)->nullable()->after('station_type');
            }
            if (! Schema::hasColumn('users', 'is_mao_head')) {
                $table->boolean('is_mao_head')->default(false)->after('area_covered_ha');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['position', 'station_type', 'area_covered_ha', 'is_mao_head']);
        });
    }
};
