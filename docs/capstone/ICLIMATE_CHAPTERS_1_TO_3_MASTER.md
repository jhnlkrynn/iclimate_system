# iClimate Chapters I to III Master Manuscript

Project Title: iClimate: A Web-Based Weather Impact Analysis and Rice Yield Prediction System for Lian, Batangas

Generated: 2026-08-19

Source Basis: Actual iClimate source code in `C:\xampp\htdocs\iclimate_system`, existing phase documents in `docs/`, migrations, models, controllers, services, views, tests, Python scripts, dependency files, and local document-generation tool checks.

Document Integrity Note: This Markdown file is the master source for the generated DOCX and PDF. The DOCX remains editable. The PDF was rendered from the same master content rather than saved as plain text with a `.pdf` extension.

Verification Markers:

- `[RESEARCHER INPUT REQUIRED]` marks missing local research, evaluation, participant, or institutional information.
- `[LITERATURE SOURCE REQUIRED]` marks claims that require external scholarly or official sources.
- `[TECHNICAL VERIFICATION REQUIRED]` marks technical details that were not fully verifiable from the current repository.

Generation Tool Check:

| Tool / Method | Local Availability | Result |
|---|---|---|
| Pandoc | Not installed on PATH | Missing dependency. Not used. |
| LaTeX / XeLaTeX / pdfLaTeX | Not installed on PATH | Missing dependency. Not used. |
| Python DOCX/PDF packages | Current Python found, but `python-docx`, `reportlab`, `markdown`, and `weasyprint` were not installed | Missing dependency. Not used. |
| HTML-to-PDF renderer | Project-local `dompdf/dompdf` v3.1.6 and `barryvdh/laravel-dompdf` v3.1.2 are installed through Composer | Used for PDF rendering. |
| Editable DOCX generator | PHP Zip/XML extensions are available | Used to generate an editable OOXML DOCX from the same Markdown source. |
| Visual PDF/DOCX render QA | LibreOffice, Poppler `pdfinfo`, and Poppler `pdftoppm` were not installed on PATH | PNG visual QA could not be completed locally. |

# CHAPTER I - THE PROBLEM AND ITS BACKGROUND

## Introduction

Rice production is exposed to climate and weather uncertainty because planting, irrigation, crop growth, pest and disease risk, and harvesting decisions are affected by rainfall, temperature, humidity, wind speed, seasonal timing, drought, heavy rain, and typhoon conditions. In the iClimate system, this problem is addressed through a localized web-based platform for Lian, Batangas that combines climate records, rice production records, weather forecasts, rice yield prediction, advisory generation, risk mapping, reporting, notifications, and user management.

The verified system implementation is a Laravel-based decision-support application. It contains role-based dashboards for Farmer, MAO Personnel, and IT Expert users; climate record management; rice production management; rice yield prediction; weather prediction; planting and agricultural advisories; Open-Meteo forecast storage; PAGASA online advisory filtering; heat map visualization; reports; in-app notifications; community feed; direct messaging; calendar; AI farming assistant; typhoon safety responses; model evaluation; system logs; and mobile authentication API endpoints.

This integration is significant because weather and production data are not treated as isolated records. In iClimate, weather observations and forecasts support dashboard summaries, advisory rules, live forecasting, prediction inputs, heat map risk scoring, and report generation. Rice production records support yield summaries, analytics, model-evaluation sample construction, and fallback yield estimates when the trained model is unavailable. The system therefore supports climate-informed decision-making for farmers and agricultural personnel in Lian, Batangas.

Verified from System:

- Laravel routes expose dashboards, CRUD modules, prediction endpoints, reports, notifications, heat maps, advisories, AI chat, messaging, and community feed through `routes/web.php`.
- API routes expose Lian boundaries, heat map data, Lian weather data, and mobile login/register through `routes/api.php`.
- Climate variables are stored in `climate_records`: rainfall, temperature, humidity, wind speed, season, source, and record date.
- Rice production variables are stored in `rice_productions`: barangay, season, irrigation type, yield per hectare, area in hectares, total production, year, and remarks.
- Rice yield prediction uses `RiceYieldPredictionService`, `PredictionEngine`, `PythonService`, and `python_scripts/predict.py`.
- The deployed rice yield model file is `storage/models/rice_yield_model_final.pkl`; a weather model file also exists at `storage/models/weather_model.pkl`.

Requires External Literature:

- Weather and climate effects on rice production in the Philippines. `[LITERATURE SOURCE REQUIRED]`
- Climate-smart agriculture and agricultural decision-support systems. `[LITERATURE SOURCE REQUIRED]`
- Machine-learning crop-yield prediction using Multiple Linear Regression, Random Forest, and Gradient Boosting. `[LITERATURE SOURCE REQUIRED]`
- Evaluation models for usability, reliability, efficiency, response time, and predictive accuracy. `[LITERATURE SOURCE REQUIRED]`

Requires Local/MAO Evidence:

- Official profile of Lian, Batangas rice farming conditions. `[RESEARCHER INPUT REQUIRED]`
- MAO confirmation of climate and rice production datasets. `[RESEARCHER INPUT REQUIRED]`
- Local interviews, problem statements, and stakeholder validation. `[RESEARCHER INPUT REQUIRED]`

## Background and Settings of the Study

The study setting is Lian, Batangas. The repository repeatedly identifies Lian, Batangas as the target location through the project title, landing page, weather service configuration, PAGASA advisory filtering, Open-Meteo location defaults, local Lian barangay support classes, and `public/geojson/lian-barangays.geojson`.

The system is intended for rice farmers, MAO personnel, agricultural technicians as stakeholders, and IT experts. In the executable code, the actual role names are limited to three coded roles: `Farmer`, `MAO Personnel`, and `IT Expert`. Agricultural technicians are not implemented as a separate coded role; they should be discussed as MAO-related agricultural stakeholders unless the researchers provide a separate role implementation.

The data setting includes stored climate records, stored rice production records, external forecast data, and advisory sources. The climate records table uses `PAGASA Historical Climate Record` as its default source. PAGASA online advisory pages are fetched and filtered through `PagasaAdvisoryService`. Open-Meteo provides forecast data for online advisory generation and dashboard support. The final manuscript must distinguish historical PAGASA climate records from runtime Open-Meteo forecast data and PAGASA online advisory pages.

The repository verifies database structures and application workflows, but it does not contain the original PAGASA historical dataset files, MAO rice-production source files, survey instruments, respondent records, or complete model-training notebooks. These must be supplied by the researchers before final defense or appendices.

## Objectives of the Study

### General Objective

The main goal of this study will be to develop iClimate: A Web-Based Weather Impact Analysis and Rice Yield Prediction System for Lian, Batangas, with integrated weather analysis, rice yield prediction, planting advisory, climate monitoring, and advisory notification modules to support climate-informed agricultural decision-making.

### Specific Objectives

This study specifically aims to:

1. To collect, preprocess, and analyze historical climate data from PAGASA, including rainfall, temperature, humidity, and wind speed, together with local rice production records from the Municipal Agricultural Office (MAO) in Lian, Batangas, from 2020 to 2025.

2. To compare and evaluate Multiple Linear Regression, Random Forest, and Gradient Boosting algorithms in forecasting seasonal rice yield per hectare under varying conditions, such as wet/dry seasons and irrigated/rainfed farming systems, and determine the best-performing algorithm using RMSE, MAE, and RÂ² evaluation metrics

3. To develop a web-based decision-support platform with Weather Analysis, Rice Yield Prediction, Planting Advisory, Climate Monitoring Dashboard, Heat Map Visualization, Reports and Analytics, Advisory Notification, and User Management functionalities for rice farmers, MAO personnel and agricultural technicians.

4. To evaluate the iClimate platform in terms of functionality, usability, reliability, efficiency, response time, and predictive accuracy through system testing and user evaluation involving rice farmers, MAO personnel, agricultural technicians, and IT experts.

Objective Alignment Matrix:

| Objective | Related System Component | Code Evidence | Dataset/Model Evidence | Status |
|---|---|---|---|---|
| SO1 | Climate records and rice production records | `ClimateRecord`, `RiceProduction`, related migrations and controllers | Climate variables and rice production fields exist; original PAGASA/MAO files not in repo | PARTIALLY IMPLEMENTED; provenance needs researcher evidence |
| SO1 | Data preprocessing and analysis | `ModelEvaluationService::samples`, `PredictionEngine::buildModelInput` | Runtime aggregation and feature construction verified; original preprocessing notebook missing | PARTIALLY IMPLEMENTED |
| SO2 | Algorithm comparison | `ModelEvaluationService`, `model-evaluation` view and tests | MLR, Random Forest, Gradient Boosting metrics hard-coded/displayed; source notebook named but absent | PARTIALLY VERIFIED |
| SO2 | Deployed prediction | `RiceYieldPredictionService`, `PredictionEngine`, `python_scripts/predict.py` | `rice_yield_model_final.pkl` exists and is used; Random Forest named as deployed model | VERIFIED FOR DEPLOYMENT |
| SO3 | Web-based platform modules | `routes/web.php`, controllers, views, models, services | Required modules exist plus supporting modules | VERIFIED WITH SOME ROLE-NAME GAP |
| SO4 | Automated system testing | `tests/Feature`, `tests/Unit`, current `php artisan test --compact` | Current run: 137 passed, 14 failed, 679 assertions | PARTIALLY VERIFIED; failing tests must be addressed or documented |
| SO4 | User evaluation | No survey/result files found | Participant counts, usability scores, response-time measurements absent | [RESEARCHER INPUT REQUIRED] |

## Significance of the Study

Rice Farmers. The system benefits farmers by providing role-specific dashboard summaries, weather and climate guidance, rice yield prediction, planting advisories, in-app notifications, heat map risk information, community updates, messaging, calendar items, and AI-assisted farming answers. Supports SO3 and SO4.

MAO Personnel. The system benefits MAO personnel by supporting management of farmer profiles, climate records, rice production records, advisories, reports, heat maps, community communication, notifications, and typhoon safety responses. Supports SO1, SO3, and SO4.

Agricultural Technicians. The study names agricultural technicians as intended evaluators/stakeholders. The current code does not implement a separate Agricultural Technician role, so their operational responsibilities must be provided by the researchers or described under MAO workflows. Supports SO3 and SO4. `[RESEARCHER INPUT REQUIRED]`

IT Experts / System Administrators. The system benefits IT Experts by providing user management, system logs, model evaluation access, administrative dashboard data, role authorization oversight, and system maintenance visibility. Supports SO3 and SO4.

Local Government / Municipal Agriculture Office. The system can help the MAO organize climate and production data, monitor barangay risk, publish advisories, and generate reports. Local institutional use and adoption status require confirmation. `[RESEARCHER INPUT REQUIRED]`

Future Researchers and Developers. The project provides a source-based example of Laravel, MySQL, Python machine-learning deployment, weather API integration, map visualization, advisory rules, and agricultural decision support. Supports SO1, SO2, and SO3.

## Scope and Limitation of the Study

Geographic Scope: The system is scoped to Lian, Batangas. Barangay names and coordinates are represented in `LianBarangays`, `LianFarmTypes`, heat map defaults, and GeoJSON.

User Scope: The actual coded roles are Farmer, MAO Personnel, and IT Expert. Agricultural technicians are mentioned by the research objective but are not a separate coded role.

Data Scope: The database stores climate records, rice production records, external weather forecast records, advisories, reports, notifications, AI chat records, community-feed records, messaging records, and system logs. Original research datasets are not included in the repository.

Climate Variables: Rainfall, temperature, humidity, wind speed, season, soil temperature, soil moisture, evapotranspiration, weather code, and precipitation probability appear in the implementation. The approved objective specifically identifies rainfall, temperature, humidity, and wind speed.

Agricultural Scope: The agricultural scope is rice production and rice farming decision support, including yield per hectare, area in hectares, total production, season, irrigation type, farm type, barangay, planting advisory, irrigation recommendation, and agricultural risk.

Prediction Scope: Rice yield prediction estimates yield in tons per hectare and estimated total production by multiplying predicted yield per hectare by farm area. Weather prediction uses a monthly Random Forest service for rainfall, temperature, humidity, and wind speed. Exact model-training procedure remains `[TECHNICAL VERIFICATION REQUIRED]`.

Functional Scope: Verified modules include authentication, dashboards, farmer profiles, climate records, rice production records, weather prediction, rice yield prediction, planting/advisory management, live forecasting, heat maps, reports, notifications, community feed, messaging, calendar, AI assistant, model evaluation, user management, system logs, mobile auth API, and typhoon safety response.

Technology Scope: Laravel 12, PHP 8.2, MySQL/MariaDB, Blade, Vite, Tailwind tooling, Bootstrap, Chart.js, Leaflet, Leaflet.heat, Python scripts, joblib, pandas, scikit-learn-compatible model loading, Open-Meteo, PAGASA online pages, optional Groq API, Composer, Node.js, and NPM appear in the project.

Model Scope: The system identifies Random Forest as the deployed selected model and displays comparison metrics for MLR, Random Forest, and Gradient Boosting. The training notebook and raw model-development evidence are not in the current repo.

Time/Data Period Scope: The approved objective requires 2020 to 2025. The database schema includes a `year` field and source labels, but source files proving complete 2020-2025 coverage were not found. `[RESEARCHER INPUT REQUIRED]`

Limitations:

- The system is geographically limited to Lian, Batangas.
- The current repository does not verify complete PAGASA and MAO source datasets from 2020 to 2025.
- The current repository does not contain the training notebook, raw preprocessing outputs, cross-validation records, or complete train/test split evidence.
- External weather functionality depends on online services such as Open-Meteo and PAGASA pages.
- Notification evidence is limited to in-app database notifications; SMS and email dispatch were not verified.
- Report exports are verified for print view and CSV, not Excel/PDF report exports.
- Current automated testing has failures in AI intent/source expectations and notification visibility.
- User evaluation instruments, participant counts, response-time results, and acceptance summaries are missing.

## Definition of Terms

iClimate. The implemented web-based system for weather impact analysis, rice yield prediction, climate monitoring, advisories, reports, notifications, risk visualization, and user management for Lian, Batangas.

Weather Analysis. The system function that uses stored climate records and external forecast services to present rainfall, temperature, humidity, wind speed, and weather-related summaries.

Climate Monitoring. The dashboard and record-management workflow for viewing climate records, recent weather data, trends, and risk-related climate information.

Rice Yield Prediction. The machine-learning workflow that estimates rice yield in tons per hectare using model features such as rainfall, temperature, season, farm area, and derived weather inputs.

Planting Advisory. An advisory displayed or managed in the system to guide farm decisions. Advisories may be manual, rule-based, Open-Meteo-driven, or PAGASA-page-derived depending on source.

PAGASA. The Philippine weather agency referenced by the system as a source for historical climate records and online advisories. Original historical files require researcher confirmation.

MAO. Municipal Agricultural Office, the local agricultural office named in the study as source/stakeholder for rice production records and agricultural decision support.

Random Forest. The selected deployed rice-yield model according to `ModelEvaluationService`, `RiceYieldPredictionService`, and `python_scripts/predict.py`.

Multiple Linear Regression. A comparison algorithm displayed by `ModelEvaluationService`; training source evidence is not included in the repository.

Gradient Boosting. A comparison algorithm displayed by `ModelEvaluationService`; training source evidence is not included in the repository.

RMSE, MAE, and RÂ². Evaluation metrics displayed by the model evaluation page for comparing MLR, Random Forest, and Gradient Boosting. Original computation source files are `[TECHNICAL VERIFICATION REQUIRED]`.

Wet Season and Dry Season. Season categories represented in climate records, rice production records, prediction normalization, and model-evaluation sample logic.

Irrigated and Rainfed. Farm or irrigation categories represented in farmer profiles, rice production records, and prediction farm-type handling.

Heat Map. A Leaflet/Leaflet.heat visualization using barangay risk data and Lian GeoJSON to show agricultural risk conditions.

Advisory Notification. An in-app database-backed notification related to advisories, warnings, or announcements.

