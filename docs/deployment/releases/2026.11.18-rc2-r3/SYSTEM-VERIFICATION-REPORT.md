# iClimate: complete system verification and readiness audit (2026-11-18/19)

**Candidate verified:** RC2-R3. Fingerprint `5587b145f0512c711e225c44dcb28f38b55659f23e413a10e5525b281c8604ab`, archive `d2b30450…7034`, 1,507 files, base RC1 `d43cd1aa`.

**Supersedes:** RC2-R2. That candidate failed this audit on three points: a Farmer-safety defect, a PDF crash and a queue-restart defect.

**What was not done:** nothing was committed, tagged, pushed, merged, deployed or migrated in production. No model was retrained. No weather or harvest data was created. Production data was read only, with SELECT queries.

**Model:** sha256 `f3a54e12…b777c`, identical to RC1.

## Method

The verification was done on an isolated, production-shaped QA environment, not by re-reading earlier results:

- **Code:** the exact r3 source, the same 1,507 files, verified against the manifest.
- **Mode:** production (`APP_ENV=production`, `APP_DEBUG=false`).
- **Database:** MariaDB 10.11.19 with Laravel's `mysql` driver. The audit-clone backup of production data was restored with its checksum verified: 69 tables, 114,779 rows, 898 users. The 7 RC2 migrations were applied with a temporary DDL account. The application ran as a **SELECT/INSERT/UPDATE/DELETE-only** account.
- **Services:** the real ML service (FastAPI), Reverb, a database queue worker, database cache and sessions, and the PHP 8.4 web server.
- **Evidence collected:**
  - real HTTP requests as Guest, Farmer, MAO Staff, MAO Head, a retired IT account and a Pending account;
  - Chromium browser sessions at 1280, 768 and 375 px;
  - direct database queries;
  - service stop and restart tests;
  - load tests.

## Verdicts

| Component | Verdict |
|---|---|
| Laravel Backend | **PASS** |
| Database Integrity | **PASS** (with data findings: §2) |
| APIs | **PASS** |
| ML Integration | **PASS** |
| ML Input Calculations | **PASS** |
| ML Accuracy | **NOT VERIFIED** |
| Farmer Prediction Safety | **PASS** (after fix F1) |
| Frontend and User Workflows | **PASS** |
| Security | **PASS** (no critical or high findings; hardening items in §8) |
| Performance | **PASS** (under the documented test conditions) |
| Production Infrastructure | **NOT VERIFIED** (no VPS exists) |
| Public Launch | **NO-GO** |

## Defects found and fixed in this audit

| # | Severity | Defect | Fix | Regression evidence |
|---|---|---|---|---|
| F1 | **P1: Farmer safety** | Saved model estimates bypassed the yield gate and were shown to Farmers as "Unvalidated estimate" numbers, on 5 surfaces: calendar, My Reports (screen **and PDF**), `/analysis/yield`, the yield survey, and saved chat history. QA data: **6,606 planting schedules of 715 Farmers** plus 5 yield requests. My Reports' farm estimates also skipped the dashboard's gate check. | `YieldModelGate::showsStoredEstimate()`: under `withhold`, saved estimates and their derived total and outlook stay hidden until accuracy is verified. The check happens at display only; stored rows are never modified. | `FarmerStoredEstimateGateTest` (3 tests) fails on unfixed r2 and passes on r3. Three existing tests asserted the unsafe behaviour and were updated. A browser and HTTP scan of all 44 Farmer-reachable paths found **no model value** after the fix. |
| F2 | P2: reliability | `/reports/export/pdf` returned **HTTP 500** for an 871-row report. DomPDF needs ~0.18 MB per row, and the 1,500-row cap exceeded PHP-FPM's 128 MB. | The cap is now cell-based: 1,500 cells, so 250 rows at 6 columns (measured peak ~110 MB). The PDF already says "Download Excel for all rows". | `ReportPdfRowLimitTest`. All 4 QA reports export as PDF under 128 MB (159–674 ms). |
| F3 | P2: reliability | `iclimate-queue.service` gave up after 10 restarts in 5 minutes. A MariaDB outage of more than ~50 s therefore left the queue **stopped until a manual `reset-failed`**. Observed: the worker exits while the database refuses connections. | `RestartSec=15`, `StartLimitIntervalSec=0`: restarts indefinitely, one attempt per 15 s. | Unit-file review; QA outage test (worker exited, came back after the restart). `systemd-analyze verify` is a VPS checklist item. |
| F4 | P3: performance | `SchemaCache` read the cache store on every call: ~200 database round trips per Farmer page with the database cache store, 97 of them for the version key alone. | Memoised per request on the container, with a 5 s lapse for long-running workers. `forget()` still applies immediately. | `SchemaCacheMemoTest` (3 tests). |

