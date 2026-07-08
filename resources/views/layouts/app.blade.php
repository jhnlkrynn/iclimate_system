<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'iClimate') }}</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
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
                --ic-amber: #ffd166;
                --ic-coral: #d85b45;
                --ic-ink: #1b2b23;
                --ic-muted: #5a7a64;
                --ic-border: #d4edda;
                --ic-paper: rgba(255,255,255,.94);
                --ic-panel: #f5f9f6;
                --ic-panel-strong: #e8f5ed;
                --ic-field: #ffffff;
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
                background:
                    radial-gradient(circle at 20% 0%, rgba(82,183,136,.18), transparent 18rem),
                    radial-gradient(circle at 90% 80%, rgba(116,198,157,.12), transparent 16rem),
                    repeating-linear-gradient(150deg, rgba(255,255,255,.045) 0 1px, transparent 1px 22px),
                    linear-gradient(178deg, var(--ic-green-950) 0%, var(--ic-green-900) 48%, #163324 100%);
                box-shadow: 1rem 0 2.4rem rgba(13,31,24,.22);
            }
            .sidebar-brand { border-bottom: 1px solid rgba(255,255,255,.16); background: rgba(255,255,255,.08); position: relative; overflow: hidden; }
            .sidebar-brand::after { content: ""; position: absolute; inset: auto 1.4rem 1rem 1.4rem; height: 5px; background: linear-gradient(90deg, var(--ic-amber), var(--ic-green-500), var(--ic-green-300)); border-radius: 999px; }
            .brand-chip, .brand-mark {
                width: 58px; height: 58px; border-radius: 8px; position: relative; flex: 0 0 auto;
                background: linear-gradient(135deg, #fff 0 45%, #c7efd3 45% 68%, var(--ic-green-500) 68% 100%);
                box-shadow: inset 0 0 0 1px rgba(255,255,255,.7), 0 .7rem 1.6rem rgba(0,0,0,.16);
            }
            .brand-chip::after, .brand-mark::after { content: ""; position: absolute; left: 10px; right: 10px; bottom: 13px; height: 7px; border-top: 2px solid rgba(13,95,62,.75); border-bottom: 2px solid rgba(82,183,136,.7); }
            .sidebar-logo-img {
                width: 178px;
                max-width: 100%;
                height: auto;
                display: block;
                object-fit: contain;
                filter: brightness(0) invert(1);
            }
            .sidebar-link {
                color: rgba(255,255,255,.82); text-decoration: none; display: flex; align-items: center; justify-content: space-between;
                border-radius: 8px; padding: .78rem .85rem; transition: transform .15s ease, background .15s ease, color .15s ease;
                border: 1px solid transparent;
            }
            .sidebar-link:hover, .sidebar-link.active { color: #fff; background: rgba(255,255,255,.13); transform: translateX(3px); border-color: rgba(255,255,255,.16); }
            .sidebar-link.active { color: var(--ic-green-100); box-shadow: inset 4px 0 0 var(--ic-green-500), 0 .7rem 1.5rem rgba(0,0,0,.12); }
            .sidebar-icon { width: 28px; height: 28px; border-radius: 8px; display: inline-grid; place-items: center; background: rgba(255,255,255,.12); font-size: .72rem; font-weight: 900; color: #fff; }
            .sidebar-section { color: rgba(255,255,255,.58); font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; margin: 1.25rem .9rem .45rem; }
            .sidebar-group { border: 1px solid rgba(255,255,255,.12); border-radius: 8px; background: rgba(255,255,255,.055); overflow: hidden; }
            .sidebar-group summary {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: .75rem;
                padding: .72rem .85rem;
                color: rgba(255,255,255,.82);
                cursor: pointer;
                list-style: none;
                font-weight: 800;
            }
            .sidebar-group summary::-webkit-details-marker { display: none; }
            .sidebar-group[open] summary { color: #fff; background: rgba(255,255,255,.08); }
            .sidebar-group-caret { font-size: .75rem; transition: transform .16s ease; }
            .sidebar-group[open] .sidebar-group-caret { transform: rotate(90deg); }
            .sidebar-group-links { display: grid; gap: .25rem; padding: .35rem; border-top: 1px solid rgba(255,255,255,.1); }
            .sidebar-group-links .sidebar-link { padding: .66rem .7rem; }
            .topbar { position: sticky; top: 0; z-index: 1020; background: rgba(245,249,246,.88); backdrop-filter: blur(16px); border-bottom: 1px solid rgba(212,237,218,.95); box-shadow: 0 .55rem 1.4rem rgba(13,31,24,.05); }
            .topbar-inner { min-height: 74px; }
            .weather-strip { display: flex; align-items: center; gap: .65rem; padding: .52rem .75rem; border: 1px solid rgba(183,228,199,.9); border-radius: 8px; background: linear-gradient(90deg, #ffffff, var(--ic-green-50)); }
            .weather-strip .pulse { width: 10px; height: 10px; border-radius: 999px; background: var(--ic-green-500); box-shadow: 0 0 0 5px rgba(82,183,136,.14); }
            .card, .glass-card { background: linear-gradient(145deg, var(--ic-paper), #f7fbf8); border: 1px solid rgba(212,237,218,.98); border-radius: 8px; box-shadow: 0 .9rem 2.2rem rgba(13,31,24,.07); transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
            .card:hover, .module-tile:hover, .climate-chip:hover, .risk-card:hover { transform: translateY(-3px); box-shadow: 0 1.1rem 2.2rem rgba(13,31,24,.11); border-color: rgba(82,183,136,.45); }
            .card.no-lift:hover { transform: none; }
            .card-header { border-top-left-radius: 8px !important; border-top-right-radius: 8px !important; }
            .btn, .form-control, .form-select, .alert { border-radius: 8px; }
            .btn { font-weight: 800; letter-spacing: 0; transition: transform .16s ease, box-shadow .16s ease, background-color .16s ease; }
            .btn:hover { transform: translateY(-1px); }
            .btn:active { transform: translateY(0); }
            .btn:focus-visible, .form-control:focus, .form-select:focus, .form-check-input:focus { outline: 3px solid rgba(82,183,136,.24); outline-offset: 2px; }
            .btn-primary { --bs-btn-bg: var(--ic-green-800); --bs-btn-border-color: var(--ic-green-800); --bs-btn-hover-bg: var(--ic-green-700); --bs-btn-hover-border-color: var(--ic-green-700); box-shadow: 0 .55rem 1.2rem rgba(26,58,42,.2); }
            .btn-outline-primary { --bs-btn-color: var(--ic-green-700); --bs-btn-border-color: var(--ic-green-500); --bs-btn-hover-bg: var(--ic-green-700); --bs-btn-hover-border-color: var(--ic-green-700); }
            .form-control, .form-select { border-color: rgba(212,237,218,.98); background-color: var(--ic-field); }
            .form-control:hover, .form-select:hover { border-color: rgba(82,183,136,.58); background-color: #fff; }
            .form-control:focus, .form-select:focus { border-color: var(--ic-green-500); box-shadow: 0 0 0 .22rem rgba(82,183,136,.14); }
            .page-hero { position: relative; overflow: hidden; border-radius: 8px; padding: 1.35rem; margin-bottom: 1.25rem; color: #fff; background: linear-gradient(145deg, var(--ic-green-950) 0%, var(--ic-green-800) 62%, #163324 100%); box-shadow: 0 1rem 2rem rgba(13,31,24,.2); }
            .page-hero::before { content: ""; position: absolute; inset: 0; background: repeating-linear-gradient(130deg, rgba(255,255,255,.12) 0 1px, transparent 1px 22px); opacity: .9; }
            .page-hero::after { content: ""; position: absolute; left: 0; right: 0; bottom: 0; height: 8px; background: linear-gradient(90deg, var(--ic-amber), var(--ic-green-500), var(--ic-green-300), var(--ic-coral)); }
            .page-hero > * { position: relative; z-index: 1; }
            .page-hero h1, .sidebar-brand .h4 { font-family: 'Syne', sans-serif; letter-spacing: 0; }
            .eyebrow { font-size: .72rem; font-weight: 900; text-transform: uppercase; letter-spacing: .12em; color: rgba(255,255,255,.72); }
            .stat-card { overflow: hidden; position: relative; min-height: 136px; }
            .stat-card::before { content: ""; position: absolute; inset: 0 0 auto; height: 5px; background: var(--accent, var(--ic-green-500)); }
            .stat-card::after { content: ""; position: absolute; inset: auto 0 0; height: 38px; background: repeating-linear-gradient(90deg, rgba(82,183,136,.08) 0 8px, transparent 8px 16px); }
            .stat-label { color: var(--ic-muted); font-size: .72rem; font-weight: 900; text-transform: uppercase; letter-spacing: .06em; }
            .stat-value { color: var(--ic-ink); font-size: clamp(1.7rem, 3vw, 2.35rem); font-weight: 900; line-height: 1; margin-top: .45rem; }
            .stat-dot { width: 2.55rem; height: 2.55rem; border-radius: 8px; background: color-mix(in srgb, var(--accent, var(--ic-green-500)) 18%, #edf7e7); border: 1px solid color-mix(in srgb, var(--accent, var(--ic-green-500)) 35%, #edf7e7); position: relative; z-index: 1; }
            .stat-dot::after { content: ""; position: absolute; inset: 9px 7px; border-top: 2px solid var(--accent, var(--ic-green-500)); border-bottom: 2px solid var(--accent, var(--ic-green-500)); opacity: .8; }
            .tone-green { --accent: var(--ic-green-500); } .tone-blue { --accent: var(--ic-green-700); } .tone-amber { --accent: var(--ic-amber); } .tone-coral { --accent: var(--ic-coral); }
            .insight-panel { position: relative; overflow: hidden; }
            .insight-panel::before { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 5px; background: var(--accent, var(--ic-green-500)); }
            .module-tile { border: 1px solid rgba(212,237,218,.98); border-radius: 8px; padding: 1rem; background: linear-gradient(135deg, #ffffff, var(--ic-green-50)); min-height: 96px; transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
            .module-tile .meter { height: 7px; border-radius: 999px; background: #e8eef6; overflow: hidden; }
            .module-tile .meter > span { display: block; height: 100%; width: min(var(--meter, 45%), 100%); background: linear-gradient(90deg, var(--ic-green-500), var(--ic-green-700)); }
            .update-list > * + * { border-top: 1px solid var(--ic-border); }
            .climate-chip { border: 1px solid rgba(212,237,218,.98); border-radius: 8px; padding: 1rem; min-height: 112px; background: linear-gradient(145deg, #ffffff, var(--chip-bg, var(--ic-green-50))); transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
            .table { --bs-table-bg: transparent; }
            .table thead th { color: var(--ic-muted); font-size: .75rem; text-transform: uppercase; letter-spacing: .055em; white-space: nowrap; background: var(--ic-green-50); }
            .table td, .table th { vertical-align: middle; padding: .95rem 1rem; }
            .table-hover tbody tr { transition: background .12s ease; }
            .table tbody tr { transition: background .15s ease, transform .15s ease; }
            .table-hover tbody tr:hover { background: rgba(240,247,244,.95); transform: scale(1.003); }
            .badge { border-radius: 999px; padding: .45rem .65rem; }
            .empty-state { border: 1px dashed #aac7b0; background: linear-gradient(135deg, #ffffff, var(--ic-green-50)); border-radius: 8px; }
            .loading-overlay { position: fixed; inset: 0; background: rgba(240,247,244,.68); backdrop-filter: blur(3px); z-index: 2000; display: none; align-items: center; justify-content: center; }
            .loading-overlay.show { display: flex; }
            .loading-overlay .card { min-width: 220px; }
            .bg-white, .table-light { background-color: #ffffff !important; }
            .text-bg-light { color: var(--ic-ink) !important; background-color: var(--ic-green-50) !important; }
            .border-top, .border-bottom, .border-start, .border-end, .border { border-color: rgba(212,237,218,.98) !important; }
            .filter-panel { background: linear-gradient(135deg, #ffffff, var(--ic-green-50)); border: 1px solid rgba(212,237,218,.98); }
            .soft-section { background: linear-gradient(135deg, #ffffff, #f5f9f6); border: 1px solid rgba(212,237,218,.98); border-radius: 8px; box-shadow: 0 .8rem 1.8rem rgba(13,31,24,.05); }
            .action-cluster { background: rgba(240,247,244,.76); border: 1px solid rgba(212,237,218,.98); border-radius: 8px; padding: .35rem; }
            .risk-card { position: relative; overflow: hidden; transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
            .risk-card::after { content: ""; position: absolute; inset: auto 0 0; height: 7px; background: linear-gradient(90deg, var(--ic-green-500), var(--ic-amber), var(--ic-coral)); }
            .details-list dt { color: #496071; font-size: .78rem; text-transform: uppercase; letter-spacing: .06em; }
            .details-list dd { background: rgba(240,247,244,.75); border: 1px solid rgba(212,237,218,.98); border-radius: 8px; padding: .7rem .85rem; }
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
        @auth
            @include('components.ai-chat-widget')
        @endauth
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const overlay = document.getElementById('loadingOverlay');
                document.querySelectorAll('form[data-loading="true"]').forEach((form) => {
                    form.addEventListener('submit', () => {
                        overlay.classList.add('show');
                        form.querySelectorAll('button[type="submit"]').forEach((button) => {
                            button.disabled = true;
                            if (button.dataset.loadingText) button.textContent = button.dataset.loadingText;
                        });
                    });
                });
            });
        </script>
    </body>
</html>
