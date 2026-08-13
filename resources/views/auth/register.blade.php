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
    html, body { margin: 0; min-height: 100%; }
    body { font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
    a { color: inherit; }
    button, input { font: inherit; }
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
      overflow-x: hidden;
    }
    .scene-glow { position: fixed; z-index: 0; pointer-events: none; border-radius: 50%; filter: blur(90px); }
    .scene-glow-1 { width: 420px; height: 420px; background: rgba(95,143,120,.16); top: -110px; right: -70px; }
    .scene-glow-2 { width: 320px; height: 320px; background: rgba(232,167,61,.1); bottom: -50px; left: -50px; }

    .register-main { position: relative; z-index: 1; flex: 1; display: flex; align-items: center; justify-content: center; padding: 28px 20px; }
    .register-grid {
      width: 100%; max-width: 1100px;
      display: grid; grid-template-columns: 42% 58%; gap: 32px; align-items: start;
      animation: authSceneEnter var(--auth-motion-slow) both;
    }

    /* -- LEFT: MARKETING COLUMN -------------------------- */
    .back-link-top {
      display: inline-flex; align-items: center; gap: 6px; font-size: .85rem; font-weight: 600;
      color: #7FD6B5; text-decoration: none; margin-bottom: 18px; transition: color .2s;
    }
    .back-link-top:hover { color: #F6D58A; }

    .reg-logo { display: flex; align-items: center; gap: 10px; margin-bottom: 18px; }
    .reg-logo img { width: 44px; height: auto; display: block; }
    .reg-logo-word { font-family: 'Inter', sans-serif; font-weight: 800; font-size: 1.3rem; color: #fff; letter-spacing: -0.01em; }

    .reg-headline { font-family: 'DM Serif Display', Georgia, serif; font-size: clamp(1.7rem, 2.8vw, 2.2rem); font-weight: 400; color: #fff; line-height: 1.18; margin-bottom: 12px; }
    .reg-headline em { font-style: normal; color: #E8A73D; }
    .reg-sub { font-size: .92rem; color: rgba(255,255,255,.78); line-height: 1.6; margin-bottom: 18px; max-width: 420px; }
    .reg-sub a { color: #E8A73D; font-weight: 600; text-decoration: none; }

    .perk-card {
      background: rgba(31,77,58,.4); border: 1px solid rgba(95,143,120,.5); border-radius: 14px;
      padding: 16px 18px; margin-bottom: 14px;
      transition: transform var(--auth-motion-med), border-color var(--auth-motion-med), background-color var(--auth-motion-med);
    }
    .perk-card:hover { transform: translateY(-3px); border-color: rgba(95,143,120,.72); background: rgba(31,77,58,.55); }
    .perk-header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
    .perk-icon {
      width: 36px; height: 36px; border-radius: 10px; background: rgba(127,214,181,.18); border: 1px solid rgba(127,214,181,.4);
      color: #7FD6B5; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .perk-title { font-family: 'Inter', sans-serif; font-weight: 700; font-size: .93rem; color: #fff; }
    .perk-sub { font-family: 'DM Mono', monospace; font-size: .62rem; letter-spacing: .05em; text-transform: uppercase; color: rgba(127,214,181,.75); margin-top: 2px; }
    .perk-list { display: flex; flex-direction: column; gap: 8px; }
    .perk-item { display: flex; align-items: center; gap: 10px; font-size: .84rem; color: rgba(127,214,181,.92); }
    .perk-check { width: 17px; height: 17px; background: rgba(127,214,181,.25); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: #7FD6B5; }

    .notice-card {
      display: flex; gap: 10px; padding: 12px 14px; background: rgba(22,59,45,.6);
      border: 1px solid rgba(95,143,120,.4); border-radius: 12px;
      transition: transform var(--auth-motion-med), border-color var(--auth-motion-med), background-color var(--auth-motion-med);
    }
    .notice-card:hover { transform: translateY(-3px); border-color: rgba(95,143,120,.6); background: rgba(22,59,45,.75); }
    .notice-icon { flex-shrink: 0; margin-top: 1px; color: #E8A73D; }
    .notice-card p { font-size: .82rem; color: rgba(255,255,255,.75); line-height: 1.5; margin: 0; }
    .notice-card strong { color: #F6D58A; font-weight: 700; }

    /* -- RIGHT: FORM CARD --------------------------------- */
    .form-card {
      background: rgba(255,255,255,.98); border: 1.5px solid rgba(95,143,120,.4); border-radius: 20px;
      box-shadow: 0 26px 60px rgba(13,31,24,.24); backdrop-filter: blur(18px); padding: 26px 30px;
      transition: transform var(--auth-motion-med), box-shadow var(--auth-motion-med), border-color var(--auth-motion-med), background-color var(--auth-motion-med);
      transform: translateZ(0);
    }
    .form-card:hover { transform: translateY(-4px); box-shadow: 0 32px 72px rgba(13,31,24,.3); border-color: rgba(95,143,120,.6); }
    .form-card-header { display: flex; align-items: center; gap: 12px; margin-bottom: 6px; }
    .form-card-icon {
      width: 40px; height: 40px; border-radius: 50%; background: rgba(95,143,120,.16); border: 1px solid rgba(95,143,120,.35);
      color: #1F4D3A; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .form-card-header h1 { font-family: 'DM Serif Display', Georgia, serif; font-size: 1.5rem; font-weight: 400; color: #163B2D; letter-spacing: -0.01em; }
    .form-card-sub { font-size: .86rem; color: #64748B; margin: 0 0 14px; }

    .role-pill {
      display: inline-flex; align-items: center; gap: 8px; background: rgba(127,214,181,.18);
      border: 1px solid rgba(127,214,181,.4); border-radius: 100px; padding: 5px 14px 5px 8px; margin-bottom: 16px;
    }
    .role-pill-dot { width: 20px; height: 20px; background: #7FD6B5; color: #163B2D; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-family: 'DM Mono', monospace; font-size: .6rem; font-weight: 700; }
    .role-pill span { font-size: .8rem; font-weight: 600; color: #163B2D; }

    .form-group { margin-bottom: 12px; }
    .form-group label { display: block; font-size: .8rem; font-weight: 600; color: #1F2937; margin-bottom: 5px; }
    .form-row-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .input-wrap { position: relative; }
    .form-input {
      width: 100%; background: #fff; border: 1.5px solid rgba(95,143,120,.45); border-radius: 11px;
      padding: 10px 14px; font-family: 'Inter', sans-serif; font-size: .92rem; color: #1F2937; transition: border-color var(--auth-motion-med), box-shadow var(--auth-motion-med), background-color var(--auth-motion-med), transform var(--auth-motion-med); outline: none;
    }
    .form-input.has-icon { padding-left: 40px; }
    .form-input:focus { border-color: #1F4D3A; box-shadow: 0 0 0 3px rgba(31,77,58,.18); background: #fff; transform: translateY(-1px); }
    .form-input::placeholder { color: #64748B; }
    .input-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #1F4D3A; }
    .pwd-toggle { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #64748B; background: none; border: none; cursor: pointer; padding: 2px; display: flex; transition: color .2s; }
    .pwd-toggle:hover { color: #1F4D3A; }
    .captcha-card {
      display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 10px; align-items: center;
      background: #EDF6F1; border: 1.5px solid #5F8F78; border-radius: 12px;
      padding: 10px 12px; margin-bottom: 12px;
    }
    .captcha-icon {
      width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center;
      background: rgba(95,143,120,.18); color: #1F4D3A; border: 1px solid rgba(95,143,120,.35);
    }
    .captcha-label { font-size: .76rem; font-weight: 800; color: #1F2937; margin-bottom: 5px; }
    .captcha-image {
      display: block; width: 100%; height: 64px; border-radius: 13px;
      border: 1px solid rgba(95,143,120,.4); margin-bottom: 8px; background: #ffffff;
    }

    .pwd-strength { margin-top: 7px; display: flex; gap: 4px; }
    .pwd-strength-bar { height: 3px; flex: 1; border-radius: 2px; background: rgba(95,143,120,.25); transition: background .3s; }
    .pwd-strength-bar.filled-weak { background: #e57a68; }
    .pwd-strength-bar.filled-ok { background: #F6D58A; }
    .pwd-strength-bar.filled-strong { background: #1F4D3A; }

    .terms-row {
      display: flex; align-items: flex-start; gap: 10px; margin: 14px 0; padding: 12px;
      background: rgba(95,143,120,.08); border: 1.5px solid rgba(95,143,120,.3); border-radius: 12px;
    }
    .custom-checkbox { display: none; }
    .checkbox-custom {
      width: 17px; height: 17px; border: 2px solid rgba(95,143,120,.5); border-radius: 5px; background: #fff;
      display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px; transition: all .18s; cursor: pointer;
    }
    .custom-checkbox:checked + .checkbox-custom { background: #1F4D3A; border-color: #1F4D3A; }
    .custom-checkbox:checked + .checkbox-custom::after { content: ''; width: 9px; height: 5px; border: 2px solid white; border-top: none; border-right: none; transform: rotate(-45deg) translateY(-1px); display: block; }
    .terms-text { font-size: .82rem; color: #64748B; line-height: 1.5; }
    .terms-text a { color: #1F4D3A; font-weight: 600; text-decoration: none; }
    .terms-text a:hover { color: #163B2D; }

    #formMessage { font-size: .84rem; color: #c0392b; background: rgba(229,122,104,.1); border: 1px solid rgba(229,122,104,.28); border-radius: 8px; padding: 9px 14px; margin-bottom: 12px; }

    .btn-login {
      width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px;
      background: #E8A73D; color: #1F2937; border: none; border-radius: 11px; font-family: 'Inter', sans-serif;
      font-size: .96rem; font-weight: 800; cursor: pointer; transition: transform var(--auth-motion-med), box-shadow var(--auth-motion-med), background-color var(--auth-motion-med); margin-bottom: 14px;
      box-shadow: 0 10px 24px rgba(232,167,61,.28);
    }
    .btn-login:hover { background: #F6D58A; transform: translateY(-3px); box-shadow: 0 16px 34px rgba(232,167,61,.42); }
    .btn-login:active { transform: translateY(0) scale(.985); }

    .login-register { text-align: center; font-size: .86rem; color: #64748B; }
    .login-register a { color: #E8A73D; font-weight: 700; text-decoration: none; transition: color .2s; }
    .login-register a:hover { color: #C6872A; }

    @keyframes authSceneEnter { from { opacity: 0; transform: translateY(16px) scale(.992); filter: blur(2px); } to { opacity: 1; transform: translateY(0) scale(1); filter: blur(0); } }

    /* -- FOOTER BAR --------------------------------------- */
    .register-foot {
      position: relative; z-index: 1;
      background: rgba(22,59,45,.92); border-top: 1px solid rgba(95,143,120,.3);
      padding: 14px 32px; display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap;
    }
    .rf-brand { display: flex; align-items: center; gap: 10px; }
    .rf-brand img { width: 26px; height: 26px; object-fit: contain; }
    .rf-brand-text { display: flex; flex-direction: column; line-height: 1.3; }
    .rf-brand-text strong { font-family: 'Inter', sans-serif; font-weight: 800; font-size: .9rem; color: #E8A73D; }
    .rf-brand-text span { font-size: .68rem; color: rgba(127,214,181,.75); }
    .rf-badges { display: flex; gap: 22px; flex-wrap: wrap; }
    .rf-badge { display: flex; align-items: center; gap: 8px; }
    .rf-badge-icon { width: 28px; height: 28px; border-radius: 50%; background: rgba(95,143,120,.18); border: 1px solid rgba(95,143,120,.4); color: #5F8F78; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .rf-badge-text { display: flex; flex-direction: column; line-height: 1.25; }
    .rf-badge-text strong { font-size: .78rem; color: #fff; font-weight: 700; }
    .rf-badge-text span { font-size: .68rem; color: rgba(127,214,181,.75); }

    /* -- RESPONSIVE ---------------------------------------- */
    @media (max-width: 980px) {
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
    let score = 0;
    if (val.length >= 8) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const cls = score <= 1 ? 'filled-weak' : score <= 2 ? 'filled-ok' : 'filled-strong';
    bars.forEach((bar, index) => {
      bar.className = 'pwd-strength-bar';
      if (index < score) bar.classList.add(cls);
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.register-form');
    const firstName = document.getElementById('firstName');
    const lastName = document.getElementById('lastName');
    const name = document.getElementById('name');
    const submit = document.getElementById('createAccountBtn');

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
