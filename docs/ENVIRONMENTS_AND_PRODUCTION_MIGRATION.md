# iClimate environments and the production database migration

> **Production postponed (2026-11-10).** The Hostinger subscription has not been renewed. iClimate runs only on the
> Mac (`bash scripts/start-iclimate-local.sh start`). Do **not** expose the Mac's database or point
> `iclimate-lian.tech` at it: the environment guard refuses non-local hosts outside production. XAMPP MariaDB currently
> listens on all interfaces (reachable on the LAN); set `bind-address=127.0.0.1` in XAMPP's `my.cnf` (needs approval). When hosting resumes:
>
> 1. Provision a separate MySQL/MariaDB database on the server with **new** credentials.
> 2. Rotate the exposed keys (section 5).
> 3. Run `verify-production.sh`, then follow section 4.
>
> If the server is re-created, its host name and IP will change. The VPS facts below describe the old server.

Status on 2026-11-10: the local environment is verified. Production (Hostinger) is **not verified**, because this
machine has no SSH access to it. No production configuration or database has been changed. This document contains no
secret values.

## 1. The two environments

| | Local development | Production |
|---|---|---|
| Host | macOS, this checkout | Hostinger VPS (`srv2043342.hstgr.cloud`, nginx, HTTPS + HSTS; public evidence only) |
| Database | XAMPP MariaDB 10.4.28, `127.0.0.1:3306`, `iclimate_staging_20261103_fix3` | Its own Hostinger MySQL/MariaDB database, **to be confirmed on the server** |
| Runtime DB account | `iclimate_app`: SELECT/INSERT/UPDATE/DELETE on that one database | Own account, own password (never the local one) |
| Backup account | `iclimate_backup`: SELECT, SHOW VIEW, EVENT, TRIGGER (Keychain item `iclimate.local.mariadb.iclimate_backup`) | Own account from `deploy/hostinger/mariadb-backup-account.sql` |
| `DB_TIMEZONE` | `+08:00` | `+08:00` (**required**, see section 3) |
| Sessions / queue | database / database | database / database |
| Cache | file | database |
| Broadcasting | Reverb `127.0.0.1:8080` | Reverb behind nginx (`wss://iclimate-lian.tech`); `/app/*` currently returns 404, so it is **not proxied yet** |
| Queue worker | `queue:work --queue=analytics,default,broadcasts` | `deploy/hostinger/iclimate-queue.service` (same queues) |
| Scheduler | `schedule:work` (start script) | `iclimate-scheduler.timer` (systemd, every minute); never together with cron |
| ML service | `.venv-ml`, uvicorn `127.0.0.1:8010` | `iclimate-python.service`, `127.0.0.1:8010` |
| APP_KEY | local key | **different** production key (see section 5) |

### Separation is enforced, not just documented

`App\Support\DatabaseEnvironmentGuard` runs at boot from configuration only, before any connection opens. The
application refuses to start when:

- Outside production, the application or backup connection points anywhere except this machine (127.0.0.1, ::1,
  localhost, or a socket). A copied production `.env` therefore cannot touch Hostinger from a laptop.
  `ICLIMATE_ALLOW_REMOTE_DATABASE=true` lifts this for one approved session.
- In production, the database is a local working copy (`iclimate_staging_*`, `iclimate_restore_*`, `iclimate_phpunit`, …).
- In any environment, the database host is Supabase.

`php artisan iclimate:environment-check` prints the same verdict without secrets (host *categories* only) and exits 1
when unsafe. `deploy/hostinger/verify-production.sh` runs it first.

## 2. What must be confirmed on Hostinger (needs SSH as the deploy user)

Run `sudo -u www-data bash deploy/hostinger/verify-production.sh` and, as an administrator:

```bash
php -v; php -m | grep -E 'pdo_mysql|mbstring|openssl|bcmath|intl|curl|xml|zip'
mysql --version; systemctl status mariadb mysql 2>/dev/null | head -3
mysql -e "select @@version, @@global.time_zone, @@system_time_zone, @@explicit_defaults_for_timestamp"   # admin account
df -h /var/lib/mysql /var/www /var/backups
systemctl list-units 'iclimate-*' --all; ss -ltn | grep -E ':8010|:8080|:3306'
/var/www/iclimate_system/.venv-ml/bin/python -c "import sklearn, numpy, pandas, fastapi; print('ml deps ok')"
```

Record: engine and version; server time zone; the database name and whether it already holds iClimate tables or
users; free disk space; PHP version and extensions; whether systemd units exist and run; and whether port 8080 is
proxied for `/app` and `/apps`.

Because this is a VPS (not shared hosting), long-running processes are possible: queue worker, Reverb, uvicorn and a
systemd timer. If the server turns out to be shared hosting, Reverb and the ML service cannot run there. Options then:
move them to a small VPS, or run with `BROADCAST_CONNECTION=log` (pages already fall back to polling) and a cron-driven
`queue:work --stop-when-empty`.

## 3. Time zones (critical for the import)

