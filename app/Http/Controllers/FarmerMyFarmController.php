<?php

namespace App\Http\Controllers;

use App\Models\FarmerPlantingSchedule;
use App\Support\BusinessClock;
use App\Support\RiceSeason;
use App\Support\SchemaCache;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Illuminate\View\View;

/**
 * "Aking Bukid" (My Farm): the signed-in farmer's own farm only — farm size and type, their planting schedules, their
 * harvest records (printable) and their upcoming planting and harvest dates. Nothing of another farmer is read.
 */
class FarmerMyFarmController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $profile = $user->farmerProfile;
        $schedules = SchemaCache::hasTable('farmer_planting_schedules')
            ? FarmerPlantingSchedule::query()->where('user_id', $user->id)->orderByDesc('planting_date')->get()
            : collect();
        $type = $profile?->farm_type ? strtolower((string) $profile->farm_type) : null;

        return view('farmer-records.my-farm', [
            'farm' => [
                'barangay' => $user->registeredBarangay() ?? ($profile?->barangay ?: $user->barangay),
                'area' => (float) ($profile?->farm_area ?? 0) > 0 ? (float) $profile->farm_area : null,
                'type' => $type && Lang::has('farmer.my_farm.water.'.$type) ? __('farmer.my_farm.water.'.$type) : null,
            ],
            'schedules' => $schedules->map(fn (FarmerPlantingSchedule $s): array => [
                'id' => $s->id,
                'planting_date' => $s->planting_date,
                'harvest_date' => $s->harvestDateForPlanning(),
                'season' => $this->seasonLabel($s),
                'status' => $this->statusLabel($s->monitoringStatus()),
            ])->all(),
            'harvests' => $this->harvests($schedules),
            'events' => $this->upcoming($schedules),
            'printedAt' => BusinessClock::now(),
        ]);
    }

    private function seasonLabel(FarmerPlantingSchedule $schedule): ?string
    {
        $season = $schedule->season ?: ($schedule->planting_date ? RiceSeason::forDate($schedule->planting_date) : null);

        return $season ? __('farmer.my_farm_page.season_names.'.strtolower((string) $season)) : null;
    }

    private function statusLabel(string $status): string
    {
        return Lang::has('farmer.my_farm_page.statuses.'.$status) ? __('farmer.my_farm_page.statuses.'.$status) : str($status)->replace('_', ' ')->title()->toString();
    }

    /**
     * Recorded harvests (tons per hectare), newest first.
     *
     * @param  Collection<int, FarmerPlantingSchedule>  $schedules
     * @return array<int, array<string, mixed>>
     */
    private function harvests(Collection $schedules): array
    {
        if (! SchemaCache::hasColumn('farmer_planting_schedules', 'actual_yield')) {
            return [];
        }

        return $schedules
            ->filter(fn (FarmerPlantingSchedule $s): bool => is_numeric($s->actual_yield) && (float) $s->actual_yield > 0)
            ->sortByDesc(fn (FarmerPlantingSchedule $s): int => ($s->harvested_on ?? $s->harvestDateForPlanning() ?? $s->planting_date)?->timestamp ?? 0)
            ->map(fn (FarmerPlantingSchedule $s): array => [
                'harvested_on' => $s->harvested_on ?? $s->harvestDateForPlanning(),
                'planting_date' => $s->planting_date,
                'season' => $this->seasonLabel($s),
                'yield' => (float) $s->actual_yield,
                'area' => is_numeric($s->farm_area_hectares) ? (float) $s->farm_area_hectares : null,
            ])->values()->all();
    }

    /**
     * Upcoming planting and expected harvest dates from today, soonest first.
     *
     * @param  Collection<int, FarmerPlantingSchedule>  $schedules
     * @return array<int, array{date: \Carbon\Carbon, label: string}>
     */
    private function upcoming(Collection $schedules): array
    {
        $today = BusinessClock::today()->toDateString();
        $events = [];
        foreach ($schedules as $s) {
            if ($s->planting_date && $s->planting_date->toDateString() >= $today) {
                $events[] = ['date' => $s->planting_date, 'label' => __('farmer.my_farm_page.event_planting')];
            }
            $harvest = $s->harvestDateForPlanning();
            if ($harvest && $harvest->toDateString() >= $today && $s->monitoringStatus() !== 'harvested') {
                $events[] = ['date' => $harvest, 'label' => __('farmer.my_farm_page.event_harvest')];
            }
        }
        usort($events, fn (array $a, array $b): int => $a['date'] <=> $b['date']);

        return array_slice($events, 0, 10);
    }
}
