# 8. Requirements Traceability Matrix

The matrix connects each functional requirement (Document 5) to the route, the source code, the database tables, and the automated test that exercises it. Paths are relative to the project root. Controllers are under `app/Http/Controllers/`, services under `app/Services/`, and feature tests under `tests/Feature/` unless a path is given.

Every controller, service, support class, script, and test named below was confirmed to exist in the repository. Tests were assigned to requirements by the subject of the test file. A test named in the last column is evidence that a test file for the function exists. It is not evidence that the test passes at the present commit; `docs/known-failing-tests.md` lists thirty-seven failing tests as of 17 October 2026.

## 8.1 Authentication and Account Management

| ID | Route (name or URI) | Controller / middleware | Service / support | Tables | Test |
|---|---|---|---|---|---|
| FR-AUTH-01 | `POST /register` | `Auth/RegisteredUserController@store` | `Rules/RegistrationEmailAvailable`, `Support/LianFarmTypes` | `users`, `farmer_profiles`, `farmer_organization_members` | `Auth/RegistrationTest` |
| FR-AUTH-02 | `captcha.image` | `Auth/CaptchaImageController` | `Security/CaptchaService` | cache | `SecurityHardeningTest` |
| FR-AUTH-03 | `POST /register` | `RegisteredUserController@store` | `Support/SystemSettings::farmerApproval`, `Mao/FarmerApprovalNotifier` | `users.status`, `notifications` | `Manuscript/Phase1AccountsTest`, `FarmerApprovalNotificationTest` |
| FR-AUTH-04 | `POST /login`, `dashboard` | `Auth/AuthenticatedSessionController@store` | `Http/Requests/Auth/LoginRequest`, `User::dashboardRoute` | `users`, `sessions` | `Auth/AuthenticationTest` |
| FR-AUTH-05 | `POST /login` | `LoginRequest::authenticate` | `CaptchaService`, `SystemSettings` (`lockout_attempts`, `lockout_minutes`) | cache | `SecurityHardeningTest`, `Manuscript/Phase8SettingsTest` |
| FR-AUTH-06 | `POST /login`, `account.deleted` | `LoginRequest`, `Auth/DeletedAccountController` | `User::signInRefusal`, `Accounts/AccountDeletion` | `users` | `AccountDeletionTest`, `RejectionAndFarmRestoreTest` |
| FR-AUTH-07 | all web routes | `Middleware/EnsureAccountIsActive` | `SystemAuditLogger` | `users`, `system_logs` | `SecurityHardeningTest` |
| FR-AUTH-08 | `password.request`, `password.email`, `password.reset`, `password.store` | `Auth/PasswordResetLinkController`, `Auth/NewPasswordController` | Laravel password broker | `password_reset_tokens` | `Auth/PasswordResetTest` |
| FR-AUTH-09 | `password.first-change*` | `FirstPasswordController`, `Middleware/EnsurePasswordChanged` | `config/iclimate.php` | `users.must_change_password` | `PanelRevisionsTest` |
| FR-AUTH-10 | `profile.*`, `password.update` | `ProfileController`, `Auth/PasswordController` | `Accounts/AccountDeletion` | `users` | `ProfileTest`, `Auth/PasswordUpdateTest` |
| FR-AUTH-11 | sign-in and sign-out | `LoginRequest::auditFailure`, `AuthenticatedSessionController` | `SystemAuditLogger` | `system_logs` | `SystemLogsPageTest` |
| FR-AUTH-12 | `verification.*` | `Auth/VerifyEmailController` | `User` does not implement `MustVerifyEmail` | `users.email_verified_at` | `Auth/EmailVerificationTest` |
| FR-AUTH-13 | `POST /api/mobile/login`, `POST /api/mobile/register` | `Api/MobileAuthController` | none | `users`, `farmer_profiles` | none found |

## 8.2 Farmer Dashboard and Farm Management

