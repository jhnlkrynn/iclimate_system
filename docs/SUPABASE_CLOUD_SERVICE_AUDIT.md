# iClimate Supabase Cloud-Service Audit

Audit date: 2026-11-10  
Supabase project: `ilwbgawaalodtrwuargp`  
Application schema: `iclimate_laravel`

## Decision

**UNVERIFIED — cloud retirement is not approved.**

The repository contains no confirmed active Supabase SDK, frontend client,
Storage call, Realtime subscription, or Supabase API URL in application runtime
code. The local runtime uses Laravel authentication, MariaDB, local storage,
Laravel Reverb, database queues, and the local ML service. This repository
finding does not prove that the cloud project has no Auth users, Storage
objects, publications, policies, functions, Edge Functions, or external
consumers.

## Service inventory

| Service | Local dependency finding | Live evidence | Status | Retirement condition |
|---|---|---|---|---|
| Supabase Auth | Laravel `users` authentication is used; no confirmed Supabase Auth client | `auth.users`, identities, providers, sessions, MFA not obtained | UNVERIFIED | Confirm no active identities/providers or external consumers |
| Supabase Storage | Laravel local/S3 filesystem configuration; no confirmed Supabase Storage SDK | Buckets, objects, references, and consumers not obtained | UNVERIFIED | Inventory buckets/objects and external file references |
| Supabase Realtime | Local Laravel Reverb is configured and healthy; no confirmed Supabase subscription | Publications/subscriptions and external consumers not obtained | UNVERIFIED | Confirm no required publication/subscription remains |
| RLS/policies | Local MariaDB application path does not use PostgreSQL RLS | Live policies, grants, roles not obtained | UNVERIFIED | Record policy/role inventory and confirm no external client depends on it |
| PostgreSQL functions/triggers | Local equivalents exist for required timestamp behavior | Current function/trigger/extension inventory not obtained | UNVERIFIED | Review all non-system functions/triggers and dependencies |
| Edge Functions | No repository Edge Function source or invocation found | Dashboard function list/logs not obtained | UNVERIFIED | Confirm no deployed function, schedule, or consumer remains |
| Webhooks/queues | No confirmed Supabase webhook runtime path | Database hooks, `pg_net`, and dashboard webhooks not obtained | UNVERIFIED | Inventory endpoints and disable only under separate authorization |
| External integrations | Repository references local services and documented weather/AI/map/mail providers | Deployed environment, CI/CD secrets, and external consumers not verified | UNVERIFIED | Inspect deployed configs and provider logs |

## Evidence and search scope

The runtime search covered `app`, `bootstrap`, `config`, `database`, `routes`,
`resources`, `public`, `scripts`, `python_scripts`, `deploy`, Composer, npm,
and `.env.example`. Remaining references are migration tools, safety guards,
historical comments/docs, or explicit local-only protections. They are not
evidence of an active runtime dependency.

The active local environment check reports MariaDB, local filesystem, database
queue/session/cache, and Reverb. No production environment or cloud dashboard
configuration was modified.

## Required manual dashboard checks

Use the original Supabase project without changing settings:

1. **Authentication → Users / Providers / Configuration**: record counts only,
   provider names, enabled OAuth providers, MFA capability, and whether any
   active identity is referenced by an external application. Do not export
   emails, tokens, passwords, or identity payloads.
2. **Storage → Buckets**: record bucket names, public/private flags, object
   counts, and last-modified timestamps. Do not download objects or expose
   object paths containing personal data.
3. **Database → Publications / Replication**: record publication names and
   published tables. Compare them with the repository’s absence of Supabase
   Realtime subscriptions.
4. **Database → Policies / Roles / Functions / Triggers**: record object names,
   schemas, and counts only. Do not alter policies, grants, functions, or
   triggers.
5. **Edge Functions**: record deployed function names, schedules, invocation
   timestamps, and consumer URLs without exporting secrets or payloads.
6. **Database Webhooks / Integrations / Logs**: record endpoint identity by
   redacted label, event/table, enabled state, and last invocation status.
7. **Project settings and deployment integrations**: inspect environment
   variable names and deployment targets only; never copy secret values.

