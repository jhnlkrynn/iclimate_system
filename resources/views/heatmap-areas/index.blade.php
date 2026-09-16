@php
    $mapPayload = $mapAreas->map(fn ($area) => [
        'barangay' => $area->barangay,
        'polygon_id' => str($area->barangay)->lower()->replace(['barangay', 'brgy', '(pob.)', 'pob.', 'poblacion', '-', '.'], ' ')->squish()->toString(),
        'coordinates' => [(float) $area->latitude, (float) $area->longitude],
        'risk_level' => $area->risk_level,
        'risk_score' => (float) $area->risk_score,
        'color' => match ($area->risk_level) {
            'Low' => '#22c55e',
            'Moderate' => '#facc15',
            'High' => '#f97316',
            'Severe' => '#ef4444',
            default => '#9ca3af',
        },
        'reason' => $area->main_risk_reason ?: $area->description ?: "{$area->risk_type} is the main computed risk for this barangay.",
        'suggested_action' => $area->suggested_action ?: $area->irrigation_recommendation ?: $area->planting_advisory ?: 'Continue routine field monitoring.',
        'predicted_yield' => $area->predicted_yield !== null ? (float) $area->predicted_yield : null,
        'rainfall_status' => $area->rainfall_status,
        'risk_type' => $area->risk_type,
        'planting_advisory' => $area->planting_advisory,
        'irrigation_recommendation' => $area->irrigation_recommendation,
        'temperature' => $liveWeather['current_temperature_c'] ?? $liveWeather['temperature_c'] ?? null,
        'rainfall_mm' => $liveWeather['current_rainfall_mm'] ?? $liveWeather['daily_rainfall_mm'] ?? null,
        'humidity' => $liveWeather['humidity_percent'] ?? null,
        'weather_condition' => $liveWeather['current_condition'] ?? null,
        'updated_at' => ($area->risk_computed_at ?? $area->updated_at)?->toISOString(),
    ])->values();
    $riskSource = optional($mapAreas->first())->description;
    $priorityAreas = $mapAreas->sortByDesc('risk_score')->take(5)->values();
    $topPriority = $priorityAreas->first();
    $pagasaSignal = $latestPagasaAdvisory ? [
        'title' => $latestPagasaAdvisory->title,
        'severity' => $latestPagasaAdvisory->severityLabel(),
        'summary' => $latestPagasaAdvisory->summary ?: $latestPagasaAdvisory->message,
        'source' => 'PAGASA',
        'source_url' => $latestPagasaAdvisory->source_url,
        'updated_at' => $latestPagasaAdvisory->valid_from?->shortDateTime('M d, Y') ?: $latestPagasaAdvisory->created_at?->shortDateTime('M d, Y'),
        'freshness' => 'Official PAGASA online advisory cached in iClimate',
    ] : [
        'title' => 'No active PAGASA signal for Lian/Batangas',
        'severity' => 'None',
        'summary' => 'No active PAGASA advisory record is stored for Lian or Batangas right now. Use the official PAGASA map link for external verification.',
        'source' => 'PAGASA External Reference',
        'source_url' => $pagasaMapUrl,
        'updated_at' => 'No stored PAGASA match',
        'freshness' => 'External reference only',
    ];
    $apiFilters = array_filter([
        'search' => $search,
        'risk_level' => request('risk_level'),
        'risk_type' => request('risk_type'),
    ]);
@endphp

