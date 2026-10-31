<?php

return [
    'hero' => [
        'title' => "Weather and planting advice for Lian's rice farmers",
        'description' => 'iClimate brings local forecasts, planting guidance and rice records together for decisions in the field.',
        'button' => 'See what iClimate provides',
    ],
    'about' => [
        'title' => 'About iClimate',
        'description' => 'iClimate combines climate data with the Municipal Agriculture Office’s rice production records so farmers can understand the conditions behind each planting decision.',
        'mission' => 'The Municipal Agriculture Office and iClimate team work to make useful, local climate information easier to understand and act on.',
        'sources' => 'Data sources',
        'source_items' => ['PAGASA advisories', 'Open-Meteo forecasts', 'Stored climate records', 'MAO rice production records'],
    ],
    'features' => [
        'title' => 'What you can do',
        'items' => [
            ['title' => 'Weather analysis', 'text' => 'Check rainfall, temperature, humidity and wind for Lian.'],
            ['title' => 'Rice production', 'text' => 'Review local records and yield forecasts for planning.'],
            ['title' => 'Planting advisory', 'text' => 'Use season, rainfall and soil conditions to plan planting.'],
            ['title' => 'Climate monitoring', 'text' => 'Follow long-term trends and unusual patterns that affect farming.'],
            ['title' => 'Heat map', 'text' => 'See flood, drought, typhoon and heat risk by barangay.'],
            ['title' => 'Reports', 'text' => 'Download seasonal reports and yield forecasts.'],
        ],
    ],
    'numbers' => [
        'title' => 'Numbers from Lian',
        'lead' => 'Current counts from iClimate records.',
        'labels' => ['farmers' => 'Registered farmers', 'farms' => 'Farms on record', 'barangays' => 'Barangays with farms', 'varieties' => 'Rice varieties listed'],
    ],
    'how' => [
        'title' => 'How it works',
        'lead' => 'Use iClimate through one wet season, from setting up your farm to knowing when to harvest.',
        'months' => ['Jun', 'Jul', 'Aug', 'Sep', 'Oct'],
        'steps' => [
            ['title' => 'Add your farm', 'text' => 'Choose your barangay, farm type and the rice variety you planted.'],
            ['title' => 'Check your barangay forecast', 'text' => 'See the rain, heat and typhoon outlook for your barangay.'],
            ['title' => "Follow the MAO's planting advisory", 'text' => 'Know when to plant and what field conditions to watch for.'],
            ['title' => 'Know your harvest date', 'text' => 'See the estimate based on your variety’s days to maturity.'],
        ],
    ],
    'roles' => [
        'title' => 'Who uses it',
        'items' => [
            ['title' => 'Farmer', 'text' => 'Check conditions, planting advice and seasonal information for your farm.', 'items' => ['Climate monitoring', 'Planting guidance', 'Weather risk alerts', 'Seasonal reports']],
            ['title' => 'MAO Staff', 'text' => 'Manage records, compare climate and yield, and prepare reports for the municipality.', 'items' => ['Production monitoring', 'Climate analysis', 'Report generation', 'Record validation']],
            ['title' => 'IT Personnel', 'text' => 'Keep iClimate reliable, secure and available to the people who use it.', 'items' => ['Access support', 'Activity checks', 'Record upkeep', 'System reliability']],
        ],
    ],
    'questions' => [
        'title' => 'Questions and contact',
        'lead' => 'Find an answer or send a message to the Municipal Agriculture Office.',
        'items' => [
            ['q' => 'Which rice varieties and harvest dates are listed?', 'a' => 'The system lists active rice varieties and uses local production records to show the expected harvest period for a farm or season.'],
            ['q' => 'Can a farmers’ association use iClimate?', 'a' => 'Ask the Municipal Agriculture Office about access for an association or a group of farmers.'],
            ['q' => 'What should I do if I forget my password?', 'a' => 'Use the Forgot password link on the sign-in card. If you still need help, contact the MAO during office hours.'],
        ],
    ],
    'footer' => [
        'description' => 'Weather, planting advice and rice records for Lian, Batangas.',
        'copyright' => '© 2026 iClimate · Capstone project of BatStateU ARASOF-Nasugbu with MAO Lian',
    ],
];
