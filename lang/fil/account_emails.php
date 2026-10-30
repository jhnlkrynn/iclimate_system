<?php

// Mga email tungkol sa sariling account ng tao (App\Services\Accounts\AccountEmails).
return [
    'hello' => 'Magandang araw, :name.',
    'thanks' => 'Salamat,',
    'team' => 'Municipal Agriculture Office ng Lian',

    'approved' => [
        'subject' => 'Aprubado na ang inyong iClimate account',
        'body' => 'Sinuri at inaprubahan ng Municipal Agriculture Office ng Lian ang inyong iClimate account noong :date.',
        'button' => 'Mag-log in sa iClimate',
        'can_do' => 'Ang maaari ninyong gawin ngayon:',
        'items' => [
            'Tingnan ang taya ng panahon sa inyong barangay',
            'Basahin ang mga payo sa pagtatanim mula sa MAO',
            'Alamin ang inaasahang petsa ng anihan',
            'Itala ang inyong mga bukid at plano sa Aking Bukid',
        ],
    ],

    'rejected' => [
        'subject' => 'Hindi naaprubahan ang inyong iClimate account',
        'body' => 'Sinuri ng Municipal Agriculture Office ng Lian ang inyong pagpaparehistro sa iClimate noong :date at hindi ito naaprubahan.',
        'reason' => 'Dahilan:',
        'next' => 'Ang maaari ninyong gawin:',
        'items' => [
            'Itama ang inyong mga detalye at magparehistro muli.',
            'O bumisita sa Municipal Agriculture Office para matulungan kayo.',
        ],
        'button' => 'Magparehistro muli',
    ],

    'deleted' => [
        'subject' => 'Binura na ang inyong iClimate account',
        'body' => 'Binura ang inyong iClimate account noong :date.',
        'reason' => 'Dahilan:',
        'details' => 'Iba pang detalye:',
        'mistake' => 'Kung sa tingin ninyo ay may pagkakamali, pakikontak ang Municipal Agriculture Office sa loob ng :days araw mula sa petsang iyon. Maibabalik nila ang inyong account.',
    ],

    'restored' => [
        'subject' => 'Naibalik na ang inyong iClimate account',
        'body' => 'Naibalik ang inyong iClimate account noong :date. Maaari na kayong mag-log in muli gamit ang inyong email at password.',
        'farms' => 'Naka-archive pa ang inyong mga bukid. Maaari ninyo o ng MAO na ibalik ang mga ito sa Aking Bukid.',
        'button' => 'Mag-log in sa iClimate',
    ],

    'test' => [
        'subject' => 'Test email ng iClimate',
        'body' => 'Ito ay test email mula sa iClimate. Kung nababasa ninyo ito, gumagana ang pagpapadala ng email.',
    ],
];