| ID | Route | Controller | Service / support | Tables | Test |
|---|---|---|---|---|---|
| FR-FARM-01 | `farmer.dashboard`, `farmer.dashboard.live`, `farmer.dashboard.weather` | `DashboardController@farmer`, `@farmerLive`, `@farmerWeather` | `Analytics/FarmerDashboardService`, `Farmer/FarmerHomeKpis`, `Weather/FarmerDashboardWeatherService` | `external_weather_data`, `heatmap_areas`, `yield_predictions`, `planting_advisories` | `FarmerHomeTest`, `FarmerHomeKpisTest`, `FarmerDashboardWeatherTest` |
| FR-FARM-02 | `farmer.dashboard.language` | `DashboardController@farmerLanguage` | `Middleware/SetFarmerLanguage`, `Support/FarmerLanguage` | `users.preferred_language` | `FarmerLanguageTest` |
| FR-FARM-03 | `farmer.farms.*` | `FarmController` | `Farms/FarmService`, `Support/ActiveFarm`, `Support/FarmSize` | `farms` | `FarmerPagesDesignTest`, `RejectionAndFarmRestoreTest` |
| FR-FARM-04 | `farmer.farms.*` | `FarmController::ownFarm` | | `farms.user_id` | `RoleAuthorizationTest` |
| FR-FARM-05 | `farmer.farms.map*` | `FarmController@map`, `@saveMap`, `@deleteMap` | `FarmBoundaryMeasurementService`, `Support/CoordinateNormalizer` | `farms`, `farm_boundaries` | `FarmBoundaryTest` |
| FR-FARM-06 | `farmer.boundary.*` | `FarmBoundaryController` behind `farmer.redirect` | | `farm_boundaries` | `FarmBoundaryTest` |
| FR-FARM-07 | `farmer.association-prompt.*` | `FarmAssociationPromptController` | `Farms/FarmAssociations` | `farms` (association columns) | `FarmAssociationPromptTest` |
| FR-FARM-08 | `farmer.planting-schedules.*`, `farmer.dashboard.planting-info` | `FarmerPlantingScheduleController` | `PlantingSchedulePredictionService` | `farmer_planting_schedules`, `rice_varieties` | `FarmerPlantingInfoTest` |
| FR-FARM-09 | `rice-varieties.estimate` | `RiceVarietyController@estimate` | `Support/VarietyHarvestEstimator` | `rice_varieties` | `tests/Unit/VarietyHarvestEstimatorTest` |
| FR-FARM-10 | `farmer.yield-survey.store` | `FarmerActivityController@yieldSurvey` | `Farmer/YieldSurveyService`, `Support/YieldUnit` | `farmer_planting_schedules`, `yield_prediction_feedback` | `FarmerNaicelFeaturesTest` |
| FR-FARM-11 | `farmer.activities.store` | `FarmerActivityController@store` | `Support/HarvestTiming`, `config/farm_calendar.php` | `farmer_planting_schedules` (`harvest_timing*`) | `PanelRevisionsTest` |
| FR-FARM-12 | `farmer.my-farm`, `calendar.index` | `FarmerMyFarmController`, `CalendarController` | `Analytics/FarmerCropPlanService` | `farmer_planting_schedules` | `FarmerPlantingHarvestCalendarTest`, `CalendarPageTest` |
| FR-FARM-13 | `farmer.dashboard.farm-size`, `farmer.dashboard.profile` | `FarmerDashboardSetupController` | `Support/FarmerSetupChecklist`, `Farmer/FarmerMissingInfoNotifier` | `farms`, `farmer_profiles`, `notifications` | `FarmerDashboardPopupsTest` |

## 8.3 Weather and Forecast

| ID | Route / command | Controller / command | Service | Tables | Test |
|---|---|---|---|---|---|
| FR-WX-01 | `iclimate:refresh-barangay-weather` | `Console/Commands/RefreshLianBarangayWeather` | `Weather/LianBarangayWeatherService`, `Weather/OpenMeteoService` | `external_weather_data` | `BarangayCurrentWeatherTest` |
| FR-WX-02 | same | same | `LianBarangayWeatherService` (freshness) | `external_weather_data.fetched_at` | `SavedDataStatesTest`, `WeatherFetchTimeTest` |
| FR-WX-03 | same | same | `Models/WeatherFetchLog` | `weather_fetch_logs` | `SystemHealthTest` |
| FR-WX-04 | `weather-predictions.index` | `WeatherPredictionController@index` | `Weather/DailyForecastService`, `Prediction/ShortTermOutlookService` | `external_weather_data` | `PredictionsPageTest` |
| FR-WX-05 | `iclimate:refresh-predictions`, `iclimate:warm-prediction-cache` | `RefreshIclimatePredictions`, `WarmPredictionCache`; job `GenerateMonthlyWeatherForecast` | `Prediction/PredictionOrchestrator`, `MachineLearning/MonthlyWeatherRandomForest` | `climate_records`, `monthly_weather_predictions` | `MachineLearning/MonthlyWeatherRandomForestTest`, `Prediction/PredictionOrchestratorTest` |
| FR-WX-06 | `analysis.weather` | `WeatherAnalysisController@weather` | `Climate/WeatherAnalysisService`, `Support/ClimateHistory` | `climate_records` | `Manuscript/Phase4AnalysisTest` |
| FR-WX-07 | `weather-feedback.store`, `farmer.forecast-survey.store` | `WeatherFeedbackController`, `FarmerForecastSurveyController` | `Weather/WeatherFeedbackService` | `weather_forecast_feedback` | `WeatherFeedbackTest` |
| FR-WX-08 | `login.weather`, `api.weather.current` | `Auth/AuthenticatedSessionController@weather` | `WeatherApiService` | `external_weather_data` | `LandingPageTest` |

