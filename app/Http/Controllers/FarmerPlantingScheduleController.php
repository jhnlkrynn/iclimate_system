<?php

namespace App\Http\Controllers;

use App\Models\FarmerPlantingSchedule;
use App\Services\PlantingSchedulePredictionService;
use App\Support\ActiveFarm;
use App\Support\LianBarangays;
use App\Models\FarmerProfile;
use App\Models\User;
use App\Support\BusinessClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;
use Illuminate\View\View;

class FarmerPlantingScheduleController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (! $this->featureReady()) {
            return $this->featureUnavailable();
        }

        $user = $request->user();
        $profile = $user->farmerProfile;
        // The plan is for the farm the farmer asked for (?farm=, only their own), else the farm they are viewing.
        $farm = ActiveFarm::owned($user, $request->query('farm')) ?? ActiveFarm::current($user);

        return view('planting-schedules.form', [
            'schedule' => new FarmerPlantingSchedule($farm !== null ? [
                'farm_id' => $farm->getKey(),
                'barangay' => $farm->barangay,
                'farm_type' => $farm->farm_type,
                'farm_area_hectares' => $farm->knownHectares(),
            ] : [
                'barangay' => $profile?->barangay ?: $user->barangay,
                'farm_type' => $profile?->farm_type,
                'farm_area_hectares' => $profile?->farm_area,
            ]),
            'farms' => ActiveFarm::farms($user),
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
        $schedule = $request->user()->plantingSchedules()->create($this->normalized($data) + $this->farmColumn($request));
        $schedule = $request->user()->plantingSchedules()->create($this->normalized($data, $request));
        $predictions->refresh($schedule);
        $this->afterSave($request, $schedule);

        return redirect()->route('farmer.my-farm')->with('success', 'Planting and harvest schedule saved.')
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
            'farms' => ActiveFarm::farms($request->user()),
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
        $schedule->update($this->normalized($this->validated($request)) + $this->farmColumn($request, $schedule));
        $schedule->update($this->normalized($this->validated($request), $request));
        $predictions->refresh($schedule);
        $this->afterSave($request, $schedule);

        return redirect()->route('farmer.my-farm')->with('success', 'Schedule updated.')
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
        app(\App\Services\Farmer\FarmerMissingInfoNotifier::class)->sync($request->user());

        return redirect()->route('farmer.my-farm')->with('success', 'Schedule deleted.');
    }

    // ------------------------------------------------------------------ Farmer – Naicel (2026-10)

    /** The farmer's own active farms for the form's "Farm" choice. */
    public static function farmOptions(Request $request): \Illuminate\Support\Collection
    {
        return app(\App\Services\Farmer\FarmService::class)->farms($request->user());
    }

    /** farm_id for a saved plan: the farm chosen on the form when it is the farmer's own, else the plan's, else the primary farm. */
    private function farmColumn(Request $request, ?FarmerPlantingSchedule $schedule = null): array
    {
        if (! \App\Support\SchemaCache::hasColumn('farmer_planting_schedules', 'farm_id')) {
            return [];
        }
        $farms = self::farmOptions($request);
        $chosen = is_numeric($request->input('farm_id')) ? $farms->firstWhere('id', (int) $request->input('farm_id')) : null;

        return ['farm_id' => $chosen?->id ?? $schedule?->farm_id ?? $farms->first()?->id];
    }

    /** A harvest typed on the form is kept with the yield predicted for it (stored only); the bell's reminders follow. */
    private function afterSave(Request $request, FarmerPlantingSchedule $schedule): void
    {
        rescue(function () use ($request, $schedule): void {
            $schedule->refresh();
            if (is_numeric($schedule->actual_yield) && (float) $schedule->actual_yield > 0) {
                app(\App\Services\Farmer\YieldSurveyService::class)->record($schedule, (float) $schedule->actual_yield);
            }
            app(\App\Services\Farmer\FarmerMissingInfoNotifier::class)->sync($request->user());
        }, null, true);
    }

    // ------------------------------------------------------------------ end Farmer – Naicel

    /**
     * The farmer dashboard's "Add Planting Information" pop-up: a planting date and a farm size, nothing else.
     *
     * Farm size is kept on the farmer's profile (farm_area, the figure the dashboard reads) and copied onto the plan.
     * The planting date goes on the farmer's current plan (the earliest one not yet harvested); with no plan a new one
     * is started, its harvest date set from the dashboard's own typical season length so the crop calendar and the
     * harvest estimate have a date to work with. The farmer is always the signed-in user.
     */
    public function applyFromDashboard(Request $request, PlantingSchedulePredictionService $predictions): JsonResponse
    {
        $failed = response()->json(['message' => __('farmer.planting_info.failed')], 500);
        if (! $this->featureReady()) {
            return $failed;
        }

        $today = BusinessClock::today();
        $data = $request->validate([
            'planting_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today->copy()->subYear()->toDateString(), 'before_or_equal:'.$today->copy()->addYear()->toDateString()],
            'farm_area' => ['required', 'numeric', 'gt:0', 'max:10000', 'decimal:0,2'],
        ], [
            'planting_date.required' => __('farmer.planting_info.date_required'),
            'planting_date.*' => __('farmer.planting_info.date_invalid'),
            'farm_area.decimal' => __('farmer.planting_info.area_decimals'),
            'farm_area.*' => __('farmer.planting_info.area_invalid'),
        ]);

        $user = $request->user();
        // The farm the farmer is viewing: its size and its plan (no farms yet: the profile, as before).
        $farm = ActiveFarm::current($user);
        $schedule = self::currentSchedule($user, $farm);
        $harvest = $schedule?->harvestDateForPlanning();
        if ($harvest && $harvest->toDateString() < $data['planting_date']) {
            throw ValidationException::withMessages([
                'planting_date' => __('farmer.planting_info.date_after_harvest', ['date' => $harvest->format('M j, Y')]),
            ]);
        }

        try {
            $schedule = DB::transaction(function () use ($user, $schedule, $data, $farm) {
                $profile = $user->farmerProfile;
                if ($farm !== null) {
                    app(\App\Services\Farms\FarmService::class)->update($farm, ['farm_size_method' => \App\Models\Farm::SIZE_EXACT, 'farm_size_value' => $data['farm_area'], 'farm_size_unit' => \App\Models\Farm::UNIT_HECTARES]);
                } elseif ($profile) {
                    $profile->update(['farm_area' => $data['farm_area']]);
                } elseif ($user->barangay) {
                    $profile = FarmerProfile::query()->create([
                        'user_id' => $user->id,
                        'full_name' => $user->name,
                        'contact_number' => $user->contact_number,
                        'address' => $user->address,
                        'barangay' => $user->barangay,
                        'farm_area' => $data['farm_area'],
                        'farm_type' => FarmerProfile::FARM_TYPE_IRRIGATED,      // the same starting value registration gives a new farmer
                    ]);
                }

                if ($schedule) {
                    $schedule->update(['planting_date' => $data['planting_date'], 'farm_area_hectares' => $data['farm_area']]);

                    return $schedule;
                }

                $planting = Carbon::parse($data['planting_date']);
                $barangay = $farm?->barangay ?: ($profile?->barangay ?: $user->barangay);

                return $user->plantingSchedules()->create($this->farmColumn(request()) + [
                return $user->plantingSchedules()->create([
                    ...($farm !== null ? ['farm_id' => $farm->getKey()] : []),
                    'planting_date' => $data['planting_date'],
                    'scheduled_harvest_date' => $planting->copy()->addDays((int) config('farmer_dashboard.crop.default_duration_days', 115))->toDateString(),
                    // May to October plantings are the wet season crop, the rest the dry season crop (App\Support\RiceSeason).
                    'season' => \App\Models\ClimateRecord::seasonForMonth($planting->month),
                    'farm_type' => $farm?->farm_type ?: ($profile?->farm_type ?: FarmerProfile::FARM_TYPE_IRRIGATED),
                    'barangay' => in_array($barangay, LianBarangays::all(), true) ? $barangay : null,
                    'farm_area_hectares' => $data['farm_area'],
                ]);
            });
        } catch (Throwable $e) {
            report($e);

            return $failed;
        }

        try {
            $predictions->refresh($schedule);
        } catch (Throwable $e) {
            report($e);                                                 // the plan is saved; its yield estimate is rebuilt the next time it is saved
        }

        return response()->json([
            'message' => __('farmer.planting_info.saved'),
            'planting_date' => $schedule->planting_date->toDateString(),
            'farm_area' => number_format((float) $data['farm_area'], 2, '.', ''),
        ]);
    }

    /**
     * The plan the dashboard's Crop Season card shows first: the earliest planting not harvested yet, of the given farm
     * (older plans without a farm count as the primary farm's).
     */
    public static function currentSchedule(User $user, ?\App\Models\Farm $farm = null): ?FarmerPlantingSchedule
    {
        if (! \App\Support\SchemaCache::hasTable('farmer_planting_schedules')) {
            return null;
        }

        return FarmerPlantingSchedule::query()->where('user_id', $user->id)->whereNotNull('planting_date')
            ->when($farm !== null, fn ($q) => $q->where(fn ($w) => $w->where('farm_id', $farm->getKey())->when($farm->is_primary, fn ($p) => $p->orWhereNull('farm_id'))))
            ->orderBy('planting_date')->limit(100)->get()
            ->first(fn (FarmerPlantingSchedule $row) => $row->monitoringStatus() !== 'harvested');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'farm_id' => ['nullable', 'integer'],
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

    private function normalized(array $data, Request $request): array
    {
        // Which farm: one of the farmer's own farms in use (a farm id that is not theirs is refused), else the farm
        // they are viewing. Its barangay fills an empty barangay.
        if (ActiveFarm::available() && \App\Support\SchemaCache::hasColumn('farmer_planting_schedules', 'farm_id')) {
            $farm = isset($data['farm_id']) ? ActiveFarm::owned($request->user(), $data['farm_id']) : ActiveFarm::current($request->user());
            if (isset($data['farm_id']) && $farm === null) {
                throw ValidationException::withMessages(['farm_id' => __('farms.errors.not_found')]);
            }
            $data['farm_id'] = $farm?->getKey();
            if (empty($data['barangay']) && $farm?->barangay) {
                $data['barangay'] = $farm->barangay;
            }
        } else {
            unset($data['farm_id']);
        }

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
        // The first three months of each season (App\Support\RiceSeason: wet from May, dry from November).
        $expectedPlantingMonths = $schedule->season === 'Wet' ? [5, 6, 7] : [11, 12, 1];
        if (! in_array($plantingMonth, $expectedPlantingMonths, true)) {
            $warnings[] = $schedule->season.' season planting is normally scheduled in '.($schedule->season === 'Wet' ? 'May to July' : 'November to January').'. Your record was still saved.';
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
