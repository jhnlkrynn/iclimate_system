<?php

/*
|--------------------------------------------------------------------------
| Weather risk levels on the Heat Map (panel revision 2.1)
|--------------------------------------------------------------------------
|
| Each barangay is rated from its saved daily weather forecast (rainfall, wind, maximum temperature) for the period
| shown (This Week = 7 days, 2 Weeks = 14 days) and from any active typhoon / tropical cyclone advisory.
| The worst hazard sets the level. App\Services\Risk\BarangayWeekRiskService applies these rules.
|
| RULES (all thresholds below can be changed here; IT can also override the rain/wind/heat ones on System Settings):
|
|   Heavy rain    one or more days at or above `heavy_rain_mm`                          → Moderate
|                 3+ heavy-rain days, or one day at or above `very_heavy_rain_mm`        → High
|                 one day at or above `extreme_rain_mm`, or the period total at or above
|                 `extreme_rain_total_mm` (flooding likely)                               → Severe
|   Strong wind   strongest day at or above `strong_wind_kmh`                             → Moderate
|                 at or above `storm_wind_kmh` (tropical storm, PAGASA)                   → High
|                 at or above `severe_wind_kmh` (severe tropical storm, PAGASA)           → Severe
|   Heat          1–2 days at or above `hot_day_c`                                        → Moderate
|                 3+ such days                                                            → High
|                 `extreme_heat_days`+ days at or above `extreme_heat_c`                  → Severe
|   No rain       rainfed barangays only: no day with `rain_day_mm` or more in the period  → Moderate
|                 (High when the period is 14 days)
|   Typhoon       an active typhoon / tropical cyclone advisory for Lian                  → Severe (all barangays)
|   None of the above                                                                     → Low
|
| IMPORTANT: these limits are working defaults and must be confirmed by the Municipal Agriculture Office of Lian before
| they are treated as official advice.
*/

return [
    'levels' => ['low', 'moderate', 'high', 'severe'],

    // Legend colours (green / yellow / orange / red), readable on white.
    'colors' => [
        'low' => '#2E7D32',
        'moderate' => '#F2C230',
        'high' => '#EF6C00',
        'severe' => '#C62828',
        'nodata' => '#9CA3AF',
    ],

    'labels' => [
        'low' => 'Low Risk',
        'moderate' => 'Moderate Risk',
        'high' => 'High Risk',
        'severe' => 'Severe Risk',
        'nodata' => 'No data',
    ],

    'thresholds' => [
        'heavy_rain_mm' => 25.0,
        'very_heavy_rain_mm' => 50.0,
        'extreme_rain_mm' => 100.0,
        'extreme_rain_total_mm' => 200.0,
        'strong_wind_kmh' => 35.0,
        'storm_wind_kmh' => 62.0,
        'severe_wind_kmh' => 89.0,
        'hot_day_c' => 35.0,
        'extreme_heat_c' => 38.0,
        'extreme_heat_days' => 3,
        'rain_day_mm' => 1.0,
    ],

    // Short notes on what each hazard can do to rice, shown in the map tooltip.
    'crop_impact' => [
        'heavy_rain' => 'Possible flooding of paddies, lodging of plants and grain spoilage if harvesting.',
        'strong_wind' => 'Plants may lodge (fall over) and grains may shatter, especially near harvest.',
        'heat' => 'High heat during flowering can cause empty grains and lower yield.',
        'dry' => 'Rainfed fields may lack water; seedlings and young plants can be stressed.',
        'typhoon' => 'Typhoon conditions: secure harvests and inputs, expect flooding and crop damage.',
        'calm' => 'No weather hazard expected; normal field work can continue.',
        'nodata' => 'No forecast saved yet for this barangay.',
    ],

    // Flood layer: no MGB/NOAH/GloFAS dataset is shipped with this project.
    // The layer therefore uses the MAO's stored flood_prone attribute when
    // present, plus the saved Open-Meteo forecast; it never invents a hazard
    // classification from a barangay name alone.
    'flood_layer' => [
        'moderate_daily_rain_mm' => 25.0,
        'flood_prone_daily_rain_mm' => 50.0,
        'flood_prone_total_rain_mm' => 200.0,
        'note' => 'MAO flood-prone attributes plus saved Open-Meteo rainfall/runoff forecast; no official MGB/NOAH/GloFAS layer is installed.',
    ],
];
