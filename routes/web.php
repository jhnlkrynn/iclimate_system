<?php

use App\Http\Controllers\AIChatController;
use App\Http\Controllers\AiAdminController;
use App\Http\Controllers\AnalyticsRunController;
use App\Http\Controllers\Auth\CaptchaImageController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ClimateRecordController;
use App\Http\Controllers\CommunityFeedController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FarmBoundaryController;
use App\Http\Controllers\FarmerPlantingScheduleController;
use App\Http\Controllers\FarmerProfileController;
use App\Http\Controllers\FieldReportController;
use App\Http\Controllers\MessagingController;
use App\Http\Controllers\ModelEvaluationController;
use App\Http\Controllers\ModelRegistryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PlantingAdvisoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RiceProductionController;
use App\Http\Controllers\RiceYieldPredictionController;
use App\Http\Controllers\SystemHealthController;
use App\Http\Controllers\SystemLogController;
use App\Http\Controllers\TyphoonSafetyController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\WeatherPredictionController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return view('welcome');
});

// The home page's "Send us a message" form: delivered to MAO staff as a notification.
Route::post('/contact', \App\Http\Controllers\ContactMessageController::class)
    ->middleware('throttle:3,10,post-contact')
    ->name('contact.send');


Route::get('/dashboard', function () {
    return redirect()->route(
        request()->user()->dashboardRoute()
    );
})->middleware(['auth', 'verified'])
    ->name('dashboard');


Route::get(
    '/analytics-runs/{analyticsRun}',
    [AnalyticsRunController::class, 'show']
)
    ->middleware(['auth', 'verified'])
    ->name('analytics-runs.show');


