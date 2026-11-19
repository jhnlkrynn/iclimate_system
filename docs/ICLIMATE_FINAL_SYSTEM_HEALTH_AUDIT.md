# iClimate Final System Health Audit

**Project:** iClimate local system
**Audit date:** 2026-11-10
**Scope:** Read-only operational, database, service, test, and configuration verification
**Decision:** **NOT PRODUCTION READY**

## Executive summary

The Laravel web application, local MySQL connection, migrations, Python ML health endpoint, Reverb listener, scheduler, weather refreshes, and automated test suites are operational or verified in isolation. The system is not production-ready because the live queue worker is stopped, analytics runs 2727 and 2728 are verified failed/cancelled with recovery still undecided, historical queue/restart failures include exit code 137, and several live browser/WebSocket/deployment checks remain unverified.

The two failing dashboard checks are calculation mismatches in the audit command, not evidence of missing database rows:

- The raw heatmap data contains 19 computed barangay labels, while the dashboard intentionally reports 9 supported canonical barangays. `LianBarangays` and `DashboardData::currentRiskRatings()` both resolve to 9.
- The dashboard prediction KPI is 6,606 because it combines completed analytics runs with 6,606 scheduled estimates. The audit command subtracts scheduled estimates from zero completed runs and incorrectly reports `-6600`.

The audit formulas were corrected in the current authorized repair: the command now uses the dashboard's canonical risk-rating and prediction-KPI semantics and no longer clears the application cache. The correction changes audit calculation behavior only; it does not modify database records.

## Verification matrix

| Area | Result | Evidence and limitations |
|---|---|---|
| Laravel configuration | PASS | `php artisan about` reports local MySQL, database queue, database sessions, Reverb broadcasting, and local URL `127.0.0.1:8000`. |
| Laravel web | PASS | `/up` returned HTTP 200; the web supervisor and PHP listeners were present. |
| Database migrations | PASS | `php artisan migrate:status` showed all listed migrations as ran and no pending migrations. |
| Database structure | PASS | Existing migration evidence verifies 69 tables, 87 foreign keys, and the expected local schema. No records were changed by this audit. |
| Database audit | PASS | Duplicate-protection and freshness checks passed. The dashboard comparisons now use the same canonical formulas as the dashboard, and the read-only audit command completed with all checks passing. |
| Queue worker | **FAIL** | The queue supervisor reported stopped; no queue worker process was present. Two `analytics` jobs were waiting with zero attempts at the initial audit check. Historical logs contain repeated code 137 exits and a supervisor `restart_times[@]: unbound variable` failure. No jobs were retried. Their later disappearance was not attributable to a verified worker run and remains unverified. |
| Scheduler | PASS | Scheduler supervisor and `schedule:work` child were running; recent weather/PAGASA refreshes were successful. |
| Python ML | PASS | Port 8010 `/health` returned HTTP 200 and reported both `rice` and `weather` models loaded. Python tests passed: 15 passed, 2 warnings. |
| Laravel-to-ML integration | WARNING | Isolated Laravel tests passed, including ML-related contracts. A new live prediction was not generated because that could create persistent records. |
| Weather and advisories | PASS | Recent Open-Meteo, PAGASA, forecast, and risk-rating timestamps were fresh; duplicate-protection checks passed. Three advisories were published, 16 pending review, and 14 expired. |
| Reverb | PASS (isolated authenticated verification) | Reverb was listening on 8080 and its supervisor was healthy. Two fresh disposable browser-origin runs passed handshake, protected subscription, unauthorized `403`, actual notification delivery, and reconnect. Active queue/account verification remains separate. |
| Broadcasting | WARNING | Resilient broadcast tests passed. Logs show earlier temporary delivery failures that later completed, but the stopped queue currently prevents normal asynchronous processing. |
| Supabase runtime dependency | PASS | Runtime configuration and service checks point to local MySQL/Reverb. Historical migration/audit references remain intentionally retained. |
| Frontend/browser | PASS (isolated) | Repaired harness completed a fresh run with **162 passed, 42 expected skips, 0 failures**. The six previously failing farm-association/registration scenarios pass in both viewports. Live active-database browser verification remains outside scope. |
| Automated Laravel tests | PASS | Isolated test suite: **1,729 passed, 5 skipped, 13,559 assertions**. No failures. Skips were expected SQLite exclusions for MariaDB-specific migration behavior. |
| Automated Python tests | PASS | `.venv-ml/bin/python -m pytest -q python_scripts/tests`: **15 passed, 2 warnings**. |
| Live database test mode | UNVERIFIED | The full Laravel suite used the isolated SQLite testing configuration, not the active MySQL database. |

