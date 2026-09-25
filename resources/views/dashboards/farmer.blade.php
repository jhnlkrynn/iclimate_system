<x-app-layout>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Inter:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

    <style>
        .farmer-console {
            --fc-green-950: #123F32;
            --fc-green-900: #1F5A46;
            --fc-green-800: #1F5A46;
            --fc-green-700: #1F5A46;
            --fc-green-500: #5F8F78;
            --fc-green-400: #8FAF9A;
            --fc-green-200: #C9D8CE;
            --fc-green-100: rgba(143,175,154,.18);
            --fc-green-50:  #EEF3EF;
            --fc-sand:      #F4F6F2;
            --fc-sand-dark: #D8E0DA;
            --fc-accent:      #8FAF9A;
            --fc-accent-dark: #6F8978;
            --fc-accent-light:#C9D8CE;
            --fc-blue:      #4B7185;
            --fc-coral:     #B84A4A;
            --fc-warning:   #C58B2A;
            --fc-ink:       #1E2B25;
            --fc-ink-mid:   #4B5A50;
            --fc-ink-light: rgba(30,43,37,.78);
            --fc-border:    #D8E0DA;
            --radius-sm: 4px;
            --radius-md: 10px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --radius-pill: 100px;
            --shadow-sm: 0 1px 4px rgba(13,31,24,.08);
            --shadow-md: 0 6px 16px rgba(13,31,24,.10);
            --shadow-lg: 0 10px 26px rgba(13,31,24,.12);
            --shadow-accent: none;
            --ease: cubic-bezier(.4,0,.2,1);
            --text-xs: .75rem;
            --text-sm: .875rem;
            --text-base: 1rem;
            --text-md: 1.04rem;
            --text-lg: 1.125rem;
            --text-xl: 1.25rem;
            --text-2xl: 1.5rem;
            --text-3xl: 1.875rem;
            font-family: 'Inter', system-ui, sans-serif;
            font-size: var(--text-base);
            line-height: 1.58;
            color: var(--fc-ink);
        }
        .farmer-console h1, .farmer-console h2, .farmer-console h3 {
            font-family: 'DM Serif Display', Georgia, serif;
            font-weight: 400;
            letter-spacing: -0.01em;
            color: var(--fc-ink);
        }
        .farmer-console .mono { font-family: 'DM Mono', monospace; }

        /* -- EYEBROW -------------------------------------- */
        .fc-eyebrow {
            display: inline-flex; align-items: center; gap: 8px;
            font-family: 'DM Mono', monospace;
            font-size: var(--text-sm);
            font-weight: 500;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--fc-accent-light);
            margin-bottom: 14px;
        }
        .fc-eyebrow::before { content: none; }
        .fc-eyebrow.on-light { color: var(--fc-accent-light); }
        .fc-eyebrow.on-light::before { content: none; }

        /* -- BUTTONS -------------------------------------- */
        .fc-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 22px;
            border-radius: var(--radius-md);
            font-family: 'Inter', sans-serif;
            font-size: var(--text-sm);
            font-weight: 600;
            letter-spacing: .01em;
            transition: all .2s var(--ease);
            white-space: nowrap;
            border: 1.5px solid transparent;
        }
        .fc-btn-primary { background: var(--fc-green-700); color: #fff; box-shadow: none; }
        .fc-btn-primary:hover { background: var(--fc-green-950); color: #fff; transform: translateY(-1px); }
        .fc-btn-outline-light { border-color: #fff; color: #fff; }
        .fc-btn-outline-light:hover { background: #fff; border-color: #fff; color: var(--fc-ink); }
        .fc-btn-outline { border-color: var(--fc-green-700); color: var(--fc-green-700); background: transparent; }
        .fc-btn-outline:hover { background: var(--fc-green-700); border-color: var(--fc-green-700); color: #fff; }

        /* -- HERO ------------------------------------------ */
        .farmer-hero {
            position: relative;
            overflow: hidden;
            border-radius: var(--radius-xl);
            padding: 1.5rem 1.65rem;
            margin-bottom: 1.5rem;
            color: #fff;
            background: linear-gradient(145deg, rgba(18,63,50,.6) 0%, rgba(31,90,70,.5) 100%);
            backdrop-filter: var(--glass-blur, blur(22px) saturate(170%));
            -webkit-backdrop-filter: var(--glass-blur, blur(22px) saturate(170%));
            border: 1px solid rgba(255,255,255,.16);
            box-shadow: var(--shadow-md);
        }
        .farmer-hero::before {
            content: "";
            position: absolute; inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.05'/%3E%3C/svg%3E");
            pointer-events: none; opacity: .6;
        }
        .farmer-hero::after { content: none; }
        .farmer-hero > * { position: relative; z-index: 1; }
        .farmer-hero-grid {
            display: grid;
            grid-template-columns: minmax(420px, 1fr) minmax(360px, 448px);
            grid-template-areas:
                "copy actions"
                "copy weather";
            align-items: stretch;
            gap: 1rem 1.5rem;
        }
        .farmer-hero-copy {
            grid-area: copy;
            max-width: 760px;
            align-self: center;
            padding-bottom: 0;
        }
        .farmer-hero-copy .fc-eyebrow {
            font-size: var(--text-sm);
            letter-spacing: .08em;
            margin-bottom: .8rem;
        }
        .farmer-hero-copy .fc-eyebrow::before { content: none; }
        .farmer-hero-copy h1 {
            font-size: clamp(1.85rem, 3vw, 2.55rem);
            line-height: 1.08;
            max-width: 820px;
            margin-bottom: .9rem;
        }
        .farmer-hero-copy p {
            max-width: 760px;
            font-size: var(--text-base);
            line-height: 1.55;
        }
        .farmer-hero-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
            max-width: 780px;
            margin-top: 1.35rem;
        }
        .farmer-hero-summary-item {
            border: 1px solid rgba(255,255,255,.18);
            border-radius: var(--radius-md);
            background: rgba(255,255,255,.1);
            backdrop-filter: blur(8px);
            padding: .75rem .85rem;
        }
        .farmer-hero-summary-item span {
            display: block;
            color: var(--fc-accent-light);
            font-family: 'DM Mono', monospace;
            font-size: var(--text-sm);
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            margin-bottom: .32rem;
        }
        .farmer-hero-summary-item strong {
            display: block;
            color: #fff;
            font-size: 1.08rem;
            line-height: 1.35;
        }
        .farmer-hero-summary-item small {
            display: block;
            color: rgba(255,255,255,.8);
            font-size: var(--text-sm);
            line-height: 1.5;
            margin-top: .18rem;
        }
        .farmer-hero-side {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            align-items: stretch;
        }
        .farmer-hero-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .55rem;
            justify-content: flex-start;
            align-self: start;
        }
        .farmer-hero .weather-live-card { grid-area: weather; }
        .farmer-hero-actions { grid-area: actions; }
        .farmer-hero-actions .field-chip { flex: 1 1 100%; width: 100%; justify-content: center; }
        .farmer-hero h1 { color: #fff; font-size: clamp(1.7rem, 3.4vw, 2.5rem); margin-bottom: .35rem; }
        .farmer-hero h1 em { font-style: normal; color: #fff; }
        .farmer-hero p { color: rgba(255,255,255,.78); max-width: 640px; margin: 0; font-size: var(--text-base); line-height: 1.7; }
        .field-chip {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            border: 1px solid rgba(255,255,255,.2);
            border-radius: var(--radius-md);
            padding: .5rem .85rem;
            background: rgba(255,255,255,.12);
            backdrop-filter: blur(8px);
            color: rgba(255,255,255,.85);
            font-family: 'DM Mono', monospace;
            font-size: var(--text-sm);
            font-weight: 500;
            letter-spacing: .02em;
        }
        .field-pulse {
            width: 8px; height: 8px;
            border-radius: 999px;
            background: var(--fc-green-400);
            flex-shrink: 0;
        }

        /* -- SECTION HEADER --------------------------------- */
        .dashboard-section-label {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1rem;
            margin: 2rem 0 1rem;
        }
        .dashboard-section-label:first-of-type { margin-top: 0; }
        .section-title {
            color: var(--fc-accent-light);
            font-size: clamp(1.2rem, 2vw, 1.5rem);
            margin: 0;
        }
        .section-note { color: rgba(255,255,255,.74); font-size: var(--text-sm); margin: .2rem 0 0; line-height: 1.5; }
        .fc-section-link {
            display: inline-flex; align-items: center; gap: 6px;
            font-family: 'DM Mono', monospace;
            font-size: var(--text-sm);
            font-weight: 600;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: var(--fc-accent);
            white-space: nowrap;
        }
        .fc-section-link:hover { color: var(--fc-accent-light); }

        /* -- STAT / FIELD CARDS ------------------------------ */
        .climate-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            align-items: stretch;
            gap: .85rem;
            margin-bottom: .5rem;
        }
        .field-card {
            position: relative;
            border: 1px solid rgba(255,255,255,.55);
            border-radius: var(--radius-lg);
            background: rgba(255,255,255,.66);
            backdrop-filter: var(--glass-blur, blur(22px) saturate(170%));
            -webkit-backdrop-filter: var(--glass-blur, blur(22px) saturate(170%));
            padding: 1rem 1.1rem;
            text-decoration: none;
            display: flex; flex-direction: column; gap: .5rem;
            transition: transform .18s var(--ease), box-shadow .18s var(--ease), border-color .18s;
            width: 100%;
            text-align: left;
            font: inherit;
            cursor: pointer;
            box-shadow: var(--shadow-sm);
            min-height: 174px;
        }
        .field-card:hover { transform: translateY(-1px); box-shadow: var(--shadow-md); border-color: var(--fc-green-500); }
        .field-card:focus-visible { outline: 2px solid var(--fc-green-400); outline-offset: 2px; }
        .field-tap-hint {
            color: var(--fc-green-700);
            font-family: 'DM Mono', monospace;
            font-size: var(--text-xs);
            font-weight: 600;
            letter-spacing: .04em;
            text-transform: uppercase;
            opacity: 0;
            transform: translateY(2px);
            transition: opacity .2s var(--ease), transform .2s var(--ease);
        }
        .field-card:hover .field-tap-hint, .field-card:focus-visible .field-tap-hint { opacity: 1; transform: translateY(0); }

        /* -- STAT DETAIL MODALS ------------------------------- */
        .fc-stat-modal {
            --fc-green-950: #163B2D;
            --fc-green-900: #1F4D3A;
            --fc-green-800: #1F4D3A;
            --fc-green-700: #1F4D3A;
            --fc-green-400: #7FD6B5;
            --fc-accent: #8FAF9A;
            --fc-accent-dark: #6F8978;
            --fc-ink: #1F2937;
            --radius-lg: 18px;
            --radius-pill: 100px;
            --shadow-accent: none;
            --ease: cubic-bezier(.4,0,.2,1);
            --text-xs: .75rem;
            --text-sm: .875rem;
            --text-base: 1rem;
            --text-md: 1.04rem;
            --text-lg: 1.125rem;
            --text-xl: 1.25rem;
            --text-2xl: 1.5rem;
            --text-3xl: 1.875rem;
        }
        .fc-stat-modal.ic-drawer .ic-drawer-panel { width: min(560px, 100vw); }
        .fc-modal-content { background: linear-gradient(145deg, #ffffff, #f7fbf8); color: var(--fc-ink-mid); border: 1px solid var(--fc-sand-dark); border-radius: var(--radius-lg); box-shadow: 0 1.25rem 3rem rgba(13,31,24,.18); overflow: hidden; }
        .fc-modal-header { background: linear-gradient(90deg, var(--fc-green-50), var(--fc-green-100)); border-bottom: 1px solid var(--fc-border); color: var(--fc-ink); }
        .fc-modal-header .modal-title { color: var(--fc-ink); font-family: 'DM Serif Display', serif; }
        .fc-modal-body { overflow-y: auto; background: transparent; padding: 1rem; }
        .fc-modal-footer { border-top: 1px solid var(--fc-border); background: var(--fc-green-50); padding: .8rem 1rem; }
        .fc-modal-headline { font-family: 'DM Serif Display', serif; font-size: var(--text-2xl); color: var(--fc-ink); line-height: 1.18; }
        .fc-modal-sub { color: var(--fc-ink-mid); font-size: var(--text-sm); margin: .25rem 0 .75rem; }
        .fc-modal-note { color: var(--fc-ink-mid); font-size: var(--text-base); line-height: 1.58; margin-bottom: .85rem; }
        .fc-modal-table { color: var(--fc-ink); }
        .fc-modal-table.table { min-width: 620px; }
        .fc-modal-table thead th { background: var(--fc-green-50) !important; color: var(--fc-ink-mid) !important; font-size: var(--text-sm); text-transform: uppercase; letter-spacing: .04em; border-color: var(--fc-border); font-weight: 600; }
        .fc-modal-table td { color: var(--fc-ink) !important; border-color: var(--fc-border); font-size: var(--text-sm); line-height: 1.45; }
        .fc-modal-table-highlight { color: var(--fc-green-700) !important; font-weight: 700; }
        .field-icon {
            width: 32px; height: 32px;
            border-radius: 8px;
            display: grid; place-items: center;
            background: rgba(95,143,120,.14);
            border: 1px solid rgba(127,214,181,.35);
            color: var(--fc-green-700);
            flex-shrink: 0;
        }
        .field-icon svg { width: 18px; height: 18px; }
        .field-card-top { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
        .field-status {
            display: inline-flex; align-items: center;
            font-family: 'Inter', sans-serif;
            font-size: var(--text-xs);
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: none;
            padding: .22rem .55rem;
            border-radius: var(--radius-pill);
            background: rgba(95,143,120,.14);
            color: var(--fc-green-700);
            white-space: nowrap;
            flex-shrink: 0;
        }
        .field-status.is-live { background: rgba(143,175,154,.18); color: var(--fc-green-700); }
        .field-status.is-warn { background: rgba(216,91,69,.14); color: var(--fc-coral); }
        .field-label {
            color: var(--fc-ink-light);
            font-family: 'Inter', sans-serif;
            font-size: var(--text-sm);
            font-weight: 500;
            text-transform: none;
            letter-spacing: 0;
        }
        .field-value {
            font-family: 'DM Serif Display', serif;
            font-size: clamp(1.7rem, 2.4vw, 2rem);
            line-height: 1;
            color: var(--fc-ink);
            letter-spacing: -.02em;
        }
        .field-note { color: var(--fc-ink-mid); font-size: var(--text-sm); line-height: 1.5; }
        .field-source {
            color: var(--fc-green-700);
            font-family: 'DM Mono', monospace;
            font-size: var(--text-xs);
            font-weight: 600;
            letter-spacing: .04em;
            text-transform: uppercase;
            line-height: 1.35;
            margin-top: auto;
        }
        .field-source span { display: block; color: var(--fc-ink-light); margin-top: .1rem; }
        .weather-live-card {
            width: 100%;
            min-width: 0;
            border: 1px solid rgba(255,255,255,.6);
            border-radius: var(--radius-lg);
            background: rgba(255,255,255,.82);
            backdrop-filter: var(--glass-blur-strong, blur(30px) saturate(180%));
            -webkit-backdrop-filter: var(--glass-blur-strong, blur(30px) saturate(180%));
            padding: 1rem;
            box-shadow: 0 10px 34px rgba(8,25,19,.22);
        }
        .weather-live-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            margin-bottom: .75rem;
        }
        .weather-live-status {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            color: var(--fc-green-700);
            font-family: 'Inter', sans-serif;
            font-size: var(--text-sm);
            font-weight: 700;
            letter-spacing: 0;
            text-transform: none;
        }
        .weather-live-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--fc-green-500);
        }
        .weather-refresh-btn {
            border: 1px solid var(--fc-border);
            background: #fff;
            color: var(--fc-green-700);
            border-radius: var(--radius-md);
            padding: .35rem .65rem;
            font-size: var(--text-sm);
            font-weight: 700;
        }
        .weather-refresh-btn:disabled { opacity: .65; cursor: wait; }
        .weather-live-main {
            display: grid;
            grid-template-columns: auto 1fr;
            align-items: center;
            gap: .9rem;
        }
        .weather-live-icon {
            width: 72px;
            height: 72px;
            border-radius: 18px;
        }
        .weather-live-temp {
            color: var(--fc-ink);
            font-family: 'DM Serif Display', serif;
            font-size: 2.35rem;
            line-height: 1;
        }
        .weather-live-condition { color: var(--fc-green-700); font-weight: 800; font-size: 1.08rem; }
        .weather-live-meta { color: var(--fc-ink-mid); font-size: var(--text-sm); margin-top: .3rem; line-height: 1.5; }
        .weather-mini-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .55rem;
            margin-top: .85rem;
        }
        .weather-mini {
            border-radius: var(--radius-md);
            background: var(--fc-green-50);
            padding: .6rem;
        }
        .weather-mini span { display: block; color: var(--fc-ink-mid); font-size: var(--text-xs); text-transform: uppercase; letter-spacing: .04em; line-height: 1.35; }
        .weather-mini strong { display: block; color: var(--fc-ink); font-size: var(--text-base); margin-top: .1rem; line-height: 1.25; }
        .forecast-strip {
            display: grid;
            grid-template-columns: repeat(7, minmax(96px, 1fr));
            gap: .7rem;
            margin-top: 1rem;
        }
        .forecast-day {
            border: 1px solid rgba(255,255,255,.55);
            border-radius: var(--radius-md);
            background: rgba(255,255,255,.6);
            backdrop-filter: blur(10px) saturate(150%);
            padding: .7rem;
            text-align: center;
        }
        .forecast-day img { width: 34px; height: 34px; display: block; margin: .25rem auto; }
        .forecast-day strong { display: block; color: var(--fc-ink); font-size: var(--text-sm); font-weight: 700; line-height: 1.3; }
        .forecast-day span { display: block; color: var(--fc-ink-mid); font-size: var(--text-sm); line-height: 1.42; }

        /* -- PANELS ------------------------------------------ */
        .farmer-panel {
            border: 1px solid rgba(255,255,255,.55);
            border-radius: var(--radius-lg);
            background: rgba(255,255,255,.66);
            backdrop-filter: var(--glass-blur, blur(22px) saturate(170%));
            -webkit-backdrop-filter: var(--glass-blur, blur(22px) saturate(170%));
            box-shadow: var(--glass-light-shadow, 0 10px 34px rgba(8,25,19,.16));
            overflow: hidden;
        }
        .farmer-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.15rem 1.25rem 1rem;
            border-bottom: 1px solid var(--fc-border);
        }
        h2.farmer-panel-title { font-size: var(--text-lg); margin: 0; color: var(--fc-ink); }
        .farmer-panel-sub { margin: .25rem 0 0; color: var(--fc-ink-mid); font-size: var(--text-sm); line-height: 1.5; font-family: 'Inter', sans-serif; }
        .farmer-panel-body { padding: 1.25rem; }
        .farmer-list-item {
            display: flex;
            gap: .9rem;
            align-items: flex-start;
            padding: .85rem 0;
        }
        .farmer-list-item + .farmer-list-item { border-top: 1px solid var(--fc-border); }
        .list-mark {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: rgba(95,143,120,.14);
            border: 1px solid rgba(127,214,181,.3);
            color: var(--fc-green-700);
            font-family: 'DM Mono', monospace;
            font-weight: 700;
            font-size: var(--text-xs);
            flex: 0 0 auto;
        }
        .list-title { font-weight: 700; color: var(--fc-ink); line-height: 1.3; font-size: var(--text-base); }
        .list-text { color: var(--fc-ink-mid); font-size: var(--text-sm); margin-top: .2rem; line-height: 1.55; }
        .list-meta { color: var(--fc-ink-mid); font-family: 'DM Mono', monospace; font-size: var(--text-xs); margin-top: .4rem; font-weight: 500; text-transform: uppercase; letter-spacing: .04em; line-height: 1.4; }
        .fc-risk-badge {
            display: inline-flex;
            align-items: center;
            margin-left: .5rem;
            padding: .15rem .55rem;
            border-radius: var(--radius-pill);
            font-family: 'DM Mono', monospace;
            font-size: var(--text-xs);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .03em;
            vertical-align: middle;
            white-space: nowrap;
        }
        .fc-risk-badge-severe { background: rgba(239,68,68,.16); color: #b91c1c; }
        .fc-risk-badge-high { background: rgba(249,115,22,.16); color: #c2410c; }
        .fc-risk-badge-moderate { background: rgba(250,204,21,.22); color: #854d0e; }
        .fc-risk-badge-low { background: rgba(34,197,94,.16); color: #15803d; }
        .fc-risk-interpretation { font-weight: 500; color: var(--fc-ink); }
        .status-pill {
            display: inline-flex;
            align-items: center;
            border-radius: var(--radius-pill);
            padding: .32rem .62rem;
            font-family: 'DM Mono', monospace;
            font-size: var(--text-xs);
            font-weight: 600;
            letter-spacing: .03em;
            text-transform: uppercase;
            background: rgba(95,143,120,.14);
            color: var(--fc-green-700);
        }
        .status-pill.muted { background: var(--fc-green-50); color: var(--fc-ink-light); }
        .status-pill.warn { background: rgba(216,91,69,.14); color: var(--fc-coral); }
        .status-pill.info { background: rgba(47,111,143,.14); color: var(--fc-blue); }
        .status-pill.neutral { background: rgba(255,255,255,.7); color: #173f31; }
        .advisory-card {
            padding: 1.1rem 1.2rem;
            border-radius: var(--radius-md);
            border: 1px solid rgba(255,255,255,.5);
            background: rgba(238,243,239,.55);
            backdrop-filter: blur(8px) saturate(150%);
            min-height: 100%;
        }
        .advisory-card strong { color: var(--fc-ink); display: block; margin: .5rem 0 .35rem; font-size: var(--text-base); line-height: 1.35; }
        .advisory-feedback { display: flex; align-items: center; gap: .5rem; margin-top: .75rem; padding-top: .65rem; border-top: 1px solid var(--fc-border); }
        .advisory-feedback-label { color: var(--fc-ink-light); font-size: var(--text-xs); margin-right: .15rem; }
        .advisory-feedback-btn { border: 1px solid var(--fc-border); background: rgba(255,255,255,.6); color: var(--fc-ink-mid); border-radius: var(--radius-pill); padding: .3rem .75rem; font-size: var(--text-xs); font-weight: 600; display: inline-flex; align-items: center; gap: .3rem; }
        .advisory-feedback-btn.active-yes { background: rgba(95,143,120,.18); border-color: var(--fc-green-500); color: var(--fc-green-700); }
        .advisory-feedback-btn.active-no { background: rgba(184,74,74,.14); border-color: var(--fc-coral); color: var(--fc-coral); }
        .advisory-feedback-status { font-size: var(--text-xs); color: var(--fc-ink-light); margin-left: auto; }

        /* -- HIGHLIGHTS STRIP ------------------------------------ */
        .fc-highlights { display: flex; flex-direction: column; gap: .6rem; margin-bottom: 1.1rem; }
        .fc-highlight { display: flex; align-items: flex-start; gap: .65rem; border-radius: var(--radius-lg); padding: .8rem 1rem; border: 1px solid rgba(255,255,255,.55); background: rgba(255,255,255,.72); backdrop-filter: blur(16px) saturate(170%); box-shadow: var(--shadow-sm); }
        .fc-highlight-critical { border-left: 5px solid var(--fc-coral); }
        .fc-highlight-warning { border-left: 5px solid var(--fc-warning); }
        .fc-highlight-info { border-left: 5px solid var(--fc-blue); }
        .fc-highlight-dot { flex: 0 0 auto; width: 9px; height: 9px; border-radius: 999px; margin-top: .4rem; }
        .fc-highlight-critical .fc-highlight-dot { background: var(--fc-coral); }
        .fc-highlight-warning .fc-highlight-dot { background: var(--fc-warning); }
        .fc-highlight-info .fc-highlight-dot { background: var(--fc-blue); }
        .fc-highlight-title { color: var(--fc-ink); font-weight: 700; font-size: var(--text-base); }
        .fc-highlight-message { color: var(--fc-ink-mid); font-size: var(--text-sm); margin-top: .1rem; }

        /* -- PREDICTION / ORGANIZATION CARDS ------------------------------------ */
        .fc-yoy-badge { display: inline-flex; align-items: center; gap: .3rem; border-radius: var(--radius-pill); padding: .25rem .65rem; font-family: 'DM Mono', monospace; font-size: var(--text-xs); font-weight: 700; }
        .fc-yoy-up { background: rgba(184,74,74,.14); color: var(--fc-coral); }
        .fc-yoy-down { background: rgba(95,143,120,.16); color: var(--fc-green-700); }
        .fc-yoy-flat { background: rgba(100,116,139,.14); color: var(--fc-ink-light); }
        .fc-prediction-value { font-family: 'DM Serif Display', serif; font-size: var(--text-3xl); color: var(--fc-ink); line-height: 1; }
        .fc-prediction-note { color: var(--fc-ink-mid); font-size: var(--text-sm); margin-top: .5rem; line-height: 1.55; }
        .fc-prediction-limitation { display: flex; gap: .5rem; margin-top: .85rem; padding: .65rem .8rem; border-radius: var(--radius-md); background: var(--fc-green-50); border: 1px solid var(--fc-border); color: var(--fc-ink-mid); font-size: var(--text-xs); line-height: 1.5; }
        .fc-risk-explainer { margin-top: .75rem; padding-top: .6rem; border-top: 1px solid var(--fc-border); }
        .fc-risk-explainer summary { cursor: pointer; color: var(--fc-green-700); font-size: var(--text-sm); font-weight: 600; }
        .fc-risk-bar-row { display: flex; align-items: center; gap: .5rem; margin: .5rem 0; font-size: var(--text-xs); }
        .fc-risk-bar-label { flex: 0 0 150px; color: var(--fc-ink-mid); }
        .fc-risk-bar { flex: 1 1 auto; height: 6px; border-radius: 999px; background: rgba(95,143,120,.14); overflow: hidden; }
        .fc-risk-bar-fill { height: 100%; background: var(--fc-blue); border-radius: 999px; }

        /* -- QUICK ACTIONS ------------------------------------ */
        .quick-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .85rem; }
        .quick-action {
            min-height: 108px;
            padding: 1.1rem 1.2rem;
            border-radius: var(--radius-md);
            border: 1px solid rgba(255,255,255,.5);
            background: rgba(238,243,239,.55);
            backdrop-filter: blur(8px) saturate(150%);
            color: inherit;
            text-decoration: none;
            display: block;
            transition: transform .2s var(--ease), box-shadow .2s var(--ease), border-color .2s, background .2s;
        }
        .quick-action:hover { transform: translateY(-3px); box-shadow: var(--shadow-sm); border-color: var(--fc-green-500); background: rgba(143,175,154,.32); }
        .quick-action strong { display: block; color: var(--fc-ink); font-size: var(--text-base); font-weight: 700; }
        .quick-action span { display: block; color: var(--fc-ink-mid); font-size: var(--text-sm); margin-top: .3rem; line-height: 1.5; }
        .empty-soft {
            border: 1.5px dashed var(--fc-sand-dark);
            background: rgba(238,243,239,.55);
            backdrop-filter: blur(8px) saturate(150%);
            border-radius: var(--radius-md);
            padding: 1.75rem;
            text-align: center;
        }
        .empty-soft strong { font-family: 'DM Serif Display', serif; font-weight: 400; font-size: var(--text-lg); color: var(--fc-ink); }

        /* -- PRIORITY TOOLS (role-card style) ------------------ */
        .priority-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: .5rem; }
        .priority-card {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            min-height: 160px;
            padding: 1.4rem 1.3rem;
            border: 1.5px solid rgba(255,255,255,.55);
            border-radius: var(--radius-xl);
            background: rgba(255,255,255,.66);
            backdrop-filter: var(--glass-blur, blur(22px) saturate(170%));
            -webkit-backdrop-filter: var(--glass-blur, blur(22px) saturate(170%));
            box-shadow: var(--glass-light-shadow, 0 10px 34px rgba(8,25,19,.16));
            text-decoration: none;
            color: inherit;
            transition: box-shadow .25s var(--ease), transform .25s var(--ease), border-color .25s, background .25s;
        }
        .priority-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); border-color: var(--fc-green-700); }
        .priority-card strong { display: block; color: var(--fc-ink); font-family: 'DM Serif Display', serif; font-weight: 400; font-size: 1.1rem; margin-bottom: .4rem; }
        .priority-card span.desc { display: block; color: var(--fc-ink-mid); font-size: var(--text-sm); line-height: 1.55; font-family: 'Inter', sans-serif; }
        .priority-card .status-pill { align-self: flex-start; }
        .priority-card.priority-highlight .status-pill { background: var(--fc-green-100); color: var(--fc-green-700); }
        .priority-icon {
            width: 44px; height: 44px;
            border-radius: 50%;
            display: grid; place-items: center;
            background: rgba(95,143,120,.14);
            border: 1px solid rgba(127,214,181,.35);
            color: var(--fc-green-700);
            flex-shrink: 0;
        }
        .priority-card.priority-highlight .priority-icon { background: rgba(143,175,154,.16); border-color: rgba(143,175,154,.4); color: var(--fc-green-700); }

        /* -- DASHBOARD TABS --------------------------------- */
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
            color: rgba(255,255,255,.8);
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
        .dashboard-tab.active { color: #fff; border-bottom-color: var(--fc-accent); }
        .dashboard-tab-panel { display: none; }
        .dashboard-tab-panel.active { display: block; animation: icPageEnter var(--ic-motion-slow, 420ms) both; }
        .dashboard-tab-panel > .row:last-child, .dashboard-tab-panel > .farmer-panel:last-child { margin-bottom: 0 !important; }
        /* -- SIMPLIFIED FARMER DASHBOARD ------------------- */
        .fc-simple-panel { margin-bottom: 1.25rem; }
        .fc-simple-row { margin-bottom: 1.25rem; }
        .farmer-hero-grid { grid-template-areas: "copy weather" "actions weather"; }
        .forecast-strip { grid-template-columns: repeat(auto-fit, minmax(108px, 1fr)); margin-top: 0; }
        .fc-forecast-empty { grid-column: 1 / -1; }
        .fc-risk-block { margin-top: .5rem; }
        .fc-risk-caveat { margin: .35rem 0 0; color: var(--fc-ink-mid); font-size: var(--text-sm); line-height: 1.5; }
        .fc-details { margin-top: .75rem; border-top: 1px solid var(--fc-border); padding-top: .6rem; }
        .fc-details-first { margin-top: 0; border-top: 0; padding-top: 0; }
        .fc-details > summary { cursor: pointer; color: var(--fc-green-700); font-weight: 600; font-size: var(--text-base); padding: .35rem 0; }
        .fc-details-body { padding-top: .6rem; }
        .fc-details-body .field-note { margin-bottom: .35rem; }
        .fc-details .climate-grid { grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); }
        .fc-map-wrap { margin-top: .85rem; min-width: 0; overflow-x: auto; }
        @media (max-width: 767.98px) {
            .farmer-hero-grid { grid-template-areas: "copy" "weather" "actions"; }
            .fc-simple-panel, .fc-simple-row { margin-bottom: .9rem; }
        }
        .list-compact .farmer-list-item:nth-of-type(n+4), .list-compact .row > [class*="col-"]:nth-child(n+5) { display: none; }

        @media (max-width: 1399.98px) {
            .farmer-hero-grid { grid-template-columns: minmax(0, 1fr) minmax(340px, 420px); }
            .climate-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
        @media (max-width: 1199.98px) { .priority-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 767.98px) {
            .farmer-hero-grid { grid-template-columns: 1fr; }
            .quick-grid, .priority-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .farmer-hero-grid {
                grid-template-areas:
                    "copy"
                    "actions"
                    "weather";
            }
            .farmer-hero-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .farmer-hero-actions .field-chip { flex: 1 1 100%; width: 100%; justify-content: flex-start; }
            .climate-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .weather-mini-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .forecast-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .5rem; margin-top: 0; }
            .farmer-hero { padding: 1.1rem 1rem; margin-bottom: 1rem; }
            .farmer-hero h1 { margin-bottom: .2rem; }
            .fc-eyebrow { margin-bottom: .6rem; }
            .fc-modal-table.table { min-width: 560px; }
            .farmer-panel-header, .dashboard-section-label { align-items: flex-start; flex-direction: column; }

            /* -- tighter section rhythm on small screens -- */
            .dashboard-section-label { margin: 1.15rem 0 .6rem; gap: .35rem; }
            .dashboard-section-label:first-of-type { margin-top: 0; }
            .section-note { margin-top: .1rem; }
            .climate-grid { gap: .6rem; margin-bottom: .35rem; padding-bottom: .25rem; }
            .field-card { min-height: 0; padding: .8rem .75rem; gap: .35rem; }
            .field-icon { width: 28px; height: 28px; }
            .field-status { white-space: normal; text-align: right; justify-content: flex-end; }
            .field-value { font-size: 1.55rem; }
            .weather-live-card { padding: .8rem; }
            .weather-live-head { margin-bottom: .55rem; }
            .weather-live-icon { width: 56px; height: 56px; }
            .weather-live-temp { font-size: 1.6rem; }
            .weather-mini-grid { gap: .4rem; margin-top: .6rem; }
            .weather-mini { padding: .45rem .55rem; }
            .forecast-day { padding: .5rem; }
            .farmer-panel-header { padding: .85rem .95rem .7rem; gap: .5rem; }
            .farmer-panel-body { padding: .9rem; }
            .dashboard-tab { margin-right: 1rem; font-size: .84rem; }
            .dashboard-tab-panel .row.g-4 { --bs-gutter-y: .9rem; }
            .priority-grid, .quick-grid { gap: .6rem; }
            .priority-card { min-height: 0; padding: 1rem 1.05rem; gap: .65rem; }
            .quick-action { min-height: 72px; padding: .8rem .95rem; }
            .advisory-card { padding: .85rem .95rem; }
            .farmer-list-item { padding: .65rem 0; gap: .7rem; }
            .empty-soft { padding: 1.25rem; }
        }
        @media (max-width: 380px) {
            .quick-grid, .priority-grid { grid-template-columns: 1fr; }
            .field-card { padding: .75rem .68rem; }
            .field-label, .field-note { font-size: .82rem; }
            .field-value { font-size: 1.42rem; }
        }
    </style>

    @php
        $unreadNotifications = $unreadNotificationCount ?? $notifications->where('is_read', false)->count();
        $latestAdvisory = $advisories->first();
        $profile = auth()->user()->farmerProfile;
        $weatherTimezone = config('services.weather.timezone', 'Asia/Manila');
        $weatherNow = now($weatherTimezone);
        $weatherPayload = $dashboardWeather ?? [];
        $currentWeather = $weatherPayload['current'] ?? [];
        $todayWeather = $weatherPayload['today'] ?? [];
        $weatherSource = $weatherPayload['provider'] ?? null;
        $weatherSourceDisplay = ($weatherSource === null || $weatherSource === 'Unavailable')
            ? 'Weather temporarily unavailable'
            : (($weatherPayload['stale'] ?? false) ? 'Saved weather' : 'Live weather');
        $weatherFetchedAt = $weatherPayload['fetched_at'] ?? null;
        $weatherFetchedLabel = $weatherFetchedAt instanceof \Illuminate\Support\Carbon
            ? $weatherFetchedAt->timezone($weatherTimezone)->format('M j, Y').' • '.$weatherFetchedAt->timezone($weatherTimezone)->format('g:i A')
            : ($weatherPayload['fetched_at_label'] ?? 'Weather temporarily unavailable');
        $weatherCheckedAt = $dashboardWeatherResponse['checked_at'] ?? null;
        $weatherCheckedLabel = $dashboardWeatherResponse['checked_at_label'] ?? $weatherNow->format('M j, Y').' - '.$weatherNow->format('g:i A');
        $weatherStatusLabel = ($weatherPayload['stale'] ?? false) ? 'Latest available' : 'Near-real-time';
        $weatherStatusShort = ($weatherPayload['success'] ?? false) && ! ($weatherPayload['stale'] ?? false) ? 'Live' : 'Stored';
        $temperatureValue = $currentWeather['temperature'] ?? null;
        $feelsLikeValue = $currentWeather['feels_like'] ?? null;
        $humidityValue = $currentWeather['humidity'] ?? null;
        $todayRainfallValue = $todayWeather['rainfall'] ?? null;
        $currentPrecipitationValue = $currentWeather['precipitation'] ?? $currentWeather['rain'] ?? null;
        $rainfallValue = $todayRainfallValue ?? $currentPrecipitationValue ?? $climateSummary?->rainfall;
        $windSpeedValue = $currentWeather['wind_speed'] ?? null;
        $weatherCondition = $currentWeather['condition'] ?? 'Weather temporarily unavailable';
        $weatherIcon = $currentWeather['icon'] ?? $weatherPayload['icon'] ?? '/images/weather/unavailable.svg';
        $weatherGuidance = $weatherGuidance ?? ['title' => 'iClimate Weather Guidance', 'message' => 'Weather guidance is temporarily unavailable.'];
        $weatherNoteLabel = ($weatherPayload['success'] ?? false)
            ? 'Current weather for Lian, Batangas'
            : 'Weather data temporarily unavailable';
        $weatherSourceLine = 'Source: '.$weatherSourceDisplay;
        $weatherUpdateLine = $weatherFetchedLabel;
        $weatherTimingLabel = ($weatherPayload['success'] ?? false) ? 'Current weather reading' : 'Latest available weather reading';
        $weatherDisplayDate = $weatherFetchedAt instanceof \Illuminate\Support\Carbon
            ? $weatherFetchedAt->timezone($weatherTimezone)->format('M d, Y')
            : $climateSummary?->record_date?->format('M d, Y');
        $weatherForecastDays = $weatherPayload['forecast'] ?? [];
        $weatherFetchedIso = $dashboardWeatherResponse['fetched_at'] ?? ($weatherFetchedAt instanceof \Illuminate\Support\Carbon ? $weatherFetchedAt->toIso8601String() : null);

        $aiPrefillQuestion = 'It\'s currently '.strtolower((string) $weatherCondition)
            .($temperatureValue !== null ? ', '.number_format((float) $temperatureValue, 1).'°C' : '')
            .' in '.($farmerBarangay ?: 'Lian, Batangas')
            .($latestAdvisory ? '. There is a recent advisory: "'.$latestAdvisory->title.'"' : '')
            .'. What should I do for my farm today?';
    @endphp

    @php
        $riskLevelRaw = $farmerBarangayRisk['risk_level'] ?? null;
        $riskHasData = $farmerBarangayRisk && $riskLevelRaw && $riskLevelRaw !== 'No Data';
        $riskBadgeClass = ! $riskHasData ? 'muted' : (in_array($riskLevelRaw, ['High', 'Severe'], true) ? 'warn' : ($riskLevelRaw === 'Moderate' ? 'info' : 'neutral'));
        $riskBadgeText = $riskHasData ? $riskLevelRaw.' Farm Risk' : 'No Data';
        $riskMeaning = ! $riskHasData
            ? 'Not enough information is available yet to rate the farm risk for your barangay.'
            : (in_array($riskLevelRaw, ['High', 'Severe'], true)
                ? 'Weather and past harvests suggest a higher chance of crop trouble in your barangay.'
                : ($riskLevelRaw === 'Moderate'
                    ? 'Some conditions in your barangay need watching.'
                    : 'Conditions in your barangay look manageable for now.'));
        $riskAction = $farmerBarangayRisk['advisory']['planting'] ?? null;
        $topHighlights = collect($farmerHighlights ?? [])->take(2);
        $warningCount = (int) ($activeWeatherAlerts ?? 0);
        $hasWarning = $warningCount > 0 || $topHighlights->contains(fn ($h) => in_array($h['severity'] ?? '', ['critical', 'warning'], true));
        $yieldPrediction = $farmerYieldPrediction;
        $yieldReliability = $yieldPrediction ? \App\Support\PredictionWording::reliability($yieldPrediction['api_confidence'] ?? $yieldPrediction['confidence'] ?? null) : null;
    @endphp

    <div class="farmer-console">
        {{-- 1. Today's Weather --}}
        <section class="farmer-hero" data-reveal="fade-up" data-reveal-duration="600">
            <div class="farmer-hero-grid">
                <div class="farmer-hero-copy">
                    <div class="fc-eyebrow on-light">Farmer Dashboard</div>
                    <h1>Today in Lian, Batangas</h1>
                    <p>
                        {{ $weatherCondition }}@if($temperatureValue !== null), {{ number_format((float) $temperatureValue, 1) }}°C @endif.
                        @if($todayRainfallValue !== null && (float) $todayRainfallValue >= 10)
                            Heavy rain is expected today. Check your fields and drainage.
                        @elseif($todayRainfallValue !== null && (float) $todayRainfallValue > 0)
                            Some rain is expected today.
                        @elseif($todayRainfallValue !== null)
                            No rain is expected today.
                        @else
                            Rain information is not available yet.
                        @endif
                    </p>
                </div>
                <div class="weather-live-card" data-weather-root data-weather-url="{{ route('farmer.dashboard.weather') }}" data-reveal="fade-right" data-reveal-delay="200" data-reveal-duration="600">
                    <div class="weather-live-head">
                        <span class="weather-live-status"><span class="weather-live-dot"></span>Today&apos;s Weather</span>
                        <button type="button" class="weather-refresh-btn" data-weather-refresh>Refresh</button>
                    </div>
                    <div class="weather-live-main">
                        <img class="weather-live-icon" src="{{ $weatherIcon }}" alt="{{ $weatherCondition }}" data-weather-icon>
                        <div>
                            <div class="weather-live-condition" data-weather-condition>{{ $weatherCondition }}</div>
                            <div class="weather-live-temp" data-weather-temperature>{{ $temperatureValue !== null ? number_format((float) $temperatureValue, 1).'°C' : 'N/A' }}</div>
                            <div class="weather-live-meta">
                                <strong>Lian, Batangas</strong><br>
                                Feels like <span data-weather-feels-like>{{ $feelsLikeValue !== null ? number_format((float) $feelsLikeValue, 1).'°C' : 'N/A' }}</span><br>
                                <span data-weather-provider>{{ $weatherSourceDisplay }}</span> &middot;
                                <span data-weather-freshness>{{ $weatherFetchedIso ? 'Near-real-time weather data' : 'Weather timestamp unavailable' }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="weather-mini-grid">
                        <div class="weather-mini"><span>Humidity</span><strong data-weather-humidity-mini>{{ $humidityValue !== null ? number_format((float) $humidityValue, 0).'%' : 'N/A' }}</strong></div>
                        <div class="weather-mini"><span>Today&apos;s Rain</span><strong data-weather-rain-mini>{{ $todayRainfallValue !== null ? number_format((float) $todayRainfallValue, 1).' mm' : 'N/A' }}</strong></div>
                        <div class="weather-mini"><span>Wind</span><strong data-weather-wind-mini>{{ $windSpeedValue !== null ? number_format((float) $windSpeedValue, 1).' km/h' : 'N/A' }}</strong></div>
                    </div>
                </div>
                <div class="farmer-hero-actions">
                    <span class="field-chip"><span class="field-pulse"></span> <span data-current-date>{{ $weatherNow->format('l, F j, Y') }} {{ $weatherNow->format('g:i:s A') }}</span></span>
                </div>
            </div>
        </section>

        {{-- 2. Upcoming Weather --}}
        <div class="farmer-panel fc-simple-panel" data-reveal="fade-up">
            <div class="farmer-panel-header">
                <div>
                    <h2 class="farmer-panel-title">Upcoming Weather</h2>
                    <p class="farmer-panel-sub">Expected weather for the next few days.</p>
                </div>
            </div>
            <div class="farmer-panel-body">
                <section class="forecast-strip" data-weather-forecast>
                    @forelse($weatherForecastDays as $forecastDay)
                        <div class="forecast-day"
                             data-tmax="{{ $forecastDay['temperature_max'] ?? '' }}"
                             data-tmin="{{ $forecastDay['temperature_min'] ?? '' }}"
                             data-pop="{{ $forecastDay['precipitation_probability'] ?? '' }}">
                            <strong>{{ $forecastDay['day'] ?? '' }}</strong>
                            <img src="{{ $forecastDay['icon'] ?? '/images/weather/unavailable.svg' }}" alt="{{ $forecastDay['condition'] ?? 'Forecast' }}">
                            <span>{{ $forecastDay['condition'] ?? 'N/A' }}</span>
                            <span>{{ isset($forecastDay['temperature_max'], $forecastDay['temperature_min']) ? number_format((float) $forecastDay['temperature_max'], 0).' / '.number_format((float) $forecastDay['temperature_min'], 0).'°C' : 'N/A' }}</span>
                            <span>{{ isset($forecastDay['precipitation_probability']) ? number_format((float) $forecastDay['precipitation_probability'], 0).'%' : 'N/A' }} rain chance</span>
                        </div>
                    @empty
                        <div class="empty-soft fc-forecast-empty"><strong>Forecast Not Available Yet</strong><div class="small mt-1" style="color: var(--fc-ink-light);">Please check again later.</div></div>
                    @endforelse
                </section>
            </div>
        </div>

        {{-- 3. Important Farm Warning --}}
        <div class="farmer-panel fc-simple-panel" id="risk-map" data-reveal="fade-up">
            <div class="farmer-panel-header">
                <div>
                    <h2 class="farmer-panel-title">Important Farm Warning</h2>
                    <p class="farmer-panel-sub">{{ $hasWarning ? 'Please read this first.' : 'Nothing urgent right now.' }}</p>
                </div>
                <span class="status-pill {{ $hasWarning ? 'warn' : 'neutral' }}">{{ $hasWarning ? 'Needs Attention' : 'No Warning' }}</span>
            </div>
            <div class="farmer-panel-body">
                @if($topHighlights->isNotEmpty() || $warningCount > 0)
                    <section class="fc-highlights" aria-label="Warnings">
                        @foreach($topHighlights as $highlight)
                            <div class="fc-highlight fc-highlight-{{ $highlight['severity'] }}">
                                <span class="fc-highlight-dot"></span>
                                <div>
                                    <div class="fc-highlight-title">{{ $highlight['title'] }}</div>
                                    <div class="fc-highlight-message">{{ $highlight['message'] }}</div>
                                </div>
                            </div>
                        @endforeach
                        @if($warningCount > 0)
                            <div class="fc-highlight fc-highlight-warning">
                                <span class="fc-highlight-dot"></span>
                                <div>
                                    <div class="fc-highlight-title">{{ number_format($warningCount) }} weather {{ \Illuminate\Support\Str::plural('warning', $warningCount) }} active</div>
                                    <div class="fc-highlight-message"><a href="{{ route('notifications.index') }}">Read your notifications</a></div>
                                </div>
                            </div>
                        @endif
                    </section>
                @endif

                @if($farmerBarangay)
                    <div class="fc-risk-block">
                        <div class="field-card-top">
                            <div class="list-title">Your Barangay: {{ $farmerBarangay }}</div>
                            <span class="status-pill {{ $riskBadgeClass }}">{{ $riskBadgeText }}</span>
                        </div>
                        <p class="fc-prediction-note">{{ $riskMeaning }}@if($riskHasData && $riskAction) <strong>What to do:</strong> {{ $riskAction }}@endif</p>
                        <p class="fc-risk-caveat">Farm Risk is a planning guide only. It is not yet checked against official hazard data.</p>

                        <details class="fc-details">
                            <summary>View Details</summary>
                            <div class="fc-details-body">
                                @if($farmerFarmBoundary)
                                    <div class="field-note">Your farm outline is saved ({{ number_format((float) $farmerFarmBoundary->calculated_area_hectares, 2) }} ha).</div>
                                @else
                                    <div class="field-note">No farm boundary recorded yet for your profile.</div>
                                @endif
                                @if($farmerBarangayRisk)
                                    <div class="field-note">Farm Risk: {{ $farmerBarangayRisk['risk_level'] ?? 'No Data' }}</div>
                                    @if($riskMainConcern = data_get($farmerBarangayRisk, 'advisory.warnings.0'))
                                        <div class="field-note">Main concern: {{ $riskMainConcern }}</div>
                                    @endif
                                @endif
                                @if($farmerBarangayRisk && $farmerBarangayRisk['updated_at'])
                                    <div class="field-note">Last updated: {{ \Illuminate\Support\Carbon::parse($farmerBarangayRisk['updated_at'])->format('M j, Y g:i A') }}</div>
                                @endif
                                @if($farmerBarangayRisk && ($farmerBarangayRisk['weather']['status'] ?? null) === 'fresh')
                                    <div class="field-note">
                                        @if($farmerBarangayRisk['weather']['icon'] ?? null)
                                            <img class="farmer-weather-icon" src="{{ $farmerBarangayRisk['weather']['icon'] }}" alt="{{ $farmerBarangayRisk['weather']['condition'] ?? 'Weather icon' }}" width="34" height="34" loading="lazy">{{ $farmerBarangayRisk['weather']['condition'] ?? '' }} &middot;
                                        @endif
                                        Weather now (for information only; humidity and wind do not change the Farm Risk rating):
                                        @if($farmerBarangayRisk['weather']['rainfall_24h_mm'] !== null) Rain expected in the next 24 hours: {{ $farmerBarangayRisk['weather']['rainfall_24h_mm'] }} mm &middot; @endif
                                        @if($farmerBarangayRisk['weather']['temperature_c'] !== null) Temp: {{ $farmerBarangayRisk['weather']['temperature_c'] }}°C &middot; @endif
                                        @if($farmerBarangayRisk['weather']['humidity_percent'] !== null) Humidity: {{ $farmerBarangayRisk['weather']['humidity_percent'] }}% &middot; @endif
                                        @if($farmerBarangayRisk['weather']['wind_speed_kmh'] !== null) Wind: {{ $farmerBarangayRisk['weather']['wind_speed_kmh'] }} km/h @endif
                                    </div>
                                @else
                                    <div class="field-note">Weather information is temporarily unavailable for your barangay.</div>
                                @endif
                                @if($farmerBarangayRisk && $farmerBarangayRisk['last_recorded_yield'])
                                    <div class="field-note">Last recorded yield: {{ $farmerBarangayRisk['last_recorded_yield']['value'] }} t/ha ({{ $farmerBarangayRisk['last_recorded_yield']['season'] }} {{ $farmerBarangayRisk['last_recorded_yield']['year'] }})</div>
                                @endif
                                @if($farmerBarangayRisk && ($farmerBarangayRisk['advisory']['irrigation'] ?? null))
                                    <div class="field-note"><strong>Irrigation:</strong> {{ $farmerBarangayRisk['advisory']['irrigation'] }}</div>
                                @endif
                                @if($farmerBarangayRisk && !empty($farmerBarangayRisk['advisory']['warnings']))
                                    <div class="field-note"><strong>Warnings:</strong> {{ implode(' · ', $farmerBarangayRisk['advisory']['warnings']) }}</div>
                                @endif
                                <div class="fc-map-wrap">
                                    @include('partials.barangay-risk-map', [
                                        'heading' => 'Lian Municipality Risk Map',
                                        'subheading' => 'All 19 barangays at a glance, using live weather and recorded rice harvests. Tap a barangay for details.',
                                        'showRefresh' => false,
                                        'highlightBarangay' => \App\Support\LianBarangays::canonicalName($farmerBarangay),
                                    ])
                                </div>
                            </div>
                        </details>
                    </div>
                @else
                    <details class="fc-details">
                        <summary>View Details</summary>
                        <div class="fc-details-body fc-map-wrap">
                            <p class="fc-risk-caveat">Farm Risk is a planning guide only. It is not yet checked against official hazard data.</p>
                            @include('partials.barangay-risk-map', [
                                'heading' => 'Lian Municipality Risk Map',
                                'subheading' => 'All 19 barangays at a glance, using live weather and recorded rice harvests. Tap a barangay for details.',
                                'showRefresh' => false,
                                'highlightBarangay' => null,
                            ])
                        </div>
                    </details>
                @endif
            </div>
        </div>

        {{-- 4 + 5. Schedule and Expected Rice Yield --}}
        <div class="row g-4 fc-simple-row">
            <div class="col-lg-6">
                <div class="farmer-panel h-100" data-reveal="fade-up">
                    <div class="farmer-panel-header">
                        <div>
                            <h2 class="farmer-panel-title">Planting and Harvest Schedule</h2>
                            <p class="farmer-panel-sub">Plan your planting and harvest dates.</p>
                        </div>
                        <span class="status-pill info">Calendar</span>
                    </div>
                    <div class="farmer-panel-body">
                        <p class="fc-prediction-note" style="margin-top:0;">Open the calendar to see your dates and the best time to plant.</p>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <a class="fc-btn fc-btn-primary" href="{{ route('calendar.index') }}">Open Calendar</a>
                            <a class="fc-btn fc-btn-outline" href="{{ route('farmer.planting-schedules.create') }}">Add My Schedule</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="farmer-panel h-100" data-reveal="fade-up" data-reveal-delay="80">
                    <div class="farmer-panel-header">
                        <div>
                            <h2 class="farmer-panel-title">Expected Rice Yield</h2>
                            <p class="farmer-panel-sub">Expected harvest for your farm this season.</p>
                        </div>
                        @if($yieldPrediction && $farmerYoyDelta)
                            <span class="status-pill {{ $farmerYoyDelta['direction'] === 'down' ? 'warn' : ($farmerYoyDelta['direction'] === 'up' ? 'neutral' : 'muted') }}">{{ $farmerYoyDelta['direction'] === 'down' ? 'Lower than '.$farmerYoyDelta['previous_year'] : ($farmerYoyDelta['direction'] === 'up' ? 'Higher than '.$farmerYoyDelta['previous_year'] : 'About the same') }}</span>
                        @endif
                    </div>
                    <div class="farmer-panel-body">
                        @if($yieldPrediction)
                            <div class="fc-prediction-value">{{ number_format((float) $yieldPrediction['yield_tons_per_hectare'], 2) }} <span style="font-size:var(--text-base);color:var(--fc-ink-mid);">tons/hectare</span></div>
                            @if($farmerYoyDelta && $farmerYoyDelta['direction'] === 'down')
                                <p class="fc-prediction-note">This is lower than last year in {{ $farmerBarangay }}. {{ $yieldPrediction['planting_advisory'] ?: 'Review irrigation and planting timing before this season.' }}</p>
                            @elseif($farmerYoyDelta)
                                <p class="fc-prediction-note">This is similar to or better than last year in {{ $farmerBarangay }}. Keep checking your field conditions.</p>
                            @else
                                <p class="fc-prediction-note">{{ $yieldPrediction['planting_advisory'] ?? 'Not enough information is available yet to compare with last year.' }}</p>
                            @endif
                            <details class="fc-details">
                                <summary>View Details</summary>
                                <div class="fc-details-body">
                                    <div class="field-note">Farm type: {{ $yieldPrediction['farm_type'] }} &middot; Farm size: {{ $yieldPrediction['farm_area_hectares'] ?? 'not set' }} ha</div>
                                    <div class="field-note">Expected total harvest: {{ $yieldPrediction['estimated_total_production_tons'] !== null ? number_format((float) $yieldPrediction['estimated_total_production_tons'], 2).' tons' : 'not available yet' }}</div>
                                    @if($farmerYoyDelta)
                                        <div class="field-note">Previous Yield Comparison: {{ $farmerYoyDelta['direction'] === 'down' ? 'lower' : ($farmerYoyDelta['direction'] === 'up' ? 'higher' : 'about the same') }} ({{ number_format(abs($farmerYoyDelta['delta_percent']), 1) }}% compared with {{ $farmerYoyDelta['previous_year'] }})</div>
                                    @endif
                                    @if($yieldReliability)
                                        <div class="field-note">Prediction Reliability: {{ $yieldReliability }}</div>
                                    @endif
                                    <div class="fc-prediction-limitation">
                                        <span>&#9432;</span>
                                        <span>This is an expected yield, not a guarantee ({{ $yieldPrediction['condition_score_label'] ?? 'Not enough information yet' }}). It uses recent weather and your farm details. Keep your farm profile up to date.</span>
                                    </div>
                                    <a class="fc-btn fc-btn-outline mt-2" href="{{ route('weather-predictions.index', ['tab' => 'rice-yield']) }}">See Full Details</a>
                                </div>
                            </details>
                        @else
                            <div class="empty-soft"><strong>Forecast Temporarily Unavailable</strong><div class="small mt-1" style="color: var(--fc-ink-light);">Please check again later, or open <a href="{{ route('weather-predictions.index', ['tab' => 'rice-yield']) }}">Predictions</a>.</div></div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- 6. Recommended Action --}}
        <div class="farmer-panel fc-simple-panel" data-reveal="fade-up">
            <div class="farmer-panel-header">
                <div>
                    <h2 class="farmer-panel-title">Recommended Action</h2>
                    <p class="farmer-panel-sub">What to do today, based on today&apos;s weather.</p>
                </div>
                <button type="button" class="fc-btn fc-btn-outline" data-ai-prefill="{{ $aiPrefillQuestion }}">Ask Climora AI</button>
            </div>
            <div class="farmer-panel-body">
                @if($weatherGuidance)
                    <div class="list-title" data-weather-guidance-title>{{ $weatherGuidance['title'] }}</div>
                    <div class="list-text" data-weather-guidance-message>{{ $weatherGuidance['message'] }}</div>
                @elseif($latestAdvisory)
                    <div class="list-title">{{ $latestAdvisory->title }}</div>
                    <div class="list-text">{{ str($latestAdvisory->content)->limit(180) }}</div>
                    <div class="list-text">{{ str($latestAdvisory->displaySummary())->limit(180) }}</div>
                @elseif($climateSummary)
                    <div class="list-title">Check the latest climate records</div>
                    <div class="list-text">Season is {{ $climateSummary->season }} with {{ $climateSummary->rainfall }} mm rainfall recorded.</div>
                @else
                    <div class="empty-soft"><strong>No action needed right now</strong><div class="small mt-1" style="color: var(--fc-ink-light);">Advice will appear here once it is available.</div></div>
                @endif
            </div>
        </div>

        {{-- 7. Weather Check (short link only) --}}
        <div class="farmer-panel fc-simple-panel" data-reveal="fade-up">
            <div class="farmer-panel-header">
                <div>
                    <h2 class="farmer-panel-title">Weather Check</h2>
                    <p class="farmer-panel-sub">See if today&apos;s weather matched what was expected, and tell us what you saw.</p>
                </div>
                <a class="fc-btn fc-btn-outline" href="{{ route('weather-predictions.index', ['tab' => 'weather-forecast']) }}">Open Weather Check</a>
            </div>
        </div>

        {{-- Everything else --}}
        <div class="farmer-panel fc-simple-panel" data-reveal="fade-up">
            <div class="farmer-panel-header">
                <div>
                    <h2 class="farmer-panel-title">More for Your Farm</h2>
                    <p class="farmer-panel-sub">Extra details, advisories, and shortcuts.</p>
                </div>
            </div>
            <div class="farmer-panel-body">
                <details class="fc-details fc-details-first">
                    <summary>Weather details</summary>
                    <div class="fc-details-body">
                        <section class="climate-grid">
                            <button type="button" class="field-card" data-drawer-open="#statModalTemperature">
                                <div class="field-card-top">
                                    <div class="field-label">Temperature</div>
                                    <span class="field-status {{ ($weatherPayload['success'] ?? false) && ! ($weatherPayload['stale'] ?? false) ? 'is-live' : '' }}">{{ $weatherStatusShort }}</span>
                                </div>
                                <div class="field-value" data-weather-temperature-card>{{ $temperatureValue !== null ? number_format((float) $temperatureValue, 1).'°C' : 'N/A' }}</div>
                                <div class="field-note" data-weather-temperature-note>Feels like {{ $feelsLikeValue !== null ? number_format((float) $feelsLikeValue, 1).'°C' : 'N/A' }}</div>
                                <div class="field-tap-hint">View details &rarr;</div>
                            </button>
                            <button type="button" class="field-card" data-drawer-open="#statModalRainfall">
                                <div class="field-card-top">
                                    <div class="field-label">Today&apos;s Rainfall</div>
                                    <span class="field-status {{ ($weatherPayload['success'] ?? false) && ! ($weatherPayload['stale'] ?? false) ? 'is-live' : '' }}">{{ $weatherStatusShort }}</span>
                                </div>
                                <div class="field-value" data-weather-rain-card>{{ $todayRainfallValue !== null ? number_format((float) $todayRainfallValue, 1).' mm' : 'N/A' }}</div>
                                <div class="field-note" data-weather-precip-note>Current precipitation: {{ $currentPrecipitationValue !== null ? number_format((float) $currentPrecipitationValue, 1).' mm' : 'N/A' }}</div>
                                <div class="field-tap-hint">View details &rarr;</div>
                            </button>
                            <button type="button" class="field-card" data-drawer-open="#statModalHumidity">
                                <div class="field-card-top">
                                    <div class="field-label">Humidity</div>
                                    <span class="field-status {{ ($weatherPayload['success'] ?? false) && ! ($weatherPayload['stale'] ?? false) ? 'is-live' : '' }}">{{ $weatherStatusShort }}</span>
                                </div>
                                <div class="field-value" data-weather-humidity-card>{{ $humidityValue !== null ? number_format((float) $humidityValue, 0).'%' : 'N/A' }}</div>
                                <div class="field-note">Current relative humidity</div>
                                <div class="field-tap-hint">View details &rarr;</div>
                            </button>
                            <button type="button" class="field-card" data-drawer-open="#statModalAlerts">
                                <div class="field-card-top">
                                    <div class="field-label">Weather Alerts</div>
                                    <span class="field-status {{ $unreadNotifications > 0 ? 'is-warn' : '' }}">{{ $unreadNotifications > 0 ? 'Unread' : 'Clear' }}</span>
                                </div>
                                <div class="field-value">{{ number_format($activeWeatherAlerts ?? 0) }} active</div>
                                <div class="field-note">Unread weather warnings only</div>
                                <div class="field-tap-hint">View details &rarr;</div>
                            </button>
                        </section>
                    </div>
                </details>

                <details class="fc-details">
                    <summary>Latest planting advisories</summary>
                    <div class="fc-details-body">
                        <div class="row g-3">
                            @forelse($advisories as $advisory)
                                @php $myFeedback = $myAdvisoryFeedback->get($advisory->id); @endphp
                                <div class="col-md-6">
                                    <div class="advisory-card">
                                        <div class="d-flex justify-content-between gap-2">
                                            <span class="status-pill {{ match(strtolower((string) $advisory->type)) {
                                                'warning' => 'warn',
                                                'irrigation' => 'info',
                                                'climate' => 'neutral',
                                                default => '',
                                            } }}">{{ $advisory->typeLabel() }}</span>
                                            <span class="small mono" style="color: var(--fc-ink-mid);">{{ $advisory->created_at?->format('M d') }}</span>
                                        </div>
                                        <strong>{{ $advisory->title }}</strong>
                                        <div class="list-text">{{ str($advisory->content)->limit(120) }}</div>
                                        <div class="list-text">{{ str($advisory->displaySummary())->limit(120) }}</div>
                                        <div class="list-meta">{{ $advisory->target_barangay ?: 'All barangays' }}</div>
                                        <div class="advisory-feedback" data-advisory-feedback data-advisory-id="{{ $advisory->id }}">
                                            <span class="advisory-feedback-label">Was this helpful?</span>
                                            <button type="button" class="advisory-feedback-btn {{ $myFeedback?->is_helpful === true ? 'active-yes' : '' }}" data-feedback-value="1">Yes</button>
                                            <button type="button" class="advisory-feedback-btn {{ $myFeedback?->is_helpful === false ? 'active-no' : '' }}" data-feedback-value="0">No</button>
                                            <span class="advisory-feedback-status"></span>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12"><div class="empty-soft"><strong>No advisories yet</strong><div class="small mt-1" style="color: var(--fc-ink-light);">Published planting advisories will appear here.</div></div></div>
                            @endforelse
                        </div>
                        <a class="fc-btn fc-btn-outline mt-3" href="{{ route('planting-advisories.index') }}">View All Advisories</a>
                    </div>
                </details>

                <details class="fc-details">
                    <summary>Community posts</summary>
                    <div class="fc-details-body list-compact">
                        @forelse($feedPosts as $post)
                            <div class="farmer-list-item">
                                <div class="list-mark">{{ str($post->category)->substr(0, 3)->upper() }}</div>
                                <div>
                                    <div class="list-title">{{ $post->title }}</div>
                                    <div class="list-text">{{ str($post->body)->limit(90) }}</div>
                                    <div class="list-meta">{{ $post->category }} | {{ $post->author?->name ?? 'MAO' }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="empty-soft"><strong>No community posts</strong><div class="small mt-1" style="color: var(--fc-ink-light);">MAO updates will appear here.</div></div>
                        @endforelse
                    </div>
                </details>

                <details class="fc-details">
                    <summary>My farm information</summary>
                    <div class="fc-details-body">
                        <div class="field-note">Barangay: <strong>{{ $profile?->barangay ?? auth()->user()->barangay ?? 'Not set' }}</strong></div>
                        <div class="field-note">Farm area: <strong>{{ $profile?->farm_area ? number_format($profile->farm_area, 2).' ha' : 'Not set' }}</strong></div>
                        <div class="field-note">Irrigation type: <strong>{{ $profile?->farm_type ?? 'Not set' }}</strong></div>
                        <div class="field-note">Farmer group: <strong>{{ $profile?->farmerOrganization?->name ?? 'Not part of a farmer group yet' }}</strong></div>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <a class="fc-btn fc-btn-outline" href="{{ route('profile.edit') }}">Edit Profile</a>
                            <a class="fc-btn fc-btn-outline" href="{{ route('farmer.boundary.edit') }}">Set Location &amp; Size</a>
                        </div>
                    </div>
                </details>

                <details class="fc-details">
                    <summary>Quick links</summary>
                    <div class="fc-details-body">
                        <div class="quick-grid">
                            <a class="quick-action" href="{{ route('climate-records.index') }}"><strong>Climate Records</strong><span>Past rainfall, temperature, humidity, and wind.</span></a>
                            <a class="quick-action" href="{{ route('farmer.boundary.edit') }}"><strong>My Farm Map</strong><span>Draw and save the outline of your farm.</span></a>
                            <a class="quick-action" href="{{ route('ai-chat.index') }}"><strong>Climora AI</strong><span>Ask questions about weather, harvest, and planting.</span></a>
                            <a class="quick-action" href="{{ route('planting-advisories.index') }}"><strong>Planting Advisories</strong><span>Guidance from MAO Personnel.</span></a>
                            <a class="quick-action" href="{{ route('community-feed.index') }}"><strong>Community Feed</strong><span>MAO updates, programs, and activities.</span></a>
                            <a class="quick-action" href="{{ route('messages.index') }}"><strong>Messages</strong><span>Send a private question to MAO personnel.</span></a>
                        </div>
                    </div>
                </details>
            </div>
        </div>

        @php
            $fcModalTrend = fn () => $recentClimateRecords->reverse()->values();
            $fcStatModals = [
                ['id' => 'Temperature', 'title' => 'Temperature Detail', 'headline' => $temperatureValue !== null ? number_format((float) $temperatureValue, 1).' °C' : 'No weather data yet', 'note' => 'High temperatures make crops need more water and can stress them. Compare with the recent records below before watering or applying fertilizer.', 'highlight' => 'temperature'],
                ['id' => 'Rainfall', 'title' => 'Rainfall Detail', 'headline' => $rainfallValue !== null ? number_format((float) $rainfallValue, 1).' mm' : 'No weather data yet', 'note' => 'Heavy rain can wash away fertilizer and flood fields. Check advisories before applying fertilizer or watering.', 'highlight' => 'rainfall'],
                ['id' => 'Humidity', 'title' => 'Humidity Detail', 'headline' => $humidityValue !== null ? number_format((float) $humidityValue, 1).'%' : 'No weather data yet', 'note' => 'High humidity for several days after rain can cause fungal disease in rice. Watch your fields closely.', 'highlight' => 'humidity'],
            ];
        @endphp

        @foreach($fcStatModals as $fcModal)
            <div class="ic-drawer fc-stat-modal" id="statModal{{ $fcModal['id'] }}" aria-hidden="true">
                <div class="ic-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="statModal{{ $fcModal['id'] }}Label">
                    <div class="modal-content fc-modal-content">
                        <div class="modal-header fc-modal-header">
                            <h2 class="modal-title h5" id="statModal{{ $fcModal['id'] }}Label">{{ $fcModal['title'] }}</h2>
                            <button type="button" class="btn-close btn-close-white" data-drawer-close aria-label="Close"></button>
                        </div>
                        <div class="modal-body fc-modal-body">
                            <div class="fc-modal-headline">{{ $fcModal['headline'] }}</div>
                            <p class="fc-modal-sub">{{ $weatherSourceDisplay }} &middot; {{ $weatherTimingLabel }}{{ $weatherDisplayDate ? ' - '.$weatherDisplayDate : '' }} &middot; {{ $weatherUpdateLine }}</p>
                            <p class="fc-modal-note">{{ $fcModal['note'] }}</p>
                            @include('dashboards.partials.climate-trend-table', ['records' => $fcModalTrend(), 'highlight' => $fcModal['highlight']])
                        </div>
                        <div class="modal-footer fc-modal-footer">
                            <a class="fc-btn fc-btn-primary" href="{{ route('climate-records.index') }}">Open Climate Records</a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="ic-drawer fc-stat-modal" id="statModalAlerts" aria-hidden="true">
            <div class="ic-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="statModalAlertsLabel">
                <div class="modal-content fc-modal-content">
                    <div class="modal-header fc-modal-header">
                        <h2 class="modal-title h5" id="statModalAlertsLabel">Weather Alerts</h2>
                        <button type="button" class="btn-close btn-close-white" data-drawer-close aria-label="Close"></button>
                    </div>
                    <div class="modal-body fc-modal-body">
                        <div class="fc-modal-headline">{{ number_format($unreadNotifications) }} unread</div>
                        <p class="fc-modal-sub">Most recent notifications sent to your account.</p>
                        @forelse($notifications as $notification)
                            <div class="farmer-list-item">
                                <div class="list-mark">
                                    @if($notification->is_read)
                                        <svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M3.5 9.5l3.5 3.5 7-8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    @else
                                        <svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M5.5 14V8a3.5 3.5 0 1 1 7 0v6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M4 14h10M7.2 15.9a1.7 1.7 0 0 0 3 0" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                                    @endif
                                </div>
                                <div>
                                    <div class="list-title">{{ $notification->title }}</div>
                                    <div class="list-text">{{ str($notification->message)->limit(140) }}</div>
                                    <div class="list-meta">{{ $notification->type }} &middot; {{ $notification->created_at?->shortDateTime('M d, Y') }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="empty-soft"><strong>No alerts yet</strong><div class="small mt-1" style="color: var(--fc-ink-light);">Notifications will appear here when sent.</div></div>
                        @endforelse
                    </div>
                    <div class="modal-footer fc-modal-footer">
                        <a class="fc-btn fc-btn-primary" href="{{ route('notifications.index') }}">Open Notifications</a>
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

            document.querySelectorAll('[data-ai-prefill]').forEach((button) => {
                button.addEventListener('click', () => {
                    if (window.iClimateAi?.prefill) {
                        window.iClimateAi.prefill(button.dataset.aiPrefill);
                    }
                });
            });

            const advisoryFeedbackToken = document.querySelector('meta[name="csrf-token"]')?.content;
            document.querySelectorAll('[data-advisory-feedback]').forEach((wrapper) => {
                const advisoryId = wrapper.dataset.advisoryId;
                const statusEl = wrapper.querySelector('.advisory-feedback-status');

                wrapper.querySelectorAll('[data-feedback-value]').forEach((button) => {
                    button.addEventListener('click', async () => {
                        const helpful = button.dataset.feedbackValue === '1';

                        try {
                            const response = await fetch(`/planting-advisories/${advisoryId}/feedback`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': advisoryFeedbackToken,
                                },
                                body: JSON.stringify({ is_helpful: helpful }),
                            });

                            if (!response.ok) throw new Error('Request failed');

                            wrapper.querySelectorAll('[data-feedback-value]').forEach((btn) => {
                                btn.classList.remove('active-yes', 'active-no');
                            });
                            button.classList.add(helpful ? 'active-yes' : 'active-no');
                            if (statusEl) statusEl.textContent = 'Thanks!';
                        } catch (error) {
                            if (statusEl) statusEl.textContent = 'Could not save — try again.';
                        }
                    });
                });
            });

            const dateTarget = document.querySelector('[data-current-date]');

            const MANILA_TIME_ZONE = @json($weatherTimezone);
            const WEATHER_POLL_INTERVAL = @json(max(10, (int) config('services.weather.poll_seconds', 60)) * 1000);
            const dashboardDateFormatter = new Intl.DateTimeFormat('en-US', {
                weekday: 'long',
                month: 'long',
                day: 'numeric',
                year: 'numeric',
                timeZone: MANILA_TIME_ZONE,
            });
            const dashboardTimeFormatter = new Intl.DateTimeFormat('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit',
                hour12: true,
                timeZone: MANILA_TIME_ZONE,
            });
            const weatherDateFormatter = new Intl.DateTimeFormat('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                timeZone: MANILA_TIME_ZONE,
            });
            const weatherTimeFormatter = new Intl.DateTimeFormat('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true,
                timeZone: MANILA_TIME_ZONE,
            });
            const manilaDatePartsFormatter = new Intl.DateTimeFormat('en-US', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                timeZone: MANILA_TIME_ZONE,
            });
            const getManilaDateKey = () => {
                const parts = Object.fromEntries(manilaDatePartsFormatter.formatToParts(new Date()).map((part) => [part.type, part.value]));

                return `${parts.year}-${parts.month}-${parts.day}`;
            };
            let previousManilaDate = getManilaDateKey();

            const updateDashboardClock = (refreshOnDateChange = false) => {
                const now = new Date();
                if (dateTarget) {
                    dateTarget.textContent = `${dashboardDateFormatter.format(now)} ${dashboardTimeFormatter.format(now)}`;
                }

                const currentManilaDate = getManilaDateKey();
                if (refreshOnDateChange && currentManilaDate !== previousManilaDate) {
                    previousManilaDate = currentManilaDate;
                    refreshWeather(true);
                }
            };

            const weatherRoot = document.querySelector('[data-weather-root]');
            const refreshButton = document.querySelector('[data-weather-refresh]');
            let weatherRequestInProgress = false;
            let weatherPollTimer = null;
            let weatherMinuteTimeout = null;
            let weatherMinuteTimer = null;
            let weatherFetchedAt = @json($weatherFetchedIso);
            let weatherCheckedAt = @json($weatherCheckedAt);
            let weatherProvider = @json($weatherSourceDisplay);

            const fmt = (value, decimals = 1, suffix = '') => {
                if (value === null || value === undefined || Number.isNaN(Number(value))) return 'N/A';
                return `${Number(value).toFixed(decimals)}${suffix}`;
            };
            const text = (selector, value) => {
                const animatedSelectors = [
                    '[data-weather-status]',
                    '[data-weather-condition]',
                    '[data-weather-temperature]',
                    '[data-weather-feels-like]',
                    '[data-weather-provider]',
                    '[data-weather-freshness]',
                    '[data-weather-humidity-mini]',
                    '[data-weather-rain-mini]',
                    '[data-weather-wind-mini]',
                    '[data-weather-temperature-card]',
                    '[data-weather-temperature-note]',
                    '[data-weather-rain-card]',
                    '[data-weather-precip-note]',
                    '[data-weather-humidity-card]',
                    '[data-weather-temperature-modal]',
                    '[data-weather-rain-modal]',
                    '[data-weather-humidity-modal]',
                ];
                const shouldAnimate = animatedSelectors.includes(selector);

                document.querySelectorAll(selector).forEach((target) => {
                    if (shouldAnimate && window.iClimateMotion?.animateValueChange) {
                        window.iClimateMotion.animateValueChange(target, value);
                    } else {
                        target.textContent = value;
                    }
                });
            };
            const weatherSourceLabel = (payload = {}) => {
                const provider = payload.provider || payload.source || weatherProvider;
                if (!provider || provider === 'Unavailable') return 'Weather temporarily unavailable';

                return payload.stale ? 'Saved weather' : 'Live weather';
            };
            const setWeatherStatus = (label) => text('[data-weather-status]', label);
            const updateLiveWeatherTimestamp = () => {
                const providerLabel = weatherProvider || 'Weather temporarily unavailable';
                text('[data-weather-provider]', providerLabel);
                return;
                const now = new Date();
                const provider = weatherProvider || 'Live weather';

                text('[data-weather-live-line]', `Live as of ${weatherDateFormatter.format(now)} • ${weatherTimeFormatter.format(now)} · ${provider}`);
            };
            const startMinuteClock = () => {
                if (weatherMinuteTimeout) clearTimeout(weatherMinuteTimeout);
                if (weatherMinuteTimer) clearInterval(weatherMinuteTimer);

                updateLiveWeatherTimestamp();

                const now = new Date();
                const delay = Math.max(0, (60 - now.getSeconds()) * 1000 - now.getMilliseconds());

                weatherMinuteTimeout = setTimeout(() => {
                    updateLiveWeatherTimestamp();
                    weatherMinuteTimer = setInterval(updateLiveWeatherTimestamp, WEATHER_POLL_INTERVAL);
                }, delay);
            };
            const weatherExactLabel = (isoValue) => {
                if (!isoValue) return 'Weather temporarily unavailable';

                const fetched = new Date(isoValue);
                if (Number.isNaN(fetched.getTime())) return 'Weather temporarily unavailable';

                return `${weatherDateFormatter.format(fetched)} • ${weatherTimeFormatter.format(fetched)}`;
            };
            const weatherAgeSeconds = () => {
                if (!weatherFetchedAt) return null;

                const fetched = new Date(weatherFetchedAt);
                if (Number.isNaN(fetched.getTime())) return null;

                return Math.max(0, Math.floor((Date.now() - fetched.getTime()) / 1000));
            };
            const weatherCheckedLabel = () => weatherCheckedAt ? weatherExactLabel(weatherCheckedAt) : 'Dashboard check pending';
            const weatherFreshnessLabel = (seconds) => {
                if (seconds === null) return 'Weather timestamp unavailable';
                if (seconds < 60) return 'just now';
                if (seconds < 120) return '1 minute ago';
                if (seconds < 3600) return `${Math.floor(seconds / 60)} minutes ago`;
                if (seconds < 7200) return '1 hour ago';

                return `${Math.floor(seconds / 3600)} hours ago`;
            };
            const weatherStatusLabel = (seconds) => {
                if (!navigator.onLine) return 'Offline';
                if (seconds === null) return 'Latest available';
                if (seconds < 300) return 'Near-real-time';
                if (seconds < 900) return `Weather Data · ${Math.floor(seconds / 60)} min ago`;
                if (seconds < 1800) return 'Getting old';

                return 'May be stale';
            };
            const updateWeatherFreshness = () => {
                const seconds = weatherAgeSeconds();
                const exact = weatherExactLabel(weatherFetchedAt);
                const freshness = weatherFreshnessLabel(seconds);

                text('[data-weather-updated]', exact);
                text('[data-weather-provider]', weatherProvider || 'Weather temporarily unavailable');
                text('[data-weather-freshness]', freshness);
                setWeatherStatus(weatherStatusLabel(seconds));
            };

            const renderForecast = (days) => {
                const target = document.querySelector('[data-weather-forecast]');
                if (!target || !Array.isArray(days)) return;

                target.innerHTML = days.map((day) => `
                    <div class="forecast-day" data-reveal="fade-up"
                         data-tmax="${day.temperature_max ?? ''}"
                         data-tmin="${day.temperature_min ?? ''}"
                         data-pop="${day.precipitation_probability ?? ''}">
                        <strong>${day.day ?? ''}</strong>
                        <img src="${day.icon ?? '/images/weather/unavailable.svg'}" alt="${day.condition ?? 'Forecast'}">
                        <span>${day.condition ?? 'N/A'}</span>
                        <span>${day.temperature_max !== null && day.temperature_min !== null ? `${Number(day.temperature_max).toFixed(0)} / ${Number(day.temperature_min).toFixed(0)}°C` : 'N/A'}</span>
                        <span>${day.precipitation_probability !== null && day.precipitation_probability !== undefined ? Number(day.precipitation_probability).toFixed(0) : 'N/A'}% rain chance</span>
                    </div>
                `).join('');
                window.iClimateMotion?.initScrollAnimations?.(target);
            };

            const renderWeather = (payload) => {
                const current = payload.current ?? {};
                const today = payload.today ?? {};
                                const sourceLabel = weatherSourceLabel(payload);
                weatherFetchedAt = payload.fetched_at ?? weatherFetchedAt;
                weatherCheckedAt = payload.checked_at ?? weatherCheckedAt;
                weatherProvider = sourceLabel;
                const updated = weatherExactLabel(weatherFetchedAt);
                const freshness = weatherFreshnessLabel(weatherAgeSeconds());

                text('[data-weather-condition]', current.condition ?? 'Weather temporarily unavailable');
                text('[data-weather-temperature]', fmt(current.temperature, 1, '°C'));
                text('[data-weather-feels-like]', fmt(current.feels_like, 1, '°C'));
                text('[data-weather-updated]', updated);
                text('[data-weather-provider]', sourceLabel);
                text('[data-weather-freshness]', freshness);
                text('[data-weather-humidity-mini]', fmt(current.humidity, 0, '%'));
                text('[data-weather-rain-mini]', fmt(today.rainfall, 1, ' mm'));
                text('[data-weather-wind-mini]', fmt(current.wind_speed, 1, ' km/h'));
                text('[data-weather-temperature-card]', fmt(current.temperature, 1, '°C'));
                text('[data-weather-temperature-note]', `Feels like ${fmt(current.feels_like, 1, '°C')}`);
                text('[data-weather-rain-card]', fmt(today.rainfall, 1, ' mm'));
                text('[data-weather-precip-note]', `Current precipitation: ${fmt(current.precipitation ?? current.rain, 1, ' mm')}`);
                text('[data-weather-humidity-card]', fmt(current.humidity, 0, '%'));
                text('[data-weather-temperature-modal]', fmt(current.temperature, 1, '°C'));
                text('[data-weather-rain-modal]', fmt(today.rainfall, 1, ' mm'));
                text('[data-weather-humidity-modal]', fmt(current.humidity, 0, '%'));

                text('[data-weather-rain-sub]', `Today's rainfall uses daily total precipitation from ${sourceLabel}. Current precipitation: ${fmt(current.precipitation ?? current.rain, 1, ' mm')}.`);
                text('[data-weather-modal-sub]', `Source: ${sourceLabel}`);
                text('[data-weather-humidity-sub]', `Source: ${sourceLabel}`);

                const icon = document.querySelector('[data-weather-icon]');
                if (icon && current.icon) {
                    icon.src = current.icon;
                    icon.alt = current.condition ?? 'Weather condition';
                }

                if (payload.guidance) {
                    text('[data-weather-guidance-title]', payload.guidance.title ?? 'iClimate Weather Guidance');
                    text('[data-weather-guidance-message]', payload.guidance.message ?? 'Weather guidance is temporarily unavailable.');
                }

                renderForecast(payload.forecast ?? []);
                updateLiveWeatherTimestamp();
                updateWeatherFreshness();
            };

            const refreshWeather = async (force = false) => {
                if (!weatherRoot || weatherRequestInProgress) return;
                weatherRequestInProgress = true;
                setWeatherStatus('Updating Weather');
                if (refreshButton) {
                    refreshButton.disabled = true;
                    refreshButton.textContent = 'Refreshing...';
                }

                try {
                    const url = new URL(weatherRoot.dataset.weatherUrl, window.location.origin);
                    if (force) url.searchParams.set('refresh', '1');
                    const response = await fetch(url, { headers: { Accept: 'application/json' } });
                    if (!response.ok) throw new Error(`Weather endpoint returned ${response.status}`);
                    renderWeather(await response.json());
                } catch (error) {
                    setWeatherStatus(navigator.onLine ? 'Latest available' : 'Offline');
                } finally {
                    weatherRequestInProgress = false;
                    if (refreshButton) {
                        refreshButton.disabled = false;
                        refreshButton.textContent = 'Refresh Weather';
                    }
                }
            };

            refreshButton?.addEventListener('click', () => refreshWeather(true));

            const startWeatherPolling = () => {
                if (weatherPollTimer) clearInterval(weatherPollTimer);
                weatherPollTimer = setInterval(() => {
                    if (document.visibilityState === 'visible') refreshWeather(false);
                }, WEATHER_POLL_INTERVAL);
            };

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    updateDashboardClock(false);
                    updateLiveWeatherTimestamp();
                    refreshWeather(false);
                    startWeatherPolling();
                } else if (weatherPollTimer) {
                    clearInterval(weatherPollTimer);
                    weatherPollTimer = null;
                }
            });

            window.addEventListener('online', () => {
                setWeatherStatus('Updating Weather');
                refreshWeather(false);
            });
            window.addEventListener('offline', () => {
                setWeatherStatus('Offline');
            });

            updateDashboardClock(false);
            startMinuteClock();
            updateWeatherFreshness();
            setInterval(() => {
                updateDashboardClock(true);
                updateWeatherFreshness();
            }, 1000);
            startWeatherPolling();
        });
</script>
</x-app-layout>
