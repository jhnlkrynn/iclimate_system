#!/usr/bin/env bash
set -Eeuo pipefail

# Host-terminal runner. It never contacts Supabase and never writes to the
# existing MariaDB database. The only database created is a unique disposable
# PostgreSQL database, dropped on exit.

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BACKUP="${ICLIMATE_RECON_BACKUP:-$PROJECT_ROOT/storage/app/backups/supabase-full-20261103.backup}"
REPORT="${ICLIMATE_RECON_REPORT:-$PROJECT_ROOT/docs/SUPABASE_TO_MARIADB_DATA_RECONCILIATION.md}"
JSON_REPORT="${ICLIMATE_RECON_JSON:-$PROJECT_ROOT/storage/app/reconciliation/supabase-to-mariadb-live.json}"
XAMPP_ROOT="${ICLIMATE_XAMPP_ROOT:-/Applications/XAMPP/xamppfiles}"
MYSQL="${XAMPP_ROOT}/bin/mysql"
PG_RESTORE="${PG_RESTORE:-$(command -v pg_restore || true)}"
CREATEDB="${CREATEDB:-$(command -v createdb || true)}"
DROPDB="${DROPDB:-$(command -v dropdb || true)}"
PSQL="${PSQL:-$(command -v psql || true)}"
POSTGRES="${POSTGRES:-$(command -v postgres || true)}"
INITDB="${INITDB:-$(command -v initdb || true)}"
PG_CTL="${PG_CTL:-$(command -v pg_ctl || true)}"
PHP_BIN="${PHP_BIN:-php}"

fail() { printf 'FAIL: %s\n' "$1" >&2; exit 1; }
[ -r "$BACKUP" ] || fail "Backup is not readable: $BACKUP"
[ -x "$MYSQL" ] || fail "XAMPP mysql client unavailable: $MYSQL"
[ -x "$PG_RESTORE" ] || fail 'pg_restore is unavailable; install PostgreSQL client tools.'
[ -x "$CREATEDB" ] || fail 'createdb is unavailable; install a local PostgreSQL server/client.'
[ -x "$DROPDB" ] || fail 'dropdb is unavailable; install a local PostgreSQL server/client.'
[ -x "$PSQL" ] || fail 'psql is unavailable; install PostgreSQL client tools.'

printf 'Checking the existing local MariaDB startup path...\n'
if ! bash "$PROJECT_ROOT/scripts/start-iclimate-local.sh" check; then
  cat >&2 <<'EOF'
The workspace cannot invoke macOS sudo. Run this command directly in a macOS
Terminal window, then rerun this reconciliation script:

  cd /Users/johnlukereynon/iclimate_system
  bash scripts/start-iclimate-local.sh check

Do not paste the password into chat. If the local root account has a password,
set it only in that Terminal session:

  export ICLIMATE_LOCAL_DB_PASSWORD='your-local-password'
EOF
  fail 'MariaDB is not reachable from this process.'
fi

PG_DB="iclimate_reconcile_$(date -u +%Y%m%d%H%M%S)_$$"
PG_TEMP=""
PG_TEMP_DATA=""
PG_TEMP_SOCKET=""
PG_TEMP_STARTED=0
RESTORE_LIST=""
PG_MAINTENANCE_DB="${RECON_PG_MAINTENANCE_DB:-postgres}"
PG_HOST="${RECON_PG_HOST:-${PGHOST:-}}"
PG_PORT="${RECON_PG_PORT:-${PGPORT:-}}"

choose_unused_port() {
  local port
  for port in $(seq 55432 55482); do
    if ! lsof -nP -iTCP:"$port" -sTCP:LISTEN >/dev/null 2>&1; then
      printf '%s' "$port"
      return 0
    fi
  done
  return 1
}

if [ -x "$POSTGRES" ] && [ -x "$INITDB" ] && [ -x "$PG_CTL" ]; then
  PG_TEMP="$(mktemp -d "${TMPDIR:-/tmp}/iclimate-pg-reconcile.XXXXXX")"
  PG_TEMP_DATA="$PG_TEMP/data"
  PG_TEMP_SOCKET="$PG_TEMP/socket"
  mkdir -m 700 "$PG_TEMP_SOCKET"
  PG_PORT="${PG_PORT:-$(choose_unused_port)}" || fail 'No unused local PostgreSQL port was found.'
  PG_HOST="$PG_TEMP_SOCKET"
  printf 'Initializing isolated PostgreSQL cluster at %s\n' "$PG_TEMP_DATA"
  "$INITDB" --no-locale --encoding=UTF8 --auth=trust --username="${RECON_PG_USER:-$(id -un)}" "$PG_TEMP_DATA" >/dev/null
  "$PG_CTL" -D "$PG_TEMP_DATA" -o "-p $PG_PORT -k $PG_TEMP_SOCKET -h ''" -l "$PG_TEMP/server.log" -w start >/dev/null
  PG_TEMP_STARTED=1
