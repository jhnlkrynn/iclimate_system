<?php

/*
|--------------------------------------------------------------------------
| iClimate application settings that used to be read with env() in app code
|--------------------------------------------------------------------------
|
| env() only works while the config is not cached. Once `php artisan
| config:cache` is on (production), env() outside config files returns null,
| so anything the app needs from .env is read here and used via config().
*/

return [
    /* Python used for the ML scripts. Empty: the app looks for the project's virtualenv. */
    'python_binary' => env('PYTHON_BINARY') ?: null,

    /* Password shown next to the demo accounts on the login page. */
    'default_account_password' => env('ICLIMATE_DEFAULT_ACCOUNT_PASSWORD', 'iClimate2026!'),
];
