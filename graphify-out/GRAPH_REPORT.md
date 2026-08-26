# Graph Report - iclimate_system  (2026-08-26)

## Corpus Check
- Large corpus: 327 files · ~543,270 words. Semantic extraction will be expensive (many Claude tokens). Consider running on a subfolder.

## Summary
- 1723 nodes · 4069 edges · 199 communities (147 shown, 52 thin omitted)
- Extraction: 96% EXTRACTED · 4% INFERRED · 0% AMBIGUOUS · INFERRED: 143 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- AI Intent & Knowledge Detection
- Lian Geospatial & Heatmap Services
- Lian Geospatial & Heatmap Services
- Lian Geospatial & Heatmap Services
- AI Orchestrator & Advisory Core
- Laravel Testing & Mock Foundations
- App Service Provider & Live Weather Feed
- Lian Geospatial & Heatmap Services
- Direct Messaging & Chat Conversations
- AI Intent & Knowledge Detection
- Direct Messaging & Chat Conversations
- Frontend NPM Packages & Tooling
- AI Chat & CRUD Controllers
- Notification System & Queries
- Direct Messaging & Chat Conversations
- AI Assistant Feature Tests
- Lian Geospatial & Heatmap Services
- Direct Messaging & Chat Conversations
- Frontend NPM Packages & Tooling
- Frontend NPM Packages & Tooling
- Lian Geospatial & Heatmap Services
- Advisory Models & Rule Framework
- User Entity & Farmer Profiles
- Lian Geospatial & Heatmap Services
- Lian Geospatial & Heatmap Services
- Agricultural Reporting & Seasonal Filters
- Frontend NPM Packages & Tooling
- Yield ML Models & Evaluation Engine
- Registration & Captcha Verification
- Manuscript Figure Generator Scripts
- PagasaAdvisoryService Group
- Feature Test Suite 31
- Weather Asset Component 32
- AI Intent & Knowledge Detection
- Weather Asset Component 34
- AI Orchestrator & Advisory Core
- Yield ML Models & Evaluation Engine
- Frontend NPM Packages & Tooling
- Feature Test Suite 38
- Weather Asset Component 39
- Feature Test Suite 40
- Weather Asset Component 41
- Yield ML Models & Evaluation Engine
- Yield ML Models & Evaluation Engine
- Yield ML Models & Evaluation Engine
- Database Migration 45
- RoleAssistantService Group
- Yield ML Models & Evaluation Engine
- Yield ML Models & Evaluation Engine
- Lian Geospatial & Heatmap Services
- Database Migration 50
- Lian Geospatial & Heatmap Services
- Yield ML Models & Evaluation Engine
- Feature Test Suite 53
- Yield ML Models & Evaluation Engine
- AnnouncementController.php Group
- post-root-package-install Group
- Email Verification & Heatmap Areas
- Authentication Component 58
- Yield ML Models & Evaluation Engine
- Database Migration 60
- CaptchaService.php Group
- pestphp/pest-plugin Group
- Lian Geospatial & Heatmap Services
- Farmer Profile Asset 64
- Lian Geospatial & Heatmap Services
- Weather Asset Component 66
- Authentication Component 67
- autoload Group
- require Group
- logging.php Group
- Yield ML Models & Evaluation Engine
- Lian Geospatial & Heatmap Services
- docs_figures_fig05_requirem... Group
- Blade View Component 74
- post-create-project-cmd Group
- Illuminate\Foundation\Inspi... Group
- Authentication Component 77
- Weather Asset Component 78
- Graphify Query and Path Wor... Group
- Yield ML Models & Evaluation Engine
- extra Group
- Lian Geospatial & Heatmap Services
- Farmer Profile Asset 103
- Blade View Component 104
- Blade View Component 105
- Weather Asset Component 106
- public_images_it_personnel_... Group
- Farmer Profile Asset 108
- Weather Asset Component 109
- Weather Asset Component 110
- Blade View Component 111
- Blade View Component 112
- Blade View Component 113
- Blade View Component 114
- Blade View Component 115
- Blade View Component 116
- Blade View Component 117
- Blade View Component 118
- Needs Verification Claims T... Group
- public_images_ai_assistant_... Group
- public_images_da_png_asset Group
- Farmer Profile Asset 132
- Farmer Profile Asset 133
- Farmer Profile Asset 134
- Farmer Profile Asset 135
- Farmer Profile Asset 136
- Weather Asset Component 137
- Weather Asset Component 138
- Weather Asset Component 139
- Web Crawler Robots Configur... Group
- Snapshots Directory Context Group
- Snapshots AI Sponsorship an... Group

