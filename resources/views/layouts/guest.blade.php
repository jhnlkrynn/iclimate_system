@php
    $gxEyebrow = 'iClimate Account';
    $gxHeadline = 'Secure account <em>access</em>';
    $gxSub = 'Manage access to your iClimate account for Lian, Batangas.';

    if (request()->routeIs('password.request')) {
        $gxEyebrow = 'Account Recovery';
        $gxHeadline = 'Forgot your <em>password</em>?';
        $gxSub = "Enter your registered email address and we'll send you a secure link to reset your password.";
    } elseif (request()->routeIs('password.reset')) {
        $gxEyebrow = 'Account Recovery';
        $gxHeadline = 'Choose a new <em>password</em>';
        $gxSub = 'Create a new password to regain access to your iClimate account.';
    } elseif (request()->routeIs('password.confirm')) {
        $gxEyebrow = 'Security Check';
        $gxHeadline = "Confirm it's <em>you</em>";
        $gxSub = 'For your security, please confirm your password before continuing to this area.';
    } elseif (request()->routeIs('verification.notice')) {
        $gxEyebrow = 'Almost There';
        $gxHeadline = 'Verify your <em>email</em>';
        $gxSub = 'Check your inbox for a verification link to activate your iClimate account.';
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ config('app.name', 'iClimate') }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Inter:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    html, body { margin: 0; min-height: 100%; }
    body.login-page { font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f6f9f7; min-height: 100vh; display: flex; align-items: stretch; }
    .auth-logo-img { height: 48px; width: auto; max-width: 220px; object-fit: contain; display: block; }

    .login-layout { display: grid; grid-template-columns: 38% 62%; min-height: 100vh; width: 100%; }

    /* -- LEFT PANEL ------------------------------------ */
    .login-panel-left {
      background: linear-gradient(145deg, #f6f9f7 0%, #e7f0ea 60%, #eef5f1 100%);
      border-right: 1px solid #e3ece6;
      position: relative; overflow: hidden; display: flex; flex-direction: column;
    }
    .lpl-blob { position: absolute; border-radius: 50%; filter: blur(80px); pointer-events: none; }
    .lpl-blob-1 { width: 420px; height: 420px; background: rgba(82,183,136,0.13); top: -80px; left: -80px; }
    .lpl-blob-2 { width: 320px; height: 320px; background: rgba(45,106,79,0.1); bottom: 60px; right: -60px; }
    .lpl-grid { position: absolute; inset: 0; background-image: radial-gradient(rgba(45,106,79,0.14) 1px, transparent 1px); background-size: 32px 32px; pointer-events: none; }
    .lpl-inner { position: relative; z-index: 1; display: flex; flex-direction: column; height: 100%; padding: 44px 52px; }
    .lpl-logo { display: flex; align-items: center; gap: 10px; text-decoration: none; margin-bottom: auto; }
    .lpl-body { padding: 48px 0; flex: 1; display: flex; flex-direction: column; justify-content: center; }
    .lpl-eyebrow { font-family: 'DM Mono', monospace; font-size: 0.72rem; font-weight: 500; letter-spacing: 0.1em; text-transform: uppercase; color: #2D6A4F; margin-bottom: 20px; display: inline-flex; align-items: center; gap: 8px; }
    .lpl-eyebrow::before { content: ''; display: block; width: 20px; height: 1px; background: #2D6A4F; }
    .lpl-headline { font-family: 'DM Serif Display', Georgia, serif; font-size: clamp(1.8rem, 3vw, 2.5rem); font-weight: 400; color: #1f2a24; line-height: 1.2; margin-bottom: 16px; }
    .lpl-headline em { font-style: italic; color: #2D6A4F; }
    .lpl-sub { font-size: 0.97rem; color: #4a5c52; line-height: 1.65; max-width: 380px; }
    .lpl-foot { padding-top: 32px; border-top: 1px solid #e3ece6; font-size: 0.8rem; color: #6b7c72; }

    /* -- RIGHT PANEL ----------------------------------- */
    .login-panel-right { background: #f5f9f6; display: flex; align-items: center; justify-content: center; padding: 40px 48px; }
    .login-form-wrap { width: 100%; max-width: 460px; }
    .back-link-top { display: inline-flex; align-items: center; gap: 6px; font-size: 0.83rem; font-weight: 500; color: #5A7A64; text-decoration: none; margin-bottom: 36px; transition: color 0.2s; }
    .back-link-top:hover { color: #2D6A4F; }
    .login-form-header { margin-bottom: 28px; }
    .login-form-header h1 { font-family: 'DM Serif Display', Georgia, serif; font-size: 1.7rem; font-weight: 400; color: #1B2B23; margin-bottom: 6px; letter-spacing: -0.02em; }
    .login-form-header p { font-size: 0.92rem; color: #5A7A64; line-height: 1.6; margin: 0; }

    /* Form field spacing to match login/register pages */
    .login-form-wrap form > div { margin-bottom: 18px; }
    .login-form-wrap input[type="email"], .login-form-wrap input[type="password"], .login-form-wrap input[type="text"] {
      border-radius: 10px !important; padding: 11px 14px !important; border-width: 1.5px !important;
    }

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
    <div class="lpl-grid"></div>

    <div class="lpl-inner">
      <a href="{{ url('/') }}" class="lpl-logo">
        <img src="{{ asset('images/iclimate-logo.png') }}" alt="iClimate" class="auth-logo-img">
      </a>

      <div class="lpl-body">
        <div class="lpl-eyebrow">{{ $gxEyebrow }}</div>
        <h1 class="lpl-headline">{!! $gxHeadline !!}</h1>
        <p class="lpl-sub">{{ $gxSub }}</p>
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

      <div class="login-form-header">
        <h1>{{ str_replace(['<em>', '</em>'], '', strip_tags($gxHeadline)) }}</h1>
      </div>

      {{ $slot }}

    </div>
  </div>
</div>

</body>
</html>
