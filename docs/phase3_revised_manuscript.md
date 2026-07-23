# Phase 3 - Revised Manuscript Draft

Project Title: iClimate: A Web-Based Weather Impact Analysis and Rice Yield Prediction System for Lian, Batangas  
Revision date: 2026-07-21  
Primary authority used for revision: implemented Laravel/Python/MySQL system in `C:\xampp\htdocs\iclimate`

## Revision Note

This draft rewrites the manuscript into a complete Chapter I to Chapter V structure aligned with the implemented iClimate system. The original objectives are preserved. Items that cannot be verified from the repository are marked as Needs Verification and should be completed only when supporting datasets, evaluation forms, screenshots, or university formatting requirements are supplied.

# CHAPTER I

# THE PROBLEM AND ITS BACKGROUND

## Introduction

Rice farming remains one of the most important agricultural activities in the Philippines because rice is the country's staple food and a major source of livelihood for rural communities. In municipalities where farming still depends heavily on local weather conditions, climate variability can directly affect production decisions, planting schedules, irrigation needs, and expected harvest. For rice farmers in Lian, Batangas, rainfall, temperature, humidity, wind speed, seasonal changes, and extreme weather events create practical uncertainty in day-to-day farm planning.

Climate-related risks are especially important in rice production because rice growth depends on the timing and amount of water available during the crop cycle. Insufficient rainfall may delay land preparation and crop establishment, while excessive rainfall, flooding, strong winds, and typhoons may damage crops or reduce yield. These conditions show the need for localized agricultural decision support that can combine climate records, rice production data, weather forecasts, prediction models, advisories, and communication tools into one accessible system.

iClimate was developed as a web-based weather impact analysis and rice yield prediction system for Lian, Batangas. The implemented system provides role-based dashboards, climate monitoring, rice production records, farmer profiles, weather prediction, rice yield prediction, planting advisories, heat map visualization, reports, in-app notifications, community feed, direct messaging, AI farming assistance, live forecasting, model evaluation, user management, system logs, and typhoon safety responses. It uses Laravel 12.62.0 and PHP 8.2.12 as the main web application backend, MySQL/MariaDB as the database, Blade with Bootstrap, Tailwind tooling, Chart.js, Leaflet, and Leaflet.heat for the user interface and visualization, and Python/Flask scripts for machine learning prediction integration.

The system is designed to support climate-informed agricultural decision-making. Farmers can view advisories, weather guidance, prediction results, notifications, maps, community updates, and AI assistance. MAO Personnel can manage agricultural records, advisories, reports, communication, and monitoring workflows. IT Experts can manage users, system logs, model evaluation information, and administrative functions. Through this structure, iClimate supports both farm-level decisions and municipal-level monitoring.

## Background of the Study

The use of digital technologies in agriculture has expanded through decision-support platforms, prediction models, dashboards, online advisories, and communication tools. These technologies help agricultural stakeholders reduce dependence on manual estimation by organizing historical records, generating forecasts, visualizing risks, and improving the delivery of timely information. For climate-sensitive crops such as rice, digital tools become more useful when they are localized and connected to the actual conditions of the farming area.

The current iClimate implementation addresses this need by combining agricultural records and weather-related services into a single Laravel-based platform. The system stores historical climate records and rice production records, uses online weather data from Open-Meteo and OpenWeather, filters PAGASA advisory pages for official weather advisories relevant to Lian, Batangas, and provides prediction and advisory workflows. It also uses a local GeoJSON file and Leaflet-based maps to visualize barangay-level agricultural risk.

The system includes a deployed rice yield prediction model saved as `storage/models/rice_yield_model_final.pkl` and a deployed weather model saved as `storage/models/weather_model.pkl`. A Python script, `python_scripts/predict.py`, is used by Laravel to produce rice yield predictions from structured weather and farm inputs. A Flask service, `python_scripts/farming_assistant_api.py`, supports prediction workflows for the AI farming assistant. The implemented model evaluation page reports that Random Forest was selected over Multiple Linear Regression and Gradient Boosting based on RMSE, MAE, and R2 metrics.

Needs Verification: The source training notebook and original dataset files referenced by the system are not present in the repository. Therefore, the final manuscript should not claim complete verification of preprocessing, train-test split, hyperparameter tuning, or cross-validation unless those files are supplied.

