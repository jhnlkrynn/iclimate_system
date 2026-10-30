<?php

return [
    // The NSIC list that php artisan rice-varieties:import reads when no path is given.
    'import_path' => env('RICE_VARIETIES_IMPORT_PATH', database_path('data/nsic-registered_rice.xlsx')),

    // The same list as a PDF, shown as "View source list" (never imported: its columns are split across pages).
    'source_pdf' => database_path('data/nsic-registered_rice.pdf'),

    // Seedbed days before transplanting, when a farmer does not say (maturity is counted from sowing).
    'default_seedling_age_days' => 21,

    // Harvest window around the expected date when the variety's trials give no maturity range.
    'window_days' => 5,

    // Short names of the breeders in the NSIC list, for the IT table (the full name stays in a tooltip). Matched without
    // regard to case; "University of the Philippines - Los Baños" and "Los Banos" match too. Names not listed are cut short.
    'breeder_short' => [
        'Philippine Rice Research Institute' => 'PhilRice',
        'PhilRice' => 'PhilRice',
        'University of the Philippines[\s,-]*Los Ba[nñ]os' => 'UPLB',
        'International Rice Research Institute' => 'IRRI',
        'Central Luzon State University' => 'CLSU',
        'Bureau of Plant Industry' => 'BPI',
    ],

    // "Planted in my barangays": harvests counted as coming soon.
    'soon_days' => [14, 30],
];
