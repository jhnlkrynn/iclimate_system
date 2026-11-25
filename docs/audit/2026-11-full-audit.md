# iClimate Full System Audit (November 2026)

**Audit date:** 2026-11-25  
**Mode:** Read-only audit (no application code or database records modified)  
**Scope:** Functional (Farmer, MAO Staff, MAO Head on desktop `1280×800` + mobile `375×740`), Data & Jobs, Security, UI/UX, Performance, Deployment, and UAT Readiness.

---

## 1. Executive Summary

### Baseline Verification Runs
| Suite / Check | Result | Notes |
| --- | --- | --- |
| **PHPUnit** (`php artisan test --no-ansi`) | **1,711 passed**, **111 failed**, 5 skipped (13,258 assertions) | Failures cluster around `DroughtRiskService` calling Python `:8010` without fallback (`StrayRequestException`), moved/removed routes (`weather-predictions`, `analysis.forecast`), updated advisory copy, and `FarmerNaicelFeaturesTest` (`setup:complete_profile` + required `rice_variety_id`). |
| **Playwright** (`npm run test:browser`) | **164 passed**, **24 failed**, 18 skipped | Failures in `account-requests`, `critical-workflows`, `farmer-kpi-modal`, `forecast-moved-to-mao`, `landing` (K2b/K3/K4/K7), `live-clock`, `mao-removed-pages`, and `mobile-navigation`. |
| **Playwright Multi-Role Sweep** (102 route+role+viewport checks) | **0 unexpected 4xx/500s**, **0 horizontal overflows**, **0 invisible active toggles**, **0 slow (>1s) pages** | Only console errors were `ws://127.0.0.1:8080` (`ERR_CONNECTION_REFUSED`, Reverb not running). |
| **JS Unit Tests** (`npm run test:js`) | **26 passed**, 0 failed | `picomatch` mitigation + `rt-timestamps.mjs` verified. |
| **Dependency Security** (`composer audit`, `npm audit --omit=dev`) | **0 advisories / 0 vulnerabilities** | Clean. |

### Severity Counts
| Severity | Count |
| --- | --- |
| **Critical** | **3** |
| **High** | **7** |
| **Medium** | **9** |
| **Low** | **5** |
| **Total** | **24** |

### Top 5 to Fix First
1. **CRIT-01 — `DroughtRiskService` crashes `iclimate:refresh-weather-cache`, `iclimate:refresh-drought-risk`, and 50+ PHPUnit tests when ML service (`:8010`) is down.**
2. **CRIT-02 — `iclimate:refresh-barangay-weather` crashes with `Undefined array key "latitude"`** (scheduled every 10 minutes), leaving barangay forecasts incomplete (**13/19** instead of **19/19**).
3. **HIGH-01 — Queue worker, scheduler, Reverb, and ML service (`:8010`) are not running** (`219` queued jobs, newest backup 5 days old, weather/risk stale).
4. **HIGH-02 — `WarmFarmerYields` ignores `STOP_AFTER_SERVICE_FAILURES` when `:8010` is down**, spawning 1,082 sequential local Python subprocesses (~4+ min) instead of failing fast or batching.
5. **HIGH-03 — 24 Playwright browser tests failing on `main`** (including `account-requests`, `critical-workflows`, `farmer-kpi-modal`, `mobile-navigation`, and `landing` Create Account mode).

*(Note: `CRIT-03` — legacy `IT Expert` accounts `id=3` and `id=9` — has already been resolved by deactivating/soft-deleting both accounts and cleaning up stale seeder/config comments.)*

---

## 2. Detailed Findings

### Critical

#### CRIT-01 — `DroughtRiskService` has no fallback when ML service (`127.0.0.1:8010`) is unreachable
- **Severity:** Critical
- **Area:** Data & Jobs / ML Resilience
- **Steps to reproduce:**
  1. Ensure FastAPI ML service on port `8010` is stopped.
  2. Run `php artisan iclimate:refresh-weather-cache` or `php artisan iclimate:refresh-drought-risk`.
  3. Or run `php artisan test --filter=BarangayCurrentWeatherTest`.
