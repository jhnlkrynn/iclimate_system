<?php

namespace App\Http\Controllers;

use App\Models\FarmerPlantingSchedule;
use App\Services\PlantingSchedulePredictionService;
use App\Support\LianBarangays;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FarmerPlantingScheduleController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (! $this->featureReady()) {
            return $this->featureUnavailable();
        }

        $profile = $request->user()->farmerProfile;

        return view('planting-schedules.form', [
            'schedule' => new FarmerPlantingSchedule([
                'barangay' => $profile?->barangay ?: $request->user()->barangay,
                'farm_type' => $profile?->farm_type,
                'farm_area_hectares' => $profile?->farm_area,
            ]),
            'barangays' => LianBarangays::all(),
            'action' => route('farmer.planting-schedules.store'),
            'method' => 'POST',
        ]);
    }

    public function store(Request $request, PlantingSchedulePredictionService $predictions): RedirectResponse
    {
        if (! $this->featureReady()) {
            return $this->featureUnavailable();
        }

        $data = $this->validated($request);
        $schedule = $request->user()->plantingSchedules()->create($this->normalized($data));
        $predictions->refresh($schedule);

        return redirect()->route('calendar.index')->with('success', 'Planting and harvest schedule saved.')
            ->with('schedule_warnings', $this->seasonWarnings($schedule));
    }

    public function edit(Request $request, int $plantingSchedule): View|RedirectResponse
    {
        if (! $this->featureReady()) {
            return $this->featureUnavailable();
        }

        $schedule = FarmerPlantingSchedule::query()->findOrFail($plantingSchedule);
        $this->authorizeOwner($request, $schedule);

        return view('planting-schedules.form', [
            'schedule' => $schedule,
            'barangays' => LianBarangays::all(),
            'action' => route('farmer.planting-schedules.update', $schedule),
            'method' => 'PUT',
        ]);
    }

    public function update(Request $request, int $plantingSchedule, PlantingSchedulePredictionService $predictions): RedirectResponse
    {
        if (! $this->featureReady()) {
            return $this->featureUnavailable();
        }

        $schedule = FarmerPlantingSchedule::query()->findOrFail($plantingSchedule);
        $this->authorizeOwner($request, $schedule);
        $schedule->update($this->normalized($this->validated($request)));
        $predictions->refresh($schedule);

        return redirect()->route('calendar.index')->with('success', 'Schedule updated.')
            ->with('schedule_warnings', $this->seasonWarnings($schedule));
    }

    public function destroy(Request $request, int $plantingSchedule): RedirectResponse
    {
        if (! $this->featureReady()) {
            return $this->featureUnavailable();
        }

        $schedule = FarmerPlantingSchedule::query()->findOrFail($plantingSchedule);
        $this->authorizeOwner($request, $schedule);
        $schedule->delete();

        return redirect()->route('calendar.index')->with('success', 'Schedule deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'planting_date' => ['required', 'date'],
            'scheduled_harvest_date' => ['nullable', 'date', 'required_without:harvest_month', 'after_or_equal:planting_date'],
            'harvest_month' => ['nullable', 'date_format:Y-m', 'required_without:scheduled_harvest_date'],
            'season' => ['required', Rule::in(FarmerPlantingSchedule::SEASONS)],
            'farm_type' => ['required', Rule::in(FarmerPlantingSchedule::FARM_TYPES)],
            'barangay' => ['nullable', Rule::in(LianBarangays::all())],
            'farm_area_hectares' => ['nullable', 'numeric', 'gt:0', 'max:10000'],
            'progress_status' => ['nullable', Rule::in(FarmerPlantingSchedule::MANUAL_STATUSES)],
            'actual_yield' => ['nullable', 'numeric', 'gt:0', 'max:15'],                          // tons per hectare; no rice field yields more
            'harvested_on' => ['nullable', 'date', 'after_or_equal:planting_date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function normalized(array $data): array
    {
        $data['harvest_month'] = ! empty($data['harvest_month']) ? $data['harvest_month'].'-01' : null;
        $data['scheduled_harvest_date'] = $data['scheduled_harvest_date'] ?? null;
        $data['barangay'] = $data['barangay'] ?? null;
        $data['progress_status'] = $data['progress_status'] ?? null;

        // A recorded harvest means the crop is harvested; the harvest columns are only written once they exist in the database.
        if (! \App\Support\SchemaCache::hasColumn('farmer_planting_schedules', 'actual_yield')) {
            unset($data['actual_yield'], $data['harvested_on']);
        } else {
            $data['actual_yield'] = $data['actual_yield'] ?? null;
            $data['harvested_on'] = $data['harvested_on'] ?? null;
            if ($data['actual_yield'] !== null || $data['harvested_on'] !== null) {
                $data['progress_status'] = 'Harvested';
            }
        }

        return $data;
    }

    private function seasonWarnings(FarmerPlantingSchedule $schedule): array
    {
        $warnings = [];
        $plantingMonth = $schedule->planting_date->month;
        $expectedPlantingMonths = $schedule->season === 'Wet' ? [5, 6, 7] : [10, 11, 12];
        if (! in_array($plantingMonth, $expectedPlantingMonths, true)) {
            $warnings[] = $schedule->season.' season planting is normally scheduled in '.($schedule->season === 'Wet' ? 'May to July' : 'October to December').'. Your record was still saved.';
        }

        $harvestMonth = $schedule->harvestDateForPlanning()?->month;
        if ($harvestMonth && ! in_array($harvestMonth, [3, 4, 10, 11], true)) {
            $warnings[] = 'Common harvest months are March, April, October, and November. Your record was still saved.';
        }

        return $warnings;
    }

    private function authorizeOwner(Request $request, FarmerPlantingSchedule $schedule): void
    {
        abort_unless($schedule->user_id === $request->user()->id, 403);
    }

    private function featureReady(): bool
    {
        return \App\Support\SchemaCache::hasTable('farmer_planting_schedules');
    }

    private function featureUnavailable(): RedirectResponse
    {
        return redirect()->route('calendar.index')->with('schedule_feature_warning', 'Farmer schedules are temporarily unavailable while the database update is completed.');
    }
}
