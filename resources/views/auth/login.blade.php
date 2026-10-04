@php
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
    $loginWeather = $loginWeather ?? [];
    $hasRealWeather = $loginWeather['has_real_weather'] ?? false;
    $temperature = $loginWeather['temperature'] ?? null;
    $humidity = $loginWeather['humidity'] ?? null;
    $windSpeed = $loginWeather['wind_speed'] ?? null;
    $rainfall = $loginWeather['rainfall'] ?? null;
    $conditionLabel = $loginWeather['condition_label'] ?? 'Weather data unavailable';
    $conditionEmoji = $loginWeather['condition_emoji'] ?? '🌦️';
    $updatedLabel = $loginWeather['updated_label'] ?? 'No live or stored weather available';
    $weatherUnavailable = 'Weather information is temporarily unavailable.';
    // Sign-in failures arrive on the email key; wrong credentials get plain wording, other messages (lockout, inactive account) are shown as sent.
    $signInError = $errors->first('email');
    if ($signInError === trans('auth.failed')) {
        $signInError = 'Unable to sign in. Please check your email and password.';
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_' , '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Sign In | {{ config('app.name', 'iClimate') }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    :root {
      --reg-green-dark: #174F3C;
      --reg-green: #1F684F;
      --reg-green-2: #2E7D5B;
      --reg-green-bg: #F2F7F4;
      --reg-border: #D7E3DC;
      --reg-input-border: #CBD9D1;
      --reg-text: #24312B;
      --reg-text-2: #65736C;
      --reg-error: #B42318;
      --reg-success: #16794A;
      --reg-serif: Georgia, 'Times New Roman', serif;
      --reg-sans: 'Inter', Arial, system-ui, -apple-system, 'Segoe UI', sans-serif;
    }
    html, body { margin: 0; min-height: 100%; }
    body { font-family: var(--reg-sans); font-size: 14px; line-height: 1.5; color: var(--reg-text); }
    a { color: inherit; }
    button, input { font: inherit; }
    h1, h2, p, dl, dd { margin: 0; }

    /* -- PAGE BACKGROUND ---------------------------------- */
    body.login-page {
      min-height: 100vh;
      background-color: var(--reg-green-dark);
      background-image:
        linear-gradient(rgba(13,65,47,.76), rgba(13,65,47,.76)),
        url('{{ asset('images/rice-hero-aerial.jpg') }}');
      background-position: center;
      background-size: cover;
      background-repeat: no-repeat;
      background-attachment: fixed;
      display: flex;
      flex-direction: column;
    }
    .login-shell { flex: 1 1 auto; width: 100%; max-width: 1240px; margin: 0 auto; padding: 24px 32px; display: flex; flex-direction: column; }

    .back-link-top {
      align-self: flex-start; display: inline-flex; align-items: center; gap: 8px;
      font-size: 14px; font-weight: 500; color: #E3F0E9; text-decoration: none; border-radius: 4px;
    }
    .back-link-top:hover { color: #fff; text-decoration: underline; text-underline-offset: 3px; }
    .back-link-top:focus-visible { outline: 2px solid #fff; outline-offset: 3px; }

    .login-main { flex: 1 1 auto; display: flex; align-items: center; justify-content: center; gap: 32px; padding: 24px 0; }

    /* -- SIGN IN CARD -------------------------------------- */
    .login-card {
      flex: 0 1 480px; min-width: 0;
      background: #fff; border: 1px solid var(--reg-border); border-radius: 14px;
      box-shadow: 0 8px 24px rgba(10,35,26,.18); padding: 36px;
    }
    .card-logo { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 20px; }
    .card-logo img { height: 40px; width: auto; display: block; }
    .card-logo-word { font-weight: 700; font-size: 20px; color: var(--reg-green-dark); }

    .login-form-header { text-align: center; margin-bottom: 24px; }
    .login-form-header h1 { font-family: var(--reg-serif); font-size: 28px; font-weight: 700; color: var(--reg-green-dark); line-height: 1.2; margin-bottom: 8px; }
    .login-form-header p { font-size: 15px; color: var(--reg-text-2); }
    .access-note { display: inline-flex; align-items: center; gap: 6px; margin-top: 12px; font-size: 13px; color: var(--reg-text-2); }
    .access-note svg { color: var(--reg-green-2); flex-shrink: 0; }

    .auth-status, .auth-alert { display: flex; align-items: flex-start; gap: 10px; border-radius: 8px; padding: 10px 14px; font-size: 14px; margin-bottom: 16px; }
    .auth-status { background: var(--reg-green-bg); border: 1px solid var(--reg-border); color: var(--reg-green-dark); }
    .auth-alert { background: #FEF3F2; border: 1px solid #F4C7C3; color: var(--reg-error); }
    .auth-alert[hidden] { display: none; }
    .auth-alert svg { flex-shrink: 0; margin-top: 2px; }

    .form-group + .form-group, .form-group + .captcha-card { margin-top: 16px; }
    .form-group label { display: block; font-size: 14px; font-weight: 600; color: var(--reg-text); margin-bottom: 6px; }
    .input-wrap { position: relative; }
    .form-input {
      width: 100%; height: 46px; background: #fff; border: 1px solid var(--reg-input-border); border-radius: 8px;
      padding: 0 14px; font-size: 15px; font-weight: 400; color: var(--reg-text); outline: none;
      transition: border-color .15s ease, box-shadow .15s ease;
    }
    .form-input.has-icon { padding-left: 40px; }
    .form-input.has-toggle { padding-right: 46px; }
    .form-input::placeholder { color: #8A968F; }
    .form-input:focus { border-color: var(--reg-green); box-shadow: 0 0 0 3px rgba(31,104,79,.10); }
    .form-input.is-invalid { border-color: var(--reg-error); }
    .form-input.is-invalid:focus { box-shadow: 0 0 0 3px rgba(180,35,24,.10); }
    .input-icon { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #6E8A7D; }
    .pwd-toggle {
      position: absolute; right: 6px; top: 50%; transform: translateY(-50%); width: 34px; height: 34px;
      display: flex; align-items: center; justify-content: center;
      color: var(--reg-text-2); background: none; border: none; border-radius: 6px; cursor: pointer;
    }
    .pwd-toggle:hover { color: var(--reg-green); }
    .pwd-toggle-slash[hidden] { display: none; }
    .pwd-toggle:focus-visible, .login-card a:focus-visible, .checkbox-label input:focus-visible { outline: 2px solid var(--reg-green); outline-offset: 2px; }

    .field-error { display: flex; align-items: flex-start; gap: 6px; font-size: 13px; line-height: 1.4; margin-top: 6px; color: var(--reg-error); }
    .field-error[hidden] { display: none; }
    .field-error::before {
      content: ''; width: 14px; height: 14px; flex-shrink: 0; margin-top: 2px; background-repeat: no-repeat; background-position: center;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 14 14' fill='none'%3E%3Ccircle cx='7' cy='7' r='6' stroke='%23B42318' stroke-width='1.4'/%3E%3Cpath d='M7 4v3.5' stroke='%23B42318' stroke-width='1.4' stroke-linecap='round'/%3E%3Ccircle cx='7' cy='9.8' r='.8' fill='%23B42318'/%3E%3C/svg%3E");
    }

    .captcha-card { background: var(--reg-green-bg); border: 1px solid var(--reg-border); border-radius: 8px; padding: 14px; text-align: center; }
    .captcha-card label { display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 14px; font-weight: 600; color: var(--reg-text); margin-bottom: 10px; }
    .captcha-card label svg { color: var(--reg-green-2); }
    .captcha-image { display: block; height: 56px; width: auto; max-width: 100%; margin: 0 auto 10px; border: 1px solid var(--reg-input-border); border-radius: 8px; background: #fff; }
    .captcha-field { max-width: 320px; margin: 0 auto; }
    .captcha-field .form-input { text-align: center; text-transform: uppercase; letter-spacing: .12em; }
    .captcha-field .form-input::placeholder { text-transform: none; letter-spacing: normal; }
    .captcha-field .field-error { justify-content: center; text-align: left; }

    .form-row-split { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px 16px; margin-top: 12px; }
    .checkbox-label { display: inline-flex; align-items: center; gap: 8px; font-size: 14px; color: var(--reg-text); cursor: pointer; user-select: none; }
    .checkbox-label input { width: 18px; height: 18px; margin: 0; flex-shrink: 0; accent-color: var(--reg-green); cursor: pointer; }
    .forgot-link, .login-register a { color: var(--reg-green); font-weight: 600; text-decoration: none; border-radius: 3px; }
    .forgot-link { font-size: 14px; }
    .forgot-link:hover, .login-register a:hover { color: var(--reg-green-dark); text-decoration: underline; text-underline-offset: 3px; }

    .btn-login {
      width: 100%; height: 48px; margin-top: 20px; display: flex; align-items: center; justify-content: center; gap: 10px;
      background: var(--reg-green); color: #fff; border: none; border-radius: 8px;
      font-size: 15px; font-weight: 600; cursor: pointer; transition: background-color .15s ease;
    }
    .btn-login:hover { background: var(--reg-green-dark); }
    .btn-login:focus-visible { outline: 3px solid rgba(31,104,79,.45); outline-offset: 2px; }
    .btn-login:disabled { opacity: .8; cursor: wait; }
    .btn-spinner { width: 16px; height: 16px; border-radius: 50%; border: 2px solid rgba(255,255,255,.4); border-top-color: #fff; animation: regSpin .8s linear infinite; }
    @keyframes regSpin { to { transform: rotate(360deg); } }

    .login-register { text-align: center; font-size: 14px; color: var(--reg-text-2); margin-top: 20px; }

    /* -- WEATHER CARD -------------------------------------- */
    .weather-card {
      flex: 0 1 380px; min-width: 0;
      background: rgba(13,46,34,.90); border: 1px solid rgba(255,255,255,.18); border-radius: 14px;
      box-shadow: 0 8px 24px rgba(10,35,26,.18); padding: 28px; color: #fff;
    }
    .weather-title { font-family: var(--reg-serif); font-size: 19px; font-weight: 700; line-height: 1.3; color: #fff; }
    .weather-loc { display: flex; align-items: center; gap: 6px; font-size: 14px; color: #BFE3D0; margin-top: 4px; }
    .weather-main { display: flex; align-items: center; gap: 16px; margin: 20px 0; }
    .weather-icon { flex-shrink: 0; width: 52px; height: 52px; display: grid; place-items: center; color: #BFE3D0; font-size: 34px; line-height: 1; }
    .weather-temp { font-family: var(--reg-serif); font-size: 32px; font-weight: 700; line-height: 1.1; color: #fff; }
    .weather-cond { font-size: 15px; font-weight: 600; color: #BFE3D0; margin-top: 4px; }
    .weather-card.is-unavailable .weather-cond { font-weight: 400; color: rgba(255,255,255,.86); margin-top: 0; }
    .weather-card.is-unavailable .weather-temp { display: none; }
    .weather-rows { border-top: 1px solid rgba(255,255,255,.14); }
    .weather-row { display: flex; align-items: center; gap: 10px; padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,.14); }
    .weather-row svg { flex-shrink: 0; color: #9FD3BA; }
    .weather-row dt { flex: 1; font-size: 12px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: rgba(255,255,255,.74); }
    .weather-row dd { font-size: 16px; font-weight: 600; color: #fff; }
    .weather-updated { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #BFE3D0; margin-top: 16px; }
    .weather-updated-dot { width: 6px; height: 6px; border-radius: 50%; background: #9FD3BA; flex-shrink: 0; }
    .weather-card.is-unavailable .weather-updated-dot { background: rgba(255,255,255,.45); }

    /* -- FOOTER -------------------------------------------- */
    .login-foot { flex: 0 0 auto; background: rgba(12,42,31,.9); border-top: 1px solid rgba(255,255,255,.12); padding: 12px 32px; }
    .login-foot-inner { max-width: 1176px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; gap: 16px 32px; flex-wrap: wrap; }
    .rf-brand { display: flex; align-items: center; gap: 10px; }
    .rf-brand img { width: 22px; height: 22px; object-fit: contain; }
    .rf-brand-text { display: flex; flex-direction: column; line-height: 1.4; }
    .rf-brand-text strong, .rf-badge { font-size: 13px; font-weight: 600; color: rgba(255,255,255,.92); }
    .rf-brand-text span { font-size: 12px; color: rgba(255,255,255,.68); }
    .rf-badges { display: flex; gap: 10px 24px; flex-wrap: wrap; }
    .rf-badge { display: flex; align-items: center; gap: 8px; font-weight: 500; }
    .rf-badge svg { color: #9FD3BA; flex-shrink: 0; }

    /* -- RESPONSIVE ---------------------------------------- */
    @media (max-width: 1024px) {
      .login-shell { padding: 20px 24px; }
      .login-main { gap: 24px; }
      .login-card { padding: 32px; }
      .weather-card { flex-basis: 340px; padding: 24px; }
    }
    @media (max-width: 820px) {
      body.login-page { background-attachment: scroll; }
      .login-main { flex-direction: column; align-items: center; justify-content: flex-start; padding: 16px 0 8px; }
      .login-card, .weather-card { flex: 0 0 auto; width: 100%; max-width: 480px; }
    }
    @media (max-width: 560px) {
      .login-shell { padding: 16px; }
      .login-card, .weather-card { padding: 24px; }
      .login-foot { padding: 14px 16px; }
      .login-foot-inner { flex-direction: column; align-items: flex-start; }
    }
    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after { transition-duration: .01ms !important; }
      .btn-spinner { animation-duration: 2s; }
    }
  </style>
    @include('partials.global-loader')
</head>
<body class="login-page">
  {{-- Sign-in errors are shown inline in the card, so the toast is kept for session flashes only. --}}
  @if (session('success') || session('error'))
    <x-flash-toast />
  @endif

  <div class="login-shell">
    <a href="{{ url('/') }}" class="back-link-top">
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M13 8H3M7 12L3 8l4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Back to Home
    </a>

    <main class="login-main">
      <div class="login-card">
        <div class="card-logo">
          <img src="{{ asset('images/iclimate-icon.png') }}" alt="">
          <div class="card-logo-word">iClimate</div>
        </div>

        <div class="login-form-header">
          <h1>Sign In</h1>
          <p>Access your iClimate account.</p>
          <div class="access-note">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="3" y="7" width="10" height="7" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M5 7V5a3 3 0 016 0v2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
            Secure Account Access
          </div>
        </div>

        @if (session('status'))
          <div class="auth-status" role="status">{{ session('status') }}</div>
        @endif

        <div class="auth-alert" id="signInAlert" role="alert" @unless($signInError) hidden @endunless>
          <svg width="16" height="16" viewBox="0 0 14 14" fill="none" aria-hidden="true"><circle cx="7" cy="7" r="6" stroke="currentColor" stroke-width="1.4"/><path d="M7 4v3.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="7" cy="9.8" r=".8" fill="currentColor"/></svg>
          <span>{{ $signInError }}</span>
        </div>

        <form method="POST" action="{{ route('login') }}" class="login-form" data-loader-text="Signing In...">
          @csrf

          <div class="form-group">
            <label for="email">Email Address</label>
            <div class="input-wrap">
              <svg class="input-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="1.5" y="3" width="13" height="10" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M2 5l6 4.2L14 5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
              <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-input has-icon" placeholder="Enter your email address" autocomplete="username" aria-describedby="email-error" required autofocus/>
            </div>
            <p class="field-error" id="email-error" hidden></p>
          </div>

          <div class="form-group">
            <label for="password">Password</label>
            <div class="input-wrap">
              <svg class="input-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="3" y="7" width="10" height="7" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M5 7V5a3 3 0 016 0v2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
              <input type="password" id="password" name="password" class="form-input has-icon has-toggle @error('password') is-invalid @enderror" placeholder="Enter your password" autocomplete="current-password" aria-describedby="password-error" @error('password') aria-invalid="true" @enderror required/>
              <button class="pwd-toggle" id="pwdToggle" type="button" aria-label="Show password" aria-pressed="false">
                <svg width="18" height="18" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M1 8C2.5 4.5 5 3 8 3s5.5 1.5 7 5c-1.5 3.5-4 5-7 5S2.5 11.5 1 8z" stroke="currentColor" stroke-width="1.4"/><circle cx="8" cy="8" r="2" stroke="currentColor" stroke-width="1.4"/><path class="pwd-toggle-slash" d="M2.5 2.5l11 11" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" hidden/></svg>
              </button>
            </div>
            <p class="field-error" id="password-error" @unless($errors->has('password')) hidden @endunless>{{ $errors->first('password') }}</p>
          </div>

          @if($requiresCaptcha ?? false)
          <div class="captcha-card">
            <label for="captcha_answer">
              <svg width="16" height="16" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M9 2l5 2v4.2c0 3.1-1.9 5.8-5 7.1-3.1-1.3-5-4-5-7.1V4l5-2z" stroke="currentColor" stroke-width="1.5"/><path d="M6.8 9l1.4 1.4 3.2-3.4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
              Security Check
            </label>
            <img class="captcha-image" width="190" height="64" src="{{ route('captcha.image', ['context' => 'login', 'v' => $captcha['token'] ?? now()->timestamp]) }}" alt="Security code image">
            <div class="captcha-field">
              <input type="text" id="captcha_answer" name="captcha_answer" class="form-input @error('captcha_answer') is-invalid @enderror" placeholder="Enter the code shown above" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="20" aria-describedby="captcha_answer-error" @error('captcha_answer') aria-invalid="true" @enderror required/>
              <p class="field-error" id="captcha_answer-error" @unless($errors->has('captcha_answer')) hidden @endunless>{{ $errors->first('captcha_answer') }}</p>
            </div>
          </div>
          @endif

          <div class="form-row-split">
            <label class="checkbox-label" for="remember_me">
              <input type="checkbox" id="remember_me" name="remember" checked/>
              Remember Me
            </label>
            @if (Route::has('password.request'))<a href="{{ route('password.request') }}" class="forgot-link">Forgot Password?</a>@endif
          </div>

          <button class="btn-login" id="loginBtn" type="submit">Sign In</button>
        </form>

        <p class="login-register">Don't have an account? <a href="{{ route('register') }}">Create Account</a></p>
      </div>

      <section class="weather-card {{ $hasRealWeather ? '' : 'is-unavailable' }}" id="weatherCard" aria-labelledby="weatherTitle" data-weather-endpoint="{{ route('login.weather') }}">
        <h2 class="weather-title" id="weatherTitle">Today's Weather</h2>
        <div class="weather-loc">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 14s5-3.8 5-8A5 5 0 0 0 3 6c0 4.2 5 8 5 8Z" stroke="currentColor" stroke-width="1.4"/><circle cx="8" cy="6" r="1.7" stroke="currentColor" stroke-width="1.4"/></svg>
          Lian, Batangas
        </div>

        <div class="weather-main">
          {{-- Starts as the provider's emoji and is swapped for a matching line icon by the page script. --}}
          <div class="weather-icon" id="weatherEmoji" aria-hidden="true">{{ $hasRealWeather ? $conditionEmoji : '🌦️' }}</div>
          <div>
            <div class="weather-temp" id="weatherTemp">{{ $temperature !== null ? $temperature.'°C' : '--' }}</div>
            <div class="weather-cond" id="weatherCond">{{ $hasRealWeather ? $conditionLabel : $weatherUnavailable }}</div>
          </div>
        </div>

        <dl class="weather-rows">
          <div class="weather-row">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M9 2.5c2 2.6 3.6 4.8 3.6 7a3.6 3.6 0 1 1-7.2 0c0-2.2 1.6-4.4 3.6-7Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>
            <dt>Humidity</dt>
            <dd id="weatherHumidity">{{ $humidity !== null ? $humidity.'%' : '--' }}</dd>
          </div>
          <div class="weather-row">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M2 6.5h8a2 2 0 1 0-2-2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M2 10.5h11a2.2 2.2 0 1 1-2.2 2.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
            <dt>Wind Speed</dt>
            <dd id="weatherWind">{{ $windSpeed !== null ? $windSpeed.' km/h' : '--' }}</dd>
          </div>
          <div class="weather-row">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M5 10.5h8a3 3 0 0 0 .4-5.97A4.2 4.2 0 0 0 5.3 5.6 2.5 2.5 0 0 0 5 10.5Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M6.5 13v2M9 13v2M11.5 13v2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
            <dt>Rainfall Today</dt>
            <dd id="weatherRainfall">{{ $rainfall !== null ? number_format((float) $rainfall, 1).' mm' : '--' }}</dd>
          </div>
        </dl>

        <div class="weather-updated">
          <span class="weather-updated-dot" aria-hidden="true"></span>
          <span id="weatherUpdatedLabel">{{ $updatedLabel }}</span>
        </div>
      </section>
    </main>
  </div>

  <footer class="login-foot">
    <div class="login-foot-inner">
      <div class="rf-brand">
        <img src="{{ asset('images/iclimate-icon.png') }}" alt="">
        <div class="rf-brand-text">
          <strong>iClimate &middot; Climate and Farming Information System for Lian, Batangas</strong>
          <span>&copy; 2026 iClimate Research Group &middot; Batangas State University ARASOF-Nasugbu</span>
        </div>
      </div>
      <div class="rf-badges">
        <div class="rf-badge">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 1.5l5.5 2v3.8c0 3.6-2.4 6.1-5.5 7-3.1-.9-5.5-3.4-5.5-7V3.5l5.5-2z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>
          Secure Access
        </div>
        <div class="rf-badge">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 14s5-3.8 5-8A5 5 0 0 0 3 6c0 4.2 5 8 5 8Z" stroke="currentColor" stroke-width="1.4"/><circle cx="8" cy="6" r="1.7" stroke="currentColor" stroke-width="1.4"/></svg>
          Local Climate Information
        </div>
        <div class="rf-badge">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 14V7M8 9C8 5.5 10 3 13.5 2.5 13.5 6 11.5 8.5 8 9ZM8 11C8 8.5 6.5 6.5 3 6c0 3 1.5 4.7 5 5Z" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Climate-Smart Farming
        </div>
      </div>
    </div>
  </footer>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.login-form');
    const email = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const captchaAnswer = document.getElementById('captcha_answer');
    const toggle = document.getElementById('pwdToggle');
    const loginBtn = document.getElementById('loginBtn');

    // Inline messages replace the browser's validation bubbles; the server still validates everything.
    form.noValidate = true;

    const setError = (field, message) => {
      const slot = document.getElementById(`${field.id}-error`);
      field.classList.toggle('is-invalid', Boolean(message));
      if (message) field.setAttribute('aria-invalid', 'true'); else field.removeAttribute('aria-invalid');
      if (slot) {
        slot.textContent = message || '';
        slot.hidden = ! message;
      }
    };

    const validators = new Map([
      [email, () => {
        const value = email.value.trim();
        if (! value) return 'Please enter your email address.';
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) ? '' : 'Please enter a valid email address.';
      }],
      [passwordInput, () => passwordInput.value ? '' : 'Please enter your password.'],
    ]);
    if (captchaAnswer) {
      validators.set(captchaAnswer, () => captchaAnswer.value.trim() ? '' : 'Please enter the code shown in the image.');
    }

    const validateField = (field) => {
      const message = validators.get(field)();
      setError(field, message);
      return ! message;
    };

    validators.forEach((validate, field) => {
      field.addEventListener('input', () => {
        if (field.classList.contains('is-invalid')) validateField(field);
      });
    });
    email.addEventListener('blur', () => {
      if (email.value.trim()) validateField(email);
    });

    toggle.addEventListener('click', () => {
      const isPassword = passwordInput.type === 'password';
      passwordInput.type = isPassword ? 'text' : 'password';
      toggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
      toggle.setAttribute('aria-pressed', String(isPassword));
      toggle.querySelector('.pwd-toggle-slash').toggleAttribute('hidden', ! isPassword);
    });

    form.addEventListener('submit', (event) => {
      const invalid = [...validators.keys()].filter((field) => ! validateField(field));

      if (invalid.length) {
        event.preventDefault();
        invalid[0].focus();
        return;
      }

      loginBtn.disabled = true;
      loginBtn.innerHTML = '<span class="btn-spinner" aria-hidden="true"></span>Signing In...';
    });

    // Returning with the Back button restores the page from cache: make the button usable again.
    window.addEventListener('pageshow', (event) => {
      if (! event.persisted) return;
      loginBtn.disabled = false;
      loginBtn.textContent = 'Sign In';
    });

    /* -- WEATHER ------------------------------------------- */
    const weatherCard = document.getElementById('weatherCard');
    const endpoint = weatherCard?.dataset.weatherEndpoint;
    const weatherIconEl = document.getElementById('weatherEmoji');
    const weatherUnavailable = @json($weatherUnavailable);

    const cloud = '<path d="M13 30h19a7 7 0 0 0 1-13.9A10 10 0 0 0 13.6 18.5 6 6 0 0 0 13 30Z"/>';
    const weatherIcons = {
      sun: '<circle cx="22" cy="22" r="7"/><path d="M22 6v4M22 34v4M6 22h4M34 22h4M10.7 10.7l2.8 2.8M30.5 30.5l2.8 2.8M33.3 10.7l-2.8 2.8M13.5 30.5l-2.8 2.8"/>',
      partly: '<circle cx="15" cy="14" r="5"/><path d="M15 4v2.5M5 14h2.5M7.9 6.9l1.8 1.8M22.1 6.9l-1.8 1.8"/><path d="M15 34h17a6 6 0 0 0 .8-11.9A8.5 8.5 0 0 0 16 24.2 5 5 0 0 0 15 34Z"/>',
      cloud: cloud,
      rain: cloud + '<path d="M16 34l-1.5 4M23 34l-1.5 4M30 34l-1.5 4"/>',
      thunder: cloud + '<path d="M23 31l-4 6h5l-3 5"/>',
      fog: '<path d="M13 24h19a7 7 0 0 0 1-13.9A10 10 0 0 0 13.6 12.5 6 6 0 0 0 13 24Z"/><path d="M9 30h26M13 36h18"/>',
      moon: '<path d="M33 26.5A13 13 0 0 1 17.5 11 13 13 0 1 0 33 26.5Z"/>',
    };
    const iconByEmoji = { '⛈': 'thunder', '🌧': 'rain', '🌦': 'rain', '🌫': 'fog', '🌤': 'partly', '⛅': 'cloud', '🌙': 'moon', '☀': 'sun' };

    const setWeatherIcon = (emoji) => {
      const key = iconByEmoji[String(emoji || '').replace(/️/g, '')] || 'partly';
      weatherIconEl.innerHTML = `<svg width="48" height="48" viewBox="0 0 44 44" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${weatherIcons[key]}</svg>`;
    };

    const showWeather = (data) => {
      weatherCard.classList.toggle('is-unavailable', ! data.has_real_weather);
      setWeatherIcon(data.has_real_weather ? data.condition_emoji : null);
      document.getElementById('weatherTemp').textContent = data.temperature !== null ? `${data.temperature}°C` : '--';
      document.getElementById('weatherCond').textContent = data.has_real_weather ? data.condition_label : weatherUnavailable;
      document.getElementById('weatherHumidity').textContent = data.humidity !== null ? `${data.humidity}%` : '--';
      document.getElementById('weatherWind').textContent = data.wind_speed !== null ? `${data.wind_speed} km/h` : '--';
      document.getElementById('weatherRainfall').textContent = data.rainfall !== null ? `${data.rainfall.toFixed(1)} mm` : '--';
      document.getElementById('weatherUpdatedLabel').textContent = data.updated_label ?? 'No live or stored weather available';
    };

    setWeatherIcon(weatherCard.classList.contains('is-unavailable') ? null : weatherIconEl.textContent.trim());

    if (endpoint) {
      let firstLoad = true;

      const refreshWeather = () => {
        // With nothing stored yet, say so while the first live reading is on its way.
        const waiting = firstLoad && weatherCard.classList.contains('is-unavailable');
        firstLoad = false;
        if (waiting) document.getElementById('weatherCond').textContent = 'Loading weather information...';

        // Background refresh: never raise the global loading overlay.
        fetch(endpoint, { headers: { Accept: 'application/json' }, loader: false })
          .then((response) => (response.ok ? response.json() : Promise.reject()))
          .then(showWeather)
          .catch(() => {
            // Keep the last real reading on screen; only an empty card falls back to the notice.
            if (waiting) document.getElementById('weatherCond').textContent = weatherUnavailable;
          });
      };

      refreshWeather();
      setInterval(refreshWeather, 60000);
      document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') refreshWeather();
      });
    }
  });
</script>
</body>
</html>
