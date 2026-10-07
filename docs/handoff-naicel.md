# Handoff: farmer side (Naicel → Kaye), October 2026

What changed on the farmer side and what the MAO/IT side can use. I did not edit any MAO or IT page, the shared top bar or the sidebar. In shared files, my lines sit inside blocks marked `Farmer – Naicel`.

**Farms are John Luke's.** Multiple farms come from his work (commit 77643e4: `App\Models\Farm`, `App\Services\Farms\FarmService`, `App\Support\ActiveFarm`, the `farmer.farms.*` pages). My farmer features were moved onto his model. I dropped my own earlier farms table; it is kept only on the branch `ui-naicel-backup`.

## Database

### `farms` (John Luke)

Migration `2026_10_07_000001_create_farms_table`.

| Column | Meaning |
|---|---|
| `user_id` | The farmer who owns the farm |
| `farm_name` | The farm's name, e.g. "Farm 1" |
| `barangay` | One of Lian's 19 official names, or null |
| `location_description` | Sitio / purok / landmark |
| `location_method` | `barangay`, `pin` or `boundary` |
| `latitude`, `longitude` | Optional point |
| `farm_size_hectares` | The size as a number; only set when known |
| `farm_size_method` | How the size is known: `exact`, `estimated`, `mapped` or `unknown` |
| `farm_size_estimate_range` | Only for `estimated`: `lt_0_5`, `0_5_1`, `1_2`, `2_3` or `gt_3` |
| `farm_size_unit` | `hectares` or `square_meters` (the unit the farmer typed) |
| `farm_type` | `Irrigated`, `Rainfed` or null |
| `is_primary` | The farmer's primary farm |
| `archived_at` | Set when the farm is removed; farms are never deleted |

Other farm migrations (also John Luke's):

- **`2026_10_07_000002`** adds `farm_id` to `farmer_planting_schedules` and `farm_boundaries`.
- **`2026_10_07_000003`** turned each existing farmer profile into a primary farm named **"Farm 1"**.

What to use when reading farm data:

- **Farm size for a number:** `Farm::knownHectares()` (null for an estimate or no size) or `Farm::modelArea()` (also gives a number for an estimate). Do not read `farm_size_hectares` directly.
- **A farmer's farms in use:** `Farm::query()->ownedBy($user)->active()`.
- **`farmer_profiles` still works:** it mirrors the farmer's **primary** farm (barangay, `farm_area`, `farm_type`), so MAO pages that read it show the same values as before.

### `yield_prediction_feedback` (mine)

Migration `2026_10_07_100001_create_yield_prediction_feedback_table`. One row per harvested planting, with:

- links: `user_id`, `farm_id` (→ `farms`), `farmer_planting_schedule_id` (unique)
- season: `season`, `season_year`, `barangay`, `farm_type`
- a snapshot of the prediction at answer time: `predicted_yield`, `prediction_source`, `prediction_model_version`, `prediction_generated_at`
- the answer: `actual_yield` (t/ha), `harvested_on`, `submitted_at`

**Stored only, for a future retraining. Nothing reads it to retrain a model.**

### No new tables for these

- **Missing-info notifications** are rows in `notifications` with `category` set to `setup:barangay`, `setup:farm`, `setup:farm_area`, `setup:planting_date` or `setup:harvest`.
- **The daily forecast survey** uses the existing `weather_forecast_feedback` table with `forecast_type = 'daily'`. Monthly rows still use `'monthly'`.

## Models and services (mine)

| Name | Purpose |
|---|---|
| `App\Models\YieldPredictionFeedback` | Actual harvest next to its prediction |
| `WeatherForecastFeedback::TYPE_DAILY` | New constant |
| `App\Services\Farmer\FarmerMissingInfoNotifier` | One bell notification per missing item, never duplicated, removed once filled in, in the farmer's language. `linkFor($category, $locale, $user)` gives the link. |
| `App\Services\Farmer\YieldSurveyService` | "Ano ang tunay mong ani?": `pending()`, `predictedFor()`, `record()` |
| `App\Services\Farmer\FarmerHomeCharts` | Home charts: harvest per season (with the barangay average) and 14-day temperature and rain chance |
| `WeatherFeedbackService::pendingDailyPrompt()` / `submitDaily()` / `storedDailyForecast()` | The daily forecast survey |
| `App\Http\Controllers\FarmerActivityController` | Activity Calendar (planted / harvested on one of the farmer's farms) and the actual-harvest answer |
| `App\Http\Controllers\FarmerForecastSurveyController` | Saves the daily forecast answer |

## Routes

All farmer-only, in `routes/web.php` inside the `Farmer – Naicel` block.

**Mine:**

| Method | URL | Name |
|---|---|---|
| POST | `/farmer/activities` | `farmer.activities.store` (`date`, `kind` = `planted`/`harvested`, `farm_id`) |
| POST | `/farmer/yield-survey/{plantingSchedule}` | `farmer.yield-survey.store` (`actual_yield`) |
| POST | `/farmer/forecast-survey` | `farmer.forecast-survey.store` (`forecast_date`, `accuracy`, `observed_condition`) |

**John Luke's farm routes,** all under `/farmer/farms`:

| Method | URL | Name |
|---|---|---|
| GET | `/create` | `farmer.farms.create` |
| POST | `/` | `farmer.farms.store` |
| POST | `/active` | `farmer.farms.select` (the top-bar selector) |
| GET | `/{farm}/edit` | `farmer.farms.edit` |
| PUT | `/{farm}` | `farmer.farms.update` |
| DELETE | `/{farm}` | `farmer.farms.destroy` (archives) |
| POST | `/{farm}/primary` | `farmer.farms.primary` |
| GET | `/{farm}/map` | `farmer.farms.map` |
| PUT | `/{farm}/map` | `farmer.farms.map.update` |
| DELETE | `/{farm}/map` | `farmer.farms.map.destroy` |

**There are no MAO routes for this data yet.** If you want MAO views, the data is ready:

- farms per barangay: `farms`
- actual vs predicted yield: `yield_prediction_feedback`
- daily forecast accuracy: `weather_forecast_feedback` where `forecast_type = 'daily'`

## Shared files I touched (marked blocks only)

- `routes/web.php`: my three routes.
- `lang/en/farmer.php` and `lang/fil/farmer.php`: new keys `activity`, `yield_survey`, `forecast_survey`, `charts`, `missing_info`. The greeting now ends with "!".
- `app/Providers/AppServiceProvider.php`: when a `Farm` or `FarmerProfile` is saved, the farmer's bell reminders are checked again on their next page.
  - The listener returns nothing on purpose. A model-event listener that returns `false` stops the listeners after it, including John Luke's profile → farm sync.
- `NotificationController::feed()`: adds `link_url` and `link_label` for `setup:*` notifications. The bell component itself was not changed.
- `DashboardController::farmer()` and `FarmerDashboardService::payload()`: the missing-info check and the `charts` key.
- `FarmerMyFarmController` and `farmer-records/my-farm.blade.php`: include `farmer-records/_activity.blade.php` (calendar and harvest question).
- `FarmerPlantingScheduleController`: after a save, a typed-in harvest is stored with its prediction.
- Farmer-only branches of `advisories/index.blade.php` and `advisories/_announcements.blade.php`: a farmer with no advisories sees only the page title. Staff views are unchanged.
- `weather-predictions/index.blade.php` and `WeatherPredictionController::index`: the survey card, shown to farmers only.
