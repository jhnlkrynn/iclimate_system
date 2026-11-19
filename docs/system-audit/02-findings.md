# iClimate System Audit — Findings (2026-11-14)

Every finding below is marked with how it was established:

- **VERIFIED** — observed directly on 2026-11-14 (query, request, test run or file measurement).
- **VERIFIED (code)** — established by reading the code path; not exercised end to end.
- **CARRIED** — found in an earlier audit (date given) and not re-run today.

Severity: **Critical** = blocks deployment or defeats a core purpose · **High** = serious security, reliability
or correctness gap · **Medium** = should be fixed before wider use · **Low** = hygiene.

The deployed ML model keeps the label **ACCURACY NOT VERIFIED**. Nothing in this audit verified the accuracy
of any model.

---

## Summary table

| ID | Severity | Area | Finding | Status |
|---|---|---|---|---|
| SEC-01 | Critical | Accounts | 716 active accounts on known default passwords, including one IT Expert | VERIFIED |
| ML-01 | Critical | ML | Yield model is fed inputs outside its training definitions; every prediction is 4.11–4.18 t/ha | VERIFIED |
| SEC-02 | High | Accounts | Forced password change can be skipped and does not cover non-GET or JSON requests | VERIFIED (code) + partial request evidence |
| SEC-03 | High | Database | MariaDB listens on all interfaces; `root@localhost` has no password; 6 blank-password accounts | VERIFIED |
| OPS-01 | High | Scheduler | The host sleeps after 1 idle minute; scheduler and queue stop; runs are missed with no catch-up | VERIFIED |
| DATA-01 | High | Roles | No account is the MAO Head on the active database, so MAO personnel management is closed to everyone | VERIFIED |
| ML-02 | High | ML | Monthly weather forest performs worse than same-month climatology | CARRIED (2026-11-12) |
| SEC-04 | High | Secrets | Credentials leaked in public Git history were not rotated | CARRIED (2026-11-10) |
| SEC-05 | Medium | Settings | Saving Roles & Permissions with no boxes ticked silently closes every module to Farmer and MAO | VERIFIED |
| OPS-02 | Medium | Cache | File cache is never pruned: 2.7 GB in ~11,000 files | VERIFIED |
| OPS-03 | Medium | Database | `scheduled_task_runs` is never pruned: 92,217 rows, 90 MB of a 120 MB database | VERIFIED |
| OPS-04 | Medium | Logs | `laravel.log` is one 75 MB file at debug level, no rotation | VERIFIED |
| OPS-05 | Medium | Environments | File-backed state is shared by every app instance started from this checkout | VERIFIED |
| DATA-02 | Medium | Data | 12 farms carry unsupported or misspelled barangays; reference tables hold 19 barangays, the app supports 9 | VERIFIED |
| DATA-03 | Medium | Data | 4 active farmer accounts have no profile and no farm | VERIFIED |
| DATA-04 | Medium | Data | 0 of 124 rice-production rows are verified; Climate Records table is empty | VERIFIED |
| ML-03 | Medium | ML tests | Hold-out tests only assert metrics are non-negative numbers and do not cover the deployed model | CARRIED (2026-11-12) |
| FEAT-01 | Medium | Mobile API | `/api/mobile/login` returns no token; there is no authenticated mobile endpoint | VERIFIED (code) |
| TEST-01 | Medium | Tests | One browser test fails on desktop (account deletion D1) | VERIFIED |
| TEST-02 | Low | Tests | 5 MariaDB-only tests are skipped; nothing runs the suite against MariaDB | VERIFIED |
| TEST-03 | Low | Docs | `docs/known-failing-tests.md` lists 37 failures; the suite is now green | VERIFIED |
| OPS-06 | Low | Queue | 15 stale `failed_jobs` rows from the 5 and 10 November outages | VERIFIED |
| OPS-07 | Low | Config | Active app runs with `APP_ENV=local`, `LOG_LEVEL=debug`, no secure-cookie setting | VERIFIED |
| SEC-06 | Low | Headers | CSP allows `unsafe-inline`, `unsafe-eval` and any `https:` source | VERIFIED (code) |
| SEC-07 | Low | Logs | Outgoing mail was written to the log at debug level (66 entries) | VERIFIED |
| SEC-08 | Low | Mobile API | `/api/mobile/register` has no captcha (the web form has one) | VERIFIED (code) |
| DATA-05 | Low | Data | `verified` route middleware does nothing: `MustVerifyEmail` is off; 729 accounts are unverified | VERIFIED |
| DATA-06 | Low | Data | One password-reset token from July is still stored | VERIFIED |

---

## Critical

