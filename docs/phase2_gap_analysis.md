# Phase 2 - Manuscript Gap Analysis

Project: iClimate: A Web-Based Weather Impact Analysis and Rice Yield Prediction System for Lian, Batangas  
Inspection date: 2026-07-21  
Compared documents: implemented system in `C:\xampp\htdocs\iclimate`, Phase 1 reverse-engineering report, and `Finalized Manuscript iClimate .docx`

## 1. Executive Gap Findings

The manuscript is not yet final. The attached DOCX contains Chapter I, Chapter II, and Chapter III only. Chapter IV and Chapter V were not found in the Word document headings, and the current text still reads mostly as a proposal using future-tense phrases such as "will develop," "will use," and "will evaluate."

The actual implemented system is broader than the draft manuscript. The Laravel application includes role-based dashboards, authentication with CAPTCHA/security headers, weather prediction, rice yield prediction, AI farming assistant, planting advisory management, Open-Meteo/OpenWeather/PAGASA integration, heat maps, community feed, direct messaging, reports, notifications, mobile auth API, typhoon safety responses, model evaluation, and system logs.

The manuscript objectives should remain unchanged. However, several sections need to be rewritten so the system description matches the implementation instead of a planned design.

## 2. Objective Alignment

| Manuscript Objective | Implementation Status | Correction Needed |
|---|---|---|
| Collect, preprocess, and analyze historical climate data from PAGASA and rice production records from MAO. | Partially confirmed. The database contains `climate_records` and `rice_productions`; climate source defaults to PAGASA in schema. Source dataset files and preprocessing notebooks are not in the repo. | Keep objective. Mark dataset provenance and preprocessing evidence as Needs Verification unless files are supplied. |
| Compare Multiple Linear Regression, Random Forest, and Gradient Boosting for rice yield prediction. | Partially confirmed. `ModelEvaluationService` displays comparison metrics and selects Random Forest, but the training notebook and source dataset files are not present. | Keep objective. Discuss deployed metrics as system evidence, and mark original training notebook/dataset as Needs Verification. |
| Develop a web-based decision-support platform with weather analysis, yield prediction, advisories, dashboard, heat map, reports, notifications, and user management. | Confirmed and expanded. The implemented system includes these modules plus AI assistant, community feed, messaging, online advisory automation, typhoon safety responses, live forecasting, model evaluation, system logs, and mobile auth endpoints. | Keep objective. Add the confirmed extra modules to scope, Chapter III implementation, and Chapter IV discussions. |
| Evaluate the platform in functionality, usability, reliability, efficiency, response time, and predictive accuracy. | Partially confirmed. Automated tests pass: 105 tests and 435 assertions. User evaluation respondents, instruments, and statistical treatment are not present in the repo. | Keep objective. Separate system testing evidence from user evaluation evidence. Mark survey/evaluation data as Needs Verification. |

## 3. Major Mismatches and Corrections

### 3.1 Manuscript Stops Before Chapter IV and Chapter V

Issue: The DOCX contains only Chapter I to Chapter III headings. No Chapter IV results/discussion and no Chapter V conclusion/recommendations were found.

Impact: A final capstone manuscript normally needs implementation results, screenshots, testing outcomes, conclusions, and recommendations. The current document cannot be treated as final.

Recommended correction: Add Chapter IV with system implementation, screen mapping, feature discussion, database/ML/AI results, and testing evidence. Add Chapter V with conclusions tied to objectives, limitations, recommendations, and future work.

### 3.2 Planned-System Language Does Not Match Implemented-System Reality

Issue: Many paragraphs say the proponents "will develop," "will use," "will implement," or "will evaluate." The system already exists and has current code, routes, migrations, tests, and database records.

Impact: Panelists may read the manuscript as a proposal rather than a completed capstone.

Recommended correction: Convert future-tense implementation claims into completed or present-tense academic prose. Example: use "The system was developed using Laravel 12..." or "iClimate provides..." instead of "The system will be developed..."

### 3.3 Backend Technology Is Incorrectly Framed

Issue: The manuscript describes Python with Flask or FastAPI as the backend. The implemented backend is Laravel/PHP. Python is used for model prediction and a Flask farming-assistant API, not as the main application backend. FastAPI was not found.