## God Nodes (most connected - your core abstractions)
1. `User` - 264 edges
2. `PlantingAdvisory` - 98 edges
3. `TestCase` - 50 edges
4. `PredictionService` - 44 edges
5. `Controller` - 43 edges
6. `ClimateRecord` - 42 edges
7. `RiceProduction` - 42 edges
8. `HeatmapArea` - 38 edges
9. `FeedPost` - 36 edges
10. `ExternalWeatherData` - 35 edges

## Surprising Connections (you probably didn't know these)
- `Community Feed and Messaging Feature` --conceptually_related_to--> `Phase 3 Revised Manuscript Draft`  [INFERRED]
  README.md → docs/phase3_revised_manuscript.md
- `iClimate Chapters 1-3 Rendered PDF Document` --references--> `iClimate Chapters 1-3 Master Manuscript`  [EXTRACTED]
  docs/capstone/ICLIMATE_CHAPTERS_1_TO_3.pdf → docs/capstone/ICLIMATE_CHAPTERS_1_TO_3_MASTER.md
- `AgriculturalRiskScorerTest` --references--> `AgriculturalRiskScorer`  [EXTRACTED]
  tests/Unit/AgriculturalRiskScorerTest.php → app/Services/Risk/AgriculturalRiskScorer.php
- `Online Agricultural Advisory System Overview` --conceptually_related_to--> `Phase 3 Revised Manuscript Draft`  [INFERRED]
  docs/online-agricultural-advisories.md → docs/phase3_revised_manuscript.md
- `Yield Prediction Algorithm Comparison (MLR vs RF vs GBDT)` --semantically_similar_to--> `Rice Yield Prediction & Random Forest Model`  [INFERRED] [semantically similar]
  docs/capstone/ICLIMATE_CHAPTERS_1_TO_3_MASTER.md → docs/phase3_revised_manuscript.md

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Agricultural Advisory and Weather Processing Pipeline** — docs_online_agricultural_advisories_open_meteo_service, docs_online_agricultural_advisories_advisory_generation_service, docs_online_agricultural_advisories_advisory_rule_engine, docs_online_agricultural_advisories_approval_workflow [EXTRACTED 1.00]
- **Capstone Research and Manuscript Evolution Cycle** — docs_phase1_system_reverse_engineering_doc, docs_phase2_gap_analysis_overview, docs_phase3_revised_manuscript_draft, docs_capstone_iclimate_chapters_1_to_3_master_manuscript [EXTRACTED 1.00]
- **Decision Support and Yield Prediction Modeling** — docs_capstone_iclimate_chapters_1_to_3_master_theoretical_framework, docs_capstone_iclimate_chapters_1_to_3_master_algorithm_comparison, docs_phase3_revised_manuscript_rice_yield_prediction [INFERRED 0.85]
- **Theoretical and Conceptual Architecture Framework** — docs_figures_fig01_theoretical_framework, docs_figures_fig02_conceptual_framework, docs_figures_fig04_system_architecture, docs_figures_fig07_context_diagram [INFERRED 0.85]
- **Development and Lifecycle Methodology Group** — docs_figures_fig03_agile_methodology, docs_figures_fig05_requirement_analysis, docs_figures_fig10_development_flow [INFERRED 0.85]
- **Functional, Data Flow, and Schema Group** — docs_figures_fig06_use_case_diagram, fig08_dfd_level1, fig09_erd [INFERRED 0.85]

