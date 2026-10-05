<x-app-layout>
    <style>
        .mao-console {
            --mao-ink: #1F2937;
            --mao-muted: #475569;
            --mao-green: #1F5A46;
            --mao-green-700: #1F5A46;   /* fill of the numbered workflow steps */
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
        .mao-console .btn-primary {
            background: var(--mao-green) !important; border-color: var(--mao-green) !important; color: #fff !important;
            box-shadow: none;
        }
        .mao-console .btn-light {
            background: rgba(255,255,255,.9) !important; border-color: rgba(31,90,70,.25) !important; color: var(--mao-ink) !important;
            box-shadow: none;
        }
        .mao-console .btn-primary:hover { background: #184a3a !important; border-color: #184a3a !important; color: #fff !important; transform: translateY(-1px); }
        .mao-console .btn-light:hover { background: #fff !important; border-color: rgba(31,90,70,.4) !important; color: var(--mao-ink) !important; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(8,25,19,.08); }
        .mao-console .btn-outline-light { border-color: rgba(255,255,255,.55) !important; color: #fff !important; background: transparent !important; }
        .mao-console .btn-outline-light:hover { background: #fff !important; border-color: #fff !important; color: var(--mao-ink) !important; }
        .mao-console .btn-outline-primary { border-color: var(--mao-green) !important; color: var(--mao-green) !important; background: transparent !important; }
        .mao-console .btn-outline-primary:hover { background: #fff !important; border-color: var(--mao-green) !important; color: var(--mao-green) !important; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(8,25,19,.12); }
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
        .mao-risk-badge-nodata { background: rgba(100,116,139,.16); color: #475569; }
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
        .forecast-strip { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: .6rem; overflow-x: auto; }
        .forecast-strip-day { border: 1px solid rgba(255,255,255,.55); border-radius: 8px; background: rgba(255,255,255,.66); backdrop-filter: blur(16px) saturate(170%); padding: .7rem; text-align: center; }
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
        .map-toolbar { position: absolute; z-index: 500; left: 1rem; right: 1rem; top: 1rem; display: flex; gap: .5rem; flex-wrap: wrap; pointer-events: none; }
        .map-toolbar > * { pointer-events: auto; }
        .layer-btn { min-height: 38px; border: 1px solid rgba(95,143,120,.35); border-radius: 8px; background: rgba(255,255,255,.8); backdrop-filter: blur(14px) saturate(170%); color: var(--mao-ink); padding: .48rem .64rem; font-size: .78rem; font-weight: 700; box-shadow: 0 .5rem 1.2rem rgba(13,31,24,.08); white-space: nowrap; }
        .layer-btn.active { background: #1F4D3A; border-color: #1F4D3A; color: #fff; }
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
        .mao-highlights { display: flex; flex-direction: column; gap: .6rem; margin-bottom: 1.25rem; }
        .mao-highlight { display: flex; align-items: flex-start; gap: .65rem; border-radius: 14px; padding: .75rem .95rem; border: 1px solid rgba(255,255,255,.55); background: rgba(255,255,255,.7); backdrop-filter: blur(16px) saturate(170%); }
        .mao-highlight-critical { border-left: 5px solid var(--mao-red); }
        .mao-highlight-warning { border-left: 5px solid #C58B2A; }
        .mao-highlight-info { border-left: 5px solid var(--mao-blue); }
        .mao-highlight-dot { flex: 0 0 auto; width: 9px; height: 9px; border-radius: 999px; margin-top: .35rem; }
        .mao-highlight-critical .mao-highlight-dot { background: var(--mao-red); }
        .mao-highlight-warning .mao-highlight-dot { background: #C58B2A; }
        .mao-highlight-info .mao-highlight-dot { background: var(--mao-blue); }
        .mao-highlight-title { color: var(--mao-ink); font-weight: 700; font-size: .92rem; }
        .mao-highlight-message { color: var(--mao-muted); font-size: .82rem; margin-top: .1rem; }
        .mao-yoy-badge { display: inline-flex; align-items: center; gap: .3rem; border-radius: 999px; padding: .25rem .65rem; font-family: 'DM Mono', monospace; font-size: .72rem; font-weight: 700; }
        .mao-yoy-up { background: rgba(184,74,74,.14); color: var(--mao-red); }
        .mao-yoy-down { background: rgba(95,143,120,.16); color: var(--mao-green); }
        .mao-yoy-flat { background: rgba(100,116,139,.14); color: var(--mao-muted); }
        .mao-irrigation-toggle { display: inline-flex; gap: .35rem; margin-bottom: .75rem; }
        .mao-irrigation-toggle button { border: 1px solid rgba(95,143,120,.35); background: transparent; color: var(--mao-muted); border-radius: 999px; padding: .3rem .8rem; font-size: .76rem; font-weight: 600; }
        .mao-irrigation-toggle button.active { background: var(--mao-green); border-color: var(--mao-green); color: #fff; }
        .mao-risk-breakdown { margin-top: .6rem; border-top: 1px solid rgba(95,143,120,.3); padding-top: .55rem; }
        .mao-risk-breakdown-row { display: flex; align-items: center; gap: .5rem; margin-bottom: .35rem; font-size: .72rem; }
        .mao-risk-breakdown-label { flex: 0 0 128px; color: var(--mao-muted); }
        .mao-risk-breakdown-bar { flex: 1 1 auto; height: 6px; border-radius: 999px; background: rgba(95,143,120,.14); overflow: hidden; }
        .mao-risk-breakdown-bar-fill { height: 100%; background: var(--mao-blue); border-radius: 999px; }
        .mao-risk-breakdown-value { flex: 0 0 38px; text-align: right; color: var(--mao-ink); font-weight: 700; }
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
        .dashboard-tabs {
            display: flex;
            align-items: center;
            gap: 0;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            border-bottom: 1px solid rgba(255,255,255,.18);
            margin: 0 0 1.25rem;
        }
        .dashboard-tabs::-webkit-scrollbar { display: none; }
        .dashboard-tab {
            flex: 0 0 auto;
            border: 0;
            background: transparent;
            color: rgba(255,255,255,.86);
            font-family: 'Inter', sans-serif;
            font-size: var(--text-sm);
            font-weight: 600;
            padding: .75rem .2rem;
            margin-right: 1.5rem;
            white-space: nowrap;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            transition: color .16s ease, border-color .16s ease;
        }
        .dashboard-tab:hover { color: #fff; }
        .dashboard-tab.active { color: #fff; border-bottom-color: var(--mao-accent); }
        .dashboard-tab-panel { display: none; }
        .dashboard-tab-panel.active { display: block; animation: icPageEnter var(--ic-motion-slow, 420ms) both; }
        .dashboard-tab-panel > .row:last-child, .dashboard-tab-panel > .mao-panel:last-child, .dashboard-tab-panel > section.mao-panel:last-child { margin-bottom: 0 !important; }
        .list-compact .mao-list-item:nth-of-type(n+5), .list-compact .risk-card:nth-of-type(n+5) { display: none; }
        @media (max-width: 1199.98px) { .mao-stat-grid, .visual-grid, .live-weather-grid, .forecast-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); } .quick-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .risk-level-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 767.98px) {
            .mao-stat-grid, .visual-grid, .quick-grid, .heat-grid, .variable-info-grid { grid-template-columns: 1fr; }
            .mao-stat-grid, .visual-grid, .live-weather-grid, .quick-grid, .heat-grid, .risk-level-grid, .variable-info-grid, .forecast-strip { gap: .6rem; }
            .variable-info-card { padding: .75rem; }
            .live-weather-grid, .risk-level-grid, .forecast-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .mao-stat-grid { margin-bottom: .9rem; }
            .mao-card-body { padding: .85rem; }
            .mao-panel-header, .dashboard-section-label { align-items: flex-start; flex-direction: column; }
            .mao-panel-header { padding: .85rem .85rem .6rem; }
            .mao-panel-body { padding: .8rem; }
            .mao-list-item { padding: .65rem 0; gap: .65rem; }
            .dashboard-section-label { margin: 1rem 0 .6rem; }
            .dashboard-tab { margin-right: 1rem; font-size: .84rem; }
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
        .mao-panel {
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
        .dashboard-tab,
        .map-popup-label { font-size: var(--text-sm); line-height: 1.35; }
        .mao-hero p,
        .section-note,
        .mao-note,
        .mao-panel-sub,
        .row-text,
        .quick-action span,
        .chart-note,
        .map-popup-note,
        .map-popup-source,
        .map-detail-empty { font-size: var(--text-sm); line-height: 1.5; color: var(--mao-muted); }
        /* Section notes sit on the dark page background, not on a light card. */
        .dashboard-section-label .section-note { color: var(--mao-mint); }
        .mao-panel-title,
        .chart-title,
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
        .mao-monitoring-table { color: var(--mao-ink); }
        .mao-monitoring-table thead th { background: rgba(95,143,120,.08); color: var(--mao-muted); text-transform: uppercase; letter-spacing: .04em; font-size: .72rem; font-weight: 700; border-color: rgba(95,143,120,.24); }
        .mao-monitoring-table td { border-color: rgba(95,143,120,.16); vertical-align: middle; }
    </style>

    @php
        $latestClimate = $latestClimate ?? null;
        $profileCount = $profileCount ?? 0;
        $totalClimateRecords = $totalClimateRecords ?? 0;
        // null stays null: missing harvest data is shown as "Insufficient verified data", never as 0.
        $riceProductionTotal = $riceProductionTotal ?? null;
        $riceAreaTotal = $riceAreaTotal ?? null;
        $avgYield = $avgYield ?? null;
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
        $recentFarmerProfiles = collect($recentFarmerProfiles ?? []);
        $recentClimateRecords = collect($recentClimateRecords ?? []);
        $recentRiceProductions = collect($recentRiceProductions ?? []);
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
                    <h1 class="h2 fw-bold mb-2">Weather &amp; Farm <em>Monitoring</em></h1>
                    <p class="mb-0">Weather, farm risk, planting, and yield for Lian, Batangas.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="mao-chip"><span class="mao-pulse"></span> Season: {{ $latestSeason }}</span>
                    <a class="btn btn-outline-light fw-bold" href="{{ route('planting-advisories.create') }}">Create Advisory</a>
                    <a class="btn btn-outline-light fw-bold" href="{{ route('reports.index') }}">Open Reports</a>
                </div>
            </div>
        </section>

        @if(! empty($maoHighlights))
            <section class="mao-highlights" aria-label="Highlights since your last visit">
                @foreach($maoHighlights as $highlight)
                    <div class="mao-highlight mao-highlight-{{ $highlight['severity'] }}">
                        <span class="mao-highlight-dot"></span>
                        <div>
                            @php
                                $hlTitle = preg_replace('/^(.+) is at (Low|Moderate|High|Severe) risk$/u', '$1: $2 Farm Risk', $highlight['title']);
                                $hlMessage = preg_replace('/^Risk score [\d.]+\/1\.00 \x{2014} (.*?) risk\.$/u', 'Main concern: $1. Farm risk ratings are a planning guide.', $highlight['message']);
                            @endphp
                            <div class="mao-highlight-title">{{ $hlTitle }}</div>
                            <div class="mao-highlight-message">{{ $hlMessage }}</div>
                        </div>
                    </div>
                @endforeach
            </section>
        @endif

        @if($activeTyphoonSafetyEvent)
            <section class="mao-panel mb-4" id="typhoon-safety" aria-label="Typhoon safety check-in">
                <div class="mao-panel-header">
                    <div>
                        <h2 class="mao-panel-title">Typhoon Safety Check-In</h2>
                        <p class="mao-panel-sub">"{{ $activeTyphoonSafetyEvent['title'] }}" &middot; {{ $activeTyphoonSafetyEvent['severity'] }}</p>
                    </div>
                    <span class="status-pill {{ $typhoonSafetyNeedsHelpCount > 0 ? '' : 'muted' }}">{{ $typhoonSafetyNeedsHelpCount }} need help</span>
                </div>
                <div class="mao-panel-body">
                    <div class="risk-level-grid mb-3" style="grid-template-columns:repeat(3,minmax(0,1fr));">
                        <div class="risk-level-card low">
                            <div class="mao-label">Safe</div>
                            <div class="risk-level-value">{{ number_format($typhoonSafetySafeCount) }}</div>
                            <div class="mao-note">farmer(s) checked in</div>
                        </div>
                        <div class="risk-level-card severe">
                            <div class="mao-label">Needs Help</div>
                            <div class="risk-level-value">{{ number_format($typhoonSafetyNeedsHelpCount) }}</div>
                            <div class="mao-note">farmer(s) checked in</div>
                        </div>
                        <div class="risk-level-card moderate">
                            <div class="mao-label">No Response</div>
                            <div class="risk-level-value">{{ number_format($typhoonSafetyNoResponseCount) }}</div>
                            <div class="mao-note">farmer(s) not yet checked in</div>
                        </div>
                    </div>
                    @if($typhoonSafetyNeedsHelpCount > 0)
                        <div class="mao-panel-body list-compact p-0">
                            @foreach($typhoonSafetyResponses->where('status', \App\Models\TyphoonSafetyResponse::STATUS_NEEDS_HELP) as $response)
                                <div class="mao-list-item">
                                    <div class="list-mark"><svg width="17" height="17" viewBox="0 0 20 20" fill="none"><path d="M10 2.5 18 16.5H2L10 2.5Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg></div>
                                    <div class="flex-grow-1">
                                        <div class="row-title">{{ $response->farmer?->name ?? 'Unknown farmer' }} &middot; {{ $response->farmer?->barangay ?? 'Barangay not on file' }}</div>
                                        <div class="row-text">{{ $response->note ?: 'No additional note provided.' }}</div>
                                        <div class="row-meta">Checked in {{ $response->responded_at?->diffForHumans() }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        @endif

        <x-dashboard-overview :overview="$overview" kpiTitle="Agriculture at a Glance" note="Municipality-wide figures from stored records." />

        <div class="dashboard-section-label">
            <div>
                <h2 class="section-title">Office Overview</h2>
                <p class="section-note">Core records and risk modules for the municipality.</p>
            </div>
        </div>

        <section class="mao-stat-grid">
            <button type="button" class="mao-card text-decoration-none" data-drawer-open="#maoStatFarmers"><div class="mao-card-body"><div class="mao-label">Farmer Profiles</div><div class="mao-value">{{ number_format($profileCount) }}</div><div class="mao-note">Registered farm records</div><div class="mao-tap-hint">View details &rarr;</div></div></button>
            <button type="button" class="mao-card text-decoration-none" data-drawer-open="#maoStatClimate"><div class="mao-card-body"><div class="mao-label">Climate Records</div><div class="mao-value">{{ number_format($totalClimateRecords) }}</div><div class="mao-note">Latest: {{ $latestClimate?->record_date?->format('M d, Y') ?? 'N/A' }}</div><div class="mao-tap-hint">View details &rarr;</div></div></button>
            <button type="button" class="mao-card text-decoration-none" data-drawer-open="#maoStatRice"><div class="mao-card-body"><div class="mao-label">Rice Production{{ ! empty($riceLatestYear) ? ' ('.$riceLatestYear.')' : '' }}</div>@if($riceProductionTotal !== null)<div class="mao-value">{{ number_format($riceProductionTotal, 1) }} <span class="mao-value-unit">tons</span></div><div class="mao-note">{{ $riceAreaTotal !== null ? number_format($riceAreaTotal, 1).' hectares harvested' : 'Area not recorded' }}</div>@else<div class="mao-value">&mdash;</div><div class="mao-note">Insufficient verified data</div>@endif<div class="mao-tap-hint">View details &rarr;</div></div></button>
            <a href="{{ route('farmer-profiles.index') }}" class="mao-card text-decoration-none"><div class="mao-card-body"><div class="mao-label">Farmer Organizations</div><div class="mao-value">{{ number_format($farmerOrganizationSummary['totalOrganizations']) }}</div><div class="mao-note">{{ number_format($farmerOrganizationSummary['totalFarmers']) }} farmers with profiles</div><div class="mao-tap-hint">View breakdown &rarr;</div></div></a>
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
                        @if($riceProductionTotal !== null)
                        <div class="mao-modal-headline">{{ number_format($riceProductionTotal, 1) }} tons produced{{ ! empty($riceLatestYear) ? ' in '.$riceLatestYear : '' }}</div>
                        <p class="mao-modal-sub">{{ $riceAreaTotal !== null ? number_format($riceAreaTotal, 1).' hectares harvested' : 'Area not recorded' }}@if($avgYield !== null) &middot; Average yield {{ number_format($avgYield, 2) }} t/ha (weighted by area)@endif.</p>
                        @else
                        <div class="mao-modal-headline">Insufficient verified data</div>
                        <p class="mao-modal-sub">No verified harvest records are on file yet.</p>
                        @endif
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

        <div class="dashboard-tabs" role="tablist" aria-label="Dashboard sections">
            <button type="button" class="dashboard-tab active" data-tab="overview" role="tab" aria-selected="true">Overview</button>
            <button type="button" class="dashboard-tab" data-tab="heatmap" role="tab" aria-selected="false">Heat Map &amp; Forecast</button>
            <button type="button" class="dashboard-tab" data-tab="production" role="tab" aria-selected="false">Monitoring &amp; Production</button>
            <button type="button" class="dashboard-tab" data-tab="fieldreports" role="tab" aria-selected="false">Field Reports</button>
            <button type="button" class="dashboard-tab" data-tab="publishing" role="tab" aria-selected="false">Publishing &amp; Activity</button>
            <button type="button" class="dashboard-tab" data-tab="reportscenter" role="tab" aria-selected="false">Reports</button>
        </div>

        <div class="dashboard-tab-panels">
        <div class="dashboard-tab-panel active" data-panel="overview">
        <section class="mao-panel mb-4">
            <div class="mao-panel-header">
                <div>
                    <h2 class="mao-panel-title">MAO Operational Workflow</h2>
                    <p class="mao-panel-sub">End-to-end municipal agricultural guidance and monitoring process.</p>
                </div>
                <span class="status-pill">Standard Operating Procedure</span>
            </div>
            <div class="mao-panel-body">
                <div class="row g-3">
                    <div class="col-lg-3 col-sm-6">
                        <div class="p-3 rounded h-100" style="background: rgba(31,90,70,0.05); border: 1px solid rgba(31,90,70,0.12);">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge rounded-circle p-1 d-inline-flex align-items-center justify-content-center" style="background: var(--mao-green-700); color: #fff; width: 26px; height: 26px; font-weight: 700; font-size: 0.85rem;">1</span>
                                <strong style="color: var(--mao-ink);">Climate Surveillance</strong>
                            </div>
                            <p class="small text-muted mb-0">Daily weather observations in Lian provide rainfall, temperature, and wind readings to track moisture levels and abnormal seasonal trends.</p>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6">
                        <div class="p-3 rounded h-100" style="background: rgba(31,90,70,0.05); border: 1px solid rgba(31,90,70,0.12);">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge rounded-circle p-1 d-inline-flex align-items-center justify-content-center" style="background: var(--mao-green-700); color: #fff; width: 26px; height: 26px; font-weight: 700; font-size: 0.85rem;">2</span>
                                <strong style="color: var(--mao-ink);">Risk &amp; Yield Review</strong>
                            </div>
                            <p class="small text-muted mb-0">Barangay monitoring and farm risk serve as an operational planning guide to identify vulnerable crop areas and flag yield shortfalls early.</p>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6">
                        <div class="p-3 rounded h-100" style="background: rgba(31,90,70,0.05); border: 1px solid rgba(31,90,70,0.12);">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge rounded-circle p-1 d-inline-flex align-items-center justify-content-center" style="background: var(--mao-green-700); color: #fff; width: 26px; height: 26px; font-weight: 700; font-size: 0.85rem;">3</span>
                                <strong style="color: var(--mao-ink);">Advisory Formulation</strong>
                            </div>
                            <p class="small text-muted mb-0">Extension personnel formulate and publish timely planting advisories, irrigation guidance, and weather warnings directly to farmers and cooperatives.</p>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6">
                        <div class="p-3 rounded h-100" style="background: rgba(31,90,70,0.05); border: 1px solid rgba(31,90,70,0.12);">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge rounded-circle p-1 d-inline-flex align-items-center justify-content-center" style="background: var(--mao-green-700); color: #fff; width: 26px; height: 26px; font-weight: 700; font-size: 0.85rem;">4</span>
                                <strong style="color: var(--mao-ink);">Harvest &amp; Governance</strong>
                            </div>
                            <p class="small text-muted mb-0">Post-harvest rice production and farmer reports are validated to calculate area-weighted yields, driving evidence-based municipal agricultural policy.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mao-panel" data-mao-weather-root data-mao-weather-url="{{ route('mao.dashboard.weather') }}">
            <div class="mao-panel-header">
                <div>
                    <h2 class="mao-panel-title">Current Farm Conditions</h2>
                    <p class="mao-panel-sub">Weather right now in Lian, Batangas.</p>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="status-pill {{ $weatherWarning['level'] === 'Low' ? '' : 'muted' }}" title="Based on current barangay farm risk levels">{{ $weatherWarning['label'] }}</span>
                    <span class="source-badge" data-mao-weather-source>{{ $liveWeather ? 'Live weather for '.data_get($liveWeather, 'location', 'Lian, Batangas') : 'Saved weather records' }}</span>
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('weather-predictions.index') }}">Open Weather Forecast</a>
                </div>
            </div>
            <div class="mao-panel-body">
                @if($liveWeather)
                    <div class="live-weather-grid">
                        <div class="live-weather-card">
                            <div class="mao-label">Current Rain</div>
                            <div class="risk-level-value" data-mao-current-rain>{{ is_numeric(data_get($liveWeather, 'current_rainfall_mm')) ? number_format($liveWeather['current_rainfall_mm'], 1) : '—' }}</div>
                            <div class="mao-note">mm right now</div>
                        </div>
                        <div class="live-weather-card">
                            <div class="mao-label">Temperature</div>
                            <div class="risk-level-value" data-mao-current-temperature>{{ is_numeric(data_get($liveWeather, 'current_temperature_c')) ? number_format($liveWeather['current_temperature_c'], 1) : '—' }}</div>
                            <div class="mao-note">&deg;C current reading</div>
                        </div>
                        <div class="live-weather-card">
                            <div class="mao-label">Humidity</div>
                            <div class="risk-level-value" data-mao-current-humidity>{{ is_numeric(data_get($liveWeather, 'humidity_percent')) ? number_format($liveWeather['humidity_percent'], 1) : '—' }}</div>
                            <div class="mao-note">% current reading</div>
                        </div>
                        <div class="live-weather-card">
                            <div class="mao-label">Wind Speed</div>
                            <div class="risk-level-value" data-mao-current-wind>{{ is_numeric(data_get($liveWeather, 'wind_speed_kmh')) ? number_format($liveWeather['wind_speed_kmh'], 1) : '—' }}</div>
                            <div class="mao-note">km/h current reading</div>
                        </div>
                    </div>
                @endif

                @if($weatherAnomaly['anomaly_percent'] !== null)
                    <div class="mao-note mb-3">
                        <strong style="color: var(--mao-ink);">Rainfall compared with normal:</strong>
                        {{ $weatherAnomaly['anomaly_label'] }}
                        ({{ number_format($weatherAnomaly['current_daily_rainfall'], 1) }} mm/day expected vs {{ number_format($weatherAnomaly['seasonal_avg_daily_rainfall'], 1) }} mm/day {{ $weatherAnomaly['season'] }}-season average &mdash;
                        {{ $weatherAnomaly['anomaly_percent'] > 0 ? '+' : '' }}{{ number_format($weatherAnomaly['anomaly_percent'], 1) }}%).
                    </div>
                @endif
            </div>
        </section>


        <section class="mao-panel">
            <div class="mao-panel-body">
                @if(! empty($weatherChartData['labels']))
                    <div class="chart-title">Weather Outlook: Next 7 Days</div>
                    <div class="chart-note">{{ $liveWeather ? 'Daily forecast for Lian, Batangas.' : 'Recent saved readings (live forecast unavailable).' }}</div>
                    <div class="forecast-strip mb-3" data-forecast-strip>
                        @foreach(array_slice($weatherChartData['labels'], 0, 7) as $i => $label)
                            <div class="forecast-strip-day" data-rain-mm="{{ is_numeric($weatherChartData['rainfall'][$i] ?? null) ? (float) $weatherChartData['rainfall'][$i] : '' }}">
                                <div class="mao-label">{{ $label }}</div>
                                <div class="risk-level-value" style="font-size:1.1rem;">{{ is_numeric($weatherChartData['rainfall'][$i] ?? null) ? number_format($weatherChartData['rainfall'][$i], 0).'mm' : '—' }}</div>
                                <div class="mao-note">{{ is_numeric($weatherChartData['temperature'][$i] ?? null) ? number_format($weatherChartData['temperature'][$i], 0).'°C' : '—' }} &middot; {{ is_numeric($weatherChartData['windSpeed'][$i] ?? null) ? number_format($weatherChartData['windSpeed'][$i], 0).($liveWeather ? 'km/h' : '').' wind' : '—' }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif

            </div>
        </section>

        <section class="mao-panel">
            <div class="mao-panel-header">
                <div>
                    <h2 class="mao-panel-title">Barangays to Watch</h2>
                    <p class="mao-panel-sub">Barangays with a High or Severe farm risk. The Heat Map tab shows all 19.</p>
                </div>
                <a class="btn btn-sm btn-outline-primary" href="#" onclick="document.querySelector('[data-tab=heatmap]')?.click(); return false;">View Details</a>
            </div>
            <div class="mao-panel-body list-compact">
                @php $watchRows = collect($barangayMonitoring)->filter(fn ($row) => in_array($row['risk_level'] ?? null, ['High', 'Severe'], true))->values(); @endphp
                @forelse($watchRows as $row)
                    <div class="mao-list-item">
                        <div class="flex-grow-1">
                            <div class="row-title">{{ $row['barangay'] }} <span class="mao-risk-badge {{ $row['risk_level'] === 'Severe' ? 'mao-risk-badge-severe' : 'mao-risk-badge-high' }}">{{ $row['risk_level'] }} Farm Risk</span></div>
                            <div class="row-text">{{ $row['risk_type'] ? 'Main concern: '.strtolower($row['risk_type']).'.' : 'Check local conditions.' }}</div>
                        </div>
                    </div>
                @empty
                    <div class="empty-soft"><strong>No High or Severe farm risk right now</strong></div>
                @endforelse
                <p class="mao-note mb-0">Farm risk ratings are a planning guide, not a certified hazard assessment.</p>
            </div>
        </section>

        <section class="mao-panel">
            <div class="mao-panel-header">
                <div>
                    <h2 class="mao-panel-title">Planting &amp; Expected Yield</h2>
                    <p class="mao-panel-sub">Latest season and production records.</p>
                </div>
                <span class="status-pill">{{ $cropStatus['season'] ?? 'Not recorded' }}</span>
            </div>
            <div class="mao-panel-body">
                <div class="risk-level-grid mb-3" style="grid-template-columns:repeat(4,minmax(0,1fr));">
                    <div class="risk-level-card low">
                        <div class="mao-label">Hectares Planted</div>
                        <div class="risk-level-value">{{ number_format($cropStatus['total_hectares'], 1) }}</div>
                        <div class="mao-note">total, latest records per barangay</div>
                    </div>
                    <div class="risk-level-card low">
                        <div class="mao-label">Irrigated</div>
                        <div class="risk-level-value">{{ number_format($cropStatus['irrigated_hectares'], 1) }}</div>
                        <div class="mao-note">{{ $cropStatus['irrigated_barangays'] }} barangay(s)</div>
                    </div>
                    <div class="risk-level-card moderate">
                        <div class="mao-label">Rainfed</div>
                        <div class="risk-level-value">{{ number_format($cropStatus['rainfed_hectares'], 1) }}</div>
                        <div class="mao-note">{{ $cropStatus['rainfed_barangays'] }} barangay(s)</div>
                    </div>
                    <div class="risk-level-card {{ $cropStatus['estimated_yield'] !== null && $cropStatus['actual_yield'] !== null && $cropStatus['estimated_yield'] > $cropStatus['actual_yield'] ? 'moderate' : 'low' }}">
                        <div class="mao-label">Expected vs Recorded Yield</div>
                        <div class="risk-level-value" style="font-size:1.4rem;">{{ $cropStatus['estimated_yield'] !== null ? number_format($cropStatus['estimated_yield'], 2) : 'N/A' }} / {{ $cropStatus['actual_yield'] !== null ? number_format($cropStatus['actual_yield'], 2) : 'N/A' }}</div>
                        <div class="mao-note">t/ha &middot; expected / recorded</div>
                    </div>
                </div>
                <p class="mao-note mb-0">Planting and harvest progress are not tracked yet. Figures come from the latest production records.</p>
            </div>
        </section>

        @php $lowerYieldSchedules = collect($farmerSchedules ?? [])->filter(fn ($schedule) => $schedule->comparison_interpretation === 'lower' && $schedule->predicted_yield !== null && $schedule->historical_same_month_average !== null)->values(); @endphp
        @if($lowerYieldSchedules->isNotEmpty())
            <section class="mao-panel">
                <div class="mao-panel-header">
                    <div>
                        <h2 class="mao-panel-title">Lower Than Previous Yield</h2>
                        <p class="mao-panel-sub">Farm schedules expected to yield less than the previous same-month average.</p>
                    </div>
                    <span class="status-pill muted">{{ $lowerYieldSchedules->count() }} {{ \Illuminate\Support\Str::plural('case', $lowerYieldSchedules->count()) }}</span>
                </div>
                <div class="mao-panel-body list-compact">
                    @foreach($lowerYieldSchedules->take(4) as $schedule)
                        <div class="mao-list-item">
                            <div class="flex-grow-1">
                                <div class="row-title">{{ $schedule->farmer?->farmerProfile?->full_name ?: $schedule->farmer?->name ?: 'Unknown farmer' }} &middot; {{ $schedule->barangay ?: 'Location not available' }}</div>
                                <div class="row-text">Expected {{ number_format((float) $schedule->predicted_yield, 2) }} t/ha vs {{ number_format((float) $schedule->historical_same_month_average, 2) }} t/ha before.@if($schedule->recommended_actions) Action: {{ $schedule->recommended_actions }}@endif</div>
                            </div>
                        </div>
                    @endforeach
                    <p class="mao-note mb-0">All schedules are under View Details in the Monitoring &amp; Production tab.</p>
                </div>
            </section>
        @endif

        @include('dashboards.partials.farmer-weather-feedback')

        </div>

        <div class="dashboard-tab-panel" data-panel="heatmap">
        <section class="mao-panel">
            <div class="mao-panel-body">
                @include('partials.barangay-risk-map', [
                    'heading' => 'Lian Municipality Heat Map',
                    'subheading' => 'All 19 barangays at a glance, using the same live weather and recorded rice yield data used municipality-wide. Click any barangay for details.',
                    'showRefresh' => true,
                ])
            </div>
        </section>

        <section class="mao-panel">
            <div class="mao-panel-header">
                <div>
                    <h2 class="mao-panel-title">Weather Charts</h2>
                    <p class="mao-panel-sub">Detailed charts for rainfall, temperature, humidity, and wind.</p>
                </div>
            </div>
            <div class="mao-panel-body">
                <div class="variable-info-grid">
                    <div class="variable-info-card">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="row-title mb-0">Rainfall &amp; Temperature</div>
                            <span class="status-pill">Used for expected yield</span>
                        </div>
                        <p class="row-text mb-0">Water and heat drive how rice grows. Expected yield is based mainly on rainfall, temperature, farm area, and season.</p>
                    </div>
                    <div class="variable-info-card">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="row-title mb-0">Humidity &amp; Wind Speed</div>
                            <span class="status-pill muted">For warnings only</span>
                        </div>
                        <p class="row-text mb-0">Used for weather warnings, such as strong wind before harvest or disease risk in humid weather. Not yet used in expected yield.</p>
                    </div>
                </div>

                @if(! empty($weatherChartData['labels']))
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
                                <div class="chart-note">{{ $liveWeather ? 'Forecast rainfall in millimeters.' : 'Recent saved rainfall in millimeters.' }}</div>
                                <div class="chart-canvas-scroll">
                                    <div class="chart-render-state" data-chart-state="rainfall" role="status">Loading real weather data...</div>
                                    <canvas id="rainfallChart" aria-busy="true" data-chart-source="{{ $liveWeather ? 'Online weather forecast for the municipality' : 'Stored iClimate climate records' }}" data-chart-type="{{ $liveWeather ? 'predicted' : 'historical' }}" data-chart-unit="mm"></canvas>
                                </div>
                                @include('dashboards.partials.chart-interpretation', ['key' => 'rainfall'])
                            </div>
                            <div class="chart-box" data-chart-pane="temperature">
                                <div class="chart-title">Temperature Chart</div>
                                <div class="chart-note">{{ $liveWeather ? 'Live forecast average temperature.' : 'Recent recorded average temperature.' }}</div>
                                <div class="chart-canvas-scroll">
                                    <div class="chart-render-state" data-chart-state="temperature" role="status">Loading real weather data...</div>
                                    <canvas id="temperatureChart" aria-busy="true" data-chart-source="{{ $liveWeather ? 'Online weather forecast for the municipality' : 'Stored iClimate climate records' }}" data-chart-type="{{ $liveWeather ? 'predicted' : 'historical' }}" data-chart-unit="°C"></canvas>
                                </div>
                                @include('dashboards.partials.chart-interpretation', ['key' => 'temperature'])
                            </div>
                            <div class="chart-box" data-chart-pane="humidity">
                                <div class="chart-title">Humidity Chart</div>
                                <div class="chart-note">{{ $liveWeather ? 'Live forecast humidity percentage.' : 'Recent recorded humidity percentage.' }}</div>
                                <div class="chart-canvas-scroll">
                                    <div class="chart-render-state" data-chart-state="humidity" role="status">Loading real weather data...</div>
                                    <canvas id="humidityChart" aria-busy="true" data-chart-source="{{ $liveWeather ? 'Online weather forecast for the municipality' : 'Stored iClimate climate records' }}" data-chart-type="{{ $liveWeather ? 'predicted' : 'historical' }}" data-chart-unit="%"></canvas>
                                </div>
                                @include('dashboards.partials.chart-interpretation', ['key' => 'humidity'])
                            </div>
                            <div class="chart-box" data-chart-pane="wind">
                                <div class="chart-title">Wind Speed Chart</div>
                                <div class="chart-note">{{ $liveWeather ? 'Live forecast wind speed.' : 'Recent recorded wind speed.' }}</div>
                                <div class="chart-canvas-scroll">
                                    <div class="chart-render-state" data-chart-state="windSpeed" role="status">Loading real weather data...</div>
                                    <canvas id="windSpeedChart" aria-busy="true" data-chart-source="{{ $liveWeather ? 'Online weather forecast for the municipality' : 'Stored iClimate climate records' }}" data-chart-type="{{ $liveWeather ? 'predicted' : 'historical' }}" data-chart-unit="{{ $liveWeather ? 'km/h' : '' }}"></canvas>
                                </div>
                                @include('dashboards.partials.chart-interpretation', ['key' => 'windSpeed'])
                            </div>
                        </div>
                    </div>
                @else
                    <div class="empty-soft"><strong>No weather chart data yet</strong><div class="small text-muted mt-1">Live weather is unavailable and there are no saved weather records to show.</div></div>
                @endif
            </div>
        </section>
        </div>

        <div class="dashboard-tab-panel" data-panel="production">
        <details class="dashboard-group" id="farmer-schedule-monitor">
            <summary>
                <div>
                    <h2 class="dashboard-group-title">Farmer Planting and Harvest Calendar</h2>
                    <p class="dashboard-group-note">Farmer schedules, expected yield, and follow-up needs.</p>
                </div>
                <span class="dashboard-group-toggle"></span>
            </summary>
            <div class="dashboard-group-body">
                @unless($plantingScheduleFeatureReady ?? true)
                    <div class="alert alert-warning mb-4" role="status"><strong>Farm calendar is temporarily unavailable.</strong> The dashboard remains available while the database update is completed.</div>
                @endunless
                <form class="row g-2 align-items-end mb-4" method="GET" action="{{ route('mao.operations') }}#farmer-schedule-monitor">
                    <div class="col-6 col-lg-2"><label class="form-label fw-bold" for="schedule_month">Month</label><select class="form-select" id="schedule_month" name="schedule_month"><option value="">All</option>@foreach(range(1,12) as $month)<option value="{{ $month }}" @selected(($scheduleFilters['month'] ?? 0) === $month)>{{ \Illuminate\Support\Carbon::create(2000,$month,1)->format('F') }}</option>@endforeach</select></div>
                    <div class="col-6 col-lg-2"><label class="form-label fw-bold" for="schedule_season">Season</label><select class="form-select" id="schedule_season" name="schedule_season"><option value="">All</option>@foreach(\App\Models\FarmerPlantingSchedule::SEASONS as $season)<option value="{{ $season }}" @selected(($scheduleFilters['season'] ?? '') === $season)>{{ $season }}</option>@endforeach</select></div>
                    <div class="col-6 col-lg-2"><label class="form-label fw-bold" for="schedule_barangay">Barangay</label><select class="form-select" id="schedule_barangay" name="schedule_barangay"><option value="">All</option>@foreach($scheduleBarangays as $barangay)<option value="{{ $barangay }}" @selected(($scheduleFilters['barangay'] ?? '') === $barangay)>{{ $barangay }}</option>@endforeach</select></div>
                    <div class="col-6 col-lg-2"><label class="form-label fw-bold" for="schedule_farm_type">Farm type</label><select class="form-select" id="schedule_farm_type" name="schedule_farm_type"><option value="">All</option>@foreach(\App\Models\FarmerPlantingSchedule::FARM_TYPES as $farmType)<option value="{{ $farmType }}" @selected(($scheduleFilters['farm_type'] ?? '') === $farmType)>{{ $farmType }}</option>@endforeach</select></div>
                    <div class="col-6 col-lg-2"><label class="form-label fw-bold" for="schedule_farmer">Farmer</label><select class="form-select" id="schedule_farmer" name="schedule_farmer"><option value="">All</option>@foreach($scheduleFarmers as $farmer)<option value="{{ $farmer->id }}" @selected(($scheduleFilters['farmer'] ?? 0) === $farmer->id)>{{ $farmer->name }}</option>@endforeach</select></div>
                    <div class="col-6 col-lg-2"><label class="form-label fw-bold" for="schedule_status">Status</label><select class="form-select" id="schedule_status" name="schedule_status"><option value="">All</option>@foreach(['upcoming_planting','planted','upcoming_harvest','harvested','delayed','needs_review'] as $status)<option value="{{ $status }}" @selected(($scheduleFilters['status'] ?? '') === $status)>{{ str($status)->replace('_',' ')->title() }}</option>@endforeach</select></div>
                    <div class="col-12 d-flex flex-wrap gap-2 mt-3"><button class="btn btn-primary" type="submit">Apply filters</button><a class="btn btn-outline-secondary" href="{{ route('mao.operations') }}#farmer-schedule-monitor">Clear</a><a class="btn btn-outline-primary ms-lg-auto" href="{{ route('calendar.index') }}">Open full calendar</a></div>
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
                                <div class="schedule-yield-box"><span>Expected yield</span><strong>{{ $schedule->predicted_yield !== null ? number_format((float)$schedule->predicted_yield, 2).' t/ha' : 'Not Enough Data' }}</strong></div>
                                <div class="schedule-yield-box"><span>Previous yield (same month)</span><strong>{{ $schedule->historical_same_month_average !== null ? number_format((float)$schedule->historical_same_month_average, 2).' t/ha' : 'No history' }}</strong></div>
                                <div class="schedule-yield-box"><span>Difference</span><strong>{{ $schedule->yield_difference !== null ? (($schedule->yield_difference > 0 ? '+' : '').number_format((float)$schedule->yield_difference, 2).' t/ha') : 'Not available' }}</strong></div>
                            </div>

                            @if($schedule->predicted_yield === null)
                                <div class="schedule-guidance"><strong>Not Enough Data</strong><br>Expected yield cannot be shown yet. Check the farm details and review this schedule.</div>
                            @elseif($schedule->historical_same_month_average === null)
                                <div class="schedule-guidance"><strong>No previous yield to compare.</strong><br>Keep watching the weather and check the field.@if($confidenceNeedsReview)<br><span class="text-muted">Limited data. Please review this schedule.</span>@endif</div>
                            @elseif($schedule->comparison_interpretation === 'lower')
                                <div class="schedule-guidance urgent"><strong>Recommended Actions</strong><br>{{ $schedule->recommended_actions }}@if($confidenceNeedsReview)<br><span class="text-muted">Limited data. Please review before deciding.</span>@endif</div>
                            @else
                                <div class="schedule-guidance"><strong>Notes</strong><br>Expected yield is {{ $schedule->comparison_interpretation ?: 'stable' }} compared with before. Keep watching the weather and keep the advisory updated.@if($confidenceNeedsReview)<br><span class="text-muted">Limited data. Please review this schedule.</span>@endif</div>
                            @endif
                        </article>
                    @empty
                        <div class="empty-state text-center p-4" style="grid-column:1/-1"><strong>No farmer schedules match these filters.</strong><p class="text-muted mb-0 mt-1">Saved farmer schedules will appear here.</p></div>
                    @endforelse
                </div>
            </div>
        </details>

        <section class="mao-panel">
            <div class="mao-panel-header">
                <div>
                    <h2 class="mao-panel-title">Barangay Overview</h2>
                    <p class="mao-panel-sub">{{ count($barangayMonitoring) }} barangay(s) tracked.</p>
                </div>
            </div>
            <div class="mao-panel-body p-0">
                <div class="table-responsive">
                    <table class="table mao-monitoring-table ic-row-hover mb-0">
                        <thead>
                            <tr>
                                <th>Barangay</th>
                                <th>Rice Area (ha)</th>
                                <th>Production (tons)</th>
                                <th>Avg Yield (t/ha)</th>
                                <th>Farm Risk</th>
                                <th>Main Concern</th>
                                <th>Last Monitored</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($barangayMonitoring as $row)
                                <tr>
                                    <td class="fw-bold">{{ $row['barangay'] }}</td>
                                    <td>{{ $row['area_hectares'] !== null ? number_format($row['area_hectares'], 1) : '—' }}</td>
                                    <td>{{ $row['production_volume'] !== null ? number_format($row['production_volume'], 1) : '—' }}</td>
                                    <td>{{ $row['avg_yield'] !== null ? number_format($row['avg_yield'], 2) : '—' }}</td>
                                    <td><span class="mao-risk-badge {{ in_array($row['risk_level'], ['High','Severe']) ? 'mao-risk-badge-severe' : ($row['risk_level'] === 'Moderate' ? 'mao-risk-badge-moderate' : ($row['risk_level'] === 'Low' ? 'mao-risk-badge-low' : 'mao-risk-badge-nodata')) }}">{{ $row['risk_level'] ?: 'No Data' }}</span></td>
                                    <td>{{ $row['risk_type'] ?: '—' }}</td>
                                    <td>{{ $row['last_monitored']?->diffForHumans() ?? 'Never' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center py-4">No barangay records yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <div class="row g-4 mb-0">
            <div class="col-xl-8">
                <div class="mao-panel h-100">
                    <div class="mao-panel-header"><div><h2 class="mao-panel-title">Recent Weather Records</h2><p class="mao-panel-sub">Saved rainfall, temperature, humidity, and wind entries.</p></div><a class="btn btn-sm btn-outline-primary" href="{{ route('climate-records.index') }}">Manage Climate</a></div>
                    <div class="mao-panel-body list-compact">
                        @forelse($recentClimateRecords as $record)
                            <div class="mao-list-item">
                                <div class="list-mark"><svg width="17" height="17" viewBox="0 0 20 20" fill="none"><path d="M5.5 10.5a3 3 0 0 1 .6-5.9 4 4 0 0 1 7.7.7A2.7 2.7 0 0 1 13.5 10.5h-8Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg></div>
                                <div class="flex-grow-1"><div class="row-title">{{ $record->record_date?->format('M d, Y') }} | {{ $record->season }} Season</div><div class="row-text">Rainfall {{ number_format($record->rainfall, 1) }} mm, temperature {{ number_format($record->temperature, 1) }}&deg;C, humidity {{ number_format($record->humidity, 1) }}%, wind {{ number_format($record->wind_speed, 1) }}.</div><div class="row-meta">Source: {{ $record->source }}</div></div>
                            </div>
                        @empty
                            <div class="empty-soft"><strong>No climate records yet</strong><div class="small text-muted mt-1">Add climate records to populate this monitoring list.</div></div>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="mao-panel h-100">
                    <div class="mao-panel-header"><div><h2 class="mao-panel-title">Common Tasks</h2><p class="mao-panel-sub">Common MAO actions for daily work.</p></div></div>
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

        <div class="row g-4 mb-0">
            <div class="col-xl-12">
                <div class="mao-panel h-100">
                    <div class="mao-panel-header">
                        <div><h2 class="mao-panel-title">Rice Harvest Records</h2><p class="mao-panel-sub">Recent barangay production records and yield per hectare.</p></div>
                        <div class="d-flex align-items-center gap-2">
                            @if($riceYoyDelta)
                                <span class="mao-yoy-badge mao-yoy-{{ $riceYoyDelta['direction'] }}" title="Average yield/ha: {{ $riceYoyDelta['previous_year'] }} = {{ number_format($riceYoyDelta['previous_avg_yield'], 2) }}, {{ $riceYoyDelta['latest_year'] }} = {{ number_format($riceYoyDelta['latest_avg_yield'], 2) }}">
                                    {{ $riceYoyDelta['direction'] === 'up' ? '▲' : ($riceYoyDelta['direction'] === 'down' ? '▼' : '—') }}
                                    {{ number_format(abs($riceYoyDelta['delta_percent']), 1) }}% vs {{ $riceYoyDelta['previous_year'] }}
                                </span>
                            @endif
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('rice-productions.index') }}">Open Records</a>
                        </div>
                    </div>
                    <div class="mao-panel-body list-compact">
                        <div class="mao-irrigation-toggle" data-irrigation-toggle role="group" aria-label="Filter by farm type">
                            <button type="button" class="active" data-irrigation-filter="all">All</button>
                            <button type="button" data-irrigation-filter="Irrigated">Irrigated</button>
                            <button type="button" data-irrigation-filter="Rainfed">Rainfed</button>
                        </div>
                        @forelse($recentRiceProductions as $production)
                            @php
                                $farmTypeTag = str_contains(strtolower((string) $production->irrigation_type), 'irrig') ? 'Irrigated' : 'Rainfed';
                            @endphp
                            <div class="mao-list-item" data-irrigation-row data-irrigation="{{ $farmTypeTag }}">
                                <div class="list-mark"><svg width="17" height="17" viewBox="0 0 20 20" fill="none"><rect x="2" y="12" width="4" height="6" rx="1" fill="currentColor"/><rect x="8" y="7.5" width="4" height="10.5" rx="1" fill="currentColor" opacity="0.75"/><rect x="14" y="3" width="4" height="15" rx="1" fill="currentColor" opacity="0.5"/></svg></div>
                                <div class="flex-grow-1"><div class="row-title">{{ $production->barangay }} | {{ $production->season }} {{ $production->year }}</div><div class="row-text">{{ number_format($production->area_hectares, 2) }} ha, {{ number_format($production->yield_per_hectare, 2) }} yield/ha, total {{ number_format($production->total_production, 2) }}.</div><div class="row-meta">{{ $production->irrigation_type }}</div></div>
                            </div>
                        @empty
                            <div class="empty-soft"><strong>No production records yet</strong><div class="small text-muted mt-1">Rice production entries will appear here.</div></div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-0 mt-1">
            <div class="col-12">
                <div class="mao-panel h-100">
                    <div class="mao-panel-header">
                        <div><h2 class="mao-panel-title">Rice Harvest Trend</h2><p class="mao-panel-sub">Average yield per hectare by year, and this year's highest/lowest producing barangays.</p></div>
                    </div>
                    <div class="mao-panel-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                @if($riceYieldTrend['highest_barangay'])
                                    <div class="risk-level-card low">
                                        <div class="mao-label">Highest Producing Barangay ({{ $riceYieldTrend['latest_year'] }})</div>
                                        <div class="risk-level-value" style="font-size:1.3rem;">{{ $riceYieldTrend['highest_barangay']->barangay }}</div>
                                        <div class="mao-note">{{ number_format((float) $riceYieldTrend['highest_barangay']->total_production, 1) }} tons total production</div>
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-6">
                                @if($riceYieldTrend['lowest_barangay'])
                                    <div class="risk-level-card moderate">
                                        <div class="mao-label">Lowest Producing Barangay ({{ $riceYieldTrend['latest_year'] }})</div>
                                        <div class="risk-level-value" style="font-size:1.3rem;">{{ $riceYieldTrend['lowest_barangay']->barangay }}</div>
                                        <div class="mao-note">{{ number_format((float) $riceYieldTrend['lowest_barangay']->total_production, 1) }} tons total production</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>

        <div class="dashboard-tab-panel" data-panel="fieldreports">
        <div class="row g-4 mb-0">
            <div class="col-xl-4">
                <div class="mao-panel h-100">
                    <div class="mao-panel-header">
                        <div><h2 class="mao-panel-title">Unresolved Field Issues</h2><p class="mao-panel-sub">Not yet resolved.</p></div>
                        <span class="status-pill {{ $fieldReportsPendingCount > 0 ? 'muted' : '' }}">{{ $fieldReportsPendingCount }} open</span>
                    </div>
                    <div class="mao-panel-body">
                        @forelse(\App\Models\FieldReport::ISSUE_TYPES as $issueType)
                            @php $count = $fieldReportsByIssueType[$issueType] ?? 0; @endphp
                            @if($count > 0)
                                <div class="mao-list-item">
                                    <div class="list-mark"><svg width="17" height="17" viewBox="0 0 20 20" fill="none"><path d="M10 2.5 18 16.5H2L10 2.5Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg></div>
                                    <div class="flex-grow-1"><div class="row-title">{{ $issueType }}</div></div>
                                    <span class="status-pill muted">{{ $count }}</span>
                                </div>
                            @endif
                        @empty
                        @endforelse
                        @if($fieldReportsPendingCount === 0)
                            <div class="empty-soft"><strong>No open issues</strong><div class="small text-muted mt-1">Field reports will appear here once submitted.</div></div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-xl-8">
                <div class="mao-panel h-100">
                    <div class="mao-panel-header">
                        <div><h2 class="mao-panel-title">Recent Field Reports</h2><p class="mao-panel-sub">Latest issues reported from the field.</p></div>
                        <div class="d-flex flex-wrap gap-2">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('field-reports.index') }}">View All</a>
                            <a class="btn btn-sm btn-primary" href="{{ route('field-reports.create') }}">Report a Field Issue</a>
                        </div>
                    </div>
                    <div class="mao-panel-body list-compact">
                        @forelse($recentFieldReports as $report)
                            @php
                                $reportBadgeClass = match($report->status) {
                                    'Resolved' => 'mao-risk-badge-low',
                                    'Verified' => 'mao-risk-badge-moderate',
                                    default => 'mao-risk-badge-severe',
                                };
                            @endphp
                            <div class="mao-list-item">
                                <div class="list-mark"><svg width="17" height="17" viewBox="0 0 20 20" fill="none"><path d="M10 2.5 18 16.5H2L10 2.5Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg></div>
                                <div class="flex-grow-1">
                                    <div class="row-title">{{ $report->title }} <span class="mao-risk-badge {{ $reportBadgeClass }}">{{ $report->status }}</span></div>
                                    <div class="row-text">{{ $report->barangay }} &middot; {{ $report->issue_type }}{{ $report->description ? ' — '.str($report->description)->limit(90) : '' }}</div>
                                    <div class="row-meta">{{ $report->assignedTo?->name ? 'Assigned to '.$report->assignedTo->name : 'Unassigned' }} &middot; {{ $report->created_at?->diffForHumans() }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="empty-soft"><strong>No field reports yet</strong><div class="small text-muted mt-1">Log a report to track pest/disease, flooding, drought, or crop-damage issues by barangay.</div></div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
        </div>

        <div class="dashboard-tab-panel" data-panel="publishing">
        <div class="row g-4 mb-0">
            <div class="col-xl-6">
                <div class="mao-panel h-100">
                    <div class="mao-panel-header">
                        <div><h2 class="mao-panel-title">Advisory Management</h2><p class="mao-panel-sub">Latest advisories for rice farmers.</p></div>
                        <div class="d-flex align-items-center gap-2">
                            @if($pendingAdvisoryCount > 0)
                                <span class="status-pill">{{ number_format($pendingAdvisoryCount) }} pending review</span>
                            @endif
                            <a class="btn btn-sm btn-primary" href="{{ route('planting-advisories.create') }}">New Advisory</a>
                        </div>
                    </div>
                    <div class="mao-panel-body list-compact">
                        @forelse($latestAdvisories as $advisory)
                            <div class="mao-list-item"><div class="list-mark"><svg width="17" height="17" viewBox="0 0 20 20" fill="none"><path d="M3.5 2.5h8l3 3v10h-11v-13z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M6 9h6M6 12h4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg></div><div><div class="row-title">{{ $advisory->title }}</div><div class="row-text">{{ str($advisory->content)->limit(95) }}</div><div class="row-meta">{{ $advisory->type }} | {{ $advisory->status }} | {{ $advisory->target_barangay ?: 'All barangays' }}</div></div></div>
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

        <section class="mao-panel" id="activity-log">
            <div class="mao-panel-header">
                <div><h2 class="mao-panel-title">Activity Log</h2><p class="mao-panel-sub">Most recent recorded events.</p></div>
            </div>
            <div class="mao-panel-body list-compact">
                @forelse($recentActivity as $log)
                    <div class="mao-list-item">
                        <div class="list-mark"><svg width="17" height="17" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="6.5" stroke="currentColor" stroke-width="1.4"/><path d="M9 5.5V9l3 1.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                        <div class="flex-grow-1">
                            <div class="row-title">{{ $log->action }}</div>
                            <div class="row-meta">{{ $log->user?->name ?? 'System' }} &middot; {{ $log->created_at?->diffForHumans() }}</div>
                        </div>
                    </div>
                @empty
                    <div class="empty-soft"><strong>No activity recorded yet</strong></div>
                @endforelse
            </div>
        </section>

        <div class="row g-4 mb-0">
            <div class="col-xl-6">
                <div class="mao-panel h-100">
                    <div class="mao-panel-header">
                        <div><h2 class="mao-panel-title">Farm Supplies &amp; Support</h2><p class="mao-panel-sub">Seeds, fertilizer, equipment, and irrigation support.</p></div>
                    </div>
                    <div class="mao-panel-body">
                        <div class="empty-soft"><strong>Not yet tracked</strong><div class="small text-muted mt-1">Resource inventory and distribution (seeds, fertilizer, equipment, irrigation support) is not yet a feature in iClimate. This card is a placeholder for that future module.</div></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="mao-panel h-100">
                    <div class="mao-panel-header">
                        <div><h2 class="mao-panel-title">Farmer Assistance Records</h2><p class="mao-panel-sub">Requests and assistance provided, by barangay.</p></div>
                    </div>
                    <div class="mao-panel-body">
                        <div class="empty-soft"><strong>Not yet tracked</strong><div class="small text-muted mt-1">Assistance requests and fulfillment tracking is not yet a feature in iClimate. This card is a placeholder for that future module.</div></div>
                    </div>
                </div>
            </div>
        </div>
        </div>

        <div class="dashboard-tab-panel" data-panel="reportscenter">
        <div class="mao-panel">
            <div class="mao-panel-header">
                <div><h2 class="mao-panel-title">Reports</h2><p class="mao-panel-sub">Generate summaries for climate records, rice production, farmer registration, and advisories.</p></div>
                <div class="d-flex align-items-center gap-2">
                    @if($latestExternalWeather)
                        <span class="status-pill {{ $latestExternalWeather->freshnessLabel() === 'outdated' ? 'muted' : '' }}" title="Fetched {{ $latestExternalWeather->fetched_at?->diffForHumans() }}">
                            Weather data: {{ ucfirst($latestExternalWeather->freshnessLabel()) }}
                        </span>
                    @endif
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('reports.index') }}">Reports Center</a>
                </div>
            </div>
            <div class="mao-panel-body list-compact">
                <div class="quick-grid mb-3">
                    <a class="quick-action" href="{{ route('reports.index', ['report_type' => 'Climate Records Report']) }}"><strong>Climate Report</strong><span>Track rainfall, temperature, and humidity trends to time planting and irrigation advisories.</span></a>
                    <a class="quick-action" href="{{ route('reports.index', ['report_type' => 'Rice Production Report']) }}"><strong>Rice Production Report</strong><span>Compare yield and production across barangay, season, and year for planning.</span></a>
                    <a class="quick-action" href="{{ route('reports.index', ['report_type' => 'Farmer Registration Report']) }}"><strong>Farmer Report</strong><span>Audit registered farmer accounts and profile completeness.</span></a>
                    <a class="quick-action" href="{{ route('reports.index', ['report_type' => 'Advisory Report']) }}"><strong>Advisory Report</strong><span>Review what was published, when, and to which barangay.</span></a>
                    <a class="quick-action" href="{{ route('reports.index', ['report_type' => 'Community Feed Report']) }}"><strong>Community Feed Report</strong><span>Check outreach activity — posts published and engagement.</span></a>
                </div>
                @forelse($latestReports as $report)
                    <div class="mao-list-item"><div class="list-mark"><svg width="17" height="17" viewBox="0 0 20 20" fill="none"><path d="M4 3h10l4 4v12H4V3z" stroke="currentColor" stroke-width="1.4"/><path d="M14 3v4h4" stroke="currentColor" stroke-width="1.4"/><path d="M7 10h8M7 13h6M7 16h7" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg></div><div class="flex-grow-1"><div class="row-title">{{ $report->title }}</div><div class="row-text">{{ $report->report_type }} generated by {{ $report->generatedBy?->name ?? 'System' }}.</div><div class="row-meta">{{ $report->created_at?->format('M d, Y') }}</div></div><a class="btn btn-sm btn-outline-primary" href="{{ route('reports.show', $report) }}">Open</a></div>
                @empty
                    <div class="empty-soft"><strong>No report history yet</strong><div class="small text-muted mt-1">Generated reports will appear here.</div></div>
                @endforelse
            </div>
        </div>
        </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const dashboardTabButtons = document.querySelectorAll('.dashboard-tabs .dashboard-tab');
            const dashboardTabPanels = document.querySelectorAll('.dashboard-tab-panels .dashboard-tab-panel');
            dashboardTabButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    dashboardTabButtons.forEach((btn) => {
                        btn.classList.toggle('active', btn === button);
                        btn.setAttribute('aria-selected', btn === button ? 'true' : 'false');
                    });
                    dashboardTabPanels.forEach((panel) => {
                        panel.classList.toggle('active', panel.dataset.panel === button.dataset.tab);
                    });
                });
            });
        });
    </script>
    @php
        $maoWeatherChartData = $weatherChartData;
    @endphp
    <div id="mao-weather-chart-data" data-json="{{ json_encode($maoWeatherChartData, JSON_HEX_TAG | JSON_HEX_AMP) }}" hidden></div>
    @php
        $maoRiceYieldData = $riceYieldTrend['chart'];
    @endphp
    <div id="mao-rice-yield-trend-data" data-json="{{ json_encode($maoRiceYieldData, JSON_HEX_TAG | JSON_HEX_AMP) }}" hidden></div>
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

            document.querySelectorAll('[data-irrigation-toggle]').forEach((toggle) => {
                const rows = toggle.parentElement?.querySelectorAll('[data-irrigation-row]') || [];

                toggle.querySelectorAll('[data-irrigation-filter]').forEach((button) => {
                    button.addEventListener('click', () => {
                        toggle.querySelectorAll('[data-irrigation-filter]').forEach((other) => other.classList.remove('active'));
                        button.classList.add('active');

                        const filter = button.dataset.irrigationFilter;
                        rows.forEach((row) => {
                            row.hidden = filter !== 'all' && row.dataset.irrigation !== filter;
                        });
                    });
                });
            });

            const MAO_WEATHER_POLL_INTERVAL = 60000;
            const parseMaoJson = (id, fallback) => {
                const source = document.getElementById(id)?.dataset.json;
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
                            label: (context) => `${context.dataset.label}: ${context.parsed.y}${unit ? ' ' + unit : ''}`,
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
                // unit intentionally omitted: this chart reads stored ClimateRecord.wind_speed,
                // whose unit is not established by any repository evidence (see the
                // "Climate Monitoring Summary" list below for the same disclosure).
                windSpeed: { id: 'windSpeedChart', label: 'Wind Speed', color: '#f4b63f', unit: '' },
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

                // Unit intentionally omitted from this sentence — see the chart definition above.
                return { text: `Wind speed reaches ${value} on ${date}, which is ${category} wind.`, action, status };
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

            let riceYieldTrendData = { labels: [], avg_yield: [] };
            try {
                const dataEl = document.getElementById('mao-rice-yield-trend-data');
                if (dataEl && dataEl.dataset.json) {
                    riceYieldTrendData = JSON.parse(dataEl.dataset.json);
                }
            } catch (e) {
                console.warn('Failed to parse rice yield trend data:', e);
            }
            const riceYieldTrendCanvas = document.getElementById('riceYieldTrendChart');
            let riceYieldTrendChart = null;
            if (riceYieldTrendCanvas && Array.isArray(riceYieldTrendData?.labels) && riceYieldTrendData.labels.length >= 2) {
                riceYieldTrendChart = new Chart(riceYieldTrendCanvas, {
                    type: 'line',
                    data: {
                        labels: riceYieldTrendData.labels,
                        datasets: [{
                            label: 'Average yield (t/ha)',
                            data: Array.isArray(riceYieldTrendData.avg_yield) ? riceYieldTrendData.avg_yield : [],
                            borderColor: '#1F5A46',
                            backgroundColor: '#1F5A4622',
                            fill: true,
                            borderWidth: 3,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            tension: .3,
                        }],
                    },
                    options: chartOptions('t/ha'),
                });
            }

            // Charts inside a hidden tab panel render at 0 width, since Chart.js
            // sizes to its container at construction time. Resize once the panel
            // containing a chart becomes active so it isn't left blank/undersized.
            document.querySelectorAll('.dashboard-tabs .dashboard-tab').forEach((tab) => {
                tab.addEventListener('click', () => {
                    if (tab.dataset.tab === 'heatmap') Object.values(weatherCharts).forEach((chart) => chart?.resize());
                    if (tab.dataset.tab === 'production') riceYieldTrendChart?.resize();
                });
            });

            const setMaoText = (selector, value) => {
                document.querySelectorAll(selector).forEach((target) => { target.textContent = value; });
            };
            // Strict Number.isFinite (no coercion): a genuinely missing
            // reading arrives as null/undefined from the backend and must
            // show "N/A", not be coerced to 0 first (Number(null) === 0,
            // which Number.isFinite(Number(value)) would wrongly accept).
            const formatOne = (value) => Number.isFinite(value) ? Number(value).toFixed(1) : 'N/A';
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

                setMaoText('[data-mao-weather-source]', `Live weather for ${weather.location || 'Lian, Batangas'}`);
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
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeMaoDashboard, { once: true });
        } else {
            initializeMaoDashboard();
        }
    </script>
</x-app-layout>
