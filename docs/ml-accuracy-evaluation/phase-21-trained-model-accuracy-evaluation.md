# Phase 21 — Trained ML Model Accuracy Evaluation

**Project:** iClimate  
**Evaluation date:** 13 November 2026  
**Scope:** Read-only inspection and evaluation preparation for the existing deployed rice-yield model.

## Executive result

The existing trained model can be identified and loaded, but its actual predictive accuracy cannot be measured scientifically from the repository. The original training dataset, preprocessing/training package, original train/test split, and verified independent yield observations for the deployed 12-feature schema are absent.

Therefore:

> **ACCURACY NOT VERIFIED**

No deployed-model R², MAE, RMSE, MAPE, error distribution, or actual-versus-predicted chart is claimed by this report. No model, prediction formula, database record, or production service was changed.

## 1. Deployed model identity

| Property | Observed value |
|---|---|
| Artifact | `storage/models/rice_yield_model_final.pkl` |
| SHA-256 | `f3a54e12ecf85e66340475e2c49ce5c15d9c58efd82213435015a275734b777c` |
| Loaded type | scikit-learn `Pipeline` |
| Estimator | `RandomForestRegressor` |
| Trees | 300 |
| Max depth | 10 |
| Max features | `sqrt` |
| Minimum split size | 10 |
| Minimum leaf size | 1 |
| Random state | 42 |
| Preprocessing | `ColumnTransformer`; `Season` one-hot encoded; 11 numeric fields passed through |
| Target | Not recoverable from the artifact alone; production documentation describes rice yield, but the original target definition/unit is not authenticated |

The artifact loads successfully in the project ML virtual environment. Its `feature_names_in_` contains exactly 12 features in this order:

```text
RAINFALL
TEMP_AVG
TEMP_RANGE
Area
Previous_Rainfall
Previous_Temp
Rainfall_6M
Temp_3M
Temp_6M
Seasonal_Rainfall
Seasonal_Temp
Season
```

The checksum was read only and was not changed.

## 2. Training and preprocessing evidence

### Recovered

- The production artifact and its 12 input names/order.
- The serialized preprocessing structure and Random Forest hyperparameters.
- The current production feature builder in `PredictionEngine` and the Python `rice_features()` contract.
- Existing parity and input-validation tests.

### Missing

- Original training dataset and stable observation identifiers.
- Original training script/notebook for `rice_yield_model_final.pkl`.
- Authoritative feature dictionary with units, spatial scale, aggregation windows, transformations, and missing-value rules.
- Original target column definition and unit.
- Original train/test or cross-validation split.
- Original preprocessing environment/version lock and model-construction history.
- Independent test observations not used for training, tuning, feature construction, or derived allocation.

The repository’s `train_rice_yield_model.py` trains a different 7-feature pipeline and explicitly warns that its smoke-test path must not replace the deployed model. The available candidate artifacts use a different 10-feature schema and are not evidence for the deployed artifact.

## 3. Available datasets and admissibility

| Dataset/artifact | Shape or content | Can evaluate deployed model? | Reason |
|---|---|---:|---|
| `storage/datasets/iClimate_Modeling_Ready_TimeAware.csv` | 60 monthly rows; 10 candidate features; `AVERAGE_YIELD` | No | Different schema and prior audit did not establish authentic independent provenance |
| `storage/datasets/generated/rice_yield_backtest.csv` | Candidate actual/predicted pairs | No | Candidate model, derived backtest, includes training rows; not deployed-model evidence |
| `database/seeders/data/mao-planting-summary-2020-2025.csv` | MAO-shaped monthly summary | No | Seeder/source authenticity and independent-observation status are not approved |
| `database/data/Ambulong Monthly Data.csv` | Climate observations only | No | No matched verified yield target; station-to-Lian applicability remains unapproved |
| `storage/datasets/candidate_2025_full_predictions.csv` | Candidate predictions and selected actuals | No | Candidate schema/model and not an approved deployed evaluation set |
| `storage/datasets/candidate_2026_full_predictions.csv` | Historical-weather predictions without actual yields | No | Prediction-only rows, no target values |
| Production prediction rows/backups | Operational estimates and metadata | No | Not independent observations and may be derived/demo data |

No repository file provides an authenticated set of actual yield observations paired with all twelve deployed features and an approved independent split.

## 4. Evaluation methodology that was possible

The existing safe evaluation infrastructure supports chronological or season-grouped evaluation, duplicate and leakage checks, baseline comparisons, and MAE/RMSE/R²/bias calculations. It remains blocked until a researcher-approved snapshot is supplied.

The required future procedure is:

1. Freeze the unchanged deployed artifact and record its checksum.
2. Receive a source-backed evaluation snapshot with stable observation IDs, target units, dates, season, geography, and provenance.
3. Reconstruct every deployed feature from the supplied source data using the authenticated training definitions.
4. Reject rows that are demo, derived, duplicated, replicated, training, tuning, future-leaking, or outside the approved target scope.
5. Freeze a chronological or season-grouped test split before running inference.
6. Run the unchanged artifact in an isolated environment only.
7. Report R², MAE, RMSE, MAPE only when appropriate for nonzero targets, signed bias, error distribution, sample count, group count, and baseline comparisons.
8. Preserve the raw actual/predicted pairs, checksums, code version, limitations, and approval record.

No step above was completed for the deployed model because the required evidence is missing.

## 5. Candidate-only metrics reproduced

The read-only command `python_scripts/retrain_yield_season.py --check` reproduced the existing report for a different 10-feature candidate Random Forest using 2020–2024 training rows and an eight-row 2025 holdout:

| Candidate result | MAE | RMSE | R² | Status |
|---|---:|---:|---:|---|
| Original candidate season rule | 0.3944 | 0.4690 | -1.1043 | Candidate-only; not deployed |
| October-as-wet candidate variant | 0.3847 | 0.4584 | -1.0105 | Candidate-only; not deployed |
| Leave-one-year-out, October-as-wet | 0.5137 | 0.7155 | -0.1745 | Candidate-only; not deployed |

These values are included solely to distinguish reproducible candidate evidence from deployed-model evaluation. They do not validate the production artifact, and they are not presented as iClimate production accuracy. No MAPE is reported because this run was not an approved deployed evaluation and the repository does not provide an authenticated target package for that purpose.

## 6. Leakage, overfitting, and mismatch assessment

### Deployed model

- **Leakage:** cannot be assessed because the original split and rows are missing.
- **Overfitting:** cannot be assessed from an independent test set. The serialized model alone is insufficient.
- **Preprocessing consistency:** the serialized preprocessing order is known, but the original feature construction semantics are not.
- **Geographic mismatch:** production `Area` is farm-oriented while the training observation scale is unknown; the mismatch is unresolved.
- **Season mismatch:** production preserves the deployed model’s trained-season helper, but the original calendar is not documented by the training package.
- **Climate-history mismatch:** production rolling/lag features may be stale or unavailable; the original training coverage and windows are unknown.

### Candidate model

The candidate’s negative holdout R² indicates that this candidate performed worse than the test-set mean relationship on its reported holdout. That is evidence about the candidate dataset/recipe only, not the deployed model. The candidate dataset is also not accepted independent research evidence.

## 7. Actual-versus-predicted results and charts

There are no scientifically admissible deployed-model actual/predicted pairs in the repository. Consequently, no actual-versus-predicted scatter plot, residual/error-distribution chart, or deployed-model metric table was generated; creating one from candidate, seeded, derived, or prediction-only rows would be misleading.

When approved data arrives, the chart package must include:

- actual versus predicted yield with a 1:1 reference line;
- residual/error distribution with sample count;
- error by season and approved geography/group;
- a table of row IDs, actuals, predictions, signed errors, and units;
- baseline comparison and limitations.

## 8. Exact external evidence required

1. Original developer’s deployed-model training dataset with stable IDs, checksum, target, units, and source provenance.
2. Original training/preprocessing scripts or a reproducible signed technical specification.
3. Complete 12-feature dictionary, including `Area`, `Rainfall_6M`, `TEMP_RANGE`, season encoding, lag/rolling windows, units, spatial level, and missing-value rules.
4. Original train/test methodology and held-out observation identities.
5. Verified MAO production, planting, harvest, area, crop, season, and yield records with source documents and correction history.
6. Historical climate data with source metadata, dates, units, coverage, missingness, and approved Lian/Ambulong applicability.
7. Adviser/researcher approval for target scope, independence rules, minimum groups/samples, chronological or season-grouped split, and baselines.

## 9. Integrity and protection checks

- Existing production model checksum recorded above; no model file was written.
- No retraining, replacement, activation, prediction formula change, database write, or service restart was performed.
- Candidate reproduction used `--check`, which does not write the candidate model/report.
- Successful API inference was not treated as accuracy evidence.
- Scientific status remains **ACCURACY NOT VERIFIED**.

## Final assessment

Phase 21 identified the exact deployed algorithm and schema and reproduced the already documented candidate-only holdout evidence. It could not honestly produce deployed-model accuracy metrics because authentic compatible evaluation data and original training evidence remain missing. The next action is authenticated research-package intake, followed by an approved isolated evaluation—not a model change.
