# Graph Report - iclimate_system  (2026-09-12)

## Corpus Check
- cluster-only mode — file stats not available

## Summary
- 1932 nodes · 4360 edges · 218 communities (156 shown, 62 thin omitted)
- Extraction: 96% EXTRACTED · 4% INFERRED · 0% AMBIGUOUS · INFERRED: 173 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `c199011c`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- Illuminate\Support\Collection
- main.py
- Illuminate\Database\Eloquent\Model
- LianBarangays
- AIOrchestratorService
- PredictionService
- Illuminate\Database\Eloquent\Factories\Factory
- users entity
- devDependencies
- FarmerDashboardWeatherService
- Illuminate\Http\Request
- Illuminate\Database\Eloquent\Builder
- User
- app.js
- Illuminate\Foundation\Testing\RefreshDatabase
- PlantingAdvisory
- web.php
- User.php
- Controller
- Illuminate\Http\RedirectResponse
- ExternalWeatherData
- MessagingController
- route
- monthly_weather_random_forest.py
- Illuminate\Database\Eloquent\Relations\HasMany
- RegisteredUserController.php
- Phase 3 Revised Manuscript Draft
- Illuminate\View\View
- bootstrap/app.php
- TestCase.php
- DecisionSupportService
- AIChatTest
- Carbon\CarbonImmutable
- generate_iclimate_figures.py
- Illuminate\Database\Schema\Blueprint
- Illuminate\Support\Facades\Http
- Agile Requirements Phase
- Illuminate\Http\JsonResponse
- FarmerProfile
- HeatmapRiskService
- scripts
- Illuminate\Database\Migrations\Migration
- CaptchaService
- UsabilitySurveyController
- WeatherPredictionController.php
- PredictionParityTest.php
- Illuminate\Support\Facades\Schema
- Notification
- RiceProduction
- DecisionTreeRegressor
- FarmerDashboardWeatherTest
- UsabilitySurveyResponse
- LianBarangayWeatherService
- composer.json
- CommunityFeedController
- HeatmapArea
- MonthlyWeatherRandomForest
- RiceYieldPredictionService
- PHPUnit\Framework\TestCase
- FarmerOrganization
- build_docx_from_md.py
- AIHybridOrchestratorTest
- PredictionEngine
- WeatherApiService
- RegistrationTest
- LianHeatmapController
- ProfileController.php
- KnowledgeBase
- require-dev
- Illuminate\Database\Seeder
- KnowledgeBaseSeeder
- predict.py
- ModelEvaluationService
- WeatherLabeler
- WeatherIconMapperTest
- LianFarmTypes
- setup
- EmailVerificationTest.php
- UsabilitySurveyTest
- AgriculturalRiskScorerTest
- LoginRequest
- config
- require
- CommunityFeedAuthorizationTest
- FarmBoundaryMeasurementService
- FeatureCatalog
- iClimate Use Cases
- generate_smoketest_data.py
- psr-4
- logging.php
- Figure 1: Theoretical Framework
- Figure 2: Conceptual Framework
- Stakeholder Needs
- app.blade.php
- console.php
- profile/edit.blade.php
- Rain Weather Icon
- Graphify Knowledge Graph Rule
- keywords
- post-create-project-cmd
- rice_productions entity
- dashboards.partials.climate-trend-table
- layouts.partials.dark-workspace
- layouts.partials.sidebar-brand
- iClimate Icon Asset
- IT Personnel Dash Image Asset
- Rice Farmer Avatar Asset
- Clear Day Weather Icon
- Partly Cloudy Day Weather Icon
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
- Needs Verification Claims Tracking
- EvaluatesYieldModel
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
1. `User` - 168 edges
2. `PlantingAdvisory` - 90 edges
3. `PredictionService` - 45 edges
4. `FarmerProfile` - 44 edges
5. `ClimateRecord` - 42 edges
6. `TestCase` - 36 edges
7. `ExternalWeatherData` - 35 edges
8. `Controller` - 34 edges
9. `FarmerDashboardWeatherService` - 34 edges
10. `HeatmapArea` - 30 edges

## Surprising Connections (you probably didn't know these)
- `Community Feed and Messaging Feature` --conceptually_related_to--> `Phase 3 Revised Manuscript Draft`  [INFERRED]
  README.md → docs/phase3_revised_manuscript.md
