@php
    $weatherReady = isset($result) && $result && ($result['ready'] ?? false);
    $predictions = $weatherReady ? $result['predictions'] : [];
    $targetLabel = $targetMonth->format('F Y');
    $targetDate = $targetDate ?? $targetMonth;
    $modelInput = $mlResult['model_input'] ?? ($defaultModelInput ?? []);
    $seasonValue = $modelInput['season'] ?? ($predictions['season'] ?? 'Wet');

    $rainfall = (float) ($modelInput['rainfall'] ?? ($predictions['rainfall'] ?? 0));
    $tempAvg = (float) ($modelInput['temp_avg'] ?? ($predictions['temperature'] ?? 0));
    $tempRange = (float) ($modelInput['temp_range'] ?? 0);
    $area = (float) ($modelInput['area'] ?? 0);
    $previousRainfall = (float) ($modelInput['previous_rainfall'] ?? $rainfall);
    $previousTemp = (float) ($modelInput['previous_temp'] ?? $tempAvg);
    $rainfall6m = (float) ($modelInput['rainfall_6m'] ?? $rainfall);
    $temp3m = (float) ($modelInput['temp_3m'] ?? $tempAvg);
    $temp6m = (float) ($modelInput['temp_6m'] ?? $tempAvg);
    $seasonalRainfall = (float) ($modelInput['seasonal_rainfall'] ?? $rainfall);
    $seasonalTemp = (float) ($modelInput['seasonal_temp'] ?? $tempAvg);

    $rainfallStatus = $rainfall < 120 ? ['Dry', 'Rainfall may be limited for rainfed fields.', 'tone-amber'] : ($rainfall > 280 ? ['Very Wet', 'Watch for flooding and drainage issues.', 'tone-red'] : ['Favorable', 'Rainfall is within a workable planning range.', 'tone-green']);
    $temperatureStatus = $tempAvg > 32 ? ['Hot', 'Heat stress may reduce crop performance.', 'tone-red'] : ($tempAvg < 24 ? ['Cool', 'Growth may be slower than expected.', 'tone-amber'] : ['Favorable', 'Temperature is suitable for rice growth.', 'tone-green']);
    $humidity = (float) ($modelInput['humidity'] ?? ($predictions['humidity'] ?? 75));
    $windSpeed = (float) ($modelInput['wind_speed'] ?? ($predictions['wind_speed'] ?? 8));
    $humidityStatus = $humidity > 88 ? ['Humid', 'Monitor for disease pressure.', 'tone-amber'] : ['Balanced', 'Humidity is manageable.', 'tone-green'];
    $windStatus = $windSpeed > 18 ? ['Windy', 'Secure field materials and monitor lodging risk.', 'tone-amber'] : ['Calm', 'Wind speed is not a major concern.', 'tone-green'];
    $weatherConfidence = $result['confidence'] ?? ['value' => 0, 'label' => 'Not ready', 'note' => 'Add more climate records.'];
    $weatherInsights = $result['insights'] ?? [];
    $activeWeatherTab = request()->isMethod('post') || isset($mlResult) && $mlResult ? 'rice-yield' : 'weather-forecast';
@endphp

