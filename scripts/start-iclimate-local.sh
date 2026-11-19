#!/usr/bin/env bash
# iClimate local launcher (macOS + XAMPP MariaDB). Local only: it never contacts Supabase or Hostinger and never
# edits .env. The application connects with the account in .env (the restricted iclimate_app), never root.
#
#   bash scripts/start-iclimate-local.sh start     start every service in the background (skips ones already running)
#   bash scripts/start-iclimate-local.sh status    health of every service
#   bash scripts/start-iclimate-local.sh stop      stop every service started by this script (MariaDB is left alone)
#   bash scripts/start-iclimate-local.sh restart   stop, then start
#   bash scripts/start-iclimate-local.sh check     prerequisites + database checks only
#   bash scripts/start-iclimate-local.sh web|ml|queue|scheduler|reverb   run one service in the foreground
#   bash scripts/start-iclimate-local.sh install-autostart   start at login and re-check every 5 min (launchd agent)
#   bash scripts/start-iclimate-local.sh remove-autostart    remove that agent (running services are left alone)
#
# Services: web (php artisan serve :8000), ml (uvicorn :8010), queue (analytics,default,broadcasts),
# scheduler (schedule:work, every minute), reverb (:8080, only when BROADCAST_CONNECTION=reverb).
# Each background service runs under a small restart loop (the queue worker exits hourly by design), in its own
# process group, with its PID in storage/logs/local-services/ and its log in storage/logs/local-<service>.log.
# MariaDB itself is managed by XAMPP; pass --start-mariadb to let this script run `sudo xampp startmysql`.
set -Euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
XAMPP_ROOT="${ICLIMATE_XAMPP_ROOT:-/Applications/XAMPP/xamppfiles}"
STATE_DIR="$PROJECT_ROOT/storage/logs/local-services"
LOG_DIR="$PROJECT_ROOT/storage/logs"
PHP_BIN="${ICLIMATE_PHP:-php}"
ML_PYTHON="$PROJECT_ROOT/.venv-ml/bin/python"
WEB_HOST="${ICLIMATE_WEB_HOST:-127.0.0.1}"
WEB_PORT="${ICLIMATE_WEB_PORT:-8000}"
ML_PORT="${ICLIMATE_ML_PORT:-8010}"
REVERB_PORT="${ICLIMATE_REVERB_PORT:-8080}"
EXPECTED_TABLES=69
EXPECTED_FKS=87
SERVICES=(ml web reverb queue scheduler)
SUPERVISOR="$PROJECT_ROOT/scripts/local-service-supervisor.sh"
AUTOSTART_LABEL="local.iclimate.services"
START_LOCK_DIR="$STATE_DIR/start.lock"
START_LOCK_HELD=0

red() { printf '\033[31m%s\033[0m\n' "$1"; }
green() { printf '\033[32m%s\033[0m\n' "$1"; }
fail() { red "FAIL: $1" >&2; exit 1; }
cd "$PROJECT_ROOT"

# `launchd` runs this command every five minutes. mkdir is atomic on the local filesystem, so overlapping
# start invocations cannot both pass the PID-file check and create a supervisor for the same service.
release_start_lock() {
  [ "$START_LOCK_HELD" = 1 ] || return 0
  rm -f "$START_LOCK_DIR/pid"
  rmdir "$START_LOCK_DIR" 2>/dev/null || true
  START_LOCK_HELD=0
}

acquire_start_lock() {
  mkdir -p "$STATE_DIR"
  if mkdir "$START_LOCK_DIR" 2>/dev/null; then
    printf '%s\n' "$$" >"$START_LOCK_DIR/pid"
    START_LOCK_HELD=1
    trap release_start_lock EXIT
    return 0
  fi

  local owner=""
  owner="$(sed -n '1p' "$START_LOCK_DIR/pid" 2>/dev/null || true)"
  if [ -n "$owner" ] && kill -0 "$owner" 2>/dev/null; then
    echo "start already in progress (pid $owner); leaving existing services untouched"
    return 1
  fi

  # A dead owner left the lock behind after an interrupted shell. Remove only this exact lock directory, then retry
  # the atomic mkdir. A missing owner is treated as busy because its ownership cannot be verified safely.
  [ -n "$owner" ] || { echo "start lock exists without a verifiable owner; refusing a concurrent start" >&2; return 1; }
  rm -f "$START_LOCK_DIR/pid"
  rmdir "$START_LOCK_DIR" 2>/dev/null || { echo "start lock changed while recovering; refusing a concurrent start" >&2; return 1; }
  acquire_start_lock
}

