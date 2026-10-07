<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\FarmerPlantingSchedule;
use App\Services\Farmer\FarmerMissingInfoNotifier;
use App\Services\Farmer\FarmService;
use App\Services\Farmer\YieldSurveyService;
use App\Support\BusinessClock;
use App\Support\LianBarangays;
use App\Support\RiceSeason;
use App\Support\SchemaCache;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Illuminate\View\View;

/**
 * "Aking Bukid" (My Farm): the signed-in farmer's own farms (add, edit, archive), the Activity Calendar where they set
 * when they planted and harvested, the actual-harvest question, their planting schedules, their harvest records
 * (printable) and their upcoming dates. Nothing of another farmer is read.
 */
class FarmerMyFarmController extends Controller
{
    public function __invoke(Request $request, FarmService $farmService, YieldSurveyService $survey, FarmerMissingInfoNotifier $missing): View
    {
        $user = $request->user();
        $missing->syncOnPageLoad($user);

        $farms = $farmService->farms($user);
        $schedules = SchemaCache::hasTable('farmer_planting_schedules')
            ? FarmerPlantingSchedule::query()->where('user_id', $user->id)->orderByDesc('planting_date')->get()
            : collect();
        // Archived farms keep their records, so their names are still needed beside those records.
        $farmNames = FarmService::ready() ? Farm::query()->where('user_id', $user->id)->pluck('name', 'id') : collect();
        $farmName = fn (FarmerPlantingSchedule $s): ?string => $s->farm_id ? $farmNames->get($s->farm_id) : null;

        return view('farmer-records.my-farm', [
            'farms' => $farms->map(fn (Farm $farm, int $i): array => [
                'id' => $farm->id,
                'name' => $farm->name,
                'barangay' => $farm->barangay,
                'area' => $farm->hasArea() ? (float) $farm->area_hectares : null,
                'farm_type' => $farm->farm_type,
                'type' => $this->typeLabel($farm->farm_type),
                'primary' => $i === 0,
            ])->values()->all(),
            'farmsReady' => FarmService::ready(),
            'showFarmColumn' => $farmNames->count() > 1,
            'barangays' => LianBarangays::all(),
            'farmTypes' => collect(Farm::FARM_TYPES)->mapWithKeys(fn (string $type): array => [$type => $this->typeLabel($type)])->all(),
            'calendar' => $this->calendar($request, $schedules, $farmName, $farmNames->count() > 1),
            'yieldSurvey' => $this->yieldSurvey($survey->pending($user), $survey, $farmName),
            'schedules' => $schedules->map(fn (FarmerPlantingSchedule $s): array => [
                'id' => $s->id,
                'farm' => $farmName($s),
                'planting_date' => $s->planting_date,
                'harvest_date' => $s->harvestDateForPlanning(),
                'season' => $this->seasonLabel($s),
                'status' => $this->statusLabel($s->monitoringStatus()),
            ])->all(),
            'harvests' => $this->harvests($schedules, $farmName),
            'events' => $this->upcoming($schedules),
            'printedAt' => BusinessClock::now(),
        ]);
    }

    private function typeLabel(?string $type): ?string
    {
        $key = 'farmer.my_farm.water.'.strtolower((string) $type);

        return $type && Lang::has($key) ? __($key) : ($type ?: null);
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
     * The Activity Calendar for one month (?month=YYYY-MM, this month by default): the farmer's own planting dates,
     * harvest dates and expected harvests, as a month grid and as a list (the phone shows the list).
     *
     * @param  Collection<int, FarmerPlantingSchedule>  $schedules
     * @return array<string, mixed>
     */
    private function calendar(Request $request, Collection $schedules, \Closure $farmName, bool $showFarm): array
    {
        $today = CarbonImmutable::parse(BusinessClock::today()->toDateString());
        $asked = $request->query('month');
        $month = is_string($asked) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $asked) ? CarbonImmutable::parse($asked.'-01') : $today->startOfMonth();
        // Within a few years of today: enough for past seasons and next year's plan.
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
        $cursor = $month->startOfWeek(CarbonImmutable::SUNDAY);
        $last = $month->endOfMonth()->endOfWeek(CarbonImmutable::SATURDAY);
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
            'today' => $today->toDateString(),
            // The date the pop-up opens with from the "Add Activity" button: today when this month is shown.
            'default_date' => $month->format('Y-m') === $today->format('Y-m') ? $today->toDateString() : $month->toDateString(),
            'min' => $today->subYears(3)->toDateString(),
            'max' => $today->addYear()->toDateString(),
            'weeks' => $weeks,
            'list' => collect($byDate)->map(fn (array $events, string $date): array => [
                'date' => CarbonImmutable::parse($date),
                'events' => $events,
            ])->values()->all(),
        ];
    }

    /**
     * "Ano ang tunay mong ani?" for the latest harvest that has no yield yet, with the yield predicted for it.
     *
     * @return ?array<string, mixed>
     */
    private function yieldSurvey(?FarmerPlantingSchedule $schedule, YieldSurveyService $survey, \Closure $farmName): ?array
    {
        if ($schedule === null) {
            return null;
        }

        return [
            'id' => $schedule->id,
            'farm' => $farmName($schedule),
            'season' => $this->seasonLabel($schedule),
            'harvested_on' => $schedule->harvested_on ?? $schedule->harvestDateForPlanning(),
            'predicted' => $survey->predictedFor($schedule)['value'],
        ];
    }

    /**
     * Recorded harvests (tons per hectare), newest first.
     *
     * @param  Collection<int, FarmerPlantingSchedule>  $schedules
     * @return array<int, array<string, mixed>>
     */
    private function harvests(Collection $schedules, \Closure $farmName): array
    {
        if (! SchemaCache::hasColumn('farmer_planting_schedules', 'actual_yield')) {
            return [];
        }

        return $schedules
            ->filter(fn (FarmerPlantingSchedule $s): bool => is_numeric($s->actual_yield) && (float) $s->actual_yield > 0)
            ->sortByDesc(fn (FarmerPlantingSchedule $s): int => ($s->harvested_on ?? $s->harvestDateForPlanning() ?? $s->planting_date)?->timestamp ?? 0)
            ->map(fn (FarmerPlantingSchedule $s): array => [
                'farm' => $farmName($s),
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
