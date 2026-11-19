# iClimate Supabase Backup Recovery Verification

Verification date: 2026-11-10  
Project: `/Users/johnlukereynon/iclimate_system`

## Result

**PASS — RESTORE VERIFIED** for the retained PostgreSQL custom-format archive
`/Users/johnlukereynon/.iclimate-private/supabase-archive/supabase-iclimate_laravel-final-20261110T040716Z.dump`.

This pass applies to the isolated PostgreSQL recovery drill only. It does not
prove current live Supabase state or managed-service parity.

## Restore method

1. Created a fresh temporary PostgreSQL 18.6 cluster under `/private/tmp`.
2. Started it on a private local socket and disposable port.
3. Restored the custom-format archive with `pg_restore --exit-on-error
   --no-owner --no-privileges`.
4. Queried only the isolated database for schema and record-count evidence.
5. Stopped the temporary server and removed only the temporary cluster.

The active MariaDB database, Laravel `.env`, backups, Supabase project, and
application workers were not used as restore targets or modified.

## Recovery evidence

| Check | Result |
|---|---|
| Archive format | PostgreSQL custom database dump v1.16-0 |
| Archive size | 2,488,206 bytes |
| Archive SHA-256 | `c7d6f19b225d30fbcff55f8cf8c5a69c145763084fd0bf74536d2437ba937ebe` |
| Recovered application tables | 69 |
| Recovered application rows | 107,388 |
| Recovered primary keys | 69 |
| Recovered foreign keys | 87 |
| `yield_predictions` | 3,271 rows, IDs 1–3,271 |
| Restore command | Completed successfully |
| Cleanup | Temporary cluster removed |

The initialized cluster already contained the default `postgres` database;
that harmless `createdb` warning did not affect restoration. `pg_restore`
completed successfully and the validation queries passed.

## Other backup artifacts

- `storage/app/backups/iclimate_2026-11-03_1025.backup`: PostgreSQL custom
  archive; SHA-256 `a7594bd013c82d22fb2b5825a9e00f33de4146372cb9a12ef2b629f5f1e40676`.
- `storage/app/backups/supabase-full-20261103.backup`: PostgreSQL custom
  archive; SHA-256 `c16e14fa264458cdc44f70e6f61cb811b0b5953484ef542359c393dd07837241`.
- `storage/app/backups/iclimate_2026-11-10_0703.backup`: MariaDB backup;
  read-only application verification passed with 69 archive entries and
  SHA-256 beginning `15e4df76462ab519…`.

No backup was deleted, overwritten, or restored over an active database.

## Remaining recovery limitation

This drill validates the retained PostgreSQL application-schema archive. It
does not validate Supabase Auth, Storage, Realtime configuration, Edge
Functions, webhooks, or external integrations, which require separate
dashboard/source evidence.
