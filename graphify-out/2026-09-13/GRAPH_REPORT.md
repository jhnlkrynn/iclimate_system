# Graph Report - iclimate_system  (2026-09-13)

## Corpus Check
- cluster-only mode — file stats not available

## Summary
- 2062 nodes · 4680 edges · 240 communities (166 shown, 74 thin omitted)
- Extraction: 94% EXTRACTED · 6% INFERRED · 0% AMBIGUOUS · INFERRED: 263 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `c199011c`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- FarmerOrganization
- Illuminate\Database\Eloquent\Model
- RegisteredUserController.php
- AIOrchestratorService
- PredictionService
- users entity
- LianBarangays
- User
- User.php
- Illuminate\Database\Eloquent\Factories\Factory
- devDependencies
- app.js
- Illuminate\Http\Request
- route
- TestCase.php
- Illuminate\Console\Command
- Illuminate\View\View
- Illuminate\Http\JsonResponse
- Illuminate\Http\RedirectResponse
- FarmerProfile
- FarmerDashboardWeatherService
- Illuminate\Support\Facades\Cache
- TestCase
- Controller
- AIChatTest
- monthly_weather_random_forest.py
- PlantingAdvisory
- ExternalWeatherData
- Phase 3 Revised Manuscript Draft
- Illuminate\Support\Collection
- Carbon\CarbonImmutable
- bootstrap/app.php
- HeatmapArea
- AnalyticsRun
- Report
- generate_iclimate_figures.py
- ReportController
- PagasaAdvisoryService
- SystemAuditLogger
- Agile Requirements Phase
- analytics_tasks.py
- .rule
- ClimateRecord
- AgriculturalRiskScorer
- HeatmapRiskService
- Illuminate\Database\Migrations\Migration
- rice_yield_service.py
- OpenMeteoService
- RiceYieldPredictionController.php
- scripts
- PHPUnit\Framework\TestCase
- LiveForecastingTest
- Notification
- DecisionTreeRegressor
- FarmerDashboardWeatherTest
- UsabilitySurveyResponse
- RoleAssistantService
- LianBarangayWeatherService
- main.py
- composer.json
- CaptchaService
- WeatherApiService
- GroqChatService
- DecisionSupportService
- test_smoke.py
- .index
- FeedPost
- RiceYieldPredictionService
- build_docx_from_md.py
- AIHybridOrchestratorTest
- Illuminate\Support\Facades\Schema
- Illuminate\Database\Schema\Blueprint
- compare_yield_features.py
- SystemLog
- LianHeatmapController
- RunPythonAnalytics.php
- RiceProduction
- require-dev
- predict.py
- PredictionParityTest
- ModelEvaluationService
- WeatherLabeler
- ShortTermOutlookService
- MonthlyWeatherCalendarService
- setup
- EmailVerificationTest.php
- UsabilitySurveyTest
- LoginRequest
- AppServiceProvider
- config
- require
- FarmBoundaryMeasurementService
- FeatureCatalog
- iClimate Use Cases
- generate_smoketest_data.py
- AnalyticsResultPersister
- psr-4
- logging.php
- OnlineAgriculturalAdvisorySystemTest.php
- Figure 1: Theoretical Framework
- Figure 2: Conceptual Framework
- Stakeholder Needs
- app.blade.php
- post-create-project-cmd
- console.php
- profile/edit.blade.php
- Rain Weather Icon
- Graphify Knowledge Graph Rule
- keywords
- rice_productions entity
- dashboards.partials.climate-trend-table
- layouts.partials.dark-workspace
- layouts.partials.sidebar-brand
- iClimate Icon Asset
- IT Personnel Dash Image Asset
- Rice Farmer Avatar Asset
- Clear Day Weather Icon
- Partly Cloudy Day Weather Icon
- advisories/index.blade.php
- management.blade.php
- it-expert.blade.php
- mao.blade.php
- 401.blade.php
- 402.blade.php
- 403.blade.php
- 404.blade.php
- 419.blade.php
- 429.blade.php
- 500.blade.php
- 503.blade.php
- User
- PredictionDateValidator
- Needs Verification Claims Tracking
- EvaluatesYieldModel
- Illuminate\Support\Facades\File
- Illuminate\Support\Facades\Process
- AI Assistant Image Asset
- DA Logo Image Asset
- Rice About Farmer Image Asset
- Rice Contact Golden Field Image Asset
- Rice Features Paddy Image Asset
- Rice Hero Aerial Image Asset
- Rice Roles Terrace Image Asset
- Fog Weather Icon
- Overcast Weather Icon
- Unavailable Weather Icon
- Web Crawler Robots Configuration
- Snapshots Directory Context
- Snapshots AI Sponsorship and Support

