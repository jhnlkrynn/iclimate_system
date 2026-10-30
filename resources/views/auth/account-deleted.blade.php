{{-- "Your iClimate account was deleted" (Auth\DeletedAccountController): a calm card in the iClimate theme, in the person's
     saved language. Never IT's internal note or the name of who deleted the account. --}}
<!DOCTYPE html>
<html lang="{{ $locale === 'fil' ? 'fil' : 'en' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>{{ __('user_deletion.panel.title') }} | iClimate</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --brand: {{ config('palette.sage.700') }}; --advisory-bg: #FBF5E3; --ink: {{ config('palette.ink') }}; --muted: #55675c; --line: #e6dcbc; }
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px 16px; background: {{ config('palette.sage.50') }}; font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif; font-size: 17px; line-height: 1.55; color: var(--ink); }
        .ad-card { width: min(560px, 100%); border-radius: 16px; overflow: hidden; background: var(--advisory-bg); border: 1px solid var(--line); box-shadow: 0 18px 44px rgba({{ config('palette.rgb.700') }}, .16); }
        .ad-head { display: flex; align-items: center; gap: 14px; padding: 18px 22px; background: var(--brand); color: #fff; }
        .ad-head img { width: 48px; height: 48px; flex-shrink: 0; border-radius: 50%; background: #fff; object-fit: contain; }
        .ad-head h1 { margin: 0; font-size: 1.3rem; line-height: 1.25; font-weight: 700; }
        .ad-body { display: grid; gap: 16px; padding: 22px; }
        .ad-label { margin: 0 0 2px; font-size: .85rem; font-weight: 600; letter-spacing: .02em; color: var(--muted); text-transform: uppercase; }
        .ad-text { margin: 0; overflow-wrap: anywhere; }
        .ad-contact { padding: 14px 16px; border-radius: 12px; background: #fff; border: 1px solid var(--line); }
        .ad-contact h2 { margin: 0 0 6px; font-size: 1.05rem; color: var(--brand); }
        .ad-contact dl { display: grid; grid-template-columns: max-content minmax(0, 1fr); gap: 4px 12px; margin: 10px 0 0; font-size: .95rem; }
        .ad-contact dt { color: var(--muted); font-weight: 600; }
        .ad-contact dd { margin: 0; overflow-wrap: anywhere; }
        .ad-contact a { color: var(--brand); font-weight: 600; }
        .ad-btn { display: inline-flex; align-items: center; justify-content: center; min-height: 48px; padding: 10px 22px; border-radius: 10px; background: var(--brand); color: #fff; font-weight: 600; text-decoration: none; }
        .ad-btn:focus-visible { outline: 3px solid {{ config('palette.sage.500') }}; outline-offset: 2px; }
        @media (max-width: 480px) {
            body { padding: 12px; font-size: 16px; }
            .ad-head, .ad-body { padding: 16px; }
            .ad-contact dl { grid-template-columns: minmax(0, 1fr); }
            .ad-contact dt { margin-top: 6px; }
            .ad-btn { width: 100%; }
        }
    </style>
</head>
<body>
    <main class="ad-card" data-account-deleted>
        <header class="ad-head">
            <img src="{{ asset('images/mao-lian-seal.png') }}" alt="" width="48" height="48">
            <h1>{{ __('user_deletion.panel.title') }}</h1>
        </header>
        <div class="ad-body">
            <div>
                <p class="ad-label">{{ __('user_deletion.panel.reason') }}</p>
                <p class="ad-text" data-account-deleted-reason>{{ $reason }}</p>
            </div>
            @if (filled($other))
                <div>
                    <p class="ad-label">{{ __('user_deletion.panel.details') }}</p>
                    <p class="ad-text" data-account-deleted-other>{{ $other }}</p>
                </div>
            @endif
            @if ($date)
                <div>
                    <p class="ad-label">{{ __('user_deletion.panel.date') }}</p>
                    <p class="ad-text" data-account-deleted-date>{{ $date }}</p>
                </div>
            @endif
            <section class="ad-contact" aria-labelledby="adContactTitle">
                <h2 id="adContactTitle">{{ __('user_deletion.panel.contact_title') }}</h2>
                <p class="ad-text">{{ __('user_deletion.panel.contact') }}</p>
                <dl>
                    <dt>{{ __('user_deletion.panel.address') }}</dt><dd>{{ \App\Support\MaoContact::ADDRESS }}</dd>
                    <dt>{{ __('user_deletion.panel.email') }}</dt><dd><a href="mailto:{{ \App\Support\MaoContact::EMAIL }}">{{ \App\Support\MaoContact::EMAIL }}</a></dd>
                    <dt>{{ __('user_deletion.panel.hours') }}</dt><dd>{{ \App\Support\MaoContact::HOURS }}</dd>
                </dl>
            </section>
            <div><a class="ad-btn" href="{{ url('/') }}" data-account-deleted-home>{{ __('user_deletion.panel.home') }}</a></div>
        </div>
    </main>
</body>
</html>
