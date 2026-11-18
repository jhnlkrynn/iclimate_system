# Phase 26 — Real Dataset Acquisition and ML Model Improvement

Date: 2026-11-14  
Status: `AUTHENTIC AGGREGATE EVIDENCE ACQUIRED; EXPERIMENT BLOCKED`  
Deployed model status: `ACCURACY NOT VERIFIED`

## 1. Outcome

Official PSA and PAGASA evidence was acquired into the isolated directory
`storage/datasets/external/phase-26/`. No production code, database record,
prediction, formula, model weight, or running service was changed. The evidence
is sufficient to establish a Batangas province-level production/area series and
to derive a province-level yield-per-hectare target. It is not sufficient to
train or evaluate a leakage-safe climate-to-yield model because it does not
contain crop-season weather inputs matched to the same observations.

## 2. Acquired evidence and provenance

| Artifact | Source and coverage | Unit/geography | SHA-256 | Use decision |
|---|---|---|---|---|
| `psa-batangas/palay-production-batangas-2015-2025.csv` | PSA OpenSTAT production table; selected Palay, Batangas, 2015–2025, all published periods | metric tons; province aggregate | `a16d12c0d2909ee4681d834a67df058b012dd172f65877a1c936314997a9167f` | Authentic context/target source; not farm-level evaluation |
| `psa-batangas/palay-area-harvested-batangas-2015-2025.csv` | PSA OpenSTAT area-harvested table; same selectors | hectares; province aggregate | `a2d26eeb3aef36c27c6080f5fdbcc0cd0e5e440ce67816d448196f2cc67604a4` | Authentic denominator/target source; not farm-level evaluation |
| `ambulong-climatological-normals-1991-2020.pdf` | PAGASA Ambulong station climatological normals | monthly normals; station aggregate | `93f65373eb736e4b77c2fa02c9f8247ca20ee17212a4d27b344133e42c1eb108` | Climate context only; not historical season input |

The exact PSA API selectors and retrieval metadata are in
`storage/datasets/external/phase-26/psa-query.json`; the complete manifest is
`storage/datasets/external/phase-26/manifest.json`.