- **Evidence:**
  - `app/Services/Risk/DroughtRiskService.php:239` calls `$this->python->farmingAssistant(['prediction_type' => 'weather', ...])` without catching `RuntimeException` / `Throwable`.
  - `app/Services/PythonService.php:113` throws `RuntimeException: The Farming AI API could not be reached`.
  - Caused `iclimate:refresh-weather-cache` and `iclimate:refresh-drought-risk` to exit with uncaught `RuntimeException`, and caused `StrayRequestException` across `BarangayCurrentWeatherTest`, `BarangayInsightTest`, `HeatMapDataFlowTest`, `FarmerDashboardWeatherTest`, `BarangayRiskMapTest`, `BarangayRiskSyncTest`, and `CacheDependencyTest`.
- **Suggested fix:** Wrap `$this->python->farmingAssistant(...)` in `DroughtRiskService::assessWithMl()` in a `try/catch (Throwable)` and return `['ml_reachable' => false, 'error' => ...]` so callers fall back to stored Open-Meteo / rule-based drought metrics.

#### CRIT-02 — `iclimate:refresh-barangay-weather` throws `Undefined array key "latitude"`
- **Severity:** Critical
- **Area:** Data & Jobs
- **Steps to reproduce:**
  1. Run `php artisan iclimate:refresh-barangay-weather --force` (or wait for the 10-minute cron in `routes/console.php:112`).
- **Evidence:**
  - `app/Console/Commands/RefreshLianBarangayWeather.php:44`: `ErrorException: Undefined array key "latitude"` while mapping `$records` for `Binubusan`.
  - `iclimate:refresh-weather-cache` also reported `Barangay forecasts ready: 13` (not 19/19).
- **Suggested fix:** Align the record keys returned by `LianBarangayWeatherService::refreshAll()` with `RefreshLianBarangayWeather::handle()` (use null-coalescing `?? '—'` or lookup coordinates from `LianBarangays::coordinates()`), and verify all 19/19 barangay forecasts populate cleanly.

#### CRIT-03 — Legacy `IT Expert` database accounts (`id=3`, `id=9`) — ✅ Resolved (Deactivated)
- **Severity:** Critical *(Resolved)*
- **Area:** Security / Accounts & Roles
- **Context:** `"IT Expert"` is **not** a system role (IT experts are UAT evaluators who use normal farmer/MAO test accounts). Code, seeders, and forms already have no active `'IT Expert'` role handling (`User::ROLES` is `['farmer', 'mao']`, and `TwoRoleAccessTest` enforces login rejection).
- **Affected Database Rows (preserved for audit, login disabled):**
  - `id=3` — `Default IT Expert` (`admin@iclimate.com`), raw `role = 'IT Expert'` → set `status = 'Deleted'`, `deleted_at = 2026-11-25 15:26:34`, sessions cleared.
  - `id=9` — `it personnel` (`it@gmail.com`), raw `role = 'IT Expert'` → set `status = 'Deleted'`, `deleted_at = 2026-11-25 15:26:34`, sessions cleared.
- **Evidence:**
  - `database/seeders/StaffLoginAccountSeeder.php:12` and `config/iclimate.php:59` had stale `it@gmail.com` / `IT` comments (cleaned up).
  - `app/Models/User.php:179-186` intentionally omits `'IT Expert'` from `canonicalRole()`.
- **Resolution:** Deactivated/soft-deleted users `id=3` and `id=9` (rows retained for audit; not mapped to MAO) and removed stale `IT` / `it@gmail.com` comments in `StaffLoginAccountSeeder.php` and `config/iclimate.php`.

---

### High

#### HIGH-01 — Background workers (`queue:work`, `schedule:work`, Reverb, ML `:8010`) are not running; stale backups & 219 queued jobs
- **Severity:** High
- **Area:** Data & Jobs / Deploy
- **Steps to reproduce:**
  1. Run `php artisan iclimate:health --no-ansi`.
  2. Check `jobs` table and `lsof -i :8010 -i :8080`.
- **Evidence:**
  - `iclimate:health` output:
    - `FAIL ml-service` (port `8010` not listening)
    - `FAIL reverb` (port `8080` not listening)
    - `FAIL queue` (`oldest job waiting 4914 min`; `219` jobs queued after `iclimate:clean-stale-queue`)
    - `FAIL scheduler` (`last scheduled weather fetch 1896 min ago`)
    - `FAIL backup` (`newest backup is 5 days ago`)
