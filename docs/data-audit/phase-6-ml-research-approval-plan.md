# iClimate Phase 6 — ML Research Approval and Correction Plan

**Project:** iClimate  
**Assessment date:** 2026-11-13  
**Purpose:** Convert the Phase 3–5 audit findings into adviser-approved research decisions and an implementation-ready, leakage-free correction path.

## Executive summary

iClimate's prediction pipeline is technically operational, but the deployed rice-yield model is not ready for a scientific claim of farm-level accuracy. The repository contains the deployed model artifact and its 12 feature names, but not the original training dataset, original preprocessing script, feature dictionary, observation identities, or independent validation split.

The current code produces the required feature names and preserves the deployed artifact's apparent `Wet`/`Dry` encoding. That proves schema wiring, not scientific compatibility. Several production values remain outside the ranges inferred from the artifact, especially farm `Area`, six-month rainfall, and temperature range. The model's geographic and observation scale cannot be confirmed as farm-level.

The current research status is therefore:

> **ACCURACY NOT VERIFIED. Current rice-yield outputs must remain labelled as unvalidated estimates and must not be described as scientifically validated farm-specific predictions.**

This plan does not retrain, replace, activate, or alter any model. It does not modify active records, prediction formulas, risk calculations, or historical analytics.

## 1. Consolidated unresolved decisions from Phases 3–5

| Decision | Why it blocks validation | Required authority | Current status |
|---|---|---|---|
| Confirm the original MAO source and the meaning of `month` | The 60-row file may be planting-month, harvest-month, or season-allocation data | MAO + researchers | Unresolved |
| Choose the independent observation unit | Farm, barangay, month, and season rows are not interchangeable | Adviser + statistician | Season is the safest provisional unit; not approved |
| Confirm the deployed model's training scale | Farm-size inputs may be invalid if training rows were municipal totals | Original researchers | Unresolved; farm-level use is not established |
| Confirm all 12 feature definitions and units | Names in a pickle file do not define aggregation or spatial level | Original researchers | Unresolved |
| Define the crop/weather matching window | Yield must be matched to the correct planting-to-harvest period | MAO + agriculture researcher | Unresolved |
| Approve climate source and spatial proxy policy | Ambulong is Tanauan, not Lian | Adviser + climate/agriculture authority | Unresolved |
| Approve season boundaries | iClimate uses May–October generally; deployed model code preserves May–September | Adviser + MAO | Unresolved |
| Set minimum independent sample size | Current evidence is only 11 municipal seasons and unverified | Adviser/statistical reviewer | Unresolved |
| Approve validation split and baseline | Random row splits risk leakage across the same season | Adviser/statistical reviewer | Grouped/chronological testing proposed |
| Decide display treatment of current yield values | Labels are applied, but numerical estimates remain visible | Product owner + adviser | Suppression decision unresolved |
| Decide whether demo/derived records affect Heat Map risk | Existing operational formula reads records whose provenance is not independent | Product owner + MAO | Formula preserved; policy unresolved |

## 2. Current model readiness

### Deployed artifact and code contract

Read-only inspection of `storage/models/rice_yield_model_final.pkl` found a scikit-learn Pipeline with a Random Forest regressor:

- 300 trees, `max_depth=10`, `min_samples_leaf=1`, `random_state=42`.
- 12 artifact feature names:

  `RAINFALL`, `TEMP_AVG`, `TEMP_RANGE`, `Area`, `Previous_Rainfall`, `Previous_Temp`, `Rainfall_6M`, `Temp_3M`, `Temp_6M`, `Seasonal_Rainfall`, `Seasonal_Temp`, `Season`.

- `Season` is encoded as `Dry`/`Wet` by the artifact preprocessor.
- `RiceYieldPredictionService::INPUT_TO_MODEL_FEATURE` and `python_scripts/rice_yield_service.py::rice_features()` preserve the same deployed feature order.
- `PredictionEngine::REQUIRED_MODEL_INPUTS` also lists humidity, wind, and month values used by the wider prediction/weather path; these are not additional features in the deployed 12-feature rice-yield artifact.

The code contract is therefore technically coherent, but its scientific meaning is not verified.

### Farm-level suitability decision

The deployed model must be treated as **scale-unknown**, not farm-level. Inferred tree ranges suggest training `Area` values of approximately 12–258 ha, while current farmer farms are approximately 0.1–30 ha. This is evidence of a material scale mismatch, not proof of the original training scale.

Until the original training rows are recovered, the model cannot safely be described as a farm model. If the original rows are municipal or season totals, its output should be interpreted at that scale and the current farm-specific display should be suppressed or reworded only after approval.

