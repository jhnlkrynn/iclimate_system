<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('heatmap_areas', function (Blueprint $table) {
            $table->json('risk_components')->nullable()->after('risk_score');
        });
    }

    public function down(): void
    {
        Schema::table('heatmap_areas', function (Blueprint $table) {
            $table->dropColumn('risk_components');
        });
    }
};
