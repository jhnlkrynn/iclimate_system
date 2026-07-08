<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('heatmap_areas', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('barangay');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->decimal('risk_score', 5, 2)->default(0)->after('risk_type');
            $table->decimal('predicted_yield', 8, 2)->nullable()->after('risk_score');
            $table->string('rainfall_status')->nullable()->after('predicted_yield');
            $table->string('planting_advisory')->nullable()->after('rainfall_status');
            $table->string('irrigation_recommendation')->nullable()->after('planting_advisory');
        });
    }

    public function down(): void
    {
        Schema::table('heatmap_areas', function (Blueprint $table) {
            $table->dropColumn([
                'latitude',
                'longitude',
                'risk_score',
                'predicted_yield',
                'rainfall_status',
                'planting_advisory',
                'irrigation_recommendation',
            ]);
        });
    }
};
