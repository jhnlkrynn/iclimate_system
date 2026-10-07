# Manuscript feature plan (October 2026)

The manuscript's feature list for Farmer, MAO and IT, compared with the system on `ui` (2026-10-07), and the phases that
add what is missing. Each phase is one commit, so any phase can be reverted on its own.

**Rules kept from earlier work**

- Pages read stored data only. Anything slow (the yield model, weather providers) runs in a queued job or a scheduled
  command.
- Farmer pages exist in English and Filipino (`lang/en`, `lang/fil`).
- New routes live in `routes/manuscript.php`. Every edit to an existing shared, MAO or IT file is inside a block marked
  `Manuscript – Naicel`.
- New tables come from new migration files only.

## What already exists (no work needed)

| Manuscript item | Where it is |
|---|---|
| Register, log in/out, forgot/reset password, change password, profile | Breeze auth pages, `profile` |
| Farm details (size, barangay, irrigated/rainfed), several farms | `farmer.farms.*` (John Luke) |
| Farmer dashboard: weather today, risk level, yield estimate, latest advisory, 7 days | `FarmerDashboardService` |
| Weather forecast (16 days), forecast survey | Predictions page |
| Planting advisories (generated, approved, published, by barangay), feedback | `planting-advisories.*` |
| Crop calendar / planting schedules, activity calendar | `calendar`, My Farm |
| Heat map with barangay risk and monthly forecast | `heat-map` |
| Notification inbox (read/unread), early warnings, typhoon check-in | `notifications.*`, typhoon safety |
| MAO dashboard: farmers, area, yield vs last season, high-risk barangays, yield trend 2020–2025 | `MaoHomeService` |
| Climate and production records: add, edit, delete, filter, print | `climate-records.*`, `rice-productions.*` |
| Farmer list and profiles | `farmer-profiles.*` |
| Reports (climate, production, farmers, advisories), print, CSV | `reports.*` |
| User management (add, edit, activate/deactivate, delete, password) | `users.*` |
| Role access, failed-login lockout, login/activity/error logs with filters | `role:` middleware, `LoginRequest`, `system-logs` |
| System health, ML service status, scheduled jobs | `system-health` |
| Daily backup, backup verification | `iclimate:backup-database`, `BackupVerificationService` |
| Model registry (activate/switch), model evaluation (RMSE, MAE, R²) | `model-registry.*`, `model-evaluation` |

## Phases

### Phase 1: Accounts and farmer approval
1. New status `Pending` (and `Rejected`) for users. Farmer sign-up (web and mobile) creates a Pending account when
   `ICLIMATE_FARMER_APPROVAL=true` (the default).
2. A Pending or Rejected farmer who signs in sees "waiting for MAO approval" / "not approved" instead of "inactive".
3. MAO page **Farmer Approvals**: pending sign-ups with barangay and farm type; Approve, or Reject with a reason. Audit
   log entries; the farmer gets an in-app notification.
4. `users.last_login_at`, set at each sign-in. The IT user list shows Last Login; filters for role and status
   (including Pending).
5. Farmer Approvals is a tab of Farmers (the Farmers list already filters by barangay and farm type). IT can reset a
   user's password to a one-time temporary password.

### Phase 2: Climate and production data management
1. `verified_at` / `verified_by` on climate records and production records. Validate and Unvalidate buttons, and a
   Verified/Unverified filter.
2. `area_harvested_hectares` on production records.
3. CSV import for both (a template to download, upload, row-by-row checks with the same rules as the form, a summary of
   rows imported and rows skipped with the reason).
4. Climate records filter by year and month.

### Phase 3: Barangays and heat map layers
1. `barangay_profiles`: latitude, longitude, farm area, MAO risk classification (Low/Moderate/High), flood-prone,
   drought-prone, notes.
2. MAO **Barangays** page: the 19 barangays of Lian, edit each one.
3. Heat map layer switch: Weekly risk (current) · Flood-prone · Drought-prone · Predicted yield.

### Phase 4: Climate monitoring and weather impact analysis
1. **Weather Analysis** page for farmers (English/Filipino) and MAO: monthly rainfall, temperature, humidity and wind
   2020–2025; filter by year, season or month; wet vs dry comparison.
2. Staff view adds climate risk indicators (heavy-rain, heat, dry months) and climate vs yield.
3. MAO **Weather Impact Analysis**: correlation of each climate variable with rice yield, ranked, with an impact level
   (Low/Moderate/High). Save an analysis; analysis history.

### Phase 5: Yield prediction requests and forecasting
1. Farmer **Yield Prediction**: choose season, irrigation, barangay and area. The request is queued (no model in the
   page). Result: t/ha, total tons, historical average, impact level (Favorable / Normal / At Risk). Prediction
   history.
2. MAO **Yield Forecast**: run per barangay, season and irrigation, or for the whole municipality (queued). Saved run
   history. Predicted vs actual (farmers' harvest answers and production records).

### Phase 6: Advisory alerts
1. Create an alert with type (typhoon/flood/drought/heat/general), severity, message, recommended action, recipients
   (all or chosen barangays).
2. Send now or schedule (a scheduled command publishes due alerts each minute).
3. `notifications.read_at` and a link to the advisory: delivery and read counts per advisory, and a sent-alert
   history.

### Phase 7: Reports
1. New report types: seasonal production, yield prediction summary, climate trend, barangay risk.
2. Export to Excel (.xlsx) and PDF, beside the existing CSV and Print.
3. Report status Draft / Published, with a version number when a report is regenerated.
4. Farmer **My Reports**: own prediction summary and advisory summary, PDF download and print.

### Phase 8: IT settings, security, maintenance and models
1. **System Settings**: system name, municipality, logo, weather source, season month ranges, risk thresholds,
   notification switches, maintenance mode (non-IT users see a maintenance page).
2. **Security settings**: session timeout, password policy (length, mixed case, numbers, symbols), failed-login lockout
   attempts and minutes, HTTPS status.
3. **Roles & Permissions**: module × role matrix (defaults = the manuscript's access matrix), enforced by middleware.
   IT always keeps the IT modules.
4. **Maintenance Records**: log updates and fixes, maintenance history.
5. **Backups**: back up now, backup history, download, restore (typed confirmation; a backup is taken first).
6. **Model management**: retrain the rice-yield model with new data (queued), compare Multiple Linear Regression,
   Random Forest and Gradient Boosting (RMSE, MAE, R²), upload a model version, training history.
7. System logs export (CSV).

### After the phases
- Handoff note for Kaye and John Luke (`docs/handoff-manuscript.md`).
- `scripts/check-code.sh`, the related tests, then push `ui`.

## Status

| Phase | Status |
|---|---|
| 1 Accounts and farmer approval | done |
| 2 Climate and production data | done |
| 3 Barangays and heat map layers | done |
| 4 Climate monitoring and impact analysis | done |
| 5 Yield prediction and forecasting | to do |
| 6 Advisory alerts | to do |
| 7 Reports | to do |
| 8 IT settings, security, maintenance, models | to do |
