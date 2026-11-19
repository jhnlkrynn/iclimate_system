<?php

return [
    'weather' => [
        'model_path' => env('WEATHER_MODEL_PATH', 'storage/models/weather_model.pkl'),
        'model_name' => env('WEATHER_MODEL_NAME', 'weather_model'),
    ],

    'rice_yield' => [
        // Safety gate (App\Services\Prediction\YieldModelGate): withhold | label | off. ACCURACY NOT VERIFIED.
        'gate' => env('ICLIMATE_YIELD_GATE', 'withhold'),
        // Under "withhold", an estimate is shown only when its input is inside the training envelope AND this is true.
        // Set it only after the model has been validated against independent observed yields (owner decision).
        'accuracy_verified' => filter_var(env('ICLIMATE_YIELD_ACCURACY_VERIFIED', false), FILTER_VALIDATE_BOOL),   // only true/1/yes/on
        // Input range the deployed model was trained on, read from the split thresholds of rice_yield_model_final.pkl
        // (read-only audit 2026-11-12: 48 municipal monthly rows). Outside it the estimate is an extrapolation.
        'training_envelope' => [
            'area' => [12.0, 258.0],        // hectares (municipal monthly area harvested)
            'rainfall_6m' => [54.0, 311.0], // mm, a 6-month MEAN
            'temp_range' => [5.75, 9.45],   // °C, TMAX - TMIN
        ],
        'model_path' => env('RICE_YIELD_MODEL_PATH', 'storage/models/rice_yield_model_final.pkl'),
        'model_name' => env('RICE_YIELD_MODEL_NAME', 'rice_yield_model_final'),
        // When true, activating a registry model also switches the live Python
        // service and verifies it. Turn off only if no Python service is used.
        'sync_runtime' => env('RICE_YIELD_SYNC_RUNTIME', true),
        'display_decimals' => env('RICE_YIELD_DISPLAY_DECIMALS', 2),
    ],
];