# ----------------------------------------------------------------------------- prerequisites and database

# Reads one non-secret value from the application's resolved configuration.
config_value() { "$PHP_BIN" -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo (string) config($argv[1]);' "$1" 2>/dev/null; }

check_prerequisites() {
  command -v "$PHP_BIN" >/dev/null || fail "PHP is not installed (expected php 8.2+ on PATH)."
  "$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") && extension_loaded("pdo_mysql") ? 0 : 1);' || fail "PHP 8.2+ with pdo_mysql is required."
  [ -f .env ] || fail ".env is missing. Copy .env.example and fill in the local values (never production ones)."
  [ -f vendor/autoload.php ] || fail "Composer packages are missing. Run: composer install"
  [ -f public/build/manifest.json ] || echo "WARN: no frontend build (public/build). Run: npm run build"
  [ -x "$ML_PYTHON" ] || fail ".venv-ml is missing. Create it: python3 -m venv .venv-ml && .venv-ml/bin/pip install -r requirements.txt"
  "$ML_PYTHON" -c 'import uvicorn, fastapi' 2>/dev/null || fail ".venv-ml lacks uvicorn/fastapi. Run: .venv-ml/bin/pip install -r requirements.txt"
  command -v curl >/dev/null && command -v lsof >/dev/null && command -v perl >/dev/null || fail "curl, lsof and perl are required."
  [ -x "$SUPERVISOR" ] || fail "The local service supervisor is missing or not executable: $SUPERVISOR"
}

database_reachable() {
  "$PHP_BIN" -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); try { DB::selectOne("select 1"); exit(0); } catch (Throwable) { exit(1); }' >/dev/null 2>&1
}

ensure_database() {
  if ! database_reachable; then
    if [ "${START_MARIADB:-0}" = 1 ]; then
      echo "Starting XAMPP MariaDB (sudo)..."
      sudo "$XAMPP_ROOT/xampp" startmysql || fail "XAMPP could not start MariaDB."
      for _ in $(seq 1 30); do database_reachable && break; sleep 1; done
    fi
    database_reachable || fail "Local MariaDB is not reachable on 127.0.0.1:3306. Start it with the XAMPP manager, or rerun with --start-mariadb."
  fi

  # The environment guard refuses remote hosts, staging-in-production and Supabase; it prints no secrets.
  "$PHP_BIN" artisan iclimate:environment-check >/dev/null 2>&1 || { "$PHP_BIN" artisan iclimate:environment-check; fail "Unsafe database configuration (see above)."; }

  local user
  user="$(config_value "database.connections.$(config_value database.default).username")"
  if [ "$user" = root ]; then
    fail "The application is configured to connect as root. Set DB_USERNAME to the restricted local account (iclimate_app) in .env."
  fi

  local summary
  summary="$("$PHP_BIN" -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $t = DB::selectOne("select count(*) c from information_schema.tables where table_schema = database() and table_type = ?", ["BASE TABLE"])->c;
    $f = DB::selectOne("select count(*) c from information_schema.referential_constraints where constraint_schema = database()")->c;
    $z = DB::selectOne("select @@session.time_zone z")->z;
    $p = collect(app("migrator")->getMigrationFiles(database_path("migrations")))->keys()->diff(DB::table("migrations")->pluck("migration"))->count();
    echo implode(" ", [DB::connection()->getDatabaseName(), $t, $f, DB::table("users")->count(), $z, $p]);' 2>/dev/null)" || fail "The database checks could not run."
  read -r db tables fks users zone pending <<<"$summary"
  [ "$tables" = "$EXPECTED_TABLES" ] || fail "Unexpected table count in $db: $tables (expected $EXPECTED_TABLES)."
  [ "$fks" = "$EXPECTED_FKS" ] || fail "Unexpected foreign-key count in $db: $fks (expected $EXPECTED_FKS)."
  green "Local MariaDB PASS: database=$db account=restricted tables=$tables foreign_keys=$fks users=$users session_zone=$zone pending_migrations=$pending"
  [ "$pending" = 0 ] || echo "NOTE: $pending migration(s) pending; apply them only after a verified backup (see docs/SUPABASE_DECOMMISSION_RUNBOOK_2026-11-10.md)."
}

