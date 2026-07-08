<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'weather_api' => [
        'enabled' => env('WEATHER_API_ENABLED', false),
        'key' => env('OPENWEATHER_API_KEY'),
        'timeout' => env('WEATHER_API_TIMEOUT', 8),
        'location_name' => env('WEATHER_API_LOCATION_NAME', 'Lian, Batangas'),
        'latitude' => env('WEATHER_API_LATITUDE', 14.04),
        'longitude' => env('WEATHER_API_LONGITUDE', 120.65),
        'timezone' => env('WEATHER_API_TIMEZONE', env('APP_TIMEZONE', 'Asia/Manila')),
        'forecast_days' => env('WEATHER_API_FORECAST_DAYS', 5),
    ],

    'farming_ai' => [
        'url' => env('FARMING_AI_API_URL', 'http://127.0.0.1:5001'),
        'timeout' => env('FARMING_AI_API_TIMEOUT', 5),
    ],

];
