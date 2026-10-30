<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// There is no separate login or sign-up page: both open the card on the landing page. The session's one-time messages
// (errors, old input, an "account is inactive" notice) are kept for that next page.
Route::get('login', function (Request $request) {
    $request->session()->reflash();

    return redirect(url('/').'#login');
})->name('login');

Route::get('login/weather', [AuthenticatedSessionController::class, 'weather'])
    ->middleware('throttle:20,1,get-login-weather')
    ->name('login.weather');

Route::middleware('guest')->group(function () {
    // Signed in with the correct password of a deleted account: why it was deleted (DeletedAccountController).
    Route::get('account-deleted', \App\Http\Controllers\Auth\DeletedAccountController::class)->name('account.deleted');

    Route::get('register', function (Request $request) {
        $request->session()->reflash();

        return redirect(url('/').'#register');
    })->name('register');

    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:5,10,post-register');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,10,post-forgot-password')
        ->name('password.email');

});

Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
    ->name('password.reset');

Route::post('reset-password', [NewPasswordController::class, 'store'])
    ->middleware('throttle:5,10,post-reset-password')
    ->name('password.store');

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1,get-verify-email-id-hash'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1,post-email-verification-notification')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