## Objectives of the Study

### General Objective

The main goal of this study is to develop iClimate: A Web-Based Weather Impact Analysis and Rice Yield Prediction System for Lian, Batangas, with integrated weather analysis, rice yield prediction, planting advisory, climate monitoring, and advisory notification modules to support climate-informed agricultural decision-making.

### Specific Objectives

This study specifically aims to:

- To collect, preprocess, and analyze historical climate data from PAGASA, including rainfall, temperature, humidity, and wind speed, together with local rice production records from the Municipal Agricultural Office (MAO) in Lian, Batangas, from 2020 to 2025.
- To compare and evaluate Multiple Linear Regression, Random Forest, and Gradient Boosting algorithms in forecasting seasonal rice yield per hectare under varying conditions, such as wet/dry seasons and irrigated/rainfed farming systems, and determine the best-performing algorithm using RMSE, MAE, and R2 evaluation metrics.
- To develop a web-based decision-support platform with Weather Analysis, Rice Yield Prediction, Planting Advisory, Climate Monitoring Dashboard, Heat Map Visualization, Reports and Analytics, Advisory Notification, and User Management functionalities for rice farmers, MAO personnel and agricultural technicians.
- To evaluate the iClimate platform in terms of functionality, usability, reliability, efficiency, response time, and predictive accuracy through system testing and user evaluation involving rice farmers, MAO personnel, agricultural technicians, and IT experts.

## Significance of the Study

The development of iClimate benefits rice farmers, MAO Personnel, and IT Experts by providing a localized system for weather-aware rice farming support in Lian, Batangas.

For rice farmers, the system provides access to weather information, planting advisories, rice yield prediction, heat map risk visualization, notifications, community feed updates, direct messaging, and the PalayPilot AI farming assistant. These features help farmers make more informed decisions about planting, irrigation, weather preparedness, and possible yield outcomes.

For MAO Personnel, the system supports municipal-level monitoring of farmer profiles, climate records, rice production records, advisories, reports, heat map areas, community communications, and typhoon safety responses. It allows the MAO to publish and manage advisories, generate reports, communicate with farmers, and monitor risk conditions across barangays.

For IT Experts, the system provides administrative tools for user management, system logs, model evaluation viewing, dashboard monitoring, and system oversight. These functions help maintain role-based access and support the technical operation of the application.

For future researchers and developers, the project demonstrates an integrated approach that combines Laravel web development, MySQL data management, Python machine learning deployment, weather API integration, Leaflet-based mapping, and hybrid AI assistance in an agricultural decision-support context.

## Scope and Limitation of the Study

This study focuses on the development of iClimate as a web-based weather impact analysis and rice yield prediction system for Lian, Batangas. The implemented system includes authentication, role-based dashboards, farmer profiles, climate records, rice production records, planting advisories, advisory management, weather prediction, rice yield prediction, live forecasting, heat map visualization, reports, in-app notifications, community feed, direct messaging, AI farming assistance, model evaluation, user management, system logs, and typhoon safety responses.

The system is implemented as a Laravel 12 web application using PHP 8.2.12, Blade views, Bootstrap, Tailwind tooling, Vite, Chart.js, Leaflet, Leaflet.heat, MySQL/MariaDB, and Python integration. Weather-related data sources include stored historical climate records, Open-Meteo forecast storage, OpenWeather forecast summaries, and PAGASA advisory page filtering. Rice yield prediction is performed through a deployed Python model loaded from `storage/models/rice_yield_model_final.pkl`.

The system supports three coded roles: Farmer, MAO Personnel, and IT Expert. Agricultural technicians may be discussed as MAO-related stakeholders, but they are not implemented as a separate coded role in the current application.

The notification feature is limited to database-backed in-app notifications based on the inspected implementation. SMS notification delivery was not found. Email configuration exists, but module-level email notification dispatch was not verified. Report generation supports web viewing, print view, CSV export, and saved report history. PDF and Excel export were not found in the implemented `ReportController`.

The system requires internet connectivity for web access and external API-based weather/advisory updates. Offline access is not implemented. Production deployment details, automated backup configuration, survey results, and user-evaluation datasets are Needs Verification. Training notebooks, original ML datasets, and preprocessing artifacts are also Needs Verification because they were not found in the repository.

