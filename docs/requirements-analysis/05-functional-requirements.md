# 5. Functional Requirements

Each requirement describes a function that exists in the implementation. The **Status** column uses the following values:

- **Implemented**: the function is present in routes, controllers, and services.
- **Implemented, unverified**: the function executes, but the correctness of its result has not been established (see Document 9).
- **Partial**: part of the stated function is absent.
- **Hidden**: present in code and disabled by default through configuration.

Identifiers follow the pattern `FR-<module>-<number>` and are reused in the traceability matrix (Document 8).

## 5.1 Authentication and Account Management (AUTH)

| ID | Requirement | Actor | Status |
|---|---|---|---|
| FR-AUTH-01 | The system shall allow a visitor to register as a farmer by supplying a name, e-mail, password, barangay, and optional farmer associations. | Visitor | Implemented |
| FR-AUTH-02 | The system shall require a correct CAPTCHA answer for registration. | Visitor | Implemented |
| FR-AUTH-03 | The system shall place a new farmer account in Pending status until approved by MAO Personnel, when farmer approval is enabled. | System | Implemented |
| FR-AUTH-04 | The system shall authenticate users by e-mail and password and direct each user to the dashboard of the user's role. | All | Implemented |
| FR-AUTH-05 | The system shall lock sign-in after a configurable number of failed attempts for a configurable period and shall require a CAPTCHA after repeated failures. | System | Implemented |
| FR-AUTH-06 | The system shall refuse sign-in to Pending, Rejected, Inactive, and Deleted accounts and state the reason. | System | Implemented |
| FR-AUTH-07 | The system shall sign out an account on its next request once its status is no longer Active. | System | Implemented |
| FR-AUTH-08 | The system shall allow a user to request a password reset link by e-mail. | All | Partial (requires an SMTP server; the default mailer writes to the log) |
| FR-AUTH-09 | The system shall require an account flagged for password change to set a new password at first sign-in. | All | Implemented |
| FR-AUTH-10 | The system shall allow a user to update the profile, photo, and password, and to delete the user's own account. | All | Implemented |
| FR-AUTH-11 | The system shall record each sign-in, sign-out, and failed attempt in the audit log. | System | Implemented |
| FR-AUTH-12 | The system shall verify the e-mail address of a new account. | System | **Missing** (routes exist; the `User` model does not implement `MustVerifyEmail`) |
| FR-AUTH-13 | The system shall provide API endpoints for mobile sign-in and registration. | Mobile client | Partial (no access token is issued; no client exists) |

## 5.2 Farmer Dashboard and Farm Management (FARM)

| ID | Requirement | Actor | Status |
|---|---|---|---|
| FR-FARM-01 | The system shall display to the farmer the current weather, weekly risk level, expected yield, seven-day forecast, and latest advisory for the farmer's barangay. | Farmer | Implemented |
| FR-FARM-02 | The system shall allow the farmer to select English or Filipino and shall store the selection. | Farmer | Implemented |
| FR-FARM-03 | The system shall allow the farmer to add, edit, archive, and restore farms and to designate one primary farm. | Farmer | Implemented |
| FR-FARM-04 | The system shall restrict every farm operation to the farms of the signed-in farmer. | System | Implemented |
| FR-FARM-05 | The system shall allow the farmer to save or delete the map location of a farm. | Farmer | Implemented |
| FR-FARM-06 | The system shall allow the farmer to draw a farm boundary polygon. | Farmer | **Removed** (October 2026; the routes redirect to My Farm) |
| FR-FARM-07 | The system shall allow the farmer to declare a farm's association or defer the answer. | Farmer | Implemented |
| FR-FARM-08 | The system shall allow the farmer to create, edit, and delete planting schedules with rice variety and crop establishment method. | Farmer | Implemented |
| FR-FARM-09 | The system shall compute the expected harvest date from the planting date and the maturity of the variety. | System | Implemented |
| FR-FARM-10 | The system shall allow the farmer to record the actual harvest in t/ha or as a total in sacks, kilograms, or tons, and shall convert the total to t/ha. | Farmer | Implemented |
| FR-FARM-11 | The system shall classify a harvest as on time, early, or late and shall require a reason for an early or late harvest. | System | Implemented |
| FR-FARM-12 | The system shall display an activity calendar of planting and harvest events. | Farmer | Implemented |
| FR-FARM-13 | The system shall prompt the farmer to complete missing profile and farm information. | System | Implemented |

