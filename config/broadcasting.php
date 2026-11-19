<?php

return [

    'default' => env('BROADCAST_CONNECTION', 'null'),

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST', '127.0.0.1'),
                'port' => (int) env('REVERB_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'http'),
                'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
                'path' => env('REVERB_SERVER_PATH', ''),
            ],
            // Guzzle options for the server-to-Reverb HTTP call. Short limits keep a stopped or unreachable Reverb
            // from holding up the request that triggered the update (the failure is then retried from the queue).
            'client_options' => [
                'connect_timeout' => (float) env('REVERB_CONNECT_TIMEOUT', 2),
                'timeout' => (float) env('REVERB_REQUEST_TIMEOUT', 3),
            ],
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

    /*
    | Retry of realtime updates that could not be delivered immediately (App\Broadcasting\ResilientBroadcastManager).
    | The queue worker must listen to this queue, e.g. --queue=analytics,default,broadcasts. A retry that is still
    | waiting after fresh_minutes is dropped: by then pages have re-read the saved data by polling.
    */
    'retry' => [
        'enabled' => (bool) env('BROADCAST_RETRY_ENABLED', true),
        'connection' => env('BROADCAST_RETRY_CONNECTION'),
        'queue' => env('BROADCAST_RETRY_QUEUE', 'broadcasts'),
        'first_delay_seconds' => (int) env('BROADCAST_RETRY_FIRST_DELAY', 10),
        'tries' => (int) env('BROADCAST_RETRY_TRIES', 4),
        'backoff' => [15, 60, 180],
        'fresh_minutes' => (int) env('BROADCAST_RETRY_FRESH_MINUTES', 15),
        // After a delivery failure Reverb is treated as unavailable for this long (no immediate attempts).
        'circuit_seconds' => (int) env('BROADCAST_CIRCUIT_SECONDS', 30),
        // At most this many retries are queued per circuit window; the rest are dropped (pages poll).
        'max_queued_per_window' => (int) env('BROADCAST_RETRY_MAX_PER_WINDOW', 100),
    ],

];