## God Nodes (most connected - your core abstractions)
1. `User` - 164 edges
2. `PlantingAdvisory` - 70 edges
3. `PredictionService` - 45 edges
4. `FarmerProfile` - 41 edges
5. `ClimateRecord` - 40 edges
6. `ExternalWeatherData` - 34 edges
7. `AnalyticsRun` - 33 edges
8. `TestCase` - 30 edges
9. `LianBarangays` - 30 edges
10. `FarmerDashboardWeatherService` - 29 edges

## Surprising Connections (you probably didn't know these)
- `Community Feed and Messaging Feature` --conceptually_related_to--> `Phase 3 Revised Manuscript Draft`  [INFERRED]
  README.md → docs/phase3_revised_manuscript.md
- `iClimate Chapters 1-3 Rendered PDF Document` --references--> `iClimate Chapters 1-3 Master Manuscript`  [EXTRACTED]
  docs/capstone/ICLIMATE_CHAPTERS_1_TO_3.pdf → docs/capstone/ICLIMATE_CHAPTERS_1_TO_3_MASTER.md
- `AgriculturalRiskScorerTest` --references--> `AgriculturalRiskScorer`  [EXTRACTED]
  tests/Unit/AgriculturalRiskScorerTest.php → app/Services/Risk/AgriculturalRiskScorer.php
- `Yield Prediction Algorithm Comparison (MLR vs RF vs GBDT)` --semantically_similar_to--> `Rice Yield Prediction & Random Forest Model`  [INFERRED] [semantically similar]
  docs/capstone/ICLIMATE_CHAPTERS_1_TO_3_MASTER.md → docs/phase3_revised_manuscript.md
- `Online Agricultural Advisory System Overview` --conceptually_related_to--> `Phase 3 Revised Manuscript Draft`  [INFERRED]
  docs/online-agricultural-advisories.md → docs/phase3_revised_manuscript.md

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Agricultural Advisory and Weather Processing Pipeline** — docs_online_agricultural_advisories_open_meteo_service, docs_online_agricultural_advisories_advisory_generation_service, docs_online_agricultural_advisories_advisory_rule_engine, docs_online_agricultural_advisories_approval_workflow [EXTRACTED 1.00]
- **Capstone Research and Manuscript Evolution Cycle** — docs_phase1_system_reverse_engineering_doc, docs_phase2_gap_analysis_overview, docs_phase3_revised_manuscript_draft, docs_capstone_iclimate_chapters_1_to_3_master_manuscript [EXTRACTED 1.00]
- **Decision Support and Yield Prediction Modeling** — docs_capstone_iclimate_chapters_1_to_3_master_theoretical_framework, docs_capstone_iclimate_chapters_1_to_3_master_algorithm_comparison, docs_phase3_revised_manuscript_rice_yield_prediction [INFERRED 0.85]
- **Development and Lifecycle Methodology Group** — docs_figures_fig03_agile_methodology, docs_figures_fig05_requirement_analysis, docs_figures_fig10_development_flow [INFERRED 0.85]
- **Functional, Data Flow, and Schema Group** — docs_figures_fig06_use_case_diagram, fig08_dfd_level1, fig09_erd [INFERRED 0.85]
- **Theoretical and Conceptual Architecture Framework** — docs_figures_fig01_theoretical_framework, docs_figures_fig02_conceptual_framework, docs_figures_fig04_system_architecture, docs_figures_fig07_context_diagram [INFERRED 0.85]

## Communities (240 total, 74 thin omitted)

### Community 0 - "FarmerOrganization"
Cohesion: 0.06
Nodes (10): FarmerOrganization, KnowledgeBase, IntentDetectionService, KnowledgeBaseService, LianFarmTypes, FarmerOrganizationSeeder, KnowledgeBaseSeeder, XamppLocalhostSeeder (+2 more)

### Community 1 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.09
Nodes (9): Announcement, FeedComment, FeedMedia, FeedReaction, Illuminate\Database\Eloquent\Factories\HasFactory, Illuminate\Database\Eloquent\Model, Illuminate\Database\Eloquent\Relations\BelongsTo, Illuminate\Pagination\Paginator (+1 more)

