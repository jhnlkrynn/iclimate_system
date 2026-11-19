# iClimate — API and ML verification (2026-11-18)

**For:** the project owner, the thesis panel and whoever deploys or maintains the ML service.
**Code verified:** the tagged release `v2026.11.18-rc1` (commit `d43cd1aa…1170`) in a separate, unmodified checkout.
Another agent is editing the shared working tree at the same time; nothing here depends on, or changes, its work.
**Environment:** PHP 8.4.27 + MariaDB 10.11.19 (the Ubuntu 24.04 versions) with the real-data copy of the database,
RC1's own ML service (port 8011), its own Reverb, mail to the log only. Not the production VPS (none exists yet).

## 1. Verdicts

| Area | Verdict |
|---|---|
| Laravel JSON APIs (24 endpoints) | **PASS** — correct status, JSON, auth, role isolation and validation for every endpoint tested |
| Python ML service (7 endpoints) | **PASS** — health, model info, predict, metrics, analytics, forecast, model switch; bad input refused |
| Laravel → Python → result → gate → dashboard | **PASS** — one prediction traced end to end; numbers identical at every hop |
| Model artifact and feature order | **PASS** — the expected file, the 12 trained features in the trained order |
| Fallback inference (ML service down) | **PASS in result**, **FAIL in validation (RC1)** — see defect 1 |
| Weather integration | **PASS** — live call works; an unreachable API degrades to the last weather with a notice |
| Prediction input pipeline | **FAIL** — 8 of 12 features are frozen at one value for every farm and month (defect 2) |
| Model accuracy | **NOT VERIFIED** — no independent, verified observations; an indicative check is in section 5 |
| Production deployment | **NOT VERIFIED** — no VPS exists |

## 2. API inventory and tests

### Laravel (24 JSON endpoints, RC1)

Public, all rate-limited: boundaries, risk map (all / one barangay), risk forecast, current weather, barangay weather,
login weather, captcha, mobile login / register / password / forgot-password, health (`/up`).
Signed in: heat-map layers, live weather, Farmer and MAO dashboard live + weather, notifications feed, rice-variety
harvest estimate, analysis pages, yield-request status, Reverb channel authorization.

Tested as guest, Farmer and MAO Staff against the running copy:

| Result | Endpoints |
|---|---|
| 200 JSON for everyone | boundaries, risk map, risk forecast, weather current / barangays, login weather |
| 401 JSON for guests, 200 for both roles | heat map, notifications feed |
| 401 guest · 200 Farmer · **403** MAO | Farmer live weather, Farmer dashboard live / weather |
| 401 guest · **403** Farmer · 200 MAO | MAO dashboard live / weather |
| Harvest estimate | 200 with a valid date; **422** with an invalid one |
| Validation (6 mobile POSTs) | **422** with field errors: empty login, bad email, wrong password, incomplete registration, bad recovery email, empty password change |
| Unknown barangay | **404** JSON |

### Outbound integrations

Open-Meteo (forecast and archive), WeatherAPI, PAGASA advisories, GeoRisk, Groq (AI assistant), and the internal
FastAPI ML service. Live Open-Meteo call: 28.6 °C in 1.6 s. With the weather API unreachable: no exception, 0.28 s,
"Weather update is delayed. Showing the last available weather."

### ML service (RC1 code)

`scripts/deploy/ml-activation-check.sh` — 11/11 ML checks OK: health, model checksum `f3a54e12…b777c`, production
input accepted, area −5 / 0, 60 °C and 140 % humidity refused (422), three path-traversal attempts on `/metrics`
refused (422), default metrics answer. Also: `/model/switch` refused without or with a wrong token (403); unknown
analytics task and malformed monthly-forecast records refused (422); weather model answers (Rain, 175.7 mm).

## 3. One prediction, end to end

Farmer #1284, primary farm (0.5 ha), through the application:

| Hop | Value |
|---|---|
| Laravel builds the input | 12 features + context (`model_input`) |
| HTTP `POST /predict` to RC1's ML service | 200 OK |
| Service result | **4.17** t/ha |
| Same input straight into the model in Python (`model.predict`) | **4.172240** t/ha |
| Laravel raw value, before the gate | **4.17** t/ha |
| Yield gate | **withheld**: area, `rainfall_6m` and `temp_range` are outside the training range |
| What the Farmer sees | no number; "No harvest estimate yet…" |

No hard-coded, simulated or fallback number appears anywhere in the chain. When the ML service is down, Laravel runs
the same model locally (`predict.py`); valid inputs give the same 4.17.

## 4. Model artifact

`storage/models/rice_yield_model_final.pkl`: scikit-learn 1.6.1 Pipeline (preprocessor → RandomForestRegressor, 300
trees). `feature_names_in_` = RAINFALL, TEMP_AVG, TEMP_RANGE, Area, Previous_Rainfall, Previous_Temp, Rainfall_6M,
Temp_3M, Temp_6M, Seasonal_Rainfall, Seasonal_Temp, Season — exactly what the service sends, in that order. The trees
were grown from about 48 rows (25–38 per bootstrap) and every leaf lies between 1.76 and 4.70 t/ha.

## 5. Accuracy

**No independent, verified observations exist.** `rice_productions` has 0 verified rows (110 of 124 are seeded
allocations); the deployed model's training data and script are missing, so no set can be shown to be outside its
training data.