- `AgriculturalRiskScorerTest` --references--> `AgriculturalRiskScorer`  [EXTRACTED]
  tests/Unit/AgriculturalRiskScorerTest.php → app/Services/Risk/AgriculturalRiskScorer.php
- `iClimate Chapters 1-3 Rendered PDF Document` --references--> `iClimate Chapters 1-3 Master Manuscript`  [EXTRACTED]
  docs/capstone/ICLIMATE_CHAPTERS_1_TO_3.pdf → docs/capstone/ICLIMATE_CHAPTERS_1_TO_3_MASTER.md
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

## Communities (218 total, 62 thin omitted)

### Community 0 - "Illuminate\Support\Collection"
Cohesion: 0.06
Nodes (16): CheckGroqApi, ExpireAgriculturalAdvisories, ExportRiceYieldTrainingData, FetchOnlineWeather, FetchPagasaAdvisories, GenerateAgriculturalAdvisories, RefreshLianBarangayWeather, RefreshLianBoundaries (+8 more)

### Community 1 - "main.py"
Cohesion: 0.06
Nodes (41): BaseModel, DataFrame, FastAPI, get, Pipeline, post, build_pipeline(), evaluate() (+33 more)

### Community 2 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.07
Nodes (10): AIChat, Announcement, FeedComment, FeedMedia, FeedReaction, TyphoonSafetyResponse, Illuminate\Database\Eloquent\Factories\HasFactory, Illuminate\Database\Eloquent\Model (+2 more)

### Community 3 - "LianBarangays"
Cohesion: 0.08
Nodes (5): FarmerProfileController, LiveForecastingController, PagasaAdvisoryService, IntentDetectionService, LianBarangays

### Community 4 - "AIOrchestratorService"
Cohesion: 0.13
Nodes (7): AIOrchestratorService, CarbonImmutable, GroqChatService, IntentDetectionService, IntentClassifierService, GroqChatService, IntentDetectionService

### Community 5 - "PredictionService"
Cohesion: 0.11
Nodes (6): CarbonImmutable, DecisionSupportService, IntentDetectionService, PredictionDateValidator, User, PredictionService

### Community 6 - "Illuminate\Database\Eloquent\Factories\Factory"
Cohesion: 0.07
Nodes (14): AnnouncementFactory, ClimateRecordFactory, FarmerProfileFactory, HeatmapAreaFactory, PlantingAdvisoryFactory, ReportFactory, RiceProductionFactory, SystemLogFactory (+6 more)

### Community 7 - "users entity"
Cohesion: 0.05
Nodes (39): Figure 4: System Architecture, Figure 7: Context Diagram, Application Layer, Data Layer, External Services Layer, ML/Python Layer, Presentation Layer, Service Layer (+31 more)

### Community 8 - "devDependencies"
Cohesion: 0.05
Nodes (36): alpinejs, autoprefixer, axios, bootstrap, chart.js, concurrently, laravel-vite-plugin, leaflet (+28 more)

### Community 9 - "FarmerDashboardWeatherService"
Cohesion: 0.13
Nodes (8): AppServiceProvider, FarmerDashboardWeatherService, Carbon\CarbonInterface, Illuminate\Pagination\Paginator, Illuminate\Support\Carbon, Illuminate\Support\ServiceProvider, PDO, WeatherIconMapper

### Community 10 - "Illuminate\Http\Request"
Cohesion: 0.13
Nodes (5): AIChatController, CrudController, SystemLogController, UserManagementController, Illuminate\Http\Request

### Community 11 - "Illuminate\Database\Eloquent\Builder"
Cohesion: 0.11
Nodes (3): ReportController, Illuminate\Database\Eloquent\Builder, Symfony\Component\HttpFoundation\StreamedResponse

### Community 12 - "User"
Cohesion: 0.11
Nodes (4): User, Illuminate\Foundation\Auth\User, AuthenticationTest, ProfileTest

### Community 13 - "app.js"
Cohesion: 0.11
Nodes (27): bindChartTabs(), bindDeleteConfirmations(), bindFastNavigation(), bindGestureFeedback(), bindLoadingForms(), bindLogoutConfirmations(), bindMobileNavigation(), bindNavbarState() (+19 more)

