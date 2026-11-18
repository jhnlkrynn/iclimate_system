# iClimate Phase 9 — Automated ML Validation and Research Readiness

**Project:** iClimate  
**Assessment date:** 2026-11-13  
**Scope:** Read-only automated gates built on the Phase 8 validators.

## 1. Implemented validation workflow

Created `python_scripts/run_ml_readiness.py`, plus the Laravel `iclimate:ml-readiness` wrapper. Both emit a machine-readable JSON report to stdout and perform no writes.

The runner reuses `python_scripts/ml_validation.py` and checks:

- deployed model feature names and order;
- model artifact availability, type, size, and SHA-256 checksum;
- input provenance declarations;
- historical climate freshness against a supplied target date;
- agricultural-source row availability and provenance classification;
- independent-observation and leakage readiness;
- prediction sensitivity evidence availability;
- whether an accuracy claim is supportable.
- approved-evidence checks for chronological and season-grouped evaluation splits;
- MAE, RMSE, R², bias, and simple-baseline comparisons when independent pairs are explicitly supplied.

The runner never calls Laravel prediction services, the Python prediction endpoint, retraining scripts, scheduled jobs, queue dispatch, model switching, or database writes. It reads only the supplied model/CSV paths and emits JSON.

Example:

```text
.venv-ml/bin/python python_scripts/run_ml_readiness.py --target-date 2026-11-13
php artisan iclimate:ml-readiness --target-date=2026-11-13
```

The command is intentionally evidence-conservative: absent approved evidence produces `BLOCKED` or `NOT VERIFIED`; it cannot manufacture an `ACCURACY VERIFIED` result.

## 2. New files

- `python_scripts/run_ml_readiness.py` — read-only machine-readable readiness runner.
- `app/Console/Commands/MlReadiness.php` — Laravel wrapper that invokes the isolated runner without database access or queue dispatch.
- `python_scripts/tests/test_ml_readiness_runner.py` — runner status and non-mutation tests.
- `docs/data-audit/phase-9-ml-automated-validation.md` — this report.

The Phase 8 files were extended, not duplicated:

- `python_scripts/ml_validation.py` now includes readiness-status constants, input-provenance checks, sensitivity diagnostics, and non-mutating chronological/season-grouped split validation.
- `python_scripts/tests/test_ml_validation.py` now covers those additions.

No Laravel prediction calculation, model artifact, active record, historical analytic, or Heat Map formula was changed; the only Laravel addition is the read-only CLI wrapper.

## 3. Model readiness-gate results

The runner was executed against the repository artifact and source files with target date `2026-11-13`.

| Gate | Status | Evidence/result |
|---|---|---|
| Deployed feature schema | **PASS** | Artifact feature names/order match the 12-feature repository contract |
| Model checksum/metadata | **PASS** | Artifact metadata and SHA-256 available read-only |
| Input provenance | **BLOCKED** | Production wiring is not approved training provenance |
| Climate freshness | **FAIL** | Required history is missing/stale for the requested current target |
| Agricultural provenance | **BLOCKED** | MAO-shaped rows are not approved independent observations |
| Leakage and independent evidence | **BLOCKED** | No approved independent evaluation snapshot supplied |
| Prediction sensitivity | **NOT VERIFIED** | Runner never executes prediction code; no approved perturbation experiment supplied |
| Accuracy claim | **BLOCKED** | **ACCURACY NOT VERIFIED** |

The `PASS` results are software/artifact checks only. They do not imply predictive accuracy.

## 4. Feature compatibility findings

The exact deployed feature contract remains:

`RAINFALL`, `TEMP_AVG`, `TEMP_RANGE`, `Area`, `Previous_Rainfall`, `Previous_Temp`, `Rainfall_6M`, `Temp_3M`, `Temp_6M`, `Seasonal_Rainfall`, `Seasonal_Temp`, `Season`.

Automated checks now detect missing, reordered, duplicated, non-numeric, non-finite, unsupported-season, unit-mismatched, and explicitly out-of-approved-range inputs.

The runner does not assert unapproved training ranges. The known research findings remain:

- `Area` has a likely farm-versus-municipal scale mismatch.
- `Rainfall_6M` is produced as a sum while the inferred training range suggests a possible average.
- `TEMP_RANGE` is calculated from monthly average temperatures while the inferred training meaning may be max-minus-min.
- Previous and rolling climate inputs require freshness and source approval.
- The deployed path preserves May–September wet encoding, while the general application calendar uses May–October wet; the original training calendar remains unconfirmed.

These are not silently corrected by Phase 9.

## 5. Data provenance and leakage results

The automated infrastructure classifies caller-supplied rows as:

- `verified_observation`;
- `unverified_source`;
- `derived_allocation`;
- `demo_seed`;
- `model_output`.