# ----------------------------------------------------------------------------- service definitions

reverb_enabled() { [ "$(config_value broadcasting.default)" = reverb ]; }

service_port() { case "$1" in web) echo "$WEB_PORT" ;; ml) echo "$ML_PORT" ;; reverb) echo "$REVERB_PORT" ;; *) echo "" ;; esac; }

# Run one service in the foreground without going through a shell-string
# expansion. This keeps the documented foreground modes usable and avoids the
# undefined service_command path that previously failed under set -u.
run_foreground_service() {
  case "$1" in
    web)
      cd "$PROJECT_ROOT"
      PHP_CLI_SERVER_WORKERS="${ICLIMATE_WEB_WORKERS:-4}" exec "$PHP_BIN" "$PROJECT_ROOT/artisan" serve --host="$WEB_HOST" --port="$WEB_PORT" --no-reload
      ;;
    ml)
      cd "$PROJECT_ROOT/python_scripts"
      exec "$ML_PYTHON" -m uvicorn main:app --app-dir "$PROJECT_ROOT/python_scripts" --host 127.0.0.1 --port "$ML_PORT"
      ;;
    queue)
      cd "$PROJECT_ROOT"
      exec "$PHP_BIN" "$PROJECT_ROOT/artisan" queue:work database --queue=analytics,default,broadcasts --sleep=3 --tries=2 --timeout=300 --max-time=3600 --memory=256
      ;;
    scheduler)
      cd "$PROJECT_ROOT"
      exec "$PHP_BIN" "$PROJECT_ROOT/artisan" schedule:work
      ;;
    reverb)
      cd "$PROJECT_ROOT"
      exec "$PHP_BIN" "$PROJECT_ROOT/artisan" reverb:start --host=127.0.0.1 --port="$REVERB_PORT"
      ;;
    *)
      fail "Unknown foreground service: $1"
      ;;
  esac
}

pid_file() { echo "$STATE_DIR/$1.pid"; }

owned_supervisor_pids() {
  local name="$1" needle
  needle="$SUPERVISOR $name $PROJECT_ROOT"
  ps -axo pid=,command= | awk -v needle="$needle" 'index($0, needle) > 0 && ($2 == "bash" || $2 ~ /\/bash$/) { gsub(/^ +/, ""); print $1 }'
}

ps_command() { ps -o command= -p "$1" 2>/dev/null | sed 's/^ *//'; }
ps_pgid() { ps -o pgid= -p "$1" 2>/dev/null | tr -d ' '; }
process_cwd() { lsof -a -p "$1" -d cwd -Fn 2>/dev/null | sed -n 's/^n//p' | head -1; }

supervisor_matches() {
  local pid="$1" name="$2" command
  command="$(ps_command "$pid")"
  [[ "$command" == *"$SUPERVISOR $name $PROJECT_ROOT"* ]]
}

legacy_supervisor_matches() {
  local pid="$1" command cwd
  command="$(ps_command "$pid")"
  cwd="$(process_cwd "$pid")"
  [[ "$cwd" = "$PROJECT_ROOT" && "$command" == *"ICLIMATE_SERVICE_CMD"* && "$command" == *"ICLIMATE_STOP_FLAG"* ]]
}

