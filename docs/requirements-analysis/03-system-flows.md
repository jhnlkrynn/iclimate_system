# 3. Complete System Flows

Each module is described in the sequence **Actor → Input → Process → Database → Output**. Table names are those created by the migrations. Rate limits are quoted from the route definitions as *requests per minutes*.

---

## 3.1 Account and Access Flows

### 3.1.1 Farmer registration

| Step | Description |
|---|---|
| Actor | Visitor |
| Input | Name, e-mail, password with confirmation, barangay, primary farmer association (optional), other associations (optional, at most twenty), CAPTCHA answer |
| Process | 1. `RegisteredUserController::store` validates the fields; the barangay must be one with a registered association, and the association must belong to that barangay. 2. `CaptchaService::validate` checks the answer. 3. Within one database transaction, a user is created with role Farmer and status `Pending` (or `Active` when approval is switched off), and a farmer profile is created with the farm type of the chosen association. 4. Association memberships are synchronised. 5. `FarmerApprovalNotifier` notifies MAO Personnel. Limit: 5 per 10 minutes. |
| Database | Insert `users`, `farmer_profiles`, `farmer_organization_members`; insert `notifications` for MAO Personnel |
| Output | Redirect to the landing page with the message that the MAO will review the account |

### 3.1.2 Farmer approval

| Step | Description |
|---|---|
| Actor | MAO Personnel |
| Input | Selection of a pending account; decision (approve, or reject with a reason) |
| Process | `FarmerApprovalController::approve` or `reject` sets the status, the approver, and the time. A model event on `User` sends the approval e-mail when a mail server is configured. The action is recorded by `MaoActivityLogger` and `SystemAuditLogger`. |
| Database | Update `users` (`status`, `approved_at`, `approved_by`, `rejection_reason`); insert `notifications`, `mao_activity_logs`, `system_logs` |
| Output | Updated approvals list; notification and e-mail to the farmer |

### 3.1.3 Sign-in

| Step | Description |
|---|---|
| Actor | Farmer, MAO Personnel, IT Expert |
| Input | E-mail, password; CAPTCHA answer after repeated failures |
| Process | 1. `LoginRequest` normalises the e-mail to lower case. 2. The rate limiter refuses the attempt when the lockout threshold is reached (default five attempts, one minute; configurable in Security settings). 3. When required, the CAPTCHA is validated. 4. `Auth::attempt` verifies the hashed password; "remember me" is never used. 5. A deleted account with the correct password is shown the reason for deletion and is not signed in. 6. A non-active account is signed out with a message that states the reason. 7. On success the limiter is cleared, `last_login_at` is set, and the user is redirected to the dashboard of the role. Every failure is written to the audit log. |
| Database | Read `users`; write `sessions`; insert `system_logs`; update `users.last_login_at` |
| Output | Role dashboard, or an error message on the sign-in card |

### 3.1.4 First-password change

| Step | Description |
|---|---|
| Actor | Any user whose account has `must_change_password` |
| Input | New password with confirmation, or "Skip for now" when permitted by `MAO_ALLOW_SKIP_PASSWORD_CHANGE` |
| Process | `EnsurePasswordChanged` redirects every GET request to the change page until a password is saved or the step is skipped for the session. `FirstPasswordController::update` applies the password policy. |
| Database | Update `users` (`password`, `must_change_password`) |
| Output | Role dashboard |

### 3.1.5 Password reset

| Step | Description |
|---|---|
| Actor | Any user; IT Personnel; Municipal Agriculturist |
| Input | (a) E-mail on the forgot-password page; (b) IT selects a user; (c) the Municipal Agriculturist selects an MAO account |
| Process | (a) `PasswordResetLinkController` creates a token and sends a link; with the default log mailer the page instructs the user to ask the MAO or IT. (b) `UserPasswordResetController` sets a one-time temporary password. (c) `MaoPersonnelController::resetPassword` restores the default password and sets `must_change_password`. |
| Database | Insert `password_reset_tokens`; update `users`; insert `system_logs` |
| Output | Reset e-mail, or the temporary password shown once to the authorized IT Expert or MAO Head |

