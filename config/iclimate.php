<?php

/*
|--------------------------------------------------------------------------
| iClimate application settings that used to be read with env() in app code
|--------------------------------------------------------------------------
|
| env() only works while the config is not cached. Once `php artisan
| config:cache` is on (production), env() outside config files returns null,
| so anything the app needs from .env is read here and used via config().
*/

return [
    /*
    | Chatbot Knowledge pages (topics, terms, synonyms, FAQs, blocked words). Off by default: the tab is not in any
    | sidebar and its pages send every role to their dashboard. Set CHATBOT_KNOWLEDGE_UI=true to bring the tab back
    | for IT. Only the pages are switched: the knowledge itself is always used by the Climora chatbot.
    */
    'chatbot_knowledge_ui' => (bool) env('CHATBOT_KNOWLEDGE_UI', false),

    /*
    | Field Reports pages (MAO/IT – Naicel, 2026-10-07). Off by default: the item is not in any sidebar or dashboard and
    | its addresses send MAO and IT to their own dashboard. Set FIELD_REPORTS_UI=true to bring the pages back. The table,
    | data, model, controller and migrations are kept.
    */
    'field_reports_ui' => (bool) env('FIELD_REPORTS_UI', false),

    /*
    | Manuscript – Naicel (2026-10-08): a farmer who signs up (web or mobile) waits for MAO approval (Farmer Approvals
    | page) before they can log in. Set ICLIMATE_FARMER_APPROVAL=false to make new farmer accounts active at once.
    */
    'farmer_approval' => (bool) env('ICLIMATE_FARMER_APPROVAL', true),

    /*
    | The Candidate Model and Season Calendar Retraining sections of the Model Evaluation page. Off by default
    | (hidden for now); the files, the report and python_scripts/retrain_yield_season.py are kept.
    */
    'model_evaluation_candidate_ui' => (bool) env('MODEL_EVALUATION_CANDIDATE_UI', false),

    /*
    | Fewest validated, separately measured rice records before Model Evaluation shows metrics as a result.
    | 30 is the reliability floor already used by python_scripts/evaluation_service.py; the researchers may
    | agree another number (docs/data-audit/phase-3-ml-dataset-methodology.md, decision 5).
    */
    'model_evaluation_min_observations' => (int) env('MODEL_EVALUATION_MIN_OBSERVATIONS', 30),

    /* Python used for the ML scripts. Empty: the app looks for the project's virtualenv. */
    'python_binary' => env('PYTHON_BINARY') ?: null,

    /* Password of the demo accounts made by DevelopmentDemoSeeder. No fallback: a password is never written in code. */
    'default_account_password' => env('ICLIMATE_DEFAULT_ACCOUNT_PASSWORD'),

    /*
    | First password of farmer accounts imported by FarmerMasterlistSeeder. Empty: every imported account gets
    | its own random password and IT sets a real one (Users > Edit) when the farmer first asks to sign in.
    */
    'farmer_account_password' => env('ICLIMATE_FARMER_ACCOUNT_PASSWORD'),

    /* Password given to it@gmail.com and mao@gmail.com by StaffLoginAccountSeeder. Empty: those accounts are not seeded. */
    'staff_account_password' => env('ICLIMATE_STAFF_ACCOUNT_PASSWORD'),

    /*
    | Panel revision 4.4 A: MAO personnel accounts. New accounts, and accounts the Municipal Agriculturist resets, get the
    | default password and must change it at their next sign-in. "Skip for now" is offered while
    | MAO_ALLOW_SKIP_PASSWORD_CHANGE is true (testing / thesis defense); set it to false for real use.
    | A new account's e-mail is firstnamelastname@<domain>, with 2, 3 … added when that address is taken.
    */
    'mao_default_password' => env('MAO_DEFAULT_PASSWORD'),
    'mao_email_domain' => env('MAO_EMAIL_DOMAIN', 'gmail.com'),
    'mao_allow_skip_password_change' => (bool) env('MAO_ALLOW_SKIP_PASSWORD_CHANGE', false),
    // Shared defaults that sign-in refuses to keep (the account must choose its own first). The first entry was the
    // seeded default of earlier releases and is already public in the repository history; add more with the env list.
    // Shared-password lockdown (audit 2026-11-18): sign-in flags accounts on a shared password, and the mobile app gets
    // 409 password_change_required instead of the profile. On in production; off only until the rollout is approved.
    'password_lockdown' => (bool) env('ICLIMATE_PASSWORD_LOCKDOWN', true),
    'known_default_passwords' => array_values(array_unique(array_filter(array_merge(
        ['Password@123'],
        array_map('trim', explode(',', (string) env('ICLIMATE_KNOWN_DEFAULT_PASSWORDS', ''))),
    )))),

    /*
    | Where iClimate operates. The single source is App\Support\LianLocation;
    | services.php reads the same constants. The timezone here is the BUSINESS
    | timezone (what "today" means). Stored timestamps stay UTC.
    */
    'location' => [
        'municipality' => \App\Support\LianLocation::MUNICIPALITY,
        'province' => \App\Support\LianLocation::PROVINCE,
        'country' => \App\Support\LianLocation::COUNTRY,
        'latitude' => \App\Support\LianLocation::LATITUDE,
        'longitude' => \App\Support\LianLocation::LONGITUDE,
    ],
    'timezone' => \App\Support\LianLocation::TIMEZONE,

    /*
    | Keep seeded sample rows (DatabaseSeeder / model factories) and impossible
    | climate rows out of dashboards and statistics, so they are never presented
    | as verified data. Nothing is deleted — see `php artisan iclimate:data-audit`
    | and `iclimate:purge-sample-data` for the reviewed clean-up. Set to false
    | only for automated tests that build their own factory data.
    */
    'exclude_sample_data' => (bool) env('ICLIMATE_EXCLUDE_SAMPLE_DATA', true),

];
