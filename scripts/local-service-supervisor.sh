#!/usr/bin/env bash
# Run one iClimate local service in a dedicated process group.
# The launcher owns the group; this helper never signals sibling processes.
set -Euo pipefail

service="${1:?service name required}"
project_root="${2:?project root required}"
stop_flag="${3:?stop flag required}"
php_bin="${4:?php binary required}"
ml_python="${5:?ML python required}"
web_host="${6:?web host required}"
web_port="${7:?web port required}"
ml_port="${8:?ML port required}"
reverb_port="${9:?Reverb port required}"

restart_window=60
restart_limit=4
restart_backoff="${ICLIMATE_RESTART_BACKOFF:-300}"
restart_delay="${ICLIMATE_RESTART_DELAY:-5}"
restart_times=()
child_pid=""
exit_pid=""
stopping=0

on_term() {
  stopping=1
  if [ -n "$child_pid" ] && kill -0 "$child_pid" 2>/dev/null; then
    kill -TERM "$child_pid" 2>/dev/null || true
  fi
}
trap on_term TERM INT HUP

# Wait in the background so a TERM is handled at once (bash defers traps until a foreground sleep ends).
pause() {
  local until=$(( $(date +%s) + $1 ))
  while [ "$(date +%s)" -lt "$until" ]; do
    if [ "$stopping" = 1 ] || [ -f "$stop_flag" ]; then
      exit 0
    fi
    sleep 1 &
    wait $! 2>/dev/null || true
  done
}

prune_restart_times() {
  local now="$(date +%s)" kept=() started
  # Bash 3.2 (the macOS system shell) treats an empty array expansion under
  # `set -u` as an unbound variable. Guard the expansion explicitly.
  if [ "${#restart_times[@]}" -gt 0 ]; then
    for started in "${restart_times[@]}"; do
      [ $((now - started)) -lt "$restart_window" ] && kept+=("$started")
    done
  fi
  restart_times=()
  if [ "${#kept[@]}" -gt 0 ]; then
    restart_times=("${kept[@]}")
  fi
}

run_service() {
  case "$service" in
    web)
      cd "$project_root"
      PHP_CLI_SERVER_WORKERS="${ICLIMATE_WEB_WORKERS:-4}" exec "$php_bin" "$project_root/artisan" serve --host="$web_host" --port="$web_port" --no-reload
      ;;
    ml)
      cd "$project_root/python_scripts"
      exec "$ml_python" -m uvicorn main:app --app-dir "$project_root/python_scripts" --host 127.0.0.1 --port "$ml_port"
      ;;
    queue)
      cd "$project_root"
      exec "$php_bin" "$project_root/artisan" queue:work database --queue=analytics,default,broadcasts --sleep=3 --tries=2 --timeout=300 --max-time=3600 --memory=256
      ;;
    scheduler)
      cd "$project_root"
      exec "$php_bin" "$project_root/artisan" schedule:work
      ;;
    reverb)
      cd "$project_root"
      exec "$php_bin" "$project_root/artisan" reverb:start --host=127.0.0.1 --port="$reverb_port"
      ;;
    *)
      echo "Unknown iClimate service: $service" >&2
      return 64
      ;;
  esac
}

while :; do
  if [ "$stopping" = 1 ] || [ -f "$stop_flag" ]; then
    exit 0
  fi
  code=0
  run_service &
  child_pid=$!
  wait "$child_pid" || code=$?
  exit_pid="$child_pid"
  child_pid=""

  if [ "$stopping" = 1 ] || [ -f "$stop_flag" ]; then
    exit 0
  fi
  prune_restart_times
  signal_note=""
  [ "$code" -eq 137 ] && signal_note=" signal=SIGKILL"
  if [ "${#restart_times[@]}" -ge "$restart_limit" ]; then
    # A crash loop (4 exits in 60 s, e.g. the database is down) backs off instead of giving up for good: the service
    # comes back by itself once the cause is gone. Stop (TERM or the stop flag) still ends the wait at once.
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] service=$service pid=$exit_pid supervisor_pid=$$ exit_code=$code$signal_note; restart limit reached, trying again in ${restart_backoff} s" >&2
    restart_times=()
    pause "$restart_backoff"
    continue
  fi
  restart_times+=("$(date +%s)")
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] service=$service pid=$exit_pid supervisor_pid=$$ exit_code=$code$signal_note; restarting in ${restart_delay} s" >&2
  pause "$restart_delay"
done
