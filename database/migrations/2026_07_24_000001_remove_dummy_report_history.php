<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('reports')
            ->whereIn('report_type', ['Climate', 'Production', 'Advisory', 'Heat Map'])
            ->delete();
    }

    public function down(): void
    {
        //
    }
};