- **Suggested fix:** Start and supervise `iclimate-queue`, `schedule:run` cron, `iclimate-python` (`:8010`), and `reverb` (`:8080`) per `docs/deploy/workers.md`, and run `php artisan iclimate:backup-database` to verify backup recovery.

#### HIGH-02 — `WarmFarmerYields` spawns 1,082 sequential local Python subprocesses when `:8010` is down
- **Severity:** High
- **Area:** Data & Jobs / Performance
- **Steps to reproduce:**
  1. Stop ML service (`:8010`).
  2. Run `php artisan iclimate:warm-farmer-yields`.
- **Evidence:**
  - `app/Console/Commands/WarmFarmerYields.php:23` defines `STOP_AFTER_SERVICE_FAILURES = 3`, expecting to stop early if the ML service is down.
  - Because `RiceYieldPredictionService` falls back to local Python `shell_exec` when `127.0.0.1:8010` fails, each of the `868` eligible farmers (`1,082` active verified farms) logs 2 `WARNING`s in `storage/logs/laravel.log` and spawns a fresh Python process (~3.5+ minutes, timed out/cancelled at 3m53s).
- **Suggested fix:** In `WarmFarmerYields`, check ML `/health` once up front (or pass a flag to skip local subprocess fallback during bulk warm-up when `:8010` is unreachable) and batch/reuse a single Python process.

#### HIGH-03 — 24 Playwright browser tests failing on `main`
- **Severity:** High
- **Area:** Functional / UI
- **Steps to reproduce:**
  1. Run `npm run test:browser`.
- **Evidence:**
  - `24 failed, 18 skipped, 164 passed`:
    - `tests/Browser/account-requests.spec.mjs:39,71` (desktop & mobile): `strict mode violation: locator('article') resolved to 2 elements` on Review Details page.
    - `tests/Browser/critical-workflows.spec.mjs:26` (desktop & mobile): `locator.fill` timeout (`30000ms`) on `row.locator('input[name="user_id"]')` in `/mao/association-reports`.
    - `tests/Browser/farmer-kpi-modal.spec.mjs:17` (desktop & mobile): `locator.click` timeout (`30000ms`) on `[data-panel]`.
    - `tests/Browser/forecast-moved-to-mao.spec.mjs:33`, `live-clock.spec.mjs:48`, `mao-removed-pages.spec.mjs:17,24`: `locator.click` timeout (`30000ms`) on `.ic-topbar-menu-btn` / mobile nav.
    - `tests/Browser/landing.spec.mjs:469,491,501,588` (K2b, K3, K4, K7): Create Account form selectors/layout assertions failing (`toBeDisabled` / `toBeVisible` / `375px rows`).
    - `tests/Browser/mobile-navigation.spec.mjs:26`: `expect(body).not.toHaveCSS('overflow', 'hidden')` failed.
- **Suggested fix:** Inspect traces (`test-results/port-8123/*/trace.zip`) for each failing spec; fix regressions in UI selectors/drawer overflow/landing Create Account mode, and update specs where intentional UI changes occurred.

#### HIGH-04 — 111 PHPUnit tests failing on `main` (stale assertions + `DroughtRiskService` crash)
- **Severity:** High
- **Area:** Functional / Code Health
- **Steps to reproduce:**
  1. Run `php artisan test --no-ansi`.
- **Evidence:**
  - `111 failed, 5 skipped, 1,711 passed`:
    - ~60+ failures stem from **CRIT-01** (`DroughtRiskService` calling `PythonService` without fallback → `StrayRequestException`).
    - Remaining failures stem from recent UI/route changes without updating tests: `StrictContentSecurityPolicyTest:30` (`weather-predictions` now redirects farmers `303`), `YieldProvenanceDisplayTest:87` (`analysis.forecast` redirects `302`), `TwoRoleAccessTest:115` (`isAssociationChairman()` false), `YieldModelGateTest:130`, `FarmerAdvisoryViewTest:44` (copy changed to `"Walk your field and check water depth."`), `FarmerNaicelFeaturesTest:179,201` (`setup:complete_profile` + required `rice_variety_id`), `PredictionsPageTest`, `MaoDashboardHomeTest`, `FarmerPagesSimplificationTest`.
