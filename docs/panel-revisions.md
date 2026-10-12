# Panel Revisions – Implementation Report

System: iClimate – Rice Yield Monitoring and Prediction System, Municipal Agriculture Office (MAO), Lian, Batangas.
Stack: Laravel 12 + MySQL, Blade + Bootstrap 5 / Tailwind, Chart.js, Leaflet, Vite; Python ML service.
Date: October 10, 2026.

## Setup after pulling these changes

```bash
php artisan migrate                                   # 7 new migrations (2026_10_10_000001 … 000007)
php artisan db:seed --class=MaoPersonnelSeeder        # the 4 real MAO personnel (also part of DatabaseSeeder)
php artisan iclimate:import-yield-backtest            # predicted vs actual yield (4.2)
php artisan db:seed --class=LianSampleFarmersSeeder   # optional, local/testing only: 120 sample farmers
npm ci && npm run build                               # new CSS/JS (gov-theme.css, panel-revisions.js)
php artisan test --filter=PanelRevisionsTest          # 17 tests for the revisions
```

### MAO accounts (panel revision 4.4 A)

| Full name | E-mail (login) | Default password | Role | Position | Barangays covered | Area (ha) |
|---|---|---|---|---|---|---|
| Ofelia V. Rodil | ofeliarodil@gmail.com | Password@123 | Admin | Municipal Agriculturist | All barangays of Lian | — |
| Jean L. Laviste | jeanlaviste@gmail.com | Password@123 | MAO | Agricultural Technologist | Kapito, Malaruhatan, Bungahan, San Diego | 245.15 |
| Lodivico A. Cachuela | lodivicocachuela@gmail.com | Password@123 | MAO | Agricultural Technologist | Balibago, Binubusan, Luyahan, Matabungkay, Tan-ag (San Diego) | 202.83 |
| Evelyn M. Mayari | evelynmayari@gmail.com | Password@123 | MAO | Administrative Aide I, designated Agricultural Extension Worker | Prenza | 262.39 |

- Passwords are stored hashed (bcrypt), never in plain text. The default comes from `MAO_DEFAULT_PASSWORD` (`.env`).
- First sign-in: every page leads to **Change your password** until a new one is saved. **Skip for now** is shown while
  `MAO_ALLOW_SKIP_PASSWORD_CHANGE=true` (testing / defense) and lasts for that session only; set it to `false` for real use.
- The Municipal Agriculturist (MAO Office → MAO Personnel) adds accounts, edits them, sets them Inactive, resets a
  password back to the default (the person must change it again), and reassigns barangays.
- A new account's e-mail is made from the name when left blank (firstnamelastname@gmail.com); when taken, a number is
  added (jeanlaviste2@gmail.com). Duplicate e-mails are refused.

## Phases

| Phase | Scope | Items |
|---|---|---|
| 0 | One config file per concern: `config/yield.php`, `config/weather_risk.php`, `config/farm_calendar.php` (harvest timing) | – |
| 1 | MAO personnel, roles, barangay coverage, "done by" stamps, activity log | 4.4 |
| 2 | Many organizations per farmer, early/late harvest reason, yields in t/ha | 1.1, 1.3, 1.2 |
| 3 | MAO dashboard: Total Farmers KPI, year range filter, rain + yield chart, predicted vs actual | 4.1, 3.1, 3.2, 4.2 |
| 4 | Four-level weather risk heat map | 2.1 |
| 5 | Live search | 1.4 |
| 6 | White government theme + phone layout | 4.5, 4.3 |
| 7 | Verification: tests, build, screenshots at 360/390/414 px | all |

---

## 1.1 Multiple farmer organizations

- **Database:** new pivot `farmer_organization_members` (farmer_profile_id, farmer_organization_id, date_joined). The name
  `farmer_organizations` was already the organizations table. The migration copies every farmer's current organization
  into the pivot. `farmer_profiles.farmer_organization_id` stays as the *primary* organization (it sets the barangay and
  farm type at sign-up) and is always one of the memberships.
