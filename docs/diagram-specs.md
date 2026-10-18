# iClimate Chapter Diagram Specifications

Read-only analysis of the repository as it exists on 2026-10-18. Diagram labels use the visible UI names where the routes/views establish them. Items marked `[to confirm]` are not established by code or are explicitly marked uncertain in the project documents. Items marked `[verify source]` are theory citations that must be checked against the thesis RRL before submission.

## 1. CONCEPTUAL FRAMEWORK — Input–Process–Output

### Diagram layout

Use three large boxes arranged left to right: **INPUT → PROCESS → OUTPUT**. Place a feedback/evaluation loop below OUTPUT returning to PROCESS. Inside INPUT, use three grouped sub-boxes. The exact box text below is ready to paste.

### INPUT

**Knowledge Requirements**

- Rice production: farms, farm area, irrigation type, planting schedules, harvest/yield, seasons and rice yield in tons per hectare.
- Climate and weather: rainfall, temperature, humidity, wind, weather codes, forecasts, historical climate records and PAGASA advisories.
- Agricultural risk: flood, drought, typhoon, heat, heavy rain, strong wind, dry-month and crop-data conditions.
- Planting and decision support: planting calendars, advisory rules, recommended actions, risk classifications and barangay coverage.
- Data analytics: validation, filtering, weather-versus-yield correlation, risk scoring, Random Forest forecasting, model comparison using RMSE, MAE and R².

**Software Requirements**

- PHP 8.2+ and Laravel 12; Blade templates; Laravel routing, middleware, Eloquent ORM, migrations, queues and scheduler.
- HTML/CSS/JavaScript; Tailwind CSS, Bootstrap, Alpine.js, Chart.js, Leaflet, Axios and Vite.
- Relational database configured by Laravel; current development configuration is PostgreSQL, while the project documentation and backup restore path also support MySQL/MariaDB `[to confirm deployment database]`.
- Python FastAPI/uvicorn service with pandas, NumPy, scikit-learn, SciPy, joblib and pydantic.
- Open-Meteo, PAGASA, GeoRisk Philippines, OpenStreetMap/Esri map layers and optional Groq API; configured mail server for password reset.

**Hardware Requirements**

- Server/host capable of running the Laravel web application, relational database, Laravel scheduler, database queue worker, and Python ML service.
- Client desktop or mobile device with a current web browser and Internet connection.
- Exact CPU, RAM, storage, operating system, browser support matrix and network bandwidth are not specified by the implementation `[to confirm]`.

### PROCESS

**Development method:** the repository documents a phased manuscript feature plan with eight completed phases, but does not explicitly establish Agile/Scrum, Waterfall or RAD as the formal development methodology. Use **Phased iterative development [to confirm formal method]**.

**Core system process:**

1. Collect data from MAO-entered/imported climate and production records; farmer farms, boundaries, planting schedules, harvest and survey responses; stored PAGASA history; live Open-Meteo forecasts; PAGASA advisories; and barangay boundary data.
2. Validate and normalize data: form rules, CSV row checks, record verification, barangay/season/irrigation filters and missing-data checks.
3. Store data in Laravel's relational database, model files/metadata, local storage and database-backed queue/scheduler tables.
4. Analyze and forecast: compute dashboard aggregates, weather trends, climate/yield correlations, rule-based barangay risk, planting advice, monthly weather forecasts, and rice yield estimates. Queue slow analytics and call the FastAPI ML service over HTTP where configured.
5. Visualize and report: dashboards, charts, Weather Analysis, Heat Map, calendars, notifications, community feed, reports, CSV/XLSX/PDF export and print views.
6. Notify and support decisions: publish approved planting advisories, scheduled alerts and in-app notifications; provide Climora AI answers from system knowledge/live data with optional Groq fallback; deliver password-reset mail when configured.

**Background operation:** Laravel scheduler commands refresh weather/risk/predictions, publish due alerts, warm caches, verify backups and record task runs; the queue worker handles queued analytics, predictions, maintenance and report work.

