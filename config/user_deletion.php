<?php

// Deleting a user account (IT, Users list). A deleted account is closed, not erased: the row stays so the person can be
// told why when they try to log in, and the account can be restored within restore_days.
//
// Each reason: show_panel (the person sees why on the login page), send_email (the person is emailed the reason) and
// requires_text (IT must explain, at least other_min_length characters). The words people see are in
// lang/{en,fil}/user_deletion.php under reasons.<key> (label: the dropdown; message: the panel and the email).
//
// Later: the Data Privacy Act may require removing personal data once a retention period has passed. Nothing is purged
// automatically yet; a purge of accounts deleted more than N days ago would go here (and in a scheduled command).
return [
    'restore_days' => (int) env('USER_DELETION_RESTORE_DAYS', 30),

    'other_min_length' => 10,

    'reasons' => [
        'farm_sold' => ['show_panel' => true, 'send_email' => true, 'requires_text' => false],
        'no_longer_farming' => ['show_panel' => true, 'send_email' => true, 'requires_text' => false],
        'moved_out' => ['show_panel' => true, 'send_email' => true, 'requires_text' => false],
        'duplicate' => ['show_panel' => true, 'send_email' => true, 'requires_text' => false],
        'invalid_registration' => ['show_panel' => true, 'send_email' => true, 'requires_text' => false],
        'misuse' => ['show_panel' => true, 'send_email' => true, 'requires_text' => false],
        'owner_request' => ['show_panel' => true, 'send_email' => true, 'requires_text' => false],
        // No panel and no email: whoever signs in is only told the account is no longer active.
        'deceased' => ['show_panel' => false, 'send_email' => false, 'requires_text' => false],
        'other' => ['show_panel' => true, 'send_email' => true, 'requires_text' => true],
    ],
];
