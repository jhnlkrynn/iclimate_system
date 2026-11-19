# iClimate Phase 12 — ML Evidence Acquisition and Validation Readiness

**Project:** iClimate  
**Assessment date:** 2026-11-13  
**Scope:** Evidence inventory and execution preparation only. Production predictions, trained models, formulas, database records, and running services were not changed.

## Implementation summary

Phase 12 reviewed the Phase 10–11 reports, manifests, templates, validators, and readiness runner. The existing row-level intake, manifest validation, feature-definition comparison, chronological/season-grouped leakage checks, baseline metrics, and conservative readiness statuses were retained.

One non-redundant improvement was added: the readiness report now contains a machine-readable evidence inventory that distinguishes:

- the present deployed artifact;
- candidate-only datasets and metadata;
- present but unapproved climate proxy/agricultural source-shaped files;
- missing original training and independent-evaluation evidence.

Presence is never treated as approval, and the inventory does not open or import research artifacts.

## Evidence inventory

| Role | Current status | Interpretation |
|---|---|---|
| Deployed model artifact | `present_deployed_artifact` | Read-only checksum and schema inspection are possible; training provenance is absent. |
| Candidate time-aware dataset | `present_candidate_only` | 60 repository candidate rows; not approved evidence for the deployed model. |
| Candidate metadata/retraining report | `present_candidate_only` | Documents a separate 10-feature candidate recipe. |
| Ambulong climate file | `present_proxy_unapproved` | Tanauan station proxy; includes a `-999` missing-value sentinel row and is not Lian-specific. |
| MAO-shaped agricultural CSV | `present_unverified` | Source-shaped rows without approved authenticity or independent-observation status. |
| Original deployed training dataset | `missing_required` | Required from the original developer/researcher. |
| Original training/preprocessing code | `missing_required` | Required to reproduce the 12-feature recipe. |
| Original feature dictionary | `missing_required` | Required for units, transformations, windows, and spatial scope. |
| Original evaluation protocol | `missing_required` | Required for valid train/test methodology and baselines. |
| Independent yield observations | `missing_required` | Required before any deployed accuracy claim. |

The candidate dataset contains 60 rows, 52 training rows, and 8 test rows, with no duplicate year/month keys or blank fields. Those software checks do not prove source authenticity, independence, or deployed-model relevance.

## Missing and incomplete evidence

The following must still be obtained and approved:

1. Original deployed-model training dataset with stable observation IDs.
2. Original training and preprocessing code, environment, and model-construction version.
3. Complete definitions for all 12 features, including units, aggregation, transformations, windows, spatial level, categories, and missing-value rules.
4. Original target definition and observation unit: farm, barangay, municipal, monthly, or season.
5. Original train/test or cross-validation split and baseline methodology.
6. Verified MAO production records with source custody, correction history, planting dates, harvest dates, area, production, yield, crop/variety, and validation status.
7. Historical climate observations with station metadata, units, missingness, revision history, and approved Lian/proxy policy.
8. Independent yield observations excluded from training or explicitly held out by an approved group split.
9. Adviser/statistical approval for sample size, season grouping, chronological cutoffs, baselines, and acceptance thresholds.

No available backup contains the missing original training package. Operational prediction rows in database dumps are not independent evaluation evidence.

## Training/production feature compatibility

The deployed artifact and production parity tests confirm the exact feature order:

`RAINFALL`, `TEMP_AVG`, `TEMP_RANGE`, `Area`, `Previous_Rainfall`, `Previous_Temp`, `Rainfall_6M`, `Temp_3M`, `Temp_6M`, `Seasonal_Rainfall`, `Seasonal_Temp`, `Season`.

Current compatibility status remains:

| Area | Result |
|---|---|
| Names/order | **PASS** — artifact and production mapping agree |
| Units | **NOT VERIFIED** — production declarations are not original training evidence |
| `TEMP_RANGE` transformation | **NOT VERIFIED** — monthly-average spread may differ from max/min definition |
| `Area` spatial scale | **NOT VERIFIED** — farm versus municipal training scope is unknown |
| `Rainfall_6M` aggregation | **NOT VERIFIED** — production sum versus possible training mean remains unresolved |
| Rolling/seasonal windows | **NOT VERIFIED** — original boundaries and missing-data rules are absent |
| Season calendar | **NOT VERIFIED** — categories are known, original month rule is not |
| Candidate schema equivalence | **FAIL/INCOMPATIBLE** — available candidate uses a different 10-feature schema |

