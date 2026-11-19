# iClimate — Final Supabase Decommissioning Audit

Audit date: 2026-11-10  
Project: `/Users/johnlukereynon/iclimate_system`  
Target database: `iclimate_staging_20261103_fix3`

## Executive summary

**Decision: BLOCKED — INSUFFICIENT EVIDENCE.**

The local application is operating on MariaDB and the active runtime does not
require Supabase credentials or Supabase network access. The local database has
passed current table, migration, foreign-key, and orphan checks. All five local
services are healthy.

Cloud retirement cannot yet be approved because the live Supabase source could
not be queried during this audit. The guarded migration command refused to use
the local application `DB_*` settings as source credentials, and no explicit
`MIGRATION_SOURCE_DB_*` credentials were available. Therefore current-source
record comparison, Supabase Auth, Storage, Realtime, RLS, functions, webhooks,
and external-integration checks remain unverified.

No Supabase resource, MySQL record, backup, or production configuration was
modified.

## Current local MySQL verification

Read-only checks were run against the active local database.

| Check | Result |
|---|---:|
| Database | `iclimate_staging_20261103_fix3` |
| MariaDB | 10.4.28 |
| Tables | 69 |
| Foreign-key constraints | 87 |
| Foreign-key metadata rows audited | 87 |
| Orphan rows | 0 |
| Pending migrations | 0 |
| Users | 877 |
| Farms | 1,081 |
| Farmer profiles | 868 |
| Yield predictions | 6,498 |
| Notifications | 33 |
| Reports | 4 |
| System logs | 533 |
| Backup verifications | 41 |
| Preserved failed jobs | 15 |
| Session timezone | `+08:00` |

The important user, farm, profile, planting-schedule, and prediction
relationships are covered by the 87-constraint audit. No orphan rows were
found. The audit was read-only.

## Live Supabase source verification

**Status: BLOCKED.**

The command below was attempted:

```bash
php artisan iclimate:migrate-supabase --connect-only
```

It stopped before opening a source connection with the credential-safe result:

> Migration source configuration is incomplete. Set MIGRATION_SOURCE_DB_HOST, MIGRATION_SOURCE_DB_DATABASE, MIGRATION_SOURCE_DB_USERNAME, and MIGRATION_SOURCE_DB_PASSWORD explicitly; active DB_* values are not used.

Consequently, this audit did not claim a current-source comparison. No source
rows, schemas, or metadata were read.

### Historical snapshot comparison available

The existing dated reconciliation report compared 69 PostgreSQL tables with 69
MariaDB tables:

| Measure | Historical result |
|---|---:|
| Matching tables | 58 |
| Tables with differences | 11 |
| Missing target records | 8 |
| Extra target records | 3,547 |
| Changed records | 93 |
| Unverified tables | 0 |

The historical differences were classified as expected runtime, derived-data,
cache, migration-history, session, and post-backup prediction activity. No
original business-record primary keys were missing. This is evidence about the
dated archive snapshot, not proof of equality with the current live Supabase
project.

The local yield-prediction count of 6,498 versus the historical source count of
3,271 is accounted for by 3,227 contiguous target IDs after the source maximum;
the reconciliation classified these as post-backup model-warming/current-season
predictions, not duplicates or migration loss. A current-source comparison is
still required.

### Table-by-table historical status

The historical report marked the following tables equal:

`a_i_chats`, `account_emails`, `advisory_feedback`, `advisory_rules`,
`ai_blocked_patterns`, `ai_blocked_terms`, `ai_faqs`, `ai_knowledge_topics`,
`ai_moderation_events`, `ai_synonyms`, `ai_terms`, `announcements`,
`backup_verifications`, `barangay_profiles`, `cache_locks`,
`chatbot_responses`, `climate_records`, `conversation_messages`,
`conversations`, `farm_boundaries`, `farmer_organization_members`,
`farmer_organizations`, `farmer_planting_schedules`, `farmer_profiles`,
`farmer_survey_responses`, `farms`, `feed_comments`, `feed_media`,
`feed_posts`, `feed_reactions`, `field_reports`,
`historical_rice_productions`, `job_batches`, `jobs`, `knowledge_base`,
`maintenance_records`, `mao_activities`, `mao_activity_logs`,
`mao_user_barangays`, `ml_models`, `model_feedback_decisions`,
`municipal_yield_predictions`, `notifications`, `password_reset_tokens`,
`reports`, `rice_productions`, `rice_varieties`, `rice_variety_imports`,
`rice_yield_model_comparisons`, `system_logs`, `typhoon_safety_responses`,
`usability_survey_responses`, `users`, `weather_fetch_logs`,
`weather_forecast_feedback`, `weather_impact_analyses`,
`yield_prediction_feedback`, and `yield_prediction_requests`.

The 11 historical difference tables were:

| Table | Historical classification |
|---|---|
| `analytics_runs` | 10 extra post-backup runtime rows |
| `cache` | 5 expired/evicted ephemeral keys missing |
| `external_weather_data` | 2 refreshed weather rows |
| `failed_jobs` | Runtime failure/retry history changed; current count preserved at 15 |
| `heatmap_areas` | Derived risk-map recomputation |
| `migrations` | Laravel migration-history divergence |
| `monthly_weather_predictions` | Prediction lifecycle refresh |
| `planting_advisories` | 3 generated rows and 1 lifecycle change |
| `scheduled_task_runs` | 304 post-backup scheduler rows |
| `sessions` | 1 expired/cleared session missing |
| `yield_predictions` | 3,227 contiguous post-backup predictions |