**Files changed (r2 → r3):**
- `app/Services/Prediction/YieldModelGate.php`
- `app/Http/Controllers/FarmerReportController.php`
- `app/Http/Controllers/ReportExtrasController.php`
- `app/Support/SchemaCache.php`
- `deploy/hostinger/iclimate-queue.service`
- `lang/en/farmer.php`, `lang/fil/farmer.php`
- `resources/views/{calendar/index, analysis/yield, components/ai-chat-widget, farmer-records/_my-reports-body, farmer-records/survey}.blade.php`
- tests: 3 new (`FarmerStoredEstimateGateTest`, `SchemaCacheMemoTest`, `ReportPdfRowLimitTest`) and 3 updated (`CalendarPageTest`, `Manuscript/Phase5YieldTest`, `Manuscript/Phase7ReportsTest`).

**Against RC1:** 45 files added, 62 modified, 0 deleted.

## 1. Laravel backend: PASS

**Route inventory:** 278 routes (137 GET, 106 POST, 25 PUT, 21 PATCH, 26 DELETE).

**Authorisation matrix:** 91 parameter-free GET routes × 4 personas, over real HTTP: **0 server errors and 0 timeouts**.
- Guests never received a protected page: pages redirect to login, and APIs return 401 JSON.
- Farmers received 403 on all 16 MAO-only routes. `/users` is MAO Head only: ordinary MAO gets 403.
- Every remaining 302/303/404 was traced to its intended middleware or validation. For example, `farmer.redirect`, and report exports that require `?report=`.

**Workflows exercised with database persistence:**
- concurrent account approval: one 302 and three **409** conflicts; exactly 1 review row and 1 approval email;
- report generation for 3 report types;
- CSV, XLSX, PDF and print export;
- mobile-API login, register, forgot and password-change validation;
- the variety harvest-date estimate;
- a scheduler run;
- queued analytics jobs;
- weather refresh (a real Open-Meteo fetch: 16 records).

**PHPUnit from the extracted r3 archive (PHP 8.4.27):**

| Database | Result |
|---|---|
| SQLite | **1,702 tests: 1,697 passed, 0 failed, 5 MariaDB-only skips** |
| MariaDB 10.11.19 (`mysql` driver) | **1,702 of 1,702 passed, 0 skipped** |

**Two ML integration tests** need a project `.venv`. With it linked they pass from the archive, as they do in the working tree.

## 2. Database integrity: PASS, with data findings

**Integrity checks:**
- **113 declared foreign keys: 0 orphans.** Undeclared `*_id` columns with an obvious target: 0 orphans.
- No duplicate emails, profiles, notification deduplication keys, forecast rows or yield-prediction rows.
- Stored yield arithmetic matches its inputs: rice productions and the 41 historical records.
- No harvest dates before planting dates, and no implausible climate records.

**Migrations, backup and restore on MariaDB 10.11:**

| Step | Result |
|---|---|
| Restore from the 10.4 backup | Columns, CHECKSUM TABLE, row counts and foreign keys identical |
| 7 migrations | Every data-preservation check OK |
| Rollback of all 7 | Identical to the pre-migration database |
| Re-migrate | Identical to the first migration |
| Backup and restore with the production scripts | Identical |

The restricted runtime account works.

