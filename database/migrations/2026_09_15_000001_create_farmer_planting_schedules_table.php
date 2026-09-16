<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('farmer_planting_schedules')) {
            Schema::create('farmer_planting_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->date('planting_date')->index();
                $table->date('scheduled_harvest_date')->nullable()->index();
                $table->date('harvest_month')->nullable()->index();
                $table->enum('season', ['Wet', 'Dry'])->index();
                $table->enum('farm_type', ['Irrigated', 'Rainfed'])->index();
                $table->string('barangay')->nullable()->index();
                $table->decimal('farm_area_hectares', 10, 2)->nullable();
                $table->text('notes')->nullable();
                $table->string('progress_status')->nullable()->index();
                $table->decimal('predicted_yield', 10, 2)->nullable();
                $table->decimal('prediction_confidence', 5, 2)->nullable();
                $table->string('prediction_source')->nullable();
                $table->text('prediction_unavailable_reason')->nullable();
                $table->json('prediction_details')->nullable();
                $table->decimal('historical_same_month_average', 10, 2)->nullable();
                $table->decimal('yield_difference', 10, 2)->nullable();
                $table->string('comparison_interpretation')->nullable();
                $table->text('comparison_note')->nullable();
                $table->text('recommended_actions')->nullable();
                $table->timestamp('prediction_generated_at')->nullable();
                $table->timestamps();

                $table->index(['season', 'farm_type', 'barangay'], 'planting_schedule_monitor_filters');
            });
        }

        if (Schema::hasTable('rice_productions') && ! Schema::hasColumn('rice_productions', 'production_month')) {
            Schema::table('rice_productions', function (Blueprint $table) {
                $table->date('production_month')->nullable()->after('year')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('rice_productions') && Schema::hasColumn('rice_productions', 'production_month')) {
            Schema::table('rice_productions', function (Blueprint $table) {
                $table->dropColumn('production_month');
            });
        }

        Schema::dropIfExists('farmer_planting_schedules');
    }
};
