#!/usr/bin/env bash

set -euo pipefail

cd "$(dirname "$0")"

mkdir -p storage/logs

if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
    docker compose up -d --build ml-service
elif [[ -x ".venv/bin/python" ]]; then
    "${PWD}/.venv/bin/python" -m uvicorn python_scripts.main:app --host 127.0.0.1 --port 8010 >> storage/logs/farming-ai.log 2>&1 &
    ML_PID=$!
else
    echo "Warning: Python ML runtime is unavailable. The web, scheduler, and queue will still start."
    echo "Install dependencies with:"
    echo "  python3 -m venv .venv && .venv/bin/pip install -r python_scripts/requirements.txt"
    echo "or install Docker Desktop and run this script again."
fi

php artisan serve --host=127.0.0.1 --port=8000 >> storage/logs/serve.log 2>&1 &
WEB_PID=$!
php artisan schedule:work >> storage/logs/scheduler.log 2>&1 &
SCHEDULE_PID=$!
php artisan queue:work --queue=analytics,default --tries=2 --timeout=120 >> storage/logs/queue.log 2>&1 &
QUEUE_PID=$!

cleanup() {
    kill "$WEB_PID" "$SCHEDULE_PID" "$QUEUE_PID" 2>/dev/null || true
    if [[ -n "${ML_PID:-}" ]]; then
        kill "$ML_PID" 2>/dev/null || true
    fi
}

trap cleanup INT TERM EXIT

echo "iClimate web:       http://127.0.0.1:8000"
echo "iClimate ML health: http://127.0.0.1:8010/health"
echo "Scheduler, queue, and data refresh workers are running."
wait "$WEB_PID"
