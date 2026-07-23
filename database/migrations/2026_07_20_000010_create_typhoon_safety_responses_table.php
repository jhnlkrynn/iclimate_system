<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('typhoon_safety_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_key');
            $table->string('event_title');
            $table->enum('status', ['safe', 'needs_help']);
            $table->text('note')->nullable();
            $table->timestamp('responded_at');
            $table->timestamps();

            $table->unique(['user_id', 'event_key']);
            $table->index(['event_key', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('typhoon_safety_responses');
    }
};
