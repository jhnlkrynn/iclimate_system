<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rice_productions') && Schema::hasColumn('rice_productions', 'irrigation_type')) {
            Schema::table('rice_productions', function (Blueprint $table): void {
                $table->string('irrigation_type')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Existing null values are intentionally preserved; requiring a value again would be destructive.
    }
};
