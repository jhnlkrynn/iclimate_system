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
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BarangayProfileController;
use App\Http\Controllers\MaintenanceRecordController;
use App\Http\Controllers\ModelManagementController;
use App\Http\Controllers\FarmerApprovalController;
use App\Http\Controllers\FarmerReportController;
use App\Http\Controllers\ReportExtrasController;
use App\Http\Controllers\SystemSettingsController;
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

    // Association Requests (MAO): farms whose farmer's association "isn't listed" (FarmAssociationReviewController).
    Route::middleware('role:'.User::ROLE_MAO)->prefix('mao/farm-associations')->name('mao.farm-associations.')->group(function () {
        Route::get('/', [\App\Http\Controllers\FarmAssociationReviewController::class, 'index'])->name('index');
        // Undeclared Farms: farms without an answer, counted per barangay; the MAO can answer for many at once.
        Route::get('undeclared', [\App\Http\Controllers\FarmAssociationUndeclaredController::class, 'index'])->name('undeclared');
        Route::post('undeclared', [\App\Http\Controllers\FarmAssociationUndeclaredController::class, 'update'])->middleware('throttle:30,1,post-farm-associations-undeclared')->name('undeclared.update');
        Route::post('{farm}/link', [\App\Http\Controllers\FarmAssociationReviewController::class, 'link'])->whereNumber('farm')->middleware('throttle:60,1,post-farm-association-link')->name('link');
        Route::post('{farm}/create', [\App\Http\Controllers\FarmAssociationReviewController::class, 'store'])->whereNumber('farm')->middleware('throttle:60,1,post-farm-association-create')->name('store');
        Route::post('{farm}/reject', [\App\Http\Controllers\FarmAssociationReviewController::class, 'reject'])->whereNumber('farm')->middleware('throttle:60,1,post-farm-association-reject')->name('reject');
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

    // Phase 7: report Excel / PDF and Draft / Published (MAO); the farmer's My Reports.
    Route::middleware('role:'.User::ROLE_MAO)->group(function () {
        Route::get('reports/export/xlsx', [ReportExtrasController::class, 'xlsx'])->middleware('throttle:20,1,get-reports-xlsx')->name('reports.export.xlsx');
        Route::get('reports/export/pdf', [ReportExtrasController::class, 'pdf'])->middleware('throttle:20,1,get-reports-pdf')->name('reports.export.pdf');
        Route::post('reports/{report}/publish', [ReportExtrasController::class, 'publish'])->whereNumber('report')->middleware('throttle:30,1,post-reports-publish')->name('reports.publish');
    });
    Route::middleware(['role:'.User::ROLE_FARMER, 'farmer.language'])->prefix('farmer/my-reports')->name('farmer.reports.')->group(function () {
        Route::get('/', [FarmerReportController::class, 'index'])->name('index');
        Route::get('pdf', [FarmerReportController::class, 'pdf'])->middleware('throttle:10,1,get-farmer-reports-pdf')->name('pdf');
    });

    // Phase 8: System Settings, Security, Roles & Permissions (IT).
    Route::middleware('role:'.User::ROLE_IT_EXPERT)->prefix('admin')->name('admin.')->group(function () {
        Route::get('settings', [SystemSettingsController::class, 'general'])->name('settings.index');
        Route::post('settings', [SystemSettingsController::class, 'saveGeneral'])->middleware('throttle:20,1,post-admin-settings')->name('settings.save');
        Route::get('settings/security', [SystemSettingsController::class, 'security'])->name('settings.security');
        Route::post('settings/security', [SystemSettingsController::class, 'saveSecurity'])->middleware('throttle:20,1,post-admin-security')->name('settings.security.save');
        Route::get('permissions', [SystemSettingsController::class, 'permissions'])->name('permissions.index');
        Route::post('permissions', [SystemSettingsController::class, 'savePermissions'])->middleware('throttle:20,1,post-admin-permissions')->name('permissions.save');

        // System Logs as CSV, with the page's filters.
        Route::get('system-logs/export', [\App\Http\Controllers\SystemLogController::class, 'export'])->middleware('throttle:10,1,get-system-logs-export')->name('system-logs.export');

        // Backups, Maintenance Records, ML Models.
        Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('backups', [BackupController::class, 'store'])->middleware('throttle:5,10,post-admin-backups')->name('backups.store');
        Route::get('backups/{file}/download', [BackupController::class, 'download'])->where('file', '[A-Za-z0-9_\-.]+')->middleware('throttle:10,1,get-admin-backup-download')->name('backups.download');
        Route::post('backups/{file}/restore', [BackupController::class, 'restore'])->where('file', '[A-Za-z0-9_\-.]+')->middleware('throttle:3,10,post-admin-backup-restore')->name('backups.restore');
        Route::get('maintenance', [MaintenanceRecordController::class, 'index'])->name('maintenance.index');
        Route::post('maintenance', [MaintenanceRecordController::class, 'store'])->middleware('throttle:30,1,post-admin-maintenance')->name('maintenance.store');
        Route::put('maintenance/{record}', [MaintenanceRecordController::class, 'update'])->whereNumber('record')->name('maintenance.update');
        Route::delete('maintenance/{record}', [MaintenanceRecordController::class, 'destroy'])->whereNumber('record')->name('maintenance.destroy');
        Route::get('models', [ModelManagementController::class, 'index'])->name('models.index');
        Route::post('models/compare', [ModelManagementController::class, 'compare'])->middleware('throttle:5,10,post-admin-models-compare')->name('models.compare');
        Route::post('models/retrain', [ModelManagementController::class, 'retrain'])->middleware('throttle:5,10,post-admin-models-retrain')->name('models.retrain');
        Route::post('models/dataset', [ModelManagementController::class, 'uploadDataset'])->middleware('throttle:10,10,post-admin-models-dataset')->name('models.dataset');
        Route::post('models/upload', [ModelManagementController::class, 'uploadModel'])->middleware('throttle:10,10,post-admin-models-upload')->name('models.upload');
    });

    // Farmer Survey & Model Feedback – Naicel (2026-10-09): the farmer's survey, and Settings → Model Feedback (IT).
    Route::middleware(['role:'.User::ROLE_FARMER, 'farmer.language'])->prefix('farmer/survey')->name('farmer.survey.')->group(function () {
        Route::get('/', [\App\Http\Controllers\FarmerSurveyController::class, 'show'])->name('show');
        Route::post('/', [\App\Http\Controllers\FarmerSurveyController::class, 'store'])->middleware('throttle:20,1,post-farmer-survey')->name('store');
    });
    Route::middleware('role:'.User::ROLE_IT_EXPERT)->prefix('admin/feedback')->name('admin.feedback.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ModelFeedbackController::class, 'index'])->name('index');
        Route::get('insights', [\App\Http\Controllers\ModelFeedbackController::class, 'insights'])->middleware('throttle:60,1,get-admin-feedback-insights')->name('insights');
        Route::put('responses/{response}', [\App\Http\Controllers\ModelFeedbackController::class, 'update'])->whereNumber('response')->middleware('throttle:120,1,put-admin-feedback')->name('update');
        Route::post('decision', [\App\Http\Controllers\ModelFeedbackController::class, 'decide'])->middleware('throttle:10,1,post-admin-feedback-decision')->name('decide');
    });

    // Phase 6: Send Alert (MAO, opened from Advisories).
    Route::middleware('role:'.User::ROLE_MAO)->prefix('mao/alerts')->name('mao.alerts.')->group(function () {
        Route::get('/', [AdvisoryAlertController::class, 'index'])->name('index');
        Route::post('/', [AdvisoryAlertController::class, 'store'])->middleware('throttle:20,1,post-mao-alerts')->name('store');
        Route::post('{batch}/cancel', [AdvisoryAlertController::class, 'cancel'])->middleware('throttle:20,1,post-mao-alerts-cancel')->name('cancel');
    });
});