## 5.3 Weather and Forecast (WX)

| ID | Requirement | Actor | Status |
|---|---|---|---|
| FR-WX-01 | The system shall retrieve current weather and a daily forecast for each of the nineteen barangays from Open-Meteo every ten minutes. | Scheduler | Implemented |
| FR-WX-02 | The system shall retain the last valid weather record when a refresh fails and shall label its freshness. | System | Implemented |
| FR-WX-03 | The system shall log each weather fetch with provider, duration, and status. | System | Implemented |
| FR-WX-04 | The system shall display stored daily forecasts on the Predictions page. | Farmer, MAO | Implemented |
| FR-WX-05 | The system shall generate monthly forecasts of rainfall, temperature, humidity, and wind for a twelve-month horizon. | Scheduler | Implemented, unverified |
| FR-WX-06 | The system shall present monthly climate by year, season, or month and compare the wet and dry seasons. | Farmer, MAO | Implemented |
| FR-WX-07 | The system shall allow the farmer to rate the accuracy of a forecast. | Farmer | Implemented |
| FR-WX-08 | The system shall display current weather on the landing and sign-in page. | Visitor | Implemented |

## 5.4 Heat Map and Risk (RISK)

| ID | Requirement | Actor | Status |
|---|---|---|---|
| FR-RISK-01 | The system shall rate each barangay as Low, Moderate, High, or Severe from the stored forecast for a seven-day and a fourteen-day period. | System | Implemented, thresholds unconfirmed |
| FR-RISK-02 | The system shall rate all barangays Severe while a typhoon advisory is active. | System | Implemented |
| FR-RISK-03 | The system shall report "No data" for a barangay without a stored forecast. | System | Implemented |
| FR-RISK-04 | The system shall display the barangays on a map coloured by risk level, with the main weather factor and a crop-impact note. | Farmer, MAO | Implemented |
| FR-RISK-05 | The system shall provide map layers for flood-prone areas, drought-prone areas, and predicted yield. | Farmer, MAO | Implemented |
| FR-RISK-06 | The system shall compute a composite agricultural risk score per barangay. | System | Implemented, unverified |
| FR-RISK-07 | The system shall allow IT Personnel to override the rain, wind, and heat thresholds. | IT | Implemented |

## 5.5 Yield Prediction (YLD)

| ID | Requirement | Actor | Status |
|---|---|---|---|
| FR-YLD-01 | The system shall estimate rice yield in t/ha for each farm and store the estimate. | Scheduler | Implemented, unverified |
| FR-YLD-02 | The system shall derive a barangay estimate as the area-weighted mean of its farm estimates. | System | Implemented |
| FR-YLD-03 | The system shall allow a farmer to request a yield prediction by season, irrigation type, barangay, and area. | Farmer | Implemented, unverified |
| FR-YLD-04 | The system shall process a prediction request in the background and report its status. | System | Implemented |
| FR-YLD-05 | The system shall report a prediction as unavailable when a required input is missing and shall not substitute a value. | System | Implemented |
| FR-YLD-06 | The system shall compare the predicted yield with the historical average and assign an impact level. | System | Implemented |
| FR-YLD-07 | The system shall allow MAO Personnel to run a yield forecast for a barangay or the municipality. | MAO | Implemented, unverified |
| FR-YLD-08 | The system shall present predicted yield beside actual yield. | MAO | Implemented (present data are largely seeded) |
| FR-YLD-09 | The system shall compute the correlation of each climate variable with rice yield and rank the variables. | MAO | Implemented |
| FR-YLD-10 | The system shall allow MAO Personnel to save an impact analysis and view its history. | MAO | Implemented |