PSA describes the production table as volume produced and actually harvested,
with provincial disaggregation. The matching area table defines area harvested
as the actual area from which harvests are realized. The two official annual
series therefore support a derived province-level target `metric tons / hectare`,
not a Lian farm target. PSA coverage and definitions are documented in the
[production table](https://openstat.psa.gov.ph/PXWeb/pxweb/en/DB/DB__2E__CS/0012E4EVCP0.px/)
and [area table](https://openstat.psa.gov.ph/PXWeb/pxweb/en/DB/DB__2E__CS/0022E4EAHC0.px/).

PAGASA’s Ambulong file is a 1991–2020 climatological-normal summary. PAGASA
states that raw climatological data for research requires a request, proposal,
terms of use, and adviser-signed letter; its public CliMap download also
requires a completed request form and terms acceptance. See the
[PAGASA climate-data request page](https://pagasa.dost.gov.ph/climate/climate-data),
[Ambulong normals](https://pubfiles.pagasa.dost.gov.ph/pagasaweb/files/cad/CLIMATOLOGICAL%20NORMALS%20%281991-2020%29/AMBULONG.pdf),
and [CliMap](https://climgridph.pagasa.dost.gov.ph/climapv2/).

## 3. Derived PSA target check

Using the annual cells from the two PSA extracts, the derived province-level
yield values are:

| Year | Production (t) | Area harvested (ha) | Derived yield (t/ha) |
|---:|---:|---:|---:|
| 2015 | 40,009.00 | 13,085.00 | 3.0576 |
| 2016 | 39,797.00 | 13,761.00 | 2.8920 |
| 2017 | 42,246.00 | 13,196.00 | 3.2014 |
| 2018 | 47,309.79 | 13,427.00 | 3.5235 |
| 2019 | 44,982.62 | 14,252.56 | 3.1561 |
| 2020 | 33,001.50 | 12,781.15 | 2.5820 |
| 2021 | 35,354.04 | 12,714.35 | 2.7806 |
| 2022 | 34,871.94 | 11,952.29 | 2.9176 |
| 2023 | 38,026.81 | 11,582.38 | 3.2832 |
| 2024 | 22,028.89 | 7,796.81 | 2.8254 |
| 2025 | 26,686.50 | 8,377.00 | 3.1857 |

All 11 annual production and area cells used for this check were present and
finite. This is a target derivation check, not a model-evaluation result.
No observations were imported into Laravel or presented as farm outcomes.

## 4. Compatibility with the deployed 12-feature schema

The deployed schema remains:

`RAINFALL`, `TEMP_AVG`, `TEMP_RANGE`, `Area`, `Previous_Rainfall`,
`Previous_Temp`, `Rainfall_6M`, `Temp_3M`, `Temp_6M`, `Seasonal_Rainfall`,
`Seasonal_Temp`, `Season`.

The PSA extracts provide only an aggregate `Area`-like target denominator and
production/area periods. They do not provide verified feature definitions for
`Area`, `Rainfall_6M`, `TEMP_RANGE`, lag windows, seasonal windows, or the
deployed target scope. PAGASA normals provide no observation-year or planting/
harvest-specific climate values and cannot safely populate those features.

| Required input | Evidence in Phase 26 | Compatibility |
|---|---|---|
| Current rainfall and temperature windows | No matched historical series | Not compatible |
| `TEMP_RANGE` | Ambulong normals show max/min context but not matched crop seasons | Not verified |
| `Area` | PSA province harvested area only | Scope mismatch; not farm-compatible |
| Previous climate features | No historical matched windows | Not compatible |
| Seasonal climate features | No planting/harvest dates or season mapping | Not compatible |
| `Season` | PSA period labels exist, but model season encoding is unverified | Not verified |
| Actual target | Province-level derived t/ha is calculable | Target scope mismatch |

## 5. Experimental optimization decision

The Phase 25 evidence-gated optimizer was intentionally not trained. Its
required approved package includes all 12 production-compatible inputs, target,
unique observation and group identifiers, dates, leakage-safe split labels,
provenance, approval references, and a metadata sidecar defining units and
transformations. The acquired PSA/PAGASA artifacts do not satisfy those gates.

The optimizer was run without an approved dataset and returned the expected
read-only blocked result:

```json
{
  "status": "BLOCKED",
  "accuracy_status": "ACCURACY NOT VERIFIED",
  "reason": "No approved dataset and metadata sidecar supplied.",
  "production_model_unchanged": true,
  "artifacts_written": false
}
```

No Random Forest, Gradient Boosting, Extra Trees, HistGradientBoosting, or
baseline metric is reported. Any such result from a join against climatological
normals would be a fabricated or leakage-prone evaluation, not an improvement
claim.

## 6. Exact evidence still required

1. Researcher/model-developer package: original 12-feature dictionary, units,
   transformations, target definition, season encoding, preprocessing, and
   original split protocol.
2. MAO-approved farm or at least municipal records: anonymized observation ID,
   municipality/barangay or approved spatial unit, farm area, planting date,
   harvest date, ecosystem/irrigation, observed production, harvested area,
   yield unit, and source/approval reference.
3. PAGASA-approved historical observations or an approved CliMap export matched
   to the same location and crop season, including rainfall and temperature
   windows needed to reconstruct all experimental features.
4. Explicit declaration that evaluation rows are independent of the deployed
   training data, with no duplicate, replicated, seeded, demo, or prediction-
   derived rows.
5. Researcher/adviser approval of chronological or season-grouped holdout,
   minimum sample size, baseline, metrics, and farm-versus-municipal target
   interpretation.

## 7. Recommendation

The shortest scientifically defensible route is to request a single approved
MAO/PAGASA evidence package with season-linked rows and the model developer’s
feature dictionary. If the original 12-feature definitions remain unavailable,
use the PSA/PAGASA sources only as documented aggregate context and build a
separate experimental model with a new, explicitly approved feature schema and
target. Do not replace or modify the deployed model. The deployed model remains
`ACCURACY NOT VERIFIED`, and no replacement is ready for deployment.
