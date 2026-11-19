# iClimate RC2 Final Readiness

Date: 18 November 2026, Asia/Manila. Project: `/Users/johnlukereynon/iclimate_system`. Proposed tag: `v2026.11.18-rc2`.

## Status and decisions

**RC2 is prepared as an independently tested source candidate for approval. No commit, tag, push or deployment has been performed.** RC1 remains unchanged at `d43cd1aa1b3915b2b2e2928e21b207b3346f1170`. Existing uncommitted work and research directories were preserved. Active accounts, passwords, migrations and agricultural records were not changed; healthy services were not restarted.

| Decision | Result | Scope |
|---|---|---|
| Software readiness | GO for RC2 approval | The exercised candidate has zero failing required tests and no known unmitigated deployment-blocking code defect. The documented build-parser mitigation remains part of the reviewed source. |
| Private VPS deployment readiness | CONDITIONAL GO | Ready for an approved, controlled installation rehearsal of this exact candidate. Actual Ubuntu/Hostinger acceptance remains NOT VERIFIED. |
| Public production launch | NO-GO | No target VPS, live TLS/DNS, operational SMTP/recovery rollout or production service/security acceptance has been completed. |

“No known blocker found” applies to the reproduced defects, inspected code and executed checks below. It is not a claim that all conceivable defects or attacks have been ruled out. The raw npm advisory remains visible alongside its tested mitigation.

## Issues and fixes

The fixes from the preceding [predeployment verification](ICLIMATE_FINAL_PREDEPLOYMENT_VERIFICATION.md) are included in RC2. This table covers those carried changes and the additional autonomous fixing loop.

