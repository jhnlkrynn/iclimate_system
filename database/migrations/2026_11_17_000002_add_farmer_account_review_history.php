<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedInteger('registration_revision')->default(0);
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('resubmission_note')->nullable();
        });
        Schema::create('farmer_account_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->string('decision', 20);
            $table->string('reason_key', 80)->nullable();
            $table->string('reason', 500)->nullable();
            $table->json('application');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'revision']);
        });
        Schema::table('notifications', function (Blueprint $table): void {
            $table->string('deduplication_key', 191)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropUnique(['deduplication_key']);
            $table->dropColumn('deduplication_key');
        });
        Schema::dropIfExists('farmer_account_reviews');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['registration_revision', 'reviewed_at', 'resubmission_note']);
        });
    }
};