The SQL statements in [SUPABASE_FINAL_MANUAL_VERIFICATION.sql](SUPABASE_FINAL_MANUAL_VERIFICATION.sql)
are SELECT-only and provide the database-side portion of this checklist.
Permission errors must be recorded as `UNVERIFIED`, not resolved by granting
broader access.

## Current conclusion

No active iClimate runtime dependency on a Supabase-managed service has been
confirmed. Cloud-service retirement remains **UNVERIFIED** until dashboard-only
and live metadata checks are completed. This document authorizes no deletion,
pause, disablement, or mutation of the Supabase project.

## Live metadata evidence — 2026-11-10T10:06Z

The authorized connection and SELECT-only metadata checks succeeded:

| Check | Live result | Interpretation |
|---|---:|---|
| `auth.users` | 0 | No Supabase Auth users in the inspected project |
| `auth.identities` | 0 | No Supabase Auth identities |
| `auth.sessions` | 0 | No Supabase Auth sessions |
| `auth.mfa_factors` | 0 | No Supabase MFA factors |
| `storage.buckets` | 0 | No Supabase Storage buckets |
| `storage.objects` | 0 | No Supabase Storage objects |
| PostgreSQL publications | 1 (`supabase_realtime`) | Publication exists |
| Published tables | 0 | No tables currently published through the inspected publication |
| Application RLS policies | 0 | No `pg_policies` rows for `iclimate_laravel` |
| Service schemas | `auth`, `realtime`, `storage` | Supabase service schemas exist |
| Functions | 4 auth, 19 realtime, 24 storage, 3 application | Service functions exist; no Edge Function conclusion from database metadata |
| Triggers | 3 application, 13 storage | Stored triggers exist and require retention review |
| Audit session | TLS enabled; `transaction_read_only=off` | Queries were SELECT-only, but the role/session is not intrinsically read-only |

These results reduce the risk that iClimate depends on Supabase Auth, Storage,
or table Realtime subscriptions. They do not verify dashboard Edge Functions,
webhooks, OAuth/provider configuration, or external consumers. The existing
publication and service functions/triggers must not be deleted as part of this
audit; their external dependencies remain a dashboard-level check.

Live evidence file: `storage/app/migration-reports/supabase-live-audit-20261110.json`.

The live database primary-key inventory also matched local MariaDB for all 69
application tables. The live source-to-local comparison found zero missing
source keys. This confirms relational key coverage, but not that dashboard
Edge Functions, webhooks, or external consumers are absent.

## Dashboard-only and external-integration status

The agent does not have an authorized Supabase Dashboard session or visibility
into third-party deployment accounts. These checks remain **UNVERIFIED**:

1. Open the Supabase project and go to **Edge Functions**. Record deployed
   function names, status, last deployment, invocation count/time, schedules,
   and consumer labels only. Do not copy function secrets or payloads.
2. Open **Database → Webhooks** (and any **Integrations** page). Record enabled
   webhook event/table, redacted endpoint label, last delivery status/time,
   and retry/error presence. Do not disable or replay anything.
3. Open **Authentication → Providers/Configuration**. Record enabled provider
   names and configuration presence, not client secrets, redirect secrets, or
   identity data.
4. Open **Database → Extensions, Publications, Functions, Triggers, and
   Policies**. Record names/counts and whether any object references an
   external endpoint or application schema.
5. Inspect organization **Integrations, API settings, and deployment
   connections** for scheduled jobs or consumers. Record only service names,
   status, and last activity.
6. Search every deployed iClimate host, CI/CD variable-name inventory, cron
   schedule, webhook receiver, and legacy website configuration for
   `SUPABASE_`, `supabase.co`, `createClient`, Auth, Storage, or Realtime
   references. Confirm each result with the service owner.

Repository/deployment search found only migration tooling, historical
documentation/comments, safety guards, and local-only protections; no active
Supabase SDK/client/API endpoint was confirmed. This does not observe external
applications or legacy deployments, so owner confirmation is required.

## Final automated verification update — 2026-11-10