### OUTPUT

**iClimate: a web-based climate and rice production decision-support system for the Municipal Agriculture Office of Lian, Batangas.**

- **Rice Farmer:** dashboard, current weather and 16-day forecast, barangay Heat Map, expected yield and prediction history, farms and boundaries, planting schedules/calendar, planting advisories, notifications and Typhoon Safety Check-In, My Reports, Climora AI, community feed, messages and feedback surveys.
- **MAO Personnel:** MAO Dashboard, farmer approvals/profiles, climate and rice production records, CSV import/validation, barangay profiles, Heat Map/risk views, Weather Analysis, Impact Analysis, Yield Forecast, planting advisories and alerts, MAO Planner, announcements, notifications, reports, messages and activity log.
- **IT Personnel / Administrator:** IT Dashboard, users, settings, permissions, security, backups, maintenance records, system health, system logs, model registry/evaluation, model feedback, model retraining/comparison/upload and AI knowledge management.

### FEEDBACK / EVALUATION

- Implemented evidence: `usability_survey_responses` stores one response per user with role and survey items; `UsabilitySurveyResponse` calculates a score and benchmark comparison.
- Implemented feedback loops also include advisory feedback, weather forecast feedback, yield prediction feedback and model feedback decisions.
- Formal evaluation instrument/standard is not established in the implementation. ISO/IEC 25010 may be a suitable candidate only if it is already in the thesis/RRL `[to confirm; do not label as implemented]`.

## 2. THEORETICAL FRAMEWORK

Theories are not derivable from source code. The following are candidate lenses grounded in implemented features; verify all bibliographic details and retain only theories already supported by Chapter 2/RRL.

| Candidate theory/model | Original author/year | One-sentence fit | iClimate application |
|---|---|---|---|
| Decision Support Systems theory | Gorry and Scott Morton (1971) `[verify source]` | Computerized systems support semi-structured managerial decisions by combining data, models and user judgment. | MAO Dashboard, Heat Map, Weather/Impact Analysis, Yield Forecast and advisories combine stored records, rules and ML outputs for agricultural decisions. |
| Technology Acceptance Model | Fred D. Davis (1989) `[verify source]` | Perceived usefulness and perceived ease of use influence acceptance of an information system. | Responsive farmer/MAO dashboards, English/Filipino UI, notification inbox, charts and Climora AI are observable usability/adoption mechanisms. |
| Climate-Smart Agriculture framework | FAO (2013) `[verify source]` | Climate-smart agriculture targets productivity, resilience/adaptation and, where relevant, mitigation. | Weather monitoring, risk classification, planting advice and yield forecasting support productivity and climate-risk adaptation; mitigation is not an established system output. |
| DeLone–McLean Information Systems Success Model | DeLone and McLean (1992; updated 2003) `[verify source]` | Information quality, system quality, service quality, use, satisfaction and net benefits form an IS success evaluation model. | Data validation, system health, response-time tests, usability surveys, feedback tables and decision-support outputs provide candidate measures. |
| Statistical learning / supervised regression | Algorithm-specific theory and source `[verify source]` | A model learns a mapping from labeled features to a continuous target and is evaluated on held-out data. | Python uses Random Forest Regressor for rice yield and monthly weather forecasts; Multiple Linear Regression, Random Forest and Gradient Boosting are compared with RMSE, MAE and R². |

### Theoretical-framework drawing layout

Place the five theory/model boxes on the left, a central box labeled **iClimate Climate and Rice Production Decision-Support System**, and feature/output boxes on the right: **Weather Monitoring**, **Heat Map/Risk Assessment**, **Yield Prediction**, **Planting Advisories and Alerts**, **Reports and Dashboards**, **User Feedback/Evaluation**. Draw labeled arrows such as “explains decision support,” “guides acceptance/usability,” “frames climate adaptation,” “provides evaluation dimensions,” and “supports forecasting method.” Keep citations in the thesis, not inside the production diagram unless required by the school format.

