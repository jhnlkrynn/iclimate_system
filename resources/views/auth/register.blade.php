@php($errors = $errors ?? new \Illuminate\Support\ViewErrorBag)
<!DOCTYPE html>
<html lang="{{ str_replace('_' , '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Create Account | {{ config('app.name', 'iClimate') }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Inter:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    html, body { margin: 0; min-height: 100%; }
    body { font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
    a { color: inherit; }
    button, input { font: inherit; }
    .register-brand { display: inline-flex; align-items: center; gap: 12px; color: #fff; text-decoration: none; }
    .register-brand-mark { width: 42px; height: 42px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #52B788, #1A3A2A); color: #fff; font-family: 'DM Serif Display', Georgia, serif; font-size: .92rem; font-weight: 400; box-shadow: 0 16px 36px rgba(82,183,136,.25); }
    .register-brand-copy { display: flex; flex-direction: column; line-height: 1.05; }
    .register-brand-copy strong { font-family: 'DM Serif Display', Georgia, serif; font-size: 1.05rem; font-weight: 400; }
    .register-brand-copy small { margin-top: 3px; color: rgba(255,255,255,.48); font-size: .62rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; }
    .field-error { color: #b42318; font-size: .78rem; font-weight: 600; margin-top: 7px; }
    .form-input.is-invalid { border-color: #d92d20; box-shadow: 0 0 0 3px rgba(217,45,32,.10); }
    .btn-login:disabled { opacity: .75; cursor: wait; transform: none; }
    .auth-logo-img { height: 48px; width: auto; max-width: 220px; object-fit: contain; display: block; }
    /* -- CREATE ACCOUNT PAGE --------------------------- */
    body.login-page {
      background: #0f1f17;
      min-height: 100vh;
      display: flex;
      align-items: stretch;
    }

    .login-layout {
      display: grid;
      grid-template-columns: 44% 56%;
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
    }

    .lpl-blob {
      position: absolute;
      border-radius: 50%;
      filter: blur(80px);
      pointer-events: none;
    }
    .lpl-blob-1 { width: 380px; height: 380px; background: rgba(82,183,136,0.13); top: -60px; right: -60px; }
    .lpl-blob-2 { width: 260px; height: 260px; background: rgba(45,106,79,0.3); bottom: 80px; left: -40px; }

    .lpl-grid {
      position: absolute;
      inset: 0;
      background-image: radial-gradient(rgba(82,183,136,0.15) 1px, transparent 1px);
      background-size: 32px 32px;
      pointer-events: none;
    }

    .lpl-inner {
      position: relative;
      z-index: 1;
      display: flex;
      flex-direction: column;
      height: 100%;
      padding: 44px 48px;
    }

    .lpl-logo {
      display: flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
      margin-bottom: 48px;
    }
    .lpl-logo-text {
      font-family: 'DM Serif Display', Georgia, serif;
      font-size: 1.2rem;
      color: rgba(255,255,255,0.6);
    }
    .lpl-logo-text strong { color: #fff; font-weight: 400; }

    .lpl-body { flex: 1; }

    .lpl-eyebrow {
      font-family: 'DM Mono', monospace;
      font-size: 0.7rem;
      font-weight: 500;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: #52B788;
      margin-bottom: 16px;
    }

    .lpl-headline {
      font-family: 'DM Serif Display', Georgia, serif;
      font-size: clamp(1.6rem, 2.5vw, 2.1rem);
      font-weight: 400;
      color: #fff;
      line-height: 1.18;
      margin-bottom: 12px;
    }
    .lpl-sub {
      font-size: 0.9rem;
      color: rgba(255,255,255,0.42);
      line-height: 1.6;
      margin-bottom: 36px;
    }

    /* Account type card */
    .account-type-card {
      background: rgba(82,183,136,0.08);
      border: 1px solid rgba(82,183,136,0.2);
      border-radius: 14px;
      padding: 22px;
      margin-bottom: 28px;
    }
    .atc-header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 16px;
    }
    .atc-icon {
      width: 40px; height: 40px;
      background: rgba(232,167,61,0.14);
      border: 1px solid rgba(232,167,61,0.3);
      color: #E8A73D;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
      flex-shrink: 0;
    }
    .atc-title {
      font-family: 'DM Serif Display', Georgia, serif;
      font-size: 1.02rem;
      font-weight: 400;
      color: #fff;
      margin-bottom: 2px;
    }
    .atc-subtitle {
      font-family: 'DM Mono', monospace;
      font-size: 0.65rem;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.4);
    }
    .atc-perks {
      display: flex;
      flex-direction: column;
      gap: 9px;
    }
    .atc-perk {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 0.85rem;
      color: rgba(255,255,255,0.6);
    }
    .atc-perk-check {
      width: 18px; height: 18px;
      background: rgba(82,183,136,0.18);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .atc-perk-check svg { display: block; }

    /* Notice */
    .lpl-notice {
      display: flex;
      gap: 10px;
      padding: 14px 16px;
      background: rgba(255,209,102,0.07);
      border: 1px solid rgba(255,209,102,0.18);
      border-radius: 10px;
      margin-bottom: 32px;
    }
    .lpl-notice-icon { flex-shrink: 0; margin-top: 1px; }
    .lpl-notice p {
      font-size: 0.82rem;
      color: rgba(255,255,255,0.45);
      line-height: 1.55;
      margin: 0;
    }
    .lpl-notice strong { color: rgba(255,209,102,0.85); font-weight: 600; }

    .lpl-foot {
      font-size: 0.78rem;
      color: rgba(255,255,255,0.22);
      padding-top: 24px;
      border-top: 1px solid rgba(255,255,255,0.07);
    }

    /* -- RIGHT PANEL ----------------------------------- */
    .login-panel-right {
      background: #f5f9f6;
      display: flex;
      align-items: flex-start;
      justify-content: center;
      padding: 48px 52px;
      overflow-y: auto;
    }

    .login-form-wrap {
      width: 100%;
      max-width: 460px;
      padding: 16px 0;
    }

    .back-link-top {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 0.83rem;
      font-weight: 500;
      color: #5A7A64;
      text-decoration: none;
      margin-bottom: 32px;
      transition: color 0.2s;
    }
    .back-link-top:hover { color: #2D6A4F; }

    .login-form-header {
      margin-bottom: 28px;
    }
    .login-form-header h1 {
      font-family: 'DM Serif Display', Georgia, serif;
      font-size: 1.85rem;
      font-weight: 400;
      color: #1B2B23;
      margin-bottom: 5px;
      letter-spacing: -0.02em;
    }
    .login-form-header p {
      font-size: 0.9rem;
      color: #5A7A64;
    }

    /* Role pill */
    .role-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: #D8F3DC;
      border-radius: 100px;
      padding: 6px 14px 6px 8px;
      margin-bottom: 28px;
    }
    .role-pill-dot {
      width: 22px; height: 22px;
      background: #52B788;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.75rem;
    }
    .role-pill span {
      font-size: 0.82rem;
      font-weight: 600;
      color: #1A3A2A;
    }

    /* Form layout */
    .form-group { margin-bottom: 16px; }
    .form-group label {
      display: block;
      font-size: 0.82rem;
      font-weight: 600;
      color: #1B2B23;
      margin-bottom: 6px;
    }

    .form-row-2col {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px;
    }

    .form-input {
      width: 100%;
      background: #fff;
      border: 1.5px solid #D4EDDA;
      border-radius: 10px;
      padding: 11px 14px;
      font-family: 'Inter', sans-serif;
      font-size: 0.93rem;
      color: #1B2B23;
      transition: all 0.2s;
      outline: none;
    }
    .form-input:focus {
      border-color: #52B788;
      box-shadow: 0 0 0 3px rgba(82,183,136,0.14);
    }
    .form-input::placeholder { color: #9DB3A4; }

    .input-wrap { position: relative; }
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

    /* Password strength */
    .pwd-strength {
      margin-top: 8px;
      display: flex;
      gap: 4px;
    }
    .pwd-strength-bar {
      height: 3px;
      flex: 1;
      border-radius: 2px;
      background: #D4EDDA;
      transition: background 0.3s;
    }
    .pwd-strength-bar.filled-weak { background: #E76F51; }
    .pwd-strength-bar.filled-ok { background: #F6C344; }
    .pwd-strength-bar.filled-strong { background: #52B788; }

    /* Terms */
    .terms-row {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      margin-bottom: 20px;
      padding: 14px;
      background: #fff;
      border: 1.5px solid #D4EDDA;
      border-radius: 10px;
    }
    .custom-checkbox { display: none; }
    .checkbox-custom {
      width: 18px; height: 18px;
      border: 2px solid #D4EDDA;
      border-radius: 4px;
      background: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      margin-top: 2px;
      transition: all 0.18s;
      cursor: pointer;
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
    .terms-text {
      font-size: 0.84rem;
      color: #5A7A64;
      line-height: 1.5;
    }
    .terms-text a { color: #52B788; font-weight: 600; text-decoration: none; }
    .terms-text a:hover { color: #2D6A4F; }

    /* Error message */
    #formMessage {
      font-size: 0.84rem;
      color: #E76F51;
      background: rgba(231,111,81,0.08);
      border: 1px solid rgba(231,111,81,0.2);
      border-radius: 8px;
      padding: 10px 14px;
      margin-bottom: 16px;
    }

    /* Submit button */
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
      margin-bottom: 20px;
    }
    .btn-login:hover {
      background: #2D6A4F;
      transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(26,58,42,0.22);
    }

    .login-register {
      text-align: center;
      font-size: 0.88rem;
      color: #5A7A64;
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
      .login-panel-right { padding: 32px 24px; }
      .form-row-2col { grid-template-columns: 1fr; gap: 0; }
    }
  </style>
</head>
<body class="login-page">

<div class="login-layout">

  <!-- -- LEFT PANEL ------------------------------------ -->
  <div class="login-panel-left">
    <div class="lpl-blob lpl-blob-1"></div>
    <div class="lpl-blob lpl-blob-2"></div>
    <div class="lpl-grid"></div>

    <div class="lpl-inner">
      <a href="{{ url('/') }}" class="lpl-logo">
        <img src="{{ asset('images/iclimate-logo.png') }}" alt="iClimate" class="auth-logo-img">
      </a>

      <div class="lpl-body">
        <div class="lpl-eyebrow">Farmer Registration</div>
        <h1 class="lpl-headline">Join the iClimate community</h1>
        <p class="lpl-sub">Create a free account to access climate records, planting advisories, community feed posts, and notifications for Lian, Batangas.</p>

        <div class="account-type-card">
          <div class="atc-header">
            <div class="atc-icon">
              <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                <path d="M4 14C4 7 10 3 15 3C15 8 11 14 4 14Z" stroke="#E8A73D" stroke-width="1.3" stroke-linejoin="round"/>
                <path d="M4 14L11 7" stroke="#E8A73D" stroke-width="1.3" stroke-linecap="round"/>
              </svg>
            </div>
            <div>
              <div class="atc-title">Rice Farmer Account</div>
              <div class="atc-subtitle">Public registration &middot; Free access</div>
            </div>
          </div>
          <div class="atc-perks">
            <div class="atc-perk">
              <div class="atc-perk-check">
                <svg width="10" height="8" viewBox="0 0 10 8" fill="none"><path d="M1 4l3 3 5-6" stroke="#52B788" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </div>
              Climate records and advisories
            </div>
            <div class="atc-perk">
              <div class="atc-perk-check">
                <svg width="10" height="8" viewBox="0 0 10 8" fill="none"><path d="M1 4l3 3 5-6" stroke="#52B788" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </div>
              Seasonal planting recommendations
            </div>
            <div class="atc-perk">
              <div class="atc-perk-check">
                <svg width="10" height="8" viewBox="0 0 10 8" fill="none"><path d="M1 4l3 3 5-6" stroke="#52B788" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </div>
              Rice production information and notifications
            </div>
          </div>
        </div>

        <div class="lpl-notice">
          <div class="lpl-notice-icon">
            <svg width="18" height="18" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="9" stroke="#FFD166" stroke-width="1.5"/><line x1="10" y1="9" x2="10" y2="14" stroke="#FFD166" stroke-width="1.5" stroke-linecap="round"/><circle cx="10" cy="6" r="1" fill="#FFD166"/></svg>
          </div>
          <p><strong>MAO Staff and IT Personnel</strong> accounts are provisioned by system administrators. Contact your local MAO office for access.</p>
        </div>
      </div>

      <div class="lpl-foot">&copy; 2026 iClimate &middot; Lian, Batangas</div>
    </div>
  </div>

  <!-- -- RIGHT PANEL ---------------------------------- -->
  <div class="login-panel-right">
    <div class="login-form-wrap">

      <a href="{{ url('/') }}" class="back-link-top">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M11 7H3M6 10L3 7l3-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Back to Home
      </a>

      <div class="login-form-header">
        <h1>Create your account</h1>
        <p>Join iClimate as a Rice Farmer - it's free</p>
      </div>

      <div class="role-pill">
        <div class="role-pill-dot">RF</div>
        <span>Rice Farmer &middot; Public Registration</span>
      </div>

      <form method="POST" action="{{ route('register') }}" class="register-form">
        @csrf
        <input type="hidden" id="name" name="name" value="{{ old('name') }}">

      <!-- Name row -->
      <div class="form-row-2col">
        <div class="form-group">
          <label for="firstName">First Name</label>
          <input type="text" id="firstName" class="form-input @error('name') is-invalid @enderror" placeholder="Juan" autocomplete="given-name" required/>
        </div>
        <div class="form-group">
          <label for="lastName">Last Name</label>
          <input type="text" id="lastName" class="form-input @error('name') is-invalid @enderror" placeholder="Dela Cruz" autocomplete="family-name" required/>
        </div>
      </div>

      <!-- Email -->
      @error('name')<div class="field-error">{{ $message }}</div>@enderror
      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-input @error('email') is-invalid @enderror" placeholder="your.email@example.com" autocomplete="username" required/>
      </div>
      @error('email')<div class="field-error">{{ $message }}</div>@enderror

      <!-- Password -->
      <div class="form-group">
        <label for="password">Password</label>
        <div class="input-wrap">
          <input type="password" id="password" name="password" class="form-input @error('password') is-invalid @enderror" placeholder="Create a strong password (min. 8 chars)" autocomplete="new-password" oninput="checkStrength(this.value)" required/>
          <button class="pwd-toggle" id="pwdToggle" type="button" aria-label="Show password">
            <svg id="eyeIcon" width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M1 8C2.5 4.5 5 3 8 3s5.5 1.5 7 5c-1.5 3.5-4 5-7 5S2.5 11.5 1 8z" stroke="#6B8F71" stroke-width="1.5"/><circle cx="8" cy="8" r="2" stroke="#6B8F71" stroke-width="1.5"/></svg>
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

      <!-- Confirm password -->
      <div class="form-group">
        <label for="confirmPassword">Confirm Password</label>
        <div class="input-wrap">
          <input type="password" id="confirmPassword" name="password_confirmation" class="form-input @error('password_confirmation') is-invalid @enderror" placeholder="Re-enter your password" autocomplete="new-password" required/>
          <button class="pwd-toggle" id="confirmPwdToggle" type="button" aria-label="Show password">
            <svg id="confirmEyeIcon" width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M1 8C2.5 4.5 5 3 8 3s5.5 1.5 7 5c-1.5 3.5-4 5-7 5S2.5 11.5 1 8z" stroke="#6B8F71" stroke-width="1.5"/><circle cx="8" cy="8" r="2" stroke="#6B8F71" stroke-width="1.5"/></svg>
          </button>
        </div>
      </div>
      @error('password_confirmation')<div class="field-error">{{ $message }}</div>@enderror

      <!-- Terms -->
      <div class="terms-row">
        <input type="checkbox" id="agreeTerms" class="custom-checkbox" required/>
        <label class="checkbox-custom" for="agreeTerms"></label>
        <span class="terms-text">
          I agree to the Terms of Use and Privacy Policy. I understand my data is used to provide climate and agricultural recommendations.
        </span>
      </div>

      <!-- Error -->
      <div id="formMessage" style="display:none;"></div>

      <!-- Submit -->
      <button class="btn-login" id="createAccountBtn" type="submit">
        Create Account
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 1v14M1 8h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
      </button>
      </form>

      <p class="login-register">Already have an account? <a href="{{ route('login') }}">Sign In</a></p>

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