service_process_matches() {
  local pid="$1" name="$2" command cwd
  command="$(ps_command "$pid")"
  cwd="$(process_cwd "$pid")"
  case "$name" in
    web) [[ ("$command" == *"$PROJECT_ROOT/artisan serve"* && "$command" == *"--port=$WEB_PORT"*) || ("$cwd" = "$PROJECT_ROOT/public" && "$command" == *"php -S 127.0.0.1:$WEB_PORT"* && "$command" == *"$PROJECT_ROOT"*) ]] ;;
    ml) [[ "$command" == *"$PROJECT_ROOT/python_scripts"* && "$command" == *"uvicorn main:app"* && "$command" == *"--port $ML_PORT"* ]] ;;
    queue) [[ "$command" == *"$PROJECT_ROOT/artisan queue:work database"* ]] ;;
    scheduler) [[ "$command" == *"$PROJECT_ROOT/artisan schedule:work"* ]] ;;
    reverb) [[ "$command" == *"$PROJECT_ROOT/artisan reverb:start"* && "$command" == *"--port=$REVERB_PORT"* ]] ;;
    *) return 1 ;;
  esac
}

legacy_service_process_matches() {
  local pid="$1" name="$2" command cwd
  command="$(ps_command "$pid")"
  cwd="$(process_cwd "$pid")"
  case "$name" in
    web) [[ ("$cwd" = "$PROJECT_ROOT" && "$command" == *"artisan serve"* && "$command" == *"--port=$WEB_PORT"*) || ("$cwd" = "$PROJECT_ROOT/public" && "$command" == *"php -S 127.0.0.1:$WEB_PORT"* && "$command" == *"$PROJECT_ROOT/public"*) ]] ;;
    ml) [[ ("$cwd" = "$PROJECT_ROOT" || "$cwd" = "$PROJECT_ROOT/python_scripts") && "$command" == *"uvicorn main:app"* && "$command" == *"--port $ML_PORT"* ]] ;;
    queue) [[ "$cwd" = "$PROJECT_ROOT" && "$command" == *"artisan queue:work database"* ]] ;;
    scheduler) [[ "$cwd" = "$PROJECT_ROOT" && "$command" == *"artisan schedule:work"* ]] ;;
    reverb) [[ "$cwd" = "$PROJECT_ROOT" && "$command" == *"artisan reverb:start"* && "$command" == *"--port=$REVERB_PORT"* ]] ;;
    *) return 1 ;;
  esac
}

group_members() { ps -axo pid=,pgid= | awk -v group="$1" '$2 == group {print $1}'; }

owned_group() {
  local name="$1" group="$2" member
  [ -n "$group" ] || return 1
  while read -r member; do
    [ -n "$member" ] || continue
    supervisor_matches "$member" "$name" || legacy_supervisor_matches "$member" || service_process_matches "$member" "$name" || legacy_service_process_matches "$member" "$name" || return 1
  done < <(group_members "$group")
  return 0
}

find_supervisor_in_group() {
  local name="$1" group="$2" member
  while read -r member; do
    [ -n "$member" ] && { supervisor_matches "$member" "$name" || legacy_supervisor_matches "$member"; } && { echo "$member"; return 0; }
  done < <(group_members "$group")
  return 1
}

discover_orphan() {
  local name="$1" candidate group supervisor port listener
  port="$(service_port "$name")"
  if [ -n "$port" ]; then
    while read -r listener; do
      [ -n "$listener" ] || continue
      service_process_matches "$listener" "$name" || legacy_service_process_matches "$listener" "$name" || continue
      group="$(ps_pgid "$listener")"
      owned_group "$name" "$group" || continue
      find_supervisor_in_group "$name" "$group" && return 0
    done < <(lsof -tiTCP:"$port" -sTCP:LISTEN 2>/dev/null)
  else
    while read -r candidate; do
      [ -n "$candidate" ] || continue
      service_process_matches "$candidate" "$name" || legacy_service_process_matches "$candidate" "$name" || continue
      group="$(ps_pgid "$candidate")"
      owned_group "$name" "$group" || continue
      find_supervisor_in_group "$name" "$group" && return 0
    done < <(ps -axo pid=)
  fi
  return 1
}

