# iClimate Remaining Production-Readiness Actions

Status: cutover remains blocked. No production cutover, Supabase mutation, protected-staging mutation, or production `.env` change is authorized by this document.

## P0 — required before approval

Physical-backup recovery is complete and removed from the P0 list. Verified recovery evidence: `/Users/Shared/iclimate_mariadb_recovery/recovery-20261105-072151-15996`; MariaDB 10.4.28; 69 tables; 877 users; 107,274 application rows; 87 foreign keys; integrity check PASS; clean shutdown PASS.

### 1. Restore Supabase connectivity and reconcile current data

Dependency: authorized access to the current Supabase PostgreSQL source and the protected MySQL staging target.

Run the harmless probe first:

```bash
cd /Users/johnlukereynon/iclimate_system
php artisan iclimate:migrate-supabase --connect-only
```

After the probe passes, run the existing read-only comparison with a report path outside public storage:

```bash
php artisan iclimate:migrate-supabase --compare --report=storage/app/migration-audits/supabase-current-$(date -u +%Y%m%dT%H%M%SZ).json
```

Record the source observation time, whether the source was held in a consistent transaction snapshot, all missing/extra/changed primary keys, and any unsupported tables. Do not use `--migrate` or `--confirm` during this action.

Current result: **BLOCKED**. Explicit `MIGRATION_SOURCE_DB_HOST`, port, database, username, password, and SSL mode are configured without exposing values. The direct Supabase-style hostname has no DNS A or AAAA result; TCP IPv4/IPv6 and `pg_isready` fail. This is not an IPv6-only problem. Do not invent a pooler hostname: obtain the exact project direct or Session pooler connection details from the Supabase dashboard. No source comparison ran.

Safe code change completed: `config/migration.php` and `MigrateSupabaseToMysql` now require explicit source settings and preserve the active application connection. No `.env` value was changed.

### 2. Replace root-style production database access

Dependency: production database administrator approval.

Prepare, review, and execute only on the final production MariaDB server:

```sql
CREATE USER 'iclimate_app'@'<application-host>' IDENTIFIED BY '<generated-secret-held-out-of-band>';
GRANT SELECT, INSERT, UPDATE, DELETE
ON `iclimate_production`.* TO 'iclimate_app'@'<application-host>';
FLUSH PRIVILEGES;
```

Do not use the literal placeholders. Generate the secret in the deployment secret manager, inject it into the runtime environment, test the application connection, and rotate it under the organization’s credential policy. Restrict the account host more narrowly than `%` wherever possible. Repository review found no stored-procedure calls, `LOAD DATA`, or explicit temporary-table/lock-table statements in runtime code. Add narrowly reviewed privileges only if a production smoke test proves they are required. Do not grant global privileges, `FILE`, `SUPER`, `CREATE`, `ALTER`, `DROP`, or system-schema access to the runtime account. Use a separate reviewed deployment/migration account for schema changes and backups.

Current result: **NOT EXECUTED**. No production account or credential was created.

### 3. Verify the actual production host

Dependency: access to the deployed Laravel host and hosting owner.

Verify PHP 8.2+, required extensions, MariaDB connectivity, web-server document root at `public/`, HTTPS, secure cookies, queue worker, scheduler, cache/session drivers, file storage, ML service, mail, monitoring, log rotation, and backup jobs. Local XAMPP results do not satisfy this gate.

## P1 — required before or during the approved maintenance window

### 4. Audit Supabase-managed dependencies

Dependency: read-only Supabase project/database metadata access.

Inventory Auth identities/providers/MFA, Storage buckets and object hashes, Realtime publications/subscriptions, RLS policies and grants, database functions/triggers, webhooks, and external integrations. Map each item to an iClimate feature and mark migrate, replace, or unused. Do not delete or disable any source service during the inventory.

### 5. Final synchronization and smoke tests

Dependency: current comparison PASS, final source backup, written approval, and a maintenance window.

Freeze source writes, create the final backup, run the deterministic comparison, import only an approved non-conflicting delta into the isolated target, and repeat counts/orphan checks. Then test login, authorization, farmer accounts, farms, planting schedules, predictions, reports, advisory flows, MAO CRUD, notifications, queues, scheduler, uploads, and rollback monitoring.

### 6. Maintain tested backup and recovery procedures

Dependency: P0 backup gates and the production backup schedule.