Live WebSocket handshake and event delivery against the active Reverb process remain **UNVERIFIED**. The disposable browser-origin handshake, protected subscription, event delivery, unauthorized rejection, and reconnect are verified in the update below.

After the supervisor and audit-command repairs, the isolated queue/analytics regression set passed: **48 tests, 240 assertions** across `PythonAnalyticsQueueTest`, `ResilientBroadcastingTest`, and `SystemHealthTest`; the broader targeted set passed **58 tests, 310 assertions**. The earlier targeted broadcasting set also passed: **21 tests, 104 assertions**.

After the foreground-launcher repair and cleanup review, `CriticalFixesTest`, `PythonAnalyticsQueueTest`, `ResilientBroadcastingTest`, and `SystemHealthTest` passed: **55 tests, 303 assertions**. Cleanup safety is covered in isolation: timed-out request jobs are removed only after their parent state is closed, refresh duplicates are reduced, and a backup copy is created.

The actual local Laravel queue worker was not started during this audit. Starting it would process real database-backed work and requires explicit authorization; therefore live worker persistence, real-job completion, and live queue restart recovery remain unverified.

## Service status at audit time

The local launcher reported:

- ML: healthy
- Laravel web: healthy
- Reverb: healthy
- Scheduler: healthy
- Queue: stopped
- Database: `iclimate_staging_20261103_fix3`

Observed listeners were limited to the expected local service ports: web 8000, ML 8010, and Reverb 8080. No unrelated process was signaled or terminated.

## Database integrity and dashboard discrepancies

The existing migration audit and isolated backup recovery evidence remain valid: 69 application tables, 107,388 source rows in the retained historical snapshot, primary-key coverage, foreign-key coverage, and critical source-to-local identity checks were previously verified. This health audit did not repeat or alter those records.

The corrected read-only data audit now reports:

1. **Monitored barangays: 9 versus 9 — PASS.** The raw computed heatmap rows contain 19 distinct labels, but the dashboard applies the supported canonical barangay list and latest-row rules; `currentRiskRatings()` resolves to 9. The audit now compares against that same canonical result.

2. **Valid yield predictions: 6,606 versus 6,606 — PASS.** There are zero completed analytics runs and 6,606 scheduled estimates. The dashboard intentionally displays their combined KPI; the audit now adds those same components instead of subtracting schedules.

The local database contains 15 historical failed jobs. The latest recorded failure was 2026-11-10 08:09:26 in the captured status. These jobs were not retried or deleted. Two analytics jobs were observed waiting with zero attempts during the initial check; a later read showed no pending rows, but no running queue worker or matching successful execution was observed. Their disposition is therefore unverified and no replacement or retry was performed.

## Queue repair performed in this audit

The confirmed supervisor defect was the empty-array expansion of `restart_times[@]` under `set -u`. The affected variable was `restart_times`; the failure was reported at the supervisor's `prune_restart_times` function. The script now guards empty-array length and copies only non-empty arrays, and resets the child exit code for each restart-loop iteration.