## Definition of Terms

AI Farming Assistant. The PalayPilot assistant implemented in iClimate. It uses intent detection, knowledge-base entries, database-aware answers, Python prediction integration, fallback rules, and optional Groq generation to answer supported farming and system questions.

Climate Data. Weather-related records used by the system, including rainfall, temperature, humidity, wind speed, season, and source. These are stored in the `climate_records` table and are used by dashboards, prediction, reports, AI context, and heat map logic.

Farmer. A registered iClimate user role that can access farmer dashboard features, advisories, weather prediction, rice yield prediction, heat maps, community feed, messages, notifications, AI assistant, and profile functions.

Heat Map. A Leaflet-based barangay risk visualization that displays agricultural risk levels and related map data using `heatmap_areas`, Leaflet.heat, and local Lian barangay GeoJSON.

iClimate. The implemented web-based system for weather impact analysis, rice yield prediction, and agricultural decision support for Lian, Batangas.

In-App Notification. A database-stored notification displayed inside the system. This is the confirmed notification channel in the implementation.

IT Expert. A coded system role responsible for user management, system logs, model evaluation access, administrative dashboard functions, and system oversight.

MAO Personnel. A coded system role that manages agricultural records, advisories, reports, farmer monitoring, heat maps, community communication, and typhoon safety response information.

Open-Meteo. A weather API source used by iClimate to store forecast data for advisory generation and dashboard support.

OpenWeather. A weather API source used by iClimate to provide live forecast summaries for dashboards and planning inputs.

PAGASA Advisory. Official weather advisory content retrieved and filtered by the system for Lian, Batangas relevance through `PagasaAdvisoryService`.

Random Forest. An ensemble machine learning algorithm used in the implemented system as the selected rice yield prediction model based on displayed RMSE, MAE, and R2 comparison metrics.

Rice Yield Prediction. The system feature that estimates rice yield using weather and farm-related input values and a deployed Python model.

Typhoon Safety Response. A feature that allows farmers to submit safe or needs-help status during active typhoon-related advisory events.

# CHAPTER II

# REVIEW OF LITERATURE AND STUDIES

## Conceptual Literature

Climate-smart agriculture emphasizes the use of information, planning, and technology to improve farming decisions under changing climate conditions. In rice production, this concept is important because crop performance is affected by rainfall, temperature, humidity, wind, seasonal timing, flooding, and drought. A system such as iClimate supports climate-smart agriculture by transforming weather and production records into dashboards, predictions, advisories, risk maps, and reports.

Agricultural decision-support systems help stakeholders make informed choices by organizing data and producing actionable outputs. Instead of relying only on manual judgment, decision-support platforms can combine historical records, real-time data, forecasting, visualization, and rule-based recommendations. iClimate follows this concept by integrating climate records, rice production data, weather APIs, advisory rules, machine learning prediction, and role-specific dashboards.

Machine learning in agriculture is commonly used to identify patterns between environmental variables and crop outcomes. Regression models such as Multiple Linear Regression, Random Forest, and Gradient Boosting are relevant to rice yield prediction because they can estimate numeric yield values from weather and farm-related features. In iClimate, the deployed model comparison identifies Random Forest as the selected algorithm based on the lowest RMSE and MAE and the highest R2 among the compared models.

Web-based agricultural platforms improve access because users can reach system features through a browser without installing specialized desktop software. The implemented iClimate platform uses a Laravel MVC architecture with Blade views and a MySQL/MariaDB database. This structure supports role-based access, record management, dashboards, reporting, predictions, maps, and communication tools.

Hybrid AI assistants are increasingly relevant in decision-support systems because they can provide conversational access to system knowledge and structured workflows. In iClimate, PalayPilot is not treated as an unrestricted chatbot. It combines intent detection, knowledge base search, database-aware answers, prediction integration, fallback logic, and optional generative responses with constraints against inventing private records or exact forecast values.

## Review of Related Literature

Related studies on climate variability and rice production show that rainfall distribution, temperature patterns, and extreme weather can affect yield performance. These studies support the need for localized monitoring and prediction tools, especially for rice-growing communities exposed to irregular rainfall, typhoons, and seasonal changes.