## Communities (199 total, 52 thin omitted)

### Community 0 - "AI Intent & Knowledge Detection"
Cohesion: 0.06
Nodes (9): KnowledgeBase, IntentDetectionService, KnowledgeBaseService, CarbonImmutable, PredictionService, AdvisoryRuleSeeder, KnowledgeBaseSeeder, XamppLocalhostSeeder (+1 more)

### Community 1 - "Lian Geospatial & Heatmap Services"
Cohesion: 0.06
Nodes (8): LianHeatmapController, AdvisoryRule, ExternalWeatherData, AdvisoryRuleEngine, ModelEvaluationService, LianFarmTypes, Illuminate\Support\Collection, OnlineAgriculturalAdvisorySystemTest

### Community 2 - "Lian Geospatial & Heatmap Services"
Cohesion: 0.06
Nodes (9): DecisionSupportService, HeatmapRiskService, AgriculturalRiskScorer, WeatherApiService, PHPUnit\Framework\TestCase, RiskParityTest, AgriculturalRiskScorerTest, ExampleTest (+1 more)

### Community 3 - "Lian Geospatial & Heatmap Services"
Cohesion: 0.07
Nodes (20): LianBoundaryController, LianWeatherController, AuthenticatedSessionController, ConfirmablePasswordController, EmailVerificationNotificationController, NewPasswordController, PasswordController, PasswordResetLinkController (+12 more)

### Community 4 - "AI Orchestrator & Advisory Core"
Cohesion: 0.10
Nodes (5): AIOrchestratorService, CarbonImmutable, GroqChatService, IntentClassifierService, Illuminate\Support\Arr

### Community 5 - "Laravel Testing & Mock Foundations"
Cohesion: 0.07
Nodes (16): Illuminate\Foundation\Testing\RefreshDatabase, Illuminate\Foundation\Testing\TestCase, Illuminate\Http\Client\ConnectionException, Illuminate\Http\Client\Request, Illuminate\Http\UploadedFile, Illuminate\Support\Facades\Config, Illuminate\Support\Facades\Hash, Illuminate\Support\Facades\Http (+8 more)

### Community 6 - "App Service Provider & Live Weather Feed"
Cohesion: 0.11
Nodes (8): AppServiceProvider, FarmerDashboardWeatherService, WeatherIconMapper, Carbon\CarbonInterface, Illuminate\Pagination\Paginator, Illuminate\Support\Carbon, Illuminate\Support\ServiceProvider, PDO

### Community 7 - "Lian Geospatial & Heatmap Services"
Cohesion: 0.07
Nodes (14): AnnouncementFactory, ClimateRecordFactory, FarmerProfileFactory, HeatmapAreaFactory, NotificationFactory, PlantingAdvisoryFactory, ReportFactory, RiceProductionFactory (+6 more)

### Community 8 - "Direct Messaging & Chat Conversations"
Cohesion: 0.09
Nodes (4): User, Illuminate\Foundation\Auth\User, AuthenticationTest, ProfileTest

### Community 9 - "AI Intent & Knowledge Detection"
Cohesion: 0.10
Nodes (8): AIChat, FeedComment, FeedMedia, FeedReaction, TyphoonSafetyResponse, Illuminate\Database\Eloquent\Factories\HasFactory, Illuminate\Database\Eloquent\Model, Illuminate\Database\Eloquent\Relations\BelongsTo

### Community 10 - "Direct Messaging & Chat Conversations"
Cohesion: 0.05
Nodes (39): Figure 4: System Architecture, Figure 7: Context Diagram, Application Layer, Data Layer, External Services Layer, ML/Python Layer, Presentation Layer, Service Layer (+31 more)