The current worker command was inspected and no invalid option was confirmed: it uses the local database driver, the intended `analytics,default,broadcasts` queues, a 300-second job timeout, a one-hour max runtime, two attempts, and a 256 MB Laravel worker limit. The historical code-137 evidence proves SIGKILL but does not establish whether the kernel OOM killer, a manual signal, or another supervisor terminated the process. No memory-pressure record was available in the retained logs, so OOM remains unverified. The older log command used a legacy `bash -c "$ICLIMATE_SERVICE_CMD"` wrapper, distinct from the current direct supervisor invocation.

The launcher also contained a separate confirmed defect in its documented foreground service modes: they called an undefined `service_command` function. The foreground dispatch now uses direct, argument-safe `exec` calls for web, ML, queue, scheduler, and Reverb. This path was syntax-checked but not invoked, because the queue mode would consume real database-backed jobs.

An isolated synthetic supervisor test used `/usr/bin/true` instead of PHP or Laravel. It exited and entered the restart path, then stopped cleanly when the temporary stop flag was created. It produced no `unbound variable` error. This verifies the shell-control fix only; it does not verify a live Laravel worker or process the existing analytics jobs.

## Forensic outcome of the two missing analytics jobs

The jobs did not disappear silently. The scheduled `iclimate:clean-stale-queue` command removes queue rows for analytics runs that are already closed and writes a JSON safety copy before deletion. Read-only inspection of those retained copies established the following:

| Queue job | Parent run | Type | Evidence | Classification | Recovery |
|---|---:|---|---|---|---|
| 126 | 2727 | `dataset_processing` | Retained in `removed-jobs-2026-11-10_113500.json`; queue `analytics`; `reserved_at` was null. Parent run status is `failed`, with no `started_at`, a timeout error, and no result. | **VERIFIED CANCELLED**; parent run **VERIFIED FAILED** | Expected output was not produced. Do not recreate or retry without explicit review and authorization. |
| 127 | 2728 | `advisory_ai` | Retained in the same safety copy; queue `analytics`; `reserved_at` was null. Parent run status is `failed`, with no `started_at`, a timeout error, and no result. | **VERIFIED CANCELLED**; parent run **VERIFIED FAILED** | No advisory output was generated in the checked window. Recovery requires a separately authorized, idempotency-reviewed operation. |

The redacted parent-run error was: “Timed out while waiting for the queue worker.” The relevant rows were created at 11:30 and closed at 11:35. No corresponding `planting_advisories`, `external_weather_data`, `yield_predictions`, or other checked derived-output rows were created in the 11:30–11:35 window. The queue rows were removed by the cleanup command after the parent runs failed; this is not evidence that a worker completed them.

Earlier and later two-job safety copies show the same scheduler pattern at 11:05 and 12:05. This confirms an operational interaction: scheduled cleanup can remove timed-out analytics jobs while the queue worker is unavailable, although it preserves a forensic copy. No job was replayed or restored during this audit.

## Log and error assessment

- Repeated Reverb/broadcast delivery failures in the queue log later reached successful completion; they remain evidence of prior transient instability.
- Exit code 137 entries and the historical supervisor `unbound variable` error remain operational evidence requiring live observation. The supervisor now records child/supervisor identifiers and labels code 137 as SIGKILL, but the underlying cause remains unverified.
- `Operation not permitted` database errors in the Laravel log were generated by sandboxed diagnostic attempts, not by application traffic.
- The `There are no commands defined in data namespace` entry came from an incorrect audit command invocation and is not an application feature failure.
- The `iclimate:data-audit` command is database-read-only for this audit and no longer invokes `Cache::flush()`. It still emits diagnostic output and reads configured application services, but it does not clear cache or write database records.

## Safety and change-control record

During this health audit:

- No MySQL or Supabase records were inserted, updated, deleted, synchronized, retried, or flushed.
- No failed jobs were retried or deleted.
- No migrations, backups, Supabase resources, or environment settings were changed.
- The supervisor received a diagnostic-only logging improvement; no application business logic or database behavior was changed.
- No live prediction or browser test data was created.
- No credentials or private notification contents were added to this report.