### Community 14 - "Illuminate\Foundation\Testing\RefreshDatabase"
Cohesion: 0.10
Nodes (9): Illuminate\Foundation\Testing\RefreshDatabase, Illuminate\Foundation\Testing\TestCase, PasswordConfirmationTest, PasswordUpdateTest, ExampleTest, PlantingAdvisoryBarangayTest, RoleAuthorizationTest, SecurityHardeningTest (+1 more)

### Community 15 - "PlantingAdvisory"
Cohesion: 0.10
Nodes (6): AutomationStatus, RunIClimateAutomation, Advisory, PlantingAdvisory, SystemLog, TyphoonSafetyService

### Community 16 - "web.php"
Cohesion: 0.09
Nodes (6): ClimateRecordController, HeatmapAreaController, NotificationController, RiceProductionController, TyphoonSafetyController, CrudController

### Community 17 - "User.php"
Cohesion: 0.17
Nodes (6): ClimateRecord, DatabaseSeeder, Illuminate\Database\Console\Seeds\WithoutModelEvents, Illuminate\Database\Eloquent\Factories\Sequence, Illuminate\Notifications\Notifiable, Illuminate\Support\Arr

### Community 18 - "Controller"
Cohesion: 0.10
Nodes (12): ConfirmablePasswordController, EmailVerificationNotificationController, EmailVerificationPromptController, PasswordController, PasswordResetLinkController, VerifyEmailController, Controller, PredictionController (+4 more)

### Community 19 - "Illuminate\Http\RedirectResponse"
Cohesion: 0.21
Nodes (3): PlantingAdvisoryController, SystemAuditLogger, Illuminate\Http\RedirectResponse

### Community 21 - "MessagingController"
Cohesion: 0.20
Nodes (4): MessagingController, Conversation, ConversationMessage, Illuminate\Support\Facades\Storage

### Community 22 - "route"
Cohesion: 0.17
Nodes (5): CalendarController, Carbon, route, TyphoonSafetyResponseValidationTest, WeatherPredictionTest

### Community 23 - "monthly_weather_random_forest.py"
Cohesion: 0.15
Nodes (24): date, ndarray, build_training_samples(), confidence(), feature_vector(), insights(), _leave_one_out_mae(), main() (+16 more)

### Community 24 - "Illuminate\Database\Eloquent\Relations\HasMany"
Cohesion: 0.11
Nodes (4): FeedPost, Illuminate\Database\Eloquent\Relations\HasMany, Illuminate\Http\UploadedFile, CommunicationPlatformTest

### Community 25 - "RegisteredUserController.php"
Cohesion: 0.16
Nodes (11): AuthenticatedSessionController, RegisteredUserController, Illuminate\Auth\Events\Lockout, Illuminate\Auth\Events\PasswordReset, Illuminate\Auth\Events\Registered, Illuminate\Contracts\Validation\ValidationRule, Illuminate\Support\Facades\Auth, Illuminate\Support\Facades\Hash (+3 more)

### Community 26 - "Phase 3 Revised Manuscript Draft"
Cohesion: 0.09
Nodes (23): Yield Prediction Algorithm Comparison (MLR vs RF vs GBDT), IPO Conceptual Framework, iClimate Chapters 1-3 Master Manuscript, Technical Stack Specification, DSS & Climate-Smart Theoretical Framework, iClimate Chapters 1-3 Rendered PDF Document, Advisory Generation Service, Advisory Rule Engine & Evaluation (+15 more)

### Community 27 - "Illuminate\View\View"
Cohesion: 0.14
Nodes (7): NewPasswordController, ModelEvaluationController, AppLayout, GuestLayout, Illuminate\Validation\Rule, Illuminate\View\Component, Illuminate\View\View

### Community 28 - "bootstrap/app.php"
Cohesion: 0.16
Nodes (11): Response, RoleMiddleware, SecurityHeaders, TrackUserActivity, Closure, Illuminate\Database\QueryException, Illuminate\Foundation\Application, Illuminate\Foundation\Configuration\Exceptions (+3 more)

### Community 29 - "TestCase.php"
Cohesion: 0.10
Nodes (4): Illuminate\Http\Client\ConnectionException, FarmerDashboardRealtimeWeatherTest, ReportsHeatmapNotificationsTest, ResponsiveLayoutTest

