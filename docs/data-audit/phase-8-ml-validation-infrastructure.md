# iClimate Phase 8 — Safe ML Validation Infrastructure

**Project:** iClimate  
**Assessment date:** 2026-11-13  
**Scope:** Safe technical preparations from the Phase 7 `READY` category only.

## 1. Implemented safe improvements

Added an isolated, read-only Python validation module at `python_scripts/ml_validation.py`.

It provides:

- Exact deployed 12-feature name/order validation.
- Missing, non-numeric, non-finite, unsupported-season, and optional approved-range diagnostics.
- Unit declaration comparison without converting or silently changing values.
- Climate-history freshness checks for missing previous/rolling months.
- Provenance classification for verified, unverified, derived, demo, and model-output rows.
- Duplicate observation/source identity detection.
- Train/test group and observation leakage detection.
- MAE, RMSE, R², bias, baseline comparison, sample count, and independent-group count results.
- Explicit `ACCURACY NOT VERIFIED` results when evidence is empty or invalid.
- Read-only model artifact checksum/metadata inspection. Artifact loading is opt-in because pickle loading is executable.
- In-memory model metadata generation with activation explicitly set to `False`.

These utilities do not write files, alter active database records, transform model inputs, retrain models, replace artifacts, or activate model versions.

## 2. Files created or modified

Created:

- `python_scripts/ml_validation.py` — isolated validation primitives and metadata schemas.
- `python_scripts/tests/test_ml_validation.py` — fixtures covering feature compatibility, missing values, units, freshness, provenance, duplicates, leakage, baselines, artifact metadata, and non-activation.
- `docs/data-audit/phase-8-ml-validation-infrastructure.md` — this implementation report.

No existing production prediction or risk service was modified. Existing Phase 3–7 safeguards were preserved.

## 3. Model compatibility diagnostics

The validation module confirms the deployed contract remains exactly:

`RAINFALL`, `TEMP_AVG`, `TEMP_RANGE`, `Area`, `Previous_Rainfall`, `Previous_Temp`, `Rainfall_6M`, `Temp_3M`, `Temp_6M`, `Seasonal_Rainfall`, `Seasonal_Temp`, `Season`.

The checks now fail diagnostically for:

- missing or reordered features;
- duplicate feature names;
- missing, non-numeric, or non-finite values;
- unsupported season categories;
- values outside explicitly supplied approved ranges;
- missing unit declarations or unit mismatches.

No training ranges or transformations are assumed by default. A range warning is only produced when an approved range is passed to the validator.

The existing production calculations remain unchanged. The known research blockers remain:

- `Area` training scale is unknown and appears materially different from current farm sizes.
- `Rainfall_6M` production input is a six-month sum while the inferred training range suggests a possible average; this is unconfirmed.
- `TEMP_RANGE` production input is derived from monthly average temperatures while the inferred training meaning may be max-minus-min; this is unconfirmed.
- Previous and rolling climate inputs require freshness and source approval.
- Season categories are technically supported, but the training calendar requires researcher confirmation.

## 4. Data provenance validation results

The new isolated fixtures demonstrate that the validator:

- separates verified, unverified, derived, demo, and model-output records;
- detects duplicate observation identities;
- detects a season/group appearing in both train and test;
- detects the same observation appearing in both train and test;
- reports missing and stale historical climate months without filling them;
- preserves the original supplied values and does not fabricate replacements.

The validator does not reclassify active records. Existing project evidence remains unchanged:

- 60 MAO-shaped monthly rows representing 11 inferred municipal seasons;
- 110 derived allocations;
- 14 demo rice-production rows;
- demo farmer harvest records;
- no confirmed independent Lian climate series;
- Ambulong station data available only as a labelled Tanauan proxy.

## 5. Regression test results

### New and related Python ML tests

- **25 passed**
- **0 failed**
- **2 warnings** (third-party deprecation warnings from Starlette/httpx)

Command:

```text
.venv-ml/bin/python -m pytest python_scripts/tests -q
```

### Targeted Laravel regression tests

- **51 passed**
- **0 failed**
- **531 assertions**

Covered prediction parity, prediction-engine climate plausibility, prediction pages, Heat Map yield/data flow, and report/Heat Map notifications.

### Complete Laravel suite

- **1,784 passed**
- **0 failed**
- **5 skipped**
- **13,821 assertions**

The suite continues to pass after adding the isolated validation infrastructure.

`git diff --check` passed.

## 6. Model and active-data integrity

Read-only status checks found no changes under:

- `storage/models`
- `storage/datasets`
- `database/data`

No active model was switched, retrained, replaced, or activated. No active database records, predictions, historical analytics, production formulas, or Heat Map calculations were modified. Healthy services were not restarted.

The code graph was refreshed after the Python validation additions.

## 7. Remaining research approval blockers

The following remain blocked or approval-dependent:

1. Original deployed training dataset, preprocessing script, and feature dictionary are still missing.
2. Farm versus municipal model scale is unresolved.
3. Exact definitions and units for all 12 deployed features are not confirmed.
4. MAO source authenticity and the meaning of the monthly `month` field are unresolved.
5. Independent observation unit, crop-duration window, and climate-matching window require approval.
6. Ambulong's acceptability as a labelled Lian proxy requires approval.
7. Minimum independent sample size and validation protocol require adviser/statistical approval.
8. Suppression versus labelling of current numeric farm estimates remains a product/research decision.
9. Demo/derived-record treatment in operational Heat Map risk calculations remains an MAO/product decision.
10. No deployed rice-yield accuracy claim is supported: **ACCURACY NOT VERIFIED**.

## 8. Phase 9 recommendations

### READY — safe technical preparation

- Add a read-only command or report wrapper around these validators if a stable research-data intake location is approved.
- Add fixture schemas for source checksums, observation IDs, season groups, units, and climate windows.
- Add a report serializer that records dataset checksum, schema version, split groups, baseline, and diagnostics.
- Add PHP/Python parity fixtures that compare serialized feature names and order without changing production input construction.

### BLOCKED — requires evidence

- Reproduce the deployed model only after the original training package is obtained.
- Build a frozen research dataset only after MAO source confirmation and provenance approval.
- Produce scientific accuracy metrics only after independent observations and a pre-approved split exist.

### PENDING APPROVAL

- Correct `Area`, `Rainfall_6M`, `TEMP_RANGE`, rolling windows, seasonal windows, or season boundaries.
- Suppress or replace current farm-specific numeric outputs.
- Exclude demo/derived records from operational Heat Map calculations.
- Define candidate-model promotion thresholds.

### NOT AUTHORIZED

- Retraining, replacing, or activating a model.
- Modifying active database records, stored predictions, historical analytics, prediction formulas, or risk calculations.

## Final assessment

Phase 8 successfully establishes a non-mutating validation foundation. iClimate remains technically operational and regression-stable, but the deployed rice-yield model remains scientifically unverified. The next safe step is to use the new validators against an adviser-approved, provenance-controlled research snapshot; the next scientific step is recovery and approval of the missing training definitions and independent observations.
