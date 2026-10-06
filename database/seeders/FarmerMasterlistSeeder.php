<?php

namespace Database\Seeders;

use App\Models\FarmerOrganization;
use App\Models\FarmerProfile;
use App\Models\User;
use App\Support\LianBarangays;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Imports the iClimate Farmer Masterlist (RSBSA export) into users + farmer_profiles.
 *
 * Source file: database/data/iclimate_farmer_masterlist.csv. It holds farmers' names and RSBSA numbers, so it
 * is NOT kept in git (.gitignore): copy it onto the server by hand before running this seeder.
 *
 * Passwords: nothing is written in code. A new account gets ICLIMATE_FARMER_ACCOUNT_PASSWORD when that is set,
 * otherwise its own random password. An account that already exists keeps the password it has.
 * Columns:     No., RSBSA No., Last Name, First Name, Middle Name, Suffix,
 *              Barangay, Area (ha), Association
 *
 * Run with:
 *   php artisan db:seed --class=FarmerMasterlistSeeder
 */
class FarmerMasterlistSeeder extends Seeder
{
    private const EMAIL_DOMAIN = 'iclimate.local';

    public function run(): void
    {
        $path = database_path('data/iclimate_farmer_masterlist.csv');

        if (! is_file($path)) {
            $this->command?->error("Masterlist CSV not found at: {$path}");

            return;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->command?->error("Unable to open masterlist CSV at: {$path}");

            return;
        }

        // Skip the header row.
        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);
            $this->command?->error('Masterlist CSV appears to be empty.');

