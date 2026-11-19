# iClimate Phase 7 — ML Implementation Readiness

**Project:** iClimate  
**Assessment date:** 2026-11-13  
**Purpose:** Turn the Phase 3–6 research findings into an approval-dependent implementation roadmap. This document defines what may be prepared safely, what is blocked by missing evidence, and what must not be executed before researcher, MAO, or adviser approval.

## 1. Executive summary

iClimate's ML pipeline is technically integrated but not scientifically validated. The deployed rice-yield artifact has a known 12-column input schema, while its original training data, preprocessing recipe, observation scale, feature dictionary, and independent evaluation set remain unavailable. Existing Phase 4 safeguards correctly label yield outputs as unvalidated; they must remain in place.

The implementation strategy is therefore staged:

1. Prepare read-only validators, provenance structures, evaluation reports, and model-package checks.
2. Obtain and approve the original research evidence and data definitions.
3. Build a frozen, provenance-controlled research dataset outside active production records.
4. Reproduce the deployed model if possible; otherwise design a new model under an approved schema.
5. Evaluate chronologically or by complete season against simple baselines.
6. Promote a candidate only after independent performance, scope, provenance, and rollback gates pass.

No Phase 7 model calculation, active prediction, risk formula, database record, model artifact, or service was changed.

## 2. Current system and model readiness

### Completed work carried forward

- Phase 3 identified only 11 genuine-looking municipal-season observations; the 60 monthly rows remain unverified.
- Derived and demo rice records are not independent research observations.
- Phase 4 established likely input incompatibilities, stale climate windows, leakage risks, and lack of valid deployed-model accuracy evidence.
- Phase 4/5 presentation safeguards label prediction values as `Unvalidated estimate` and `Accuracy not verified`; the weather page displays `Confidence not measured`.
- Technical reliability work and regression coverage are complete; no model or prediction data was altered.

### Current readiness

| Component | Technical status | Research status | Phase 7 treatment |
|---|---|---|---|
| Deployed rice-yield artifact | Loads and accepts its 12-column schema | Accuracy and training provenance unverified | Preserve; audit read-only |
| Prediction engine | Centralized in `PredictionEngine` and `RiceYieldPredictionService` | Several definitions/units remain unconfirmed | Do not change until approval |
| Python rice service | Enforces deployed column names/order | Does not contain original training recipe | Add compatibility checks only in isolation |
| Weather Random Forest | Operational and independently testable | Not shown superior to seasonal baselines | Keep labelled and benchmarked |
| MAO agricultural data | 60 monthly rows, 11 inferred seasons | Original source and validation absent | Do not import/reclassify |
| Ambulong climate data | 131 station-month rows, Jan 2020 missing | Tanauan proxy, not verified Lian data | Require spatial-transfer approval |
| Heat Map | Operational formula preserved | Demo/derived influence requires policy decision | Add provenance reporting only after approval |

### Model scope decision

The deployed model must be treated as **scale-unknown**, not as a validated farm model. The artifact's inferred training range for `Area` is approximately 12–258 ha, while current farmer farms are approximately 0.1–30 ha. This suggests a material scale mismatch, but does not prove the original model was municipal. The original training rows are required before making a farm-versus-municipal decision.

## 3. Feature correction specifications

The current PHP-to-Python mapping is technically consistent:

`rainfall → RAINFALL`, `temp_avg → TEMP_AVG`, `temp_range → TEMP_RANGE`, `area → Area`, `previous_rainfall → Previous_Rainfall`, `previous_temp → Previous_Temp`, `rainfall_6m → Rainfall_6M`, `temp_3m → Temp_3M`, `temp_6m → Temp_6M`, `seasonal_rainfall → Seasonal_Rainfall`, `seasonal_temp → Seasonal_Temp`, `season → Season`.

That mapping is not evidence that the values are compatible with training. The following are implementation requirements, not approved changes:

