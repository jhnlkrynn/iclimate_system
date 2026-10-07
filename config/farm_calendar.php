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
];