### SEC-01 — Active accounts on known default passwords

- **Evidence.** Read-only check of the active database:
  - 713 Farmer accounts share one password hash that matches the literal `Password@123`
    (`must_change_password = 1`).
  - 144 more Farmer accounts share a second single hash (`must_change_password = 0`); its value was not
    identified.
  - Staff accounts #8 (MAO Personnel), #9 (IT Expert) and #15 (MAO Personnel) match a documented default
    password and have `must_change_password = 0`. Accounts #8 and #9 last signed in on 2026-11-11.
  - Only 6 farmer accounts have ever signed in.
- **Affected files.** `config/iclimate.php:68` (`mao_default_password` falls back to `Password@123`),
  `database/seeders/FarmerMasterlistSeeder.php:60-61,143` (one shared hash for the whole masterlist),
  `database/seeders/FarmerDemoSeeder.php:74`, `app/Http/Controllers/MaoPersonnelController.php:53,122`.
- **Root cause.** Bulk-created accounts get one shared password; the literal default is in the repository;
  staff accounts were created without the must-change flag.
- **Impact.** Anyone who knows the default can sign in as 713 farmers and as an IT Expert (user management,
  backups and restore, model upload). The web server is bound to `127.0.0.1` today, so exposure is local;
  this blocks any networked deployment.
- **Recommended fix.** Give each bulk-created account its own random password or a one-time set-password
  link; remove the literal fallback; set `must_change_password` on every account still on a default and on
  staff #8, #9, #15; rotate those three now (owner action).

### ML-01 — Yield predictions carry almost no information

- **Evidence.** `yield_predictions` on the active database: 6,498 rows (6,414 farm, 84 barangay), **8
  distinct values**, range **4.11–4.18 t/ha**. This matches the 2026-11-12 audit: the deployed
  `rice_yield_model_final.pkl` was trained on municipal monthly rows (`Area` 12–258 ha, `Rainfall_6M` as a
  mean of 54–311, `TEMP_RANGE` 5.75–9.45), while `PredictionEngine::buildModelInput` sends farm area
  (~1 ha), a six-month rainfall **sum** (~1,983) and a range of monthly mean temperatures (~1.15).
- **Affected files.** `app/Services/Prediction/PredictionEngine.php` (`buildModelInput`),
  `python_scripts/rice_yield_service.py`, `python_scripts/predict.py`, `storage/models/rice_yield_model_final.pkl`.
- **Root cause.** The feature definitions used at inference differ from the ones used at training; the
  original training script and dataset are not in the repository.
- **Impact.** Farmers and MAO see a yield figure that is effectively the same for every farm, season and
  weather outlook. The system runs end to end, which hides this.
- **Recommended fix.** Do not change the model without owner approval. First add an input-contract check
  (range guard per feature, logged and surfaced), keep the "accuracy not verified" wording on every yield
  figure, and add a test that fails when predictions across clearly different inputs collapse to one value.
  Then, with approval, either recover the training definitions or retrain.

---

## High

### SEC-02 — Forced password change is bypassable

- **Evidence.** `app/Http/Middleware/EnsurePasswordChanged.php` lets a request through when it is not `GET`,
  when it expects JSON, or when the session holds the skip flag. `FirstPasswordController::skip` is allowed
  while `iclimate.mao_allow_skip_password_change` is true, which is the default. In the clone crawl, a
  must-change farmer was redirected on 108 HTML `GET` pages, as designed; the bypass paths were established
  from the code, not exercised.
- **Impact.** With SEC-01, the 713 default-password accounts can stay on the default indefinitely.
- **Recommended fix.** Apply the check to every method and to JSON requests (answer 409/403 with a
  message); default the skip option to off; keep only logout and the change-password routes open.

### SEC-03 — Database server exposure

- **Evidence.** MariaDB 10.4.28 listens on `*:3306` (web, Reverb and ML are on `127.0.0.1` only).
  `mysql -uroot` on `127.0.0.1` connects with no password. `mysql.user` has 6 accounts with a blank
  password. The application account `iclimate_app` is correctly limited to
  `SELECT, INSERT, UPDATE, DELETE` on the active database.
- **Root cause.** XAMPP defaults; no `bind-address`.
- **Recommended fix (owner action, needs sudo).** Set `bind-address=127.0.0.1`, give `root` a password,
  remove the anonymous and blank-password accounts. No application code change.

### OPS-01 — Host sleep stops the scheduler and queue

- **Evidence.** `pmset` shows `sleep 1`. The every-minute task `iclimate:publish-scheduled-alerts` ran at
  01:18, 01:19, 01:26–01:28 and then not until 01:48 UTC. The 01:30 UTC `iclimate:automation-run` did not
  run: the newest `analytics_runs` row is still 18:30 UTC the previous day. `schedule:work` does not run
  missed tasks afterwards. The supervisor scripts do not hold a wake lock.