# PID of the service supervisor, recovered from a stale/missing PID file only when its complete group is owned.
running_pid() {
  local file pid group name="$1"
  file="$(pid_file "$1")"
  if [ -f "$file" ]; then
    pid="$(sed -n '1p' "$file" 2>/dev/null | tr -cd '0-9')"
    group="$(sed -n '2p' "$file" 2>/dev/null | tr -cd '0-9')"
    if [ -n "$pid" ] && [ -n "$group" ] && kill -0 "$pid" 2>/dev/null && { supervisor_matches "$pid" "$name" || legacy_supervisor_matches "$pid"; } && [ "$(ps_pgid "$pid")" = "$group" ] && owned_group "$name" "$group"; then
      echo "$pid"
      return 0
    fi
    rm -f "$file"
  fi
  if pid="$(discover_orphan "$name")"; then
    group="$(ps_pgid "$pid")"
    printf '%s\n%s\n%s\n' "$pid" "$group" "$name" >"$file"
    echo "$pid"
    return 0
  fi
  return 1
}

# True when the program listening on the port belongs to this service's process group (not an orphan or stranger).
owns_port() {
  local group listener
  group="$(running_pid "$1")" || return 1
  for listener in $(lsof -tiTCP:"$2" -sTCP:LISTEN 2>/dev/null); do
    [ "$(ps_pgid "$listener")" = "$(ps_pgid "$group")" ] && { service_process_matches "$listener" "$1" || legacy_service_process_matches "$listener" "$1"; } && return 0
  done
  return 1
}

healthy() {
  case "$1" in
    web) owns_port web "$WEB_PORT" && curl -fsS -m 3 -o /dev/null "http://$WEB_HOST:$WEB_PORT/up" 2>/dev/null ;;
    ml) owns_port ml "$ML_PORT" && curl -fsS -m 3 "http://127.0.0.1:$ML_PORT/health" 2>/dev/null | grep -q '"ok"' ;;
    reverb) owns_port reverb "$REVERB_PORT" ;;
    queue|scheduler)
      local supervisor="$(running_pid "$1")" group="$(ps_pgid "$(running_pid "$1")")" member
      while read -r member; do
        { service_process_matches "$member" "$1" || legacy_service_process_matches "$member" "$1"; } && return 0
      done < <(group_members "$group")
      return 1
      ;;
  esac
}

start_service() {
  local name="$1" pid port log group
  if [ "$name" = reverb ] && ! reverb_enabled; then
    echo "reverb     skipped (BROADCAST_CONNECTION is not reverb; pages use polling)"
    return 0
  fi
  if pid="$(running_pid "$name")"; then
    echo "$(printf '%-10s' "$name") already running (pid $pid)"
    return 0
  fi
  if [ "$name" = queue ]; then
    healthy web || { red "queue      NOT started: Laravel web is not healthy yet"; return 1; }
    healthy ml || { red "queue      NOT started: ML service is not healthy yet"; return 1; }
    if reverb_enabled; then
      healthy reverb || { red "queue      NOT started: Reverb is not ready yet"; return 1; }
    fi
  fi
  port="$(service_port "$name")"
  if [ -n "$port" ] && lsof -nP -iTCP:"$port" -sTCP:LISTEN >/dev/null 2>&1; then
    red "$(printf '%-10s' "$name") NOT started: port $port is already used by another program (pid $(lsof -tiTCP:"$port" -sTCP:LISTEN | head -1)). Stop it first."
    return 1
  fi

  log="$LOG_DIR/local-$name.log"
  : >>"$log"
  rm -f "$STATE_DIR/$name.stop"
  nohup perl -e 'setpgrp(0, 0); exec @ARGV' "$SUPERVISOR" "$name" "$PROJECT_ROOT" "$STATE_DIR/$name.stop" "$PHP_BIN" "$ML_PYTHON" "$WEB_HOST" "$WEB_PORT" "$ML_PORT" "$REVERB_PORT" >>"$log" 2>&1 &
  pid="$!"
  group="$(ps_pgid "$pid")"
  printf '%s\n%s\n%s\n' "$pid" "$group" "$name" >"$(pid_file "$name")"

  for _ in $(seq 1 "${ICLIMATE_START_TIMEOUT:-40}"); do
    healthy "$name" && { green "$(printf '%-10s' "$name") started (pid $(sed -n '1p' "$(pid_file "$name")"), log storage/logs/local-$name.log)"; return 0; }
    sleep 1
  done
  red "$(printf '%-10s' "$name") did not become healthy. Last log lines:"
  tail -n 15 "$log" | sed 's/^/    /'
  stop_service "$name" >/dev/null
  return 1
}

