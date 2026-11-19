# iClimate System Audit — Codex Prompts per Phase (2026-11-14)

Phase 0 is owner actions and has no prompt. Run the phases in order. Each prompt is self-contained: paste
the "Common rules" block first, then the phase block.

---

## Common rules (paste at the top of every phase prompt)

```text
You are working in the iClimate repository (Laravel 12, PHP 8.5, MariaDB 10.4, Python FastAPI ML service,
Laravel Reverb, database queue). Read docs/system-audit/02-findings.md and
docs/system-audit/03-phase-roadmap.md before changing anything; finding IDs below refer to them.

Hard rules:
- Implement ONLY the phase described below. No unrelated refactors, renames, UI redesigns or wording changes.
- The working tree has many unrelated uncommitted changes. Keep them. Never run git stash, git reset,
  git checkout -- <file>, git clean or git restore. Run `git status --short` before and after and report
  any file you did not intend to touch.
- Do not commit, push or deploy.
- Do not modify, retrain, replace or delete any file under storage/models. Do not delete or rewrite any
  dataset. Do not restore previously deleted PSA datasets.
- Never run migrations, seeders, tinker writes or artisan commands that write against the database in .env
  (iclimate_staging_20261103_fix3). Use the PHPUnit environment (in-memory SQLite) for tests.
- Never start a second app server from this checkout: storage/ is shared with the running app
  (finding OPS-05). Do not run `php artisan cache:clear`, `config:clear` or `optimize`.
- Any command that changes data must have a --dry-run mode that is the default; applying needs an explicit
  --apply flag and is for the owner to run.
- The deployed ML model's status is ACCURACY NOT VERIFIED. Never add text, tests or docs that state or
  imply verified accuracy.
- Farmer-facing text stays short and plain, in the existing style; say "Heat Map", never "Risk Map".
- After the change run `php vendor/bin/phpunit` (about 9 minutes, 1,789 tests green before you start) and
  report passed, failed and skipped counts with the failing output if any. Do not weaken or delete an
  existing test to make it pass; if one must change, explain why.

Finish with: files changed, tests added, test results, anything not done and why, and any step the owner
must run by hand.
```

---

## Phase 1 — Account and access hardening

```text
PHASE 1 — Account and access hardening. Closes SEC-01, SEC-02, SEC-05, DATA-01.

1. app/Http/Middleware/EnsurePasswordChanged.php
   - Enforce for every HTTP method and for requests that expect JSON. For JSON answer 409 with
     {"message": "..."}; otherwise redirect to route('password.first-change').
   - Keep open only: password.first-change, password.first-change.update, password.first-change.skip,
     logout, password.update.
   - config/iclimate.php: default mao_allow_skip_password_change to false (env override stays).

2. Default passwords
   - config/iclimate.php: remove the literal 'Password@123' fallback for mao_default_password (default
     null). MaoPersonnelSeeder and MaoPersonnelController::store/resetPassword must, when it is not
     configured, generate a random password (Str::password(16)), set must_change_password = true, and show
     it once to the acting MAO Head in the flash message. Remove the literal from FarmerDemoSeeder too
     (use config or a random value).
   - database/seeders/FarmerMasterlistSeeder.php: never reuse one hash for many accounts. Each new account
     gets its own random password and must_change_password = true.
   - New command app/Console/Commands/FlagDefaultPasswords.php, signature
     `iclimate:flag-default-passwords {--apply}`. It lists (count per role, never the passwords or hashes)
     accounts whose password hash is identical to another account's hash, and accounts whose hash matches
     any value in a new config list iclimate.known_default_passwords (env, comma separated, default empty).
     With --apply it sets must_change_password = true on them and writes one SystemAuditLogger entry.
     Default is dry-run.

3. Roles & Permissions (app/Http/Controllers/SystemSettingsController.php, app/Support/ModuleAccess.php,
   the admin permissions view)
   - savePermissions must validate that `modules` is present and is an array. A request without it is
     rejected with a validation error and changes nothing.
   - If a save would remove EVERY module from a role, require an extra field confirm_remove_all=1; the view
     shows a confirmation checkbox only in that case (small JS or a second step; keep the page design).
   - The audit entry records before and after per changed module.

4. MAO Head (DATA-01)
   - On the IT Users edit form, for MAO Personnel accounts only, add a checkbox "Municipal Agriculturist
     (MAO Head)". Saving it sets is_mao_head on that account and clears it on any other, in one
     transaction, with an audit entry. Only IT Expert can do this. Do not change MaoPersonnelController's
     own rule that only the head manages MAO accounts.

Tests to add (Feature):
- A must-change user is redirected on GET and blocked on POST, PUT, PATCH, DELETE and JSON requests, and
  can still log out and change the password.
- Skip is refused by default and allowed when the config is true.
- POST admin/permissions without `modules` returns a validation error and leaves the settings unchanged;
  removing every module from a role without confirmation is refused; with confirmation it saves.
- IT can set the MAO Head; MAO and Farmer cannot; setting a new head clears the old one.
- iclimate:flag-default-passwords dry-run changes nothing; --apply flags only the shared-hash and
  known-default accounts; output contains no password or hash.
- Masterlist seeding produces distinct hashes.

Acceptance: all new tests pass, full suite green, no literal default password remains in app/, config/ or
database/ (grep for 'Password@123').
Owner steps to list at the end: run the flagging command with --apply on the active database; rotate staff
accounts #8, #9, #15; choose the MAO Head.
```

