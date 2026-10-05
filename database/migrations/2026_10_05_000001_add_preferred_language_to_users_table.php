<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The Farmer dashboard's language (en | fil), kept on the account so it follows the farmer to any device. */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'preferred_language')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('preferred_language', 5)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'preferred_language')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('preferred_language');
        });
    }
};
