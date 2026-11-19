# iClimate Final Predeployment Verification

Verification date: 18 November 2026 (Asia/Manila). Domain: `iclimate-lian.tech`.

## A. Overall System Status

**The corrected working tree is a candidate for a new release, not approval to deploy RC1 unchanged. Actual Hostinger/Ubuntu installation and public launch are NOT VERIFIED.** Independent testing reproduced and corrected application, dependency, backup, configuration, and deployment-safety defects. No claim is made that every possible interaction is defect-free.

Two source states were tested separately:

- Exact annotated `v2026.11.18-rc1`, peeled commit `d43cd1aa1b3915b2b2e2928e21b207b3346f1170`. All 1,462 manifest files matched and all 59 declared removals were absent; fingerprint `bd8f2c9432d62b6191a4845c8085414fa8cfeef09eb42a60e9bd4b5a54b1266f`. The bundle was verified and checked out independently. PHPUnit used a separate copy of `vendor`, rather than a symlink that could load the working application. The archive used for suites excludes `.github` under the tag's own export rules; the complete bundle checkout was separately checked against every manifest entry.
- Current working tree, including the user's two existing tracked modifications and three untracked VPS files, plus the fixes below. Existing research directories were preserved and excluded from release verification. RC1's tag and commit were never changed. A new application release and deployment-tooling approval are required.

Tests used disposable SQLite databases and MariaDB 10.11.19 instances/databases, synthetic test accounts, separate local ports, and array/log mail transports. The active MariaDB was inspected and dumped read-only; the final inventory matched the initial one exactly; no active migration, account, password, agricultural record, or historical review was changed. No push, deployment, DNS change, purchase, model-artifact retraining, or healthy-service restart occurred.

## B. Complete Feature Verification Matrix

`PHP` means actual Laravel HTTP/command regression execution, usually with faked upstream APIs/mail. `Browser` means real Chromium against an isolated Laravel HTTP server. A passing PHP check does not prove delivery through a real email/weather provider. The browser page walk covers 18 authenticated routes at 1280×800, 768×1024 and 375×740, with runtime-error and horizontal-overflow assertions. Existing browser cases additionally exercise forms, modals, filters, navigation and CRUD interactions; this is broad automated coverage, not a claim of exhaustive manual inspection of every button.

| Farmer feature | Result and evidence |
|---|---|
| Registration and Pending status | PASS: `FarmerAccountRequestsTest`, registration/auth tests; browser registers through mobile API and reviews the resulting application. |
| Approval/rejection and decision notification | PASS: `FarmerAccountRequestsTest`, `FarmerApprovalNotificationTest`; browser ordinary-staff approval, rejection, details/history and resubmission. |
| Login/logout | PASS: auth, `LandingPageTest`, browser H4/H8/H10; legacy approved users remain supported. |
| Password recovery | PASS: `Auth/PasswordResetTest`, `DefaultPasswordLockdownTest`: email token, one-time MAO code, expiry, rate limiting, ownership and session invalidation. Real SMTP delivery NOT VERIFIED. |
| Dashboard | PASS: Farmer dashboard/home/KPI/language/weather/realtime tests; desktop/tablet/mobile page walk and ticking-clock browser test. |
| Farm registration/management | PASS: `FarmAssociationTest`, farm/boundary/profile tests; browser add/edit associations, validation and mobile CRUD. |
| Planting schedules | PASS: planting/calendar tests and rice-variety picker browser tests; create form included in responsive page walk. |
| Weather information | PASS: weather-contract/cache/failure tests and browser live-chip/panel/error handling. Current provider credentials and VPS egress NOT VERIFIED. |
| Agricultural advisories | PASS: advisories/localization/content/source/reliability tests and authenticated page walk. External advisory ingestion remains provider-dependent. |
| Rice-yield safety gate | PASS: `YieldModelGateTest`, `HeatMapYieldLayerTest`, prediction tests. Required deployment setting remains `withhold`; scientific accuracy NOT VERIFIED. |
| Notifications | PASS: inbox ownership/feed/mark-read tests, decision notification deduplication, actual private-channel event/reconnect probe. |
| Profile management | PASS: `ProfileTest`, `FarmerProfileModifyTest`, password/account hardening tests; responsive profile page. |
| Chairman assignment | PASS: `TwoRoleAccessTest`, account-request invitation tests: Farmer approval plus authorized assignment required; no third role. |
| Association report submission | PASS: assigned-chairman ownership tests and new `AssociationReportingWorkflowTest`, including production/area validation and calculated observed yield. |
| Revisions/submission history | PASS for submit → needs revision → resubmit → accept, preserved reviewer notes and review history, and accepted-report editing denial. The current system keeps the report row plus review history; immutable snapshots of every previous report body are not implemented and must not be represented as available. |
| Invalid/unauthorized/error paths | PASS: pending/rejected session/API/channel denial, cross-account access, conflicting account reviews, invalid inputs, service failure and actual CSRF HTTP checks. |

