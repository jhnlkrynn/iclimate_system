<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The barangay the mapped farm lies in, worked out from the outline when it is saved. Kept on the farm's own record:
     * a farmer may live in one barangay (the profile's) and farm in another.
     */
    public function up(): void
    {
        if (Schema::hasTable('farm_boundaries') && ! Schema::hasColumn('farm_boundaries', 'barangay')) {
            Schema::table('farm_boundaries', function (Blueprint $table) {
                $table->string('barangay')->nullable()->after('boundary_coordinates')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('farm_boundaries') && Schema::hasColumn('farm_boundaries', 'barangay')) {
            Schema::table('farm_boundaries', function (Blueprint $table) {
                $table->dropIndex(['barangay']);
                $table->dropColumn('barangay');
            });
        }
    }
};
