# Private VPS installation checklist: RC2-R2 (checkpoint 2 in `CHECKPOINTS.md`)

**Target:** a fresh Ubuntu 24.04 LTS KVM VPS. The detailed steps are in `deploy/hostinger/VPS-RUNBOOK-RC2.md` (RC1 runbook for provisioning, database accounts and HTTPS).

**Status:** no VPS exists yet. Every box below is open. Record each result: command, output, time.

## A. Host and packages (`provision-ubuntu-24.04.sh`; refuses a host that already runs these services)

- [ ] Owner authorises the VPS, the admin IP and the data transfer. Set and test the operator's sudo password **before** root SSH is disabled.
- [ ] Firewall: ufw denies incoming except OpenSSH, 80, 443. fail2ban and unattended-upgrades are active.
- [ ] **PHP 8.4:** `php -v` shows 8.4.x (≥ 8.4.1). `php -m` lists pdo_mysql, mbstring, openssl, intl, bcmath, gd, zip, xml, curl, opcache. php8.4-fpm is enabled.
- [ ] **MariaDB 10.11** (distro package): `mariadb --version` shows 10.11.x. `60-iclimate.cnf` sets bind 127.0.0.1 and skip-name-resolve. Run `mariadb-secure-installation`.
- [ ] **Node 22** (NodeSource `node_22.x`): `node -v` shows v22.x.
- [ ] **Python 3.12:** `python3 -V`; python3-venv and build-essential installed.
- [ ] nginx and certbot installed. `bash deploy/hostinger/install-check.sh` exits 0.

## B. Database (`mariadb-migration-account.sql`, `mariadb-backup-account.sql`, `phase-18-dba-handoff.md`)

- [ ] **Database:** utf8mb4 / utf8mb4_unicode_ci.
- [ ] **Accounts:**
  - [ ] **runtime:** SELECT, INSERT, UPDATE, DELETE only;
  - [ ] **migration:** DDL, temporary, locked after use;
  - [ ] **backup:** SELECT, SHOW VIEW, TRIGGER, EVENT;
  - [ ] none of them has global privileges or GRANT OPTION.
- [ ] **Env:** `DB_CONNECTION=mysql`. This is Laravel's `mysql` driver against MariaDB. **Not** `mariadb`: that driver skips 9 MySQL-guarded migration steps. `DB_TIMEZONE=+08:00`.
- [ ] **Data transfer:**
  1. Take a protected logical backup of the current production database (MariaDB 10.4) with `iclimate-backup.sh`.
  2. Copy it over with its `.sha256`.
  3. Restore it into a disposable `iclimate_restore_<timestamp>` database with `restore-isolated-mariadb.sh`.
  4. Snapshot both sides with `CHECK_RELEASE=rc2 scripts/deploy/two-role-migration-check.sh snapshot`. Row counts, checksums and foreign keys must match, as they did in the local rehearsal.
- [ ] Rehearse the **7** pending migrations on the disposable copy (`compare` = all OK). Do **not** migrate production at this checkpoint; that is checkpoint 3.

## C. Configuration (`/etc/iclimate/iclimate.env`, root:www-data 640, directory 750)

- [ ] **Env file contents:**
  - [ ] `APP_ENV=production`, `APP_DEBUG=false`;
  - [ ] a generated `APP_KEY`;
  - [ ] HTTPS `APP_URL`, `SESSION_SECURE_COOKIE=true`;
  - [ ] `ICLIMATE_PASSWORD_LOCKDOWN=true`;
  - [ ] **`ICLIMATE_YIELD_GATE=withhold`**, **`ICLIMATE_YIELD_ACCURACY_VERIFIED=false`**;
  - [ ] `DB_CONNECTION=mysql`;
  - [ ] each key defined **once**;
  - [ ] no `${…}` substitutions.
- [ ] Reverb: the backend publishes to 127.0.0.1:8080 over http. The browser uses the public hostname:443 over https. `VITE_REVERB_APP_KEY` = `REVERB_APP_KEY`.
- [ ] Real SMTP credentials, plus `FARMING_AI_API_URL=http://127.0.0.1:8010`.

## D. Code (`deploy-release.sh`, after checkpoint 1)

