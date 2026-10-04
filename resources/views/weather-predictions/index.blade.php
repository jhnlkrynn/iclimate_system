@php
    $isTechnical = $isTechnical ?? false;
    $isFarmer = $isFarmer ?? false;
    $weatherReady = ! empty($result) && (bool) ($result['ready'] ?? false);
    $predictions = $weatherReady ? (array) ($result['predictions'] ?? []) : [];
    $targetLabel = $targetMonth->format('F Y');
    $targetDate = $targetDate ?? $targetMonth;
    $modelInput = $mlResult['model_input'] ?? ($defaultModelInput ?? []);

    $rainfall = isset($predictions['rainfall']) ? (float) $predictions['rainfall'] : null;
    $forecastCondition = \App\Services\Weather\WeatherFeedbackService::conditionForRainfall($rainfall);
    $tempAvg = isset($predictions['temperature']) ? (float) $predictions['temperature'] : null;
    $humidity = isset($predictions['humidity']) ? (float) $predictions['humidity'] : null;
    $farmTone = match (\App\Services\Prediction\WeatherLabeler::fromRainfall((float) ($rainfall ?? 0))) {
        'Heavy Rain' => ['High Farm Risk', 'Watch for flooding and drainage problems.', 'tone-red'],
        'Dry' => ['Watch Closely', 'Rainfall may be low for rainfed fields.', 'tone-amber'],
        default => ['Good Farm Conditions', 'Rainfall is within a workable range.', 'tone-green'],
    };
    if ($tempAvg !== null && $tempAvg > 32) {
        $farmTone = ['High Farm Risk', 'Heat may stress the crop.', 'tone-red'];
    }
    $weatherReliability = \App\Support\PredictionWording::reliability(data_get($result, 'confidence.value'));
    $weatherRecommendation = $result['farming_recommendation'] ?? (($result['insights'] ?? [])[0] ?? null);

    $liveWeatherAvailable = is_array($liveWeather ?? null) && ! empty($liveWeather);
    $liveWeatherLocation = $liveWeather['location'] ?? 'Lian, Batangas';
    $liveWeatherCondition = $liveWeather['current_condition'] ?? 'Condition unavailable';
    $liveWeatherFetchedAt = ! empty($liveWeather['fetched_at']) ? \Illuminate\Support\Carbon::parse($liveWeather['fetched_at'])->timezone('Asia/Manila') : null;

    $runType = $analyticsRun?->type;
    $runStatus = $analyticsRun?->status;
    $runIsYield = $runType === 'rice_yield_prediction';
    $isRunLoading = in_array($runStatus, [\App\Models\AnalyticsRun::STATUS_PENDING, \App\Models\AnalyticsRun::STATUS_PROCESSING], true);
    $requestedTab = request()->query('tab') === 'weather' ? 'weather-forecast' : request()->query('tab');
    $oldPredictionType = old('prediction_type');
    $activeWeatherTab = (in_array($requestedTab, ['weather-forecast', 'rice-yield'], true)
        ? $requestedTab
        : (($runIsYield || $oldPredictionType === 'rice_yield' || ! empty($mlResult)) ? 'rice-yield' : 'weather-forecast')) ?? 'weather-forecast';
    $weatherHasError = (! empty($errorInfo) && ! $runIsYield && $oldPredictionType !== 'rice_yield')
        || ($errors->any() && $oldPredictionType !== 'rice_yield' && ! $errors->has('accuracy') && ! $errors->has('observed_condition') && ! $errors->has('forecast_date'));
    $yieldHasError = (! empty($errorInfo) && $runIsYield)
        || ($errors->any() && $oldPredictionType === 'rice_yield');
    $oldArea = old('area', $mlResult['farm_area_hectares'] ?? null);
    $weatherFailure = $errorInfo ?? ($errors->any() ? ['title' => 'Please Check Your Entry', 'detail' => $errors->first(), 'technical' => null] : null);
@endphp

