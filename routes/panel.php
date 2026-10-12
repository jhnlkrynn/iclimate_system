<?php

/*
|--------------------------------------------------------------------------
| Thesis panel revisions (2026-10-10)
|--------------------------------------------------------------------------
|
| Pages and endpoints added for the panel's revisions (docs/panel-revisions.md). Every route needs a verified,
| signed-in account.
|
*/

use App\Http\Controllers\MaoActivityLogController;
use App\Http\Controllers\FarmerSearchController;
use App\Http\Controllers\FirstPasswordController;
use App\Http\Controllers\MaoPersonnelController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {

    // 4.4 MAO personnel, coverage and the activity log.
    Route::middleware('role:'.User::ROLE_MAO)->prefix('mao')->name('mao.')->group(function () {
        Route::get('personnel', [MaoPersonnelController::class, 'index'])->name('personnel.index');
        Route::post('personnel', [MaoPersonnelController::class, 'store'])->middleware('throttle:20,1,post-mao-personnel')->name('personnel.store');
        Route::put('personnel/{user}', [MaoPersonnelController::class, 'update'])->whereNumber('user')->middleware('throttle:60,1,put-mao-personnel')->name('personnel.update');
        Route::post('personnel/{user}/reset-password', [MaoPersonnelController::class, 'resetPassword'])->whereNumber('user')->middleware('throttle:20,1,post-mao-personnel-reset')->name('personnel.reset-password');
        Route::post('view', [MaoPersonnelController::class, 'switchView'])->middleware('throttle:60,1,post-mao-view')->name('view.switch');
        Route::get('activity-log', [MaoActivityLogController::class, 'index'])->name('activity-log.index');
    });

    // 4.4 A: choose your own password at the first sign-in (any role whose account has must_change_password).
    Route::get('account/change-password', [FirstPasswordController::class, 'show'])->name('password.first-change');
    Route::post('account/change-password', [FirstPasswordController::class, 'update'])->middleware('throttle:10,1,post-first-password')->name('password.first-change.update');
    Route::post('account/change-password/skip', [FirstPasswordController::class, 'skip'])->name('password.first-change.skip');

    // 1.4 Live farmer search (name, farmer ID, barangay, organization) for MAO and IT.
    Route::get('farmers/search', FarmerSearchController::class)
        ->middleware(['role:'.User::ROLE_MAO.','.User::ROLE_IT_EXPERT, 'throttle:120,1,get-farmer-search'])
        ->name('farmers.search');
});
