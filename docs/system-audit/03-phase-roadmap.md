# iClimate System Audit — Phase Roadmap (2026-11-14)

Phases are ordered by risk removed per unit of change. Each phase is independent enough to ship alone and
names the findings it closes (IDs from `02-findings.md`). The matching Codex prompt is in
`04-codex-prompts.md`.

Rules for every phase:

- Change only what the phase names. No UI redesign, no refactor outside the listed files.
- Do not change, retrain or replace any ML model file without written owner approval.
- Do not restore deleted PSA datasets. Do not delete or rewrite original datasets.
- Do not run migrations, seeders or data corrections on the active database without owner approval; build
  them with a dry-run mode first.
- Keep the full PHPUnit suite green (1,789 tests today).
- Do not commit, push or deploy unless the owner asks.

| Phase | Title | Closes | Needs owner |
|---|---|---|---|
| 0 | Owner actions (no code) | SEC-01 (staff), SEC-03, SEC-04, DATA-01 decision, OPS-01 interim | Yes |
| 1 | Account and access hardening | SEC-01, SEC-02, SEC-05, DATA-01 | Approval to apply to data |
| 2 | Runtime reliability and housekeeping | OPS-01 to OPS-07, SEC-07 | No |
| 3 | Data integrity | DATA-02 to DATA-06 | Approval to apply to data |
| 4 | ML input contract and honest reporting | ML-01, ML-02, ML-03 | Approval for any model change |
| 5 | Test coverage and documentation | TEST-01 to TEST-03, FEAT-01, SEC-06, SEC-08 | Decision on mobile API |
| 6 | Deployment readiness | OPS-07 and re-verification | Yes |

---

## Phase 0 — Owner actions (no code)

**Objective.** Remove the exposures that need the owner's hands, today.

1. Change the passwords of staff accounts #8 (MAO), #9 (IT Expert) and #15 (MAO).
2. MariaDB: set `bind-address=127.0.0.1`, set a `root` password, remove anonymous and blank-password
   accounts (needs sudo).
3. Rotate the Groq and mail credentials exposed in Git history; revoke unused keys.
4. Decide which account is the Municipal Agriculturist (MAO Head).
5. Stop the Mac from sleeping while iClimate runs (`sudo pmset -a sleep 0`, or keep it on power with
   `caffeinate -s`), until Phase 2 builds this into the supervisor.
6. Decide what to do with the extra backup `storage/app/backups/iclimate_2026-11-14_0147.backup` created
   during this audit (keep or delete).

**Acceptance.** A default password no longer signs in to any staff account; `nc -z <LAN IP> 3306` fails
from another device; `mysql -uroot` without a password is refused; every-minute tasks show no gap over an
idle hour.

## Phase 1 — Account and access hardening

**Objective.** No account stays on a shared or default password, and access controls cannot be bypassed or
wiped by accident.

**Affected modules.** Authentication, first-password flow, MAO personnel, Users (IT), Roles & Permissions,
seeders.

**Required changes.**

- `EnsurePasswordChanged`: enforce on every method and on JSON requests; keep only logout and the
  change-password routes open. Default `iclimate.mao_allow_skip_password_change` to false.
- Remove the literal `Password@123` fallback from `config/iclimate.php`; seeders and
  `MaoPersonnelController` must fail clearly when no password is configured, or generate a random one.
- New artisan command `iclimate:flag-default-passwords` with `--dry-run`: finds accounts whose hash is
  shared with another account or matches a configured default, and sets `must_change_password`. It must not
  print or store any password.
- `FarmerMasterlistSeeder`: one hash per account (random password or reset-link flow), never a shared hash.
- Roles & Permissions: validate that `modules` is present; refuse or require confirmation when a save
  removes every module from a role; write the audit log entry with before and after.
- IT Users page: an audited control to set or move the MAO Head flag (exactly one head at a time).

**Tests.** Feature tests for: must-change user blocked on POST, PUT, DELETE and JSON; skip refused by
default; empty permissions post rejected; head flag can be set by IT only and moving it unsets the previous
head; the flagging command in dry-run changes nothing.

**Acceptance.** All new tests pass; full suite green; on a clone, the flagging dry-run reports 716 accounts
and applying it leaves 0 accounts with `must_change_password = 0` on a shared or default hash.

## Phase 2 — Runtime reliability and housekeeping

**Objective.** The system keeps running unattended and does not fill the disk.

**Affected modules.** Supervisor scripts, scheduler, System Health, logging, cache, backups, settings
storage, browser-test server.

**Required changes.**

- `scripts/local-service-supervisor.sh` / `start-iclimate-local.sh`: hold a wake assertion on macOS while
  services run.
- System Health: new check that reports the largest gap between consecutive every-minute task runs in the
  last 24 hours, and flags a missed `iclimate:automation-run`.
