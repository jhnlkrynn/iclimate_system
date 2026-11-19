# iClimate Production Cutover Readiness Audit

Audit date: 2026-11-05  
Migration: Supabase PostgreSQL to XAMPP MariaDB 10.4.28  
Protected staging database: `iclimate_staging_20261103_fix3`

## A. Executive summary

Decision: **CONDITIONAL FAIL — do not perform production cutover yet.**

The verified staging maintenance run established a strong local target and backup baseline:

- MariaDB 10.4.28 was reachable and the protected staging database contained 69 tables, 877 users, 87 foreign keys, and 107,274 application rows.
- The prepared physical backup passed checkpoint, metadata, and tablespace-completeness validation.
- A complete logical dump was created and restored into a uniquely named disposable database.
- The disposable restore contained 69 tables, 107,274 application rows, 877 users, 69 primary keys, 87 foreign keys, and all three timestamp triggers.
- Infrastructure tests passed: 80 tests and 352 assertions.
- The previously reported full Laravel baseline was 1,647 passed, 12,407 assertions, with no failures.

The mandatory production gates below are still open:

1. A fresh, read-only comparison against the current Supabase source has not been re-run in this audit. Historical snapshot equality is not proof that the source has not changed.
2. The physical backup was successfully booted and checked in a separate isolated MariaDB instance. Physical recovery is now **PASS**.
3. The actual deployed Laravel hosting environment has not been tested from this workspace.
4. The configured application path uses local root-style database defaults in `.env.example`; production must use a dedicated least-privilege account with a non-empty secret.
5. Cutover requires a final source write freeze, final synchronization, reconciliation, approval, and an explicit rollback decision window.
6. The current read-only Supabase probe was attempted during this audit and returned the application's redacted database-connection failure. No source comparison was executed.
7. The migration source configuration guard has been hardened: source settings no longer fall back to active `DB_*` values. The explicit source variables are now present, but the configured direct Supabase-style hostname has no DNS A or AAAA result; TCP IPv4/IPv6 and `pg_isready` also fail. The source comparison remains blocked until the actual project hostname or approved Session pooler hostname is obtained from Supabase.

No Supabase data, protected staging records, production `.env`, or production database was modified by this audit.

## B. Verification matrix

| Gate | Status | Evidence / remaining action |
|---|---|---|
| MariaDB version and connectivity | PASS (historical run) | Maintenance report records MariaDB 10.4.28 and port 3306 connectivity. Recheck immediately before cutover. |
| Protected staging table count | PASS (historical run) | 69 tables in `iclimate_staging_20261103_fix3`. |
| Protected staging users | PASS (historical run) | 877 users. |
| Protected staging application rows | PASS (historical run) | 107,274 rows using the migration row-total definition. |
| Protected staging foreign keys | PASS (historical run) | 87 foreign keys. |
| Physical backup creation | PASS | `/Users/Shared/iclimate_mariadb_backups/physical-20261105-055451-8085`. Backup and prepare completed with open-file limit 65,536. |
| Physical checkpoint metadata | PASS | `backup_type = log-applied`, `from_lsn = 0`, `to_lsn = 2484836930`, `last_lsn = 2484836939`. |
| Physical tablespace completeness | PASS (script validation) | Source and backup `.ibd` inventories were compared; the earlier OS-error-24 backup was rejected. |
| Physical isolated recovery | PASS | Recovery directory `/Users/Shared/iclimate_mariadb_recovery/recovery-20261105-072151-15996`; MariaDB 10.4.28 started with the prepared log-applied, non-partial, non-incremental backup, verified 69 tables, 877 users, 107,274 application rows, 87 foreign keys, passed integrity checks, and shut down cleanly. |
| Logical backup | PASS | `/Users/Shared/iclimate_mariadb_backups/logical-20261105-060150-10639.sql`, 35,593,990 bytes. |
| Logical disposable restore | PASS | `iclimate_restore_probe_20261105_060150_10639`. |
| Restored table/row/key counts | PASS | 69 tables, 107,274 application rows, 877 users, 69 primary keys, 87 foreign keys. |
| Timestamp triggers | PASS | Three triggers verified on the disposable target. |
| Stored routines/events | PASS for observed target | Restore report recorded routines=0 and events=0, matching the source target at audit time. |
| Scheduler | PASS for maintenance run | `event_scheduler=ON`, with zero existing events. Recheck after any restart. |
| System health compatibility | PASS (historical) | Infrastructure suite: 80 passed, 352 assertions. Health checks use `information_schema`, avoiding unsupported MariaDB 10.4 `performance_schema` queries. |
| Full Laravel suite | HISTORICAL PASS | Reported baseline: 1,647 passed, 12,407 assertions. Not re-run as part of this report-only audit. |
| Current Supabase-to-target reconciliation | BLOCKED | Explicit source variables are configured without exposing values. `--connect-only` failed; the configured direct-style host returned DNS A=FAIL and AAAA=FAIL, with TCP IPv4/IPv6 and `pg_isready` also failing. No rows were read or written and `--compare` was not run. |
| Supabase Auth | NOT VERIFIED | Repository review shows Laravel password-based authentication over the `users` table; current Supabase Auth provider metadata, identities, recovery flows, and MFA state remain unverified. |
| Supabase Storage | NOT VERIFIED | Repository review shows Laravel local/S3 filesystem configuration and no confirmed Supabase Storage SDK usage. Bucket/object parity still requires source access and an object inventory. |
| Supabase Realtime/RLS/policies | NOT VERIFIED | No confirmed Realtime client or Supabase SDK usage was found in the repository search; PostgreSQL publications, RLS policies, and roles still require source metadata access before being declared unused. |
| Deployed hosting connectivity | NOT VERIFIED | Test from the actual deployed Laravel host, not only local XAMPP. |
| Production cutover approval | BLOCKED | Explicit stakeholder approval is required; no cutover was performed. |

