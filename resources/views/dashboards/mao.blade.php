<x-app-layout>
    <style>
        .mao-console {
            --mao-ink: #1F2937;
            --mao-muted: #475569;
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
        .mao-risk-badge-severe { background: rgba(184,74,74,.14); color: var(--mao-red); }
        .mao-risk-badge-moderate { background: rgba(197,139,42,.14); color: #C58B2A; }
        .mao-risk-badge-low { background: rgba(95,143,120,.14); color: var(--mao-green); }
        .mao-risk-interpretation { font-weight: 500; color: var(--mao-ink); }
        .status-pill { display: inline-flex; align-items: center; border-radius: 999px; padding: .3rem .62rem; font-family: 'DM Mono', monospace; font-size: .64rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; background: rgba(95,143,120,.14); color: var(--mao-green); }
        .status-pill.muted { background: var(--mao-green-50); color: var(--mao-muted); }
        .quick-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .85rem; }
        .quick-action { min-height: 108px; padding: 1rem; border-radius: 10px; border: 1px solid rgba(255,255,255,.5); background: rgba(238,243,239,.55); backdrop-filter: blur(8px) saturate(150%); color: inherit; text-decoration: none; }
        .quick-action:hover { transform: translateY(-2px); border-color: rgba(95,143,120,.55); }
        .quick-action strong { display: block; color: var(--mao-ink); }
        .quick-action span { display: block; color: var(--mao-muted); font-size: .82rem; margin-top: .25rem; }
        .heat-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .85rem; }
        .risk-card { padding: 1rem; border-radius: 8px; border: 1px solid rgba(95,143,120,.72); background: var(--mao-green-50); }
        .risk-card strong { color: var(--mao-ink); }
        .risk-card.severe, .risk-card.high { border-color: rgba(216,91,69,.45); background: linear-gradient(135deg, #fff3ee, #f5e7db); }
        .variable-info-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .85rem; margin-bottom: 1rem; }
        .variable-info-card { border: 1px solid rgba(255,255,255,.55); border-radius: 8px; background: rgba(255,255,255,.66); backdrop-filter: blur(16px) saturate(170%); padding: .85rem .9rem; }
        .variable-info-card .row-text { margin-top: .3rem; }
        .visual-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
        .live-weather-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; margin-bottom: 1rem; }
        .live-weather-card { border: 1px solid rgba(255,255,255,.55); border-radius: 8px; background: rgba(255,255,255,.66); backdrop-filter: blur(16px) saturate(170%); padding: .9rem; }
        .forecast-strip { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: .6rem; overflow-x: auto; }
        .forecast-strip-day { border: 1px solid rgba(255,255,255,.55); border-radius: 8px; background: rgba(255,255,255,.66); backdrop-filter: blur(16px) saturate(170%); padding: .7rem; text-align: center; }
        .source-badge { display: inline-flex; align-items: center; gap: .4rem; border-radius: 999px; border: 1px solid rgba(22,119,184,.24); background: #eef7ff; color: #155a84; padding: .35rem .65rem; font-size: .76rem; font-weight: 700; }
        .chart-box { position: relative; min-height: 330px; padding: 1rem; border: 1px solid rgba(255,255,255,.55); border-radius: 8px; background: rgba(255,255,255,.66); backdrop-filter: blur(16px) saturate(170%); overflow-x: auto; }
        .chart-box canvas { width: 100% !important; min-width: 420px; height: 260px !important; }
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
        .risk-level-card.low { border-left: 5px solid #5F8F78; }
        .risk-level-card.moderate { border-left: 5px solid #ffd166; }
        .risk-level-card.high, .risk-level-card.severe { border-left: 5px solid #d85b45; }
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
            color: rgba(255,255,255,.62);
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
        @media (max-width: 767.98px) { .mao-stat-grid, .live-weather-grid, .risk-level-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .visual-grid, .quick-grid, .heat-grid { grid-template-columns: 1fr; } .mao-panel-header, .dashboard-section-label { align-items: flex-start; flex-direction: column; } .mao-card-body, .live-weather-card, .risk-level-card { padding: .78rem; } .mao-value, .risk-level-value { font-size: 1.55rem; } .chart-box { min-height: 280px; padding: .75rem; } .chart-box canvas { min-width: 360px; height: 220px !important; } .dashboard-map { height: min(52vh, 320px); min-height: 260px; } .dashboard-map-shell { overflow: visible; } .map-toolbar { position: relative; inset: auto; margin-bottom: .75rem; overflow-x: auto; flex-wrap: nowrap; pointer-events: auto; -webkit-overflow-scrolling: touch; } .layer-btn { flex: 0 0 auto; min-width: max-content; font-size: .76rem; } .map-detail-panel { position: relative; inset: auto; width: auto; max-height: 260px; margin-top: .75rem; } }
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

        @if(! empty($maoHighlights))
            <section class="mao-highlights" aria-label="Highlights since your last visit">
                @foreach($maoHighlights as $highlight)
                    <div class="mao-highlight mao-highlight-{{ $highlight['severity'] }}">
                        <span class="mao-highlight-dot"></span>
                        <div>
                            <div class="mao-highlight-title">{{ $highlight['title'] }}</div>
                            <div class="mao-highlight-message">{{ $highlight['message'] }}</div>
                        </div>
                    </div>
                @endforeach
            </section>
        @endif

        @if($activeTyphoonSafetyEvent)
            <section class="mao-panel mb-4" aria-label="Typhoon safety check-in">
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
                                    <div class="row-text">Rainfall {{ number_format($record->rainfall, 1) }} mm, temperature {{ number_format($record->temperature, 1) }}°C, humidity {{ number_format($record->humidity, 1) }}%.</div>
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
                                    'severe', 'high' => 'mao-risk-badge-severe',
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

        <div class="dashboard-tabs" role="tablist" aria-label="Dashboard sections">
            <button type="button" class="dashboard-tab active" data-tab="overview" role="tab" aria-selected="true">Overview</button>
            <button type="button" class="dashboard-tab" data-tab="heatmap" role="tab" aria-selected="false">Heat Map &amp; Weather</button>
            <button type="button" class="dashboard-tab" data-tab="production" role="tab" aria-selected="false">Monitoring &amp; Production</button>
            <button type="button" class="dashboard-tab" data-tab="fieldreports" role="tab" aria-selected="false">Field Reports</button>
            <button type="button" class="dashboard-tab" data-tab="publishing" role="tab" aria-selected="false">Publishing &amp; Activity</button>
            <button type="button" class="dashboard-tab" data-tab="reportscenter" role="tab" aria-selected="false">Reports</button>
        </div>

        <div class="dashboard-tab-panels">
        <div class="dashboard-tab-panel active" data-panel="overview">
        <section class="mao-panel">
            <div class="mao-panel-header">
                <div>
                    <h2 class="mao-panel-title">Current Cropping Season</h2>
                    <p class="mao-panel-sub">Based on the latest recorded climate season and current production records.</p>
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
                        <div class="mao-label">Est. vs Actual Yield</div>
                        <div class="risk-level-value" style="font-size:1.4rem;">{{ $cropStatus['estimated_yield'] !== null ? number_format($cropStatus['estimated_yield'], 2) : 'N/A' }} / {{ $cropStatus['actual_yield'] !== null ? number_format($cropStatus['actual_yield'], 2) : 'N/A' }}</div>
                        <div class="mao-note">t/ha &middot; model estimate / recorded actual</div>
                    </div>
                </div>
                <p class="mao-note mb-0">Planting and harvesting progress are not yet tracked in iClimate — figures above reflect the latest production and yield records on file.</p>
            </div>
        </section>
        </div>

        <div class="dashboard-tab-panel" data-panel="heatmap">
        <section class="mao-panel" data-mao-weather-root data-mao-weather-url="{{ route('mao.dashboard.weather') }}">
            <div class="mao-panel-header">
                <div>
                    <h2 class="mao-panel-title">Barangay Heat Map Dashboard</h2>
                    <p class="mao-panel-sub">Primary view for rainfall, yield, irrigation, and climate-impact risk by barangay.</p>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="status-pill {{ $weatherWarning['level'] === 'Low' ? '' : 'muted' }}" title="Derived from current barangay risk levels">{{ $weatherWarning['label'] }}</span>
                    <span class="source-badge" data-mao-weather-source>{{ $weatherDataSource }}</span>
                    <a class="btn btn-sm btn-primary" href="{{ route('heatmap-areas.index') }}">Open Heat Map</a>
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('weather-predictions.index') }}">Open Prediction</a>
                </div>
            </div>
            <div class="mao-panel-body">
                @if($liveWeather)
                    <div class="live-weather-grid">
                        <div class="live-weather-card">
                            <div class="mao-label">Current Rain</div>
                            <div class="risk-level-value" data-mao-current-rain>{{ number_format($liveWeather['current_rainfall_mm'], 1) }}</div>
                            <div class="mao-note">mm right now</div>
                        </div>
                        <div class="live-weather-card">
                            <div class="mao-label">Temperature</div>
                            <div class="risk-level-value" data-mao-current-temperature>{{ number_format($liveWeather['current_temperature_c'], 1) }}</div>
                            <div class="mao-note">°C current reading</div>
                        </div>
                        <div class="live-weather-card">
                            <div class="mao-label">Humidity</div>
                            <div class="risk-level-value" data-mao-current-humidity>{{ number_format($liveWeather['humidity_percent'], 1) }}</div>
                            <div class="mao-note">% current reading</div>
                        </div>
                        <div class="live-weather-card">
                            <div class="mao-label">Wind Speed</div>
                            <div class="risk-level-value" data-mao-current-wind>{{ number_format($liveWeather['wind_speed_kmh'], 1) }}</div>
                            <div class="mao-note">km/h current reading</div>
                        </div>
                    </div>
                @endif

                @if($weatherAnomaly['anomaly_percent'] !== null)
                    <div class="mao-note mb-3">
                        <strong style="color: var(--mao-ink);">Rainfall anomaly:</strong>
                        {{ $weatherAnomaly['anomaly_label'] }}
                        ({{ number_format($weatherAnomaly['current_daily_rainfall'], 1) }} mm/day forecast vs {{ number_format($weatherAnomaly['seasonal_avg_daily_rainfall'], 1) }} mm/day {{ $weatherAnomaly['season'] }}-season average &mdash;
                        {{ $weatherAnomaly['anomaly_percent'] > 0 ? '+' : '' }}{{ number_format($weatherAnomaly['anomaly_percent'], 1) }}%).
                    </div>
                @endif

                <div class="row g-4">
                    <div class="col-xl-8">
                        <div class="chart-title">Municipal Heat Map</div>
                        <div class="chart-note">Barangay markers colored by climate and production risk. Start here before reviewing weather trends.</div>
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
            </div>
        </section>

        <section class="mao-panel">
            <div class="mao-panel-header">
                <div>
                    <h2 class="mao-panel-title">Weather Trends &amp; Model Inputs</h2>
                    <p class="mao-panel-sub">Forecast detail and the variables behind the yield model and the live readings above.</p>
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
                        <p class="row-text mb-0">Tracked live above for weather forecasting and risk warnings (e.g. high wind before harvest, high humidity disease risk). Not yet part of the trained yield model &mdash; validating whether they should be added requires more historical climate and production records than are currently on file.</p>
                    </div>
                </div>

                @if(! empty($weatherChartData['labels']))
                    <div class="chart-title">7-Day Forecast</div>
                    <div class="chart-note">{{ $liveWeather ? 'Live daily forecast for Lian, Batangas.' : 'Recent recorded readings (live forecast unavailable).' }}</div>
                    <div class="forecast-strip mb-3">
                        @foreach(array_slice($weatherChartData['labels'], 0, 7) as $i => $label)
                            <div class="forecast-strip-day">
                                <div class="mao-label">{{ $label }}</div>
                                <div class="risk-level-value" style="font-size:1.1rem;">{{ number_format($weatherChartData['rainfall'][$i] ?? 0, 0) }}mm</div>
                                <div class="mao-note">{{ number_format($weatherChartData['temperature'][$i] ?? 0, 0) }}&deg;C &middot; {{ number_format($weatherChartData['windSpeed'][$i] ?? 0, 0) }}km/h wind</div>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if(! empty($weatherChartData['labels']))
                    <div data-chart-tabs>
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
                                <canvas id="rainfallChart"></canvas>
                            </div>
                            <div class="chart-box" data-chart-pane="temperature">
                                <div class="chart-title">Temperature Chart</div>
                                <div class="chart-note">{{ $liveWeather ? 'Live forecast average temperature.' : 'Recent recorded average temperature.' }}</div>
                                <canvas id="temperatureChart"></canvas>
                            </div>
                            <div class="chart-box" data-chart-pane="humidity">
                                <div class="chart-title">Humidity Chart</div>
                                <div class="chart-note">{{ $liveWeather ? 'Live forecast humidity percentage.' : 'Recent recorded humidity percentage.' }}</div>
                                <canvas id="humidityChart"></canvas>
                            </div>
                            <div class="chart-box" data-chart-pane="wind">
                                <div class="chart-title">Wind Speed Chart</div>
                                <div class="chart-note">{{ $liveWeather ? 'Live forecast wind speed.' : 'Recent recorded wind speed.' }}</div>
                                <canvas id="windSpeedChart"></canvas>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="empty-soft"><strong>No weather chart data yet</strong><div class="small text-muted mt-1">Open-Meteo is unavailable and there are no saved climate records to show.</div></div>
                @endif
            </div>
        </section>
        </div>

        <div class="dashboard-tab-panel" data-panel="production">
        <section class="mao-panel">
            <div class="mao-panel-header">
                <div>
                    <h2 class="mao-panel-title">Barangay Monitoring Table</h2>
                    <p class="mao-panel-sub">{{ count($barangayMonitoring) }} barangay(s) tracked.</p>
                </div>
            </div>
            <div class="mao-panel-body p-0">
                <div class="table-responsive">
                    <table class="table mao-monitoring-table mb-0">
                        <thead>
                            <tr>
                                <th>Barangay</th>
                                <th>Rice Area (ha)</th>
                                <th>Production (tons)</th>
                                <th>Avg Yield (t/ha)</th>
                                <th>Risk Level</th>
                                <th>Active Issue</th>
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
                                    <td><span class="mao-risk-badge {{ in_array($row['risk_level'], ['High','Severe']) ? 'mao-risk-badge-severe' : ($row['risk_level'] === 'Moderate' ? 'mao-risk-badge-moderate' : 'mao-risk-badge-low') }}">{{ $row['risk_level'] ?: 'Unknown' }}</span></td>
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

        <div class="row g-4 mb-0">
            <div class="col-xl-7">
                <div class="mao-panel h-100">
                    <div class="mao-panel-header">
                        <div><h2 class="mao-panel-title">Rice Production Monitoring</h2><p class="mao-panel-sub">Recent barangay production records and yield per hectare.</p></div>
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

        <div class="row g-4 mb-0 mt-1">
            <div class="col-12">
                <div class="mao-panel h-100">
                    <div class="mao-panel-header">
                        <div><h2 class="mao-panel-title">Rice Yield Trend</h2><p class="mao-panel-sub">Average yield per hectare by year, and this year's highest/lowest producing barangays.</p></div>
                    </div>
                    <div class="mao-panel-body">
                        @if(count($riceYieldTrend['chart']['labels']) >= 2)
                            <div class="chart-box mb-3" style="min-height:260px;">
                                <canvas id="riceYieldTrendChart"></canvas>
                            </div>
                        @else
                            <div class="empty-soft mb-3"><strong>Not enough years of data for a trend chart</strong><div class="small text-muted mt-1">At least two years of rice production records are needed.</div></div>
                        @endif
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
                        <div><h2 class="mao-panel-title">Open Issues by Type</h2><p class="mao-panel-sub">Not yet resolved.</p></div>
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
                            <a class="btn btn-sm btn-primary" href="{{ route('field-reports.create') }}">Log Report</a>
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

        <section class="mao-panel">
            <div class="mao-panel-header">
                <div><h2 class="mao-panel-title">System Activity</h2><p class="mao-panel-sub">Most recent recorded events.</p></div>
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
                        <div><h2 class="mao-panel-title">Agricultural Resources</h2><p class="mao-panel-sub">Seeds, fertilizer, equipment, and irrigation support.</p></div>
                    </div>
                    <div class="mao-panel-body">
                        <div class="empty-soft"><strong>Not yet tracked</strong><div class="small text-muted mt-1">Resource inventory and distribution (seeds, fertilizer, equipment, irrigation support) is not yet a feature in iClimate. This card is a placeholder for that future module.</div></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="mao-panel h-100">
                    <div class="mao-panel-header">
                        <div><h2 class="mao-panel-title">Farmer Assistance Monitoring</h2><p class="mao-panel-sub">Requests and assistance provided, by barangay.</p></div>
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
                <div><h2 class="mao-panel-title">Reports & Analytics</h2><p class="mao-panel-sub">Generate summaries for climate records, rice production, farmer registration, and advisories.</p></div>
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
            const chartData = @json($weatherChartData);
            const mapAreas = @json($dashboardMapAreas);
            const labels = chartData.labels || [];
            const chartLabels = window.iClimateChartLabels || {};
            const readableLabels = labels.map((label) => chartLabels.wrap ? chartLabels.wrap(label, 12, 2) : label);
            const weatherRoot = document.querySelector('[data-mao-weather-root]');
            let maoWeatherPollTimer = null;

            const chartOptions = (unit) => ({
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
                            ? chartLabels.horizontalCategoryTicks({ color: '#334155', autoSkip: labels.length > 8, maxTicksLimit: 8, font: { size: 12, weight: 600, lineHeight: 1.25 } })
                            : { maxRotation: 0, minRotation: 0, autoSkip: labels.length > 8, maxTicksLimit: 8, color: '#334155', font: { size: 12, weight: 600 } },
                    },
                    y: { beginAtZero: true, grid: { color: 'rgba(13,31,24,.08)' }, ticks: { color: '#475569', font: { size: 12, weight: 600 }, padding: 8 } },
                },
            });

            const makeChart = (id, label, values, color, unit) => {
                const canvas = document.getElementById(id);
                if (!canvas || !labels.length) return null;

                return new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: readableLabels,
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

            const weatherCharts = {
                rainfall: makeChart('rainfallChart', 'Rainfall', chartData.rainfall, '#1677b8', 'mm'),
                temperature: makeChart('temperatureChart', 'Temperature', chartData.temperature, '#d85b45', 'C'),
                humidity: makeChart('humidityChart', 'Humidity', chartData.humidity, '#52b788', '%'),
                windSpeed: makeChart('windSpeedChart', 'Wind Speed', chartData.windSpeed, '#f4b63f', 'km/h'),
            };

            const riceYieldTrendData = @json($riceYieldTrend['chart']);
            const riceYieldTrendCanvas = document.getElementById('riceYieldTrendChart');
            let riceYieldTrendChart = null;
            if (riceYieldTrendCanvas && riceYieldTrendData.labels.length >= 2) {
                riceYieldTrendChart = new Chart(riceYieldTrendCanvas, {
                    type: 'line',
                    data: {
                        labels: riceYieldTrendData.labels,
                        datasets: [{
                            label: 'Average yield (t/ha)',
                            data: riceYieldTrendData.avg_yield,
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
            const formatOne = (value) => Number.isFinite(Number(value)) ? Number(value).toFixed(1) : 'N/A';
            const updateWeatherChart = (chart, labels, values) => {
                if (!chart || !Array.isArray(labels) || !Array.isArray(values)) return;

                chart.data.labels = labels.map((label) => chartLabels.wrap ? chartLabels.wrap(label, 12, 2) : label);
                chart.data.datasets[0].data = values;
                chart.update('none');
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
                updateWeatherChart(weatherCharts.rainfall, series.labels, series.rainfall);
                updateWeatherChart(weatherCharts.temperature, series.labels, series.temperature);
                updateWeatherChart(weatherCharts.humidity, series.labels, series.humidity);
                updateWeatherChart(weatherCharts.windSpeed, series.labels, series.windSpeed);
            };
            const refreshMaoWeather = async () => {
                if (!weatherRoot?.dataset.maoWeatherUrl || document.visibilityState !== 'visible') return;

                try {
                    const response = await fetch(weatherRoot.dataset.maoWeatherUrl, { headers: { Accept: 'application/json' } });
                    if (!response.ok) return;
                    renderMaoWeather(await response.json());
                } catch (error) {
                    // Keep the last visible weather values when the live source is temporarily unavailable.
                }
            };
            const startMaoWeatherPolling = () => {
                if (maoWeatherPollTimer) clearInterval(maoWeatherPollTimer);
                maoWeatherPollTimer = setInterval(refreshMaoWeather, MAO_WEATHER_POLL_INTERVAL);
            };

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
                    if (['High', 'Severe'].includes(area.risk_level)) {
                        return '--popup-accent:#d85b45;--popup-bg:#fde8e2;--popup-color:#9f3728;';
                    }
                    if (area.risk_level === 'Moderate') {
                        return '--popup-accent:#ffd166;--popup-bg:#fff4cf;--popup-color:#8a5a00;';
                    }
                    return '--popup-accent:#5F8F78;--popup-bg:rgba(95,143,120,.14);--popup-color:#1F4D3A;';
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
                                    <div class="map-popup-note">Showing: ${escapeHtml(layerTitle(layer))}</div>
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
                            ${riskBreakdown(area)}
                        </div>
                    `;
                };
                const riskBreakdown = (area) => {
                    if (!area.risk_components) return '';

                    const rows = Object.values(area.risk_components).map((component) => {
                        const percent = Math.round(Math.max(0, Math.min(1, Number(component.value) || 0)) * 100);

                        return `
                            <div class="mao-risk-breakdown-row">
                                <span class="mao-risk-breakdown-label">${escapeHtml(component.label)}</span>
                                <span class="mao-risk-breakdown-bar"><span class="mao-risk-breakdown-bar-fill" style="width:${percent}%;"></span></span>
                                <span class="mao-risk-breakdown-value">+${escapeHtml(String(component.weighted))}</span>
                            </div>
                        `;
                    }).join('');

                    return `
                        <div class="mao-risk-breakdown">
                            <div class="map-popup-label mb-2">How this risk score was calculated</div>
                            ${rows}
                            <div class="map-popup-note mt-2">Barangay exposure is an engineering-judgment estimate, not measured hazard data &mdash; treat it as a starting point, not a certainty.</div>
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

                // Leaflet sizes itself to its container at construction time; the map
                // starts inside a hidden tab panel, so it needs a resize nudge once
                // that panel actually becomes visible.
                document.querySelector('.dashboard-tab[data-tab="heatmap"]')?.addEventListener('click', () => {
                    setTimeout(() => map.invalidateSize(), 50);
                });
            }
        });
    </script>
</x-app-layout>