# CHAPTER II - REVIEW OF LITERATURE AND STUDIES

## Conceptual Literature

The following conceptual topics are required because they directly support the implemented system. Actual citations must be supplied from peer-reviewed or official sources before final submission.

| Concept | Connection to iClimate | Objective Supported | Feature Supported | Literature Needed |
|---|---|---|---|---|
| Climate-smart agriculture | Frames climate-informed farm decision support | SO1, SO3 | Dashboards, advisories, risk maps | `[LITERATURE SOURCE REQUIRED]` |
| Climate variability and rice production | Explains why rainfall, temperature, humidity, and wind matter | SO1 | Climate records, yield prediction | `[LITERATURE SOURCE REQUIRED]` |
| Agricultural decision-support systems | Explains integration of data, analytics, and advisories | SO3 | Entire platform | `[LITERATURE SOURCE REQUIRED]` |
| Machine learning in crop-yield prediction | Supports algorithm comparison and yield forecasting | SO2 | Model evaluation, prediction | `[LITERATURE SOURCE REQUIRED]` |
| Seasonal rice farming | Supports wet/dry season handling | SO1, SO2 | Climate records, rice production | `[LITERATURE SOURCE REQUIRED]` |
| Irrigated and rainfed farming systems | Supports farm-type and irrigation-type variables | SO2 | Farmer profiles, prediction inputs | `[LITERATURE SOURCE REQUIRED]` |
| Agricultural advisories | Supports advisory dissemination and decision rules | SO3 | Planting advisories, notifications | `[LITERATURE SOURCE REQUIRED]` |
| Web-based agricultural platforms | Supports browser-based access and Laravel implementation | SO3 | Web application | `[LITERATURE SOURCE REQUIRED]` |
| Usability and system evaluation | Supports SO4 evaluation criteria | SO4 | Testing and user evaluation | `[LITERATURE SOURCE REQUIRED]` |

## Research Literature

Foreign Studies Needed:

- Weather effects on rice yield using rainfall, temperature, humidity, and wind speed.
- Machine-learning rice yield forecasting using Multiple Linear Regression, Random Forest, and Gradient Boosting.
- Agricultural decision-support platforms with dashboards, maps, and advisories.
- Model evaluation using RMSE, MAE, and R-squared.

Philippine Studies Needed:

- Philippine rice production and climate variability.
- PAGASA climate data use in agriculture.
- Department of Agriculture, IRRI, PSA, or Philippine academic studies on rice yield forecasting.
- Agricultural advisory systems and digital extension in Philippine municipalities.

Local/Regional Studies Needed:

- Rice farming conditions in Batangas or CALABARZON.
- Lian, Batangas MAO records or local rice production context.
- Local climate hazards affecting rice farmers in Lian.

The repository does not provide complete citation data. No literature citation, author, publication year, sample size, or finding should be fabricated.

## Technical Background

| Technology | Version | Purpose in iClimate | Files Using It | Objective Supported |
|---|---|---|---|---|
| PHP | 8.2.12 verified by CLI; composer requires `^8.2` | Main backend runtime | `composer.json`, Laravel app files | SO3 |
| Laravel | `^12.0`; installed framework reported as Laravel 12 family | MVC backend, routing, auth, validation, ORM | `routes/`, `app/`, `config/` | SO3, SO4 |
| Blade | Laravel bundled | Server-rendered views | `resources/views` | SO3 |
| MySQL/MariaDB | Driver configured; exact server version not verified | Relational database | `config/database.php`, migrations | SO1, SO3 |
| Composer | Installed; exact version not recorded in manuscript | PHP dependency management | `composer.json`, `composer.lock` | SO3 |
| Node.js | v24.16.0 verified | Frontend build tooling runtime | `package.json` | SO3 |
| NPM | Available through `npm.cmd`; PowerShell `npm.ps1` blocked by policy | Frontend package manager | `package-lock.json` | SO3 |
| Vite | `^7.0.7` | Asset building | `vite.config.js`, `package.json` | SO3 |
| Tailwind CSS | `^3.1.0`; `@tailwindcss/forms ^0.5.2` | CSS utility tooling | `resources/css/app.css`, `tailwind.config.js` | SO3 |
| Bootstrap | `^5.3.3` | UI styling/components | `package.json`, views | SO3 |
| Chart.js | `^4.4.7` | Dashboard/report charts | `resources/js/app.js`, dashboard views | SO3 |
| Leaflet | `^1.9.4` | Map visualization | `package.json`, heat map/live forecasting views | SO3 |
| Leaflet.heat | `^0.2.0` | Heat map layer | `package.json`, heat map views | SO3 |
| Python | 3.14.6 verified by CLI | ML script runtime | `python_scripts/predict.py`, `farming_assistant_api.py` | SO2, SO3 |
| pandas | Imported by Python scripts; package availability in current Python not verified | Model input DataFrame construction | `python_scripts/predict.py` | SO2 |
| joblib | Imported by Python scripts; package availability in current Python not verified | Load `.pkl` models | Python scripts | SO2 |
| scikit-learn | Compatibility shims present; installed version not verified | Model artifact compatibility | Python scripts | SO2 |
| Open-Meteo | Configured base URL | Forecast data source | `OpenMeteoService`, `WeatherApiService` | SO3 |
| PAGASA pages | Configured URLs | Official online advisory filtering | `PagasaAdvisoryService`, `config/services.php` | SO1, SO3 |
| Groq API | Configured optional provider | Generative AI assistant support | `GroqChatService`, AI services | Supporting System Functionality |
| DomPDF | `dompdf/dompdf` v3.1.6; `barryvdh/laravel-dompdf` v3.1.2 | Local PDF generation for this document and possible app dependency | `composer.lock` | Documentation output |

## Theoretical Framework

The strongest theoretical foundation for the study is a combined Decision Support System and climate-informed agricultural decision-making framework. The verified system receives climate data, rice production data, farm attributes, and user requests; processes these through records management, forecasts, prediction, advisory rules, reports, and risk visualization; and produces decision-support outputs such as yield estimates, advisories, dashboards, reports, notifications, and heat map risk views.

Potential theories or models:

- Decision Support System framework: directly evidenced by data input, model processing, dashboards, reports, and advisory outputs. Recommended primary framework.
- Climate-smart agriculture principles: conceptually aligned with climate-informed farming support, but requires literature citation.
- Predictive modeling theory: supports feature-based yield prediction and metric-based algorithm comparison.
- Technology acceptance/usability framework: relevant to SO4 only if the researchers supply a user evaluation instrument.

Figure 1 - Theoretical Framework Placeholder:

`[INSERT FIGURE 1 - THEORETICAL FRAMEWORK HERE]`

Suggested figure content: Climate and agricultural input factors -> iClimate decision-support processes -> weather analysis, rice yield prediction, advisories, risk mapping, reports, notifications -> climate-informed farming and MAO decision support.

## Conceptual Framework

The conceptual framework should follow an Input-Process-Output-Outcome structure aligned with the fixed objectives.

Inputs:

- Historical climate data from PAGASA: rainfall, temperature, humidity, wind speed. `[RESEARCHER INPUT REQUIRED]`
- Local rice production records from MAO: barangay, season, irrigation type, yield per hectare, area, total production, year. `[RESEARCHER INPUT REQUIRED]`
- Farm area, farm type, season, target date, user role, external forecast records, and advisory source data.

Processes:

- Data collection, validation, storage, and preprocessing.
- Model comparison for MLR, Random Forest, and Gradient Boosting.
- Random Forest deployment for rice yield prediction.
- Web platform development using Laravel, MySQL, Blade, JavaScript, and Python integration.
- System testing, debugging, and user evaluation.

Outputs:

- iClimate web platform.
- Weather analysis and climate monitoring dashboard.
- Rice yield prediction and estimated total production.
- Planting advisory and irrigation recommendation.
- Heat map, reports, notifications, user management, AI assistant, community feed, and messaging.

Outcome:

- Climate-informed agricultural decision support for rice farming stakeholders in Lian, Batangas.

Figure 2 - Conceptual Framework Placeholder:

`[INSERT FIGURE 2 - CONCEPTUAL FRAMEWORK HERE]`