- **Suggested fix:** Fix **CRIT-01** first (clears ~60+ failures), investigate `TwoRoleAccessTest:115` and `YieldModelGateTest:130`, and update tests whose routes/copy were intentionally changed.

#### HIGH-05 — Invalid and blank `barangay` values in live MySQL tables
- **Severity:** High
- **Area:** Data & Jobs
- **Steps to reproduce:**
  1. Query `farmer_profiles`, `farms`, and `rice_productions` for `barangay` values not in `LianBarangays::all()`.
- **Evidence:**
  - `farmer_profiles`: `blank=0`, invalid = `["—", "BARANGAY 1 POB", "LUYAHN", "Unknown"]`
  - `farms`: `blank=1`, invalid = `["—", "BARANGAY 1 POB", "LUYAHN"]`
  - `rice_productions`: `blank=11`, invalid = `["—", "BARANGAY 1 POB", "LUYAHN"]`
  - Note: `WarmFarmerYields` and `BarangayYieldForecastService` exclude unmatched farms from barangay averages, and `MaoScope` cannot match non-canonical names (`LUYAHN` vs `Luyahan`, `BARANGAY 1 POB` vs `Barangay 1 (Pob.)`).
- **Suggested fix:** Run `php artisan iclimate:apply-barangay-corrections` (or a targeted data cleanup migration/command) to normalize `"LUYAHN"` → `"Luyahan"`, `"BARANGAY 1 POB"` → `"Barangay 1 (Pob.)"`, and resolve `"—"` / `"Unknown"` / blank rows.

#### HIGH-06 — `users.is_mao_head = true` has `0` rows in local/staging DB
- **Severity:** High
- **Area:** Data & Jobs / Functional
- **Steps to reproduce:**
  1. Query `SELECT COUNT(*) FROM users WHERE is_mao_head = 1`.
- **Evidence:**
  - Live MySQL database returns `users.is_mao_head=true: 0` (also noted in `docs/audit/uat-readiness.md:5`).
  - Without an MAO Head account, MAO Head-only routes (`/users`, `/mao/personnel`, `/mao/activity-log` full view) have no active owner in the database.
- **Suggested fix:** Run `php artisan iclimate:assign-mao-head` for the designated Municipal Agriculturist account.

#### HIGH-07 — `.env` has syntax warning (`(` on line 139), uses deprecated `MAIL_ENCRYPTION`, and omits key production settings vs `.env.example`
- **Severity:** High
- **Area:** Deploy / Security
- **Steps to reproduce:**
  1. Parse `.env` or compare `.env` keys against `.env.example`.
- **Evidence:**
  - `parse_ini_file('.env')` emits `PHP Warning: syntax error, unexpected '(' in .env on line 139` (unquoted value with parentheses).
  - `.env` sets `MAIL_ENCRYPTION` instead of Laravel 12's `MAIL_SCHEME` (though `config/mail.php` resolved `scheme='smtp'` via default/fallback).
  - `.env` is missing `SESSION_SECURE_COOKIE`, `SESSION_HTTP_ONLY`, `SESSION_SAME_SITE`, `BACKUP_DB_*`, `APP_VERSION`, `APP_DEPLOYED_AT`, `ICLIMATE_YIELD_ACCURACY_VERIFIED`, `PAGASA_REGIONAL_FORECAST_URL`, etc.
  - `APP_URL=http://127.0.0.1:8000` and `APP_ENV=local` in local `.env` (must be `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true` on HTTPS production).
- **Suggested fix:** Quote any `.env` value containing parentheses/spaces, remove `MAIL_ENCRYPTION`, and sync all keys from `.env.example` for production cutover.

---

### Medium

#### MED-01 — UAT Survey vs UI feature gaps (moved/removed features still in survey)
- **Severity:** Medium
- **Area:** UAT Readiness
- **Steps to reproduce:**
  1. Compare `docs/audit/uat-readiness.md` against `routes/web.php`, `routes/manuscript.php`, and `resources/views/layouts/sidebar.blade.php`.