## C. Database integrity report

### Migration implementation

The guarded migration path is implemented by `App\\Services\\SupabaseToMysqlMigrationService` and `iclimate:migrate-supabase`:

- Source and target connections are separate and configured through `MIGRATION_SOURCE_DB_*` and `MIGRATION_TARGET_DB_*` variables.
- `--connect-only`, `--audit-only`, and `--compare` are read-only modes.
- Migration refuses a non-local target and refuses a target that is not fresh.
- Source rows are copied in batches with primary-key values preserved.
- Foreign-key checks are disabled only for the guarded import transaction window and re-enabled in `finally` cleanup.
- Framework runtime tables (`cache`, queue, migration, and session tables) are intentionally excluded from application-data transfer.
- Deterministic comparison uses shared primary keys and canonical row hashes, normalizing timestamps, JSON, booleans, NULLs, and compatible type differences.

### Observed target integrity

The maintenance report confirms the target-side counts above and a zero-orphan result from the previously completed migration audit. The disposable logical restore reproduced the key counts and relationships without changing protected staging.

The three PostgreSQL timestamp behaviors are represented by Laravel migration `2026_11_03_000005_create_supabase_timestamp_equivalent_triggers.php`:

- `external_weather_data` → `fetched_at`
- `monthly_weather_predictions` → `generated_at`
- `typhoon_safety_responses` → `responded_at`

The disposable restore showed all three `BEFORE UPDATE` triggers.

### Remaining reconciliation work

The current-source comparison must still be run after Supabase connectivity is confirmed. It must report, per table, source-only keys, target-only keys, changed hashes, unsupported tables, and a source snapshot timestamp. It must not call a verification-record write path.

The current probe was attempted with:

```bash
php artisan iclimate:migrate-supabase --connect-only
```

It failed before any inventory query with the application's credential-safe connection error. The exact provider exception was intentionally not printed because it could contain host or connection details. A source observation time was therefore recorded by the audit run, but no consistent source snapshot exists.

Additional non-secret diagnostics:

- Explicit `MIGRATION_SOURCE_DB_HOST`, port, database, username, password, and SSL mode were present; the optional connection and schema variables used safe defaults (`pgsql` and `public`).
- The configured host matched a direct Supabase-style hostname pattern, but DNS A and AAAA resolution both failed.
- TCP connectivity to the effective PostgreSQL port failed over both IPv4 and IPv6.
- `/opt/homebrew/opt/libpq/bin/pg_isready`, `pg_dump`, and `pg_restore` were available (PostgreSQL 18.6).
- `pg_isready` failed before authentication. SSL mode was configured. No credential reset or configuration mutation was attempted.

The migration safety fix now requires the four source identity fields explicitly and leaves the active application connection untouched. Validation result: PHP syntax checks passed and `--connect-only` returned the non-secret configuration error before opening a database connection.

Also verify before cutover:

