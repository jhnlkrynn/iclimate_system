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

    // Signing up again with the email of a closed account (no reason given there).
    'email_closed' => 'Ang email na ito ay para sa isang iClimate account na isinara na. Pakikontak ang Municipal Agriculture Office ng Lian para maibalik ito.',

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
    // Audit 2026-11-23 (M-6): the delete-account window in Filipino.
    'modal' => [
        'title' => 'Burahin ang account',
        'intro' => 'Isasara ang account, hindi buburahin ang datos. Hindi na makakapag-log in ang tao, at ia-archive ang kanyang mga bukid. Maibabalik ito sa loob ng :days araw.',
        'name' => 'Pangalan',
        'role' => 'Tungkulin',
        'barangay' => 'Barangay',
        'email' => 'Nakarehistrong email',
        'reason' => 'Dahilan',
        'reason_choose' => 'Pumili ng dahilan',
        'other' => 'Pakisabi kung ano',
        'other_hint' => 'Hindi bababa sa :min na titik. Makikita ito ng tao.',
        'note' => 'Panloob na tala (opsyonal)',
        'note_hint' => 'Ang MAO Head lang ang makakakita nito.',
        'preview' => 'Ang makikita ng tao',
        'preview_none' => 'Walang ipapakita o ipapadala. Kapag may nag-sign in, ito lang ang makikita: ":message"',
        'email_to' => 'Magpapadala ng email sa :email',
        'no_email' => 'Walang ipapadalang email (walang wastong email na nakatala)',
        'mao_head' => 'Ito ang account ng Municipal Agriculturist (MAO head). Mawawalan ng head account ang MAO hanggang may maitalagang iba.',
        'confirm' => 'I-type ang :name para kumpirmahin',
        'submit' => 'Burahin ang account',
        'cancel' => 'Kanselahin',
        'done' => 'Nabura na ang account ni :name.',
        'restored' => 'Naibalik na ang account ni :name.',
        'restore' => 'Ibalik ang account',
        'restore_confirm' => 'Ibalik ang account ni :name? Makakapag-log in na ulit siya; mananatiling naka-archive ang kanyang mga bukid hanggang ibalik niya o ng MAO.',
        'restore_closed' => 'Tapos na ang panahon ng pagbabalik',
        'archived_hint' => 'May :count naka-archive na bukid ang magsasakang ito. Maibabalik niya ito sa My Farms.|May :count naka-archive na bukid ang magsasakang ito. Maibabalik niya ang mga ito sa My Farms.',
    ],
];
