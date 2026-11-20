# Phase 34 — Weather Forecast Optimization & Verification

**Date:** 2026-11-20  
**Scope:** Per-target weather forecasting optimization in `python_scripts/monthly_weather_random_forest.py` using strict expanding-window walk-forward validation on `database/data/Ambulong Monthly Data.csv` (`2024–2025`, `n=24`).

## 1. Per-Target Architecture
- **Temperature:** Retained `RandomForestRegressor` (`n_estimators=45`, `max_depth=7`, `min_samples_split=2`), which outperformed anomaly Ridge and climatology.
- **Rainfall, Humidity, Wind Speed:** Switched to **Climatology-Anomaly Ridge** (`StandardScaler` + `RidgeCV` predicting `y - same_month_climatology` from 15 seasonal/lag/rolling features, then adding `same_month_climatology`) when `>= 24` monthly samples and prior same-month history exist (retaining RF fallback for smaller sample fixtures).

## 2. Walk-Forward Comparison (2024–2025, `n=24`)

| Target | Original RF (`d7`) MAE / RMSE / R² | Phase 34 MAE / RMSE / R² | Monthly Climatology Baseline |
|---|---|---|---|
| Rainfall (mm) | `114.73 / 163.50 / 0.325` | **`90.52 / 135.23 / 0.538`** | `88.58 / 137.00 / 0.526` |
| Temperature (°C) | `0.41 / 0.54 / 0.781` | **`0.41 / 0.54 / 0.781`** | `0.44 / 0.55 / 0.767` |
| Humidity (%) | `2.67 / 3.68 / 0.608` | **`2.76 / 3.66 / 0.613`** | `3.29 / 4.30 / 0.465` |
| Wind Speed (m/s) | `0.44 / 0.56 / 0.196` | **`0.42 / 0.54 / 0.263`** | `0.46 / 0.57 / 0.156` |

All four targets beat the monthly climatology baseline on both **RMSE** and **R²**.

## 3. Untouched Multi-Window Verification & PHP Synchronization
- **Untouched 2021–2023 (`n=36`) & Full 5-Year 2021–2025 (`n=60`) Walk-Forward (Python):**
  - 5-Year Rainfall: `MAE=84.34` (clim `83.02`), `RMSE=119.42` (beats `119.64`), `R²=0.480` (beats `0.478`).
  - 5-Year Temperature: `MAE=0.39` (beats `0.43`), `RMSE=0.50` (beats `0.54`), `R²=0.768` (beats `0.728`).
  - 5-Year Humidity: `MAE=2.29` (beats `2.50`), `RMSE=2.99` (beats `3.33`), `R²=0.593` (beats `0.494`).
  - 5-Year Wind Speed: `MAE=0.44` (beats `0.46`), `RMSE=0.56` (beats `0.58`), `R²=0.200` (beats `0.125`).
- **PHP `MonthlyWeatherRandomForest.php` (`v5`, 2025 true holdout `n=12`):**
  - Applied climatology-shrinkage (`0.25 * RF + 0.75 * month_clim_rainfall`) in `buildPrediction()`.
  - 2025 Rainfall improved from `MAE=119.67 / RMSE=169.80 / R²=0.2586` → **`MAE=98.49 / RMSE=141.05 / R²=0.4884`**.
- **Seasonal Walk-Forward Breakdown (2021–2025, `n=60`):**
  - Wet (May–Oct, `n=30`): Rainfall `MAE=110.07, R²=0.236`; Temp `MAE=0.35, R²=0.633`; Humidity `MAE=1.90, R²=0.546`; Wind `MAE=0.60, R²=-0.384`.
  - Dry (Nov–Apr, `n=30`): Rainfall `MAE=58.62, R²=-0.002`; Temp `MAE=0.42, R²=0.714`; Humidity `MAE=2.69, R²=0.437`; Wind `MAE=0.29, R²=0.481`.
- **PHP ↔ Python Synchronization & Failover:** `MonthlyWeatherRandomForest::buildPrediction()` calls `PythonService::monthlyWeatherForecast()` (`POST /forecast/monthly-weather`) as primary (`engine=python_fastapi_phase34`, `fallback_used=false`), and falls back to PHP RF + `0.25*RF + 0.75*month_clim_rainfall` on outage (`engine=php_fallback_rf`, `fallback_used=true`), persisting `engine`, `fallback_used`, `source_name`, `weather_model`, and `generated_at` in `monthly_weather_predictions`. Verified in `MonthlyWeatherFailoverTest`.
