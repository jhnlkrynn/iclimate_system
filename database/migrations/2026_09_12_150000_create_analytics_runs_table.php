<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type')->index();
            $table->string('status')->default('pending')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cache_key')->nullable()->index();
            $table->json('input');
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_runs');
    }
};
