# iClimate UAT Readiness Audit (ISO/IEC 25010)

**Audit date:** 2026-11-25  
**Scope:** Codebase audit (`routes/web.php`, `routes/manuscript.php`, `resources/views/`, `app/Http/Controllers/`, `app/Services/`, `resources/css/app.css`) + Playwright verification against `http://127.0.0.1:8000` (desktop `1280×800` and phone `390×844`).  
**Playwright note:** Unauthenticated routes (`/`, `/login`) rendered with `0` horizontal overflow (`scrollWidth === innerWidth`) and `17px` body font size; live authenticated traversal in Playwright was blocked by the login CAPTCHA (`captcha_answer` in `resources/views/auth/login.blade.php`), which was not bypassed. All authenticated route/UI/controller/view findings below are verified directly against the codebase. Also note: local database currently has no `is_mao_head = true` user seeded.

---

## Summary of Removed / Moved Features & Label Mismatches

### Recently Removed or Moved Features Still Asked About in the Survey
1. **7-day & 14-day Forecast page moved to MAO-only (`/weather-predictions`):** `weather-predictions.index` now uses `['farmer.redirect:farmer.dashboard', 'role:mao']` (`routes/web.php:809-813`), and **Forecast** was removed from the Farmer sidebar (`resources/views/layouts/sidebar.blade.php:79-86`).
2. **"Was yesterday's forecast right?" (`forecast-survey`) unreachable for farmers:** The `forecast-survey` partial (`resources/views/weather-predictions/partials/forecast-survey.blade.php`) is only included on `weather-predictions/index.blade.php:48`, which redirects farmers to `/farmer/dashboard`.
3. **Impact Analysis (`/analysis/impact`) removed/redirected:** `GET/POST /analysis/impact` and `GET /analysis/impact/{analysis}` all redirect to `mao.dashboard` (`routes/manuscript.php:108-110`).
4. **Standalone MAO Yield Forecast (`/analysis/forecast`) redirected:** `GET/POST /analysis/forecast` redirects to `mao.dashboard` (`routes/manuscript.php:122-123`); yield forecast lives as a tab/section on `/weather-predictions`.
5. **Model Evaluation (`/analysis/model-evaluation`) removed/redirected:** Redirects to `mao.dashboard` (`routes/web.php:845-850`).

### Label Mismatches (Survey vs UI)
| Survey Label | Current UI Label / Route | Location |
|---|---|---|
| **Forecast** (Farmer menu) | Removed from Farmer sidebar; `/analysis/weather` exists by URL only, `/weather-predictions` is MAO-only | `sidebar.blade.php:79-86`, `routes/web.php:809-813` |
| **Yield Prediction** (Farmer menu) | Not in Farmer sidebar/mobile nav; route `/analysis/yield` exists by URL only | `sidebar.blade.php:79-86`, `routes/manuscript.php:115` |
| **Predictions** (MAO menu) | **Forecast** (`mao.nav.forecast` → `/weather-predictions`) | `sidebar.blade.php:102` |
| **Advisories** (MAO menu) | **Advisories & Planner** | `sidebar.blade.php:104` |
| **Reports** (MAO menu) | **Records & Reports** (opens `/climate-records` with tabs) | `sidebar.blade.php:113-114` |
| **Weather Analysis** (IT Expert 5 modules) | Split across Farmer Dashboard 7-day widget, `/analysis/weather` (monthly charts), and MAO `/weather-predictions` | `routes/manuscript.php:105`, `routes/web.php:809` |

---

## FORM A – Rice Farmers