<x-app-layout>
    <style>
        .wp-hero { position: relative; overflow: hidden; border-radius: 32px; padding: 1.35rem; margin-bottom: 1.25rem; color: #1f2a24; background: radial-gradient(circle at 86% 14%, rgba(82,183,136,.14), transparent 30%), linear-gradient(145deg, #f6f9f7 0%, #e7f0ea 100%); border: 1px solid #e3ece6; box-shadow: 0 1rem 2.3rem rgba(31,42,36,.08); }
        .wp-hero::before { content: ""; position: absolute; inset: 0; background: radial-gradient(rgba(31,42,36,.07) 1px, transparent 1px); background-size: 28px 28px; mask-image: linear-gradient(90deg, rgba(0,0,0,.8), transparent 86%); }
        .wp-hero .text-white-50 { color: #4a5c52 !important; }
        .wp-panel::before, .wp-metric::before { content: ""; position: absolute; left: 0; right: 0; top: 0; height: 5px; background: var(--accent, #52b788); }
        .wp-hero > * { position: relative; z-index: 1; }
        .wp-eyebrow { font-size: .875rem; font-weight: 900; text-transform: uppercase; letter-spacing: .06em; color: #2d6a4f; line-height: 1.35; }
        .wp-tabs { display: inline-flex; gap: .35rem; padding: .35rem; margin-bottom: 1.25rem; border: 1px solid #e3ece6; border-radius: 999px; background: #f3f6f4; }
        .wp-tab-button { border: 0; border-radius: 999px; padding: .65rem 1rem; color: #4a5c52; background: transparent; font-weight: 900; }
        .wp-tab-button.active { color: #0d1f18; background: #52b788; }
        .wp-tab-button:focus-visible { outline: 3px solid rgba(45,106,79,.28); outline-offset: 2px; }
        .wp-tab-panel[hidden] { display: none !important; }
        .wp-state { min-height: 180px; display: grid; place-items: center; padding: 1.25rem; text-align: center; }
        .wp-state-copy { max-width: 640px; }
        .wp-state-icon { width: 3rem; height: 3rem; display: inline-grid; place-items: center; margin-bottom: .75rem; border-radius: 999px; color: #2d6a4f; background: #d8f3dc; font-size: 1.25rem; font-weight: 900; }
        .wp-panel, .wp-metric, .wp-input-card, .wp-result-card { position: relative; overflow: hidden; border: 1px solid rgba(212,237,218,.98); border-radius: 18px; background: linear-gradient(145deg, rgba(255,255,255,.96), rgba(247,251,248,.96)); box-shadow: 0 .9rem 2rem rgba(13,31,24,.07); }
        .wp-panel-header { padding: 1rem; border-bottom: 1px solid rgba(212,237,218,.9); background: linear-gradient(90deg, #fff, #f0f7f4); }
        .wp-panel-body, .wp-metric-body { padding: 1rem; }
        .wp-metric { min-height: 168px; }
        .wp-label { color: #4f665b; font-size: .875rem; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; line-height: 1.35; }
        .wp-value { margin-top: .35rem; color: #0d1f18; font-size: clamp(1.8rem, 3vw, 2.45rem); font-weight: 900; line-height: 1; }
        .wp-unit { color: #5a7a64; font-size: .86rem; font-weight: 700; }
        .wp-card-value { color: #0d1f18; font-weight: 900; line-height: 1.35; }
        .wp-note { color: #5a7a64; font-size: .86rem; margin-top: .7rem; }
        .wp-source-line { color: rgba(255,255,255,.72); font-size: .875rem; font-weight: 800; margin-top: .65rem; line-height: 1.4; }
        .wp-guide-flow { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .65rem; margin-top: 1rem; }
        .wp-guide-step { border: 1px solid rgba(255,255,255,.12); border-radius: 8px; background: rgba(255,255,255,.04); padding: .72rem; }
        .wp-guide-step strong { display: block; color: #fff; font-size: .875rem; line-height: 1.3; }
        .wp-guide-step span { display: block; color: rgba(255,255,255,.72); font-size: .875rem; line-height: 1.45; margin-top: .28rem; }
        .wp-status { display: inline-flex; align-items: center; gap: .4rem; border-radius: 999px; padding: .38rem .7rem; margin-top: .85rem; font-size: .8125rem; font-weight: 900; color: var(--status-color, #2d6a4f); background: var(--status-bg, #d8f3dc); line-height: 1.25; }
        .wp-status::before { content: ""; width: .45rem; height: .45rem; border-radius: 999px; background: currentColor; }
        .tone-green { --accent: #52b788; --status-color: #2d6a4f; --status-bg: #d8f3dc; }
        .tone-blue { --accent: #2d6a4f; --status-color: #2d6a4f; --status-bg: #d8f3dc; }
        .tone-amber { --accent: #ffd166; --status-color: #8a5a00; --status-bg: #fff4cf; }
        .tone-red { --accent: #d85b45; --status-color: #9f3728; --status-bg: #fde8e2; }
        .wp-input-card { padding: 1rem; height: 100%; }
        .wp-input-row { display: flex; justify-content: space-between; gap: .75rem; align-items: flex-start; margin-bottom: .7rem; }
        .wp-input-card .form-control { width: 100%; max-width: none; font-weight: 800; text-align: left; }
        .wp-input-card .input-group { flex-wrap: nowrap; }
        .wp-area-group .form-control { min-width: 180px; }
        .wp-area-group .input-group-text { color: #4f665b; font-weight: 800; }
        .wp-help { color: #4f665b; font-size: .875rem; margin-top: .45rem; line-height: 1.45; }
        .wp-advanced-panel { overflow: visible; }
        .wp-advanced-panel .wp-panel-body { overflow: visible; }
        #advancedWeatherInputs.collapse:not(.show) { display: none; }
        #advancedWeatherInputs.collapse.show,
        #advancedWeatherInputs.collapsing {
            display: block !important;
            height: auto !important;
            overflow: visible !important;
            transition: none !important;
        }
        #advancedWeatherInputs .form-label { color: rgba(255,255,255,.82); }
        #advancedWeatherInputs .form-control,
        #advancedWeatherInputs .form-select {
            background: rgba(255,255,255,.05);
            border-color: rgba(255,255,255,.18);
            color: #fff;
        }
        #advancedWeatherInputs .form-select option { color: #0d1f18; background: #fff; }
        .wp-result-card { padding: 1rem; }
        .wp-advisory { border: 1px solid #d4edda; border-radius: 8px; background: #fff; padding: 1rem; }
        .wp-feature-pill { display: inline-flex; border-radius: 999px; padding: .38rem .7rem; background: #d8f3dc; color: #1f6f4a; font-size: .8125rem; font-weight: 800; margin: .18rem; line-height: 1.25; }
        .wp-score-bar { height: 10px; border-radius: 999px; overflow: hidden; background: #dbe8dd; }
        .wp-score-bar > span { display: block; height: 100%; width: min(var(--score, 50%), 100%); background: linear-gradient(90deg, #ffd166, #52b788); }
        .ds-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .85rem; }
        .ds-card { border: 1px solid #d4edda; border-radius: 8px; background: #fff; padding: 1rem; min-height: 138px; }
        .ds-card.primary { background: linear-gradient(145deg, #fff, #f0f7f4); border-left: 5px solid var(--accent, #52b788); }
        .ds-title { color: #4f665b; font-size: .875rem; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; line-height: 1.35; }
        .ds-value { color: #0d1f18; font-size: 1.05rem; font-weight: 900; line-height: 1.28; margin-top: .45rem; }
        .ds-note { color: #5a7a64; font-size: .86rem; line-height: 1.4; margin-top: .45rem; }
        .ds-pill { display: inline-flex; align-items: center; border-radius: 999px; padding: .42rem .72rem; font-size: .84rem; font-weight: 900; background: var(--status-bg, #d8f3dc); color: var(--status-color, #2d6a4f); }
        .ds-notifications { display: grid; gap: .45rem; padding: 0; margin: .55rem 0 0; list-style: none; }
        .ds-notifications li { border: 1px solid #d4edda; border-radius: 8px; background: #f7fbf8; padding: .62rem .72rem; font-weight: 700; }
        .smart-list { display: grid; gap: .55rem; margin: 0; padding: 0; list-style: none; }
        .smart-list li { border: 1px solid #d4edda; border-radius: 8px; background: #fff; padding: .72rem .8rem; color: #1b2b23; font-weight: 700; }
        .stress-chip { display: grid; gap: .25rem; border: 1px solid #d4edda; border-left: 5px solid var(--accent, #52b788); border-radius: 8px; background: #fff; padding: .8rem; }
        .stress-chip.high { --accent: #d85b45; }
        .stress-chip.moderate { --accent: #ffd166; }
        /* Monthly / weekly prediction panel: explicit dark text so nothing inherits the layout's white body colour. */
        .wp-outlook { margin-bottom: 1rem; padding: 1.1rem; border: 1px solid #b9dcc6; border-radius: 14px; background: rgba(247,251,248,.96); color: #0d1f18; box-shadow: 0 6px 18px rgba(13,31,24,.07); }
        .wp-outlook h3, .wp-outlook h4, .wp-outlook h5, .wp-outlook p, .wp-outlook dt, .wp-outlook dd, .wp-outlook li { color: inherit; }
        .wp-outlook .text-muted, .wp-outlook small { color: #3d5247 !important; }
        .wp-outlook-head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: .35rem 1rem; margin-bottom: .9rem; }
        .wp-outlook-head h3 { font-size: 1.4rem; font-weight: 800; color: #0d3b2a; }
        .wp-outlook-period { font-size: 1rem; color: #26382f; font-weight: 600; }
        .wp-outlook-block { padding: .9rem; margin-bottom: .85rem; border: 1px solid #cfe4d6; border-radius: 12px; background: #fff; }
        .wp-outlook-tag { display: inline-block; margin-bottom: .6rem; padding: .25rem .8rem; border-radius: 999px; font-size: .82rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
        .wp-outlook-tag.is-model { background: #fff0c2; color: #6b4400; }
        .wp-outlook-tag.is-daily { background: #d8f3dc; color: #1b4d38; }
        .wp-outlook-tag.is-history { background: #dde8f6; color: #243f6b; }
        .wp-outlook-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: .6rem; }
        .wp-outlook-row { display: flex; flex-direction: column; gap: .1rem; padding: .6rem .8rem; border-radius: 10px; background: #eef6f0; border: 1px solid #d9eadf; min-width: 0; }
        .wp-outlook-k { font-size: .95rem; font-weight: 700; color: #26382f; }
        .wp-outlook-v { font-size: 1.35rem; font-weight: 800; color: #0d1f18; line-height: 1.25; overflow-wrap: anywhere; }
        .wp-outlook-v small { font-size: .85rem; font-weight: 600; color: #3d5247 !important; }
        .wp-outlook-row.is-unavailable { background: #f5f6f5; }
        .wp-outlook-row.is-unavailable .wp-outlook-v { font-size: 1rem; font-weight: 600; color: #4a5a52; }
        .wp-outlook-subtitle { margin: 1.1rem 0 .6rem; font-size: 1.15rem; font-weight: 700; letter-spacing: -0.01em; color: #0d3b2a; }
        .wp-outlook-cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .8rem; }
        .wp-ocard { display: flex; flex-direction: column; gap: .35rem; padding: 1rem; border: 1px solid #b9dcc6; border-radius: 14px; background: linear-gradient(180deg, #ffffff 0%, #f1f8f3 100%); box-shadow: 0 4px 12px rgba(13,31,24,.06); min-width: 0; }
        .wp-ocard-title { margin: 0 0 .2rem; font-size: 1rem; font-weight: 800; letter-spacing: .02em; text-transform: uppercase; color: #1f5a46; }
        .wp-ocard-status { align-self: flex-start; padding: .25rem .8rem; border-radius: 999px; font-size: 1rem; font-weight: 800; background: #d8f3dc; color: #14503a; }
        .wp-ocard-status.is-above-normal { background: #fff0c2; color: #6b4400; }
        .wp-ocard-status.is-below-normal { background: #dde8f6; color: #243f6b; }
        .wp-ocard-value { font-size: 2.1rem; font-weight: 800; line-height: 1.1; color: #0d1f18; }
        .wp-ocard-caption { margin: 0; font-size: 1rem; line-height: 1.4; color: #26382f; }
        .wp-ocard-basis { margin: auto 0 0; padding-top: .5rem; border-top: 1px solid #d9eadf; font-size: .9rem; color: #33483d; }
        .wp-ocard-none { font-size: 1.1rem; font-weight: 700; color: #4a5a52; }
        .wp-season { display: flex; align-items: center; gap: .7rem; padding: .6rem .75rem; border-radius: 10px; border: 1px solid #d9eadf; background: #f7faf8; }
        .wp-season.is-active { background: #d8f3dc; border-color: #52b788; }
        .wp-season-icon { font-size: 1.7rem; line-height: 1; }
        .wp-season div { display: flex; flex-direction: column; min-width: 0; }
        .wp-season strong { font-size: 1.05rem; color: #0d1f18; }
        .wp-season span:not(.wp-season-icon) { font-size: 1rem; color: #26382f; }
        .wp-season em { margin-left: auto; font-size: .8rem; font-style: normal; font-weight: 800; color: #14503a; text-align: right; }
        .wp-panel .text-muted, .wp-panel-body .text-muted { color: #3d5247 !important; }
        .wp-outlook-fineprint { margin: .7rem 0 0; font-size: .9rem; color: #3d5247; }
        .wp-outlook-meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: .6rem 1.2rem; margin: 1rem 0 0; }
        .wp-outlook-meta dt { font-size: .82rem; text-transform: uppercase; letter-spacing: .05em; font-weight: 800; color: #33483d; }
        .wp-outlook-meta dd { margin: 0; font-size: .98rem; color: #0d1f18; overflow-wrap: anywhere; }
        .wp-outlook-missing, .wp-outlook-pagasa { margin: .7rem 0 0; font-size: .9rem; color: #3d5247; }
        .wp-outlook-empty { font-size: 1.1rem; font-weight: 700; color: #26382f; }
        .wp-outlook-weeks { display: grid; grid-template-columns: repeat(auto-fit, minmax(290px, 1fr)); gap: .8rem; margin-bottom: .85rem; }
        .wp-outlook-week { padding: .9rem; border: 1px solid #cfe4d6; border-radius: 12px; background: #fff; min-width: 0; color: #0d1f18; }
        .wp-outlook-week.is-empty { background: #f5f6f5; }
        .wp-outlook-week-head { display: flex; justify-content: space-between; align-items: baseline; gap: .5rem; margin-bottom: .2rem; font-size: 1.15rem; letter-spacing: -0.01em; color: #0d1f18; }
        .wp-outlook-week-head span { font-weight: 800; color: #1f5a46; }
        .wp-outlook-grid.is-compact { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        @media (max-width: 991.98px) { .wp-outlook-cards { grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); } }
        @media (max-width: 575.98px) { .wp-outlook { padding: .8rem; } .wp-outlook-head h3 { font-size: 1.2rem; } .wp-outlook-cards, .wp-outlook-grid.is-compact { grid-template-columns: 1fr; } .wp-ocard-value { font-size: 1.8rem; } }
        .wp-calendar-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; }
        .wp-calendar-bar { display: flex; align-items: center; gap: .6rem; margin-bottom: .75rem; padding: .5rem .75rem; border: 1px solid #d4edda; border-radius: 12px; background: rgba(255,255,255,.85); }
        .wp-calendar-arrow { display: grid; place-items: center; width: 2.4rem; height: 2.4rem; border-radius: 50%; border: 1px solid #cfe4d6; background: #fff; color: #1f5a46; font-size: 1.6rem; line-height: 1; text-decoration: none; font-weight: 700; }
        .wp-calendar-arrow:hover, .wp-calendar-arrow:focus-visible { background: #e8f5ec; border-color: #52b788; color: #0d3b2a; }
        .wp-calendar-today { margin-left: auto; padding: .35rem .9rem; border-radius: 999px; border: 1px solid #2d6a4f; color: #1f5a46; background: #fff; font-weight: 700; font-size: .9rem; text-decoration: none; }
        .wp-calendar-today:hover { background: #2d6a4f; color: #fff; }
        .wp-calendar-daynum { display: inline-flex; align-items: center; justify-content: center; min-width: 2rem; height: 2rem; padding: 0 .35rem; border-radius: 999px; font-size: 1.25rem; font-weight: 800; line-height: 1; color: #0d1f18; }
        .wp-calendar-dow { display: none; margin-right: .4rem; font-size: .85rem; font-weight: 700; color: #4f665b; }
        .wp-calendar-day.is-today .wp-calendar-daynum { background: #2d6a4f; color: #fff; }
        .wp-calendar-day.outside-month .wp-calendar-daynum { color: #5f6f66; font-weight: 700; }
        .wp-calendar-month { min-width: 9.5ch; text-align: center; font-size: 1.6rem; font-weight: 800; letter-spacing: -.01em; color: #1f5a46; }
        .wp-calendar-day.no-forecast { background: #f7faf8; border-style: dashed; border-top-style: solid; }
        .wp-calendar-day.no-forecast:hover { transform: none; box-shadow: none; }
        .wp-calendar-day.has-forecast { background: #fff; box-shadow: 0 2px 8px rgba(13,31,24,.06); }
        .wp-calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: .55rem; }
        .wp-calendar-week { display: contents; }
        .wp-calendar-weekday { color: #1f3a2d; padding: .35rem; font-size: 1rem; font-weight: 800; text-align: center; }
        .wp-calendar-day { color: #0d1f18; min-height: 150px; border: 1px solid #d4edda; border-top: 6px solid #cbd5cf; border-radius: 10px; background: #fff; padding: .75rem; font-size: 1rem; line-height: 1.45; transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease; }
        .wp-calendar-day:hover { transform: translateY(-1px); border-color: #52b788; box-shadow: 0 6px 16px rgba(13,31,24,.08); }
        .wp-calendar-day.outside-month { background: #eef1ef; border-color: #d9e0dc; color: #55665d; }
        .wp-calendar-day.outside-month .wp-calendar-values, .wp-calendar-day.outside-month .wp-calendar-condition { opacity: 1; color: #55665d; }
        .wp-calendar-day.is-today { outline: 3px solid rgba(45,106,79,.28); outline-offset: 1px; }
        .wp-calendar-day.risk-low { border-top-color: #52b788; }
        .wp-calendar-day.risk-moderate { border-top-color: #ffd166; }
        .wp-calendar-day.risk-high, .wp-calendar-day.risk-severe { border-top-color: #d85b45; }
        .wp-calendar-date { display: flex; justify-content: space-between; align-items: flex-start; gap: .5rem; margin-bottom: .45rem; }
        .wp-calendar-risk { display: inline-flex; border-radius: 999px; padding: .25rem .55rem; background: #edf2ee; color: #33483d; font-size: .8rem; font-weight: 800; }
        .risk-low .wp-calendar-risk { background: #d8f3dc; color: #14503a; }
        .risk-moderate .wp-calendar-risk { background: #fff0c2; color: #6b4400; }
        .risk-high .wp-calendar-risk, .risk-severe .wp-calendar-risk { background: #fde1da; color: #8a2a1c; }
        .wp-calendar-condition { display: flex; align-items: center; gap: .35rem; color: #0d1f18; font-size: .94rem; font-weight: 800; line-height: 1.3; }
        .wp-calendar-icon { width: 20px; height: 20px; flex: 0 0 auto; }
        .wp-calendar-values { display: grid; gap: .18rem; margin-top: .35rem; color: #1f3327; font-size: .875rem; line-height: 1.35; overflow-wrap: anywhere; }
        .wp-calendar-values strong { font-weight: 700; color: #0d1f18; }
        .wp-calendar-unavailable { color: #6b7c72; font-size: .93rem; }
        /* Severity legend: same family as the section headings, but smaller. */
        .wp-risk-legend { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1.6rem; margin: 0 0 .75rem; padding: .5rem .9rem; border: 1px solid #cfe4d6; border-radius: 10px; background: rgba(255,255,255,.9); }
        .wp-risk-legend-item { display: inline-flex; align-items: center; gap: .55rem; font-size: 1rem; font-weight: 700; letter-spacing: -0.01em; color: #0d1f18; }
        .wp-risk-swatch { width: 16px; height: 16px; border-radius: 50%; border: 1px solid rgba(13,31,24,.35); flex: none; }
        .wp-calendar-loading { min-height: 220px; place-items: center; text-align: center; }
        .wp-calendar-loading:not([hidden]) { display: grid; }
        .wp-monthly-prediction { background: #f4f9f5; border: 1px solid #cfe4d6; border-radius: 12px; padding: 1rem 1.15rem; margin-bottom: 1.1rem; }
        .wp-monthly-prediction-title { font-size: 1.1rem; font-weight: 900; color: #0d1f18; display: flex; align-items: center; gap: .5rem; }
        .wp-monthly-prediction-badge { display: inline-block; padding: .18rem .6rem; border-radius: 999px; background: #2d6a4f; color: #fff; font-size: .72rem; font-weight: 800; letter-spacing: .03em; text-transform: uppercase; }
        .wp-monthly-prediction-note { margin: .4rem 0 .7rem; color: #4f665b; font-size: .92rem; line-height: 1.5; }
        .wp-monthly-prediction-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: .5rem .9rem; font-size: .95rem; color: #33483d; font-weight: 700; }
        @media (max-width: 1199.98px) { .ds-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 991.98px) { .wp-guide-flow { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 767.98px) {
            .ds-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .wp-guide-flow { grid-template-columns: 1fr; gap: .5rem; margin-top: .75rem; }
            .wp-input-row { display: grid; }
            .wp-input-card .form-control { max-width: none; text-align: left; }
            .wp-hero { padding: 1rem; border-radius: 20px; margin-bottom: .85rem; }
            .wp-tabs { margin-bottom: .85rem; padding: .28rem; }
            .wp-tab-button { padding: .55rem .8rem; font-size: .86rem; }
            .wp-panel-header, .wp-panel-body { padding: .75rem; }
            .wp-metric-body { padding: .75rem; }
            .wp-metric { min-height: auto; }
            .wp-value { font-size: 1.7rem; margin-top: .25rem; }
            .wp-guide-step { padding: .6rem; }
            .ds-card { min-height: auto; padding: .75rem; }
            .wp-page .row.g-4 { --bs-gutter-y: .75rem; }
            .wp-page .mb-4 { margin-bottom: 1rem !important; }
            .wp-result-card, .wp-input-card { padding: .85rem; }
            .wp-outlook { margin-bottom: 1rem; padding: 1rem; border: 1px solid #d4edda; border-radius: 12px; background: rgba(247,251,248,.9); }
        .wp-outlook-head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: .35rem 1rem; margin-bottom: .75rem; }
        .wp-outlook-period { font-size: .9rem; color: #4f665b; font-weight: 600; }
        .wp-outlook-block { padding: .75rem; margin-bottom: .75rem; border: 1px solid #e3ece6; border-radius: 10px; background: #fff; }
        .wp-outlook-tag { display: inline-block; margin-bottom: .5rem; padding: .15rem .65rem; border-radius: 999px; font-size: .75rem; font-weight: 800; letter-spacing: .03em; text-transform: uppercase; }
        .wp-outlook-tag.is-model { background: #fff4cf; color: #8a5a00; }
        .wp-outlook-tag.is-daily { background: #d8f3dc; color: #2d6a4f; }
        .wp-outlook-tag.is-history { background: #e4ecf7; color: #2f4f7f; }
        .wp-outlook-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: .5rem; }
        .wp-outlook-row { display: flex; flex-direction: column; padding: .45rem .65rem; border-radius: 8px; background: #f3f8f4; min-width: 0; }
        .wp-outlook-k { font-size: .8rem; font-weight: 700; color: #4f665b; }
        .wp-outlook-v { font-size: 1.15rem; font-weight: 800; color: #0d1f18; overflow-wrap: anywhere; }
        .wp-outlook-v small { font-size: .75rem; font-weight: 600; color: #4f665b; }
        .wp-outlook-notes { margin-top: .6rem; display: grid; gap: .25rem; font-size: .92rem; }
        .wp-outlook-meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: .4rem 1rem; margin: .75rem 0 0; font-size: .88rem; }
        .wp-outlook-meta dt { font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: #4f665b; }
        .wp-outlook-meta dd { margin: 0; overflow-wrap: anywhere; }
        .wp-outlook-missing, .wp-outlook-pagasa { margin: .6rem 0 0; font-size: .82rem; color: #5b6b64; }
        .wp-outlook-empty { font-weight: 700; }
        .wp-outlook-weeks { display: grid; grid-template-columns: repeat(auto-fit, minmax(270px, 1fr)); gap: .75rem; margin-bottom: .75rem; }
        .wp-outlook-week { padding: .75rem; border: 1px solid #e3ece6; border-radius: 10px; background: #fff; min-width: 0; }
        .wp-outlook-week.is-empty { background: #f7f8f7; }
        .wp-outlook-week-head { display: flex; justify-content: space-between; align-items: baseline; gap: .5rem; margin-bottom: .15rem; color: #0d1f18; }
        .wp-outlook-week-head span { font-weight: 700; color: #2d6a4f; }
        .wp-outlook-grid.is-compact { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        @media (max-width: 420px) { .wp-outlook-grid.is-compact { grid-template-columns: 1fr; } }
        .wp-outlook-row.is-unavailable { background: #f7f8f7; }
        .wp-outlook-row.is-unavailable .wp-outlook-v { font-size: .9rem; font-weight: 600; color: #6b7c72; }
        .wp-outlook-pagasa { margin-bottom: 0; }
        .wp-calendar-toolbar { align-items: stretch; }
            .wp-calendar-dow { display: inline; }
            .wp-calendar-daynum { justify-content: flex-start; }
            .wp-calendar-month { font-size: 1.35rem; }
            .wp-calendar-day.no-forecast { padding: .5rem .9rem; }
            .wp-calendar-grid { display: block; }
            .wp-calendar-weekday { display: none; }
            .wp-calendar-week { display: block; }
            .wp-calendar-day { min-height: 0; margin-bottom: .7rem; padding: .9rem; font-size: 1rem; }
            .wp-calendar-day.outside-month { display: none; }
        }
        @media (max-width: 380px) {
            .ds-grid { grid-template-columns: 1fr; }
        }

        .wp-source-line { color: #6b7c72; }
        .wp-guide-step { border-color: #e3ece6; background: #fff; }
        .wp-guide-step strong { color: #1f2a24; }
        .wp-guide-step span { color: #4a5c52; }
        #advancedWeatherInputs .form-label { color: #4a5c52; }
        #advancedWeatherInputs .form-control,
        #advancedWeatherInputs .form-select {
            background: #fff;
            border-color: #d4edda;
            color: #1f2a24;
        }

        /* -- plain, uniform page (matches farmer dashboard, no tone variation) -- */
        .wp-metric::before, .wp-panel::before { background: #52b788 !important; }
        .wp-metric .wp-status { color: #74c69d !important; background: rgba(82,183,136,.16) !important; }
        .ds-card.primary { border-left-color: #52b788 !important; }
        .stress-chip { border-left-color: #52b788 !important; }

        /* Product UI restraint pass */
        .wp-hero {
            border-radius: 16px;
            background: #f5f7f4;
            box-shadow: 0 1px 2px rgba(13,31,24,.08);
        }
        .wp-hero::before { opacity: .24; }
        .wp-eyebrow,
        .wp-label,
        .ds-title {
            font-family: 'Inter', sans-serif;
            text-transform: none;
            letter-spacing: 0;
        }
        .wp-tabs {
            border-radius: 10px;
            padding: .25rem;
        }
        .wp-tab-button {
            border-radius: 8px;
            font-weight: 750;
        }
        .wp-panel,
        .wp-metric,
        .wp-input-card,
        .wp-result-card {
            border: 1px solid #c9d7ce;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(13,31,24,.08);
        }
        .wp-panel-header { background: #f5f7f4; color: #1f2a24; }
        .wp-panel-header h1,
        .wp-panel-header h2,
        .wp-panel-header h3,
        .wp-panel-header h4,
        .wp-panel-header h5,
        .wp-panel-header h6 { color: #1f2a24; }
        .wp-status,
        .wp-feature-pill,
        .ds-pill {
            font-family: 'Inter', sans-serif;
            font-weight: 700;
        }

        .wp-tab-button { min-height: 44px; }
        .wp-tab-button.active { box-shadow: inset 0 -3px 0 #2d6a4f; }
        .btn-check:focus-visible + .btn { outline: 3px solid rgba(45,106,79,.35); outline-offset: 2px; }
        details.wp-panel > summary { list-style: none; }
        details.wp-panel > summary::-webkit-details-marker { display: none; }
        details.wp-panel[open] > summary { border-bottom: 1px solid rgba(212,237,218,.9); }
    
        /* ====================================================================
           Weather Forecast typography system
           One font family, one colour palette, one size scale and one spacing
           scale for the whole page. Only presentation: no data or logic here.
           ==================================================================== */
        .wp-page {
            --wp-font: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            /* colour palette (all pass 4.5:1 on the light cards) */
            --wp-ink: #0d1f18;        /* primary text */
            --wp-ink-2: #26382f;      /* secondary text */
            --wp-ink-3: #3d5247;      /* muted text: still clearly readable */
            --wp-green: #0d3b2a;      /* headings and key values */
            /* size scale */
            --wp-fs-title: 1.5rem;    /* 24px  page / calendar title */
            --wp-fs-h1: 1.4rem;       /* 22px  main section heading */
            --wp-fs-h2: 1.15rem;      /* 18px  section heading */
            --wp-fs-card: 0.9375rem;  /* 15px  card titles */
            --wp-fs-label: 0.875rem;  /* 14px  metric labels */
            --wp-fs-value: 1.375rem;  /* 22px  important values */
            --wp-fs-body: 0.9375rem;  /* 15px  supporting text */
            --wp-fs-meta: 0.875rem;   /* 14px  metadata values */
            --wp-fs-meta-label: 0.8125rem; /* 13px metadata labels */
            /* spacing scale */
            --wp-s1: .5rem; --wp-s2: .75rem; --wp-s3: 1rem; --wp-s4: 1.25rem;
        }
        .wp-page, .wp-page * { font-family: var(--wp-font); }
        .wp-page h1, .wp-page h2, .wp-page h3, .wp-page h4, .wp-page h5, .wp-page h6 { font-weight: 700; letter-spacing: 0; text-wrap: balance; }
        .wp-page .wp-panel h2, .wp-page .wp-panel h3, .wp-page .wp-panel h4, .wp-page .wp-panel h5 { color: var(--wp-green); }
        .wp-page .wp-panel .text-muted, .wp-page .wp-panel small, .wp-page .wp-note, .wp-page .ds-note, .wp-page .wp-unit,
        .wp-page .wp-source-line { color: var(--wp-ink-3) !important; }
        .wp-page .wp-note, .wp-page .ds-note, .wp-page .wp-unit { font-size: var(--wp-fs-meta); line-height: 1.5; }
        .wp-page .wp-label { font-weight: 700; color: var(--wp-ink-2); }

        /* Title + intro lines */
        .wp-page .wp-calendar-toolbar h2 { font-size: var(--wp-fs-title); font-weight: 800; margin-bottom: .2rem !important; }
        .wp-page .wp-calendar-toolbar .text-muted { font-size: var(--wp-fs-body); }
        .wp-page [data-weather-calendar] > .mb-2 { font-size: var(--wp-fs-body); color: var(--wp-ink-2) !important; margin-bottom: var(--wp-s3) !important; }

        /* Monthly Weather Prediction */
        .wp-page .wp-outlook { padding: var(--wp-s4); margin-bottom: var(--wp-s4); }
        .wp-page .wp-outlook-head { margin-bottom: var(--wp-s3); gap: var(--wp-s1) var(--wp-s3); }
        .wp-page .wp-outlook-head h3 { font-size: var(--wp-fs-h1); font-weight: 800; color: var(--wp-green); line-height: 1.25; }
        .wp-page .wp-outlook-period { font-size: var(--wp-fs-body); font-weight: 600; color: var(--wp-ink-2); }
        .wp-page .wp-outlook-block { padding: var(--wp-s3); margin-bottom: var(--wp-s3); }
        .wp-page .wp-outlook-tag { font-size: var(--wp-fs-meta-label); font-weight: 700; letter-spacing: .05em; padding: .3rem .85rem; margin-bottom: var(--wp-s2); }
        .wp-page .wp-outlook-grid { gap: var(--wp-s2); }
        .wp-page .wp-outlook-row { min-height: 4.75rem; justify-content: space-between; gap: .25rem; padding: var(--wp-s2) var(--wp-s3); }
        .wp-page .wp-outlook-k { font-size: var(--wp-fs-label); font-weight: 600; color: var(--wp-ink-2); line-height: 1.3; }
        .wp-page .wp-outlook-v { font-size: var(--wp-fs-value); font-weight: 700; color: var(--wp-ink); line-height: 1.25; }
        .wp-page .wp-outlook-v small { font-size: var(--wp-fs-label); font-weight: 600; color: var(--wp-ink-3) !important; }
        .wp-page .wp-outlook-row.is-unavailable .wp-outlook-v { font-size: var(--wp-fs-body); font-weight: 600; color: var(--wp-ink-3); }

        /* Seasonal & Outlook cards: identical structure */
        .wp-page .wp-outlook-subtitle { font-size: var(--wp-fs-h2); font-weight: 700; color: var(--wp-green); margin: var(--wp-s4) 0 var(--wp-s2); letter-spacing: 0; }
        .wp-page .wp-outlook-cards { gap: var(--wp-s2); align-items: stretch; }
        .wp-page .wp-ocard { padding: var(--wp-s3); gap: var(--wp-s1); }
        .wp-page .wp-ocard-title { font-size: var(--wp-fs-card); font-weight: 700; letter-spacing: .05em; color: #1f5a46; margin: 0; }
        .wp-page .wp-ocard-status { font-size: var(--wp-fs-card); font-weight: 700; padding: .25rem .8rem; }
        .wp-page .wp-ocard-value { font-size: var(--wp-fs-value); font-weight: 800; color: var(--wp-green); line-height: 1.2; }
        .wp-page .wp-ocard-caption { font-size: var(--wp-fs-body); font-weight: 400; line-height: 1.5; color: var(--wp-ink-2); }
        .wp-page .wp-ocard-basis { font-size: var(--wp-fs-meta); font-weight: 400; color: var(--wp-ink-2); padding-top: var(--wp-s2); }
        .wp-page .wp-ocard-basis strong { font-weight: 700; color: var(--wp-ink); }
        .wp-page .wp-ocard-none { font-size: var(--wp-fs-body); font-weight: 600; color: var(--wp-ink-3); }
        .wp-page .wp-season { padding: var(--wp-s2); gap: var(--wp-s2); }
        .wp-page .wp-season strong { font-size: var(--wp-fs-card); font-weight: 700; color: var(--wp-ink); }
        .wp-page .wp-season span:not(.wp-season-icon) { font-size: var(--wp-fs-body); color: var(--wp-ink-2); }
        .wp-page .wp-season em { font-size: var(--wp-fs-meta-label); font-weight: 600; color: #1f5a46; }
        .wp-page .wp-outlook-fineprint, .wp-page .wp-outlook-missing, .wp-page .wp-outlook-pagasa { font-size: var(--wp-fs-meta); color: var(--wp-ink-3); line-height: 1.5; }

        /* Metadata: Forecast Period / Type / Source / Last Updated / Uncertainty */
        .wp-page .wp-outlook-meta { gap: var(--wp-s2) var(--wp-s4); margin-top: var(--wp-s3); padding-top: var(--wp-s3); border-top: 1px solid #d9eadf; }
        .wp-page .wp-outlook-meta dt { font-size: var(--wp-fs-meta-label); font-weight: 700; letter-spacing: .05em; color: var(--wp-ink-2); margin-bottom: .15rem; }
        .wp-page .wp-outlook-meta dd { font-size: var(--wp-fs-meta); font-weight: 400; color: var(--wp-ink); line-height: 1.5; }

        /* Weekly fallback cards */
        .wp-page .wp-outlook-week { padding: var(--wp-s3); }
        .wp-page .wp-outlook-week-head { font-size: var(--wp-fs-h2); font-weight: 700; letter-spacing: 0; color: var(--wp-ink); }
        .wp-page .wp-outlook-week-head span { font-size: var(--wp-fs-card); font-weight: 700; color: #1f5a46; }
        .wp-page .wp-outlook-empty { font-size: var(--wp-fs-body); font-weight: 600; color: var(--wp-ink-2); }

        /* Month bar, legend, calendar */
        .wp-page .wp-calendar-month { font-size: var(--wp-fs-title); font-weight: 800; letter-spacing: 0; color: var(--wp-green); }
        .wp-page .wp-calendar-today { font-size: var(--wp-fs-label); font-weight: 700; }
        .wp-page .wp-risk-legend { margin-bottom: var(--wp-s2); }
        .wp-page .wp-risk-legend-item { font-size: 1rem; font-weight: 600; letter-spacing: 0; color: var(--wp-ink); }
        .wp-page .wp-calendar-weekday { font-size: var(--wp-fs-body); font-weight: 700; color: var(--wp-ink); }
        .wp-page .wp-calendar-daynum { font-size: 1.0625rem; font-weight: 700; }
        .wp-page .wp-calendar-condition { font-size: var(--wp-fs-body); font-weight: 700; color: var(--wp-ink); }
        .wp-page .wp-calendar-values { font-size: var(--wp-fs-meta); font-weight: 400; color: var(--wp-ink-2); }
        .wp-page .wp-calendar-values strong { font-weight: 600; color: var(--wp-ink); }
        .wp-page .wp-calendar-risk { font-size: var(--wp-fs-meta-label); font-weight: 700; }
        .wp-page .wp-calendar-day.outside-month, .wp-page .wp-calendar-day.outside-month .wp-calendar-values, .wp-page .wp-calendar-day.outside-month .wp-calendar-condition { color: #4c5d54; }
        .wp-page .wp-calendar-day.outside-month .wp-calendar-daynum { color: #56675e; font-weight: 600; }
        .wp-page [data-calendar-content] > .small { font-size: var(--wp-fs-meta); color: var(--wp-ink-3) !important; }

        @media (max-width: 575.98px) {
            .wp-page { --wp-fs-title: 1.3rem; --wp-fs-h1: 1.2rem; --wp-fs-value: 1.25rem; }
            .wp-page .wp-outlook { padding: var(--wp-s3); }
            .wp-page .wp-outlook-row { min-height: 0; }
        }
    </style>

    @if($isFarmer)
    {{-- Farmers get the farmer design system (farmer-predictions.css). The confirm dialog below stays outside .fad:
         .fad is a size container and would trap a fixed-position dialog. --}}
    <div class="fad fpred">
        <header class="fad-hero">
            <div class="fad-hero-text">
                <span class="fad-kicker">Rice Yield and Weather Predictions</span>
                <h1>Predictions</h1>
                <p>Expected rice yield and expected weather for your area. These are estimates, not guarantees.</p>
            </div>
        </header>
    @endif
    {{-- IT accounts ($isTechnical) get the IT dashboard design system: .mfd / .itd parts plus it-pages.css. --}}
    <div @class(['wp-page', 'mfd itd itp' => $isTechnical])>
    @if($isTechnical)
    <x-it.page-header kicker="Rice Yield and Weather Forecasts" title="Predictions" lede="Expected rice yield and expected weather for Lian. These are forecasts, not guarantees." />
    @elseif(! $isFarmer)
    <section class="wp-hero">
        <div>
            <div class="wp-eyebrow mb-2">Farm planning</div>
            <h1 class="h2 fw-bold mb-2">Predictions</h1>
            <p class="mb-0 text-white-50" style="max-width: 720px;">Expected rice yield and expected weather for your area. These are forecasts, not guarantees.</p>
        </div>
    </section>
    @endif

    <div class="wp-tabs" role="tablist" aria-label="Prediction types" data-initial-weather-tab="{{ $activeWeatherTab }}">
        <button id="rice-yield-tab" class="wp-tab-button {{ $activeWeatherTab === 'rice-yield' ? 'active' : '' }}" type="button" role="tab" aria-controls="rice-yield-panel" aria-selected="{{ $activeWeatherTab === 'rice-yield' ? 'true' : 'false' }}" tabindex="{{ $activeWeatherTab === 'rice-yield' ? '0' : '-1' }}" data-weather-tab="rice-yield">Rice Yield</button>
        <button id="weather-forecast-tab" class="wp-tab-button {{ $activeWeatherTab === 'weather-forecast' ? 'active' : '' }}" type="button" role="tab" aria-controls="weather-forecast-panel" aria-selected="{{ $activeWeatherTab === 'weather-forecast' ? 'true' : 'false' }}" tabindex="{{ $activeWeatherTab === 'weather-forecast' ? '0' : '-1' }}" data-weather-tab="weather-forecast">Weather</button>
    </div>

    {{-- ===================== RICE YIELD TAB ===================== --}}
    <div id="rice-yield-panel" class="wp-tab-panel ic-tab-panel" role="tabpanel" aria-labelledby="rice-yield-tab" data-weather-panel="rice-yield" @if($activeWeatherTab !== 'rice-yield') hidden @endif>
        <div class="wp-panel mb-4">
            <div class="wp-panel-header"><h2 class="h5 fw-bold mb-1">Check Expected Rice Yield</h2></div>
            <div class="wp-panel-body">
                <form method="POST" action="{{ route('rice-yield-predictions.predict') }}" id="yieldPredictionForm" data-confirm-yield>
                    @csrf
                    <input type="hidden" name="prediction_type" value="rice_yield">
                    @php $selectedFarmType = old('farm_type', auth()->user()?->farmerProfile?->farm_type ?? \App\Models\FarmerProfile::FARM_TYPE_RAINFED); @endphp
                    <div class="row g-3 align-items-end">
                        <div class="col-md-6 col-xl-3">
                            <div class="wp-input-card">
                                <label class="wp-label" for="prediction_date">Date to Check</label>
                                <input id="prediction_date" type="date" name="prediction_date" class="form-control mt-2 text-start" value="{{ $targetDate->format('Y-m-d') }}" required>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="wp-input-card">
                                <label class="wp-label" for="area">Farm Size (hectares)</label>
                                <div class="input-group mt-2 wp-area-group">
                                    <input id="area" type="number" name="area" class="form-control text-start @error('area') is-invalid @enderror" step="0.01" min="0.01" max="10000" placeholder="Example: 2.5" value="{{ $oldArea }}" required>
                                    <span class="input-group-text">hectares</span>
                                    @error('area')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="wp-input-card">
                                <label class="wp-label" for="farm_type">Type of Farm</label>
                                <select id="farm_type" name="farm_type" class="form-select mt-2">
                                    <option value="Rainfed" @selected($selectedFarmType === 'Rainfed')>Rainfed</option>
                                    <option value="Irrigated" @selected($selectedFarmType === 'Irrigated')>Irrigated</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <button id="yieldPredictButton" class="btn btn-primary fw-bold px-4 w-100" type="submit" data-loading-text="Working on it...">Estimate My Harvest</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @if($isRunLoading && $runIsYield)
        <div class="wp-panel wp-state mb-4" data-analytics-run="{{ route('analytics-runs.show', $analyticsRun) }}">
            <div class="wp-state-copy">
                <div class="spinner-border text-success mb-3" role="status"><span class="visually-hidden">Loading</span></div>
                <h2 class="h5 fw-bold">Estimating your harvest</h2>
                <p class="text-muted mb-0" data-analytics-message>This page will update on its own.</p>
            </div>
        </div>
        @elseif($yieldHasError)
        @php $yieldFailure = $errorInfo ?? ['title' => 'Please Check Your Entry', 'detail' => $errors->first(), 'technical' => null]; @endphp
        <div class="alert alert-warning shadow-sm mb-4" role="alert">
            <strong>{{ $yieldFailure['title'] }}</strong>
            <div class="mt-1">{{ $yieldFailure['detail'] }}</div>
            @if($isTechnical && ! empty($yieldFailure['technical']))
                <div class="small mt-2 text-muted">Technical detail (IT only): {{ $yieldFailure['technical'] }}</div>
            @endif
        </div>
        @elseif(! empty($yieldSummary))
        @php
            $ys = $yieldSummary;
            $yieldReliability = \App\Support\PredictionWording::reliability($mlResult['api_confidence'] ?? null);
        @endphp
        <div class="wp-panel mb-4 {{ $ys['status']['tone'] }}">
            <div class="wp-panel-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h2 class="h5 fw-bold mb-0">Expected Rice Yield</h2>
                <span class="wp-status" style="margin-top:0">{{ $ys['status']['label'] }}</span>
            </div>
            <div class="wp-panel-body">
                <div class="row g-3">
                    <div class="col-md-4"><div class="wp-advisory h-100"><div class="wp-label">Expected Rice Yield</div><div class="wp-value">{{ number_format($ys['expected'], 2) }}</div><div class="wp-unit">tons/ha for {{ $ys['period'] }}</div></div></div>
                    <div class="col-md-4"><div class="wp-advisory h-100"><div class="wp-label">Previous Yield</div>@if($ys['previous'] !== null)<div class="wp-card-value">{{ number_format($ys['previous'], 2) }} tons/ha</div><div class="ds-note">{{ $ys['previous_year'] }} record</div>@else<div class="wp-card-value">Not available</div><div class="ds-note">No {{ $ys['previous_year'] }} record on file</div>@endif</div></div>
                    <div class="col-md-4"><div class="wp-advisory h-100"><div class="wp-label">Difference</div><div class="wp-card-value">{{ $ys['status']['difference'] ?? 'Not available' }}</div></div></div>
                </div>
                @if($ys['recommendation'])
                    <div class="wp-advisory mt-3"><div class="wp-label">What to do</div><div class="wp-card-value">{{ $ys['recommendation'] }}</div></div>
                @endif
                @unless($isFarmer)
                    {{-- MAO / staff context: still short --}}
                    <div class="row g-3 mt-1">
                        @if($ys['barangay'])<div class="col-md-4"><div class="wp-advisory h-100"><div class="wp-label">Barangay</div><div class="wp-card-value">{{ $ys['barangay'] }}</div></div></div>@endif
                        @if($ys['risk'])<div class="col-md-4"><div class="wp-advisory h-100"><div class="wp-label">Farm Condition</div><div class="wp-card-value">{{ $ys['risk'] }}</div></div></div>@endif
                        @if($ys['irrigation'])<div class="col-md-4"><div class="wp-advisory h-100"><div class="wp-label">Watering</div><div class="wp-card-value">{{ $ys['irrigation'] }}</div></div></div>@endif
                    </div>
                @endunless
                <details class="ic-collapse mt-3">
                    <summary><span class="ic-collapse-title">View Details</span></summary>
                    <div class="row g-2 mt-2">
                        <div class="col-md-4"><div class="wp-advisory h-100"><div class="wp-label mb-1">Farm Size Used</div><div class="wp-card-value">{{ number_format((float) ($mlResult['farm_area_hectares'] ?? 0), 2) }} hectares</div></div></div>
                        <div class="col-md-4"><div class="wp-advisory h-100"><div class="wp-label mb-1">Estimated Total Harvest</div><div class="wp-card-value">{{ number_format((float) ($mlResult['estimated_total_production_tons'] ?? 0), 2) }} tons</div></div></div>
                        @if($yieldReliability)<div class="col-md-4"><div class="wp-advisory h-100"><div class="wp-label mb-1">Prediction Reliability</div><div class="wp-card-value">{{ $yieldReliability }}</div><div class="ds-note">A rough guide only, not a guarantee.</div></div></div>@endif
                        @if(isset($mlResult['condition_score']))<div class="col-md-4"><div class="wp-advisory h-100"><div class="wp-label mb-1">Farm Condition Score</div><div class="wp-card-value">{{ number_format((float) $mlResult['condition_score']) }} / 100</div><div class="ds-note">Higher is better.</div></div></div>@endif
                    </div>
                </details>
                @if($isTechnical)
                    @php
                        $modelFeatureRows = collect($mlResult['features'] ?? [])
                            ->map(function ($value, $modelKey) {
                                $entry = \App\Services\Prediction\FeatureCatalog::byModelKey((string) $modelKey, \App\Services\Prediction\FeatureCatalog::SCHEMA_DEPLOYED);
                                return ['value' => $value, 'label' => $entry['label'] ?? $modelKey, 'unit' => $entry['unit'] ?? ''];
                            })->values();
                    @endphp
                    <details class="ic-collapse mt-3">
                        <summary><span class="ic-collapse-title">Technical Details (IT only)</span></summary>
                        <div class="small text-muted mt-2">Source: {{ $mlResult['source'] ?? $mlResult['source_name'] ?? 'iClimate Rice Yield Prediction Model' }}@if(! empty($mlResult['api_confidence'])) | Estimated confidence {{ $mlResult['api_confidence'] }}%@endif</div>
                        <div class="small text-muted">{{ $mlResult['source_credit'] ?? '' }}</div>
                        <div class="row g-2 mt-1">
                            @foreach($modelFeatureRows as $row)
                                <div class="col-md-6 col-lg-4"><div class="wp-advisory h-100"><div class="wp-label mb-1">{{ $row['label'] }}@if($row['unit']) ({{ $row['unit'] }})@endif</div><div class="wp-card-value">{{ is_numeric($row['value']) ? number_format((float) $row['value'], 2) : ($row['value'] ?? 'N/A') }}</div></div></div>
                            @endforeach
                        </div>
                    </details>
                @endif
            </div>
        </div>
        @else
        <div class="wp-panel wp-state mb-4">
            <div class="wp-state-copy">
                <div class="wp-state-icon" aria-hidden="true">R</div>
                <h2 class="h5 fw-bold">Ready to estimate rice yield</h2>
                <p class="text-muted mb-0">Enter the date and your farm size above to see your expected harvest.</p>
            </div>
        </div>
        @endif
    </div>

    {{-- ===================== WEATHER TAB ===================== --}}
    <div id="weather-forecast-panel" class="wp-tab-panel ic-tab-panel" role="tabpanel" aria-labelledby="weather-forecast-tab" data-weather-panel="weather-forecast" @if($activeWeatherTab !== 'weather-forecast') hidden @endif>
        <div class="wp-panel mb-4">
            <div class="wp-panel-header"><h2 class="h5 fw-bold mb-1">Check Expected Weather</h2></div>
            <div class="wp-panel-body">
                <form method="POST" action="{{ route('weather-predictions.predict') }}" class="row g-3 align-items-end" data-loading="true">
                    @csrf
                    <input type="hidden" name="prediction_type" value="weather">
                    <div class="col-md-6 col-xl-4">
                        <label for="target_date" class="wp-label">Date to Check</label>
                        <input id="target_date" name="prediction_date" type="date" class="form-control mt-2" value="{{ $targetDate->format('Y-m-d') }}" required>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <button class="btn btn-primary fw-bold px-4 w-100" type="submit" data-loading-text="Working on it...">Check Weather</button>
                    </div>
                </form>
            </div>
        </div>

        @if (session('weather_check_status'))
            <div class="alert alert-success" role="status">{{ session('weather_check_status') }}</div>
        @endif
        @if (session('weather_check_error'))
            <div class="alert alert-warning" role="status">{{ session('weather_check_error') }}</div>
        @endif

        @if ($isRunLoading && ! $runIsYield)
            <div class="wp-panel wp-state mb-4" data-analytics-run="{{ route('analytics-runs.show', $analyticsRun) }}">
                <div class="wp-state-copy">
                    <div class="spinner-border text-success mb-3" role="status"><span class="visually-hidden">Loading</span></div>
                    <h2 class="h5 fw-bold">Checking the weather</h2>
                    <p class="text-muted mb-0" data-analytics-message>This page will update on its own.</p>
                </div>
            </div>
        @elseif ($weatherHasError && $weatherFailure)
            <div class="alert alert-warning shadow-sm mb-4" role="alert">
                <strong>{{ $weatherFailure['title'] }}</strong>
                <div class="mt-1">{{ $weatherFailure['detail'] }}</div>
                @if($isTechnical && ! empty($weatherFailure['technical']))
                    <div class="small mt-2 text-muted">Technical detail (IT only): {{ $weatherFailure['technical'] }}</div>
                @endif
            </div>
        @elseif (! empty($result) && ! ($result['ready'] ?? false))
            @php $notEnough = \App\Support\PredictionWording::friendlyFailure(null, true); @endphp
            <div class="alert alert-warning shadow-sm mb-4" role="status">
                <strong>{{ $notEnough['title'] }}</strong>
                <div class="mt-1">{{ $notEnough['detail'] }}</div>
                @if($isTechnical)<div class="small mt-2 text-muted">Technical detail (IT only): {{ $result['message'] ?? '' }} Monthly samples: {{ $result['months_available'] ?? 0 }}.</div>@endif
            </div>
        @elseif ($weatherReady)
            <div class="wp-panel mb-4 {{ $farmTone[2] }}">
                <div class="wp-panel-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div><h2 class="h5 fw-bold mb-1">Expected Weather</h2><div class="small text-muted">For {{ $targetLabel }}@if($latestPredictionSavedAt) | Prediction saved {{ $latestPredictionSavedAt->timezone('Asia/Manila')->format('M j, Y g:i A') }}@endif</div></div>
                    <span class="wp-status" style="margin-top:0">{{ $farmTone[0] }}</span>
                </div>
                <div class="wp-panel-body">
                    <div class="row g-3">
                        <div class="col-6 col-lg-3"><div class="wp-advisory h-100"><div class="wp-label">Expected Condition</div><div class="wp-card-value">{{ $forecastCondition ?? 'Not available' }}</div></div></div>
                        <div class="col-6 col-lg-3"><div class="wp-advisory h-100"><div class="wp-label">Expected Rainfall</div><div class="wp-card-value">{{ $rainfall !== null ? number_format($rainfall, 1).' mm' : 'Not available' }}</div></div></div>
                        <div class="col-6 col-lg-3"><div class="wp-advisory h-100"><div class="wp-label">Temperature</div><div class="wp-card-value">{{ $tempAvg !== null ? number_format($tempAvg, 1).'°C' : 'Not available' }}</div></div></div>
                        <div class="col-6 col-lg-3"><div class="wp-advisory h-100"><div class="wp-label">Humidity</div><div class="wp-card-value">{{ $humidity !== null ? number_format($humidity, 0).'%' : 'Not available' }}</div></div></div>
                    </div>
                    <div class="wp-advisory mt-3">
                        <div class="wp-label">Farm Condition</div>
                        <div class="wp-card-value">{{ $farmTone[0] }}</div>
                        <div class="wp-note mt-1">{{ $farmTone[1] }}@if($weatherRecommendation) {{ $weatherRecommendation }}@endif</div>
                    </div>
                    @if($weatherReliability)<div class="small text-muted mt-2">Prediction Reliability: <strong>{{ $weatherReliability }}</strong>. This is a forecast, not a guarantee.</div>@endif
                    @if($isTechnical)
                        <details class="ic-collapse mt-3">
                            <summary><span class="ic-collapse-title">Technical Details (IT only)</span></summary>
                            <div class="small text-muted mt-2">Source: {{ $result['source_name'] ?? 'iClimate monthly forecast model' }}. {{ $result['source_note'] ?? '' }}</div>
                            <div class="small text-muted">Model message: {{ $result['message'] ?? '' }}</div>
                            @if(isset($predictions['wind_speed']))<div class="small text-muted">Model wind value (unit not confirmed): {{ number_format((float) $predictions['wind_speed'], 2) }}</div>@endif
                        </details>
                    @endif
                </div>
            </div>
        @endif

        <div class="wp-panel mb-4">
            <div class="wp-panel-header d-flex flex-column flex-lg-row justify-content-between gap-2">
                <div>
                    @if(! empty($savedWeatherLocation['barangay']) && ! $savedWeatherLocation['is_municipal_preview'])
                        <h2 class="h5 fw-bold mb-1">Weather Now in {{ $savedWeatherLocation['barangay'] }} <span class="text-muted fw-normal">(your barangay)</span></h2>
                    @else
                        <h2 class="h5 fw-bold mb-1">Weather Now in Lian, Batangas</h2>
                    @endif
                    <div class="small text-muted">{{ $liveWeatherLocation }}</div>
                </div>
                @if($liveWeatherAvailable)
                    <div class="small text-muted text-lg-end">Updated {{ $liveWeatherFetchedAt?->format('M j, Y g:i A') ?? 'recently' }}</div>
                @endif
            </div>
            <div class="wp-panel-body">
                @if($liveWeatherAvailable)
                    @if(! empty($liveWeather['is_cached_fallback']))
                        <div class="alert alert-warning py-2" role="status">Showing the most recent saved weather update.</div>
                    @endif
                    <div class="row g-3">
                        <div class="col-6 col-xl"><div class="wp-result-card h-100" data-weather-mood-root><div class="wp-label">Condition</div><div class="wp-card-value" data-weather-mood-source>{{ $liveWeatherCondition }}</div></div></div>
                        <div class="col-6 col-xl"><div class="wp-result-card h-100"><div class="wp-label">Rainfall</div><div class="wp-card-value">{{ is_numeric($liveWeather['current_rainfall_mm'] ?? null) ? number_format((float) $liveWeather['current_rainfall_mm'], 2).' mm' : '—' }}</div></div></div>
                        <div class="col-6 col-xl"><div class="wp-result-card h-100"><div class="wp-label">Temperature</div><div class="wp-card-value">{{ is_numeric($liveWeather['current_temperature_c'] ?? null) ? number_format((float) $liveWeather['current_temperature_c'], 1).'°C' : '—' }}</div></div></div>
                        <div class="col-6 col-xl"><div class="wp-result-card h-100"><div class="wp-label">Humidity</div><div class="wp-card-value">{{ is_numeric($liveWeather['humidity_percent'] ?? null) ? number_format((float) $liveWeather['humidity_percent']).'%' : '—' }}</div></div></div>
                        <div class="col-6 col-xl"><div class="wp-result-card h-100"><div class="wp-label">Wind</div><div class="wp-card-value">{{ is_numeric($liveWeather['wind_speed_kmh'] ?? null) ? number_format((float) $liveWeather['wind_speed_kmh'], 1).' km/h' : '—' }}</div></div></div>
                    </div>
                    @if($lianWeather)
                        @php
                            $lianNow = $lianWeather['current'] ?? [];
                        @endphp
                        <div class="mt-3 pt-3 border-top">
                            <div class="wp-label mb-2">Lian-wide (town center) &middot; same reading as your Dashboard</div>
                            <div class="row g-3">
                                <div class="col-6 col-xl"><div class="wp-result-card h-100" data-weather-mood-root><div class="wp-label">Condition</div><div class="wp-card-value" data-weather-mood-source>{{ $lianNow['condition'] ?? '—' }}</div></div></div>
                                <div class="col-6 col-xl"><div class="wp-result-card h-100"><div class="wp-label">Rainfall</div><div class="wp-card-value">{{ is_numeric($lianNow['precipitation'] ?? null) ? number_format((float) $lianNow['precipitation'], 2).' mm' : '—' }}</div></div></div>
                                <div class="col-6 col-xl"><div class="wp-result-card h-100"><div class="wp-label">Temperature</div><div class="wp-card-value">{{ is_numeric($lianNow['temperature'] ?? null) ? number_format((float) $lianNow['temperature'], 1).'°C' : '—' }}</div></div></div>
                                <div class="col-6 col-xl"><div class="wp-result-card h-100"><div class="wp-label">Humidity</div><div class="wp-card-value">{{ is_numeric($lianNow['humidity'] ?? null) ? number_format((float) $lianNow['humidity']).'%' : '—' }}</div></div></div>
                                <div class="col-6 col-xl"><div class="wp-result-card h-100"><div class="wp-label">Wind</div><div class="wp-card-value">{{ is_numeric($lianNow['wind_speed'] ?? null) ? number_format((float) $lianNow['wind_speed'], 1).' km/h' : '—' }}</div></div></div>
                            </div>
                            <div class="small text-muted mt-2">Your barangay's reading is for its own location, so it can differ slightly from the Lian-wide reading. Both were updated at the same time.</div>
                        </div>
                    @endif
                    @if($isTechnical)<div class="small text-muted mt-3">Source (IT only): {{ $liveWeather['source_name'] ?? 'Open-Meteo Forecast API' }}. Responses are cached for {{ $liveWeatherCacheMinutes }} minutes.</div>@endif
                @else
                    <div class="alert alert-warning mb-0" role="alert"><strong>Forecast Not Available Yet.</strong> {{ $liveWeatherError ?? 'Please try again later.' }}</div>
                @endif
            </div>
        </div>

        {{-- Weather Check: farmers only, only after the forecast period has ended --}}
        @if($isFarmer)
            <div class="wp-panel mb-4" id="weather-check">
                <div class="wp-panel-header"><h2 class="h5 fw-bold mb-1">Weather Check</h2></div>
                <div class="wp-panel-body">
                    @if(! $weatherCheck)
                        <p class="text-muted mb-0">No past forecast to check right now. Thank you for helping us improve.</p>
                    @elseif($weatherCheckBarangayIssue)
                        <p class="mb-0">{{ \App\Services\Weather\WeatherFeedbackService::message($weatherCheckBarangayIssue) }}</p>
                    @else
                        <form method="POST" action="{{ route('weather-feedback.store') }}" data-weather-check>
                            @csrf
                            <input type="hidden" name="forecast_date" value="{{ $weatherCheck['date'] }}">
                            <fieldset>
                                <legend class="h6 fw-bold">Was this weather forecast accurate in your area?</legend>
                                <p class="small text-muted mb-2">Forecast for {{ $weatherCheck['label'] }}: {{ $weatherCheck['condition'] }}</p>
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    @foreach(\App\Models\WeatherForecastFeedback::accuracyLabels() as $value => $label)
                                        <div>
                                            <input class="btn-check" type="radio" name="accuracy" id="wc-accuracy-{{ $value }}" value="{{ $value }}" @checked(old('accuracy') === $value) required>
                                            <label class="btn btn-outline-success fw-bold" for="wc-accuracy-{{ $value }}">{{ $label }}</label>
                                        </div>
                                    @endforeach
                                </div>
                                @error('accuracy')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                            </fieldset>
                            <fieldset data-weather-check-observed hidden>
                                <legend class="h6 fw-bold">What weather did you experience? (optional)</legend>
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    @foreach(\App\Models\WeatherForecastFeedback::OBSERVED_CONDITIONS as $i => $condition)
                                        <div>
                                            <input class="btn-check" type="radio" name="observed_condition" id="wc-observed-{{ $i }}" value="{{ $condition }}" @checked(old('observed_condition') === $condition)>
                                            <label class="btn btn-outline-secondary" for="wc-observed-{{ $i }}">{{ $condition }}</label>
                                        </div>
                                    @endforeach
                                </div>
                                @error('observed_condition')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                            </fieldset>
                            <button class="btn btn-primary fw-bold" type="submit">Send Answer</button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        <details class="wp-panel mb-4" @if(request()->has('calendar_month')) open @endif>
            <summary class="wp-panel-header fw-bold" style="cursor:pointer">View Details</summary>
            <div class="wp-panel-body" data-weather-calendar>
                <div class="wp-calendar-toolbar mb-3">
                    <div>
                        <h2 class="h5 fw-bold mb-1">Monthly Weather Calendar</h2>
                        <div class="text-muted">Forecast for <strong>Lian, Batangas municipal preview</strong></div>
                    </div>
                </div>
                <div class="mb-2 text-muted"><strong>Daily forecast values:</strong> shown on the exact dates they cover.@if($isTechnical) Source (IT only): Open-Meteo Forecast API.@endif</div>
                @include('weather-predictions.partials.monthly-outlook')
                <div class="wp-calendar-bar" role="group" aria-label="Choose calendar month">
                    <a class="wp-calendar-arrow" href="{{ route('weather-predictions.index', array_filter(['calendar_month' => $weatherCalendar['previous_month'], 'tab' => 'weather-forecast', 'run' => request('run')])) }}" data-calendar-nav aria-label="Show previous month" rel="prev"><span aria-hidden="true">&lsaquo;</span></a>
                    <h3 class="wp-calendar-month mb-0" aria-live="polite" data-calendar-month>{{ $weatherCalendar['label'] }}</h3>
                    <a class="wp-calendar-arrow" href="{{ route('weather-predictions.index', array_filter(['calendar_month' => $weatherCalendar['next_month'], 'tab' => 'weather-forecast', 'run' => request('run')])) }}" data-calendar-nav aria-label="Show next month" rel="next"><span aria-hidden="true">&rsaquo;</span></a>
                    @if($weatherCalendar['month']->format('Y-m') !== now('Asia/Manila')->format('Y-m'))
                        <a class="wp-calendar-today" href="{{ route('weather-predictions.index', array_filter(['calendar_month' => now('Asia/Manila')->format('Y-m'), 'tab' => 'weather-forecast', 'run' => request('run')])) }}" data-calendar-nav>Today</a>
                    @endif
                </div>
                <div class="wp-risk-legend" role="group" aria-label="Daily planning risk colors">
                    <span class="wp-risk-legend-item"><span class="wp-risk-swatch" style="background:#52b788"></span>Low</span>
                    <span class="wp-risk-legend-item"><span class="wp-risk-swatch" style="background:#ffd166"></span>Moderate</span>
                    <span class="wp-risk-legend-item"><span class="wp-risk-swatch" style="background:#d85b45"></span>High / Severe</span>
                </div>
                <div class="wp-calendar-loading" data-calendar-loading hidden role="status" aria-live="polite">
                    <div>
                        <div class="spinner-border text-success mb-3" aria-hidden="true"></div>
                        <div class="h5 fw-bold">Loading weather calendar</div>
                    </div>
                </div>
                <div data-calendar-content>
                    <div class="wp-calendar-grid" role="grid" aria-label="Weather forecast calendar for {{ $weatherCalendar['label'] }}">
                        @foreach($weatherCalendar['weekdays'] as $weekday)
                            <div class="wp-calendar-weekday" role="columnheader">{{ $weekday }}</div>
                        @endforeach
                        @foreach($weatherCalendar['weeks'] as $week)
                            <div class="wp-calendar-week" role="row">
                                @foreach($week as $day)
                                    @php
                                        $forecast = $day['forecast'];
                                        $riskLevel = data_get($day, 'risk.level');
                                    @endphp
                                    <article class="wp-calendar-day {{ $day['in_month'] ? '' : 'outside-month' }} {{ $day['is_today'] ? 'is-today' : '' }} {{ $forecast ? 'has-forecast' : 'no-forecast' }} {{ $riskLevel ? 'risk-'.strtolower($riskLevel) : '' }}" role="gridcell" aria-label="{{ $day['day_label'] }}{{ $forecast ? ', forecast available' : '' }}">
                                        <div class="wp-calendar-date">
                                            <span class="wp-calendar-daynum"><span class="wp-calendar-dow">{{ \Carbon\Carbon::parse($day['date'])->format('D') }}</span>{{ $day['day'] }}</span>
                                            @if($riskLevel)<span class="wp-calendar-risk">{{ $riskLevel }}</span>@endif
                                        </div>
                                        @if($forecast)
                                            <div class="wp-calendar-condition">
                                                @if($forecast['icon'])<img class="wp-calendar-icon" src="{{ $forecast['icon'] }}" alt="" width="20" height="20" loading="lazy">@endif
                                                {{ $forecast['condition'] ?: 'Condition unavailable' }}
                                            </div>
                                            <div class="wp-calendar-values">
                                                @if($forecast['rainfall'] !== null)
                                                    <div><strong>Rainfall:</strong> {{ number_format($forecast['rainfall'], 1) }} mm</div>
                                                @endif
                                                @if($forecast['temperature_max'] !== null || $forecast['temperature_min'] !== null)
                                                    <div><strong>Temperature:</strong> {{ $forecast['temperature_min'] !== null ? number_format($forecast['temperature_min'], 1).'°' : '—' }} / {{ $forecast['temperature_max'] !== null ? number_format($forecast['temperature_max'], 1).'°C' : '—' }}</div>
                                                @elseif($forecast['temperature'] !== null)
                                                    <div><strong>Temperature:</strong> {{ number_format($forecast['temperature'], 1) }}°C</div>
                                                @endif
                                                @if($forecast['humidity'] !== null)
                                                    <div><strong>RH:</strong> {{ number_format($forecast['humidity'], 0) }}%</div>
                                                @endif
                                            </div>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    <div class="small text-muted mt-3">Only dates with a real forecast show values; other dates are left blank. We never fill them in with invented values.</div>
                </div>

                @if(! empty($shortTermOutlook))
                    <h3 class="h6 fw-bold mt-4">Short-Term Planning Outlook</h3>
                    <div class="small text-muted mb-2">{{ $shortTermOutlook['planning_tip'] }}</div>
                    <div class="row g-3">
                        @foreach($shortTermOutlook['weeks'] as $week)
                            <div class="col-md-6 col-lg-3">
                                <div class="wp-advisory h-100">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <div class="wp-label mb-0">{{ $week['label'] }}</div>
                                        <span class="small text-muted">{{ $week['date_range_label'] }}</span>
                                    </div>
                                    <div class="wp-card-value">{{ number_format($week['rainfall'], 1) }} mm <span class="small text-muted fw-normal">/ {{ number_format($week['temperature'], 1) }}°C</span></div>
                                    <div class="ds-note mt-1">{{ $week['advisory'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="wp-note">{{ $shortTermOutlook['disclaimer'] }}</div>
                @endif
            </div>
        </details>
    </div>
    </div>
    @if($isFarmer)
    </div>
    @endif

    <div class="modal fade" id="yieldConfirmModal" tabindex="-1" aria-labelledby="yieldConfirmTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5 fw-bold" id="yieldConfirmTitle">Confirm Harvest Estimate</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cancel"></button>
                </div>
                <div class="modal-body">
                    <div class="wp-advisory mb-3">
                        <div class="wp-label mb-2">Farm size</div>
                        <div class="fw-bold"><span data-confirm-area>0</span> hectares</div>
                    </div>
                    <p class="mb-0 text-muted">This will estimate your rice harvest using this farm size.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary fw-bold" data-confirm-yield-submit>Estimate My Harvest</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const initWeatherPredictionPage = () => {
            if (document.documentElement.dataset.weatherPredictionBound === 'true') return;
            document.documentElement.dataset.weatherPredictionBound = 'true';
            const forecastDate = document.getElementById('target_date');
            const predictionDate = document.getElementById('prediction_date');
            const yieldForm = document.getElementById('yieldPredictionForm');
            const areaInput = document.getElementById('area');
            const yieldButton = document.getElementById('yieldPredictButton');
            const confirmArea = document.querySelector('[data-confirm-area]');
            const confirmSubmit = document.querySelector('[data-confirm-yield-submit]');
            const confirmModalElement = document.getElementById('yieldConfirmModal');
            const confirmModal = confirmModalElement && window.bootstrap ? new window.bootstrap.Modal(confirmModalElement) : null;
            const tabs = document.querySelectorAll('[data-weather-tab]');
            const panels = document.querySelectorAll('[data-weather-panel]');
            const tabsContainer = document.querySelector('[data-initial-weather-tab]');
            const calendar = document.querySelector('[data-weather-calendar]');
            const calendarLoading = calendar?.querySelector('[data-calendar-loading]');
            const calendarContent = calendar?.querySelector('[data-calendar-content]');
            let yieldConfirmed = false;

            const showTab = (name) => {
                const validName = Array.from(tabs).some((tab) => tab.dataset.weatherTab === name)
                    ? name
                    : 'weather-forecast';
                tabs.forEach((tab) => {
                    const active = tab.dataset.weatherTab === validName;
                    tab.classList.toggle('active', active);
                    tab.setAttribute('aria-selected', active ? 'true' : 'false');
                    tab.tabIndex = active ? 0 : -1;
                });

                panels.forEach((panel) => {
                    panel.hidden = panel.dataset.weatherPanel !== validName;
                });
            };

            tabs.forEach((tab) => {
                tab.addEventListener('click', () => {
                    showTab(tab.dataset.weatherTab);
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', tab.dataset.weatherTab);
                    window.history.replaceState({}, '', url);
                });
            });

            document.querySelectorAll('[data-calendar-nav]').forEach((link) => {
                link.addEventListener('click', () => {
                    if (calendarLoading) calendarLoading.hidden = false;
                    if (calendarContent) calendarContent.hidden = true;
                    link.setAttribute('aria-disabled', 'true');
                });
            });

            forecastDate?.addEventListener('input', () => {
                if (predictionDate) predictionDate.value = forecastDate.value;
            });

            predictionDate?.addEventListener('input', () => {
                if (forecastDate) forecastDate.value = predictionDate.value;
            });

            yieldForm?.addEventListener('submit', (event) => {
                /*
                |--------------------------------------------------------------------------
                | Already Confirmed
                |--------------------------------------------------------------------------
                |
                | Once the user confirms, allow the normal browser POST request.
                |
                */

                if (yieldConfirmed) {
                    if (yieldButton) {
                        yieldButton.disabled = true;
                        yieldButton.textContent =
                            yieldButton.dataset.loadingText
                            || 'Generating rice-yield prediction...';
                    }

                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | Validate Farm Area
                |--------------------------------------------------------------------------
                */

                const area =
                    Number(
                        areaInput?.value
                    );

                if (
                    ! areaInput?.checkValidity()
                    ||
                    ! Number.isFinite(area)
                    ||
                    area <= 0
                ) {
                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | Stop Initial Submission
                |--------------------------------------------------------------------------
                */

                event.preventDefault();

                if (confirmArea) {
                    confirmArea.textContent =
                        areaInput.value;
                }

                /*
                |--------------------------------------------------------------------------
                | Show Confirmation Modal
                |--------------------------------------------------------------------------
                */

                if (confirmModal) {
                    confirmModal.show();
                } else {
                    /*
                    |--------------------------------------------------------------------------
                    | No Bootstrap Modal Available
                    |--------------------------------------------------------------------------
                    |
                    | Use native HTML form submission directly.
                    |
                    */

                    yieldConfirmed = true;

                    if (yieldButton) {
                        yieldButton.disabled = true;
                        yieldButton.textContent =
                            yieldButton.dataset.loadingText
                            || 'Generating rice-yield prediction...';
                    }

                    yieldForm.submit();
                }
            });


            confirmSubmit?.addEventListener(
                'click',
                () => {
                    /*
                    |--------------------------------------------------------------------------
                    | Confirm Prediction
                    |--------------------------------------------------------------------------
                    */

                    if (
                        ! yieldForm
                    ) {
                        return;
                    }

                    yieldConfirmed = true;

                    /*
                    |--------------------------------------------------------------------------
                    | Close Confirmation Modal
                    |--------------------------------------------------------------------------
                    */

                    if (confirmModal) {
                        confirmModal.hide();
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Update Buttons
                    |--------------------------------------------------------------------------
                    */

                    confirmSubmit.disabled =
                        true;

                    confirmSubmit.textContent =
                        'Generating rice-yield prediction...';

                    if (yieldButton) {
                        yieldButton.disabled =
                            true;

                        yieldButton.textContent =
                            yieldButton.dataset.loadingText
                            || 'Generating rice-yield prediction...';
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | IMPORTANT
                    |--------------------------------------------------------------------------
                    |
                    | Use native form.submit() here.
                    |
                    | This bypasses the JavaScript submit event and sends the POST directly
                    | to Laravel.
                    |
                    */

                    yieldForm.submit();
                }
            );

            const initialTab = tabsContainer?.dataset.initialWeatherTab || 'weather-forecast';
            showTab(initialTab);

            tabs.forEach((tab) => {
                tab.addEventListener('keydown', (event) => {
                    const list = Array.from(tabs);
                    const at = list.indexOf(tab);
                    let next = null;
                    if (event.key === 'ArrowRight') next = list[(at + 1) % list.length];
                    if (event.key === 'ArrowLeft') next = list[(at - 1 + list.length) % list.length];
                    if (event.key === 'Home') next = list[0];
                    if (event.key === 'End') next = list[list.length - 1];
                    if (next) {
                        event.preventDefault();
                        next.focus();
                        next.click();
                    }
                });
            });

            const checkForm = document.querySelector('[data-weather-check]');
            const observedBox = checkForm?.querySelector('[data-weather-check-observed]');
            const syncObserved = () => {
                if (!observedBox) return;
                const chosen = checkForm.querySelector('input[name="accuracy"]:checked')?.value;
                observedBox.hidden = !(chosen === 'partly' || chosen === 'different');
            };
            checkForm?.querySelectorAll('input[name="accuracy"]').forEach((input) => input.addEventListener('change', syncObserved));
            syncObserved();
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initWeatherPredictionPage, { once: true });
            } else {
                initWeatherPredictionPage();
            }
        })();
    </script>
    @if (($analyticsRun ?? null) && in_array($analyticsRun->status, ['pending', 'processing'], true))
        <script>
            (() => {
                const banner = document.querySelector('[data-analytics-run]');
                if (!banner) return;
                const message = banner.querySelector('[data-analytics-message]');
                let failures = 0;
                const poll = window.setInterval(async () => {
                    try {
                        const response = await fetch(banner.dataset.analyticsRun, { headers: { Accept: 'application/json' }, loader: false });
                        if (!response.ok) throw new Error(`Status check failed.`);
                        const run = await response.json();
                        failures = 0;
                        if (run.status === 'completed' || run.status === 'failed') {
                            window.clearInterval(poll);
                            window.location.reload();
                        }
                    } catch (error) {
                        failures += 1;
                        if (failures >= 2 && message) {
                            message.textContent = 'We could not refresh the status. Please reload the page in a moment.';
                            message.classList.add('text-danger');
                        }
                        console.error('Prediction status check failed.', error);
                    }
                }, 2500);
            })();
        </script>
    @endif
</x-app-layout>
