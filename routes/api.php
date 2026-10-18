<?php

use App\Http\Controllers\Api\BarangayRiskForecastController;
use App\Http\Controllers\Api\BarangayRiskMapController;
use App\Http\Controllers\Api\LianBoundaryController;
use App\Http\Controllers\Api\LianWeatherController;
use App\Http\Controllers\Api\HeatMapController;
use App\Http\Controllers\Api\MobileAuthController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::get('boundaries/lian-barangays', LianBoundaryController::class)
    ->middleware('throttle:30,1,get-boundaries-lian-barangays');

Route::get('weather/lian-barangays', LianWeatherController::class)
    ->middleware('throttle:30,1,get-weather-lian-barangays');

Route::get('weather/current', [AuthenticatedSessionController::class, 'weather'])
    ->middleware('throttle:20,1,get-api-weather-current')
    ->name('api.weather.current');

Route::get('lian/risk-map', [BarangayRiskMapController::class, 'index'])
    ->middleware('throttle:30,1,get-lian-risk-map');

Route::get('lian/risk-map/{barangay}', [BarangayRiskMapController::class, 'show'])
    ->middleware('throttle:30,1,get-lian-risk-map-barangay');

Route::get('heat-map', HeatMapController::class)->middleware(['auth', 'verified', 'throttle:60,1,get-heat-map']);

Route::get('lian/risk-forecast', BarangayRiskForecastController::class)
    ->middleware('throttle:30,1,get-lian-risk-forecast');

Route::prefix('mobile')->group(function (): void {
    Route::post('login', [MobileAuthController::class, 'login'])
        ->middleware('throttle:5,1,post-login');
    Route::post('register', [MobileAuthController::class, 'register'])
        ->middleware('throttle:3,10,post-register-2');
});
