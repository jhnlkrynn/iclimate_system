<?php

namespace Database\Seeders;

use App\Models\Farm;
use App\Models\FarmerOrganization;
use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\RiceProduction;
use App\Models\User;
use App\Support\LianBarangays;
use App\Support\LianFarmTypes;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Opt-in demo import for the supplied 851-record farmer list.
 *
 * The source is deliberately a plain text file so the demo data can be audited and removed without
 * changing the application's schema. Every generated account uses the demo domain below.
 */
class FarmerDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public const EMAIL_DOMAIN = 'iclimate.test';

    public const SOURCE = 'database/seeders/data/farmers-list.txt';

    private array $report = [
        'records' => 0, 'unique_people' => 0, 'merged_duplicates' => 0, 'accounts_created' => 0,
        'accounts_updated' => 0, 'farms_created' => 0, 'schedules_created' => 0, 'harvested' => 0,
        'current_crops' => 0, 'productions_created' => 0, 'total_area' => 0.0, 'total_production' => 0.0,
        'barangay_fixes' => [], 'unmatched_barangays' => [], 'unmatched_associations' => [],
        'duplicate_names' => [], 'rsbsa_conflicts' => [], 'barangays' => [], 'recommended' => [], 'credentials' => [],
    ];

    private array $organizations;

    private array $currentEmails = [];

    public function run(?string $source = null, bool $freshActivity = false, bool $allowRemote = false): array
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Refusing to seed demo farmers while APP_ENV=production.');
        }
        $connection = (string) config('database.default');
        $host = (string) config("database.connections.{$connection}.host", '');
        if (! $allowRemote && $connection !== 'sqlite' && $host !== '' && ! in_array(strtolower($host), ['127.0.0.1', 'localhost', '::1'], true)) {
            throw new \RuntimeException("Refusing to seed non-local database host {$host}. Set the local database connection first.");
        }

        $source = $source ?: base_path(self::SOURCE);
        if (! is_file($source)) {
            throw new \RuntimeException("Farmer source file not found: {$source}");
        }

        $rows = $this->parse($source);
        if ($rows === []) {
            throw new \RuntimeException('No numbered farmer records were found in the source file.');
        }

        $this->report['records'] = count($rows);
        $this->organizations = FarmerOrganization::query()->get()->keyBy(fn (FarmerOrganization $o) => $this->key($o->name))->all();
        $people = $this->mergePeople($rows);
        $this->report['unique_people'] = count($people);

        $password = Hash::make('Password@123');
        DB::transaction(function () use ($people, $password, $freshActivity): void {
            if ($freshActivity) {
                $this->removeDemoActivity();
            }

            foreach ($people as $person) {
                $this->seedPerson($person, $password);
            }

            $this->seedAggregateProductionRows();
        });

        $this->writeOutputs();

        return $this->report;
    }

    /** @return array<int, array{number:int,name:string,rsbsa:?string,barangay:string,area:float,association:?string}> */
    private function parse(string $source): array
    {
        $rows = [];
        foreach (file($source, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (! preg_match('/^\s*(\d+)\.\s*(.*?)\s*\|\s*(.*?)\s*\|\s*(.*?)\s*\|\s*([0-9.]+)\s*\|\s*(.*?)\s*$/u', $line, $m)) {
                continue;
            }

            $association = trim($m[6]) === '—' || trim($m[6]) === '' ? null : $this->association(trim($m[6]));
            $barangay = $this->barangay($m[4], $association);
            $rows[] = [
                'number' => (int) $m[1], 'name' => trim($m[2]),
                'rsbsa' => trim($m[3]) === '—' ? null : (trim($m[3]) ?: null),
                'barangay' => $barangay['value'], 'area' => (float) $m[5], 'association' => $association,
            ];
        }

        return $rows;
    }

    private function mergePeople(array $rows): array
    {
        $people = [];
        foreach ($rows as $row) {
            $name = $this->displayName($row['name']);
            $key = $this->key($name);
            if (isset($people[$key])) {
                $people[$key]['farms'][] = $row;
                $people[$key]['area'] += $row['area'];
                $this->report['merged_duplicates']++;
                $this->report['duplicate_names'][$name] = ($this->report['duplicate_names'][$name] ?? 1) + 1;
                continue;
            }

            $people[$key] = ['name' => $name, 'farms' => [$row], 'area' => $row['area']];
        }

        return array_values($people);
    }

    private function seedPerson(array $person, string $password): void
    {
        $firstFarm = $person['farms'][0];
        $profileRsbsa = null;
        foreach ($person['farms'] as $sourceFarm) {
            $rsbsa = $sourceFarm['rsbsa'];
            if (! $rsbsa) {
                continue;
            }
            $existingProfile = FarmerProfile::query()->where('rsbsa_no', $rsbsa)->with('user:id,email')->first();
            if ($existingProfile && ! str_ends_with(strtolower((string) $existingProfile->user?->email), '@'.self::EMAIL_DOMAIN)) {
                $this->report['rsbsa_conflicts'][] = [
                    'rsbsa' => $rsbsa, 'source_name' => $person['name'], 'existing_profile_id' => $existingProfile->id,
                    'existing_user_id' => $existingProfile->user_id,
                ];

                // A conflicting RSBSA belongs to an existing real account;
                // skip only that identifier and continue importing this demo
                // person and any other farm rows.
                continue;
            }
            $profileRsbsa ??= $rsbsa;
        }
        $email = $this->uniqueEmail($person['name']);
        $existing = User::query()->where('email', $email)->first();
        if ($existing && ! str_ends_with(strtolower((string) $existing->email), '@'.self::EMAIL_DOMAIN)) {
            throw new \RuntimeException("Refusing to modify a non-demo account using {$email}.");
        }

        $user = $existing ?: new User(['email' => $email]);
        $user->fill([
            'name' => $person['name'], 'password' => $password, 'role' => User::ROLE_FARMER,
            'contact_number' => '0999'.str_pad((string) ($firstFarm['number'] % 10000000), 7, '0', STR_PAD_LEFT),
            'address' => 'Lian, Batangas', 'barangay' => $firstFarm['barangay'], 'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
        if (Schema::hasColumn('users', 'must_change_password')) {
            $user->must_change_password = true;
        }
        $user->save();
        $existing ? $this->report['accounts_updated']++ : $this->report['accounts_created']++;

        $primaryOrg = $this->findOrganization($firstFarm['association']);
        $profile = FarmerProfile::query()->updateOrCreate(['user_id' => $user->id], [
            'farmer_organization_id' => $primaryOrg?->id, 'rsbsa_no' => $profileRsbsa,
            'full_name' => $person['name'], 'contact_number' => $user->contact_number,
            'address' => $user->address, 'barangay' => $firstFarm['barangay'], 'farm_area' => $person['area'],
            'farm_type' => $primaryOrg?->farm_type ?? FarmerProfile::FARM_TYPE_IRRIGATED,
        ]);
        if ($primaryOrg) {
            $profile->syncOrganizations([$primaryOrg->id], $primaryOrg->id);
        }

        $farms = [];
        foreach ($person['farms'] as $index => $row) {
            $org = $this->findOrganization($row['association']);
            $farmType = $org?->farm_type ?? FarmerProfile::FARM_TYPE_RAINFED;
            $farmName = 'Farm '.($index + 1);
            $legacyFarmName = 'Demo'.' Rice Farm '.($index + 1);
            $farm = Farm::query()->where('user_id', $user->id)->whereIn('farm_name', [$farmName, 'Rice Farm '.($index + 1), $legacyFarmName])->first() ?? new Farm;
            $farm->fill([
                'user_id' => $user->id, 'farm_name' => $farmName,
                'barangay' => $row['barangay'], 'location_description' => $row['barangay'].', Lian, Batangas',
                'location_method' => Farm::LOCATION_BARANGAY, 'farm_size_hectares' => $row['area'],
                'farm_size_method' => Farm::SIZE_EXACT, 'farm_size_unit' => Farm::UNIT_HECTARES,
                'farm_type' => $farmType, 'is_primary' => $index === 0, 'archived_at' => null,
            ]);
            $farm->save();
            $farms[] = [$farm, $row, $farmType];
            $this->report['farms_created']++;
            $this->report['total_area'] += $row['area'];
        }

        $this->seedSchedules($user, $farms);
        $this->report['credentials'][] = [
            $firstFarm['number'], $person['name'], $email, 'Password@123', $firstFarm['rsbsa'],
            $firstFarm['barangay'], $firstFarm['association'], number_format($person['area'], 2, '.', ''),
        ];
    }

    private function seedSchedules(User $user, array $farms): void
    {
        $today = now('Asia/Manila')->startOfDay();
        foreach ($farms as [$farm, $row, $farmType]) {
            $history = [
                ['season' => 'Wet', 'year' => 2023, 'plant' => '2023-06-20', 'harvest' => '2023-10-18'],
                ['season' => 'Dry', 'year' => 2023, 'plant' => '2023-12-10', 'harvest' => '2024-04-02'],
                ['season' => 'Wet', 'year' => 2024, 'plant' => '2024-07-02', 'harvest' => '2024-10-22'],
                ['season' => 'Dry', 'year' => 2024, 'plant' => '2024-12-18', 'harvest' => '2025-04-08'],
                ['season' => 'Wet', 'year' => 2025, 'plant' => '2025-06-24', 'harvest' => '2025-10-16'],
                ['season' => 'Dry', 'year' => 2025, 'plant' => '2025-12-12', 'harvest' => '2026-04-05'],
            ];
            foreach ($history as $i => $cycle) {
                if (($row['number'] + $i * 17) % 100 >= ($cycle['season'] === 'Wet' ? 85 : 65)) {
                    continue;
                }
                $area = round($row['area'] * (0.78 + (($row['number'] + $i) % 18) / 100), 2);
                $hybrid = in_array(($row['number'] + $i) % 8, [4, 7], true);
                $yield = 3.2 + (($row['number'] + $i * 3) % 18) / 10 + ($cycle['season'] === 'Dry' ? .4 : 0) + ($hybrid ? .6 : 0);
                if (($row['number'] + $i) % 37 === 0) {
                    $yield = 1.1 + (($row['number'] + $i) % 9) / 10;
                }
                $this->createSchedule($user, $farm, $row, $farmType, $cycle, $area, round(min(5.5, $yield), 2));
            }

            if (($row['number'] % 100) < 85) {
                $plant = $today->copy()->setDate($today->year, 6 + ($row['number'] % 2), 15 + ($row['number'] % 15));
                $area = round($row['area'] * (0.8 + ($row['number'] % 15) / 100), 2);
                $cycle = ['season' => 'Wet', 'plant' => $plant->toDateString(), 'harvest' => $plant->copy()->addDays(115)->toDateString()];
                $this->createSchedule($user, $farm, $row, $farmType, $cycle, $area, null);
                $this->report['current_crops']++;
            }
        }
    }

    private function createSchedule(User $user, Farm $farm, array $row, string $farmType, array $cycle, float $area, ?float $yield): void
    {
        $plant = Carbon::parse($cycle['plant'], 'Asia/Manila')->startOfDay();
        $harvested = $yield === null ? null : Carbon::parse($cycle['harvest'], 'Asia/Manila')->startOfDay();
        $varieties = ['NSIC Rc 222', 'Rc 216', 'Rc 160', 'Rc 402', 'Rc 480', 'Rc 506', 'SL-8H', 'Mestiso 20'];
        $variety = $varieties[$row['number'] % count($varieties)];
        $notes = '[demo_seed] Variety: '.$variety.'. Method: '.(($row['number'] % 5 === 0) ? 'direct seeding' : 'transplanting').'. '
            .'Land preparation: '.($plant->copy()->subDays(18)->toDateString()).'; fertilizer application: '.$plant->copy()->addDays(21)->toDateString().'. '
            .'Inputs: certified seed, complete fertilizer, urea; observation: '.(($row['number'] % 11 === 0) ? 'minor rice bug monitoring' : 'routine pest monitoring').'.';
        $attributes = [
            'user_id' => $user->id, 'farm_id' => $farm->id, 'planting_date' => $plant->toDateString(),
            'scheduled_harvest_date' => $cycle['harvest'], 'season' => $cycle['season'], 'farm_type' => $farmType,
            'barangay' => $row['barangay'], 'farm_area_hectares' => $area, 'notes' => $notes,
            'progress_status' => $yield === null ? 'Planted' : 'Harvested', 'predicted_yield' => $yield === null ? 4.0 : round($yield + .15, 2),
            'prediction_confidence' => $yield === null ? 82 : 86, 'prediction_source' => 'demo_seed',
            'actual_yield' => $yield, 'harvested_on' => $harvested?->toDateString(),
        ];
        $existing = FarmerPlantingSchedule::query()->where('user_id', $user->id)->where('farm_id', $farm->id)
            ->whereDate('planting_date', $plant->toDateString())->first();
        $schedule = $existing ?: new FarmerPlantingSchedule;
        $schedule->fill($attributes);
        $schedule->created_at = $plant;
        $schedule->updated_at = $harvested ?: $plant;
        $schedule->save();
        $this->report['schedules_created']++;
        if ($yield !== null) {
            $this->report['harvested']++;
            $this->report['total_production'] += $area * $yield;
        }
        $b = $row['barangay'];
        $this->report['barangays'][$b]['area'] = ($this->report['barangays'][$b]['area'] ?? 0) + $area;
        $this->report['barangays'][$b]['production'] = ($this->report['barangays'][$b]['production'] ?? 0) + ($yield ? $area * $yield : 0);
    }

    private function seedAggregateProductionRows(): void
    {
        foreach ($this->report['barangays'] as $barangay => $totals) {
            $row = RiceProduction::query()->where('barangay', $barangay)->where('season', 'Wet')->where('year', 2025)
                ->where('irrigation_type', 'Demo mixed')->first();
            if ($row) {
                continue;
            }
            $area = round($totals['area'], 2);
            $production = round($totals['production'], 2);
            RiceProduction::query()->create([
                'barangay' => $barangay, 'season' => 'Wet', 'irrigation_type' => 'Demo mixed',
                'yield_per_hectare' => $area > 0 ? round($production / $area, 2) : null,
                'area_hectares' => $area, 'area_harvested_hectares' => $area,
                'total_production' => $production, 'year' => 2025,
                'production_month' => '2025-10-01', 'remarks' => '[demo_seed] Aggregated from demo farmer schedules.',
                'created_at' => now('Asia/Manila')->setDate(2025, 10, 31)->endOfDay(),
                'updated_at' => now('Asia/Manila')->setDate(2025, 10, 31)->endOfDay(),
            ]);
            $this->report['productions_created']++;
        }
    }

    private function removeDemoActivity(): void
    {
        $ids = User::query()->where('email', 'like', '%@'.self::EMAIL_DOMAIN)->pluck('id');
        if ($ids->isEmpty()) return;
        FarmerPlantingSchedule::query()->whereIn('user_id', $ids)->where('prediction_source', 'demo_seed')->delete();
        Farm::query()->whereIn('user_id', $ids)->where(function ($query): void {
            $query->where('farm_name', 'like', 'Farm %')->orWhere('farm_name', 'like', 'Rice Farm %')->orWhere('farm_name', 'like', 'Demo'.' Rice Farm %');
        })->delete();
        RiceProduction::query()->where('remarks', 'like', '[demo_seed]%')->delete();
    }

    private function barangay(string $input, ?string $association = null): array
    {
        $raw = trim($input);
        if ($raw === '' || $raw === '—') {
            $fallback = $association ? LianFarmTypes::barangayForAssociation($association) : null;
            if ($fallback !== null) {
                $this->report['barangay_fixes']['(blank)'] = $fallback;

                return ['value' => $fallback, 'fixed' => true];
            }
        }
        $fixes = ['BALIBGO' => 'Balibago', 'SAN' => 'San Diego', 'BRGY.1' => 'Barangay 1 (Pob.)', 'BRGY. 1' => 'Barangay 1 (Pob.)', 'BARANGAY 4 POB' => 'Barangay 4 (Pob.)'];
        $candidate = $fixes[strtoupper($raw)] ?? $raw;
        $canonical = LianBarangays::canonicalName($candidate);
        if ($canonical === null) {
            $this->report['unmatched_barangays'][$raw] = true;

            return ['value' => $raw, 'fixed' => false];
        }
        if ($canonical !== $raw) $this->report['barangay_fixes'][$raw] = $canonical;

        return ['value' => $canonical, 'fixed' => $canonical !== $raw];
    }

    private function association(string $input): ?string
    {
        $key = $this->key($input);
        $aliases = [
            'BALIBAGO FA' => 'Balibago Farmers Association', 'CUACO IA' => "Cuaco Irrigator's Association",
            'BUNGAHAN BALDEO IA' => 'Bungahan Baldeo Irrigators Association Inc', 'MOLINO FA' => 'Molino-Resagwa Farmers Irrigators Association Inc',
            'LUMANG TUBIGAN FA' => 'Lumang Tubigan Farmers Association', 'SALUYSOY FA' => 'Saluysoy Farmers Association',
            'LUYAHAN FA' => 'Luyahan Farmers Association', 'MALARUHATAN FA' => 'Malaruhatan Farmers Association',
            'MATABUNGKAY FA' => 'Matabungkay Farmers Association', 'PRENZA BAGBAG BUNGAHAN FA' => 'Prenza Bagbag Bungahan Farmers Association',
            'CALACA FA' => 'Sitio Calaca Barangay Prenza Lian, Batangas Farmers Assn. Inc.', 'LAGULUAN FA' => 'Laguluan Spring Irrigators FA',
            'PRELA BASAKA IA' => 'Prenza Ladron Balanoy Sampalocan Calumpit Irrigators Association Inc.',
            'TAN AG FA' => 'Tan-ag Farmers Association', 'GINTONG PAG ASA FA' => 'Samahan ng Gintong Pagasa Ng Magsasaka Ng Lian, Inc.',
        ];
        $value = $aliases[$key] ?? null;
        if ($value === null || ! isset($this->organizations[$this->key($value)])) {
            $this->report['unmatched_associations'][$input] = true;

            return null;
        }

        return $value;
    }

    private function findOrganization(?string $name): ?FarmerOrganization
    {
        return $name ? ($this->organizations[$this->key($name)] ?? null) : null;
    }

    private function displayName(string $name): string
    {
        return Str::title(strtolower(trim(preg_replace('/\s+/', ' ', $name) ?: $name)));
    }

    private function key(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]+/', ' ', Str::ascii($value)) ?: '');
    }

    private function uniqueEmail(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $suffix = '';
        $last = strtolower((string) end($parts));
        if (in_array($last, ['jr', 'sr', 'i', 'ii', 'iii', 'iv', 'v'], true)) {
            $suffix = $last === 'jr' || $last === 'sr' ? '.'.$last : (string) ['i' => 1, 'ii' => 2, 'iii' => 3, 'iv' => 4, 'v' => 5][$last];
            array_pop($parts);
        }
        $surname = array_pop($parts) ?: 'farmer';
        $given = preg_replace('/[^a-z]/', '', strtolower(Str::ascii(implode('', $parts)))) ?: 'farmer';
        $base = $given.'.'.(preg_replace('/[^a-z]/', '', strtolower(Str::ascii($surname))) ?: 'farmer').$suffix;
        $email = $base.'@'.self::EMAIL_DOMAIN;
        $n = 2;
        $existing = User::query()->where('email', $email)->first();
        while (in_array($email, $this->currentEmails, true) || ($existing && strcasecmp((string) $existing->name, $name) !== 0)) {
            $email = $base.$n.'@'.self::EMAIL_DOMAIN;
            $n++;
            $existing = User::query()->where('email', $email)->first();
        }
        $this->currentEmails[] = $email;
        return $email;
    }

    private function writeOutputs(): void
    {
        Storage::disk('local')->makeDirectory('demo');
        $csv = "No,Full Name,Email,Password,RSBSA Number,Barangay,Association,Farm Area\n";
        foreach ($this->report['credentials'] as $row) {
            $csv .= implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', (string) ($v ?? '')).'"', $row))."\n";
        }
        Storage::disk('local')->put('demo/farmer-credentials.csv', $csv);

        if (class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            $sheet = (new \PhpOffice\PhpSpreadsheet\Spreadsheet())->getActiveSheet();
            $sheet->fromArray([['No', 'Full Name', 'Email', 'Password', 'RSBSA Number', 'Barangay', 'Association', 'Farm Area'], ...$this->report['credentials']]);
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($sheet->getParent());
            $writer->save(Storage::disk('local')->path('demo/farmer-credentials.xlsx'));
        }

        $lines = ['# Farmer demo seed report', '', '- Source records: '.$this->report['records'], '- Unique people/accounts: '.$this->report['unique_people'], '- Duplicate merges: '.$this->report['merged_duplicates'], '- Accounts created: '.$this->report['accounts_created'], '- Accounts updated: '.$this->report['accounts_updated'], '- Farms created/updated: '.$this->report['farms_created'], '- Planting schedules: '.$this->report['schedules_created'], '- Harvested schedules: '.$this->report['harvested'], '- Current crops: '.$this->report['current_crops'], '- Aggregate production rows: '.$this->report['productions_created'], '- Total farm area: '.number_format($this->report['total_area'], 2).' ha', '- Total demo production: '.number_format($this->report['total_production'], 2).' t', '', '## Data fixes', ''];
        foreach ($this->report['barangay_fixes'] as $from => $to) $lines[] = "- Barangay: `{$from}` → `{$to}`";
        $lines[] = ''; $lines[] = '## Unmatched values'; $lines[] = '- Barangays: '.implode(', ', array_keys($this->report['unmatched_barangays']) ?: ['none']); $lines[] = '- Associations: '.implode(', ', array_keys($this->report['unmatched_associations']) ?: ['none']);
        $lines[] = '- Existing RSBSA conflicts skipped: '.count($this->report['rsbsa_conflicts']);
        foreach ($this->report['rsbsa_conflicts'] as $conflict) $lines[] = "  - {$conflict['rsbsa']} ({$conflict['source_name']}); existing profile {$conflict['existing_profile_id']} was not modified.";
        $lines[] = ''; $lines[] = '## Duplicate merges'; foreach ($this->report['duplicate_names'] as $name => $count) $lines[] = "- {$name} ×{$count}; farm rows retained separately.";
        $lines[] = ''; $lines[] = '## Removal marker'; $lines[] = 'All generated schedules use `prediction_source=demo_seed`, generated farms use the `Farm {n}` name pattern, and aggregate production rows use `[demo_seed]` remarks.';
        $lines[] = ''; $lines[] = '## Recommended live-demo accounts';
        foreach (array_slice($this->report['credentials'], 0, 5) as $row) $lines[] = "- {$row[1]} — {$row[2]} / Password@123 ({$row[5]})";
        Storage::disk('local')->put('demo/seed-report.md', implode("\n", $lines)."\n");
    }
}
