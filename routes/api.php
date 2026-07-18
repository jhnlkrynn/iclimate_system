<?php

use App\Http\Controllers\Api\MobileAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile')->group(function (): void {
    Route::post('login', [MobileAuthController::class, 'login'])
        ->middleware('throttle:5,1');
    Route::post('register', [MobileAuthController::class, 'register'])
        ->middleware('throttle:3,10');
});
