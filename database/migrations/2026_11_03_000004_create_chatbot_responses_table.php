<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_responses', function (Blueprint $table): void {
            $table->id();
            $table->string('category');
            $table->text('question');
            $table->text('answer');
            $table->string('language');
            $table->text('keywords');
            $table->text('synonyms')->nullable();
            $table->text('visible_roles')->nullable();
            $table->string('status');
            $table->unsignedBigInteger('times_used')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['category', 'language', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_responses');
    }
};
