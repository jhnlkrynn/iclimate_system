# iClimate Post-Migration Difference Acceptance

Prepared: 2026-11-10  
Status: **PENDING — PARTIAL OWNER ACCEPTANCE RECORDED**

## Current owner decision draft — 2026-11-10

| Item | Owner decision/status |
|---|---|
| Notification ID 96 | **ACCEPT LOCAL ENGLISH VERSION — APPROVED** |
| Additional 3,227 yield predictions | **PENDING** |
| Additional 7 external weather records | **PENDING** |
| Additional 6 planting advisories | **PENDING** |
| User ID 10 security flags | **NOT YET OWNER-CONFIRMED** |
| Cloud-service verification | **PENDING** |
| External-consumer verification | **PENDING** |
| Supabase retirement | **BLOCKED — INSUFFICIENT EVIDENCE** |

These statuses record the current decision state. Notification 96 is approved
for retention of the existing local English version only; no database change or
Supabase retirement is authorized. All other items remain pending or
unconfirmed as shown.

## Plain-language summary

The original Supabase application data was copied to local MariaDB. The
retained source dump and read-only comparisons show that the original critical
records are preserved. Local prediction rows increased because the local
system generated a new season’s predictions after migration.

No approval or rejection is assumed by this document.

## Decisions requiring owner input

| Difference | Evidence | Proposed classification | Owner decision |
|---|---|---|---|
| 3,227 additional `yield_predictions` | IDs 3,272–6,498; new `2026-11-01` season; same model; no duplicate identities; original IDs 1–3,271 match full-field | Legitimate post-migration derived data | **PENDING** — ACCEPTED / REJECTED / NEEDS FURTHER INVESTIGATION |
| 7 additional `external_weather_data` rows | Local count 114 vs. source snapshot 107; likely post-migration external-data collection | Likely legitimate post-migration data; current live identity comparison pending | **PENDING** — ACCEPTED / REJECTED / NEEDS FURTHER INVESTIGATION |
| 6 additional `planting_advisories` rows | Local count 251 vs. source snapshot 245; derived advisory output | Likely legitimate post-migration data; current live identity comparison pending | **PENDING** — ACCEPTED / REJECTED / NEEDS FURTHER INVESTIGATION |

Other differences are operational state or history: caches, queue jobs,
sessions, failed-job history, migrations, backup verifications, scheduler
runs, analytics runs, logs, and weather-fetch logs. They are not treated as
missing farmer or farm records.

## What the evidence establishes

- Original source prediction IDs 1–3,271 are preserved locally.
- Full canonicalized fields for those 3,271 predictions match the retained
  PostgreSQL dump.
- No duplicate local prediction identity keys were found.
- Critical primary-key sets for users, farms, farmer profiles, planting
  schedules, and rice productions match the retained source dump.
- The additional predictions were generated locally after migration for a new
  season and new forecast periods.

## What remains open

- Current live Supabase row-level comparison for every non-transient table.
- Current Auth, Storage, Realtime, RLS, Edge Function, webhook, and external
  integration inventory.
- Business-owner acceptance of the three listed derived-data differences.

## Owner response section

Authorized business owner: ______________________________  
Role: _________________________________________________  
Date: _________________________________________________

- 3,227 additional yield predictions: **PENDING**
- 7 additional external weather records: **PENDING**
- 6 additional planting advisories: **PENDING**
- Notification ID 96: **ACCEPT LOCAL ENGLISH VERSION — APPROVED**
- User ID 10 security flags: **NOT YET OWNER-CONFIRMED**
- Cloud-service and external-consumer verification: **PENDING**
- Supabase retirement: **BLOCKED — INSUFFICIENT EVIDENCE**

Comments or required investigation:

________________________________________________________________________

________________________________________________________________________

This document records decisions only when an authorized owner completes the
response section. It does not authorize Supabase deletion or service changes.

## Live comparison update — 2026-11-10

The current authorized live comparison found zero source rows missing locally.
It also found additional local operational/derived rows and 28 changed rows:

- `external_weather_data`: 7 extra, 11 changed
- `planting_advisories`: 6 extra, 1 changed
- `heatmap_areas`: 9 changed
- `monthly_weather_predictions`: 1 changed
- `notifications`: 1 changed
- `backup_verifications`: 9 extra, 2 changed
- `system_logs`: 2 extra, 2 changed
- `users`: 1 changed, with no missing user

These changes do not currently show source-record loss, but field-level review
and owner acceptance remain required. All response decisions above remain
**PENDING**, except Notification 96, which is now **APPROVED — ACCEPT LOCAL
ENGLISH VERSION**. This approval is documentation-only and does not authorize
database modification or Supabase deletion.

## Changed-row acceptance

The live field-level review found 27 expected operational/derived refreshes and
one unresolved inconsistency:

| Row | Evidence | Owner decision |
|---|---|---|
| `notifications` ID 96 | Source is newer than local; title and message differ | **APPROVED — ACCEPT LOCAL ENGLISH VERSION** |
| `users` ID 10 | Role/status and security flags match semantically (`false` vs `0`); timestamp/history differs | **PENDING** — ACCEPTED / REJECTED / NEEDS FURTHER INVESTIGATION |

The 27 expected rows cover backup verification metadata, refreshed external
weather data, heatmap risk outputs, monthly forecasts, one planting advisory,
two system-log entries, and user 10’s representation-normalized fields. Their
local timestamps are newer than source or their values are semantically equal.
Notification 96 was classified VERIFIED INCONSISTENT during technical review.
The owner has now accepted the local English version as a localization/content
discrepancy. Supabase is not selected for migration, synchronization is not
required, and database modification remains **NOT AUTHORIZED**. The historical
root cause remains **UNVERIFIED**.

The technical review found user 10’s `is_mao_head` and
`must_change_password` values semantically equivalent (`false` in PostgreSQL,
`0` in MySQL). This is a technical verification finding only; the owner has
not yet confirmed that the security state is intended.

The sanitized evidence is recorded in
`storage/app/migration-reports/supabase-changed-rows-20261110.json`; it contains
no row values or personal information.

## Notification 96 owner-review status — 2026-11-10

- Decision: **ACCEPT LOCAL ENGLISH VERSION**.
- Decision status: **APPROVED**.
- Approved database version: **Local MySQL**.
- Supabase version: **Not selected for migration**.
- Synchronization required: **NO**.
- Database modification authorized: **NO**.
- Owner acceptance: **Confirmed in the current conversation**.
- Decision date: **November 10, 2026**.
- Investigation reason: title and message differ; Supabase has the newer
  `updated_at` timestamp.
- Rationale: both records communicate the same instruction to confirm the
  user’s barangay in their profile; the owner selected the existing local
  English version. The source version is not selected for migration.
- Cause: **UNVERIFIED**. Read-only source/local metadata, retained backup
  evidence, application-code review, and repository-log review did not establish
  whether the source edit was an administrator action, scheduled task,
  application event, or migration artifact.
- Private content review: restricted to
  `/private/tmp/notification-96-private-content-review.json` (mode 600). The
  actual title and message are intentionally excluded from this shared document.

Both records are unread `Announcement` records with matching creation
timestamps and equivalent intended meaning. The local version remains
displayable through the current notification feed; no notification foreign-key
dependents were found. The owner’s acceptance resolves the disposition of this
discrepancy, not its root cause. No synchronization was performed.

## Final owner-review package — 2026-11-10

The remaining business decisions are consolidated in
[`SUPABASE_FINAL_OWNER_REVIEW.md`](SUPABASE_FINAL_OWNER_REVIEW.md). All four
remain **PENDING**:

- 3,227 additional yield predictions.
- 7 additional external weather records.
- 6 additional planting advisories.
- User ID 10 security flags.

Dashboard-only and external-consumer checks remain **PENDING/UNVERIFIED** and
are listed in [`SUPABASE_FINAL_OWNER_CHECKLIST.md`](SUPABASE_FINAL_OWNER_CHECKLIST.md).
Notification ID 96 remains **ACCEPT LOCAL ENGLISH VERSION — APPROVED** and is
not reopened by this package.