| Feature | Current implementation | Required evidence | Possible correction after approval | Affected components |
|---|---|---|---|---|
| `RAINFALL` | Target-period forecast/period rainfall in mm | Training aggregation, station/source, target window | Recompute from the approved historical/forecast window | `PredictionEngine`, `PredictionOrchestrator`, `MonthlyWeatherRandomForest`, Python service |
| `TEMP_AVG` | Target-period mean temperature in °C | Training period and averaging method | Align source and aggregation to training | Same as above |
| `TEMP_RANGE` | Max-minus-min across recent monthly average temperatures | Whether training used daily max–min, monthly TMAX–TMIN, or another spread | Compute the exact approved spread from source observations | `PredictionEngine`, climate-history adapter, Python service |
| `Area` | Farmer farm area; municipal fallback for non-farmer requests | Training spatial unit and area semantics | Use farm, representative farm, or municipal value only if training scope supports it | `PredictionEngine::buildModelInput`, `municipalityArea`, farm/profile services |
| `Previous_Rainfall` | Latest climate-history rainfall | Lag definition and freshness threshold | Use target-month-minus-one observation only when available and valid | `ClimateHistory`, `PredictionEngine`, Python service |
| `Previous_Temp` | Latest climate-history temperature | Same as previous rainfall | Same as above | Same as above |
| `Rainfall_6M` | Sum of six recent rainfall values | Sum versus mean and exact window | Apply approved sum/mean and date bounds | `PredictionEngine`, climate-history adapter, Python service |
| `Temp_3M` | Mean of three recent temperatures | Statistic, date bounds, and missing-month policy | Apply approved rolling window | Same as above |
| `Temp_6M` | Mean of six recent temperatures | Statistic, date bounds, and missing-month policy | Apply approved rolling window | Same as above |
| `Seasonal_Rainfall` | Sum for rows classified into model season | Season calendar and whether partial seasons are allowed | Compute only from approved season window | `RiceSeason`, `PredictionEngine`, climate-history adapter |
| `Seasonal_Temp` | Mean for rows classified into model season | Same as seasonal rainfall | Same as above | Same as above |
| `Season` | Deployed path preserves May–September wet encoding; general iClimate calendar is May–October wet | Original training calendar and MAO season definition | Change only after approved schema/model decision | `RiceSeason`, `PredictionEngine`, dashboards, Python service |

### Important schema boundary

`PredictionEngine::REQUIRED_MODEL_INPUTS` also contains humidity, wind, and month values for the shared weather/decision-support path. They are not additional inputs in the deployed 12-feature rice-yield artifact. Any future model schema change must update the PHP mapping, Python input builder, metadata, tests, evaluation scripts, model registry, and all consumers together.

## 4. Dataset validation and provenance workflow

### 4.1 Collection and intake

1. Obtain the original MAO source document/export and record custodian, collection date, checksum, page/source reference, and correction history.
2. Obtain authoritative fields: observation ID, farm/barangay/municipality, planted area, production, yield, variety, irrigation, planting date, harvest date, and validation status.
3. Obtain climate records with station/provider, coordinates, units, timezone, missingness, revision date, and source checksum.
4. Keep an immutable raw-source copy separate from cleaned research tables and production tables.
5. Do not import into active tables until the data owner and adviser approve the intake mapping.

### 4.2 Record classification

Every row must receive one explicit provenance class:

| Class | Definition | Research use |
|---|---|---|
| `verified_observation` | Direct source row with traceable identity and validation status | Eligible, subject to quality checks |
| `unverified_source` | Source-shaped row whose original authority is not yet confirmed | Not eligible for final accuracy claim |
| `derived_allocation` | Calculated split from a municipal/season total | Never an independent observation |
| `demo_seed` | Generated example or seeded application data | Exclude |
| `corrected_observation` | Source observation with documented correction and reviewer | Eligible only with audit trail |
| `model_output` | Prediction or forecast | Never a ground-truth target |

The existing 110 derived allocations, 14 demo production rows, and demo farmer harvests must remain identifiable and excluded from validation until an approved provenance policy says otherwise.

### 4.3 Data-quality checklist

Before a row enters a modelling snapshot, verify:

- stable observation ID and source checksum;
- no duplicate observation or season identity;
- valid dates and timezone;
- planting/harvest order and approved crop-duration window;
- target unit is t/ha and production/area arithmetic is consistent;
- area is positive and at the approved spatial scale;
- rainfall and temperature units are explicit;
- missingness and imputation are recorded, never silently filled;
- climate station is geographically approved and labelled;
- no target-period/future values enter lag or rolling features;
- no demo/derived rows are duplicated into train or test;
- same-season rows are kept in the same validation group;
- feature values fall within the approved training/evaluation domain;
- dataset checksum, schema version, and reviewer are recorded.

## 5. Proposed evaluation and retraining workflow

### 5.1 Evaluation readiness gate

Evaluation may run only when:

1. target definition and independent unit are approved;
2. source rows are classified and validated;
3. feature definitions and units are approved;
4. climate windows and spatial source are approved;
5. demo and derived rows are excluded or explicitly handled;
6. training/test grouping is defined before metrics are calculated;
7. the dataset is frozen and checksummed.