Indicative check only, labelled as such: the deployed model was run on the 40 real MAO barangay-season records in
`historical_rice_productions` (source `RICE-PRODUCTION (LIAN).xlsx`, 2024–2025), with real Ambulong monthly weather.
29 rows could be used (2 have no area or yield; 9 need weather after December 2025, which does not exist). Feature
definitions were inferred from the trees and are stated in the script (`docs/deployment/evidence/eval_deployed_model.py`; run: `.venv-ml/bin/python -I docs/deployment/evidence/eval_deployed_model.py . <export of historical_rice_productions>.csv`).

| | MAE | RMSE | R² | Pearson r |
|---|---|---|---|---|
| Deployed model | 0.510 | 0.615 | 0.146 | 0.457 |
| Baseline: average of the other observations | 0.601 | 0.689 | — | — |

Observed 2.38–4.68 t/ha (mean 3.53); model 3.30–4.03. The model is somewhat better than the average on these rows,
but 29 rows, unproven independence and inferred preprocessing do not establish accuracy. **ACCURACY NOT VERIFIED.**

The weather model was evaluated earlier (2026-11-12, 2024–25 backtest): worse than monthly climatology (rain MAE
119 mm vs 89 mm).

## 6. Defects

| # | Defect | Severity | Status |
|---|---|---|---|
| 1 | Local fallback `predict.py` accepted impossible inputs (−5 ha, 60 °C, 140 % humidity) that the service refuses | P2 (the yield gate withholds such values anyway) | **Fixed in the working tree by the other agent** (shared validation + parity tests); verified here: refuses them with "Out-of-range numeric feature", production input still 4.17, Python suite 69 passed. Not in RC1 |
| 2 | **Prediction inputs frozen:** in all 6,435 stored predictions, `temp_range`, `previous_rainfall`, `previous_temp`, `rainfall_6m`, `temp_3m`, `temp_6m`, `seasonal_rainfall` and `seasonal_temp` each have **one value**, and `season` is always "Dry". The lag and seasonal features come from the last station months on file (Ambulong, to December 2025), not from the months before each target month | **P1** — this, not the farm-area definition, is why every estimate is about 4.1 t/ha. A sweep of 192 input combinations with those features held fixed moves the output only 3.97–4.19; with real seasonal weather the same model ranges 3.30–4.03 and tracks the observations (r 0.46) | **Not fixed**: it changes how prediction inputs are built and needs a current monthly weather source for the lag months — an owner decision. `PredictionEngine.php` is also being edited by the other agent |
| 3 | `FarmersAssociationDirectorySeeder` used barangay "Sandiego" and matched by exact name + that barangay: on MariaDB the first `db:seed` stopped (duplicate "Tan-Ag" / "Tan-ag"); on SQLite it created a duplicate association | P1 for a fresh-database deployment | **Fixed** (official barangay name, case-insensitive name match) + `AssociationDirectorySeederTest`; fails without the fix on both engines, passes with it; `db:seed` now completes twice on MariaDB 10.11 |
| 4 | `SystemHealthCommandTest` fixture wrote text into a datetime column (rejected by MariaDB strict mode) | P3 (test only) | **Fixed**; passes on SQLite and MariaDB 10.11 |

Fixes 3 and 4 are in the working tree (uncommitted) and in no release yet; they belong in the next release candidate.

## 7. Test results (this verification)

| Suite | Environment | Result |
|---|---|---|
| Laravel, full | RC1, PHP 8.5, SQLite | 1,667 passed, 0 failed, 7 skipped (5 MariaDB-only, 2 need the ML virtualenv) |
| Laravel, full | RC1, **PHP 8.4 + MariaDB 10.11** | 1,668 passed, **4 failed** (defects 3 and 4), 2 skipped |
| Laravel, full | RC1 + fixes 3–4, **PHP 8.4 + MariaDB 10.11** | **1,673 passed, 0 failed**, 2 skipped (both need the ML virtualenv) |
| Python ML | RC1 | 53 passed, 2 warnings |
| JavaScript | RC1 | 19 passed |
| Frontend build | RC1 | OK |
| Playwright | RC1 | 166 passed, 0 failed, 40 skipped |
| Browser crawl | RC1, PHP 8.4 + MariaDB 10.11 | 564 desktop page loads (94 routes × 6 roles): 0 server errors; 220 mobile/tablet loads: 0 overflow, 0 unexpected console errors |
| Workflows | RC1, PHP 8.4 + MariaDB 10.11 | **36/36 passed**: registration → approval / rejection → resubmission; concurrent decisions; restrictions and CSRF; association reporting with a live Reverb push; 5 report downloads; shared-password recovery with a MAO code |
| API matrix | RC1, PHP 8.4 + MariaDB 10.11 | 17 endpoints × 3 roles + 8 error cases: all as expected |
| Migrations | RC1, PHP 8.4 + MariaDB 10.11 | 6/6 applied, compare all OK, rollback 6 / re-apply 6, restore drill identical |
| Fresh install | RC1 export, PHP 8.4 | `composer install --no-dev`, platform check, `npm ci && npm run build`, pinned Python packages: all OK |

## 8. Readiness

- **Functional integration:** PASS on RC1 (APIs, ML service, end-to-end prediction, weather, real-time, workflows).
- **Model accuracy:** NOT VERIFIED; the inputs are frozen (defect 2), so today's numbers carry almost no information.
  Keep `ICLIMATE_YIELD_GATE=withhold`.
- **Production deployment:** NOT VERIFIED (no VPS). Before a fresh-database rollout, include fix 3.

Recommended next steps: (1) owner decision on defect 2 — build the lag and seasonal features from the actual months
before each target month, using a current monthly weather source — then re-run section 5; (2) a release candidate
that includes fixes 1, 3 and 4, coordinated with the other agent's uncommitted work; (3) independent, verified
observations before any accuracy claim.
