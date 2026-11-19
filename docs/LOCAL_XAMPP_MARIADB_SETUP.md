# Local iClimate XAMPP/MariaDB Setup

This profile runs Laravel against the existing local MariaDB staging database. It does not require Supabase and does not edit `.env`.

## Current local readiness

Status: **PASS for the verified local profile; browser suite passed all executed tests, with 42 intentional skips**.

The verified local target is `iclimate_staging_20261103_fix3` with the following historical evidence:

- MariaDB 10.4.28
- 69 tables
- 877 users
- 107,274 application rows
- 87 foreign keys
- 3 timestamp triggers
- Prepared physical and logical backups with isolated physical recovery verified

The user's authorized Terminal run verified the local MariaDB gate on November 5, 2026:

```text
XAMPP: Starting MySQL...ok.
Local MariaDB PASS: database=iclimate_staging_20261103_fix3 tables=69 users=877 foreign_keys=87
```

This is a read-only verification; no records were changed. The earlier workspace check could not see port 3306 and returned `ERROR 2002 (HY000)`, indicating workspace/process isolation rather than disproving the host-level result.

The user's authorized Terminal run verified the Laravel HTTP server at `http://127.0.0.1:8000` with HTTP `200 OK`, Laravel session cookies, and the expected security headers (`X-Frame-Options`, `X-Content-Type-Options`, CSP, and related headers). The earlier workspace check could not see port 8000 and returned curl status `000`, indicating workspace/process isolation.

## Start and verify MariaDB

Start MariaDB from the XAMPP manager (or let the launcher run `sudo xampp startmysql` with `--start-mariadb`). Then:

```bash
cd /Users/johnlukereynon/iclimate_system
bash scripts/start-iclimate-local.sh check
```

`check` verifies PHP, `.venv-ml`, the environment guard (local host only, never Supabase), that the application
account is **not root**, and the database: 69 tables, 87 foreign keys, users, session time zone and pending
migrations. It uses the account in `.env` (the restricted `iclimate_app`) and never prints a password.

## Run the whole local environment (no Supabase, no Hostinger)

```bash
bash scripts/start-iclimate-local.sh start     # ml, web :8000, queue, scheduler, reverb (in the background)
bash scripts/start-iclimate-local.sh status    # health of each service + jobs waiting, failed jobs, last scheduled task
bash scripts/start-iclimate-local.sh stop      # stops everything it started (waits for the queue's current job)
bash scripts/start-iclimate-local.sh restart
```

- Each service runs under a restart loop in its own process group, so a crash (or the queue worker's hourly
  `--max-time` exit) restarts it after 5 s. PIDs live in `storage/logs/local-services/`; logs are
  `storage/logs/local-<service>.log`.
- Running `start` twice never starts a second copy. A port already used by another program is reported and that
  service is not started (the command exits non-zero).
- The scheduler (`schedule:work`) runs every minute while the environment is up; it refreshes weather and risk,
  prepares dashboards and makes the daily backup.
- The queue worker listens to `analytics,default,broadcasts`. Reverb is started only when
  `BROADCAST_CONNECTION=reverb`. If Reverb stops, saved actions still succeed and realtime updates are retried.
- To run one service in the foreground for debugging: `bash scripts/start-iclimate-local.sh web|ml|queue|scheduler|reverb`.

## Tests

Use an isolated MySQL test database, not `iclimate_staging_20261103_fix3`, for tests that write data. The previously verified baseline is 1,647 passed and 12,407 assertions. The infrastructure group previously passed 80 tests and 352 assertions.

```bash
APP_ENV=testing DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=iclimate_test_mysql_20261103 DB_USERNAME=root DB_PASSWORD= php artisan test --compact
```

Do not run migrations, seeders, resets, or destructive cleanup against the protected staging database.

The affected calendar test initially exposed a SQLite incompatibility: it issued MySQL-only `SET SESSION FOREIGN_KEY_CHECKS` during isolated schema removal. The cleanup is now driver-aware; the affected test passes with 6 tests and 42 assertions.

The complete suite was rerun after that fix with a 600-second limit:

```text
Tests:    1647 passed (12407 assertions)
Duration: 467.98s
Exit:     0
```

Targeted infrastructure checks also pass: 48 tests and 281 assertions. `php artisan schedule:list` successfully enumerates the configured automation, cache-warming, advisory, prediction, backup, and alert tasks. This verifies registration, not execution of every scheduled task.

Disposable runtime probes additionally passed:

- Queue: a file-backed Laravel job was dispatched to a temporary SQLite database and processed successfully by `queue:work --once`.
- Scheduler: a temporary scheduled callback executed successfully through Laravel's scheduling runtime. No real scheduled task, staging record, backup, or production configuration was changed.

The isolated Playwright browser suite was rerun against its throwaway SQLite database after `npm run build`:

```text
162 passed, 42 skipped, 0 failed
```

The browser suite's authenticated Admin/IT, MAO, and Farmer workflows were exercised against seeded disposable accounts and isolated SQLite. Login/dashboard, CRUD, reports, notifications, and role navigation paths passed. The seven landing-page root causes were fixed with targeted CSS/template changes; the feature-card assertion was aligned with the current intentional tab-and-link interaction, and the guest landing navigation assertion now reflects the implemented in-page `#login` flow. The 42 skipped tests remain explicit suite skips and were not introduced by this change.

The browser harness disables Supabase-related calls and uses local Laravel authentication/database services, so the passing workflows do not require a Supabase subscription. External weather/AI/advisory services remain optional or separately configured.

After the landing fixes, the affected Laravel regression tests passed (37 tests, 340 assertions). The complete Laravel suite was rerun against an isolated SQLite test database:

```text
Tests:    1647 passed (12407 assertions)
Duration: 475.67s
Exit:     0
```

This full-suite result verifies application regressions after the UI changes; the user's host-level MariaDB check remains the authoritative local MariaDB connectivity evidence because this workspace cannot see the host XAMPP process directly.

## Supabase independence and remaining external services

The Laravel application uses local MariaDB tables for authentication, dashboards, CRUD, reports, notifications, queues, cache, and sessions. The Supabase migration command remains available as a separate, explicitly configured historical tool; its source settings never fall back to active `DB_*` values.

Internet access may still be needed for configured external services:

- Open-Meteo weather data
- PAGASA advisories/data
- GeoRisk or map boundary sources
- Map tile providers
- SMTP mail
- Optional Groq AI fallback
- Optional Python ML service

These are not Supabase subscriptions. If unavailable, the application should use saved data or report the service as unavailable according to its existing service logic.

## Local limitations

- Local MariaDB must be running before web, queue, or scheduler processes can work.
- The intended production host is not selected by this document; production readiness remains separate.
- A local XAMPP run does not verify HTTPS, deployed-host networking, secret injection, monitoring, off-host backups, or production rollback.
- Never switch production configuration or run the migration import from this local profile.

## Supabase-independent configuration plan

The active repository `.env` currently resolves the default Laravel connection
to PostgreSQL/Supabase. It was intentionally not changed by this audit. The
wrapper is the safe current local profile because it injects MariaDB settings
only into the launched process.

For an approved local or production deployment, set these values manually in
the deployment environment or a separately backed-up `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=iclimate_staging_20261103_fix3
DB_USERNAME=iclimate_app
DB_PASSWORD=<secret-held-out-of-band>
DB_SOCKET=
QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=file
FILESYSTEM_DISK=local
```

Use a dedicated MariaDB account with only the reviewed runtime privileges. Do
not use `root` or a blank password outside isolated local development. The
`MIGRATION_SOURCE_DB_*` variables are for the separate historical migration
tool and must not be used as the application `DB_*` connection.

### Configuration-cache procedure

`bootstrap/cache/config.php` was absent during this audit, so Laravel currently
reads environment settings at boot. If configuration is ever cached, change
the deployment environment first and then run the following in the approved
deployment directory:

```bash
php artisan config:clear
php artisan config:cache
php artisan queue:restart
```

Verify the cached configuration before accepting traffic. Never run these
commands against the protected staging application while it is serving users
without an approved maintenance window.

### Read-only verification

The following verifies the effective database, queue, session, cache, and
scheduler configuration without changing records:

```bash
env APP_ENV=local \
  DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 \
  DB_DATABASE=iclimate_staging_20261103_fix3 DB_USERNAME=root DB_PASSWORD= \
  php artisan tinker --execute='echo config("database.default")."/".DB::connection()->getDriverName()."/".DB::connection()->getDatabaseName()."\n"; echo "queue=".config("queue.default")." session=".config("session.driver")." cache=".config("cache.default")."\n"; echo DB::table("users")->count()." users\n";'

env APP_ENV=local DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 \
  DB_DATABASE=iclimate_staging_20261103_fix3 DB_USERNAME=root DB_PASSWORD= \
  php artisan schedule:list
```

Expected output includes `mysql/mysql/iclimate_staging_20261103_fix3`, the
database queue, and the verified user count. The web, queue, and scheduler
wrappers use the same explicit `DB_CONNECTION=mysql` environment injection;
they do not inherit the active Supabase `DB_*` values.

### Rollback

Before activation, preserve the prior environment file and record the config
cache state out of band. If rollback is required, stop new writes and workers,
restore the prior environment values, clear/rebuild the configuration cache,
restart workers, and run the read-only connection check again. Any writes made
after activation must be reconciled before switching systems; changing
`DB_CONNECTION` alone does not reverse those writes.

## Controlled local activation audit — 2026-11-06

Activation was prepared but **not applied**. The active `.env` remains
unchanged and still points the ordinary Laravel process at PostgreSQL/Supabase.

Read-only checks found:

- XAMPP MariaDB 10.4.28 is reachable on `127.0.0.1:3306`.
- `iclimate_staging_20261103_fix3` exists with 69 tables and 87 foreign keys.
- `iclimate_app` does not currently exist in `mysql.user`.
- No `iclimate_app` database-grant row exists for the target database.
- `bootstrap/cache/config.php` is absent.
- XAMPP Apache/MariaDB, `php artisan serve`, and `php artisan schedule:work`
  processes are currently running. They were not stopped because no activation
  approval was provided.

Therefore **local activation readiness: FAIL/BLOCKED** until the dedicated
account is provisioned and its out-of-band password is available. No account
was created and no grants were changed by this audit.

## MariaDB application-account provisioning

The target schema was inspected read-only. It contains 69 base tables and no
views, stored routines, triggers, or events. Runtime source review found no
`CALL`, `LOAD DATA`, table-lock, or dynamic DDL operation. The minimum ordinary
application privileges are therefore:

| Privilege | Purpose |
|---|---|
| `SELECT` | Laravel reads, authentication, reports, dashboards, queue/session reads |
| `INSERT` | New users, business records, notifications, jobs, logs, sessions |
| `UPDATE` | Normal CRUD, queue reservations, sessions, status changes |
| `DELETE` | User-requested deletes and queue/runtime cleanup |

The application account does not need `CREATE`, `ALTER`, `DROP`, `INDEX`,
`REFERENCES`, `TRIGGER`, `EXECUTE`, `FILE`, `SUPER`, `GRANT OPTION`, or access to
the `mysql` system schema. Schema migrations, backups, physical recovery, and
restores must use a separate administrator/deployment account.

