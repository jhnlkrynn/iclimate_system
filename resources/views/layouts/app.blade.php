<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'iClimate') }}</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=DM+Serif+Display:ital@0;1&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            :root {
                --ic-green-950: #0d1f18;
                --ic-green-900: #122b20;
                --ic-green-800: #1a3a2a;
                --ic-green-700: #2d6a4f;
                --ic-green-500: #52b788;
                --ic-green-400: #74c69d;
                --ic-green-300: #95d5b2;
                --ic-green-100: #d8f3dc;
                --ic-green-50: #f0f7f4;
                --ic-amber: #e8a73d;
                --ic-gold: #e8a73d;
                --ic-gold-dark: #c6872a;
                --ic-gold-light: #fbebcf;
                --ic-sand: #f5f0e8;
                --ic-sand-dark: #e8e0d0;
                --ic-blue: #2f6f8f;
                --ic-coral: #d85b45;
                --ic-ink: #0d1f18;
                --ic-ink-mid: #3d5a48;
                --ic-muted: #6b8f71;
                --ic-border: #d4edda;
                --ic-paper: rgba(255,255,255,.94);
                --ic-panel: #f5f9f6;
                --ic-panel-strong: #e8f5ed;
                --ic-field: #ffffff;
                --ic-radius-sm: 4px;
                --ic-radius-md: 10px;
                --ic-radius-lg: 18px;
                --ic-radius-xl: 32px;
                --ic-radius-pill: 100px;
                --ic-shadow-sm: 0 1px 4px rgba(13,31,24,.08);
                --ic-shadow-md: 0 4px 20px rgba(13,31,24,.12);
                --ic-shadow-lg: 0 16px 56px rgba(13,31,24,.18);
                --ic-shadow-gold: 0 10px 28px rgba(232,167,61,.32);
                --sidebar-width: 300px;
            }
            html { background: var(--ic-green-50); }
            body {
                font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                min-height: 100vh;
                color: var(--ic-ink);
                background:
                    linear-gradient(115deg, rgba(240,247,244,.96), rgba(245,240,232,.9)),
                    radial-gradient(circle at top right, rgba(82,183,136,.14), transparent 32rem),
                    linear-gradient(140deg, #f5f9f6 0%, #eef7ec 54%, #f5f0e8 100%);
            }
            body::before {
                content: "";
                position: fixed;
                inset: 0;
                pointer-events: none;
                z-index: -1;
                background:
                    radial-gradient(rgba(82,183,136,.14) 1px, transparent 1px);
                background-size: 30px 30px;
                mask-image: linear-gradient(90deg, transparent, #000 18%, #000 100%);
            }
            .app-main { margin-left: var(--sidebar-width); min-height: 100vh; }
            .page-shell { padding: 1.25rem; }
            .sidebar-fixed {
                position: fixed; inset: 0 auto 0 0; width: var(--sidebar-width); z-index: 1030; overflow-y: auto;
                background: var(--ic-green-950);
            }
            .sidebar-brand { position: relative; }
            .sidebar-location { display: flex; align-items: center; gap: 6px; font-family: 'DM Mono', monospace; font-size: .68rem; font-weight: 500; letter-spacing: .08em; text-transform: uppercase; color: var(--ic-green-400); }
            .sidebar-location .pulse-dot { width: 6px; height: 6px; border-radius: 999px; background: var(--ic-green-400); box-shadow: 0 0 0 4px rgba(116,198,157,.2); flex-shrink: 0; }
            .sidebar-tagline { font-family: 'Inter', sans-serif; font-size: .74rem; color: rgba(255,255,255,.45); line-height: 1.5; margin-top: .3rem; }
            .sidebar-brand-row { display: flex; align-items: center; gap: 10px; margin-bottom: .5rem; }
            .sidebar-logo-icon { width: 34px; height: 34px; object-fit: contain; flex-shrink: 0; }
            .sidebar-wordmark { font-family: 'Inter', sans-serif; font-weight: 800; font-size: 1.25rem; color: var(--ic-green-400); letter-spacing: -0.01em; }
            .sidebar-brand-underline { width: 66px; height: 2px; background: var(--ic-green-500); border-radius: 2px; position: relative; margin: 0 0 1rem; }
            .sidebar-brand-underline::after { content: ''; position: absolute; right: -3px; top: 50%; transform: translateY(-50%); width: 6px; height: 6px; border-radius: 50%; background: var(--ic-green-400); }
            .sidebar-brand--large { text-align: center; }
            .sidebar-brand--large .sidebar-logo-large { width: 128px; height: auto; margin: 0 auto .75rem; }
            .sidebar-brand--large .sidebar-wordmark-lg { font-family: 'Inter', sans-serif; font-weight: 800; font-size: 1.85rem; color: var(--ic-green-400); letter-spacing: -0.01em; }
            .sidebar-brand--large .sidebar-brand-underline { margin: .5rem auto 0; }
            .sidebar-foot { margin-top: auto; padding: 1rem 1.4rem 1.25rem; font-size: .72rem; color: rgba(255,255,255,.32); }
            .sidebar-link {
                position: relative;
                color: rgba(255,255,255,.78); text-decoration: none; display: flex; align-items: center; justify-content: space-between; gap: .5rem;
                border-radius: var(--ic-radius-pill); padding: .72rem 1rem; transition: background .15s ease, color .15s ease;
            }
            .sidebar-link:hover { color: #fff; background: rgba(255,255,255,.09); }
            .sidebar-link.active { color: #fff; background: rgba(255,255,255,.1); font-weight: 600; }
            .sidebar-link.active::before { content: ''; position: absolute; left: -.7rem; top: 50%; transform: translateY(-50%); width: 3px; height: 60%; border-radius: 3px; background: var(--ic-green-400); }
            .sidebar-link.active .sidebar-icon { background: var(--ic-green-500); color: #fff; }
            .sidebar-icon { width: 30px; height: 30px; border-radius: 50%; display: inline-grid; place-items: center; background: rgba(255,255,255,.1); color: rgba(255,255,255,.85); flex-shrink: 0; }
            .sidebar-badge { background: var(--ic-gold); color: var(--ic-ink); font-family: 'DM Mono', monospace; font-size: .68rem; font-weight: 700; border-radius: 999px; min-width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; padding: 0 .4rem; flex-shrink: 0; }
            .sidebar-link-arrow { flex-shrink: 0; opacity: 0; transform: translateX(-3px); transition: opacity .15s ease, transform .15s ease; }
            .sidebar-link:hover .sidebar-link-arrow { opacity: .55; transform: translateX(0); }
            .sidebar-link.active .sidebar-link-arrow { opacity: .9; transform: translateX(0); color: #fff; }
            .sidebar-section { color: rgba(255,255,255,.42); font-family: 'DM Mono', monospace; font-size: .66rem; font-weight: 500; text-transform: uppercase; letter-spacing: .1em; margin: 1.4rem 1rem .5rem; }
            .sidebar-ai-card { margin: .75rem 1rem 1rem; padding: .85rem 1rem; border-radius: var(--ic-radius-lg); background: linear-gradient(135deg, rgba(82,183,136,.16), rgba(82,183,136,.05)); border: 1px solid rgba(116,198,157,.35); display: flex; align-items: center; gap: .75rem; cursor: pointer; transition: background .15s ease, border-color .15s ease; }
            .sidebar-ai-card:hover { background: linear-gradient(135deg, rgba(82,183,136,.24), rgba(82,183,136,.08)); border-color: rgba(116,198,157,.55); }
            .sidebar-ai-icon { width: 40px; height: 40px; border-radius: 50%; background: rgba(82,183,136,.18); display: flex; align-items: center; justify-content: center; color: var(--ic-green-400); flex-shrink: 0; }
            .sidebar-ai-title { color: #fff; font-weight: 700; font-size: .88rem; }
            .sidebar-ai-sub { color: rgba(255,255,255,.5); font-size: .74rem; }
            .topbar { position: sticky; top: 0; z-index: 1020; background: rgba(245,249,246,.88); backdrop-filter: blur(16px); border-bottom: 1px solid rgba(212,237,218,.95); box-shadow: 0 .55rem 1.4rem rgba(13,31,24,.05); }
            .topbar-inner { min-height: 74px; }
            .weather-strip { display: flex; align-items: center; gap: .65rem; padding: .52rem .75rem; border: 1px solid rgba(183,228,199,.9); border-radius: 8px; background: linear-gradient(90deg, #ffffff, var(--ic-green-50)); }
            .weather-strip .pulse { width: 10px; height: 10px; border-radius: 999px; background: var(--ic-green-500); box-shadow: 0 0 0 5px rgba(82,183,136,.14); }
            h1, h2, h3, h4, .card-header .fw-semibold, .card-header .fw-bold { font-family: 'DM Serif Display', Georgia, serif; font-weight: 400; letter-spacing: -0.01em; }
            .card, .glass-card { background: linear-gradient(145deg, var(--ic-paper), #f7fbf8); border: 1.5px solid var(--ic-sand-dark); border-radius: var(--ic-radius-lg); box-shadow: var(--ic-shadow-sm); transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
            .card:hover, .module-tile:hover, .climate-chip:hover, .risk-card:hover { transform: translateY(-3px); box-shadow: var(--ic-shadow-md); border-color: rgba(82,183,136,.45); }
            .card.no-lift:hover { transform: none; }
            .card-header { border-top-left-radius: var(--ic-radius-lg) !important; border-top-right-radius: var(--ic-radius-lg) !important; }
            .form-control, .form-select, .alert { border-radius: var(--ic-radius-md); }
            .btn { border-radius: var(--ic-radius-pill); font-family: 'Inter', sans-serif; font-weight: 700; letter-spacing: .01em; transition: transform .16s ease, box-shadow .16s ease, background-color .16s ease; }
            .btn:hover { transform: translateY(-1px); }
            .btn:active { transform: translateY(0); }
            .btn:focus-visible, .form-control:focus, .form-select:focus, .form-check-input:focus { outline: 3px solid rgba(82,183,136,.24); outline-offset: 2px; }
            .btn-primary { --bs-btn-bg: var(--ic-green-800); --bs-btn-border-color: var(--ic-green-800); --bs-btn-hover-bg: var(--ic-green-700); --bs-btn-hover-border-color: var(--ic-green-700); box-shadow: 0 .55rem 1.2rem rgba(26,58,42,.2); }
            .btn-outline-primary { --bs-btn-color: var(--ic-green-700); --bs-btn-border-color: var(--ic-green-500); --bs-btn-hover-bg: var(--ic-green-700); --bs-btn-hover-border-color: var(--ic-green-700); }
            .btn-light { --bs-btn-bg: var(--ic-gold); --bs-btn-border-color: var(--ic-gold); --bs-btn-color: var(--ic-ink); --bs-btn-hover-bg: var(--ic-gold-dark); --bs-btn-hover-border-color: var(--ic-gold-dark); --bs-btn-hover-color: var(--ic-ink); --bs-btn-active-bg: var(--ic-gold-dark); --bs-btn-active-border-color: var(--ic-gold-dark); box-shadow: var(--ic-shadow-gold); }
            .form-label { font-family: 'DM Mono', monospace; font-size: .7rem; font-weight: 500; letter-spacing: .05em; text-transform: uppercase; color: var(--ic-muted); }
            .form-control, .form-select { border-color: var(--ic-sand-dark); background-color: var(--ic-field); }
            .form-control:hover, .form-select:hover { border-color: rgba(82,183,136,.58); background-color: #fff; }
            .form-control:focus, .form-select:focus { border-color: var(--ic-green-500); box-shadow: 0 0 0 .22rem rgba(82,183,136,.14); }
            .page-hero { position: relative; overflow: hidden; border-radius: var(--ic-radius-xl); padding: 1.75rem 1.85rem; margin-bottom: 1.25rem; color: #fff; background: linear-gradient(145deg, var(--ic-green-950) 0%, var(--ic-green-800) 62%, #163324 100%); box-shadow: var(--ic-shadow-lg); }
            .page-hero::before {
                content: "";
                position: absolute; inset: 0;
                background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.05'/%3E%3C/svg%3E"),
                    radial-gradient(ellipse at 88% -10%, rgba(82,183,136,.16) 0%, transparent 60%);
                pointer-events: none;
            }
            .page-hero > * { position: relative; z-index: 1; }
            .page-hero h1, .sidebar-brand .h4 { font-family: 'DM Serif Display', Georgia, serif; font-weight: 400; letter-spacing: -0.01em; }
            .eyebrow { display: inline-flex; align-items: center; gap: 8px; font-family: 'DM Mono', monospace; font-size: .7rem; font-weight: 500; text-transform: uppercase; letter-spacing: .12em; color: var(--ic-green-400); }
            .eyebrow::before { content: ''; display: block; width: 18px; height: 1px; background: var(--ic-green-400); }
            .stat-card { overflow: hidden; position: relative; min-height: 136px; }
            .stat-card::before { content: ""; position: absolute; inset: 0 0 auto; height: 5px; background: var(--accent, var(--ic-green-500)); }
            .stat-card::after { content: ""; position: absolute; inset: auto 0 0; height: 38px; background: repeating-linear-gradient(90deg, rgba(82,183,136,.08) 0 8px, transparent 8px 16px); }
            .stat-label { color: var(--ic-muted); font-family: 'DM Mono', monospace; font-size: .68rem; font-weight: 500; text-transform: uppercase; letter-spacing: .06em; }
            .stat-value { color: var(--ic-ink); font-family: 'DM Serif Display', serif; font-size: clamp(1.6rem, 3vw, 2.2rem); line-height: 1; margin-top: .45rem; letter-spacing: -0.01em; }
            .stat-dot { width: 2.55rem; height: 2.55rem; border-radius: 8px; background: color-mix(in srgb, var(--accent, var(--ic-green-500)) 18%, #edf7e7); border: 1px solid color-mix(in srgb, var(--accent, var(--ic-green-500)) 35%, #edf7e7); position: relative; z-index: 1; }
            .stat-dot::after { content: ""; position: absolute; inset: 9px 7px; border-top: 2px solid var(--accent, var(--ic-green-500)); border-bottom: 2px solid var(--accent, var(--ic-green-500)); opacity: .8; }
            .tone-green { --accent: var(--ic-green-500); } .tone-blue { --accent: var(--ic-green-700); } .tone-amber { --accent: var(--ic-amber); } .tone-coral { --accent: var(--ic-coral); }
            .insight-panel { position: relative; overflow: hidden; }
            .insight-panel::before { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 5px; background: var(--accent, var(--ic-green-500)); }
            .module-tile { border: 1.5px solid var(--ic-sand-dark); border-radius: var(--ic-radius-md); padding: 1rem; background: linear-gradient(135deg, #ffffff, var(--ic-green-50)); min-height: 96px; transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
            .module-tile .meter { height: 7px; border-radius: 999px; background: #e8eef6; overflow: hidden; }
            .module-tile .meter > span { display: block; height: 100%; width: min(var(--meter, 45%), 100%); background: linear-gradient(90deg, var(--ic-green-500), var(--ic-green-700)); }
            .update-list > * + * { border-top: 1px solid var(--ic-border); }
            .climate-chip { border: 1.5px solid var(--ic-sand-dark); border-radius: var(--ic-radius-md); padding: 1rem; min-height: 112px; background: linear-gradient(145deg, #ffffff, var(--chip-bg, var(--ic-green-50))); transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
            .table { --bs-table-bg: transparent; }
            .table thead th { color: var(--ic-muted); font-family: 'DM Mono', monospace; font-size: .68rem; text-transform: uppercase; letter-spacing: .07em; font-weight: 500; white-space: nowrap; background: var(--ic-green-50); }
            .table td, .table th { vertical-align: middle; padding: .95rem 1rem; }
            .table-hover tbody tr { transition: background .12s ease; }
            .table tbody tr { transition: background .15s ease, transform .15s ease; }
            .table-hover tbody tr:hover { background: rgba(240,247,244,.95); transform: scale(1.003); }
            .badge { border-radius: 999px; padding: .45rem .65rem; font-family: 'DM Mono', monospace; font-weight: 500; letter-spacing: .02em; }
            .empty-state { border: 1.5px dashed var(--ic-sand-dark); background: linear-gradient(135deg, #ffffff, var(--ic-green-50)); border-radius: var(--ic-radius-lg); }
            .loading-overlay { position: fixed; inset: 0; background: rgba(240,247,244,.68); backdrop-filter: blur(3px); z-index: 2000; display: none; align-items: center; justify-content: center; }
            .loading-overlay.show { display: flex; }
            .loading-overlay .card { min-width: 220px; }
            .page-progress { position: fixed; inset: 0 0 auto; height: 3px; z-index: 2100; pointer-events: none; background: transparent; opacity: 0; transition: opacity .12s ease; }
            .page-progress::before { content: ""; display: block; width: 42%; height: 100%; background: linear-gradient(90deg, var(--ic-green-500), var(--ic-gold)); transform: translateX(-100%); animation: pageProgress 1s ease-in-out infinite; }
            .page-progress.show { opacity: 1; }
            .is-loading-action { opacity: .72; pointer-events: none; }
            @keyframes pageProgress {
                0% { transform: translateX(-110%); }
                55% { transform: translateX(80vw); }
                100% { transform: translateX(110vw); }
            }
            .bg-white, .table-light { background-color: #ffffff !important; }
            .text-bg-light { color: var(--ic-ink) !important; background-color: var(--ic-green-50) !important; }
            .border-top, .border-bottom, .border-start, .border-end, .border { border-color: rgba(212,237,218,.98) !important; }
            .filter-panel { background: linear-gradient(135deg, #ffffff, var(--ic-green-50)); border: 1.5px solid var(--ic-sand-dark); }
            .soft-section { background: linear-gradient(135deg, #ffffff, #f5f9f6); border: 1.5px solid var(--ic-sand-dark); border-radius: var(--ic-radius-md); box-shadow: 0 .8rem 1.8rem rgba(13,31,24,.05); }
            .action-cluster { background: rgba(240,247,244,.76); border: 1.5px solid var(--ic-sand-dark); border-radius: var(--ic-radius-md); padding: .35rem; }
            .risk-card { position: relative; overflow: hidden; transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
            .details-list dt { color: #496071; font-family: 'DM Mono', monospace; font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; }
            .details-list dd { background: rgba(240,247,244,.75); border: 1.5px solid var(--ic-sand-dark); border-radius: var(--ic-radius-md); padding: .7rem .85rem; }
            .interactive-card { cursor: default; }
            .interactive-card:hover .stat-label, .module-tile:hover .small { color: var(--ic-green-700) !important; }
            @media (min-width: 1400px) { .page-shell { padding: 1.75rem 2rem; } }
            @media (max-width: 991.98px) { .app-main { margin-left: 0; } .sidebar-fixed { display: none; } .page-shell { padding: .9rem; } .topbar-inner { min-height: 64px; } .page-hero { padding: 1rem; } }
            @media (max-width: 767.98px) {
                body { background: linear-gradient(180deg, #f7fbf8, #eef7ec); }
                .topbar { padding-inline: .75rem !important; }
                .topbar-inner { gap: .65rem !important; }
                .page-shell { padding: .75rem; }
                .page-hero h1 { font-size: 1.45rem; line-height: 1.15; }
                .card-body { padding: 1rem; }
                .table-responsive { border-radius: 8px; }
                .action-cluster { display: grid !important; gap: .35rem; }
                .action-cluster .btn { width: 100%; }
                .stat-value { font-size: 1.65rem; }
            }
            @media (max-width: 575.98px) {
                .topbar .container-fluid { align-items: flex-start; }
                .topbar .btn { padding: .42rem .55rem; font-size: .78rem; }
                .weather-strip { display: none !important; }
                .page-hero { margin-bottom: .85rem; }
                .page-hero .btn, .filter-panel .btn, .filter-panel a.btn { width: 100%; }
                .filter-panel form > [class*="col-"] { width: 100%; }
                .card, .glass-card, .module-tile, .climate-chip, .soft-section { box-shadow: 0 .45rem 1rem rgba(13,31,24,.06); }
            }
        </style>
    </head>
    <body>
        @include('layouts.sidebar')
        <div class="app-main">
            @include('layouts.navigation')
            <main class="page-shell">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                        <strong>Success.</strong> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                        <strong>Error.</strong> {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger shadow-sm">
                        <strong>Please check the form.</strong>
                        <ul class="mb-0 mt-2">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                {{ $slot }}
            </main>
        </div>
        <div id="loadingOverlay" class="loading-overlay">
            <div class="card border-0 shadow-sm"><div class="card-body d-flex align-items-center gap-3"><div class="spinner-border text-primary" role="status" aria-hidden="true"></div><div class="fw-semibold">Loading...</div></div></div>
        </div>
        <div id="pageProgress" class="page-progress" aria-hidden="true"></div>
        @auth
            @include('components.ai-chat-widget')
        @endauth
        <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>
