<?php

/*
| Every word on the Farmer dashboard (/farmer/dashboard). Short sentences, common words, written for a farmer reading
| on a phone. To add Filipino, copy this file to lang/fil/farmer.php and translate the values: nothing else changes.
|
| Words to avoid on this page: precipitation, relative humidity, heat index, forecast, anomaly, deviation, yield
| prediction, phenological stage, "data unavailable", "synchronization failed".
*/

return [
    'farmer' => 'Farmer',
    'greeting' => [
        'morning' => 'Good morning, :name',
        'afternoon' => 'Good afternoon, :name',
        'evening' => 'Good evening, :name',
    ],
    'place' => ':barangay, Lian, Batangas',
    'place_default' => 'Lian, Batangas',

    'header' => [
        'updated' => 'Updated :age',
        'minutes_ago' => '{0} just now|{1} 1 minute ago|[2,*] :count minutes ago',
        'hours_ago' => '{0} just now|{1} 1 hour ago|[2,*] :count hours ago',
        'js_now' => 'Updated just now',
        'js_minute' => 'Updated 1 minute ago',
        'js_minutes' => 'Updated :count minutes ago',
        'js_hour' => 'Updated 1 hour ago',
        'js_hours' => 'Updated :count hours ago',
        'no_update' => 'We do not know when the weather was last updated',
        'old_data' => 'Old data',
        'refresh' => 'Refresh',
        'refreshing' => 'Refreshing…',
        'refresh_label' => 'Refresh the dashboard',
    ],

    'state' => [
        'error' => "We can't show this right now.",
        'retry' => 'Try again',
        'loading' => 'Loading…',
        'refresh_failed' => "Couldn't update. Try again.",
        'offline' => 'You are offline.',
        'seeing_from' => 'You are seeing what loaded at :time.',
        'updated_announce' => 'Dashboard updated.',
    ],

    'status' => [
        'good' => 'Normal',
        'watch' => 'Watch',
        'danger' => 'Warning',
        'info' => 'Note',
    ],

    'weather' => [
        'unknown' => 'Weather',
        'humidity' => [
            'very_wet' => 'Very wet air. Rice disease can spread.',
            'wet' => 'Wet air.',
            'comfortable' => 'Air is not too wet.',
        ],
        'wind' => [
            'light' => 'Light wind. Good for spraying.',
            'breezy' => 'Breezy. Spray with care.',
            'strong' => 'Strong wind. Do not spray.',
        ],
    ],

    'now' => [
        'title' => 'Weather Now',
        'feels_like' => 'Feels like',
        'humidity' => 'Humidity (how wet the air is)',
        'wind' => 'Wind',
        'rain_today' => 'Rain expected today',
        'chance' => ':percent% chance of rain',
        'rain_today_note' => 'Total for today',
        'source' => 'Weather for Lian • Source: :source • Updated :time',
        'see_full' => "See the week's weather",
        'raining_amount' => 'Raining now. About :mm mm of rain expected today.',
        'raining' => 'Raining now.',
        'later_amount' => ':condition right now. Rain expected later today (about :mm mm).',
        'later' => ':condition right now. Rain expected later today.',
        'dry' => ':condition right now. No rain expected today.',
    ],

    'heat' => [
        'normal' => 'Not hot',
        'caution' => 'Caution',
        'extreme_caution' => 'Extreme Caution',
        'danger' => 'Danger',
        'extreme_danger' => 'Extreme Danger',
    ],

    'advice' => [
        'title' => 'What to Do Today',
        'ask' => 'Ask Climora AI',
    ],

    'actions' => [
        'where_now' => 'It is raining now.',
        'where_later' => 'It is not raining yet.',
        'heavy_rain' => [
            'title' => 'Check that water can drain from your field',
            'reason' => 'A lot of rain is expected today (about :mm mm). Water that stays in the field can hurt your rice.',
            'ask' => 'A lot of rain (about :mm mm) is expected today at my rice farm. What should I do to protect my field?',
        ],
        'rain' => [
            'title' => 'Wait before you put on fertilizer or spray',
            'reason' => 'Rain is expected today (about :mm mm). Rain can wash fertilizer and spray away.',
            'ask' => 'Rain (about :mm mm) is expected today. Should I wait before putting fertilizer or spraying my rice?',
        ],
        'humid' => [
            'title' => 'Look at your rice leaves for spots',
            'reason' => 'Humidity is :percent%. The air is very wet, so rice disease can spread.',
            'ask' => 'Humidity is :percent% today. What disease signs should I look for on my rice leaves?',
        ],
        'wind' => [
            'title' => 'Do not spray in strong wind',
            'reason' => 'Wind is :kmh km/h. Strong wind blows spray away from your plants.',
            'ask' => 'Wind is :kmh km/h today. Is it safe to spray my rice?',
        ],
        'heat' => [
            'title' => 'Check the water in your field',
            'reason' => 'It will feel like :feels°C today (:level). Rice needs more water in the heat.',
            'ask' => 'It will feel like :feels°C today. How do I take care of my rice in this heat?',
        ],
        'risk' => [
            'title' => 'Read the advice for your barangay',
            'reason' => 'Farm risk in your barangay is :level. Check the details before you work in the field.',
            'ask' => 'Farm risk in my barangay is :level. What should I do on my rice farm today?',
        ],
        'normal' => [
            'title' => 'Good day to check your farm',
            'reason' => 'The weather looks normal today. Read the latest advisories before field work.',
            'ask' => 'The weather is normal today. What should I do on my rice farm?',
        ],
        'no_weather' => [
            'title' => 'Check the advisories before field work',
            'reason' => "We can't show today's weather right now, so read the latest advisories first.",
            'ask' => 'What should I do on my rice farm today?',
        ],
    ],

    'alerts' => [
        'none' => 'No weather warnings',
        'list_label' => 'Weather warnings',
        'details' => 'Details',
        'today' => 'today',
        'tomorrow' => 'tomorrow',
        'on_day' => 'on :day',
        'heavy_rain' => [
            'title' => 'Heavy rain expected :when',
            'message' => 'About :mm mm of rain is coming. Check field drainage and do not spray.',
        ],
        'strong_wind' => [
            'title' => 'Strong wind expected :when',
            'message' => 'Winds up to :kmh km/h are coming. Do not spray.',
        ],
        'high_heat' => [
            'title' => 'Very hot weather expected :when',
            'message' => 'Up to :temp°C is coming. Check that your crop has enough water.',
        ],
        'risk' => ['title' => 'Farm risk in your barangay is :level'],
        'typhoon' => [
            'title' => 'Typhoon safety check-in is open',
            'message' => 'Let the Municipal Agriculture Office know if you are safe.',
        ],
    ],

    'source' => [
        'weather' => 'Online weather service',
        'weather_calculated' => 'Online weather service (feels like is worked out from temperature and humidity)',
        'rain_records' => 'Online daily weather records',
        'rain_vs_normal' => 'Online daily weather records and Ambulong station average',
    ],

    'kpi' => [
        'title' => 'My Farm at a Glance',
        'source' => 'Source: :source • :time',
        'updated' => 'updated :time',
        'no_time' => 'update time not known',
        'rain_past' => [
            'label' => 'Rain this past week',
            'meaning_none' => 'No rain was recorded in the last 7 days.',
            'meaning_light' => 'A little rain fell in the last 7 days.',
            'meaning_moderate' => 'A fair amount of rain fell in the last 7 days.',
            'meaning_heavy' => 'A lot of rain fell in the last 7 days. Check your field for standing water.',
            'trend_up' => 'More than the week before (:mm mm)',
            'trend_down' => 'Less than the week before (:mm mm)',
            'trend_flat' => 'About the same as the week before (:mm mm)',
        ],
        'rain_next' => [
            'label' => 'Rain coming this week',
            'label_days' => 'Rain coming in the next :days days',
            'meaning_none' => 'No rain is expected.',
            'meaning_light' => 'Only a little rain is expected.',
            'meaning_moderate' => 'A fair amount of rain is expected.',
            'meaning_heavy' => 'A lot of rain is expected. Plan field work around it.',
            'trend_up' => 'More than this past week (:mm mm)',
            'trend_down' => 'Less than this past week (:mm mm)',
            'trend_flat' => 'About the same as this past week (:mm mm)',
        ],
        'rain_vs_normal' => [
            'label' => 'Rain compared to normal (this month)',
            'detail' => ':mtd mm so far. Usual for the first :days days: :normal mm.',
        ],
        'heat' => [
            'label' => 'Heat today',
            'headline' => 'Heat today: :level',
            'meaning_normal' => 'It will not feel dangerously hot.',
            'meaning_caution' => 'It may feel tiring to work in the sun for a long time.',
            'meaning_extreme_caution' => 'Heat cramps and exhaustion are possible. Rest in the shade and drink water.',
            'meaning_danger' => 'Heat stroke is possible. Work early in the morning and rest often.',
            'meaning_extreme_danger' => 'Heat stroke is very likely. Avoid working in the sun.',
        ],
        'work_days' => [
            'label' => 'Good days for spraying',
            'meaning' => 'Days with under :rain% chance of rain and wind under :wind km/h.',
            'of' => 'Out of :judged days checked',
        ],
        'alerts' => [
            'label' => 'Alerts that need your attention',
            'none' => 'Nothing needs your attention right now.',
            'some' => 'Read these warnings before you go to the field.',
            'source' => 'iClimate alerts',
        ],
    ],

    'rain_vs_normal' => [
        'more' => 'More than usual',
        'normal' => 'Normal',
        'less' => 'Less than usual',
    ],

    'reason' => [
        'rain_records_missing' => 'We only have rain records for :found of the last 7 days.',
        'rain_records_missing_month' => 'We only have rain records for :found of :needed days this month.',
        'forecast_rain_missing' => 'The rain amounts coming are not available yet.',
        'forecast_missing' => 'The weather coming is not available right now.',
        'month_just_started' => 'The month just started. Check again tomorrow.',
        'no_normal' => 'We do not have enough past years to say what is usual.',
        'heat_missing' => "We can't work out how hot it will feel today.",
        'work_days_missing' => "We can't tell which days are good for work right now.",
        'no_planting_date' => 'Add your planting date to see this.',
        'not_planted_yet' => 'Planting is in :days days.',
    ],

    'units' => ['days' => 'days'],

    'stages' => [
        'land_prep' => 'Land preparation',
        'planting' => 'Planting',
        'growing' => 'Growing',
        'flowering' => 'Flowering',
        'ripening' => 'Ripening',
        'harvest' => 'Harvest',
    ],

    'week' => [
        'title' => 'Weather This Week',
        'see_full' => "See the week's weather",
        'good_legend' => 'Good for spraying and fertilizer: under :rain% chance of rain and wind under :wind km/h.',
        'today' => 'Today',
        'high_low' => 'high / low',
        'chance' => ':percent% chance of rain',
        'good_day' => 'Good day for farm work',
        'flag_heavy_rain' => 'Heavy rain',
        'flag_strong_wind' => 'Strong wind',
        'flag_high_heat' => 'Very hot',
        'rain_chart_title' => 'Rain expected each day (mm)',
        'rain_chart_label' => 'Bar chart of the rain expected each day this week, in millimeters, with a dashed line at the heavy rain level.',
        'temp_chart_title' => 'High and low temperature each day (°C)',
        'temp_chart_label' => 'Line chart of the highest and lowest temperature each day this week, in degrees Celsius.',
        'col_day' => 'Day',
        'col_rain' => 'Rain (mm)',
        'col_high' => 'High (°C)',
        'col_low' => 'Low (°C)',
        'foot' => 'Weather for Lian, Batangas. It can change, so check again before big farm work.',
        'empty' => 'The weather for the coming days is not available yet.',
        'takeaway_none' => 'We do not have rain amounts for the coming days.',
        'takeaway_dry' => 'No real rain is expected this week.',
        'takeaway_wet' => 'The wettest day is :day, with about :mm mm of rain.',
        'temp_takeaway' => 'The hottest day is :day, up to :temp°C.',
    ],

    'season' => [
        'title' => 'My Crop Season',
        'empty_title' => 'Add your planting date to see your crop calendar',
        'empty_reason' => 'You will see each crop stage, your expected harvest dates and reminders for farm work.',
        'add' => 'Add planting date',
        'open_calendar' => 'Open Calendar',
        'plot' => 'Plot :n',
        'day_of' => 'Day :day of about :total',
        'stage_line' => ':stage stage',
        'progress_label' => 'How far along the crop season is',
        'stages_label' => 'Crop stages',
        'state_done' => 'Done',
        'state_current' => 'Now',
        'state_upcoming' => 'Coming up',
        'harvest_dates' => 'Expected harvest',
        'next_task' => 'Next task',
        'edit' => 'Change this plan',
        'more' => '{1} And 1 more plot in your calendar.|[2,*] And :count more plots in your calendar.',
        'foot' => 'The stages are a usual rice calendar counted from your own planting and harvest dates. Follow the Municipal Agriculture Office advisories first.',
    ],

    'harvest' => [
        'title' => 'Expected Harvest',
        'not_ready' => 'Harvest estimate is not ready yet',
        'reason_service' => "The estimate service is not answering right now. This is not about your farm. Try again in a few minutes.",
        'reason_missing_planting' => 'Add your planting date.',
        'reason_missing_area' => 'Add your farm size.',
        'reason_missing_planting_area' => 'Add your planting date and farm size.',
        'add_area' => 'Add farm size',
        'needs_script' => 'Turn on JavaScript or reload the page to see your harvest estimate.',
        'see_more' => 'See more',
        'range' => 'Usually between :low and :high t/ha',
        'for_month' => 'Expected for :month',
        'expected' => 'Expected harvest',
        'meaning' => 'This is a best guess, not a real harvest record. It can change with the weather.',
        'reliability' => 'How sure we are: :level.',
        'total' => 'About :tons tons from your :area ha farm.',
        'total_missing' => 'Add your farm size to see how many tons you may harvest.',
        'compare_title' => 'Compared with the average',
        'above' => 'About :percent% more than the :scope (:avg t/ha).',
        'below' => 'About :percent% less than the :scope (:avg t/ha).',
        'avg_month' => 'Lian average for :month',
        'avg_all' => 'Lian average for all months',
        'yours' => 'Your expected harvest',
        'own_past' => 'Your own past harvests (:count)',
        'avg_foot' => 'The average is for all of Lian, from records for :from to :to. It is not for your barangay or your farm.',
        'source' => 'Estimate made by iClimate on :time.',
        'time_unknown' => 'a date we do not know',
    ],

    'rain' => [
        'title' => 'Rain Each Month',
        'summary_more' => ':month had :mm mm of rain, about :percent% more than usual.',
        'summary_less' => ':month had :mm mm of rain, about :percent% less than usual.',
        'summary_normal' => ':month had :mm mm of rain, about the usual amount.',
        'summary_no_normal' => ':month had :mm mm of rain. We do not have enough past years to say what is usual.',
        'empty' => 'We do not have monthly rain records yet.',
        'chart_title' => 'Rain each month in :year compared with usual (mm)',
        'chart_label' => 'Bar chart of the rain in each month of :year next to the usual rain for that month, in millimeters.',
        'col_month' => 'Month',
        'col_year' => 'Rain in :year (mm)',
        'col_usual' => 'Usual rain (mm)',
        'station' => 'Measured at Ambulong station (nearest PAGASA station, in Tanauan). Rain in your barangay may be different.',
        'older' => 'The latest records from Ambulong station are for :year.',
        'usual_note' => 'Usual rain is the average of the other years on record for that month. A month needs at least :years years of records.',
    ],

    'risk' => [
        'title' => 'Farm Risk in Your Barangay',
        'level' => ['low' => 'Low', 'moderate' => 'Moderate', 'high' => 'High', 'severe' => 'Severe'],
        'meaning' => [
            'low' => 'Conditions in your barangay look manageable for now.',
            'moderate' => 'Some conditions in your barangay need watching.',
            'high' => 'The weather in your barangay suggests a higher chance of crop trouble.',
            'severe' => 'The weather in your barangay suggests a high chance of crop trouble.',
        ],
        'concern' => [
            'flood' => 'Main worry: too much water (flood).',
            'drought' => 'Main worry: too little water (dry spell).',
            'typhoon' => 'Main worry: strong wind (typhoon).',
            'heat' => 'Main worry: heat.',
        ],
        'level_line' => ':level risk',
        'concern_line' => ':concern',
        'foot' => 'A planning guide, not an official hazard warning. Checked on :time.',
        'no_barangay' => 'Add your barangay to see farm risk for your area.',
        'no_rating' => 'We do not have a risk rating for your barangay yet.',
        'no_rating_reason' => 'It is made from the latest weather. Try again in a few minutes.',
        'map_summary' => 'Show risk map and barangay details',
        'map_note' => 'Farm risk is a planning guide only. It is not an official hazard warning.',
        'map_heading' => 'Lian Municipality Risk Map',
        'map_subheading' => 'All 19 barangays at a glance, using the weather right now. Tap a barangay for details.',
    ],

    'setup' => [
        'title' => 'Finish Setting Up Your Farm',
        'progress' => ':done of :total done',
        'barangay' => ['title' => 'Add your barangay', 'benefit' => 'to see farm risk and advisories for your area.', 'cta' => 'Update profile'],
        'farm_type' => ['title' => 'Choose your farm type', 'benefit' => 'so your expected harvest fits how your farm gets water (irrigated or rain only).', 'cta' => 'Update profile'],
        'farm_area' => ['title' => 'Add your farm size', 'benefit' => 'to see how many tons your farm may produce, not just tons per hectare.', 'cta' => 'Update profile'],
        'boundary' => ['title' => 'Draw your farm on the map', 'benefit' => 'so your farm size comes from your own map.', 'cta' => 'Map my farm'],
        'harvest' => ['title' => 'Record your harvest', 'benefit' => 'so your harvest is compared with your own past harvests.', 'cta' => 'Record harvest'],
    ],

    'advisories' => [
        'title' => 'Latest Farm Advisories',
        'all' => 'All advisories',
        'read' => 'Read more',
        'today' => 'Today',
        'yesterday' => 'Yesterday',
    ],

    'quick' => [
        'title' => 'Quick Actions',
        'advisories' => 'View Advisories',
        'calendar' => 'Open Calendar',
        'prediction' => 'Check Prediction',
        'notifications' => 'Notifications',
        'unread' => '{1} 1 unread notification|[2,*] :count unread notifications',
    ],

    'about' => [
        'title' => 'About this information',
        'weather' => ['label' => 'Weather now', 'text' => 'The latest reading for Lian, Batangas from an online weather service. It is not measured on your own farm.'],
        'coming' => ['label' => 'Weather coming', 'text' => 'What the weather service expects for the next days. It can change.'],
        'rain' => ['label' => 'Rain each month', 'text' => 'Rain recorded at Ambulong station, the nearest PAGASA station. Rain in your barangay may be different.'],
        'harvest' => ['label' => 'Expected harvest', 'text' => 'A best guess made by iClimate. It is not a real harvest record.'],
        'advisories' => ['label' => 'Advisories', 'text' => 'Written by the Municipal Agriculture Office.'],
    ],
];
