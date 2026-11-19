# Supabase to XAMPP MySQL migration

## Implementation status

The repository now contains a guarded migration command:

```bash
php artisan iclimate:migrate-supabase --audit-only
php artisan iclimate:migrate-supabase --migrate --confirm
```

The command inventories every base table in the configured source schema and
writes exact source row counts to a JSON report. The import path creates the
Laravel schema on a fresh local MySQL/MariaDB target, copies shared columns in
batches, preserves source primary keys, disables foreign-key checks only for
the transfer, re-enables them afterward, and records per-table results.

It refuses non-local targets and refuses to run against a non-empty target.
It never writes to the source connection. Transient Laravel queue/session
tables are intentionally excluded from the data transfer because they are
local runtime state, not application records.

## Current audit blocker

This workspace cannot produce a source backup or record-count report yet:

- The active `.env` points to a Supabase PostgreSQL connection whose hostname
  is currently unreachable from the workspace.
- XAMPP is installed at `/Applications/XAMPP/xamppfiles`, but MySQL/MariaDB is
  not listening on `127.0.0.1:3306`.
- Therefore no source rows, authentication records, Storage objects, RLS
  policies, Realtime publications, functions, triggers, or Supabase metadata
  are claimed as migrated here.

The source must remain unchanged. Once Supabase connectivity and XAMPP MySQL
are available, configure the `MIGRATION_SOURCE_DB_*` and
`MIGRATION_TARGET_DB_*` variables in `.env`, run the audit command, review its
JSON report, then run the confirmed import against a fresh staging database.

## Rollback

Before cutover, create a verified local backup with:

```bash
php artisan iclimate:backup-database --no-prune
```

To roll back the application, restore the previous `DB_*` values and clear
Laravel's cached configuration. Do not delete or modify the Supabase source
until the imported counts, relationships, authentication behavior, files,
and feature tests have been independently verified.