- unique indexes and nullable columns against the source catalog;
- JSON canonicalization for all JSON-bearing columns;
- auto-increment values greater than each imported maximum ID;
- timezone/precision behavior for all domain timestamps;
- cascade behavior for destructive operations in a disposable clone;
- current critical-record counts for farmers, farms, schedules, predictions, reports, notifications, and chatbot data.

## D. Backup and recovery report

### Physical backup

The first backup attempt was rejected because MariaDB inherited a 256-file descriptor limit and logged OS error 24 (“Too many open files”). The maintenance script was corrected to run XAMPP `mariabackup` as `_mysql` with an explicit 65,536 open-file limit and to compare source and backup tablespace inventories.

The accepted backup is:

`/Users/Shared/iclimate_mariadb_backups/physical-20261105-055451-8085`

It has valid prepared checkpoint metadata and was not overwritten. It was recovered in isolation at:

`/Users/Shared/iclimate_mariadb_recovery/recovery-20261105-072151-15996`

The isolated MariaDB 10.4.28 instance started successfully, loaded 69 tables, 877 users, 107,274 application rows, and 87 foreign keys, passed the integrity check, and shut down cleanly. The original backup remained preserved.

The current workspace can see `/Users/Shared` and the backup parent, but the parent is mode `0700` and owned by `_mysql:admin`. The accepted child directory and checkpoint files are not readable as the workspace user. This is a filesystem permission boundary; it is not valid evidence that the backup was deleted. Administrator inspection is required.

The recovery harness is [verify-isolated-mariadb-backup.sh](../scripts/verify-isolated-mariadb-backup.sh). It refused the active XAMPP datadir, copied into a unique recovery directory, used `--no-defaults` and `--skip-networking`, and preserved the recovery directory. Its authorized execution produced the PASS evidence above.

### Logical backup

The accepted logical backup is:

`/Users/Shared/iclimate_mariadb_backups/logical-20261105-060150-10639.sql`

It was created with XAMPP-compatible `mysqldump` including tables, data, triggers, routines, and events, and restored to:

`iclimate_restore_probe_20261105_060150_10639`

The restore passed the table, row, key, routine/event, and trigger checks listed in the matrix.

### Recovery and rollback requirements

Before cutover, retain at least:

1. the verified Supabase PostgreSQL backup;
2. the accepted physical MariaDB backup;
3. the accepted logical MariaDB dump;
4. a final post-synchronization logical dump;
5. SHA-256 manifests stored separately from the backup data.

The physical restore procedure must use a stopped disposable MariaDB instance with a dedicated data directory and non-production port. Never copy prepared files over `/Applications/XAMPP/xamppfiles/var/mysql` while MariaDB is running.

Rollback after new writes is not a simple `.env` switch: writes made to MariaDB after cutover must either be reconciled back to Supabase or accepted as lost. The safest rollback point is before unfreezing writes. After writes begin, freeze the application, export the write delta, decide whether to replay it into the prior system, then switch configuration only after reconciliation.

## E. Security findings

### High priority

- The application and migration examples use `root` with an empty password as local defaults. This is acceptable only for isolated local development. Production must use a dedicated MariaDB account with only runtime application privileges, a strong rotated secret, and no administrative DDL/system-table access.
- The previously exposed database password must remain treated as compromised. It must not be reused in deployment, shell history, reports, or backup filenames.
- Backup directories contain full database exports and must remain outside public web roots with owner-only or service-account-only permissions.

### Medium priority

- Supabase Auth, Storage, Realtime, RLS policies, database functions, and triggers require an explicit dependency inventory. Application-table parity alone does not migrate those services.
- Queue, cache, sessions, scheduler, and file storage need production-specific durable services and monitoring; local XAMPP behavior is not sufficient evidence.
- Maintain the isolated recovery directory and repeat restore drills according to the production backup schedule.

### Positive controls observed

- Migration errors redact connection details before console output.
- Backup verification supports a non-mutating mode (`--no-record`).
- The maintenance workflow does not print credentials and does not modify Supabase.
- The application health checks avoid leaking raw connection errors and avoid the incompatible MariaDB `performance_schema` query path.

## F. Application compatibility report

The codebase contains an explicit PostgreSQL migration connection and a separate MariaDB target connection. The migration service uses portable information-schema queries for inventory and target schema checks. The application health service uses `information_schema.tables` for table counts on MariaDB.

### Supabase dependency inventory

