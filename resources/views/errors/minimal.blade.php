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
      background: linear-gradient(145deg, #0f1f17 0%, #1a3a2a 60%, #163324 100%);
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
    .err-blob-2 { width: 320px; height: 320px; background: rgba(45,106,79,0.28); bottom: -60px; right: -60px; }
    .err-grid { position: absolute; inset: 0; background-image: radial-gradient(rgba(82,183,136,0.16) 1px, transparent 1px); background-size: 32px 32px; pointer-events: none; }
    .err-card { position: relative; z-index: 1; width: 100%; max-width: 460px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); border-radius: 24px; padding: 44px 40px; text-align: center; backdrop-filter: blur(10px); }
    .err-logo { display: inline-flex; align-items: center; gap: 10px; text-decoration: none; margin-bottom: 32px; }
    .err-logo img { height: 40px; width: auto; object-fit: contain; }
    .err-code { font-family: 'DM Mono', monospace; font-size: 0.78rem; font-weight: 500; letter-spacing: 0.14em; text-transform: uppercase; color: #74C69D; margin-bottom: 14px; display: inline-flex; align-items: center; gap: 8px; }
    .err-code::before { content: ''; display: block; width: 20px; height: 1px; background: #74C69D; }
    .err-title { font-family: 'DM Serif Display', Georgia, serif; font-size: 1.9rem; font-weight: 400; color: #fff; margin-bottom: 14px; letter-spacing: -0.02em; }
    .err-message { font-size: 0.95rem; color: rgba(255,255,255,0.5); line-height: 1.65; margin-bottom: 32px; }
    .err-actions { display: flex; align-items: center; justify-content: center; gap: 12px; flex-wrap: wrap; }
    .err-btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 26px; border-radius: 100px; font-size: 0.875rem; font-weight: 600; text-decoration: none; transition: all 0.2s; white-space: nowrap; }
    .err-btn-primary { background: #E8A73D; color: #0D1F18; }
    .err-btn-primary:hover { background: #C6872A; transform: translateY(-1px); }
    .err-btn-outline { border: 1.5px solid rgba(255,255,255,0.25); color: rgba(255,255,255,0.85); }
    .err-btn-outline:hover { border-color: rgba(255,255,255,0.6); color: #fff; }
    .err-foot { margin-top: 32px; padding-top: 24px; border-top: 1px solid rgba(255,255,255,0.08); font-size: 0.78rem; color: rgba(255,255,255,0.25); }
  </style>
</head>
<body>
  <div class="err-blob err-blob-1"></div>
  <div class="err-blob err-blob-2"></div>
  <div class="err-grid"></div>

  <div class="err-card">
    <a href="{{ url('/') }}" class="err-logo">
      <img src="{{ asset('images/iClimate.png') }}" alt="iClimate">
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
