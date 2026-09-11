<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Heat-map areas now come from boundary GeoJSON and verified data sources.
    }

    public function down(): void
    {
        // No seeded heat-map records are created by this migration.
    }
};
