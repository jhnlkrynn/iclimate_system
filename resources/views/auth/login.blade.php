@php
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
    $latestClimate = \App\Support\ClimateSnapshot::latest();
    $loginWeather = $loginWeather ?? [];
    $weatherForecast = $loginWeather['forecast'] ?? null;
    $weatherResult = $loginWeather['result'] ?? [];
    $weatherTimezone = $loginWeather['timezone'] ?? config('services.open_meteo.timezone', 'Asia/Manila');
    $currentWeather = $weatherForecast?->raw_response['current'] ?? [];
    $weatherCode = data_get($currentWeather, 'weather_code') ?? $weatherForecast?->weather_code;
    $temperature = data_get($currentWeather, 'temperature_2m') ?? $weatherForecast?->temperature ?? $latestClimate?->temperature ?? 26;
    $humidity = data_get($currentWeather, 'relative_humidity_2m') ?? $weatherForecast?->humidity ?? $latestClimate?->humidity ?? 78;
    $windSpeed = data_get($currentWeather, 'wind_speed_10m') ?? $weatherForecast?->wind_speed ?? $latestClimate?->wind_speed ?? 10;
    $rainfall = (float) ($weatherForecast?->rainfall_mm ?? $latestClimate->rainfall ?? 2.4);
    $weatherUpdatedAt = $weatherForecast?->fetched_at?->timezone($weatherTimezone) ?? $latestClimate?->updated_at ?? now($weatherTimezone);
    $condition = match (true) {
        in_array((int) $weatherCode, [95, 96, 99], true) => ['Thunderstorm', '&#9928;&#65039;'],
        in_array((int) $weatherCode, [80, 81, 82, 61, 63, 65, 66, 67], true) => ['Rain', '&#127783;&#65039;'],
        in_array((int) $weatherCode, [51, 53, 55, 56, 57], true) => ['Drizzle', '&#127783;&#65039;'],
        in_array((int) $weatherCode, [45, 48], true) => ['Foggy', '&#127787;&#65039;'],
        in_array((int) $weatherCode, [1, 2, 3], true) => ['Cloudy', '&#9925;&#65039;'],
        (int) $weatherCode === 0 => ['Clear', '&#9728;&#65039;'],
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
    body.login-page {
      min-height: 100vh;
      background-color: #1F4D3A;
      background-image:
        linear-gradient(180deg, rgba(22,59,45,.82), rgba(22,59,45,.92)),
        url('{{ asset('images/rice-hero-aerial.jpg') }}');
      background-position: center;
      background-size: cover;
      background-repeat: no-repeat;
      background-attachment: fixed;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 16px;
      position: relative;
      overflow-x: hidden;
    }
    .scene-glow {
      position: fixed; z-index: 0; pointer-events: none; border-radius: 50%; filter: blur(90px);
    }
    .scene-glow-1 { width: 420px; height: 420px; background: rgba(95,143,120,.16); top: -110px; left: -90px; }
    .scene-glow-2 { width: 320px; height: 320px; background: rgba(232,167,61,.1); bottom: -70px; right: -50px; }

    .scene-content {
      position: relative; z-index: 1;
      display: flex; align-items: center; justify-content: center; gap: 24px; flex-wrap: wrap;
      width: 100%; max-width: 1080px;
      animation: authSceneEnter var(--auth-motion-slow) both;
    }

    /* -- BACK TO HOME LINK -------------------------------- */
    .back-link-top {
      position: fixed; top: 20px; left: 24px; z-index: 2;
      display: inline-flex; align-items: center; gap: 6px; font-size: .85rem; font-weight: 600;
      color: #FFFFFF; text-decoration: none; transition: color .2s;
    }
    .back-link-top:hover { color: #F6D58A; }

    /* -- LOGIN CARD -------------------------------------- */
    .login-card {
      flex: 0 1 560px;
      background: rgba(255,255,255,.98);
      border: 1.5px solid rgba(95,143,120,.4);
      border-radius: 22px;
      box-shadow: 0 26px 60px rgba(13,31,24,.24);
      backdrop-filter: blur(18px);
      padding: 34px 40px 30px;
      transition: transform var(--auth-motion-med), box-shadow var(--auth-motion-med), border-color var(--auth-motion-med), background-color var(--auth-motion-med);
      transform: translateZ(0);
    }
    .login-card:hover { transform: translateY(-4px); box-shadow: 0 32px 72px rgba(13,31,24,.3); border-color: rgba(95,143,120,.6); }
    .card-logo { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 10px; }
    .card-logo img { width: 42px; height: auto; display: block; }
    .card-logo-word { font-family: 'Inter', sans-serif; font-weight: 800; font-size: 1.25rem; color: #1F4D3A; letter-spacing: -0.01em; }

    .login-form-header { text-align: center; margin-bottom: 14px; }
    .login-form-header h1 {
      font-family: 'DM Serif Display', Georgia, serif; font-size: 1.65rem; font-weight: 400; color: #163B2D; margin-bottom: 4px; letter-spacing: -0.02em;
    }
    .login-form-header p { font-size: .88rem; color: #64748B; }
    .login-form-header p em { font-style: normal; color: #1F4D3A; font-weight: 600; }

    .auth-status { background: rgba(95,143,120,.14); border: 1px solid rgba(95,143,120,.35); color: #1F4D3A; border-radius: 10px; padding: 9px 12px; font-size: .82rem; margin-bottom: 12px; }
    @keyframes authSceneEnter { from { opacity: 0; transform: translateY(16px) scale(.992); filter: blur(2px); } to { opacity: 1; transform: translateY(0) scale(1); filter: blur(0); } }

    .form-group { margin-bottom: 11px; }
    .form-group label { display: block; font-size: .82rem; font-weight: 600; color: #1F2937; margin-bottom: 6px; }
    .input-wrap { position: relative; }
    .form-input {
      width: 100%; background: #fff; border: 1.5px solid rgba(95,143,120,.45); border-radius: 10px;
      padding: 9px 14px; font-family: 'Inter', sans-serif; font-size: .92rem; color: #1F2937; transition: border-color var(--auth-motion-med), box-shadow var(--auth-motion-med), background-color var(--auth-motion-med), transform var(--auth-motion-med); outline: none;
    }
    .form-input.has-icon { padding-left: 40px; }
    .form-input:focus { border-color: #1F4D3A; box-shadow: 0 0 0 3px rgba(31,77,58,.18); background: #fff; transform: translateY(-1px); }
    .form-input::placeholder { color: #64748B; }
    .input-icon { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #1F4D3A; }
    .pwd-toggle { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #64748B; background: none; border: none; cursor: pointer; padding: 2px; display: flex; transition: color .2s; }
    .pwd-toggle:hover { color: #1F4D3A; }
    .captcha-card {
      display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 10px; align-items: center;
      background: rgba(95,143,120,.08); border: 1.5px solid rgba(95,143,120,.25); border-radius: 12px;
      padding: 10px 12px; margin-bottom: 11px;
    }
    .captcha-icon {
      width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center;
      background: rgba(95,143,120,.18); color: #1F4D3A; border: 1px solid rgba(95,143,120,.32);
    }
    .captcha-label { font-size: .76rem; font-weight: 800; color: #1F2937; margin-bottom: 5px; }
    .captcha-image {
      display: block; width: 100%; height: 64px; border-radius: 13px;
      border: 1px solid rgba(95,143,120,.35); margin-bottom: 8px; background: #f2f7f4;
    }

    .form-row-split { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
    .checkbox-label { display: flex; align-items: center; gap: 9px; font-size: .85rem; color: #1F2937; cursor: pointer; user-select: none; }
    .custom-checkbox { display: none; }
    .checkbox-custom { width: 17px; height: 17px; border: 2px solid rgba(95,143,120,.5); border-radius: 5px; background: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: all .18s; }
    .custom-checkbox:checked + .checkbox-custom { background: #1F4D3A; border-color: #1F4D3A; }
    .custom-checkbox:checked + .checkbox-custom::after { content: ''; width: 9px; height: 5px; border: 2px solid white; border-top: none; border-right: none; transform: rotate(-45deg) translateY(-1px); display: block; }
    .forgot-link { font-size: .85rem; font-weight: 600; color: #1F4D3A; text-decoration: none; transition: color .2s; }
    .forgot-link:hover { color: #163B2D; }

    .btn-login {
      width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 11px;
      background: #E8A73D; color: #1F2937; border: none; border-radius: 10px; font-family: 'Inter', sans-serif;
      font-size: .95rem; font-weight: 800; cursor: pointer; transition: transform var(--auth-motion-med), box-shadow var(--auth-motion-med), background-color var(--auth-motion-med); margin-bottom: 12px;
      box-shadow: 0 10px 24px rgba(232,167,61,.28);
    }
    .btn-login:hover { background: #F6D58A; transform: translateY(-3px); box-shadow: 0 16px 34px rgba(232,167,61,.42); }
    .btn-login:active { transform: translateY(0) scale(.985); }

    .form-divider { display: flex; align-items: center; gap: 12px; margin: 0 0 12px; color: #64748B; font-size: .8rem; }
    .form-divider::before, .form-divider::after { content: ''; flex: 1; height: 1px; background: #E5E7EB; }

    .login-register { text-align: center; font-size: .86rem; color: #64748B; margin: 0; }
    .login-register a { color: #E8A73D; font-weight: 700; text-decoration: none; transition: color .2s; }
    .login-register a:hover { color: #C6872A; }

    /* -- WEATHER WIDGET ---------------------------------- */
    .weather-card {
      flex: 0 1 420px;
      background: rgba(22,59,45,.88);
      border: 1.5px solid rgba(95,143,120,.4);
      border-radius: 22px;
      box-shadow: 0 20px 48px rgba(0,0,0,.38);
      backdrop-filter: blur(16px);
      padding: 30px 32px 26px;
      color: #fff;
      animation: authSceneEnter 680ms var(--auth-ease-out) both;
      animation-delay: 90ms;
      transition: transform var(--auth-motion-med), box-shadow var(--auth-motion-med), border-color var(--auth-motion-med);
    }
    .weather-card:hover { transform: translateY(-4px); box-shadow: 0 26px 58px rgba(0,0,0,.42); border-color: rgba(95,143,120,.6); }
    .weather-title { font-family: 'DM Serif Display', Georgia, serif; font-size: 1.1rem; margin-bottom: 3px; color: #fff; }
    .weather-loc { display: flex; align-items: center; gap: 6px; font-size: .8rem; color: #7FD6B5; font-weight: 600; margin-bottom: 12px; }
    .weather-main { text-align: center; margin-bottom: 10px; }
    .weather-emoji { font-size: 2rem; line-height: 1; margin-bottom: 5px; }
    .weather-temp { font-family: 'DM Serif Display', Georgia, serif; font-size: 1.9rem; color: #fff; line-height: 1; }
    .weather-cond { font-size: .84rem; color: #7FD6B5; font-weight: 600; margin-top: 3px; }
    .weather-divider { height: 1px; background: rgba(95,143,120,.3); margin: 2px 0 10px; }
    .weather-rows { display: flex; flex-direction: column; gap: 8px; margin-bottom: 10px; }
    .weather-row { display: flex; align-items: center; gap: 12px; }
    .weather-row-icon { color: #E8A73D; flex-shrink: 0; width: 20px; }
    .weather-row-val { font-weight: 700; font-size: .93rem; color: #fff; }
    .weather-row-label { font-size: .72rem; color: rgba(127,214,181,.85); }
    .weather-updated { display: flex; align-items: center; gap: 6px; font-size: .72rem; color: rgba(127,214,181,.7); }
    .weather-updated-dot { width: 6px; height: 6px; border-radius: 50%; background: #7FD6B5; box-shadow: 0 0 6px rgba(127,214,181,.7); }

    /* -- BELOW-CARD TAGLINE ------------------------------ */
    .scene-tagline { position: relative; z-index: 1; text-align: center; margin-top: 12px; width: 100%; }
    .scene-tagline-row { display: inline-flex; align-items: center; gap: 8px; color: rgba(255,255,255,.92); font-size: .88rem; font-weight: 600; }
    .scene-copyright { margin-top: 5px; font-size: .76rem; color: rgba(127,214,181,.65); }

    @media (max-width: 820px) {
      .scene-content { flex-direction: column; align-items: center; justify-content: center; gap: 20px; }
      .login-card, .weather-card { flex: 0 1 auto; width: 100%; }
      .weather-card { max-width: 440px; }
    }
    @media (max-width: 540px) {
      .login-card { padding: 24px 20px 20px; }
      .back-link-top { position: absolute; top: 14px; left: 14px; }
      .captcha-card { grid-template-columns: 1fr; }
      .captcha-icon { display: none; }
      .form-row-split { align-items: flex-start; flex-direction: column; gap: 10px; }
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
<body class="login-page">
  <x-flash-toast />
  <div class="scene-glow scene-glow-1"></div>
  <div class="scene-glow scene-glow-2"></div>

  <a href="{{ url('/') }}" class="back-link-top">
    <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M11 7H3M6 10L3 7l3-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    Back to Home
  </a>

  <div class="scene-content">
    <div class="login-card">
      <div class="card-logo">
        <img src="{{ asset('images/iclimate-icon.png') }}" alt="iClimate">
        <div class="card-logo-word">iClimate</div>
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

        @if($requiresCaptcha ?? false)
        <div class="captcha-card">
          <div class="captcha-icon" aria-hidden="true">
            <svg width="17" height="17" viewBox="0 0 18 18" fill="none"><path d="M9 2l5 2v4.2c0 3.1-1.9 5.8-5 7.1-3.1-1.3-5-4-5-7.1V4l5-2z" stroke="currentColor" stroke-width="1.5"/><path d="M6.8 9l1.4 1.4 3.2-3.4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
          <div>
            <label for="captcha_answer" class="captcha-label">Security Check</label>
            <img class="captcha-image" src="{{ route('captcha.image', ['context' => 'login', 'v' => $captcha['token'] ?? now()->timestamp]) }}" alt="Security code image">
            <input type="text" id="captcha_answer" name="captcha_answer" class="form-input @error('captcha_answer') is-invalid @enderror" placeholder="Enter the code shown above" autocomplete="off" autocapitalize="characters" spellcheck="false" required/>
            @error('captcha_answer')<div class="field-error">{{ $message }}</div>@enderror
          </div>
        </div>
        @endif

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

    </div>

    <div class="weather-card">
      <div class="weather-title">Today's Weather</div>
      <div class="weather-loc">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M7 1c2.8 0 5 2.2 5 5.1C12 10 7 13 7 13S2 10 2 6.1C2 3.2 4.2 1 7 1Z" stroke="currentColor" stroke-width="1.3"/><circle cx="7" cy="6" r="1.6" stroke="currentColor" stroke-width="1.2"/></svg>
        Lian, Batangas
      </div>

      <div class="weather-main">
        <div class="weather-emoji">{!! $condition[1] !!}</div>
        <div class="weather-temp">{{ round((float) $temperature) }}&deg;C</div>
        <div class="weather-cond">{{ $condition[0] }}</div>
      </div>

      <div class="weather-divider"></div>

      <div class="weather-rows">
        <div class="weather-row">
          <span class="weather-row-icon"><svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 2c1.4 2 2.7 3.9 2.7 5.8A2.7 2.7 0 1 1 6.3 7.8C6.3 5.9 7.6 4 9 2Z" stroke="currentColor" stroke-width="1.4"/></svg></span>
          <span><span class="weather-row-val">{{ round((float) $humidity) }}%</span><br><span class="weather-row-label">Humidity</span></span>
        </div>
        <div class="weather-row">
          <span class="weather-row-icon"><svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M2 6h8a2 2 0 1 0-2-2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M2 10h11a2.2 2.2 0 1 1-2.2 2.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg></span>
          <span><span class="weather-row-val">{{ round((float) $windSpeed) }} km/h</span><br><span class="weather-row-label">Wind Speed</span></span>
        </div>
        <div class="weather-row">
          <span class="weather-row-icon"><svg width="18" height="18" viewBox="0 0 18 18" fill="none"><rect x="4" y="2" width="2.4" height="10" rx="1" fill="currentColor"/><rect x="8" y="5" width="2.4" height="7" rx="1" fill="currentColor" opacity=".7"/><rect x="12" y="7.5" width="2.4" height="4.5" rx="1" fill="currentColor" opacity=".5"/></svg></span>
          <span><span class="weather-row-val">{{ number_format($rainfall, 1) }} mm</span><br><span class="weather-row-label">Rainfall Today</span></span>
        </div>
      </div>

      <div class="weather-updated">
        <span class="weather-updated-dot"></span>
        Updated {{ $weatherUpdatedAt->shortTime() }}
      </div>
    </div>
  </div>

  <div class="scene-tagline">
    <div class="scene-tagline-row">
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 1c2.4 3.4 4.4 6.2 4.4 8.9A4.4 4.4 0 1 1 3.6 9.9C3.6 7.2 5.6 4.4 8 1z" stroke="#7FD6B5" stroke-width="1.4"/></svg>
      iClimate Decision Support System
    </div>
    <div class="scene-copyright">&copy; 2026 iClimate Research Group &ndash; Batangas State University ARASOF-Nasugbu</div>
  </div>

<script>
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