It detects duplicate observation/source IDs and train/test overlap at both observation and group/season levels. It reports missing or stale climate months without filling them.

The repository evidence remains insufficient for scientific evaluation:

- 60 MAO-shaped monthly rows represent 11 inferred municipal seasons;
- 110 records are derived allocations;
- 14 rice-production rows are demo records;
- farmer harvest records are demo-generated;
- no approved independent Lian climate series is available;
- Ambulong is a Tanauan station proxy and requires explicit approval.

No active record was reclassified by the runner.

## 6. Baseline evaluation availability

The Phase 9 runner does not calculate model metrics unless independent actual/predicted observations are supplied to the Phase 8 evaluation functions. The isolated evaluator supports chronological and season-grouped evidence, duplicate/group leakage checks, and candidate-vs-baseline metrics. With the current repository evidence, it reports:

> **ACCURACY NOT VERIFIED**

Existing candidate-model holdout metrics remain candidate-only evidence and do not validate the deployed 12-feature model. No retraining, candidate activation, or model replacement was performed.

## 7. Regression test results

### Python ML tests

```text
.venv-ml/bin/python -m pytest python_scripts/tests -q
```

- **32 passed** (all Python validation/analytics tests)
- **0 failed**
- **2 third-party deprecation warnings**

The runner and Laravel wrapper were exercised directly and produced valid JSON with the expected gate statuses. Climate freshness correctly normalizes the repository's `YEAR,MONTH` CSV columns; it reports missing/stale months rather than `NO_HISTORY`.

### Targeted Laravel regression tests

- **26 passed**
- **0 failed**
- **269 assertions**

Coverage included model-registry readiness safeguards and the Phase 8 IT/readiness surface. The previously recorded 51-test prediction/Heat Map baseline was not rerun in this continuation.

### Complete Laravel suite

- Existing Phase 8 baseline recorded **1,784 passed**, **0 failed**, **5 skipped**, and **13,821 assertions**. The full suite was not rerun during this Phase 9 continuation because the workspace contains extensive pre-existing uncommitted changes.

`git diff --check` passed.

## 8. Production and service integrity

- No active database writes were performed.
- No model artifact was retrained, replaced, switched, or activated.
- No prediction formula or model input transformation was changed.
- No Heat Map risk formula was changed.
- Existing prediction accuracy warnings were preserved.
- Scheduler, Reverb, queue, Python ML, web, and MariaDB services were not restarted or reconfigured.
- The code graph could not be refreshed because the expected `graphify-out/` and `GRAPH_REPORT.md` inputs are absent from this checkout.

Pre-existing unrelated working-tree modifications were preserved.

## 9. Remaining research blockers

1. Original deployed training dataset and preprocessing script are missing.
2. Exact units, windows, transformations, and spatial scale for all 12 features are not confirmed.
3. Farm-level versus municipal-level model scope is unresolved.
4. MAO source authenticity and monthly semantics are unresolved.
5. Independent observation unit, crop duration, and climate window require approval.
6. Ambulong's use as a labelled Lian proxy requires approval.
7. Minimum independent sample size and grouped/chronological evaluation protocol require adviser approval.
8. Farm-specific output suppression versus continued labelled display requires product/research approval.
9. Demo/derived-record treatment in operational Heat Map risk calculations requires MAO/product approval.

## 10. Recommended Phase 10 actions requiring approval

### Safe preparation

- Add a documented intake format for approved research snapshots, including checksums, observation IDs, provenance classes, units, and season groups.
- Add a report serializer for stored research diagnostics only after an approved non-production destination is chosen.
- Add PHP/Python parity fixtures for serialized feature payloads without changing production input construction.
- Add a read-only sensitivity harness that accepts approved baseline/perturbed fixtures but never calls live prediction paths.

### Approval-dependent work

- Approve the target, independent unit, crop/weather matching window, season calendar, climate source, and minimum sample size.
- Obtain the original training package and reproduce the deployed recipe.
- Decide whether current farm-specific numeric estimates should be suppressed.
- Decide whether demo/derived records remain in operational Heat Map calculations.
- Approve any corrected schema, retraining, candidate promotion threshold, shadow comparison, or activation.

### Not authorized

- Changing production feature calculations or prediction formulas.
- Importing or fabricating agricultural observations.
- Retraining, replacing, switching, or activating models.
- Rewriting predictions or historical analytics.

## Final readiness assessment

Phase 9 establishes an automated, reproducible technical gate workflow. Software-level schema and artifact checks pass, but data provenance, current climate freshness, independent evaluation, sensitivity evidence, and scientific accuracy remain blocked or unverified. iClimate is technically operational but must continue to present rice-yield predictions as unvalidated estimates and must not claim scientifically verified accuracy.
