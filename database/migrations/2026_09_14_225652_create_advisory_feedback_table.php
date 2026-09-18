<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advisory_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planting_advisory_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_helpful')->nullable();
            $table->text('comment')->nullable();
            $table->text('mao_response')->nullable();
            $table->timestamp('mao_responded_at')->nullable();
            $table->foreignId('mao_responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['planting_advisory_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advisory_feedback');
    }
};