The repository, deployment configuration, environment-variable names, routes,
scheduled commands, Composer/npm manifests, and integration documentation were
searched without printing secret values. Results are classified conservatively:

| Area | Automated result | Status |
|---|---|---|
| Laravel runtime database | Active local MariaDB path; Supabase PostgreSQL references are isolated migration/backup compatibility paths | VERIFIED INACTIVE for normal local runtime |
| Supabase SDK/frontend client | No confirmed package, `createClient` call, Realtime subscription, Storage SDK call, or direct Supabase API URL | VERIFIED INACTIVE in repository |
| Auth | Laravel `users` authentication and local authorization rules are present; live Auth metadata showed 0 users, identities, sessions, and MFA factors | VERIFIED INACTIVE for inspected application path; external consumers unverified |
| Storage | Local filesystem/S3 configuration; live Storage metadata showed 0 buckets and 0 objects | VERIFIED INACTIVE for inspected application path; external consumers unverified |
| Realtime | Local Laravel Reverb path; live publication had 0 published application tables | VERIFIED INACTIVE for inspected application path; external consumers unverified |
| Edge Functions | No repository source or invocation evidence; dashboard not accessible in this session | UNVERIFIED |
| Database webhooks | No repository consumer found; dashboard/webhook delivery inventory not accessible | UNVERIFIED |
| OAuth providers | No active local OAuth dependency established; dashboard provider configuration not accessible | UNVERIFIED |
| Scheduled cloud functions | Local Laravel scheduler and queue paths identified; Supabase cron/dashboard inventory not accessible | UNVERIFIED |
| External consumers/legacy deployments | Not observable from this repository or local database | UNVERIFIED |

The local scheduled generation paths are documented for owner review: the
`iclimate:warm-farmer-yields` command persists yield estimates for dashboards
and the heat map; scheduled weather and Python advisory processes populate
derived local data. These commands were inspected only and were not executed.

## Current local-runtime cross-check — 2026-11-10

A fresh read-only local check confirmed Laravel local mode, MySQL
`iclimate_staging_20261103_fix3`, database queue, `0` pending jobs, `0` reserved
jobs, and `15` historical failed jobs. ML `/health` and Laravel `/up` returned
HTTP 200; Reverb remained listening on port 8080. No active queue worker was
started. This local result does not provide dashboard or external-consumer
evidence, so the cloud retirement decision remains **BLOCKED — INSUFFICIENT
EVIDENCE**.

Read-only local automation checks on November 10 reported fresh Open-Meteo
weather and PAGASA advisory data, and duplicate protection passed for
`planting_advisories`, `external_weather_data`, and
`monthly_weather_predictions`. A separate read-only data audit reported two
dashboard recount mismatches (monitored barangays and valid yield prediction
runs). Those mismatches were not changed or attributed to Supabase, and remain
local application issues outside the cloud-service inventory.

The following references remain intentionally retained: migration source
configuration, migration/audit scripts, PostgreSQL backup tooling, historical
reports, and safety guards that reject remote/Supabase runtime targets. Their
presence is not evidence of an active application dependency.

## External-consumer classification

| Consumer class | Repository evidence | Status | Owner action |
|---|---|---|---|
| Local Laravel application | Uses MariaDB, local filesystem, Laravel auth, queue/session/cache, and Reverb | VERIFIED INACTIVE as a Supabase consumer |
| Local ML/Python service | Local process called by application analytics paths | VERIFIED INACTIVE as a Supabase consumer |
| CI/CD and deployment accounts | Configuration files are inspectable, but account activity and secret-name inventories are not observable here | UNVERIFIED | Owner checks deployments and CI/CD variables |
| Legacy websites/frontends/mobile apps | No active client found in this repository; other deployments are outside scope | UNVERIFIED | Owner confirms each deployment is retired or migrated |
| Webhook/API/database clients | No local consumer found; external network clients are outside scope | UNVERIFIED | Owner checks endpoint owners and access logs |

No dashboard-only item is marked PASS without direct evidence. The manual steps
in `SUPABASE_FINAL_OWNER_CHECKLIST.md` are the required next action. This audit
does not authorize disabling or deleting any Supabase resource.