## 8.4 Heat Map and Risk

| ID | Route / command | Controller / command | Service | Tables | Test |
|---|---|---|---|---|---|
| FR-RISK-01 | `heat-map.index`, `GET /api/heat-map` | `DashboardController@heatMap`, `Api/HeatMapController` | `Risk/BarangayWeekRiskService`, `config/weather_risk.php` | `external_weather_data` | `tests/Unit/BarangayWeekRiskRuleTest`, `HeatMapApiTest` |
| FR-RISK-02 | same | same | `BarangayWeekRiskService`, `Advisories/PagasaAdvisoryService` | `planting_advisories` | `PanelRevisionsTest` |
| FR-RISK-03 | same | same | `BarangayWeekRiskService`, `Risk/BarangayRiskMapService` | none written | `HeatMapDataFlowTest` |
| FR-RISK-04 | `heat-map.index`, `heat-map.detail` | `DashboardController@heatMap`, `@heatMapDetail` | `Risk/HeatMapPanel`; `resources/js/heat-map.js` | `heatmap_areas` | `HeatMapPageTest` |
| FR-RISK-05 | `GET /api/heat-map` | `Api/HeatMapController` | `Risk/HeatMapLayerService`, `Risk/BarangayMapLayers` | `barangay_profiles`, `yield_predictions` | `HeatMapYieldLayerTest`, `Manuscript/Phase3BarangaysTest` |
| FR-RISK-06 | `iclimate:refresh-barangay-risk`, `GET /api/lian/risk-map` | `RefreshBarangayAgriculturalRisk`, `Api/BarangayRiskMapController`; job `RefreshBarangayRiskMap` | `Risk/AgriculturalRiskScorer`, `DecisionSupportService` | `heatmap_areas`, `rice_productions`, `farmer_profiles` | `tests/Unit/AgriculturalRiskScorerTest`, `BarangayRiskMapTest`, `RiskParityTest` |
| FR-RISK-07 | `admin.settings.save` | `SystemSettingsController@saveGeneral` | `Support/SystemSettings` (`risk_*`) | settings file | `Manuscript/Phase8SettingsTest` |

## 8.5 Yield Prediction

| ID | Route / command | Controller / job | Service | Tables | Test |
|---|---|---|---|---|---|
| FR-YLD-01 | `iclimate:warm-farmer-yields` | `WarmFarmerYields`; job `PrepareFarmerYieldEstimate` | `Prediction/RiceYieldPredictionService`, `Prediction/PredictionEngine`, `PythonService`; `python_scripts/rice_yield_service.py` | `yield_predictions` | `FarmerYieldEstimateTest`, `PredictionParityTest` |
| FR-YLD-02 | same | same | `Prediction/BarangayYieldForecastService`, `Prediction/YieldPredictionStore` | `yield_predictions` (`scope = barangay`) | `HeatMapYieldLayerTest` |
| FR-YLD-03 | `analysis.yield`, `analysis.yield.store` | `YieldPredictionController@farmer`, `@farmerStore` | `PredictionEngine` | `yield_prediction_requests` | `Manuscript/Phase5YieldTest` |
| FR-YLD-04 | `analysis.yield.status` | `YieldPredictionController@status`; job `RunYieldPredictionRequest` | | `yield_prediction_requests.status` | `Manuscript/Phase5YieldTest` |
| FR-YLD-05 | internal | | `PredictionEngine` (`missing_inputs`), `rice_yield_service._feature` | | `PredictionEngineClimateRecordPlausibilityTest` |
| FR-YLD-06 | internal | `RunYieldPredictionRequest` | `Farmer/HistoricalYieldBenchmark`, `Prediction/SeasonYield` | `yield_prediction_requests` (`historical_average`, `impact_level`) | `Manuscript/Phase5YieldTest` |
| FR-YLD-07 | `analysis.forecast`, `analysis.forecast.store` | `YieldPredictionController@mao`, `@maoStore`; job `PrepareMunicipalYieldForecast` | `Prediction/MunicipalYieldForecastService` | `yield_prediction_requests` | `Manuscript/Phase5YieldTest` |
| FR-YLD-08 | `mao.dashboard`, `analysis.forecast` | `DashboardController@mao` | `Analytics/MaoYieldAnalytics`, `Support/YieldCorrections` | `municipal_yield_predictions`, `yield_prediction_feedback` | `PanelRevisionsTest` |
| FR-YLD-09 | `analysis.impact` | `WeatherAnalysisController@impact` | `Climate/WeatherAnalysisService::impact`, `Support/ProjectYieldDataset` | `climate_records` | `Manuscript/Phase4AnalysisTest` |
| FR-YLD-10 | `analysis.impact.store`, `analysis.impact.show` | `WeatherAnalysisController@store`, `@show` | | `weather_impact_analyses` | `Manuscript/Phase4AnalysisTest` |

