<?php

namespace App\Support;

use App\Models\FarmerPlantingSchedule;
use App\Models\User;

/**
 * What is still missing from a farmer's farm information, shown on the Profile page (it used to be a checklist on the
 * dashboard). Each item is one real gap with what filling it in unlocks; the wording is in lang/{en,fil}/farmer.php.
 */
final class FarmerSetupChecklist
{
    private function __construct() {}

    /** @return array{items: array<int, array<string, mixed>>, done: int, total: int} */
    public static function for(User $user): array
    {
        $profile = $user->farmerProfile;
        $checks = [
            ['key' => 'barangay', 'ok' => $user->registeredBarangay() !== null, 'href' => '#barangay'],
            ['key' => 'farm_type', 'ok' => (bool) $profile?->farm_type, 'href' => '#farm_type'],
            ['key' => 'farm_area', 'ok' => (float) ($profile?->farm_area ?? 0) > 0, 'href' => '#farm_area'],
        ];

        if (SchemaCache::hasColumn('farmer_planting_schedules', 'actual_yield')) {
            $unrecorded = FarmerPlantingSchedule::query()->where('user_id', $user->id)->get()
                ->first(fn (FarmerPlantingSchedule $s): bool => $s->monitoringStatus() === 'harvested' && ! (is_numeric($s->actual_yield) && (float) $s->actual_yield > 0));
            if ($unrecorded) {
                $checks[] = ['key' => 'harvest', 'ok' => false, 'href' => route('farmer.planting-schedules.edit', $unrecorded->id)];
            }
        }

        $locale = FarmerLanguage::resolve(request());
        $items = array_values(array_map(fn (array $c): array => $c + [
            'title' => __('farmer.setup.'.$c['key'].'.title', [], $locale),
            'benefit' => __('farmer.setup.'.$c['key'].'.benefit', [], $locale),
        ], array_filter($checks, fn (array $c): bool => ! $c['ok'])));

        return ['items' => $items, 'done' => count($checks) - count($items), 'total' => count($checks)];
    }
}
