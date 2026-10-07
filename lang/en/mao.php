<?php

// MAO/IT – Kaye: words on the MAO pages that follow the MAO's saved language.
return [
    'planner' => ['title' => 'MAO Planner'],

    // MAO/IT – Naicel (2026-10-07): bell notifications for data not added yet (App\Services\Mao\MaoMissingDataNotifier).
    'missing' => [
        'open' => 'Add now',
        'climate_month' => [
            'title' => 'No climate records this month – add one',
            'message' => 'The rainfall and temperature chart and the forecasts need this month\'s climate record.',
        ],
        'production_season' => [
            'title' => 'No production records this season – add one',
            'message' => 'Add the rice production of this season so the yield figures stay up to date.',
        ],
        'advisories' => [
            'title' => 'No advisories in effect – write one',
            'message' => 'Farmers have no current advisory from the MAO. Post one on the Advisories page.',
        ],
        'planner' => [
            'title' => 'No upcoming activities in the MAO Planner – add one',
            'message' => 'Plan the next field visit, training or distribution so farmers can see it.',
        ],
    ],
];
