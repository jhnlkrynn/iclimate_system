<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farmer_profiles', function (Blueprint $table) {
            $table->foreignId('farmer_organization_id')
                ->nullable()
                ->after('user_id')
                ->constrained('farmer_organizations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('farmer_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('farmer_organization_id');
        });
    }
};
