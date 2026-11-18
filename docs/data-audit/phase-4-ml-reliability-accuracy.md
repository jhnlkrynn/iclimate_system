# Phase 4 report: ML reliability and accuracy

Date: 2026-11-12. Companion to `phase-2-report.md` and `phase-3-ml-dataset-methodology.md`.
For: researchers and adviser. Purpose: review of findings and approval of a correction plan.

Every check behind this report was read-only or run on copies. No model was retrained or replaced, no formula or record changed, and no service was stopped. **No Phase 4 runtime/model safeguard proposed here has been implemented.** The separate Phase 3 evaluation-page and MAO-panel evidence labels were applied before this report and are recorded as such below.

Each finding is marked:

- **Confirmed**: measured or read directly from the system.
- **Inferred**: the most reasonable explanation of the evidence, not proven.
- **Unresolved**: cannot be answered from what the project holds.

## 1. Executive summary

1. **The checked execution paths work.** Every saved model loads, runs and returns values, and the web application, the Python service and the database exchange them correctly. Stored predictions equal what the model computes. This establishes functionality, not accuracy. *(Confirmed)*
2. **No model has verified accuracy.** There is no independent, validated yield data to test against, and the one model that could be tested on unseen observations (the weather forecast) did worse than a simple average. *(Confirmed)*
3. **The deployed rice-yield model is given inputs it was not trained on.** Three of its twelve inputs arrive on a different scale or definition than the training data used, and four more are frozen at 2025 values. As a result it returns 4.11 to 4.18 t/ha for every farm. *(Confirmed)*
4. **The "confidence" number is a fixed rule, not a measurement.** It returned 91 for inputs the model had never seen the like of. *(Confirmed)*
5. **Demo rice records feed the Heat Map risk score** for at least 11 barangays. *(Confirmed)*
6. **Recommendation:** treat all yield figures as unvalidated until the items in section 10 are approved, and apply the display safeguards in section 7 first. They change no model and no prediction. The Phase 3 evaluation-page and MAO-panel wording safeguards are already applied; the Phase 4 runtime/display safeguards remain proposed.

## 2. Model inventory and functionality

| Model | File | Type | Status in system | Loads | Runs |
|---|---|---|---|---|---|
| Rice yield, deployed | `storage/models/rice_yield_model_final.pkl` | Random Forest, 300 trees, depth 10, 12 inputs | Active; used for all farm, barangay and municipal yield figures | Yes | Yes |
| Monthly weather forecast | built in code (`MonthlyWeatherRandomForest`, version `monthly_weather_rf_v5`) | Random Forest, retrained from the Ambulong record each time it runs | Active; feeds the yield model, Heat Map and dashboards | Yes | Yes |
| Weather label model | `storage/models/weather_model.pkl` | Linear regression, 6 inputs | Active inside the yield service; gives a rain label | Yes | Yes |
| Rice yield, candidate | `storage/models/rice_yield_model_candidate.pkl` | Random Forest, 500 trees, depth 2, 10 inputs | Inactive; shown only as a stored backtest | Yes | Yes |
| Rice yield, candidate "October wet" | `storage/models/rice_yield_model_candidate_oct_wet.pkl` | same as candidate | Inactive | Yes | Not exercised |

The risk score (`AgriculturalRiskScorer`) is a weighted formula, not a trained model. It is covered in section 5.

Functional checks, all *Confirmed*:

- The Python service answers its health check with both models loaded.
- A request with missing inputs is refused with "Missing required feature: previous_rainfall". No value is invented.
- A stored production input, run through the live service and separately on a copy, gives 4.12 t/ha, the saved value.
- Weather forecasts and the 6,498 stored yield predictions were regenerated on 2026-11-11. Open-Meteo data and Heat Map ratings were refreshed within minutes of the check.

Classification:

| Model | Classification |
|---|---|
| Rice yield, deployed | **Partially working** (runs, but on incompatible inputs); accuracy not verified |
| Monthly weather forecast | **Working, accuracy not verified** (measured below a simple average) |
| Weather label model | **Working, accuracy not verified** |
| Rice yield, candidate (both) | **Working, accuracy not verified** (hold-out worse than guessing the average) |

## 3. Training versus production input compatibility

### 3.1 What the deployed yield model was trained on

The training dataset and training script for `rice_yield_model_final.pkl` are **not in the project** *(Confirmed; Unresolved where they are)*. The script that is in the project, `train_rice_yield_model.py`, builds a different model with 7 inputs.

The saved model itself records the following *(Confirmed, read from its trees)*:

- It was trained on **48 rows**.
- Its predictions can only fall between **1.76 and 4.70 t/ha**. Those are exactly the lowest and highest monthly yields in the MAO planting summary.

*Inferred:* it was trained on 48 of the 60 MAO municipal monthly rows, which is what an 80/20 split of 60 rows gives. Those rows are unvalidated (Phase 3).

### 3.2 Inputs that do not match

The model's trees show the range of values each input took during training. Production sends something different for three of them:

| Input | Range in training | What production sends | Stored predictions outside the training range |
|---|---|---|---|
| Area | 12 to 258 ha | the farm's size, 0.1 to 30 ha | 6,431 of 6,435 |
| Rainfall_6M | 54 to 311 mm | a six-month **sum**, 1,982.9 mm | 6,435 of 6,435 |
| TEMP_RANGE | 5.75 to 9.45 °C | spread of six monthly averages, 1.15 °C | 6,435 of 6,435 |