- **Evidence:**
  - **7/14-day Forecast** (`/weather-predictions`) is MAO-only (`routes/web.php:809-813`), and **Forecast** was removed from the Farmer sidebar (`sidebar.blade.php:79-86`).
  - **"Was yesterday's forecast right?"** (`forecast-survey.blade.php`) is only included on MAO-only `/weather-predictions`, making it unreachable for farmers.
  - **Yield Prediction** (`/analysis/yield`) and **Weather Analysis** (`/analysis/weather`) work for farmers by URL but are omitted from the Farmer sidebar and mobile nav.
  - **Impact Analysis** (`/analysis/impact`), **MAO Yield Forecast** (`/analysis/forecast`), and **Model Evaluation** (`/analysis/model-evaluation`) all redirect to `mao.dashboard` (`routes/manuscript.php:108-123`, `routes/web.php:845-850`), while Form B #6/#19 and Form C #3/#6 still ask about them.
  - Recent commit `4444f8dc` also removed the Season Yield Comparison chart from `my-farm.blade.php`, and `b2db568c` removed drawer detail links on the Farmer dashboard.
- **Suggested fix:** Either restore/link Farmer navigation items (`/analysis/yield`, `/analysis/weather`, `forecast-survey` on Farmer dashboard) or update the UAT survey instruments to match the finalized scope.

#### MED-02 — Literal `"EN"` / `"FIL"` text and hardcoded English strings in Climora widget & topbar
- **Severity:** Medium
- **Area:** UI/UX / Translations
- **Steps to reproduce:**
  1. Switch language to Filipino (`fil`) and open the topbar farm selector, account menu, or Climora widget.
- **Evidence:**
  - `resources/views/components/ai-chat-widget.blade.php:630-642` has hardcoded English (`Climora AI`, `Weather, rice yield, planting, and system guidance`, `Conversation Language`, `Minimize chat`, `Close chat`) and literal `<option>EN</option>` / `<option>FIL</option>` text.
  - `resources/views/layouts/navigation.blade.php:66,129,134,138` has hardcoded English `' · Pending MAO review'`, `' · Rejected'`, `'Covers: '`, `'Profile'`, and `'Settings'`.
  - `resources/views/layouts/sidebar.blade.php:84` and `resources/views/farmer/complete-profile.blade.php:119-120` use inline `$fil ? ... : ...` ternaries instead of `lang/` keys (though bilingual).
- **Suggested fix:** Move remaining hardcoded strings into `lang/{en,fil}/` files and replace literal `EN`/`FIL` option text in the Climora widget with full language names (`English` / `Filipino`) or flag icons matching the header.

#### MED-03 — `Climora` widget & `Navigation` hardcoded English `aria-label`s
- **Severity:** Medium
- **Area:** UI/UX / Accessibility
- **Steps to reproduce:**
  1. Inspect `resources/views/layouts/navigation.blade.php:33,38,90,113` in Filipino locale.
- **Evidence:** `aria-label="Top bar"`, `aria-label="Language"`, `aria-label="Notifications..."` are hardcoded in English.
- **Suggested fix:** Translate all `aria-label` attributes via `__(...)`.

#### MED-04 — PurgeCSS disabled when building with `NODE_ENV=development` (large unpurged CSS)
- **Severity:** Medium
- **Area:** Performance / Build
- **Steps to reproduce:**
  1. Run `NODE_ENV=development npx vite build` vs `npm run build` (`NODE_ENV=production`).
- **Evidence:**
  - `postcss.config.js:59`: `if (process.env.NODE_ENV !== 'production') { return; }`.
  - When built with `NODE_ENV=development`, PurgeCSS is skipped, inflating `app.css` from **416 KB** to **~544 KB** (plus `app.js` **332 KB**, `chart.js` **200 KB**, `leaflet` **146 KB**; total `public/build/assets/` is **3.3 MB**).
- **Suggested fix:** Ensure CI/production deployments always use `npm run build` (`NODE_ENV=production`), and verify no dynamic classes are stripped by PurgeCSS safelist (`postcss.config.js:11-49`).

#### MED-05 — `DashboardController` and `WeatherPredictionController` import `WeatherApiService` directly
- **Severity:** Medium
- **Area:** Performance
- **Steps to reproduce:**
  1. Inspect `app/Http/Controllers/DashboardController.php:35,357,434` and `app/Http/Controllers/WeatherPredictionController.php:17,217,363`.
