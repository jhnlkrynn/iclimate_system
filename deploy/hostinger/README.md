# iClimate Hostinger VPS deployment kit

These files are templates for an Ubuntu LTS Hostinger KVM VPS. They are not executed by the repository and contain no credentials.

Assumptions:

- Application: `/var/www/iclimate_system`
- Verified current hostname: `iclimate-lian.tech` (confirm ownership before cutover)
- Runtime user: `www-data`
- Verified PHP-FPM socket: `/run/php/php8.4-fpm.sock`
- MariaDB is bound to localhost or a private interface only
- Python analytics listens on `127.0.0.1:8010`
- Reverb listens on `127.0.0.1:8080`; Nginx terminates TLS/WSS
- `systemd` timer is the only scheduler mechanism

## Safe installation order

1. Provision Ubuntu LTS, SSH keys, firewall, and a non-root deployment operator.
2. Install/verify PHP 8.3+ with required extensions, Composer, Node LTS, Python 3.11+, MariaDB client/server, and Nginx.
3. Create `/etc/iclimate/iclimate.env` with mode `0640` (`root:www-data`); copy values from `iclimate.env.example` only after approving secrets.
4. Create the dedicated backup account with the least-privilege SQL in `mariadb-backup-account.sql`.
5. Deploy the application artifact, install Composer/npm/Python dependencies, and set `storage/` and `bootstrap/cache/` ownership.
6. Install the systemd units and timer; run `systemctl daemon-reload` but do not enable/start until smoke-test approval.
7. Install the Nginx template with the real domain and PHP-FPM socket; obtain the certificate before enabling HTTPS redirects.
8. Run Laravel config/cache commands on the VPS only, then perform smoke tests.

Never run both `schedule:work` and the timer. Never put database passwords in unit files, shell history, Git, or command arguments.


## Phase 19 additions (exact steps; run on the VPS as the deployment operator)

Minimum VPS: Ubuntu 22.04/24.04 LTS KVM with root, 2 vCPU, **4 GB RAM** (8 GB recommended: PHP-FPM + MariaDB +
Python/scikit-learn + Reverb + queue), 40 GB disk. Shared or cloud web hosting is not enough: Reverb, the queue worker
and the ML service are long-running processes that need systemd.

1. Firewall: `ufw default deny incoming && ufw allow OpenSSH && ufw allow 80,443/tcp && ufw enable`. MariaDB (3306),
   Reverb (8080) and the ML service (8010) stay closed; Nginx proxies WSS to Reverb.
2. MariaDB: copy `mariadb-iclimate.cnf` to `/etc/mysql/mariadb.conf.d/60-iclimate.cnf`, create the app account at
   `'iclimate_app'@'127.0.0.1'` (SELECT, INSERT, UPDATE, DELETE only), restart MariaDB, check `ss -ltn | grep 3306` shows
   `127.0.0.1:3306` only. Migrations use the temporary account from `mariadb-migration-account.sql` (`phase-18-dba-handoff.md`).
3. Release: deploy the files listed by `scripts/deploy/build-release-manifest.sh` (compare its fingerprint), then
   `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`, and in `.venv-ml`
   `pip install -r python_scripts/requirements.txt`. Verify `sha256sum storage/models/rice_yield_model_final.pkl`.
4. Secrets: `/etc/iclimate/iclimate.env` mode 0640 owned by root:www-data, from `iclimate.env.example`. Never in Git,
   unit files or shell history.
5. Database: restore the approved backup (`restore-isolated-mariadb.sh` first, into a disposable database), then
   follow `VPS-RUNBOOK-RC2.md` / `phase-18-deployment-runbook.md` for the seven migrations. Then `php artisan config:cache route:cache view:cache`.
6. Services: `systemctl enable --now iclimate-python iclimate-reverb iclimate-queue iclimate-scheduler.timer`
   (never also `schedule:work`). Check: `php artisan iclimate:health` exits 0;
   `scripts/deploy/ml-activation-check.sh <model sha256>` exits 0.
7. Backups: enable `iclimate-backup.sh` on a daily timer; run one restore drill with `restore-isolated-mariadb.sh`.
8. HTTPS last: obtain the certificate, enable the HTTPS redirect, confirm `SESSION_SECURE_COOKIE=true` and that the
   site sets `Secure; HttpOnly` cookies.
