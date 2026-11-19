# iClimate end-to-end audit: independent verification addendum

**Verification date:** 2026-11-19 (Asia/Manila)  
**Primary report:** `docs/deployment/releases/2026.11.18-rc2-r3/SYSTEM-VERIFICATION-REPORT.md`  
**Requested baseline:** RC2-R2  
**Candidate actually found:** RC2-R3, which supersedes RC2-R2 after four verified fixes  
**Production writes/deployment:** none

This addendum records an independent rerun against the current working tree and corrects time-sensitive dependency and external-service findings in the primary report. It does not replace the detailed route, migration, ML, security, performance, and workflow evidence in that report.

## Executive decision

| Area | Verdict | Evidence-based qualification |
|---|---|---|
| Laravel backend | **PASS** | Full SQLite and MariaDB suites pass. |
| Database integrity | **PASS** | Active staging was queried read-only; isolated MariaDB exercised writes and migrations. |
| Internal APIs | **PASS** | 483 persona/route probes; no unhandled 500. |
| External APIs | **FAIL / BLOCKED** | Open-Meteo timed out from this environment; Groq rejected the configured key; WeatherAPI is not configured. PAGASA pages were reachable. |
| ML integration | **PASS** | Model loading, feature order, transport parity, failure handling, and safe display behavior pass. |
| ML input calculations | **PASS** | Climate-window, boundary, missing-input, and leakage regressions pass. |
| ML accuracy | **NOT VERIFIED** | No independent holdout with known training exclusion exists. The exploratory metrics must not be treated as validation. |
| Farmer prediction safety | **PASS** | Gate remains `withhold`; accuracy flag remains false; no numeric yield is displayed on failed/withheld paths. |
| Frontend and role workflows | **PASS** | Full browser suite and three-width verification suite pass. |
| Application/dependency security | **PASS** | Composer, npm production/full, and Python audits found no known advisories on 2026-11-19. Recommended CSP hardening remains. |
| Local performance/reliability | **PASS** | Under the test conditions documented in the primary report. Not a VPS benchmark. |
| Actual production infrastructure | **NOT VERIFIED** | No target VPS, DNS, TLS endpoint, SMTP route, systemd host, or off-host backup target was available. |
| Private VPS deployment | **CONDITIONAL NO-GO** | The software can proceed to an authorised isolated VPS rehearsal, but that infrastructure has not passed acceptance. |
| Public launch | **NO-GO** | External integrations, real VPS acceptance, data disposition, backups, TLS/SMTP, monitoring, and independent ML validation remain open. |

## Exact fresh regression results

| Suite | Result |
|---|---|
| PHPUnit, PHP 8.4.27, SQLite | **1,702 total: 1,697 passed, 5 expected MariaDB-only skips, 12,782 assertions, 0 failures** in 586.53 s |
| PHPUnit, PHP 8.4.27, MariaDB 10.11.19 | **1,702 passed, 12,795 assertions, 0 failures/skips** in 1,039.28 s |
| Targeted MariaDB rerun after an initial child-process timeout | **2 passed, 300 assertions** in 163.05 s; the subsequent clean full run above passed |
| Python, Python 3.12.15 / scikit-learn 1.6.1 | **69 passed**, 2 deprecation warnings, in 1.71 s |
| JavaScript, Node 22.23.5 | **26 passed**; all 3 picomatch copies passed the hash-enforced mitigation check |
| Vite production build, Node 22.23.5 | **PASS**, 119 modules transformed |
| Deployment/packaging tests | **25 passed** in 17.79 s |
| Playwright desktop/mobile | **178 passed, 18 intentional viewport skips** in 2.0 min |
| Playwright verification, desktop/tablet/mobile | **18 passed** in 42.8 s |
| Corrected end-to-end prediction safety trace | **2 passed** (desktop and mobile) in 8.4 s |
| API inventory/error contract | **2 passed, 13 assertions** in 1.14 s |
| Composer audit | **0 advisories** |
| npm production and full audits | **0 vulnerabilities** |
| Python `pip-audit` | **No known vulnerabilities** |

The first MariaDB run had two Python child processes exceed the 60-second test timeout while another disposable ML service was idle. Both tests passed unchanged in isolation, the stale service was stopped, and the entire 1,702-test MariaDB suite then passed cleanly. This was a test-environment resource contention event, not a code fix or suppressed failure.

## API evidence

The current Laravel route inventory contains **278 routes**: 137 GET (plus HEAD), 106 POST, 26 DELETE, 25 PUT, 21 PATCH, and 7 OPTIONS registrations.

The fresh in-process acceptance matrix made **483 requests** across **61 unique JSON-capable URIs**, 69 requests for each of Guest, Farmer, MAO Staff, MAO Head, Pending Farmer, Rejected Farmer, and retired IT identities.

| Status | Count | Interpretation |
|---|---:|---|
| 200 | 50 | Successful authorised/public responses |
| 303 | 19 | Intended workflow redirects |
| 401 | 240 | Authentication enforcement |
| 403 | 41 | Role/ownership enforcement |
| 404 | 85 | Missing resources or deliberately unavailable routes |
| 422 | 45 | Validation failures |
| 503 | 3 | Deliberately simulated unavailable dependencies |

There were **no unhandled HTTP 500 responses**. The three 503 responses are expected error-contract results, not unexpected server failures.

External checks on 2026-11-19 produced these results:

- Open-Meteo: connection/SSL timeout from both Laravel and a direct request. Its application failure path is handled, but live availability is not accepted.
- WeatherAPI: no credential configured, so it was not testable.
- Groq: configured credential returned HTTP 401 `invalid_api_key`.
- PAGASA regional and weekly pages: HTTP 200 HTML.

