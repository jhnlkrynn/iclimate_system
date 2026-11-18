# Phase 29 — Independent 2026 Accuracy Evaluation

Date: 2026-11-14  
Status: `EVALUATED; SAMPLE TOO SMALL FOR RELIABLE GENERALIZATION CLAIM`  
Deployed model: `ACCURACY NOT VERIFIED`

## 1. Frozen protocol

The protocol was frozen before reading any 2026 target values in
`storage/models/experiments/phase-29/protocol-freeze.json`.

- Forecast origin: end of 2025 Q4.
- Model selection: Phase 28 pre-2021 validation only.
- No 2026 tuning, model selection, or hyperparameter changes.
- Historical training refit: authentic PSA observations through 2025.
- Evaluation: 2026 Q1–Q3 only.
- Lagged features use only PSA records through 2025 Q4. No 2026 target was
  used to construct Q2 or Q3 features.
- Metrics: MAE, RMSE, bias, MAPE; R² retained only as descriptive context.

The frozen lagged Random Forest used the Phase 28 selected parameters:
`n_estimators=200`, `max_depth=5`, `min_samples_leaf=4`, and
`max_features=1.0`. The frozen calendar-only Random Forest used
`n_estimators=200`, `max_depth=10`, `min_samples_leaf=2`, and
`max_features=1.0`. Baselines were previous-quarter naive, previous-year
same-quarter naive, and historical seasonal mean.

## 2. Official 2026 evidence

The PSA OpenSTAT production table is measured in metric tons, has provincial
disaggregation, and identifies Q3 2026 as preliminary. The area-harvested table
is measured in hectares and identifies Q2 2026 as preliminary. PSA defines area
harvested as the effective area from which harvests were realized. Sources:
[production table](https://openstat.psa.gov.ph/PXWeb/pxweb/en/DB/DB__2E__CS/0012E4EVCP0.px/)
and [area table](https://openstat.psa.gov.ph/PXWeb/pxweb/en/DB/DB__2E__CS/0022E4EAHC0.px/).

Raw files and checksums are preserved in
`storage/datasets/external/phase-29/`; see its [manifest](</Users/johnlukereynon/iclimate_system/storage/datasets/external/phase-29/manifest.json>).

Validation found three matching periods, zero missing values, zero duplicate
periods, compatible units, and Batangas/Palay classification in both files.
The target is an authentic but province-level derived yield, not a Lian farm
observation.

## 3. 2026 results

| Model | R² (descriptive) | MAE (t/ha) | RMSE (t/ha) | Bias (t/ha) | MAPE |
|---|---:|---:|---:|---:|---:|
| Lagged Random Forest | -0.8569 | 0.3584 | 0.4917 | -0.3516 | 9.39% |
| Calendar-only Random Forest | -0.8466 | 0.4308 | 0.4904 | -0.1860 | 12.07% |
| Previous-quarter naive | -6.0995 | 0.8912 | 0.9615 | -0.8912 | 24.59% |
| Previous-year same-quarter naive | -0.0867 | **0.2981** | **0.3762** | -0.1135 | **8.00%** |
| Seasonal historical mean | -0.4870 | 0.4152 | 0.4400 | -0.4152 | 11.81% |

The previous-year same-quarter naive baseline outperformed both frozen Random
Forest candidates on this three-quarter sample. This is not evidence that the
baseline is generally superior; it is a small diagnostic result.

## 4. Individual errors

| Period | Actual t/ha | Lagged RF prediction | Lagged RF error | Calendar RF prediction | Calendar RF error | Previous-year naive | Naive error |
|---|---:|---:|---:|---:|---:|---:|---:|
| 2026 Q1 | 3.9075 | 3.0938 | -0.8137 | 3.1633 | -0.7442 | 3.2900 | -0.6175 |
| 2026 Q2 | 3.5792 | 3.3280 | -0.2512 | 3.3982 | -0.1810 | 3.7670 | +0.1878 |
| 2026 Q3 | 3.0326 | 3.0428 | +0.0102 | 3.3998 | +0.3672 | 3.1217 | +0.0891 |

All frozen Random Forest estimates underpredicted the aggregate target in Q1
and Q2. The Q3 production and Q2 area inputs are preliminary according to PSA,
so the figures may be revised.

## 5. Interpretation

The evaluation is genuinely post-2025 evidence and was not used for model
selection. Nevertheless, three observations are insufficient to establish
reliable generalization, stable bias, or meaningful R². The frozen lagged model
did not demonstrate consistent improvement over the previous-year seasonal
baseline on this sample.

The result also does not evaluate the deployed 12-feature climate model. It
uses province-level PSA targets and historical yield/calendar features only;
there are still no authenticated crop-season PAGASA observations or verified
definitions for the deployed model’s 12 inputs.

## 6. Artifacts

Under `storage/models/experiments/phase-29/`:

- `protocol-freeze.json`
- `2026_q1_q3_predictions_and_errors.csv`
- `2026_q1_q3_actual_vs_predicted.svg`
- `frozen_lagged_random_forest.joblib`
- `frozen_calendar_random_forest.joblib`
- `metrics.json`

## 7. Recommendation

Do not deploy either experimental model to Lian. Obtain additional unseen
quarters and preferably multiple future years, with final PSA revisions,
matching geographic scope, and publication timestamps. A minimum practical
confirmation set should cover several complete seasons rather than three rows.
Keep the 2026 Q1–Q3 observations as a locked diagnostic set for future protocol
reviews, not as a tuning set.

The deployed model remains `ACCURACY NOT VERIFIED`.