Confirmed implementation: Laravel 12.62.0, PHP 8.2.12, Blade, MySQL/MariaDB, Vite, Bootstrap, Tailwind tooling, Chart.js, Leaflet, Python scripts, Flask API.

Recommended correction: Describe Laravel as the main MVC backend. Describe Python/Flask as an ML/AI integration service. Remove FastAPI unless a FastAPI service is later supplied.

### 3.4 Weather Sources Are Incomplete

Issue: The manuscript emphasizes PAGASA but does not document Open-Meteo or OpenWeather, both of which are implemented.

Confirmed implementation: `OpenMeteoService` stores external forecasts, `WeatherApiService` maps OpenWeather forecasts, and `PagasaAdvisoryService` scrapes/filters PAGASA advisory pages.

Recommended correction: Explain three weather source categories: historical stored climate records, Open-Meteo/OpenWeather forecast APIs, and PAGASA advisory pages. Keep PAGASA in the historical/advisory discussion, but do not make it the only current weather source.

### 3.5 BSWM Data Claim Is Unsupported

Issue: The definition section says BSWM will provide soil-related information used in calibrating the rice yield prediction model.

Confirmed implementation: No BSWM files, BSWM API integration, soil table, or soil-data service was found. `external_weather_data` includes soil temperature and soil moisture fields from Open-Meteo, but this is not the same as BSWM-provided soil data.

Recommended correction: Remove or revise the BSWM claim unless actual BSWM dataset/API evidence is supplied. If retained, mark as Needs Verification and explain that the current implementation only stores soil-related forecast fields from Open-Meteo.

### 3.6 Evaluation Respondents Are Unsupported

Issue: The manuscript states random sampling will select 100 respondents: 75 farmers, 15 agricultural technicians, and 10 IT personnel.

Confirmed implementation: No survey forms, respondent dataset, evaluation summary, statistical treatment output, or raw user-evaluation records were found in the repository.

Recommended correction: Keep the evaluation objective but mark respondent counts and sampling results as Needs Verification. If actual evaluation data exists outside the repo, add it as appendices and summarize it in Chapter IV.

### 3.7 User Roles Need Correction

Issue: The manuscript refers to rice farmers, MAO personnel, agricultural technicians, and IT administrators/personnel. The implemented system has exactly three coded roles: Farmer, MAO Personnel, and IT Expert.

Confirmed implementation: Role constants are defined in `User.php` as `Farmer`, `MAO Personnel`, and `IT Expert`.

Recommended correction: Use the three implemented role names consistently. Agricultural technicians may be discussed as stakeholders under MAO if desired, but they should not be described as a separate system role unless a coded role is added.

### 3.8 AI Assistant Is Missing From the Manuscript Scope

Issue: The pasted project instructions mention an AI chatbot, and the implemented system includes PalayPilot, but the parsed manuscript uses no "chatbot" term and does not document the assistant in its major headings.

Confirmed implementation: `AIChatController`, `PredictionService`, `PythonService`, `GroqChatService`, `IntentDetectionService`, `KnowledgeBaseService`, `RoleAssistantService`, `a_i_chats`, and `knowledge_base`.

Recommended correction: Add AI assistant documentation in Chapter III and Chapter IV. Present it as a hybrid assistant using intent detection, knowledge base answers, database-aware answers, Python prediction integration, fallback rules, and optional Groq generation.

### 3.9 Notification Channel Must Be Limited To In-App Unless Proven Otherwise

Issue: The manuscript mentions advisory notification but does not clearly define the actual channel.

Confirmed implementation: The notification module is database/in-app. Mail configuration exists, but email notification dispatch for module alerts was not confirmed. SMS was not found.

Recommended correction: Describe notifications as in-app notifications. Do not claim SMS or email alert delivery unless that implementation is added and tested.

### 3.10 Reports Export Capabilities Must Be Precise

Issue: The implemented system supports report generation, web/print view, CSV export, and saved report history. PDF and Excel report export were not found in `ReportController`.

Confirmed implementation: `reports.export` maps to `ReportController@exportCsv`; `reports.print` maps to `ReportController@print`.

Recommended correction: State that reports can be viewed, printed, exported as CSV, and saved in report history. Do not claim PDF/Excel export unless implemented later.

### 3.11 Heat Map Provider Needs Specificity

Issue: The manuscript discusses heat map visualization but does not specify the actual map provider/stack.

