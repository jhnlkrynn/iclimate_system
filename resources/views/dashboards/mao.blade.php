<x-app-layout>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        .mao-console {
            --mao-ink: #0d1f18;
            --mao-muted: #5f7569;
            --mao-green: #2d6a4f;
            --mao-green-bright: #52b788;
            --mao-blue: #1677b8;
            --mao-gold: #f4b63f;
            --mao-red: #d85b45;
            --mao-line: rgba(153, 185, 160, .72);
        }
        .mao-hero {
            position: relative;
            overflow: hidden;
            border-radius: 32px;
            padding: 1.35rem;
            margin-bottom: 1.25rem;
            color: #fff;
            background:
                radial-gradient(circle at 84% 12%, rgba(82,183,136,.26), transparent 30%),
                linear-gradient(145deg, #0d1f18 0%, #1a3a2a 62%, #163324 100%);
            box-shadow: 0 1rem 2.3rem rgba(13,31,24,.18);
        }
        .mao-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, rgba(255,255,255,.08) 1px, transparent 1px),
                linear-gradient(0deg, rgba(255,255,255,.06) 1px, transparent 1px);
            background-size: 38px 38px;
            mask-image: linear-gradient(90deg, rgba(0,0,0,.78), transparent 88%);
        }
        .mao-hero > * { position: relative; z-index: 1; }
        .mao-eyebrow {
            font-size: .72rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: rgba(255,255,255,.68);
        }
        .mao-hero h1 { color: #fff; }
        .mao-hero em { color: #9be5ba; font-style: italic; }
        .mao-hero p { color: rgba(255,255,255,.66); max-width: 760px; }
        .mao-chip {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 999px;
            padding: .45rem .75rem;
            background: rgba(255,255,255,.08);
            color: rgba(255,255,255,.88);
            font-size: .82rem;
            font-weight: 800;
        }
        .mao-pulse { width: 10px; height: 10px; border-radius: 999px; background: var(--mao-green-bright); box-shadow: 0 0 0 6px rgba(82,183,136,.16); }
        .mao-stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
        .mao-card {
            position: relative;
            overflow: hidden;
            border: 1px solid var(--mao-line);
            border-radius: 18px;
            background: linear-gradient(145deg, rgba(244,250,239,.97), rgba(229,242,226,.97));
            box-shadow: 0 .9rem 2rem rgba(20,32,51,.07);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
            width: 100%;
            text-align: left;
            font: inherit;
            cursor: pointer;
        }
        .mao-card:hover { transform: translateY(-2px); box-shadow: 0 1.1rem 2.2rem rgba(20,32,51,.11); border-color: rgba(31,143,85,.42); }
        .mao-card:focus-visible { outline: 2px solid var(--mao-green-bright); outline-offset: 2px; }
        .mao-tap-hint { color: var(--mao-green-bright); font-size: .74rem; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; margin-top: .5rem; opacity: 0; transform: translateY(2px); transition: opacity .18s ease, transform .18s ease; }
        .mao-card:hover .mao-tap-hint, .mao-card:focus-visible .mao-tap-hint { opacity: 1; transform: translateY(0); }
        .mao-modal-content { border-radius: 18px; background: var(--ic-green-950, #0D1F18); color: rgba(255,255,255,.85); }
        .mao-modal-content .modal-footer { border-top-color: rgba(255,255,255,.1); }
        .mao-modal-header { background: linear-gradient(90deg, #0d1f18, #1a3a2a); color: #fff; }
        .mao-modal-header .modal-title { color: #fff; }
        .mao-modal-headline { font-size: 1.6rem; font-weight: 900; color: #fff; }
        .mao-modal-sub { color: rgba(255,255,255,.55); font-size: .85rem; margin: .3rem 0 .9rem; }
        .mao-card-body { padding: 1rem; position: relative; z-index: 1; }
        .mao-label { color: var(--mao-muted); font-size: .72rem; font-weight: 900; text-transform: uppercase; letter-spacing: .06em; }
        .mao-value { margin-top: .35rem; font-size: clamp(1.55rem, 3vw, 2.2rem); line-height: 1; font-weight: 900; color: var(--mao-ink); }
        .mao-note { color: var(--mao-muted); font-size: .84rem; margin-top: .65rem; }
        .mao-panel {
            border: 1px solid var(--mao-line);
            border-radius: 18px;
            background: linear-gradient(145deg, rgba(244,250,239,.97), rgba(232,244,230,.97));
            box-shadow: 0 .9rem 2rem rgba(20,32,51,.07);
            overflow: hidden;
        }
        .mao-panel-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 1rem .75rem; border-bottom: 1px solid rgba(153,185,160,.58); background: rgba(226,241,219,.58); }
        .mao-panel-title { font-size: 1rem; font-weight: 900; margin: 0; color: var(--mao-ink); }
        .mao-panel-sub { margin: .15rem 0 0; color: var(--mao-muted); font-size: .82rem; }
        .mao-panel-body { padding: 1rem; }
        .mao-list-item { display: flex; gap: .85rem; align-items: flex-start; padding: .85rem 0; }
        .mao-list-item + .mao-list-item { border-top: 1px solid rgba(153,185,160,.55); }
        .list-mark { width: 38px; height: 38px; border-radius: 10px; display: grid; place-items: center; background: #e2f1df; color: var(--mao-green); font-weight: 900; font-size: .76rem; flex: 0 0 auto; }
        .row-title { font-weight: 900; color: var(--mao-ink); line-height: 1.25; }
        .row-text { color: var(--mao-muted); font-size: .84rem; margin-top: .15rem; line-height: 1.45; }
        .row-meta { color: #7a8f82; font-size: .76rem; margin-top: .35rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; }
        .status-pill { display: inline-flex; align-items: center; border-radius: 999px; padding: .32rem .58rem; font-size: .72rem; font-weight: 900; background: #d8f3dc; color: #1f6f4a; }
        .status-pill.muted { background: #e8eef6; color: #516579; }
        .quick-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .85rem; }
        .quick-action { min-height: 108px; padding: 1rem; border-radius: 10px; border: 1px solid rgba(153,185,160,.72); background: linear-gradient(135deg, #edf7e7, #dfeee8); color: inherit; text-decoration: none; }
        .quick-action:hover { transform: translateY(-2px); border-color: rgba(31,143,85,.55); }
        .quick-action strong { display: block; color: var(--mao-ink); }
        .quick-action span { display: block; color: var(--mao-muted); font-size: .82rem; margin-top: .25rem; }
        .heat-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .85rem; }
        .risk-card { padding: 1rem; border-radius: 8px; border: 1px solid rgba(153,185,160,.72); background: linear-gradient(135deg, #edf7e7, #e3f1e7); }
        .risk-card.severe, .risk-card.high { border-color: rgba(216,91,69,.45); background: linear-gradient(135deg, #fff3ee, #f5e7db); }
        .visual-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
        .live-weather-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; margin-bottom: 1rem; }
        .live-weather-card { border: 1px solid rgba(153,185,160,.72); border-radius: 8px; background: #fff; padding: .9rem; }
        .source-badge { display: inline-flex; align-items: center; gap: .4rem; border-radius: 999px; border: 1px solid rgba(22,119,184,.24); background: #eef7ff; color: #155a84; padding: .35rem .65rem; font-size: .76rem; font-weight: 900; }
        .chart-box { position: relative; min-height: 280px; padding: 1rem; border: 1px solid rgba(153,185,160,.72); border-radius: 8px; background: #fff; }
        .chart-box canvas { width: 100% !important; height: 220px !important; }
        .chart-title { color: var(--mao-ink); font-weight: 900; margin-bottom: .25rem; }
        .chart-note { color: var(--mao-muted); font-size: .82rem; margin-bottom: .85rem; }
        .dashboard-map-shell { position: relative; overflow: hidden; border-radius: 8px; }
        .map-toolbar { position: absolute; z-index: 500; left: 1rem; right: 1rem; top: 1rem; display: flex; gap: .5rem; flex-wrap: wrap; pointer-events: none; }
        .map-toolbar > * { pointer-events: auto; }
        .layer-btn { min-height: 38px; border: 1px solid #d4edda; border-radius: 8px; background: rgba(255,255,255,.94); color: #1b2b23; padding: .48rem .64rem; font-size: .78rem; font-weight: 900; box-shadow: 0 .5rem 1.2rem rgba(13,31,24,.08); white-space: nowrap; }
        .layer-btn.active { background: #1a3a2a; border-color: #1a3a2a; color: #fff; }
        .dashboard-map {
            height: 540px;
            min-height: 380px;
            border-radius: 8px;
            overflow: hidden;
            background:
                radial-gradient(circle at 20% 30%, rgba(16, 77, 196, .45), transparent 20rem),
                radial-gradient(circle at 70% 38%, rgba(255, 24, 18, .35), transparent 18rem),
                linear-gradient(135deg, #0736a8, #15cfe0 28%, #59f03d 48%, #fff118 62%, #ff850f 78%, #f71912);
        }
        .thermo-map .leaflet-tile-pane { filter: saturate(1.65) contrast(1.05); opacity: .22; }
        .thermo-map .leaflet-overlay-pane canvas { mix-blend-mode: multiply; filter: saturate(1.55) contrast(1.08); }
        .thermo-point { width: 12px; height: 12px; border-radius: 999px; border: 2px solid rgba(13,31,24,.78); background: rgba(255,255,255,.86); box-shadow: 0 0 0 5px rgba(255,255,255,.22), 0 .35rem .8rem rgba(13,31,24,.24); }
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
            top: 1rem;
            right: 1rem;
            z-index: 520;
            width: min(292px, calc(100% - 2rem));
            max-height: calc(100% - 2rem);
            overflow: auto;
            border: 1px solid #d4edda;
            border-radius: 8px;
            background: rgba(255,255,255,.96);
            box-shadow: 0 .9rem 2rem rgba(13,31,24,.18);
            padding: .85rem;
            display: none;
        }
        .map-detail-panel.show { display: block; }
        .map-detail-empty { color: #5a7a64; font-size: .86rem; line-height: 1.45; }
        .map-detail-close { position: absolute; top: .5rem; right: .5rem; width: 30px; height: 30px; border: 1px solid #d4edda; border-radius: 8px; background: #fff; color: #0d1f18; font-weight: 900; line-height: 1; }
        .map-detail-panel .map-popup-title { padding-right: 2rem; }
        .risk-level-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; }
        .risk-level-card { border: 1px solid rgba(153,185,160,.72); border-radius: 8px; background: #fff; padding: .9rem; }
        .risk-level-card.low { border-left: 5px solid #52b788; }
        .risk-level-card.moderate { border-left: 5px solid #ffd166; }
        .risk-level-card.high, .risk-level-card.severe { border-left: 5px solid #d85b45; }
        .risk-level-value { color: var(--mao-ink); font-size: 1.85rem; font-weight: 900; line-height: 1; margin-top: .35rem; }
        .barangay-tooltip { border: 0; border-radius: 999px; background: rgba(13,31,24,.88); color: #fff; font-size: .68rem; font-weight: 900; padding: .22rem .45rem; box-shadow: 0 .45rem 1rem rgba(13,31,24,.16); }
        .barangay-tooltip::before { display: none; }
        .empty-soft { border: 1px dashed #aac7b0; background: linear-gradient(135deg, #edf7e7, #e2f1df); border-radius: 8px; padding: 1.5rem; text-align: center; }
        .dashboard-section-label { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin: 1.35rem 0 .75rem; }
        .dashboard-section-label:first-of-type { margin-top: 0; }
        .section-title { color: var(--mao-ink); font-size: .92rem; font-weight: 900; text-transform: uppercase; letter-spacing: .08em; margin: 0; }
        .section-note { color: var(--mao-muted); font-size: .84rem; margin: 0; }
        .dashboard-focus-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        .dashboard-group {
            border: 1px solid var(--mao-line);
            border-radius: 18px;
            background: rgba(255,255,255,.58);
            box-shadow: 0 .55rem 1.35rem rgba(20,32,51,.045);
            overflow: hidden;
        }
        .dashboard-group > summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .95rem 1rem;
            cursor: pointer;
            list-style: none;
            background: linear-gradient(135deg, rgba(244,250,239,.95), rgba(232,244,230,.82));
        }
        .dashboard-group > summary::-webkit-details-marker { display: none; }
        .dashboard-group-title { color: var(--mao-ink); font-size: .9rem; font-weight: 900; text-transform: uppercase; letter-spacing: .06em; margin: 0; }
        .dashboard-group-note { color: var(--mao-muted); font-size: .82rem; margin: .1rem 0 0; }
        .dashboard-group-toggle { color: var(--mao-green); font-size: .78rem; font-weight: 900; white-space: nowrap; }
        .dashboard-group-toggle::after { content: "Collapse"; }
        .dashboard-group:not([open]) .dashboard-group-toggle::after { content: "Expand"; }
        .dashboard-group-body { padding: 1rem; }
        .dashboard-group-body > .row:last-child, .dashboard-group-body > .mao-panel:last-child { margin-bottom: 0 !important; }
        .list-compact .mao-list-item:nth-of-type(n+5), .list-compact .risk-card:nth-of-type(n+5) { display: none; }
        @media (max-width: 1199.98px) { .mao-stat-grid, .visual-grid, .live-weather-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .quick-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .risk-level-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 767.98px) { .mao-stat-grid, .visual-grid, .live-weather-grid, .quick-grid, .heat-grid, .risk-level-grid { grid-template-columns: 1fr; } .mao-panel-header, .dashboard-section-label, .dashboard-group > summary { align-items: flex-start; flex-direction: column; } .chart-box canvas { height: 240px !important; } .dashboard-map { height: 360px; } .dashboard-map-shell { overflow: visible; } .map-toolbar { position: relative; inset: auto; margin-bottom: .75rem; } .layer-btn { flex: 0 0 auto; min-width: max-content; font-size: .76rem; } .map-detail-panel { position: relative; inset: auto; width: auto; max-height: 300px; margin-top: .75rem; } }

        /* -- dark theme overrides (matches farmer dashboard palette) -- */
        .mao-card { background: var(--ic-green-950, #0D1F18); border-color: rgba(255,255,255,.14); }
        .mao-card:hover { border-color: rgba(149,213,178,.4); }
        .mao-label { color: rgba(255,255,255,.5); }
        .mao-value { color: #fff; }
        .mao-note { color: rgba(255,255,255,.55); }
        .mao-panel { background: var(--ic-green-950, #0D1F18); border-color: rgba(255,255,255,.12); }
        .mao-panel-header { background: rgba(255,255,255,.03); border-bottom-color: rgba(255,255,255,.08); }
        .mao-panel-title { color: #fff; }
        .mao-panel-sub { color: rgba(255,255,255,.55); }
        .mao-list-item + .mao-list-item { border-top-color: rgba(255,255,255,.08); }
        .list-mark { background: rgba(82,183,136,.16); color: var(--mao-green-bright); }
        .row-title { color: #fff; }
        .row-text { color: rgba(255,255,255,.55); }
        .row-meta { color: rgba(255,255,255,.4); }
        .status-pill { background: rgba(82,183,136,.16); color: var(--mao-green-bright); }
        .status-pill.muted { background: rgba(255,255,255,.06); color: rgba(255,255,255,.5); }
        .quick-action { background: rgba(255,255,255,.04); border-color: rgba(255,255,255,.1); }
        .quick-action:hover { border-color: rgba(116,198,157,.4); background: rgba(255,255,255,.07); }
        .quick-action strong { color: #fff; }
        .quick-action span { color: rgba(255,255,255,.55); }
        .risk-card { background: rgba(255,255,255,.04); border-color: rgba(255,255,255,.1); }
        .risk-card.severe, .risk-card.high { background: rgba(216,91,69,.14); border-color: rgba(216,91,69,.45); }
        .live-weather-card { background: var(--ic-green-950, #0D1F18); border-color: rgba(255,255,255,.12); }
        .source-badge { background: rgba(47,111,143,.22); border-color: rgba(126,200,227,.35); color: #7ec8e3; }
        .chart-box { background: var(--ic-green-950, #0D1F18); border-color: rgba(255,255,255,.12); }
        .chart-title { color: #fff; }
        .chart-note { color: rgba(255,255,255,.55); }
        .risk-level-card { background: var(--ic-green-950, #0D1F18); border-color: rgba(255,255,255,.12); }
        .risk-level-value { color: #fff; }
        .empty-soft { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.18); color: rgba(255,255,255,.7); }
        .empty-soft strong { color: #fff; }
        .section-title { color: #fff; }
        .section-note { color: rgba(255,255,255,.55); }
        .dashboard-group { background: var(--ic-green-950, #0D1F18); border-color: rgba(255,255,255,.1); }
        .dashboard-group > summary { background: rgba(255,255,255,.03); }
        .dashboard-group-title { color: #fff; }
        .dashboard-group-note { color: rgba(255,255,255,.55); }
        .dashboard-group-toggle { color: var(--mao-green-bright); }
        .mao-console .text-muted { color: rgba(255,255,255,.5) !important; }
    </style>

    @php
        $latestSeason = $latestClimate?->season ?? 'N/A';
    @endphp

    <div class="mao-console">
        <section class="mao-hero">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-4 align-items-xl-end">
                <div>
                    <div class="mao-eyebrow mb-2">Municipal Agriculture Office</div>
                    <h1 class="h2 fw-bold mb-2">Climate-informed <em>agricultural monitoring</em></h1>
                    <p class="mb-0">Monitor farmer records, climate observations, rice production performance, barangay risk records, advisories, community posts, and reports for Lian, Batangas.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="mao-chip"><span class="mao-pulse"></span> Season: {{ $latestSeason }}</span>
                    <a class="btn btn-light fw-bold" href="{{ route('heatmap-areas.index') }}">Open Heat Map</a>
                    <a class="btn btn-outline-light fw-bold" href="{{ route('planting-advisories.create') }}">Create Advisory</a>
                    <a class="btn btn-outline-light fw-bold" href="{{ route('reports.index') }}">Open Reports</a>
                </div>
            </div>
        </section>

        <div class="dashboard-section-label">
            <div>
                <h2 class="section-title">Office Overview</h2>
                <p class="section-note">Core records and risk modules for the municipality.</p>
            </div>
        </div>

        <section class="mao-stat-grid">
            <button type="button" class="mao-card text-decoration-none" data-bs-toggle="modal" data-bs-target="#maoStatFarmers"><div class="mao-card-body"><div class="mao-label">Farmer Profiles</div><div class="mao-value">{{ number_format($profileCount) }}</div><div class="mao-note">Registered farm records</div><div class="mao-tap-hint">View details &rarr;</div></div></button>
            <button type="button" class="mao-card text-decoration-none" data-bs-toggle="modal" data-bs-target="#maoStatClimate"><div class="mao-card-body"><div class="mao-label">Climate Records</div><div class="mao-value">{{ number_format($totalClimateRecords) }}</div><div class="mao-note">Latest: {{ $latestClimate?->record_date?->format('M d, Y') ?? 'N/A' }}</div><div class="mao-tap-hint">View details &rarr;</div></div></button>
            <button type="button" class="mao-card text-decoration-none" data-bs-toggle="modal" data-bs-target="#maoStatRice"><div class="mao-card-body"><div class="mao-label">Rice Production</div><div class="mao-value">{{ number_format($riceProductionTotal, 1) }}</div><div class="mao-note">{{ number_format($riceAreaTotal, 1) }} hectares recorded</div><div class="mao-tap-hint">View details &rarr;</div></div></button>
            <button type="button" class="mao-card text-decoration-none" data-bs-toggle="modal" data-bs-target="#maoStatRisk"><div class="mao-card-body"><div class="mao-label">Heat Map Risks</div><div class="mao-value">{{ number_format($heatMapCount) }}</div><div class="mao-note">{{ number_format($highRiskHeatMapAreas) }} high or severe areas</div><div class="mao-tap-hint">View details &rarr;</div></div></button>
        </section>

        <div class="modal fade" id="maoStatFarmers" tabindex="-1" aria-labelledby="maoStatFarmersLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 mao-modal-content">
                    <div class="modal-header mao-modal-header">
                        <h2 class="modal-title h5 fw-bold" id="maoStatFarmersLabel">Farmer Profiles</h2>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mao-modal-headline">{{ number_format($profileCount) }} registered</div>
                        <p class="mao-modal-sub">Most recently registered farmer profiles.</p>
                        @forelse($recentFarmerProfiles as $profile)
                            <div class="mao-list-item">
                                <div class="list-mark">FAR</div>
                                <div class="flex-grow-1">
                                    <div class="row-title">{{ $profile->full_name }}</div>
                                    <div class="row-text">{{ $profile->barangay }} &middot; {{ number_format($profile->farm_area, 2) }} ha &middot; {{ $profile->farm_type }}</div>
                                    <div class="row-meta">{{ $profile->contact_number }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="empty-soft"><strong>No farmer profiles yet</strong></div>
                        @endforelse
                    </div>
                    <div class="modal-footer">
                        <a class="btn btn-primary fw-bold" href="{{ route('farmer-profiles.index') }}">Open Farmer Profiles</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="maoStatClimate" tabindex="-1" aria-labelledby="maoStatClimateLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 mao-modal-content">
                    <div class="modal-header mao-modal-header">
                        <h2 class="modal-title h5 fw-bold" id="maoStatClimateLabel">Climate Records</h2>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mao-modal-headline">{{ number_format($totalClimateRecords) }} recorded</div>
                        <p class="mao-modal-sub">Latest entry: {{ $latestClimate?->record_date?->format('F d, Y') ?? 'N/A' }} &middot; Source: {{ $latestClimate?->source ?? 'N/A' }}</p>
                        @forelse($recentClimateRecords as $record)
                            <div class="mao-list-item">
                                <div class="list-mark">CLI</div>
                                <div class="flex-grow-1">
                                    <div class="row-title">{{ $record->record_date?->format('M d, Y') }} &middot; {{ $record->season }} Season</div>
                                    <div class="row-text">Rainfall {{ number_format($record->rainfall, 1) }} mm, temperature {{ number_format($record->temperature, 1) }}&deg;C, humidity {{ number_format($record->humidity, 1) }}%.</div>
                                    <div class="row-meta">Source: {{ $record->source }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="empty-soft"><strong>No climate records yet</strong></div>
                        @endforelse
                    </div>
                    <div class="modal-footer">
                        <a class="btn btn-primary fw-bold" href="{{ route('climate-records.index') }}">Open Climate Records</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="maoStatRice" tabindex="-1" aria-labelledby="maoStatRiceLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 mao-modal-content">
                    <div class="modal-header mao-modal-header">
                        <h2 class="modal-title h5 fw-bold" id="maoStatRiceLabel">Rice Production</h2>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mao-modal-headline">{{ number_format($riceProductionTotal, 1) }} total production</div>
                        <p class="mao-modal-sub">{{ number_format($riceAreaTotal, 1) }} hectares recorded &middot; Average yield {{ number_format($avgYield, 2) }} per hectare.</p>
                        @forelse($recentRiceProductions as $production)
                            <div class="mao-list-item">
                                <div class="list-mark">RIC</div>
                                <div class="flex-grow-1">
                                    <div class="row-title">{{ $production->barangay }} &middot; {{ $production->season }} {{ $production->year }}</div>
                                    <div class="row-text">{{ number_format($production->area_hectares, 2) }} ha, {{ number_format($production->yield_per_hectare, 2) }} yield/ha, total {{ number_format($production->total_production, 2) }}.</div>
                                    <div class="row-meta">{{ $production->irrigation_type }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="empty-soft"><strong>No production records yet</strong></div>
                        @endforelse
                    </div>
                    <div class="modal-footer">
                        <a class="btn btn-primary fw-bold" href="{{ route('rice-productions.index') }}">Open Rice Production</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="maoStatRisk" tabindex="-1" aria-labelledby="maoStatRiskLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 mao-modal-content">
                    <div class="modal-header mao-modal-header">
                        <h2 class="modal-title h5 fw-bold" id="maoStatRiskLabel">Heat Map Risks</h2>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mao-modal-headline">{{ number_format($highRiskHeatMapAreas) }} high or severe</div>
                        <p class="mao-modal-sub">Out of {{ number_format($heatMapCount) }} mapped barangay records.</p>
                        @forelse($highRiskAreasList as $area)
                            <div class="mao-list-item">
                                <div class="list-mark">{{ $area->risk_level === 'Severe' ? 'SEV' : 'HI' }}</div>
                                <div class="flex-grow-1">
                                    <div class="row-title">{{ $area->barangay }} &middot; {{ $area->risk_type }}</div>
                                    <div class="row-text">{{ $area->planting_advisory ?: 'Review latest climate and rice production data before planting.' }}</div>
                                    <div class="row-meta">Risk score {{ number_format($area->risk_score, 2) }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="empty-soft"><strong>No high risk barangays</strong></div>
                        @endforelse
                    </div>
                    <div class="modal-footer">
                        <a class="btn btn-primary fw-bold" href="{{ route('heatmap-areas.index') }}">Open Heat Map</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-focus-grid">
        <details class="dashboard-group" open>
            <summary>
                <div>
                    <h2 class="dashboard-group-title">Heat Map Intelligence</h2>
                    <p class="dashboard-group-note">Barangay risk map first, with weather readings and charts below.</p>
                </div>
                <span class="dashboard-group-toggle"></span>
            </summary>
            <div class="dashboard-group-body">
        <section class="mao-panel">
            <div class="mao-panel-header">
                <div>
                    <h2 class="mao-panel-title">Barangay Heat Map Dashboard</h2>
                    <p class="mao-panel-sub">Primary view for rainfall, yield, irrigation, and climate-impact risk by barangay.</p>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="source-badge">{{ $weatherDataSource }}</span>
                    <a class="btn btn-sm btn-primary" href="{{ route('heatmap-areas.index') }}">Open Heat Map</a>
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('weather-predictions.index') }}">Open Prediction</a>
                </div>
            </div>
            <div class="mao-panel-body">
                @if($liveWeather)
                    <div class="live-weather-grid">
                        <div class="live-weather-card">
                            <div class="mao-label">Current Rain</div>
                            <div class="risk-level-value">{{ number_format($liveWeather['current_rainfall_mm'], 1) }}</div>
                            <div class="mao-note">mm right now</div>
                        </div>
                        <div class="live-weather-card">
                            <div class="mao-label">Temperature</div>
                            <div class="risk-level-value">{{ number_format($liveWeather['current_temperature_c'], 1) }}</div>
                            <div class="mao-note">C current reading</div>
                        </div>
                        <div class="live-weather-card">
                            <div class="mao-label">Humidity</div>
                            <div class="risk-level-value">{{ number_format($liveWeather['humidity_percent'], 1) }}</div>
                            <div class="mao-note">percent current reading</div>
                        </div>
                        <div class="live-weather-card">
                            <div class="mao-label">Wind Speed</div>
                            <div class="risk-level-value">{{ number_format($liveWeather['wind_speed_kmh'], 1) }}</div>
                            <div class="mao-note">km/h current reading</div>
                        </div>
                    </div>
                @endif

                <div class="row g-4 mb-4">
                    <div class="col-xl-8">
                        <div class="chart-title">Municipal Heat Map</div>
                        <div class="chart-note">Barangay markers colored by climate and production risk. Start here before reviewing weather charts.</div>
                        <div class="dashboard-map-shell">
                            <div class="map-toolbar" aria-label="Dashboard heat map layer controls">
                                <button class="layer-btn active" type="button" data-dashboard-layer="impact" aria-pressed="true">Climate Impact</button>
                                <button class="layer-btn" type="button" data-dashboard-layer="rainfall" aria-pressed="false">Rainfall Risk</button>
                                <button class="layer-btn" type="button" data-dashboard-layer="yield" aria-pressed="false">Rice Yield</button>
                                <button class="layer-btn" type="button" data-dashboard-layer="irrigation" aria-pressed="false">Irrigation Priority</button>
                            </div>
                            <div id="dashboardRiskMap" class="dashboard-map"></div>
                            <aside id="dashboardMapDetailPanel" class="map-detail-panel" aria-live="polite">
                                <button class="map-detail-close" type="button" aria-label="Close details">&times;</button>
                                <div class="map-detail-empty">Click a barangay on the heat map to view risk details here.</div>
                            </aside>
                        </div>
                    </div>
                    <div class="col-xl-4">
                        <div class="chart-title">Barangay Risk Levels</div>
                        <div class="chart-note">Quick count of mapped barangays per risk level.</div>
                        <div class="risk-level-grid mb-3">
                            @foreach(['Low', 'Moderate', 'High', 'Severe'] as $level)
                                <div class="risk-level-card {{ strtolower($level) }}">
                                    <div class="mao-label">{{ $level }}</div>
                                    <div class="risk-level-value">{{ number_format($riskLevelCounts[$level] ?? 0) }}</div>
                                    <div class="mao-note">barangay(s)</div>
                                </div>
                            @endforeach
                        </div>
                        <div class="heat-grid">
                            @forelse($dashboardMapAreas->take(6) as $area)
                                <div class="risk-card {{ strtolower($area['risk_level']) }}">
                                    <div class="d-flex justify-content-between gap-2 mb-2">
                                        <strong>{{ $area['barangay'] }}</strong>
                                        <span class="status-pill">{{ $area['risk_level'] }}</span>
                                    </div>
                                    <div class="row-text">{{ $area['risk_type'] }} | Score {{ number_format($area['risk_score'], 2) }}</div>
                                    <div class="row-meta">{{ $area['rainfall_status'] ?: 'Rainfall not recorded' }}</div>
                                </div>
                            @empty
                                <div class="empty-soft"><strong>No mapped barangays yet</strong><div class="small text-muted mt-1">Heat map risk records will appear here.</div></div>
                            @endforelse
                        </div>
                    </div>
                </div>

                @if(! empty($weatherChartData['labels']))
                    <div class="visual-grid mb-4">
                        <div class="chart-box">
                            <div class="chart-title">Rainfall Chart</div>
                            <div class="chart-note">{{ $liveWeather ? 'Live forecast rainfall in millimeters.' : 'Recent recorded rainfall in millimeters.' }}</div>
                            <canvas id="rainfallChart"></canvas>
                        </div>
                        <div class="chart-box">
                            <div class="chart-title">Temperature Chart</div>
                            <div class="chart-note">{{ $liveWeather ? 'Live forecast average temperature.' : 'Recent recorded average temperature.' }}</div>
                            <canvas id="temperatureChart"></canvas>
                        </div>
                        <div class="chart-box">
                            <div class="chart-title">Humidity Chart</div>
                            <div class="chart-note">{{ $liveWeather ? 'Live forecast humidity percentage.' : 'Recent recorded humidity percentage.' }}</div>
                            <canvas id="humidityChart"></canvas>
                        </div>
                        <div class="chart-box">
                            <div class="chart-title">Wind Speed Chart</div>
                            <div class="chart-note">{{ $liveWeather ? 'Live forecast wind speed.' : 'Recent recorded wind speed.' }}</div>
                            <canvas id="windSpeedChart"></canvas>
                        </div>
                    </div>
                @else
                    <div class="empty-soft mb-4"><strong>No weather chart data yet</strong><div class="small text-muted mt-1">The live weather API is unavailable and there are no saved climate records to show.</div></div>
                @endif

            </div>
        </section>
            </div>
        </details>

        <details class="dashboard-group">
            <summary>
                <div>
                    <h2 class="dashboard-group-title">Daily Operations</h2>
                    <p class="dashboard-group-note">Climate records and common MAO actions.</p>
                </div>
                <span class="dashboard-group-toggle"></span>
            </summary>
            <div class="dashboard-group-body">
        <div class="row g-4 mb-0">
            <div class="col-xl-8">
                <div class="mao-panel h-100">
                    <div class="mao-panel-header"><div><h2 class="mao-panel-title">Climate Monitoring Summary</h2><p class="mao-panel-sub">Recent rainfall, temperature, humidity, and wind entries.</p></div><a class="btn btn-sm btn-outline-primary" href="{{ route('climate-records.index') }}">Manage Climate</a></div>
                    <div class="mao-panel-body list-compact">
                        @forelse($recentClimateRecords as $record)
                            <div class="mao-list-item">
                                <div class="list-mark">CLI</div>
                                <div class="flex-grow-1"><div class="row-title">{{ $record->record_date?->format('M d, Y') }} | {{ $record->season }} Season</div><div class="row-text">Rainfall {{ number_format($record->rainfall, 1) }} mm, temperature {{ number_format($record->temperature, 1) }} C, humidity {{ number_format($record->humidity, 1) }}%, wind {{ number_format($record->wind_speed, 1) }} km/h.</div><div class="row-meta">Source: {{ $record->source }}</div></div>
                            </div>
                        @empty
                            <div class="empty-soft"><strong>No climate records yet</strong><div class="small text-muted mt-1">Add climate records to populate this monitoring list.</div></div>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="mao-panel h-100">
                    <div class="mao-panel-header"><div><h2 class="mao-panel-title">Operational Tasks</h2><p class="mao-panel-sub">Common MAO actions for daily work.</p></div></div>
                    <div class="mao-panel-body">
                        <div class="quick-grid" style="grid-template-columns:1fr;">
                            <a class="quick-action" href="{{ route('rice-productions.create') }}"><strong>Validate Production Records</strong><span>Add or update barangay rice production data.</span></a>
                            <a class="quick-action" href="{{ route('community-feed.index') }}"><strong>Post Community Update</strong><span>Share activities, programs, images, videos, and files with farmers.</span></a>
                            <a class="quick-action" href="{{ route('messages.index') }}"><strong>Farmer Messages</strong><span>Read and respond to private farmer conversations.</span></a>
                            <a class="quick-action" href="{{ route('reports.index') }}"><strong>Generate Weekly Report</strong><span>Prepare climate and production summaries.</span></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
            </div>
        </details>

        <details class="dashboard-group">
            <summary>
                <div>
                    <h2 class="dashboard-group-title">Production And Risk Records</h2>
                    <p class="dashboard-group-note">Recent rice production entries and barangay risk status.</p>
                </div>
                <span class="dashboard-group-toggle"></span>
            </summary>
            <div class="dashboard-group-body">
        <div class="row g-4 mb-0">
            <div class="col-xl-7">
                <div class="mao-panel h-100">
                    <div class="mao-panel-header"><div><h2 class="mao-panel-title">Rice Production Monitoring</h2><p class="mao-panel-sub">Recent barangay production records and yield per hectare.</p></div><a class="btn btn-sm btn-outline-primary" href="{{ route('rice-productions.index') }}">Open Records</a></div>
                    <div class="mao-panel-body list-compact">
                        @forelse($recentRiceProductions as $production)
                            <div class="mao-list-item">
                                <div class="list-mark">RIC</div>
                                <div class="flex-grow-1"><div class="row-title">{{ $production->barangay }} | {{ $production->season }} {{ $production->year }}</div><div class="row-text">{{ number_format($production->area_hectares, 2) }} ha, {{ number_format($production->yield_per_hectare, 2) }} yield/ha, total {{ number_format($production->total_production, 2) }}.</div><div class="row-meta">{{ $production->irrigation_type }}</div></div>
                            </div>
                        @empty
                            <div class="empty-soft"><strong>No production records yet</strong><div class="small text-muted mt-1">Rice production entries will appear here.</div></div>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="mao-panel h-100">
                    <div class="mao-panel-header"><div><h2 class="mao-panel-title">Barangay Heat Map Overview</h2><p class="mao-panel-sub">Leaflet-based barangay risk records for rainfall, yield, irrigation, and climate impact.</p></div><a class="btn btn-sm btn-outline-primary" href="{{ route('heatmap-areas.index') }}">Open Map</a></div>
                    <div class="mao-panel-body list-compact"><div class="heat-grid">
                        @forelse($latestHeatmapAreas as $area)
                            <div class="risk-card {{ strtolower($area->risk_level) }}"><div class="d-flex justify-content-between gap-2 mb-2"><strong>{{ $area->barangay }}</strong><span class="status-pill {{ in_array($area->risk_level, ['High','Severe']) ? 'muted' : '' }}">{{ $area->risk_level }}</span></div><div class="row-text">{{ $area->risk_type }} risk | {{ $area->predicted_yield !== null ? number_format($area->predicted_yield, 2).' t/ha' : 'No yield prediction' }}</div><div class="row-meta">{{ str($area->irrigation_recommendation ?: $area->description)->limit(70) }}</div></div>
                        @empty
                            <div class="empty-soft"><strong>No risk records</strong><div class="small text-muted mt-1">Barangay heat map records will appear here.</div></div>
                        @endforelse
                    </div></div>
                </div>
            </div>
        </div>
            </div>
        </details>

        <details class="dashboard-group">
            <summary>
                <div>
                    <h2 class="dashboard-group-title">Publishing Center</h2>
                    <p class="dashboard-group-note">Latest advisories and community feed posts for farmers.</p>
                </div>
                <span class="dashboard-group-toggle"></span>
            </summary>
            <div class="dashboard-group-body">
        <div class="row g-4 mb-0">
            <div class="col-xl-6">
                <div class="mao-panel h-100">
                    <div class="mao-panel-header"><div><h2 class="mao-panel-title">Advisory Management</h2><p class="mao-panel-sub">Latest advisories for rice farmers.</p></div><a class="btn btn-sm btn-primary" href="{{ route('planting-advisories.create') }}">New Advisory</a></div>
                    <div class="mao-panel-body list-compact">
                        @forelse($latestAdvisories as $advisory)
                            <div class="mao-list-item"><div class="list-mark">ADV</div><div><div class="row-title">{{ $advisory->title }}</div><div class="row-text">{{ str($advisory->content)->limit(95) }}</div><div class="row-meta">{{ $advisory->type }} | {{ $advisory->status }} | {{ $advisory->target_barangay ?: 'All barangays' }}</div></div></div>
                        @empty
                            <div class="empty-soft"><strong>No advisories yet</strong><div class="small text-muted mt-1">Create advisories for farmers from this module.</div></div>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="mao-panel h-100">
                    <div class="mao-panel-header"><div><h2 class="mao-panel-title">Community Feed Posts</h2><p class="mao-panel-sub">Latest posts published for farmers.</p></div><a class="btn btn-sm btn-primary" href="{{ route('community-feed.index') }}">New Post</a></div>
                    <div class="mao-panel-body list-compact">
                        @forelse($latestFeedPosts as $post)
                            <div class="mao-list-item"><div class="list-mark">{{ str($post->category)->substr(0, 3)->upper() }}</div><div><div class="row-title">{{ $post->title }}</div><div class="row-text">{{ str($post->body)->limit(95) }}</div><div class="row-meta">{{ $post->category }} | {{ $post->author?->name ?? 'MAO' }}</div></div></div>
                        @empty
                            <div class="empty-soft"><strong>No community posts yet</strong><div class="small text-muted mt-1">Published feed posts will appear here.</div></div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
            </div>
        </details>

        <details class="dashboard-group">
            <summary>
                <div>
                    <h2 class="dashboard-group-title">Reports</h2>
                    <p class="dashboard-group-note">Generate and review analytics outputs.</p>
                </div>
                <span class="dashboard-group-toggle"></span>
            </summary>
            <div class="dashboard-group-body">
        <div class="mao-panel">
            <div class="mao-panel-header"><div><h2 class="mao-panel-title">Reports & Analytics</h2><p class="mao-panel-sub">Generate summaries for climate records, rice production, farmer registration, and advisories.</p></div><a class="btn btn-sm btn-outline-primary" href="{{ route('reports.index') }}">Reports Center</a></div>
            <div class="mao-panel-body list-compact">
                <div class="quick-grid mb-3">
                    <a class="quick-action" href="{{ route('reports.index', ['report_type' => 'Climate Records Report']) }}"><strong>Climate Report</strong><span>Filter by dates, barangay, and season.</span></a>
                    <a class="quick-action" href="{{ route('reports.index', ['report_type' => 'Rice Production Report']) }}"><strong>Rice Production Report</strong><span>Review year, season, area, and production totals.</span></a>
                    <a class="quick-action" href="{{ route('reports.index', ['report_type' => 'Farmer Registration Report']) }}"><strong>Farmer Report</strong><span>Summarize farmer account and profile records.</span></a>
                </div>
                @forelse($latestReports as $report)
                    <div class="mao-list-item"><div class="list-mark">RPT</div><div class="flex-grow-1"><div class="row-title">{{ $report->title }}</div><div class="row-text">{{ $report->report_type }} generated by {{ $report->generatedBy?->name ?? 'System' }}.</div><div class="row-meta">{{ $report->created_at?->format('M d, Y') }}</div></div><a class="btn btn-sm btn-outline-primary" href="{{ route('reports.show', $report) }}">Open</a></div>
                @empty
                    <div class="empty-soft"><strong>No report history yet</strong><div class="small text-muted mt-1">Generated reports will appear here.</div></div>
                @endforelse
            </div>
        </div>
            </div>
        </details>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const chartData = @json($weatherChartData);
            const mapAreas = @json($dashboardMapAreas);
            const labels = chartData.labels || [];

            const chartOptions = (unit) => ({
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => `${context.dataset.label}: ${context.parsed.y} ${unit}`,
                        },
                    },
                },
                scales: {
                    x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, color: 'rgba(255,255,255,.55)' } },
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,.1)' }, ticks: { color: 'rgba(255,255,255,.55)' } },
                },
            });

            const makeChart = (id, label, values, color, unit) => {
                const canvas = document.getElementById(id);
                if (!canvas || !labels.length) return;

                new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [{
                            label,
                            data: values || [],
                            borderColor: color,
                            backgroundColor: `${color}22`,
                            fill: true,
                            borderWidth: 3,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            tension: .36,
                        }],
                    },
                    options: chartOptions(unit),
                });
            };

            makeChart('rainfallChart', 'Rainfall', chartData.rainfall, '#1677b8', 'mm');
            makeChart('temperatureChart', 'Temperature', chartData.temperature, '#d85b45', 'C');
            makeChart('humidityChart', 'Humidity', chartData.humidity, '#52b788', '%');
            makeChart('windSpeedChart', 'Wind Speed', chartData.windSpeed, '#f4b63f', '');

            const mapEl = document.getElementById('dashboardRiskMap');
            if (mapEl && mapAreas.length && window.L) {
                mapEl.classList.add('thermo-map');
                const intensityFor = (riskLevel, score) => {
                    const numericScore = Number(score || 0);
                    if (numericScore > 0) return Math.max(.12, numericScore);
                    if (['High', 'Severe'].includes(riskLevel)) return .92;
                    if (riskLevel === 'Moderate') return .58;
                    return .22;
                };
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
                const impactScore = (area) => intensityFor(area.risk_level, area.risk_score);
                const scoreFor = (area, layer) => ({ rainfall: rainfallScore, yield: yieldScore, irrigation: irrigationScore, impact: impactScore }[layer] || impactScore)(area);
                let heatLayer = null;
                let markerLayer = null;
                let activeDashboardLayer = 'impact';
                let selectedDashboardArea = null;

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
                const plainRain = (area) => {
                    const rainfall = String(area.rainfall_status || '').toLowerCase();
                    if (rainfall.includes('low')) return 'Rain may be too low. Water support may be needed.';
                    if (rainfall.includes('high')) return 'Rain may be too much. Watch drainage and low areas.';
                    return 'Rain looks manageable for now.';
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
                    if (layer === 'rainfall') return { label: 'Rain condition', value: area.rainfall_status || 'Not recorded', note: plainRain(area) };
                    if (layer === 'yield') return {
                        label: 'Estimated harvest',
                        value: harvestEstimate(area),
                        note: harvestSource(area),
                    };
                    if (layer === 'irrigation') return { label: 'Water support', value: area.irrigation_recommendation ? 'Action needed' : 'No urgent water note', note: plainAction(area) };
                    return { label: 'Main thing to watch', value: plainConcern(area), note: plainRiskMessage(area) };
                };
                const layerSecondDetail = (area, layer) => {
                    if (layer === 'rainfall') return { label: 'Field concern', value: plainConcern(area), note: 'Check low fields first after strong rain.' };
                    if (layer === 'yield') return { label: 'Field concern', value: plainConcern(area), note: 'This may affect the expected harvest.' };
                    if (layer === 'irrigation') return { label: 'Rain condition', value: area.rainfall_status || 'Not recorded', note: plainRain(area) };
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
                const popup = (area, layer = activeDashboardLayer) => {
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
                const detailPanel = document.getElementById('dashboardMapDetailPanel');
                const showDetails = (area, layer = activeDashboardLayer) => {
                    if (!detailPanel) return;

                    selectedDashboardArea = area;
                    detailPanel.innerHTML = `
                        <button class="map-detail-close" type="button" aria-label="Close details">&times;</button>
                        ${popup(area, layer)}
                    `;
                    detailPanel.classList.add('show');
                    detailPanel.querySelector('.map-detail-close')?.addEventListener('click', () => {
                        detailPanel.classList.remove('show');
                        selectedDashboardArea = null;
                        detailPanel.innerHTML = `
                            <button class="map-detail-close" type="button" aria-label="Close details">&times;</button>
                            <div class="map-detail-empty">Click a barangay on the heat map to view risk details here.</div>
                        `;
                    });
                };
                detailPanel?.querySelector('.map-detail-close')?.addEventListener('click', () => detailPanel.classList.remove('show'));

                const map = L.map(mapEl, { scrollWheelZoom: false }).setView([14.015, 120.65], 12);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 18,
                    attribution: '&copy; OpenStreetMap contributors',
                }).addTo(map);

                const renderDashboardLayer = (layer = 'impact') => {
                    activeDashboardLayer = layer;
                    if (heatLayer) map.removeLayer(heatLayer);
                    if (markerLayer) map.removeLayer(markerLayer);

                    if (L.heatLayer) {
                        heatLayer = L.heatLayer(mapAreas.map((area) => [
                            area.latitude,
                            area.longitude,
                            Math.max(.12, scoreFor(area, layer)),
                        ]), {
                            radius: 72,
                            blur: 44,
                            maxZoom: 14,
                            minOpacity: .42,
                            gradient: thermographicGradient,
                        }).addTo(map);
                    }

                    markerLayer = L.featureGroup(mapAreas.flatMap((area) => {
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

                        clickZone.on('click', () => showDetails(area, layer));
                        marker.on('click', () => showDetails(area, layer));

                        marker.bindTooltip(area.barangay, {
                            permanent: true,
                            direction: 'top',
                            offset: [0, -10],
                            className: 'barangay-tooltip',
                        });

                        return [clickZone, marker];
                    })).addTo(map);

                    if (selectedDashboardArea && detailPanel?.classList.contains('show')) {
                        showDetails(selectedDashboardArea, layer);
                    }
                };

                renderDashboardLayer();
                map.fitBounds(markerLayer.getBounds().pad(.2));
                setTimeout(() => map.invalidateSize(), 150);

                document.querySelectorAll('[data-dashboard-layer]').forEach((button) => {
                    button.addEventListener('click', () => {
                        document.querySelectorAll('[data-dashboard-layer]').forEach((item) => {
                            item.classList.remove('active');
                            item.setAttribute('aria-pressed', 'false');
                        });
                        button.classList.add('active');
                        button.setAttribute('aria-pressed', 'true');
                        renderDashboardLayer(button.dataset.dashboardLayer);
                    });
                });
            }
        });
    </script>
</x-app-layout>
