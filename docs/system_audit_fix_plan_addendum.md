# iClimate System Audit — Addendum (Fresh Pass)

Prepared: 2026-09-12
Companion to `docs/system_audit_fix_plan.md` (the original audit) and the fix pass committed 2026-09-11/12 (`Fix #1`–`#19`). This is a genuinely new audit pass, not a re-check of the original list — see the verification status table delivered alongside this document for that re-check. Findings here were found by re-reading the current code with fresh eyes, live-testing routes, running dependency/security tooling, and checking git history for things the original audit's snapshot-in-time review couldn't have caught.

Severity tags match the original document's convention: **[CRIT]**, **[MOD]**, **[MIN]**.

---

## New findings

### [CRIT] Live weather data is completely broken in this environment — cURL SSL verification failure

`storage/logs/laravel.log` shows, reproducing live right now on every farmer dashboard load:

```
Farmer dashboard weather fetch failed. All weather providers failed.
WeatherAPI: WeatherAPI key is not configured. |
Open-Meteo: cURL error 60: SSL certificate ... unable to get local issuer certificate (20)
```

Root cause: the project's PHP binary (`C:\xampp\php_broken_backup_20260715\php.ini`) has both `curl.cainfo` and `openssl.cafile` commented out/unset, so every outbound HTTPS request (Open-Meteo, PAGASA, WeatherAPI) fails TLS verification. `WEATHERAPI_KEY` is also unset in `.env`, so the WeatherAPI path fails first (by design — `FarmerDashboardWeatherService` throws a clear "key is not configured" error) and falls through to Open-Meteo, which then fails on the cert issue too. The dashboard itself still returns HTTP 200 (no 500), but the live weather widget cannot show real data.

This almost certainly also silently affects the scheduled commands that hit HTTPS endpoints (`iclimate:refresh-lian-weather`, `iclimate:refresh-lian-boundaries`, `iclimate:fetch-pagasa-advisories`) — same root cause, one fix.

