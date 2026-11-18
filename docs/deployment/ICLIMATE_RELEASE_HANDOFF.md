# iClimate release handoff — RC 2026.11.18-rc1

**For:** the project owner (approvals), the MariaDB administrator (DBA), the deployment operator, and MAO Staff
(account recovery). This is the one document for the rollout; the files it names hold the exact commands.

Nothing in this document has been run on the active database, the live services or a production server. Every step
marked **APPROVAL** waits for the project owner's explicit go-ahead.

---

## 1. Release version and fingerprint

| Item | Value |
|---|---|
| Version | `2026.11.18-rc1` = `main` @ `c34c6e5b` + the working tree listed in the manifest (not yet committed) |
| Manifest | `scripts/deploy/build-release-manifest.sh` → `storage/app/backups/preflight-2026-11-18/release-2026.11.18-rc1.manifest` |
| Manifest fingerprint | in `release-2026.11.18-rc1.manifest.sha256` next to the manifest (sha256 of the manifest's file lines; kept outside this document because this document is itself in the manifest) |
| Contents | every tracked and untracked source file with its sha256, the removed files (including the 26 IT files), the files to untrack |
| Excluded | 32 research-only files (`storage/models/experiments/`, `storage/datasets/external/`; not read at runtime); secrets, backups, logs, caches, `vendor/`, `node_modules/` |
| Git index | `storage/framework/PsySH/psysh_history` is staged for removal from tracking (the local file is kept, now ignored) |
| Migration files | 6, sha256 in `storage/app/backups/preflight-2026-11-18/migration-files.sha256` |
| Deployed model | `storage/models/rice_yield_model_final.pkl` sha256 `f3a54e12ecf85e66340475e2c49ce5c15d9c58efd82213435015a275734b777c` (unchanged) |
| Roles | Farmer and MAO Staff only (MAO Head is a flag on an MAO account). The IT role, routes and pages are removed; former IT accounts cannot sign in |

Check before using the release: re-run the manifest script and compare the fingerprint.

## 2. Account-security status and remaining risks

867 of 877 accounts (0 deleted) have a password that proves nothing about who is signing in:

| Accounts | Role | Published default | Hash copied onto other accounts | Working email | Recovery path |
|---|---|---|---|---|---|
| 713 | Farmer | yes | yes (one hash shared by all 713) | no (`@iclimate.test`) | MAO code |
| 144 | Farmer | no | yes (a second shared hash) | no (`@iclimate.local`) | MAO code |
| 8 | Farmer | yes | no | yes | email link |
| 1 | MAO Staff | yes | no | yes | email link (or the MAO Head resets it) |
| 1 | former IT | yes | no | — | none: cannot sign in |

Totals: 723 on the published default, 857 on a copied hash (713 have both). **857 Farmers need a MAO code.**

What the code already enforces (tests: `DefaultPasswordLockdownTest`, 18 tests; all pass on SQLite and MariaDB):

- A shared password never opens a session or sets a new password, on the web, the mobile API, an existing session or
  Reverb. The person is sent to recovery: an email link (60 minutes) or a code from MAO Staff.
- **Network guard:** even with `ICLIMATE_PASSWORD_LOCKDOWN=false`, a shared password is refused from any address
  other than this machine (127.0.0.1). A forged `X-Forwarded-For` header does not count.
- MAO codes: 10 characters, one use, 60 minutes, stored only as a hash, never logged. Issuing one needs a stated
  identity check and a confirmation box, ends the account's sessions, is audit-logged, and tells the owner in-app.
  Only Farmer accounts; MAO Staff accounts are reset by the MAO Head.

Remaining risks:

| Risk | Status |
|---|---|
| On **this Mac** (web, Reverb and ML listen on 127.0.0.1 only; no tunnels), anyone at the keyboard can still sign in with a shared password while the switch is off | Contained to this machine; ends with step 6 |
| **On any server reached over the network, the 857 + 10 accounts cannot sign in until they recover**, whatever the switch says | By design: the recovery rollout (step 6) must be ready on go-live day |
| MariaDB listens on all interfaces (`*:3306`) and the macOS firewall is off; root may have no password (2026-11-14 finding, not rechecked) | **Open:** step 7, DBA |
| A MAO Staff member could misuse a code | Mitigated: identity check recorded, owner notified, audit log names the staff member |
| The old mobile app shows only the 409 message (it already tells the Farmer what to do) | Mobile release: `docs/deployment/mobile-password-change-contract.md` |

## 3. DBA: temporary migration account

| | |
|---|---|
| Who | DBA (MariaDB admin credentials) |
| Instructions | `deploy/hostinger/phase-18-dba-handoff.md`, SQL template `deploy/hostinger/mariadb-migration-account.sql` |
| Grants | on the one database only: SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES, DROP, LOCK TABLES. No global privileges, no GRANT OPTION. `iclimate_app` stays SELECT, INSERT, UPDATE, DELETE |
| Verified | 2026-11-18 on a throwaway MariaDB 10.4.28 loaded with the real-data copy, accounts created **from these templates**: the app account was refused ALTER; the migration account applied the six migrations, rolled them back and re-applied them, and ran the restore drill |
| Expected result | `SHOW GRANTS` shows one database-scoped line |
| Safe recovery | `DROP USER 'iclimate_migrator'@'127.0.0.1';` |

## 4. Six-migration deployment (APPROVAL)

| | |
|---|---|
| Who | Operator, with the temporary account (typed at hidden prompts) |
| Procedure | `deploy/hostinger/phase-18-deployment-runbook.md`: A (gate, read-only), B (maintenance, backup, snapshot, migrate, compare, up, `queue:restart`), C (recovery) |
| Prerequisites | step 3; `shasum -a 256 -c …/migration-files.sha256` → 6 × OK; `bootstrap/cache/config.php` absent |
| Expected result | 0 pending; `two-role-migration-check.sh compare` all OK; `php artisan iclimate:health` exit 0 |
| Data affected | Active schema (8 new tables, new columns). Existing rows unchanged (checked by the compare script) |
| Safe recovery | Still in maintenance: restore the backup (runbook C, verified). After real reviews exist: fix forward, do not roll back |

## 5. MAO Head assignment (APPROVAL)

| | |
|---|---|
| Who | Owner chooses the account; operator runs it |
| Command | `php artisan iclimate:assign-mao-head <email>` (dry run), then the same with `--apply` |
| Prerequisites | step 4; the account is an active MAO Staff account |
| Expected result | Exactly one MAO Head; that account opens `/users`; audit-log entry "Changed MAO Head assignment" |
| Data affected | One `is_mao_head` flag (and the previous head's, if any) |
| Safe recovery | Run it again with the previous head's email |

## 6. Password recovery rollout and lockdown activation (APPROVAL)

| Step | Who | What | Expected result |
|---|---|---|---|
| 6.1 | Owner | Mobile release per `docs/deployment/mobile-password-change-contract.md`, or accept the website-only path | The contract's 4 acceptance checks |
| 6.2 | MAO Head | Train MAO Staff: Account Requests → farmer → **Account recovery**. Check the person first (in person with ID, call back the registered number, or the association chairman in person), tick the box, give the code, send them to `/account/recover` | Each staff member issues one code to a test Farmer and the test Farmer recovers |
| 6.3 | MAO Head | Tell Farmers through associations and barangay announcements: "Bring an ID to the MAO office (or wait for our call) to set a new iClimate password" | — |
| 6.4 | Operator | Set `ICLIMATE_PASSWORD_LOCKDOWN=true`; optionally `php artisan iclimate:flag-default-passwords --apply` (flags the 867 and ends their sessions) | A shared-password sign-in lands on recovery; `iclimate:health` shows `OK accounts` |
| 6.5 | MAO Staff | Recover Farmers barangay by barangay. A code lasts 60 minutes, so give it while the person is present or on the call | Count of shared-password accounts falls (dry run of the flag command) |

Capacity: 857 Farmers at about 3 minutes each is about 43 staff-hours. Plan 2–3 weeks with a daily desk.

Safe recovery: set the switch back to `false` (flagged accounts stay flagged, which is safe). On a networked server the
network guard keeps shared passwords refused either way.

## 7. MariaDB hardening (APPROVAL, DBA)

| | |
|---|---|
| Who | DBA (root) |
| File | `deploy/hostinger/mariadb-iclimate.cnf` (`bind-address = 127.0.0.1`, `skip-name-resolve`) |
| Prerequisites | As admin: `SHOW PROCESSLIST` and `SELECT user, host FROM mysql.user` show no client from another machine, and every app account is granted to `'127.0.0.1'`; a maintenance window |
| Also | Set a root password; turn on the macOS firewall on the Mac; rotate the 2.1 GB MariaDB error log |
| Verified | Throwaway MariaDB 10.4.28 with this file: listens on 127.0.0.1 only, LAN connection refused, Laravel and both accounts connect |
| Expected result | `netstat -an \| grep 3306` → `127.0.0.1.3306` only; `iclimate:health` OK |
| Safe recovery | Remove the lines, restart MariaDB |

## 8. ML-only restart (APPROVAL)

| | |
|---|---|
| Who | Operator |
| Before | `curl -s 127.0.0.1:8010/model-info` → note `sha256`. Find the current process (do not reuse old notes): `lsof -nP -iTCP:8010 -sTCP:LISTEN` (on 2026-11-18: pid 2702, parent 2665 = `local-service-supervisor.sh ml`) |
| Command | `scripts/deploy/ml-switch-code.sh new --restart`: checks the saved copies' checksums, makes sure the process belongs to the ML supervisor, ends only that child, waits for the relaunch |
| Verify | `scripts/deploy/ml-activation-check.sh <sha256>` → exit 0 (health, model-info checksum, production input accepted, impossible inputs 422, `/metrics` path traversal 422, Laravel `iclimate:health`). Proven on a temporary instance: all 14 checks pass; same 4.13 t/ha as the running service |
| Data affected | None. About 5 seconds without ML predictions |
| Rollback | `scripts/deploy/ml-switch-code.sh previous --restart` restores **the code the service runs today**, not the same files again. The saved previous version was run side by side with the live service: same answers on 7 probes and the same model checksum |

Yield estimates stay withheld (`ICLIMATE_YIELD_GATE=withhold`). **ACCURACY NOT VERIFIED.**

## 9. VPS provisioning (APPROVAL, external)

| | |
|---|---|
| Who | Owner (purchase, domain) and operator |
| Requirement | KVM VPS with root and systemd: Ubuntu 22.04/24.04 LTS, 2 vCPU, 4 GB RAM minimum (8 GB recommended), 40 GB disk. Shared or cloud web hosting cannot run Reverb, the queue worker or the ML service |
| Procedure | `deploy/hostinger/README.md`, "Phase 19 additions" steps 1–8 (firewall, MariaDB, release, secrets, database, services, backups, HTTPS) |
| Software | PHP 8.4 (FPM), Composer, Node LTS (build only), Python 3.12 with `python_scripts/requirements.txt` (scikit-learn 1.6.1 — the model needs it), MariaDB 10.4+, Nginx, certbot |
| Expected result | `deploy/hostinger/verify-production.sh` passes; `iclimate:health` exit 0 |
| Status | **Not verified**: no server has been provisioned |

## 10. Post-deployment verification

| Check | Command / action | Expected |
|---|---|---|
| Health | `php artisan iclimate:health` | exit 0, every line OK |
| ML | `scripts/deploy/ml-activation-check.sh <sha256>` | exit 0 |
| Schema | `php artisan migrate:status \| grep -c Pending` | 0 |
| Accounts | Sign in as a test Farmer, a test MAO Staff and the MAO Head; one shared-password test account | Dashboards open; the shared one lands on `/account/recover` |
| Account Requests | Register a test Farmer → approve → sign in | Works; one notification each |
| Association reporting | Assign a test chairman → submit → review | Works; reminder job `associations:send-reminders` runs |
| Real time | Open a page as the test Farmer, act as MAO | Update arrives without reload |
| Backups | `iclimate:backup-database --no-prune`, then restore into a disposable database | Row counts match |

## 11. Recovery and rollback

| Situation | Action |
|---|---|
| A migration fails during maintenance | Runbook C: restore the backup taken in the window (verified as the migration account) |
| Problems after migration, before any real review | `migrate:rollback --step=6` as the migration account (verified) |
| Problems after real records exist | Fix forward; do not roll back (it would remove review history) |
| Lockdown causes trouble | Switch back to `false`; network sign-ins with shared passwords stay refused |
| ML problem after the restart | `scripts/deploy/ml-switch-code.sh previous --restart` |
| MariaDB hardening breaks a client | Remove the config lines, restart MariaDB |
| Wrong MAO Head | Re-run the command with the right email |

## 12. Approval gates (in order)

| # | Gate | Needs | Affects live |
|---|---|---|---|
| 1 | Commit the release candidate (and the staged PsySH untrack) | Owner | No |
| 2 | Create the temporary migration account | Owner + DBA | DB accounts only |
| 3 | Apply the six migrations | Owner | **Schema** |
| 4 | Assign the MAO Head (which account) | Owner | One account flag |
| 5 | Restart the ML service on the new code | Owner | ML service, about 5 s |
| 6 | MariaDB hardening + root password + firewall | Owner + DBA | **MariaDB restart** |
| 7 | Mobile release or website-only recovery | Owner + mobile developer | No |
| 8 | Turn on the lockdown and start the recovery rollout | Owner + MAO Head | **867 accounts** |
| 9 | Provision and deploy the VPS | Owner | New host |
| 10 | Drop the temporary migration account | DBA | DB accounts only |
| — | Show farm-level yield estimates again | Independent harvest observations and a model re-evaluation | — |