### Community 30 - "DecisionSupportService"
Cohesion: 0.16
Nodes (3): DecisionSupportService, AgriculturalRiskScorer, RiskParityTest

### Community 32 - "Carbon\CarbonImmutable"
Cohesion: 0.22
Nodes (4): RiceYieldPredictionController, ShortTermOutlookService, Carbon\CarbonImmutable, InvalidArgumentException

### Community 33 - "generate_iclimate_figures.py"
Cohesion: 0.37
Nodes (19): arrow(), Box, box_center(), edge_point(), figure_1(), figure_10(), figure_2(), figure_3() (+11 more)

### Community 35 - "Illuminate\Support\Facades\Http"
Cohesion: 0.25
Nodes (6): ClimateSnapshot, Illuminate\Support\Facades\Cache, Illuminate\Support\Facades\Http, Illuminate\Support\Facades\Log, RuntimeException, Throwable

### Community 36 - "Agile Requirements Phase"
Cohesion: 0.12
Nodes (18): Figure 3: Agile Methodology, Figure 10: Development Flow of iClimate, Agile Deployment Phase, Agile Design Phase, Agile Development Phase, Agile Iteration and Maintenance, Agile Planning Phase, Agile Requirements Phase (+10 more)

### Community 37 - "Illuminate\Http\JsonResponse"
Cohesion: 0.18
Nodes (5): LianBoundaryController, LianWeatherController, MobileAuthController, User, Illuminate\Http\JsonResponse

### Community 38 - "FarmerProfile"
Cohesion: 0.21
Nodes (4): FarmBoundary, FarmerProfile, FarmBoundaryFactory, FarmBoundaryTest

### Community 39 - "HeatmapRiskService"
Cohesion: 0.23
Nodes (4): HeatmapRiskService, DecisionSupportService, WeatherApiService, RiceProduction

### Community 40 - "scripts"
Cohesion: 0.12
Nodes (16): scripts, dev, post-autoload-dump, post-update-cmd, pre-package-uninstall, serve, test, Composer\\Config::disableProcessTimeout (+8 more)

### Community 41 - "Illuminate\Database\Migrations\Migration"
Cohesion: 0.13
Nodes (3): up(), Illuminate\Database\Migrations\Migration, Illuminate\Support\Facades\DB

### Community 43 - "UsabilitySurveyController"
Cohesion: 0.17
Nodes (5): FarmBoundaryController, User, UsabilitySurveyController, Controller, Illuminate\Http\Response

### Community 44 - "WeatherPredictionController.php"
Cohesion: 0.17
Nodes (5): WeatherPredictionController, RiceYieldPredictionRequest, WeatherPredictionRequest, WeatherPredictionService, Illuminate\Foundation\Http\FormRequest

### Community 45 - "PredictionParityTest.php"
Cohesion: 0.19
Nodes (5): CarbonImmutable, PredictionDateValidator, Illuminate\Http\Client\Request, Illuminate\Support\Facades\Config, PredictionParityTest

### Community 47 - "Notification"
Cohesion: 0.17
Nodes (5): Notification, NotificationFactory, Illuminate\Auth\Notifications\ResetPassword, Illuminate\Support\Facades\Notification, PasswordResetTest

### Community 53 - "composer.json"
Cohesion: 0.14
Nodes (13): autoload-dev, psr-4, description, extra, laravel, dont-discover, license, minimum-stability (+5 more)

### Community 54 - "CommunityFeedController"
Cohesion: 0.31
Nodes (3): CommunityFeedController, FeedPost, User

### Community 55 - "HeatmapArea"
Cohesion: 0.40
Nodes (3): DashboardController, HeatmapArea, Report

### Community 58 - "PHPUnit\Framework\TestCase"
Cohesion: 0.20
Nodes (3): PHPUnit\Framework\TestCase, ExampleTest, ShortTermOutlookServiceTest

### Community 59 - "FarmerOrganization"
Cohesion: 0.29
Nodes (3): FarmerOrganization, FarmerOrganizationSeeder, AdminDashboardOrganizationChartTest

### Community 60 - "build_docx_from_md.py"
Cohesion: 0.40
Nodes (10): document_body(), esc(), main(), paragraph(), run_text(), runs_from_inline(), strip_md(), table() (+2 more)

