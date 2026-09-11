<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
<<<<<<< Updated upstream
        // Heat-map areas now come from boundary GeoJSON and verified data sources.
=======
        // Heat-map risk records are generated only from verified weather,
        // climate, production, prediction, and farm inputs.
>>>>>>> Stashed changes
    }

    public function down(): void
    {
<<<<<<< Updated upstream
        // No seeded heat-map records are created by this migration.
=======
        // No default heat-map records are seeded by this migration.
>>>>>>> Stashed changes
    }
};