---

## Phase 2 — Runtime reliability and housekeeping

```text
PHASE 2 — Runtime reliability and housekeeping. Closes OPS-01 to OPS-07 and SEC-07.

1. Host sleep (OPS-01)
   - scripts/local-service-supervisor.sh and scripts/start-iclimate-local.sh: on macOS, hold a wake
     assertion for as long as the scheduler or queue service runs (run the service under `caffeinate -s`
     or start one `caffeinate -s -w <supervisor pid>`). No effect on other platforms. `stop` must release it.
   - app/Services/SystemHealthService.php: add a check "Scheduler continuity" that reads
     scheduled_task_runs for the every-minute task over the last 24 h and reports the largest gap; warn
     above 3 minutes, fail above 15. Add a check that the last iclimate:automation-run is not older than
     45 minutes. Show both on the existing System Health page using the existing row style.

2. Growth (OPS-02, OPS-03, OPS-06)
   - New command iclimate:prune-runtime-data {--days=14} that deletes scheduled_task_runs older than N days
     in chunks, and removes expired entries from the file cache store (read the expiry prefix of each cache
     file; delete only expired ones; skip silently when the store is not `file`). Schedule it daily in
     routes/console.php next to the existing tasks, using the same logging pattern.
   - Schedule `queue:prune-failed --hours=336` daily.

3. Logging (OPS-04, SEC-07)
   - .env.example: LOG_STACK=daily, LOG_DAILY_DAYS=14, LOG_LEVEL=info, with a one-line comment each.
   - Find where outgoing mail is written to the log at debug level and make sure message bodies are not
     logged outside the testing environment.
   - Do not edit .env; list the three values for the owner to set.

4. Environment isolation (OPS-05)
   - app/Support/SystemSettings.php: the settings file path comes from config('iclimate.settings_path'),
     default storage_path('app/system-settings.json') (testing keeps its own file as today).
   - config/database.php: the mysql_backup connection's database default must be env('DB_DATABASE'), not a
     hard-coded name.
   - tests/Browser/serve.sh: give the browser-test server its own storage directory. PHP's built-in server
     does not pass LARAVEL_STORAGE_PATH to $_SERVER, so add tests/Browser/public/index.php (a copy of
     public/index.php that calls $app->useStoragePath(getenv('BROWSER_TEST_STORAGE')) before handling the
     request) and serve from there; create the needed storage subfolders in the run directory and point
     ICLIMATE settings, cache and sessions at it. The active app's storage/ must not change during a
     browser run.

5. Config hygiene (OPS-07): extend `php artisan iclimate:environment-check` to warn when APP_ENV is local
   while APP_URL is not a loopback address, and when SESSION_SECURE_COOKIE is not true on an https APP_URL.

Tests to add: prune command (rows older than N days removed, newer kept; expired cache files removed,
live ones kept); both health checks with synthetic rows; settings path override; mysql_backup default
follows DB_DATABASE.

Acceptance: full suite green; `caffeinate -dimsu npx playwright test` leaves `find storage/app -type f`
identical before and after; schedule:list shows the two new daily tasks.
```

---

## Phase 3 — Data integrity

