# Phase 33 — ML Reliability, OOD Gating, and Weather Forecast Improvements

**Date:** 2026-11-20  
**Scope:** Domain/sensitivity audit of `rice_yield_model_final.pkl`, OOD gate expansion, and leakage-free weather forecast improvements (`python_scripts/monthly_weather_random_forest.py`). All models and database records preserved; `ICLIMATE_YIELD_GATE=withhold` retained.

## 1. Farm-Area Mismatch & OOD Gating
- Across `1,081` farms in `iclimate_staging_20261103_fix3`: `1,073` (`99.26%`) are `< 12.0 ha` (mean `0.91 ha`), `7` (`0.65%`) have missing/zero area, and `1` (`0.09%`, `29.96 ha`) is within `rice_yield_model_final.pkl`'s `Area` split range (`12.42–258.09 ha`).
- `1,020` farms are `Rainfed` and `61` are `Irrigated`; `farm_type` is not a feature in `rice_yield_model_final.pkl`.
- **Fix:** Added `extended_split_envelope` (`config/prediction.php`) covering all remaining 8 numeric features (`rainfall`, `temp_avg`, `previous_rainfall`, `previous_temp`, `temp_3m`, `temp_6m`, `seasonal_rainfall`, `seasonal_temp`) from the 300-tree split bounds, enforced in `YieldModelGate::assess()` and exposed as `outside_features` in `YieldEstimateRecord`.

## 2. Weather Model Walk-Forward Evaluation (Ambulong, 2024–2025, `n=24`)
Implemented leakage-free same-month historical climatology (`clim_*`), regularized RF (`max_depth=5`, `min_samples_leaf=3`), and 50/50 RF+climatology shrinkage for `rainfall` in `python_scripts/monthly_weather_random_forest.py`:

| Target | Before (MAE / RMSE / R²) | After (MAE / RMSE / R²) | Monthly Climatology Baseline |
|---|---|---|---|
| Rainfall (mm) | `114.73 / 163.50 / 0.325` | **`96.57 / 145.38 / 0.466`** | `88.58 / 137.00 / 0.526` |
| Temperature (°C) | `0.41 / 0.54 / 0.781` | `0.40 / 0.54 / 0.775` | `0.44 / 0.55 / 0.767` |
| Humidity (%) | `2.67 / 3.68 / 0.608` | `2.69 / 3.83 / 0.577` | `3.29 / 4.30 / 0.465` |
| Wind Speed (m/s) | `0.44 / 0.56 / 0.196` | **`0.43 / 0.55 / 0.226`** | `0.46 / 0.57 / 0.156` |

- `weather_model.pkl` (LinearRegression rainfall, 2025 holdout `n=12`): `MAE=97.42`, `RMSE=154.89`, `R²=0.383` (vs climatology `R²=0.539`).

## 3. Rice Yield Data Provenance & Validation Plan
- `farmer_planting_schedules`: `5,794/5,794` (`100%`) `actual_yield` rows belong to `@iclimate.test` demo accounts (`0` real-user actuals).
- `rice_productions`: `124` rows, `0` verified (`14` `[demo_seed]`, `110` unverified municipal-summary rows).
- **Plan:** Collect `>= 30` signed MAO Lian farm-season records (`planting_date`, `harvest_date`, `area_harvested_hectares`, `production_tons`, `yield_per_hectare`, `irrigation_type`, `rice_variety`, typhoon/loss flag) paired with verified local/PAGASA climate history; train and evaluate via chronological/season-grouped CV against a seasonal-irrigation mean baseline before requesting approval to lift `ICLIMATE_YIELD_GATE=withhold`.
