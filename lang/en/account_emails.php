<?php

// Emails about a person's own account (App\Services\Accounts\AccountEmails). Plain, respectful words; the office's
// contact details are in the footer of every email (resources/views/vendor/mail).
return [
    'hello' => 'Hello, :name.',
    'thanks' => 'Thank you,',
    'team' => 'Municipal Agriculture Office of Lian',

    'approved' => [
        'subject' => 'Your iClimate account is approved',
        'body' => 'Your iClimate account was reviewed and approved by the Municipal Agriculture Office of Lian on :date.',
        'button' => 'Log in to iClimate',
        'can_do' => 'What you can do now:',
        'items' => [
            'See the weather forecast for your barangay',
            'Read planting advisories from the MAO',
            'Get your expected harvest dates',
            'Keep your farms and plans in My Farm',
        ],
    ],

    'rejected' => [
        'subject' => 'Your iClimate account was not approved',
        'body' => 'The Municipal Agriculture Office of Lian reviewed your iClimate sign-up on :date and could not approve it.',
        'reason' => 'Reason:',
        'note' => 'Note from the office:',
        'next' => 'What you can do:',
        'items' => [
            'Correct your details and register again.',
            'Or visit the Municipal Agriculture Office so they can help you.',
        ],
        'button' => 'Register again',
    ],

    'deleted' => [
        'subject' => 'Your iClimate account has been deleted',
        'body' => 'Your iClimate account was deleted on :date.',
        'reason' => 'Reason:',
        'details' => 'More details:',
        'mistake' => 'If you think this is a mistake, please contact the Municipal Agriculture Office within :days days of that date. They can bring your account back.',
    ],

    'restored' => [
        'subject' => 'Your iClimate account has been restored',
        'body' => 'Your iClimate account was restored on :date. You can log in again with your email and password.',
        'farms' => 'Your farms stay archived for now. You or the MAO can bring them back in My Farm.',
        'button' => 'Log in to iClimate',
    ],

    'test' => [
        'subject' => 'iClimate test email',
        'body' => 'This is a test email from iClimate. If you can read this, sending email works.',
    ],
];
