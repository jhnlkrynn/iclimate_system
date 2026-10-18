<?php

// Accounts and an advisory for the landing page browser tests (run by tests/Browser/serve.sh on its throwaway database).

use App\Models\Advisory;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (config('database.default') !== 'sqlite') {
    fwrite(STDERR, "Refusing to seed: the browser tests must run on their own SQLite database.\n");
    exit(1);
}

$accounts = [
    'farmer@browser.test' => User::ROLE_FARMER,
    'mao@browser.test' => User::ROLE_MAO,
    'it@browser.test' => User::ROLE_IT_EXPERT,
];

foreach ($accounts as $email => $role) {
    User::query()->create([
        'name' => $role.' Tester',
        'email' => $email,
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
        'role' => $role,
        'contact_number' => '09170000000',
        'barangay' => 'Prenza',
        'status' => User::STATUS_ACTIVE,
    ]);
}

Advisory::query()->create([
    'title' => 'Typhoon watch for Lian',
    'content' => 'Harvest mature palay before Friday and keep drainage canals clear.',
    'type' => 'Climate',
    'status' => Advisory::STATUS_PUBLISHED,
    'published_at' => now(),
    'posted_by' => User::query()->where('email', 'mao@browser.test')->value('id'),
]);
