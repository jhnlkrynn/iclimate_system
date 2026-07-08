<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_base', function (Blueprint $table) {
            $table->id();
            $table->text('question');
            $table->longText('answer');
            $table->string('category')->index();
            $table->json('keywords')->nullable();
            $table->string('source_type')->default('Knowledge Base')->index();
            $table->string('source_name')->nullable();
            $table->text('source_url')->nullable();
            $table->boolean('verified')->default(false)->index();
            $table->unsignedInteger('times_used')->default(0);
            $table->decimal('confidence', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_base');
    }
};
