# Prioritized Decision and Collection Checklist

## Priority 0 — required before scientific ML evaluation

- [ ] Original model developer delivers the deployed-model training dataset and preprocessing/training package.
- [ ] Record checksums, provenance, version, target, observation IDs, and permitted use.
- [ ] Supply the authoritative 12-feature definitions, units, transformations, windows, encodings, and missing-value rules.
- [ ] MAO Head approves target geography, yield unit, season calendar, and crop-weather matching window.
- [ ] MAO supplies verified production, planting, harvest, and farm observations with source documents and stable IDs.
- [ ] Supply fresh historical climate records with station/source metadata and an approved Lian/Ambulong interpretation.
- [ ] Researchers approve chronological or season-grouped independent evaluation, minimum samples/groups, leakage exclusions, and baselines.

## Priority 1 — required before thesis requirements sign-off

- [ ] MAO Head approves advisory thresholds, risk methodology, and publication wording.
- [ ] MAO Head and adviser approve each Heat Map layer’s data source, spatial aggregation, weights, freshness, minimum evidence, and no-data behavior.
- [ ] Farmer and MAO Personnel complete usability, field, language, accessibility, and estimate-label review.
- [ ] IT Expert signs security, privacy, audit, retention, backup, RPO/RTO, availability, browser, performance, and recovery requirements.
- [ ] Adviser confirms which hidden/retired/provisional features are in final thesis scope.

## Priority 2 — after decisions are recorded

- [ ] Intake validators run on supplied files without importing them into active tables.
- [ ] Compatibility report compares every training feature with production definitions.
- [ ] Evaluation excludes demo, derived, duplicate, replicated, training, and tuning observations.
- [ ] Evaluation compares overall mean and seasonal baselines and reports supported MAE, RMSE, R², bias, counts, and limitations.
- [ ] Update the Requirements Validation Matrix with evidence IDs and signed decision records.
- [ ] Keep deployment unchanged unless a separately approved model-correction decision is made.

## Action ownership summary

| Owner | Must provide or decide |
|---|---|
| Farmer | Usability, field meaning, privacy, accessibility, estimate comprehension |
| MAO Personnel | Source records, dates, units, validation/correction rules, operational workflow |
| MAO Head | Official target, seasons, thresholds, sources, Heat Map method, publication and correction authority |
| IT Expert | Security, operations, recovery, monitoring, model activation and rollback gates |
| Original model developer | Training artifact, dataset, preprocessing, feature semantics, split methodology |
| Researcher/adviser | Independent evaluation protocol, minimum evidence, acceptance criteria, scientific interpretation |
