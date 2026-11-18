# Phase 13 — Capstone Demo Readiness

Date: 2026-11-13  
Scope: local iClimate demonstration environment  
Scientific status: **ACCURACY NOT VERIFIED**

## Executive assessment

iClimate is suitable for a functional capstone demonstration with explicit
limitations. The existing Laravel application, deployed Python service, model
artifact, database, queue, scheduler, and Reverb service were checked without
changing predictions, models, formulas, records, or services. The live ML
service loaded the deployed rice model and returned a finite example estimate.

This does **not** establish scientific accuracy. The original training data,
preprocessing code, complete semantic feature dictionary, and independent
verified yield observations remain unavailable. Demonstrate estimates as
examples and retain the existing **Unvalidated estimate / accuracy not
verified** wording.

Final assessment: **GO for a supervised functional demo; NO-GO for claiming
validated accuracy, model superiority, or research approval.**

## Working features and verified path

The production path is:

1. Laravel validates the farmer or MAO request and queues the request.
2. `PredictionEngine` obtains the prepared climate forecast and builds the
   model input without replacing missing values with zeroes.
3. `PythonService` posts `prediction_type=rice_yield` and the feature payload
   to FastAPI `/predict`.
4. FastAPI loads `storage/models/rice_yield_model_final.pkl`, maps the 12
   deployed fields, and returns the estimate.
5. Laravel accepts only a finite numeric result. Missing data or service
   failure produces an unavailable/failed state rather than a fabricated
   prediction.
6. Farmer, MAO, report, survey, calendar, chat, and heat-map surfaces retain
   the existing unvalidated-estimate safeguards.

The deployed feature names and order are confirmed identical across the PHP
map and Python model frame:

| # | Production input | Model column | Runtime type | Semantic unit status |
|---:|---|---|---|---|
| 1 | `rainfall` | `RAINFALL` | finite float | application treats as climate rainfall; original training unit not evidenced |
| 2 | `temp_avg` | `TEMP_AVG` | finite float | application treats as temperature; original training unit not evidenced |
| 3 | `temp_range` | `TEMP_RANGE` | finite float | derived range; original preprocessing not evidenced |
| 4 | `area` | `Area` | finite float | hectares in current application; training scale not evidenced |
| 5 | `previous_rainfall` | `Previous_Rainfall` | finite float | trailing climate value; source/scale not evidenced |
| 6 | `previous_temp` | `Previous_Temp` | finite float | trailing climate value; source/scale not evidenced |
| 7 | `rainfall_6m` | `Rainfall_6M` | finite float | six-month application aggregate; original window not evidenced |
| 8 | `temp_3m` | `Temp_3M` | finite float | three-month application aggregate; original window not evidenced |
| 9 | `temp_6m` | `Temp_6M` | finite float | six-month application aggregate; original window not evidenced |
| 10 | `seasonal_rainfall` | `Seasonal_Rainfall` | finite float | current same-season aggregate; original definition not evidenced |
| 11 | `seasonal_temp` | `Seasonal_Temp` | finite float | current same-season aggregate; original definition not evidenced |
| 12 | `season` | `Season` | string | current Wet/Dry encoding; original encoding evidence not supplied |

The weather companion model additionally requires previous humidity, previous
wind speed, and month number. A complete request containing these fields was
accepted by both the in-process service and the live endpoint. The successful
smoke input was an example fixture only, not research evidence.

## ML verification results

- Model loaded: **PASS** — rice and weather models loaded.
- Model file: `storage/models/rice_yield_model_final.pkl`.
- SHA-256: `f3a54e12ecf85e66340475e2c49ce5c15d9c58efd82213435015a275734b777c`.
- Live `/health`: **PASS**, both models reported loaded.
- Live `/model-info`: **PASS**, deployed filename and checksum reported.
- Live `/predict`: **PASS** for a complete example request; returned a finite
  `4.01` tons/hectare example estimate.
- Feature compatibility: **PASS for names/order and runtime conversion**;
  **NOT VERIFIED for original units, transformations, target scope, or
  training semantics**.
- Accuracy: **ACCURACY NOT VERIFIED**. No metric from the smoke request is an
  accuracy result.
- The API also returns a weather/decision-support `confidence_score`. It is
  not a calibrated rice-yield accuracy probability and must not be quoted as
  model accuracy or displayed as a yield-confidence percentage.

## Research evidence still missing

Phase 12 inventory remains authoritative. The following are still required:

- original rice-yield training dataset and observation identifiers;
- original training and preprocessing scripts;
- complete 12-feature dictionary, units, encodings, aggregation windows,
  missing-value rules, target definition, and prediction scope;
- original train/test or cross-validation protocol and random seeds;
- approved MAO production records with source documents;
- planting and harvest dates or season identifiers;
- source-backed historical climate observations aligned to the crop seasons;
- independent yield observations not used for training, tuning, or derived
  municipal allocation; and
- adviser/researcher approval of farm-level versus municipal target and the
  evaluation acceptance criteria.

The Ambulong station file remains a proxy climate source and is not evidence
that Lian-specific weather was observed. Demo, replicated, derived, and
unverified records must not be presented as independent validation data.

## Critical workflows

The full Laravel regression suite and focused prediction tests pass, covering
the role-gated dashboards, reports, prediction requests, labels, and service
contracts. The local service supervisor reports healthy Admin/MAO/Farmer
supporting infrastructure. A browser-by-browser walkthrough was not claimed as
an automated test; use the sequence below for the live adviser demo.