If any gate fails, the report must state **ACCURACY NOT VERIFIED** and must not show a scientific accuracy score.

### 5.2 Leakage-free split

- Primary method: leave-one-season-out or forward-chaining by complete season.
- Secondary method: chronological year holdout when enough independent years exist.
- Never randomly split rows from one season across training and test.
- Never allow a derived copy of a training observation in the test set.
- Fit preprocessing only on the training fold.
- Record train/test observation IDs, seasons, years, and feature-source dates.

### 5.3 Baselines and metrics

Every candidate must be compared with:

- training-season mean yield;
- same-month or same-season historical average only when available at prediction time;
- last-known-season baseline when operationally justified.

Report MAE, RMSE, R², mean signed error/bias, sample count, independent-season count, uncertainty limitations, and the baseline delta. Negative R² must be explained as worse than the test-set mean relationship.

### 5.4 Reproducible retraining procedure — approval-gated

1. Freeze the approved dataset and calculate a checksum.
2. Record schema version, target definition, feature definitions, climate source, split plan, random seeds, and environment lockfile.
3. Run data-quality and leakage checks before fitting.
4. Fit preprocessing only within each training fold for evaluation.
5. Compare candidate algorithms with approved baselines.
6. Save the candidate artifact outside the active production path.
7. Save metadata containing dataset checksum, feature order, units, training groups, metrics, baseline metrics, code revision, environment, and approval status.
8. Run parity tests between PHP feature construction and Python model input order.
9. Run shadow comparison without changing user-visible predictions.
10. Obtain research and technical sign-off before registry activation.

No step above authorizes retraining or activation during Phase 7.

## 6. Prediction and Heat Map safeguard implementation plan

### Existing safeguards to preserve

- `Unvalidated estimate` labels on farmer, MAO, reports, calendar, records, feedback/survey, chat, and Heat Map surfaces.
- `Accuracy not verified` explanatory wording.
- `Confidence not measured` for the displayed technical weather confidence field.
- Existing stored predictions, historical analytics, and operational Heat Map formula.

### Approval-dependent options

| Option | Effect | Approval required |
|---|---|---|
| Keep numeric output with labels | Preserves continuity and historical behavior | Product owner + adviser |
| Suppress farm-specific numeric yield | Prevents unsupported precision from reaching users | Product owner + adviser |
| Show a transparent seasonal baseline | Provides a non-ML reference with different limitations | Researcher + adviser + product owner |
| Exclude demo/derived records from Heat Map | Improves research provenance but changes operational results | MAO + product owner + adviser |
| Keep records but show provenance mix | Preserves formula and data while making limitations explicit | MAO + adviser |

No option is implemented without approval.

### Affected safeguard components

Current presentation surfaces are primarily in `resources/views`, language files, `HeatMapLayerService`, and related risk-map views. Any future suppression or provenance policy must also review `MaoDashboardService`, `DashboardOverviewService`, `BarangayRiskMapService`, `AgriculturalRiskScorer`, `BarangayYieldForecastService`, `PredictionService`, and their feature tests.

## 7. Approval-dependent task matrix

