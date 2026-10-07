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

use App\Http\Controllers\AdvisoryAlertController;
use App\Http\Controllers\BarangayProfileController;
use App\Http\Controllers\FarmerApprovalController;
use App\Http\Controllers\RecordImportController;
use App\Http\Controllers\RecordValidationController;
use App\Http\Controllers\UserPasswordResetController;
use App\Http\Controllers\WeatherAnalysisController;
use App\Http\Controllers\YieldPredictionController;
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

    // Phase 3: Barangays (MAO, a tab of Records).
    Route::middleware('role:'.User::ROLE_MAO)->prefix('mao/barangays')->name('mao.barangays.')->group(function () {
        Route::get('/', [BarangayProfileController::class, 'index'])->name('index');
        Route::put('{barangay}', [BarangayProfileController::class, 'update'])->middleware('throttle:60,1,put-mao-barangay')->name('update');
    });

    // Phase 4: Weather Analysis (farmers and MAO) and Weather Impact Analysis (MAO), tabs of Predictions.
    Route::prefix('analysis')->name('analysis.')->group(function () {
        Route::get('weather', [WeatherAnalysisController::class, 'weather'])
            ->middleware(['role:'.User::ROLE_FARMER.','.User::ROLE_MAO, 'farmer.language'])->name('weather');
        Route::middleware('role:'.User::ROLE_MAO)->group(function () {
            Route::get('impact', [WeatherAnalysisController::class, 'impact'])->name('impact');
            Route::post('impact', [WeatherAnalysisController::class, 'store'])->middleware('throttle:20,1,post-analysis-impact')->name('impact.store');
            Route::get('impact/{analysis}', [WeatherAnalysisController::class, 'show'])->whereNumber('analysis')->name('impact.show');
        });

        // Phase 5: Yield Prediction (farmer) and Yield Forecast (MAO). The model runs after the response.
        Route::middleware(['role:'.User::ROLE_FARMER, 'farmer.language'])->group(function () {
            Route::get('yield', [YieldPredictionController::class, 'farmer'])->name('yield');
            Route::post('yield', [YieldPredictionController::class, 'farmerStore'])->middleware('throttle:10,1,post-analysis-yield')->name('yield.store');
        });
        Route::middleware('role:'.User::ROLE_MAO)->group(function () {
            Route::get('forecast', [YieldPredictionController::class, 'mao'])->name('forecast');
            Route::post('forecast', [YieldPredictionController::class, 'maoStore'])->middleware('throttle:20,1,post-analysis-forecast')->name('forecast.store');
        });
    });

});
