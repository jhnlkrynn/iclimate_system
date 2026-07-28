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
    .field-error { color: #ffb4a6; font-size: .78rem; font-weight: 600; margin-top: 6px; }
    .form-input.is-invalid { border-color: #e57a68; box-shadow: 0 0 0 3px rgba(229,122,104,.14); }
    .btn-login:disabled { opacity: .75; cursor: wait; transform: none; }

    /* -- SCENE BACKGROUND ------------------------------- */
    body.login-page {
      min-height: 100vh;
      background:
        linear-gradient(180deg, rgba(8,18,13,.72), rgba(8,18,13,.88)),
        url('{{ asset('images/rice-hero-aerial.jpg') }}') center/cover no-repeat fixed;
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
    .scene-glow-1 { width: 420px; height: 420px; background: rgba(82,183,136,.14); top: -110px; left: -90px; }
    .scene-glow-2 { width: 320px; height: 320px; background: rgba(232,167,61,.09); bottom: -70px; right: -50px; }

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
      color: #95D5B2; text-decoration: none; transition: color .2s;
    }
    .back-link-top:hover { color: #74C69D; }

    /* -- LOGIN CARD -------------------------------------- */
    .login-card {
      flex: 0 1 560px;
      background: rgba(15,31,23,.68);
      border: 1.5px solid rgba(149,213,178,.22);
      border-radius: 22px;
      box-shadow: 0 26px 60px rgba(0,0,0,.42);
      backdrop-filter: blur(18px);
      padding: 34px 40px 30px;
      transition: transform var(--auth-motion-med), box-shadow var(--auth-motion-med), border-color var(--auth-motion-med), background-color var(--auth-motion-med);
      transform: translateZ(0);
    }
    .login-card:hover { transform: translateY(-4px); box-shadow: 0 32px 72px rgba(0,0,0,.46); border-color: rgba(149,213,178,.34); }
    .card-logo { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 10px; }
    .card-logo img { width: 42px; height: auto; display: block; }
    .card-logo-word { font-family: 'Inter', sans-serif; font-weight: 800; font-size: 1.25rem; color: #74C69D; letter-spacing: -0.01em; }

    .login-form-header { text-align: center; margin-bottom: 14px; }
    .login-form-header h1 {
      font-family: 'DM Serif Display', Georgia, serif; font-size: 1.65rem; font-weight: 400; color: #fff; margin-bottom: 4px; letter-spacing: -0.02em;
    }
    .login-form-header p { font-size: .88rem; color: rgba(255,255,255,.55); }
    .login-form-header p em { font-style: normal; color: #74C69D; font-weight: 600; }

    .auth-status { background: rgba(82,183,136,.14); border: 1px solid rgba(82,183,136,.35); color: #d8f3dc; border-radius: 10px; padding: 9px 12px; font-size: .82rem; margin-bottom: 12px; }
    .auth-popup { position: fixed; inset: 0; z-index: 20; display: none; align-items: flex-start; justify-content: center; padding: 18px; background: radial-gradient(circle at 50% 0, rgba(82,183,136,.22), transparent 22rem), rgba(8,18,13,.34); backdrop-filter: blur(5px); }
    .auth-popup.show { display: flex; }
    .auth-popup-card { width: min(430px, 100%); margin-top: 22px; border: 1.5px solid rgba(149,213,178,.34); border-radius: 20px; overflow: hidden; background: linear-gradient(145deg, rgba(255,255,255,.99), rgba(240,247,244,.99)); box-shadow: 0 24px 58px rgba(0,0,0,.42); animation: authPopupIn .24s ease-out; }
    .auth-popup-card.success { --popup-accent: #52B788; }
    .auth-popup-card.error { --popup-accent: #e57a68; }
    .auth-popup-card.info { --popup-accent: #E8A73D; }
    .auth-popup-card::before { content: ""; display: block; height: 7px; background: linear-gradient(90deg, var(--popup-accent), #E8A73D, #74C69D); }
    .auth-popup-body { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 13px; padding: 16px 16px 13px; }
    .auth-popup-icon { width: 46px; height: 46px; border-radius: 14px; display: grid; place-items: center; color: #fff; background: var(--popup-accent); box-shadow: 0 12px 24px color-mix(in srgb, var(--popup-accent) 36%, transparent); font-weight: 900; }
    .auth-popup-title { margin: 0 0 5px; color: #0d1f18; font-size: 1rem; font-weight: 900; }
    .auth-popup-message { margin: 0; color: #3d5a48; line-height: 1.45; }
    .auth-popup-close { width: 100%; border: 0; border-top: 1px solid #d4edda; padding: 11px 14px; background: #f0f7f4; color: #1a3a2a; font-weight: 900; cursor: pointer; }
    .auth-popup-close:hover { background: #d8f3dc; }
    .auth-popup-timer { height: 3px; background: color-mix(in srgb, var(--popup-accent) 24%, #fff); }
    .auth-popup-timer span { display: block; height: 100%; background: var(--popup-accent); animation: authPopupTimer 5.2s linear forwards; }
    @keyframes authPopupIn { from { opacity: 0; transform: translateY(-12px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
    @keyframes authPopupTimer { from { width: 100%; } to { width: 0%; } }
    @keyframes authSceneEnter { from { opacity: 0; transform: translateY(16px) scale(.992); filter: blur(2px); } to { opacity: 1; transform: translateY(0) scale(1); filter: blur(0); } }

    .form-group { margin-bottom: 11px; }
    .form-group label { display: block; font-size: .82rem; font-weight: 600; color: rgba(255,255,255,.82); margin-bottom: 6px; }
    .input-wrap { position: relative; }
    .form-input {
      width: 100%; background: rgba(255,255,255,.06); border: 1.5px solid rgba(149,213,178,.28); border-radius: 10px;
      padding: 9px 14px; font-family: 'Inter', sans-serif; font-size: .92rem; color: #fff; transition: border-color var(--auth-motion-med), box-shadow var(--auth-motion-med), background-color var(--auth-motion-med), transform var(--auth-motion-med); outline: none;
    }
    .form-input.has-icon { padding-left: 40px; }
    .form-input:focus { border-color: #52B788; box-shadow: 0 0 0 3px rgba(82,183,136,.2); background: rgba(255,255,255,.09); transform: translateY(-1px); }
    .form-input::placeholder { color: rgba(255,255,255,.35); }
    .input-icon { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #74C69D; }
    .pwd-toggle { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,.55); background: none; border: none; cursor: pointer; padding: 2px; display: flex; transition: color .2s; }
    .pwd-toggle:hover { color: #74C69D; }
    .captcha-card {
      display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 10px; align-items: center;
      background: rgba(82,183,136,.1); border: 1.5px solid rgba(149,213,178,.24); border-radius: 12px;
      padding: 10px 12px; margin-bottom: 11px;
    }
    .captcha-icon {
      width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center;
      background: rgba(82,183,136,.18); color: #74C69D; border: 1px solid rgba(149,213,178,.3);
    }
    .captcha-label { font-size: .76rem; font-weight: 800; color: rgba(255,255,255,.72); margin-bottom: 5px; }
    .captcha-image {
      display: block; width: 100%; height: 64px; border-radius: 13px;
      border: 1px solid rgba(149,213,178,.34); margin-bottom: 8px; background: #eef8f2;
    }

    .form-row-split { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
    .checkbox-label { display: flex; align-items: center; gap: 9px; font-size: .85rem; color: rgba(255,255,255,.8); cursor: pointer; user-select: none; }
    .custom-checkbox { display: none; }
    .checkbox-custom { width: 17px; height: 17px; border: 2px solid rgba(149,213,178,.4); border-radius: 5px; background: rgba(255,255,255,.05); display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: all .18s; }
    .custom-checkbox:checked + .checkbox-custom { background: #52B788; border-color: #52B788; }
    .custom-checkbox:checked + .checkbox-custom::after { content: ''; width: 9px; height: 5px; border: 2px solid white; border-top: none; border-right: none; transform: rotate(-45deg) translateY(-1px); display: block; }
    .forgot-link { font-size: .85rem; font-weight: 600; color: #74C69D; text-decoration: none; transition: color .2s; }
    .forgot-link:hover { color: #95D5B2; }

    .btn-login {
      width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 11px;
      background: #E8A73D; color: #1a3a2a; border: none; border-radius: 10px; font-family: 'Inter', sans-serif;
      font-size: .95rem; font-weight: 800; cursor: pointer; transition: transform var(--auth-motion-med), box-shadow var(--auth-motion-med), background-color var(--auth-motion-med); margin-bottom: 12px;
      box-shadow: 0 10px 24px rgba(232,167,61,.28);
    }
    .btn-login:hover { background: #f0b559; transform: translateY(-3px); box-shadow: 0 16px 34px rgba(232,167,61,.42); }
    .btn-login:active { transform: translateY(0) scale(.985); }

    .form-divider { display: flex; align-items: center; gap: 12px; margin: 0 0 12px; color: rgba(255,255,255,.35); font-size: .8rem; }
    .form-divider::before, .form-divider::after { content: ''; flex: 1; height: 1px; background: rgba(149,213,178,.2); }

    .demo-accounts {
      display: grid;
      gap: 8px;
      margin: 0 0 12px;
    }
    .demo-title {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      color: rgba(255,255,255,.7);
      font-size: .76rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: .08em;
    }
    .demo-password {
      color: #E8A73D;
      font-family: 'DM Mono', ui-monospace, SFMono-Regular, Consolas, monospace;
      font-size: .72rem;
      font-weight: 700;
      text-transform: none;
      letter-spacing: 0;
    }
    .demo-list {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 7px;
    }
    .demo-account {
      min-width: 0;
      border: 1px solid rgba(149,213,178,.24);
      border-radius: 10px;
      background: rgba(255,255,255,.06);
      color: rgba(255,255,255,.82);
      cursor: pointer;
      display: grid;
      gap: 3px;
      padding: 8px 9px;
      text-align: left;
      transition: background-color var(--auth-motion-med), border-color var(--auth-motion-med), transform var(--auth-motion-med);
    }
    .demo-account:hover {
      background: rgba(82,183,136,.14);
      border-color: rgba(149,213,178,.44);
      transform: translateY(-2px);
    }
    .demo-role {
      color: #95D5B2;
      font-size: .73rem;
      font-weight: 800;
      line-height: 1.15;
    }
    .demo-email {
      color: rgba(255,255,255,.54);
      font-family: 'DM Mono', ui-monospace, SFMono-Regular, Consolas, monospace;
      font-size: .64rem;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .login-register { text-align: center; font-size: .86rem; color: rgba(255,255,255,.55); margin: 0; }
    .login-register a { color: #E8A73D; font-weight: 700; text-decoration: none; transition: color .2s; }
    .login-register a:hover { color: #f0b559; }

    /* -- WEATHER WIDGET ---------------------------------- */
    .weather-card {
      flex: 0 1 420px;
      background: rgba(15,31,23,.64);
      border: 1.5px solid rgba(149,213,178,.2);
      border-radius: 22px;
      box-shadow: 0 20px 48px rgba(0,0,0,.38);
      backdrop-filter: blur(16px);
      padding: 30px 32px 26px;
      color: #fff;
      animation: authSceneEnter 680ms var(--auth-ease-out) both;
      animation-delay: 90ms;
      transition: transform var(--auth-motion-med), box-shadow var(--auth-motion-med), border-color var(--auth-motion-med);
    }
    .weather-card:hover { transform: translateY(-4px); box-shadow: 0 26px 58px rgba(0,0,0,.42); border-color: rgba(149,213,178,.34); }
    .weather-title { font-family: 'DM Serif Display', Georgia, serif; font-size: 1.1rem; margin-bottom: 3px; }
    .weather-loc { display: flex; align-items: center; gap: 6px; font-size: .8rem; color: #74C69D; font-weight: 600; margin-bottom: 12px; }
    .weather-main { text-align: center; margin-bottom: 10px; }
    .weather-emoji { font-size: 2rem; line-height: 1; margin-bottom: 5px; }
    .weather-temp { font-family: 'DM Serif Display', Georgia, serif; font-size: 1.9rem; color: #fff; line-height: 1; }
    .weather-cond { font-size: .84rem; color: #95D5B2; font-weight: 600; margin-top: 3px; }
    .weather-divider { height: 1px; background: rgba(149,213,178,.18); margin: 2px 0 10px; }
    .weather-rows { display: flex; flex-direction: column; gap: 8px; margin-bottom: 10px; }
    .weather-row { display: flex; align-items: center; gap: 12px; }
    .weather-row-icon { color: #74C69D; flex-shrink: 0; width: 20px; }
    .weather-row-val { font-weight: 700; font-size: .93rem; color: #fff; }
    .weather-row-label { font-size: .72rem; color: rgba(255,255,255,.5); }
    .weather-updated { display: flex; align-items: center; gap: 6px; font-size: .72rem; color: rgba(255,255,255,.4); }
    .weather-updated-dot { width: 6px; height: 6px; border-radius: 50%; background: #52B788; box-shadow: 0 0 6px rgba(82,183,136,.7); }

    /* -- BELOW-CARD TAGLINE ------------------------------ */
    .scene-tagline { position: relative; z-index: 1; text-align: center; margin-top: 12px; width: 100%; }
    .scene-tagline-row { display: inline-flex; align-items: center; gap: 8px; color: rgba(255,255,255,.62); font-size: .88rem; font-weight: 600; }
    .scene-copyright { margin-top: 5px; font-size: .76rem; color: rgba(255,255,255,.32); }

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
      .demo-list { grid-template-columns: 1fr; }
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
  @php
    $popupType = $errors->any() ? 'error' : (session('success') ? 'success' : (session('status') ? 'info' : null));
    $popupTitle = $popupType === 'error' ? 'Login failed' : ($popupType === 'success' ? 'Action successful' : 'Notice');
    $popupMessage = session('success') ?: session('status') ?: ($errors->any() ? $errors->first() : null);
  @endphp
  @if($popupType && $popupMessage)
    <div id="authActionPopup" class="auth-popup show" role="alertdialog" aria-modal="true" aria-labelledby="authPopupTitle">
      <div class="auth-popup-card {{ $popupType }}">
        <div class="auth-popup-body">
          <div class="auth-popup-icon" aria-hidden="true">{{ $popupType === 'success' ? '✓' : ($popupType === 'error' ? '!' : 'i') }}</div>
          <div>
            <h2 id="authPopupTitle" class="auth-popup-title">{{ $popupTitle }}</h2>
            <p class="auth-popup-message">{{ $popupMessage }}</p>
          </div>
        </div>
        <button type="button" class="auth-popup-close" data-auth-popup-close>OK</button>
        <div class="auth-popup-timer" aria-hidden="true"><span></span></div>
      </div>
    </div>
  @endif
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

      @if(($demoAccounts ?? collect())->isNotEmpty())
        <div class="demo-accounts" aria-label="Demo accounts">
          <div class="demo-title">
            <span>Demo accounts</span>
            <span class="demo-password">Password: {{ $demoPassword }}</span>
          </div>
          <div class="demo-list">
            @foreach($demoAccounts as $demoAccount)
              <button type="button" class="demo-account" data-demo-email="{{ $demoAccount->email }}" data-demo-password="{{ $demoPassword }}">
                <span class="demo-role">{{ $demoAccount->role }}</span>
                <span class="demo-email">{{ $demoAccount->email }}</span>
              </button>
            @endforeach
          </div>
        </div>
      @endif

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
        Updated {{ $weatherUpdatedAt->format('g:i A') }}
      </div>
    </div>
  </div>

  <div class="scene-tagline">
    <div class="scene-tagline-row">
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 1c2.4 3.4 4.4 6.2 4.4 8.9A4.4 4.4 0 1 1 3.6 9.9C3.6 7.2 5.6 4.4 8 1z" stroke="#74C69D" stroke-width="1.4"/></svg>
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
    const emailInput = document.getElementById('email');

    toggle?.addEventListener('click', () => {
      const isPassword = passwordInput.type === 'password';
      passwordInput.type = isPassword ? 'text' : 'password';
      toggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
    });

    document.querySelectorAll('[data-demo-email]').forEach((button) => {
      button.addEventListener('click', () => {
        emailInput.value = button.dataset.demoEmail || '';
        passwordInput.value = button.dataset.demoPassword || '';
        passwordInput.type = 'password';
        emailInput.focus();
      });
    });

    form?.addEventListener('submit', () => {
      loginBtn.disabled = true;
      loginBtn.innerHTML = 'Signing in...';
    });

    const popup = document.getElementById('authActionPopup');
    if (popup) {
      const close = () => popup.classList.remove('show');
      popup.querySelector('[data-auth-popup-close]')?.addEventListener('click', close);
      popup.addEventListener('click', (event) => {
        if (event.target === popup) close();
      });
      setTimeout(close, 5200);
    }
  });
</script>
</body>
</html>
