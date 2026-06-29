<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'iClimate') }}</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            :root {
                --ic-leaf: #1f8f55;
                --ic-leaf-deep: #0d5f3e;
                --ic-river: #1677b8;
                --ic-river-deep: #123f73;
                --ic-sun: #f4b63f;
                --ic-coral: #d85b45;
                --ic-mint: #dff6e5;
                --ic-sky: #e7f4ff;
                --ic-cream: #fff9ec;
                --ic-ink: #162033;
                --ic-muted: #66758a;
                --ic-border: #d8e3ef;
                --ic-paper: rgba(244,250,239,.94);
                --ic-panel: #eef7ec;
                --ic-panel-strong: #e4f0df;
                --ic-field: #f3f8ed;
                --sidebar-width: 300px;
            }
            html { background: #eef5f1; }
            body {
                min-height: 100vh;
                color: var(--ic-ink);
                background:
                    linear-gradient(115deg, rgba(237,247,232,.94), rgba(226,240,229,.92)),
                    repeating-linear-gradient(135deg, rgba(22,119,184,.08) 0 1px, transparent 1px 18px),
                    linear-gradient(140deg, #eaf4e7 0%, #dcecdf 54%, #f4edd2 100%);
            }
            body::before {
                content: "";
                position: fixed;
                inset: 0;
                pointer-events: none;
                z-index: -1;
                background:
                    linear-gradient(90deg, transparent 0 48px, rgba(31,143,85,.06) 48px 49px, transparent 49px 96px),
                    linear-gradient(0deg, transparent 0 48px, rgba(18,63,115,.05) 48px 49px, transparent 49px 96px);
                mask-image: linear-gradient(90deg, transparent, #000 18%, #000 100%);
            }
            .app-main { margin-left: var(--sidebar-width); min-height: 100vh; }
            .page-shell { padding: 1.25rem; }
            .sidebar-fixed {
                position: fixed; inset: 0 auto 0 0; width: var(--sidebar-width); z-index: 1030; overflow-y: auto;
                background:
                    repeating-linear-gradient(150deg, rgba(255,255,255,.05) 0 1px, transparent 1px 20px),
                    linear-gradient(178deg, #0d2d4d 0%, #114d69 42%, #0c6542 100%);
                box-shadow: 1rem 0 2.4rem rgba(18, 63, 115, .18);
            }
            .sidebar-brand { border-bottom: 1px solid rgba(255,255,255,.16); background: rgba(255,255,255,.08); position: relative; overflow: hidden; }
            .sidebar-brand::after { content: ""; position: absolute; inset: auto 1.4rem 1rem 1.4rem; height: 5px; background: linear-gradient(90deg, var(--ic-sun), var(--ic-leaf), var(--ic-river)); border-radius: 999px; }
            .brand-chip, .brand-mark {
                width: 46px; height: 46px; border-radius: 8px; position: relative; flex: 0 0 auto;
                background: linear-gradient(135deg, #fff 0 45%, #c7efd3 45% 64%, #8ed2ff 64% 100%);
                box-shadow: inset 0 0 0 1px rgba(255,255,255,.7), 0 .7rem 1.6rem rgba(0,0,0,.16);
            }
            .brand-chip::after, .brand-mark::after { content: ""; position: absolute; left: 8px; right: 8px; bottom: 10px; height: 6px; border-top: 2px solid rgba(13,95,62,.75); border-bottom: 2px solid rgba(22,119,184,.65); }
            .sidebar-link {
                color: rgba(255,255,255,.82); text-decoration: none; display: flex; align-items: center; justify-content: space-between;
                border-radius: 8px; padding: .78rem .85rem; transition: transform .15s ease, background .15s ease, color .15s ease;
                border: 1px solid transparent;
            }
            .sidebar-link:hover, .sidebar-link.active { color: #fff; background: rgba(255,255,255,.13); transform: translateX(3px); border-color: rgba(255,255,255,.16); }
            .sidebar-link.active { box-shadow: inset 4px 0 0 var(--ic-sun), 0 .7rem 1.5rem rgba(0,0,0,.12); }
            .sidebar-icon { width: 28px; height: 28px; border-radius: 8px; display: inline-grid; place-items: center; background: rgba(255,255,255,.12); font-size: .72rem; font-weight: 900; color: #fff; }
            .sidebar-section { color: rgba(255,255,255,.58); font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; margin: 1.25rem .9rem .45rem; }
            .topbar { position: sticky; top: 0; z-index: 1020; background: rgba(236,246,230,.9); backdrop-filter: blur(16px); border-bottom: 1px solid rgba(188,210,188,.9); box-shadow: 0 .55rem 1.4rem rgba(22,55,40,.05); }
            .topbar-inner { min-height: 74px; }
            .weather-strip { display: flex; align-items: center; gap: .65rem; padding: .52rem .75rem; border: 1px solid rgba(151,185,160,.65); border-radius: 8px; background: linear-gradient(90deg, #eaf6df, #dceef8); }
            .weather-strip .pulse { width: 10px; height: 10px; border-radius: 999px; background: var(--ic-leaf); box-shadow: 0 0 0 5px rgba(31,143,85,.12); }
            .card, .glass-card { background: linear-gradient(145deg, var(--ic-paper), #eaf5e6); border: 1px solid rgba(188,210,188,.95); border-radius: 8px; box-shadow: 0 .9rem 2.2rem rgba(20, 32, 51, .07); transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
            .card:hover, .module-tile:hover, .climate-chip:hover, .risk-card:hover { transform: translateY(-3px); box-shadow: 0 1.1rem 2.2rem rgba(20, 32, 51, .12); border-color: rgba(31,143,85,.38); }
            .card.no-lift:hover { transform: none; }
            .card-header { border-top-left-radius: 8px !important; border-top-right-radius: 8px !important; }
            .btn, .form-control, .form-select, .alert { border-radius: 8px; }
            .btn { font-weight: 800; letter-spacing: 0; transition: transform .16s ease, box-shadow .16s ease, background-color .16s ease; }
            .btn:hover { transform: translateY(-1px); }
            .btn:active { transform: translateY(0); }
            .btn:focus-visible, .form-control:focus, .form-select:focus, .form-check-input:focus { outline: 3px solid rgba(244,182,63,.28); outline-offset: 2px; }
            .btn-primary { --bs-btn-bg: var(--ic-leaf); --bs-btn-border-color: var(--ic-leaf); --bs-btn-hover-bg: var(--ic-leaf-deep); --bs-btn-hover-border-color: var(--ic-leaf-deep); box-shadow: 0 .55rem 1.2rem rgba(31,143,85,.22); }
            .btn-outline-primary { --bs-btn-color: var(--ic-river); --bs-btn-border-color: var(--ic-river); --bs-btn-hover-bg: var(--ic-river); --bs-btn-hover-border-color: var(--ic-river); }
            .form-control, .form-select { border-color: rgba(175,201,176,.95); background-color: var(--ic-field); }
            .form-control:hover, .form-select:hover { border-color: rgba(31,143,85,.48); background-color: #f7fbef; }
            .form-control:focus, .form-select:focus { border-color: var(--ic-river); box-shadow: 0 0 0 .22rem rgba(22,119,184,.12); }
            .page-hero { position: relative; overflow: hidden; border-radius: 8px; padding: 1.35rem; margin-bottom: 1.25rem; color: #fff; background: linear-gradient(125deg, #123f73 0%, #146b78 52%, #0d6a41 100%); box-shadow: 0 1rem 2rem rgba(18,63,115,.18); }
            .page-hero::before { content: ""; position: absolute; inset: 0; background: repeating-linear-gradient(130deg, rgba(255,255,255,.12) 0 1px, transparent 1px 22px); opacity: .9; }
            .page-hero::after { content: ""; position: absolute; left: 0; right: 0; bottom: 0; height: 8px; background: linear-gradient(90deg, var(--ic-sun), var(--ic-leaf), var(--ic-river), var(--ic-coral)); }
            .page-hero > * { position: relative; z-index: 1; }
            .eyebrow { font-size: .72rem; font-weight: 900; text-transform: uppercase; letter-spacing: .12em; color: rgba(255,255,255,.72); }
            .stat-card { overflow: hidden; position: relative; min-height: 136px; }
            .stat-card::before { content: ""; position: absolute; inset: 0 0 auto; height: 5px; background: var(--accent, var(--ic-leaf)); }
            .stat-card::after { content: ""; position: absolute; inset: auto 0 0; height: 38px; background: repeating-linear-gradient(90deg, rgba(22,119,184,.08) 0 8px, transparent 8px 16px); }
            .stat-label { color: var(--ic-muted); font-size: .72rem; font-weight: 900; text-transform: uppercase; letter-spacing: .06em; }
            .stat-value { color: var(--ic-ink); font-size: clamp(1.7rem, 3vw, 2.35rem); font-weight: 900; line-height: 1; margin-top: .45rem; }
            .stat-dot { width: 2.55rem; height: 2.55rem; border-radius: 8px; background: color-mix(in srgb, var(--accent, var(--ic-leaf)) 18%, #edf7e7); border: 1px solid color-mix(in srgb, var(--accent, var(--ic-leaf)) 35%, #edf7e7); position: relative; z-index: 1; }
            .stat-dot::after { content: ""; position: absolute; inset: 9px 7px; border-top: 2px solid var(--accent, var(--ic-leaf)); border-bottom: 2px solid var(--accent, var(--ic-leaf)); opacity: .8; }
            .tone-green { --accent: var(--ic-leaf); } .tone-blue { --accent: var(--ic-river); } .tone-amber { --accent: var(--ic-sun); } .tone-coral { --accent: var(--ic-coral); }
            .insight-panel { position: relative; overflow: hidden; }
            .insight-panel::before { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 5px; background: var(--accent, var(--ic-leaf)); }
            .module-tile { border: 1px solid rgba(188,210,188,.95); border-radius: 8px; padding: 1rem; background: linear-gradient(135deg, #edf7e7, #e3f0e5); min-height: 96px; transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
            .module-tile .meter { height: 7px; border-radius: 999px; background: #e8eef6; overflow: hidden; }
            .module-tile .meter > span { display: block; height: 100%; width: min(var(--meter, 45%), 100%); background: linear-gradient(90deg, var(--ic-leaf), var(--ic-river)); }
            .update-list > * + * { border-top: 1px solid var(--ic-border); }
            .climate-chip { border: 1px solid rgba(188,210,188,.95); border-radius: 8px; padding: 1rem; min-height: 112px; background: linear-gradient(145deg, #edf7e7, var(--chip-bg, var(--ic-sky))); transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
            .table { --bs-table-bg: transparent; }
            .table thead th { color: #516579; font-size: .75rem; text-transform: uppercase; letter-spacing: .055em; white-space: nowrap; background: #dfeee0; }
            .table td, .table th { vertical-align: middle; padding: .95rem 1rem; }
            .table-hover tbody tr { transition: background .12s ease; }
            .table tbody tr { transition: background .15s ease, transform .15s ease; }
            .table-hover tbody tr:hover { background: rgba(226,241,219,.95); transform: scale(1.003); }
            .badge { border-radius: 999px; padding: .45rem .65rem; }
            .empty-state { border: 1px dashed #aac7b0; background: linear-gradient(135deg, #edf7e7, #e2f1df); border-radius: 8px; }
            .loading-overlay { position: fixed; inset: 0; background: rgba(248,251,255,.68); backdrop-filter: blur(3px); z-index: 2000; display: none; align-items: center; justify-content: center; }
            .loading-overlay.show { display: flex; }
            .loading-overlay .card { min-width: 220px; }
            .bg-white, .table-light { background-color: #eaf5e6 !important; }
            .text-bg-light { color: var(--ic-ink) !important; background-color: #e2f1df !important; }
            .border-top, .border-bottom, .border-start, .border-end, .border { border-color: rgba(188,210,188,.95) !important; }
            .filter-panel { background: linear-gradient(135deg, #e7f3e0, #dbece0); border: 1px solid rgba(188,210,188,.95); }
            .soft-section { background: linear-gradient(135deg, #e7f3e0, #dfeee8); border: 1px solid rgba(188,210,188,.95); border-radius: 8px; box-shadow: 0 .8rem 1.8rem rgba(20, 32, 51, .06); }
            .action-cluster { background: rgba(226,241,219,.72); border: 1px solid rgba(188,210,188,.9); border-radius: 8px; padding: .35rem; }
            .risk-card { position: relative; overflow: hidden; transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
            .risk-card::after { content: ""; position: absolute; inset: auto 0 0; height: 7px; background: linear-gradient(90deg, var(--ic-leaf), var(--ic-sun), var(--ic-coral)); }
            .details-list dt { color: #496071; font-size: .78rem; text-transform: uppercase; letter-spacing: .06em; }
            .details-list dd { background: rgba(226,241,219,.55); border: 1px solid rgba(188,210,188,.75); border-radius: 8px; padding: .7rem .85rem; }
            .interactive-card { cursor: default; }
            .interactive-card:hover .stat-label, .module-tile:hover .small { color: var(--ic-leaf-deep) !important; }
            @media (min-width: 1400px) { .page-shell { padding: 1.75rem 2rem; } }
            @media (max-width: 991.98px) { .app-main { margin-left: 0; } .sidebar-fixed { display: none; } .page-shell { padding: .9rem; } .topbar-inner { min-height: 64px; } .page-hero { padding: 1rem; } }
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