- **Backend:** `FarmerProfile::organizations()`, `syncOrganizations()`, `organization_names`; `FarmerOrganization::members()`.
  `FarmerOrganizationSummaryService` counts memberships, so a farmer in two organizations is counted in both. The MAO
  farmer list's organization filter matches any membership. Registration, the MAO form and the farmer's profile save them.
- **Frontend:** checkbox tiles (large tap targets, one column on phones) on registration ("Other organizations you belong
  to"), the MAO Farmer Profile form and the farmer's Profile page; an "Organizations" column and pop-up field.
- **Test:** tick two organizations on a farmer's Profile → both show on the profile and in the MAO list; filter the MAO
  list by either organization → the farmer appears; the "Farmers by Organization" chart counts them in both.

## 1.2 Yield in tons per hectare (t/ha)

- **Database:** none needed. Yields were already stored as t/ha; the conversion happens on save.
- **Config:** `config/yield.php`: unit, 2 decimals, `sack_kg` (50; `.env` `YIELD_SACK_KG`).
- **Backend:** `App\Support\YieldUnit`: yield (t/ha) = total harvest in tons ÷ area harvested in hectares. Rice Production
  records accept the total harvest in tons, kg or sacks (stored in tons) and compute a blank yield from it. Model outputs
  are t/ha with 2 decimals (`config/prediction.php`), so predicted and actual are comparable.
- **Frontend:** "Or enter your total harvest" converter (sacks / kg / tons → t/ha) on the planting form and the
  "What was your actual harvest?" card; "t/ha" added to every yield label, column, axis, tooltip and KPI that lacked it.
- **Test:** enter 160 sacks on 2 ha → 4.00 t/ha (with 50 kg sacks); change `YIELD_SACK_KG` → the result changes.

## 1.3 Reason for an early or late harvest

- **Database:** `farmer_planting_schedules.harvest_timing` (On time / Early / Late), `harvest_timing_reason`,
  `harvest_timing_reason_other`. Existing harvests get their timing back-filled.
- **Config:** `config/farm_calendar.php` → `harvest_timing`: ±3-day tolerance and the reason list (Typhoon/strong winds,
  Heavy rain/flooding, Drought, Pest or disease, Crop matured early, Labor or machinery, Market/price timing, Other).
- **Backend:** `App\Support\HarvestTiming` computes the timing; an early or late harvest without a reason is refused
  (Activity Calendar and planting form). The model keeps the timing right on every save.
- **Frontend:** the reason dropdown (and "Other" text box) appears as soon as the chosen date is early or late, with
  "Your harvest is 12 day(s) early (expected on Nov 11, 2026)". The timing and reason show on the calendar, in My Harvest
  Records, and on the MAO Planner (Harvest timing column). English and Filipino.
- **Test:** record a harvest 2 weeks before the expected date → a reason is required; save with "Other" → the planner
  shows "Early (14 days) – …".

## 1.4 Live search

- **Backend:** `GET /farmers/search?q=` (MAO and IT): every word must match part of the name, farmer ID (RSBSA no. or
  record no.), barangay or organization, in any letter case, within the MAO user's coverage. At most 12 results.
- **Frontend:** "Find a farmer" box on the MAO dashboard and Farmer Profiles. Results appear as you type (300 ms debounce),
  matched text highlighted, name with barangay, organizations and ID, "No results found" when empty, keyboard arrows,
  52 px rows, and on phones the box scrolls up so the list is not under the keyboard. The list pages' own search boxes
  (staff lists and the farmer Notifications page) also update as you type and highlight matches.
- **Privacy:** farmers cannot search other farmers (the endpoint answers 403 for them).
- **Test:** type "dela kap" → Leonardo E. Dela Cruz of Kapito appears with "Dela" and "Kap" highlighted; "zzz" → "No results found".

## 2.1 Weather risk levels on the heat map

- **Config:** `config/weather_risk.php` holds the levels, colours (green / yellow / orange / red), every threshold and the
  written rules: heavy rain (25 / 50 / 100 mm per day or 200 mm total), wind (35 / 62 / 89 km/h), heat (35 °C; 38 °C for
  3+ days), no rain for rainfed farms, and an active typhoon advisory → Severe.
- **Backend:** `BarangayWeekRiskService` rates Low / Moderate / High / Severe and names the main weather factor;
  `HeatMapPanel` adds the factor and the crop-impact note in English and Filipino.
- **Frontend:** both dashboards (MAO preview and gauge; the Heat Map page that farmers and MAO use; the farmer Home risk
  card). The legend always shows all four levels. Tooltip / tap shows barangay, level, main factor ("Heavy rainfall:
  120 mm in one day …") and the possible crop impact; on phones the clicked barangay's panel shows the same, and the map
  pinch-zooms.
- **Test:** `PanelRevisionsTest::test_the_heat_map_rates_four_weather_risk_levels`; on the map, tap a barangay.

## 3.1 Year range filter (Rice Yield by Year)

- **Backend:** `MaoYieldAnalytics::yieldByYear()` sends every year with all-month and per-season averages; the
  controller reads `?from=&to=&season=` and refuses a start year after the end year with a message.
- **Frontend:** Start year, End year, Season, and presets Last 3 years / Last 5 years / All years. The chart, its title
  and its totals (average, highest, lowest, change) update immediately; the choice stays in the address bar.
- **Test:** choose 2025 → 2022: "The start year cannot be after the end year." and the charts keep the last valid range.

## 3.2 Weather Impact: rain and yield

- **Backend:** `MaoYieldAnalytics::rainAndYield()`: per rice season, total rainfall (mm) and average yield (t/ha).
- **Frontend:** combined chart: rainfall bars on the left axis (mm), yield line on the right axis (t/ha); the tooltip
  shows both; it follows the same Year range / Season filter, and the one-line reading is recomputed.
- **Note:** the dataset has no harvest tonnage, so the line is the average yield in t/ha (as the panel allowed).

## 4.1 Accurate Total Farmers KPI

- **Backend:** the tile counts every registered farmer account (role Farmer, any status except Rejected), not only farmer
  profiles. It shows how many are active and how many wait for approval; it refreshes whenever farmers change.
  When filtered to an MAO user's barangays it reads "Total farmers (filtered)" with "All of Lian: N" beside it.
- **Seeder:** `LianSampleFarmersSeeder` (local/testing only): 120 realistic sample farmers across Lian's 19 barangays,
  with organizations (some in two), farms, harvests (on time / early / late with reasons) and predictions. Remove with
  `SAMPLE_FARMERS_PURGE=true php artisan db:seed --class=LianSampleFarmersSeeder`.
- **Changed behaviour:** before, the tile counted farmer profiles only. The tests that asserted the old count were
  updated to the panel's rule.

## 4.2 Predicted vs actual yield for Lian

- **Database:** `municipal_yield_predictions` (year, month, season, barangay, predicted_yield, actual_yield, split, model).
- **Data:** `python_scripts/backtest_candidate_yield.py` runs the trained candidate model on every month of the modeling
  dataset; `php artisan iclimate:import-yield-backtest` loads it (60 months, 2020–2025). Months used to train the model are
  marked "trained" (in-sample); 2025 months were held out (out-of-sample), and their accuracy is reported separately.
- **Frontend:** chart (actual bars, predicted line) per year or per season; summary: predicted, actual, average
  difference, MAPE and accuracy; a table per period; drill-down per barangay from farmers' own harvests.
- **Current result:** MAPE 12.2% over all 60 months; **9.6% on the 8 held-out months (accuracy 90.4%)**.

## 4.3 Mobile responsiveness

Checked with headless Chrome at 360, 390 and 414 px (farmer Home, Heat Map, My Farm, Profile, planting form, menu,
Notifications, login) and at 1366 px plus 390 px for the MAO pages: no horizontal page scrolling on any page, no
JavaScript errors, every farmer-page input ≥ 16 px, buttons and inputs ≥ 44 px, one-column forms, tables that stack into
cards (`is-stack`) or scroll in their own box, charts sized to the screen, the menu in a slide-in drawer, the farm
selector on its own row in the phone header, and the map pinch-zoomable with a readable legend.

## 4.4 Specific MAO user identification

- **Database:** `users` + position, station_type, area_covered_ha, is_mao_head; `mao_user_barangays` (user_id,
  barangay_profile_id, barangay, sitio); `mao_activity_logs`; `created_by` / `updated_by` on farmer profiles, rice
  production, climate records, advisories, organizations, planting schedules, MAO activities and barangay profiles.
- **Seeded personnel:** Ofelia V. Rodil (Municipal Agriculturist, head, municipal-wide); Jean L. Laviste (Agricultural
  Technologist: Kapito, Malaruhatan, Bungahan, San Diego, 245.15 ha); Lodivico A. Cachuela (Agricultural Technologist:
  Balibago, Binubusan, Luyahan, Matabungkay, Tan-ag – a sitio of San Diego, 202.83 ha); Evelyn M. Mayari (Administrative
  Aide I, designated Agricultural Extension Worker: Prenza, 262.39 ha).
- **Access:** the head sees everything, manages MAO accounts and reassigns barangays (MAO Office → MAO Personnel). Field
  personnel see their barangays by default (dashboard KPIs and charts, farmer list, production records, approvals,
  planner, search) and can switch to a read-only municipal view; records outside their barangays cannot be changed.
- **Shown:** header "Jean L. Laviste – Agricultural Technologist" with a coverage bar; farmer Home and Profile show "Your
  Agricultural Technologist: …" by barangay and sitio; every record shows "Created by / Edited by / Verified by /
  Approved by: name (position) on Oct 10, 2026, 4:16 PM"; record lists have a "Validated by" column.
- **Activity Log:** MAO Office → Activity Log, filterable by MAO user, action, barangay and dates. The head sees all;
  others see their own. Every audited MAO action is logged automatically.
- **Note:** the old shared accounts (`mao@gmail.com`, `mao@iclimate.com`) still work but are not tied to a person; the
  Municipal Agriculturist should set them Inactive on MAO Personnel. Barangays with no assigned field personnel
  (e.g. Cumba, Lumaniag, the Poblacion barangays) are listed on MAO Personnel and covered by the Municipal Agriculturist.

## 4.5 White, government-style theme

- `resources/css/gov-theme.css`: every colour is a token in `:root` (background #F5F7FA, cards white with #E5E7EB borders,
  primary #2E7D32, government blue #0038A8, gold #FCD116, text #1F2937, labels #4B5563). Loaded last on every page.
- Government strip (Republic of the Philippines · Province of Batangas · Municipality of Lian), white header with the
  Municipal Agriculture Office name and the official seal of Lian (`public/images/lian-seal.jpg`),
  white sidebar with a green active item, white cards, tables, forms, modals and login page, charts on white with light
  gridlines (Inter font). Contrast meets WCAG AA (body text 14.7:1, white on green 5.1:1).
- The app is locked to the light theme and the dark-only stylesheet (`theme-dark-pages.css`) is no longer loaded.

---

## Status checklist (for the panel)

- [x] 1.1 Multiple farmer organizations
- [x] 1.2 Yield in t/ha
- [x] 1.3 Early/late harvest reason
- [x] 1.4 Live search
- [x] 2.1 Weather risk heatmap
- [x] 3.1 Year range filter
- [x] 3.2 Rain and total yield chart
- [x] 4.1 Total Farmers KPI
- [x] 4.2 Predicted vs. actual yield (Lian)
- [x] 4.3 Mobile responsiveness
- [x] 4.4 Specific MAO user identification
- [x] 4.5 White government-style theme
