<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Panel revision 1.3: whether a harvest came On time, Early or Late against the expected harvest date (computed by
 * App\Support\HarvestTiming), and the farmer's reason for an early or late one (a reason key from
 * config/farm_calendar.php, plus free text for "Other").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farmer_planting_schedules', function (Blueprint $table) {
            if (! Schema::hasColumn('farmer_planting_schedules', 'harvest_timing')) {
                $table->string('harvest_timing', 10)->nullable()->index();
            }
            if (! Schema::hasColumn('farmer_planting_schedules', 'harvest_timing_reason')) {
                $table->string('harvest_timing_reason', 40)->nullable();
            }
            if (! Schema::hasColumn('farmer_planting_schedules', 'harvest_timing_reason_other')) {
                $table->string('harvest_timing_reason_other', 255)->nullable();
            }
        });

        // Harvests already recorded get their timing (their reason stays empty: it was not asked then).
        $tolerance = (int) config('farm_calendar.harvest_timing.on_time_tolerance_days', 3);
        DB::table('farmer_planting_schedules')->whereNotNull('harvested_on')->orderBy('id')
            ->select(['id', 'harvested_on', 'scheduled_harvest_date', 'harvest_month'])
            ->chunk(500, function ($rows) use ($tolerance): void {
                foreach ($rows as $row) {
                    $expected = $row->scheduled_harvest_date ?: ($row->harvest_month ? date('Y-m-t', strtotime((string) $row->harvest_month)) : null);
                    if (! $expected) {
                        continue;
                    }
                    $days = (int) round((strtotime(substr((string) $row->harvested_on, 0, 10)) - strtotime(substr((string) $expected, 0, 10))) / 86400);
                    DB::table('farmer_planting_schedules')->where('id', $row->id)->update([
                        'harvest_timing' => $days < -$tolerance ? 'Early' : ($days > $tolerance ? 'Late' : 'On time'),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('farmer_planting_schedules', function (Blueprint $table) {
            $table->dropColumn(['harvest_timing', 'harvest_timing_reason', 'harvest_timing_reason_other']);
        });
    }
};
