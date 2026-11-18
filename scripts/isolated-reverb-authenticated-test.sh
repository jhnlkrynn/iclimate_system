#!/bin/sh
set -Eeuo pipefail
cd "$(dirname "$0")/.."

PORT="${REVERB_PROBE_WEB_PORT:-18123}"
WS_PORT="${REVERB_PROBE_WS_PORT:-18080}"
RUN_DIR="${TMPDIR:-/tmp}/iclimate-reverb-probe-${PORT}-$$"
DB_FILE="$RUN_DIR/database.sqlite"
SESSION_DIR="$RUN_DIR/sessions"
WEB_LOG="$RUN_DIR/web.log"
REVERB_LOG="$RUN_DIR/reverb.log"
WEB_PID=""
REVERB_PID=""

cleanup() {
    exit_code="${1:-0}"
    trap - EXIT INT TERM
    if [ -n "$WEB_PID" ] && kill -0 "$WEB_PID" 2>/dev/null; then kill -TERM "$WEB_PID" 2>/dev/null || true; wait "$WEB_PID" 2>/dev/null || true; fi
    if [ -n "$REVERB_PID" ] && kill -0 "$REVERB_PID" 2>/dev/null; then kill -TERM "$REVERB_PID" 2>/dev/null || true; wait "$REVERB_PID" 2>/dev/null || true; fi
    rm -rf "$RUN_DIR"
    exit "$exit_code"
}
trap 'cleanup "$?"' EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

mkdir -p "$SESSION_DIR"
touch "$DB_FILE"
export APP_ENV=local APP_DEBUG=true APP_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')" APP_URL="http://127.0.0.1:$PORT"
export DB_CONNECTION=sqlite DB_DATABASE="$DB_FILE" DB_URL= CACHE_STORE=array SESSION_DRIVER=file SESSION_FILES="$SESSION_DIR" SESSION_COOKIE="iclimate-reverb-$PORT-session"
export QUEUE_CONNECTION=sync MAIL_MAILER=log BROADCAST_CONNECTION=reverb ICLIMATE_EXCLUDE_SAMPLE_DATA=true
export REVERB_APP_ID=isolated-test-app REVERB_APP_KEY=isolated-test-key REVERB_APP_SECRET=isolated-test-secret REVERB_HOST=127.0.0.1 REVERB_PORT="$WS_PORT" REVERB_SERVER_HOST=127.0.0.1 REVERB_SERVER_PORT="$WS_PORT" REVERB_SCHEME=http REVERB_ALLOWED_ORIGINS=127.0.0.1,localhost
export VITE_REVERB_APP_KEY=isolated-test-key VITE_REVERB_HOST=127.0.0.1 VITE_REVERB_PORT="$WS_PORT" VITE_REVERB_SCHEME=http
export FARMING_AI_API_URL=http://127.0.0.1:19999 OPEN_METEO_TIMEOUT=1 WEATHERAPI_TIMEOUT=1 WEATHER_API_TIMEOUT=1 PAGASA_ADVISORIES_ENABLED=false GROQ_ENABLED=false

php artisan config:clear >/dev/null
php artisan migrate:fresh --force >/dev/null
php tests/Browser/seed-landing.php >/dev/null
(cd public && exec php -S "127.0.0.1:$PORT" ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php >"$WEB_LOG" 2>&1) & WEB_PID=$!
php artisan reverb:start --host=127.0.0.1 --port="$WS_PORT" >"$REVERB_LOG" 2>&1 & REVERB_PID=$!

for _ in $(seq 1 60); do
    if curl -fsS "http://127.0.0.1:$PORT/login" >/dev/null 2>&1 && kill -0 "$REVERB_PID" 2>/dev/null; then break; fi
    if ! kill -0 "$WEB_PID" 2>/dev/null || ! kill -0 "$REVERB_PID" 2>/dev/null; then cat "$WEB_LOG" "$REVERB_LOG" >&2; exit 1; fi
    sleep 1
done

PROBE_BASE_URL="http://127.0.0.1:$PORT" PROBE_WS_URL="ws://127.0.0.1:$WS_PORT/app/isolated-test-key?protocol=7&client=js&version=8.6.0&flash=false" node scripts/isolated-reverb-authenticated-test.mjs
