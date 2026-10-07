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
use App\Http\Controllers\RecordImportController;
use App\Http\Controllers\RecordValidationController;
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

    // Phase 2: climate and production records — CSV import and validation (MAO).
    Route::middleware('role:'.User::ROLE_MAO)->prefix('records')->name('records.')->group(function () {
        $types = 'climate-records|rice-productions';
        Route::get('import/{type}', [RecordImportController::class, 'show'])->where('type', $types)->name('import');
        Route::get('import/{type}/template', [RecordImportController::class, 'download'])->where('type', $types)->name('import.template');
        Route::post('import/{type}', [RecordImportController::class, 'store'])->where('type', $types)->middleware('throttle:10,1,post-records-import')->name('import.store');
        Route::post('{type}/validate-all', [RecordValidationController::class, 'validateAll'])->where('type', $types)->middleware('throttle:10,1,post-records-validate-all')->name('validate-all');
        Route::post('{type}/{id}/validate', [RecordValidationController::class, 'validateRecord'])->where('type', $types)->whereNumber('id')->middleware('throttle:120,1,post-records-validate')->name('validate');
        Route::post('{type}/{id}/unvalidate', [RecordValidationController::class, 'unvalidate'])->where('type', $types)->whereNumber('id')->middleware('throttle:120,1,post-records-unvalidate')->name('unvalidate');
    });
});
