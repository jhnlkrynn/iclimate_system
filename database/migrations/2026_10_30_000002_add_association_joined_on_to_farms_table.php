<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * farms.association_joined_on: the date the farmer joined the farm's association, kept on an archived farm when its
 * membership was dropped (no farm in use needed it any more), so restoring the farm brings the membership back with
 * its original date (FarmAssociations::reconcile()).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('farms') || Schema::hasColumn('farms', 'association_joined_on')) {
            return;
        }

        Schema::table('farms', function (Blueprint $table) {
            $table->date('association_joined_on')->nullable()->after('association_note');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('farms', 'association_joined_on')) {
            Schema::table('farms', function (Blueprint $table) {
                $table->dropColumn('association_joined_on');
            });
        }
    }
};
