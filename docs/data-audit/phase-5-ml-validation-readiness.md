# iClimate Phase 5 — ML Accuracy Validation and Prediction Readiness

**Project:** iClimate  
**Assessment date:** 2026-11-13  
**Scope:** deployed rice-yield model, weather model, available agricultural and climate evidence, and the safeguards applied to prediction presentation.

## Executive conclusion

iClimate is technically capable of running its prediction pipeline, but the deployed rice-yield model is not scientifically validated. The original training dataset, original preprocessing script, and authoritative definitions for the deployed model's 12 features are not present in the repository. Production currently supplies several inputs whose units or time windows do not match the ranges inferred from the model artifact. Accordingly, the correct research status is:

> **ACCURACY NOT VERIFIED — do not present the deployed rice-yield output as a scientifically validated farm-specific estimate.**

The display safeguards from Phase 4 remain in place. No active database records, stored predictions, historical analytics, model artifacts, prediction formulas, or services were changed for this assessment.

## 1. Evidence recovered

### Deployed artifact

`storage/models/rice_yield_model_final.pkl` is a scikit-learn Pipeline with a `preprocessor` and Random Forest regressor. Read-only inspection found:

- 300 trees, `max_depth=10`, `min_samples_leaf=1`, `random_state=42`.
- 12 input names: `RAINFALL`, `TEMP_AVG`, `TEMP_RANGE`, `Area`, `Previous_Rainfall`, `Previous_Temp`, `Rainfall_6M`, `Temp_3M`, `Temp_6M`, `Seasonal_Rainfall`, `Seasonal_Temp`, `Season`.
- `Season` is one-hot encoded with categories `Dry` and `Wet`.
- The artifact contains preprocessing and feature names, but not the original source dataset, data dictionary, observation identity, sampling rule, or training/evaluation split.
- SHA-256 at inspection: `f3a54e12ecf85e66340475e2c49ce5c15d9c58efd82213435015a275734b777c`.

### Available related artifacts

| Artifact | What it proves | Limitation |
|---|---|---|
| `python_scripts/train_rice_yield_model.py` | A separate 7-feature smoke-test/target pipeline exists | It does not reproduce the deployed 12-feature model |
| `python_scripts/compare_yield_features.py` | Candidate baseline/extended comparison methodology | Uses the target 7-feature schema, not the deployed schema |
| `python_scripts/retrain_yield_season.py` | Candidate 10-feature time-aware model and season sensitivity check | Explicitly states the deployed model cannot be rebuilt from this repository |
| `storage/datasets/iClimate_Modeling_Ready_TimeAware.csv` | Candidate modelling dataset | Candidate data is not proof of deployed-model training provenance |
| `storage/models/rice_yield_model_candidate*.pkl` and metadata | Candidate artifacts and holdout reports | Not active production models |
| `database/seeders/data/mao-planting-summary-2020-2025.csv` | 60 monthly municipal summary rows | Original MAO source document is absent; validation status is unknown |
| `database/data/Ambulong Monthly Data.csv` | 131 monthly station rows, Jan 2015–Dec 2025 except missing Jan 2020 | Ambulong is in Tanauan, not Lian; it is not automatically a Lian farm observation |

### Missing artifacts to obtain

1. The original deployed-model training CSV or database export, including row identifiers and source provenance.
2. The original training script/notebook, feature engineering code, scaling/encoding configuration, and train/test split.
3. A data dictionary defining every feature's unit, aggregation window, spatial level, and missing-value handling.
4. The original target definition: yield per hectare, harvest period, crop variety, irrigation status, and whether rows are farm, barangay, municipal, or season observations.
5. The original evaluation report, baseline, and independent test set.
6. Confirmation from the researchers/MAO that the 60-row planting summary is authentic and what its `month` column means.

## 2. Deployed feature compatibility matrix

The status below distinguishes artifact-confirmed facts from inferences. No production calculation was changed.