### 3.1.6 Account deletion and restoration

| Step | Description |
|---|---|
| Actor | IT Personnel; any user for the user's own account |
| Input | Account selection; reason for deletion |
| Process | `AccountDeletion` sets status `Deleted` with the reason and time; the account disappears from all queries. `UserManagementController::restore` reverses the deletion within the restoration period. |
| Database | Update `users` (`status`, deletion columns); insert `system_logs` |
| Output | Updated user list; e-mail to the account holder when configured |

---

## 3.2 Farmer Flows

### 3.2.1 Farmer dashboard

| Step | Description |
|---|---|
| Actor | Farmer |
| Input | Page request; language selection; active farm selection |
| Process | `DashboardController::farmer` calls `FarmerDashboardService`, which assembles stored data: weather for the farmer's barangay (`FarmerDashboardWeatherService`), weekly risk (`BarangayWeekRiskService`), the prepared yield estimate, the latest published advisory, indicators (`FarmerHomeKpis`), and the setup checklist. When the yield estimate is missing or due, `PrepareFarmerYieldEstimate` runs after the response has been sent. The endpoint `/farmer/dashboard/live` returns refreshed figures. |
| Database | Read `users`, `farms`, `farmer_profiles`, `farmer_planting_schedules`, `external_weather_data`, `heatmap_areas`, `planting_advisories`, `yield_predictions`, `notifications` |
| Output | Dashboard in English or Filipino |

### 3.2.2 Farm management (My Farm)

| Step | Description |
|---|---|
| Actor | Farmer |
| Input | Farm name, barangay, location description or map point, farm size (value, unit, or estimate range), farm type, association answer |
| Process | `FarmController` validates the input and restricts every operation to the farmer's own farms. Removal archives the farm (`archived_at`); it may be restored. One farm is marked primary. A change dispatches `FarmChanged` and queues a new yield estimate. |
| Database | Insert or update `farms`, `farm_boundaries`; read `farmer_organizations`, `barangay_profiles` |
| Output | My Farm page with the farm list, schedule, harvest records, and calendar |

### 3.2.3 Planting schedule and harvest record

| Step | Description |
|---|---|
| Actor | Farmer |
| Input | Planting date, rice variety, crop establishment method, seedling age, farm area, notes; later, harvest date and actual yield (t/ha, or total in sacks, kilograms, or tons) with a reason when early or late |
| Process | `FarmerPlantingScheduleController` derives the season and the expected harvest date from the variety's maturity (`VarietyHarvestEstimator`). `FarmerActivityController::yieldSurvey` converts the harvest to tons per hectare (`YieldUnit`), classifies the timing with a tolerance of three days (`HarvestTiming`), and refuses an early or late harvest without a reason. The predicted value held at that time is stored beside the actual value. |
| Database | Insert or update `farmer_planting_schedules`; insert `yield_prediction_feedback` |
| Output | Updated calendar and harvest records; data for predicted-versus-actual comparison |

### 3.2.4 Yield Prediction request

| Step | Description |
|---|---|
| Actor | Farmer |
| Input | Season, irrigation type, barangay, farm area |
| Process | 1. `YieldPredictionController::farmerStore` stores the request with status pending (10 per minute). 2. `RunYieldPredictionRequest` runs after the response. 3. `PredictionEngine::predict` assembles the model input from the climate history and the prepared monthly forecast; a missing input stops the run and is reported. 4. `PythonService` posts the features to the FastAPI `/predict` endpoint, or runs the model locally when the service is unreachable. 5. The result is compared with the historical average and classified as Favorable, Normal, or At Risk. 6. The page polls `/analysis/yield/{id}/status`. |
| Database | Insert and update `yield_prediction_requests`; read `climate_records`, `monthly_weather_predictions`, `rice_productions` |
| Output | Yield in t/ha, total tons, historical average, impact level, prediction history |

### 3.2.5 Feedback and surveys

