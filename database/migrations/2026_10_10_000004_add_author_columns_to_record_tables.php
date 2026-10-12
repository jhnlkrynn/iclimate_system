<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panel revision 4.4 C: the records the MAO keeps remember who created them and who last edited them
 * (App\Models\Concerns\RecordsAuthor). With the existing verified_by / approved_by columns this lets every record show
 * "Created by", "Edited by", "Verified by" and "Approved by" with the person and the time.
 */
return new class extends Migration
{
    private const TABLES = [
        'farmer_profiles', 'rice_productions', 'climate_records', 'planting_advisories', 'farmer_organizations',
        'farmer_planting_schedules', 'mao_activities', 'barangay_profiles',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            if (! Schema::hasTable($name)) {
                continue;
            }
            Schema::table($name, function (Blueprint $table) use ($name) {
                if (! Schema::hasColumn($name, 'created_by')) {
                    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn($name, 'updated_by') && $name !== 'barangay_profiles') {
                    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            if (! Schema::hasTable($name)) {
                continue;
            }
            Schema::table($name, function (Blueprint $table) use ($name) {
                if (Schema::hasColumn($name, 'created_by')) {
                    $table->dropConstrainedForeignId('created_by');
                }
                if ($name !== 'barangay_profiles' && Schema::hasColumn($name, 'updated_by')) {
                    $table->dropConstrainedForeignId('updated_by');
                }
            });
        }
    }
};
