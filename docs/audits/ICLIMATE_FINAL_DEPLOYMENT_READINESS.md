# iClimate — Final Deployment Readiness Audit

**Date:** 2026-11-18 · **Code state:** `main` @ `c34c6e5b` plus about 600 uncommitted files (nothing committed) · **Active DB:** `iclimate_staging_20261103_fix3` (MariaDB 10.4.28)

## Verification labels

| Label | Meaning |
|---|---|
| **LIVE** | Done in a real browser or with real HTTP requests by a signed-in user, against a running server and real data |
| **LIVE-CLONE** | LIVE, but on an isolated copy: a separate APFS copy of the code, its own `storage/`, its own Reverb (port 8091) and mail sent only to the log, running against `iclimate_rt_audit_20261111` (a real-data copy of the active database) with the six pending migrations applied. The active database and live services were not changed. The clone was restored afterwards. |
| **LIVE-READONLY** | Read-only check against the active database or a running service |
| **AUTO** | Covered by an automated test suite only |
| **CODE** | Checked by reading the code only |
| **NOT VERIFIED** / **FAILED** | As written |

---

## A. Executive summary

**Decision: NO-GO for production today. CONDITIONAL GO is reachable once the P0 items in section I are closed.** The
application works end to end for Farmers and MAO Staff when its schema is in place: every page and workflow tested
on the real-data clone worked. Production deployment is blocked by items that code alone cannot fix:

1. **The six migrations are still pending on the active database.** Account Requests decisions and association
   reporting cannot work there yet. The pre-migration guards added on 2026-11-18 keep every other page working.
2. **Credentials.** 721 Farmer accounts, 1 MAO account and 1 former IT account still use the shared password
   `Password@123`. The forced password change can be skipped for the session and does not apply at all to the mobile
   API, which has no password-change endpoint.
3. **No MAO Head on the active database**, and before this audit there was no way to designate one, because only a
   Head could do it and the IT role that used to do it is gone. Fixed in code: `iclimate:assign-mao-head`. It still
   has to be run, with approval.
4. **MariaDB** listens on all network interfaces (`*:3306`). The earlier audit found a passwordless root account; that
   could not be rechecked here. MariaDB also restarted repeatedly on 11–17 November and is not supervised.
5. **Production hosting has never been provisioned or tested.** The Hostinger VPS kit in `deploy/hostinger/` is
   templates only. Everything verified here ran on the development Mac.
6. **ML scientific accuracy: NOT VERIFIED.** The deployed yield model gives 4.00–4.18 t/ha for every input,
   including impossible ones, because its inputs do not match how it was trained.

**Fixed during this audit** (each has a regression test; full suites green, see G):

| # | Defect | Severity |
|---|---|---|
| 1 | Heat Map layers never loaded for any signed-in user (`/api/heat-map` returned 401 in every browser) | P1 |
| 2 | MAO association-reports page showed only the latest period, so reports for overdue or earlier periods could not be opened | P1 |
| 3 | Nobody could designate an MAO Head (no in-app path) | P0 (fix in code; still has to be run) |
| 4 | ML service accepted physically impossible inputs (negative area, 60 °C, 1e9 mm rain) and returned a normal-looking yield | P1 |
| 5 | ML `/metrics?dataset_path=` read any CSV path on the server and returned 500 for others | P2 |
| 6 | (2026-11-18, earlier the same day) On the un-migrated schema every Farmer page returned 500. Fixed with guards that switch off after migration | P0 |

---

## B. Feature verification matrix

Persona accounts on the clone: Farmer #1284 (5 farms), chairman candidate #1372, ordinary MAO Staff #8, MAO Head #2,
former IT #3, Pending applicant #1594, plus 3 applicants registered during the test. A crawl of 93 routes × 6
personas at desktop width, plus 212 page loads at 375 px and 768 px, found **0 server errors (5xx), 0 horizontal
overflow, and no console errors** apart from the Heat Map 401 (fixed) and expected 403/429 responses.

### Farmer

