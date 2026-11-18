# Phase 32 — Data Collection and Submission Checklist

Use this checklist for the MAO agricultural file and the PAGASA climate file.
An unchecked item keeps the package in `PENDING` or `BLOCKED`; it must not be
treated as approval.

## A. Provider and approval package

- [ ] Provider, custodian, contact, and responsible office identified.
- [ ] Research purpose, adviser, institution, and approval/ethics reference attached.
- [ ] Written permission covers academic analysis and the stated geographic scope.
- [ ] Anonymization/privacy statement confirms no direct identifiers are present.
- [ ] Original source reference, extraction date, file format, revision history,
      and SHA-256 checksum recorded.
- [ ] Provider states which rows are direct observations, aggregates, estimates,
      derived values, corrections, or model outputs.
- [ ] [Evidence manifest](../../data-audit/phase-11-evidence-manifest.example.json)
      is completed with `approval_status: pending` until signed approval exists.

## B. MAO agricultural fields

Use the existing [MAO CSV template](../phase-23/mao-yield-evaluation-template.csv).
Its fields map as follows:

| Field | Required meaning and unit | Intake rule |
|---|---|---|
| `observation_id` | Stable pseudonymous row ID | Unique; never reused |
| `farm_group_id` | Approved independent farm/season group | Required for leakage checks; do not use names |
| `barangay_code` | Lian geographic identifier | Must state geographic level |
| `observation_date` | Source observation/record date | ISO `YYYY-MM-DD` plus source meaning |
| `planting_date`, `harvest_date` | Actual crop dates | ISO dates; must define season window |
| `season` | MAO-defined crop season | Do not assume `Wet`/`Dry` without mapping |
| `crop` | Crop and, if available, variety | Rice/palay definition required |
| `area_ha` | Area with documented meaning | Hectares; distinguish planted vs harvested |
| `actual_production_t` | Observed production | Metric tonnes; definition required |
| `actual_yield_t_ha` | Official target or approved derivation | Tonnes/hectare; derived flag required |
| `source_record_ref` | Custodian source reference | Must resolve to the source package |
| `provenance_status` | `verified` or `approved` after review | Pending is not admissible |
| `approval_ref` | Approval/custody reference | Required per accepted row |
| `notes` | Corrections, exclusions, definitions | Must not conceal substitutions |

The climate columns in the same template (`rainfall_mm`, `temp_avg_c`,
`temp_range_c`, `previous_rainfall_mm`, `previous_temp_c`, `rainfall_6m_mm`,
`temp_3m_c`, `temp_6m_c`, `seasonal_rainfall_mm`, `seasonal_temp_c`) must not be
filled from assumptions. Each requires an approved source, time window,
spatial unit, release/availability date, unit, and transformation record.

## C. PAGASA climate fields

- [ ] Station/grid ID, coordinates, elevation, and Lian relevance documented.
- [ ] Observation timestamps, timezone, aggregation, units, and missing codes provided.
- [ ] Rainfall and temperature series are observed or explicitly label any derived component.
- [ ] Quality flags, corrections, revisions, and publication/availability dates provided.
- [ ] Planting-to-harvest matching window is approved against MAO dates.
- [ ] Any Ambulong or other non-Lian source is labelled as a proxy and separately approved.

## D. Pre-submission rejection checks

Reject or quarantine a package if it contains:

- duplicate IDs, duplicate farm-season observations, or replicated rows;
- non-finite values, `-999` sentinels, impossible dates, negative area/production,
  zero/invalid harvested area, or incompatible units;
- demo, seeded, prediction-only, synthetic, allocated, or unexplained derived rows;
- missing source references, checksum mismatch, unapproved provenance, or missing approval;
- overlapping train/test season or farm groups, future climate values unavailable
  at the forecast origin, or unresolvable crop-weather windows;
- provincial aggregates presented as Lian farm/municipal observations.

## E. Preferred coverage

Provide all available complete Lian seasons and state gaps. The adviser or
statistical reviewer must approve the minimum number of independent seasons and
the target scope; a preferred request of multiple complete years is not itself
an acceptance rule. Preserve missing periods rather than fabricate them.
