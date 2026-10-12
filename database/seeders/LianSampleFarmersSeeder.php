<?php

namespace Database\Seeders;

use App\Models\Farm;
use App\Models\FarmerOrganization;
use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\User;
use App\Support\HarvestTiming;
use App\Support\LianBarangays;
use App\Support\SchemaCache;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Panel revision 4.1: realistic SAMPLE farmers across Lian's 19 barangays, for testing the dashboards (Total Farmers KPI,
 * MAO coverage filters, organizations, harvest timing, predicted vs actual by barangay).
 *
 * Opt-in only (not part of DatabaseSeeder) and refused outside local/testing:
 *     php artisan db:seed --class=LianSampleFarmersSeeder
 * Every sample account uses the e-mail domain below, so they can be removed in one step:
 *     SAMPLE_FARMERS_PURGE=true php artisan db:seed --class=LianSampleFarmersSeeder
 *
 * The data is generated from a fixed seed: running it again gives the same farmers (and does not duplicate them).
 */
class LianSampleFarmersSeeder extends Seeder
{
    public const EMAIL_DOMAIN = 'sample.iclimate.test';

    public const COUNT = 120;

    private const FIRST = ['Juan', 'Maria', 'Jose', 'Ana', 'Pedro', 'Rosa', 'Antonio', 'Luz', 'Ramon', 'Carmen', 'Eduardo', 'Teresita', 'Ricardo',
        'Josefina', 'Danilo', 'Erlinda', 'Rogelio', 'Marites', 'Ernesto', 'Lourdes', 'Romeo', 'Nenita', 'Rodolfo', 'Gloria', 'Arnel', 'Cristina',
        'Renato', 'Rowena', 'Efren', 'Imelda', 'Noel', 'Divina', 'Alfredo', 'Corazon', 'Leonardo', 'Remedios', 'Mario', 'Felisa', 'Wilfredo', 'Aurora'];

    private const LAST = ['Dela Cruz', 'Garcia', 'Reyes', 'Ramos', 'Mendoza', 'Santos', 'Castillo', 'Villanueva', 'Bautista', 'Aquino', 'Manalo',
        'Ilagan', 'Macalintal', 'Panganiban', 'Dimaculangan', 'Hernandez', 'Marasigan', 'Atienza', 'Comia', 'Landicho', 'Perez', 'Mercado',
        'Rodriguez', 'Cabrera', 'De Leon', 'Magpantay', 'Caringal', 'Maranan', 'Javier', 'Umali'];

    /** Rough share of Lian's rice farmers by barangay (more in the big farming barangays). */
    private const WEIGHTS = [
        'Bagong Pook' => 5, 'Balibago' => 8, 'Barangay 1 (Pob.)' => 2, 'Barangay 2 (Pob.)' => 1, 'Barangay 3 (Pob.)' => 1,
        'Barangay 4 (Pob.)' => 1, 'Barangay 5 (Pob.)' => 2, 'Binubusan' => 9, 'Bungahan' => 7, 'Cumba' => 6, 'Humayingan' => 6,
        'Kapito' => 8, 'Lumaniag' => 6, 'Luyahan' => 7, 'Malaruhatan' => 8, 'Matabungkay' => 5, 'Prenza' => 10, 'Puting-Kahoy' => 5,
        'San Diego' => 8,
    ];

