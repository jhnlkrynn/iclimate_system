# iClimate Isolated Verification Results

**Date:** 2026-11-10  
**Scope:** Disposable SQLite, queue, supervisor, browser, and Reverb checks only  
**Active database:** not used by isolated tests

## Isolation evidence

- Temporary SQLite database under `/private/tmp`.
- Process-level `APP_ENV=testing`, `DB_CONNECTION=sqlite`, and temporary `DB_DATABASE`.
- Dedicated `isolated-test` / `isolated-failure` queues for direct worker tests.
- Supervisor-path test used the isolated database but the normal `analytics,default,broadcasts` queue list; only a disposable test job was present.
- Array cache, cookie/session isolation, log mail, sync or isolated queues, and no external production API calls.
- Temporary processes and files were removed after the checks.
- Active MariaDB, active queue rows, backups, and Supabase resources were not touched.

## Worker results

| Check | Result | Evidence |
|---|---|---|
| Direct worker success | PASS | `queue:work database --queue=isolated-test --once` exited 0; one marker line; no pending job. |
| Direct worker failure/retry | PASS | Separate failing job ran twice; no pending job remained; one failed-job record was created. |
| Supervisor worker success | PASS | Repaired supervisor processed one isolated `analytics` job exactly once; marker line count 1; isolated queue count 0. |
| Supervisor shutdown | PASS | Supervisor received TERM, child stopped, supervisor exited 0; no temporary process remained. |
| Duplicate side effects | PASS for probe | One successful marker was observed. This does not claim exactly-once delivery generally. |
| Active worker | UNVERIFIED | No worker connected to active MariaDB was started. |

The first closure-based probe was rejected as invalid harness evidence because eval'd closures could not be serialized reliably. It was replaced with `Tests\Support\IsolatedQueueProbe`, and the valid checks above were rerun.

## Supervisor and exit diagnostics

The supervisor synthetic restart still passes. It now records service, child PID, supervisor PID, exit code, and labels code 137 as SIGKILL. Historical code 137 remains causally unverified; no OOM or memory-pressure evidence was found.

## Reverb/WebSocket results

- Effective allowed origins: `127.0.0.1`, `localhost`; wildcard is absent.
- Origin regression tests: **2 passed, 5 assertions**.
- Raw Node probe: server reached, rejected with `4009 Origin not allowed` because the probe did not present an allowed browser origin.
- Authenticated isolated browser-origin orchestration: **no reliable result**; temporary test servers were stopped and no real or active event was sent.
- Authenticated channel subscription, event delivery, unauthorized subscription rejection, and reconnect: **UNVERIFIED in the earlier probe; superseded by the two-run PASS below**.

## Browser results

The repaired isolated Playwright suite: **162 passed, 42 expected skips, 0 failures**. The six affected scenarios (farm-association F1/F2 and registration K4 in both viewports) pass. It uses throwaway SQLite and disposable accounts. Active-account workflows remain unverified.

A fresh rerun was attempted with host permission because the sandbox could not bind port 8123, but overlapping temporary web-server processes prevented a final summary. Those explicitly identified test processes were stopped. The prior completed 162/42 baseline remains the authoritative browser result; no new browser pass count is claimed.

Skip classification remains: viewport-conditional skips and intentional static/scroll/motion coverage; no skip was removed to increase pass counts.

## Exact current-run validation

- Current `iclimate:data-audit`: all checks passed.
- Current migration status: all listed migrations ran.
- Current active queue inventory: 0 pending, 0 reserved, 15 failed historical jobs.
- Web endpoint: HTTP 200.
- ML endpoint: HTTP 200.
- Reverb root: HTTP 404, expected service-root behavior.
- Isolated worker probe: success and retry/failure checks passed.
- Isolated supervisor worker: passed.
- Reverb origin regression: 2 passed, 5 assertions.
- Shell syntax, PHP lint, and `git diff --check`: passed.

## Remaining limitations

No active worker, historical analytics recovery, or active browser-account journey was executed. The disposable authenticated Reverb event is verified below; active-environment account and worker operations remain owner-authorized or evidence-limited.

## Browser/WebSocket orchestration update — 2026-11-10

The browser harness was repaired after reproducing the overlap failure. The prior fixed SQLite path, fixed port, `reuseExistingServer: true`, and untracked server lifetime allowed stale/overlapping test servers to interfere with a run. The harness now uses a per-run SQLite/session directory, an explicit `PLAYWRIGHT_PORT`, `reuseExistingServer: false`, one owned PHP server PID, and TERM/wait cleanup on exit. The existing specs' fixed `127.0.0.1:8123` contract is preserved; an occupied live server is refused by Playwright rather than reused.

Validation:

- Representative fixed-port test: **1 passed**.
- Root cause of all six failures: the harness had switched from file sessions to cookie sessions while trying to isolate state. Redirected validation errors and old registration input were lost. Restoring file sessions with an isolated cookie namespace fixed F1, F2, and K4 without weakening application validation.
- Complete repaired suite: **162 passed, 42 expected skips, 0 failures**.
- The run completed and temporary server/database/session artifacts were cleaned up.

The effective Reverb origin allowlist remains explicit (`127.0.0.1`, `localhost`) with no wildcard. A raw Node client was correctly rejected with `4009 Origin not allowed`.

### Authenticated Reverb verification update — 2026-11-10

The prior login-form discovery failure was a local Chromium launch-permission problem: the browser was terminated before opening the page. With the required local browser permission, the actual landing-page form was found and the real UI login succeeded.

The reproducible disposable probe is `scripts/isolated-reverb-authenticated-test.sh`, with protocol checks in `scripts/isolated-reverb-authenticated-test.mjs`. Each run uses fresh SQLite, file sessions, disposable accounts, dedicated Reverb credentials, and dedicated localhost ports. It does not use active MariaDB, the active queue, or production `.env` values.

Two fresh runs completed with the same result: login **PASS**, browser-origin handshake **PASS**, `/broadcasting/auth` **PASS**, private-channel subscription acknowledgement **PASS**, unauthorized subscription rejection **PASS** with HTTP `403`, actual `NotificationController` → `NotificationChanged` → Reverb → WebSocket event delivery **PASS**, and reconnect/second-event delivery **PASS**.

The authenticated Reverb blocker is **RESOLVED in the isolated environment**. Active queue-worker and active-account browser verification remain separate limitations.
