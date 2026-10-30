<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting a user account closes it instead of erasing the row (App\Services\Accounts\AccountDeletion), so the login
 * page can still tell the person why and IT can restore it within config user_deletion.restore_days.
 *
 *   users.status                  "Deleted" (a plain string since 2026_10_08_000001)
 *   users.deleted_reason          a key of config user_deletion.reasons
 *   users.deleted_reason_other    IT's words for "Other"
 *   users.deleted_note_internal   only IT ever sees it
 *   users.deleted_at / deleted_by when, and which IT account
 *   users.status_before_delete    what a restore brings back
 *
 * account_emails: every account email (approved, not approved, deleted, restored) with its recipient and whether it
 * was sent, for the "Last email" line on the user's page.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'deleted_reason')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('deleted_reason', 40)->nullable();
                $table->text('deleted_reason_other')->nullable();
                $table->text('deleted_note_internal')->nullable();
                $table->timestamp('deleted_at')->nullable()->index();
                $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status_before_delete', 20)->nullable();
            });
        }

        if (! Schema::hasTable('account_emails')) {
            Schema::create('account_emails', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('type', 20);          // approved | rejected | deleted | restored
                $table->string('recipient')->nullable();
                $table->string('status', 20);        // queued | sent | failed | no_valid_email
                $table->string('error', 500)->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_emails');
        if (Schema::hasColumn('users', 'deleted_reason')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('deleted_by');
                $table->dropIndex(['deleted_at']);
                $table->dropColumn(['deleted_reason', 'deleted_reason_other', 'deleted_note_internal', 'deleted_at', 'status_before_delete']);
            });
        }
    }
};