| Feature | Status | Evidence |
|---|---|---|
| Registration (mobile API), pending status | **LIVE-CLONE** | 3 sign-ups → 201 Pending. Repeat sign-ups from the same address within minutes → 429 (rate limit works) |
| Web registration form | LIVE-CLONE (page) · AUTO (submit) | `/register` 200. Submitting needs the image captcha, so the submit is covered by `RegistrationTest` |
| Approval → sign-in | **LIVE-CLONE** | Applicant approved by ordinary MAO Staff; signs in; "Account approved" notification shown |
| Pending / Rejected cannot sign in | **LIVE-CLONE** | Both stay on `/`; sessions, API and channels refused (also AUTO) |
| Rejected → correct and resubmit | **LIVE-CLONE** | `/registration/resubmit` UI → back to Pending with note; earlier Rejected review kept in history; then approved |
| Login / logout | LIVE-CLONE (login) · AUTO (logout) | Logout covered by Playwright `mobile-navigation`, `account-deletion` |
| Account recovery | LIVE-CLONE (page) · AUTO | `/forgot-password` 200; reset flow in `Auth/PasswordResetTest` |
| Farmer Dashboard | **LIVE-CLONE** | 200 at desktop, mobile and tablet; no console errors |
| Farm registration and management | LIVE-CLONE (pages, isolation) · AUTO (create/edit/delete) | Own farm edit 200; another farmer's farm edit 404; PUT on another farmer's farm → 404 |
| Planting schedules | LIVE-CLONE (pages, isolation) · AUTO (create) | Own schedule edit 200; another farmer's → 403 |
| Rice-yield prediction display | LIVE-CLONE (shown) · **ACCURACY NOT VERIFIED** | See section F |
| Weather monitoring and forecasts | **LIVE-READONLY** · LIVE-CLONE | 42/42 weather fetches in 24 h succeeded; weather pages 200 |
| Advisories and notifications | **LIVE-CLONE** | Pages 200; decision and reminder notifications delivered; Reverb push verified |
| Reports and submission history | **LIVE-CLONE** | `/farmer/my-reports` 200; `/farmer/my-reports/pdf` 200, application/pdf, 884 kB |
| Heat Map | **FAILED → FIXED**, AUTO (browser) | Layer API 401 in every browser; fixed; new Playwright test passes (desktop and mobile) |
| Association chairman | **LIVE-CLONE** | Assigned chairman: dashboard, submit with PDF, revise after "needs revision", locked after acceptance (403). Ordinary Farmer → 403 |

### MAO Staff

| Feature | Status | Evidence |
|---|---|---|
| MAO Dashboard and monitoring | **LIVE-CLONE** | 200 at all three widths; pages load in about 50–150 ms of server time (calendar about 0.6 s) |
| Account Requests: list, count, search, details | **LIVE-CLONE** | Search finds applicant; details show contact information and review history |
| Approve / reject (reason required) / resubmit | **LIVE-CLONE** | Ordinary Staff #8, no Head flag. Reject without a reason → refused, still Pending |
| Concurrent decisions | **LIVE-CLONE** · AUTO | Simultaneous approve (Staff) and reject (Head) → 302 and 409; one decision recorded |
| Farmer and farm management | LIVE-CLONE (pages) · AUTO | `farmer-profiles`, `farmers/search`, `mao/farm-associations` 200 |
| Association directory and chairman assignment | **LIVE-CLONE** · usability gap (P2) | Assignment works, but requires typing a raw account ID |
| Report submission monitoring | **FAILED → FIXED** · LIVE-CLONE | Overdue period's report unreachable; period selector added; re-run passes |
| Deadlines and overdue reminders | **LIVE-CLONE** | `associations:send-reminders` run twice → one reminder per association and period, deduplicated |
| Report review and revisions | **LIVE-CLONE** | "Needs revision" without notes refused; with notes recorded; accept; manual reminder |
| Supporting documents | **FAILED (gap)** · P2 | Upload stored privately; MAO has no way to open the document |
| Advisories and announcements | LIVE-CLONE (pages) · AUTO (create) | `management/advisories`, `announcements`, `mao/alerts` 200 |
| Heat Map, analytics | **FIXED** (Heat Map) · LIVE-CLONE | `analysis/*`, `weather-predictions` 200 |
| Report generation and downloads | **LIVE-CLONE** | CSV 200 (4.4 kB), XLSX 200, PDF 200 (883 kB), print 200 |
| Notifications and Reverb | **LIVE-CLONE** | MAO alerted on sign-up and on report submission; `notification.changed` received over Reverb on the chairman's private channel |
| Account administration restricted to the Head | **LIVE-CLONE** | Staff `/users` 403, POST `/users` 403; Head `/users` 200 |
| IT role inaccessible | **LIVE-CLONE** · LIVE-READONLY | Former IT login refused; only the 4 public pages reachable; 0 IT accounts have access on the active DB |

