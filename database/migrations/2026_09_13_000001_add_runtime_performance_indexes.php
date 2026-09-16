<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_runs', function (Blueprint $table): void {
            $table->index(['requested_by', 'type', 'status', 'completed_at'], 'analytics_runs_user_type_status_done_idx');
        });

        Schema::table('reports', function (Blueprint $table): void {
            $table->index(['is_generated', 'created_at', 'id'], 'reports_generated_created_id_idx');
            $table->index(['generated_by', 'is_generated', 'created_at'], 'reports_user_generated_created_idx');
        });

        Schema::table('heatmap_areas', function (Blueprint $table): void {
            $table->index(['risk_level', 'risk_score'], 'heatmap_risk_level_score_idx');
            $table->index(['barangay', 'updated_at'], 'heatmap_barangay_updated_idx');
        });

        Schema::table('planting_advisories', function (Blueprint $table): void {
            $table->index(['status', 'valid_from', 'valid_until'], 'advisories_status_validity_idx');
            $table->index(['target_barangay', 'status', 'valid_from'], 'advisories_barangay_status_valid_idx');
        });

        Schema::table('feed_posts', function (Blueprint $table): void {
            $table->index(['archived_at', 'show_on_calendar', 'event_date'], 'feed_calendar_lookup_idx');
        });

        Schema::table('a_i_chats', function (Blueprint $table): void {
            $table->index(['user_id', 'created_at'], 'ai_chats_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('a_i_chats', fn (Blueprint $table) => $table->dropIndex('ai_chats_user_created_idx'));
        Schema::table('feed_posts', fn (Blueprint $table) => $table->dropIndex('feed_calendar_lookup_idx'));
        Schema::table('planting_advisories', function (Blueprint $table): void {
            $table->dropIndex('advisories_barangay_status_valid_idx');
            $table->dropIndex('advisories_status_validity_idx');
        });
        Schema::table('heatmap_areas', function (Blueprint $table): void {
            $table->dropIndex('heatmap_barangay_updated_idx');
            $table->dropIndex('heatmap_risk_level_score_idx');
        });
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropIndex('reports_user_generated_created_idx');
            $table->dropIndex('reports_generated_created_id_idx');
        });
        Schema::table('analytics_runs', fn (Blueprint $table) => $table->dropIndex('analytics_runs_user_type_status_done_idx'));
    }
};