The audit commands did generate diagnostic log entries. The corrected data-audit command does not clear application cache. The repository already contained unrelated changes from the preceding decommissioning work; this audit does not represent those changes as part of this report.

## Required actions before production sign-off

1. Verify the repaired queue supervisor with a real queue worker in an authorized isolated environment, including the code 137 cause. Confirm all five services remain healthy through repeated start/status/stop checks.
2. Investigate the 15 failed jobs using existing failure records and logs, without automatically retrying them. The two analytics jobs above are now classified as failed/cancelled, but their missing business outputs still require an explicit recovery decision.
3. Preserve the corrected `iclimate:data-audit` formulas and include the command in future regression verification.
4. Run an authorized live WebSocket handshake test against Reverb and a browser walkthrough for the required roles without creating persistent test records.
5. Run any required frontend build in a controlled workspace and review generated artifacts separately.
6. Decide whether the missing outputs for runs 2727 and 2728 should remain failed or be regenerated through a separately authorized, idempotency-reviewed recovery process. Do not recreate or retry the preserved jobs automatically.
7. Re-run the health matrix after the queue is healthy and verify processing through the normal service path using a separately created isolated test job.

## Final decision

### Browser orchestration and WebSocket verification update — 2026-11-10

The Playwright conflict was isolated to fixed shared browser state and server reuse: the old harness reused port 8123 and one SQLite/session location without owning cleanup. It now runs with `reuseExistingServer: false`, a per-run SQLite/session directory, an owned PHP server PID, and graceful TERM/wait cleanup. The fixed port remains intentional because the browser specifications assert the local 8123 URL.

The repaired full suite completed: **162 passed, 42 expected skips, 0 failures**. The six failures were caused by the browser harness using cookie sessions; redirected validation errors and old registration input were lost. Restoring file sessions with an isolated cookie namespace fixed the farm-association F1/F2 and registration K4 scenarios without weakening application validation.

Reverb's explicit local origin policy still passes its 2-test/5-assertion regression check. The raw non-browser client was rejected with `4009 Origin not allowed`, as expected for an unapproved origin. The earlier disposable authenticated browser-origin probe stopped before login-form discovery; the corrected two-run result is recorded in the update below.

### Final live-verification update

The latest elevated read-only preflight confirmed the application is running locally against MariaDB `iclimate_staging_20261103_fix3` with the database queue. Pending jobs: **0**; historical failed jobs: **15**. ML, web, Reverb, and scheduler processes were observed, while no queue worker process was present. Web `/up` and ML `/health` returned HTTP 200; Reverb was listening on 8080. The initial non-elevated launcher status was misleading because the sandbox denied `/bin/ps`; this was an execution-environment limitation, not evidence of stopped web/ML/Reverb services.

The live queue worker remains intentionally unstarted. Runs 2727 and 2728 remain pending owner recovery decisions. Active-environment browser verification remains unverified. Disposable authenticated Reverb verification is complete; see the update below and `docs/ICLIMATE_ISOLATED_VERIFICATION_RESULTS.md`.

New isolated verification passed: one disposable queue job processed exactly once through the real worker; a separate failing job retried twice and produced one isolated failed-job record; and the repaired supervisor processed an isolated analytics job and shut down cleanly. This does not change the active-worker status.

### Authenticated Reverb verification — 2026-11-10

The prior authenticated probe stopped before login because Chromium could not launch under the ordinary sandbox permission. With the required local browser permission, the real landing-page login and protected Reverb path were verified in a disposable SQLite environment. `scripts/isolated-reverb-authenticated-test.sh` was run twice from fresh environments; both runs passed the browser login, Reverb handshake, `/broadcasting/auth`, private-channel subscription acknowledgement, unauthorized-channel HTTP `403`, actual notification event delivery, and reconnect/second-event delivery. No active MariaDB record, active queue, production account, or Supabase resource was used.

