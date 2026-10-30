<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Services\Farms\FarmAssociations;
use App\Services\SystemAuditLogger;
use App\Support\ActiveFarm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The farmer dashboard's association card (dashboards/farmer/association-prompt): "Is <farm> part of a farmers'
 * association?" for one farm without an answer at a time, with the same three choices as Add Farm (associations of that
 * farm's barangay only). Saving goes through FarmAssociations like the farm form, memberships and the MAO's
 * Association Requests ("isn't listed") included. "Later" hides the card for FarmAssociations::PROMPT_SNOOZE_DAYS days.
 */
class FarmAssociationPromptController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless(FarmAssociations::available(), 404);
        $farm = Farm::query()->ownedBy($request->user())->active()->findOrFail((int) $request->input('farm_id'));

        $data = $request->validate([
            'association_status' => ['required', Rule::in(Farm::ASSOCIATION_ANSWERS)],
            'farmer_organization_id' => ['nullable', 'required_if:association_status,'.Farm::ASSOCIATION_MEMBER, 'integer'],
            'association_other_name' => ['nullable', 'required_if:association_status,'.Farm::ASSOCIATION_PENDING, 'string', 'max:120'],
        ], [
            'association_status.*' => __('farms.errors.association'),
            'farmer_organization_id.*' => __('farms.errors.association_pick'),
            'association_other_name.*' => __('farms.errors.association_name'),
        ]);
        if ($data['association_status'] === Farm::ASSOCIATION_MEMBER && ! FarmAssociations::inBarangay((int) $data['farmer_organization_id'], $farm->barangay)) {
            throw ValidationException::withMessages(['farmer_organization_id' => __('farms.errors.association_pick')]);
        }

        FarmAssociations::fill($farm, $data);
        $farm->save();
        ActiveFarm::forget($request->user());
        SystemAuditLogger::record('Updated Farm', $request, [
            'record_type' => 'Farm', 'record_id' => $farm->getKey(), 'farm_barangay' => $farm->barangay,
            'association_status' => $farm->association_status, 'farmer_organization_id' => $farm->farmer_organization_id, 'via' => 'dashboard',
        ]);

        return redirect()->route('farmer.dashboard')->with('success', __('farms.association.prompt.saved', ['name' => $farm->farm_name]));
    }

    public function later(Request $request): RedirectResponse
    {
        FarmAssociations::snoozePrompt($request->user());

        return redirect()->route('farmer.dashboard')->with('success', __('farms.association.prompt.later_done', ['days' => FarmAssociations::PROMPT_SNOOZE_DAYS]));
    }
}