else
  if [ -z "$PG_HOST" ]; then PG_HOST="127.0.0.1"; fi
  if [ -z "$PG_PORT" ]; then PG_PORT="5432"; fi
  if [ ! -x "$POSTGRES" ]; then
    cat >&2 <<'EOF'
The PostgreSQL client tools are present, but the PostgreSQL server binary is
missing. Nothing was installed automatically. Install the free local server
manually, then rerun this script:

  brew install postgresql@16
  export PATH="/opt/homebrew/opt/postgresql@16/bin:$PATH"
  cd /Users/johnlukereynon/iclimate_system
  bash scripts/reconcile-supabase-mariadb.sh

The script will create and clean up its own isolated cluster; no Homebrew
PostgreSQL service needs to be started separately.
EOF
    fail 'PostgreSQL server binary is unavailable.'
  fi
  printf 'Using PostgreSQL at %s:%s\n' "$PG_HOST" "$PG_PORT"
fi

export PGHOST="$PG_HOST"
export PGPORT="$PG_PORT"
export PGUSER="${RECON_PG_USER:-${PGUSER:-$(id -un)}}"
export PGDATABASE="$PG_MAINTENANCE_DB"
cleanup() {
  if [ "$PG_TEMP_STARTED" = 1 ]; then
    "$PG_CTL" -D "$PG_TEMP_DATA" -m fast -w stop >/dev/null 2>&1 || true
  fi
  if [ -n "$PG_TEMP" ] && [ "${KEEP_RECON_DB:-0}" != 1 ]; then
    rm -rf "$PG_TEMP"
  fi
  [ -z "$RESTORE_LIST" ] || rm -f "$RESTORE_LIST"
  if [ "${KEEP_RECON_DB:-0}" != 1 ] && [ -z "$PG_TEMP" ]; then
    "$DROPDB" --if-exists --maintenance-db="$PG_MAINTENANCE_DB" "$PG_DB" >/dev/null 2>&1 || true
  elif [ "$PG_TEMP_STARTED" = 1 ] && [ "${KEEP_RECON_DB:-0}" = 1 ]; then
    printf 'Disposable PostgreSQL database preserved: %s\n' "$PG_DB"
  fi
}
trap cleanup EXIT

mkdir -p "$(dirname "$JSON_REPORT")"
printf 'Creating disposable PostgreSQL database: %s\n' "$PG_DB"
"$CREATEDB" --host="$PG_HOST" --port="$PG_PORT" --maintenance-db="$PG_MAINTENANCE_DB" "$PG_DB"
# pg_restore's --schema filter selects objects inside the schema but may omit
# the SCHEMA TOC item itself. Create only this disposable schema explicitly;
# pg_restore still applies its normal pre-data, data, and post-data ordering
# for enums, sequences, tables, rows, indexes, constraints, and triggers.
"$PSQL" --host="$PG_HOST" --port="$PG_PORT" --dbname="$PG_DB" --command='CREATE SCHEMA IF NOT EXISTS iclimate_laravel' >/dev/null
printf 'Restoring application schema in PostgreSQL archive order (pre-data, data, post-data).\n'
RESTORE_LIST="$(mktemp "${TMPDIR:-/tmp}/iclimate-restore-list.XXXXXX")"
"$PG_RESTORE" --list "$BACKUP" | awk '$0 !~ / SCHEMA - iclimate_laravel /' > "$RESTORE_LIST"
"$PG_RESTORE" --exit-on-error --no-owner --schema=iclimate_laravel --use-list="$RESTORE_LIST" --dbname="$PG_DB" "$BACKUP"

export RECON_BACKUP="$BACKUP"
export RECON_REPORT="$REPORT"
export RECON_JSON="$JSON_REPORT"
export RECON_PG_DATABASE="$PG_DB"
export RECON_PG_HOST="$PG_HOST"
export RECON_PG_PORT="$PG_PORT"
export RECON_PG_USER="$PGUSER"
export RECON_MYSQL_DATABASE="${ICLIMATE_LOCAL_DB_DATABASE:-iclimate_staging_20261103_fix3}"
export RECON_MYSQL_USER="${ICLIMATE_LOCAL_DB_USERNAME:-root}"
export RECON_MYSQL_PASSWORD="${ICLIMATE_LOCAL_DB_PASSWORD:-}"
if [ -n "${ICLIMATE_RECON_PHP_MEMORY_LIMIT:-}" ]; then
  "$PHP_BIN" -d "memory_limit=$ICLIMATE_RECON_PHP_MEMORY_LIMIT" "$PROJECT_ROOT/scripts/reconcile-supabase-mariadb.php"
else
  "$PHP_BIN" "$PROJECT_ROOT/scripts/reconcile-supabase-mariadb.php"
fi