| Step | Description |
|---|---|
| Actor | Farmer |
| Input | (a) Advisory: helpful or not, comment. (b) Forecast: accuracy rating and observed condition. (c) Farmer survey: usefulness, accuracy, helpfulness on a numeric scale, comment |
| Process | `PlantingAdvisoryController::feedback`, `WeatherFeedbackController::store` and `FarmerForecastSurveyController::store`, and `FarmerSurveyController::store` validate and save the response together with the model version in use. |
| Database | Insert `advisory_feedback`, `weather_forecast_feedback`, `farmer_survey_responses` |
| Output | Confirmation; data for MAO responses and IT Model Feedback |

### 3.2.6 Farmer My Reports

| Step | Description |
|---|---|
| Actor | Farmer |
| Input | Page request; PDF request (10 per minute) |
| Process | `FarmerReportController` summarises the farmer's own predictions and advisories and renders a PDF through dompdf. |
| Database | Read `yield_prediction_requests`, `yield_predictions`, `planting_advisories`, `farms` |
| Output | On-screen summary; PDF file |

---

## 3.3 Weather, Risk, and Prediction Flows

### 3.3.1 Weather acquisition (automated)

| Step | Description |
|---|---|
| Actor | Scheduler |
| Input | Coordinates of the nineteen barangays |
| Process | `iclimate:refresh-barangay-weather` (every ten minutes) sends one multi-coordinate request to Open-Meteo; on failure it retries one request per barangay. A successful reading replaces the last valid record; a failed refresh never overwrites it. Freshness is classified as fresh, recent, stale, or unavailable from `fetched_at`. Each fetch is logged. `WeatherFetched` and `WeatherChanged` are broadcast. |
| Database | Upsert `external_weather_data`; insert `weather_fetch_logs`, `scheduled_task_runs` |
| Output | Stored current weather and daily forecast for all modules |

### 3.3.2 Weekly risk rating and Heat Map

| Step | Description |
|---|---|
| Actor | Scheduler; then Farmer or MAO Personnel |
| Input | Stored daily forecast; farm type of the barangay; active typhoon advisory; range (This Week or 2 Weeks); layer selection |
| Process | `BarangayWeekRiskService` evaluates four hazards over the period and takes the worst: heavy rain (25, 50, 100 mm per day, or 200 mm total), strong wind (35, 62, 89 km/h), heat (35 °C; 38 °C on three or more days), and absence of rain in rainfed barangays. An active typhoon advisory rates all barangays Severe. A barangay without stored forecast days is reported as "No data". `BarangayRiskMapService` also computes a composite score through `AgriculturalRiskScorer`. `HeatMapLayerService` provides the flood-prone, drought-prone, and predicted-yield layers. `RiskChanged` is broadcast. |
| Database | Read `external_weather_data`, `planting_advisories`, `barangay_profiles`, `yield_predictions`, `rice_productions`, `farmer_profiles`; upsert `heatmap_areas` |
| Output | Map coloured by risk level, with the main factor and a crop-impact note per barangay |

### 3.3.3 Monthly weather forecast (automated)

| Step | Description |
|---|---|
| Actor | Scheduler |
| Input | Monthly climate history |
| Process | `iclimate:refresh-predictions` (daily, 02:00) queues `GenerateMonthlyWeatherForecast` for each missing or stale month of a twelve-month horizon. `MonthlyWeatherRandomForest` trains with a strict cut-off and predicts rainfall, maximum and minimum temperature, humidity, wind speed, and wind direction. `iclimate:warm-prediction-cache` (02:15) completes missing months and marks the cache ready. |
| Database | Read `climate_records`; upsert `monthly_weather_predictions` |
| Output | Stored monthly forecasts read by the Predictions page, the yield engine, and Climora |

### 3.3.4 Farm and barangay yield estimates (automated)

| Step | Description |
|---|---|
| Actor | Scheduler |
| Input | Each active farm (barangay, area, season); climate history; monthly forecast |
| Process | `iclimate:warm-farmer-yields` (every three hours) calls `RiceYieldPredictionService::predictForUser` for each farm. The barangay value is the area-weighted mean of its farm values. Values are stored for three periods: season, week, and two weeks. `PredictionChanged` is broadcast. |
| Database | Upsert `yield_predictions`; cache entry per farm |
| Output | Expected yield on the farmer dashboard and the Heat Map yield layer |