| MAO feature | Result and evidence |
|---|---|
| Dashboard | PASS: dashboard/operations/home tests, role page walk and live-clock browser test. |
| Account Requests | PASS: search/status/details/history; all authenticated authorized MAO Staff, including non-head reviewer browser account. |
| Approve/reject Farmer accounts | PASS: required reason, reviewer/time, optimistic revision/concurrency checks, notification deduplication and reconsideration without duplicate account. |
| Farmer/farm management | PASS: role/ownership/scope/farm/profile tests and existing browser CRUD tests. |
| Farmers Association management | PASS: farm-association linking, undeclared association review, membership preservation; responsive association list. |
| Chairman assignments | PASS: two-role and invitation/assignment tests; pending Farmer cannot acquire protected reporting access. |
| Reporting periods | PASS after duplicate-key fix: period creation/list/selection and HTTP 422 on duplicate keys. |
| Deadlines | PASS: overdue/upcoming period selection and reminder-date logic; overdue submissions are not claimed to be automatically prohibited. |
| Overdue reminders | PASS: new approved-chairman reminder regression and actual scheduler `schedule:test` invocation; repeat invocation does not duplicate delivery. |
| Report review | PASS: authorized review, revision-note requirement, computed observed yield and accepted state. |
| Acceptance/revisions | PASS: new reporting lifecycle regression preserves both review entries and restricts unrelated Farmer access. |
| Advisories | PASS: adviser generation, CRUD/publication/localization/fallback HTTP tests and page walk. |
| Weather/agricultural monitoring | PASS: prepared forecast, risk/realtime/cache/failure tests; real provider reachability on VPS NOT VERIFIED. |
| Heat Map | PASS: page/API/data-flow/layer/terminology/gate tests, layer browser cases and three-width page walk. |
| Analytics/reports | PASS: analytics privacy/queue tests; Phase7 and report tests actually generate/download PDF and Excel responses. |
| Announcements/notifications | PASS: publish/community-feed/inbox/feed filtering/CRUD/authorization tests and private-event probe. |
| Reverb updates | PASS locally on both RC1 and working source: login, handshake, private auth, subscription, event, reconnect, expected 403 for unauthorized channel. Production template corrected separately. |
| MAO Head/general administration | PASS: ordinary staff can review Farmer accounts but `/users` is forbidden; MAO Head-only management tested. Retired IT login, sessions, API and channels denied. |

## C. Full Test Results

Fresh execution evidence is under the protected local directory `/tmp/iclimate-final-verify`; sensitive temporary SQL dumps and cloned data were removed after verification; only the protected local test evidence remains. No database dump is a release artifact. The commands below were actually executed in this verification. Counts are separate executions and must not be added together as unique coverage.