Repository inspection found no confirmed Supabase client package, Realtime subscription, Storage SDK call, or direct `supabase.co` application URL. The application uses Laravel authentication and database tables, Laravel local/S3 filesystem configuration, database-backed queues/cache/sessions, and external weather, PAGASA, GeoRisk, map-tile, mail, and optional Groq/ML services. This is an application-code finding, not proof that the live Supabase project has no policies, functions, Storage objects, Auth identities, or Realtime publications; those require authorized source metadata access.

Remaining dependencies requiring deployment review:

- Supabase-specific configuration and URLs remain in the repository for source connectivity and must not be removed until the final dependency audit is complete.
- Authentication behavior must be verified against the chosen production identity implementation; imported `users` rows alone do not prove password-reset, email-verification, provider, or MFA behavior.
- File uploads use Laravel filesystem configuration; verify the production disk, object permissions, retention, and backup policy.
- Queues and scheduler require an always-on worker and scheduler process outside a developer XAMPP session.
- ML/Python runtime files and external weather/API credentials require host-level deployment verification.

No unrelated application refactoring was made for this audit.

## G. Deployment readiness

Local XAMPP is suitable as a staging/verification environment, not proof of production hosting readiness. The deployed host must independently satisfy:

- supported PHP version and required extensions (`pdo_mysql`, database driver, mbstring, XML, cURL, fileinfo, and any ML/runtime extensions);
- Composer installation with optimized autoloading and a reproducible frontend build;
- MariaDB version and SQL mode compatible with the tested schema;
- HTTPS, secure cookies, trusted proxy configuration, and enforced TLS;
- dedicated database, cache, session, queue, and file-storage services;
- queue workers, scheduler, log rotation, monitoring, alerting, and restart policy;
- automated encrypted backups with restore drills;
- non-root database credentials and production secret injection outside source control.

## H. Testing results

Evidence available for this audit:

- Current Supabase connectivity probe: **BLOCKED** with the redacted application connection failure; no source data was touched.
- Infrastructure tests: **80 passed, 352 assertions**.
- Previously reported full MySQL suite: **1,647 passed, 12,407 assertions**.
- Disposable restore: **PASS** for 69 tables, 107,274 application rows, 877 users, 69 primary keys, 87 foreign keys, 3 triggers, 0 routines, and 0 events.
- The full suite was not re-run for this report-only audit because no application code was changed and the verified baseline was supplied as historical evidence.

Warnings seen in the maintenance log include deprecated PHPUnit doc-comment metadata and historical MariaDB system-table warnings. They are not production cutover approvals; address them in routine maintenance.

## I. Remaining issues

### Critical — must resolve before cutover

1. Repair or authorize the effective Supabase hostname/network path, then run and archive a current read-only Supabase-to-target row-hash comparison.
2. Reconcile all source changes after the original migration snapshot.
3. Verify deployed Laravel-host connectivity and all production services.
4. Replace root/blank-password defaults with a dedicated least-privilege production account and rotated secret.
5. Obtain explicit cutover approval and define the rollback owner and decision deadline.

### High — resolve before or during the cutover window

1. Inventory and replace any required Supabase Auth, Storage, Realtime, policy, function, trigger, and webhook dependencies.
2. Verify object-storage migration and file access independently of database row counts.
3. Perform final synchronization under a source write freeze.
4. Execute smoke tests for authentication, farmer workflows, MAO workflows, reports, predictions, CRUD, queues, scheduler, and file uploads.

### Medium

1. Remove deprecated PHPUnit doc-comment metadata in a planned compatibility change.
2. Document backup retention, RPO/RTO, and a tested restore schedule.
3. Add monitoring for scheduler freshness, queue backlog, database latency, backup age, and failed jobs.

## J. Approval-gated cutover runbook

Do not execute these commands until the critical gates above are PASS and the owner explicitly approves the maintenance window.

1. Announce the window and assign an operator, verifier, rollback owner, and business approver.
2. Confirm current source and target backups, hashes, free disk space, credentials, and health dashboards.
3. Disable new writes at the application boundary and stop queue consumers that can write business data. Keep read-only access if safe.
4. Create a final Supabase backup and record its timestamp and checksum.
5. Run the read-only source inventory and deterministic row-hash comparison against the target.
6. Import only the verified final delta into an isolated target using the existing guarded migration service. Do not overwrite records; stop on conflicts.
7. Re-run counts, foreign-key orphan checks, critical-record checks, and application smoke tests.
8. Create a final MariaDB logical backup and retain the pre-cutover physical backup.
9. Deploy production configuration with the dedicated least-privilege MariaDB credentials. Do not commit secrets or edit the repository `.env` as part of this audit.
10. Run migrations only if reviewed and approved; do not run seeders against production.
11. Start workers and scheduler, then verify cache, sessions, queues, file storage, HTTPS, authentication, and critical CRUD flows.
12. Monitor errors, latency, queue depth, scheduler freshness, and database connections throughout the decision window.
13. If critical acceptance criteria fail before new writes, restore the prior configuration and resume the source system. If writes have begun, freeze again and reconcile the write delta before rollback.