Studies on crop yield prediction demonstrate that machine learning algorithms can support agricultural forecasting when trained on relevant environmental and production variables. Multiple Linear Regression provides a baseline approach, while ensemble methods such as Random Forest and Gradient Boosting can capture more complex relationships. This literature supports the algorithm comparison required by the study objectives.

Studies on agricultural information systems emphasize the value of dashboards, alerts, advisory platforms, and data visualization. These systems support stakeholders by making records easier to analyze and by improving access to timely recommendations. iClimate extends this idea through role-based dashboards, heat maps, reports, in-app notifications, community feed, and direct messaging.

Studies on digital extension and advisory systems support the use of online communication to improve agricultural guidance. Farmers benefit when recommendations are localized, timely, and understandable. This supports iClimate's planting advisories, automated advisory generation, PAGASA advisory filtering, notification workflow, and PalayPilot assistant.

Needs Verification: The final Chapter II should retain and verify the original manuscript's cited sources in APA format. The current repository does not contain a verified reference database, so citation numbers and reference details should be checked against the university's required format.

## Technical Background

The implemented system uses Laravel as the main backend framework. Laravel follows the Model-View-Controller pattern, where routes direct requests to controllers, controllers coordinate validation and business logic, models represent database entities, and Blade views render the user interface. This architecture fits iClimate because the system contains many record-based modules, role-based dashboards, and service-based integrations.

The database uses MySQL/MariaDB and contains 24 executed migrations. Major tables include `users`, `farmer_profiles`, `climate_records`, `rice_productions`, `planting_advisories`, `advisory_rules`, `external_weather_data`, `heatmap_areas`, `reports`, `notifications`, `a_i_chats`, `knowledge_base`, `feed_posts`, `feed_comments`, `feed_reactions`, `conversations`, `conversation_messages`, `system_logs`, and `typhoon_safety_responses`.

The machine learning integration uses Python models and scripts. The Laravel application calls `python_scripts/predict.py` for rice yield prediction, while `python_scripts/farming_assistant_api.py` provides a Flask API used by AI-assisted prediction workflows. FastAPI was not found in the implemented repository and should not be described as part of the actual backend unless later added.

The map implementation uses Leaflet and Leaflet.heat with local GeoJSON for Lian barangays. This confirms that the manuscript should describe Leaflet/OpenStreetMap-style mapping rather than Google Maps.

The reporting implementation supports generated report views, print view, CSV export, and saved report history. Although DomPDF is installed as a dependency, implemented PDF report export was not found in the inspected report controller.

## Synthesis

The reviewed concepts and related studies support the development of a localized agricultural decision-support system that combines climate monitoring, rice yield prediction, advisories, dashboards, reports, and communication tools. The implemented iClimate system aligns with this direction by using Laravel, MySQL, Python machine learning integration, weather APIs, map visualization, AI assistance, and role-based workflows. The remaining manuscript work must focus on accurately documenting what the system actually implements and clearly marking unverified training, evaluation, and deployment details.

# CHAPTER III

# DESIGN AND METHODOLOGY

## Research Design

The study uses an applied developmental research design because its primary output is a functioning web-based system that addresses a practical agricultural decision-support problem. The development process combines system analysis, software engineering, data management, machine learning integration, and system testing.

The project follows an iterative development approach. Requirements were derived from the need to support rice farmers and municipal agricultural stakeholders in Lian, Batangas. The system was then designed around role-based access, climate and production records, prediction workflows, advisory management, dashboards, heat maps, reports, communication, and administration.

## System Development Methodology

The development process follows these stages:

- Requirements analysis: Identify users, objectives, modules, data needs, and decision-support workflows.
- System design: Define Laravel routes, controllers, models, views, database tables, role permissions, and service integrations.
- Development: Implement authentication, dashboards, CRUD modules, prediction workflows, advisory system, reports, heat maps, AI assistant, communication modules, and administrative tools.
- Testing and debugging: Validate authentication, role authorization, security controls, AI responses, prediction validation, reports, heat maps, notifications, advisory workflows, and typhoon safety responses.
- Evaluation: Assess system behavior through automated tests and prepare for user evaluation. User evaluation data remains Needs Verification.

## System Architecture