*Inferred definitions in training:* Area was the municipal area planted that month (the MAO file's range is 5.7 to 266 ha); Rainfall_6M was a six-month average; TEMP_RANGE was the month's maximum minus minimum temperature. Phase 2 had flagged TEMP_RANGE as a suspected mismatch. This confirms it and adds two more.

### 3.3 Inputs that are out of date

Four inputs describe "recent weather": previous month's rainfall and temperature, the six-month rainfall, and the seasonal rainfall. They are taken from the Ambulong record, which ends in December 2025. For a November 2026 prediction the "previous month" is December 2025. *(Confirmed: these four values are identical on all 6,435 stored predictions.)*

### 3.4 Why every farm gets nearly the same prediction

*(Confirmed)*

- Seven of the twelve inputs are either outside what the model learned from or identical for everyone, so they push every prediction down the same branches.
- Only forecast rainfall and temperature differ between predictions, and they have little weight in this model.
- Farm area cannot matter: every farm is smaller than the smallest area the model ever split on.
- Changing each input across its full plausible range, one at a time, moves the result only between 3.69 and 4.18 t/ha.
- In the 2026-11-11 saved-yield snapshot, all 6,498 stored predictions lie between 4.11 and 4.18 t/ha. The Heat Map yield-layer thresholds classify those values as "fair". This is distinct from `heatmap_areas.predicted_yield`, which belongs to the separately persisted agricultural-risk table and must not be used as evidence that the yield layer is accurate.

The figure a farmer sees is therefore close to a constant. It is not a prediction for that farm.

### 3.5 Other models

| Model | Input compatibility |
|---|---|
| Monthly weather forecast | Compatible: it trains and predicts from the same Ambulong record. *(Confirmed)* |
| Weather label model | Training data unknown; inputs are supplied in the expected form. *(Unresolved)* |
| Candidate yield models | Compatible with the modeling dataset they were trained on. Not connected to production. *(Confirmed)* |

## 4. Accuracy and baseline comparisons

A baseline is the simplest possible guess. A model has shown skill only if it beats it on data it has not seen.

### 4.1 Monthly weather forecast against a seasonal average

This is the only test with independent observations. The model was trained on months up to December of one year and asked for each month of the next, for 2024 and 2025 (24 months). The baseline is the average of the same calendar month in earlier years. *(Confirmed)*

| Variable | Model error (MAE) | Model R² | Seasonal average error (MAE) | Seasonal average R² |
|---|---|---|---|---|
| Rainfall (mm) | 118.9 | 0.27 | 88.6 | 0.53 |
| Temperature (°C) | 0.59 | 0.40 | 0.44 | 0.77 |
| Humidity (%) | 3.50 | 0.29 | 3.29 | 0.46 |

The rain category (heavy rain, rain, cloudy, dry) was right in 10 of 24 months for the model and 16 of 24 for the seasonal average.

Limitations of the model:

- It missed the extreme months: 670 mm fell in October 2024 against 197 mm predicted, and 711 mm in July 2025 against 241 mm.
- In production it forecasts 11 to 22 months past the last observation (December 2025), longer than the 1 to 12 months tested here.
- Its own "confidence" label comes from how much data it has, not from tested skill.

Limitations of the seasonal average:

- It cannot foresee an unusual year or a typhoon either. It is only the average of the past.
- It rests on ten years from one station.

Limitations of both:

- The observations are from the PAGASA Ambulong station in Tanauan, not Lian.
- Twenty-four months is a small test.

### 4.2 Candidate yield model

Trained on 2020 to 2024, tested on the 8 months of 2025. *(Confirmed, recomputed)*

| | MAE (t/ha) | RMSE | R² |
|---|---|---|---|
| Candidate model | 0.394 | 0.469 | −1.104 |
| Always guessing the 2020–2024 average | 0.301 | 0.402 | −0.549 |

The model is worse than the average. The "recorded" yields are the unvalidated MAO monthly figures, so even this is not a validated test.

### 4.3 Deployed yield model

**ACCURACY NOT VERIFIED.**

- There is no yield data independent of what it was probably trained on.
- For information only: given observed weather and correctly aligned recent-weather inputs, it scores MAE 0.36 and R² 0.36 over 52 MAO months, against MAE 0.45 for the overall average. Most of those months were probably training rows, so this is not a test. *(Confirmed figures; Inferred overlap)*
- In the two typhoon seasons it predicted 3.68 t/ha where 2.70 was recorded.
- An attempt to rebuild its training inputs from project files did not reproduce its fit, so the original definitions remain *Unresolved*.

### 4.4 Weather label model

Over 118 pairs of consecutive Ambulong months: MAE 84.3 mm, R² 0.32, against 82.8 mm and 0.41 for the seasonal average. Its training rows are unknown, so some of these may be training data. *(Confirmed figures; Unresolved independence)*

### 4.5 Dataset-processing source gap

The successful `dataset_processing` analytics run 2766 returned `climate_rows: 0`, `rice_rows: 124`, `dataset_rows: 0`, and evaluation not ready. This is a separate analytics-pipeline limitation, not evidence that the prediction service has no climate source. *(Confirmed)*

- `AnalyticsPayloadFactory::dataset()` queries `climate_records` directly and pairs those rows with `rice_productions` by exact year and season; it does not call `ClimateHistory`.
- The current database has zero `climate_records`, while the Ambulong CSV has 131 valid historical rows. Prediction paths use `ClimateHistory`, whose fallback can read that CSV; the dataset-processing path bypasses that fallback.
- `external_weather_data` is Open-Meteo current/forecast data, not historical training climate, and must not be silently substituted for the dataset-processing history.
- The zero-row result prevents cross-validation/evaluation in run 2766. It does not by itself invalidate or overwrite the existing prediction, weather, advisory, or risk outputs; those use separate paths and persisted outputs. No evidence in this audit proves that the zero-row run changed their freshness.

**Smallest safe correction if approved:** decide and document whether evaluation may use the maintained Ambulong fallback (clearly labelled as non-Lian), or require a validated Lian climate source. Then make the dataset builder and its tests follow that one approved source policy. Do not import the CSV into `climate_records`, fabricate rows, or rerun the analytics job without researcher approval.

## 5. Data provenance and leakage risks

| Data | Provenance | Risk |
|---|---|---|
| MAO planting summary (60 months) | Original document not in the project; unvalidated | Every yield model was trained and scored on it |
| `rice_productions` (124 rows) | 110 derived allocations, 14 demo rows (Phase 3) | Not separate observations |
| Ambulong monthly record | PAGASA station in Tanauan, January 2015 to December 2025, 131 valid months | Not Lian; ends 11 months ago |
| Open-Meteo | Live and short-range forecast | Correctly kept out of model history |
| Deployed model training set | Not in the project | Cannot be audited |

Leakage risks:

- *Inferred:* the deployed model was split at random by month. Months in the same season share weather and the same trailing-window inputs, so a random split lets the test rows resemble the training rows. Any accuracy reported from that split would be too high.
- *Confirmed (Phase 3):* evaluating on the 110 duplicated rows gives R² near 1.00 that falls below zero when whole seasons are held out.
- *Confirmed:* the existing automated hold-out tests only check that the error figures are numbers of zero or more. They pass whatever the accuracy is, and they test the candidate, not the deployed model.

### Demo records in the Heat Map risk score

*(Confirmed)* The risk score takes a "last recorded yield" for each barangay, weighted 27%. It reads the newest `rice_productions` row. For at least 11 barangays that row is a demo record. Example: Balibago is scored with the demo value 3.24 t/ha although its allocated figure is 3.79.

The existing sample-data filter does not catch these rows, because it only recognises placeholder Latin text.

Proposed handling (not implemented; the Heat Map is a protected baseline and needs explicit approval):

1. Exclude demo rows when choosing a barangay's last recorded yield.
2. If only a derived allocation remains, either score the barangay without a yield term and say so, or use the allocation labelled as a municipal figure. This choice is a methodology decision (section 8).
3. Show on the map which barangays were scored without a verified yield.

## 6. User-facing prediction risks

Where the system presents model output as a prediction for a specific farm or place, or attaches a confidence. All *Confirmed* from the code and stored data unless noted.

| Place | Seen by | What it says | Why it is a risk |
|---|---|---|---|
| Farmer Home, "Rice Yield Forecast" | Farmers | "Expected yield", "For your X ha farm: about N tons", "An estimate from your farm and weather information" | The figure does not depend on the farm; tons are that near-constant times the farm's area |
| Predictions page | Farmers, MAO, IT | Same estimate with "Compared with usual" | Same |
| My Farm, planting schedules | Farmers | A predicted yield saved for each planting, with a saved confidence | Same; the confidence is the fixed rule |
| Heat Map, yield layer | All roles | Barangay and farm yield classed good, fair or low | The 2026-11-11 saved-yield snapshot was always "fair"; current live classification must be checked from the YieldPredictionStore, not the separate risk table |
| MAO Yield Forecast page | MAO | Per-barangay and municipal forecast with tons and "Outlook" | Presented as barangay-specific |
| Heat Map and dashboards, risk level | All roles | Low, Moderate, High | Uses demo yields and placeholder weights |
| Rice-yield service response | API/request or planting-schedule metadata | "confidence_score", "Trained Model API", "trained iClimate Random Forest model" | Reads as validated; the current `yield_predictions` table has no `confidence_score` column |
| AI assistant | All roles | Answers carry fixed confidence numbers (for example 76, 82, 85, 90, 92) | Rule-based; *Unresolved* whether the chat screen displays them |
| Predictions page, technical details | IT | Weather model "Confidence %" | From data quantity, not tested skill |

How the confidence number is produced *(Confirmed)*: start at 78; add 7 if the predicted yield is 4 or more; subtract 8 for a heavy-rain or dry label; add 6 if the trees agree closely, subtract 12 if they disagree widely; keep within 45 to 95. Inputs far outside the training range make the trees agree, which raises the score.

## 7. Immediate safeguards (proposed, awaiting approval)

None of these changes a model, a formula or a stored prediction.

**A. Label yield figures honestly.**
- Replace "Expected yield" wording with a plain statement that this is an early estimate for Lian as a whole and has not yet been checked against real harvests.
- Remove "from your farm" wording, and remove or caveat the per-farm tons line.
- On the Heat Map yield layer, show "Not yet validated" in place of good/fair/low, or hide the layer.

**B. Remove the confidence number wherever it is shown or stored for display.**
- Show "Confidence not measured" instead.
- Stop saving the rule-based value as `prediction_confidence` for new predictions; leave existing rows as they are.
- Apply the same to fixed confidence values in assistant answers if the chat screen shows them.

**C. Weather forecast.**
- State next to monthly forecasts that they are long-range estimates from the Ambulong station record.
- Show the seasonal average beside the forecast.

**D. Heat Map risk.** As proposed in section 5.

**E. Model Evaluation and MAO panels.** Already applied in Phase 3 follow-up.

**F. Duplicate scheduler.** See below.

### Duplicate scheduler processes

*(Confirmed)* Two scheduler processes are running, each under its own supervisor:

| Supervisor | Started (Manila) | Tracked by the launcher |
|---|---|---|
| process 86340 | 2026-11-12 09:03:12 | Yes (recorded in `scheduler.pid`) |
| process 98200 | 2026-11-12 09:03:19 | **No** |

- Both belong to the same user account and were started by the project's launcher script. A login agent (`local.iclimate.services`) runs that launcher at login and every 5 minutes.
- *Inferred:* two launcher runs overlapped at 09:03. The first had not yet recorded its scheduler when the second checked, so both started one.
- The untracked one will not be stopped by the launcher's own stop command, and the launcher reports the scheduler as healthy without noticing it.
- Every scheduled task has an overlap lock, held in the shared file cache. In the recent scheduler log each task ran once per time slot. *(Confirmed for the slots inspected; not proven for all tasks.)* A short task that finishes before the second scheduler tries could still run twice.

Proposed correction, safest first:

1. Stop only the untracked supervisor and its child (process group 98200) with a normal termination signal, mid-minute so no task is starting. The tracked scheduler keeps running, so no schedule is missed and no other service is touched.
2. Confirm one scheduler remains and tasks still run each minute.
3. Prevent a repeat: have the launcher take a lock before starting a service, so two overlapping runs cannot both start one, and have its status check report more than one scheduler.

## 8. Research methodology decisions

These need the researchers and adviser. Items 1 to 5 are carried over from Phase 3 and are still open.

1. Confirm the MAO planting summary and what its "month" means.
2. Unit of analysis: municipal season (11) or planting month grouped by season (60).
3. Which weather months belong to a crop.
4. Whether Ambulong station data may stand in for Lian, always labelled as such.
5. Validation method and the minimum number of independent seasons.
6. **What the yield model is meant to predict.** It was trained on municipal monthly averages. It cannot give farm-level predictions unless farm-level observations exist to train and test it.
7. **Definitions of every model input**, written down with units, including what "Area" means.
8. **How recent-weather inputs are obtained** for a future month when station data ends a year earlier: wait for updated PAGASA data, or drop those inputs.
9. **Whether to keep a weather model that does not beat the seasonal average**, or to use the seasonal average for long-range months.
10. **Whether yield belongs in the risk score** while no verified barangay yields exist, and the basis for its weights.

## 9. Regression testing plan

### Production feature compatibility (automatic)

1. **Record a training profile for each deployed model:** for every input, its name, unit, definition, and the minimum and maximum seen in training. Until the original training data is recovered, the split ranges read from the saved model (section 3.2) can serve as a provisional profile.
2. **Test that production inputs fall inside the profile.** Build the model input the way the application does, for a representative farm, barangay and municipal request, and fail if any input is outside its recorded range.
3. **Check at run time as well:** when an input is outside the profile, mark the prediction "outside the model's tested range" and do not show it as an estimate.
4. **Test freshness:** fail if a "previous month" input is more than an agreed number of months older than the target month.
5. **Test sensitivity:** across a set of clearly different farms and months, predictions must not all be the same. A spread below an agreed threshold fails.
6. **Test definitions:** for a fixed set of monthly weather values, the six-month rainfall and temperature-range inputs must equal hand-computed values under the agreed definitions.

### Accuracy tests

7. Replace "the error is a number" checks with a comparison against a baseline on held-out seasons, reported, and failing only if it falls below an agreed floor.
8. Keep the checks added in Phase 3: demo and derived records are never counted as observations.

### Display tests

9. No page shows a confidence percentage for yield while confidence is unmeasured.
10. Yield wording carries the "not yet validated" statement while the evidence checks fail.

## 10. Researcher/adviser approval checklist

**A. Before any claim of prediction accuracy, all of these must exist:**

Verified data
- [ ] Original MAO planting and production records, with their source document, validated by MAO.
- [ ] Harvest dates or an agreed crop duration.
- [ ] At least the agreed minimum number of independent seasons.
- [ ] If farm or barangay predictions are wanted: farm or barangay harvest records, validated, not allocations.
- [ ] A climate record for Lian, or written approval to use Ambulong, updated to the present.

Model-training artifacts
- [ ] The training dataset for each deployed model, kept with the project.
- [ ] The training script, with library versions.
- [ ] A written definition and unit for every input.
- [ ] The list of rows used for training and for testing.
- [ ] The training profile described in section 9.

Methodology approvals
- [ ] Decisions 1 to 10 in section 8.
- [ ] Results reported against a baseline, on held-out seasons, with the number of seasons stated.

**B. Approval of immediate safeguards (section 7):**

- [ ] A. Honest labels on yield figures
- [ ] B. Remove the rule-based confidence number
- [ ] C. Weather forecast note and seasonal average
- [ ] D. Heat Map: exclude demo yields (protected page; explicit approval required)
- [ ] F. Stop the untracked scheduler and add the launcher lock

**C. Approval of later work (not to start without sign-off):**

- [ ] Correct the production input definitions once the training definitions are confirmed
- [ ] Retrain or replace the yield model under the approved methodology
- [ ] Add the regression tests in section 9

Reviewed by: ______________________  Date: ____________

Approved items: ______________________________________________

## 11. Phase 4 completion status and remaining blockers

### Completed or verified in the existing work

- Read and reconciled the Phase 2 and Phase 3 evidence with the current Phase 4 report.
- Verified model inventory, load/inference paths, training-range observations, production feature construction, provenance/leakage risks, and the weather-versus-seasonal-baseline comparison.
- Recorded the distinction between functionality and accuracy, and between measured confidence and rule/data-quantity scores.
- Confirmed the Phase 3 display/evaluation safeguards already applied, while leaving Phase 4 runtime safeguards proposed only.
- Documented Heat Map demo/derived-data dependencies, the dataset-processing zero-climate-row source gap, and the historical duplicate-scheduler observation.
- Checked report whitespace with `git diff --check`. No application code, database row, model artifact, service, or scheduler was changed.

### Unfinished or requiring approval

- Recover and validate the missing deployed-model training dataset, script, input definitions, and library versions.
- Obtain researcher/adviser decisions 1–10 before changing source selection, model target, risk weighting, confidence presentation, or model status.
- Approve and implement the regression/display safeguards in sections 7 and 9; none of the Phase 4 safeguards has been implemented.
- Recheck the duplicate scheduler with a fresh process snapshot before any operational action; the two-process evidence in section 7 is a historical snapshot, not a claim that the same PIDs remain active.
- Establish independent, validated Lian harvest/climate observations and rerun evaluation only after approval. Until then, yield accuracy remains **NOT VERIFIED** and the dataset-processing evaluation remains **NOT READY**.