### Community 2 - "RegisteredUserController.php"
Cohesion: 0.09
Nodes (13): LianBoundaryController, LianWeatherController, MobileAuthController, User, AuthenticatedSessionController, RegisteredUserController, Illuminate\Auth\Events\Lockout, Illuminate\Auth\Events\Registered (+5 more)

### Community 3 - "AIOrchestratorService"
Cohesion: 0.13
Nodes (7): AIOrchestratorService, CarbonImmutable, GroqChatService, IntentDetectionService, IntentClassifierService, GroqChatService, IntentDetectionService

### Community 4 - "PredictionService"
Cohesion: 0.11
Nodes (6): CarbonImmutable, DecisionSupportService, IntentDetectionService, PredictionDateValidator, User, PredictionService

### Community 5 - "users entity"
Cohesion: 0.05
Nodes (39): Figure 4: System Architecture, Figure 7: Context Diagram, Application Layer, Data Layer, External Services Layer, ML/Python Layer, Presentation Layer, Service Layer (+31 more)

### Community 6 - "LianBarangays"
Cohesion: 0.09
Nodes (7): FarmerProfileController, HeatmapAreaController, LiveForecastingController, RiceProductionController, LianBarangays, CrudController, Illuminate\Validation\Rule

### Community 7 - "User"
Cohesion: 0.08
Nodes (7): User, Illuminate\Database\Eloquent\Relations\HasMany, Illuminate\Foundation\Auth\User, Illuminate\Notifications\Notifiable, PasswordConfirmationTest, ProfileTest, PythonAnalyticsQueueTest

### Community 8 - "User.php"
Cohesion: 0.14
Nodes (9): Illuminate\Foundation\Testing\RefreshDatabase, Illuminate\Http\Client\ConnectionException, Illuminate\Http\Client\Request, Illuminate\Pagination\LengthAwarePaginator, Illuminate\Support\Arr, Illuminate\Support\Carbon, Illuminate\Support\Facades\Config, Illuminate\Support\Facades\Queue (+1 more)

### Community 9 - "Illuminate\Database\Eloquent\Factories\Factory"
Cohesion: 0.08
Nodes (12): AnnouncementFactory, ClimateRecordFactory, FarmerProfileFactory, HeatmapAreaFactory, PlantingAdvisoryFactory, ReportFactory, RiceProductionFactory, SystemLogFactory (+4 more)

### Community 10 - "devDependencies"
Cohesion: 0.06
Nodes (34): alpinejs, autoprefixer, axios, bootstrap, concurrently, laravel-vite-plugin, dependencies, bootstrap (+26 more)

### Community 11 - "app.js"
Cohesion: 0.11
Nodes (28): bindChartTabs(), bindDeleteConfirmations(), bindFastNavigation(), bindGestureFeedback(), bindLoadingForms(), bindLogoutConfirmations(), bindMobileNavigation(), bindNavbarState() (+20 more)

### Community 12 - "Illuminate\Http\Request"
Cohesion: 0.15
Nodes (5): AIChatController, CrudController, SystemLogController, UserManagementController, Illuminate\Http\Request

### Community 13 - "route"
Cohesion: 0.12
Nodes (4): route, AuthenticationTest, CommunicationPlatformTest, TyphoonSafetyResponseValidationTest

### Community 14 - "TestCase.php"
Cohesion: 0.09
Nodes (7): AdminDashboardOrganizationChartTest, CommunityFeedAuthorizationTest, FeedPost, User, FarmerDashboardRealtimeWeatherTest, ResponsiveLayoutTest, WeatherApiServiceTest

### Community 15 - "Illuminate\Console\Command"
Cohesion: 0.11
Nodes (11): CheckGroqApi, ExpireAgriculturalAdvisories, ExportRiceYieldTrainingData, FetchPagasaAdvisories, GenerateAgriculturalAdvisories, QueueDatasetProcessing, RefreshLianBarangayWeather, RefreshLianBoundaries (+3 more)

### Community 16 - "Illuminate\View\View"
Cohesion: 0.09
Nodes (12): ModelEvaluationController, ProfileController, UsabilitySurveyController, ProfileUpdateRequest, AppLayout, GuestLayout, Illuminate\Contracts\Validation\ValidationRule, Illuminate\Http\Response (+4 more)

