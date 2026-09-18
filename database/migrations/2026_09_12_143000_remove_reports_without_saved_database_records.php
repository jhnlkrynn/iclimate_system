<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('reports')
            ->select(['id', 'payload'])
            ->orderBy('id')
            ->chunkById(200, function ($reports): void {
                foreach ($reports as $report) {
                    $payload = json_decode((string) $report->payload, true);
                    $records = is_array($payload) ? ($payload['records'] ?? null) : null;
                    $columns = is_array($payload) ? ($payload['columns'] ?? null) : null;
                    $rowCount = is_array($payload) ? ($payload['row_count'] ?? null) : null;
                    $reportType = is_array($payload) ? ($payload['report_type'] ?? null) : null;

                    $hasSavedDatabaseRows = is_array($records)
                        && is_array($columns)
                        && is_int($rowCount)
                        && $rowCount === count($records)
                        && is_string($reportType);

                    if (! $hasSavedDatabaseRows) {
                        DB::table('reports')->where('id', $report->id)->delete();
                    }
                }
            });
    }

    public function down(): void
    {
        // Removed legacy count-only reports cannot be reconstructed safely.
    }
};