### Community 11 - "Frontend NPM Packages & Tooling"
Cohesion: 0.05
Nodes (36): alpinejs, autoprefixer, axios, bootstrap, chart.js, concurrently, laravel-vite-plugin, leaflet (+28 more)

### Community 12 - "AI Chat & CRUD Controllers"
Cohesion: 0.12
Nodes (5): AIChatController, CrudController, SystemLogController, UserManagementController, Illuminate\Http\Request

### Community 13 - "Notification System & Queries"
Cohesion: 0.15
Nodes (4): NotificationController, PlantingAdvisoryController, SystemAuditLogger, Illuminate\Http\RedirectResponse

### Community 14 - "Direct Messaging & Chat Conversations"
Cohesion: 0.11
Nodes (4): CalendarController, CommunityFeedController, FeedPost, Illuminate\Database\Eloquent\Relations\HasMany

### Community 15 - "AI Assistant Feature Tests"
Cohesion: 0.11
Nodes (3): route, AIChatTest, CommunicationPlatformTest

### Community 16 - "Lian Geospatial & Heatmap Services"
Cohesion: 0.18
Nodes (10): DashboardController, Announcement, ClimateRecord, FarmerProfile, HeatmapArea, Report, RiceProduction, DatabaseSeeder (+2 more)

### Community 17 - "Direct Messaging & Chat Conversations"
Cohesion: 0.15
Nodes (6): MobileAuthController, MessagingController, Conversation, ConversationMessage, Illuminate\Http\JsonResponse, Illuminate\Support\Facades\Storage

### Community 18 - "Frontend NPM Packages & Tooling"
Cohesion: 0.12
Nodes (5): SimpleGradientBoostingModel, SimpleLinearRegressionModel, SimpleRandomForestModel, SimpleRegressionTree, EvaluatesYieldModel

### Community 19 - "Frontend NPM Packages & Tooling"
Cohesion: 0.10
Nodes (16): bindFastNavigation(), bindLoadingForms(), bindRevealMotion(), bindStatDrawers(), closeDrawer(), lockSubmitButtons(), openDrawer(), applyRevealOptions() (+8 more)

### Community 20 - "Lian Geospatial & Heatmap Services"
Cohesion: 0.10
Nodes (6): Notification, Illuminate\Auth\Notifications\ResetPassword, Illuminate\Support\Facades\Notification, PasswordResetTest, FarmerDashboardRealtimeWeatherTest, ReportsHeatmapNotificationsTest

### Community 21 - "Advisory Models & Rule Framework"
Cohesion: 0.10
Nodes (3): Advisory, PlantingAdvisory, Illuminate\Database\Eloquent\Builder

### Community 22 - "User Entity & Farmer Profiles"
Cohesion: 0.09
Nodes (6): ClimateRecordController, FarmerProfileController, ProfileController, ProfileUpdateRequest, Illuminate\Support\Facades\Redirect, Illuminate\Validation\Rule

### Community 23 - "Lian Geospatial & Heatmap Services"
Cohesion: 0.12
Nodes (8): EmailVerificationPromptController, HeatmapAreaController, ModelEvaluationController, TyphoonSafetyController, AppLayout, GuestLayout, Illuminate\View\Component, Illuminate\View\View

### Community 24 - "Lian Geospatial & Heatmap Services"
Cohesion: 0.09
Nodes (23): Yield Prediction Algorithm Comparison (MLR vs RF vs GBDT), IPO Conceptual Framework, iClimate Chapters 1-3 Master Manuscript, Technical Stack Specification, DSS & Climate-Smart Theoretical Framework, iClimate Chapters 1-3 Rendered PDF Document, Advisory Generation Service, Advisory Rule Engine & Evaluation (+15 more)

### Community 26 - "Frontend NPM Packages & Tooling"
Cohesion: 0.16
Nodes (11): Response, RoleMiddleware, SecurityHeaders, TrackUserActivity, Closure, Illuminate\Database\QueryException, Illuminate\Foundation\Application, Illuminate\Foundation\Configuration\Exceptions (+3 more)

