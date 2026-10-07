<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Services\Farmer\FarmerMissingInfoNotifier;
use App\Services\Farmer\FarmService;
use App\Support\LianBarangays;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * My Farm (2026-10, Farmer – Naicel): the signed-in farmer adds, edits and archives their own farms. A farm is never
 * deleted (its planting and harvest records stay); another farmer's farm answers 404.
 */
class FarmerFarmController extends Controller
{
    /** More than any one farmer in Lian works; stops a runaway form. */
    public const MAX_ACTIVE_FARMS = 20;

    public function store(Request $request, FarmService $farms, FarmerMissingInfoNotifier $missing): RedirectResponse
    {
        $user = $request->user();
        if ($farms->farms($user)->count() >= self::MAX_ACTIVE_FARMS) {
            return $this->back('new')->withErrors(['name' => __('farmer.farms.limit', ['count' => self::MAX_ACTIVE_FARMS])], 'farm');
        }

        $validator = $this->validator($request);
        if ($validator->fails()) {
            return $this->back('new')->withErrors($validator, 'farm')->withInput();
        }

        $farms->create($user, $validator->validated());
        $missing->sync($user);

        return redirect()->to(route('farmer.my-farm').'#farms')->with('success', __('farmer.farms.saved'));
    }

    public function update(Request $request, int $farm, FarmService $farms, FarmerMissingInfoNotifier $missing): RedirectResponse
    {
        $user = $request->user();
        $record = $this->own($request, $farm);

        $validator = $this->validator($request);
        if ($validator->fails()) {
            return $this->back((string) $record->id)->withErrors($validator, 'farm')->withInput();
        }

        $farms->update($user, $record, $validator->validated());
        $missing->sync($user);

        return redirect()->to(route('farmer.my-farm').'#farms')->with('success', __('farmer.farms.saved'));
    }

    public function archive(Request $request, int $farm, FarmService $farms, FarmerMissingInfoNotifier $missing): RedirectResponse
    {
        $user = $request->user();
        $farms->archive($user, $this->own($request, $farm));
        $missing->sync($user);

        return redirect()->to(route('farmer.my-farm').'#farms')->with('success', __('farmer.farms.archived'));
    }

    private function own(Request $request, int $farm): Farm
    {
        return Farm::query()->where('user_id', $request->user()->id)->active()->findOrFail($farm);
    }

    /** Back to My Farm with the pop-up open again on the farm that was being saved ("new" or its id). */
    private function back(string $dialog): RedirectResponse
    {
        return redirect()->to(route('farmer.my-farm').'#farms')->with('fmf_farm_dialog', $dialog);
    }

    private function validator(Request $request): \Illuminate\Validation\Validator
    {
        return Validator::make($request->only(['name', 'barangay', 'area_hectares', 'farm_type']), [
            'name' => ['required', 'string', 'max:100'],
            'barangay' => ['required', 'string', Rule::in(LianBarangays::all())],
            'area_hectares' => ['required', 'numeric', 'gt:0', 'max:10000', 'decimal:0,2'],
            'farm_type' => ['required', 'string', Rule::in(Farm::FARM_TYPES)],
        ], [
            'name.*' => __('farmer.farms.errors.name'),
            'barangay.*' => __('farmer.farms.errors.barangay'),
            'area_hectares.*' => __('farmer.farms.errors.area'),
            'farm_type.*' => __('farmer.farms.errors.type'),
        ]);
    }
}
