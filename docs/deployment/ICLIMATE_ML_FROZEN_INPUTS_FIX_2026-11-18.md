# iCLIMATE — Frozen ML inputs: fix and accuracy check (2026-11-18)

**Status (updated 2026-11-18, evening): INTEGRATED into the RC2 working tree — uncommitted, not deployed.** See section 10.
Sections 1–9 describe the original patch verification on RC1.
The deployed model has not been retrained or replaced (sha256 `f3a54e12ecf85e66340475e2c49ce5c15d9c58efd82213435015a275734b777c`, unchanged).
`ICLIMATE_YIELD_GATE=withhold` stays in place. **ACCURACY NOT VERIFIED.**

This follows on from `ICLIMATE_API_ML_VERIFICATION_2026-11-18.md`.

---

## 1. Root cause

`PredictionEngine::buildModelInput()` built the lag, trend and season inputs from `ClimateHistory::months()->take(6)`, which returns **the latest six months on file** (up to 2025-12). It did this whatever month the estimate was for. That gave three faults:

| Feature | Before | Trained definition (from the model's split thresholds) |
|---|---|---|
| previous_rainfall / previous_temp | last month on file (2025-12) | month **T-1** (the month before the target) |
| rainfall_6m | **sum** of the last 6 months on file (1,982.9 mm) | **mean** of T-1 .. T-6 (thresholds 54–311 mm) |
| temp_3m / temp_6m | last 3 / 6 months on file | mean of T-1..T-3 / T-1..T-6 |
| temp_range | max−min of six monthly means (1.15 °C) | **TMAX − TMIN** of the target month (5.75–9.45 °C) |
| seasonal_rainfall / seasonal_temp | months on file in the same season | sum / mean over the **whole season** that contains T (thresholds 280–1,864 mm) |

As a result, 8 of the 12 features held the same value in every stored prediction, and every estimate was 4.0–4.2 t/ha. The model itself and the Season encoding (Wet = May–Sep, Dry = Oct–Apr, as trained) were correct. Units, feature order and preprocessing were also correct: the model applies an identity transform plus one-hot encoding of Season.

## 2. The fix

**New class `app/Support/YieldModelClimateWindow.php`** (already in the working tree; it does nothing until the patch is applied):

- Every window is anchored on the target month T.
- Lags and trailing means read **only months strictly before T**, so nothing from the target month or later leaks into them.
- A month comes from the entered Climate Record when one exists, otherwise from the PAGASA Ambulong station record.
- **A month with no observation stays missing.** No other month and no invented value takes its place. The feature is null, and the prediction reports it as missing instead of running.
- **Whole-season inputs** (owner decision 2026-11-18), applied month by month:
  - months before T must be observed;
  - T uses the target month's weather;
  - later months use the observation if it exists, otherwise the stored **ready** monthly weather forecast (`monthly_weather_predictions`, status `ready`).
  - Forecast months are listed in `seasonal_forecast_months`. If any month is unavailable, the feature is null.
- `temp_range` = TMAX − TMIN of the target month's weather; it is null if TMAX or TMIN is absent.

**Patch `docs/deployment/evidence/PredictionEngine-frozen-inputs.patch`** (10 files, and it applies cleanly to the current working tree, including Codex's `PredictionEngine.php` changes):

| File | Change |
|---|---|
| `app/Services/Prediction/PredictionEngine.php` | Inputs come from `YieldModelClimateWindow::features($targetDate, $predictions)`. The old "latest six" block is removed. Codex's hunk at ~line 259 is untouched. |
| `app/Services/Prediction/YieldModelGate.php`, `config/prediction.php`, `RiceYieldPredictionService.php` | New `ICLIMATE_YIELD_ACCURACY_VERIFIED` setting, default **false**. Under `withhold`, an estimate is shown only when its input is inside the training envelope **and** accuracy is verified. |
| `tests/Concerns/PreparesPredictions.php`, 5 feature tests | Fixtures record the months before the target. Old assertions about the "latest six, summed" values are replaced with target-relative ones. |

**Why the gate change is required with the fix:** before, `rainfall_6m` = 1,982.9 put every input outside the training range, so the gate withheld everything. With correct inputs, some farms (12 ha or more, e.g. a 29.96 ha farm in Humayingan) fall *inside* the range. The old gate would then have shown Farmers an unverified number. Apply both parts together, or neither.

Apply with: `git apply docs/deployment/evidence/PredictionEngine-frozen-inputs.patch`. The new class and its unit test are already in place.

## 3. Before / after (real Laravel pipeline, isolated RC1 worktree on the audit clone database, RC1 ML service)

The sample was 30 Active farmers × 6 target months = 180 runs. The 2025-11 and 2026-11 targets returned no model result after the fix (see the end of this section), so the "after" figures cover 120 runs.

| Feature | Before: distinct values (range) | After: distinct values (range) |
|---|---|---|
| temp_range | 1 (1.15) | 4 (6.72–9.58) |
| previous_rainfall | 1 (87.6) | 4 (11.8–488.8) |
| previous_temp | 1 (26.95) | 4 (26.8–29.8) |
| rainfall_6m | 1 (1,982.9) | 4 (68.4–294.3) |
| temp_3m / temp_6m | 1 / 1 | 4 / 4 |
| seasonal_rainfall / seasonal_temp | 2 / 2 | 4 / 4 (461–1,542 mm) |
| rainfall, temp_avg, area, season | varied | unchanged |

Predictions by target month:

| Target month | Before | After |
|---|---|---|
| 2024-03 | 4.17 | 4.03 |
| 2024-08 | 4.05 | 3.34 |
| 2025-01 | 4.19 | 4.11 |
| 2025-06 | 4.02 | 3.82 |
| 2025-11 | 4.17 | missing (season runs to 2026-04, which is not observed or forecast) |
| 2026-11 | 4.17 | missing (no observed 2026 months) |

The estimate now follows the weather. It still barely differs between farms in the same month: weather comes from one station, and farm areas below 12 ha all reach the same tree branches.

## 4. Consistency: direct model vs API vs Laravel

| Check | Result |
|---|---|
| Direct `model.predict` vs FastAPI `/predict` vs Laravel | 80 distinct inputs, **0 mismatches** (e.g. 2024-03: 4.026644 → 4.03 → 4.03) |
| Working-tree HTTP service vs backup CLI `predict.py` vs Laravel | 40 inputs, **0 mismatches** |
| Invalid input (missing, null, −999, negative, 100 °C, "nan", boolean area, bad season) | All 8 cases rejected by **both** the API and the backup path, with the same message |
| ML API down | The local fallback returns the same raw value (3.34), still withheld. With the fallback also unavailable: no number, `success=false`. |
| Missing months (2026 targets) | No model call. `missing_inputs` lists the features, and the reason is shown. |
| Model file | sha256 unchanged before and after |
| Farmer visibility | 180/180 runs withheld (`availability=withheld`); **0 numbers shown** |

## 5. Exploratory accuracy (EXPLORATORY ONLY — not a validation)

- **Data:** the deployed model on the 29 MAO barangay-season records in `historical_rice_productions`, using features built by the application's own code.
- **Hindcast weather:** the observed Ambulong month is used as the target-month weather.
- **Records used:** 19 records have complete weather (all 2024: 10 WS, 9 DS). 20 were skipped because their windows need weather after 2025-12, which does not exist and was not filled in. 2 more were skipped as incomplete.
- **Season estimate:** the mean of the monthly predictions.

| Inputs (same 19 records) | MAE | RMSE | R² | Pearson r |
|---|---|---|---|---|
| Frozen (before) | 0.779 | 0.965 | −0.797 | 0.38 |
| **Fixed (after)** | **0.531** | **0.673** | **+0.127** | **+0.475** |
| Baseline: mean of observed (in-sample / leave-one-out) | 0.628 / 0.663 | | | |

After the fix the model beats the mean baseline by a small margin. Before the fix it did worse than simply guessing the average. Predictions cover 3.36–4.01 against observed 2.38–4.68, so the model still compresses the spread.

**Caveats:**
- n = 19, from a single year.
- The training data is unknown, so overlap with these records **cannot be ruled out**. Municipal 2024 yields may be in the training set.
- Most of the 124 rice-production rows are seeded allocations of municipal totals.

This is **not** evidence of verified accuracy.

Scripts and per-record results are in `docs/deployment/evidence/`: `eval_app_features.php`, `eval_app_features_score.py`, `frozen-inputs-eval-19-records.json` and `frozen-inputs-consistency.py`.

## 6. Tests

| Suite | Result |
|---|---|
| PHPUnit, RC1 + patch, PHP 8.5 | **1,679 tests, 0 failures** (7 skipped) |
| PHPUnit, current working tree + patch, PHP 8.4.27 | 1,693 tests; 2 failures (`AccountDeletionTest` email, `PaletteTest` email theme). **Both also fail without the patch**: they come from in-progress work in the tree, not from this fix. |
| Prediction / climate / gate / AI classes on **MariaDB 10.11.19** + PHP 8.4 | 76 tests, 0 failures (migrations ran: 78 tables) |
| Python (working tree) | 69 passed |
| JS unit tests / Vite build | 19 passed / build OK |
| Playwright, RC1 + patch, isolated SQLite | 160 passed, 40 skipped, 6 failed on port 8141. All 6 failures were RC1's hard-coded `127.0.0.1:8123` in `landing.spec.mjs` (fixed in the working tree). On port 8123, `landing.spec.mjs`: 139 passed, 0 failed. The patch changes no UI. |
| New unit tests (`tests/Unit/YieldModelClimateWindowTest.php`) | 5 passed |

The new unit tests cover:
- anchoring on the target month, with no reads of the target month or later for lags;
- the six-month mean;
- TMAX − TMIN;
- the whole Wet season;
- a Dry season that crosses the year end;
- forecast months flagged and `unready` forecasts ignored;
- no forecast → missing, not a partial total;
- an entered month replacing only its own station month.

## 7. Remaining issues

1. **Live estimates will be "missing" until 2026 weather is entered.** The station record ends in 2025-12, so every 2026 target lacks its lag months. Once the MAO enters the observed months in Climate Records, the next month becomes predictable. This is correct behaviour (no substituted months), but it is a visible change: the yield card will say the input data is incomplete instead of being withheld.
2. **Only the current month and next month can ever be estimated**, because lags must be observed and later months lie in the future. The warm-up prepares 12 forecast months, which covers any season end.
3. **No farm or barangay differentiation within a month:** single-station weather, and farms below 12 ha share tree branches. This is a model and data limitation, not an integration fault.
4. One comment in the working-tree `python_scripts/rice_yield_service.py` ("Rainfall_6M allows a multi-month SUM, which is what the application sends today") goes out of date once the patch is applied. The range itself is still fine. Left for Codex, since that file is theirs.
5. The model's accuracy is still unverified: the training data is unknown, and there is no independent holdout.

## 8. ML readiness

- **Integration:** ready. Inputs are correct, the three paths match, and invalid input is rejected on both the API and the backup path.
- **Accuracy:** **NOT VERIFIED.** The exploratory result is slightly better than the mean baseline, but the sample is small, from one year, and may overlap training.
- **Farmer exposure:** none. Estimates stay withheld under `withhold` until the owner sets `ICLIMATE_YIELD_ACCURACY_VERIFIED=true` after an independent validation.

## 9. Recommendation for the next release candidate

1. Apply the patch to RC2 **as one unit**: input fix plus gate change. Coordinate with Codex, because the patch touches `PredictionEngine.php` (it applies cleanly over their current change).
2. Keep `ICLIMATE_YIELD_GATE=withhold` and leave `ICLIMATE_YIELD_ACCURACY_VERIFIED` unset.
3. After deploy, have the MAO enter the observed 2026 monthly weather (PAGASA Ambulong) in Climate Records, so lag inputs exist for upcoming months.
4. Before any estimate is shown to Farmers, run a validation against independent observed yields, such as a season not used for training, with training rows documented. Only then decide on `ICLIMATE_YIELD_ACCURACY_VERIFIED`.
5. Stored `yield_predictions` rows made with frozen inputs remain as history. They are already withheld by the gate and should not be used as evidence.

---

## 10. RC2 integration (2026-11-18, evening)

**Status: integrated into the RC2 working tree.** Nothing was committed, pushed, tagged, merged, retrained or deployed. The model sha256 is unchanged (`f3a54e12…777c`). The RC1 tag `v2026.11.18-rc1` is intact.

### Integration

- **Conflicts:** none. No files had been edited in the 3 hours before integration, and `git apply` was clean. Codex's `source_type` / `inference_transport` change in `PredictionEngine.php` is preserved; the API-down path reports `inference_transport=local_subprocess`.
- **Backup:** the pre-integration copies of every touched file are in the session scratchpad (`pre-integration/`).

### Files changed

| Change | Files |
|---|---|
| Patch, applied as is | `app/Services/Prediction/PredictionEngine.php`, `RiceYieldPredictionService.php`, `YieldModelGate.php`, `config/prediction.php`, `tests/Concerns/PreparesPredictions.php`, `tests/Feature/{AIHybridOrchestratorTest, AiPredictionReadOnlyTest, PredictionEngineClimateRecordPlausibilityTest, PredictionParityTest, YieldModelGateTest}.php` |
| Already in place | `app/Support/YieldModelClimateWindow.php` |
| Added in RC2 | `tests/Unit/YieldModelClimateWindowTest.php`: 2 new tests, for 7 in total. One checks that an extreme entered value in the target month or later never changes the lag inputs (leakage probe). The other checks that an observed later month is used instead of its forecast, and that missing target weather gives a missing total. |
| Comment only | `python_scripts/rice_yield_service.py`: the "Rainfall_6M is a SUM" comment now describes the mean and whole-season sum. No behaviour change. |
| Test fix (MariaDB) | `tests/Feature/PreMigrationCompatibilityTest.php`: rolls back the new `2026_11_18_000001` submissions migration first, because its table references `association_yield_reports` and MariaDB refuses to drop a referenced table. SQLite does not check this. The defect came from the RC2 migration and is unrelated to the ML fix. |

### The two "email" failures from section 6

These were not code defects. My scratch copy excluded every directory named `vendor`, which also removed `resources/views/vendor/mail`. On the real tree, both tests pass on PHP 8.4 and 8.5. No change was made.

### Test results (RC2 working tree + integration)

| Suite | Result |
|---|---|
| PHPUnit, PHP 8.4.27, SQLite | **1,695 passed, 0 failed** (5 MariaDB-only tests skipped) |
| PHPUnit, PHP 8.4.27, **MariaDB 10.11.19**, `mysql` driver (as deployed) | **1,695 passed, 0 failed, 0 skipped** |
| Python | 69 passed |
| JavaScript | 26 passed |
| Playwright (isolated SQLite, port 8123) | **178 passed, 0 failed** (18 viewport-specific skips) |
| Direct model vs ML API vs Laravel | 80 distinct inputs, 0 mismatches |
| HTTP service vs backup CLI vs Laravel | 40 inputs, 0 mismatches; 8/8 invalid inputs rejected identically |

**Driver note:** with `DB_CONNECTION=mariadb`, 40 tests fail, both before and after this integration. Nine migrations guard MySQL-specific DDL with `getDriverName() === 'mysql'`. Under the `mariadb` driver that DDL is skipped: for example, `planting_advisories.status` stays `enum('Draft','Published')`, so newer statuses are truncated. Production, `.env.example` and `deploy/hostinger/iclimate.env.example` all use `mysql`, so the deployed configuration is not affected. **Do not switch to the `mariadb` driver** until those guards accept both drivers.

### Prediction comparison (RC2 code, audit clone, 30 farmers × 6 months)

These figures are identical to section 3.

- Frozen features: 0 of 12 (each former frozen feature takes 4 distinct values).
- Estimates by month: 2024-03 4.03, 2024-08 3.34, 2025-01 4.11, 2025-06 3.82–3.83.
- 2025-11 and 2026-11 targets: explicit missing inputs.
- Estimates shown to Farmers: 0 of 180.

What a Farmer sees for a 2026 month (missing weather): card state `records`, with no number and this text:
- "Not enough weather records yet for an estimate."
- "Kulang pa ang datos ng panahon para sa tantiya."

### Exploratory metrics (recomputed on RC2): ACCURACY NOT VERIFIED

- n = 19 (2024 only)
- MAE 0.531, RMSE 0.673, R² 0.127, r 0.475
- Mean baseline MAE 0.628 (leave-one-out 0.663)
- Training overlap cannot be ruled out.

### Release readiness

- **Integration:** ready for an RC2 commit once approved. All suites pass, and there are no unexplained failures.
- **Yield model:** not ready to show Farmers. Keep `ICLIMATE_YIELD_GATE=withhold` and leave `ICLIMATE_YIELD_ACCURACY_VERIFIED` unset (false).
- **Owner action:**
  - Approve the RC2 commit.
  - Have the MAO enter the observed 2026 monthly weather.
  - Before enabling the flag, commission an independent accuracy validation.