| Suite / source state | Passed | Failed | Skipped | Warnings / notes |
|---|---:|---:|---:|---|
| Full Laravel, exact RC1 | 1,669 | 0 | 5 | 1,674 total; 12,451 assertions; 4 PHPUnit metadata deprecations; 10m29.775s. |
| Full Laravel, initial working tree | 1,669 | 0 | 5 | 1,674 total; 12,450 assertions; 4 deprecations; 10m40.463s. |
| Full Laravel, final patched working tree | 1,672 | 0 | 5 | 1,677 total; 12,472 assertions; 4 PHPUnit metadata deprecations; 9m07.605s. |
| MariaDB 10.11 exact RC1 | 55 | 0 | 0 | 413 assertions; 4 PHPUnit deprecations; 1m00.616s. |
| PHP 8.4.27 targeted working source | 46 | 0 | 0 | 416 assertions; 4 PHPUnit deprecations; 2.230s. Platform requirements also pass on 8.4.27. |
| MariaDB 10.11 targeted initial working source | 55 | 0 | 0 | 421 assertions; 4 PHPUnit deprecations. |
| MariaDB 10.11 targeted patched | 58 | 0 | 0 | 444 assertions; 4 PHPUnit deprecations; 1m06.118s. |
| New association lifecycle/reminder tests + period list (SQLite) | 4 | 0 | 0 | 43 assertions. New tests separately on MariaDB: 3 passed, 30 assertions. |
| Python exact RC1 | 53 | 0 | 0 | 2 Starlette/AnyIO deprecation warnings; 1.96s. |
| Python working tree | 53 | 0 | 0 | Same 2 warnings; 1.85s. |
| JavaScript exact RC1 | 19 | 0 | 0 | 0 warnings in test output. |
| JavaScript Node22.23.5, corrected command | 19 | 0 | 0 | Actual isolated Node22 execution. |
| JavaScript updated dependencies / Node26 | 19 | 0 | 0 | 0 warnings in test output. |
| Browser exact RC1, default port | 166 | 0 | 40 | 1.9m; skip audit below. |
| Browser working tree, before dependency updates/new security case | 170 | 0 | 16 | 1.9m. |
| Browser final working tree, desktop/mobile | 172 | 0 | 16 | 188 total; 1.8m. |
| Browser final desktop/tablet/mobile verification config | 9 | 0 | 0 | 17.5s; page/runtime/overflow + real CSRF and sensitive-file denial. |
| Deployment safety regression (`unittest`) | 15 | 0 | 0 | Real disposable bundle/checkouts, synthetic backup and mocked provisioning guards; not Ubuntu provisioning. |
| Live ML HTTP checks | 9 | 0 | 0 | Health, metadata, metrics, inference, invalid input/type/body, forbidden switch/path. |
| Laravel → actual FastAPI / unavailable service | 2 | 0 | 0 | Real `PythonService` HTTP inference and controlled failure. |
| Reverb probe, each of RC1 and working source | 7 | 0 | 0 | Login/handshake/private auth/subscription/unauthorized denial/event/reconnect; one probe per source. |
| Local supervisor recovery | 2 | 0 | 0 | TERM precedence and crash-loop backoff/recovery, using disposable process. |
| Actual database queue worker | 2 jobs exercised | 0 unexpected | 0 | Success once; intentional failure retried then stored in `failed_jobs`; 0 queued jobs remain, 1 intentional failed job after two failed attempts. |
| Actual scheduler command | 1 | 0 | 0 | `schedule:test --name=associations:send-reminders`; scheduler list also inspected. |
| Frontend production builds | 4 successful | 0 | 0 | RC1, initial working tree, final patched dependencies on Node26 and Node22.23.5. |
| Shell syntax | 12 | 0 | 0 | `bash -n` for Hostinger/deploy/probe/browser scripts. |
| Source/Blade/class checks | PASS | 0 | — | `scripts/check-code.sh`; no conflicts, PHP syntax/load errors or broken compiled Blade. |
| Composer platform + manifest validation | PASS | 0 | — | Local PHP 8.5.12 requirements pass; `composer validate` valid. |
| Python installed dependencies | PASS | 0 | — | `pip check`; all installed pinned versions match requirements, Python 3.12.15 / sklearn 1.6.1. |
| Six-migration MariaDB rehearsal | 6 up + 6 down + 6 reapply | 0 | 0 | Historical-data comparison passed; see D. |
| Backup/restore MariaDB rehearsal | PASS | 0 | 0 | Actual SQL backup, checksum, isolated restore, `mysqlcheck`, 77/77 table count/checksum comparisons. |