### 3.3.5 Predictions page and Weather Analysis

| Step | Description |
|---|---|
| Actor | Farmer, MAO Personnel |
| Input | Page request; filter by year, season, or month |
| Process | `WeatherPredictionController::index` reads stored daily forecasts and yield estimates; no form runs a model. `WeatherAnalysisService` computes monthly values, wet and dry season comparison, yearly totals, and risk indicators (heavy-rain month ≥ 300 mm, dry month ≤ 50 mm, hot month ≥ 29 °C, configurable). |
| Database | Read `external_weather_data`, `monthly_weather_predictions`, `yield_predictions`, `climate_records` |
| Output | Forecast tables and charts |

### 3.3.6 Weather Impact Analysis

| Step | Description |
|---|---|
| Actor | MAO Personnel |
| Input | Filters; title and notes when saving |
| Process | `WeatherAnalysisService::impact` pairs each monthly climate value with the monthly rice yield record (`ProjectYieldDataset`), computes the correlation of each climate variable with yield, ranks the variables, and assigns an impact level (Low, Moderate, High). `WeatherAnalysisController::store` saves the analysis. |
| Database | Read `climate_records`, yield dataset; insert `weather_impact_analyses` |
| Output | Ranked correlation table; saved analysis history |

### 3.3.7 Yield Forecast (MAO)

| Step | Description |
|---|---|
| Actor | MAO Personnel |
| Input | Scope (barangay or municipality), season, irrigation type |
| Process | `YieldPredictionController::maoStore` stores the request; `RunYieldPredictionRequest` or `PrepareMunicipalYieldForecast` runs the model in the background. Results are shown beside actual harvests from farmers and production records. |
| Database | Insert and update `yield_prediction_requests`; read `municipal_yield_predictions`, `yield_prediction_feedback`, `rice_productions` |
| Output | Forecast value, run history, predicted-versus-actual chart |

---

## 3.4 Advisory Flows

### 3.4.1 Automatic advisory generation

| Step | Description |
|---|---|
| Actor | Scheduler; MAO Personnel ("Regenerate") |
| Input | Stored forecast; active advisory rules |
| Process | Two generation paths exist. **(a) Rule engine.** `LocalFarmGuidanceService` calls `AdvisoryGenerationService` and `AdvisoryRuleEngine`, which compute forecast metrics and evaluate the JSON conditions of each active rule in `advisory_rules`. This path runs with the PAGASA fetch (every fifteen minutes and on "Refresh"). **(b) Python analytics task.** `iclimate:automation-run` (every thirty minutes) and the "Regenerate" action queue an `advisory_ai` analytics run. The Python task flags forecast rows with rainfall of 70 or more, wind of 25 or more, or temperature of 34 or more, and `AnalyticsResultPersister` stores each result as an advisory pending review. This path requires a running queue worker; the command skips the round when none is alive. Both paths use a unique generation key to prevent duplicates. `iclimate:expire-advisories` closes advisories past `valid_until`. |
| Database | Read `advisory_rules`, `external_weather_data`; insert `analytics_runs`; insert or update `planting_advisories` |
| Output | Advisories awaiting MAO review |

### 3.4.2 PAGASA advisories

| Step | Description |
|---|---|
| Actor | Scheduler (every fifteen minutes); any signed-in user ("Refresh", 6 per minute) |
| Input | PAGASA web pages |
| Process | `PagasaAdvisoryService::fetchAndStore` downloads the bulletins; `PagasaBulletinParser` extracts only bulletin content that concerns the locality. Stored PAGASA advisories expire after six hours. |
| Database | Upsert `planting_advisories` (source PAGASA) |
| Output | Official advisories on the Advisories page; typhoon status for the risk rating and the Typhoon Safety check-in |

### 3.4.3 Advisory review and publication