The following remain safe to demonstrate:

- Admin: dashboard, user management, reports, system health.
- MAO: farmer/farm records, schedules, climate information, reports, and a
  supervised yield-estimate request.
- Farmer: dashboard, farm records, schedules, prediction history, and
  advisories.

If the model or required climate input is unavailable, show the unavailable
state and explain that the system refuses to invent a result.

## Exact macOS commands

From the repository root:

```bash
cd /Users/johnlukereynon/iclimate_system
bash scripts/start-iclimate-local.sh check
bash scripts/start-iclimate-local.sh status
open http://127.0.0.1:8000
```

The project launcher starts the ML service on `127.0.0.1:8010`, Laravel on
port `8000`, and the configured queue, scheduler, and Reverb services. If the
stack is not running, use the existing launcher:

```bash
bash scripts/start-iclimate-local.sh start
bash scripts/start-iclimate-local.sh status
```

Do not run `restart` during the demo unless necessary. The queue can process
existing database jobs, so inspect status before starting or stopping it.

## Recommended demonstration sequence

1. Run `status` and show all required services healthy.
2. Open the Admin system-health view and explain that model health is separate
   from scientific accuracy.
3. Show the MAO climate and records context.
4. Submit one clearly labelled example yield request with a documented example
   input.
5. Show the completed estimate together with **Unvalidated estimate** and
   **Accuracy not verified**.
6. Open the Farmer view and show the same safeguards on prediction history and
   advisories.
7. Show the ML readiness result and explain why it remains blocked.
8. Do not present candidate-data metrics, smoke outputs, or weather confidence
   as rice-yield accuracy.

## Likely adviser questions and accurate answers

**Does the model work?** The inference integration works: the deployed model
loads and returns a finite output for a complete input. Scientific predictive
accuracy is not verified.

**What is the model's accuracy?** We cannot honestly state one yet. The
original training package and independent approved yield observations are
missing, so the readiness gate remains `ACCURACY NOT VERIFIED`.

**Why show an estimate at all?** It demonstrates the existing technical
workflow. It is explicitly labelled unvalidated and must not be used as proof
of farm-level performance.

**What does the confidence number mean?** Any confidence value returned by the
weather/decision-support path is a heuristic operational score, not a
calibrated rice-yield accuracy probability. It is not a validation metric.

**What data is needed next?** The original feature/preprocessing/training
package, source-backed MAO production and date records, aligned climate data,
independent yield observations, and approved chronological or season-grouped
evaluation protocol.

**Can the model be retrained now?** No. Retraining or replacement requires
researcher and adviser approval plus authentic evidence and a reproducible
evaluation that beats appropriate baselines on independent groups.

## Emergency recovery

Use only the existing project-owned commands:

```bash
cd /Users/johnlukereynon/iclimate_system
bash scripts/start-iclimate-local.sh status
bash scripts/start-iclimate-local.sh check
bash scripts/start-iclimate-local.sh ml
bash scripts/start-iclimate-local.sh web
bash scripts/start-iclimate-local.sh queue
bash scripts/start-iclimate-local.sh scheduler
bash scripts/start-iclimate-local.sh reverb
```

Run a foreground service command only after `status` confirms that service is
not already healthy. Do not clear failed jobs, reset the database, delete
models, edit `.env` secrets, or run migrations as a demo recovery step. The
read-only diagnostics are:

```bash
php artisan iclimate:environment-check
php artisan iclimate:ml-readiness --target-date=2026-11-13
curl -fsS http://127.0.0.1:8010/health
curl -fsS http://127.0.0.1:8010/model-info
```

## Test and integrity results

Executed during Phase 13:

- Targeted Python ML validation/readiness tests: **25 passed**.
- Full Python suite: **40 passed, 2 existing deprecation warnings**.
- Focused Laravel prediction/readiness tests: **28 passed, 273 assertions**.
- Full Laravel suite: **1,784 passed, 5 skipped, 13,821 assertions**.
- `php artisan iclimate:ml-readiness --target-date=2026-11-13`: completed
  read-only; accuracy remained `ACCURACY NOT VERIFIED`.
- `php artisan iclimate:environment-check`: **PASS**, MariaDB 10.4.28,
  69 tables, 87 foreign keys, zero pending migrations.
- `bash scripts/start-iclimate-local.sh check`: **PASS**.
- `bash scripts/start-iclimate-local.sh status`: **PASS** for ML, web,
  Reverb, queue, and scheduler; zero waiting jobs. Fifteen historical failed
  jobs were reported and were not modified.
- Live ML `/health`, `/model-info`, and complete example `/predict` smoke:
  **PASS**.
- `bash -n scripts/start-iclimate-local.sh scripts/local-service-supervisor.sh`:
  **PASS**.
- `git diff --check`: **PASS**.

No production predictions, database records, trained models, formulas, or
running services were modified by Phase 13. No source code fix was necessary
after the live integration checks; only this readiness report was added.

## Next actionable phase

Obtain the missing original-model and approved research evidence package. Then
run the existing intake and readiness gates in an isolated workspace, obtain
researcher/adviser sign-off on target and split methodology, and only then
execute chronological/season-grouped evaluation against mean and seasonal
baselines. Keep the deployed model unchanged until that review is complete.
