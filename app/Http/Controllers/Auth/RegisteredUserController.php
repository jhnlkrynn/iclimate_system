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
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request, CaptchaService $captcha): View
    {
        return view('auth.register', [
            'captcha' => $captcha->challenge($request, 'register'),
            'farmerOrganizations' => FarmerOrganization::query()->orderBy('id')->get(),
            'manualBarangays' => $this->manualBarangays(),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, CaptchaService $captcha): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'farmer_organization_id' => ['nullable', 'integer', 'exists:farmer_organizations,id'],
            'barangay' => ['required_without:farmer_organization_id', 'nullable', 'string', Rule::in($this->manualBarangays())],
            'captcha_answer' => ['required', 'string', 'max:20'],
        ], [
            'barangay.required_without' => 'Please choose your farm location when no farmer organization is selected.',
            'captcha_answer.required' => 'Please answer the security check before creating an account.',
            'captcha_answer.max' => 'The security check answer is too long.',
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
                'status' => User::STATUS_ACTIVE,
            ]);

            FarmerProfile::create([
                'user_id' => $user->id,
                'farmer_organization_id' => $organization?->id,
                'full_name' => $validated['name'],
                'barangay' => $barangay,
                'farm_type' => $farmType,
            ]);

            return $user;
        });

        event(new Registered($user));

        return redirect('/')
            ->with('success', 'Account created successfully. Please log in using your email and password.');
    }

    /** The barangays with a registered farmer association (LianFarmTypes, which the organizations are seeded from). */
    private function manualBarangays(): array
    {
        return LianFarmTypes::records()->pluck('barangay')->unique()->sort()->values()->all();
    }
}