Warnings are not hidden: the four PHPUnit deprecations are doc-comment data-provider metadata in `LandingPageTest`, unrelated to production execution. Five default SQLite skips are engine-specific `TimestampRefreshMigrationTest` cases; all five were exercised successfully in the MariaDB suite. Python emits two dependency deprecations about AnyIO's portal alias. Playwright's runner also prints environment warnings about `NO_COLOR` together with `FORCE_COLOR`; they are not browser JavaScript errors. Runtime page-error assertions passed.

Earlier failures retained as evidence:

- RC1 custom-port execution: 160 passed, 6 failed, 40 skipped. N1/H3/C1 asserted port 8123. The user's existing three URL changes fixed these; exact RC1 passed unchanged at 8123.
- Two working-browser trace-file ENOENT failures came from this verification's simultaneous runs sharing `test-results`. Fixed with per-port output directories and rerun.
- Initial new security assertions expected only 404; `/storage/logs/laravel.log` correctly returns 403. Corrected to accept either denial, not public access. The final three-width check passed.
- Duplicate-period reproducer: 2 tests, 1 pass / 1 failure (HTTP 500). After the fix, the lifecycle and validation regressions pass.
- Original backup script's checksum failed because it referenced the renamed `.partial` file. Corrected script passed real backup/restore.
- First isolated restore attempt correctly refused an account without CREATE permission on the second disposable database. Granting access only to that disposable target allowed the same restore to complete. No active grant changed.
- Initial Node22 JavaScript invocation failed before collecting the test file because directory-only test arguments are not resolved like Node26. Changed the package script to an explicit test-file glob; all 19 tests pass on both runtimes.
- One intermediate full working-tree run was intentionally interrupted before changing installed Composer dependencies. It is not counted as a completed suite.

Browser skips: RC1's 40 consist of 24 obsolete static skips and 16 intentional viewport exclusions. Removed obsolete H2/B1/T3/S1/S2/S4/S5/S8/S9/M1/M2/M3 specifications for deleted links, footer/CTA variants, old card styling and removed reveal animations. Current active N2–N6, F1, R1/R2, footer T1/T2, S10–S14, G2/G4 and W1–W4 cover the current design. The remaining 16 select desktop-only navigation, mobile-only drawers/cards, fixed-width checks, or avoid repeating tests that set all their own widths. No failing application test was skipped.

Representative reproduction commands (all DB mutation commands require disposable targets):

```sh
APP_KEY=<disposable-key> vendor/bin/phpunit
.venv-ml/bin/python -m pytest python_scripts/tests -q
npm run test:js
npm run build
PLAYWRIGHT_PORT=18126 npm run test:browser
PLAYWRIGHT_PORT=18127 npx playwright test --config=playwright.verification.config.mjs
python3 -m unittest discover -s tests/deployment -v
bash scripts/isolated-reverb-authenticated-test.sh
bash scripts/test-local-service-supervisor.sh
bash scripts/check-code.sh
```

The deployment regression suite uses the existing local RC1 bundle/manifest in `storage/app/backups/preflight-2026-11-18`; supply those approved artifacts before reproducing it on another machine. It never embeds them or the real database in the release.

## D. Database Readiness

Active database read-only inventory: MariaDB **10.4.28**, 69 tables before pending migrations, **877 users** (872 legacy Active Farmers, 3 Active MAO Personnel, 2 retired IT accounts retained as history), **868 Farmer profiles**, **1,081 farms**, **124 rice-production records**, **6,498 historical yield predictions**, **100 applied migrations**, **87 foreign keys**, **339 indexes**, **zero foreign-key orphans**.

