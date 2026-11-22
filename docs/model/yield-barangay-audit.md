# Yield Barangay Audit (`/heat-map?layer=yield` & `/analysis/yield`)

**Scope:** Read-only audit conducted on `2026-11-22`. No database records, model artifacts, or application code were modified.

---

## 1. Is the Value Hard-Coded or Cached as a Single Constant?

**No.** Every barangay's estimate is computed individually through the trained Random Forest model (`storage/models/rice_yield_model_final.pkl`) and persisted per barangay in `yield_predictions`:

```text
GET /api/heat-map?layer=yield
  -> App\Http\Controllers\Api\HeatMapController::__invoke()
  -> App\Services\Risk\HeatMapLayerService::forLayer('yield', ...)
  -> App\Services\Risk\HeatMapLayerService::yieldRows()
  -> App\Services\Prediction\BarangayYieldForecastService::all('season')
  -> App\Services\Prediction\YieldPredictionStore::barangays('season')   [reads yield_predictions]

Daily scheduled refresh (iclimate:warm-farmer-yields, routes/console.php):
  -> App\Console\Commands\WarmFarmerYields::handle()
  -> App\Services\Prediction\BarangayYieldForecastService::prepare()
  -> saveBarangays('season', ...) -> runForBarangay() -> run()
  -> App\Services\Prediction\RiceYieldPredictionService::predictForUser()
  -> App\Services\Prediction\PredictionEngine::predict()
  -> PredictionEngine::requestYieldFromModel() -> PythonService::farmingAssistant()
  -> App\Services\Prediction\YieldLocalAdjustment::forRequest()
```

---

## 2. Exact Model Features & Inputs Sent (`Dry` Season, `2027-03-01`)

`RiceYieldPredictionService::INPUT_TO_MODEL_FEATURE` maps **12 features** into `rice_yield_model_final.pkl`:

`RAINFALL`, `TEMP_AVG`, `TEMP_RANGE`, `Area`, `Previous_Rainfall`, `Previous_Temp`, `Rainfall_6M`, `Temp_3M`, `Temp_6M`, `Seasonal_Rainfall`, `Seasonal_Temp`, `Season`.

Neither `barangay` nor `farm_type` is a feature of the trained model. For `Humayingan`, `Prenza`, and `Balibago` (`Dry` season `2027-03-01`, `area = 1.5 ha`), **all 12 model inputs are identical**:

| Feature | Humayingan | Prenza | Balibago |
| --- | ---: | ---: | ---: |
| `RAINFALL` (mm) | `19.3` | `19.3` | `19.3` |
| `TEMP_AVG` (°C) | `27.48` | `27.48` | `27.48` |
| `TEMP_RANGE` (°C) | `8.28` | `8.28` | `8.28` |
| `Area` (ha) | `1.5` | `1.5` | `1.5` |
| `Previous_Rainfall` (mm) | `33.57` | `33.57` | `33.57` |
| `Previous_Temp` (°C) | `26.55` | `26.55` | `26.55` |
| `Rainfall_6M` (mm) | `134.26` | `134.26` | `134.26` |
| `Temp_3M` (°C) | `26.69` | `26.69` | `26.69` |
| `Temp_6M` (°C) | `27.42` | `27.42` | `27.42` |
| `Seasonal_Rainfall` (mm) | `600.82` | `600.82` | `600.82` |
| `Seasonal_Temp` (°C) | `27.4` | `27.4` | `27.4` |
| `Season` | `Dry` | `Dry` | `Dry` |
| **Predicted Yield** | **`4.07` t/ha** | **`4.07` t/ha** | **`4.07` t/ha** |

