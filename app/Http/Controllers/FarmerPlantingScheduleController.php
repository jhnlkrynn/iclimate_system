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

        $schedule = new FarmerPlantingSchedule($farm !== null ? [
            'farm_id' => $farm->getKey(),
            'barangay' => $farm->barangay,
            'farm_type' => $farm->farm_type,
            'farm_area_hectares' => $farm->knownHectares(),
        ] : [
            'barangay' => $profile?->barangay ?: $user->barangay,
            'farm_type' => $profile?->farm_type,
            'farm_area_hectares' => $profile?->farm_area,
        ]);
        $this->prefillVariety($request, $schedule);

        return view('planting-schedules.form', [
            'schedule' => $schedule,
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
        $schedule = $request->user()->plantingSchedules()->create($this->normalized($data, $request, null));
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
        $this->prefillVariety($request, $schedule);

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
        $schedule->update($this->normalized($this->validated($request, $schedule), $request, $schedule));
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
        app(\App\Services\Farmer\FarmerMissingInfoNotifier::class)->sync($request->user());      // Farmer – Naicel

        return redirect()->route('farmer.my-farm')->with('success', 'Schedule deleted.');
    }

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
            'farm_area' => ['required', 'numeric', 'gt:0', 'max:'.config('farms.max_hectares', 10000), 'decimal:0,2'],
            ...$this->varietyRules(),
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

                // A variety chosen here gives the plan its variety-based harvest date (FarmerPlantingSchedule::applyVarietyHarvestDate).
                $variety = $this->varietyAttributes($data);
                if ($schedule) {
                    $schedule->update(['planting_date' => $data['planting_date'], 'farm_area_hectares' => $data['farm_area']] + $variety);

                    return $schedule;
                }

                $planting = Carbon::parse($data['planting_date']);
                $barangay = $farm?->barangay ?: ($profile?->barangay ?: $user->barangay);

                return $user->plantingSchedules()->create($variety + [
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

    private function validated(Request $request, ?FarmerPlantingSchedule $existing = null): array
    {
        // With a variety chosen the harvest date comes from the variety, so neither date field is needed.
        $varieties = $this->varietiesReady();
        $harvestNeeded = $varieties ? 'required_without_all:harvest_month,rice_variety_id' : 'required_without:harvest_month';
        $monthNeeded = $varieties ? 'required_without_all:scheduled_harvest_date,rice_variety_id' : 'required_without:scheduled_harvest_date';

        return $request->validate([
            'farm_id' => ['nullable', 'integer'],
            'planting_date' => ['required', 'date'],
            'scheduled_harvest_date' => ['nullable', 'date', $harvestNeeded, 'after_or_equal:planting_date'],
            'harvest_month' => ['nullable', 'date_format:Y-m', $monthNeeded],
            'season' => ['required', Rule::in(FarmerPlantingSchedule::SEASONS)],
            'farm_type' => ['required', Rule::in(FarmerPlantingSchedule::FARM_TYPES)],
            'barangay' => ['nullable', Rule::in(LianBarangays::all())],
            'farm_area_hectares' => ['nullable', 'numeric', 'gt:0', 'max:'.config('farms.max_hectares', 10000)],
            'progress_status' => ['nullable', Rule::in(FarmerPlantingSchedule::MANUAL_STATUSES)],
            'actual_yield' => ['nullable', 'numeric', 'gt:0', 'max:15'],                          // tons per hectare; no rice field yields more
            'harvested_on' => ['nullable', 'date', 'after_or_equal:planting_date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            ...$this->varietyRules($existing),
        ] + \App\Support\HarvestTiming::rules());   // panel revision 1.3
    }

    private function normalized(array $data, Request $request, ?FarmerPlantingSchedule $existing): array
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

        $data = $this->withVarietyHarvest($data, $request, $existing);

        // Panel revision 1.3: On time / Early / Late against the expected harvest date; early or late needs a reason.
        if (\App\Support\SchemaCache::hasColumn('farmer_planting_schedules', 'harvest_timing')) {
            $expected = $data['scheduled_harvest_date'] ?? ($data['harvest_month'] ? \Illuminate\Support\Carbon::parse($data['harvest_month'])->endOfMonth() : null);
            $data = array_merge($data, ! empty($data['harvested_on'])
                ? \App\Support\HarvestTiming::attributes($expected, $data['harvested_on'], $data)
                : ['harvest_timing' => null, 'harvest_timing_reason' => null, 'harvest_timing_reason_other' => null]);
        } else {
            unset($data['harvest_timing_reason'], $data['harvest_timing_reason_other']);
        }

        return $data;
    }

    // ------------------------------------------------------------------ Rice varieties

    private function varietiesReady(): bool
    {
        return \App\Support\SchemaCache::hasColumn('farmer_planting_schedules', 'rice_variety_id')
            && \App\Support\SchemaCache::hasTable('rice_varieties');
    }

    /**
     * An active variety, or the inactive one this plan already has (an inactive variety stays on the plans using it).
     *
     * @return array<string, array<int, mixed>>
     */
    private function varietyRules(?FarmerPlantingSchedule $existing = null): array
    {
        if (! $this->varietiesReady()) {
            return [];
        }
        $keep = $existing?->rice_variety_id;

        return [
            'rice_variety_id' => ['nullable', 'integer', Rule::exists('rice_varieties', 'id')->where(fn ($q) => $keep
                ? $q->where(fn ($w) => $w->where('is_active', true)->orWhere('id', $keep))
                : $q->where('is_active', true))],
            'crop_establishment' => ['nullable', Rule::in(\App\Support\VarietyHarvestEstimator::ESTABLISHMENTS)],
            'date_basis' => ['nullable', Rule::in(\App\Support\VarietyHarvestEstimator::DATE_BASES)],
            'seedling_age_days' => ['nullable', 'integer', 'min:5', 'max:60'],
            'harvest_date_mode' => ['nullable', Rule::in(['auto', 'manual'])],
        ];
    }

    /** The variety fields of a request, ready to save (none when the columns are not there yet). */
    private function varietyAttributes(array $data): array
    {
        if (! $this->varietiesReady() || ! array_key_exists('rice_variety_id', $data)) {
            return [];
        }
        $basis = $data['date_basis'] ?? 'sowing';

        return [
            'rice_variety_id' => $data['rice_variety_id'] ?: null,
            'crop_establishment' => $data['crop_establishment'] ?? null,
            'date_basis' => $basis,
            'seedling_age_days' => $basis === 'transplanting'
                ? (int) ($data['seedling_age_days'] ?? config('rice_varieties.default_seedling_age_days', 21))
                : (int) config('rice_varieties.default_seedling_age_days', 21),
        ];
    }

    /**
     * The planting form with a variety: the expected harvest date is the variety's estimate, unless the farmer typed
     * their own (a date that differs from the estimate and from the one already saved, or one the form marks as
     * typed by hand). A recorded harvest keeps its dates as they are.
     */
    private function withVarietyHarvest(array $data, Request $request, ?FarmerPlantingSchedule $existing): array
    {
        $mode = $data['harvest_date_mode'] ?? null;
        unset($data['harvest_date_mode']);
        if (! $this->varietiesReady()) {
            return array_diff_key($data, array_flip(['rice_variety_id', 'crop_establishment', 'date_basis', 'seedling_age_days']));
        }

        $data = array_merge($data, $this->varietyAttributes($data + ['rice_variety_id' => null]));
        $harvested = ! empty($data['harvested_on']) || ($data['progress_status'] ?? null) === 'Harvested';
        if ($data['rice_variety_id'] === null || $harvested) {
            $data['harvest_date_manual'] = $data['rice_variety_id'] !== null && ($existing?->harvest_date_manual ?? false);

            return $data;
        }

        $estimate = app(\App\Support\VarietyHarvestEstimator::class)->estimate(
            \App\Models\RiceVariety::query()->find($data['rice_variety_id']),
            $data['planting_date'], $data['season'] ?? null, $data['crop_establishment'], $data['date_basis'], $data['seedling_age_days'],
        );
        $estimated = $estimate['expected_harvest_date']->toDateString();
        $typed = ! empty($data['scheduled_harvest_date']) ? Carbon::parse($data['scheduled_harvest_date'])->toDateString() : null;
        $saved = $existing?->scheduled_harvest_date?->toDateString();
        $manual = $typed !== null && $typed !== $estimated && ($mode === 'manual' || $typed !== $saved);

        $data['scheduled_harvest_date'] = $manual ? $typed : $estimated;
        $data['harvest_date_manual'] = $manual;

        return $data;
    }

    /** ?variety= (from a variety's "I planted this variety"): an active variety fills the form's variety. */
    private function prefillVariety(Request $request, FarmerPlantingSchedule $schedule): void
    {
        if ($this->varietiesReady() && $request->filled('variety')) {
            $variety = \App\Models\RiceVariety::query()->active()->find((int) $request->query('variety'));
            if ($variety !== null) {
                $schedule->rice_variety_id = $variety->id;
                $schedule->setRelation('riceVariety', $variety);
            }
        }
    }

    // ------------------------------------------------------------------ end rice varieties

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

    // ------------------------------------------------------------------ Farmer – Naicel (2026-10)

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
}