Confirmed implementation: Leaflet, Leaflet.heat, local `public/geojson/lian-barangays.geojson`, and Lian barangay coordinates/risk records.

Recommended correction: Document Leaflet/OpenStreetMap-style mapping and local GeoJSON. Avoid Google Maps claims unless a Google Maps implementation is added.

### 3.12 ML Pipeline Details Are Not Fully Verifiable

Issue: The manuscript mentions dataset splitting, model training, hyperparameter tuning, and cross-validation.

Confirmed implementation: Deployed `.pkl` models exist, `predict.py` loads the rice-yield model, the Flask API loads the weather/yield models, and `ModelEvaluationService` displays metrics. Training notebook, source datasets, preprocessing scripts, hyperparameter tuning code, and cross-validation outputs were not found.

Recommended correction: In Chapter III, separate confirmed deployment details from training-process details. Training process claims should be labeled Needs Verification until the notebook/datasets are supplied.

### 3.13 Security Documentation Is Incomplete

Issue: The manuscript generally mentions authentication and role-based access control, but the implementation has more concrete security features.

Confirmed implementation: Laravel authentication, role middleware, CAPTCHA on auth pages, request validation, throttling on sensitive routes, CSRF protection, password hashing, inactive-user login blocking, and security headers.

Recommended correction: Add a security subsection documenting these implemented controls. Do not expose `.env` API keys in screenshots or appendices.

### 3.14 Implemented Modules Missing From Draft

The following implemented modules should be added or expanded:

- AI farming assistant / PalayPilot
- Community feed
- Direct messaging
- Typhoon safety response
- Online agricultural advisory automation
- Advisory review and lifecycle management
- Live forecasting
- Model evaluation page
- Mobile login/register API
- CAPTCHA and security headers
- System logs
- Role dashboards for Farmer, MAO Personnel, and IT Expert
- CSV report export and print view

## 4. Chapter-by-Chapter Gap Analysis

### Chapter I

Confirmed:

- Project title and general direction are aligned with the implemented system.
- Objectives are broadly compatible with the system and should not be removed.
- Scope includes major implemented modules such as weather analysis, rice yield prediction, advisories, dashboards, heat maps, reports, notifications, and user management.

Needs revision:

- Convert proposal/future tense to completed-system language.
- Add AI assistant, community feed, messaging, live forecasting, online advisory automation, typhoon safety, model evaluation, and system logs to the scope.
- Revise beneficiaries and user roles to match Farmer, MAO Personnel, and IT Expert.
- Revise BSWM definition or mark it Needs Verification.
- Mark 100-respondent/random-sampling claim Needs Verification unless evaluation data is supplied.

### Chapter II

Confirmed:

- Literature areas on climate variability, rice yield prediction, machine learning, dashboards, and decision-support systems are relevant.
- Random Forest, Gradient Boosting, and Multiple Linear Regression discussion aligns with the model comparison requirement.

Needs revision:

- Add literature support for hybrid AI assistants or agricultural chatbots if PalayPilot is retained as a major feature.
- Add literature support for web-based community communication and advisory dissemination if community feed/messaging are discussed in Chapter IV.
- Make the technical background reflect Laravel MVC and Python/Flask ML integration rather than Python/FastAPI as the main backend.
- Add API/weather-source discussion for Open-Meteo/OpenWeather/PAGASA.

### Chapter III

Confirmed:

- Agile/iterative development fits the observed system evolution and git history.
- Web application architecture, database storage, and ML integration are relevant.

Needs revision:

- Replace "Flask or FastAPI backend" with Laravel MVC backend and Python/Flask prediction integration.
- Add actual route/module workflow documentation.
- Add database design based on the implemented migrations and live schema.
- Add testing evidence from the current automated test suite.
- Separate implementation testing from user evaluation.
- Add security implementation details.
- Add deployment context as local XAMPP/Laravel/MySQL unless production hosting details are supplied.

### Chapter IV

Status: Missing from parsed manuscript.

Required content:

- Implementation overview
- Screen mapping with placeholders/screenshots
- Role-based dashboard discussion
- Module-by-module documentation
- Database table discussion
- ML prediction workflow and model evaluation
- AI assistant workflow
- Weather source integration
- Heat map implementation
- Reports/notifications/community/messaging discussion
- Security and testing results

### Chapter V