## 3. Missing training artifacts and evidence

### Located

| Location | Evidence | What it cannot establish |
|---|---|---|
| `storage/models/rice_yield_model_final.pkl` | Deployed preprocessing, feature names, estimator parameters | Training observations, units, row identity, split, target provenance |
| `app/Services/Prediction/RiceYieldPredictionService.php` | PHP input-to-feature name mapping | Original training definitions |
| `python_scripts/rice_yield_service.py` | Python request schema and column order | Original training data and preprocessing history |
| `python_scripts/train_rice_yield_model.py` | Separate 7-feature smoke-test/target pipeline | Reproduction of the deployed 12-feature artifact |
| `python_scripts/compare_yield_features.py` | Candidate 7-feature evaluation path | Validity of the deployed model |
| `python_scripts/retrain_yield_season.py` | Candidate 10-feature time-aware experiment | Deployed model provenance; it explicitly leaves that model untouched |
| `storage/datasets/iClimate_Modeling_Ready_TimeAware.csv` | Candidate data and time-aware split | Verified MAO observations or deployed training data |
| `storage/models/rice_yield_model_candidate*` | Candidate artifacts and metadata | Production readiness |
| `database/seeders/data/mao-planting-summary-2020-2025.csv` | 60 monthly municipal rows | Authenticity, validation, and original source document |
| `database/data/Ambulong Monthly Data.csv` | Station history for Jan 2015–Dec 2025, Jan 2020 missing | Lian-specific climate observations |

### Must be obtained from the original researchers/MAO

1. Original training CSV/database extract with stable observation IDs.
2. Original feature-engineering and preprocessing code or notebook.
3. Data dictionary for units, aggregation windows, missing values, spatial scale, and target construction.
4. Original train/test split and evaluation report, including baseline.
5. Documentation of crop variety, irrigation, planted area, harvest date, production, and yield target.
6. Confirmation of whether rows are farm, barangay, municipal, monthly, or seasonal observations.
7. Written confirmation of the season calendar used in training.

## 4. Feature compatibility matrix

The matrix separates verified wiring from unverified scientific definitions. No input calculation is changed by this plan.

| Deployed input | Expected unit/meaning: verified vs assumption | Current implementation | Compatibility status | Approval needed |
|---|---|---|---|---|
| `RAINFALL` | Name and numeric rainfall input are verified; target aggregation is not | Forecast/period rainfall in mm | Unit plausible; window/source unverified | Define target period and source |
| `TEMP_AVG` | Numeric temperature in °C is verified; averaging window is not | Period average in °C | Plausible but unverified | Define window and station/spatial policy |
| `TEMP_RANGE` | Spread meaning is only inferred | Difference between recent monthly average temperatures | Likely incompatible with daily/monthly max–min training feature | Recover definition; do not substitute |
| `Area` | Name is verified; spatial scale and unit are not | Farmer farm area in hectares, or municipal fallback | Likely scale mismatch | Decide farm versus municipal target |
| `Previous_Rainfall` | Lag concept inferred from name | Latest climate history row | Staleness risk; not valid when source ends before target lag | Define lag and freshness rule |
| `Previous_Temp` | Lag concept inferred from name | Latest climate history row | Same staleness risk | Define lag and freshness rule |
| `Rainfall_6M` | Six-month window inferred; sum/mean is unknown | Six-month sum in mm | Likely incompatible: production around 1,983 mm versus inferred training range 54–311 mm | Confirm sum versus average |
| `Temp_3M` | Three-month aggregate inferred; statistic unknown | Three-month average | Unverified | Confirm statistic and window |
| `Temp_6M` | Six-month aggregate inferred; statistic unknown | Six-month average | Unverified | Confirm statistic and window |
| `Seasonal_Rainfall` | Season-relative aggregate inferred | Sum over rows assigned to model season | Unverified and potentially stale | Approve season and aggregation |
| `Seasonal_Temp` | Season-relative aggregate inferred | Average over rows assigned to model season | Unverified and potentially stale | Approve season and aggregation |
| `Season` | Artifact categories `Dry`/`Wet` verified; calendar is not | Deployed path uses May–September wet; general iClimate calendar uses May–October wet | Controlled code distinction, not researcher validation | Approve training/application calendar |

### Compatibility rule

No corrected input should be deployed from a plausible interpretation. A correction requires the original definition or a formally approved new schema followed by retraining and independent evaluation.

## 5. Data provenance requirements

### Agricultural data

- Treat the 60 MAO rows as **unverified source-shaped data** until the original document/export is confirmed.
- Treat the 110 barangay allocations as derived municipal distributions, not independent barangay observations.
- Exclude the 14 demo production rows and 5,794 demo harvests from research evaluation.
- Require MAO validation status, source custodian, observation date, correction history, and a stable observation ID.
- Preserve source rows separately from derived, demo, corrected, and model-generated rows.