Route::get(
    '/captcha/{context}',
    CaptchaImageController::class
)
    ->middleware('throttle:20,1,get-captcha-context')
    ->name('captcha.image');


Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/heat-map', [DashboardController::class, 'heatMap'])
        ->middleware('farmer.language')
        ->name('heat-map.index');

    Route::get('/heat-map/{range}/{barangay}/detail', [DashboardController::class, 'heatMapDetail'])
        ->middleware('farmer.language')
        ->where('range', '[A-Za-z0-9_-]+')
        ->name('heat-map.detail');

    /*
    |--------------------------------------------------------------------------
    | Farmer Dashboard
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/farmer/dashboard',
        [DashboardController::class, 'farmer']
    )
        ->middleware(['role:'.User::ROLE_FARMER, 'farmer.language'])
        ->name('farmer.dashboard');


    Route::get(
        '/farmer/dashboard/weather',
        [DashboardController::class, 'farmerWeather']
    )
        ->middleware([
            'role:'.User::ROLE_FARMER,
            'throttle:30,1,get-farmer-dashboard-weather',
        ])
        ->name('farmer.dashboard.weather');

    Route::get('/farmer/dashboard/live', [DashboardController::class, 'farmerLive'])
        ->middleware(['role:'.User::ROLE_FARMER, 'farmer.language', 'throttle:30,1,get-farmer-dashboard-live'])
        ->name('farmer.dashboard.live');

    // The dashboard's English / Filipino selector: saves the choice on the farmer's account.
    Route::post('/farmer/dashboard/language', [DashboardController::class, 'farmerLanguage'])
        ->middleware(['role:'.User::ROLE_FARMER, 'throttle:20,1,post-farmer-dashboard-language'])
        ->name('farmer.dashboard.language');


    Route::middleware(
        'role:'.User::ROLE_FARMER
    )->group(function () {

        // The farmer dashboard's pop-ups (planting date, farm size, profile, farm map): each saves for the signed-in farmer and answers in JSON.
        Route::middleware(['farmer.language', 'throttle:20,1,post-farmer-dashboard-language-2'])->group(function () {
            Route::post('/farmer/dashboard/planting-info', [FarmerPlantingScheduleController::class, 'applyFromDashboard'])->name('farmer.dashboard.planting-info');
            Route::post('/farmer/dashboard/farm-size', [\App\Http\Controllers\FarmerDashboardSetupController::class, 'farmSize'])->name('farmer.dashboard.farm-size');
            Route::post('/farmer/dashboard/profile', [\App\Http\Controllers\FarmerDashboardSetupController::class, 'profile'])->name('farmer.dashboard.profile');
            // Farm map drawing was removed (2026-10): the old pop-up's save opens My Farm and stores nothing.
            Route::post('/farmer/dashboard/farm-map', [\App\Http\Controllers\FarmBoundaryController::class, 'updateFromDashboard'])->middleware('farmer.redirect:farmer.my-farm')->name('farmer.dashboard.farm-map');
        });

        // "Aking Bukid": the farmer's farms (My Farms), and the current farm's planting schedule, harvest records and dates.
        Route::get('/farmer/my-farm', \App\Http\Controllers\FarmerMyFarmController::class)
            ->middleware('farmer.language')
            ->name('farmer.my-farm');

        // Farmer – Naicel (2026-10): the Activity Calendar (planted / harvested), the actual-harvest question and the
        // forecast survey. Farmer only; each acts on the signed-in farmer's own records and farms.
        Route::middleware(['farmer.language', 'throttle:30,1,post-farmer-naicel'])->group(function () {
            Route::post('/farmer/activities', [\App\Http\Controllers\FarmerActivityController::class, 'store'])->name('farmer.activities.store');
            Route::post('/farmer/yield-survey/{plantingSchedule}', [\App\Http\Controllers\FarmerActivityController::class, 'yieldSurvey'])->whereNumber('plantingSchedule')->name('farmer.yield-survey.store');
            Route::post('/farmer/forecast-survey', [\App\Http\Controllers\FarmerForecastSurveyController::class, 'store'])->name('farmer.forecast-survey.store');
        });
        // end Farmer – Naicel

        // A farmer's farms: add, edit, remove (archive), set as primary, map, and the My Farm selector. Every {farm} is
        // looked up among the signed-in farmer's own farms only (FarmController::ownFarm).
        Route::middleware('farmer.language')->prefix('/farmer/farms')->name('farmer.farms.')->group(function () {
            Route::get('/create', [\App\Http\Controllers\FarmController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\FarmController::class, 'store'])->middleware('throttle:20,1,post-farmer-farms')->name('store');
            Route::post('/active', [\App\Http\Controllers\FarmController::class, 'select'])->middleware('throttle:60,1,post-farmer-farms-active')->name('select');
            Route::get('/{farm}/edit', [\App\Http\Controllers\FarmController::class, 'edit'])->whereNumber('farm')->name('edit');
            Route::put('/{farm}', [\App\Http\Controllers\FarmController::class, 'update'])->whereNumber('farm')->name('update');
            Route::delete('/{farm}', [\App\Http\Controllers\FarmController::class, 'destroy'])->whereNumber('farm')->name('destroy');
            Route::post('/{farm}/primary', [\App\Http\Controllers\FarmController::class, 'primary'])->whereNumber('farm')->name('primary');
            Route::get('/{farm}/map', [\App\Http\Controllers\FarmController::class, 'map'])->whereNumber('farm')->name('map');
            Route::put('/{farm}/map', [\App\Http\Controllers\FarmController::class, 'saveMap'])->whereNumber('farm')->middleware('throttle:10,1,put-farmer-farm-map')->name('map.update');
            Route::delete('/{farm}/map', [\App\Http\Controllers\FarmController::class, 'deleteMap'])->whereNumber('farm')->name('map.destroy');
        });

        Route::get(
            '/farmer/planting-schedules/create',
            [FarmerPlantingScheduleController::class, 'create']
        )->name(
            'farmer.planting-schedules.create'
        );


        Route::post(
            '/farmer/planting-schedules',
            [FarmerPlantingScheduleController::class, 'store']
        )->name(
            'farmer.planting-schedules.store'
        );


        Route::get(
            '/farmer/planting-schedules/{plantingSchedule}/edit',
            [FarmerPlantingScheduleController::class, 'edit']
        )->name(
            'farmer.planting-schedules.edit'
        );


        Route::put(
            '/farmer/planting-schedules/{plantingSchedule}',
            [FarmerPlantingScheduleController::class, 'update']
        )->name(
            'farmer.planting-schedules.update'
        );


        Route::delete(
            '/farmer/planting-schedules/{plantingSchedule}',
            [FarmerPlantingScheduleController::class, 'destroy']
        )->name(
            'farmer.planting-schedules.destroy'
        );


        // Farm boundary drawing was removed from the farmer pages (2026-10): these URLs open My Farm instead.
        Route::get(
            '/farmer/boundary',
            [FarmBoundaryController::class, 'edit']
        )->middleware('farmer.redirect:farmer.my-farm')->name(
            'farmer.boundary.edit'
        );


        Route::put(
            '/farmer/boundary',
            [FarmBoundaryController::class, 'update']
        )
            ->middleware(['farmer.redirect:farmer.my-farm', 'throttle:10,1,put-farmer-boundary'])
            ->name(
                'farmer.boundary.update'
            );


        Route::delete(
            '/farmer/boundary',
            [FarmBoundaryController::class, 'destroy']
        )->middleware('farmer.redirect:farmer.my-farm')->name(
            'farmer.boundary.destroy'
        );


        Route::post(
            '/planting-advisories/{plantingAdvisory}/feedback',
            [PlantingAdvisoryController::class, 'feedback']
        )
            ->middleware('throttle:30,1,post-planting-advisories-plantingadvisory-feedback')
            ->name(
                'planting-advisories.feedback'
            );
    });


    Route::get(
        '/api/weather/live',
        [DashboardController::class, 'liveWeather']
    )
        ->middleware([
            'role:'.User::ROLE_FARMER,
            'throttle:30,1,get-api-weather-live',
        ])
        ->name('api.weather.live');


    /*
    |--------------------------------------------------------------------------
    | MAO Dashboard
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/mao/dashboard',
        [DashboardController::class, 'mao']
    )
        ->middleware('role:'.User::ROLE_MAO)
        ->name('mao.dashboard');


    Route::get(
        '/mao/dashboard/weather',
        [DashboardController::class, 'maoWeather']
    )
        ->middleware([
            'role:'.User::ROLE_MAO,
            'throttle:30,1,get-mao-dashboard-weather',
        ])
        ->name('mao.dashboard.weather');

    Route::middleware('role:'.User::ROLE_MAO)->group(function () {
        Route::get('/mao/dashboard/live', [DashboardController::class, 'maoLive'])->middleware('throttle:30,1,get-mao-dashboard-live')->name('mao.dashboard.live');
        // The Operations page was removed (2026-10): its parts live on Advisories, Calendar, Field Reports and Reports.
        // The old address opens the dashboard.
        Route::get('/mao/operations', fn () => redirect()->route('mao.dashboard'))->name('mao.operations');
        // MAO Dashboard – Naicel (2026-10-08): the dashboard's English / Filipino selector, saved on the MAO's account.
        Route::post('/mao/dashboard/language', [DashboardController::class, 'maoLanguage'])
            ->middleware('throttle:20,1,post-mao-dashboard-language')->name('mao.dashboard.language');
    });


    /*
    |--------------------------------------------------------------------------
    | IT Expert / Admin
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/admin/dashboard',
        [DashboardController::class, 'admin']
    )
        ->middleware(
            'role:'.User::ROLE_IT_EXPERT
        )
        ->name('admin.dashboard');

    Route::middleware('role:'.User::ROLE_IT_EXPERT)->group(function () {
        Route::get('/admin/dashboard/live', [DashboardController::class, 'adminLive'])->name('admin.dashboard.live');
        Route::get('/admin/dashboard/activity', [DashboardController::class, 'adminActivity'])->name('admin.dashboard.activity');
        Route::get('/admin/dashboard/recent', [DashboardController::class, 'adminRecent'])->name('admin.dashboard.recent');
        Route::post('/admin/dashboard/retry-failed', [DashboardController::class, 'adminRetryFailed'])->name('admin.dashboard.retry-failed');
    });


    /*
    |--------------------------------------------------------------------------
    | Candidate Rice Yield Model Test Page
    |--------------------------------------------------------------------------
    |
    | Candidate Random Forest model testing page.
    | Uses Ambulong meteorological data as the consistent weather reference.
    | Does not replace the current production rice yield model.
    |
    */

    // The candidate model test page was removed (2026-10); both of its URLs open Model Evaluation.
    Route::get(
        '/testing/candidate-rice-yield',
        fn () => redirect()->route('model-evaluation.index')
    )
        ->middleware(
            'role:'.User::ROLE_IT_EXPERT
        )
        ->name(
            'testing.candidate-rice-yield'
        );


    Route::post(
        '/testing/candidate-rice-yield/predict',
        fn () => redirect()->route('model-evaluation.index', [], 303)
    )
        ->middleware([
            'role:'.User::ROLE_IT_EXPERT,
            'throttle:10,1,post-testing-candidate-rice-yield-predict',
        ])
        ->name(
            'testing.candidate-rice-yield.predict'
        );


    /*
    |--------------------------------------------------------------------------
    | Shared Authenticated Features
    |--------------------------------------------------------------------------
    */

    Route::middleware(
        'role:'.
        User::ROLE_FARMER.','.
        User::ROLE_MAO.','.
        User::ROLE_IT_EXPERT
    )->group(function () {

        Route::resource(
            'farmer-profiles',
            FarmerProfileController::class
        );


        // Removed from the farmer pages (2026-10): a farmer who opens one is sent to Home.
        Route::resource(
            'climate-records',
            ClimateRecordController::class
        )->middleware('farmer.redirect:farmer.dashboard');


        Route::resource(
            'rice-productions',
            RiceProductionController::class
        )->middleware('farmer.redirect:farmer.dashboard');


        /*
        |--------------------------------------------------------------------------
        | Advisories
        |--------------------------------------------------------------------------
        */

        // Advisory Management moved into the Advisories page (2026-10): the old address opens its "All" view, keeping
        // a watched analytics run.
        Route::get(
            'management/advisories',
            fn (\Illuminate\Http\Request $request) => redirect()->route('planting-advisories.index', array_filter(['show' => 'all', 'run' => $request->query('run')]))
        )
            ->middleware(
                'role:'.
                User::ROLE_MAO.','.
                User::ROLE_IT_EXPERT
            )
            ->name(
                'management.advisories.index'
            );


        Route::post(
            'management/advisories/refresh-weather',
            [PlantingAdvisoryController::class, 'refreshWeather']
        )
            ->middleware(
                'role:'.
                User::ROLE_MAO.','.
                User::ROLE_IT_EXPERT
            )
            ->name(
                'management.advisories.refresh-weather'
            );


        Route::post(
            'management/advisories/regenerate',
            [PlantingAdvisoryController::class, 'regenerate']
        )
            ->middleware(
                'role:'.
                User::ROLE_MAO.','.
                User::ROLE_IT_EXPERT
            )
            ->name(
                'management.advisories.regenerate'
            );


        Route::post(
            'planting-advisories/refresh-pagasa',
            [PlantingAdvisoryController::class, 'refreshPagasa']
        )
            ->middleware('throttle:6,1,post-planting-advisories-refresh-pagasa')
            ->name(
                'planting-advisories.refresh-pagasa'
            );


        Route::post(
            'management/advisories/{advisory}/approve',
            [PlantingAdvisoryController::class, 'approve']
        )
            ->middleware(
                'role:'.
                User::ROLE_MAO.','.
                User::ROLE_IT_EXPERT
            )
            ->name(
                'management.advisories.approve'
            );


        Route::post(
            'management/advisories/{advisory}/reject',
            [PlantingAdvisoryController::class, 'reject']
        )
            ->middleware(
                'role:'.
                User::ROLE_MAO.','.
                User::ROLE_IT_EXPERT
            )
            ->name(
                'management.advisories.reject'
            );


        Route::post(
            'management/advisories/{advisory}/publish',
            [PlantingAdvisoryController::class, 'publish']
        )
            ->middleware(
                'role:'.
                User::ROLE_MAO.','.
                User::ROLE_IT_EXPERT
            )
            ->name(
                'management.advisories.publish'
            );


        Route::post(
            'management/advisories/{advisory}/archive',
            [PlantingAdvisoryController::class, 'archive']
        )
            ->middleware(
                'role:'.
                User::ROLE_MAO.','.
                User::ROLE_IT_EXPERT
            )
            ->name(
                'management.advisories.archive'
            );


        Route::post(
            'advisory-feedback/{advisoryFeedback}/respond',
            [PlantingAdvisoryController::class, 'respondToFeedback']
        )
            ->middleware(
                'role:'.
                User::ROLE_MAO.','.
                User::ROLE_IT_EXPERT
            )
            ->name(
                'advisory-feedback.respond'
            );


        Route::resource(
            'planting-advisories',
            PlantingAdvisoryController::class
        );


        /*
        |--------------------------------------------------------------------------
        | Calendar
        |--------------------------------------------------------------------------
        */

        Route::get(
            'calendar',
            [CalendarController::class, 'index']
        )->name(
            'calendar.index'
        );


        /*
        |--------------------------------------------------------------------------
        | Announcements / Community Feed
        |--------------------------------------------------------------------------
        */

        Route::redirect(
            'announcements',
            'community-feed'
        )->name(
            'announcements.index'
        );


        /*
        |--------------------------------------------------------------------------
        | Notifications
        |--------------------------------------------------------------------------
        */

        // The header bell's pop-up list; registered before the resource so "feed" is never read as an id.
        Route::get(
            'notifications/feed',
            [NotificationController::class, 'feed']
        )->name(
            'notifications.feed'
        );


        Route::post(
            'notifications/mark-all-read',
            [NotificationController::class, 'markAllRead']
        )->name(
            'notifications.mark-all-read'
        );


        Route::patch(
            'notifications/{notification}/mark-read',
            [NotificationController::class, 'markRead']
        )->name(
            'notifications.mark-read'
        );


        Route::resource(
            'notifications',
            NotificationController::class
        );


        /*
        |--------------------------------------------------------------------------
        | Community Feed
        |--------------------------------------------------------------------------
        */

        // Farmers read MAO posts as read-only Announcements on the Advisories page.
        Route::get(
            'community-feed',
            [CommunityFeedController::class, 'index']
        )->middleware('farmer.redirect:planting-advisories.index,announcements')->name(
            'community-feed.index'
        );


        // Farmers read posts as Announcements and cannot post, edit, archive, comment, react or delete: any of these
        // sends a farmer to the Announcements (a redirect, not an error page). MAO and IT keep the full feed.
        Route::post(
            'community-feed',
            [CommunityFeedController::class, 'store']
        )
            ->middleware(['farmer.redirect:planting-advisories.index,announcements', 'throttle:12,1,post-community-feed'])
            ->name(
                'community-feed.store'
            );


        Route::patch(
            'community-feed/{post}',
            [CommunityFeedController::class, 'update']
        )
            ->middleware(['farmer.redirect:planting-advisories.index,announcements', 'throttle:20,1,patch-community-feed-post'])
            ->name(
                'community-feed.update'
            );


        Route::patch(
            'community-feed/{post}/archive',
            [CommunityFeedController::class, 'archive']
        )->middleware('farmer.redirect:planting-advisories.index,announcements')->name(
            'community-feed.archive'
        );


        Route::post(
            'community-feed/{post}/comments',
            [CommunityFeedController::class, 'comment']
        )
            ->middleware(['farmer.redirect:planting-advisories.index,announcements', 'throttle:30,1,post-community-feed-post-comments'])
            ->name(
                'community-feed.comments.store'
            );


        Route::post(
            'community-feed/{post}/reactions',
            [CommunityFeedController::class, 'react']
        )
            ->middleware(['farmer.redirect:planting-advisories.index,announcements', 'throttle:60,1,post-community-feed-post-reactions'])
            ->name(
                'community-feed.reactions.store'
            );


        Route::delete(
            'community-feed/{post}',
            [CommunityFeedController::class, 'destroy']
        )->middleware('farmer.redirect:planting-advisories.index,announcements')->name(
            'community-feed.destroy'
        );


        /*
        |--------------------------------------------------------------------------
        | Messaging
        |--------------------------------------------------------------------------
        */

        Route::get(
            'messages',
            [MessagingController::class, 'index']
        )->name(
            'messages.index'
        );


        Route::get(
            'messages/search',
            [MessagingController::class, 'searchRecipients']
        )->name(
            'messages.search'
        );

        Route::get(
            'messages/people',
            [MessagingController::class, 'people']
        )->middleware('throttle:60,1,get-messages-people')->name('messages.people');


        Route::post(
            'messages/open',
            [MessagingController::class, 'openConversation']
        )->middleware('throttle:30,1,post-messages-open')->name(
            'messages.open'
        );


        Route::post(
            'messages',
            [MessagingController::class, 'store']
        )->middleware('throttle:30,1,post-messages')->name(
            'messages.store'
        );


        Route::get(
            'messages/{conversation}',
            [MessagingController::class, 'show']
        )->name(
            'messages.show'
        );


        Route::get(
            'messages/{conversation}/poll',
            [MessagingController::class, 'poll']
        )->name(
            'messages.poll'
        );


        Route::post(
            'messages/{conversation}/typing',
            [MessagingController::class, 'typing']
        )
            ->middleware('throttle:30,1,post-messages-conversation-typing')
            ->name(
                'messages.typing'
            );


        Route::post(
            'messages/{conversation}',
            [MessagingController::class, 'reply']
        )->middleware('throttle:30,1,post-messages-conversation')->name(
            'messages.reply'
        );


        // Attachments are private files: only the two people in the conversation can open them.
        Route::get(
            'messages/{conversation}/messages/{message}/attachment',
            [MessagingController::class, 'attachment']
        )->name(
            'messages.attachment'
        );


        Route::delete(
            'messages/{conversation}/messages/{message}',
            [MessagingController::class, 'destroyMessage']
        )->name(
            'messages.destroy-message'
        );


        /*
        |--------------------------------------------------------------------------
        | Field Reports
        |--------------------------------------------------------------------------
        */

        // Internal MAO operations reports: staff only. Farmers do not see them (typing the URL returns 403).
        Route::resource(
            'field-reports',
            FieldReportController::class
        )->middleware([\App\Http\Middleware\FieldReportsUi::class, 'role:'.User::ROLE_MAO.','.User::ROLE_IT_EXPERT]);   // MAO/IT – Naicel: hidden unless FIELD_REPORTS_UI=true


        /*
        |--------------------------------------------------------------------------
        | Typhoon Safety
        |--------------------------------------------------------------------------
        */

        Route::post(
            'typhoon-safety',
            [TyphoonSafetyController::class, 'store']
        )
            ->middleware('throttle:10,1,post-typhoon-safety')
            ->name(
                'typhoon-safety.store'
            );


        /*
        |--------------------------------------------------------------------------
        | AI Farming Assistant
        |--------------------------------------------------------------------------
        */

        Route::get(
            'ai-farming-assistant',
            [AIChatController::class, 'index']
        )->name(
            'ai-chat.index'
        );


        // The chat widget, loaded only when the Climora button is first clicked.
        Route::get(
            'ai-farming-assistant/widget',
            [AIChatController::class, 'widget']
        )->name(
            'ai-chat.widget'
        );


        Route::post(
            'ai-farming-assistant/message',
            [AIChatController::class, 'message']
        )
            ->middleware('throttle:20,1,post-ai-farming-assistant-message')
            ->name(
                'ai-chat.message'
            );

        Route::patch(
            'ai-farming-assistant/language',
            [AIChatController::class, 'setLanguage']
        )
            ->middleware('role:'.User::ROLE_FARMER)
            ->name(
                'ai-chat.language'
            );


        Route::delete(
            'ai-farming-assistant',
            [AIChatController::class, 'clear']
        )->name(
            'ai-chat.clear'
        );


        /*
        |--------------------------------------------------------------------------
        | Machine Learning Prediction
        |--------------------------------------------------------------------------
        */

        Route::get(
            'weather-predictions',
            [WeatherPredictionController::class, 'index']
        )->middleware('farmer.language')->name(
            'weather-predictions.index'
        );

        Route::get(
            'weather-predictions/municipality-yield-status',
            [WeatherPredictionController::class, 'municipalityYieldStatus']
        )->middleware('farmer.language')->name('weather-predictions.municipality-yield-status');


        Route::get(
            'model-evaluation',
            [ModelEvaluationController::class, 'index']
        )
            ->middleware(
                'role:'.User::ROLE_IT_EXPERT
            )
            ->name(
                'model-evaluation.index'
            );

        Route::get('/system-health', [SystemHealthController::class, 'index'])
            ->middleware('role:'.User::ROLE_IT_EXPERT)
            ->name('system-health.index');

        // Chatbot Knowledge (vocabulary and language filter). Hidden from the interface unless
        // CHATBOT_KNOWLEDGE_UI=true: while hidden, these pages send every role to their dashboard (the first
        // middleware). When shown, they are IT only. The chatbot uses the knowledge either way.
        Route::prefix('ai-admin')
            ->middleware([\App\Http\Middleware\ChatbotKnowledgeUi::class, 'role:'.User::ROLE_IT_EXPERT])
            ->name('ai-admin.')
            ->group(function () {
                Route::get('/', fn () => redirect()->route('ai-admin.index', ['resource' => 'topics']))->name('home');
                Route::get('{resource}', [AiAdminController::class, 'index'])->name('index');
                Route::post('{resource}', [AiAdminController::class, 'store'])->middleware('throttle:60,1,post-resource')->name('store');
                Route::put('{resource}/{id}', [AiAdminController::class, 'update'])->whereNumber('id')->middleware('throttle:60,1,put-resource-id')->name('update');
                Route::post('{resource}/{id}/toggle', [AiAdminController::class, 'toggle'])->whereNumber('id')->middleware('throttle:60,1,post-resource-id-toggle')->name('toggle');
            });

        Route::prefix('system-health/model-registry')
            ->middleware('role:'.User::ROLE_IT_EXPERT)
            ->name('model-registry.')
            ->group(function () {
                Route::get('/', [ModelRegistryController::class, 'index'])->name('index');
                Route::post('{model}/validate', [ModelRegistryController::class, 'validateModel'])->middleware('throttle:20,1,post-model-validate')->name('validate');
                Route::post('{model}/activate', [ModelRegistryController::class, 'activate'])->middleware('throttle:20,1,post-model-activate')->name('activate');
                Route::post('{model}/fail', [ModelRegistryController::class, 'fail'])->middleware('throttle:20,1,post-model-fail')->name('fail');
            });


        // The Predictions page has no forms since 2026-10 (it shows saved forecasts and estimates); old form posts open it.
        Route::post(
            'weather-predictions/predict',
            fn () => redirect()->route('weather-predictions.index', [], 303)
        )
            ->middleware('throttle:20,1,post-weather-predictions-predict')
            ->name(
                'weather-predictions.predict'
            );


        Route::post(
            'weather-predictions/feedback',
            [\App\Http\Controllers\WeatherFeedbackController::class, 'store']
        )
            ->middleware(['role:'.User::ROLE_FARMER, 'farmer.redirect:weather-predictions.index', 'throttle:20,1,post-weather-predictions-feedback'])
            ->name(
                'weather-feedback.store'
            );


        Route::post(
            'rice-yield-predictions/predict',
            fn () => redirect()->to(route('weather-predictions.index').'#yield', 303)
        )
            ->middleware('throttle:20,1,post-rice-yield-predictions-predict')
            ->name(
                'rice-yield-predictions.predict'
            );


        /*
        |--------------------------------------------------------------------------
        | Live Forecasting — Legacy Redirect
        |--------------------------------------------------------------------------
        */

        Route::any(
            'live-forecasting',
            function (Request $request) {
                return redirect()->route(
                    $request->user()->dashboardRoute()
                );
            }
        );


        Route::any(
            'live-forecasting/{any}',
            function (Request $request) {
                return redirect()->route(
                    $request->user()->dashboardRoute()
                );
            }
        )->where(
            'any',
            '.*'
        );


        /*
        |--------------------------------------------------------------------------
        | Reports
        |--------------------------------------------------------------------------
        */

        Route::post(
            'reports/generate',
            [ReportController::class, 'generate']
        )
            ->middleware('throttle:10,1,post-reports-generate')
            ->name(
                'reports.generate'
            );


        Route::get(
            'reports/results',
            [ReportController::class, 'results']
        )->name(
            'reports.results'
        );


        Route::get(
            'reports/print',
            [ReportController::class, 'print']
        )->name(
            'reports.print'
        );


        Route::get(
            'reports/export',
            [ReportController::class, 'exportCsv']
        )->name(
            'reports.export'
        );


        Route::resource(
            'reports',
            ReportController::class
        )
            ->only([
                'index',
                'show',
                'destroy',
            ])
            ->whereNumber(
                'report'
            );
    });


    /*
    |--------------------------------------------------------------------------
    | Usability Survey — Legacy Redirect
    |--------------------------------------------------------------------------
    */

    Route::any(
        'usability-survey',
        function (Request $request) {
            return redirect()->route(
                $request->user()->dashboardRoute()
            );
        }
    );


    Route::any(
        'usability-survey/{any}',
        function (Request $request) {
            return redirect()->route(
                $request->user()->dashboardRoute()
            );
        }
    )->where(
        'any',
        '.*'
    );


    /*
    |--------------------------------------------------------------------------
    | Farmer Organizations — Legacy Redirect
    |--------------------------------------------------------------------------
    */

    Route::any(
        'farmer-organizations',
        function (Request $request) {
            return redirect()->route(
                $request->user()->dashboardRoute()
            );
        }
    );


    Route::any(
        'farmer-organizations/{any}',
        function (Request $request) {
            return redirect()->route(
                $request->user()->dashboardRoute()
            );
        }
    )->where(
        'any',
        '.*'
    );


    /*
    |--------------------------------------------------------------------------
    | User Management
    |--------------------------------------------------------------------------
    */

    Route::resource(
        'users',
        UserManagementController::class
    )->middleware(
        'role:'.User::ROLE_IT_EXPERT
    );


    /*
    |--------------------------------------------------------------------------
    | System Logs
    |--------------------------------------------------------------------------
    */

    Route::resource(
        'system-logs',
        SystemLogController::class
    )
        ->only([
            'index',
            'show',
        ])
        ->middleware(
            'role:'.User::ROLE_IT_EXPERT
        );
});