Exactly these six migrations remain pending on the active database:

1. `2026_11_16_000001_add_association_directory_fields`
2. `2026_11_16_000002_create_association_reporting_tables`
3. `2026_11_16_000003_allow_unknown_association_farm_type`
4. `2026_11_16_000004_create_association_chairman_invitations_table`
5. `2026_11_17_000001_allow_canonical_application_roles`
6. `2026_11_17_000002_add_farmer_account_review_history`

A consistent read-only logical dump (~42 MB) was imported into an independently initialized **MariaDB 10.11.19** data directory and listener on port 19336. The six files were applied, rolled back with `--step=6`, and reapplied. Snapshots compared original column fingerprints, untouched agricultural checksums, all pre-existing row counts, role/status counts, and foreign keys. All comparisons passed: eight new empty tables, exactly six recorded migrations, no lost FK and 24 added FKs. The canonical-role migration intentionally does not convert historical role values on rollback; this is recorded rather than claiming byte-for-byte schema reversal.

The corrected backup script then dumped this migrated clone. `restore-isolated-mariadb.sh` verified its checksum and restored into a second new disposable database; `mysqlcheck` passed. **All 77 tables matched both row counts and MariaDB table checksums.** This establishes logical restoration and migration evidence on 10.11.19 on macOS, not physical 10.4→10.11 datadir portability or actual Ubuntu operation. Use logical transfer, never copy the active 10.4 datadir into 10.11.

No new migration is required by the fixes in this verification. Production still requires approved migration of those six files after a verified production backup. Configure `DB_TIMEZONE=+08:00` and separate runtime/migration/backup accounts. Existing retired IT rows are preserved but cannot authenticate through the two-role application.

## E. Security Readiness

Verified locally: Farmer/MAO isolation, MAO Head administrative restriction, ordinary-staff account review, pending/rejected web/session/API/channel denial, retired IT denial, known/shared password blocking, email-token and MAO-code recovery, expiry and throttling, session invalidation, scoped private channels, server validation, ownership checks, CSRF 419, and 403/404 for `.env`, `.git/config` and logs. Intentional denial responses are not failures. The suite covers upload validation and escaped view content; this is not a penetration-test certification or proof against every SQL/XSS payload.

Production template retains `APP_DEBUG=false`, `ICLIMATE_PASSWORD_LOCKDOWN=true`, `ICLIMATE_YIELD_GATE=withhold`, secure cookies and SameSite=lax. The deploy script validates key length, file permissions, release identity, safe settings and Reverb build key, and refuses unexpected checkout/env files. The environment file is intended as root:www-data 640 within directory 750, readable by PHP-FPM/units but not the world. Provisioning now gives the web group no write access to the application root; only storage/cache get write ACLs.

Fresh dependency results:

- RC1: Composer found one low Laravel advisory; npm found **17** affected package entries (2 critical, 14 high, 1 moderate).
- Working tree: Laravel **12.69.3 → 12.73.0**, compatible npm updates, and patched glob/minimatch build dependencies. Composer now reports **0 advisories**; npm reports **12 high entries, 0 critical, 0 moderate**, all tracing to the same unresolved `picomatch` advisory. These are dependency-tree entries, not 12 separate vulnerabilities.
- [The reviewed upstream picomatch advisory](https://github.com/advisories/GHSA-fc5q-39xc-vjpf) lists versions through 4.0.7 and **no patched version**. Registry lookup also found no 4.0.8. Do not force npm's suggested downgrade to obsolete Laravel Vite tooling. Keep builds trusted/isolated, never accept user-provided globs or expose Vite publicly, and deploy built static assets. This mitigates exposure; it does not clear the audit finding. Require explicit risk acceptance or a tested upstream patch before treating dependency security as closed.

Real SMTP transport, recovery operations by trained staff, production account ownership verification, actual TLS/cookie headers, Ubuntu hardening and external firewall enforcement remain unverified. Active-instance lockdown/passwords were not changed.

## F. ML Readiness

Inference reliability PASS locally, scientific accuracy **ACCURACY NOT VERIFIED**. Keep `ICLIMATE_YIELD_GATE=withhold`.

Live Uvicorn loaded rice and weather models, `/health` returned `ok`, `/model-info` matched SHA-256 `f3a54e12ecf85e66340475e2c49ce5c15d9c58efd82213435015a275734b777c`, and real Laravel `PythonService` inference succeeded. `/predict` validates the twelve-feature rice contract and rejects invalid types, missing payloads and sentinel/agricultural bounds. Tests exercise feature completeness and invalid inputs. `/metrics` returned `ready=false`, sample_count 0, because approved evaluation data is missing; no accuracy figure was invented. Unauthorized model switching returned 403 and arbitrary metric-file paths returned 422.

Laravel service failure, cache/storage and yield-gate regressions pass. Farmer/Heat Map tests enforce withheld or appropriately qualified model output. Existing 6,498 prediction records survived the migration and logical restore comparisons. Model artifacts were checksummed before and after the tests; no deployed artifact changed. Inference output is not evidence of scientific validity. Existing test-only fixtures do not become operational agricultural records.

## G. VPS Compatibility

| Requirement | Evidence / limitation |
|---|---|
| Ubuntu 24.04 provisioning | NOT VERIFIED: no Ubuntu VM/container or Hostinger VPS available. Syntax and mocked preflight rejection tests only. |
| PHP / Laravel | Local PHP 8.5.12 full suites and PHP8.4.27 targeted 46-test suite plus both Composer platform checks pass. Lockfile requires PHP ≥8.4.1. Actual Ubuntu PHP8.4/FPM install NOT VERIFIED. |
| Node / Vite | Actual builds and 19 JavaScript tests on Node26.10.0 and isolated Node22.23.5 pass. Actual Ubuntu Node installation remains NOT VERIFIED. |
| Python / sklearn | Actual Python 3.12.15, pinned packages and sklearn 1.6.1 checked; inference HTTP and test suites pass. Ubuntu wheel/compiler installation NOT VERIFIED. |
| MariaDB 10.11 | Actual 10.11.19 isolated migration/rollback/reapply and logical backup/restore PASS on macOS. Ubuntu service/account setup still NOT VERIFIED. |
| Nginx / HTTPS | Template reviewed and corrected: public ACME path, private HTTPS include, PHP8.4 socket, WebSocket proxy. `nginx -t`, certificate issuance/renewal and live hostname validation NOT VERIFIED: Nginx/production certificates unavailable. |
| Reverb | Real local private auth/event/reconnect PASS. Backend publishes to 127.0.0.1:8080; browser uses public HTTPS/443. VPS proxy/TLS/origin behavior NOT VERIFIED. |
| Queue / scheduler | Actual local database worker, intentional retry/failure, scheduler command and reminder deduplication PASS. systemd timer/recovery/permissions on Ubuntu NOT VERIFIED. |
| systemd / firewall | Units/config reviewed, with restart/backoff and bounded worker settings. No systemd runtime available; `systemd-analyze verify`, UFW rules and off-host denial NOT VERIFIED. |
| Secrets / filesystem | Installer guard regressions PASS; production directory ownership, sudo, ACLs and service ability to read env/write backups must be tested on VPS. |
| Backups | Real 10.11 logical dump/restore PASS with GNU tools. Encrypted off-host storage, retention, restore drill and backup monitoring on VPS NOT VERIFIED. |
| Provisioning failure safety | Invalid operator/IP and active-service guards tested with mocks; root temp installer now unique. Package install/repository outages/reboot behavior NOT VERIFIED. |

The three new VPS files were absent from the original RC1 manifest and commit. Their current safety tests do not make them part of RC1. Approve and commit them with the corrected application/dependency/configuration files as a new candidate; build a fresh bundle and fingerprint. The installer's default tag/commit/fingerprint still identify RC1 for reproducible verification: **do not use those defaults to install a corrected release**. Supply the newly approved TAG/EXPECTED_COMMIT/EXPECTED_FINGERPRINT and regenerate artifacts; never silently retag RC1.

## H. Issues Found and Fixed

| Priority | Root cause / effect | Small safe fix and regression evidence |
|---|---|---|
| P1 | Duplicate reporting-period key reached SQL UNIQUE violation and HTTP 500. | `MaoAssociationReportController`: unique validation plus concurrent-insert exception conversion to validation error. New lifecycle/duplicate tests pass on SQLite and MariaDB. |
| P1 | Backup sidecar named `.partial` after file rename; restore always failed checksum validation. | `iclimate-backup.sh` writes final basename; restore verifies from dump directory. Synthetic transferred backup test + actual 77-table restore pass. |
| P1 | Production template defaulted broadcasting to null, omitted Vite app key, and published to public endpoint instead of private Reverb listener. | Explicit reverb connection, public build key, loopback backend and distinct browser transport. Installer rejects missing/mismatched key; Laravel config-resolution regression + real Reverb probe pass. |
| P1 | Fresh-server bundle verification required an enclosing Git repository and failed from `/tmp`/operator home. | Verify the complete bundle in a unique empty temporary bare repository; installer regressions now execute outside any repository. |
| P1 | Forced deployment checkout overwrote an unexpected untracked README in a disposable reproducer. | No force checkout; dirty/untracked and ignored collision protection, strict tag fetch/bundle identity. Checkout-preservation tests pass. |
| P1 | Predictable root Composer download path, unvalidated Nginx IP input, and provisioning could overwrite/restart an existing installation. | Unique private temp directory; IP/operator/key checks; fresh-server config/path/active-service guards. Mock guard tests pass; Ubuntu execution unverified. |
| P1 | Runtime backup directory was root-only; application root was group-writable; template chmod600 contradicted FPM service access. | Backups www-data 700; app root deploy:www-data 2750; env root:www-data 640; install-check aligned. Target filesystem verification remains external. |
| P1/P2 | RC1 dependency audits found Laravel and npm advisories. | Laravel12.73, compatible JS patches and patched glob/minimatch overrides. Full regressions/build repeated; unresolved picomatch finding remains explicitly open. |
| P2 | Existing `.env` could be replaced; malformed APP_KEY accepted; bundle fetch failure ignored. | Preserve existing env, validate 32-byte base64 key and safe mode, fail tag fetch and compare bundle tag. Regression suite pass. |
| P2 | `#!/bin/sh` plus Bash-only options breaks Ubuntu dash execution. | Browser/probe scripts specify Bash; Playwright invokes Bash explicitly. Syntax and actual probes/browser pass locally. |
| P2 | JavaScript test script used a directory-only argument unsupported by the target Node22 runtime. | Explicit test-file glob; actual Node22 and Node26 test runs pass, with production builds on both. |
| P2 | Browser assertions hardcoded port8123 and simultaneous runs collided on trace output. | Preserved user's URL fixes; per-port result directories. Final custom-port suite pass. |
| P2 | Runbook uploaded directly into an inaccessible backup directory; checksums retained source paths; disabled-password operator had no sudo password. | Protected incoming transfer, basename checksum, explicit backup ownership and local sudo-password verification before root logout. No actual server account changed. |
| P3 | 24 permanently skipped checks described removed UI. | Removed obsolete specifications after comparing current coverage; kept 16 valid viewport exclusions. No failing feature hidden. |

Changed files for this verification (alongside preserved pre-existing edits):

- Application: `app/Http/Controllers/MaoAssociationReportController.php`.
- Dependencies: `composer.lock`, `package.json`, `package-lock.json`.
- Deployment: `deploy/hostinger/iclimate-backup.sh`, `restore-isolated-mariadb.sh`, `install-check.sh`, `nginx-iclimate.conf`, `iclimate.env.example`; existing untracked `deploy-release.sh`, `provision-ubuntu-24.04.sh`, `VPS-RUNBOOK-RC1.md` corrected in place.
- Regression tooling: `playwright.config.mjs`, new `playwright.verification.config.mjs`, `tests/Browser/serve.sh`, `landing.spec.mjs`, new `verification.spec.mjs`, `scripts/isolated-reverb-authenticated-test.sh`, new `tests/Feature/AssociationReminderApprovalTest.php`, `AssociationReportingWorkflowTest.php`, `tests/deployment/test_release_tooling.py`.
- This report and AST-only graphify outputs. Graph update warns that SQL parser support is missing and some data/config files yield no symbols; it is navigation evidence, not runtime verification.

No database migration was added. No scientific prediction formula changed. Uncommitted research data/model experiment directories were preserved.

## I. Remaining Deployment Blockers

Only external infrastructure, credentials, approval or scientific-evidence requirements are listed here:

1. **Release approval:** review the uncommitted fixes, approve a new candidate (proposed RC2) plus deployment tooling, create its commit/tag/bundle/manifest and repeat identity checks. Current working-tree fixes are not in RC1. No commit/tag/push was performed.
2. **Upstream dependency / security acceptance:** picomatch has no published patched version. Obtain a tested patch or explicit acceptance of trusted isolated-build/static-production mitigation. Do not expose a Vite development server publicly.
3. **Actual Hostinger Ubuntu environment:** provision an authorized VPS and verify PHP8.4/FPM, Node22, Python packages, MariaDB grants/ACLs, Nginx syntax, systemd units, queue, scheduler, restart recovery, firewall and off-host private-access denial. Local macOS evidence cannot close this.
4. **Owner/DBA authorization:** encrypted personal-data transfer, production backup and restore comparison, six production migrations, approved MAO Head designation and ownership/recovery rollout. Active Mac data/passwords remain unchanged.
5. **Credentials and operational delivery:** configure actual SMTP and API/Reverb secrets, prove email/reset-code delivery, external weather/advisory access, queue latency/failed-job monitoring and backup retention/off-host restoration.
6. **DNS/TLS/private/public approval:** owner-authorized DNS records, real certificates/renewals, HTTPS session/cookie behavior and WebSocket proxy validation; test unapproved IPs are denied before private acceptance. Obtain owner launch approval and staff recovery training/mobile rollout decision.
7. **Scientific evidence:** approved ground-truth evaluation/provenance and independent validation before enabling scientifically presented yield predictions. Keep `withhold` and `ACCURACY NOT VERIFIED` meanwhile; validated accuracy is not a prerequisite to operating clearly withheld-yield account/weather/reporting features.

## J. Final GO / NO-GO Decision

1. **SOFTWARE READINESS — CONDITIONAL GO for the corrected working tree as a proposed new candidate; NO-GO for RC1 unchanged.** The verified fixes and regressions support a controlled release review. Dependency risk acceptance/upstream fix and a new approved immutable release are required. This is not a claim of zero undiscovered defects or complete penetration-test coverage.
2. **PRIVATE VPS DEPLOYMENT READINESS — CONDITIONAL GO for an approved isolated installation rehearsal of the new candidate.** Actual installation is NOT VERIFIED. First obtain the VPS, approved release artifacts/credentials and data-transfer authorization; complete the Ubuntu, service, restore and private-access acceptance checks before admitting staff. Do not install RC1 unchanged using the script defaults.
3. **PUBLIC PRODUCTION LAUNCH — NO-GO.** No production VPS, DNS/TLS, real mail/API transport, operational recovery rollout or owner acceptance has been verified. Public launch requires the external checks in I; yield predictions remain withheld until scientific requirements are separately satisfied.

**Answer to the requested question:** the system has substantial passing independent software and MariaDB10.11 evidence, with confirmed safe fixes implemented. It is not yet proven fully functional on a Hostinger KVM VPS, and the exact RC1 release is not free of known issues. Proceed only with a newly approved corrected candidate and controlled private VPS acceptance; public launch is not approved by this verification.
