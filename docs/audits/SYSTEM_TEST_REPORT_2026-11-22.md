# iClimate System Test Report — 2026-11-22

Code tested: branch `ui` at `e2e690c6` (same as `main`), which includes the farmer dashboard summary panels (`91b263e1`).
Local runs only: Windows 11, PHP 8.5.8, Python 3.12.10, Node with Playwright 1.64.1 (Chromium 1248).

No staging or production data was changed. The Laravel tests use SQLite in memory, and the browser tests use their own
throwaway SQLite database (`tests/Browser/serve.sh`). The performance tests only read pages and sent about 700 requests,
all to this computer.

## Summary

| Level | Result |
|---|---|
| 1. Unit testing | Passed: 281 of 281 |
| 2. Integration testing | Passed after two test fixes: 1,575 Laravel feature tests (3 now skipped on Windows only), 8 of 8 ML service checks |
| 3. System testing | Passed: 180 of 180 browser tests that ran, 121 of 121 protected pages refuse signed-out visitors |
| 4. Performance testing | Stable. Every request succeeded except the ones the rate limit refused on purpose. |

## 1. Unit testing — passed

| Part | Result |
|---|---|
| Laravel unit tests (`tests/Unit`: climate data, weather labels, risk rules, yield climate windows, seasons, KPIs) | 184 / 184 passed (925 assertions) |
| Python ML tests (`python_scripts/tests`: rice yield, monthly weather forecast, analytics, evaluation, model switch) | 71 / 71 passed |
| JavaScript tests (`npm run test:js`: relative time, live clock, dependency security) | 26 / 26 passed |

`tests/Frontend/farmer-advisories.test.mjs` still does not start. It imports `resources/js/live-page` without the `.js`
extension. This was already reported on 2026-11-21, and no npm script runs this test.

## 2. Integration testing — passed (after one test fix)

**Laravel feature tests (HTTP, database, services, jobs): 1,575 tests, 12,992 assertions.**

- **7 failures caused by the dashboard panel change, now fixed.** `FarmerYieldEstimateTest` found the yield card by
  looking for exactly `class="fh-card"`. The card now also has `fh-card-opens` and contains its hidden summary panel.
  The test's helper now accepts the extra class and leaves the hidden panel out of the card's visible words. After the
  fix: 15 / 15 passed. The page itself was working the whole time.
- **3 failures on Windows only, not a bug, now skipped there.** `OffsiteBackupTest` checks that the backup encryption
  key is readable by its owner only (`chmod 600`). Windows ignores `chmod`, so the key always looks readable by everyone
  (`666`), and the service correctly refuses to use it. The production server runs Linux, where this check works.
  These 3 tests now skip, with that reason, when the file system cannot make the key owner-only, and still run on Linux.
  The backup service and its security check were not changed. After the fix: 2 passed and 3 skipped on Windows.
- 12 skipped. These tests are marked to run only with a Linux server, an outside service or a database other than SQLite.

**Laravel ↔ FastAPI ML service.** This project's ML service was started on its own port and called over HTTP:

| Check | Result |
|---|---|
| `/health` | `ok`, with the `rice` and `weather` models loaded |
| Valid rice-yield request (1.5 ha, wet season) | 3.87 t/ha |
| Valid weather request | "Heavy Rain" |
| Negative rainfall | Refused (422): "rainfall must be between 0 and 3000" |
| Missing required input | Refused (422): "Missing required feature: temp_avg" |
| Unknown prediction type | Refused (422) |
| Model switch with no token, and with a wrong token | Refused (403) both times |

## 3. System testing — passed

**Browser tests (Playwright, real Chromium), at desktop 1280 px and phone 375 px widths: 180 passed, 0 failed, 18 skipped.**

They cover:
- the landing page, sign-in and sign-up;
- Farmer and MAO dashboards, the mobile navigation and the top bar;
- account requests, approval and rejection, account deletion and recovery codes;
- farms and associations, rice varieties and the heat map layers;
- the MAO lists on phones;
- strict CSP with no blocked scripts, and no page errors or sideways scrolling on any signed-in page.

The 18 skipped tests:
- 16 run at one screen size only on purpose, for example the phone menu on phones only.
- 2 runs of `prediction-trace` need a separately configured ML acceptance service (`API_ML_ACCEPTANCE=1`). That service
  is not set up here.

