@php
    $weatherReady = isset($result) && $result && ($result['ready'] ?? false);
    $predictions = $weatherReady ? $result['predictions'] : [];
    $targetLabel = $targetMonth->format('F Y');
    $seasonValue = old('season', $predictions['season'] ?? 'Wet');
    $farmTypeValue = old('farm_type', 'Rainfed');

    $rainfall = (float) old('rainfall', $predictions['rainfall'] ?? 180);
    $tempAvg = (float) old('temp_avg', $predictions['temperature'] ?? 29);
    $tempRange = (float) old('temp_range', 8);
    $area = (float) old('area', 120);
    $previousRainfall = (float) old('previous_rainfall', 150);
    $previousTemp = (float) old('previous_temp', 28.5);
    $rainfall6m = (float) old('rainfall_6m', 170);
    $temp3m = (float) old('temp_3m', 29);
    $temp6m = (float) old('temp_6m', 28.8);
    $seasonalRainfall = (float) old('seasonal_rainfall', 900);
    $seasonalTemp = (float) old('seasonal_temp', 29);

    $rainfallStatus = $rainfall < 120 ? ['Dry', 'Rainfall may be limited for rainfed fields.', 'tone-amber'] : ($rainfall > 280 ? ['Very Wet', 'Watch for flooding and drainage issues.', 'tone-red'] : ['Favorable', 'Rainfall is within a workable planning range.', 'tone-green']);
    $temperatureStatus = $tempAvg > 32 ? ['Hot', 'Heat stress may reduce crop performance.', 'tone-red'] : ($tempAvg < 24 ? ['Cool', 'Growth may be slower than expected.', 'tone-amber'] : ['Favorable', 'Temperature is suitable for rice growth.', 'tone-green']);
    $humidityStatus = ($predictions['humidity'] ?? 75) > 88 ? ['Humid', 'Monitor for disease pressure.', 'tone-amber'] : ['Balanced', 'Humidity is manageable.', 'tone-green'];
    $windStatus = ($predictions['wind_speed'] ?? 8) > 18 ? ['Windy', 'Secure field materials and monitor lodging risk.', 'tone-amber'] : ['Calm', 'Wind speed is not a major concern.', 'tone-green'];
    $weatherConfidence = $result['confidence'] ?? ['value' => 0, 'label' => 'Not ready', 'note' => 'Add more climate records.'];
    $weatherInsights = $result['insights'] ?? [];
@endphp

