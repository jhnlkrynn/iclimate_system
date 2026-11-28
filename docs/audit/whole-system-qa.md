# iClimate Whole-System Usability & Functional QA Audit

**Audit date:** 2026-11-28  
**Method:** Live Playwright browser traversal (`1280×800` desktop and `375×740` mobile) in English and Filipino (`EN`/`FIL`) against an isolated SQLite server (`http://127.0.0.1:8130`, ML service offline on `:19999` to verify fallbacks), plus full `php artisan route:list` (`290` routes) and `docs/audit/uat-readiness.md` cross-check.  
**No application code was modified.**

---

## 1. Executive Summary

### Task Success Table by Role

| Persona / Role | Tasks Tested | Passed | Partial / Failed | Avg Time | Where a Real User Would Give Up |
|---|---:|---:|---:|---:|---|
| **Guest** (First-time visitor) | 5 | 5 | 0 | 0.3s | Smooth on both `1280px` and `375px`; only minor friction is 1 unlabeled form field and `<40px` footer/nav links on mobile. |
| **Mang Romy** (Farmer, 58, Humayingan, `375px` Android) | 12 | 9 | 3 | 0.8s | **1)** Cannot find header language flags on `375px` phone (hidden in mobile topbar). **2)** Long wall of association-pending text for multiple farms on Dashboard. **3)** Raw ISO timestamp & `Stored heatmap_areas` jargon on Heat Map; `"Weather risk to crc"` clipped on mobile. |
| **Bagong Magsasaka** (Empty Farmer, 0 farms/plantings) | 5 | 5 | 0 | 0.4s | Empty states render cleanly without crashes, though `Complete Profile` prompt is persistent. |
| **Ma'am Liza** (MAO Staff, 4 barangays, `1280px` + `375px`) | 12 | 10 | 2 | 2.9s | **1)** `/analysis/model-evaluation` throws uncaught JS `TypeError`. **2)** `/reports/print` & export endpoints 404 if opened before generating a report; `/reports` filter/dropdown overlapped by Climora widget; `0 of 19` high-risk barangays shown even in 4-barangay `"My barangays"` mode. |
| **Sir Ben** (MAO Head, all 19 barangays, `1280px`) | 5 | 4 | 1 | 0.2s | Advisory publish flow works; `/management/advisories` pending-publish button selector differed from expected form action when already approved/filtered. |
| **Cross-Role & Security** | 17 | 17 | 0 | 0.4s | All 14 MAO-only routes blocked Farmers (`403` or redirect to `/farmer/dashboard`); Ma'am Liza properly scoped to her 4 barangays (`Luyahan` hidden); Sir Ben sees all 19 barangays. |

### Issue Counts by Severity & Type

| Severity | Functional | Usability | Total |
|---|---:|---:|---:|
| **Blocker** | 0 | 0 | **0** |
| **Major** | 4 | 7 | **11** |
| **Minor** | 1 | 15 | **16** |
| **Total** | **5** | **22** | **27** (deduplicated across 51 raw Playwright observations) |

### Top 10 Issues

