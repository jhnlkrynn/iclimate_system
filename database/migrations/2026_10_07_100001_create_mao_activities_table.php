<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activities the MAO plans (MAO Planner): a planting event, training, seed distribution and so on, for one barangay or
 * for all of them (barangay is null). ends_on is set only when the activity runs for more than one day.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mao_activities')) {
            return;
        }

        Schema::create('mao_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('activity_type', 40)->index();
            $table->string('barangay')->nullable()->index();
            $table->date('starts_on')->index();
            $table->date('ends_on')->nullable();
            $table->string('title', 160);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mao_activities');
    }
};