Retain the accepted physical backup, logical dump, Supabase backup, and independent checksums. The physical backup has already passed isolated recovery; repeat the physical and logical restore drills on the agreed schedule and record elapsed restore time and resulting RPO/RTO.

## P2 — operational hardening

- Remove root/blank-password defaults from deployment templates.
- Configure encrypted off-host backup retention and scheduled restore drills.
- Add monitoring for database latency, queue depth, scheduler freshness, backup age, failed jobs, disk capacity, and external-provider failures.
- Replace deprecated PHPUnit doc-comment metadata with attributes during routine maintenance.
- Document the selected hosting topology; current project documentation supports Windows XAMPP, Linux Apache/Nginx, and Hostinger VPS patterns but does not identify the final production host.

## Approval gates

Cutover requires written approval after all P0 items are PASS, the current source comparison has zero unexplained differences, a recovery test has passed, security credentials are provisioned, and the production smoke-test/rollback owners are assigned. No task in this file authorizes production cutover.

## Read-only status update — 2026-11-10

The active local snapshot is `0` pending jobs, `0` reserved jobs, and `15` historical failed jobs, with no queue worker running. ML, web, Reverb, and scheduler processes were observed under project-owned supervisors. The authenticated Reverb browser-origin probe passed twice in disposable environments.

New blocker requiring repair before sign-off: when the Reverb circuit is open, `app/Broadcasting/RetryBroadcastEvent.php` calls an undefined `release()` method. The focused test suite passes but does not cover that branch; the current Laravel log records the failure. No repair or live retry was performed.

Required owner decisions remain independent: authorize/decline the bounded live worker, choose a disposition for Run 2727, choose a disposition for Run 2728, and complete Supabase dashboard/external-consumer verification. Production readiness remains **NOT PRODUCTION READY** and Supabase retirement remains **BLOCKED — INSUFFICIENT EVIDENCE**.

## RetryBroadcastEvent repair — 2026-11-10

Root cause is **CONFIRMED**. `RetryBroadcastEvent` extends Laravel's `Illuminate\Broadcasting\BroadcastEvent`, which supplies queue metadata through `Queueable` but does not provide the runtime `release()` method. Laravel injects the active queue job only for jobs using `Illuminate\Queue\InteractsWithQueue`; the circuit-open branch therefore raised an undefined-method error instead of releasing the job.

Implemented fix: `RetryBroadcastEvent` now uses `InteractsWithQueue`. Existing freshness limits, configured tries/backoff, circuit breaker, queue cap, failed-job handling, and payload/event identity remain unchanged.

Verification:

- Circuit-open release contract: PASS.
- Real isolated `queue:work` release then Reverb-recovery flow: PASS.
- Retry exhaustion and failed-job recording: PASS.
- Reverb-available immediate delivery: PASS.
- Recovery delivery: PASS, exactly one event and one saved notification.
- Duplicate prevention: PASS in the isolated worker scenario.
- Focused broadcasting matrix: **27 passed, 139 assertions**.
- PHP lint and `git diff --check`: PASS.

No active MariaDB worker was started, no historical job was retried, and no live record was changed.

## Final regression and preflight update — 2026-11-10

The captured full Laravel suite completed in the isolated test environment:

- **1,733 passed, 5 skipped, 0 failed, 13,577 assertions**.
- Duration: 472.17 seconds.
- Existing PHPUnit doc-comment metadata deprecation warnings remain non-failing.
- The repaired broadcasting matrix remains **27 passed, 139 assertions**.

Read-only local preflight remains healthy: Laravel local, MySQL `iclimate_staging_20261103_fix3`, database queue, `0` pending, `0` reserved, and `15` historical failed jobs. No active queue worker was started. Production-host checks remain **UNVERIFIED** because this session has local-development access only.

## Hosting and deployment verification — 2026-11-10

Repository deployment documentation identifies an old Hostinger VPS as the
intended production target, but records that the Hostinger subscription was
not renewed and production was postponed. No SSH, hosting-panel, or
production-server access is available in this workspace. The actual current
runtime is local macOS/XAMPP MariaDB; production is therefore **UNVERIFIED /
NOT DEPLOYED**.

The local environment guard passed with MySQL `10.4.28-MariaDB`, session zone
`+08:00`, database `iclimate_staging_20261103_fix3`, and no Supabase database
target. The Hostinger verification script and local launcher passed shell
syntax checks. This does not verify production PHP, HTTPS, SSL renewal,
systemd units, deployed logs, monitoring, or off-host backups.
