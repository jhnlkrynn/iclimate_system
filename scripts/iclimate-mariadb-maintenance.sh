#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
XAMPP_ROOT="/Applications/XAMPP/xamppfiles"
MYSQL="$XAMPP_ROOT/bin/mysql"
MYSQLDUMP="$XAMPP_ROOT/bin/mysqldump"
MARIABACKUP="$XAMPP_ROOT/bin/mariabackup"
MYSQL_UPGRADE="$XAMPP_ROOT/bin/mysql_upgrade"
XAMPP="$XAMPP_ROOT/xampp"
DATADIR="$XAMPP_ROOT/var/mysql"
SOCKET="$DATADIR/mysql.sock"
CONFIG="$XAMPP_ROOT/etc/my.cnf"
SOURCE_DB="iclimate_staging_20261103_fix3"
BACKUP_ROOT="${ICLIMATE_MARIADB_BACKUP_ROOT:-/Users/Shared/iclimate_mariadb_backups}"
REPORT_ROOT="$PROJECT_ROOT/storage/app/mariadb-maintenance-reports"
STAMP="$(date -u +%Y%m%d-%H%M%S)-$$"
REPORT="$REPORT_ROOT/maintenance-$STAMP.report.txt"
PHYSICAL_DIR="$BACKUP_ROOT/physical-$STAMP"
LOGICAL_FILE="$BACKUP_ROOT/logical-$STAMP.sql"
RESTORE_DB="iclimate_restore_probe_${STAMP//-/_}"
CNF_BACKUP="$BACKUP_ROOT/my.cnf.$STAMP.bak"

mkdir -p "$REPORT_ROOT"
chmod 700 "$REPORT_ROOT"
exec > >(tee -a "$REPORT") 2>&1
fail() { printf 'FAIL: %s\n' "$1"; exit 1; }
trap 'printf "FAIL: maintenance stopped at line %s. Report: %s\n" "$LINENO" "$REPORT"' ERR
mysqlq() { "$MYSQL" --protocol=tcp --host=127.0.0.1 --port=3306 --user=root -Nse "$1"; }
mysql_db() { "$MYSQL" --protocol=tcp --host=127.0.0.1 --port=3306 --user=root "$1" -Nse "$2"; }
run_mariabackup() {
  sudo -u _mysql /bin/sh -c '
    ulimit -n 65536 || {
      printf "Unable to raise _mysql open-file limit; check launchctl maxfiles and administrator policy.\n" >&2
      exit 77
    }
    exec "$@"
  ' sh "$@"
}

validate_physical_backup() {
  local dir="$1" missing
  sudo -u _mysql test -f "$dir/xtrabackup_checkpoints" || return 1
  sudo -u _mysql test -f "$dir/xtrabackup_info" || return 1
  sudo grep -q '^backup_type = log-applied$' "$dir/xtrabackup_checkpoints" || return 1
  sudo grep -q '^partial = N$' "$dir/xtrabackup_info" || return 1
  sudo grep -q '^incremental = N$' "$dir/xtrabackup_info" || return 1
  missing="$(sudo -u _mysql /bin/sh -c '
    set -eu
    src="$1"; dst="$2"; work="$(mktemp -d)"
    trap "rmdir \"$work\" 2>/dev/null || true" EXIT
    find "$src" -type f -name "*.ibd" -print | sed "s#^$src/##" | sort > "$work/source"
    find "$dst" -type f -name "*.ibd" -print | sed "s#^$dst/##" | sort > "$work/backup"
    comm -23 "$work/source" "$work/backup"
  ' sh "$DATADIR" "$dir")"
  [ -z "$missing" ] || { printf 'Missing tablespace files in %s:\n%s\n' "$dir" "$missing"; return 1; }
}

printf 'iClimate MariaDB maintenance started: %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
printf 'Report: %s\n' "$REPORT"
for tool in "$MYSQL" "$MYSQLDUMP" "$MARIABACKUP" "$MYSQL_UPGRADE" "$XAMPP"; do
  [ -x "$tool" ] || fail "Required XAMPP tool unavailable: $tool"
done
[ -f "$CONFIG" ] || fail "XAMPP configuration unavailable: $CONFIG"