| Step | Description |
|---|---|
| Actor | MAO Personnel |
| Input | Advisory text, type, severity, target barangay, validity period; decision |
| Process | `PlantingAdvisoryController` provides create, edit, approve, reject (with reason), publish, and archive. On publication `AdvisoryPublishedNotifier` creates one notification per affected farmer, subject to the setting `notify_farmers_on_publish`. Advisories published by the system itself do not notify. |
| Database | Update `planting_advisories` (`status`, `approved_by`, `approved_at`, `published_at`, `rejection_reason`); insert `notifications`, `mao_activity_logs` |
| Output | Published advisory visible to farmers of the target barangay and to all farmers for municipality-wide advisories |

### 3.4.4 Send Alert

| Step | Description |
|---|---|
| Actor | MAO Personnel |
| Input | Alert type (typhoon, flood, drought, heat, general), severity, message, recommended action, recipients (all or selected barangays), send now or scheduled time |
| Process | `AdvisoryAlertService::create` creates one published advisory per chosen barangay, or one for all of Lian. A scheduled alert holds status "scheduled" until `iclimate:publish-scheduled-alerts` (every minute) publishes it. An alert may be cancelled before publication. Delivery and read counts are computed from notifications. |
| Database | Insert `planting_advisories` (`scheduled_for`); insert `notifications` (`planting_advisory_id`, `read_at`) |
| Output | Alert on farmers' Advisories page and notification bell; sent-alert history with counts |

### 3.4.5 Typhoon Safety check-in

| Step | Description |
|---|---|
| Actor | Farmer (also available to other roles) |
| Input | Safety status and optional note for the active event |
| Process | `TyphoonSafetyController::store` validates and stores one response per user and event (10 per minute). |
| Database | Upsert `typhoon_safety_responses` |
| Output | Confirmation; status summary for staff |

---

## 3.5 MAO Data Management Flows

### 3.5.1 Climate and production records

| Step | Description |
|---|---|
| Actor | MAO Personnel |
| Input | Climate record: date, rainfall, temperature, humidity, wind speed, season, source. Production record: barangay, season, year, irrigation type, area planted, area harvested, total harvest (tons, kilograms, or sacks) or yield |
| Process | `ClimateRecordController` and `RiceProductionController` extend `CrudController` (list, filter, create, edit, delete, print). A blank yield is computed from total harvest and area. Field personnel may change only records of their assigned barangays (`authorizeRecordCoverage`). Author columns record who created and edited each record. |
| Database | Insert, update, delete `climate_records`, `rice_productions`, `historical_rice_productions`; insert `mao_activity_logs` |
| Output | Record lists with provenance ("Created by", "Validated by") |

### 3.5.2 CSV import

| Step | Description |
|---|---|
| Actor | MAO Personnel |
| Input | CSV file following the downloadable template |
| Process | `RecordImportController::store` checks each row with the same rules as the form, imports valid rows, and reports skipped rows with the reason (10 per minute). |
| Database | Insert `climate_records` or `rice_productions` |
| Output | Import summary |

### 3.5.3 Record validation

| Step | Description |
|---|---|
| Actor | MAO Personnel |
| Input | One record, or "validate all" |
| Process | `RecordValidationController` sets or clears the verification stamp. |
| Database | Update `verified_at`, `verified_by` on `climate_records`, `rice_productions` |
| Output | Verified or Unverified marker and filter |

### 3.5.4 Barangay profiles and farmer profiles

| Step | Description |
|---|---|
| Actor | MAO Personnel |
| Input | Barangay: coordinates, farm area, risk classification, flood-prone, drought-prone, notes. Farmer: name, contact, address, barangay, farm area, farm type, RSBSA number, associations |
| Process | `BarangayProfileController::update`; `FarmerProfileController` (create, edit, view, restore an archived farm); `FarmerSearchController` returns at most twelve matches within the user's coverage. |
| Database | Update `barangay_profiles`; insert or update `farmer_profiles`, `farmer_organization_members`, `farms` |
| Output | Barangay list; farmer list and profile |

### 3.5.5 Farm association review