iClimate uses a layered Laravel architecture. Browser clients access Blade-rendered pages through Laravel routes. Controllers handle requests, apply validation and authorization, call service classes, and return views or responses. Eloquent models manage database entities through MySQL/MariaDB. Service classes handle weather APIs, prediction integration, advisory generation, heat map risk scoring, AI workflows, and security utilities.

The Python layer is used for machine learning prediction rather than as the main backend. Rice yield prediction uses `python_scripts/predict.py`, which loads `storage/models/rice_yield_model_final.pkl`. The AI assistant can use the Flask farming assistant API at `127.0.0.1:5001` for combined weather and yield prediction workflows.

External integrations include Open-Meteo, OpenWeather, PAGASA advisory pages, and optional Groq Chat Completions. The system stores weather data where needed and uses service classes to normalize external responses.

## Database Design

The database contains normalized module tables connected mainly through user and module-specific foreign keys. The `users` table stores account, role, and status information. The `farmer_profiles` table connects to `users` through a one-to-one relationship. Climate and production records are stored in separate tables to support dashboards, reports, prediction, and heat map risk analysis.

Advisory-related data is stored in `planting_advisories`, `advisory_rules`, and `external_weather_data`. This design supports both manually created advisories and automated online advisory generation based on weather data and rule conditions. The `notifications` table stores in-app notifications. The community feed uses `feed_posts`, `feed_media`, `feed_comments`, and `feed_reactions`. Direct messaging uses `conversations` and `conversation_messages`. AI chat history uses `a_i_chats` and `knowledge_base`.

Some database design limitations remain. Barangays are represented through strings, arrays, and local GeoJSON rather than a normalized barangay table. The `planting_advisories` table contains both legacy fields and expanded advisory-system fields, which should be explained as schema evolution. These limitations do not prevent the system from functioning but should be documented as future normalization opportunities.

## User Roles and Access Control

The implemented system has three coded roles: Farmer, MAO Personnel, and IT Expert.

Farmers can access their dashboard, advisories, calendar, community feed, messages, notifications, climate record views, weather prediction, heat map, rice production views, AI assistant, profile functions, and typhoon safety response when applicable.

MAO Personnel can access the MAO dashboard, farmer profiles, climate records, rice production records, planting advisories, advisory management workflows, weather prediction, live forecasting, heat maps, community feed, messages, reports, notifications, AI assistant, and typhoon safety response summaries.

IT Experts can access the IT dashboard, reports, user management, system logs, farmer profiles, climate records, weather prediction, model evaluation, live forecasting, rice production, heat maps, advisories, community feed, messages, and AI assistant.

## Machine Learning Implementation

The implemented system contains two deployed model files: `storage/models/weather_model.pkl` and `storage/models/rice_yield_model_final.pkl`. Rice yield prediction uses Python to load the deployed rice model and generate a predicted yield in tons per hectare from weather and farm-related input features.

The rice prediction input features include rainfall, average temperature, temperature range, area, previous rainfall, previous temperature, six-month rainfall, three-month temperature, six-month temperature, seasonal rainfall, seasonal temperature, and season. The prediction workflow returns the predicted yield and related decision-support guidance such as planting advisories, irrigation recommendations, and warning messages.

The model evaluation page displays the following algorithm comparison:

| Algorithm | RMSE | MAE | R2 | Status |
|---|---:|---:|---:|---|
| Multiple Linear Regression | 0.863691 | 0.651078 | 0.120158 | Compared Model |
| Random Forest | 0.802549 | 0.591967 | 0.240319 | Selected Model |
| Gradient Boosting | 0.897037 | 0.653737 | 0.050907 | Compared Model |

Random Forest is identified as the selected deployed model because it has the lowest RMSE and MAE and the highest R2 among the compared algorithms.

Needs Verification: The original training notebook, source datasets, preprocessing procedures, hyperparameter tuning, cross-validation, train-test split, and full model training evidence were not found in the repository.

## AI Assistant Implementation

The PalayPilot AI assistant supports user questions related to weather prediction, rice yield prediction, planting recommendation, irrigation recommendation, climate risk, farming advisory, announcements, notifications, calendar, profiles, system help, general agriculture, barangay information, MAO reports, and IT system status.

