# Rice Yield ML Model Accuracy Verification & Audit

**Date:** 2026-11-20  
**Branch:** `feature/2026-11-20-yield-monitoring-security`  
**Deployed Artifact:** `storage/models/rice_yield_model_final.pkl` (`sha256: f3a54e12ecf85e66340475e2c49ce5c15d9c58efd82213435015a275734b777c`)  
**Production Gate:** `ICLIMATE_YIELD_GATE=withhold` (`ICLIMATE_YIELD_ACCURACY_VERIFIED=false`)

---

## 1. Accuracy Verdict

**Accuracy Percentage: NOT YET MEASURABLE — Insufficient Verified Real Harvest Data.**

No legitimate, independently verified farm-level harvest dataset exists in the database or repository to measure real-world farm-level prediction accuracy (`±10%`, `±20%`, MAE, RMSE, R², or MAPE).

---

## 2. Model Inspection & Feature Importance

`rice_yield_model_final.pkl` is a `sklearn.pipeline.Pipeline` (`preprocessor` + `RandomForestRegressor` with `n_estimators=300`, `max_depth=10`, `min_samples_split=10`, `min_samples_leaf=1`, `max_features='sqrt'`, `random_state=42`, `oob_score=False`). Its original training dataset and script are not present in the repository (`python_scripts/model_management.py`), and `ml_models` row 1 records `mae=null`, `rmse=null`, `r2=null`.

**12 Features & Gini Importances:**
1. `Season` (`OneHotEncoder(['Dry', 'Wet'])`): `0.2025`
2. `Seasonal_Temp`: `0.1004`
3. `Rainfall_6M`: `0.0864`
4. `Seasonal_Rainfall`: `0.0841`
5. `Area`: `0.0811`
6. `Temp_3M`: `0.0704`
7. `Temp_6M`: `0.0660`
8. `Previous_Rainfall`: `0.0549`
9. `Previous_Temp`: `0.0517`
10. `TEMP_RANGE`: `0.0464`
11. `RAINFALL`: `0.0160`
12. `TEMP_AVG`: `0.0065`

---

## 3. Data Provenance Audit

| Source | Rows | Farm-Level? | Verified Real Harvests? | Notes |
|---|---:|---|---|---|
| `farmer_planting_schedules` (`actual_yield` not null) | 5,794 | Yes | **0** | All 5,794 rows belong to `@iclimate.test` demo accounts (`0` non-demo). |
| `rice_productions` | 124 | No (Barangay) | **0** | `verified_at` is null on all 124 rows (`14` `[demo_seed]`; `110` municipal-summary-derived rows sharing identical monthly municipal yields). |
| `historical_rice_productions` | 40 | No (Barangay) | Aggregate only | From `RICE-PRODUCTION (LIAN).xlsx` (`WS 2024`–`DS 2025-2026`); seasonal barangay totals without monthly weather or farm-level observations. |
| `mao-planting-summary-2020-2025.csv` | 60 | No (Municipal) | Aggregate only | Monthly municipal averages (`2020-05` to `2025-04`), overlapping the deployed model's training domain. |

---

## 4. Municipal-Level Diagnostic (Non-Independent, Not Farm-Level)

Evaluating `rice_yield_model_final.pkl` on the 60-row municipal monthly dataset (`mao-planting-summary-2020-2025.csv` + `iClimate_Modeling_Ready_TimeAware.csv`) — **not** independent farm-level validation:
- **All 60 rows (`actual` 1.76–4.70, `pred` 3.18–4.25 t/ha):** `MAE = 0.3748`, `RMSE = 0.5527`, `R² = 0.2982`, `MAPE = 12.61%`, `±10% = 68.33%`, `±20% = 88.33%`.
- **TEST split (n=8):** `MAE = 0.2326`, `RMSE = 0.2694`, `R² = 0.3028`, `MAPE = 5.62%`, `±10% = 87.50%`, `±20% = 100.00%`.
- By comparison, `rice_yield_model_candidate.pkl` (`ml_models` id=2) recorded `MAE = 0.3944`, `RMSE = 0.4690`, **`R² = -1.1043`** on 2025 municipal test data.

### Chronological Walk-Forward Comparison (Months 25–60, `n=36`, Causal Expanding Features)

Using strictly historical data (`0..t-1` to predict `t`, zero future leakage) on the authentic 60-row municipal dataset:

| Model / Baseline (Municipal Only) | MAE (t/ha) | RMSE (t/ha) | R² | MAPE | ±10% | ±20% |
|---|---:|---:|---:|---:|---:|---:|
| **Expanding Seasonal Mean Baseline** (`Wet`/`Dry`) | **0.4920** | **0.7031** | **0.1724** | **18.34%** | 61.11% | 83.33% |
| Walk-Forward Ridge (`alpha=10.0`) | 0.5144 | 0.7310 | 0.1055 | 19.06% | 61.11% | 83.33% |
| Walk-Forward RF (`max_depth=3`, `min_samples_leaf=3`) | 0.5033 | 0.7462 | 0.0679 | 19.06% | **63.89%** | 83.33% |
| Deployed `rice_yield_model_final.pkl` *(in-sample overlap)* | 0.4370 | 0.6350 | 0.3250 | 16.29% | 61.11% | 86.11% |

**Conclusion:** Neither Ridge nor a shallower Random Forest outperforms the simple expanding Seasonal Mean Baseline on leakage-free chronological walk-forward evaluation of the 60-row municipal dataset, confirming that retraining on existing municipal aggregates would not improve out-of-sample reliability.

---

## 5. Why Predictions Cluster (4.00–4.16 t/ha) & Farm-Size Mismatch

- **Tree-split `Area` bounds:** `[12.4250, 258.0900]` ha (reflecting municipal planted areas).
- **Real `farms` table (`1,081` farms):** `min = 0.1000` ha, `median = 0.7` ha, `max = 29.9587` ha. **`1,073` farms (99.26%) are `< 12.425` ha**, `7` have null area, and only **`1` farm** falls inside `[12.4250, 258.0900]` ha.
- **Zero sensitivity below 12.425 ha:** For `Area` = `0.1`, `0.5`, `0.7`, `1.0`, `2.0`, `5.0`, and `10.0` ha (Wet), the model outputs the exact same **`4.1069` t/ha**; across `0.1`–`500.0` ha, predictions vary only `4.0584`–`4.1417` t/ha.
- **Stored predictions (`yield_predictions`, n=6,542):** `min = 3.82`, `max = 4.16`, `avg = 4.0381` t/ha, with **`4,364` (66.7%)** clustered in `4.00–4.16` t/ha.

---

## 6. Integration & Verification Results

- `YieldModelGate` (`config/prediction.php` + `app/Services/Prediction/YieldModelGate.php`) enforces OOD checks across all 11 numeric features and withholds unvalidated estimates under `ICLIMATE_YIELD_GATE=withhold` across Farmer Dashboard, Yield Analysis, MAO Dashboard, Weather Predictions, Heat Map, and Planting Schedules.
- **Python ML tests (`pytest python_scripts/tests -q`):** `71 passed, 0 failed`.
- **Laravel Yield/Dashboard/HeatMap/ML tests:** `66 passed (640 assertions), 0 failed`.

---

## 7. Data Collection Requirement Before Retraining

To measure real farm-level accuracy and support retraining, collect **≥ 200–300** independently verified farm-level harvest records (spanning Wet/Dry seasons, Irrigated/Rainfed farms, barangays, soil/management practices, and farm sizes `0.1–30` ha), then run farm-grouped, chronological walk-forward validation.
