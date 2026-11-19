# 2. User Roles and Modules

## 2.1 Actors

The system defines three stored roles in `App\Models\User` and one attribute that distinguishes a fourth operational actor.

| Actor | Stored value | Definition | Account creation |
|---|---|---|---|
| Farmer | `role = 'Farmer'` | A rice farmer of Lian who owns one or more farms | Self-registration on the landing page; pending until approved by MAO Personnel |
| MAO Personnel | `role = 'MAO Personnel'` | Field or office staff of the Municipal Agriculture Office assigned to particular barangays | Created by the Municipal Agriculturist or by IT Personnel |
| Municipal Agriculturist (MAO Head) | `role = 'MAO Personnel'` with `is_mao_head = true` | Head of the office, with municipality-wide coverage and authority over MAO accounts | Seeded (`MaoPersonnelSeeder`) or set by IT Personnel |
| IT Expert (system administrator) | `role = 'IT Expert'` | Administrator of accounts, settings, security, backups, and models | Created by an IT Expert only |
| Visitor | none | An unauthenticated person on the landing page | Not applicable |

Non-human actors are the Laravel scheduler, the queue worker, the Python ML service, and the external services listed in Document 1.

### Account status

An account carries one of five statuses: `Active`, `Inactive`, `Pending`, `Rejected`, or `Deleted`. Only an `Active` account may sign in. A `Deleted` account is hidden from every query by a global scope (`Scopes\HideDeletedUsers`) and may be restored by IT Personnel within the period set in `config/user_deletion.php` (thirty days by default).

## 2.2 Access Control Mechanisms

Access is enforced in five layers, applied in this order.

| Layer | Mechanism | Source |
|---|---|---|
| 1. Authentication | Session guard; `auth` middleware on all application routes | `routes/web.php` |
| 2. Account status | A non-active account is signed out on its next request | `EnsureAccountIsActive` |
| 3. Role | `role:` middleware compares the stored role with the roles permitted on the route and answers HTTP 403 otherwise | `RoleMiddleware` |
| 4. Role redirection | Farmers are redirected from staff record pages; IT Personnel are redirected from agricultural pages to the IT dashboard | `RedirectFarmers`, `RedirectItFromFieldPages` |
| 5. Module permission | IT Personnel may withdraw a module from a role; the role is then redirected to its dashboard | `CheckModuleAccess`, `App\Support\ModuleAccess` |

In addition, data scope is restricted within a role:

- **Farmer.** Every farm, schedule, and report is looked up among the signed-in farmer's own records (for example `FarmController::ownFarm`).
- **MAO Personnel.** Dashboard figures, farmer lists, production records, approvals, and search are limited to the barangays assigned in `mao_user_barangays` (`App\Support\MaoScope`). Field personnel may switch to a read-only municipal view. The Municipal Agriculturist sees all barangays.
- **Private channels.** WebSocket channels are authorised per user, farm, conversation, role, and barangay (`routes/channels.php`).

## 2.3 Module Access Matrix

The matrix below shows effective access after all five layers. **F** = Farmer, **M** = MAO Personnel, **H** = Municipal Agriculturist only (`is_mao_head = true`), **I** = IT Expert. "R" denotes read-only access.

| Module | F | M | I | Notes |
|---|:-:|:-:|:-:|---|
| Landing page, contact form | Visitor | Visitor | Visitor | Public |
| Registration | Visitor | | | Farmer accounts only |
| Sign-in, password reset, profile, photo | ✔ | ✔ | ✔ | |
| Farmer Dashboard | ✔ | | | |
| My Farm (farms, schedules, harvests, calendar, farm map) | ✔ | | | Own records |
| Farmer My Reports (PDF) | ✔ | | | |
| Farmer Survey; forecast survey; yield survey | ✔ | | | |
| Yield Prediction request | ✔ | | | |
| Weather Analysis | ✔ | ✔ | | |
| Predictions / Forecast page | ✔ | ✔ | | IT redirected |
| Heat Map | ✔ | ✔ | | IT redirected |
| Advisories (read, feedback) | ✔ | ✔ | | IT redirected |
| Advisories (create, approve, reject, publish, archive, regenerate) | | ✔ | | Routes also permit IT, but IT is redirected |
| Send Alert | | ✔ | | |
| Calendar | ✔ | ✔ | | IT redirected |
| MAO Planner | | ✔ | | |
| Rice Varieties (view) | ✔ | ✔ | ✔ | |
| Rice Varieties (add, edit, toggle, import) | | | ✔ | `RiceVarietyPolicy` |
| Climate Records, Rice Production | | ✔ | | Farmers and IT redirected |
| Historical Rice Production | | ✔ | ✔ | Route permits M and I; this page is not in the IT redirection list |
| CSV import and record validation | | ✔ | | |
| Barangay profiles | | ✔ | | |
| Farmer Profiles | own | ✔ | single profile | IT edits one profile from User Management |
| Farmer Approvals; Farm Association review | | ✔ | | |
| Farmer live search | | ✔ | ✔ | |
| Weather Impact Analysis | | ✔ | | |
| Yield Forecast | | ✔ | | |
| Reports (generate, print, export CSV) | | ✔ | | Controller admits managing roles only (`authorizeManage`); IT redirected |
| Reports (Excel, PDF, publish) | | ✔ | | |
| Announcements / Community Feed | R (as Announcements) | ✔ | | Farmers read on the Advisories page; IT redirected |
| Notifications | ✔ | ✔ | ✔ | |
| Messages | ✔ | ✔ | ✔ | |
| Typhoon Safety check-in | ✔ | ✔ | ✔ | Shown to farmers during an active typhoon advisory |
| Climora AI assistant | ✔ | ✔ | ✔ | |
| MAO Personnel management | | H | | Others view the list |
| MAO Activity Log | | ✔ (own) / H (all) | | |
| IT Dashboard | | | ✔ | |
| User Management, password reset, restore | | | ✔ | |
| System Settings, Security, Roles and Permissions | | | ✔ | |
| Backups, Maintenance Records | | | ✔ | |
| ML Model Management, Model Registry, Model Evaluation, Model Feedback | | | ✔ | |
| System Health, System Logs | | | ✔ | |
| Field Reports | | (hidden) | (hidden) | Enabled only with `FIELD_REPORTS_UI=true` |
| Chatbot Knowledge management | | | (hidden) | Enabled only with `CHATBOT_KNOWLEDGE_UI=true` |