1. **`QA-U01` [Major / Usability] — Header language flags (`EN`/`FIL`) are hidden on `375px` mobile** (`/farmer/dashboard` and all mobile views): A mobile farmer like Mang Romy cannot switch to Filipino from the topbar on a phone (`button[aria-label*="Filipino"]` is `display: none` at `375px`).
2. **`QA-F01` [Major / Functional] — Uncaught JS `TypeError` on `/analysis/model-evaluation`** (`1280×800`): `TypeError: Cannot read properties of null (reading 'value')` in `resources/views/model-evaluation/index.blade.php` when `<30` verified records exist.
3. **`QA-F02` [Major / Functional] — `/reports/print` and `/reports/export*` return raw `404` when visited without `?report=<id>`**: Navigating directly or bookmarking `/reports/print` or `/reports/export/pdf` shows a 404 error instead of redirecting to `/reports` with a prompt to generate/select a report first.
4. **`QA-U02` [Major / Usability] — Heat Map mobile layer label clipped & technical jargon shown** (`/heat-map`, `375px`): `"Weather risk to crc"` is truncated inside the pill, and the footer displays raw ISO-8601 UTC timestamp (`2026-11-25T15:42:27+00:00`) + `"Source: Stored heatmap_areas / barangay weather risk"`.
5. **`QA-U03` [Major / Usability] — Mobile bottom nav `"More"` / `"Higit pa"` tab has missing icon** (`375px`, EN & FIL): Blank white gap appears above `"More"` / `"Higit pa"` in the bottom navigation bar.
6. **`QA-U04` [Major / Usability] — Farmer Dashboard association banner dumps all farms in one long block** (`/farmer/dashboard`, `375px`): Multi-farm farmers see `"Farm 1: association not answered yet · Bukid sa Ilaya: ..."` spanning 8+ lines above the fold.
7. **`QA-U05` [Major / Usability] — Incomplete Filipino translations on Farmer & MAO pages**:
   - Farmer bottom nav (`Heat Map`, `Advice` instead of `Payo`), `"Data Privacy Notice"`, `"Edit farm"`.
   - MAO Dashboard in `FIL`: `"MAO Staff Dashboard"`, `"Find a farmer"`, `"Name, farmer ID, barangay or organization"` remain in English.
8. **`QA-U06` [Major / Usability] — MAO topbar truncates user name & shows `"0 of 19"` in 4-barangay view** (`/mao/dashboard`, `1280px`): `"Good afternoon, Ma'am Liza (MAO!"` cuts off `"Staff)"`; `"High-risk barangays"` says `0 of 19` even when `"My barangays"` (`4` barangays) is active; `"Total farmers (filtered): 3"` vs `"Farmers with a planting plan: 0 of 1"` denominator mismatch confuses staff.
9. **`QA-U07` [Major / Usability] — Climora floating button overlaps form controls** (`375px` & `1280px`): Bottom-right floating circle covers the `Season` dropdown on `/reports` (`1280px`) and overlaps farm/sort controls on `/rice-varieties` and `/analysis/yield` (`375px`).
10. **`QA-F03` [Major / Functional] — `/heat-map/this_week/{barangay}/detail` returns `404` for snake_case range**: Expected range key in `heatMapDetail` differs from `this_week` / `two_weeks` used in URL assumptions (needs strict validation/alias or clearer route constraint).

---

## 2. Route Coverage Table (`290` Artisan Routes)

All `142` `GET` routes from `php artisan route:list --except-vendor --json` were accounted for across live Playwright visits (`125` primary role/viewport/language page audits + `40` remaining static GET/API/export checks = `165` live HTTP checks). `POST`/`PUT`/`PATCH`/`DELETE` routes (`148`) were exercised via interactive E2E workflows (login, language switch, farm switch, farmer/farm approval, advisory publish, report generation, Climora chat) or verified via middleware/controller guards.