**Migrations that are unsafe to roll back after real use:**
- `2026_11_16_000002` drops six association tables;
- `2026_11_16_000004` drops the invitations table;
- `2026_11_17_000002` drops the Farmer review history;
- `2026_11_16_000003` fails once associations have no farm type;
- only `2026_11_18_000001` refuses when data exists.

See `ROLLBACK.md`: never roll back more than one step after use.

**Data findings (owner action, not code defects):**
1. **The live database holds 713 demo accounts (`@iclimate.test`) among 877 users,** created by demo seeders. 75 of them have two "primary" farms created in the same second. Real accounts are unaffected; this was checked read-only on live.
2. Rice production figures are seeded allocations of municipal totals (known). There are 18 historical failed jobs from November outages of the ML service and Reverb.
3. The QA source (the audit clone) contained real-time-audit test rows. Live has none, checked read-only.

## 3. APIs: PASS

**The 12 `/api/*` routes plus 8 JSON dashboard and feed endpoints**, as Guest, Farmer and MAO:

| Condition | Response |
|---|---|
| Unauthenticated | 401 JSON |
| Wrong role | 403 JSON |
| Invalid input | 422 JSON with field errors (login, register, forgot, password, variety estimate) |
| Unknown barangay | 404 JSON |
| Every endpoint | Throttled; login throttled at 5 per minute (429 observed) |

**Pagination, filters and sorting:** covered by the PHPUnit suites, and the list views were exercised in the browser.

**ML API:**
- `/predict` rejects 8 classes of impossible input, with the same message on the backup path;
- `/model/switch` returns 403 with no token or a wrong token;
- `/model-info` reports the deployed checksum.

## 4. ML integration: PASS. ML input calculations: PASS

**Pipeline tested end to end:** Laravel → FastAPI → model → Laravel → database → dashboard.

| Check | Result |
|---|---|
| Feature order | Matches `feature_names_in_` exactly; preprocessing unchanged |
| Pipeline sample (30 Farmers × 6 months) | Identical to r2 on all 180 runs |
| Formerly frozen features | 0 of 8 still frozen; each takes 4 values |
| Direct `model.predict` vs FastAPI vs Laravel | 80 distinct inputs, **0 mismatches** |
| HTTP service vs backup CLI vs Laravel | 40 inputs, 0 mismatches |
| ML service stopped | Local inference returns the same raw value (3.34), still withheld; pages load in 25–75 ms |
| Missing 2026 weather | Explicit missing inputs, no model call. Farmer sees "Not enough weather records yet for an estimate." |
| Future-data leakage | Unit tests prove lags never read the target month or later; observed months are used before forecasts |

## 5. ML accuracy: NOT VERIFIED

**Data:**
- **Only evaluation source:** 41 MAO barangay-season rows ("RICE-PRODUCTION (LIAN).xlsx", 4 seasons 2024–2025, plus 1 QA test row ignored).
- **Usable records:** 19, all from 2024. 2025 seasons need 2026 weather, which doesn't exist and was not invented.
- **Training overlap:** the training rows are unknown, so overlap **cannot be ruled out**.

**Results:**

| Measure | Value |
|---|---|
| MAE | 0.531 |
| RMSE | 0.673 |
| R² | 0.127 |
| Pearson r | 0.475 |
| Mean baseline MAE (in-sample / leave-one-out) | 0.628 / 0.663 |
| MAE with frozen RC1 inputs (same records) | 0.779 |

**Error structure:**
- **Season level only.** Predictions vary between seasons but are almost identical across barangays within one: Wet 3.36–3.39, Dry 3.94–4.01.
- **Spread compressed.** Prediction SD is 0.30, against 0.72 observed.
- **Bias towards the middle.** Low-yield barangays are over-predicted by about +0.73 and high-yield ones under-predicted by about −0.23. The worst barangays are Luyahan (MAE 0.99) and Tan-ag San Diego (0.90).

**Conclusion:** the model mostly predicts the season, not the barangay or the farm, and farms under 12 ha share tree branches. **Accuracy is NOT verified.**

