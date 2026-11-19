#!/usr/bin/env bash
# Switch the ML service's three changed files between the audited versions, with checksum checks.
#   scripts/deploy/ml-switch-code.sh new|previous [--restart]
# "previous" is the code the service ran before 2026-11-18 (behaviour verified identical to the running service);
# "new" adds the input-range and /metrics path checks. Saved copies: storage/app/backups/preflight-2026-11-18/ml-rollback.
# Without --restart only the files change (the running service keeps its loaded code until restarted).
# --restart ends ONLY the uvicorn child that listens on the ML port and whose parent is the "ml" supervisor, which
# relaunches it in about 5 seconds. Approval is required before using --restart on a live machine.
set -Eeuo pipefail
cd "$(dirname "$0")/../.."
version="${1:?new or previous}"; restart="${2:-}"
R=storage/app/backups/preflight-2026-11-18/ml-rollback
[ "$version" = new ] || [ "$version" = previous ] || { echo "usage: $0 new|previous [--restart]"; exit 2; }
(cd "$R" && shasum -a 256 -c checksums.sha256 --quiet) || { echo "FAIL saved copies do not match checksums.sha256"; exit 1; }

for f in rice_yield_service.py evaluation_service.py main.py; do
    cp "$R/$version/$f" "python_scripts/$f"
done
for f in rice_yield_service.py evaluation_service.py main.py; do
    cmp -s "$R/$version/$f" "python_scripts/$f" || { echo "FAIL python_scripts/$f did not switch"; exit 1; }
done
echo "OK    python_scripts now holds the '$version' ML code"

if [ "$restart" = --restart ]; then
    port="${ML_PORT:-8010}"
    pid=$(lsof -nP -iTCP:"$port" -sTCP:LISTEN -t 2>/dev/null | head -1)
    [ -n "$pid" ] || { echo "FAIL nothing listens on :$port"; exit 1; }
    parent=$(ps -o ppid= -p "$pid" | tr -d ' ')
    ps -o command= -p "$parent" | grep -q "local-service-supervisor.sh ml" || { echo "FAIL pid $pid is not owned by the ml supervisor; restart it by hand"; exit 1; }
    echo "      ending ML child $pid (supervisor $parent relaunches it)"
    kill -TERM "$pid"
    for _ in $(seq 1 30); do
        sleep 1
        new=$(lsof -nP -iTCP:"$port" -sTCP:LISTEN -t 2>/dev/null | head -1)
        if [ -n "$new" ] && [ "$new" != "$pid" ] && curl -s -m2 "http://127.0.0.1:$port/health" | grep -q '"ok"'; then
            echo "OK    ML service back as pid $new"; exit 0
        fi
    done
    echo "FAIL ML service did not come back within 30 s — run: scripts/deploy/ml-switch-code.sh previous --restart"; exit 1
fi
