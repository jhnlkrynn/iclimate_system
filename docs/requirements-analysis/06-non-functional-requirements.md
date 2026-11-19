# 6. Non-Functional Requirements

Each requirement is stated with the evidence in the implementation and an assessment. "Met" means that the mechanism exists in the code; it does not mean that the property was measured in operation, except where a measurement is cited.

## 6.1 Security

| ID | Requirement | Evidence in the implementation | Assessment |
|---|---|---|---|
| NFR-SEC-01 | Passwords shall be stored only in hashed form. | `Hash::make` in registration and account controllers; Laravel's default hasher | Met |
| NFR-SEC-02 | The password policy shall be configurable. | `SystemSettings`: minimum length 8, mixed case, numbers, symbols; applied through `Password::defaults()` | Met |
| NFR-SEC-03 | Repeated failed sign-ins shall be limited. | `LoginRequest`: lockout after five attempts for one minute by default; CAPTCHA after repeated failures (`CaptchaService`) | Met |
| NFR-SEC-04 | Access shall be restricted by role. | `RoleMiddleware` on every role-specific route; HTTP 403 otherwise | Met; covered by `RoleAuthorizationTest` |
| NFR-SEC-05 | Module access shall be adjustable without code changes. | `CheckModuleAccess`, `ModuleAccess` (twenty-one modules) | Met |
| NFR-SEC-06 | A deactivated account shall lose access immediately. | `EnsureAccountIsActive` signs the account out on its next request | Met |
| NFR-SEC-07 | Users shall access only their own data. | Owner lookups in `FarmController`; `MaoScope` for barangay coverage; private attachments; channel authorisation in `routes/channels.php` | Met; covered by `BroadcastChannelAuthorizationTest`, `CommunityFeedAuthorizationTest` |
| NFR-SEC-08 | Requests shall be rate limited. | A named `throttle` limit on nearly every state-changing route and on public API routes | Met |
| NFR-SEC-09 | Forms shall be protected against cross-site request forgery. | Laravel web middleware group (CSRF token); `form-action 'self'` | Met |
| NFR-SEC-10 | Responses shall carry browser security headers. | `SecurityHeaders`: X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, Content-Security-Policy, HSTS over HTTPS, no caching of authenticated pages | Partially met: the content policy permits `'unsafe-inline'` and `'unsafe-eval'`, as the code itself notes |
| NFR-SEC-11 | Security-relevant actions shall be audited. | `SystemAuditLogger` → `system_logs`; `MaoActivityLogger` → `mao_activity_logs` | Met |
| NFR-SEC-12 | Secrets shall not appear in command lines, logs, or pages. | `DatabaseBackupService` passes credentials through environment variables; `RuntimeModelClient` returns generic errors | Met in the code read |
| NFR-SEC-13 | Model switching shall require authorisation. | `/model/switch` requires the `X-Model-Admin-Token` header (`ML_ADMIN_TOKEN`) | Met |
| NFR-SEC-14 | Transport shall be encrypted. | HSTS is sent only when the request is secure; HTTPS depends on server configuration | Dependent on deployment |
| NFR-SEC-15 | E-mail ownership shall be verified. | Verification routes exist; `User` does not implement `MustVerifyEmail` | **Not met** |
| NFR-SEC-16 | Sessions shall expire. | Database sessions; configurable timeout; no "remember me" | Met |
| NFR-SEC-17 | Abusive chat input shall be filtered. | `ModerationService` with whole-word matching and disguise handling | Met; dictionary-based, as the code notes |
| NFR-SEC-18 | Default credentials shall be changed at first use. | `EnsurePasswordChanged`; skipping is permitted while `MAO_ALLOW_SKIP_PASSWORD_CHANGE=true` (default) | Partially met: the default permits skipping and the default password is a known value |
| NFR-SEC-19 | Public API endpoints shall expose no personal data. | `routes/api.php`: boundaries, weather, risk map, and risk forecast are public and rate limited; the mobile sign-in response returns the farmer profile | Partially met: mobile registration has no CAPTCHA and mobile sign-in has no lockout beyond the rate limit of five per minute |

## 6.2 Usability

