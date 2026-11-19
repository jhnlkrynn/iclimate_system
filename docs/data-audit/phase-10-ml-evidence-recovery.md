# iClimate Phase 10 — ML Evidence Recovery and Scientific Validation Preparation

**Project:** iClimate  
**Assessment date:** 2026-11-13  
**Scope:** Evidence recovery and safe validation preparation only. No model, prediction formula, risk formula, active record, or production service was changed.

## 1. Evidence recovered and provenance

The repository contains useful candidate-model evidence, but no newly recovered original training package for the deployed model.

| Evidence | What is established | Reliability and limitation |
|---|---|---|
| `storage/models/rice_yield_model_final.pkl` | The deployed artifact is a scikit-learn Pipeline with the exact 12 input names/order and a Random Forest regressor. SHA-256: `f3a54e12ecf85e66340475e2c49ce5c15d9c58efd82213435015a275734b777c`. | Artifact metadata is directly inspectable. It does not contain source-row identity, feature dictionary, or an independent test set. |
| `storage/datasets/iClimate_Modeling_Ready_TimeAware.csv` | 60 candidate rows covering 2020–2025; 52 `TRAIN` rows and 8 `TEST` rows; no duplicate year/month keys or blank fields. | Repository-generated candidate dataset; its MAO source authenticity and independent-observation status are not approved. |
| `storage/models/rice_yield_model_candidate_metadata.json` | Candidate schema, target, hyperparameters, training years, 2025 holdout, and candidate metrics. | Describes a separate 10-feature candidate, not the deployed 12-feature artifact. |
| `storage/models/rice_yield_season_retrain_report.json` | Reproducible candidate season-calendar comparison and dataset checksum `e49e3727c239cceb32b368f3ed39af5da48de895f168ed448f92fdbfdf74b8c3`. | Explicitly records that the deployed model was not retrained because its training package is absent. |
| `storage/datasets/Ambulong_Monthly_Data.csv` and `database/data/Ambulong Monthly Data.csv` | 132 calendar rows spanning 2015–2025. January 2020 is present only as `-999` missing-value sentinels. | Tanauan/Ambulong station data, not Lian-specific observations; proxy use requires approval. |
| `storage/app/backups/*.backup` | MariaDB/PostgreSQL database dumps containing operational rows and prediction metadata. | No original deployed training CSV, notebook, preprocessing package, or feature dictionary was found in the backups. Operational prediction rows are not independent evaluation evidence. |

The candidate 2025 holdout is reproducible evidence about the candidate recipe only: MAE `0.3944`, RMSE `0.4690`, R² `-1.1043`. The October-as-Wet candidate variant reports MAE `0.3847`, RMSE `0.4584`, R² `-1.0105`; leave-one-year-out after the change reports MAE `0.5137`, RMSE `0.7155`, R² `-0.1745`. None of these results validates the deployed model or establishes scientific accuracy.

## 2. Missing training artifacts

The following remain absent after searching repository code, model directories, datasets, backups, history, and notebook/training-file names:

1. Original deployed-model training CSV/database export with stable observation IDs.
2. Original 12-feature training script or notebook.
3. Authoritative feature dictionary with units, transformations, spatial scale, and time windows.
4. Original missing-value and outlier policy.
5. Original train/test or cross-validation split and independent test rows.
6. Target definition and observation unit: farm, barangay, municipal season, or monthly total.
7. Approval record establishing the authenticity of the MAO source and whether the climate series is an accepted Lian proxy.

The candidate scripts and datasets are not substitutes for these artifacts.

## 3. Updated 12-feature compatibility matrix

The exact names and order are confirmed by the deployed artifact and production parity tests. The training meaning is not confirmed unless stated otherwise.

| Feature | Production calculation/evidence | Training compatibility status | Required evidence |
|---|---|---|---|
| `RAINFALL` | Target-period rainfall in mm | Unit plausible; aggregation and source equivalence unverified | Original window and station/source definition |
| `TEMP_AVG` | Target-period average temperature in °C | Unit plausible; exact window unverified | Original temperature aggregation |
| `TEMP_RANGE` | Spread derived from recent monthly averages | Likely mismatch with a max/min-style training spread, but not proven | Original formula and source columns |
| `Area` | Farm area in hectares, with non-farm fallback behavior | Training spatial scale unknown; farm-level compatibility not established | Original area field and observation scale |
| `Previous_Rainfall` | Latest prior climate value | Current history can be stale or unavailable | Lag definition and freshness rule |
| `Previous_Temp` | Latest prior climate value | Current history can be stale or unavailable | Lag definition and source |
| `Rainfall_6M` | Six-month sum in production | Possible sum/mean mismatch remains unresolved | Exact six-month formula and units |
| `Temp_3M` | Recent three-month average | Window and aggregation unverified | Original rolling-window definition |
| `Temp_6M` | Recent six-month average | Window and aggregation unverified | Original rolling-window definition |
| `Seasonal_Rainfall` | Season-relative aggregate | Season boundaries and aggregation unverified | Approved season calendar and formula |
| `Seasonal_Temp` | Season-relative aggregate | Season boundaries and aggregation unverified | Approved season calendar and formula |
| `Season` | `Wet`/`Dry` production category | Categories are artifact-confirmed; original calendar is not | Original training calendar and category rule |

The recovered candidate artifacts use a different 10-feature schema (`MONTH_SIN`, `MONTH_COS`, `SEASON_WET`, weather variables, and directional encodings). They provide no evidence that the deployed 12-feature transformations are compatible.

## 4. Dataset validation results

Added `validate_research_dataset()` to `python_scripts/ml_validation.py`. It is an isolated intake check for future researcher-approved snapshots and does not import, rewrite, or classify active database rows. It checks:

- required observation ID, date, group, season, provenance, source, and unit fields;
- valid dates and `Wet`/`Dry` values;
- expected yield units;
- duplicate observation identities;
- demo, derived, allocation, and model-output classifications;
- Ambulong/Lian proxy labelling;
- independent group counts.

The readiness runner now accepts an optional `--evaluation` snapshot and `--evaluation-strategy chronological|season_grouped`. It rejects overlapping groups or observations before any evaluation result can be considered.

The repository candidate dataset passes basic CSV completeness and duplicate year/month checks, but fails scientific intake as approved independent evidence because its provenance and independent observation status are not established. The current Ambulong source now produces an explicit `INVALID_CLIMATE_SENTINEL` diagnostic for the January 2020 `-999` row, in addition to missing/stale current-history diagnostics.

## 5. Independent evaluation readiness

Current status: **BLOCKED**.

The candidate file has a chronological 2025 holdout, but its 60 rows are monthly municipal-shaped records without an approved independent observation definition. Candidate metrics therefore remain candidate-only evidence. No deployed-model predictions were generated, no records were imported, and no retraining occurred.

The isolated evaluator supports:

- chronological validation;
- season-grouped validation;
- duplicate observation and group leakage detection;
- MAE, RMSE, R², bias, sample count, group count, and baseline comparisons when genuine pairs are supplied.

It will not choose a cutoff, shuffle rows, or authorize an accuracy claim.

## 6. Automated readiness-gate results

Executed with the deployed artifact and current repository CSVs, target date `2026-11-13`:

| Gate | Status | Result |
|---|---|---|
| Deployed feature schema | **PASS** | Exact 12-feature names/order confirmed |
| Model checksum/metadata | **PASS** | Artifact metadata and checksum available |
| Input provenance | **BLOCKED** | Production wiring is not approved training provenance |
| Climate freshness | **FAIL** | Current months are missing/stale; invalid `-999` sentinel detected |
| Agricultural provenance | **BLOCKED** | MAO-shaped rows are not approved independent observations |
| Leakage and independent evidence | **BLOCKED** | No approved evaluation snapshot supplied |
| Research dataset intake | **BLOCKED** | No approved snapshot supplied |
| Prediction sensitivity | **NOT VERIFIED** | No approved perturbation evidence; runner never calls prediction code |
| Accuracy claim | **BLOCKED** | `ACCURACY NOT VERIFIED` |

The PASS gates are technical artifact checks only and do not imply predictive accuracy.

## 7. Files created or modified

Created/modified for Phase 10:

- `python_scripts/ml_validation.py` — research snapshot intake validation and climate sentinel handling.
- `python_scripts/run_ml_readiness.py` — optional evaluation snapshot and strategy-aware leakage gate.
- `python_scripts/tests/test_ml_validation.py` — intake, provenance, unit, duplicate, and proxy tests.
- `python_scripts/tests/test_ml_readiness_runner.py` — optional snapshot and overlapping-season tests.
- `app/Services/MachineLearning/ModelEvaluationService.php` — clarified candidate-only wording; no prediction logic changed.
- `resources/views/model-evaluation/index.blade.php` — clarified that candidate metrics do not validate the deployed artifact.
- `docs/data-audit/phase-10-ml-evidence-recovery.md` — this report.

The existing Phase 9 command, model files, production data, prediction formulas, and risk formulas were preserved.

## 8. Test results

- Python validation/analytics suite: **36 passed, 0 failed, 2 third-party deprecation warnings**.
- Phase 10 targeted intake/readiness tests: **21 passed** when run as the two Phase 10 Python test files.
- Laravel focused regression coverage: **26 passed, 269 assertions**.
- Readiness command executed successfully and emitted valid JSON with `ACCURACY NOT VERIFIED`.
- `git diff --check`: clean.
- Full Laravel suite: **1,784 passed, 5 skipped, 13,821 assertions in 483.48s**.

No production database writes, prediction calls, model loads for activation, retraining, model replacement, formula changes, or service restarts were performed.

## 9. Remaining research approval requirements

1. Supply and authenticate the original deployed training package.
2. Approve the independent observation unit and target definition.
3. Confirm all 12 feature units, transformations, time windows, spatial scale, and season calendar.
4. Approve whether Ambulong may be used as a labelled Lian proxy.
5. Approve the climate-to-crop matching window and missing-value policy.
6. Approve the minimum independent sample/group count and chronological or season-grouped protocol.
7. Confirm MAO data provenance and whether monthly rows are independently observed or derived allocations.
8. Approve any change to farm-specific output presentation, model correction, retraining, replacement, or activation.

## 10. Concrete next implementation steps

1. Obtain the original training package and record checksums without placing it in production paths.
2. Populate a non-production intake snapshot using the required fields and source provenance declarations.
3. Have the adviser/MAO approve the observation unit, climate source, season calendar, and evaluation strategy.
4. Run the isolated intake and leakage gates against that frozen snapshot.
5. Reproduce the deployed feature table and compare it row-by-row with production feature construction.
6. Evaluate the deployed artifact and simple information-available baselines on independent groups only.
7. Publish metrics and limitations only if all evidence gates pass; otherwise retain `ACCURACY NOT VERIFIED`.
8. Seek separate approval before any retraining, replacement, activation, or UI policy change.

## Final assessment

Phase 10 recovered and checksum-recorded candidate evidence and added a safe research-data intake path. It did not recover the missing deployed training artifacts, so no scientific accuracy blocker can be cleared. iClimate remains technically prepared for an approved evidence delivery, while the deployed model remains scientifically unverified.