### Community 66 - "ProfileController.php"
Cohesion: 0.25
Nodes (3): ProfileController, ProfileUpdateRequest, Illuminate\Support\Facades\Redirect

### Community 68 - "require-dev"
Cohesion: 0.22
Nodes (9): require-dev, fakerphp/faker, laravel/breeze, laravel/pail, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision (+1 more)

### Community 69 - "Illuminate\Database\Seeder"
Cohesion: 0.31
Nodes (3): AdvisoryRuleSeeder, XamppLocalhostSeeder, Illuminate\Database\Seeder

### Community 71 - "predict.py"
Cohesion: 0.39
Nodes (8): build_features(), install_sklearn_compatibility_shims(), irrigation_recommendation(), main(), notifications(), parse_payload(), planting_advisory(), Allow models saved with older sklearn internals to load on this machine.

### Community 76 - "setup"
Cohesion: 0.25
Nodes (8): post-root-package-install, setup, composer install, npm install, npm run build, @php artisan key:generate, @php artisan migrate --force, @php -r \"file_exists('.env') || copy('.env.example', '.env');\

### Community 77 - "EmailVerificationTest.php"
Cohesion: 0.25
Nodes (4): Illuminate\Auth\Events\Verified, Illuminate\Support\Facades\Event, Illuminate\Support\Facades\URL, EmailVerificationTest

### Community 81 - "config"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 82 - "require"
Cohesion: 0.29
Nodes (7): require, barryvdh/laravel-dompdf, guzzlehttp/guzzle, laravel/framework, laravel/tinker, league/commonmark, php

### Community 83 - "CommunityFeedAuthorizationTest"
Cohesion: 0.52
Nodes (3): CommunityFeedAuthorizationTest, FeedPost, User

### Community 86 - "iClimate Use Cases"
Cohesion: 0.33
Nodes (6): Figure 6: Use Case Diagram, Actor: IT Expert / Admin, Actor: Farmer, Actor: MAO Personnel, External Services (Weather/AI Providers), iClimate Use Cases

### Community 87 - "generate_smoketest_data.py"
Cohesion: 0.47
Nodes (5): climate_for(), main(), SMOKE TEST DATA GENERATOR — NOT REAL DATA. Generates a purely synthetic,…, Deliberately-learnable synthetic relationship (not real agronomy) so the smoke…, yield_for()

### Community 88 - "psr-4"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 89 - "logging.php"
Cohesion: 0.40
Nodes (4): Monolog\Handler\NullHandler, Monolog\Handler\StreamHandler, Monolog\Handler\SyslogUdpHandler, Monolog\Processor\PsrLogMessageProcessor

### Community 90 - "Figure 1: Theoretical Framework"
Cohesion: 0.80
Nodes (5): Figure 1: Theoretical Framework, Climate and Farm Inputs, Decision-Support Outcome, iClimate Processes, Theoretical Basis (DSS, CSA, Predictive Modeling)

### Community 91 - "Figure 2: Conceptual Framework"
Cohesion: 0.70
Nodes (5): Figure 2: Conceptual Framework, Input Stage (PAGASA, Open-Meteo, MAO Records), Outcome (Climate-Informed Decision Support for Lian Batangas), Output Stage (Yield Estimates, Advisories, Heatmap), Process Stage (Validation, Modeling, Advisories, Risk)

### Community 92 - "Stakeholder Needs"
Cohesion: 0.50
Nodes (5): Figure 5: Requirement Analysis, Functional Requirements, Non-Functional Requirements, Stakeholder Needs, System Modules

### Community 93 - "app.blade.php"
Cohesion: 0.50
Nodes (3): components.ai-chat-widget, layouts.navigation, layouts.sidebar

### Community 94 - "console.php"
Cohesion: 0.50
Nodes (3): Illuminate\Foundation\Inspiring, Illuminate\Support\Facades\Artisan, Illuminate\Support\Facades\Schedule

### Community 95 - "profile/edit.blade.php"
Cohesion: 0.50
Nodes (3): profile.partials.delete-user-form, profile.partials.update-password-form, profile.partials.update-profile-information-form

### Community 96 - "Rain Weather Icon"
Cohesion: 0.50
Nodes (4): Heavy Rain Weather Icon, Light Rain Weather Icon, Rain Weather Icon, Thunderstorm Weather Icon

