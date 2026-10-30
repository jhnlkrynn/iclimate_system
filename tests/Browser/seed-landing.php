<?php

// Accounts and an advisory for the landing page browser tests (run by tests/Browser/serve.sh on its throwaway database).

use App\Models\Advisory;
use App\Models\FarmerPlantingSchedule;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (config('database.default') !== 'sqlite') {
    fwrite(STDERR, "Refusing to seed: the browser tests must run on their own SQLite database.\n");
    exit(1);
}

$accounts = [
    'farmer@browser.test' => User::ROLE_FARMER,
    'mao@browser.test' => User::ROLE_MAO,
    'it@browser.test' => User::ROLE_IT_EXPERT,
];

foreach ($accounts as $email => $role) {
    User::query()->create([
        'name' => $role.' Tester',
        'email' => $email,
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
        'role' => $role,
        'contact_number' => '09170000000',
        'barangay' => 'Prenza',
        'status' => User::STATUS_ACTIVE,
    ]);
}

Advisory::query()->create([
    'title' => 'Typhoon watch for Lian',
    'content' => 'Harvest mature palay before Friday and keep drainage canals clear.',
    'type' => 'Climate',
    'status' => Advisory::STATUS_PUBLISHED,
    'published_at' => now(),
    'posted_by' => User::query()->where('email', 'mao@browser.test')->value('id'),
]);

// Rice Varieties smoke test (tests/Browser/rice-varieties.spec.mjs): five real rows of the NSIC list.
app(\App\Services\RiceVarieties\RiceVarietyImporter::class)->import(__DIR__.'/../fixtures/nsic-rice-sample.xlsx');

FarmerPlantingSchedule::query()->create([
    'user_id' => User::query()->where('email', 'farmer@browser.test')->value('id'),
    'planting_date' => '2026-07-01',
    'scheduled_harvest_date' => '2026-10-24',
    'season' => 'Wet',
    'farm_type' => 'Irrigated',
    'barangay' => 'Prenza',
    'farm_area_hectares' => 1.5,
]);

// Farm association tests (tests/Browser/farm-associations.spec.mjs): their own farmer, and associations in Kapito (one
// Irrigated, one Rainfed) and Prenza (Irrigated only). Luyahan has none.
User::query()->create([
    'name' => 'Farm Tester', 'email' => 'farms@browser.test', 'email_verified_at' => now(), 'password' => Hash::make('password'),
    'role' => User::ROLE_FARMER, 'contact_number' => '09170000001', 'barangay' => 'Kapito', 'status' => User::STATUS_ACTIVE,
]);
foreach ([
    ['Saluysoy Farmers Association', 'Kapito', 'Irrigated'],
    ['Kapito Rainfed Growers', 'Kapito', 'Rainfed'],
    ['Prenza Bagbag Bungahan Farmers Association', 'Prenza', 'Irrigated'],
] as [$name, $barangay, $type]) {
    \App\Models\FarmerOrganization::query()->create(['name' => $name, 'barangay' => $barangay, 'farm_type' => $type]);
}