## 8.6 Advisories and Alerts

| ID | Route / command | Controller / command | Service | Tables | Test |
|---|---|---|---|---|---|
| FR-ADV-01 | `management.advisories.regenerate`, `iclimate:automation-run`, `iclimate:generate-advisories` | `PlantingAdvisoryController@regenerate`, `RunIClimateAutomation`; job `RunPythonAnalytics` | `Analytics/AnalyticsDispatcher`, `Analytics/AnalyticsResultPersister`, `python_scripts/analytics_tasks.py::advisory_ai`; `Advisories/AdvisoryRuleEngine`, `Advisories/LocalFarmGuidanceService` | `analytics_runs`, `advisory_rules`, `planting_advisories` | `OnlineAgriculturalAdvisorySystemTest`, `PythonAnalyticsQueueTest` |
| FR-ADV-02 | `iclimate:fetch-pagasa-advisories`, `planting-advisories.refresh-pagasa` | `FetchPagasaAdvisories`, `PlantingAdvisoryController@refreshPagasa` | `Advisories/PagasaAdvisoryService`, `Advisories/PagasaBulletinParser` | `planting_advisories` | `PlantingAdvisoriesReliabilityTest`, `FarmerAdvisorySourcesTest` |
| FR-ADV-03 | `planting-advisories.*`, `management.advisories.{approve,reject,publish,archive}` | `PlantingAdvisoryController` | `Advisory/AdvisoryVerificationService` | `planting_advisories` | `AdvisoriesListTest`, `OnlineAgriculturalAdvisorySystemTest` |
| FR-ADV-04 | `planting-advisories.index`, `.show` | `PlantingAdvisoryController::isVisibleToFarmer` | `Support/FarmerAdvisoryView` | `planting_advisories` | `PlantingAdvisoryBarangayTest`, `BarangayAdvisoryExperienceTest` |
| FR-ADV-05 | `management.advisories.publish` | `PlantingAdvisoryController@publish` | `Advisories/AdvisoryPublishedNotifier` | `notifications` | `ReportsHeatmapNotificationsTest` |
| FR-ADV-06 | `planting-advisories.feedback`, `advisory-feedback.respond` | `PlantingAdvisoryController@feedback`, `@respondToFeedback` | | `advisory_feedback` | `AdvisoryFeedbackTest` |
| FR-ADV-07 | `mao.alerts.index`, `mao.alerts.store` | `AdvisoryAlertController` | `Advisories/AdvisoryAlertService::create` | `planting_advisories`, `notifications` | `Manuscript/Phase6AlertsTest` |
| FR-ADV-08 | `iclimate:publish-scheduled-alerts`, `mao.alerts.cancel` | `PublishScheduledAlerts`, `AdvisoryAlertController@cancel` | `AdvisoryAlertService::publishDue`, `::cancel` | `planting_advisories.scheduled_for` | `Manuscript/Phase6AlertsTest` |
| FR-ADV-09 | `mao.alerts.index` | `AdvisoryAlertController@index` | `AdvisoryAlertService::history` | `notifications` (`read_at`, `planting_advisory_id`) | `Manuscript/Phase6AlertsTest` |
| FR-ADV-10 | `iclimate:expire-advisories` | `ExpireAgriculturalAdvisories` | `Advisories/AdvisoryGenerationService::expireOutdated` | `planting_advisories.valid_until` | `OnlineAgriculturalAdvisorySystemTest` |
| FR-ADV-11 | `planting-advisories.show` | `PlantingAdvisoryController@show` | `Advisories/AdvisoryWeatherService` | `external_weather_data` | `AdvisoryWeatherTest` |
| FR-ADV-12 | `typhoon-safety.store` | `TyphoonSafetyController@store` | `TyphoonSafetyService` | `typhoon_safety_responses` | `TyphoonSafetyResponseValidationTest` |

