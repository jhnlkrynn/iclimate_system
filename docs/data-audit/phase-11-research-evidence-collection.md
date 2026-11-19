# iClimate Phase 11 — Research Evidence Collection and ML Validation Preparation

**Project:** iClimate  
**Assessment date:** 2026-11-13  
**Scope:** Prepare a controlled evidence-delivery workflow. No production data, stored prediction, model artifact, prediction formula, or risk formula was changed.

## Outcome

Phase 11 adds two narrow capabilities missing from Phases 6–10:

1. A metadata-only evidence manifest validator for checksums, artifact roles, and approval status.
2. A feature-definition comparison helper for supplied training versus production units, transformations, windows, and spatial levels.

The existing row intake, chronological/season-grouped leakage checks, readiness statuses, and `ACCURACY NOT VERIFIED` safeguard remain authoritative. No new research evidence was found in the repository or backups.

## 1. Research evidence request checklist

| Requested evidence | Required format | Responsible provider | Acceptance criteria |
|---|---|---|---|
| Original deployed rice-yield training dataset | CSV/Parquet/database export plus data dictionary | Original model developer/researcher | Stable row IDs, target definition, complete source provenance, checksum |
| Training and preprocessing code | Git commit/archive, `.py`, or notebook with environment lockfile | Original model developer | Reproduces feature order, preprocessing, missing-value policy, and artifact type |
| Complete 12-feature definitions | CSV/JSON/table | Original model developer + adviser | Unit, formula, window, spatial unit, categorical encoding, and missing-value rule for every feature |
| Original train/test methodology | Protocol/report plus split manifest | Original developer + adviser/statistical reviewer | Independent groups, dates, baseline, metrics, and no derived-copy leakage |
| Verified MAO production records | Signed CSV/export or source document plus checksum | MAO data custodian | Observation IDs, source/correction history, area, production, yield, season, and validation status |
| Planting and harvest dates | Linked table keyed by observation ID | MAO + agriculture researcher | Dates are source-backed and define the crop-weather matching window |
| Historical climate observations | CSV/Parquet/API export plus station metadata | PAGASA/climate data custodian or approved provider | Station, coordinates, units, timezone, missingness, revisions, and source checksum |
| Independent yield observations | Non-derived, source-backed table | MAO/researcher/statistical reviewer | Independent unit and group membership approved; excluded from training or explicitly held out |
| Research approval record | Signed PDF/record or controlled decision log | Adviser, MAO, product/technical owners | Names, dates, decisions, scope, and permitted use recorded |

Deliver all files using [`phase-11-evidence-manifest.example.json`](phase-11-evidence-manifest.example.json) and preserve the original files unchanged. A manifest with `approval_status: pending` is a receipt record, not approval.

## 2. Reusable intake specifications and templates

- [`phase-11-evidence-manifest.example.json`](phase-11-evidence-manifest.example.json) defines package identity, responsible provider, artifact roles, checksums, and approval metadata.
- [`phase-11-research-snapshot-template.csv`](phase-11-research-snapshot-template.csv) defines the minimum row-level fields for future agricultural/yield snapshots.
- `validate_evidence_manifest()` checks required manifest fields, supported roles, unique artifact IDs, and SHA-256 syntax without opening or importing artifacts.
- `validate_research_dataset()` checks row provenance, dates, seasons, units, duplicate IDs, demo/derived/model rows, proxy labeling, and independent-group counts.

These templates contain no real observations and must not be loaded into production.

## 3. Original-model compatibility verification procedure

Once the original package is supplied, use this isolated sequence:

1. Hash every delivered file and validate the evidence manifest.
2. Inspect the deployed artifact read-only and confirm its checksum, estimator, preprocessing, and exact 12-feature order.
3. Parse the supplied feature dictionary into a mapping keyed by the model-facing names.
4. Compare it with the production dictionary using `compare_feature_definitions()` for unit, transformation, window, and spatial-unit mismatches.
5. Reconstruct each feature from source rows in a non-production working directory, preserving source row IDs and formulas.
6. Compare reconstructed values to the production feature payload without changing production code or invoking live prediction paths.
7. Check historical weather coverage, missingness, sentinel values, lag/rolling windows, season boundaries, and crop planting-to-harvest alignment.
8. Compare training and production distributions with explicit evidence labels; do not infer compatibility from plausible ranges alone.
9. Test supplied sensitivity fixtures only through the isolated evaluator; never use a live prediction call as evidence of accuracy.
10. Permit scientific evaluation only if provenance, definitions, independence, and the approved evaluation protocol all pass. Otherwise return `BLOCKED` or `NOT VERIFIED`.

No feature correction, model replacement, retraining, activation, or database write is part of this procedure.

