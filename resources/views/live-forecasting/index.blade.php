<x-app-layout>
    <style>
        .lf-hero {
            position: relative;
            overflow: hidden;
            border-radius: 8px;
            padding: 1.35rem;
            margin-bottom: 1.25rem;
            color: #fff;
            background:
                linear-gradient(120deg, rgba(13,31,24,.96), rgba(26,58,42,.88)),
                url("https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1600&q=80") center/cover;
            box-shadow: 0 1rem 2.3rem rgba(13,31,24,.18);
        }
        .lf-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background: repeating-linear-gradient(130deg, rgba(255,255,255,.12) 0 1px, transparent 1px 24px);
            opacity: .8;
        }
        .lf-hero::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 7px;
            background: linear-gradient(90deg, #ffd166, #52b788, #95d5b2, #d85b45);
        }
        .lf-hero > * { position: relative; z-index: 1; }
        .lf-eyebrow {
            font-size: .72rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: rgba(255,255,255,.74);
        }
        .lf-map-shell {
            overflow: hidden;
            border: 1px solid rgba(212,237,218,.98);
            border-radius: 8px;
            background: #0d1f18;
            box-shadow: 0 .9rem 2rem rgba(13,31,24,.1);
        }
        .lf-map-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            padding: .85rem 1rem;
            background: linear-gradient(90deg, #fff, #f0f7f4);
            border-bottom: 1px solid rgba(212,237,218,.98);
        }
        .lf-map-frame {
            display: block;
            width: 100%;
            height: min(74vh, 760px);
            min-height: 560px;
            border: 0;
            background: #0d1f18;
        }
        .lf-meta-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .85rem;
            margin-top: 1rem;
        }
        .lf-meta {
            border: 1px solid rgba(212,237,218,.98);
            border-radius: 8px;
            padding: .9rem 1rem;
            background: linear-gradient(145deg, #fff, #f7fbf8);
        }
        .lf-label {
            color: #5a7a64;
            font-size: .72rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .lf-value {
            color: #0d1f18;
            font-size: 1.02rem;
            font-weight: 900;
            margin-top: .3rem;
        }
        .lf-barangay-panel {
            margin-top: 1rem;
            border: 1px solid rgba(212,237,218,.98);
            border-radius: 8px;
            padding: 1rem;
            background: linear-gradient(145deg, #fff, #f7fbf8);
        }
        .lf-barangay-list {
            display: flex;
            flex-wrap: wrap;
            gap: .45rem;
            margin-top: .85rem;
        }
        .lf-barangay-chip {
            border: 1px solid rgba(82,183,136,.34);
            border-radius: 999px;
            background: #fff;
            color: #1b2b23;
            padding: .42rem .68rem;
            font-size: .82rem;
            font-weight: 800;
            transition: transform .15s ease, border-color .15s ease, background-color .15s ease;
        }
        .lf-barangay-chip:hover,
        .lf-barangay-chip:focus {
            transform: translateY(-1px);
            border-color: #52b788;
            background: #f0f7f4;
        }
        .lf-popup-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .75rem;
        }
        .lf-popup-item {
            border: 1px solid rgba(212,237,218,.98);
            border-radius: 8px;
            background: #f7fbf8;
            padding: .8rem;
        }
        .lf-popup-note {
            border-left: 5px solid #52b788;
            border-radius: 8px;
            background: #f0f7f4;
            padding: .85rem 1rem;
        }
        .lf-model-panel {
            margin-top: 1rem;
            border: 1px solid rgba(212,237,218,.98);
            border-radius: 8px;
            background: linear-gradient(145deg, #fff, #f7fbf8);
            overflow: hidden;
            box-shadow: 0 .9rem 2rem rgba(13,31,24,.07);
        }
        .lf-model-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid rgba(212,237,218,.98);
            background: linear-gradient(90deg, #fff, #f0f7f4);
        }
        .lf-model-body { padding: 1rem; }
        .lf-model-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .85rem;
        }
        .lf-model-card {
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(212,237,218,.98);
            border-radius: 8px;
            background: #fff;
            padding: .9rem;
            min-height: 132px;
        }
        .lf-model-card::before {
            content: "";
            position: absolute;
            inset: 0 0 auto;
            height: 5px;
            background: var(--accent, #52b788);
        }
        .lf-model-number {
            color: #0d1f18;
            font-size: clamp(1.65rem, 3vw, 2.25rem);
            font-weight: 900;
            line-height: 1;
            margin-top: .5rem;
        }
        .lf-model-unit {
            color: #5a7a64;
            font-size: .86rem;
            font-weight: 800;
        }
        .lf-insight-list {
            display: grid;
            gap: .55rem;
            margin: 1rem 0 0;
            padding: 0;
            list-style: none;
        }
        .lf-insight-list li {
            border: 1px solid rgba(212,237,218,.98);
            border-radius: 8px;
            background: #fff;
            padding: .7rem .8rem;
            color: #1b2b23;
            font-weight: 700;
        }
        .lf-source-note {
            border-left: 5px solid #1677b8;
            border-radius: 8px;
            background: #eef7ff;
            color: #155a84;
            padding: .85rem 1rem;
            font-weight: 800;
        }
        @media (max-width: 991.98px) {
            .lf-map-frame { height: 68vh; min-height: 480px; }
            .lf-meta-grid { grid-template-columns: 1fr; }
            .lf-popup-grid { grid-template-columns: 1fr; }
            .lf-model-header { align-items: stretch; flex-direction: column; }
            .lf-model-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 575.98px) {
            .lf-map-frame { height: 72vh; min-height: 420px; }
            .lf-map-toolbar .btn { width: 100%; }
            .lf-model-grid { grid-template-columns: 1fr; }
        }
    </style>

    <section class="lf-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-4 align-items-xl-end">
            <div>
                <div class="lf-eyebrow mb-2">Windy.com + Trained Model</div>
                <h1 class="h2 fw-bold mb-2">Live Forecasting for Lian</h1>
                <p class="mb-0 text-white-50" style="max-width: 800px;">Windy shows the live weather map, while your trained iClimate model gives the upcoming Lian-only forecast for planning.</p>
            </div>
        </div>
    </section>

    <section class="lf-map-shell">
        <div class="lf-map-toolbar">
            <div>
                <div class="lf-label">Map Focus</div>
                <div class="fw-bold">Lian, Batangas</div>
            </div>
            <div class="text-muted small fw-semibold">
                Center {{ number_format($latitude, 4) }}, {{ number_format($longitude, 4) }} | Municipality-wide zoom {{ $zoom }}
            </div>
        </div>
        <iframe
            class="lf-map-frame"
            title="Windy.com live forecast map for all barangays in Lian, Batangas"
            src="{{ $windyUrl }}"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            allowfullscreen>
        </iframe>
    </section>

    <div class="lf-meta-grid">
        <div class="lf-meta">
            <div class="lf-label">Coverage</div>
            <div class="lf-value">Lian Prediction Only</div>
        </div>
        <div class="lf-meta">
            <div class="lf-label">Live Map</div>
            <div class="lf-value">Universal Windy View</div>
        </div>
        <div class="lf-meta">
            <div class="lf-label">Model Source</div>
            <div class="lf-value">Trained Random Forest</div>
        </div>
    </div>

    <section class="lf-model-panel">
        <div class="lf-model-header">
            <div>
                <div class="lf-label">Upcoming Lian Prediction</div>
                <div class="lf-value">{{ $targetMonth->format('F Y') }}</div>
            </div>
            <form method="GET" action="{{ route('live-forecasting.index') }}" class="d-flex flex-column flex-sm-row gap-2 align-items-sm-end">
                <div>
                    <label class="form-label small fw-bold text-muted mb-1" for="target_month">Target Month</label>
                    <input id="target_month" name="target_month" type="month" class="form-control" value="{{ $targetMonth->format('Y-m') }}">
                </div>
                <button class="btn btn-primary" type="submit">Update Model</button>
            </form>
        </div>
        <div class="lf-model-body">
            <div class="lf-source-note mb-3">Use Windy for live movement and use the trained model below for Lian-only upcoming planning. They support each other, but they are different sources.</div>

            @if($modelForecast['ready'] ?? false)
                @php($predictions = $modelForecast['predictions'])
                <div class="lf-model-grid">
                    <div class="lf-model-card" style="--accent:#1677b8;">
                        <div class="lf-label">Rainfall</div>
                        <div class="lf-model-number">{{ number_format($predictions['rainfall'] ?? 0, 2) }}</div>
                        <div class="lf-model-unit">mm</div>
                    </div>
                    <div class="lf-model-card" style="--accent:#d85b45;">
                        <div class="lf-label">Temperature</div>
                        <div class="lf-model-number">{{ number_format($predictions['temperature'] ?? 0, 2) }}</div>
                        <div class="lf-model-unit">C</div>
                    </div>
                    <div class="lf-model-card" style="--accent:#52b788;">
                        <div class="lf-label">Humidity</div>
                        <div class="lf-model-number">{{ number_format($predictions['humidity'] ?? 0, 2) }}</div>
                        <div class="lf-model-unit">%</div>
                    </div>
                    <div class="lf-model-card" style="--accent:#f4b63f;">
                        <div class="lf-label">Wind Speed</div>
                        <div class="lf-model-number">{{ number_format($predictions['wind_speed'] ?? 0, 2) }}</div>
                        <div class="lf-model-unit">km/h</div>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-4">
                        <div class="lf-popup-item h-100">
                            <div class="lf-label">Season</div>
                            <div class="lf-value">{{ $predictions['season'] ?? 'N/A' }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="lf-popup-item h-100">
                            <div class="lf-label">Confidence</div>
                            <div class="lf-value">{{ $modelForecast['confidence']['value'] ?? 0 }}%</div>
                            <div class="small text-muted fw-semibold mt-1">{{ $modelForecast['confidence']['label'] ?? 'Not available' }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="lf-popup-item h-100">
                            <div class="lf-label">Training Months</div>
                            <div class="lf-value">{{ $modelForecast['months_available'] ?? 0 }}</div>
                        </div>
                    </div>
                </div>

                @if(! empty($modelForecast['insights']))
                    <ul class="lf-insight-list">
                        @foreach($modelForecast['insights'] as $insight)
                            <li>{{ $insight }}</li>
                        @endforeach
                    </ul>
                @endif
            @else
                <div class="alert alert-warning mb-0">
                    <strong>Model not ready.</strong>
                    {{ $modelForecast['message'] ?? 'Add more climate records to generate trained model predictions.' }}
                    Current monthly samples: {{ $modelForecast['months_available'] ?? 0 }}.
                </div>
            @endif
        </div>
    </section>

    <section class="lf-barangay-panel">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-2">
            <div>
                <div class="lf-label">Barangays Included</div>
                <div class="lf-value">Select an area for details</div>
            </div>
            <div class="small text-muted fw-semibold">{{ count($barangays) }} barangays are included in the Lian-only prediction context.</div>
        </div>
        <div class="lf-barangay-list" aria-label="Lian barangays included in live forecasting">
            @foreach ($barangays as $barangay)
                <button class="lf-barangay-chip" type="button" data-barangay="{{ $barangay }}" data-bs-toggle="modal" data-bs-target="#barangayForecastModal">{{ $barangay }}</button>
            @endforeach
        </div>
    </section>

    <div class="modal fade" id="barangayForecastModal" tabindex="-1" aria-labelledby="barangayForecastModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0" style="border-radius: 8px;">
                <div class="modal-header" style="background: linear-gradient(90deg, #0d1f18, #1a3a2a); color: #fff;">
                    <div>
                        <div class="lf-eyebrow">Selected Lian Area</div>
                        <h2 class="modal-title h5 fw-bold" id="barangayForecastModalLabel">Barangay</h2>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="lf-popup-note mb-3">
                        <strong>Friendly reminder:</strong> Windy is a live universal weather map, but iClimate uses this selection only for Lian-focused forecast guidance.
                    </div>
                    <div class="lf-popup-grid mb-3">
                        <div class="lf-popup-item">
                            <div class="lf-label">Risk Level</div>
                            <div class="lf-value" data-popup-field="risk_level">For monitoring</div>
                        </div>
                        <div class="lf-popup-item">
                            <div class="lf-label">Main Watch</div>
                            <div class="lf-value" data-popup-field="risk_type">Live weather watch</div>
                        </div>
                        <div class="lf-popup-item">
                            <div class="lf-label">Coordinates</div>
                            <div class="lf-value" data-popup-field="coordinates">Lian, Batangas</div>
                        </div>
                        <div class="lf-popup-item">
                            <div class="lf-label">Rainfall Status</div>
                            <div class="lf-value" data-popup-field="rainfall_status">Check live rain layer.</div>
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        <div>
                            <div class="lf-label">Planting Guidance</div>
                            <p class="mb-0 text-muted fw-semibold" data-popup-field="planting_advisory">Use the Lian-only upcoming prediction before making planting decisions.</p>
                        </div>
                        <div>
                            <div class="lf-label">Irrigation Guidance</div>
                            <p class="mb-0 text-muted fw-semibold" data-popup-field="irrigation_recommendation">Confirm field moisture locally and monitor rainfall movement on the live map.</p>
                        </div>
                        <div>
                            <div class="lf-label">Local Note</div>
                            <p class="mb-0 text-muted fw-semibold" data-popup-field="description">This barangay is included in the Lian forecast scope.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a class="btn btn-outline-primary" href="{{ route('weather-predictions.index') }}">Open Upcoming Prediction</a>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Got it</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const barangayDetails = @json($barangayDetails);
            const modal = document.getElementById('barangayForecastModal');
            const title = document.getElementById('barangayForecastModalLabel');
            const setField = (field, value) => {
                const node = modal?.querySelector(`[data-popup-field="${field}"]`);
                if (node) node.textContent = value || 'For monitoring';
            };

            document.querySelectorAll('[data-barangay]').forEach((button) => {
                button.addEventListener('click', () => {
                    const barangay = button.dataset.barangay;
                    const detail = barangayDetails[barangay] || {};
                    const lat = Number(detail.latitude);
                    const lon = Number(detail.longitude);
                    const coordinates = Number.isFinite(lat) && Number.isFinite(lon)
                        ? `${lat.toFixed(4)}, ${lon.toFixed(4)}`
                        : 'Lian, Batangas';

                    if (title) title.textContent = barangay;
                    setField('risk_level', detail.risk_level);
                    setField('risk_type', detail.risk_type);
                    setField('coordinates', coordinates);
                    setField('rainfall_status', detail.rainfall_status);
                    setField('planting_advisory', detail.planting_advisory);
                    setField('irrigation_recommendation', detail.irrigation_recommendation);
                    setField('description', detail.description);
                });
            });
        });
    </script>
</x-app-layout>