| Issue / priority | Root cause and correction | Verification |
|---|---|---|
| Glob-parser denial of service, P1 | `+(*a)` caused all three installed picomatch parsers to exceed a 600 ms bounded probe. A source-hash-checked install mitigation rejects wildcard-containing repeated extglobs before regex creation, including when the upstream recursion guard is disabled. Build/dev commands fail if the mitigation is absent or modified. Upstream package versions and audit warnings are preserved. | All three copies reject payload variants quickly; ordinary build patterns pass. Clean `npm ci`, 26 JS tests and real builds pass. Details in `docs/security/PICOMATCH_BUILD_MITIGATION.md`. |
| ML fallback can overrun chat deadline, P1 | Local inference always received at least 15 seconds even within a shorter chat budget. Chat fallback now uses the remaining budget minus rendering time, capped at three seconds, or does not start when exhausted. Background inference retains its original budget. No prediction formula changed. | A deterministic five-second fake process failed before the fix (5.156 s against a <3 s assertion); the deadline and read-only chat suite then passed 28 tests. Final full regression also includes it. |
| Draft accepted / duplicate or conflicting report review, P1 | Report review lacked state checks/transactional serialization. Locked review now permits awaiting-review states, makes identical retries idempotent, and refuses stale conflicting decisions with 409. The UI hides actions when no review is pending. | New integrity tests, MariaDB integration and real chairman → ordinary MAO review flow. |
| Previous submission contents overwritten, P1 | Resubmission changed one report row while only reviewer notes remained. Added an append-only formal submission table and versioned content snapshots. Prior review rows remain untouched; no old submission contents are invented. Populated-history rollback is refused. | Two versions retain original/corrected totals and remarks; immutable model checks, deletion protection, private version downloads and rollback-refusal regression pass. |
| Stale calculated observed yield, P2 | Clearing production/area left the old ratio. Recalculate from the current observed fields or clear the ratio. This is report arithmetic, not a change to ML formulas. | Failing-before/passing-after regression. |
| Revision fields lost / validation invisible, P2 | Date inputs received timestamp strings; irrigation was not selected; forms lacked visible validation summaries. Render date-only values, preserve irrigation and show validation. | HTTP regression and actual browser correction flow at all three widths. |
| Supporting documents not reviewable, P2 | Uploads were stored without an authorized download interface. Added private current/version document download with role, assignment/coverage and path checks. Files are explicitly stored on the private local disk. | Real browser download; unrelated Farmer denied; malicious upload/path rejected. |
| API denial returned redirected HTML/200, P2 | Validation without an Accept header used the web redirect handler. `/api/*` exceptions now render JSON consistently; this was an error-contract defect, not successful authentication. | Missing/invalid credentials and Pending accounts return 422 JSON without user data; browser API denial checks pass. |
| Duplicate reporting period HTTP500, P1 | Database uniqueness reached the error page. Validate uniqueness and convert a concurrent unique-constraint exception into a validation error. | SQLite/MariaDB lifecycle and duplicate-period tests. |
| Broken backup checksum / portability, P1 | Sidecar referenced the renamed `.partial` path. Hash the final basename and verify relative to the dump directory; use private creation permissions and unique names. | Actual MariaDB backup/restore and 78-table comparison; synthetic transfer regression. |
| Production Reverb misconfiguration, P1 | Broadcasting defaulted to null, the browser key was missing and backend publication used the public HTTPS endpoint. Explicitly configure private loopback publication and separate public browser transport; installer validates matching keys. | Production-template Laravel config test and actual compiled Echo/badge/reconnection probe. |
| Unsafe deployment checkout/env handling, P1 | Force checkout could overwrite untracked files; tag-fetch errors were ignored; `.env` could be replaced. Preserve unexpected files, disallow ignored collisions, verify bundle/tag strictly, validate key/mode and retain existing env files. | Disposable checkout/env safety tests; no real server mutation. |
| Fresh VPS bundle verification failed, P1 | `git bundle verify` required an enclosing repository. Verify a complete bundle in a unique empty bare repository. Installer now requires explicit approved tag/commit/fingerprint rather than defaulting to RC1. | Installer tests run from outside a repository; fingerprint mismatch/tag absence refuse safely. |
| Provisioning / permissions defects, P1 | Predictable root download path, unsafe IP input, existing-installation overwrite/restart, incorrect backup/env/app permissions. Use private temp storage, validate inputs/keys, refuse active installations, restrict app-root writes, permit service-only backup/env access and document sudo setup. | Syntax and mocked guard tests; actual Ubuntu filesystem/systemd enforcement still external. |
| Locked dependency advisories, P1/P2 | RC1 Laravel and npm versions had advisories. Retained Laravel12.73.0 and compatible JS patches, including glob/minimatch overrides. No ML artifact changed. | Fresh locked installs, platform checks, audits, JS/build/browser/PHP regressions. |
| Test portability / warnings, P2/P3 | Shell scripts used Bash syntax under `sh`; JS directory argument failed on Node22; URLs assumed port8123; browser artifacts collided; PHPUnit used deprecated provider annotations. Use Bash, explicit test-file glob, dynamic URLs, per-port artifacts and PHPUnit attributes. | Both Node versions tested previously; final Node22 and PHP8.4 suites pass without PHPUnit metadata deprecations. |
| Obsolete and coupled browser fixtures, test-only | Twenty-four former static skips described deleted UI. Prior loop removed them with current coverage retained. New chairman association fixtures initially changed Prenza dropdown expectations; moved those fixtures to Balibago. No application failure was disabled. | Final full browser suite passes with 16 documented viewport exclusions. |

## Feature verification

All testing used synthetic isolated accounts and disposable databases. The only application roles remain **Farmer** and **MAO Staff**; chairman is an approved Farmer with an authorized assignment. Retired IT accounts remain historical database rows and cannot authenticate or use protected sessions/APIs/channels.

