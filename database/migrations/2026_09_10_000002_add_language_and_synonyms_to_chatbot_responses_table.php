<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatbot_responses', function (Blueprint $table) {
            $table->string('language')->default('English')->after('answer')->index();
            $table->json('synonyms')->nullable()->after('keywords');
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_responses', function (Blueprint $table) {
            $table->dropColumn(['language', 'synonyms']);
        });
    }
};
