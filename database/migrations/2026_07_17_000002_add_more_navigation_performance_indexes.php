<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index(['role', 'status', 'name'], 'users_role_status_name_idx');
            $table->index(['status', 'name'], 'users_status_name_idx');
        });

        Schema::table('farmer_profiles', function (Blueprint $table) {
            $table->index(['barangay', 'farm_type'], 'farmer_profiles_barangay_type_idx');
        });

        Schema::table('rice_productions', function (Blueprint $table) {
            $table->index(['barangay', 'year', 'season'], 'rice_productions_barangay_year_season_idx');
            $table->index(['season', 'irrigation_type', 'year'], 'rice_productions_season_irrigation_year_idx');
        });

        Schema::table('planting_advisories', function (Blueprint $table) {
            $table->index(['status', 'target_barangay', 'created_at'], 'planting_advisories_status_barangay_created_idx');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->index(['report_type', 'created_at'], 'reports_type_created_idx');
        });

        Schema::table('system_logs', function (Blueprint $table) {
            $table->index(['created_at', 'user_id'], 'system_logs_created_user_idx');
        });
    }

    public function down(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {
            $table->dropIndex('system_logs_created_user_idx');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex('reports_type_created_idx');
        });

        Schema::table('planting_advisories', function (Blueprint $table) {
            $table->dropIndex('planting_advisories_status_barangay_created_idx');
        });

        Schema::table('rice_productions', function (Blueprint $table) {
            $table->dropIndex('rice_productions_season_irrigation_year_idx');
            $table->dropIndex('rice_productions_barangay_year_season_idx');
        });

        Schema::table('farmer_profiles', function (Blueprint $table) {
            $table->dropIndex('farmer_profiles_barangay_type_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_status_name_idx');
            $table->dropIndex('users_role_status_name_idx');
        });
    }
};
