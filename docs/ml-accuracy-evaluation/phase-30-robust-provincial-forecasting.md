# Phase 30 — Provincial Rice-Yield Forecasting Optimization and Robustness Testing

Date: 2026-11-14  
Status: `NO CONSISTENT COMPLEX-MODEL SUPERIORITY ESTABLISHED`  
Deployed model: `ACCURACY NOT VERIFIED`

## 1. Scope and leakage controls

This experiment uses only authentic PSA Batangas quarterly aggregate yields
derived from production and harvested area. It is not a Lian farm model and
does not modify the deployed 12-feature climate model.

The outer selection period is 2015–2020, entirely before the previously
examined 2021–2025 and 2026 Q1–Q3 diagnostics. Each outer year is forecast
quarter-by-quarter using only rows earlier than the target date. Candidate
parameters are selected by inner chronological folds contained within each
outer training history. Neither 2021–2025 nor 2026 outcomes were used for
selection.

## 2. Methods compared

- Seasonal naive: latest observed same-quarter yield.
- Weighted historical seasonal average.
- Exponential seasonal smoothing.
- Regularized Ridge regression using calendar and lagged historical features.
- Random Forest.
- Gradient Boosting.
- Equal-purpose ML/seasonal ensemble with the ML blend selected inside the
  chronological training history.

Lagged predictors are previous-quarter yield, previous-year same-quarter yield,
shifted rolling means, shifted expanding mean, historical trend, year, and
quarter. No current or future target or harvested-area value is used.

## 3. Nested walk-forward results

Metrics are averages across six outer years and 24 quarterly forecasts.

| Method | Mean RMSE | Mean MAE | Mean MAPE | Mean bias | Years with lowest RMSE |
|---|---:|---:|---:|---:|---:|
| **ML + Seasonal Naive Ensemble** | **0.4602** | 0.3896 | 13.44% | -0.0145 | 1/6 |
| Seasonal Naive | 0.4658 | 0.4049 | 13.77% | -0.0017 | **2/6** |
| Regularized Ridge | 0.4685 | 0.4112 | 14.15% | 0.0196 | 1/6 |
| Random Forest | 0.4718 | **0.3791** | **12.99%** | 0.0015 | 0/6 |
| Weighted Seasonal Average | 0.4815 | 0.4069 | 14.39% | 0.1051 | 1/6 |
| Gradient Boosting | 0.5012 | 0.4273 | 14.51% | -0.0281 | 1/6 |
| Exponential Seasonal Smoothing | 0.5056 | 0.4276 | 14.64% | 0.0215 | 0/6 |

The frozen selection rule chose the ensemble because it had the lowest average
RMSE. Its advantage over seasonal naive is only 0.0056 t/ha, approximately
1.2%, and it did not win consistently across years. Seasonal naive remains the
preferred conservative fallback until future evidence confirms the small
ensemble difference.

The six outer-year winners were: Ridge (2015), seasonal naive (2016), weighted
seasonal average (2017), ensemble (2018), Gradient Boosting (2019), and
seasonal naive (2020). This spread demonstrates model-selection instability.

## 4. Previously examined diagnostics

These periods were not used for Phase 30 selection.

The selected ensemble’s diagnostic performance was:

| Period | MAE | RMSE | MAPE | Bias |
|---|---:|---:|---:|---:|
| 2021–2025 | 0.2701 | 0.3588 | 8.92% | -0.0310 |
| 2026 Q1–Q3 | 0.3106 | 0.4585 | 8.22% | -0.2479 |

The 2026 results are only three province-level observations and remain
descriptive. They cannot prove generalization or deployment suitability.

## 5. Interpretation

The previous-year seasonal baseline is strong because Batangas provincial yield
has recurring quarterly structure and recent seasonal history captures much of
the signal available without verified climate inputs. More complex models add
variance and are sensitive to distribution shifts, especially abrupt changes in
2020 and 2024–2026.

No approach consistently outperformed seasonal naive across the outer years.
The correct technical conclusion is not that machine learning is more
accurate, but that a simple seasonal baseline is difficult to beat with the
available province-level target history alone.

## 6. Artifacts and tests

Artifacts are under `storage/models/experiments/phase-30/`:

- `nested_walk_forward_predictions.csv`
- `selected_forecaster_diagnostic_predictions.csv`
- `selected_forecaster_2021_2026_diagnostics.svg`
- `selected_forecaster.json`
- `metrics.json`

The selected forecaster configuration is preserved for future independent
testing. No production model was activated.

Verification:

- Phase 30 targeted tests: 1 passed.
- Full Python suite: 50 passed, 2 existing dependency deprecation warnings.
- `git diff --check`: passed.
- Production model checksum unchanged: `f3a54e12ecf85e66340475e2c49ce5c15d9c58efd82213435015a275734b777c`.

## 7. Future independent evaluation

Keep the selected configuration frozen and evaluate it on genuinely future PSA
quarters after 2026 Q3. Do not tune on those rows. Prefer several complete
future seasons, preserve PSA revisions and publication dates, and continue to
distinguish provincial aggregates from Lian farm-level observations.

The deployed model remains `ACCURACY NOT VERIFIED`; no provincial experimental
model is ready for Lian deployment.
