# iClimate RC2 Private Staging & VPS Deployment Runbook

**Branch:** `feature/2026-11-20-yield-monitoring-security` (base commit `0cadbc74`)  
**Target OS / Stack:** Ubuntu 24.04 LTS KVM VPS, PHP-FPM 8.4, MariaDB 10.11, Python 3.12 (`scikit-learn 1.6.1`), Node 22, Nginx, systemd  
**Production Yield Gate:** `ICLIMATE_YIELD_GATE=withhold`, `ICLIMATE_YIELD_ACCURACY_VERIFIED=false` (keep withheld until independent scientific validation and owner approval)  
**Launch Status:** **NO-GO for public launch** until all external infrastructure, credentials, and post-deploy acceptance checks below are completed and signed off.

---

## 1. Verification Status Summary

| Component | Local Verification Status | Ubuntu 24.04 VPS Status |
|---|---|---|
| Laravel PHPUnit (PHP 8.4.27, SQLite) | **PASS** (1,726 passed, 5 MariaDB-only skips, 0 failed) | **NOT VERIFIED** |
| Laravel PHPUnit (PHP 8.4.27, MariaDB 10.11.19) | **PASS** (54 passed, 363 assertions, 0 skipped, 0 failed) | **NOT VERIFIED** |
| Playwright Browser Suite (desktop + mobile) | **PASS** (180 passed, 18 intentional viewport skips, 0 failed) | **NOT VERIFIED** |
| Python ML & Deployment Tooling (`pytest`) | **PASS** (97 passed, 0 failed) | **NOT VERIFIED** |
| JavaScript & Vite Build (`npm run test:js && npm run build`) | **PASS** (26 passed; `picomatch` mitigation verified) | **NOT VERIFIED** |
| Composer / npm Security Audits | **PASS** (0 advisories / 0 vulnerabilities) | — |
| 7 Pending Migrations + Rollback + Encrypted Restore | **PASS** on disposable MariaDB 10.11.19 (78/78 tables `OK`, 877 users, 713 demo accounts, 1,081 farms preserved) | **NOT VERIFIED** |
| Systemd Units (`systemd-analyze verify`) | **NOT VERIFIED** (macOS host) | **NOT VERIFIED** (requires Ubuntu 24.04) |
| DNS + Live Let's Encrypt TLS (`iclimate-lian.tech`) | **NOT VERIFIED** (external) | **NOT VERIFIED** |
| Live SMTP Delivery (reset + health alert) | **NOT VERIFIED** (external credentials) | **NOT VERIFIED** |
| Off-Host Encrypted Backup Target (`BACKUP_OFFSITE_TARGET`) | **NOT VERIFIED** (external destination) | **NOT VERIFIED** |

---

## 2. Step-by-Step Ubuntu 24.04 VPS Staging Deployment Procedure

### Step 1 — Generate Approved Release Bundle & Manifest (On Mac, After Owner Approval)
> Do **not** tag, push, or deploy until the owner reviews and approves commit `0cadbc74`.

```sh
# 1. Create annotated release tag (only after approval)
git tag -a v2026.11.20-rc2 -m "iClimate RC2 release candidate"

# 2. Build deterministic release manifest and bundle
OUT=/tmp/iclimate-rc2-release
mkdir -p "$OUT"
python3 scripts/deploy/prepare-release.py create --output "$OUT" --version 2026.11.20-rc2
git bundle create "$OUT/iclimate-v2026.11.20-rc2.bundle" v2026.11.20-rc2
```
**Success criteria:** `prepare-release.py verify-tree` and `verify-archive` report `1529` files verified, `git bundle verify` exits `0`.

---

### Step 2 — Provision Fresh Ubuntu 24.04 VPS (As `root`)
Copy `deploy/hostinger/` to the VPS, then run once on a clean Ubuntu 24.04 LTS image:

