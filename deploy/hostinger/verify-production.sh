#!/usr/bin/env bash
# Read-only production verification for the Hostinger VPS. Run as the
# application user from the application directory, e.g.
#   sudo -u www-data bash deploy/hostinger/verify-production.sh
# It changes nothing: no migrations, no queue work, no cache writes. It never
# prints passwords, keys or full connection strings; hosts are reported only
# as a category (loopback / private / public / supabase).
set -u
APP_DIR="${APP_DIR:-/var/www/iclimate_system}"
ENV_FILE="${ENV_FILE:-/etc/iclimate/iclimate.env}"
PHP="${PHP:-/usr/bin/php}"
cd "$APP_DIR" || { echo "FAIL app directory $APP_DIR not found"; exit 1; }

section() { printf '\n== %s\n' "$1"; }

section "Environment guard (local/production separation, Supabase, DB_TIMEZONE)"
"$PHP" artisan iclimate:environment-check 2>&1

section "Database (driver, host category, connectivity, Supabase references)"
"$PHP" artisan tinker --execute='
$name = config("database.default");
$c = config("database.connections.".$name);
$host = (string) ($c["host"] ?? "");
$ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : (gethostbyname($host) ?: $host);
$category = str_contains(strtolower($host), "supabase") || str_contains(strtolower($host), "pooler") ? "SUPABASE"
    : (in_array($ip, ["127.0.0.1", "::1"], true) || $host === "localhost" ? "loopback (this VPS)"
    : (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) ? "public/external" : "private network"));
echo "default=".$name." driver=".($c["driver"] ?? "?")." host_category=".$category." database=".($c["database"] ?? "?")."\n";
try { $v = DB::selectOne("select version() v")->v; echo "connectivity=PASS server_version=".$v."\n"; echo "users=".DB::table("users")->count()."\n"; }
catch (Throwable $e) { echo "connectivity=FAIL ".get_class($e)."\n"; }
$all = strtolower(json_encode(config()->all()));
echo "supabase_refs_in_runtime_config=".(substr_count($all, "supabase.co") + substr_count($all, "pooler.supabase"))."\n";
echo "queue=".config("queue.default")." session=".config("session.driver")." cache=".config("cache.default")." broadcast=".config("broadcasting.default")." filesystem=".config("filesystems.default")."\n";
echo "app_env=".config("app.env")." debug=".var_export(config("app.debug"), true)." url=".config("app.url")."\n";
' 2>&1
if [ -r "$ENV_FILE" ]; then
  echo "env_file_mode=$(stat -c %a "$ENV_FILE") supabase_lines_in_env=$(grep -ciE 'supabase|pooler' "$ENV_FILE")"
else
  echo "env_file $ENV_FILE not readable by $(id -un) (expected when run as www-data with mode 600 owned by root)"
fi

section "Migrations"
"$PHP" artisan migrate:status --no-ansi 2>&1 | awk '/Pending/{p++} /Ran/{r++} END{printf "ran=%d pending=%d\n", r, p}'
"$PHP" artisan migrate:status --no-ansi 2>&1 | grep Pending || true

section "Queue worker"
systemctl is-active iclimate-queue.service 2>/dev/null | sed 's/^/iclimate-queue: /'
pgrep -fa 'artisan queue:work' | sed -E 's/^([0-9]+).*/worker pid \1/' || echo "no queue:work process"
"$PHP" artisan tinker --execute='echo "jobs_waiting=".DB::table("jobs")->count()." oldest_job_age_min=".(($t = DB::table("jobs")->min("created_at")) ? round((time() - $t) / 60) : 0)." failed_jobs_24h=".DB::table("failed_jobs")->where("failed_at", ">=", now()->subDay())->count()."\n";' 2>&1

section "Scheduler"
systemctl is-active iclimate-scheduler.timer 2>/dev/null | sed 's/^/iclimate-scheduler.timer: /'
systemctl list-timers iclimate-scheduler.timer --no-pager 2>/dev/null | sed -n 2p
crontab -l 2>/dev/null | grep -c 'schedule:run' | sed 's/^/crontab schedule:run entries: /'
"$PHP" artisan tinker --execute='if (Schema::hasTable("scheduled_task_runs")) { $r = DB::table("scheduled_task_runs")->orderByDesc("id")->first(); echo "last_scheduled_task_run=".($r->created_at ?? "none")."\n"; echo "runs_last_hour=".DB::table("scheduled_task_runs")->where("created_at", ">=", now()->subHour())->count()."\n"; }' 2>&1
for cmd in backup-database verify-backup; do
  f="storage/logs/automation-$cmd.log"; [ -f "$f" ] && echo "$cmd last log line: $(tail -n 1 "$f" | cut -c1-160)"
done
ls -l --time-style=+%F_%T "$("$PHP" artisan tinker --execute='echo config("backup.directory");' 2>/dev/null)" 2>/dev/null | tail -n 3

section "Python ML service"
systemctl is-active iclimate-python.service 2>/dev/null | sed 's/^/iclimate-python: /'
curl -s -m 5 http://127.0.0.1:8010/health || echo "ML service not reachable on 127.0.0.1:8010"
echo
"$PHP" artisan tinker --execute='echo "python_binary=".(config("services.python.binary") ?: env("PYTHON_BINARY") ?: "(default)")." farming_ai_url=".config("services.farming_ai.url", env("FARMING_AI_API_URL"))."\n";' 2>&1

section "Reverb"
systemctl is-active iclimate-reverb.service 2>/dev/null | sed 's/^/iclimate-reverb: /'
(ss -ltn 2>/dev/null || netstat -ltn 2>/dev/null) | grep -E '127\.0\.0\.1:8080|:8080 ' | head -n 1 || echo "nothing listening on 8080"

section "Recent errors (counts by category, last 2 daily logs)"
logs=$(ls -1t storage/logs/laravel*.log 2>/dev/null | head -n 2)
if [ -n "$logs" ]; then
  for pat in 'SQLSTATE' 'BroadcastException' 'Python analytics service could not be reached' 'MaxAttemptsExceeded|has been attempted too many times' 'Database backup failed' 'supabase'; do
    printf '%-60s %s\n' "$pat" "$(cat $logs | grep -E '\.(ERROR|CRITICAL|EMERGENCY)' | grep -ciE "$pat")"
  done
else
  echo "no laravel log files found"
fi
