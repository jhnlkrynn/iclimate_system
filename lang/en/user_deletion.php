<?php

// Deleting a user account (IT) and what the person is told. Which reasons exist, and which show the panel or send an
// email: config/user_deletion.php. Words for the person: plain, respectful, never blaming.
return [
    'reasons' => [
        'farm_sold' => [
            'label' => 'Farm was sold or transferred',
            'message' => 'Your account was closed because your farm was sold or transferred to someone else.',
        ],
        'no_longer_farming' => [
            'label' => 'No longer farming',
            'message' => 'Your account was closed because you are no longer farming.',
        ],
        'moved_out' => [
            'label' => 'Moved out of Lian',
            'message' => 'Your account was closed because you have moved out of Lian.',
        ],
        'duplicate' => [
            'label' => 'Duplicate account',
            'message' => 'Your account was closed because you have another iClimate account. Please use that account.',
        ],
        'invalid_registration' => [
            'label' => 'Fake or invalid registration',
            'message' => 'Your account was closed because the details given when signing up could not be confirmed.',
        ],
        'misuse' => [
            'label' => 'Misuse of the system / violated the terms of use',
            'message' => 'Your account was closed because it was used in a way that is not allowed under the iClimate terms of use.',
        ],
        'owner_request' => [
            'label' => 'Requested by the account owner',
            'message' => 'Your account was closed because you asked for it to be deleted.',
        ],
        'deceased' => [
            'label' => 'Deceased',
            'message' => '',
        ],
        'other' => [
            'label' => 'Other (please specify)',
            'message' => 'Your account was closed by the Municipal Agriculture Office.',
        ],
    ],

    // Someone signs in to an account closed as Deceased (no panel, no reason).
    // Signing up again with the email of a closed account (no reason given there).
    'email_closed' => 'This email belongs to an iClimate account that was closed. Please contact the Municipal Agriculture Office of Lian to restore it.',

    'no_longer_active' => 'This account is no longer active. Please contact the Municipal Agriculture Office.',

    // The panel after a correct email and password for a deleted account.
    'panel' => [
        'title' => 'Your iClimate account was deleted',
        'reason' => 'Why',
        'details' => 'More details',
        'date' => 'Date deleted',
        'contact_title' => 'Need help?',
        'contact' => 'If you think this is a mistake, please visit or contact the Municipal Agriculture Office of Lian.',
        'address' => 'Address',
        'email' => 'Email',
        'hours' => 'Office hours',
        'home' => 'Back to home',
    ],

    // IT: the Delete account dialog and the Users list.
    'modal' => [
        'title' => 'Delete account',
        'intro' => 'The account is closed, not erased. The person can no longer log in, and their farms are archived. You can restore it within :days days.',
        'name' => 'Name',
        'role' => 'Role',
        'barangay' => 'Barangay',
        'email' => 'Registered email',
        'reason' => 'Reason',
        'reason_choose' => 'Choose a reason',
        'other' => 'Please specify',
        'other_hint' => 'At least :min characters. The person will see this.',
        'note' => 'Internal note (optional)',
        'note_hint' => 'Only IT can see this.',
        'preview' => 'What the person will see',
        'preview_none' => 'Nothing is shown or sent. If someone signs in, they only see: ":message"',
        'email_to' => 'Email will be sent to :email',
        'no_email' => 'No email will be sent (no valid email on file)',
        'mao_head' => 'This is the Municipal Agriculturist (MAO head) account. The MAO will lose its head account until another one is set.',
        'confirm' => 'Type :name to confirm',
        'submit' => 'Delete account',
        'cancel' => 'Cancel',
        'done' => ':name\'s account was deleted.',
        'restored' => ':name\'s account was restored.',
        'restore' => 'Restore account',
        'restore_confirm' => 'Restore :name\'s account? They can log in again; their farms stay archived until they or the MAO restore them.',
        'restore_closed' => 'Restore period ended',
        'archived_hint' => 'This farmer has :count archived farm. They can restore it in My Farms.|This farmer has :count archived farms. They can restore them in My Farms.',
    ],
];
