<?php

namespace App\Http\Controllers;

use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Services\Farmer\FarmerMissingInfoNotifier;
use App\Services\Farmer\YieldSurveyService;
use App\Support\ActiveFarm;
use App\Services\PlantingSchedulePredictionService;
use App\Support\BusinessClock;
use App\Support\LianBarangays;
use App\Support\RiceSeason;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * My Farm's Activity Calendar and the harvest question (2026-10, Farmer – Naicel). The farmer picks a date, Planted or
 * Harvested, and one of their farms. Both are kept in the existing planting records (farmer_planting_schedules):
 * "Planted" starts a record for that farm, "Harvested" sets the harvest date on that farm's open record.
 * Everything is the signed-in farmer's own; a farm or record of someone else answers 404.
 */
class FarmerActivityController extends Controller
{
    public const KIND_PLANTED = 'planted';

    public const KIND_HARVESTED = 'harvested';

    public function store(Request $request, PlantingSchedulePredictionService $predictions, FarmerMissingInfoNotifier $missing): RedirectResponse
    {
        $user = $request->user();
        $today = BusinessClock::today();
        $back = fn (array $errors) => redirect()->to(route('farmer.my-farm', array_filter(['month' => $this->monthOf($request->input('date'))])).'#activity')
            ->withErrors($errors, 'activity')->withInput()->with('fmf_activity_dialog', true);

        $validator = Validator::make($request->only(['date', 'kind', 'farm_id']), [
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today->copy()->subYears(3)->toDateString(), 'before_or_equal:'.$today->copy()->addYear()->toDateString()],
            'kind' => ['required', Rule::in([self::KIND_PLANTED, self::KIND_HARVESTED])],
            'farm_id' => ['required', 'integer'],
        ], [
            'date.*' => __('farmer.activity.errors.date'),
            'kind.*' => __('farmer.activity.errors.kind'),
            'farm_id.*' => __('farmer.activity.errors.farm'),
        ]);
        if ($validator->fails()) {
            return $back($validator->errors()->toArray());
        }
        $data = $validator->validated();

        $farm = ActiveFarm::owned($user, $data['farm_id']);
        if ($farm === null) {
            return $back(['farm_id' => __('farmer.activity.errors.farm')]);
        }
        $date = Carbon::parse($data['date']);
        $month = ['month' => $date->format('Y-m')];

        if ($data['kind'] === self::KIND_PLANTED) {
            $exists = FarmerPlantingSchedule::query()->where('user_id', $user->id)->where('farm_id', $farm->id)->whereDate('planting_date', $date->toDateString())->exists();
            if ($exists) {
                return $back(['date' => __('farmer.activity.errors.duplicate')]);
            }

            $schedule = $user->plantingSchedules()->create([
                'farm_id' => $farm->id,
                'planting_date' => $date->toDateString(),
                'scheduled_harvest_date' => $date->copy()->addDays((int) config('farmer_dashboard.crop.default_duration_days', 115))->toDateString(),
                'season' => RiceSeason::forDate($date),
                // A farm saved without a type takes the profile's, else the same starting value registration gives.
                'farm_type' => $farm->farm_type ?: ($user->farmerProfile?->farm_type ?: FarmerProfile::FARM_TYPE_IRRIGATED),
                'barangay' => in_array($farm->barangay, LianBarangays::all(), true) ? $farm->barangay : null,
                'farm_area_hectares' => $farm->knownHectares(),
            ]);
            // The record is saved either way; its yield estimate is filled from prepared data when that is ready.
            rescue(fn () => $predictions->refresh($schedule), null, true);
            $missing->sync($user);

            return redirect()->to(route('farmer.my-farm', $month).'#activity')->with('success', __('farmer.activity.saved_planted'));
        }

        if ($date->toDateString() > $today->toDateString()) {
            return $back(['date' => __('farmer.activity.errors.harvest_future')]);
        }

        // The farm's open planting: the latest one planted on or before that day and not harvested yet.
        $schedule = FarmerPlantingSchedule::query()
            ->where('user_id', $user->id)->where('farm_id', $farm->id)
            ->whereDate('planting_date', '<=', $date->toDateString())
            ->whereNull('harvested_on')
            ->where(fn ($q) => $q->whereNull('progress_status')->orWhere('progress_status', '!=', 'Harvested'))
            ->orderByDesc('planting_date')->first();
        if ($schedule === null) {
            return $back(['date' => __('farmer.activity.errors.no_planting')]);
        }

        $schedule->update(['harvested_on' => $date->toDateString(), 'progress_status' => 'Harvested']);
        $missing->sync($user);

        // The actual-harvest question opens next (My Farm shows it for the latest harvest without a yield).
        return redirect()->to(route('farmer.my-farm', $month).'#yield-survey')->with('success', __('farmer.activity.saved_harvested'));
    }

    /** "Ano ang tunay mong ani?": the actual yield (t/ha) of one harvested planting. Stored only; it never retrains a model. */
    public function yieldSurvey(Request $request, int $plantingSchedule, YieldSurveyService $survey, FarmerMissingInfoNotifier $missing): RedirectResponse
    {
        $user = $request->user();
        $schedule = FarmerPlantingSchedule::query()->where('user_id', $user->id)->findOrFail($plantingSchedule);

        $validator = Validator::make($request->only('actual_yield'), [
            'actual_yield' => ['required', 'numeric', 'gt:0', 'max:15', 'decimal:0,2'],                // tons per hectare; no rice field yields more
        ], ['actual_yield.*' => __('farmer.yield_survey.error')]);
        if ($validator->fails()) {
            return redirect()->to(route('farmer.my-farm').'#yield-survey')->withErrors($validator, 'yield_survey')->withInput();
        }

        $survey->record($schedule, (float) $validator->validated()['actual_yield']);
        $missing->sync($user);

        return redirect()->to(route('farmer.my-farm').'#harvests')->with('success', __('farmer.yield_survey.saved'));
    }

    private function monthOf(mixed $date): ?string
    {
        return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? substr($date, 0, 7) : null;
    }
}