| Area / Route Group | Routes | Guest | Farmer | MAO Staff | MAO Head | Status / Notes |
|---|---:|:---:|:---:|:---:|:---:|---|
| **Public / Auth** (`/`, `/login`, `/register`, `/forgot-password`, `/privacy-notice`, `/account-deleted`, `/account/recover`, `/login/weather`, `/registration/resubmit`) | 18 | 200 | — | — | — | All visited; 0 overflows; 1 unlabeled field on `/`, `/register`, `/login`. |
| **Farmer Core** (`/farmer/dashboard`, `/complete-profile`, `/my-farm`, `/farms/*`, `/planting-schedules/*`, `/explore-results`, `/my-reports`, `/my-reports/pdf`, `/survey`, `/boundary`) | 34 | — | 200 | — | — | All GET routes visited (`200`); `/farmer/my-reports/pdf` downloads PDF (`200`). |
| **Farmer & Shared Analytics** (`/analysis/yield`, `/analysis/yield/comparison`, `/rice-varieties`, `/rice-varieties/{id}`, `/rice-varieties/estimate`, `/heat-map`, `/api/heat-map`) | 15 | — | 200 | 200 | 200 | `/heat-map` & `/analysis/yield` pass; `/heat-map/this_week/.../detail` returned `404`. |
| **Advisories, Announcements, Calendar, Messages, Notifications, Profile, Climora** | 46 | — | 200 | 200 | 200 | All visited (`200`); `ws://127.0.0.1:8080` Reverb console warning when WebSocket daemon not running locally. |
| **MAO Operations & Scoping** (`/mao/dashboard`, `/operations`, `/farmer-approvals`, `/farm-verification`, `/farm-associations*`, `/association-reports*`, `/barangays`, `/alerts`, `/planner`, `/climora-feedback`, `/activity-log`, `/personnel`) | 49 | — | 403 | 200 | 200 | Farmer blocked (`403`); Staff scoped to 4 barangays; Head sees all 19. |
| **MAO Users Management** (`/users`, `/users/create`, `/users/{user}`) | 9 | — | 403 | 403 | 200 | Properly restricted to MAO Head (`Sir Ben`). |
| **MAO Analytics & Records** (`/weather-predictions*`, `/analysis/weather`, `/analysis/forecast`, `/analysis/impact`, `/analysis/model-evaluation`, `/analysis/harvest-training*`, `/climate-records*`, `/rice-productions*`, `/historical-rice-productions`, `/field-reports*`) | 44 | — | Redirect/403 | 200 | 200 | Farmer redirected/blocked; `/analysis/model-evaluation` has JS `TypeError`. |
| **Reports & Exports** (`/reports`, `/reports/results`, `/reports/print`, `/reports/export`, `/reports/export/xlsx`, `/reports/export/pdf`) | 10 | — | 200/403 | 200 / 404* | 200 / 404* | `/reports` & `/reports/results` return `200`; `/reports/print` & exports return `404` without `?report=<id>`. |
| **Public & Internal JSON APIs** (`/api/boundaries/lian-barangays`, `/api/lian/risk-*`, `/api/weather/*`, `/farmer/dashboard/live`, `/mao/dashboard/live`, `/notifications/feed`, `/messages/people`) | 20 | 200 | 200 | 200 | 200 | All returned `200` JSON cleanly. |
| **Association Chairman** (`/association-chairman*`) | 6 | — | 403 (non-chair) | — | — | Non-chairman farmer properly receives `403`. |
| **Legacy / Redirected Routes** (`/community-feed`, `/live-forecasting`, `/usability-survey`, `/farmer-organizations`, `/field-reports/create`) | 39 | — | Redirect | Redirect | Redirect | Redirect cleanly to `/planting-advisories`, `/farmer/dashboard`, or `/mao/dashboard`. |

---

## 3. Full Issue List Grouped by Page

### Landing & Auth (`/`, `/register`, `/login`, `/forgot-password`)
- **`QA-001` [Minor / Usability]** (`Guest`, `1280×800` & `375×740`, `EN`): 1 visible form field missing `<label>` or `aria-label` on `/`, `/register`, `/login`.  
  *Fix:* Add explicit `<label for>` or `aria-label` to every visible input/select.
- **`QA-004` [Minor / Usability]** (`Guest`, `375×740`, `EN`): 8 mobile links/buttons under `40px` height (`Skip to main content` `16px`, `Log in to open` `22px`, `Open in Maps` `36px`, footer/header links `20px`).  
  *Fix:* Increase mobile tap target padding/line-height (`min-height: 44px` on actionable buttons/links).
- **`QA-008` [Minor / Usability]** (`Guest`, `375×740`, `EN`): `/forgot-password` links (`Back to Home` `24px`, `Have a recovery code from MAO?` `20px`) below `40px` tap height.  
  *Fix:* Add vertical padding on mobile auth helper links.

### App Shell, Topbar & Mobile Navigation (All Authenticated Pages)
- **`QA-U01` [Major / Usability]** (`Farmer / Mang Romy`, `375×740`, `EN/FIL`): Header language flags (`EN`/`FIL`) are hidden on `375px` mobile (`mobileHeaderFlagVisible: false`), preventing quick language switching on a phone.  
  *Fix:* Expose a compact `EN | FIL` toggle in the mobile topbar or sticky header of the More sheet.
- **`QA-U03` [Major / Usability]** (`Farmer / Mang Romy`, `375×740`, `EN/FIL`, `farmer-375-clean.png`): Bottom bar `"More"` / `"Higit pa"` tab renders without a visible icon above the label.  
  *Fix:* Render the profile/menu SVG icon cleanly inside the 5th `.mobile-tab` slot.