| # | Item | Status | Route / Page | One-line Note |
|---|---|---|---|---|
| 1 | Weather Analysis: 7 & 14-day forecast + monthly rainfall/temp/humidity charts | **Partial** | `/analysis/weather`, `/farmer/dashboard` | `/analysis/weather` has monthly rainfall/temp/humidity charts and year comparison, but no 7/14-day forecast and is missing from Farmer nav; 14-day forecast is on MAO-only `/weather-predictions`. |
| 2 | Yield prediction request (choose farm + season), past requests + status | **Partial** | `/analysis/yield` | Request form (farm + season), past requests table, and status polling work in `YieldPredictionController` & `analysis/yield.blade.php`, but **Yield Prediction** is missing from the Farmer sidebar and mobile nav. |
| 3 | Advisories for my barangay with valid dates + "What to do" | **Available** | `/planting-advisories` | `PlantingAdvisoryController::index` filters active/published advisories sorted by severity and proximity, displaying valid dates and `"What to do"` actions. |
| 4 | 7-day forecast per barangay | **Available** | `/farmer/dashboard` | `FarmerDashboardService` renders the barangay-specific 7-day forecast (`dashboards/farmer/weather.blade.php`). |
| 5 | Predicted t/ha + total harvest | **Available** | `/farmer/dashboard`, `/analysis/yield` | Farmer Dashboard shows `t/ha` and expected harvest tons; `/analysis/yield` shows predicted `t/ha` and total harvest. |
| 6 | Heat Map risk Low/Moderate/High/Severe for my barangay | **Available** | `/heat-map` | `HeatMapPanel` and `BarangayWeekRiskService` display Low/Moderate/High/Severe levels for all 19 barangays. |
| 7 | Plain weekly answer (e.g. "Rain most days – good for planting") | **Available** | `/farmer/dashboard` | Rendered in `dashboards/farmer/week.blade.php` from `FarmerDashboardService::week()`. |
| 8 | Favorable / Normal / At Risk label on the yield prediction | **Available** | `/analysis/yield` | `analysis/yield.blade.php:145,216` displays `Favorable` / `Normal` / `At Risk` badges (though page is not in Farmer sidebar). |
| 9 | "What to do" in advisories AND on the Heat Map | **Available** | `/planting-advisories`, `/heat-map` | Both `advisories/index.blade.php` and `heat-map/index.blade.php` (`Gawin ngayon` / `What to do`) display concrete actions. |
| 10 | Dashboard figures: farm size, days to harvest, expected harvest, rainy days ahead | **Available** | `/farmer/dashboard` | All four KPI tiles are rendered by `FarmerHomeKpis` in `dashboards/farmer/kpis.blade.php`. |
| 11 | Farmer menus: Forecast, Yield Prediction, Advisories, Heat Map | **Partial** | `sidebar.blade.php`, `mobile-navigation.blade.php` | **Heat Map** and **Advisories** are in Farmer nav; **Forecast** was moved to MAO (`/weather-predictions`) and **Yield Prediction** (`/analysis/yield`) was omitted from Farmer menus. |
| 14 | Usable on smartphone | **Available** | All farmer pages | Responsive CSS (`app.css`), `mobile-navigation.blade.php` bottom tabs + More sheet, and mobile card layouts (`analysis/yield.blade.php`). |
| 15 | Clicking a dashboard card / advisory / barangay opens details | **Available** | `/farmer/dashboard`, `/planting-advisories`, `/heat-map` | Dashboard KPI cards open detail drawers (`fhDrawer`), advisories expand/open details, and Heat Map barangays open `/heat-map/{range}/{barangay}/detail`. |
| 16 | Missing farm area → asked before prediction | **Available** | `/analysis/yield`, `/farmer/dashboard` | `YieldPredictionController::farmerStore()` requires `area_hectares` when `$farm->modelArea() === null` (`yield.blade.php:91`); Dashboard prompts for farm size. |
| 17 | Repeated requests limited; latest result shown | **Available** | `/analysis/yield` | `YieldPredictionController` enforces `MAX_PENDING = 3` and reuses matching requests within 24 hours (`yield_prediction_requests`). |
| 19 | "Was yesterday's forecast right?" + "Was this useful?" | **Partial** | `/weather-predictions` (blocked), `/analysis/yield` | `"Was this useful?"` (`analysis.yield.useful`) is on `/analysis/yield`, and `/farmer/survey` exists, but `"Was yesterday's forecast right?"` (`forecast-survey.blade.php`) is only on MAO-only `/weather-predictions`. |
| 20 | Language switch (header flags) | **Available** | `/farmer/dashboard/language` | EN/FIL header flag toggle persists language across Farmer pages. |
| 22 | "Today's message" on Dashboard + assigned MAO contact | **Available** | `/farmer/dashboard` | `dashboards/farmer/message.blade.php` renders `"Today's message"` and `partials/farmer-mao-contact.blade.php` shows assigned MAO personnel. |
| 23 | Risk colors + badges distinguishable | **Available** | `/heat-map`, `/farmer/dashboard` | Uses distinct color tokens plus explicit text labels (`Low`, `Moderate`, `High`, `Severe`). |
| 24 | Heat Map explains risk meaning, what to do, main factor | **Available** | `/heat-map` | `HeatMapPanel` renders `Ibig sabihin` (meaning), `Gawin ngayon` (what to do), and `main_factor`. |
| 25 | Summary: this year's weather vs a usual year | **Partial** | `/analysis/weather` | `analysis/weather.blade.php:143-189` shows `"this year vs usual"` rain comparison and sentences, but `/analysis/weather` is not linked in Farmer nav. |

---

## FORM B – MAO Staff