<x-app-layout>
    <style>
        .wp-hero { position: relative; overflow: hidden; border-radius: 32px; padding: 1.35rem; margin-bottom: 1.25rem; color: #fff; background: radial-gradient(circle at 86% 14%, rgba(82,183,136,.24), transparent 30%), linear-gradient(145deg, #0d1f18 0%, #1a3a2a 62%, #163324 100%); box-shadow: 0 1rem 2.3rem rgba(13,31,24,.18); }
        .wp-hero::before { content: ""; position: absolute; inset: 0; background: radial-gradient(rgba(255,255,255,.16) 1px, transparent 1px); background-size: 28px 28px; mask-image: linear-gradient(90deg, rgba(0,0,0,.8), transparent 86%); }
        .wp-panel::before, .wp-metric::before { content: ""; position: absolute; left: 0; right: 0; top: 0; height: 5px; background: var(--accent, #52b788); }
        .wp-hero > * { position: relative; z-index: 1; }
        .wp-eyebrow { font-size: .72rem; font-weight: 900; text-transform: uppercase; letter-spacing: .12em; color: rgba(255,255,255,.7); }
        .wp-tabs { display: inline-flex; gap: .35rem; padding: .35rem; margin-bottom: 1.25rem; border: 1px solid rgba(255,255,255,.12); border-radius: 999px; background: var(--ic-green-950); }
        .wp-tab-button { border: 0; border-radius: 999px; padding: .65rem 1rem; color: rgba(255,255,255,.62); background: transparent; font-weight: 900; }
        .wp-tab-button.active { color: #0d1f18; background: #52b788; }
        .wp-tab-panel[hidden] { display: none !important; }
        .wp-panel, .wp-metric, .wp-input-card, .wp-result-card { position: relative; overflow: hidden; border: 1px solid rgba(212,237,218,.98); border-radius: 18px; background: linear-gradient(145deg, rgba(255,255,255,.96), rgba(247,251,248,.96)); box-shadow: 0 .9rem 2rem rgba(13,31,24,.07); }
        .wp-panel-header { padding: 1rem; border-bottom: 1px solid rgba(212,237,218,.9); background: linear-gradient(90deg, #fff, #f0f7f4); }
        .wp-panel-body, .wp-metric-body { padding: 1rem; }
        .wp-metric { min-height: 168px; }
        .wp-label { color: #5a7a64; font-size: .72rem; font-weight: 900; text-transform: uppercase; letter-spacing: .06em; }
        .wp-value { margin-top: .35rem; color: #0d1f18; font-size: clamp(1.8rem, 3vw, 2.45rem); font-weight: 900; line-height: 1; }
        .wp-unit { color: #5a7a64; font-size: .86rem; font-weight: 700; }
        .wp-note { color: #5a7a64; font-size: .86rem; margin-top: .7rem; }
        .wp-status { display: inline-flex; align-items: center; gap: .4rem; border-radius: 999px; padding: .35rem .65rem; margin-top: .85rem; font-size: .76rem; font-weight: 900; color: var(--status-color, #2d6a4f); background: var(--status-bg, #d8f3dc); }
        .wp-status::before { content: ""; width: .45rem; height: .45rem; border-radius: 999px; background: currentColor; }
        .tone-green { --accent: #52b788; --status-color: #2d6a4f; --status-bg: #d8f3dc; }
        .tone-blue { --accent: #2d6a4f; --status-color: #2d6a4f; --status-bg: #d8f3dc; }
        .tone-amber { --accent: #ffd166; --status-color: #8a5a00; --status-bg: #fff4cf; }
        .tone-red { --accent: #d85b45; --status-color: #9f3728; --status-bg: #fde8e2; }
        .wp-input-card { padding: 1rem; height: 100%; }
        .wp-input-row { display: flex; justify-content: space-between; gap: .75rem; align-items: flex-start; margin-bottom: .7rem; }
        .wp-input-card .form-control { max-width: 132px; font-weight: 800; text-align: right; }
        .wp-help { color: #5a7a64; font-size: .78rem; margin-top: .45rem; }
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
        .wp-feature-pill { display: inline-flex; border-radius: 999px; padding: .35rem .65rem; background: #d8f3dc; color: #1f6f4a; font-size: .76rem; font-weight: 800; margin: .18rem; }
        .wp-score-bar { height: 10px; border-radius: 999px; overflow: hidden; background: #dbe8dd; }
        .wp-score-bar > span { display: block; height: 100%; width: min(var(--score, 50%), 100%); background: linear-gradient(90deg, #ffd166, #52b788); }
        .ds-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .85rem; }
        .ds-card { border: 1px solid #d4edda; border-radius: 8px; background: #fff; padding: 1rem; min-height: 138px; }
        .ds-card.primary { background: linear-gradient(145deg, #fff, #f0f7f4); border-left: 5px solid var(--accent, #52b788); }
        .ds-title { color: #5a7a64; font-size: .74rem; font-weight: 900; text-transform: uppercase; letter-spacing: .055em; }
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
        @media (max-width: 1199.98px) { .ds-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 767.98px) { .ds-grid { grid-template-columns: 1fr; } .wp-input-row { display: grid; } .wp-input-card .form-control { max-width: none; text-align: left; } }

        /* -- dark theme overrides -- */
        .wp-page { color: rgba(255,255,255,.85); }
        .wp-panel, .wp-metric, .wp-input-card, .wp-result-card {
            border-color: rgba(255,255,255,.12);
            background: var(--ic-green-950);
        }
        .wp-panel-header { border-color: rgba(255,255,255,.08); background: rgba(255,255,255,.02); }
        .wp-label { color: rgba(255,255,255,.5); }
        .wp-value { color: #fff; }
        .wp-unit, .wp-note, .wp-help { color: rgba(255,255,255,.55); }
        .tone-green { --accent: #52b788; --status-color: #74c69d; --status-bg: rgba(82,183,136,.16); }
        .tone-blue { --accent: #6fb8e0; --status-color: #6fb8e0; --status-bg: rgba(47,111,143,.22); }
        .tone-amber { --accent: #ffd166; --status-color: #ffd166; --status-bg: rgba(255,209,102,.16); }
        .tone-red { --accent: #f0917c; --status-color: #f0917c; --status-bg: rgba(216,91,69,.2); }
        .wp-input-card .form-control { background: rgba(255,255,255,.05); border-color: rgba(255,255,255,.18); color: #fff; }
        .wp-advisory { border-color: rgba(255,255,255,.12); background: rgba(255,255,255,.04); }
        .wp-feature-pill { background: rgba(82,183,136,.16); color: #74c69d; }
        .wp-score-bar { background: rgba(255,255,255,.08); }
        .ds-card { border-color: rgba(255,255,255,.12); background: rgba(255,255,255,.04); }
        .ds-card.primary { background: rgba(82,183,136,.08); }
        .ds-title { color: rgba(255,255,255,.5); }
        .ds-value { color: #fff; }
        .ds-note { color: rgba(255,255,255,.55); }
        .ds-notifications li { border-color: rgba(255,255,255,.12); background: rgba(255,255,255,.04); color: rgba(255,255,255,.85); }
        .smart-list li { border-color: rgba(255,255,255,.12); background: rgba(255,255,255,.04); color: rgba(255,255,255,.85); }
        .stress-chip { border-color: rgba(255,255,255,.12); background: rgba(255,255,255,.04); }
        .wp-page .text-muted { color: rgba(255,255,255,.5) !important; }
        .wp-page .text-dark { color: #fff !important; }
        .wp-page .text-success { color: #74c69d !important; }
        .wp-page .text-primary { color: #6fb8e0 !important; }
        .wp-page code { color: rgba(255,255,255,.6); }

        /* -- plain, uniform page (matches farmer dashboard, no tone variation) -- */
        .wp-metric::before, .wp-panel::before { background: #52b788 !important; }
        .wp-metric .wp-status { color: #74c69d !important; background: rgba(82,183,136,.16) !important; }
        .ds-card.primary { border-left-color: #52b788 !important; }
        .stress-chip { border-left-color: #52b788 !important; }
    </style>

    <div class="wp-page">
    <section class="wp-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-4 align-items-xl-end">
            <div>
                <div class="wp-eyebrow mb-2">Random Forest Machine Learning</div>
                <h1 class="h2 fw-bold mb-2">Rice Yield Forecast</h1>
                <p class="mb-0 text-white-50" style="max-width: 720px;">Pick a date and iClimate will estimate the rice yield using the forecast for that month.</p>
            </div>
            <form method="GET" action="{{ route('weather-predictions.index') }}" class="d-flex flex-column flex-sm-row gap-2 align-items-sm-end">
                <div>
                    <label for="target_date" class="form-label text-white-50 small fw-bold mb-1">Prediction Date</label>
                    <input id="target_date" name="target_date" type="date" class="form-control" value="{{ $targetDate->format('Y-m-d') }}">
                </div>
                <button class="btn btn-light fw-bold" type="submit">Update Forecast</button>
            </form>
        </div>
    </section>

    <div class="wp-tabs" role="tablist" aria-label="Weather prediction sections">
        <button class="wp-tab-button {{ $activeWeatherTab === 'weather-forecast' ? 'active' : '' }}" type="button" role="tab" aria-selected="{{ $activeWeatherTab === 'weather-forecast' ? 'true' : 'false' }}" data-weather-tab="weather-forecast">Weather Forecast</button>
        <button class="wp-tab-button {{ $activeWeatherTab === 'rice-yield' ? 'active' : '' }}" type="button" role="tab" aria-selected="{{ $activeWeatherTab === 'rice-yield' ? 'true' : 'false' }}" data-weather-tab="rice-yield">Rice Yield</button>
    </div>

    @if (isset($error) && $error)
        <div class="alert alert-danger shadow-sm">
            <strong>Prediction error:</strong>
            <pre class="mb-0 mt-2 small">{{ $error }}</pre>
        </div>
    @endif

    <div class="wp-tab-panel" data-weather-panel="weather-forecast" @if($activeWeatherTab !== 'weather-forecast') hidden @endif>
        @if (isset($result) && $result && ! $result['ready'])
            <div class="alert alert-warning shadow-sm">
                <strong>Weather model not ready.</strong>
                {{ $result['message'] }}
                Current monthly samples: {{ $result['months_available'] }}.
            </div>
        @elseif ($weatherReady)
            <div class="row g-4 mb-4">
                <div class="col-md-6 col-xl-3"><div class="wp-metric {{ $rainfallStatus[2] }} h-100"><div class="wp-metric-body"><div class="wp-label">Rainfall</div><div class="wp-value">{{ number_format($predictions['rainfall'], 2) }} <span class="wp-unit">mm</span></div><div class="wp-status">{{ $rainfallStatus[0] }}</div><div class="wp-note">{{ $rainfallStatus[1] }}</div></div></div></div>
                <div class="col-md-6 col-xl-3"><div class="wp-metric {{ $temperatureStatus[2] }} h-100"><div class="wp-metric-body"><div class="wp-label">Temperature</div><div class="wp-value">{{ number_format($predictions['temperature'], 2) }} <span class="wp-unit">&deg;C</span></div><div class="wp-status">{{ $temperatureStatus[0] }}</div><div class="wp-note">{{ $temperatureStatus[1] }}</div></div></div></div>
                <div class="col-md-6 col-xl-3"><div class="wp-metric {{ $humidityStatus[2] }} h-100"><div class="wp-metric-body"><div class="wp-label">Humidity</div><div class="wp-value">{{ number_format($humidity, 2) }}<span class="wp-unit">%</span></div><div class="wp-status">{{ $humidityStatus[0] }}</div><div class="wp-note">{{ $humidityStatus[1] }}</div></div></div></div>
                <div class="col-md-6 col-xl-3"><div class="wp-metric {{ $windStatus[2] }} h-100"><div class="wp-metric-body"><div class="wp-label">Wind Speed</div><div class="wp-value">{{ number_format($windSpeed, 2) }}</div><div class="wp-status">{{ $windStatus[0] }}</div><div class="wp-note">{{ $windStatus[1] }}</div></div></div></div>
            </div>

            <div class="wp-panel mb-4 tone-green">
                <div class="wp-panel-header"><h2 class="h5 fw-bold mb-1">Forecast Summary</h2><div class="small text-muted">{{ $targetLabel }} | {{ $predictions['season'] }} season | {{ $weatherConfidence['label'] }}</div></div>
                <div class="wp-panel-body">
                    <p class="mb-0 text-muted">{{ $result['message'] }}</p>
                    @if(isset($mlResult) && $mlResult)
                        <div class="mt-3">
                            <span class="wp-feature-pill">{{ $mlResult['source'] ?? 'Trained Random Forest model' }}</span>
                            <span class="wp-feature-pill">Yield: {{ number_format((float) $mlResult['predicted_yield'], 2) }} {{ $mlResult['unit'] ?? 'tons/hectare' }}</span>
                            @if(! empty($mlResult['api_response_time_ms']))
                                <span class="wp-feature-pill">API {{ number_format((float) $mlResult['api_response_time_ms']) }} ms</span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <div class="wp-tab-panel" data-weather-panel="rice-yield" @if($activeWeatherTab !== 'rice-yield') hidden @endif>
        <div class="wp-panel mb-4 tone-green">
            <div class="wp-panel-header">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                    <div><h2 class="h5 fw-bold mb-1">Predict Rice Yield</h2><div class="small text-muted">Only the date is needed. Weather inputs are filled automatically.</div></div>
                </div>
            </div>
            <div class="wp-panel-body">
                <form method="POST" action="{{ route('weather-predictions.predict') }}" data-loading="true" id="yieldPredictionForm">
                    @csrf
                    <input type="hidden" name="farm_type" value="Rainfed">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-7 col-xl-4">
                            <div class="wp-input-card">
                                <label class="wp-label" for="prediction_date">Prediction Date</label>
                                <input id="prediction_date" type="date" name="prediction_date" class="form-control mt-2 text-start" value="{{ $targetDate->format('Y-m-d') }}" required>
                                <div class="wp-help">Choose the date to check.</div>
                            </div>
                        </div>
                        <div class="col-md-5 col-xl-3">
                            <button class="btn btn-primary fw-bold px-4 w-100" type="submit" data-loading-text="Predicting...">Predict</button>
                        </div>
                        <div class="col-xl-5">
                            <div class="small text-muted">Using {{ number_format($rainfall, 2) }} mm rainfall, {{ number_format($tempAvg, 2) }} &deg;C, {{ number_format($humidity, 2) }}% humidity, {{ number_format($windSpeed, 2) }} wind speed, and {{ $seasonValue }} season.</div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @if(isset($mlResult) && $mlResult)
        @php
            $yield = (float) $mlResult['predicted_yield'];
            $yieldScore = max(8, min(100, ($yield / 7) * 100));
            $yieldTone = $yield >= 4 ? 'tone-green' : ($yield >= 2.5 ? 'tone-amber' : 'tone-red');
            $decision = $mlResult['decision_support'] ?? null;
            $score = $decision['score']['value'] ?? null;
            $scoreTone = $score >= 90 ? 'tone-green' : ($score >= 70 ? 'tone-blue' : ($score >= 50 ? 'tone-amber' : 'tone-red'));
        @endphp
        <div class="wp-panel mb-4 {{ $yieldTone }}">
            <div class="wp-panel-header"><h2 class="h5 fw-bold mb-1">Prediction Result</h2><div class="small text-muted">{{ $mlResult['source'] ?? 'Trained Random Forest model' }}{{ ! empty($mlResult['api_confidence']) ? ' | Confidence '.$mlResult['api_confidence'].'%' : '' }}</div></div>
            <div class="wp-panel-body">
                <div class="row g-4">
                    <div class="col-lg-4"><div class="wp-result-card h-100"><div class="wp-label">Estimated Rice Yield</div><div class="wp-value">{{ number_format($yield, 2) }}</div><div class="wp-unit">tons/hectare</div><div class="wp-score-bar mt-3" style="--score: {{ $yieldScore }}%;"><span></span></div></div></div>
                    <div class="col-lg-8">
                        <div class="row g-3">
                            <div class="col-md-6"><div class="wp-advisory h-100"><div class="wp-label mb-2">Planting Advisory</div><div class="fw-bold text-success">{{ $mlResult['planting_advisory'] }}</div></div></div>
                            <div class="col-md-6"><div class="wp-advisory h-100"><div class="wp-label mb-2">Irrigation Recommendation</div><div class="fw-bold text-primary">{{ $mlResult['irrigation_recommendation'] }}</div></div></div>
                            <div class="col-md-6"><div class="wp-advisory h-100"><div class="wp-label mb-2">Risk Level</div><div class="fw-bold">{{ $decision['risk']['label'] ?? 'Not available' }}</div></div></div>
                            <div class="col-md-6"><div class="wp-advisory h-100"><div class="wp-label mb-2">Condition Score</div><div class="fw-bold">{{ $decision['score']['value'] ?? 0 }} - {{ $decision['score']['interpretation'] ?? 'Not available' }}</div></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($decision)
            <div class="wp-panel mb-4 {{ $scoreTone }}">
                <div class="wp-panel-header"><h2 class="h5 fw-bold mb-1">What To Do</h2><div class="small text-muted">Main recommendation only.</div></div>
                <div class="wp-panel-body">
                    <div class="row g-3">
                        <div class="col-lg-4"><div class="ds-card h-100 primary"><div class="ds-title">Weather</div><div class="ds-value">{{ $decision['overall_recommendation']['weather'] }}</div></div></div>
                        <div class="col-lg-4"><div class="ds-card h-100 primary"><div class="ds-title">Yield</div><div class="ds-value">{{ $decision['overall_recommendation']['yield'] }}</div></div></div>
                        <div class="col-lg-4"><div class="ds-card h-100 primary"><div class="ds-title">Action</div><div class="ds-value">{{ $decision['overall_recommendation']['planting'] }}</div><div class="ds-note">{{ $decision['overall_recommendation']['irrigation'] }}</div></div></div>
                    </div>
                </div>
            </div>
        @endif
        @endif
    </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const forecastDate = document.getElementById('target_date');
            const predictionDate = document.getElementById('prediction_date');
            const tabs = document.querySelectorAll('[data-weather-tab]');
            const panels = document.querySelectorAll('[data-weather-panel]');

            const showTab = (name) => {
                tabs.forEach((tab) => {
                    const active = tab.dataset.weatherTab === name;
                    tab.classList.toggle('active', active);
                    tab.setAttribute('aria-selected', active ? 'true' : 'false');
                });

                panels.forEach((panel) => {
                    panel.hidden = panel.dataset.weatherPanel !== name;
                });
            };

            tabs.forEach((tab) => {
                tab.addEventListener('click', () => showTab(tab.dataset.weatherTab));
            });

            forecastDate?.addEventListener('input', () => {
                if (predictionDate) predictionDate.value = forecastDate.value;
            });

            predictionDate?.addEventListener('input', () => {
                if (forecastDate) forecastDate.value = predictionDate.value;
            });

            showTab(@json($activeWeatherTab));
        });
    </script>
</x-app-layout>
