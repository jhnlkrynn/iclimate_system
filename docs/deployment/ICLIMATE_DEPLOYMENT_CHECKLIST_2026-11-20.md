# iClimate deployment checklist — 2026-11-20

**Scope:** every NO-GO item in `ICLIMATE_END_TO_END_AUDIT_ADDENDUM_2026-11-19.md` plus the RC2-R3 report's open items, re-checked against the current working tree on `release/2026.11.18-rc1`.
**Production writes, deployment, commits, pushes:** none. Every drill ran on throwaway copies.
**Updated:** 2026-11-20, second pass (Alpine CSP build, `'unsafe-eval'` removed, profile-save fix, final full retest).
**Overall:** all locally resolvable blockers are cleared. **Public launch remains NO-GO** until the external, credential and owner items in section C are closed.

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

## B. Final verification (all on this tree, after the second pass)

| Requirement | Result | Evidence |
|---|---|---|
| PHPUnit, PHP 8.4.27 (target), SQLite | **PASS** | 1,726 passed, 5 MariaDB-only skips, 0 failed |
| PHPUnit, PHP 8.4.27, MariaDB 10.11.19, `mysql` driver | **PASS** | 1,731 passed, 0 skipped, 0 failed |
| Playwright desktop + mobile | **PASS** | 180 passed (incl. Alpine CSP and CSP-violation checks for Farmer and MAO pages), 18 intentional skips |
| Python (ML) | **PASS** | 71 passed |
| JavaScript | **PASS** | 26 passed |
| Deployment / packaging | **PASS** | 26 passed |
| Composer / npm (production and full) audits | **PASS** | 0 advisories / 0 / 0 (after the Alpine package change) |
| `pip-audit` | **PASS** | No known vulnerabilities after the certifi pin |
| Vite build | **PASS** | Built (picomatch mitigation check enforced) |
| PHP 8.4 compatibility of changed files | **PASS** | `php -l` 0 failures; `check-platform-reqs` OK |
| Model artifacts preserved | **PASS** | Rice `f3a54e12…b777c`, weather `bbd59810…d47`: unchanged |
| ML integration | **PASS** | 3,235/3,235 stored estimates equal the `.pkl` run directly. The service reports `rice, weather` loaded. |
| Scheduler / workers | **PASS (local)** | `schedule:list` includes the new jobs. 2 queue workers respawn after `queue:restart`. Health: queue, scheduler, Reverb OK. |
| External: Open-Meteo forecast and archive, PAGASA | **PASS** | HTTP 200 each; last scheduled fetch succeeded |
| Farmer yield gate | **PASS (production template)** | `withhold` in the template; accuracy flag false |
| Data preserved | **PASS** | Staging (read-only check): 877 users, 713 demo accounts, 1,081 farms; 7 migrations still pending |
| ML accuracy | **NOT VERIFIED** | No independent real-harvest evidence. Not claimed. |

## C. Still blocked — needs infrastructure, credentials, evidence or approval

| Blocker | Owner | Status |
|---|---|---|
| Target VPS (Ubuntu 24.04, PHP-FPM 8.4, MariaDB 10.11, Python 3.12, Node 22) and its acceptance | Owner / infrastructure | **FAIL — no host.** Unit files cannot be checked with `systemd-analyze verify` on macOS. |
| DNS and a real TLS certificate for `iclimate-lian.tech` | Owner | **FAIL — external.** The config is tested with a self-signed certificate only. |
| SMTP provider account and real delivery of a reset e-mail | Owner | **FAIL — credentials.** The transport is proven only against a local listener. |
| Off-host backup destination and safe keeping of the encryption key | Owner | **FAIL — external.** The tooling is ready; `iclimate:health` warns until it is set. |
| Groq API key | Owner | **FAIL — HTTP 401 `invalid_api_key`.** The assistant degrades safely without it. |
| WeatherAPI (optional second provider) | Owner decision | Not configured |
| 713 `@iclimate.test` demo accounts and seeded data | Owner / MAO | **Decision pending.** Nothing deleted. |
| Applying the 7 migrations to staging/production | Owner + DBA | **Approval pending.** Rehearsed successfully. |
| Independent ML validation on harvests not used for training | Researchers | **NOT VERIFIED.** Estimates stay gated. |
| Showing farmers the number (`label`) in production | Owner | The local `.env` uses `label` (approved for local use 2026-11-20). Production needs that approval recorded. |
| Shared-password lockdown (2 copied hashes locally) | Owner | On in the production template; local only |
| Commit, new immutable release candidate, tag | Owner | **Approval pending.** Nothing committed. |

## Operator notes

- The local Mac missed scheduled backups while asleep at 03:15. On the VPS the systemd timer covers this, and `iclimate:health` now raises it.
- `script-src` no longer allows `'unsafe-eval'`. New Alpine behaviour must be added as a registered component in `resources/js/alpine-components.js`: the CSP build ignores inline JavaScript in `x-` attributes.
- `'unsafe-inline'` and `https:` in `script-src` are ignored by browsers that support nonces; they only keep very old browsers working. Inline `style` attributes are still allowed (`style-src 'unsafe-inline'`).
- Rollback: `SECURITY_CSP_STRICT=false` restores the previous loose policy without a deploy.
- The nginx `listen … http2` deprecation warning is expected: Ubuntu 24.04 ships nginx 1.24, which does not have the standalone `http2` directive.