```text
PHASE 3 — Data integrity. Closes DATA-02 to DATA-06. Every data change is dry-run by default.

Facts from the audit (active database, 2026-11-14): App\Support\LianBarangays supports 9 barangays;
barangay_profiles and heatmap_areas hold 19 rows each; farms has 12 unplaceable rows: '—' (3),
'Barangay 4 (Pob.)' (3), 'Barangay 1 (Pob.)' (2), 'BARANGAY 1 POB' (1), 'Humayingan' (1), 'LUYAHN' (1),
NULL (1). 4 active Farmer users have no farmer_profiles row. rice_productions: 124 rows, 0 verified.

1. Barangays
   - Read app/Console/Commands/BarangayReconciliation.php and ApplyBarangayCorrections.php first and extend
     them; do not write a parallel tool.
   - The report must list every farms and farmer_profiles row whose barangay is not a supported name, in
     three groups: (a) misspelling with exactly one confident match (e.g. 'LUYAHN' -> 'Luyahan'),
     (b) a real Lian barangay outside the supported list, (c) empty or placeholder. Only group (a) is
     corrected by --apply; (b) and (c) are written to a CSV for the MAO. Never guess.
   - Add Rule::in(LianBarangays::all()) (or the existing equivalent rule) to every controller that writes
     farms.barangay or farmer_profiles.barangay and does not have it yet. List the ones you changed.
   - For the 10 unsupported barangays in barangay_profiles and heatmap_areas: do not delete rows. Make
     every page and API that lists barangays read through LianBarangays so that only supported ones are
     shown, and add a test per page. Do not change the Heat Map mask, basemap, GeoJSON or layers.

2. Accounts
   - New read-only command iclimate:accounts-report listing Farmer users without a profile or a farm.
   - When such a farmer signs in, the existing dashboard setup flow must create the missing profile;
     confirm with a test, fix only if it does not.

3. Provenance labels
   - Wherever a figure is computed from rice_productions rows that are unverified or tagged demo_seed
     (Heat Map "last recorded yield", MAO dashboard figures, reports), show the existing
     unvalidated-estimate component or equivalent short note. No new design; reuse existing components.

4. Housekeeping
   - Schedule `auth:clear-resets` daily.
   - DATA-05: leave the `verified` middleware in place and add a short comment in routes/web.php and
     docs/system-audit/ noting it is inactive until the owner decides on email verification. No behaviour
     change.

Tests: reconciliation dry-run leaves data unchanged, --apply fixes only group (a); validation tests for
each write path changed; provenance note present when sources are unverified and absent when verified.

Acceptance: full suite green; on fixtures the report reproduces the three groups; nothing was applied to
the database in .env. Owner step: review the dry-run report, then run --apply.
```

---

## Phase 4 — ML input contract and honest reporting

```text
PHASE 4 — ML input contract and honest reporting. Addresses ML-01, ML-02, ML-03.
The deployed model stays exactly as it is. ACCURACY NOT VERIFIED stays on every surface.

Background (audits of 2026-11-12 and 2026-11-14): storage/models/rice_yield_model_final.pkl is a 300-tree
random forest trained on 48 municipal monthly rows. Its split thresholds show training ranges of roughly
Area 12-258 ha, Rainfall_6M 54-311 (a mean) and TEMP_RANGE 5.75-9.45 (TMAX-TMIN).
App\Services\Prediction\PredictionEngine::buildModelInput sends farm area (~1 ha), a six-month rainfall
SUM (~1,983) and max-min of monthly mean temperatures (~1.15). All 6,498 stored predictions fall in
4.11-4.18 t/ha with 8 distinct values.

1. Contract file
   - Write python_scripts/export_model_contract.py: loads a model read-only, walks the trees and writes
     storage/models/rice_yield_model_final.contract.json with, per feature, the min and max split
     threshold, plus n_estimators, the feature order and the model file's SHA-256. It must not write the
     .pkl. Record the SHA-256 of every file in storage/models before and after your work and show they
     are unchanged.

2. Inference-time check
   - PredictionEngine: after building the input, compare each feature to the contract (allow a 10% margin)
     and attach input_in_contract (bool) and out_of_contract_features (list) to the prediction details it
     already stores (yield_predictions.model_input JSON; no schema change unless unavoidable — if you need a
     migration, write it but do not run it on the .env database).
   - If the contract file is missing or its SHA-256 does not match the model, treat every input as
     unverified and log one warning per process.

3. Reporting
   - System Health and the IT model evaluation page: show "Predictions within training ranges: X of Y" for
     the latest generation, using existing row styles.
   - Farmer and MAO yield figures: the existing unvalidated-estimate note must be shown whenever
     input_in_contract is false. Do not add numbers, percentages or the word "accurate".

4. Tests
   - Contract test: for the inputs buildModelInput produces for a typical farm, assert the result of the
     contract check is reported truthfully (today: out of contract). The test documents the mismatch; it
     must not be written so that it passes by changing the feature definitions.
   - Variance test: with the ML service faked, three clearly different inputs must be passed through
     unchanged (guards against accidental clamping).
   - tests/Feature/MachineLearning/*Holdout*: keep the tests, and add a reported comparison against a
     same-month climatology or training-mean baseline. Output the metrics; do not assert that the model
     beats the baseline, and do not assert anything that could be read as an accuracy guarantee.

5. Options paper: docs/system-audit/ml-options.md (one page) describing three routes for the owner:
   recover the original training definitions, retrain on documented features with a proper hold-out, or
   replace the figure with a labelled historical baseline. For each: what is needed, what it would and
   would not prove. Do not implement any of them.

Acceptance: full PHPUnit suite and python_scripts/tests green; storage/models hashes unchanged apart from
the new contract JSON; `grep -ri "accura" resources lang` shows no new claim of verified accuracy.
```

