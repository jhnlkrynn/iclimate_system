<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FarmerProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MobileAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'role' => ['nullable', 'string', 'in:farmer,mao'],
        ]);

        $user = User::query()
            ->where('email', $credentials['email'])
            ->first();

        // A deleted account (hidden from User::query()) with its own password: no sign-in, no reason, only that the
        // account is no longer active. The web login page shows the reason; the app does not.
        if ($user === null && ($deleted = User::withDeleted()->where('email', $credentials['email'])->where('status', User::STATUS_DELETED)->first())
            && rescue(fn () => Hash::check($credentials['password'], $deleted->password), false, false)) {
            throw ValidationException::withMessages(['email' => [trans('user_deletion.no_longer_active', [], $deleted->mailLocale())]]);
        }

        try {
            $passwordMatches = $user && Hash::check($credentials['password'], $user->password);
        } catch (RuntimeException) {
            $passwordMatches = false;
        }

        if (! $passwordMatches) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if (($credentials['role'] ?? null) && $user->role !== $credentials['role']) {
            throw ValidationException::withMessages([
                'role' => ['The selected role does not match this account.'],
            ]);
        }

        if (! $user->hasApplicationAccess()) {
            throw ValidationException::withMessages([
                'email' => [User::signInRefusal($user->status)],   // Manuscript – Naicel
            ]);
        }

        // A shared default password, or an account told to change its password, gets no profile until a new
        // password is chosen (POST /api/mobile/password, or on the website). 409 tells the app what to show.
        \App\Services\Accounts\PasswordChange::flagIfDefault($user, $credentials['password']);
        if (\App\Services\Accounts\PasswordChange::lockdownFor($request) && \App\Services\Accounts\PasswordChange::isShared($user, $credentials['password'])) {
            return $this->recoveryRequired($user, sendLink: true);
        }
        if (\App\Services\Accounts\PasswordChange::lockdown() && $user->fresh()->must_change_password) {
            return $this->passwordChangeRequired();
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();   // Manuscript – Naicel

        return response()->json([
            'message' => 'Login successful.',
            'user' => $this->mobileUser($user),
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', new \App\Rules\RegistrationEmailAvailable],   // a closed account's email says so
            'password' => ['required', 'confirmed', Rules\Password::defaults(), new \App\Rules\NotKnownDefaultPassword],
            'contact_number' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'barangay' => ['required', 'string', 'max:255', \Illuminate\Validation\Rule::in(\App\Support\LianBarangays::all())],
            'farm_area' => ['nullable', 'numeric', 'min:0'],
            'farm_type' => ['required', 'string', 'in:Rainfed,Irrigated'],
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => User::ROLE_FARMER,
                'contact_number' => $validated['contact_number'],
                'address' => $validated['address'] ?? null,
                'barangay' => $validated['barangay'],
                // Manuscript – Naicel: Pending until the MAO approves the sign-up.
                'status' => User::STATUS_PENDING,
            ]);

            FarmerProfile::create([
                'user_id' => $user->id,
                'full_name' => $validated['name'],
                'contact_number' => $validated['contact_number'],
                'address' => $validated['address'] ?? null,
                'barangay' => $validated['barangay'],
                'farm_area' => $validated['farm_area'] ?? null,
                'farm_type' => $validated['farm_type'],
            ]);

            return $user->load('farmerProfile');
        });
        // MAO – Naicel (2026-10-09): a sign-up that waits for approval goes to every MAO's notification bell.
        rescue(fn () => app(\App\Services\Mao\FarmerApprovalNotifier::class)->sync(), null, false);

        return response()->json([
            'message' => $user->status === User::STATUS_PENDING ? 'Registration received. You can log in once the Municipal Agriculture Office approves it.' : 'Registration successful.',   // Manuscript – Naicel
            'user' => $this->mobileUser($user),
        ], 201);
    }

    /**
     * POST /api/mobile/password — the app's own "choose a new password" (there are no app tokens: the current password
     * proves who is asking). Rate limited; the account's web sessions are ended afterwards.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults(), new \App\Rules\NotKnownDefaultPassword, 'different:current_password'],
        ]);
        $user = User::query()->where('email', $data['email'])->first();
        $matches = $user && rescue(fn () => Hash::check($data['current_password'], $user->password), false, false);
        if (! $matches) {
            throw ValidationException::withMessages(['email' => ['These credentials do not match our records.']]);
        }
        if (! $user->hasApplicationAccess()) {
            throw ValidationException::withMessages(['email' => [User::signInRefusal($user->status)]]);
        }
        // A shared password proves nothing: it cannot be used to choose a new one (that would let anyone take over).
        if (\App\Services\Accounts\PasswordChange::lockdownFor($request) && \App\Services\Accounts\PasswordChange::isShared($user, $data['current_password'])) {
            return $this->recoveryRequired($user, sendLink: true);
        }

        \App\Services\Accounts\PasswordChange::apply($user, $data['password'], null, 'mobile app');

        return response()->json(['message' => 'Your new password is saved.', 'user' => $this->mobileUser($user->fresh())]);
    }

    /**
     * POST /api/mobile/forgot-password — emails the standard expiring reset link (config auth.passwords). Always the
     * same answer, so it never tells whether an email has an account.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email']]);
        $user = User::query()->where('email', $data['email'])->first();
        if ($user && $user->hasApplicationAccess() && \App\Services\Accounts\AccountEmails::recipient($user) !== null) {
            rescue(fn () => \Illuminate\Support\Facades\Password::sendResetLink(['email' => $user->email]), null, true);
        }

        return response()->json(['message' => 'If this email has an iClimate account, a reset link was sent to it. You can also sign in on the iClimate website to choose a new password.']);
    }

    /** 409 for a shared password: recover by email link (sent now when possible) or with a code from MAO Staff. */
    private function recoveryRequired(User $user, bool $sendLink): JsonResponse
    {
        $method = \App\Services\Accounts\AccountRecovery::method($user);
        if ($sendLink && $method === \App\Services\Accounts\AccountRecovery::METHOD_EMAIL) {
            \App\Services\Accounts\AccountRecovery::sendEmailLink($user);
        }

        return response()->json([
            'message' => $method === \App\Services\Accounts\AccountRecovery::METHOD_EMAIL
                ? 'Your password is not safe. We sent a link to your email to choose a new one.'
                : 'Your password is not safe. Ask the Municipal Agriculture Office for a code to choose a new one.',
            'recovery_required' => true,
            'recovery_method' => $method,
            'recover_url' => route('account.recover'),
        ], 409);
    }

    private function passwordChangeRequired(): JsonResponse
    {
        return response()->json([
            'message' => 'Choose a new password before using iClimate.',
            'password_change_required' => true,
            'change_password_url' => url('/api/mobile/password'),
            'web_change_password_url' => route('password.first-change'),
            'forgot_password_url' => url('/api/mobile/forgot-password'),
        ], 409);
    }

    private function mobileUser(User $user): array
    {
        $user->loadMissing('farmerProfile');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'contact_number' => $user->contact_number,
            'address' => $user->address,
            'barangay' => $user->barangay,
            'status' => $user->status,
            'farmer_profile' => $user->farmerProfile,
        ];
    }
}
