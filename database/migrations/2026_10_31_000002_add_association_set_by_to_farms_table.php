<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who answered a farm's association question (App\Services\Farms\FarmAssociations):
 *   farms.association_set_by           farmer | mao (null: answered before this, or by the sign-up backfill)
 *   farms.association_set_by_user_id   the MAO user, for an answer the MAO set in bulk (Undeclared farms)
 *   farms.association_set_at           when
 * users.association_prompt_snoozed_until: the farmer pressed "Later" on the dashboard's association card (7 days).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('farms') && ! Schema::hasColumn('farms', 'association_set_by')) {
            Schema::table('farms', function (Blueprint $table) {
                $table->string('association_set_by', 10)->nullable();
                $table->foreignId('association_set_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('association_set_at')->nullable();
            });
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'association_prompt_snoozed_until')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('association_prompt_snoozed_until')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('farms', 'association_set_by')) {
            Schema::table('farms', function (Blueprint $table) {
                $table->dropConstrainedForeignId('association_set_by_user_id');
                $table->dropColumn(['association_set_by', 'association_set_at']);
            });
        }
        if (Schema::hasColumn('users', 'association_prompt_snoozed_until')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('association_prompt_snoozed_until');
            });
        }
    }
};
