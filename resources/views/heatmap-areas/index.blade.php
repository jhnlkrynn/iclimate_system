@php
    $mapPayload = $mapAreas->map(fn ($area) => [
        'id' => $area->id,
        'barangay' => $area->barangay,
        'latitude' => (float) $area->latitude,
        'longitude' => (float) $area->longitude,
        'risk_level' => $area->risk_level,
        'risk_type' => $area->risk_type,
        'risk_score' => (float) $area->risk_score,
        'predicted_yield' => $area->predicted_yield !== null ? (float) $area->predicted_yield : null,
        'predicted_yield_source' => str_contains(strtolower((string) $area->description), 'trained rice yield model') ? 'Trained rice yield model' : 'Stored production record',
        'rainfall_status' => $area->rainfall_status,
        'planting_advisory' => $area->planting_advisory,
        'irrigation_recommendation' => $area->irrigation_recommendation,
        'description' => $area->description,
    ])->values();
    $riskSource = optional($mapAreas->first())->description;
    $priorityAreas = $mapAreas->sortByDesc('risk_score')->take(5)->values();
    $topPriority = $priorityAreas->first();
@endphp

<x-app-layout>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        .heatmap-grid { display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(320px, .8fr); gap: 1rem; align-items: stretch; }
        .heatmap-main { display: flex; flex-direction: column; gap: 1rem; min-height: 100%; }
        .map-shell { position: relative; overflow: hidden; display: flex; flex: 1 1 auto; flex-direction: column; border: 1.5px solid #e8e0d0; border-radius: 18px; background: #fff; box-shadow: 0 .9rem 2rem rgba(13,31,24,.07); min-height: 0; }
        #barangayRiskMap {
            flex: 1 1 auto;
            height: auto;
            min-height: 560px;
            background:
                radial-gradient(circle at 20% 30%, rgba(16, 77, 196, .45), transparent 20rem),
                radial-gradient(circle at 70% 38%, rgba(255, 24, 18, .35), transparent 18rem),
                linear-gradient(135deg, #0736a8, #15cfe0 28%, #59f03d 48%, #fff118 62%, #ff850f 78%, #f71912);
        }
        .thermo-map .leaflet-tile-pane { filter: saturate(1.65) contrast(1.05); opacity: .22; }
        .thermo-map .leaflet-overlay-pane canvas {
            mix-blend-mode: multiply;
            filter: saturate(1.55) contrast(1.08);
        }
        .thermo-point {
            width: 12px;
            height: 12px;
            border-radius: 999px;
            border: 2px solid rgba(13,31,24,.78);
            background: rgba(255,255,255,.86);
            box-shadow: 0 0 0 5px rgba(255,255,255,.22), 0 .35rem .8rem rgba(13,31,24,.24);
        }
        .map-insight-strip { display: grid; grid-template-columns: 1.15fr .85fr; gap: 1rem; margin-bottom: 1rem; align-items: start; }
        .map-insight { position: relative; overflow: hidden; border: 1.5px solid #e8e0d0; border-radius: 18px; background: linear-gradient(145deg, #fff, #f7fbf8); padding: .85rem; box-shadow: 0 .8rem 1.8rem rgba(13,31,24,.06); }
        .map-insight::before { content: ""; position: absolute; inset: 0 0 auto; height: 5px; background: var(--accent, #52b788); }
        .map-summary-card .risk-value { font-size: 1.55rem; line-height: 1.12; margin-top: .32rem; }
        .map-summary-card .risk-help { margin-top: .35rem; }
        .priority-list { display: grid; grid-template-columns: 1fr; gap: .42rem; margin: 0; padding: 0; list-style: none; }
        .priority-item { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: .55rem; align-items: center; border: 1px solid #d4edda; border-radius: 8px; background: #fff; padding: .5rem .58rem; }
        .priority-item .fw-bold, .priority-item .small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .priority-score { min-width: 3.6rem; text-align: center; border-radius: 999px; padding: .35rem .55rem; background: var(--chip-bg, #d8f3dc); color: var(--chip-color, #2d6a4f); font-weight: 900; }
        .map-toolbar { position: absolute; z-index: 500; left: 1rem; right: 1rem; top: 1rem; display: flex; gap: .5rem; flex-wrap: wrap; pointer-events: none; }
        .map-toolbar > * { pointer-events: auto; }
        .layer-btn { min-height: 42px; border: 1px solid #d4edda; border-radius: 8px; background: rgba(255,255,255,.94); color: #1b2b23; padding: .55rem .72rem; font-size: .82rem; font-weight: 900; box-shadow: 0 .5rem 1.2rem rgba(13,31,24,.08); white-space: nowrap; }
        .layer-btn.active { background: #1a3a2a; border-color: #1a3a2a; color: #fff; }
        .risk-side { display: flex; flex-direction: column; gap: 1rem; }
        .risk-stat { position: relative; overflow: hidden; border: 1.5px solid #e8e0d0; border-radius: 18px; background: linear-gradient(145deg, #fff, #f7fbf8); padding: 1rem; box-shadow: 0 .8rem 1.8rem rgba(13,31,24,.06); }
        .risk-stat::before { content: ""; position: absolute; inset: 0 0 auto; height: 5px; background: var(--accent, #52b788); }
        .risk-label { color: #5a7a64; font-size: .72rem; font-weight: 900; text-transform: uppercase; letter-spacing: .06em; }
        .risk-value { color: #0d1f18; font-size: 2rem; font-weight: 900; line-height: 1; margin-top: .45rem; }
        .legend-row { display: flex; align-items: center; gap: .55rem; color: #5a7a64; font-size: .86rem; }
        .legend-swatch { width: 1rem; height: 1rem; border-radius: 4px; flex: 0 0 auto; }
        .barangay-risk-list { display: grid; gap: 1rem; }
        .barangay-card { border: 1.5px solid #e8e0d0; border-radius: 18px; background: #fff; overflow: hidden; box-shadow: 0 .75rem 1.6rem rgba(13,31,24,.05); }
        .barangay-card-head { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; padding: 1rem; background: linear-gradient(90deg, #fff, #f0f7f4); border-bottom: 1px solid #d4edda; }
        .barangay-name { color: #0d1f18; font-size: 1.15rem; font-weight: 900; line-height: 1.2; }
        .barangay-card-body { padding: 1rem; display: grid; gap: 1rem; }
        .risk-summary-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem; }
        .risk-info-box { border: 1px solid #d4edda; border-radius: 8px; background: #f7fbf8; padding: .9rem; min-height: 112px; }
        .risk-info-box.primary { background: #fff; border-left: 5px solid var(--accent, #52b788); }
        .risk-info-label { color: #5a7a64; font-size: .78rem; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; }
        .risk-info-value { color: #0d1f18; font-size: 1.02rem; font-weight: 900; margin-top: .28rem; line-height: 1.25; }
        .risk-chip { display: inline-flex; border-radius: 999px; padding: .42rem .72rem; font-size: .86rem; font-weight: 900; background: var(--chip-bg, #d8f3dc); color: var(--chip-color, #2d6a4f); }
        .risk-help { color: #5a7a64; font-size: .9rem; line-height: 1.45; margin-top: .42rem; }
        .risk-advice-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
        .risk-advice { border: 1px solid #d4edda; border-radius: 8px; background: #fff; padding: .95rem; }
        .risk-actions { display: flex; gap: .5rem; flex-wrap: wrap; justify-content: flex-end; }
        .risk-low { --accent: #52b788; --chip-bg: #d8f3dc; --chip-color: #2d6a4f; }
        .risk-moderate { --accent: #ffd166; --chip-bg: #fff4cf; --chip-color: #8a5a00; }
        .risk-high, .risk-severe { --accent: #d85b45; --chip-bg: #fde8e2; --chip-color: #9f3728; }
        .leaflet-popup-content { width: min(320px, calc(100vw - 4.5rem)) !important; min-width: 0; margin: .85rem; }
        .leaflet-popup-content-wrapper { border-radius: 8px; }
        .map-popup { color: #1b2b23; font-size: .86rem; line-height: 1.35; }
        .map-popup-title { display: flex; justify-content: space-between; align-items: flex-start; gap: .6rem; border-bottom: 1px solid #d4edda; padding-bottom: .58rem; margin-bottom: .62rem; }
        .map-popup-name { color: #0d1f18; font-size: 1rem; font-weight: 900; line-height: 1.18; }
        .map-popup-badge { flex: 0 0 auto; border-radius: 999px; padding: .28rem .52rem; background: var(--popup-bg, #d8f3dc); color: var(--popup-color, #2d6a4f); font-size: .7rem; font-weight: 900; }
        .map-popup-takeaway { border-left: 4px solid var(--popup-accent, #52b788); background: #f7fbf8; border-radius: 8px; padding: .58rem .65rem; margin-bottom: .6rem; font-weight: 800; }
        .map-popup-grid { display: grid; grid-template-columns: 1fr; gap: .45rem; }
        .map-popup-box { border: 1px solid #d4edda; border-radius: 8px; background: #fff; padding: .52rem .58rem; }
        .map-popup-label { color: #5a7a64; font-size: .66rem; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; }
        .map-popup-value { color: #0d1f18; font-weight: 900; margin-top: .16rem; }
        .map-popup-note { color: #5a7a64; font-size: .78rem; margin-top: .18rem; }
        .map-popup-advice { border: 1px solid #d4edda; border-radius: 8px; background: #f0f7f4; padding: .58rem .65rem; margin-top: .55rem; }
        .map-popup-source { color: #5a7a64; font-size: .74rem; margin-top: .55rem; border-top: 1px solid #d4edda; padding-top: .5rem; }
        .map-detail-panel {
            position: absolute;
            top: 4.75rem;
            right: 1rem;
            z-index: 520;
            width: min(292px, calc(100% - 2rem));
            max-height: calc(100% - 5.75rem);
            overflow: auto;
            border: 1.5px solid #e8e0d0;
            border-radius: 18px;
            background: rgba(255,255,255,.96);
            box-shadow: 0 .9rem 2rem rgba(13,31,24,.18);
            padding: .85rem;
            display: none;
        }
        .map-detail-panel.show { display: block; }
        .map-detail-empty { color: #5a7a64; font-size: .88rem; line-height: 1.45; }
        .map-detail-close {
            position: absolute;
            top: .5rem;
            right: .5rem;
            width: 30px;
            height: 30px;
            border: 1px solid #d4edda;
            border-radius: 8px;
            background: #fff;
            color: #0d1f18;
            font-weight: 900;
            line-height: 1;
        }
        .map-detail-panel .map-popup-title { padding-right: 2rem; }
        .barangay-tooltip {
            border: 0;
            border-radius: 999px;
            background: rgba(13, 31, 24, .88);
            color: #fff;
            font-size: .72rem;
            font-weight: 900;
            letter-spacing: 0;
            padding: .24rem .5rem;
            box-shadow: 0 .45rem 1rem rgba(13,31,24,.16);
        }
        .barangay-tooltip::before { display: none; }
        @media (max-width: 1199.98px) {
            .map-insight-strip { grid-template-columns: 1fr; }
            .heatmap-grid { grid-template-columns: 1fr; }
            .risk-side { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
            #barangayRiskMap { height: 480px; }
        }
        @media (max-width: 767.98px) {
            .map-shell { overflow: visible; }
            #barangayRiskMap { height: min(70vh, 430px); min-height: 340px; }
            .map-toolbar {
                position: relative;
                inset: auto;
                z-index: 1;
                padding: .75rem;
                overflow-x: auto;
                flex-wrap: nowrap;
                pointer-events: auto;
                background: #fff;
                border-bottom: 1px solid #d4edda;
                -webkit-overflow-scrolling: touch;
            }
            .map-detail-panel {
                position: relative;
                inset: auto;
                width: auto;
                max-height: 320px;
                margin: .75rem;
            }
            .layer-btn { flex: 0 0 auto; min-width: max-content; font-size: .8rem; }
            .risk-side { grid-template-columns: 1fr; }
            .priority-list { grid-template-columns: 1fr; }
            .priority-item { grid-template-columns: 1fr; }
            .priority-score { justify-self: start; }
            .risk-stat { padding: .9rem; }
            .risk-value { font-size: 1.75rem; }
            .barangay-card-head, .risk-actions { display: grid; justify-content: stretch; }
            .risk-summary-grid, .risk-advice-grid { grid-template-columns: 1fr; }
            .risk-actions .btn, .risk-actions form, .risk-actions button { width: 100%; }
            .leaflet-popup { max-width: calc(100vw - 1rem) !important; }
            .leaflet-popup-content { width: calc(100vw - 4rem) !important; margin: .65rem; }
            .map-popup-title { display: grid; gap: .5rem; }
            .map-popup-badge { justify-self: start; }
            .barangay-tooltip { font-size: .68rem; max-width: 120px; white-space: normal; text-align: center; }
        }
        @media (max-width: 420px) {
            .leaflet-popup-content { width: calc(100vw - 3rem) !important; }
            .map-popup { font-size: .86rem; }
            .map-popup-name { font-size: 1rem; }
            .map-popup-box, .map-popup-advice, .map-popup-takeaway { padding: .58rem .62rem; }
            .map-popup-source { word-break: break-word; }
        }
    </style>

    <section class="page-hero">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
            <div>
                <div class="eyebrow mb-2">Barangay Agricultural Risk Map</div>
                <h1 class="h2 fw-bold mb-2">Heat Map Areas</h1>
                <p class="mb-0 text-white-50">Interactive Leaflet map for rainfall risk, rice yield, irrigation priority, and combined climate impact.</p>
            </div>
            @if ($canManage)
                <a class="btn btn-light align-self-start align-self-lg-end" href="{{ route('heatmap-areas.create') }}">Create Risk Area</a>
            @endif
        </div>
    </section>

    <div class="card filter-panel no-lift mb-4">
        <div class="card-body">
            <form class="row g-3 align-items-end" method="GET" data-loading="true">
                <div class="col-lg-4">
                    <label class="form-label fw-semibold">Search</label>
                    <input class="form-control form-control-lg" name="search" value="{{ $search }}" placeholder="Search barangay, rainfall, advisory">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label fw-semibold">Risk Level</label>
                    <select class="form-select form-select-lg" name="risk_level">
                        <option value="">All</option>
                        @foreach($riskLevels as $level)
                            <option value="{{ $level }}" @selected(request('risk_level') === $level)>{{ $level }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label fw-semibold">Risk Type</label>
                    <select class="form-select form-select-lg" name="risk_type">
                        <option value="">All</option>
                        @foreach($riskTypes as $type)
                            <option value="{{ $type }}" @selected(request('risk_type') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-auto d-flex gap-2">
                    <button class="btn btn-outline-primary btn-lg" type="submit" data-loading-text="Filtering...">Apply</button>
                    <a class="btn btn-outline-secondary btn-lg" href="{{ route('heatmap-areas.index') }}">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="heatmap-grid mb-4">
        <div class="heatmap-main">
            <div class="map-insight map-summary-card {{ $topPriority ? 'risk-'.strtolower($topPriority->risk_level) : 'risk-low' }}">
                <div class="risk-label">Smart Heat Map Summary</div>
                @if($topPriority)
                    <div class="risk-value">{{ $topPriority->barangay }}</div>
                    <div class="risk-help">
                        Highest priority at score {{ number_format($topPriority->risk_score, 2) }}.
                        Main concern: {{ $topPriority->risk_type }}.
                        {{ $topPriority->planting_advisory ?: 'Review field condition before planting.' }}
                    </div>
                @else
                    <div class="risk-value">No mapped records</div>
                    <div class="risk-help">Add barangay coordinates and risk records to activate the smart map.</div>
                @endif
            </div>

            <div class="map-shell">
                <div class="map-toolbar" aria-label="Heat map layer controls">
                    <button class="layer-btn active" type="button" data-layer="impact" aria-pressed="true">Climate Impact</button>
                    <button class="layer-btn" type="button" data-layer="rainfall" aria-pressed="false">Rainfall Risk</button>
                    <button class="layer-btn" type="button" data-layer="yield" aria-pressed="false">Rice Yield</button>
                    <button class="layer-btn" type="button" data-layer="irrigation" aria-pressed="false">Irrigation Priority</button>
                </div>
                <div id="boundaryNotice" class="alert alert-warning position-absolute m-3 bottom-0 start-0 end-0 d-none" style="z-index: 500;">
                    Add official Lian barangay boundaries to <code>public/geojson/lian-barangays.geojson</code> to render exact barangay polygons.
                </div>
                <aside id="mapDetailPanel" class="map-detail-panel" aria-live="polite">
                    <button class="map-detail-close" type="button" aria-label="Close details">&times;</button>
                    <div class="map-detail-empty">Click a barangay on the heat map to view risk details here.</div>
                </aside>
                <div id="barangayRiskMap"></div>
            </div>
        </div>

        <aside class="risk-side">
            <div class="map-insight">
                <div class="risk-label mb-3">Priority Queue</div>
                @if($priorityAreas->count())
                    <ul class="priority-list">
                        @foreach($priorityAreas as $area)
                            <li class="priority-item risk-{{ strtolower($area->risk_level) }}">
                                <div>
                                    <div class="fw-bold">{{ $area->barangay }}</div>
                                    <div class="small text-muted">{{ $area->risk_level }} risk | {{ $area->risk_type }} | {{ $area->rainfall_status ?: 'No rainfall status' }}</div>
                                </div>
                                <span class="priority-score">{{ number_format($area->risk_score, 2) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="text-muted">No priority areas yet.</div>
                @endif
            </div>
            <div class="risk-stat risk-high">
                <div class="risk-label">Mapped Barangays</div>
                <div class="risk-value">{{ number_format($mapAreas->count()) }}</div>
                <div class="text-muted small mt-2">Records with coordinates available for Leaflet display.</div>
            </div>
            <div class="risk-stat risk-moderate">
                <div class="risk-label">High Priority Areas</div>
                <div class="risk-value">{{ number_format($mapAreas->whereIn('risk_level', ['High', 'Severe'])->count()) }}</div>
                <div class="text-muted small mt-2">Barangays needing close MAO monitoring.</div>
            </div>
            <div class="risk-stat risk-low">
                <div class="risk-label">Average Predicted Yield</div>
                <div class="risk-value">{{ number_format($mapAreas->whereNotNull('predicted_yield')->avg('predicted_yield') ?? 0, 2) }}</div>
                <div class="text-muted small mt-2">Tons/hectare across mapped records.</div>
            </div>
            <div class="risk-stat">
                <div class="risk-label mb-3">Legend</div>
                <div class="d-grid gap-2">
                    <div class="legend-row"><span class="legend-swatch" style="background:#123cba"></span> Cold / low impact</div>
                    <div class="legend-row"><span class="legend-swatch" style="background:#17d8dc"></span> Light risk zone</div>
                    <div class="legend-row"><span class="legend-swatch" style="background:#69f23a"></span> Watch zone</div>
                    <div class="legend-row"><span class="legend-swatch" style="background:#fff118"></span> Moderate impact</div>
                    <div class="legend-row"><span class="legend-swatch" style="background:#ff7a0f"></span> High impact</div>
                    <div class="legend-row"><span class="legend-swatch" style="background:#f71912"></span> Hot / severe impact</div>
                </div>
            </div>
            @if ($riskSource)
                <div class="risk-stat">
                    <div class="risk-label">Risk Source</div>
                    <div class="small text-muted mt-2">{{ $riskSource }}</div>
                </div>
            @endif
        </aside>
    </div>

    @if ($records->count())
        <section class="mb-4">
            <div class="card no-lift mb-3">
                <div class="card-body">
                    <div class="d-flex flex-column flex-lg-row justify-content-between gap-2">
                        <div>
                            <div class="fw-bold h5 mb-1">Barangay Risk Details</div>
                            <div class="text-muted">Each card explains the risk, weather condition, yield information, and recommended action in plain language.</div>
                        </div>
                        <div class="text-muted small align-self-lg-end">Showing {{ $records->count() }} of {{ $records->total() }} records</div>
                    </div>
                </div>
            </div>

            <div class="barangay-risk-list">
                @foreach ($records as $record)
                    @php
                        $riskClass = 'risk-'.strtolower($record->risk_level);
                        $riskScore = (float) $record->risk_score;
                        $riskMeaning = $record->risk_level === 'High' || $record->risk_level === 'Severe'
                            ? 'Needs close monitoring because weather, yield, or field exposure may affect rice production.'
                            : ($record->risk_level === 'Moderate'
                                ? 'Watch conditions and prepare support if rainfall or temperature worsens.'
                                : 'Current ML and weather inputs suggest normal monitoring is enough.');
                        $scoreMeaning = $riskScore >= .65
                            ? 'High score: stronger chance of climate impact.'
                            : ($riskScore >= .50
                                ? 'Medium score: some warning signs are present.'
                                : 'Low score: current inputs are favorable.');
                        $yieldMeaning = $record->predicted_yield !== null
                            ? 'Estimated tons per hectare from available barangay production data.'
                            : 'No barangay yield record is available yet, so risk is based more on weather and exposure.';
                        $rainMeaning = str_contains(strtolower((string) $record->rainfall_status), 'low')
                            ? 'Rainfall may be short for rainfed fields.'
                            : (str_contains(strtolower((string) $record->rainfall_status), 'high')
                                ? 'Rainfall may cause flooding or drainage problems.'
                                : 'Rainfall is within a workable range for planning.');
                    @endphp
                    <article class="barangay-card {{ $riskClass }}">
                        <div class="barangay-card-head">
                            <div>
                                <div class="risk-info-label">Barangay</div>
                                <div class="barangay-name">{{ $record->barangay }}</div>
                            </div>
                            <div class="d-flex flex-column align-items-start align-items-lg-end gap-2">
                                <span class="risk-chip {{ $riskClass }}">{{ $record->risk_level }} Risk</span>
                                <div class="risk-help text-lg-end m-0">Score {{ number_format($riskScore, 2) }}. {{ $scoreMeaning }}</div>
                            </div>
                        </div>

                        <div class="barangay-card-body">
                            <div class="risk-summary-grid">
                                <div class="risk-info-box primary">
                                    <div class="risk-info-label">What This Means</div>
                                    <div class="risk-info-value">{{ $riskMeaning }}</div>
                                </div>
                                <div class="risk-info-box">
                                    <div class="risk-info-label">Main Concern</div>
                                    <div class="risk-info-value">{{ $record->risk_type }}</div>
                                    <div class="risk-help">{{ $record->risk_type }} is the main climate concern detected for this barangay.</div>
                                </div>
                                <div class="risk-info-box">
                                    <div class="risk-info-label">Rainfall</div>
                                    <div class="risk-info-value">{{ $record->rainfall_status ?: 'Not recorded' }}</div>
                                    <div class="risk-help">{{ $rainMeaning }}</div>
                                </div>
                            </div>

                            <div class="risk-summary-grid">
                                <div class="risk-info-box">
                                    <div class="risk-info-label">Predicted Yield</div>
                                    <div class="risk-info-value">{{ $record->predicted_yield !== null ? number_format($record->predicted_yield, 2).' t/ha' : 'No prediction' }}</div>
                                    <div class="risk-help">{{ $yieldMeaning }}</div>
                                </div>
                                <div class="risk-info-box">
                                    <div class="risk-info-label">Data Source</div>
                                    <div class="risk-info-value">ML + Weather API</div>
                                    <div class="risk-help">{{ $record->description ?: 'No source note available.' }}</div>
                                </div>
                                <div class="risk-info-box">
                                    <div class="risk-info-label">Monitoring Level</div>
                                    <div class="risk-info-value">{{ in_array($record->risk_level, ['High', 'Severe'], true) ? 'Close monitoring' : ($record->risk_level === 'Moderate' ? 'Regular monitoring' : 'Routine monitoring') }}</div>
                                    <div class="risk-help">Use this as a quick guide for MAO follow-up priority.</div>
                                </div>
                            </div>

                            <div class="risk-advice-grid">
                                <div class="risk-advice">
                                    <div class="risk-info-label">Planting Advisory</div>
                                    <div class="risk-info-value">{{ $record->planting_advisory ?: 'No advisory yet.' }}</div>
                                    <div class="risk-help">Suggested planting action based on the current risk level.</div>
                                </div>
                                <div class="risk-advice">
                                    <div class="risk-info-label">Irrigation Recommendation</div>
                                    <div class="risk-info-value">{{ $record->irrigation_recommendation ?: 'No recommendation yet.' }}</div>
                                    <div class="risk-help">Suggested water-support action for MAO monitoring.</div>
                                </div>
                            </div>

                            <div class="risk-actions">
                                <a class="btn btn-outline-secondary" href="{{ route('heatmap-areas.show', $record) }}">View Full Record</a>
                                @if ($canManage)
                                    <a class="btn btn-outline-primary" href="{{ route('heatmap-areas.edit', $record) }}">Edit</a>
                                    <form method="POST" action="{{ route('heatmap-areas.destroy', $record) }}" data-loading="true" onsubmit="return confirm('Delete this risk area?');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-outline-danger" type="submit" data-loading-text="Deleting...">Delete</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="card no-lift mt-3">
                <div class="card-body d-flex justify-content-end">{{ $records->links() }}</div>
            </div>
        </section>
    @else
        <div class="empty-state text-center p-5">
            <div class="h5">No heat map risk records found</div>
            <div class="text-muted">Try changing filters or create a new barangay risk area.</div>
        </div>
    @endif

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const areas = @json($mapPayload);
            const areaByBarangay = new Map(areas.map((area) => [area.barangay.toLowerCase(), area]));
            const boundaryUrl = "{{ asset('geojson/lian-barangays.geojson') }}";
            const map = L.map('barangayRiskMap', { scrollWheelZoom: false }).setView([14.015, 120.65], 12);
            document.getElementById('barangayRiskMap')?.classList.add('thermo-map');
            const colorFor = (value) => value >= .82 ? '#f71912' : value >= .68 ? '#ff7a0f' : value >= .54 ? '#fff118' : value >= .38 ? '#69f23a' : value >= .22 ? '#17d8dc' : '#123cba';
            const thermographicGradient = {
                .05: '#123cba',
                .18: '#1769d1',
                .32: '#17d8dc',
                .48: '#69f23a',
                .62: '#fff118',
                .78: '#ff7a0f',
                1: '#f71912',
            };
            const yieldScore = (area) => area.predicted_yield === null ? .5 : (area.predicted_yield < 3 ? .9 : (area.predicted_yield < 4 ? .6 : .25));
            const rainfallScore = (area) => String(area.rainfall_status || '').toLowerCase().includes('low') ? .9 : (String(area.rainfall_status || '').toLowerCase().includes('high') ? .65 : .3);
            const irrigationScore = (area) => String(area.irrigation_recommendation || '').toLowerCase().match(/increase|prioritize|urgent|support|reduce/) ? .9 : (area.risk_score || .3);
            const impactScore = (area) => Number(area.risk_score || 0);
            const scoreFor = (area, layer) => ({ rainfall: rainfallScore, yield: yieldScore, irrigation: irrigationScore, impact: impactScore }[layer] || impactScore)(area);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 18,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            let geoLayer = null;
            let heatLayer = null;
            let pointLayer = null;
            let barangayBoundaries = null;
            let activeLayer = 'impact';
            let selectedArea = null;

            const plainRiskMessage = (area) => {
                if (['High', 'Severe'].includes(area.risk_level)) {
                    return 'Please check this barangay first. Farms here may need help soon.';
                }

                if (area.risk_level === 'Moderate') {
                    return 'Keep watch. Conditions can still change, especially after rain or hot days.';
                }

                return 'Looks okay for now. Continue normal field checking.';
            };

            const plainConcern = (area) => {
                const concern = String(area.risk_type || '').toLowerCase();
                if (concern.includes('flood')) return 'Flooding or poor drainage';
                if (concern.includes('drought')) return 'Low water / drought';
                if (concern.includes('typhoon') || concern.includes('storm')) return 'Strong wind or storm';
                if (concern.includes('heat')) return 'Too much heat';
                return area.risk_type || 'Field condition';
            };

            const plainYield = (area) => {
                if (area.predicted_yield === null) return 'No harvest estimate yet';
                const yieldValue = Number(area.predicted_yield);
                if (yieldValue < 3) return 'Possible low harvest';
                if (yieldValue < 4) return 'Average harvest expected';
                return 'Good harvest outlook';
            };

            const harvestEstimate = (area) => area.predicted_yield === null
                ? 'No estimate yet'
                : `${Number(area.predicted_yield).toFixed(2)} t/ha`;

            const harvestSource = (area) => area.predicted_yield === null
                ? 'The model needs enough weather and farm area inputs.'
                : `${area.predicted_yield_source || 'Trained rice yield model'} estimate.`;

            const plainRain = (area) => {
                const rainfall = String(area.rainfall_status || '').toLowerCase();
                if (rainfall.includes('low')) return 'Rain may be too low. Water support may be needed.';
                if (rainfall.includes('high')) return 'Rain may be too much. Watch drainage and low areas.';
                return 'Rain looks manageable for now.';
            };

            const plainAction = (area) => {
                return area.irrigation_recommendation
                    || area.planting_advisory
                    || 'Visit or contact farmers in this barangay before major field work.';
            };

            const layerTitle = (layer) => ({
                impact: 'Climate impact',
                rainfall: 'Rainfall risk',
                yield: 'Rice yield',
                irrigation: 'Irrigation priority',
            }[layer] || 'Climate impact');

            const layerTakeaway = (area, layer) => {
                if (layer === 'rainfall') return plainRain(area);
                if (layer === 'yield') {
                    if (area.predicted_yield === null) return 'No harvest estimate yet. Use field reports and recent weather for now.';
                    return `${plainYield(area)}. Estimated harvest is ${harvestEstimate(area)}.`;
                }
                if (layer === 'irrigation') return plainAction(area);
                return plainRiskMessage(area);
            };

            const layerMainDetail = (area, layer) => {
                if (layer === 'rainfall') {
                    return {
                        label: 'Rain condition',
                        value: area.rainfall_status || 'Not recorded',
                        note: plainRain(area),
                    };
                }

                if (layer === 'yield') {
                    return {
                        label: 'Estimated harvest',
                        value: harvestEstimate(area),
                        note: harvestSource(area),
                    };
                }

                if (layer === 'irrigation') {
                    return {
                        label: 'Water support',
                        value: area.irrigation_recommendation ? 'Action needed' : 'No urgent water note',
                        note: plainAction(area),
                    };
                }

                return {
                    label: 'Main thing to watch',
                    value: plainConcern(area),
                    note: plainRiskMessage(area),
                };
            };

            const layerSecondDetail = (area, layer) => {
                if (layer === 'rainfall') {
                    return { label: 'Field concern', value: plainConcern(area), note: 'Check low fields first after strong rain.' };
                }

                if (layer === 'yield') {
                    return { label: 'Field concern', value: plainConcern(area), note: 'This may affect the expected harvest.' };
                }

                if (layer === 'irrigation') {
                    return { label: 'Rain condition', value: area.rainfall_status || 'Not recorded', note: plainRain(area) };
                }

                return { label: 'Risk level', value: `${area.risk_level} Risk`, note: 'Use this for follow-up priority.' };
            };

            const layerAction = (area, layer) => {
                if (layer === 'rainfall') return 'Check water level and drainage before advising farmers.';
                if (layer === 'yield') return area.planting_advisory || 'Check crop stage and field condition before harvest planning.';
                if (layer === 'irrigation') return plainAction(area);
                return plainAction(area);
            };

            const popupStyle = (area) => {
                if (['High', 'Severe'].includes(area.risk_level)) {
                    return '--popup-accent:#d85b45;--popup-bg:#fde8e2;--popup-color:#9f3728;';
                }

                if (area.risk_level === 'Moderate') {
                    return '--popup-accent:#ffd166;--popup-bg:#fff4cf;--popup-color:#8a5a00;';
                }

                return '--popup-accent:#52b788;--popup-bg:#d8f3dc;--popup-color:#2d6a4f;';
            };

            const popup = (area, score, layer = activeLayer) => {
                const mainDetail = layerMainDetail(area, layer);
                const secondDetail = layerSecondDetail(area, layer);

                return `
                <div class="map-popup" style="${popupStyle(area)}">
                    <div class="map-popup-title">
                        <div>
                            <div class="map-popup-label">Barangay</div>
                            <div class="map-popup-name">${area.barangay}</div>
                            <div class="map-popup-note">Showing: ${layerTitle(layer)}</div>
                        </div>
                        <span class="map-popup-badge">${area.risk_level} Risk</span>
                    </div>

                    <div class="map-popup-takeaway">${layerTakeaway(area, layer)}</div>

                    <div class="map-popup-grid">
                        <div class="map-popup-box">
                            <div class="map-popup-label">${mainDetail.label}</div>
                            <div class="map-popup-value">${mainDetail.value}</div>
                            <div class="map-popup-note">${mainDetail.note}</div>
                        </div>
                        <div class="map-popup-box">
                            <div class="map-popup-label">${secondDetail.label}</div>
                            <div class="map-popup-value">${secondDetail.value}</div>
                            <div class="map-popup-note">${secondDetail.note}</div>
                        </div>
                        <div class="map-popup-box">
                            <div class="map-popup-label">Rice harvest</div>
                            <div class="map-popup-value">${harvestEstimate(area)}</div>
                            <div class="map-popup-note">${plainYield(area)}</div>
                        </div>
                    </div>

                    <div class="map-popup-advice">
                        <div class="map-popup-label">What to do now</div>
                        <div class="map-popup-value">${layerAction(area, layer)}</div>
                    </div>
                </div>
            `;
            };
            const detailPanel = document.getElementById('mapDetailPanel');
            const showDetails = (area, score, layer = activeLayer) => {
                if (!detailPanel) return;

                selectedArea = area;
                detailPanel.innerHTML = `
                    <button class="map-detail-close" type="button" aria-label="Close details">&times;</button>
                    ${popup(area, score, layer)}
                `;
                detailPanel.classList.add('show');
                detailPanel.querySelector('.map-detail-close')?.addEventListener('click', () => {
                    detailPanel.classList.remove('show');
                    selectedArea = null;
                    detailPanel.innerHTML = `
                        <button class="map-detail-close" type="button" aria-label="Close details">&times;</button>
                        <div class="map-detail-empty">Click a barangay on the heat map to view risk details here.</div>
                    `;
                });
            };
            detailPanel?.querySelector('.map-detail-close')?.addEventListener('click', () => detailPanel.classList.remove('show'));

            const featureName = (feature) => {
                const props = feature.properties || {};
                return String(
                    props.barangay ||
                    props.BARANGAY ||
                    props.brgy ||
                    props.BRGY ||
                    props.name ||
                    props.NAME ||
                    props.ADM4_EN ||
                    props.ADM4_PCODE ||
                    ''
                ).trim();
            };

            const boundaryFeatures = () => {
                if (!barangayBoundaries || !Array.isArray(barangayBoundaries.features)) {
                    return [];
                }

                return barangayBoundaries.features
                    .map((feature) => {
                        const name = featureName(feature);
                        const area = areaByBarangay.get(name.toLowerCase());
                        if (!area) return null;

                        return {
                            ...feature,
                            properties: {
                                ...(feature.properties || {}),
                                area,
                                score: 0,
                            },
                        };
                    })
                    .filter(Boolean);
            };

            const renderLayer = (layer = 'impact') => {
                activeLayer = layer;
                if (geoLayer) map.removeLayer(geoLayer);
                if (heatLayer) map.removeLayer(heatLayer);
                if (pointLayer) map.removeLayer(pointLayer);

                const features = boundaryFeatures().map((feature) => ({
                    ...feature,
                    properties: {
                        ...feature.properties,
                        score: scoreFor(feature.properties.area, layer),
                    },
                }));

                geoLayer = L.geoJSON({ type: 'FeatureCollection', features }, {
                    style: (feature) => ({
                        color: '#0d1f18',
                        weight: 1,
                        fillColor: colorFor(feature.properties.score),
                        fillOpacity: .62,
                    }),
                    onEachFeature: (feature, polygon) => {
                        polygon.on('click', () => showDetails(feature.properties.area, feature.properties.score, layer));
                        polygon.bindTooltip(feature.properties.area.barangay, {
                            permanent: true,
                            direction: 'center',
                            className: 'barangay-tooltip',
                        });
                    }
                }).addTo(map);

                if (L.heatLayer) {
                    heatLayer = L.heatLayer(areas.map((area) => [area.latitude, area.longitude, Math.max(.12, scoreFor(area, layer))]), {
                        radius: 70,
                        blur: 42,
                        maxZoom: 14,
                        minOpacity: .42,
                        gradient: thermographicGradient,
                    }).addTo(map);
                }

                pointLayer = L.featureGroup(areas.flatMap((area) => {
                    const score = scoreFor(area, layer);
                    const clickZone = L.circleMarker([area.latitude, area.longitude], {
                        radius: 34,
                        stroke: false,
                        fillOpacity: 0,
                        interactive: true,
                    });
                    const marker = L.marker([area.latitude, area.longitude], {
                        icon: L.divIcon({
                            className: '',
                            html: '<span class="thermo-point"></span>',
                            iconSize: [12, 12],
                            iconAnchor: [6, 6],
                        }),
                    });

                    clickZone.on('click', () => showDetails(area, score, layer));
                    marker.on('click', () => showDetails(area, score, layer));
                    marker.bindTooltip(area.barangay, {
                        permanent: true,
                        direction: 'top',
                        offset: [0, -12],
                        className: 'barangay-tooltip',
                    });

                    return [clickZone, marker];
                })).addTo(map);

                if (features.length > 0) {
                    map.fitBounds(geoLayer.getBounds().pad(.18));
                    document.getElementById('boundaryNotice')?.classList.add('d-none');
                } else if (areas.length > 0) {
                    map.fitBounds(pointLayer.getBounds().pad(.24));
                    document.getElementById('boundaryNotice')?.classList.add('d-none');
                } else {
                    document.getElementById('boundaryNotice')?.classList.remove('d-none');
                }

                if (selectedArea && detailPanel?.classList.contains('show')) {
                    showDetails(selectedArea, scoreFor(selectedArea, layer), layer);
                }
            };

            fetch(boundaryUrl)
                .then((response) => response.ok ? response.json() : null)
                .then((geojson) => {
                    barangayBoundaries = geojson;
                    renderLayer();
                })
                .catch(() => renderLayer());

            document.querySelectorAll('[data-layer]').forEach((button) => {
                button.addEventListener('click', () => {
                    document.querySelectorAll('[data-layer]').forEach((item) => {
                        item.classList.remove('active');
                        item.setAttribute('aria-pressed', 'false');
                    });
                    button.classList.add('active');
                    button.setAttribute('aria-pressed', 'true');
                    renderLayer(button.dataset.layer);
                });
            });
        });
    </script>
</x-app-layout>