- Daily prune of `scheduled_task_runs` (keep 14 days) and of expired file-cache entries; schedule
  `queue:prune-failed`.
- `.env.example` and docs: `LOG_STACK=daily`, `LOG_DAILY_DAYS=14`, `LOG_LEVEL=info`; mail never logged at
  debug outside tests.
- Environment isolation: an explicit base path for settings, cache, backups and model reports that a second
  instance can override; remove the hard-coded database name from `config/database.php` (`mysql_backup`);
  make `tests/Browser/serve.sh` use its own storage directory.

**Tests.** Prune commands (unit); health check with a synthetic gap; a test that a second environment with
its own storage path does not write the default settings file.

**Acceptance.** After 24 idle hours: no gap over 2 minutes in every-minute tasks; `scheduled_task_runs`
under 40,000 rows; cache directory size stable; a browser-test run leaves `storage/app` of the active app
untouched (compare file listing before and after).

## Phase 3 — Data integrity

**Objective.** Every farm, account and reference row is one the application can use, and seeded data is
visibly marked.

**Affected modules.** Farms, farmer profiles, barangay reference tables, rice production, climate records.

**Required changes.**

- Extend the existing reconciliation commands (`iclimate:barangay-reconciliation`,
  `iclimate:apply-barangay-corrections`) to report and, with `--apply`, fix the 12 unplaceable farms;
  misspellings map to the official name, unsupported barangays are listed for the MAO, never guessed.
- Decide and implement one rule for the 10 unsupported barangays in `barangay_profiles` and
  `heatmap_areas`: hide them consistently or mark them unsupported.
- Validate barangay against `LianBarangays` on every farm and profile write path.
- Report command for farmer accounts without a profile; create the profile on first sign-in.
- Wherever a figure reads `rice_productions`, show when the source rows are unverified or seeded.
- Either remove the `verified` middleware or adopt email verification (owner decision); prune expired
  password-reset tokens on a schedule.

**Tests.** Reconciliation dry-run and apply on fixtures; validation tests per write path; label tests.

**Acceptance.** On a clone: 0 farms outside the supported list or the reviewed exceptions list; dry-run
output reviewed and approved by the owner before anything is applied to the active database.

## Phase 4 — ML input contract and honest reporting

**Objective.** The system detects and says when a prediction is outside what the model was trained on.
**No accuracy claim is added. ACCURACY NOT VERIFIED stays.**

**Affected modules.** `PredictionEngine`, Python yield service, System Health, model evaluation page, ML
tests.

**Required changes.**

- Record the training feature ranges of the deployed model as a contract file next to the model (read from
  the model's trees, as the 2026-11-12 audit did). Do not modify the `.pkl`.
- At inference, compare each input to the contract; store an `out_of_range` flag with the prediction; show
  it on the IT model page and in System Health.
- Keep the "not validated" label on every yield figure for farmers and MAO while any input is out of range.
- Tests: a contract test that fails when stored inputs are outside the contract; a variance test that fails
  when predictions for clearly different inputs collapse; hold-out tests that compare against a climatology
  or mean baseline and report the result instead of asserting `>= 0`.
- A written options paper for the owner (recover training definitions, retrain on documented features, or
  replace the model with a labelled baseline). Implementation of any option is a separate, approved task.

**Acceptance.** System Health shows the share of predictions out of contract (today: all of them); no
wording anywhere states or implies verified accuracy; model files are byte-identical before and after.

## Phase 5 — Test coverage and documentation

**Objective.** The automated suites cover what this audit had to check by hand.

**Required changes.**

- Fix `account-deletion.spec.mjs` D1 on desktop after finding the real cause.
- Add the route × role matrix as a PHPUnit feature test generated from the route list.
- Add authenticated Playwright smoke tests per role: dashboard, Heat Map, planting schedule create,
  advisory publish, notification bell.
- A documented way to run the 5 MariaDB-only tests against a throwaway MariaDB database.
- Update or retire `docs/known-failing-tests.md`.
- Mobile API: finish (token auth) or remove, per owner decision; add captcha or stricter throttling to
  registration if kept.
- Tighten the CSP once inline scripts are moved to assets (may be deferred).

**Acceptance.** Browser suite 0 failures on both viewports, three runs in a row; matrix test in the suite.

## Phase 6 — Deployment readiness

**Objective.** A checklist-driven move to an always-on host.

**Required changes.** Production env template (`APP_ENV=production`, HTTPS, `SESSION_SECURE_COOKIE=true`,
`LOG_LEVEL=info`, cached config and routes); a real process supervisor; `DB_TIMEZONE=+08:00` kept; backup
and restore drill on the target; rerun of this audit's matrix and suites on the target.

**Acceptance.** Every Critical and High finding closed and re-verified on the target host; restore drill
passed; owner sign-off.
