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
            --fc-ink:       #0D1F18;
            --fc-ink-mid:   #3D5A48;
            --fc-ink-light: #6B8F71;
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
        .fc-btn-outline-light { border-color: rgba(255,255,255,.32); color: rgba(255,255,255,.9); }
        .fc-btn-outline-light:hover { background: rgba(255,255,255,.12); border-color: rgba(255,255,255,.6); color: #fff; }
        .fc-btn-outline { border-color: var(--fc-sand-dark); color: var(--fc-green-700); background: #fff; }
        .fc-btn-outline:hover { background: var(--fc-green-700); border-color: var(--fc-green-700); color: #fff; }

        /* -- HERO ------------------------------------------ */
        .farmer-hero {
            position: relative;
            overflow: hidden;
            border-radius: var(--radius-xl);
            padding: 2rem 2.25rem;
            margin-bottom: 1.5rem;
            color: #fff;
            background: var(--fc-green-950);
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
        .farmer-hero > * { position: relative; z-index: 1; }
        .farmer-hero h1 { color: #fff; font-size: clamp(1.7rem, 3.4vw, 2.5rem); margin-bottom: .35rem; }
        .farmer-hero h1 em { font-style: italic; color: var(--fc-green-400); }
        .farmer-hero p { color: rgba(255,255,255,.55); max-width: 640px; margin: 0; font-size: .95rem; line-height: 1.7; }
        .field-chip {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            border: 1px solid rgba(255,255,255,.18);
            border-radius: var(--radius-pill);
            padding: .5rem .85rem;
            background: rgba(255,255,255,.06);
            color: rgba(255,255,255,.85);
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

        /* -- STAT / FIELD CARDS ------------------------------ */
        .climate-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 1rem;
            margin-bottom: .5rem;
        }
        .field-card {
            position: relative;
            border: 1.5px solid var(--fc-sand-dark);
            border-radius: var(--radius-lg);
            background: #fff;
            padding: 1.15rem 1.2rem;
            text-decoration: none;
            display: flex; flex-direction: column; gap: .6rem;
            transition: transform .25s var(--ease), box-shadow .25s var(--ease), border-color .25s;
        }
        .field-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); border-color: var(--accent, var(--fc-green-200)); }
        .field-icon {
            width: 40px; height: 40px;
            border-radius: 50%;
            display: grid; place-items: center;
            background: color-mix(in srgb, var(--accent, var(--fc-green-500)) 14%, var(--fc-green-50));
            border: 1px solid color-mix(in srgb, var(--accent, var(--fc-green-500)) 30%, var(--fc-green-100));
            color: var(--accent, var(--fc-green-700));
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
        .tone-green { --accent: var(--fc-green-500); }
        .tone-blue  { --accent: var(--fc-blue); }
        .tone-gold  { --accent: var(--fc-gold); }
        .tone-red   { --accent: var(--fc-coral); }

        /* -- PANELS ------------------------------------------ */
        .farmer-panel {
            border: 1.5px solid var(--fc-sand-dark);
            border-radius: var(--radius-lg);
            background: #fff;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        .farmer-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.15rem 1.25rem 1rem;
            border-bottom: 1px solid var(--fc-sand-dark);
        }
        .farmer-panel-title { font-size: 1.05rem; margin: 0; color: var(--fc-ink); }
        .farmer-panel-sub { margin: .25rem 0 0; color: var(--fc-ink-light); font-size: .82rem; font-family: 'Inter', sans-serif; }
        .farmer-panel-body { padding: 1.25rem; }
        .farmer-list-item {
            display: flex;
            gap: .9rem;
            align-items: flex-start;
            padding: .85rem 0;
        }
        .farmer-list-item + .farmer-list-item { border-top: 1px solid var(--fc-sand-dark); }
        .list-mark {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: var(--fc-green-50);
            border: 1px solid var(--fc-green-100);
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
            background: var(--fc-green-100);
            color: var(--fc-green-700);
        }
        .status-pill.muted { background: var(--fc-sand); color: var(--fc-ink-light); }
        .advisory-card {
            padding: 1.1rem 1.2rem;
            border-radius: var(--radius-md);
            border: 1px solid var(--fc-sand-dark);
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
            border: 1px solid var(--fc-sand-dark);
            background: #fff;
            color: inherit;
            text-decoration: none;
            display: block;
            transition: transform .2s var(--ease), box-shadow .2s var(--ease), border-color .2s;
        }
        .quick-action:hover { transform: translateY(-3px); box-shadow: var(--shadow-sm); border-color: var(--fc-green-200); background: var(--fc-green-50); }
        .quick-action strong { display: block; color: var(--fc-ink); font-size: .92rem; font-weight: 700; }
        .quick-action span { display: block; color: var(--fc-ink-light); font-size: .8rem; margin-top: .3rem; line-height: 1.5; }
        .empty-soft {
            border: 1.5px dashed var(--fc-sand-dark);
            background: var(--fc-green-50);
            border-radius: var(--radius-md);
            padding: 1.75rem;
            text-align: center;
        }
        .empty-soft strong { font-family: 'DM Serif Display', serif; font-weight: 400; font-size: 1.05rem; }

        /* -- PRIORITY TOOLS (role-card style) ------------------ */
        .priority-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: .5rem; }
        .priority-card {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            min-height: 160px;
            padding: 1.4rem 1.3rem;
            border: 1.5px solid var(--fc-green-100);
            border-radius: var(--radius-xl);
            background: #fff;
            box-shadow: var(--shadow-sm);
            text-decoration: none;
            color: inherit;
            transition: box-shadow .25s var(--ease), transform .25s var(--ease), border-color .25s;
        }
        .priority-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); border-color: var(--fc-green-200); }
        .priority-card strong { display: block; color: var(--fc-ink); font-family: 'DM Serif Display', serif; font-weight: 400; font-size: 1.1rem; margin-bottom: .4rem; }
        .priority-card span.desc { display: block; color: var(--fc-ink-light); font-size: .82rem; line-height: 1.55; font-family: 'Inter', sans-serif; }
        .priority-card .status-pill { align-self: flex-start; }
        .priority-card.priority-highlight {
            background: var(--fc-green-950);
            border-color: var(--fc-green-900);
        }
        .priority-card.priority-highlight strong { color: #fff; }
        .priority-card.priority-highlight span.desc { color: rgba(255,255,255,.55); }
        .priority-card.priority-highlight .status-pill { background: var(--fc-gold); color: var(--fc-ink); }
        .priority-card.priority-highlight:hover { border-color: var(--fc-green-800); }

        /* -- COLLAPSIBLE GROUPS --------------------------------- */
        .dashboard-focus-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        .dashboard-group {
            border: 1.5px solid var(--fc-sand-dark);
            border-radius: var(--radius-lg);
            background: #fff;
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
        .dashboard-group-title { color: var(--fc-ink); font-size: 1rem; margin: 0; }
        .dashboard-group-note { color: var(--fc-ink-light); font-size: .82rem; margin: .15rem 0 0; font-family: 'Inter', sans-serif; }
        .dashboard-group-toggle { font-family: 'DM Mono', monospace; color: var(--fc-green-700); font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; white-space: nowrap; }
        .dashboard-group-toggle::after { content: "Collapse"; }
        .dashboard-group:not([open]) .dashboard-group-toggle::after { content: "Expand"; }
        .dashboard-group-body { padding: 1.25rem; }
        .dashboard-group-body > .row:last-child, .dashboard-group-body > .farmer-panel:last-child { margin-bottom: 0 !important; }
        .list-compact .farmer-list-item:nth-of-type(n+4), .list-compact .row > [class*="col-"]:nth-child(n+5) { display: none; }

        @media (max-width: 1399.98px) { .climate-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (max-width: 1199.98px) { .climate-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .priority-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 767.98px) {
            .climate-grid, .quick-grid, .priority-grid { grid-template-columns: 1fr; }
            .farmer-hero { padding: 1.5rem; }
            .farmer-panel-header, .dashboard-section-label, .dashboard-group > summary { align-items: flex-start; flex-direction: column; }
        }
    </style>

    @php
        $unreadNotifications = $notifications->where('is_read', false)->count();
        $latestAdvisory = $advisories->first();
        $profile = auth()->user()->farmerProfile;
    @endphp

    <div class="farmer-console">
        <section class="farmer-hero">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-4 align-items-xl-end">
                <div>
                    <div class="fc-eyebrow">Farmer field view</div>
                    <h1>Climate-smart <em>farmer dashboard.</em></h1>
                    <p>Welcome back, {{ auth()->user()->name }}. Here is your latest climate summary, advisories, community updates, and messages for Lian, Batangas.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="field-chip"><span class="field-pulse"></span> <span data-current-date>{{ now()->format('l, F d, Y') }}</span></span>
                    <a class="fc-btn fc-btn-gold" href="{{ route('heatmap-areas.index') }}">View Heat Map</a>
                    <a class="fc-btn fc-btn-outline-light" href="{{ route('community-feed.index') }}">Community Feed</a>
                    <a class="fc-btn fc-btn-outline-light" href="{{ route('profile.edit') }}">My Profile</a>
                </div>
            </div>
        </section>

        <div class="dashboard-section-label">
            <div>
                <div class="fc-eyebrow on-light">At a glance</div>
                <h2 class="section-title">Today&apos;s overview</h2>
                <p class="section-note">Latest climate values and alerts for quick scanning.</p>
            </div>
        </div>

        <section class="climate-grid">
            <a class="field-card tone-green" href="{{ route('climate-records.index') }}">
                <div class="field-icon">TMP</div>
                <div class="field-label">Temperature</div>
                <div class="field-value">{{ $climateSummary?->temperature !== null ? number_format($climateSummary->temperature, 1).' C' : 'N/A' }}</div>
                <div class="field-note">Latest recorded field climate data</div>
            </a>
            <a class="field-card tone-blue" href="{{ route('climate-records.index') }}">
                <div class="field-icon">RAIN</div>
                <div class="field-label">Rainfall</div>
                <div class="field-value">{{ $climateSummary?->rainfall !== null ? number_format($climateSummary->rainfall, 1).' mm' : 'N/A' }}</div>
                <div class="field-note">Use advisories before fertilizer application</div>
            </a>
            <a class="field-card tone-gold" href="{{ route('climate-records.index') }}">
                <div class="field-icon">HUM</div>
                <div class="field-label">Humidity</div>
                <div class="field-value">{{ $climateSummary?->humidity !== null ? number_format($climateSummary->humidity, 1).'%' : 'N/A' }}</div>
                <div class="field-note">Monitor crop disease risk after rain</div>
            </a>
            <a class="field-card tone-red" href="{{ route('planting-advisories.index') }}">
                <div class="field-icon">ALT</div>
                <div class="field-label">Saved Alerts</div>
                <div class="field-value">{{ number_format($unreadNotifications) }}</div>
                <div class="field-note">Legacy alerts kept for records</div>
            </a>
            <a class="field-card tone-red" href="{{ route('heatmap-areas.index') }}">
                <div class="field-icon">RSK</div>
                <div class="field-label">Heat Map Risks</div>
                <div class="field-value">{{ number_format($highRiskHeatMapAreas) }}</div>
                <div class="field-note">High or severe barangay risk areas</div>
            </a>
        </section>

        <div class="dashboard-section-label">
            <div>
                <div class="fc-eyebrow on-light">Daily tools</div>
                <h2 class="section-title">Priority tools</h2>
                <p class="section-note">The three places farmers are most likely to use every day.</p>
            </div>
        </div>

        <section class="priority-grid">
            <a class="priority-card priority-highlight" href="{{ route('ai-chat.index') }}">
                <div><strong>PalayPilot</strong><span class="desc">Ask questions, predict weather, estimate yield, and get planting or irrigation guidance.</span></div>
                <span class="status-pill">Open PalayPilot</span>
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

        <div class="dashboard-focus-grid">
        <details class="dashboard-group" open>
            <summary>
                <div>
                    <h2 class="dashboard-group-title">Guidance</h2>
                    <p class="dashboard-group-note">Published advisories and the most relevant field note.</p>
                </div>
                <span class="dashboard-group-toggle"></span>
            </summary>
            <div class="dashboard-group-body">
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
            </div>
        </details>

        <details class="dashboard-group">
            <summary>
                <div>
                    <h2 class="dashboard-group-title">Updates And Profile</h2>
                    <p class="dashboard-group-note">Community posts, message access, and your registered farm details.</p>
                </div>
                <span class="dashboard-group-toggle"></span>
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
                <div>
                    <h2 class="dashboard-group-title">Module Access</h2>
                    <p class="dashboard-group-note">All farmer modules in one place.</p>
                </div>
                <span class="dashboard-group-toggle"></span>
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
                    <a class="quick-action" href="{{ route('ai-chat.index') }}"><strong>PalayPilot</strong><span>Ask questions and get weather, yield, planting, irrigation, and warning guidance.</span></a>
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
            if (!dateTarget) return;

            const formatter = new Intl.DateTimeFormat('en-US', {
                weekday: 'long',
                month: 'long',
                day: '2-digit',
                year: 'numeric',
            });

            const refreshDate = () => {
                dateTarget.textContent = formatter.format(new Date());
            };

            refreshDate();
            setInterval(refreshDate, 60000);
        });
    </script>
</x-app-layout>
