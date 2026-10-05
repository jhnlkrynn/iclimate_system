<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'iClimate') }}</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Public+Sans:wght@400;500;600;700&family=DM+Serif+Display:ital@0;1&family=DM+Mono:wght@400;500&family=Sora:wght@600;700;800&family=DM+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
        {{-- Light / Dark: applied before any stylesheet so the page never flashes the other theme (resources/js/theme.js). --}}
        <script>(function(){var t='light';try{t=localStorage.getItem('iclimate-theme')==='dark'?'dark':'light';}catch(e){}var d=document.documentElement;d.setAttribute('data-theme',t);d.setAttribute('data-bs-theme',t);d.style.colorScheme=t;})();</script>
        @vite(['resources/css/app.css', 'resources/css/ui-refresh.css', 'resources/css/glass-system.css', 'resources/css/sidebar.css', 'resources/css/theme-dark-pages.css', 'resources/css/theme.css', 'resources/js/app.js'])
        <style>
            :root {
                /* The landing page's rice-field photo, reused as the backdrop of every signed-in page (see BACKDROP in theme.css). */
                --ic-backdrop-image: url('{{ asset('images/rice-hero-aerial.jpg') }}');
                --iclimate-primary: #123F32;
                --iclimate-primary-medium: #1F5A46;
                --iclimate-accent: #8FAF9A;
                --iclimate-accent-light: #C9D8CE;
                --iclimate-surface: #F4F6F2;
                --iclimate-border: #D8E0DA;
                --iclimate-text: #1E2B25;
                --iclimate-text-muted: #4B5A50;
                --iclimate-warning: #C58B2A;
                --iclimate-danger: #B84A4A;
                --iclimate-info: #4B7185;
                --color-primary: var(--iclimate-primary);
                --color-primary-dark: #0e2f25;
                --color-primary-soft: #eef3ef;
                --color-surface: #ffffff;
                --color-surface-muted: #f5f7f4;
                --color-border: var(--iclimate-border);
                --color-text: var(--iclimate-text);
                --color-text-muted: var(--iclimate-text-muted);
                --color-accent: var(--iclimate-accent);
                --color-success: #2d7a4f;
                --color-warning: var(--iclimate-warning);
                --color-danger: var(--iclimate-danger);
                --color-info: var(--iclimate-info);
                --ic-green-950: var(--color-primary-dark);
                --ic-green-900: var(--color-primary);
                --ic-green-800: var(--color-primary);
                --ic-green-700: var(--color-primary);
                --ic-green-500: #5f8f78;
                --ic-green-400: #8cc8ad;
                --ic-green-300: #b9d9c7;
                --ic-green-100: rgba(95,143,120,.14);
                --ic-green-50: var(--color-primary-soft);
                --ic-amber: var(--color-warning);
                --ic-sand: #f7f6f2;
                --ic-sand-dark: var(--color-border);
                --ic-blue: var(--color-info);
                --ic-coral: var(--color-danger);
                --ic-ink: var(--color-text);
                --ic-ink-mid: var(--color-text-muted);
                --ic-muted: var(--color-text-muted);
                --ic-border: var(--color-border);
                --ic-paper: var(--color-surface);
                --ic-panel: var(--color-surface);
                --ic-panel-strong: var(--color-surface);
                --ic-field: var(--color-surface);
                --ic-sidebar-bg: var(--glass-dark-bg);
                --ic-sidebar-active-bg: rgba(143,175,154,.22);
                --ic-hero-from: var(--iclimate-primary);
                --ic-hero-to: var(--iclimate-primary-medium);
                --ic-radius-sm: 6px;
                --ic-radius-md: 8px;
                --ic-radius-lg: 12px;
                --ic-radius-xl: 16px;
                --ic-radius-pill: 999px;
                --ic-shadow-sm: 0 1px 2px rgba(22,59,45,.08);
                --ic-shadow-md: 0 6px 16px rgba(22,59,45,.10);
                --ic-shadow-lg: 0 10px 26px rgba(22,59,45,.12);
                /* Glassmorphism system: two families (light glass for surfaces on the
                   green backdrop, dark glass for chrome that used to be solid green). */
                --glass-light-bg: rgba(255,255,255,.66);
                --glass-light-bg-strong: rgba(255,255,255,.84);
                --glass-light-border: rgba(255,255,255,.55);
                --glass-light-shadow: 0 10px 34px rgba(8,25,19,.16), inset 0 1px 0 rgba(255,255,255,.5);
                --glass-dark-bg: rgba(15,44,35,.55);
                --glass-dark-bg-strong: rgba(13,38,30,.72);
                --glass-dark-border: rgba(255,255,255,.14);
                --glass-dark-shadow: 0 12px 40px rgba(5,16,12,.35), inset 0 1px 0 rgba(255,255,255,.08);
                --glass-blur: blur(22px) saturate(170%);
                --glass-blur-strong: blur(30px) saturate(180%);
                --glass-blur-soft: blur(10px) saturate(150%);
                --ic-motion-fast: 140ms cubic-bezier(.2,.8,.2,1);
                --ic-motion-med: 240ms cubic-bezier(.2,.8,.2,1);
                --ic-motion-slow: 420ms cubic-bezier(.16,1,.3,1);
                --text-xs: 0.75rem;
                --text-sm: 0.875rem;
                --text-base: 1rem;
                --text-md: 1.04rem;
                --text-lg: 1.125rem;
                --text-xl: 1.25rem;
                --text-2xl: 1.5rem;
                --text-3xl: 1.875rem;
                --text-4xl: 2.25rem;
                --ic-readable-body: 1.04rem;
                --ic-readable-small: .96rem;
                --ic-readable-label: .84rem;
            }
            html { background: var(--ic-green-800); width: 100%; overflow-x: clip; }
            body {
                font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                font-size: var(--ic-readable-body);
                line-height: 1.58;
                min-height: 100vh;
                color: var(--white, #ffffff);
                width: 100%;
                max-width: 100%;
                overflow-x: hidden;
                transform: none !important;
                background: #1f4d3a;
            }
            p, li, .card-body, .modal-body, .offcanvas-body, .alert, .form-control, .form-select, .table, .module-tile, .climate-chip, .risk-card, .soft-section, .filter-panel, .details-list dd, .empty-state {
                font-size: var(--ic-readable-body);
                line-height: 1.58;
            }
            small, .small, .text-muted, .card-text, .form-text {
                font-size: var(--ic-readable-small);
                line-height: 1.52;
            }
            .app-main {
                margin-left: var(--sidebar-width);
                min-height: 100vh;
                min-width: 0;
                background: linear-gradient(180deg, #255b44 0%, #1f4d3a 100%);
            }
            .page-shell { padding: 1.25rem; min-width: 0; max-width: 100%; }
            .topbar { position: sticky; top: 0; z-index: 1020; background: var(--glass-dark-bg-strong); backdrop-filter: var(--glass-blur); -webkit-backdrop-filter: var(--glass-blur); border-bottom: 1px solid var(--glass-dark-border); box-shadow: 0 8px 28px rgba(5,16,12,.22); color: #fff; }
            .topbar-inner { min-height: 74px; min-width: 0; }
            .topbar .text-muted { color: rgba(255,255,255,.78) !important; }
            .topbar .badge.text-bg-light { background: rgba(255,255,255,.12) !important; color: #fff !important; border-color: rgba(255,255,255,.2) !important; }
            .topbar .btn-outline-primary { --bs-btn-color: #fff; --bs-btn-border-color: rgba(255,255,255,.5); --bs-btn-hover-bg: #fff; --bs-btn-hover-border-color: #fff; --bs-btn-hover-color: var(--ic-ink); }
            .topbar .btn-outline-secondary { --bs-btn-color: #fff; --bs-btn-border-color: rgba(255,255,255,.5); --bs-btn-hover-bg: #fff; --bs-btn-hover-border-color: #fff; --bs-btn-hover-color: var(--ic-ink); }
            .topbar .btn-outline-dark { --bs-btn-color: #fff; --bs-btn-border-color: rgba(255,255,255,.5); --bs-btn-hover-bg: rgba(255,255,255,.12); --bs-btn-hover-border-color: var(--iclimate-accent-light); --bs-btn-hover-color: #fff; }
            .weather-strip { display: flex; align-items: center; gap: .65rem; padding: .52rem .75rem; border: 1px solid var(--glass-light-border); border-radius: 8px; background: var(--glass-light-bg); backdrop-filter: var(--glass-blur-soft); color: var(--ic-ink); }
            .weather-strip .pulse { width: 8px; height: 8px; border-radius: 999px; background: var(--iclimate-accent); }
            h1, h2, h3, h4, .card-header .fw-semibold, .card-header .fw-bold { font-family: 'DM Serif Display', Georgia, serif; font-weight: 400; letter-spacing: -0.01em; }
            .card, .glass-card { background: var(--glass-light-bg); backdrop-filter: var(--glass-blur); -webkit-backdrop-filter: var(--glass-blur); border: 1px solid var(--glass-light-border); border-radius: var(--ic-radius-lg); box-shadow: var(--glass-light-shadow); transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease, background .16s ease; }
            .card:hover, .module-tile:hover, .climate-chip:hover, .risk-card:hover { transform: translateY(-1px); box-shadow: var(--ic-shadow-md); border-color: rgba(95,143,120,.55); }
            .card.no-lift:hover { transform: none; }
            .card-header { border-top-left-radius: var(--ic-radius-lg) !important; border-top-right-radius: var(--ic-radius-lg) !important; }
            .form-control, .form-select, .alert { border-radius: var(--ic-radius-md); }
            .btn { border-radius: var(--ic-radius-md); font-family: 'Inter', sans-serif; font-weight: 650; letter-spacing: 0; transition: transform .16s ease, box-shadow .16s ease, background-color .16s ease; }
            .btn, .dropdown-item { font-size: var(--text-base); line-height: 1.4; }
            .btn:hover { transform: translateY(-1px); }
            .btn:active { transform: translateY(0); }
            .btn:focus-visible, .form-control:focus, .form-select:focus, .form-check-input:focus { outline: 3px solid rgba(82,183,136,.24); outline-offset: 2px; }
            .btn-primary { --bs-btn-bg: var(--iclimate-primary-medium); --bs-btn-border-color: var(--iclimate-primary-medium); --bs-btn-color: #fff; --bs-btn-hover-bg: var(--iclimate-primary); --bs-btn-hover-border-color: var(--iclimate-primary); --bs-btn-hover-color: #fff; box-shadow: none; }
            .btn-outline-primary { --bs-btn-color: var(--ic-green-700); --bs-btn-border-color: var(--ic-green-700); --bs-btn-hover-bg: #fff; --bs-btn-hover-border-color: var(--ic-green-700); --bs-btn-hover-color: var(--ic-green-700); }
            .page-hero .btn-outline-light { --bs-btn-color: #fff; --bs-btn-border-color: #fff; --bs-btn-hover-bg: #fff; --bs-btn-hover-border-color: #fff; --bs-btn-hover-color: var(--ic-ink); }
            .btn-light { --bs-btn-bg: var(--iclimate-surface); --bs-btn-border-color: var(--iclimate-border); --bs-btn-color: var(--iclimate-text); --bs-btn-hover-bg: #fff; --bs-btn-hover-border-color: var(--iclimate-accent); --bs-btn-hover-color: var(--iclimate-primary); --bs-btn-active-bg: #fff; --bs-btn-active-border-color: var(--iclimate-accent); box-shadow: none; }
            .btn-warning { --bs-btn-bg: var(--iclimate-warning); --bs-btn-border-color: var(--iclimate-warning); --bs-btn-color: #fff; --bs-btn-hover-bg: #a87420; --bs-btn-hover-border-color: #a87420; --bs-btn-hover-color: #fff; }
            .form-label { font-family: 'DM Mono', monospace; font-size: var(--ic-readable-label); font-weight: 500; letter-spacing: .05em; text-transform: uppercase; color: var(--ic-muted); }
            .form-control, .form-select { border-color: var(--ic-sand-dark); background-color: var(--ic-field); font-size: var(--text-base); line-height: 1.45; }
            .form-control:hover, .form-select:hover { border-color: rgba(82,183,136,.58); background-color: #fff; }
            .form-control:focus, .form-select:focus { border-color: var(--iclimate-accent); box-shadow: 0 0 0 .22rem rgba(143,175,154,.18); }
            .page-hero { position: relative; overflow: hidden; border-radius: var(--ic-radius-xl); padding: 1.45rem 1.55rem; margin-bottom: 1.25rem; color: #fff; background: var(--glass-dark-bg); backdrop-filter: var(--glass-blur); -webkit-backdrop-filter: var(--glass-blur); border: 1px solid var(--glass-dark-border); box-shadow: var(--glass-dark-shadow); }
            .page-hero::before {
                content: "";
                position: absolute; inset: 0;
                background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.05'/%3E%3C/svg%3E"),
                    radial-gradient(ellipse at 88% -10%, rgba(143,175,154,.14) 0%, transparent 60%);
                pointer-events: none;
            }
            .page-hero > * { position: relative; z-index: 1; }
            .page-hero p, .page-hero .text-muted { color: rgba(255,255,255,.72) !important; }
            .page-hero h1 { font-family: 'DM Serif Display', Georgia, serif; font-weight: 400; letter-spacing: -0.01em; color: #fff; }
            .eyebrow { display: inline-flex; align-items: center; gap: 8px; font-family: 'DM Mono', monospace; font-size: var(--ic-readable-label); font-weight: 500; text-transform: uppercase; letter-spacing: .12em; color: var(--iclimate-accent-light); }
            .eyebrow::before { content: none; }
            .stat-card { overflow: hidden; position: relative; min-height: 136px; }
            .stat-card::before { content: ""; position: absolute; inset: 0 0 auto; height: 5px; background: var(--accent, var(--ic-green-500)); }
            .stat-card::after { content: none; }
            .stat-label { color: var(--ic-muted); font-family: 'DM Mono', monospace; font-size: var(--ic-readable-label); font-weight: 500; text-transform: uppercase; letter-spacing: .06em; }
            .stat-value { color: var(--ic-ink); font-family: 'DM Serif Display', serif; font-size: clamp(1.6rem, 3vw, 2.2rem); line-height: 1; margin-top: .45rem; letter-spacing: -0.01em; }
            .stat-dot { width: 2.2rem; height: 2.2rem; border-radius: 6px; background: color-mix(in srgb, var(--accent, var(--ic-green-500)) 14%, #edf7e7); border: 1px solid color-mix(in srgb, var(--accent, var(--ic-green-500)) 30%, #edf7e7); position: relative; z-index: 1; }
            .stat-dot::after { content: ""; position: absolute; inset: 9px 7px; border-top: 2px solid var(--accent, var(--ic-green-500)); opacity: .65; }
            .tone-green { --accent: var(--ic-green-500); } .tone-blue { --accent: var(--ic-green-700); } .tone-amber { --accent: var(--ic-amber); } .tone-coral { --accent: var(--ic-coral); }
            .insight-panel { position: relative; overflow: hidden; }
            .insight-panel::before { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 5px; background: var(--accent, var(--ic-green-500)); }
            .module-tile { border: 1px solid var(--glass-light-border); border-radius: var(--ic-radius-md); padding: 1rem; background: var(--glass-light-bg); backdrop-filter: var(--glass-blur-soft); min-height: 96px; transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, background .18s ease; }
            .module-tile .meter { height: 7px; border-radius: 999px; background: #e8eef6; overflow: hidden; }
            .module-tile .meter > span { display: block; height: 100%; width: min(var(--meter, 45%), 100%); background: linear-gradient(90deg, var(--ic-green-500), var(--ic-green-700)); }
            .update-list > * + * { border-top: 1px solid var(--ic-border); }
            .climate-chip { border: 1px solid var(--glass-light-border); border-radius: var(--ic-radius-md); padding: 1rem; min-height: 112px; background: var(--glass-light-bg); backdrop-filter: var(--glass-blur-soft); transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, background .18s ease; }
            .table { --bs-table-bg: transparent; }
            .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-gutter: stable; }
            .table-responsive > .table { margin-bottom: 0; }
            .table thead th { color: var(--ic-muted); font-family: 'DM Mono', monospace; font-size: var(--ic-readable-label); text-transform: uppercase; letter-spacing: .07em; font-weight: 500; white-space: nowrap; background: rgba(240,247,244,.55); backdrop-filter: blur(6px); }
            .table td, .table th { vertical-align: middle; padding: .95rem 1rem; font-size: var(--text-sm); line-height: 1.45; }
            .table-hover tbody tr { transition: background .12s ease; }
            .table tbody tr { transition: background .15s ease; }
            .table-hover tbody tr:hover { background: rgba(240,247,244,.95); transform: none; }
            .badge { border-radius: 999px; padding: .35rem .55rem; font-family: 'Inter', sans-serif; font-size: var(--text-xs); line-height: 1.2; font-weight: 700; letter-spacing: 0; }
            .empty-state { border: 1px dashed var(--glass-light-border); background: var(--glass-light-bg); backdrop-filter: var(--glass-blur-soft); border-radius: var(--ic-radius-lg); }
            .is-loading-action { opacity: .72; pointer-events: none; }
            .ic-logout-confirm { position: fixed; inset: 0; z-index: 10000; display: none; place-items: center; padding: 1rem; overflow-y: auto; background: radial-gradient(circle at 50% 10%, rgba(143,175,154,.22), transparent 22rem), rgba(13,31,24,.6); backdrop-filter: blur(10px) saturate(150%); }
            .ic-logout-confirm.show { display: grid !important; }
            body.ic-modal-open { overflow: hidden; }
            /* Shared slide-in drawer — replaces per-page Bootstrap stat-detail modals so
               opening/closing never depends on Bootstrap's modal backdrop/focus-trap stack. */
            .ic-drawer { position: fixed; inset: 0; z-index: 2150; display: none; background: rgba(13,31,24,.4); backdrop-filter: blur(6px) saturate(140%); }
            .ic-drawer.show { display: block; }
            .ic-drawer-panel { position: fixed; top: 0; right: 0; bottom: 0; z-index: 1; width: min(560px, 100vw); box-shadow: -1.2rem 0 3rem rgba(13,31,24,.35); transform: translateX(100%); transition: transform .26s cubic-bezier(.2,.8,.2,1); display: flex; flex-direction: column; overflow: hidden; }
            .ic-drawer.show .ic-drawer-panel { transform: translateX(0); }
            .ic-drawer-panel .modal-content { height: 100%; border-radius: 0 !important; background: var(--glass-light-bg-strong); backdrop-filter: var(--glass-blur-strong); -webkit-backdrop-filter: var(--glass-blur-strong); border-left: 1px solid var(--glass-light-border); }
            .ic-drawer-panel .modal-body { overflow-y: auto; flex: 1 1 auto; }
            @media (max-width: 560px) { .ic-drawer-panel { width: 100vw; } }
            .ic-logout-card { position: relative; z-index: 1; display: block; width: min(430px, calc(100vw - 2rem)); border: 1px solid var(--glass-light-border); border-radius: 22px; background: var(--glass-light-bg-strong); backdrop-filter: var(--glass-blur-strong); -webkit-backdrop-filter: var(--glass-blur-strong); box-shadow: 0 1.4rem 3.4rem rgba(13,31,24,.34); overflow: hidden; opacity: 1; visibility: visible; animation: icPopupIn .22s ease-out; }
            .ic-logout-card::before { content: ""; display: block; height: 7px; background: var(--iclimate-primary-medium); }
            .ic-logout-body { padding: 1.1rem; text-align: center; }
            .ic-logout-icon { width: 58px; height: 58px; margin: 0 auto .75rem; border-radius: 18px; display: grid; place-items: center; color: #fff; background: var(--iclimate-primary-medium); box-shadow: none; font-size: 1.45rem; font-weight: 900; }
            .ic-logout-title { margin: 0 0 .35rem; color: var(--ic-ink); font-weight: 900; font-size: 1.15rem; }
            .ic-logout-message { margin: 0; color: var(--ic-ink-mid); line-height: 1.45; }
            .ic-logout-actions { display: grid; grid-template-columns: 1fr 1fr; gap: .65rem; padding: 0 1.1rem 1.1rem; }
            .ic-logout-actions button { border: 0; border-radius: 999px; padding: .75rem 1rem; font-weight: 900; }
            .ic-logout-cancel { background: var(--ic-green-50); color: var(--ic-green-800); }
            .ic-logout-confirm-btn { background: var(--ic-coral); color: #fff; box-shadow: 0 .75rem 1.45rem rgba(216,91,69,.22); }
            @keyframes icPopupIn { from { opacity: 0; transform: translateY(-12px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
            .bg-white, .table-light { background-color: #ffffff !important; }
            .text-bg-light { color: var(--ic-ink) !important; background-color: var(--ic-green-50) !important; }
            .border-top, .border-bottom, .border-start, .border-end, .border { border-color: rgba(212,237,218,.98) !important; }
            .filter-panel { background: var(--glass-light-bg); backdrop-filter: var(--glass-blur); border: 1px solid var(--glass-light-border); }
            .soft-section { background: var(--glass-light-bg); backdrop-filter: var(--glass-blur); border: 1px solid var(--glass-light-border); border-radius: var(--ic-radius-md); box-shadow: var(--glass-light-shadow); }
            .action-cluster { background: rgba(240,247,244,.5); backdrop-filter: blur(8px) saturate(150%); border: 1.5px solid var(--glass-light-border); border-radius: var(--ic-radius-md); padding: .35rem; }
            .risk-card { position: relative; overflow: hidden; transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, background .18s ease; }
            .details-list dt { color: #496071; font-family: 'DM Mono', monospace; font-size: var(--ic-readable-label); text-transform: uppercase; letter-spacing: .06em; }
            .details-list dd { background: rgba(240,247,244,.5); backdrop-filter: blur(8px) saturate(150%); border: 1.5px solid var(--glass-light-border); border-radius: var(--ic-radius-md); padding: .7rem .85rem; }
            .interactive-card { cursor: default; }
            .interactive-card:hover .stat-label, .module-tile:hover .small { color: var(--ic-green-700) !important; }
            .page-shell { animation: icPageEnter var(--ic-motion-slow) both; }
            .card, .glass-card, .module-tile, .climate-chip, .risk-card, .stat-card, .soft-section, .filter-panel, .quick-action, .priority-card, .btn, .form-control, .form-select { transition-timing-function: cubic-bezier(.2,.8,.2,1) !important; transition-duration: 180ms !important; }
            .btn:hover, .quick-action:hover, .priority-card:hover { transform: translateY(-1px); }
            .btn:active, .quick-action:active, .priority-card:active { transform: translateY(0) scale(.985); }
            .form-control:focus, .form-select:focus { transform: translateY(-1px); }
            .ic-logout-cancel:hover, .ic-logout-confirm-btn:hover, .ic-action-close:hover { transform: translateY(-1px); }
            .ic-logout-cancel:active, .ic-logout-confirm-btn:active, .ic-action-close:active { transform: translateY(0) scale(.985); }
            @keyframes icPageEnter { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
            @keyframes icSoftRise { from { opacity: 0; transform: translateY(10px) scale(.985); } to { opacity: 1; transform: translateY(0) scale(1); } }
            @media (prefers-reduced-motion: reduce) {
                .page-shell, .ic-action-dialog, .ic-logout-card { animation: none !important; }
                .card, .glass-card, .module-tile, .climate-chip, .risk-card, .stat-card, .soft-section, .filter-panel, .quick-action, .priority-card, .btn, .form-control, .form-select { transition-duration: .01ms !important; }
            }
            @media (min-width: 1400px) { .page-shell { padding: 1.75rem 2rem; } }
            .card, .glass-card, .soft-section, .filter-panel, .module-tile, .climate-chip, .risk-card, .stat-card, .alert, .modal-content, .offcanvas, .page-hero, .weather-strip { min-width: 0; overflow-wrap: anywhere; }
            .card-body > *, .glass-card > *, .soft-section > *, .filter-panel > *, .page-hero > *, .topbar-inner > * { min-width: 0; }
            .btn, .badge { white-space: normal; }
            .modal-dialog { max-width: calc(100vw - 1rem); }
            .modal-content { background: var(--glass-light-bg-strong); backdrop-filter: var(--glass-blur-strong); -webkit-backdrop-filter: var(--glass-blur-strong); border: 1px solid var(--glass-light-border); box-shadow: var(--glass-light-shadow); }
            .modal-backdrop.show { opacity: 1; background: rgba(13,31,24,.42); backdrop-filter: blur(6px) saturate(140%); }
            .dropdown-menu { background: var(--glass-light-bg-strong); backdrop-filter: var(--glass-blur); -webkit-backdrop-filter: var(--glass-blur); border: 1px solid var(--glass-light-border); box-shadow: var(--glass-light-shadow); }
            .offcanvas { max-width: min(92vw, 380px); background: var(--ic-sidebar-bg); backdrop-filter: var(--glass-blur-strong); -webkit-backdrop-filter: var(--glass-blur-strong); border-right: 1px solid var(--glass-dark-border); color: #fff; }
            @media (max-width: 1199.98px) { .page-hero .d-flex, .card-header.d-flex, .filter-panel .row, .topbar-inner { flex-wrap: wrap; } .table-responsive > .table { min-width: 760px; } }
            @media (max-width: 991.98px) { .app-main { margin-left: 0; } .page-shell { padding: .9rem; } .topbar-inner { min-height: 64px; } .page-hero { padding: 1rem; } }
            @media (max-width: 767.98px) {
                body { background: linear-gradient(180deg, #1f4d3a, #1f4d3a); font-size: 1.02rem; }
                .topbar { padding-inline: .75rem !important; }
                .topbar-inner { gap: .65rem !important; }
                .page-shell { padding: .75rem; }
                .page-hero h1 { font-size: 1.45rem; line-height: 1.15; }
                .card-body { padding: 1rem; }
                .table-responsive { border-radius: 8px; margin-inline: -.35rem; width: calc(100% + .7rem); }
                .table-responsive > .table { min-width: 680px; }
                .card-header.d-flex, .card-body.d-flex, .modal-footer { align-items: stretch !important; flex-direction: column; }
                .action-cluster { display: grid !important; gap: .35rem; }
                .action-cluster .btn, .modal-footer .btn { width: 100%; }
                .stat-value { font-size: 1.65rem; }
            }
            /* ---- Responsive safety net for every page inside the shell (Farmer, MAO, IT) ---- */
            /* Nothing may push the page sideways: media and charts shrink with their card, long words wrap. */
            .page-shell img, .page-shell video, .page-shell canvas, .page-shell iframe { max-width: 100%; }
            .page-shell pre { max-width: 100%; overflow-x: auto; }
            .page-shell .leaflet-container { max-width: 100%; }
            /* A table that is not already in a scroll area gets one from app.js (bindResponsiveTables). */
            .ic-table-scroll { width: 100%; max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .offcanvas-backdrop.show { opacity: 1; background: rgba(13,31,24,.5); }
            @media (max-width: 991.98px) {
                .topbar-inner { row-gap: .5rem; }
                .topbar-actions { flex-wrap: nowrap; }
            }
            @media (max-width: 575.98px) {
                /* Pop-ups: 12px from each edge, scrolling inside, close button and footer buttons always reachable. */
                .modal-dialog { width: calc(100% - 24px); max-width: calc(100% - 24px); margin: 12px auto; }
                .modal-content { max-height: calc(100vh - 24px); max-height: calc(100dvh - 24px); }
                .modal-body { overflow-y: auto; -webkit-overflow-scrolling: touch; }
                .modal-header { position: sticky; top: 0; z-index: 2; }
                .modal-header .btn-close { flex: none; min-width: 32px; min-height: 32px; }
                /* Charts follow their card instead of a fixed minimum width. */
                .chart-box canvas, .chart-render-state { min-width: 0 !important; }
            }
            @media (max-width: 575.98px) {
                .topbar .container-fluid { align-items: flex-start; }
                .topbar .btn { padding: .42rem .55rem; font-size: .9rem; }
                .weather-strip { display: none !important; }
                .page-hero { margin-bottom: .85rem; }
                .page-hero .btn, .filter-panel .btn, .filter-panel a.btn { width: 100%; }
                .filter-panel form > [class*="col-"] { width: 100%; }
                .card, .glass-card, .module-tile, .climate-chip, .soft-section { box-shadow: 0 .45rem 1rem rgba(13,31,24,.06); }
                .ic-logout-actions { grid-template-columns: 1fr; }
            }
        </style>
        @include('partials.global-loader')
    </head>
    <body data-user-role="{{ auth()->user()?->role }}" @class(['is-farmer-dashboard' => request()->routeIs('farmer.dashboard'), 'is-it-dashboard' => request()->routeIs('admin.dashboard'), 'is-mao-dashboard' => request()->routeIs('mao.dashboard')])>
        @include('layouts.sidebar')
        <div class="app-main">
            @include('layouts.navigation')
            <main class="page-shell">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert" data-auto-dismiss="5000">
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
        <x-flash-toast />
        <script>
            // A success message is good news, not something to keep on screen: it closes itself after 5 seconds.
            document.querySelectorAll('[data-auto-dismiss]').forEach((el) => {
                setTimeout(() => {
                    const close = el.querySelector('.btn-close');
                    close ? close.click() : el.remove();
                }, Number(el.dataset.autoDismiss) || 5000);
            });
        </script>
        <div id="icLogoutConfirm" class="ic-logout-confirm" role="dialog" aria-modal="true" aria-labelledby="icLogoutTitle">
            <div class="ic-logout-card">
                <div class="ic-logout-body">
                    <div class="ic-logout-icon" aria-hidden="true">?</div>
                    <h2 id="icLogoutTitle" class="ic-logout-title">Log out of iClimate?</h2>
                    <p class="ic-logout-message">Your current session will close, and you will return to the home page.</p>
                </div>
                <div class="ic-logout-actions">
                    <button type="button" class="ic-logout-cancel" data-logout-cancel>Stay logged in</button>
                    <button type="button" class="ic-logout-confirm-btn" data-logout-confirm-submit>Log out</button>
                </div>
            </div>
        </div>
        @auth
            @include('components.ai-chat-widget')
        @endauth
    </body>
</html>
