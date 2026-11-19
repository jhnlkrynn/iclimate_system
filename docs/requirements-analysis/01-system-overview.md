# 1. System Overview

## 1.1 Purpose

iClimate is a web-based decision-support system that assists the Municipal Agriculture Office (MAO) of Lian, Batangas, and the rice farmers of the municipality in monitoring weather conditions, assessing weather-related risk to rice farms at the barangay level, estimating rice yield, and distributing planting advisories and alerts. The system consolidates climate records, rice production records, farmer and farm records, external weather data, and machine learning outputs into role-specific dashboards.

## 1.2 Objectives

The implementation supports the following objectives. Each objective is stated as the code realises it.

| No. | Objective | Realised by |
|---|---|---|
| O1 | Provide current weather and a daily forecast for each of the nineteen barangays of Lian | `LianBarangayWeatherService`, `OpenMeteoService`, table `external_weather_data` |
| O2 | Classify weather risk to rice farms per barangay into four levels | `BarangayWeekRiskService`, `config/weather_risk.php`, table `heatmap_areas` |
| O3 | Estimate rice yield in tons per hectare for a farm, a barangay, and the municipality | `PredictionEngine`, `RiceYieldPredictionService`, Python `rice_yield_service.py`, tables `yield_predictions`, `yield_prediction_requests` |
| O4 | Forecast monthly rainfall, temperature, humidity, and wind | `MonthlyWeatherRandomForest`, table `monthly_weather_predictions` |
| O5 | Maintain climate and rice production records with validation and provenance | `ClimateRecordController`, `RiceProductionController`, `RecordImportController`, `RecordValidationController` |
| O6 | Generate, review, and publish planting advisories and alerts | `AdvisoryRuleEngine`, `PlantingAdvisoryController`, `AdvisoryAlertService` |
| O7 | Register farmers and their farms subject to MAO approval | `RegisteredUserController`, `FarmerApprovalController`, `FarmController` |
| O8 | Support communication between farmers and staff | `MessagingController`, `NotificationController`, `CommunityFeedController`, `AIChatController` |
| O9 | Produce reports in printable and exportable formats | `ReportController`, `ReportExtrasController`, `FarmerReportController` |
| O10 | Administer accounts, security, backups, and models | `UserManagementController`, `SystemSettingsController`, `BackupController`, `ModelRegistryController` |

## 1.3 Scope

### 1.3.1 Functional scope

The implemented system comprises the following modules. A fuller description of each is given in Documents 2 and 3.

1. Public landing page with sign-in, registration, and a contact form.
2. Authentication and account lifecycle (registration, approval, sign-in, lockout, password reset, first-password change, deactivation, deletion and restoration).
3. Farmer dashboard, My Farm (farms, planting schedules, harvest records, activity calendar), and farmer profile.
4. Weather forecast and predictions page; Weather Analysis; Weather Impact Analysis.
5. Yield Prediction (farmer request) and Yield Forecast (MAO, per barangay or municipality).
6. Heat Map of weather risk with weekly and two-week ranges and additional layers.
7. Planting advisories, PAGASA advisories, advisory feedback, and MAO alerts.
8. Climate records, rice production records, historical rice production, CSV import, and record validation.
9. Barangay profiles; farmer profiles; farmer approvals; farm association review.
10. Rice Varieties catalogue (NSIC).
11. Reports (nine types) and farmer My Reports.
12. Notifications, Messages, Announcements (Community Feed), Calendar, MAO Planner.
13. Typhoon Safety check-in.
14. Climora AI farming assistant.
15. MAO Office: MAO Personnel, barangay coverage, Activity Log.
16. IT administration: User Management, System Settings, Security, Roles and Permissions, Backups, Maintenance Records, ML Model Management, Model Registry, Model Evaluation, Model Feedback, System Health, System Logs.

### 1.3.2 Geographic and subject scope

The system is defined for rice only and for the nineteen barangays of the Municipality of Lian, Batangas. Barangay names, coordinates, and farm types are fixed in `App\Support\LianBarangays`, `LianGeography`, `LianLocation`, and `LianFarmTypes`.

### 1.3.3 Technical scope

| Layer | Technology (as declared in the repository) |
|---|---|
| Server framework | Laravel 12 on PHP 8.2 or later (`composer.json`) |
| Presentation | Blade templates, Tailwind CSS 3, Bootstrap 5.3, Alpine.js 3, Chart.js 4, Leaflet 1.9, built with Vite 7 (`package.json`) |
| Database | MySQL/MariaDB as the configured default (`.env.example`: `DB_CONNECTION=mysql`). The code retains support for PostgreSQL, which the project used earlier through Supabase (`DatabaseBackupService`, `MigrateSupabaseToMysql`) |
| Machine learning service | Python, FastAPI 0.141, scikit-learn 1.6.1, pandas 3.0, NumPy 2.4 (`python_scripts/requirements.txt`) |
| Background processing | Laravel scheduler (`routes/console.php`), database queue (`QUEUE_CONNECTION=database`), nine job classes |
| Real-time delivery | Laravel Reverb 1.12 (WebSocket), Laravel Echo, pusher-js; disabled unless `BROADCAST_CONNECTION` is set to Reverb |
| Document output | barryvdh/laravel-dompdf (PDF), PhpSpreadsheet (Excel), CSV, browser print |
| Session and cache | Database sessions; file cache by default |

