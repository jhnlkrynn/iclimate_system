# Phase 18 deployment runbook: six two-role migrations

**For:** the operator applying the migrations on the iClimate Mac (project root `/Users/johnlukereynon/iclimate_system`,
zsh). **Requires:** the temporary `iclimate_migrator` account (`phase-18-dba-handoff.md`) and the project owner's
explicit approval for part B. Part A changes nothing.

Rehearsed on a full real-data copy (2026-11-18): every step below passed. The six migrations take about 0.6 s.

## A. Deployment gate (read-only)

```sh
P=storage/app/backups/preflight-2026-11-18
php artisan tinker --execute='echo DB::getDatabaseName(), PHP_EOL;'      # iclimate_staging_20261103_fix3
php artisan migrate:status | grep Pending                                 # exactly the six 2026_11_16/17 files
shasum -a 256 -c $P/migration-files.sha256                                # six lines ending ": OK" (tested versions)
test ! -f bootstrap/cache/config.php && echo "config not cached: OK"      # the per-command credentials will be used
bash -n scripts/deploy/two-role-migration-check.sh && echo "checker OK"

# Credentials: typed at hidden prompts, kept only in this shell's memory. Never put them in .env.
read -r "MIG_USER?Migration account: "; read -rs "MIG_PASS?Password: "; echo

# The account is the right one, scoped to this database (the password hash line is filtered out):
DB_USERNAME="$MIG_USER" DB_PASSWORD="$MIG_PASS" php artisan tinker --execute='echo DB::selectOne("select current_user() u")->u, PHP_EOL; foreach (DB::select("show grants") as $g) { $l = current((array) $g); if (str_contains($l, " ON `")) echo $l, PHP_EOL; }'
# It can do what the migrations need (prints SQL only; changes nothing). Expect 8:
DB_USERNAME="$MIG_USER" DB_PASSWORD="$MIG_PASS" php artisan migrate --pretend --force | grep -ci "create table"
```

Stop if any line differs from what is shown.

## B. Approved execution (only after explicit approval)

```sh
php artisan down --retry=60                        # Farmers/MAO see the maintenance page (about 1 minute)
php artisan iclimate:backup-database --no-prune
B=$(ls -t storage/app/backups/*.backup | head -1); echo "$B"
tail -1 "$B"; grep -c '^CREATE TABLE' "$B"         # "-- Dump completed on ...", 69
shasum -a 256 "$B" > "$B.sha256"
scripts/deploy/two-role-migration-check.sh snapshot $P/window-before
php artisan migrate:status | grep -c Pending       # 6

DB_USERNAME="$MIG_USER" DB_PASSWORD="$MIG_PASS" php artisan migrate --force

php artisan migrate:status | grep -c Pending       # 0
scripts/deploy/two-role-migration-check.sh snapshot $P/window-after
scripts/deploy/two-role-migration-check.sh compare $P/window-before $P/window-after   # every line OK, exit 0
```

If every check is OK:

```sh
php artisan up
php artisan queue:restart                          # the only restart; the supervisor relaunches the worker in ~5 s
unset MIG_USER MIG_PASS
```

Then ask the DBA to lock the account (handoff step 4).

Do not change `CACHE_STORE` for the migrate command: the schema cache is refreshed after the migration, and the
compatibility guards switch off on their own.

## C. Recovery (if `migrate` or `compare` fails, while still in maintenance)

MariaDB cannot roll back schema changes, so a failed migration can leave a partial state. The application is still
down, so no new records exist. Restore the backup taken in B, then compare it with `window-before`:

```sh
export MYSQL_PWD="$MIG_PASS"; DB=iclimate_staging_20261103_fix3
shasum -a 256 -c "$B.sha256"
T=$(mysql -h127.0.0.1 -u"$MIG_USER" -N -e "select group_concat(concat('\`',table_name,'\`')) from information_schema.tables where table_schema='$DB'")
mysql -h127.0.0.1 -u"$MIG_USER" "$DB" -e "SET FOREIGN_KEY_CHECKS=0; DROP TABLE $T; SET FOREIGN_KEY_CHECKS=1;"
mysql -h127.0.0.1 -u"$MIG_USER" "$DB" < "$B"
unset MYSQL_PWD
scripts/deploy/two-role-migration-check.sh snapshot $P/window-restored
diff $P/window-before/row-counts.txt $P/window-restored/row-counts.txt && diff $P/window-before/checksums-untouched.txt $P/window-restored/checksums-untouched.txt && echo RESTORED
php artisan up      # the application runs safely on the old schema (compatibility guards)
```

This exact drop-and-reload was verified twice on the real-data copy (all 69 tables, row counts and checksums matched).

**After `php artisan up`, once real sign-ups or reviews exist, do not roll back.** Both `migrate:rollback` and a
restore would delete review history and new accounts. Fix forward instead. Use `migrate:rollback --step=6` only if
there are still no rows in `farmer_account_reviews` and no new users since `window-before`.