Acceptance requires: zero unexplained row-hash differences, successful current backup and restore evidence, all critical smoke tests passing, stable monitoring, and written approval. This report does not authorize cutover.

## K. Final production cutover audit update — 2026-11-06

### Final status

| Area | Decision | Evidence / limitation |
|---|---|---|
| Database migration integrity | PASS | 69/69 tables reconciled; 87/87 MariaDB foreign keys found and audited; zero orphan records; no confirmed migration defects. |
| Application compatibility | UNVERIFIED for production; PASS for the local wrapper profile | A read-only Laravel probe resolved `DB_CONNECTION=mysql` to `iclimate_staging_20261103_fix3` and read 877 users. Existing local MariaDB and browser/test evidence is positive, but the deployed host was not tested. |
| Supabase dependency status | BLOCKED / UNVERIFIED | No confirmed Supabase SDK, frontend client, Storage SDK, Realtime subscription, or direct Supabase application URL was found. However, the active `.env` still points the normal Laravel connection at Supabase PostgreSQL, and current live Supabase-only data/services cannot be checked while source access is unavailable. |
| Backup and recovery readiness | PASS with operational follow-up | Physical and logical MariaDB backups were previously restored successfully in disposable environments. Retention, off-host storage, RPO/RTO, and scheduled repeat drills remain deployment requirements. |
| Business-owner acceptance | PENDING | Refreshed weather, prediction, advisory, and derived-risk records are expected post-backup changes but still require written acceptance. |
| Production cutover decision | **NO-GO** | Production host, credentials, current-source comparison, Supabase-managed services, and rollback ownership remain open. |

### Configuration and dependency findings

The local launch wrapper correctly injects MariaDB settings for web, queue, and
scheduler processes. The active repository environment, however, currently has
`DB_CONNECTION=pgsql` and a Supabase PostgreSQL host. Therefore a plain
`php artisan` process is not independent of Supabase; it is local-only only when
the MariaDB environment is explicitly injected or the deployment environment is
changed under an approved cutover.

The remaining PostgreSQL/Supabase references are isolated migration and backup
paths, PostgreSQL compatibility branches, documentation, and historical audit
tooling. They are not proof of a runtime SDK dependency. The following still
require source or deployment verification before retirement:

- current Supabase-only writes after the dated backup;
- Auth provider identities, recovery, email verification, and MFA state;
- Storage objects, bucket permissions, and upload parity;
- Realtime publications/subscriptions, RLS policies, roles, functions, triggers,
  and webhooks;
- production queue workers, scheduler, mail, file storage, ML service, HTTPS,
  monitoring, and restart policy.

### Required actions before changing the decision to GO

1. Set the approved production runtime to MariaDB with a dedicated,
   least-privilege, non-root account and an out-of-band secret; do not use the
   current Supabase `.env` values for local cutover.
2. Obtain authorized read-only Supabase metadata/current-source access, create a
   final source backup, and run one final consistent comparison for newer data.
3. Inventory or explicitly retire Supabase Auth, Storage, Realtime, policies,
   functions, triggers, and webhooks.
4. Validate login, registration, CRUD, reports, analytics, notifications,
   queues, scheduled jobs, uploads, and rollback on the actual deployment host.
5. Obtain written business-owner acceptance and assign the cutover and rollback
   owners with an explicit decision window.

Until these conditions are complete, the migration is data-integrity approved
for the verified local target but is not approved for production cutover or
Supabase retirement.

## M. Controlled local MariaDB activation audit — 2026-11-06

The intended local database was verified read-only: MariaDB 10.4.28 is
reachable, `iclimate_staging_20261103_fix3` contains 69 tables and 87 foreign
keys, and no records were changed. The requested dedicated runtime account is
not yet provisioned:

- `mysql.user` contains no `iclimate_app` account.
- No database-grant row exists for `iclimate_app` on
  `iclimate_staging_20261103_fix3`.