## Synthesis

The literature chapter must connect climate variability, rice yield, agricultural decision support, machine learning, advisory dissemination, web-based platforms, and system evaluation. The verified iClimate implementation supports this synthesis through stored records, predictive modeling, weather APIs, advisory rules, dashboards, risk maps, reports, notifications, and role-based access. The final literature review must still supply scholarly and official sources. Until then, all uncited external claims remain `[LITERATURE SOURCE REQUIRED]`.

# CHAPTER III - DESIGN AND METHODOLOGY

## Research Design

The most appropriate source-supported design is applied developmental research with quantitative and system-evaluation components. The project produced a functioning web-based system and includes database structures, prediction services, machine-learning model deployment, automated tests, and evaluation criteria. Quantitative elements are present in climate variables, rice production records, prediction metrics, and model evaluation. User evaluation remains `[RESEARCHER INPUT REQUIRED]`.

## Design Stages of the Process

The approved methodology figure is Agile. The repository supports an iterative-development interpretation through multiple modules, migrations over time, tests, services, views, and gap/revision documents. Exact sprint dates, issue boards, and meeting records were not found.

Suggested Agile stages for iClimate:

1. Requirements Gathering - identify users, climate/rice data needs, prediction needs, advisory needs, dashboard requirements, and evaluation criteria.
2. Planning - define modules, roles, database entities, model needs, and technology stack.
3. System Design - create database schema, role access, workflows, interfaces, architecture, and diagram models.
4. Development - implement Laravel routes/controllers/models/views, Python prediction scripts, services, APIs, and frontend assets.
5. Testing - run feature/unit tests, validation checks, role authorization tests, prediction parity tests, and security tests.
6. Review - compare implemented behavior with objectives and fix manuscript/system gaps.
7. Deployment/Release - configure local XAMPP/Laravel/MySQL and required Python/API services. Production deployment evidence is `[RESEARCHER INPUT REQUIRED]`.
8. Iteration/Maintenance - update advisories, refresh forecast data, maintain users, logs, and model artifacts.

Figure 3 - Agile Methodology Placeholder:

`[INSERT FIGURE 3 - AGILE METHODOLOGY HERE]`

## Development Process

The development process followed a Laravel MVC architecture. Routes in `routes/web.php` and `routes/api.php` define public, authenticated, role-restricted, and throttled workflows. Controllers coordinate CRUD operations, dashboards, reports, predictions, advisories, messaging, notifications, and AI chat. Models represent users, farmer profiles, climate records, rice production records, advisories, reports, heat map areas, notifications, AI chats, feed posts, conversations, and logs. Services isolate external weather access, prediction logic, Python API calls, decision support, advisory rules, heat map risk scoring, AI orchestration, CAPTCHA, and security support.

The machine-learning integration is hybrid. Laravel builds the weather and farm input payload through `PredictionEngine`. It first attempts to call the Farming AI API through `PythonService`; if unavailable, it runs `python_scripts/predict.py`; if both fail, it uses a local fallback estimate based on recent rice production records and weather adjustments. The shared engine is used by system prediction and AI assistant workflows to reduce inconsistency.

Figure 4 - System Architecture Placeholder:

`[INSERT FIGURE 4 - SYSTEM ARCHITECTURE HERE]`

## Testing Process

Current test files cover authentication, registration, password reset/update, profile management, role authorization, security hardening, AI chat, AI hybrid orchestration, weather prediction, prediction parity, model evaluation, farmer dashboard weather, reports, heat maps, notifications, community feed, messaging, online agricultural advisory generation, planting advisory barangay filtering, typhoon safety response validation, weather API mapping, agricultural risk scoring, and weather icon mapping.

Current execution result on 2026-08-19:

- Command: `php artisan test --compact`
- Passed: 137 tests
- Failed: 14 tests
- Assertions: 679
- Main failing area: AI assistant intent/source-label expectations, past-month prediction handling, farmer dashboard weather assertion, and notification visibility assertion.

Therefore, SO4 is partially supported by automated testing evidence but cannot be described as fully satisfied until the failing tests and missing user evaluation evidence are resolved.

## Debugging Process

The implemented system includes multiple debugging and error-handling mechanisms:

- Laravel validation returns form errors for invalid prediction, profile, advisory, report, and CRUD input.
- Role middleware aborts unauthorized access.
- Security tests verify browser security headers and no-store cache headers for authenticated HTML responses.
- Open-Meteo failures log warnings and fall back to the latest stored forecast when available.
- Python model failures fall back from Farming AI API to local script, then to local estimate.
- PAGASA fetch failures are captured in summary errors without stopping the whole advisory process.
- Tests reproduce behavior for prediction validation, risk parity, role access, advisory generation, and security.

## Validation Process

Validation exists at several levels:

- Request validation through `RiceYieldPredictionRequest`, `WeatherPredictionRequest`, `ProfileUpdateRequest`, and controller-level validation.
- Role validation through `RoleMiddleware`.
- Prediction date validation through `PredictionDateValidator`.
- Database validation through migrations, indexes, nullable rules, foreign keys, and enum columns.
- Automated test validation through feature and unit tests.
- User evaluation validation is `[RESEARCHER INPUT REQUIRED]`.

## Programming Procedure

The programming procedure can be documented as:

1. Configure Laravel project, environment, database, routes, and authentication.
2. Define database migrations and Eloquent models for users, farm profiles, climate data, rice production, advisories, reports, notifications, heat maps, AI chats, feed, messages, and logs.
3. Build role-specific dashboards and navigation.
4. Implement CRUD workflows for agricultural and administrative records.
5. Implement prediction services and Python integration.
6. Implement external weather services and advisory rule generation.
7. Implement visualization, reporting, notifications, community, messaging, and AI assistant modules.
8. Implement middleware, validation, CAPTCHA, throttling, and security headers.
9. Create and run automated tests.
10. Generate research documentation and diagrams from verified implementation evidence.

## Software and Hardware Needed in the Development

Table 1 - Software Requirements:

| Software | Version | Purpose | Verification |
|---|---|---|---|
| Windows / XAMPP environment | Exact OS build `[RESEARCHER INPUT REQUIRED]` | Local development and server stack | Project path under `C:\xampp\htdocs` |
| PHP | 8.2.12 | Laravel runtime | CLI verified |
| Laravel | 12 family / `^12.0` | MVC backend | `composer.json`, installed dependencies |
| Composer | `[TECHNICAL VERIFICATION REQUIRED]` | PHP package management | `composer.json`, `composer.lock` |
| MySQL/MariaDB | `[TECHNICAL VERIFICATION REQUIRED]` | Database | Laravel config and migrations |
| Node.js | v24.16.0 | Frontend build runtime | CLI verified |
| NPM | `[TECHNICAL VERIFICATION REQUIRED]` | JS package management | `package-lock.json`; PowerShell policy blocked `npm.ps1` |
| Vite | ^7.0.7 | Asset building | `package.json` |
| Tailwind CSS | ^3.1.0 | CSS utility framework | `package.json` |
| Bootstrap | ^5.3.3 | UI components/styles | `package.json` |
| Chart.js | ^4.4.7 | Charts | `package.json` |
| Leaflet | ^1.9.4 | Maps | `package.json` |
| Leaflet.heat | ^0.2.0 | Heat map layer | `package.json` |
| Python | 3.14.6 local CLI | Model script runtime | CLI verified |
| pandas, joblib, scikit-learn | `[TECHNICAL VERIFICATION REQUIRED]` | Model artifact loading and prediction input preparation | Python imports in scripts |
| DomPDF | dompdf/dompdf v3.1.6 | PDF rendering dependency | `composer.lock` |
| Git / GitHub | `[RESEARCHER INPUT REQUIRED]` | Version control | `.git` exists; remote workflow not documented |
| Browser | `[RESEARCHER INPUT REQUIRED]` | Client access | Web-based app |

Table 2 - Hardware Requirements:

| Category | Hardware Requirement | Status |
|---|---|---|
| Development Hardware | Computer capable of running XAMPP/Laravel, PHP 8.2, MySQL/MariaDB, Node/Vite, and Python model scripts | Recommended requirement - must be researcher-approved |
| Server Hardware | Web/database server capable of hosting Laravel, MySQL/MariaDB, storage, scheduled commands, and Python/Farming AI service if used | `[RESEARCHER INPUT REQUIRED]` |
| Client Hardware | Browser-capable desktop, laptop, tablet, or mobile device with internet access | Recommended requirement - must be researcher-approved |
| Actual Development Machine | Exact CPU, RAM, storage, and OS version | `[RESEARCHER INPUT REQUIRED]` |

## Instrumentation

Data Collection Instruments:

- PAGASA historical climate data files for rainfall, temperature, humidity, and wind speed. `[RESEARCHER INPUT REQUIRED]`
- MAO rice production records for Lian, Batangas from 2020 to 2025. `[RESEARCHER INPUT REQUIRED]`

System Evaluation Instruments:

- Automated Laravel/PHPUnit feature and unit tests are present.
- User evaluation questionnaire, Likert scale, respondent list, and statistical treatment are `[RESEARCHER INPUT REQUIRED]`.

Model Evaluation Instruments:

- RMSE, MAE, and RÂ² are displayed by `ModelEvaluationService`.
- Source notebook and metric-computation outputs are `[TECHNICAL VERIFICATION REQUIRED]`.

## Preparation and Evaluation

Preparation stages include dataset collection, database setup, model preparation, web application setup, module testing, advisory and API configuration, and evaluation preparation. The application has evidence of code-level preparation through migrations, factories, seeders, support classes, services, and tests. Research-side preparation such as consent forms, respondent recruitment, survey instruments, evaluation schedules, and official dataset authorization remains `[RESEARCHER INPUT REQUIRED]`.

## Data Collection

Climate Dataset:

- Source: PAGASA is identified in schema defaults and manuscript objectives; Open-Meteo is a runtime forecast source; PAGASA online pages are advisory sources.
- Period: 2020 to 2025 required by SO1; complete coverage not verified.
- Variables: rainfall, temperature, humidity, wind speed, season, source, record date.
- Format: Database table verified; original file format not found.
- Cleaning/transformations: Runtime aggregation and feature engineering are verified in services; original preprocessing pipeline is missing.

Rice Production Dataset:

- Source: Municipal Agricultural Office required by SO1; source files not found.
- Period: 2020 to 2025 required; complete coverage not verified.
- Variables: barangay, season, irrigation type, yield per hectare, area hectares, total production, year, remarks.
- Cleaning/preprocessing: Database schema and model-evaluation sample construction verified; original cleaning artifacts missing.

## Development Model Evaluation

The deployed model evidence identifies Random Forest as the selected rice yield prediction model. The model evaluation service displays the following metrics:

| Model | RMSE | MAE | RÂ² | Rank | Selected? |
|---|---:|---:|---:|---:|---|
| Random Forest | 0.802549 | 0.591967 | 0.240319 | 1 | Yes |
| Multiple Linear Regression | 0.863691 | 0.651078 | 0.120158 | 2 | No |
| Gradient Boosting | 0.897037 | 0.653737 | 0.050907 | 3 | No |

Important distinction: Random Forest is verified as the deployed model and as the selected model in displayed system evaluation. The original notebook, source datasets, and metric-computation procedure are not present in the repository, so the model-training proof remains `[TECHNICAL VERIFICATION REQUIRED]`.

Figure 11 - Predictive Model Performance Evaluation Flowchart Placeholder:

`[INSERT FIGURE 11 - PREDICTIVE MODEL PERFORMANCE EVALUATION FLOWCHART HERE]`

## Ethical Considerations

Implemented Technical Safeguards:

- Authentication, password hashing, CSRF protection, role middleware, request validation, throttling, CAPTCHA, inactive-user login blocking, and security headers.
- User records include personal fields such as name, email, contact number, address, barangay, and role.
- Farm records and rice production records include agricultural and location information.

Research Ethical Requirements:

- Participant consent for farmers, MAO personnel, agricultural technicians, and IT experts. `[RESEARCHER INPUT REQUIRED]`
- Privacy protection for accounts, farm details, contact numbers, and addresses. `[RESEARCHER INPUT REQUIRED]`
- Anonymization of survey results and screenshots. `[RESEARCHER INPUT REQUIRED]`
- Transparent communication that prediction outputs are decision-support aids, not guaranteed outcomes.
- Careful handling of API keys and `.env` values; these should not appear in appendices or screenshots.

## Required Figure Placeholders

Figure 1 - Theoretical Framework

`[INSERT FIGURE 1 - THEORETICAL FRAMEWORK HERE]`

Figure 2 - Conceptual Framework

`[INSERT FIGURE 2 - CONCEPTUAL FRAMEWORK HERE]`

Figure 3 - Agile Methodology

`[INSERT FIGURE 3 - AGILE METHODOLOGY HERE]`

Figure 4 - System Architecture

`[INSERT FIGURE 4 - SYSTEM ARCHITECTURE HERE]`

Figure 5 - Requirement Analysis

`[INSERT FIGURE 5 - REQUIREMENT ANALYSIS HERE]`

Figure 6 - Use Case Diagram

`[INSERT FIGURE 6 - USE CASE DIAGRAM HERE]`

Figure 7 - Context Diagram

`[INSERT FIGURE 7 - CONTEXT DIAGRAM HERE]`

Figure 8 - Data Flow Diagram

`[INSERT FIGURE 8 - DATA FLOW DIAGRAM HERE]`

Figure 9 - Entity Relationship Diagram

`[INSERT FIGURE 9 - ENTITY RELATIONSHIP DIAGRAM HERE]`

Figure 10 - Development Flow of the iClimate Web-Based Decision-Support Platform

`[INSERT FIGURE 10 - DEVELOPMENT FLOW OF THE ICLIMATE WEB-BASED DECISION-SUPPORT PLATFORM HERE]`

Figure 11 - Predictive Model Performance Evaluation Flowchart

`[INSERT FIGURE 11 - PREDICTIVE MODEL PERFORMANCE EVALUATION FLOWCHART HERE]`

## System Actors

| Actor | Actual Role Name in Code | Responsibilities | Modules | Objective Alignment |
|---|---|---|---|---|
| Rice Farmer | Farmer | View farmer dashboard, advisories, predictions, heat map, community feed, messages, notifications, profile, typhoon safety response | Farmer dashboard, predictions, advisories, heat map, community, messages | SO3, SO4 |
| MAO Personnel | MAO Personnel | Manage agricultural records, advisories, reports, monitoring workflows, communications, typhoon response summaries | MAO dashboard, records, advisories, reports, heat map, community, messages | SO1, SO3, SO4 |
| Agricultural Technician | Not a coded role | Stakeholder/evaluator named by objectives; separate system permissions not implemented | `[RESEARCHER INPUT REQUIRED]` | SO3, SO4 |
| IT Expert / Administrator | IT Expert | Manage users, logs, model evaluation, administrative dashboard and oversight | Admin dashboard, users, logs, model evaluation | SO3, SO4 |

## System Modules