## 5.6 Advisories and Alerts (ADV)

| ID | Requirement | Actor | Status |
|---|---|---|---|
| FR-ADV-01 | The system shall generate planting advisories from advisory rules and the stored forecast. | Scheduler, MAO | Implemented |
| FR-ADV-02 | The system shall retrieve and store PAGASA advisories every fifteen minutes. | Scheduler | Implemented (depends on the format of the PAGASA pages) |
| FR-ADV-03 | The system shall allow MAO Personnel to create, edit, approve, reject, publish, and archive advisories. | MAO | Implemented |
| FR-ADV-04 | The system shall show a farmer only active advisories for the farmer's barangay, municipality-wide advisories, and official advisories. | System | Implemented |
| FR-ADV-05 | The system shall notify the farmers of the target barangay when MAO Personnel publish an advisory. | System | Implemented |
| FR-ADV-06 | The system shall allow a farmer to give feedback on an advisory and MAO Personnel to respond. | Farmer, MAO | Implemented |
| FR-ADV-07 | The system shall allow MAO Personnel to send an alert to all or selected barangays immediately or at a scheduled time. | MAO | Implemented |
| FR-ADV-08 | The system shall publish a scheduled alert within one minute of its time and allow cancellation before publication. | Scheduler, MAO | Implemented |
| FR-ADV-09 | The system shall report delivery and read counts for each alert. | MAO | Implemented |
| FR-ADV-10 | The system shall expire advisories after their validity period. | Scheduler | Implemented |
| FR-ADV-11 | The system shall display the forecast weather for the place and period of an advisory. | Farmer, MAO | Implemented |
| FR-ADV-12 | The system shall allow a user to respond to a typhoon safety check-in. | Farmer | Implemented |

## 5.7 Records Management (REC)

| ID | Requirement | Actor | Status |
|---|---|---|---|
| FR-REC-01 | The system shall allow MAO Personnel to add, edit, delete, filter, and print climate records. | MAO | Implemented |
| FR-REC-02 | The system shall allow MAO Personnel to add, edit, delete, filter, and print rice production records. | MAO | Implemented |
| FR-REC-03 | The system shall compute yield per hectare from total harvest and area harvested when the yield is blank. | System | Implemented |
| FR-REC-04 | The system shall import climate and production records from a CSV file and report the rows imported and skipped. | MAO | Implemented |
| FR-REC-05 | The system shall allow MAO Personnel to validate and unvalidate records individually or collectively. | MAO | Implemented |
| FR-REC-06 | The system shall record who created, edited, and validated each record. | System | Implemented |
| FR-REC-07 | The system shall restrict field personnel to changing records of their assigned barangays. | System | Implemented |
| FR-REC-08 | The system shall maintain historical rice production records by barangay and season. | MAO, IT | Implemented |
| FR-REC-09 | The system shall allow MAO Personnel to maintain the profile of each barangay. | MAO | Implemented |
| FR-REC-10 | The system shall exclude seeded sample rows from dashboards and statistics. | System | Partial (the detector recognises placeholder text only) |

## 5.8 Farmer Administration (FADM)

| ID | Requirement | Actor | Status |
|---|---|---|---|
| FR-FADM-01 | The system shall list pending farmer registrations and allow approval, or rejection with a reason. | MAO | Implemented |
| FR-FADM-02 | The system shall allow MAO Personnel to view, create, and edit farmer profiles. | MAO | Implemented |
| FR-FADM-03 | The system shall search farmers by name, farmer identifier, barangay, or association within the user's coverage. | MAO, IT | Implemented |
| FR-FADM-04 | The system shall support membership of a farmer in several associations. | Farmer, MAO | Implemented |
| FR-FADM-05 | The system shall allow MAO Personnel to link, create, or reject an association requested for a farm, and to answer for undeclared farms collectively. | MAO | Implemented |
| FR-FADM-06 | The system shall allow MAO and IT Personnel to restore an archived farm. | MAO, IT | Implemented |

## 5.9 MAO Office (MAO)