```sh
OPERATOR=deploy \
OPERATOR_PUBKEY="ssh-ed25519 AAAA... operator" \
ADMIN_IP=<owner-or-operator-ipv4> \
bash deploy/hostinger/provision-ubuntu-24.04.sh
```

Set the operator's sudo password and enable SSH hardening after verifying key login from a **second** terminal:
```sh
passwd deploy
# From second terminal as deploy: sudo -v
sudo mv /etc/ssh/sshd_config.d/60-iclimate.conf.pending /etc/ssh/sshd_config.d/60-iclimate.conf
sudo sshd -t && sudo systemctl reload ssh
```
**Success criteria:** `ss -ltn | grep 3306` shows `127.0.0.1:3306` only; `ufw status` shows only `OpenSSH`, `80/tcp`, `443/tcp`; `php -v` >= `8.4.1`; `node -v` >= `22.12`.

---

### Step 3 — Create MariaDB Accounts & Empty Target Database (DBA)
```sh
sudo mariadb-secure-installation
```
Using `MYSQL_HISTFILE=/dev/null mariadb -h127.0.0.1 -u root -p`:
1. Create database `iclimate` (`CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`).
2. Create least-privilege runtime account `'iclimate_app'@'127.0.0.1'` (`SELECT, INSERT, UPDATE, DELETE` on `iclimate.*`).
3. Create backup account from `deploy/hostinger/mariadb-backup-account.sql`.
4. Create temporary migration account from `deploy/hostinger/mariadb-migration-account.sql` (scoped to `iclimate.*`).

**Success criteria:** `SELECT user, host FROM mysql.user` shows all app/backup/migrator accounts bound to `'127.0.0.1'`.

---

### Step 4 — Configure `/etc/iclimate/iclimate.env` & Off-Host Backup Key
Install `/etc/iclimate/iclimate.env` owned by `root:www-data` with mode `0640` (directory `/etc/iclimate` mode `0750`), starting from `deploy/hostinger/iclimate.env.example`:
- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://iclimate-lian.tech`
- `APP_KEY=` generated via `php artisan key:generate --show`
- `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_TIMEZONE=+08:00`
- `ICLIMATE_YIELD_GATE=withhold`, `ICLIMATE_YIELD_ACCURACY_VERIFIED=false`
- `ICLIMATE_PASSWORD_LOCKDOWN=true`, `SECURITY_CSP_STRICT=true`, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`
- Real `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT=587`, `MAIL_SCHEME=smtp`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `HEALTH_ALERT_EMAIL`
- Real `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, and matching `VITE_REVERB_APP_KEY`

Generate the backup encryption key and store a copy off-server (password manager):
```sh
sudo sh -c 'umask 077 && openssl rand -base64 48 > /etc/iclimate/backup.key'
sudo chown www-data:www-data /etc/iclimate/backup.key
sudo chmod 600 /etc/iclimate/backup.key
```
Set `BACKUP_OFFSITE_TARGET` (e.g. `backup@offsite-host:/srv/iclimate-backups` or mounted off-host path) and `BACKUP_ENCRYPTION_KEY_FILE=/etc/iclimate/backup.key`.

**Success criteria:** Zero `REPLACE_WITH_*` placeholders, zero `${...}` substitutions, file mode `0640` (`root:www-data`), key mode `0600` (`www-data:www-data`).

---

### Step 5 — Install Release Payload (`deploy-release.sh`)
Copy bundle and manifest to the VPS, verify first with `VERIFY_ONLY=1`, then install:
```sh
TAG=v2026.11.20-rc2 \
EXPECTED_COMMIT=<approved-commit> \
EXPECTED_FINGERPRINT=<approved-manifest-sha256> \
BUNDLE=~/iclimate-v2026.11.20-rc2.bundle \
MANIFEST=~/source.manifest \
VERIFY_ONLY=1 bash deploy/hostinger/deploy-release.sh

# Repeat without VERIFY_ONLY=1 to install dependencies, build assets, and set ACLs:
TAG=v2026.11.20-rc2 \
EXPECTED_COMMIT=<approved-commit> \
EXPECTED_FINGERPRINT=<approved-manifest-sha256> \
BUNDLE=~/iclimate-v2026.11.20-rc2.bundle \
MANIFEST=~/source.manifest \
bash deploy/hostinger/deploy-release.sh
```
**Success criteria:** `deploy-release.sh` exits `0`, verifies `rice_yield_model_final.pkl` SHA-256 `f3a54e12ecf85e66340475e2c49ce5c15d9c58efd82213435015a275734b777c`, builds Vite assets, and verifies `scikit-learn 1.6.1`.

