# iClimate — Data Accuracy Audit, Part 1 (Audit only, nothing was changed)

Date of audit: October 1, 2026. Scope: the whole project (code, live database, live weather, prediction service).
How: I searched the code, ran read-only checks on the live database, and compared screen values with the real sources.
**No code, data or settings were changed for this audit.**

## 1. What the system is made of (baseline)

| Part | What it is |
|---|---|
| Framework | Laravel 12, PHP 8.2+ |
| Database (live) | PostgreSQL on Supabase. (`.env.example` still says MySQL — see L2.) Tests use in-memory SQLite. |
| Roles | Farmer (858 accounts), MAO Personnel (3), IT Expert (2) |
| Models | 40 (users, farmer profiles, organizations, schedules, climate records, rice production, advisories, notifications, predictions, logs, chat/AI) |
| Services | About 60 in `app/Services` and `app/Support` (weather, risk, prediction, dashboards, advisories, AI) |
| Dashboards | Farmer, MAO overview, MAO operations (old page), IT |
| Charts | Chart.js, drawn from backend data (no chart has typed-in numbers) |
| Cache | File cache. Short caches for dashboards (60–90 s), weather (45 min), prediction (6 h), risk map (20 min) |
| Background jobs | Laravel scheduler: weather every 30 min, dashboard warm-up every minute, PAGASA advisories every 15 min, risk every 4 h, backups daily |
| Weather | Open-Meteo (live). WeatherAPI.com code exists but is not the live provider. PAGASA web pages for advisories |
| Prediction | Python FastAPI service on port 8010 (Random Forest models for rice yield and monthly weather). Status checked today: running, both models loaded |
| Secrets | Kept in `.env`. No API keys found in templates or JavaScript |

## 2. What is correct (no change needed)

| Where | Value | Source | Result |
|---|---|---|---|
| IT dashboard | Total users 863 = 858 + 3 + 2; active 863 | Database count | Correct |
| MAO + IT | Farmer profiles 855, organizations 16, without organization 7, farm area 736.06 ha | Database | Same on both dashboards. Average area excludes the 3 profiles with no area |
| Farmer dashboard | Temperature, air moisture, wind, rain, 7-day forecast | Open-Meteo | Matched my direct check of the provider |
| Farmer dashboard | Lian average harvest (October 4.23, September 3.48 t/ha) | Yield file (60 monthly values) | Matches the file |
| Farmer dashboard | Production 8.80 tons = 3.52 t/ha × 2.5 ha | Calculated | Units correct (t/ha vs tons) |
| Farmer dashboard | Weather when the provider fails | Last good reading, marked "stale" with a time | Good practice |
| IT dashboard | Health status | Real checks (database, jobs, weather, backups) | Not typed in |
| All code | Sample numbers from your list (863, 858, 855, 736.06, 2343, 26.9, 28.3 …) | Searched `app`, `resources`, `routes`, `config`, `database/seeders` | **None are typed into the code** |
| Database | Orphan profiles, duplicate profiles, duplicate climate rows, negative or impossible values, missing organizations | Read-only checks | **None found** |

## 3. Problems found

Types used: HARDCODED, DATABASE-DRIVEN, API-DRIVEN, CALCULATED, PREDICTED, HISTORICAL, CACHED, CONFIGURATION, PLACEHOLDER.

### CRITICAL

**C1. The rice-yield model uses fake weather rows as its "recent weather" inputs.** (PLACEHOLDER shown as RECORDED)
- Where: `app/Services/Prediction/PredictionEngine.php` lines 79–110.
- It takes the latest 6 rows of `climate_records` that are "plausible". The table has only 6 rows. Five are seeded sample rows labelled "PAGASA", created together on 2026-07-15 13:56:13. The sixth is the impossible "sir mags" row, which is dropped. So all 5 sample rows go into the model.
- Proof: for the demo farmer, the model input `previous_temp` is 37.46 °C, `previous_rainfall` 28.86, `previous_humidity` 69, `previous_wind_speed` 15.66. These are exactly row id 2. `rainfall_6m`, `temp_3m`, `temp_6m`, `seasonal_rainfall` and `seasonal_temp` come from the same five rows.
- Effect: **the Expected Rice Harvest (3.52 t/ha today), planting-plan predictions, the AI assistant's answers and any other use of this engine rest on sample data.** The dashboards hide the sample rows, but this engine does not.
- Expected source: real monthly climate (the Ambulong file has 131 real months; the import into `climate_records` never ran).
- Fix (Part 4): stop using sample rows; read recent months from the real Ambulong data; show "not enough recent data" instead of guessing.

