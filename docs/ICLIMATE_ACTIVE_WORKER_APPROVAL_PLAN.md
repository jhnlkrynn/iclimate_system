# iClimate Active Worker Approval Plan

**Environment:** local runtime, MariaDB `iclimate_staging_20261103_fix3`  
**Queue connection:** Laravel database queue  
**Queues consumed:** `analytics,default,broadcasts`  
**Current read-only snapshot:** 0 pending, 0 reserved, 15 historical failed jobs

## Explicit authorization request

Please explicitly authorize or decline one bounded run of:

```bash
bash scripts/start-iclimate-local.sh start
```

This starts all local services and may consume any active queue job, including analytics, broadcast retries, notifications, weather refreshes, or other scheduled work. It may update business records, create derived outputs, dispatch follow-up jobs, call configured local/external services, and deliver broadcasts to currently connected local clients.

## Pre-start checks

```bash
php artisan about
php artisan tinker --execute='echo json_encode(["db"=>config("database.connections.mysql.database"),"queue"=>config("queue.default"),"pending"=>DB::table("jobs")->count(),"reserved"=>DB::table("jobs")->whereNotNull("reserved_at")->count(),"failed"=>DB::table("failed_jobs")->count()]),PHP_EOL;'
ps -axo pid,ppid,pgid,user,stat,command | rg 'iclimate|artisan|queue:work|schedule:work|reverb|uvicorn'
lsof -nP -iTCP:8000 -iTCP:8010 -iTCP:8080 -sTCP:LISTEN
```

Do not proceed if the database name, process ownership, or queue inventory differs from the approved scope.

## Monitoring and success criteria

Monitor the supervisor and worker PID/group, queue and failed-job counts, service logs, process RSS, and exit codes for a bounded period. Success requires one project-owned worker, no duplicate consumer, stable memory, no new supervisor error, no code 137, expected job accounting, and clean shutdown.

Stop immediately on duplicate workers, ownership mismatch, unexpected data writes, unexpected notification/broadcast, new code 137, restart-loop exhaustion, or failure to release project-owned ports.

## Stop and recovery

```bash
bash scripts/start-iclimate-local.sh stop
```

Verify project-owned processes and ports are gone. Do not delete queue rows, retry failed jobs, or restore payloads. Any recovery of Analytics Runs 2727/2728 requires separate approval and the normal application workflow.

## Owner approval record

- Active worker authorization: **PENDING**
- Authorized operator: ____________________
- Approved time window: ____________________
- Approved queue scope: ____________________
- Approval date/time: ____________________

## Fresh read-only readiness snapshot — 2026-11-10 14:51 UTC

- Laravel: local; database: MySQL `iclimate_staging_20261103_fix3`; queue: database; broadcasting: Reverb.
- Queue inventory: `0` pending, `0` reserved, `15` historical failed jobs.
- ML, web, Reverb, and scheduler supervisors were observed. Queue worker: **not running**.
- Expected listeners were observed on web `8000`, ML `8010`, and Reverb `8080`. No worker was started.
- Historical exit 137 remains **CAUSE UNVERIFIED**: SIGKILL is established, but OOM, manual termination, and supervisor termination are not distinguished.
- A separate current retry defect is present: `RetryBroadcastEvent::release()` is undefined when the Reverb circuit is open. This must be repaired and tested before production worker approval; no fix was applied in this preparation.

The approval request remains exactly one bounded live operation:

```bash
bash scripts/start-iclimate-local.sh start
```

This command is not authorized by this document. It can consume real database-backed jobs and create business/realtime side effects. Decline or approve it explicitly with an operator, time window, queue scope, monitoring owner, and rollback owner recorded above.

## Current verification supersession — 2026-11-10

The historical snapshot above is retained. Current read-only evidence confirms
the queue remains at `0` pending, `0` reserved, and `15` historical failed
jobs; no active queue worker was started. The `RetryBroadcastEvent` circuit-open
release defect has been repaired and verified in isolated tests, including a
real isolated `queue:work` release/recovery cycle. This does not authorize the
active MariaDB-backed worker. The approval request, monitoring criteria, and
rollback procedure remain unchanged.

Hosting is not an approval shortcut: the repository's Hostinger target is
documented as postponed/not renewed, and no production host was inspected.
The only current queue inventory is the local MariaDB snapshot above.

## Real-time completion: active start plan — 2026-11-11

Read-only state at 04:00–05:00 UTC: **all five local services stopped** (the Mac restarted at 12:01 Manila time and
nothing starts the stack after a reboot). Database `iclimate_staging_20261103_fix3`: 14 waiting jobs (11
`GenerateMonthlyWeatherForecast`, one per month 2026-12 … 2027-10, waiting since 2026-11-10 18:00 UTC; 3 broadcast
retries from 2026-11-11 02:47 UTC), 0 reserved, 15 historical failed jobs, 41 analytics runs failed since
2026-11-10 09:30 UTC (scheduler without a worker), 0 scheduled MAO alerts due.

Backup integrity: the latest logical backup `storage/app/backups/iclimate_2026-11-10_1915.backup` (SHA-256 prefix
`650912ac02d98ae2`, taken at the last scheduler run before the stop) was restored into the disposable clone
`iclimate_rt_audit_20261111` in 2 s: 69 tables and 87 foreign keys (same as active), identical ID checksums on users,
farms, farmer_profiles, planting_advisories, notifications, rice_productions, climate_records, analytics_runs and
yield_predictions. The only count differences are rows written after the backup (3 broadcast retries, 1 task run,
1 log line, sessions). It contains no `USE`/`CREATE DATABASE`, so it restores only into the database it is given.

Fixes in place before any active start (isolated verification in `docs/realtime-status.md`):
- `iclimate:automation-run` skips a round while no worker heartbeat is seen (no more timed-out failed runs) and
  never queues a type whose previous scheduled run is still waiting.
- A forecast month already waiting in the queue is not queued again.
- The supervisor backs off 5 min after a crash loop instead of staying stopped; TERM still stops it at once.
- `install-autostart` (launchd, login + every 5 min) is available but **not installed**; a manual `stop` holds it.

### The one command to approve

```bash
bash scripts/start-iclimate-local.sh start
```

Expected effects on the active database, in order:
1. Web, ML, Reverb: no business writes (sessions and logs only).
2. Queue worker (`analytics,default,broadcasts`): runs the 14 waiting jobs. The 11 forecasts upsert
   `monthly_weather_predictions` (one row per month; ~90 s total, measured on the restored clone). The 3 broadcast
   retries re-send old refresh signals to any open page (no new notification is created).
3. Scheduler (first minutes): weather/risk refresh (`external_weather_data`, `heatmap_areas`), weather cache, PAGASA
   fetch (may add PAGASA advisories), cache warming, stale-queue cleanup, the 30-minute analytics round (2 new
   `analytics_runs`, now only with the worker up), and the scheduled database backup at its time.
4. Not touched: Analytics Runs 2727 and 2728 stay failed (no job exists for them; nothing regenerates them).

Monitoring for the first 30 min: `bash scripts/start-iclimate-local.sh status`; System Health → "Queue worker:
Running"; `jobs` reaches 0; no new `failed_jobs`; `storage/logs/local-queue.log` without `exit_code=137`; worker RSS
under 256 MB (`ps -o rss= -p <pid>`); open Farmer / MAO / IT pages update without reload.
Stop / rollback: `bash scripts/start-iclimate-local.sh stop` (the worker finishes its current job first).
Optional after approval: `bash scripts/start-iclimate-local.sh install-autostart`.

Owner approval for this start: **PENDING**.
