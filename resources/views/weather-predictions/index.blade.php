<x-app-layout>
    <style>
        .prediction-hero {
            position: relative;
            overflow: hidden;
            border-radius: 8px;
            padding: 1.35rem;
            margin-bottom: 1.25rem;
            color: #fff;
            background:
                radial-gradient(circle at 86% 14%, rgba(82,183,136,.26), transparent 30%),
                linear-gradient(135deg, #0d1f18 0%, #146b78 48%, #0d6a41 100%);
            box-shadow: 0 1rem 2.3rem rgba(13,31,24,.18);
        }

        .prediction-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, rgba(255,255,255,.08) 1px, transparent 1px),
                linear-gradient(0deg, rgba(255,255,255,.06) 1px, transparent 1px);
            background-size: 38px 38px;
            mask-image: linear-gradient(90deg, rgba(0,0,0,.78), transparent 88%);
        }

        .prediction-hero::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 7px;
            background: linear-gradient(90deg, #f4b63f, #52b788, #1677b8);
        }

        .prediction-hero > * {
            position: relative;
            z-index: 1;
        }

        .prediction-eyebrow {
            font-size: .72rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: rgba(255,255,255,.68);
        }

        .prediction-hero h1 {
            color: #fff;
        }

        .prediction-hero p {
            color: rgba(255,255,255,.66);
            max-width: 780px;
        }

        .prediction-card {
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(153,185,160,.72);
            border-radius: 8px;
            background: linear-gradient(145deg, rgba(244,250,239,.97), rgba(229,242,226,.97));
            box-shadow: 0 .9rem 2rem rgba(20,32,51,.07);
        }

        .prediction-card::before {
            content: "";
            position: absolute;
            inset: 0 0 auto;
            height: 5px;
            background: var(--accent, #52b788);
        }

        .prediction-card-body {
            padding: 1rem;
            position: relative;
            z-index: 1;
        }

        .prediction-label {
            color: #5f7569;
            font-size: .72rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .prediction-value {
            margin-top: .35rem;
            font-size: clamp(1.65rem, 3vw, 2.3rem);
            line-height: 1;
            font-weight: 900;
            color: #0d1f18;
        }

        .prediction-note {
            color: #5f7569;
            font-size: .84rem;
            margin-top: .65rem;
        }

        .tone-green { --accent: #52b788; }
        .tone-blue { --accent: #1677b8; }
        .tone-gold { --accent: #f4b63f; }
        .tone-red { --accent: #d85b45; }

        .model-panel {
            border: 1px solid rgba(153,185,160,.72);
            border-radius: 8px;
            background: linear-gradient(145deg, rgba(244,250,239,.97), rgba(232,244,230,.97));
            box-shadow: 0 .9rem 2rem rgba(20,32,51,.07);
            overflow: hidden;
        }

        .model-panel-header {
            padding: 1rem;
            border-bottom: 1px solid rgba(153,185,160,.58);
            background: rgba(226,241,219,.58);
        }

        .model-panel-body {
            padding: 1rem;
        }

        .feature-pill {
            display: inline-flex;
            border-radius: 999px;
            padding: .35rem .65rem;
            background: #d8f3dc;
            color: #1f6f4a;
            font-size: .76rem;
            font-weight: 800;
            margin: .18rem;
        }

        .form-section-title {
            font-size: .82rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #2d6a4f;
            margin-bottom: .75rem;
        }

        .result-box {
            border-radius: 8px;
            background: rgba(255,255,255,.65);
            border: 1px solid rgba(153,185,160,.55);
            padding: 1rem;
        }
    </style>

    <section class="prediction-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-4 align-items-xl-end">
            <div>
                <div class="prediction-eyebrow mb-2">Random Forest Machine Learning</div>
                <h1 class="h2 fw-bold mb-2">Monthly Weather Prediction and Rice Yield</h1>
                <p class="mb-0">
                    Predict next-month climate conditions, estimate rice yield per hectare, and generate planting,
                    irrigation, and notification recommendations for climate-informed farming decisions.
                </p>
            </div>

            <form method="GET" action="{{ route('weather-predictions.index') }}" class="d-flex flex-column flex-sm-row gap-2 align-items-sm-end">
                <div>
                    <label for="target_month" class="form-label text-white-50 small fw-bold mb-1">Target Month</label>
                    <input id="target_month" name="target_month" type="month" class="form-control" value="{{ $targetMonth->format('Y-m') }}">
                </div>

                <button class="btn btn-light fw-bold" type="submit">
                    Predict Weather
                </button>
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
    @elseif (isset($result) && $result && $result['ready'])
        <div class="row g-4 mb-4">
            <div class="col-md-6 col-xl-3">
                <div class="prediction-card tone-blue h-100">
                    <div class="prediction-card-body">
                        <div class="prediction-label">Rainfall</div>
                        <div class="prediction-value">{{ number_format($result['predictions']['rainfall'], 2) }}</div>
                        <div class="prediction-note">Predicted monthly rainfall in mm</div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="prediction-card tone-green h-100">
                    <div class="prediction-card-body">
                        <div class="prediction-label">Temperature</div>
                        <div class="prediction-value">{{ number_format($result['predictions']['temperature'], 2) }} C</div>
                        <div class="prediction-note">Predicted average temperature</div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="prediction-card tone-gold h-100">
                    <div class="prediction-card-body">
                        <div class="prediction-label">Humidity</div>
                        <div class="prediction-value">{{ number_format($result['predictions']['humidity'], 2) }}%</div>
                        <div class="prediction-note">Predicted average humidity</div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="prediction-card tone-red h-100">
                    <div class="prediction-card-body">
                        <div class="prediction-label">Wind Speed</div>
                        <div class="prediction-value">{{ number_format($result['predictions']['wind_speed'], 2) }}</div>
                        <div class="prediction-note">Predicted average wind speed</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-7">
                <div class="model-panel h-100">
                    <div class="model-panel-header">
                        <h2 class="h5 fw-bold mb-1">Weather Prediction Summary</h2>
                        <div class="small text-muted">
                            Target: {{ $targetMonth->format('F Y') }}
                            |
                            Season: {{ $result['predictions']['season'] }}
                        </div>
                    </div>

                    <div class="model-panel-body">
                        <p class="text-muted mb-3">{{ $result['message'] }}</p>

                        <div class="row g-3">
                            <div class="col-sm-4">
                                <div class="result-box h-100">
                                    <div class="prediction-label">Monthly Samples</div>
                                    <div class="h3 fw-bold mb-0 mt-2">{{ $result['months_available'] }}</div>
                                </div>
                            </div>

                            <div class="col-sm-4">
                                <div class="result-box h-100">
                                    <div class="prediction-label">Trees Per Metric</div>
                                    <div class="h3 fw-bold mb-0 mt-2">{{ $result['training']['rainfall']['trees'] }}</div>
                                </div>
                            </div>

                            <div class="col-sm-4">
                                <div class="result-box h-100">
                                    <div class="prediction-label">Training Rows</div>
                                    <div class="h3 fw-bold mb-0 mt-2">{{ $result['training']['rainfall']['samples'] }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="model-panel h-100">
                    <div class="model-panel-header">
                        <h2 class="h5 fw-bold mb-1">Weather Model Features</h2>
                        <div class="small text-muted">Signals used by the Laravel weather model</div>
                    </div>

                    <div class="model-panel-body">
                        @foreach (['Month cycle', 'Wet/Dry season', 'Previous rainfall', 'Previous temperature', 'Previous humidity', 'Previous wind speed', '3-month rolling averages', 'Time index'] as $feature)
                            <span class="feature-pill">{{ $feature }}</span>
                        @endforeach

                        <p class="small text-muted mt-3 mb-0">
                            This weather forecast is generated from local historical climate records.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="model-panel mb-4">
        <div class="model-panel-header">
            <h2 class="h5 fw-bold mb-1">Rice Yield Prediction Form</h2>
            <div class="small text-muted">
                This form sends values to <code>python_scripts/predict.py</code> and uses the trained <code>rice_yield_model_final.pkl</code>.
            </div>
        </div>

        <div class="model-panel-body">
            <form method="POST" action="{{ route('weather-predictions.predict') }}">
                @csrf

                <div class="form-section-title">Current Weather and Farm Inputs</div>

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Rainfall</label>
                        <input type="number" step="0.01" name="rainfall" class="form-control" value="{{ old('rainfall', $result['predictions']['rainfall'] ?? 180) }}" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Average Temperature</label>
                        <input type="number" step="0.01" name="temp_avg" class="form-control" value="{{ old('temp_avg', $result['predictions']['temperature'] ?? 29) }}" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Temperature Range</label>
                        <input type="number" step="0.01" name="temp_range" class="form-control" value="{{ old('temp_range', 8) }}" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Farm Area</label>
                        <input type="number" step="0.01" name="area" class="form-control" value="{{ old('area', 120) }}" required>
                    </div>
                </div>

                <div class="form-section-title">Previous and Seasonal Weather Features</div>

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Previous Rainfall</label>
                        <input type="number" step="0.01" name="previous_rainfall" class="form-control" value="{{ old('previous_rainfall', 150) }}" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Previous Temperature</label>
                        <input type="number" step="0.01" name="previous_temp" class="form-control" value="{{ old('previous_temp', 28.5) }}" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">6-Month Rainfall</label>
                        <input type="number" step="0.01" name="rainfall_6m" class="form-control" value="{{ old('rainfall_6m', 170) }}" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">3-Month Temperature</label>
                        <input type="number" step="0.01" name="temp_3m" class="form-control" value="{{ old('temp_3m', 29) }}" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">6-Month Temperature</label>
                        <input type="number" step="0.01" name="temp_6m" class="form-control" value="{{ old('temp_6m', 28.8) }}" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Seasonal Rainfall</label>
                        <input type="number" step="0.01" name="seasonal_rainfall" class="form-control" value="{{ old('seasonal_rainfall', 900) }}" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Seasonal Temperature</label>
                        <input type="number" step="0.01" name="seasonal_temp" class="form-control" value="{{ old('seasonal_temp', 29) }}" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Season</label>
                        <select name="season" class="form-select" required>
                            <option value="Wet" @selected(old('season', $result['predictions']['season'] ?? 'Wet') === 'Wet')>Wet</option>
                            <option value="Dry" @selected(old('season', $result['predictions']['season'] ?? 'Wet') === 'Dry')>Dry</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Farm Type</label>
                        <select name="farm_type" class="form-select" required>
                            <option value="Rainfed" @selected(old('farm_type', 'Rainfed') === 'Rainfed')>Rainfed</option>
                            <option value="Irrigated" @selected(old('farm_type') === 'Irrigated')>Irrigated</option>
                        </select>
                    </div>
                </div>

                <button class="btn btn-success fw-bold px-4" type="submit">
                    Predict Rice Yield
                </button>
            </form>
        </div>
    </div>

    @if(isset($mlResult) && $mlResult)
        <div class="model-panel mb-4">
            <div class="model-panel-header">
                <h2 class="h5 fw-bold mb-1">Rice Yield Prediction Result</h2>
                <div class="small text-muted">
                    Output from Python model and decision-support rules
                </div>
            </div>

            <div class="model-panel-body">
                <div class="row g-4">
                    <div class="col-lg-4">
                        <div class="prediction-card tone-green h-100">
                            <div class="prediction-card-body">
                                <div class="prediction-label">Predicted Rice Yield</div>
                                <div class="prediction-value">
                                    {{ number_format($mlResult['predicted_yield'], 2) }}
                                </div>
                                <div class="prediction-note">tons/hectare</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="result-box mb-3">
                            <div class="prediction-label mb-2">Planting Advisory</div>
                            <div class="fw-bold text-success">
                                {{ $mlResult['planting_advisory'] }}
                            </div>
                        </div>

                        <div class="result-box mb-3">
                            <div class="prediction-label mb-2">Irrigation Recommendation</div>
                            <div class="fw-bold text-primary">
                                {{ $mlResult['irrigation_recommendation'] }}
                            </div>
                        </div>

                        <div class="result-box">
                            <div class="prediction-label mb-2">Notifications</div>

                            @if(! empty($mlResult['notifications']))
                                <ul class="mb-0">
                                    @foreach($mlResult['notifications'] as $notification)
                                        <li>{{ $notification }}</li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="mb-0 text-muted">No warning notification generated.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-app-layout>
