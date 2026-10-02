<?php

return [

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