The authenticated WebSocket technical blocker is **RESOLVED in isolation**. The final production-readiness decision remains **NOT PRODUCTION READY** because the live queue worker and the two analytics recovery decisions are still outstanding; Supabase retirement remains unchanged.

**NOT PRODUCTION READY.** The core web, ML, scheduler, Reverb listener, database schema, corrected audit command, and isolated test suites are healthy, but the stopped queue and unresolved cause of the historical SIGKILL affect background processing. Live worker, browser/WebSocket, deployment, and the remaining owner/cloud-consumer decisions from the Supabase decommissioning audit are also outstanding.

This report does not authorize database changes, job retries, environment changes, cloud-resource deletion, or Git push.

## Final production-readiness preparation update — 2026-11-10 14:51 UTC

Fresh read-only checks confirmed the local runtime is Laravel `local` with MySQL `iclimate_staging_20261103_fix3`, database queue, and Reverb broadcasting. Queue inventory was `0` pending, `0` reserved, and `15` historical failed jobs. ML, web, Reverb, and scheduler processes were observed on the expected project-owned ports; no queue worker was started.

The authenticated Reverb blocker is resolved in isolation: the disposable browser-origin probe passed twice for login, handshake, private-channel authorization, subscription, unauthorized `403`, actual notification delivery, and reconnect. Active-account and live-worker checks remain separate.

A new confirmed production blocker was identified in retained Laravel logs: when Reverb is unavailable and the circuit is open, `RetryBroadcastEvent::handle()` calls an undefined `release()` method. The focused resilient-broadcasting suite passed 15 tests and 83 assertions but does not cover that branch. The historical exit-137 cause remains unverified and is unrelated evidence. No application code was changed to address this blocker during the preparation step.

Required approvals remain: bounded live-worker authorization, independent disposition for Analytics Runs 2727 and 2728, repair/regression verification of the retry branch, active-environment browser/worker evidence, and Supabase dashboard/external-consumer confirmation. Production readiness remains **NOT PRODUCTION READY** and Supabase retirement remains **BLOCKED — INSUFFICIENT EVIDENCE**.

### RetryBroadcastEvent repair update — 2026-11-10

The confirmed circuit-open failure was repaired. Laravel's `BroadcastEvent` has queue metadata but no `release()` method; `RetryBroadcastEvent` now uses `InteractsWithQueue`, allowing the worker-injected queue job to release the current attempt through Laravel's supported queue contract.

The focused suite now passes **17 tests and 96 assertions** for resilient broadcasting, including direct release assertions and a real isolated `queue:work` release/recovery cycle. The combined notification, channel-authorization, Reverb-origin, and resilient-broadcasting matrix passes **27 tests and 139 assertions**. The recovery scenario delivered one event for one saved notification and created no failed-job record.

This repair does not establish the cause of historical exit 137, authorize the active worker, retry Runs 2727/2728, or resolve Supabase dashboard/external-consumer blockers.

## Final regression and preflight update — 2026-11-10

The full isolated Laravel regression suite completed with **1,733 passed, 5 skipped, 0 failed, and 13,577 assertions** in 472.17 seconds. Existing PHPUnit metadata deprecation warnings remain non-failing. The repaired `RetryBroadcastEvent` coverage remains **27 passed, 139 assertions**, including circuit-open release, exhaustion, recovery, and duplicate-prevention scenarios.

Read-only local preflight confirmed Laravel is healthy in `local` mode with MySQL `iclimate_staging_20261103_fix3`, database queue, `0` pending jobs, `0` reserved jobs, and `15` historical failed jobs. No active worker, real job processing, database mutation, or production operation was performed. Production readiness remains **NOT PRODUCTION READY** pending approval-gated live worker verification and the other documented gates.

## Local-readiness verification — 2026-11-10