- The active `.env` was not changed and still resolves ordinary Laravel
  processes to PostgreSQL/Supabase.
- No configuration cache file is present.
- Existing XAMPP/web/scheduler processes were not stopped because activation was
  not approved.

**Local activation readiness: FAIL/BLOCKED.** Provision the reviewed
least-privilege account out of band, then follow the approval-gated sequence in
`LOCAL_XAMPP_MARIADB_SETUP.md`. This audit did not create the account, change
grants, edit `.env`, or stop processes.

## L. Supabase-independent Laravel configuration audit — 2026-11-06

| Check | Result |
|---|---|
| Intended MariaDB settings identified | PASS |
| Active `.env` already MariaDB-independent | FAIL — it currently resolves `DB_CONNECTION=pgsql` to a Supabase PostgreSQL host |
| Local wrapper MariaDB resolution | PASS — `mysql/mysql/iclimate_staging_20261103_fix3`, 877 users read-only |
| Config cache fallback | PASS — `bootstrap/cache/config.php` was absent |
| CLI configuration | PASS when explicit MariaDB environment is injected |
| Queue/scheduler configuration | PASS locally when explicit MariaDB environment is injected; scheduler registration inspected |
| Web process configuration | PASS by wrapper inspection; deployed web host unverified |
| Hidden application PostgreSQL fallback | None found in runtime application queries; PostgreSQL remains in migration/tooling and compatibility paths |

No `.env`, configuration cache, database record, credential, or production
deployment was changed. The exact manual activation and rollback procedure is
documented in [LOCAL_XAMPP_MARIADB_SETUP.md](LOCAL_XAMPP_MARIADB_SETUP.md).

The application can operate locally without Supabase when launched through the
MariaDB wrapper or an approved environment containing `DB_CONNECTION=mysql`,
the target MariaDB host/database, and a dedicated runtime account. It cannot
yet be called Supabase-independent by default because an ordinary Laravel
process still reads the active PostgreSQL/Supabase `DB_*` settings.

### Updated decision

The production decision remains **NO-GO**. Before changing it to GO, manually
apply and verify the MariaDB deployment environment, use a least-privilege
non-root account, validate configuration caching and worker restarts, perform a
current-source comparison if authorized, verify Supabase-managed services and
newer data, and obtain business-owner and rollback-owner approval.

### Application-account privilege audit

Read-only target inspection found 69 base tables and zero views, routines,
triggers, or events. Runtime source inspection found no application use of
stored procedures, `LOAD DATA`, table locks, or dynamic DDL. The prepared
runtime account therefore needs only `SELECT`, `INSERT`, `UPDATE`, and `DELETE`
on the application database. Schema changes, backup/restore, and
administrative inspection remain separate-account responsibilities.

The non-executed provisioning and verification SQL is documented in
`LOCAL_XAMPP_MARIADB_SETUP.md`. At the time of that pre-provisioning audit the
account was absent; the subsequent approved provisioning result is recorded
below. Local activation still requires the separate environment switch.

### Provisioning result

The explicitly approved local provisioning completed on 2026-11-06. The
`iclimate_app` account is restricted to `127.0.0.1` and the target database,
has exactly `SELECT, INSERT, UPDATE, DELETE` schema privileges, and has zero
non-`USAGE` global privileges. A Laravel read-only probe connected as that
account and read 877 users. The FK audit also passed as that account: 87/87
relationships and zero orphans.

The password is stored in macOS Keychain and is not present in source, logs, or
this report. The active `.env` was not changed, so ordinary Laravel processes
still require the separately approved MariaDB environment activation.

### Controlled local activation result

The approved local activation was applied on 2026-11-06. The original
Supabase-oriented `.env` was preserved in a mode-600 backup, and the active
settings now resolve normal Laravel CLI and web requests to
`mysql/mysql/iclimate_staging_20261103_fix3`. Read-only verification returned
877 users, 69 tables, 87 foreign keys, and zero orphans; the local web probe
returned HTTP 200.

Queue configuration is `database`; session storage is `database`; filesystem
storage is `local`. Scheduler registration passed, but the scheduler process
was intentionally left stopped after a short smoke test so no write-capable
scheduled task would run during verification. This makes local activation
**INCOMPLETE for continuous operations**, not a database-integrity failure.

Production remains **NO-GO** pending production environment provisioning,
current-source verification, Supabase-managed service inventory,
business-owner acceptance, and an approved queue/scheduler operating policy.