| Step | Description |
|---|---|
| Actor | MAO Personnel |
| Input | A farm whose association "is not listed"; decision to link to an existing association, create a new one, or reject; bulk answers for undeclared farms |
| Process | `FarmAssociationReviewController` and `FarmAssociationUndeclaredController` update the farm's association status and record who set it. |
| Database | Update `farms` (`farmer_organization_id`, `association_status`, set-by columns); insert `farmer_organizations` |
| Output | Updated request list; counts per barangay |

### 3.5.6 MAO personnel and coverage

| Step | Description |
|---|---|
| Actor | Municipal Agriculturist |
| Input | Name, e-mail (generated when blank), position, station type, area covered, assigned barangays and sitios, status |
| Process | `MaoPersonnelController` creates the account with the default password and `must_change_password`, and stores the assignments. `switchView` toggles a field user between own barangays and a read-only municipal view. |
| Database | Insert or update `users`, `mao_user_barangays`; insert `mao_activity_logs` |
| Output | MAO Personnel list with coverage |

### 3.5.7 MAO Planner and Calendar

| Step | Description |
|---|---|
| Actor | MAO Personnel; Farmer (Calendar, read) |
| Input | Activity type, barangay, start and end dates, title, notes |
| Process | `MaoPlannerController` maintains the user's own activities. `CalendarController::index` combines planting schedules, harvest dates, advisories, and posts marked for the calendar. |
| Database | Insert, update, delete `mao_activities`; read `farmer_planting_schedules`, `planting_advisories`, `feed_posts` |
| Output | Planner list; calendar |

---

## 3.6 Reporting Flow

| Step | Description |
|---|---|
| Actor | MAO Personnel |
| Input | Report type (one of nine), date range, barangay, season; export format; publish action |
| Process | 1. `ReportController::generate` creates an analytics run (10 per minute). 2. `RunPythonAnalytics` executes the `report_analytics` task for the five record-based types; `ManuscriptReportBuilder` builds the four summary types in PHP from stored data. 3. The result is saved as a report with a series key; regenerating the same type and filters increments the version. 4. `ReportExtrasController` exports to Excel (PhpSpreadsheet) or PDF (dompdf) and sets Draft or Published. 5. `ReportController` provides print and CSV export. |
| Database | Insert `analytics_runs`, `reports`; read the source tables for the chosen type |
| Output | Report on screen; CSV, XLSX, PDF, or printed copy |

Report types: Climate Records, Rice Production, Farmer Registration, Advisory, Community Feed, Seasonal Production, Yield Prediction Summary, Climate Trend, Barangay Risk.

---

## 3.7 Communication Flows

### 3.7.1 Notifications

| Step | Description |
|---|---|
| Actor | System; all roles |
| Input | A triggering event; "mark read" or "mark all read" |
| Process | Notifiers insert a notification with category and optional advisory link. `NotificationChanged` is broadcast on the private channel of the user. `NotificationController::feed` supplies the header bell list. |
| Database | Insert, update `notifications` (`is_read`, `read_at`) |
| Output | Bell counter and notification inbox |

### 3.7.2 Messages

| Step | Description |
|---|---|
| Actor | All roles |
| Input | Recipient search; message text; optional attachment |
| Process | `MessagingController` opens or reuses a conversation between two participants, stores the message with a client identifier to prevent duplicates, and broadcasts `MessageCreated` on the conversation channel; the page also polls. Attachments are private files served only to the two participants. A sender may delete an own message. |
| Database | Insert `conversations`, `conversation_messages`; update `read_at` |
| Output | Conversation thread with read status and typing indicator |

### 3.7.3 Announcements (Community Feed)

| Step | Description |
|---|---|
| Actor | MAO Personnel (write); Farmer (read) |
| Input | Title, body, category, visibility, event date, media, calendar flag; comment; reaction |
| Process | `CommunityFeedController` restricts writing to MAO and IT roles; a post is edited or deleted by its author. Farmers are redirected to the Announcements section of the Advisories page. |
| Database | Insert, update `feed_posts`, `feed_media`, `feed_comments`, `feed_reactions` |
| Output | Feed for staff; read-only announcements for farmers |

