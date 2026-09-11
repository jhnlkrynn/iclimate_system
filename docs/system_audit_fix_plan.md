# iClimate System Audit — Phased Fix Plan

Prepared: 2026-09-11
Source: full-system audit covering the Laravel backend and the Python ML service (`python_scripts/`), findings verified directly against the live database, git history, and running code — not just static review.

## Guiding principle

**Prefer the smallest change that removes the problem.** Most items below have a one-line or one-file fix. Where a "correct" fix would mean a bigger redesign (a `barangays` lookup table, a full Python test suite, a statistically-derived confidence model), this plan takes the cheap interim fix now and only recommends the bigger version if the cheap fix keeps failing in practice. Nothing here should take more than a day except the items explicitly marked as blocked on external data.

Every item is tagged with its severity from the audit: **[CRIT]**, **[MOD]**, **[MIN]**.

---

## Phase 0 — Stop the bleeding (today, ~30 minutes, no code changes)

These are config/data actions, not development work. Do them before anything else in this plan.

- [ ] **[CRIT] Rotate the live admin credential.** `admin@iclimate.com` / `iclimate2026` is a working IT-Expert login in the current database. Either change that user's password directly (`php artisan tinker` → `User::where('email','admin@iclimate.com')->first()->update(['password' => Hash::make('<new-strong-password>')])`) or deactivate the seeded demo accounts (`status = inactive`) if they're not actually needed day-to-day. Do the same for `mao@iclimate.com` / `farmer@iclimate.com` if they're not intentionally kept as demo logins.
- [ ] **[MOD] Set `SESSION_SECURE_COOKIE` correctly for wherever this actually deploys.** If this `.env` genuinely stays local-only, leave it — but add a one-line comment in `.env.example` noting it must be `true` the moment `APP_URL` uses `https://`. Simpler than making it auto-derive in code.

---

## Phase 1 — One-line/one-file code fixes (this week)

Nothing here requires design decisions. Each is a small, isolated diff.

- [ ] **[MOD] Fix `CommunityFeedController::canManagePost`** (line 147-150). Add the ownership check that `MessagingController::destroyMessage` already models correctly:
  ```php
  private function canManagePost(User $user, FeedPost $post): bool
  {
      return $this->canPost($user) && $post->user_id === $user->id;
  }
  ```
  If IT-Expert should be able to moderate any post, make that explicit (`|| $user->role === User::ROLE_IT_EXPERT`) instead of leaving it implicit.

- [ ] **[MOD] Throttle the web registration route.** `routes/auth.php` — add `throttle:5,10` to the `register` POST route, matching the pattern already used on the mobile API (`routes/api.php`).

- [ ] **[MOD] Add a try/catch boundary around the heatmap's ML call.** In `HeatmapAreaController::index` (or inside `HeatmapRiskService::refresh()`, whichever is the single choke point), wrap the `requestYieldFromModel()` call and fall through to the stored-average path on `Throwable`, logging via `Log::error`. Copy the pattern already used correctly in `RiceYieldPredictionController`.

- [ ] **[MIN] Fix the local `artisan test` false-failure readout.** Uncomment `pdo_sqlite` (and `pdo_mysql` if needed) in the PHP 8.5 binary's `php.ini` (`php.ini:935` area) so `artisan test` inherits the same working driver `vendor/bin/phpunit` gets when run directly. This is purely local dev hygiene — the real suite already passes (182 tests / 888 assertions).

- [ ] **[MIN] Add unique constraints to prevent duplicate historical rows.** Migration adding a unique index on `rice_productions(barangay, season, year)` and on `climate_records(record_date, source)` (or whatever combination is actually meant to be unique). Cheap insurance against reseeding/reimport duplicating rows silently.

- [ ] **[MOD] Delete the dead Flask service.** Remove `python_scripts/farming_assistant_api.py`, drop `Flask`/`Werkzeug`/`blinker`/`click`/`itsdangerous`/`Jinja2`/`MarkupSafe` from `python_scripts/requirements.txt` if nothing else needs them, and fix `main.py`'s stale docstring line ("Not yet wired to PHP") since it has been wired since `PythonService.php` was pointed at FastAPI. This is a deletion, not a rewrite — lowest-risk item on this list.

---

## Phase 2 — Honesty fixes: relabel before you retrain (this week, no ML work needed)

These fix the "system says something misleading" problem without touching any model. They're copy/labeling changes plus one small guard clause — deliberately chosen over a bigger fix because the underlying ML work (Phase 3) is what would actually be needed to make the *numbers themselves* trustworthy, and that's blocked on data you don't have yet. Until then, don't let the UI imply more rigor than exists.

