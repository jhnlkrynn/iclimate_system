<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('a_i_chats', function (Blueprint $table) {
            $table->string('source_type')->default('Machine Learning')->after('intent')->index();
            $table->string('source_name')->nullable()->after('source_type');
            $table->text('source_url')->nullable()->after('source_name');
            $table->string('language')->default('English')->after('source_url');
            $table->json('memory')->nullable()->after('language');
        });
    }

    public function down(): void
    {
        Schema::table('a_i_chats', function (Blueprint $table) {
            $table->dropColumn([
                'source_type',
                'source_name',
                'source_url',
                'language',
                'memory',
            ]);
        });
    }
};