- **`QA-U07` [Major / Usability]** (`Farmer` & `MAO`, `375×740` & `1280×800`, `farmer-375-en_rice_varieties.png`, `liza-1280-en_reports.png`): Floating Climora launcher button overlaps right-side dropdowns (`Season` on `/reports`, `Sort` on `/rice-varieties`, farm card on `/analysis/yield`).  
  *Fix:* Add bottom-right clearance (`padding-bottom: 84px` / offset launcher above mobile nav) or dock Climora inside the header/nav on narrow viewports.
- **`QA-009..033` [Minor / Usability]** (`Farmer`, `375×740`): Topbar farm status pill (`Farm 1 · 29°C Light rain`, `28px`), `09171110002` (`24px`), and `Data Privacy Notice` (`21px`) are under `40px` touch height.  
  *Fix:* Increase mobile hit area (`min-height: 44px` or `padding`) on interactive links/pills.
- **`QA-WS` [Minor / Functional]** (`All Roles`, `1280×800` & `375×740`): Browser console logs `WebSocket connection to 'ws://127.0.0.1:8080/app/...' failed: ERR_CONNECTION_REFUSED` when Reverb is not running, even when `BROADCAST_CONNECTION=null`.  
  *Fix:* Only initialize Echo/Reverb client in `app.js` when `BROADCAST_CONNECTION=reverb` and `VITE_REVERB_HOST` is configured.

### Farmer Dashboard (`/farmer/dashboard`)
- **`QA-U04` [Major / Usability]** (`Farmer / Mang Romy`, `375×740`, `EN/FIL`, `farmer-375-clean.png`): Association-pending banner lists every farm inline (`"Farm 1: association not answered yet · Bukid sa Ilaya: ..."`), creating an intimidating 8-line wall of text on mobile.  
  *Fix:* Summarize as `"4 farms need association info — Complete Profile"` with a single CTA button.
- **`QA-U05a` [Major / Usability]** (`Farmer / Mang Romy`, `375×740`, `FIL`, `farmer-375-real-fil-dashboard.png`): Mobile bottom nav in Filipino still shows `"Heat Map"` and `"Advice"` (English) instead of `"Mapa ng Panganib"` / `"Payo"`, and `"Farm 1 · 29°C..."` mixes English farm labels.  
  *Fix:* Localize `Advice` -> `Payo` in `lang/fil/farmer.php` mobile nav keys.
- **`QA-009b` [Minor / Usability]** (`Farmer`, `375×740`): Dashboard inline pills (`7 days` `24px`, `14 days` `24px`, `View Heat Map` `24px`, `See all` `24px`) are below `40px` tap height.  
  *Fix:* Style pill toggles with `min-height: 40px` and `padding: 8px 14px` on mobile.

### Heat Map (`/heat-map` & `/heat-map/{range}/{barangay}/detail`)
- **`QA-U02` [Major / Usability]** (`Farmer / Mang Romy`, `375×740`, `EN`, `e2e-farmer-heatmap-375.png`):  
  1. Map layer selector pill clips `"Weather risk to crc"` on `375px`.  
  2. Footer exposes raw ISO timestamp (`Updated 2026-11-25T15:42:27+00:00`) and internal table jargon (`Stored heatmap_areas / barangay weather risk`).  
  *Fix:* Allow the layer select pill to wrap or shorten label (`"Crop risk"`), format timestamp as `"Nov 25, 2026 11:42 PM"`, and replace `"Stored heatmap_areas"` with `"Lian weather & risk data"`.
- **`QA-F03` [Major / Functional]** (`Farmer`, `375×740`, `EN`): `/heat-map/this_week/Humayingan/detail` and `/heat-map/two_weeks/Humayingan/detail` return `404 Not Found`.  
  *Fix:* Document/align expected `{range}` parameter (`week` / `two-weeks` vs `this_week` / `two_weeks`) or accept both.

