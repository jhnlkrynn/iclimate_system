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

class MobileAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'role' => ['nullable', 'string', 'in:Farmer,MAO Personnel,IT Expert'],
        ]);

        $user = User::query()
            ->where('email', $credentials['email'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if (($credentials['role'] ?? null) && $user->role !== $credentials['role']) {
            throw ValidationException::withMessages([
                'role' => ['The selected role does not match this account.'],
            ]);
        }

        if ($user->status !== User::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'email' => ['This account is inactive. Please contact the administrator.'],
            ]);
        }

        return response()->json([
            'message' => 'Login successful.',
            'user' => $this->mobileUser($user),
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'contact_number' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'barangay' => ['required', 'string', 'max:255'],
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
                'status' => User::STATUS_ACTIVE,
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

        return response()->json([
            'message' => 'Registration successful.',
            'user' => $this->mobileUser($user),
        ], 201);
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
