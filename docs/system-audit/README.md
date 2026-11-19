# iClimate System Audit — Summary (2026-11-14)

Read-only audit of the iClimate system as it runs on the local Mac: `main` at `c34c6e5b` plus 405
uncommitted paths, active database `iclimate_staging_20261103_fix3` (MariaDB 10.4.28), PHP 8.5.12.

| File | Contents |
|---|---|
| `01-test-results.md` | Suites run, passed/failed/skipped, what was not tested |
| `02-findings.md` | Every finding with severity, evidence, files, root cause and fix |
| `03-phase-roadmap.md` | Phases 0–6 with objectives, changes, tests and acceptance criteria |
| `04-codex-prompts.md` | One copy-paste Codex prompt per phase |

## Overall status

**The application works and is well tested; it is not ready to deploy.** The code paths are sound. The
problems are in accounts, operations, data and the meaning of the ML output.

- **Functionality: good.** 1,784 of 1,789 PHPUnit tests pass (5 skipped, 0 failed), 50 of 50 Python tests,
  19 of 19 JavaScript tests, 167 of 168 browser tests that ran. No route returned a server error for any
  role, on seeded data or on a clone of the real data.
- **Authorization: good.** Across 300 routes and 6 personas, no role reached anything it should not, and
  no farmer could read or change another farmer's records.
- **Security: not acceptable for deployment.** 716 active accounts are on known default passwords,
  including an IT Expert account; the forced password change can be skipped; the database server listens
  on the network with a passwordless `root`.
- **Reliability: fragile.** The Mac sleeps after one idle minute and the scheduler and queue stop with it;
  missed runs are not caught up. Cache, logs and one table grow without limit.
- **ML: ACCURACY NOT VERIFIED.** The deployed yield model receives inputs outside its training definitions.
  All 6,498 stored predictions fall between 4.11 and 4.18 t/ha (8 distinct values). The number shown to
  farmers carries almost no information.

## Findings by severity

| Severity | Count | IDs |
|---|---|---|
| Critical | 2 | SEC-01, ML-01 |
| High | 6 | SEC-02, SEC-03, OPS-01, DATA-01, ML-02 (carried), SEC-04 (carried) |
| Medium | 11 | SEC-05, OPS-02, OPS-03, OPS-04, OPS-05, DATA-02, DATA-03, DATA-04, ML-03 (carried), FEAT-01, TEST-01 |
| Low | 9 | TEST-02, TEST-03, OPS-06, OPS-07, SEC-06, SEC-07, SEC-08, DATA-05, DATA-06 |

Three findings are carried from the audits of 10 and 12 November and were not re-run today; they are marked
CARRIED in `02-findings.md`.

## Recommended first phase

**Phase 0 (owner actions), then Phase 1 (account and access hardening).** Phase 0 needs no code: change the
three staff passwords, close the database port and set a `root` password, stop the Mac sleeping, and name
the MAO Head. Phase 1 then removes the shared passwords for good and closes the bypass.

## Deployment readiness

**Not ready.** Blocking: SEC-01, SEC-02, SEC-03, SEC-04 and OPS-01. ML-01 does not stop the software from
running, but the yield figure should not be presented as a prediction of anything until Phase 4 is done
and the owner has chosen how to fix the model. A deployment decision should follow Phase 6.

## Incident during this audit

The audit was meant to be read-only. Three things were changed by mistake on the active system. All were
found and undone within minutes; they are recorded here so nothing is hidden.

1. **One request ran against the active database.** A scratch environment file lost its database line, so
   a test server briefly used the active database. One `GET /admin/dashboard` request updated
   `users.id = 9` `last_active_at` and `updated_at` (to 2026-11-14 01:48:30 UTC). Nothing else was written.
   This was not reverted; it is an activity timestamp.
2. **The active app's settings file was written.** The isolated test server was supposed to use its own
   storage directory, but PHP's built-in server does not pass that setting to Laravel. An empty Roles &
   Permissions save from the test server created `storage/app/system-settings.json` in the active app and
   closed 14 modules to Farmer and MAO accounts for about 7 minutes (09:48–09:55 Manila time). The file did
   not exist before; it was moved out and the defaults were confirmed restored.
3. **The active app's file cache received entries built from test data.** All 1,615 cache files written
   while the test servers ran were moved out of `storage/framework/cache`; the app rebuilt them from its
   own database. A model-comparison report the test server wrote to `storage/models/` was also moved out.
   No model file was touched.

Left in place for the owner to decide: `storage/app/backups/iclimate_2026-11-14_0147.backup`, a genuine
39.9 MB backup of the active database triggered by the same test server.

`git status` is identical before and after the audit apart from this folder. The cause of items 2 and 3 is
itself finding OPS-05, and item 2 exposed finding SEC-05.