---

## Phase 5 — Test coverage and documentation

```text
PHASE 5 — Test coverage and documentation. Closes TEST-01 to TEST-03, FEAT-01, SEC-08; SEC-06 optional.
Prerequisite: Phase 2 item 4 (browser tests use their own storage directory).

1. tests/Browser/account-deletion.spec.mjs:18 (D1) fails on desktop-1280: after IT deletes the account,
   logIn() opens '/' and waits 30 s for '#login-email'; mobile-375 passes. Find the real cause with the
   Playwright trace (test-results/.../trace.zip) before changing anything. If the page is wrong, fix the
   page; if the test is wrong (for example IT is still signed in, or the sign-in card needs '/#login' on
   desktop), fix the test. State which it was.

2. Route x role matrix as a feature test: tests/Feature/RouteAccessMatrixTest.php iterates
   Route::getRoutes(), and for guest, Farmer, MAO Personnel, MAO Head and IT Expert asserts:
   - no response is 5xx;
   - a guest gets 401/302 on every route that has the auth middleware;
   - a role not listed in the route's role: middleware gets 403 (or the documented redirect);
   - a second farmer gets 403/404 on another farmer's farm, planting schedule, notification, profile and
     conversation.
   Send empty bodies to write routes. Skip logout and DELETE profile. Keep it under 60 s.

3. Playwright smoke tests per role (new spec, both viewports): sign in; dashboard renders without console
   errors; Heat Map opens and shows the map container; a farmer creates a planting schedule; a MAO user
   creates and publishes an advisory and a farmer sees it; the notification bell opens. Extend
   tests/Browser/seed-landing.php only as needed.

4. MariaDB-only tests: add docs/testing-mariadb.md with the exact commands to run
   Tests\Feature\TimestampRefreshMigrationTest against a throwaway database (name must start with
   iclimate_test_; the doc must say never to point it at the active database). Do not run it yourself.

5. docs/known-failing-tests.md: replace the content with a dated note that the 37 failures listed on
   2026-10-17 no longer occur (suite green on 2026-11-14), keeping the old list under a "History" heading.

6. Mobile API (FEAT-01, SEC-08): ASK THE OWNER FIRST which option to take and do nothing until answered:
   (a) remove POST api/mobile/login and api/mobile/register and their controller and tests, or
   (b) finish them (token authentication and the first authenticated endpoints).

7. Optional, only if asked: tighten the Content-Security-Policy in
   app/Http/Middleware/SecurityHeaders.php after moving inline scripts to assets.

Acceptance: `caffeinate -dimsu npx playwright test` shows 0 failed three times in a row; the matrix test
is part of the suite and green; full PHPUnit suite green.
```

---

## Phase 6 — Deployment readiness

```text
PHASE 6 — Deployment readiness. Documentation and scripts only; no deployment.
Prerequisite: Phases 0 to 5 done and every Critical and High finding in docs/system-audit/02-findings.md
closed or explicitly accepted by the owner in writing.

1. Write docs/system-audit/deployment-checklist.md: one checklist the owner can follow on the target
   host, covering: APP_ENV=production, APP_DEBUG=false, HTTPS and APP_URL, SESSION_SECURE_COOKIE=true,
   LOG_STACK=daily, LOG_LEVEL=info, DB_TIMEZONE=+08:00 (required: TIMESTAMP columns hold UTC written
   through a +08:00 session), queue worker on analytics,default,broadcasts, scheduler, Reverb behind TLS,
   ML service bound to loopback, config/route/view caching, storage permissions, database account limited
   to SELECT/INSERT/UPDATE/DELETE, backup schedule and off-machine copy.
2. Provide process-supervisor unit files (systemd) for web/php-fpm, queue, scheduler, Reverb and the ML
   service under deploy/, mirroring what scripts/local-service-supervisor.sh does today. Do not install
   them.
3. Write deploy/verify-target.sh (read-only): checks every item of the checklist it can check, runs
   `php artisan iclimate:environment-check`, `migrate:status`, the ML /health call and a backup
   verification, and prints PASS/FAIL per line. It must not write to the database.
4. Write the restore-drill procedure (restore the newest backup into a database whose name starts with
   iclimate_restore_test_, run iclimate:test-restore, drop it) as a documented manual step.
5. Re-run on the target, by the owner: PHPUnit, python tests, Playwright, and the Phase 5 matrix test;
   record results in docs/system-audit/deployment-verification.md (template only; leave results blank).

Acceptance: the checklist and scripts exist and are consistent with the current code and config; nothing
was deployed, installed or run against a real database.
```
