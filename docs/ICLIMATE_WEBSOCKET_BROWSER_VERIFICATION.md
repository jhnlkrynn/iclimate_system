# iClimate WebSocket and Browser Verification

**Date:** 2026-11-10  
**Decision:** Technical verification incomplete; production readiness remains **NOT PRODUCTION READY**.

## Browser harness repair

The startup conflict was caused by a shared fixed SQLite/session state, a shared port, `reuseExistingServer: true`, and no PID-specific cleanup. A stale or overlapping temporary server could make a run report connection refusals or attach to the wrong state.

The harness now:

- preserves the existing test URL `127.0.0.1:8123` required by current browser assertions;
- refuses reuse through `reuseExistingServer: false`;
- creates a unique temporary SQLite database, session namespace, and server log per run;
- directs Laravel file sessions to that same unique temporary run directory;
- starts one owned PHP server and terminates/waits for that PID on exit;
- removes only its own temporary run directory.

Run command:

```text
npm run test:browser
```

## Results

- Focused affected scenarios: **6 passed, 0 failed** across desktop and mobile. The command also matched two unrelated feature-card tests, for **8 passed** total.
- Full repaired run: **162 passed, 42 expected skips, 0 failures**.
- Root cause of F1/F2/K4: the isolation repair had changed the browser server from Laravel's file-session driver to the cookie-session driver. Redirected validation errors and old registration input were not reliably preserved, so farm-association validation messages and K4's `#register` state disappeared. The harness now retains the file-session driver with an isolated cookie namespace while keeping its unique run directory, PID cleanup, and no-server-reuse behavior.
- The run completed normally and its temporary test server/database/session artifacts were cleaned up.

## Reverb/WebSocket

- Reverb listener and local service health: **PASS**.
- Allowed origins: `127.0.0.1`, `localhost`; wildcard: absent.
- Origin regression test: **PASS**, 2 tests and 5 assertions.
- Raw Node client: reached Reverb but received `4009 Origin not allowed`, confirming origin enforcement rather than proving an outage.
- The initial isolated browser-origin probe was stopped by the local Chromium sandbox before page startup. With the required local browser permission, the reproducible probe `scripts/isolated-reverb-authenticated-test.sh` completed twice from fresh SQLite/session environments. Both runs passed real UI login, browser-origin handshake, `/broadcasting/auth`, protected subscription acknowledgement, unauthorized-channel HTTP `403`, actual notification event delivery, and reconnect/second-event delivery.

No active live event, real user notification, active queue job, or active database record was created by this verification. The disposable probe's temporary processes and database were removed. Authenticated WebSocket verification is **PASS in isolation**; active-environment browser verification remains outside this result.

## Remaining blockers

1. Resolve or explicitly accept the six browser scenario failures and rerun the complete suite.
2. Complete a real authenticated browser-origin Reverb handshake, subscription, harmless isolated event, and reconnect test.
3. Authorize live queue-worker verification separately; no active worker was started.
4. Keep Supabase retirement **BLOCKED — INSUFFICIENT EVIDENCE** pending its independent dashboard, external-consumer, and owner-acceptance checks.