## 8.7 Records Management

| ID | Route | Controller | Service / support | Tables | Test |
|---|---|---|---|---|---|
| FR-REC-01 | `climate-records.*` | `ClimateRecordController` (extends `CrudController`) | `Climate/ClimateRecordHistory` | `climate_records` | `CrudListFiltersTest`, `PrintListsTest` |
| FR-REC-02 | `rice-productions.*` | `RiceProductionController` (extends `CrudController`) | | `rice_productions` | `StaffDatasetRecordsTest` |
| FR-REC-03 | `rice-productions.store`, `.update` | `RiceProductionController` | `Support/YieldUnit`, `config/yield.php` | `rice_productions.yield_per_hectare` | `PanelRevisionsTest` |
| FR-REC-04 | `records.import*` | `RecordImportController` | `ClimateRecordController::importRules`, `RiceProductionController::importRules` | `climate_records`, `rice_productions` | `Manuscript/Phase2RecordsTest` |
| FR-REC-05 | `records.validate`, `.unvalidate`, `.validate-all` | `RecordValidationController` | | `verified_at`, `verified_by` | `Manuscript/Phase2RecordsTest` |
| FR-REC-06 | record routes | `CrudController` | `Support/MaoStamp`, `Support/RecordProvenance` | `created_by`, `updated_by`, `verified_by` | `YieldProvenanceDisplayTest` |
| FR-REC-07 | record routes | `CrudController::authorizeRecordCoverage` | `Support/MaoScope` | `mao_user_barangays` | `PanelRevisionsTest` |
| FR-REC-08 | `historical-rice-productions.*` | `HistoricalRiceProductionController` | | `historical_rice_productions` | `StaffDatasetRecordsTest` |
| FR-REC-09 | `mao.barangays.*` | `BarangayProfileController` | `Support/LianBarangays` | `barangay_profiles` | `Manuscript/Phase3BarangaysTest` |
| FR-REC-10 | dashboards | | `Support/SampleDataDetector`, `Support/SampleData`; `iclimate:data-audit`, `iclimate:purge-sample-data` | flagged rows in several tables | `DatabaseSeederTest` |

## 8.8 Farmer Administration

| ID | Route | Controller | Service / support | Tables | Test |
|---|---|---|---|---|---|
| FR-FADM-01 | `mao.farmer-approvals.*` | `FarmerApprovalController` | `Mao/FarmerApprovalNotifier`, `Accounts/AccountEmails` | `users` | `Manuscript/Phase1AccountsTest` |
| FR-FADM-02 | `farmer-profiles.*` | `FarmerProfileController` | `FarmerOrganizationSummaryService` | `farmer_profiles` | `FarmerProfileModifyTest` |
| FR-FADM-03 | `farmers.search` | `FarmerSearchController` | `Support/MaoScope` | `farmer_profiles`, `users` | `PanelRevisionsTest` |
| FR-FADM-04 | registration, `farmer-profiles.update` | `RegisteredUserController`, `FarmerProfileController` | `FarmerProfile::syncOrganizations` | `farmer_organization_members` | `PanelRevisionsTest` |
| FR-FADM-05 | `mao.farm-associations.*` | `FarmAssociationReviewController`, `FarmAssociationUndeclaredController` | `Farms/FarmAssociations` | `farms`, `farmer_organizations` | `FarmAssociationTest` |
| FR-FADM-06 | `farmer-profiles.farms.restore` | `FarmerProfileController@restoreFarm` | `Farms/FarmService` | `farms.archived_at` | `RejectionAndFarmRestoreTest` |

## 8.9 MAO Office

