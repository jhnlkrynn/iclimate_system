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
            --fc-ink-mid:   #68756D;
            --fc-ink-light: rgba(30,43,37,.64);
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
            background: linear-gradient(145deg, var(--fc-green-950) 0%, var(--fc-green-900) 100%);
            border: 1px solid rgba(255,255,255,.12);
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
            border: 1px solid rgba(255,255,255,.16);
            border-radius: var(--radius-md);
            background: rgba(255,255,255,.06);
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
            color: rgba(255,255,255,.64);
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
            border: 1px solid rgba(255,255,255,.18);
            border-radius: var(--radius-md);
            padding: .5rem .85rem;
            background: rgba(255,255,255,.1);
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
            border: 1px solid rgba(201,215,206,.95);
            border-radius: var(--radius-lg);
            background: #ffffff;
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
        .fc-modal-body { overflow-y: auto; background: #ffffff; padding: 1rem; }
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
            border: 1px solid rgba(201,215,206,.95);
            border-radius: var(--radius-lg);
            background: #ffffff;
            padding: 1rem;
            box-shadow: var(--shadow-sm);
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
            border: 1px solid var(--fc-border);
            border-radius: var(--radius-md);
            background: #ffffff;
            padding: .7rem;
            text-align: center;
        }
        .forecast-day img { width: 34px; height: 34px; display: block; margin: .25rem auto; }
        .forecast-day strong { display: block; color: var(--fc-ink); font-size: var(--text-sm); font-weight: 700; line-height: 1.3; }
        .forecast-day span { display: block; color: var(--fc-ink-mid); font-size: var(--text-sm); line-height: 1.42; }

        /* -- PANELS ------------------------------------------ */
        .farmer-panel {
            border: 1px solid rgba(201,215,206,.95);
            border-radius: var(--radius-lg);
            background: #ffffff;
            box-shadow: var(--shadow-sm);
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
        .status-pill.neutral { background: rgba(143,175,154,.18); color: var(--fc-green-700); }
        .advisory-card {
            padding: 1.1rem 1.2rem;
            border-radius: var(--radius-md);
            border: 1px solid var(--fc-border);
            background: var(--fc-green-50);
            min-height: 100%;
        }
        .advisory-card strong { color: var(--fc-ink); display: block; margin: .5rem 0 .35rem; font-size: var(--text-base); line-height: 1.35; }

        /* -- QUICK ACTIONS ------------------------------------ */
        .quick-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .85rem; }
        .quick-action {
            min-height: 108px;
            padding: 1.1rem 1.2rem;
            border-radius: var(--radius-md);
            border: 1px solid var(--fc-border);
            background: var(--fc-green-50);
            color: inherit;
            text-decoration: none;
            display: block;
            transition: transform .2s var(--ease), box-shadow .2s var(--ease), border-color .2s, background .2s;
        }
        .quick-action:hover { transform: translateY(-3px); box-shadow: var(--shadow-sm); border-color: var(--fc-green-500); background: var(--fc-green-100); }
        .quick-action strong { display: block; color: var(--fc-ink); font-size: var(--text-base); font-weight: 700; }
        .quick-action span { display: block; color: var(--fc-ink-mid); font-size: var(--text-sm); margin-top: .3rem; line-height: 1.5; }
        .empty-soft {
            border: 1.5px dashed var(--fc-sand-dark);
            background: var(--fc-green-50);
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
            border: 1.5px solid var(--fc-sand-dark);
            border-radius: var(--radius-xl);
            background: linear-gradient(145deg, #ffffff, #f7fbf8);
            box-shadow: var(--shadow-sm);
            text-decoration: none;
            color: inherit;
            transition: box-shadow .25s var(--ease), transform .25s var(--ease), border-color .25s;
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

        /* -- COLLAPSIBLE GROUPS --------------------------------- */
        .dashboard-focus-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        .dashboard-group {
            border: 1.5px solid var(--fc-sand-dark);
            border-radius: var(--radius-lg);
            background: linear-gradient(145deg, #ffffff, #f7fbf8);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        .dashboard-group > summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.1rem 1.25rem;
            cursor: pointer;
            list-style: none;
            background: var(--fc-green-50);
        }
        .dashboard-group > summary::-webkit-details-marker { display: none; }
        .dashboard-group-summary-main { display: flex; align-items: center; gap: .9rem; }
        h2.dashboard-group-title { color: var(--fc-ink); font-size: var(--text-lg); margin: 0; }
        .dashboard-group-note { color: var(--fc-ink-mid); font-size: var(--text-sm); margin: .15rem 0 0; line-height: 1.5; font-family: 'Inter', sans-serif; }
        .dashboard-group-toggle { display: inline-flex; align-items: center; gap: 6px; font-family: 'DM Mono', monospace; color: var(--fc-green-700); font-size: var(--text-sm); font-weight: 600; text-transform: uppercase; letter-spacing: .05em; white-space: nowrap; }
        .dashboard-group-toggle::before { content: "Collapse"; }
        .dashboard-group:not([open]) .dashboard-group-toggle::before { content: "Expand"; }
        .dashboard-group-body { padding: 1.25rem; }
        .dashboard-group-body > .row:last-child, .dashboard-group-body > .farmer-panel:last-child { margin-bottom: 0 !important; }
        .list-compact .farmer-list-item:nth-of-type(n+4), .list-compact .row > [class*="col-"]:nth-child(n+5) { display: none; }

        @media (max-width: 1399.98px) {
            .farmer-hero-grid { grid-template-columns: minmax(0, 1fr) minmax(340px, 420px); }
            .climate-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
        @media (max-width: 1199.98px) { .priority-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 767.98px) {
            .farmer-hero-grid, .quick-grid, .priority-grid { grid-template-columns: 1fr; }
            .farmer-hero-grid {
                grid-template-areas:
                    "copy"
                    "actions"
                    "weather";
            }
            .farmer-hero-summary { grid-template-columns: 1fr; }
            .farmer-hero-actions .field-chip { flex: 1 1 100%; width: 100%; justify-content: flex-start; }
            .climate-grid, .quick-grid, .priority-grid { grid-template-columns: 1fr; }
            .weather-mini-grid { grid-template-columns: 1fr; }
            .forecast-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .farmer-hero { padding: 1.5rem; }
            .fc-modal-table.table { min-width: 560px; }
            .farmer-panel-header, .dashboard-section-label, .dashboard-group > summary { align-items: flex-start; flex-direction: column; }
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
        $weatherSource = $weatherPayload['provider'] ?? 'Open-Meteo';
        $weatherSourceDisplay = $weatherSource;
        if (($weatherPayload['stale'] ?? false) && $weatherSource !== 'Unavailable') {
            $weatherSourceDisplay .= ' stored data';
        }
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
    @endphp

    <div class="farmer-console">
        <section class="farmer-hero" data-reveal="blur" data-reveal-duration="720" data-parallax data-parallax-speed="0.035" data-parallax-limit="16">
            <div class="farmer-hero-leaf" aria-hidden="true" data-parallax data-parallax-speed="0.08" data-parallax-limit="28"></div>
            <div class="farmer-hero-grid">
                <div class="farmer-hero-copy">
                    <div class="fc-eyebrow on-light" data-reveal="fade-up" data-reveal-delay="60" data-reveal-duration="560">Farmer Dashboard</div>
                    <h1 data-reveal="blur" data-reveal-delay="150" data-reveal-duration="680">Lian field conditions</h1>
                    <p data-reveal="fade-up" data-reveal-delay="240" data-reveal-duration="620">Weather, rainfall, advisories, community updates, and messages for {{ auth()->user()->name }} in Lian, Batangas.</p>
                    <div class="farmer-hero-summary" aria-label="Farmer dashboard summary" data-reveal-stagger>
                        <div class="farmer-hero-summary-item" data-reveal="fade-up" data-reveal-delay="320">
                            <span>Current Weather</span>
                            <strong>{{ $weatherCondition }}</strong>
                            <small>{{ $temperatureValue !== null ? number_format((float) $temperatureValue, 1).'°C' : 'No temperature data' }} in Lian, Batangas</small>
                        </div>
                        <div class="farmer-hero-summary-item" data-reveal="fade-up" data-reveal-delay="390">
                            <span>Today&apos;s Rain</span>
                            <strong>{{ $todayRainfallValue !== null ? number_format((float) $todayRainfallValue, 1).' mm' : 'No rainfall data' }}</strong>
                            <small>Current precipitation: {{ $currentPrecipitationValue !== null ? number_format((float) $currentPrecipitationValue, 1).' mm' : 'N/A' }}</small>
                        </div>
                        <div class="farmer-hero-summary-item" data-reveal="fade-up" data-reveal-delay="460">
                            <span>Advisories</span>
                            <strong>{{ number_format($activeWeatherAlerts ?? 0) }} active</strong>
                            <small>{{ number_format($highRiskHeatMapAreas) }} high-risk barangay areas tracked</small>
                        </div>
                    </div>
                </div>
                <div class="weather-live-card" data-weather-root data-weather-url="{{ route('farmer.dashboard.weather') }}" data-reveal="fade-right" data-reveal-delay="360" data-reveal-duration="740">
                    <div class="weather-live-head">
                        <span class="weather-live-status"><span class="weather-live-dot"></span>Current Weather</span>
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
                                Source: <span data-weather-provider>{{ $weatherSourceDisplay }}</span><br>
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
                <div class="farmer-hero-actions" data-reveal="fade-left" data-reveal-delay="260">
                    <span class="field-chip"><span class="field-pulse"></span> <span data-current-date>{{ $weatherNow->format('l, F j, Y') }} {{ $weatherNow->format('g:i:s A') }}</span></span>
                    <a class="fc-btn fc-btn-outline-light" href="{{ route('heatmap-areas.index') }}">View Heat Map</a>
                    <a class="fc-btn fc-btn-outline-light" href="{{ route('community-feed.index') }}">Community Feed</a>
                    <a class="fc-btn fc-btn-outline-light" href="{{ route('profile.edit') }}">My Profile</a>
                </div>
            </div>
        </section>

        <div class="dashboard-section-label" data-reveal="fade-up" data-reveal-line>
            <div class="fc-eyebrow on-light">At a glance</div>
        </div>

        <section class="climate-grid" data-reveal-stagger>
            <button type="button" class="field-card" data-drawer-open="#statModalTemperature" data-reveal="fade-up" data-reveal-delay="0">
                <div class="field-card-top">
                    <div class="field-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M9 11.3V4.5a1.5 1.5 0 0 1 3 0v6.8a3 3 0 1 1-3 0Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg></div>
                    <span class="field-status {{ ($weatherPayload['success'] ?? false) && ! ($weatherPayload['stale'] ?? false) ? 'is-live' : '' }}">{{ $weatherStatusShort }}</span>
                </div>
                <div class="field-label">Temperature</div>
                <div class="field-value" data-weather-temperature-card>{{ $temperatureValue !== null ? number_format((float) $temperatureValue, 1).'°C' : 'N/A' }}</div>
                <div class="field-note" data-weather-temperature-note>Feels like {{ $feelsLikeValue !== null ? number_format((float) $feelsLikeValue, 1).'°C' : 'N/A' }}</div>
                <div class="field-tap-hint">View details &rarr;</div>
            </button>
            <button type="button" class="field-card" data-drawer-open="#statModalRainfall" data-reveal="fade-up" data-reveal-delay="70">
                <div class="field-card-top">
                    <div class="field-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 2c2.6 3.6 5 6.8 5 9.5A5 5 0 1 1 5 11.5C5 8.8 7.4 5.6 10 2Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg></div>
                    <span class="field-status {{ ($weatherPayload['success'] ?? false) && ! ($weatherPayload['stale'] ?? false) ? 'is-live' : '' }}">{{ $weatherStatusShort }}</span>
                </div>
                <div class="field-label">Today&apos;s Rainfall</div>
                <div class="field-value" data-weather-rain-card>{{ $todayRainfallValue !== null ? number_format((float) $todayRainfallValue, 1).' mm' : 'N/A' }}</div>
                <div class="field-note" data-weather-precip-note>Current precipitation: {{ $currentPrecipitationValue !== null ? number_format((float) $currentPrecipitationValue, 1).' mm' : 'N/A' }}</div>
                <div class="field-tap-hint">View details &rarr;</div>
            </button>
            <button type="button" class="field-card" data-drawer-open="#statModalHumidity" data-reveal="fade-up" data-reveal-delay="140">
                <div class="field-card-top">
                    <div class="field-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M6 10.5a3.6 3.6 0 0 0 .7 7.1 4.8 4.8 0 0 0 9.2.8 3.2 3.2 0 0 0-.5-6.3H6Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><path d="M7.2 12.6A3.6 3.6 0 0 1 12 7.1a4.7 4.7 0 0 1 1.6 3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg></div>
                    <span class="field-status {{ ($weatherPayload['success'] ?? false) && ! ($weatherPayload['stale'] ?? false) ? 'is-live' : '' }}">{{ $weatherStatusShort }}</span>
                </div>
                <div class="field-label">Humidity</div>
                <div class="field-value" data-weather-humidity-card>{{ $humidityValue !== null ? number_format((float) $humidityValue, 0).'%' : 'N/A' }}</div>
                <div class="field-note">Current relative humidity</div>
                <div class="field-tap-hint">View details &rarr;</div>
            </button>
            <button type="button" class="field-card" data-drawer-open="#statModalAlerts" data-reveal="fade-up" data-reveal-delay="210">
                <div class="field-card-top">
                    <div class="field-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M6 15.5V9a4 4 0 1 1 8 0v6.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M4.5 15.5h11M8.3 17.8a1.9 1.9 0 0 0 3.4 0" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg></div>
                    <span class="field-status {{ $unreadNotifications > 0 ? 'is-warn' : '' }}">{{ $unreadNotifications > 0 ? 'Unread' : 'Clear' }}</span>
                </div>
                <div class="field-label">Weather Alerts</div>
                <div class="field-value">{{ number_format($activeWeatherAlerts ?? 0) }} active</div>
                <div class="field-note">Unread weather warnings only</div>
                <div class="field-tap-hint">View details &rarr;</div>
            </button>
            <button type="button" class="field-card" data-drawer-open="#statModalRisk" data-reveal="fade-up" data-reveal-delay="280">
                <div class="field-card-top">
                    <div class="field-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 2.5 18 16.5H2L10 2.5Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M10 8v3.6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="10" cy="14" r=".9" fill="currentColor"/></svg></div>
                    <span class="field-status {{ $highRiskHeatMapAreas > 0 ? 'is-warn' : '' }}">{{ $highRiskHeatMapAreas > 0 ? 'Flagged' : 'Clear' }}</span>
                </div>
                <div class="field-label">High Risk Areas</div>
                <div class="field-value">{{ number_format($highRiskHeatMapAreas) }}</div>
                <div class="field-note">High or severe barangay risk areas</div>
                <div class="field-tap-hint">View details &rarr;</div>
            </button>
        </section>

        @if($weatherForecastDays !== [])
            <section class="forecast-strip" data-weather-forecast data-reveal-stagger>
                @foreach($weatherForecastDays as $forecastDay)
                    <div class="forecast-day" data-reveal="fade-up" data-reveal-delay="{{ $loop->index * 60 }}">
                        <strong>{{ $forecastDay['day'] ?? '' }}</strong>
                        <img src="{{ $forecastDay['icon'] ?? '/images/weather/unavailable.svg' }}" alt="{{ $forecastDay['condition'] ?? 'Forecast' }}">
                        <span>{{ $forecastDay['condition'] ?? 'N/A' }}</span>
                        <span>{{ isset($forecastDay['temperature_max'], $forecastDay['temperature_min']) ? number_format((float) $forecastDay['temperature_max'], 0).' / '.number_format((float) $forecastDay['temperature_min'], 0).'°C' : 'N/A' }}</span>
                        <span>{{ isset($forecastDay['precipitation_probability']) ? number_format((float) $forecastDay['precipitation_probability'], 0).'%' : 'N/A' }} rain chance</span>
                    </div>
                @endforeach
            </section>
        @endif

        @php
            $fcModalTrend = fn () => $recentClimateRecords->reverse()->values();
        @endphp

        <div class="ic-drawer fc-stat-modal" id="statModalTemperature" aria-hidden="true">
            <div class="ic-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="statModalTemperatureLabel">
                <div class="modal-content fc-modal-content">
                    <div class="modal-header fc-modal-header">
                        <h2 class="modal-title h5" id="statModalTemperatureLabel">Temperature Detail</h2>
                        <button type="button" class="btn-close btn-close-white" data-drawer-close aria-label="Close"></button>
                    </div>
                    <div class="modal-body fc-modal-body">
                        <div class="fc-modal-headline">{{ $temperatureValue !== null ? number_format((float) $temperatureValue, 1).' °C' : 'No weather data yet' }}</div>
                        <p class="fc-modal-sub">{{ $weatherSourceLine }} &middot; Weather reading: {{ $weatherTimingLabel }}{{ $weatherDisplayDate ? ' - '.$weatherDisplayDate : '' }} &middot; {{ $weatherUpdateLine }} &middot; Historical record source: {{ $climateSummary?->source ?? 'N/A' }}</p>
                        <p class="fc-modal-note">High field temperatures increase crop water demand and heat stress risk. The headline uses the latest available forecast; compare with recent recorded readings below before scheduling irrigation or fertilizer application.</p>
                        @include('dashboards.partials.climate-trend-table', ['records' => $fcModalTrend(), 'highlight' => 'temperature'])
                    </div>
                    <div class="modal-footer fc-modal-footer">
                        <a class="fc-btn fc-btn-primary" href="{{ route('climate-records.index') }}">Open Climate Records</a>
                    </div>
        <div class="fc-stat-panel" id="statPanelTemperature" hidden>
            <div class="fc-modal-content">
                <div class="fc-modal-header">
                    <h2 class="modal-title h5">Temperature Detail</h2>
                    <button type="button" class="fc-panel-close" data-panel-close aria-label="Close">&times;</button>
                </div>
                <div class="fc-modal-body">
                    <div class="fc-modal-headline" data-weather-temperature-modal>{{ $temperatureValue !== null ? number_format((float) $temperatureValue, 1).'°C' : 'No weather data yet' }}</div>
                    <p class="fc-modal-sub" data-weather-modal-sub>{{ $weatherSourceLine }} &middot; Source: {{ strtoupper($weatherSourceDisplay) }} &middot; Historical record source: {{ $climateSummary?->source ?? 'N/A' }}</p>
                    <p class="fc-modal-note">Current temperature and apparent temperature come from the normalized weather provider payload for Lian, Batangas. Compare with recent recorded readings below before scheduling irrigation or fertilizer application.</p>
                    @include('dashboards.partials.climate-trend-table', ['records' => $fcModalTrend(), 'highlight' => 'temperature'])
                </div>
                <div class="fc-modal-footer">
                    <a class="fc-btn fc-btn-primary" href="{{ route('climate-records.index') }}">Open Climate Records</a>
                </div>
            </div>
        </div>

        <div class="ic-drawer fc-stat-modal" id="statModalRainfall" aria-hidden="true">
            <div class="ic-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="statModalRainfallLabel">
                <div class="modal-content fc-modal-content">
                    <div class="modal-header fc-modal-header">
                        <h2 class="modal-title h5" id="statModalRainfallLabel">Rainfall Detail</h2>
                        <button type="button" class="btn-close btn-close-white" data-drawer-close aria-label="Close"></button>
                    </div>
                    <div class="modal-body fc-modal-body">
                        <div class="fc-modal-headline">{{ $rainfallValue !== null ? number_format((float) $rainfallValue, 1).' mm' : 'No weather data yet' }}</div>
                        <p class="fc-modal-sub">{{ $weatherSourceLine }} &middot; Weather reading: {{ $weatherTimingLabel }}{{ $weatherDisplayDate ? ' - '.$weatherDisplayDate : '' }} &middot; {{ $weatherUpdateLine }} &middot; Historical record source: {{ $climateSummary?->source ?? 'N/A' }}</p>
                        <p class="fc-modal-note">Heavy rainfall can wash away fertilizer and raise flooding or waterlogging risk. The headline uses the latest available forecast; check advisories before applying inputs or irrigating.</p>
                        @include('dashboards.partials.climate-trend-table', ['records' => $fcModalTrend(), 'highlight' => 'rainfall'])
                    </div>
                    <div class="modal-footer fc-modal-footer">
                        <a class="fc-btn fc-btn-primary" href="{{ route('climate-records.index') }}">Open Climate Records</a>
                    </div>
        <div class="fc-stat-panel" id="statPanelRainfall" hidden>
            <div class="fc-modal-content">
                <div class="fc-modal-header">
                    <h2 class="modal-title h5">Rainfall Detail</h2>
                    <button type="button" class="fc-panel-close" data-panel-close aria-label="Close">&times;</button>
                </div>
                <div class="fc-modal-body">
                    <div class="fc-modal-headline" data-weather-rain-modal>{{ $todayRainfallValue !== null ? number_format((float) $todayRainfallValue, 1).' mm' : 'No weather data yet' }}</div>
                    <p class="fc-modal-sub" data-weather-rain-sub>Today&apos;s rainfall uses daily total precipitation from {{ strtoupper($weatherSourceDisplay) }}. Current precipitation: {{ $currentPrecipitationValue !== null ? number_format((float) $currentPrecipitationValue, 1).' mm' : 'N/A' }}. {{ $weatherUpdateLine }}</p>
                    <p class="fc-modal-note">The headline is today&apos;s accumulated rainfall, not the current interval. Current precipitation is shown separately to avoid mixing rainfall definitions.</p>
                    @include('dashboards.partials.climate-trend-table', ['records' => $fcModalTrend(), 'highlight' => 'rainfall'])
                </div>
                <div class="fc-modal-footer">
                    <a class="fc-btn fc-btn-primary" href="{{ route('climate-records.index') }}">Open Climate Records</a>
                </div>
            </div>
        </div>

        <div class="ic-drawer fc-stat-modal" id="statModalHumidity" aria-hidden="true">
            <div class="ic-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="statModalHumidityLabel">
                <div class="modal-content fc-modal-content">
                    <div class="modal-header fc-modal-header">
                        <h2 class="modal-title h5" id="statModalHumidityLabel">Humidity Detail</h2>
                        <button type="button" class="btn-close btn-close-white" data-drawer-close aria-label="Close"></button>
                    </div>
                    <div class="modal-body fc-modal-body">
                        <div class="fc-modal-headline">{{ $humidityValue !== null ? number_format((float) $humidityValue, 1).'%' : 'No weather data yet' }}</div>
                        <p class="fc-modal-sub">{{ $weatherSourceLine }} &middot; Weather reading: {{ $weatherTimingLabel }}{{ $weatherDisplayDate ? ' - '.$weatherDisplayDate : '' }} &middot; {{ $weatherUpdateLine }} &middot; Historical record source: {{ $climateSummary?->source ?? 'N/A' }}</p>
                        <p class="fc-modal-note">Sustained high humidity after rainfall raises the risk of fungal disease in rice. The headline uses the latest available forecast; monitor fields closely when humidity stays elevated for several days.</p>
                        @include('dashboards.partials.climate-trend-table', ['records' => $fcModalTrend(), 'highlight' => 'humidity'])
                    </div>
                    <div class="modal-footer fc-modal-footer">
                        <a class="fc-btn fc-btn-primary" href="{{ route('climate-records.index') }}">Open Climate Records</a>
                    </div>
        <div class="fc-stat-panel" id="statPanelHumidity" hidden>
            <div class="fc-modal-content">
                <div class="fc-modal-header">
                    <h2 class="modal-title h5">Humidity Detail</h2>
                    <button type="button" class="fc-panel-close" data-panel-close aria-label="Close">&times;</button>
                </div>
                <div class="fc-modal-body">
                    <div class="fc-modal-headline" data-weather-humidity-modal>{{ $humidityValue !== null ? number_format((float) $humidityValue, 0).'%' : 'No weather data yet' }}</div>
                    <p class="fc-modal-sub" data-weather-humidity-sub>{{ $weatherSourceLine }} &middot; Source: {{ strtoupper($weatherSourceDisplay) }} &middot; Historical record source: {{ $climateSummary?->source ?? 'N/A' }}</p>
                    <p class="fc-modal-note">Sustained high humidity after rainfall raises the risk of fungal disease in rice. Monitor fields closely when humidity stays elevated.</p>
                    @include('dashboards.partials.climate-trend-table', ['records' => $fcModalTrend(), 'highlight' => 'humidity'])
                </div>
                <div class="fc-modal-footer">
                    <a class="fc-btn fc-btn-primary" href="{{ route('climate-records.index') }}">Open Climate Records</a>
                </div>
            </div>
        </div>

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
        <div class="fc-stat-panel" id="statPanelAlerts" hidden>
            <div class="fc-modal-content">
                <div class="fc-modal-header">
                    <h2 class="modal-title h5">Weather Alerts</h2>
                    <button type="button" class="fc-panel-close" data-panel-close aria-label="Close">&times;</button>
                </div>
                <div class="fc-modal-body">
                    <div class="fc-modal-headline">{{ number_format($activeWeatherAlerts ?? 0) }} active</div>
                    <p class="fc-modal-sub">Unread warning notifications that mention weather, rainfall, storms, floods, drought, heat, typhoon, PAGASA, or climate.</p>
                    @forelse($weatherAlertNotifications ?? collect() as $notification)
                        <div class="farmer-list-item">
                            <div class="list-mark">{{ $notification->is_read ? 'OK' : 'NEW' }}</div>
                            <div>
                                <div class="list-title">{{ $notification->title }}</div>
                                <div class="list-text">{{ str($notification->message)->limit(140) }}</div>
                                <div class="list-meta">{{ $notification->type }} &middot; {{ $notification->created_at?->shortDateTime() }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="empty-soft"><strong>0 active weather alerts</strong><div class="small mt-1" style="color: var(--fc-ink-light);">No unread iClimate weather warnings are active for your account.</div></div>
                    @endforelse
                </div>
                <div class="fc-modal-footer">
                    <a class="fc-btn fc-btn-primary" href="{{ route('notifications.index') }}">Open Notifications</a>
                </div>
            </div>
        </div>

        <div class="ic-drawer fc-stat-modal" id="statModalRisk" aria-hidden="true">
            <div class="ic-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="statModalRiskLabel">
                <div class="modal-content fc-modal-content">
                    <div class="modal-header fc-modal-header">
                        <h2 class="modal-title h5" id="statModalRiskLabel">High Risk Barangays</h2>
                        <button type="button" class="btn-close btn-close-white" data-drawer-close aria-label="Close"></button>
                    </div>
                    <div class="modal-body fc-modal-body">
                        <div class="fc-modal-headline">{{ number_format($highRiskHeatMapAreas) }} flagged</div>
                        <p class="fc-modal-sub">Barangays currently marked High or Severe risk on the heat map.</p>
                        @forelse($highRiskAreasList as $area)
                            <div class="farmer-list-item">
                                <div class="list-mark"><svg width="16" height="16" viewBox="0 0 20 20" fill="none"><path d="M10 2.5 18 16.5H2L10 2.5Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M10 8v3.6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="10" cy="14" r=".9" fill="currentColor"/></svg></div>
                                <div>
                                    <div class="list-title">{{ $area->barangay }} &middot; {{ $area->risk_type }}</div>
                                    <div class="list-text">{{ $area->planting_advisory ?: 'Review latest climate and rice production data before planting.' }}</div>
                                    <div class="list-meta">Risk score {{ number_format($area->risk_score, 2) }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="empty-soft"><strong>No high risk barangays</strong><div class="small mt-1" style="color: var(--fc-ink-light);">All barangays are currently within normal risk levels.</div></div>
                        @endforelse
                    </div>
                    <div class="modal-footer fc-modal-footer">
                        <a class="fc-btn fc-btn-primary" href="{{ route('heatmap-areas.index') }}">Open Heat Map</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-section-label" data-reveal="fade-up" data-reveal-line>
            <div class="fc-eyebrow on-light">Daily tools</div>
        </div>

        <section class="priority-grid" data-reveal-stagger>
            <a class="priority-card priority-highlight" href="{{ route('ai-chat.index') }}" data-reveal="fade-up" data-reveal-delay="0">
                <div class="priority-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M3 5.5A2.5 2.5 0 0 1 5.5 3h9A2.5 2.5 0 0 1 17 5.5v6A2.5 2.5 0 0 1 14.5 14H9l-3.5 3v-3h-1A2.5 2.5 0 0 1 2 11.5v-6Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><circle cx="7" cy="8.5" r=".9" fill="currentColor"/><circle cx="10.5" cy="8.5" r=".9" fill="currentColor"/><circle cx="14" cy="8.5" r=".9" fill="currentColor"/></svg></div>
                <div><strong>Climora AI</strong><span class="desc">Ask questions, predict weather, estimate yield, and get planting or irrigation guidance.</span></div>
                <span class="status-pill">Open Climora AI</span>
            </a>
            <a class="priority-card" href="{{ route('heatmap-areas.index') }}" data-reveal="fade-up" data-reveal-delay="70">
                <div class="priority-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M2.5 5.5 7 4l6 2 4.5-1.5v10L13 16l-6-2-4.5 1.5v-10Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M7 4v10M13 6v10" stroke="currentColor" stroke-width="1.2"/></svg></div>
                <div><strong>Barangay Heat Map</strong><span class="desc">Check risk areas before planning field work, irrigation, and harvest movement.</span></div>
                <span class="status-pill">Open Heat Map</span>
            </a>
            <a class="priority-card" href="{{ route('community-feed.index') }}" data-reveal="fade-up" data-reveal-delay="140">
                <div class="priority-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><rect x="2.5" y="4" width="15" height="10.5" rx="1.6" stroke="currentColor" stroke-width="1.4"/><path d="M6 17l2.2-2.5h3.6L14 17" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/><path d="M5.5 7.5h9M5.5 10.5h6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg></div>
                <div><strong>Community Feed</strong><span class="desc">View MAO updates, programs, activities, photos, videos, comments, and reactions.</span></div>
                <span class="status-pill">Open Feed</span>
            </a>
            <a class="priority-card" href="{{ route('messages.index') }}" data-reveal="fade-up" data-reveal-delay="210">
                <div class="priority-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M2.5 5.5A1.5 1.5 0 0 1 4 4h12a1.5 1.5 0 0 1 1.5 1.5v8a1.5 1.5 0 0 1-1.5 1.5H4a1.5 1.5 0 0 1-1.5-1.5v-8Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M3 5.5l7 5.5 7-5.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                <div><strong>Messages</strong><span class="desc">Start private conversations with MAO personnel for specific farm concerns.</span></div>
                <span class="status-pill">Open Messages</span>
            </a>
        </section>

        <div class="dashboard-section-label" data-reveal="fade-up" data-reveal-line>
            <div>
                <div class="fc-eyebrow on-light">Guidance</div>
                <p class="section-note">Published advisories and the most relevant field note.</p>
            </div>
            <a class="fc-section-link" href="{{ route('planting-advisories.index') }}">
                View All
                <svg width="12" height="12" viewBox="0 0 14 14" fill="none"><path d="M2 7h10M7 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
        </div>

        <div class="dashboard-focus-grid">
        <div class="row g-4 mb-0">
            <div class="col-xl-8">
                <div class="farmer-panel h-100" data-reveal="fade-right">
                    <div class="farmer-panel-header">
                        <div>
                            <h2 class="farmer-panel-title">Latest Planting Advisories</h2>
                            <p class="farmer-panel-sub">Published guidance from MAO Personnel.</p>
                        </div>
                        <a class="fc-btn fc-btn-outline" href="{{ route('planting-advisories.index') }}">View All</a>
                    </div>
                    <div class="farmer-panel-body">
                        <div class="row g-3">
                            @forelse($advisories as $advisory)
                                <div class="col-md-6">
                                    <div class="advisory-card">
                                        <div class="d-flex justify-content-between gap-2">
                                            <span class="status-pill {{ match(strtolower((string) $advisory->type)) {
                                                'warning' => 'warn',
                                                'irrigation' => 'info',
                                                'climate' => 'neutral',
                                                default => '',
                                            } }}">{{ $advisory->type }}</span>
                                            <span class="small mono" style="color: var(--fc-ink-mid);">{{ $advisory->created_at?->format('M d') }}</span>
                                        </div>
                                        <strong>{{ $advisory->title }}</strong>
                                        <div class="list-text">{{ str($advisory->content)->limit(120) }}</div>
                                        <div class="list-meta">{{ $advisory->target_barangay ?: 'All barangays' }}</div>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12"><div class="empty-soft"><strong>No advisories yet</strong><div class="small mt-1" style="color: var(--fc-ink-light);">Published planting advisories will appear here.</div></div></div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="farmer-panel h-100" data-reveal="fade-left" data-reveal-delay="120">
                    <div class="farmer-panel-header">
                        <div>
                            <h2 class="farmer-panel-title">Today&apos;s Field Guide</h2>
                            <p class="farmer-panel-sub">Quick action based on latest records.</p>
                        </div>
                    </div>
                    <div class="farmer-panel-body">
                        @if($weatherGuidance)
                            <div class="list-mark mb-3">WX</div>
                            <div class="list-title" data-weather-guidance-title>{{ $weatherGuidance['title'] }}</div>
                            <div class="list-text" data-weather-guidance-message>{{ $weatherGuidance['message'] }}</div>
                            <div class="list-meta">Deterministic guidance from current weather values</div>
                        @elseif($latestAdvisory)
                            <div class="list-mark mb-3">ADV</div>
                            <div class="list-title">{{ $latestAdvisory->title }}</div>
                            <div class="list-text">{{ str($latestAdvisory->content)->limit(180) }}</div>
                            <div class="list-meta">{{ $latestAdvisory->type }} advisory</div>
                        @elseif($climateSummary)
                            <div class="list-mark mb-3"><svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M5.5 10.5a3 3 0 0 1 .6-5.9 4 4 0 0 1 7.7.7A2.7 2.7 0 0 1 13.5 10.5h-8Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/></svg></div>
                            <div class="list-title">Review latest climate conditions</div>
                            <div class="list-text">Season is {{ $climateSummary->season }} with {{ $climateSummary->rainfall }} mm rainfall recorded.</div>
                            <div class="list-meta">Source: {{ $climateSummary->source }}</div>
                        @else
                            <div class="empty-soft"><strong>No guide available</strong><div class="small mt-1" style="color: var(--fc-ink-light);">Advisories and climate records will appear here once published.</div></div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <details class="dashboard-group" data-reveal="scale">
            <summary>
                <div class="dashboard-group-summary-main">
                    <span class="list-mark">
                        <svg width="16" height="16" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="6" r="2.8" stroke="currentColor" stroke-width="1.4"/><path d="M3 15c.7-3.4 2.8-5.2 6-5.2s5.3 1.8 6 5.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    </span>
                    <div>
                        <h2 class="dashboard-group-title">Updates And Profile</h2>
                        <p class="dashboard-group-note">Community posts, message access, and your registered farm details.</p>
                    </div>
                </div>
                <span class="dashboard-group-toggle">
                    <svg width="12" height="12" viewBox="0 0 14 14" fill="none"><path d="M2 7h10M7 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
            </summary>
            <div class="dashboard-group-body">
        <div class="row g-4 mb-0">
            <div class="col-xl-4">
                <div class="farmer-panel h-100" data-reveal="fade-up">
                    <div class="farmer-panel-header">
                        <div>
                            <h2 class="farmer-panel-title">Community Posts</h2>
                            <p class="farmer-panel-sub">MAO updates, programs, activities, and announcements.</p>
                        </div>
                        <a class="fc-btn fc-btn-outline" href="{{ route('community-feed.index') }}">Open Feed</a>
                    </div>
                    <div class="farmer-panel-body list-compact">
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
                            <div class="empty-soft"><strong>No community posts</strong><div class="small mt-1" style="color: var(--fc-ink-light);">MAO feed updates will appear here.</div></div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="farmer-panel h-100" data-reveal="fade-up" data-reveal-delay="80">
                    <div class="farmer-panel-header">
                        <div>
                            <h2 class="farmer-panel-title">Community and Messages</h2>
                            <p class="farmer-panel-sub">MAO posts and private conversations.</p>
                        </div>
                        <a class="fc-btn fc-btn-outline" href="{{ route('community-feed.index') }}">Open Feed</a>
                    </div>
                    <div class="farmer-panel-body">
                        <div class="quick-grid" style="grid-template-columns:1fr;">
                            <a class="quick-action" href="{{ route('community-feed.index') }}" data-reveal="fade-up"><strong>Community Feed</strong><span>View MAO updates, activities, programs, photos, videos, comments, and reactions.</span></a>
                            <a class="quick-action" href="{{ route('messages.index') }}" data-reveal="fade-up" data-reveal-delay="70"><strong>Messages</strong><span>Start a private conversation with MAO personnel.</span></a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="farmer-panel h-100" data-reveal="fade-up" data-reveal-delay="160">
                    <div class="farmer-panel-header">
                        <div>
                            <h2 class="farmer-panel-title">Farm Profile Snapshot</h2>
                            <p class="farmer-panel-sub">Your registered farmer details.</p>
                        </div>
                        <a class="fc-btn fc-btn-outline" href="{{ route('profile.edit') }}">Edit</a>
                    </div>
                    <div class="farmer-panel-body">
                        <div class="farmer-list-item pt-0">
                            <div class="list-mark"><svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M9 16s5.5-5.1 5.5-9A5.5 5.5 0 0 0 3.5 7c0 3.9 5.5 9 5.5 9Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><circle cx="9" cy="7" r="2" stroke="currentColor" stroke-width="1.3"/></svg></div>
                            <div><div class="list-title">{{ $profile?->barangay ?? auth()->user()->barangay ?? 'Barangay not set' }}</div><div class="list-text">Registered barangay</div></div>
                        </div>
                        <div class="farmer-list-item">
                            <div class="list-mark"><svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M2.5 15.5V8l6.5-5.5L15.5 8v7.5" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M6 15.5v-5h6v5" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/></svg></div>
                            <div><div class="list-title">{{ $profile?->farm_area ? number_format($profile->farm_area, 2).' ha' : 'Farm area not set' }}</div><div class="list-text">Farm area</div></div>
                        </div>
                        <div class="farmer-list-item">
                            <div class="list-mark"><svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M3 9a6 6 0 0 1 10.5-4M3 5v4h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/><path d="M15 9a6 6 0 0 1-10.5 4M15 13v-4h-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                            <div><div class="list-title">{{ $profile?->farm_type ?? 'Farm type not set' }}</div><div class="list-text">Farm irrigation type</div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
            </div>
        </details>

        <details class="dashboard-group" data-reveal="scale" data-reveal-delay="100">
            <summary>
                <div class="dashboard-group-summary-main">
                    <span class="list-mark">
                        <svg width="16" height="16" viewBox="0 0 18 18" fill="none"><rect x="6" y="1.5" width="6" height="15" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M8.3 13.7h1.4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    </span>
                    <div>
                        <h2 class="dashboard-group-title">Mobile Access</h2>
                        <p class="dashboard-group-note">All farmer modules in one place.</p>
                    </div>
                </div>
                <span class="dashboard-group-toggle">
                    <svg width="12" height="12" viewBox="0 0 14 14" fill="none"><path d="M2 7h10M7 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
            </summary>
            <div class="dashboard-group-body">
        <div class="farmer-panel" data-reveal="fade-up">
            <div class="farmer-panel-header">
                <div>
                    <h2 class="farmer-panel-title">Farmer Quick Access</h2>
                    <p class="farmer-panel-sub">Open the modules available to your role.</p>
                </div>
            </div>
            <div class="farmer-panel-body">
                <div class="quick-grid" data-reveal-stagger>
                    <a class="quick-action" href="{{ route('climate-records.index') }}" data-reveal="fade-up"><strong>Climate Records</strong><span>Review rainfall, temperature, humidity, wind, and season records.</span></a>
                    <a class="quick-action" href="{{ route('heatmap-areas.index') }}" data-reveal="fade-up"><strong>Barangay Heat Map</strong><span>Review climate and production risk by barangay.</span></a>
                    <a class="quick-action" href="{{ route('ai-chat.index') }}" data-reveal="fade-up"><strong>Climora AI</strong><span>Ask questions and get weather, yield, planting, irrigation, and warning guidance.</span></a>
                    <a class="quick-action" href="{{ route('planting-advisories.index') }}" data-reveal="fade-up"><strong>Planting Advisories</strong><span>Read MAO guidance for planting, irrigation, harvesting, and climate risks.</span></a>
                    <a class="quick-action" href="{{ route('community-feed.index') }}" data-reveal="fade-up"><strong>Community Feed</strong><span>React and comment on MAO posts about updates, programs, and activities.</span></a>
                    <a class="quick-action" href="{{ route('messages.index') }}" data-reveal="fade-up"><strong>Messages</strong><span>Send private questions and attachments to MAO personnel.</span></a>
                </div>
            </div>
        </div>
            </div>
        </details>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
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
                const provider = payload.provider || payload.source || weatherProvider || 'Weather provider unavailable';
                if (provider === 'Unavailable') return 'Weather unavailable';

                const parts = [provider];
                if (payload.stale) parts.push('stored data');

                return parts.join(' ');
            };
            const setWeatherStatus = (label) => text('[data-weather-status]', label);
            const updateLiveWeatherTimestamp = () => {
                const providerLabel = weatherProvider || 'Weather provider unavailable';
                text('[data-weather-provider]', providerLabel);
                return;
                const now = new Date();
                const provider = weatherProvider || 'Open-Meteo';

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
                text('[data-weather-provider]', weatherProvider || 'Weather provider unavailable');
                text('[data-weather-freshness]', freshness);
                setWeatherStatus(weatherStatusLabel(seconds));
            };

            const renderForecast = (days) => {
                const target = document.querySelector('[data-weather-forecast]');
                if (!target || !Array.isArray(days)) return;

                target.innerHTML = days.map((day) => `
                    <div class="forecast-day" data-reveal="fade-up">
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
                const provider = payload.provider ?? 'Open-Meteo';
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

