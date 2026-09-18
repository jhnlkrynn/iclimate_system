<?php

use App\Models\Report;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->boolean('is_generated')->default(false)->index()->after('payload');
        });

        DB::table('reports')
            ->select(['id', 'report_type', 'generated_by', 'payload'])
            ->orderBy('id')
            ->chunkById(200, function ($reports): void {
                foreach ($reports as $report) {
                    $payload = json_decode((string) $report->payload, true);
                    $isRealGeneratedReport = in_array($report->report_type, array_values(Report::TYPES), true)
                        && $report->generated_by !== null
                        && is_array($payload)
                        && array_key_exists('filters', $payload)
                        && array_key_exists('row_count', $payload);

                    if ($isRealGeneratedReport) {
                        DB::table('reports')->where('id', $report->id)->update(['is_generated' => true]);
                    }
                }
            });

        DB::table('reports')->where('is_generated', false)->delete();
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('is_generated');
        });
    }
};
