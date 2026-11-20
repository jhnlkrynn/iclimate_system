# iClimate deployment checklist — 2026-11-20

**Scope:** Final staging readiness and deployment preparation on `feature/2026-11-20-yield-monitoring-security` (the five commits plus final preflight fixes).
**Production writes, deployment, commits, pushes:** none. Active staging data (877 users, 713 demo accounts, 1,081 farms, 7 pending migrations) untouched.
**Updated:** 2026-11-20, final staging readiness pass.
**Overall:** **GO for private staging rehearsal / RC2 packaging** once owner approves commit/tag. **Public launch remains NO-GO** until the external, credential, and owner-approval items in section C are closed.

## A. Fixed in this pass

| Item | What changed | Evidence |
|---|---|---|
| Weak CSP (R3 §8 hardening) | Per-response script nonce, `'strict-dynamic'`, `script-src-attr 'none'`, `default-src 'self'`. Blade `<script @nonce>` on all 37 executable script tags. The 17 inline `on*` handlers became `data-autosubmit` / `data-print` / `data-window-close`. Fetched widgets get the page nonce. `SECURITY_CSP_STRICT=false` restores the old policy (rollback only). | `StrictContentSecurityPolicyTest` (3). Browser crawl of 15 farmer pages: 0 violations, 0 page errors. `verification.spec.mjs` now fails on any CSP violation for Farmer and MAO pages (6 passed). A deliberate inline `onclick` was blocked and reported. |
| `'unsafe-eval'` in the CSP (second pass) | Alpine.js replaced by its CSP build `@alpinejs/csp@3.15.12`. All logic moved into registered components (`resources/js/alpine-components.js`: `modal`, `modalTrigger`, `flash`); templates only name properties and methods. `'unsafe-eval'` removed from `script-src`. | The built bundle has no `new Function`/eval (the only match is axios's `"AsyncFunction"` type name). `alpine-csp.spec.mjs` (desktop and mobile): the delete-account modal opens, keeps focus inside, closes on Escape and Cancel; the "Saved." note shows and fades; no CSP violation or Alpine error. Live crawl of 18 farmer pages: 0 violations, 0 page errors. |
| Profile save returned HTTP 500 (found while testing) | A farmer without a profile row saving the profile inserted a profile with no barangay (NOT NULL). A blank barangay also tried to clear it. Now: the profile is created from the chosen or account barangay; with neither, the form asks for it; a blank choice keeps the saved one. | 4 staging farmer accounts had no profile row and would have hit this. `ProfileTest` +3 tests; the browser save flow passes. |
| Session data at rest | Production template sets `SESSION_ENCRYPT=true`. | Template + deployment tests pass. |
| Monitoring gaps | `iclimate:health` adds `backup` (FAIL when stale), `backup-offsite` and `yield-inputs` (archive and 18-month forecast). FAILs are e-mailed to `HEALTH_ALERT_EMAIL` at most hourly. | `SystemHealthCommandTest` (+2). The new check found the local backup 2 days stale; a manual backup then ran cleanly. |
| No encrypted off-host backups | `iclimate:backup-offsite` (daily 03:30): OpenSSL AES-256-CBC with PBKDF2 at 200k iterations, a key file of mode 600 or stricter, a decrypt-and-compare check before sending, a directory or rsync/SSH target, retention. | `OffsiteBackupTest` (5). Drill below. |
| Backup/restore drill | Real backup (41.12 MB), encrypted, decrypted (SHA-256 OK), restored with `restore-isolated-mariadb.sh` into MariaDB 10.11.19 (an isolated instance, port 3317). | 69/69 tables, 87 FKs, 339 indexes and 33 CHECK constraints identical to staging. One table differed only because the scheduler rewrote it 1 minute after the dump. |
| 7 pending migrations | Re-rehearsed on the restored copy: migrate → rollback 7 → migrate. | 7 DONE, 0 pending. 877 users, 1,081 farms preserved. FK audit 87/87, 0 orphans. 0 duplicate e-mails or profiles, 0 harvest-before-planting rows. |
| SMTP not configured in the production template | `MAIL_*` placeholders (587/STARTTLS). `deploy-release.sh` refuses a `log`/`array`/empty mailer. | Deploy test `test_log_mailer_refused`. Over a local SMTP listener, a reset e-mail and a health alert were both delivered. |
| TLS not hardened | nginx: TLS 1.2/1.3 only, no session tickets, `server_tokens off`, HSTS, one copy of each security header (it previously sent two conflicting Permissions-Policy values), immutable `/build/` caching. | `nginx -t` OK. Live nginx + PHP-FPM 8.4: TLS 1.0/1.1 refused (`alert protocol version`), TLS 1.2/1.3 accepted, 301 to HTTPS, `/.env` 403, cookies `secure; httponly; samesite=lax`. |
| Log rotation | `deploy/hostinger/iclimate-logrotate` (weekly, 8 kept, 20 MB cap), installed by provisioning. | `logrotate -f` test rotated both sample logs. |
| Python dependency advisory | `certifi` pinned to 2026.10.18 (PYSEC-2026-4320 in 2026.7.22). | `pip-audit`: requirements clean; the installed environment is clean after upgrade. |
| Gate in the production template | `ICLIMATE_YIELD_GATE=withhold` (it was `label`), as RC2 runbook step 3 requires. | `deploy-release.sh` accepts withhold or label, never off. The accuracy flag stays false. |
| Deployment test bug | `test_enabled_accuracy_flag_refused` reused the previous env file. | 26 of 26 deployment tests pass. |
| Ops runbook | RC2 runbook step 7a: weather archive, 18-month forecast, encryption key, off-host copy, restore from the encrypted copy, alert test. | `deploy/hostinger/VPS-RUNBOOK-RC2.md` |
| Scoped-user `migrate --pretend` / `migrate` crash | `AppServiceProvider::createMysqlDatabaseForMigrations()` ran `CREATE DATABASE IF NOT EXISTS` unconditionally, failing with `1044 Access denied` for scoped accounts (`iclimate_app` / `iclimate_migrator`) and on `--pretend`. Now skips `--pretend`, checks `dbname=` connectivity first, and only creates on `1049 Unknown database` with `DB_CONNECT_TIMEOUT`. | `php artisan migrate --pretend --no-ansi` exits 0 and previews all 7 pending migrations against `iclimate_staging_20261103_fix3`. |
| Deployment README drift | `deploy/hostinger/README.md` listed mode `0600` for `/etc/iclimate/iclimate.env` (blocking `www-data` reads) and referenced 6 migrations instead of 7. Aligned with `0640` (`root:www-data`) and `VPS-RUNBOOK-RC2.md` (7 migrations). | Inspected alongside `provision-ubuntu-24.04.sh`, `install-check.sh`, and `VPS-RUNBOOK-RC2.md`. |

## B. Final verification (re-verified on `feature/2026-11-20-yield-monitoring-security`)

| Requirement | Result | Evidence |
|---|---|---|
| PHPUnit (SQLite, PHP 8.4.27 target) | **PASS** | 1,726 passed, 5 MariaDB-only skips, 0 failed (13,670 assertions) |
| PHPUnit (MariaDB 10.11.19, PHP 8.4.27) | **PASS** | 54 passed, 363 assertions, 0 skipped, 0 failed (`TimestampRefreshMigrationTest`, `TwoRoleAccessTest`, `AssociationReportIntegrityTest`, `AssociationReportingWorkflowTest`, `ProfileTest`, `DeploymentReadinessTest`, `StrictContentSecurityPolicyTest`, `OffsiteBackupTest`, `SystemHealthCommandTest`) |
| Isolated MariaDB 10.11.19 + PHP 8.4.27 rehearsal | **PASS** | Restored 43.1 MB staging backup; `migrate --pretend` OK; 7 up → 7 down → 7 reapply; `two-role-migration-check.sh compare` OK (78 tables, 107 migrations, 26 FKs added, 0 lost); 877 users, 713 demo accounts, 1,081 farms preserved |
| Encrypted backup & restore drill (MariaDB 10.11.19) | **PASS** | `mariadb-dump` → OpenSSL AES-256-CBC PBKDF2 (200k iter) → decrypt SHA-256 identical → `restore-isolated-mariadb.sh` + `mysqlcheck` 78/78 tables `OK` |
| Playwright full suite (`npm run test:browser`) | **PASS** | 180 passed, 18 intentional viewport skips, 0 failed (desktop-1280 + mobile-375; includes Alpine CSP and Farmer/MAO CSP-violation checks) |
| Python (ML + deployment tooling) | **PASS** | 97 passed (`.venv-ml/bin/pytest python_scripts/tests tests/deployment -q`), `pip check` clean |
| JavaScript (`npm run test:js`) | **PASS** | 26 passed, `picomatch` mitigation verified |
| Composer / npm audits | **PASS** | `composer audit`: 0 advisories; `npm audit` (prod & full): 0 vulnerabilities |
| Vite build (`npm run build`) | **PASS** | Built cleanly in 1.78s (`picomatch` mitigation verified) |
| PHP & shell syntax | **PASS** | `php -l` 0 failures on changed files; `bash -n` 0 failures across all 10 scripts in `deploy/hostinger/` and `scripts/deploy/` |
| Config/route/view cache cycle | **PASS** | `config:cache`, `route:cache`, `view:cache` all succeeded, then cleared cleanly |
| Model artifacts preserved | **PASS** | Rice `f3a54e12…b777c`, weather `bbd59810…d47`: unchanged; `/health` reports `rice, weather` |
| Staging DB & health checks | **PASS** | `iclimate:environment-check` OK (`10.4.28-MariaDB`, `+08:00`); `iclimate:health` OK for DB, ML, Reverb, queue, scheduler, backup, yield-inputs; 877 users, 713 demo accounts, 1,081 farms preserved; 7 migrations pending |
| Farmer yield gate | **PASS** | `ICLIMATE_YIELD_GATE=withhold` and `ICLIMATE_YIELD_ACCURACY_VERIFIED=false` in `.env.example` and `iclimate.env.example` |
| ML accuracy | **NOT VERIFIED** | No independent real-harvest evidence. Estimates stay gated (`withhold`). |

## C. Remaining blockers by classification

### Resolved (locally fixed & verified)
- **Weak CSP & `'unsafe-eval'`**: Fixed with per-response nonces, `'strict-dynamic'`, and `@alpinejs/csp@3.15.12`.
- **Missing-profile save HTTP 500**: Fixed in `ProfileController` with `ProfileTest` coverage.
- **Scoped-user `migrate --pretend` / `migrate` `CREATE DATABASE` 1044 error**: Fixed in `AppServiceProvider::createMysqlDatabaseForMigrations()`.
- **Monitoring, encrypted off-host backups, TLS hardening, logrotate, `certifi` pin, production `withhold` gate, `README.md` drift**: All implemented and verified.

### Externally Dependent (requires target infrastructure / credentials)
| Blocker | Owner | Status |
|---|---|---|
| Target VPS (Ubuntu 24.04, PHP-FPM 8.4, MariaDB 10.11, Python 3.12, Node 22) and `systemd-analyze verify` | Owner / infrastructure | **FAIL — external.** No target Ubuntu host provisioned yet. |
| DNS and real TLS certificate for `iclimate-lian.tech` | Owner | **FAIL — external.** Tested with self-signed certificate only. |
| SMTP provider credentials and live reset-email delivery | Owner | **FAIL — credentials.** Transport proven against local listener only. |
| Off-host backup destination and off-server encryption key (`/etc/iclimate/backup.key`) | Owner | **FAIL — external.** Tooling ready; `iclimate:health` warns until configured. |
| Groq API key | Owner | **FAIL — HTTP 401 `invalid_api_key`.** Assistant degrades safely without it. |
| WeatherAPI (optional second provider) | Owner decision | Optional; Open-Meteo primary works. |

### Requiring Approval
| Blocker | Owner | Status |
|---|---|---|
| Commit, annotated `v2026.11.18-rc2` tag, and bundle/manifest generation | Owner | **Approval pending.** Working tree left uncommitted for review. |
| Applying the 7 pending migrations to staging/production | Owner + DBA | **Approval pending.** Rehearsed and `--pretend` verified. |
| 713 `@iclimate.test` demo accounts and seeded data retention/cleanup | Owner / MAO | **Decision pending.** All 713 preserved untouched. |
| Independent ML validation & switching `ICLIMATE_YIELD_GATE` from `withhold` | Researchers / Owner | **Approval pending.** Remains `withhold` in production template. |
| Enabling `ICLIMATE_PASSWORD_LOCKDOWN=true` in production rollout | Owner | **Approval pending.** On in production template. |

## Operator notes

- The local Mac missed scheduled backups while asleep at 03:15. On the VPS the systemd timer covers this, and `iclimate:health` now raises it.
- `script-src` no longer allows `'unsafe-eval'`. New Alpine behaviour must be added as a registered component in `resources/js/alpine-components.js`: the CSP build ignores inline JavaScript in `x-` attributes.
- `'unsafe-inline'` and `https:` in `script-src` are ignored by browsers that support nonces; they only keep very old browsers working. Inline `style` attributes are still allowed (`style-src 'unsafe-inline'`).
- Rollback: `SECURITY_CSP_STRICT=false` restores the previous loose policy without a deploy.
- The nginx `listen … http2` deprecation warning is expected: Ubuntu 24.04 ships nginx 1.24, which does not have the standalone `http2` directive.
