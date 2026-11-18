# Phase 28 — Experimental ML Accuracy Improvement and Time-Series Optimization

Date: 2026-11-14  
Status: `EXPERIMENTAL RESULTS AVAILABLE; NO DEPLOYMENT DECISION`  
Deployed model status: `ACCURACY NOT VERIFIED`

## Objective and data boundary

This phase reuses only the authentic PSA Batangas quarterly production and
harvested-area records acquired in Phase 27. It adds no observations, climate
normals, predictions, or application records. The target is the derived
province-level aggregate `production metric tons / harvested hectares`.

This is not the deployed 12-feature model and cannot establish Lian farm-level
accuracy.

## Historical features

The separate experiment uses only predictors available before each quarterly
forecast:

- `lag_1_yield_t_ha`: previous observed quarter yield
- `lag_4_yield_t_ha`: previous year's same-quarter yield
- `rolling_4_mean_prior`: previous four-quarter mean
- `rolling_8_mean_prior`: previous eight-quarter mean
- `expanding_mean_prior`: shifted historical mean
- `trend_4_prior`: previous-quarter yield minus previous-year same-quarter yield
- calendar `year` and `quarter`

Every target-derived feature is shifted before rolling or expanding. Current or
future production and harvested area are never predictors. The implementation
is `python_scripts/phase_28_timeseries_optimization.py`.

The one-quarter shift is conservative; final use requires PSA publication
timestamps confirming prior-quarter availability. Eight initial rows lacked a
complete rolling history, leaving **148 usable observations**.

## Protocol

| Partition | Years | Rows | Use |
|---|---:|---:|---|
| Hyperparameter tuning | 1987–2016 after warm-up | 112 | RandomizedSearchCV with TimeSeriesSplit |
| Walk-forward validation | 2017–2020 | 16 | Candidate selection only |
| Historical diagnostic | 2021–2025 | 20 | Reused evidence; not selection |

The 2021–2025 period was not used to select parameters or algorithms.

## Validation-selected results

| Algorithm | CV RMSE | Validation R² | Validation MAE | Validation RMSE | Validation MAPE |
|---|---:|---:|---:|---:|---:|
| **Random Forest** | **0.4868** | -0.3751 | 0.3669 | **0.4567** | 11.90% |
| Extra Trees | 0.5351 | -0.4754 | 0.3609 | 0.4731 | 11.78% |
| Gradient Boosting | 0.5270 | -0.5973 | 0.3967 | 0.4922 | 12.61% |
| HistGradientBoosting | 0.5891 | -0.6986 | 0.4149 | 0.5076 | 13.12% |

Random Forest is the validation-selected candidate. Its fold RMSE values were
0.5368, 0.4242, 0.2775, and 0.5379 for 2017–2020. This instability and the
mostly negative fold R² values show that the apparent improvement is not stable
across time.

## Baselines and comparison with Phase 27

| Baseline | Validation RMSE | Validation R² | Diagnostic RMSE | Diagnostic R² |
|---|---:|---:|---:|---:|
| Previous-quarter naive | 0.5631 | -1.0900 | 0.9371 | -4.3790 |
| Previous-year same-quarter naive | 0.9561 | -5.0256 | 0.4082 | -0.0205 |
| Seasonal historical mean | 0.5967 | -1.3471 | 0.5096 | -0.5903 |

Lagged Random Forest improves pre-2021 validation RMSE over the calendar-only
Phase 28 Random Forest benchmark (0.5357) by approximately 14.7%. The
improvement does not persist on the reused 2021–2025 diagnostic period:

| Model | Diagnostic R² | MAE | RMSE | MAPE | Bias |
|---|---:|---:|---:|---:|---:|
| Lagged Random Forest | 0.2054 | 0.2756 | 0.3602 | 9.24% | -0.0051 |
| Calendar-only Random Forest | 0.4171 | 0.2567 | 0.3085 | 8.60% | -0.0275 |

Phase 27’s selected calendar-only HistGradientBoosting diagnostic was R²
-0.1290, MAE 0.3509, RMSE 0.4293, and MAPE 11.49%. Phase 28 is numerically
better on that same historical period, but this is not a new independent test.

## Feature importance and error analysis

Permutation importance was calculated using the 2020 validation fold only:

| Feature | Mean importance |
|---|---:|
| `lag_4_yield_t_ha` | 0.0562 |
| `trend_4_prior` | 0.0342 |
| `lag_1_yield_t_ha` | 0.0279 |
| `rolling_8_mean_prior` | 0.0033 |
| `rolling_4_mean_prior` | -0.0020 |
| `year` | 0.0000 |
| `expanding_mean_prior` | 0.0000 |
| `quarter` | -0.0328 |

This is based on only four validation observations, so it is unstable evidence,
not causal agricultural interpretation. The main reproducible finding is
temporal drift: lagged features help 2017–2020 validation but underperform the
calendar-only benchmark on 2021–2025 diagnostics.

## Artifacts and verification

Artifacts are isolated under `storage/models/experiments/phase-28/`:

- `best_lagged_candidate.joblib`
- `best_calendar_candidate.joblib`
- `lagged_provincial_dataset.csv`
- `metrics.json`
- `walk-forward-diagnostic-comparison.svg`

Tests:

- Phase 28 targeted tests: 2 passed.
- Full Python suite: 49 passed, 2 existing dependency deprecation warnings.
- `git diff --check`: passed.
- Production model checksum unchanged: `f3a54e12ecf85e66340475e2c49ce5c15d9c58efd82213435015a275734b777c`.

## Decision and next evidence

Lagged historical features improve pre-2021 validation relative to calendar-only
features, but the gain is not consistent on the reused diagnostic period. This
does not justify deployment or a claim of improved Lian accuracy.

Final confirmation requires genuinely unseen PSA/MAO observations after 2025,
with verified release dates and matching geographic/target definitions. If
climate predictors are to be evaluated, approved PAGASA historical observations
must also be joined to the same crop seasons. Those future rows must remain
untouched until the final model and protocol are fixed.

The deployed model remains `ACCURACY NOT VERIFIED`.