| Module | Purpose | Actor | Inputs | Processes | Outputs | Files | Supports Objective |
|---|---|---|---|---|---|---|---|
| Authentication | Secure access | All | Email, password, CAPTCHA | Login, register, reset, verify | Session/account access | `routes/auth.php`, Auth controllers | SO3, SO4 |
| Climate Monitoring Dashboard | Summarize climate data | Farmer, MAO, IT | Climate records, forecasts | Aggregation/charts | Dashboard weather summaries | `DashboardController`, dashboard views | SO1, SO3 |
| Weather Analysis / Prediction | Predict monthly weather values | All roles | Target date, climate history | Monthly Random Forest | Rainfall, temperature, humidity, wind | `WeatherPredictionController`, `MonthlyWeatherRandomForest` | SO3 |
| Rice Yield Prediction | Estimate yield and production | All roles | Date, area, farm type, weather features | Shared engine + Python model | Yield t/ha, total production, advisories | `RiceYieldPredictionService`, `PredictionEngine`, `predict.py` | SO2, SO3 |
| Planting Advisory | Publish farm guidance | Farmer, MAO, IT | Forecasts, PAGASA pages, rules, manual data | Rule generation/review/publication | Advisories and recommendations | `PlantingAdvisoryController`, advisory services | SO3 |
| Heat Map Visualization | Show barangay risk | All roles | Heatmap areas, weather, GeoJSON | Risk scoring and map rendering | Risk map | `HeatmapAreaController`, API controllers, GeoJSON | SO3 |
| Reports and Analytics | Generate data summaries | MAO, IT | Filters and records | Query/aggregate/export | Web report, print, CSV | `ReportController` | SO3, SO4 |
| Advisory Notification | In-app notices | All roles | Advisory/warning/announcement | Recipient scoping/read status | Notification inbox | `NotificationController`, `Notification` | SO3 |
| User Management | Manage accounts | IT Expert | User profile data | CRUD, role/status control | Managed users | `UserManagementController` | SO3, SO4 |
| AI Assistant | Conversational support | All roles | User question, context | Intent detection, KB, prediction, Groq optional | Chat answer | `AIChatController`, AI services | Supporting System Functionality |
| Community Feed | Updates and discussions | All roles | Posts, media, comments | Posting, reactions, visibility | Feed records | `CommunityFeedController` | Supporting System Functionality |
| Messaging | Direct communication | All roles | Conversation messages | Send/reply/read/typing | Direct messages | `MessagingController` | Supporting System Functionality |
| Calendar | Show advisories/events | All roles | Advisories, feed events | Event mapping | Calendar view | `CalendarController` | Supporting System Functionality |
| Model Evaluation | Show algorithm comparison | IT Expert | Evaluation metadata | Display metrics | Model comparison | `ModelEvaluationController`, `ModelEvaluationService` | SO2, SO4 |
| System Logs | Audit activity | IT Expert | System actions | Store/view logs | Log history | `SystemLogController`, `SystemAuditLogger` | SO4 |

## Functional Requirements

- FR-01 - The system shall authenticate users and route them to role-specific dashboards. Evidence: `routes/web.php`, `User::dashboardRoute`.
- FR-02 - The system shall store climate records containing rainfall, temperature, humidity, wind speed, season, source, and record date. Evidence: `create_climate_records_table`.
- FR-03 - The system shall store rice production records containing barangay, season, irrigation type, yield per hectare, area, total production, year, and remarks. Evidence: `create_rice_productions_table`.
- FR-04 - The system shall generate rice yield predictions using a shared prediction engine and deployed Python model when available. Evidence: `PredictionEngine`, `RiceYieldPredictionService`, `predict.py`.
- FR-05 - The system shall compute estimated total production as predicted yield per hectare multiplied by farm area. Evidence: `RiceYieldPredictionService::standardizeResult`.
- FR-06 - The system shall display MLR, Random Forest, and Gradient Boosting metrics. Evidence: `ModelEvaluationService`.
- FR-07 - The system shall manage planting and agricultural advisories. Evidence: `PlantingAdvisoryController`, advisory migrations/services.
- FR-08 - The system shall fetch and store Open-Meteo forecast records. Evidence: `OpenMeteoService`, `external_weather_data`.
- FR-09 - The system shall filter PAGASA online advisory pages for Lian/Batangas relevance. Evidence: `PagasaAdvisoryService`.
- FR-10 - The system shall display barangay heat map data. Evidence: `HeatmapAreaController`, `LianHeatmapController`, GeoJSON.
- FR-11 - The system shall generate reports with print and CSV export. Evidence: `ReportController::print`, `ReportController::exportCsv`.
- FR-12 - The system shall manage in-app notifications and read/unread state. Evidence: `NotificationController`, `notifications` table.
- FR-13 - The system shall allow IT Experts to manage users and system logs. Evidence: routes and controllers.
- FR-14 - The system shall support community feed, direct messaging, AI chat, calendar, live forecasting, mobile auth API, and typhoon safety response as supporting functionality.

Figure 5 - Requirement Analysis Placeholder:

`[INSERT FIGURE 5 - REQUIREMENT ANALYSIS HERE]`

Figure 6 - Use Case Diagram Placeholder:

`[INSERT FIGURE 6 - USE CASE DIAGRAM HERE]`

Figure 7 - Context Diagram Placeholder:

`[INSERT FIGURE 7 - CONTEXT DIAGRAM HERE]`

Figure 8 - Data Flow Diagram Placeholder:

`[INSERT FIGURE 8 - DATA FLOW DIAGRAM HERE]`

Figure 9 - Entity Relationship Diagram Placeholder:

`[INSERT FIGURE 9 - ENTITY RELATIONSHIP DIAGRAM HERE]`

Figure 10 - Development Flow Placeholder:

`[INSERT FIGURE 10 - DEVELOPMENT FLOW OF THE ICLIMATE WEB-BASED DECISION-SUPPORT PLATFORM HERE]`

## Database Entities and Relationships

Primary entities verified from models and migrations include: User, FarmerProfile, ClimateRecord, RiceProduction, PlantingAdvisory, AdvisoryRule, ExternalWeatherData, HeatmapArea, Report, Notification, AIChat, KnowledgeBase, FeedPost, FeedMedia, FeedComment, FeedReaction, Conversation, ConversationMessage, SystemLog, TyphoonSafetyResponse, Announcement, and Laravel infrastructure tables for sessions, cache, and jobs.

Key relationships:

- User 1:1 FarmerProfile.
- User 1:M PlantingAdvisory through `posted_by`.
- User 1:M Notification.
- User 1:M Report through `generated_by`.
- User 1:M SystemLog.
- User 1:M TyphoonSafetyResponse.
- ExternalWeatherData 1:M PlantingAdvisory.
- AdvisoryRule 1:M PlantingAdvisory.
- FeedPost 1:M FeedMedia.
- FeedPost 1:M FeedComment.
- FeedPost 1:M FeedReaction.
- Conversation 1:M ConversationMessage.
- User participates in conversations through participant foreign keys.

## Role-Permission Matrix

| Function | Farmer | MAO Personnel | Agricultural Technician | IT Expert/Admin |
|---|---:|---:|---:|---:|
| Farmer dashboard | Yes | No | ? | No |
| MAO dashboard | No | Yes | ? | No |
| IT dashboard | No | No | ? | Yes |
| Climate records | Read/limited route access | Manage | ? | Manage |
| Rice production | Read/limited route access | Manage | ? | Manage |
| Rice yield prediction | Yes | Yes | ? | Yes |
| Planting advisory view | Yes | Yes | ? | Yes |
| Advisory management | No | Yes | ? | Yes |
| Heat map | Yes | Yes | ? | Yes |
| Reports | Limited by route behavior | Yes | ? | Yes |
| Notifications | Yes | Yes | ? | Yes |
| User management | No | No | ? | Yes |
| System logs | No | No | ? | Yes |

## Implementation Inconsistency Audit

| Issue | Evidence | Actual Behavior | Manuscript Impact | Needs Resolution? |
|---|---|---|---|---|
| Agricultural Technician role absent | `User` role constants and users migration | Only Farmer, MAO Personnel, IT Expert coded | Must not claim separate technician login/permissions | Yes |
| Original PAGASA/MAO datasets absent | No raw files found | Schema and source labels exist, but provenance not proven | Mark SO1 evidence partial | Yes |
| Training notebook absent | `ModelEvaluationService` names notebook, but file not found | Metrics displayed; source computation not verifiable | Mark model training proof partial | Yes |
| Current tests failing | `php artisan test --compact` | 137 passed, 14 failed | SO4 cannot be reported as fully passed | Yes |
| Notification channel limited | Notification model/table/controller | In-app notifications verified; email/SMS not verified | Do not claim SMS/email alerts | Yes |
| Report export limited | `ReportController` | Print and CSV verified | Do not claim Excel/PDF report export | Yes |
| Runtime weather source broader than PAGASA | `OpenMeteoService`, `WeatherApiService`, `PagasaAdvisoryService` | Open-Meteo forecasts and PAGASA advisory pages used | Distinguish historical and runtime sources | Yes |

## Manual Information Required From Researchers