## Supabase-managed services

| Component | Status | Evidence / action |
|---|---|---|
| Supabase Auth | NOT VERIFIED | Repository uses Laravel `users`, password hashing, sessions, and `Auth`; live Supabase provider, identities, MFA, and recovery metadata require source access. |
| Supabase Storage | NOT VERIFIED | Repository uses Laravel local/public storage; no confirmed Supabase Storage SDK or endpoint was found. Bucket/object parity still requires source access. |
| Supabase Realtime | NOT VERIFIED | Repository uses Laravel Reverb and `pusher-js`; no confirmed Supabase realtime client was found. Supabase publications and subscriptions require source metadata access. |
| RLS and database roles | NOT VERIFIED | Local foreign keys passed, but Supabase policies, grants, and roles require source access. |
| Functions, triggers, scheduled tasks | PARTIAL | Local Laravel migrations and scheduler are active; Supabase functions/events/webhooks cannot be verified without project metadata. |
| External integrations | PARTIAL | Repository audit found local Laravel, ML, Reverb, Open-Meteo/PAGASA/Groq paths; deployed-project integrations remain unverified. |

## Runtime independence audit

The project-wide search found no active Supabase SDK, frontend client,
Supabase URL, PostgREST call, Supabase Storage request, or Supabase Realtime
client in application runtime code.

The active `.env` contains local MySQL, database queue/session, file cache,
local filesystem, and Reverb settings. No `SUPABASE_*` or
`MIGRATION_SOURCE_*` values are active.

Remaining references are intentionally retained in:

- one-off migration and reconciliation commands;
- historical PostgreSQL backup/restore support;
- migration-history files documenting Supabase-to-MySQL schema behavior;
- safety guards that reject accidental Supabase runtime configuration;
- audit, deployment, and decommissioning documentation.

These references are not active runtime dependencies and must remain until
retirement and recovery acceptance are complete.

Laravel was booted with Supabase-related environment variables removed. The
environment guard reported local MySQL, database queue/session, file cache,
Reverb, and local filesystem configuration with `Database environment: OK`.

## Local services and application health

All five services were rechecked as healthy:

- Python ML service: healthy; `/health` returned HTTP 200.
- Laravel web server: healthy; `/up` returned HTTP 200.
- Laravel Reverb: healthy and listening on port 8080; its root HTTP route returned 404, which is expected.
- Queue worker: healthy.
- Scheduler: healthy and recording scheduled-task activity.

The failed-job count remained 15. No failed jobs were retried, flushed, or
modified.

## Backup and recovery verification

The two named historical artifacts exist and are PostgreSQL custom-format
archives:

| Backup | Result |
|---|---|
| `storage/app/backups/iclimate_2026-11-03_1025.backup` | Exists; `pg_restore --list` found 809 entries |
| `storage/app/backups/supabase-full-20261103.backup` | Exists; `pg_restore --list` found 1,216 entries |

The newest local backup was verified read-only with:

```bash
php artisan iclimate:verify-backup --no-record
```

Result: `iclimate_2026-11-10_0703.backup`, 69 archive entries, valid SHA-256
computed. No verification row was written.

Historical documentation records a successful isolated MariaDB physical and
logical recovery. A new restore was not run because the request is
verification-only and no additional restore authorization was provided.

The current backup set does not by itself prove preservation of Supabase Auth,
Storage, or project metadata. Those resources require separate source-side
inventory and export evidence.

## Superseding current runtime status — 2026-11-10

The older service snapshot above is retained as historical evidence. The latest
read-only local check is authoritative for current runtime status: Laravel,
ML, Reverb, and scheduler processes were observed, but no queue worker was
running; queue inventory was `0` pending, `0` reserved, and `15` historical
failed jobs. No worker was started and no jobs were processed. The retry branch
has since been repaired and passed the focused and full regression coverage;
live worker approval remains pending.

## Tests and verification coverage

Previously completed results, retained as evidence and supplemented by current
checks:

- Laravel unit: 145 passed.
- Laravel feature: 1,584 passed; 5 MariaDB-only schema tests skipped.
- Python ML: 15 passed.
- Frontend production build: passed.
- Broadcast/environment focused tests: 23 passed, 105 assertions.

The complete suite was not run against the active staging database. Laravel
MySQL tests can perform destructive refresh migrations, and the request
prohibits destructive migrations against active data. A separate `test`
database exists, but it was not used for destructive test setup without
explicit authorization.

## Remaining risks and required actions