### Community 17 - "Illuminate\Http\JsonResponse"
Cohesion: 0.19
Nodes (6): MessagingController, Conversation, ConversationMessage, Illuminate\Http\JsonResponse, Illuminate\Http\UploadedFile, Illuminate\Support\Facades\Storage

### Community 18 - "Illuminate\Http\RedirectResponse"
Cohesion: 0.19
Nodes (5): User, PlantingAdvisoryController, TyphoonSafetyController, Illuminate\Http\RedirectResponse, PlantingAdvisory

### Community 19 - "FarmerProfile"
Cohesion: 0.12
Nodes (8): FarmBoundary, FarmerProfile, FarmBoundaryFactory, DatabaseSeeder, Illuminate\Database\Console\Seeds\WithoutModelEvents, Illuminate\Database\Eloquent\Factories\Sequence, Illuminate\Database\Eloquent\Relations\HasOne, FarmBoundaryTest

### Community 20 - "FarmerDashboardWeatherService"
Cohesion: 0.22
Nodes (3): FarmerDashboardWeatherService, Carbon, WeatherIconMapper

### Community 21 - "Illuminate\Support\Facades\Cache"
Cohesion: 0.17
Nodes (7): Illuminate\Support\Facades\Cache, Illuminate\Support\Facades\Http, Illuminate\Support\Facades\Log, Illuminate\Support\Str, Pdo\Mysql, RuntimeException, Throwable

### Community 22 - "TestCase"
Cohesion: 0.10
Nodes (7): Illuminate\Foundation\Testing\TestCase, PasswordUpdateTest, ExampleTest, PlantingAdvisoryBarangayTest, RoleAuthorizationTest, SecurityHardeningTest, TestCase

### Community 23 - "Controller"
Cohesion: 0.08
Nodes (15): CaptchaImageController, ConfirmablePasswordController, EmailVerificationNotificationController, EmailVerificationPromptController, NewPasswordController, PasswordController, PasswordResetLinkController, VerifyEmailController (+7 more)

### Community 25 - "monthly_weather_random_forest.py"
Cohesion: 0.15
Nodes (24): date, ndarray, build_training_samples(), confidence(), feature_vector(), insights(), _leave_one_out_mae(), main() (+16 more)

### Community 26 - "PlantingAdvisory"
Cohesion: 0.09
Nodes (3): Advisory, PlantingAdvisory, Illuminate\Database\Eloquent\Builder

### Community 28 - "Phase 3 Revised Manuscript Draft"
Cohesion: 0.09
Nodes (23): Yield Prediction Algorithm Comparison (MLR vs RF vs GBDT), IPO Conceptual Framework, iClimate Chapters 1-3 Master Manuscript, Technical Stack Specification, DSS & Climate-Smart Theoretical Framework, iClimate Chapters 1-3 Rendered PDF Document, Advisory Generation Service, Advisory Rule Engine & Evaluation (+15 more)

### Community 29 - "Illuminate\Support\Collection"
Cohesion: 0.25
Nodes (3): AdvisoryRule, AdvisoryRuleEngine, Illuminate\Support\Collection

### Community 30 - "Carbon\CarbonImmutable"
Cohesion: 0.17
Nodes (7): MonthlyWeatherRandomForest, CarbonImmutable, CarbonImmutable, PredictionDateValidator, WeatherPredictionService, Carbon\CarbonImmutable, InvalidArgumentException

### Community 31 - "bootstrap/app.php"
Cohesion: 0.16
Nodes (11): Response, RoleMiddleware, SecurityHeaders, TrackUserActivity, Closure, Illuminate\Database\QueryException, Illuminate\Foundation\Application, Illuminate\Foundation\Configuration\Exceptions (+3 more)

### Community 32 - "HeatmapArea"
Cohesion: 0.21
Nodes (3): DashboardController, HeatmapArea, TyphoonSafetyResponse

### Community 35 - "generate_iclimate_figures.py"
Cohesion: 0.37
Nodes (19): arrow(), Box, box_center(), edge_point(), figure_1(), figure_10(), figure_2(), figure_3() (+11 more)

### Community 38 - "SystemAuditLogger"
Cohesion: 0.20
Nodes (3): FarmBoundaryController, NotificationController, SystemAuditLogger

### Community 39 - "Agile Requirements Phase"
Cohesion: 0.12
Nodes (18): Figure 3: Agile Methodology, Figure 10: Development Flow of iClimate, Agile Deployment Phase, Agile Design Phase, Agile Development Phase, Agile Iteration and Maintenance, Agile Planning Phase, Agile Requirements Phase (+10 more)