- **Impact.** Weather refresh, advisories, alerts, risk refresh, backups and yield warm-ups stop whenever the
  machine idles. The earlier incident on 2026-11-11 (41 failed analytics runs) had the same shape.
- **Recommended fix.** Hold a wake assertion from the supervisor (`caffeinate -s` on macOS) or run on an
  always-on host; add a System Health check that flags a gap between consecutive every-minute runs.

### DATA-01 — No MAO Head on the active database

- **Evidence.** All 3 MAO accounts have `is_mao_head = 0`; `mao_user_barangays` is empty.
  `MaoPersonnelController::authorizeHead` answers 403 unless `isMaoHead()`. On the isolated test server the
  head account could create personnel (422 on an empty form) and a non-head got 403, so the code is correct;
  the data is missing.
- **Impact.** Nobody can add, edit or reset MAO personnel. Barangay assignment and the assigned/municipal
  view switch are unused, so they are untested on real data.
- **Recommended fix.** Owner decides who is the Municipal Agriculturist; set the flag through an approved,
  audited path (seeder run or a new IT-only control on the Users page). The Users row already reads
  `is_mao_head` (`resources/views/users/_row-actions.blade.php:23`) but nothing lets IT set it.

### ML-02 — Weather forest below climatology (CARRIED, 2026-11-12)

Year-ahead backtest 2024–2025: rain MAE 119 mm against 89 mm for same-month climatology. Not re-run today.
`monthly_weather_predictions` holds 14 `monthly_weather_rf_v5` rows generated 2026-11-12.

### SEC-04 — Leaked credentials in Git history (CARRIED, 2026-11-10)

Commit `afa3be2f` and `.env.mysql-backup` exposed credentials in public history. The APP_KEY was rotated;
Groq and mail credentials were not, as of 2026-11-10. Not re-checked today.

---

## Medium

### SEC-05 — Empty Roles & Permissions save closes every module

- **Evidence.** A `POST /admin/permissions` with no `modules` field stored an empty list for all 14 modules.
  After that, a MAO account was redirected to its dashboard from 68 pages with "Your role does not have
  access to that page". This was first seen by accident during this audit (see the incident note in
  `README.md`) and is reproducible.
- **Affected files.** `app/Http/Controllers/SystemSettingsController.php:116-122`,
  `app/Support/ModuleAccess.php:88-99`.
- **Root cause.** `(array) $request->input('modules', [])` treats a missing field as "untick everything";
  no validation, no confirmation.
- **Recommended fix.** Require the field to be present, refuse a save that removes every module from a role
  unless confirmed, and show what will change before saving.

### OPS-02 — File cache never pruned

`storage/framework/cache` is 2.7 GB in about 11,000 files. The file store only removes an entry when it is
read after expiry. Fix: a scheduled prune of expired entries, or move the cache to the database store.

### OPS-03 — `scheduled_task_runs` grows without limit

92,217 rows since 2026-10-07, 90.4 MB of a 119.5 MB database; about 2,400 rows a day. No prune exists
(`app/Models/ScheduledTaskRun.php`, `routes/console.php`). Fix: keep 7–14 days, prune daily.

### OPS-04 — No log rotation

`storage/logs/laravel.log` is 75.9 MB and 394,000 lines, `LOG_STACK=single`, `LOG_LEVEL=debug`. Fix:
`LOG_STACK=daily` with a retention, `LOG_LEVEL=info` outside development.

### OPS-05 — Environments share file-backed state

- **Evidence.** A second app instance on a throwaway SQLite database wrote into the active app's
  `storage/`: the settings file `storage/app/system-settings.json`, cache entries, a comparison report in
  `storage/models/`, and a backup. `LARAVEL_STORAGE_PATH` is ignored under PHP's built-in server because
  Laravel reads it from `$_ENV`/`$_SERVER` only. `config/database.php:71` hard-codes the active database
  name as the default for the backup connection, so a backup requested from a test instance dumps the
  active database. `tests/Browser/serve.sh` has the same exposure for anything file-backed.
- **Affected files.** `app/Support/SystemSettings.php:53-56`, `config/database.php:66-75`,
  `config/cache.php`, `tests/Browser/serve.sh`.
- **Recommended fix.** Make the settings path, cache path, backup directory and model-report directory
  depend on an explicit environment value; have `serve.sh` call `useStoragePath()` through a small test
  front controller or set per-path env values; drop the hard-coded database default.