### Community 97 - "Graphify Knowledge Graph Rule"
Cohesion: 0.67
Nodes (3): Graphify Query and Path Workflow, Graphify Knowledge Graph Rule, Graphify Pipeline Workflow

### Community 98 - "keywords"
Cohesion: 0.67
Nodes (3): keywords, framework, laravel

### Community 99 - "post-create-project-cmd"
Cohesion: 0.50
Nodes (4): post-create-project-cmd, @php artisan key:generate --ansi, @php artisan migrate --graceful --ansi, @php -r \"file_exists('database/database.sqlite') || touch('database/database.sqlite');\

### Community 116 - "rice_productions entity"
Cohesion: 0.67
Nodes (3): climate_records entity, heatmap_areas entity, rice_productions entity

## Knowledge Gaps
- **161 isolated node(s):** `dashboards.partials.climate-trend-table`, `layouts.partials.dark-workspace`, `layouts.partials.sidebar-brand`, `dashboards.partials.farmer-organization-chart`, `dashboards.partials.farmer-organization-chart` (+156 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **62 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User` to `Illuminate\Support\Collection`, `Illuminate\Database\Eloquent\Model`, `LianBarangays`, `PredictionService`, `Illuminate\Database\Eloquent\Factories\Factory`, `Illuminate\Http\Request`, `Illuminate\Foundation\Testing\RefreshDatabase`, `web.php`, `User.php`, `Illuminate\Http\RedirectResponse`, `ExternalWeatherData`, `MessagingController`, `route`, `Illuminate\Database\Eloquent\Relations\HasMany`, `RegisteredUserController.php`, `Illuminate\View\View`, `bootstrap/app.php`, `Carbon\CarbonImmutable`, `Illuminate\Support\Facades\Http`, `PredictionParityTest.php`, `Notification`, `RiceProduction`, `RiceYieldPredictionService`, `ProfileController.php`, `EmailVerificationTest.php`?**
  _High betweenness centrality (0.146) - this node is a cross-community bridge._
- **Why does `PlantingAdvisory` connect `PlantingAdvisory` to `Illuminate\Support\Collection`, `Illuminate\Database\Eloquent\Model`, `Illuminate\Support\Facades\Http`, `LianBarangays`, `AIOrchestratorService`, `PredictionService`, `Illuminate\Database\Eloquent\Factories\Factory`, `Illuminate\Database\Eloquent\Builder`, `web.php`, `User.php`, `RiceProduction`, `Illuminate\Http\RedirectResponse`, `ExternalWeatherData`, `TestCase.php`, `route`, `HeatmapArea`, `Illuminate\View\View`, `AIHybridOrchestratorTest`?**
  _High betweenness centrality (0.050) - this node is a cross-community bridge._
- **Why does `ClimateRecord` connect `User.php` to `Illuminate\Support\Collection`, `Carbon\CarbonImmutable`, `Illuminate\Database\Eloquent\Model`, `Illuminate\Support\Facades\Http`, `Illuminate\Database\Eloquent\Factories\Factory`, `HeatmapRiskService`, `ModelEvaluationService`, `Illuminate\Database\Eloquent\Builder`, `PredictionParityTest.php`, `web.php`, `AIHybridOrchestratorTest`, `route`, `HeatmapArea`, `MonthlyWeatherRandomForest`, `console.php`, `TestCase.php`, `PredictionEngine`, `AIChatTest`?**
  _High betweenness centrality (0.049) - this node is a cross-community bridge._
- **Are the 15 inferred relationships involving `PlantingAdvisory` (e.g. with `.adminAggregates()` and `.farmer()`) actually correct?**
  _`PlantingAdvisory` has 15 INFERRED edges - model-reasoned connections that need verification._
- **What connects `dashboards.partials.climate-trend-table`, `layouts.partials.dark-workspace`, `layouts.partials.sidebar-brand` to the rest of the system?**
  _161 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Illuminate\Support\Collection` be split into smaller, more focused modules?**
  _Cohesion score 0.0579476861167002 - nodes in this community are weakly interconnected._
- **Should `main.py` be split into smaller, more focused modules?**
  _Cohesion score 0.0603921568627451 - nodes in this community are weakly interconnected._