# iClimate System Audit — Test Results (2026-11-14)

Code under test: branch `main` at `c34c6e5b` plus 405 uncommitted paths (266 modified, 139 untracked). The
working tree was left as it was found.

## Automated suites

| Suite | Environment | Passed | Failed | Skipped | Notes |
|---|---|---|---|---|---|
| PHPUnit (Unit + Feature) | in-memory SQLite, array cache, sync queue | 1,784 | 0 | 5 | 1,789 tests, 13,825 assertions, 510 s, 4 PHPUnit deprecations |
| Python (`python_scripts/tests`) | `.venv-ml`, Python 3.12 | 50 | 0 | 0 | 1.7 s |
| JavaScript (`node --test tests/js/`) | Node | 19 | 0 | 0 | |
| Playwright (`tests/Browser`) | own SQLite server on :8123, desktop 1280 + mobile 375 | 167 | 1 | 42 | 2.0 min, under `caffeinate` |

**Skipped.**

- PHPUnit: the 5 tests of `TimestampRefreshMigrationTest` (MariaDB/MySQL only).
- Playwright: 42 tests that apply to one viewport only (mobile-drawer tests on desktop and the reverse).

**Failed.**

- Playwright `account-deletion.spec.mjs:18` D1 on `desktop-1280`: timeout waiting for `#login-email` on `/`
  at the second sign-in. Passes on `mobile-375`. See TEST-01.

## Route and role matrix (manual, isolated server)

The unmodified application was served from a scratch front controller that signs a chosen user in for one
request. Two databases were used: a fresh SQLite database with seeded accounts, and the existing audit clone
`iclimate_rt_audit_20261111`.

| Run | Requests | HTTP 5xx | Result |
|---|---|---|---|
| SQLite, all `GET` routes, 7 personas | ~1,050 | 0 | Access matrix as expected for every role |
| SQLite, all write routes with an empty body, 6 personas | ~930 | 0 | Guest 401; wrong role 403; wrong owner 403/404; allowed role 422/302 |
| Clone (real data), all `GET` routes, 3 personas, twice | ~900 | 0 | All pages under 1.1 s; most under 100 ms |

Limits of this matrix:

- Write routes were sent empty bodies. This tests authentication, authorization and validation, not that a
  valid submission produces the right record.
- The clone timings used a warm cache and a local database. They are not a load test.
- On the clone, the farmer account was pending a password change and the MAO account was affected by the
  settings problem described in the incident note, so their page coverage there is partial. The SQLite run
  is the complete matrix.

## Read-only checks on the active system

- Database integrity and freshness queries (`SELECT` only) on `iclimate_staging_20261103_fix3`.
- Process, port and supervisor status; scheduler run history; queue and failed-job tables.
- Application log summary for 10–14 November.
- Default-password check (hash comparison, no sign-in).
- MariaDB bind address and `root` access.

## Not tested

These were not exercised and no claim is made about them:

- Model accuracy of any kind. **ACCURACY NOT VERIFIED** stands.
- Real email delivery, Groq responses, and live PAGASA parsing beyond what the log shows.
- Live WebSocket delivery in a browser (last verified 2026-11-11; today only the Reverb process and port).
- Backup restore, model upload, model retrain, dataset upload, spreadsheet imports with real files.
- Valid end-to-end submissions per role (create a schedule, publish an advisory, send an alert) outside
  what PHPUnit and Playwright already cover.
- MAO barangay-scoped behaviour on real data (no assignments exist on the active database).
- PDF and XLSX export content (the farmer PDF route answered 200; MAO exports answered 404 without
  parameters).
- Responsive layout beyond the two Playwright viewports.
- Any production or remote host. The system runs on one local Mac only.
- Modules switched off by configuration: Field Reports, the chatbot knowledge admin, and legacy redirects
  (`farmer-organizations`, `live-forecasting`, `usability-survey`).