## ML verification and limitations

The deployed model artifacts remain unchanged:

- Rice yield model SHA-256: `f3a54e12ecf85e66340475e2c49ce5c15d9c58efd82213435015a275734b777c`
- Weather model SHA-256: `bbd598108bfed72375507ee0a5cd8a9ecc37bbbdca6917de0ea6934df8c95d47`

The primary report's independent integration evidence remains valid: the 12-feature order matches the artifact, direct model/FastAPI/Laravel comparison had 0 mismatches across 80 inputs, HTTP/backup/Laravel comparison had 0 mismatches across 40 inputs, all eight invalid-input classes had matching rejection behavior, and 180 full pipeline runs exposed no Farmer-visible yield value.

The fresh browser trace reached a date-correct missing-data branch. In November, the selected next Wet season requires future lag/season weather that does not exist; the system correctly made no model call, persisted a failed request, returned no `predicted_yield` property, displayed “Not enough data”, and showed no `t/ha` number. The test previously assumed every safe failure would be the accuracy-gate message. It was corrected to accept either legitimate safe outcome while still requiring the absence of a numeric prediction.

Accuracy remains **NOT VERIFIED**. The only exploratory deployed-model comparison has 19 usable 2024 rows and unknown training overlap: MAE 0.531, RMSE 0.673, R² 0.127, Pearson r 0.475. Those numbers describe the available sample only and are not evidence of production accuracy. No data was invented and no model was retrained or replaced.

## Database and migration evidence

The configured staging database was accessed inside a read-only transaction. It is MariaDB 10.4.28 and currently contains 877 users, 868 profiles, 1,081 farms, 6,498 yield predictions, 124 rice-production rows, and 100 applied migrations. Role/status counts are 872 active Farmers, 3 active MAO Personnel, and 2 historical active IT Expert records. Historical IT rows remain data, but the application remains a two-role system.

Checks found 87 declared foreign keys with 0 orphans, 0 duplicate emails, 0 duplicate profiles, 0 harvest dates before planting dates, and 0 exact duplicate yield-prediction keys. The database contains 713 `@iclimate.test` demo accounts; their disposition is an owner decision before launch, not an instruction to delete them.

Exactly seven RC2 migrations remain pending on that staging database:

1. `2026_11_16_000001`
2. `2026_11_16_000002`
3. `2026_11_16_000003`
4. `2026_11_16_000004`
5. `2026_11_17_000001`
6. `2026_11_17_000002`
7. `2026_11_18_000001`

They were rehearsed on isolated MariaDB infrastructure, including restore, migrate, rollback, re-migrate, and restricted runtime credentials. They were **not** applied to the configured staging or production data. The destructive rollback qualifications in the primary report and `ROLLBACK.md` remain mandatory: after live use, restore a verified backup instead of blindly rolling back the seven-migration set.

## Release identity and files changed by this addendum

The sealed RC2-R2 and RC2-R3 archives were preserved. Both their checksums and archives verified before the final test-only edit. RC2-R3 contained 1,507 files with source fingerprint `5587b145f0512c711e225c44dcb28f38b55659f23e413a10e5525b281c8604ab` and archive checksum `d2b30450…7034`.

This independent rerun changes only:

- `tests/Browser/prediction-trace.spec.mjs` — makes the end-to-end safety assertion date-robust and verifies the actual failed-status API shape.
- this addendum.
- generated `graphify-out/` knowledge-graph files after the required graph refresh.

Because the test file changed after RC2-R3 was sealed, the current working tree is no longer byte-identical to the RC2-R3 source manifest. Runtime application code and model artifacts were not changed by this rerun. Do not overwrite RC2-R3; create and verify a new immutable candidate only after the owner decides to include the test correction.

## Required deployment sequence

1. Owner reviews the RC2-R3 fixes and this post-R3 test-only correction; create a new immutable archive only if approved.
2. Provision an isolated private VPS and record OS, PHP 8.4 extensions, MariaDB, Python 3.12, Node 22, disk, memory, permissions, and service versions.
3. Validate the new archive checksum and manifest on that host before extracting it into a versioned release directory.
4. Configure secrets outside the archive; replace the invalid Groq key and configure/approve the intended weather provider. Keep `ICLIMATE_YIELD_GATE=withhold` and `ICLIMATE_YIELD_ACCURACY_VERIFIED=false`.
5. Run install checks, `composer audit`, full `npm audit`, `pip-audit`, JS tests, build, PHP tests, and Python tests on the VPS.
6. Verify nginx/PHP-FPM, TLS, secure cookies, SMTP/password recovery, Reverb, queue workers, scheduler, ML services, and all systemd units, including `systemd-analyze verify`.
7. Restore a checksum-verified database backup into an isolated database, rehearse the seven migrations, verify integrity and workflows, then rehearse restoration. Do not use the active database for this drill.
8. Configure encrypted off-host backups, retention, monitoring, log rotation, health alerts, and an operator-tested restore procedure.
9. Repeat multi-role browser, API, external-service, prediction safety, performance, and failure/recovery checks on the actual target infrastructure.
10. Obtain explicit approval before any production migration, traffic switch, public deployment, commit, tag, or push.

## Final recommendation

The current application is functionally strong and its internal regression evidence is a **PASS**. The deployed model is integrated correctly but its accuracy is **NOT VERIFIED**, so Farmer-facing numeric estimates must remain withheld. Actual infrastructure acceptance is **NOT VERIFIED**. Proceed only to an authorised, isolated private-VPS installation rehearsal. Public launch remains **NO-GO** until the external API credentials/availability, target-host acceptance, data disposition, backup/restore, TLS/SMTP, monitoring, and independent ML validation blockers are resolved.