*(With Lian's typical/municipality area `29.96 ha`, the model returns `4.10 t/ha`.)*

---

## 3. Per-Barangay Weather Forecasts

`LianBarangayWeatherService::savedForecasts()` **does** fetch and store 16-day daily forecasts per barangay centroid (`LianBarangays::coordinates()`):

| Barangay | Centroid (lat, lon) | 16-Day Rain Sum | 16-Day Mean Temp | Model Rerun with Barangay Weather |
| --- | --- | ---: | ---: | ---: |
| Humayingan | `13.9967, 120.6725` | `22.3 mm` | `26.50 °C` | **`4.08 t/ha`** |
| Prenza | `14.0129, 120.6399` | `22.3 mm` | `26.83 °C` | **`4.08 t/ha`** |
| Balibago | `13.9414, 120.6489` | `11.3 mm` | `27.02 °C` | **`4.08 t/ha`** |

Two reasons this does not separate barangays:
1. The 16-day forecast only covers `2026-11-22` to `2026-12-07`, whereas the season yield model targets the season's harvest month (`2027-03-01` for Dry) using Lian's municipality-wide monthly forecast (`PredictionOrchestrator::monthlyWeatherForecast`).
2. Even when overriding `rainfall` and `temp_avg` with each barangay's 16-day values, all 3 barangays still produce **`4.08 t/ha`** because Lian's barangays are only a few kilometers apart and the trailing 3m/6m/seasonal climate features dominate.

---

## 4. Retraining Check on `rice_productions`

- **Counts (excluding `[demo_seed]` rows):** 10 barangay buckets (`Balibago`, `Binubusan`, `Bungahan`, `Kapito`, `Luyahan`, `Malaruhatan`, `Matabungkay`, `Prenza`, `(blank)`, `—`), each with **5 Dry** (2020–2024) and **6 Wet** (2020–2025) `Irrigated` rows (`109` + `1` blank/Wet = `110` rows total; `0` `Rainfed` rows; `0` rows for 11 official barangays; `0` verified rows).
- **Cross-barangay yield variance (`Irrigated` across the 10 buckets):**
  - `2020 Dry` (`4.15`), `2021 Dry` (`4.20`), `2023 Dry` (`4.12`), `2020 Wet` (`3.90`), `2022 Wet` (`3.40`), `2023 Wet` (`3.95`), `2024 Wet` (`1.90`): **variance = `0`** (all 10 identical).
  - `2022 Dry` (`4.34–4.35`), `2024 Dry` (`3.84–3.85`), `2021 Wet` (`3.84–3.85`), `2025 Wet` (`3.79–3.80`): **variance = `0.000009`** (`0.01 t/ha` rounding difference only).
  - `2025 Wet` including `[demo_seed]` (`n = 24`): `mean = 3.5617`, `variance = 0.075089`.

This confirms the historical `rice_productions` rows were **allocated proportionally from municipal totals**, not independently measured per barangay.

---

## 5. Conclusion

**(a) Correct for a Lian-wide model** (and **(d) needs retraining** if barangay-level differentiation is desired later).

- Today's identical `4.10 t/ha` across all 19 barangays is **not a bug** — it is the honest output of a municipality-level climate model paired with `YieldLocalAdjustment` falling back to `1.0` because `rice_productions` has no true barangay-specific yield differences.
- **What retraining would need:** at least 3–5+ seasons of **independently measured (non-allocated) per-barangay, per-farm-type (`Irrigated` vs. `Rainfed`) harvest yields**, along with barangay-level features (e.g., irrigation share, soil/elevation/flood-proneness, or verified farmer harvest actuals).

---

## 6. Limitations and Future Work

- **Current Scope (Option a):** Because `rice_yield_model_final.pkl` uses municipality-wide climate inputs and `rice_productions` contains allocated municipal totals rather than measured per-barangay yields, all 19 Lian barangays receive the same Lian-wide seasonal estimate (`4.10 t/ha` for Dry season 2026–2027). The Heat Map yield layer presents this transparently as a single Lian-wide card (with neutral sage shading and no misleading band colors) until local records diverge by more than 3%.
- **Harvest Data Collection for Future Retraining:** Farmer harvest submissions (`farmer_planting_schedules` via `YieldSurveyService` and planting records) capture `actual_yield` (t/ha), `harvested_on`, `farm_id`, `barangay`, `farm_type`, `season`, and `rice_variety_id`. The MAO `"Harvest records for model training"` page (`/analysis/harvest-training`) tracks progress per `(barangay, farm_type, season)` toward a usable retraining threshold (`15` harvests per bucket) and provides an anonymized CSV export (personal names omitted, `farm_id` hashed) for future model evaluation and retraining.
- **Farm-Type Yield Adjustment (`YieldFarmTypeAdjustment`):** Applied after `YieldLocalAdjustment` in `RunYieldPredictionRequest` and `BarangayYieldForecastService::runForBarangay()` (with `farm_type_model_value`, `farm_type_factor`, `farm_type_basis`, and `farm_type_source` stored in `details` / `model_input`), using **only iClimate's own records** (`farmer_planting_schedules.actual_yield` + verified `rice_productions` rows with `irrigation_type` in `Irrigated`/`Rainfed`):
  - `factor = avg(actual_yield of farm_type in season) / avg(all harvested farms in season)`, clamped to `[0.7, 1.3]`.
  - Enabled only when **both** `Rainfed` and `Irrigated` each have at least **10 harvests** across at least **2 seasons**; otherwise `factor = 1.0`.
  - Until that threshold is met, MAO may optionally enter a rainfed/irrigated yield difference (%) with a required note on `/mao/associations/farm-types` (labeled `"Set by MAO"`); computed iClimate harvest factors automatically take priority once enough records exist.
