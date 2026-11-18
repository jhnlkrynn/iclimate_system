# Phase 31 — Lian-Specific Rice Yield Model Readiness

Date: 2026-11-14  
Status: `BLOCKED BEFORE TRAINING`  
Deployed model: `ACCURACY NOT VERIFIED`

## Decision

No new authenticated Lian MAO agricultural dataset or approved PAGASA
historical climate observation package was found in the repository. Training,
hyperparameter optimization, and accuracy evaluation were therefore not run.
No Phase 31 model or metric was created.

This is an evidence blocker, not a software failure.

## Evidence inventory

| Available artifact | Classification | Why it cannot support Lian training |
|---|---|---|
| PSA Batangas quarterly production/area, Phases 27–30 | Authentic province aggregate | Not Lian farm/municipal observations; no matched climate features |
| `database/seeders/data/mao-planting-summary-2020-2025.csv` | Source-shaped, previously unverified | Original MAO source, custody, approval, and independence are absent |
| `database/data/Ambulong Monthly Data.csv` | Ambulong/Tanauan station proxy | Not Lian; season matching and proxy approval are absent |
| `storage/datasets/generated/*` | Generated, candidate, or prediction artifacts | Not admissible actual yield evidence |
| `storage/datasets/candidate_2026_full_predictions.csv` | Open-Meteo prediction-only weather | No observed yield target and not approved PAGASA evidence |
| Demo/derived rice records | Seeded or allocated records | Not independent observations |

The provincial benchmarks remain preserved in the Phase 27–30 artifacts and
were not reused as Lian evidence.

## Exact evidence required before training

1. A signed or otherwise custodied MAO export for Lian with stable row IDs and
   source document/checksum.
2. One row per approved independent farm-season or municipal-season unit,
   including municipality/barangay, planting date, harvest date, area harvested,
   production, derived yield unit, rice ecosystem/irrigation, variety if used,
   and correction/revision status.
3. PAGASA-approved historical climate observations or an explicitly approved
   CliMap/station proxy matched to each crop season, including rainfall,
   temperature, station/grid coordinates, units, missing-value codes, and data
   release dates.
4. Research approval of the prediction scope, forecast origin, crop-weather
   window, season definitions, publication-delay handling, and minimum sample
   size.
5. A declared independent evaluation partition that excludes demo, derived,
   replicated, training, and prediction-generated rows.
6. If the deployed model is to be compared, the original 12-feature definitions,
   transformations, preprocessing, target, and training-scope documentation.

## Planned evaluation once evidence arrives

The existing provincial benchmarks will remain reference-only. A future Lian
experiment should construct a separate approved dataset, compare Random Forest,
Gradient Boosting, Extra Trees, regularized regression, and seasonal/agricultural
baselines, and use chronological or season-grouped nested validation. Metrics
should include MAE, RMSE, R² only where sample size supports it, MAPE when the
target is nonzero, bias, per-season errors, provenance, and limitations.

No candidate should be recommended for production unless it consistently beats
the approved simple baseline on independent Lian observations. The deployed
model must remain unchanged until separately validated evidence supports a
research-approved correction.

## Protection checks

- No records were imported or modified.
- No model was trained, replaced, or activated.
- No prediction formula or production model checksum changed.
- No accuracy claim was made.

The scientific status remains **ACCURACY NOT VERIFIED**.
