# Supabase decommissioning: approval runbook (2026-11-10)

Everything below was rehearsed on isolated restored copies on a throwaway MariaDB 10.4.28 instance. Nothing here has
been run against the active staging database (`iclimate_staging_20261103_fix3`) or Hostinger production. Each step
needs explicit approval. No command takes a password on the command line.

## 0. Before any step: a fresh, restorable backup

```bash
php artisan iclimate:backup-database && php artisan iclimate:verify-backup
```

The backup account password comes from `BACKUP_DB_PASSWORD` in `.env` (macOS Keychain item
`iclimate.local.mariadb.iclimate_backup`). Files are written `0600` into a `0700` folder. A backup only counts once an
isolated restore of it passes: restore it into a new database on a separate instance, then compare checksums, foreign
keys and indexes (rehearsed 2026-11-10: all 69 tables, 87 FKs and 339 indexes matched).

## 1. Pending migrations `2026_11_03_000005`, `2026_11_03_000006` and `2026_11_10_000001`

| Migration | Change | Data effect |
|---|---|---|
| 000005 (rewritten 2026-11-10) | Drops the three `iclimate_*_timestamp` triggers if an earlier version created them. Ensures `ON UPDATE CURRENT_TIMESTAMP` on `external_weather_data.fetched_at`, `monthly_weather_predictions.generated_at` and `typhoon_safety_responses.responded_at`, keeping each column's nullability and indexes. | None. On the staging DB the attribute already exists, so it is a no-op. |
| 000006 | `planting_advisories.metadata` JSON (MariaDB: LONGTEXT + `json_valid` CHECK) becomes LONGTEXT NULL; on MySQL 8 the separate `planting_advisories_chk_*` constraint is also dropped. | None (248 rows kept; metadata fingerprint unchanged). |
| `2026_11_10_000001` (added second session) | On MySQL 8 only: converts **native** JSON columns to LONGTEXT + `json_valid` (the MariaDB representation). Native JSON re-sorted keys, which broke report column order, saved filters and the rice-variety importer's "unchanged" detection (6 tests failed on a fresh MySQL 8.4 schema). | None. A no-op on MariaDB and on a database imported from the MariaDB dump (no native JSON columns). |

**Why 000005 was rewritten:** the original created BEFORE UPDATE triggers. These overwrote the fetch time the weather
code writes explicitly, and they wrote the server's local time. On this Philippine-time XAMPP server Laravel then read
`fetched_at` **8 hours in the future** (rehearsal: +660 min drift versus the value written). Supabase's
`on_update_current_timestamp_*` functions are pgloader's translation of MySQL's native column attribute, which is the
faithful equivalent.

**Cross-engine fixes (same day, second session):** on MySQL 8 the first rewrite of 000005 crashed, because
`information_schema` returns upper-case column names there; the columns are now aliased. 000006 left MySQL 8's separate
`planting_advisories_chk_1` constraint in place; it now drops any CHECK on that table that tests `metadata`. Both
migrations were re-rehearsed on fresh imports into MySQL 8.4 and MariaDB 10.4, each running at UTC: 0 pending, no
triggers, no metadata check left, the 33 other JSON checks kept, every data row unchanged.

**Privileges:** run with a migration/administrator account. It needs `ALTER`, `TRIGGER` and `DROP` on the database,
plus `INSERT` on `migrations`. The runtime account `iclimate_app` keeps SELECT/INSERT/UPDATE/DELETE only. Do not widen it.

**Procedure (staging, then production):**

1. Take a backup and verify it (step 0).
2. Run `php artisan migrate --pretend` and confirm only these three migrations appear (on MariaDB, 000001 issues no ALTER).
3. Run `php artisan migrate --force` with the migration account.
4. Check that `migrate:status` shows 0 pending; that `information_schema.triggers` returns 0 rows for these three
   tables; that `metadata` is `longtext`; and that the row counts of `planting_advisories` and `external_weather_data`
   are unchanged.
5. Run `php vendor/bin/phpunit tests/Feature/TimestampRefreshMigrationTest.php` against a MariaDB test database.

**Rollback:** neither change touches data. 000005 `down()` is intentionally a no-op, because the attribute predates it.
To undo 000006, restore the step-0 backup into a new database and compare. Do not re-add the JSON check while legacy
empty values may exist.

## 2. Supabase-only records (`scripts/reconcile-supabase-only-records.php`)