---

### Step 6 — Database Transfer, 7-Migration Execution & Rollback Safety
1. Transfer the approved encrypted staging dump to the VPS, verify `.sha256`, and rehearse into a **disposable** database first:
   ```sh
   DUMP_FILE=~/iclimate-incoming/<backup>.sql \
   MYSQL_HOST=127.0.0.1 MYSQL_PORT=3306 MYSQL_ADMIN_USER=root MYSQL_ADMIN_PASSWORD="<root-pass>" \
   RESTORE_DATABASE="iclimate_restore_$(date +%Y%m%d_%H%M%S)" \
   bash deploy/hostinger/restore-isolated-mariadb.sh
   ```
2. Load the verified dump into `iclimate`, then run the preflight snapshot and migration check (`CHECK_RELEASE=rc2`):
   ```sh
   cd /var/www/iclimate_system
   scripts/deploy/two-role-migration-check.sh snapshot /tmp/mig-before
   DB_USERNAME="$MIG_USER" DB_PASSWORD="$MIG_PASS" php artisan migrate --pretend --force
   ```
3. Put the app in maintenance mode, run all **7** migrations, and compare snapshots:
   ```sh
   php artisan down --retry=60
   DB_USERNAME="$MIG_USER" DB_PASSWORD="$MIG_PASS" php artisan migrate --force
   php artisan migrate:status --no-ansi | grep -c Pending   # expect 0
   scripts/deploy/two-role-migration-check.sh snapshot /tmp/mig-after
   CHECK_RELEASE=rc2 scripts/deploy/two-role-migration-check.sh compare /tmp/mig-before /tmp/mig-after
   ```
4. Cache configuration/routes/views and bring the app up:
   ```sh
   sudo -u www-data php artisan config:cache
   sudo -u www-data php artisan route:cache
   sudo -u www-data php artisan view:cache
   php artisan up
   ```
5. **Rollback instructions:**
   - **Before `php artisan up` (or if `migrate` / `compare` fails):** Restore the pre-migration `.sql` dump (`shasum -a 256 -c` verified), re-run `two-role-migration-check.sh` against `/tmp/mig-before`, then `php artisan up`.
   - **After `php artisan up` once real submissions/reviews exist:** Do **not** run `migrate:rollback` (`2026_11_18_000001` blocks dropping populated submission history) or restore over live data; fix forward.

**Success criteria:** `compare` prints `OK` on all 7 integrity lines; `877` users, `713` `@iclimate.test` accounts, `1,081` farms preserved; `0` pending migrations.

---