Once authentic definitions arrive, `compare_feature_definitions()` and the Phase 11 reconstruction procedure provide the safe comparison path. No live feature calculation is changed by this phase.

## Executable evaluation workflow

The workflow is intentionally executable but blocked by evidence:

1. Run `php artisan iclimate:ml-readiness` or `python_scripts/run_ml_readiness.py`.
2. Supply a metadata-only evidence manifest with `--manifest`.
3. Supply a researcher snapshot with `--evaluation` and an approved `--evaluation-strategy` of `chronological` or `season_grouped`.
4. The runner validates checksums/declarations, row provenance, dates, seasons, units, duplicates, groups, and leakage.
5. Only externally approved independent actual/predicted pairs may be passed to the isolated metric functions.
6. Compare MAE, RMSE, R², and bias with information-available mean and seasonal baselines.
7. Any missing approval, source, definition, independent group, or valid split returns `BLOCKED`, `FAIL`, or `NOT VERIFIED`; it cannot produce `ACCURACY VERIFIED`.

The workflow never dispatches predictions, retrains, activates a model, writes a database record, or changes production formulas.

## Current readiness status

Run against the current repository evidence with target date `2026-11-13`:

| Gate | Status |
|---|---|
| Deployed feature schema | **PASS** |
| Model checksum/metadata | **PASS** |
| Input provenance | **BLOCKED** |
| Climate freshness | **FAIL** — stale/missing current history and invalid `-999` sentinel |
| Agricultural provenance | **BLOCKED** |
| Leakage and independent evidence | **BLOCKED** |
| Research dataset intake | **BLOCKED** |
| Evidence manifest | **BLOCKED** — no supplied package manifest |
| Prediction sensitivity | **NOT VERIFIED** |
| Accuracy claim | **BLOCKED** — `ACCURACY NOT VERIFIED` |

The status remains **ACCURACY NOT VERIFIED**. Candidate holdout metrics and successful model inference do not change it.

## Acceptance criteria before scientific evaluation

All of the following must pass before the deployed model is evaluated scientifically:

- every artifact has a provider, role, checksum, format, and approval reference;
- the deployed artifact checksum is pinned and the original training package is identified;
- all 12 feature definitions match or every mismatch is explicitly approved as a new model/schema decision;
- MAO observations are source-backed and non-derived, with stable IDs and approved target units;
- planting/harvest dates define an approved climate window;
- climate source, station/proxy policy, units, missingness, and season boundaries are approved;
- no duplicate, demo, derived, replicated, future-leaking, or overlapping-group test rows remain;
- independent group count meets adviser/statistical approval;
- chronological or season-grouped splits are frozen and reproducible;
- model metrics are compared against mean and seasonal baselines with limitations;
- UI and operational risk-data treatment are separately approved.

## Files changed

- `python_scripts/run_ml_readiness.py` — evidence inventory in the read-only report.
- `python_scripts/tests/test_ml_readiness_runner.py` — inventory coverage.
- `docs/data-audit/phase-12-ml-evidence-readiness.md` — this report.

Phase 10–11 validators, manifests, templates, model files, prediction formulas, database records, and uncommitted changes were preserved.

## Test results and protection checks

- Phase 12 targeted Python tests: **25 passed**.
- Full Python suite: **40 passed** with existing third-party deprecation warnings.
- Focused Laravel suite: **28 passed, 273 assertions**.
- Full Laravel regression suite: **1,784 passed, 5 skipped, 13,821 assertions in 478.54s**.
- `php artisan iclimate:ml-readiness` executed successfully and returned `ACCURACY NOT VERIFIED`.
- `git diff --check`: clean.
- Graph refreshed after code changes.

No production records, stored predictions, trained models, prediction/risk formulas, services, queues, schedulers, or Reverb processes were modified or restarted.

## Remaining blockers and recommended next phase

The remaining blockers are external evidence and approval requirements, not missing application plumbing. The next actionable phase is **Phase 13: Authenticated Research Package Intake**, beginning only when the original developer/MAO supplies the manifest, checksums, original 12-feature training package, verified records, and approved evaluation protocol. Phase 13 should validate and freeze the package in an isolated location before any model evaluation is attempted.