### 1.3.4 External integrations

| External system | Purpose | Integration point |
|---|---|---|
| Open-Meteo | Current weather and daily forecast for each barangay | `OpenMeteoService`, `LianBarangayWeatherService`, `WeatherApiService` |
| PAGASA website | Regional forecast, weekly outlook, tropical cyclone bulletins | `PagasaAdvisoryService`, `PagasaBulletinParser` |
| GeoRisk Philippines portal | Barangay boundary polygons (Philippine Statistics Authority) | `RefreshLianBoundaries`, `Api\LianBoundaryController` |
| Map tile providers (OpenStreetMap, Esri World Imagery) | Base maps for Leaflet | Client side (`resources/js/heat-map.js`) |
| Groq API | Large language model for Climora when the question cannot be answered from system data | `GroqChatService`; disabled by default (`GROQ_ENABLED=false`) |
| SMTP mail server | Account e-mails and password reset links | `App\Mail\AccountStatusMail`, `AccountEmails`; default mailer writes to the log (`MAIL_MAILER=log`) |
| iClimate ML service (internal, FastAPI, port 8010) | Rice yield inference, monthly weather endpoint, analytics tasks | `PythonService`, `PythonAnalyticsClient`, `RuntimeModelClient` |

## 1.4 Architecture

The system follows a layered architecture:

1. **Presentation layer.** Blade views under `resources/views/` and JavaScript modules under `resources/js/`. Farmer pages and the MAO dashboard are localised in English and Filipino (`lang/en`, `lang/fil`).
2. **Application layer.** Sixty web controllers, six API controllers, and eleven authentication controllers. A global middleware chain enforces security headers, account status, activity tracking, read-only prediction mode, IT redirection from agricultural pages, maintenance mode, module permissions, and first-password change (`bootstrap/app.php`).
3. **Domain service layer.** Services grouped by concern: `AI`, `Accounts`, `Advisories`, `Analytics`, `Climate`, `Farmer`, `Farms`, `Feedback`, `MachineLearning`, `Mao`, `Prediction`, `Reports`, `RiceVarieties`, `Risk`, `Security`, `Weather`.
4. **Background layer.** Seventeen scheduled commands and nine queued jobs prepare weather, risk, forecasts, yield estimates, dashboards, backups, and alerts so that page requests read stored results only.
5. **Machine learning layer.** A FastAPI service exposing `/health`, `/model-info`, `/model/switch`, `/predict`, `/forecast/monthly-weather`, `/metrics`, and `/analytics/{task_type}`. When the service is unreachable, `PythonService` falls back to running the same model artefact locally.
6. **Data layer.** Sixty-eight tables created by the migrations (application and framework tables) in a relational database, plus file storage for model artefacts (`storage/models/`), backups, attachments, and the system settings file (`storage/app/system-settings.json`).

A design rule stated in the code and enforced by middleware (`ReadPreparedPredictionsOnly`, `PredictionReadMode`) is that **no page request trains or rebuilds a model**. A second rule, stated in `PredictionEngine`, is that **the system never invents a number**: a missing input is reported and the model is not run.

## 1.5 Limitations

The limitations below are those evidenced by the implementation. Document 9 lists further gaps in detail.

1. **Crop and place.** The system covers rice only and the nineteen barangays of Lian only. Use elsewhere requires changes to code-level constants and data.
2. **No field instrumentation.** The system uses no sensors or weather stations. Weather values are external forecasts; the historical climate reference is the PAGASA Ambulong station in Tanauan, which is outside Lian.
3. **Model accuracy is unverified.** The deployed rice-yield model receives inputs whose definitions differ from those of its training data, and the candidate models evaluated on the 2025 hold-out produced a negative coefficient of determination. Predictions are estimates and must not be presented as validated.
4. **Risk thresholds are working defaults.** The thresholds in `config/weather_risk.php` and the weights in `AgriculturalRiskScorer` are stated in the code as requiring confirmation by the MAO.
5. **No mobile application.** Two API endpoints for mobile sign-in and registration exist, but they issue no access token and no client application is included.
6. **No SMS or push notification.** Notifications are delivered only inside the system. E-mail is sent only when an SMTP server is configured.
7. **Dependence on external services and background processes.** When Open-Meteo, PAGASA, or the GeoRisk portal is unavailable, the system shows the last stored data with a delay notice. When the scheduler, queue worker, or ML service is stopped, stored data are no longer refreshed.
8. **E-mail addresses are not verified.** The `User` model does not implement `MustVerifyEmail`; the `verified` route middleware therefore has no effect.
9. **Language.** Farmer pages and the MAO dashboard and menu are bilingual. IT pages are in English only.
10. **Backup restoration.** Restoration from the web interface supports MySQL/MariaDB dumps only; backups are stored on the same server.
11. **Hidden modules.** Field Reports, Chatbot Knowledge management, and the candidate-model section of Model Evaluation are present in the code but disabled by default through configuration flags.
