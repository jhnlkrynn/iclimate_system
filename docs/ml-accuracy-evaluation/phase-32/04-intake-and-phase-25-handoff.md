# Phase 32 — Intake and Phase 25 Handoff Procedure

This procedure activates no model and writes no production data. It is the
controlled path for a future authentic delivery.

## 1. Quarantine and receipt

Place delivered files only in an isolated, access-controlled intake directory,
for example `storage/datasets/external/phase-32/incoming/`. Preserve original
filenames and bytes. Record provider, receipt time, source references, and
SHA-256 hashes in a copied manifest. Never overwrite the existing Phase 23–31
artifacts or active database records.

## 2. Evidence validation order

1. Validate manifest syntax, checksum, file format, custody, permission, and
   approval status.
2. Validate schema, stable IDs, units, dates, seasons, geography, crop, area,
   production, yield target, climate metadata, and missingness.
3. Classify every row as direct, aggregate, estimated, derived, corrected, demo,
   prediction-only, or rejected.
4. Reject duplicates, non-finite/sentinel values, impossible dates, invalid
   denominators, unsupported proxies, and incompatible units with an explicit
   reason code.
5. Match climate observations only to approved planting/harvest windows and
   only using values available at the declared forecast origin.
6. Freeze independent farm/season or approved municipal groups before any model
   fitting. Keep training, validation, and evaluation membership in a manifest.
7. Verify minimum independent groups and obtain adviser/statistical approval.

## 3. Phase 25 compatibility gate

The current `python_scripts/experimental_ml_optimization.py` is an evidence-
gated experimental tool. Its approved deployed-schema path expects the lower-
case production inputs plus `actual_yield_t_ha`, `observation_id`, `group_id`,
`record_date`, `split`, `provenance_status`, and `approval_ref`. Its metadata
sidecar must declare `approval_status: approved`, the exact ordered 12-feature
schema, target, checksum, and complete unit/transformation/window/spatial-unit
definitions.

The ordered production input names are:

`rainfall`, `temp_avg`, `temp_range`, `area`, `previous_rainfall`,
`previous_temp`, `rainfall_6m`, `temp_3m`, `temp_6m`, `seasonal_rainfall`,
`seasonal_temp`, `season`.

These names describe the interface only; they do not establish the unresolved
training definitions, units, windows, or transformations. Those definitions
must come from the original model package or an explicitly approved separate
experimental schema.

The MAO template contains names that must be mapped explicitly to that schema;
it is not automatically compatible. In particular, `actual_production_t`,
`area_ha`, and climate columns require a documented target/feature decision, and
the unresolved deployed definitions for `Area`, `Rainfall_6M`, `TEMP_RANGE`,
season encoding, and stale climate windows must not be guessed.

If the supplied Lian data has a new, approved feature dictionary, it may enter a
separately versioned experimental pipeline. It must not be labelled as an
evaluation of the deployed 12-feature model unless every original definition
and transformation is recovered and matched.

## 4. Evaluation handoff

After all gates pass, run the existing Phase 25 validator/evaluator in an
isolated environment, with chronological or season-grouped validation and
baseline models fitted only on the training partition. Record MAE, RMSE, R²
only when sample size supports it, applicable MAPE, bias, row/group counts,
provenance, limitations, and artifact checksums. Store experimental models only
under the phase-specific experiment directory. Do not activate or copy them
into production.

If any gate fails, return `BLOCKED` or `NOT VERIFIED`, retain
`ACCURACY NOT VERIFIED`, and issue the rejection reasons to the provider/adviser.

## 5. Acceptance record

The future package should end with a signed decision containing:

- target scope: farm, barangay, municipality, or province;
- accepted row types and independent unit;
- feature dictionary, season calendar, climate window, publication-delay rule;
- train/validation/evaluation split and minimum independent groups;
- approver names, dates, scope, and evidence references;
- decision: accepted for isolated evaluation, returned for correction, or rejected.

Until that record exists, no training or accuracy claim is permitted. The
deployed model, formulas, checksum, services, and production predictions remain
unchanged.