The assistant workflow begins with intent and language detection. It then searches system knowledge, database-aware answers, built-in responses, prediction services, and fallback logic. When prediction is required, the assistant can call the Python Flask API. If the trained model service is unavailable, local fallback rules provide cautious estimates. Optional Groq integration may be used for supported answer generation, but prompts restrict the assistant from inventing private records, exact weather values, or unsupported prediction results.

## Weather and Advisory Implementation

Weather-related implementation uses multiple sources. Historical climate observations are stored in `climate_records`. Open-Meteo provides stored forecast data through `OpenMeteoService`. OpenWeather provides live forecast summaries through `WeatherApiService`. PAGASA advisory pages are retrieved and filtered through `PagasaAdvisoryService` for relevant Lian, Batangas advisories.

The advisory system uses `advisory_rules`, `external_weather_data`, and `planting_advisories`. Generated advisories may be published immediately or marked for review. MAO Personnel and IT Experts can approve, reject, publish, archive, refresh weather, and regenerate advisories.

## Security Implementation

The implemented system uses Laravel authentication, password hashing, CSRF protection, role middleware, verified-user middleware, request validation, CAPTCHA on authentication pages, throttling on sensitive endpoints, inactive-user login blocking, and security headers. Security headers include X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, Cross-Origin-Opener-Policy, and authenticated response cache controls.

The `.env` file contains sensitive API configuration and should not be included in manuscript screenshots, appendices, or public repositories without redaction and key rotation.

## Testing Procedure

The system was verified through the Laravel automated test suite. The latest test run produced 105 passing tests and 435 assertions. The tests cover AI chat behavior, authentication, registration, password reset, email verification, community feed, messaging, weather prediction, rice yield prediction validation, model evaluation access, reports, heat maps, notifications, role authorization, security hardening, online agricultural advisories, farmer dashboard weather, planting advisory barangay filtering, and typhoon safety response validation.

Needs Verification: User evaluation instruments, respondent counts, sampling results, statistical treatment, and acceptance-test forms were not found in the repository.

# CHAPTER IV

# RESULTS AND DISCUSSION

## System Implementation Overview

iClimate was implemented as a Laravel-based web application for weather-aware rice farming support in Lian, Batangas. The system provides a centralized platform where farmers, MAO Personnel, and IT Experts can access role-appropriate features. The implementation confirms the project's core objective of developing a web-based decision-support system with weather analysis, rice yield prediction, planting advisory, climate monitoring, heat map visualization, reports, advisory notification, and user management.

The implemented system also includes additional support modules that strengthen the platform, including PalayPilot AI assistant, community feed, direct messaging, live forecasting, online advisory automation, model evaluation, system logs, mobile authentication API, and typhoon safety responses.

## Screen Mapping

| Screen | Purpose | Role | Screenshot Placeholder |
|---|---|---|---|
| Landing/Home | Public entry page and system introduction | Public | [Insert Screenshot: Home] |
| Login | User authentication with CAPTCHA | Public | [Insert Screenshot: Login] |
| Register | Account creation and farmer-related information | Public | [Insert Screenshot: Register] |
| Farmer Dashboard | Farmer weather, advisories, notifications, feed, and risk summary | Farmer | [Insert Screenshot: Farmer Dashboard] |
| MAO Dashboard | Agricultural monitoring, charts, records, reports, risks, and typhoon response counts | MAO Personnel | [Insert Screenshot: MAO Dashboard] |
| IT Dashboard | User, log, role, module, report, and system oversight summary | IT Expert | [Insert Screenshot: IT Dashboard] |
| Farmer Profiles | Farm and farmer profile management | Farmer, MAO, IT | [Insert Screenshot: Farmer Profiles] |
| Climate Records | Historical weather record management | Farmer, MAO, IT | [Insert Screenshot: Climate Records] |
| Rice Production | Rice yield and production record management | Farmer, MAO, IT | [Insert Screenshot: Rice Production] |
| Planting Advisories | Advisory viewing and dissemination | Farmer, MAO, IT | [Insert Screenshot: Planting Advisories] |
| Advisory Management | Review, approve, reject, publish, archive, regenerate, and refresh advisories | MAO, IT | [Insert Screenshot: Advisory Management] |
| Weather Prediction | Weather and rice yield prediction workflow | Farmer, MAO, IT | [Insert Screenshot: Weather Prediction] |
| Live Forecasting | Forecast map and barangay detail page | Farmer, MAO, IT | [Insert Screenshot: Live Forecasting] |
| Heat Map | Barangay agricultural risk visualization | Farmer, MAO, IT | [Insert Screenshot: Heat Map] |
| Reports | Report generation, print, CSV export, and report history | MAO, IT | [Insert Screenshot: Reports] |
| Notifications | In-app notification inbox and sending workflow | Farmer, MAO, IT | [Insert Screenshot: Notifications] |
| Community Feed | Posts, media, comments, reactions, and announcements | Farmer, MAO, IT | [Insert Screenshot: Community Feed] |
| Messages | Direct user conversations and attachments | Farmer, MAO, IT | [Insert Screenshot: Messages] |
| PalayPilot AI Assistant | Conversational farming and system support | Farmer, MAO, IT | [Insert Screenshot: PalayPilot] |
| Model Evaluation | Algorithm comparison and selected model metrics | IT Expert | [Insert Screenshot: Model Evaluation] |
| User Management | User account CRUD and role/status control | IT Expert | [Insert Screenshot: User Management] |
| System Logs | Administrative log viewing | IT Expert | [Insert Screenshot: System Logs] |

