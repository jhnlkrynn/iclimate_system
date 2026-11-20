<?php

return [
    // Strict Content-Security-Policy with a per-response script nonce (App\Http\Middleware\SecurityHeaders).
    // false = the previous loose policy; a rollback switch only, for a page that turns out to break.
    'csp_strict' => filter_var(env('SECURITY_CSP_STRICT', true), FILTER_VALIDATE_BOOL),
];
