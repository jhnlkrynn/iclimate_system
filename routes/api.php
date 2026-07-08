<?php

use App\Http\Controllers\Api\MobileAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile')->group(function (): void {
    Route::post('login', [MobileAuthController::class, 'login']);
    Route::post('register', [MobileAuthController::class, 'register']);
});
