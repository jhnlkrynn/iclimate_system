# iClimate System Test Report — 2026-11-21

Staging site: https://staging.iclimate-lian.tech/ · Local runs: Windows 11, PHP 8.5.8, SQLite in memory, Python 3.12.

No staging data was changed. The staging load test sent about 500 requests, spread over several minutes.

## 1. Unit testing — passed

| Part | Result |
|---|---|
| Laravel unit tests (climate data processing, weather labels and risk rules, yield climate windows, dashboard and farmer KPIs) | 172 / 172 passed (864 assertions) |
| Python ML tests (rice yield prediction, monthly weather forecast, analytics, evaluation) | 71 / 71 passed |
| JavaScript tests | 26 / 26 passed |

## 2. Integration testing — passed

- **Laravel ↔ FastAPI:** the FastAPI ML service was started and called over HTTP.
  - `/health` returned `ok` with the `rice` and `weather` models loaded.
  - A valid rice-yield request returned 4.04 t/ha; a weather request returned "Heavy Rain".
  - A negative rainfall value and a missing field were refused (422). Model switching without the admin token was refused (403).
- **Laravel feature tests for predictions, the Python service, and saving and reading data in the database:** 128 tests, 124 passed, 4 skipped, 0 failed. The 4 skipped tests check how the server finds Python, and are skipped on Windows.

## 3. System testing — passed

- On staging, all 118 pages that need a login were opened while signed out. Every one redirected to the login page or was refused (401/403).
- The 13 pages anyone can open are meant to be public (landing page, password reset, public weather and risk APIs). None of them return emails, phone numbers or names.
- Farmer and MAO role tests (`RoleAuthorizationTest`, `TwoRoleAccessTest` and related tests) passed: a Farmer cannot open MAO pages, and MAO cannot open Farmer-only pages.
- `.env`, `.git` and the log files are blocked on staging (403), and error responses do not show internal details.
- Not covered: signing in on staging as a Farmer or MAO, because no staging test accounts were available.

## 4. Performance testing — stable

| Page / data | Users at once | Success | Median / 95th percentile |
|---|---|---|---|
| Landing page | 10 / 25 / 50 | 100% | about 1.4 s / up to 6.2 s |
| Password reset page | 25 | 100% | 0.4 s / 1.4 s |
| Risk forecast data | 10 | 100% | 0.7 s / 1.6 s |
| Barangay boundaries (305 KB) | 10 | 100% | 5.2 s / 10.4 s |
| Risk map, 45 quick requests | 10 | 32 served, then 429 | The 30-requests-per-minute limit worked as designed |

ML service (local): about 0.2–0.4 s for one prediction. With 20 predictions at once, each took about 6.5 s on average, because the service runs as one process.

## Problems found

1. **Staging does not compress responses.** The 305 KB boundaries data and the CSS are sent at full size. Gzip was added to `deploy/hostinger/nginx-iclimate.conf` (commit `5809ee6e`) and reduces the boundaries data to about 89 KB. It still has to be applied on the staging server, followed by `sudo nginx -t && sudo systemctl reload nginx`.
2. **Predictions slow down when many users ask at once**, because the ML service runs as one process. This was not changed, because the model-switch endpoint replaces the model in one process only. Running more processes needs a change to how the model is loaded first.
3. **`tests/Frontend/farmer-advisories.test.mjs` does not start.** It imports `resources/js/live-page` without the `.js` extension, which Vite accepts but Node does not. No npm script runs this test.
4. **Deployment-script tests (14) fail on this computer** because they need a release manifest under `storage/app/backups/preflight-2026-11-18/` that is not here. They test the release tools, not the system.
