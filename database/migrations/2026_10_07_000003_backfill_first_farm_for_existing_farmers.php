<?php

use App\Support\LianBarangays;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every existing farmer profile becomes ONE primary farm, "Farm 1", from what the profile already holds: its barangay
 * (only when it is one of Lian's 19 official names), farm size and farm type. The farmer's saved map (if any) and their
 * planting plans are linked to that farm. Nothing is removed or changed on the profile, the map or the plans.
 *
 * Safe to run again: a farmer who already has a farm is skipped, and only maps and plans without a farm are linked.
 * Size: the profile's farm_area is "exact"; it is "mapped" when it matches the saved map's measured area (within
 * 0.005 ha), and a profile without a size but with a map takes the measured area as "mapped". No size at all: "unknown".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('farms') || ! Schema::hasTable('farmer_profiles')) {
            return;
        }
        $linkBoundaries = Schema::hasColumn('farm_boundaries', 'farm_id');
        $linkPlans = Schema::hasTable('farmer_planting_schedules') && Schema::hasColumn('farmer_planting_schedules', 'farm_id');

        DB::table('farmer_profiles')
            ->select(['id', 'user_id', 'barangay', 'farm_area', 'farm_type'])
            ->orderBy('id')
            ->chunkById(200, function ($profiles) use ($linkBoundaries, $linkPlans): void {
            $withFarm = DB::table('farms')->whereIn('user_id', $profiles->pluck('user_id'))->pluck('user_id')->flip();
            $boundaries = $linkBoundaries
                ? DB::table('farm_boundaries')
                    ->select(['id', 'farmer_profile_id', 'barangay', 'calculated_area_hectares'])
                    ->whereIn('farmer_profile_id', $profiles->pluck('id'))
                    ->whereNull('farm_id')
                    ->orderBy('id')
                    ->get()
                    ->groupBy('farmer_profile_id')
                : collect();

            $pending = [];
            foreach ($profiles as $profile) {
                if (isset($withFarm[$profile->user_id])) {
                    continue;
                }

                $boundary = $boundaries->get($profile->id)?->first();
                $mapped = $boundary !== null && (float) $boundary->calculated_area_hectares > 0 ? round((float) $boundary->calculated_area_hectares, 4) : null;
                $area = is_numeric($profile->farm_area) && (float) $profile->farm_area > 0 ? (float) $profile->farm_area : null;
                [$size, $method] = match (true) {
                    $area !== null && $mapped !== null && abs($area - $mapped) < 0.005 => [$mapped, 'mapped'],
                    $area !== null => [$area, 'exact'],
                    $mapped !== null => [$mapped, 'mapped'],
                    default => [null, 'unknown'],
                };
                $barangay = LianBarangays::canonicalName($profile->barangay)
                    ?? ($boundary !== null ? LianBarangays::canonicalName($boundary->barangay ?? null) : null);
                $type = in_array($profile->farm_type, ['Irrigated', 'Rainfed'], true) ? $profile->farm_type : null;
                $pending[] = [
                    'profile_id' => (int) $profile->id,
                    'user_id' => (int) $profile->user_id,
                    'boundary_id' => $boundary?->id,
                    'farm' => [
                        'user_id' => $profile->user_id,
                        'farm_name' => 'Farm 1',
                        'barangay' => $barangay,
                        'location_method' => $boundary !== null ? 'boundary' : 'barangay',
                        'farm_size_hectares' => $size,
                        'farm_size_method' => $method,
                        'farm_size_unit' => $size !== null ? 'hectares' : null,
                        'farm_type' => $type,
                        'is_primary' => true,
                    ],
                ];
            }

            if ($pending === []) {
                return;
            }

            $now = now();
            $farmRows = array_map(static fn (array $item): array => $item['farm'] + [
                'created_at' => $now,
                'updated_at' => $now,
            ], $pending);
            $userIds = array_column($pending, 'user_id');

            DB::transaction(function () use ($farmRows, $pending, $userIds, $linkPlans): void {
                DB::table('farms')->insert($farmRows);

                // Each pending user had no farm before this chunk's insert, so the first row is the one just created.
                $farmIds = DB::table('farms')
                    ->whereIn('user_id', $userIds)
                    ->orderBy('id')
                    ->get(['id', 'user_id'])
                    ->groupBy('user_id')
                    ->map(static fn ($farms): int => (int) $farms->first()->id);

                $boundaryCases = [];
                foreach ($pending as $item) {
                    $farmId = $farmIds->get($item['user_id']);
                    if ($farmId === null) {
                        throw new RuntimeException("Farm backfill failed for user {$item['user_id']}.");
                    }

                    if ($item['boundary_id'] !== null) {
                        $boundaryCases[] = 'WHEN '.(int) $item['boundary_id'].' THEN '.(int) $farmId;
                    }
                }
                if ($boundaryCases !== []) {
                    $boundaryIds = array_filter(array_column($pending, 'boundary_id'));
                    DB::table('farm_boundaries')
                        ->whereIn('id', $boundaryIds)
                        ->whereNull('farm_id')
                        ->update(['farm_id' => DB::raw('CASE id '.implode(' ', $boundaryCases).' ELSE farm_id END')]);
                }

                if ($linkPlans) {
                    $scheduleCases = [];
                    foreach ($pending as $item) {
                        $farmId = $farmIds->get($item['user_id']);
                        $scheduleCases[] = 'WHEN '.(int) $item['user_id'].' THEN '.(int) $farmId;
                    }
                    DB::table('farmer_planting_schedules')
                        ->whereIn('user_id', $userIds)
                        ->whereNull('farm_id')
                        ->update(['farm_id' => DB::raw('CASE user_id '.implode(' ', $scheduleCases).' ELSE farm_id END')]);
                }
            });
        });
    }

    public function down(): void
    {
        // Farms made here may since hold the farmer's own changes, plans and maps; they are not removed.
    }
};
