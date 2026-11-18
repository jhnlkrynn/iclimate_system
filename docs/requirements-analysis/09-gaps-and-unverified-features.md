# 9. Missing, Incomplete, and Unverified Features

This register lists the differences between what the system is described as doing and what the implementation was found to do. Items are grouped by kind and ordered by their consequence for the study. Each item states the evidence and its implication for the manuscript.

Two sources of evidence are used and are distinguished:

- **Code reading (this analysis, 13 November 2026).** The item can be confirmed by opening the file named.
- **Project audit record.** The item was established by an earlier read-only audit of the project's data and models (12 November 2026) and by documents in `docs/`. It was not re-measured in this analysis and is reported as recorded.

## 9.1 Unverified Features (the function runs; its correctness is not established)

| No. | Feature | Finding | Evidence | Implication |
|---|---|---|---|---|
| U1 | Deployed rice-yield model (`rice_yield_model_final.pkl`) | The model receives inputs whose definitions differ from those of its training data: farm area of about one hectare where the training range was municipal area of 12 to 258 hectares; a six-month rainfall sum where training used a mean; and a temperature range computed differently. Outputs fall within 4.11 to 4.18 t/ha for every farm. | Project audit record; `PredictionEngine::buildModelInput`; only `Area` varies per farm among twelve features | The predicted yield carries little information. The manuscript should not describe the model as accurate or validated. Correction requires the original training data, which is not in the repository, or a retrained model approved by the researchers. |
| U2 | Accuracy of the yield model | No verified accuracy figure exists for the deployed model. The candidate model evaluated on the 2025 hold-out gave a negative R². The hold-out tests assert only that metrics are non-negative numbers and cover the candidate, not the deployed model. | Project audit record; `docs/chapter1-scope-and-limitations.md`; `tests/Feature/MachineLearning/*Holdout*` | Report RMSE, MAE, and R² only for a model and data set that the researchers have verified. The figure "MAPE 12.2 %" in `docs/panel-revisions.md` is largely in-sample (52 of 60 months were training months). |
| U3 | Monthly weather forecast (`MonthlyWeatherRandomForest`) | A year-ahead backtest for 2024 to 2025 performed worse than same-month climatology (rainfall MAE 119 mm against 89 mm). | Project audit record | Present the monthly forecast as experimental, or compare it against climatology in the evaluation chapter. |
| U4 | Composite risk score (`AgriculturalRiskScorer`) | The barangay exposure values and weights are stated in the code as engineering-judgment placeholders that require methodology validation. | Class comment in `app/Services/Risk/AgriculturalRiskScorer.php` | Describe the score as provisional. The weekly four-level rating (U5) is a separate mechanism. |
| U5 | Weekly risk thresholds | The thresholds are working defaults that "must be confirmed by the Municipal Agriculture Office". | `config/weather_risk.php`; `BarangayWeekRiskService` class comment | Obtain and cite MAO confirmation, or state the thresholds as assumptions with their sources (PAGASA rainfall and wind classifications). |
| U6 | Rice production data | Of 124 production rows in the active database, 110 are allocations of eleven municipal season totals across barangays by a demonstration seeder, and 14 are demonstration rows. None is validated. | Project audit record; `database/seeders/FarmerActivityDemoSeeder.php` | The independent sample is eleven municipal seasons. Barangay-level production analyses and evaluation metrics computed from these rows do not represent observations. |
| U7 | Predicted-versus-actual comparison from farmers | All harvested planting schedules in the active database are seeded, with the predicted value set at a fixed offset from the actual value. | Project audit record; `Support/YieldCorrections` | The displayed average miss is an artefact of the seeder. Do not report it as a result. |
| U8 | Heat Map yield input | The "last recorded yield" input to the composite score reads demonstration rows for eleven barangays. | Project audit record; `BarangayRiskMapService::latestYieldsByBarangay` | Same as U6. |
| U9 | Weather Impact Analysis | The correlation is computed from sixty monthly pairs at most; no significance test is applied. | `WeatherAnalysisService::impact` | State the sample size and treat impact levels as descriptive. |
| U10 | Automated test suite | Thirty-seven PHPUnit tests failed as of 17 October 2026, attributed to interface changes not reflected in the tests. The suite was not re-run in this analysis. | `docs/known-failing-tests.md` | Re-run the suite and report the current result before citing test coverage as verification. |
| U11 | Real-time delivery | Broadcasting is off by default (`BROADCAST_CONNECTION=log` or `null`). Project records state that it was verified on 11 November 2026 on the development installation. | `.env.example`; `docs/realtime-status.md` | Confirm the configuration of the installation used for the defence. |
| U12 | Mobile responsiveness and contrast | Stated in project documents; not re-measured here. | `docs/panel-revisions.md` | Acceptable to cite with the project's own test evidence. |
| U13 | Usability | No usability evaluation results are in the repository; the earlier survey pages are retired. | `routes/web.php` (legacy redirect); table `usability_survey_responses` | If the manuscript reports a usability evaluation (for example ISO/IEC 25010 or SUS), its instrument and data must come from outside the system. |
| U14 | API route `GET /api/heat-map` | The route applies `auth` within the API middleware group. Whether the session is available to that group was not confirmed by execution. | `routes/api.php` | Confirm at runtime; `HeatMapApiTest` exists. |

