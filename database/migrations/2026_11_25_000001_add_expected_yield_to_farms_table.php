<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('farms')) {
            return;
        }

        Schema::table('farms', function (Blueprint $table): void {
            if (! Schema::hasColumn('farms', 'expected_yield_t_ha')) {
                $table->decimal('expected_yield_t_ha', 6, 2)->nullable();
            }
            if (! Schema::hasColumn('farms', 'expected_yield_season')) {
                $table->string('expected_yield_season', 32)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('farms')) {
            return;
        }

        Schema::table('farms', function (Blueprint $table): void {
            $drop = array_values(array_filter([
                Schema::hasColumn('farms', 'expected_yield_t_ha') ? 'expected_yield_t_ha' : null,
                Schema::hasColumn('farms', 'expected_yield_season') ? 'expected_yield_season' : null,
            ]));
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
