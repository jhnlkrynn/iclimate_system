# Stakeholder Validation Questionnaires

These questionnaires collect decisions and evidence; they do not pre-fill approval. Each respondent should mark **Accept**, **Revise**, **Reject**, or **Not applicable**, add comments, and attach supporting policy or source documents where requested.

## Common respondent record

| Field | Response |
|---|---|
| Name / position | |
| Organization / barangay scope | |
| Date | |
| Review version | Phase 18 |
| Decision | Accept / Revise / Reject |
| Signature or documented approval reference | |

## A. Farmer questionnaire

Rate each item: 1 Not understood, 2 Difficult, 3 Adequate, 4 Good, 5 Very good. Add examples for ratings 1–2.

1. Can you register, sign in, and understand account approval status?
2. Can you identify your farm, location, area unit, rice variety, planting date, and expected harvest date?
3. Are the planting and harvest forms understandable and sufficient for your practice?
4. Are weather, risk, advisory, and calendar displays timely and understandable?
5. Does the prediction page clearly say that an estimate is not proof of accuracy?
6. Do you understand the difference between an estimate, an actual harvest record, and a verified result?
7. Are advisory actions, notifications, messages, and typhoon check-ins useful?
8. Which language, device, browser, and connectivity conditions must be supported?
9. What personal or farm information may be displayed to MAO staff, and what must remain restricted?
10. What missing feature would prevent practical use?

**Farmer validation decisions:** approve the displayed farm fields; approve the meaning of estimate labels; approve the requested advisory information; identify accessibility/language requirements; confirm consent and privacy expectations.

## B. MAO Personnel questionnaire

1. Which records are authoritative for farmers, farms, planting schedules, harvests, and production?
2. Who may create, correct, approve, reject, import, and archive each record?
3. What documents or source references must accompany an imported record?
4. What dates define a season, and how should cross-year planting/harvest records be grouped?
5. What crop-weather matching window is agriculturally acceptable?
6. What units and allowed ranges apply to area, rainfall, temperature, production, and yield?
7. How should duplicates, demo rows, derived values, missing values, and conflicting records be resolved?
8. Are the current barangay scope rules and MAO read-only municipal view correct?
9. Are current weather/risk thresholds and advisory rules approved? If not, provide authoritative replacements.
10. What reports, alerts, advisories, and Heat Map layers are required for daily work?
11. Which data may be used for model evaluation, and which records must be excluded?
12. What minimum evidence is needed before a prediction or Heat Map layer is shown as decision-support information?

**Required attachments:** approved source list, season calendar, unit/range sheet, data correction policy, risk/advisory approval, and Heat Map review comments.

## C. MAO Head questionnaire

1. Is the system target farm-level, barangay-level, municipal, or another level for each output?
2. What is the approved definition of rice yield and the target unit?
3. Which MAO records and external climate sources are authoritative?
4. Is Ambulong climate data acceptable for Lian, and under what documented limitations or correction?
5. What planting/harvest and climate windows define a matched observation?
6. What evidence and minimum number of independent seasons/observations are required for an accuracy claim?
7. Which chronological or season-grouped evaluation protocol and baselines should researchers use?
8. What Heat Map layers may be published, at what spatial resolution, and with what uncertainty or no-data message?
9. Are current risk weights, thresholds, and exposure assumptions approved for operational use?
10. Who authorizes model correction, retraining, model activation, and publication of evaluation results?
11. What records require retention, redaction, or restricted access?
12. What final acceptance criteria must be met before thesis or operational sign-off?

**Approval fields:** target scope; data authority; evaluation protocol; Heat Map methodology; model activation authority; final acceptance criteria.

## D. IT Expert questionnaire

1. Are role names, MAO Head handling, module restrictions, private channels, and barangay scope correct?
2. Are account lifecycle, password reset, deletion/restore, and first-password rules acceptable?
3. What availability target, maintenance window, response-time target, and supported browsers/devices apply?
4. What backup frequency, off-server retention, restore test, RPO, and RTO are required?
5. Are queue, scheduler, Reverb, MariaDB, Python ML health, and external-provider monitoring sufficient?
6. Which logs and audit events must be retained, and for how long?
7. Are privacy, least privilege, file import, export, rate-limit, and secret-management controls sufficient?
8. What evidence is required before a model artifact or candidate can be activated?
9. Are model checksum, feature schema, readiness gates, and rollback procedures sufficient?
10. What deployment, incident, recovery, and support runbooks require revision?
11. Are accessibility, security, load, and browser tests required before release?
12. What technical risks should be recorded in the final acceptance decision?

**IT sign-off topics:** infrastructure; security; backups/recovery; monitoring; model activation gate; deployment/recovery; supported environments.

## Response handling

Responses must be stored as stakeholder evidence outside the application unless an approved repository process exists. A response changes a requirement status only when the respondent, scope, date, decision, and supporting evidence are recorded. A questionnaire response cannot by itself prove model accuracy.