## N. Laravel scheduler safety audit — 2026-11-06

The scheduler registry contains 17 tasks covering weather refresh, risk maps,
dashboard/cache warming, PAGASA advisories, predictions, queue cleanup,
backups, backup verification, and scheduled alerts. Static inspection found no
direct Supabase SDK, PostgreSQL connection, Supabase Auth/Storage/Realtime API,
or Supabase URL in scheduled application code. All scheduled Laravel database
work follows the default connection and is therefore MariaDB-compatible at the
query layer.

The tasks are not all read-only. Several write business or derived data:
weather and risk tables, advisories, notifications, predictions, analytics
runs, queue rows, and scheduled-task metadata. `clean-stale-queue` also deletes
selected stale runtime jobs after writing a file backup. These are expected
operational writers, not safe verification commands.

The dedicated `iclimate_app` account is sufficient for scheduled DML. The
03:15 `iclimate:backup-database` task is an intentional exception: `mysqldump`
with triggers/routines/events requires a separate reviewed backup/administrator
identity and must not receive those privileges through the application account.

All 17 tasks use overlap protection. Only one scheduler mechanism may be
enabled: a single `schedule:work` process or one cron/Task Scheduler entry.
Repository deployment files document alternatives, but the host crontab could
not be inspected from this session (`crontab -l` was denied by macOS). The
controlled local scheduler remains stopped, and no scheduled task or queue job
was run during this audit.

### Scheduler decision

**Scheduler activation: BLOCKED** until:

1. An operator confirms there is exactly one host scheduler and no duplicate
   cron, Task Scheduler, Supervisor, or `schedule:work` process.
2. A separate backup/admin credential is configured for the backup task.
3. Python ML, Open-Meteo/weather, PAGASA, GeoRisk, mail, filesystem, and backup
   dependencies have owners and monitoring.
4. The operator approves the expected writes to derived/business tables and
   queue cleanup behavior.
5. Monitoring and rollback owners are assigned, including preservation and
   reconciliation of MariaDB writes made after activation.

### Evidence from the final read-only scheduler audit

At inspection time no Laravel scheduler, queue worker, Apache/PHP scheduler,
or matching process-manager process was visible. Accessible user LaunchAgents
contained no iClimate entry. This does not clear system LaunchDaemons, other
users' agents, or cron: macOS denied `crontab -l` with
`zsh: operation not permitted: crontab`. An authorized administrator must run
the host-level `launchctl`, `crontab`, and process checks documented in
`docs/LOCAL_XAMPP_MARIADB_SETUP.md` before activation.

The backup task was also confirmed to use `mysqldump` with triggers and,
by default, routines and events. The `iclimate_app` account's DML-only grants
are intentionally insufficient for that operation. A separate least-privilege
backup identity and named Laravel backup connection remain required; no
account or grant changes were made during this audit.

Accordingly the production cutover remains **NO-GO**. Scheduler activation is
**BLOCKED**, and no scheduled command, queue worker, or database backup was
executed as part of this audit.

### Scheduler preparation update — 2026-11-06

The operator completed the previously restricted macOS checks: no scheduler
process, personal cron, root cron, or matching iClimate LaunchAgent/Daemon was
found; `/usr/lib/cron/tabs` is empty and `/etc/crontab` does not exist. These
checks cover the inspected cron and launchd paths. Third-party process
managers remain an owner-controlled verification item.

The implementation review confirms the backup command uses `mysqldump` with
`--single-transaction`, `--triggers`, `--no-tablespaces`, and optional
`--routines`/`--events`; the password is passed through `MYSQL_PWD`. The
approved design is a separate `iclimate_backup` account restricted to
`127.0.0.1` and `iclimate_staging_20261103_fix3`, with proposed schema grants
`SELECT, SHOW VIEW, TRIGGER, EVENT`. No account, grant, environment, code, or
database changes have been made.

Laravel already supports selecting a named backup connection through
`BACKUP_DB_CONNECTION`; the exact `iclimate_backup` connection and Keychain
runtime-injection procedure are documented in the local setup guide but await
explicit approval. No backup or verification command was executed.

**Scheduler activation remains BLOCKED.** The required next checkpoint is
`APPROVE ICLIMATE_BACKUP_ACCOUNT PROVISIONING`; later approvals are required
for Laravel configuration changes and the first backup execution.

