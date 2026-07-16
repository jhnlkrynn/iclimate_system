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
    html, body { margin: 0; min-height: 100%; }
    body { font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
    a { color: inherit; }
    button, input { font: inherit; }
    .field-error { color: #ffb4a6; font-size: .78rem; font-weight: 600; margin-top: 7px; }
    .form-input.is-invalid { border-color: #e57a68; box-shadow: 0 0 0 3px rgba(229,122,104,.14); }
    .btn-login:disabled { opacity: .75; cursor: wait; transform: none; }

    /* -- SCENE BACKGROUND ------------------------------- */
    body.register-page {
      min-height: 100vh;
      background:
        linear-gradient(180deg, rgba(6,16,12,.55), rgba(6,16,12,.82)),
        linear-gradient(160deg, #0f2318 0%, #1a3a2a 45%, #2d5a3f 75%, #142e20 100%);
      display: flex;
      flex-direction: column;
      position: relative;
      overflow-x: hidden;
    }
    .scene-hills {
      position: fixed; inset: 0; z-index: 0; pointer-events: none;
      background-image:
        url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1600 900' preserveAspectRatio='none'%3E%3Cpath d='M0 620 Q250 540 500 600 T1000 580 T1600 610 V900 H0Z' fill='%23234a34' fill-opacity='0.55'/%3E%3Cpath d='M0 700 Q300 650 600 690 T1200 680 T1600 700 V900 H0Z' fill='%231b3a29' fill-opacity='0.65'/%3E%3Cpath d='M0 780 Q350 740 700 770 T1400 760 T1600 780 V900 H0Z' fill='%2513291d' fill-opacity='0.8'/%3E%3C/svg%3E");
      background-size: cover;
      background-position: bottom;
    }
    .scene-glow { position: fixed; z-index: 0; pointer-events: none; border-radius: 50%; filter: blur(90px); }
    .scene-glow-1 { width: 480px; height: 480px; background: rgba(82,183,136,.16); top: -120px; right: -80px; }
    .scene-glow-2 { width: 360px; height: 360px; background: rgba(232,167,61,.1); bottom: -60px; left: -60px; }

    .register-main { position: relative; z-index: 1; flex: 1; display: flex; align-items: center; justify-content: center; padding: 48px 24px; }
    .register-grid {
      width: 100%; max-width: 1180px;
      display: grid; grid-template-columns: 42% 58%; gap: 48px; align-items: start;
    }

    /* -- LEFT: MARKETING COLUMN -------------------------- */
    .back-link-top {
      display: inline-flex; align-items: center; gap: 6px; font-size: .87rem; font-weight: 600;
      color: #95D5B2; text-decoration: none; margin-bottom: 30px; transition: color .2s;
    }
    .back-link-top:hover { color: #74C69D; }

    .reg-logo { margin-bottom: 26px; }
    .reg-logo img { width: 92px; height: auto; display: block; margin-bottom: 8px; }
    .reg-logo-word { font-family: 'Inter', sans-serif; font-weight: 800; font-size: 1.5rem; color: #74C69D; letter-spacing: -0.01em; }
    .reg-logo-underline { width: 56px; height: 2px; background: #52B788; border-radius: 2px; position: relative; margin-top: 6px; }
    .reg-logo-underline::after { content: ''; position: absolute; right: -3px; top: 50%; transform: translateY(-50%); width: 6px; height: 6px; border-radius: 50%; background: #74C69D; }

    .reg-headline { font-family: 'DM Serif Display', Georgia, serif; font-size: clamp(1.9rem, 3vw, 2.5rem); font-weight: 400; color: #fff; line-height: 1.18; margin-bottom: 16px; }
    .reg-headline em { font-style: normal; color: #74C69D; }
    .reg-sub { font-size: .95rem; color: rgba(255,255,255,.55); line-height: 1.65; margin-bottom: 28px; max-width: 420px; }
    .reg-sub a { color: #E8A73D; font-weight: 600; text-decoration: none; }

    .perk-card {
      background: rgba(82,183,136,.1); border: 1px solid rgba(82,183,136,.24); border-radius: 16px;
      padding: 20px 22px; margin-bottom: 20px;
    }
    .perk-header { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
    .perk-icon {
      width: 38px; height: 38px; border-radius: 10px; background: rgba(82,183,136,.18); border: 1px solid rgba(82,183,136,.35);
      color: #74C69D; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .perk-title { font-family: 'Inter', sans-serif; font-weight: 700; font-size: .95rem; color: #fff; }
    .perk-sub { font-family: 'DM Mono', monospace; font-size: .64rem; letter-spacing: .05em; text-transform: uppercase; color: rgba(255,255,255,.4); margin-top: 2px; }
    .perk-list { display: flex; flex-direction: column; gap: 9px; }
    .perk-item { display: flex; align-items: center; gap: 10px; font-size: .86rem; color: rgba(255,255,255,.72); }
    .perk-check { width: 18px; height: 18px; background: rgba(82,183,136,.25); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: #74C69D; }

    .notice-card {
      display: flex; gap: 10px; padding: 15px 16px; background: rgba(232,167,61,.08);
      border: 1px solid rgba(232,167,61,.22); border-radius: 12px;
    }
    .notice-icon { flex-shrink: 0; margin-top: 1px; color: #E8A73D; }
    .notice-card p { font-size: .83rem; color: rgba(255,255,255,.6); line-height: 1.55; margin: 0; }
    .notice-card strong { color: #f0c674; font-weight: 700; }

    /* -- RIGHT: FORM CARD --------------------------------- */
    .form-card {
      background: rgba(15,31,23,.62); border: 1.5px solid rgba(149,213,178,.22); border-radius: 22px;
      box-shadow: 0 30px 70px rgba(0,0,0,.38); backdrop-filter: blur(18px); padding: 38px 40px;
    }
    .form-card-header { display: flex; align-items: center; gap: 14px; margin-bottom: 8px; }
    .form-card-icon {
      width: 44px; height: 44px; border-radius: 50%; background: rgba(82,183,136,.16); border: 1px solid rgba(82,183,136,.35);
      color: #74C69D; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .form-card-header h1 { font-family: 'DM Serif Display', Georgia, serif; font-size: 1.65rem; font-weight: 400; color: #fff; letter-spacing: -0.01em; }
    .form-card-sub { font-size: .88rem; color: rgba(255,255,255,.5); margin: 0 0 20px; }

    .role-pill {
      display: inline-flex; align-items: center; gap: 8px; background: rgba(82,183,136,.16);
      border: 1px solid rgba(82,183,136,.35); border-radius: 100px; padding: 6px 14px 6px 8px; margin-bottom: 26px;
    }
    .role-pill-dot { width: 22px; height: 22px; background: #52B788; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-family: 'DM Mono', monospace; font-size: .62rem; font-weight: 700; }
    .role-pill span { font-size: .82rem; font-weight: 600; color: #d8f3dc; }

    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; font-size: .82rem; font-weight: 600; color: rgba(255,255,255,.82); margin-bottom: 6px; }
    .form-row-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .input-wrap { position: relative; }
    .form-input {
      width: 100%; background: rgba(255,255,255,.06); border: 1.5px solid rgba(149,213,178,.28); border-radius: 12px;
      padding: 11px 14px; font-family: 'Inter', sans-serif; font-size: .93rem; color: #fff; transition: all .2s; outline: none;
    }
    .form-input.has-icon { padding-left: 40px; }
    .form-input:focus { border-color: #52B788; box-shadow: 0 0 0 3px rgba(82,183,136,.2); background: rgba(255,255,255,.09); }
    .form-input::placeholder { color: rgba(255,255,255,.35); }
    .input-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #74C69D; }
    .pwd-toggle { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,.55); background: none; border: none; cursor: pointer; padding: 2px; display: flex; transition: color .2s; }
    .pwd-toggle:hover { color: #74C69D; }

    .pwd-strength { margin-top: 8px; display: flex; gap: 4px; }
    .pwd-strength-bar { height: 3px; flex: 1; border-radius: 2px; background: rgba(149,213,178,.25); transition: background .3s; }
    .pwd-strength-bar.filled-weak { background: #e57a68; }
    .pwd-strength-bar.filled-ok { background: #f0c674; }
    .pwd-strength-bar.filled-strong { background: #52B788; }

    .terms-row {
      display: flex; align-items: flex-start; gap: 10px; margin: 20px 0; padding: 14px;
      background: rgba(255,255,255,.05); border: 1.5px solid rgba(149,213,178,.22); border-radius: 12px;
    }
    .custom-checkbox { display: none; }
    .checkbox-custom {
      width: 18px; height: 18px; border: 2px solid rgba(149,213,178,.4); border-radius: 5px; background: rgba(255,255,255,.05);
      display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px; transition: all .18s; cursor: pointer;
    }
    .custom-checkbox:checked + .checkbox-custom { background: #52B788; border-color: #52B788; }
    .custom-checkbox:checked + .checkbox-custom::after { content: ''; width: 9px; height: 5px; border: 2px solid white; border-top: none; border-right: none; transform: rotate(-45deg) translateY(-1px); display: block; }
    .terms-text { font-size: .83rem; color: rgba(255,255,255,.62); line-height: 1.5; }
    .terms-text a { color: #74C69D; font-weight: 600; text-decoration: none; }
    .terms-text a:hover { color: #95D5B2; }

    #formMessage { font-size: .84rem; color: #ffb4a6; background: rgba(229,122,104,.1); border: 1px solid rgba(229,122,104,.28); border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; }

    .btn-login {
      width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 14px;
      background: #E8A73D; color: #1a3a2a; border: none; border-radius: 12px; font-family: 'Inter', sans-serif;
      font-size: .98rem; font-weight: 800; cursor: pointer; transition: all .2s; margin-bottom: 18px;
      box-shadow: 0 10px 28px rgba(232,167,61,.28);
    }
    .btn-login:hover { background: #f0b559; transform: translateY(-1px); box-shadow: 0 12px 32px rgba(232,167,61,.4); }

    .login-register { text-align: center; font-size: .88rem; color: rgba(255,255,255,.55); }
    .login-register a { color: #E8A73D; font-weight: 700; text-decoration: none; transition: color .2s; }
    .login-register a:hover { color: #f0b559; }

    /* -- FOOTER BAR --------------------------------------- */
    .register-foot {
      position: relative; z-index: 1;
      background: rgba(9,20,15,.75); border-top: 1px solid rgba(149,213,178,.15);
      padding: 18px 40px; display: flex; align-items: center; justify-content: space-between; gap: 24px; flex-wrap: wrap;
    }
    .rf-brand { display: flex; align-items: center; gap: 12px; }
    .rf-brand img { width: 32px; height: 32px; object-fit: contain; }
    .rf-brand-text { display: flex; flex-direction: column; line-height: 1.3; }
    .rf-brand-text strong { font-family: 'Inter', sans-serif; font-weight: 800; font-size: .92rem; color: #74C69D; }
    .rf-brand-text span { font-size: .7rem; color: rgba(255,255,255,.4); }
    .rf-badges { display: flex; gap: 26px; flex-wrap: wrap; }
    .rf-badge { display: flex; align-items: center; gap: 8px; }
    .rf-badge-icon { width: 30px; height: 30px; border-radius: 50%; background: rgba(82,183,136,.14); border: 1px solid rgba(82,183,136,.3); color: #74C69D; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .rf-badge-text { display: flex; flex-direction: column; line-height: 1.25; }
    .rf-badge-text strong { font-size: .8rem; color: #fff; font-weight: 700; }
    .rf-badge-text span { font-size: .7rem; color: rgba(255,255,255,.4); }

    /* -- RESPONSIVE ---------------------------------------- */
    @media (max-width: 980px) {
      .register-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 540px) {
      .form-card { padding: 28px 22px; }
      .form-row-2col { grid-template-columns: 1fr; gap: 0; }
      .register-foot { flex-direction: column; align-items: flex-start; }
    }
  </style>
</head>
<body class="register-page">
  <div class="scene-hills"></div>
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
          <img src="{{ asset('images/iclimate-logo.png') }}" alt="iClimate">
          <div class="reg-logo-word">iClimate</div>
          <div class="reg-logo-underline"></div>
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
      <img src="{{ asset('images/iclimate-logo.png') }}" alt="iClimate">
      <div class="rf-brand-text">
        <strong>iClimate</strong>
        <span>&copy; 2026 iClimate Research Group<br>Batangas State University ARASOF-Masungki</span>
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
