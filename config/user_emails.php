<?php

// Emails about a person's own account (approved, not approved, deleted, restored): App\Services\Accounts\AccountEmails.
// They always go to the account's own registered email (users.email); the sender is MAIL_FROM_ADDRESS.
return [
    // Addresses on these domains are placeholders or test accounts: nothing is sent and the user's detail page says
    // "No valid email". A subdomain counts too (mail.example.com).
    'placeholder_domains' => [
        'iclimate.test',
        'example.com',
        'example.org',
        'example.net',
        'test',
        'invalid',
        'localhost',
    ],

    // Queue the emails (QUEUE_CONNECTION) when a queue worker runs. Off: they are sent at once, while the page saves.
    // The shared database's queue is not always processed, so this stays off unless a worker is known to run.
    'queue' => (bool) env('USER_EMAILS_QUEUE', false),
];