---

## C. System health (development Mac, 2026-11-18)

| Component | Status | Evidence |
|---|---|---|
| Laravel web (:8000, `php -S` under supervisor) | Healthy (LIVE-READONLY) | `/up` 200 |
| MariaDB | **Unreliable** | Uptime since 2026-11-17 08:53 UTC; "connection refused" windows logged on 11, 12, 13, 14, 15, 16 and 17 Nov; not under the service supervisor; listens on `*:3306` |
| Python ML (:8010) | Healthy | `/health` ok, rice and weather models loaded; inference 20–50 ms |
| Queue worker | Healthy | 1 process; 0 queued jobs; 0 failed jobs in 24 h |
| Scheduler | Healthy | 1 `schedule:work` process (no duplication); logs current (UTC timestamps) |
| Reverb (:8080) | Healthy | `/up` 200 |
| Weather APIs | Healthy | 42/42 fetches in 24 h succeeded; last fetch about 10 minutes before the check |
| SMTP | Configured | Gmail STARTTLS + AUTH succeeded on 2026-11-17 (no message sent). Account emails send inline after the decision is saved |
| Historical errors | Historical, not reproducible | `Undefined variable $maoNav` (54 entries, last at 2026-11-17 14:59 UTC; crawl of every MAO page today found no 500s) |

---

## D. Security assessment

| Area | Result | Status |
|---|---|---|
| Role isolation (Farmer / MAO / Head / former IT / Pending) | 93 routes × 6 personas: no unexpected 200s; Farmers kept from other Farmers' farms (404) and schedules (403) | **PASS** (LIVE-CLONE) |
| Account approval security | Farmer → approve 403; reason required; stale or concurrent decisions → 409 | **PASS** |
| CSRF | POST without token → 419 | **PASS** (LIVE-CLONE) |
| Upload validation | All upload rules restrict type and size (`mimes` / `mimetypes`, `max`); a `.php` upload was rejected | **PASS** (CODE + LIVE-CLONE) |
| Reverb private channels | `user.{id}` delivered to its owner; Pending, Rejected and IT sessions refused (AUTO) | **PASS** |
| Rate limiting | Mobile registration 429 after repeated sign-ups; login throttle and captcha | **PASS** |
| Exposed files | `/.env`, `/.git/config`, `/composer.json` → 404; `APP_DEBUG=false` | **PASS** (LIVE-READONLY) |
| **Shared default passwords** | 721 Farmer, 1 MAO, 1 IT account on `Password@123`; 713 flagged `must_change_password` | **FAIL — P0** |
| **Forced password change** | Skippable for the session; not applied to the mobile API (no API password-change endpoint) | **FAIL — P0** |
| **MariaDB exposure** | Listens on `*:3306`; earlier audit found passwordless root (not rechecked) | **FAIL — P0 (owner/DBA)** |
| ML service | Bound to 127.0.0.1; `/model/switch` token-protected; `/metrics` path restricted (fixed) | PASS after fix |
| Production TLS / secure cookies | Templates only (`nginx-iclimate.conf`, `SESSION_SECURE_COOKIE` not set locally) | **NOT VERIFIED** |

---

## E. Database and migration status