| Risk | Severity | Blocks cloud deletion? | Required action |
|---|---|---:|---|
| No current live Supabase comparison | Critical | Yes | Obtain authorized read-only source credentials and run `--connect-only`, `--audit-only`, and `--compare`. |
| Supabase Auth state unverified | Critical | Yes | Inventory users, identities, providers, MFA, recovery, and confirm no active identity depends on Supabase. |
| Supabase Storage state unverified | High | Yes | Inventory buckets/objects and compare required assets with local storage. |
| Supabase Realtime/RLS/roles unverified | High | Yes | Inventory publications, subscriptions, policies, grants, and integrations. |
| No fresh isolated restore in this audit | Medium | Yes until accepted | Run a separately authorized restore drill without touching active MySQL. |
| Historical snapshot differs from current target | Medium | No by itself | Obtain business acceptance of expected post-backup predictions, scheduler rows, weather, advisories, and derived-risk changes. |
| Deployed environment not verified | High | Yes for production retirement | Inspect every deployed app, worker, scheduler, webhook, and integration. |

## Required final sequence

1. Obtain explicit read-only Supabase source access.
2. Run current inventory and deterministic comparison.
3. Inventory Supabase Auth, Storage, Realtime, RLS, functions, webhooks, and integrations.
4. Export and retain any required source-side metadata or objects.
5. Run an isolated restore drill and record the result.
6. Obtain business-owner acceptance of expected post-backup target changes.
7. Recheck deployed environments and external automations.
8. Obtain explicit approval before any irreversible cloud action.

## Final retirement decision

**BLOCKED — INSUFFICIENT EVIDENCE.**

The local application is operationally independent of Supabase, but the cloud
project must not be deleted until current source comparison and Supabase-managed
service inventories pass. This report authorizes no deletion, shutdown, or
mutation of the Supabase project.

## Follow-up source-access audit — 2026-11-10T08:56:41Z

### Access required

The remaining audit requires an authorized, read-only PostgreSQL path to the
original Supabase project database. The minimum connection details are:

- PostgreSQL host: the exact Supabase direct host or approved Session pooler
  host from the project dashboard;
- port: normally `5432` for direct or Session pooler access;
- database: normally `postgres`;
- username: a read-only database role, or an approved project read-only
  dashboard role with SQL Editor access;
- password: supplied out-of-band and never recorded in this report;
- schema: `public` unless the project owner identifies another application
  schema; and
- TLS: required (`sslmode=require`, or stronger certificate verification if
  available).

The connection must be authorized for read-only inspection of application
tables plus metadata needed to inventory Auth, Storage, Realtime, RLS,
functions, triggers, publications, roles, and webhooks. It must not be a
credential that can alter data or project settings. A database read-only role
alone may not expose every Supabase-managed service; project/dashboard
read-only access or separate read-only service inventories may also be needed.

### Approved-access checks performed

At 2026-11-10T08:56:41Z:

- `.env` contained no `MIGRATION_SOURCE_DB_*` variables.
- No active `SUPABASE_*` connection variables were present.
- Repository configuration requires explicit source settings and never falls
  back to the active local `DB_*` connection.
- Repository searches found no Supabase credential-manager retrieval wrapper.
- The documented macOS Keychain item is for local MariaDB backup access, not
  Supabase PostgreSQL access.
- No source connection was opened, and no source or target records were
  modified.

The guarded probe therefore remains blocked before any PostgreSQL query:

```text
php artisan iclimate:migrate-supabase --connect-only
Migration source configuration is incomplete; explicit MIGRATION_SOURCE_DB_*
settings are required.
```

### Safe dashboard procedure for obtaining access

1. Sign in to the organization that owns the original Supabase project and
   open the project; do not create, reset, pause, or delete anything.
2. At the top of the project, choose **Connect** and copy the **Session
   pooler** connection details for an IPv4-only workstation, or the direct
   connection details if the approved network supports the direct endpoint.
   Use the exact host and username shown by the dashboard; do not construct a
   pooler hostname manually.
3. If the organization supports project read-only access, grant the auditor
   the minimum read-only project/database role. Otherwise have an authorized
   administrator provision a temporary database role restricted to `CONNECT`
   and `USAGE`/`SELECT`-only inspection, with an expiry and a separately
   delivered password. Do not paste the password into chat, source control, or
   an audit report.
4. Confirm the connection is TLS-protected and that the role cannot `INSERT`,
   `UPDATE`, `DELETE`, `TRUNCATE`, `ALTER`, `DROP`, create functions, or change
   roles/policies. The audit runner should receive the values only through its
   approved secret-injection mechanism.
5. Provide the operator with only a confirmation that the authorized source
   is ready, plus the non-secret host/port/database/schema identifiers if
   needed. Do not send the password or API keys in chat.
6. After access is provisioned, run the guarded `--connect-only`, then
   `--audit-only`, then `--compare --report=<non-secret-path>` commands. The
   compare command reads both databases and writes only its local JSON report;
   it does not write either database.

