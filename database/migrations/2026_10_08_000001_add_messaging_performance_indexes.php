<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->index(['participant_one_id', 'last_message_at'], 'conversations_one_last_message_idx');
            $table->index(['participant_two_id', 'last_message_at'], 'conversations_two_last_message_idx');
        });

        Schema::table('conversation_messages', function (Blueprint $table): void {
            $table->string('client_message_id', 64)->nullable()->after('sender_id');
            $table->unique(['conversation_id', 'sender_id', 'client_message_id'], 'conversation_messages_client_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table): void {
            $table->dropUnique('conversation_messages_client_id_unique');
            $table->dropColumn('client_message_id');
        });
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropIndex('conversations_two_last_message_idx');
            $table->dropIndex('conversations_one_last_message_idx');
        });
    }
};