| Feature group | Evidence |
|---|---|
| Farmer registration / Pending / approval / rejection / resubmission | Full account-request tests, real browser API registration and ordinary-staff approve/reject/details/history forms. Required reason, reviewer/time, stale review protection, notification deduplication and account reuse verified. |
| Authentication, logout and account security | Auth/landing/two-role/default-password suites; actual pending/rejected browser and API denial; real CSRF419 and sensitive-file403/404 checks. |
| Recovery / profile | Email token and MAO-code expiry, ownership, throttling and session revocation tests. Browser ordinary staff issues one-time code, code disappears after refresh, Farmer resets password and logs in. Real SMTP delivery remains external. Profile/password/account-deletion tests pass. |
| Farmer dashboard / farms / planting | Full home, KPI, farm/boundary/association, planting/calendar/variety and language suites; responsive page walks, add/edit form interactions, navigation and clock tests. |
| Weather / advisories | Provider-contract, failure, cache, risk and localization tests; actual Open-Meteo Laravel fetch stored 16 provider records in isolated SQLite. Public provider availability is not a guarantee of target-VPS credentials/egress. |
| Prediction / Heat Map safety | Model-gate, dashboard/layer/API/privacy/storage/cache tests, inference HTTP checks and chat deadline handling. Farm-specific estimates remain withheld; no scientific accuracy claim. |
| Chairman workflow | Browser first denies unassigned Farmer, ordinary MAO assigns the approved account, creates a period, receives submission, requests correction, reviews preserved dates/irrigation and two content versions, downloads a private document, then accepts. Accepted editing and unrelated-user access denied. |
| Periods / deadlines / reminders | Creation/duplicate validation and overdue-period selection; actual scheduler command on an approved-chairman fixture, invoked twice with one delivery. Deadline labels/reminder behavior verified; overdue submissions are not claimed to be prohibited. |
| MAO dashboard / Farmer management / associations / planner / alerts / monitoring | Full HTTP and CRUD/scope/operations tests; desktop/tablet/mobile protected-page walks and existing form/filter/modal tests. |
| Reports / analytics / downloads | Report/Phase7 tests generate PDF and Excel responses with role restrictions; analytics queue/privacy/state tests; browser private document download. |
| Announcements / notifications / Reverb | Publish/feed/inbox/mark-read/CRUD tests; real socket private auth and expected unauthorized403; **compiled Echo receives the event, updates the visible notification badge and reconnects**. |
| MAO Head separation | Ordinary staff performs Farmer review, recovery and authorized association work; `/users` returns403. MAO Head-only account management tested. No third role introduced. |

Browser checks cover 1280×800, 768×1024 and 375×740. The page walk asserts HTTP200, no unhandled page exceptions and no horizontal overflow on 18 protected routes. Existing suites exercise modals, filtering, mobile CRUD, contact/registration, downloads and navigation. This is broad automated coverage, not an assertion that every possible browser state was manually inspected.

## Test results

Final tests below refer to the source manifest identified in “Proposed release identity”. Different suite rows overlap and should not be added as unique test coverage. Intermediate failures were reproduced and corrected, not suppressed. A first health invocation was sandbox-restricted and ran with the disposable Reverb listener stopped; a permitted check with both disposable services running returned exit0 as recorded. An initial production rehearsal used `composer --no-scripts` after a dev install and retained stale dev-provider manifests; rerunning the actual deployment Composer command, with its normal cleanup/discovery scripts, resolved that harness issue.

| Final verification | Passed | Failed | Skipped / warnings |
|---|---:|---:|---|
| Full Laravel, PHP8.4.27 | 1,679 | 0 | 1,684 total; 12,520 assertions; 5 engine-specific skips; 0 PHPUnit warnings/deprecations; 10m54.445s. |
| MariaDB10.11 / PHP8.4 targeted integration | 65 | 0 | 493 assertions; 0 skips/deprecations; 1m00.649s. |
| Python3.12 fresh environment | 53 | 0 | 2 Starlette/AnyIO deprecation warnings; 3.17s in final rerun. |
| Node22.23.5 JS, including security regression | 26 | 0 | 0 skipped; payload probes bounded in child processes. |
| Full desktop/mobile Playwright | 178 | 0 | 194 total; 16 justified viewport skips; 2.1m. |
| Desktop/tablet/mobile critical verification | 18 | 0 | 0 skips; 41.6s. |
| Deployment safety + deterministic packaging | 22 | 0 | 0 skipped; includes archive traversal/tampering, research/secret exclusion and exact reproduction. |
| ML HTTP | 9 | 0 | Health, model-info, metrics, predict, invalid type/body/value and forbidden file/switch requests. |
| Laravel ↔ live ML / unavailable service | 2 | 0 | Actual HTTP inference and controlled error response. |
| Actual Reverb browser probe | 8 checks | 0 | Auth/event/reconnect/unauthorized denial plus compiled UI/badge update. |
| Database worker / scheduler | PASS | 0 unexpected | One success, one intentional failure attempted twice (one retry) and stored once; queue empty. Reminder command invoked twice, one notification/delivery. |
| Isolated health command | 8 checks | 0 FAIL | 7 OK, 1 WARN for the deliberately failed queue job; exit0. No warning was hidden or cleared. |
| Real Open-Meteo integration | PASS | 0 | Fresh provider response, 16 isolated stored records; no active data write. |
| Seven-migration rehearsal | 7 up + 7 down + 7 reapply | 0 | All critical historical comparisons pass. |
| Real backup / restore | PASS | 0 | `mysqlcheck` passes; 78/78 table counts and checksums match. |
| Fresh Composer/npm/Python installation | PASS | 0 | Actual source-copy installation on macOS; not Ubuntu provisioning. |
| Production dependency/cache rehearsal | PASS | 0 | Production Composer/no-dev and config/route/view cache results below. |
| Source / Blade / classes / shell syntax | PASS | 0 | Source/class/view checks plus 12 shell scripts. |
| Source archive reproduction | PASS | 0 | Identical manifest and gzip archive bytes in an independent output directory. |