That approval was subsequently received, but the provisioning attempt was
blocked because XAMPP MariaDB was stopped and this session could not satisfy
the interactive `sudo` prompt. No account, grant, Keychain entry, Laravel
configuration, or backup was changed. An authorized operator must first run
`bash scripts/start-iclimate-local.sh check` from an interactive Terminal;
backup-account provisioning and verification can then resume.

The approved provisioning subsequently completed after MariaDB was started.
`iclimate_backup` is restricted to `127.0.0.1` and the staging database, with
only `EVENT`, `SELECT`, `SHOW VIEW`, and `TRIGGER` schema privileges and zero
non-`USAGE` global privileges. Its credential is stored in macOS Keychain and
was rotated before final verification. Read-only verification passed for
MariaDB 10.4.28, the target database, 877 users, 69 tables, and 87 foreign
keys. No backup, scheduler, queue worker, Laravel configuration, or business
data operation was executed.

### Dedicated Laravel backup connection preparation — 2026-11-06

Read-only inspection confirmed that `DatabaseBackupService` already supports
`BACKUP_DB_CONNECTION` and passes the selected password to `mysqldump` through
`MYSQL_PWD`. The proposed change is limited to adding a named `mysql_backup`
connection using `iclimate_backup`, then setting `BACKUP_DB_CONNECTION` in the
protected runtime environment. The default `mysql` connection and
`iclimate_app` remain unchanged.

The proposed connection, Keychain wrapper, cache procedure, read-only probes,
first-backup approval checkpoint, and rollback procedure are documented in
`docs/LOCAL_XAMPP_MARIADB_SETUP.md`. None has been applied. Direct Keychain
access from a non-interactive scheduler is not assumed; a reviewed wrapper or
backup-only process must provide the secret at runtime without placing it in
`.env`, Git, logs, or command-line arguments.

Named backup connection: **BLOCKED pending explicit approval**. First backup
test: **PENDING**. Scheduler activation: **BLOCKED**. Production cutover:
**NO-GO**.

The explicit approval `APPROVE ICLIMATE NAMED BACKUP CONNECTION` was then
received. The minimal `mysql_backup` connection was added, and the active
environment selects it through `BACKUP_DB_CONNECTION=mysql_backup`; the default
`mysql` connection remains assigned to `iclimate_app`. A Keychain-injected,
read-only Laravel probe reached the staging database and read 877 users.
Configuration cache was cleared. Backup regression tests passed in-memory
(50 tests, 214 assertions). No database backup, scheduler, queue worker, or
business-data operation was executed. The first backup test remains pending
separate approval, and production remains **NO-GO**.

The explicit approval `APPROVE ICLIMATE FIRST BACKUP TEST` was subsequently
received. A single 36.24 MB dump was created with `--no-prune` in a new mode-700
directory; the file was mode 600 and ended with the mysqldump completion marker.
Laravel verified it successfully with `iclimate:verify-backup --no-record`,
reporting 69 archive entries without writing a verification row. No existing
backup, database record, scheduler, or queue worker was modified. The first
backup test is **PASS**; scheduler activation remains **BLOCKED** pending its
separate operating approval.

### Final scheduler operating readiness — authoritative current status

The current decision is **BLOCKED**. The 17 registered tasks include
write-capable weather/risk refreshes, analytics and prediction queueing,
derived prediction/yield writes, stale-job cleanup, scheduled notifications,
backup-file creation/pruning, and backup-verification metadata unless
`--no-record` is used. All are MariaDB-compatible at the query layer and use
overlap protection, but overlap protection does not replace single-process
ownership.

| Item | Result |
|---|---|
| Monitoring owner | **PENDING** |
| Rollback owner | **PENDING** |
| Duplicate scheduler risk | **UNVERIFIED** for third-party process managers; cron/launchd checks passed |
| Backup integration | **PASS**; dedicated connection and first backup verified |
| Explicit operating approval | **PENDING** |
| Scheduler activation | **BLOCKED** |

Before activation, a named operator must own one `php artisan schedule:work`
process, monitoring, incident response, and rollback/write-delta reconciliation.
The Keychain-based backup secret must be supplied through a reviewed wrapper
with appropriate keychain unlock/ACL behavior; it must not be placed in `.env`,
Git, logs, or command-line arguments. No scheduled task or queue worker was
executed during this audit.

**Exact next action:** assign the monitoring and rollback owners, finish the
third-party process-manager check, and obtain explicit operating approval.
Production cutover remains **NO-GO**.
