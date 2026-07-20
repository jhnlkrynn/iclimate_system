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
    <link rel="preload" as="style" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"></noscript>
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
        .thermo-map .leaflet-tile-pane { filter: saturate(1.08) contrast(1.04); }
        .thermo-map .leaflet-overlay-pane canvas {
            mix-blend-mode: screen;
            filter: saturate(1.45) contrast(1.04);
            opacity: .72;
        }
        .thermo-point {
            position: relative;
            display: block;
            width: 14px;
            height: 14px;
            border-radius: 999px;
            border: 2px solid rgba(255,255,255,.92);
            background: var(--marker-color, #52b788);
            box-shadow: 0 0 0 5px rgba(255,255,255,.2), 0 0 0 10px var(--marker-glow, rgba(82,183,136,.18)), 0 .45rem .9rem rgba(13,31,24,.38);
        }
        .thermo-point::after { content: ""; position: absolute; inset: 2px; border-radius: inherit; background: rgba(255,255,255,.28); }
        .map-insight-strip { display: grid; grid-template-columns: 1.15fr .85fr; gap: 1rem; margin-bottom: 1rem; align-items: start; }
        .map-insight { position: relative; overflow: hidden; border: 1.5px solid #e8e0d0; border-radius: 18px; background: linear-gradient(145deg, #fff, #f7fbf8); padding: .85rem; box-shadow: 0 .8rem 1.8rem rgba(13,31,24,.06); }
        .map-insight::before { content: ""; position: absolute; inset: 0 0 auto; height: 5px; background: var(--accent, #52b788); }
        .map-summary-card .risk-value { font-size: 1.55rem; line-height: 1.12; margin-top: .32rem; }
        .map-summary-card .risk-help { margin-top: .35rem; }
        .priority-list { display: grid; grid-template-columns: 1fr; gap: .42rem; margin: 0; padding: 0; list-style: none; }
        .priority-item { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: .55rem; align-items: center; border: 1px solid #d4edda; border-radius: 8px; background: #fff; padding: .5rem .58rem; }
        .priority-jump { border: 0; background: transparent; color: inherit; text-align: left; padding: 0; min-width: 0; }
        .priority-jump:hover .fw-bold, .priority-jump:focus .fw-bold { color: #2d6a4f; text-decoration: underline; }
        .priority-item .fw-bold, .priority-item .small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .priority-score { min-width: 3.6rem; text-align: center; border-radius: 999px; padding: .35rem .55rem; background: var(--chip-bg, #d8f3dc); color: var(--chip-color, #2d6a4f); font-weight: 900; }
        .map-toolbar { position: absolute; z-index: 500; left: 1rem; right: 1rem; top: 1rem; display: flex; gap: .5rem; flex-wrap: wrap; pointer-events: none; }
        .map-toolbar > * { pointer-events: auto; }
        .layer-btn { min-height: 42px; border: 1px solid #d4edda; border-radius: 8px; background: rgba(255,255,255,.94); color: #1b2b23; padding: .55rem .72rem; font-size: .82rem; font-weight: 900; box-shadow: 0 .5rem 1.2rem rgba(13,31,24,.08); white-space: nowrap; }
        .layer-btn.active { background: #1a3a2a; border-color: #1a3a2a; color: #fff; }
        .basemap-btn { background: rgba(13,31,24,.78); border-color: rgba(255,255,255,.22); color: #fff; }
        .basemap-btn.active { background: #e8a73d; border-color: #e8a73d; color: #0d1f18; }
        .readability-btn { background: rgba(255,255,255,.96); border-color: #95d5b2; color: #1f6f4a; }
        .readability-btn.active { background: #1f6f4a; border-color: #1f6f4a; color: #fff; }
        .map-atmosphere { position: absolute; inset: 0; z-index: 410; pointer-events: none; background: radial-gradient(circle at 23% 24%, rgba(255,255,255,.16), transparent 18rem), radial-gradient(circle at 76% 68%, rgba(13,31,24,.22), transparent 22rem), linear-gradient(180deg, rgba(13,31,24,.08), rgba(13,31,24,.16)); mix-blend-mode: soft-light; }
        .leaflet-container { font-family: 'Inter', system-ui, sans-serif; }
        .leaflet-control-attribution { background: rgba(255,255,255,.78) !important; color: #315044 !important; backdrop-filter: blur(10px); border-radius: 8px 0 0 0; }
        .realistic-map .leaflet-overlay-pane path { transition: fill-opacity .18s ease, stroke-width .18s ease, filter .18s ease; }
        .field-zone { stroke: rgba(255,255,255,.82); stroke-width: 1.5; stroke-dasharray: 4 5; filter: drop-shadow(0 4px 10px rgba(13,31,24,.22)); }
        .map-control-panel {
            position: absolute;
            z-index: 510;
            left: 1rem;
            top: 4.85rem;
            width: min(360px, calc(100% - 2rem));
            border: 1px solid rgba(149,213,178,.38);
            border-radius: 8px;
            background: rgba(13,31,24,.92);
            color: rgba(255,255,255,.88);
            box-shadow: 0 .8rem 1.8rem rgba(0,0,0,.32);
            backdrop-filter: blur(10px);
            padding: .75rem;
        }
        .map-control-panel .risk-label { color: rgba(255,255,255,.58); }
        .map-control-panel .form-select {
            border-color: rgba(149,213,178,.32);
            background-color: rgba(255,255,255,.08);
            color: #fff;
            font-weight: 800;
        }
        .map-control-panel .form-select:hover,
        .map-control-panel .form-select:focus {
            border-color: #74c69d;
            background-color: rgba(255,255,255,.12);
            box-shadow: 0 0 0 .2rem rgba(116,198,157,.16);
        }
        .map-control-panel .form-select option { color: #0d1f18; background: #fff; }
        .map-live-status {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .65rem;
            margin-top: .58rem;
            color: rgba(255,255,255,.64);
            font-size: .78rem;
            font-weight: 800;
        }
        .map-live-status strong { color: rgba(255,255,255,.82); }
        .map-selected-pill {
            display: inline-flex;
            align-items: center;
            max-width: 100%;
            border-radius: 999px;
            background: rgba(116,198,157,.18);
            color: #b7e4c7;
            padding: .28rem .55rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
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
            top: 4.85rem;
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
        .selection-ring {
            width: 30px;
            height: 30px;
            border-radius: 999px;
            border: 3px solid #0d1f18;
            box-shadow: 0 0 0 7px rgba(82,183,136,.28), 0 .6rem 1.2rem rgba(13,31,24,.22);
            background: rgba(255,255,255,.72);
        }
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
        .barangay-tooltip.easy-read {
            border: 2px solid rgba(255,255,255,.88);
            background: var(--label-bg, rgba(13,31,24,.9));
            color: var(--label-color, #fff);
            box-shadow: 0 .5rem 1.25rem rgba(13,31,24,.28);
        }
        .barangay-tooltip::before { display: none; }
        .easy-read-marker {
            min-width: 108px;
            border: 2px solid rgba(255,255,255,.92);
            border-radius: 10px;
            background: var(--label-bg, #2c7bb6);
            color: var(--label-color, #fff);
            padding: .32rem .45rem;
            box-shadow: 0 .55rem 1.25rem rgba(13,31,24,.28);
            text-align: center;
            font-weight: 900;
            line-height: 1.1;
        }
        .easy-read-marker small {
            display: block;
            margin-top: .12rem;
            font-size: .62rem;
            font-weight: 800;
            opacity: .92;
        }
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
                background: rgba(13,31,24,.94);
                border-bottom: 1px solid rgba(149,213,178,.26);
                -webkit-overflow-scrolling: touch;
            }
            .map-control-panel {
                position: relative;
                inset: auto;
                width: auto;
                margin: .75rem;
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

        /* -- dark theme overrides (page chrome only; map/overlay controls kept self-contained) -- */
        .heatmap-page { color: rgba(255,255,255,.85); }
        .map-shell { border-color: rgba(255,255,255,.12); background: var(--ic-green-950); }
        .map-insight { border-color: rgba(255,255,255,.12); background: var(--ic-green-950); }
        .risk-label { color: rgba(255,255,255,.5); }
        .risk-value { color: #fff; }
        .risk-help { color: rgba(255,255,255,.6); }
        .legend-row { color: rgba(255,255,255,.65); }
        .priority-item { border-color: rgba(255,255,255,.12); background: var(--ic-green-950); }
        .priority-item .fw-bold { color: #fff; }
        .priority-jump:hover .fw-bold, .priority-jump:focus .fw-bold { color: #74c69d; }
        .risk-stat { border-color: rgba(255,255,255,.12); background: var(--ic-green-950); }
        .barangay-card { border-color: rgba(255,255,255,.12); background: var(--ic-green-950); }
        .barangay-card-head { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.1); }
        .barangay-name { color: #fff; }
        .risk-info-box { border-color: rgba(255,255,255,.12); background: rgba(255,255,255,.04); }
        .risk-info-box.primary { background: rgba(82,183,136,.08); }
        .risk-info-label { color: rgba(255,255,255,.5); }
        .risk-info-value { color: #fff; }
        .risk-advice { border-color: rgba(255,255,255,.12); background: rgba(255,255,255,.04); }
        .heatmap-page .empty-state { background: var(--ic-green-950); border-color: rgba(255,255,255,.16); color: rgba(255,255,255,.7); }
        .heatmap-page .text-muted { color: rgba(255,255,255,.5) !important; }
        .heatmap-page .btn-outline-secondary { color: rgba(255,255,255,.8); border-color: rgba(255,255,255,.28); }
        .heatmap-page .btn-outline-secondary:hover { background: rgba(255,255,255,.12); border-color: rgba(255,255,255,.6); color: #fff; }
        .heatmap-page .btn-outline-primary { color: #74c69d; border-color: #74c69d; }
        .heatmap-page .btn-outline-primary:hover { background: #2d6a4f; border-color: #2d6a4f; color: #fff; }
    </style>
    @include('layouts.partials.dark-workspace')

    <div class="dark-workspace heatmap-page">
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
            <form class="row g-3 align-items-end" method="GET">
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
                    <button class="btn btn-outline-primary btn-lg" type="submit">Apply</button>
                    <a class="btn btn-outline-secondary btn-lg" href="{{ route('heatmap-areas.index') }}">Reset</a>
                    @if($canManage)
                        <a class="btn btn-outline-success btn-lg" href="{{ route('heatmap-areas.index', ['refresh' => 1]) }}">Refresh Map</a>
                    @endif
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
                    <button class="layer-btn basemap-btn active" type="button" data-basemap="satellite" aria-pressed="true">Satellite</button>
                    <button class="layer-btn basemap-btn" type="button" data-basemap="terrain" aria-pressed="false">Terrain</button>
                    <button class="layer-btn basemap-btn" type="button" data-basemap="street" aria-pressed="false">Street</button>
                    <button class="layer-btn readability-btn" type="button" id="easyReadToggle" aria-pressed="false">Easy Colors</button>
                </div>
                <div class="map-control-panel">
                    <label class="risk-label mb-2 d-block" for="mapBarangayFocus">Focus Barangay</label>
                    <select id="mapBarangayFocus" class="form-select" aria-label="Focus heat map on a barangay">
                        <option value="">Choose a barangay</option>
                        @foreach($mapAreas->sortBy('barangay') as $area)
                            <option value="{{ $area->barangay }}">{{ $area->barangay }}</option>
                        @endforeach
                    </select>
                    <div class="map-live-status">
                        <span>Layer: <strong id="activeLayerLabel">Climate Impact</strong></span>
                        <span class="map-selected-pill" id="selectedBarangayLabel">No barangay selected</span>
                    </div>
                </div>
                <div id="boundaryNotice" class="alert alert-warning position-absolute m-3 bottom-0 start-0 end-0 d-none" style="z-index: 500;">
                    Add official Lian barangay boundaries to <code>public/geojson/lian-barangays.geojson</code> to render exact barangay polygons.
                </div>
                <aside id="mapDetailPanel" class="map-detail-panel" aria-live="polite">
                    <button class="map-detail-close" type="button" aria-label="Close details">&times;</button>
                    <div class="map-detail-empty">Click a barangay on the heat map to view risk details here.</div>
                </aside>
                <div id="barangayRiskMap"></div>
                <div class="map-atmosphere" aria-hidden="true"></div>
            </div>
        </div>

        <aside class="risk-side">
            <div class="map-insight">
                <div class="risk-label mb-3">Priority Queue</div>
                @if($priorityAreas->count())
                    <ul class="priority-list">
                        @foreach($priorityAreas as $area)
                            <li class="priority-item risk-{{ strtolower($area->risk_level) }}">
                                <button class="priority-jump" type="button" data-focus-barangay="{{ $area->barangay }}">
                                    <div class="fw-bold">{{ $area->barangay }}</div>
                                    <div class="small text-muted">{{ $area->risk_level }} risk | {{ $area->risk_type }} | {{ $area->rainfall_status ?: 'No rainfall status' }}</div>
                                </button>
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
                    <div class="legend-row"><span class="legend-swatch" style="background:#2c7bb6"></span> Cold / low impact</div>
                    <div class="legend-row"><span class="legend-swatch" style="background:#2ec7c9"></span> Light risk zone</div>
                    <div class="legend-row"><span class="legend-swatch" style="background:#2cba6c"></span> Watch zone</div>
                    <div class="legend-row"><span class="legend-swatch" style="background:#fff34d"></span> Moderate impact</div>
                    <div class="legend-row"><span class="legend-swatch" style="background:#fdae21"></span> High impact</div>
                    <div class="legend-row"><span class="legend-swatch" style="background:linear-gradient(90deg,#d7191c,#7f0000)"></span> Hot / severe impact</div>
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
    </div>

    <script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script defer src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof L === 'undefined') {
                document.getElementById('boundaryNotice')?.classList.remove('d-none');
                return;
            }

            const areas = @json($mapPayload);
            const areaByBarangay = new Map(areas.map((area) => [area.barangay.toLowerCase(), area]));
            const boundaryUrl = "{{ asset('geojson/lian-barangays.geojson') }}";
            const map = L.map('barangayRiskMap', {
                scrollWheelZoom: false,
                zoomControl: true,
                preferCanvas: true,
            }).setView([14.015, 120.65], 12);
            document.getElementById('barangayRiskMap')?.classList.add('thermo-map', 'realistic-map');
            const colorFor = (value) => value >= .88 ? '#7f0000' : value >= .74 ? '#d7191c' : value >= .58 ? '#fdae21' : value >= .42 ? '#fff34d' : value >= .26 ? '#2cba6c' : value >= .12 ? '#2ec7c9' : '#2c7bb6';
            const rgbaFor = (value, alpha = .42) => {
                const hex = colorFor(value).replace('#', '');
                const bigint = parseInt(hex, 16);
                const red = (bigint >> 16) & 255;
                const green = (bigint >> 8) & 255;
                const blue = bigint & 255;

                return `rgba(${red},${green},${blue},${alpha})`;
            };
            const thermographicGradient = {
                .00: '#2c7bb6',
                .16: '#2ec7c9',
                .32: '#2cba6c',
                .50: '#fff34d',
                .68: '#fdae21',
                .84: '#d7191c',
                1.00: '#7f0000',
            };
            const yieldScore = (area) => area.predicted_yield === null ? .5 : (area.predicted_yield < 3 ? .9 : (area.predicted_yield < 4 ? .6 : .25));
            const rainfallScore = (area) => String(area.rainfall_status || '').toLowerCase().includes('low') ? .9 : (String(area.rainfall_status || '').toLowerCase().includes('high') ? .65 : .3);
            const irrigationScore = (area) => String(area.irrigation_recommendation || '').toLowerCase().match(/increase|prioritize|urgent|support|reduce/) ? .9 : (area.risk_score || .3);
            const impactScore = (area) => Number(area.risk_score || 0);
            const scoreFor = (area, layer) => ({ rainfall: rainfallScore, yield: yieldScore, irrigation: irrigationScore, impact: impactScore }[layer] || impactScore)(area);

            const baseLayers = {
                satellite: L.layerGroup([
                    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                        maxZoom: 19,
                        attribution: 'Tiles &copy; Esri',
                    }),
                    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_only_labels/{z}/{x}/{y}{r}.png', {
                        maxZoom: 20,
                        opacity: .9,
                        attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
                    }),
                ]),
                terrain: L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
                    maxZoom: 17,
                    attribution: '&copy; OpenTopoMap &copy; OpenStreetMap contributors',
                }),
                street: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors',
                }),
            };
            let activeBaseLayer = baseLayers.satellite.addTo(map);

            let geoLayer = null;
            let heatLayer = null;
            let fieldLayer = null;
            let pointLayer = null;
            let barangayBoundaries = null;
            let activeLayer = 'impact';
            let selectedArea = null;
            let selectedRing = null;
            let easyReadMode = false;
            const focusSelect = document.getElementById('mapBarangayFocus');
            const selectedLabel = document.getElementById('selectedBarangayLabel');
            const activeLayerLabel = document.getElementById('activeLayerLabel');
            const easyReadToggle = document.getElementById('easyReadToggle');
            const updateLayerLabel = () => {
                if (activeLayerLabel) activeLayerLabel.textContent = `${layerTitle(activeLayer)}${easyReadMode ? ' - Easy colors' : ''}`;
            };

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

            const readabilityLabel = (score) => {
                if (score >= .88) return 'Severe';
                if (score >= .74) return 'High';
                if (score >= .58) return 'Elevated';
                if (score >= .42) return 'Moderate';
                if (score >= .26) return 'Watch';
                if (score >= .12) return 'Light';
                return 'Low';
            };

            const readableTextColor = (score) => (score >= .42 && score < .74) ? '#0d1f18' : '#fff';

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
                if (selectedLabel) selectedLabel.textContent = area.barangay;
                if (focusSelect && focusSelect.value !== area.barangay) focusSelect.value = area.barangay;
                detailPanel.innerHTML = `
                    <button class="map-detail-close" type="button" aria-label="Close details">&times;</button>
                    ${popup(area, score, layer)}
                `;
                detailPanel.classList.add('show');
                detailPanel.querySelector('.map-detail-close')?.addEventListener('click', () => {
                    detailPanel.classList.remove('show');
                    selectedArea = null;
                    if (selectedLabel) selectedLabel.textContent = 'No barangay selected';
                    if (focusSelect) focusSelect.value = '';
                    if (selectedRing) {
                        map.removeLayer(selectedRing);
                        selectedRing = null;
                    }
                    detailPanel.innerHTML = `
                        <button class="map-detail-close" type="button" aria-label="Close details">&times;</button>
                        <div class="map-detail-empty">Click a barangay on the heat map to view risk details here.</div>
                    `;
                });
            };
            detailPanel?.querySelector('.map-detail-close')?.addEventListener('click', () => {
                detailPanel.classList.remove('show');
                selectedArea = null;
                if (selectedLabel) selectedLabel.textContent = 'No barangay selected';
                if (focusSelect) focusSelect.value = '';
                if (selectedRing) {
                    map.removeLayer(selectedRing);
                    selectedRing = null;
                }
            });

            const updateSelectionRing = (area) => {
                if (selectedRing) map.removeLayer(selectedRing);

                selectedRing = L.marker([area.latitude, area.longitude], {
                    interactive: false,
                    icon: L.divIcon({
                        className: '',
                        html: '<span class="selection-ring"></span>',
                        iconSize: [30, 30],
                        iconAnchor: [15, 15],
                    }),
                }).addTo(map);
            };

            const focusArea = (barangay, zoom = 13) => {
                const area = areaByBarangay.get(String(barangay || '').toLowerCase());
                if (!area) return;

                const score = scoreFor(area, activeLayer);
                selectedArea = area;
                updateSelectionRing(area);
                showDetails(area, score, activeLayer);
                map.flyTo([area.latitude, area.longitude], Math.max(map.getZoom(), zoom), {
                    animate: true,
                    duration: .65,
                });
            };

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
                updateLayerLabel();
                if (geoLayer) map.removeLayer(geoLayer);
                if (heatLayer) map.removeLayer(heatLayer);
                if (fieldLayer) map.removeLayer(fieldLayer);
                if (pointLayer) map.removeLayer(pointLayer);
                if (selectedRing) {
                    map.removeLayer(selectedRing);
                    selectedRing = null;
                }

                const features = boundaryFeatures().map((feature) => ({
                    ...feature,
                    properties: {
                        ...feature.properties,
                        score: scoreFor(feature.properties.area, layer),
                    },
                }));

                geoLayer = L.geoJSON({ type: 'FeatureCollection', features }, {
                    style: (feature) => ({
                        color: 'rgba(255,255,255,.88)',
                        weight: easyReadMode ? 2.2 : 1.4,
                        opacity: .92,
                        fillColor: colorFor(feature.properties.score),
                        fillOpacity: easyReadMode ? .72 : .38,
                        dashArray: easyReadMode ? null : '5 5',
                    }),
                    onEachFeature: (feature, polygon) => {
                        polygon.on({
                            click: () => showDetails(feature.properties.area, feature.properties.score, layer),
                            mouseover: () => polygon.setStyle({ weight: 2.8, fillOpacity: easyReadMode ? .82 : .5 }),
                            mouseout: () => polygon.setStyle({ weight: easyReadMode ? 2.2 : 1.4, fillOpacity: easyReadMode ? .72 : .38 }),
                        });
                        polygon.bindTooltip(feature.properties.area.barangay, {
                            permanent: true,
                            direction: 'center',
                            className: `barangay-tooltip ${easyReadMode ? 'easy-read' : ''}`,
                            opacity: easyReadMode ? 1 : .92,
                        });
                    }
                }).addTo(map);

                fieldLayer = L.featureGroup(areas.map((area) => {
                    const score = scoreFor(area, layer);
                    const radius = 850 + (score * 1350);

                    return L.circle([area.latitude, area.longitude], {
                        radius,
                        className: 'field-zone',
                        color: rgbaFor(score, .78),
                        fillColor: rgbaFor(score, .46),
                        fillOpacity: .28,
                        opacity: .78,
                        interactive: false,
                    });
                })).addTo(map);

                if (L.heatLayer && !easyReadMode) {
                    heatLayer = L.heatLayer(areas.map((area) => [area.latitude, area.longitude, Math.max(.12, scoreFor(area, layer))]), {
                        radius: 62,
                        blur: 38,
                        maxZoom: 14,
                        minOpacity: .25,
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
                            html: easyReadMode
                                ? `<span class="easy-read-marker" style="--label-bg:${colorFor(score)};--label-color:${readableTextColor(score)}">${area.barangay}<small>${readabilityLabel(score)}</small></span>`
                                : `<span class="thermo-point" style="--marker-color:${colorFor(score)};--marker-glow:${rgbaFor(score, .26)}"></span>`,
                            iconSize: easyReadMode ? [112, 42] : [14, 14],
                            iconAnchor: easyReadMode ? [56, 21] : [7, 7],
                        }),
                    });

                    clickZone.on('click', () => showDetails(area, score, layer));
                    marker.on('click', () => showDetails(area, score, layer));
                    if (!easyReadMode) {
                        marker.bindTooltip(area.barangay, {
                            permanent: true,
                            direction: 'top',
                            offset: [0, -12],
                            className: 'barangay-tooltip',
                        });
                    }

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
                    updateSelectionRing(selectedArea);
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

            document.querySelectorAll('[data-basemap]').forEach((button) => {
                button.addEventListener('click', () => {
                    const layerName = button.dataset.basemap;
                    const nextLayer = baseLayers[layerName];
                    if (!nextLayer || nextLayer === activeBaseLayer) return;

                    map.removeLayer(activeBaseLayer);
                    activeBaseLayer = nextLayer.addTo(map);
                    document.querySelectorAll('[data-basemap]').forEach((item) => {
                        item.classList.remove('active');
                        item.setAttribute('aria-pressed', 'false');
                    });
                    button.classList.add('active');
                    button.setAttribute('aria-pressed', 'true');
                });
            });

            easyReadToggle?.addEventListener('click', () => {
                easyReadMode = !easyReadMode;
                easyReadToggle.classList.toggle('active', easyReadMode);
                easyReadToggle.setAttribute('aria-pressed', easyReadMode ? 'true' : 'false');
                easyReadToggle.textContent = easyReadMode ? 'Real Heat' : 'Easy Colors';
                updateLayerLabel();
                renderLayer(activeLayer);
            });

            focusSelect?.addEventListener('change', () => {
                if (!focusSelect.value) return;
                focusArea(focusSelect.value);
            });

            document.querySelectorAll('[data-focus-barangay]').forEach((button) => {
                button.addEventListener('click', () => focusArea(button.dataset.focusBarangay));
            });
        });
    </script>
</x-app-layout>
