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
    @vite('resources/css/pages/weather-predictions.css')

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
    {{-- Staff accounts (IT and MAO) get the IT dashboard design system: .mfd / .itd parts plus it-pages.css. --}}
    <div @class(['wp-page', 'mfd itd itp' => ! $isFarmer])>
    @unless($isFarmer)
    <x-it.page-header kicker="Rice Yield and Weather Forecasts" title="Predictions" lede="Expected rice yield and expected weather for Lian. These are forecasts, not guarantees." />
    @endunless

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
                        @if($yieldReliability)<div class="col-md-4"><div class="wp-advisory h-100"><div class="wp-label mb-1">Forecast Reliability</div><div class="wp-card-value">{{ $yieldReliability }}</div><div class="ds-note">A rough guide only, not a guarantee.</div></div></div>@endif
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
                    @if($weatherReliability)<div class="small text-muted mt-2">Forecast Reliability: <strong>{{ $weatherReliability }}</strong>. This is a forecast, not a guarantee.</div>@endif
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
                    <div class="small text-muted mt-3">Only days with a forecast show values. Other days are left blank.</div>
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
