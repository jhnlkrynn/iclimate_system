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
      --green-950: #0D1F18;
      --green-900: #122B20;
      --green-800: #1A3A2A;
      --green-700: #2D6A4F;
      --green-500: #52B788;
      --green-400: #74C69D;
      --green-200: #B7E4C7;
      --green-100: #D8F3DC;
      --green-50:  #F0F7F4;
      --sand:      #F5F0E8;
      --sand-dark: #E8E0D0;
      --gold:      #E8A73D;
      --gold-dark: #C6872A;
      --gold-light:#FBEBCF;
      --ink:       #0D1F18;
      --ink-mid:   #3D5A48;
      --ink-light: #6B8F71;
      --white:     #FFFFFF;
      --radius-sm: 4px;
      --radius-md: 10px;
      --radius-lg: 18px;
      --radius-xl: 32px;
      --radius-blob: 62% 38% 41% 59% / 48% 42% 58% 52%;
      --radius-pill: 100px;
      --shadow-sm: 0 1px 4px rgba(13,31,24,0.08);
      --shadow-md: 0 4px 20px rgba(13,31,24,0.12);
      --shadow-lg: 0 16px 56px rgba(13,31,24,0.18);
      --shadow-gold: 0 10px 28px rgba(232,167,61,0.35);
      --nav-h: 108px;
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
      background: var(--white);
      -webkit-font-smoothing: antialiased;
    }
    img, svg { display: block; max-width: 100%; }
    a { color: inherit; text-decoration: none; }
    ul { list-style: none; }
    button { cursor: pointer; border: none; background: none; font-family: inherit; }
    p { color: var(--ink-mid); }

    /* -- TYPE ------------------------------------------ */
    h1, h2, h3, h4 {
      font-family: 'DM Serif Display', Georgia, serif;
      line-height: 1.1;
      font-weight: 400;
      color: var(--ink);
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
      border: 1.5px solid var(--green-800);
      color: var(--green-800);
      background: transparent;
    }
    .btn-outline:hover {
      background: var(--green-800);
      color: var(--white);
    }
    .btn-ghost-light {
      color: rgba(255,255,255,0.75);
      background: transparent;
      padding-left: 0;
    }
    .btn-ghost-light:hover { color: var(--white); }
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
      position: absolute;
      top: 0; left: 0; right: 0;
      z-index: 100;
      height: var(--nav-h);
      background: transparent;
      padding: 14px 0;
    }
    .nav-container {
      max-width: 1120px; margin: 0 auto;
      padding: 10px 16px 10px 24px;
      height: 100%;
      display: flex; align-items: center; gap: 40px;
      background: var(--green-950);
      border-radius: var(--radius-pill);
      box-shadow: 0 8px 32px rgba(13,31,24,0.28);
      transition: box-shadow 0.3s var(--ease);
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
    .logo-text em { font-style: italic; color: var(--green-400); }
    .nav-links {
      display: flex; align-items: center; gap: 2px;
      margin-left: auto;
    }
    .nav-link {
      padding: 6px 14px;
      border-radius: var(--radius-sm);
      font-size: 0.875rem;
      font-weight: 500;
      color: rgba(255,255,255,0.6);
      transition: color 0.2s, background 0.2s;
    }
    .nav-link:hover, .nav-link.active { color: var(--white); }
    .nav-actions { display: flex; align-items: center; gap: 10px; }
    .nav-login {
      padding: 9px 20px;
      border: 1px solid rgba(255,255,255,0.25);
      border-radius: var(--radius-pill);
      font-size: 0.875rem;
      font-weight: 500;
      color: rgba(255,255,255,0.8);
      transition: all 0.2s;
    }
    .nav-login:hover {
      border-color: rgba(255,255,255,0.6);
      color: var(--white);
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
      background: var(--white); border-radius: 2px;
      transition: all 0.2s var(--ease);
    }

    /* ===============================================
       HERO
    =============================================== */
    .hero {
      position: relative;
      background: var(--green-950);
      min-height: 100vh;
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
      background: radial-gradient(ellipse at center, rgba(82,183,136,0.10) 0%, transparent 65%);
      pointer-events: none;
    }
    .hero-inner {
      flex: 1;
      max-width: 1120px;
      width: 100%;
      margin: 0 auto;
      padding: 0 24px;
      padding-top: calc(var(--nav-h) + 56px);
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
      color: var(--green-400);
      margin-bottom: 24px;
    }
    .hero-location svg { flex-shrink: 0; }
    .hero-title {
      color: var(--white);
      margin-bottom: 8px;
    }
    .hero-title-italic {
      font-style: italic;
      color: var(--green-400);
    }
    .hero-subtitle {
      font-family: 'DM Serif Display', serif;
      font-size: clamp(1rem, 2vw, 1.25rem);
      font-weight: 400;
      color: rgba(255,255,255,0.5);
      margin-bottom: 24px;
      letter-spacing: -0.01em;
    }
    .hero-desc {
      font-size: 0.975rem;
      color: rgba(255,255,255,0.5);
      max-width: 460px;
      line-height: 1.75;
      margin-bottom: 40px;
    }
    .hero-actions {
      display: flex; align-items: center; gap: 20px;
      flex-wrap: wrap; margin-bottom: 0;
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
      background-color: rgba(255,255,255,0.02);
      background-image:
        linear-gradient(rgba(255,255,255,0.05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.05) 1px, transparent 1px);
      background-size: 28px 28px;
      border: 1px solid rgba(255,255,255,0.08);
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
      0%   { box-shadow: 0 0 0 5px rgba(116,198,157,0.22), 0 0 18px 4px rgba(116,198,157,0.4), 0 0 0 0 rgba(116,198,157,0.5); }
      70%  { box-shadow: 0 0 0 5px rgba(116,198,157,0.22), 0 0 18px 4px rgba(116,198,157,0.4), 0 0 0 10px rgba(116,198,157,0); }
      100% { box-shadow: 0 0 0 5px rgba(116,198,157,0.22), 0 0 18px 4px rgba(116,198,157,0.4), 0 0 0 0 rgba(116,198,157,0); }
    }
    .hero-map-card {
      position: absolute;
      top: 30%; left: 50%;
      transform: translateX(-50%);
      width: 270px;
      background: rgba(13,31,24,0.85);
      border: 1px solid rgba(82,183,136,0.22);
      border-radius: var(--radius-md);
      padding: 14px 20px;
      backdrop-filter: blur(12px);
      box-shadow: var(--shadow-lg);
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

    /* Trust / stats band */
    .trust-band {
      position: relative;
      z-index: 2;
      background: var(--green-900);
      padding-top: 40px;
    }
    .trust-grid {
      display: flex;
      justify-content: space-between;
      gap: 24px;
      flex-wrap: wrap;
      margin-bottom: 40px;
    }
    .trust-item {
      display: flex; align-items: center; gap: 16px;
      flex: 1;
      min-width: 180px;
    }
    .trust-icon {
      width: 52px; height: 52px;
      flex-shrink: 0;
      border-radius: 50%;
      background: rgba(232,167,61,0.12);
      border: 1px solid rgba(232,167,61,0.3);
      display: flex; align-items: center; justify-content: center;
    }
    .trust-num {
      font-family: 'DM Serif Display', serif;
      font-size: 1.6rem;
      color: var(--white);
      line-height: 1;
      letter-spacing: -0.02em;
    }
    .trust-label {
      font-family: 'DM Mono', monospace;
      font-size: 0.68rem;
      color: rgba(255,255,255,0.45);
      text-transform: uppercase;
      letter-spacing: 0.08em;
      margin-top: 5px;
    }
    @media (max-width: 768px) {
      .trust-grid { flex-direction: column; }
    }

    /* Hero wave */
    .hero-wave {
      position: relative; z-index: 1;
      line-height: 0;
    }
    .hero-wave svg { width: 100%; }

    /* ===============================================
       ABOUT
    =============================================== */
    .about-section {
      background: var(--white);
      padding: 120px 0;
    }
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
    .about-text .section-title { margin-bottom: 4px; }
    .about-text .section-title em { font-style: italic; color: var(--green-700); }
    .about-text p { font-size: 0.975rem; line-height: 1.8; }
    .about-tagline {
      font-family: 'DM Mono', monospace;
      font-size: 0.78rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--green-700);
    }
    .about-photo {
      position: relative;
      border-radius: var(--radius-lg);
      overflow: hidden;
      min-height: 420px;
      background:
        linear-gradient(160deg, rgba(13,31,24,0.15), rgba(13,31,24,0.55)),
        linear-gradient(135deg, var(--green-700), var(--green-900));
      box-shadow: var(--shadow-lg);
    }
    .about-photo::before {
      content: "";
      position: absolute; inset: 0;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 420'%3E%3Cpath d='M0 300 Q100 250 200 290 T400 270 V420 H0 Z' fill='%2374C69D' fill-opacity='0.18'/%3E%3Cpath d='M0 340 Q120 300 220 330 T400 320 V420 H0 Z' fill='%2352B788' fill-opacity='0.22'/%3E%3C/svg%3E");
      background-size: cover;
      background-position: bottom;
    }
    .mission-card {
      position: absolute;
      right: 20px; bottom: 20px; left: 20px;
      max-width: 340px;
      margin-left: auto;
      background: var(--white);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-lg);
      padding: 22px 24px;
      display: flex; gap: 14px; align-items: flex-start;
    }
    .mission-icon {
      flex-shrink: 0;
      width: 42px; height: 42px;
      border-radius: 50%;
      background: var(--green-100);
      display: flex; align-items: center; justify-content: center;
    }
    .mission-card strong { display: block; font-size: 0.95rem; color: var(--ink); margin-bottom: 4px; }
    .mission-card p { font-size: 0.83rem; line-height: 1.55; color: var(--ink-mid); }

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
      background: var(--green-50);
      border: 1px solid var(--green-100);
      border-radius: var(--radius-lg);
      padding: 22px 18px;
      text-align: center;
      transition: box-shadow 0.2s var(--ease), border-color 0.2s;
    }
    .pillar-card:hover { box-shadow: var(--shadow-sm); border-color: var(--green-200); }
    .pillar-card .pillar-icon {
      width: 48px; height: 48px;
      border-radius: 50%;
      background: var(--green-700);
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 14px;
    }
    .pillar-card strong { display: block; font-size: 0.88rem; color: var(--ink); margin-bottom: 6px; }
    .pillar-card p { font-size: 0.78rem; line-height: 1.5; color: var(--ink-light); }
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
      background: var(--green-900);
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
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 260 200'%3E%3Cpath d='M0 160 Q60 120 130 150 T260 140 V200 H0Z' stroke='%2352B788' stroke-opacity='0.35' fill='none' stroke-width='2'/%3E%3Cpath d='M20 170 h30 l10 -30 10 30 h30' stroke='%2374C69D' stroke-opacity='0.4' fill='none' stroke-width='2'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      opacity: 0.8;
    }
    .who-we-are-text { position: relative; z-index: 1; }
    .who-we-are-text .eyebrow { color: var(--green-400); }
    .who-we-are-text h2 { color: var(--white); margin: 10px 0 14px; }
    .who-we-are-text p { color: rgba(255,255,255,0.65); font-size: 0.92rem; line-height: 1.7; }
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
      color: var(--white);
      letter-spacing: -0.02em;
    }
    .wwa-label {
      font-family: 'DM Mono', monospace;
      font-size: 0.64rem;
      color: rgba(255,255,255,0.5);
      text-transform: uppercase;
      letter-spacing: 0.06em;
      margin-top: 2px;
    }
    .wwa-desc {
      font-size: 0.76rem;
      color: rgba(255,255,255,0.55);
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
      background: var(--sand);
      padding: 120px 0;
    }
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
    }
    .features-top {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 24px;
      margin-bottom: 24px;
    }
    .features-bottom {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 24px;
    }
    .feature-card {
      background: var(--white);
      padding: 32px 28px;
      display: flex; flex-direction: column; gap: 12px;
      border-radius: var(--radius-lg);
      border: 1px solid var(--sand-dark);
      transition: transform 0.25s var(--ease), box-shadow 0.25s var(--ease), border-color 0.25s;
      position: relative;
      min-width: 0;
    }
    .feature-card:hover {
      transform: translateY(-5px);
      box-shadow: var(--shadow-md);
      border-color: var(--green-200);
    }
    .feature-card--dark {
      background: var(--green-950);
      border-color: var(--green-900);
    }
    .feature-card--dark:hover { border-color: var(--green-800); }
    .feature-card.feature-card--dark h3 { color: var(--white); }
    .feature-card.feature-card--dark p { color: rgba(255,255,255,0.55); }
    .feature-card.feature-card--dark .fc-icon-wrap { background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.14); }
    .feature-card.feature-card--dark .fc-badge { background: rgba(82,183,136,0.16); color: var(--green-400); }
    .fc-icon-wrap {
      width: 52px; height: 52px;
      background: var(--green-50);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      margin-bottom: 4px;
      border: 1px solid var(--green-100);
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
    .feature-card p { font-size: 0.85rem; line-height: 1.65; color: var(--ink-light); }
    .fc-badge {
      display: inline-block; margin-top: auto;
      font-family: 'DM Mono', monospace;
      font-size: 0.65rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--green-500);
      background: var(--green-100);
      padding: 3px 10px;
      border-radius: var(--radius-pill);
      width: fit-content;
    }

    /* How it works */
    .how-block { margin-top: 96px; }
    .how-block .section-header { margin-bottom: 56px; }
    .how-steps {
      display: flex;
      justify-content: space-between;
      gap: 20px;
      position: relative;
    }
    .how-steps::before {
      content: '';
      position: absolute;
      top: 20px;
      left: 6%;
      right: 6%;
      height: 0;
      border-top: 2px dotted var(--green-200);
      z-index: 0;
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
    .how-step-num {
      width: 40px; height: 40px;
      border-radius: 50%;
      background: var(--white);
      border: 1.5px solid var(--green-200);
      color: var(--green-700);
      font-family: 'DM Mono', monospace;
      font-weight: 600;
      font-size: 0.85rem;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
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
      color: var(--ink-light);
    }

    /* ===============================================
       DASHBOARD PREVIEW
    =============================================== */
    .preview-section {
      background: var(--white);
      padding: 120px 0;
    }
    .preview-section .section-header { margin: 0 auto 56px; }
    .dashboard-mockup {
      border: 1px solid #E8EDEB;
      border-radius: var(--radius-lg);
      overflow: hidden;
      display: flex;
      height: 440px;
      box-shadow: var(--shadow-lg);
    }
    .dm-sidebar {
      width: 200px;
      flex-shrink: 0;
      background: var(--green-950);
      display: flex; flex-direction: column;
      padding: 20px 0;
    }
    .dms-brand {
      padding: 0 20px 20px;
      margin-bottom: 4px;
      border-bottom: 1px solid rgba(255,255,255,0.06);
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
      color: var(--white);
      letter-spacing: -0.01em;
    }
    .dms-nav { padding: 12px 10px; display: flex; flex-direction: column; gap: 2px; flex: 1; }
    .dms-link {
      display: flex; align-items: center; gap: 10px;
      padding: 8px 12px;
      border-radius: var(--radius-sm);
      font-size: 0.8rem;
      color: rgba(255,255,255,0.45);
      transition: all 0.15s;
      cursor: pointer;
    }
    .dms-link:hover, .dms-link.active {
      color: var(--white);
      background: rgba(255,255,255,0.07);
    }
    .dms-link.active { color: var(--green-400); }
    .dm-main { flex: 1; background: var(--green-50); overflow: hidden; display: flex; flex-direction: column; }
    .dm-topbar {
      height: 52px;
      background: var(--white);
      border-bottom: 1px solid #E8EDEB;
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
      background: var(--green-100);
      color: var(--green-700);
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
      border: 1px solid #E8EDEB;
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
      background: var(--sand);
      padding: 120px 0;
    }
    .contact-methods {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 24px;
      margin-bottom: 64px;
    }
    .contact-method {
      background: var(--white);
      border: 1px solid var(--sand-dark);
      border-radius: var(--radius-lg);
      padding: 28px 26px;
      display: flex; flex-direction: column; gap: 8px;
      transition: transform 0.25s var(--ease), box-shadow 0.25s var(--ease), border-color 0.25s;
    }
    .contact-method:hover {
      transform: translateY(-5px);
      box-shadow: var(--shadow-md);
      border-color: var(--green-200);
    }
    .contact-method-icon {
      width: 44px; height: 44px;
      border-radius: 50%;
      background: var(--green-50);
      border: 1px solid var(--green-100);
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
      color: var(--ink-light);
    }
    .contact-method-link {
      margin-top: 10px;
      padding-top: 10px;
      border-top: 1px solid var(--sand-dark);
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
    .contact-method.contact-method--dark {
      background: var(--green-950);
      border-color: var(--green-900);
    }
    .contact-method.contact-method--dark:hover { border-color: var(--green-800); }
    .contact-method.contact-method--dark h3 { color: var(--white); }
    .contact-method.contact-method--dark p { color: rgba(255,255,255,0.55); }
    .contact-method.contact-method--dark .contact-method-icon { background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.14); }
    .contact-method.contact-method--dark .contact-method-link { color: var(--green-400); border-top-color: rgba(255,255,255,0.1); }

    .contact-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 80px;
      align-items: start;
    }
    .contact-info { display: flex; flex-direction: column; gap: 0; }
    .contact-info h2 { margin-bottom: 14px; }
    .contact-info > p { font-size: 0.975rem; margin-bottom: 36px; }
    .contact-details { display: flex; flex-direction: column; gap: 16px; }
    .cd-item {
      display: flex; align-items: flex-start; gap: 14px;
    }
    .cd-icon-wrap {
      width: 40px; height: 40px;
      border-radius: 50%;
      background: var(--white);
      border: 1px solid var(--sand-dark);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      font-size: 1rem;
    }
    .cd-text strong { font-size: 0.8rem; font-weight: 700; display: block; color: var(--ink); margin-bottom: 2px; }
    .cd-text span { font-size: 0.875rem; color: var(--ink-light); }

    .contact-hours {
      margin-top: 28px;
      padding-top: 24px;
      border-top: 1px solid var(--sand-dark);
    }
    .contact-hours-label {
      font-family: 'DM Mono', monospace;
      font-size: 0.62rem;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: var(--ink-light);
      margin-bottom: 10px;
    }
    .contact-hours-row {
      display: flex;
      justify-content: space-between;
      padding: 9px 0;
      font-size: 0.875rem;
      border-bottom: 1px solid var(--sand-dark);
    }
    .contact-hours-row:last-child { border-bottom: none; }
    .contact-hours-row span:first-child { color: var(--ink-mid); }
    .contact-hours-row span:last-child { color: var(--ink); font-weight: 600; }

    .contact-form-card {
      background: var(--white);
      border-radius: var(--radius-xl);
      padding: 40px;
      border: 1px solid var(--sand-dark);
      box-shadow: var(--shadow-sm);
    }
    .contact-form-card h3 {
      font-family: 'DM Serif Display', serif;
      font-size: 1.4rem;
      font-weight: 400;
      margin-bottom: 6px;
      color: var(--ink);
      letter-spacing: -0.01em;
    }
    .contact-form-sub {
      font-size: 0.85rem;
      color: var(--ink-light);
      margin-bottom: 24px;
    }
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }
    .form-group { margin-bottom: 18px; }
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
      border: 1.5px solid #D8E0DC;
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
    .form-input:focus {
      border-color: var(--green-500);
      box-shadow: 0 0 0 3px rgba(82,183,136,0.12);
    }
    .form-input::placeholder { color: #B0C0B8; }
    .btn-dark { background: var(--ink); color: var(--white); }
    .btn-dark:hover {
      background: var(--green-950);
      transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(13,31,24,0.3);
    }

    /* ===============================================
       FOOTER
    =============================================== */
    .footer {
      background: var(--green-950);
      padding: 72px 0 36px;
    }
    .footer-grid {
      display: grid;
      grid-template-columns: 2fr 1fr 2fr;
      gap: 48px;
      margin-bottom: 56px;
      padding-bottom: 56px;
      border-bottom: 1px solid rgba(255,255,255,0.06);
    }
    .footer-col-pair {
      display: grid;
      grid-template-columns: 1fr 1.5fr;
      column-gap: 32px;
      align-items: start;
    }
    .footer-logo { margin-bottom: 14px; }
    .footer-tagline {
      font-size: 0.875rem;
      color: rgba(255,255,255,0.35);
      line-height: 1.6;
      max-width: 260px;
    }
    .footer-links h4 {
      font-family: 'DM Mono', monospace;
      font-size: 0.65rem;
      font-weight: 500;
      color: rgba(255,255,255,0.3);
      letter-spacing: 0.12em;
      text-transform: uppercase;
      margin-bottom: 18px;
    }
    .footer-links ul { display: flex; flex-direction: column; gap: 12px; }
    .footer-links a {
      font-size: 0.875rem;
      color: rgba(255,255,255,0.45);
      transition: color 0.2s;
    }
    .footer-links a:hover { color: var(--green-400); }
    .footer-bottom {
      display: flex; align-items: center; justify-content: space-between;
    }
    .footer-bottom p { font-size: 0.8rem; color: rgba(255,255,255,0.25); }
    .footer-badge {
      font-family: 'DM Mono', monospace;
      font-size: 0.65rem;
      letter-spacing: 0.08em;
      color: rgba(255,255,255,0.2);
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
      .features-bottom { grid-template-columns: 1fr 1fr; }
      .how-steps { flex-wrap: wrap; }
      .how-steps::before { display: none; }
      .how-step { flex: 1 1 45%; }
      .contact-methods { grid-template-columns: 1fr 1fr; }
      .contact-grid { grid-template-columns: 1fr; gap: 48px; }
      .footer-grid { grid-template-columns: 1fr 1fr; }
      .footer-brand { grid-column: span 2; }
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
        background: var(--green-950);
        padding: 16px 24px;
        gap: 4px;
        border-top: 1px solid rgba(255,255,255,0.06);
        z-index: 99;
      }
      .hamburger { display: flex; }
      .nav-login { display: none; }
      .features-top { grid-template-columns: 1fr; }
      .features-bottom { grid-template-columns: 1fr; }
      .how-step { flex: 1 1 100%; }
      .contact-methods { grid-template-columns: 1fr; }
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
      background: var(--green-50);
      padding: 120px 0;
    }
    .roles-section .section-header {
      text-align: center;
      margin-left: auto;
      margin-right: auto;
      max-width: 620px;
      margin-bottom: 64px;
    }
    .roles-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 24px;
      align-items: stretch;
    }
    .role-card {
      background: var(--white);
      border: 1.5px solid var(--green-100);
      border-radius: var(--radius-xl);
      padding: 40px 32px 32px;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      transition: box-shadow 0.25s var(--ease), transform 0.25s var(--ease), border-color 0.25s;
    }
    .role-card:hover {
      box-shadow: var(--shadow-md);
      transform: translateY(-4px);
      border-color: var(--green-200);
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
    .role-card.role-card--highlight .role-feature-item svg { color: var(--green-400); }
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
      background: var(--green-950);
      border-radius: var(--radius-xl);
      padding: 48px 44px;
      margin-top: 64px;
    }
    .roles-connect .eyebrow { color: var(--green-400); margin-bottom: 12px; }
    .roles-connect .eyebrow::before { background: var(--green-400); }
    .roles-connect h2 { color: var(--white); max-width: 460px; }
    .connect-flow {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
      margin-top: 44px;
    }
    .connect-step {
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      gap: 10px;
    }
    .connect-icon {
      width: 56px; height: 56px;
      border-radius: 50%;
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.16);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .connect-step h3 {
      font-family: 'Inter', sans-serif;
      font-size: 0.9rem;
      font-weight: 700;
      color: var(--white);
      letter-spacing: -0.01em;
    }
    .connect-step p {
      font-size: 0.8rem;
      line-height: 1.55;
      color: rgba(255,255,255,0.5);
      max-width: 200px;
    }
    .connect-arrow {
      color: var(--green-500);
      font-size: 1.1rem;
      padding-top: 16px;
      flex-shrink: 0;
    }
    @media (max-width: 768px) {
      .roles-connect { padding: 36px 24px; }
      .connect-flow { flex-direction: column; align-items: stretch; gap: 24px; }
      .connect-arrow { display: none; }
    }
    /* Standard SVG icon wrap */
    .role-icon-wrap {
      width: 72px; height: 72px;
      background: var(--green-700);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 28px;
      flex-shrink: 0;
    }
    /* Image logo wrap - white bg, larger, no crop */
    .role-icon-wrap--img {
      width: 96px; height: 96px;
      background: transparent;
      border: none;
      border-radius: 0;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 20px;
      flex-shrink: 0;
    }
    .role-icon-wrap--img img {
      width: 96px;
      height: 96px;
      object-fit: contain;
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
      color: var(--ink-light);
      margin-bottom: 24px;
    }
    .role-divider {
      width: 100%;
      height: 1px;
      background: var(--green-100);
      margin-bottom: 20px;
    }
    .role-features-label {
      font-family: 'DM Mono', monospace;
      font-size: 0.62rem;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: var(--ink-light);
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
      color: var(--ink-mid);
    }
    .role-feature-item svg { flex-shrink: 0; color: var(--green-500); }
    .role-cta {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      padding: 11px 24px;
      border-radius: var(--radius-pill);
      border: 1.5px solid var(--green-200);
      font-size: 0.875rem;
      font-weight: 600;
      color: var(--ink-mid);
      transition: all 0.2s var(--ease);
      margin-top: auto;
      width: 100%;
    }
    .role-cta:hover {
      background: var(--gold);
      border-color: var(--gold);
      color: var(--ink);
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
      box-shadow: 0 10px 28px rgba(82,183,136,0.24);
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
      box-shadow: 0 18px 40px rgba(13,31,24,0.14);
    }
    .role-farmer { background: linear-gradient(135deg, #52B788, #2D6A4F); }
    .role-mao { background: linear-gradient(135deg, var(--gold), #2D6A4F); }
    .role-it { background: linear-gradient(135deg, #3D5A48, #0D1F18); }
    .contact-form-card button[type="button"] { cursor: default; }
    .brand-logo-img {
      height: 76px;
      width: auto;
      max-width: 300px;
      object-fit: contain;
      display: block;
    }
    .role-logo-img {
      width: 118px;
      height: 118px;
      object-fit: contain;
      display: block;
    }
    @media (max-width: 640px) {
      .brand-logo-img {
        height: 58px;
        max-width: 230px;
      }
      .role-logo-img {
        width: 104px;
        height: 104px;
      }
    }
  </style>
</head>
<body>

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
        <a href="{{ route('login') }}" class="btn btn-primary btn-lg">Get Started</a>
        <a href="{{ route('register') }}" class="btn-ghost-light btn">
          Create Account
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7h10M7 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
      </div>
    </div>

    <div class="hero-visual fade-up fade-up-3">
      <div class="hero-map-panel">
        <div class="hero-map-dot"></div>
        <div class="hero-map-card">
          <div class="hmc-location">Lian, Batangas</div>
          <div class="hmc-office">Municipal Agricultural Office</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- TRUST / STATS BAND -->
<section class="trust-band">
  <div class="container trust-grid">
    <div class="trust-item">
      <div class="trust-icon">
        <svg width="22" height="22" viewBox="0 0 22 22" fill="none"><path d="M11 2a5 5 0 100 10 5 5 0 000-10zM3 20c0-4.4 3.6-7 8-7s8 2.6 8 7" stroke="#E8A73D" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </div>
      <div>
        <div class="trust-num">3</div>
        <div class="trust-label">User Roles</div>
      </div>
    </div>
    <div class="trust-item">
      <div class="trust-icon">
        <svg width="22" height="22" viewBox="0 0 22 22" fill="none"><path d="M3 17V9M9 17V5M15 17v-7M19 17V3" stroke="#E8A73D" stroke-width="1.6" stroke-linecap="round"/></svg>
      </div>
      <div>
        <div class="trust-num">24/7</div>
        <div class="trust-label">Real-Time Monitoring</div>
      </div>
    </div>
    <div class="trust-item">
      <div class="trust-icon">
        <svg width="22" height="22" viewBox="0 0 22 22" fill="none"><path d="M6.5 15a4 4 0 01-.5-7.97A5.5 5.5 0 0116.9 8.6 3.6 3.6 0 0116 15.5H6.5z" stroke="#E8A73D" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 18.5v1M11.5 18.5v2M15 18.5v1" stroke="#E8A73D" stroke-width="1.6" stroke-linecap="round"/></svg>
      </div>
      <div>
        <div class="trust-num">PAGASA</div>
        <div class="trust-label">Verified Data Source</div>
      </div>
    </div>
  </div>

  <div class="hero-wave">
    <svg viewBox="0 0 1440 72" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
      <path d="M0,36 C240,72 480,0 720,36 C960,72 1200,0 1440,36 L1440,72 L0,72Z" fill="#FFFFFF"/>
    </svg>
  </div>
</section>

<!-- ABOUT -->
<section class="about-section" id="about">
  <div class="container">
    <div class="about-grid">
      <div class="about-text">
        <div>
          <span class="eyebrow">About iClimate</span>
          <h2 class="section-title">About <em>iClimate</em></h2>
          <p class="about-tagline">Understand today. Prepare tomorrow.</p>
        </div>
        <p>iClimate is a web-based decision support system that combines climate data and local rice production records to help rice farmers, MAO staff with technicians, and IT personnel make informed, data-driven decisions for better agricultural outcomes in Lian, Batangas.</p>
      </div>

      <div class="about-visual">
        <div class="about-photo">
          <div class="mission-card">
            <div class="mission-icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 2c3 4 6 7.5 6 10.5A6 6 0 1 1 4 12.5C4 9.5 7 6 10 2z" stroke="#2D6A4F" stroke-width="1.6"/></svg>
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
        <div class="pillar-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M2 12c1.5-3.5 4-5 8-5s6.5 1.5 8 5" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/><circle cx="10" cy="5.5" r="1.6" fill="#fff"/></svg></div>
        <strong>Climate-Informed</strong>
        <p>Integrating PAGASA climate data for real-time weather monitoring and seasonal forecasting.</p>
      </div>
      <div class="pillar-card">
        <div class="pillar-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 2c2.2 3 4 5.3 4 7.5A4 4 0 1 1 6 9.5C6 7.3 7.8 5 10 2z" stroke="#fff" stroke-width="1.5"/></svg></div>
        <strong>Rice-Focused</strong>
        <p>Specialized in rice production analysis, yield prediction, and planting recommendations.</p>
      </div>
      <div class="pillar-card">
        <div class="pillar-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><rect x="3" y="10" width="3" height="7" fill="#fff"/><rect x="8.5" y="6" width="3" height="11" fill="#fff" opacity="0.85"/><rect x="14" y="3" width="3" height="14" fill="#fff" opacity="0.6"/></svg></div>
        <strong>Data-Driven</strong>
        <p>Using historical records and analytics to generate accurate insights and reports.</p>
      </div>
      <div class="pillar-card">
        <div class="pillar-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="7" cy="7" r="2.6" stroke="#fff" stroke-width="1.4"/><circle cx="14" cy="8" r="2.2" stroke="#fff" stroke-width="1.4"/><path d="M2.5 16c0.5-3 2-4.5 4.5-4.5s4 1.5 4.5 4.5" stroke="#fff" stroke-width="1.4" stroke-linecap="round"/><path d="M11.5 16c0.4-2.4 1.7-3.7 3.5-3.7s3 1.1 3.5 3" stroke="#fff" stroke-width="1.4" stroke-linecap="round"/></svg></div>
        <strong>Community-Centered</strong>
        <p>Built for farmers, supported by MAO staff and technicians, and optimized by IT personnel.</p>
      </div>
      <div class="pillar-card">
        <div class="pillar-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 2l7 2.5v5c0 4.5-3 7.5-7 8.5-4-1-7-4-7-8.5v-5L10 2z" stroke="#fff" stroke-width="1.5" stroke-linejoin="round"/></svg></div>
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
      <span class="eyebrow">Key Features</span>
      <h2>Six tools, one growing cycle</h2>
      <p>Each module maps to a real stage of rice farming, from watching the sky to filing the season&rsquo;s final report.</p>
    </div>

    <div class="features-top">
      <div class="feature-card">
        <div class="fc-icon-wrap">
          <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
            <path d="M2 13C3.5 9 6.5 7 11 7s7.5 2 9 6" stroke="#52B788" stroke-width="1.8" stroke-linecap="round"/>
            <path d="M5 17c1-2.5 3-4 6-4s5 1.5 6 4" stroke="#95D5B2" stroke-width="1.4" stroke-linecap="round"/>
            <circle cx="11" cy="4.5" r="2" fill="#52B788"/>
          </svg>
        </div>
        <h3>Weather Analysis</h3>
        <p>Rainfall, temperature, humidity, wind, and seasonal climate records for Lian, Batangas, sourced from PAGASA.</p>
        <span class="fc-badge">Climate Records</span>
      </div>
      <div class="feature-card feature-card--dark">
        <div class="fc-icon-wrap">
          <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
            <rect x="2" y="14" width="4" height="6" rx="1" fill="#52B788"/>
            <rect x="9" y="9" width="4" height="11" rx="1" fill="#52B788" opacity="0.7"/>
            <rect x="16" y="5" width="4" height="15" rx="1" fill="#52B788" opacity="0.45"/>
            <path d="M4 10l7-5 7-3" stroke="#95D5B2" stroke-width="1.4" stroke-linecap="round"/>
          </svg>
        </div>
        <h3>Rice Production</h3>
        <p>Organized production records and report-ready summaries for municipal planning and forecasting.</p>
        <span class="fc-badge">Records</span>
      </div>
    </div>

    <div class="features-bottom">
      <div class="feature-card">
        <div class="fc-icon-wrap">
          <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
            <circle cx="11" cy="11" r="8" stroke="#52B788" stroke-width="1.8"/>
            <path d="M11 6v5l3.5 2" stroke="#95D5B2" stroke-width="1.6" stroke-linecap="round"/>
          </svg>
        </div>
        <h3>Planting Advisory</h3>
        <p>Season-specific dates, seed varieties, and fertilizer schedules.</p>
        <span class="fc-badge">Advisory</span>
      </div>
      <div class="feature-card">
        <div class="fc-icon-wrap">
          <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
            <path d="M2 11C5 5,8 3,11 3C14 3,17 5,20 11C17 17,14 19,11 19C8 19,5 17,2 11Z" stroke="#52B788" stroke-width="1.8"/>
            <circle cx="11" cy="11" r="3" fill="#52B788"/>
          </svg>
        </div>
        <h3>Climate Monitoring</h3>
        <p>Track long-term trends, detect anomalies, and observe pattern shifts.</p>
        <span class="fc-badge">Monitoring</span>
      </div>
      <div class="feature-card">
        <div class="fc-icon-wrap">
          <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
            <rect x="2" y="2" width="18" height="18" rx="3" stroke="#52B788" stroke-width="1.8"/>
            <path d="M5 11h3v7H5zM9.5 7h3v11h-3zM14 9h3v9h-3z" fill="#52B788" opacity="0.5"/>
          </svg>
        </div>
        <h3>Heat Map</h3>
        <p>Barangay risk records using flood, drought, typhoon, and heat categories.</p>
        <span class="fc-badge">Risk Records</span>
      </div>
      <div class="feature-card">
        <div class="fc-icon-wrap">
          <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
            <path d="M4 3h10l4 4v12H4V3z" stroke="#52B788" stroke-width="1.8"/>
            <path d="M14 3v4h4" stroke="#52B788" stroke-width="1.8"/>
            <path d="M7 10h8M7 13h6M7 16h7" stroke="#95D5B2" stroke-width="1.4" stroke-linecap="round"/>
          </svg>
        </div>
        <h3>Reports &amp; Analytics</h3>
        <p>Downloadable seasonal reports and shareable insights for LGU stakeholders.</p>
        <span class="fc-badge">Analytics</span>
      </div>
    </div>

    <div class="how-block">
      <div class="section-header">
        <span class="eyebrow">How It Works</span>
        <h2>From records to harvest, in four steps</h2>
      </div>
      <div class="how-steps">
        <div class="how-step">
          <div class="how-step-num">01</div>
          <h3>Log climate data</h3>
          <p>MAO staff enter or sync PAGASA rainfall, temperature, and wind records.</p>
        </div>
        <div class="how-step">
          <div class="how-step-num">02</div>
          <h3>Get advisories</h3>
          <p>Farmers receive planting windows and fertilizer guidance for the season.</p>
        </div>
        <div class="how-step">
          <div class="how-step-num">03</div>
          <h3>Monitor risk</h3>
          <p>Heat maps flag flood, drought, typhoon, and heat exposure by barangay.</p>
        </div>
        <div class="how-step">
          <div class="how-step-num">04</div>
          <h3>Report outcomes</h3>
          <p>Generate seasonal reports and yield comparisons for LGU planning.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- USER ROLES -->
<section class="roles-section" id="roles">
  <div class="container">
    <div class="section-header">
      <span class="eyebrow">User Roles</span>
      <h2>Who Uses iClimate?</h2>
      <p>iClimate supports three role-based workspaces for Rice Farmers, MAO Staff with Technician, and IT Personnel, each designed to support climate-informed agricultural planning and decision-making.</p>
    </div>
    <div class="roles-grid">

      <!-- Rice Farmers -->
      <div class="role-card">
        <div class="role-icon-wrap role-icon-wrap--img">
          <img src="{{ asset('images/rice-farmer.png') }}" alt="Rice Farmers" class="role-logo-img">
        </div>
        <div class="role-workspace-label">Workspace 01</div>
        <h3>Rice Farmers</h3>
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
        <div class="role-icon-wrap role-icon-wrap--img">
          <img src="{{ asset('images/da.png') }}" alt="Department of Agriculture" class="role-logo-img">
        </div>
        <div class="role-workspace-label">Workspace 02</div>
        <h3>MAO Staff with Technician</h3>
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
        <div class="role-icon-wrap role-icon-wrap--img">
          <img src="{{ asset('images/it-personnel.png') }}" alt="IT Personnel" class="role-logo-img">
        </div>
        <div class="role-workspace-label">Workspace 03</div>
        <h3>IT Personnel</h3>
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
      <span class="eyebrow">How the roles connect</span>
      <h2>One climate picture, three vantage points</h2>
      <div class="connect-flow">
        <div class="connect-step">
          <div class="connect-icon">
            <svg width="22" height="22" viewBox="0 0 22 22" fill="none"><path d="M11 20c-5-1-8-5-8-11 6 0 10 3 11 8 1-5 5-8 11-8 0 6-3 10-8 11" stroke="#52B788" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
          <h3>Rice Farmers</h3>
          <p>Follows planting advisories and weather alerts in the field.</p>
        </div>
        <div class="connect-arrow">&rarr;</div>
        <div class="connect-step">
          <div class="connect-icon">
            <svg width="22" height="22" viewBox="0 0 22 22" fill="none"><path d="M3 11L11 4l8 7" stroke="#52B788" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 9.5V19h12V9.5" stroke="#52B788" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
          <h3>MAO Staff with Technician</h3>
          <p>Validates records and turns data into reports for planning.</p>
        </div>
        <div class="connect-arrow">&rarr;</div>
        <div class="connect-step">
          <div class="connect-icon">
            <svg width="22" height="22" viewBox="0 0 22 22" fill="none"><path d="M11 2l8 3v6c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V5l8-3z" stroke="#52B788" stroke-width="1.6" stroke-linejoin="round"/></svg>
          </div>
          <h3>IT Personnel</h3>
          <p>Keeps accounts, access, and system records secure for everyone.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CONTACT -->
<section class="contact-section" id="contact">
  <div class="container">

    <div class="contact-methods">
      <div class="contact-method">
        <div class="contact-method-icon">
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M3.5 2.5h3l1.5 4-2 1.3a9 9 0 0 0 4.2 4.2l1.3-2 4 1.5v3a1.5 1.5 0 0 1-1.6 1.5A13.5 13.5 0 0 1 2 4.1 1.5 1.5 0 0 1 3.5 2.5Z" stroke="#2D6A4F" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <h3>+63 43 456 7890</h3>
        <p>Speak with the MAO front desk about records, dashboard access, or general inquiries.</p>
        <a href="tel:+63434567890" class="contact-method-link">
          Call During Office Hours
          <svg width="12" height="12" viewBox="0 0 14 14" fill="none"><path d="M2 7h10M7 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
      </div>
      <div class="contact-method contact-method--dark">
        <div class="contact-method-icon">
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><rect x="2" y="4" width="14" height="10" rx="1.5" stroke="#52B788" stroke-width="1.4"/><path d="M2.5 5l6.5 5 6.5-5" stroke="#52B788" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <h3>maolian@iclimate.ph</h3>
        <p>Email the team directly for account setup, data corrections, or partnership questions.</p>
        <a href="mailto:maolian@iclimate.ph" class="contact-method-link">
          Send An Email
          <svg width="12" height="12" viewBox="0 0 14 14" fill="none"><path d="M2 7h10M7 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
      </div>
      <div class="contact-method">
        <div class="contact-method-icon">
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 16s5.5-5.1 5.5-9A5.5 5.5 0 0 0 3.5 7c0 3.9 5.5 9 5.5 9Z" stroke="#2D6A4F" stroke-width="1.4" stroke-linejoin="round"/><circle cx="9" cy="7" r="2" stroke="#2D6A4F" stroke-width="1.4"/></svg>
        </div>
        <h3>Lian, Batangas, PH</h3>
        <p>Visit the Municipal Agricultural Office to consult on-site during standard hours.</p>
        <a href="#contact" class="contact-method-link">
          Get Directions
          <svg width="12" height="12" viewBox="0 0 14 14" fill="none"><path d="M2 7h10M7 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
      </div>
    </div>

    <div class="contact-grid">
      <div class="contact-info">
        <span class="eyebrow">Contact Us</span>
        <h2>Have questions about iClimate?</h2>
        <p>Reach out to the team at the Municipal Agricultural Office of Lian, Batangas. We support rice farmers, MAO staff with technicians, and IT personnel using the platform.</p>
        <div class="contact-details">
          <div class="cd-item">
            <div class="cd-icon-wrap">
              <svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M9 16s5.5-5.1 5.5-9A5.5 5.5 0 0 0 3.5 7c0 3.9 5.5 9 5.5 9Z" stroke="#2D6A4F" stroke-width="1.4" stroke-linejoin="round"/><circle cx="9" cy="7" r="2" stroke="#2D6A4F" stroke-width="1.4"/></svg>
            </div>
            <div class="cd-text">
              <strong>Address</strong>
              <span>Municipal Agricultural Office, Lian, Batangas, Philippines</span>
            </div>
          </div>
          <div class="cd-item">
            <div class="cd-icon-wrap">
              <svg width="16" height="16" viewBox="0 0 18 18" fill="none"><rect x="2" y="4" width="14" height="10" rx="1.5" stroke="#2D6A4F" stroke-width="1.4"/><path d="M2.5 5l6.5 5 6.5-5" stroke="#2D6A4F" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <div class="cd-text">
              <strong>Email</strong>
              <span>maolian@iclimate.ph</span>
            </div>
          </div>
          <div class="cd-item">
            <div class="cd-icon-wrap">
              <svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M3.5 2.5h3l1.5 4-2 1.3a9 9 0 0 0 4.2 4.2l1.3-2 4 1.5v3a1.5 1.5 0 0 1-1.6 1.5A13.5 13.5 0 0 1 2 4.1 1.5 1.5 0 0 1 3.5 2.5Z" stroke="#2D6A4F" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <div class="cd-text">
              <strong>Phone</strong>
              <span>+63 49 123 4567</span>
            </div>
          </div>
        </div>

        <div class="contact-hours">
          <div class="contact-hours-label">Office Hours</div>
          <div class="contact-hours-row">
            <span>Monday to Friday</span>
            <span>8:00 AM to 5:00 PM</span>
          </div>
          <div class="contact-hours-row">
            <span>Saturday</span>
            <span>By appointment</span>
          </div>
          <div class="contact-hours-row">
            <span>Sunday</span>
            <span>Closed</span>
          </div>
        </div>
      </div>

      <div class="contact-form-card">
        <h3>Send a message</h3>
        <p class="contact-form-sub">We typically respond within one business day.</p>
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" class="form-input" placeholder="Juan dela Cruz"/>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Email Address</label>
            <input type="email" class="form-input" placeholder="juan@email.com"/>
          </div>
          <div class="form-group">
            <label>Role</label>
            <select class="form-input">
              <option>Rice Farmers</option>
              <option>MAO Staff with Technician</option>
              <option>IT Personnel</option>
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
        <p class="footer-tagline">A climate-informed agricultural decision-support platform integrating PAGASA climate data and local rice production records to support rice farmers, MAO staff with technicians, and IT personnel through forecasting, monitoring, and analytics.<br>Serving Lian, Batangas.</p>
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
            <li><a href="{{ route('login') }}">Rice Farmers Dashboard</a></li>
            <li><a href="{{ route('login') }}">MAO Staff with Technician Dashboard</a></li>
            <li><a href="{{ route('login') }}">IT Personnel Dashboard</a></li>
          </ul>
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
  });

</script>
</body>
</html>
