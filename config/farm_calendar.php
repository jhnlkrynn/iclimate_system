<?php

/*
|--------------------------------------------------------------------------
| Farm calendar
|--------------------------------------------------------------------------
|
| The recommended rice planting window of each season, shown on the Calendar
| as a planning guide (month-day, inclusive). Seasons follow
| App\Support\RiceSeason: Wet = May to October, Dry = November to April.
|
| IMPORTANT: these dates are working defaults. They MUST be checked and
| signed off by the Municipal Agriculture Office (MAO) of Lian before they
| are treated as official advice for farmers.
*/

return [
    'planting_windows' => [
        'Wet' => ['from' => '06-01', 'until' => '07-31'],
        'Dry' => ['from' => '11-15', 'until' => '12-31'],
    ],

    /*
    | Harvest timing (panel revision 1.3). A harvest more than `on_time_tolerance_days` before the expected harvest
    | date is "Early", more than that after it is "Late"; otherwise "On time". An early or late harvest needs a reason.
    */
    'harvest_timing' => [
        'on_time_tolerance_days' => (int) env('HARVEST_ON_TIME_TOLERANCE_DAYS', 3),
        'reasons' => [
            'typhoon' => 'Typhoon / strong winds',
            'flooding' => 'Heavy rain / flooding',
            'drought' => 'Drought',
            'pest_disease' => 'Pest or disease infestation',
            'matured_early' => 'Crop matured early',
            'labor_machinery' => 'Labor or machinery availability',
            'market_timing' => 'Market / price timing',
            'other' => 'Other',
        ],
    ],
];
