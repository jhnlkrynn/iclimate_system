<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('a_i_chats')) {
            return;
        }

        Schema::table('a_i_chats', function (Blueprint $table): void {
            if (! Schema::hasColumn('a_i_chats', 'helpful')) {
                $table->boolean('helpful')->nullable()->after('response_time_ms');
            }
            if (! Schema::hasColumn('a_i_chats', 'feedback_reason')) {
                $table->string('feedback_reason', 500)->nullable()->after('helpful');
            }
            if (! Schema::hasColumn('a_i_chats', 'feedback_at')) {
                $table->timestamp('feedback_at')->nullable()->after('feedback_reason');
            }
            $table->index(['helpful', 'created_at'], 'ai_chats_helpful_created_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('a_i_chats')) {
            return;
        }

        Schema::table('a_i_chats', function (Blueprint $table): void {
            $table->dropIndex('ai_chats_helpful_created_idx');
            $table->dropColumn(['helpful', 'feedback_reason', 'feedback_at']);
        });
    }
};