            return;
        }

        $sharedPassword = (string) config('iclimate.farmer_account_password');
        $sharedHash = $sharedPassword !== '' ? Hash::make($sharedPassword) : null;
        $imported = 0;
        $skipped = 0;
        $rowNumber = 1; // header was row 0

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            // Ignore completely blank lines.
            if (count(array_filter($row, fn ($value) => $this->cleanValue((string) $value) !== '')) === 0) {
                continue;
            }

            [
                $no,
                $rsbsaNo,
                $lastName,
                $firstName,
                $middleName,
                $suffix,
                $barangayRaw,
                $areaRaw,
                $associationRaw,
            ] = array_pad(array_map(fn ($value) => $this->cleanValue((string) $value), $row), 9, '');

            if ($lastName === '' && $firstName === '') {
                $skipped++;

                continue;
            }

            DB::transaction(function () use (
                $no,
                $rsbsaNo,
                $lastName,
                $firstName,
                $middleName,
                $suffix,
                $barangayRaw,
                $areaRaw,
                $associationRaw,
                $hashedPassword,
            ) {
                $identifier = $rsbsaNo !== '' ? $rsbsaNo : $this->placeholderIdentifier($no);

                $fullName = trim(implode(' ', array_filter([$firstName, $middleName, $lastName, $suffix])));
                $barangay = $this->resolveBarangay($barangayRaw);
                $farmArea = $areaRaw !== '' && is_numeric($areaRaw) ? round((float) $areaRaw, 2) : null;
                $associationName = $this->resolveAssociationName($associationRaw);

                $farmerOrganizationId = null;
                $farmType = FarmerProfile::FARM_TYPE_RAINFED;

                if ($associationName !== null) {
                    $inferredFarmType = Str::endsWith(Str::upper($associationName), ' IA')
                        ? FarmerProfile::FARM_TYPE_IRRIGATED
                        : FarmerProfile::FARM_TYPE_RAINFED;

                    $organization = FarmerOrganization::query()->firstOrCreate(
                        ['name' => $associationName],
                        [
                            'barangay' => $barangay ?? 'Unknown',
                            'farm_type' => $inferredFarmType,
                        ],
                    );

                    $farmerOrganizationId = $organization->id;
                    $farmType = $organization->farm_type;
                }

                $email = $this->buildEmail($identifier);

                $user = User::query()->firstOrNew(['email' => $email]);
                $user->fill([
                    'name' => $fullName !== '' ? $fullName : $identifier,
                    'role' => User::ROLE_FARMER,
                    'barangay' => $barangay,
                    'status' => User::STATUS_ACTIVE,
                ]);
                $user->email_verified_at ??= now();
                if (! $user->exists) {
                    // Re-running the import never resets a password a farmer (or IT) has already set.
                    $user->password = $sharedHash ?? Hash::make(Str::password(24));
                }
                $user->save();

                FarmerProfile::query()->updateOrCreate(
                    ['rsbsa_no' => $identifier],
                    [
                        'user_id' => $user->id,
                        'farmer_organization_id' => $farmerOrganizationId,
                        'full_name' => $fullName !== '' ? $fullName : $identifier,
                        'barangay' => $barangay ?? 'Unknown',
                        'farm_area' => $farmArea,
                        'farm_type' => $farmType,
                    ],
                );
            });

            $imported++;
        }

        fclose($handle);

        $this->command?->info("Farmer masterlist import complete. Imported: {$imported}, skipped blank rows: {$skipped}.");
    }

    /**
     * Strip non-breaking spaces and collapse repeated whitespace.
     */
    private function cleanValue(string $value): string
    {
        $value = str_replace(["\xC2\xA0", "\xEF\xBB\xBF"], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function placeholderIdentifier(string $no): string
    {
        $no = $no !== '' ? $no : (string) random_int(100000, 999999);

        return 'NOID-'.str_pad($no, 4, '0', STR_PAD_LEFT);
    }

    private function buildEmail(string $identifier): string
    {
        $local = Str::of($identifier)
            ->lower()
            ->replaceMatches('/[^a-z0-9.\-]+/', '.')
            ->trim('.')
            ->toString();

        if ($local === '') {
            $local = 'farmer.'.Str::random(8);
        }

        return "{$local}@".self::EMAIL_DOMAIN;
    }

    private function resolveBarangay(string $barangayRaw): ?string
    {
        if ($barangayRaw === '') {
            return null;
        }

        return LianBarangays::canonicalName($barangayRaw) ?? Str::title(Str::lower($barangayRaw));
    }

    private const ABBREVIATION_TO_NAME = [
        'BALIBAGO FA' => 'Balibago Farmers Association',
        'CUACO IA' => 'Cuaco Irrigator\'s Association',
        'BUNGAHAN BALDEO IA' => 'Bungahan Baldeo Irrigators Association Inc',
        'MOLINO FA' => 'Molino-Resagwa Farmers Irrigators Association Inc',
        'LUMANG TUBIGAN FA' => 'Lumang Tubigan Farmers Association',
        'SALUYSOY FA' => 'Saluysoy Farmers Association',
        'LUYAHAN FA' => 'Luyahan Farmers Association',
        'MALARUHATAN FA' => 'Malaruhatan Farmers Association',
        'MATABUNGKAY FA' => 'Matabungkay Farmers Association',
        'PRENZA BAGBAG BUNGAHAN FA' => 'Prenza Bagbag Bungahan Farmers Association',
        'CALACA FA' => 'Sitio Calaca Barangay Prenza Lian, Batangas Farmers Assn. Inc.',
        'LAGULUAN FA' => 'Laguluan Spring Irrigators FA',
        'MPOFA' => 'Masipag Prenza Organic Farmers Association',
        'PRELA BASAKA IA' => 'Prenza Ladron Balanoy Sampalocan Calumpit Irrigators Association Inc.',
        'GINTONG PAG ASA FA' => 'Samahan ng Gintong Pagasa Ng Magsasaka Ng Lian, Inc.',
        'TAN-AG FA' => 'Tan-ag Farmers Association',
    ];

    private function resolveAssociationName(string $associationRaw): ?string
    {
        if ($associationRaw === '' || Str::upper($associationRaw) === 'NO ASSOCIATION') {
            return null;
        }

        $upper = Str::upper(trim($associationRaw));
        if (isset(self::ABBREVIATION_TO_NAME[$upper])) {
            return self::ABBREVIATION_TO_NAME[$upper];
        }

        return $associationRaw;
    }
}
