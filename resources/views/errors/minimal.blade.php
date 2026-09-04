@php
    $code = $code ?? (isset($exception) && method_exists($exception, 'getStatusCode') ? (string) $exception->getStatusCode() : '');
    $title = $title ?? 'Something Went Wrong';
    $message = $message ?? 'An unexpected error occurred. Please try again in a moment.';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>{{ $code }} | {{ config('app.name', 'iClimate') }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Inter:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    html, body { margin: 0; min-height: 100%; }
    body {
      font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: linear-gradient(145deg, #f6f9f7 0%, #e7f0ea 60%, #eef5f1 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      position: relative;
      overflow: hidden;
    }
    .err-blob { position: absolute; border-radius: 50%; filter: blur(80px); pointer-events: none; }
    .err-blob-1 { width: 420px; height: 420px; background: rgba(82,183,136,0.14); top: -80px; left: -80px; }
    .err-blob-2 { width: 320px; height: 320px; background: rgba(45,106,79,0.1); bottom: -60px; right: -60px; }
    .err-grid { position: absolute; inset: 0; background-image: radial-gradient(rgba(45,106,79,0.12) 1px, transparent 1px); background-size: 32px 32px; pointer-events: none; }
    .err-card { position: relative; z-index: 1; width: 100%; max-width: 460px; background: rgba(255,255,255,0.92); border: 1px solid rgba(149,213,178,.35); border-radius: 24px; padding: 44px 40px; text-align: center; backdrop-filter: blur(10px); box-shadow: 0 20px 48px rgba(13,31,24,.14); }
    .err-logo { display: inline-flex; align-items: center; gap: 10px; text-decoration: none; margin-bottom: 32px; }
    .err-logo img { height: 40px; width: auto; object-fit: contain; }
    .err-code { font-family: 'DM Mono', monospace; font-size: 0.78rem; font-weight: 500; letter-spacing: 0.14em; text-transform: uppercase; color: #2D6A4F; margin-bottom: 14px; display: inline-flex; align-items: center; gap: 8px; }
    .err-code::before { content: none; }
    .err-title { font-family: 'DM Serif Display', Georgia, serif; font-size: 1.9rem; font-weight: 400; color: #1f2a24; margin-bottom: 14px; letter-spacing: -0.02em; }
    .err-message { font-size: 0.95rem; color: #4a5c52; line-height: 1.65; margin-bottom: 32px; }
    .err-actions { display: flex; align-items: center; justify-content: center; gap: 12px; flex-wrap: wrap; }
    .err-btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 26px; border-radius: 100px; font-size: 0.875rem; font-weight: 600; text-decoration: none; transition: all 0.2s; white-space: nowrap; }
    .err-btn-primary { background: #1F5A46; color: #0D1F18; }
    .err-btn-primary:hover { background: #123F32; transform: translateY(-1px); }
    .err-btn-outline { border: 1.5px solid rgba(31,42,36,0.18); color: #1f2a24; }
    .err-btn-outline:hover { border-color: rgba(31,42,36,0.45); color: #122b20; }
    .err-foot { margin-top: 32px; padding-top: 24px; border-top: 1px solid #e3ece6; font-size: 0.78rem; color: #6b7c72; }
  </style>
</head>
<body>
  <div class="err-blob err-blob-1"></div>
  <div class="err-blob err-blob-2"></div>
  <div class="err-grid"></div>

  <div class="err-card">
    <a href="{{ url('/') }}" class="err-logo">
      <img src="{{ asset('images/iclimate-logo.png') }}" alt="iClimate">
    </a>

    <div class="err-code">Error {{ $code }}</div>
    <h1 class="err-title">{{ $title }}</h1>
    <p class="err-message">{{ $message }}</p>

    <div class="err-actions">
      @auth
        <a href="{{ route(auth()->user()->dashboardRoute()) }}" class="err-btn err-btn-primary">Back to Dashboard</a>
      @else
        <a href="{{ url('/') }}" class="err-btn err-btn-primary">Back to Home</a>
        <a href="{{ route('login') }}" class="err-btn err-btn-outline">Sign In</a>
      @endauth
    </div>

    <div class="err-foot">&copy; 2026 iClimate &middot; Lian, Batangas</div>
  </div>
</body>
</html>
