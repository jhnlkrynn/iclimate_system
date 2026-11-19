# iClimate Queue Recovery and Verification

**Date:** 2026-11-10
**Production readiness:** **NOT PRODUCTION READY**
**Supabase retirement:** **BLOCKED — INSUFFICIENT EVIDENCE**

## 1. Worker configuration assessment

| Check | Result | Finding |
|---|---|---|
| Queue driver | PASS | Local configuration uses Laravel's `database` queue connection and the `jobs` table. |
| Queue names | PASS | The intended worker consumes `analytics,default,broadcasts`; analytics jobs 126 and 127 were on `analytics`. |
| Worker timeout | PASS with warning | Worker timeout is 300 seconds, job-level analytics timeout is 90 seconds, and database `retry_after` is 330 seconds. The relationship avoids ordinary timeout redelivery, but the 256 MB process limit needs live observation. |
| Retry policy | PASS | `RunPythonAnalytics` declares three attempts with 5/15/30 second backoff; the launcher adds two worker attempts for jobs without an overriding policy. |
| Memory limit | UNVERIFIED | Laravel worker limit is 256 MB. Exit code 137 proves SIGKILL but the available evidence does not prove OOM, manual termination, or another signal source. |
| Restart behavior | PASS in isolation | The empty `restart_times[@]` expansion under Bash `set -u` was repaired and a synthetic restart test completed without the prior error. |
| Foreground launcher | REPAIRED | Foreground modes referenced an undefined `service_command`. The launcher now dispatches services directly with argument-safe `exec` calls. The real queue mode was not invoked. |
| Duplicate workers | WARNING | The background launcher validates process groups and PID ownership. A live worker was not started, so duplicate-consumer behavior was not observed. |
| OS identity | UNVERIFIED | No production worker was started for a user/permission observation. |

The previous exit-137 log entries came from a legacy `bash -c "$ICLIMATE_SERVICE_CMD"` path. No kernel OOM record or equivalent memory-pressure evidence was available. The cause remains **UNVERIFIED**.

## 2. Missing analytics job outcome

The retained queue backups establish the actual outcome without replaying the jobs:

| Job | Analytics run | Type | Evidence | Classification | Recovery recommendation |
|---:|---:|---|---|---|---|
| 126 | 2727 | `dataset_processing` | Preserved in `storage/app/backups/queue/removed-jobs-2026-11-10_113500.json`; `reserved_at` null; parent run failed before `started_at`, timed out waiting for the worker, and has no result. | **VERIFIED CANCELLED / PARENT VERIFIED FAILED** | **RECOVERY RECOMMENDED**, only after owner approval and a separate idempotency review. |
| 127 | 2728 | `advisory_ai` | Preserved in the same backup; `reserved_at` null; parent run failed before `started_at`, timed out waiting for the worker, and has no result. | **VERIFIED CANCELLED / PARENT VERIFIED FAILED** | **RECOVERY RECOMMENDED**, only after owner approval and review of advisory duplication/review workflow. |

No equivalent new output was found in the checked 11:30–11:35 window for planting advisories, external weather data, yield predictions, or other checked derived tables. The failed analytics-run rows remain traceable. Recovery is a recommendation, not an authorization.

Owner choices for each run:

- **Option A — Regenerate:** create a new normal-workflow analytics execution after the worker is verified and the input/output idempotency review is approved.
- **Option B — Leave failed:** retain the failed historical run and accept that no derived result was generated.
- **Option C — Investigate further:** defer regeneration pending business review.

No option was selected automatically. Backed-up payloads were not reinserted into `jobs`.

## 3. Queue cleanup safety

`CleanStaleQueue` performs the following safety checks:

- It only scans unreserved jobs for removal.
- It parses only known job classes.
- It removes analytics jobs only when their parent run is already failed or completed, or when a stale pending/processing run is atomically expired first.
- It preserves a JSON copy before deleting queue rows.
- It deduplicates only the known refresh and monthly-forecast job types.
- It leaves unknown or unparsable payloads alone.
- Existing isolated regression coverage verifies that timed-out request jobs are removed, one refresh job is retained, and monthly forecast jobs are deduplicated.

