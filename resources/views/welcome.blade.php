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
      --ink:       #0D1F18;
      --ink-mid:   #3D5A48;
      --ink-light: #6B8F71;
      --white:     #FFFFFF;
      --radius-sm: 4px;
      --radius-md: 10px;
      --radius-lg: 18px;
      --radius-pill: 100px;
      --shadow-sm: 0 1px 4px rgba(13,31,24,0.08);
      --shadow-md: 0 4px 20px rgba(13,31,24,0.12);
      --shadow-lg: 0 16px 56px rgba(13,31,24,0.18);
      --nav-h: 64px;
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
      border-radius: var(--radius-md);
      font-family: 'Inter', sans-serif;
      font-size: 0.875rem;
      font-weight: 600;
      transition: all 0.2s var(--ease);
      white-space: nowrap;
      letter-spacing: 0.01em;
    }
    .btn-primary {
      background: var(--green-500);
      color: var(--white);
    }
    .btn-primary:hover {
      background: var(--green-700);
      transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(82,183,136,0.4);
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
    .btn-lg { padding: 14px 32px; font-size: 0.95rem; border-radius: var(--radius-lg); }
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
      position: fixed;
      top: 0; left: 0; right: 0;
      z-index: 100;
      height: var(--nav-h);
      background: transparent;
      transition: background 0.3s var(--ease), box-shadow 0.3s var(--ease);
    }
    .navbar.scrolled {
      background: var(--green-950);
      box-shadow: 0 1px 0 rgba(255,255,255,0.06);
    }
    .nav-container {
      max-width: 1120px; margin: 0 auto;
      padding: 0 24px;
      height: 100%;
      display: flex; align-items: center; gap: 40px;
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
    .nav-actions { display: flex; align-items: center; gap: 12px; }
    .nav-login {
      padding: 7px 18px;
      border: 1px solid rgba(255,255,255,0.25);
      border-radius: var(--radius-md);
      font-size: 0.875rem;
      font-weight: 500;
      color: rgba(255,255,255,0.8);
      transition: all 0.2s;
    }
    .nav-login:hover {
      border-color: rgba(255,255,255,0.6);
      color: var(--white);
    }
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
      padding-top: calc(var(--nav-h) + 100px);
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
      flex-wrap: wrap; margin-bottom: 56px;
    }
    .hero-stats {
      display: flex; align-items: stretch; gap: 0;
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: var(--radius-md);
      overflow: hidden;
      width: fit-content;
    }
    .stat {
      padding: 16px 24px;
      display: flex; flex-direction: column; gap: 3px;
      border-right: 1px solid rgba(255,255,255,0.08);
    }
    .stat:last-child { border-right: none; }
    .stat-num {
      font-family: 'DM Mono', monospace;
      font-size: 1.4rem;
      font-weight: 500;
      color: var(--white);
      line-height: 1;
      letter-spacing: -0.02em;
    }
    .stat-label { font-size: 0.72rem; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 0.06em; }

    /* Hero Card */
    .hero-card {
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(82,183,136,0.18);
      border-radius: var(--radius-lg);
      overflow: hidden;
      backdrop-filter: blur(16px);
    }
    .hc-head {
      display: flex; align-items: center; gap: 8px;
      padding: 14px 18px;
      border-bottom: 1px solid rgba(255,255,255,0.06);
    }
    .hc-dot { width: 9px; height: 9px; border-radius: 50%; }
    .hc-dot.r { background: #FF5F56; }
    .hc-dot.y { background: #FFBD2E; }
    .hc-dot.g { background: #27C93F; }
    .hc-title {
      font-family: 'DM Mono', monospace;
      font-size: 0.72rem;
      color: rgba(255,255,255,0.4);
      letter-spacing: 0.08em;
      text-transform: uppercase;
      margin-left: auto;
      margin-right: auto;
      padding-right: 40px;
    }
    .hc-body { padding: 20px 18px; display: flex; flex-direction: column; gap: 18px; }
    
    /* Weather row */
    .hc-weather {
      display: flex; align-items: center; gap: 16px;
      padding-bottom: 16px;
      border-bottom: 1px solid rgba(255,255,255,0.06);
    }
    .hc-temp {
      font-family: 'DM Serif Display', serif;
      font-size: 3rem;
      color: var(--white);
      line-height: 1;
      letter-spacing: -0.02em;
    }
    .hc-temp sup { font-size: 1.2rem; vertical-align: super; }
    .hc-weather-meta { display: flex; flex-direction: column; gap: 4px; }
    .hc-weather-label {
      font-size: 0.875rem; font-weight: 500; color: rgba(255,255,255,0.7);
    }
    .hc-weather-sub {
      font-family: 'DM Mono', monospace;
      font-size: 0.72rem; color: rgba(255,255,255,0.35);
      letter-spacing: 0.05em;
    }
    .hc-sun {
      margin-left: auto;
      width: 44px; height: 44px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(82,183,136,0.35) 0%, transparent 70%);
      display: flex; align-items: center; justify-content: center;
    }
    
    /* Mini chart */
    .hc-chart-wrap { display: flex; flex-direction: column; gap: 8px; }
    .hc-chart-label {
      font-family: 'DM Mono', monospace;
      font-size: 0.65rem; color: rgba(255,255,255,0.3);
      letter-spacing: 0.08em; text-transform: uppercase;
    }
    .hc-chart svg { width: 100%; }
    .hc-chart-days {
      display: flex; justify-content: space-between;
      font-family: 'DM Mono', monospace;
      font-size: 0.62rem; color: rgba(255,255,255,0.25);
      letter-spacing: 0.05em;
    }
    
    /* Status chips */
    .hc-chips { display: flex; gap: 8px; flex-wrap: wrap; }
    .chip {
      padding: 5px 12px;
      border-radius: var(--radius-pill);
      font-family: 'DM Mono', monospace;
      font-size: 0.68rem;
      font-weight: 500;
      letter-spacing: 0.05em;
    }
    .chip-green {
      background: rgba(82,183,136,0.15);
      color: var(--green-400);
      border: 1px solid rgba(82,183,136,0.25);
    }
    .chip-mint {
      background: rgba(183,228,199,0.1);
      color: var(--green-200);
      border: 1px solid rgba(183,228,199,0.2);
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
    .about-img-card {
      background: var(--green-50);
      border-radius: var(--radius-lg);
      overflow: hidden;
      aspect-ratio: 4/3;
      display: flex; align-items: center; justify-content: center;
      border: 1px solid var(--green-100);
    }
    .about-img-card svg { width: 100%; height: 100%; }
    .about-accent-box {
      position: absolute;
      bottom: -24px; right: -24px;
      background: var(--green-500);
      border-radius: var(--radius-md);
      padding: 20px 24px;
      min-width: 160px;
      box-shadow: var(--shadow-lg);
    }
    .aab-num {
      font-family: 'DM Mono', monospace;
      font-size: 1.8rem;
      font-weight: 500;
      color: var(--white);
      line-height: 1;
      letter-spacing: -0.02em;
    }
    .aab-label {
      font-size: 0.75rem;
      color: rgba(255,255,255,0.7);
      margin-top: 4px;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }
    .about-text { display: flex; flex-direction: column; gap: 20px; }
    .about-text .section-title { margin-bottom: 4px; }
    .about-text p { font-size: 0.975rem; line-height: 1.8; }
    .pillars { display: flex; flex-direction: column; gap: 16px; margin-top: 8px; }
    .pillar {
      display: flex; align-items: flex-start; gap: 14px;
      padding: 16px 20px;
      background: var(--green-50);
      border-radius: var(--radius-md);
      border: 1px solid var(--green-100);
    }
    .pillar-icon {
      font-size: 1.3rem;
      flex-shrink: 0;
      margin-top: 1px;
    }
    .pillar-body strong {
      display: block;
      font-size: 0.9rem;
      font-weight: 600;
      color: var(--ink);
      margin-bottom: 3px;
    }
    .pillar-body p {
      font-size: 0.85rem;
      color: var(--ink-light);
      line-height: 1.55;
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
    .features-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 2px;
      border-radius: var(--radius-lg);
      overflow: hidden;
      border: 1px solid var(--sand-dark);
    }
    .feature-card {
      background: var(--white);
      padding: 36px 32px;
      display: flex; flex-direction: column; gap: 12px;
      transition: background 0.2s;
      position: relative;
    }
    .feature-card:hover { background: var(--green-50); }
    .feature-card.fc-wide {
      grid-column: span 2;
    }
    .fc-icon-wrap {
      width: 48px; height: 48px;
      background: var(--green-50);
      border-radius: var(--radius-md);
      display: flex; align-items: center; justify-content: center;
      margin-bottom: 4px;
      border: 1px solid var(--green-100);
    }
    .feature-card h3 {
      font-family: 'Inter', sans-serif;
      font-size: 0.975rem;
      font-weight: 700;
      color: var(--ink);
      line-height: 1.3;
      letter-spacing: -0.01em;
    }
    .feature-card p { font-size: 0.875rem; line-height: 1.7; color: var(--ink-light); }
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
      border-radius: var(--radius-md);
      background: var(--white);
      border: 1px solid var(--sand-dark);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      font-size: 1rem;
    }
    .cd-text strong { font-size: 0.8rem; font-weight: 700; display: block; color: var(--ink); margin-bottom: 2px; }
    .cd-text span { font-size: 0.875rem; color: var(--ink-light); }
    
    .contact-form-card {
      background: var(--white);
      border-radius: var(--radius-lg);
      padding: 40px;
      border: 1px solid var(--sand-dark);
      box-shadow: var(--shadow-sm);
    }
    .contact-form-card h3 {
      font-family: 'DM Serif Display', serif;
      font-size: 1.4rem;
      font-weight: 400;
      margin-bottom: 28px;
      color: var(--ink);
      letter-spacing: -0.01em;
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
    .form-input:focus {
      border-color: var(--green-500);
      box-shadow: 0 0 0 3px rgba(82,183,136,0.12);
    }
    .form-input::placeholder { color: #B0C0B8; }

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
      .hero-card { display: none; }
      .about-grid { grid-template-columns: 1fr; gap: 48px; }
      .about-visual { display: none; }
      .features-grid { grid-template-columns: 1fr 1fr; }
      .feature-card.fc-wide { grid-column: span 2; }
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
      .features-grid { grid-template-columns: 1fr; }
      .feature-card.fc-wide { grid-column: span 1; }
      .hero-stats { overflow-x: auto; }
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
      border-radius: var(--radius-lg);
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
    /* Standard SVG icon wrap */
    .role-icon-wrap {
      width: 72px; height: 72px;
      background: var(--green-700);
      border-radius: var(--radius-md);
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
      border-radius: var(--radius-md);
      border: 1.5px solid var(--green-200);
      font-size: 0.875rem;
      font-weight: 600;
      color: var(--ink-mid);
      transition: all 0.2s var(--ease);
      margin-top: auto;
      width: 100%;
    }
    .role-cta:hover {
      background: var(--green-700);
      border-color: var(--green-700);
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
      background: linear-gradient(135deg, var(--green-500), #168aad);
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
    .role-mao { background: linear-gradient(135deg, #168aad, #2D6A4F); }
    .role-it { background: linear-gradient(135deg, #3D5A48, #0D1F18); }
    .contact-form-card button[type="button"] { cursor: default; }
    .brand-logo-img {
      height: 42px;
      width: auto;
      max-width: 172px;
      object-fit: contain;
      display: block;
    }
    .footer-logo .brand-logo-img { filter: brightness(0) invert(1); }
    .role-logo-img {
      width: 96px;
      height: 96px;
      object-fit: contain;
      display: block;
    }
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar" id="navbar">
  <div class="nav-container">
    <a href="{{ url('/') }}" class="nav-logo">
      <img src="{{ asset('images/iClimate.png') }}" alt="iClimate" class="brand-logo-img">
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
      <p class="hero-desc fade-up fade-up-3">A web-based weather impact analysis and rice yield prediction platform for Lian, Batangas &mdash; built for farmers, MAO personnel, and IT experts.</p>
      <div class="hero-actions fade-up fade-up-3">
        <a href="{{ route('login') }}" class="btn btn-primary btn-lg">Get Started</a>
        <a href="{{ route('register') }}" class="btn-ghost-light btn">
          Create Account
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7h10M7 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
      </div>
      <div class="hero-stats fade-up fade-up-4">
        <div class="stat">
          <span class="stat-num">3</span>
          <span class="stat-label">User Roles</span>
        </div>
        <div class="stat">
          <span class="stat-num">RT</span>
          <span class="stat-label">Climate Records</span>
        </div>
        <div class="stat">
          <span class="stat-num">BRY</span>
          <span class="stat-label">Based Reports</span>
        </div>
      </div>
    </div>

    <div class="hero-card fade-up fade-up-3">
      <div class="hc-head">
        <span class="hc-dot r"></span>
        <span class="hc-dot y"></span>
        <span class="hc-dot g"></span>
        <span class="hc-title">Live Climate Monitor</span>
      </div>
      <div class="hc-body">
        <div class="hc-weather">
          <div>
            <div class="hc-temp">29<sup>&deg;C</sup></div>
          </div>
          <div class="hc-weather-meta">
            <span class="hc-weather-label">Partly Cloudy</span>
            <span class="hc-weather-sub">HUMID 74% &middot; WIND 12 KM/H</span>
          </div>
          <div class="hc-sun">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="12" r="5" fill="#52B788"/>
              <g stroke="#95D5B2" stroke-width="1.5" stroke-linecap="round">
                <line x1="12" y1="2" x2="12" y2="5"/>
                <line x1="12" y1="19" x2="12" y2="22"/>
                <line x1="2" y1="12" x2="5" y2="12"/>
                <line x1="19" y1="12" x2="22" y2="12"/>
                <line x1="4.9" y1="4.9" x2="7.1" y2="7.1"/>
                <line x1="16.9" y1="16.9" x2="19.1" y2="19.1"/>
                <line x1="19.1" y1="4.9" x2="16.9" y2="7.1"/>
                <line x1="7.1" y1="16.9" x2="4.9" y2="19.1"/>
              </g>
            </svg>
          </div>
        </div>
        <div class="hc-chart-wrap">
          <span class="hc-chart-label">7-Day Temperature</span>
          <div class="hc-chart">
            <svg viewBox="0 0 340 60" xmlns="http://www.w3.org/2000/svg">
              <defs>
                <linearGradient id="cg" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#52B788" stop-opacity="0.3"/>
                  <stop offset="100%" stop-color="#52B788" stop-opacity="0"/>
                </linearGradient>
              </defs>
              <path d="M0 42 C30 38,60 28,90 32 C120 36,150 16,190 20 C220 23,270 12,340 18 L340 60 L0 60Z" fill="url(#cg)"/>
              <path d="M0 42 C30 38,60 28,90 32 C120 36,150 16,190 20 C220 23,270 12,340 18" fill="none" stroke="#52B788" stroke-width="1.5"/>
              <circle cx="190" cy="20" r="3.5" fill="#74C69D"/>
              <circle cx="190" cy="20" r="7" fill="rgba(82,183,136,0.2)"/>
            </svg>
          </div>
          <div class="hc-chart-days">
            <span>MON</span><span>TUE</span><span>WED</span><span>THU</span><span>FRI</span><span>SAT</span><span>SUN</span>
          </div>
        </div>
        <div class="hc-chips">
          <span class="chip chip-green">&#10003; Good Planting Window</span>
          <span class="chip chip-mint">&uarr; Production +14%</span>
        </div>
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
      <div class="about-visual">
        <div class="about-img-card">
          <svg viewBox="0 0 440 330" xmlns="http://www.w3.org/2000/svg">
            <rect width="440" height="330" fill="#1A3A2A" rx="0"/>
            <ellipse cx="220" cy="240" rx="180" ry="55" fill="#2D6A4F" opacity="0.5"/>
            <!-- Rice stalks -->
            <g fill="#52B788" opacity="0.85">
              <ellipse cx="80" cy="195" rx="9" ry="24"/>
              <ellipse cx="80" cy="171" rx="3.5" ry="10"/>
              <ellipse cx="130" cy="185" rx="9" ry="26"/>
              <ellipse cx="130" cy="159" rx="3.5" ry="11"/>
              <ellipse cx="180" cy="192" rx="9" ry="24"/>
              <ellipse cx="180" cy="168" rx="3.5" ry="10"/>
              <ellipse cx="230" cy="182" rx="9" ry="26"/>
              <ellipse cx="230" cy="156" rx="3.5" ry="11"/>
              <ellipse cx="280" cy="190" rx="9" ry="24"/>
              <ellipse cx="280" cy="166" rx="3.5" ry="10"/>
              <ellipse cx="330" cy="188" rx="9" ry="26"/>
              <ellipse cx="330" cy="162" rx="3.5" ry="11"/>
              <ellipse cx="380" cy="194" rx="9" ry="22"/>
              <ellipse cx="380" cy="172" rx="3.5" ry="9"/>
            </g>
            <!-- Grid lines -->
            <g stroke="#95D5B2" stroke-width="0.8" opacity="0.3">
              <line x1="40" y1="100" x2="400" y2="100"/>
              <line x1="40" y1="120" x2="400" y2="120"/>
            </g>
            <!-- Data line -->
            <path d="M100 90 L180 72 L260 80 L340 68" stroke="#52B788" stroke-width="2" fill="none" stroke-linecap="round"/>
            <circle cx="100" cy="90" r="4" fill="#74C69D"/>
            <circle cx="180" cy="72" r="5" fill="#52B788"/>
            <circle cx="260" cy="80" r="4" fill="#74C69D"/>
            <circle cx="340" cy="68" r="4" fill="#74C69D"/>
            <!-- Label -->
            <rect x="140" y="44" width="100" height="22" rx="11" fill="#52B788" opacity="0.9"/>
            <text x="190" y="59" text-anchor="middle" fill="white" font-size="10" font-family="'DM Mono', monospace" letter-spacing="1">YIELD FORECAST</text>
          </svg>
        </div>
        <div class="about-accent-box">
          <div class="aab-num">4.8t</div>
          <div class="aab-label">per hectare<br>recorded yield</div>
        </div>
      </div>

      <div class="about-text">
        <div>
          <span class="eyebrow">About iClimate</span>
          <h2 class="section-title">Climate-informed rice production</h2>
        </div>
        <p>Rice production in Lian, Batangas underpins local food security and farmer livelihoods. Yet rainfall variability, shifting temperatures, droughts, and typhoons increasingly disrupt planting schedules and harvests.</p>
        <p>iClimate integrates PAGASA climate data with local rice records from the Municipal Agricultural Office (MAO) to deliver weather analysis, planting recommendations, and decision-support analytics.</p>
        <div class="pillars">
          <div class="pillar">
            <span class="pillar-icon">Rice</span>
            <div class="pillar-body">
              <strong>Built for Farmers</strong>
              <p>Designed around local context, seasonal cycles, and the way farmers actually make decisions.</p>
            </div>
          </div>
          <div class="pillar">
            <span class="pillar-icon">Data</span>
            <div class="pillar-body">
              <strong>Data-Driven</strong>
              <p>Designed to organize PAGASA-sourced entries and historical crop records from the MAO.</p>
            </div>
          </div>
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
      <h2>Everything you need to grow smarter</h2>
      <p>Six integrated tools covering every stage of the rice growing cycle &mdash; from records to harvest.</p>
    </div>
    <div class="features-grid">
      <div class="feature-card fc-wide">
        <div class="fc-icon-wrap">
          <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
            <path d="M2 13C3.5 9 6.5 7 11 7s7.5 2 9 6" stroke="#52B788" stroke-width="1.8" stroke-linecap="round"/>
            <path d="M5 17c1-2.5 3-4 6-4s5 1.5 6 4" stroke="#95D5B2" stroke-width="1.4" stroke-linecap="round"/>
            <circle cx="11" cy="4.5" r="2" fill="#52B788"/>
          </svg>
        </div>
        <h3>Weather Analysis</h3>
        <p>Manage rainfall, temperature, humidity, wind, and seasonal climate records for Lian, Batangas.</p>
        <span class="fc-badge">Climate Records</span>
      </div>
      <div class="feature-card">
        <div class="fc-icon-wrap">
          <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
            <rect x="2" y="14" width="4" height="6" rx="1" fill="#52B788"/>
            <rect x="9" y="9" width="4" height="11" rx="1" fill="#52B788" opacity="0.7"/>
            <rect x="16" y="5" width="4" height="15" rx="1" fill="#52B788" opacity="0.45"/>
            <path d="M4 10l7-5 7-3" stroke="#95D5B2" stroke-width="1.4" stroke-linecap="round"/>
          </svg>
        </div>
        <h3>Rice Rice Production</h3>
        <p>Organized rice production records and report-ready summaries for municipal planning and future forecasting work.</p>
        <span class="fc-badge">Records</span>
      </div>
      <div class="feature-card">
        <div class="fc-icon-wrap">
          <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
            <circle cx="11" cy="11" r="8" stroke="#52B788" stroke-width="1.8"/>
            <path d="M11 6v5l3.5 2" stroke="#95D5B2" stroke-width="1.6" stroke-linecap="round"/>
          </svg>
        </div>
        <h3>Planting Advisory</h3>
        <p>Season-specific recommendations for transplanting dates, seed varieties, and fertilizer schedules.</p>
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
        <p>Track long-term trends, detect anomalies, and observe seasonal pattern shifts across your region.</p>
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
        <p>Barangay climate risk records using Flood, Drought, Typhoon, and Heat risk categories.</p>
        <span class="fc-badge">Risk Records</span>
      </div>
      <div class="feature-card fc-wide">
        <div class="fc-icon-wrap">
          <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
            <path d="M4 3h10l4 4v12H4V3z" stroke="#52B788" stroke-width="1.8"/>
            <path d="M14 3v4h4" stroke="#52B788" stroke-width="1.8"/>
            <path d="M7 10h8M7 13h6M7 16h7" stroke="#95D5B2" stroke-width="1.4" stroke-linecap="round"/>
          </svg>
        </div>
        <h3>Reports &amp; Analytics</h3>
        <p>Generate downloadable seasonal reports, multi-year comparative analysis, and shareable insights for LGU stakeholders.</p>
        <span class="fc-badge">Analytics</span>
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
      <p>Three specialized user roles: Rice Farmers, MAO Personnel, and IT Expert, each with dedicated features and dashboards for climate-informed agricultural decision-making.</p>
    </div>
    <div class="roles-grid">

      <!-- Rice Farmers -->
      <div class="role-card">
        <div class="role-icon-wrap role-icon-wrap--img">
          <img src="{{ asset('images/rice farmer.png') }}" alt="Rice Farmer" class="role-logo-img">
        </div>
        <h3>Rice Farmers</h3>
        <p>Access climate monitoring, rice planting advisories, seasonal reports, and weather risk alerts through a farmer-friendly dashboard.</p>
        <div class="role-divider"></div>
        <div class="role-features-label">Key Features</div>
        <ul class="role-features">
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Climate Dashboard
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Rice Production
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Planting Advisory
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Weather Alerts
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

      <!-- MAO Staff -->
      <div class="role-card">
        <div class="role-icon-wrap role-icon-wrap--img">
          <img src="{{ asset('images/da.png') }}" alt="Department of Agriculture" class="role-logo-img">
        </div>
        <h3>MAO Personnel</h3>
        <p>Monitor rice production trends, validate agricultural records, analyze climate-yield relationships, and generate reports for agricultural planning and farmer support.</p>
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
            Data Validation
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Forecast Monitoring
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
          <img src="{{ asset('images/it-personnel.png') }}" alt="IT Expert" class="role-logo-img">
        </div>
        <h3>IT Expert</h3>
        <p>Maintain the system infrastructure, manage databases and user accounts, review system logs, maintain records, and ensure system security and performance.</p>
        <div class="role-divider"></div>
        <div class="role-features-label">Key Features</div>
        <ul class="role-features">
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            User Management
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            System Monitoring
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Database Maintenance
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Security Control
          </li>
          <li class="role-feature-item">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Backup &amp; Recovery
          </li>
        </ul>
        <a href="{{ route('login') }}" class="role-cta">
          View Dashboard
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><rect x="1" y="1" width="5" height="5" rx="1" fill="currentColor"/><rect x="8" y="1" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/><rect x="1" y="8" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/><rect x="8" y="8" width="5" height="5" rx="1" fill="currentColor" opacity="0.5"/></svg>
        </a>
      </div>

    </div>
  </div>
</section>

<!-- CONTACT -->
<section class="contact-section" id="contact">
  <div class="container">
    <div class="contact-grid">
      <div class="contact-info">
        <span class="eyebrow">Contact Us</span>
        <h2>Get in touch</h2>
        <p>Have questions about iClimate? Reach out to our team at the Agriculture Municipality Office of Lian, Batangas.</p>
        <div class="contact-details">
          <div class="cd-item">
            <div class="cd-icon-wrap">LOC</div>
            <div class="cd-text">
              <strong>Address</strong>
              <span>Lian, Batangas, Philippines</span>
            </div>
          </div>
          <div class="cd-item">
            <div class="cd-icon-wrap">MAIL</div>
            <div class="cd-text">
              <strong>Email</strong>
              <span>maolian@iclimate.ph</span>
            </div>
          </div>
          <div class="cd-item">
            <div class="cd-icon-wrap">TEL</div>
            <div class="cd-text">
              <strong>Phone</strong>
              <span>+63 49 123 4567</span>
            </div>
          </div>
        </div>
      </div>
      <div class="contact-form-card">
        <h3>Send a message</h3>
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" class="form-input" placeholder="Juan dela Cruz"/>
        </div>
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" class="form-input" placeholder="juan@email.com"/>
        </div>
        <div class="form-group">
          <label>Message</label>
          <textarea class="form-input" rows="4" placeholder="How can we help you?"></textarea>
        </div>
        <button type="button" class="btn btn-primary btn-full">Send Message</button>
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
          <img src="{{ asset('images/iClimate.png') }}" alt="iClimate" class="brand-logo-img">
        </a>
        <p class="footer-tagline">A climate-informed agricultural decision-support platform integrating PAGASA climate data and local rice production records to support farmers, MAO personnel, and IT experts through forecasting, monitoring, and analytics.<br>Serving Lian, Batangas.</p>
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
            <li><a href="{{ route('login') }}">IT Expert Dashboard</a></li>
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
    const navbar = document.getElementById('navbar');
    const navLinks = document.getElementById('navLinks');
    const hamburger = document.getElementById('hamburger');
    const links = document.querySelectorAll('.nav-link');
    const sections = [...document.querySelectorAll('section[id]')];

    const setNavbarState = () => {
      navbar?.classList.toggle('scrolled', window.scrollY > 24);
    };

    const setActiveLink = () => {
      const current = sections.findLast(section => section.offsetTop - 120 <= window.scrollY);
      if (!current) return;
      links.forEach(link => link.classList.toggle('active', link.getAttribute('href') === `#${current.id}`));
    };

    hamburger?.addEventListener('click', () => navLinks?.classList.toggle('open'));
    links.forEach(link => link.addEventListener('click', () => navLinks?.classList.remove('open')));
    window.addEventListener('scroll', () => {
      setNavbarState();
      setActiveLink();
    }, { passive: true });

    setNavbarState();
    setActiveLink();
  });
</script>
</body>
</html>