## 6. Farmer prediction safety: PASS (after F1)

**Configuration:**
- `ICLIMATE_YIELD_GATE=withhold` and `ICLIMATE_YIELD_ACCURACY_VERIFIED=false` are the defaults and are set in both env templates. The installer refuses any other value, duplicated keys, and a driver other than `mysql`.
- A gate value like `not-a-mode` is treated as `withhold` (regression test).

**Results:**

| Check | Result |
|---|---|
| Pipeline runs | 180/180 withheld |
| Small and large farms (0.25–29.96 ha), 6 target months | 0 numbers shown |
| Farmer pages and JSON (44 paths) | No model value after F1 |
| Heat-map yield layer | null |
| Notifications and messages | No yield numbers |
| PDF | Same gated data as the screen |
| ML fallback path | Still withheld |

**MAO-facing (policy item, not changed):** MAO staff still see labelled unverified estimates, for example the MAO dashboard's "Expected Yield (estimate)" and the barangay forecast list. The owner should decide whether MAO also sees nothing until validation.

## 7. Frontend and user workflows: PASS

**Browser crawl:** 100 routes × 6 personas at 1280 px, plus every permitted page for Farmer, MAO and MAO Head at 375 and 768 px.
- **0 page exceptions and 0 horizontal overflow.**
- The retired IT and Pending accounts are refused at login.

| Console message | Cause |
|---|---|
| 401/403 | Disallowed navigation |
| 429 | The crawler outpaced the public weather throttle (20 per minute) |
| Aborted OSM tiles | Fast navigation |

**Accessibility:**
- Images have alt text, and buttons and links have accessible names.
- The only unlabeled field is the anti-spam honeypot (`aria-hidden`).
- Every HTML page has a `lang` attribute; only JSON or fragment endpoints lack one.

**Playwright from the r3 archive:**
- **178 passed, 0 failed** (18 viewport-specific skips);
- verification suite at desktop, tablet and mobile: **18 passed**.

## 8. Security: PASS (no critical or high defects)

**Probe: 38 of 38 passed.**

| Area | Result |
|---|---|
| Headers | X-Frame-Options SAMEORIGIN, nosniff, Referrer-Policy, CSP and Permissions-Policy present |
| Cookies | Session HttpOnly + SameSite=Lax |
| CSRF | 419 without a token |
| Leakage | No stack traces, paths or SQL on 404, malformed input or a database outage (clean 503) |
| Sensitive files | `.env`, `.git`, `composer.json`, `vendor/` and logs not served |
| Cross-account access | Farmers cannot read, update or delete another Farmer's farm, schedule, profile, prediction, survey or messages |
| Privilege escalation | Profile mass assignment cannot grant MAO; Farmers refused on staff writes; MAO Staff refused on account creation |
| Uploads | Validated by real MIME type; stored under generated names |
| ML | Model switch refused; heat-map and API give Farmers no yield numbers |

**Dependencies:**
- Composer: 0 advisories.
- npm production dependencies: 0.
- npm dev/build: one `picomatch` advisory, with the hash-verified mitigation enforced by the build.
- The Python `pip-audit` was **not run** (tool download not permitted; owed on the VPS).

**Hardening recommended (not blockers):**
1. The CSP allows `'unsafe-inline' 'unsafe-eval'` and any `https:` source, so it gives little XSS protection.
2. `SESSION_SECURE_COOKIE=true` is enforced by the installer but can only be verified over real TLS.

## 9. Performance and reliability: PASS (documented conditions)

**Test conditions:** a MacBook, PHP 8.4 built-in server with 6 workers (not PHP-FPM or nginx), MariaDB 10.11 on localhost.

**Per page, in-process, 142 page/role pairs:**
- server time median 5 ms, p95 31 ms, max 56 ms;
- the slowest query was 3.3 ms;
- the N+1 signal was entirely cache-store reads (fixed in F4).