### Step 7 — Install & Verify Systemd Services, ML, Backups & Monitoring
```sh
sudo install -m 644 deploy/hostinger/iclimate-*.service deploy/hostinger/iclimate-*.timer /etc/systemd/system/
sudo systemd-analyze verify /etc/systemd/system/iclimate-*.service /etc/systemd/system/iclimate-*.timer
sudo systemctl daemon-reload
sudo systemctl enable --now iclimate-python iclimate-reverb iclimate-queue iclimate-scheduler.timer
```
Warm archive & forecast inputs, verify off-host encrypted backup/restore, and test health alerts:
```sh
cd /var/www/iclimate_system
sudo -u www-data php artisan iclimate:fetch-climate-archive
sudo -u www-data php artisan iclimate:refresh-predictions
sudo -u www-data php artisan queue:restart
sudo -u www-data php artisan iclimate:warm-farmer-yields
sudo -u www-data php artisan iclimate:backup-database
sudo -u www-data php artisan iclimate:backup-offsite
sudo -u www-data php artisan iclimate:health --log
sudo -u www-data bash deploy/hostinger/verify-production.sh
```
Test decrypting and restoring the newest off-host `.enc` copy into a disposable database:
```sh
openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 -md sha256 \
  -in <file>.enc -out /tmp/offsite-restore-test.sql -pass file:/etc/iclimate/backup.key
# Compare SHA-256 with <file>.enc.sha256, then run restore-isolated-mariadb.sh
```
**Success criteria:** `systemd-analyze verify` exits `0`; `php artisan iclimate:health` exits `0` (`database`, `ml-service`, `reverb`, `queue`, `scheduler`, `backup`, `backup-offsite`, `yield-inputs`, `disk` all `OK`); `ml-activation-check.sh f3a54e12ecf85e66340475e2c49ce5c15d9c58efd82213435015a275734b777c` exits `0`; one real health-alert email arrives at `HEALTH_ALERT_EMAIL`.

---

### Step 8 — DNS & Private HTTPS / TLS Setup
1. Point `A` records for `iclimate-lian.tech` and `www.iclimate-lian.tech` to the VPS IPv4 and confirm `dig +short iclimate-lian.tech`.
2. Enable temporary HTTP-only ACME vhost (`/var/www/html/.well-known/acme-challenge/`), issue certificate:
   ```sh
   sudo certbot certonly --webroot -w /var/www/html -d iclimate-lian.tech -d www.iclimate-lian.tech
   ```
3. Install `deploy/hostinger/nginx-iclimate.conf` (keeping `include snippets/iclimate-private.conf;` in the `443` server block), remove distro `default` site, and reload:
   ```sh
   sudo nginx -t && sudo systemctl reload nginx
   ```
**Success criteria:** From `ADMIN_IP`, `https://iclimate-lian.tech` loads with TLS 1.2/1.3, HTTP→HTTPS 301 works, `/.env` returns `403`, cookies have `Secure; HttpOnly; SameSite=Lax`, and an unapproved external IP receives `403`.

---

### Step 9 — End-to-End Private Acceptance Tests (From `ADMIN_IP`)
Before removing `snippets/iclimate-private.conf`, verify on the live VPS:
1. **Authentication & Role Separation:**
   - Farmer login/logout, Pending & Rejected denial messages, shared-password lockdown (`409` / forced password change), CSRF `419` enforcement.
   - Ordinary MAO Staff: approve/reject Farmer Account Requests, issue one-time recovery code, manage reporting periods & review association submissions, confirm `/users` returns `403`.
   - MAO Head (`php artisan iclimate:assign-mao-head <email> --apply`): `/users` account management works; retired IT accounts cannot authenticate.
   - Association Chairman (approved Farmer with assignment): submit versioned report + private supporting document, handle revision request, confirm private download works for authorized roles and denies unrelated Farmers (`403`).
2. **Dashboards, ML, APIs, & Realtime:**
   - Farmer dashboard loads with `ICLIMATE_YIELD_GATE=withhold` (no unvalidated yield number displayed), Heat Map & Farm Calendar load cleanly, mobile (`375px`) and desktop (`1280px`) have zero CSP/console errors.
   - Reverb WSS (`wss://iclimate-lian.tech/app/`) subscribes to private channels and delivers live notification badge updates.
   - SMTP password-reset email actually delivers to a real inbox and completes reset.
3. **Final Lockdown Before Public Launch:**
   - Lock/drop temporary `iclimate_migrator` account.
   - Decide & execute policy on the `713` `@iclimate.test` demo accounts (owner/MAO decision).
   - Remove `include snippets/iclimate-private.conf;` and run `sudo nginx -t && sudo systemctl reload nginx` only after explicit owner sign-off.
