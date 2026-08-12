@php
    $heroClimate = \App\Support\ClimateSnapshot::latest();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_' , '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>{{ config('app.name', 'iClimate') }} | Weather Impact Analysis for Lian, Batangas</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Inter:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  <style>
    /* -- TOKENS --------------------------------------- */
    :root {
      --green-950: #163B2D;
      --green-900: #1F4D3A;
      --green-800: #1F4D3A;
      --green-700: #1F4D3A;
      --green-500: #5F8F78;
      --green-400: #7FD6B5;
      --green-200: #7FD6B5;
      --green-100: rgba(127,214,181,0.22);
      --green-50:  rgba(95,143,120,0.08);
      --sand:      #F7F6F2;
      --sand-dark: #E5E7EB;
      --gold:      #E8A73D;
      --gold-dark: #C6872A;
      --gold-light:#F6D58A;
      --ink:       #1F2937;
      --ink-mid:   #64748B;
      --ink-light: rgba(100,116,139,0.72);
      --white:     #FFFFFF;
      --border:    #5F8F78;
      --mint:      #7FD6B5;
      --hero-from: #1F4D3A;
      --hero-to:   #1F4D3A;
      --sidebar-bg: #F7F6F2;
      --sidebar-active-bg: rgba(127,214,181,0.18);
      --radius-sm: 4px;
      --radius-md: 10px;
      --radius-lg: 18px;
      --radius-xl: 32px;
      --radius-blob: 62% 38% 41% 59% / 48% 42% 58% 52%;
      --radius-pill: 100px;
      --shadow-sm: 0 1px 4px rgba(22,59,45,0.08);
      --shadow-md: 0 4px 20px rgba(22,59,45,0.12);
      --shadow-lg: 0 16px 56px rgba(22,59,45,0.18);
      --shadow-gold: 0 10px 28px rgba(232,167,61,0.35);
      --nav-h: 92px;
      --ease: cubic-bezier(0.4,0,0.2,1);
    }

    /* -- RESET ----------------------------------------- */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body {
      font-family: 'Inter', system-ui, sans-serif;
      font-size: 16px;
      line-height: 1.65;
      color: var(--ink);
      background: var(--green-800);
      -webkit-font-smoothing: antialiased;
    }
    img, svg { display: block; max-width: 100%; }
    a { color: inherit; text-decoration: none; }
    ul { list-style: none; }
    button { cursor: pointer; border: none; background: none; font-family: inherit; }
    p { color: var(--white); }

    /* -- TYPE ------------------------------------------ */
    h1, h2, h3, h4 {
      font-family: 'DM Serif Display', Georgia, serif;
      line-height: 1.1;
      font-weight: 400;
      color: var(--white);
    }
    h1 { font-size: clamp(2.4rem, 5.5vw, 4rem); letter-spacing: -0.02em; }
    h2 { font-size: clamp(1.8rem, 3.5vw, 2.8rem); letter-spacing: -0.02em; }
    h3 { font-size: 1.2rem; }
    .mono { font-family: 'DM Mono', monospace; }

    /* -- LAYOUT ---------------------------------------- */
    .container { max-width: 1120px; margin: 0 auto; padding: 0 24px; }

    /* -- BUTTONS --------------------------------------- */
    .btn {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 11px 24px;
      border-radius: var(--radius-pill);
      font-family: 'Inter', sans-serif;
      font-size: 0.875rem;
      font-weight: 600;
      transition: all 0.2s var(--ease);
      white-space: nowrap;
      letter-spacing: 0.01em;
    }
    .btn-primary {
      background: var(--gold);
      color: var(--ink);
      box-shadow: var(--shadow-gold);
    }
    .btn-primary:hover {
      background: var(--gold-dark);
      transform: translateY(-1px);
      box-shadow: 0 10px 28px rgba(232,167,61,0.48);
    }
    .btn-outline {
      border: 1.5px solid var(--white);
      color: var(--white);
      background: transparent;
    }
    .btn-outline:hover {
      background: var(--white);
      color: var(--ink);
    }
    .btn-ghost-light {
      color: var(--white);
      background: transparent;
      padding-left: 0;
    }
    .btn-ghost-light:hover { color: var(--gold); }
    .btn-lg { padding: 14px 32px; font-size: 0.95rem; border-radius: var(--radius-pill); }
    .btn-full { width: 100%; justify-content: center; }

    /* -- EYEBROW --------------------------------------- */
    .eyebrow {
      display: inline-flex; align-items: center; gap: 8px;
      font-family: 'DM Mono', monospace;
      font-size: 0.72rem;
      font-weight: 500;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: var(--green-500);
      margin-bottom: 20px;
    }
    .eyebrow::before {
      content: '';
      display: block;
      width: 20px;
      height: 1px;
      background: var(--green-500);
    }

    /* ===============================================
       NAVBAR
    =============================================== */
    .navbar {
      position: sticky;
      top: 0; left: 0; right: 0;
      z-index: 100;
      height: var(--nav-h);
      background: var(--green-800);
      border-bottom: 1px solid var(--border);
    }
    .nav-container {
      max-width: 1120px; margin: 0 auto;
      padding: 0 24px;
      height: 100%;
      display: flex; align-items: center; gap: 40px;
      background: transparent;
    }
    .nav-logo {
      display: flex; align-items: center; gap: 10px;
      flex-shrink: 0;
    }
    .logo-mark {
      width: 32px; height: 32px;
      background: var(--green-500);
      border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
    }
    .logo-text {
      font-family: 'DM Serif Display', serif;
      font-size: 1.2rem;
      font-weight: 400;
      color: var(--white);
      letter-spacing: -0.01em;
    }
    .logo-text em { font-style: italic; color: var(--gold); }
    .nav-links {
      display: flex; align-items: center; gap: 2px;
      margin-left: auto;
    }
    .nav-link {
      position: relative;
      padding: 6px 14px;
      border-radius: var(--radius-sm);
      font-size: 0.875rem;
      font-weight: 500;
      color: var(--mint);
      transition: color 0.2s, background 0.2s;
    }
    .nav-link:hover { color: var(--white); }
    .nav-link.active {
      color: var(--gold);
      font-weight: 600;
    }
    .nav-link.active::after {
      content: '';
      position: absolute;
      left: 50%; bottom: -2px;
      transform: translateX(-50%);
      width: 4px; height: 4px;
      border-radius: 50%;
      background: var(--gold);
    }
    .nav-actions { display: flex; align-items: center; gap: 10px; }
    .nav-login {
      padding: 9px 20px;
      border: 1px solid var(--white);
      border-radius: var(--radius-pill);
      font-size: 0.875rem;
      font-weight: 500;
      color: var(--white);
      transition: all 0.2s;
    }
    .nav-login:hover {
      background: var(--white);
      color: var(--ink);
    }
    .nav-cta {
      padding: 9px 22px;
      border-radius: var(--radius-pill);
      font-size: 0.875rem;
      font-weight: 600;
      color: var(--ink);
      background: var(--gold);
      transition: all 0.2s;
      white-space: nowrap;
    }
    .nav-cta:hover {
      background: var(--gold-dark);
      transform: translateY(-1px);
    }
    @media (max-width: 640px) { .nav-cta { display: none; } }
    .hamburger {
      display: none; flex-direction: column; gap: 5px;
      width: 28px; padding: 4px;
    }
    .hamburger span {
      display: block; height: 1.5px;
      background: var(--ink); border-radius: 2px;
      transition: all 0.2s var(--ease);
    }

    /* ===============================================
       HERO
    =============================================== */
    .hero {
      position: relative;
      background: linear-gradient(180deg, var(--hero-from), var(--hero-to));
      min-height: calc(100vh - var(--nav-h));
      display: flex; flex-direction: column;
      overflow: hidden;
    }
    /* Grain texture overlay */
    .hero::before {
      content: '';
      position: absolute; inset: 0;
      background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
      pointer-events: none; opacity: 0.5;
    }
    /* Radial glow */
    .hero::after {
      content: '';
      position: absolute;
      top: -20%; right: -10%;
      width: 70%; height: 100%;
      background: radial-gradient(ellipse at center, rgba(95,143,120,0.10) 0%, transparent 65%);
      pointer-events: none;
    }
    .hero-inner {
      flex: 1;
      max-width: 1120px;
      width: 100%;
      margin: 0 auto;
      padding: 0 24px;
      padding-top: 72px;
      padding-bottom: 100px;
      display: grid;
      grid-template-columns: 1fr 420px;
      gap: 80px;
      align-items: center;
      position: relative;
      z-index: 1;
    }
    .hero-location {
      display: inline-flex; align-items: center; gap: 6px;
      font-family: 'DM Mono', monospace;
      font-size: 0.72rem;
      font-weight: 500;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: var(--mint);
      margin-bottom: 24px;
    }
    .hero-location svg { flex-shrink: 0; }
    .hero-title {
      color: var(--white);
      margin-bottom: 8px;
    }
    .hero-title-italic {
      font-style: italic;
      color: var(--gold);
    }
    .hero-subtitle {
      font-family: 'DM Serif Display', serif;
      font-size: clamp(1rem, 2vw, 1.25rem);
      font-weight: 400;
      color: var(--white);
      margin-bottom: 24px;
      letter-spacing: -0.01em;
    }
    .hero-desc {
      font-size: 0.975rem;
      color: var(--white);
      max-width: 460px;
      line-height: 1.75;
      margin-bottom: 40px;
    }
    .hero-actions {
      display: flex; align-items: center; gap: 20px;
      flex-wrap: wrap; margin-bottom: 0;
    }
    .hero-text .trust-grid {
      margin-top: 40px;
      margin-bottom: 0;
    }

    /* ===============================================
       HERO VISUAL (location map panel)
    =============================================== */
    .hero-visual {
      position: relative;
      height: 480px;
    }
    .hero-map-panel {
      position: absolute;
      inset: 0;
      border-radius: var(--radius-lg);
      overflow: hidden;
      background-color: var(--green-950);
      background-image:
        linear-gradient(rgba(255,255,255,0.05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.05) 1px, transparent 1px);
      background-size: 28px 28px;
      border: 1px solid rgba(255,255,255,0.08);
      box-shadow: var(--shadow-lg);
    }
    .hero-map-dot {
      position: absolute;
      top: 16%; left: 50%;
      width: 8px; height: 8px;
      border-radius: 50%;
      background: var(--green-400);
      animation: heroMapPulse 2.4s infinite;
    }
    @keyframes heroMapPulse {
      0%   { box-shadow: 0 0 0 5px rgba(127,214,181,0.22), 0 0 18px 4px rgba(127,214,181,0.4), 0 0 0 0 rgba(127,214,181,0.5); }
      70%  { box-shadow: 0 0 0 5px rgba(127,214,181,0.22), 0 0 18px 4px rgba(127,214,181,0.4), 0 0 0 10px rgba(127,214,181,0); }
      100% { box-shadow: 0 0 0 5px rgba(127,214,181,0.22), 0 0 18px 4px rgba(127,214,181,0.4), 0 0 0 0 rgba(127,214,181,0); }
    }
    .hero-map-card {
      position: absolute;
      top: 30%; left: 50%;
      transform: translateX(-50%);
      width: 270px;
      background: rgba(22,59,45,.68);
      border: 1.5px solid rgba(127,214,181,.24);
      border-radius: var(--radius-md);
      padding: 14px 20px;
      backdrop-filter: blur(16px);
      box-shadow: 0 20px 45px rgba(0,0,0,.32);
    }
    .hmc-location {
      font-family: 'DM Mono', monospace;
      font-size: 0.8rem;
      color: var(--green-400);
      margin-bottom: 4px;
      letter-spacing: 0.02em;
    }
    .hmc-office {
      font-size: 0.9rem;
      font-weight: 600;
      color: var(--white);
    }

    /* Weather stat strip (bottom of hero map panel) */
    .hero-weather-strip {
      position: absolute;
      left: 20px; right: 20px; bottom: 20px;
      display: flex; gap: 12px; flex-wrap: wrap;
    }
    .hws-item {
      flex: 1 1 120px;
      display: flex; align-items: center; gap: 10px;
      background: rgba(22,59,45,.6);
      border: 1.5px solid rgba(127,214,181,.2);
      border-radius: var(--radius-md);
      padding: 12px 14px;
      backdrop-filter: blur(14px);
      box-shadow: 0 12px 28px rgba(0,0,0,.22);
    }
    .hws-icon { color: var(--green-400); flex-shrink: 0; }
    .hws-val { font-family: 'DM Serif Display', serif; font-size: 1.15rem; color: var(--white); line-height: 1; }
    .hws-label { font-family: 'DM Mono', monospace; font-size: 0.62rem; color: rgba(255,255,255,.5); text-transform: uppercase; letter-spacing: 0.04em; margin-top: 3px; }

    /* Trust / stats band */
    .trust-grid {
      display: flex;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
    }
    .trust-item {
      display: flex; align-items: center; gap: 12px;
      flex: 1;
      min-width: 150px;
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius-lg);
      padding: 14px 16px;
      box-shadow: var(--shadow-sm);
      backdrop-filter: blur(10px);
      transition: background .2s ease, border-color .2s ease;
    }
    .trust-item:hover { background: var(--green-50); border-color: rgba(95,143,120,.32); }
    .trust-icon {
      width: 40px; height: 40px;
      flex-shrink: 0;
      border-radius: 50%;
      background: rgba(232,167,61,0.12);
      border: 1px solid rgba(232,167,61,0.3);
      display: flex; align-items: center; justify-content: center;
    }
    .trust-num {
      font-family: 'DM Serif Display', serif;
      font-size: 1.25rem;
      color: var(--ink);
      line-height: 1;
      letter-spacing: -0.02em;
    }
    .trust-label {
      font-family: 'DM Mono', monospace;
      font-size: 0.6rem;
      color: var(--ink-light);
      text-transform: uppercase;
      letter-spacing: 0.06em;
      margin-top: 4px;
    }
    @media (max-width: 480px) {
      .trust-grid { flex-direction: column; }
    }

    /* ===============================================
       ABOUT
    =============================================== */
    .about-section {
      position: relative;
      background: var(--green-800);
      padding: 120px 0;
      overflow: hidden;
    }
    .about-section::before {
      content: '';
      position: absolute; inset: 0;
      background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
      pointer-events: none; opacity: 0.5;
    }
    .about-section::after {
      content: '';
      position: absolute;
      top: -15%; left: -10%;
      width: 60%; height: 70%;
      background: radial-gradient(ellipse at center, rgba(95,143,120,0.09) 0%, transparent 65%);
      pointer-events: none;
    }
    .about-section > .container { position: relative; z-index: 1; }
    .about-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 80px;
      align-items: center;
    }
    .about-visual {
      position: relative;
    }

    /* ===============================================
       ABOUT PHOTO + MISSION CARD
    =============================================== */
    .about-text { display: flex; flex-direction: column; gap: 20px; }
    .about-text .hero-location { margin-bottom: 0; }
    .about-text .section-title { margin-bottom: 4px; color: var(--white); }
    .about-text .section-title em { font-style: italic; color: var(--gold); }
    .about-text p { font-size: 0.975rem; line-height: 1.8; color: var(--ink-mid); }
    .about-tagline {
      display: flex; align-items: center; gap: 12px;
      font-family: 'DM Serif Display', serif;
      font-style: italic;
      font-size: 1.05rem;
      color: var(--gold);
    }
    .tagline-flourish {
      position: relative;
      width: 22px; height: 1px;
      background: var(--gold);
      flex-shrink: 0;
    }
    .tagline-flourish::after {
      content: '';
      position: absolute; top: 50%;
      transform: translateY(-50%);
      width: 4px; height: 4px;
      border-radius: 50%;
      background: var(--green-400);
    }
    .tagline-flourish:first-child::after { right: -9px; }
    .tagline-flourish:last-child::after { left: -9px; }
    .about-photo {
      position: relative;
      border-radius: var(--radius-lg);
      overflow: hidden;
      min-height: 420px;
      box-shadow: var(--shadow-lg);
      background-color: var(--green-950);
      background-image:
        linear-gradient(rgba(255,255,255,0.05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.05) 1px, transparent 1px),
        radial-gradient(ellipse at 30% 20%, rgba(95,143,120,0.28) 0%, transparent 60%);
      background-size: 28px 28px, 28px 28px, 100% 100%;
      border: 1px solid rgba(255,255,255,0.08);
    }
    .about-photo-leaf {
      position: absolute; right: -20px; bottom: -30px;
      width: 220px; height: 220px;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 220 220'%3E%3Cpath d='M110 10C60 40 30 90 40 150c6 24 22 40 40 48-12-48 0-100 30-148 10-16 10-30 0-40Z' fill='%235F8F78' fill-opacity='.18'/%3E%3Cpath d='M100 20C68 62 52 112 62 168' stroke='%237FD6B5' stroke-opacity='.4' stroke-width='2.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      pointer-events: none;
    }
    .mission-card {
      position: absolute;
      right: 20px; bottom: 20px; left: 20px;
      max-width: 340px;
      margin-left: auto;
      background: rgba(22,59,45,.72);
      border: 1.5px solid rgba(127,214,181,.24);
      border-radius: var(--radius-lg);
      box-shadow: 0 20px 45px rgba(0,0,0,.32);
      backdrop-filter: blur(16px);
      padding: 22px 24px;
      display: flex; gap: 14px; align-items: flex-start;
      z-index: 1;
    }
    .mission-icon {
      flex-shrink: 0;
      width: 42px; height: 42px;
      border-radius: 50%;
      background: rgba(232,167,61,0.16);
      border: 1px solid rgba(232,167,61,0.35);
      display: flex; align-items: center; justify-content: center;
    }
    .mission-card strong { display: block; font-size: 0.95rem; color: var(--white); margin-bottom: 4px; }
    .mission-card p { font-size: 0.83rem; line-height: 1.55; color: rgba(255,255,255,0.65); }

    /* ===============================================
       PILLARS ROW (5 cards)
    =============================================== */
    .pillars-row {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 16px;
      margin-top: 64px;
    }
    .pillar-card {
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius-lg);
      padding: 22px 18px;
      text-align: center;
      backdrop-filter: blur(10px);
      transition: background 0.2s ease, border-color 0.2s ease;
    }
    .pillar-card:hover { background: var(--white); border-color: rgba(95,143,120,.32); box-shadow: var(--shadow-sm); }
    .pillar-card .pillar-icon {
      width: 48px; height: 48px;
      border-radius: 50%;
      background: rgba(232,167,61,0.16);
      border: 1px solid rgba(232,167,61,0.35);
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 14px;
    }
    .pillar-card strong { display: block; font-size: 0.88rem; color: var(--ink); margin-bottom: 6px; }
    .pillar-card p { font-size: 0.78rem; line-height: 1.5; color: var(--ink-mid); }
    @media (max-width: 900px) {
      .pillars-row { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 560px) {
      .pillars-row { grid-template-columns: 1fr; }
    }

    /* ===============================================
       WHO WE ARE / BUILT FOR LIAN, BATANGAS
    =============================================== */
    .who-we-are {
      position: relative;
      overflow: hidden;
      margin-top: 72px;
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: var(--radius-xl);
      padding: 56px;
      display: grid;
      grid-template-columns: minmax(0,1fr) minmax(0,1.4fr);
      gap: 48px;
      align-items: center;
    }
    .who-we-are::before {
      content: "";
      position: absolute; left: -40px; bottom: -40px;
      width: 260px; height: 200px;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 260 200'%3E%3Cpath d='M0 160 Q60 120 130 150 T260 140 V200 H0Z' stroke='%235F8F78' stroke-opacity='0.35' fill='none' stroke-width='2'/%3E%3Cpath d='M20 170 h30 l10 -30 10 30 h30' stroke='%237FD6B5' stroke-opacity='0.4' fill='none' stroke-width='2'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      opacity: 0.8;
    }
    .who-we-are-text { position: relative; z-index: 1; }
    .who-we-are-text .eyebrow { color: var(--green-700); }
    .who-we-are-text h2 { color: var(--ink); margin: 10px 0 14px; }
    .who-we-are-text p { color: var(--ink-mid); font-size: 0.92rem; line-height: 1.7; }
    .wwa-grid {
      position: relative; z-index: 1;
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 20px;
    }
    .wwa-stat { display: flex; flex-direction: column; gap: 10px; }
    .wwa-icon {
      width: 44px; height: 44px;
      border-radius: 50%;
      background: rgba(232,167,61,0.12);
      border: 1px solid rgba(232,167,61,0.3);
      display: flex; align-items: center; justify-content: center;
    }
    .wwa-num {
      font-family: 'DM Serif Display', serif;
      font-size: 1.5rem;
      color: var(--ink);
      letter-spacing: -0.02em;
    }
    .wwa-label {
      font-family: 'DM Mono', monospace;
      font-size: 0.64rem;
      color: var(--ink-light);
      text-transform: uppercase;
      letter-spacing: 0.06em;
      margin-top: 2px;
    }
    .wwa-desc {
      font-size: 0.76rem;
      color: var(--ink-mid);
      line-height: 1.5;
    }
    @media (max-width: 900px) {
      .who-we-are { grid-template-columns: 1fr; padding: 36px 28px; }
      .wwa-grid { grid-template-columns: repeat(2, 1fr); }
    }

    /* ===============================================
       FEATURES
    =============================================== */
    .features-section {
      position: relative;
      background: var(--green-800);
      padding: 120px 0;
      overflow: hidden;
    }
    .features-section::before {
      content: '';
      position: absolute; top: 0; left: 0;
      width: 340px; height: 340px;
      background-image: radial-gradient(rgba(127,214,181,0.3) 1.5px, transparent 1.5px);
      background-size: 22px 22px;
      -webkit-mask-image: radial-gradient(circle at top left, black 0%, transparent 70%);
      mask-image: radial-gradient(circle at top left, black 0%, transparent 70%);
      pointer-events: none;
      z-index: 0;
    }
    .features-section > .container { position: relative; z-index: 1; }
    .section-header {
      max-width: 560px;
      margin-bottom: 56px;
    }
    .section-header .eyebrow { margin-bottom: 16px; }
    .section-header h2 { margin-bottom: 14px; }
    .section-header p { font-size: 1rem; line-height: 1.7; }
    .features-section > .container > .section-header {
      max-width: 560px;
      margin-left: auto;
      margin-right: auto;
      text-align: center;
    }
    .features-section > .container > .section-header .eyebrow {
      justify-content: center;
      color: var(--mint);
    }
    .features-section > .container > .section-header h2 { color: var(--white); }
    .features-section > .container > .section-header h2 em { font-style: italic; color: var(--gold); }
    .features-section > .container > .section-header p { color: var(--white); }
    .features-grid {
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      gap: 20px;
    }
    .feature-card {
      background: linear-gradient(180deg, rgba(247,246,242,.98), rgba(247,246,242,.94));
      padding: 32px 28px;
      display: flex; flex-direction: column; gap: 12px;
      border-radius: var(--radius-lg);
      border: 1px solid rgba(127,214,181,.72);
      transition: transform 0.25s var(--ease), box-shadow 0.25s var(--ease), border-color 0.25s;
      position: relative;
      min-width: 0;
    }
    .feature-card:hover {
      transform: translateY(-5px);
      box-shadow: var(--shadow-md);
      border-color: rgba(127,214,181,.88);
    }
    .fc-icon-wrap {
      width: 52px; height: 52px;
      background: rgba(255,255,255,.42);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      margin-bottom: 4px;
      border: 1px solid rgba(232,167,61,0.3);
      flex-shrink: 0;
    }
    .feature-card h3 {
      font-family: 'Inter', sans-serif;
      font-size: 0.975rem;
      font-weight: 700;
      color: var(--ink);
      line-height: 1.3;
      letter-spacing: -0.01em;
    }
    .feature-card p { font-size: 0.85rem; line-height: 1.65; color: var(--green-700); }
    .fc-badge {
      display: inline-block; margin-top: auto;
      font-family: 'DM Mono', monospace;
      font-size: 0.65rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--green-950);
      background: var(--gold-light);
      padding: 3px 10px;
      border-radius: var(--radius-pill);
      width: fit-content;
    }

    /* How it works */
    .how-block {
      position: relative;
      overflow: hidden;
      margin-top: 96px;
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: var(--radius-xl);
      padding: 56px;
    }
    .how-block::after {
      content: '';
      position: absolute; right: 20px; bottom: -16px;
      width: 200px; height: 400px;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 200 400'%3E%3Cpath d='M104 400 C98 300 94 210 108 100' stroke='%231F4D3A' stroke-width='4' fill='none' stroke-linecap='round'/%3E%3Cpath d='M104 260 C70 250 50 220 40 180 C74 186 100 206 108 240Z' fill='%235F8F78' fill-opacity='0.35'/%3E%3Cpath d='M110 320 C146 312 168 284 178 246 C142 250 116 272 108 306Z' fill='%235F8F78' fill-opacity='0.3'/%3E%3Cg fill='%237FD6B5' fill-opacity='0.55'%3E%3Cellipse cx='108' cy='95' rx='7' ry='14' transform='rotate(-10 108 95)'/%3E%3Cellipse cx='120' cy='78' rx='7' ry='14' transform='rotate(-4 120 78)'/%3E%3Cellipse cx='128' cy='58' rx='7' ry='14' transform='rotate(4 128 58)'/%3E%3Cellipse cx='130' cy='36' rx='7' ry='14' transform='rotate(10 130 36)'/%3E%3Cellipse cx='94' cy='82' rx='7' ry='14' transform='rotate(-22 94 82)'/%3E%3Cellipse cx='88' cy='60' rx='7' ry='14' transform='rotate(-28 88 60)'/%3E%3Cellipse cx='86' cy='38' rx='7' ry='14' transform='rotate(-32 86 38)'/%3E%3C/g%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-size: contain;
      background-position: bottom right;
      opacity: 0.9;
      pointer-events: none;
    }
    .how-block > * { position: relative; z-index: 1; }
    .how-block .section-header { margin-bottom: 56px; }
    .how-block .eyebrow { color: var(--green-700); }
    .how-block .section-header h2 { color: var(--ink); }
    .how-block .section-header h2 em { font-style: italic; color: var(--green-700); }
    .how-block .section-header p { color: var(--ink-mid); }
    .how-steps {
      display: flex;
      justify-content: space-between;
      gap: 20px;
      position: relative;
    }
    .how-step {
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
      gap: 14px;
      position: relative;
      z-index: 1;
    }
    .how-step-top {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .how-step-icon {
      width: 44px; height: 44px;
      border-radius: 50%;
      background: rgba(232,167,61,0.16);
      border: 1px solid rgba(232,167,61,0.35);
      color: var(--gold-dark);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .how-step-num {
      font-family: 'DM Mono', monospace;
      font-weight: 700;
      font-size: 1.05rem;
      color: var(--green-700);
    }
    .how-step:not(:last-child) .how-step-top::after {
      content: '';
      flex: 1;
      height: 0;
      border-top: 2px dotted rgba(127,214,181,.35);
      margin-left: 6px;
    }
    .how-step h3 {
      font-family: 'Inter', sans-serif;
      font-size: 0.95rem;
      font-weight: 700;
      color: var(--ink);
      letter-spacing: -0.01em;
    }
    .how-step p {
      font-size: 0.85rem;
      line-height: 1.6;
      color: var(--ink-mid);
    }
    @media (max-width: 1024px) {
      .how-block { padding: 36px 28px; }
    }

    /* ===============================================
       DASHBOARD PREVIEW
    =============================================== */
    .preview-section {
      background: var(--green-800);
      padding: 120px 0;
    }
    .preview-section .section-header { margin: 0 auto 56px; }
    .dashboard-mockup {
      border: 1px solid #E5E7EB;
      border-radius: var(--radius-lg);
      overflow: hidden;
      display: flex;
      height: 440px;
      box-shadow: var(--shadow-lg);
    }
    .dm-sidebar {
      width: 200px;
      flex-shrink: 0;
      background: var(--sidebar-bg);
      border-right: 1px solid var(--border);
      display: flex; flex-direction: column;
      padding: 20px 0;
    }
    .dms-brand {
      padding: 0 20px 20px;
      margin-bottom: 4px;
      border-bottom: 1px solid var(--border);
      display: flex; align-items: center; gap: 8px;
    }
    .dms-brand-mark {
      width: 26px; height: 26px;
      background: var(--green-500);
      border-radius: 6px;
      display: flex; align-items: center; justify-content: center;
      font-family: 'DM Serif Display', serif;
      font-size: 0.8rem;
      color: var(--white);
    }
    .dms-brand-name {
      font-family: 'DM Serif Display', serif;
      font-size: 0.9rem;
      color: var(--ink);
      letter-spacing: -0.01em;
    }
    .dms-nav { padding: 12px 10px; display: flex; flex-direction: column; gap: 2px; flex: 1; }
    .dms-link {
      display: flex; align-items: center; gap: 10px;
      padding: 8px 12px;
      border-radius: var(--radius-sm);
      font-size: 0.8rem;
      color: var(--ink-light);
      transition: all 0.15s;
      cursor: pointer;
    }
    .dms-link:hover, .dms-link.active {
      color: var(--ink);
      background: var(--sidebar-active-bg);
    }
    .dms-link.active { color: var(--green-700); }
    .dm-main { flex: 1; background: var(--green-50); overflow: hidden; display: flex; flex-direction: column; }
    .dm-topbar {
      height: 52px;
      background: var(--white);
      border-bottom: 1px solid #E5E7EB;
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 20px;
    }
    .dm-page-title {
      font-family: 'Inter', sans-serif;
      font-size: 0.875rem;
      font-weight: 600;
      color: var(--ink);
    }
    .dm-topbar-right { display: flex; align-items: center; gap: 10px; }
    .dm-chip {
      font-family: 'DM Mono', monospace;
      font-size: 0.65rem;
      background: var(--gold-light);
      color: var(--green-950);
      padding: 4px 10px;
      border-radius: var(--radius-pill);
      letter-spacing: 0.05em;
    }
    .dm-avatar {
      width: 28px; height: 28px;
      background: var(--green-500);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 0.62rem; font-weight: 600;
      color: var(--white);
      font-family: 'DM Mono', monospace;
    }
    .dm-body { flex: 1; padding: 16px; overflow: hidden; }
    .dm-widgets {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 10px;
      height: 100%;
    }
    .dmw {
      background: var(--white);
      border-radius: var(--radius-md);
      padding: 16px;
      border: 1px solid #E5E7EB;
      display: flex; flex-direction: column; gap: 6px;
    }
    .dmw-wide { grid-column: span 2; }
    .dmw-label {
      font-family: 'DM Mono', monospace;
      font-size: 0.62rem;
      color: var(--ink-light);
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }
    .dmw-val {
      font-family: 'DM Serif Display', serif;
      font-size: 1.6rem;
      color: var(--ink);
      letter-spacing: -0.02em;
      line-height: 1;
    }
    .dmw-trend {
      font-size: 0.72rem;
      color: var(--ink-light);
      display: flex; align-items: center; gap: 4px;
    }
    .dmw-trend.up { color: var(--green-500); }
    .dmw-chart svg { width: 100%; margin-top: 4px; }

    /* ===============================================
       CONTACT
    =============================================== */
    .contact-section {
      position: relative;
      background: var(--green-800);
      padding: 120px 0;
      overflow: hidden;
    }
    .contact-hero-media {
      position: absolute; left: 0; bottom: 0;
      width: 46%; height: 360px;
      max-width: 560px;
      overflow: hidden;
      -webkit-mask-image: linear-gradient(to top, black 45%, transparent 100%);
      mask-image: linear-gradient(to top, black 45%, transparent 100%);
      pointer-events: none;
      z-index: 0;
    }
    .contact-hero-media img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .contact-section > .container { position: relative; z-index: 1; }
    .contact-methods {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    .contact-method {
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius-lg);
      padding: 28px 26px;
      display: flex; flex-direction: column; gap: 8px;
      backdrop-filter: blur(10px);
      transition: background 0.2s ease, border-color 0.2s ease, transform 0.25s var(--ease), box-shadow 0.25s var(--ease);
    }
    .contact-method:hover {
      transform: translateY(-5px);
      box-shadow: var(--shadow-md);
      background: var(--white);
      border-color: rgba(95,143,120,.32);
    }
    .contact-method-icon {
      width: 44px; height: 44px;
      border-radius: 50%;
      background: rgba(232,167,61,0.16);
      border: 1px solid rgba(232,167,61,0.35);
      display: flex; align-items: center; justify-content: center;
      margin-bottom: 6px;
      flex-shrink: 0;
    }
    .contact-method h3 {
      font-family: 'Inter', sans-serif;
      font-size: 1rem;
      font-weight: 700;
      color: var(--ink);
      letter-spacing: -0.01em;
    }
    .contact-method p {
      font-size: 0.85rem;
      line-height: 1.6;
      color: var(--ink-mid);
    }
    .contact-method-link {
      margin-top: 10px;
      padding-top: 10px;
      border-top: 1px solid var(--border);
      font-family: 'DM Mono', monospace;
      font-size: 0.66rem;
      font-weight: 600;
      letter-spacing: 0.07em;
      text-transform: uppercase;
      color: var(--green-700);
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .contact-grid {
      display: grid;
      grid-template-columns: minmax(290px, 1.05fr) minmax(360px, 1.28fr) minmax(260px, .82fr);
      gap: 36px;
      align-items: stretch;
    }
    .contact-info { display: flex; flex-direction: column; gap: 0; }
    .contact-info .hero-location { margin-bottom: 18px; }
    .contact-info h2 { color: var(--white); margin-bottom: 10px; }
    .contact-info h2 em { font-style: italic; color: var(--gold); }
    .contact-info .about-tagline { margin-bottom: 18px; }
    .contact-info > p { font-size: 0.9rem; color: var(--white); margin-bottom: 28px; }
    .contact-details { display: flex; flex-direction: column; gap: 18px; }
    .cd-item {
      display: flex; align-items: flex-start; gap: 14px;
    }
    .cd-icon-wrap {
      width: 40px; height: 40px;
      border-radius: 50%;
      background: rgba(232,167,61,0.16);
      border: 1px solid rgba(232,167,61,0.35);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      font-size: 1rem;
    }
    .cd-text { min-width: 0; flex: 1; }
    .cd-text strong { font-size: 0.8rem; font-weight: 700; display: block; color: var(--white); margin-bottom: 2px; }
    .cd-text span { font-size: 0.875rem; color: var(--white); }
    .cd-hours { display: flex; flex-direction: column; gap: 3px; margin-top: 4px; }
    .cd-hours-row { display: grid; grid-template-columns: minmax(110px, 1fr) max-content; gap: 18px; align-items: baseline; font-size: 0.8rem; }
    .cd-hours-row span:first-child { color: var(--mint); }
    .cd-hours-row span:last-child { color: var(--ink); font-weight: 600; white-space: nowrap; text-align: right; }

    .contact-form-card {
      background: var(--white);
      border-radius: var(--radius-xl);
      padding: 40px 40px 38px;
      border: 1.5px solid var(--border);
      backdrop-filter: blur(10px);
      box-shadow: var(--shadow-md);
      display: flex;
      flex-direction: column;
      height: 100%;
    }
    .cfc-header {
      display: flex; align-items: center; gap: 14px;
      margin-bottom: 24px;
    }
    .cfc-icon {
      width: 44px; height: 44px;
      border-radius: 50%;
      background: rgba(232,167,61,0.16);
      border: 1px solid rgba(232,167,61,0.35);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .contact-form-card h3 {
      font-family: 'DM Serif Display', serif;
      font-size: 1.4rem;
      font-weight: 400;
      margin-bottom: 2px;
      color: var(--ink);
      letter-spacing: -0.01em;
    }
    .contact-form-sub {
      font-size: 0.85rem;
      color: var(--ink-mid);
    }
    .form-row {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(150px, .72fr);
      gap: 16px;
    }
    .form-group { margin-bottom: 18px; }
    .contact-form-card .form-group:has(textarea) {
      flex: 1;
      display: flex;
      flex-direction: column;
    }
    .contact-form-card .form-group:has(textarea) textarea.form-input {
      flex: 1;
      resize: none;
    }
    .form-group label {
      display: block;
      font-size: 0.8rem;
      font-weight: 600;
      color: var(--ink);
      margin-bottom: 7px;
      letter-spacing: 0.01em;
    }
    .form-input {
      width: 100%;
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius-md);
      padding: 10px 14px;
      font-family: 'Inter', sans-serif;
      font-size: 0.9rem;
      color: var(--ink);
      transition: all 0.2s;
      outline: none;
      resize: vertical;
    }
    select.form-input { cursor: pointer; }
    select.form-input option { color: var(--ink); }
    .form-input:focus {
      border-color: var(--green-500);
      box-shadow: 0 0 0 3px rgba(95,143,120,0.12);
    }
    .form-input::placeholder { color: var(--ink-light); }
    .btn-dark { background: var(--green-800); color: var(--white); }
    .btn-dark:hover {
      background: var(--green-950);
      transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(22,59,45,0.3);
    }

    /* ===============================================
       FOOTER
    =============================================== */
    .footer {
      background: var(--green-950);
      padding: 72px 0 36px;
      border-top: 1px solid rgba(255,255,255,0.1);
    }
    .footer-grid {
      display: grid;
      grid-template-columns: 2fr 1fr 2fr 1fr;
      gap: 48px;
      margin-bottom: 56px;
      padding-bottom: 56px;
      border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .footer-social h4 {
      font-family: 'DM Mono', monospace;
      font-size: 0.65rem;
      font-weight: 500;
      color: var(--mint);
      letter-spacing: 0.12em;
      text-transform: uppercase;
      margin-bottom: 18px;
    }
    .footer-social-icons { display: flex; gap: 12px; }
    .footer-social-icon {
      width: 40px; height: 40px;
      border-radius: 50%;
      background: rgba(232,167,61,0.16);
      border: 1px solid rgba(232,167,61,0.3);
      display: flex; align-items: center; justify-content: center;
      color: var(--gold);
      transition: background 0.2s, border-color 0.2s;
    }
    .footer-social-icon:hover { background: var(--gold); border-color: var(--gold); color: var(--ink); }
    .footer-col-pair {
      display: grid;
      grid-template-columns: 1fr 1.5fr;
      column-gap: 32px;
      align-items: start;
    }
    .footer-logo { margin-bottom: 14px; }
    .footer-tagline {
      font-size: 0.875rem;
      color: var(--white);
      line-height: 1.6;
      max-width: 260px;
    }
    .footer-links h4 {
      font-family: 'DM Mono', monospace;
      font-size: 0.65rem;
      font-weight: 500;
      color: var(--mint);
      letter-spacing: 0.12em;
      text-transform: uppercase;
      margin-bottom: 18px;
    }
    .footer-links ul { display: flex; flex-direction: column; gap: 12px; }
    .footer-links a {
      font-size: 0.875rem;
      color: var(--white);
      transition: color 0.2s;
    }
    .footer-links a:hover { color: var(--gold); }
    .footer-bottom {
      display: flex; align-items: center; justify-content: space-between;
    }
    .footer-bottom p { font-size: 0.8rem; color: var(--mint); }
    .footer-badge {
      font-family: 'DM Mono', monospace;
      font-size: 0.65rem;
      letter-spacing: 0.08em;
      color: var(--mint);
      text-transform: uppercase;
    }

    /* ===============================================
       RESPONSIVE
    =============================================== */
    @media (max-width: 1024px) {
      .hero-inner { grid-template-columns: 1fr; gap: 0; }
      .hero-visual { display: none; }
      .about-grid { grid-template-columns: 1fr; gap: 40px; }
      .about-visual { order: 2; }
      .about-text { order: 1; }
      .features-hero-media { display: none; }
      .features-grid { grid-template-columns: repeat(3, 1fr); }
      .how-steps { flex-wrap: wrap; }
      .how-step-top::after { display: none; }
      .how-step { flex: 1 1 45%; }
      .contact-hero-media { display: none; }
      .contact-grid { grid-template-columns: 1fr; gap: 48px; }
      .contact-methods { flex-direction: row; flex-wrap: wrap; }
      .contact-methods .contact-method { flex: 1 1 240px; }
      .footer-grid { grid-template-columns: 1fr 1fr; }
      .footer-brand { grid-column: span 2; }
      .footer-social { grid-column: span 2; }
      .footer-col-pair { grid-template-columns: 1fr 1.5fr; column-gap: 24px; }
      .dm-widgets { grid-template-columns: repeat(2,1fr); }
      .dmw-wide { grid-column: span 2; }
      .dashboard-mockup { height: auto; flex-direction: column; }
      .dm-sidebar { width: 100%; flex-direction: row; flex-wrap: wrap; height: auto; padding: 12px 12px; }
      .dms-brand { border-bottom: none; padding-bottom: 0; margin-bottom: 0; }
      .dms-nav { flex-direction: row; padding: 0 4px; gap: 2px; }
      .dm-body { padding: 12px; }
    }
    @media (max-width: 768px) {
      .nav-links { display: none; }
      .nav-links.open {
        display: flex; flex-direction: column;
        position: fixed;
        top: var(--nav-h); left: 0; right: 0;
        background: var(--green-800);
        padding: 16px 24px;
        gap: 4px;
        border-top: 1px solid var(--border);
        box-shadow: var(--shadow-md);
        z-index: 99;
      }
      .hamburger { display: flex; }
      .nav-login { display: none; }
      .features-grid { grid-template-columns: 1fr; }
      .how-step { flex: 1 1 100%; }
      .contact-methods { flex-direction: column; }
      .form-row { grid-template-columns: 1fr; gap: 0; }
      .footer-grid { grid-template-columns: 1fr; }
      .footer-brand { grid-column: span 1; }
      .footer-col-pair { grid-template-columns: 1fr 1.5fr; column-gap: 20px; }
      .footer-bottom { flex-direction: column; gap: 8px; text-align: center; }
    }
    @media (max-width: 480px) {
      .hero-actions { flex-direction: column; align-items: flex-start; }
      .hero-actions .btn-lg { width: 100%; justify-content: center; }
      .contact-form-card { padding: 28px 20px; }
    }

    /* -- SCROLL ANIMATIONS ------------------------- */
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(24px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .fade-up { animation: fadeUp 0.6s var(--ease) both; }
    .fade-up-1 { animation-delay: 0.1s; }
    .fade-up-2 { animation-delay: 0.2s; }
    .fade-up-3 { animation-delay: 0.3s; }
    .fade-up-4 { animation-delay: 0.45s; }

    @media (prefers-reduced-motion: reduce) {
      .fade-up { animation: none; }
    }

    /* ===============================================
       USER ROLES
    =============================================== */
    .roles-section {
      position: relative;
      background: var(--green-800);
      padding: 120px 0;
      overflow: hidden;
    }
    .roles-leaf-deco {
      position: absolute; left: -30px; bottom: -20px;
      width: 300px; height: 480px;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 300 480'%3E%3Cpath d='M40 480 C36 340 42 220 70 90' stroke='%235F8F78' stroke-width='2.5' fill='none' stroke-opacity='0.35'/%3E%3Cpath d='M62 380 C110 368 142 336 156 292 C104 296 68 328 56 372Z' fill='none' stroke='%235F8F78' stroke-width='2' stroke-opacity='0.3'/%3E%3Cpath d='M56 300 C104 292 138 262 154 220 C102 222 66 252 52 292Z' fill='none' stroke='%235F8F78' stroke-width='2' stroke-opacity='0.3'/%3E%3Cpath d='M52 220 C98 210 130 182 146 142 C96 144 62 172 48 212Z' fill='none' stroke='%235F8F78' stroke-width='2' stroke-opacity='0.25'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      pointer-events: none;
      z-index: 0;
    }
    .roles-section::before {
      content: '';
      position: absolute; top: 8%; left: 4%;
      width: 300px; height: 300px;
      background-image: radial-gradient(rgba(127,214,181,0.22) 1.5px, transparent 1.5px);
      background-size: 24px 24px;
      -webkit-mask-image: radial-gradient(circle at center, black 0%, transparent 70%);
      mask-image: radial-gradient(circle at center, black 0%, transparent 70%);
      pointer-events: none;
      z-index: 0;
    }
    .roles-section > .container { position: relative; z-index: 1; }
    .roles-section .section-header {
      text-align: center;
      margin-left: auto;
      margin-right: auto;
      max-width: 620px;
      margin-bottom: 64px;
    }
    .roles-section .section-header .eyebrow { justify-content: center; color: var(--mint); }
    .roles-section .section-header h2 { color: var(--white); }
    .roles-section .section-header h2 em { font-style: italic; color: var(--gold); }
    .roles-section .section-header p { color: var(--white); }
    .roles-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 24px;
      align-items: stretch;
    }
    .role-card {
      background: linear-gradient(180deg, rgba(247,246,242,.98), rgba(247,246,242,.94));
      border: 1.5px solid rgba(127,214,181,.72);
      border-radius: var(--radius-xl);
      padding: 40px 32px 32px;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      box-shadow: var(--shadow-lg);
      transition: box-shadow 0.25s var(--ease), transform 0.25s var(--ease), border-color 0.25s;
    }
    .role-card:hover {
      box-shadow: var(--shadow-md);
      transform: translateY(-4px);
      border-color: rgba(127,214,181,.88);
    }
    .role-workspace-label {
      font-family: 'DM Mono', monospace;
      font-size: 0.65rem;
      font-weight: 600;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: var(--green-500);
      margin-bottom: 8px;
    }
    .role-card.role-card--highlight {
      background: var(--green-950);
      border-color: var(--green-900);
      margin-top: -16px;
      padding-bottom: 48px;
      box-shadow: var(--shadow-lg);
    }
    .role-card.role-card--highlight:hover { border-color: var(--green-800); }
    .role-card.role-card--highlight .role-workspace-label { color: var(--gold); }
    .role-card.role-card--highlight h3 { color: var(--white); }
    .role-card.role-card--highlight > p { color: rgba(255,255,255,0.55); }
    .role-card.role-card--highlight .role-divider { background: rgba(255,255,255,0.1); }
    .role-card.role-card--highlight .role-features-label { color: rgba(255,255,255,0.4); }
    .role-card.role-card--highlight .role-feature-item { color: rgba(255,255,255,0.78); }
    .role-card.role-card--highlight .role-feature-item svg { color: var(--gold); }
    .role-card.role-card--highlight .role-cta {
      background: var(--gold);
      border-color: var(--gold);
      color: var(--ink);
    }
    .role-card.role-card--highlight .role-cta:hover {
      background: var(--gold-dark);
      border-color: var(--gold-dark);
    }
    @media (max-width: 1024px) {
      .role-card.role-card--highlight { margin-top: 0; padding-bottom: 32px; }
    }

    /* How the roles connect */
    .roles-connect {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: var(--radius-xl);
      padding: 32px 40px;
      margin-top: 64px;
    }
    .connect-bar {
      display: flex;
      align-items: center;
    }
    .connect-intro {
      display: flex; align-items: center; gap: 16px;
      flex: 1 1 260px;
      min-width: 0;
      padding-right: 32px;
    }
    .connect-intro h3 {
      font-family: 'Inter', sans-serif;
      font-size: 1.05rem;
      font-weight: 700;
      color: var(--ink);
      line-height: 1.3;
      letter-spacing: -0.01em;
    }
    .connect-divider {
      width: 1px;
      align-self: stretch;
      background: var(--border);
      flex-shrink: 0;
    }
    .connect-item {
      display: flex; align-items: flex-start; gap: 12px;
      flex: 1 1 200px;
      min-width: 0;
      padding: 0 28px;
    }
    .connect-icon {
      width: 44px; height: 44px;
      border-radius: 50%;
      background: rgba(232,167,61,0.16);
      border: 1px solid rgba(232,167,61,0.35);
      color: var(--gold-dark);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .connect-icon-lg { width: 52px; height: 52px; }
    .connect-item h4 {
      font-family: 'Inter', sans-serif;
      font-size: 0.9rem;
      font-weight: 700;
      color: var(--ink);
      letter-spacing: -0.01em;
      margin-bottom: 4px;
    }
    .connect-item p {
      font-size: 0.78rem;
      line-height: 1.5;
      color: var(--ink-mid);
    }
    @media (max-width: 900px) {
      .roles-connect { padding: 28px 24px; }
      .connect-bar { flex-direction: column; align-items: stretch; gap: 20px; }
      .connect-intro { padding-right: 0; }
      .connect-divider { width: 100%; height: 1px; }
      .connect-item { padding: 0; }
    }
    /* Image logo wrap - real role images, contained in a white circle */
    .role-icon-wrap--img {
      width: 96px; height: 96px;
      border-radius: 50%;
      overflow: hidden;
      background: var(--white);
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 20px;
      flex-shrink: 0;
      border: 3px solid var(--white);
      box-shadow: 0 0 0 4px rgba(232,167,61,0.2), var(--shadow-sm);
    }
    .role-card--highlight .role-icon-wrap--img {
      border-color: rgba(255,255,255,0.14);
      box-shadow: 0 0 0 4px rgba(232,167,61,0.24), 0 10px 24px rgba(0,0,0,0.3);
    }
    .role-icon-wrap--img img {
      width: 100%; height: 100%;
      object-fit: contain;
      padding: 6px;
      display: block;
    }
    .role-card h3 {
      font-family: 'Inter', sans-serif;
      font-size: 1.05rem;
      font-weight: 700;
      color: var(--ink);
      margin-bottom: 12px;
      letter-spacing: -0.01em;
    }
    .role-card > p {
      font-size: 0.875rem;
      line-height: 1.75;
      color: var(--green-700);
      margin-bottom: 24px;
    }
    .role-divider {
      width: 100%;
      height: 1px;
      background: rgba(95,143,120,.24);
      margin-bottom: 20px;
    }
    .role-features-label {
      font-family: 'DM Mono', monospace;
      font-size: 0.62rem;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: rgba(31,77,58,.72);
      margin-bottom: 14px;
    }
    .role-features {
      display: flex; flex-direction: column; gap: 9px;
      margin-bottom: 28px;
      flex: 1;
      width: 100%;
      text-align: left;
    }
    .role-feature-item {
      display: flex; align-items: center; gap: 10px;
      font-size: 0.875rem;
      color: var(--green-800);
    }
    .role-feature-item svg { flex-shrink: 0; color: var(--gold-dark); }
    .role-cta {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      padding: 11px 24px;
      border-radius: var(--radius-pill);
      border: 1.5px solid var(--green-800);
      font-size: 0.875rem;
      font-weight: 600;
      color: var(--green-800);
      transition: all 0.2s var(--ease);
      margin-top: auto;
      width: 100%;
    }
    .role-cta:hover {
      background: var(--green-800);
      border-color: var(--green-800);
      color: var(--white);
    }
    @media (max-width: 1024px) {
      .roles-grid { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 640px) {
      .roles-grid { grid-template-columns: 1fr; }
    }
  
    /* Laravel self-contained asset fallbacks */
    .brand-wordmark {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      color: var(--white);
    }
    .brand-glyph {
      width: 38px;
      height: 38px;
      border-radius: 10px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, var(--green-500), var(--green-800));
      color: var(--white);
      font-family: 'DM Mono', monospace;
      font-size: 0.78rem;
      font-weight: 700;
      box-shadow: 0 10px 28px rgba(95,143,120,0.24);
    }
    .brand-copy { display: flex; flex-direction: column; line-height: 1.05; }
    .brand-copy strong {
      font-family: 'DM Serif Display', Georgia, serif;
      font-size: 1.16rem;
      font-weight: 400;
      letter-spacing: 0;
    }
    .brand-copy small {
      color: rgba(255,255,255,0.48);
      font-family: 'DM Mono', monospace;
      font-size: 0.58rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      margin-top: 3px;
    }
    .footer-logo .brand-glyph { filter: none; }
    .role-fallback {
      width: 96px;
      height: 96px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 24px;
      color: var(--white);
      font-family: 'DM Mono', monospace;
      font-weight: 700;
      font-size: 1.05rem;
      box-shadow: 0 18px 40px rgba(22,59,45,0.14);
    }
    .role-farmer { background: linear-gradient(135deg, #5F8F78, #1F4D3A); }
    .role-mao { background: linear-gradient(135deg, var(--gold), #1F4D3A); }
    .role-it { background: linear-gradient(135deg, #1F4D3A, #163B2D); }
    .contact-form-card button[type="button"] { cursor: default; }
    .brand-logo-img {
      height: 76px;
      width: auto;
      max-width: 300px;
      object-fit: contain;
      display: block;
    }
    @media (max-width: 640px) {
      .brand-logo-img {
        height: 58px;
        max-width: 230px;
      }
    }

    /* Responsive hardening: keep the landing page stable on narrow screens. */
    html, body {
      width: 100%;
      max-width: 100%;
      overflow-x: hidden;
    }
    body {
      text-rendering: optimizeLegibility;
    }
    .container,
    .nav-container,
    .hero-inner,
    section,
    .navbar,
    .footer,
    .dashboard-mockup,
    .contact-form-card,
    .role-card,
    .feature-card,
    .pillar-card {
      min-width: 0;
    }
    .btn,
    .nav-login,
    .nav-cta,
    .role-cta,
    input,
    textarea,
    select,
    button {
      max-width: 100%;
    }
    input,
    textarea,
    select {
      min-width: 0;
    }
    h1,
    h2,
    h3,
    p,
    .btn,
    .nav-link,
    .role-card,
    .feature-card,
    .contact-method,
    .footer-links {
      overflow-wrap: anywhere;
    }
    @media (max-width: 768px) {
      :root { --nav-h: 76px; }
      .container,
      .nav-container,
      .hero-inner {
        padding-left: 18px;
        padding-right: 18px;
      }
      .nav-container {
        gap: 14px;
        justify-content: space-between;
      }
      .nav-actions {
        margin-left: auto;
      }
      .nav-links.open {
        max-height: calc(100dvh - var(--nav-h));
        overflow-y: auto;
      }
      .nav-link {
        width: 100%;
        padding: 11px 4px;
      }
      .hero {
        min-height: auto;
      }
      .hero-inner {
        padding-top: 42px;
        padding-bottom: 56px;
      }
      .hero-desc {
        max-width: none;
      }
      .contact-hero-media {
        display: none;
      }
      .about-section,
      .roles-section {
        padding: 72px 0;
      }
      .mission-card {
        position: relative;
        inset: auto;
        max-width: none;
        margin: -96px 16px 0;
      }
      .about-photo {
        min-height: 340px;
      }
      .dashboard-mockup {
        width: 100%;
        overflow: hidden;
      }
      .dm-sidebar,
      .dms-nav {
        min-width: 0;
      }
    }
    @media (max-width: 560px) {
      .container,
      .nav-container,
      .hero-inner {
        padding-left: 14px;
        padding-right: 14px;
      }
      .brand-logo-img {
        height: 48px;
        max-width: min(210px, 56vw);
      }
      .nav-actions {
        gap: 6px;
      }
      .hamburger {
        flex: 0 0 auto;
      }
      .hero-actions,
      .contact-methods,
      .footer-col-pair {
        width: 100%;
      }
      .btn,
      .role-cta {
        white-space: normal;
        text-align: center;
      }
      .footer-col-pair {
        grid-template-columns: 1fr;
        row-gap: 28px;
      }
      .roles-connect,
      .contact-form-card,
      .role-card {
        border-radius: 18px;
        padding-left: 20px;
        padding-right: 20px;
      }
      .mission-card {
        display: block;
        padding: 18px;
      }
      .mission-icon {
        margin-bottom: 12px;
      }
    }
    @media (max-width: 380px) {
      .container,
      .nav-container,
      .hero-inner {
        padding-left: 10px;
        padding-right: 10px;
      }
      .brand-logo-img {
        max-width: 52vw;
      }
    }
  </style>
</head>
<body>
<x-flash-toast />

<!-- NAVBAR -->
<nav class="navbar" id="navbar">
  <div class="nav-container">
    <a href="{{ url('/') }}" class="nav-logo">
      <img src="{{ asset('images/iclimate-logo.png') }}" alt="iClimate" class="brand-logo-img">
    </a>
    <ul class="nav-links" id="navLinks">
      <li><a href="#home" class="nav-link active">Home</a></li>
      <li><a href="#about" class="nav-link">About</a></li>
      <li><a href="#features" class="nav-link">Features</a></li>
      <li><a href="#roles" class="nav-link">User Roles</a></li>
      <li><a href="#contact" class="nav-link">Contact</a></li>
    </ul>
    <div class="nav-actions">
      <a href="{{ route('login') }}" class="nav-login">Login</a>
      <a href="{{ route('register') }}" class="nav-cta">Create Account</a>
      <button class="hamburger" id="hamburger" aria-label="Menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="hero" id="home">
  <div class="hero-inner">
    <div class="hero-text">
      <div class="hero-location fade-up fade-up-1">
        <svg width="10" height="12" viewBox="0 0 10 12" fill="none">
          <path d="M5 0C2.24 0 0 2.24 0 5c0 3.75 5 7 5 7s5-3.25 5-7c0-2.76-2.24-5-5-5zm0 6.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3z" fill="currentColor"/>
        </svg>
        Lian, Batangas &mdash; Philippines
      </div>
      <h1 class="hero-title fade-up fade-up-2">
        Climate-smart<br>
        <span class="hero-title-italic">rice farming.</span>
      </h1>
      <p class="hero-subtitle fade-up fade-up-2">iClimate Decision Support System</p>
      <p class="hero-desc fade-up fade-up-3">A web-based weather impact analysis and rice yield prediction platform for Lian, Batangas &mdash; built for rice farmers, MAO staff with technicians, and IT personnel.</p>
      <div class="hero-actions fade-up fade-up-3">
        <a href="{{ route('login') }}" class="btn btn-primary btn-lg">
          <svg width="15" height="15" viewBox="0 0 20 20" fill="none"><path d="M10 2c3 4 6 7.5 6 10.5A6 6 0 1 1 4 12.5C4 9.5 7 6 10 2z" stroke="#163B2D" stroke-width="1.6"/></svg>
          Get Started
        </a>
        <a href="#about" class="btn-ghost-light btn">
          Learn More
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7h10M7 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
      </div>
      <div class="trust-grid fade-up fade-up-4">
        <div class="trust-item">
          <div class="trust-icon">
            <svg width="18" height="18" viewBox="0 0 22 22" fill="none"><path d="M11 2a5 5 0 100 10 5 5 0 000-10zM3 20c0-4.4 3.6-7 8-7s8 2.6 8 7" stroke="#E8A73D" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
          <div>
            <div class="trust-num">3</div>
            <div class="trust-label">User Roles</div>
          </div>
        </div>
        <div class="trust-item">
          <div class="trust-icon">
            <svg width="18" height="18" viewBox="0 0 22 22" fill="none"><path d="M3 17V9M9 17V5M15 17v-7M19 17V3" stroke="#E8A73D" stroke-width="1.6" stroke-linecap="round"/></svg>
          </div>
          <div>
            <div class="trust-num">24/7</div>
            <div class="trust-label">Real-Time Monitoring</div>
          </div>
        </div>
        <div class="trust-item">
          <div class="trust-icon">
            <svg width="18" height="18" viewBox="0 0 22 22" fill="none"><path d="M6.5 15a4 4 0 01-.5-7.97A5.5 5.5 0 0116.9 8.6 3.6 3.6 0 0116 15.5H6.5z" stroke="#E8A73D" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 18.5v1M11.5 18.5v2M15 18.5v1" stroke="#E8A73D" stroke-width="1.6" stroke-linecap="round"/></svg>
          </div>
          <div>
            <div class="trust-num">PAGASA</div>
            <div class="trust-label">Verified Data Source</div>
          </div>
        </div>
      </div>
    </div>

    <div class="hero-visual fade-up fade-up-3">
      <div class="hero-map-panel">
        <div class="hero-map-dot"></div>
        <div class="hero-map-card">
          <div class="hmc-location">Lian, Batangas</div>
          <div class="hmc-office">Municipal Agricultural Office</div>
        </div>
        <div class="hero-weather-strip">
          <div class="hws-item">
            <svg class="hws-icon" width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M5.5 10.5a3 3 0 0 1 .6-5.9 4 4 0 0 1 7.7.7A2.7 2.7 0 0 1 13.5 10.5h-8Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M6 13.5v1M9 13.5v1.6M12 13.5v1" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
            <div><div class="hws-val">{{ $heroClimate ? round((float) $heroClimate->temperature) : 26 }}&deg;C</div><div class="hws-label">Temperature</div></div>
          </div>
          <div class="hws-item">
            <svg class="hws-icon" width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 2c1.6 2.2 3 4.3 3 6.1A3 3 0 1 1 7 8.1C7 6.3 8.4 4.2 10 2Z" stroke="currentColor" stroke-width="1.4"/></svg>
            <div><div class="hws-val">{{ $heroClimate ? round((float) $heroClimate->humidity) : 78 }}%</div><div class="hws-label">Humidity</div></div>
          </div>
          <div class="hws-item">
            <svg class="hws-icon" width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M2 7h9a2.2 2.2 0 1 0-2.2-2.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M2 11.5h12.2a2.4 2.4 0 1 1-2.4 2.4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
            <div><div class="hws-val">{{ $heroClimate ? round((float) $heroClimate->wind_speed) : 10 }} km/h</div><div class="hws-label">Wind Speed</div></div>
          </div>
          <div class="hws-item">
            <svg class="hws-icon" width="20" height="20" viewBox="0 0 20 20" fill="none"><rect x="4" y="3" width="2.6" height="11" rx="1" fill="currentColor"/><rect x="8.7" y="6" width="2.6" height="8" rx="1" fill="currentColor" opacity=".7"/><rect x="13.4" y="8.5" width="2.6" height="5.5" rx="1" fill="currentColor" opacity=".5"/></svg>
            <div><div class="hws-val">{{ $heroClimate ? number_format((float) $heroClimate->rainfall, 1) : '2.4' }} mm</div><div class="hws-label">Rainfall Today</div></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ABOUT -->
<section class="about-section" id="about">
  <div class="container">
    <div class="about-grid">
      <div class="about-text">
        <div>
          <div class="hero-location fade-up fade-up-1">
            <svg width="10" height="12" viewBox="0 0 10 12" fill="none">
              <path d="M5 0C2.24 0 0 2.24 0 5c0 3.75 5 7 5 7s5-3.25 5-7c0-2.76-2.24-5-5-5zm0 6.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3z" fill="currentColor"/>
            </svg>
            Lian, Batangas &mdash; Philippines
          </div>
          <h2 class="section-title">About <em>iClimate</em></h2>
          <p class="about-tagline"><span class="tagline-flourish"></span>Understand today. Prepare tomorrow.<span class="tagline-flourish"></span></p>
        </div>
        <p>iClimate is a web-based decision support system that combines climate data and local rice production records to help rice farmers, MAO staff with technicians, and IT personnel make informed, data-driven decisions for better agricultural outcomes in Lian, Batangas.</p>
      </div>

      <div class="about-visual">
        <div class="about-photo">
          <div class="about-photo-leaf" aria-hidden="true"></div>
          <div class="mission-card">
            <div class="mission-icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 2c3 4 6 7.5 6 10.5A6 6 0 1 1 4 12.5C4 9.5 7 6 10 2z" stroke="#C6872A" stroke-width="1.6"/></svg>
            </div>
            <div>
              <strong>Our Mission</strong>
              <p>To empower the farming community of Lian, Batangas through accurate climate information, smart analytics, and practical recommendations.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="pillars-row">
      <div class="pillar-card">
        <div class="pillar-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M2 12c1.5-3.5 4-5 8-5s6.5 1.5 8 5" stroke="#C6872A" stroke-width="1.6" stroke-linecap="round"/><circle cx="10" cy="5.5" r="1.6" fill="#C6872A"/></svg></div>
        <strong>Climate-Informed</strong>
        <p>Integrating PAGASA climate data for real-time weather monitoring and seasonal forecasting.</p>
      </div>
      <div class="pillar-card">
        <div class="pillar-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 2c2.2 3 4 5.3 4 7.5A4 4 0 1 1 6 9.5C6 7.3 7.8 5 10 2z" stroke="#C6872A" stroke-width="1.5"/></svg></div>
        <strong>Rice-Focused</strong>
        <p>Specialized in rice production analysis, yield prediction, and planting recommendations.</p>
      </div>
      <div class="pillar-card">
        <div class="pillar-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><rect x="3" y="10" width="3" height="7" fill="#C6872A"/><rect x="8.5" y="6" width="3" height="11" fill="#C6872A" opacity="0.85"/><rect x="14" y="3" width="3" height="14" fill="#C6872A" opacity="0.6"/></svg></div>
        <strong>Data-Driven</strong>
        <p>Using historical records and analytics to generate accurate insights and reports.</p>
      </div>
      <div class="pillar-card">
        <div class="pillar-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="7" cy="7" r="2.6" stroke="#C6872A" stroke-width="1.4"/><circle cx="14" cy="8" r="2.2" stroke="#C6872A" stroke-width="1.4"/><path d="M2.5 16c0.5-3 2-4.5 4.5-4.5s4 1.5 4.5 4.5" stroke="#C6872A" stroke-width="1.4" stroke-linecap="round"/><path d="M11.5 16c0.4-2.4 1.7-3.7 3.5-3.7s3 1.1 3.5 3" stroke="#C6872A" stroke-width="1.4" stroke-linecap="round"/></svg></div>
        <strong>Community-Centered</strong>
        <p>Built for farmers, supported by MAO staff and technicians, and optimized by IT personnel.</p>
      </div>
      <div class="pillar-card">
        <div class="pillar-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 2l7 2.5v5c0 4.5-3 7.5-7 8.5-4-1-7-4-7-8.5v-5L10 2z" stroke="#C6872A" stroke-width="1.5" stroke-linejoin="round"/></svg></div>
        <strong>Secure &amp; Reliable</strong>
        <p>Ensuring data security, system reliability, and continuous improvement.</p>
      </div>
    </div>

    <div class="who-we-are">
      <div class="who-we-are-text">
        <span class="eyebrow">Who We Are</span>
        <h2>Built for Lian, Batangas</h2>
        <p>iClimate is developed to address the unique agricultural challenges of Lian, Batangas. By combining local knowledge with modern technology, we help our community adapt to climate variability and build a more resilient future.</p>
      </div>
      <div class="wwa-grid">
        <div class="wwa-stat">
          <div class="wwa-icon"><svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="6.5" cy="6" r="2.2" stroke="#E8A73D" stroke-width="1.3"/><circle cx="12" cy="7" r="1.8" stroke="#E8A73D" stroke-width="1.3"/><path d="M2 15c.4-2.7 1.8-4 4.5-4s4 1.3 4.5 4" stroke="#E8A73D" stroke-width="1.3" stroke-linecap="round"/><path d="M10.5 15c.3-2 1.5-3.2 3-3.2s2.7.9 3 2.9" stroke="#E8A73D" stroke-width="1.3" stroke-linecap="round"/></svg></div>
          <div class="wwa-num">3</div>
          <div class="wwa-label">User Roles</div>
          <p class="wwa-desc">Rice Farmers, MAO Staff with Technician, and IT Personnel</p>
        </div>
        <div class="wwa-stat">
          <div class="wwa-icon"><svg width="18" height="18" viewBox="0 0 18 18" fill="none"><rect x="3" y="10" width="2.6" height="5" fill="#E8A73D"/><rect x="7.7" y="6" width="2.6" height="9" fill="#E8A73D" opacity="0.85"/><rect x="12.4" y="3" width="2.6" height="12" fill="#E8A73D" opacity="0.6"/></svg></div>
          <div class="wwa-num">24/7</div>
          <div class="wwa-label">Real-Time Monitoring</div>
          <p class="wwa-desc">Continuous weather and climate updates</p>
        </div>
        <div class="wwa-stat">
          <div class="wwa-icon"><svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 2l6 2v4c0 4-2.6 6.7-6 7.5-3.4-.8-6-3.5-6-7.5V4l6-2z" stroke="#E8A73D" stroke-width="1.4" stroke-linejoin="round"/></svg></div>
          <div class="wwa-num">PAGASA</div>
          <div class="wwa-label">Verified Data Source</div>
          <p class="wwa-desc">Trusted climate data from PAGASA</p>
        </div>
        <div class="wwa-stat">
          <div class="wwa-icon"><svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 2a5 5 0 0 1 5 5c0 3.5-5 9-5 9s-5-5.5-5-9a5 5 0 0 1 5-5z" stroke="#E8A73D" stroke-width="1.4"/><circle cx="9" cy="7" r="1.6" stroke="#E8A73D" stroke-width="1.2"/></svg></div>
          <div class="wwa-num">1 Goal</div>
          <div class="wwa-label">Stronger Farming Community</div>
          <p class="wwa-desc">Better decisions for better harvests</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FEATURES -->
<section class="features-section" id="features">
  <div class="container">
    <div class="section-header">
      <span class="eyebrow">Features</span>
      <h2>Six tools, one <em>growing</em> cycle</h2>
      <p>Each module helps you plan, monitor, and decide with confidence &mdash; from planting to harvest.</p>
    </div>

    <div class="features-grid">
      <div class="feature-card">
        <div class="fc-icon-wrap">
          <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
            <path d="M2 13C3.5 9 6.5 7 11 7s7.5 2 9 6" stroke="#E8A73D" stroke-width="1.8" stroke-linecap="round"/>
            <path d="M5 17c1-2.5 3-4 6-4s5 1.5 6 4" stroke="#C6872A" stroke-width="1.4" stroke-linecap="round"/>
            <circle cx="11" cy="4.5" r="2" fill="#5F8F78"/>
          </svg>
        </div>
        <h3>Weather Analysis</h3>
        <p>Access real-time and historical climate data including rainfall, temperature, humidity, wind, and more from PAGASA.</p>
        <span class="fc-badge">Climate Records</span>
      </div>
      <div class="feature-card">
        <div class="fc-icon-wrap">
          <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
            <rect x="2" y="14" width="4" height="6" rx="1" fill="#5F8F78"/>
            <rect x="9" y="9" width="4" height="11" rx="1" fill="#5F8F78" opacity="0.7"/>
            <rect x="16" y="5" width="4" height="15" rx="1" fill="#5F8F78" opacity="0.45"/>
            <path d="M4 10l7-5 7-3" stroke="#C6872A" stroke-width="1.4" stroke-linecap="round"/>
          </svg>
        </div>
        <h3>Rice Production</h3>
        <p>View and analyze local rice production records and forecasts to support planning and food security.</p>
        <span class="fc-badge">Records</span>
      </div>
      <div class="feature-card">
        <div class="fc-icon-wrap">
          <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
            <circle cx="11" cy="11" r="8" stroke="#E8A73D" stroke-width="1.8"/>
            <path d="M11 6v5l3.5 2" stroke="#C6872A" stroke-width="1.6" stroke-linecap="round"/>
          </svg>
        </div>
        <h3>Planting Advisory</h3>
        <p>Get data-driven planting recommendations based on season, rainfall outlook, and soil conditions.</p>
        <span class="fc-badge">Advisory</span>
      </div>
      <div class="feature-card">
        <div class="fc-icon-wrap">
          <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
            <path d="M2 11C5 5,8 3,11 3C14 3,17 5,20 11C17 17,14 19,11 19C8 19,5 17,2 11Z" stroke="#E8A73D" stroke-width="1.8"/>
            <circle cx="11" cy="11" r="3" fill="#5F8F78"/>
          </svg>
        </div>
        <h3>Climate Monitoring</h3>
        <p>Monitor long-term climate trends and detect anomalies that affect farming and productivity.</p>
        <span class="fc-badge">Monitoring</span>
      </div>
      <div class="feature-card">
        <div class="fc-icon-wrap">
          <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
            <path d="M11 2c-4 5-7 8.5-7 12.5A7 7 0 1 0 18 14.5C18 10.5 15 7 11 2z" stroke="#E8A73D" stroke-width="1.8"/>
          </svg>
        </div>
        <h3>Heat Map</h3>
        <p>Visualize risk levels of flood, drought, typhoon, and heat across barangays using interactive maps.</p>
        <span class="fc-badge">Risk Records</span>
      </div>
      <div class="feature-card">
        <div class="fc-icon-wrap">
          <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
            <path d="M4 3h10l4 4v12H4V3z" stroke="#E8A73D" stroke-width="1.8"/>
            <path d="M14 3v4h4" stroke="#E8A73D" stroke-width="1.8"/>
            <path d="M7 10h8M7 13h6M7 16h7" stroke="#C6872A" stroke-width="1.4" stroke-linecap="round"/>
          </svg>
        </div>
        <h3>Reports &amp; Analytics</h3>
        <p>Generate and download seasonal reports, yield forecasts, and analytics for smarter decision-making.</p>
        <span class="fc-badge">Analytics</span>
      </div>
    </div>

    <div class="how-block">
      <div class="section-header">
        <span class="eyebrow">How It Works</span>
        <h2>From records to<br>harvest, in <em>four steps</em></h2>
      </div>
      <div class="how-steps">
        <div class="how-step">
          <div class="how-step-top">
            <div class="how-step-icon">
              <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M4.5 13.5A3 3 0 0 1 4 7.6a4 4 0 0 1 7.7-1.2A2.7 2.7 0 0 1 13.5 11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 15V8.5M6.5 11l2.5-2.5L11.5 11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <span class="how-step-num">01</span>
          </div>
          <h3>Log climate data</h3>
          <p>We collect real-time and historical climate data from trusted sources like PAGASA.</p>
        </div>
        <div class="how-step">
          <div class="how-step-top">
            <div class="how-step-icon">
              <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 2c2.7 2.7 3.6 4.8 3.6 6.75A3.6 3.6 0 1 1 5.4 8.75C5.4 6.8 6.3 4.7 9 2z" stroke="currentColor" stroke-width="1.5"/></svg>
            </div>
            <span class="how-step-num">02</span>
          </div>
          <h3>Get advisories</h3>
          <p>Our system analyzes the data to provide planting and fertilizer recommendations.</p>
        </div>
        <div class="how-step">
          <div class="how-step-top">
            <div class="how-step-icon">
              <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="7.5" cy="7.5" r="5" stroke="currentColor" stroke-width="1.5"/><path d="M11.3 11.3 15 15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            </div>
            <span class="how-step-num">03</span>
          </div>
          <h3>Monitor risk</h3>
          <p>We track weather risks and climate patterns that may impact your crops.</p>
        </div>
        <div class="how-step">
          <div class="how-step-top">
            <div class="how-step-icon">
              <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M3.5 2.5h8l3 3v10h-11v-13z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M6 9h6M6 12h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
            </div>
            <span class="how-step-num">04</span>
          </div>
          <h3>Report outcomes</h3>
          <p>Generate reports and yield forecasts to guide better farming decisions.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- USER ROLES -->
<section class="roles-section" id="roles">
  <div class="roles-leaf-deco" aria-hidden="true"></div>
  <div class="container">
    <div class="section-header">
      <span class="eyebrow">User Roles</span>
      <h2>Who Uses <em>iClimate?</em></h2>
      <p>iClimate supports three role-based workspaces for Rice Farmers, MAO Personnel, and IT Experts, each designed to support climate-informed agricultural planning and decision-making.</p>
    </div>
    <div class="roles-grid">

      <!-- Rice Farmer -->
      <div class="role-card">
        <div class="role-icon-wrap--img">
          <img src="{{ asset('images/rice-farmer-avatar.png') }}" alt="Rice Farmer">
        </div>
        <div class="role-workspace-label">Workspace 01</div>
        <h3>Rice Farmer</h3>
        <p>View climate conditions, receive planting advisories, check weather risk alerts, and access seasonal information through a simple farmer-friendly dashboard.</p>
        <div class="role-divider"></div>
        <div class="role-features-label">Key Features</div>
        <ul class="role-features">
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Climate Monitoring
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Planting Guidance
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Weather Risk Alerts
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Notifications
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Seasonal Reports
          </li>
        </ul>
        <a href="{{ route('login') }}" class="role-cta">
          View Dashboard
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><rect x="1" y="1" width="5" height="5" rx="1" fill="currentColor"/><rect x="8" y="1" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/><rect x="1" y="8" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/><rect x="8" y="8" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/></svg>
        </a>
      </div>

      <!-- MAO Personnel -->
      <div class="role-card role-card--highlight">
        <div class="role-icon-wrap--img">
          <img src="{{ asset('images/da.png') }}" alt="MAO Personnel">
        </div>
        <div class="role-workspace-label">Workspace 02</div>
        <h3>MAO Personnel</h3>
        <p>Manage agricultural records, monitor rice production trends, analyze climate-yield relationships, and generate reports to support farmers and municipal planning.</p>
        <div class="role-divider"></div>
        <div class="role-features-label">Key Features</div>
        <ul class="role-features">
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Production Monitoring
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Climate Analytics
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Report Generation
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Record Validation
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Heat Map Monitoring
          </li>
        </ul>
        <a href="{{ route('login') }}" class="role-cta">
          View Dashboard
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><rect x="1" y="1" width="5" height="5" rx="1" fill="currentColor"/><rect x="8" y="1" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/><rect x="1" y="8" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/><rect x="8" y="8" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/></svg>
        </a>
      </div>

      <!-- IT Expert -->
      <div class="role-card">
        <div class="role-icon-wrap--img">
          <img src="{{ asset('images/it-personnel.png') }}" alt="IT Expert">
        </div>
        <div class="role-workspace-label">Workspace 03</div>
        <h3>IT Expert</h3>
        <p>Manage user accounts, monitor system activity, review logs, maintain records, and help ensure that iClimate remains secure, organized, and reliable.</p>
        <div class="role-divider"></div>
        <div class="role-features-label">Key Features</div>
        <ul class="role-features">
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            User Management
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            System Logs
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Record Maintenance
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Access Control
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Security Monitoring
          </li>
        </ul>
        <a href="{{ route('login') }}" class="role-cta">
          View Dashboard
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><rect x="1" y="1" width="5" height="5" rx="1" fill="currentColor"/><rect x="8" y="1" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/><rect x="1" y="8" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/><rect x="8" y="8" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/></svg>
        </a>
      </div>

    </div>

    <div class="roles-connect">
      <div class="connect-bar">
        <div class="connect-intro">
          <div class="connect-icon connect-icon-lg">
            <svg width="24" height="24" viewBox="0 0 22 22" fill="none"><path d="M11 20c-5-1-8-5-8-11 6 0 10 3 11 8 1-5 5-8 11-8 0 6-3 10-8 11" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
          <h3>One climate picture, three vantage points</h3>
        </div>
        <div class="connect-divider"></div>
        <div class="connect-item">
          <div class="connect-icon">
            <svg width="18" height="18" viewBox="0 0 22 22" fill="none"><path d="M11 20c-5-1-8-5-8-11 6 0 10 3 11 8 1-5 5-8 11-8 0 6-3 10-8 11" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
          <div>
            <h4>Rice Farmer</h4>
            <p>Follows planting advisories and weather alerts in the field.</p>
          </div>
        </div>
        <div class="connect-divider"></div>
        <div class="connect-item">
          <div class="connect-icon">
            <svg width="18" height="18" viewBox="0 0 22 22" fill="none"><circle cx="7.5" cy="6" r="3" fill="currentColor"/><path d="M2.5 17c.5-4.5 2.7-7 5-7s4.5 2.5 5 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="15.5" cy="7.5" r="2.7" fill="currentColor" opacity="0.7"/><path d="M11.5 18c.4-3.5 2.3-5.5 4-5.5s3.6 2 4 5.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity="0.7"/></svg>
          </div>
          <div>
            <h4>MAO Personnel</h4>
            <p>Validates records and turns data into reports for planning.</p>
          </div>
        </div>
        <div class="connect-divider"></div>
        <div class="connect-item">
          <div class="connect-icon">
            <svg width="18" height="18" viewBox="0 0 22 22" fill="none"><path d="M6 5l-4 6 4 6M16 5l4 6-4 6M13 3L9 19" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
          <div>
            <h4>IT Expert</h4>
            <p>Keeps accounts, access, and system records secure for everyone.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CONTACT -->
<section class="contact-section" id="contact">
  <div class="container">
    <div class="contact-grid">
      <div class="contact-info">
        <div class="hero-location">
          <svg width="10" height="12" viewBox="0 0 10 12" fill="none">
            <path d="M5 0C2.24 0 0 2.24 0 5c0 3.75 5 7 5 7s5-3.25 5-7c0-2.76-2.24-5-5-5zm0 6.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3z" fill="currentColor"/>
          </svg>
          Lian, Batangas &mdash; Philippines
        </div>
        <h2>Have questions about <em>iClimate?</em></h2>
        <p class="about-tagline"><span class="tagline-flourish"></span>We&rsquo;re here to help.<span class="tagline-flourish"></span></p>
        <p>Reach out to the team at the Municipal Agricultural Office of Lian, Batangas. We support farmers, MAO personnel, and IT experts using the platform.</p>
        <div class="contact-details">
          <div class="cd-item">
            <div class="cd-icon-wrap">
              <svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M9 16s5.5-5.1 5.5-9A5.5 5.5 0 0 0 3.5 7c0 3.9 5.5 9 5.5 9Z" stroke="#C6872A" stroke-width="1.4" stroke-linejoin="round"/><circle cx="9" cy="7" r="2" stroke="#C6872A" stroke-width="1.4"/></svg>
            </div>
            <div class="cd-text">
              <strong>Address</strong>
              <span>Municipal Agricultural Office, Lian, Batangas, Philippines</span>
            </div>
          </div>
          <div class="cd-item">
            <div class="cd-icon-wrap">
              <svg width="16" height="16" viewBox="0 0 18 18" fill="none"><rect x="2" y="4" width="14" height="10" rx="1.5" stroke="#C6872A" stroke-width="1.4"/><path d="M2.5 5l6.5 5 6.5-5" stroke="#C6872A" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <div class="cd-text">
              <strong>Email</strong>
              <span>mao@lianbatangas.gov.ph</span>
            </div>
          </div>
          <div class="cd-item">
            <div class="cd-icon-wrap">
              <svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M3.5 2.5h3l1.5 4-2 1.3a9 9 0 0 0 4.2 4.2l1.3-2 4 1.5v3a1.5 1.5 0 0 1-1.6 1.5A13.5 13.5 0 0 1 2 4.1 1.5 1.5 0 0 1 3.5 2.5Z" stroke="#C6872A" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <div class="cd-text">
              <strong>Phone</strong>
              <span>+63 917 123 4567</span>
            </div>
          </div>
          <div class="cd-item">
            <div class="cd-icon-wrap">
              <svg width="16" height="16" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="6.5" stroke="#C6872A" stroke-width="1.4"/><path d="M9 5.5V9l3 1.5" stroke="#C6872A" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <div class="cd-text">
              <strong>Office Hours</strong>
              <div class="cd-hours">
                <div class="cd-hours-row"><span>Monday to Friday</span><span>8:00 AM to 5:00 PM</span></div>
                <div class="cd-hours-row"><span>Saturday</span><span>By appointment</span></div>
                <div class="cd-hours-row"><span>Sunday</span><span>Closed</span></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="contact-form-card">
        <div class="cfc-header">
          <div class="cfc-icon">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><rect x="2" y="4" width="14" height="10" rx="1.5" stroke="#C6872A" stroke-width="1.4"/><path d="M2.5 5l6.5 5 6.5-5" stroke="#C6872A" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
          <div>
            <h3>Send us a message</h3>
            <p class="contact-form-sub">We typically respond within one business day.</p>
          </div>
        </div>
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" class="form-input" placeholder="Juan Dela Cruz"/>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Email Address</label>
            <input type="email" class="form-input" placeholder="juan@gmail.com"/>
          </div>
          <div class="form-group">
            <label>Role</label>
            <select class="form-input">
              <option value="" selected disabled>Select your role</option>
              <option>Rice Farmer</option>
              <option>MAO Personnel</option>
              <option>IT Expert</option>
              <option>Other</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Message</label>
          <textarea class="form-input" rows="4" placeholder="How can we help you?"></textarea>
        </div>
        <button type="button" class="btn btn-dark btn-full">
          Send Message
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7h10M7 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
      </div>

      <div class="contact-methods">
        <div class="contact-method">
          <div class="contact-method-icon">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M3.5 2.5h3l1.5 4-2 1.3a9 9 0 0 0 4.2 4.2l1.3-2 4 1.5v3a1.5 1.5 0 0 1-1.6 1.5A13.5 13.5 0 0 1 2 4.1 1.5 1.5 0 0 1 3.5 2.5Z" stroke="#C6872A" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
          <h3>Call Us</h3>
          <p>Speak with the MAO front desk about records, advisories, or general inquiries.</p>
          <a href="tel:+639171234567" class="contact-method-link">
            Call During Office Hours
            <svg width="12" height="12" viewBox="0 0 14 14" fill="none"><path d="M2 7h10M7 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </a>
        </div>
        <div class="contact-method">
          <div class="contact-method-icon">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><rect x="2" y="4" width="14" height="10" rx="1.5" stroke="#C6872A" stroke-width="1.4"/><path d="M2.5 5l6.5 5 6.5-5" stroke="#C6872A" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
          <h3>Email Us</h3>
          <p>Email the team directly for account setup, data corrections, or partnership questions.</p>
          <a href="mailto:mao@lianbatangas.gov.ph" class="contact-method-link">
            Send An Email
            <svg width="12" height="12" viewBox="0 0 14 14" fill="none"><path d="M2 7h10M7 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </a>
        </div>
        <div class="contact-method">
          <div class="contact-method-icon">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 16s5.5-5.1 5.5-9A5.5 5.5 0 0 0 3.5 7c0 3.9 5.5 9 5.5 9Z" stroke="#C6872A" stroke-width="1.4" stroke-linejoin="round"/><circle cx="9" cy="7" r="2" stroke="#C6872A" stroke-width="1.4"/></svg>
          </div>
          <h3>Visit Us</h3>
          <p>Visit the Municipal Agricultural Office to consult on-site during standard business hours.</p>
          <a href="#contact" class="contact-method-link">
            Get Directions
            <svg width="12" height="12" viewBox="0 0 14 14" fill="none"><path d="M2 7h10M7 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer class="footer">
  <div class="container">
    <div class="footer-grid">
      <!-- Column 1: Brand -->
      <div class="footer-brand">
        <a href="{{ url('/') }}" class="nav-logo footer-logo">
          <img src="{{ asset('images/iclimate-logo.png') }}" alt="iClimate" class="brand-logo-img">
        </a>
        <p class="footer-tagline">A climate-informed agricultural decision-support platform integrating PAGASA climate data and local rice production records to support farmers, MAO personnel, and IT experts in strategic forecasting, monitoring, and serving Lian, Batangas.</p>
      </div>

      <!-- Column 2: Platform -->
      <div class="footer-links">
        <h4>Platform</h4>
        <ul>
          <li><a href="{{ route('login') }}">Dashboard</a></li>
          <li><a href="#features">Climate Records</a></li>
          <li><a href="#features">Rice Production Records</a></li>
          <li><a href="#features">Reports</a></li>
        </ul>
      </div>

      <!-- Columns 3 & 4: Company + Access side-by-side -->
      <div class="footer-col-pair">
        <div class="footer-links">
          <h4>Company</h4>
          <ul>
            <li><a href="#about">About iClimate</a></li>
            <li><a href="#features">Features</a></li>
            <li><a href="#contact">Contact Us</a></li>
          </ul>
        </div>

        <div class="footer-links">
          <h4>Access</h4>
          <ul>
            <li><a href="{{ route('login') }}">Login</a></li>
            <li><a href="{{ route('register') }}">Create Account</a></li>
            <li><a href="{{ route('login') }}">Rice Farmer Dashboard</a></li>
            <li><a href="{{ route('login') }}">MAO Personnel Dashboard</a></li>
            <li><a href="{{ route('login') }}">IT Personnel Dashboard</a></li>
          </ul>
        </div>
      </div>

      <!-- Column 5: Follow Us -->
      <div class="footer-social">
        <h4>Follow Us</h4>
        <div class="footer-social-icons">
          <a href="#" class="footer-social-icon" aria-label="Facebook">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 21v-7.5h2.5l.4-3H13.5V8.5c0-.9.2-1.5 1.5-1.5h1.6V4.3C16.3 4.2 15.3 4 14.1 4c-2.4 0-4.1 1.5-4.1 4.2V10.5H7.5v3H10V21h3.5z"/></svg>
          </a>
          <a href="#" class="footer-social-icon" aria-label="Twitter">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M22 5.9c-.7.3-1.5.6-2.3.7.8-.5 1.5-1.3 1.8-2.3-.8.5-1.7.8-2.6 1-.7-.8-1.8-1.3-3-1.3-2.3 0-4.1 1.9-4.1 4.1 0 .3 0 .6.1.9-3.4-.2-6.4-1.8-8.4-4.3-.4.6-.6 1.3-.6 2.1 0 1.4.7 2.7 1.8 3.4-.7 0-1.3-.2-1.9-.5v.1c0 2 1.4 3.7 3.3 4-.3.1-.7.2-1.1.2-.3 0-.5 0-.8-.1.5 1.7 2.1 2.9 3.9 2.9-1.4 1.1-3.2 1.8-5.2 1.8-.3 0-.7 0-1-.1 1.8 1.2 4 1.9 6.3 1.9 7.5 0 11.7-6.3 11.7-11.7v-.5c.8-.6 1.5-1.3 2.1-2.1z"/></svg>
          </a>
          <a href="mailto:mao@lianbatangas.gov.ph" class="footer-social-icon" aria-label="Email">
            <svg width="16" height="16" viewBox="0 0 18 18" fill="none"><rect x="2" y="4" width="14" height="10" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M2.5 5l6.5 5 6.5-5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </a>
        </div>
      </div>
    </div>

    <div class="footer-bottom">
      <p>&copy; 2026 iClimate Research Group &mdash; Batangas State University ARASOF-Nasugbu</p>
      <span class="footer-badge">Lian &middot; Batangas &middot; PH</span>
    </div>
  </div>
</footer>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const navLinks = document.getElementById('navLinks');
    const hamburger = document.getElementById('hamburger');
    const links = document.querySelectorAll('.nav-link');

    hamburger?.addEventListener('click', () => navLinks?.classList.toggle('open'));
    links.forEach(link => link.addEventListener('click', () => navLinks?.classList.remove('open')));

    const sections = document.querySelectorAll('section[id]');
    const spyObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        const id = entry.target.getAttribute('id');
        links.forEach(link => {
          link.classList.toggle('active', link.getAttribute('href') === '#' + id);
        });
      });
    }, { rootMargin: '-40% 0px -55% 0px', threshold: 0 });
    sections.forEach(section => spyObserver.observe(section));
  });

</script>
</body>
</html>