Supabase documents that connection strings are obtained from the project’s
**Connect** dialog and that Session pooler access uses port 5432. See the
[Supabase PostgreSQL connection guide](https://supabase.com/docs/guides/database/connecting-to-postgres).
For organization-level read-only access, see [Supabase access control](https://supabase.com/docs/guides/platform/access-control).

### Current disposition

The audit stops at this access blocker. No live table comparison, Auth,
Storage, Realtime, RLS, Edge Function, webhook, or external-integration
verification is claimed. The retirement decision remains:

**BLOCKED — INSUFFICIENT EVIDENCE.**

No cloud deletion or database mutation is authorized by this report.

## Follow-up SQL Editor evidence — 2026-11-10

The project owner reports successful access to the original Supabase SQL
Editor for project `ilwbgawaalodtrwuargp`, with application schema
`iclimate_laravel`. The following values are recorded as operator-supplied
evidence pending receipt of the exported result sets; they were not retrieved
by this audit process and are not treated as a complete reconciliation:

| Table | Supabase reported | Local MySQL verified | Difference |
|---|---:|---:|---:|
| `users` | 877 | 877 | 0 |
| `farms` | 1,081 | 1,081 | 0 |
| `farmer_profiles` | 868 | 868 | 0 |
| `yield_predictions` | 3,271 | 6,498 | +3,227 local |

The Supabase application schema is reported to contain 69 tables, matching the
local MySQL table count. Matching table counts do not establish row identity,
primary-key coverage, column equality, foreign-key integrity, or managed-
service parity.

### Audit artifacts supplied for completion

- [Read-only Supabase SQL packet](SUPABASE_READ_ONLY_AUDIT_QUERIES.sql)
- [Local MySQL comparison script](../scripts/compare-supabase-audit.php)

Run the first query in the SQL packet and export only the columns
`table_name,row_count`. Export the primary-key query separately as
`table_name,primary_key_columns`. Do not export row values, password hashes,
tokens, model inputs, email addresses, or credentials. Combine the two
non-sensitive result sets into a CSV with the header:

```text
table_name,row_count,primary_key_columns
```

Then run the local-only comparison:

```text
php scripts/compare-supabase-audit.php --source-report=/secure/path/supabase-counts.csv
```

The script opens a read-only MySQL transaction, never connects to Supabase,
and reports table-set, count, and primary-key differences. A nonzero exit code
means the result is `DIFFERENT`, not that data should be changed.

### Yield-prediction finding remains unresolved

The reported difference is exactly 6,498 - 3,271 = 3,227 local rows. Existing
historical evidence suggested contiguous local IDs after the historical source
maximum and possible post-backup model warming, but that is not proof. The
yield queries in the SQL packet request only metadata needed to classify the
rows: ID ranges, scope, subject key, farm/user references, season, period,
model version, and generation timestamps. A legitimate derived-record finding
requires demonstrating that the extra rows are valid local post-snapshot
outputs, do not replace missing source business records, do not duplicate the
same `(subject_key, season_start, period)` identity, and are accepted by the
business owner. Until those checks are exported and reviewed, the difference
is unexplained.

### Managed-service verification remains pending

The SQL packet includes separate read-only checks for Auth users/identities/
sessions/MFA, Storage buckets/objects, Realtime publications, RLS policies,
functions, triggers, service schemas, and session TLS/read-only state. A
permission error is evidence of `UNVERIFIED`; it is not permission to broaden
the audit role. No Auth, Storage, Realtime, Edge Function, webhook, or external
integration retirement conclusion has been made.

### Revised decision

**BLOCKED — INSUFFICIENT EVIDENCE.**

The newly reported headline counts narrow the investigation but do not satisfy
the required table-by-table, primary-key, row-identity, yield-prediction,
backup/recovery, and managed-service evidence requirements. No database or
cloud resource was modified, and this report authorizes no cloud deletion.

## Verified count and primary-key comparison — 2026-11-10T09:36:39Z

### Inputs and method

- Supabase count export: `~/Downloads/Supabase Snippet Untitled query.csv`.
- Export contains 69 application tables and sums to 107,388 rows.
- Primary-key metadata was recovered from the retained schema-only view of
  `supabase-iclimate_laravel-final-20261110T040716Z.dump`; it contained 69
  tables and 69 primary-key constraints. This is source-schema evidence for
  that dated snapshot, not a substitute for executing the live primary-key
  query in the SQL packet.
- Combined sanitized input was generated at
  `/private/tmp/supabase-counts-with-pk.csv` with columns
  `table_name,row_count,primary_key_columns`.
- Local comparison output was written to
  `/private/tmp/supabase-audit-comparison.json`.
- The comparison opened a read-only MySQL transaction and did not write either
  database.

### Table names and primary keys

- Source table count: 69.
- Local table count: 69.
- Tables only in source: none.
- Tables only in local: none.
- Primary-key differences: none across the 69 compared tables.

The primary-key result is limited by the source metadata coming from the
dated retained schema snapshot. Current live PK metadata still requires the
SQL packet's primary-key query or a successful live source connection.

### Count differences

| Table | Supabase export | Local MySQL | Difference | Assessment |
|---|---:|---:|---:|---|
| `analytics_runs` | 2,679 | 2,707 | +28 local | Derived/runtime; row identity unverified |
| `backup_verifications` | 32 | 41 | +9 local | Local verification history |
| `cache` | 5 | 0 | -5 local | Transient cache |
| `external_weather_data` | 107 | 114 | +7 local | Derived/external-data history; acceptance required |
| `failed_jobs` | 3 | 15 | +12 local | Preserved historical failed jobs |
| `jobs` | 2 | 0 | -2 local | Transient queue state |
| `migrations` | 99 | 100 | +1 local | Local schema-history difference |
| `planting_advisories` | 245 | 251 | +6 local | Derived output; identity review required |
| `scheduled_task_runs` | 86,854 | 87,748 | +894 local | Scheduler/runtime history |
| `sessions` | 1 | 7 | +6 local | Transient session state |
| `system_logs` | 531 | 533 | +2 local | Operational log history |
| `weather_fetch_logs` | 2,552 | 2,565 | +13 local | Operational fetch history |
| `yield_predictions` | 3,271 | 6,498 | +3,227 local | Derived model output; unresolved |

All other tables have matching counts. These classifications explain why a
count difference may be expected, but they do not prove row-level equality.
The next required row-identity step is the live `--compare` run or equivalent
read-only SQL Editor export of primary-key sets and canonical row hashes for
non-transient application tables.

### Live-source limitation and managed services

The guarded live audit command was attempted with the private mode-600 source
configuration but failed before issuing a PostgreSQL query because the source
connection was unavailable from this environment. The schema-only dump was
used only for non-mutating PK metadata recovery. No current live Auth,
Storage, Realtime, RLS, function, trigger, webhook, or external-integration
results were obtained. Execute the corresponding separate statements in
`docs/SUPABASE_READ_ONLY_AUDIT_QUERIES.sql` and transfer only sanitized result
sets.

### Revised decision

**BLOCKED — INSUFFICIENT EVIDENCE.**

The comparison proves matching table names and matching PK definitions for the
available source schema snapshot, but it does not prove current row identity,
explain the 3,227 extra local predictions, or verify Supabase-managed services.
No database records, jobs, backups, or cloud resources were modified.

## Complete final verification matrix — 2026-11-10

| Requirement | Status | Evidence / blocker |
|---|---|---|
| Live Supabase source verified | UNVERIFIED | Approved connection attempt failed before a PostgreSQL query; current SQL Editor service results still required. |
| All 69 application tables accounted for | PASS for retained source snapshot | 69 source tables, 69 local tables, 107,388 source rows. |
| Original business records preserved | PASS for verified critical tables; UNVERIFIED for all current live tables | Critical PK sets match the retained dump; full live all-table identity comparison remains open. |
| Post-migration differences explained | UNVERIFIED | Operational differences are classified; external weather and planting advisories need current identity review and owner acceptance. |
| Prediction integrity | PASS for retained source range; owner acceptance pending for additions | IDs 1–3,271 full-field match; IDs 3,272–6,498 are post-migration derived predictions with no duplicate identities. |
| Foreign-key integrity | PASS for recovered source schema and local audit | Recovered archive has 87 FKs; local audit has 87 FKs and zero orphans. |
| Auth verified | UNVERIFIED | Live `auth.*` and dashboard provider/identity checks pending. |
| Storage verified | UNVERIFIED | Live buckets/objects and external consumers pending. |
| Realtime verified | UNVERIFIED | Live publications/subscriptions and consumers pending; local Reverb is healthy. |
| RLS verified | UNVERIFIED | Live policies, grants, and roles pending. |
| Edge Functions verified | UNVERIFIED | Dashboard function inventory pending. |
| Webhooks verified | UNVERIFIED | Database/dashboard webhook inventory pending. |
| External integrations verified | UNVERIFIED | Deployed environments and external consumers pending. |
| Backup recovery verified | PASS — RESTORE VERIFIED | Disposable PostgreSQL 18.6 restore recovered 69 tables, 107,388 rows, 69 PKs, 87 FKs, and 3,271 predictions. |
| Business-owner acceptance | PENDING | See `docs/SUPABASE_BUSINESS_OWNER_ACCEPTANCE.md`; no approval assumed. |

### Final readiness decision

**BLOCKED — INSUFFICIENT EVIDENCE.**

The application-schema backup is recoverable, critical source rows are
preserved, and the 3,227 additional predictions are strongly supported as
legitimate post-migration derived records. The project is not ready for
retirement approval because current live source row identity, Supabase-managed
services, external integrations, and authorized business acceptance remain
incomplete.

See:

- [Supabase cloud-service audit](SUPABASE_CLOUD_SERVICE_AUDIT.md)
- [Backup recovery verification](SUPABASE_BACKUP_RECOVERY_VERIFICATION.md)
- [Business-owner acceptance](SUPABASE_BUSINESS_OWNER_ACCEPTANCE.md)
- [Manual verification SQL](SUPABASE_FINAL_MANUAL_VERIFICATION.sql)

No deletion, pause, disablement, or other mutation of the Supabase project is
authorized by this report.

## Live source verification completed — 2026-11-10T10:06Z

The authorized PostgreSQL connection to project `ilwbgawaalodtrwuargp` was
successfully probed and inventoried. The source schema was `iclimate_laravel`.
The live comparison was read-only against the existing local staging database
`iclimate_staging_20261103_fix3`.

| Check | Result | Evidence |
|---|---|---|
| Live application tables | PASS | 69 source tables; 69 local tables |
| Live non-transient comparison | DIFFERENT | 62 tables compared; 50 equal; 12 different; 7 transient tables skipped |
| Source rows missing locally | PASS | 0 missing rows across compared tables |
| Local extra rows | REVIEW | 4,260 local extras, primarily post-migration/runtime/derived activity |
| Changed rows | REVIEW | 28 changed rows across seven tables |
| Live source inventory | PASS | 107,388 source application rows; report at `storage/app/migration-reports/supabase-live-audit-20261110.json` |
| Live comparison artifact | COMPLETE | `storage/app/migration-reports/supabase-live-compare-20261110.json` |
| Live primary-key metadata | PASS | 69 source PK definitions match 69 local PK definitions |

### Live count differences

| Table | Source | Local | Missing locally | Extra locally | Changed rows | Current classification |
|---|---:|---:|---:|---:|---:|---|
| `analytics_runs` | 2,679 | 2,709 | 0 | 30 | 0 | Post-migration analytics activity |
| `backup_verifications` | 32 | 41 | 0 | 9 | 2 | Local verification history; changed rows require no-loss review |
| `external_weather_data` | 107 | 114 | 0 | 7 | 11 | Likely post-migration collection; owner acceptance/review pending |
| `heatmap_areas` | 19 | 19 | 0 | 0 | 9 | Derived rows changed; review required |
| `monthly_weather_predictions` | 14 | 14 | 0 | 0 | 1 | Derived row changed; review required |
| `notifications` | 33 | 33 | 0 | 0 | 1 | Operational/derived row changed; review required |
| `planting_advisories` | 245 | 251 | 0 | 6 | 1 | Likely post-migration derived output; owner acceptance pending |
| `scheduled_task_runs` | 86,854 | 87,818 | 0 | 964 | 0 | Post-migration scheduler history |
| `system_logs` | 531 | 533 | 0 | 2 | 2 | Post-migration operational history; changed rows review pending |
| `users` | 877 | 877 | 0 | 0 | 1 | No missing user; one field-level change requires review |
| `weather_fetch_logs` | 2,552 | 2,567 | 0 | 15 | 0 | Post-migration fetch history |
| `yield_predictions` | 3,271 | 6,498 | 0 | 3,227 | 0 | Legitimate derived additions strongly supported by identity/full-field evidence |

The seven skipped tables are transient framework tables: `cache`,
`cache_locks`, `failed_jobs`, `job_batches`, `jobs`, `migrations`, and
`sessions`. Their snapshot equality is not a retirement requirement. No
source business row was reported missing by the live comparison.

The 28 changed rows are not silently classified as equal. They require a
field-level review or explicit acceptance, but they are not source-only loss:
the comparison found zero missing source keys.

## Live managed-service verification summary

Live metadata checks found zero Supabase Auth users/identities/sessions/MFA
factors, zero Storage buckets/objects, one Realtime publication with zero
published tables, and zero application RLS policies. Supabase service schemas,
functions, and triggers remain present. Edge Functions, dashboard webhooks,
OAuth/provider configuration, and external consumers remain dashboard-only
checks. Details are in `docs/SUPABASE_CLOUD_SERVICE_AUDIT.md`.

## Updated final decision

**BLOCKED — INSUFFICIENT EVIDENCE.**

Live source verification substantially reduces the data-loss risk: no source
rows are missing, original critical prediction rows are preserved, and the
3,227 additional predictions are supported as post-migration derived output.
Retirement approval remains blocked by 28 changed rows needing field-level
review, dashboard-only Edge Function/webhook/external-integration checks, and
pending business-owner acceptance. No Supabase cloud resource may be deleted
or modified under this audit.

## Final changed-row field review — 2026-11-10T10:18Z

Sanitized field-level comparison artifact:
`storage/app/migration-reports/supabase-changed-rows-20261110.json`.
It contains table names, primary keys, changed column names, timestamp ordering,
and classifications only; it contains no row values, personal data, tokens, or
credentials.

| Table | Changed PKs | Classification | Changed non-timestamp fields |
|---|---|---|---|
| `backup_verifications` | 31, 32 | 2 VERIFIED EXPECTED | Backup filename/size/hash, verification status/details, archive count |
| `external_weather_data` | 94, 95, 99–107 | 11 VERIFIED EXPECTED | Weather measurements, raw response, fetch timestamp |
| `heatmap_areas` | 2, 8, 9, 12, 14–17, 19 | 9 VERIFIED EXPECTED | Risk type/score/components and recommendations |
| `monthly_weather_predictions` | 4 | 1 VERIFIED EXPECTED | Forecast values, metadata, generation/expiry fields |
| `planting_advisories` | 250 | 1 VERIFIED EXPECTED | Review/publish/status and validity fields |
| `system_logs` | 541, 542 | 2 VERIFIED EXPECTED | Log user/action/details |
| `notifications` | 96 | VERIFIED INCONSISTENT | Title, message |
| `users` | 10 | VERIFIED EXPECTED | PostgreSQL/MySQL boolean and timestamp representation; security flags semantically equal |

For the 27 expected rows, local `updated_at` is newer than the source value,
or the difference is a PostgreSQL/MySQL representation normalization. User 10
has matching role/status, `is_mao_head=false/0`, and
`must_change_password=false/0`; the only meaningful timestamp difference is
that the source `updated_at` is newer. Notification 96 remains inconsistent:
the source is newer and its title/message lengths differ from local. No
automatic synchronization was performed.

### Changed-row conclusion

- VERIFIED EXPECTED: 27/28.
- VERIFIED INCONSISTENT: 1/28 (`notifications` 96).
- UNVERIFIED: 0/28 after timestamp, boolean, and changed-column review.

The 3,227 additional yield predictions are unaffected by these 28 changes and
remain supported as post-migration derived records. Retirement remains blocked
until notification 96 is resolved or explicitly accepted by an authorized
business owner, and dashboard/external-consumer checks and business acceptance
are complete.

## Yield-prediction and critical-key investigation — 2026-11-10

### Yield-prediction evidence

The retained PostgreSQL dump contains the full source `yield_predictions` data
set. The source and local evidence now shows:

- Source IDs are contiguous `1..3271`.
- Local IDs are contiguous `1..6498`; there are no ID gaps.
- The local rows with IDs `1..3271` match the source dump on identity fields:
  `subject_key`, `season_start`, `period`, `model_version`, and
  `generated_at`.
- Canonical full-field comparison of those 3,271 rows found zero changed rows
  and zero changed columns. JSON ordering was normalized before comparison.
- No duplicate local `(subject_key, season_start, period)` identity keys were
  found.
- Every extra local row is in the contiguous range `3272..6498`.
- All 3,227 extra rows use `season_start=2026-11-01` and the same model
  version as the source rows. They were generated within
  `2026-11-10 17:40:00..17:40:34` local time, after the retained dump was
  created at `2026-11-10 12:07:43` local time.
- The source rows are all the earlier `2026-05-01` season and have counts
  `1,093 season`, `1,089 week`, and `1,089 2weeks`.
- The local additional season has counts `1,083 season`, `1,072 week`, and
  `1,072 2weeks`, totaling 3,227.
- The extra rows comprise 3,200 farm predictions and 27 barangay predictions.

This is strong evidence that the 3,227 rows are legitimate post-migration
derived predictions for a new season/period run, not replacements for missing
source rows. It also proves that the 3,271 source prediction rows were not
overwritten or lost in local MySQL. The classification remains subject to
business-owner acceptance because the current live Supabase table was not
queried directly during this run.

### Critical source-to-local primary-key coverage

Using the retained PostgreSQL dump and read-only local MySQL queries, exact
primary-key sets matched for:

| Table | Source rows | Local rows | PK-set result |
|---|---:|---:|---|
| `users` | 877 | 877 | Match |
| `farms` | 1,081 | 1,081 | Match |
| `farmer_profiles` | 868 | 868 | Match |
| `farmer_planting_schedules` | 6,606 | 6,606 | Match |
| `rice_productions` | 124 | 124 | Match |
| `yield_predictions` | 3,271 source rows preserved in local IDs 1–3,271 | 6,498 | Source range and full fields match; 3,227 post-migration rows added |

This is backup-derived evidence. It does not replace a current live-source
row-hash comparison for every non-transient application table.

### Classification of the 13 count differences

| Table group | Classification | Evidence |
|---|---|---|
| `cache`, `jobs`, `sessions` | Expected runtime-generated difference | Ephemeral framework state; local state need not equal the source snapshot. |
| `failed_jobs` | Expected retained operational difference | Local 15 historical failures were preserved; no retry or deletion was performed. |
| `migrations` | Expected local schema-history difference | Local migration history has one additional applied migration. |
| `backup_verifications` | Expected local operational difference | Local backup verification history is newer than the source snapshot. |
| `analytics_runs`, `scheduled_task_runs`, `system_logs`, `weather_fetch_logs` | Expected post-migration runtime difference | Local scheduler, analytics, logging, and fetch activity continued after migration; row identity is not fully live-verified. |
| `external_weather_data`, `planting_advisories` | Likely legitimate post-migration derived/business data; acceptance required | Counts increased locally, but current-source row identity and business acceptance remain incomplete. |
| `yield_predictions` | Legitimate post-migration derived data supported by evidence | New season, contiguous IDs, matching source range, no duplicate identity keys, and full-field equality for preserved rows. |

No count difference is currently classified as a confirmed missing source
business record. The two likely post-migration business/derived tables remain
partially unverified until current live row identities or approved business
acceptance are recorded.

### Supabase-managed services and retirement blockers

The application repository still shows no confirmed runtime dependency on
Supabase Auth, Storage, Realtime, Edge Functions, or Supabase webhooks. However,
current live service inventories were not obtained because the guarded source
connection failed before issuing a PostgreSQL query. The SQL Editor packet
contains safe checks for Auth, Storage, Realtime publications, RLS, functions,
triggers, service schemas, and session TLS/read-only state. Those results must
be captured before declaring the services unused or retired.

### Revised recommendation

**BLOCKED — INSUFFICIENT EVIDENCE.**

The available backup evidence supports preservation of original critical rows
and legitimacy of the 3,227 additional predictions. Retirement is still not
approved because current live row-level comparison, managed-service
inventories, external integrations, and final business acceptance remain open.

## Final owner-preparation update — 2026-11-10

### Notification 96

Both databases identify this as an unread `Announcement` for user 10 with the
same creation timestamp and read state. Supabase is newer by `updated_at`; the
title and message lengths differ, so the source and local content are not
equivalent. No matching application-log entry was found.

Owner review is now complete: **ACCEPTED — RETAIN LOCAL ENGLISH VERSION**.
The difference is accepted as a localization/content discrepancy. No source
record is missing, no notification synchronization is required, and no database
change is authorized. The historical root cause remains **UNVERIFIED**.

### User 10

Both databases identify user 10 as an active `Farmer`. `is_mao_head` is false
in Supabase and `0` in MySQL; `must_change_password` is false in Supabase and
`0` in MySQL. These are semantically equal. `last_active_at` and
`last_login_at` also match; the remaining difference is timestamp/history in
`updated_at`. Related system-log counts and time bounds match. No password,
email, name, or token was read into the report.

Safe resolution: no data change is recommended. An authorized owner should
confirm the equivalent false security flags; do not alter either security flag
without explicit security approval.

### Owner checklist and readiness

1. Confirm user 10’s security flags are intentionally false/equivalent.
2. In Supabase Dashboard, inspect Edge Functions, Database Webhooks,
   Authentication Providers, scheduled functions, Integrations, and legacy
   deployments. Record names, status, and last activity only.
3. Confirm external consumers with deployment and integration owners, including
   CI/CD, cron, webhooks, public APIs, legacy sites, and database clients.
4. Complete the remaining choices in `docs/SUPABASE_BUSINESS_OWNER_ACCEPTANCE.md`.

| Final requirement | Status |
|---|---|
| Live source and PK verification | PASS |
| Original critical business records preserved | PASS for verified live comparison |
| 3,227 predictions explained | PASS technically; owner acceptance pending |
| Notification 96 | ACCEPTED — RETAIN LOCAL ENGLISH VERSION |
| User 10 security fields | VERIFIED EXPECTED; owner confirmation pending |
| Edge Functions/webhooks/OAuth/scheduled cloud functions | UNVERIFIED — dashboard required |
| External consumers and legacy deployments | UNVERIFIED — owner confirmation required |
| Backup recovery | PASS |
| Business-owner acceptance | PENDING |

**Final decision: BLOCKED — INSUFFICIENT EVIDENCE.**

No database record, environment setting, backup, or Supabase resource was
modified. This audit does not authorize cloud deletion.

## Current owner decision draft — 2026-11-10

| Item | Recorded owner decision/status |
|---|---|
| Notification ID 96 | **ACCEPT LOCAL ENGLISH VERSION — APPROVED** |
| Additional 3,227 yield predictions | **PENDING** |
| Additional 7 external weather records | **PENDING** |
| Additional 6 planting advisories | **PENDING** |
| User ID 10 security flags | **NOT YET OWNER-CONFIRMED** |
| Cloud-service verification | **PENDING** |
| External-consumer verification | **PENDING** |
| Supabase retirement | **BLOCKED — INSUFFICIENT EVIDENCE** |

The owner decision records acceptance of Notification 96 only; it does not
authorize synchronization, deletion, or retirement. Technical verification
remains distinct from owner approval:
user 10’s security flags are semantically equivalent (`false` in PostgreSQL
and `0` in MySQL), but they are not owner-confirmed.

### Private notification review

A sanitized metadata comparison and a separate restricted content comparison
for notification 96 were prepared for private owner review outside the shared
audit reports. The shared evidence contains record type, state, timestamps,
and content lengths only—no notification contents, user identity, or
account-sensitive data. The owner must review the actual content only in an
approved private channel. The recorded choice is **ACCEPT LOCAL ENGLISH
VERSION**; the actual notification contents remain excluded from this shared
report.

Retirement remains **BLOCKED — INSUFFICIENT EVIDENCE** until the pending
technical checks and owner decisions are completed. No database, backup,
environment setting, or Supabase resource was modified.

## Notification 96 investigation — 2026-11-10T10:58Z

At the time of the technical investigation, the owner decision was **NEEDS
FURTHER INVESTIGATION**. The subsequent owner decision supersedes that pending
disposition: **ACCEPTED — RETAIN LOCAL ENGLISH VERSION**. This is documentation
approval only and does not authorize synchronization.

Read-only PostgreSQL and MySQL comparisons confirmed:

- Both rows are unread `Announcement` notifications with the same creation
  timestamp.
- Supabase `updated_at` is `2026-11-05 05:17:16+00`; local MySQL
  `updated_at` remains `2026-10-31 18:57:43`.
- Title lengths are 30 (Supabase) and 21 (local); message lengths are 106
  (Supabase) and 100 (local). The actual contents are retained only in the
  mode-600 private owner-review file and are not included here.
- No matching repository/application log entry was found. Read-only system-log
  searches found no notification-96 audit match on either side.
- The retained source backup is available, but the available historical
  evidence does not establish whether the source change was an administrator
  action, scheduled task, application event, or migration artifact.
- Application review shows notifications are MAO-managed and editable; the
  local version is still displayable through the current user notification feed.
  No database foreign-key dependents of `notifications.id` were found.

Investigation result: **CAUSE UNVERIFIED**. The newer Supabase timestamp does
not by itself establish that the Supabase content was the intended business
state. The owner selected the existing local English version because both
versions communicate the same barangay-confirmation instruction. Supabase is
not selected for migration, synchronization is not required, and database
modification remains **NOT AUTHORIZED**.

Private evidence file: `/private/tmp/notification-96-private-content-review.json`
(mode 600; not tracked or committed). Shared audit evidence remains sanitized.

### Recorded owner disposition

Owner acceptance: **Confirmed in the current conversation**. Decision date:
**November 10, 2026**. This acceptance resolves the disposition of Notification
96, not the unresolved historical cause.

**Final retirement decision remains: BLOCKED — INSUFFICIENT EVIDENCE.**
