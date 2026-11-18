# Phase 22 — ML Training Data Recovery and Accuracy Verification

**Project:** iClimate  
**Review date:** 13 November 2026  
**Scope:** Read-only recovery audit of the deployed rice-yield model and possible accuracy evidence.

## Executive conclusion

The original deployed Random Forest artifact is present, loadable, and historically stable, but the repository and Git history do not contain the original training dataset, preprocessing package, original split, or a verified independent evaluation set. The exact 12 feature names/order and serialized preprocessing structure are recoverable. Their authoritative research definitions, units, spatial scale, target semantics, and original methodology are not.

No scientifically valid deployed-model metric could be calculated. The final status remains:

> **ACCURACY NOT VERIFIED**

The existing candidate metrics are not substituted for deployed-model accuracy.

## 1. Recovery search performed

The following locations and evidence classes were inspected:

- current `storage/models`, `storage/datasets`, `database/data`, seed data, private storage, and Python scripts;
- Phase 2–13 ML audit reports and manifests/templates;
- model metadata and serialized pipeline structure;
- Git history, including the initial repository commits, model-related commits, and historical path listings;
- tracked backups/archive-shaped files and database export locations;
- training, candidate, backtest, model-management, and evaluation scripts.

### Git-history result

`storage/models/rice_yield_model_final.pkl` exists in the initial repository history (`d50eb9ea`, 29 June 2026) and has the same SHA-256 in the inspected historical commits through the current working tree:

```text
f3a54e12ecf85e66340475e2c49ce5c15d9c58efd82213435015a275734b777c
```

The history contains the artifact but no corresponding original training CSV, notebook, preprocessing package, or test-split file. Later commits add the separate candidate dataset/model and evaluation scripts; they do not add the deployed model’s missing research package.

The historical `database/data/iclimate_farmer_masterlist.csv` is a farmer masterlist containing identities, barangays, associations, and area. It is not a yield-training dataset and contains no compatible climate-feature/target pairs.

## 2. Deployed model identity and recoverable schema

| Property | Recovered value |
|---|---|
| Artifact | `storage/models/rice_yield_model_final.pkl` |
| SHA-256 | `f3a54e12ecf85e66340475e2c49ce5c15d9c58efd82213435015a275734b777c` |
| Serialized object | scikit-learn `Pipeline` |
| Estimator | `RandomForestRegressor` |
| Estimator parameters | `n_estimators=300`, `max_depth=10`, `max_features='sqrt'`, `min_samples_split=10`, `min_samples_leaf=1`, `random_state=42` |
| Preprocessing | `ColumnTransformer`; `Season` one-hot encoded; 11 numeric inputs passed through |
| Target | Not authenticated from the artifact; application context calls it rice yield |

The exact serialized input order is:

```text
RAINFALL
TEMP_AVG
TEMP_RANGE
Area
Previous_Rainfall
Previous_Temp
Rainfall_6M
Temp_3M
Temp_6M
Seasonal_Rainfall
Seasonal_Temp
Season
```

This confirms model identity and interface, not training meaning or accuracy.

## 3. Feature-definition recovery matrix

| Feature | What source code currently computes/describes | Authenticated original training definition? | Missing evidence |
|---|---|---|---|
| `RAINFALL` | Target-period rainfall; catalog describes mm | No | Source station, target window, aggregation, unit confirmation |
| `TEMP_AVG` | Target-period average temperature; catalog describes °C | No | Original temperature source/window/unit |
| `TEMP_RANGE` | Production spread from recent temperature values; catalog describes max–min concept | No | Original source columns and exact formula |
| `Area` | Farmer/farm area in hectares in production | No | Training observation scale and area meaning |
| `Previous_Rainfall` | Prior climate-history value | No | Exact lag, source, freshness, missing rule |
| `Previous_Temp` | Prior temperature value | No | Exact lag, source, freshness, missing rule |
| `Rainfall_6M` | Production six-month aggregate, currently a sum | No | Sum versus mean, inclusive dates, source and alignment |
| `Temp_3M` | Production trailing three-month average | No | Window boundaries, missing rule, target-month inclusion |
| `Temp_6M` | Production trailing six-month average | No | Window boundaries, missing rule, target-month inclusion |
| `Seasonal_Rainfall` | Aggregate within production season | No | Original season calendar, aggregation, partial-season rule |
| `Seasonal_Temp` | Average within production season | No | Original season calendar, aggregation, partial-season rule |
| `Season` | `Wet`/`Dry` string supplied by production trained-season helper | No | Original month-to-category mapping and encoding source |

The model’s column names are known. No claim that the production definitions match the original training definitions is justified.

## 4. Training methodology and recorded metrics

### Deployed model

No authenticated training methodology or original evaluation metrics were recovered. The pickle contains a fitted pipeline and estimator configuration, but not the source-row identities, training target table, split protocol, or provenance needed to reconstruct those facts.

The following statements therefore remain unavailable:

- training row count and date range;
- training/test or validation split;
- whether the split was chronological, grouped by season, random, or leaked;
- training, validation, and test metrics;
- original baseline definition;
- MAPE handling for zero or near-zero targets;
- geographic and seasonal coverage.