### Yield Prediction & Rice Varieties (`/analysis/yield`, `/rice-varieties`)
- **`QA-022` [Minor / Usability]** (`Farmer`, `375×740`, `EN/FIL`, `farmer-375-real-fil-yield.png`): `Edit farm` (`28px`) link is below `40px` height; Climora button overlaps right corner of the farm selector card.  
  *Fix:* Give `Edit farm` button-like padding (`40px+` height) and add bottom spacing.
- **`QA-024` [Minor / Usability]** (`Farmer`, `375×740`, `EN/FIL`): Variety detail link/card touch target (`A` `36px`) and filter controls slightly under `40px`.  
  *Fix:* Ensure card header link has `min-height: 44px`.

### MAO Dashboard (`/mao/dashboard`)
- **`QA-U06` [Major / Usability]** (`MAO Staff / Ma'am Liza`, `1280×800`, `EN/FIL`, `liza-1280-clean.png`, `mao-1280-real-fil-dashboard.png`):  
  1. Topbar greeting truncates name at 2nd word/paren: `"Good afternoon, Ma'am Liza (MAO!"`.  
  2. `"High-risk barangays"` KPI displays `0 of 19` even when `"My barangays"` (`4` barangays) is selected.  
  3. `"Total farmers (filtered): 3"` vs `"Farmers with a planting plan: 0 of 1"` denominator mismatch.  
  *Fix:* Display full user name (or first+last without mid-paren truncation), scope `of N` to `4` in `"My barangays"` mode, and align farmer count denominators.
- **`QA-U05b` [Major / Usability]** (`MAO Staff / Ma'am Liza`, `1280×800`, `FIL`, `mao-1280-real-fil-dashboard.png`): `"MAO Staff Dashboard"`, `"Find a farmer"`, and `"Name, farmer ID, barangay or organization"` remain in English when Filipino is active.  
  *Fix:* Wrap those strings in `__('mao....')` keys in `lang/en/mao.php` and `lang/fil/mao.php`.
- **`QA-050` [Minor / Usability]** (`MAO Staff`, `375×740`): `Open Heat Map →` (`32px`) and approval row buttons (`View Details`, `Approve` `35px`) are under `40px` on mobile.  
  *Fix:* Increase mobile button height to `42px–44px`.

### Model Evaluation (`/analysis/model-evaluation`)
- **`QA-F01` [Major / Functional]** (`MAO Staff / Ma'am Liza`, `1280×800`, `EN`): Uncaught JS `TypeError: Cannot read properties of null (reading 'value')` on `comparisonCount.value` when `<30` verified records exist.  
  *Fix:* Guard `if (!comparisonCount) return;` in `resources/views/model-evaluation/index.blade.php`.
- **`QA-U08` [Minor / Usability]** (`MAO Staff`, `1280×800`, `liza-1280-en_analysis_model_evaluation.png`): Exposes internal Python artifact filename `rice_yield_model_final.pkl` in UI copy.  
  *Fix:* Replace filename with plain-language label (`"Active yield model"`).

### Reports & Exports (`/reports`, `/reports/print`, `/reports/export*`)
- **`QA-F02` [Major / Functional]** (`MAO Staff / Ma'am Liza`, `1280×800`, `EN`): `/reports/print`, `/reports/export`, `/reports/export/xlsx`, `/reports/export/pdf` return raw `404` when visited without `?report=<id>` (i.e., before clicking `Generate Report`).  
  *Fix:* Redirect to `/reports` with a clear validation message (`"Generate or select a report first."`) when `report` parameter is missing.

---

## 4. UAT Survey Alignment (`docs/audit/uat-readiness.md`)

Cross-checked against live UI:
- **Resolved since 2026-11-25 audit:**
  - Farmer **Yield Prediction** (`/analysis/yield`) is now in Farmer sidebar & mobile More menu.
  - MAO **Impact Analysis** (`/analysis/impact`), **Yield Forecast** (`/analysis/forecast`), and **Model Evaluation** (`/analysis/model-evaluation`) are restored and accessible (`200`).
  - **Forecast** (`/weather-predictions`) and **Weather Analysis** (`/analysis/weather`) are intentionally MAO-only; farmers are redirected cleanly to `/farmer/dashboard`.
