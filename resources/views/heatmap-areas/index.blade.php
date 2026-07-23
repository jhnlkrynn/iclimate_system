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
        'data_quality' => str_contains(strtolower((string) $area->description), 'auto-updated from')
            ? 'Calculated from weather, yield, and local exposure inputs'
            : 'Baseline barangay location; refresh needed for live risk',
        'is_baseline' => ! str_contains(strtolower((string) $area->description), 'auto-updated from'),
        'updated_at' => $area->updated_at?->format('M d, Y g:i A'),
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
        'updated_at' => $latestPagasaAdvisory->valid_from?->format('M d, Y g:i A') ?: $latestPagasaAdvisory->created_at?->format('M d, Y g:i A'),
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
@endphp

<x-app-layout>
    <style>
        .heatmap-grid { display: grid; grid-template-columns: minmax(270px, 320px) minmax(0, 1fr); gap: 1rem; align-items: stretch; }
        .heatmap-grid > .heatmap-main { grid-column: 2; grid-row: 1; }
        .heatmap-grid > .risk-side { grid-column: 1; grid-row: 1; }
        .heatmap-main { display: flex; flex-direction: column; gap: 1rem; min-height: 100%; }
        .map-shell { position: relative; overflow: hidden; display: flex; flex: 1 1 auto; flex-direction: column; border: 1.5px solid #e8e0d0; border-radius: 12px; background: #fff; box-shadow: 0 .9rem 2rem rgba(13,31,24,.07); min-height: 0; }
        #barangayRiskMap {
            position: relative;
            flex: 1 1 auto;
            height: auto;
            min-height: 640px;
            overflow: hidden;
            background: #07160f;
        }
        .api-map-tiles,
        .api-heatmap-canvas,
        .api-heatmap-svg {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
        }
        .api-map-tiles {
            z-index: 0;
            overflow: hidden;
            background: #d8e6df;
        }
        .api-map-tile {
            position: absolute;
            width: 256px;
            height: 256px;
            image-rendering: auto;
            user-select: none;
        }
        .api-map-tile.street { filter: saturate(.98) contrast(1.02) brightness(.98); }
        .api-map-attribution {
            position: absolute;
            right: .45rem;
            bottom: .35rem;
            z-index: 4;
            border-radius: 6px;
            background: rgba(255,255,255,.82);
            color: #315044;
            padding: .16rem .35rem;
            font-size: .68rem;
            font-weight: 800;
            text-decoration: none;
        }
        .api-heatmap-canvas { z-index: 1; filter: blur(10px) saturate(1.45) contrast(1.08); opacity: .72; mix-blend-mode: multiply; transform: scale(1.035); }
        .api-heatmap-svg { z-index: 2; }
        .api-heatmap-region {
            cursor: pointer;
            stroke: rgba(20,20,20,.28);
            stroke-width: .0015;
            vector-effect: non-scaling-stroke;
            transition: fill-opacity .16s ease, stroke-width .16s ease, stroke .16s ease;
        }
        .api-heatmap-region:hover,
        .api-heatmap-region.is-selected {
            stroke: rgba(20,20,20,.72);
            stroke-width: .003;
            fill-opacity: .08;
        }
        .api-heatmap-label {
            pointer-events: none;
            fill: #111827;
            paint-order: stroke;
            stroke: rgba(255,255,255,.92);
            stroke-width: .005;
            font-size: .0105px;
            font-weight: 900;
            opacity: 1;
            text-anchor: middle;
            dominant-baseline: central;
        }
        .api-heatmap-label.is-selected { font-size: .014px; stroke-width: .006; }
        .easy-read-map .api-heatmap-label { font-size: .012px; stroke-width: .0055; }
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
        .layer-btn { min-height: 42px; border: 1px solid #d4edda; border-radius: 8px; background: rgba(255,255,255,.94); color: #1b2b23; padding: .55rem .72rem; font-size: .82rem; font-weight: 900; box-shadow: 0 .5rem 1.2rem rgba(13,31,24,.08); white-space: nowrap; }
        .layer-btn.active { background: #1a3a2a; border-color: #1a3a2a; color: #fff; }
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
            font-size: .76rem;
            font-weight: 900;
            white-space: nowrap;
        }
        .pagasa-source-copy { min-width: 0; font-size: .78rem; font-weight: 800; line-height: 1.25; }
        .pagasa-source-copy strong { display: block; color: #fff; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .pagasa-map-link {
            flex: 0 0 auto;
            border-radius: 8px;
            border: 1px solid #f0c36a;
            background: #e8a73d;
            color: #0d1f18;
            padding: .52rem .7rem;
            font-size: .78rem;
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
        .risk-stat, .risk-side .map-insight { position: relative; overflow: hidden; border: 1px solid rgba(149,213,178,.18); border-radius: 12px; background: rgba(13,31,24,.96); padding: 1rem; box-shadow: 0 .8rem 1.8rem rgba(13,31,24,.06); }
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
            .heatmap-grid > .heatmap-main, .heatmap-grid > .risk-side { grid-column: auto; grid-row: auto; }
            .risk-side { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
            #barangayRiskMap { height: 520px; min-height: 520px; }
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
            .pagasa-source-strip {
                position: relative;
                inset: auto;
                display: grid;
                margin: .75rem;
            }
            .pagasa-map-link { width: 100%; text-align: center; }
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
            .map-popup-title { display: grid; gap: .5rem; }
            .map-popup-badge { justify-self: start; }
            .barangay-tooltip { font-size: .68rem; max-width: 120px; white-space: normal; text-align: center; }
        }
        @media (max-width: 420px) {
            .map-popup { font-size: .86rem; }
            .map-popup-name { font-size: 1rem; }
            .map-popup-box, .map-popup-advice, .map-popup-takeaway { padding: .58rem .62rem; }
            .map-popup-source { word-break: break-word; }
            .pagasa-official-badge { width: 100%; justify-content: center; }
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
                <p class="mb-0 text-white-50">API-rendered Lian barangay heatmap, with PAGASA used as the official weather reference source.</p>
            </div>
            <div class="d-flex flex-wrap gap-2 align-self-start align-self-lg-end action-cluster">
                <a class="btn btn-warning" href="{{ $pagasaMapUrl }}" target="_blank" rel="noopener">View PAGASA Map</a>
                @if ($canManage)
                    <a class="btn btn-light" href="{{ route('heatmap-areas.create') }}">Create Risk Area</a>
                @endif
            </div>
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
                    <span class="pagasa-official-badge">PAGASA Official Source</span>
                    <button class="layer-btn active" type="button" data-layer="impact" aria-pressed="true">Climate Impact</button>
                    <button class="layer-btn" type="button" data-layer="rainfall" aria-pressed="false">Rainfall Risk</button>
                    <button class="layer-btn" type="button" data-layer="farm_type" aria-pressed="false">Farm Type</button>
                    <button class="layer-btn" type="button" data-layer="irrigation" aria-pressed="false">Irrigation Priority</button>
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
                    Loading the Lian barangay heatmap API. If the official boundary API is unavailable, iClimate will use its local fallback boundary file.
                </div>
                <aside id="mapDetailPanel" class="map-detail-panel" aria-live="polite">
                    <button class="map-detail-close" type="button" aria-label="Close details">&times;</button>
                    <div class="map-detail-empty">Click a barangay on the heat map to view risk details here.</div>
                </aside>
                <div id="barangayRiskMap"></div>
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
            <div class="risk-stat">
                <div class="risk-label mb-3">Legend</div>
                <div class="d-grid gap-2">
                    <div class="legend-row"><span class="legend-swatch" style="background:#2c7bb6"></span> Low risk</div>
                    <div class="legend-row"><span class="legend-swatch" style="background:#fff34d"></span> Moderate risk</div>
                    <div class="legend-row"><span class="legend-swatch" style="background:#fdae21"></span> High risk</div>
                    <div class="legend-row"><span class="legend-swatch" style="background:linear-gradient(90deg,#d7191c,#7f0000)"></span> Critical / severe risk</div>
                    <div class="risk-label mt-2">Farm Type Layer</div>
                    <div class="legend-row"><span class="legend-swatch" style="background:#2563eb"></span> Rainfed</div>
                    <div class="legend-row"><span class="legend-swatch" style="background:#16a34a"></span> Irrigated</div>
                    <div class="legend-row"><span class="legend-swatch" style="background:#a855f7"></span> Mixed</div>
                    <div class="legend-row"><span class="legend-swatch" style="background:#6b7280"></span> Unknown</div>
                </div>
            </div>
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
            <div class="risk-stat">
                <div class="risk-label">Risk Source</div>
                <div class="small text-muted mt-2">{{ $riskSource }}</div>
                <div class="small text-muted mt-2">Accuracy note: barangay colors are advisory estimates from stored rice records, weather inputs, and local exposure rules. Validate high-risk areas with MAO field observation and official PAGASA warnings.</div>
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

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const apiUrl = "{{ url('/api/heatmaps/lian-barangays') }}";
            const mapEl = document.getElementById('barangayRiskMap');
            const detailPanel = document.getElementById('mapDetailPanel');
            const focusSelect = document.getElementById('mapBarangayFocus');
            const selectedLabel = document.getElementById('selectedBarangayLabel');
            const activeLayerLabel = document.getElementById('activeLayerLabel');
            const boundaryNotice = document.getElementById('boundaryNotice');
            const pagasaSignal = @json($pagasaSignal);
            let activeLayer = 'impact';
            const easyReadMode = false;
            let heatmapData = null;
            let selectedBarangay = '';

            if (!mapEl) return;

            mapEl.innerHTML = '<div class="api-map-tiles" aria-hidden="true"></div><canvas class="api-heatmap-canvas" aria-hidden="true"></canvas><svg class="api-heatmap-svg" role="img" aria-label="Lian barangay risk heatmap"></svg><a class="api-map-attribution" href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">&copy; OpenStreetMap contributors</a>';
            const tileLayer = mapEl.querySelector('.api-map-tiles');
            const canvas = mapEl.querySelector('canvas');
            const svg = mapEl.querySelector('svg');

            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            const layerTitle = (layer) => ({
                impact: 'Climate impact',
                rainfall: 'Rainfall risk',
                yield: 'Rice yield',
                farm_type: 'Farm type',
                irrigation: 'Irrigation priority',
            }[layer] || 'Climate impact');

            const colorFor = (value, alpha = 1) => {
                const stops = value >= .88 ? [127, 0, 0]
                    : value >= .74 ? [215, 25, 28]
                    : value >= .58 ? [253, 174, 33]
                    : value >= .42 ? [255, 243, 77]
                    : value >= .26 ? [44, 186, 108]
                    : value >= .12 ? [46, 199, 201]
                    : [44, 123, 182];

                return `rgba(${stops[0]},${stops[1]},${stops[2]},${alpha})`;
            };

            const visualScoreFor = (area, layer, features) => {
                const raw = scoreFor(area, layer);
                const scores = features
                    .map((feature) => scoreFor(featureArea(feature), layer))
                    .filter((score) => Number.isFinite(score));
                const min = Math.min(...scores);
                const max = Math.max(...scores);

                if (!Number.isFinite(min) || !Number.isFinite(max) || max - min < .04) {
                    return Math.max(.18, Math.min(.86, raw));
                }

                return .16 + ((raw - min) / (max - min)) * .76;
            };

            const heatColorFor = (value, alpha = .82) => {
                const score = Math.max(0, Math.min(1, value));
                const stops = score >= .82 ? [185, 28, 28]
                    : score >= .64 ? [239, 68, 68]
                    : score >= .48 ? [249, 115, 22]
                    : score >= .32 ? [250, 204, 21]
                    : [34, 197, 94];

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

            const heatSamplesFor = (feature, project, rect, score) => {
                const [lng, lat] = centroidFor(feature);
                const offsets = [
                    [0, 0, 1],
                    [.0034, .0016, .52],
                    [-.0028, .0022, .46],
                    [.0018, -.003, .42],
                    [-.0032, -.0018, .38],
                ];

                return offsets.map(([lngOffset, latOffset, weight]) => {
                    const [nx, ny] = project([lng + lngOffset, lat + latOffset]);

                    return {
                        x: nx * rect.width,
                        y: ny * rect.height,
                        score: Math.max(.08, Math.min(1, score * weight)),
                    };
                });
            };

            const scoreFor = (area, layer) => {
                if (!area) return 0;
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
                        : null;
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

            const updateLayerLabel = () => {
                if (activeLayerLabel) activeLayerLabel.textContent = layerTitle(activeLayer);
                mapEl.classList.toggle('easy-read-map', easyReadMode);
            };

            const featureArea = (feature) => feature?.properties?.heatmap || null;
            const featureName = (feature) => featureArea(feature)?.barangay || feature?.properties?.barangay || '';

            const ringsFor = (geometry) => {
                if (!geometry) return [];
                if (geometry.type === 'Polygon') return geometry.coordinates || [];
                if (geometry.type === 'MultiPolygon') return (geometry.coordinates || []).flat();
                return [];
            };

            const allPoints = (features) => features.flatMap((feature) => ringsFor(feature.geometry).flat());
            const mercatorPoint = ([lng, lat]) => {
                const clampedLat = Math.max(Math.min(Number(lat), 85.05112878), -85.05112878);
                const sin = Math.sin((clampedLat * Math.PI) / 180);

                return [
                    (Number(lng) + 180) / 360,
                    .5 - Math.log((1 + sin) / (1 - sin)) / (4 * Math.PI),
                ];
            };

            const boundsFor = (features) => {
                const points = allPoints(features);
                if (!points.length) return [120.59, 13.94, 120.73, 14.08];

                const lngs = points.map((point) => Number(point[0]));
                const lats = points.map((point) => Number(point[1]));
                const minLng = Math.min(...lngs);
                const maxLng = Math.max(...lngs);
                const minLat = Math.min(...lats);
                const maxLat = Math.max(...lats);
                const padLng = Math.max((maxLng - minLng) * .08, .004);
                const padLat = Math.max((maxLat - minLat) * .08, .004);

                return [minLng - padLng, minLat - padLat, maxLng + padLng, maxLat + padLat];
            };

            const centroidFor = (feature) => {
                const ring = ringsFor(feature.geometry)[0] || [];
                if (!ring.length) return [0, 0];
                const total = ring.reduce((carry, point) => [carry[0] + Number(point[0]), carry[1] + Number(point[1])], [0, 0]);
                return [total[0] / ring.length, total[1] / ring.length];
            };

            const rendererFor = (features) => {
                const [minLng, minLat, maxLng, maxLat] = boundsFor(features);
                const [minX, maxY] = mercatorPoint([minLng, minLat]);
                const [maxX, minY] = mercatorPoint([maxLng, maxLat]);
                const width = Math.max(maxX - minX, .000001);
                const height = Math.max(maxY - minY, .000001);

                return {
                    bounds: [minLng, minLat, maxLng, maxLat],
                    mercatorBounds: [minX, minY, maxX, maxY],
                    project: (point) => {
                        const [x, y] = mercatorPoint(point);

                        return [
                            (x - minX) / width,
                            (y - minY) / height,
                        ];
                    },
                    viewBox: `0 0 1 1`,
                };
            };

            const pathFor = (feature, project) => ringsFor(feature.geometry).map((ring) => ring.map((point, index) => {
                const [x, y] = project(point);
                return `${index === 0 ? 'M' : 'L'}${x.toFixed(6)} ${y.toFixed(6)}`;
            }).join(' ') + ' Z').join(' ');

            const drawHeat = (features, project) => {
                const rect = mapEl.getBoundingClientRect();
                const scale = window.devicePixelRatio || 1;
                canvas.width = Math.max(Math.floor(rect.width * scale), 1);
                canvas.height = Math.max(Math.floor(rect.height * scale), 1);
                canvas.style.width = `${rect.width}px`;
                canvas.style.height = `${rect.height}px`;

                const ctx = canvas.getContext('2d');
                ctx.setTransform(scale, 0, 0, scale, 0, 0);
                ctx.clearRect(0, 0, rect.width, rect.height);
                ctx.globalCompositeOperation = 'source-over';

                features
                    .slice()
                    .sort((a, b) => scoreFor(featureArea(a), activeLayer) - scoreFor(featureArea(b), activeLayer))
                    .forEach((feature) => {
                        const area = featureArea(feature);
                        const baseScore = Math.max(.08, Math.min(1, visualScoreFor(area, activeLayer, features)));
                        heatSamplesFor(feature, project, rect, baseScore).forEach((sample) => {
                            const radius = Math.max(rect.width, rect.height) * (.12 + sample.score * .12);
                            const heat = ctx.createRadialGradient(sample.x, sample.y, 0, sample.x, sample.y, radius);
                            const heatColor = (score, alpha) => activeLayer === 'farm_type'
                                ? farmTypeColorFor(area, alpha)
                                : heatColorFor(score, alpha);

                            heat.addColorStop(0, heatColor(baseScore, easyReadMode ? .38 : .28));
                            heat.addColorStop(.38, heatColor(Math.max(baseScore - .08, .12), easyReadMode ? .28 : .2));
                            heat.addColorStop(.78, heatColor(Math.max(baseScore - .18, .08), easyReadMode ? .12 : .08));
                            heat.addColorStop(1, 'rgba(255,255,255,0)');
                            ctx.fillStyle = heat;
                            ctx.fillRect(sample.x - radius, sample.y - radius, radius * 2, radius * 2);
                        });
                    });

                ctx.globalCompositeOperation = 'source-over';
            };

            const renderTiles = (renderer) => {
                if (!tileLayer) return;

                const rect = mapEl.getBoundingClientRect();
                const zoom = rect.width > 1100 ? 14 : 13;
                const tilesAtZoom = 2 ** zoom;
                const [minX, minY, maxX, maxY] = renderer.mercatorBounds;
                const tileMinX = Math.floor(minX * tilesAtZoom);
                const tileMaxX = Math.floor(maxX * tilesAtZoom);
                const tileMinY = Math.floor(minY * tilesAtZoom);
                const tileMaxY = Math.floor(maxY * tilesAtZoom);
                const width = Math.max(maxX - minX, .000001);
                const height = Math.max(maxY - minY, .000001);
                const parts = [];

                for (let x = tileMinX; x <= tileMaxX; x += 1) {
                    for (let y = tileMinY; y <= tileMaxY; y += 1) {
                        const left = (((x / tilesAtZoom) - minX) / width) * rect.width;
                        const top = (((y / tilesAtZoom) - minY) / height) * rect.height;
                        const sizeX = (1 / tilesAtZoom / width) * rect.width;
                        const sizeY = (1 / tilesAtZoom / height) * rect.height;
                        const wrappedX = ((x % tilesAtZoom) + tilesAtZoom) % tilesAtZoom;

                        const style = `left:${left.toFixed(2)}px;top:${top.toFixed(2)}px;width:${sizeX.toFixed(2)}px;height:${sizeY.toFixed(2)}px;`;
                        parts.push(`<img class="api-map-tile street" src="https://tile.openstreetmap.org/${zoom}/${wrappedX}/${y}.png" alt="" loading="lazy" style="${style}">`);
                        parts.push(`<img class="api-map-tile street" src="https://a.basemaps.cartocdn.com/light_only_labels/${zoom}/${wrappedX}/${y}.png" alt="" loading="lazy" style="${style};z-index:1;opacity:.95;">`);
                    }
                }

                tileLayer.innerHTML = parts.join('');
            };

            const showDetails = (area) => {
                if (!detailPanel || !area) return;
                selectedBarangay = area.barangay;
                if (selectedLabel) selectedLabel.textContent = area.barangay;
                if (focusSelect && focusSelect.value !== area.barangay) focusSelect.value = area.barangay;

                detailPanel.innerHTML = `
                    <button class="map-detail-close" type="button" aria-label="Close details">&times;</button>
                    <div class="map-popup" style="--popup-accent:${colorFor(scoreFor(area, activeLayer), 1)};--popup-bg:#f7fbf8;--popup-color:#0d1f18;">
                        <div class="map-popup-title">
                            <div>
                                <div class="map-popup-label">Barangay</div>
                                <div class="map-popup-name">${escapeHtml(area.barangay)}</div>
                                <div class="map-popup-note">Showing: ${escapeHtml(layerTitle(activeLayer))}</div>
                            </div>
                            <span class="map-popup-badge">${escapeHtml(area.risk_level)} Risk</span>
                        </div>
                        <div class="map-popup-takeaway">${escapeHtml(layerTakeaway(area, activeLayer))}</div>
                        <div class="map-popup-grid">${popupMetricCards(area, activeLayer)}</div>
                        <div class="map-popup-advice"><div class="map-popup-label">What to do now</div><div class="map-popup-value">${escapeHtml(area.irrigation_recommendation || area.planting_advisory || 'Visit or contact farmers in this barangay before major field work.')}</div></div>
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

            const labelTextFor = (name) => {
                return name
                    .replace('Barangay ', 'Brgy. ')
                    .replace(' (Pob.)', '')
                    .replace('Puting-Kahoy', 'Puting Kahoy');
            };

            const labelLayoutFor = (items, rect) => {
                const boxes = [];

                return items.map((item) => {
                    const text = labelTextFor(item.area.barangay);
                    const selected = selectedBarangay === item.area.barangay;
                    const width = Math.max(46, text.length * (easyReadMode || selected ? 8.2 : 6.8));
                    const height = easyReadMode || selected ? 19 : 16;
                    const box = {
                        left: (item.x * rect.width) - (width / 2),
                        right: (item.x * rect.width) + (width / 2),
                        top: (item.y * rect.height) - (height / 2),
                        bottom: (item.y * rect.height) + (height / 2),
                    };
                    const collides = boxes.some((existing) => !(
                        box.right < existing.left ||
                        box.left > existing.right ||
                        box.bottom < existing.top ||
                        box.top > existing.bottom
                    ));

                    if (!collides || selected) {
                        boxes.push(box);

                        return { ...item, text, selected, visible: true };
                    }

                    return { ...item, text, selected, visible: false };
                });
            };

            const renderMap = () => {
                const features = heatmapData?.features || [];
                if (!features.length) {
                    boundaryNotice?.classList.remove('d-none');
                    return;
                }

                boundaryNotice?.classList.add('d-none');
                updateLayerLabel();
                const renderer = rendererFor(features);
                svg.setAttribute('viewBox', renderer.viewBox);
                renderTiles(renderer);
                drawHeat(features, renderer.project);

                const rect = mapEl.getBoundingClientRect();
                const items = features.map((feature) => {
                    const area = featureArea(feature);
                    const score = visualScoreFor(area, activeLayer, features);
                    const [lng, lat] = centroidFor(feature);
                    const [x, y] = renderer.project([lng, lat]);

                    return { feature, area, score, x, y };
                });
                const labels = labelLayoutFor(items, rect);

                svg.innerHTML = labels.map((item) => {
                    const selected = item.selected;
                    const fill = activeLayer === 'farm_type'
                        ? farmTypeColorFor(item.area, easyReadMode ? .28 : .08)
                        : colorFor(item.score, easyReadMode ? .06 : .004);
                    return `
                        <path class="api-heatmap-region ${selected ? 'is-selected' : ''}" data-barangay="${escapeHtml(item.area.barangay)}" d="${pathFor(item.feature, renderer.project)}" fill="${fill}"></path>
                        ${item.visible ? `<text class="api-heatmap-label ${selected ? 'is-selected' : ''}" x="${item.x.toFixed(6)}" y="${item.y.toFixed(6)}">${escapeHtml(item.text)}</text>` : ''}
                    `;
                }).join('');

                svg.querySelectorAll('.api-heatmap-region').forEach((path) => {
                    path.addEventListener('click', () => {
                        const feature = features.find((item) => featureName(item) === path.dataset.barangay);
                        showDetails(featureArea(feature));
                    });
                });
            };

            fetch(apiUrl, { headers: { Accept: 'application/json' } })
                .then((response) => response.ok ? response.json() : Promise.reject(response))
                .then((data) => {
                    heatmapData = data;
                    renderMap();
                })
                .catch(() => {
                    boundaryNotice?.classList.remove('d-none');
                    boundaryNotice.textContent = 'Unable to load the Lian barangay heatmap API right now.';
                });

            document.querySelectorAll('[data-layer]').forEach((button) => {
                button.addEventListener('click', () => {
                    document.querySelectorAll('[data-layer]').forEach((item) => {
                        item.classList.remove('active');
                        item.setAttribute('aria-pressed', 'false');
                    });
                    button.classList.add('active');
                    button.setAttribute('aria-pressed', 'true');
                    activeLayer = button.dataset.layer;
                    renderMap();
                    if (selectedBarangay) {
                        const feature = (heatmapData?.features || []).find((item) => featureName(item) === selectedBarangay);
                        if (feature) showDetails(featureArea(feature));
                    }
                });
            });

            focusSelect?.addEventListener('change', () => {
                if (!focusSelect.value) return;
                const feature = (heatmapData?.features || []).find((item) => featureName(item) === focusSelect.value);
                showDetails(featureArea(feature));
            });

            document.querySelectorAll('[data-focus-barangay]').forEach((button) => {
                button.addEventListener('click', () => {
                    const feature = (heatmapData?.features || []).find((item) => featureName(item) === button.dataset.focusBarangay);
                    showDetails(featureArea(feature));
                });
            });

            window.addEventListener('resize', () => renderMap(), { passive: true });
        });
    </script>
</x-app-layout>