| Model feature | Unit/definition required by training | Current production input | Evidence/status | Decision needed |
|---|---|---|---|---|
| `RAINFALL` | Target-period rainfall; exact aggregation unknown | Weather forecast/period rainfall in mm | Name and unit are confirmed; aggregation and source equivalence are unverified | Confirm monthly/seasonal window and source |
| `TEMP_AVG` | Target-period average temperature | Period average in °C | Unit is plausible; exact window and source are unverified | Confirm target window |
| `TEMP_RANGE` | Temperature spread; exact period unknown | Spread of recent monthly average temperatures | **Mismatch likely**: inferred training range resembles daily/monthly max–min, not a spread of six monthly averages | Recover training definition; do not substitute silently |
| `Area` | Training scale unknown | Individual farm area in hectares | **Mismatch likely**: inferred training range is approximately 12–258 ha while production farms are approximately 0.1–30 ha | Confirm whether model is municipal or farm-level |
| `Previous_Rainfall` | Rainfall immediately before target period | Latest available climate row or fallback | **Not current for 2026 targets** when source ends Dec 2025; repeated/stale values observed | Acquire current historical observations and define lag |
| `Previous_Temp` | Temperature immediately before target period | Latest available climate row or fallback | Same recency problem; repeated/stale values observed | Acquire current observations and define lag |
| `Rainfall_6M` | Six-month rainfall aggregate | Six-month **sum** in production | **Mismatch likely**: inferred training range 54–311 mm, while production values are about 1,983 mm; six-month average is a plausible inference, not a fact | Confirm sum versus average and exact six months |
| `Temp_3M` | Three-month temperature aggregate | Recent three-month average | Definition is not confirmed; source can be stale | Confirm mean/median/window and source |
| `Temp_6M` | Six-month temperature aggregate | Recent six-month average | Definition is not confirmed; source can be stale | Confirm window and source |
| `Seasonal_Rainfall` | Seasonal rainfall aggregate | Season-relative aggregate from available history | Definition and season boundaries are unverified | Confirm season calendar and aggregate |
| `Seasonal_Temp` | Seasonal temperature aggregate | Season-relative aggregate from available history | Definition and season boundaries are unverified | Confirm season calendar and aggregate |
| `Season` | Training category `Dry`/`Wet` | `Dry`/`Wet` derived by application calendar | Categories confirmed; calendar is not. Code and pages have different seasonal conventions in places | Adviser/MAO must approve calendar |

### Compatibility decision

The model cannot currently be called a valid farm-level model. Recovering definitions may establish that some inputs are compatible, but it cannot establish accuracy. If the original training scale is municipal, the safe interpretation is municipal/seasonal output, not an individual farm estimate.

## 3. Dataset provenance and availability

### Agricultural observations

- The MAO CSV contains 60 monthly rows representing 11 municipal seasons: six wet seasons and five dry seasons.
- The repository database contains 124 `rice_productions` rows: 110 derived allocations from those season totals and 14 demo rows.
- The 110 allocations are not independent barangay observations; values were distributed from municipal totals using seeded farm proportions.
- No rice record is marked as MAO-validated.
- The repository contains 5,794 harvested farmer records, but these are demo-generated and cannot be treated as independent observations.
- The original MAO document/export is not available, so even the 60 monthly rows remain unverified.

### Climate observations

- Ambulong monthly data covers Jan 2015–Dec 2025, with Jan 2020 missing.
- Ambulong is a PAGASA station in Tanauan, Batangas, not a Lian farm or barangay observation.
- `climate_records` does not provide a verified Lian historical series for this evaluation.
- Applying Ambulong data to Lian can be a documented proxy only if the researchers approve the spatial limitation and the climate-matching window.

### Data leakage and duplication risks

The following must be excluded or handled explicitly before any accuracy claim:

- Demo farmer harvests and `[demo_seed]` production rows.
- Derived barangay allocations from municipal totals.
- Duplicate rows representing the same municipal season.
- Any feature values computed from the target observation or future weather.
- Random row splits that place months from the same season in both train and test sets.
- Any training/test split that uses derived copies of an observation in the test set.

The independent unit should normally be a complete municipal season. If MAO confirms that monthly values are independently recorded, months may be retained for modelling, but all evaluation must remain grouped by season or chronological block.

## 4. Reproducible dataset preparation workflow

1. Obtain the original MAO source document or signed export and record its checksum, date, custodian, and correction history.
2. Obtain authoritative harvest dates, planted area, production, yield, variety, irrigation, barangay/farm identity, and observation status.
3. Assign a stable observation ID. Keep source rows separate from derived allocations and demo rows.
4. Confirm whether each row is a planting month, harvest month, season total, or monthly measurement.
5. Define crop matching: planting-to-harvest duration, weather exposure window, and treatment of typhoon/loss notes. This requires MAO/researcher approval.
6. Acquire climate data with source, station coordinates, units, timezone, missingness, and revision metadata. Keep Ambulong proxy values visibly distinct from Lian observations.
7. Recompute features only from information available before or during the prediction target window. Store the exact formula and source row IDs for every feature.
8. Run automated checks for units, ranges, duplicate observation IDs, duplicate season membership, future leakage, missingness, and farm/municipality scale.
9. Freeze a versioned modelling table and checksum it. Do not overwrite source records.
10. Use season-grouped or forward-chaining evaluation and publish the baseline alongside model metrics.

## 5. Independent validation methodology

### Required evaluation design

- Primary split: leave-one-season-out or forward-chaining by complete season.
- Secondary split: chronological year holdout when enough years exist.
- No random row split for the 60 monthly rows unless season groups are respected.
- Test rows must be independently observed and must not be derived copies of training rows.
- Report sample size at both row and independent-season levels.

### Required metrics

For each model and baseline, report:

- MAE in t/ha.
- RMSE in t/ha.
- R², with a warning that negative R² means worse than the test-set mean baseline.
- Mean signed error/bias in t/ha.
- Prediction interval or error distribution only if a validated uncertainty method exists.
- Training seasons, test seasons, climate source, feature version, and data checksum.

The minimum comparison is the model against a training-season mean baseline. Seasonal-average and last-observation baselines should be added when their information would genuinely be available at prediction time.

### Current evidence

