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
        'timeout' => env('WEATHER_API_TIMEOUT', 8),
        'location_name' => env('WEATHER_API_LOCATION_NAME', 'Lian, Batangas'),
        'latitude' => env('WEATHER_API_LATITUDE', 14.04),
        'longitude' => env('WEATHER_API_LONGITUDE', 120.65),
        'timezone' => env('WEATHER_API_TIMEZONE', env('APP_TIMEZONE', 'Asia/Manila')),
        'forecast_days' => env('WEATHER_API_FORECAST_DAYS', 5),
        'refresh_minutes' => env('WEATHER_API_REFRESH_MINUTES', 10),
        'realtime' => env('WEATHER_API_REALTIME', true),
    ],

    'weather' => [
        'provider' => env('WEATHER_PROVIDER', 'weatherapi'),
        'latitude' => env('ICLIMATE_WEATHER_LAT', env('WEATHER_LATITUDE', env('ICLIMATE_LATITUDE', 14.033))),
        'longitude' => env('ICLIMATE_WEATHER_LON', env('WEATHER_LONGITUDE', env('ICLIMATE_LONGITUDE', 120.650))),
        'timezone' => env('WEATHER_TIMEZONE', env('ICLIMATE_TIMEZONE', 'Asia/Manila')),
        'location_name' => env('WEATHER_LOCATION_NAME', env('ICLIMATE_LOCATION_NAME', 'Lian, Batangas')),
        'cache_minutes' => env('WEATHER_CACHE_MINUTES', 1),
        'forecast_days' => env('WEATHER_FORECAST_DAYS', 7),
        'poll_seconds' => env('WEATHER_POLL_SECONDS', 60),
    ],

    'weatherapi' => [
        'key' => env('WEATHERAPI_KEY'),
        'base_url' => env('WEATHERAPI_BASE_URL', 'https://api.weatherapi.com/v1'),
        'timeout' => env('WEATHERAPI_TIMEOUT', 8),
    ],

    'open_meteo' => [
        'base_url' => env('OPEN_METEO_BASE_URL', 'https://api.open-meteo.com/v1'),
        'latitude' => env('ICLIMATE_LATITUDE', 14.033),
        'longitude' => env('ICLIMATE_LONGITUDE', 120.650),
        'timezone' => env('ICLIMATE_TIMEZONE', 'Asia/Manila'),
        'location_name' => env('ICLIMATE_LOCATION_NAME', 'Lian, Batangas'),
        'timeout' => env('OPEN_METEO_TIMEOUT', 10),
        'refresh_minutes' => env('OPEN_METEO_REFRESH_MINUTES', 10),
        'forecast_days' => env('OPEN_METEO_FORECAST_DAYS', 16),
        'realtime' => env('OPEN_METEO_REALTIME', true),
    ],

    'accuweather' => [
        'key' => env('ACCUWEATHER_API_KEY'),
        'timeout' => env('ACCUWEATHER_TIMEOUT', 8),
    ],

    'pagasa' => [
        'enabled' => env('PAGASA_ADVISORIES_ENABLED', true),
        'regional_forecast_url' => env('PAGASA_REGIONAL_FORECAST_URL', 'https://bagong.pagasa.dost.gov.ph/regional-forecast/ncrprsd'),
        'regional_forecast_urls' => array_filter(array_map('trim', explode(',', env(
            'PAGASA_REGIONAL_FORECAST_URLS',
            'https://www.pagasa.dost.gov.ph/regional-forecast/ncrprsd,https://bagong.pagasa.dost.gov.ph/regional-forecast/slprsd,https://bagong.pagasa.dost.gov.ph/ten-day-regional-agri-weather'
        )))),
        'weekly_outlook_url' => env('PAGASA_WEEKLY_OUTLOOK_URL', 'https://pagasa.dost.gov.ph/weather/weather-outlook-weekly'),
        'timeout' => env('PAGASA_TIMEOUT', 12),
        'cache_minutes' => env('PAGASA_CACHE_MINUTES', 10),
        'realtime' => env('PAGASA_REALTIME', true),
        'location_keywords' => array_filter(array_map('trim', explode(',', env('PAGASA_LOCATION_KEYWORDS', 'Lian,Batangas')))),
    ],

    'farming_ai' => [
        'url' => env('FARMING_AI_API_URL', 'http://127.0.0.1:5001'),
        'timeout' => env('FARMING_AI_API_TIMEOUT', 5),
    ],

    'groq' => [
        'enabled' => env('GROQ_ENABLED', env('GROQ_API_KEY') !== null),
        'key' => env('GROQ_API_KEY'),
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
        'timeout' => env('GROQ_TIMEOUT', 12),
    ],

];