VERSION="$(mysqlq 'select @@version')" || fail 'MariaDB is not reachable on port 3306.'
[[ "$VERSION" == 10.4.28-MariaDB* ]] || fail "Unexpected MariaDB version: $VERSION"
TABLES="$(mysqlq "select count(*) from information_schema.tables where table_schema='$SOURCE_DB'")"
USERS="$(mysql_db "$SOURCE_DB" 'select count(*) from users')"
FKS="$(mysqlq "select count(*) from information_schema.table_constraints where constraint_schema='$SOURCE_DB' and constraint_type='FOREIGN KEY'")"
PKS="$(mysqlq "select count(*) from information_schema.table_constraints where constraint_schema='$SOURCE_DB' and constraint_type='PRIMARY KEY'")"
[ "$TABLES" = 69 ] || fail "Protected staging table count changed: $TABLES"
[ "$USERS" = 877 ] || fail "Protected staging user count changed: $USERS"
[ "$FKS" = 87 ] || fail "Protected staging foreign-key count changed: $FKS"
EVENTS_BEFORE="$(mysqlq 'select count(*) from mysql.event')" || fail 'Could not inspect MariaDB events.'
[ "$EVENTS_BEFORE" = 0 ] || fail "Unexpected existing MariaDB events: $EVENTS_BEFORE"
PROC_OK=1
COLUMN_STATS_OK=1
mysqlq 'show function status' >/dev/null 2>&1 || PROC_OK=0
mysqlq 'select count(*) from mysql.column_stats' >/dev/null 2>&1 || COLUMN_STATS_OK=0
if [ "$COLUMN_STATS_OK" = 1 ]; then
  COLUMN_STATS_COLUMNS="$(mysqlq "select count(*) from information_schema.columns where table_schema='mysql' and table_name='column_stats' and column_name in ('min_value','max_value') and column_type='varbinary(255)'")"
  [ "$COLUMN_STATS_COLUMNS" = 2 ] || COLUMN_STATS_OK=0
fi
printf 'Preflight PASS: MariaDB=%s tables=%s users=%s primary_keys=%s foreign_keys=%s events=%s scheduler=%s proc=%s column_stats=%s\n' "$VERSION" "$TABLES" "$USERS" "$PKS" "$FKS" "$EVENTS_BEFORE" "$(mysqlq 'select @@event_scheduler')" "$PROC_OK" "$COLUMN_STATS_OK"