Assessment: **PASS with operational warning**. The cleanup behavior is intentional and traceable, but it can remove a timed-out analytics job while its desired business output is still recoverable in principle. The retained backup prevents silent loss, but operators must decide whether to regenerate the failed run. No additional code defect was confirmed in this review, so cleanup logic was not broadened or changed.

## 4. Isolated verification evidence

The following tests use the isolated Laravel testing configuration, not the active MySQL queue:

- `CriticalFixesTest`, `PythonAnalyticsQueueTest`, `ResilientBroadcastingTest`, and `SystemHealthTest`: **55 passed, 303 assertions**.
- Prior full Laravel baseline: **1,729 passed, 5 skipped**.
- Prior Python baseline: **15 passed, 2 warnings**.
- Frontend production build: **PASS** (`npm run build`).
- Isolated Playwright browser suite: **162 passed, 42 skipped** across desktop and mobile projects. The suite used a throwaway SQLite database, sync queue, null broadcasting, isolated accounts, and disabled external services.
- Synthetic supervisor restart test: passed; no `unbound variable` error.
- Shell validation: `bash -n scripts/local-service-supervisor.sh` and `bash -n scripts/start-iclimate-local.sh` passed.
- Corrected read-only dashboard audit: `php artisan iclimate:data-audit` completed with all checks passing; monitored barangays matched 9 and valid yield predictions matched 6,606. The command no longer clears application cache.
- Broader focused regression set: **58 passed, 310 assertions**. No live queue worker was started and no existing job was retried.

These results verify queue and analytics behavior in isolation. They do not prove that a live worker is currently running or that it can safely consume the active database queue.

## 5. Controlled production-worker verification runbook

This runbook is prepared but **not executed**. Starting the command below may consume existing database-backed jobs, including newly scheduled work; it requires explicit owner authorization.

1. Record a read-only preflight: service status, queue count, failed-job count, recent backup verification, current worker processes, and the exact queue rows/classes present.
2. Confirm the intended local database and restricted application account. Do not change `.env` or queue rows.
3. Confirm web, ML, and Reverb readiness before consuming analytics/broadcast jobs.
4. Obtain explicit authorization to start the local queue worker. The supervised command is `bash scripts/start-iclimate-local.sh start`; this starts all local services and may process existing jobs. A queue-only foreground check is `bash scripts/start-iclimate-local.sh queue`; it also consumes real database jobs and is not a harmless test.
5. Observe the worker process, supervisor PID/group, queue log, memory, failed-job count, and job completion for a bounded period. Do not retry failed jobs automatically.
6. Stop only the verified project-owned service group using the launcher after observation. Confirm no unrelated processes were signaled and ports are released.
7. Compare analytics-run statuses and derived outputs after the run. Any regeneration of runs 2727/2728 requires a separate decision and must not be inferred from worker health.

Success criteria are: one verified worker group, no duplicate worker, no new supervisor error, no new code-137 exit, stable memory, expected job accounting, and clean project-owned shutdown. Until those criteria are observed, live worker verification is **UNVERIFIED**.

## 6. Remaining blockers

- Live queue-worker startup and persistence have not been authorized or verified.
- Exit code 137's underlying cause remains unverified.
- Runs 2727 and 2728 have no generated outputs; owner recovery decisions are pending.
- Browser login/role flows and live WebSocket delivery remain unverified.
- Supabase dashboard-only services and external consumers remain pending in the separate decommissioning audit.

## Final assessment

| Area | Status |
|---|---|
| Queue configuration | **PASS with warning** |
| Isolated worker/analytics tests | **PASS** |
| Live worker verification | **UNVERIFIED** |
| Queue cleanup safety | **PASS with warning** |
| Analytics 2727/2728 integrity | **VERIFIED FAILED/CANCELLED; recovery pending** |
| Production readiness | **NOT PRODUCTION READY** |

No database, backup, `.env`, Supabase resource, or historical queue payload was modified. No job was retried or restored.