| ID | Requirement | Evidence in the implementation | Assessment |
|---|---|---|---|
| NFR-USE-01 | Farmer pages shall be available in English and Filipino. | `lang/en`, `lang/fil` (twelve files each); `SetFarmerLanguage`; `users.preferred_language` | Met for farmer pages and the MAO dashboard and menu; IT pages are in English only |
| NFR-USE-02 | Farmer text shall use plain language. | `PlainLanguagePagesTest`, `MaoDashboardSimpleWordingTest`, `HeatMapTerminologyTest`; `FarmerAnswerStyle` | Met by convention and tests |
| NFR-USE-03 | The interface shall be usable on mobile browsers. | Responsive layout; mobile navigation (`mobile-navigation.js`); `ResponsiveLayoutTest`; browser tests `crud-mobile.spec.mjs`, `mobile-navigation.spec.mjs`; project report of checks at 360, 390, and 414 pixels | Met according to repository tests and documents; not re-measured here |
| NFR-USE-04 | Values shall carry units and plain explanations. | `YieldUnit` (t/ha), `PredictionWording`, `chart-explainer.js`, `info-modal.js`, `ReportTypeCatalog` | Met |
| NFR-USE-05 | The system shall state when data are unavailable or delayed. | Freshness labels in `LianBarangayWeatherService`; "No data" in the risk rating; `SavedDataStatesTest` | Met |
| NFR-USE-06 | Colour contrast shall meet WCAG AA. | Stated in `docs/panel-revisions.md` (body text 14.7:1); palette in `config/palette.php`; `PaletteTest` | Stated by the project; not independently measured |
| NFR-USE-07 | Errors shall be shown in plain messages. | Custom validation messages; custom error views (`errors/503`, `errors/system-maintenance`) | Met |
| NFR-USE-08 | Usability shall be evaluated with users. | Table `usability_survey_responses` exists; the survey pages are retired | **Not evidenced**: no usability evaluation results are in the repository |

## 6.3 Performance

| ID | Requirement | Evidence in the implementation | Assessment |
|---|---|---|---|
| NFR-PERF-01 | No page request shall train or run a model. | `ReadPreparedPredictionsOnly`, `PredictionReadMode`; jobs run after the response or on the queue | Met; `AiPredictionReadOnlyTest` |
| NFR-PERF-02 | Expensive pages shall be prepared in advance. | `PreparedDashboards`, `iclimate:warm-dashboards` (every minute), `iclimate:warm-dashboard-cache`; rebuild only when dependencies change (`CacheScope`) | Met; `PreparedDashboardsTest`, `CacheDependencyTest` |
| NFR-PERF-03 | Weather for nineteen barangays shall be obtained in one request. | `LianBarangayWeatherService` batched request (about 1.5 seconds, as stated in the code) | Met as designed |
| NFR-PERF-04 | Model inference shall be fast. | Project note: 20 to 35 ms through the ML service; about 0.8 s through the local fallback | Measured by the project on a development machine |
| NFR-PERF-05 | Database access shall be indexed. | Four index migrations (`add_performance_indexes`, `add_more_navigation_performance_indexes`, `add_runtime_performance_indexes`, `add_messaging_performance_indexes`) | Met; `DatabaseQueryRegressionTest`, `PagePerformanceTest` |
| NFR-PERF-06 | The chatbot shall answer from local data before any external call. | Routing order in `AIOrchestratorService`; Groq timeout and cool-down settings | Met; `AiResponseSpeedTest` |
| NFR-PERF-07 | Response time targets shall be defined and met under load. | No load test or stated target was found | **Not evidenced** |

## 6.4 Reliability and Availability

| ID | Requirement | Evidence in the implementation | Assessment |
|---|---|---|---|
| NFR-REL-01 | A failed external refresh shall not destroy valid data. | Last-valid record retained; stale data labelled | Met |
| NFR-REL-02 | A database outage shall produce a clear message. | `bootstrap/app.php` renders "Data Service Unavailable" (HTTP 503) for connection failures | Met; `DataServiceUnavailableTest` |
| NFR-REL-03 | Scheduled commands shall not overlap. | `withoutOverlapping` on every scheduled command; one log file per command | Met |
| NFR-REL-04 | The database shall be backed up daily and the backup verified. | `iclimate:backup-database` 03:15; `iclimate:verify-backup` 03:25; `backup_verifications` | Met; `DatabaseBackupTest`, `BackupVerificationTest` |
| NFR-REL-05 | Restoration shall be recoverable. | A fresh backup is taken before every restoration; `iclimate:test-restore` | Met for MySQL/MariaDB |
| NFR-REL-06 | Backups shall be stored off the server. | Backups are written to a directory on the same server | **Not met** |
| NFR-REL-07 | The ML service shall have a fallback. | `PythonService` local inference | Met |
| NFR-REL-08 | Stalled requests shall be closed. | `iclimate:clean-stale-queue` every five minutes | Met |
| NFR-REL-09 | Background processes shall be supervised. | `QueueWorkerHeartbeat`; System Health checks; start scripts (`start-services.sh`, `start-services.bat`) | Partially met: monitoring exists; automatic restart depends on deployment |
| NFR-REL-10 | Real-time delivery shall degrade safely. | Pages poll when broadcasting is off; `ResilientBroadcastingTest` | Met |
| NFR-REL-11 | Timestamps shall be consistent. | Stored in UTC, displayed in Asia/Manila; `KeepsDatabaseClockColumns`; `DatabaseClockColumnsTest` | Met for Eloquent models; raw updates are not guarded |
| NFR-REL-12 | The automated test suite shall pass. | `docs/known-failing-tests.md` lists thirty-seven failing PHPUnit tests as of 17 October 2026, attributed to interface changes not reflected in the tests | **Not met as of that date**; current status not re-run in this analysis |

