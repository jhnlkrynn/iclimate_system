<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\FarmerProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
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
        // Farm size and type belong to the farmer profile (saved below), not to the account.
        $request->user()->fill($request->safe()->except(['farm_area', 'farm_type']));

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        if ($request->user()->role === User::ROLE_FARMER && $request->hasAny(['barangay', 'farm_area', 'farm_type'])) {
            $profile = $request->user()->farmerProfile()->firstOrCreate([], [
                'full_name' => $request->user()->name,
                'contact_number' => $request->user()->contact_number,
                'farm_type' => FarmerProfile::FARM_TYPE_RAINFED,
            ]);
            $changes = [];
            if ($request->has('barangay')) {
                $changes['barangay'] = $request->validated('barangay') ?: null;
            }
            if ($request->filled('farm_area')) {
                $changes['farm_area'] = $request->validated('farm_area');
            }
            if ($request->filled('farm_type')) {
                $changes['farm_type'] = $request->validated('farm_type');
            }
            $profile->update($changes);
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
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
        if (UserManagementController::isLastActiveItExpert($user)) {
            return back()->withErrors(['password' => 'This is the last active IT Expert account, so it cannot be deleted. Create another IT Expert account first.'], 'userDeletion');
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
