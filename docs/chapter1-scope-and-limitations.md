# Scope and Limitations of the Study

## Scope of the Study

This study will cover the design and development of iClimate, a web-based climate and rice production decision-support system for the Municipal Agriculture Office (MAO) of Lian, Batangas. The system will help the MAO and the rice farmers of the municipality monitor weather conditions, assess weather-related risk to rice farms per barangay, forecast rice yield, and distribute planting advisories and alerts. The system will serve three groups of users: Rice Farmers, MAO Personnel, and IT Personnel (System Administrator). Its coverage will be limited to rice and to the nineteen (19) barangays of the Municipality of Lian. In general, the system will provide weather monitoring and forecasting, a barangay-level Heat Map of weather risk, rice yield prediction, management of climate and rice production records, planting advisories and alerts, in-app notifications and messaging, report generation, and system administration functions.

### Rice Farmer

The Rice Farmer module will give registered farmers access to weather information, risk levels, advisories, and records for their own farms. The pages for farmers will be available in English and Filipino. A Rice Farmer will be able to:

1. Create an account through the registration page and sign in once the account is approved by the MAO.
2. View the **Dashboard**, which will show the day's message, the weather today, the risk level of the farmer's barangay, the expected yield for the current season, the forecast for the next seven days, the latest planting advice, and the **Typhoon Safety Check-In** when a typhoon advisory is active.
3. View the **Forecast** page, which will show the daily weather forecast for Lian and the rice yield forecast, and submit feedback on the accuracy of the forecast.
4. View **Weather Analysis**, which will present monthly rainfall, temperature, humidity, and wind speed by year, season, or month, and compare the wet and dry seasons.
5. Submit a **Yield Prediction** request by selecting the season, irrigation type, barangay, and farm area, and view the estimated yield in tons per hectare, the total tons, the historical average, the impact level (Favorable, Normal, or At Risk), and the prediction history.
6. View the **Heat Map** of the 19 barangays of Lian, colored by weather risk to crops (Low, Moderate, High, or Severe) for This Week or the next 2 Weeks.
7. Read **Advisories** for the farmer's barangay and for all of Lian, including official PAGASA advisories, and give feedback on an advisory.
8. Manage **My Farm**: record one or more farms with their barangay, size, and farm type (irrigated or rainfed), draw the farm boundary on a map, add planting schedules, record actual harvests, view the farm calendar, and print the farm record.
9. View and download **My Reports** (personal yield prediction and advisory summaries) in PDF or print form.
10. Send and receive **Messages** with MAO Personnel and IT Personnel.
11. Receive in-app notifications of new advisories, alerts, and account updates.
12. Ask questions about the weather, the forecast, and rice farming through the **Climora AI** chat assistant.
13. Update the profile, profile photo, password, and preferred language (English or Filipino).

### MAO Personnel

The MAO Personnel module will support the agricultural staff of the Municipal Agriculture Office in managing data, monitoring risk, and communicating with farmers. A member of the MAO Personnel will be able to:

1. View the **Dashboard**, which will show the total number of farmers, the area planted for the season, the expected yield compared with the previous season, the number of high-risk barangays, the farmers with planting plans, the harvests due, and the yield trend.
2. View the **Heat Map** of weather risk per barangay, with map layers for weekly risk, flood-prone areas, drought-prone areas, and expected yield.
3. Manage **Advisories**: generate, create, edit, approve, reject, publish, and archive planting advisories, refresh the PAGASA advisories, and use **Send Alert** to send an alert (typhoon, flood, drought, heat, or general) to all or selected barangays immediately or at a scheduled time, with delivery and read counts.
4. Plan and record the office's own activities in the **MAO Planner**.
5. Manage **Records**: add, edit, delete, filter, validate, and print **Climate Records** and **Rice Production** records, import records through CSV files, and update the **Barangays** profiles (location, farm area, risk classification, flood-prone and drought-prone status).
6. Manage **Farmers**: view and search farmer profiles, and approve or reject farmer registrations through **Farmer Approvals**.
7. View **Predictions**, which will include the saved weather forecasts and yield estimates, **Weather Analysis**, **Impact Analysis** (the correlation of each climate variable with rice yield, ranked by impact level, with saved analysis history), and **Yield Forecast** (per barangay, season, and irrigation type, or for the whole municipality, compared with actual harvests).
8. Generate **Reports** (Climate Records, Rice Production, Farmer Registration, Advisory, Community Feed, Seasonal Production, Yield Prediction Summary, Climate Trend, and Barangay Risk reports), save them as Draft or Published with version numbers, and export them to CSV, Excel (.xlsx), PDF, or print.
9. Post, edit, archive, comment on, and react to **Announcements** for farmers.
10. Send and receive **Messages** with farmers and IT Personnel.
11. View the **MAO Office** pages: the **Activity Log** of MAO actions and the list of **MAO Personnel** with their assigned barangays. The Municipal Agriculturist (head of office) will be able to add MAO Personnel accounts and assign barangays.
12. Use the **Climora AI** chat assistant and choose English or Filipino for the dashboard and menu.