| ID | Route | Controller | Service / support | Tables | Test |
|---|---|---|---|---|---|
| FR-MAO-01 | `mao.dashboard`, `mao.dashboard.live` | `DashboardController@mao`, `@maoLive` | `Analytics/MaoHomeService`, `Analytics/MaoDashboardService`, `Analytics/PreparedDashboards` | several (read) | `MaoDashboardHomeTest`, `MaoDashboardFiguresTest` |
| FR-MAO-02 | `mao.view.switch` | `MaoPersonnelController@switchView` | `Support/MaoScope` | `mao_user_barangays` | `PanelRevisionsTest` |
| FR-MAO-03 | `mao.personnel.*` | `MaoPersonnelController` | `config/iclimate.php` (`mao_default_password`) | `users` | `PanelRevisionsTest` |
| FR-MAO-04 | `mao.personnel.store`, `.update` | `MaoPersonnelController` | `Models/MaoUserBarangay` | `mao_user_barangays` | `PanelRevisionsTest` |
| FR-MAO-05 | `mao.activity-log.index` | `MaoActivityLogController` | `Mao/MaoActivityLogger` | `mao_activity_logs` | `PanelRevisionsTest` |
| FR-MAO-06 | `mao.planner.*` | `MaoPlannerController` | | `mao_activities` | `MaoPlannerTest` |
| FR-MAO-07 | `calendar.index` | `CalendarController@index` | `Weather/MonthlyWeatherCalendarService` | `farmer_planting_schedules`, `feed_posts`, `planting_advisories` | `CalendarPageTest` |

## 8.10 Reports

| ID | Route | Controller | Service / support | Tables | Test |
|---|---|---|---|---|---|
| FR-RPT-01 | `reports.generate`, `reports.results` | `ReportController@generate`, `@results` | `Analytics/AnalyticsDispatcher`, job `RunPythonAnalytics`, `Reports/ManuscriptReportBuilder`, `Support/ReportTypeCatalog` | `analytics_runs`, `reports` | `Manuscript/Phase7ReportsTest`, `ClimateReportSeparationTest` |
| FR-RPT-02 | `reports.generate` | `ReportController` | `Models/Report` (`series_key`, `version`) | `reports` | `Manuscript/Phase7ReportsTest` |
| FR-RPT-03 | `reports.publish` | `ReportExtrasController@publish` | | `reports.status`, `published_at` | `Manuscript/Phase7ReportsTest` |
| FR-RPT-04 | `reports.export`, `reports.export.xlsx`, `reports.export.pdf`, `reports.print` | `ReportController@exportCsv`, `@print`; `ReportExtrasController@xlsx`, `@pdf` | `Support/SimpleXlsx`, PhpSpreadsheet, dompdf | `reports` | `Manuscript/Phase7ReportsTest` |
| FR-RPT-05 | `farmer.reports.index`, `farmer.reports.pdf` | `FarmerReportController` | dompdf | `yield_prediction_requests`, `planting_advisories` | `Manuscript/Phase7ReportsTest` |
| FR-RPT-06 | `reports.*` | `Middleware/RedirectItFromFieldPages` | | | `ItPagesDesignTest` |

## 8.11 Communication

| ID | Route / channel | Controller / event | Service | Tables | Test |
|---|---|---|---|---|---|
| FR-COM-01 | `notifications.*` | `NotificationController`; `Events/NotificationChanged` | `Support/NavigationCounts` | `notifications` | `NotificationsPopupTest`, `NotificationBroadcastingTest` |
| FR-COM-02 | `messages.*`; channel `conversation.{id}` | `MessagingController`; `Events/MessageCreated` | | `conversations`, `conversation_messages` | `CommunicationPlatformTest`, `MessageBroadcastingTest` |
| FR-COM-03 | `community-feed.*` | `CommunityFeedController` | | `feed_posts`, `feed_media`, `feed_comments`, `feed_reactions` | `CommunityFeedAuthorizationTest` |
| FR-COM-04 | `community-feed.index` | `Middleware/RedirectFarmers` | `Support/FarmerAdvisoryView` | `feed_posts` | `FarmerPagesSimplificationTest` |
| FR-COM-05 | `contact.send` | `ContactMessageController` | `Support/MaoContact` | `notifications` | `LandingPageTest` |
| FR-COM-06 | `routes/channels.php` | `Events/*Changed`, `Events/WeatherFetched` | Laravel Reverb; `resources/js/dashboard-realtime.js`, `farm-realtime.js`, `prediction-realtime.js`, `live-page.js` | none | `RealtimeCompletionTest`, `BroadcastChannelAuthorizationTest`, `ResilientBroadcastingTest` |
| FR-COM-07 | none | none | none | none | none |

## 8.12 Climora AI Assistant

