<?php

// MAO/IT – Kaye: mga salita sa mga pahina ng MAO na sumusunod sa wikang pinili ng MAO.
return [
    'planner' => ['title' => 'Planner ng MAO'],

    // MAO/IT – Naicel (2026-10-07): mga notification sa kampana para sa datos na hindi pa naidaragdag (MaoMissingDataNotifier).
    'missing' => [
        'open' => 'Magdagdag',
        'climate_month' => [
            'title' => 'Walang climate records ngayong buwan – magdagdag',
            'message' => 'Kailangan ng chart ng ulan at temperatura at ng mga forecast ang climate record ng buwang ito.',
        ],
        'production_season' => [
            'title' => 'Walang production records ngayong season – magdagdag',
            'message' => 'Idagdag ang ani ng palay ngayong season para manatiling bago ang mga bilang ng ani.',
        ],
        'advisories' => [
            'title' => 'Walang aktibong advisory – gumawa',
            'message' => 'Walang kasalukuyang advisory ang MAO para sa mga magsasaka. Mag-post sa pahina ng Advisories.',
        ],
        'planner' => [
            'title' => 'Walang paparating na gawain sa Planner ng MAO – magdagdag',
            'message' => 'Iplano ang susunod na field visit, training o pamamahagi para makita ng mga magsasaka.',
        ],
    ],
];
