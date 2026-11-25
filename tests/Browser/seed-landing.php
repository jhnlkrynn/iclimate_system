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
    'reviewer@browser.test' => User::ROLE_MAO,
];

foreach ($accounts as $email => $role) {
    User::query()->create([
        'name' => $role.' Tester',
        'email' => $email,
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
        'role' => $role,
        'is_mao_head' => $email === 'mao@browser.test',
        'contact_number' => '09170000000',
        'barangay' => 'Prenza',
        'status' => User::STATUS_ACTIVE,
        'privacy_agreed_at' => now(),
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
    'role' => User::ROLE_FARMER, 'contact_number' => '09170000001', 'barangay' => 'Kapito', 'status' => User::STATUS_ACTIVE, 'privacy_agreed_at' => now(),
]);
foreach ([
    ['Saluysoy Farmers Association', 'Kapito', 'Irrigated'],
    ['Kapito Rainfed Growers', 'Kapito', 'Rainfed'],
    ['Prenza Bagbag Bungahan Farmers Association', 'Prenza', 'Irrigated'],
] as [$name, $barangay, $type]) {
    \App\Models\FarmerOrganization::query()->create(['name' => $name, 'barangay' => $barangay, 'farm_type' => $type]);
}

// Account deletion tests (tests/Browser/account-deletion.spec.mjs): one farmer to delete per screen size. A real-looking
// domain, so the dialog says an email will be sent (the test server only logs mail: MAIL_MAILER=log).
foreach (['desktop-1280' => 'Delia Desktop', 'mobile-375' => 'Mona Mobile'] as $project => $name) {
    User::query()->create([
        'name' => $name, 'email' => 'delete.'.$project.'@lian-farmers.ph', 'email_verified_at' => now(), 'password' => Hash::make('password'),
        'role' => User::ROLE_FARMER, 'contact_number' => '09170000002', 'barangay' => 'Prenza', 'status' => User::STATUS_ACTIVE, 'privacy_agreed_at' => now(),
    ]);
}

// RC2 workflow fixtures: isolated only, per project so state cannot leak between viewports.
foreach (['desktop-1280', 'mobile-375', 'tablet-768'] as $project) {
    foreach (['chairman' => User::STATUS_APPROVED, 'pending' => User::STATUS_PENDING, 'rejected' => User::STATUS_REJECTED, 'recovery' => User::STATUS_APPROVED] as $kind => $status) {
        User::create(['name' => ucfirst($kind).' '.$project, 'email' => $kind.'-'.$project.'@browser.test',
            'password' => Hash::make(ucfirst($kind).'-'.$project.'!23'), 'role' => User::ROLE_FARMER,
            'status' => $status, 'barangay' => 'Prenza', 'contact_number' => '09170000003',
            'email_verified_at' => now(), 'privacy_agreed_at' => now()]);
    }
    \App\Models\FarmerOrganization::create(['name' => 'Workflow '.$project, 'barangay' => 'Balibago', 'farm_type' => 'Rainfed']);
}

if (getenv('SEED_FORECAST_FIXTURE') === '1') {
    $today = \Carbon\CarbonImmutable::now('Asia/Manila')->startOfDay();
    $daily = array_map(function ($i) use ($today) {
        $isDry = ($i % 3 === 1);
        $isStorm = ($i === 0 || $i === 5);

        return [
            'date' => $today->addDays($i)->toDateString(),
            'weather_code' => $isStorm ? 95 : ($isDry ? 1 : 61),
            'condition' => $isStorm ? 'Thunderstorm' : ($isDry ? 'Mainly clear' : 'Light rain'),
            'rainfall' => $isDry ? 0.0 : ($isStorm ? 1.6 : 3.4),
            'precipitation_probability' => $isDry ? 12 : ($isStorm ? 97 : 65),
            'temperature_max' => 31.0,
            'temperature_min' => 24.0,
            'wind_speed_max' => $isStorm ? 20.5 : 11.2,
        ];
    }, range(0, 15));

    $records = [];
    foreach (\App\Support\LianBarangays::all() as $brgy) {
        $records[$brgy] = [
            'barangay' => $brgy,
            'temperature_c' => 29.0,
            'fetched_at' => now()->toIso8601String(),
            'observed_at' => now()->toIso8601String(),
            'daily' => $daily,
        ];
    }
    \Illuminate\Support\Facades\Cache::forever(\App\Services\Weather\LianBarangayWeatherService::STORE_KEY, [
        'records' => $records,
        'status' => [],
    ]);
}

if (getenv('API_ML_ACCEPTANCE') === '1') {
    // Explicit diagnostic fixtures, not operational weather observations or accuracy evidence.
    for ($i=1;$i<=6;$i++) \App\Models\ClimateRecord::create(['record_date'=>now()->subMonthsNoOverflow($i)->startOfMonth()->toDateString(),
        'rainfall'=>150+$i*10,'temperature'=>27+$i*.2,'humidity'=>78,'wind_speed'=>8,'season'=>'Wet','source'=>'API ML ACCEPTANCE FIXTURE']);
    foreach (['Wet','Dry'] as $season) {
        $month=\App\Models\YieldPredictionRequest::targetMonthFor($season);
        \App\Models\MonthlyWeatherPrediction::create(['prediction_year'=>$month->year,'prediction_month'=>$month->month,
            'weather_model'=>\App\Services\MachineLearning\MonthlyWeatherRandomForest::MODEL_VERSION,'status'=>'ready',
            'rainfall'=>180,'tmax'=>33,'tmin'=>25,'temperature_average'=>29,'humidity'=>78,'wind_speed'=>8,
            'generated_at'=>now(),'expires_at'=>now()->addDay(),'metadata'=>['source_name'=>'API ML ACCEPTANCE FIXTURE']]);
    }
}
