<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\FarmerProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('profile.edit', [
            'user' => $user,
            // Farmers: what is still missing from their farm information (it used to be a checklist on the dashboard).
            'farmSetup' => $user->role === User::ROLE_FARMER ? \App\Support\FarmerSetupChecklist::for($user) : null,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        // farmer_profiles.barangay is required (NOT NULL). A farmer without a profile row gets one from the chosen
        // barangay or the account's; with neither, the form asks for it instead of failing (500, found 2026-11-20).
        $user = $request->user();
        $barangay = $request->validated('barangay') ?: $user->barangay;
        if ($user->role === User::ROLE_FARMER && $user->farmerProfile === null && blank($barangay)
            && ($request->hasAny(['barangay', 'farm_area', 'farm_type']) || $request->has('farmer_organization_ids_present'))) {
            return Redirect::route('profile.edit')->withInput()->withErrors(['barangay' => __('validation.required', ['attribute' => __('barangay')])]);
        }

        // Farm size and type belong to the farmer profile (saved below), not to the account.
        $request->user()->fill($request->safe()->except(['farm_area', 'farm_type', 'farmer_organization_ids']));

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        if ($request->user()->role === User::ROLE_FARMER && $request->hasAny(['barangay', 'farm_area', 'farm_type'])) {
            $profile = $request->user()->farmerProfile()->firstOrCreate([], [
                'full_name' => $request->user()->name,
                'contact_number' => $request->user()->contact_number,
                'barangay' => $barangay,
                'farm_type' => FarmerProfile::FARM_TYPE_RAINFED,
            ]);
            $changes = [];
            // A blank choice keeps the saved barangay: the column cannot be empty.
            if ($request->filled('barangay')) {
                $changes['barangay'] = $request->validated('barangay');
            }
            if ($request->filled('farm_area')) {
                $changes['farm_area'] = $request->validated('farm_area');
            }
            if ($request->filled('farm_type')) {
                $changes['farm_type'] = $request->validated('farm_type');
            }
            $profile->update($changes);
        }

        // Panel revision 1.1: the farmer's organizations (the form always sends the marker, so "none ticked" clears them).
        if ($request->user()->role === User::ROLE_FARMER && $request->has('farmer_organization_ids_present')) {
            $profile = $request->user()->farmerProfile()->firstOrCreate([], [
                'full_name' => $request->user()->name,
                'barangay' => $barangay,
                'farm_type' => FarmerProfile::FARM_TYPE_RAINFED,
            ]);
            $profile->syncOrganizations((array) ($request->validated('farmer_organization_ids') ?? []));
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Save a new profile photo (replacing the old file).
     */
    public function updatePhoto(Request $request): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
        ]);

        $user = $request->user();
        $old = $user->avatar_path;
        $user->avatar_path = $request->file('photo')->store('avatars', 'public');
        $user->save();

        if ($old) {
            Storage::disk('public')->delete($old);
        }

        return Redirect::route('profile.edit')->with('status', 'photo-updated');
    }

    /**
     * Remove the profile photo; the initials show again.
     */
    public function destroyPhoto(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->avatar_path = null;
            $user->save();
        }

        return Redirect::route('profile.edit')->with('status', 'photo-removed');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // The system must always keep a working administrator.
        if (UserManagementController::isLastActiveMaoHead($user)) {
            return back()->withErrors(['password' => 'This is the last active MAO Head account, so it cannot be deleted. Create another MAO Head account first.'], 'userDeletion');
        }

        if (\App\Models\FarmerAccountReview::query()->where('user_id', $user->id)->orWhere('reviewer_id', $user->id)->exists()) {
            return back()->withErrors(['password' => 'This account has registration review history. Contact the MAO Head to close it while preserving its records.'], 'userDeletion');
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