stop_service() {
  local name="$1" pid group
  if ! pid="$(running_pid "$name")"; then
    echo "$(printf '%-10s' "$name") not running"
    return 0
  fi
  group="$(ps_pgid "$pid")"
  if ! owned_group "$name" "$group"; then
    red "$(printf '%-10s' "$name") not stopped: process identity could not be verified; leaving it untouched"
    return 1
  fi
  touch "$STATE_DIR/$name.stop"
  kill -TERM -- "-$group" 2>/dev/null || kill -TERM "$pid" 2>/dev/null
  # Wait for the whole process group (artisan serve's PHP workers, uvicorn), not just the restart loop.
  # The queue worker finishes its current job first (up to the 300 s job timeout).
  for _ in $(seq 1 "$( [ "$name" = queue ] && echo 320 || echo 20 )"); do
    group_members "$group" | grep -q . || break
    sleep 1
  done
  if group_members "$group" | grep -q .; then
    if owned_group "$name" "$group"; then
      kill -KILL -- "-$group" 2>/dev/null
    else
      red "$(printf '%-10s' "$name") did not stop cleanly; refusing SIGKILL because group ownership changed"
      return 1
    fi
    sleep 1
  fi
  if ! group_members "$group" | grep -q .; then
    rm -f "$(pid_file "$name")" "$STATE_DIR/$name.stop"
  else
    red "$(printf '%-10s' "$name") remains active; PID file retained for safe recovery"
    return 1
  fi
  echo "$(printf '%-10s' "$name") stopped"
  # A previous launcher version could leave a second supervisor group after its PID file disappeared. Recover and
  # stop any additional group one at a time; each group is revalidated before it is signalled.
  if discover_orphan "$name" >/dev/null 2>&1; then
    stop_service "$name"
  fi
}