**C2. A made-up yield can be shown as the farmer's "Expected Rice Harvest".** (HARDCODED, shown as PREDICTED)
- Where: `PredictionEngine::localYieldFallback()` lines 195–260.
- If the Python model service is down, the engine returns a number built from typed-in base values (3.6 t/ha dry, 4.1 t/ha wet), typed-in adjustments (−0.8, +0.25, −0.65 …), and a clamp of 1.2–6.5. It sets `fallback = true`. Its base can also come from the 4 sample `rice_productions` rows.
- The Farmer dashboard (`FarmerDashboardService::prediction`) does **not** check `fallback`, so it would show this number as the farmer's best guess.
- Today the model is running, so today's 3.52 is real model output (model `rice_yield_model_final:687394f77677`, fallback false). The risk is on any day the service is down.
- Fix (Part 4): when the model is down, show no yield (or the last real result with its date). Never show the local rule number as a prediction.

**C3. Missing weather becomes zero and is used in calculations.** (missing → 0)
- `PredictionEngine.php` 96–99 (rain, temperature, humidity, wind default to `0`), `DecisionSupportService.php` 15–18, `ShortTermOutlookService.php` 43–50, `BarangayRiskMapService.php` 298–303 (`?? 0.0`), `WeatherApiService.php` 277–295 (chart series use `0` for a missing reading).
- Effect: a missing temperature is treated as 0 °C in risk and prediction, and a gap in a weather chart is drawn as a real zero.
- Fix (Parts 3 and 4): treat missing as "no data". Skip the calculation or the chart point.

**C4. 20 seeded sample rows are still in the live database.** (PLACEHOLDER)
- 4 rice production, 6 climate (1 impossible), 3 notifications (they belong to Default Farmer, farmer@iclimate.com), 3 advisories (already expired), 4 prediction runs made by an under-trained model.
- Dashboards leave them out, but the prediction engine (C1, C2), the chatbot and the ML payloads still read them.
- Fix (Part 2): after you approve, run `php artisan iclimate:purge-sample-data --force` (it makes a backup first).

### HIGH

**H1. Farm risk uses placeholder numbers.** (CONFIGURATION that is not validated)
- `AgriculturalRiskScorer`: per-barangay "exposure" values (for example Matabungkay 0.90, Binubusan 0.45), weights (rain 0.34, yield 0.27, exposure 0.18, temperature 0.14), and a default exposure of **0.55 for any barangay name it does not know**. The code itself says these are "engineering-judgment placeholders, not yet derived from validated MDRRMO/LGU hazard data".
- The planting advice ("Ideal planting period", "Delay planting due to flood risk") comes from typed-in monthly rain bands (80, 180–280, 281–350, 350 mm) in `DecisionSupportService`. No source or reference is written down.
- Status: **REQUIRES METHODOLOGY VALIDATION.** I did not change any threshold. The dashboard already says "a planning guide only".

**H2. 9 farmer profiles have a barangay name that is not one of the 19.** (DATABASE, invalid)
- Names found: "Poblacion", "Luyahn" (typo), "Unknown", "Barangay 4 Pob", "Barangay 1 Pob", "Balibgo" (typo).
- Effect: these farmers are missing from "farmers per barangay", the risk map highlight and the "barangays covered" count (10 of 19 today).
- Fix (Part 2): list them for you to correct. I will not guess or auto-edit them.

**H3. The app clock is UTC, but farmers live in Manila time.** (DATE/TIME)
- `config/app.php` timezone is `UTC`. The dashboard services use Asia/Manila, but model methods (for example `FarmerPlantingSchedule::monitoringStatus()` uses `today()` and `isFuture()`) use UTC.
- Effect: between midnight and 8 AM in Manila, schedule status and "days until planting" can be one day off. A test failed for exactly this reason last night.
- Fix (Part 3): use one timezone rule for "today" everywhere.

**H4. "Lian" has four different coordinate sets.** (CONFIGURATION, inconsistent)
- 14.033, 120.650 (main weather); 14.04, 120.65 (`WeatherApiService`, used for the daily wind on the farmer forecast); 14.0389, 120.6503 (`WeatherPredictionController`); 13.98, 120.65 (in the live `.env`, not used by the main weather).
- Effect: small, but the farmer's temperature and wind come from slightly different points. Forecast days are 5 in one config and 7 in another.
- Fix (Part 3): one shared setting for the place.

### MEDIUM

