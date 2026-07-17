# Online Agricultural Advisory System

The iClimate advisory module fetches a seven-day Open-Meteo forecast for Lian, Batangas, stores the normalized weather records, evaluates configurable advisory rules, and publishes or queues agricultural advisories for MAO review.

## Environment Values

Add these values to `.env` when they differ from the defaults:

```dotenv
OPEN_METEO_BASE_URL=https://api.open-meteo.com/v1
ICLIMATE_LATITUDE=14.033
ICLIMATE_LONGITUDE=120.650
ICLIMATE_TIMEZONE=Asia/Manila
ICLIMATE_LOCATION_NAME="Lian, Batangas"
OPEN_METEO_TIMEOUT=10
```

## Setup Commands

```bash
php artisan migrate
php artisan db:seed --class=AdvisoryRuleSeeder
php artisan iclimate:fetch-weather
php artisan iclimate:generate-advisories
php artisan iclimate:expire-advisories
php artisan schedule:work
```

For local XAMPP scheduling, create a Windows Task Scheduler task that runs `php artisan schedule:run` every minute from the project directory. The Laravel scheduler fetches weather hourly, generates advisories shortly after, and expires outdated advisories every 30 minutes.

## How It Works

`OpenMeteoService` calls the official `/forecast` endpoint with hourly temperature, humidity, rainfall, wind, soil temperature, and soil moisture variables plus daily rainfall, wind, temperature, evapotranspiration, sunrise, and sunset values. The raw response is saved with normalized daily records in `external_weather_data`.

`AdvisoryGenerationService` loads the latest stored forecast, expires outdated advisories, and sends weather metrics to `AdvisoryRuleEngine`. The engine evaluates active `advisory_rules`, creates advisories with a unique `generation_key`, and skips duplicates.

API failures are logged. If a fresh API response is unavailable, the system keeps using the last successful stored forecast. Weather data less than 2 hours old is fresh, 2 to 6 hours is delayed, and older than 6 hours is outdated. High and critical advisories generated from outdated data require staff review.

## Approval Flow

Farmers can view published, active advisories for all barangays or their own barangay. MAO Personnel and IT Experts can open `/management/advisories` to refresh weather, regenerate advisories, approve and publish generated records, reject them with a reason, or archive records.

Critical advisories and crop-dependent harvest advisories are queued for review. Approved advisories record `approved_by`, `approved_at`, and `published_at`.

## Initial Rule Thresholds

- Heavy Rainfall Warning: rain probability at least 80% and daily rainfall at least 30 mm.
- Severe Rainfall and Flooding Risk: daily rainfall at least 50 mm, or two consecutive days at least 30 mm.
- High Temperature Advisory: maximum temperature at least 35 C.
- Strong Wind Advisory: maximum wind speed at least 35 km/h.
- Potentially Favorable Planting Conditions: seven-day rainfall 20 to 70 mm, average maximum temperature 25 to 33 C, and at least two rainy-probability days.
- Consider Delaying Planting Activities: seven-day rainfall at least 100 mm, or a critical rainfall advisory exists.
- Supplemental Irrigation May Be Needed: next three-day rainfall below 5 mm, evapotranspiration at least 4 mm/day, and low soil moisture when available.
- Consider Postponing Irrigation: next 24-hour rainfall at least 15 mm, or rain probability at least 80%.
- Consider Harvesting Before Expected Rainfall: harvest-ready crop context plus 48-hour rain probability at least 70% or rainfall at least 15 mm.
- Delay Harvesting During Hazardous Weather: harvest-ready crop context plus heavy rain and strong wind.

Adjust thresholds by editing records in `advisory_rules.conditions` or updating `AdvisoryRuleSeeder` and re-running the seeder. New rule types should be added as database rules and matched in `AdvisoryRuleEngine`.

## Safety Notes

The system labels Open-Meteo and iClimate-generated guidance clearly. It does not present generated advisories as official PAGASA warnings. Advisories use cautious wording such as "may", "consider", and "consult the Municipal Agriculture Office" because actual farm conditions can differ from forecast data.