| Item | Status |
|---|---|
| Pending migrations on the active DB | **6 pending** (`2026_11_16_000001`–`000004`, `2026_11_17_000001`–`000002`). **BLOCKED**: needs the temporary migration account (see `deploy/hostinger/phase-18-dba-handoff.md`) and the owner's approval |
| Schema compatibility | Running code is ahead of the schema. Guards (`RequireSchema`, `FarmerAccountReviewService::available()`) keep pages working; review decisions and association reporting answer "being updated, nothing was saved" until the migrations run |
| Rehearsal | Up → down → up cycle on the real-data clone; final run on an exact copy (about 0.6 s, 877 users kept) |
| Backups | `iclimate_2026-11-17_1625.backup` restore-verified (69 tables, 121,795 rows, checksums equal); runbook takes another one inside the maintenance window |
| Integrity (active, LIVE-READONLY) | 0 duplicate emails; 0 orphaned profiles, farms, predictions, notifications or schedules; 4 Active Farmers without a profile; 2 former IT accounts still `Active` in the data (no access) |
| Historical production records | 124 `rice_productions` rows, **0 verified** (most are seeded allocations of municipal totals) |
| Prediction storage | 6,498 rows; regenerated 2026-11-17 15:40 UTC; every row stores `model_input` and `model_version` (provenance is recorded) |

---

## F. ML readiness

| Question | Status |
|---|---|
| Inference works end to end (Laravel → FastAPI → DB → UI) | **PASS** (LIVE-READONLY / LIVE-CLONE) |
| 12-feature schema | RAINFALL, TEMP_AVG, TEMP_RANGE, Area, Previous_Rainfall, Previous_Temp, Rainfall_6M, Temp_3M, Temp_6M, Seasonal_Rainfall, Seasonal_Temp, Season. Required, finite and (now) physically plausible |
| Invalid input rejection | Missing, non-numeric, NaN, bad season or month → 422 before the fix; **negative or absurd values → 200 before the fix, 422 now** (Python tests; the running service needs a restart to pick this up) |
| Model checksum | `rice_yield_model_final.pkl` sha256 `f3a54e12…b777c`, scikit-learn 1.6.1, `version` null |
| Feature compatibility | **FAIL**: the application sends `Area` ≈ 1.4 ha (training: 12–258 ha municipal area), `Rainfall_6M` as a 6-month sum ≈ 1,983 (training: a 54–311 mean), `TEMP_RANGE` ≈ 1.15 (training: 5.75–9.45, TMAX − TMIN) |
| Output behaviour | 6,498 stored predictions span 4.11–4.18 t/ha; crafted inputs including impossible ones give 4.00–4.13 |
| Training data | 48 municipal monthly rows (from the 2026-11-12 tree inspection); farm-level vs municipal mismatch; no independent farm-level yield observations |
| Weather model | 2024–25 backtest worse than climatology (rain MAE 119 vs 89 mm, from 2026-11-12) |
| **Scientific accuracy** | **ACCURACY NOT VERIFIED** |

No model was retrained, replaced or modified.

---

## G. Testing results

| Suite | Passed | Failed | Skipped | Assertions / notes |
|---|---|---|---|---|
| PHPUnit (full) | 1639 | 0 | 5 | 12,286 assertions; 5 skipped are MariaDB-only tests; 524 s |
| Python (pytest, `.venv-ml`) | 53 | 0 | 0 | 2 warnings (anyio/starlette deprecation) |
| JavaScript (`node --test`) | 19 | 0 | 0 | — |
| Playwright (desktop 1280 + mobile 375) | 166 | 0 | 40 | includes the new `heat-map-layers.spec.mjs` (4); skips: viewport-specific tests (drawer or menu only on mobile) plus 10 landing-page tests unconditionally marked `test.skip` before this audit (×2 viewports); worth reviewing (P3) |
| Production build (`vite build`) | OK (built in 1.65 s) | | | |
| Live clone crawl | 558 desktop page loads + 212 responsive | 0 × 5xx | — | 0 overflow; console errors only on the Heat Map 401 (fixed) and expected 403/429 |
| Live clone workflows | 24/27 (run 1) and 13/13 association steps (run 2) | 3 → fixed | — | Run 1's 3 failures were the MAO period defect (#2); run 2's F1/F2 steps hit the registration rate limit (expected) |
| Migration rehearsal (MariaDB clone) | 6/6 up, 6/6 down, 6/6 up | 0 | — | snapshot/compare script: all checks OK; tamper tests caught |
| MariaDB workflow tests (2026-11-17) | 26 | 0 | — | plus 10/10 concurrent review races |
| Deployment configuration | `composer validate` OK; config not cached; one scheduler and one worker | — | — | production host NOT VERIFIED |