## 9.2 Missing Features (no implementation)

| No. | Feature | Evidence | Implication |
|---|---|---|---|
| M1 | E-mail verification | `User` does not implement `MustVerifyEmail` (the import is commented out); the `verified` middleware therefore admits every signed-in user. | Stated correctly as a limitation in the scope document. |
| M2 | SMS and mobile push notification | No SMS or push provider is referenced in the code. | Stated correctly as a limitation. |
| M3 | Native mobile application | No client code is in the repository. | Stated correctly as a limitation. |
| M4 | IoT or sensor integration | No device interface exists. | Stated correctly as a limitation. |
| M5 | Off-server backup storage | Backups are written to a local directory. | Add to limitations if not already stated. |
| M6 | Retraining of the deployed production model | The training script and data for `rice_yield_model_final.pkl` are absent. Retraining produces a new candidate only. | Stated correctly in the scope document. |
| M7 | Load or stress testing | No load test or response-time target was found. | Do not claim performance under concurrent load. |
| M8 | Multi-municipality or multi-crop support | Place and crop are fixed in code. | Stated correctly as a limitation. |

## 9.3 Incomplete Features (partly implemented)

| No. | Feature | Finding | Evidence |
|---|---|---|---|
| I1 | Mobile API | `POST /api/mobile/login` returns the user record but issues no access token, so no later request can be authenticated. Mobile registration requires no CAPTCHA. | `app/Http/Controllers/Api/MobileAuthController.php`; no token library in `composer.json` |
| I2 | Password reset by e-mail | Functional only when an SMTP server is configured; the default mailer writes to the log, and the page then directs users to the MAO or IT. | `.env.example` (`MAIL_MAILER=log`) |
| I3 | Sample-data exclusion | `SampleDataDetector` recognises placeholder text only; rows marked `[demo_seed]` are not excluded from statistics. | Project audit record; `app/Support/SampleDataDetector.php` |
| I4 | Backup restoration | The web restore supports MySQL/MariaDB SQL dumps only; PostgreSQL archives are not restored from the page. | `app/Services/DatabaseRestoreService.php` |
| I5 | First-password enforcement | "Skip for now" is permitted by default, and the default MAO password is a fixed known value. | `config/iclimate.php` (`mao_allow_skip_password_change`, `mao_default_password`) |
| I6 | Content Security Policy | The policy permits inline and evaluated scripts. | `app/Http/Middleware/SecurityHeaders.php` |
| I7 | Automatic advisory generation by the Python task | Requires a running queue worker; the scheduled command skips the round when none is alive. Its thresholds (rainfall 70, wind 25, temperature 34) are fixed in the script and differ from the rule set in `advisory_rules` and from `config/weather_risk.php`. | `app/Console/Commands/RunIClimateAutomation.php`; `python_scripts/analytics_tasks.py::advisory_ai` |
| I8 | Season definition | The MAO planting summary defines the dry season as October to March under the starting year; `RiceSeason` defines the wet season as May to October. | Project audit record; `app/Support/RiceSeason.php` |
| I9 | Database engine | The configured default is MySQL/MariaDB; migration from PostgreSQL (Supabase) was in progress and its decommissioning was not approved as of 10 November 2026. | `.env.example`; `docs/SUPABASE_DECOMMISSION_RUNBOOK_2026-11-10.md` |
| I10 | Timestamp handling for raw updates | Eloquent models guard database clock columns; raw `DB::table()->update()` calls are not guarded. | `app/Models/Concerns/KeepsDatabaseClockColumns.php` |
| I11 | Referential integrity by barangay | Several tables reference a barangay by name and not by foreign key. | Migrations for `farms`, `rice_productions`, `heatmap_areas`, `planting_advisories` |

