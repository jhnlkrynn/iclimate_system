<?php

return [
    'weather' => [
        'model_path' => env('WEATHER_MODEL_PATH', 'storage/models/weather_model.pkl'),
        'model_name' => env('WEATHER_MODEL_NAME', 'weather_model'),
    ],

    'rice_yield' => [
        'model_path' => env('RICE_YIELD_MODEL_PATH', 'storage/models/rice_yield_model_final.pkl'),
        'model_name' => env('RICE_YIELD_MODEL_NAME', 'rice_yield_model_final'),
        'display_decimals' => env('RICE_YIELD_DISPLAY_DECIMALS', 2),
    ],
];