- [ ] **[CRIT] Add a schema-mismatch guard to the Model Evaluation page.** In `ModelEvaluationService::buildReadyEvaluation()`, when metrics come back `ready: true`, add a static warning string to the response (e.g. `'schema_caveat' => 'These metrics were computed on a candidate feature set. The currently deployed model (rice_yield_model_final.pkl) was trained on a different feature set and has not yet been evaluated against this data.'`) and render it prominently in `resources/views/model-evaluation/index.blade.php`, right next to the `deployed_model` line (453). This is a few lines of PHP/Blade — no retraining required — and it closes the gap between "what the code comments already know" and "what a MAO/IT-Expert user sees."

- [ ] **[MOD] Relabel fabricated confidence scores as what they are.** In `rice_yield_service.py::confidence_for()` and `monthly_weather_random_forest.py::confidence()`, keep the logic (it's a reasonable decision-support heuristic) but stop calling it a model "confidence score." Rename the field/label to something like `advisory_confidence` or `decision_support_confidence`, and update the explanation strings (currently `"source_type": "Trained Model API"` next to a hand-computed number) so the UI text says it's a rule-based indicator combining model output with weather conditions — not a statistical confidence interval. Pure renaming + copy edit, same behavior.

- [ ] **[MOD] Add a methodology caveat to the heat map's risk scoring.** `AgriculturalRiskScorer::BARANGAY_EXPOSURE` hardcodes an "exposure" value per barangay with no cited source, and the weighting formula has no derivation. Getting real flood-zone/elevation/hazard data is a Phase-3-style blocked item (see below), but in the meantime, add one line to the heat map view (and to `AgriculturalRiskScorer.php`'s class docblock) stating these are engineering-judgment placeholder values, not sourced hazard data. Same pattern as the schema-mismatch guard above — disclose now, fix properly when the data exists.

- [ ] **[MOD] Put the weather-label thresholds in one place.** Currently duplicated as identical magic numbers in `WeatherLabeler.php` and `rice_yield_service.py::weather_label()`. Simplest fix that doesn't require a shared-config mechanism across PHP/Python: add a one-line comment in each pointing at the other file ("keep in sync with X — see also Y"), **and** add a cheap test on each side that pins the exact threshold values, so any future edit that only changes one side fails CI instead of drifting silently. (A shared JSON config both languages read would be "more correct" but is overkill for four numbers that rarely change — skip it unless these thresholds start changing often.)

---

## Phase 3 — Data-dependent ML fixes (blocked on real MAO/PAGASA data)

These cannot be "coded" away — they require the real historical dataset that doesn't exist in the repo yet. Listed here so the plan is complete, but they don't start until that data arrives.

- [ ] **[CRIT] Import real historical climate + rice production records**, run `php artisan iclimate:export-yield-data`, confirm ≥30 rows (the reliability floor already coded into `evaluation_service.py`).
- [ ] **[CRIT] Retrain on the TARGET schema** using `train_rice_yield_model.py` against the real export, replace `storage/models/rice_yield_model_final.pkl` with the new model, and update `predict.py` / `rice_yield_service.py::rice_features()` to the TARGET column set at the same time so there is only one schema again.
- [ ] Once the deployed model matches the schema the Model Evaluation page actually evaluates, **remove the Phase 2 schema-mismatch guard** — that warning should disappear the moment it stops being true, not linger as permanent clutter.
- [ ] **[CRIT] Delete the DEPLOYED-schema code path entirely** (`FEATURE_MAP` in `predict.py`, the old `rice_features()` columns) once the cutover is done — this is a net simplification, removing a whole parallel schema instead of maintaining two.
- [ ] Retrain `weather_model.pkl` the same way, or replace it with a documented, tested pipeline if the original approach can't be reconstructed.
- [ ] **[MOD] Replace `BARANGAY_EXPOSURE`'s invented values with real hazard data** (MDRRMO flood-hazard maps, elevation, historical typhoon-track exposure — whatever the LGU/MDRRMO can actually provide) once accessible, and remove the Phase 2 caveat once it's evidence-based. If no such data becomes available before the defense, keep the disclosure permanently rather than removing it.

---

## Phase 4 — Background hardening (ongoing, do opportunistically, not blocking)

Lower urgency than everything above. Pick these up between other work rather than scheduling a dedicated sprint.