### Community 40 - "analytics_tasks.py"
Cohesion: 0.26
Nodes (15): advisory_ai(), dataset_processing(), _day(), heatmap_risk(), _model_input(), _number(), Pure-Python analytics handlers called only by queued Laravel jobs., report_analytics() (+7 more)

### Community 41 - ".rule"
Cohesion: 0.17
Nodes (4): ClimateRecordController, CommunityFeedController, FeedPost, User

### Community 42 - "ClimateRecord"
Cohesion: 0.22
Nodes (4): ClimateRecord, PredictionEngine, PythonService, ClimateSnapshot

### Community 43 - "AgriculturalRiskScorer"
Cohesion: 0.13
Nodes (3): AgriculturalRiskScorer, RiskParityTest, AgriculturalRiskScorerTest

### Community 44 - "HeatmapRiskService"
Cohesion: 0.23
Nodes (4): HeatmapRiskService, DecisionSupportService, WeatherApiService, RiceProduction

### Community 45 - "Illuminate\Database\Migrations\Migration"
Cohesion: 0.15
Nodes (3): up(), Illuminate\Database\Migrations\Migration, Illuminate\Support\Facades\DB

### Community 46 - "rice_yield_service.py"
Cohesion: 0.23
Nodes (16): DataFrame, confidence_for(), _feature(), install_sklearn_compatibility_shims(), load_models(), predict(), Consolidated rice-yield + weather-label prediction logic, in-process. This is…, Keep these thresholds identical to WeatherLabeler::fromRainfall() in… (+8 more)

### Community 47 - "OpenMeteoService"
Cohesion: 0.20
Nodes (3): FetchOnlineWeather, AdvisoryGenerationService, OpenMeteoService

### Community 48 - "RiceYieldPredictionController.php"
Cohesion: 0.20
Nodes (4): RiceYieldPredictionController, RiceYieldPredictionRequest, WeatherPredictionRequest, Illuminate\Foundation\Http\FormRequest

### Community 49 - "scripts"
Cohesion: 0.12
Nodes (16): scripts, dev, post-autoload-dump, post-update-cmd, pre-package-uninstall, serve, test, Composer\\Config::disableProcessTimeout (+8 more)

### Community 50 - "PHPUnit\Framework\TestCase"
Cohesion: 0.15
Nodes (4): PHPUnit\Framework\TestCase, ExampleTest, ShortTermOutlookServiceTest, WeatherIconMapperTest

### Community 52 - "Notification"
Cohesion: 0.17
Nodes (5): Notification, NotificationFactory, Illuminate\Auth\Notifications\ResetPassword, Illuminate\Support\Facades\Notification, PasswordResetTest

### Community 58 - "main.py"
Cohesion: 0.22
Nodes (13): BaseModel, FastAPI, get, post, analytics(), forecast_monthly_weather(), health(), lifespan() (+5 more)

### Community 59 - "composer.json"
Cohesion: 0.14
Nodes (13): autoload-dev, psr-4, description, extra, laravel, dont-discover, license, minimum-stability (+5 more)

### Community 64 - "test_smoke.py"
Cohesion: 0.20
Nodes (5): evaluate(), _not_ready(), _pipeline(), Real, computed model-evaluation metrics — the replacement for…, Minimal smoke tests — not exhaustive coverage. Confirms the service boots and…

### Community 65 - ".index"
Cohesion: 0.25
Nodes (5): AnalyticsRun, AnalyticsRunController, WeatherPredictionController, Controller, PredictionDateValidator

### Community 68 - "build_docx_from_md.py"
Cohesion: 0.40
Nodes (10): document_body(), esc(), main(), paragraph(), run_text(), runs_from_inline(), strip_md(), table() (+2 more)

### Community 73 - "compare_yield_features.py"
Cohesion: 0.27
Nodes (8): Pipeline, build_pipeline(), evaluate(), main(), Compares Random Forest rice yield accuracy with vs. without humidity and…, build_pipeline(), main(), Trains and saves (via joblib) the rice-yield Random Forest pipeline, using the…

### Community 74 - "SystemLog"
Cohesion: 0.33
Nodes (3): AutomationStatus, SystemLog, TyphoonSafetyService

