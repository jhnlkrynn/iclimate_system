# RC2 change manifest: candidate 2026.11.18-rc2 (r2)

**Release identity:**

| Item | Value |
|---|---|
| Base | RC1 `d43cd1aa1b3915b2b2e2928e21b207b3346f1170` (tag `v2026.11.18-rc1`, unchanged) |
| Source fingerprint | `04c7db9fedf0db9c077521adfebae8621291b1ddcf5e0e371a72a966dcb2d29d` (sha256 of `source.manifest`) |
| Archive | `source.tar.gz`, sha256 `c26275e0d6cbde76d5c3582afd9d637f23f992408dddd815ffd5aca2e24527ad` |
| Files | 1,504 |

**Against RC1:** 42 files added, 48 modified, **0 deleted**. Every RC1 file is still shipped. The per-file list with sha256 hashes is `changed-files.txt`.

**Superseded candidate:** `../2026.11.18-rc2/` (fingerprint `c9070a44…`, 1,486 files, built 16:46). This candidate keeps every one of its files and adds 18 more: the ML input fix, its tests and evidence, and RC2 tests that were created after that build. That directory has been left exactly as it was.

## 1. Frozen ML input fix (Claude; see `docs/deployment/ICLIMATE_ML_FROZEN_INPUTS_FIX_2026-11-18.md`)

| File | Change |
|---|---|
| `app/Support/YieldModelClimateWindow.php` (new) | Climate inputs anchored on the target month. The previous-month and trend inputs use only observed months before it. The whole-season inputs use observed months plus ready monthly forecasts, and forecast months are flagged. A month with no data stays missing and is never substituted. |
| `app/Services/Prediction/PredictionEngine.php` | Uses the class above. Codex's `source_type` / `inference_transport` change is preserved. |
| `app/Services/Prediction/YieldModelGate.php`, `RiceYieldPredictionService.php`, `config/prediction.php` | `ICLIMATE_YIELD_ACCURACY_VERIFIED` (default false, strict boolean parsing). Under `withhold`, an estimate is shown only if its input is inside the training range **and** accuracy is verified. |
| `python_scripts/rice_yield_service.py` | Comment only: Rainfall_6M is a six-month mean, Seasonal_Rainfall a whole-season sum. |
| Tests | `tests/Unit/YieldModelClimateWindowTest.php` (new, 7 tests, including leakage and observed-over-forecast). Target-relative fixtures in `tests/Concerns/PreparesPredictions.php`, `AIHybridOrchestratorTest`, `AiPredictionReadOnlyTest`, `PredictionEngineClimateRecordPlausibilityTest`, `PredictionParityTest`, `YieldModelGateTest`. |
| Evidence | `docs/deployment/evidence/*` (patch, evaluation scripts, 19-record exploratory results), `docs/deployment/ICLIMATE_API_ML_VERIFICATION_2026-11-18.md`. |

## 2. Final RC2 hardening (Claude, this session)

| File | Change |
|---|---|
| `deploy/hostinger/deploy-release.sh` | Refuses an env file with a duplicated key: Laravel reads the first value, and the old check read the last. Requires `DB_CONNECTION=mysql`. Refuses `ICLIMATE_YIELD_ACCURACY_VERIFIED` other than unset/false/0. |
| `deploy/hostinger/iclimate.env.example`, `.env.example` | State `ICLIMATE_YIELD_ACCURACY_VERIFIED=false`, plus `ICLIMATE_YIELD_GATE=withhold` in `.env.example`. |
| `deploy/hostinger/VPS-RUNBOOK-RC2.md` | Env requirements list the flag, the `mysql` driver and one definition per key. |
| `tests/deployment/test_release_tooling.py` | Fixture states the driver and flag. 3 new refusal tests (duplicate key, enabled flag, `mariadb` driver). |
| `tests/Feature/PreMigrationCompatibilityTest.php` | Rolls back the RC2 submissions migration first. MariaDB refuses to drop a table that a foreign key still references; SQLite does not check. This is a test-only defect. |

## 3. RC2 improvements by Codex (carried unchanged; detail in `docs/deployment/ICLIMATE_RC2_FINAL_READINESS.md`)

- **Association reporting integrity:**
  - locked, idempotent report review (409 on stale decisions);
  - append-only submission history: migration `2026_11_18_000001`, `AssociationYieldReportSubmission`, `_submissions.blade.php`;
  - observed-yield recalculation;
  - date/irrigation preservation and visible validation;
  - private, authorised supporting-document downloads (`AssociationReportDocumentController`);
  - duplicate-period validation.
- **API and ML:**
  - JSON error contract for `/api/*` (`bootstrap/app.php`);
  - chat ML-fallback deadline (`PythonService.php`);
  - inference transport provenance;
  - backup-path range validation (`python_scripts/predict.py`, `rice_yield_service.py`, `main.py`, `analytics_tasks.py`, `evaluation_service.py`).
- **Deployment:**
  - backup checksum and permissions (`iclimate-backup.sh`);
  - restore script;
  - nginx;
  - install check;
  - Reverb production transport (`iclimate.env.example`);
  - strict bundle/tag/fingerprint installer (`deploy-release.sh`);
  - Ubuntu provisioning script;
  - RC1/RC2 runbooks;
  - deterministic packager (`scripts/deploy/prepare-release.py`);
  - migration check `CHECK_RELEASE=rc2`.
- **Dependencies:**
  - Laravel 12.73.0 and JS patches (`composer.lock`, `package.json`, `package-lock.json`);
  - hash-verified `picomatch` build mitigation (`scripts/security/*`, `docs/security/PICOMATCH_BUILD_MITIGATION.md`).
- **Seeder and health fixes:** `FarmersAssociationDirectorySeeder` canonical barangay names; `SystemHealthCommandTest`.
- **Tests:**
  - new Feature tests (API contract and inventory, association workflow/integrity/reminders, chat deadline, transport provenance, gate messaging);
  - Python boundary and parity tests;
  - Playwright critical-workflow, verification and prediction-trace specs with a tablet config;
  - deployment packaging and safety tests;
  - JS `picomatch` security test.

## Not part of the release payload

- **Excluded by the packager:**
  - `storage/models/experiments/`, `storage/datasets/external/` (research);
  - `docs/deployment/releases/` (this metadata);
  - `storage/app/` (backups and runtime);
  - the companion `ICLIMATE_RC2_FINAL_READINESS.md`.
- **Refused by the packager:**
  - secrets (`.env`, keys);
  - `vendor/`, `node_modules/`, `.git/`;
  - logs and dumps.

**Optional cleanup for a later release (already tracked in RC1, left unchanged):**
- `public/images/ai assistant.png` and `public/images/it personnel.png`;
- the duplicate files `public/images/landing/*-960 (1).jpg`, which are byte-identical to the files without " (1)";
- `public/images/landing/photo_2026-11-03 07.24.2{7,9}.jpeg`.

None of these is referenced by any view, route or config.
