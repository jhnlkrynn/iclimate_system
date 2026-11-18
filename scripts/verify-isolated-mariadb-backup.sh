#!/usr/bin/env bash
set -Eeuo pipefail

BACKUP_DIR="${ICLIMATE_PHYSICAL_BACKUP:-/Users/Shared/iclimate_mariadb_backups/physical-20261105-055451-8085}"
XAMPP_ROOT="/Applications/XAMPP/xamppfiles"
ACTIVE_DATADIR="$XAMPP_ROOT/var/mysql"
MYSQLD="$XAMPP_ROOT/sbin/mysqld"
MYSQL="$XAMPP_ROOT/bin/mysql"
MYSQLADMIN="$XAMPP_ROOT/bin/mysqladmin"
MYSQLCHECK="$XAMPP_ROOT/bin/mysqlcheck"
RECOVERY_ROOT="${ICLIMATE_MARIADB_RECOVERY_ROOT:-/Users/Shared/iclimate_mariadb_recovery}"
STAMP="$(date -u +%Y%m%d-%H%M%S)-$$"
RECOVERY_DIR="$RECOVERY_ROOT/recovery-$STAMP"
DATA_DIR="$RECOVERY_DIR/data"
SOCKET="$RECOVERY_DIR/recovery.sock"
PIDFILE="$RECOVERY_DIR/recovery.pid"
ERROR_LOG="$RECOVERY_DIR/recovery.err"
SOURCE_DB="${ICLIMATE_RECOVERY_DATABASE:-iclimate_staging_20261103_fix3}"
SERVER_PID=""

fail() { printf 'FAIL: %s\n' "$1" >&2; exit 1; }
trap 'printf "FAIL: isolated recovery stopped. Preserved directory: %s\n" "$RECOVERY_DIR" >&2' ERR

[ "${EUID:-$(id -u)}" -eq 0 ] || fail 'Run this harness from Terminal with administrator authorization.'
[ -x "$MYSQLD" ] || fail "XAMPP mysqld is unavailable: $MYSQLD"
[ -x "$MYSQL" ] || fail "XAMPP mysql client is unavailable: $MYSQL"
[ -x "$MYSQLADMIN" ] || fail "XAMPP mysqladmin is unavailable: $MYSQLADMIN"
[ -x "$MYSQLCHECK" ] || fail "XAMPP mysqlcheck is unavailable: $MYSQLCHECK"
[ -d "$BACKUP_DIR" ] || fail "Physical backup directory is unavailable: $BACKUP_DIR"
[ "$BACKUP_DIR" != "$ACTIVE_DATADIR" ] || fail 'Refusing to use the active XAMPP datadir as a recovery source.'

printf 'Backup source: %s\n' "$BACKUP_DIR"
printf 'Recovery directory: %s\n' "$RECOVERY_DIR"
VERSION_OUTPUT="$("$MYSQLD" --version)"
printf 'MariaDB binary: %s\n' "$VERSION_OUTPUT"
[[ "$VERSION_OUTPUT" == *10.4.28-MariaDB* ]] || fail 'Recovery binary is not MariaDB 10.4.28.'
[ -f "$BACKUP_DIR/xtrabackup_checkpoints" ] || fail 'xtrabackup_checkpoints is missing.'
[ -f "$BACKUP_DIR/xtrabackup_info" ] || fail 'xtrabackup_info is missing.'
grep -q '^backup_type = log-applied$' "$BACKUP_DIR/xtrabackup_checkpoints" || fail 'Backup is not prepared with log-applied checkpoints.'
grep -q '^partial = N$' "$BACKUP_DIR/xtrabackup_info" || fail 'Backup is marked partial.'
grep -q '^incremental = N$' "$BACKUP_DIR/xtrabackup_info" || fail 'Backup is marked incremental.'
grep -E '^(backup_type|from_lsn|to_lsn|last_lsn)' "$BACKUP_DIR/xtrabackup_checkpoints"
grep -E '^(partial|incremental)' "$BACKUP_DIR/xtrabackup_info"