### Administrator-only provisioning procedure

Do not execute this block until explicitly approved. Generate and store the
password out of band; never place it in Git, this document, chat, or a shared
shell history:

```bash
umask 077
openssl rand -hex 32
```

In an administrator-only MariaDB session, use the generated value without
printing it:

```sql
CREATE USER 'iclimate_app'@'127.0.0.1' IDENTIFIED BY '<out-of-band-secret>';
GRANT SELECT, INSERT, UPDATE, DELETE
ON `iclimate_staging_20261103_fix3`.*
TO 'iclimate_app'@'127.0.0.1';
SHOW GRANTS FOR 'iclimate_app'@'127.0.0.1';
```

Because Laravel is configured with `DB_HOST=127.0.0.1`, the host restriction is
intentional. If a future deployment uses a Unix socket or resolves as
`localhost`, provision and verify a separately restricted host entry; do not
broaden this account to `%` without security approval.

### Read-only account verification

After provisioning, supply the password only in the current terminal session:

```bash
export ICLIMATE_LOCAL_DB_PASSWORD='<secret-set-out-of-band>'
MYSQL_PWD="$ICLIMATE_LOCAL_DB_PASSWORD" \
  /Applications/XAMPP/xamppfiles/bin/mysql --protocol=tcp \
  --host=127.0.0.1 --port=3306 --user=iclimate_app \
  iclimate_staging_20261103_fix3 \
  --batch --skip-column-names \
  --execute='SELECT CURRENT_USER(), DATABASE(), @@version; SELECT COUNT(*) FROM users; SHOW GRANTS;'
unset ICLIMATE_LOCAL_DB_PASSWORD MYSQL_PWD
```

This verifies connection, intended database, MariaDB version, user-table read
access, and effective grants without modifying records. Do not test write
privileges against the protected database; validate those in a disposable
database or rely on the reviewed grant statement.

### Approval-gated activation sequence

After explicit approval, run these steps from macOS Terminal. The first command
creates a mode-600 backup without printing its contents; choose a protected
destination and do not commit it:

```bash
cd /Users/johnlukereynon/iclimate_system
umask 077
mkdir -p "$HOME/.iclimate-private/env-backups" && chmod 700 "$HOME/.iclimate-private" "$HOME/.iclimate-private/env-backups"
cp .env "$HOME/.iclimate-private/env-backups/.env.before-mariadb-$(date -u +%Y%m%dT%H%M%SZ)"
```

Stop the local processes that may hold the old configuration, using the
project’s normal service controls or by stopping the specific `php artisan
serve`, queue, and scheduler processes. Do not stop MariaDB itself.

Then manually update only the local runtime environment with:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=iclimate_staging_20261103_fix3
DB_USERNAME=iclimate_app
DB_PASSWORD=<existing-secure-password>
QUEUE_CONNECTION=database
SESSION_DRIVER=database
FILESYSTEM_DISK=local
```

Do not place the password in chat, Git, command history, or this document. If
configuration caching is used, run `php artisan config:clear` followed by
`php artisan config:cache` only after the environment is correct. Restart the
web, queue, and scheduler processes with the same environment.

### Post-activation read-only checks

```bash
cd /Users/johnlukereynon/iclimate_system
bash scripts/start-iclimate-local.sh check

