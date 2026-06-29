<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\ClimateRecordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FarmerProfileController;
use App\Http\Controllers\HeatmapAreaController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PlantingAdvisoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RiceProductionController;
use App\Http\Controllers\SystemLogController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\WeatherPredictionController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route(auth()->user()->dashboardRoute())
        : view('welcome');
});

Route::get('/dashboard', function () {
    return redirect()->route(request()->user()->dashboardRoute());
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/farmer/dashboard', [DashboardController::class, 'farmer'])
        ->middleware('role:' . User::ROLE_FARMER)
        ->name('farmer.dashboard');

    Route::get('/mao/dashboard', [DashboardController::class, 'mao'])
        ->middleware('role:' . User::ROLE_MAO)
        ->name('mao.dashboard');

    Route::get('/admin/dashboard', [DashboardController::class, 'admin'])
        ->middleware('role:' . User::ROLE_IT_EXPERT)
        ->name('admin.dashboard');

    Route::middleware(
        'role:' .
        User::ROLE_FARMER . ',' .
        User::ROLE_MAO . ',' .
        User::ROLE_IT_EXPERT
    )->group(function () {

        Route::resource('farmer-profiles', FarmerProfileController::class);

        Route::resource('climate-records', ClimateRecordController::class);

        Route::resource('rice-productions', RiceProductionController::class);

        Route::resource('planting-advisories', PlantingAdvisoryController::class);

        Route::resource('announcements', AnnouncementController::class);

        Route::post(
            'notifications/mark-all-read',
            [NotificationController::class, 'markAllRead']
        )->name('notifications.mark-all-read');

        Route::patch(
            'notifications/{notification}/mark-read',
            [NotificationController::class, 'markRead']
        )->name('notifications.mark-read');

        Route::resource('notifications', NotificationController::class);

        Route::resource('heatmap-areas', HeatmapAreaController::class);

        /*
        |--------------------------------------------------------------------------
        | Machine Learning Prediction
        |--------------------------------------------------------------------------
        */

        Route::get(
            'weather-predictions',
            [WeatherPredictionController::class, 'index']
        )->name('weather-predictions.index');

        Route::post(
            'weather-predictions/predict',
            [WeatherPredictionController::class, 'predict']
        )->name('weather-predictions.predict');

        /*
        |--------------------------------------------------------------------------
        | Reports
        |--------------------------------------------------------------------------
        */

        Route::post(
            'reports/generate',
            [ReportController::class, 'generate']
        )->name('reports.generate');

        Route::get(
            'reports/print',
            [ReportController::class, 'print']
        )->name('reports.print');

        Route::get(
            'reports/export',
            [ReportController::class, 'exportCsv']
        )->name('reports.export');

        Route::resource('reports', ReportController::class);
    });

    Route::resource('users', UserManagementController::class)
        ->middleware('role:' . User::ROLE_IT_EXPERT);

    Route::resource('system-logs', SystemLogController::class)
        ->only(['index', 'show'])
        ->middleware('role:' . User::ROLE_IT_EXPERT);
});

Route::middleware('auth')->group(function () {

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');
});

require __DIR__ . '/auth.php';