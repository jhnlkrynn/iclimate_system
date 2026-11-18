#!/usr/bin/env bash
# Read-only checks for the ML service after an approved ML-only restart (audit 2026-11-18). Never restarts anything.
#   scripts/deploy/ml-activation-check.sh <expected-model-sha256>
# Exit 0 only when every check passes.
set -uo pipefail
cd "$(dirname "$0")/../.."
URL="${ML_URL:-http://127.0.0.1:8010}"
EXPECTED="${1:?expected model sha256 required (record it before the restart from /model-info)}"
fail=0
ok() { echo "OK    $1"; }
bad() { echo "FAIL  $1"; fail=1; }
code() { curl -s -m 10 -o /tmp/ml-check.$$ -w '%{http_code}' "$@"; }

[ "$(code "$URL/health")" = 200 ] && grep -q '"status":"ok"' /tmp/ml-check.$$ && ok "/health ok, models loaded" || bad "/health"
[ "$(code "$URL/model-info")" = 200 ] && grep -q "\"sha256\":\"$EXPECTED\"" /tmp/ml-check.$$ && ok "/model-info checksum unchanged" || bad "/model-info checksum differs from $EXPECTED"

base='"rainfall":90.14,"temp_avg":28.52,"temp_range":1.15,"area":1.4,"previous_rainfall":87.6,"previous_temp":26.95,"previous_humidity":81,"previous_wind_speed":2,"rainfall_6m":1982.9,"temp_3m":27.55,"temp_6m":27.72,"seasonal_rainfall":734.7,"seasonal_temp":27.55,"humidity":80.67,"wind_speed":2.18,"season":"Dry","farm_type":"Rainfed","month_num":11'
predict() { code -H 'Content-Type: application/json' -d "{\"prediction_type\":\"rice_yield\",\"features\":{$1}}" "$URL/predict"; }
[ "$(predict "$base")" = 200 ] && grep -q '"predicted_yield"' /tmp/ml-check.$$ && ok "/predict accepts today's production input" || bad "/predict production input"
for case in 'area":1.4/area":-5' 'temp_avg":28.52/temp_avg":60' 'previous_humidity":81/previous_humidity":140' 'area":1.4/area":0'; do
    from="${case%%/*}"; to="${case##*/}"
    [ "$(predict "${base/$from/$to}")" = 422 ] && ok "/predict rejects ${to%%\":*} = ${to##*\":}" || bad "/predict accepted ${to}"
done
for p in /etc/hosts ../../.env storage/app/private/ml/../../../../.env; do
    [ "$(code -G --data-urlencode "dataset_path=$p" "$URL/metrics")" = 422 ] && ok "/metrics refuses dataset_path=$p" || bad "/metrics dataset_path=$p"
done
[ "$(code "$URL/metrics")" = 200 ] && ok "/metrics default dataset answers" || bad "/metrics default"
php artisan iclimate:health 2>/dev/null | grep -E "ml-service|database" | sed 's/^/      laravel: /'
php artisan iclimate:health >/dev/null 2>&1 && ok "Laravel iclimate:health exit 0" || bad "Laravel iclimate:health reports a FAIL"
rm -f /tmp/ml-check.$$
exit $fail
