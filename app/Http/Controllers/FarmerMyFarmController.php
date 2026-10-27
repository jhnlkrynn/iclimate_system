<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\FarmerPlantingSchedule;
use App\Services\Risk\HeatMapPanel;
use App\Support\ActiveFarm;
use App\Support\BusinessClock;
use App\Support\FarmSize;
use App\Support\RiceSeason;
use App\Support\SchemaCache;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Illuminate\View\View;

/**
 * "Aking Bukid" (My Farm): the signed-in farmer's farms (My Farms: add, edit, map, set as primary, remove), then the
 * current farm's size and type, its planting schedules, harvest records (printable) and upcoming planting and harvest
 * dates. Nothing of another farmer is read.
 */
class FarmerMyFarmController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        // Farmer – Naicel (2026-10): missing farm information goes to the notification bell.
        app(\App\Services\Farmer\FarmerMissingInfoNotifier::class)->syncOnPageLoad($user);
        $profile = $user->farmerProfile;
        $farms = ActiveFarm::farms($user);
        $current = ActiveFarm::current($user);
        $schedules = SchemaCache::hasTable('farmer_planting_schedules')
            ? FarmerPlantingSchedule::query()->where('user_id', $user->id)
                // The current farm's plans (older plans without a farm count as the primary farm's).
                ->when($current !== null, fn ($q) => $q->where(fn ($w) => $w->where('farm_id', $current->getKey())
                    ->when($current->is_primary, fn ($p) => $p->orWhereNull('farm_id'))))
                ->orderByDesc('planting_date')->get()
            : collect();
        FarmerPlantingSchedule::withVarieties($schedules);
        $farmType = $current !== null ? $current->farm_type : $profile?->farm_type;
        $type = $farmType ? strtolower((string) $farmType) : null;

        return view('farmer-records.my-farm', [
            'available' => ActiveFarm::available(),
            'farms' => $farms->map(fn (Farm $f): array => [
                'id' => $f->getKey(),
                'name' => $f->farm_name,
                'barangay' => $f->barangay ? HeatMapPanel::displayName($f->barangay) : null,
                'location' => $f->location_description,
                'size' => FarmSize::describe($f),
                'mapped' => $f->boundary !== null,
                'primary' => (bool) $f->is_primary,
                'current' => $current !== null && $f->getKey() === $current->getKey(),
            ])->all(),
            'current' => $current,
            'farm' => [
                'name' => $current?->farm_name,
                'barangay' => $current !== null ? ($current->barangay ? HeatMapPanel::displayName($current->barangay) : null) : ($user->registeredBarangay() ?? ($profile?->barangay ?: $user->barangay)),
                'area' => $current !== null ? null : ((float) ($profile?->farm_area ?? 0) > 0 ? (float) $profile->farm_area : null),
                'size' => $current !== null ? FarmSize::describe($current) : null,
                'type' => $type && Lang::has('farmer.my_farm.water.'.$type) ? __('farmer.my_farm.water.'.$type) : null,
            ],
            'schedules' => $schedules->map(fn (FarmerPlantingSchedule $s): array => [
                'id' => $s->id,
                'planting_date' => $s->planting_date,
                'harvest_date' => $s->harvestDateForPlanning(),
                'variety' => $s->varietyLabel(),
                'season' => $this->seasonLabel($s),
                'status' => $this->statusLabel($s->monitoringStatus()),
            ])->all(),
            'harvests' => $this->harvests($schedules),
            'events' => $this->upcoming($schedules),
            'printedAt' => BusinessClock::now(),
        ] + $this->activityData($request, $farms, $current));
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
                // Panel revision 1.3
                'timing' => $s->harvest_timing,
                'timing_text' => $s->harvestTimingSummary(app()->getLocale()),
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
                $events[] = ['date' => $harvest, 'label' => __('farmer.my_farm_page.event_harvest').(($variety = $s->varietyLabel()) ? ' · '.$variety : '')];
            }
        }
        usort($events, fn (array $a, array $b): int => $a['date'] <=> $b['date']);

        return array_slice($events, 0, 10);
    }

    // ------------------------------------------------------------------ Farmer – Naicel (2026-10)

    /**
     * The Activity Calendar (all of the farmer's own planting and harvest dates, with the farm's name when there are two
     * or more farms) and the actual-harvest question for the latest harvest without a yield.
     *
     * @param  Collection<int, Farm>  $farms
     * @return array<string, mixed>
     */
    private function activityData(Request $request, Collection $farms, ?Farm $current): array
    {
        $user = $request->user();
        $all = SchemaCache::hasTable('farmer_planting_schedules')
            ? FarmerPlantingSchedule::query()->where('user_id', $user->id)->orderByDesc('planting_date')->get()
            : collect();
        FarmerPlantingSchedule::withVarieties($all);
        // Archived farms keep their records, so their names are still needed beside those records.
        $names = ActiveFarm::available() ? Farm::query()->ownedBy($user)->pluck('farm_name', 'id') : collect();
        $primaryId = $farms->first()?->getKey();
        $farmName = fn (FarmerPlantingSchedule $s): ?string => $names->get($s->farm_id ?? $primaryId);
        $survey = app(\App\Services\Farmer\YieldSurveyService::class);
        $pending = $survey->pending($user);

        return [
            // Panel revision 1.3: each farm's open planting's expected harvest, so the dialog can ask why a harvest is early or late.
            'activityFarms' => $farms->map(fn (Farm $f): array => [
                'id' => $f->getKey(),
                'name' => $f->farm_name,
                'expected_harvest' => $all->first(fn (FarmerPlantingSchedule $s): bool => (int) ($s->farm_id ?? $primaryId) === (int) $f->getKey()
                    && $s->harvested_on === null && $s->progress_status !== 'Harvested')?->harvestDateForPlanning()?->toDateString(),
            ])->values()->all(),
            'activityFarmId' => $current?->getKey(),
            'calendar' => $this->calendar($request, $all, $farmName, $farms->count() > 1),
            'yieldSurvey' => $pending === null ? null : [
                'id' => $pending->id,
                'farm' => $farms->count() > 1 ? $farmName($pending) : null,
                'season' => $this->seasonLabel($pending),
                'harvested_on' => $pending->harvested_on ?? $pending->harvestDateForPlanning(),
                'predicted' => $survey->predictedFor($pending)['value'],
                'area' => is_numeric($pending->farm_area_hectares) ? (float) $pending->farm_area_hectares : null,   // panel revision 1.2
            ],
        ];
    }

    /**
     * One month (?month=YYYY-MM, this month by default) of the farmer's own planting dates, harvest dates and expected
     * harvests, as a month grid and as a list (the phone shows the list).
     *
     * @param  Collection<int, FarmerPlantingSchedule>  $schedules
     * @return array<string, mixed>
     */
    private function calendar(Request $request, Collection $schedules, \Closure $farmName, bool $showFarm): array
    {
        $today = \Carbon\CarbonImmutable::parse(BusinessClock::today()->toDateString());
        $asked = $request->query('month');
        $month = is_string($asked) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $asked) ? \Carbon\CarbonImmutable::parse($asked.'-01') : $today->startOfMonth();
        if (abs($month->year - $today->year) > 5) {
            $month = $today->startOfMonth();
        }

        $byDate = [];
        $add = function ($date, string $kind, FarmerPlantingSchedule $s) use (&$byDate, $month, $farmName, $showFarm): void {
            if ($date === null || $date->format('Y-m') !== $month->format('Y-m')) {
                return;
            }
            $byDate[$date->format('Y-m-d')][] = [
                'kind' => $kind,
                'label' => __('farmer.activity.'.$kind),
                'farm' => $showFarm ? $farmName($s) : null,
                'edit' => route('farmer.planting-schedules.edit', $s->id),
                // Panel revision 1.3: "Early (12 days) – Heavy rain / flooding" on a recorded harvest.
                'timing' => $kind === 'harvested' && $s->harvest_timing ? $s->harvest_timing : null,
                'timing_text' => $kind === 'harvested' ? $s->harvestTimingSummary(app()->getLocale()) : null,
                'variety' => $kind === 'expected' ? $s->varietyLabel() : null,      // the variety its expected date comes from
            ];
        };
        foreach ($schedules as $s) {
            $add($s->planting_date, 'planted', $s);
            if ($s->harvested_on) {
                $add($s->harvested_on, 'harvested', $s);
            } elseif ($s->progress_status !== 'Harvested') {
                $add($s->harvestDateForPlanning(), 'expected', $s);
            }
        }
        ksort($byDate);

        $weeks = [];
        $cursor = $month->startOfWeek(\Carbon\CarbonImmutable::SUNDAY);
        $last = $month->endOfMonth()->endOfWeek(\Carbon\CarbonImmutable::SATURDAY);
        while ($cursor <= $last) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $key = $cursor->format('Y-m-d');
                $week[] = [
                    'date' => $key,
                    'day' => $cursor->day,
                    'in_month' => $cursor->month === $month->month,
                    'is_today' => $cursor->isSameDay($today),
                    'label' => $cursor->locale(app()->getLocale())->translatedFormat('F j, Y'),
                    'events' => $cursor->month === $month->month ? ($byDate[$key] ?? []) : [],
                ];
                $cursor = $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return [
            'title' => $month->locale(app()->getLocale())->translatedFormat('F Y'),
            'month' => $month->format('Y-m'),
            'prev' => route('farmer.my-farm', ['month' => $month->subMonth()->format('Y-m')]).'#activity',
            'next' => route('farmer.my-farm', ['month' => $month->addMonth()->format('Y-m')]).'#activity',
            'default_date' => $month->format('Y-m') === $today->format('Y-m') ? $today->toDateString() : $month->toDateString(),
            'min' => $today->subYears(3)->toDateString(),
            'max' => $today->addYear()->toDateString(),
            'weeks' => $weeks,
            'list' => collect($byDate)->map(fn (array $events, string $date): array => [
                'date' => \Carbon\CarbonImmutable::parse($date),
                'events' => $events,
            ])->values()->all(),
        ];
    }

    // ------------------------------------------------------------------ end Farmer – Naicel
}
