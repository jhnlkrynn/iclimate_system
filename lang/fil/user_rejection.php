<?php

// Dahilan kung bakit hindi naaprubahan ang pagpaparehistro (config/user_rejection.php).
return [
    'reasons' => [
        'not_resident' => 'Hindi residente o magsasaka ng Lian',
        'unverified' => 'Hindi ma-verify ang impormasyon',
        'rsbsa_mismatch' => 'Hindi tugma ang RSBSA number',
        'duplicate' => 'Dobleng account',
        'incomplete' => 'Kulang o maling detalye',
        'other' => 'Iba pa',
    ],
];
