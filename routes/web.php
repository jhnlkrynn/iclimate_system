<?php

use App\Http\Controllers\AIChatController;
use App\Http\Controllers\Auth\CaptchaImageController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ClimateRecordController;
use App\Http\Controllers\CommunityFeedController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FarmerProfileController;
use App\Http\Controllers\FarmBoundaryController;
use App\Http\Controllers\HeatmapAreaController;
use App\Http\Controllers\LiveForecastingController;
use App\Http\Controllers\MessagingController;
use App\Http\Controllers\ModelEvaluationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PlantingAdvisoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RiceYieldPredictionController;
use App\Http\Controllers\RiceProductionController;
use App\Http\Controllers\SystemLogController;
use App\Http\Controllers\TyphoonSafetyController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\WeatherPredictionController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return redirect()->route(request()->user()->dashboardRoute());
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/captcha/{context}', CaptchaImageController::class)
    ->middleware('throttle:20,1')
    ->name('captcha.image');

Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/farmer/dashboard', [DashboardController::class, 'farmer'])
        ->middleware('role:'.User::ROLE_FARMER)
        ->name('farmer.dashboard');

    Route::get('/farmer/dashboard/weather', [DashboardController::class, 'farmerWeather'])
        ->middleware(['role:'.User::ROLE_FARMER, 'throttle:30,1'])
        ->name('farmer.dashboard.weather');

    Route::middleware('role:'.User::ROLE_FARMER)->group(function () {
        Route::get('/farmer/boundary', [FarmBoundaryController::class, 'edit'])
            ->name('farmer.boundary.edit');
        Route::put('/farmer/boundary', [FarmBoundaryController::class, 'update'])
            ->middleware('throttle:10,1')
            ->name('farmer.boundary.update');
        Route::delete('/farmer/boundary', [FarmBoundaryController::class, 'destroy'])
            ->name('farmer.boundary.destroy');
    });

    Route::get('/api/weather/live', [DashboardController::class, 'liveWeather'])
        ->middleware(['role:'.User::ROLE_FARMER, 'throttle:30,1'])
        ->name('api.weather.live');

    Route::get('/mao/dashboard', [DashboardController::class, 'mao'])
        ->middleware('role:'.User::ROLE_MAO)
        ->name('mao.dashboard');

    Route::get('/mao/dashboard/weather', [DashboardController::class, 'maoWeather'])
        ->middleware(['role:'.User::ROLE_MAO, 'throttle:30,1'])
        ->name('mao.dashboard.weather');

    Route::get('/admin/dashboard', [DashboardController::class, 'admin'])
        ->middleware('role:'.User::ROLE_IT_EXPERT)
        ->name('admin.dashboard');

    Route::middleware(
        'role:'.
        User::ROLE_FARMER.','.
        User::ROLE_MAO.','.
        User::ROLE_IT_EXPERT
    )->group(function () {

        Route::resource('farmer-profiles', FarmerProfileController::class);

        Route::resource('climate-records', ClimateRecordController::class);

        Route::resource('rice-productions', RiceProductionController::class);

        Route::get('management/advisories', [PlantingAdvisoryController::class, 'adminIndex'])
            ->middleware('role:'.User::ROLE_MAO.','.User::ROLE_IT_EXPERT)
            ->name('management.advisories.index');

        Route::post('management/advisories/refresh-weather', [PlantingAdvisoryController::class, 'refreshWeather'])
            ->middleware('role:'.User::ROLE_MAO.','.User::ROLE_IT_EXPERT)
            ->name('management.advisories.refresh-weather');

        Route::post('management/advisories/regenerate', [PlantingAdvisoryController::class, 'regenerate'])
            ->middleware('role:'.User::ROLE_MAO.','.User::ROLE_IT_EXPERT)
            ->name('management.advisories.regenerate');

        Route::post('planting-advisories/refresh-pagasa', [PlantingAdvisoryController::class, 'refreshPagasa'])
            ->middleware('throttle:6,1')
            ->name('planting-advisories.refresh-pagasa');

        Route::post('management/advisories/{advisory}/approve', [PlantingAdvisoryController::class, 'approve'])
            ->middleware('role:'.User::ROLE_MAO.','.User::ROLE_IT_EXPERT)
            ->name('management.advisories.approve');

        Route::post('management/advisories/{advisory}/reject', [PlantingAdvisoryController::class, 'reject'])
            ->middleware('role:'.User::ROLE_MAO.','.User::ROLE_IT_EXPERT)
            ->name('management.advisories.reject');

        Route::post('management/advisories/{advisory}/publish', [PlantingAdvisoryController::class, 'publish'])
            ->middleware('role:'.User::ROLE_MAO.','.User::ROLE_IT_EXPERT)
            ->name('management.advisories.publish');

        Route::post('management/advisories/{advisory}/archive', [PlantingAdvisoryController::class, 'archive'])
            ->middleware('role:'.User::ROLE_MAO.','.User::ROLE_IT_EXPERT)
            ->name('management.advisories.archive');

        Route::resource('planting-advisories', PlantingAdvisoryController::class);

        Route::get('calendar', [CalendarController::class, 'index'])
            ->name('calendar.index');

        Route::redirect('announcements', 'community-feed')
            ->name('announcements.index');

        Route::post(
            'notifications/mark-all-read',
            [NotificationController::class, 'markAllRead']
        )->name('notifications.mark-all-read');

        Route::patch(
            'notifications/{notification}/mark-read',
            [NotificationController::class, 'markRead']
        )->name('notifications.mark-read');

        Route::resource('notifications', NotificationController::class);

        Route::get('community-feed', [CommunityFeedController::class, 'index'])
            ->name('community-feed.index');

        Route::post('community-feed', [CommunityFeedController::class, 'store'])
            ->middleware('throttle:12,1')
            ->name('community-feed.store');

        Route::patch('community-feed/{post}', [CommunityFeedController::class, 'update'])
            ->middleware('throttle:20,1')
            ->name('community-feed.update');

        Route::patch('community-feed/{post}/archive', [CommunityFeedController::class, 'archive'])
            ->name('community-feed.archive');

        Route::post('community-feed/{post}/comments', [CommunityFeedController::class, 'comment'])
            ->middleware('throttle:30,1')
            ->name('community-feed.comments.store');

        Route::post('community-feed/{post}/reactions', [CommunityFeedController::class, 'react'])
            ->middleware('throttle:60,1')
            ->name('community-feed.reactions.store');

        Route::delete('community-feed/{post}', [CommunityFeedController::class, 'destroy'])
            ->name('community-feed.destroy');

        Route::get('messages', [MessagingController::class, 'index'])
            ->name('messages.index');

        Route::get('messages/search', [MessagingController::class, 'searchRecipients'])
            ->name('messages.search');

        Route::post('messages/open', [MessagingController::class, 'openConversation'])
            ->name('messages.open');

        Route::post('messages', [MessagingController::class, 'store'])
            ->name('messages.store');

        Route::get('messages/{conversation}', [MessagingController::class, 'show'])
            ->name('messages.show');

        Route::get('messages/{conversation}/poll', [MessagingController::class, 'poll'])
            ->name('messages.poll');

        Route::post('messages/{conversation}/typing', [MessagingController::class, 'typing'])
            ->middleware('throttle:30,1')
            ->name('messages.typing');

        Route::post('messages/{conversation}', [MessagingController::class, 'reply'])
            ->name('messages.reply');

        Route::delete('messages/{conversation}/messages/{message}', [MessagingController::class, 'destroyMessage'])
            ->name('messages.destroy-message');

        Route::resource('heatmap-areas', HeatmapAreaController::class);

        Route::post('typhoon-safety', [TyphoonSafetyController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('typhoon-safety.store');

        Route::get('ai-farming-assistant', [AIChatController::class, 'index'])
            ->name('ai-chat.index');

        Route::post('ai-farming-assistant/message', [AIChatController::class, 'message'])
            ->middleware('throttle:20,1')
            ->name('ai-chat.message');

        Route::delete('ai-farming-assistant', [AIChatController::class, 'clear'])
            ->name('ai-chat.clear');

        /*
        |--------------------------------------------------------------------------
        | Machine Learning Prediction
        |--------------------------------------------------------------------------
        */

        Route::get(
            'weather-predictions',
            [WeatherPredictionController::class, 'index']
        )->name('weather-predictions.index');

        Route::get(
            'model-evaluation',
            [ModelEvaluationController::class, 'index']
        )->middleware('role:'.User::ROLE_IT_EXPERT)
            ->name('model-evaluation.index');

        Route::post(
            'weather-predictions/predict',
            [WeatherPredictionController::class, 'predict']
        )->middleware('throttle:20,1')->name('weather-predictions.predict');

        Route::post(
            'rice-yield-predictions/predict',
            [RiceYieldPredictionController::class, 'predict']
        )->middleware('throttle:20,1')->name('rice-yield-predictions.predict');

        Route::get(
            'live-forecasting',
            [LiveForecastingController::class, 'index']
        )->name('live-forecasting.index');

        /*
        |--------------------------------------------------------------------------
        | Reports
        |--------------------------------------------------------------------------
        */

        Route::post(
            'reports/generate',
            [ReportController::class, 'generate']
        )->middleware('throttle:10,1')->name('reports.generate');

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
        ->middleware('role:'.User::ROLE_IT_EXPERT);

    Route::resource('system-logs', SystemLogController::class)
        ->only(['index', 'show'])
        ->middleware('role:'.User::ROLE_IT_EXPERT);
});

Route::middleware('auth')->group(function () {

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');
});

require __DIR__.'/auth.php';