The current local system is **READY WITH LIMITATIONS** for everyday read-only and
interactive use. Fresh checks confirmed:

- Laravel local environment and MariaDB `10.4.28` connectivity: **PASS**.
- Laravel `/up`: **PASS** (HTTP 200).
- Python ML `/health`: **PASS** (HTTP 200).
- Reverb listener on `127.0.0.1:8080`: **PASS**; HTTP 404 at its root is expected.
- Scheduler supervisor: **OBSERVED**.
- Queue worker: **NOT RUNNING / APPROVAL-GATED**; no active jobs were consumed.
- Queue inventory: `0` pending, `0` reserved, `15` historical failed jobs.
- Authentication, role access, dashboards, CRUD, reports, notifications, responsive workflows, and browser journeys: covered by the completed isolated Laravel/Playwright evidence above.

The latest 250 Laravel log lines contained simulated/expected Reverb-unavailable
errors from resilience testing and one PsySH history-permission warning from the
tool environment; they contained no SQLSTATE or queue-exhaustion entries. The
large historical error totals in the log are not treated as current defects
without a reproduced production path.

Analytics Runs 2727 and 2728 remain preserved failed/cancelled records with no
regeneration. Each still requires an independent owner decision. Historical
exit code 137 remains SIGKILL-confirmed but causally unverified. Local readiness
is therefore limited by the stopped approval-gated worker and unresolved
analytics decisions, not by a newly discovered application defect.

## Final local operational verification — 2026-11-11

Fresh read-only checks confirmed Laravel local mode, MariaDB connectivity,
`0` pending jobs, `0` reserved jobs, and `15` historical failed jobs. Web and
ML health endpoints returned HTTP 200; Reverb remained available on port 8080;
no queue worker was started.

Analytics metadata was rechecked without exposing payloads:

| Run | Type | Status | Started | Result | Failure evidence |
|---:|---|---|---|---|---|
| 2727 | `dataset_processing` | `failed` | `null` | absent | timeout-class error; preserved |
| 2728 | `advisory_ai` | `failed` | `null` | absent | timeout-class error; preserved |

Recommendation for Run 2727: **INVESTIGATE FURTHER**, then obtain separate
owner approval before any regeneration. Its normal workflow can update the
analytics-run result/status.

Recommendation for Run 2728: **INVESTIGATE FURTHER**, then obtain separate
owner approval before any regeneration. Its normal workflow can create
planting-advisory output and downstream notification/realtime effects, even
where generation-key idempotency applies.

Active-account write testing remains **NOT VERIFIED** because no approval was
given to create or modify records in the active MariaDB database. Equivalent
login, permissions, CRUD, dashboards, and browser workflows are covered by
the completed disposable-account Playwright and Laravel suites. Authenticated
Reverb delivery and reconnect are likewise verified in the disposable
environment only.

## Queue stability and recovery verification — 2026-11-11

The focused disposable regression set completed with **50 passed, 253
assertions, 0 failures** in 9.27 seconds:

- `PythonAnalyticsQueueTest`: analytics queueing, status transitions, and
  isolated Python-result persistence passed.
- `ResilientBroadcastingTest`: Reverb availability/outage, circuit-open
  release, bounded retries, failed-job recording, recovery, notifications,
  and duplicate prevention passed.
- `SystemHealthTest`: queue, failed-job, analytics, scheduler, model-health,
  permissions, and no-side-effect health checks passed.

Retained disposable-worker evidence also shows direct worker success, bounded
retry/failure handling, supervisor restart, and clean shutdown. No active
MariaDB worker was started. Memory usage was not claimed as verified: the
historical code 137 remains SIGKILL-confirmed but its OOM versus external or
supervisor termination cause is still **UNVERIFIED**. A sustained active-worker
observation therefore remains approval-gated.

## Supervisor TERM-shutdown repair — 2026-11-11

