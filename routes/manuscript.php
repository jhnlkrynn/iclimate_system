<?php

/*
|--------------------------------------------------------------------------
| Manuscript modules (Naicel, 2026-10-08)
|--------------------------------------------------------------------------
|
| The features the manuscript lists that the system did not have yet, one block per phase
| (docs/manuscript-phase-plan.md). Every route here needs a verified, signed-in account.
|
*/

use App\Http\Controllers\FarmerApprovalController;
use App\Http\Controllers\UserPasswordResetController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {

    // Phase 1: Farmer Approvals (MAO) and IT password reset.
    Route::middleware('role:'.User::ROLE_MAO)->prefix('mao/farmer-approvals')->name('mao.farmer-approvals.')->group(function () {
        Route::get('/', [FarmerApprovalController::class, 'index'])->name('index');
        Route::post('{user}/approve', [FarmerApprovalController::class, 'approve'])->whereNumber('user')->middleware('throttle:60,1,post-farmer-approve')->name('approve');
        Route::post('{user}/reject', [FarmerApprovalController::class, 'reject'])->whereNumber('user')->middleware('throttle:60,1,post-farmer-reject')->name('reject');
    });

    Route::post('users/{user}/reset-password', UserPasswordResetController::class)
        ->whereNumber('user')
        ->middleware(['role:'.User::ROLE_IT_EXPERT, 'throttle:20,1,post-user-reset-password'])
        ->name('users.reset-password');
});
