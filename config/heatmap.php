<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Lian Heat Map Risk Thresholds
    |--------------------------------------------------------------------------
    |
    | These thresholds drive the centralized heat-map risk calculation. Values
    | are intentionally conservative: missing inputs are not converted to risk
    | scores, and the UI displays "Insufficient data" instead.
    |
    */

    'refresh_seconds' => env('HEATMAP_REFRESH_SECONDS', 5),

    'weather_cache_minutes' => env('HEATMAP_WEATHER_CACHE_MINUTES', env('OPEN_METEO_REFRESH_MINUTES', 10)),

    'stale_after_minutes' => env('HEATMAP_STALE_AFTER_MINUTES', 30),

    'thresholds' => [
        'rainfall_24h_high_mm' => 80,
        'rainfall_24h_watch_mm' => 25,
        'rainfall_7d_high_mm' => 180,
        'rainfall_7d_watch_mm' => 70,
        'rainfall_7d_low_mm' => 20,
        'temperature_high_c' => 35,
        'temperature_watch_c' => 32,
        'wind_high_kmh' => 62,
        'wind_watch_kmh' => 39,
        'yield_high_risk_t_ha' => 3,
        'yield_watch_t_ha' => 4,
    ],

    'weights' => [
        'official_pagasa_observation' => 0.28,
        'forecast_weather' => 0.26,
        'observed_climate' => 0.12,
        'pagasa_advisory' => 0.20,
        'rice_yield' => 0.09,
        'farm_system' => 0.05,
    ],

    'levels' => [
        'severe' => 0.85,
        'high' => 0.68,
        'moderate' => 0.45,
    ],
];