| ID | Requirement | Actor | Status |
|---|---|---|---|
| FR-MAO-01 | The system shall display to MAO Personnel the number of farmers, planted area, expected yield, high-risk barangays, planting plans, and harvests due. | MAO | Implemented |
| FR-MAO-02 | The system shall limit dashboard figures to the user's assigned barangays, with an optional read-only municipal view. | System | Implemented |
| FR-MAO-03 | The system shall allow the Municipal Agriculturist to add, edit, deactivate, and reset the password of MAO Personnel accounts. | MAO Head | Implemented |
| FR-MAO-04 | The system shall allow the Municipal Agriculturist to assign barangays and sitios to MAO Personnel. | MAO Head | Implemented |
| FR-MAO-05 | The system shall record MAO actions in an activity log, filterable by user, action, barangay, and date. | System, MAO | Implemented |
| FR-MAO-06 | The system shall allow MAO Personnel to maintain a planner of office activities. | MAO | Implemented |
| FR-MAO-07 | The system shall display a calendar of planting, harvest, advisory, and announcement events. | Farmer, MAO | Implemented |

## 5.10 Reports (RPT)

| ID | Requirement | Actor | Status |
|---|---|---|---|
| FR-RPT-01 | The system shall generate nine report types from stored data with filters for date range, barangay, and season. | MAO | Implemented |
| FR-RPT-02 | The system shall save a generated report and increment its version when the same type and filters are regenerated. | System | Implemented |
| FR-RPT-03 | The system shall allow a report to be marked Draft or Published. | MAO | Implemented |
| FR-RPT-04 | The system shall export a report to CSV, Excel, and PDF and shall provide a print view. | MAO | Implemented |
| FR-RPT-05 | The system shall provide the farmer with a personal summary of predictions and advisories, downloadable as PDF. | Farmer | Implemented |
| FR-RPT-06 | The system shall allow the IT Expert to generate and export reports. | IT Expert | **Not available** (IT Expert is redirected from `reports.*`) |

## 5.11 Communication (COM)

| ID | Requirement | Actor | Status |
|---|---|---|---|
| FR-COM-01 | The system shall deliver in-system notifications with read and unread status. | All | Implemented |
| FR-COM-02 | The system shall allow two users to exchange messages with optional attachments visible only to the two participants. | All | Implemented |
| FR-COM-03 | The system shall allow MAO Personnel to post, edit, archive, comment on, and react to announcements. | MAO | Implemented |
| FR-COM-04 | The system shall present announcements to farmers as read-only. | Farmer | Implemented |
| FR-COM-05 | The system shall deliver a visitor's contact message to MAO Personnel as a notification. | Visitor | Implemented |
| FR-COM-06 | The system shall push changes to notifications, messages, weather, risk, predictions, and dashboards in real time. | System | Implemented (requires Reverb; polling otherwise) |
| FR-COM-07 | The system shall deliver alerts by SMS or mobile push notification. | System | **Missing** |

## 5.12 Climora AI Assistant (AI)

| ID | Requirement | Actor | Status |
|---|---|---|---|
| FR-AI-01 | The system shall answer questions on weather, risk, advisories, and yield from stored system data. | All | Implemented |
| FR-AI-02 | The system shall answer farming vocabulary and how-to questions from an approved knowledge base in English and Filipino. | All | Implemented |
| FR-AI-03 | The system shall block messages that match the language filter and shall not store their text. | System | Implemented |
| FR-AI-04 | The system shall consult the Groq service only when the question cannot be answered from system data and the service is enabled. | System | Implemented (disabled by default) |
| FR-AI-05 | The system shall never train a model or process a dataset during a chat request. | System | Implemented |
| FR-AI-06 | The system shall store each exchange with its intent, source, confidence score, and response time. | System | Implemented |
| FR-AI-07 | The system shall allow IT Personnel to maintain the chatbot vocabulary and language filter. | IT | Hidden (`CHATBOT_KNOWLEDGE_UI`) |

## 5.13 IT Administration (ADM)