### Community 27 - "Yield ML Models & Evaluation Engine"
Cohesion: 0.19
Nodes (6): WeatherPredictionController, CarbonImmutable, PredictionDateValidator, WeatherPredictionService, Carbon\CarbonImmutable, InvalidArgumentException

### Community 28 - "Registration & Captcha Verification"
Cohesion: 0.17
Nodes (4): CaptchaImageController, RegisteredUserController, CaptchaService, Illuminate\Http\Response

### Community 29 - "Manuscript Figure Generator Scripts"
Cohesion: 0.37
Nodes (19): arrow(), Box, box_center(), edge_point(), figure_1(), figure_10(), figure_2(), figure_3() (+11 more)

### Community 31 - "Feature Test Suite 31"
Cohesion: 0.12
Nodes (18): Figure 3: Agile Methodology, Figure 10: Development Flow of iClimate, Agile Deployment Phase, Agile Design Phase, Agile Development Phase, Agile Iteration and Maintenance, Agile Planning Phase, Agile Requirements Phase (+10 more)

### Community 33 - "AI Intent & Knowledge Detection"
Cohesion: 0.18
Nodes (3): LiveForecastingController, RiceProductionController, LianBarangays

### Community 34 - "Weather Asset Component 34"
Cohesion: 0.17
Nodes (6): CheckGroqApi, ExpireAgriculturalAdvisories, FetchOnlineWeather, FetchPagasaAdvisories, RefreshLianBoundaries, Illuminate\Console\Command

### Community 35 - "AI Orchestrator & Advisory Core"
Cohesion: 0.23
Nodes (5): ClimateSnapshot, Illuminate\Support\Facades\Cache, Illuminate\Support\Facades\Log, RuntimeException, Throwable

### Community 36 - "Yield ML Models & Evaluation Engine"
Cohesion: 0.17
Nodes (6): LoginRequest, WeatherPredictionRequest, Illuminate\Auth\Events\Lockout, Illuminate\Contracts\Validation\ValidationRule, Illuminate\Foundation\Http\FormRequest, Illuminate\Support\Facades\RateLimiter

### Community 38 - "Feature Test Suite 38"
Cohesion: 0.12
Nodes (16): scripts, dev, post-autoload-dump, post-update-cmd, pre-package-uninstall, serve, test, Composer\\Config::disableProcessTimeout (+8 more)

### Community 39 - "Weather Asset Component 39"
Cohesion: 0.22
Nodes (3): GenerateAgriculturalAdvisories, AdvisoryGenerationService, OpenMeteoService

### Community 40 - "Feature Test Suite 40"
Cohesion: 0.14
Nodes (13): autoload-dev, psr-4, description, keywords, license, minimum-stability, name, prefer-stable (+5 more)

### Community 44 - "Yield ML Models & Evaluation Engine"
Cohesion: 0.33
Nodes (12): confidence_for(), feature(), health(), install_sklearn_compatibility_shims(), load_models(), predict(), recommendations(), rice_features() (+4 more)

### Community 45 - "Database Migration 45"
Cohesion: 0.25
Nodes (3): AutomationStatus, SystemLog, TyphoonSafetyService

### Community 49 - "Lian Geospatial & Heatmap Services"
Cohesion: 0.22
Nodes (3): up(), Illuminate\Database\Migrations\Migration, Illuminate\Support\Facades\DB

### Community 50 - "Database Migration 50"
Cohesion: 0.40
Nodes (10): document_body(), esc(), main(), paragraph(), run_text(), runs_from_inline(), strip_md(), table() (+2 more)

### Community 53 - "Feature Test Suite 53"
Cohesion: 0.22
Nodes (9): require-dev, fakerphp/faker, laravel/breeze, laravel/pail, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision (+1 more)