## 6.5 Maintainability

| ID | Requirement | Evidence in the implementation | Assessment |
|---|---|---|---|
| NFR-MNT-01 | Business logic shall be separated from controllers. | Service classes grouped in seventeen namespaces | Met, with exceptions below |
| NFR-MNT-02 | Parameters shall be configurable. | `config/weather_risk.php`, `yield.php`, `farm_calendar.php`, `prediction.php`, `iclimate.php`, `backup.php`, `user_deletion.php`; `SystemSettings` | Met |
| NFR-MNT-03 | Features shall be switchable. | Flags for Field Reports, Chatbot Knowledge, candidate evaluation, farmer approval, sample-data exclusion | Met |
| NFR-MNT-04 | Schema changes shall be versioned. | One hundred migration files | Met |
| NFR-MNT-05 | The system shall have automated tests. | 156 feature test files, unit tests, Python tests (`python_scripts/tests`), Playwright browser tests | Met in coverage; see NFR-REL-12 |
| NFR-MNT-06 | Models shall be versioned and replaceable. | `ml_models` registry with validation and activation; metadata files beside artefacts | Met |
| NFR-MNT-07 | Operations shall be documented. | Runbooks and audits under `docs/`; knowledge graph under `graphify-out/` | Met |
| NFR-MNT-08 | Code units shall be of manageable size. | `MonthlyWeatherRandomForest.php` (2,269 lines), `AIOrchestratorService.php` (1,139), `BarangayRiskMapService.php` (1,107), `routes/web.php` (1,182) | Partially met: several units are large |
| NFR-MNT-09 | Retired functions shall be removed. | Retired pages are kept as redirects; legacy tables remain (`announcements`, `usability_survey_responses`, `heatmap_area_forecasts`) | Partially met |
| NFR-MNT-10 | The system shall support one database engine consistently. | Code paths for both MySQL/MariaDB and PostgreSQL; compatibility migrations dated November 2026 | Partially met: dual support adds maintenance cost |

## 6.6 Portability and Compatibility

| ID | Requirement | Evidence | Assessment |
|---|---|---|---|
| NFR-PORT-01 | The system shall run on Windows and Unix-like servers. | `start-services.bat`, `start-services.sh`; `MysqlDumpLocator`, `PostgresToolLocator`, `PythonRuntime` | Met by design |
| NFR-PORT-02 | The ML service shall be containerisable. | `python_scripts/Dockerfile`, `docker-compose.yml` | Met |
| NFR-PORT-03 | The system shall be adaptable to another municipality. | Barangays, coordinates, and farm types are constants in `App\Support\Lian*` | **Not met** without code changes |

## 6.7 Data Integrity and Accuracy

| ID | Requirement | Evidence | Assessment |
|---|---|---|---|
| NFR-DATA-01 | The system shall never present an invented value. | `PredictionEngine` rule; "No data" states; "Not provided by source" in `AdvisoryWeatherService` | Met by design |
| NFR-DATA-02 | Records shall carry provenance. | Author and verifier columns; `RecordProvenance`, `DataProvenanceService`, `YieldProvenanceDisplayTest` | Met |
| NFR-DATA-03 | Duplicate records shall be prevented. | Unique constraints on climate and production records and on `heatmap_areas.barangay` | Met |
| NFR-DATA-04 | Demonstration data shall be distinguishable from real data. | `SampleDataDetector`, `iclimate:data-audit`, `iclimate:purge-sample-data` | Partially met (see Document 9) |
| NFR-DATA-05 | Predictions shall have known accuracy. | No verified accuracy for the deployed model | **Not met** (see Document 9) |