## 3. SYSTEM ARCHITECTURE

### Components

1. Client browser: responsive Blade pages and JavaScript modules (`resources/js`), charts, Leaflet maps, local GeoJSON and external map tiles.
2. Laravel web application: `routes/web.php`, `routes/api.php`, `routes/auth.php`, `routes/panel.php`, `routes/manuscript.php`; controllers, middleware, policies/role checks, services, Eloquent models and migrations.
3. Relational database: users, roles-as-user-attributes, climate/production/farm data, weather, risk, advisories, reports, messages, notifications, AI, logs, model registry and feedback.
4. Database queue: Laravel `jobs`, `job_batches`, `failed_jobs` plus queued analytics/prediction/maintenance work.
5. Scheduler/command layer: `routes/console.php`, `app/Console/Commands`, and host scheduler/cron; commands include weather refresh, risk refresh, prediction preparation, automation, alert publishing, dashboard warming and backups.
6. Queue worker: consumes database-backed jobs and invokes analytics, predictions, reports, notifications and maintenance.
7. Python ML service: `python_scripts/main.py` FastAPI service, `predict.py`, model-management/retraining scripts and `requirements.txt`; Docker Compose exposes it on port 8010 by default and Laravel uses `FARMING_AI_API_URL`/related configuration.
8. Model/data storage: `storage/models`, `storage/datasets`, `storage/app/private/ml`, local backups, report exports, attachments and generated files.
9. External data/services: Open-Meteo weather API; PAGASA web advisories and historical Ambulong data; GeoRisk Philippines boundary source; OpenStreetMap/Esri tiles; optional Groq AI fallback; configured SMTP/mail service for password reset.

### Connections

- Browser → Laravel: HTTP/HTTPS web routes, authenticated sessions, Blade pages and JSON/API endpoints.
- Browser → map providers: Leaflet tile requests to OpenStreetMap/Esri; browser → local GeoJSON for Lian boundaries.
- Laravel → database: configured PostgreSQL connection in the current environment; migrations and Eloquent queries; MySQL/MariaDB remains a documented supported/deployment path `[to confirm]`.
- Laravel → database queue: dispatches jobs; queue worker → queue tables: claims/completes/retries jobs.
- Scheduler/cron → artisan commands: periodic weather, risk, prediction, advisory, alert, cache, backup and health work.
- Laravel/worker → FastAPI ML service: HTTP prediction, analytics, model health and model-management requests; service ↔ model storage for `.pkl` and metadata.
- Laravel → Open-Meteo: HTTP current/daily/hourly weather requests; saved in `external_weather_data`, `weather_fetch_logs` and related forecast tables.
- Laravel → PAGASA: HTTP/web parsing or imported historical data for advisories and source records; exact PAGASA endpoint is not a stable API contract `[to confirm]`.
- Laravel → GeoRisk/boundary source: boundary acquisition/synchronization; runtime UI currently also uses `public/geojson`.
- Laravel → Groq: optional HTTP fallback for Climora AI when internal knowledge/data cannot answer.
- Laravel → mail service: password-reset links only when mail is configured; otherwise the app uses its fallback instruction.
- Laravel → local storage: backups, generated report files, uploads, attachments, model exports and ML dataset exchange.

## 4. USE CASE DIAGRAM

The PlantUML source is `docs/diagrams/use-case.puml`. Actors are **Guest/Visitor**, **Rice Farmer**, **MAO Personnel**, **IT Personnel / Administrator**, **Weather API**, **PAGASA advisory source**, **Email service**, and **ML module**. The source intentionally does not invent a separate mobile-app actor: mobile login/register API endpoints exist, but the project scope says no mobile client is included.

### Use-case evidence grouping

