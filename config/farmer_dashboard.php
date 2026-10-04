<?php

/*
|--------------------------------------------------------------------------
| Farmer dashboard thresholds
|--------------------------------------------------------------------------
|
| Every number that turns a measurement into a plain-language label on the
| Farmer dashboard lives here, so an agriculturist can adjust it without
| touching code. Used by App\Services\Analytics\FarmerKpiService.
|
| IMPORTANT: the "good work day" limits, the crop-stage windows, the weekly
| rain bands and the "more / less than usual" band are working defaults. They
| MUST be checked and signed off by the Municipal Agriculture Office (MAO) of
| Lian before they are treated as advice for farmers.
*/

return [
    /*
    | Heat levels, PAGASA heat index classes (°C). A "feels like" value at or
    | above the number gets that level; the highest matching level wins.
    | Below the first number there is no PAGASA class ("normal").
    */
    'heat_levels' => [
        'caution' => 27,
        'extreme_caution' => 33,
        'danger' => 42,
        'extreme_danger' => 52,
    ],

    /*
    | A day is good for spraying / fertilizer when the chance of rain is UNDER
    | max_rain_chance_percent and the wind is UNDER max_wind_kmh.
    | Defaults must be checked with the Municipal Agriculture Office.
    */
    'good_work_day' => [
        'max_rain_chance_percent' => 40,
        'max_wind_kmh' => 15,
    ],

    /*
    | "More than usual / Normal / Less than usual": rain this month so far,
    | compared with the long-term average for the same days, within this
    | percentage band either side of 100%.
    */
    'rain_normal_band_percent' => 20,

    /* Rain on one day at or above this is "heavy rain" (same limit the MAO dashboard uses). */
    'heavy_rain_mm' => \App\Services\Analytics\MaoDashboardService::HEAVY_RAIN_MM,

    /* A forecast day counts as "rain expected" at or above this amount of rain, or this chance of rain. */
    'rain_expected' => [
        'min_mm' => 1.0,
        'min_chance_percent' => 50,
    ],

    /* Plain-language meaning of a week of rain (mm in 7 days). Below light_below is "a little", at or above heavy_at is "a lot". */
    'rain_week_bands' => [
        'light_below' => 10,
        'heavy_at' => 75,
    ],

    /*
    | One plain word for today's temperature (°C), rain (mm) and humidity (%)
    | under "Today's Farm Conditions". A value at or above the number gets
    | that word. Heavy rain uses heavy_rain_mm and very high humidity uses
    | humid_percent, so a word never disagrees with a warning.
    | Defaults must be checked with the Municipal Agriculture Office.
    */
    'today_levels' => [
        'temperature' => [
            'mild_at' => 24,
            'warm_at' => 29,
            'hot_at' => 33,
            'very_hot_at' => \App\Services\Analytics\MaoDashboardService::VERY_HOT_C,
        ],
        'rain' => [
            'light_at' => 0.1,
            'moderate_at' => 7.5,
        ],
        'humidity' => [
            'moderate_at' => 40,
            'high_at' => 70,
        ],
    ],

    /*
    | Rice yield forecast: an estimate within this percentage either side of
    | the Lian average reads "within the typical range".
    */
    'yield_typical_band_percent' => 10,

    /* Humidity (%) at or above this: rice disease can spread. */
    'humid_percent' => 85,

    /* Wind (km/h) at or above this makes spraying work poorly. */
    'strong_wind_kmh' => 20,

    /*
    | Weather freshness. Weather is cached for at most `cache_max_minutes`
    | (whatever services.weather.cache_minutes says is capped to this), and is
    | marked "Old data" when its fetch time is older than `stale_after_minutes`.
    */
    'weather' => [
        'cache_max_minutes' => 30,
        'stale_after_minutes' => 60,
    ],

    /*
    | Crop season. Used only when the farmer's schedule has no harvest date.
    | Stage windows are fractions of the season length (planting is in days).
    | A typical 115-day lowland rice variety; the real variety length is not
    | stored per farm yet.
    */
    'crop' => [
        'default_duration_days' => 115,
        'planting_days' => 7,
        'growing_until' => 0.50,
        'flowering_until' => 0.68,
        'ripening_until' => 0.88,
    ],

    /* Daily rain history used for "Rain this past week" and "compared with normal". */
    'rain_history' => [
        'days' => 35,
        'cache_minutes' => 180,
    ],
];