A final read-only archive of the `iclimate_laravel` schema is at
`~/.iclimate-private/supabase-archive/supabase-iclimate_laravel-final-20261110T040716Z.dump` (+ `.sha256`, mode 600;
69 tables, 87 FKs; contains Supabase `system_logs` 539–542 and `backup_verifications` 29–32).

| Supabase record | Class | Action | Proposed MariaDB ID |
|---|---|---|---|
| `system_logs` 541 (user 10, User Login, 2026-11-05 05:17:13 UTC) | Historical audit | Insert; details kept, `_provenance` added | next AUTO_INCREMENT (543 in rehearsal) |
| `system_logs` 542 (user 10, User Login, 2026-11-05 08:04:31 UTC) | Historical audit | Insert | next (544 in rehearsal) |
| `backup_verifications` 31 (failed, 2026-11-03 15:59:32) | Operational metadata | Insert (MariaDB 31 is a different record) | next AUTO_INCREMENT |
| `backup_verifications` 32 (passed, 2026-11-03 15:59:43) | Operational metadata | Insert | next |
| `users` 10 `last_login_at` / `last_active_at` (2026-11-05) | Operational metadata | Move forward only if later than MariaDB's | — |
| `notifications` 96 text | System reminder (`FarmerMissingInfoNotifier`), regenerated on page load | Archive only | — |
| cache, sessions, jobs, failed_jobs, migrations | Transient | Archive only | — |
| Every other difference | MariaDB is newer | Keep MariaDB | — |

IDs are never reused, existing rows are never overwritten, and the script is idempotent (a second run inserts 0). The
rehearsal on a fresh restore left all other 66 tables' checksums unchanged, plus every pre-existing `system_logs` and
`backup_verifications` row and every `users` field except user 10's two login times. FK check: user 10 exists, 0
orphans.

```bash
# Dry run (read-only on both sides; safe to repeat):
SOURCE_DB_PASSWORD=... TARGET_DB_PASSWORD=... php scripts/reconcile-supabase-only-records.php \
  --target-database=iclimate_staging_20261103_fix3 --target-user=iclimate_backup \
  --archive=$HOME/.iclimate-private/supabase-archive/supabase-iclimate_laravel-final-20261110T040716Z.dump
# After approval and a fresh verified backup (runtime account has INSERT/UPDATE):
SOURCE_DB_PASSWORD=... TARGET_DB_PASSWORD=... php scripts/reconcile-supabase-only-records.php \
  --target-database=iclimate_staging_20261103_fix3 --target-user=iclimate_app --apply --approved-active-target \
  --archive=... --report=storage/app/migration-reports/supabase-only-$(date -u +%Y%m%dT%H%M%SZ).json
```

Then re-run `iclimate:migrate-supabase --compare` with the pooler overrides. Expect only the documented
"MariaDB newer" differences and the new IDs.

**Production:** run the same dry run against the production database. If production was seeded from a different
snapshot, the plan will differ and must be reviewed again.

## 3. Hostinger verification (needs server access)

SSH from this machine was not permitted in the 2026-11-10 session. With access, run on the VPS as the app user:

```bash
sudo -u www-data bash deploy/hostinger/verify-production.sh
```

It is read-only and prints no secrets. It reports the DB driver and host category, connectivity, pending migrations,
queue worker, scheduler (timer or cron) and last run, ML service health, Reverb, the error counts per category, and
Supabase references in the runtime config. After deploying the code from 2026-11-10, also update
`iclimate-queue.service` (it now listens to `analytics,default,broadcasts`) and run `systemctl daemon-reload` and
`php artisan queue:restart`.

## 4. Credential rotation (public repository exposure)

`.env.mysql-backup` was committed in `afa3be2f` (2026-09-25), removed in `c753b4b2`, and is still in the history of the
**public** GitHub repository. These values are still in use and must be rotated at their providers (values are not
listed here):

- `APP_KEY`: rotate with `APP_PREVIOUS_KEYS` so sessions and encrypted values stay readable.
- `MAIL_PASSWORD`
- `GROQ_API_KEY`
- `OPENWEATHER_API_KEY`
- `WINDY_API_KEY`
- `WEATHERAPI_KEY`
- `VITE_GOOGLE_MAPS_API_KEY` (restrict by HTTP referrer)
- `REDIS_PASSWORD`

The old `ICLIMATE_DEFAULT_ACCOUNT_PASSWORD` is no longer used by any account (verified by hash check). Removing the
file from history (`git filter-repo` plus a force-push) rewrites shared history and needs separate approval. Rotation
is required either way. The Supabase password was never committed.
