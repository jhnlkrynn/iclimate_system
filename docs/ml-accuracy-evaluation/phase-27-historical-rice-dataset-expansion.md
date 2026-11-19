# Phase 27 — Historical Rice Dataset Expansion and Experimental Accuracy Improvement

Date: 2026-11-14  
Deployed model: `ACCURACY NOT VERIFIED`  
Experimental status: `PROVINCIAL CALENDAR-ONLY RESULTS AVAILABLE; NOT DEPLOYMENT EVIDENCE`

## 1. Dataset acquisition

The official PSA OpenSTAT API was queried for `Palay`, `....Batangas`, years
1987–2025, and Quarter 1–Quarter 4. Production and harvested-area extracts
were requested with identical selectors. The original files and exact query
are preserved under `storage/datasets/external/phase-27/`; the checksum and
provenance manifest is `manifest.json`.

PSA identifies the production table as metric tons and provides provincial
disaggregation. Its area table reports hectares and defines area harvested as
the area from which harvests were realized. See the [PSA production table](https://openstat.psa.gov.ph/PXWeb/pxweb/en/DB/DB__2E__CS/0012E4EVCP0.px/)
and [PSA area table](https://openstat.psa.gov.ph/PXWeb/pxweb/en/DB/DB__2E__CS/0022E4EAHC0.px/).

## 2. Validation results

- 156 usable paired quarterly observations: 39 years × 4 quarters.
- 0 missing production/area pairs.
- 0 non-finite values.
- 0 invalid negative production or nonpositive area values.
- 0 duplicate period labels.
- Ecosystem and geography match in both files: `Palay`, `Batangas`.
- Target calculation: `production_mt / area_harvested_ha`.
- Target scope: province-level Batangas yield, not Lian farm-level yield.
- No harvested-area value was used as a predictor.

These are authentic official aggregates, not fabricated, seeded, demo, or
prediction-derived records. They were not imported into the active database.

## 3. PAGASA climate investigation

No historical season-matched PAGASA observation file was acquired. PAGASA’s
CliMap documentation describes historical daily/sub-daily gridded rainfall and
temperature data, but states that downloads require registration, terms, and a
one-time email link. PAGASA’s raw climatological data route requires a formal
research request and supporting documents. See [CliMap documentation](https://origin.pagasa.dost.gov.ph/climate/climate-change/dynamic-downscaling/climap-v2)
and [PAGASA climate-data requests](https://pagasa.dost.gov.ph/climate/climate-data).

The Phase 26 Ambulong normals were not reused as observations. Therefore no
climate predictor was joined to the PSA rows.

## 4. Experimental dataset and leakage-safe protocol

Because the deployed 12-feature definitions remain unverified, the experiment
is a separate provincial calendar-only model. Predictors are only `year` and
`quarter`, both known before a quarterly forecast. The target is the derived
official province-level yield in metric tons per hectare.

Chronological partitions:

| Partition | Years | Rows | Use |
|---|---:|---:|---|
| Training | 1987–2016 | 120 | Hyperparameter search only |
| Validation | 2017–2020 | 16 | Candidate selection |
| Untouched test | 2021–2025 | 20 | Final independent temporal score |

`RandomizedSearchCV` used `TimeSeriesSplit` on training rows only. The selected
candidate was refit on training plus validation rows before one test-set score.
No future test values, predictions, or area inputs were used during tuning.

The reusable implementation is
`python_scripts/phase_27_provincial_experiment.py`. It is separate from
`rice_yield_service.py` and does not modify or load the deployed model.

## 5. Results

Metrics are province-level calendar-only experimental results. They must not be
described as the deployed model’s accuracy or as Lian farm accuracy.

| Algorithm | Validation RMSE | Test R² | Test MAE | Test RMSE | Test MAPE | Test bias |
|---|---:|---:|---:|---:|---:|---:|
| Random Forest | 0.7499 | **0.4731** | **0.2546** | **0.2933** | **8.33%** | -0.0797 |
| Gradient Boosting | 0.7506 | 0.3695 | 0.2663 | 0.3208 | 8.63% | -0.0829 |
| Extra Trees | 0.9544 | 0.0120 | 0.3148 | 0.4016 | 9.88% | -0.1476 |
| HistGradientBoosting | **0.5161** | -0.1290 | 0.3509 | 0.4293 | 11.49% | -0.0385 |
| Historical mean baseline | 0.5252 | -0.3121 | 0.4148 | 0.4629 | 13.24% | -0.2257 |
| Seasonal mean baseline | 0.6450 | -1.1778 | 0.4241 | 0.5963 | 12.89% | -0.2257 |

Candidate selection was based on validation RMSE, so the protocol-selected
candidate is HistGradientBoosting despite Random Forest having the best test
score in this single temporal test period. This distinction prevents test-set
selection bias. The result is exploratory evidence that calendar/time trends
may explain some province-level variation; it is not evidence that the
deployed climate model is accurate.

## 6. Artifacts

Stored under `storage/models/experiments/phase-27/`:

- `provincial_calendar_experiment_dataset.csv`
- `best_candidate_test_predictions.csv`
- `metrics.json`
- `actual-vs-predicted-test.svg`
- `best_calendar_only_candidate.joblib`

The best candidate is the validation-selected HistGradientBoosting pipeline.
The test-set Random Forest result is retained in `metrics.json` for transparent
comparison and was not promoted.

## 7. Limitations and deployment decision

1. The target is a Batangas provincial aggregate and cannot establish Lian
   farm-level or municipal predictive performance.
2. No historical climate observations were available, so the experiment does
   not test rainfall, temperature, lag, or seasonal climate effects.
3. PSA quarterly production and area are aggregate survey outputs; they do not
   authenticate planting/harvest dates, irrigation at farm level, or the
   deployed model’s `Area` definition.
4. The 20-row test period is one province and five years; it is not sufficient
   for a deployment claim.
5. The deployed model’s 12-feature schema, preprocessing, training set, and
   target definition remain unverified.

No replacement model is ready for Lian deployment. The production model and
its checksum remain unchanged, and the scientific status remains
`ACCURACY NOT VERIFIED`.

## 8. Next required evidence

- PAGASA-approved historical daily/monthly observations or CliMap export joined
  to each crop season and prediction-time window.
- MAO-approved Lian or municipality observations with planting/harvest dates,
  production, harvested area, irrigation/ecosystem, and provenance.
- Original model developer’s 12-feature definitions and preprocessing.
- Research approval for whether provincial PSA targets may support any separate
  provincial model study, and for minimum sample size and acceptance criteria.