status_report() {
  local name pid state failures=0 scheduler_pids scheduler_count
  scheduler_pids="$(owned_supervisor_pids scheduler)"
  scheduler_count="$(printf '%s\n' "$scheduler_pids" | awk 'NF { count++ } END { print count + 0 }')"
  for name in "${SERVICES[@]}"; do
    if [ "$name" = reverb ] && ! reverb_enabled; then
      printf '%-10s %s\n' "$name" "off (BROADCAST_CONNECTION is not reverb)"
      continue
    fi
    if [ "$name" = scheduler ] && [ "$scheduler_count" -gt 1 ]; then
      state="$(red "duplicate supervisors (pids ${scheduler_pids//$'\n'/ })")"
      failures=$((failures + 1))
    elif pid="$(running_pid "$name")"; then
      if healthy "$name"; then state="$(green "healthy (pid $pid)")"; else state="$(red "running but not healthy (pid $pid)")"; failures=$((failures + 1)); fi
    else
      state="$(red 'stopped')"; failures=$((failures + 1))
    fi
    printf '%-10s %s\n' "$name" "$state"
  done
  "$PHP_BIN" -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $last = DB::table("scheduled_task_runs")->max("created_at");
    $oldest = DB::table("jobs")->min("available_at");
    printf("database   %s | jobs waiting %d (oldest %s) | failed jobs %d | last scheduled task %s\n",
      DB::connection()->getDatabaseName(), DB::table("jobs")->count(), $oldest ? round((time() - $oldest) / 60)." min" : "-",
      DB::table("failed_jobs")->count(), $last ?: "never");' 2>/dev/null || { red "database   unreachable"; failures=$((failures + 1)); }
  return "$failures"
}

# ----------------------------------------------------------------------------- commands

mkdir -p "$STATE_DIR"
chmod 700 "$STATE_DIR"
mode="${1:-status}"
shift || true
for arg in "$@"; do [ "$arg" = --start-mariadb ] && START_MARIADB=1; done

case "$mode" in
  check)
    check_prerequisites
    ensure_database
    ;;
  start)
    # The autostart agent never overrides a deliberate stop; a manual start lifts the hold.
    if [ "${ICLIMATE_AUTOSTART:-0}" = 1 ]; then
      [ -f "$STATE_DIR/autostart.hold" ] && exit 0
    else
      rm -f "$STATE_DIR/autostart.hold"
    fi
    acquire_start_lock || exit 0
    check_prerequisites
    ensure_database
    failed=0
    for name in "${SERVICES[@]}"; do start_service "$name" || failed=1; done
    echo
    status_report || true
    [ "$failed" = 0 ] || fail "One or more services did not start (see above)."
    echo "Open http://$WEB_HOST:$WEB_PORT  |  stop with: bash scripts/start-iclimate-local.sh stop"
    ;;
  stop)
    mkdir -p "$STATE_DIR" && touch "$STATE_DIR/autostart.hold"   # autostart waits for the next manual start
    for ((i = ${#SERVICES[@]} - 1; i >= 0; i--)); do stop_service "${SERVICES[$i]}"; done
    ;;
  restart)
    "$0" stop
    exec "$0" start "$@"
    ;;
  status)
    status_report
    ;;
  install-autostart)
    # Why (2026-11-11): after a reboot nothing started again, so every page stayed on its last data. The agent runs
    # `start` at login and every 5 minutes; start skips services that are running, so this only restarts what stopped.
    # It processes real queue jobs on the configured database: install it only when that is intended.
    agent="$HOME/Library/LaunchAgents/$AUTOSTART_LABEL.plist"
    mkdir -p "$HOME/Library/LaunchAgents"
    cat >"$agent" <<PLIST
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict>
  <key>Label</key><string>$AUTOSTART_LABEL</string>
  <key>ProgramArguments</key><array><string>/bin/bash</string><string>$PROJECT_ROOT/scripts/start-iclimate-local.sh</string><string>start</string></array>
  <key>WorkingDirectory</key><string>$PROJECT_ROOT</string>
  <key>EnvironmentVariables</key><dict><key>ICLIMATE_AUTOSTART</key><string>1</string><key>PATH</key><string>$(dirname "$(command -v "$PHP_BIN")"):/usr/local/bin:/opt/homebrew/bin:/usr/bin:/bin:/usr/sbin:/sbin</string></dict>
  <key>RunAtLoad</key><true/>
  <key>StartInterval</key><integer>300</integer>
  <key>StandardOutPath</key><string>$LOG_DIR/local-autostart.log</string>
  <key>StandardErrorPath</key><string>$LOG_DIR/local-autostart.log</string>
</dict></plist>
PLIST
    launchctl bootout "gui/$(id -u)/$AUTOSTART_LABEL" >/dev/null 2>&1 || true
    launchctl bootstrap "gui/$(id -u)" "$agent" && green "autostart installed: $agent (log storage/logs/local-autostart.log)"
    ;;
  remove-autostart)
    launchctl bootout "gui/$(id -u)/$AUTOSTART_LABEL" >/dev/null 2>&1 || true
    rm -f "$HOME/Library/LaunchAgents/$AUTOSTART_LABEL.plist"
    green "autostart removed (running services were not stopped)"
    ;;
  web|ml|queue|scheduler|reverb)
    check_prerequisites
    ensure_database
    run_foreground_service "$mode"
    ;;
  *)
    fail 'Usage: scripts/start-iclimate-local.sh {start|stop|restart|status|check|install-autostart|remove-autostart|web|ml|queue|scheduler|reverb} [--start-mariadb]'
    ;;
esac