<x-app-layout>
    <style>
        .wp-hero { position: relative; overflow: hidden; border-radius: 32px; padding: 1.35rem; margin-bottom: 1.25rem; color: #fff; background: radial-gradient(circle at 86% 14%, rgba(82,183,136,.24), transparent 30%), linear-gradient(145deg, #0d1f18 0%, #1a3a2a 62%, #163324 100%); box-shadow: 0 1rem 2.3rem rgba(13,31,24,.18); }
        .wp-hero::before { content: ""; position: absolute; inset: 0; background: radial-gradient(rgba(255,255,255,.16) 1px, transparent 1px); background-size: 28px 28px; mask-image: linear-gradient(90deg, rgba(0,0,0,.8), transparent 86%); }
        .wp-hero::after, .wp-panel::before, .wp-metric::before { content: ""; position: absolute; left: 0; right: 0; top: 0; height: 5px; background: var(--accent, #52b788); }
        .wp-hero::after { top: auto; bottom: 0; height: 7px; background: linear-gradient(90deg, #ffd166, #52b788, #95d5b2, #d85b45); }
        .wp-hero > * { position: relative; z-index: 1; }
        .wp-eyebrow { font-size: .72rem; font-weight: 900; text-transform: uppercase; letter-spacing: .12em; color: rgba(255,255,255,.7); }
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
        .wp-flow { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem; }
        .wp-step { display: flex; gap: .75rem; align-items: flex-start; padding: .85rem; border: 1px solid #d4edda; border-radius: 8px; background: #fff; }
        .wp-step-num { width: 2rem; height: 2rem; flex: 0 0 auto; display: inline-grid; place-items: center; border-radius: 8px; background: #1a3a2a; color: #fff; font-weight: 900; }
        .wp-input-card { padding: 1rem; height: 100%; }
        .wp-input-row { display: flex; justify-content: space-between; gap: .75rem; align-items: flex-start; margin-bottom: .7rem; }
        .wp-input-card input[type="range"] { width: 100%; accent-color: #52b788; }
        .wp-input-card .form-control { max-width: 132px; font-weight: 800; text-align: right; }
        .wp-help { color: #5a7a64; font-size: .78rem; margin-top: .45rem; }
        .wp-preset { border: 1px solid #d4edda; border-radius: 8px; background: #fff; color: #1b2b23; padding: .7rem .8rem; text-align: left; transition: transform .15s ease, border-color .15s ease, background .15s ease; }
        .wp-preset:hover { transform: translateY(-1px); border-color: #52b788; background: #f0f7f4; }
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
        @media (max-width: 991.98px) { .wp-flow { grid-template-columns: 1fr; } }
        @media (max-width: 1199.98px) { .ds-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 767.98px) { .ds-grid { grid-template-columns: 1fr; } .wp-input-row { display: grid; } .wp-input-card .form-control { max-width: none; text-align: left; } }
    </style>

    <section class="wp-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-4 align-items-xl-end">
            <div>
                <div class="wp-eyebrow mb-2">Random Forest Machine Learning</div>
                <h1 class="h2 fw-bold mb-2">Monthly Weather Prediction and Rice Yield</h1>
                <p class="mb-0 text-white-50" style="max-width: 820px;">Choose a target month, review the forecast in plain language, then adjust farm inputs to estimate rice yield and decision-support recommendations.</p>
            </div>
            <form method="GET" action="{{ route('weather-predictions.index') }}" class="d-flex flex-column flex-sm-row gap-2 align-items-sm-end">
                <div>
                    <label for="target_month" class="form-label text-white-50 small fw-bold mb-1">Target Month</label>
                    <input id="target_month" name="target_month" type="month" class="form-control" value="{{ $targetMonth->format('Y-m') }}">
                </div>
                <button class="btn btn-light fw-bold" type="submit">Update Forecast</button>
            </form>
        </div>
    </section>

    @if (isset($error) && $error)
        <div class="alert alert-danger shadow-sm">
            <strong>Prediction error:</strong>
            <pre class="mb-0 mt-2 small">{{ $error }}</pre>
        </div>
    @endif

    @if (isset($result) && $result && ! $result['ready'])
        <div class="alert alert-warning shadow-sm">
            <strong>Weather model not ready.</strong>
            {{ $result['message'] }}
            Current monthly samples: {{ $result['months_available'] }}.
        </div>
    @elseif ($weatherReady)
        <div class="wp-flow mb-4">
            <div class="wp-step"><span class="wp-step-num">1</span><div><div class="fw-bold">Review {{ $targetLabel }}</div><div class="small text-muted">Check rainfall, heat, humidity, and wind risks.</div></div></div>
            <div class="wp-step"><span class="wp-step-num">2</span><div><div class="fw-bold">Tune farm inputs</div><div class="small text-muted">Use presets or sliders for the field scenario.</div></div></div>
            <div class="wp-step"><span class="wp-step-num">3</span><div><div class="fw-bold">Generate guidance</div><div class="small text-muted">Estimate yield and read advisories.</div></div></div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6 col-xl-3"><div class="wp-metric {{ $rainfallStatus[2] }} h-100"><div class="wp-metric-body"><div class="wp-label">Rainfall</div><div class="wp-value">{{ number_format($predictions['rainfall'], 2) }} <span class="wp-unit">mm</span></div><div class="wp-status">{{ $rainfallStatus[0] }}</div><div class="wp-note">{{ $rainfallStatus[1] }}</div></div></div></div>
            <div class="col-md-6 col-xl-3"><div class="wp-metric {{ $temperatureStatus[2] }} h-100"><div class="wp-metric-body"><div class="wp-label">Temperature</div><div class="wp-value">{{ number_format($predictions['temperature'], 2) }} <span class="wp-unit">&deg;C</span></div><div class="wp-status">{{ $temperatureStatus[0] }}</div><div class="wp-note">{{ $temperatureStatus[1] }}</div></div></div></div>
            <div class="col-md-6 col-xl-3"><div class="wp-metric {{ $humidityStatus[2] }} h-100"><div class="wp-metric-body"><div class="wp-label">Humidity</div><div class="wp-value">{{ number_format($predictions['humidity'], 2) }}<span class="wp-unit">%</span></div><div class="wp-status">{{ $humidityStatus[0] }}</div><div class="wp-note">{{ $humidityStatus[1] }}</div></div></div></div>
            <div class="col-md-6 col-xl-3"><div class="wp-metric {{ $windStatus[2] }} h-100"><div class="wp-metric-body"><div class="wp-label">Wind Speed</div><div class="wp-value">{{ number_format($predictions['wind_speed'], 2) }}</div><div class="wp-status">{{ $windStatus[0] }}</div><div class="wp-note">{{ $windStatus[1] }}</div></div></div></div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-7">
                <div class="wp-panel h-100 tone-green">
                    <div class="wp-panel-header"><h2 class="h5 fw-bold mb-1">Weather Prediction Summary</h2><div class="small text-muted">Target: {{ $targetLabel }} | Season: {{ $predictions['season'] }}</div></div>
                    <div class="wp-panel-body">
                        <p class="text-muted mb-3">{{ $result['message'] }}</p>
                        <div class="row g-3">
                            <div class="col-sm-4"><div class="wp-advisory h-100"><div class="wp-label">Monthly Samples</div><div class="h3 fw-bold mb-0 mt-2">{{ $result['months_available'] }}</div></div></div>
                            <div class="col-sm-4"><div class="wp-advisory h-100"><div class="wp-label">Trees Per Metric</div><div class="h3 fw-bold mb-0 mt-2">{{ $result['training']['rainfall']['trees'] }}</div></div></div>
                            <div class="col-sm-4"><div class="wp-advisory h-100"><div class="wp-label">Forecast Confidence</div><div class="h3 fw-bold mb-0 mt-2">{{ $weatherConfidence['value'] }}%</div><div class="wp-note">{{ $weatherConfidence['label'] }}</div></div></div>
                        </div>
                        @if($weatherInsights)
                            <ul class="smart-list mt-3">
                                @foreach($weatherInsights as $insight)
                                    <li>{{ $insight }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="wp-panel h-100 tone-blue">
                    <div class="wp-panel-header"><h2 class="h5 fw-bold mb-1">Weather Model Features</h2><div class="small text-muted">Signals used by the Laravel weather model</div></div>
                    <div class="wp-panel-body">
                        @foreach (['Month cycle', 'Wet/Dry season', 'Previous rainfall', 'Previous temperature', 'Previous humidity', 'Previous wind speed', '3-month rolling averages', 'Time index'] as $feature)
                            <span class="wp-feature-pill">{{ $feature }}</span>
                        @endforeach
                        <div class="mt-3">
                            @foreach($result['training'] as $metric => $training)
                                <div class="d-flex justify-content-between gap-2 py-1 border-bottom">
                                    <span class="small fw-bold text-capitalize">{{ str_replace('_', ' ', $metric) }}</span>
                                    <span class="small text-muted">{{ $training['stability']['label'] }} history</span>
                                </div>
                            @endforeach
                        </div>
                        <p class="small text-muted mt-3 mb-0">Forecast values are generated from local historical climate records.</p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="wp-panel mb-4 tone-green">
        <div class="wp-panel-header">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                <div><h2 class="h5 fw-bold mb-1">Rice Yield Prediction Form</h2><div class="small text-muted">Use the forecast as a starting point, then adjust the scenario before predicting yield.</div></div>
                <div class="d-flex flex-wrap gap-2"><button type="button" class="wp-preset" data-preset="rainfed">Rainfed field</button><button type="button" class="wp-preset" data-preset="irrigated">Irrigated field</button><button type="button" class="wp-preset" data-preset="stress">Weather stress</button></div>
            </div>
        </div>
        <div class="wp-panel-body">
            <form method="POST" action="{{ route('weather-predictions.predict') }}" data-loading="true" id="yieldPredictionForm">
                @csrf
                <div class="row g-3 mb-4">
                    <div class="col-md-6 col-xl-3"><div class="wp-input-card"><div class="wp-input-row"><div><label class="wp-label" for="rainfall">Rainfall</label><div class="wp-help">Monthly rainfall in mm</div></div><input id="rainfall" type="number" step="0.01" name="rainfall" class="form-control" value="{{ $rainfall }}" required data-sync="rainfallRange"></div><input id="rainfallRange" type="range" min="0" max="500" step="1" value="{{ $rainfall }}" data-sync="rainfall"></div></div>
                    <div class="col-md-6 col-xl-3"><div class="wp-input-card"><div class="wp-input-row"><div><label class="wp-label" for="temp_avg">Avg Temperature</label><div class="wp-help">Average temperature in C</div></div><input id="temp_avg" type="number" step="0.01" name="temp_avg" class="form-control" value="{{ $tempAvg }}" required data-sync="tempAvgRange"></div><input id="tempAvgRange" type="range" min="18" max="40" step=".1" value="{{ $tempAvg }}" data-sync="temp_avg"></div></div>
                    <div class="col-md-6 col-xl-3"><div class="wp-input-card"><div class="wp-input-row"><div><label class="wp-label" for="temp_range">Temp Range</label><div class="wp-help">Daily high-low spread</div></div><input id="temp_range" type="number" step="0.01" name="temp_range" class="form-control" value="{{ $tempRange }}" required data-sync="tempRangeSlider"></div><input id="tempRangeSlider" type="range" min="0" max="18" step=".1" value="{{ $tempRange }}" data-sync="temp_range"></div></div>
                    <div class="col-md-6 col-xl-3"><div class="wp-input-card"><div class="wp-input-row"><div><label class="wp-label" for="area">Farm Area</label><div class="wp-help">Area used by the model</div></div><input id="area" type="number" step="0.01" name="area" class="form-control" value="{{ $area }}" required data-sync="areaRange"></div><input id="areaRange" type="range" min="1" max="500" step="1" value="{{ $area }}" data-sync="area"></div></div>
                </div>

                <div class="wp-panel mb-4 tone-amber">
                    <div class="wp-panel-header"><button class="btn btn-link p-0 fw-bold text-decoration-none text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#advancedWeatherInputs" aria-expanded="false" aria-controls="advancedWeatherInputs">Advanced weather memory inputs</button><div class="small text-muted">These help the model understand recent and seasonal patterns.</div></div>
                    <div class="collapse" id="advancedWeatherInputs">
                        <div class="wp-panel-body">
                            <div class="row g-3">
                                <div class="col-md-3"><label class="form-label fw-bold">Previous Rainfall</label><input type="number" step="0.01" name="previous_rainfall" class="form-control" value="{{ $previousRainfall }}" required></div>
                                <div class="col-md-3"><label class="form-label fw-bold">Previous Temperature</label><input type="number" step="0.01" name="previous_temp" class="form-control" value="{{ $previousTemp }}" required></div>
                                <div class="col-md-3"><label class="form-label fw-bold">6-Month Rainfall</label><input type="number" step="0.01" name="rainfall_6m" class="form-control" value="{{ $rainfall6m }}" required></div>
                                <div class="col-md-3"><label class="form-label fw-bold">3-Month Temperature</label><input type="number" step="0.01" name="temp_3m" class="form-control" value="{{ $temp3m }}" required></div>
                                <div class="col-md-3"><label class="form-label fw-bold">6-Month Temperature</label><input type="number" step="0.01" name="temp_6m" class="form-control" value="{{ $temp6m }}" required></div>
                                <div class="col-md-3"><label class="form-label fw-bold">Seasonal Rainfall</label><input type="number" step="0.01" name="seasonal_rainfall" class="form-control" value="{{ $seasonalRainfall }}" required></div>
                                <div class="col-md-3"><label class="form-label fw-bold">Seasonal Temperature</label><input type="number" step="0.01" name="seasonal_temp" class="form-control" value="{{ $seasonalTemp }}" required></div>
                                <div class="col-md-3"><label class="form-label fw-bold">Season</label><select name="season" class="form-select" required><option value="Wet" @selected($seasonValue === 'Wet')>Wet</option><option value="Dry" @selected($seasonValue === 'Dry')>Dry</option></select></div>
                                <div class="col-md-3"><label class="form-label fw-bold">Farm Type</label><select name="farm_type" class="form-select" required><option value="Rainfed" @selected($farmTypeValue === 'Rainfed')>Rainfed</option><option value="Irrigated" @selected($farmTypeValue === 'Irrigated')>Irrigated</option></select></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-column flex-sm-row gap-2 align-items-sm-center"><button class="btn btn-primary fw-bold px-4" type="submit" data-loading-text="Predicting...">Predict Rice Yield</button><span class="small text-muted">The prediction uses <code>python_scripts/predict.py</code> and the trained rice yield model.</span></div>
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
            $confidence = $decision['confidence'] ?? null;
            $stressFactors = $decision['stress_factors'] ?? [];
        @endphp
        <div class="wp-panel mb-4 {{ $yieldTone }}">
            <div class="wp-panel-header"><h2 class="h5 fw-bold mb-1">Rice Yield Prediction Result</h2><div class="small text-muted">Output from Python model and decision-support rules</div></div>
            <div class="wp-panel-body">
                <div class="row g-4">
                    <div class="col-lg-4"><div class="wp-result-card h-100"><div class="wp-label">Predicted Rice Yield</div><div class="wp-value">{{ number_format($yield, 2) }}</div><div class="wp-unit">tons/hectare</div><div class="wp-score-bar mt-3" style="--score: {{ $yieldScore }}%;"><span></span></div><div class="wp-note">Higher bars indicate stronger yield outlook for the submitted scenario.</div></div></div>
                    <div class="col-lg-8">
                        <div class="row g-3">
                            <div class="col-md-6"><div class="wp-advisory h-100"><div class="wp-label mb-2">Planting Advisory</div><div class="fw-bold text-success">{{ $mlResult['planting_advisory'] }}</div></div></div>
                            <div class="col-md-6"><div class="wp-advisory h-100"><div class="wp-label mb-2">Irrigation Recommendation</div><div class="fw-bold text-primary">{{ $mlResult['irrigation_recommendation'] }}</div></div></div>
                            <div class="col-12"><div class="wp-advisory"><div class="wp-label mb-2">Notifications</div>@if(! empty($mlResult['notifications']))<ul class="mb-0">@foreach($mlResult['notifications'] as $notification)<li>{{ $notification }}</li>@endforeach</ul>@else<p class="mb-0 text-muted">No warning notification generated.</p>@endif</div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($decision)
            <div class="wp-panel mb-4 {{ $scoreTone }}">
                <div class="wp-panel-header">
                    <h2 class="h5 fw-bold mb-1">Decision Support Recommendation</h2>
                    <div class="small text-muted">Rule-based advisory modules using the ML yield result, forecast rainfall, season, farm type, humidity, and wind speed.</div>
                </div>
                <div class="wp-panel-body">
                    <div class="ds-grid mb-3">
                        <div class="ds-card primary">
                            <div class="ds-title">Suitability Score</div>
                            <div class="wp-value">{{ $decision['score']['value'] }}</div>
                            <div class="wp-score-bar mt-3" style="--score: {{ $decision['score']['value'] }}%;"><span></span></div>
                            <div class="ds-note">{{ $decision['score']['interpretation'] }}</div>
                        </div>
                        <div class="ds-card">
                            <div class="ds-title">Risk Level</div>
                            <div class="ds-value"><span class="ds-pill">{{ $decision['risk']['label'] }}</span></div>
                            <div class="ds-note">Fast summary for deciding how closely the farm condition should be monitored.</div>
                        </div>
                        <div class="ds-card">
                            <div class="ds-title">Weather</div>
                            <div class="ds-value">{{ $decision['weather']['summary'] }}</div>
                            <div class="ds-note">Rainfall: {{ number_format($decision['weather']['rainfall'], 2) }} mm | Humidity: {{ number_format($decision['weather']['humidity'], 2) }}% | Wind: {{ number_format($decision['weather']['wind_speed'], 2) }}</div>
                        </div>
                        <div class="ds-card">
                            <div class="ds-title">Recommendation Confidence</div>
                            <div class="ds-value">{{ $confidence['value'] ?? 0 }}%</div>
                            <div class="ds-note">{{ $confidence['label'] ?? 'Not available' }}. {{ $confidence['note'] ?? '' }}</div>
                        </div>
                        <div class="ds-card">
                            <div class="ds-title">Planting Advisory</div>
                            <div class="ds-value">{{ $decision['planting']['recommendation'] }}</div>
                        </div>
                        <div class="ds-card">
                            <div class="ds-title">Irrigation Recommendation</div>
                            <div class="ds-value">{{ $decision['irrigation']['recommendation'] }}</div>
                        </div>
                        <div class="ds-card">
                            <div class="ds-title">Yield Advisory</div>
                            <div class="ds-value">{{ $decision['yield']['advisory'] }}</div>
                            <div class="ds-note">{{ $decision['yield']['predicted_yield'] !== null ? number_format($decision['yield']['predicted_yield'], 2).' tons/hectare' : 'No yield prediction available.' }}</div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="ds-card h-100">
                                <div class="ds-title">Detected Stress Factors</div>
                                @if($stressFactors)
                                    <div class="d-grid gap-2 mt-2">
                                        @foreach($stressFactors as $factor)
                                            <div class="stress-chip {{ strtolower($factor['severity']) }}">
                                                <strong>{{ $factor['label'] }} - {{ $factor['severity'] }}</strong>
                                                <span class="small text-muted">{{ $factor['detail'] }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="ds-value">No major stress factor detected.</div>
                                    <div class="ds-note">Current inputs are within the normal planning range.</div>
                                @endif
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="ds-card h-100">
                                <div class="ds-title">Notifications</div>
                                @if(! empty($decision['notifications']))
                                    <ul class="ds-notifications">
                                        @foreach($decision['notifications'] as $notification)
                                            <li>{{ $notification }}</li>
                                        @endforeach
                                    </ul>
                                @else
                                    <div class="ds-value">No urgent notification.</div>
                                    <div class="ds-note">Current inputs do not trigger a weather, irrigation, planting, or yield warning.</div>
                                @endif
                            </div>
                        </div>
                        <div class="col-lg-12">
                            <div class="ds-card h-100 primary">
                                <div class="ds-title">Overall Recommendation</div>
                                <div class="ds-note mt-2"><strong>Weather:</strong> {{ $decision['overall_recommendation']['weather'] }}</div>
                                <div class="ds-note"><strong>Yield:</strong> {{ $decision['overall_recommendation']['yield'] }}</div>
                                <div class="ds-note"><strong>Planting:</strong> {{ $decision['overall_recommendation']['planting'] }}</div>
                                <div class="ds-note"><strong>Irrigation:</strong> {{ $decision['overall_recommendation']['irrigation'] }}</div>
                                <div class="ds-note"><strong>Risk:</strong> {{ $decision['overall_recommendation']['risk_level'] }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-sync]').forEach((control) => {
                const target = document.getElementById(control.dataset.sync);
                if (!target) return;
                control.addEventListener('input', () => { target.value = control.value; });
            });

            const presets = {
                rainfed: { rainfall: 180, temp_avg: 29, temp_range: 8, area: 120, previous_rainfall: 150, previous_temp: 28.5, rainfall_6m: 170, temp_3m: 29, temp_6m: 28.8, seasonal_rainfall: 900, seasonal_temp: 29, season: 'Wet', farm_type: 'Rainfed' },
                irrigated: { rainfall: 120, temp_avg: 28.5, temp_range: 7, area: 120, previous_rainfall: 100, previous_temp: 28, rainfall_6m: 140, temp_3m: 28.7, temp_6m: 28.5, seasonal_rainfall: 720, seasonal_temp: 28.7, season: 'Dry', farm_type: 'Irrigated' },
                stress: { rainfall: 85, temp_avg: 33, temp_range: 11, area: 120, previous_rainfall: 70, previous_temp: 32, rainfall_6m: 95, temp_3m: 32.5, temp_6m: 31.8, seasonal_rainfall: 520, seasonal_temp: 32.2, season: 'Dry', farm_type: 'Rainfed' }
            };

            const setField = (name, value) => {
                const field = document.querySelector(`[name="${name}"]`);
                if (!field) return;
                field.value = value;
                const synced = field.dataset.sync ? document.getElementById(field.dataset.sync) : null;
                if (synced) synced.value = value;
            };

            document.querySelectorAll('[data-preset]').forEach((button) => {
                button.addEventListener('click', () => {
                    const preset = presets[button.dataset.preset];
                    if (!preset) return;
                    Object.entries(preset).forEach(([name, value]) => setField(name, value));
                });
            });
        });
    </script>
</x-app-layout>
