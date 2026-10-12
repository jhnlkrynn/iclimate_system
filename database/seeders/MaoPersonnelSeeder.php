<?php

namespace Database\Seeders;

use App\Models\BarangayProfile;
use App\Models\MaoUserBarangay;
use App\Models\User;
use App\Support\LianBarangays;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Panel revision 4.4 A: one sign-in account for each Municipal Agriculture Office staff member of Lian, with their
 * position, role (Admin = Municipal Agriculturist, MAO = the others), station, area covered and barangays
 * (mao_user_barangays). Every action is then recorded under the person's own name.
 *
 * Password: the default MAO password (config iclimate.mao_default_password, "Password@123"), stored HASHED; the account
 * must change it at the first sign-in (must_change_password).
 *
 * Safe to run again: an account is found by its e-mail (or the e-mail an earlier version of this seeder gave it, which
 * is then changed to the one below). Details and barangays are written only when the account is first made, so a run
 * never undoes the Municipal Agriculturist's later edits or a password the person already changed. Set
 * MAO_SEED_RESET_COVERAGE=true to put the barangays back to this list.
 */
class MaoPersonnelSeeder extends Seeder
{
    /** @var array<int, array<string, mixed>> */
    public const PERSONNEL = [
        [
            'email' => 'ofeliarodil@gmail.com',
            'previous_emails' => ['ofelia.rodil@iclimate.com'],
            'name' => 'Ofelia V. Rodil',
            'position' => 'Municipal Agriculturist',
            'station_type' => User::STATION_MUNICIPAL,
            'area_covered_ha' => null,
            'is_mao_head' => true,                       // Admin
            'barangays' => [],                           // municipal-wide
        ],
        [
            'email' => 'jeanlaviste@gmail.com',
            'previous_emails' => ['jean.laviste@iclimate.com'],
            'name' => 'Jean L. Laviste',
            'position' => 'Agricultural Technologist',
            'station_type' => User::STATION_MUNICIPAL,
            'area_covered_ha' => 245.15,
            'is_mao_head' => false,
            'barangays' => [['Kapito', null], ['Malaruhatan', null], ['Bungahan', null], ['San Diego', null]],
        ],
        [
            'email' => 'lodivicocachuela@gmail.com',
            'previous_emails' => ['lodivico.cachuela@iclimate.com'],
            'name' => 'Lodivico A. Cachuela',
            'position' => 'Agricultural Technologist',
            'station_type' => User::STATION_MUNICIPAL,
            'area_covered_ha' => 202.83,
            'is_mao_head' => false,
            // Tan-ag is a sitio of San Diego: only that part of San Diego is his.
            'barangays' => [['Balibago', null], ['Binubusan', null], ['Luyahan', null], ['Matabungkay', null], ['San Diego', 'Tan-ag']],
        ],
        [
            'email' => 'evelynmayari@gmail.com',
            'previous_emails' => ['evelyn.mayari@iclimate.com'],
            'name' => 'Evelyn M. Mayari',
            'position' => 'Administrative Aide I, designated Agricultural Extension Worker',
            'station_type' => User::STATION_MUNICIPAL,
            'area_covered_ha' => 262.39,
            'is_mao_head' => false,
            'barangays' => [['Prenza', null]],
        ],
    ];

    public function run(): void
    {
        $password = (string) config('iclimate.mao_default_password', 'Password@123');
        $resetCoverage = filter_var(env('MAO_SEED_RESET_COVERAGE', false), FILTER_VALIDATE_BOOL);
        $made = $moved = 0;

        foreach (self::PERSONNEL as $person) {
            $user = User::query()->where('email', $person['email'])->first();
            $isNew = false;

            if (! $user && ($user = User::query()->whereIn('email', $person['previous_emails'])->first())) {
                // An account made by the earlier version of this seeder: same person, new sign-in e-mail and the
                // default password (its history stays attached to it).
                $user->forceFill(['email' => $person['email'], 'password' => Hash::make($password), 'must_change_password' => true])->save();
                $moved++;
            }

            if (! $user) {
                $user = new User;
                $isNew = true;
                $user->fill([
                    'name' => $person['name'],
                    'email' => $person['email'],
                    'role' => User::ROLE_MAO,
                    'position' => $person['position'],
                    'station_type' => $person['station_type'],
                    'area_covered_ha' => $person['area_covered_ha'],
                    'is_mao_head' => $person['is_mao_head'],
                    'address' => 'Municipal Agriculture Office, Lian, Batangas',
                    'barangay' => 'Barangay 1 (Pob.)',
                    'status' => User::STATUS_ACTIVE,
                    'password' => Hash::make($password),
                    'must_change_password' => true,
                ]);
                $user->email_verified_at = now();
                $user->save();
                $made++;
            }

            if ($isNew || $resetCoverage) {
                MaoUserBarangay::query()->where('user_id', $user->id)->delete();
                foreach ($person['barangays'] as [$barangay, $sitio]) {
                    $name = LianBarangays::canonicalName($barangay) ?? $barangay;
                    MaoUserBarangay::query()->create([
                        'user_id' => $user->id,
                        'barangay' => $name,
                        'sitio' => $sitio,
                        'barangay_profile_id' => BarangayProfile::query()->where('barangay', $name)->value('id'),
                    ]);
                }
            }
            \App\Auth\CachedEloquentUserProvider::forget($user->id);
        }
        User::forgetMaoPersonnel();

        $this->command?->info("MAO personnel accounts ready: {$made} created, {$moved} moved to their new e-mail, ".count(self::PERSONNEL).' in all.');
    }
}
