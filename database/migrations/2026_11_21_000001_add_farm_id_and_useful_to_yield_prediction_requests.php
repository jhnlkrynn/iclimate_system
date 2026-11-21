<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('yield_prediction_requests')) {
            return;
        }

        Schema::table('yield_prediction_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('yield_prediction_requests', 'farm_id')) {
                $table->foreignId('farm_id')->nullable()->after('user_id')->constrained('farms')->nullOnDelete();
            }
            if (! Schema::hasColumn('yield_prediction_requests', 'useful')) {
                $table->boolean('useful')->nullable()->after('completed_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('yield_prediction_requests')) {
            return;
        }

        Schema::table('yield_prediction_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('yield_prediction_requests', 'farm_id')) {
                $table->dropConstrainedForeignId('farm_id');
            }
            if (Schema::hasColumn('yield_prediction_requests', 'useful')) {
                $table->dropColumn('useful');
            }
        });
    }
};