| ID | Route | Controller | Service | Tables | Test |
|---|---|---|---|---|---|
| FR-AI-01 | `ai-chat.message` | `AIChatController@message` | `AI/AIOrchestratorService`, `AI/ClimateRiskReplies`, `PredictionService` | `a_i_chats`, read tables | `AIChatTest`, `AIHybridOrchestratorTest`, `ClimoraRiskContractTest` |
| FR-AI-02 | same | same | `AI/LexiconService`, `AI/KnowledgeAnswerService`, `AI/KnowledgeBaseService` | `ai_knowledge_topics`, `ai_terms`, `ai_synonyms`, `ai_faqs`, `knowledge_base`, `chatbot_responses` | `AiKnowledgeTest` |
| FR-AI-03 | same | same | `AI/ModerationService`, `AI/TextNormalizer` | `ai_blocked_terms`, `ai_blocked_patterns`, `ai_moderation_events` | `AiLanguageFilterTest` |
| FR-AI-04 | same | same | `AI/GroqChatService`, `config/ai.php` | none | `AIHybridOrchestratorTest` |
| FR-AI-05 | same | `Middleware/ReadPreparedPredictionsOnly` | `Prediction/PredictionReadMode` | none | `AiPredictionReadOnlyTest` |
| FR-AI-06 | same | `AIChatController@message` | `AI/AiTimings` | `a_i_chats` | `AiResponseSpeedTest` |
| FR-AI-07 | `ai-admin.*` | `AiAdminController`, `Middleware/ChatbotKnowledgeUi` | `Support/AiAdminResources` | `ai_*` | `ChatbotKnowledgeHiddenTest` |

## 8.13 IT Administration

| ID | Route / command | Controller / command | Service / support | Tables / files | Test |
|---|---|---|---|---|---|
| FR-ADM-01 | `users.*`, `users.restore` | `UserManagementController` | `Accounts/AccountDeletion`, `Auth/CachedEloquentUserProvider` | `users` | `UserListSortTest`, `AccountDeletionTest` |
| FR-ADM-02 | `users.reset-password` | `UserPasswordResetController` | `SystemAuditLogger` | `users` | `Manuscript/Phase1AccountsTest` |
| FR-ADM-03 | `admin.settings.*` | `SystemSettingsController@general`, `@saveGeneral` | `Support/SystemSettings` | `storage/app/system-settings.json` | `Manuscript/Phase8SettingsTest` |
| FR-ADM-04 | all web routes | `Middleware/SystemMaintenanceMode` | `SystemSettings::maintenanceOn` | settings file | `Manuscript/Phase8SettingsTest` |
| FR-ADM-05 | `admin.settings.security*` | `SystemSettingsController@security`, `@saveSecurity` | `SystemSettings`, `Providers/AppServiceProvider` | settings file | `Manuscript/Phase8SettingsTest` |
| FR-ADM-06 | `admin.permissions.*` | `SystemSettingsController@permissions`, `@savePermissions`; `Middleware/CheckModuleAccess` | `Support/ModuleAccess` | settings file | `Manuscript/Phase8SettingsTest` |
| FR-ADM-07 | `iclimate:backup-database`, `iclimate:verify-backup` | `BackupDatabase`, `VerifyBackup` | `DatabaseBackupService`, `BackupVerificationService` | backup files, `backup_verifications` | `DatabaseBackupTest`, `BackupVerificationTest` |
| FR-ADM-08 | `admin.backups.*` | `BackupController`; job `RunDatabaseMaintenance` | `DatabaseRestoreService`, `BackupRestoreTestService` | backup files, `maintenance_records` | `Manuscript/Phase8MaintenanceTest`, `BackupRestoreTestTest` |
| FR-ADM-09 | `admin.maintenance.*` | `MaintenanceRecordController` | | `maintenance_records` | `Manuscript/Phase8MaintenanceTest` |
| FR-ADM-10 | `system-health.index`, `iclimate:warm-dashboard-cache` | `SystemHealthController`, `WarmDashboardCache` | `SystemHealthService`, `Support/QueueWorkerHeartbeat` | `scheduled_task_runs`, `weather_fetch_logs`, `jobs`, `failed_jobs` | `SystemHealthTest` |
| FR-ADM-11 | `system-logs.*`, `admin.system-logs.export` | `SystemLogController` | `Support/SystemLogPresenter` | `system_logs` | `SystemLogsPageTest` |
| FR-ADM-12 | `admin.dashboard.retry-failed` | `DashboardController@adminRetryFailed` | `Analytics/ItDashboardService` | `failed_jobs` | `ItDashboardTest` |
| FR-ADM-13 | `rice-varieties.*` | `RiceVarietyController` | `Policies/RiceVarietyPolicy`, `RiceVarieties/RiceVarietyImporter` | `rice_varieties`, `rice_variety_imports` | `RiceVarietiesTest` |

## 8.14 Machine Learning Management