## Dashboard Results

The Farmer dashboard provides weather and advisory information in a user-focused format. It includes recent climate summaries, advisories, notifications, high-risk heat map areas, community feed data, and typhoon safety response prompts when applicable.

The MAO dashboard supports monitoring and municipal agricultural management. It displays climate charts, weather source labels, recent records, rice production information, advisory data, feed activity, heat map risk counts, reports, farmer information, and typhoon safety response counts.

The IT dashboard supports administration. It displays user counts, active and inactive accounts, role counts, system logs, module counts, reports, and high-risk areas.

## Prediction Results

The weather prediction module uses stored climate records and a PHP-implemented Random Forest approach to predict monthly rainfall, temperature, humidity, and wind speed. The rice yield prediction workflow calls the deployed Python model to estimate yield in tons per hectare.

The model evaluation page reports Random Forest as the selected rice yield algorithm, with RMSE 0.802549, MAE 0.591967, and R2 0.240319. Multiple Linear Regression and Gradient Boosting are retained as compared models.

Needs Verification: The final results chapter should include training dataset details and model training evidence if the notebook and original datasets are supplied.

## Advisory and Weather Results

The advisory module supports both manual and automated advisory workflows. Open-Meteo weather data is stored and evaluated by advisory rules. PAGASA online advisory pages are filtered for official Lian, Batangas relevance. Generated advisories can be reviewed, approved, rejected, published, archived, refreshed, or regenerated by authorized users.

This implementation supports the objective of delivering planting and climate advisories to farmers. The notification channel confirmed by implementation is in-app notification.

## Heat Map Results

The heat map module visualizes barangay-level agricultural risk using Leaflet, Leaflet.heat, local GeoJSON, and `heatmap_areas` data. Risk levels include Low, Moderate, High, and Severe, while risk types include Flood, Drought, Typhoon, and Heat. The map supports spatial interpretation of climate and agricultural risk in Lian, Batangas.

## AI Assistant Results

PalayPilot provides conversational support for supported farming and system questions. It can answer system help questions, explain prediction methods, use recent forecast context, answer in Tagalog or mixed language, and fall back to safe local rules when model services are unavailable. The test suite confirms AI chat behavior, supported fallback handling, and rejection of unsupported system questions.

## Reports, Communication, and Notification Results

The reports module generates climate, rice production, farmer registration, advisory, and community feed reports. It supports web viewing, print view, CSV export, and saved report history.

The community feed allows posts, media, comments, reactions, visibility control, archiving, editing, and deletion. The messaging module allows direct conversations between users with optional attachments. The notification module supports database-backed in-app notices, recipient scopes, read status, and mark-all-read functions.

## Security and Testing Results

The system uses role-based access control, CAPTCHA, throttling, security headers, CSRF protection, request validation, password hashing, and inactive-user login blocking. The test suite passed 105 tests with 435 assertions, confirming broad functionality across authentication, authorization, AI, prediction, reports, heat maps, notifications, advisories, community feed, messaging, and typhoon safety.

