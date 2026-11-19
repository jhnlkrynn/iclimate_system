# Source Request and Retraining Decision

## One-page request to the original model developer

Please provide, for the exact deployed artifact `rice_yield_model_final.pkl`:

1. Training dataset/export with stable row identifiers.
2. Training and preprocessing source code/notebook.
3. Complete 12-feature data dictionary with units, formulas, time windows, geography, season encoding, and missing-value handling.
4. Target column definition and unit.
5. Train/test/validation split and row/group membership.
6. Training, test, and baseline metrics with the evaluation script.
7. Python/scikit-learn/joblib versions and random seed.
8. Artifact version, creation date, checksum, and known limitations.

The package should be delivered with a signed provenance statement and may be placed only in an isolated evaluation directory. It must not overwrite the production model.

## One-page request to the MAO data custodian

Please provide an anonymized, signed export containing approved rice observations for Lian with:

- pseudonymous observation/farm/group IDs;
- barangay code and observation geography;
- planting and harvest dates;
- approved season label and crop/variety;
- area in hectares, actual production in tonnes, and actual yield in tonnes/hectare;
- source document/reference and correction history;
- matching historical climate records or the approved source/window needed to reconstruct them;
- provenance, approval reference, and exclusion flags for demo/derived/allocation rows.

Do not include farmer names, RSBSA numbers, phone numbers, addresses, or other direct identifiers in the evaluation export.

## Decision: evaluate deployed model or retrain separately?

| Evidence result | Decision |
|---|---|
| Original definitions and independent compatible rows recovered | Evaluate unchanged deployed model; report independent metrics |
| Independent rows recovered but original definitions remain unresolved | Do not claim accuracy; obtain approval for a clearly labelled compatibility/sensitivity study or stop |
| Original model cannot be reconstructed and target scope is incompatible | Retain deployed model unchanged for historical comparison; propose separate approved retraining |
| Verified agricultural dataset supports a new target/schema | Train a separately versioned candidate in isolation; compare against mean/seasonal baselines; require approval before any activation |

## Evidence-based retraining strategy if required

1. Define the target (farm-level or approved aggregate) and unit with MAO/researcher approval.
2. Build a source-backed dataset from verified MAO production/harvest records and approved climate records.
3. Freeze feature definitions, units, season calendar, and missing-value policy before training.
4. Split chronologically or by season/farm group; hold out future independent groups.
5. Compare simple mean and seasonal baselines against candidate algorithms.
6. Report MAE, RMSE, R², bias, MAPE where appropriate, sample/group counts, uncertainty, and limitations.
7. Register the candidate with dataset/model checksums and researcher approval.
8. Keep the current deployed artifact unchanged until a separate correction, replacement, rollback, and activation decision is approved.

## Official source leads to verify

- [PAGASA climate-data requests](https://pagasa.dost.gov.ph/climate/climate-data) — researcher/raw climatological data request route.
- [PAGASA CliMap](https://climgridph.pagasa.dost.gov.ph/climapv2/) — historical gridded climate baseline; applicability and resolution must be approved.
- [PSA palay production statistics](https://psa.gov.ph/content/statistical-tables-palay-volume-production-and-area-harvested-ecosystem-region-and-4) — official palay production/area statistics and OpenSTAT link; likely aggregate context rather than farm-level evaluation data.
- [PSA OpenSTAT API documentation](https://openstat.psa.gov.ph/API-Documentation) — possible programmatic retrieval for approved aggregate baselines.

These links identify potential official routes only. They do not establish that the resulting data are available for Lian, compatible with the deployed model, or approved for this study.