[ ! -e "$RECOVERY_DIR" ] || fail "Refusing to overwrite recovery directory: $RECOVERY_DIR"
mkdir -p "$DATA_DIR"
chmod 700 "$RECOVERY_DIR" "$DATA_DIR"
printf 'Copying prepared backup into isolated storage; original remains untouched.\n'
cp -a "$BACKUP_DIR/." "$DATA_DIR/"
chown -R _mysql:admin "$RECOVERY_DIR"
chmod 700 "$RECOVERY_DIR" "$DATA_DIR"

cleanup() {
  if [ -n "$SERVER_PID" ] && kill -0 "$SERVER_PID" 2>/dev/null; then
    "$MYSQLADMIN" --protocol=socket --socket="$SOCKET" --user=root shutdown >/dev/null 2>&1 || kill "$SERVER_PID" 2>/dev/null || true
  fi
}
trap cleanup EXIT

printf 'Starting isolated MariaDB with networking disabled and no external defaults.\n'
sudo -u _mysql "$MYSQLD" --no-defaults --basedir="$XAMPP_ROOT" --datadir="$DATA_DIR" --socket="$SOCKET" --pid-file="$PIDFILE" --log-error="$ERROR_LOG" --port=0 --skip-networking --skip-name-resolve --skip-grant-tables --event-scheduler=OFF >/dev/null 2>&1 &
SERVER_PID=$!

READY=0
for _ in $(seq 1 60); do
  if "$MYSQL" --protocol=socket --socket="$SOCKET" --user=root -Nse 'select 1' >/dev/null 2>&1; then READY=1; break; fi
  if ! kill -0 "$SERVER_PID" 2>/dev/null; then break; fi
  sleep 1
done
[ "$READY" = 1 ] || { tail -n 80 "$ERROR_LOG" 2>/dev/null || true; fail 'Isolated MariaDB did not become ready.'; }

VERSION="$("$MYSQL" --protocol=socket --socket="$SOCKET" --user=root -Nse 'select @@version')"
TABLES="$("$MYSQL" --protocol=socket --socket="$SOCKET" --user=root -Nse "select count(*) from information_schema.tables where table_schema='$SOURCE_DB'")"
USERS="$("$MYSQL" --protocol=socket --socket="$SOCKET" --user=root "$SOURCE_DB" -Nse 'select count(*) from users')"
FKS="$("$MYSQL" --protocol=socket --socket="$SOCKET" --user=root -Nse "select count(*) from information_schema.table_constraints where constraint_schema='$SOURCE_DB' and constraint_type='FOREIGN KEY'")"
APP_ROWS=0
while IFS= read -r table_name; do
  case "$table_name" in cache|cache_locks|failed_jobs|job_batches|jobs|migrations|sessions) continue ;; esac
  count="$("$MYSQL" --protocol=socket --socket="$SOCKET" --user=root "$SOURCE_DB" -Nse "select count(*) from $table_name")"
  APP_ROWS=$((APP_ROWS + count))
done < <("$MYSQL" --protocol=socket --socket="$SOCKET" --user=root -Nse "select table_name from information_schema.tables where table_schema='$SOURCE_DB' and table_type='BASE TABLE' order by table_name")
printf 'Isolated server PASS: version=%s tables=%s users=%s application_rows=%s foreign_keys=%s\n' "$VERSION" "$TABLES" "$USERS" "$APP_ROWS" "$FKS"
"$MYSQLCHECK" --protocol=socket --socket="$SOCKET" --user=root --all-databases --check >/dev/null || fail 'Integrity check failed; recovery directory and log were preserved.'
printf 'Integrity check PASS.\n'
"$MYSQLADMIN" --protocol=socket --socket="$SOCKET" --user=root shutdown
for _ in $(seq 1 30); do
  if ! kill -0 "$SERVER_PID" 2>/dev/null; then SERVER_PID=""; break; fi
  sleep 1
done
[ -z "$SERVER_PID" ] || fail 'Isolated MariaDB did not stop cleanly.'
printf 'Clean shutdown PASS. Recovery directory preserved: %s\n' "$RECOVERY_DIR"