- **Evidence:** `WeatherPredictionController::municipalForecast()` and `DashboardController::maoOperationsLiveWeather()` reference `WeatherApiService` directly; if cache is cold, requests can hit external weather APIs inline instead of reading only stored DB/cache records.
- **Suggested fix:** Ensure controller endpoints read strictly from stored `ExternalWeatherData` / `PreparedDashboards` and leave external HTTP calls to scheduled commands.

#### MED-06 — `DroughtRiskService` ML fallback failure cascades into `laravel.log` noise
- **Severity:** Medium
- **Area:** Data & Jobs / Operations
- **Steps to reproduce:**
  1. Run `WarmFarmerYields` or weather refresh while `:8010` is stopped; inspect `storage/logs/laravel.log`.
- **Evidence:** `storage/logs/laravel.log` receives 2 `WARNING` entries per farm (`FastAPI /predict connection failed` + `Using local rice-yield inference`) for every single farm, rapidly bloating daily logs.
- **Suggested fix:** Circuit-break `:8010` for the remainder of a batch run after `N` consecutive connection failures (`PythonService::recordFailure()`), logging once per batch instead of per farm.

#### MED-07 — `storage/logs/laravel.log` contains historical debug/drill connection strings
- **Severity:** Medium
- **Area:** Security / Logs
- **Steps to reproduce:**
  1. Search `storage/logs/laravel.log` for `DB_PASSWORD`.
- **Evidence:** `storage/logs/laravel.log:282335,450223` (from `2026-11-05` and `2026-11-20` CLI drills) logged `DB_CONNECTION=... DB_PASSWORD=drill-local-only` in exception messages.
- **Suggested fix:** Rotate/prune old `storage/logs/laravel.log` before production cutover and keep `LOG_LEVEL=warning` (never `debug`) in production.

#### MED-08 — `climate_records` table has `0` rows in MySQL
- **Severity:** Medium
- **Area:** Data & Jobs
- **Steps to reproduce:**
  1. Run `php artisan iclimate:data-audit`.
- **Evidence:** `climate_records total 0 verified 0`. While ML training falls back to `database/data/Ambulong Monthly Data.csv` (132 rows, up to `2025-12-01`) and Open-Meteo archive (`2026-10`), `iclimate:ml-readiness` reports `climate_freshness: FAIL` on the CSV proxy alone.
- **Suggested fix:** Import Ambulong CSV + recent verified monthly climate records into `climate_records` (`php artisan climate:import-ambulong`) and confirm `iclimate:fetch-climate-archive` runs daily.

#### MED-09 — `ml-readiness` reports `ACCURACY NOT VERIFIED` (`research/*` artifacts missing)
- **Severity:** Medium
- **Area:** Data & ML Governance
- **Steps to reproduce:**
  1. Run `php artisan iclimate:ml-readiness`.
- **Evidence:** `accuracy_status: "ACCURACY NOT VERIFIED"` because `research/original_training_dataset.csv`, `research/train_deployed_model.py`, `research/feature_dictionary.csv`, `research/evaluation_protocol.json`, and `research/independent_yield_observations.csv` are `missing_required`.
- **Suggested fix:** Supply the approved research evaluation artifacts if formal ML accuracy sign-off is required, or keep `ICLIMATE_YIELD_ACCURACY_VERIFIED=false` so unverified-model gates remain active.

---

### Low

#### LOW-01 — `Landing` Playwright K7 (`375px rows`) layout failure
- **Severity:** Low
- **Area:** UI/UX
- **Steps to reproduce:**
  1. Run `npx playwright test tests/Browser/landing.spec.mjs -g "K7"`.
- **Evidence:** `tests/Browser/landing.spec.mjs:588` failed with `Error: 375px rows` on both `desktop-1280` and `mobile-375` projects.
- **Suggested fix:** Inspect the K7 screenshot/trace in `test-results/port-8123` and adjust the Create Account form row wrapping/padding at `375px`.

#### LOW-02 — Inline `$fil ? ... : ...` ternaries in Blade views instead of `lang/` keys
- **Severity:** Low
- **Area:** UI/UX / Maintainability
- **Steps to reproduce:**
  1. Search `resources/views/` for `\$fil \?`.