| ID | Task/objective | Status | Affected files/components | Required evidence/approver | Dependencies | Testing | Acceptance | Rollback |
|---|---|---|---|---|---|---|---|---|
| P7-01 | Inventory and checksum source/model artifacts | **READY — Safe technical preparation** | Read-only scripts/docs; `storage/models`, `storage/datasets` | None; technical owner reviews | None | Repeatable checksum check | Inventory is reproducible and read-only | Delete report only; source files untouched |
| P7-02 | Add isolated schema/range/freshness validator | **READY — Safe technical preparation** | New validator/tests; no production formula | Technical owner | P7-01 | Unit tests with fixtures | Validator fails closed and never mutates records | Remove validator/tests |
| P7-03 | Recover original deployed training package | **BLOCKED — Requires missing evidence** | External researcher/MAO package | Original researchers | P7-01 | Reproduction/parity test | Training recipe and data dictionary are traceable | Keep current artifact unchanged |
| P7-04 | Approve target and independent unit | **PENDING APPROVAL** | Research protocol only | Adviser, statistician, MAO | P7-03 or source confirmation | Protocol review | Signed unit and target definition | No runtime effect |
| P7-05 | Approve climate station/window policy | **PENDING APPROVAL** | Climate adapter/evaluation config | Adviser, climate authority, MAO | P7-04 | Window and spatial checks | Every feature has source/date bounds | Revert evaluation config, not production data |
| P7-06 | Build frozen provenance-controlled research dataset | **BLOCKED — Requires missing evidence** | New research-data pipeline; separate storage | MAO source and P7-04/P7-05 approval | P7-03–05 | Provenance, duplicate, unit, leakage tests | Dataset checksum and row classifications published | Discard research snapshot only |
| P7-07 | Reproduce deployed model evaluation | **BLOCKED — Requires missing evidence** | Evaluation scripts and report | Original recipe plus approved split | P7-06 | Season-grouped holdout and baselines | Reproducible metrics and limitations | Do not alter deployed artifact |
| P7-08 | Define corrected feature schema | **PENDING APPROVAL** | `PredictionEngine`, `RiceYieldPredictionService`, Python service | Original researchers/adviser | P7-03/P7-06 | PHP/Python parity and unit tests | Approved definitions cover all 12 fields | Retain old schema behind no activation |
| P7-09 | Implement corrected inputs | **NOT AUTHORIZED — Must not execute** | Prediction engine, climate history, Python service | Explicit technical/research approval | P7-08 | Full prediction and regression suite | Only approved features reach model | Restore prior version/config; no live mutation |
| P7-10 | Train approved replacement/candidate | **NOT AUTHORIZED — Must not execute** | Training scripts, model artifact path | Adviser + technical owner | P7-06/P7-08 | Independent evaluation and baseline gate | Candidate passes promotion criteria | Keep prior artifact and registry pointer |
| P7-11 | Shadow-test candidate | **PENDING APPROVAL** | Model registry/shadow service/tests | Technical owner + adviser | P7-10 | Parity, drift, latency, output comparison | No user-visible activation and documented differences | Disable shadow path |
| P7-12 | Decide yield suppression/labels | **PENDING APPROVAL** | Prediction views and controllers | Product owner + adviser | Evidence review | Feature/page tests | Approved wording and scope | Restore labels/current display |
| P7-13 | Decide Heat Map provenance policy | **PENDING APPROVAL** | Risk services, dashboards, tests | MAO + product owner + adviser | P7-06 | Read-only comparison before/after | Formula/data policy documented | Preserve current formula and records |
| P7-14 | Activate validated model | **NOT AUTHORIZED — Must not execute** | Registry/configuration/deployment | Formal research and technical sign-off | All prior gates | Full suite, rollback drill, smoke tests | All acceptance criteria pass | Restore previous model pointer |

## 8. Testing and acceptance criteria

### Safe preparation tests

- Artifact loads read-only and exposes exactly 12 expected feature names.
- PHP mapping order equals Python request order.
- Validator detects wrong units, missing windows, stale lags, out-of-domain values, duplicate IDs, and same-season leakage.
- Provenance classification excludes demo and derived rows from evaluation fixtures.
- Snapshot/checksum tooling does not write active tables or model paths.

### Model evaluation tests

- Train/test group IDs are disjoint.
- Preprocessing is fit only on training folds.
- Every metric has row count, independent-group count, baseline, source, and checksum.
- Candidate does not pass when it only improves training metrics.
- Candidate must meet the approved baseline-improvement threshold on independent data.

### Runtime regression tests after any approved implementation

Run targeted tests for `PredictionParityTest`, prediction-engine plausibility, `PredictionsPageTest`, Heat Map data flow/yield tests, dashboard tests, model registry tests, and Python service tests, followed by the complete Laravel and Python suites. Do not report results until actually run.

### Scientific acceptance criteria

Before a model may be called scientifically validated:

1. Source provenance and observation identity are documented.
2. Target, spatial scale, crop window, and season definition are approved.
3. All 12 feature units and transformations are confirmed or replaced by an approved retrained schema.
4. Demo, derived, duplicated, and future-leaking rows are excluded.
5. Evaluation is season-grouped or chronological.
6. The model is compared with an information-available baseline.
7. MAE, RMSE, R², bias, row count, independent-group count, and uncertainty limitations are published.
8. Farm-level claims are supported by farm-level training and independent validation evidence.
9. UI wording and confidence interpretation are approved.
10. Rollback artifact and model metadata are verified.

## 9. Model versioning and rollback strategy

Every candidate package must include:

- immutable model artifact checksum;
- model name, semantic version, and status (`candidate`, `shadow`, `approved`, `active`, `retired`);
- feature names in exact order, units, transformations, and schema version;
- dataset checksum and provenance report;
- training code revision and Python environment lockfile;
- train/test groups and all metrics/baselines;
- approval record and activation timestamp;
- known limitations and supported prediction scale.

