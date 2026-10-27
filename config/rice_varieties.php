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

    // "Planted in my barangays": harvests counted as coming soon.
    'soon_days' => [14, 30],
];
