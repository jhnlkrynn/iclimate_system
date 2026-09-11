<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('heatmap_areas')
            ->whereIn('barangay', [
                'Barangay 1 (Pob.)',
                'Barangay 2 (Pob.)',
                'Barangay 3 (Pob.)',
                'Barangay 4 (Pob.)',
            ])
            ->delete();
    }

    public function down(): void
    {
        // Placeholder records are intentionally not restored.
    }
};