php artisan tinker --execute='echo config("database.default")."/".DB::connection()->getDriverName()."/".DB::connection()->getDatabaseName()."\n"; echo "queue=".config("queue.default")." session=".config("session.driver")." cache=".config("cache.default")."\n"; echo DB::table("users")->count()." users\n";'
php artisan schedule:list
```

Expected results are `mysql/mysql/iclimate_staging_20261103_fix3`, 877 users,
database queue, database sessions, local filesystem, and successful scheduler
registration. Do not run migrations, seeders, queue workers, or scheduled jobs
against the protected database as part of this verification.

### Rollback

Stop the same web, queue, and scheduler processes before rollback. Restore the
mode-600 `~/.iclimate-private/env-backups/.env.before-mariadb-<timestamp>` file, clear/rebuild config cache if
present, and restart the prior processes. If MariaDB received writes, freeze
the application and reconcile/export that write delta before resuming the
previous PostgreSQL/Supabase configuration.

## Provisioning verification result — 2026-11-06

The explicitly approved provisioning operation completed successfully. The
generated password is stored in macOS Keychain under the service name
`iclimate.local.mariadb.iclimate_app`; the password itself is not recorded in
the repository or documentation.

| Check | Result |
|---|---|
| Account | `iclimate_app`@`127.0.0.1` created |
| Database scope | `iclimate_staging_20261103_fix3` only |
| Global non-`USAGE` privileges | 0 |
| Database privileges | `SELECT, INSERT, UPDATE, DELETE` only |
| Account connection | PASS; MariaDB 10.4.28 |
| Laravel read-only probe | PASS; `mysql/mysql/iclimate_staging_20261103_fix3`, 877 users |
| Database-session profile | PASS; queue `database`, session `database`, filesystem `local` |
| Foreign-key audit as application account | PASS; 87/87, zero orphans |
| `.env` activation | NOT APPLIED |

The protected database was queried only; no test writes, migrations, seeders,
queue jobs, scheduled jobs, process stops, or Supabase changes were performed.
Local activation remains pending until the environment switch is separately
approved.

## Controlled activation result — 2026-11-06

The approved local switch was applied without changing the database or the
Supabase backup. The original `.env` is preserved as
`.env.before-mariadb-20261105T170715Z` with mode `600`. On 2026-11-10 it was
moved out of the repository to `~/.iclimate-private/env-backups/` (directory
mode `700`); `.gitignore` now excludes every `.env.*` file except `.env.example`.

| Check | Result |
|---|---|
| Local `.env` MariaDB settings | PASS |
| Config cache | ABSENT; no stale cache remained |
| Normal Laravel CLI connection | PASS — `mysql/mysql/iclimate_staging_20261103_fix3` |
| Users read | PASS — 877 |
| Tables / foreign keys | PASS — 69 / 87 |
| Orphan records | PASS — 0 |
| Queue configuration | PASS — database; no worker started |
| Session / filesystem configuration | PASS — database / local |
| Scheduler registration | PASS — `php artisan schedule:list` |
| Scheduler process | STOPPED intentionally after a non-mutating smoke test |
| Web verification | PASS — HTTP 200 on `127.0.0.1:8000` |
| Supabase configuration preserved | PASS — backup and migration-source settings retained |
| Local activation | **INCOMPLETE** until an approved scheduler/queue operating policy is applied |

The scheduler was not left running because its configured tasks can write
application data; the smoke process was terminated before any scheduled command
ran. The web server is running in the current terminal session. Start the
scheduler and queue worker only under an approved local operating procedure.

## Laravel scheduler safety audit — 2026-11-06

The scheduler registry contains 17 tasks. This audit inspected registration and
command implementations only; it did not start the scheduler, run a scheduled
command, or start a queue worker.

`php artisan schedule:list` was run read-only and enumerated all 17 entries;
it did not execute any of them.

| Scheduled task | Frequency | Mode / dependencies | MariaDB | Risk / action |
|---|---|---|---|---|
| `iclimate:automation-run --force-weather` | Every 30 min | Writes `analytics_runs` and `jobs`; analytics, weather, ML | Compatible | High; enable only with queue/ML monitoring |
| `iclimate:refresh-barangay-weather` | `:01/:11/:21/:31/:41/:51` | Writes weather/risk/cache tables; Open-Meteo | Compatible | High; monitor provider failures and overlaps |
| `iclimate:warm-dashboards` | Every minute | Reads dashboards; writes prepared cache/log/runtime metadata | Compatible | Medium; retain `withoutOverlapping` |
| `iclimate:warm-dashboard-cache` | Every 2 min | Reads health checks; writes cache | Compatible | Low/medium; monitor cache failures |
| `iclimate:refresh-weather-cache` | Every 3 min | Reads profiles/users; writes weather/cache; Open-Meteo/weather provider | Compatible | Medium; external API availability required |
| `iclimate:fetch-pagasa-advisories --force` | `:07/:22/:37/:52` | Writes `planting_advisories` and related notifications; PAGASA and local weather services | Compatible | High; business-data writer and external dependency |
| `iclimate:refresh-lian-boundaries` | Daily | Writes local cache only; GeoRisk ArcGIS API | Compatible | Medium; bundled boundary fallback exists |
| `iclimate:refresh-barangay-risk` | `:05/:35` | Reads stored weather; updates `heatmap_areas` and cache | Compatible | Medium; derived-data writer |
| `iclimate:refresh-flood-risk` | `:06/:36` | Reads stored forecasts/profiles; updates `heatmap_areas` and cache | Compatible | Medium; derived-data writer |
| `iclimate:clean-stale-queue` | Every 5 min | Updates `analytics_runs`; deletes selected `jobs`; writes queue backup files | Compatible | High; destructive runtime cleanup; monitor retained queue backups |
| `iclimate:process-datasets` | Daily 01:30 | Writes `analytics_runs`/`jobs`; local Python/ML pipeline | Compatible | High; requires Python service/runtime and queue worker |
| `iclimate:refresh-predictions` | Daily 02:00 | Queues monthly forecast work; writes runtime queue/analytics state | Compatible | High; requires queue worker and ML service |
| `iclimate:warm-prediction-cache` | Daily 02:15 | Writes monthly predictions, yield/municipal results, cache; local ML | Compatible | High; business-derived-data writer |
| `iclimate:warm-farmer-yields` | Every 3 hours at `:40` | Reads farms/profiles; writes `yield_predictions` and cache; local ML | Compatible | High; business-derived-data writer |
| `iclimate:backup-database` | Daily 03:15 | Reads DB; writes/deletes backup files; invokes `mysqldump` | App account insufficient | Critical; run with separate backup/admin identity |
| `iclimate:verify-backup` | Daily 03:25 | Reads backup files; records `backup_verifications` by default | Compatible for record write | Medium; requires backup files and verification tools |
| `iclimate:publish-scheduled-alerts` | Every minute | Updates advisories; inserts notifications; may send/queue mail | Compatible | High; notification/business writer |

All registered tasks use `withoutOverlapping` protections. The repository
contains alternative deployment instructions for one cron-driven scheduler or
one long-running `schedule:work` process, but both must never be enabled at the
same time. The host crontab could not be inspected from this session because
macOS denied `crontab -l`; duplicate-run status therefore remains a host-level
verification requirement. The controlled local scheduler process is currently
stopped.

### Supabase and external-service findings

No scheduled command directly uses a Supabase SDK, Supabase URL, PostgreSQL
connection, or Supabase Auth/Storage/Realtime API. The migration command and
PostgreSQL compatibility branches are separate tooling. Scheduled external
dependencies are Open-Meteo/weather provider, PAGASA, GeoRisk ArcGIS, the local
Python ML service, SMTP/mail, and filesystem backup tools. If an external
provider fails, the command’s existing fallback or failure behavior must be
monitored; it does not redirect the database to Supabase.

### Privilege findings

The existing `iclimate_app` account is sufficient for ordinary scheduled DML:
`SELECT`, `INSERT`, `UPDATE`, and `DELETE`. No scheduled application command
requires schema DDL, stored routines, triggers, events, `SUPER`, or global
privileges. `iclimate:backup-database` is the exception: `mysqldump` with
triggers/routines/events must use a separate reviewed backup account or an
administrator-controlled wrapper. Do not grant those privileges to
`iclimate_app`.

### Safe activation and monitoring

Before activation, obtain explicit approval for the first run, confirm exactly
one scheduler owner, confirm queue/ML/mail/API availability, and create a
fresh database/filesystem backup. Start one scheduler only after checking that
no cron, Task Scheduler, Supervisor, or existing `schedule:work` process is
active. Start the queue worker separately only when queued writes are approved.

Monitor `storage/logs/automation-*.log`, Laravel errors, `scheduled_task_runs`,
`jobs`, `failed_jobs`, backup age, provider failures, prediction freshness, and
database disk capacity. Keep `withoutOverlapping` enabled and investigate stale
locks rather than launching a second scheduler.

### Scheduler rollback

Stop the single scheduler and queue worker, preserve the MariaDB write delta,
and export/record changes made after activation before switching configuration
back. Do not point the application at Supabase and continue writing without a
reconciliation decision. Restore the prior `.env` from its mode-600 backup only
after the write delta and rollback owner are confirmed.

**Scheduler activation decision: BLOCKED pending explicit scheduler approval,
host-level duplicate-process verification, and a separate backup/admin
credential for the 03:15 backup task.**

### Administrator-confirmed host checks

The operator has now completed the previously blocked macOS checks:

- No running `artisan schedule` or equivalent scheduler process.
- No personal cron jobs for `johnlukereynon`.
- No root cron jobs.
- `/usr/lib/cron/tabs` is empty.
- No matching iClimate user LaunchAgents.
- No matching iClimate system LaunchAgents or LaunchDaemons.
- `/etc/crontab` does not exist.

These results clear the inspected cron and launchd paths. They do not verify
third-party process managers or externally managed service definitions. Any
Supervisor, Docker, Homebrew service, MAMP/XAMPP wrapper, or other process
manager must be checked by its owner before activation. The intended local
mechanism remains exactly one `php artisan schedule:work` process.

### Pending backup-account provisioning plan

No account or grant is created by this document. After separate administrator
approval, run the following SQL as a MariaDB administrator, replacing only the
password through a secure interactive mechanism. Do not paste the password into
source control, this file, or shell history:

```sql
CREATE USER 'iclimate_backup'@'127.0.0.1' IDENTIFIED BY '<out-of-band-password>';
GRANT SELECT, SHOW VIEW, TRIGGER, EVENT
  ON `iclimate_staging_20261103_fix3`.*
  TO 'iclimate_backup'@'127.0.0.1';