- Official local background for Lian, Batangas rice farming.
- MAO confirmation of historical rice production records.
- PAGASA dataset files and coverage confirmation for 2020 to 2025.
- Original machine-learning training notebook and datasets.
- Preprocessing, train/test split, cross-validation, and hyperparameter evidence.
- Participant counts, sampling method, demographics, survey instruments, consent procedure, and evaluation results.
- Response-time measurements and predictive-accuracy validation report.
- Actual development hardware specifications.
- Production deployment details.
- Final figure/diagram graphics and screenshots.

## Final Master Summary

Approved objectives are preserved exactly in Chapter I.

System actors identified: Farmer, MAO Personnel, IT Expert, plus Agricultural Technician as named stakeholder but not coded role.

Core modules identified: authentication, dashboards, climate records, rice production records, weather prediction, rice yield prediction, planting advisories, heat map, reports, notifications, user management, model evaluation, system logs.

Supporting modules identified: AI assistant, community feed, messaging, calendar, live forecasting, mobile auth API, typhoon safety response, PAGASA advisory fetch, Open-Meteo forecast fetch.

Database entities identified: 22 Eloquent models plus Laravel infrastructure tables.

External data sources identified: PAGASA historical records as schema/source label, PAGASA online pages, Open-Meteo forecast API, MAO rice production records as required by objective.

External APIs identified: Open-Meteo, PAGASA web pages, optional Groq, Farming AI API at configured local service.

ML models identified: weather model `.pkl`, rice yield model `.pkl`, monthly PHP Random Forest service, displayed MLR/Random Forest/Gradient Boosting comparison.

Inputs: climate variables, production records, farm area, farm type, season, target date, user profile, weather API data, advisory rules, user messages.

Processes: data storage, validation, preprocessing/feature construction, prediction, advisory generation, report aggregation, notification delivery, risk scoring, AI orchestration, testing.

Outputs: dashboards, predictions, estimated total production, advisories, irrigation recommendations, reports, heat maps, notifications, chat answers, logs.

Model evaluation methods: RMSE, MAE, and RÂ².

Chapter I readiness: Partially ready; source-based system sections are ready, but local background and MAO evidence are required.

Chapter II readiness: Technical background is ready; literature review requires citations.

Chapter III readiness: Development and technical methodology are mostly ready; user evaluation, production deployment, hardware, and training artifacts require researcher input.

Diagram readiness: Figures 4, 6, 7, 8, 9, and 10 are partially ready from code evidence. Figures 1, 2, 3, 5, and 11 require diagram drawing and, in some cases, researcher/literature confirmation.

System vs objective gaps: agricultural technician role not coded; raw 2020-2025 datasets missing; preprocessing/training notebook missing; current test suite has failures; user evaluation evidence missing; SMS/email notification not verified; Excel/PDF report export not implemented in report controller.

# FILES ANALYZED

The source analysis covered routes, controllers, requests, models, services, middleware, views, database migrations, seeders, factories, configuration, JavaScript/CSS, Python scripts, machine-learning artifacts, tests, documentation, and dependency files. A generated file inventory is appended below.


## Generated File Inventory

Significant inspectable project files counted for this documentation: 299

### Routes

- `routes/web.php`
- `routes/api.php`
- `routes/auth.php`
- `routes/console.php`

### Controllers

- `app/Http/Controllers/AIChatController.php`
- `app/Http/Controllers/AnnouncementController.php`
- `app/Http/Controllers/CalendarController.php`
- `app/Http/Controllers/ClimateRecordController.php`
- `app/Http/Controllers/CommunityFeedController.php`
- `app/Http/Controllers/Controller.php`
- `app/Http/Controllers/CrudController.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/FarmerProfileController.php`
- `app/Http/Controllers/HeatmapAreaController.php`
- `app/Http/Controllers/LiveForecastingController.php`
- `app/Http/Controllers/MessagingController.php`
- `app/Http/Controllers/ModelEvaluationController.php`
- `app/Http/Controllers/NotificationController.php`
- `app/Http/Controllers/PlantingAdvisoryController.php`
- `app/Http/Controllers/PredictionController.php`
- `app/Http/Controllers/ProfileController.php`
- `app/Http/Controllers/ReportController.php`
- `app/Http/Controllers/RiceProductionController.php`
- `app/Http/Controllers/RiceYieldPredictionController.php`
- `app/Http/Controllers/SystemLogController.php`
- `app/Http/Controllers/TyphoonSafetyController.php`
- `app/Http/Controllers/UserManagementController.php`
- `app/Http/Controllers/WeatherPredictionController.php`

### API Controllers

- `app/Http/Controllers/Api/LianBoundaryController.php`
- `app/Http/Controllers/Api/LianHeatmapController.php`
- `app/Http/Controllers/Api/LianWeatherController.php`
- `app/Http/Controllers/Api/MobileAuthController.php`

### Requests

- `app/Http/Requests/ProfileUpdateRequest.php`
- `app/Http/Requests/RiceYieldPredictionRequest.php`
- `app/Http/Requests/WeatherPredictionRequest.php`

### Models

- `app/Models/AIChat.php`
- `app/Models/Advisory.php`
- `app/Models/AdvisoryRule.php`
- `app/Models/Announcement.php`
- `app/Models/ClimateRecord.php`
- `app/Models/Conversation.php`
- `app/Models/ConversationMessage.php`
- `app/Models/ExternalWeatherData.php`
- `app/Models/FarmerProfile.php`
- `app/Models/FeedComment.php`
- `app/Models/FeedMedia.php`
- `app/Models/FeedPost.php`
- `app/Models/FeedReaction.php`
- `app/Models/HeatmapArea.php`
- `app/Models/KnowledgeBase.php`
- `app/Models/Notification.php`
- `app/Models/PlantingAdvisory.php`
- `app/Models/Report.php`
- `app/Models/RiceProduction.php`
- `app/Models/SystemLog.php`
- `app/Models/TyphoonSafetyResponse.php`
- `app/Models/User.php`

### Services

- `app/Services/DecisionSupportService.php`
- `app/Services/HeatmapRiskService.php`
- `app/Services/PredictionService.php`
- `app/Services/PythonService.php`
- `app/Services/SystemAuditLogger.php`
- `app/Services/TyphoonSafetyService.php`
- `app/Services/WeatherApiService.php`
- `app/Services/AI/AIOrchestratorService.php`
- `app/Services/AI/GroqChatService.php`
- `app/Services/AI/IntentClassifierService.php`
- `app/Services/AI/IntentDetectionService.php`
- `app/Services/AI/KnowledgeBaseService.php`
- `app/Services/AI/RoleAssistantService.php`
- `app/Services/Advisories/AdvisoryGenerationService.php`
- `app/Services/Advisories/AdvisoryRuleEngine.php`
- `app/Services/Advisories/PagasaAdvisoryService.php`
- `app/Services/MachineLearning/ModelEvaluationService.php`
- `app/Services/MachineLearning/MonthlyWeatherRandomForest.php`
- `app/Services/Prediction/PredictionDateValidator.php`
- `app/Services/Prediction/PredictionEngine.php`
- `app/Services/Prediction/RiceYieldPredictionService.php`
- `app/Services/Prediction/WeatherLabeler.php`
- `app/Services/Prediction/WeatherPredictionService.php`
- `app/Services/Risk/AgriculturalRiskScorer.php`
- `app/Services/Security/CaptchaService.php`
- `app/Services/Weather/FarmerDashboardWeatherService.php`
- `app/Services/Weather/LianBarangayWeatherService.php`
- `app/Services/Weather/OpenMeteoService.php`
- `app/Services/Weather/WeatherIconMapper.php`

### Middleware

- `app/Http/Middleware/RoleMiddleware.php`
- `app/Http/Middleware/SecurityHeaders.php`
- `app/Http/Middleware/TrackUserActivity.php`

### Views

