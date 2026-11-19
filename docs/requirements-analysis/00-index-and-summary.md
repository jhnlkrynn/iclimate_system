# iClimate System: Requirements Analysis (Documents 1 to 9)

**System:** iClimate, a web-based climate and rice production decision-support system for the Municipal Agriculture Office (MAO) of Lian, Batangas.
**Date of analysis:** 13 November 2026
**Basis of analysis:** source code at commit `c34c6e5b` on branch `main`, including the uncommitted working-tree changes present on that date.

## Method

This analysis was conducted as a read-only inspection of the implementation. No system code, configuration, or database was modified. Findings were derived from the following artefacts:

| Artefact | Location | Used to establish |
|---|---|---|
| Route definitions | `routes/web.php`, `auth.php`, `manuscript.php`, `panel.php`, `api.php`, `channels.php`, `console.php` | Modules, actors, permissions, rate limits, scheduled processes |
| Controllers and middleware | `app/Http/` | Inputs, validation, access control |
| Services, jobs, commands | `app/Services/`, `app/Jobs/`, `app/Console/Commands/` | Processing logic |
| Migrations and models | `database/migrations/` (100 files), `app/Models/` (60 models) | Data structures |
| Python service | `python_scripts/` | Machine learning components |
| Configuration | `config/`, `.env.example` | Operating parameters, integrations |
| Automated tests | `tests/` (156 feature test files, unit, browser) | Evidence of verification |

A statement in these documents is one of three kinds, and each is marked where the distinction matters:

- **Implemented**: the code path exists and was read.
- **Incomplete or missing**: the code path is absent, disabled, or does not deliver the stated function.
- **Unverified**: the code path exists, but its correctness or accuracy was not demonstrated by this analysis or by evidence in the repository.

The system was not executed during this analysis. Runtime behaviour is therefore reported from the code, not from observation.

## Documents

| No. | Document | Content |
|---|---|---|
| 1 | [System Overview](01-system-overview.md) | Purpose, objectives, scope, limitations, architecture |
| 2 | [User Roles and Modules](02-user-roles-and-modules.md) | Actors, permissions, access matrix, interactions |
| 3 | [System Flows](03-system-flows.md) | Actor → Input → Process → Database → Output for every module |
| 4 | [System Diagrams](04-system-diagrams.md) | Context diagram, DFD Levels 0 and 1, use case diagrams, flowcharts, ERD (Mermaid) |
| 5 | [Functional Requirements](05-functional-requirements.md) | Requirement tables per module |
| 6 | [Non-Functional Requirements](06-non-functional-requirements.md) | Security, usability, performance, reliability, maintainability |
| 7 | [IPO Model](07-ipo-model.md) | Inputs, processes, and outputs per module |
| 8 | [Requirements Traceability Matrix](08-requirements-traceability.md) | Requirement → route → code → table → test |
| 9 | [Gaps and Unverified Features](09-gaps-and-unverified-features.md) | Missing, incomplete, and unverified features |

A second, single-file analysis of the same system (`iclimate-requirements-analysis.md` and `requirements-traceability-matrix.md`) is in this directory. It was produced separately and uses its own requirement identifiers; the two sets have not been reconciled.

## Summary: How iClimate Operates from Start to Finish

1. **Data acquisition.** The Laravel scheduler runs continuously on the server. Every ten minutes it requests the current weather and a daily forecast of up to sixteen days for the nineteen barangays of Lian from Open-Meteo in a single batched request and stores the result in `external_weather_data`. Every fifteen minutes it retrieves the PAGASA bulletins and stores the parsed advisories. Historical monthly climate data from the PAGASA Ambulong station and the MAO rice production records are entered or imported by MAO Personnel and stored in `climate_records`, `rice_productions`, and `historical_rice_productions`.

2. **Risk rating.** After each weather refresh, a rule-based service rates each barangay as Low, Moderate, High, or Severe from the stored daily forecast (heavy rain, strong wind, heat, absence of rain in rainfed barangays, and any active typhoon advisory). The rating is displayed on the Heat Map.

3. **Forecasting and prediction.** Each night a Random Forest implemented in PHP generates monthly weather forecasts for a twelve-month horizon from the climate history and stores them in `monthly_weather_predictions`. Every three hours a command sends each farm's inputs to the Python FastAPI service, which runs the deployed scikit-learn Random Forest rice-yield model, and stores the estimates in `yield_predictions`. Pages never train or run a model during a request; they read these stored results.

4. **Advisory production.** A rule engine and a Python analytics task compare the stored forecast with advisory rules and thresholds and create planting advisories that await review. MAO Personnel review, approve, publish, or reject them. MAO Personnel may also send an alert immediately or at a scheduled time. Publication creates an in-system notification for each affected farmer.

5. **Farmer participation.** A farmer registers through the landing page, passes a CAPTCHA, and waits for approval by MAO Personnel. Once approved, the farmer records farms, planting schedules, and actual harvests; reads the dashboard, forecast, Heat Map, and advisories in English or Filipino; requests a yield prediction; answers surveys on forecast accuracy; and exchanges messages with staff.

6. **Monitoring and decision support.** MAO Personnel view a dashboard of farmers, planted area, expected yield, and high-risk barangays within their assigned barangays; validate records; analyse the relationship between climate and yield; run yield forecasts; and generate reports in CSV, Excel, PDF, or print form.

7. **Administration.** IT Personnel manage accounts, roles and permissions, system and security settings, backups, maintenance records, the model registry, and model evaluation, and they monitor system health and audit logs.

8. **Real-time delivery.** Changes to weather, risk, predictions, notifications, messages, and dashboards are broadcast over WebSocket channels (Laravel Reverb) when broadcasting is configured; otherwise pages obtain the same data by polling.

The principal qualification to this description is stated in full in [Document 9](09-gaps-and-unverified-features.md): the system operates end to end, but **the accuracy of the deployed rice-yield model, the monthly weather forecast, and the composite risk score has not been verified**, and several data sets on which the analytics depend contain seeded demonstration rows.