| # | Item | Status | Route / Page | One-line Note |
|---|---|---|---|---|
| 1 | Reports: 9 types + date/year/season/barangay filters; Print/CSV/Excel/PDF | **Available** | `/reports` | `Report::TYPES` defines all 9 report types; `reports/index.blade.php` & `ReportExtrasController` provide date/year/season/barangay filters and Print, CSV, Excel, PDF exports. |
| 2 | Association Reports: reporting periods, status per association, review/accept/return | **Available** | `/mao/association-reports` | `MaoAssociationReportController` supports creating periods, tracking association status, and reviewing/accepting/returning submissions. |
| 3 | Yield Forecast by barangay + season; create/approve/publish advisories | **Partial** | `/weather-predictions`, `/planting-advisories` | Create/approve/publish advisories works on `/planting-advisories`; standalone `/analysis/forecast` redirects to `/mao/dashboard`, while `/weather-predictions` only shows saved/scheduled yield estimates (`predict` redirects). |
| 4 | Report figures match records + filters | **Available** | `/reports` | `ReportController` and `ManuscriptReportBuilder` query stored records using the selected filters. |
| 5 | Predicted vs actual yield (validated harvests) | **Available** | `/mao/dashboard` | `dashboards/mao.blade.php:366-485` displays municipality-wide and per-barangay predicted vs actual yield and MAE/MAPE from validated harvests. |
| 6 | Impact Analysis: top climate factor for a season/years | **Missing** | `/analysis/impact` | `GET/POST /analysis/impact` redirects to `/mao/dashboard` (`routes/manuscript.php:108-110`); feature was removed/disabled. |
| 7 | Report history + Draft/Published status | **Available** | `/reports` | `reports/_manuscript-actions.blade.php` shows `Draft`/`Published` badge, version, and `reports.publish` route. |
| 8 | Dashboard "Needs Action" list + high-risk barangay count | **Available** | `/mao/dashboard` | `dashboards/mao.blade.php:50,171` shows the `"Needs Action"` list and high-risk barangay KPI tile. |
| 9 | Heat Map ratings pre-fill new advisories | **Available** | `/planting-advisories` | `PlantingAdvisoryController::create()` pre-fills recommendation, action, and severity from `BarangayWeekRiskService` (`prefilled: true`). |
| 11 | Menus: Predictions, Yield Forecast, Advisories, Heat Map | **Partial** | `sidebar.blade.php:99-133` | Sidebar labels differ: **Forecast** (not Predictions), **Advisories & Planner** (not Advisories), **Heat Map**, and **Yield Forecast** is not a separate menu item. |
| 14 | Each dashboard number links to its page | **Available** | `/mao/dashboard` | Every KPI tile (`mao.blade.php:40-70`) and Needs Action row (`mao.blade.php:183`) links to its target page. |
| 15 | Filters: barangay/year/season/month | **Available** | `/reports`, `/mao/dashboard`, `/weather-predictions` | Filter controls for barangay, year, season, and month are present across MAO analytics and reports pages. |
| 16 | Advisories go through Pending approval before publish | **Available** | `/planting-advisories` | `PlantingAdvisoryController` supports `pending_review` (`requires_review = true`) and explicit `approve`/`publish` transitions. |
| 17 | Cancel a sent batch of alerts | **Available** | `/mao/alerts` | `AdvisoryAlertController::cancel` (`POST /mao/alerts/{batch}/cancel`) cancels scheduled alert batches. |
| 18 | Charts, Heat Map preview, risk donut on dashboard | **Available** | `/mao/dashboard` | `mao.blade.php:203,253-271` renders charts, `[data-heat-map-preview]`, and `partials/half-donut` risk distribution. |
| 19 | Save impact analysis with title + notes, reopen later | **Missing** | `/analysis/impact` | Both `analysis.impact.store` and `analysis.impact.show` redirect to `/mao/dashboard` (`routes/manuscript.php:109-110`). |
| 20 | Language switch on dashboard | **Available** | `/mao/dashboard` | `[data-mao-language]` posts to `/mao/dashboard/language` to switch EN/FIL. |
| 24 | Heat Map barangay panel: rain total, wettest day, max wind, max temp | **Available** | `/heat-map` | `HeatMapPanel` (`app/Services/Risk/HeatMapPanel.php:93,156-188`) provides `rain_total_mm`, `wettest_day_mm`, `wind_max_kmh`, and `temp_max_c`. |
| 25 | Normal / Higher / Lower than usual yield label vs usual t/ha | **Available** | `/weather-predictions` | `SeasonYield` / `weather-predictions` compares predicted `t/ha` against usual `t/ha` with Normal / Higher / Lower labels. |

---

## FORM C – IT Experts