- Guest: Register Account, Sign In, Reset Password (`routes/auth.php`, auth controllers, registration views).
- Rice Farmer: Dashboard, Weather Forecast, Heat Map, Farms, Farm Boundary, Planting Schedules, Forecast/Yield Surveys, Yield Prediction, My Reports, Notifications, Typhoon Safety Check-In, Climora AI, Community Feed, Messaging, Planting Advisories and Advisory Feedback (`routes/web.php`, farmer controllers/services/views, `docs/chapter1-scope-and-limitations.md`).
- MAO: MAO Dashboard, climate/production records, import/validation, barangays, farmer approvals/profiles, personnel, planner, advisories/alerts, weather/impact/yield analysis, reports, announcements, notifications and activity log (`routes/web.php`, MAO controllers/services/views).
- IT: IT Dashboard, users, settings, permissions, health, backups, maintenance, ML models, model evaluation/feedback, system logs and AI knowledge (`routes/web.php`, IT controllers/services/views).
- External actors: weather API is used by weather services; PAGASA source is used by advisory refresh/import; Email is used for password reset; ML module is called by queued prediction/analytics and model-management paths.

`<<include>>` is used only for clear implementation dependencies: authenticated dashboards/features require Sign In; Weather Forecast and Heat Map are dashboard features; advisory management validates/publishes notifications; model management uses evaluation; MAO advisory approval uses record validation. `<<extend>>` is used for password reset from Sign In. Exact use-case generalization is not drawn because role inheritance is not implemented as a UML actor hierarchy; roles share routes/features through middleware and controllers.

## 5. ERD

The Mermaid ERD is `docs/diagrams/erd.mmd`. It includes application-designed tables and omits framework infrastructure tables from the main graph.

### Skipped framework tables

