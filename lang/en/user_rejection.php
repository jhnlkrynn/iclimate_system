<?php

// Why a farmer sign-up was not approved (config/user_rejection.php). Shown to the MAO and in the farmer's email.
return [
    'reasons' => [
        'not_resident' => 'Not a resident or farmer of Lian',
        'unverified' => 'Information could not be verified',
        'rsbsa_mismatch' => 'RSBSA number does not match',
        'duplicate' => 'Duplicate account',
        'incomplete' => 'Incomplete or incorrect details',
        'other' => 'Other (please specify)',
    ],
];