### 3.7.4 Contact message

| Step | Description |
|---|---|
| Actor | Visitor |
| Input | Name, contact detail, message |
| Process | `ContactMessageController` validates the form (3 per 10 minutes) and notifies MAO Personnel. |
| Database | Insert `notifications` |
| Output | Confirmation on the landing page |

### 3.7.5 Climora AI assistant

| Step | Description |
|---|---|
| Actor | All roles |
| Input | A question in English or Filipino (20 per minute) |
| Process | `AIOrchestratorService::answer` applies the cheapest sufficient route in this order: 1. **Moderation** (`ModerationService`): a blocked message proceeds no further and its text is not stored. 2. **Language detection** and conversation memory. 3. **Rule-based intent classification** (`IntentClassifierService`). 4. **Approved knowledge** (`LexiconService`, `KnowledgeAnswerService`) for definitions and how-to questions. 5. **System data** for weather, risk, advisory, and yield questions, read from stored records in read-only prediction mode. 6. **Groq** only when none of the above can answer and the service is enabled. Farmer answers are shortened by `FarmerAnswerStyle`, with details available on request. |
| Database | Read `ai_*` tables, `knowledge_base`, `chatbot_responses`, `external_weather_data`, `heatmap_areas`, `planting_advisories`, `monthly_weather_predictions`; insert `a_i_chats`, `ai_moderation_events` |
| Output | Answer with source name, confidence score, and response time |

---

## 3.8 IT Administration Flows

### 3.8.1 User management

| Step | Description |
|---|---|
| Actor | IT Personnel |
| Input | Name, e-mail, role, status, barangay, contact; filters by role and status |
| Process | `UserManagementController` (resource) creates, edits, deletes, and restores accounts. A change to a user clears the cached authenticated user so that it takes effect on the next request. |
| Database | Insert, update `users`; insert `system_logs` |
| Output | User list with last sign-in and status |

### 3.8.2 System settings, security, and permissions

| Step | Description |
|---|---|
| Actor | IT Personnel |
| Input | System name, office name, logo, weather source note, climate and risk thresholds, notification switches, farmer approval switch, maintenance mode and message; session timeout, password policy, lockout attempts and minutes; module-by-role matrix |
| Process | `SystemSettingsController` validates and saves through `SystemSettings::set`. Only values that differ from the defaults are stored. `SystemMaintenanceMode` shows the maintenance page to every role except IT. `ModuleAccess::save` stores only modules from which a role was withdrawn; IT modules cannot be withdrawn from IT. |
| Database | File `storage/app/system-settings.json` (not a database table); insert `system_logs` |
| Output | Settings applied on the next request |

### 3.8.3 Backup, verification, and restoration

| Step | Description |
|---|---|
| Actor | Scheduler; IT Personnel |
| Input | "Back up now"; file selection for download or restore with a typed confirmation |
| Process | `iclimate:backup-database` (daily, 03:15) runs mysqldump or pg_dump, with credentials passed outside the command line, and prunes files past the retention period. `iclimate:verify-backup` (03:25) confirms that the newest archive is readable. `BackupController::store` and `restore` queue `RunDatabaseMaintenance`; restoration always takes a fresh backup first and supports MySQL/MariaDB dumps only. Limits: 5 backups and 3 restorations per 10 minutes. |
| Database | Backup files on the server; insert `backup_verifications`, `maintenance_records`, `system_logs` |
| Output | Backup history with verification status; downloadable file |

### 3.8.4 Model management, registry, and evaluation

| Step | Description |
|---|---|
| Actor | IT Personnel |
| Input | Dataset upload; model file upload; "Compare"; "Retrain" with an algorithm; "Validate", "Activate", or "Fail" on a registry entry |
| Process | 1. `ModelManagementController` queues `RunModelManagement`, which runs `python_scripts/model_management.py` to compare Multiple Linear Regression, Random Forest, and Gradient Boosting by RMSE, MAE, and R², or to retrain one algorithm. A retrained model is registered as a candidate. 2. `ModelRegistryService::validate` checks the file and its metadata. 3. `activate` marks one model active per slot and, when runtime synchronisation is on, instructs the Python service through `/model/switch` with an administrator token and verifies the loaded model. 4. `ModelEvaluationService` reports metrics only when at least thirty validated observations exist (configurable). |
| Database | Insert, update `ml_models`; insert `maintenance_records`, `system_logs`; files in `storage/models/` |
| Output | Comparison report; registry with one active model; evaluation page |