- `resources/views/advisories/form.blade.php`
- `resources/views/advisories/index.blade.php`
- `resources/views/advisories/management.blade.php`
- `resources/views/advisories/show.blade.php`
- `resources/views/ai-chat/index.blade.php`
- `resources/views/auth/confirm-password.blade.php`
- `resources/views/auth/forgot-password.blade.php`
- `resources/views/auth/login.blade.php`
- `resources/views/auth/register.blade.php`
- `resources/views/auth/reset-password.blade.php`
- `resources/views/auth/verify-email.blade.php`
- `resources/views/calendar/index.blade.php`
- `resources/views/community-feed/index.blade.php`
- `resources/views/components/ai-chat-widget.blade.php`
- `resources/views/components/application-logo.blade.php`
- `resources/views/components/auth-session-status.blade.php`
- `resources/views/components/danger-button.blade.php`
- `resources/views/components/dropdown-link.blade.php`
- `resources/views/components/dropdown.blade.php`
- `resources/views/components/flash-toast.blade.php`
- `resources/views/components/input-error.blade.php`
- `resources/views/components/input-label.blade.php`
- `resources/views/components/modal.blade.php`
- `resources/views/components/nav-link.blade.php`
- `resources/views/components/primary-button.blade.php`
- `resources/views/components/responsive-nav-link.blade.php`
- `resources/views/components/secondary-button.blade.php`
- `resources/views/components/stat-cards.blade.php`
- `resources/views/components/text-input.blade.php`
- `resources/views/crud/form.blade.php`
- `resources/views/crud/index.blade.php`
- `resources/views/crud/show.blade.php`
- `resources/views/dashboards/farmer.blade.php`
- `resources/views/dashboards/it-expert.blade.php`
- `resources/views/dashboards/mao.blade.php`
- `resources/views/errors/401.blade.php`
- `resources/views/errors/402.blade.php`
- `resources/views/errors/403.blade.php`
- `resources/views/errors/404.blade.php`
- `resources/views/errors/419.blade.php`
- `resources/views/errors/429.blade.php`
- `resources/views/errors/500.blade.php`
- `resources/views/errors/503.blade.php`
- `resources/views/errors/minimal.blade.php`
- `resources/views/heatmap-areas/index.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/guest.blade.php`
- `resources/views/layouts/navigation.blade.php`
- `resources/views/layouts/sidebar.blade.php`
- `resources/views/live-forecasting/index.blade.php`
- `resources/views/messages/index.blade.php`
- `resources/views/model-evaluation/index.blade.php`
- `resources/views/modules/placeholder.blade.php`
- `resources/views/profile/edit.blade.php`
- `resources/views/reports/index.blade.php`
- `resources/views/reports/print.blade.php`
- `resources/views/weather-predictions/index.blade.php`

### Database

- `database/migrations/0001_01_01_000000_create_users_table.php`
- `database/migrations/0001_01_01_000001_create_cache_table.php`
- `database/migrations/0001_01_01_000002_create_jobs_table.php`
- `database/migrations/2026_06_25_000001_create_farmer_profiles_table.php`
- `database/migrations/2026_06_25_000002_create_climate_records_table.php`
- `database/migrations/2026_06_25_000003_create_rice_productions_table.php`
- `database/migrations/2026_06_25_000004_create_planting_advisories_table.php`
- `database/migrations/2026_06_25_000005_create_announcements_table.php`
- `database/migrations/2026_06_25_000006_create_notifications_table.php`
- `database/migrations/2026_06_25_000007_create_heatmap_areas_table.php`
- `database/migrations/2026_06_25_000008_create_reports_table.php`
- `database/migrations/2026_06_25_000009_create_system_logs_table.php`
- `database/migrations/2026_06_30_000001_add_agricultural_risk_fields_to_heatmap_areas_table.php`
- `database/migrations/2026_06_30_000002_seed_lian_barangay_heatmap_defaults.php`
- `database/migrations/2026_07_01_162643_create_a_i_chats_table.php`
- `database/migrations/2026_07_02_010000_create_community_feed_tables.php`
- `database/migrations/2026_07_02_010001_create_messaging_tables.php`
- `database/migrations/2026_07_03_230000_add_archived_at_to_feed_posts_table.php`
- `database/migrations/2026_07_06_000001_create_knowledge_base_table.php`
- `database/migrations/2026_07_06_000002_add_hybrid_ai_metadata_to_a_i_chats_table.php`
- `database/migrations/2026_07_17_000001_add_performance_indexes.php`
- `database/migrations/2026_07_17_000002_add_more_navigation_performance_indexes.php`
- `database/migrations/2026_07_17_000003_create_online_advisory_system_tables.php`
- `database/migrations/2026_07_20_000010_create_typhoon_safety_responses_table.php`
- `database/migrations/2026_07_23_000011_add_show_on_calendar_to_feed_posts_table.php`
- `database/migrations/2026_07_24_000001_remove_dummy_report_history.php`
- `database/migrations/2026_07_31_000001_add_last_active_at_to_users_table.php`
- `database/seeders/AdvisoryRuleSeeder.php`
- `database/seeders/DatabaseSeeder.php`
- `database/seeders/KnowledgeBaseSeeder.php`
- `database/seeders/XamppLocalhostSeeder.php`
- `database/factories/AnnouncementFactory.php`
- `database/factories/ClimateRecordFactory.php`
- `database/factories/FarmerProfileFactory.php`
- `database/factories/HeatmapAreaFactory.php`
- `database/factories/NotificationFactory.php`
- `database/factories/PlantingAdvisoryFactory.php`
- `database/factories/ReportFactory.php`
- `database/factories/RiceProductionFactory.php`
- `database/factories/SystemLogFactory.php`
- `database/factories/UserFactory.php`

### Configuration

- `config/app.php`
- `config/auth.php`
- `config/cache.php`
- `config/database.php`
- `config/filesystems.php`
- `config/logging.php`
- `config/mail.php`
- `config/prediction.php`
- `config/queue.php`
- `config/services.php`
- `config/session.php`

### JavaScript and CSS

- `resources/js/app.js`
- `resources/js/bootstrap.js`
- `resources/js/scroll-animations.js`
- `resources/css/animations.css`
- `resources/css/app.css`

### Python and Machine Learning

- `python_scripts/farming_assistant_api.py`
- `python_scripts/predict.py`
- `storage/models/rice_yield_model_final.pkl`
- `storage/models/weather_model.pkl`

### Tests

- `tests/Feature/AIChatTest.php`
- `tests/Feature/AIHybridOrchestratorTest.php`
- `tests/Feature/CommunicationPlatformTest.php`
- `tests/Feature/ExampleTest.php`
- `tests/Feature/FarmerDashboardRealtimeWeatherTest.php`
- `tests/Feature/FarmerDashboardWeatherTest.php`
- `tests/Feature/ModelEvaluationTest.php`
- `tests/Feature/OnlineAgriculturalAdvisorySystemTest.php`
- `tests/Feature/PlantingAdvisoryBarangayTest.php`
- `tests/Feature/PredictionParityTest.php`
- `tests/Feature/ProfileTest.php`
- `tests/Feature/ReportsHeatmapNotificationsTest.php`
- `tests/Feature/RiskParityTest.php`
- `tests/Feature/RoleAuthorizationTest.php`
- `tests/Feature/SecurityHardeningTest.php`
- `tests/Feature/TyphoonSafetyResponseValidationTest.php`
- `tests/Feature/WeatherPredictionTest.php`
- `tests/Feature/Auth/AuthenticationTest.php`
- `tests/Feature/Auth/EmailVerificationTest.php`
- `tests/Feature/Auth/PasswordConfirmationTest.php`
- `tests/Feature/Auth/PasswordResetTest.php`
- `tests/Feature/Auth/PasswordUpdateTest.php`
- `tests/Feature/Auth/RegistrationTest.php`
- `tests/Unit/AgriculturalRiskScorerTest.php`
- `tests/Unit/ExampleTest.php`
- `tests/Unit/WeatherApiServiceTest.php`
- `tests/Unit/WeatherIconMapperTest.php`

### Documentation and Dependencies

- `docs/online-agricultural-advisories.md`
- `docs/phase1_system_reverse_engineering.md`
- `docs/phase2_gap_analysis.md`
- `docs/phase3_revised_manuscript.md`
- `README.md`
- `composer.json`
- `package.json`
- `phpunit.xml`

