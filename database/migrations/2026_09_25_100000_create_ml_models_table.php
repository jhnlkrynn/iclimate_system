<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive model registry. It only records metadata about model files; it
     * never moves, rewrites or loads them.
     *
     * `active_slot` holds the purpose while a row is active and NULL otherwise.
     * Its unique index is what guarantees at most one active model per purpose
     * at the database level (NULLs never collide on a unique index).
     */
    public function up(): void
    {
        Schema::create('ml_models', function (Blueprint $table): void {
            $table->id();
            $table->string('purpose', 40)->default('rice_yield');
            $table->string('name');
            $table->string('model_type');
            $table->string('version', 40);
            $table->string('target_variable')->nullable();
            $table->string('file_path');
            $table->string('metadata_path')->nullable();
            $table->json('training_years')->nullable();
            $table->unsignedSmallInteger('test_year')->nullable();
            $table->json('feature_list')->nullable();
            $table->json('hyperparameters')->nullable();
            $table->json('compatibility')->nullable();
            $table->decimal('mae', 10, 4)->nullable();
            $table->decimal('rmse', 10, 4)->nullable();
            $table->decimal('r2', 10, 4)->nullable();
            $table->timestamp('trained_at')->nullable();
            $table->string('status', 20)->default('candidate');
            $table->boolean('is_active')->default(false);
            $table->string('active_slot', 40)->nullable()->unique();
            $table->timestamps();

            $table->unique(['purpose', 'version']);
            $table->index(['purpose', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ml_models');
    }
};