**Access control (signed out):**
- All 121 pages and data addresses that need a login were opened without signing in. 117 sent the visitor to the login
  page, 2 refused with 401 and 2 answered 404. None showed content.
- 13 pages are public on purpose, the same list as on 2026-11-21.
- `.env`, `.git/config` and `composer.json` return 404. `storage/logs/laravel.log` and `storage/backups/` return 403.

## 4. Performance testing — stable

All timings are from this computer. The local web servers handle **one request at a time** (`php artisan serve` and
`php -S`), so the "users at once" numbers mostly show requests waiting in line. They are not the speed of the real server,
which runs several PHP workers.

**Signed-in pages (browser-test database, one user, 5 requests each):**

| Page | Median / 95th percentile |
|---|---|
| Farmer dashboard | 0.42 s / 0.62 s |
| Farmer dashboard, timed refresh (`/farmer/dashboard/live`) | 0.50 s / 0.88 s |
| My Farm | 0.38 s / 0.63 s |
| Heat map | 0.43 s / 0.57 s |
| Weather & predictions | 0.48 s / 0.86 s |
| Planting advisories | 0.89 s / 1.50 s |
| MAO dashboard | 0.62 s / 1.02 s |
| Farmer dashboard, 5 farmers at once (25 requests) | 100% success, 2.0 s / 2.6 s |

**Public pages (local copy of the real data, one user):** landing page 0.38 s, password reset 0.76 s, sign-up 0.97 s,
current weather 0.32 s, risk forecast 0.30 s, barangay boundaries 0.46 s, barangay weather 0.35 s.

| Load | Success | Median / 95th percentile |
|---|---|---|
| Landing page, 10 at once (50 requests) | 100% | 2.9 s / 3.0 s |
| Risk forecast, 10 at once (40 requests) | 25 served, then 429 | The per-minute limit worked as designed |

**ML service (one process):**

| Load | Success | Median / 95th percentile | Throughput |
|---|---|---|---|
| 1 prediction at a time (20) | 100% | 0.19 s / 0.32 s | 4.7 per second |
| 10 at once (50) | 100% | 2.4 s / 4.0 s | 4.0 per second |
| 20 at once (100) | 100% | 4.9 s / 6.5 s | 4.0 per second |
| Health check, 20 at once (200) | 100% | 0.05 s / 0.06 s | 410 per second |

## Problems found

1. **Fixed: a test broken by the dashboard panel change.** `tests/Feature/FarmerYieldEstimateTest.php` now finds the
   yield card when it has the extra class and hidden panel. This fix is not committed yet.
2. **Still open from 2026-11-21: predictions slow down when many people ask at once.** The ML service runs as one
   process and handles about 4 predictions per second. With 20 at once, each waits about 5 s.
3. **Still open from 2026-11-21: `tests/Frontend/farmer-advisories.test.mjs` does not start** (missing `.js` in an import).
4. **Fixed: `OffsiteBackupTest` failed on Windows** because Windows ignores `chmod`. Its 3 permission-dependent tests
   now skip there and still run on Linux. This fix is not committed yet.
5. **Setup steps after pulling `main`:**
   - Two database migrations are not yet applied to the local MySQL database
     (`2026_11_21_000001_add_farm_id_and_useful_to_yield_prediction_requests`,
     `2026_11_22_000001_add_federation_fields_to_farmer_organizations`).
   - `certifi` in the ML virtual environment is 2026.7.22, but `requirements.txt` asks for 2026.10.18.
   - After `npm install`, Playwright needed Chromium build 1248 (`npx playwright install chromium`).
6. **The browser tests cannot start their own server on Windows.** The `webServer` command in `playwright.config.mjs`
   uses Unix shell syntax (`PLAYWRIGHT_PORT=… bash …`). For this run, `tests/Browser/serve.sh` was started from Git Bash
   and Playwright was given a temporary config without `webServer`.
7. **Port 8000 serves a different copy of iClimate.** The Windows sign-in autostart runs Apache from
   `C:\xampp\htdocs\iclimate_system`, which answered "Data Service Unavailable". This copy was served on port 8001 instead.
