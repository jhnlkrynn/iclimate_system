# RC2 rollback instructions (candidate 2026.11.18-rc2, fingerprint `04c7db9f…2d29d`)

Each step needs owner approval at the time. No step below has been executed against a real server.

## Before installing RC2

1. **Take a protected backup** with the production script. Record the checksum and copy both files off the host.
   ```sh
   sudo -u www-data env $(cat /etc/iclimate/backup.env) bash deploy/hostinger/iclimate-backup.sh
   ```
2. **Prove the backup restores**, into a disposable database only:
   ```sh
   RESTORE_DATABASE=iclimate_restore_$(date +%Y%m%d_%H%M%S) … bash deploy/hostinger/restore-isolated-mariadb.sh
   ```
   Then compare it with `scripts/deploy/two-role-migration-check.sh snapshot`.
3. **Snapshot the live database:**
   ```sh
   CHECK_RELEASE=rc2 scripts/deploy/two-role-migration-check.sh snapshot /root/rc2-before
   ```

## Level 1: configuration only (no code or data change)

Restore the previous `/etc/iclimate/iclimate.env` from its root-only copy. Then run `php artisan config:cache` and `systemctl reload php8.4-fpm`.

The yield settings must stay as they are under every rollback level:
- `ICLIMATE_YIELD_GATE=withhold`
- `ICLIMATE_YIELD_ACCURACY_VERIFIED=false`

## Level 2: application code back to RC1, keeping the database (preferred)

- **Why this is safe:** RC2 adds one table to the RC1 schema, `association_yield_report_submissions` (migration `2026_11_18_000001`). RC1 code does not reference that table or its model (verified with `git grep` on `d43cd1aa`). RC1 code therefore runs on the RC2 schema unchanged, and no database step is needed.
- **Cost:** the frozen-input fix is lost. RC1 estimates return to ~4.1 t/ha, and the RC1 gate still withholds them.
- **Steps:**
  1. Stop the queue worker and scheduler: `systemctl stop iclimate-queue iclimate-scheduler.timer`.
  2. Deploy the RC1 tag with the installer and the RC1 identifiers:
     ```sh
     TAG=v2026.11.18-rc1 EXPECTED_COMMIT=d43cd1aa1b3915b2b2e2928e21b207b3346f1170 \
     EXPECTED_FINGERPRINT=bd8f2c9432d62b6191a4845c8085414fa8cfeef09eb42a60e9bd4b5a54b1266f \
     BUNDLE=<rc1 bundle> MANIFEST=<rc1 manifest> bash deploy-release.sh
     ```
  3. Run `php artisan config:cache route:cache view:cache` and `php artisan queue:restart`. Then start the services again and run `php artisan iclimate:health`.
  4. Check the ML service. The model file is identical in both releases (sha256 `f3a54e12…b777c`), so no model change is needed.

## The seven migrations: what a rollback does to real data

| Migration | Up | Down |
|---|---|---|
| `2026_11_16_000001_add_association_directory_fields` | Directory columns on `farmer_organizations` | Drops those columns. Keeps the wider role column if any role exists that the old column cannot hold. |
| `2026_11_16_000002_create_association_reporting_tables` | 6 association tables (assignments, periods, reports, reviews, reminders, messages) | **Drops all six tables, with no guard. Destroys association reports, reviews and messages.** |
| `2026_11_16_000003_allow_unknown_association_farm_type` | Allows an unknown (null) association farm type | Narrows `farm_type` back to `ENUM('Rainfed','Irrigated') NOT NULL`. **Fails once directory associations without a farm type exist**; without strict SQL mode it would alter their values instead. |
| `2026_11_16_000004_create_association_chairman_invitations_table` | Invitations table | **Drops it, with no guard** |
| `2026_11_17_000001_allow_canonical_application_roles` | Widens the role column | No-op: the column stays wide, so accounts are kept |
| `2026_11_17_000002_add_farmer_account_review_history` | `farmer_account_reviews` plus review columns | **Drops the review history and the columns, with no guard** |
| `2026_11_18_000001_create_association_report_submissions` (RC2) | Append-only submission history | **Refuses** while any submission exists |

**After production use, never run `migrate:rollback` with more than `--step=1`.** Steps 2–7 permanently delete Farmer review history and association records. The 7-step rollback rehearsal was safe only because those tables were still empty. To undo further, use Level 2 (code only) or Level 4 (restore).

## Level 3: undo the RC2 database migration (only if the table itself must go)

1. Run the dry run first:
   ```sh
   php artisan migrate:rollback --step=1 --pretend
   ```
   Do this with the temporary migration account. The runtime account has no DDL rights.
2. Run the real rollback:
   ```sh
   php artisan migrate:rollback --step=1
   ```
   **It refuses if any submission history exists** ("Submission history exists; preserve it before an approved rollback"). That is intentional. Do not force it and do not delete rows. Export the history first and get a separate approval.
3. **Rehearsed 2026-11-18 on MariaDB 10.11**, on a restored copy of the audit clone (69 tables, 114,779 rows):
   - all 7 pending migrations up;
   - all 7 down;
   - up again.

   After the rollback, schema, indexes, foreign keys, row counts, checksums and migration list were identical to the pre-migration copy. After re-migrating, everything was identical to the first migration.

## Level 4: full database restore (data corruption only)

1. Restore the pre-install backup into a new disposable database with `restore-isolated-mariadb.sh`.
2. Compare it with `/root/rc2-before` using `two-role-migration-check.sh compare`.
3. Only then have the DBA switch the application to the restored database: change `DB_DATABASE` in the env file, `config:cache`, reload.
4. Records written after the backup are lost. List them first from the live database.

Never run `migrate:fresh`, `db:wipe` or a destructive rollback on the production database.
