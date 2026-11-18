# Leakage-Safe Evaluation Protocol

This protocol is proposed for researcher and adviser approval. It does not run predictions or change the deployed model.

## Gate 1 — package identity

- Record SHA-256 for the deployed model, supplied data files, source documents, and evaluation code version.
- Record the original model developer, artifact date/version, target, and permitted use.
- Preserve the production checksum: `f3a54e12ecf85e66340475e2c49ce5c15d9c58efd82213435015a275734b777c`.

## Gate 2 — data acceptance

Reject or quarantine rows with missing source references, unapproved provenance, demo/seed/allocation/derived status, duplicate IDs, invalid dates, invalid units, non-rice crop, impossible area/yield, or mismatched geography.

Do not use a row as independent evidence if it was used for model training, feature engineering, tuning, baseline construction, or a replicated copy of any such row.

## Gate 3 — feature reconstruction

For every accepted row:

1. Reconstruct all twelve features using the authenticated original definitions.
2. Check finite numeric values and the approved `Wet`/`Dry` encoding.
3. Verify that planting, harvest, season, and climate windows do not include future information relative to the target date.
4. Compare the reconstructed feature table with the production mapping without changing production calculations.

If any feature definition is unresolved, the evaluation is `BLOCKED` or must be explicitly scoped as a sensitivity/compatibility analysis rather than accuracy validation.

## Gate 4 — split design

Preferred design:

- chronological holdout by later season/year, or
- season-grouped holdout where all records from a season/group remain in one partition.

The split must be frozen before model inference. Report the number of observations, farms/groups, seasons, years, barangays, and target range in each partition. Do not shuffle rows when shared season or farm history could overlap.

## Gate 5 — metrics and baselines

On the frozen independent test set, calculate:

- MAE in the approved target unit;
- RMSE in the approved target unit;
- R² with an explanation if negative;
- signed bias/mean error;
- MAPE only when the target is a ratio-scale yield and zero/near-zero handling is pre-approved;
- median and percentile absolute errors;
- actual-versus-predicted table and residual plots;
- overall-mean and information-available seasonal baselines.

State sample/group counts and limitations beside every metric. A lower error than a baseline does not by itself prove operational suitability; the acceptance threshold must be approved in advance.

## Gate 6 — interpretation

Report three separate result classes:

1. **Original training metrics:** only if authenticated from the original developer package.
2. **Reproduced historical test metrics:** only if the original split and rows are supplied and reproducible.
3. **Independent deployed-model evaluation:** only when the frozen accepted observations were not used in training, tuning, feature construction, or model selection.

If any gate fails, report `ACCURACY NOT VERIFIED` and do not convert a prediction value, heuristic confidence, or candidate-model score into an accuracy percentage.