The five normal SQLite skips are MariaDB/MySQL-specific timestamp/JSON cases, all exercised in the MariaDB suite. The POSIX slow-process regression runs on this platform and the Ubuntu target; its explicit Windows exclusion did not add a local skip. PHPUnit data-provider deprecations were corrected rather than ignored. Playwright prints runner `NO_COLOR`/`FORCE_COLOR` warnings; no unhandled page errors occurred in the verified page walks. Python's two dependency deprecations are recorded. npm install also reports optional package lifecycle-script review notices; production assets built successfully.

The 16 browser skips are viewport applicability/deduplication (desktop link rows versus mobile drawers/cards and tests that set their own widths). No required failure was hidden by a skip. Preliminary browser fixture failures were corrected by isolating fixture barangays. An initial custom API assertion assumed403; actual missing-Accept behavior redirected to HTML/200, so the API contract was fixed and now returns JSON422. Initial recovery selector/URL assumptions were corrected to the existing button and `/account/recover` route. The initially unbounded five-second inference test failed before its fix. Superseded full runs were stopped after discovering the deadline defect and are not reported as successful final runs.

Local evidence is retained in protected `/tmp/iclimate-rc2`. Sensitive SQL and cloned database files are removed after verification. Report, manifest, archive, checksums and approval instructions are durable companion artifacts in this repository.

## Database integrity

Active MariaDB10.4.28 was read-only: 877 accounts (872 Active legacy Farmers, 3 Active MAO Personnel, 2 retained retired IT accounts), 868 profiles, 1,081 farms, 124 rice-production records, 6,498 historical predictions, 100 applied migrations, 87 foreign keys, 339 indexes and zero orphans. The six RC1 migrations still remain pending there. RC2 adds a **seventh** pending migration:

`2026_11_18_000001_create_association_report_submissions`.

The new migration creates only a submission-history table with two protected relationships and per-report version uniqueness; it does not rewrite existing users, reports, reviews or predictions and does not backfill guessed history. A rollback refuses to drop populated submission history. Parent association/period deletion is rejected when formal history exists.

A fresh consistent read-only logical dump was restored into separately initialized **MariaDB10.11.19**, port19436. Applied all seven pending migrations, compared snapshots, rolled back seven while the new tables were empty, reapplied seven and compared again. Existing role/status groups, original columns, critical agricultural checksums, pre-existing row counts and foreign keys were preserved. Nine new tables were empty; exactly seven migrations were added; no FK was lost and 26 were added. The canonical-role rollback remains intentionally non-destructive rather than rewriting historical role values.

The final candidate's backup script then dumped the migrated clone. A second disposable restore passed checksum verification and `mysqlcheck`; **all 78 tables matched counts and MariaDB checksums**. This is logical migration/restore evidence on 10.11.19/macOS, not physical datadir portability or actual Ubuntu acceptance. The final active critical inventory matched the initial inventory exactly. Active background services may update their own operational caches during the audit; this task never wrote the active database.

Production requires approved backup/transfer, the six existing migrations plus the new history migration, `DB_TIMEZONE=+08:00` and distinct runtime/temporary-migration/backup privileges. Do not force a rollback after production submission history exists.

## ML and weather