`password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, and `failed_jobs` are framework/infrastructure tables. They remain relevant to deployment and queue operation but are not domain entities in the ERD.

### Data dictionary

The compact dictionary below lists every application table created by migrations. `FK` means a foreign key; `UK` means unique; `?` means nullable; `timestamps` means Laravel `created_at`/`updated_at` timestamp columns. Later migrations add validation, targeting, performance indexes and prediction fields; the final state is represented.

| Table | Columns (type; key/nullable notes) |
|---|---|
| users | id bigint PK; name string; email string UK; email_verified_at timestamp?; password string; role enum/index; contact_number string?; address text?; barangay string?; status string/index (Active/Inactive/Pending/Rejected); last_login_at/approved_at timestamp?; approved_by FK?; rejection_reason string?; remember_token; avatar_path?; timestamps |
| farmer_profiles | id; user_id bigint FK UK; full_name string; contact_number string?; address text?; barangay string/index; farm_area decimal?; farm_type enum; timestamps |
| farms | id; user_id FK; farm_name string; barangay string?; location_description string?; location_method string; latitude/longitude decimal?; farm_size_hectares decimal?; farm_size_method; farm_size_estimate_range?; farm_size_unit?; farm_type?; is_primary boolean; archived_at timestamp?; timestamps |
| farm_boundaries | id; farmer_profile_id FK UK; farm_id FK UK?; boundary_coordinates json; calculated_area_hectares decimal; calculated_perimeter_meters decimal; timestamps |
| climate_records | id; record_date date UK/index; rainfall/temperature/humidity/wind_speed decimal?; season enum; source string; verified_at timestamp?; verified_by FK?; timestamps |
| rice_productions | id; barangay/season/irrigation_type strings/index; yield_per_hectare/area_hectares/total_production decimal?; year year; production_month date?; remarks text?; verified_at timestamp?; verified_by FK?; timestamps |
| barangay_profiles | id; barangay string UK; latitude/longitude/farm_area_hectares decimal?; risk_classification string?; flood_prone/drought_prone boolean; notes text?; updated_by FK?; timestamps |
| heatmap_areas | id; barangay string; risk_level/risk_type enum; risk_score decimal?; description text?; risk component fields added by later migrations; timestamps |
| heatmap_area_forecasts | id; barangay; forecast_month date; risk_level/risk_type; risk_score; predicted_yield?; rainfall_status?; planting_advisory?; irrigation_recommendation?; description/main_risk_reason/suggested_action?; computed_at?; timestamps |
| external_weather_data | id; source/location_name; barangay_id bigint?; latitude/longitude; forecast_date/time?; weather_code; temperature/max/min; humidity; rainfall_mm; precipitation_probability; wind_speed; soil_temperature/moisture; evapotranspiration_mm; raw_response json?; fetched_at; timestamps |
| advisory_rules | id; name UK; advisory_type; description?; severity; priority; conditions json; recommendation; source_name/reference?; is_active; requires_crop_data; created_by FK?; timestamps |
| planting_advisories | id; title; content; type; target_barangay?; posted_by FK; status; advisory_type/summary/message/recommended_action/severity/priority/target_barangay_id/target_scope/source/source_url; weather_data_id FK?; advisory_rule_id FK?; generation_key UK?; generated_automatically; requires_review; valid_from/until/published_at; approved_by FK?; approved_at; rejection_reason/metadata?; timestamps |
| advisory_feedback | id; planting_advisory_id FK; user_id FK; is_helpful?; mao_response?; mao_responded_at?; mao_responded_by FK?; timestamps |
| announcements | id; title; content; category; posted_by FK; status; timestamps |
| notifications | id; user_id FK; planting_advisory_id FK?; title; message; type; is_read; category?; read_at?; timestamps |
| reports | id; report_type; title; generated_by FK; payload json?; status; version; series_key?; published_at?; timestamps |
| system_logs | id; user_id FK?; action; details?; later metadata/ip_address/user_agent/severity fields; timestamps |
| ai_chats | id; user_id FK; question; answer; intent; prediction/weather/yield/planting/irrigation/warnings json?; explanation?; confidence_score/response_time_ms?; hybrid AI metadata added later; timestamps |
| feed_posts, feed_media, feed_comments, feed_reactions | post: id/user_id FK/title/body/category/visibility/event_date/timestamps; media: id/feed_post_id FK/path/original_name/mime_type/media_type/size/timestamps; comment: id/feed_post_id/user_id FK/body/timestamps; reaction: id/feed_post_id/user_id FK/type/timestamps; post+user unique |
| conversations, conversation_messages | conversation: id/participant_one_id FK/participant_two_id FK/last_message_at/timestamps, participant pair unique; message: id/conversation_id FK/sender_id FK/body?/attachment fields?/read_at?/timestamps |
| knowledge_base | id; question; answer; category; keywords json?; source_type/source_name/source_url?; verified; times_used; confidence?; timestamps |
| typhoon_safety_responses | id; user_id FK; event_key/title; status enum; note?; responded_at; user/event unique; timestamps |
| farmer_organizations, farmer_organization_members | organization: id/name UK/barangay/farm_type/area_hectares?/farmer_count?/timestamps; member: id/farmer_profile_id FK/farmer_organization_id FK/date_joined?/timestamps; pair unique |
| usability_survey_responses | id; user_id FK UK; role; survey item columns (one per configured item); timestamps |
| analytics_runs | id; uuid UK; type/status; requested_by FK?; cache_key?; input json; result/error?; started_at/completed_at?; timestamps |
| farmer_planting_schedules | id; user_id FK; farm_id FK?; planting_date; scheduled_harvest_date/harvest_month?; season/farm_type; barangay?; farm_area_hectares?; notes?; progress_status?; prediction fields/details/recommended_actions?; production_month?; timestamps |
| advisory/forecast/prediction feedback | `weather_forecast_feedback`: user_id FK, monthly_weather_prediction_id FK, barangay, forecast fields, accuracy/observed_condition; `yield_prediction_feedback`: user_id FK, farm_id FK?, farmer_planting_schedule_id FK UK, prediction/actual/harvest fields; `farmer_survey_responses`: user_id FK, advisory/prediction FK?, subject/category/scores/model_version/date/reviewer FK?; all include timestamps |
| field_reports | id; title; barangay; issue_type/status; description?; reported_by/assigned_to FK?; resolved_at?; timestamps |
| heatmap_area_forecasts | see heatmap row above; unique barangay+forecast_month |
| rice_yield_model_comparisons | id; user_id FK; algorithm/model and training/test fields; metrics (RMSE/MAE/R²) and metadata; timestamps |
| monthly_weather_predictions | id; prediction_year/month; rainfall/tmax/tmin/temperature_average/humidity/wind fields; season/weather_model; training dates; generated_at/expires_at; status; metadata; timestamps |
| ml_models | id; purpose/name/model_type/version; target_variable/file_path/metadata_path; training_years/feature_list/hyperparameters/compatibility json?; test_year?; mae/rmse/r2?; trained_at?; status/is_active; active_slot UK?; timestamps |
| backup_verifications | id; backup_filename/size/sha256; verified_at/status; archive_entry_count/failure_reason?; timestamps |
| ai_knowledge_topics, ai_terms, ai_synonyms, ai_faqs | topics: id/slug UK/category/title_en/title_fil?/description?/prefers_live_data/is_context/is_active/timestamps; terms: id/topic_id FK/category/term_en/term_fil?/definition/context/related/example/approved response/source?/is_active/timestamps; synonyms: id/term_id FK/synonym/language/kind/is_active/timestamps; FAQs: id/topic_id FK?/category/questions/answers/keywords/source?/is_active/timestamps |
| ai_blocked_terms, ai_blocked_patterns, ai_moderation_events | blocked_terms: id/term/normalized_term UK/language/category/exceptions?/is_active/timestamps; patterns: id/pattern/description?/language/category/is_active/timestamps; events: id/user_id FK?/language/category/reason_code/blocked/created_at |
| scheduled_task_runs, weather_fetch_logs | scheduled task: id/task_name/started_at/finished_at/status/output_summary/timestamps; weather log: id/provider/started_at/duration_ms/status/error_message?/timestamps |
| farms/MAO additions | `mao_activities`: user_id FK, activity fields; `mao_user_barangays`: user_id FK, barangay_profile_id FK?, barangay/sitio; `mao_activity_logs`: user_id FK?, actor snapshot, action/record snapshot/barangay; `maintenance_records`: performer FK? and maintenance fields; `municipal_yield_predictions`: year/month/season/barangay/predicted/actual/split/model/source; all are represented in the ERD. |

### Relationship and mismatch notes

- `users`–`farmer_profiles` is 1:1 by unique `farmer_profiles.user_id`.
- `users`–`farms`, `farms`–`farmer_planting_schedules`, and `users`–many operational records are 1:N through explicit foreign keys.
- Farmer organizations and farmer profiles are M:N through `farmer_organization_members`.
- Conversations have two user foreign keys rather than a pivot table; messages are 1:N from conversations.
- Heat-map tables and several production/climate tables use barangay strings rather than FK references to `barangay_profiles`; this is a deliberate denormalized boundary and should be shown as a labeled logical association only, not an FK relationship.
- `farm_boundaries` retains `farmer_profile_id` and later adds nullable `farm_id`; the model supports both legacy profile ownership and farm ownership. Decide whether the chapter ERD should show the legacy path as “compatibility” `[to confirm]`.
- The migration history contains an earlier `heatmap_area_forecasts` create migration followed by a drop/re-create migration; the ERD shows the final table once.
- `FarmerProfile` contains a singular `farmerOrganization()` relationship while also defining many-to-many `organizations()`; the member pivot is the schema-backed relationship. Verify whether the singular relation is legacy/unused.
- `RiceProduction` and `ClimateRecord` expose verification relationships after later migrations; those FKs are included in the ERD.
- `field_reports`, `farmer_organizations`, `usability_survey_responses`, AI moderation tables and some API routes exist but are hidden, limited or retired according to Chapter 1. Include them in the full data ERD only if the study's final scope includes them.

## Evidence map for review

| Diagram element | Primary evidence |
|---|---|
| Roles and access | `routes/web.php`, route middleware, `app/Models/User.php`, `app/Http/Middleware`, role-aware controllers/views |
| Farmer features | `routes/web.php`, `app/Http/Controllers/FarmController.php`, `FarmerPlantingScheduleController.php`, `DashboardController.php`, `resources/views/farmer-records`, `resources/views/dashboards/farmer`, `docs/chapter1-scope-and-limitations.md` |
| MAO features | `routes/web.php`, `app/Http/Controllers/Mao*`, `PlantingAdvisoryController.php`, `AdvisoryAlertController.php`, `MaoHomeService.php`, `resources/views/dashboards/mao`, `docs/manuscript-phase-plan.md` |
| IT features | `routes/web.php`, `app/Http/Controllers/ModelManagementController.php`, `SystemSettingsController.php`, `BackupController.php`, `SystemHealthController.php`, `resources/views/dashboards/it`, `docs/manuscript-phase-plan.md` |
| Weather and external sources | `app/Services/WeatherApiService.php`, `app/Services/Weather/OpenMeteoService.php`, `app/Services/Weather/PagasaAdvisoryService.php`, config files, `public/geojson`, Chapter 1 technical/data scope |
| ML and forecasting | `python_scripts/main.py`, `predict.py`, model-management/retraining scripts, `requirements.txt`, `app/Services/Prediction`, `app/Services/Analytics`, `docker-compose.yml`, model migrations |
| Scheduling/queues | `routes/console.php`, `app/Console/Commands`, `app/Jobs`, `app/Services/SystemHealthService.php`, README, framework queue migrations |
| Database entities/keys | `database/migrations`, `app/Models`, model relationship methods, `database/seeders` |
| UI names and outputs | `resources/views`, `resources/js`, route names and Chapter 1/manuscript docs |
| Evaluation and feedback | `usability_survey_responses`, advisory/weather/yield feedback migrations and models, related controllers/tests |

## Items to confirm before drawing the final thesis figures

- Formal development methodology (Agile/Scrum, Waterfall, RAD, or another method).
- Deployment hardware, operating system, browser support, network and hosting setting.
- Final database engine: current environment is PostgreSQL; Chapter 1 describes MySQL/MariaDB and backup restore is MySQL/MariaDB-oriented.
- Exact PAGASA source/endpoints and whether GeoRisk data are runtime-fetched or treated as a one-time imported source.
- Formal evaluation standard/instrument, especially whether ISO/IEC 25010 is actually used.
- Chapter 2/RRL citations and exact author/year details for each theoretical candidate.
- Whether hidden Field Reports, Chatbot Knowledge, candidate-model evaluation, Farmer Organizations and Usability Survey are in the final study scope.
- Whether retired Live Forecasting, Usability Survey and Farmer Organizations pages should be omitted from the use-case figure despite remaining routes/tables/models.
- Whether mobile authentication APIs are documented as an integration boundary or excluded because no mobile client is part of the study.
- Whether `farm_boundaries.farmer_profile_id` is retained as a legacy relationship in the final ERD.
- Model training data years/source, risk-threshold approval by MAO, and final deployment period.

## Half-built, hidden, retired or potentially unused areas

- Chapter 1 explicitly says Field Reports and Chatbot Knowledge management are hidden/feature-flagged by default; their routes/migrations/controllers still exist.
- Candidate yield-model evaluation is present in data/models and IT paths but described as hidden in the manuscript limitations.
- Mobile login/register API endpoints exist, but no mobile client is included in scope.
- Retired Live Forecasting, Usability Survey and Farmer Organizations pages are described as redirecting or retired, while related schema/models remain.
- `farmer_organizations` and its pivot are schema-backed, but the current visible user journey may not use them.
- PAGASA and GeoRisk integrations are partly source/import oriented rather than guaranteed stable APIs; draw them as external data sources with a “fetch/import” label, not as a guaranteed live API unless confirmed.
- The deployed model's original training data are not available; retraining creates candidate models that IT must validate and activate.
- The current Chapter 1 notes only 60 monthly modeling records and negative hold-out R²; show predictions as estimates and avoid implying validated operational accuracy.