**Load:** 30 concurrent sessions for 45 s gave **17,377 requests at 385 req/s**.
- **0 server errors.**
- Latency p50 74 ms, p95 112 ms, p99 130 ms, max 345 ms.
- 2,039 responses were 429 from the per-user throttles; each login was shared by 10 clients.

**Concurrency:** four simultaneous approvals of one account gave 1 success, 3 × 409 and no duplicate side effects.

**Restarts and outages:**

| Event | Behaviour |
|---|---|
| ML service down | Fallback inference; health check reports it |
| Reverb down | Broadcast queued, then delivered within 35 s after restart |
| Database down | Clean 503 everywhere; `/up` stays up; full recovery after restart. Reverb exits while the database refuses connections (its restart signal uses the database cache); systemd restarts it every 5 s. The queue worker exits too, now covered by F3. |
| Scheduler | Health check confirmed against live (48 fetches per 24 h) and QA (after a forced fetch) |

## 10. Production infrastructure: NOT VERIFIED

**No VPS exists.** These items cannot be verified without it:
- PHP 8.4 and its extensions on Ubuntu 24.04;
- MariaDB 10.11 as an Ubuntu service;
- Python 3.12 with the pinned requirements and **pip-audit**;
- **Node 22**: `npm ci`, the build and the 26 JS tests;
- systemd units (`systemd-analyze verify`), including the F3 change;
- TLS and DNS;
- SMTP delivery and account recovery;
- off-host backups and a restore drill on the VPS;
- logging, monitoring and alerting.

`VPS-CHECKLIST.md` lists each one.

## 11. Test counts (exact r3 candidate)

| Suite | Result |
|---|---|
| PHPUnit PHP 8.4, SQLite | 1,702: 1,697 passed, 0 failed, 5 skipped (MariaDB-only) |
| PHPUnit PHP 8.4, MariaDB 10.11 `mysql` | 1,702 of 1,702 passed |
| Python | 69 passed |
| JavaScript (Node 26.10) | 26 passed |
| Deployment and packaging (repository) | 25 passed. From the archive, 11 fail only because the RC1 bundle artifacts are intentionally excluded. |
| Playwright desktop/mobile / verification (3 widths) | 178 passed (18 skips) / 18 passed |
| HTTP authorisation matrix | 364 requests, 0 errors |
| Browser crawl | 856 page visits, 0 exceptions, 0 overflow |
| Security probe | 38 of 38 |
| ML consistency | 80 + 40 inputs, 0 mismatches; 180 pipeline runs identical to r2 |
| Load | 17,377 requests, 0 5xx |
| Reproducible build | Byte-identical rebuild; the QA tree equals the manifest (1,507 files) |

## 12. Remaining blockers and next actions

1. **Owner: approve the RC2-R3 commit and tag** (`APPROVAL.md`, expected tree `173d66d3…`).
2. **Owner/MAO: separate or remove the 713 demo accounts** (and their seeded farms and productions) before public launch. Decide what real data the production database should start with.
3. **MAO: enter the observed 2026 monthly weather.** Until then every current estimate reads "Not enough weather records yet".
4. **Owner policy:** whether MAO staff should also see no unverified estimates (§6).
5. **VPS (checkpoint 2):**
   - provision;
   - run Node 22 and `pip-audit`;
   - run `systemd-analyze verify`;
   - test TLS, SMTP and recovery;
   - set up off-host backups and a restore drill;
   - set up monitoring.
6. **Hardening:** a stricter CSP (nonces or hashes for inline scripts).
7. **Before `ICLIMATE_YIELD_ACCURACY_VERIFIED`:** an independent validation on harvests not used for training, with the training rows documented. Today the model only distinguishes seasons.

## Final decision

- **Software release candidate RC2-R3: GO for owner approval.** Commit and tag only. All fixable defects are fixed and every suite passes on the exact candidate.
- **ML accuracy: NOT VERIFIED.** Estimates stay hidden from Farmers.
- **Private VPS installation: CONDITIONAL GO.** It needs the approved tag and an authorised VPS.
- **Public launch: NO-GO** until the blockers in §12 (items 2, 3, 5) are closed.