### IT Personnel (System Administrator)

The IT Personnel module will allow the system administrator to manage user accounts, settings, security, data protection, and the machine learning models. The IT Personnel will be able to:

1. View the **Dashboard**, which will show the system status, user activity, recent events, and failed background jobs, and retry failed jobs.
2. Use **User management** to add, edit, activate, deactivate, and delete accounts of all roles, view the last login of each user, and reset a user's password to a temporary password.
3. Generate and export **Reports**.
4. Configure **Settings**, which will include:
   - **System Settings** (system name, municipality, logo, weather source, risk thresholds, notification switches, and maintenance mode);
   - **Security** (session timeout, password policy, and failed-login lockout);
   - **Roles & Permissions** (the module access of each role);
   - **Backups** (create, download, and restore database backups);
   - **Maintenance Records** (a log of system updates and fixes);
   - **ML Models** (retrain the rice yield model from the modeling dataset, compare models, upload a model version, and view the training history); and
   - **Model Feedback** (farmers' responses on forecast accuracy).
5. Send and receive **Messages** with all users.
6. View **System health details**, including the status of the machine learning service and scheduled jobs, the **Model Registry** (validate and activate model versions), and **Model Evaluation** (RMSE, MAE, and R²).
7. View and filter the **Activity logs** (login, activity, and error logs) and export them to CSV.

### Scope of Data and Analytics

The system will draw its data from five sources: (1) records entered or imported by the MAO, namely climate records, rice production records, and barangay profiles; (2) information entered by farmers, namely farm details, farm boundaries, planting schedules, actual harvests, and survey responses; (3) the historical monthly climate data of the PAGASA Ambulong station (Tanauan, Batangas) for January 2015 to December 2025; (4) live weather and a daily forecast of up to sixteen (16) days for each barangay from the Open-Meteo weather service, together with the regional forecast and weekly outlook published on the PAGASA website; and (5) the barangay boundaries of the Philippine Statistics Authority obtained through the GeoRisk Philippines portal. The modeling dataset for rice yield will cover the wet and dry seasons of 2020 to 2025 [CONFIRM DATA YEARS AND SOURCE OF YIELD RECORDS].

Rice yield prediction will use a Random Forest Regressor (scikit-learn) that estimates yield in tons per hectare from rainfall, temperature, temperature range, lagged and seasonal rainfall and temperature, farm area, and season. The monthly weather forecast will use a Random Forest Regressor for each of rainfall, temperature, humidity, and wind speed, trained on the stored monthly climate records. Multiple Linear Regression, Random Forest, and Gradient Boosting will be compared using the Root Mean Square Error (RMSE), Mean Absolute Error (MAE), and coefficient of determination (R²). The Heat Map will classify each barangay into Low, Moderate, High, or Severe risk with a rule-based method that applies thresholds for heavy rain, strong wind, extreme heat, the absence of rain in rainfed barangays, and active typhoon advisories to the saved daily forecast. Impact Analysis will compute the correlation between each climate variable and rice yield. Planting advisories will be generated from a set of advisory rules and the PAGASA advisories, and will be released to farmers only after MAO approval.

Reports will be exported in CSV, Excel (.xlsx), and PDF formats, and printed directly from the browser. Notifications will be delivered inside the system through a notification inbox with read and unread status. Email will be used only for password reset links when a mail server is configured.

### Technical Scope

iClimate will be a web-based system with a responsive layout for desktop and mobile browsers. It will be built with the Laravel 12 framework (PHP 8.2 or later), Blade templates, Tailwind CSS and Bootstrap, Alpine.js, Chart.js for charts, and Leaflet for maps with OpenStreetMap and Esri World Imagery base layers. The database will be MySQL/MariaDB [CONFIRM: the development configuration also uses PostgreSQL]. The machine learning components will be written in Python and served through a FastAPI service using scikit-learn, pandas, and NumPy. Background tasks such as weather refresh, risk computation, predictions, scheduled alerts, and the daily database backup will run through the Laravel scheduler and queue worker. The system will use the following external services: Open-Meteo for weather data, the PAGASA website for advisories, the GeoRisk Philippines portal for barangay boundaries, map tile providers for the base maps, and the Groq API for the Climora AI assistant when a question cannot be answered from the system's own data. The system will be deployed on [DEPLOYMENT SETTING, e.g., a Windows server with XAMPP at the MAO of Lian, or an online host].

## Limitations of the Study

The system will cover rice only. All yield values, models, and computations are expressed for rice in tons per hectare, and harvests entered in sacks are converted using the weight of a sack of palay. Other crops, livestock, and fisheries will not be covered. The system will also be limited to the Municipality of Lian, Batangas: the map, weather locations, risk ratings, and registration options are defined only for its 19 barangays, so the system cannot be used for other municipalities without changes to its configuration and data.

The system will not use Internet of Things (IoT) devices, weather stations, or field sensors. Weather information will come entirely from external sources: live and forecast weather from Open-Meteo, advisories from the PAGASA website, and historical climate data from the PAGASA Ambulong station, which is located in Tanauan and not within Lian. Conditions on a particular farm may therefore differ from the values shown.

The system will not have a native mobile application. Although application programming interface (API) endpoints for mobile sign-in and registration exist, no mobile client is included in this study; users will access the system through a web browser. The system will not send SMS messages or mobile push notifications. Notifications, alerts, and advisories will be visible only when a user signs in, and email will be used only for password reset when a mail server is configured; otherwise, users will request a new password from the MAO or IT Personnel. The system will not process payments, subsidies, loans, or crop insurance.

The accuracy of the forecasts and predictions will depend on the availability and quality of the data. Yield predictions and weather forecasts are estimates and must not be treated as certain outcomes. The modeling dataset contains only sixty (60) monthly records from 2020 to 2025; when the candidate models were tested on the 2025 hold-out data, Multiple Linear Regression, Random Forest, and Gradient Boosting all produced a negative R², which means that more historical records from the MAO are needed before the models can be considered reliable. The training data of the deployed rice yield model is not available in the system, so the deployed model itself cannot be retrained; retraining will produce a new candidate model that IT Personnel must validate and activate. When required inputs such as recent climate records are missing, the system will report that a prediction is unavailable instead of estimating the missing values. The risk thresholds used on the Heat Map and the weights of the risk score are working defaults that still require confirmation by the MAO of Lian before they are treated as official advice [CONFIRM WHETHER MAO HAS VALIDATED THE THRESHOLDS]. The season calendar used by the yield model cannot be edited because it must match how the model was trained.

The system will depend on the availability of external services. If Open-Meteo, the PAGASA website, or the GeoRisk portal is unavailable, or if the PAGASA web pages change their format, the system will display the most recent saved data and a notice that current information is temporarily unavailable. The Climora AI assistant will answer from the system's own data first, and will be unable to answer general questions while the Groq service is unavailable. The system will also require the server's scheduler, queue worker, and machine learning service to run continuously; when any of these is stopped, predictions, reports, weather, risk ratings, and advisories will not be updated.

Users will need an Internet connection and an up-to-date web browser. The interface was tested on Chromium-based browsers at desktop (1280 pixels) and mobile (375 pixels) screen widths [CONFIRM SUPPORTED BROWSERS, e.g., Google Chrome, Microsoft Edge, Mozilla Firefox]. The farmer pages and the MAO dashboard and menu will be available in English and Filipino, while the IT Personnel pages will be available in English only.

Only farmers will be able to create their own accounts. Farmer registration will require an email address and a security check (CAPTCHA), and the account will remain pending until it is approved by MAO Personnel. MAO Personnel accounts will be created by the Municipal Agriculturist or by IT Personnel, and IT Personnel accounts will be created only by IT Personnel. Email addresses will not be verified during registration.

Security will be limited to the measures implemented within the application: password-based authentication, role-based access to modules, failed-login lockout with a CAPTCHA after repeated failed attempts, request rate limiting, a configurable password policy and session timeout, a maintenance mode, and activity and audit logs. The use of HTTPS will depend on the server configuration. Database backups will be stored on the same server, and the restore function will support MySQL/MariaDB backups only. The personal data in the farmer masterlist will not be distributed with the source code and must be loaded onto the server separately.

Some features will be present in the current build but hidden or limited. The **Field Reports** module and the **Chatbot Knowledge** management pages will be hidden from the interface by default and enabled only through configuration, and the evaluation page for candidate yield models will likewise be hidden. Earlier pages for Live Forecasting, the Usability Survey, and Farmer Organizations have been retired and redirect users to other pages. [CONFIRM WHETHER ANY OF THESE HIDDEN FEATURES WILL BE PART OF THE FINAL SYSTEM.]

The study will be conducted at the Municipal Agriculture Office of Lian, Batangas, during [SCHOOL YEAR / PERIOD].