### Candidate model history — not deployed evidence

The repository does contain an independently documented 10-feature candidate pipeline and a 60-row time-aware candidate dataset. Its read-only reproduction remains:

| Candidate result | MAE | RMSE | R² | Interpretation |
|---|---:|---:|---:|---|
| 2025 holdout, original candidate rule | 0.3944 | 0.4690 | -1.1043 | Candidate only; worse than the holdout mean relationship |
| 2025 holdout, October-as-wet candidate variant | 0.3847 | 0.4584 | -1.0105 | Candidate only; not the deployed schema |
| Leave-one-year-out, October-as-wet candidate | 0.5137 | 0.7155 | -0.1745 | Candidate only; not independent deployed evidence |

These results must not be reported as the accuracy of `rice_yield_model_final.pkl`.

## 5. Authentic independent-observation assessment

No admissible evaluation set was found. The available files fail one or more required conditions:

| Source | Failure for deployed evaluation |
|---|---|
| Candidate time-aware CSV | Ten features, not twelve; provenance/independence not approved |
| Candidate backtest/prediction CSVs | Derived candidate outputs; some rows are training or prediction-only |
| MAO planting summary/seeder data | Seeded or source-unverified; not an approved independent observation set |
| Ambulong monthly climate CSV | Climate-only; no matched verified yield target; Lian applicability unapproved |
| Production database/backups | Operational/derived/demo records; not an independent frozen test set |
| Farmer masterlist history | Identity/area data only; no climate and yield target pairs |

Consequently, no actual-versus-predicted deployed comparison, error distribution, baseline comparison, R², MAE, RMSE, or MAPE is scientifically reportable.

## 6. Leakage, overfitting, season, and geography

### Deployed model

- **Leakage:** cannot be tested without original rows and split metadata.
- **Overfitting:** cannot be quantified without independent test observations; a fitted forest’s successful load is not evidence of generalization.
- **Seasonality:** production uses a trained-season helper, but the original training calendar is missing.
- **Geography:** production `Area` is farm-oriented; original training scale is unknown and may not be farm-level.
- **Climate history:** lag and rolling features depend on complete, current history; current records may be stale or unavailable.
- **Target mismatch:** the application describes rice yield, but the original target field/unit and observation granularity are not authenticated.

### Candidate model

The negative candidate holdout R² is evidence about the candidate recipe only. It cannot establish or disprove deployed-model accuracy, and it cannot resolve the deployed model’s feature or geography mismatches.

## 7. Exact recovery package required

Request the following from the original model developer/researcher and MAO data custodian:

1. The exact deployed training export, including stable row IDs, all 12 raw/engineered inputs, target, units, geography, dates, season, and source references.
2. Training and preprocessing source code/notebook, dependency versions, random seeds, and serialized preprocessing configuration.
3. A feature dictionary defining every input’s formula, unit, spatial scale, target period, lag/rolling boundaries, category encoding, missing-value behavior, and outlier policy.
4. The original target definition, including whether it is t/ha, total production, municipal average, barangay aggregate, or another unit.
5. The exact train/test/validation rows and split method, including any grouping by season, year, farm, barangay, or municipality.
6. Original training/test metrics, baselines, and evaluation scripts with timestamps and checksums.
7. Verified independent MAO yield observations with planting/harvest dates and matching climate records, excluded from training, tuning, and feature construction.
8. Source approval from the MAO custodian and researcher/adviser approval for independent use.

## 8. Safe evaluation procedure after recovery

1. Store the package in an isolated, non-production location and record SHA-256 checksums.
2. Validate provenance, dates, units, target scope, duplicates, demo/derived flags, and climate coverage.
3. Reconstruct all 12 features from the authenticated definitions and compare them row-by-row with production calculations without changing production code.
4. Freeze an approved chronological or season-grouped independent split.
5. Run the unchanged deployed artifact only on the approved test rows.
6. Compare against overall-mean and information-available seasonal baselines.
7. Report MAE, RMSE, R², bias, MAPE only when suitable, sample/group counts, actual-vs-predicted rows, residual distribution, geographic/seasonal subgroup results, and limitations.
8. Keep the production model and checksum unchanged until a separate research-approved correction decision.

## 9. Verification performed in Phase 22

- Deployed pickle loaded successfully in `.venv-ml`.
- Deployed artifact checksum verified and unchanged.
- Git history checked from initial commit through model-related commits.
- Candidate season report reproduced using `retrain_yield_season.py --check`; no files were written by that command.
- No production predictions, database rows, services, formulas, or models were modified.
- No deployed-model metrics or charts were generated because there are no scientifically admissible actual/predicted pairs.
- `ACCURACY NOT VERIFIED` preserved.

## Final assessment

Phase 22 recovered the artifact’s historical identity and exact serialized schema, but not the evidence required to measure its accuracy. The model has no authenticated original test history in the repository. The next step is external recovery of the signed training/evaluation package and independent MAO observations, followed by the isolated evaluation procedure above. Until that package passes the evidence gates, no accuracy percentage or model reliability claim is supportable.