### Climate data

- Preserve station name, coordinates, provider, units, timezone, missingness, and revision date.
- Label Ambulong as a Tanauan station proxy; never silently label it as Lian climate.
- Require an approved spatial-transfer rationale before using Ambulong to support Lian farm claims.
- Do not use forecast values as historical observations or future values in a validation target.

### Required provenance record for each modelling row

`observation_id`, `source_id`, `source_type`, `source_checksum`, `spatial_unit`, `crop/variety`, `planting_date`, `harvest_date`, `climate_station`, `climate_window_start`, `climate_window_end`, `feature_version`, `target_definition`, `validation_status`, and `derived_from`.

## 6. Proposed leakage-free validation methodology

### Unit and split

1. Make one complete municipal season the provisional independent unit.
2. If MAO confirms genuinely independent monthly observations, retain them for modelling but keep all months from one season in the same fold.
3. Use forward-chaining or leave-one-season-out evaluation.
4. Use a chronological year holdout as a secondary analysis when enough years exist.
5. Never randomly split copied, derived, or same-season rows across train and test.

### Baselines

At minimum compare against:

- Training-season mean yield.
- Same-season historical mean only when it would be available at prediction time.
- Last known season or last known observation where operationally appropriate.

A model is not useful merely because it has a positive R² or low training error; it must beat an information-available baseline on independent observations.

### Metrics and report fields

For every model and baseline report:

- MAE (t/ha).
- RMSE (t/ha).
- R², with negative values explicitly explained.
- Mean signed error/bias (t/ha).
- Number of rows and number of independent seasons.
- Train/test seasons and dates.
- Feature schema/version and source checksum.
- Climate source and spatial limitation.
- Missingness, duplicate, and leakage checks.

### Current evidence boundary

The deployed model has no valid independent accuracy result: **ACCURACY NOT VERIFIED**.

The candidate 2025 holdout is not evidence for the deployed model. Its recorded result was MAE `0.394`, RMSE `0.469`, R² `-1.1043`, compared with a mean baseline MAE `0.301`, RMSE `0.402`, R² approximately `-0.549`. This supports caution, not a production correction.

Weather evaluation also found the Random Forest underperforming seasonal baselines for several variables. It remains operationally testable but is not established as scientifically superior.

## 7. Model correction versus retraining options

### Option 1 — Recover and reproduce the deployed model

Preferred if the original team can supply the missing artifacts. Reproduce preprocessing, feature values, target construction, and split; compare the reproduced artifact and metrics. Only after successful reproduction should compatibility be reassessed.

### Option 2 — Retain the artifact but restrict interpretation

If the model cannot be reproduced, preserve it for continuity and historical comparison only. Do not claim farm-level accuracy. Keep the current labels or suppress numerical output after approval. This is not a scientific correction.

### Option 3 — Retrain a documented municipal/seasonal model

Use verified observations, an approved target, a fixed feature schema, documented climate windows, grouped evaluation, and baseline comparisons. Do not activate until acceptance criteria below are met.

### Option 4 — Use a transparent baseline while evidence is collected

Expose a clearly labelled seasonal historical baseline or “insufficient verified data” state. This is more interpretable than an unvalidated ML value, but any live product change requires approval.

### Decision

The existing deployed model cannot be safely “corrected” by changing its inputs alone. If definitions cannot be recovered, a scientifically defensible result requires a new verified dataset and retraining; the current model must not be silently repurposed.

## 8. Handling current prediction and Heat Map risks

### Unvalidated yield estimates

The existing `Unvalidated estimate` and `Accuracy not verified` safeguards should remain. The decision to suppress numeric values, replace them with an unavailable state, or show a non-ML baseline requires product-owner and adviser approval.

### Confidence scores

Heuristic confidence values from decision support, AI intent handling, or weather labels are not calibrated rice-yield accuracy probabilities. They must not be displayed as evidence that the rice-yield model is correct. A separate calibration study is required before any percentage is called model confidence.

### Heat Map

The operational risk formula is intentionally unchanged. Before research use:

- identify whether the yield component reads demo or derived records;
- report the provenance mix alongside any risk output;
- obtain MAO approval for exclusions or recalculation;
- keep historical runs immutable and distinguish operational output from validated research output.

## 9. Researcher / MAO / adviser approval checklist

Each item requires a named approver, date, evidence reference, and decision. Blank fields mean no approval has been granted.

