<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('farm_type_yield_overrides')) {
            return;
        }

        Schema::create('farm_type_yield_overrides', function (Blueprint $table): void {
            $table->id();
            $table->decimal('rainfed_diff_percent', 6, 2);
            $table->text('note');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_type_yield_overrides');
    }
};
