# Handoff: farmer side (Naicel → Kaye), October 2026

This note covers what changed on the farmer side and what the MAO/IT side can now use. I did not edit any MAO or IT page, or the shared top bar and sidebar. In shared files, my lines sit inside blocks marked `Farmer – Naicel`.

## Database

All of these are new migrations. Run `php artisan migrate`.

| Migration | What it adds |
|---|---|
| `2026_10_07_000001_create_farms_table` | **`farms`**: `id`, `user_id`, `name`, `barangay`, `area_hectares`, `farm_type` (`Irrigated`/`Rainfed`), `archived_at`, timestamps. Each farmer's existing farm (from `farmer_profiles`) became **"Farm 1"**. |
| `2026_10_07_000002_add_farm_id_to_farmer_planting_schedules_table` | **`farmer_planting_schedules.farm_id`** (nullable FK → `farms`, null on delete). Existing rows were set to the farmer's Farm 1. |
| `2026_10_07_000003_create_yield_prediction_feedback_table` | **`yield_prediction_feedback`**: one row per harvested planting with `user_id`, `farm_id`, `farmer_planting_schedule_id` (unique), `season`, `season_year`, `barangay`, `farm_type`, a snapshot of the prediction (`predicted_yield`, `prediction_source`, `prediction_model_version`, `prediction_generated_at`), `actual_yield` (t/ha), `harvested_on`, `submitted_at`. |

There are no new tables for notifications or the forecast survey:

- **Missing-info notifications** are ordinary rows in `notifications`, with `category` set to `setup:barangay`, `setup:farm`, `setup:farm_area`, `setup:planting_date` or `setup:harvest`.
- **The daily forecast survey** writes to the existing `weather_forecast_feedback` table with `forecast_type = 'daily'` (the monthly rows still use `'monthly'`).

### What to keep in mind on the MAO side

- **`farmer_profiles` still exists and still works.** It always mirrors the farmer's first active farm (barangay, `farm_area`, `farm_type`), so every MAO page that reads it shows the same values as before. When a farmer edits their first farm, the profile is updated. When the MAO edits a profile, the first farm picks up the change the next time the farmer's farms are read.
- **To list all of a farmer's farms**, use `Farm::query()->where('user_id', $id)->active()`. `active()` skips archived farms. Archived farms are never deleted, and their records stay.
- **For farm-level reports**, group `farmer_planting_schedules` by `farm_id`.
- **`yield_prediction_feedback` is stored only, for a future retraining.** Nothing reads it automatically and nothing retrains. If IT builds a retraining export, read it from there.

## Models and services

| Name | Purpose |
|---|---|
| `App\Models\Farm` | A farm (`plantingSchedules()`, `scopeActive()`, `hasArea()`) |
| `App\Models\YieldPredictionFeedback` | An actual harvest next to its prediction |
| `FarmerPlantingSchedule::farm()` | New relation |
| `WeatherForecastFeedback::TYPE_DAILY` | New constant |
| `App\Services\Farmer\FarmService` | A farmer's farms: `farms()`, `primary()`, `create()`, `update()`, `archive()`, plus syncing with the profile |
| `App\Services\Farmer\FarmerMissingInfoNotifier` | One bell notification per missing item, never duplicated, removed once the item is filled in. `linkFor($category, $locale)` gives the link. |
| `App\Services\Farmer\YieldSurveyService` | "Ano ang tunay mong ani?": `pending()`, `predictedFor()`, `record()` |
| `App\Services\Farmer\FarmerHomeCharts` | The two charts on the farmer's Home page |
| `WeatherFeedbackService::pendingDailyPrompt()` / `submitDaily()` / `storedDailyForecast()` | The daily forecast survey |
| `PrepareFarmerYieldEstimate` | Now takes an optional `$farmId`, so each extra farm keeps its own estimate. Cache key: `FarmerDashboardService::farmPredictionCacheKey($userId, $farmId)`. `iclimate:warm-farmer-yields` warms these too. |

## Routes

All of these are farmer-only and sit in `routes/web.php` inside the `Farmer – Naicel` block.

| Method | URL | Name |
|---|---|---|
| POST | `/farmer/farms` | `farmer.farms.store` |
| PUT | `/farmer/farms/{farm}` | `farmer.farms.update` |
| POST | `/farmer/farms/{farm}/archive` | `farmer.farms.archive` |
| POST | `/farmer/activities` | `farmer.activities.store` (date, kind `planted`/`harvested`, farm_id) |
| POST | `/farmer/yield-survey/{plantingSchedule}` | `farmer.yield-survey.store` |
| POST | `/farmer/forecast-survey` | `farmer.forecast-survey.store` |

`GET /farmer/dashboard?farm={id}` opens Home for one of the farmer's farms. The farm selector only appears when the farmer has 2 or more farms.

**There are no MAO routes yet.** If you want MAO views of these, the data is ready:

- farms per barangay: `farms`
- actual vs predicted yield: `yield_prediction_feedback`
- daily forecast accuracy: `weather_forecast_feedback` where `forecast_type = 'daily'`. `WeatherFeedbackSummaryService` already accepts daily rows once the day has passed.

## Shared files I touched (marked blocks only)

- `routes/web.php`: the route block above.
- `lang/en/farmer.php` and `lang/fil/farmer.php`: new keys `farms`, `activity`, `yield_survey`, `forecast_survey`, `charts`, `missing_info`. The greeting now ends with "!".
- `NotificationController::feed()`: adds `link_url` and `link_label` for `setup:*` notifications. The bell component itself was not changed.
- `advisories/index.blade.php` and `advisories/_announcements.blade.php`: changes on the farmer branch only. A farmer with no advisories now sees just the page title. Staff views are unchanged.
- `weather-predictions/index.blade.php` and `WeatherPredictionController::index`: the survey card, shown to farmers only.
- `DashboardController::farmer()` and `farmerLive()`: the farm selector and the missing-info sync.
