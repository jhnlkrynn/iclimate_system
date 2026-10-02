# Phase 2 report: data accuracy over a complete-looking dashboard

Date: 2026-10-01. Companion to `phase-1-audit.md`.

## 1. Ambulong → deployed rice-yield model: feature compatibility (NOT IMPLEMENTED)

**Result: the Ambulong monthly file is NOT confirmed compatible. The model's recent-weather source was not changed.**
The deployed model is `storage/models/rice_yield_model_final.pkl`: a scikit-learn Pipeline, Random Forest (300 trees), with 12 input features:
`RAINFALL, TEMP_AVG, TEMP_RANGE, Area, Previous_Rainfall, Previous_Temp, Rainfall_6M, Temp_3M, Temp_6M, Seasonal_Rainfall, Seasonal_Temp, Season`.
The training dataset and the feature definitions used to build it are **not in the repository** (only the 10-feature candidate model's dataset, `iClimate_Modeling_Ready_TimeAware.csv`, is). So every "required definition" below is inferred from `FeatureCatalog.php` and `PredictionEngine.php`, and none can be proven.

Ambulong file: `database/data/Ambulong Monthly Data.csv`, columns `YEAR, MONTH, RAINFALL, TMAX, TMIN, RH, WIND_SPEED, WIND_DIRECTION`; monthly resolution; Jan 2015 – Dec 2025 (131 rows); station is Ambulong (PAGASA, Tanauan, Batangas), not a point inside Lian.

| Model feature | Required definition (inferred) | Current source | Proposed Ambulong field | Unit | Resolution | Period | Transformation | Compatible? |
|---|---|---|---|---|---|---|---|---|
| RAINFALL | rainfall of the target month | Monthly weather forecast model (trained on Ambulong CSV) | RAINFALL | mm (monthly total assumed) | monthly | target month | none | UNVERIFIED (needs confirmation that training used monthly totals) |
| TEMP_AVG | mean temperature of target month | same forecast model | (TMAX+TMIN)/2 | °C | monthly | target month | mean of TMAX, TMIN | UNVERIFIED (training definition of "average" unknown) |
| TEMP_RANGE | spread of temperature in the period | derived from the 6 latest `climate_records` rows (max−min of temperature) | TMAX−TMIN | °C | monthly | target month | difference | **NO** — current code takes max−min of 6 rows' average temperature, which is a different quantity from TMAX−TMIN of one month |
| Area | farm area | farmer profile / request | none | ha | per farm | n/a | none | **NO** — Ambulong has no area; and no verified area data exists anywhere |
| Previous_Rainfall | rainfall of the month before the target month | latest `climate_records` row (now empty) | RAINFALL of month−1 | mm | monthly | month−1 | none | **NO for current targets**: Ambulong ends Dec 2025; for a 2026 target the previous month is not in the file |
| Previous_Temp | temperature of month−1 | latest `climate_records` row (now empty) | (TMAX+TMIN)/2 of month−1 | °C | monthly | month−1 | mean | **NO for current targets** (same gap) |
| Rainfall_6M | sum of rainfall over trailing 6 months | sum of 6 latest rows (now empty) | RAINFALL sum, months −6..−1 | mm | monthly | 6 months | sum | UNVERIFIED (window alignment and inclusion of the target month unknown); also the same recency gap |
| Temp_3M | mean temperature, trailing 3 months | mean of 3 latest rows | mean (TMAX+TMIN)/2, months −3..−1 | °C | monthly | 3 months | mean | UNVERIFIED + recency gap |
| Temp_6M | mean temperature, trailing 6 months | mean of 6 latest rows | same, 6 months | °C | monthly | 6 months | mean | UNVERIFIED + recency gap |
| Seasonal_Rainfall | rainfall total within the target's season | rows of the same season among the 6 latest | RAINFALL summed over the season | mm | monthly | season (Wet = May–Oct in code) | sum | UNVERIFIED (season window and whether partial seasons count are unknown) |
| Seasonal_Temp | mean temperature within the season | same | mean (TMAX+TMIN)/2 | °C | monthly | season | mean | UNVERIFIED |
| Season | Wet / Dry | forecast model | derived from MONTH | category | monthly | target month | May–Oct = Wet | UNVERIFIED (Philippine climate-type boundaries differ by station) |
| (weather model only) previous_humidity, previous_wind_speed | month−1 humidity, wind | latest `climate_records` row | RH, WIND_SPEED of month−1 | %, unit unknown | monthly | month−1 | none | **NO for current targets** (recency); WIND_SPEED unit not documented in the file |

**Blocking issues**
1. *Recency:* the model wants "previous month / last 3 / last 6 months" relative to the target. The newest Ambulong month is Dec 2025. Using it for an Oct 2026 prediction would feed 9-month-old data as "previous month". That is the silent substitution you asked me not to do.
2. *Unknown training definitions* for TEMP_AVG, TEMP_RANGE and the windows.
3. *Area* has no verified source at all.
4. *Current weather (Open-Meteo) vs historical climate:* kept separate. Open-Meteo feeds only live weather widgets and advisories; it must not be used as the model's historical input.

**Current behavior (after Phase 2):** the engine builds these inputs from `climate_records` only. That table is now empty, so the prediction is **unavailable** and says which inputs are missing. Nothing is substituted.

**Decisions needed from you / the researchers:** (a) the original training script or dataset for `rice_yield_model_final.pkl`; (b) whether the model should be retrained on the time-aware Ambulong dataset (the 10-feature candidate model already exists, but its test R² is −1.10, worse than predicting the mean); (c) a verified, current monthly climate feed (PAGASA/Ambulong updates) if the lag features are kept.

## 2. Risk calculation (REQUIRES METHODOLOGY VALIDATION)

Isolated behind `App\Contracts\RiskMethodology` (bound in `AppServiceProvider`). Dashboards call `DecisionSupportService`, which depends on the contract, so a validated methodology can replace `AgriculturalRiskScorer` without touching any dashboard. No weight, threshold, or exposure value was changed.

- **Formula:** `score = 0.34·rainfallRisk + 0.27·yieldRisk + 0.14·temperatureRisk + 0.18·barangayExposure + farmExposure + stressPenalty`, clamped to 0.05–0.98.
- **Weights:** rainfall 0.34, yield 0.27, barangay exposure 0.18, temperature 0.14. Farm-type add-on: Rainfed +0.10, Irrigated +0.03. Stress penalty: 0.025 per stress factor, max 0.12.
- **Rainfall risk bands (monthly mm):** ≤0 → 0.60; <80 → 0.95; <120 → 0.75; ≤280 → 0.22; ≤350 → 0.62; else 0.92.
- **Yield risk:** missing → **0.55 (default)**; <2 → 0.95; <3 → 0.78; <4 → 0.48; else 0.18.
- **Temperature risk:** ≥35 → 0.86; ≥32 → 0.64; <22 → 0.52; else 0.24.
- **Exposure values:** per-barangay table in `AgriculturalRiskScorer::BARANGAY_EXPOSURE` (0.45–0.90); **unknown barangay → 0.55 (default)**. These are engineering-judgment placeholders, not hazard data.
- **Level thresholds:** ≥0.85 Severe, ≥0.68 High, ≥0.45 Moderate, else Low.
- **Variables used:** monthly rainfall, temperature, predicted/last-recorded yield, farm type, barangay, stress-factor count.
- **Affected pages:** Barangay Risk Map (+ API), Farmer dashboard (barangay risk, highlights), MAO dashboard / operations, IT dashboard counts, System Prediction page, AI assistant answers, per-barangay `heatmap_areas` rows.
- **Phase 2 change that does touch behavior:** when rainfall or temperature is missing, the decision-support result is "Not assessed" instead of being scored from 0 mm / 0 °C. A barangay without a numeric live temperature is "No Data" on the map.
- **Source of the final method:** not chosen. Needs a documented basis agreed with the researchers/adviser; do not assume MDRRMO, PAGASA or MAO defines an agricultural-risk methodology until that is confirmed.

## 3. 25 mm/day heavy-rain rule: PENDING MAO / METHODOLOGY CONFIRMATION

Constant: `MaoDashboardService::HEAVY_RAIN_MM = 25.0` (marked pending in code). Nothing was removed or changed.

| File | Function / place | Purpose | UI effect | Calculation effect |
|---|---|---|---|---|
| `app/Services/Analytics/MaoDashboardService.php:44` | constant | single definition | none | none |
| `MaoDashboardService.php` ~170 | forecast day flags | tag a forecast day "Heavy rain" | highlighted day on MAO forecast | none |
| `MaoDashboardService.php` ~508 | heavy-rain lookahead alert | alert within look-ahead days | MAO alert "N mm forecast (threshold 25 mm)" | none |
| `app/Services/Analytics/FarmerDashboardService.php:44,197,503` | `HEAVY_RAIN_MM` alias, day flags, alerts | flag heavy-rain days; "Heavy rain expected" alert | farmer forecast highlight + alert | none |
| `app/Services/Analytics/FarmerCropPlanService.php:194` | crop-plan rain scan | warn about field activity on heavy-rain day | crop-plan warning text | none |
| `resources/views/dashboards/farmer/forecast.blade.php:40` | footnote | states "heavy rain (25 mm or more)" | visible text (not described as official) | none |
| `resources/views/dashboards/mao/forecast.blade.php:23` | footnote | prints the constant | visible text | none |
| `app/Services/Weather/MonthlyWeatherCalendarService.php:152` | day risk level | rainfall ≥25 → "High" day | calendar day risk colour | none |
| `app/Services/Weather/LianBarangayWeatherService.php:182` | `rainfallStatus` | 24 h ≥25 → "Adequate rainfall" (a different meaning of 25) | barangay weather label | label only |

Related but different numbers (also unvalidated): 30 mm/day in `AdvisoryRuleEngine` (lines 269, 368), 50 mm "Severe" in `MonthlyWeatherCalendarService`.

## 4. Lian coordinates

Single definition now: `App\Support\LianLocation` (also surfaced as `config('iclimate.location')`), municipality Lian, province Batangas, country Philippines, timezone Asia/Manila, reference point **14.033, 120.650**. Verification: Wikipedia lists Lian at 14°02′N 120°39′E (about 14.03, 120.65); this agrees to that precision. It is a municipal reference point, not an official survey point. Please confirm if an official one is required.

Found before the change:

| Definition | Where | Purpose | Action |
|---|---|---|---|
| 14.033, 120.650 | `config/services.php` (`weather`, `open_meteo`) and code fallbacks | live municipal weather | centralized |
| 14.04, 120.65 | `config/services.php` `weather_api`, `.env` | WeatherAPI default | centralized to canonical (small behavior change) |
| 13.98, 120.65 | local `.env` `WEATHER_LATITUDE` (overridden by `ICLIMATE_WEATHER_LAT`) | stale | set to canonical |
| 14.0389, 120.6503 | `WeatherPredictionController::LIAN_MUNICIPAL_COORDINATES` | marker for users with no barangay | removed, uses canonical |
| 14.033, 120.650 | Leaflet `setView` in `crud/show`, `farm-boundaries/edit` | map centre | left (same value, JS) |
| 14.02, 120.66 | `resources/js/barangay-risk-map.js` FALLBACK_CENTER | map fallback centre | left, view-only |
| 13.95–14.12 / 120.55–120.75 | `FarmBoundaryController` | validation box for farm boundary points | kept separate |
| 19 barangay centroids | `LianBarangays.php` | polygon centroids of the real GeoJSON (verified) | **kept; this is the barangay source** |
| 19 barangay points (different) | `python_scripts/analytics_tasks.py` DEFAULT_AREAS | fallback | **removed** (same hand-entered error class as the old LianBarangays values) |
| same hand-entered points | migration `2026_06_30_000002_seed_lian_barangay_heatmap_defaults.php`, table `heatmap_areas` | seeded rows | **not changed**; `BarangayRiskMapService` overwrites lat/lon from `LianBarangays` on refresh |
| Ambulong station | CSV | observation site (Tanauan area) | not a Lian point; not mixed in |

## 5. Timezone (Asia/Manila business dates)

- **Storage stays UTC.** `config('app.timezone')` was deliberately NOT changed: 30 columns are `timestamp without time zone` (AI tables, `ml_models`, `backup_verifications`, `weather_forecast_feedback`, …). Changing the app zone would shift those values by 8 hours.
- New `App\Support\BusinessClock` (Manila `now()/today()/year()/month()`, `todayUtc()/startOfDayUtc()` for DB comparisons, `sqlDate()` for per-day grouping).
- Switched to it: Calendar controller, Candidate prediction month, Dashboard "today" and 7-day report window, advisories "expired today", System-log "today", `ClimateRecord::plausible` future-date check, schedule status (`FarmerPlantingSchedule::monitoringStatus`, now compares calendar dates), PAGASA weekly dedupe key, AI assistant "next month", prediction date validator and default target date, PredictionService date parsing, KPI "next harvest", dashboard overview windows/charts, IT activity chart, per-day SQL grouping (`DATE(created_at AT TIME ZONE 'Asia/Manila')`), risk-map season/month, farmer and MAO yield-prediction target month, Python metric counters, `harvested_on` max date, "Today's Activities" label.
- **Not changed (needs your decision):** scheduled jobs in `routes/console.php` use the app timezone, so `dailyAt('01:30')` runs at 09:30 Manila. Setting `app.schedule_timezone = Asia/Manila` would move them 8 h earlier. Python scripts use the server clock (`datetime.now()`).

## 6. Missing value → zero audit

Fixed (semantic, not a global replace):

| Place | Was | Now |
|---|---|---|
| `PredictionEngine::buildModelInput` | 0 for missing rainfall/temp/humidity/wind; area default 1.0 ha; forecast copied into "previous" values; partial 6-month sums | null; model not run; missing inputs reported |
| `DecisionSupportService::evaluate` | missing → 0 mm → "Drought warning" | "Not assessed" |
| `rice_yield_service.py` | defaults 180 mm / 29 °C / 78 % / 8 km/h in warnings | rules skip missing values; required features reject null |
| `analytics_tasks.py` (`_model_input`, `heatmap_risk`, `dataset_processing`, `weather_prediction`) | `_number()` default 0.0, humidity 75, wind 8, area 1 | `_optional_number`, missing → run fails or row skipped |
| `ShortTermOutlookService` | 0 and start=end substitution | no outlook when a value is missing |
| `BarangayRiskMapService` | missing temp/humidity/wind → 0.0 | "No Data" / null |
| `WeatherApiService` chart arrays | null → 0 | null (gap) |
| `DashboardController::maoAggregates` + MAO view | missing → "0.0 tons" | "Insufficient verified data" |
| `FarmerDashboardWeatherService` "hot and dry" | missing rainfall counted as 0 mm | needs both readings |
| `WeatherPredictionController::yieldSummary` | missing yield → 0 | no summary |

Reviewed and left (valid): counts (`?? 0` for a count), threshold checks where a missing value cannot trigger a positive alert, 0 mm rainfall (a real observation).
Left, noted: `AgriculturalRiskScorer::components` still casts null to 0 internally; every caller now filters nulls before calling it. `advisory_ai` in Python prints missing values as 0 in its message text but skips benign/missing rows.

## 7. Prediction failure behavior

`PredictionEngine::localYieldFallback` (3.6 / 4.1 t/ha ± fixed adjustments) is removed. Model failure or missing input → no yield, no saved prediction. `PlantingSchedulePredictionService` keeps the last successful prediction (its own generation date, plus a "last successful, newer one failed" note) when inputs are unchanged, and clears it only if the schedule's planning inputs changed. Dashboard caches never store a failed result. Failures are logged (`Log::warning`) so IT still sees them in System Logs/Health; farmers see plain "not available".
