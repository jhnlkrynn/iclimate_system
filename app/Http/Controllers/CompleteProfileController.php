<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\FarmerOrganization;
use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Services\Farmer\FarmerMissingInfoNotifier;
use App\Services\Farms\FarmAssociations;
use App\Services\Farms\FarmService;
use App\Support\ActiveFarm;
use App\Support\LianBarangays;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CompleteProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $profile = $user->farmerProfile;
        $primaryFarm = ActiveFarm::available()
            ? $user->farms()->active()->orderByDesc('is_primary')->orderBy('id')->first()
            : null;

        return view('farmer.complete-profile', [
            'user' => $user,
            'profile' => $profile,
            'primaryFarm' => $primaryFarm,
            'barangays' => LianBarangays::all(),
            'organizations' => FarmAssociations::organizations(),
            'farmTypes' => FarmerPlantingSchedule::FARM_TYPES,
        ]);
    }

    public function store(Request $request, FarmService $farmService): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'barangay' => ['required', 'string', Rule::in(LianBarangays::all())],
            'association_choice' => ['required', 'string'],
            'other_organizations' => ['nullable', 'array', 'max:20'],
            'other_organizations.*' => ['integer', 'distinct', 'exists:farmer_organizations,id'],
            'contact_number' => ['required', 'string', 'max:50', 'regex:/^\+?[0-9][0-9\s\-()]{6,19}$/'],
            'farm_name' => ['required', 'string', 'max:120'],
            'farm_type' => ['required', 'string', Rule::in(FarmerPlantingSchedule::FARM_TYPES)],
            'farm_size_hectares' => ['required', 'numeric', 'gt:0', 'max:'.config('farms.max_hectares', 10000), 'decimal:0,2'],
        ], [
            'contact_number.regex' => __('farmer.popup.profile.contact_invalid'),
            'farm_size_hectares.decimal' => __('farmer.popup.size.decimals'),
        ]);

        $choice = $validated['association_choice'];
        $organization = null;
        $associationStatus = 'not_member';
        if ($choice !== 'not_member') {
            $organization = FarmerOrganization::query()
                ->whereKey((int) $choice)
                ->where('barangay', $validated['barangay'])
                ->first();
            if (! $organization) {
                throw ValidationException::withMessages([
                    'association_choice' => __('farmer.complete_profile.association_invalid'),
                ]);
            }
            $associationStatus = 'member';
        }

        DB::transaction(function () use ($user, $validated, $organization, $associationStatus, $farmService): void {
            $user->forceFill([
                'barangay' => $validated['barangay'],
                'contact_number' => $validated['contact_number'],
            ])->save();

            $profile = FarmerProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name' => $user->name,
                    'barangay' => $validated['barangay'],
                    'contact_number' => $validated['contact_number'],
                    'farmer_organization_id' => $organization?->id,
                    'farm_type' => $validated['farm_type'],
                    'farm_area' => $validated['farm_size_hectares'],
                ]
            );

            $others = array_values(array_diff(
                array_map('intval', (array) ($validated['other_organizations'] ?? [])),
                [(int) $organization?->id]
            ));
            if ($organization !== null || $others !== []) {
                $profile->syncOrganizations(
                    array_values(array_unique(array_merge($organization ? [$organization->id] : [], $others))),
                    $organization?->id
                );
            }

            if (ActiveFarm::available()) {
                $farmPayload = [
                    'farm_name' => $validated['farm_name'],
                    'barangay' => $validated['barangay'],
                    'farm_type' => $validated['farm_type'],
                    'farm_size_method' => Farm::SIZE_EXACT,
                    'farm_size_value' => $validated['farm_size_hectares'],
                    'farm_size_unit' => Farm::UNIT_HECTARES,
                    'association_status' => $associationStatus,
                    'farmer_organization_id' => $organization?->id,
                ];

                $existingFarm = $user->farms()->active()->orderByDesc('is_primary')->orderBy('id')->first();
                if ($existingFarm) {
                    $farmService->update($existingFarm, $farmPayload);
                } else {
                    $farmService->create($user, $farmPayload);
                }
            }
        });

        $request->session()->forget('complete_profile_banner_dismissed');
        rescue(fn () => app(FarmerMissingInfoNotifier::class)->sync($user->fresh()), null, false);

        return redirect()->route('farmer.dashboard')->with('success', __('farmer.complete_profile.saved'));
    }

    public function dismiss(Request $request): RedirectResponse|JsonResponse
    {
        $request->session()->put('complete_profile_banner_dismissed', true);

        if ($request->expectsJson()) {
            return response()->json(['dismissed' => true]);
        }

        return back();
    }
}