SHOW GRANTS FOR 'iclimate_backup'@'127.0.0.1';
```

The proposed grant is based on the exact dump flags currently used. `SELECT`
reads table data; `SHOW VIEW` covers views if they are introduced; `TRIGGER`
and `EVENT` cover the explicitly requested trigger/event metadata. The target
currently has no views, triggers, routines, or events. `--single-transaction`
avoids requiring `LOCK TABLES` for transactional tables, and `--no-tablespaces`
avoids the usual tablespace/process privilege requirement. If the installed
XAMPP client returns a privilege error, add only the specific privilege named
by that error after administrator review; do not grant global privileges or
expand `iclimate_app`.

The account must be limited to host `127.0.0.1` and this database. Verify it
without printing the password:

```bash
MYSQL_PWD="$ICLIMATE_BACKUP_PASSWORD" \
  /Applications/XAMPP/xamppfiles/bin/mysql --protocol=tcp -h 127.0.0.1 -P 3306 \
  -u iclimate_backup iclimate_staging_20261103_fix3 \
  -NBe 'SELECT DATABASE(), CURRENT_USER(), COUNT(*) FROM users;'
unset MYSQL_PWD ICLIMATE_BACKUP_PASSWORD
```

The verification command is read-only. The password must be supplied in the
current shell from a secure prompt or Keychain and must never be placed in the
command text saved to history.

### Pending named Laravel backup connection

The current code already supports `BACKUP_DB_CONNECTION`: `config/backup.php`
selects that named connection, while `DatabaseBackupService` obtains its host,
port, database, username, and password from Laravel's connection config. The
following is the exact configuration change to prepare, but it has **not** been
applied because application-code and environment changes require a separate
approval:

```php
// config/database.php, inside 'connections'
'iclimate_backup' => [
    'driver' => 'mysql',
    'host' => env('BACKUP_DB_HOST', '127.0.0.1'),
    'port' => env('BACKUP_DB_PORT', '3306'),
    'database' => env('BACKUP_DB_DATABASE', 'iclimate_staging_20261103_fix3'),
    'username' => env('BACKUP_DB_USERNAME', 'iclimate_backup'),
    'password' => env('BACKUP_DB_PASSWORD'),
    'unix_socket' => env('DB_SOCKET', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'prefix_indexes' => true,
    'strict' => true,
    'engine' => null,
],
```

The protected local environment would then set only the non-secret values:

```text
BACKUP_DB_CONNECTION=iclimate_backup
BACKUP_DB_HOST=127.0.0.1
BACKUP_DB_PORT=3306
BACKUP_DB_DATABASE=iclimate_staging_20261103_fix3
BACKUP_DB_USERNAME=iclimate_backup
BACKUP_DB_PASSWORD=(injected at runtime from Keychain; never committed)
```

For Keychain storage, an administrator may store the secret under
`iclimate.local.mariadb.iclimate_backup` using `security add-generic-password`
with the value supplied through a hidden prompt, then retrieve it only into a
short-lived environment variable for the approved backup/scheduler process.
`security find-generic-password` must never be run with `-w` in a command whose
output is logged or displayed. The current reconciliation and application
account Keychain entries are not changed by this plan.

Unexecuted example for the administrator:

```bash
read -r -s ICLIMATE_BACKUP_PASSWORD
printf '\n'
security add-generic-password -U -a "$USER" \
  -s 'iclimate.local.mariadb.iclimate_backup' \
  -w "$ICLIMATE_BACKUP_PASSWORD"
unset ICLIMATE_BACKUP_PASSWORD
```

At execution time, retrieve it only for the approved process and clear the
variable afterward:

```bash
ICLIMATE_BACKUP_PASSWORD="$(security find-generic-password -a "$USER" \
  -s 'iclimate.local.mariadb.iclimate_backup' -w)"
export BACKUP_DB_PASSWORD="$ICLIMATE_BACKUP_PASSWORD"
unset ICLIMATE_BACKUP_PASSWORD
# Run only the separately approved Laravel verification here.
unset BACKUP_DB_PASSWORD
```

### Separately approved backup verification

After the account and named connection are approved and configured, verify in
this order:

1. Run `php artisan config:clear` and rebuild configuration only if the
   administrator approves the environment change.
2. Run a read-only Laravel connection probe that prints only driver, database,
   current user, and a row count—never credentials.
3. Invoke `php artisan iclimate:backup-database --no-prune` once during an
   approved maintenance window; this writes a new backup file and therefore
   requires explicit approval separate from this audit.
4. Run `php artisan iclimate:verify-backup --no-record` against that newest
   file. This avoids inserting a `backup_verifications` row.
5. Check the file age, size, completion marker, permissions, and restore plan
   using a disposable database only. Never restore over the staging database.

Until this procedure passes, keep the 03:15 backup schedule disabled or keep
the scheduler stopped. The remaining 16 scheduled entries are independent of
the backup entry, but enabling them still requires explicit approval because
many write derived or business-facing records.

### Approval checkpoint

No provisioning, Laravel configuration change, backup, scheduled command,
scheduler, queue worker, account grant, or application-code edit may proceed
from this preparation. Required approval must explicitly authorize:

```text
APPROVE ICLIMATE_BACKUP_ACCOUNT PROVISIONING
```

After that separate approval, the administrator can create and verify the
account. A later, separate approval is required for the named Laravel
connection change and for the first backup execution. Scheduler activation
remains blocked until all three approvals and the process-manager check are
complete.

### Approved provisioning attempt — prerequisite blocked

The approval `APPROVE ICLIMATE_BACKUP_ACCOUNT PROVISIONING` was received, but
the existing XAMPP MariaDB service was stopped. The project check path confirmed
the client was present and attempted only `startmysql`; it did not start
Laravel, the scheduler, or a queue worker. macOS rejected the required
administrator elevation with:

```text
sudo: a terminal is required to read the password; either use the -S option to read from standard input or configure an askpass helper
sudo: a password is required
```

No `CREATE USER`, `GRANT`, Keychain write, Laravel configuration change, or
backup was performed. An authorized administrator must run this in an
interactive Terminal, then return for read-only account provisioning and
verification:

```bash
cd /Users/johnlukereynon/iclimate_system
bash scripts/start-iclimate-local.sh check
```

That command starts only the existing XAMPP MariaDB service and verifies the
known staging table/user/FK counts; it does not start Laravel scheduler or
queue processes. The approved backup-account provisioning must still be run
separately after MariaDB becomes reachable.

### Approved backup-account provisioning result — 2026-11-06

The approved provisioning was completed after MariaDB became reachable. The
generated credential is stored only in macOS Keychain under
`iclimate.local.mariadb.iclimate_backup`; no password or password hash is
recorded here. The credential was rotated before final verification.

| Check | Result |
|---|---|
| Account | `iclimate_backup`@`127.0.0.1` created |
| Database scope | `iclimate_staging_20261103_fix3` only |
| Schema privileges | `EVENT`, `SELECT`, `SHOW VIEW`, `TRIGGER` only |
| Non-`USAGE` global privileges | 0 |
| MariaDB connection | PASS; MariaDB 10.4.28 |
| Target database selected | PASS |
| Users readable | PASS; 877 rows |
| Target tables visible | PASS; 69 |
| Foreign keys visible | PASS; 87 |

Verification used read-only SQL only. No backup was executed, and no Laravel
configuration, scheduler, queue worker, or business data was changed.

### Dedicated Laravel backup connection — prepared, not applied

Read-only inspection confirmed that `DatabaseBackupService` selects
`config('backup.connection')` when `BACKUP_DB_CONNECTION` is set, otherwise it
uses Laravel's default connection. It then passes the selected MariaDB
password to `mysqldump` through `MYSQL_PWD`; the password is not a command-line
argument or log value. The current backup options are:

```text
--host, --port, --user, --single-transaction, --triggers,
--no-tablespaces, --default-character-set=utf8mb4, --result-file,
--routines, --events
```

The proposed minimal `config/database.php` addition is the following entry
inside `connections`. It has **not** been applied:

```php
'mysql_backup' => [
    'driver' => 'mysql',
    'url' => env('BACKUP_DB_URL'),
    'host' => env('BACKUP_DB_HOST', '127.0.0.1'),
    'port' => env('BACKUP_DB_PORT', '3306'),
    'database' => env('BACKUP_DB_DATABASE', 'iclimate_staging_20261103_fix3'),
    'username' => env('BACKUP_DB_USERNAME', 'iclimate_backup'),
    'password' => env('BACKUP_DB_PASSWORD', ''),
    'unix_socket' => env('BACKUP_DB_SOCKET', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'prefix_indexes' => true,
    'strict' => true,
    'engine' => null,
    'options' => extension_loaded('pdo_mysql') ? array_filter([
        PDO::ATTR_TIMEOUT => max(1, (int) env('BACKUP_DB_CONNECT_TIMEOUT', 3)),
    ]) : [],
],
```

The protected environment would set only these non-secret selectors:

```text
BACKUP_DB_CONNECTION=mysql_backup
BACKUP_DB_HOST=127.0.0.1
BACKUP_DB_PORT=3306
BACKUP_DB_DATABASE=iclimate_staging_20261103_fix3
BACKUP_DB_USERNAME=iclimate_backup
BACKUP_DB_PASSWORD=(runtime-injected; never committed)
```

The default `DB_CONNECTION`, `DB_USERNAME`, and `DB_PASSWORD` remain unchanged
for `iclimate_app`. Existing backup format, retention, verification, and
pruning behavior also remain unchanged.

### Keychain and non-interactive scheduler design

The Keychain item is `iclimate.local.mariadb.iclimate_backup`. A non-
interactive `schedule:work` process cannot be assumed to have an unlocked
login keychain or an approved Keychain ACL. Therefore the preferred design is
an administrator-owned wrapper that retrieves the item once and `exec`s the
single approved scheduler process with `BACKUP_DB_PASSWORD` in its environment:

```bash
BACKUP_DB_PASSWORD="$(security find-generic-password -a "$USER" \
  -s 'iclimate.local.mariadb.iclimate_backup' -w)"
export BACKUP_DB_PASSWORD
exec php artisan schedule:work
```

The wrapper must be mode `700`, owned by the scheduler user, excluded from Git,
and run only after the login Keychain is unlocked and its access policy is
reviewed. It must not print the variable. A safer alternative is a separate
backup-only service that injects the secret only for the 03:15 command; this
requires an approved scheduler design change. Do not place the password in
`.env`, a launchd plist, a shell history entry, or a process-manager command
line.

### Configuration approval and verification plan

The following changes require explicit approval and are not yet applied:

1. Add the `mysql_backup` connection above.
2. Set `BACKUP_DB_CONNECTION=mysql_backup` and the non-secret `BACKUP_DB_*`
   values in the protected runtime environment.
3. Clear/rebuild Laravel configuration cache so the named connection is loaded.
4. Verify that normal Laravel queries still use `mysql`/`iclimate_app` and that
   only the backup service resolves to `mysql_backup`/`iclimate_backup`.
5. Run read-only probes for current user, selected database, 69 tables, and 87
   foreign keys using the named connection.

No backup is part of this approval. A later, separate approval is required for
the first backup test, which must use a new protected destination and
`--no-prune`, then run `iclimate:verify-backup --no-record`. The test must not
restore over the application database.

### Rollback

To roll back the connection preparation, stop the approved scheduler process,
remove only `BACKUP_DB_CONNECTION` and the `BACKUP_DB_*` runtime settings,
clear/rebuild Laravel configuration cache, and verify that the default
`mysql` connection still uses `iclimate_app`. No application database records
are changed by this rollback. The Keychain item may remain until an
administrator separately approves credential retirement.

**Named backup connection: BLOCKED pending explicit approval. First backup
test: PENDING. Scheduler activation: BLOCKED. Production cutover: NO-GO.**

### Named backup connection activation result — 2026-11-06

The explicit approval `APPROVE ICLIMATE NAMED BACKUP CONNECTION` was received.
The minimal `mysql_backup` connection was added to `config/database.php`, and
the active environment now selects it through the non-secret setting
`BACKUP_DB_CONNECTION=mysql_backup`. No backup password was added to `.env`.

| Check | Result |
|---|---|
| Default Laravel connection | PASS; remains `mysql` / `iclimate_app` |
| Named backup selector | PASS; `mysql_backup` |
| Named backup driver/database | PASS; MySQL / `iclimate_staging_20261103_fix3` |
| Named backup account probe | PASS; 877 users readable |
| Configuration cache | Cleared after change |
| Protected staging database modified | NO |
| First database backup | NOT RUN; separate approval still required |

The Keychain credential was injected only into the read-only probe process.
Backup-related regression tests passed against an in-memory SQLite database:
50 tests and 214 assertions. No scheduler or queue worker was started.

### First backup test result — 2026-11-06

The explicit approval `APPROVE ICLIMATE FIRST BACKUP TEST` was received. One
backup was created with `iclimate_backup` through the `mysql_backup` connection,
using `--no-prune` and a newly created mode-700 destination under
`storage/app/reconciliation`. No existing backup was overwritten.

| Check | Result |
|---|---|
| Backup command | PASS; `iclimate:backup-database --no-prune` |
| Output | New 36.24 MB MariaDB SQL dump |
| File permissions | PASS; `0600` |
| Directory permissions | PASS; `0700` |
| Completion marker | PASS; `-- Dump completed` present |
| Archive/content verification | PASS; 69 archive entries |
| Laravel verification | PASS; `iclimate:verify-backup --no-record` |
| Existing backups overwritten | NO |
| Backup verification row written | NO; `--no-record` used |
| Scheduler/queue worker started | NO |
| Database records modified | NO |

The final optional metadata-printing helper used GNU `find -printf`, which is
not supported by macOS BSD `find` and returned a nonzero status after the
backup and verification had already succeeded. Native `stat` checks then
confirmed the file and directory modes above. This reporting portability issue
does not invalidate the backup test.

First backup test: **PASS**. Scheduler activation remains **BLOCKED** pending
the separate scheduler operating approval and monitoring ownership.

### Host-level duplicate-scheduler verification details

The read-only checks available to this session found:

- No running process matching `artisan`, `schedule`, `queue:work`, Apache/PHP
  scheduler startup, or a process manager scheduler at the time of inspection.
- No iClimate entry in the accessible per-user LaunchAgents directory. The
  visible entries there belonged to unrelated software. System LaunchDaemons
  and other users' agents were not treated as inspected because macOS access
  is restricted.
- `launchctl list` returned no matching iClimate/PHP/Laravel scheduler entry in
  the accessible session view.
- `crontab -l` failed with the exact macOS error `zsh:1: operation not
  permitted: crontab`. This is an access-control result, not proof that no
  crontab exists.

An authorized administrator must complete the host check before activation:

```bash
launchctl list | grep -Ei 'iclimate|artisan|php|schedule|cron' || true
sudo launchctl print system | grep -Ei 'iclimate|artisan|php|schedule|cron' || true
sudo launchctl print-disabled system
find "$HOME/Library/LaunchAgents" /Library/LaunchAgents /Library/LaunchDaemons \
  -maxdepth 1 -type f -print 2>/dev/null
crontab -l
sudo crontab -l
sudo grep -RniE 'iclimate|artisan|schedule:(run|work)|queue:work' \
  /Library/LaunchAgents /Library/LaunchDaemons /etc/crontab /etc/cron.d /etc/periodic 2>/dev/null
ps axww -o user,pid,ppid,command | grep -Ei 'artisan|schedule|queue:work|supervis|cron' | grep -v grep
```

The repository also contains Windows Task Scheduler and PowerShell supervisor
instructions. Those are deployment alternatives, not evidence of an active
macOS service. Select exactly one mechanism: one long-running
`php artisan schedule:work` owner for local XAMPP, or one cron/launchd entry
calling `php artisan schedule:run` every minute. Do not combine them.

### Backup-task readiness and least-privilege proposal

Static inspection of `DatabaseBackupService` confirms that the scheduled
`iclimate:backup-database` task invokes `mysqldump`, passes the password only
through `MYSQL_PWD`, requests `--single-transaction`, `--triggers`,
`--no-tablespaces`, and—by default—`--routines` and `--events`, then prunes old
backup files. No backup was executed during this audit.

`BACKUP_DB_CONNECTION` can select a named Laravel connection, but the current
configuration has no separate backup connection or backup credential. The
application account has only `SELECT, INSERT, UPDATE, DELETE` on the target
schema. It must not be expanded for backup use. A separately reviewed local
backup account should be configured with only the privileges demonstrated as
necessary by the installed XAMPP client and MariaDB 10.4, typically schema
`SELECT` plus `SHOW VIEW`, `TRIGGER`, and `EVENT` for the requested dump
objects; `LOCK TABLES` or other privileges must be added only if the actual
client reports that they are required. No global administrative privilege is
approved by this audit.

The safe implementation is a separately named Laravel database connection
whose credentials are supplied out-of-band, with `BACKUP_DB_CONNECTION` set
only in the protected local environment. An administrator-controlled wrapper
around `mysqldump` is an alternative. Do not put backup credentials in
`iclimate_app`, source code, logs, or this document.

The backup schedule entry can be disabled independently of the other 16
scheduled entries, but doing so creates a deliberate backup gap. Until the
separate identity is provisioned and tested by an administrator, keep the
entire scheduler stopped or use an explicitly approved schedule definition
that omits only the backup entry. The 03:25 verification task is also not a
substitute for creating a backup; it only inspects the newest existing file
and records a verification row by default.

### Final scheduler operating readiness — authoritative current status

This section supersedes earlier pending-status notes in this document.

| Decision item | Current result |
|---|---|
| Scheduler readiness | **BLOCKED** |
| Monitoring owner | **PENDING**; no named operator or escalation rota assigned |
| Rollback owner | **PENDING**; no named owner for post-activation write reconciliation |
| Duplicate scheduler risk | **UNVERIFIED**; cron/launchd checks passed, but third-party process-manager ownership is not verified |
| Backup integration | **PASS**; `mysql_backup` selected, first dump and `--no-record` verification passed |
| Explicit operating approval | **PENDING** |

The 17-task registry was reviewed. All tasks use Laravel overlap protection,
but the scheduler remains a single-process responsibility. Write-capable tasks
include weather/risk refreshes, cache and dashboard warming, analytics and
prediction queueing, prediction/yield materialization, stale-job cleanup,
scheduled alerts/notifications, backup-file creation/pruning, and backup
verification metadata when `--no-record` is not used. External dependencies
are Open-Meteo/weather, PAGASA, GeoRisk ArcGIS, local Python/ML, mail, and
filesystem backup storage.

The exact local process owner must be assigned by the operator, for example a
named macOS launchd/Terminal service owner responsible for one
`php artisan schedule:work` process. No process manager may run a second
cron/`schedule:run` path. The scheduler wrapper must retrieve the backup
credential from Keychain in its controlled runtime environment; direct
non-interactive Keychain access is not assumed safe without an unlocked
keychain and reviewed ACL.

Required monitoring owner actions include checking per-task logs under
`storage/logs/automation-*.log`, Laravel errors, `scheduled_task_runs`,
`jobs`, `failed_jobs`, backup age/completion, API/ML/mail failures, and disk
capacity. On failure, stop the single scheduler and queue worker, preserve the
MariaDB write delta, record affected task windows, and reconcile those writes
before any rollback to another database. No scheduled task was executed during
this readiness audit.

**Exact next action:** assign monitoring and rollback owners, complete the
third-party process-manager check, and provide explicit operating approval.
Only then may one scheduler process be started.
