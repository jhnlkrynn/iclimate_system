# 7. Input–Process–Output Model

## 7.1 System-Level IPO

| Input | Process | Output |
|---|---|---|
| **From users:** farmer registration details; farm, planting schedule, and harvest data; climate records; rice production records; barangay profiles; advisory text and decisions; alert details; messages; survey and feedback responses; account, setting, and permission data | **Account processing:** validation, CAPTCHA, hashing, approval, role and module checks, audit logging | **For farmers:** dashboard, forecast, risk level, yield estimate, advisories and alerts, calendar, My Reports (PDF), messages, notifications, chatbot answers |
| **From external services:** Open-Meteo current weather and daily forecast; PAGASA bulletins; GeoRisk barangay boundaries; Groq responses | **Data processing:** CSV import, unit conversion to t/ha, record validation, provenance stamping | **For MAO Personnel:** dashboard, Heat Map, weather and impact analyses, yield forecasts, validated records, reports (CSV, XLSX, PDF, print), activity log |
| **From stored data:** PAGASA Ambulong monthly climate history; MAO seasonal production records; NSIC rice variety catalogue; advisory rules; chatbot knowledge | **Analytical processing:** weekly risk rating, composite risk scoring, monthly weather forecasting (Random Forest), rice yield prediction (Random Forest), correlation analysis, model comparison (RMSE, MAE, R²) | **For IT Expert:** system health, audit logs, backup files and verification results, model registry and evaluation, model feedback summary |
| **From the system clock:** schedule triggers | **Decision support:** rule-based advisory generation, review and publication, alert scheduling, notification delivery | **Stored results:** forecasts, estimates, risk ratings, advisories, reports, logs, backups |
| | **Administration:** backup, verification, restoration, model validation and activation, maintenance mode | |

## 7.2 Module-Level IPO

### 7.2.1 Authentication and accounts

| Input | Process | Output |
|---|---|---|
| Name, e-mail, password, barangay, associations, CAPTCHA answer | Validate; verify CAPTCHA; hash password; create user and profile; set Pending status; notify MAO | Pending account; confirmation message |
| E-mail, password, CAPTCHA answer | Check lockout; verify CAPTCHA; verify hash; check status; write audit entry | Session and role dashboard, or refusal with reason |
| Approval or rejection with reason | Update status; record approver; notify and e-mail farmer | Active or Rejected account |
| New password | Apply password policy; hash; clear change flag | Updated credentials |

### 7.2.2 Farmer dashboard

| Input | Process | Output |
|---|---|---|
| Farmer identity, active farm, language | Read stored weather, risk, yield estimate, advisory, and schedule data; compute indicators and checklist; queue a yield estimate when due | Bilingual dashboard with weather, risk badge, expected yield, seven-day forecast, latest advice |

### 7.2.3 Farm management

| Input | Process | Output |
|---|---|---|
| Farm name, barangay, location, size, unit, farm type, association | Validate; confirm ownership; convert size to hectares; archive or restore; set primary | Farm list; active farm |
| Planting date, variety, establishment method, area | Derive season; estimate harvest date from variety maturity | Planting schedule; calendar entries |
| Harvest date, actual yield or total harvest, timing reason | Convert to t/ha; classify timing; require reason; store beside predicted value | Harvest record; predicted-versus-actual pair |

### 7.2.4 Weather acquisition

| Input | Process | Output |
|---|---|---|
| Coordinates of nineteen barangays; schedule trigger | Batched request to Open-Meteo; fallback per barangay; store last valid record; log fetch; classify freshness; broadcast change | Current weather and sixteen-day daily forecast per barangay |

### 7.2.5 Risk rating and Heat Map

| Input | Process | Output |
|---|---|---|
| Stored daily forecast; farm type; typhoon advisory status; thresholds; period | Evaluate heavy rain, wind, heat, and absence of rain; take the worst level; identify the main factor | Risk level per barangay (Low, Moderate, High, Severe, or No data) with explanation |
| Monthly rainfall, temperature, last recorded yield, farm type | Weighted composite score | Risk score and components |
| Barangay boundaries, risk levels, barangay profile marks, yield estimates | Assemble map layers | Interactive map with four layers |

### 7.2.6 Monthly weather forecast

| Input | Process | Output |
|---|---|---|
| Monthly climate history (rainfall, maximum and minimum temperature, humidity, wind speed and direction) | Build lag, rolling, and climatological features; train Random Forest with a strict cut-off; predict each target month | Monthly forecast for a twelve-month horizon |