The deployed rice-yield model has no valid independent evaluation in the repository: **ACCURACY NOT VERIFIED**.

The available candidate 2025 holdout is informative but not a validation of the deployed model:

| Model/evaluation | MAE | RMSE | R² | Interpretation |
|---|---:|---:|---:|---|
| Candidate yield model, 2025 holdout | 0.394 | 0.469 | -1.1043 | Worse than a useful benchmark; candidate only |
| Mean baseline, same holdout | 0.301 | 0.402 | -0.5490 | Better than candidate |

Weather-model independent 2024/2025 checks also showed the Random Forest underperforming seasonal baselines for rainfall, temperature, humidity, and categorical weather classification. It may be operationally functional, but its scientific predictive accuracy is not established.

## 6. Model suitability and alternatives

### Alternative A — Recover and audit the deployed recipe

Proceed only if the original researchers provide the dataset, data dictionary, preprocessing, and evaluation split. Reproduce the artifact byte-for-byte or explain every difference, then run season-grouped independent evaluation. This is the only route that can establish what the deployed model was intended to mean.

### Alternative B — Documented municipal/seasonal baseline

Use verified municipal season totals and a transparent seasonal mean or historical-average forecast. This is scientifically interpretable and should remain the minimum benchmark. It is not a replacement recommendation until the observation count and season definitions are approved.

### Alternative C — Properly retrained model

After data provenance and feature definitions are approved, train a new model with a documented schema, leakage checks, season-grouped evaluation, baseline comparison, and an explicit deployment review. The existing candidate model must not be activated merely because it runs or has a better in-sample score.

### Weather model

Keep the weather Random Forest operational only as an explicitly labelled forecast component. Compare it continuously with seasonal historical baselines and do not use a model advantage claim unless an independent, time-separated evaluation demonstrates it.

## 7. Prediction presentation and risk review

The following surfaces now carry the unvalidated status: Farmer prediction pages and dashboard harvest figures; MAO prediction and production figures; reports; calendar; farmer records; feedback/survey context; AI chat yield cards; and Heat Map yield labels/source text.

Remaining risks:

- Numerical yield output is still visible, although labelled. Suppression or replacement with “not available until validated” requires adviser/product approval.
- AI and decision-support confidence scores are not calibrated measures of rice-yield accuracy. They should not be interpreted as model validation percentages; a separate review is required before exposing them beside yield values.
- Heat Map risk calculations remain operational and were not changed. Demo/derived production records can influence risk inputs and require an approved data-provenance policy before exclusion or recalculation.
- Historical analytics and stored predictions were preserved intentionally.

## 8. Researcher/adviser approval checklist

| Decision | Required approval |
|---|---|
| Is the MAO CSV authentic and what does `month` mean? | MAO + adviser |
| What is the independent observation unit: farm, barangay, month, or season? | Researcher + adviser |
| What crop duration and weather window link planting to yield? | Researcher + MAO |
| Is Ambulong an acceptable labelled proxy for Lian? | Adviser + climate/agriculture authority |
| What are the exact definitions and units of all 12 deployed features? | Original researchers |
| Is the deployed model municipal or farm-level? | Original researchers + adviser |
| What minimum number of independent seasons is acceptable? | Adviser/statistical reviewer |
| Which grouped/chronological validation protocol is authoritative? | Adviser/statistical reviewer |
| Should current farm-specific values be suppressed or only labelled? | Product owner + adviser |
| Should demo and derived rows be excluded from Heat Map calculations? | Product owner + MAO |
| What evidence is required before retraining, replacing, or activating a model? | Adviser + technical owner |

## 9. Safe implementation plan

### Safe now, without approval

- Preserve and test the current unvalidated labels.
- Add read-only provenance reports and checksums for future source deliveries.
- Add isolated validators for feature names, units, ranges, freshness, season membership, duplicates, and leakage.
- Keep candidate and deployed models clearly separated in filenames, metadata, and evaluation reports.
- Ensure every future metric report states independent sample count, grouping method, baseline, and climate source.

### Requires approval or new authoritative evidence

- Changing any deployed feature calculation or season calendar.
- Importing or treating Ambulong as Lian climate evidence.
- Reclassifying the MAO rows as verified observations.
- Suppressing or replacing live numerical yield outputs.
- Excluding demo/derived records from operational Heat Map calculations.
- Retraining, replacing, or activating any model.

## Readiness assessment

| Area | Status |
|---|---|
| Laravel/Python technical execution | Ready; regression suites green |
| Prediction display safety | Improved; unvalidated outputs are labelled |
| Deployed feature compatibility | Not established; material mismatches remain |
| Agricultural dataset provenance | Insufficient for validation |
| Independent rice-yield accuracy | **ACCURACY NOT VERIFIED** |
| Weather-model scientific accuracy | Operational, but not demonstrated superior to baseline |
| Research deployment readiness | Not ready for validated farm-specific claims |

The next scientifically meaningful step is not another model run. It is recovery and approval of the original data definitions, independent observation unit, and climate-matching protocol, followed by a frozen, provenance-controlled dataset and season-grouped validation.
