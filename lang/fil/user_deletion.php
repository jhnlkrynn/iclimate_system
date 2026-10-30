<?php

// Pagbura ng account (IT) at ang sinasabi sa tao. Ang mga dahilan at kung alin ang may panel o email: config/user_deletion.php.
// Ang dialog ng IT ay nasa English (lang/en).
return [
    'reasons' => [
        'farm_sold' => [
            'label' => 'Naibenta o nailipat ang bukid',
            'message' => 'Isinara ang inyong account dahil naibenta o nailipat na sa iba ang inyong bukid.',
        ],
        'no_longer_farming' => [
            'label' => 'Hindi na nagsasaka',
            'message' => 'Isinara ang inyong account dahil hindi na kayo nagsasaka.',
        ],
        'moved_out' => [
            'label' => 'Lumipat na palabas ng Lian',
            'message' => 'Isinara ang inyong account dahil lumipat na kayo palabas ng Lian.',
        ],
        'duplicate' => [
            'label' => 'Dobleng account',
            'message' => 'Isinara ang inyong account dahil may iba pa kayong iClimate account. Pakigamit ang account na iyon.',
        ],
        'invalid_registration' => [
            'label' => 'Peke o hindi wastong pagpaparehistro',
            'message' => 'Isinara ang inyong account dahil hindi makumpirma ang mga detalyeng ibinigay sa pagpaparehistro.',
        ],
        'misuse' => [
            'label' => 'Maling paggamit ng sistema / paglabag sa mga tuntunin',
            'message' => 'Isinara ang inyong account dahil nagamit ito sa paraang hindi pinapayagan ng mga tuntunin ng iClimate.',
        ],
        'owner_request' => [
            'label' => 'Hiniling ng may-ari ng account',
            'message' => 'Isinara ang inyong account dahil hiniling ninyong burahin ito.',
        ],
        'deceased' => [
            'label' => 'Pumanaw',
            'message' => '',
        ],
        'other' => [
            'label' => 'Iba pa (pakisulat)',
            'message' => 'Isinara ng Municipal Agriculture Office ang inyong account.',
        ],
    ],

    'no_longer_active' => 'Hindi na aktibo ang account na ito. Pakikontak ang Municipal Agriculture Office.',

    'panel' => [
        'title' => 'Binura na ang inyong iClimate account',
        'reason' => 'Dahilan',
        'details' => 'Iba pang detalye',
        'date' => 'Petsa ng pagbura',
        'contact_title' => 'Kailangan ng tulong?',
        'contact' => 'Kung sa tingin ninyo ay may pagkakamali, pakibisita o kontakin ang Municipal Agriculture Office ng Lian.',
        'address' => 'Address',
        'email' => 'Email',
        'hours' => 'Oras ng opisina',
        'home' => 'Bumalik sa home',
    ],
];