All 168 time columns are `TIMESTAMP`. The data holds UTC wall-clock strings that were written through a `+08:00`
session (the XAMPP server's SYSTEM zone). `mysqldump` exports TIMESTAMPs converted to UTC (`--tz-utc`, the default),
so the import is exact. Reading it back, however, depends on the session zone:

| Restored onto a UTC server, read through Laravel | `users#1.created_at` |
|---|---|
| Source (XAMPP) | 2026-07-15 13:56:11 |
| Target, `DB_TIMEZONE` unset | 2026-07-15 05:56:11: **every timestamp 8 hours early** |
| Target, `DB_TIMEZONE=+08:00` | 2026-07-15 13:56:11 |

Verified on MariaDB 10.4 and MySQL 8.4 servers running at UTC. Production must set `DB_TIMEZONE=+08:00`; the
environment guard warns when it is missing in production.

## 4. Production import plan (never automatic; needs approval)

**Compatibility (rehearsed 2026-11-10):** the MariaDB 10.4 backup imports into MySQL 8.4 and into MariaDB 10.4
without errors. Every row of all 69 tables is identical (row-level comparison), including all 877 password hashes.
The 87 foreign keys, 339 indexes and 34 JSON checks are kept. Migrations 000005/000006 then apply on both engines(000006 needed a fix for MySQL 8, and the new `2026_11_10_000001` keeps JSON as text on MySQL; see the runbook).

1. **Inventory production first** (read-only): `verify-production.sh`, then
   `scripts/compare-mysql-databases.php` against the local DB. If production already holds iClimate data with users
   or records that local lacks, **stop**: those are unique production records and need a reconciliation decision
   (same approach as `scripts/reconcile-supabase-only-records.php`: new IDs, nothing overwritten).
2. **Rehearse** on Hostinger: create `iclimate_import_rehearsal`, import the latest verified local backup, set
   `DB_TIMEZONE=+08:00`, run `php artisan migrate --force` (000005/000006), compare with
   `scripts/compare-mysql-databases.php`, then run `verify-production.sh` against it.
3. **Maintenance window** (≈15 minutes for 38 MB):
   1. `php artisan down`; stop the queue worker, scheduler timer and Reverb.
   2. Take a final local backup and run `verify-backup`.
   3. Check its SHA-256, then copy it to the VPS (`scp`, mode 600).
   4. Take a backup of the current production DB, if any.
   5. Import into an **empty** production database.
   6. Run `migrate --force` with the migration account.
   7. Run the compare script; expect `RESULT: MATCH` except for the ignored transient tables.
   8. Run `config:cache`, start the services, run `php artisan up`, then run the smoke tests (section 6).
4. **Rollback:** keep the previous production database (or its backup) untouched until sign-off. Point
   `DB_DATABASE` back at it, run `config:cache`, restart the services. Local XAMPP is never a rollback target.

## 5. Credentials (public Git history)

Treat these as compromised (committed in `afa3be2f`, public repository): `APP_KEY`, `MAIL_PASSWORD`, `GROQ_API_KEY`,
`OPENWEATHER_API_KEY`, `WINDY_API_KEY`, `WEATHERAPI_KEY`, `VITE_GOOGLE_MAPS_API_KEY`, `REDIS_PASSWORD`. Do not deploy
production with any of them.

- **Provider keys:** create a new key at each provider, revoke the old one, store the new one only in
  `/etc/iclimate/iclimate.env` (mode 600) or the local `.env`. Use **different** keys for local and production where
  the provider allows it. Restrict the Google Maps key by HTTP referrer.
- **APP_KEY:** nothing in the database is encrypted with it. It protects encrypted session cookies
  (`SESSION_ENCRYPT=true`) and the cached user entries. Procedure:
  1. Generate the new key on the server: `php artisan key:generate --show`. Do not paste it into chat or tickets.
  2. Set `APP_PREVIOUS_KEYS=<old key>` and `APP_KEY=<new key>`, then `php artisan config:cache` and restart PHP-FPM
     and the workers. Existing sessions keep working through the previous key.
  3. After the session lifetime (120 min) plus margin, remove `APP_PREVIOUS_KEYS` and run `config:cache`.
  4. Pending password-reset links stop working; users request a new one.
- **History:** rotation is required regardless. Rewriting history (`git filter-repo --path .env.mysql-backup
  --invert-paths` plus a force-push) changes every commit ID for all collaborators and needs separate approval.

## 6. Production smoke tests (after access is granted)

Run `verify-production.sh`; then as each of IT, MAO and Farmer: log in; open the dashboard, heat map, weather
predictions and planting advisories; request a yield estimate; generate one report and watch it reach `completed`;
send one notification to a test account; ask Climora one question. Then:

- Stop Reverb and repeat the notification (it must still succeed). Start Reverb again and confirm the retry was
  delivered.
- Run `iclimate:backup-database` and `iclimate:verify-backup`, and restore the backup into a separate
  `iclimate_restore_<timestamp>` database (`deploy/hostinger/restore-isolated-mariadb.sh`).