// MAO/IT – Kaye: MAO Planner (the MAO's own activities as a list; replaces the calendar grid for MAO accounts).
Route::middleware(['auth', 'verified', 'role:'.User::ROLE_MAO])->prefix('mao/planner')->name('mao.planner.')->group(function () {
    Route::get('/', [\App\Http\Controllers\MaoPlannerController::class, 'index'])->name('index');
    Route::post('/', [\App\Http\Controllers\MaoPlannerController::class, 'store'])->name('store');
    Route::put('{activity}', [\App\Http\Controllers\MaoPlannerController::class, 'update'])->name('update');
    Route::delete('{activity}', [\App\Http\Controllers\MaoPlannerController::class, 'destroy'])->name('destroy');
});
// End MAO/IT – Kaye

// Rice Varieties: the NSIC catalog for farmers, MAO and IT (the page differs by role), the Variety Details pop-up's
// data, the planting form's expected-harvest preview (farmers) and adding / editing varieties (IT). Permissions:
// App\Policies\RiceVarietyPolicy.
Route::middleware(['auth', 'verified', 'farmer.language'])->prefix('rice-varieties')->name('rice-varieties.')->group(function () {
    $controller = \App\Http\Controllers\RiceVarietyController::class;
    $staffAndFarmers = 'role:'.User::ROLE_FARMER.','.User::ROLE_MAO.','.User::ROLE_IT_EXPERT;

    Route::get('/', [$controller, 'index'])->middleware($staffAndFarmers)->name('index');
    Route::get('/estimate', [$controller, 'estimate'])->middleware(['role:'.User::ROLE_FARMER, 'throttle:60,1,get-rice-variety-estimate'])->name('estimate');
    Route::middleware('role:'.User::ROLE_IT_EXPERT)->group(function () use ($controller) {
        Route::get('/create', [$controller, 'create'])->name('create');
        Route::post('/', [$controller, 'store'])->name('store');
        Route::get('/{riceVariety}/edit', [$controller, 'edit'])->whereNumber('riceVariety')->name('edit');
        Route::put('/{riceVariety}', [$controller, 'update'])->whereNumber('riceVariety')->name('update');
        Route::patch('/{riceVariety}/toggle', [$controller, 'toggle'])->whereNumber('riceVariety')->name('toggle');
        Route::post('/import', [$controller, 'import'])->middleware('throttle:5,1,post-rice-varieties-import')->name('import');
    });
    Route::get('/{riceVariety}', [$controller, 'show'])->whereNumber('riceVariety')->middleware([$staffAndFarmers, 'throttle:120,1,get-rice-variety'])->name('show');
});


/*
|--------------------------------------------------------------------------
| Profile
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    Route::get(
        '/profile',
        [ProfileController::class, 'edit']
    )->name(
        'profile.edit'
    );


    Route::patch(
        '/profile',
        [ProfileController::class, 'update']
    )->name(
        'profile.update'
    );


    Route::delete(
        '/profile',
        [ProfileController::class, 'destroy']
    )->name(
        'profile.destroy'
    );


    // Profile photo shown in the top bar's account button.
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])
        ->middleware('throttle:10,1')
        ->name('profile.photo.update');

    Route::delete('/profile/photo', [ProfileController::class, 'destroyPhoto'])
        ->name('profile.photo.destroy');
});


require __DIR__.'/auth.php';

// Manuscript – Naicel (2026-10-08): the manuscript modules (docs/manuscript-phase-plan.md) have their own route file.
require __DIR__.'/manuscript.php';

// Thesis panel revisions (2026-10-10): docs/panel-revisions.md.
require __DIR__.'/panel.php';