---

## H. Defects and fixes made in this audit

| # | Root cause | Change | Regression test |
|---|---|---|---|
| 1 Heat Map layers 401 | Route in `routes/api.php` (no session) but called by the browser with the session cookie; feature tests used `actingAs()`, which bypasses sessions | Moved to `routes/web.php` (same URL, throttle, auth; Pending/Rejected/IT still 401) | `tests/Browser/heat-map-layers.spec.mjs` (4 browser runs) |
| 2 Overdue period reports unreachable | `index()` loaded only `latest('due_at')` | `?period=` selector with "(overdue)" marker; chairman column shows the assigned account | `tests/Feature/MaoAssociationReportPeriodsTest.php` |
| 3 No way to designate an MAO Head | Only the Head could assign one; the IT role was removed | `php artisan iclimate:assign-mao-head {email} [--apply]` (dry-run default, exactly one Head, audit-logged) | `tests/Feature/AssignMaoHeadCommandTest.php` |
| 4 Impossible ML inputs predicted | Only finiteness was checked | `PLAUSIBLE_RANGES` in `rice_yield_service._numeric_feature` (physical bounds, not training ranges; current production inputs stay valid) | `python_scripts/tests/test_smoke.py` (2 tests) |
| 5 `/metrics` arbitrary path | `dataset_path` used as given | Restricted to `.csv` files inside `storage/app/private/ml`; 422 otherwise | `python_scripts/tests/test_smoke.py` |

Earlier on 2026-11-18: the pre-migration compatibility guards (`PreMigrationCompatibilityTest`, 6 tests) and the MariaDB migration
fixes (identifier length, timestamp defaults, rollback FK order).

---

## I. Prioritized implementation plan

### P0 — Critical (blocks production)

**P0-1 Apply the six migrations.**
Problem: schema behind code. Root cause: the app account has no DDL rights (by design).
Files: `deploy/hostinger/phase-18-*.md`, `scripts/deploy/two-role-migration-check.sh`.
Solution: DBA creates the temporary account, then the operator follows the runbook.
Dependencies: DBA, owner approval.
Verify: `compare` all OK, 0 pending, `PreMigrationCompatibilityTest` guards inert.
Done when: Account Requests decisions and association reporting work on the active DB.

**P0-2 Designate the MAO Head.**
Problem: 0 Heads, so nobody can administer accounts.
Solution: `php artisan iclimate:assign-mao-head <email>` (dry run), then add `--apply`.
Dependencies: owner chooses the person; approval to write.
Verify: that account opens `/users`; exactly one Head.
Done when: account administration is possible in the app.

**P0-3 Retire shared default passwords.**
Problem: 723 accounts on `Password@123`.
Root cause: seeded or imported accounts.
Files: `FlagDefaultPasswords`, `EnsurePasswordChanged`, `Api/MobileAuthController`.
Solution:
- (a) run `iclimate:flag-default-passwords --apply`;
- (b) make the web change mandatory (remove the session "skip");
- (c) add an authenticated mobile password-change endpoint, expose `must_change_password` in the login response, then return 409 from other API routes;
- (d) rotate the MAO and IT credentials now.

Dependencies: a mobile app release for (c), with a staged rollout so the 713 mobile users are not locked out.
Verify: 0 accounts match `Password@123`; flagged web and mobile users cannot reach protected routes until they change their password.
Done when: the hash audit returns 0.

