<x-app-layout>
    <style>
        .mao-console {
            --mao-ink: #1F2937;
            --mao-muted: #64748B;
            --mao-green: #1F5A46;
            --mao-blue: #4B7185;
            --mao-accent: #8FAF9A;
            --mao-accent-dark: #6F8978;
            --mao-red: #B84A4A;
            --mao-mint: #C9D8CE;
            --mao-accent-light: #C9D8CE;
            --mao-line: #D8E0DA;
            --mao-green-50: rgba(95,143,120,.1);
            --mao-green-100: rgba(127,214,181,.22);
            color: var(--mao-ink);
            font-family: 'Inter', system-ui, sans-serif;
        }
        .mao-console h1, .mao-console h2, .mao-console h3 { font-family: 'DM Serif Display', Georgia, serif; font-weight: 400 !important; letter-spacing: -.01em; }
        .mao-console .mono { font-family: 'DM Mono', monospace; }
        /* -- Buttons unified with landing/farmer pill style -- */
        .mao-console .btn { border-radius: 999px !important; font-weight: 600 !important; letter-spacing: .01em; transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease, border-color .18s ease; }
        .mao-console .btn-primary, .mao-console .btn-light {
            background: var(--mao-green) !important; border-color: var(--mao-green) !important; color: #fff !important;
            box-shadow: none;
        }
        .mao-console .btn-primary:hover, .mao-console .btn-light:hover { background: #123F32 !important; border-color: #123F32 !important; color: #fff !important; transform: translateY(-1px); }
        .mao-console .btn-outline-light { border-color: rgba(255,255,255,.55) !important; color: #fff !important; background: transparent !important; }
        .mao-console .btn-outline-light:hover { background: #fff !important; border-color: #fff !important; color: var(--mao-ink) !important; }
        .mao-console .btn-outline-primary { border-color: var(--mao-green) !important; color: var(--mao-green) !important; background: transparent !important; }
        .mao-console .btn-outline-primary:hover { background: var(--mao-green) !important; border-color: var(--mao-green) !important; color: #fff !important; transform: translateY(-1px); }
        .mao-console .btn-sm { padding: .4rem .9rem; font-size: .78rem; }
        .mao-hero {
            position: relative;
            overflow: hidden;
            border-radius: 32px;
            padding: 1.35rem;
            margin-bottom: 1.25rem;
            color: #fff;
            background:
                radial-gradient(circle at 84% 12%, rgba(127,214,181,.14), transparent 30%),
                linear-gradient(145deg, rgba(22,59,45,.6) 0%, rgba(31,77,58,.5) 100%);
            backdrop-filter: var(--glass-blur, blur(22px) saturate(170%));
            -webkit-backdrop-filter: var(--glass-blur, blur(22px) saturate(170%));
            border: 1px solid rgba(255,255,255,.16);
            box-shadow: 0 1rem 2.3rem rgba(13,31,24,.28);
        }
        .mao-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, rgba(255,255,255,.06) 1px, transparent 1px),
                linear-gradient(0deg, rgba(255,255,255,.045) 1px, transparent 1px);
            background-size: 38px 38px;
            mask-image: linear-gradient(90deg, rgba(0,0,0,.78), transparent 88%);
        }
        .mao-hero > * { position: relative; z-index: 1; }
        .mao-eyebrow {
            font-family: 'DM Mono', monospace;
            font-size: .72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: var(--mao-accent-light);
        }
        .mao-hero h1 { color: #fff; }
        .mao-hero em { color: #fff; font-style: normal; }
        .mao-hero p { color: rgba(255,255,255,.72); max-width: 760px; }
        .mao-chip {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 999px;
            padding: .45rem .75rem;
            background: rgba(255,255,255,.12);
            backdrop-filter: blur(8px);
            color: rgba(255,255,255,.85);
            font-family: 'DM Mono', monospace;
            font-size: .78rem;
            font-weight: 500;
        }
        .mao-pulse { width: 10px; height: 10px; border-radius: 999px; background: var(--mao-mint); box-shadow: none; }
        .mao-hero .btn-outline-light { border-color: #fff; color: #fff; }
        .mao-hero .btn-outline-light:hover { background: #fff; border-color: #fff; color: var(--mao-ink); }
        .mao-stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
        .mao-card {
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,.55);
            border-radius: 18px;
            background: rgba(255,255,255,.66);
            backdrop-filter: var(--glass-blur, blur(22px) saturate(170%));
            -webkit-backdrop-filter: var(--glass-blur, blur(22px) saturate(170%));
            box-shadow: var(--glass-light-shadow, 0 10px 34px rgba(8,25,19,.16));
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, background .18s ease;
            width: 100%;
            text-align: left;
            font: inherit;
            cursor: pointer;
        }
        .mao-card:hover { transform: translateY(-2px); box-shadow: 0 1.1rem 2.2rem rgba(20,32,51,.11); border-color: rgba(95,143,120,.55); }
        .mao-card:focus-visible { outline: 2px solid var(--mao-mint); outline-offset: 2px; }
        .mao-tap-hint { color: var(--mao-green); font-size: .74rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; margin-top: .5rem; opacity: 0; transform: translateY(2px); transition: opacity .18s ease, transform .18s ease; }
        .mao-card:hover .mao-tap-hint, .mao-card:focus-visible .mao-tap-hint { opacity: 1; transform: translateY(0); }
        .mao-modal-content { border-radius: 18px; background: linear-gradient(145deg, #ffffff, #f7fbf8); color: var(--mao-muted); overflow: hidden; }
        .mao-modal-content .modal-footer { border-top-color: rgba(95,143,120,.4); }
        .mao-modal-header { background: linear-gradient(90deg, var(--mao-green-50), var(--mao-green-100)); color: var(--mao-ink); }
        .mao-modal-header .modal-title { color: var(--mao-ink); }
        .mao-modal-headline { font-family: 'DM Serif Display', serif; font-size: 1.6rem; font-weight: 400; color: var(--mao-ink); }
        .mao-modal-sub { color: var(--mao-muted); font-size: .85rem; margin: .3rem 0 .9rem; }
        .mao-card-body { padding: 1rem; position: relative; z-index: 1; }
        .mao-label { color: var(--mao-muted); font-family: 'DM Mono', monospace; font-size: .68rem; font-weight: 500; text-transform: uppercase; letter-spacing: .07em; }
        .mao-value { margin-top: .35rem; font-family: 'DM Serif Display', serif; font-size: clamp(1.55rem, 3vw, 2.2rem); line-height: 1; font-weight: 400; color: var(--mao-ink); letter-spacing: -.02em; }
        .mao-value-unit { font-family: 'Inter', sans-serif; font-size: .95rem; font-weight: 600; color: var(--mao-muted); letter-spacing: 0; }
        .mao-note { color: var(--mao-muted); font-size: .84rem; margin-top: .65rem; }
        .mao-panel {
            border: 1px solid rgba(255,255,255,.55);
            border-radius: 18px;
            background: rgba(255,255,255,.66);
            backdrop-filter: var(--glass-blur, blur(22px) saturate(170%));
            -webkit-backdrop-filter: var(--glass-blur, blur(22px) saturate(170%));
            box-shadow: var(--glass-light-shadow, 0 10px 34px rgba(8,25,19,.16));
            overflow: hidden;
        }
        .mao-panel-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 1rem .75rem; border-bottom: 1px solid rgba(95,143,120,.58); background: var(--mao-green-50); }
        .mao-panel-title { font-size: 1.05rem; margin: 0; color: var(--mao-ink); }
        .mao-panel-sub { margin: .15rem 0 0; color: var(--mao-muted); font-size: .82rem; }
        .mao-panel-body { padding: 1rem; }
        .mao-list-item { display: flex; gap: .85rem; align-items: flex-start; padding: .85rem 0; }
        .mao-list-item + .mao-list-item { border-top: 1px solid rgba(95,143,120,.55); }
        .list-mark { width: 38px; height: 38px; border-radius: 50%; display: grid; place-items: center; background: rgba(95,143,120,.14); border: 1px solid rgba(127,214,181,.35); color: var(--mao-green); flex: 0 0 auto; }
        .list-mark svg { width: 17px; height: 17px; }
        .row-title { font-weight: 700; color: var(--mao-ink); line-height: 1.25; }
        .row-text { color: var(--mao-muted); font-size: .84rem; margin-top: .15rem; line-height: 1.45; }
        .row-meta { color: var(--mao-muted); font-family: 'DM Mono', monospace; font-size: .68rem; margin-top: .35rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; }
        .mao-risk-badge { display: inline-flex; align-items: center; margin-left: .5rem; padding: .15rem .55rem; border-radius: 999px; font-family: 'DM Mono', monospace; font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; vertical-align: middle; white-space: nowrap; }
        .mao-risk-badge-severe { background: rgba(239,68,68,.16); color: #b91c1c; }
        .mao-risk-badge-high { background: rgba(249,115,22,.16); color: #c2410c; }
        .mao-risk-badge-moderate { background: rgba(250,204,21,.22); color: #854d0e; }
        .mao-risk-badge-low { background: rgba(34,197,94,.16); color: #15803d; }
        .mao-risk-interpretation { font-weight: 500; color: var(--mao-ink); }
        .status-pill { display: inline-flex; align-items: center; border-radius: 999px; padding: .3rem .62rem; font-family: 'DM Mono', monospace; font-size: .64rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; background: rgba(95,143,120,.14); color: var(--mao-green); }
        .status-pill.muted { background: var(--mao-green-50); color: var(--mao-muted); }
        .status-pill.risk-badge-lg { padding: .38rem .8rem; font-size: .74rem; font-weight: 900; }
        .status-pill.risk-badge-lg.low { background: #dcfce7; color: #15803d; }
        .status-pill.risk-badge-lg.moderate { background: #fef9c3; color: #854d0e; }
        .status-pill.risk-badge-lg.high { background: #ffedd5; color: #c2410c; }
        .status-pill.risk-badge-lg.severe { background: #fee2e2; color: #b91c1c; }
        .quick-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .85rem; }
        .quick-action { min-height: 108px; padding: 1rem; border-radius: 10px; border: 1px solid rgba(255,255,255,.5); background: rgba(238,243,239,.55); backdrop-filter: blur(8px) saturate(150%); color: inherit; text-decoration: none; }
        .quick-action:hover { transform: translateY(-2px); border-color: rgba(95,143,120,.55); }
        .quick-action strong { display: block; color: var(--mao-ink); }
        .quick-action span { display: block; color: var(--mao-muted); font-size: .82rem; margin-top: .25rem; }
        .heat-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .85rem; }
        .risk-card { padding: 1rem; border-radius: 8px; border: 1px solid rgba(34,197,94,.4); border-left: 5px solid #22c55e; background: #f0fdf4; }
        .risk-card strong { color: var(--mao-ink); }
        .risk-card.moderate { border-color: rgba(250,204,21,.55); border-left-color: #facc15; background: #fefce8; }
        .risk-card.high { border-color: rgba(249,115,22,.5); border-left-color: #f97316; background: #fff7ed; }
        .risk-card.severe { border-color: rgba(239,68,68,.5); border-left-color: #ef4444; background: #fef2f2; }
        .risk-card .risk-card-meta { display: grid; gap: .3rem; margin-top: .55rem; padding-top: .55rem; border-top: 1px dashed rgba(95,143,120,.35); font-size: .78rem; color: var(--mao-muted); }
        .risk-card .risk-card-meta strong { color: var(--mao-ink); font-weight: 700; }
        .variable-info-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .85rem; margin-bottom: 1rem; }
        .variable-info-card { border: 1px solid rgba(255,255,255,.55); border-radius: 8px; background: rgba(255,255,255,.66); backdrop-filter: blur(16px) saturate(170%); padding: .85rem .9rem; }
        .variable-info-card .row-text { margin-top: .3rem; }
        .visual-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
        .live-weather-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; margin-bottom: 1rem; }
        .live-weather-card { border: 1px solid rgba(255,255,255,.55); border-radius: 8px; background: rgba(255,255,255,.66); backdrop-filter: blur(16px) saturate(170%); padding: .9rem; }
        .source-badge { display: inline-flex; align-items: center; gap: .4rem; border-radius: 999px; border: 1px solid rgba(22,119,184,.24); background: #eef7ff; color: #155a84; padding: .35rem .65rem; font-size: .76rem; font-weight: 700; }
        .chart-box { position: relative; min-height: 330px; padding: 1rem; border: 1px solid rgba(255,255,255,.55); border-radius: 8px; background: rgba(255,255,255,.66); backdrop-filter: blur(16px) saturate(170%); }
        .chart-canvas-scroll { overflow-x: auto; }
        .chart-box canvas { width: 100% !important; min-width: 420px; height: 260px !important; }
        .chart-interpretation { margin-top: .85rem; padding: .85rem .9rem; border: 1px solid rgba(95,143,120,.35); border-radius: 8px; background: rgba(255,255,255,.78); }
        .chart-interpretation-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .5rem .6rem; margin-bottom: .5rem; }
        .chart-interpretation-label { font-size: .78rem; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; color: var(--mao-muted); }
        .chart-interpretation-row { color: var(--mao-ink); font-size: .88rem; line-height: 1.5; }
        .chart-interpretation-row + .chart-interpretation-row { margin-top: .4rem; }
        .chart-interpretation-action strong { font-weight: 900; }
        .chart-render-state { display: flex; align-items: center; justify-content: center; min-width: 420px; min-height: 260px; padding: 1rem; border: 1px dashed rgba(95,143,120,.42); border-radius: 8px; background: rgba(247,251,248,.9); color: #334155; font-size: 1rem; font-weight: 700; line-height: 1.5; text-align: center; }
        .chart-render-state[hidden] { display: none; }
        .chart-title { color: var(--mao-ink); font-weight: 700; margin-bottom: .25rem; }
        .chart-note { color: var(--mao-muted); font-size: .82rem; margin-bottom: .85rem; }
        .dashboard-map-shell { position: relative; overflow: hidden; border-radius: 8px; }
        .map-toolbar { position: absolute; z-index: 500; left: 1rem; right: 1rem; top: 1rem; display: flex; gap: .5rem; flex-wrap: wrap; pointer-events: none; }
        .map-toolbar > * { pointer-events: auto; }
        .layer-btn { min-height: 38px; border: 1px solid rgba(95,143,120,.35); border-radius: 8px; background: rgba(255,255,255,.8); backdrop-filter: blur(14px) saturate(170%); color: var(--mao-ink); padding: .48rem .64rem; font-size: .78rem; font-weight: 700; box-shadow: 0 .5rem 1.2rem rgba(13,31,24,.08); white-space: nowrap; }
        .layer-btn.active { background: #1F4D3A; border-color: #1F4D3A; color: #fff; }
        .dashboard-map {
            height: 540px;
            min-height: 380px;
            border-radius: 8px;
            overflow: hidden;
            background: #dbe8e0;
        }
        .dashboard-map-legend {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem .9rem;
            align-items: center;
            margin: .6rem 0 .85rem;
            padding: .65rem .85rem;
            border: 1px solid rgba(95,143,120,.35);
            border-radius: 8px;
            background: rgba(255,255,255,.7);
            backdrop-filter: blur(12px) saturate(160%);
            font-size: .82rem;
            font-weight: 700;
            color: var(--mao-ink);
        }
        .dashboard-map-legend .legend-swatch { display: inline-flex; align-items: center; gap: .4rem; }
        .dashboard-map-legend .legend-dot { width: .85rem; height: .85rem; border-radius: 999px; flex: 0 0 auto; box-shadow: 0 0 0 1px rgba(13,31,24,.18); }
        .thermo-point {
            width: 20px;
            height: 20px;
            border-radius: 999px;
            border: 2.5px solid rgba(13,31,24,.85);
            background: var(--risk-dot-color, #22c55e);
            box-shadow: 0 0 0 4px rgba(255,255,255,.55), 0 .35rem .8rem rgba(13,31,24,.28);
        }
        .leaflet-popup-content { width: min(320px, calc(100vw - 4.5rem)) !important; min-width: 0; margin: .85rem; }
        .leaflet-popup-content-wrapper { border-radius: 8px; }
        .map-popup { color: var(--mao-ink); font-size: .86rem; line-height: 1.35; }
        .map-popup-title { display: flex; justify-content: space-between; align-items: flex-start; gap: .6rem; border-bottom: 1px solid rgba(95,143,120,.3); padding-bottom: .58rem; margin-bottom: .62rem; }
        .map-popup-name { color: var(--mao-ink); font-size: 1rem; font-weight: 700; line-height: 1.18; }
        .map-popup-badge { flex: 0 0 auto; border-radius: 999px; padding: .28rem .52rem; background: var(--popup-bg, rgba(95,143,120,.14)); color: var(--popup-color, #1F4D3A); font-size: .7rem; font-weight: 700; }
        .map-popup-takeaway { border-left: 4px solid var(--popup-accent, #5F8F78); background: #f7fbf8; border-radius: 8px; padding: .58rem .65rem; margin-bottom: .6rem; font-weight: 800; }
        .map-popup-grid { display: grid; grid-template-columns: 1fr; gap: .45rem; }
        .map-popup-box { border: 1px solid rgba(95,143,120,.3); border-radius: 8px; background: rgba(255,255,255,.85); backdrop-filter: blur(10px) saturate(160%); padding: .52rem .58rem; }
        .map-popup-label { color: var(--mao-muted); font-size: .66rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
        .map-popup-value { color: var(--mao-ink); font-weight: 700; margin-top: .16rem; }
        .map-popup-note { color: var(--mao-muted); font-size: .78rem; margin-top: .18rem; }
        .map-popup-advice { border: 1px solid rgba(95,143,120,.3); border-radius: 8px; background: rgba(95,143,120,.1); padding: .58rem .65rem; margin-top: .55rem; }
        .map-popup-source { color: var(--mao-muted); font-size: .74rem; margin-top: .55rem; border-top: 1px solid rgba(95,143,120,.3); padding-top: .5rem; }
        .map-detail-panel {
            position: absolute;
            top: 1rem;
            right: 1rem;
            z-index: 520;
            width: min(292px, calc(100% - 2rem));
            max-height: calc(100% - 2rem);
            overflow: auto;
            border: 1px solid rgba(95,143,120,.3);
            border-radius: 8px;
            background: rgba(255,255,255,.82);
            backdrop-filter: blur(18px) saturate(170%);
            box-shadow: 0 .9rem 2rem rgba(13,31,24,.18);
            padding: .85rem;
            display: none;
        }
        .map-detail-panel.show { display: block; }
        .map-detail-empty { color: var(--mao-muted); font-size: .86rem; line-height: 1.45; }
        .map-detail-close { position: absolute; top: .5rem; right: .5rem; width: 30px; height: 30px; border: 1px solid rgba(95,143,120,.3); border-radius: 8px; background: #fff; color: var(--mao-ink); font-weight: 700; line-height: 1; }
        .map-detail-panel .map-popup-title { padding-right: 2rem; }
        .risk-level-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; }
        .risk-level-card { border: 1px solid rgba(255,255,255,.55); border-radius: 8px; background: rgba(255,255,255,.66); backdrop-filter: blur(16px) saturate(170%); padding: .9rem; }
        .risk-level-card.low { border-left: 5px solid #22c55e; }
        .risk-level-card.moderate { border-left: 5px solid #facc15; }
        .risk-level-card.high { border-left: 5px solid #f97316; }
        .risk-level-card.severe { border-left: 5px solid #ef4444; }
        .risk-level-value { color: var(--mao-ink); font-size: 1.85rem; font-weight: 700; line-height: 1; margin-top: .35rem; }
        .barangay-tooltip { border: 0; border-radius: 999px; background: rgba(13,31,24,.88); color: #fff; font-size: .68rem; font-weight: 700; padding: .22rem .45rem; box-shadow: 0 .45rem 1rem rgba(13,31,24,.16); }
        .barangay-tooltip::before { display: none; }
        .empty-soft { border: 1px dashed #5F8F78; background: rgba(238,243,239,.55); backdrop-filter: blur(8px) saturate(150%); border-radius: 8px; padding: 1.5rem; text-align: center; }
        .empty-soft strong { color: var(--mao-ink); }
        .dashboard-section-label { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin: 1.35rem 0 .75rem; }
        .dashboard-section-label:first-of-type { margin-top: 0; }
        .section-title { color: var(--mao-accent-light); font-family: 'DM Mono', monospace !important; font-size: .74rem; font-weight: 600 !important; text-transform: uppercase; letter-spacing: .1em; margin: 0; }
        .section-note { color: var(--mao-mint); font-size: .84rem; margin: 0; }
        .dashboard-focus-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        .schedule-monitor-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
        .schedule-monitor-card { border: 1px solid var(--mao-border); border-radius: 10px; padding: 1rem; background: #fff; color: var(--mao-ink); }
        .schedule-monitor-card h3 { font-size: 1.05rem; font-weight: 800; margin: 0; }
        .schedule-monitor-meta { color: var(--mao-muted); font-size: .9rem; line-height: 1.55; }
        .schedule-yield-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .6rem; margin-top: .85rem; }
        .schedule-yield-box { border-radius: 8px; padding: .7rem; background: rgba(31,42,36,.045); }
        .schedule-yield-box span { display: block; color: var(--mao-muted); font-size: .78rem; font-weight: 700; }
        .schedule-yield-box strong { display: block; margin-top: .2rem; font-size: 1rem; }
        .schedule-guidance { margin-top: .85rem; border-left: 4px solid #6c757d; border-radius: 6px; padding: .75rem .85rem; background: rgba(108,117,125,.08); font-size: .9rem; line-height: 1.55; }
        .schedule-guidance.urgent { border-left-color: #b02a37; background: rgba(220,53,69,.08); }
        .dashboard-group {
            border: 1px solid var(--mao-line);
            border-radius: 18px;
            background: linear-gradient(145deg, #ffffff, #f7fbf8);
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
            background: var(--mao-green-50);
        }
        .dashboard-group > summary::-webkit-details-marker { display: none; }
        .dashboard-group-title { color: var(--mao-ink); font-family: 'Inter', sans-serif !important; font-size: .92rem; font-weight: 700 !important; text-transform: none; letter-spacing: -.005em; margin: 0; }
        .dashboard-group-note { color: var(--mao-muted); font-size: .82rem; margin: .1rem 0 0; }
        .dashboard-group-toggle { color: var(--mao-green); font-size: .78rem; font-weight: 700; white-space: nowrap; }
        .dashboard-group-toggle::after { content: "Collapse"; }
        .dashboard-group:not([open]) .dashboard-group-toggle::after { content: "Expand"; }
        .dashboard-group-body { padding: 1rem; }
        .dashboard-group-body > .row:last-child, .dashboard-group-body > .mao-panel:last-child { margin-bottom: 0 !important; }
        .list-compact .mao-list-item:nth-of-type(n+5), .list-compact .risk-card:nth-of-type(n+5) { display: none; }
        @media (max-width: 1199.98px) { .mao-stat-grid, .visual-grid, .live-weather-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .quick-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .risk-level-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 767.98px) {
            .mao-stat-grid, .visual-grid, .quick-grid, .heat-grid, .variable-info-grid { grid-template-columns: 1fr; }
            .mao-stat-grid, .visual-grid, .live-weather-grid, .quick-grid, .heat-grid, .risk-level-grid, .variable-info-grid { gap: .6rem; }
            .variable-info-card { padding: .75rem; }
            .live-weather-grid, .risk-level-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .mao-stat-grid { margin-bottom: .9rem; }
            .mao-card-body { padding: .85rem; }
            .mao-panel-header, .dashboard-section-label, .dashboard-group > summary { align-items: flex-start; flex-direction: column; }
            .mao-panel-header { padding: .85rem .85rem .6rem; }
            .mao-panel-body { padding: .8rem; }
            .mao-list-item { padding: .65rem 0; gap: .65rem; }
            .dashboard-section-label { margin: 1rem 0 .6rem; }
            .dashboard-group-body { padding: .75rem; }
            .dashboard-group > summary { padding: .8rem .85rem; }
            .mao-hero { padding: 1.05rem; border-radius: 22px; margin-bottom: .9rem; }
            .live-weather-card { padding: .7rem; }
            .live-weather-grid { margin-bottom: .75rem; }
            .risk-level-card { padding: .65rem; }
            .risk-level-value { font-size: 1.5rem; }
            .risk-card { padding: .75rem; }
            .quick-action { min-height: 84px; padding: .8rem; }
            .chart-box { padding: .75rem; min-height: 240px; }
            .chart-box canvas { height: 200px !important; }
            .chart-interpretation { padding: .7rem .75rem; margin-top: .7rem; }
            .chart-interpretation-row { font-size: .84rem; }
            .dashboard-map { height: 300px; }
            .dashboard-map-shell { overflow: visible; }
            .map-toolbar { position: relative; inset: auto; margin-bottom: .75rem; }
            .layer-btn { flex: 0 0 auto; min-width: max-content; font-size: .76rem; min-height: 40px; }
            .map-detail-panel { position: relative; inset: auto; width: auto; max-height: 300px; margin-top: .75rem; }
        }
        @media (max-width: 399.98px) {
            .live-weather-grid, .risk-level-grid { grid-template-columns: 1fr; }
            .mao-value { font-size: clamp(1.4rem, 7vw, 1.75rem); }
            .risk-level-value { font-size: 1.35rem; }
            .mao-hero { padding: .9rem; }
            .mao-chip { font-size: .72rem; padding: .38rem .6rem; }
        }
        @media (max-width: 767.98px) { .mao-stat-grid, .live-weather-grid, .risk-level-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .visual-grid, .quick-grid, .heat-grid, .schedule-monitor-grid { grid-template-columns: 1fr; } .schedule-yield-grid { grid-template-columns: 1fr; } .mao-panel-header, .dashboard-section-label, .dashboard-group > summary { align-items: flex-start; flex-direction: column; } .mao-card-body, .live-weather-card, .risk-level-card { padding: .78rem; } .mao-value, .risk-level-value { font-size: 1.55rem; } .chart-box { min-height: 280px; padding: .75rem; } .chart-box canvas { min-width: 360px; height: 220px !important; } .dashboard-map { height: min(52vh, 320px); min-height: 260px; } .dashboard-map-shell { overflow: visible; } .map-toolbar { position: relative; inset: auto; margin-bottom: .75rem; overflow-x: auto; flex-wrap: nowrap; pointer-events: auto; -webkit-overflow-scrolling: touch; } .layer-btn { flex: 0 0 auto; min-width: max-content; font-size: .76rem; } .map-detail-panel { position: relative; inset: auto; width: auto; max-height: 260px; margin-top: .75rem; } }
        @media (max-width: 380px) { .mao-stat-grid, .live-weather-grid, .risk-level-grid { grid-template-columns: 1fr; } }

        /* Product UI restraint pass */
        .mao-console .btn { border-radius: 8px !important; box-shadow: none !important; }
        .mao-console .btn:hover { transform: translateY(-1px) !important; }
        .mao-hero {
            border-radius: 16px;
            padding: 1.25rem 1.4rem;
            background: linear-gradient(145deg, rgba(22,59,45,.6) 0%, rgba(31,77,58,.5) 100%);
            backdrop-filter: var(--glass-blur, blur(22px) saturate(170%));
            -webkit-backdrop-filter: var(--glass-blur, blur(22px) saturate(170%));
            box-shadow: 0 6px 16px rgba(13,31,24,.12);
        }
        .mao-hero::before { opacity: .28; }
        .mao-eyebrow { text-transform: none; letter-spacing: 0; font-family: 'Inter', sans-serif; font-size: .82rem; }
        .mao-hero em { font-style: normal; color: #fff; }
        .mao-chip { border-radius: 8px; background: rgba(255,255,255,.08); font-family: 'Inter', sans-serif; letter-spacing: 0; }
        .mao-pulse { width: 8px; height: 8px; box-shadow: none; }
        .mao-card,
        .mao-panel,
        .dashboard-group {
            border: 1px solid rgba(255,255,255,.55);
            border-radius: 12px;
            background: rgba(255,255,255,.66);
            backdrop-filter: var(--glass-blur, blur(22px) saturate(170%));
            -webkit-backdrop-filter: var(--glass-blur, blur(22px) saturate(170%));
            box-shadow: 0 1px 2px rgba(13,31,24,.08);
        }
        .mao-card:hover,
        .quick-action:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(13,31,24,.10); }
        .mao-panel-header,
        .mao-modal-header { background: rgba(245,247,244,.55); backdrop-filter: blur(8px); }
        .list-mark { width: 32px; height: 32px; border-radius: 8px; background: transparent; }
        .status-pill { border-radius: 999px; font-family: 'Inter', sans-serif; text-transform: none; letter-spacing: 0; }
        .section-title,
        .mao-label,
        .row-meta { letter-spacing: 0; text-transform: none; font-family: 'Inter', sans-serif !important; }
        .mao-console {
            --text-xs: .75rem;
            --text-sm: .875rem;
            --text-base: 1rem;
            --text-lg: 1.125rem;
            --text-2xl: 1.5rem;
            font-size: var(--text-base);
            line-height: 1.58;
        }
        .mao-console .btn { font-size: var(--text-sm); line-height: 1.35; }
        .mao-console .btn-sm { font-size: var(--text-sm); padding: .46rem .95rem; }
        .mao-eyebrow,
        .section-title,
        .mao-label,
        .row-meta,
        .dashboard-group-toggle,
        .map-popup-label { font-size: var(--text-sm); line-height: 1.35; }
        .mao-hero p,
        .section-note,
        .mao-note,
        .mao-panel-sub,
        .row-text,
        .dashboard-group-note,
        .quick-action span,
        .chart-note,
        .map-popup-note,
        .map-popup-source,
        .map-detail-empty { font-size: var(--text-sm); line-height: 1.5; color: var(--mao-muted); }
        .mao-panel-title,
        .chart-title,
        .dashboard-group-title,
        .row-title,
        .quick-action strong,
        .map-popup-name { font-size: var(--text-base); line-height: 1.35; }
        .mao-value,
        .risk-level-value { font-size: clamp(1.75rem, 3vw, 2.25rem); line-height: 1.08; }
        .status-pill,
        .map-popup-badge { font-size: var(--text-xs); line-height: 1.25; padding: .34rem .62rem; }
        .mao-modal-headline { font-size: var(--text-2xl); line-height: 1.18; }
        .mao-modal-sub { font-size: var(--text-sm); line-height: 1.48; color: var(--mao-muted); }
        .mao-console .table th,
        .mao-console .table td { font-size: var(--text-sm); line-height: 1.45; }
    </style>

    @php
        $latestClimate = $latestClimate ?? null;
        $profileCount = $profileCount ?? 0;
        $totalClimateRecords = $totalClimateRecords ?? 0;
        $riceProductionTotal = $riceProductionTotal ?? 0;
        $riceAreaTotal = $riceAreaTotal ?? 0;
        $avgYield = $avgYield ?? 0;
        $heatMapCount = $heatMapCount ?? 0;
        $highRiskHeatMapAreas = $highRiskHeatMapAreas ?? 0;
        $weatherDataSource = $weatherDataSource ?? 'Stored climate records';
        $liveWeather = $liveWeather ?? null;
        $farmerOrganizationSummary = array_replace([
            'totalFarmers' => 0,
            'totalOrganizations' => 0,
            'farmersWithoutOrganization' => 0,
            'totalFarmArea' => 0,
        ], $farmerOrganizationSummary ?? []);
        $weatherChartData = array_replace([
            'labels' => [],
            'rainfall' => [],
            'temperature' => [],
            'humidity' => [],
            'windSpeed' => [],
        ], $weatherChartData ?? []);
        $riskLevelCounts = array_replace([
            'Low' => 0,
            'Moderate' => 0,
            'High' => 0,
            'Severe' => 0,
        ], $riskLevelCounts ?? []);
        $recentFarmerProfiles = collect($recentFarmerProfiles ?? []);
        $recentClimateRecords = collect($recentClimateRecords ?? []);
        $recentRiceProductions = collect($recentRiceProductions ?? []);
        $highRiskAreasList = collect($highRiskAreasList ?? []);
        $dashboardMapAreas = collect($dashboardMapAreas ?? [])->values();
        $latestHeatmapAreas = collect($latestHeatmapAreas ?? []);
        $latestAdvisories = collect($latestAdvisories ?? []);
        $latestFeedPosts = collect($latestFeedPosts ?? []);
        $latestReports = collect($latestReports ?? []);
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

        @if (($maoActionItems['feedbackAwaitingResponse'] ?? 0) > 0 || ($maoActionItems['highRiskAreaCount'] ?? 0) > 0)
            <div class="dashboard-section-label">
                <div>
                    <h2 class="section-title">Needs Your Attention</h2>
                    <p class="section-note">Items that need MAO action right now.</p>
                </div>
            </div>
            <section class="mao-stat-grid mb-4">
                @if (($maoActionItems['feedbackAwaitingResponse'] ?? 0) > 0)
                    <a class="mao-card text-decoration-none" href="{{ route('planting-advisories.index') }}"><div class="mao-card-body"><div class="mao-label">Farmer Feedback Awaiting Reply</div><div class="mao-value">{{ number_format($maoActionItems['feedbackAwaitingResponse']) }}</div><div class="mao-note">Farmers are waiting on a response</div><div class="mao-tap-hint">Open advisories &rarr;</div></div></a>
                @endif
                @if (($maoActionItems['highRiskAreaCount'] ?? 0) > 0)
                    <button type="button" class="mao-card text-decoration-none" data-drawer-open="#maoStatRisk"><div class="mao-card-body"><div class="mao-label">High/Severe Risk Barangays</div><div class="mao-value">{{ number_format($maoActionItems['highRiskAreaCount']) }}</div><div class="mao-note">May need field follow-up</div><div class="mao-tap-hint">View details &rarr;</div></div></button>
                @endif
            </section>
        @endif

        <div class="dashboard-section-label">
            <div>
                <h2 class="section-title">Office Overview</h2>
                <p class="section-note">Core records and risk modules for the municipality.</p>
            </div>
        </div>

        <section class="mao-stat-grid">
            <button type="button" class="mao-card text-decoration-none" data-drawer-open="#maoStatFarmers"><div class="mao-card-body"><div class="mao-label">Farmer Profiles</div><div class="mao-value">{{ number_format($profileCount) }}</div><div class="mao-note">Registered farm records</div><div class="mao-tap-hint">View details &rarr;</div></div></button>
            <button type="button" class="mao-card text-decoration-none" data-drawer-open="#maoStatClimate"><div class="mao-card-body"><div class="mao-label">Climate Records</div><div class="mao-value">{{ number_format($totalClimateRecords) }}</div><div class="mao-note">Latest: {{ $latestClimate?->record_date?->format('M d, Y') ?? 'N/A' }}</div><div class="mao-tap-hint">View details &rarr;</div></div></button>
            <button type="button" class="mao-card text-decoration-none" data-drawer-open="#maoStatRice"><div class="mao-card-body"><div class="mao-label">Rice Production</div><div class="mao-value">{{ number_format($riceProductionTotal, 1) }} <span class="mao-value-unit">tons</span></div><div class="mao-note">{{ number_format($riceAreaTotal, 1) }} hectares recorded</div><div class="mao-tap-hint">View details &rarr;</div></div></button>
            <button type="button" class="mao-card text-decoration-none" data-drawer-open="#maoStatRisk"><div class="mao-card-body"><div class="mao-label">Heat Map Risks</div><div class="mao-value">{{ number_format($heatMapCount) }}</div><div class="mao-note">{{ number_format($highRiskHeatMapAreas) }} high or severe areas</div><div class="mao-tap-hint">View details &rarr;</div></div></button>
            <a href="{{ route('farmer-profiles.index') }}" class="mao-card text-decoration-none"><div class="mao-card-body"><div class="mao-label">Farmer Organizations</div><div class="mao-value">{{ number_format($farmerOrganizationSummary['totalOrganizations']) }}</div><div class="mao-note">{{ number_format($farmerOrganizationSummary['totalFarmers']) }} farmers registered</div><div class="mao-tap-hint">View breakdown &rarr;</div></div></a>
        </section>

        <div class="ic-drawer" id="maoStatFarmers" aria-hidden="true">
            <div class="ic-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="maoStatFarmersLabel">
                <div class="modal-content border-0 mao-modal-content">
                    <div class="modal-header mao-modal-header">
                        <h2 class="modal-title h5 fw-bold" id="maoStatFarmersLabel">Farmer Profiles</h2>
                        <button type="button" class="btn-close" data-drawer-close aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mao-modal-headline">{{ number_format($profileCount) }} registered</div>
                        <p class="mao-modal-sub">Most recently registered farmer profiles.</p>
                        @forelse($recentFarmerProfiles as $profile)
                            <div class="mao-list-item">
                                <div class="list-mark"><svg width="17" height="17" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="6.5" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M3.5 18c.6-4.8 3-7.5 6.5-7.5s5.9 2.7 6.5 7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></div>
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

        <div class="ic-drawer" id="maoStatClimate" aria-hidden="true">
            <div class="ic-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="maoStatClimateLabel">
                <div class="modal-content border-0 mao-modal-content">
                    <div class="modal-header mao-modal-header">
                        <h2 class="modal-title h5 fw-bold" id="maoStatClimateLabel">Climate Records</h2>
                        <button type="button" class="btn-close" data-drawer-close aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mao-modal-headline">{{ number_format($totalClimateRecords) }} recorded</div>
                        <p class="mao-modal-sub">Latest entry: {{ $latestClimate?->record_date?->format('F d, Y') ?? 'N/A' }} &middot; Source: {{ $latestClimate?->source ?? 'N/A' }}</p>
                        @forelse($recentClimateRecords as $record)
                            <div class="mao-list-item">
                                <div class="list-mark"><svg width="17" height="17" viewBox="0 0 20 20" fill="none"><path d="M5.5 10.5a3 3 0 0 1 .6-5.9 4 4 0 0 1 7.7.7A2.7 2.7 0 0 1 13.5 10.5h-8Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg></div>
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

        <div class="ic-drawer" id="maoStatRice" aria-hidden="true">
            <div class="ic-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="maoStatRiceLabel">
                <div class="modal-content border-0 mao-modal-content">
                    <div class="modal-header mao-modal-header">
                        <h2 class="modal-title h5 fw-bold" id="maoStatRiceLabel">Rice Production</h2>
                        <button type="button" class="btn-close" data-drawer-close aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mao-modal-headline">{{ number_format($riceProductionTotal, 1) }} tons total production</div>
                        <p class="mao-modal-sub">{{ number_format($riceAreaTotal, 1) }} hectares recorded &middot; Average yield {{ number_format($avgYield, 2) }} per hectare.</p>
                        @forelse($recentRiceProductions as $production)
                            <div class="mao-list-item">
                                <div class="list-mark"><svg width="17" height="17" viewBox="0 0 20 20" fill="none"><rect x="2" y="12" width="4" height="6" rx="1" fill="currentColor"/><rect x="8" y="7.5" width="4" height="10.5" rx="1" fill="currentColor" opacity="0.75"/><rect x="14" y="3" width="4" height="15" rx="1" fill="currentColor" opacity="0.5"/></svg></div>
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

        <div class="ic-drawer" id="maoStatRisk" aria-hidden="true">
            <div class="ic-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="maoStatRiskLabel">
                <div class="modal-content border-0 mao-modal-content">
                    <div class="modal-header mao-modal-header">
                        <h2 class="modal-title h5 fw-bold" id="maoStatRiskLabel">Heat Map Risks</h2>
                        <button type="button" class="btn-close" data-drawer-close aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mao-modal-headline">{{ number_format($highRiskHeatMapAreas) }} high or severe</div>
                        <p class="mao-modal-sub">Out of {{ number_format($heatMapCount) }} mapped barangay records. Risk score runs from 0.00 (lowest risk) to 1.00 (highest risk).</p>
                        @forelse($highRiskAreasList as $area)
                            @php
                                $riskScoreValue = (float) $area->risk_score;
                                $riskLevelLabel = $area->risk_level ?: 'Moderate';
                                $riskInterpretation = match (strtolower($riskLevelLabel)) {
                                    'severe' => 'Severe risk: conditions strongly threaten this planting season. Act on the advisory right away.',
                                    'high' => 'High risk: expect significant stress on crops. Follow the advisory closely before planting.',
                                    'moderate' => 'Moderate risk: some caution is needed. Monitor conditions before proceeding.',
                                    default => 'Low risk: conditions are currently favorable for this barangay.',
                                };
                                $riskBadgeClass = match (strtolower($riskLevelLabel)) {
                                    'severe' => 'mao-risk-badge-severe',
                                    'high' => 'mao-risk-badge-high',
                                    'moderate' => 'mao-risk-badge-moderate',
                                    default => 'mao-risk-badge-low',
                                };
                            @endphp
                            <div class="mao-list-item">
                                <div class="list-mark"><svg width="17" height="17" viewBox="0 0 20 20" fill="none"><path d="M10 2.5 18 16.5H2L10 2.5Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M10 8v3.6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="10" cy="14" r=".9" fill="currentColor"/></svg></div>
                                <div class="flex-grow-1">
                                    <div class="row-title">
                                        {{ $area->barangay }} &middot; {{ $area->risk_type }}
                                        <span class="mao-risk-badge {{ $riskBadgeClass }}">{{ $riskLevelLabel }} risk</span>
                                    </div>
                                    <div class="row-text mao-risk-interpretation">{{ $riskInterpretation }}</div>
                                    <div class="row-text">{{ $area->planting_advisory ?: 'Review latest climate and rice production data before planting.' }}</div>
                                    <div class="row-meta">Risk score {{ number_format($riskScoreValue, 2) }} / 1.00</div>
                                    @if ($area->risk_breakdown)
                                        <details class="ic-collapse mt-2">
                                            <summary><span class="ic-collapse-title">How is this score calculated?</span></summary>
                                            <ul class="mb-2 ps-3">
                                                @foreach ($area->risk_breakdown['factors'] as $factor)
                                                    <li class="row-text">
                                                        <strong>{{ $factor['label'] }}{{ $factor['weight_percent'] !== null ? ' ('.$factor['weight_percent'].'% weight)' : '' }}:</strong>
                                                        {{ $factor['risk_value'] !== null ? number_format($factor['risk_value'], 2) : '+'.number_format($factor['contribution'], 2) }}
                                                        &mdash; {{ $factor['detail'] }}
                                                    </li>
                                                @endforeach
                                            </ul>
                                            <div class="row-meta">{{ $area->risk_breakdown['caveat'] }}</div>
                                        </details>
                                    @endif
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

        <details class="dashboard-group" open id="farmer-schedule-monitor">
            <summary>
                <div>
                    <h2 class="dashboard-group-title">Farmer Planting and Harvest Calendar</h2>
                    <p class="dashboard-group-note">Monitor farmer schedules, yield predictions, history comparisons, and follow-up needs.</p>
                </div>
                <span class="dashboard-group-toggle"></span>
            </summary>
            <div class="dashboard-group-body">
                @unless($plantingScheduleFeatureReady ?? true)
                    <div class="alert alert-warning mb-4" role="status"><strong>Farm calendar is temporarily unavailable.</strong> The dashboard remains available while the database update is completed.</div>
                @endunless
                <form class="row g-2 align-items-end mb-4" method="GET" action="{{ route('mao.dashboard') }}#farmer-schedule-monitor">
                    <div class="col-6 col-lg-2"><label class="form-label fw-bold" for="schedule_month">Month</label><select class="form-select" id="schedule_month" name="schedule_month"><option value="">All</option>@foreach(range(1,12) as $month)<option value="{{ $month }}" @selected(($scheduleFilters['month'] ?? 0) === $month)>{{ \Illuminate\Support\Carbon::create(2000,$month,1)->format('F') }}</option>@endforeach</select></div>
                    <div class="col-6 col-lg-2"><label class="form-label fw-bold" for="schedule_season">Season</label><select class="form-select" id="schedule_season" name="schedule_season"><option value="">All</option>@foreach(\App\Models\FarmerPlantingSchedule::SEASONS as $season)<option value="{{ $season }}" @selected(($scheduleFilters['season'] ?? '') === $season)>{{ $season }}</option>@endforeach</select></div>
                    <div class="col-6 col-lg-2"><label class="form-label fw-bold" for="schedule_barangay">Barangay</label><select class="form-select" id="schedule_barangay" name="schedule_barangay"><option value="">All</option>@foreach($scheduleBarangays as $barangay)<option value="{{ $barangay }}" @selected(($scheduleFilters['barangay'] ?? '') === $barangay)>{{ $barangay }}</option>@endforeach</select></div>
                    <div class="col-6 col-lg-2"><label class="form-label fw-bold" for="schedule_farm_type">Farm type</label><select class="form-select" id="schedule_farm_type" name="schedule_farm_type"><option value="">All</option>@foreach(\App\Models\FarmerPlantingSchedule::FARM_TYPES as $farmType)<option value="{{ $farmType }}" @selected(($scheduleFilters['farm_type'] ?? '') === $farmType)>{{ $farmType }}</option>@endforeach</select></div>
                    <div class="col-6 col-lg-2"><label class="form-label fw-bold" for="schedule_farmer">Farmer</label><select class="form-select" id="schedule_farmer" name="schedule_farmer"><option value="">All</option>@foreach($scheduleFarmers as $farmer)<option value="{{ $farmer->id }}" @selected(($scheduleFilters['farmer'] ?? 0) === $farmer->id)>{{ $farmer->name }}</option>@endforeach</select></div>
                    <div class="col-6 col-lg-2"><label class="form-label fw-bold" for="schedule_status">Status</label><select class="form-select" id="schedule_status" name="schedule_status"><option value="">All</option>@foreach(['upcoming_planting','planted','upcoming_harvest','harvested','delayed','needs_review'] as $status)<option value="{{ $status }}" @selected(($scheduleFilters['status'] ?? '') === $status)>{{ str($status)->replace('_',' ')->title() }}</option>@endforeach</select></div>
                    <div class="col-12 d-flex flex-wrap gap-2 mt-3"><button class="btn btn-primary" type="submit">Apply filters</button><a class="btn btn-outline-secondary" href="{{ route('mao.dashboard') }}#farmer-schedule-monitor">Clear</a><a class="btn btn-outline-primary ms-lg-auto" href="{{ route('calendar.index') }}">Open full calendar</a></div>
                </form>

                <div class="schedule-monitor-grid">
                    @forelse($farmerSchedules as $schedule)
                        @php
                            $farmerName = $schedule->farmer?->farmerProfile?->full_name ?: $schedule->farmer?->name ?: 'Unknown farmer';
                            $confidenceNeedsReview = $schedule->prediction_confidence === null || (float)$schedule->prediction_confidence < 70;
                        @endphp
                        <article class="schedule-monitor-card">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div><h3>{{ $farmerName }}</h3><div class="schedule-monitor-meta">{{ $schedule->barangay ?: 'Location not available' }} &middot; {{ $schedule->season }} &middot; {{ $schedule->farm_type }}</div></div>
                                <span class="badge {{ in_array($schedule->monitoringStatus(), ['delayed','needs_review']) ? 'text-bg-warning' : 'text-bg-success' }}">{{ $schedule->monitoringStatusLabel() }}</span>
                            </div>
                            <div class="schedule-monitor-meta mt-2"><strong>Planting:</strong> {{ $schedule->planting_date?->format('M d, Y') }}<br><strong>Scheduled harvest:</strong> {{ ($schedule->scheduled_harvest_date ?? $schedule->harvest_month)?->format($schedule->scheduled_harvest_date ? 'M d, Y' : 'F Y') }}</div>

                            <div class="schedule-yield-grid">
                                <div class="schedule-yield-box"><span>Current predicted yield</span><strong>{{ $schedule->predicted_yield !== null ? number_format((float)$schedule->predicted_yield, 2).' t/ha' : 'Prediction unavailable' }}</strong></div>
                                <div class="schedule-yield-box"><span>Previous same-month average</span><strong>{{ $schedule->historical_same_month_average !== null ? number_format((float)$schedule->historical_same_month_average, 2).' t/ha' : 'No history' }}</strong></div>
                                <div class="schedule-yield-box"><span>Difference</span><strong>{{ $schedule->yield_difference !== null ? (($schedule->yield_difference > 0 ? '+' : '').number_format((float)$schedule->yield_difference, 2).' t/ha') : 'Not available' }}</strong></div>
                            </div>

                            @if($schedule->predicted_yield === null)
                                <div class="schedule-guidance"><strong>Prediction unavailable</strong><br>{{ $schedule->prediction_unavailable_reason ?: 'Required prediction data is incomplete.' }}<br><span class="text-muted">This result needs MAO review before using it for decisions.</span></div>
                            @elseif($schedule->historical_same_month_average === null)
                                <div class="schedule-guidance"><strong>No previous yield history available for comparison.</strong><br>No claim is made that this prediction is better or worse. Continue observing weather and verify the field condition.@if($confidenceNeedsReview)<br><span class="text-muted">Prediction confidence is low or unavailable; MAO review is required.</span>@endif</div>
                            @elseif($schedule->comparison_interpretation === 'lower')
                                <div class="schedule-guidance urgent"><strong>Recommended Actions</strong><br>{{ $schedule->recommended_actions }}@if($confidenceNeedsReview)<br><span class="text-muted">Prediction confidence is low or required data is incomplete. Review before making decisions.</span>@endif</div>
                            @else
                                <div class="schedule-guidance"><strong>Warnings / Notes</strong><br>Yield is {{ $schedule->comparison_interpretation ?: 'stable' }} compared with the previous same-month average. Continue observing weather changes, verify field condition, and keep the farmer advisory updated.@if($confidenceNeedsReview)<br><span class="text-muted">Prediction confidence is low or unavailable; MAO review is required.</span>@endif</div>
                            @endif
                        </article>
                    @empty
                        <div class="empty-state text-center p-4" style="grid-column:1/-1"><strong>No farmer schedules match these filters.</strong><p class="text-muted mb-0 mt-1">Saved farmer schedules will appear here.</p></div>
                    @endforelse
                </div>
            </div>
        </details>

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
        <section class="mao-panel" data-mao-weather-root data-mao-weather-url="{{ route('mao.dashboard.weather') }}">
            <div class="mao-panel-header">
                <div>
                    <h2 class="mao-panel-title">Barangay Heat Map Dashboard</h2>
                    <p class="mao-panel-sub">Primary view for rainfall, yield, irrigation, and climate-impact risk by barangay.</p>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="source-badge" data-mao-weather-source>{{ $weatherDataSource }}</span>
                    <a class="btn btn-sm btn-primary" href="{{ route('heatmap-areas.index') }}">Open Heat Map</a>
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('weather-predictions.index') }}">Open Prediction</a>
                </div>
            </div>
            <div class="mao-panel-body">
                <div class="variable-info-grid">
                    <div class="variable-info-card">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="row-title mb-0">Rainfall &amp; Temperature</div>
                            <span class="status-pill">Used in yield model</span>
                        </div>
                        <p class="row-text mb-0">Water and heat directly drive how rice grows. The trained yield model uses rainfall and temperature &mdash; including recent, 3-month, 6-month, and seasonal averages &mdash; together with farm area and season as its main predictors.</p>
                    </div>
                    <div class="variable-info-card">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="row-title mb-0">Humidity &amp; Wind Speed</div>
                            <span class="status-pill muted">Shown live, not yet in yield model</span>
                        </div>
                        <p class="row-text mb-0">Tracked live below for weather forecasting and risk warnings (e.g. high wind before harvest, high humidity disease risk). Not yet part of the trained yield model &mdash; validating whether they should be added requires more historical climate and production records than are currently on file.</p>
                    </div>
                </div>
                @if($liveWeather)
                    <div class="live-weather-grid">
                        <div class="live-weather-card">
                            <div class="mao-label">Current Rain</div>
                            <div class="risk-level-value" data-mao-current-rain>{{ number_format(data_get($liveWeather, 'current_rainfall_mm', 0), 1) }}</div>
                            <div class="mao-note">mm right now</div>
                        </div>
                        <div class="live-weather-card">
                            <div class="mao-label">Temperature</div>
                            <div class="risk-level-value" data-mao-current-temperature>{{ number_format(data_get($liveWeather, 'current_temperature_c', 0), 1) }}</div>
                            <div class="mao-note">&deg;C current reading</div>
                        </div>
                        <div class="live-weather-card">
                            <div class="mao-label">Humidity</div>
                            <div class="risk-level-value" data-mao-current-humidity>{{ number_format(data_get($liveWeather, 'humidity_percent', 0), 1) }}</div>
                            <div class="mao-note">% current reading</div>
                        </div>
                        <div class="live-weather-card">
                            <div class="mao-label">Wind Speed</div>
                            <div class="risk-level-value" data-mao-current-wind>{{ number_format(data_get($liveWeather, 'wind_speed_kmh', 0), 1) }}</div>
                            <div class="mao-note">km/h current reading</div>
                        </div>
                    </div>
                @endif

                <div class="row g-4 mb-4">
                    <div class="col-xl-8">
                        <div class="chart-title">Municipal Heat Map</div>
                        <div class="chart-note">Shows which barangays need attention. Click a barangay to see the reason and recommended action.</div>
                        <div class="dashboard-map-legend" role="note" aria-label="Heat map color legend">
                            <span class="legend-swatch"><span class="legend-dot" style="background:#22c55e"></span> Green = Low</span>
                            <span class="legend-swatch"><span class="legend-dot" style="background:#facc15"></span> Yellow = Moderate</span>
                            <span class="legend-swatch"><span class="legend-dot" style="background:#f97316"></span> Orange = High</span>
                            <span class="legend-swatch"><span class="legend-dot" style="background:#ef4444"></span> Red = Severe</span>
                        </div>
                        @if($dashboardMapAreas->count())
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
                        @else
                            <div class="empty-soft"><strong>No mapped barangays yet</strong><div class="small text-muted mt-1">Heat map layers and controls will appear here once barangay locations are recorded.</div></div>
                        @endif
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
                                @php
                                    $cardLevel = data_get($area, 'risk_level', 'Moderate');
                                    $cardReason = match (strtolower((string) data_get($area, 'risk_type'))) {
                                        'flood' => 'High rainfall / flood risk',
                                        'drought' => 'Low rainfall / drought risk',
                                        'typhoon' => 'Storm or strong wind exposure',
                                        'heat' => 'High temperature stress',
                                        default => data_get($area, 'risk_type') ?: 'Climate exposure',
                                    };
                                    $cardAction = data_get($area, 'irrigation_recommendation') ?: data_get($area, 'planting_advisory') ?: 'Visit or contact farmers here before major field work.';
                                    $cardUpdated = data_get($area, 'updated_at');
                                @endphp
                                <div class="risk-card {{ strtolower($cardLevel) }}">
                                    <div class="d-flex justify-content-between gap-2 mb-2">
                                        <strong>{{ data_get($area, 'barangay', 'Unspecified barangay') }}</strong>
                                        <span class="status-pill risk-badge-lg {{ strtolower($cardLevel) }}">{{ $cardLevel }}</span>
                                    </div>
                                    <div class="row-text">Score {{ number_format(data_get($area, 'risk_score', 0), 2) }}</div>
                                    <div class="risk-card-meta">
                                        <div><strong>Main reason:</strong> {{ $cardReason }}</div>
                                        <div><strong>Suggested action:</strong> {{ $cardAction }}</div>
                                        <div><strong>Last updated:</strong> {{ $cardUpdated ? $cardUpdated->shortDateTime('M d, Y') : 'Not yet refreshed' }}</div>
                                    </div>
                                </div>
                            @empty
                                <div class="empty-soft"><strong>No mapped barangays yet</strong><div class="small text-muted mt-1">Heat map risk records will appear here.</div></div>
                            @endforelse
                        </div>
                    </div>
                </div>

                    <div class="mb-4" data-chart-tabs>
                        <div class="ic-chart-tabs">
                            <button type="button" class="ic-chart-tab active" data-chart-tab="rainfall">Rainfall</button>
                            <button type="button" class="ic-chart-tab" data-chart-tab="temperature">Temperature</button>
                            <button type="button" class="ic-chart-tab" data-chart-tab="humidity">Humidity</button>
                            <button type="button" class="ic-chart-tab" data-chart-tab="wind">Wind Speed</button>
                        </div>
                        <div class="visual-grid">
                            <div class="chart-box" data-chart-pane="rainfall">
                                <div class="chart-title">Rainfall Chart</div>
                                <div class="chart-note">{{ $liveWeather ? 'Live forecast rainfall in millimeters.' : 'Recent recorded rainfall in millimeters.' }}</div>
                                <div class="chart-canvas-scroll">
                                    <div class="chart-render-state" data-chart-state="rainfall" role="status">Loading real weather data...</div>
                                    <canvas id="rainfallChart" aria-busy="true"></canvas>
                                </div>
                                @include('dashboards.partials.chart-interpretation', ['key' => 'rainfall'])
                            </div>
                            <div class="chart-box" data-chart-pane="temperature">
                                <div class="chart-title">Temperature Chart</div>
                                <div class="chart-note">{{ $liveWeather ? 'Live forecast average temperature.' : 'Recent recorded average temperature.' }}</div>
                                <div class="chart-canvas-scroll">
                                    <div class="chart-render-state" data-chart-state="temperature" role="status">Loading real weather data...</div>
                                    <canvas id="temperatureChart" aria-busy="true"></canvas>
                                </div>
                                @include('dashboards.partials.chart-interpretation', ['key' => 'temperature'])
                            </div>
                            <div class="chart-box" data-chart-pane="humidity">
                                <div class="chart-title">Humidity Chart</div>
                                <div class="chart-note">{{ $liveWeather ? 'Live forecast humidity percentage.' : 'Recent recorded humidity percentage.' }}</div>
                                <div class="chart-canvas-scroll">
                                    <div class="chart-render-state" data-chart-state="humidity" role="status">Loading real weather data...</div>
                                    <canvas id="humidityChart" aria-busy="true"></canvas>
                                </div>
                                @include('dashboards.partials.chart-interpretation', ['key' => 'humidity'])
                            </div>
                            <div class="chart-box" data-chart-pane="wind">
                                <div class="chart-title">Wind Speed Chart</div>
                                <div class="chart-note">{{ $liveWeather ? 'Live forecast wind speed.' : 'Recent recorded wind speed.' }}</div>
                                <div class="chart-canvas-scroll">
                                    <div class="chart-render-state" data-chart-state="windSpeed" role="status">Loading real weather data...</div>
                                    <canvas id="windSpeedChart" aria-busy="true"></canvas>
                                </div>
                                @include('dashboards.partials.chart-interpretation', ['key' => 'windSpeed'])
                            </div>
                        </div>
                    </div>

            </div>
        </section>
            </div>
        </details>

        <details class="dashboard-group" open>
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
                                <div class="list-mark"><svg width="17" height="17" viewBox="0 0 20 20" fill="none"><rect x="2" y="12" width="4" height="6" rx="1" fill="currentColor"/><rect x="8" y="7.5" width="4" height="10.5" rx="1" fill="currentColor" opacity="0.75"/><rect x="14" y="3" width="4" height="15" rx="1" fill="currentColor" opacity="0.5"/></svg></div>
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
                    <div class="mao-panel-header"><div><h2 class="mao-panel-title">Barangay Heat Map Overview</h2><p class="mao-panel-sub">Each barangay's current risk level, at a glance, with the recommended irrigation action.</p></div><a class="btn btn-sm btn-outline-primary" href="{{ route('heatmap-areas.index') }}">Open Map</a></div>
                    <div class="mao-panel-body list-compact"><div class="heat-grid">
                        @forelse($latestHeatmapAreas as $area)
                            @php
                                $areaRiskLevel = data_get($area, 'risk_level', 'Moderate') ?: 'Moderate';
                            @endphp
                            <div class="risk-card {{ strtolower($areaRiskLevel) }}"><div class="d-flex justify-content-between gap-2 mb-2"><strong>{{ data_get($area, 'barangay', 'Unspecified barangay') }}</strong><span class="status-pill {{ in_array($areaRiskLevel, ['High','Severe']) ? 'muted' : '' }}">{{ $areaRiskLevel }}</span></div><div class="row-text">{{ data_get($area, 'risk_type', 'Climate') }} risk | {{ data_get($area, 'predicted_yield') !== null ? number_format(data_get($area, 'predicted_yield'), 2).' t/ha' : 'No yield prediction' }}</div><div class="row-meta">{{ str(data_get($area, 'irrigation_recommendation') ?: data_get($area, 'description', 'No recommendation yet'))->limit(70) }}</div></div>
                        @empty
                            <div class="empty-soft"><strong>No risk records</strong><div class="small text-muted mt-1">Barangay heat map records will appear here.</div></div>
                        @endforelse
                    </div></div>
                </div>
            </div>
        </div>
            </div>
        </details>

        <details class="dashboard-group" open>
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
                            <div class="mao-list-item"><div class="list-mark"><svg width="17" height="17" viewBox="0 0 20 20" fill="none"><path d="M3.5 2.5h8l3 3v10h-11v-13z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M6 9h6M6 12h4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg></div><div><div class="row-title">{{ $advisory->title }}</div><div class="row-text">{{ str($advisory->content)->limit(95) }}</div><div class="row-meta">{{ $advisory->type }} | {{ $advisory->status }} | {{ $advisory->target_barangay ?: 'All barangays' }}</div></div></div>
                            <div class="mao-list-item"><div class="list-mark"><svg width="17" height="17" viewBox="0 0 20 20" fill="none"><path d="M3.5 2.5h8l3 3v10h-11v-13z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M6 9h6M6 12h4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg></div><div><div class="row-title">{{ $advisory->title }}</div><div class="row-text">{{ str($advisory->displaySummary())->limit(95) }}</div><div class="row-meta">{{ $advisory->type }} | {{ $advisory->status }} | {{ $advisory->target_barangay ?: 'All barangays' }}</div></div></div>
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

        <details class="dashboard-group" open>
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
                                <div class="list-mark"><svg width="17" height="17" viewBox="0 0 20 20" fill="none"><path d="M5.5 10.5a3 3 0 0 1 .6-5.9 4 4 0 0 1 7.7.7A2.7 2.7 0 0 1 13.5 10.5h-8Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg></div>
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

        <details class="dashboard-group" open>
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
                    <div class="mao-list-item"><div class="list-mark"><svg width="17" height="17" viewBox="0 0 20 20" fill="none"><path d="M4 3h10l4 4v12H4V3z" stroke="currentColor" stroke-width="1.4"/><path d="M14 3v4h4" stroke="currentColor" stroke-width="1.4"/><path d="M7 10h8M7 13h6M7 16h7" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg></div><div class="flex-grow-1"><div class="row-title">{{ $report->title }}</div><div class="row-text">{{ $report->report_type }} generated by {{ $report->generatedBy?->name ?? 'System' }}.</div><div class="row-meta">{{ $report->created_at?->format('M d, Y') }}</div></div><a class="btn btn-sm btn-outline-primary" href="{{ route('reports.show', $report) }}">Open</a></div>
                @empty
                    <div class="empty-soft"><strong>No report history yet</strong><div class="small text-muted mt-1">Generated reports will appear here.</div></div>
                @endforelse
            </div>
        </div>
            </div>
        </details>
        </div>
    </div>
    <script type="application/json" id="mao-weather-chart-data">{!! json_encode($weatherChartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
    <script type="application/json" id="mao-map-areas">{!! json_encode($dashboardMapAreas, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
    <script>
        const initializeMaoDashboard = async () => {
            await (window.iClimateVisualsReady || Promise.resolve());
            document.querySelectorAll('[data-toggle-detail]').forEach((card) => {
                card.addEventListener('click', () => {
                    const panel = document.getElementById(card.dataset.toggleDetail);
                    if (!panel) return;
                    const isOpen = !panel.hidden;

                    document.querySelectorAll('.mao-stat-panel').forEach((other) => { other.hidden = true; });
                    document.querySelectorAll('[data-toggle-detail]').forEach((other) => other.setAttribute('aria-expanded', 'false'));

                    if (!isOpen) {
                        panel.hidden = false;
                        card.setAttribute('aria-expanded', 'true');
                        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                });
            });
            document.querySelectorAll('.mao-panel-close').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const panel = btn.closest('.mao-stat-panel');
                    if (panel) panel.hidden = true;
                    document.querySelectorAll('[data-toggle-detail]').forEach((other) => other.setAttribute('aria-expanded', 'false'));
                });
            });

            const MAO_WEATHER_POLL_INTERVAL = 60000;
            const parseMaoJson = (id, fallback) => {
                const source = document.getElementById(id)?.textContent;
                if (!source) return fallback;

                try {
                    return JSON.parse(source);
                } catch (error) {
                    return fallback;
                }
            };
            const maoWeatherChartData = parseMaoJson('mao-weather-chart-data', {
                labels: [],
                rainfall: [],
                temperature: [],
                humidity: [],
                windSpeed: [],
            });
            const maoMapAreas = parseMaoJson('mao-map-areas', []);
            const maoValidMapAreas = maoMapAreas.filter((area) => Number.isFinite(Number(area.latitude)) && Number.isFinite(Number(area.longitude)));
            const labels = maoWeatherChartData.labels || [];
            const chartLabels = window.iClimateChartLabels || {};
            const readableLabels = labels.map((label) => chartLabels.wrap ? chartLabels.wrap(label, 12, 2) : label);
            const weatherRoot = document.querySelector('[data-mao-weather-root]');
            let maoWeatherPollTimer = null;

            const chartOptions = (unit, labelCount) => ({
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                layout: { padding: { top: 8, right: 14, bottom: 10, left: 4 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => `${context.dataset.label}: ${context.parsed.y} ${unit}`,
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: chartLabels.horizontalCategoryTicks
                            ? chartLabels.horizontalCategoryTicks({ color: '#334155', autoSkip: labelCount > 8, maxTicksLimit: 8, font: { size: 14, weight: 600, lineHeight: 1.25 } })
                            : { maxRotation: 0, minRotation: 0, autoSkip: labelCount > 8, maxTicksLimit: 8, color: '#334155', font: { size: 14, weight: 600 } },
                    },
                    y: { beginAtZero: true, grid: { color: 'rgba(13,31,24,.08)' }, ticks: { color: '#475569', font: { size: 14, weight: 600 }, padding: 8 } },
                },
            });

            const setWeatherChartState = (key, message = '', ready = false) => {
                const state = document.querySelector(`[data-chart-state="${key}"]`);
                if (state) {
                    state.textContent = message;
                    state.hidden = ready;
                }
            };
            const makeChart = (key, id, label, seriesLabels, values, color, unit) => {
                const canvas = document.getElementById(id);
                if (!canvas) return null;

                const normalizedLabels = Array.isArray(seriesLabels) ? seriesLabels : [];
                const normalizedValues = Array.isArray(values)
                    ? values.slice(0, normalizedLabels.length).map((value) => Number(value))
                    : [];

                if (!window.Chart) {
                    canvas.hidden = true;
                    setWeatherChartState(key, 'This chart could not load. Refresh the page to try again.');
                    return null;
                }

                if (!normalizedLabels.length || !normalizedValues.some(Number.isFinite)) {
                    canvas.hidden = true;
                    setWeatherChartState(key, 'No weather records found for this chart.');
                    return null;
                }

                try {
                    canvas.hidden = false;
                    const chart = new window.Chart(canvas, {
                        type: 'line',
                        data: {
                            labels: normalizedLabels.map((item) => chartLabels.wrap ? chartLabels.wrap(item, 12, 2) : item),
                            datasets: [{
                                label,
                                data: normalizedValues,
                                borderColor: color,
                                backgroundColor: `${color}22`,
                                fill: true,
                                borderWidth: 3,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                tension: .36,
                            }],
                        },
                        options: chartOptions(unit, normalizedLabels.length),
                    });
                    canvas.removeAttribute('aria-busy');
                    setWeatherChartState(key, '', true);

                    return chart;
                } catch (error) {
                    canvas.hidden = true;
                    setWeatherChartState(key, 'This chart could not be drawn. The weather values remain available above.');
                    console.error(`iClimate ${label} chart failed to render.`, error);

                    return null;
                }
            };

            const weatherChartDefinitions = {
                rainfall: { id: 'rainfallChart', label: 'Rainfall', color: '#1677b8', unit: 'mm' },
                temperature: { id: 'temperatureChart', label: 'Temperature', color: '#d85b45', unit: 'C' },
                humidity: { id: 'humidityChart', label: 'Humidity', color: '#52b788', unit: '%' },
                windSpeed: { id: 'windSpeedChart', label: 'Wind Speed', color: '#f4b63f', unit: 'km/h' },
            };

            // --- Plain-language chart interpretation -----------------------------------
            // Reads only the values/labels the chart is currently showing. Never invents numbers.
            const friendlyDate = (label) => {
                if (!label) return 'the latest date';
                if (/^\d{4}-\d{2}-\d{2}/.test(String(label))) {
                    const parsed = new Date(`${String(label).slice(0, 10)}T00:00:00`);
                    if (!Number.isNaN(parsed.getTime())) {
                        return parsed.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                    }
                }
                return String(label);
            };
            const pairSeries = (seriesLabels, values) => (Array.isArray(seriesLabels) ? seriesLabels : [])
                .map((label, index) => ({ label, value: Number(values?.[index]) }))
                .filter((point) => Number.isFinite(point.value));
            const peakOf = (points) => points.reduce((best, point) => (!best || point.value > best.value ? point : best), null);
            const fmtValue = (value) => {
                const rounded = Math.round(value * 10) / 10;
                return Number.isInteger(rounded) ? String(rounded) : rounded.toFixed(1);
            };
            const STATUS_BADGE_CLASS = { safe: 'low', monitor: 'moderate', warning: 'high', critical: 'severe' };
            const STATUS_BADGE_TEXT = { safe: 'Safe', monitor: 'Monitor', warning: 'Warning', critical: 'Critical' };

            const interpretRainfall = (points) => {
                const peak = peakOf(points);
                const date = friendlyDate(peak.label);
                const value = fmtValue(peak.value);
                let category, status, action;
                if (peak.value < 7.5) {
                    category = 'light'; status = 'safe';
                    action = 'No major flood risk expected. Continue normal field activities.';
                } else if (peak.value < 15) {
                    category = 'moderate'; status = 'monitor';
                    action = 'Watch upcoming weather updates and prepare drainage in low-lying farms.';
                } else if (peak.value <= 40) {
                    category = 'heavy'; status = 'warning';
                    action = 'Monitor flood-prone barangays and advise farmers to delay fertilizer or pesticide application.';
                } else {
                    category = 'very heavy'; status = 'critical';
                    action = 'Alert flood-prone barangays immediately and advise farmers to postpone all field work until rain subsides.';
                }

                return { text: `Rainfall is highest on ${date} at ${value} mm, which means ${category} rain is expected.`, action, status };
            };

            const interpretTemperature = (points) => {
                const latest = points[points.length - 1];
                const first = points[0];
                const diff = latest.value - first.value;
                const trend = diff >= 1.5 ? 'rising' : diff <= -1.5 ? 'falling' : 'stable';
                const tooHot = latest.value >= 35;
                const extreme = latest.value >= 38;
                const status = extreme ? 'critical' : tooHot ? 'warning' : (trend === 'rising' ? 'monitor' : 'safe');
                const condition = tooHot ? 'too hot' : 'normal';
                let action;
                if (extreme) action = 'Issue a heat advisory. Advise farmers to increase irrigation frequency and avoid midday field work.';
                else if (tooHot) action = 'Advise farmers to irrigate early morning or late afternoon and watch for heat stress on crops.';
                else if (trend === 'rising') action = 'Temperature is climbing. Keep water levels sufficient for crops in the coming days.';
                else if (trend === 'falling') action = 'Temperature is dropping. Watch young seedlings for possible cold stress.';
                else action = 'No action needed. Continue regular farm activities.';

                return { text: `Temperature is ${trend} and is currently ${fmtValue(latest.value)}°C, which is ${condition} for this time of year.`, action, status };
            };

            const interpretHumidity = (points) => {
                const latest = points[points.length - 1];
                const veryHigh = latest.value >= 90;
                const high = latest.value >= 80;
                const status = veryHigh ? 'critical' : high ? 'warning' : 'safe';
                const condition = high ? 'high' : 'normal';
                let action;
                if (veryHigh) action = 'Very high humidity greatly raises fungal and blast disease risk. Apply preventive fungicide and inspect crops closely.';
                else if (high) action = 'High humidity increases the risk of fungal diseases and pests. Monitor crops closely and improve field ventilation or drainage.';
                else action = 'Humidity is within a normal range. Continue routine crop monitoring.';

                return { text: `Humidity is currently ${fmtValue(latest.value)}%, which is ${condition}${high ? ' and raises disease risk' : ''}.`, action, status };
            };

            const interpretWindSpeed = (points) => {
                const peak = peakOf(points);
                const date = friendlyDate(peak.label);
                const value = fmtValue(peak.value);
                let category, status, action;
                if (peak.value < 20) {
                    category = 'calm'; status = 'safe';
                    action = 'Safe for spraying, harvesting, and general field work.';
                } else if (peak.value <= 40) {
                    category = 'moderate'; status = 'monitor';
                    action = 'Field work is generally safe, but avoid spraying pesticides during peak wind hours.';
                } else if (peak.value <= 60) {
                    category = 'strong'; status = 'warning';
                    action = 'Postpone pesticide spraying and harvesting. Secure lightweight farm structures and equipment.';
                } else {
                    category = 'very strong'; status = 'critical';
                    action = 'Suspend all outdoor field work. Advise farmers to secure structures and prepare for possible storm damage.';
                }

                return { text: `Wind speed reaches ${value} km/h on ${date}, which is ${category} wind.`, action, status };
            };

            const INTERPRETERS = { rainfall: interpretRainfall, temperature: interpretTemperature, humidity: interpretHumidity, windSpeed: interpretWindSpeed };

            const updateInterpretation = (key, seriesLabels, values) => {
                const box = document.querySelector(`[data-interpretation="${key}"]`);
                if (!box) return;

                const textEl = box.querySelector('[data-role="text"]');
                const actionEl = box.querySelector('[data-role="action"]');
                const badgeEl = box.querySelector('[data-role="badge"]');
                const points = pairSeries(seriesLabels, values);

                if (points.length < 1 || !INTERPRETERS[key]) {
                    if (textEl) textEl.textContent = 'Not enough weather data yet to explain this chart.';
                    if (actionEl) actionEl.textContent = 'Check back once more readings are recorded.';
                    if (badgeEl) { badgeEl.textContent = 'No data'; badgeEl.className = 'status-pill risk-badge-lg'; }
                    return;
                }

                const result = INTERPRETERS[key](points);
                if (textEl) textEl.textContent = result.text;
                if (actionEl) actionEl.textContent = result.action;
                if (badgeEl) {
                    badgeEl.textContent = STATUS_BADGE_TEXT[result.status] || result.status;
                    badgeEl.className = `status-pill risk-badge-lg ${STATUS_BADGE_CLASS[result.status] || ''}`;
                }
            };
            // -----------------------------------------------------------------------------

            const weatherCharts = {};
            Object.entries(weatherChartDefinitions).forEach(([key, definition]) => {
                weatherCharts[key] = makeChart(
                    key,
                    definition.id,
                    definition.label,
                    labels,
                    maoWeatherChartData[key],
                    definition.color,
                    definition.unit,
                );
                updateInterpretation(key, labels, maoWeatherChartData[key]);
            });
            const setMaoText = (selector, value) => {
                document.querySelectorAll(selector).forEach((target) => { target.textContent = value; });
            };
            const formatOne = (value) => Number.isFinite(Number(value)) ? Number(value).toFixed(1) : 'N/A';
            const updateWeatherChart = (key, seriesLabels, values) => {
                if (!Array.isArray(seriesLabels) || !Array.isArray(values)) return;

                let chart = weatherCharts[key];
                if (!chart) {
                    const definition = weatherChartDefinitions[key];
                    weatherCharts[key] = makeChart(key, definition.id, definition.label, seriesLabels, values, definition.color, definition.unit);
                    updateInterpretation(key, seriesLabels, values);
                    return;
                }

                chart.data.labels = seriesLabels.map((label) => chartLabels.wrap ? chartLabels.wrap(label, 12, 2) : label);
                chart.data.datasets[0].data = values;
                chart.update('none');
                setWeatherChartState(key, '', true);
                updateInterpretation(key, seriesLabels, values);
            };
            const renderMaoWeather = (payload) => {
                const weather = payload?.weather;
                if (!weather) return;

                setMaoText('[data-mao-weather-source]', payload.source_label || `${weather.source || 'Open-Meteo'} live weather for ${weather.location || 'Lian, Batangas'}`);
                setMaoText('[data-mao-current-rain]', formatOne(weather.current_rainfall_mm));
                setMaoText('[data-mao-current-temperature]', formatOne(weather.current_temperature_c));
                setMaoText('[data-mao-current-humidity]', formatOne(weather.humidity_percent));
                setMaoText('[data-mao-current-wind]', formatOne(weather.wind_speed_kmh));

                const series = weather.daily_series || {};
                updateWeatherChart('rainfall', series.labels, series.rainfall);
                updateWeatherChart('temperature', series.labels, series.temperature);
                updateWeatherChart('humidity', series.labels, series.humidity);
                updateWeatherChart('windSpeed', series.labels, series.windSpeed);
            };
            const refreshMaoWeather = async () => {
                if (!weatherRoot?.dataset.maoWeatherUrl || document.visibilityState !== 'visible') return;

                try {
                    const response = await fetch(weatherRoot.dataset.maoWeatherUrl, { headers: { Accept: 'application/json' } });
                    if (!response.ok) throw new Error(`Weather endpoint returned ${response.status}`);
                    const payload = await response.json();
                    if (!payload?.weather) {
                        Object.keys(weatherChartDefinitions).forEach((key) => {
                            if (!weatherCharts[key]) setWeatherChartState(key, 'No live or stored weather records found.');
                        });
                        return;
                    }
                    renderMaoWeather(payload);
                } catch (error) {
                    // Keep the last visible weather values when the live source is temporarily unavailable.
                    Object.keys(weatherChartDefinitions).forEach((key) => {
                        if (!weatherCharts[key]) setWeatherChartState(key, 'Weather data is temporarily unavailable. Please try again shortly.');
                    });
                    console.error('iClimate weather dashboard refresh failed.', error);
                }
            };
            const startMaoWeatherPolling = () => {
                if (maoWeatherPollTimer) clearInterval(maoWeatherPollTimer);
                maoWeatherPollTimer = setInterval(refreshMaoWeather, MAO_WEATHER_POLL_INTERVAL);
            };

            refreshMaoWeather();
            startMaoWeatherPolling();
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    refreshMaoWeather();
                    startMaoWeatherPolling();
                } else if (maoWeatherPollTimer) {
                    clearInterval(maoWeatherPollTimer);
                    maoWeatherPollTimer = null;
                }
            });

            const mapEl = document.getElementById('dashboardRiskMap');
            if (mapEl && maoValidMapAreas.length && window.L) {
                mapEl.classList.add('thermo-map');
                const RISK_COLORS = { Low: '#22c55e', Moderate: '#facc15', High: '#f97316', Severe: '#ef4444' };
                const riskColorFor = (riskLevel) => RISK_COLORS[riskLevel] || '#9aa5b1';
                const LABEL_ZOOM_THRESHOLD = 14;
                const yieldScore = (area) => area.predicted_yield === null ? .5 : (area.predicted_yield < 3 ? .9 : (area.predicted_yield < 4 ? .6 : .25));
                const rainfallScore = (area) => String(area.rainfall_status || '').toLowerCase().includes('low') ? .9 : (String(area.rainfall_status || '').toLowerCase().includes('high') ? .65 : .3);
                const irrigationScore = (area) => String(area.irrigation_recommendation || '').toLowerCase().match(/increase|prioritize|urgent|support|reduce/) ? .9 : (area.risk_score || .3);
                const impactScore = (area) => Number(area.risk_score || 0);
                const scoreFor = (area, layer) => ({ rainfall: rainfallScore, yield: yieldScore, irrigation: irrigationScore, impact: impactScore }[layer] || impactScore)(area);
                let markerLayer = null;
                let markerTooltips = [];
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
                const escapeHtml = (value) => String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
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
                    const colors = {
                        Severe: '--popup-bg:#fee2e2;--popup-color:#b91c1c;',
                        High: '--popup-bg:#ffedd5;--popup-color:#c2410c;',
                        Moderate: '--popup-bg:#fef9c3;--popup-color:#854d0e;',
                        Low: '--popup-bg:#dcfce7;--popup-color:#15803d;',
                    };

                    return `--popup-accent:${riskColorFor(area.risk_level)};${colors[area.risk_level] || colors.Low}`;
                };
                const popup = (area, layer = activeDashboardLayer) => {
                    const mainDetail = layerMainDetail(area, layer);
                    const secondDetail = layerSecondDetail(area, layer);

                    return `
                        <div class="map-popup" style="${popupStyle(area)}">
                            <div class="map-popup-title">
                                <div>
                                    <div class="map-popup-label">Barangay</div>
                                    <div class="map-popup-name">${escapeHtml(area.barangay)}</div>
                                    <div class="map-popup-note">Risk score ${Number(area.risk_score || 0).toFixed(2)} &middot; Showing: ${escapeHtml(layerTitle(layer))}</div>
                                </div>
                                <span class="map-popup-badge">${escapeHtml(area.risk_level)} Risk</span>
                            </div>
                            <div class="map-popup-takeaway">${escapeHtml(layerTakeaway(area, layer))}</div>
                            <div class="map-popup-grid">
                                <div class="map-popup-box">
                                    <div class="map-popup-label">${escapeHtml(mainDetail.label)}</div>
                                    <div class="map-popup-value">${escapeHtml(mainDetail.value)}</div>
                                    <div class="map-popup-note">${escapeHtml(mainDetail.note)}</div>
                                </div>
                                <div class="map-popup-box">
                                    <div class="map-popup-label">${escapeHtml(secondDetail.label)}</div>
                                    <div class="map-popup-value">${escapeHtml(secondDetail.value)}</div>
                                    <div class="map-popup-note">${escapeHtml(secondDetail.note)}</div>
                                </div>
                                <div class="map-popup-box">
                                    <div class="map-popup-label">Rice harvest</div>
                                    <div class="map-popup-value">${escapeHtml(harvestEstimate(area))}</div>
                                    <div class="map-popup-note">${escapeHtml(plainYield(area))}</div>
                                </div>
                            </div>
                            <div class="map-popup-advice">
                                <div class="map-popup-label">What to do now</div>
                                <div class="map-popup-value">${escapeHtml(layerAction(area, layer))}</div>
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
                    applyLabelVisibility();
                    detailPanel.querySelector('.map-detail-close')?.addEventListener('click', () => {
                        detailPanel.classList.remove('show');
                        selectedDashboardArea = null;
                        applyLabelVisibility();
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

                const applyLabelVisibility = () => {
                    const showLabels = map.getZoom() >= LABEL_ZOOM_THRESHOLD;
                    markerTooltips.forEach(({ marker, area }) => {
                        const shouldShow = showLabels || (selectedDashboardArea && selectedDashboardArea.barangay === area.barangay);
                        if (shouldShow && !marker.isTooltipOpen()) marker.openTooltip();
                        if (!shouldShow && marker.isTooltipOpen()) marker.closeTooltip();
                    });
                };

                const renderDashboardLayer = (layer = 'impact') => {
                    activeDashboardLayer = layer;
                    if (markerLayer) map.removeLayer(markerLayer);
                    markerTooltips = [];

                    markerLayer = L.featureGroup(maoValidMapAreas.flatMap((area) => {
                        const color = riskColorFor(area.risk_level);
                        const clickZone = L.circleMarker([area.latitude, area.longitude], {
                            radius: 34,
                            stroke: false,
                            fillOpacity: 0,
                            interactive: true,
                        });
                        const marker = L.marker([area.latitude, area.longitude], {
                            icon: L.divIcon({
                                className: '',
                                html: `<span class="thermo-point" style="--risk-dot-color:${color}" title="${escapeHtml(area.risk_level)} risk"></span>`,
                                iconSize: [20, 20],
                                iconAnchor: [10, 10],
                            }),
                        });

                        clickZone.on('click', () => showDetails(area, layer));
                        marker.on('click', () => showDetails(area, layer));

                        marker.bindTooltip(area.barangay, {
                            permanent: false,
                            direction: 'top',
                            offset: [0, -10],
                            className: 'barangay-tooltip',
                        });
                        markerTooltips.push({ marker, area });

                        return [clickZone, marker];
                    })).addTo(map);

                    applyLabelVisibility();

                    if (selectedDashboardArea && detailPanel?.classList.contains('show')) {
                        showDetails(selectedDashboardArea, layer);
                    }
                };

                renderDashboardLayer();
                map.fitBounds(markerLayer.getBounds().pad(.2));
                map.on('zoomend', applyLabelVisibility);
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
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeMaoDashboard, { once: true });
        } else {
            initializeMaoDashboard();
        }
    </script>
</x-app-layout>