## 9.4 Hidden and Retired Features

| No. | Feature | State | Control |
|---|---|---|---|
| H1 | Field Reports | Code, table, and tests retained; pages hidden | `FIELD_REPORTS_UI` |
| H2 | Chatbot Knowledge management | Pages hidden; the knowledge is always used | `CHATBOT_KNOWLEDGE_UI` |
| H3 | Candidate model and season retraining section | Hidden | `MODEL_EVALUATION_CANDIDATE_UI` |
| H4 | Farm boundary polygon drawing | Removed from farmer pages in October 2026; routes redirect | `farmer.redirect` middleware |
| H5 | Live Forecasting, Usability Survey, Farmer Organizations, MAO Operations, candidate test page, prediction forms | Retired; URLs redirect | `routes/web.php` |

## 9.5 Discrepancies Between Project Documents and the Implementation

| No. | Statement in project documents | Implementation | Recommendation |
|---|---|---|---|
| D1 | IT Personnel "generate and export Reports" (`docs/chapter1-scope-and-limitations.md`, IT item 3) | `RedirectItFromFieldPages` sends IT accounts from `reports.*` to the IT dashboard | Remove the item from the manuscript or remove `reports.*` from the redirection list |
| D2 | Farmer may "draw the farm boundary on a map" (scope, Farmer item 8) | Boundary drawing was removed; a map location may be saved | Revise the wording to "mark the farm location on a map" |
| D3 | The model uses "rainfall, temperature, temperature range, lagged and seasonal rainfall and temperature, farm area, and season" | Correct as a list of feature names; the values supplied differ in definition from training (U1) | Add the qualification in U1 |
| D4 | "Planting advisories will be generated from a set of advisory rules" | Two generators exist with different thresholds (I7) | Describe both, or consolidate |
| D5 | "The database will be MySQL/MariaDB [CONFIRM]" | Default configuration is MySQL/MariaDB; PostgreSQL code paths remain | State MySQL/MariaDB as the database of the study |
| D6 | The request for this analysis names an "Admin" user | No `Admin` role exists. The administrator is the role `IT Expert`; the Municipal Agriculturist is an MAO account with `is_mao_head` | Use the three stored roles and the head-of-office attribute consistently |

## 9.6 Items Requiring Confirmation by the Researchers

1. The deployment setting of the study (local server at the MAO, or an online host). Project records indicate that the system ran on a local installation as of 10 November 2026.
2. The data years and the source of the yield records used for modelling.
3. Whether the MAO has validated the risk thresholds.
4. The browsers supported.
5. Whether any hidden feature will be part of the final system.
6. Which model, data set, and metrics will be reported as the evaluation of the study.

## 9.7 Limits of This Analysis

1. The system was not executed. Runtime behaviour is reported from the code.
2. Not every controller method was read line by line. Where a field list is given in Document 3, it was taken from validation rules where these were read and otherwise from the table columns.
3. The mapping of tests to requirements in Document 8 is by the subject of each test file. The tests were not run.
4. The working tree contained uncommitted changes on the date of analysis; the findings describe that state.
5. The twenty-one Mermaid diagrams in Document 4 were rendered with Mermaid 10.9.1 in a headless browser and produced no syntax errors. Their layout was not inspected visually.
