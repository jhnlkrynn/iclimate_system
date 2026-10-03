@php($errors = $errors ?? new \Illuminate\Support\ViewErrorBag)
<!DOCTYPE html>
<html lang="{{ str_replace('_' , '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Create Account | {{ config('app.name', 'iClimate') }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Inter:wght@300;400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    :root {
      --auth-ease-out: cubic-bezier(.16,1,.3,1);
      --auth-ease-smooth: cubic-bezier(.22,.61,.36,1);
      --auth-motion-fast: 160ms var(--auth-ease-smooth);
      --auth-motion-med: 260ms var(--auth-ease-smooth);
      --auth-motion-slow: 560ms var(--auth-ease-out);
    }
    html, body { margin: 0; min-height: 100%; height: 100%; }
    body { font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
    a { color: inherit; }
    button, input, select { font: inherit; }
    .field-error { color: #c0392b; font-size: .78rem; font-weight: 600; margin-top: 6px; }
    .form-input.is-invalid { border-color: #e57a68; box-shadow: 0 0 0 3px rgba(229,122,104,.14); }
    .btn-login:disabled { opacity: .75; cursor: wait; transform: none; }

    /* -- SCENE BACKGROUND ------------------------------- */
    body.register-page {
      min-height: 100vh;
      background-color: #1F4D3A;
      background-image:
        linear-gradient(180deg, rgba(22,59,45,.82), rgba(22,59,45,.92)),
        url('{{ asset('images/rice-contact-golden.jpg') }}');
      background-position: center;
      background-size: cover;
      background-repeat: no-repeat;
      background-attachment: fixed;
      display: flex;
      flex-direction: column;
      position: relative;
      overflow: hidden;
    }
    .scene-glow { position: fixed; z-index: 0; pointer-events: none; border-radius: 50%; filter: blur(90px); }
    .scene-glow-1 { width: 420px; height: 420px; background: rgba(95,143,120,.16); top: -110px; right: -70px; }
    .scene-glow-2 { width: 320px; height: 320px; background: rgba(143,175,154,.1); bottom: -50px; left: -50px; }

    .register-main { position: relative; z-index: 1; flex: 1 1 auto; min-height: 0; display: flex; align-items: center; justify-content: center; padding: 18px 20px; }
    .register-grid {
      width: 100%; max-width: 1080px;
      display: grid; grid-template-columns: 42% 58%; gap: 30px; align-items: center;
      animation: authSceneEnter var(--auth-motion-slow) both;
    }

    /* -- LEFT: MARKETING COLUMN -------------------------- */
    .back-link-top {
      display: inline-flex; align-items: center; gap: 6px; font-size: .85rem; font-weight: 600;
      color: #7FD6B5; text-decoration: none; margin-bottom: 14px; transition: color .2s;
    }
    .back-link-top:hover { color: #C9D8CE; }

    .reg-logo { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
    .reg-logo img { width: 36px; height: auto; display: block; }
    .reg-logo-word { font-family: 'Inter', sans-serif; font-weight: 800; font-size: 1.2rem; color: #fff; letter-spacing: -0.01em; }

    .reg-headline { font-family: 'DM Serif Display', Georgia, serif; font-size: clamp(1.55rem, 2.45vw, 2rem); font-weight: 400; color: #fff; line-height: 1.12; margin-bottom: 10px; }
    .reg-headline em { font-style: normal; color: #7FD6B5; }
    .reg-sub { font-size: .88rem; color: rgba(255,255,255,.78); line-height: 1.45; margin-bottom: 14px; max-width: 420px; }
    .reg-sub a { color: #B9E8D3; font-weight: 600; text-decoration: underline; text-decoration-color: rgba(185,232,211,.4); text-underline-offset: 3px; }

    .perk-card {
      background: rgba(31,77,58,.4); border: 1px solid rgba(95,143,120,.5); border-radius: 14px;
      padding: 14px 16px; margin-bottom: 12px;
      transition: transform var(--auth-motion-med), border-color var(--auth-motion-med), background-color var(--auth-motion-med);
    }
    .perk-card:hover { transform: translateY(-3px); border-color: rgba(95,143,120,.72); background: rgba(31,77,58,.55); }
    .perk-header { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
    .perk-icon {
      width: 32px; height: 32px; border-radius: 9px; background: rgba(127,214,181,.18); border: 1px solid rgba(127,214,181,.4);
      color: #7FD6B5; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .perk-title { font-family: 'Inter', sans-serif; font-weight: 700; font-size: .93rem; color: #fff; }
    .perk-sub { font-family: 'DM Mono', monospace; font-size: .62rem; letter-spacing: .05em; text-transform: uppercase; color: rgba(185,232,211,.95); margin-top: 2px; }
    .perk-list { display: flex; flex-direction: column; gap: 6px; }
    .perk-item { display: flex; align-items: center; gap: 10px; font-size: .84rem; color: rgba(127,214,181,.92); }
    .perk-check { width: 17px; height: 17px; background: rgba(127,214,181,.25); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: #7FD6B5; }

    .notice-card {
      display: flex; gap: 10px; padding: 11px 14px; background: rgba(22,59,45,.6);
      border: 1px solid rgba(95,143,120,.4); border-radius: 12px;
      transition: transform var(--auth-motion-med), border-color var(--auth-motion-med), background-color var(--auth-motion-med);
    }
    .notice-card:hover { transform: translateY(-3px); border-color: rgba(95,143,120,.6); background: rgba(22,59,45,.75); }
    .notice-icon { flex-shrink: 0; margin-top: 1px; color: #1F5A46; }
    .notice-card p { font-size: .82rem; color: rgba(255,255,255,.75); line-height: 1.5; margin: 0; }
    .notice-card strong { color: #C9D8CE; font-weight: 700; }

    /* -- RIGHT: FORM CARD --------------------------------- */
    .form-card {
      background: rgba(255,255,255,.98); border: 1.5px solid rgba(95,143,120,.4); border-radius: 20px;
      box-shadow: 0 26px 60px rgba(13,31,24,.24); backdrop-filter: blur(18px); padding: 16px 28px;
      transition: transform var(--auth-motion-med), box-shadow var(--auth-motion-med), border-color var(--auth-motion-med), background-color var(--auth-motion-med);
      transform: translateZ(0);
    }
    .form-card:hover { transform: translateY(-2px); box-shadow: 0 32px 72px rgba(13,31,24,.3); border-color: rgba(95,143,120,.6); }
    .form-card-header { display: flex; align-items: center; gap: 10px; margin-bottom: 2px; }
    .form-card-icon {
      width: 34px; height: 34px; border-radius: 50%; background: rgba(95,143,120,.16); border: 1px solid rgba(95,143,120,.35);
      color: #1F4D3A; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .form-card-header h1 { font-family: 'DM Serif Display', Georgia, serif; font-size: 1.35rem; font-weight: 400; color: #163B2D; letter-spacing: -0.01em; }
    .form-card-sub { font-size: .82rem; color: #4B5563; margin: 0 0 8px; }

    .role-pill {
      display: inline-flex; align-items: center; gap: 8px; background: rgba(127,214,181,.18);
      border: 1px solid rgba(127,214,181,.4); border-radius: 100px; padding: 4px 12px 4px 7px; margin-bottom: 9px;
    }
    .role-pill-dot { width: 20px; height: 20px; background: #7FD6B5; color: #163B2D; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-family: 'DM Mono', monospace; font-size: .6rem; font-weight: 700; }
    .role-pill span { font-size: .8rem; font-weight: 600; color: #163B2D; }

    .form-group { margin-bottom: 7px; }
    .form-group label { display: block; font-size: .78rem; font-weight: 600; color: #1F2937; margin-bottom: 3px; }
    .form-row-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .input-wrap { position: relative; }
    .form-input {
      width: 100%; background: #fff; border: 1.5px solid rgba(95,143,120,.45); border-radius: 11px;
      min-height: 36px; padding: 8px 12px; font-family: 'Inter', sans-serif; font-size: .88rem; color: #1F2937; transition: border-color var(--auth-motion-med), box-shadow var(--auth-motion-med), background-color var(--auth-motion-med), transform var(--auth-motion-med); outline: none;
    }
    .form-input.has-icon { padding-left: 38px; }
    .form-input:focus { border-color: #1F4D3A; box-shadow: 0 0 0 3px rgba(31,77,58,.18); background: #fff; transform: translateY(-1px); }
    .form-input::placeholder { color: #64748B; }
    .input-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #1F4D3A; }
    .pwd-toggle { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #64748B; background: none; border: none; cursor: pointer; padding: 2px; display: flex; transition: color .2s; }
    .pwd-toggle:hover { color: #1F4D3A; }
    .captcha-card {
      display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 8px; align-items: center;
      background: #EDF6F1; border: 1.5px solid #5F8F78; border-radius: 12px;
      padding: 7px 11px; margin-bottom: 8px;
    }
    .captcha-icon {
      width: 30px; height: 30px; border-radius: 9px; display: grid; place-items: center;
      background: rgba(95,143,120,.18); color: #1F4D3A; border: 1px solid rgba(95,143,120,.35);
    }
    .captcha-label { font-size: .76rem; font-weight: 800; color: #1F2937; margin-bottom: 3px; }
    .captcha-image {
      display: block; width: 100%; height: 52px; border-radius: 11px;
      border: 1px solid rgba(95,143,120,.4); margin-bottom: 6px; background: #ffffff;
    }

    .pwd-strength { margin-top: 5px; display: flex; gap: 4px; }
    .pwd-strength-bar { height: 3px; flex: 1; border-radius: 2px; background: rgba(95,143,120,.25); transition: background .3s; }
    .pwd-strength-bar.filled-weak { background: #e57a68; }
    .pwd-strength-bar.filled-ok { background: #C9D8CE; }
    .pwd-strength-bar.filled-strong { background: #1F4D3A; }
    .pwd-strength-meta { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 6px; }
    .pwd-strength-label { font-size: .72rem; font-weight: 800; color: #4B5563; }
    .pwd-strength-label.is-weak { color: #c0392b; }
    .pwd-strength-label.is-ok { color: #7c6a22; }
    .pwd-strength-label.is-strong { color: #1F4D3A; }
    .pwd-guide { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 5px 9px; margin-top: 7px; padding: 8px 10px; background: rgba(95,143,120,.08); border: 1.5px solid rgba(95,143,120,.28); border-radius: 11px; }
    .pwd-guide-item { display: flex; align-items: center; gap: 6px; min-width: 0; font-size: .72rem; font-weight: 600; color: #4B5563; line-height: 1.25; }
    .pwd-guide-dot { width: 15px; height: 15px; border-radius: 50%; display: grid; place-items: center; flex-shrink: 0; border: 1.5px solid rgba(100,116,139,.35); color: transparent; background: #fff; font-size: .62rem; font-weight: 900; line-height: 1; }
    .pwd-guide-item.is-met { color: #1F4D3A; }
    .pwd-guide-item.is-met .pwd-guide-dot { background: #1F4D3A; border-color: #1F4D3A; color: #fff; }

    .terms-row {
      display: flex; align-items: flex-start; gap: 9px; margin: 8px 0; padding: 9px 11px;
      background: rgba(95,143,120,.08); border: 1.5px solid rgba(95,143,120,.3); border-radius: 12px;
    }
    .custom-checkbox { display: none; }
    .checkbox-custom {
      width: 17px; height: 17px; border: 2px solid rgba(95,143,120,.5); border-radius: 5px; background: #fff;
      display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px; transition: all .18s; cursor: pointer;
    }
    .custom-checkbox:checked + .checkbox-custom { background: #1F4D3A; border-color: #1F4D3A; }
    .custom-checkbox:checked + .checkbox-custom::after { content: ''; width: 9px; height: 5px; border: 2px solid white; border-top: none; border-right: none; transform: rotate(-45deg) translateY(-1px); display: block; }
    .terms-text { font-size: .78rem; color: #4B5563; line-height: 1.35; }
    .terms-text a { color: #1F4D3A; font-weight: 600; text-decoration: none; }
    .terms-text a:hover { color: #163B2D; }

    #formMessage { font-size: .84rem; color: #c0392b; background: rgba(229,122,104,.1); border: 1px solid rgba(229,122,104,.28); border-radius: 8px; padding: 9px 14px; margin-bottom: 12px; }

    .btn-login {
      width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 10px;
      background: #1F5A46; color: #fff; border: none; border-radius: 11px; font-family: 'Inter', sans-serif;
      font-size: .9rem; font-weight: 800; cursor: pointer; transition: transform var(--auth-motion-med), box-shadow var(--auth-motion-med), background-color var(--auth-motion-med); margin-bottom: 8px;
      box-shadow: 0 10px 24px rgba(143,175,154,.28);
    }
    .btn-login:hover { background: #123F32; color: #fff; transform: translateY(-1px); box-shadow: none; }
    .btn-login:active { transform: translateY(0) scale(.985); }

    .login-register { text-align: center; font-size: .8rem; color: #4B5563; margin: 0; }
    .login-register a { color: #1F5A46; font-weight: 700; text-decoration: none; transition: color .2s; }
    .login-register a:hover { color: #123F32; }

    @keyframes authSceneEnter { from { opacity: 0; transform: translateY(16px) scale(.992); filter: blur(2px); } to { opacity: 1; transform: translateY(0) scale(1); filter: blur(0); } }

    /* -- FOOTER BAR --------------------------------------- */
    .register-foot {
      position: relative; z-index: 1;
      background: rgba(22,59,45,.92); border-top: 1px solid rgba(95,143,120,.3);
      flex: 0 0 auto; padding: 8px 32px; display: flex; align-items: center; justify-content: space-between; gap: 18px; flex-wrap: wrap;
    }
    .rf-brand { display: flex; align-items: center; gap: 10px; }
    .rf-brand img { width: 22px; height: 22px; object-fit: contain; }
    .rf-brand-text { display: flex; flex-direction: column; line-height: 1.18; }
    .rf-brand-text strong { font-family: 'Inter', sans-serif; font-weight: 800; font-size: .9rem; color: #fff; }
    .rf-brand-text span { font-size: .68rem; color: rgba(127,214,181,.75); }
    .rf-badges { display: flex; gap: 18px; flex-wrap: wrap; }
    .rf-badge { display: flex; align-items: center; gap: 8px; }
    .rf-badge-icon { width: 24px; height: 24px; border-radius: 50%; background: rgba(95,143,120,.18); border: 1px solid rgba(95,143,120,.4); color: #5F8F78; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .rf-badge-text { display: flex; flex-direction: column; line-height: 1.18; }
    .rf-badge-text strong { font-size: .78rem; color: #fff; font-weight: 700; }
    .rf-badge-text span { font-size: .68rem; color: rgba(127,214,181,.75); }

    .register-page .reg-sub,
    .register-page .field-error,
    .register-page .notice-card p,
    .register-page .form-card-sub,
    .register-page .terms-text,
    .register-page #formMessage,
    .register-page .login-register,
    .register-page .perk-item {
      font-size: .96rem;
      line-height: 1.55;
    }
    .register-page .form-group label,
    .register-page .captcha-label,
    .register-page .role-pill span {
      font-size: .84rem;
    }
    .register-page .form-group input,
    .register-page .form-group select,
    .register-page .btn-submit {
      font-size: .9rem;
    }
    .register-page .perk-title,
    .register-page .rf-brand-text strong,
    .register-page .rf-badge-text strong {
      font-size: .9rem;
    }
    .register-page .perk-sub,
    .register-page .rf-brand-text span,
    .register-page .rf-badge-text span {
      font-size: .7rem;
    }

    @media (min-width: 981px) and (max-height: 980px) {
      .register-main { padding-block: 18px; }
      .register-grid { max-width: 1040px; gap: 24px; }
      .back-link-top, .reg-logo, .reg-sub, .perk-card { margin-bottom: 10px; }
      .reg-headline { font-size: 1.65rem; }
      .form-card { padding: 11px 24px; border-radius: 16px; }
      .form-card-header h1 { font-size: 1.25rem; }
      .form-card-icon { width: 30px; height: 30px; }
      .form-card-sub { margin-bottom: 5px; }
      .role-pill { margin-bottom: 6px; }
      .form-group { margin-bottom: 4px; }
      .form-input { min-height: 32px; padding-block: 6px; }
      .pwd-strength { margin-top: 4px; }
      .pwd-strength-meta { margin-top: 4px; }
      .pwd-guide { grid-template-columns: repeat(3, minmax(0, 1fr)); margin-top: 5px; padding: 6px 9px; gap: 3px 8px; }
      .captcha-card { grid-template-columns: minmax(0, 1fr); padding: 6px 10px; margin-bottom: 6px; }
      .captcha-icon { display: none; }
      .captcha-image { height: 42px; margin-bottom: 4px; }
      .terms-row { padding: 7px 10px; margin: 6px 0; }
      .btn-login { padding-block: 9px; margin-bottom: 6px; }
      .register-foot { padding-block: 6px; }
    }

    /* -- RESPONSIVE ---------------------------------------- */
    @media (max-width: 980px) {
      body.register-page { overflow-y: auto; }
      /* Let the form set the page height so the footer follows it instead
         of sitting on top of the lower fields. */
      .register-main { flex: 0 0 auto; min-height: auto; }
      .register-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 540px) {
      .form-card { padding: 22px 18px; }
      .form-row-2col { grid-template-columns: 1fr; gap: 0; }
      .captcha-card { grid-template-columns: 1fr; }
      .captcha-icon { display: none; }
      .register-foot { flex-direction: column; align-items: flex-start; }
    }
    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after {
        animation-duration: .01ms !important;
        animation-iteration-count: 1 !important;
        scroll-behavior: auto !important;
        transition-duration: .01ms !important;
      }
    }
  </style>
    @include('partials.global-loader')
</head>
<body class="register-page">
  <div class="scene-glow scene-glow-1"></div>
  <div class="scene-glow scene-glow-2"></div>

  <div class="register-main">
    <div class="register-grid">

      <!-- -- LEFT: MARKETING COLUMN -------------------------- -->
      <div>
        <a href="{{ route('login') }}" class="back-link-top">
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M11 7H3M6 10L3 7l3-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Back to Login
        </a>

        <div class="reg-logo">
          <img src="{{ asset('images/iclimate-icon.png') }}" alt="iClimate">
          <div class="reg-logo-word">iClimate</div>
        </div>

        <h1 class="reg-headline">Join the <em>iClimate</em> Community</h1>
        <p class="reg-sub">Create a free account to access climate records, planting advisories, community feed posts, and notifications for <a href="{{ url('/') }}">Lian, Batangas</a>.</p>

        <div class="perk-card">
          <div class="perk-header">
            <div class="perk-icon">
              <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 2c1.4 2 2.5 3.6 2.5 5.3A2.5 2.5 0 1 1 6.5 7.3C6.5 5.6 7.6 4 9 2Z" stroke="currentColor" stroke-width="1.4"/></svg>
            </div>
            <div>
              <div class="perk-title">Rice Farmer Account</div>
              <div class="perk-sub">Public Registration &middot; Free Access</div>
            </div>
          </div>
          <div class="perk-list">
            <div class="perk-item"><span class="perk-check"><svg width="10" height="8" viewBox="0 0 10 8" fill="none"><path d="M1 4l3 3 5-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Climate records and advisories</div>
            <div class="perk-item"><span class="perk-check"><svg width="10" height="8" viewBox="0 0 10 8" fill="none"><path d="M1 4l3 3 5-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Seasonal planting recommendations</div>
            <div class="perk-item"><span class="perk-check"><svg width="10" height="8" viewBox="0 0 10 8" fill="none"><path d="M1 4l3 3 5-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Rice production information</div>
            <div class="perk-item"><span class="perk-check"><svg width="10" height="8" viewBox="0 0 10 8" fill="none"><path d="M1 4l3 3 5-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Community updates and notifications</div>
          </div>
        </div>

        <div class="notice-card">
          <div class="notice-icon">
            <svg width="18" height="18" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5"/><line x1="10" y1="9" x2="10" y2="14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="10" cy="6" r="1" fill="currentColor"/></svg>
          </div>
          <p><strong>MAO Staff and IT Personnel</strong> accounts are provisioned by system administrators. Contact your local MAO office for access.</p>
        </div>
      </div>

      <!-- -- RIGHT: FORM CARD --------------------------------- -->
      <div class="form-card">
        <div class="form-card-header">
          <div class="form-card-icon">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="8" cy="7" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M2.5 16c.6-3.4 2.6-5.2 5.5-5.2s4.9 1.8 5.5 5.2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M15 6v5M12.5 8.5h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          </div>
          <h1>Create Your Account</h1>
        </div>
        <p class="form-card-sub">Join iClimate as a Rice Farmer - it's free</p>

        <div class="role-pill">
          <div class="role-pill-dot">RF</div>
          <span>Rice Farmer &middot; Public Registration</span>
        </div>

        <form method="POST" action="{{ route('register') }}" class="register-form">
          @csrf
          <input type="hidden" id="name" name="name" value="{{ old('name') }}">

          <div class="form-row-2col">
            <div class="form-group">
              <label for="firstName">First Name</label>
              <div class="input-wrap">
                <svg class="input-icon" width="15" height="15" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="5.2" r="2.6" stroke="currentColor" stroke-width="1.4"/><path d="M2.5 13.5c.6-3 2.5-4.6 5.5-4.6s4.9 1.6 5.5 4.6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                <input type="text" id="firstName" class="form-input has-icon @error('name') is-invalid @enderror" placeholder="Juan" autocomplete="given-name" required/>
              </div>
            </div>
            <div class="form-group">
              <label for="lastName">Last Name</label>
              <div class="input-wrap">
                <svg class="input-icon" width="15" height="15" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="5.2" r="2.6" stroke="currentColor" stroke-width="1.4"/><path d="M2.5 13.5c.6-3 2.5-4.6 5.5-4.6s4.9 1.6 5.5 4.6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                <input type="text" id="lastName" class="form-input has-icon @error('name') is-invalid @enderror" placeholder="Dela Cruz" autocomplete="family-name" required/>
              </div>
            </div>
          </div>
          @error('name')<div class="field-error">{{ $message }}</div>@enderror

          <div class="form-group">
            <label for="email">Email Address</label>
            <div class="input-wrap">
              <svg class="input-icon" width="15" height="15" viewBox="0 0 16 16" fill="none"><rect x="1" y="3" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M1 5l7 5 7-5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
              <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-input has-icon @error('email') is-invalid @enderror" placeholder="your.email@example.com" autocomplete="username" required/>
            </div>
          </div>
          @error('email')<div class="field-error">{{ $message }}</div>@enderror

          <div class="form-group">
            <label for="farmer_organization_id">Farmer's Organization</label>
            <div class="input-wrap">
              <svg class="input-icon" width="15" height="15" viewBox="0 0 16 16" fill="none"><path d="M2 13V6l6-3 6 3v7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 13V8h6v5M1.5 13.2h13" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
              <select id="farmer_organization_id" name="farmer_organization_id" class="form-input has-icon @error('farmer_organization_id') is-invalid @enderror">
                <option value="">None</option>
                @foreach($farmerOrganizations as $organization)
                  <option
                    value="{{ $organization->id }}"
                    data-barangay="{{ $organization->barangay }}"
                    @selected((string) old('farmer_organization_id') === (string) $organization->id)
                  >
                    {{ $organization->name }}
                  </option>
                @endforeach
              </select>
            </div>
            @error('farmer_organization_id')<div class="field-error">{{ $message }}</div>@enderror
          </div>

          <div class="form-group" id="assignedBarangayGroup" style="display:none;">
            <label for="assignedBarangay">Assigned Barangay/Farm Location</label>
            <div class="input-wrap">
              <svg class="input-icon" width="15" height="15" viewBox="0 0 16 16" fill="none"><path d="M8 14s5-3.8 5-8A5 5 0 0 0 3 6c0 4.2 5 8 5 8Z" stroke="currentColor" stroke-width="1.4"/><circle cx="8" cy="6" r="1.7" stroke="currentColor" stroke-width="1.4"/></svg>
              <input type="text" id="assignedBarangay" class="form-input has-icon" value="" disabled>
            </div>
          </div>

          <div class="form-group" id="manualBarangayGroup">
            <label for="barangay">Farm Location/Barangay</label>
            <div class="input-wrap">
              <svg class="input-icon" width="15" height="15" viewBox="0 0 16 16" fill="none"><path d="M8 14s5-3.8 5-8A5 5 0 0 0 3 6c0 4.2 5 8 5 8Z" stroke="currentColor" stroke-width="1.4"/><circle cx="8" cy="6" r="1.7" stroke="currentColor" stroke-width="1.4"/></svg>
              <select id="barangay" name="barangay" class="form-input has-icon @error('barangay') is-invalid @enderror">
                <option value="">Select your barangay</option>
                @foreach($manualBarangays as $barangay)
                  <option value="{{ $barangay }}" @selected(old('barangay') === $barangay)>{{ $barangay }}</option>
                @endforeach
              </select>
            </div>
            @error('barangay')<div class="field-error">{{ $message }}</div>@enderror
          </div>

          <div class="form-group">
            <label for="password">Password</label>
            <div class="input-wrap">
              <svg class="input-icon" width="15" height="15" viewBox="0 0 16 16" fill="none"><rect x="3" y="7" width="10" height="7" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M5 7V5a3 3 0 016 0v2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
              <input type="password" id="password" name="password" class="form-input has-icon @error('password') is-invalid @enderror" placeholder="Create a strong password (min. 8 chars)" autocomplete="new-password" oninput="checkStrength(this.value)" required/>
              <button class="pwd-toggle" id="pwdToggle" type="button" aria-label="Show password">
                <svg id="eyeIcon" width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M1 8C2.5 4.5 5 3 8 3s5.5 1.5 7 5c-1.5 3.5-4 5-7 5S2.5 11.5 1 8z" stroke="currentColor" stroke-width="1.5"/><circle cx="8" cy="8" r="2" stroke="currentColor" stroke-width="1.5"/></svg>
              </button>
            </div>
            <div class="pwd-strength">
              <div class="pwd-strength-bar" id="bar1"></div>
              <div class="pwd-strength-bar" id="bar2"></div>
              <div class="pwd-strength-bar" id="bar3"></div>
              <div class="pwd-strength-bar" id="bar4"></div>
            </div>
            <div class="pwd-strength-meta" aria-live="polite">
              <span class="pwd-strength-label" id="passwordStrengthLabel">Password strength: Not started</span>
            </div>
            <div class="pwd-guide" id="passwordGuide" aria-label="Password requirements">
              <div class="pwd-guide-item" data-password-rule="length"><span class="pwd-guide-dot" aria-hidden="true">&#10003;</span>At least 8 characters</div>
              <div class="pwd-guide-item" data-password-rule="lowercase"><span class="pwd-guide-dot" aria-hidden="true">&#10003;</span>Lowercase letter</div>
              <div class="pwd-guide-item" data-password-rule="uppercase"><span class="pwd-guide-dot" aria-hidden="true">&#10003;</span>Uppercase letter</div>
              <div class="pwd-guide-item" data-password-rule="number"><span class="pwd-guide-dot" aria-hidden="true">&#10003;</span>Number</div>
              <div class="pwd-guide-item" data-password-rule="symbol"><span class="pwd-guide-dot" aria-hidden="true">&#10003;</span>Symbol</div>
            </div>
            @error('password')<div class="field-error">{{ $message }}</div>@enderror
          </div>

          <div class="form-group">
            <label for="confirmPassword">Confirm Password</label>
            <div class="input-wrap">
              <svg class="input-icon" width="15" height="15" viewBox="0 0 16 16" fill="none"><rect x="3" y="7" width="10" height="7" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M5 7V5a3 3 0 016 0v2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
              <input type="password" id="confirmPassword" name="password_confirmation" class="form-input has-icon @error('password_confirmation') is-invalid @enderror" placeholder="Re-enter your password" autocomplete="new-password" required/>
              <button class="pwd-toggle" id="confirmPwdToggle" type="button" aria-label="Show password">
                <svg id="confirmEyeIcon" width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M1 8C2.5 4.5 5 3 8 3s5.5 1.5 7 5c-1.5 3.5-4 5-7 5S2.5 11.5 1 8z" stroke="currentColor" stroke-width="1.5"/><circle cx="8" cy="8" r="2" stroke="currentColor" stroke-width="1.5"/></svg>
              </button>
            </div>
          </div>
          @error('password_confirmation')<div class="field-error">{{ $message }}</div>@enderror

          <div class="captcha-card">
            <div class="captcha-icon" aria-hidden="true">
              <svg width="17" height="17" viewBox="0 0 18 18" fill="none"><path d="M9 2l5 2v4.2c0 3.1-1.9 5.8-5 7.1-3.1-1.3-5-4-5-7.1V4l5-2z" stroke="currentColor" stroke-width="1.5"/><path d="M6.8 9l1.4 1.4 3.2-3.4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <div>
              <label for="captcha_answer" class="captcha-label">Security Check</label>
              <img class="captcha-image" src="{{ route('captcha.image', ['context' => 'register', 'v' => $captcha['token'] ?? now()->timestamp]) }}" alt="Security code image">
              <input type="text" id="captcha_answer" name="captcha_answer" class="form-input @error('captcha_answer') is-invalid @enderror" placeholder="Enter the code shown above" autocomplete="off" autocapitalize="characters" spellcheck="false" required/>
              @error('captcha_answer')<div class="field-error">{{ $message }}</div>@enderror
            </div>
          </div>

          <div class="terms-row">
            <input type="checkbox" id="agreeTerms" class="custom-checkbox" required/>
            <label class="checkbox-custom" for="agreeTerms"></label>
            <span class="terms-text">I agree to the <a href="#">Terms of Use</a> and <a href="#">Privacy Policy</a>. I understand my data is used to provide climate and agricultural recommendations.</span>
          </div>

          <div id="formMessage" style="display:none;"></div>

          <button class="btn-login" id="createAccountBtn" type="submit">
            Create Account
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="6" cy="6" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M1 15c.6-3.4 2.5-5.2 5-5.2s4.4 1.8 5 5.2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M12 5v4M10 7h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          </button>
        </form>

        <p class="login-register">Already have an account? <a href="{{ route('login') }}">Sign In</a></p>
      </div>

    </div>
  </div>

  <div class="register-foot">
    <div class="rf-brand">
      <img src="{{ asset('images/iclimate-icon.png') }}" alt="iClimate">
      <div class="rf-brand-text">
        <strong>iClimate</strong>
        <span>&copy; 2026 iClimate Research Group<br>Batangas State University ARASOF-Nasugbu</span>
      </div>
    </div>
    <div class="rf-badges">
      <div class="rf-badge">
        <div class="rf-badge-icon"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 1l6 2v4c0 4-2.6 6.7-6 7.5-3.4-.8-6-3.5-6-7.5V3l6-2z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg></div>
        <div class="rf-badge-text"><strong>Secure &amp; Reliable</strong><span>Your data is safe</span></div>
      </div>
      <div class="rf-badge">
        <div class="rf-badge-icon"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 1c2.4 3.4 4.4 6.2 4.4 8.9A4.4 4.4 0 1 1 3.6 9.9C3.6 7.2 5.6 4.4 8 1z" stroke="currentColor" stroke-width="1.4"/></svg></div>
        <div class="rf-badge-text"><strong>Local &amp; Relevant</strong><span>For Lian, Batangas</span></div>
      </div>
      <div class="rf-badge">
        <div class="rf-badge-icon"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M2 9C3 5 5.5 3 8 3s5 2 6 6c-1 4-3.5 6-6 6s-5-2-6-6z" stroke="currentColor" stroke-width="1.4"/><circle cx="8" cy="9" r="2" stroke="currentColor" stroke-width="1.3"/></svg></div>
        <div class="rf-badge-text"><strong>Climate-Smart</strong><span>Better decisions</span></div>
      </div>
    </div>
  </div>

<script>
  function checkStrength(val) {
    const bars = [document.getElementById('bar1'), document.getElementById('bar2'), document.getElementById('bar3'), document.getElementById('bar4')];
    const label = document.getElementById('passwordStrengthLabel');
    const password = document.getElementById('password');
    const rules = {
      length: val.length >= 8,
      lowercase: /[a-z]/.test(val),
      uppercase: /[A-Z]/.test(val),
      number: /[0-9]/.test(val),
      symbol: /[^A-Za-z0-9]/.test(val),
    };
    const score = Object.values(rules).filter(Boolean).length;
    const progress = score === 0 ? 0 : Math.min(4, Math.ceil(score * 4 / 5));
    const strength = score === 0 ? 'Not started' : score <= 2 ? 'Weak' : score <= 4 ? 'Almost strong' : 'Strong';
    const cls = score <= 2 ? 'filled-weak' : score <= 4 ? 'filled-ok' : 'filled-strong';

    bars.forEach((bar, index) => {
      bar.className = 'pwd-strength-bar';
      if (index < progress) bar.classList.add(cls);
    });

    Object.entries(rules).forEach(([rule, isMet]) => {
      document.querySelector(`[data-password-rule="${rule}"]`)?.classList.toggle('is-met', isMet);
    });

    if (label) {
      label.textContent = `Password strength: ${strength}`;
      label.className = 'pwd-strength-label';
      if (score > 0) label.classList.add(score <= 2 ? 'is-weak' : score <= 4 ? 'is-ok' : 'is-strong');
    }

    if (password) {
      password.setCustomValidity(score === 5 ? '' : 'Use at least 8 characters with uppercase, lowercase, number, and symbol.');
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.register-form');
    const firstName = document.getElementById('firstName');
    const lastName = document.getElementById('lastName');
    const name = document.getElementById('name');
    const submit = document.getElementById('createAccountBtn');
    const organization = document.getElementById('farmer_organization_id');
    const assignedBarangayGroup = document.getElementById('assignedBarangayGroup');
    const assignedBarangay = document.getElementById('assignedBarangay');
    const manualBarangayGroup = document.getElementById('manualBarangayGroup');
    const manualBarangay = document.getElementById('barangay');

    const syncName = () => {
      name.value = `${firstName.value.trim()} ${lastName.value.trim()}`.trim();
    };

    const bindToggle = (buttonId, inputId) => {
      const button = document.getElementById(buttonId);
      const input = document.getElementById(inputId);
      button?.addEventListener('click', () => {
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        button.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
      });
    };

    firstName?.addEventListener('input', syncName);
    lastName?.addEventListener('input', syncName);
    syncName();
    checkStrength(document.getElementById('password')?.value || '');

    const syncFarmLocation = () => {
      const selected = organization?.selectedOptions?.[0];
      const barangay = selected?.dataset?.barangay || '';
      const hasOrganization = Boolean(organization?.value);

      assignedBarangay.value = barangay;
      assignedBarangayGroup.style.display = hasOrganization ? '' : 'none';
      manualBarangayGroup.style.display = hasOrganization ? 'none' : '';
      manualBarangay.disabled = hasOrganization;
      manualBarangay.required = ! hasOrganization;
    };

    organization?.addEventListener('change', syncFarmLocation);
    syncFarmLocation();
    bindToggle('pwdToggle', 'password');
    bindToggle('confirmPwdToggle', 'confirmPassword');

    form?.addEventListener('submit', () => {
      syncName();
      submit.disabled = true;
      submit.innerHTML = 'Creating account...';
    });
  });
</script>
</body>
</html>
