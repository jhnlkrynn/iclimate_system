<x-app-layout>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Inter:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

    <style>
        .farmer-console {
            --fc-green-950: #0D1F18;
            --fc-green-900: #122B20;
            --fc-green-800: #1A3A2A;
            --fc-green-700: #2D6A4F;
            --fc-green-500: #52B788;
            --fc-green-400: #74C69D;
            --fc-green-200: #B7E4C7;
            --fc-green-100: #D8F3DC;
            --fc-green-50:  #F0F7F4;
            --fc-sand:      #F5F0E8;
            --fc-sand-dark: #E8E0D0;
            --fc-gold:      #E8A73D;
            --fc-gold-dark: #C6872A;
            --fc-gold-light:#FBEBCF;
            --fc-blue:      #2F6F8F;
            --fc-coral:     #D85B45;
            --fc-ink:       #1F2A24;
            --fc-ink-mid:   #4A5C52;
            --fc-ink-light: #6B7C72;
            --fc-border:    #E3ECE6;
            --radius-sm: 4px;
            --radius-md: 10px;
            --radius-lg: 18px;
            --radius-xl: 32px;
            --radius-pill: 100px;
            --shadow-sm: 0 1px 4px rgba(13,31,24,.08);
            --shadow-md: 0 4px 20px rgba(13,31,24,.12);
            --shadow-lg: 0 16px 56px rgba(13,31,24,.18);
            --shadow-gold: 0 10px 28px rgba(232,167,61,.32);
            --ease: cubic-bezier(.4,0,.2,1);
            font-family: 'Inter', system-ui, sans-serif;
            color: var(--fc-ink);
        }
        .farmer-console h1, .farmer-console h2, .farmer-console h3 {
            font-family: 'DM Serif Display', Georgia, serif;
            font-weight: 400;
            letter-spacing: -0.01em;
            color: var(--fc-ink);
        }
        .farmer-console .mono { font-family: 'DM Mono', monospace; }

        /* -- EYEBROW -------------------------------------- */
        .fc-eyebrow {
            display: inline-flex; align-items: center; gap: 8px;
            font-family: 'DM Mono', monospace;
            font-size: .72rem;
            font-weight: 500;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--fc-green-400);
            margin-bottom: 14px;
        }
        .fc-eyebrow::before { content: ''; display: block; width: 20px; height: 1px; background: var(--fc-green-400); }
        .fc-eyebrow.on-light { color: var(--fc-green-700); }
        .fc-eyebrow.on-light::before { background: var(--fc-green-700); }

        /* -- BUTTONS (pill, matches welcome.blade) -------- */
        .fc-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 22px;
            border-radius: var(--radius-pill);
            font-family: 'Inter', sans-serif;
            font-size: .85rem;
            font-weight: 600;
            letter-spacing: .01em;
            transition: all .2s var(--ease);
            white-space: nowrap;
            border: 1.5px solid transparent;
        }
        .fc-btn-gold { background: var(--fc-gold); color: var(--fc-ink); box-shadow: var(--shadow-gold); }
        .fc-btn-gold:hover { background: var(--fc-gold-dark); color: var(--fc-ink); transform: translateY(-1px); }
        .fc-btn-outline-light { border-color: var(--fc-sand-dark); color: var(--fc-ink); }
        .fc-btn-outline-light:hover { background: var(--fc-green-50); border-color: var(--fc-green-700); color: var(--fc-ink); }
        .fc-btn-outline { border-color: var(--fc-sand-dark); color: var(--fc-ink-mid); background: transparent; }
        .fc-btn-outline:hover { background: var(--fc-green-50); border-color: var(--fc-green-700); color: var(--fc-ink); }

        /* -- HERO ------------------------------------------ */
        .farmer-hero {
            position: relative;
            overflow: hidden;
            border-radius: var(--radius-xl);
            padding: 2rem 2.25rem;
            margin-bottom: 1.5rem;
            color: var(--fc-ink);
            background: linear-gradient(145deg, var(--fc-green-50) 0%, var(--fc-green-100) 100%);
            border: 1px solid var(--fc-border);
            box-shadow: var(--shadow-lg);
        }
        .farmer-hero::before {
            content: "";
            position: absolute; inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.05'/%3E%3C/svg%3E");
            pointer-events: none; opacity: .6;
        }
        .farmer-hero::after {
            content: "";
            position: absolute;
            top: -30%; right: -8%;
            width: 60%; height: 140%;
            background: radial-gradient(ellipse at center, rgba(82,183,136,.14) 0%, transparent 65%);
            pointer-events: none;
        }
        .farmer-hero-leaf {
            position: absolute; right: 0; bottom: 0;
            width: 150px; height: 130px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 120 120'%3E%3Cpath d='M65 2C30 20 12 55 18 92c4 14 12 23 22 28-6-28 0-58 16-84C68 20 68 10 65 2Z' fill='%2352B788' fill-opacity='.16'/%3E%3Cpath d='M60 12C38 38 26 68 32 100' stroke='%2374C69D' stroke-opacity='.4' stroke-width='2' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-size: contain;
            background-position: bottom right;
            pointer-events: none;
            z-index: 0;
        }
        .farmer-hero > *:not(.farmer-hero-leaf) { position: relative; z-index: 1; }
        .farmer-hero h1 { color: var(--fc-ink); font-size: clamp(1.7rem, 3.4vw, 2.5rem); margin-bottom: .35rem; }
        .farmer-hero h1 em { font-style: italic; color: var(--fc-green-700); }
        .farmer-hero p { color: var(--fc-ink-mid); max-width: 640px; margin: 0; font-size: .95rem; line-height: 1.7; }
        .field-chip {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            border: 1px solid var(--fc-border);
            border-radius: var(--radius-pill);
            padding: .5rem .85rem;
            background: rgba(255,255,255,.6);
            color: var(--fc-ink-mid);
            font-family: 'DM Mono', monospace;
            font-size: .76rem;
            font-weight: 500;
            letter-spacing: .02em;
        }
        .field-pulse {
            width: 8px; height: 8px;
            border-radius: 999px;
            background: var(--fc-green-400);
            box-shadow: 0 0 0 5px rgba(116,198,157,.2);
            flex-shrink: 0;
        }

        /* -- SECTION HEADER --------------------------------- */
        .dashboard-section-label {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1rem;
            margin: 2rem 0 1rem;
        }
        .dashboard-section-label:first-of-type { margin-top: 0; }
        .section-title {
            color: var(--fc-ink);
            font-size: clamp(1.2rem, 2vw, 1.5rem);
            margin: 0;
        }
        .section-note { color: var(--fc-ink-light); font-size: .85rem; margin: .2rem 0 0; }
        .fc-section-link {
            display: inline-flex; align-items: center; gap: 6px;
            font-family: 'DM Mono', monospace;
            font-size: .72rem;
            font-weight: 600;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: var(--fc-green-700);
            white-space: nowrap;
        }
        .fc-section-link:hover { color: var(--fc-green-500); }

        /* -- STAT / FIELD CARDS ------------------------------ */
        .climate-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
            margin-bottom: .5rem;
        }
        .field-card {
            position: relative;
            border: 1.5px solid var(--fc-sand-dark);
            border-radius: var(--radius-lg);
            background: linear-gradient(145deg, #ffffff, #f7fbf8);
            padding: 1.15rem 1.2rem;
            text-decoration: none;
            display: flex; flex-direction: column; gap: .6rem;
            transition: transform .25s var(--ease), box-shadow .25s var(--ease), border-color .25s;
            width: 100%;
            text-align: left;
            font: inherit;
            cursor: pointer;
        }
        .field-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); border-color: var(--fc-green-500); }
        .field-card:focus-visible { outline: 2px solid var(--fc-green-400); outline-offset: 2px; }
        .field-tap-hint {
            color: var(--fc-green-700);
            font-family: 'DM Mono', monospace;
            font-size: .64rem;
            font-weight: 600;
            letter-spacing: .04em;
            text-transform: uppercase;
            opacity: 0;
            transform: translateY(2px);
            transition: opacity .2s var(--ease), transform .2s var(--ease);
        }
        .field-card:hover .field-tap-hint, .field-card:focus-visible .field-tap-hint { opacity: 1; transform: translateY(0); }

        /* -- STAT DETAIL PANELS ------------------------------- */
        .fc-stat-modal {
            --fc-green-950: #0D1F18;
            --fc-green-900: #122B20;
            --fc-green-800: #1A3A2A;
            --fc-green-700: #2D6A4F;
            --fc-green-400: #74C69D;
            --fc-gold: #E8A73D;
            --fc-gold-dark: #C6872A;
            --fc-ink: #1F2A24;
            --radius-lg: 18px;
            --radius-pill: 100px;
            --shadow-gold: 0 10px 28px rgba(232,167,61,.32);
            --ease: cubic-bezier(.4,0,.2,1);
        }
        .fc-stat-panel { margin-top: 1rem; }
        .fc-stat-panel[hidden] { display: none; }
        .fc-modal-content { background: linear-gradient(145deg, #ffffff, #f7fbf8); color: var(--fc-ink-mid); border: 1px solid var(--fc-sand-dark); border-radius: var(--radius-lg); box-shadow: 0 1.25rem 3rem rgba(13,31,24,.18); overflow: hidden; }
        .fc-modal-header { display: flex; align-items: center; justify-content: space-between; gap: .5rem; background: linear-gradient(90deg, var(--fc-green-50), var(--fc-green-100)); border-bottom: 1px solid var(--fc-border); color: var(--fc-ink); padding: .85rem 1rem; }
        .fc-modal-header .modal-title { color: var(--fc-ink); font-family: 'DM Serif Display', serif; margin: 0; }
        .fc-panel-close { border: 0; background: rgba(13,31,24,.06); color: var(--fc-ink); width: 30px; height: 30px; border-radius: 999px; font-size: 1.1rem; line-height: 1; flex-shrink: 0; }
        .fc-panel-close:hover { background: rgba(13,31,24,.12); }
        .fc-modal-body { max-height: 330px; overflow-y: auto; background: #ffffff; padding: 1rem; }
        .fc-modal-footer { border-top: 1px solid var(--fc-border); background: var(--fc-green-50); padding: .8rem 1rem; }
        .fc-modal-headline { font-family: 'DM Serif Display', serif; font-size: 1.55rem; color: var(--fc-ink); }
        .fc-modal-sub { color: var(--fc-ink-light); font-size: .8rem; margin: .25rem 0 .75rem; }
        .fc-modal-note { color: var(--fc-ink-mid); font-size: .82rem; line-height: 1.55; margin-bottom: .85rem; }
        .fc-modal-table { color: var(--fc-ink); }
        .fc-modal-table.table { min-width: 620px; }
        .fc-modal-table thead th { background: var(--fc-green-50) !important; color: var(--fc-ink-light) !important; font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; border-color: var(--fc-border); font-weight: 600; }
        .fc-modal-table td { color: var(--fc-ink) !important; border-color: var(--fc-border); }
        .fc-modal-table-highlight { color: var(--fc-green-700) !important; font-weight: 700; }
        .field-icon {
            width: 40px; height: 40px;
            border-radius: 50%;
            display: grid; place-items: center;
            background: rgba(82,183,136,.14);
            border: 1px solid rgba(116,198,157,.35);
            color: var(--fc-green-700);
            font-family: 'DM Mono', monospace;
            font-size: .65rem;
            font-weight: 700;
        }
        .field-label {
            color: var(--fc-ink-light);
            font-family: 'DM Mono', monospace;
            font-size: .64rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .field-value {
            font-family: 'DM Serif Display', serif;
            font-size: clamp(1.5rem, 2.6vw, 1.9rem);
            line-height: 1;
            color: var(--fc-ink);
            letter-spacing: -.02em;
        }
        .field-note { color: var(--fc-ink-light); font-size: .78rem; line-height: 1.5; }
        .field-source {
            color: var(--fc-green-700);
            font-family: 'DM Mono', monospace;
            font-size: .62rem;
            font-weight: 600;
            letter-spacing: .04em;
            text-transform: uppercase;
            line-height: 1.35;
        }
        .field-source span { display: block; color: var(--fc-ink-light); margin-top: .1rem; }

        /* -- PANELS ------------------------------------------ */
        .farmer-panel {
            border: 1.5px solid var(--fc-sand-dark);
            border-radius: var(--radius-lg);
            background: linear-gradient(145deg, #ffffff, #f7fbf8);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        .farmer-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.15rem 1.25rem 1rem;
            border-bottom: 1px solid var(--fc-border);
        }
        h2.farmer-panel-title { font-size: 1.05rem; margin: 0; color: var(--fc-ink); }
        .farmer-panel-sub { margin: .25rem 0 0; color: var(--fc-ink-light); font-size: .82rem; font-family: 'Inter', sans-serif; }
        .farmer-panel-body { padding: 1.25rem; }
        .farmer-list-item {
            display: flex;
            gap: .9rem;
            align-items: flex-start;
            padding: .85rem 0;
        }
        .farmer-list-item + .farmer-list-item { border-top: 1px solid var(--fc-border); }
        .list-mark {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: rgba(82,183,136,.14);
            border: 1px solid rgba(149,213,178,.3);
            color: var(--fc-green-700);
            font-family: 'DM Mono', monospace;
            font-weight: 700;
            font-size: .66rem;
            flex: 0 0 auto;
        }
        .list-title { font-weight: 700; color: var(--fc-ink); line-height: 1.3; font-size: .92rem; }
        .list-text { color: var(--fc-ink-light); font-size: .84rem; margin-top: .2rem; line-height: 1.55; }
        .list-meta { color: var(--fc-ink-light); font-family: 'DM Mono', monospace; font-size: .66rem; margin-top: .4rem; font-weight: 500; text-transform: uppercase; letter-spacing: .06em; }
        .status-pill {
            display: inline-flex;
            align-items: center;
            border-radius: var(--radius-pill);
            padding: .32rem .62rem;
            font-family: 'DM Mono', monospace;
            font-size: .64rem;
            font-weight: 600;
            letter-spacing: .03em;
            text-transform: uppercase;
            background: rgba(82,183,136,.14);
            color: var(--fc-green-700);
        }
        .status-pill.muted { background: var(--fc-green-50); color: var(--fc-ink-light); }
        .advisory-card {
            padding: 1.1rem 1.2rem;
            border-radius: var(--radius-md);
            border: 1px solid var(--fc-border);
            background: var(--fc-green-50);
            min-height: 100%;
        }
        .advisory-card strong { color: var(--fc-ink); display: block; margin: .5rem 0 .35rem; font-size: .95rem; }

        /* -- QUICK ACTIONS ------------------------------------ */
        .quick-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .85rem; }
        .quick-action {
            min-height: 108px;
            padding: 1.1rem 1.2rem;
            border-radius: var(--radius-md);
            border: 1px solid var(--fc-border);
            background: var(--fc-green-50);
            color: inherit;
            text-decoration: none;
            display: block;
            transition: transform .2s var(--ease), box-shadow .2s var(--ease), border-color .2s, background .2s;
        }
        .quick-action:hover { transform: translateY(-3px); box-shadow: var(--shadow-sm); border-color: var(--fc-green-500); background: var(--fc-green-100); }
        .quick-action strong { display: block; color: var(--fc-ink); font-size: .92rem; font-weight: 700; }
        .quick-action span { display: block; color: var(--fc-ink-light); font-size: .8rem; margin-top: .3rem; line-height: 1.5; }
        .empty-soft {
            border: 1.5px dashed var(--fc-sand-dark);
            background: var(--fc-green-50);
            border-radius: var(--radius-md);
            padding: 1.75rem;
            text-align: center;
        }
        .empty-soft strong { font-family: 'DM Serif Display', serif; font-weight: 400; font-size: 1.05rem; color: var(--fc-ink); }

        /* -- PRIORITY TOOLS (role-card style) ------------------ */
        .priority-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: .5rem; }
        .priority-card {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            min-height: 160px;
            padding: 1.4rem 1.3rem;
            border: 1.5px solid var(--fc-sand-dark);
            border-radius: var(--radius-xl);
            background: linear-gradient(145deg, #ffffff, #f7fbf8);
            box-shadow: var(--shadow-sm);
            text-decoration: none;
            color: inherit;
            transition: box-shadow .25s var(--ease), transform .25s var(--ease), border-color .25s;
        }
        .priority-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); border-color: var(--fc-green-700); }
        .priority-card strong { display: block; color: var(--fc-ink); font-family: 'DM Serif Display', serif; font-weight: 400; font-size: 1.1rem; margin-bottom: .4rem; }
        .priority-card span.desc { display: block; color: var(--fc-ink-light); font-size: .82rem; line-height: 1.55; font-family: 'Inter', sans-serif; }
        .priority-card .status-pill { align-self: flex-start; }
        .priority-card.priority-highlight .status-pill { background: var(--fc-gold); color: var(--fc-ink); }

        /* -- COLLAPSIBLE GROUPS --------------------------------- */
        .dashboard-focus-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        .dashboard-group {
            border: 1.5px solid var(--fc-sand-dark);
            border-radius: var(--radius-lg);
            background: linear-gradient(145deg, #ffffff, #f7fbf8);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        .dashboard-group > summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.1rem 1.25rem;
            cursor: pointer;
            list-style: none;
            background: var(--fc-green-50);
        }
        .dashboard-group > summary::-webkit-details-marker { display: none; }
        .dashboard-group-summary-main { display: flex; align-items: center; gap: .9rem; }
        h2.dashboard-group-title { color: var(--fc-ink); font-size: 1rem; margin: 0; }
        .dashboard-group-note { color: var(--fc-ink-light); font-size: .82rem; margin: .15rem 0 0; font-family: 'Inter', sans-serif; }
        .dashboard-group-toggle { display: inline-flex; align-items: center; gap: 6px; font-family: 'DM Mono', monospace; color: var(--fc-green-700); font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; white-space: nowrap; }
        .dashboard-group-toggle::before { content: "Collapse"; }
        .dashboard-group:not([open]) .dashboard-group-toggle::before { content: "Expand"; }
        .dashboard-group-body { padding: 1.25rem; }
        .dashboard-group-body > .row:last-child, .dashboard-group-body > .farmer-panel:last-child { margin-bottom: 0 !important; }
        .list-compact .farmer-list-item:nth-of-type(n+4), .list-compact .row > [class*="col-"]:nth-child(n+5) { display: none; }

        @media (max-width: 1399.98px) { .climate-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (max-width: 1199.98px) { .climate-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .priority-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 767.98px) {
            .climate-grid, .quick-grid, .priority-grid { grid-template-columns: 1fr; }
            .farmer-hero { padding: 1.5rem; }
            .fc-modal-body { max-height: 300px; }
            .fc-modal-table.table { min-width: 560px; }
            .farmer-panel-header, .dashboard-section-label, .dashboard-group > summary { align-items: flex-start; flex-direction: column; }
        }
    </style>

    @php
        $unreadNotifications = $unreadNotificationCount ?? \App\Models\Notification::query()->where('user_id', auth()->id())->where('is_read', false)->count();
        $latestAdvisory = $advisories->first();
        $profile = auth()->user()->farmerProfile;
        $weatherTimezone = config('services.open_meteo.timezone', 'Asia/Manila');
        $weatherNow = now($weatherTimezone);
        $weatherSource = $latestForecast?->source ?? $climateSummary?->source ?? 'No weather source yet';
        $weatherDataMode = ($forecastResult['ok'] ?? false) ? 'Live online fetch' : 'Stored fallback';
        $weatherFreshness = $latestForecast
            ? str($forecastResult['freshness'] ?? $latestForecast->freshnessLabel())->headline()
            : 'Stored Record';
        $weatherFetchedAt = $latestForecast?->fetched_at?->timezone($weatherTimezone)->shortDateTime();
        $weatherDate = $latestForecast?->forecast_date?->format('M d, Y') ?? $climateSummary?->record_date?->format('M d, Y');
        $forecastHour = $weatherNow->hour;
        $forecastHourLabel = $weatherNow->format('g A');
        $currentWeather = $latestForecast?->raw_response['current'] ?? [];
        $currentWeatherTime = data_get($currentWeather, 'time');
        $currentWeatherTimeLabel = $currentWeatherTime
            ? \Illuminate\Support\Carbon::parse($currentWeatherTime, $weatherTimezone)->shortTime()
            : $weatherNow->shortTime();
        $currentWeatherDate = $currentWeatherTime
            ? \Illuminate\Support\Carbon::parse($currentWeatherTime, $weatherTimezone)->format('M d, Y')
            : $weatherNow->format('M d, Y');
        $hourlyWeather = $latestForecast?->raw_response['hourly'] ?? [];
        $hourlyTemperature = $hourlyWeather['temperature_2m'][$forecastHour] ?? null;
        $hourlyHumidity = $hourlyWeather['relative_humidity_2m'][$forecastHour] ?? null;
        $hourlyRainfall = $hourlyWeather['rain'][$forecastHour] ?? $hourlyWeather['precipitation'][$forecastHour] ?? null;
        $weatherSourceLine = $latestForecast
            ? $weatherSource.' forecast'.($weatherFetchedAt ? ' - Updated '.$weatherFetchedAt : '')
            : 'Recorded: '.$weatherSource;
        $weatherTimingLabel = $currentWeather !== []
            ? 'Current weather reading - '.$currentWeatherTimeLabel
            : ($latestForecast ? 'Hourly forecast - '.$forecastHourLabel : 'Stored Record');
        $weatherDisplayDate = $currentWeather !== []
            ? $currentWeatherDate
            : $weatherDate;
        $weatherUpdateLine = $weatherFetchedAt
            ? 'Updated '.$weatherFetchedAt
            : $weatherDataMode;
        $temperatureValue = data_get($currentWeather, 'temperature_2m') ?? $hourlyTemperature ?? $latestForecast?->temperature ?? $climateSummary?->temperature;
        $rainfallValue = data_get($currentWeather, 'rain') ?? data_get($currentWeather, 'precipitation') ?? $hourlyRainfall ?? $latestForecast?->rainfall_mm ?? $climateSummary?->rainfall;
        $humidityValue = data_get($currentWeather, 'relative_humidity_2m') ?? $hourlyHumidity ?? $latestForecast?->humidity ?? $climateSummary?->humidity;
        $weatherNoteLabel = $currentWeather !== []
            ? 'Current Open-Meteo weather for Lian'
            : ($latestForecast ? 'Latest Open-Meteo forecast for Lian' : 'Latest recorded field climate data');
    @endphp

    <div class="farmer-console">
        <section class="farmer-hero">
            <div class="farmer-hero-leaf" aria-hidden="true"></div>
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-4 align-items-xl-end">
                <div>
                    <div class="fc-eyebrow on-light">Farmer field view</div>
                    <h1>Climate-smart <em>farmer dashboard.</em></h1>
                    <p>Welcome back, {{ auth()->user()->name }}. Here is your latest climate summary, advisories, community updates, and messages for Lian, Batangas.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="field-chip"><span class="field-pulse"></span> <span data-current-date>{{ $weatherNow->format('l, F d, Y') }}</span></span>
                    <a class="fc-btn fc-btn-outline-light" href="{{ route('heatmap-areas.index') }}">View Heat Map</a>
                    <a class="fc-btn fc-btn-outline-light" href="{{ route('community-feed.index') }}">Community Feed</a>
                    <a class="fc-btn fc-btn-outline-light" href="{{ route('profile.edit') }}">My Profile</a>
                </div>
            </div>
        </section>

        <div class="dashboard-section-label">
            <div class="fc-eyebrow on-light">At a glance</div>
        </div>

        <section class="climate-grid">
            <button type="button" class="field-card" data-toggle-detail="statPanelTemperature" aria-expanded="false">
                <div class="field-icon">TMP</div>
                <div class="field-label">Temperature</div>
                <div class="field-value">{{ $temperatureValue !== null ? number_format((float) $temperatureValue, 1).' C' : 'N/A' }}</div>
                <div class="field-note">{{ $weatherNoteLabel }}</div>
                <div class="field-source">
                    Source: {{ $weatherSource }}
                    <span>{{ $weatherUpdateLine }}</span>
                </div>
                <div class="field-tap-hint">View details &rarr;</div>
            </button>
            <button type="button" class="field-card" data-toggle-detail="statPanelRainfall" aria-expanded="false">
                <div class="field-icon">RAIN</div>
                <div class="field-label">Rainfall</div>
                <div class="field-value">{{ $rainfallValue !== null ? number_format((float) $rainfallValue, 1).' mm' : 'N/A' }}</div>
                <div class="field-note">Use advisories before fertilizer application</div>
                <div class="field-source">
                    Source: {{ $weatherSource }}
                    <span>{{ $weatherUpdateLine }}</span>
                </div>
                <div class="field-tap-hint">View details &rarr;</div>
            </button>
            <button type="button" class="field-card" data-toggle-detail="statPanelHumidity" aria-expanded="false">
                <div class="field-icon">HUM</div>
                <div class="field-label">Humidity</div>
                <div class="field-value">{{ $humidityValue !== null ? number_format((float) $humidityValue, 1).'%' : 'N/A' }}</div>
                <div class="field-note">Monitor crop disease risk after rain</div>
                <div class="field-source">
                    Source: {{ $weatherSource }}
                    <span>{{ $weatherUpdateLine }}</span>
                </div>
                <div class="field-tap-hint">View details &rarr;</div>
            </button>
            <button type="button" class="field-card" data-toggle-detail="statPanelAlerts" aria-expanded="false">
                <div class="field-icon">ALT</div>
                <div class="field-label">Weather Alerts</div>
                <div class="field-value">{{ number_format($unreadNotifications) }} unread</div>
                <div class="field-note">Recent notifications sent to your account</div>
                <div class="field-source">Source: Notifications</div>
                <div class="field-tap-hint">View details &rarr;</div>
            </button>
            <button type="button" class="field-card" data-toggle-detail="statPanelRisk" aria-expanded="false">
                <div class="field-icon">RSK</div>
                <div class="field-label">High Risk Areas</div>
                <div class="field-value">{{ number_format($highRiskHeatMapAreas) }}</div>
                <div class="field-note">High or severe barangay risk areas</div>
                <div class="field-source">Source: Heat Map Records</div>
                <div class="field-tap-hint">View details &rarr;</div>
            </button>
        </section>

        @php
            $fcModalTrend = fn () => $recentClimateRecords->reverse()->values();
        @endphp

        <div class="fc-stat-panel" id="statPanelTemperature" hidden>
            <div class="fc-modal-content">
                <div class="fc-modal-header">
                    <h2 class="modal-title h5">Temperature Detail</h2>
                    <button type="button" class="fc-panel-close" data-panel-close aria-label="Close">&times;</button>
                </div>
                <div class="fc-modal-body">
                    <div class="fc-modal-headline">{{ $temperatureValue !== null ? number_format((float) $temperatureValue, 1).' °C' : 'No weather data yet' }}</div>
                    <p class="fc-modal-sub">{{ $weatherSourceLine }} &middot; Weather reading: {{ $weatherTimingLabel }}{{ $weatherDisplayDate ? ' - '.$weatherDisplayDate : '' }} &middot; {{ $weatherUpdateLine }} &middot; Historical record source: {{ $climateSummary?->source ?? 'N/A' }}</p>
                    <p class="fc-modal-note">High field temperatures increase crop water demand and heat stress risk. The headline uses the latest available forecast; compare with recent recorded readings below before scheduling irrigation or fertilizer application.</p>
                    @include('dashboards.partials.climate-trend-table', ['records' => $fcModalTrend(), 'highlight' => 'temperature'])
                </div>
                <div class="fc-modal-footer">
                    <a class="fc-btn fc-btn-gold" href="{{ route('climate-records.index') }}">Open Climate Records</a>
                </div>
            </div>
        </div>

        <div class="fc-stat-panel" id="statPanelRainfall" hidden>
            <div class="fc-modal-content">
                <div class="fc-modal-header">
                    <h2 class="modal-title h5">Rainfall Detail</h2>
                    <button type="button" class="fc-panel-close" data-panel-close aria-label="Close">&times;</button>
                </div>
                <div class="fc-modal-body">
                    <div class="fc-modal-headline">{{ $rainfallValue !== null ? number_format((float) $rainfallValue, 1).' mm' : 'No weather data yet' }}</div>
                    <p class="fc-modal-sub">{{ $weatherSourceLine }} &middot; Weather reading: {{ $weatherTimingLabel }}{{ $weatherDisplayDate ? ' - '.$weatherDisplayDate : '' }} &middot; {{ $weatherUpdateLine }} &middot; Historical record source: {{ $climateSummary?->source ?? 'N/A' }}</p>
                    <p class="fc-modal-note">Heavy rainfall can wash away fertilizer and raise flooding or waterlogging risk. The headline uses the latest available forecast; check advisories before applying inputs or irrigating.</p>
                    @include('dashboards.partials.climate-trend-table', ['records' => $fcModalTrend(), 'highlight' => 'rainfall'])
                </div>
                <div class="fc-modal-footer">
                    <a class="fc-btn fc-btn-gold" href="{{ route('climate-records.index') }}">Open Climate Records</a>
                </div>
            </div>
        </div>

        <div class="fc-stat-panel" id="statPanelHumidity" hidden>
            <div class="fc-modal-content">
                <div class="fc-modal-header">
                    <h2 class="modal-title h5">Humidity Detail</h2>
                    <button type="button" class="fc-panel-close" data-panel-close aria-label="Close">&times;</button>
                </div>
                <div class="fc-modal-body">
                    <div class="fc-modal-headline">{{ $humidityValue !== null ? number_format((float) $humidityValue, 1).'%' : 'No weather data yet' }}</div>
                    <p class="fc-modal-sub">{{ $weatherSourceLine }} &middot; Weather reading: {{ $weatherTimingLabel }}{{ $weatherDisplayDate ? ' - '.$weatherDisplayDate : '' }} &middot; {{ $weatherUpdateLine }} &middot; Historical record source: {{ $climateSummary?->source ?? 'N/A' }}</p>
                    <p class="fc-modal-note">Sustained high humidity after rainfall raises the risk of fungal disease in rice. The headline uses the latest available forecast; monitor fields closely when humidity stays elevated for several days.</p>
                    @include('dashboards.partials.climate-trend-table', ['records' => $fcModalTrend(), 'highlight' => 'humidity'])
                </div>
                <div class="fc-modal-footer">
                    <a class="fc-btn fc-btn-gold" href="{{ route('climate-records.index') }}">Open Climate Records</a>
                </div>
            </div>
        </div>

        <div class="fc-stat-panel" id="statPanelAlerts" hidden>
            <div class="fc-modal-content">
                <div class="fc-modal-header">
                    <h2 class="modal-title h5">Weather Alerts</h2>
                    <button type="button" class="fc-panel-close" data-panel-close aria-label="Close">&times;</button>
                </div>
                <div class="fc-modal-body">
                    <div class="fc-modal-headline">{{ number_format($unreadNotifications) }} unread</div>
                    <p class="fc-modal-sub">Most recent notifications sent to your account.</p>
                    @forelse($notifications as $notification)
                        <div class="farmer-list-item">
                            <div class="list-mark">{{ $notification->is_read ? 'OK' : 'NEW' }}</div>
                            <div>
                                <div class="list-title">{{ $notification->title }}</div>
                                <div class="list-text">{{ str($notification->message)->limit(140) }}</div>
                                <div class="list-meta">{{ $notification->type }} &middot; {{ $notification->created_at?->shortDateTime() }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="empty-soft"><strong>No alerts yet</strong><div class="small mt-1" style="color: var(--fc-ink-light);">Notifications will appear here when sent.</div></div>
                    @endforelse
                </div>
                <div class="fc-modal-footer">
                    <a class="fc-btn fc-btn-gold" href="{{ route('notifications.index') }}">Open Notifications</a>
                </div>
            </div>
        </div>

        <div class="fc-stat-panel" id="statPanelRisk" hidden>
            <div class="fc-modal-content">
                <div class="fc-modal-header">
                    <h2 class="modal-title h5">High Risk Barangays</h2>
                    <button type="button" class="fc-panel-close" data-panel-close aria-label="Close">&times;</button>
                </div>
                <div class="fc-modal-body">
                    <div class="fc-modal-headline">{{ number_format($highRiskHeatMapAreas) }} flagged</div>
                    <p class="fc-modal-sub">Barangays currently marked High or Severe risk on the heat map.</p>
                    @forelse($highRiskAreasList as $area)
                        <div class="farmer-list-item">
                            <div class="list-mark">{{ $area->risk_level === 'Severe' ? 'SEV' : 'HI' }}</div>
                            <div>
                                <div class="list-title">{{ $area->barangay }} &middot; {{ $area->risk_type }}</div>
                                <div class="list-text">{{ $area->planting_advisory ?: 'Review latest climate and rice production data before planting.' }}</div>
                                <div class="list-meta">Risk score {{ number_format($area->risk_score, 2) }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="empty-soft"><strong>No high risk barangays</strong><div class="small mt-1" style="color: var(--fc-ink-light);">All barangays are currently within normal risk levels.</div></div>
                    @endforelse
                </div>
                <div class="fc-modal-footer">
                    <a class="fc-btn fc-btn-gold" href="{{ route('heatmap-areas.index') }}">Open Heat Map</a>
                </div>
            </div>
        </div>

        <div class="dashboard-section-label">
            <div class="fc-eyebrow on-light">Daily tools</div>
        </div>

        <section class="priority-grid">
            <a class="priority-card priority-highlight" href="{{ route('ai-chat.index') }}">
                <div><strong>Climora AI</strong><span class="desc">Ask questions, predict weather, estimate yield, and get planting or irrigation guidance.</span></div>
                <span class="status-pill">Open Climora AI</span>
            </a>
            <a class="priority-card" href="{{ route('heatmap-areas.index') }}">
                <div><strong>Barangay Heat Map</strong><span class="desc">Check risk areas before planning field work, irrigation, and harvest movement.</span></div>
                <span class="status-pill">Open Heat Map</span>
            </a>
            <a class="priority-card" href="{{ route('community-feed.index') }}">
                <div><strong>Community Feed</strong><span class="desc">View MAO updates, programs, activities, photos, videos, comments, and reactions.</span></div>
                <span class="status-pill">Open Feed</span>
            </a>
            <a class="priority-card" href="{{ route('messages.index') }}">
                <div><strong>Messages</strong><span class="desc">Start private conversations with MAO personnel for specific farm concerns.</span></div>
                <span class="status-pill">Open Messages</span>
            </a>
        </section>

        <div class="dashboard-section-label">
            <div>
                <div class="fc-eyebrow on-light">Guidance</div>
                <p class="section-note">Published advisories and the most relevant field note.</p>
            </div>
            <a class="fc-section-link" href="{{ route('planting-advisories.index') }}">
                View All
                <svg width="12" height="12" viewBox="0 0 14 14" fill="none"><path d="M2 7h10M7 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
        </div>

        <div class="dashboard-focus-grid">
        <div class="row g-4 mb-0">
            <div class="col-xl-8">
                <div class="farmer-panel h-100">
                    <div class="farmer-panel-header">
                        <div>
                            <h2 class="farmer-panel-title">Latest Planting Advisories</h2>
                            <p class="farmer-panel-sub">Published guidance from MAO Personnel.</p>
                        </div>
                        <a class="fc-btn fc-btn-outline" href="{{ route('planting-advisories.index') }}">View All</a>
                    </div>
                    <div class="farmer-panel-body">
                        <div class="row g-3">
                            @forelse($advisories as $advisory)
                                <div class="col-md-6">
                                    <div class="advisory-card">
                                        <div class="d-flex justify-content-between gap-2">
                                            <span class="status-pill">{{ $advisory->type }}</span>
                                            <span class="small mono" style="color: var(--fc-ink-light); font-size: .74rem;">{{ $advisory->created_at?->format('M d') }}</span>
                                        </div>
                                        <strong>{{ $advisory->title }}</strong>
                                        <div class="list-text">{{ str($advisory->content)->limit(120) }}</div>
                                        <div class="list-meta">{{ $advisory->target_barangay ?: 'All barangays' }}</div>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12"><div class="empty-soft"><strong>No advisories yet</strong><div class="small mt-1" style="color: var(--fc-ink-light);">Published planting advisories will appear here.</div></div></div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="farmer-panel h-100">
                    <div class="farmer-panel-header">
                        <div>
                            <h2 class="farmer-panel-title">Today&apos;s Field Guide</h2>
                            <p class="farmer-panel-sub">Quick action based on latest records.</p>
                        </div>
                    </div>
                    <div class="farmer-panel-body">
                        @if($latestAdvisory)
                            <div class="list-mark mb-3">ADV</div>
                            <div class="list-title">{{ $latestAdvisory->title }}</div>
                            <div class="list-text">{{ str($latestAdvisory->content)->limit(180) }}</div>
                            <div class="list-meta">{{ $latestAdvisory->type }} advisory</div>
                        @elseif($climateSummary)
                            <div class="list-mark mb-3">CLI</div>
                            <div class="list-title">Review latest climate conditions</div>
                            <div class="list-text">Season is {{ $climateSummary->season }} with {{ $climateSummary->rainfall }} mm rainfall recorded.</div>
                            <div class="list-meta">Source: {{ $climateSummary->source }}</div>
                        @else
                            <div class="empty-soft"><strong>No guide available</strong><div class="small mt-1" style="color: var(--fc-ink-light);">Advisories and climate records will appear here once published.</div></div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <details class="dashboard-group">
            <summary>
                <div class="dashboard-group-summary-main">
                    <span class="list-mark">
                        <svg width="16" height="16" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="6" r="2.8" stroke="currentColor" stroke-width="1.4"/><path d="M3 15c.7-3.4 2.8-5.2 6-5.2s5.3 1.8 6 5.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    </span>
                    <div>
                        <h2 class="dashboard-group-title">Updates And Profile</h2>
                        <p class="dashboard-group-note">Community posts, message access, and your registered farm details.</p>
                    </div>
                </div>
                <span class="dashboard-group-toggle">
                    <svg width="12" height="12" viewBox="0 0 14 14" fill="none"><path d="M2 7h10M7 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
            </summary>
            <div class="dashboard-group-body">
        <div class="row g-4 mb-0">
            <div class="col-xl-4">
                <div class="farmer-panel h-100">
                    <div class="farmer-panel-header">
                        <div>
                            <h2 class="farmer-panel-title">Community Posts</h2>
                            <p class="farmer-panel-sub">MAO updates, programs, activities, and announcements.</p>
                        </div>
                        <a class="fc-btn fc-btn-outline" href="{{ route('community-feed.index') }}">Open Feed</a>
                    </div>
                    <div class="farmer-panel-body list-compact">
                        @forelse($feedPosts as $post)
                            <div class="farmer-list-item">
                                <div class="list-mark">{{ str($post->category)->substr(0, 3)->upper() }}</div>
                                <div>
                                    <div class="list-title">{{ $post->title }}</div>
                                    <div class="list-text">{{ str($post->body)->limit(90) }}</div>
                                    <div class="list-meta">{{ $post->category }} | {{ $post->author?->name ?? 'MAO' }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="empty-soft"><strong>No community posts</strong><div class="small mt-1" style="color: var(--fc-ink-light);">MAO feed updates will appear here.</div></div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="farmer-panel h-100">
                    <div class="farmer-panel-header">
                        <div>
                            <h2 class="farmer-panel-title">Community and Messages</h2>
                            <p class="farmer-panel-sub">MAO posts and private conversations.</p>
                        </div>
                        <a class="fc-btn fc-btn-outline" href="{{ route('community-feed.index') }}">Open Feed</a>
                    </div>
                    <div class="farmer-panel-body">
                        <div class="quick-grid" style="grid-template-columns:1fr;">
                            <a class="quick-action" href="{{ route('community-feed.index') }}"><strong>Community Feed</strong><span>View MAO updates, activities, programs, photos, videos, comments, and reactions.</span></a>
                            <a class="quick-action" href="{{ route('messages.index') }}"><strong>Messages</strong><span>Start a private conversation with MAO personnel.</span></a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="farmer-panel h-100">
                    <div class="farmer-panel-header">
                        <div>
                            <h2 class="farmer-panel-title">Farm Profile Snapshot</h2>
                            <p class="farmer-panel-sub">Your registered farmer details.</p>
                        </div>
                        <a class="fc-btn fc-btn-outline" href="{{ route('profile.edit') }}">Edit</a>
                    </div>
                    <div class="farmer-panel-body">
                        <div class="farmer-list-item pt-0">
                            <div class="list-mark">BRG</div>
                            <div><div class="list-title">{{ $profile?->barangay ?? auth()->user()->barangay ?? 'Barangay not set' }}</div><div class="list-text">Registered barangay</div></div>
                        </div>
                        <div class="farmer-list-item">
                            <div class="list-mark">FAR</div>
                            <div><div class="list-title">{{ $profile?->farm_area ? number_format($profile->farm_area, 2).' ha' : 'Farm area not set' }}</div><div class="list-text">Farm area</div></div>
                        </div>
                        <div class="farmer-list-item">
                            <div class="list-mark">TYP</div>
                            <div><div class="list-title">{{ $profile?->farm_type ?? 'Farm type not set' }}</div><div class="list-text">Farm irrigation type</div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
            </div>
        </details>

        <details class="dashboard-group">
            <summary>
                <div class="dashboard-group-summary-main">
                    <span class="list-mark">
                        <svg width="16" height="16" viewBox="0 0 18 18" fill="none"><rect x="6" y="1.5" width="6" height="15" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M8.3 13.7h1.4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    </span>
                    <div>
                        <h2 class="dashboard-group-title">Mobile Access</h2>
                        <p class="dashboard-group-note">All farmer modules in one place.</p>
                    </div>
                </div>
                <span class="dashboard-group-toggle">
                    <svg width="12" height="12" viewBox="0 0 14 14" fill="none"><path d="M2 7h10M7 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
            </summary>
            <div class="dashboard-group-body">
        <div class="farmer-panel">
            <div class="farmer-panel-header">
                <div>
                    <h2 class="farmer-panel-title">Farmer Quick Access</h2>
                    <p class="farmer-panel-sub">Open the modules available to your role.</p>
                </div>
            </div>
            <div class="farmer-panel-body">
                <div class="quick-grid">
                    <a class="quick-action" href="{{ route('climate-records.index') }}"><strong>Climate Records</strong><span>Review rainfall, temperature, humidity, wind, and season records.</span></a>
                    <a class="quick-action" href="{{ route('heatmap-areas.index') }}"><strong>Barangay Heat Map</strong><span>Review climate and production risk by barangay.</span></a>
                    <a class="quick-action" href="{{ route('ai-chat.index') }}"><strong>Climora AI</strong><span>Ask questions and get weather, yield, planting, irrigation, and warning guidance.</span></a>
                    <a class="quick-action" href="{{ route('planting-advisories.index') }}"><strong>Planting Advisories</strong><span>Read MAO guidance for planting, irrigation, harvesting, and climate risks.</span></a>
                    <a class="quick-action" href="{{ route('community-feed.index') }}"><strong>Community Feed</strong><span>React and comment on MAO posts about updates, programs, and activities.</span></a>
                    <a class="quick-action" href="{{ route('messages.index') }}"><strong>Messages</strong><span>Send private questions and attachments to MAO personnel.</span></a>
                </div>
            </div>
        </div>
            </div>
        </details>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const dateTarget = document.querySelector('[data-current-date]');
            document.querySelectorAll('[data-toggle-detail]').forEach((card) => {
                card.addEventListener('click', () => {
                    const panel = document.getElementById(card.dataset.toggleDetail);
                    if (!panel) return;
                    const isOpen = !panel.hidden;

                    document.querySelectorAll('.fc-stat-panel').forEach((other) => { other.hidden = true; });
                    document.querySelectorAll('[data-toggle-detail]').forEach((other) => other.setAttribute('aria-expanded', 'false'));

                    if (!isOpen) {
                        panel.hidden = false;
                        card.setAttribute('aria-expanded', 'true');
                        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                });
            });
            document.querySelectorAll('.fc-panel-close').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const panel = btn.closest('.fc-stat-panel');
                    if (panel) panel.hidden = true;
                    document.querySelectorAll('[data-toggle-detail]').forEach((other) => other.setAttribute('aria-expanded', 'false'));
                });
            });

            if (!dateTarget) return;

            const formatter = new Intl.DateTimeFormat('en-US', {
                weekday: 'long',
                month: 'long',
                day: '2-digit',
                year: 'numeric',
                timeZone: @json($weatherTimezone),
            });

            const refreshDate = () => {
                dateTarget.textContent = formatter.format(new Date());
            };

            refreshDate();
            setInterval(refreshDate, 60000);

            const weatherRefreshMs = @json(max(1, (int) config('services.open_meteo.refresh_minutes', 10)) * 60 * 1000);
            setInterval(() => {
                if (document.visibilityState !== 'visible') return;
                if (document.querySelector('.modal.show')) return;

                window.location.reload();
            }, weatherRefreshMs);
        });
    </script>
</x-app-layout>
