<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->enum('category', ['Update', 'Program', 'Activity', 'Training', 'Advisory', 'Announcement'])->default('Update')->index();
            $table->enum('visibility', ['All Farmers', 'All Users'])->default('All Farmers')->index();
            $table->timestamp('event_date')->nullable();
            $table->timestamps();
        });

        Schema::create('feed_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_post_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->enum('media_type', ['image', 'video', 'file'])->index();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });

        Schema::create('feed_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('feed_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['Like', 'Love', 'Care', 'Wow', 'Helpful'])->default('Like');
            $table->timestamps();
            $table->unique(['feed_post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_reactions');
        Schema::dropIfExists('feed_comments');
        Schema::dropIfExists('feed_media');
        Schema::dropIfExists('feed_posts');
    }
};
