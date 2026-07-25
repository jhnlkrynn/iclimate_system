<?php

use App\Http\Controllers\Api\LianBoundaryController;
use App\Http\Controllers\Api\LianHeatmapController;
use App\Http\Controllers\Api\LianWeatherController;
use App\Http\Controllers\Api\MobileAuthController;
use Illuminate\Support\Facades\Route;

Route::get('boundaries/lian-barangays', LianBoundaryController::class)
    ->middleware('throttle:30,1');

Route::get('heatmaps/lian-barangays', LianHeatmapController::class)
    ->middleware('throttle:30,1');

Route::get('weather/lian-barangays', LianWeatherController::class)
    ->middleware('throttle:30,1');

Route::prefix('mobile')->group(function (): void {
    Route::post('login', [MobileAuthController::class, 'login'])
        ->middleware('throttle:5,1');
    Route::post('register', [MobileAuthController::class, 'register'])
        ->middleware('throttle:3,10');
});
