# iClimate Requirements Traceability Matrix

Status values:

- **Implemented** — source code and executable tests provide evidence.
- **Partially implemented** — a workflow exists, but a required policy,
  external source, or complete acceptance criterion is missing.
- **Unverified** — source code cannot establish the requirement’s scientific or
  operational truth.
- **Proposed** — stakeholder approval is required before implementation or
  claim.

| ID | Requirement | Source evidence | Verification evidence | Status | Owner / approval |
|---|---|---|---|---|---|
| FR-I01 | Authenticate, verify, throttle, and apply account status | `routes/auth.php`, auth controllers, `User.php` | Authentication, registration, password and account tests | Implemented | IT / product owner |
| FR-I02 | Support Farmer, MAO Personnel, IT Expert | `User.php`, users migration | Role authorization tests | Implemented | Product owner |
| FR-I03 | Protect routes by role and verification | `routes/web.php`, `manuscript.php`, `panel.php`, `RoleMiddleware.php` | Feature authorization tests | Implemented | IT |
| FR-F01 | Farmer-owned farm and schedule workflows | Farmer controllers/services/models | Farmer home, calendar, farm and schedule tests | Implemented | MAO / product owner |
| FR-M01 | MAO farmer approval and scoped management | approval routes, `MaoScope`, MAO controllers | Approval, scope, personnel tests | Implemented | MAO |
| FR-M02 | Import and validate climate/production records | record import/validation controllers, provenance services | Dataset-record and validation tests | Partially implemented | MAO / data steward |
| FR-M03 | MAO forecasts and provenance-labeled actuals | `YieldPredictionController`, `MaoHomeService` | Prediction and report tests | Implemented | MAO |
| FR-A01 | IT health, logs, backups, and resources | `SystemHealthService`, `ItDashboardService` | System health, backup and IT tests | Implemented | IT |
| FR-A02 | Guard model registry lifecycle | model registry services/routes, `MlReadiness` | Model registry/readiness tests | Partially implemented | IT + researcher |
| FR-C01 | Persist prediction state, timestamps and errors | prediction models/jobs/migrations | Prediction request and calendar tests | Implemented | IT |
| FR-C02 | Queue long-running work | jobs, queue config, scheduler commands | Queue and isolated worker tests | Implemented | IT |
| FR-W01 | Weather source, cache and freshness states | weather services, monthly predictions | Weather/cache/risk tests | Partially implemented | MAO + weather/data owner |
| FR-R01 | Role-scoped risk and Heat Map layers | Heat Map controllers/services/channels | Heat Map and risk tests | Implemented | MAO |
| FR-R02 | Separate verified, unverified, derived and demo data | `RecordProvenance`, sample-data exclusions | Provenance and Heat Map tests | Implemented | MAO / data steward |
| FR-P01 | Reject invalid or missing prediction inputs | `PredictionEngine`, Python validation boundary | Python 43-test suite; live HTTP 422 checks | Implemented | IT |
| FR-P02 | Shared Laravel-to-Python prediction path | `PredictionEngine`, `PythonService`, FastAPI | Prediction parity and live endpoint checks | Implemented | IT |
| FR-P03 | Label estimates and preserve accuracy gate | Blade unvalidated component, readiness runner | Prediction-page and readiness tests | Implemented | Product owner / researcher |
| FR-N01 | Publish role-targeted advisories and notifications | advisory services, notifications, routes/channels | Advisory and notification tests | Implemented | MAO |
| FR-T01 | Realtime invalidation with fallback polling | events, Reverb manager, frontend live scripts | Reverb/realtime/broadcast tests | Implemented | IT |
| FR-O01 | Backup, restore test, maintenance and audit | backup services/commands, system logs | Backup and maintenance tests | Implemented | IT |
| FR-X01 | Approve prediction target scale | No definitive source artifact | Research approval required | Proposed | Adviser / researcher / MAO |
| FR-X02 | Approve evidence/provenance policy | Intake validators and provenance classes exist; source approval external | Research package review | Partially implemented | MAO / researcher |
| FR-X03 | Approve all 12 units and transformations | Runtime schema exists; original semantics absent | Original training package required | Unverified | Original model developer |
| FR-X04 | Approve independent evaluation and baselines | `ml_validation.py`, candidate scripts | Independent approved dataset required | Partially implemented | Adviser / researcher |
| FR-X05 | Decide unsupported farm-specific output policy | Existing labels and unavailable paths exist | Product/research decision required | Proposed | Adviser / product owner |
| FR-X06 | Define weather horizons and Lian/Ambulong use | Weather services and station loader exist | Data-source approval required | Partially implemented | MAO / researcher |
| FR-X07 | Define Heat Map verified-yield method | Heat Map supports unavailable and unvalidated states | Risk methodology approval required | Proposed | MAO / adviser |
| NFR-01 | Security and authorization | Middleware, channels, throttles, logs | Security/authorization tests | Implemented | IT |
| NFR-02 | Data integrity and provenance | Eloquent models, migrations, provenance services | Database/provenance tests | Partially implemented | Data steward |
| NFR-03 | Service availability and explicit degradation | health services, supervisor, fallback paths | Health and isolated recovery tests | Implemented | IT |
| NFR-04 | Cache/queue performance | cache services, prepared dashboards, jobs | Performance/cache tests | Implemented | IT |
| NFR-05 | Reproducibility and model traceability | model checksum/info, prediction metadata | Model/readiness tests | Partially implemented | IT + researcher |
| NFR-06 | Scientific honesty | unvalidated labels, blocked readiness gate | Phase 3–16 evidence reports and tests | Implemented | Adviser |
| NFR-07 | Maintainable shared prediction path | `PredictionEngine`, service contracts | Parity and regression tests | Implemented | Development team |
| NFR-08 | Localization and accessibility | farmer language middleware, Blade status labels | UI/plain-language tests; full WCAG audit absent | Partially implemented | Product owner |
| NFR-09 | Auditability | `SystemAuditLogger`, system/MAO logs | Audit and IT Expert administration tests | Partially implemented | IT / compliance owner |
| NFR-10 | Recovery and continuity | backups, retry broadcasts, supervisor | Backup, queue, Reverb tests | Partially implemented | IT / operations owner |

## Acceptance gates for future scientific validation

The following matrix must be complete before an accuracy claim or model
correction is approved:

| Gate | Required evidence | Current state |
|---|---|---|
| Training artifact identity | Original file, checksum, developer/source, model version | Missing |
| Feature compatibility | 12 definitions, units, transformations, windows, missing rules | Blocked |
| Observation provenance | Approved MAO source documents and observation IDs | Blocked |
| Independence | No training/tuning/demo/duplicate/derived overlap | Not demonstrated |
| Temporal design | Chronological or season-grouped split approved in advance | Not approved |
| Baselines | Overall mean and seasonal/information-available baselines | Tooling exists; approved dataset absent |
| Metrics | MAE, RMSE, R², bias, sample/group counts, limitations | Must wait for valid data |
| Target scope | Farm/barangay/municipal/season target approved | Unverified |
| Deployment decision | Researcher/adviser sign-off and rollback plan | Not approved |

Until all applicable gates pass, the system must retain **ACCURACY NOT
VERIFIED** and must not describe an unvalidated estimate as measured model
performance.