| ID | Route / endpoint | Controller / job | Service / script | Tables / files | Test |
|---|---|---|---|---|---|
| FR-ML-01 | `admin.models.compare` | `ModelManagementController@compare`; job `RunModelManagement` | `MachineLearning/ModelManagementService::compare`; `python_scripts/model_management.py`, `compare_models.py` | `maintenance_records` | `Manuscript/Phase8MaintenanceTest`, `MachineLearning/RiceYieldBaselineComparisonTest` |
| FR-ML-02 | `admin.models.retrain` | `ModelManagementController@retrain` | `ModelManagementService::retrain`; `train_rice_yield_model.py` | `ml_models`, `storage/models/` | `Manuscript/Phase8MaintenanceTest` |
| FR-ML-03 | `admin.models.dataset`, `admin.models.upload` | `ModelManagementController@uploadDataset`, `@uploadModel` | `SystemSettings` (`ml_dataset_path`) | `ml_models`, files | `Manuscript/Phase8MaintenanceTest` |
| FR-ML-04 | `model-registry.validate`, `.activate`, `.fail` | `ModelRegistryController` | `MachineLearning/ModelRegistryService` | `ml_models` (`status`, `is_active`, `active_slot`) | `MachineLearning/ModelRegistryTest` |
| FR-ML-05 | FastAPI `POST /model/switch`, `GET /model-info` | `ModelRegistryController@activate` | `MachineLearning/RuntimeModelClient`; `python_scripts/main.py` | none | `python_scripts/tests/test_model_switch.py` |
| FR-ML-06 | `model-evaluation.index` | `ModelEvaluationController` | `MachineLearning/ModelEvaluationService`, `Support/EvaluationEvidence`; `python_scripts/evaluation_service.py` | `ml_models`, `rice_productions` | `EvaluationEvidenceTest` |
| FR-ML-07 | `admin.feedback.*`, `farmer.survey.*` | `ModelFeedbackController`, `FarmerSurveyController` | `Feedback/ModelFeedbackService` | `farmer_survey_responses`, `model_feedback_decisions` | `FarmerSurveyFeedbackTest`, `SurveyResultsTest` |
| FR-ML-08 | none | none | Original training script and data absent | `storage/models/rice_yield_model_final.pkl` | none |
| FR-ML-09 | FastAPI `POST /predict` | | `PythonService`, `Support/PythonRuntime`; `python_scripts/predict.py` | none | `PythonRuntimeTest`, `python_scripts/tests/test_smoke.py` |

## 8.15 Non-Functional Requirements to Evidence

| NFR | Principal source | Test |
|---|---|---|
| NFR-SEC-03 | `app/Http/Requests/Auth/LoginRequest.php` | `SecurityHardeningTest` |
| NFR-SEC-04 | `app/Http/Middleware/RoleMiddleware.php` | `RoleAuthorizationTest` |
| NFR-SEC-05 | `app/Support/ModuleAccess.php` | `Manuscript/Phase8SettingsTest` |
| NFR-SEC-07 | `routes/channels.php`, `app/Support/MaoScope.php` | `BroadcastChannelAuthorizationTest` |
| NFR-SEC-10 | `app/Http/Middleware/SecurityHeaders.php` | `SecurityHardeningTest` |
| NFR-PERF-01 | `app/Http/Middleware/ReadPreparedPredictionsOnly.php` | `AiPredictionReadOnlyTest` |
| NFR-PERF-02 | `app/Services/Analytics/PreparedDashboards.php`, `app/Support/CacheScope.php` | `PreparedDashboardsTest`, `CacheDependencyTest` |
| NFR-REL-02 | `bootstrap/app.php` | `DataServiceUnavailableTest` |
| NFR-REL-04 | `app/Services/DatabaseBackupService.php`, `routes/console.php` | `DatabaseBackupTest`, `BackupVerificationTest` |
| NFR-REL-10 | `app/Events/`, `resources/js/live-page.js` | `ResilientBroadcastingTest` |
| NFR-REL-11 | `app/Models/Concerns/KeepsDatabaseClockColumns.php` | `DatabaseClockColumnsTest` |
| NFR-USE-01 | `lang/en`, `lang/fil`, `app/Http/Middleware/SetFarmerLanguage.php` | `FarmerLanguageTest`, `FarmerAdvisoryLocalizationTest` |
| NFR-USE-03 | `resources/js/mobile-navigation.js` | `ResponsiveLayoutTest`, `tests/Browser/mobile-navigation.spec.mjs` |