row_total() {
  local db="$1" total=0 table_name count
  while IFS= read -r table_name; do
    case "$table_name" in
      cache|cache_locks|failed_jobs|job_batches|jobs|migrations|sessions) continue ;;
    esac
    count="$(mysql_db "$db" "select count(*) from \`$table_name\`")"
    total=$((total + count))
  done < <(mysqlq "select table_name from information_schema.tables where table_schema='$db' and table_type='BASE TABLE' order by table_name")
  printf '%s' "$total"
}

SOURCE_APP_ROWS="$(row_total "$SOURCE_DB")"
[ "$SOURCE_APP_ROWS" = 107274 ] || fail "Protected staging application-row count changed: $SOURCE_APP_ROWS"
printf 'Protected staging application rows: %s\n' "$SOURCE_APP_ROWS"

read -r -p 'Type YES to authorize local maintenance (no password is requested here): ' APPROVAL
[ "$APPROVAL" = YES ] || fail 'Explicit YES confirmation was not provided.'
sudo -v || fail 'Administrator authentication was not completed.'
sudo -u _mysql tail -n 120 "$DATADIR/Lukes-Air.err" || fail 'Could not inspect MariaDB startup log.'

sudo mkdir -p "$BACKUP_ROOT"
sudo chown _mysql:admin "$BACKUP_ROOT"
sudo chmod 700 "$BACKUP_ROOT"
sudo -u _mysql test -x "$BACKUP_ROOT" || fail "_mysql cannot traverse backup destination: $BACKUP_ROOT"
sudo -u _mysql test -w "$BACKUP_ROOT" || fail "_mysql cannot write backup destination: $BACKUP_ROOT"
USE_EXISTING=0
while IFS= read -r EXISTING_CHECKPOINT; do
  [ "$USE_EXISTING" = 0 ] || break
  [ -n "$EXISTING_CHECKPOINT" ] || continue
  CANDIDATE_DIR="$(dirname "$EXISTING_CHECKPOINT")"
  CANDIDATE_STAMP="$(basename "$CANDIDATE_DIR" | sed 's/^physical-//')"
  CANDIDATE_REPORT="$REPORT_ROOT/maintenance-$CANDIDATE_STAMP.report.txt"
  if [ -f "$CANDIDATE_REPORT" ] && grep -qi 'too many open files' "$CANDIDATE_REPORT"; then
    printf 'Rejecting existing backup because its report contains OS error 24: %s\n' "$CANDIDATE_DIR"
  elif validate_physical_backup "$CANDIDATE_DIR"; then
    PHYSICAL_DIR="$CANDIDATE_DIR"
    USE_EXISTING=1
    printf 'Using existing complete physical backup: %s\n' "$PHYSICAL_DIR"
  else
    printf 'Rejecting existing incomplete physical backup: %s\n' "$CANDIDATE_DIR"
  fi
done < <(sudo find "$BACKUP_ROOT" "$(dirname "$PROJECT_ROOT")/iclimate_mariadb_backups" /private/tmp /tmp -maxdepth 4 -type f -name xtrabackup_checkpoints -print 2>/dev/null | sort -r)
if [ "$USE_EXISTING" = 0 ]; then
  [ ! -e "$PHYSICAL_DIR" ] || fail "Refusing to overwrite existing backup directory: $PHYSICAL_DIR"
  sudo mkdir -p "$PHYSICAL_DIR"
  sudo chown _mysql:admin "$PHYSICAL_DIR"
  sudo chmod 700 "$PHYSICAL_DIR"
  run_mariabackup "$MARIABACKUP" --backup --open-files-limit=65536 --target-dir="$PHYSICAL_DIR" --datadir="$DATADIR" --socket="$SOCKET" --user=root
  run_mariabackup "$MARIABACKUP" --prepare --open-files-limit=65536 --target-dir="$PHYSICAL_DIR"
fi
validate_physical_backup "$PHYSICAL_DIR" || fail 'Physical backup failed checkpoint or tablespace completeness validation.'
printf 'Physical backup PASS: %s\n' "$PHYSICAL_DIR"
sudo grep -E '^(backup_type|partial|incremental|from_lsn|to_lsn|last_lsn)' "$PHYSICAL_DIR/xtrabackup_checkpoints"

if [ "$PROC_OK" != 1 ] || [ "$COLUMN_STATS_OK" != 1 ]; then
  sudo -u _mysql "$MYSQL_UPGRADE" --protocol=socket --socket="$SOCKET" --user=root --force
  mysqlq 'show function status' >/dev/null || fail 'mysql_upgrade did not repair system metadata.'
  mysqlq 'show procedure status' >/dev/null || fail 'Stored-procedure compatibility failed after mysql_upgrade.'
  mysqlq 'select count(*) from mysql.column_stats' >/dev/null || fail 'mysql.column_stats remains incompatible after mysql_upgrade.'
  [ "$(mysqlq "select count(*) from information_schema.columns where table_schema='mysql' and table_name='column_stats' and column_name in ('min_value','max_value') and column_type='varbinary(255)'")" = 2 ] || fail 'mysql.column_stats column definitions remain incompatible after mysql_upgrade.'
else
  printf 'mysql.proc compatibility PASS; mysql_upgrade not repeated.\n'
fi

sudo cp -p "$CONFIG" "$CNF_BACKUP"
printf 'Configuration backup PASS: %s\n' "$CNF_BACKUP"
ACTIVE="$(sudo awk '!/^[[:space:]]*#/ && /^[[:space:]]*event_scheduler[[:space:]]*=/' "$CONFIG" | wc -l | tr -d ' ')"
[ "$ACTIVE" -le 1 ] || fail 'Conflicting active event_scheduler entries exist.'
if [ "$ACTIVE" = 1 ]; then
  sudo /usr/bin/perl -0pi -e 's/^[ \t]*event_scheduler[ \t]*=[ \t]*OFF[ \t]*$/event_scheduler=ON/mi' "$CONFIG"
else
  sudo /usr/bin/perl -0pi -e 's/^(\[mysqld\][^\n]*\n)/$1event_scheduler=ON\n/mi' "$CONFIG"
fi
sudo grep -q '^event_scheduler=ON$' "$CONFIG" || fail 'event_scheduler=ON was not written.'

restart_mysql() {
  sudo "$XAMPP" stopmysql
  sudo "$XAMPP" startmysql
}

if ! restart_mysql; then
  sudo cp -p "$CNF_BACKUP" "$CONFIG"
  restart_mysql || true
  fail 'MariaDB restart failed; previous my.cnf was restored.'
fi
READY=0
for _ in $(seq 1 30); do
  if mysqlq 'select 1' >/dev/null 2>&1; then READY=1; break; fi
  sleep 1
done
if [ "$READY" != 1 ]; then
  sudo cp -p "$CNF_BACKUP" "$CONFIG"
  restart_mysql || true
  fail 'MariaDB did not become ready; previous my.cnf was restored.'
fi
if [ "$(mysqlq 'select @@event_scheduler')" != ON ]; then
  printf 'event_scheduler remained OFF after restart; enabling it explicitly at runtime.\n'
  mysqlq 'set global event_scheduler=ON' || fail 'Could not enable event_scheduler at runtime.'
fi
[ "$(mysqlq 'select @@event_scheduler')" = ON ] || fail 'event_scheduler is not ON after restart or runtime enablement.'
[ "$(mysqlq 'select count(*) from mysql.event')" = "$EVENTS_BEFORE" ] || fail 'MariaDB event catalog changed unexpectedly.'
mysqlq 'show function status' >/dev/null || fail 'SHOW FUNCTION STATUS failed after restart.'
mysqlq 'show procedure status' >/dev/null || fail 'SHOW PROCEDURE STATUS failed after restart.'
printf 'Restart/configuration PASS: scheduler=ON events=%s\n' "$EVENTS_BEFORE"

[ ! -e "$LOGICAL_FILE" ] || fail "Refusing to overwrite logical backup: $LOGICAL_FILE"
sudo -u _mysql "$MYSQLDUMP" --host=127.0.0.1 --port=3306 --user=root --single-transaction --triggers --routines --events --no-tablespaces --default-character-set=utf8mb4 --result-file="$LOGICAL_FILE" "$SOURCE_DB"
sudo -u _mysql test -s "$LOGICAL_FILE" || fail 'Logical backup is empty.'
sudo -u _mysql grep -q -- '-- Dump completed' "$LOGICAL_FILE" || fail 'Logical backup has no completion marker.'
sudo -u _mysql grep -q 'CREATE TABLE' "$LOGICAL_FILE" || fail 'Logical backup contains no table definitions.'
printf 'Logical backup PASS: %s (%s bytes)\n' "$LOGICAL_FILE" "$(sudo -u _mysql wc -c "$LOGICAL_FILE" | awk '{print $1}')"

mysqlq "select if(count(*)=0,'ok','exists') from information_schema.schemata where schema_name='$RESTORE_DB'" | grep -qx ok || fail "Disposable database already exists: $RESTORE_DB"
"$MYSQL" --protocol=tcp --host=127.0.0.1 --port=3306 --user=root -e "create database $RESTORE_DB character set utf8mb4 collate utf8mb4_unicode_ci"
sudo -u _mysql /bin/sh -c "exec '$MYSQL' --protocol=tcp --host=127.0.0.1 --port=3306 --user=root '$RESTORE_DB' < '$LOGICAL_FILE'"
printf 'Logical restore completed: %s\n' "$RESTORE_DB"
RESTORE_TABLES="$(mysqlq "select count(*) from information_schema.tables where table_schema='$RESTORE_DB'")"
RESTORE_PKS="$(mysqlq "select count(*) from information_schema.table_constraints where constraint_schema='$RESTORE_DB' and constraint_type='PRIMARY KEY'")"
RESTORE_FKS="$(mysqlq "select count(*) from information_schema.table_constraints where constraint_schema='$RESTORE_DB' and constraint_type='FOREIGN KEY'")"
RESTORE_USERS="$(mysql_db "$RESTORE_DB" 'select count(*) from users')"
RESTORE_APP_ROWS="$(row_total "$RESTORE_DB")"
[ "$RESTORE_TABLES" = "$TABLES" ] || fail "Restored table count mismatch: $RESTORE_TABLES vs $TABLES"
[ "$RESTORE_PKS" = "$PKS" ] || fail "Restored primary-key count mismatch: $RESTORE_PKS vs $PKS"
[ "$RESTORE_USERS" = "$USERS" ] || fail "Restored user count mismatch: $RESTORE_USERS vs $USERS"
[ "$RESTORE_FKS" = "$FKS" ] || fail "Restored foreign-key count mismatch: $RESTORE_FKS vs $FKS"
[ "$RESTORE_APP_ROWS" = "$SOURCE_APP_ROWS" ] || fail "Restored application-row count mismatch: $RESTORE_APP_ROWS vs $SOURCE_APP_ROWS"

env APP_ENV=testing DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE="$RESTORE_DB" DB_USERNAME=root DB_PASSWORD= php "$PROJECT_ROOT/artisan" migrate --database=mysql --path=database/migrations/2026_11_03_000005_create_supabase_timestamp_equivalent_triggers.php --force
TRIGGERS="$(mysqlq "select count(*) from information_schema.triggers where trigger_schema='$RESTORE_DB'")"
[ "$TRIGGERS" = 3 ] || fail "Disposable restore does not have all three timestamp triggers: $TRIGGERS"
SOURCE_ROUTINES="$(mysqlq "select count(*) from information_schema.routines where routine_schema='$SOURCE_DB'")"
RESTORE_ROUTINES="$(mysqlq "select count(*) from information_schema.routines where routine_schema='$RESTORE_DB'")"
SOURCE_EVENTS="$(mysqlq "select count(*) from information_schema.events where event_schema='$SOURCE_DB'")"
RESTORE_EVENTS="$(mysqlq "select count(*) from information_schema.events where event_schema='$RESTORE_DB'")"
[ "$SOURCE_ROUTINES" = "$RESTORE_ROUTINES" ] || fail "Routine count mismatch: $RESTORE_ROUTINES vs $SOURCE_ROUTINES"
[ "$SOURCE_EVENTS" = "$RESTORE_EVENTS" ] || fail "Event count mismatch: $RESTORE_EVENTS vs $SOURCE_EVENTS"
printf 'Trigger definitions in disposable target:\n'
mysqlq "select trigger_name, event_object_table, action_timing, event_manipulation from information_schema.triggers where trigger_schema='$RESTORE_DB' order by trigger_name"
printf 'Restore integrity PASS: tables=%s application_rows=%s users=%s primary_keys=%s foreign_keys=%s triggers=%s routines=%s events=%s\n' "$RESTORE_TABLES" "$RESTORE_APP_ROWS" "$RESTORE_USERS" "$RESTORE_PKS" "$RESTORE_FKS" "$TRIGGERS" "$RESTORE_ROUTINES" "$RESTORE_EVENTS"
printf 'Protected staging recheck: tables=%s users=%s foreign_keys=%s\n' "$(mysqlq "select count(*) from information_schema.tables where table_schema='$SOURCE_DB'")" "$(mysql_db "$SOURCE_DB" 'select count(*) from users')" "$(mysqlq "select count(*) from information_schema.table_constraints where constraint_schema='$SOURCE_DB' and constraint_type='FOREIGN KEY'")"

TEST_ENV=(APP_ENV=testing DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=iclimate_test_mysql_20261103 DB_USERNAME=root DB_PASSWORD=)
env "${TEST_ENV[@]}" php "$PROJECT_ROOT/artisan" test --compact tests/Feature/DatabaseBackupTest.php tests/Feature/BackupVerificationTest.php tests/Feature/SystemHealthTest.php tests/Unit/Support/DeterministicRowHashTest.php
env "${TEST_ENV[@]}" php "$PROJECT_ROOT/artisan" test --compact
printf 'PASS: full MariaDB maintenance completed.\nPhysical backup: %s\nLogical backup: %s\nDisposable restore: %s\nProduction cutover was not performed.\n' "$PHYSICAL_DIR" "$LOGICAL_FILE" "$RESTORE_DB"

