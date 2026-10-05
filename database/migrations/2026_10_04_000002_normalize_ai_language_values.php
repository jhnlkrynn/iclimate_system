<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('ai_language', 'English')->update(['ai_language' => 'en']);
        DB::table('users')->where('ai_language', 'Tagalog')->update(['ai_language' => 'tl']);
        DB::table('users')
            ->whereNotNull('ai_language')
            ->whereNotIn('ai_language', ['en', 'tl'])
            ->update(['ai_language' => null]);
    }

    public function down(): void
    {
        DB::table('users')->where('ai_language', 'en')->update(['ai_language' => 'English']);
        DB::table('users')->where('ai_language', 'tl')->update(['ai_language' => 'Tagalog']);
    }
};