Status: Missing from parsed manuscript.

Required content:

- Conclusions mapped directly to each objective
- Recommendations based on results, limitations, and testing
- Future improvements such as verified training dataset publication, stronger deployment hardening, backup automation, optional SMS/email notification, mobile expansion, and more user-evaluation data

## 5. Claims That Should Be Marked Needs Verification

- Exact source files for PAGASA/MAO datasets
- BSWM soil-data usage
- Training notebook and preprocessing pipeline
- Hyperparameter tuning details
- Cross-validation results
- Train/test split method and exact sample sizes
- User-evaluation respondent count and sampling method
- Statistical treatment results
- Production deployment architecture
- Email notification delivery
- SMS notification delivery
- PDF/Excel report export
- Automated database backup
- University-specific capstone format checklist
- Final screenshots for every screen

## 6. Features Confirmed By Implementation

- Laravel web application
- MySQL/MariaDB database
- Role-based access for Farmer, MAO Personnel, and IT Expert
- Login, registration, password reset/update, profile management
- CAPTCHA and security headers
- Farmer, MAO, and IT dashboards
- Farmer profiles
- Climate records
- Rice production records
- Planting advisories
- Advisory management workflow
- Open-Meteo stored forecasts
- OpenWeather live forecast mapping
- PAGASA advisory scraping/filtering
- Weather prediction
- Rice yield prediction through Python model
- AI farming assistant / PalayPilot
- Heat map visualization using Leaflet/GeoJSON
- Reports with print and CSV export
- In-app notifications
- Community feed
- Direct messaging
- Calendar
- Live forecasting
- Model evaluation
- User management
- System logs
- Typhoon safety response
- Mobile login/register API
- Automated test suite with 105 passing tests

## 7. Required Corrections Before Chapter Revision

1. Preserve the objectives but rewrite their supporting discussion in present/past tense.
2. Replace unsupported technology claims with confirmed Laravel/PHP/MySQL/Python/Flask architecture.
3. Add all implemented modules missing from the draft.
4. Remove or mark unsupported data-source claims such as BSWM.
5. Limit notification claims to in-app notifications.
6. Limit report export claims to print and CSV.
7. Add Chapter IV and Chapter V.
8. Add screenshot placeholders for every screen identified in Phase 1.
9. Add testing results from `php artisan test`.
10. Mark training and evaluation items Needs Verification until evidence is supplied.

## 8. Recommended Phase 3 Rewrite Strategy

Phase 3 should rewrite the manuscript in this order:

1. Rewrite Chapter I to keep the original objectives but align scope, roles, significance, and limitations with the implementation.
2. Rewrite Chapter II to preserve relevant literature while adding missing literature for AI assistant, advisory automation, weather APIs, and Laravel-style web architecture.
3. Rewrite Chapter III around the actual architecture, database, modules, ML pipeline, AI workflow, security, testing, and local deployment context.
4. Draft Chapter IV from Phase 1 screen mapping and module evidence.
5. Draft Chapter V after Chapter IV results are stable.

## 9. Completed Outputs

- Manuscript headings extracted and reviewed.
- High-risk manuscript claims scanned.
- Objectives mapped against implemented features.
- Unsupported or partially supported claims identified.
- Chapter-by-chapter correction plan prepared.
- Phase 2 gap-analysis document created.

## 10. Remaining Missing Information

- University manuscript format/checklist.
- Actual ML training notebook and source datasets.
- Survey/evaluation instruments and respondent data.
- Final screenshots for every implemented screen.
- Production deployment details.
- Any required official forms, consent documents, budget, Gantt chart, or appendices not currently in the repo.

## 11. Phase 2 Completion Checklist

- [x] Manuscript structure inspected.
- [x] Chapter I-III status confirmed.
- [x] Chapter IV/V absence identified.
- [x] Objective alignment reviewed.
- [x] Technology mismatches identified.
- [x] Missing implemented modules listed.
- [x] Unsupported data/evaluation claims listed.
- [x] Recommended corrections prepared.
- [x] Phase 3 rewrite strategy prepared.

## 12. Recommended Next Step

Proceed to Phase 3: Chapter Revision. The rewrite should use Phase 1 as the implementation source of truth and Phase 2 as the correction map. Unsupported items should remain labeled Needs Verification instead of being silently presented as fact.
