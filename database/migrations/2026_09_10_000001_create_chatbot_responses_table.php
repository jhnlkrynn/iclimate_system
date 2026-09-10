<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_responses', function (Blueprint $table) {
            $table->id();
            $table->string('category')->index();
            $table->text('question');
            $table->longText('answer');
            $table->json('keywords');
            $table->json('visible_roles')->nullable();
            $table->string('status')->default('Active')->index();
            $table->unsignedInteger('times_used')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_responses');
    }
};
