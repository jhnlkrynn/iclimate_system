<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'iClimate') }}</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            :root { --green-950:#0d1f18; --green-900:#122b20; --green-800:#1a3a2a; --green-700:#2d6a4f; --green-500:#52b788; --green-300:#95d5b2; --green-100:#d8f3dc; --green-50:#f0f7f4; --sand:#f5f0e8; --amber:#ffd166; --coral:#d85b45; --ink:#1b2b23; --muted:#5a7a64; --border:#d4edda; }
            body { min-height: 100vh; color: var(--ink); font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: linear-gradient(115deg, rgba(240,247,244,.96), rgba(245,240,232,.9)), radial-gradient(circle at top right, rgba(82,183,136,.14), transparent 32rem), linear-gradient(140deg, #f5f9f6 0%, #eef7ec 54%, var(--sand) 100%); }
            .auth-shell { min-height: 100vh; display: grid; place-items: center; padding: 1rem; }
            .auth-card { width: min(100%, 980px); background: rgba(255,255,255,.96); border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 1.2rem 3rem rgba(13,31,24,.12); overflow: hidden; }
            .auth-side { position: relative; overflow: hidden; background: linear-gradient(145deg, var(--green-950) 0%, var(--green-800) 62%, #163324 100%); color: #fff; min-height: 100%; }
            .auth-side::before { content: ""; position: absolute; inset: 0; background: repeating-linear-gradient(130deg, rgba(255,255,255,.12) 0 1px, transparent 1px 22px); }
            .auth-side::after { content: ""; position: absolute; left: 0; right: 0; bottom: 0; height: 8px; background: linear-gradient(90deg, var(--amber), var(--green-500), var(--green-300), var(--coral)); }
            .auth-side > * { position: relative; z-index: 1; }
            .brand-chip { width: 54px; height: 54px; border-radius: 8px; position: relative; background: linear-gradient(135deg, #fff 0 45%, #c7efd3 45% 68%, var(--green-500) 68% 100%); box-shadow: 0 .8rem 1.8rem rgba(0,0,0,.18); }
            .brand-chip::after { content: ""; position: absolute; left: 9px; right: 9px; bottom: 12px; height: 6px; border-top: 2px solid rgba(13,95,62,.75); border-bottom: 2px solid rgba(82,183,136,.7); }
            .auth-form { max-width: 430px; margin: 0 auto; }
            .auth-side .h4, .auth-side h1 { font-family: 'Syne', sans-serif; letter-spacing: 0; }
            .btn-primary { --bs-btn-bg: var(--green-800); --bs-btn-border-color: var(--green-800); --bs-btn-hover-bg: var(--green-700); --bs-btn-hover-border-color: var(--green-700); }
            input, select, textarea, .btn { border-radius: 8px !important; }
            @media (max-width: 767.98px) { .auth-side { min-height: auto; } }
        </style>
    </head>
    <body>
        <main class="auth-shell">
            <div class="auth-card">
                <div class="row g-0">
                    <div class="col-md-5 auth-side p-4 p-lg-5 d-flex flex-column justify-content-between">
                        <div>
                            <a href="/" class="d-inline-flex align-items-center gap-3 text-white text-decoration-none mb-4">
                                <span class="brand-chip"></span>
                                <span><span class="h4 d-block mb-0">iClimate</span><span class="small text-white-50">Lian, Batangas</span></span>
                            </a>
                            <div class="small text-uppercase fw-bold text-white-50 mb-2" style="letter-spacing:.12em;">Climate command access</div>
                            <h1 class="h3 fw-bold mb-3">Rice and climate records, organized for local action.</h1>
                            <p class="text-white-50 mb-0">Secure access for Farmers, MAO Personnel, and IT Experts.</p>
                        </div>
                        <div class="small text-white-50 mt-4">Weather Impact Analysis and Rice Yield Information System</div>
                    </div>
                    <div class="col-md-7 p-4 p-lg-5">
                        <div class="auth-form">
                            {{ $slot }}
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </body>
</html>