**ACCURACY NOT VERIFIED. Keep `ICLIMATE_YIELD_GATE=withhold`.** Technical inference is not scientific validation.

The freshly installed Python3.12.15 environment loads rice and weather artifacts with sklearn1.6.1. `/health` is ok and `/model-info` matches deployed rice SHA-256 `f3a54e12ecf85e66340475e2c49ce5c15d9c58efd82213435015a275734b777c`. All twelve rice features, input rejection, service-error paths, Laravel integration and safe gate behavior are covered. Unauthorized metric-file paths return422; model switching without authorization returns403.

`/metrics` correctly reports `ready=false` with no approved evaluation observations; no accuracy result or agricultural record was fabricated. Seven deployed model files were checksummed and preserved. No deployed model retraining or formula modification was performed. The new report ratio fix concerns user-reported production divided by harvested area only.

Real Open-Meteo fetch/storage succeeded against isolated SQLite. Provider failures/cache behavior are covered by regression tests. SMTP, other credentialed APIs and target-VPS egress remain external acceptance requirements.

## Security and dependencies

Only the two authorized roles have protected access. Pending/rejected, retired-role, shared-password, recovery ownership/expiry/throttling, session revocation, CSRF, private-channel and private-document controls passed the listed checks. Production template enforces debug=false, lockdown=true, withhold and secure cookies; active environment/passwords were not changed. This is not a penetration-test certification.

