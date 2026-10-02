# Farmer dashboard: where every value comes from

Page: `/farmer/dashboard` · Controller: `DashboardController::farmer` / `farmerLive` · Data: `FarmerDashboardService` (+ `FarmerKpiService` for the maths)
Words: `lang/en/farmer.php` · Thresholds: `config/farmer_dashboard.php` · Times: Asia/Manila

| Card / value | Source | Notes |
|---|---|---|
| Greeting, place | `users.name`, `farmer_profiles.barangay` | "Poblacion, Lian, Batangas" |
| "Updated N minutes ago", Old data | Server fetch time of the weather reading (`FarmerDashboardWeatherService`) | Old after 60 min; cache at most 30 min |
| Weather now (temp, feels like, humidity, wind, condition) | WeatherAPI / Open-Meteo, one shared reading for Lian | Not measured on the farm |
| Rain expected today, chance of rain | Same reading, today's entry of its daily list | Same number as today's day card |
| "Clear right now. Rain expected later today…" | Built from current condition + today's rain | One sentence, cannot contradict itself |
| What to do today | Fixed rules on the same reading + barangay risk | 1 to 3 actions |
| Weather warnings | Weather notifications, forecast heavy rain / wind / heat, risk High+, open typhoon check-in | |
| Rain this past week | Open-Meteo daily record (`RecentRainService`, `past_days`) | All 7 days needed, else "—" |
| Rain coming | Daily rain from the same weather reading | |
| Rain vs normal | Month-to-date daily record ÷ Ambulong long-term monthly average, spread evenly over the month | Needs all days, else "—" |
| Heat today | Provider "feels like" high, else worked out (heat index) from today's high and humidity | PAGASA levels from config |
| Good days for spraying | Chance of rain < 40% and wind < 15 km/h (config) | Same rule marks the day cards |
| Alerts | Count of the warnings above | |
| Weather this week + charts | Same weather reading (7 days) | Rain bars; separate temperature chart |
| My Crop Season | `farmer_planting_schedules` + `FarmerCropPlanService` | Stage windows in config |
| Expected Harvest | Rice-yield model via `RiceYieldPredictionService` (cached 6 h, run time stored) | Range = model's own uncertainty, only if given |
| Harvest comparison | `ProjectYieldDataset` (Lian monthly average), the farmer's recorded harvests | |
| Rain each month | Ambulong station CSV (`AmbulongHistoricalClimateDataset`) vs average of other years | Latest year on record |
| Farm risk | `BarangayRiskMapService::detailFor`, `HeatmapArea.risk_type` | One overall level; no separate flood / drought / typhoon levels exist |
| Setup list | `farmer_profiles`, `farm_boundaries` | |
| Advisories | `planting_advisories` (published only) | |

Not found in the system: daily rain records stored by iClimate (only monthly Ambulong data, so daily history is fetched from Open-Meteo); a rice variety or its length per farm (a configured default of 115 days is used when the schedule has no harvest date); separate flood, drought and typhoon risk levels; any Ambulong record for 2026 (latest is 2025); a harvest range from the model beyond its uncertainty value.