| ID | Requirement | Actor | Status |
|---|---|---|---|
| FR-ADM-01 | The system shall allow IT Personnel to add, edit, activate, deactivate, delete, and restore user accounts. | IT | Implemented |
| FR-ADM-02 | The system shall allow IT Personnel to reset a user's password to a temporary password. | IT | Implemented |
| FR-ADM-03 | The system shall allow IT Personnel to configure the system name, office name, logo, thresholds, notification switches, and maintenance mode. | IT | Implemented |
| FR-ADM-04 | The system shall show a maintenance page to all roles except IT while maintenance mode is on. | System | Implemented |
| FR-ADM-05 | The system shall allow IT Personnel to configure session timeout, password policy, and lockout. | IT | Implemented |
| FR-ADM-06 | The system shall allow IT Personnel to withdraw a module from a role and shall enforce the choice on every request. | IT | Implemented |
| FR-ADM-07 | The system shall back up the database daily, prune old backups, and verify the newest archive. | Scheduler | Implemented |
| FR-ADM-08 | The system shall allow IT Personnel to create, download, and restore a backup, and shall take a fresh backup before restoring. | IT | Partial (restoration supports MySQL/MariaDB only) |
| FR-ADM-09 | The system shall allow IT Personnel to keep maintenance records. | IT | Implemented |
| FR-ADM-10 | The system shall display the status of the database, queue, scheduler, ML service, weather sources, and backups. | IT | Implemented |
| FR-ADM-11 | The system shall list audit log entries with filters and export them to CSV. | IT | Implemented |
| FR-ADM-12 | The system shall allow IT Personnel to retry failed background jobs. | IT | Implemented |
| FR-ADM-13 | The system shall allow IT Personnel to add, edit, enable, disable, and import rice varieties. | IT | Implemented |

## 5.14 Machine Learning Management (ML)

| ID | Requirement | Actor | Status |
|---|---|---|---|
| FR-ML-01 | The system shall compare Multiple Linear Regression, Random Forest, and Gradient Boosting by RMSE, MAE, and R². | IT | Implemented |
| FR-ML-02 | The system shall retrain a chosen algorithm from the modelling dataset and register the result as a candidate. | IT | Implemented |
| FR-ML-03 | The system shall allow IT Personnel to upload a dataset and a model file. | IT | Implemented |
| FR-ML-04 | The system shall validate a model before activation and shall keep one active model per slot. | IT | Implemented |
| FR-ML-05 | The system shall switch the running Python service to the activated model and confirm the switch. | System | Implemented |
| FR-ML-06 | The system shall display evaluation metrics only when a minimum number of validated observations exists. | System | Implemented |
| FR-ML-07 | The system shall summarise farmer feedback by model version and record the decision of IT Personnel. | IT | Implemented |
| FR-ML-08 | The system shall retrain the deployed production model. | IT | **Not possible** (the original training data and script are not in the repository) |
| FR-ML-09 | The system shall run the prediction service locally when the ML service is unreachable. | System | Implemented |

## 5.15 Hidden and Retired Functions

| ID | Function | State |
|---|---|---|
| FR-HID-01 | Field Reports (MAO and IT) | Hidden; `FIELD_REPORTS_UI=false` |
| FR-HID-02 | Chatbot Knowledge management | Hidden; `CHATBOT_KNOWLEDGE_UI=false` |
| FR-HID-03 | Candidate model and season retraining section of Model Evaluation | Hidden; `MODEL_EVALUATION_CANDIDATE_UI=false` |
| FR-RET-01 | Live Forecasting | Retired; redirects to the dashboard |
| FR-RET-02 | Usability Survey | Retired; redirects to the dashboard (table retained) |
| FR-RET-03 | Farmer Organizations pages | Retired; redirects to the dashboard |
| FR-RET-04 | MAO Operations page | Retired; functions moved to Advisories, Calendar, and Reports |
| FR-RET-05 | Candidate rice-yield test page | Retired; redirects to Model Evaluation |
| FR-RET-06 | Prediction forms on the Predictions page | Retired; the page shows stored results only |
