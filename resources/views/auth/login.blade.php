@php($errors = $errors ?? new \Illuminate\Support\ViewErrorBag)
<!DOCTYPE html>
<html lang="{{ str_replace('_' , '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Sign In | {{ config('app.name', 'iClimate') }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Inter:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    html, body { margin: 0; min-height: 100%; }
    body { font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
    a { color: inherit; }
    button, input { font: inherit; }
    .login-brand { display: inline-flex; align-items: center; gap: 12px; color: #fff; text-decoration: none; }
    .login-brand-mark { width: 42px; height: 42px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #52B788, #1A3A2A); color: #fff; font-family: 'DM Serif Display', Georgia, serif; font-size: .92rem; font-weight: 400; box-shadow: 0 16px 36px rgba(82,183,136,.25); }
    .login-brand-copy { display: flex; flex-direction: column; line-height: 1.05; }
    .login-brand-copy strong { font-family: 'DM Serif Display', Georgia, serif; font-size: 1.05rem; font-weight: 400; }
    .login-brand-copy small { margin-top: 3px; color: rgba(255,255,255,.48); font-size: .62rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; }
    .auth-status { background: #e8f5ed; border: 1px solid #b7e4c7; color: #1a3a2a; border-radius: 10px; padding: 11px 13px; font-size: .86rem; margin-bottom: 18px; }
    .field-error { color: #b42318; font-size: .78rem; font-weight: 600; margin-top: 7px; }
    .form-input.is-invalid { border-color: #d92d20; box-shadow: 0 0 0 3px rgba(217,45,32,.10); }
    .btn-login:disabled { opacity: .75; cursor: wait; transform: none; }
    .auth-logo-img { height: 48px; width: auto; max-width: 220px; object-fit: contain; display: block; }
    /* -- LOGIN PAGE OVERRIDE ---------------------------- */
    body.login-page {
      background: #0f1f17;
      min-height: 100vh;
      display: flex;
      align-items: stretch;
    }

    .login-layout {
      display: grid;
      grid-template-columns: 38% 62%;
      min-height: 100vh;
      width: 100%;
    }

    /* -- LEFT PANEL ------------------------------------ */
    .login-panel-left {
      background: linear-gradient(145deg, #0f1f17 0%, #1a3a2a 60%, #163324 100%);
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      padding: 0;
    }

    /* Ambient glow blobs */
    .lpl-blob {
      position: absolute;
      border-radius: 50%;
      filter: blur(80px);
      pointer-events: none;
    }
    .lpl-blob-1 {
      width: 420px; height: 420px;
      background: rgba(82,183,136,0.13);
      top: -80px; left: -80px;
    }
    .lpl-blob-2 {
      width: 320px; height: 320px;
      background: rgba(45,106,79,0.25);
      bottom: 60px; right: -60px;
    }
    .lpl-blob-3 {
      width: 200px; height: 200px;
      background: rgba(149,213,178,0.08);
      bottom: 200px; left: 40%;
    }

    /* Fine dot grid */
    .lpl-grid {
      position: absolute;
      inset: 0;
      background-image: radial-gradient(rgba(82,183,136,0.18) 1px, transparent 1px);
      background-size: 32px 32px;
      pointer-events: none;
    }

    .lpl-inner {
      position: relative;
      z-index: 1;
      display: flex;
      flex-direction: column;
      height: 100%;
      padding: 44px 52px;
    }

    /* Logo */
    .lpl-logo {
      display: flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
      margin-bottom: auto;
    }
    .lpl-logo-text {
      font-family: 'DM Serif Display', Georgia, serif;
      font-size: 1.2rem;
      color: rgba(255,255,255,0.6);
      letter-spacing: -0.01em;
    }
    .lpl-logo-text strong { color: #fff; font-weight: 800; }

    /* Main content */
    .lpl-body {
      padding: 48px 0;
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .lpl-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(82,183,136,0.12);
      border: 1px solid rgba(82,183,136,0.25);
      border-radius: 100px;
      padding: 6px 14px 6px 8px;
      margin-bottom: 28px;
      width: fit-content;
    }
    .lpl-badge-dot {
      width: 7px; height: 7px;
      background: #52B788;
      border-radius: 50%;
      box-shadow: 0 0 8px rgba(82,183,136,0.7);
    }
    .lpl-badge span {
      font-family: 'DM Mono', monospace;
      font-size: 0.7rem;
      font-weight: 500;
      color: #95D5B2;
      letter-spacing: 0.05em;
      text-transform: uppercase;
    }

    .lpl-headline {
      font-family: 'DM Serif Display', Georgia, serif;
      font-size: clamp(1.8rem, 3vw, 2.6rem);
      font-weight: 400;
      color: #fff;
      line-height: 1.15;
      margin-bottom: 16px;
    }
    .lpl-headline em {
      font-style: italic;
      color: #74C69D;
    }

    .lpl-sub {
      font-size: 0.97rem;
      color: rgba(255,255,255,0.45);
      line-height: 1.65;
      max-width: 360px;
      margin-bottom: 48px;
    }

    /* Stats row */
    .lpl-metrics {
      display: flex;
      gap: 0;
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 16px;
      overflow: hidden;
      background: rgba(255,255,255,0.03);
      backdrop-filter: blur(8px);
      margin-bottom: 40px;
    }
    .lpl-metric {
      flex: 1;
      padding: 20px 22px;
      border-right: 1px solid rgba(255,255,255,0.07);
    }
    .lpl-metric:last-child { border-right: none; }
    .lpl-metric-val {
      font-family: 'DM Serif Display', Georgia, serif;
      font-size: 1.5rem;
      font-weight: 400;
      color: #fff;
      line-height: 1;
      margin-bottom: 4px;
    }
    .lpl-metric-label {
      font-family: 'DM Mono', monospace;
      font-size: 0.68rem;
      color: rgba(255,255,255,0.35);
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }

    /* Login tips */
    .lpl-tips {
      display: flex;
      flex-direction: column;
      gap: 14px;
    }
    .lpl-tip {
      display: flex;
      align-items: flex-start;
      gap: 12px;
    }
    .lpl-tip-icon {
      width: 32px; height: 32px;
      border-radius: 8px;
      background: rgba(232,167,61,0.12);
      border: 1px solid rgba(232,167,61,0.3);
      color: #E8A73D;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      font-family: 'DM Mono', monospace;
      font-size: 0.56rem;
      font-weight: 500;
      letter-spacing: 0.03em;
    }
    .lpl-tip-text {
      font-size: 0.88rem;
      color: rgba(255,255,255,0.48);
      line-height: 1.55;
      padding-top: 6px;
    }

    /* Bottom strip */
    .lpl-foot {
      padding-top: 32px;
      border-top: 1px solid rgba(255,255,255,0.07);
      font-size: 0.8rem;
      color: rgba(255,255,255,0.25);
    }

    /* -- RIGHT PANEL ----------------------------------- */
    .login-panel-right {
      background: #f5f9f6;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 48px;
    }

    .login-form-wrap {
      width: 100%;
      max-width: 520px;
    }

    /* Back link */
    .back-link-top {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 0.83rem;
      font-weight: 500;
      color: #5A7A64;
      text-decoration: none;
      margin-bottom: 36px;
      transition: color 0.2s;
    }
    .back-link-top:hover { color: #2D6A4F; }

    /* Form header */
    .login-form-header {
      margin-bottom: 36px;
    }
    .login-form-header h1 {
      font-family: 'DM Serif Display', Georgia, serif;
      font-size: 1.9rem;
      font-weight: 400;
      color: #1B2B23;
      margin-bottom: 6px;
      letter-spacing: -0.02em;
    }
    .login-form-header p {
      font-size: 0.92rem;
      color: #5A7A64;
    }

    /* Form fields */
    .form-group { margin-bottom: 18px; }
    .form-group label {
      display: block;
      font-size: 0.82rem;
      font-weight: 600;
      color: #1B2B23;
      margin-bottom: 7px;
      letter-spacing: 0.01em;
    }

    .input-wrap { position: relative; }

    .form-input {
      width: 100%;
      background: #fff;
      border: 1.5px solid #D4EDDA;
      border-radius: 10px;
      padding: 12px 14px;
      font-family: 'Inter', sans-serif;
      font-size: 0.93rem;
      color: #1B2B23;
      transition: all 0.2s;
      outline: none;
    }
    .form-input.has-icon { padding-left: 42px; }
    .form-input:focus {
      border-color: #52B788;
      box-shadow: 0 0 0 3px rgba(82,183,136,0.14);
      background: #fff;
    }
    .form-input::placeholder { color: #9DB3A4; }

    .input-icon {
      position: absolute;
      left: 13px;
      top: 50%;
      transform: translateY(-50%);
      pointer-events: none;
    }

    .pwd-toggle {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #6B8F71;
      background: none;
      border: none;
      cursor: pointer;
      padding: 2px;
      display: flex;
      transition: color 0.2s;
    }
    .pwd-toggle:hover { color: #52B788; }

    /* Remember / forgot row */
    .form-row-split {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 24px;
    }
    .checkbox-label {
      display: flex;
      align-items: center;
      gap: 9px;
      font-size: 0.87rem;
      color: #1B2B23;
      cursor: pointer;
      user-select: none;
    }
    .custom-checkbox { display: none; }
    .checkbox-custom {
      width: 17px; height: 17px;
      border: 2px solid #D4EDDA;
      border-radius: 4px;
      background: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      transition: all 0.18s;
    }
    .custom-checkbox:checked + .checkbox-custom {
      background: #52B788;
      border-color: #52B788;
    }
    .custom-checkbox:checked + .checkbox-custom::after {
      content: '';
      width: 9px; height: 5px;
      border: 2px solid white;
      border-top: none; border-right: none;
      transform: rotate(-45deg) translateY(-1px);
      display: block;
    }
    .forgot-link {
      font-size: 0.87rem;
      font-weight: 600;
      color: #52B788;
      text-decoration: none;
      transition: color 0.2s;
    }
    .forgot-link:hover { color: #2D6A4F; }

    /* Sign in button */
    .btn-login {
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 13px;
      background: #1A3A2A;
      color: #fff;
      border: none;
      border-radius: 10px;
      font-family: 'Inter', sans-serif;
      font-size: 0.97rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
      letter-spacing: 0.01em;
      margin-bottom: 20px;
    }
    .btn-login:hover {
      background: #2D6A4F;
      transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(26,58,42,0.22);
    }

    /* Divider */
    .form-divider {
      display: flex;
      align-items: center;
      gap: 12px;
      margin: 4px 0 20px;
      color: #9DB3A4;
      font-size: 0.82rem;
    }
    .form-divider::before, .form-divider::after {
      content: '';
      flex: 1;
      height: 1px;
      background: #D4EDDA;
    }

    /* Demo accounts */
    .demo-section {
      background: #fff;
      border: 1px solid #D4EDDA;
      border-radius: 12px;
      padding: 16px;
      margin-bottom: 24px;
    }
    .demo-section-title {
      font-family: 'DM Mono', monospace;
      font-size: 0.68rem;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #5A7A64;
      margin-bottom: 12px;
    }
    .demo-accounts {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .demo-account {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10px 12px;
      background: #f5f9f6;
      border-radius: 8px;
      cursor: pointer;
      transition: background 0.15s;
    }
    .demo-account:hover { background: #e8f5ed; }
    .demo-account-info { display: flex; flex-direction: column; gap: 2px; }
    .demo-account-role {
      font-size: 0.8rem;
      font-weight: 600;
      color: #1B2B23;
    }
    .demo-account-email {
      font-size: 0.75rem;
      color: #5A7A64;
      font-family: monospace;
    }
    .demo-account-badge {
      font-size: 0.7rem;
      font-weight: 600;
      padding: 3px 8px;
      border-radius: 100px;
      background: #D8F3DC;
      color: #2D6A4F;
    }
    .demo-account-badge.admin {
      background: rgba(82,100,183,0.12);
      color: #3D4F9F;
    }

    /* Register link */
    .login-register {
      text-align: center;
      font-size: 0.88rem;
      color: #5A7A64;
      margin: 0;
    }
    .login-register a {
      color: #52B788;
      font-weight: 600;
      text-decoration: none;
      transition: color 0.2s;
    }
    .login-register a:hover { color: #2D6A4F; }

    /* -- RESPONSIVE ------------------------------------ */
    @media (max-width: 900px) {
      .login-layout { grid-template-columns: 1fr; }
      .login-panel-left { display: none; }
      .login-panel-right { padding: 32px 24px; min-height: 100vh; }
    }
  </style>
</head>
<body class="login-page">

<div class="login-layout">

  <!-- -- LEFT PANEL ------------------------------------ -->
  <div class="login-panel-left">
    <div class="lpl-blob lpl-blob-1"></div>
    <div class="lpl-blob lpl-blob-2"></div>
    <div class="lpl-blob lpl-blob-3"></div>
    <div class="lpl-grid"></div>

    <div class="lpl-inner">
      <a href="{{ url('/') }}" class="lpl-logo">
        <img src="{{ asset('images/iclimate-logo.png') }}" alt="iClimate" class="auth-logo-img">
      </a>

      <div class="lpl-body">
        <div class="lpl-badge">
          <div class="lpl-badge-dot"></div>
          <span>System Online</span>
        </div>

        <h1 class="lpl-headline">
          Weather Impact on<br/>
          <em>Rice Production</em><br/>
          in Lian, Batangas
        </h1>
        <p class="lpl-sub">
          Local climate records, advisories, and rice production information for Lian, Batangas.
        </p>

        <div class="lpl-metrics">
          <div class="lpl-metric">
            <div class="lpl-metric-val">42</div>
            <div class="lpl-metric-label">Farmers</div>
          </div>
          <div class="lpl-metric">
            <div class="lpl-metric-val">Local</div>
            <div class="lpl-metric-label">Climate Records</div>
          </div>
          <div class="lpl-metric">
            <div class="lpl-metric-val">3</div>
            <div class="lpl-metric-label">Barangays</div>
          </div>
        </div>

                <div class="lpl-tips">
          <div class="lpl-tip">
            <div class="lpl-tip-icon">MAIL</div>
            <div class="lpl-tip-text">Enter your registered email and password to access your account.</div>
          </div>
          <div class="lpl-tip">
            <div class="lpl-tip-icon">SAVE</div>
            <div class="lpl-tip-text">Use "Remember me" to stay signed in on trusted devices.</div>
          </div>
          <div class="lpl-tip">
            <div class="lpl-tip-icon">LOCK</div>
            <div class="lpl-tip-text">Click "Forgot Password?" to reset via your registered email.</div>
          </div>
        </div>
      </div>

      <div class="lpl-foot">&copy; 2026 iClimate &middot; Lian, Batangas &middot; For authorized users only</div>
    </div>
  </div>

  <!-- -- RIGHT PANEL ---------------------------------- -->
  <div class="login-panel-right">
    <div class="login-form-wrap">

      <a href="{{ url('/') }}" class="back-link-top">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M11 7H3M6 10L3 7l3-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Back to Home
      </a>

      @if (session('status'))
        <div class="auth-status">{{ session('status') }}</div>
      @endif

      <div class="login-form-header">
        <h1>Welcome back</h1>
        <p>Sign in to your iClimate account to continue</p>
      </div>

      <form method="POST" action="{{ route('login') }}" class="login-form">
        @csrf

      <!-- Email -->
      <div class="form-group">
        <label for="email">Email Address</label>
        <div class="input-wrap">
          <svg class="input-icon" width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="1" y="3" width="14" height="10" rx="2" stroke="#6B8F71" stroke-width="1.5"/><path d="M1 5l7 5 7-5" stroke="#6B8F71" stroke-width="1.5" stroke-linecap="round"/></svg>
          <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-input has-icon @error('email') is-invalid @enderror" placeholder="you@example.com" autocomplete="username" required autofocus/>
        </div>
      </div>
      @error('email')<div class="field-error">{{ $message }}</div>@enderror

      <!-- Password -->
      <div class="form-group">
        <label for="password">Password</label>
        <div class="input-wrap">
          <svg class="input-icon" width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="3" y="7" width="10" height="7" rx="1.5" stroke="#6B8F71" stroke-width="1.5"/><path d="M5 7V5a3 3 0 016 0v2" stroke="#6B8F71" stroke-width="1.5" stroke-linecap="round"/></svg>
          <input type="password" id="password" name="password" class="form-input has-icon @error('password') is-invalid @enderror" placeholder="Enter your password" autocomplete="current-password" required/>
          <button class="pwd-toggle" id="pwdToggle" type="button" aria-label="Show password">
            <svg id="eyeIcon" width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M1 8C2.5 4.5 5 3 8 3s5.5 1.5 7 5c-1.5 3.5-4 5-7 5S2.5 11.5 1 8z" stroke="#6B8F71" stroke-width="1.5"/><circle cx="8" cy="8" r="2" stroke="#6B8F71" stroke-width="1.5"/></svg>
          </button>
        </div>
      </div>
      @error('password')<div class="field-error">{{ $message }}</div>@enderror

      <!-- Remember / Forgot -->
      <div class="form-row-split">
        <label class="checkbox-label">
          <input type="checkbox" id="remember_me" name="remember" class="custom-checkbox"/>
          <span class="checkbox-custom"></span>
          Remember me
        </label>
        @if (Route::has('password.request'))<a href="{{ route('password.request') }}" class="forgot-link">Forgot Password?</a>@endif
      </div>

      <!-- Sign In -->
      <button class="btn-login" id="loginBtn" type="submit">
        Sign In
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>
      </form>

      <!-- Demo accounts -->
      <div class="form-divider"><span>demo accounts</span></div>

      <div class="demo-section">
        <div class="demo-section-title">Try the system</div>
<div class="demo-accounts">

  <div class="demo-account" onclick="fillDemo('farmer@iclimate.com','password123')">
    <div class="demo-account-info">
      <span class="demo-account-role">Rice Farmer</span>
      <span class="demo-account-email">farmer@iclimate.com</span>
    </div>
    <span class="demo-account-badge">Farmer</span>
  </div>

  <div class="demo-account" onclick="fillDemo('mao@iclimate.com','password123')">
    <div class="demo-account-info">
      <span class="demo-account-role">MAO Personnel</span>
      <span class="demo-account-email">mao@iclimate.com</span>
    </div>
    <span class="demo-account-badge admin">MAO</span>
  </div>

  <div class="demo-account" onclick="fillDemo('admin@iclimate.com','password123')">
    <div class="demo-account-info">
      <span class="demo-account-role">IT Expert</span>
      <span class="demo-account-email">admin@iclimate.com</span>
    </div>
    <span class="demo-account-badge admin">Admin</span>
  </div>

</div>
      </div>

      <p class="login-register">
        Need an account? <a href="{{ route('register') }}">Register here</a>
      </p>

    </div>
  </div>
</div>

<script>
  function fillDemo(email, pass) {
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    emailInput.value = email;
    passwordInput.value = pass;
  }

  document.addEventListener('DOMContentLoaded', () => {
    const passwordInput = document.getElementById('password');
    const toggle = document.getElementById('pwdToggle');
    const form = document.querySelector('.login-form');
    const loginBtn = document.getElementById('loginBtn');

    toggle?.addEventListener('click', () => {
      const isPassword = passwordInput.type === 'password';
      passwordInput.type = isPassword ? 'text' : 'password';
      toggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
    });

    form?.addEventListener('submit', () => {
      loginBtn.disabled = true;
      loginBtn.innerHTML = 'Signing in...';
    });
  });
</script>
</body>
</html>