### Community 54 - "Yield ML Models & Evaluation Engine"
Cohesion: 0.39
Nodes (8): build_features(), install_sklearn_compatibility_shims(), irrigation_recommendation(), main(), notifications(), parse_payload(), planting_advisory(), Allow models saved with older sklearn internals to load on this machine.

### Community 56 - "post-root-package-install Group"
Cohesion: 0.25
Nodes (8): post-root-package-install, setup, composer install, npm install, npm run build, @php artisan key:generate, @php artisan migrate --force, @php -r \"file_exists('.env') || copy('.env.example', '.env');\

### Community 57 - "Email Verification & Heatmap Areas"
Cohesion: 0.25
Nodes (4): Illuminate\Auth\Events\Verified, Illuminate\Support\Facades\Event, Illuminate\Support\Facades\URL, EmailVerificationTest

### Community 62 - "pestphp/pest-plugin Group"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 66 - "Weather Asset Component 66"
Cohesion: 0.33
Nodes (6): Figure 6: Use Case Diagram, Actor: IT Expert / Admin, Actor: Farmer, Actor: MAO Personnel, External Services (Weather/AI Providers), iClimate Use Cases

### Community 68 - "autoload Group"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 69 - "require Group"
Cohesion: 0.40
Nodes (5): require, barryvdh/laravel-dompdf, laravel/framework, laravel/tinker, php

### Community 70 - "logging.php Group"
Cohesion: 0.40
Nodes (4): Monolog\Handler\NullHandler, Monolog\Handler\StreamHandler, Monolog\Handler\SyslogUdpHandler, Monolog\Processor\PsrLogMessageProcessor

### Community 71 - "Yield ML Models & Evaluation Engine"
Cohesion: 0.80
Nodes (5): Figure 1: Theoretical Framework, Climate and Farm Inputs, Decision-Support Outcome, iClimate Processes, Theoretical Basis (DSS, CSA, Predictive Modeling)

### Community 72 - "Lian Geospatial & Heatmap Services"
Cohesion: 0.70
Nodes (5): Figure 2: Conceptual Framework, Input Stage (PAGASA, Open-Meteo, MAO Records), Outcome (Climate-Informed Decision Support for Lian Batangas), Output Stage (Yield Estimates, Advisories, Heatmap), Process Stage (Validation, Modeling, Advisories, Risk)

### Community 73 - "docs_figures_fig05_requirem... Group"
Cohesion: 0.50
Nodes (5): Figure 5: Requirement Analysis, Functional Requirements, Non-Functional Requirements, Stakeholder Needs, System Modules

### Community 74 - "Blade View Component 74"
Cohesion: 0.50
Nodes (3): components.ai-chat-widget, layouts.navigation, layouts.sidebar

### Community 75 - "post-create-project-cmd Group"
Cohesion: 0.50
Nodes (4): post-create-project-cmd, @php artisan key:generate --ansi, @php artisan migrate --graceful --ansi, @php -r \"file_exists('database/database.sqlite') || touch('database/database.sqlite');\

### Community 76 - "Illuminate\Foundation\Inspi... Group"
Cohesion: 0.50
Nodes (3): Illuminate\Foundation\Inspiring, Illuminate\Support\Facades\Artisan, Illuminate\Support\Facades\Schedule

### Community 77 - "Authentication Component 77"
Cohesion: 0.50
Nodes (3): profile.partials.delete-user-form, profile.partials.update-password-form, profile.partials.update-profile-information-form

### Community 78 - "Weather Asset Component 78"
Cohesion: 0.50
Nodes (4): Heavy Rain Weather Icon, Light Rain Weather Icon, Rain Weather Icon, Thunderstorm Weather Icon

### Community 79 - "Graphify Query and Path Wor... Group"
Cohesion: 0.67
Nodes (3): Graphify Query and Path Workflow, Graphify Knowledge Graph Rule, Graphify Pipeline Workflow

