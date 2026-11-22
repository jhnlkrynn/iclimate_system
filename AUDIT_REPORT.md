# iClimate Full Test & Audit Report

**Date:** 2026-11-22 to 2026-11-23 · **Code:** branch `ui` at `d7f5da24` (same as `main` plus the PR #75 merge), the copy served on
http://127.0.0.1:8001 · **Machine:** Windows 11, PHP 8.5.8, Python 3.12.10 (project `.venv`), Node + Playwright 1.64.1 (Chromium),
local MariaDB 10.4.32 (XAMPP).

**How this was done.** Every number below comes from a run on this machine during the audit. Nothing was estimated or copied
from earlier reports without being re-run. No application code was changed. New tests were added (listed in §1.5). The real
database was only read, never written. The MariaDB migration test used a throwaway database (`iclimate_audit_tmp`), which was
deleted afterwards. The browser and load tests used the project's isolated SQLite test server (`tests/Browser/serve.sh`) or
only read public pages.

---

## Verdict: **NO, not ready for real farmers yet.**

The web application around the models is in good shape:
- access control, CSRF, input validation and query performance all hold up;
- the dashboard arithmetic is correct;
- about 2,160 automated tests pass.

But the thing farmers would rely on most, **the "expected harvest" estimate, is not what the app says it is, and the current
data cannot produce it at all.**

1. **Not farm-specific.** The deployed yield model has no barangay, farm-type or irrigation input. Farm size barely matters:
   0.1 ha and 20 ha both get 3.87 t/ha. Every farm in Lian gets the same t/ha for the same month. The "tons" figure is that
   number × farm size.
2. **Not validated.** The model was trained on about 48 rows, and its training script and data are lost. On the only 29 real
   MAO records it scores R² 0.146 (MAE 0.51 t/ha). Its predictions span 3.30–4.03 t/ha; the real yields span 2.38–4.68.
3. **Not working on the current data.** Since the 2026-11-20 fix, the app has refused 3,207 times to make an estimate,
   because the monthly weather for Sept–Oct 2026 is missing (`climate_records` has 0 rows). All 6,498 stored estimates
   date from before the fix and lie between 4.11 and 4.18 t/ha.
4. **The test database is mostly demo farmers.** 713 of 872 farmer accounts come from a demo import, and the sample-data
   filter does not remove them, so MAO and barangay figures are built mostly on fabricated farms.
5. **The monthly weather forecast is no better than the long-term average for rainfall.** It also retrains on every
   request, which takes 4.6–9.5 s, but Laravel only waits 5 s.

Fix items 1–4 (see the improvement list at the end) and this becomes a "yes, as a pilot".

---

## Fixes applied after the audit (2026-11-23)

The owner's instruction was to work through the fix order **without changing the model or the heat map**. So nothing
in `python_scripts/`, `storage/models/`, or the heat-map/risk-map code was changed.

| Finding | Status | What was done |
|---|---|---|
| C-1 wording | **Fixed** | The panel text and the Predictions page (en + fil) now say the estimate is Lian-wide, the same per hectare for every farm, does not use barangay or farm type, and has not been checked against enough real harvests (`lang/*/farmer.php` `kpis.detail.expected_how`, `harvest_how`, `predictions.yield_profile`). |
| C-1 model / H-3 reproducibility | **Not done** | These need the model to be retrained. That is excluded by the owner's instruction. |
| C-2 missing weather | **Fixed for now** | `php artisan iclimate:fetch-climate-archive` loaded 142 months (2015-01 to 2026-10) into `storage/app/climate/open-meteo-archive-monthly.json`. The yield model's past-month inputs are complete again, and a real farmer's estimate runs (4.09 t/ha for 2027-03 instead of "not produced"). **Still to do:** this copy's scheduler must run so the 01:10 job keeps the file current (the Windows autostart runs an old copy, see L-4). The stale pre-2026-11-20 `yield_predictions` were left alone because they feed the heat map. |
| H-1 demo farmers | **Waiting for owner decision** | Deleting the 713 demo accounts is permanent, and it would change heat-map figures (they count farms). Not done without confirmation. |
| H-2 monthly forecast | **Not done** | Model code, excluded. |
| H-4 dump in git | **Fixed** | `.gitignore` now ignores `/storage/backups/`, `/*.sql`, `*.sql.gz` and `*.dump`. The empty root `backup-2026-11-22.sql` was removed. |
| M-1 null climate value → 500 | **Not done** | It is inside the ML service, so excluded. |
| M-2 refused input looks like an outage | **Fixed** | `PythonService::farmingAssistant` reports a 4xx as "The Farming AI API refused the input: …", does not re-run it through local `predict.py`, and does not count it as a service failure. Covered by 2 tests. |
| M-3 silent errors | **Fixed outside the model / ML / heat map** | A fresh scan found 381 silently handled failures (140 `rescue(..., false)`, 241 catch blocks that neither log, report nor rethrow). **177 fixed in 83 files**: they now call `App\Support\SilentFailure::log()`, which writes one warning per place and error type per hour. The return values and fallbacks are unchanged, so nothing users see changes. **204 skipped**: 186 touch the model, the ML service or the heat map (by file, or because the surrounding code deals with yield, predictions, risk, the heat map, forecasts or models), and 18 catch a specific exception that is normal flow (validation, lock timeout, backup already reported, …). Covered by `SilentFailureTest`. |
| M-4 capacity | **Not done** | Needs the production server (php-fpm workers) and a multi-worker ML service (model code). |
| M-5 risk validation | **Not done** | Heat-map / risk logic, excluded. |
| M-6 Filipino | **Fixed** | All 130 missing strings were translated (`lang/fil/rice_varieties.php`, `lang/fil/user_deletion.php`). The new `TranslationParityTest` fails if any English string or `:placeholder` lacks its Filipino counterpart, in any language file. |
| M-7 certifi | **Fixed** | `.venv` now has `certifi 2026.10.18`, and `pip-audit` reports no known vulnerabilities. Only certifi was installed (`--no-deps`), so the model's libraries are unchanged. |
| M-8 stale panels | **Fixed** | The panels moved into a dashboard slot (`dashboards/farmer/panels.blade.php`, `FARMER_SLOTS` + `panels`). The timed refresh now updates them with the cards, and an open panel shows the new numbers at once. |
| L-1 dead code | **Not done** | It is model scripts and model files, so excluded. |
| L-2 frontend test | **Fixed** | Added the `.js` extensions (`farmer-advisories.js`, `live-page.js`) and guarded a `window` assignment (`dashboard-realtime.js`). Updated the out-of-date test fakes. `npm run test:js` now runs `tests/Frontend` too: **31 / 31 pass**. |
| L-3 Playwright on Windows | **Fixed** | `playwright.config.mjs` passes the port through `env`. `npx playwright test` starts its own server on Windows when Git Bash and PHP 8.2+ are on the PATH. |
| L-4 local environment | **Partly fixed** | **Log flood fixed:** the live-update server (Laravel Reverb) was never started locally, so every queued live update failed against port 8080 with a full stack trace (1,826 `Pusher error` entries). `scripts/iclimate-services.ps1` now starts Reverb (as production does, `deploy/hostinger/iclimate-reverb.service`) when `BROADCAST_CONNECTION=reverb`, and the stale package list was regenerated (`php artisan package:discover`) so the `reverb:*` commands exist. No new errors were logged afterwards. **Windows autostart:** switched to this copy (task `iClimate Services`); the old copy's task was removed. **MariaDB root password:** not changed, see the steps below. **MariaDB 10.4 (local) vs 10.11 (production):** not upgraded; all 109 migrations and the MySQL-only tests pass on 10.4, but production behaviour should be confirmed on 10.11 before release. |
| L-5 framework upgrades | **Deferred** | After launch, as recommended. |
| L-6 closed map ring | **Fixed** | `FarmBoundaryMeasurementService::normalize()` drops a final point that repeats the first. It is used by both save paths (`FarmController::saveMap`, `FarmBoundaryController::save`). |
| L-7 `.env.example` | **Fixed** | `SESSION_SECURE_COOKIE=true`, with a comment. |

**MariaDB root password (for the owner to do).**
1. Set it: `C:\xampp\mysql\bin\mysqladmin.exe -u root password "NEW_PASSWORD"` (or run
   `ALTER USER 'root'@'localhost' IDENTIFIED BY 'NEW_PASSWORD';` in phpMyAdmin).
2. Update phpMyAdmin: in `C:\xampp\phpMyAdmin\config.inc.php`, set `$cfg['Servers'][$i]['password'] = 'NEW_PASSWORD';`
   (it logs in with `auth_type = 'config'`, user `root`). Better: set `auth_type` to `'cookie'` so phpMyAdmin asks for
   the password. Also set `AllowNoPassword` to `false`.
3. Update the projects that log in as root: `DB_PASSWORD=` in `C:\xampp\htdocs\evenly_system\.env` and
   `C:\xampp\htdocs\iclimate_system_new\.env` (both use `DB_USERNAME=root`). Then run `php artisan config:clear` in each.
4. **Nothing to change for this project:** it logs in as `iclimate_app`, not root. The same goes for the old copy
   (`C:\xampp\htdocs\iclimate_system`). The XAMPP Control Panel's "Admin" button just opens phpMyAdmin (step 2).

**Still open (not to be done without the owner; the model and heat map are off-limits):**
- C-1 / H-3: retrain the yield model reproducibly (barangay and farm-type inputs, year hold-out, baselines, saved metadata).
- H-2: the monthly weather forecast (train offline, one implementation, honest rainfall skill).
- M-1: a null value in the climate records → HTTP 500 in the ML service.
- M-5: validate the risk levels against recorded incidents.
- L-1: unused ML scripts and model files.
- M-4: load-test the production setup (php-fpm workers, multi-worker ML service).
- L-5: framework upgrades (Laravel 13, PHPUnit 13, Guzzle 8), after launch.
- H-1: the 713 demo farmers stay, by the owner's decision (2026-11-23). They are still counted in MAO figures and barangay
  averages.
- New, seen once this copy's scheduler ran: the scheduled health check logs `ERROR iClimate health FAIL backup: no valid
  backup in the backup directory`. Point the backup directory at a real backup, or run `php artisan iclimate:backup-database`.

**Checks after the fixes:**
- Full Laravel suite (see the commit message for the final count).
- Python 121 passed, 3 xfailed (unchanged, since the ML code was not touched).
- JavaScript 31 / 31.
- Browser: farmer flows, signed-in page sweep, top bar and mobile navigation, at both sizes: 20 passed, 2 skipped (single-size checks).

---

## 1. Test results

### 1.1 Summary

| Testing type | What ran | Passed | Failed | Skipped / expected fail |
|---|---|---|---|---|
| **Unit** | Laravel `tests/Unit` (194, incl. 10 new) | 194 | 0 | 0 |
| | Python `python_scripts/tests` (124, incl. 53 new) | 121 | 0 | 3 xfail (= 3 confirmed defects) |
| | JavaScript `npm run test:js` | 26 | 0 | 0 |
| | `tests/Frontend/farmer-advisories.test.mjs` | 0 | **1 file cannot load** | — |
| **Integration** | Laravel `tests/Feature` on SQLite (1,604, incl. 27 new) | 1,588 | 0 | 16 (see note) |
| | Migrations + seeders on real MariaDB (throwaway DB) | 109 migrations · seed ×2 · rollback 5 / re-migrate | 0 | — |
| | DB-specific + audit tests on MariaDB | 35 | 0 | 1 |
| | Laravel ↔ Python failure paths (faked HTTP) + live check vs running service | 17 | 0 | 0 (live check run with `ICLIMATE_ML_LIVE_URL`) |
| | ML service over HTTP (good / bad / unauthorised input, 2026-11-22) | 8 | 0 | 0 |
| **System (E2E)** | Playwright, desktop 1280 px + phone 375 px (206, incl. 8 new) | 188 | 0 | 18 (see note) |
| | Signed-out access to every protected page (121 routes) | 121 | 0 | — |
| | Broken-link crawl (farmer 38 pages, MAO 142 pages) | 180 | 0 | — |
| **Performance** | Query count / N+1 (10 main pages, 1× vs 15× data) | 10 | 0 | — |
| | Load test 10 / 50 / 100 users (4 endpoints) | 12 runs, **100 % success** | 0 errors | — (latency below) |
| **Security scans** | `composer audit` / `npm audit` / `pip-audit` | 0 / 0 / 0 advisories in the lock files | — | 1 in the *installed* `.venv` (certifi) |

**Skips explained:**
- **Laravel (16).** 15 need Linux file permissions, a Unix shell or MySQL/MariaDB, and cannot run on Windows/SQLite. The 5
  MySQL-only ones were then run on MariaDB and passed. The last skip is the opt-in live-ML check.
- **Playwright (18).** 16 run at one screen size only by design (for example, the phone menu is phone-only). The other 2
  (`prediction-trace`) need a separately configured ML acceptance service.

### 1.2 Unit testing

**Laravel.** The 184 existing unit tests pass. They already cover:
- days to harvest and crop progress;
- expected harvest = t/ha × known farm size, with no total for an estimated size;
- the risk thresholds;
- seasons and the climate windows.

New: `tests/Unit/Audit/FarmBoundaryMeasurementTest.php` (10 tests) checks the farm size taken from the map:
- a 100 × 100 m field = 1.000 ha (±0.5 %) with a 400 m perimeter;
- a 250 × 100 m field = 2.5 ha;
- an L-shape = 0.75 ha;
- the area is the same whichever direction the points go;
- bow-ties, collinear points, fewer than 3 points and repeated points are refused.

**All pass: the map area is accurate.**

**Python** (`python_scripts/tests/test_audit_edge_cases.py`, 53 tests). These check:
- model loading, with a SHA-256 fingerprint;
- that the feature order matches `feature_names_in_`;
- that the model path is restricted;
- that zero or negative farm size, missing or null features, NaN/∞/−999/text/booleans, impossible weather, month 0/13/6.5,
  and unknown seasons are refused;
- determinism, plausible output range and uncertainty;
- monthly-record grouping, season calendar, "not ready" with fewer than 4 months, and far-future targets;
- the API returning 422 for bad input.

All pass except **3 expected failures**, each a confirmed defect:
- `farm_type` does not change the estimate (finding C-1);
- a null value in `climate_records` raises `TypeError`, and the API returns **500 instead of 422** (finding M-1, two tests).

"Unknown barangay" cannot be tested as a failure: the model has no barangay input, so any barangay gives the same answer.

### 1.3 Integration testing

**Laravel ↔ database.**
- All 109 migrations run on MariaDB 10.4 from empty in 30 s.
- `db:seed` runs twice without duplicates.
- The last 5 migrations roll back and re-apply cleanly.
- The 5 MySQL-only schema tests (triggers, JSON columns, fetch times) pass on MariaDB.
- Relationships and seeders are also covered by the 1,604 feature tests on SQLite.

**Laravel ↔ Python** (`app/Services/PythonService.php`; HTTP to FastAPI, with a local `predict.py` fallback for rice yield).
New: `tests/Feature/Audit/PythonServiceFailureTest.php` (17 tests) covers a good answer, the service being down, a timeout,
500, 422, invalid JSON, "not ready", non-numeric or missing predictions, and no URL configured.

Every failure turns into either `null` (monthly forecast → PHP fallback) or a clear `RuntimeException`. No wrong number is
ever passed on. The live check against the running service on :8010 passes.

**External APIs (weather).** These are covered by existing tests: `PagePerformanceTest` (provider failures do not block pages,
and no retry storms), `BarangayCurrentWeatherTest`, `AdvisoryWeatherTest`, `FarmerDashboardWeatherTest` and others. All pass.

### 1.4 System testing (end-to-end)

The Playwright suite covers both roles at desktop and phone sizes:
- sign-in and sign-up, account approval and rejection, recovery codes, account deletion;
- add/edit farm with associations, rice varieties, heat-map layers, the MAO lists on phones;
- strict CSP with no blocked scripts, and no page errors or sideways scrolling on any signed-in page.

New: `tests/Browser/audit-farmer-flows.spec.mjs` (4 flows × 2 sizes, all pass):
- **A1** add a farm, draw it on the map (4 clicks), check the server-measured size is saved, then remove the farm;
- **A2** every dashboard card opens only its own summary panel, and the X, the overlay and Esc each close it;
- **A3** the calendar, weather & predictions, advisories and heat map pages open with no errors or sideways scrolling;
- **A4** a farmer is turned away from MAO pages.

**Role access.** New: `tests/Feature/Audit/AuthorizationAuditTest.php` (6 tests, 147 assertions):
- a farmer cannot view, edit, map, un-map, make primary, remove, restore or select another farmer's farm (all return 404,
  and nothing changes);
- another farmer's farm never appears on my pages;
- every MAO-only page refuses a farmer, and every farmer-only page refuses MAO staff (a sweep over all role-protected routes);
- signed-out visitors are always redirected.

**All pass.**

### 1.5 Performance testing

**Query count.** New: `tests/Feature/Audit/QueryCountAuditTest.php`. Cold cache, 1 farm vs 15 farms × 4 plans (or 1 vs 16
farmers):

| Page | Queries (small → large data) |
|---|---|
| Farmer dashboard | 39 → 35 |
| My Farm | 40 → 37 |
| Weather & predictions | 32 → 31 |
| Calendar | 40 → 37 |
| Advisories | 38 → 29 |
| MAO dashboard | 26 → 24 |
| Farmer approvals | 4 → 4 |
| Farmer profiles | 8 → 6 |
| Barangays | 7 → 5 |
| Associations | 7 → 5 |

**No N+1: no page's query count grows with the data.** 30–40 queries on a cold farmer page is acceptable, and the dashboards
are cached afterwards.

**Model timing** (warm process; models in `storage/models`):

| | Time |
|---|---|
| Import sklearn/pandas (service start) | 2.60 s |
| Load `rice_yield_model_final.pkl` / `weather_model.pkl` | 22.6 ms / 0.5 ms |
| One prediction (weather + rice + uncertainty), 50 calls | median 114 ms, p95 152 ms |
| **Monthly weather forecast** (retrains on every request) with 24 / 60 / 120 months | **7.6 s / 4.6 s / 9.5 s** |

**Load tests** (`loadtest.py`, 2 requests per user; 100 % of requests succeeded in every run):

| Endpoint | 10 users p50 / p95 | 50 users p50 / p95 | 100 users p50 / p95 | Throughput |
|---|---|---|---|---|
| ML `/predict` (uvicorn, 1 process) | 0.52 / 0.98 s | 3.50 / 7.35 s | 12.4 / 14.0 s | 7–15 req/s |
| Farmer dashboard (signed in) | 3.49 / 3.86 s | 17.6 / 18.1 s | 36.1 / 38.0 s | 2.7 req/s |
| Weather & predictions page | 3.41 / 3.69 s | 16.9 / 17.8 s | 35.8 / 36.5 s | 2.8 req/s |
| Landing page (:8001, real data) | 3.40 / 4.45 s | 16.3 / 16.9 s | 33.6 / 37.2 s | 2.8 req/s |

Single-user page times are 0.4–0.9 s. **Important limit:** the local web servers are a single PHP process (`artisan serve` /
`php -S`), so latency rises in a straight line with users (about 0.36 s of work per request, queued). These figures show the
code's cost per request, **not production capacity**, which depends on how many php-fpm workers the server runs. That was not
available to test here.

---

## 2. Audit findings

Severity: **Critical** = wrong or missing information reaches farmers, or the core feature fails. **High** = serious data,
security or reliability risk. **Medium** = should fix before a pilot. **Low** = clean-up.

### Critical

**C-1. The "expected harvest" is not farm-specific and its accuracy is not established.**

- **Where:**
  - `storage/models/rice_yield_model_final.pkl` (inputs: `RAINFALL, TEMP_AVG, TEMP_RANGE, Area, Previous_Rainfall,
    Previous_Temp, Rainfall_6M, Temp_3M, Temp_6M, Seasonal_Rainfall, Seasonal_Temp, Season`);
  - `python_scripts/rice_yield_service.py:248` (`rice_features`);
  - the text that claims otherwise: `lang/en/farmer.php:1364-1365` and `lang/fil/farmer.php:1374-1375` (added 2026-11-22
    during the dashboard-panel work).
- **What's wrong:**
  - There is no barangay, farm-type or irrigation input.
  - Farm size has almost no effect: 0.1, 0.5, 1, 2, 5 and 20 ha all give 3.87 t/ha, and 100 ha gives 3.89. Irrigated and
    Rainfed give the same value (a pytest xfail).
  - The trees were grown from about 48 rows. The training script and data are missing; `train_rice_yield_model.py` builds a
    different feature set.
  - Re-run on the 29 usable real MAO barangay-season records: **MAE 0.510, RMSE 0.615, R² 0.146**. The predictions span
    3.30–4.03 against an observed 2.38–4.68.
  - Baselines on the same rows: leave-one-out average MAE 0.601; the barangay's own average 0.743; the same barangay last
    year 0.826 (n = 10).
  - So the model beats naive baselines, but on 29 rows from 2 years, with unproven independence from its training data.
- **Why it matters:** farmers are told the estimate is for *their farm* and uses *their barangay, size and type*. In reality
  every farm gets the same Lian-wide t/ha, and "tons" is just that × size.
- **Fix:**
  1. Now: change the wording to "a Lian-wide estimate from recent weather, not specific to your farm", and label the number
     "unvalidated".
  2. Retrain with barangay, farm type/irrigation and planting season, using a scripted, versioned pipeline and a
     time-based hold-out (train ≤ 2024, test 2025).
  3. Publish MAE/R² against the baselines above, and only show "your farm" wording if the model beats them on held-out
     seasons.

**C-2. The current data cannot produce a yield estimate, and the stored ones are stale constants.**

- **Where:** `app/Services/Prediction/*` (the climate window introduced in `4f880b7b`); the `climate_records` table (0 rows);
  `yield_predictions` (6,498 rows); `storage/logs/laravel.log`.
- **What's wrong:**
  - Since 2026-11-20, "Rice-yield prediction was not produced … missing: rainfall_6m, temp_6m, seasonal_rainfall,
    seasonal_temp … missing_months 2026-09, 2026-10" was logged 3,207 times.
  - All 6,498 stored predictions (last generated 2026-11-17, before the fix) lie between 4.11 and 4.18 t/ha (7 distinct
    values).
  - In their `model_input`, 13 of 18 features have exactly one value across all rows (for example `rainfall_6m`,
    `seasonal_temp`, `previous_*`), and `month_num` is always the same.
- **Why it matters:** farmers see either "unavailable" or an old 4.1 t/ha that was effectively a constant.
- **Fix:**
  - Add a scheduled job that loads monthly station or Open-Meteo archive weather into `climate_records` before the window
    is needed, and alert MAO/IT when a month is missing.
  - Mark or delete the pre-fix `yield_predictions` so they are not shown or used as benchmarks.

### High

**H-1. Most "farmers" in the database are demo accounts, and the sample-data filter does not catch them.**

- **Where:** `app/Support/SampleDataDetector.php:30-39` (only Lorem-ipsum seed batches in `rice_productions`,
  `climate_records`, `notifications`, `planting_advisories`); `database/seeders/FarmerDemoSeeder.php:32`.
- **What's wrong:**
  - 872 farmer accounts: 713 `@iclimate.test` (demo import), 144 `@iclimate.local` (RSBSA masterlist, placeholder emails)
    and 15 others.
  - Only 7 farmers have ever logged in.
  - `excludedIds()` returns 0 for `users`, `farms` and `farmer_planting_schedules`.
  - MAO totals and barangay benchmarks include the demo farms; for example, San Diego's barangay estimate says
    `farms_used: 68`.
- **Why it matters:** MAO decisions and the "your barangay average" comparisons rest on invented farms.
- **Fix:** before go-live, delete accounts with `FarmerDemoSeeder::EMAIL_DOMAIN`. Alternatively, add an `is_demo` flag and
  apply `SampleData::without` to `users`, `farms` and `farmer_planting_schedules`.

**H-2. The monthly weather forecast retrains on every request, is slower than Laravel waits, and is no better than the
average for rainfall.**

- **Where:** `python_scripts/monthly_weather_random_forest.py:272` (trains inside `predict`); `app/Services/PythonService.php:34-35`
  (timeout capped at 5 s); `app/Services/MachineLearning/MonthlyWeatherRandomForest.php` (2,303-line PHP copy);
  `docs/ml-accuracy-evaluation/phase-34-weather-forecast-optimization.md:23-29`.
- **What's wrong:**
  - Measured 4.6–9.5 s per request, against Laravel's 5 s limit, so the service often times out and Laravel silently uses
    the PHP copy, which has to stay identical.
  - Rainfall accuracy: 5-year MAE 84.34 mm vs monthly climatology 83.02 mm (worse); RMSE is better by only 0.22 mm.
  - The 2025 "improvement" comes from a blend weight (0.25 RF + 0.75 climatology) that was chosen and then reported on the
    same 2025 data.
- **Why it matters:** the rainfall forecast adds no skill over "the usual for this month", may come from either of two code
  paths, and its claimed improvement is test-set tuning.
- **Fix:**
  - Train offline on a schedule, save the model, and only predict per request.
  - Keep one implementation, or add a parity test.
  - Report rainfall honestly as "about the monthly average", or show climatology itself.
  - Choose blend weights on a validation period that comes before the test year.

**H-3. The ML work is not reproducible.**

- **Where:** `python_scripts/train_rice_yield_model.py:105`, `compare_yield_features.py:71` and `evaluation_service.py:135`
  (`KFold(shuffle=True)` on yearly/seasonal data); `requirements.txt` vs `python_scripts/requirements.txt`; `storage/models/*.pkl`.
- **What's wrong:**
  - The deployed model cannot be retrained from the repo: the script and data are missing.
  - Shuffled K-fold on time-ordered data leaks future seasons into training, which inflates the scores those scripts report.
  - The two requirements files pin different versions (numpy 2.4.6 vs 2.5.3, pandas 3.0.5 vs 3.0.6, scipy 1.17.1 vs 1.18.1),
    and the installed `.venv` matches neither exactly.
  - The pickles carry no saved scikit-learn version and need runtime compatibility shims (`install_sklearn_compatibility_shims`).
  - Random seeds *are* fixed (`random_state=42`).
- **Fix:**
  - One `requirements.txt`, installed with `pip install -r`.
  - Commit the training script and a data snapshot (or its export command plus hash), and use `TimeSeriesSplit` or a
    year-based hold-out.
  - Save a sidecar JSON with each model: sklearn version, feature list, training rows, date range and metrics.

**H-4. A real database dump can be committed by accident.**

- **Where:** `.gitignore` (no `storage/backups/`); `storage/backups/iclimate_staging_20261103_fix3_before_it_removal_20261121_230559.sql`
  (46 MB, untracked); `backup-2026-11-22.sql` (an empty file committed at the repo root by `e72da3c1`).
- **What's wrong:** the dump holds farmer names and RSBSA numbers and sits untracked but not ignored. One `git add .` would
  publish it to GitHub.
- **Fix:** add `/storage/backups/` and `/*.sql` to `.gitignore`, remove `backup-2026-11-22.sql`, and keep dumps outside the
  working tree.

### Medium

**M-1. A null value in the monthly climate records crashes the forecast with HTTP 500.**

- **Where:** `python_scripts/monthly_weather_random_forest.py:88` (`float(r["rainfall"])` on `None`); `python_scripts/main.py:166`
  (only catches `KeyError`/`ValueError`).
- **Proof:** 2 pytest xfails.
- **Fix:** validate the records with pydantic (non-null floats) or catch `TypeError` → 422.

**M-2. Refused input looks like an outage.**

- **Where:** `app/Services/PythonService.php:106-123`.
- **What's wrong:** a 422 from the service is reported as "unavailable or returned an error", and for rice yield it re-runs
  the same bad input through local `predict.py`.
- **Proof:** `PythonServiceFailureTest::test_predict_bad_input_rejected_by_the_service_is_reported_as_unavailable`.
- **Fix:** treat 4xx as invalid input (no fallback, with a specific message).

**M-3. Errors are swallowed silently.**

- **Where:** across `app/`. There are 134 `rescue(..., false)` calls and 96 `catch (Throwable)` blocks that drop the exception.
- **Why it matters:** for example, `FarmerHomeKpis` tiles simply disappear when a query fails, with nothing in the log.
- **Fix:** pass `report: true`, or log with context, where a missing figure would hide a real fault.

**M-4. Capacity is unproven.**

- The ML service runs as one process. At 50+ concurrent predictions the median goes past Laravel's 5 s timeout (3.5 s
  median and 7.4 s p95 at 50 users; 12.4 s at 100).
- The local PHP server manages 2.8 req/s.
- **Fix:**
  - Run uvicorn with several workers. That first needs the model-switch endpoint to work across processes (reload from a
    shared model file).
  - Load-test the real php-fpm setup at 50 and 100 users before launch.

**M-5. The risk level has never been checked against real events.**

- **Where:** `app/Services/Risk/BarangayWeekRiskService.php:216-242` (rule thresholds).
- **What's wrong:** it is rule-based, not ML, so precision/recall cannot be computed. No record of actual floods, wind
  damage or heat stress per barangay exists to check it against.
- **Fix:** log MAO-confirmed incidents per barangay and week, then measure hit and false-alarm rates for each level.

**M-6. 130 Filipino strings are missing**, so they show in English in Filipino mode.

- **Where:** `lang/fil/rice_varieties.php` (farmer-facing pests, diseases, types and catalog: 23 strings; IT/MAO screens:
  81) and `lang/fil/user_deletion.php` (26).
- **Fix:** translate them, and add a test that the key sets match (the existing parity test only covers `farmer.kpis`).

**M-7. Environment drift in the ML venv.**

- `certifi 2026.7.22` is installed and has known vulnerability **PYSEC-2026-4320**; the requirements already pin the fixed
  2026.10.18.
- **Fix:** run `.venv/Scripts/pip install -r requirements.txt`.

**M-8. Dashboard panels can go stale.**

- **Where:** `resources/views/dashboards/farmer.blade.php` (the panel sections are built once per page load).
- **What's wrong:** the timed refresh updates the cards but not the summary panels, so a panel can show older numbers than
  its card until the page reloads.
- **Fix:** render the sections inside the `kpis` slot again, or refresh the drawer with the slots.

### Low

- **L-1. Dead code.**
  - 9 Python scripts are referenced nowhere (`compare_models.py`, `experimental_ml_optimization.py`,
    `phase_29_independent_2026_evaluation.py`, `phase_30_robust_forecasting.py`, `predict_candidate_full_2025/2026.py`,
    `test_candidate.py`, `validate_candidate_2025.py`, `generate_smoketest_data.py`).
  - 3 candidate models and 5 experiment models in `storage/models` are unused.
  - `docs/` holds 137 Markdown files (about 19,800 lines), many of them superseded audits.
  - **Fix:** move experiments to an `experiments/` folder or delete them, and archive the old docs.
- **L-2.** `tests/Frontend/farmer-advisories.test.mjs` cannot load: `resources/js/farmer-advisories.js` imports `./live-page`
  without `.js`. **Fix:** add the extension and add the file to `npm run test:js`.
- **L-3.** `playwright.config.mjs:28`: the `webServer` command uses POSIX shell syntax, so `npm run test:browser` cannot
  start on Windows. **Fix:** use `cross-env`, or a Node script that starts `serve.sh` through bash.
- **L-4.** Local environment:
  - XAMPP MariaDB `root` has no password;
  - local MariaDB 10.4 vs production 10.11;
  - port 8000 is auto-started from an old copy (`C:\xampp\htdocs\iclimate_system`, 2026-10-08) that answers 503;
  - the local log is flooded with Reverb timeouts (1,177 on 2026-11-20).
  - **Fix:** set a root password, align the versions, point the autostart at the current copy, and set
    `BROADCAST_CONNECTION=null` locally.
- **L-5.** Framework majors are behind (Laravel 12 → 13, PHPUnit 11 → 13, Guzzle 7 → 8). There are no security advisories;
  plan the upgrade after launch.
- **L-6.** `FarmBoundaryMeasurementService.php:16` refuses a ring closed by repeating its first point and shows the "lines
  cross" message. The map page never sends that, but an API client could. **Fix:** drop a trailing point equal to the first.
- **L-7.** The root `.env.example` has `SESSION_SECURE_COOKIE=false` with `APP_ENV=production`. The deploy script already
  refuses `false`, but the example misleads. **Fix:** set it to `true` in the example.

### What is solid (checked, no finding)

- **Authorization.** There is no IDOR on farms or planting plans. Every role-protected page refuses the wrong role. All 121
  protected routes redirect signed-out visitors.
- **Injection and XSS.** There are no raw-SQL interpolations. Every `{!! !!}` prints fixed or pre-escaped HTML. There are no
  open `$guarded = []` models.
- **CSRF and rate limits.** Login is rate-limited (`LoginRequest`), and the public APIs are throttled (the risk forecast
  returns 429 after 25 requests per minute).
- **Uploads.** Photos: image, jpg/png/webp, ≤ 2 MB, ≤ 4000 px. Attachments: MIME allow-list, ≤ 50 MB, ≤ 6 files.
- **Secrets.** No secrets are in tracked files. The deploy SQL files use placeholders. Staff passwords come from the
  environment and are hashed.
- **Dependencies.** `composer audit`, `npm audit` and `pip-audit` (both requirements files) all report 0 advisories.
- **ML input validation.** Missing or null values become errors, never zero; impossible values and bad seasons or months
  are refused; model files are limited to `storage/models/*.pkl` and checked by SHA-256; model switching needs a token.
- **Dashboard arithmetic** (`DashboardDataCorrectnessTest`). Farm size, planting and harvest dates, days left, crop progress
  and tons = t/ha × size on the page all match the database and the model output, and an estimated size never produces a
  total.
- **Database.** All migrations, seeders and rollbacks work on MariaDB. There are no harvest-before-planting plans and no
  zero-size measured farms in the data.
- **UX.** No broken links (180 pages crawled). There is no sideways scrolling and there are no page errors on any signed-in
  page at 375 px or 1280 px.

---

## 3. Improvement list (in this order)

1. **Make the yield estimate honest now** (C-1). Change the wording to "Lian-wide, unvalidated", and hide the "tons" tile or
   label it as approximate.
2. **Feed the model real monthly weather** (C-2). Load monthly climate data on a schedule, alert when a month is missing,
   and purge the stale pre-2026-11-20 predictions.
3. **Remove or flag the 713 demo farmers** (H-1) so MAO figures and barangay averages use real farms only.
4. **Rebuild the yield model reproducibly** (C-1, H-3). Write a training script in the repo, use barangay, farm type and
   season as inputs, hold out by year, compare against the baselines, and save a metadata file. Only then call it
   "your farm's estimate".
5. **Fix the monthly forecast** (H-2). Train offline, serve the saved model, keep one implementation, and evaluate rainfall
   against climatology without test-set tuning.
6. **Stop a data leak** (H-4). Add `storage/backups/` and `*.sql` to `.gitignore` and remove the root `.sql` file.
7. **Robustness:** null-safe climate records → 422 (M-1), 4xx ≠ outage (M-2), log swallowed errors (M-3), update
   `certifi` (M-7).
8. **Capacity:** multi-worker uvicorn, then a load test on the real php-fpm setup at 50 and 100 users (M-4).
9. **Risk validation** (M-5): start recording real incidents per barangay.
10. **Polish:** Filipino strings (M-6), panel refresh (M-8), and the Low items.

---

## 4. How to re-run

```bash
# Laravel unit + feature (SQLite in memory), ~40 min
php vendor/bin/phpunit
# only the audit tests
php vendor/bin/phpunit tests/Unit/Audit tests/Feature/Audit
# optional live ML check
ICLIMATE_ML_LIVE_URL=http://127.0.0.1:8010 php vendor/bin/phpunit tests/Feature/Audit/PythonServiceFailureTest.php
# Python (from the project root)
.venv/Scripts/python -m pytest -q python_scripts/tests
# JavaScript
npm run test:js
# Browser (Linux/macOS; on Windows start tests/Browser/serve.sh from Git Bash first, see L-3)
npm run build && npm run test:browser
# MariaDB-specific tests: create an empty database, then
DB_CONNECTION=mysql DB_DATABASE=<empty db> DB_USERNAME=... DB_PASSWORD=... php vendor/bin/phpunit tests/Feature/TimestampRefreshMigrationTest.php tests/Feature/Audit
```

The load-test, link-crawl, baseline and timing scripts used for §1.5 and C-1 were one-off scripts run from a scratch folder
and are not part of the repo. The numbers above are their output.