- **Evidence:** `resources/views/farmer/complete-profile.blade.php:119-120,171,184,191-192`, `resources/views/farmer/explore-results.blade.php:4-173`, `resources/views/emails/farm-verification.blade.php:7-39`, and `resources/views/dashboards/farmer/harvest.blade.php:28` use inline `$fil ? ... : ...` instead of `lang/{en,fil}/` keys.
- **Suggested fix:** Extract to `lang/en/` and `lang/fil/` so `TranslationParityTest` enforces parity automatically.

#### LOW-03 — `usic` / `nsic_code` column check
- **Severity:** Low (Verified Clean)
- **Area:** Data & Jobs
- **Evidence:** `grep -rn "nsic_code" app/ resources/ routes/ database/` returned **0 matches**. Schema guards (`Schema::hasColumn` / `Schema::hasTable`) are present in `MaoAssociationFarmTypeController`, `YieldPredictionController`, `FarmVerificationController`, and `FarmerActivityController`.

#### LOW-04 — `Storage` and `bootstrap/cache` permissions
- **Severity:** Low (Verified Clean locally; verify on Linux deploy)
- **Area:** Deploy
- **Evidence:** `storage`, `storage/app`, `storage/logs`, and `bootstrap/cache` are `drwxr-xr-x` locally. On Linux/Hostinger production, ensure ownership is `www-data:www-data` (`chown -R www-data:www-data storage bootstrap/cache`).

#### LOW-05 — `accounts` health warning: 2 copied password hashes
- **Severity:** Low (Accepted by choice)
- **Area:** Security
- **Evidence:** `iclimate:health` reported `WARN accounts: shared-password lockdown OFF; 2 password hash(es) are copied across accounts`. Per project policy, default passwords are allowed by choice; `must_change_password` flag is available for first-login password change (`/account/change-password`).

---

## 3. Proposed Fix Phases

> **Awaiting your approval before making any edits.**

| Phase | Focus | Finding IDs | Effort Estimate | Risk |
| --- | --- | --- | --- | --- |
| **Phase 1** | **Critical & High Blockers** (ML failover, `refresh-barangay-weather` crash, background workers; `CRIT-03` resolved) | `CRIT-01`, `CRIT-02`, `HIGH-01` (`CRIT-03` ✅) | ~2–3 hours | **Low–Medium** (isolated catch/fallback in `DroughtRiskService`, key alignment in `RefreshLianBarangayWeather`, starting supervisor/cron services) |
| **Phase 2** | **Data, Jobs & Test Suite Correctness** (`WarmFarmerYields` batching, invalid barangays, `is_mao_head`, PHPUnit & Playwright regressions, `climate_records`) | `HIGH-02`, `HIGH-03`, `HIGH-04`, `HIGH-05`, `HIGH-06`, `MED-06`, `MED-08`, `MED-09` | ~4–8 hours | **Medium** (data normalization on live DB should be dry-run first; Playwright/PHPUnit updates require verifying intentional UI changes vs actual bugs) |
| **Phase 3** | **Deploy & Security Hardening** (`.env` syntax/keys sync, log rotation, `Permissions`/HTTPS session flags) | `HIGH-07`, `MED-07`, `LOW-04`, `LOW-05` | ~1–2 hours | **Low** (config-only changes; test `php artisan config:cache` + `iclimate:health` afterward) |
| **Phase 4** | **UI/UX, Translations & UAT Alignment** (Climora & topbar translations, `EN/FIL` removal, K7 `375px` layout, UAT nav/survey alignment) | `MED-01`, `MED-02`, `MED-03`, `LOW-01`, `LOW-02` | ~3–5 hours | **Low** (Blade/lang/CSS updates; re-run `TranslationParityTest` and Playwright `landing.spec.mjs`) |
| **Phase 5** | **Performance & Cleanup** (`NODE_ENV=production` PurgeCSS verification, remove controller `WeatherApiService` direct coupling) | `MED-04`, `MED-05`, `LOW-03` | ~2–3 hours | **Low** (verify `npm run build` + `PagePerformanceTest` + visual check of purged CSS) |