# CHAPTER V

# SUMMARY, CONCLUSIONS, AND RECOMMENDATIONS

## Summary

iClimate was developed as a web-based weather impact analysis and rice yield prediction system for Lian, Batangas. The system integrates climate monitoring, rice production records, weather prediction, rice yield prediction, planting advisories, heat map visualization, reports, in-app notifications, user management, and role-based dashboards. The implementation also includes PalayPilot AI assistant, community feed, messaging, online advisory automation, live forecasting, model evaluation, system logs, mobile authentication endpoints, and typhoon safety responses.

The system uses Laravel 12.62.0, PHP 8.2.12, MySQL/MariaDB, Blade, Bootstrap, Tailwind tooling, Vite, Chart.js, Leaflet, Leaflet.heat, Python scripts, Flask, Open-Meteo, OpenWeather, PAGASA advisory filtering, and optional Groq AI integration. Automated testing confirms major system behaviors with 105 passing tests and 435 assertions.

## Conclusions

Based on the implemented system, the following conclusions are drawn:

1. The system supports the collection, storage, and analysis of climate and rice production records through `climate_records` and `rice_productions`. However, the original source dataset files and preprocessing artifacts remain Needs Verification.
2. The system presents a comparison of Multiple Linear Regression, Random Forest, and Gradient Boosting. Random Forest is selected based on the displayed RMSE, MAE, and R2 metrics. The deployed model is integrated into the prediction workflow, but the original training notebook remains Needs Verification.
3. The system successfully implements a web-based decision-support platform with the major objective modules and additional communication, AI, advisory automation, and safety-response features.
4. The system has automated testing evidence for functionality, authorization, security, prediction validation, AI behavior, reports, heat maps, notifications, advisories, community feed, messaging, and typhoon safety. User evaluation results remain Needs Verification.

## Recommendations

The following recommendations are based on the implemented system, identified limitations, and verification gaps:

1. Supply and archive the original ML training notebook, datasets, preprocessing scripts, and evaluation outputs so the model development process can be fully defended during panel review.
2. Add formal user evaluation instruments, respondent summaries, statistical treatment, and acceptance-test documentation to support the usability and reliability parts of the fourth objective.
3. Capture screenshots for every implemented screen and insert them into Chapter IV with discussion and captions.
4. Implement and test email or SMS notifications only if they will be claimed in the final manuscript.
5. Implement PDF or Excel export only if those formats will be claimed in reports documentation.
6. Add automated backup and restore procedures before claiming operational backup readiness.
7. Normalize barangay data into a dedicated table in a future version to strengthen database design and referential integrity.
8. Prepare production deployment documentation, including server configuration, HTTPS, environment variable handling, queue scheduling, Python service management, and API key protection.
9. Continue expanding PalayPilot's verified knowledge base while maintaining safeguards against hallucinated private records or unsupported predictions.
10. Conduct further model improvement using larger verified datasets, additional climate variables, and documented validation methods.

## Final Verification Status

| Area | Status |
|---|---|
| Chapter I | Revised and aligned with implementation |
| Chapter II | Revised with implementation-aware technical background |
| Chapter III | Revised around Laravel, database, ML, AI, security, and testing |
| Chapter IV | Drafted with screen placeholders and implementation results |
| Chapter V | Drafted with objective-based conclusions and recommendations |
| Objectives | Preserved |
| Unsupported claims | Marked Needs Verification |
| Screenshots | Still required |
| Diagrams | To be produced in Phase 4 |
| Research documents and appendices | To be produced in later phases |

## Phase 3 Completion Checklist

- [x] Objectives preserved.
- [x] Chapter I revised.
- [x] Chapter II revised.
- [x] Chapter III revised.
- [x] Chapter IV drafted.
- [x] Chapter V drafted.
- [x] Unsupported ML/evaluation/deployment items marked Needs Verification.
- [x] Implemented modules added to the manuscript draft.
- [x] Laravel/PHP backend corrected.
- [x] Python/Flask ML integration correctly positioned.
- [x] In-app notification limitation documented.
- [x] Report export limitation documented.

## Recommended Next Step

Proceed to Phase 4: Diagrams. Required diagrams should be generated from the implemented architecture, database, routes, roles, and workflows documented in Phases 1 to 3.