- **Remaining gaps affecting UAT respondents:**
  1. **Form A #20 (`Language switch — header flags`):** Hidden on `375px` mobile (`QA-U01`), so smartphone farmers cannot find the header flags.
  2. **Form A #1 & #25 (`Weather Analysis`):** Removed from Farmer role (MAO-only); Form A / Form C survey items asking farmers about `/analysis/weather` or monthly comparison charts should be updated or mapped to Farmer Dashboard + Yield Prediction.
  3. **Form A #19 (`"Was yesterday's forecast right?"`):** Survey card (`"Answer a short survey"` -> `/farmer/survey`) is present at the bottom of Farmer Dashboard, but requires scrolling past all cards on `375px`.
  4. **Form C #6 (`Model Evaluation`):** Page loads (`200`) and shows empty-data explanation when `<30` validated records exist, but throws a JS `TypeError` (`QA-F01`).

---

## 5. Empty States, Offline/ML-Down & Accessibility Basics

- **Empty States (`Bagong Magsasaka`, 0 farms/plantings):** `/farmer/dashboard`, `/farmer/my-farm`, `/analysis/yield`, `/planting-advisories`, `/farmer/explore-results` all load `200` with friendly guidance and `0` JS/server errors.
- **ML-Down / Offline Fallback (`FARMING_AI_API_URL=http://127.0.0.1:19999`):** Climora, Farmer Dashboard, Heat Map, and Model Evaluation degrade gracefully without `500` errors or long hangs (all loaded `<500ms`).
- **Accessibility Basics:**
  - **Text size:** Body text `16px–17px` across main views; zero `<12px` readability failures on primary content.
  - **Contrast:** Risk badges pair distinct colors with explicit text (`Low Risk`, `Moderate`, `High`, `Severe`).
  - **Form labels:** 1 visible input missing `<label>`/`aria-label` on `/`, `/register`, `/login` (`QA-001`).
  - **Touch targets:** Several secondary mobile links/pills (`21px–36px`) and the bottom-bar `More` icon (`QA-U03`) need `>=44px` sizing and icon fix.

---

## 6. Proposed Fix Phases (Awaiting Your Approval)

| Phase | Scope | Issue IDs | Effort | Risk |
|---|---|---|---:|---|
| **P1 — Blockers, JS Errors & Route/Export Safety** | Guard `comparisonCount` null in Model Evaluation; redirect gracefully when `/reports/print` or `/reports/export*` lacks `?report=`; align `/heat-map/{range}/{barangay}/detail` range keys; disable Echo WS connect when `BROADCAST_CONNECTION=null`. | `QA-F01`, `QA-F02`, `QA-F03`, `QA-WS` | Small (1–2h) | Low |
| **P2 — Farmer Clarity, Mobile & Filipino** | Expose `EN\|FIL` toggle on `375px` mobile; fix missing `More`/`Higit pa` icon; condense multi-farm association banner; fix clipped Heat Map layer label + human-readable timestamp/source; finish Filipino nav/dashboard strings; prevent Climora overlap. | `QA-U01`, `QA-U02`, `QA-U03`, `QA-U04`, `QA-U05a`, `QA-U07` | Medium (3–4h) | Low |
| **P3 — MAO Workflow & Scoping Clarity** | Fix topbar name truncation (`(MAO!`); show `0 of 4` (not `19`) in `"My barangays"` mode; align farmer/planting-plan denominators; translate `"Find a farmer"` / `"MAO Staff Dashboard"` in FIL; remove `.pkl` filename jargon. | `QA-U05b`, `QA-U06`, `QA-U08` | Small (1–2h) | Low |
| **P4 — UAT Survey Alignment** | Update `docs/audit/uat-readiness.md` / survey notes for MAO-only Weather Analysis and mobile language switcher placement; surface forecast survey higher or in More menu. | UAT items A#1, A#19, A#20, C#6 | Small (1h) | None |
| **P5 — Accessibility & Touch-Target Polish** | Add missing `<label>`/`aria-label` on guest/auth inputs; bump mobile pills/buttons (`7 days`, `14 days`, `Edit farm`, `Approve`, `View Details`) to `>=44px` tap height. | `QA-001`, `QA-004`, `QA-008`, `QA-009..051` | Small (1–2h) | Low |

**Waiting for your approval before making any code fixes.**
