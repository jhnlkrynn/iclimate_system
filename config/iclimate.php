<?php

/*
|--------------------------------------------------------------------------
| iClimate application settings that used to be read with env() in app code
|--------------------------------------------------------------------------
|
| env() only works while the config is not cached. Once `php artisan
| config:cache` is on (production), env() outside config files returns null,
| so anything the app needs from .env is read here and used via config().
*/

return [
    /* Python used for the ML scripts. Empty: the app looks for the project's virtualenv. */
    'python_binary' => env('PYTHON_BINARY') ?: null,

    /* Password of the demo accounts made by DevelopmentDemoSeeder. No fallback: a password is never written in code. */
    'default_account_password' => env('ICLIMATE_DEFAULT_ACCOUNT_PASSWORD'),

    /*
    | First password of farmer accounts imported by FarmerMasterlistSeeder. Empty: every imported account gets
    | its own random password and IT sets a real one (Users > Edit) when the farmer first asks to sign in.
    */
    'farmer_account_password' => env('ICLIMATE_FARMER_ACCOUNT_PASSWORD'),

    /* Password given to it@gmail.com and mao@gmail.com by StaffLoginAccountSeeder. Empty: those accounts are not seeded. */
    'staff_account_password' => env('ICLIMATE_STAFF_ACCOUNT_PASSWORD'),

    /*
    | Where iClimate operates. The single source is App\Support\LianLocation;
    | services.php reads the same constants. The timezone here is the BUSINESS
    | timezone (what "today" means). Stored timestamps stay UTC.
    */
    'location' => [
        'municipality' => \App\Support\LianLocation::MUNICIPALITY,
        'province' => \App\Support\LianLocation::PROVINCE,
        'country' => \App\Support\LianLocation::COUNTRY,
        'latitude' => \App\Support\LianLocation::LATITUDE,
        'longitude' => \App\Support\LianLocation::LONGITUDE,
    ],
    'timezone' => \App\Support\LianLocation::TIMEZONE,

    /*
    | Keep seeded sample rows (DatabaseSeeder / model factories) and impossible
    | climate rows out of dashboards and statistics, so they are never presented
    | as verified data. Nothing is deleted — see `php artisan iclimate:data-audit`
    | and `iclimate:purge-sample-data` for the reviewed clean-up. Set to false
    | only for automated tests that build their own factory data.
    */
    'exclude_sample_data' => (bool) env('ICLIMATE_EXCLUDE_SAMPLE_DATA', true),

];