### DATA-02 — Barangay values out of step

`LianBarangays` supports 9 barangays. `barangay_profiles` and `heatmap_areas` hold 19 rows each. `farms`
has 12 rows the app cannot place: `—` (3), `Barangay 4 (Pob.)` (3), `Barangay 1 (Pob.)` (2),
`BARANGAY 1 POB` (1), `Humayingan` (1), `LUYAHN` (1), null (1). Those farms get no weather, risk or
advisory scope. Fix: run the existing reconciliation commands in dry-run, review, then apply with approval;
validate barangay on every write path.

### DATA-03 — Farmer accounts without a profile

4 active Farmer accounts have no `farmer_profiles` row and no farm. Fix: list them for the MAO; create the
profile on first sign-in or deactivate.

### DATA-04 — Unverified and missing agronomic data

`rice_productions`: 124 rows, 0 verified, 14 tagged `demo_seed` in remarks (the 2026-11-12 note found 110
rows are seeded allocations of municipal totals). `climate_records`: 0 rows, so the Climate Records module
shows nothing and climate history comes from the bundled Ambulong file. Fix is a data decision for the MAO;
the app should label seeded rows clearly wherever they feed a figure.

### ML-03 — Hold-out tests do not test accuracy (CARRIED, 2026-11-12)

`tests/Feature/MachineLearning/*Holdout*` assert metrics are numbers ≥ 0 and cover the candidate model only.

### FEAT-01 — Mobile API is incomplete

`app/Http/Controllers/Api/MobileAuthController.php` checks credentials and returns the user record, but no
token or session; `routes/api.php` has no authenticated mobile route and Sanctum is not installed. Decide:
finish it (token auth, scoped endpoints) or remove the two routes.

### TEST-01 — Failing browser test

`tests/Browser/account-deletion.spec.mjs:18` (D1) fails on `desktop-1280`: after IT deletes the account,
the helper opens `/` and waits 30 s for `#login-email`. The same test passes on `mobile-375`. Likely cause:
the helper does not sign IT out first or does not open `/#login` on desktop; not diagnosed further. Whether
this is a test defect or a page defect is **not established**.

---

## Low

- **TEST-02.** `TimestampRefreshMigrationTest` (5 tests) is skipped on SQLite. MariaDB-specific behaviour
  (timestamp columns, JSON columns) is not covered by any automated run.
- **TEST-03.** `docs/known-failing-tests.md` describes 37 failures on `d57a05ee`; today 0 fail.
- **OPS-06.** `failed_jobs`: 9 broadcast jobs (2026-11-10, Reverb down) and 6 analytics jobs (2026-11-05, ML
  service down). `analytics_runs` has 60 historical failures, the last on 2026-11-10.
- **OPS-07.** `APP_ENV=local`; `SESSION_SECURE_COOKIE` unset; fine for `http://127.0.0.1`, wrong for a
  deployment.
- **SEC-06.** `app/Http/Middleware/SecurityHeaders.php` documents the loose CSP as temporary.
- **SEC-07.** 66 `local.DEBUG: From: iClimate` entries: mail bodies in the log, which can include
  temporary passwords.
- **SEC-08.** `/api/mobile/register` is throttled (3 per 10 minutes) but has no captcha.
- **DATA-05.** `User` does not implement `MustVerifyEmail`, so the `verified` middleware on ~290 routes is a
  no-op. Either adopt verification or remove the middleware to avoid a false sense of protection.
- **DATA-06.** `password_reset_tokens` has one row from 2026-07-15.

---

## Checked and found sound

- **Authentication.** Login throttling, inactive/pending/rejected/deleted account refusal, guest redirects.
- **Authorization.** 300 routes × 6 personas (guest, two farmers, MAO, MAO Head, IT) on an isolated server:
  no route gave a role access it should not have; every cross-owner attempt (another farmer's farm,
  schedule, notification, profile, conversation) answered 403 or 404; all 146 non-public write routes
  answered 401 to a guest.
- **Server errors.** No HTTP 500 on any `GET` or write route for any persona, on the seeded SQLite database
  or on the clone of real data.
- **Injection surface.** No request-driven raw SQL; unescaped Blade output is limited to server-built markup;
  no shell call takes request input.
- **Database.** 0 pending migrations; every table has a primary key; 87 foreign keys; no orphaned farms,
  profiles or schedules; no duplicate emails; no harvest date before its planting date.
- **Services.** Web, Reverb, queue worker, scheduler and ML service all running under the supervisor; ML
  `/health` reports both models loaded; 0 jobs waiting.
- **Backups.** Last verification passed 2026-11-12; 28 older failures end on 2026-11-03.