### Community 76 - "RunPythonAnalytics.php"
Cohesion: 0.31
Nodes (4): RunPythonAnalytics, PythonAnalyticsClient, Illuminate\Contracts\Queue\ShouldQueue, Illuminate\Foundation\Queue\Queueable

### Community 78 - "require-dev"
Cohesion: 0.22
Nodes (9): require-dev, fakerphp/faker, laravel/breeze, laravel/pail, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision (+1 more)

### Community 79 - "predict.py"
Cohesion: 0.39
Nodes (8): build_features(), install_sklearn_compatibility_shims(), irrigation_recommendation(), main(), notifications(), parse_payload(), planting_advisory(), Allow models saved with older sklearn internals to load on this machine.

### Community 85 - "setup"
Cohesion: 0.25
Nodes (8): post-root-package-install, setup, composer install, npm install, npm run build, @php artisan key:generate, @php artisan migrate --force, @php -r \"file_exists('.env') || copy('.env.example', '.env');\

### Community 86 - "EmailVerificationTest.php"
Cohesion: 0.25
Nodes (4): Illuminate\Auth\Events\Verified, Illuminate\Support\Facades\Event, Illuminate\Support\Facades\URL, EmailVerificationTest

### Community 90 - "config"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 91 - "require"
Cohesion: 0.29
Nodes (7): require, barryvdh/laravel-dompdf, guzzlehttp/guzzle, laravel/framework, laravel/tinker, league/commonmark, php

### Community 94 - "iClimate Use Cases"
Cohesion: 0.33
Nodes (6): Figure 6: Use Case Diagram, Actor: IT Expert / Admin, Actor: Farmer, Actor: MAO Personnel, External Services (Weather/AI Providers), iClimate Use Cases

### Community 95 - "generate_smoketest_data.py"
Cohesion: 0.47
Nodes (5): climate_for(), main(), SMOKE TEST DATA GENERATOR — NOT REAL DATA. Generates a purely synthetic,…, Deliberately-learnable synthetic relationship (not real agronomy) so the smoke…, yield_for()

### Community 97 - "psr-4"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 98 - "logging.php"
Cohesion: 0.40
Nodes (4): Monolog\Handler\NullHandler, Monolog\Handler\StreamHandler, Monolog\Handler\SyslogUdpHandler, Monolog\Processor\PsrLogMessageProcessor

### Community 100 - "Figure 1: Theoretical Framework"
Cohesion: 0.80
Nodes (5): Figure 1: Theoretical Framework, Climate and Farm Inputs, Decision-Support Outcome, iClimate Processes, Theoretical Basis (DSS, CSA, Predictive Modeling)

### Community 101 - "Figure 2: Conceptual Framework"
Cohesion: 0.70
Nodes (5): Figure 2: Conceptual Framework, Input Stage (PAGASA, Open-Meteo, MAO Records), Outcome (Climate-Informed Decision Support for Lian Batangas), Output Stage (Yield Estimates, Advisories, Heatmap), Process Stage (Validation, Modeling, Advisories, Risk)

### Community 102 - "Stakeholder Needs"
Cohesion: 0.50
Nodes (5): Figure 5: Requirement Analysis, Functional Requirements, Non-Functional Requirements, Stakeholder Needs, System Modules

### Community 103 - "app.blade.php"
Cohesion: 0.50
Nodes (3): components.ai-chat-widget, layouts.navigation, layouts.sidebar

### Community 104 - "post-create-project-cmd"
Cohesion: 0.50
Nodes (4): post-create-project-cmd, @php artisan key:generate --ansi, @php artisan migrate --graceful --ansi, @php -r \"file_exists('database/database.sqlite') || touch('database/database.sqlite');\

### Community 105 - "console.php"
Cohesion: 0.50
Nodes (3): Illuminate\Foundation\Inspiring, Illuminate\Support\Facades\Artisan, Illuminate\Support\Facades\Schedule

### Community 106 - "profile/edit.blade.php"
Cohesion: 0.50
Nodes (3): profile.partials.delete-user-form, profile.partials.update-password-form, profile.partials.update-profile-information-form

### Community 107 - "Rain Weather Icon"
Cohesion: 0.50
Nodes (4): Heavy Rain Weather Icon, Light Rain Weather Icon, Rain Weather Icon, Thunderstorm Weather Icon

### Community 108 - "Graphify Knowledge Graph Rule"
Cohesion: 0.67
Nodes (3): Graphify Query and Path Workflow, Graphify Knowledge Graph Rule, Graphify Pipeline Workflow