### 7.2.7 Rice yield prediction

| Input | Process | Output |
|---|---|---|
| Farm area, season, target month; climate history; monthly forecast | Assemble model input (twelve features); check completeness; send to the ML service or run locally; round; compare with historical average; classify | Predicted yield (t/ha), total tons, historical average, impact level; or "unavailable" with the missing inputs |

### 7.2.8 Weather and impact analysis

| Input | Process | Output |
|---|---|---|
| Climate history; filters by year, season, month | Aggregate; compare seasons; flag heavy-rain, dry, and hot months | Charts and tables of monthly climate |
| Climate history; monthly yield record | Pair by month; compute correlation per variable; rank; assign impact level | Ranked correlation table; saved analysis |

### 7.2.9 Advisories and alerts

| Input | Process | Output |
|---|---|---|
| Stored forecast; active advisory rules | Compute forecast metrics; evaluate rule conditions; create advisory with a unique key | Draft advisory |
| PAGASA web pages | Download; parse bulletin content; store | Official advisory; typhoon status |
| Advisory text; MAO decision | Approve, reject, publish, or archive; notify farmers | Published advisory; notifications |
| Alert type, severity, message, recipients, time | Create advisory per barangay; publish now or at the scheduled time; count delivery and reads | Alert; delivery report |
| Farmer rating and comment | Store feedback; store MAO response | Feedback record |

### 7.2.10 Records management

| Input | Process | Output |
|---|---|---|
| Climate record fields | Validate; check uniqueness; stamp author | Climate record |
| Production record fields, including total harvest in tons, kilograms, or sacks | Validate; convert to tons; compute yield; stamp author; check barangay coverage | Production record |
| CSV file | Check each row with form rules; import valid rows | Import summary with skipped rows and reasons |
| Validate or unvalidate command | Set or clear verification stamp | Verified status |

### 7.2.11 Farmer administration

| Input | Process | Output |
|---|---|---|
| Search text | Match every word against name, identifier, barangay, and association within coverage | Up to twelve matching farmers |
| Farmer profile fields | Validate; synchronise association memberships | Farmer profile |
| Association decision for a farm | Link, create, or reject; record who decided | Updated association status |

### 7.2.12 Reports

| Input | Process | Output |
|---|---|---|
| Report type, date range, barangay, season | Create analytics run; build rows and summary; save with series key and version | Report on screen |
| Export format | Render CSV, XLSX (PhpSpreadsheet), or PDF (dompdf) | Downloadable file; print view |
| Publish command | Set status and publication time | Published report |

### 7.2.13 Communication

| Input | Process | Output |
|---|---|---|
| Triggering event | Insert notification; broadcast | Bell count; inbox entry |
| Recipient, message, attachment | Open or reuse conversation; store; broadcast; restrict attachment access | Message thread |
| Post title, body, media | Validate; store; mark for calendar | Announcement |
| Contact form | Validate; rate limit; notify MAO | Confirmation |

### 7.2.14 Climora AI assistant

| Input | Process | Output |
|---|---|---|
| Question; user role, barangay, language; conversation memory | Moderate; detect language; classify intent; answer from approved knowledge, then stored system data, then Groq when enabled; shorten for farmers | Answer with source, confidence, and response time; stored exchange |

### 7.2.15 IT administration

| Input | Process | Output |
|---|---|---|
| Account fields, status, role | Validate; save; clear cached user; audit | Updated user list |
| Setting values, permission matrix | Validate; store differences from defaults in the settings file | Applied settings and permissions |
| Backup or restore command | Run dump tool; prune; verify; take a safety backup before restoring | Backup file; verification record |
| Health request | Run seventeen checks against stored run data and services | Health status |
| Log filters | Query audit entries | Log list; CSV export |

### 7.2.16 Machine learning management

| Input | Process | Output |
|---|---|---|
| Modelling dataset | Train and evaluate three algorithms on a hold-out year | Comparison of RMSE, MAE, and R² |
| Algorithm choice | Retrain; save artefact and metadata; register as candidate | Candidate model |
| Model file | Store; register | Registry entry |
| Validate and activate commands | Check file and metadata; switch the Python service; confirm | One active model per slot |
| Farmer survey responses | Summarise by model version; record decision | Feedback summary; decision record |