- Composer locked audit: **0 advisories**; Laravel12.73.0.
- Python pinned requirement audit: **26 packages, 0 advisories**. pip-audit additionally warns that fully hashed transitive lockfiles would strengthen supply-chain assurance; it does not report a vulnerability.
- npm raw audit: **12 high dependency-tree entries, 0 critical/moderate**, all from the single [GHSA-fc5q-39xc-vjpf advisory](https://github.com/advisories/GHSA-fc5q-39xc-vjpf). The upstream registry has no fixed published version at verification. The installed code is mitigated by the documented source-hash guard and passing adversarial tests; **the raw audit is not described as clean**. No audit filter, version spoofing or forced obsolete plugin downgrade was used.
- Clean lockfile installation reapplies the mitigation; builds refuse an absent/unrecognized/tampered parser. Ordinary project globs and the complete asset/browser suites pass. Keep builds trusted and isolated; never expose Vite as the production server. Reassess/remove the local policy only after testing an actual upstream fix.

No unmitigated critical/high software defect was found in the exercised candidate after these fixes. The locally maintained mitigation remains a review/maintenance obligation, not a claim of upstream remediation.

## VPS compatibility

| Component | Verified | Still external |
|---|---|---|
| PHP/Laravel | PHP8.4.27, fresh Composer install, full suite and production cache rehearsal | Ubuntu PPA/FPM installation, socket/permissions and actual hostname |
| Node/Vite | Node22.23.5, clean npm install, mitigation check, JS suite and production build | Ubuntu installation and approved production public build settings |
| Python/ML | Fresh Python3.12.15 pinned installation, pip check, sklearn1.6.1, tests and live inference | Ubuntu wheels/compiler/service supervision |
| MariaDB | Actual10.11.19 migration/rollback/reapply/restore and integration | Target grants, service binding, OS timezone and DBA acceptance |
| Reverb | Actual private auth/event/reconnect and compiled notification badge update | Nginx/TLS proxy/origins and off-host network behavior |
| Queue/scheduler | Actual database worker, retry/failure, scheduler invocation and deduplication | systemd timers/restart recovery and production monitoring |
| Nginx/HTTPS/firewall | Corrected private include, ACME path and proxy configuration reviewed | `nginx -t`, certificates/renewal, UFW and remote denial tests |
| Provisioning | Bash syntax, mocked preflight safety, checkout/env/permission guards | No Ubuntu VM/container or Hostinger VPS available; installation is NOT VERIFIED there |
| Production dependency/cache mode | Normal no-dev Composer deployment command, package discovery and config/route/view caches all pass in an isolated copy. | Target service ownership and real credentials |

No claim is made that an actual VPS was installed or passed security acceptance. Healthy existing services were left running.

## Proposed release identity

Source fingerprint (SHA-256 of canonical sorted source manifest):

`c9070a44452b0263134b5e712e03e77dd5762743687ca4cee221413f0615065b`

Archive SHA-256:

`95ccd276db95a328f72aae250aca72b1bb2dbccddf51116314b6e275e9142844`

**1,486 source files**, independently extracted and checked. Companion artifacts:

- `docs/deployment/releases/2026.11.18-rc2/source.manifest`
- `source.manifest.sha256`, `source.tar.gz`, `source.tar.gz.sha256`, `release.json`
- `approved-paths.txt`, `APPROVAL.md`, `tag-annotation.txt`

The source archive excludes secrets, backups, logs, caches, vendor/node_modules/venvs, graph outputs and nondeployment research. The final RC2 report and release-metadata directory are intentionally companion artifacts outside the payload to avoid a self-referential fingerprint; the approval instructions explicitly exclude them from the source-tag commit. Previously tracked source and all required untracked application/deployment/test files are included. Legitimate published Blade views under `resources/views/vendor` are retained.

Archive metadata is normalized (file ordering, permissions, zero timestamps/owner, gzip header). Independent rebuilding produced identical manifest and archive bytes. The exact extracted source was tested separately, with independent Composer autoload files pointing to that source. Fresh npm/Python installation was rehearsed; final copies use those verified dependency versions. Generated assets and runtime caches are outside the source fingerprint.

Approved commit is deliberately **null**. Proposed tag is not created. Review the supplied staged-path plan, then explicitly authorize the commit and annotated tag; preserve RC1. The installer now requires explicit approved identity values and verifies a complete bundle. Do not install the old RC1 defaults or silently modify a proposed/approved release.

## Files changed

The manifest/path plan is the complete proposed source inventory. Significant changes carried from the prior verification: reporting-period controller fix, Composer/npm locks, backup/restore/install-check/Nginx/env templates, provision/deploy/runbook scripts, Playwright URL/output/shell portability fixes and existing new regression suites.

Additional RC2 changes:

- `app/Services/PythonService.php`, `bootstrap/app.php`.
- `app/Http/Controllers/AssociationChairmanController.php`, `MaoAssociationReportController.php`, new `AssociationReportDocumentController.php`.
- `app/Models/AssociationYieldReport.php`, `FarmerOrganization.php`, `AssociationReportingPeriod.php`, new `AssociationYieldReportSubmission.php`.
- New history migration; association route/schema guards; chairman and MAO report forms/details/history views.
- `package.json`; `scripts/security/harden-picomatch.mjs`, policy JSON and security explanation.
- `scripts/deploy/prepare-release.py`, release-comparison script, RC2 runbook and approval artifacts.
- Expanded browser fixtures/critical workflows, compiled Reverb probe, API/report/deadline regressions, packaging/security tests and PHPUnit attribute update.
- Required AST-only graph refresh. SQL parsing support remains absent from graphify; this does not replace runtime/SQL verification.

## External prerequisites

1. Owner reviews/approves the exact source candidate and local glob-parser mitigation; explicitly authorizes commit and annotated RC2 tag. Generate/verify its complete bundle and record the actual commit. No push/deployment is implied.
2. Obtain an authorized fresh Hostinger KVM Ubuntu24.04 environment and perform actual PHP-FPM, Node22, Python, MariaDB, Nginx/systemd/firewall installation and private-access acceptance using `deploy/hostinger/VPS-RUNBOOK-RC2.md`.
3. DBA/owner authorizes encrypted personal-data transfer, verified production backup/restore, seven migrations, restricted accounts and an approved MAO Head designation. Retain historical accounts and predictions; do not force history rollback.
4. Configure real SMTP/API/Reverb secrets, prove reset-email delivery, train staff on ownership verification/recovery, decide mobile rollout, and validate queue/failed-job, scheduler, off-host backup retention and service recovery.
5. Authorize DNS/TLS, prove certificate renewal, secure sessions/cookies and proxied WebSockets, test an unauthorized external IP is denied before private acceptance, then obtain explicit public launch approval.
6. Obtain independent approved agricultural observations/provenance and scientific validation before changing the yield gate. Missing research evidence does not justify invented accuracy or production data.

The application is not represented as fully verified on Hostinger until those actual target checks are complete. Public launch remains NO-GO; scientific yield output remains withheld.