Promotion must be pointer/configuration based, with the previous approved artifact retained. Activation must be reversible by restoring the prior registry/configuration pointer, clearing only the candidate-specific cache if required, and verifying the prior model's parity tests. No database prediction history may be rewritten during rollback.

The current deployed artifact remains the rollback baseline until a formally approved replacement exists. Candidate files must never overwrite `storage/models/rice_yield_model_final.pkl`.

## 10. Researcher / MAO / adviser sign-off checklist

Blank fields indicate that approval has not been granted.

| Decision | Required approver | Evidence reference | Decision | Name/signature | Date |
|---|---|---|---|---|---|
| Original training data supplied and authentic | Original researchers + adviser |  | ☐ Approve ☐ Reject |  |  |
| MAO 60-row file verified and `month` meaning confirmed | MAO custodian |  | ☐ Approve ☐ Correct ☐ Reject |  |  |
| Independent observation unit selected | Adviser/statistical reviewer |  | ☐ Farm ☐ Barangay ☐ Month ☐ Season |  |  |
| Target yield and crop duration approved | Agriculture researcher + MAO |  |  |  |  |
| Climate source/spatial proxy approved | Adviser + climate authority |  | ☐ Ambulong proxy ☐ Lian data only ☐ Other |  |  |
| All 12 deployed feature definitions confirmed | Original researchers |  | ☐ Confirmed ☐ New schema required |  |  |
| Season calendar approved | MAO + adviser |  | ☐ May–Sep ☐ May–Oct ☐ Other |  |  |
| Minimum independent sample size approved | Adviser/statistical reviewer |  |  |  |  |
| Grouped/chronological evaluation protocol approved | Adviser/statistical reviewer |  |  |  |  |
| Baselines and promotion threshold approved | Adviser/statistical reviewer |  |  |  |  |
| Farm-specific output handling approved | Product owner + adviser |  | ☐ Label ☐ Suppress ☐ Baseline |  |  |
| Demo/derived Heat Map policy approved | MAO + product owner |  | ☐ Exclude ☐ Retain with provenance |  |  |
| Candidate retraining authorized | Adviser + technical owner |  | ☐ Yes ☐ No |  |  |
| Model activation authorized | Adviser + technical owner |  | ☐ Yes ☐ No |  |  |

## 11. Recommended implementation sequence

### Phase 7A — Safe preparation

1. Freeze this plan and the Phase 3–6 evidence references.
2. Add read-only artifact/schema/checksum inventory tooling.
3. Add isolated feature-domain, freshness, provenance, duplicate, and leakage validators.
4. Add fixtures and tests for derived/demo exclusion without touching active records.
5. Keep all current prediction labels and operational behavior unchanged.

### Phase 7B — Approval and evidence intake

1. Obtain the original training package and MAO source confirmation.
2. Complete sign-off for target, observation unit, climate source/window, season, and minimum sample size.
3. Build and checksum the research snapshot outside active production data.
4. Publish the data-quality and leakage report before any model fitting.

### Phase 7C — Evaluation and model decision

1. Reproduce the deployed model if possible.
2. Run grouped/chronological evaluation against approved baselines.
3. If reproduction fails or compatibility remains unsupported, keep the model unvalidated and propose a new approved schema/model.
4. Train only after explicit authorization and retain the current artifact as rollback.

### Phase 7D — Controlled product decision

1. Decide whether numerical farm-specific estimates stay visible, are suppressed, or are replaced by a baseline.
2. Decide whether Heat Map risk retains or excludes demo/derived records.
3. Shadow-test any candidate without user-visible activation.
4. Activate only after all scientific, technical, UI, and rollback acceptance criteria are signed.

## Final status

**READY:** documentation, read-only inventories, isolated validators, provenance schemas, evaluation templates, and regression fixtures that do not alter production behavior.

**BLOCKED:** reproduction and correction of the deployed model until original training artifacts and evidence are supplied.

**PENDING APPROVAL:** observation unit, target, climate window, season calendar, output suppression, and Heat Map provenance policy.

**NOT AUTHORIZED:** changing deployed input calculations, retraining, replacing or activating models, changing prediction formulas, recalculating historical analytics, or altering active database records.

Until the approval gates and scientific acceptance criteria pass, iClimate may be described as technically functioning but not as a scientifically validated farm-level ML prediction system.
