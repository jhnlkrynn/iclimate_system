<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\FarmerProfile;
use App\Models\User;
use App\Services\Security\CaptchaService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
        if (! $request->filled('name') && ($request->filled('first_name') || $request->filled('last_name'))) {
            $request->merge([
                'name' => trim((string) $request->input('first_name').' '.(string) $request->input('last_name')),
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', new \App\Rules\RegistrationEmailAvailable],   // a closed account's email says so
            'password' => ['required', 'confirmed', Rules\Password::min(8), new \App\Rules\NotKnownDefaultPassword],
            'captcha_answer' => ['required', 'string', 'max:20'],
            'privacy_agreed' => ['required', 'accepted'],
        ], [
            'captcha_answer.required' => 'Please answer the security check before creating an account.',
            'captcha_answer.max' => 'The security check answer is too long.',
            'privacy_agreed.required' => __('privacy.agree_required'),
            'privacy_agreed.accepted' => __('privacy.agree_required'),
        ]);

        if (! $captcha->validate($request, 'register', $validated['captcha_answer'] ?? null)) {
            throw ValidationException::withMessages([
                'captcha_answer' => 'The security check answer is incorrect. Please try again.',
            ]);
        }

        $user = DB::transaction(function () use ($validated): User {
            $attributes = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => User::ROLE_FARMER,
                'password' => Hash::make($validated['password']),
                'barangay' => null,
                // Manuscript – Naicel: Pending until the MAO approves the sign-up (config iclimate.farmer_approval).
                'status' => User::STATUS_PENDING,
            ];
            if (\App\Support\SchemaCache::hasColumn('users', 'privacy_agreed_at')) {
                $attributes['privacy_agreed_at'] = now();
            }
            $user = User::create($attributes);

            FarmerProfile::create([
                'user_id' => $user->id,
                'farmer_organization_id' => null,
                'full_name' => $validated['name'],
                'barangay' => null,
                'farm_type' => null,
            ]);

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
}