### Community 81 - "extra Group"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 102 - "Lian Geospatial & Heatmap Services"
Cohesion: 0.67
Nodes (3): climate_records entity, heatmap_areas entity, rice_productions entity

## Knowledge Gaps
- **157 isolated node(s):** `$schema`, `name`, `type`, `description`, `laravel` (+152 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **52 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `Direct Messaging & Chat Conversations` to `AI Intent & Knowledge Detection`, `Lian Geospatial & Heatmap Services`, `Lian Geospatial & Heatmap Services`, `AI Orchestrator & Advisory Core`, `Laravel Testing & Mock Foundations`, `Lian Geospatial & Heatmap Services`, `AI Intent & Knowledge Detection`, `AI Chat & CRUD Controllers`, `Notification System & Queries`, `Direct Messaging & Chat Conversations`, `AI Assistant Feature Tests`, `Lian Geospatial & Heatmap Services`, `Direct Messaging & Chat Conversations`, `Lian Geospatial & Heatmap Services`, `User Entity & Farmer Profiles`, `Lian Geospatial & Heatmap Services`, `Frontend NPM Packages & Tooling`, `Yield ML Models & Evaluation Engine`, `Registration & Captcha Verification`, `PagasaAdvisoryService Group`, `AI Orchestrator & Advisory Core`, `Yield ML Models & Evaluation Engine`, `Weather Asset Component 41`, `Yield ML Models & Evaluation Engine`, `Database Migration 45`, `RoleAssistantService Group`, `Yield ML Models & Evaluation Engine`, `Lian Geospatial & Heatmap Services`, `AnnouncementController.php Group`, `Email Verification & Heatmap Areas`, `Yield ML Models & Evaluation Engine`, `Lian Geospatial & Heatmap Services`, `Authentication Component 67`?**
  _High betweenness centrality (0.241) - this node is a cross-community bridge._
- **Why does `PlantingAdvisory` connect `Advisory Models & Rule Framework` to `AI Intent & Knowledge Detection`, `Lian Geospatial & Heatmap Services`, `AI Orchestrator & Advisory Core`, `Laravel Testing & Mock Foundations`, `Lian Geospatial & Heatmap Services`, `Direct Messaging & Chat Conversations`, `AI Intent & Knowledge Detection`, `Notification System & Queries`, `Direct Messaging & Chat Conversations`, `Lian Geospatial & Heatmap Services`, `Lian Geospatial & Heatmap Services`, `User Entity & Farmer Profiles`, `Lian Geospatial & Heatmap Services`, `Agricultural Reporting & Seasonal Filters`, `PagasaAdvisoryService Group`, `AI Orchestrator & Advisory Core`, `Weather Asset Component 39`, `Database Migration 45`, `RoleAssistantService Group`, `Lian Geospatial & Heatmap Services`, `Database Migration 60`, `CaptchaService.php Group`, `Lian Geospatial & Heatmap Services`?**
  _High betweenness centrality (0.052) - this node is a cross-community bridge._
- **Why does `FarmerDashboardWeatherService` connect `App Service Provider & Live Weather Feed` to `Lian Geospatial & Heatmap Services`, `AI Orchestrator & Advisory Core`, `AI Orchestrator & Advisory Core`, `Laravel Testing & Mock Foundations`?**
  _High betweenness centrality (0.039) - this node is a cross-community bridge._
- **Are the 99 inferred relationships involving `route` (e.g. with `.store()` and `.store()`) actually correct?**
  _`route` has 99 INFERRED edges - model-reasoned connections that need verification._
- **What connects `$schema`, `name`, `type` to the rest of the system?**
  _157 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `AI Intent & Knowledge Detection` be split into smaller, more focused modules?**
  _Cohesion score 0.06010230179028133 - nodes in this community are weakly interconnected._
- **Should `Lian Geospatial & Heatmap Services` be split into smaller, more focused modules?**
  _Cohesion score 0.06448412698412699 - nodes in this community are weakly interconnected._