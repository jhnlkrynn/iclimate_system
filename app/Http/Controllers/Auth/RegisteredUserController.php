<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\FarmerOrganization;
use App\Models\FarmerProfile;
use App\Models\User;
use App\Services\Security\CaptchaService;
use App\Support\LianFarmTypes;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, CaptchaService $captcha): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', new \App\Rules\RegistrationEmailAvailable],   // a closed account's email says so
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'farmer_organization_id' => ['nullable', 'integer', 'exists:farmer_organizations,id'],
            // Panel revision 1.1: any other organizations the farmer belongs to (the primary one is stripped below).
            'other_organizations' => ['nullable', 'array', 'max:20'],
            'other_organizations.*' => ['integer', 'distinct', 'exists:farmer_organizations,id'],
            'barangay' => ['required_without:farmer_organization_id', 'nullable', 'string', Rule::in($this->manualBarangays())],
            'captcha_answer' => ['required', 'string', 'max:20'],
        ], [
            'barangay.required_without' => 'Please choose your farm location when no farmer organization is selected.',
            'captcha_answer.required' => 'Please answer the security check before creating an account.',
            'captcha_answer.max' => 'The security check answer is too long.',
            'other_organizations.max' => 'Please choose 20 other organizations or fewer.',
            'other_organizations.*.integer' => 'Please choose your other organizations from the list.',
            'other_organizations.*.distinct' => 'An organization was chosen twice. Please choose each one only once.',
            'other_organizations.*.exists' => 'One of the chosen organizations could not be found. Please choose again.',
        ]);

        if (! $captcha->validate($request, 'register', $validated['captcha_answer'] ?? null)) {
            throw ValidationException::withMessages([
                'captcha_answer' => 'The security check answer is incorrect. Please try again.',
            ]);
        }

        $organization = isset($validated['farmer_organization_id'])
            ? FarmerOrganization::query()->findOrFail($validated['farmer_organization_id'])
            : null;

        $barangay = $organization?->barangay ?? $validated['barangay'];
        $farmType = $organization?->farm_type ?? FarmerProfile::FARM_TYPE_IRRIGATED;

        $user = DB::transaction(function () use ($validated, $organization, $barangay, $farmType): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => User::ROLE_FARMER,
                'password' => Hash::make($validated['password']),
                'barangay' => $barangay,
                // Manuscript – Naicel: Pending until the MAO approves the sign-up (config iclimate.farmer_approval).
                'status' => \App\Support\SystemSettings::farmerApproval() ? User::STATUS_PENDING : User::STATUS_ACTIVE,
            ]);

            $profile = FarmerProfile::create([
                'user_id' => $user->id,
                'farmer_organization_id' => $organization?->id,
                'full_name' => $validated['name'],
                'barangay' => $barangay,
                'farm_type' => $farmType,
            ]);
            // Panel revision 1.1: the main organization plus every other one chosen. The main one is never an "other".
            $others = array_values(array_diff(array_map('intval', (array) ($validated['other_organizations'] ?? [])), [(int) $organization?->id]));
            if ($others !== []) {
                $profile->syncOrganizations(array_merge($organization ? [$organization->id] : [], $others), $organization?->id);
            }

            return $user;
        });

        event(new Registered($user));
        // MAO – Naicel (2026-10-09): a sign-up that waits for approval goes to every MAO's notification bell.
        rescue(fn () => app(\App\Services\Mao\FarmerApprovalNotifier::class)->sync(), null, false);

        // Manuscript – Naicel: a sign-up that waits for approval is told so.
        return redirect('/')
            ->with('success', $user->status === User::STATUS_PENDING
                ? 'Account created. The Municipal Agriculture Office will review it; you can log in once it is approved.'
                : 'Account created successfully. Please log in using your email and password.');
    }

    /** The barangays with a registered farmer association (LianFarmTypes, which the organizations are seeded from). */
    private function manualBarangays(): array
    {
        return LianFarmTypes::records()->pluck('barangay')->unique()->sort()->values()->all();
    }
}