- [ ] Copy the RC2 bundle and `source.manifest`, then run:
  ```sh
  TAG=v2026.11.18-rc2 EXPECTED_COMMIT=<APPROVED-COMMIT.txt> EXPECTED_FINGERPRINT=04c7db9fedf0db9c077521adfebae8621291b1ddcf5e0e371a72a966dcb2d29d \
  BUNDLE=… MANIFEST=… VERIFY_ONLY=1 bash deploy-release.sh
  ```
  It must pass the bundle, tag, commit, fingerprint, all-1,504-files and **model sha256 `f3a54e12…b777c`** checks. Then run it again without `VERIFY_ONLY`. It does not migrate or start services.
- [ ] The installer's env checks pass. These are new in RC2-R2: duplicate keys refused, `mysql` driver required, accuracy flag must be false.
- [ ] **Build steps run by the installer:**
  - `composer install --no-dev --optimize-autoloader` from the lock file;
  - `npm ci && npm run build` on **Node 22**. `postinstall` applies the `picomatch` mitigation, and `build` refuses to run without it.
  - `.venv-ml` gets `pip install -r python_scripts/requirements.txt` (26 pinned packages).
  
  Confirm each succeeded in its output.
- [ ] **Outstanding Node 22 verification:** run `npm run test:js` in the installed tree (26 tests) and record the result.
- [ ] **Outstanding Python dependency audit:**
  1. In a separate throwaway venv (not `.venv-ml`), run `pip install pip-audit && pip-audit -r python_scripts/requirements.txt`.
  2. Run the 69 Python tests with `cd python_scripts && ../.venv-ml/bin/python -m pytest -q`. pytest 9.1.1 is in the pinned requirements.

  Any unreviewed advisory blocks checkpoint 3.
- [ ] `php artisan config:cache route:cache view:cache`. Check that PHP-FPM can read the env file and write `storage/` and `bootstrap/cache`.

## E. Services (systemd units in `deploy/hostinger/`)

- [ ] **`iclimate-python.service`** (uvicorn on 127.0.0.1:8010):
  - `/health` lists `models_loaded` rice and weather;
  - `scripts/deploy/ml-activation-check.sh f3a54e12ecf85e66340475e2c49ce5c15d9c58efd82213435015a275734b777c` passes.
- [ ] **`iclimate-queue.service`:** `queue:work database --queue=analytics,default,broadcasts`. Test one real queued notification and one failed-job retry.
- [ ] **`iclimate-scheduler.timer`:** runs `schedule:run` every minute under flock. Reminders, the weather fetch, and the daily `iclimate:backup-database` plus `verify` must run.
- [ ] **`iclimate-reverb.service`** (127.0.0.1:8080): browser subscribe and reconnect through TLS on the real hostname.
- [ ] Run `systemd-analyze verify` on each unit. Start the services only after authorisation. `php artisan iclimate:health` must show every check OK.

## F. TLS and nginx (`nginx-iclimate.conf`)

- [ ] `nginx -t` passes. The private allowlist include is kept, the distro default site is removed, and the ACME challenge path is public.
- [ ] `certbot --nginx` issues the certificate, and `certbot renew --dry-run` passes. HTTP redirects to HTTPS. From an off-host, non-allowlisted IP, the private site is denied.

## G. Backups

- [ ] **Scheduled:** `iclimate:backup-database` runs daily, and the newest archive is verified 10 minutes later. Retention cleanup is active. Backup files are owned by www-data with mode 700.
- [ ] **Manual protected backup** (`iclimate-backup.sh`) before every migration. The checksum sidecar refers to the final file name.
- [ ] **Restore drill:** restore the newest scheduled backup with `restore-isolated-mariadb.sh` and compare snapshots.
- [ ] **Off-host copy** of the backups. Not provided by the repository: owner action, and a **public-launch blocker**.

## H. Private acceptance (synthetic accounts first)

- [ ] **Accounts and roles:**
  - [ ] Farmer registration, Pending, approve/reject and resubmission;
  - [ ] ordinary MAO and MAO Head separation;
  - [ ] chairman report versions, private documents and review races;
  - [ ] recovery by email (real SMTP delivery) and by MAO code.
- [ ] **Yield:**
  - [ ] the Farmer yield card shows **no number**;
  - [ ] for months without observed weather it shows "Not enough weather records yet for an estimate.";
  - [ ] the stored frozen-input history stays hidden.
- [ ] Layouts at 1280, 768 and 375 px.