| # | Item | Status | Route / Page | One-line Note |
|---|---|---|---|---|
| 1 | Five modules (Weather Analysis, Yield Prediction, Planting Advisory, Climate Monitoring Dashboard, Heat Map) for both roles | **Partial** | Multiple | Dashboards, Advisories, and Heat Map are in nav for both roles; Weather Analysis & Yield Prediction are missing from Farmer nav, and `/weather-predictions` blocks farmers. |
| 2 | Advisory flow: create → approve → publish → farmers notified → Helpful/Not Helpful vote | **Available** | `/planting-advisories` | Full lifecycle implemented in `PlantingAdvisoryController` (`store`, `approve`/`publish`, `publishedMessage`, `feedback`). |
| 3 | Yield flow: farmer requests + records actual harvest; MAO runs forecasts, compares predicted vs actual, evaluates models | **Partial** | `/analysis/yield`, `/farmer/my-farm`, `/mao/dashboard`, `/analysis/model-evaluation` | Farmer requests/harvests and MAO predicted-vs-actual work; however, MAO Yield Forecast (`/analysis/forecast`) and Model Evaluation (`/analysis/model-evaluation`) redirect to `/mao/dashboard`. |
| 4 | Same forecast data for farmer and MAO | **Available** | `/farmer/dashboard`, `/weather-predictions`, `/heat-map` | Both roles read from shared `LianBarangayWeatherService` / `FarmerDashboardWeatherService` Open-Meteo cache. |
| 5 | Heat Map levels consistent across farmer view, MAO view, pre-filled advisories | **Available** | `/heat-map`, `/planting-advisories` | All three read `BarangayWeekRiskService` directly. |
| 6 | Model Evaluation: RMSE, MAE, R² for MLR, Random Forest, Gradient Boosting (incl. wet/dry + irrigated/rainfed) | **Missing** | `/analysis/model-evaluation` | Route `model-evaluation.index` (`routes/web.php:845-850`) redirects to `/mao/dashboard` instead of rendering `model-evaluation/index.blade.php`. |
| 8 | Publishing shows the number of farmers notified | **Available** | `/planting-advisories` | `PlantingAdvisoryController::publishedMessage()` returns `"Advisory published. X farmers were notified."`. |
| 9 | Pages read stored data from scheduled jobs (no live provider/model call on page load) | **Available** | `/farmer/dashboard`, `/heat-map`, `/weather-predictions` | Interactive requests read stored/cached data and dispatch queued jobs (`PrepareFarmerYieldEstimate`, scheduled weather/risk syncs). |
| 14 | Responsive on phone/tablet/desktop | **Available** | All pages | Verified responsive CSS, mobile nav bar/drawer (`mobile-navigation.blade.php`), and `0` horizontal overflow on public pages at `390×844` and `1280×800`. |
| 16 | MAO-only actions blocked for farmers (approve/publish advisories, impact analysis, yield forecast, model evaluation) | **Available** | `routes/web.php`, `routes/manuscript.php` | All MAO routes are guarded by `role:mao` and/or `farmer.redirect:farmer.dashboard`. |
| 17 | Max 3 pending requests, 24h result reuse, missing farm-area prompt | **Available** | `/analysis/yield` | `YieldPredictionController` enforces `MAX_PENDING = 3`, 24h deduplication, and required `area_hectares` when farm size is missing. |
| 19 | Forecast survey, "Was this useful?", Helpful/Not Helpful | **Partial** | `/analysis/yield`, `/planting-advisories`, `/weather-predictions` | `"Was this useful?"` and advisory `Helpful / Not Helpful` work; `"Was yesterday's forecast right?"` (`forecast-survey`) is on MAO-only `/weather-predictions`, unreachable by farmers. |
| 22 | Text size, contrast, button size (check WCAG AA) | **Available** | `resources/css/app.css` | Base body font `17px` (`--iclimate-readable-body`), mobile touch targets `min-height: 42px–44px`, and white-on-dark-badge risk labels; full authenticated contrast sweep blocked by login CAPTCHA. |
| 23 | Risk shown with color AND text (Low/Moderate/High/Severe/No data) | **Available** | `/heat-map`, `/farmer/dashboard`, `/mao/dashboard` | Risk badges consistently pair color coding with text labels (`Low`, `Moderate`, `High`, `Severe`, `No data`). |
| 25 | Live auto-refresh + status of prediction requests | **Available** | `/analysis/yield`, `/farmer/dashboard/live`, `/mao/dashboard/live` | `analysis/yield.blade.php` polls `analysis.yield.status` (`[data-prediction-status-url]`), and dashboards expose live polling endpoints. |
