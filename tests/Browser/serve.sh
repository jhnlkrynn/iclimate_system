#!/bin/sh
# Starts the site for the Playwright browser tests on its own throwaway SQLite database, so they never touch the
# shared database in .env. Each invocation owns a unique port, database, session namespace, server PID, and log.
set -Eeuo pipefail
cd "$(dirname "$0")/../.."

PORT="${PLAYWRIGHT_PORT:?PLAYWRIGHT_PORT must be supplied by playwright.config.mjs}"
RUN_DIR="${TMPDIR:-/tmp}/iclimate-browser-${PORT}-$$"
DB_FILE="$RUN_DIR/database.sqlite"
LOG_FILE="$RUN_DIR/server.log"
mkdir -p "$RUN_DIR/sessions"
cleanup() {
    status=$?
    if [ -n "${SERVER_PID:-}" ] && kill -0 "$SERVER_PID" 2>/dev/null; then
        kill -TERM "$SERVER_PID" 2>/dev/null || true
        wait "$SERVER_PID" 2>/dev/null || true
    fi
    rm -rf "$RUN_DIR"
    exit "$status"
}
trap cleanup EXIT INT TERM
touch "$DB_FILE"

export APP_ENV=local
export APP_DEBUG=true
export APP_URL="http://127.0.0.1:$PORT"
export DB_CONNECTION=sqlite
export DB_DATABASE="$DB_FILE"
export DB_URL=
export CACHE_STORE=array
export SESSION_DRIVER=file
export SESSION_FILES="$RUN_DIR/sessions"
export SESSION_COOKIE="iclimate-browser-${PORT}-session"
export QUEUE_CONNECTION=sync
export MAIL_MAILER=log
export BROADCAST_CONNECTION=null
export ICLIMATE_EXCLUDE_SAMPLE_DATA=true
# Dashboards opened by the login tests call outside services; keep those calls short (or off) and run several
# workers, so a slow call cannot hold up the landing page requests. The ML service gets an unroutable port, as in phpunit.xml.
export PHP_CLI_SERVER_WORKERS=1
export FARMING_AI_API_URL=http://127.0.0.1:19999
export OPEN_METEO_TIMEOUT=4
export WEATHERAPI_TIMEOUT=4
export WEATHER_API_TIMEOUT=4
export PAGASA_ADVISORIES_ENABLED=false
export GROQ_ENABLED=false

php artisan config:clear >/dev/null
php artisan migrate:fresh --force >/dev/null
php tests/Browser/seed-landing.php

# Laravel's router script resolves index.php from the working directory, as `artisan serve` does.
cd public
php -S "127.0.0.1:$PORT" ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php >"$LOG_FILE" 2>&1 &
SERVER_PID=$!
for _ in $(seq 1 60); do
    if curl -fsS "$APP_URL/login" >/dev/null 2>&1; then
        wait "$SERVER_PID"
        exit $?
    fi
    if ! kill -0 "$SERVER_PID" 2>/dev/null; then
        cat "$LOG_FILE" >&2
        exit 1
    fi
    sleep 1
done
echo "Timed out waiting for browser server on $APP_URL" >&2
cat "$LOG_FILE" >&2
exit 1