**Observation.** The scope statement in `docs/chapter1-scope-and-limitations.md` lists report generation among the functions of IT Personnel. In the implementation, the middleware `RedirectItFromFieldPages` includes the pattern `reports.*`, so an IT account that opens the Reports page is redirected to the IT dashboard. The manuscript and the implementation should be reconciled on this point.

## 2.4 Functions by Actor

### 2.4.1 Visitor

1. View the landing page with summary statistics and current weather.
2. Send a contact message to the MAO (three per ten minutes).
3. Register as a farmer; sign in; request a password reset link.

### 2.4.2 Farmer

1. View the dashboard: today's weather, barangay risk level, expected yield, seven-day forecast, latest advice, setup checklist, and the assigned Agricultural Technologist.
2. Choose English or Filipino; the choice is stored on the account.
3. Manage farms: add, edit, archive, restore, set primary, select the active farm, and save or delete the farm map location.
4. Declare the farm's association, or defer the answer.
5. Manage planting schedules, including rice variety, crop establishment method, and expected harvest date.
6. Record planting and harvest activities; record actual harvest with a reason when early or late.
7. Request a yield prediction and view its history.
8. View the forecast, Weather Analysis, the Heat Map, advisories, and announcements.
9. Give feedback on an advisory, on forecast accuracy, and through the farmer survey.
10. Respond to the Typhoon Safety check-in.
11. Download My Reports in PDF.
12. Exchange messages; read notifications; consult Climora.
13. Update profile, photo, and password; delete own account.

### 2.4.3 MAO Personnel

1. View the dashboard for assigned barangays, with a live farmer search.
2. Approve or reject farmer registrations; review association requests; answer for undeclared farms.
3. Maintain farmer profiles; restore archived farms.
4. Maintain climate, production, and historical production records; import by CSV; validate or unvalidate records.
5. Maintain barangay profiles.
6. Generate, create, edit, approve, reject, publish, and archive advisories; refresh PAGASA advisories; respond to advisory feedback.
7. Send or schedule alerts; cancel a scheduled alert; view delivery and read counts.
8. Maintain the MAO Planner.
9. View Predictions, Weather Analysis, Weather Impact Analysis; save an impact analysis.
10. Run Yield Forecast for a barangay or the municipality.
11. Generate, version, publish, print, and export reports.
12. Post, edit, archive, comment on, and react to announcements.
13. View the Activity Log and the MAO Personnel list.

### 2.4.4 Municipal Agriculturist (in addition to 2.4.3)

1. Add and edit MAO Personnel accounts; set them inactive; reset their passwords to the default.
2. Assign barangays and sitios to MAO Personnel.
3. View the Activity Log of all MAO Personnel.

### 2.4.5 IT Personnel

1. View the IT dashboard: system status, user activity, recent events, failed jobs; retry failed jobs.
2. Add, edit, activate, deactivate, delete, and restore accounts of every role; reset a password to a temporary value.
3. Configure system settings, risk thresholds, notification switches, and maintenance mode.
4. Configure session timeout, password policy, and lockout.
5. Withdraw or return modules for each role.
6. Create, download, and restore backups; view backup verification results.
7. Maintain maintenance records.
8. Compare and retrain models; upload a dataset or a model file.
9. Validate, activate, or mark as failed a model in the registry.
10. View model evaluation and farmer model feedback; record a decision.
11. View system health; view and export system logs.
12. Add, edit, enable, disable, and import rice varieties.

## 2.5 Interactions Among Actors

| Interaction | Initiator | Recipient | Mechanism |
|---|---|---|---|
| Registration awaiting approval | Farmer | MAO Personnel | Notification through `FarmerApprovalNotifier` |
| Approval or rejection | MAO Personnel | Farmer | Status change, in-system notification, e-mail when configured (`AccountEmails`) |
| Published advisory or alert | MAO Personnel | Farmers of the target barangay | Notification linked to the advisory (`AdvisoryPublishedNotifier`) |
| Advisory feedback and response | Farmer ↔ MAO Personnel | | `advisory_feedback` |
| Direct message | Any role ↔ any role | | `conversations`, `conversation_messages`, channel `conversation.{id}` |
| Contact message | Visitor | MAO Personnel | Notification |
| Missing data reminders | System | Farmer, MAO Personnel | `FarmerMissingInfoNotifier`, `MaoMissingDataNotifier` |
| Model feedback | Farmer | IT Personnel | `farmer_survey_responses`, `model_feedback_decisions` |
| Account administration | IT Personnel | All users | `users`, audit entry in `system_logs` |
| MAO account administration | Municipal Agriculturist | MAO Personnel | `users`, `mao_user_barangays`, `mao_activity_logs` |
