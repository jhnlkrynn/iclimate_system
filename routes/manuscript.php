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
use App\Http\Controllers\FarmerReportController;
use App\Http\Controllers\ReportExtrasController;
use App\Http\Controllers\RecordImportController;
use App\Http\Controllers\RecordValidationController;
use App\Http\Controllers\UserPasswordResetController;
use App\Http\Controllers\WeatherAnalysisController;
use App\Http\Controllers\YieldPredictionController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::middleware(\App\Http\Middleware\RequireSchema::class.':association_chairman_assignments,association_chairman_invitations')->group(function () {
    Route::get('association-chairman/invitations/{token}', [\App\Http\Controllers\AssociationInvitationController::class, 'show'])->name('association-chairman.invitation.show');
    Route::post('association-chairman/invitations/{token}', [\App\Http\Controllers\AssociationInvitationController::class, 'accept'])->middleware('throttle:10,1,association-invitation')->name('association-chairman.invitation.accept');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('association-reports/{report}/document/{submission?}', \App\Http\Controllers\AssociationReportDocumentController::class)
        ->whereNumber(['report', 'submission'])->middleware(\App\Http\Middleware\RequireSchema::class.':association_yield_report_submissions')
        ->name('association-reports.document');

    Route::middleware(['role:'.User::ROLE_FARMER, \App\Http\Middleware\RequireSchema::class.':association_chairman_assignments,association_reporting_periods,association_yield_reports,association_yield_report_submissions'])->prefix('association-chairman')->name('association-chairman.')->group(function () {
        Route::get('/', [\App\Http\Controllers\AssociationChairmanController::class, 'dashboard'])->name('dashboard');
        Route::get('reports/{report}', [\App\Http\Controllers\AssociationChairmanController::class, 'show'])->whereNumber('report')->name('reports.show');
        Route::get('reports/{period}/create', [\App\Http\Controllers\AssociationChairmanController::class, 'create'])->whereNumber('period')->name('reports.create');
        Route::post('reports/{period}', [\App\Http\Controllers\AssociationChairmanController::class, 'store'])->whereNumber('period')->middleware('throttle:20,1,association-report')->name('reports.store');
    });

    Route::middleware(['role:'.User::ROLE_MAO, \App\Http\Middleware\RequireSchema::class.':association_chairman_assignments,association_chairman_invitations,association_reporting_periods,association_yield_reports,association_yield_report_reviews,association_reminder_deliveries,association_yield_report_submissions'])->prefix('mao/association-reports')->name('mao.association-reports.')->group(function () {
        Route::get('/', [\App\Http\Controllers\MaoAssociationReportController::class, 'index'])->name('index');
        Route::post('periods', [\App\Http\Controllers\MaoAssociationReportController::class, 'storePeriod'])->middleware('throttle:20,1,association-period')->name('periods.store');
        Route::post('associations/{association}/assign', [\App\Http\Controllers\MaoAssociationReportController::class, 'assign'])->whereNumber('association')->middleware('throttle:20,1,association-assign')->name('associations.assign');
        Route::post('associations/{association}/invite', [\App\Http\Controllers\MaoAssociationReportController::class, 'invite'])->whereNumber('association')->middleware('throttle:10,1,association-invite')->name('associations.invite');
        Route::get('reports/{report}', [\App\Http\Controllers\MaoAssociationReportController::class, 'show'])->whereNumber('report')->name('reports.show');
        Route::post('reports/{report}/review', [\App\Http\Controllers\MaoAssociationReportController::class, 'review'])->whereNumber('report')->name('reports.review');
        Route::post('reports/{report}/remind', [\App\Http\Controllers\MaoAssociationReportController::class, 'remind'])->whereNumber('report')->name('reports.remind');
    });

    // Phase 1: Farmer Approvals (MAO) and IT password reset.
    Route::middleware('role:'.User::ROLE_MAO)->prefix('mao/farmer-approvals')->name('mao.farmer-approvals.')->group(function () {
        Route::get('/', [FarmerApprovalController::class, 'index'])->name('index');
        Route::get('{user}', [FarmerApprovalController::class, 'show'])->whereNumber('user')->name('show');
        Route::post('{user}/reconsider', [FarmerApprovalController::class, 'reconsider'])->whereNumber('user')->middleware('throttle:30,1,farmer-reconsider')->name('reconsider');
        Route::post('{user}/recovery-code', [FarmerApprovalController::class, 'recoveryCode'])->whereNumber('user')->middleware('throttle:10,1,farmer-recovery-code')->name('recovery-code');
        Route::post('{user}/approve', [FarmerApprovalController::class, 'approve'])->whereNumber('user')->middleware('throttle:60,1,post-farmer-approve')->name('approve');
        Route::post('{user}/reject', [FarmerApprovalController::class, 'reject'])->whereNumber('user')->middleware('throttle:60,1,post-farmer-reject')->name('reject');
    });

    Route::middleware('role:'.User::ROLE_MAO)->prefix('mao/associations')->name('mao.associations.')->group(function () {
        Route::get('farm-types', [\App\Http\Controllers\MaoAssociationFarmTypeController::class, 'index'])->name('farm-types.index');
        Route::post('{association}/farm-type', [\App\Http\Controllers\MaoAssociationFarmTypeController::class, 'update'])->whereNumber('association')->middleware('throttle:60,1,post-association-farm-type')->name('farm-types.update');
        Route::post('yield-override', [\App\Http\Controllers\MaoAssociationFarmTypeController::class, 'saveOverride'])->middleware('throttle:30,1,post-association-yield-override')->name('yield-override.store');
        Route::delete('yield-override', [\App\Http\Controllers\MaoAssociationFarmTypeController::class, 'clearOverride'])->middleware('throttle:30,1,delete-association-yield-override')->name('yield-override.destroy');
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
        ->middleware(['role:'.User::ROLE_MAO, 'throttle:20,1,post-user-reset-password'])
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
            Route::post('yield/{prediction}/useful', [YieldPredictionController::class, 'useful'])->whereNumber('prediction')->middleware('throttle:30,1,post-analysis-yield-useful')->name('yield.useful');
        });
        Route::get('yield/{prediction}/status', [YieldPredictionController::class, 'status'])
            ->whereNumber('prediction')->name('yield.status');
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

    // Farmer Survey & Model Feedback – Naicel (2026-10-09): the farmer's survey, and Settings → Model Feedback (IT).
    Route::middleware(['role:'.User::ROLE_FARMER, 'farmer.language'])->prefix('farmer/survey')->name('farmer.survey.')->group(function () {
        Route::get('/', [\App\Http\Controllers\FarmerSurveyController::class, 'show'])->name('show');
        Route::post('/', [\App\Http\Controllers\FarmerSurveyController::class, 'store'])->middleware('throttle:20,1,post-farmer-survey')->name('store');
    });
    // Phase 6: Send Alert (MAO, opened from Advisories).
    Route::middleware('role:'.User::ROLE_MAO)->prefix('mao/alerts')->name('mao.alerts.')->group(function () {
        Route::get('/', [AdvisoryAlertController::class, 'index'])->name('index');
        Route::post('/', [AdvisoryAlertController::class, 'store'])->middleware('throttle:20,1,post-mao-alerts')->name('store');
        Route::post('{batch}/cancel', [AdvisoryAlertController::class, 'cancel'])->middleware('throttle:20,1,post-mao-alerts-cancel')->name('cancel');
    });
});