- [ ] **[MOD] Make `rice_yield_service.py`'s input handling consistent with `predict.py`.** Replace the silent-default `_feature()` helper's use in `weather_features()`/`rice_features()` with the same "raise if missing" behavior `predict.py::build_features()` already has. One function change, removes a real silent-failure mode.
- [ ] **[MOD] Add a small number of Python smoke tests**, not a full suite: `/health` returns 200 with expected keys, `/predict` with a known-good payload returns a plausible shape, `evaluation_service.evaluate()` returns `ready: false` when the CSV is missing. Four or five `pytest` functions is enough to catch "the service doesn't even boot" — resist the urge to build out exhaustive coverage before there's a stable schema to test against (Phase 3).
- [ ] **[MIN] Give the monthly-weather Random Forest a cheap validation signal.** Full k-fold CV isn't very meaningful at 4-24 samples, but a leave-one-out prediction pass (cheap at this sample size) and reporting the resulting MAE alongside the existing heuristic "confidence" would at least replace one fabricated number with a real one. Small addition to `monthly_weather_random_forest.py::predict()` — compute LOO error once, include it in the response instead of (or alongside) the arithmetic confidence score.
- [ ] **[MIN] `barangay` data integrity — interim fix only.** Don't build the `barangays` lookup table yet. Instead, audit every write path (seeders, factories, import commands, not just the CRUD forms) to confirm they all go through the existing `LianBarangays` validation, and fix any that don't. Only invest in a real FK-backed lookup table if mismatches keep showing up in `normalizeBarangay()`'s fuzzy-matching after that audit — right now the cheaper fix (consistent validation) probably closes most of the gap.
- [ ] **[MIN] Revisit `area_hectares` as a yield-per-hectare predictor.** Either drop it from the TARGET schema before the Phase 3 retrain, or add one sentence to `FeatureCatalog.php` justifying why farm size should predict a size-normalized rate (e.g. economies of scale). Five-minute decision, do it before Phase 3's retrain rather than after.
- [ ] **[MIN] `ReportController` payload validation.** Add a minimal Form Request rule set for the JSON shape instead of accepting arbitrary structure — low priority since it's IT-Expert/MAO-only, but cheap once someone's touching that controller for something else.
- [ ] **[MIN] Manuscript literature gaps.** The 15 `[LITERATURE SOURCE REQUIRED]` markers and the uncited decision-support thresholds are a writing/research task, not a code task — track separately from this plan, but don't let Phase 3's retrain close out the ML section of the manuscript without also closing these.

---

## What this plan deliberately does NOT do

- It does not propose a `barangays` relational table, a shared PHP/Python config service, or a full Python CI pipeline up front — all three are reasonable long-term ideas but are more infrastructure than the current problem size justifies. Revisit only if the cheaper Phase 4 fixes prove insufficient.
- It does not attempt to statistically derive real confidence intervals in Phase 2 — that requires the same real dataset Phase 3 is blocked on. Phase 2 only stops the system from *implying* it already has that rigor.
- It does not touch the Model Evaluation page's overall design, only adds one guard clause — a full redesign isn't needed once Phase 3 closes the schema gap.

## Tracking

| # | Finding | Severity | Phase | Status |
|---|---|---|---|---|
| 1 | Live default admin credential | CRIT | 0 | [ ] |
| 2 | `SESSION_SECURE_COOKIE=false` under production | MOD | 0 | [ ] |
| 3 | `canManagePost` missing ownership check | MOD | 1 | [ ] |
| 4 | Web register route unthrottled | MOD | 1 | [ ] |
| 5 | No error boundary around heatmap ML call | MOD | 1 | [ ] |
| 6 | `artisan test` false-failure readout | MIN | 1 | [ ] |
| 7 | No unique constraints on production/climate rows | MIN | 1 | [ ] |
| 8 | Dead Flask service + stale docstring | MOD | 1 | [ ] |
| 9 | Model Evaluation page hides DEPLOYED/TARGET schema mismatch | CRIT | 2 | [ ] |
| 10 | Fabricated "confidence" presented as model output | MOD | 2 | [ ] |
| 11 | Weather-label thresholds duplicated, no sync check | MOD | 2 | [ ] |
| 12 | Heat map's `BARANGAY_EXPOSURE` values are invented, uncited | MOD | 2 (caveat) / 3 (real fix) | [ ] |
| 13 | Deployed models have no traceable training history | CRIT | 3 | [ ] |
| 14 | Two incompatible feature schemas | CRIT | 3 | [ ] |
| 15 | Inconsistent input validation between serving paths | MOD | 4 | [ ] |
| 16 | Zero Python automated tests | MOD | 4 | [ ] |
| 17 | Monthly weather RF has no validation | MOD | 4 | [ ] |
| 18 | Barangay data integrity (free text, fuzzy-matched) | MOD | 4 | [ ] |
| 19 | `area_hectares` as predictor of area-normalized target | MIN | 4 | [ ] |
| 20 | `ReportController` payload unvalidated | MIN | 4 | [ ] |
| 21 | Uncited literature/thresholds | MIN | 4 | [ ] |