## 4. Independent evaluation acceptance criteria

An evaluation snapshot is acceptable for scientific analysis only when all are true:

- source ownership, checksum, and approval are recorded;
- every row has a stable observation ID and an approved independent group ID;
- target, unit, crop, variety, planting date, harvest date, and spatial level are defined;
- climate source and crop-weather window are approved;
- demo, derived, allocated, model-output, duplicate, and replicated observations are rejected or explicitly excluded;
- dates and seasons are valid and season groups do not overlap train/test;
- chronological or season-grouped methodology is approved before metrics are calculated;
- training/test membership and source checksums are frozen;
- the model is compared with information-available mean and seasonal baselines;
- MAE, RMSE, R², bias, row count, independent-group count, and limitations are reported;
- any missing definition or insufficient evidence keeps the result `ACCURACY NOT VERIFIED`.

Positive software tests, successful inference, a checksum, or a candidate-model metric are never sufficient by themselves.

## 5. Approval and evidence tracking matrix

| Decision | Required approver | Evidence reference | Status |
|---|---|---|---|
| Farm-level versus municipal target | Adviser + original developer | Original target/data dictionary | BLOCKED |
| Feature units and transformations | Original developer + adviser | 12-feature dictionary and reproduction | BLOCKED |
| MAO source authenticity | MAO custodian + adviser | Signed source/export and checksum | BLOCKED |
| Climate source and Ambulong proxy policy | Climate/agriculture authority + adviser | Station metadata and transfer rationale | BLOCKED |
| Crop-weather matching window | MAO + agriculture researcher | Planting/harvest evidence | BLOCKED |
| Independent observation unit | Adviser/statistical reviewer | Approved protocol | BLOCKED |
| Minimum independent groups | Adviser/statistical reviewer | Power/sample rationale | BLOCKED |
| Evaluation strategy and baselines | Adviser/statistical reviewer | Split manifest and protocol | BLOCKED |
| Model correction/retraining | Technical owner + adviser | Approved change request | NOT AUTHORIZED |
| Heat Map risk-data treatment | Product owner + MAO | Provenance policy and impact review | BLOCKED |

Blank evidence references and approvals are intentionally not treated as approval.

## 6. Current readiness-gate results

The current repository run remains:

| Gate | Status |
|---|---|
| Deployed feature schema | PASS |
| Model checksum/metadata | PASS |
| Input provenance | BLOCKED |
| Climate freshness | FAIL — missing/stale current months and invalid `-999` sentinel |
| Agricultural provenance | BLOCKED |
| Leakage and independent evidence | BLOCKED |
| Research dataset intake | BLOCKED — no approved snapshot supplied |
| Evidence manifest | BLOCKED — no evidence package supplied |
| Prediction sensitivity | NOT VERIFIED |
| Accuracy claim | BLOCKED — `ACCURACY NOT VERIFIED` |

## 7. Files created or modified

Created:

- `docs/data-audit/phase-11-research-evidence-collection.md`
- `docs/data-audit/phase-11-evidence-manifest.example.json`
- `docs/data-audit/phase-11-research-snapshot-template.csv`

Modified:

- `python_scripts/ml_validation.py` — manifest validation and feature-definition comparison.
- `python_scripts/run_ml_readiness.py` — optional evidence-manifest gate.
- `python_scripts/tests/test_ml_validation.py` — manifest and definition-comparison tests.
- `python_scripts/tests/test_ml_readiness_runner.py` — manifest-gate test.

Existing Phase 3–10 implementations, safeguards, data, models, and uncommitted changes were preserved.

## 8. Testing and protection results

- Phase 11 targeted Python tests: **24 passed**.
- Full Python validation/analytics suite: **39 passed**, with the existing third-party deprecation warnings.
- Focused Laravel regression suite: **28 passed, 273 assertions**.
- Full Laravel suite from the completed Phase 10 verification: **1,784 passed, 5 skipped, 13,821 assertions**.
- `php artisan iclimate:ml-readiness` remains read-only and returns `ACCURACY NOT VERIFIED`.
- Existing unvalidated-estimate and accuracy-not-verified UI safeguards remain present.
- `git diff --check`: clean.
- Graph refreshed after code changes.

No database records, stored predictions, models, formulas, services, queues, schedulers, Reverb, or production processes were changed or restarted.

## 9. External evidence still required

The deployed model cannot undergo scientifically valid accuracy testing until the original training package, 12-feature dictionary, approved MAO records, source-backed dates, climate observations, independent yield observations, and adviser-approved evaluation protocol are delivered and pass the intake gates. Until then, the only correct scientific status remains **ACCURACY NOT VERIFIED**.