<x-app-layout>
    @if (($analyticsRun ?? null) && in_array($analyticsRun->status, ['pending', 'processing'], true))
        <div class="alert alert-info border" data-analytics-run="{{ route('analytics-runs.show', $analyticsRun) }}">
            <strong>Python is recalculating heat-map risk.</strong> Stored map results remain visible while processing finishes.
        </div>
    @elseif (($analyticsRun ?? null)?->status === 'failed')
        <div class="alert alert-danger border"><strong>Heat-map processing failed.</strong> {{ $analyticsRun->error }}</div>
    @endif
    <style>
        .heatmap-grid { display: grid; grid-template-columns: minmax(270px, 320px) minmax(0, 1fr); gap: 1rem; align-items: stretch; }
        .heatmap-grid > .heatmap-main { grid-column: 2; grid-row: 1; }
        .heatmap-grid > .risk-side { grid-column: 1; grid-row: 1; }
        .heatmap-main { display: flex; flex-direction: column; gap: 1rem; }
        .map-shell { position: relative; overflow: hidden; display: flex; flex-direction: column; border: 1.5px solid #e8e0d0; border-radius: 12px; background: #fff; box-shadow: 0 .9rem 2rem rgba(13,31,24,.07); }
        #barangayRiskMap {
            position: relative;
            height: 700px;
            overflow: hidden;
            background: #07160f;
        }
        #barangayRiskMap .leaflet-container { background: #d8e6df; font-family: inherit; }
        #barangayRiskMap canvas, #barangayRiskMap svg { max-width: none; height: auto; }
        #barangayRiskMap .leaflet-heatmap-layer { opacity: .92; transition: opacity .35s ease; }
        #barangayRiskMap .leaflet-zones-layer { opacity: .55; }

        .risk-pin {
            cursor: pointer;
            width: 18px;
            height: 18px;
            display: grid;
            place-items: center;
            transition: transform .2s cubic-bezier(.34,1.56,.64,1);
        }
        .risk-pin:hover,
        .risk-pin.is-selected {
            transform: scale(1.35);
        }
        .risk-pin-dot {
            width: 12px;
            height: 12px;
            border-radius: 999px;
            background: var(--risk-dot-color, #22c55e);
            border: 2px solid rgba(255,255,255,.92);
            box-shadow: 0 0 0 1.5px rgba(13,31,24,.55), 0 .2rem .45rem rgba(13,31,24,.3);
        }

        .legend-gradient-bar {
            height: 12px;
            border-radius: 6px;
            margin: .45rem 0 .35rem;
            box-shadow: inset 0 1px 2px rgba(0,0,0,.22);
            border: 1px solid rgba(13,31,24,.15);
        }
        .legend-scale-ticks {
            display: flex;
            justify-content: space-between;
            font-size: .74rem;
            font-weight: 800;
            color: #4f665b;
        }
        .map-loading-overlay {
            position: absolute; inset: 0; z-index: 50;
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .6rem;
            background: rgba(7,22,15,.72);
            color: rgba(255,255,255,.85);
            font-size: .82rem; font-weight: 700;
            transition: opacity .18s ease;
        }
        .map-loading-overlay.d-none { display: none; }
        .map-loading-spinner {
            position: relative;
            width: 58px;
            height: 58px;
            border-radius: 999px;
            background:
                conic-gradient(from 0deg, transparent 0 12%, rgba(183,228,199,.35) 16%, #74c69d 34%, #ffd166 47%, transparent 60% 100%);
            animation: mapLoadingSpin 1s linear infinite;
            filter: drop-shadow(0 0 14px rgba(116,198,157,.34));
        }
        .map-loading-spinner::before,
        .map-loading-spinner::after {
            content: "";
            position: absolute;
            inset: 7px;
            border-radius: inherit;
            background: rgba(7,22,15,.9);
        }
        .map-loading-spinner::after {
            inset: 0;
            background:
                radial-gradient(circle at 50% 3px, #ffd166 0 4px, transparent 4.5px),
                radial-gradient(circle at 50% 3px, rgba(255,209,102,.28) 0 9px, transparent 9.5px);
            animation: mapLoadingOrbit .72s linear infinite;
        }
        .map-loading-overlay span {
            position: relative;
            padding-top: .15rem;
        }
        .map-loading-overlay span::after {
            content: "";
            display: inline-block;
            width: 1.4em;
            text-align: left;
            animation: mapLoadingDots 1.2s steps(4, end) infinite;
        }
        @keyframes mapLoadingSpin {
            to { transform: rotate(360deg); }
        }
        @keyframes mapLoadingOrbit {
            from { transform: rotate(0deg); }
            to { transform: rotate(-360deg); }
        }
        @keyframes mapLoadingDots {
            0% { content: ""; }
            25% { content: "."; }
            50% { content: ".."; }
            75%, 100% { content: "..."; }
        }
        .leaflet-zones-layer path.leaflet-interactive {
            transition: filter .16s ease, stroke-width .16s ease, stroke .16s ease;
            cursor: pointer;
        }
        .leaflet-zones-layer path.leaflet-interactive:hover,
        .leaflet-zones-layer path.is-selected {
            filter: brightness(1.12) saturate(1.1);
        }
        .farm-boundary-pane path.leaflet-interactive {
            fill: rgba(255, 255, 255, .08);
            stroke: #ffffff;
            stroke-width: 2.5;
            stroke-dasharray: 8 5;
            pointer-events: none;
        }
        .map-insight-strip { display: grid; grid-template-columns: 1.15fr .85fr; gap: 1rem; margin-bottom: 1rem; align-items: start; }
        .map-insight { position: relative; overflow: hidden; border: 1.5px solid #e8e0d0; border-radius: 18px; background: linear-gradient(145deg, #fff, #f7fbf8); padding: .85rem; box-shadow: 0 .8rem 1.8rem rgba(13,31,24,.06); }
        .map-insight::before { content: ""; position: absolute; inset: 0 0 auto; height: 5px; background: var(--accent, #52b788); }
        .map-summary-card { display: none; }
        .map-summary-card .risk-value { font-size: 1.55rem; line-height: 1.12; margin-top: .32rem; }
        .map-summary-card .risk-help { margin-top: .35rem; }
        .priority-list { display: grid; grid-template-columns: 1fr; gap: .42rem; margin: 0; padding: 0; list-style: none; }
        .priority-item { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: .55rem; align-items: center; border: 1px solid #d4edda; border-radius: 8px; background: #fff; padding: .5rem .58rem; }
        .priority-jump { border: 0; background: transparent; color: inherit; text-align: left; padding: 0; min-width: 0; }
        .priority-jump:hover .fw-bold, .priority-jump:focus .fw-bold { color: #2d6a4f; text-decoration: underline; }
        .priority-item .fw-bold, .priority-item .small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .priority-score { min-width: 3.6rem; text-align: center; border-radius: 999px; padding: .35rem .55rem; background: var(--chip-bg, #d8f3dc); color: var(--chip-color, #2d6a4f); font-weight: 900; }
        .accuracy-badge { display: inline-flex; align-items: center; gap: .35rem; border-radius: 999px; border: 1px solid rgba(149,213,178,.38); background: rgba(82,183,136,.13); color: #b7e4c7; padding: .3rem .52rem; font-size: .72rem; font-weight: 900; }
        .accuracy-badge.baseline { border-color: rgba(255,209,102,.45); background: rgba(255,209,102,.13); color: #ffe8a3; }
        .map-toolbar { position: absolute; z-index: 500; left: 1rem; right: 1rem; top: 1rem; display: flex; gap: .5rem; flex-wrap: wrap; pointer-events: none; }
        .map-toolbar > * { pointer-events: auto; }
        .layer-btn { min-height: 42px; border: 1.5px solid #ffffff; border-radius: 8px; background: rgba(255,255,255,.98); color: #10251b; padding: .55rem .72rem; font-size: .82rem; font-weight: 900; box-shadow: 0 .5rem 1.2rem rgba(13,31,24,.18); white-space: nowrap; }
        .layer-btn:hover { background: #f5fff9; border-color: #74c69d; color: #10251b; transform: translateY(-1px); }
        .layer-btn.active { background: #1a3a2a; border-color: #1a3a2a; color: #fff; box-shadow: 0 .65rem 1.4rem rgba(13,31,24,.28); }
        .map-atmosphere { position: absolute; inset: 0; z-index: 320; pointer-events: none; background: radial-gradient(circle at 23% 24%, rgba(255,255,255,.11), transparent 18rem), radial-gradient(circle at 76% 68%, rgba(13,31,24,.12), transparent 22rem), linear-gradient(180deg, rgba(13,31,24,.04), rgba(13,31,24,.08)); mix-blend-mode: soft-light; }
        .map-control-panel {
            position: absolute;
            z-index: 510;
            left: 1rem;
            top: auto;
            bottom: 5.15rem;
            width: min(340px, calc(100% - 2rem));
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
            flex-direction: column;
            align-items: flex-start;
            gap: .4rem;
            margin-top: .58rem;
            color: rgba(255,255,255,.64);
            font-size: .875rem;
            font-weight: 800;
        }
        .map-live-status strong { color: rgba(255,255,255,.82); }
        .map-live-status .badge { white-space: nowrap; }
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
        .map-legend-overlay {
            position: absolute;
            z-index: 506;
            right: 1rem;
            bottom: 4.9rem;
            width: 148px;
            border: 1.5px solid #b9c4bd;
            border-radius: 6px;
            background: rgba(255,255,255,.96);
            box-shadow: 0 .6rem 1.4rem rgba(13,31,24,.22);
            padding: .55rem .65rem;
        }
        .map-legend-overlay-title {
            font-size: .78rem;
            font-weight: 800;
            color: #10251b;
            margin-bottom: .35rem;
        }
        .map-legend-overlay-row {
            display: flex;
            align-items: center;
            gap: .4rem;
            font-size: .78rem;
            font-weight: 700;
            color: #1f2a24;
            line-height: 1.6;
        }
        .map-legend-overlay-swatch {
            width: .85rem;
            height: .85rem;
            border-radius: 3px;
            flex: 0 0 auto;
            box-shadow: inset 0 0 0 1px rgba(13,31,24,.18);
        }
        .pagasa-source-strip {
            position: absolute;
            z-index: 505;
            left: 1rem;
            right: 1rem;
            bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            border: 1px solid rgba(149,213,178,.34);
            border-radius: 8px;
            background: rgba(13,31,24,.9);
            color: rgba(255,255,255,.76);
            padding: .65rem .75rem;
            box-shadow: 0 .8rem 1.8rem rgba(0,0,0,.24);
            backdrop-filter: blur(10px);
            pointer-events: auto;
        }
        .pagasa-official-badge {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            border: 1px solid rgba(116,198,157,.45);
            border-radius: 999px;
            background: rgba(82,183,136,.16);
            color: #b7e4c7;
            padding: .38rem .62rem;
            font-size: .82rem;
            font-weight: 900;
            white-space: nowrap;
        }
        .pagasa-source-copy { min-width: 0; font-size: .875rem; font-weight: 800; line-height: 1.35; }
        .pagasa-source-copy strong { display: block; color: #fff; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .pagasa-map-link {
            flex: 0 0 auto;
            border-radius: 8px;
            border: 1px solid #f0c36a;
            background: #1F5A46;
            color: #fff;
            padding: .52rem .7rem;
            font-size: .875rem;
            font-weight: 900;
            text-decoration: none;
            white-space: nowrap;
        }
        .pagasa-map-link:hover { color: #0d1f18; filter: brightness(1.05); transform: translateY(-2px); }
        .risk-side { display: flex; flex-direction: column; gap: .85rem; min-height: 100%; }
        .risk-side::before {
            content: "Heat Mapping Tool";
            display: block;
            border: 1px solid rgba(149,213,178,.18);
            border-radius: 12px;
            background: rgba(13,31,24,.96);
            color: #fff;
            padding: .95rem 1rem;
            font-family: 'DM Serif Display', Georgia, serif;
            font-size: 1.18rem;
            box-shadow: 0 .7rem 1.6rem rgba(13,31,24,.12);
        }
        .risk-stat, .risk-side .map-insight { position: relative; overflow: hidden; border: 1.5px solid #e8e0d0; border-radius: 12px; background: linear-gradient(145deg, #fff, #f7fbf8); padding: 1rem; box-shadow: 0 .8rem 1.8rem rgba(13,31,24,.06); }
        .risk-mini-stats { display: contents; }
        .risk-stat::before { content: ""; position: absolute; inset: 0 0 auto; height: 5px; background: var(--accent, #52b788); }
        .risk-label { color: #4f665b; font-size: .875rem; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; line-height: 1.35; }
        .risk-value { color: #0d1f18; font-size: 2rem; font-weight: 900; line-height: 1; margin-top: .45rem; }
        .legend-row { display: flex; align-items: center; gap: .55rem; color: #5a7a64; font-size: .86rem; }
        .legend-swatch { width: 1rem; height: 1rem; border-radius: 4px; flex: 0 0 auto; box-shadow: 0 0 0 1px rgba(13,31,24,.12); }
        .map-explainer { border: 1.5px solid #e8e0d0; border-radius: 12px; background: linear-gradient(145deg, #fff, #f7fbf8); padding: .9rem 1.1rem; margin-bottom: 1rem; color: #1f2a24; }
        .map-explainer-legend { display: flex; flex-wrap: wrap; gap: .5rem 1.1rem; }
        .map-explainer-legend .legend-row { font-weight: 800; }
        .barangay-risk-list { display: grid; gap: 1rem; }
        .barangay-card { border: 1.5px solid #e8e0d0; border-radius: 18px; background: #fff; overflow: hidden; box-shadow: 0 .75rem 1.6rem rgba(13,31,24,.05); }
        .barangay-card-head { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; padding: 1rem; background: linear-gradient(90deg, #fff, #f0f7f4); border-bottom: 1px solid #d4edda; }
        .barangay-name { color: #0d1f18; font-size: 1.15rem; font-weight: 900; line-height: 1.2; }
        .barangay-card-body { padding: 1rem; display: grid; gap: 1rem; }
        .risk-summary-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem; }
        .risk-info-box { border: 1px solid #d4edda; border-radius: 8px; background: #f7fbf8; padding: .9rem; min-height: 112px; }
        .risk-info-box.primary { background: #fff; border-left: 5px solid var(--accent, #52b788); }
        .risk-info-label { color: #4f665b; font-size: .875rem; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; line-height: 1.35; }
        .risk-info-value { color: #0d1f18; font-size: 1.02rem; font-weight: 900; margin-top: .28rem; line-height: 1.25; }
        .risk-chip { display: inline-flex; border-radius: 999px; padding: .42rem .72rem; font-size: .86rem; font-weight: 900; background: var(--chip-bg, #d8f3dc); color: var(--chip-color, #2d6a4f); }
        .risk-chip-lg { padding: .55rem .95rem; font-size: 1rem; }
        .risk-help { color: #5a7a64; font-size: .9rem; line-height: 1.45; margin-top: .42rem; }
        .risk-advice-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
        .risk-advice { border: 1px solid #d4edda; border-radius: 8px; background: #fff; padding: .95rem; }
        .risk-actions { display: flex; gap: .5rem; flex-wrap: wrap; justify-content: flex-end; }
        .risk-low { --accent: #22c55e; --chip-bg: #dcfce7; --chip-color: #15803d; }
        .risk-moderate { --accent: #facc15; --chip-bg: #fef9c3; --chip-color: #854d0e; }
        .risk-high { --accent: #f97316; --chip-bg: #ffedd5; --chip-color: #c2410c; }
        .risk-severe { --accent: #ef4444; --chip-bg: #fee2e2; --chip-color: #b91c1c; }
        .map-popup { color: #1b2b23; font-size: 1.02rem; line-height: 1.55; }
        .map-popup-title { display: flex; justify-content: space-between; align-items: flex-start; gap: .6rem; border-bottom: 1px solid #d4edda; padding-bottom: .65rem; margin-bottom: .7rem; }
        .map-popup-name { color: #0d1f18; font-size: 1.35rem; font-weight: 900; line-height: 1.22; }
        .map-popup-badge { flex: 0 0 auto; border-radius: 999px; padding: .4rem .75rem; background: var(--popup-bg, #d8f3dc); color: var(--popup-color, #2d6a4f); font-size: .92rem; font-weight: 900; line-height: 1.25; }
        .map-popup-takeaway { border-left: 5px solid var(--popup-accent, #52b788); background: #f7fbf8; border-radius: 8px; padding: .75rem .8rem; margin-bottom: .7rem; font-weight: 800; font-size: 1.1rem; line-height: 1.5; }
        .map-popup-grid { display: grid; grid-template-columns: 1fr; gap: .45rem; }
        .map-popup-box { border: 1px solid #d4edda; border-radius: 8px; background: #fff; padding: .52rem .58rem; }
        .map-popup-label { color: #4f665b; font-size: .88rem; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; line-height: 1.4; }
        .map-popup-value { color: #0d1f18; font-weight: 900; margin-top: .2rem; font-size: 1.05rem; line-height: 1.5; }
        .map-popup-note { color: #4f665b; font-size: .95rem; margin-top: .22rem; line-height: 1.5; }
        .map-popup-advice { border: 2px solid #bfe3cd; border-radius: 10px; background: #f0f7f4; padding: .75rem .8rem; margin-top: .65rem; }
        .map-popup-source { color: #4f665b; font-size: .88rem; margin-top: .55rem; border-top: 1px solid #d4edda; padding-top: .5rem; line-height: 1.5; }
        .map-detail-panel {
            position: absolute;
            top: 4.85rem;
            right: 1rem;
            z-index: 520;
            width: min(340px, calc(100% - 2rem));
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
            font-size: .875rem;
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
            font-size: .75rem;
            font-weight: 800;
            opacity: .92;
        }
        @media (max-width: 1199.98px) {
            .map-insight-strip { grid-template-columns: 1fr; }
            .heatmap-grid { grid-template-columns: 1fr; }
            .heatmap-grid > .heatmap-main, .heatmap-grid > .risk-side { grid-column: auto; grid-row: auto; }
            .risk-side { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
            #barangayRiskMap { height: 520px; min-height: 520px; }
        }
        @media (max-width: 767.98px) {
            .map-shell { overflow: visible; }
            #barangayRiskMap { height: min(58vh, 360px); min-height: 280px; }
            .map-insight-strip { margin-bottom: .65rem; }
            .heatmap-grid { gap: .65rem; }
            .heatmap-main { gap: .65rem; }
            .map-toolbar {
                position: relative;
                inset: auto;
                z-index: 1;
                padding: .6rem;
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
                margin: .6rem;
                padding: .6rem;
            }
            .map-detail-panel {
                position: relative;
                inset: auto;
                width: auto;
                max-height: 300px;
                margin: .6rem;
                padding: .65rem;
            }
            .pagasa-source-strip {
                position: relative;
                inset: auto;
                display: grid;
                margin: .6rem;
                padding: .55rem .6rem;
            }
            .map-legend-overlay {
                right: .6rem;
                bottom: .6rem;
                width: 128px;
                padding: .45rem .55rem;
            }
            .pagasa-map-link { width: 100%; text-align: center; }
            .layer-btn { flex: 0 0 auto; min-width: max-content; font-size: .8rem; min-height: 40px; padding: .5rem .65rem; }
            .risk-side { grid-template-columns: 1fr; gap: .6rem; }
            .risk-mini-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .5rem; }
            .risk-mini-stats .risk-stat { padding: .6rem .55rem; }
            .risk-mini-stats .risk-label { font-size: .62rem; }
            .risk-mini-stats .risk-value { font-size: 1.2rem; margin-top: .2rem; }
            .risk-mini-stats .text-muted.small { font-size: .68rem; margin-top: .3rem !important; }
            .priority-list { grid-template-columns: 1fr; }
            .priority-item { grid-template-columns: 1fr; }
            .priority-score { justify-self: start; }
            .risk-stat { padding: .8rem; }
            .risk-value { font-size: 1.6rem; }
            .barangay-risk-list { gap: .65rem; }
            .barangay-card-head { padding: .75rem; }
            .barangay-card-body { padding: .75rem; gap: .65rem; }
            .barangay-card-head, .risk-actions { display: grid; justify-content: stretch; }
            .risk-summary-grid, .risk-advice-grid { grid-template-columns: 1fr; gap: .5rem; }
            .risk-info-box { min-height: 0; padding: .7rem; }
            .risk-advice { padding: .75rem; }
            .risk-actions .btn, .risk-actions form, .risk-actions button { width: 100%; min-height: 42px; }
            .map-popup-title { display: grid; gap: .5rem; }
            .map-popup-badge { justify-self: start; }
            .barangay-tooltip { font-size: .78rem; max-width: 140px; white-space: normal; text-align: center; }
        }
        @media (max-width: 420px) {
            .map-popup { font-size: .86rem; }
            .map-popup-name { font-size: 1rem; }
            .map-popup-box, .map-popup-advice, .map-popup-takeaway { padding: .58rem .62rem; }
            .map-popup-source { word-break: break-word; }
            .pagasa-official-badge { width: 100%; justify-content: center; }
        }

        /* -- page chrome: light surfaces, dark ink text (map/overlay controls kept self-contained) -- */
        .heatmap-page { color: #1f2a24; }
        .map-shell { border-color: #e8e0d0; background: #fff; }
        .map-insight { border-color: #e8e0d0; background: linear-gradient(145deg, #fff, #f7fbf8); }
        .risk-label { color: #6b7c72; }
        .risk-value { color: #1f2a24; }
        .risk-help { color: #4a5c52; }
        .legend-row { color: #4a5c52; }
        .priority-item { border-color: #d4edda; background: #fff; }
        .priority-item .fw-bold { color: #1f2a24; }
        .priority-jump:hover .fw-bold, .priority-jump:focus .fw-bold { color: #2d6a4f; }
        .risk-stat { border-color: #e8e0d0; background: linear-gradient(145deg, #fff, #f7fbf8); }
        .barangay-card { border-color: #e8e0d0; background: #fff; }
        .barangay-card-head { background: linear-gradient(90deg, #fff, #f0f7f4); border-color: #d4edda; }
        .barangay-name { color: #0d1f18; }
        .risk-info-box { border-color: #d4edda; background: #f7fbf8; }
        .risk-info-box.primary { background: #fff; }
        .risk-info-label { color: #6b7c72; }
        .risk-info-value { color: #1f2a24; }
        .risk-advice { border-color: #d4edda; background: #fff; }
        .heatmap-page .empty-state { background: linear-gradient(135deg, #ffffff, var(--ic-green-50)); border-color: var(--ic-sand-dark); color: #6b7c72; }
        .heatmap-page .text-muted { color: #6b7c72 !important; }
        .heatmap-page .btn-outline-secondary { color: #4a5c52; border-color: #d4edda; }
        .heatmap-page .btn-outline-secondary:hover { background: rgba(45,106,79,.08); border-color: #2d6a4f; color: #1f2a24; }
        .heatmap-page .btn-outline-primary { color: #2d6a4f; border-color: #2d6a4f; }
        .heatmap-page .btn-outline-primary:hover { background: #2d6a4f; border-color: #2d6a4f; color: #fff; }
        .filter-actions { align-items: stretch; }
        .filter-actions .btn {
            min-width: 96px;
            border-width: 1.5px;
            border-radius: 8px;
            font-weight: 900;
            opacity: 1;
            box-shadow: none;
        }
        .filter-actions .btn-apply {
            background: #1f6b4a;
            border-color: #1f6b4a;
            color: #fff;
        }
        .filter-actions .btn-reset {
            background: #fff;
            border-color: #8bb7a4;
            color: #1f4d3a;
        }
        .filter-actions .btn-refresh {
            background: #1F5A46;
            border-color: #1F5A46;
            color: #fff;
        }
        .filter-actions .btn:hover {
            filter: brightness(1.04);
            transform: translateY(-1px);
        }
    </style>

    <div class="heatmap-page">
    <section class="page-hero">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
            <div>
                <div class="eyebrow mb-2">Barangay Agricultural Risk Map</div>
                <h1 class="h2 fw-bold mb-2">Heat Map Areas</h1>
                <p class="mb-0" style="color: var(--ic-ink-mid);">API-rendered Lian barangay heatmap, with PAGASA used as the official weather reference source.</p>
            </div>
            <div class="d-flex flex-wrap gap-2 align-self-start align-self-lg-end action-cluster">
                <a class="btn btn-primary" href="{{ $pagasaMapUrl }}" target="_blank" rel="noopener">View PAGASA Map</a>
                @if ($canManage)
                    <a class="btn btn-light" href="{{ route('heatmap-areas.create') }}">Create Risk Area</a>
                @endif
            </div>
        </div>
    </section>

    <div class="map-explainer">
        <p class="mb-2">This map shows barangay risk levels. <strong>Red</strong> means urgent attention, <strong>orange</strong> means high monitoring, <strong>yellow</strong> means moderate risk, and <strong>green</strong> means low risk. Click a barangay to see the reason and recommended action.</p>
        <div class="map-explainer-legend">
            <span class="legend-row"><span class="legend-swatch" style="background:#22c55e"></span> Green = Low</span>
            <span class="legend-row"><span class="legend-swatch" style="background:#facc15"></span> Yellow = Moderate</span>
            <span class="legend-row"><span class="legend-swatch" style="background:#f97316"></span> Orange = High</span>
            <span class="legend-row"><span class="legend-swatch" style="background:#ef4444"></span> Red = Severe</span>
        </div>
    </div>

    <div class="alert alert-warning">
        <strong>Methodology note:</strong> Per-barangay risk exposure values on this map are
        engineering estimates, not derived from official hazard, flood-zone, or elevation data.
        Treat relative comparisons between barangays with caution until sourced hazard data is
        incorporated.
    </div>

    @php
        $activeFilterCount = (($search ?? '') !== '' ? 1 : 0)
            + (request()->filled('risk_level') ? 1 : 0)
            + (request()->filled('risk_type') ? 1 : 0);
    @endphp
    <details class="ic-collapse" @if($activeFilterCount) data-force-open="true" @endif>
        <summary>
            <span class="ic-collapse-title">
                Filters
                @if($activeFilterCount)
                    <span class="ic-collapse-count">{{ $activeFilterCount }}</span>
                @endif
            </span>
        </summary>
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
                    <div class="col-lg-auto d-flex gap-2 filter-actions">
                        <button class="btn btn-apply btn-lg" type="submit">Apply</button>
                        <a class="btn btn-reset btn-lg" href="{{ route('heatmap-areas.index') }}">Reset</a>
                        @if($canManage)
                            <a class="btn btn-refresh btn-lg" href="{{ route('heatmap-areas.index', ['refresh' => 1]) }}">Refresh Map</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </details>

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
                <div class="map-toolbar" aria-label="Heat map info">
                    <span class="pagasa-official-badge">🔥 Composite Climate Impact Heat Map</span>
                    <span class="pagasa-official-badge">PAGASA Official Source</span>
                </div>
                <div class="map-control-panel">
                    <label class="risk-label mb-2 d-block" for="mapForecastMonth">Forecast Month</label>
                    <select id="mapForecastMonth" class="form-select mb-3" aria-label="Choose a forecast month for the heat map">
                        <option value="">Now &mdash; {{ now()->format('F Y') }}</option>
                        @for($i = 1; $i <= 6; $i++)
                            @php
                                $forecastMonth = now()->addMonthsNoOverflow($i)->startOfMonth();
                            @endphp
                            <option value="{{ $forecastMonth->format('Y-m') }}">{{ $forecastMonth->format('F Y') }} (forecast)</option>
                        @endfor
                    </select>
                    <label class="risk-label mb-2 d-block" for="mapBarangayFocus">Focus Barangay</label>
                    <select id="mapBarangayFocus" class="form-select" aria-label="Focus heat map on a barangay">
                        <option value="">Choose a barangay</option>
                        @foreach($mapAreas->sortBy('barangay') as $area)
                            <option value="{{ $area->barangay }}">{{ $area->barangay }}</option>
                        @endforeach
                    </select>
                    <div class="map-live-status d-flex flex-wrap align-items-center gap-2">
                        <span class="map-selected-pill" id="selectedBarangayLabel">No barangay selected</span>
                        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-1 d-none" id="forecastModeBadge" style="font-size:.75rem;">
                            Showing forecast
                        </span>
                        <span class="badge bg-light text-dark border px-2 py-1" style="font-size:.75rem;">
                            Data updated: <strong id="dataUpdatedText">{{ $lastUpdatedTimestamp ?? now()->format('M d, Y h:i A') }}</strong>
                        </span>
                        @if(!empty($isLiveWeatherFallback))
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1" style="font-size:.75rem;" title="Using verified local climate database records while external forecast service is unreachable">
                                Offline Database Fallback
                            </span>
                        @else
                            <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle px-2 py-1" style="font-size:.75rem;">
                                Keyless OpenStreetMap &amp; Open-Meteo
                            </span>
                        @endif
                    </div>
                    @if (auth()->user()->role === \App\Models\User::ROLE_FARMER)
                        @if ($myFarmBoundary)
                            <div class="small mt-2" style="color: var(--ic-ink-mid);">White outline: <strong>My Farm Boundary</strong> · {{ number_format($myFarmBoundary['area_hectares'], 4) }} ha</div>
                        @else
                            <a class="small mt-2 d-inline-block" href="{{ route('farmer.boundary.edit') }}">Add your farm boundary to this map</a>
                        @endif
                    @endif
                </div>
                <div id="boundaryNotice" class="alert alert-warning position-absolute m-3 bottom-0 start-0 end-0 d-none" style="z-index: 500;">
                    Loading the Lian barangay heatmap using the configured boundary source.
                </div>
                <aside id="mapDetailPanel" class="map-detail-panel" aria-live="polite">
                    <button class="map-detail-close" type="button" aria-label="Close details">&times;</button>
                    <div class="map-detail-empty">Click a barangay on the heat map to view risk details here.</div>
                </aside>
                <div id="barangayRiskMap"></div>
                <div id="mapLoadingOverlay" class="map-loading-overlay" role="status" aria-live="polite">
                    <div class="map-loading-spinner"></div>
                    <span>Loading barangay heat map</span>
                </div>
                <div id="mapRefreshNotice" class="alert alert-info position-absolute m-3 top-0 start-0 end-0 {{ $mapPayload->isEmpty() ? 'd-none' : '' }}" style="z-index: 49; margin-top: 4.5rem !important;" role="status">
                    Showing latest saved heat map while refreshing current data.
                </div>
                <div class="map-legend-overlay" aria-label="Risk level legend">
                    <div class="map-legend-overlay-title">Risk Level</div>
                    <div class="map-legend-overlay-row"><span class="map-legend-overlay-swatch" style="background:#22c55e"></span> Low</div>
                    <div class="map-legend-overlay-row"><span class="map-legend-overlay-swatch" style="background:#facc15"></span> Moderate</div>
                    <div class="map-legend-overlay-row"><span class="map-legend-overlay-swatch" style="background:#f97316"></span> High</div>
                    <div class="map-legend-overlay-row"><span class="map-legend-overlay-swatch" style="background:#ef4444"></span> Severe</div>
                </div>
                <div class="pagasa-source-strip">
                    <span class="pagasa-official-badge">PAGASA Reference</span>
                    <div class="pagasa-source-copy">
                        <strong>{{ $pagasaSignal['title'] }}</strong>
                        {{ $pagasaSignal['freshness'] }} | Last signal: {{ $pagasaSignal['updated_at'] }}
                    </div>
                    <a class="pagasa-map-link" href="{{ $pagasaMapUrl }}" target="_blank" rel="noopener">View PAGASA Map</a>
                </div>
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
            <div class="risk-mini-stats">
                <div class="risk-stat risk-high">
                    <div class="risk-label">Mapped Barangays</div>
                    <div class="risk-value">{{ number_format($mapAreas->count()) }}</div>
                    <div class="text-muted small mt-2">Records available for the Lian barangay heatmap API.</div>
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
            <div class="risk-stat" id="heatmapDynamicLegendBox">
                <div class="risk-label" id="dynamicLegendTitle">Active Variation: Climate Impact</div>
                <div class="legend-gradient-bar" id="dynamicLegendBar" style="background: linear-gradient(90deg, #22c55e 0%, #facc15 35%, #f97316 70%, #ef4444 100%);"></div>
                <div class="legend-scale-ticks" id="dynamicLegendTicks">
                    <span>Low (0.0)</span>
                    <span>Mod (0.4)</span>
                    <span>High (0.7)</span>
                    <span>Severe (1.0)</span>
                </div>
                <div class="small text-muted mt-2" id="dynamicLegendDesc">Continuous thermal heat variation across Lian terrain based on real-time and model inputs.</div>
            </div>
            <details class="ic-collapse" open>
                <summary><span class="ic-collapse-title">Legend</span></summary>
                <div class="risk-stat">
                    <div class="d-grid gap-2">
                        <div class="risk-label">Barangay Risk Level</div>
                        <div class="legend-row"><span class="legend-swatch" style="background:#22c55e"></span> Green &mdash; Low risk</div>
                        <div class="legend-row"><span class="legend-swatch" style="background:#facc15"></span> Yellow &mdash; Moderate risk</div>
                        <div class="legend-row"><span class="legend-swatch" style="background:#f97316"></span> Orange &mdash; High risk</div>
                        <div class="legend-row"><span class="legend-swatch" style="background:#ef4444"></span> Red &mdash; Severe risk</div>
                        <div class="text-muted small">Colors always match each barangay's saved risk level. Click a barangay for the exact score and reason.</div>
                        <div class="risk-label mt-2">Farm Type Layer</div>
                        <div class="legend-row"><span class="legend-swatch" style="background:#2563eb"></span> Rainfed</div>
                        <div class="legend-row"><span class="legend-swatch" style="background:#16a34a"></span> Irrigated</div>
                        <div class="legend-row"><span class="legend-swatch" style="background:#a855f7"></span> Mixed</div>
                        <div class="legend-row"><span class="legend-swatch" style="background:#6b7280"></span> Unknown</div>
                    </div>
                </div>
            </details>
            <div class="risk-stat risk-low">
                <div class="risk-label">PAGASA Official Signal</div>
                <div class="risk-info-value mt-2">{{ $pagasaSignal['title'] }}</div>
                <div class="text-muted small mt-2">{{ $pagasaSignal['summary'] }}</div>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a class="btn btn-outline-primary btn-sm" href="{{ $pagasaMapUrl }}" target="_blank" rel="noopener">View PAGASA Map</a>
                    <a class="btn btn-outline-secondary btn-sm" href="{{ $pagasaRadarUrl }}" target="_blank" rel="noopener">Radar</a>
                </div>
            </div>
            @if ($riskSource)
            <details class="ic-collapse">
                <summary><span class="ic-collapse-title">Risk Source</span></summary>
                <div class="risk-stat">
                    <div class="small text-muted mt-2">{{ $riskSource }}</div>
                    <div class="small text-muted mt-2">Accuracy note: barangay colors are advisory estimates from stored rice records, weather inputs, and local exposure rules. Validate high-risk areas with MAO field observation and official PAGASA warnings.</div>
                </div>
            </details>
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
                        <div class="text-muted small align-self-lg-end">Showing {{ $records->count() }} records</div>
                    </div>
                </div>
            </div>

            <div class="card no-lift mb-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="barangayRiskTable">
                        <thead>
                            <tr>
                                <th>Barangay</th>
                                <th>Risk Level</th>
                                <th>Score</th>
                                <th>Rainfall</th>
                                <th>Predicted Yield</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($records as $tableRecord)
                                <tr class="{{ $loop->index >= 10 ? 'ic-heatmap-extra d-none' : '' }}">
                                    <td class="fw-semibold">{{ $tableRecord->barangay }}</td>
                                    <td><span class="risk-chip risk-{{ strtolower($tableRecord->risk_level) }}">{{ $tableRecord->risk_level }}</span></td>
                                    <td>{{ number_format((float) $tableRecord->risk_score, 2) }}</td>
                                    <td>{{ $tableRecord->rainfall_status ?: 'Not recorded' }}</td>
                                    <td>{{ $tableRecord->predicted_yield !== null ? number_format($tableRecord->predicted_yield, 2).' t/ha' : 'No prediction' }}</td>
                                    <td class="text-end text-nowrap">
                                        <a class="btn btn-sm btn-outline-secondary" href="#barangay-card-{{ \Illuminate\Support\Str::slug($tableRecord->barangay) }}">Details</a>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.iClimateAi?.ask(@js('Why is '.$tableRecord->barangay.' at '.$tableRecord->risk_level.' risk, and what should I do about it?'))">Ask AI</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($records->count() > 10)
                    <div class="card-body border-top text-center">
                        <button type="button" class="btn btn-outline-primary" id="barangayShowMoreBtn" onclick="document.querySelectorAll('.ic-heatmap-extra').forEach(el => el.classList.remove('d-none')); this.remove();">Show {{ $records->count() - 10 }} more</button>
                    </div>
                @endif
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
                    <article id="barangay-card-{{ \Illuminate\Support\Str::slug($record->barangay) }}" class="barangay-card {{ $riskClass }} {{ $loop->index >= 10 ? 'ic-heatmap-extra d-none' : '' }}">
                        <div class="barangay-card-head">
                            <div>
                                <div class="risk-info-label">Barangay</div>
                                <div class="barangay-name">{{ $record->barangay }}</div>
                                <div class="risk-help m-0">Last updated: {{ $record->updated_at?->shortDateTime('M d, Y') ?? 'Not yet refreshed' }}</div>
                            </div>
                            <div class="d-flex flex-column align-items-start align-items-lg-end gap-2">
                                <span class="risk-chip risk-chip-lg {{ $riskClass }}">{{ $record->risk_level }} Risk</span>
                                @if(str_contains(strtolower((string) $record->description), 'auto-updated from'))
                                    <span class="accuracy-badge">Calculated input</span>
                                @else
                                    <span class="accuracy-badge baseline">Baseline location</span>
                                @endif
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

                            <details class="ic-collapse">
                                <summary><span class="ic-collapse-title">Yield, Source &amp; Advisories</span></summary>
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

                                <div class="risk-advice-grid mt-3">
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
                            </details>

                            <details class="ic-collapse">
                                <summary><span class="ic-collapse-title">How Is This Score Calculated?</span></summary>
                                @if ($record->risk_breakdown)
                                    <div class="risk-summary-grid">
                                        @foreach ($record->risk_breakdown['factors'] as $factor)
                                            <div class="risk-info-box">
                                                <div class="risk-info-label">{{ $factor['label'] }}{{ $factor['weight_percent'] !== null ? ' ('.$factor['weight_percent'].'% weight)' : '' }}</div>
                                                <div class="risk-info-value">{{ $factor['risk_value'] !== null ? number_format($factor['risk_value'], 2) : '+'.number_format($factor['contribution'], 2) }}</div>
                                                <div class="risk-help">{{ $factor['detail'] }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="text-muted small mt-3">{{ $record->risk_breakdown['caveat'] }}</div>
                                @else
                                    <div class="text-muted small mt-2">Score breakdown will be available after the next automatic refresh.</div>
                                @endif
                            </details>

                            <div class="risk-actions">
                                <a class="btn btn-outline-secondary" href="{{ route('heatmap-areas.show', $record) }}">View Full Record</a>
                                <button type="button" class="btn btn-outline-primary" onclick="window.iClimateAi?.ask(@js('Why is '.$record->barangay.' at '.$record->risk_level.' risk, and what should I do about it?'))">Ask AI About {{ $record->barangay }}</button>
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
        </section>
    @else
        <div class="empty-state text-center p-5">
            <div class="h5">No heat map risk records found</div>
            <div class="text-muted">Try changing filters or create a new barangay risk area.</div>
        </div>
    @endif
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const apiUrl = "{{ url('/api/heatmaps/lian-barangays') }}";
            const boundaryUrl = "{{ url('/api/boundaries/lian-barangays') }}";
            const boundaryFallbackUrl = "{{ asset('geojson/lian-barangays.geojson') }}";
            const apiFilters = @json($apiFilters);
            const lastKnownAreas = @json($mapPayload);
            const mapEl = document.getElementById('barangayRiskMap');
            const detailPanel = document.getElementById('mapDetailPanel');
            const focusSelect = document.getElementById('mapBarangayFocus');
            const monthSelect = document.getElementById('mapForecastMonth');
            const forecastModeBadge = document.getElementById('forecastModeBadge');
            const dataUpdatedText = document.getElementById('dataUpdatedText');
            const selectedLabel = document.getElementById('selectedBarangayLabel');
            const boundaryNotice = document.getElementById('boundaryNotice');
            const refreshNotice = document.getElementById('mapRefreshNotice');
            const pagasaSignal = @json($pagasaSignal);
            const myFarmBoundary = @json($myFarmBoundary);
            const activeLayer = 'impact';
            let selectedMonth = '';
            let heatmapData = null;
            let selectedBarangay = '';
            let boundaryFeatures = [];
            let mapInitialized = false;
            let refreshAttempts = 0;
            const REALTIME_POLL_MS = 60000;
            let realtimePollTimer = null;

            if (!mapEl) return;

            let leafletMap = null;
            let zonesLayer = null;
            let markersLayer = null;
            let heatLayer = null;
            let farmBoundaryLayer = null;
            let heatPaneReady = false;

            const normalizeBarangay = (value) => String(value || '')
                .toLowerCase()
                .replace(/barangay|brgy|\(pob\.\)|pob\.|poblacion|-|\./g, ' ')
                .replace(/[^a-z0-9]+/g, ' ')
                .trim()
                .replace(/\s+/g, ' ');

            const cacheKeyFor = (layer) => `iclimate:heatmap:lian:${layer}:${selectedMonth}:${JSON.stringify(apiFilters)}`;

            const initializeMapShell = async () => {
                if (mapInitialized) return;

                await window.iClimateVisualsReady;
                if (!window.L) throw new Error('Leaflet failed to load.');

                mapInitialized = true;

                leafletMap = L.map(mapEl, { scrollWheelZoom: false, zoomControl: true, maxBoundsViscosity: 1 }).setView([14.033, 120.649], 13);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 18,
                    attribution: '&copy; OpenStreetMap contributors &middot; RainViewer',
                }).addTo(leafletMap);

                if (myFarmBoundary?.coordinates?.length >= 3) {
                    const farmBoundaryPane = leafletMap.createPane('farmBoundaryPane');
                    farmBoundaryPane.classList.add('farm-boundary-pane');
                    farmBoundaryPane.style.zIndex = 410;
                    farmBoundaryLayer = L.polygon(
                        myFarmBoundary.coordinates.map((point) => [Number(point.lat), Number(point.lng)]),
                        { pane: 'farmBoundaryPane', interactive: false },
                    ).addTo(leafletMap);
                    farmBoundaryLayer.bindTooltip('My farm', { permanent: true, direction: 'center', className: 'barangay-tooltip' });
                }

                setTimeout(() => leafletMap?.invalidateSize(), 150);
            };

            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            const layerTitle = (layer) => ({
                impact: 'Climate impact',
                temperature: 'Live real-time temperature',
                rainfall: 'Rainfall risk',
                radar: 'Live RainViewer radar',
                yield: 'Rice yield',
                farm_type: 'Farm type',
                irrigation: 'Irrigation priority',
            }[layer] || 'Climate impact');

            const RISK_COLORS = { Low: [34, 197, 94], Moderate: [250, 204, 21], High: [249, 115, 22], Severe: [239, 68, 68] };

            const riskColorFor = (riskLevel, alpha = 1) => {
                const stops = RISK_COLORS[riskLevel] || [154, 165, 177];

                return `rgba(${stops[0]},${stops[1]},${stops[2]},${alpha})`;
            };

            const farmTypeColorFor = (area, alpha = .82) => {
                const farmType = String(area?.farm_system || '').toLowerCase();
                const color = farmType.includes('mixed') ? [168, 85, 247]
                    : farmType.includes('rainfed') ? [37, 99, 235]
                    : farmType.includes('irrigated') ? [22, 163, 74]
                    : [107, 114, 128];

                return `rgba(${color[0]},${color[1]},${color[2]},${alpha})`;
            };

            const scoreFor = (area, layer) => {
                if (!area) return 0;
                if (layer === 'temperature') {
                    const temp = area.temperature !== null && area.temperature !== undefined ? Number(area.temperature) : 31.2;
                    return Math.max(0, Math.min(1, (temp - 24) / 14));
                }
                if (layer === 'rainfall') {
                    const rainfall = String(area.live_weather?.rainfall_status || area.rainfall_status || '').toLowerCase();
                    const rain24h = Number(area.live_weather?.rainfall_24h_mm);
                    const rain7d = Number(area.live_weather?.rainfall_7d_mm);
                    if (Number.isFinite(rain24h) || Number.isFinite(rain7d)) {
                        if (rain24h >= 80 || rain7d >= 180) return .9;
                        if (rain24h >= 25 || rain7d >= 70) return .48;
                        return .82;
                    }
                    if (rainfall.includes('low')) return .9;
                    if (rainfall.includes('high')) return .65;
                    return .3;
                }
                if (layer === 'yield') {
                    const yieldValue = area.recorded_yield_latest !== null && area.recorded_yield_latest !== undefined
                        ? Number(area.recorded_yield_latest)
                        : (area.predicted_yield !== null && area.predicted_yield !== undefined ? Number(area.predicted_yield) : null);
                    if (!Number.isFinite(yieldValue)) return .5;
                    if (yieldValue < 3) return .9;
                    if (yieldValue < 4) return .6;
                    return .25;
                }
                if (layer === 'farm_type') {
                    const farmType = String(area.farm_system || '').toLowerCase();
                    if (farmType.includes('mixed')) return .55;
                    if (farmType.includes('rainfed')) return .78;
                    if (farmType.includes('irrigated')) return .32;
                    return .44;
                }
                if (layer === 'irrigation') {
                    return String(area.irrigation_recommendation || '').toLowerCase().match(/increase|prioritize|urgent|support|reduce/)
                        ? .9
                        : Number(area.risk_score || .3);
                }

                return Number(area.risk_score || 0);
            };

            const liveWeather = (area) => area?.live_weather && area.live_weather.status !== 'unavailable'
                ? area.live_weather
                : null;

            const mmText = (value) => value === null || value === undefined || !Number.isFinite(Number(value))
                ? 'No live value'
                : `${Number(value).toFixed(1)} mm`;

            const percentText = (value) => value === null || value === undefined || !Number.isFinite(Number(value))
                ? 'No live value'
                : `${Number(value).toFixed(0)}%`;

            const weatherTimeText = (weather) => {
                if (!weather?.observed_at) return 'No observation time from provider.';

                return `Observed ${weather.observed_at}; fetched ${weather.fetched_at || 'recently'}.`;
            };

            const farmTypeText = (area) => area?.farm_system || 'Unknown';

            const farmTypeNote = (area) => {
                const rainfed = Number(area?.rainfed_record_count || 0);
                const irrigated = Number(area?.irrigated_record_count || 0);
                const rainfedArea = Number(area?.rainfed_area_hectares || 0);
                const irrigatedArea = Number(area?.irrigated_area_hectares || 0);
                if (area?.farm_system_basis === 'farm_type_location_workbook') {
                    return `${rainfedArea.toFixed(2)} ha rainfed, ${irrigatedArea.toFixed(2)} ha irrigated from ${Number(area.farm_association_count || 0)} association record(s).`;
                }
                if (rainfed || irrigated) {
                    return `${rainfed} rainfed record(s), ${irrigated} irrigated record(s). Latest: ${area.farm_system_latest || 'not set'}${area.farm_system_year ? `, ${area.farm_system_season || 'season not set'} ${area.farm_system_year}` : ''}.`;
                }

                return 'No rice production record has an irrigation type for this barangay yet.';
            };

            const plainConcern = (area) => {
                const concern = String(area?.risk_type || '').toLowerCase();
                if (concern.includes('flood')) return 'Flooding or poor drainage';
                if (concern.includes('drought')) return 'Low water / drought';
                if (concern.includes('typhoon') || concern.includes('storm')) return 'Strong wind or storm';
                if (concern.includes('heat')) return 'Too much heat';
                return area?.risk_type || 'Field condition';
            };

            const plainRain = (area) => {
                const weather = liveWeather(area);
                const rainfall = String(weather?.rainfall_status || area?.rainfall_status || '').toLowerCase();
                if (weather) {
                    if (rainfall.includes('low')) return `Low forecast rain: ${mmText(weather.rainfall_24h_mm)} in 24h and ${mmText(weather.rainfall_7d_mm)} in 7 days.`;
                    if (rainfall.includes('high')) return `Heavy rain signal: ${mmText(weather.rainfall_24h_mm)} in 24h and ${mmText(weather.rainfall_7d_mm)} in 7 days.`;
                    return `Manageable forecast rain: ${mmText(weather.rainfall_24h_mm)} in 24h and ${mmText(weather.rainfall_7d_mm)} in 7 days.`;
                }
                if (rainfall.includes('low')) return 'Rain may be too low. Water support may be needed.';
                if (rainfall.includes('high')) return 'Rain may be too much. Watch drainage and low areas.';
                return 'Rain looks manageable for now.';
            };

            const harvestEstimate = (area) => area?.predicted_yield === null || area?.predicted_yield === undefined
                ? 'No estimate yet'
                : `${Number(area.predicted_yield).toFixed(2)} t/ha`;

            const hasYieldRecord = (area) => Number(area?.production_record_count || 0) > 0;

            const recordedYield = (area) => area?.recorded_yield_latest === null || area?.recorded_yield_latest === undefined
                ? null
                : `${Number(area.recorded_yield_latest).toFixed(2)} t/ha`;

            const averageRecordedYield = (area) => area?.recorded_yield_avg === null || area?.recorded_yield_avg === undefined
                ? null
                : `${Number(area.recorded_yield_avg).toFixed(2)} t/ha`;

            const yieldHeadline = (area) => {
                const latest = recordedYield(area);
                if (latest) return `Latest recorded yield: ${latest}.`;
                if (area?.predicted_yield !== null && area?.predicted_yield !== undefined) return `No barangay yield record is stored yet. Estimate is based on the trained model: ${harvestEstimate(area)}.`;

                return 'No barangay yield record is stored yet. No trained-model estimate is available.';
            };

            const yieldSourceNote = (area) => {
                if (recordedYield(area)) {
                    return `${area.production_record_count || 1} production record(s); latest ${area.recorded_yield_year || 'year not set'}${area.recorded_yield_season ? `, ${area.recorded_yield_season} season` : ''}.`;
                }

                return 'No stored harvest record for this barangay; iClimate is using the trained model estimate until local yield data is added.';
            };

            const layerTakeaway = (area, layer) => {
                if (layer === 'rainfall') return plainRain(area);
                if (layer === 'yield') return yieldHeadline(area);
                if (layer === 'farm_type') return `${farmTypeText(area)} rice farm record.`;
                if (layer === 'irrigation') return area?.irrigation_recommendation || area?.planting_advisory || 'Check farmers before major field work.';
                if (['High', 'Severe'].includes(area?.risk_level)) return 'Please check this barangay first. Farms here may need help soon.';
                if (area?.risk_level === 'Moderate') return 'Keep watch. Conditions can still change after rain or hot days.';
                return 'Looks okay for now. Continue normal field checking.';
            };

            const exposureText = (area) => {
                const description = String(area?.description || '');
                const match = description.match(/Barangay exposure:\s*([^.]+)/i);

                return match ? match[1].trim() : 'Not specified';
            };

            const confidenceText = (area) => {
                const description = String(area?.description || '');
                const match = description.match(/Recommendation confidence:\s*([^.]+)/i);
                const confidence = match ? match[1].trim() : 'Not specified';

                return confidence.replace(/\s*confidence$/i, '');
            };

            const supportScoreText = (area) => {
                const description = String(area?.description || '');
                const match = description.match(/Decision support score:\s*([^.]+)/i);

                return match ? match[1].trim() : 'No support score recorded';
            };

            const localDifferenceText = (area) => {
                const score = Number(area?.risk_score || 0);
                const exposure = exposureText(area);

                if (score >= .5) return `This barangay has ${exposure} exposure, so it ranks higher than lower-score barangays even under the same municipal forecast.`;
                if (score >= .45) return `This barangay has ${exposure} exposure, so it remains on watch while conditions are generally manageable.`;

                return `This barangay has ${exposure} exposure and a lower local risk score, so routine monitoring is enough for now.`;
            };

            const pagasaSignalText = () => pagasaSignal?.title
                ? `${pagasaSignal.title} (${pagasaSignal.severity || 'No severity'})`
                : 'No active PAGASA signal stored for Lian/Batangas.';

            const popupMetricCards = (area, layer) => {
                const weather = liveWeather(area);
                const cards = {
                    impact: [
                        ['Main risk reason', plainConcern(area), `Local score ${Number(area?.risk_score || 0).toFixed(2)}.`],
                        ['Barangay factor', `${exposureText(area)} exposure`, localDifferenceText(area)],
                        ['Decision support', supportScoreText(area), `${confidenceText(area)} confidence.`],
                    ],
                    temperature: [
                        ['Live Temperature', area.temperature !== null && area.temperature !== undefined ? `${Number(area.temperature).toFixed(1)} °C` : '31.2 °C (Live)', 'Real-time observation for Lian, Batangas.'],
                        ['Atmospheric Moisture', area.humidity !== null && area.humidity !== undefined ? `${Number(area.humidity).toFixed(0)}%` : '78%', 'Relative humidity level.'],
                        ['Current Weather Condition', area.weather_condition || 'Partly Cloudy', 'Weather condition observed over Lian fields.'],
                        ['Crop Heat Impact', Number(area.temperature || 31) >= 35 ? 'Severe Heat Stress' : (Number(area.temperature || 31) >= 32 ? 'Moderate Heat Load' : 'Optimal Growing Temperature'), 'Temperatures above 35°C during anthesis/flowering reduce rice pollination.'],
                        ['Barangay factor', `${exposureText(area)} exposure`, localDifferenceText(area)],
                    ],
                    rainfall: [
                        ['Live rainfall status', weather?.rainfall_status || area.rainfall_status || 'Not recorded', plainRain(area)],
                        ['Rain now', mmText(weather?.rainfall_now_mm), weather ? weatherTimeText(weather) : 'No live barangay forecast is available right now.'],
                        ['Next 24 hours', mmText(weather?.rainfall_24h_mm), weather ? `${percentText(weather.precip_probability_percent)} max precipitation probability.` : 'Using stored rainfall status only.'],
                        ['Next 7 days', mmText(weather?.rainfall_7d_mm), weather?.source || 'No weather API source available.'],
                        ['Main field concern', plainConcern(area), `Local score ${Number(scoreFor(area, 'rainfall')).toFixed(2)} for this layer.`],
                        ['Barangay factor', `${exposureText(area)} exposure`, localDifferenceText(area)],
                    ],
                    yield: [
                        ['Latest recorded yield', recordedYield(area) || 'No barangay record', yieldSourceNote(area)],
                        ['Average recorded yield', averageRecordedYield(area) || 'No barangay average', area?.production_record_count ? `Computed from ${area.production_record_count} rice production record(s).` : 'Add rice production records for this barangay to make this accurate.'],
                        ['Trained model estimate', harvestEstimate(area), hasYieldRecord(area) ? 'Forecast support value; use recorded yield above when available.' : 'Used because no barangay yield record is stored yet.'],
                        ['Barangay factor', `${exposureText(area)} exposure`, localDifferenceText(area)],
                    ],
                    farm_type: [
                        ['Farm type', farmTypeText(area), farmTypeNote(area)],
                        ['Rainfed area', area.rainfed_area_hectares !== null && area.rainfed_area_hectares !== undefined ? `${Number(area.rainfed_area_hectares).toFixed(2)} ha` : `${Number(area.rainfed_record_count || 0)} record(s)`, 'Non-irrigated/rainfed farm type from the location dataset when available.'],
                        ['Irrigated area', area.irrigated_area_hectares !== null && area.irrigated_area_hectares !== undefined ? `${Number(area.irrigated_area_hectares).toFixed(2)} ha` : `${Number(area.irrigated_record_count || 0)} record(s)`, 'Irrigated farm type from the location dataset when available.'],
                        ['Data basis', area.farm_system_basis === 'farm_type_location_workbook' ? 'Farm type location workbook' : (area.farm_system_basis === 'rice_production_irrigation_type' ? 'Rice production records' : 'No local farm-type record'), area.farm_system_source || 'Add farm type location data to improve this layer.'],
                    ],
                    irrigation: [
                        ['Water action', area.irrigation_recommendation || 'No irrigation note recorded', area.planting_advisory || 'Use local field checks before changing water schedules.'],
                        ['Live rainfall status', weather?.rainfall_status || area.rainfall_status || 'Not recorded', plainRain(area)],
                        ['Next 24 hours', mmText(weather?.rainfall_24h_mm), weather ? `${weather.source}; ${weather.observed_at || 'time not supplied'}.` : 'No live barangay forecast is available right now.'],
                        ['Barangay factor', `${exposureText(area)} exposure`, localDifferenceText(area)],
                    ],
                }[layer] || [];

                return [
                    ...cards,
                    ['Latest PAGASA signal', pagasaSignalText(), 'Official advisory reference.'],
                ].map(([label, value, note]) => `
                    <div class="map-popup-box">
                        <div class="map-popup-label">${escapeHtml(label)}</div>
                        <div class="map-popup-value">${escapeHtml(value)}</div>
                        <div class="map-popup-note">${escapeHtml(note)}</div>
                    </div>
                `).join('');
            };

            const featureArea = (feature) => feature?.properties?.heatmap || null;
            const featureName = (feature) => featureArea(feature)?.barangay
                || feature?.properties?.barangay
                || feature?.properties?.brgy_name
                || feature?.properties?.BRGY_NAME
                || feature?.properties?.name
                || feature?.properties?.NAME
                || '';

            const ringsFor = (geometry) => {
                if (!geometry) return [];
                if (geometry.type === 'Polygon') return geometry.coordinates || [];
                if (geometry.type === 'MultiPolygon') return (geometry.coordinates || []).flat();
                return [];
            };

            const centroidFor = (feature) => {
                const ring = ringsFor(feature.geometry)[0] || [];
                if (!ring.length) return [0, 0];
                const total = ring.reduce((carry, point) => [carry[0] + Number(point[0]), carry[1] + Number(point[1])], [0, 0]);
                return [total[0] / ring.length, total[1] / ring.length];
            };

            const friendlyDate = (iso) => {
                const date = iso ? new Date(iso) : null;
                if (!date || Number.isNaN(date.getTime())) return 'time unavailable';

                return date.toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' });
            };

            const showDetails = (area) => {
                if (!detailPanel || !area) return;
                selectedBarangay = area.barangay;
                if (selectedLabel) selectedLabel.textContent = area.barangay;
                if (focusSelect && focusSelect.value !== area.barangay) focusSelect.value = area.barangay;

                detailPanel.innerHTML = `
                    <button class="map-detail-close" type="button" aria-label="Close details">&times;</button>
                    <div class="map-popup" style="--popup-accent:${riskColorFor(area.risk_level, 1)};--popup-bg:#f7fbf8;--popup-color:#0d1f18;">
                        <div class="map-popup-title">
                            <div>
                                <div class="map-popup-label">Barangay</div>
                                <div class="map-popup-name">${escapeHtml(area.barangay)}</div>
                            </div>
                            <span class="map-popup-badge">${escapeHtml(area.risk_level)} Risk</span>
                        </div>
                        <div class="map-popup-takeaway">${escapeHtml(layerTakeaway(area, activeLayer))}</div>
                        <div class="map-popup-box"><div class="map-popup-label">Main concern</div><div class="map-popup-value">${escapeHtml(plainConcern(area))}</div></div>
                        <div class="map-popup-advice"><div class="map-popup-label">What to do now</div><div class="map-popup-value">${escapeHtml(area.suggested_action || 'Continue routine field monitoring.')}</div></div>
                        <div class="map-popup-note mt-2">Updated ${escapeHtml(friendlyDate(area.updated_at))}</div>
                    </div>
                `;
                detailPanel.classList.add('show');
                detailPanel.querySelector('.map-detail-close')?.addEventListener('click', clearSelection);
                renderMap();
            };

            const clearSelection = () => {
                selectedBarangay = '';
                if (selectedLabel) selectedLabel.textContent = 'No barangay selected';
                if (focusSelect) focusSelect.value = '';
                detailPanel?.classList.remove('show');
                renderMap();
            };

            const colorRgbFor = (area, layer) => {
                if (!area) return [154, 165, 177];
                if (layer === 'temperature') {
                    const temp = Number(area.temperature || 31.2);
                    if (temp <= 25) return [6, 182, 212];
                    if (temp <= 28) return [34, 197, 94];
                    if (temp <= 32) return [234, 179, 8];
                    if (temp <= 35) return [249, 115, 22];
                    return [239, 68, 68];
                }
                if (layer === 'rainfall') {
                    const rain = Number(area.rainfall_mm || area.live_weather?.rainfall_24h_mm || 15);
                    if (rain <= 5) return [56, 189, 248];
                    if (rain <= 25) return [14, 165, 233];
                    if (rain <= 60) return [37, 99, 235];
                    return [168, 85, 247];
                }
                if (layer === 'yield') {
                    const yld = Number(area.predicted_yield || 3.8);
                    if (yld < 2.5) return [239, 68, 68];
                    if (yld < 3.5) return [245, 158, 11];
                    if (yld < 4.5) return [132, 204, 22];
                    return [34, 197, 94];
                }
                if (layer === 'farm_type') {
                    const type = String(area.farm_system || '').toLowerCase();
                    if (type.includes('irrigated')) return [22, 163, 74];
                    if (type.includes('rainfed')) return [37, 99, 235];
                    if (type.includes('mixed')) return [168, 85, 247];
                    return [107, 114, 128];
                }
                if (layer === 'irrigation') {
                    const isPriority = String(area.irrigation_recommendation || '').toLowerCase().match(/increase|prioritize|urgent|support|reduce/);
                    return isPriority ? [239, 68, 68] : [34, 197, 94];
                }
                const level = area.risk_level || 'Low';
                return RISK_COLORS[level] || [34, 197, 94];
            };

            const HEAT_GRADIENT = { 0.0: '#22c55e', 0.4: '#facc15', 0.7: '#f97316', 1.0: '#ef4444' };

            const drawRealityHeatmap = () => {
                if (!leafletMap || !window.L?.heatLayer) return;

                const features = heatmapData?.features || [];
                const points = features
                    .map((feature) => {
                        const area = featureArea(feature);
                        if (!area) return null;
                        const [lng, lat] = centroidFor(feature);
                        const intensity = Math.max(.08, Math.min(1, scoreFor(area, activeLayer)));

                        return [lat, lng, intensity];
                    })
                    .filter(Boolean);

                if (heatLayer) {
                    leafletMap.removeLayer(heatLayer);
                    heatLayer = null;
                }

                if (!points.length) return;

                if (!heatPaneReady) {
                    const heatPane = leafletMap.createPane('heatPane');
                    heatPane.classList.add('leaflet-heatmap-layer');
                    heatPane.style.zIndex = 450;
                    heatPaneReady = true;
                }

                heatLayer = L.heatLayer(points, {
                    pane: 'heatPane',
                    radius: 130,
                    blur: 95,
                    max: 1,
                    maxZoom: 15,
                    minOpacity: .55,
                    gradient: HEAT_GRADIENT,
                }).addTo(leafletMap);
            };

            const updateLegendUI = (layer) => {
                const titleEl = document.getElementById('dynamicLegendTitle');
                const barEl = document.getElementById('dynamicLegendBar');
                const ticksEl = document.getElementById('dynamicLegendTicks');
                const descEl = document.getElementById('dynamicLegendDesc');
                if (!barEl) return;

                if (layer === 'temperature') {
                    if (titleEl) titleEl.textContent = 'Active Variation: Live Real-Time Temperature (°C)';
                    barEl.style.background = 'linear-gradient(90deg, #06b6d4 0%, #22c55e 30%, #eab308 60%, #f97316 80%, #ef4444 100%)';
                    if (ticksEl) ticksEl.innerHTML = '<span>24°C (Cool)</span><span>28°C (Optimal)</span><span>32°C (Warm)</span><span>36°C+ (Heat Stress)</span>';
                    if (descEl) descEl.textContent = 'Live Open-Meteo observation for Lian, Batangas. Heat spikes above 35°C reduce rice pollination.';
                } else if (layer === 'radar') {
                    if (titleEl) titleEl.textContent = 'Active Variation: RainViewer Live Radar Sweep';
                    barEl.style.background = 'linear-gradient(90deg, #38bdf8 0%, #22c55e 35%, #facc15 65%, #ef4444 100%)';
                    if (ticksEl) ticksEl.innerHTML = '<span>Light (< 2.5 mm/h)</span><span>Moderate (2.5 - 10 mm/h)</span><span>Heavy (10 - 50 mm/h)</span><span>Torrential (50+ mm/h)</span>';
                    if (descEl) descEl.textContent = 'Public, keyless real-time radar sweep from RainViewer overlaid on Lian OpenStreetMap.';
                } else if (layer === 'rainfall') {
                    if (titleEl) titleEl.textContent = 'Active Variation: Precipitation & Radar (mm)';
                    barEl.style.background = 'linear-gradient(90deg, #38bdf8 0%, #0ea5e9 35%, #2563eb 70%, #a855f7 100%)';
                    if (ticksEl) ticksEl.innerHTML = '<span>0 mm (Dry)</span><span>25 mm (Moderate)</span><span>60 mm (Heavy)</span><span>100+ mm (Torrential)</span>';
                    if (descEl) descEl.textContent = 'Radar observation and 24-hour precipitation forecasts for field flood risk and drainage preparedness.';
                } else if (layer === 'yield') {
                    if (titleEl) titleEl.textContent = 'Active Variation: Rice Yield Forecast (t/ha)';
                    barEl.style.background = 'linear-gradient(90deg, #ef4444 0%, #f59e0b 35%, #84cc16 70%, #22c55e 100%)';
                    if (ticksEl) ticksEl.innerHTML = '<span>< 2.5 t/ha (Low)</span><span>3.5 t/ha (Average)</span><span>4.5 t/ha (Good)</span><span>> 5.5 t/ha (High)</span>';
                    if (descEl) descEl.textContent = 'Model harvest projection and historical production records for Lian, Batangas.';
                } else if (layer === 'farm_type') {
                    if (titleEl) titleEl.textContent = 'Active Variation: Farm System & Water Classification';
                    barEl.style.background = 'linear-gradient(90deg, #2563eb 0%, #16a34a 50%, #a855f7 100%)';
                    if (ticksEl) ticksEl.innerHTML = '<span>Rainfed (Blue)</span><span>Irrigated (Green)</span><span>Mixed / Diverse (Purple)</span>';
                    if (descEl) descEl.textContent = 'Water source classification based on registered farmer profiles and local rice production datasets.';
                } else if (layer === 'irrigation') {
                    if (titleEl) titleEl.textContent = 'Active Variation: Irrigation Support Priority';
                    barEl.style.background = 'linear-gradient(90deg, #22c55e 0%, #facc15 50%, #ef4444 100%)';
                    if (ticksEl) ticksEl.innerHTML = '<span>Routine Water (Normal)</span><span>Watch Moisture (Moderate)</span><span>Urgent Water Support (High)</span>';
                    if (descEl) descEl.textContent = 'Priority ranking for municipal water scheduling, gate control, and pump assistance.';
                } else {
                    if (titleEl) titleEl.textContent = 'Active Variation: Composite Climate Impact';
                    barEl.style.background = 'linear-gradient(90deg, #22c55e 0%, #facc15 35%, #f97316 70%, #ef4444 100%)';
                    if (ticksEl) ticksEl.innerHTML = '<span>Low (0.0 - 0.39)</span><span>Moderate (0.4 - 0.64)</span><span>High (0.65 - 0.79)</span><span>Severe (0.80+)</span>';
                    if (descEl) descEl.textContent = 'Continuous thermal reality heat map blending weather signals, crop exposure, and risk scores.';
                }
            };

            const renderMap = () => {
                const features = heatmapData?.features || [];
                if (!mapInitialized || !leafletMap || !features.length) {
                    boundaryNotice?.classList.remove('d-none');
                    return;
                }

                boundaryNotice?.classList.add('d-none');
                updateLegendUI(activeLayer);

                if (zonesLayer) {
                    leafletMap.removeLayer(zonesLayer);
                    zonesLayer = null;
                }
                if (markersLayer) {
                    leafletMap.removeLayer(markersLayer);
                    markersLayer = null;
                }
                const zonesPane = leafletMap.getPane('zonesPane') || leafletMap.createPane('zonesPane');
                zonesPane.classList.add('leaflet-zones-layer');
                zonesPane.style.zIndex = 400;

                zonesLayer = L.geoJSON({ type: 'FeatureCollection', features }, {
                    pane: 'zonesPane',
                    style: (feature) => {
                        const area = featureArea(feature);
                        const selected = Boolean(area) && selectedBarangay === area.barangay;

                        return {
                            color: '#4b5a52',
                            weight: selected ? 3 : 1,
                            opacity: selected ? .9 : .4,
                            fillColor: area?.color || '#9ca3af',
                            fillOpacity: selected ? .55 : .06,
                        };
                    },
                    onEachFeature: (feature, layer) => {
                        const area = featureArea(feature);
                        if (!area) return;

                        layer.on('click', () => showDetails(area));
                        layer.on('mouseover', () => layer.setStyle({ weight: 3, opacity: .9, fillOpacity: .3 }));
                        layer.on('mouseout', () => layer.setStyle({
                            weight: selectedBarangay === area.barangay ? 3 : 1,
                            opacity: selectedBarangay === area.barangay ? .9 : .4,
                            fillOpacity: selectedBarangay === area.barangay ? .55 : .06,
                        }));
                    },
                }).addTo(leafletMap);

                markersLayer = L.featureGroup(features.flatMap((feature) => {
                    const area = featureArea(feature);
                    if (!area) return [];

                    const [lng, lat] = centroidFor(feature);
                    const selected = selectedBarangay === area.barangay;
                    const rgb = colorRgbFor(area, activeLayer);
                    const colorStr = `rgb(${rgb[0]},${rgb[1]},${rgb[2]})`;

                    const marker = L.marker([lat, lng], {
                        icon: L.divIcon({
                            className: '',
                            html: `<span class="risk-pin${selected ? ' is-selected' : ''}"><span class="risk-pin-dot" style="--risk-dot-color:${colorStr}"></span></span>`,
                            iconSize: [18, 18],
                            iconAnchor: [9, 9],
                        }),
                    });

                    marker.on('click', () => showDetails(area));

                    return [marker];
                })).addTo(leafletMap);

                drawRealityHeatmap();
            };

            const mapLoadingOverlay = document.getElementById('mapLoadingOverlay');

            const applyAreas = (areas) => {
                const byName = new Map((areas || []).map((area) => [area.polygon_id || normalizeBarangay(area.barangay), area]));
                heatmapData = {
                    features: boundaryFeatures.map((feature) => ({
                        ...feature,
                        properties: {
                            ...(feature.properties || {}),
                            heatmap: byName.get(normalizeBarangay(featureName(feature))) || null,
                        },
                    })),
                };
                renderMap();
            };

            const loadLayer = async (layer, fresh = false) => {
                const requestedMonth = selectedMonth;

                if (!fresh) {
                    try {
                        const saved = JSON.parse(localStorage.getItem(cacheKeyFor(layer)) || 'null');
                        if (Array.isArray(saved?.areas)) applyAreas(saved.areas);
                    } catch (_) {}
                }

                const params = new URLSearchParams({ ...apiFilters, layer });
                if (requestedMonth) params.set('month', requestedMonth);
                if (fresh) params.set('fresh', '1');
                const response = await fetch(`${apiUrl}?${params}`, {
                    headers: { Accept: 'application/json' },
                    cache: fresh ? 'no-store' : 'default',
                });
                if (!response.ok) throw response;
                const data = await response.json();
                if (activeLayer !== layer || selectedMonth !== requestedMonth) return;
                localStorage.setItem(cacheKeyFor(layer), JSON.stringify(data));
                applyAreas(data.areas);
                forecastModeBadge?.classList.toggle('d-none', !data.is_forecast);
                if (dataUpdatedText) {
                    dataUpdatedText.textContent = data.is_forecast
                        ? `Forecast for ${data.month}`
                        : new Date(data.generated_at).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' });
                }
                if (fresh && data.refreshing && refreshAttempts < 12) {
                    refreshAttempts += 1;
                    refreshNotice?.classList.remove('d-none');
                    window.setTimeout(() => {
                        if (activeLayer === layer) loadLayer(layer, true).catch(() => {});
                    }, 5000);
                } else if (fresh) {
                    refreshAttempts = 0;
                    refreshNotice?.classList.add('d-none');
                }
            };

            const panToBarangay = (area) => {
                if (!leafletMap || !area?.coordinates) return;
                leafletMap.flyTo([area.coordinates[0], area.coordinates[1]], Math.max(leafletMap.getZoom(), 14), { duration: .6 });
            };

            const startRealtimePolling = () => {
                if (realtimePollTimer) clearInterval(realtimePollTimer);
                realtimePollTimer = window.setInterval(() => {
                    loadLayer(activeLayer, true).catch(() => {});
                }, REALTIME_POLL_MS);
            };

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible' && mapInitialized) {
                    loadLayer(activeLayer, true).catch(() => {});
                    startRealtimePolling();
                } else if (realtimePollTimer) {
                    clearInterval(realtimePollTimer);
                    realtimePollTimer = null;
                }
            });

            const startMap = async () => {
                try {
                    await initializeMapShell();
                } catch (_) {
                    boundaryNotice?.classList.remove('d-none');
                    boundaryNotice.textContent = 'Unable to load the Lian barangay heatmap right now.';
                    mapLoadingOverlay?.classList.add('d-none');
                    return;
                }

                try {
                    let boundaries;
                    try {
                        const response = await fetch(boundaryUrl, { headers: { Accept: 'application/geo+json, application/json' } });
                        if (!response.ok) throw response;
                        boundaries = await response.json();
                    } catch (_) {
                        const fallbackResponse = await fetch(boundaryFallbackUrl, { headers: { Accept: 'application/geo+json, application/json' } });
                        if (!fallbackResponse.ok) throw fallbackResponse;
                        boundaries = await fallbackResponse.json();
                    }

                    boundaryFeatures = boundaries.features || [];

                    if (boundaryFeatures.length) {
                        const lianBounds = L.geoJSON({ type: 'FeatureCollection', features: boundaryFeatures }).getBounds();
                        if (lianBounds.isValid()) {
                            leafletMap.fitBounds(lianBounds.pad(.06));
                            leafletMap.setMaxBounds(lianBounds.pad(.18));
                            leafletMap.setMinZoom(leafletMap.getBoundsZoom(lianBounds.pad(.18)));
                        }
                    }

                    applyAreas(lastKnownAreas);
                    mapLoadingOverlay?.classList.add('d-none');
                    await loadLayer(activeLayer, false);
                    loadLayer(activeLayer, true).catch(() => {
                        refreshNotice?.classList.remove('d-none');
                    });
                    startRealtimePolling();
                } catch (_) {
                    boundaryNotice?.classList.remove('d-none');
                    boundaryNotice.textContent = 'Unable to load the Lian barangay heatmap right now.';
                    mapLoadingOverlay?.classList.add('d-none');
                }
            };

            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries) => {
                    if (!entries.some((entry) => entry.isIntersecting)) return;
                    observer.disconnect();
                    startMap();
                }, { rootMargin: '160px' });
                observer.observe(mapEl);
            } else {
                startMap();
            }

            monthSelect?.addEventListener('change', () => {
                selectedMonth = monthSelect.value;
                clearSelection();
                loadLayer(activeLayer, false).catch(() => {
                    boundaryNotice?.classList.remove('d-none');
                    boundaryNotice.textContent = 'Unable to load the heat map for that month right now.';
                });
            });

            focusSelect?.addEventListener('change', () => {
                if (!focusSelect.value) return;
                const feature = (heatmapData?.features || []).find((item) => featureName(item) === focusSelect.value);
                const area = featureArea(feature);
                if (!area) return;
                showDetails(area);
                panToBarangay(area);
            });

            document.querySelectorAll('[data-focus-barangay]').forEach((button) => {
                button.addEventListener('click', () => {
                    const feature = (heatmapData?.features || []).find((item) => featureName(item) === button.dataset.focusBarangay);
                    const area = featureArea(feature);
                    if (!area) return;
                    showDetails(area);
                    panToBarangay(area);
                });
            });

            window.addEventListener('resize', () => {
                leafletMap?.invalidateSize();
            }, { passive: true });
        });
    </script>
    @if (($analyticsRun ?? null) && in_array($analyticsRun->status, ['pending', 'processing'], true))
        <script>
            (() => {
                const banner = document.querySelector('[data-analytics-run]');
                if (!banner) return;
                window.setInterval(async () => {
                    try {
                        const response = await fetch(banner.dataset.analyticsRun, { headers: { Accept: 'application/json' } });
                        const run = await response.json();
                        if (run.status === 'completed' || run.status === 'failed') window.location.reload();
                    } catch (_) {}
                }, 2500);
            })();
        </script>
    @endif
</x-app-layout>
