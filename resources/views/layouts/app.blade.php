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
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            :root {
                --ic-green-950: #163b2d;
                --ic-green-900: #1f4d3a;
                --ic-green-800: #1f4d3a;
                --ic-green-700: #1f4d3a;
                --ic-green-500: #5f8f78;
                --ic-green-400: #7fd6b5;
                --ic-green-300: #7fd6b5;
                --ic-green-100: rgba(127,214,181,.22);
                --ic-green-50: rgba(95,143,120,.1);
                --ic-amber: #e8a73d;
                --ic-gold: #e8a73d;
                --ic-gold-dark: #c6872a;
                --ic-gold-light: #f6d58a;
                --ic-sand: #f7f6f2;
                --ic-sand-dark: #5f8f78;
                --ic-blue: #2f6f8f;
                --ic-coral: #d85b45;
                --ic-ink: #1f2937;
                --ic-ink-mid: #64748b;
                --ic-muted: rgba(100,116,139,.75);
                --ic-border: #5f8f78;
                --ic-paper: rgba(255,255,255,.98);
                --ic-panel: #ffffff;
                --ic-panel-strong: #ffffff;
                --ic-field: #ffffff;
                --ic-sidebar-bg: #163b2d;
                --ic-sidebar-active-bg: rgba(232,167,61,.16);
                --ic-hero-from: #1f4d3a;
                --ic-hero-to: #1f4d3a;
                --ic-radius-sm: 4px;
                --ic-radius-md: 10px;
                --ic-radius-lg: 18px;
                --ic-radius-xl: 32px;
                --ic-radius-pill: 100px;
                --ic-shadow-sm: 0 1px 4px rgba(22,59,45,.14);
                --ic-shadow-md: 0 4px 20px rgba(22,59,45,.18);
                --ic-shadow-lg: 0 16px 56px rgba(22,59,45,.24);
                --ic-shadow-gold: 0 10px 28px rgba(232,167,61,.32);
                --ic-motion-fast: 140ms cubic-bezier(.2,.8,.2,1);
                --ic-motion-med: 240ms cubic-bezier(.2,.8,.2,1);
                --ic-motion-slow: 420ms cubic-bezier(.16,1,.3,1);
                --sidebar-width: 300px;
            }
            html { background: var(--ic-green-800); width: 100%; overflow-x: clip; }
            body {
                font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                min-height: 100vh;
                color: var(--white, #ffffff);
                width: 100%;
                max-width: 100%;
                overflow-x: hidden;
                transform: none !important;
                background:
                    radial-gradient(circle at 86% 0%, rgba(127,214,181,.14), transparent 30rem),
                    radial-gradient(circle at 14% 18%, rgba(232,167,61,.08), transparent 24rem),
                    linear-gradient(145deg, #1f4d3a 0%, #1f4d3a 48%, #1f4d3a 100%);
            }
            body::before {
                content: "";
                position: fixed;
                inset: 0;
                pointer-events: none;
                z-index: -1;
                background:
                    radial-gradient(rgba(255,255,255,.06) 1px, transparent 1px);
                background-size: 30px 30px;
                mask-image: linear-gradient(90deg, transparent, #000 18%, #000 100%);
            }
            .app-main {
                margin-left: var(--sidebar-width);
                min-height: 100vh;
                min-width: 0;
                background:
                    linear-gradient(180deg, rgba(31,77,58,.98), rgba(31,77,58,1)),
                    radial-gradient(circle at 70% 10%, rgba(127,214,181,.08), transparent 28rem);
            }
            .page-shell { padding: 1.25rem; min-width: 0; max-width: 100%; }
            .sidebar-fixed {
                position: fixed !important; top: 0; left: 0; bottom: 0; width: var(--sidebar-width); height: 100vh; height: 100dvh; z-index: 1030; overflow: hidden;
                background: var(--ic-sidebar-bg);
                border-right: 1px solid rgba(255,255,255,.08);
            }
            .sidebar-nav-scroll { min-height: 0; overflow-y: auto; overflow-x: hidden; overscroll-behavior: contain; scrollbar-width: none; -ms-overflow-style: none; }
            .sidebar-nav-scroll::-webkit-scrollbar { display: none; }
            .sidebar-brand { position: relative; }
            .sidebar-location { display: flex; align-items: center; gap: 6px; font-family: 'DM Mono', monospace; font-size: .68rem; font-weight: 500; letter-spacing: .08em; text-transform: uppercase; color: var(--ic-green-400); }
            .sidebar-location .pulse-dot { width: 6px; height: 6px; border-radius: 999px; background: var(--ic-gold); box-shadow: 0 0 0 4px rgba(232,167,61,.25); flex-shrink: 0; }
            .sidebar-tagline { font-family: 'Inter', sans-serif; font-size: .74rem; color: rgba(255,255,255,.6); line-height: 1.5; margin-top: .3rem; }
            .sidebar-brand-row { display: flex; align-items: center; gap: 10px; margin-bottom: .5rem; }
            .sidebar-logo-icon { width: 34px; height: 34px; object-fit: contain; flex-shrink: 0; }
            .sidebar-wordmark { font-family: 'Inter', sans-serif; font-weight: 800; font-size: 1.25rem; color: #fff; letter-spacing: -0.01em; }
            .sidebar-brand-underline { width: 66px; height: 2px; background: var(--ic-gold); border-radius: 2px; position: relative; margin: 0 0 1rem; }
            .sidebar-brand-underline::after { content: ''; position: absolute; right: -3px; top: 50%; transform: translateY(-50%); width: 6px; height: 6px; border-radius: 50%; background: var(--ic-gold); }
            .sidebar-brand--large { text-align: center; }
            .sidebar-brand--large .sidebar-logo-large { width: 128px; height: auto; margin: 0 auto .75rem; }
            .sidebar-brand--large .sidebar-wordmark-lg { font-family: 'Inter', sans-serif; font-weight: 800; font-size: 1.85rem; color: #fff; letter-spacing: -0.01em; }
            .sidebar-brand--large .sidebar-brand-underline { margin: .5rem auto 0; }
            .sidebar-foot { margin-top: auto; padding: 1rem 1.4rem 1.25rem; font-size: .72rem; color: var(--ic-green-400); }
            .sidebar-link {
                position: relative;
                color: rgba(255,255,255,.72); text-decoration: none; display: flex; align-items: center; justify-content: space-between; gap: .5rem;
                border-radius: var(--ic-radius-pill); padding: .72rem 1rem; transition: background .15s ease, color .15s ease;
            }
            .sidebar-link:hover { color: #fff; background: rgba(255,255,255,.08); }
            .sidebar-link.active { color: var(--ic-gold); background: var(--ic-sidebar-active-bg); font-weight: 600; }
            .sidebar-link.active::before { content: ''; position: absolute; left: -.7rem; top: 50%; transform: translateY(-50%); width: 3px; height: 60%; border-radius: 3px; background: var(--ic-gold); }
            .sidebar-link.active .sidebar-icon { background: var(--ic-gold); color: var(--ic-ink); }
            .sidebar-icon { width: 30px; height: 30px; border-radius: 50%; display: inline-grid; place-items: center; background: rgba(255,255,255,.08); color: rgba(255,255,255,.85); flex-shrink: 0; }
            .sidebar-badge { background: var(--ic-gold); color: var(--ic-ink); font-family: 'DM Mono', monospace; font-size: .68rem; font-weight: 700; border-radius: 999px; min-width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; padding: 0 .4rem; flex-shrink: 0; }
            .sidebar-link-arrow { flex-shrink: 0; opacity: 0; transform: translateX(-3px); transition: opacity .15s ease, transform .15s ease; }
            .sidebar-link:hover .sidebar-link-arrow { opacity: .55; transform: translateX(0); }
            .sidebar-link.active .sidebar-link-arrow { opacity: .9; transform: translateX(0); color: var(--ic-gold); }
            .sidebar-section { color: var(--ic-green-400); font-family: 'DM Mono', monospace; font-size: .66rem; font-weight: 500; text-transform: uppercase; letter-spacing: .1em; margin: 1.4rem 1rem .5rem; }
            .sidebar-ai-card { margin: .75rem 1rem 1rem; padding: .85rem 1rem; border-radius: var(--ic-radius-lg); background: linear-gradient(135deg, rgba(232,167,61,.2), rgba(232,167,61,.08)); border: 1px solid rgba(232,167,61,.4); display: flex; align-items: center; gap: .75rem; cursor: pointer; transition: background .15s ease, border-color .15s ease; }
            .sidebar-ai-card:hover { background: linear-gradient(135deg, rgba(232,167,61,.3), rgba(232,167,61,.12)); border-color: rgba(232,167,61,.6); }
            .sidebar-ai-icon { width: 40px; height: 40px; border-radius: 50%; background: rgba(232,167,61,.22); display: flex; align-items: center; justify-content: center; color: var(--ic-gold); flex-shrink: 0; }
            .sidebar-ai-title { color: #fff; font-weight: 700; font-size: .88rem; }
            .sidebar-ai-sub { color: rgba(255,255,255,.65); font-size: .74rem; }
            .topbar { position: sticky; top: 0; z-index: 1020; background: rgba(22,59,45,.85); backdrop-filter: blur(16px); border-bottom: 1px solid rgba(255,255,255,.1); box-shadow: 0 .55rem 1.4rem rgba(13,31,24,.15); color: #fff; }
            .topbar-inner { min-height: 74px; min-width: 0; }
            .topbar .text-muted { color: rgba(255,255,255,.6) !important; }
            .topbar .badge.text-bg-light { background: rgba(255,255,255,.12) !important; color: #fff !important; border-color: rgba(255,255,255,.2) !important; }
            .topbar .btn-outline-primary { --bs-btn-color: #fff; --bs-btn-border-color: rgba(255,255,255,.5); --bs-btn-hover-bg: #fff; --bs-btn-hover-border-color: #fff; --bs-btn-hover-color: var(--ic-ink); }
            .topbar .btn-outline-secondary { --bs-btn-color: #fff; --bs-btn-border-color: rgba(255,255,255,.5); --bs-btn-hover-bg: #fff; --bs-btn-hover-border-color: #fff; --bs-btn-hover-color: var(--ic-ink); }
            .topbar .btn-outline-dark { --bs-btn-color: #fff; --bs-btn-border-color: rgba(255,255,255,.5); --bs-btn-hover-bg: var(--ic-gold); --bs-btn-hover-border-color: var(--ic-gold); --bs-btn-hover-color: var(--ic-ink); }
            .weather-strip { display: flex; align-items: center; gap: .65rem; padding: .52rem .75rem; border: 1px solid var(--ic-border); border-radius: 8px; background: var(--ic-field); color: var(--ic-ink); }
            .weather-strip .pulse { width: 10px; height: 10px; border-radius: 999px; background: var(--ic-gold); box-shadow: 0 0 0 5px rgba(232,167,61,.18); }
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
            .btn-primary { --bs-btn-bg: var(--ic-gold); --bs-btn-border-color: var(--ic-gold); --bs-btn-color: var(--ic-ink); --bs-btn-hover-bg: var(--ic-gold-dark); --bs-btn-hover-border-color: var(--ic-gold-dark); --bs-btn-hover-color: var(--ic-ink); box-shadow: var(--ic-shadow-gold); }
            .btn-outline-primary { --bs-btn-color: var(--ic-green-700); --bs-btn-border-color: var(--ic-green-700); --bs-btn-hover-bg: var(--ic-green-700); --bs-btn-hover-border-color: var(--ic-green-700); --bs-btn-hover-color: #fff; }
            .page-hero .btn-outline-light { --bs-btn-color: #fff; --bs-btn-border-color: #fff; --bs-btn-hover-bg: #fff; --bs-btn-hover-border-color: #fff; --bs-btn-hover-color: var(--ic-ink); }
            .btn-light { --bs-btn-bg: var(--ic-gold); --bs-btn-border-color: var(--ic-gold); --bs-btn-color: var(--ic-ink); --bs-btn-hover-bg: var(--ic-gold-dark); --bs-btn-hover-border-color: var(--ic-gold-dark); --bs-btn-hover-color: var(--ic-ink); --bs-btn-active-bg: var(--ic-gold-dark); --bs-btn-active-border-color: var(--ic-gold-dark); box-shadow: var(--ic-shadow-gold); }
            .form-label { font-family: 'DM Mono', monospace; font-size: .7rem; font-weight: 500; letter-spacing: .05em; text-transform: uppercase; color: var(--ic-muted); }
            .form-control, .form-select { border-color: var(--ic-sand-dark); background-color: var(--ic-field); }
            .form-control:hover, .form-select:hover { border-color: rgba(82,183,136,.58); background-color: #fff; }
            .form-control:focus, .form-select:focus { border-color: var(--ic-green-500); box-shadow: 0 0 0 .22rem rgba(82,183,136,.14); }
            .page-hero { position: relative; overflow: hidden; border-radius: var(--ic-radius-xl); padding: 1.75rem 1.85rem; margin-bottom: 1.25rem; color: #fff; background: linear-gradient(145deg, var(--ic-hero-from) 0%, var(--ic-hero-to) 100%); border: 1px solid rgba(255,255,255,.12); box-shadow: var(--ic-shadow-lg); }
            .page-hero::before {
                content: "";
                position: absolute; inset: 0;
                background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.05'/%3E%3C/svg%3E"),
                    radial-gradient(ellipse at 88% -10%, rgba(232,167,61,.16) 0%, transparent 60%);
                pointer-events: none;
            }
            .page-hero > * { position: relative; z-index: 1; }
            .page-hero p, .page-hero .text-muted { color: rgba(255,255,255,.72) !important; }
            .page-hero h1, .sidebar-brand .h4 { font-family: 'DM Serif Display', Georgia, serif; font-weight: 400; letter-spacing: -0.01em; color: #fff; }
            .eyebrow { display: inline-flex; align-items: center; gap: 8px; font-family: 'DM Mono', monospace; font-size: .7rem; font-weight: 500; text-transform: uppercase; letter-spacing: .12em; color: var(--ic-gold-light); }
            .eyebrow::before { content: ''; display: block; width: 18px; height: 1px; background: var(--ic-gold-light); }
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
            .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-gutter: stable; }
            .table-responsive > .table { margin-bottom: 0; }
            .table thead th { color: var(--ic-muted); font-family: 'DM Mono', monospace; font-size: .68rem; text-transform: uppercase; letter-spacing: .07em; font-weight: 500; white-space: nowrap; background: var(--ic-green-50); }
            .table td, .table th { vertical-align: middle; padding: .95rem 1rem; }
            .table-hover tbody tr { transition: background .12s ease; }
            .table tbody tr { transition: background .15s ease, transform .15s ease; }
            .table-hover tbody tr:hover { background: rgba(240,247,244,.95); transform: scale(1.003); }
            .badge { border-radius: 999px; padding: .45rem .65rem; font-family: 'DM Mono', monospace; font-weight: 500; letter-spacing: .02em; }
            .empty-state { border: 1.5px dashed var(--ic-sand-dark); background: linear-gradient(135deg, #ffffff, var(--ic-green-50)); border-radius: var(--ic-radius-lg); }
            .loading-overlay { position: fixed; inset: 0; background: rgba(22,59,45,.72); backdrop-filter: blur(3px); z-index: 2000; display: none; align-items: center; justify-content: center; }
            .loading-overlay.show { display: flex; }
            .loading-overlay .card { min-width: 220px; }
            .page-progress { position: fixed; inset: 0 0 auto; height: 3px; z-index: 2100; pointer-events: none; background: transparent; opacity: 0; transition: opacity .12s ease; }
            .page-progress::before { content: ""; display: block; width: 42%; height: 100%; background: linear-gradient(90deg, var(--ic-green-500), var(--ic-gold)); transform: translateX(-100%); animation: pageProgress 1s ease-in-out infinite; }
            .page-progress.show { opacity: 1; }
            .is-loading-action { opacity: .72; pointer-events: none; }
            .ic-logout-confirm { position: fixed; inset: 0; z-index: 10000; display: none; place-items: center; padding: 1rem; overflow-y: auto; background: radial-gradient(circle at 50% 10%, rgba(232,167,61,.25), transparent 22rem), rgba(13,31,24,.68); backdrop-filter: blur(5px); }
            .ic-logout-confirm.show { display: grid !important; }
            body.ic-modal-open { overflow: hidden; }
            /* Shared slide-in drawer — replaces per-page Bootstrap stat-detail modals so
               opening/closing never depends on Bootstrap's modal backdrop/focus-trap stack. */
            .ic-drawer { position: fixed; inset: 0; z-index: 2150; display: none; background: rgba(13,31,24,.32); backdrop-filter: blur(2px); }
            .ic-drawer.show { display: block; }
            .ic-drawer-panel { position: fixed; top: 0; right: 0; bottom: 0; z-index: 1; width: min(560px, 100vw); box-shadow: -1.2rem 0 3rem rgba(13,31,24,.35); transform: translateX(100%); transition: transform .26s cubic-bezier(.2,.8,.2,1); display: flex; flex-direction: column; overflow: hidden; }
            .ic-drawer.show .ic-drawer-panel { transform: translateX(0); }
            .ic-drawer-panel .modal-content { height: 100%; border-radius: 0 !important; }
            .ic-drawer-panel .modal-body { overflow-y: auto; flex: 1 1 auto; }
            @media (max-width: 560px) { .ic-drawer-panel { width: 100vw; } }
            .ic-logout-card { position: relative; z-index: 1; display: block; width: min(430px, calc(100vw - 2rem)); border: 1px solid rgba(212,237,218,.9); border-radius: 22px; background: linear-gradient(145deg, #fff, #f7fbf8); box-shadow: 0 1.4rem 3.4rem rgba(13,31,24,.34); overflow: hidden; opacity: 1; visibility: visible; animation: icPopupIn .22s ease-out; }
            .ic-logout-card::before { content: ""; display: block; height: 7px; background: linear-gradient(90deg, var(--ic-gold), var(--ic-green-500), var(--ic-coral)); }
            .ic-logout-body { padding: 1.1rem; text-align: center; }
            .ic-logout-icon { width: 58px; height: 58px; margin: 0 auto .75rem; border-radius: 18px; display: grid; place-items: center; color: var(--ic-ink); background: var(--ic-gold-light); box-shadow: 0 .9rem 1.5rem rgba(232,167,61,.24); font-size: 1.45rem; font-weight: 900; }
            .ic-logout-title { margin: 0 0 .35rem; color: var(--ic-ink); font-weight: 900; font-size: 1.15rem; }
            .ic-logout-message { margin: 0; color: var(--ic-ink-mid); line-height: 1.45; }
            .ic-logout-actions { display: grid; grid-template-columns: 1fr 1fr; gap: .65rem; padding: 0 1.1rem 1.1rem; }
            .ic-logout-actions button { border: 0; border-radius: 999px; padding: .75rem 1rem; font-weight: 900; }
            .ic-logout-cancel { background: var(--ic-green-50); color: var(--ic-green-800); }
            .ic-logout-confirm-btn { background: var(--ic-coral); color: #fff; box-shadow: 0 .75rem 1.45rem rgba(216,91,69,.22); }
            @keyframes icPopupIn { from { opacity: 0; transform: translateY(-12px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
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
            .page-shell { animation: icPageEnter var(--ic-motion-slow) both; }
            .sidebar-link, .sidebar-ai-card, .card, .glass-card, .module-tile, .climate-chip, .risk-card, .stat-card, .soft-section, .filter-panel, .quick-action, .priority-card, .btn, .form-control, .form-select { transition-timing-function: cubic-bezier(.2,.8,.2,1) !important; transition-duration: 220ms !important; }
            .sidebar-link:hover, .sidebar-ai-card:hover, .btn:hover, .quick-action:hover, .priority-card:hover { transform: translateY(-2px); }
            .sidebar-link:active, .sidebar-ai-card:active, .btn:active, .quick-action:active, .priority-card:active { transform: translateY(0) scale(.985); }
            .sidebar-fixed .sidebar-link:hover,
            .sidebar-fixed .sidebar-link:active,
            .sidebar-fixed .sidebar-ai-card:hover,
            .sidebar-fixed .sidebar-ai-card:active {
                transform: none;
            }
            .form-control:focus, .form-select:focus { transform: translateY(-1px); }
            .loading-overlay.show .card { animation: icSoftRise var(--ic-motion-med) both; }
            .ic-logout-cancel:hover, .ic-logout-confirm-btn:hover, .ic-action-close:hover { transform: translateY(-1px); }
            .ic-logout-cancel:active, .ic-logout-confirm-btn:active, .ic-action-close:active { transform: translateY(0) scale(.985); }
            @keyframes icPageEnter { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
            @keyframes icSoftRise { from { opacity: 0; transform: translateY(10px) scale(.985); } to { opacity: 1; transform: translateY(0) scale(1); } }
            @media (prefers-reduced-motion: reduce) {
                .page-shell, .loading-overlay.show .card, .ic-action-dialog, .ic-logout-card { animation: none !important; }
                .sidebar-link, .sidebar-ai-card, .card, .glass-card, .module-tile, .climate-chip, .risk-card, .stat-card, .soft-section, .filter-panel, .quick-action, .priority-card, .btn, .form-control, .form-select { transition-duration: .01ms !important; }
            }
            @media (min-width: 1400px) { .page-shell { padding: 1.75rem 2rem; } }
            .card, .glass-card, .soft-section, .filter-panel, .module-tile, .climate-chip, .risk-card, .stat-card, .alert, .modal-content, .offcanvas, .page-hero, .weather-strip { min-width: 0; overflow-wrap: anywhere; }
            .card-body > *, .glass-card > *, .soft-section > *, .filter-panel > *, .page-hero > *, .topbar-inner > * { min-width: 0; }
            .btn, .badge { white-space: normal; }
            .modal-dialog { max-width: calc(100vw - 1rem); }
            .offcanvas { max-width: min(92vw, 380px); }
            @media (max-width: 1199.98px) { .page-hero .d-flex, .card-header.d-flex, .filter-panel .row, .topbar-inner { flex-wrap: wrap; } .table-responsive > .table { min-width: 760px; } }
            @media (max-width: 991.98px) { .app-main { margin-left: 0; } .sidebar-fixed { display: none; } .page-shell { padding: .9rem; } .topbar-inner { min-height: 64px; } .page-hero { padding: 1rem; } }
            @media (max-width: 767.98px) {
                body { background: linear-gradient(180deg, #1f4d3a, #1f4d3a); }
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
            @media (max-width: 575.98px) {
                .topbar .container-fluid { align-items: flex-start; }
                .topbar .btn { padding: .42rem .55rem; font-size: .78rem; }
                .weather-strip { display: none !important; }
                .page-hero { margin-bottom: .85rem; }
                .page-hero .btn, .filter-panel .btn, .filter-panel a.btn { width: 100%; }
                .filter-panel form > [class*="col-"] { width: 100%; }
                .card, .glass-card, .module-tile, .climate-chip, .soft-section { box-shadow: 0 .45rem 1rem rgba(13,31,24,.06); }
                .ic-logout-actions { grid-template-columns: 1fr; }
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
        <x-flash-toast />
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
        <div id="loadingOverlay" class="loading-overlay">
            <div class="card border-0 shadow-sm"><div class="card-body d-flex align-items-center gap-3"><div class="spinner-border" style="color: var(--ic-green-500);" role="status" aria-hidden="true"></div><div class="fw-semibold">Loading...</div></div></div>
        </div>
        <div id="pageProgress" class="page-progress" aria-hidden="true"></div>
        @auth
            @include('components.ai-chat-widget')
        @endauth
    </body>
</html>
