<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id', 'is_read', 'created_at'], 'notifications_user_read_created_idx');
        });

        Schema::table('feed_posts', function (Blueprint $table) {
            $table->index(['archived_at', 'created_at'], 'feed_posts_archived_created_idx');
            $table->index(['visibility', 'archived_at', 'created_at'], 'feed_posts_visibility_archived_created_idx');
        });

        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->index(['conversation_id', 'created_at'], 'conversation_messages_conversation_created_idx');
            $table->index(['conversation_id', 'sender_id', 'read_at'], 'conversation_messages_read_lookup_idx');
        });

        Schema::table('climate_records', function (Blueprint $table) {
            $table->index(['record_date', 'id'], 'climate_records_date_id_idx');
        });

        Schema::table('heatmap_areas', function (Blueprint $table) {
            $table->index(['latitude', 'longitude'], 'heatmap_areas_lat_lng_idx');
        });
    }

    public function down(): void
    {
        Schema::table('heatmap_areas', function (Blueprint $table) {
            $table->dropIndex('heatmap_areas_lat_lng_idx');
        });

        Schema::table('climate_records', function (Blueprint $table) {
            $table->dropIndex('climate_records_date_id_idx');
        });

        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->dropIndex('conversation_messages_read_lookup_idx');
            $table->dropIndex('conversation_messages_conversation_created_idx');
        });

        Schema::table('feed_posts', function (Blueprint $table) {
            $table->dropIndex('feed_posts_visibility_archived_created_idx');
            $table->dropIndex('feed_posts_archived_created_idx');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_user_read_created_idx');
        });
    }
};