- **M1. The farmer's prediction is not saved.** It is cached for 6 hours only. We cannot say later what a farmer was shown. Model version is not shown on screen. (Part 4)
- **M2. Old MAO operations page.** It sets `$totalFarmers = 0`, `$riceProductionTotal ?? 0`, `$avgYield ?? 0` when data is missing (`mao-operations.blade.php` 469–477, `DashboardController` 593–595). A missing value looks like zero. It also builds some insight sentences in JavaScript. (Part 6)
- **M3. "Recommended" status is guessed from words.** `DashboardOverviewService` line 417 marks an advisory "Recommended" if its text contains "ideal", "good" or "suitable". (Part 5)
- **M4. Typed-in reference tables.** `LianFarmTypes` (associations, farm type, area, number of farmers; the source is "farm types location workbook", no date) and the barangay exposure table. They are reference data, not live data, but they have no date or version. (Part 2)
- **M5. Two meanings of "season".** The risk map treats May–October as wet; the planting form treats wet-season planting as May–July. (Part 5)
- **M6. Average yield is an unweighted average of monthly municipal averages.** The data file has no area or production, so a weighted yield is not possible. The page already says how many months are included. Please confirm this is the method you want. (Part 5)
- **M7. "Farmers" has two meanings and the labels do not always say which.** 858 = farmer accounts, 855 = farmer profiles (3 accounts have no profile). The IT dashboard explains the gap in a note; MAO "Registered Farmers 855" does not. Legacy code in `DashboardController::stats()` counts accounts (858). (Part 5)

### LOW

- **L1.** "© 2026" is typed into 7 templates (`welcome`, `login`, `register`, `guest`, `sidebar` ×2, `errors/minimal`). It will go stale in 2027.
- **L2.** `.env.example` is out of date (MySQL, WeatherAPI, 5 forecast days) compared with the live setup (PostgreSQL, Open-Meteo, 7 days).
- **L3.** Stray files in the project root: `scratch/`, `_dbcheck.php` (contains a database name and user), `an test --filter=…`, service log files.
- **L4.** The IT audit command reports one failed check: "IT · Notifications sent (in note)".
- **L5.** The `/testing/candidate-rice-yield` pages (IT only) have fixed years 2025–2027 and a 2026 command. They are testing tools, not dashboards.

## 4. Classification of important values

| Value | Class | Scope | Updated |
|---|---|---|---|
| Temperature, air moisture, wind, rain now | LIVE (Open-Meteo) | Lian point, not the farm | Every 45 min, time shown |
| 7-day forecast | FORECAST | Lian point | Same fetch |
| Expected Rice Harvest (t/ha) | PREDICTED | Farmer profile + Lian weather | Cached up to 6 h; **inputs affected by C1** |
| Estimated production (tons) | CALCULATED | Prediction × profile farm area | With prediction |
| Lian average harvest | HISTORICAL | Whole municipality | File dated 2026-09-22 |
| Rainfall chart | HISTORICAL | Ambulong station | Latest year on record: 2025 |
| Farm risk | CALCULATED | Barangay | Every 4 h; **placeholder weights (H1)** |
| Planting guidance | CALCULATED (rule) | Barangay | Live; **thresholds not sourced (H1)** |
| Advisories | RECORDED (PAGASA / MAO) | Municipality or barangay | Every 15 min; 6 h life for PAGASA |
| Farm area, barangay, farm type | PROFILE | Farmer | When edited |
| Role / farmer / organization counts | DATABASE | Municipality | Live (short cache) |

## 5. Repair plan (your 8 controlled phases)

| Part | Work | Items from this audit |
|---|---|---|
| 1 | Audit only | **Done (this document)** |
| 2 | Core database accuracy | C4 purge (needs your OK), H2 barangay names (you correct), M4 reference table dates, M7 labels |
| 3 | Weather and real time | H3 timezone, H4 coordinates, C3 for weather charts, L2 |
| 4 | Rice prediction accuracy | C1, C2, C3 for prediction inputs, M1 save prediction details |
| 5 | Analytics and formulas | M3, M5, M6, M7 one shared farmer-count definition |
| 6 | Frontend cleanup | M2, L1, remaining "missing → 0" in views |
| 7 | Cross-system checks | Farmer ↔ MAO ↔ IT ↔ Reports counts; farmers see only their own data |
| 8 | Tests and final report | Tests for each fix; trace sample values from source to screen |

## 6. What needs your decision or your data

1. **Approve the purge** of the 20 sample rows (C4). Without it, C1 and C2 stay open.
2. **Correct the 9 barangay names** (H2). I can export the list.
3. **Methodology (H1):** the risk weights and exposure values, and the planting-advice rain bands, need a real source (for example MDRRMO or PAGASA). I will not invent them.
4. **Recent weather for the model (C1):** confirm that the Ambulong monthly file is the right source for "recent months".
5. **Heavy-rain rule:** the dashboard calls 25 mm a day "heavy rain". Please confirm with the MAO.

## 7. What this audit did not cover yet

I inspected the farmer, MAO, IT and prediction paths in detail. I only scanned (did not trace line by line) the community feed, messaging, reports export, the AI assistant text, and the unit labels on the reports. Those belong to Parts 7 and 8. The IT "Notifications sent" recount (L4) was not investigated.