**P0-4 Secure MariaDB.**
Problem: listens on `*:3306`; root possibly passwordless.
Solution: `bind-address=127.0.0.1`, root password, remove anonymous users, firewall.
Dependencies: DBA, then a restart of MariaDB in a window.
Verify: `netstat` shows `127.0.0.1.3306` only; root login without a password fails.
Done when: both are verified.

**P0-5 Provision and verify production hosting.**
Problem: no production host exists.
Files: `deploy/hostinger/*`.
Solution: follow its README; enable TLS, `SESSION_SECURE_COOKIE=true`, `APP_ENV=production`, systemd units for queue, Reverb, ML and the scheduler timer; MariaDB supervised.
Dependencies: VPS, domain, secrets.
Verify: `deploy/hostinger/verify-production.sh`, the crawl and workflow scripts from this audit run against the VPS, and a restore drill.
Done when: section C is green on the VPS.

### P1 — High

**P1-1 Restart the ML service** after review, to load fixes #4 and #5. Verify: `/predict` with area −5 → 422.

**P1-2 MariaDB reliability.** Repeated outages on 11–17 Nov. Supervise MariaDB (launchd or systemd); alert on `/up` DB failures; on the Mac, prevent sleep (see `docs/system-audit`). Verify: no "connection refused" entries over 7 days.

**P1-3 Yield model input compatibility.**
Problem: feature definitions differ from training (Area, Rainfall_6M, TEMP_RANGE); output is constant.
Dependencies: the original training dataset and script, and owner approval to retrain or redefine features.
Verify: a hold-out against independent observations; prediction spread reflects the inputs.
Until then, label predictions "Estimate — accuracy not verified" (already the policy).

**P1-4 Record independent yield observations** (verified `rice_productions`, now 0/124) to make P1-3 measurable.

### P2 — Medium

**P2-1 MAO cannot open uploaded supporting documents.** Add a MAO-only, scope-checked download route (`Storage::download`) and a link on the report page. Test: Staff can download; chairmen of other associations and Farmers get 403.

**P2-2 Chairman assignment usability.** Replace "type the account ID" with a search or select of approved Farmers in the association's barangay; show the assigned chairman (done in #2).

**P2-3 MAO report view scope.** `show()` has no barangay check while review and remind do. Decide whether municipal-wide viewing is intended; if not, add `MaoScope::canManageBarangay`.

**P2-4 4 Active Farmers without a profile.** Prompt them to complete it on next sign-in. Verify: the count returns 0.

**P2-5 Landing weather widget 429.** `/api/weather/current` shares a tight per-IP limit; schools and offices behind one NAT may hit it. Raise the limit or cache publicly.

### P3 — Low