### Community 109 - "keywords"
Cohesion: 0.67
Nodes (3): keywords, framework, laravel

### Community 135 - "rice_productions entity"
Cohesion: 0.67
Nodes (3): climate_records entity, heatmap_areas entity, rice_productions entity

## Knowledge Gaps
- **164 isolated node(s):** `chart.js`, `leaflet`, `private`, `$schema`, `build` (+159 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **74 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Work-memory lessons

**Preferred sources** — corroborated by past sessions; start here.
- `ReportController` (7× useful, score=6.868421387)
- `DashboardController` (4× useful, score=3.937179907)
- `reports/index.blade.php` (4× useful, score=3.908864917)
- `WeatherPredictionController` (3× useful, score=2.979294734) _(code changed — re-verify)_
- `RunPythonAnalytics` (3× useful, score=2.959840185)
- `analytics_tasks.py` (2× useful, score=1.979689287)
- `HeatmapAreaController` (2× useful, score=1.979405571)
- `PlantingAdvisoryController` (2× useful, score=1.979405571)
- `ReportsHeatmapNotificationsTest` (2× useful, score=1.95542745)

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User` to `Illuminate\Database\Eloquent\Model`, `RegisteredUserController.php`, `PredictionService`, `LianBarangays`, `User.php`, `Illuminate\Database\Eloquent\Factories\Factory`, `Illuminate\Http\Request`, `route`, `Illuminate\View\View`, `Illuminate\Http\JsonResponse`, `Illuminate\Http\RedirectResponse`, `FarmerProfile`, `Illuminate\Support\Facades\Cache`, `TestCase`, `Controller`, `PlantingAdvisory`, `ExternalWeatherData`, `Illuminate\Support\Collection`, `Carbon\CarbonImmutable`, `bootstrap/app.php`, `HeatmapArea`, `AnalyticsRun`, `Report`, `PagasaAdvisoryService`, `SystemAuditLogger`, `Notification`, `FarmerDashboardWeatherTest`, `RoleAssistantService`, `RiceYieldPredictionService`, `EmailVerificationTest.php`, `OnlineAgriculturalAdvisorySystemTest.php`?**
  _High betweenness centrality (0.103) - this node is a cross-community bridge._
- **Why does `PlantingAdvisory` connect `PlantingAdvisory` to `Illuminate\Database\Eloquent\Model`, `AIOrchestratorService`, `PredictionService`, `LianBarangays`, `User.php`, `Illuminate\Database\Eloquent\Factories\Factory`, `route`, `FarmerProfile`, `Illuminate\Support\Facades\Cache`, `ExternalWeatherData`, `Illuminate\Support\Collection`, `HeatmapArea`, `AnalyticsRun`, `ReportController`, `PagasaAdvisoryService`, `.rule`, `OpenMeteoService`, `RoleAssistantService`, `.index`, `FeedPost`, `AIHybridOrchestratorTest`, `SystemLog`, `OnlineAgriculturalAdvisorySystemTest.php`?**
  _High betweenness centrality (0.048) - this node is a cross-community bridge._
- **Why does `LianBarangays` connect `LianBarangays` to `FarmerOrganization`, `.index`, `AIOrchestratorService`, `PredictionService`, `PagasaAdvisoryService`, `User.php`, `Illuminate\Http\RedirectResponse`, `Illuminate\Support\Facades\Cache`, `RoleAssistantService`, `LianBarangayWeatherService`?**
  _High betweenness centrality (0.023) - this node is a cross-community bridge._
- **Are the 22 inferred relationships involving `User` (e.g. with `.test_farmer_dashboard_renders_live_weather_and_real_counts()` and `.test_live_weather_endpoint_wraps_payload_for_frontend_clients()`) actually correct?**
  _`User` has 22 INFERRED edges - model-reasoned connections that need verification._
- **Are the 13 inferred relationships involving `PlantingAdvisory` (e.g. with `.adminAggregates()` and `.maoAggregates()`) actually correct?**
  _`PlantingAdvisory` has 13 INFERRED edges - model-reasoned connections that need verification._
- **What connects `chart.js`, `leaflet`, `private` to the rest of the system?**
  _164 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `FarmerOrganization` be split into smaller, more focused modules?**
  _Cohesion score 0.05779220779220779 - nodes in this community are weakly interconnected._