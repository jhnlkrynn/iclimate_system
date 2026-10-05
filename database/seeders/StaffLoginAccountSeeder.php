<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffLoginAccountSeeder extends Seeder
{
    /**
     * Create (or repair) the IT and MAO staff sign-in accounts.
     *
     * Safe to run repeatedly: each account is matched by its email, so no duplicates are made.
     * The password comes from ICLIMATE_STAFF_ACCOUNT_PASSWORD and is only ever stored hashed.
     */
    public function run(): void
    {
        $password = (string) config('iclimate.staff_account_password');

        if ($password === '') {
            $this->command?->warn('ICLIMATE_STAFF_ACCOUNT_PASSWORD is not set: staff sign-in accounts were not seeded.');

            return;
        }

        $accounts = [
            'it@gmail.com' => [
                'name' => 'IT Personnel',
                'role' => User::ROLE_IT_EXPERT,
                'contact_number' => '09170000013',
                'address' => 'Lian, Batangas',
            ],
            'mao@gmail.com' => [
                'name' => 'MAO Personnel',
                'role' => User::ROLE_MAO,
                'contact_number' => '09170000012',
                'address' => 'Municipal Agriculture Office, Lian, Batangas',
            ],
        ];

        foreach ($accounts as $email => $details) {
            $user = User::query()->firstOrNew(['email' => $email]);

            $user->fill([
                ...$details,
                'barangay' => 'Poblacion',
                'status' => User::STATUS_ACTIVE,
                'password' => Hash::make($password),
            ]);
            $user->email_verified_at ??= now();
            $user->save();
        }
    }
}
