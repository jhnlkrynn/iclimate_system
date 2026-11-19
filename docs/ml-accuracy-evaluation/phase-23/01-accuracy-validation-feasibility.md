# Phase 23 — Accuracy Validation Feasibility and Authentic Data Acquisition

## Executive decision

The deployed model is technically inspectable but not currently scientifically evaluable. The shortest valid route is:

1. Obtain the original model package and feature dictionary from the original model developer.
2. Obtain an anonymized, source-certified MAO evaluation export with actual yield targets and matching planting/harvest and climate records.
3. Obtain researcher/adviser approval for target scope, season alignment, and a chronological or season-grouped split.
4. Run the unchanged model in isolation and publish metrics only if all gates pass.

Until then, **ACCURACY NOT VERIFIED** remains mandatory.

## 1. Recoverable deployed pipeline

Artifact: `storage/models/rice_yield_model_final.pkl`  
Algorithm: scikit-learn `RandomForestRegressor` in a `Pipeline`  
Checksum: `f3a54e12ecf85e66340475e2c49ce5c15d9c58efd82213435015a275734b777c`

Recovered preprocessing:

- `Season` is one-hot encoded using `OneHotEncoder(handle_unknown='ignore')`.
- The other eleven inputs pass through as numeric columns.
- The estimator uses 300 trees, max depth 10, `max_features='sqrt'`, `min_samples_split=10`, and random state 42.

Recovered input order:

```text
RAINFALL, TEMP_AVG, TEMP_RANGE, Area, Previous_Rainfall, Previous_Temp,
Rainfall_6M, Temp_3M, Temp_6M, Seasonal_Rainfall, Seasonal_Temp, Season
```

The artifact does not recover the original target column, target unit, source geography, training rows, split, or feature semantics.

## 2. Exact evidence blocking evaluation

| Missing evidence | Why it blocks accuracy |
|---|---|
| Original training dataset | Cannot identify training distribution, row identity, target, or leakage overlap |
| Original preprocessing/training code | Cannot prove feature transformations or fitted preprocessing match production |
| Feature dictionary | Names alone do not establish units, windows, spatial scale, or formulas |
| Target definition | A farm yield rate, municipal average, and total production are different targets |
| Original split and metrics | Cannot distinguish training, historical test, and independent performance |
| Verified independent yields | No actual target exists against which deployed predictions can be scored |
| Matching climate/planting/harvest records | Lag, rolling, seasonal, and crop-weather features cannot be reconstructed safely |
| Research approval | No evaluation result can be treated as scientific evidence without an approved protocol |

## 3. Required evaluation fields and compatibility

The anonymized MAO template contains one row per approved observation or approved observation-group record. The minimum required fields are:

| Field | Required unit/format | Compatibility rule |
|---|---|---|
| `observation_id` | Stable pseudonymous ID | Must not contain farmer name or government ID |
| `farm_id` or `group_id` | Pseudonymous/group identifier | Used only for grouping and leakage prevention |
| `barangay_code` | Approved code, not free text | Must be within approved Lian coverage |
| `observation_date` | ISO date | Must be the approved target/harvest reference date |
| `planting_date`, `harvest_date` | ISO date | Required for season and climate-window alignment |
| `season` | Approved category | Must match the approved season calendar |
| `crop` | Controlled value | Must identify rice and approved crop scope |
| `area_ha` | hectares | Definition must match the model’s original `Area` meaning |
| `actual_yield_t_ha` | tonnes/hectare | Must be measured/derived from approved production and area records |
| `actual_production_t` | tonnes | Source field for yield calculation where available |
| `rainfall_mm`, `temp_avg_c`, `temp_range_c` | mm, °C, °C | Must match original target-period definitions |
| `previous_rainfall_mm`, `previous_temp_c` | mm, °C | Must be the approved prior-period values |
| `rainfall_6m_mm`, `temp_3m_c`, `temp_6m_c` | mm, °C, °C | Exact window and inclusion rule must be documented |
| `seasonal_rainfall_mm`, `seasonal_temp_c` | mm, °C | Must use approved season boundaries |
| `source_record_ref` | Custodian reference | Must point to an auditable source without exposing identity |
| `provenance_status` | `verified` only for evaluation | `demo`, `derived`, `allocation`, `proxy`, and `unknown` are rejected or descriptive-only |
| `approval_ref` | Decision/document ID | Required before independent evaluation |

The original developer may instead provide raw source fields and permit reconstruction. In that case, the raw-to-feature mapping must be supplied and approved before prediction.

## 4. Credible source routes

These are proposed acquisition routes, not accepted evidence until provenance and approval are recorded:

1. **Lian Municipal Agriculture Office / MAO:** primary source for anonymized farm-level or barangay-level planting, harvest, area, production, irrigation, variety, and season records. Request the custodian’s signed export and correction history.
2. **Philippine Statistics Authority (PSA) Palay Production Survey/OpenSTAT:** official palay production and area-harvested statistics suitable for contextual or aggregate baselines. PSA describes the data as survey-based production/harvest statistics and provides time-series tables through OpenSTAT. It may not provide the farm-level Lian observations required for this model.
3. **DOST-PAGASA climatological data request:** request station observations or approved historical climate data with dates, units, missingness, and source metadata. PAGASA offers researcher climatological raw-data request routes.
4. **PAGASA CliMap historical baselines:** possible gridded climate source for approved municipal context, subject to resolution, date coverage, and applicability review. It must not silently replace station observations or be treated as Lian farm data.
5. **DA/PhilRice or an approved agricultural research partner:** possible source of authenticated experimental or production records if the study target and geography match. Acceptance requires written permission, source documentation, and target compatibility.

External sources should be documented with access date and approval. Potential availability does not prove that a source contains the required Lian-level observations.

## 5. Feasibility decision

### Existing model evaluation

**Not feasible yet.** The model can be executed once a compatible feature row exists, but execution alone is not accuracy validation. Without the original target and feature semantics, a newly collected dataset could produce numerical scores that answer a different question from the one the model was trained to answer.

### Conditional feasibility after evidence acquisition

Evaluation becomes feasible if either:

- the original training package defines all twelve features and target, and independent MAO observations can reconstruct the same definitions; or
- researchers explicitly approve a documented interpretation of each missing definition and accept that the result evaluates the deployed artifact under that interpretation, with limitations.

If neither condition is met, the deployed model should remain a historical/operational artifact and a separate evidence-based model should be developed rather than assigning it a misleading accuracy percentage.