| Decision | Required approver | Evidence/reference | Decision | Name/signature | Date |
|---|---|---|---|---|---|
| Original MAO file is authentic | MAO custodian + adviser |  | ☐ Approve ☐ Reject |  |  |
| Meaning of `month` is confirmed | MAO + researcher |  | ☐ Planting ☐ Harvest ☐ Other |  |  |
| Independent observation unit selected | Adviser/statistical reviewer |  | ☐ Farm ☐ Barangay ☐ Month ☐ Season |  |  |
| Target yield definition approved | Agriculture researcher |  | ☐ t/ha ☐ Other |  |  |
| Crop duration and weather window approved | MAO + agriculture researcher |  |  |  |  |
| Ambulong accepted as labelled proxy | Adviser + climate authority |  | ☐ Yes ☐ No |  |  |
| All 12 feature definitions and units confirmed | Original model researchers |  | ☐ Confirmed ☐ Replace schema |  |  |
| Deployed model scale confirmed | Original model researchers |  | ☐ Farm ☐ Barangay ☐ Municipal ☐ Unknown |  |  |
| Minimum independent seasons set | Adviser/statistical reviewer |  |  |  |  |
| Grouped/chronological validation method approved | Adviser/statistical reviewer |  |  |  |  |
| Baselines approved | Adviser/statistical reviewer |  |  |  |  |
| Current numeric farm estimates suppressed or labelled | Product owner + adviser |  | ☐ Label ☐ Suppress ☐ Baseline |  |  |
| Demo/derived Heat Map policy approved | MAO + product owner |  | ☐ Exclude ☐ Retain with provenance |  |  |
| Retraining/replacement gate approved | Adviser + technical owner |  |  |  |  |

## 10. Prioritized implementation plan

### Priority 0 — Safe immediately, no methodology approval

- Preserve the current unvalidated-estimate labels and historical records.
- Keep deployed and candidate artifacts separate and read-only.
- Add read-only provenance/checksum inventories for future source deliveries.
- Add isolated validators for feature names, units, ranges, freshness, season membership, duplicates, and future leakage.
- Make every future metric report state independent sample size, grouping, baseline, climate source, and dataset checksum.

### Priority 1 — Requires evidence delivery, not model activation

- Obtain and checksum the original training dataset and source documentation.
- Obtain the preprocessing script/notebook and original validation report.
- Obtain MAO confirmation of the 60-row file and its timing semantics.
- Build a frozen provenance-controlled research dataset outside active production tables.

### Priority 2 — Requires adviser/MAO approval

- Approve independent unit, target definition, crop/weather window, season calendar, climate proxy policy, and minimum sample size.
- Decide whether current farm-specific values remain visible or are suppressed.
- Decide whether demo/derived records are excluded from operational Heat Map inputs.

### Priority 3 — Research implementation

- Reproduce the deployed model if artifacts are recovered.
- Otherwise specify and train a new model only after approval.
- Run grouped/chronological validation against baselines.
- Publish an evaluation report with bias, MAE, RMSE, R², sample counts, and limitations.

### Priority 4 — Deployment gate

- Obtain adviser sign-off on accuracy and scope.
- Confirm model version, feature schema, data checksum, and rollback artifact.
- Activate only after product and research owners approve the interpretation and UI wording.

## 11. Acceptance criteria for scientifically defensible predictions

All criteria must be satisfied before a prediction is described as scientifically validated:

1. Original or newly approved training data has documented provenance and stable observation IDs.
2. The target and independent observation unit are approved by MAO/researchers/adviser.
3. All 12 feature definitions, units, spatial scale, aggregation windows, and missing-value rules are confirmed.
4. Training and test observations are independent; demo, derived duplicates, and same-season leakage are excluded.
5. Climate records are matched to the approved crop window and spatial source, with Ambulong limitations disclosed if used.
6. Evaluation is chronological or season-grouped and includes at least one information-available simple baseline.
7. MAE, RMSE, R², bias, row count, independent-season count, and uncertainty limitations are reported.
8. The model demonstrates useful performance against the approved baseline on independent data; a positive training score alone is insufficient.
9. Farm-level claims are made only if farm-level training and validation evidence exists.
10. UI labels, confidence wording, Heat Map provenance, model version, and rollback behavior are approved.
11. Adviser and technical-owner sign-off is recorded before retraining, replacement, or activation.

## Final readiness statement

The safe current position is to keep iClimate technically functioning while treating rice-yield model accuracy as unverified. The next required action is evidence recovery and methodology approval, not an input tweak or another training run. Until the approval checklist and acceptance criteria are satisfied, current farm-specific yield values must remain labelled as unvalidated estimates and must not be used as proof of scientific accuracy.
