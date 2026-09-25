<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per run of iclimate:verify-backup. Additive: it records what was
     * checked and the outcome; it never touches backups or the live database.
     * backup_filename is nullable because "no valid backup found" is itself a
     * recorded failure.
     */
    public function up(): void
    {
        Schema::create('backup_verifications', function (Blueprint $table): void {
            $table->id();
            $table->string('backup_filename')->nullable();
            $table->unsignedBigInteger('backup_size_bytes')->nullable();
            $table->string('backup_sha256', 64)->nullable();
            $table->timestamp('verified_at');
            $table->string('status', 10);
            $table->unsignedInteger('archive_entry_count')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->index('verified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_verifications');
    }
};
