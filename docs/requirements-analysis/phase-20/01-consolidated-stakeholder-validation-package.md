# Consolidated Stakeholder Validation Package

## Distribution instructions

Send the role-specific section to the named respondent. The respondent should answer from official practice or documented policy, attach supporting evidence, and sign/date the response. Blank or “unknown” answers remain unresolved; they must not be interpreted as approval.

## Common response header

| Field | Response |
|---|---|
| Respondent name and position | |
| Stakeholder group | Farmer / MAO Personnel / MAO Head / IT Expert |
| Barangay or organizational scope | |
| Date and document version | |
| Overall decision | Accept / Revise / Reject / Unable to decide |
| Signature or approval reference | |

## Farmer validation sheet

Please confirm or correct the following:

1. Farm name, location, area, unit, rice variety, planting date, and expected harvest fields reflect actual practice.
2. Weather, risk, advisory, calendar, and notification displays are understandable and useful.
3. The prediction page clearly identifies a model-generated estimate and does not claim verified accuracy.
4. Actual harvest records are distinguishable from estimates and are not presented as research validation by default.
5. Language, device, connectivity, accessibility, and privacy needs are adequately represented.
6. Identify any output that could be misunderstood as a guarantee or measured accuracy.

**Attach:** annotated screen review or examples of required field corrections.

## MAO Personnel validation sheet

Please provide and validate:

1. Authoritative farmer, farm, planting, harvest, and production sources.
2. Source custodian, document/export format, coverage dates, correction history, and units.
3. Official season definitions, cross-year grouping, planting/harvest rules, and crop-weather matching window.
4. Rules for duplicates, demo rows, derived observations, missing values, and conflicting records.
5. Barangay coverage and read-only municipal-view expectations.
6. Weather sources, freshness requirements, advisory rules, risk thresholds, and Heat Map operational use.
7. Which records may be used for independent model evaluation.

**Attach:** signed source register, season calendar, unit/range sheet, and approved correction policy.

## MAO Head validation sheet

Please decide or delegate decisions for:

1. Prediction target geography and yield definition/unit.
2. Authoritative MAO and climate sources, including the applicability of Ambulong observations to Lian.
3. Season and crop-weather alignment rules.
4. Advisory thresholds and risk methodology.
5. Heat Map layers, spatial aggregation, weights, minimum evidence, and no-data wording.
6. Minimum independent observations/seasons and acceptable evaluation protocol.
7. Authority for model correction, retraining, activation, and publication of results.

**Attach:** office approval memo, signed methodology sheet, or controlled decision record.

## IT Expert validation sheet

Please validate:

1. Role names, MAO Head implementation, module restrictions, policies, and private channels.
2. Security, privacy, retention, audit logging, export, rate-limit, and secret-management controls.
3. Availability target, support window, browser/device scope, accessibility, and performance targets.
4. Backup frequency, off-server retention, restore testing, RPO, and RTO.
5. Monitoring and recovery for Laravel, Python ML, queue, scheduler, Reverb, MariaDB, and external providers.
6. Model checksum, schema, readiness gates, approval chain, rollback, and activation controls.

**Attach:** IT security review, operational SLO/NFR sheet, backup/recovery acceptance, and model activation policy.

## Common decision record

```text
Requirement/decision ID:
Decision:
Accepted definition or policy:
Evidence reviewed:
Conditions and exceptions:
Responsible owner:
Effective version/date:
Signature or approval reference:
Follow-up due:
```
