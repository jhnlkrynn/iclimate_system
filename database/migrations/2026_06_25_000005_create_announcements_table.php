<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->enum('category', ['News', 'Event', 'Training', 'Seed Distribution', 'Fertilizer Distribution'])->index();
            $table->foreignId('posted_by')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['Draft', 'Published'])->default('Draft')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};