    private const REASONS = ['typhoon', 'flooding', 'drought', 'pest_disease', 'matured_early', 'labor_machinery', 'market_timing'];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('LianSampleFarmersSeeder makes sample data and runs only in local or testing environments.');
        }

        if (filter_var(env('SAMPLE_FARMERS_PURGE', false), FILTER_VALIDATE_BOOL)) {
            $deleted = User::query()->where('email', 'like', '%@'.self::EMAIL_DOMAIN)->delete();
            $this->command?->info("Removed {$deleted} sample farmer accounts.");

            return;
        }

        mt_srand(20261010);
        $password = Hash::make((string) (config('iclimate.farmer_account_password') ?: config('iclimate.default_account_password') ?: 'Sample-Farmer-2026!'));
        $organizations = FarmerOrganization::query()->get(['id', 'barangay', 'farm_type'])->groupBy('barangay');
        $allOrgIds = $organizations->flatten()->pluck('id')->all();
        $barangays = $this->weightedBarangays();
        $today = now('Asia/Manila')->startOfDay();
        $made = 0;

        DB::transaction(function () use (&$made, $password, $organizations, $allOrgIds, $barangays, $today): void {
            for ($i = 1; $i <= self::COUNT; $i++) {
                $first = self::FIRST[mt_rand(0, count(self::FIRST) - 1)];
                $last = self::LAST[mt_rand(0, count(self::LAST) - 1)];
                $middle = chr(mt_rand(65, 90)).'.';
                $name = "{$first} {$middle} {$last}";
                $barangay = $barangays[mt_rand(0, count($barangays) - 1)];
                $status = match (true) {
                    $i % 17 === 0 => User::STATUS_PENDING,
                    $i % 41 === 0 => User::STATUS_INACTIVE,
                    default => User::STATUS_ACTIVE,
                };
                $email = sprintf('farmer%03d@%s', $i, self::EMAIL_DOMAIN);

                $user = User::query()->firstOrNew(['email' => $email]);
                if ($user->exists) {
                    continue;
                }
                $user->fill([
                    'name' => $name, 'role' => User::ROLE_FARMER, 'password' => $password, 'barangay' => $barangay,
                    'contact_number' => '09'.mt_rand(10, 99).mt_rand(1000000, 9999999), 'address' => 'Brgy. '.$barangay.', Lian, Batangas',
                    'status' => $status,
                ]);
                $user->email_verified_at = now();
                $user->save();
                $made++;

                // Pending sign-ups have no farm records yet, like real new registrations.
                if ($status === User::STATUS_PENDING) {
                    continue;
                }

                $local = $organizations->get($barangay, collect());
                $primary = $local->isNotEmpty() && mt_rand(1, 100) <= 75 ? $local->random() : null;
                $farmType = $primary?->farm_type ?? (mt_rand(1, 100) <= 55 ? 'Irrigated' : 'Rainfed');
                $hectares = round(mt_rand(40, 350) / 100, 2);

                $profile = FarmerProfile::query()->create([
                    'user_id' => $user->id, 'farmer_organization_id' => $primary?->id, 'full_name' => $name,
                    'contact_number' => $user->contact_number, 'address' => $user->address, 'barangay' => $barangay,
                    'farm_area' => $hectares, 'farm_type' => $farmType,
                    'rsbsa_no' => sprintf('04-10-13-%03d-%05d', array_search($barangay, LianBarangays::all(), true) + 1, 20000 + $i),
                ]);
                // Panel revision 1.1: about one in six farmers belongs to a second organization.
                if ($primary && $allOrgIds !== [] && mt_rand(1, 100) <= 17) {
                    $second = $allOrgIds[mt_rand(0, count($allOrgIds) - 1)];
                    $profile->syncOrganizations([$primary->id, $second], $primary->id);
                }

                $farm = SchemaCache::hasTable('farms') ? Farm::query()->create([
                    'user_id' => $user->id, 'farm_name' => 'Bukid sa '.$barangay, 'barangay' => $barangay,
                    'farm_size_hectares' => $hectares, 'farm_size_method' => Farm::SIZE_EXACT, 'farm_type' => $farmType, 'is_primary' => true,
                ]) : null;

                if (! SchemaCache::hasTable('farmer_planting_schedules')) {
                    continue;
                }
                // Last wet season: planted and harvested, with the predicted and actual yield (t/ha).
                if (mt_rand(1, 100) <= 65) {
                    $planted = $today->copy()->subMonths(mt_rand(7, 9))->day(mt_rand(1, 28));
                    $expected = $planted->copy()->addDays(115);
                    $offset = $this->harvestOffset();
                    $harvested = $expected->copy()->addDays($offset);
                    $predicted = round(($farmType === 'Irrigated' ? 4.3 : 3.6) + mt_rand(-40, 40) / 100, 2);
                    $actual = round($predicted + mt_rand(-80, 60) / 100 - ($offset < -3 ? 0.3 : 0), 2);
                    $timing = HarvestTiming::classify($expected, $harvested);
                    FarmerPlantingSchedule::query()->create([
                        'user_id' => $user->id, 'farm_id' => $farm?->id, 'planting_date' => $planted->toDateString(),
                        'scheduled_harvest_date' => $expected->toDateString(), 'season' => 'Wet', 'farm_type' => $farmType,
                        'barangay' => $barangay, 'farm_area_hectares' => $hectares, 'progress_status' => 'Harvested',
                        'harvested_on' => $harvested->toDateString(), 'actual_yield' => max(1.5, $actual), 'predicted_yield' => $predicted,
                        'prediction_source' => 'Sample data',
                        'harvest_timing' => $timing,
                        'harvest_timing_reason' => HarvestTiming::needsReason($timing) ? self::REASONS[mt_rand(0, count(self::REASONS) - 1)] : null,
                    ]);
                }
                // This season: planted, harvest still to come.
                if (mt_rand(1, 100) <= 55) {
                    $planted = $today->copy()->subDays(mt_rand(20, 90));
                    FarmerPlantingSchedule::query()->create([
                        'user_id' => $user->id, 'farm_id' => $farm?->id, 'planting_date' => $planted->toDateString(),
                        'scheduled_harvest_date' => $planted->copy()->addDays(115)->toDateString(),
                        'season' => \App\Support\RiceSeason::forDate($planted), 'farm_type' => $farmType, 'barangay' => $barangay,
                        'farm_area_hectares' => $hectares,
                    ]);
                }
            }
        });

        rescue(fn () => \App\Support\CacheScope::bumpAll(), null, false);
        $this->command?->info("Sample farmers added: {$made} (e-mail domain @".self::EMAIL_DOMAIN.').');
    }

    /** @return array<int, string> barangay names repeated by weight */
    private function weightedBarangays(): array
    {
        $list = [];
        foreach (self::WEIGHTS as $barangay => $weight) {
            array_push($list, ...array_fill(0, $weight, $barangay));
        }

        return $list;
    }

    /** Days between the expected and the real harvest: mostly on time, some early or late. */
    private function harvestOffset(): int
    {
        $roll = mt_rand(1, 100);

        return match (true) {
            $roll <= 60 => mt_rand(-2, 2),
            $roll <= 80 => -mt_rand(5, 15),
            default => mt_rand(5, 20),
        };
    }
}
