<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panel revision 4.4 D: the MAO Activity Log. One row per thing an MAO user created, edited, verified, approved,
 * rejected or deleted, with the record it touched and its barangay. Name and position are copied in, so the log still
 * says who did it after an account is renamed or removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mao_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name');
            $table->string('user_position', 160)->nullable();
            $table->string('action', 20)->index();             // created / edited / verified / approved / rejected / deleted / other
            $table->string('description');
            $table->string('record_type', 80)->nullable()->index();
            $table->unsignedBigInteger('record_id')->nullable();
            $table->string('record_label')->nullable();
            $table->string('barangay')->nullable()->index();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mao_activity_logs');
    }
};
