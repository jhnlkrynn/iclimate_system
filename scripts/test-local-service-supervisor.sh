#!/usr/bin/env bash
# Disposable regression check for supervisor shutdown precedence.
set -Euo pipefail

project_root="$(cd "$(dirname "$0")/.." && pwd)"
tmpdir="$(mktemp -d /tmp/iclimate-supervisor-term.XXXXXX)"
supervisor=""
cleanup() {
  if [ -n "$supervisor" ] && kill -0 "$supervisor" 2>/dev/null; then
    kill -KILL "$supervisor" 2>/dev/null || true
  fi
  rm -rf "$tmpdir"
}
trap cleanup EXIT

bash "$project_root/scripts/local-service-supervisor.sh" unknown "$project_root" "$tmpdir/stop" php python 127.0.0.1 8000 8010 8080 >"$tmpdir/log" 2>&1 &
supervisor="$!"
sleep 1
kill -TERM "$supervisor"

stopped=0
for _ in $(seq 1 70); do
  if ! kill -0 "$supervisor" 2>/dev/null; then stopped=1; break; fi
  sleep 0.1
done
if [ "$stopped" = 1 ]; then
  echo "supervisor TERM shutdown: PASS"
else
  echo "supervisor TERM shutdown: FAIL" >&2
  sed -n '1,20p' "$tmpdir/log" >&2
  exit 1
fi

# A crash loop backs off and tries again instead of staying stopped; TERM during the back-off still stops it at once.
ICLIMATE_RESTART_DELAY=0 ICLIMATE_RESTART_BACKOFF=2 bash "$project_root/scripts/local-service-supervisor.sh" unknown "$project_root" "$tmpdir/stop" php python 127.0.0.1 8000 8010 8080 >"$tmpdir/loop.log" 2>&1 &
supervisor="$!"
sleep 4
backoffs="$(grep -c 'restart limit reached, trying again in 2 s' "$tmpdir/loop.log" || true)"
kill -TERM "$supervisor"
stopped=0
for _ in $(seq 1 30); do
  if ! kill -0 "$supervisor" 2>/dev/null; then stopped=1; break; fi
  sleep 0.1
done
if [ "${backoffs:-0}" -ge 1 ] && [ "$stopped" = 1 ] && ! grep -q 'leaving it stopped' "$tmpdir/loop.log"; then
  echo "supervisor crash-loop back-off and recovery: PASS (back-offs: $backoffs)"
  exit 0
fi
echo "supervisor crash-loop back-off and recovery: FAIL (back-offs: ${backoffs:-0}, stopped: $stopped)" >&2
sed -n '1,30p' "$tmpdir/loop.log" >&2
exit 1
