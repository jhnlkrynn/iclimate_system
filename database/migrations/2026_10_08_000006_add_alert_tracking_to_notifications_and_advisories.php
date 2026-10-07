<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manuscript – Naicel (2026-10-08), Phase 6: advisory alerts.
 *  - notifications.read_at (when it was read) and planting_advisory_id (the advisory an alert is about), so the MAO
 *    sees how many farmers each alert reached and how many read it;
 *  - planting_advisories.scheduled_for: an alert to publish later (iclimate:publish-scheduled-alerts).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications', 'read_at')) {
                $table->timestamp('read_at')->nullable();
            }
            if (! Schema::hasColumn('notifications', 'planting_advisory_id')) {
                $table->foreignId('planting_advisory_id')->nullable()->constrained('planting_advisories')->nullOnDelete();
            }
        });

        Schema::table('planting_advisories', function (Blueprint $table) {
            if (! Schema::hasColumn('planting_advisories', 'scheduled_for')) {
                $table->timestamp('scheduled_for')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('planting_advisory_id');
            $table->dropColumn('read_at');
        });
        Schema::table('planting_advisories', function (Blueprint $table) {
            $table->dropColumn('scheduled_for');
        });
    }
};
