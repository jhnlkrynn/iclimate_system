@php
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
    $latestClimate = \App\Models\ClimateRecord::query()->latest('record_date')->first();
    $rainfall = (float) ($latestClimate->rainfall ?? 2.4);
    $condition = match (true) {
        $rainfall >= 300 => ['Heavy Rain', '&#127783;&#65039;'],
        $rainfall >= 120 => ['Rain', '&#127783;&#65039;'],
        $rainfall >= 70 => ['Cloudy', '&#9925;&#65039;'],
        $rainfall >= 30 => ['Partly Cloudy', '&#127780;&#65039;'],
        default => ['Sunny', '&#9728;&#65039;'],
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_' , '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Sign In | {{ config('app.name', 'iClimate') }}</title>
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
    body.login-page {
      min-height: 100vh;
      background:
        linear-gradient(180deg, rgba(6,16,12,.55), rgba(6,16,12,.82)),
        linear-gradient(160deg, #0f2318 0%, #1a3a2a 45%, #2d5a3f 75%, #142e20 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 22px 20px;
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
    .scene-glow {
      position: fixed; z-index: 0; pointer-events: none; border-radius: 50%; filter: blur(90px);
    }
    .scene-glow-1 { width: 480px; height: 480px; background: rgba(82,183,136,.16); top: -120px; left: -100px; }
    .scene-glow-2 { width: 360px; height: 360px; background: rgba(232,167,61,.1); bottom: -80px; right: -60px; }

    .scene-content {
      position: relative; z-index: 1;
      display: flex; align-items: center; justify-content: space-between; gap: 32px; flex-wrap: wrap;
      width: 100%; max-width: 1120px;
    }

    /* -- BACK TO HOME LINK -------------------------------- */
    .back-link-top {
      position: fixed; top: 28px; left: 32px; z-index: 2;
      display: inline-flex; align-items: center; gap: 6px; font-size: .87rem; font-weight: 600;
      color: #95D5B2; text-decoration: none; transition: color .2s;
    }
    .back-link-top:hover { color: #74C69D; }

    /* -- LOGIN CARD -------------------------------------- */
    .login-card {
      flex: 0 1 480px;
      background: rgba(15,31,23,.62);
      border: 1.5px solid rgba(149,213,178,.22);
      border-radius: 22px;
      box-shadow: 0 30px 70px rgba(0,0,0,.4);
      backdrop-filter: blur(18px);
      padding: 32px 36px 28px;
    }
    .card-logo { text-align: center; margin-bottom: 16px; }
    .card-logo img { width: 92px; height: auto; margin: 0 auto 8px; display: block; }
    .card-logo-word { font-family: 'Inter', sans-serif; font-weight: 800; font-size: 1.6rem; color: #74C69D; letter-spacing: -0.01em; }
    .card-logo-underline { width: 62px; height: 2px; background: #52B788; border-radius: 2px; position: relative; margin: 6px auto 0; }
    .card-logo-underline::after { content: ''; position: absolute; right: -3px; top: 50%; transform: translateY(-50%); width: 6px; height: 6px; border-radius: 50%; background: #74C69D; }

    .login-form-header { text-align: center; margin-bottom: 20px; }
    .login-form-header h1 {
      font-family: 'DM Serif Display', Georgia, serif; font-size: 1.9rem; font-weight: 400; color: #fff; margin-bottom: 5px; letter-spacing: -0.02em;
    }
    .login-form-header p { font-size: .92rem; color: rgba(255,255,255,.55); }
    .login-form-header p em { font-style: normal; color: #74C69D; font-weight: 600; }

    .auth-status { background: rgba(82,183,136,.14); border: 1px solid rgba(82,183,136,.35); color: #d8f3dc; border-radius: 10px; padding: 10px 12px; font-size: .84rem; margin-bottom: 14px; }

    .form-group { margin-bottom: 14px; }
    .form-group label { display: block; font-size: .84rem; font-weight: 600; color: rgba(255,255,255,.82); margin-bottom: 7px; }
    .input-wrap { position: relative; }
    .form-input {
      width: 100%; background: rgba(255,255,255,.06); border: 1.5px solid rgba(149,213,178,.28); border-radius: 11px;
      padding: 10px 14px; font-family: 'Inter', sans-serif; font-size: .93rem; color: #fff; transition: all .2s; outline: none;
    }
    .form-input.has-icon { padding-left: 42px; }
    .form-input:focus { border-color: #52B788; box-shadow: 0 0 0 3px rgba(82,183,136,.2); background: rgba(255,255,255,.09); }
    .form-input::placeholder { color: rgba(255,255,255,.35); }
    .input-icon { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #74C69D; }
    .pwd-toggle { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,.55); background: none; border: none; cursor: pointer; padding: 2px; display: flex; transition: color .2s; }
    .pwd-toggle:hover { color: #74C69D; }

    .form-row-split { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; }
    .checkbox-label { display: flex; align-items: center; gap: 9px; font-size: .87rem; color: rgba(255,255,255,.8); cursor: pointer; user-select: none; }
    .custom-checkbox { display: none; }
    .checkbox-custom { width: 18px; height: 18px; border: 2px solid rgba(149,213,178,.4); border-radius: 5px; background: rgba(255,255,255,.05); display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: all .18s; }
    .custom-checkbox:checked + .checkbox-custom { background: #52B788; border-color: #52B788; }
    .custom-checkbox:checked + .checkbox-custom::after { content: ''; width: 9px; height: 5px; border: 2px solid white; border-top: none; border-right: none; transform: rotate(-45deg) translateY(-1px); display: block; }
    .forgot-link { font-size: .87rem; font-weight: 600; color: #74C69D; text-decoration: none; transition: color .2s; }
    .forgot-link:hover { color: #95D5B2; }

    .btn-login {
      width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px;
      background: #E8A73D; color: #1a3a2a; border: none; border-radius: 11px; font-family: 'Inter', sans-serif;
      font-size: .96rem; font-weight: 800; cursor: pointer; transition: all .2s; margin-bottom: 16px;
      box-shadow: 0 10px 28px rgba(232,167,61,.28);
    }
    .btn-login:hover { background: #f0b559; transform: translateY(-1px); box-shadow: 0 12px 32px rgba(232,167,61,.4); }

    .form-divider { display: flex; align-items: center; gap: 12px; margin: 2px 0 16px; color: rgba(255,255,255,.35); font-size: .82rem; }
    .form-divider::before, .form-divider::after { content: ''; flex: 1; height: 1px; background: rgba(149,213,178,.2); }

    .login-register { text-align: center; font-size: .88rem; color: rgba(255,255,255,.55); margin: 0; }
    .login-register a { color: #E8A73D; font-weight: 700; text-decoration: none; transition: color .2s; }
    .login-register a:hover { color: #f0b559; }

    .demo-row { display: flex; gap: 8px; justify-content: center; margin-top: 14px; flex-wrap: wrap; }
    .demo-pill {
      font-family: 'DM Mono', monospace; font-size: .66rem; font-weight: 500; letter-spacing: .03em; text-transform: uppercase;
      color: rgba(255,255,255,.5); background: rgba(255,255,255,.05); border: 1px solid rgba(149,213,178,.2);
      border-radius: 100px; padding: 6px 12px; cursor: pointer; transition: all .15s;
    }
    .demo-pill:hover { color: #fff; background: rgba(82,183,136,.18); border-color: rgba(82,183,136,.4); }

    /* -- WEATHER WIDGET ---------------------------------- */
    .weather-card {
      flex: 0 1 280px;
      background: rgba(15,31,23,.58);
      border: 1.5px solid rgba(149,213,178,.2);
      border-radius: 20px;
      box-shadow: 0 24px 56px rgba(0,0,0,.35);
      backdrop-filter: blur(16px);
      padding: 20px 22px 18px;
      color: #fff;
    }
    .weather-title { font-family: 'DM Serif Display', Georgia, serif; font-size: 1.2rem; margin-bottom: 4px; }
    .weather-loc { display: flex; align-items: center; gap: 6px; font-size: .82rem; color: #74C69D; font-weight: 600; margin-bottom: 14px; }
    .weather-main { text-align: center; margin-bottom: 12px; }
    .weather-emoji { font-size: 2.2rem; line-height: 1; margin-bottom: 6px; }
    .weather-temp { font-family: 'DM Serif Display', Georgia, serif; font-size: 2.1rem; color: #fff; line-height: 1; }
    .weather-cond { font-size: .86rem; color: #95D5B2; font-weight: 600; margin-top: 3px; }
    .weather-divider { height: 1px; background: rgba(149,213,178,.18); margin: 2px 0 12px; }
    .weather-rows { display: flex; flex-direction: column; gap: 10px; margin-bottom: 12px; }
    .weather-row { display: flex; align-items: center; gap: 12px; }
    .weather-row-icon { color: #74C69D; flex-shrink: 0; width: 20px; }
    .weather-row-val { font-weight: 700; font-size: .95rem; color: #fff; }
    .weather-row-label { font-size: .74rem; color: rgba(255,255,255,.5); }
    .weather-updated { display: flex; align-items: center; gap: 6px; font-size: .74rem; color: rgba(255,255,255,.4); }
    .weather-updated-dot { width: 6px; height: 6px; border-radius: 50%; background: #52B788; box-shadow: 0 0 6px rgba(82,183,136,.7); }

    /* -- BELOW-CARD TAGLINE ------------------------------ */
    .scene-tagline { position: relative; z-index: 1; text-align: center; margin-top: 16px; width: 100%; }
    .scene-tagline-row { display: inline-flex; align-items: center; gap: 8px; color: rgba(255,255,255,.62); font-size: .92rem; font-weight: 600; }
    .scene-copyright { margin-top: 6px; font-size: .78rem; color: rgba(255,255,255,.32); }

    @media (max-width: 820px) {
      .scene-content { flex-direction: column; align-items: center; justify-content: center; gap: 28px; }
      .login-card, .weather-card { flex: 0 1 auto; width: 100%; }
      .weather-card { max-width: 480px; }
    }
    @media (max-width: 540px) {
      .login-card { padding: 32px 24px 28px; }
      .back-link-top { position: absolute; top: 16px; left: 16px; }
    }
  </style>
</head>
<body class="login-page">
  <div class="scene-hills"></div>
  <div class="scene-glow scene-glow-1"></div>
  <div class="scene-glow scene-glow-2"></div>

  <a href="{{ url('/') }}" class="back-link-top">
    <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M11 7H3M6 10L3 7l3-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    Back to Home
  </a>

  <div class="scene-content">
    <div class="login-card">
      <div class="card-logo">
        <img src="{{ asset('images/iclimate-logo.png') }}" alt="iClimate">
        <div class="card-logo-word">iClimate</div>
        <div class="card-logo-underline"></div>
      </div>

      <div class="login-form-header">
        <h1>Login</h1>
        <p>Continue to your <em>iClimate</em> account</p>
      </div>

      @if (session('status'))
        <div class="auth-status">{{ session('status') }}</div>
      @endif

      <form method="POST" action="{{ route('login') }}" class="login-form">
        @csrf

        <div class="form-group">
          <label for="email">Email Address</label>
          <div class="input-wrap">
            <svg class="input-icon" width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="1" y="3" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M1 5l7 5 7-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-input has-icon @error('email') is-invalid @enderror" placeholder="example@email.com" autocomplete="username" required autofocus/>
          </div>
          @error('email')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-wrap">
            <svg class="input-icon" width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="3" y="7" width="10" height="7" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M5 7V5a3 3 0 016 0v2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            <input type="password" id="password" name="password" class="form-input has-icon @error('password') is-invalid @enderror" placeholder="Enter your password" autocomplete="current-password" required/>
            <button class="pwd-toggle" id="pwdToggle" type="button" aria-label="Show password">
              <svg id="eyeIcon" width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M1 8C2.5 4.5 5 3 8 3s5.5 1.5 7 5c-1.5 3.5-4 5-7 5S2.5 11.5 1 8z" stroke="currentColor" stroke-width="1.5"/><circle cx="8" cy="8" r="2" stroke="currentColor" stroke-width="1.5"/></svg>
            </button>
          </div>
          @error('password')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-row-split">
          <label class="checkbox-label">
            <input type="checkbox" id="remember_me" name="remember" class="custom-checkbox" checked/>
            <span class="checkbox-custom"></span>
            Remember Me
          </label>
          @if (Route::has('password.request'))<a href="{{ route('password.request') }}" class="forgot-link">Forgot Password?</a>@endif
        </div>

        <button class="btn-login" id="loginBtn" type="submit">
          Login
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M6 3h6a1 1 0 011 1v8a1 1 0 01-1 1H6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 8H2m0 0l2.5-2.5M2 8l2.5 2.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
      </form>

      <div class="form-divider"><span>or</span></div>

      <p class="login-register">Don't have an account? <a href="{{ route('register') }}">Create Account</a></p>

      <div class="demo-row">
        <span class="demo-pill" onclick="fillDemo('farmer@iclimate.com','password123')">Farmer</span>
        <span class="demo-pill" onclick="fillDemo('mao@iclimate.com','password123')">MAO</span>
        <span class="demo-pill" onclick="fillDemo('admin@iclimate.com','password123')">IT Expert</span>
      </div>
    </div>

    <div class="weather-card">
      <div class="weather-title">Today's Weather</div>
      <div class="weather-loc">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M7 1c2.8 0 5 2.2 5 5.1C12 10 7 13 7 13S2 10 2 6.1C2 3.2 4.2 1 7 1Z" stroke="currentColor" stroke-width="1.3"/><circle cx="7" cy="6" r="1.6" stroke="currentColor" stroke-width="1.2"/></svg>
        Lian, Batangas
      </div>

      <div class="weather-main">
        <div class="weather-emoji">{!! $condition[1] !!}</div>
        <div class="weather-temp">{{ $latestClimate ? round((float) $latestClimate->temperature) : 26 }}&deg;C</div>
        <div class="weather-cond">{{ $condition[0] }}</div>
      </div>

      <div class="weather-divider"></div>

      <div class="weather-rows">
        <div class="weather-row">
          <span class="weather-row-icon"><svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 2c1.4 2 2.7 3.9 2.7 5.8A2.7 2.7 0 1 1 6.3 7.8C6.3 5.9 7.6 4 9 2Z" stroke="currentColor" stroke-width="1.4"/></svg></span>
          <span><span class="weather-row-val">{{ $latestClimate ? round((float) $latestClimate->humidity) : 78 }}%</span><br><span class="weather-row-label">Humidity</span></span>
        </div>
        <div class="weather-row">
          <span class="weather-row-icon"><svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M2 6h8a2 2 0 1 0-2-2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M2 10h11a2.2 2.2 0 1 1-2.2 2.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg></span>
          <span><span class="weather-row-val">{{ $latestClimate ? round((float) $latestClimate->wind_speed) : 10 }} km/h</span><br><span class="weather-row-label">Wind Speed</span></span>
        </div>
        <div class="weather-row">
          <span class="weather-row-icon"><svg width="18" height="18" viewBox="0 0 18 18" fill="none"><rect x="4" y="2" width="2.4" height="10" rx="1" fill="currentColor"/><rect x="8" y="5" width="2.4" height="7" rx="1" fill="currentColor" opacity=".7"/><rect x="12" y="7.5" width="2.4" height="4.5" rx="1" fill="currentColor" opacity=".5"/></svg></span>
          <span><span class="weather-row-val">{{ number_format($rainfall, 1) }} mm</span><br><span class="weather-row-label">Rainfall Today</span></span>
        </div>
      </div>

      <div class="weather-updated">
        <span class="weather-updated-dot"></span>
        Updated {{ $latestClimate?->updated_at?->format('g:i A') ?? now()->format('g:i A') }}
      </div>
    </div>
  </div>

  <div class="scene-tagline">
    <div class="scene-tagline-row">
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 1c2.4 3.4 4.4 6.2 4.4 8.9A4.4 4.4 0 1 1 3.6 9.9C3.6 7.2 5.6 4.4 8 1z" stroke="#74C69D" stroke-width="1.4"/></svg>
      iClimate Decision Support System
    </div>
    <div class="scene-copyright">&copy; 2026 iClimate Research Group &ndash; Batangas State University ARASOF-Masungki</div>
  </div>

<script>
  function fillDemo(email, pass) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = pass;
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
