<?php

/*
|--------------------------------------------------------------------------
| Climora AI response-time settings
|--------------------------------------------------------------------------
|
| The chatbot answers from iClimate's own data first and only asks Groq when
| there is no direct answer. Everything that can wait on the network has a
| short, strict limit so a slow service never leaves the chat spinning.
*/

return [
    /* Whole-request budget (seconds). Groq is skipped once too little is left. */
    'response_budget_seconds' => (float) env('AI_RESPONSE_BUDGET', 10),

    'groq' => [
        /* Interactive chat limits. No retries: a retry doubles the wait. */
        'timeout' => (float) env('AI_GROQ_TIMEOUT', 4),
        'connect_timeout' => (float) env('AI_GROQ_CONNECT_TIMEOUT', 2),

        /* After a timeout / 429 / 5xx, skip Groq for this long so following messages are not delayed too. */
        'cooldown_seconds' => (int) env('AI_GROQ_COOLDOWN', 30),

        /*
        | false: answers that iClimate can already write from its own data
        | (weather, forecast, ...) are returned directly. true: Groq re-words
        | them, which is slower.
        */
        'phrase_system_answers' => (bool) env('AI_GROQ_PHRASING', false),
    ],

    /* The chatbot reuses the last good weather snapshot if it is younger than this (the dashboard cache is 1 minute). */
    'weather_max_age_minutes' => (int) env('AI_WEATHER_MAX_AGE', 10),

    /* Live weather lookup limit (seconds per provider) when the cache is cold. */
    'weather_timeout' => (float) env('AI_WEATHER_TIMEOUT', 3),

    /*
    | How long the monthly weather forest result is kept. Training takes ~20 s and the
    | cache key already includes the dataset version, so a long TTL is safe.
    */
    'forest_cache_hours' => (int) env('AI_FOREST_CACHE_HOURS', 24),

    /* How long a knowledge-base match (or "no match") is remembered. */
    'knowledge_cache_seconds' => (int) env('AI_KNOWLEDGE_CACHE', 600),

    /* Only this many recent chats are read for context. */
    'history_messages' => (int) env('AI_HISTORY_MESSAGES', 3),
];
