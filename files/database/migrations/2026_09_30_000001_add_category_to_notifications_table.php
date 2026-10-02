<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Words the farmer dashboard used to guess "weather" from; used once, here, to label existing warnings. */
    private const WEATHER_WORDS = ['weather', 'rain', 'rainfall', 'storm', 'typhoon', 'flood', 'drought', 'heat', 'pagasa', 'climate'];

    public function up(): void
    {
        if (Schema::hasColumn('notifications', 'category')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->string('category', 30)->nullable()->after('type')->index();
        });

        DB::table('notifications')->where('type', 'Warning')->where(function ($q): void {
            foreach (self::WEATHER_WORDS as $word) {
                $q->orWhere('title', 'like', '%'.$word.'%')->orWhere('message', 'like', '%'.$word.'%');
            }
        })->update(['category' => 'weather']);
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }
};