A disposable TERM test found a second shutdown defect: mixed `||`/`&&`
precedence in the supervisor's top-of-loop and post-child guards allowed a
TERM received during the five-second restart delay to fall back into the
restart path. Both guards now use explicit `if` blocks that exit when either
the TERM handler sets `stopping=1` or the stop flag exists.

Regression evidence:

- `bash scripts/test-local-service-supervisor.sh`: **PASS** — supervisor
  exits after TERM without restarting.
- `bash -n scripts/local-service-supervisor.sh scripts/test-local-service-supervisor.sh`: **PASS**.
- Focused disposable Laravel suite after the repair: **50 passed, 253
  assertions, 0 failures**.

This fixes graceful supervisor shutdown in isolation. The active MariaDB queue
worker remains intentionally unstarted, and the historical exit-137 cause and
peak worker memory remain **UNVERIFIED**.

## Active-worker preparation and disposable MariaDB verification — 2026-11-11

The active local environment was inspected without starting a queue worker. The
configured database is MariaDB/MySQL `iclimate_staging_20261103_fix3`; Laravel
uses the database queue and the normal worker command targets
`analytics,default,broadcasts`. The read-only queue snapshot was `0` pending,
`0` reserved, and `15` historical failed jobs. No duplicate queue worker was
observed. Web, ML, Reverb, and scheduler listeners/processes remained present;
the expected listeners were `8000`, `8010`, and `8080`.

The scheduler is active and can create or modify data through weather refresh,
PAGASA/advisory, risk/boundary refresh, stale-queue cleanup, dataset and
prediction processing, cache warming, alert publishing, and backup/verification
tasks. For that reason, starting the general local stack is not a harmless
queue test.

### Disposable MariaDB worker evidence

A retained disposable run imported the logical MariaDB backup
`storage/app/backups/iclimate_2026-11-10_0703.backup` into a temporary isolated
MariaDB instance, cleared only the clone's queue tables, dispatched one
`Tests\\Support\\IsolatedQueueProbe` job to a dedicated queue, and ran a real
Laravel `queue:work` process. It produced exactly one marker line and left no
successful probe job pending:

`disposable_mariadb_worker=PASS success_marker_lines=1 failed_jobs_before=0 pending_jobs_after=1`

The remaining pending row was the deliberately failing probe after its first
attempt. This verifies real Laravel-to-MariaDB queue consumption for the
success path, but it is not evidence that the retry/exhaustion path completed
on MariaDB. The previously retained disposable SQLite worker evidence verifies
bounded retry, failed-job recording, supervisor restart, and clean shutdown.

A repeat of the MariaDB clone could not be completed in this execution
environment: the Homebrew MySQL 8.4 initializer crashed, and the XAMPP
MariaDB initializer requires ownership/InnoDB setup permissions unavailable to
the sandbox. No active database or backup was modified. MariaDB retry,
timeout, sustained RSS/CPU observation, and automatic-restart behavior remain
**NOT VERIFIED in the clone**; they remain covered only by the existing
disposable SQLite evidence where stated.

### Approval-gated active verification

No worker connected to active MariaDB was started. The exact approved scope
must be recorded before execution: operator, observation window, queue scope,
monitoring owner, and rollback owner. The safest proposed operation is one
dedicated harmless probe queue, not the general worker. It would create one
temporary `jobs` row, consume only that named queue, observe the worker and
queue accounting for a bounded window, and stop via the verified supervisor
shutdown path. It must not run until the owner explicitly approves the
commands and expected side effects in
`docs/ICLIMATE_ACTIVE_WORKER_APPROVAL_PLAN.md`.

The active worker remains **NOT VERIFIED / APPROVAL-GATED**. Analytics Runs
2727 and 2728 remain failed/cancelled with no regeneration; each still needs a
separate owner decision. Historical exit code 137 remains SIGKILL-confirmed
but its OOM, manual, external, or supervisor cause remains **UNVERIFIED**.