**Not a code bug** — it's a PHP/environment configuration issue, but it directly breaks a primary farmer-facing feature in the only PHP install currently available to run this project, so it blocks Part 3's smoke test as-is. Fix: point `curl.cainfo`/`openssl.cafile` at a valid CA bundle (a Mozilla `cacert.pem` already exists on this machine at `C:\xampp\phpMyAdmin\vendor\composer\ca-bundle\res\cacert.pem`, or use `.venv-ml`'s `certifi` bundle) — environment-only change, no repo commit possible (same category as the earlier `pdo_sqlite` fix).

---

### [CRIT] Model Evaluation feature is unreachable — the route was removed before the original audit was even written

`app/Http/Controllers/ModelEvaluationController.php` and `resources/views/model-evaluation/index.blade.php` exist, but **no route in `routes/web.php` points to them** — `git log -S "ModelEvaluationController" -- routes/web.php` shows the route was deleted in commit `5b695d6` ("added farmer organization tracking and MAO organization analytics"), dated **2026-08-27**, two weeks *before* `docs/system_audit_fix_plan.md` (dated 2026-09-11) was written. No sidebar link or other view references it either.

This means the original audit's finding #9 ("Model Evaluation page hides DEPLOYED/TARGET schema mismatch") was already describing dead code at the time it was written, and the fix applied for it in this pass (`Fix #9`, commit `ac75550`) patched a controller/view that no user — farmer, MAO, or IT-Expert — can currently reach. The fix isn't wrong, but it currently has zero real-world effect.

**Needs a decision, not a unilateral fix**: either the route should be restored (if Model Evaluation was meant to stay a live feature and its removal was accidental), or `ModelEvaluationController.php`, `resources/views/model-evaluation/`, and (if nothing else needs it) `ModelEvaluationService.php` should be deleted as confirmed dead code. Flagging per the "stop and report, don't silently work around it" rule.

---

### [MOD] `AnnouncementController.php` is fully dead code, and its own redirect target doesn't exist

No route in the entire app points to `AnnouncementController` (confirmed via a controller-vs-route cross-check across `routes/web.php`, `routes/auth.php`, `routes/api.php`). Its `store()` and `update()` methods redirect to `route('announcements.show', ...)` — a route name that isn't registered anywhere, so even if the controller were somehow invoked, that redirect would itself throw a `RouteNotFoundException`.

History: `/announcements` now redirects to `/community-feed` (`routes/web.php:126-127`, `name('announcements.index')`), strongly suggesting Announcements were superseded by the Community Feed feature. However, the underlying `Announcement` **model and table are still alive**: `PredictionService.php` reads the latest published announcement (lines 459, 525), `User::announcements()` relation exists, and `DatabaseSeeder.php` seeds 3 rows via `Announcement::factory()`. Only the CRUD **controller** is orphaned — meaning **there is currently no way for MAO/IT-Expert staff to create or edit an announcement through the web UI**; they can only exist via the seeder.

Confirm whether this is intentional (Announcements fully replaced by Community Feed posts, and `PredictionService`'s read of the `Announcement` model is itself now dead-in-spirit) before deleting the controller — the model/data usage suggests this might not have been a clean migration.

---

### [MOD] Password-reset endpoints have zero throttling — an audit gap the original plan didn't check

`POST /forgot-password` (`password.email`) and `POST /reset-password` (`password.store`) carry only `web`/`guest` middleware — no `throttle:` middleware, and no manual `RateLimiter` calls in `PasswordResetLinkController` or `NewPasswordController` (unlike the login flow, which correctly implements manual rate-limiting inside `LoginRequest` per Laravel Breeze convention — confirmed by reading that class, since `route:list` alone doesn't show it). This allows unlimited password-reset-email spam to any address and unbounded reset-attempt volume. The original audit's plan only covered the registration route and Community Feed; this pair was never checked.

---

### [MOD] The new unique constraints (this fix pass, Fix #7) introduced an empirically-confirmed intermittent seeding failure

Verified live against the isolated SQLite test database (not the production MySQL data): running `RiceProduction::factory()->count(5)->create()` (the exact call `DatabaseSeeder.php` makes) 200 times failed with a duplicate-key `QueryException` in **13/200 trials (~6.5%)**; `ClimateRecord::factory()->count(5)->create()` failed in **10/300 trials (~3.3%)**. Both factories generate barangay/season/irrigation_type/year (or record_date) combinations randomly with no uniqueness guard, and the unique indexes added in Fix #7 now reject the collision. Any `php artisan migrate:fresh --seed` has a real, non-trivial chance of failing outright. This is a regression from this fix pass's own earlier work, not a pre-existing issue.

---

### [MOD] Known CVEs in two transitive Composer dependencies — patchable without touching `composer.json`

`composer audit` reports 12 advisories across 2 packages, both pulled in transitively by `laravel/framework` (not direct requires):

- **`guzzlehttp/guzzle` 7.15.1** — HIGH: CVE-2026-69246 (noncanonical host bypasses host-based checks); MEDIUM: CVE-2026-69245 (noncanonical cookie domain keeps subdomain scope). Guzzle backs every `Http::` call in this app (weather providers, PAGASA fetch, the FastAPI `/predict` boundary), so this is actively exercised code, not dormant.
- **`league/commonmark` 2.8.2** — multiple HIGH advisories (several DoS variants, an XSS filter bypass, one with CVE-2026-71488 for quadratic-time DoS on crafted Markdown). Not directly invoked by any app code found in `app/`/`resources/` — appears to be a pure framework-internal dependency, lowering practical exposure.

`composer why` confirms `laravel/framework`'s own constraints (`^7.8.2` for guzzle, `^2.8.1` for commonmark) already permit the patched versions (guzzle ≥7.15.2, commonmark ≥2.10.0), so a targeted `composer update guzzlehttp/guzzle league/commonmark` should resolve all 12 without a `composer.json` change or touching Laravel's own version.

---

### [MOD] `npm audit`: 7 vulnerabilities, all in build tooling (not shipped to users)

5 high / 1 moderate / 1 low across `esbuild`, `browserslist`, `nanoid`, `postcss`, `shell-quote`, `concurrently`, `baseline-browser-mapping` — all dev/build-time dependencies, not runtime code shipped to the browser. Worth patching anyway since `npm audit fix` reports a resolution path with no `--force` needed (non-breaking), and one advisory (esbuild: "arbitrary file read when running the development server on Windows") is directly relevant since this project is developed on Windows.

---

### [MIN] `.venv-ml`'s `setuptools` (65.5.0) has 4 tracked CVEs

`pip-audit` reports PYSEC-2022-43012, PYSEC-2025-49, PYSEC-2026-1918, and PYSEC-2026-3447, all fixed in later releases (up to 83.0.0). Low practical risk — setuptools is a build-time tool, not invoked by the running FastAPI service — but a safe, zero-risk upgrade.

---

### [MIN] `.env` / `.env.example` drift and dead config keys

- `.env` declares `OPENWEATHER_API_KEY=` (confirmed empty, not a live credential — an earlier pass of this review redacted it defensively without checking the actual value, which was inaccurate) — **nothing in the codebase reads this variable** at all. Dead, unused, and empty; safe to remove.
- `.env.example` declares `ACCUWEATHER_API_KEY` — also confirmed unused anywhere in the codebase.
- `.env.example`'s comment above `FARMING_AI_HOST` still reads *"FARMING_AI_HOST is only used by the legacy Flask app (`python_scripts/farming_assistant_api.py`), kept during the migration"* — that file was deleted in **this same fix pass** (`Fix #8`), and this `.env.example` entry was never updated as part of that item (the original plan's scope for #8 only mentioned `main.py`'s docstring). `FARMING_AI_HOST` itself also isn't read anywhere in current code — safe to remove along with the stale comment.
- Several `WEATHER_*`/`ICLIMATE_WEATHER_*` keys are declared in `.env.example` but absent from `.env` — not a bug, since `config/services.php` has sensible multi-level `env()` fallback chains for all of them, but noted for completeness.

### [MIN] Stray tracked `.pyc` file predates `.gitignore`

`python_scripts/__pycache__/predict.cpython-314.pyc` is tracked in git (from the very first commit, `d50eb9e`) despite `__pycache__/` being gitignored — `.gitignore` doesn't retroactively untrack already-committed files. Safe to `git rm --cached`.

### [MIN] Two stale historical comments referencing the deleted Flask service

`python_scripts/rice_yield_service.py` lines ~3 and ~222 still name `farming_assistant_api.py` by filename for historical context ("same contract farming_assistant_api.py's Flask /predict returned..."). Not incorrect, just slightly stale — `Fix #8`'s scope only covered `main.py`'s docstring per the original plan.

### [MIN] Pre-existing Pint formatting debt — confirmed not a regression from this fix pass

`vendor/bin/pint --test` fails on 15 files. Verified `app/Services/HeatmapRiskService.php`'s violations are byte-identical to its pre-fix-pass version (checked out from commit `71df7cd`, before `Fix #5` touched it) — so this fix pass introduced no new style debt. Safe, formatting-only cleanup candidate.

---

## Confirmed healthy (no finding, stated for the record)

- **Full PHP test suite**: 186 passed / 897 assertions, zero failures (`artisan test`, run fresh).
- **FastAPI service**: starts cleanly with no warnings; `/health` returns `{"status":"ok","models_loaded":["rice","weather"]}`.
- **PHP↔Python boundary degrades gracefully, live-tested in both directions** (not just read from code): with the Python service confirmed down, `POST /rice-yield-predictions/predict` and `POST /weather-predictions/predict` both returned HTTP 200 with a locally-computed fallback result — never a 500. With the service back up, the same routes correctly reported `"iClimate ML service"` as the source instead of the fallback path.
- **CSRF protection** applies uniformly across the `web` middleware group — no exceptions configured anywhere.
- **Login throttling** is correctly implemented via Laravel Breeze's manual `RateLimiter` pattern inside `LoginRequest` (5 attempts) — not visible in `route:list` since it's controller-level, not middleware, which is why it's called out here: `route:list` alone is not sufficient to audit throttling coverage.
- **No `TODO`/`FIXME`/`XXX` comments** remain anywhere in `app/` or `python_scripts/`.
- The three scheduled commands referenced in `routes/console.php` (`iclimate:automation-run`, `iclimate:refresh-lian-weather`, `iclimate:refresh-lian-boundaries`) are all registered and resolvable — not yet dry-run (that's a Part 3 task), but not missing/broken at the definition level.