### 3.8.5 Model Feedback

| Step | Description |
|---|---|
| Actor | IT Personnel |
| Input | Review marker on a response; decision (for example keep or retrain), algorithm, note |
| Process | `ModelFeedbackService` summarises farmer survey responses by model version; `ModelFeedbackController::decide` records the decision. |
| Database | Update `farmer_survey_responses` (`reviewed_at`, `reviewed_by`); insert `model_feedback_decisions` |
| Output | Feedback summary and decision history |

### 3.8.6 System health, dashboard, and logs

| Step | Description |
|---|---|
| Actor | IT Personnel; Scheduler |
| Input | Page request; filters; "Retry failed jobs"; CSV export |
| Process | `iclimate:warm-dashboard-cache` (every two minutes) prepares seventeen health checks (`SystemHealthService`), including database, queue worker heartbeat, scheduler runs, ML service, weather fetches, and backups. `ItDashboardService` prepares the dashboard. `SystemLogController` lists and exports audit entries. |
| Database | Read `scheduled_task_runs`, `weather_fetch_logs`, `failed_jobs`, `jobs`, `backup_verifications`, `system_logs`, `users` |
| Output | Health status, IT dashboard, log list, CSV file |

### 3.8.7 Rice varieties

| Step | Description |
|---|---|
| Actor | IT Personnel (write); all roles (read) |
| Input | Variety details or an import file (5 per minute) |
| Process | `RiceVarietyController` with `RiceVarietyPolicy`; `RiceVarietyImporter` inserts, updates, or skips rows by import key and records the import. Farmers obtain an expected-harvest preview through `/rice-varieties/estimate`. |
| Database | Insert, update `rice_varieties`; insert `rice_variety_imports` |
| Output | Catalogue page by role; variety details |

---

## 3.9 Scheduled Processes

All times are Philippine time (`config app.schedule_timezone`).

| Command | Frequency | Function |
|---|---|---|
| `iclimate:refresh-barangay-weather` | Every 10 minutes | Weather for nineteen barangays, then risk rating |
| `iclimate:refresh-weather-cache` | Every 3 minutes | Keeps stored municipal weather fresh |
| `iclimate:fetch-pagasa-advisories --force` | Every 15 minutes | PAGASA bulletins |
| `iclimate:automation-run --force-weather` | Every 30 minutes | Combined automation pass (weather, advisories) |
| `iclimate:refresh-barangay-risk` | Twice per hour | Reconciliation of risk ratings |
| `iclimate:refresh-flood-risk` | Twice per hour | Flood risk refresh |
| `iclimate:warm-dashboards` | Every minute | Prepares MAO and IT dashboards when their data changed |
| `iclimate:warm-dashboard-cache` | Every 2 minutes | Prepares System Health |
| `iclimate:clean-stale-queue` | Every 5 minutes | Closes unfinished requests; removes repeated jobs |
| `iclimate:publish-scheduled-alerts` | Every minute | Publishes due alerts |
| `iclimate:process-datasets` | Daily 01:30 | Dataset processing task |
| `iclimate:refresh-predictions` | Daily 02:00 | Queues monthly forecasts |
| `iclimate:warm-prediction-cache` | Daily 02:15 | Completes forecasts; marks cache ready |
| `iclimate:warm-farmer-yields` | Every 3 hours at :40 | Farm and barangay yield estimates |
| `iclimate:backup-database` | Daily 03:15 | Database backup and pruning |
| `iclimate:verify-backup` | Daily 03:25 | Backup readability check |
| `iclimate:refresh-lian-boundaries` | Daily | Barangay boundary polygons |