- **P3-1** The association pages use bare Bootstrap markup that differs from the rest of the MAO UI; align them with the MAO design system.
- **P3-2** Former IT accounts (#3, #9) still have status `Active` in the data. Set them to `Inactive` with approval; access is already denied.
- **P3-3** Log noise: the historical `$maoNav` errors and the migration-rehearsal errors in `laravel.log`. Rotate the logs after deployment.

---

## J. Final GO / NO-GO

| Category | Status |
|---|---|
| Laravel Backend | **PASS** (full suite green; 0 × 5xx in the live crawl) |
| Farmer Features | **PASS** on the clone (Heat Map fixed) — on the active DB conditional on P0-1 |
| MAO Features | **PASS** on the clone (period fix) — supporting-document access is a P2 gap |
| Account Approval | **PASS** on the clone · **BLOCKED** on the active DB until P0-1 |
| Association Reporting | **PASS** on the clone · **BLOCKED** on the active DB until P0-1 |
| Database and Migrations | **BLOCKED** (6 pending; account and approval needed) |
| Python ML Integration | **PASS** (technical inference; validation hardened) |
| ML Scientific Accuracy | **NOT VERIFIED** |
| Weather Integration | **PASS** |
| Notifications and Reverb | **PASS** |
| Security | **FAIL** (shared default passwords, mobile password change, MariaDB exposure) |
| Frontend and Responsiveness | **PASS** (desktop, tablet, mobile; 0 overflow) |
| Production Infrastructure | **NOT VERIFIED** (no production host provisioned) |
| **Overall Deployment** | **NO-GO** |

**Path to CONDITIONAL GO:** P0-1 to P0-5 done and verified as described, P1-1 done, and P1-3 disclosed as "accuracy
not verified" in the app and to stakeholders. The application code is ready for that rollout; the blockers are
credentials, data, infrastructure and approvals.

---

## K. Recovery update (2026-11-18, later the same day)

| Item | Status | What changed / what is left |
|---|---|---|
| P0-3 Shared passwords | **FIXED in code** · rollout READY FOR APPROVED EXECUTION · mobile REQUIRES EXTERNAL RELEASE | Sign-in flags a published default **or a password hash shared with another account** (the dry run finds 867 such accounts: 865 Farmer, 1 MAO). Skipping removed (also for old sessions). Every password input refuses the defaults. A password change ends the account's other sessions. Reverb channels refuse flagged accounts. Mobile: login answers 409 `password_change_required` with no profile; new `POST /api/mobile/password` and `POST /api/mobile/forgot-password` (60-minute reset tokens, rate limited). Switch `ICLIMATE_PASSWORD_LOCKDOWN` (default true; **false in the live `.env` until approved**). App changes: `docs/deployment/mobile-password-change-contract.md`. |
| P0-1 Migrations | READY FOR APPROVED EXECUTION · REQUIRES DBA ACTION | Files hash-identical to the rehearsed ones; rehearsal repeated (compare: all OK). Needs the temporary account (`deploy/hostinger/phase-18-dba-handoff.md`). |
| P0-2 MAO Head | READY FOR APPROVED EXECUTION · REQUIRES USER ACTION | `iclimate:assign-mao-head`: dry run, audit log, reversible, no self-escalation (tests). Owner chooses the account. |
| P0-4 MariaDB | READY FOR APPROVED EXECUTION · REQUIRES DBA ACTION | `deploy/hostinger/mariadb-iclimate.cnf` tested on a throwaway MariaDB 10.4.28: listens on 127.0.0.1 only, LAN refused, Laravel connects. The live error log is 2.1 GB and unreadable without admin rights. |
| Reliability | FIXED in code | `iclimate:health` (DB, migrations, ML, Reverb, queue, scheduler, disk; exit 1 on FAIL) scheduled every 5 minutes, FAILs logged. systemd queue/scheduler units now depend on MariaDB (and the queue on the ML service), with a start limit. |
| P1-1 ML restart | READY FOR APPROVED EXECUTION | ML-only restart of the supervised child; verification steps in the final report. |
| P1-3 Yield estimates | **FIXED (safety gate)** · accuracy REQUIRES EXTERNAL EVIDENCE | `YieldModelGate` (default `withhold`): estimates outside the training range (every current input) are not shown; Farmers see why; stored rows kept as history; `ICLIMATE_YIELD_GATE=label` shows them marked unvalidated. **ACCURACY NOT VERIFIED.** |
| P0-5 Hosting | REQUIRES USER ACTION · BLOCKED until tested on the target | Needs a VPS (root, systemd, ≥ 4 GB RAM recommended). Shared or cloud web hosting cannot run Reverb, the queue worker or the ML service. |
| Skipped browser tests | Reviewed | 20 are viewport-specific (intended). 20 are the 10 landing tests from before "Simplify landing page" × 2 viewports: all 10 fail against the current page when un-skipped, so they are obsolete, not hidden failures (rewrite: P3). |

---

**Superseded for rollout:** the release, approval gates and operator steps are in `docs/deployment/ICLIMATE_RELEASE_HANDOFF.md` (RC 2026.11.18-rc1). Correction to section K: 867 = 723 on the published default (721 Farmer, 1 MAO, 1 former IT) + 144 Farmers on a copied hash only; 857 accounts have a copied hash (713 of them also the default).
