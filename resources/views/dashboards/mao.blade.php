<x-app-layout>
    @php
        $yield = $mao['yield'];
        $yieldPoints = ($yield['state'] ?? null) === 'ok' ? $yield['points'] : [];
        $showYield = count($yieldPoints) >= 2;                                   // a trend needs at least two years
        $partialYears = collect($yieldPoints)->filter(fn ($p) => $p['months'] < 12)->map(fn ($p) => $p['year'].' ('.$p['months'].' months)')->implode(', ');
        $climate = $mao['climate'];
        $riskOk = ($mao['risk']['state'] ?? null) === 'ok';
        $coverageOk = ($mao['farmers']['state'] ?? null) === 'ok';
        $typeClass = ['LIVE' => 'is-live', 'HISTORICAL' => 'is-hist', 'RECORDED' => 'is-rec', 'FORECAST' => 'is-fc', 'CALCULATED' => 'is-calc'];
    @endphp

    <style>
        /* MAO overview: one serif family (Georgia), one type scale (12 / 13 / 14 / 18 / 28 / 32px), white cards on a light working surface. */
        .mtd {
            --ink: #17211d; --ink-2: #33443b; --muted: #4b5b52; --line: #dde6e0; --surface: #f3f6f4; --card: #fff;
            --ok: #2f9e6e; --ok-bg: #e3f5ec; --ok-ink: #14503a;
            --warn: #d9a21b; --warn-bg: #fff3cd; --warn-ink: #6b4400;
            --crit: #c8483a; --crit-bg: #fde3df; --crit-ink: #8a2a1c;
            --brand: #1f5a46;
            --mtd-font: Georgia, 'Times New Roman', 'Noto Serif', serif;
            padding: 1.5rem; border-radius: 16px; background: var(--surface); color: var(--ink);
            font-family: var(--mtd-font); font-size: .875rem; line-height: 1.55; font-weight: 400;
            font-variant-numeric: lining-nums; font-feature-settings: 'lnum' 1;
        }
        .mtd, .mtd * { font-family: var(--mtd-font); }
        .mtd h1, .mtd h2, .mtd h3, .mtd th, .mtd .mtd-place, .mtd .risk-map-title, .mtd .risk-map-detail-title { margin: 0; letter-spacing: 0; color: var(--ink); font-weight: 700; }
        .mtd .mtd-place { color: var(--brand); }
        .mtd p { margin: 0; }
        .mtd small, .mtd .mtd-muted { color: var(--muted); font-size: .8125rem; }

        /* the shared risk map keeps its own layout; only its weights follow this page's typography */
        .mtd .risk-map-shell *, .mtd .risk-map-header *, .mtd .risk-map-legend, .mtd .btn { font-weight: 400; }
        .mtd .risk-map-title, .mtd .risk-map-detail-title { font-weight: 700; }
        .mtd strong, .mtd b { font-weight: 400; }

        /* header */
        .mtd-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1.5rem; }
        .mtd-header h1 { font-size: 1.75rem; font-weight: 700; line-height: 1.2; }
        .mtd-header .mtd-place { margin-top: .25rem; font-size: 1rem; font-weight: 400; color: var(--brand); }
        .mtd-header .mtd-lead { margin-top: .25rem; font-size: .875rem; color: var(--ink-2); max-width: 46rem; }
        .mtd-tools { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; }
        .mtd-refreshed { font-size: .8125rem; color: var(--muted); }
        .mtd-btn { display: inline-flex; align-items: center; justify-content: center; gap: .35rem; padding: .45rem .9rem; border-radius: 8px; border: 1px solid var(--brand); background: var(--brand); color: #fff !important; font-size: .8125rem; font-weight: 400; text-decoration: none; cursor: pointer; white-space: nowrap; }
        .mtd-btn:hover { background: #184a3a; }
        .mtd-btn.is-quiet { background: #fff; color: var(--brand) !important; }
        .mtd-btn.is-quiet:hover { background: #e8f5ec; }
        .mtd-btn:disabled { opacity: .6; cursor: progress; }
        .mtd a:focus-visible, .mtd button:focus-visible, .mtd summary:focus-visible, .mtd .mtd-info:focus-visible { outline: 3px solid rgba(31,90,70,.4); outline-offset: 2px; }
        .mtd-link { font-size: .8125rem; font-weight: 400; color: var(--brand); }

        /* data sources */
        .mtd-sources { position: relative; }
        .mtd-sources summary { list-style: none; cursor: pointer; padding: .45rem .8rem; border: 1px solid var(--line); border-radius: 8px; background: #fff; font-size: .8125rem; font-weight: 400; color: var(--ink-2); }
        .mtd-sources summary::-webkit-details-marker { display: none; }
        .mtd-sources-panel { position: absolute; z-index: 40; right: 0; top: calc(100% + 6px); width: min(420px, 88vw); padding: 1rem; background: #fff; border: 1px solid var(--line); border-radius: 12px; box-shadow: 0 10px 30px rgba(13,31,24,.18); }
        .mtd-sources-panel ul { list-style: none; margin: 0 0 .5rem; padding: 0; display: grid; gap: .6rem; }
        .mtd-sources-panel li strong { display: block; font-size: .8125rem; }
        .mtd-sources-panel li span { font-size: .8125rem; color: var(--ink-2); }
        .mtd-tag { display: inline-block; margin-right: .35rem; padding: .05rem .5rem; border-radius: 999px; font-size: .75rem; font-weight: 400; letter-spacing: .04em; background: #eef1ef; color: #3d4a43; }
        .mtd-tag.is-live { background: var(--ok-bg); color: var(--ok-ink); } .mtd-tag.is-fc { background: #dde8f6; color: #243f6b; }
        .mtd-tag.is-hist { background: #ece6f7; color: #4a3a86; } .mtd-tag.is-rec { background: #e8eee8; color: #26382f; } .mtd-tag.is-calc { background: var(--warn-bg); color: var(--warn-ink); }

        /* rows: flex, so a hidden card lets its neighbour fill the row */
        .mtd-row { display: flex; flex-wrap: wrap; gap: 1.25rem; margin-bottom: 1.25rem; align-items: stretch; }
        .mtd-cell { display: flex; flex: 1 1 100%; min-width: 0; }
        .mtd-cell[hidden] { display: none; }
        .mtd-cell > .mtd-card, .mtd-cell > .mtd-kpis { flex: 1; min-width: 0; }
        @media (min-width: 1100px) {
            .mtd-row.r-50-50 > .mtd-cell { flex: 1 1 0; }
            .mtd-row.r-65-35 > .mtd-cell:first-child { flex: 13 1 0; } .mtd-row.r-65-35 > .mtd-cell:last-child { flex: 7 1 0; }
        }

        /* cards */
        .mtd-card { background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 1.15rem; box-shadow: 0 1px 3px rgba(13,31,24,.06); }
        .mtd-card-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem 1rem; margin-bottom: 1rem; }
        .mtd-card-head h2 { font-size: 1.125rem; font-weight: 700; display: flex; align-items: center; gap: .4rem; }
        .mtd-sub { margin-top: .15rem; font-size: .8125rem; color: var(--muted); }
        .mtd-foot { margin-top: .85rem; font-size: .8125rem; color: var(--muted); }
        .mtd-head-actions { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; }
        .mtd-updated { font-size: .8125rem; color: var(--muted); }
        .mtd-count { min-width: 1.6rem; padding: .1rem .5rem; border-radius: 999px; text-align: center; font-size: .75rem; font-weight: 400; background: var(--warn-bg); color: var(--warn-ink); }
        .mtd-svg { width: 1.15rem; height: 1.15rem; }
        .mtd-soft-error { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; padding: .75rem 1rem; border-radius: 8px; background: #f1f3f2; color: var(--ink-2); font-weight: 400; }

        /* KPI row */
        .mtd-kpis { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1fr); }
        @media (min-width: 560px) { .mtd-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (min-width: 820px) { .mtd-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (min-width: 1200px) { .mtd-kpis { grid-template-columns: repeat(var(--mtd-kpi-count, 5), minmax(0, 1fr)); } }
        .mtd-kpi { display: flex; flex-direction: column; gap: .2rem; min-height: 10rem; padding: 1.15rem; background: var(--card); border: 1px solid var(--line); border-radius: 12px; text-decoration: none; color: var(--ink); box-shadow: 0 1px 3px rgba(13,31,24,.06); min-width: 0; }
        .mtd-kpi:hover { border-color: #b9dcc6; box-shadow: 0 4px 12px rgba(13,31,24,.08); }
        .mtd-kpi-top { display: flex; align-items: center; gap: .5rem; }
        .mtd-kpi-icon { display: grid; place-items: center; width: 2rem; height: 2rem; border-radius: 8px; background: var(--ok-bg); color: var(--brand); flex: none; }
        .mtd-kpi-label { font-size: .75rem; font-weight: 400; text-transform: uppercase; letter-spacing: .04em; color: var(--ink-2); }
        .mtd-kpi-value { display: flex; align-items: baseline; gap: .35rem; margin-top: .5rem; font-size: 2rem; font-weight: 400; line-height: 1.1; overflow-wrap: anywhere; }
        .mtd-kpi-value small { font-size: .875rem; font-weight: 400; color: var(--muted); }
        .mtd-kpi-context { font-size: .8125rem; color: var(--ink-2); }
        .mtd-trend { margin-top: auto; font-size: .8125rem; font-weight: 400; color: var(--ink-2); }
        .mtd-trend.is-up strong { color: var(--ok-ink); } .mtd-trend.is-down strong { color: var(--crit-ink); }
        .mtd-basis { font-size: .75rem; color: var(--muted); }

        /* info tooltips */
        .mtd-info { position: relative; display: inline-grid; place-items: center; width: 1.05rem; height: 1.05rem; flex: none; margin-left: auto; border-radius: 50%; border: 1px solid #7d8c84; color: #3d4a43; font-size: .75rem; font-weight: 400; cursor: help; text-transform: none; letter-spacing: 0; }
        .mtd-info::after { content: attr(data-tip); position: absolute; z-index: 30; right: 0; top: calc(100% + 6px); width: max-content; max-width: min(280px, 72vw); padding: .5rem .65rem; border-radius: 8px; background: #17211d; color: #fff; font-size: .8125rem; font-weight: 400; line-height: 1.4; text-align: left; white-space: normal; opacity: 0; pointer-events: none; transition: opacity .12s; }
        
        /* attention */
        .mtd-attention.is-clear { padding: .65rem 1rem; }
        .mtd-allgood { display: flex; align-items: center; gap: .6rem; padding: .6rem .9rem; border-radius: 8px; background: var(--ok-bg); color: var(--ok-ink) !important; font-weight: 400; }
        .mtd-attention.is-clear .mtd-allgood { padding: 0; background: transparent; }
        .mtd-attn-list { list-style: none; margin: 0; padding: 0; display: grid; gap: .6rem; }
        .mtd-attn { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: .85rem; padding: .7rem .9rem; border: 1px solid var(--line); border-left: 5px solid var(--warn); border-radius: 10px; background: #fffdf5; }
        .mtd-attn.is-urgent { border-left-color: var(--crit); background: #fff7f5; }
        .mtd-attn-icon { display: grid; place-items: center; width: 2rem; height: 2rem; border-radius: 8px; background: var(--warn-bg); color: var(--warn-ink); }
        .is-urgent .mtd-attn-icon { background: var(--crit-bg); color: var(--crit-ink); }
        .mtd-attn-body h3 { display: block; font-size: .875rem; font-weight: 700; }
        .mtd-attn-body span { font-size: .8125rem; color: var(--ink-2); }
        @media (max-width: 640px) { .mtd-attn { grid-template-columns: auto minmax(0, 1fr); } .mtd-attn .mtd-btn { grid-column: 1 / -1; justify-self: start; } }

        /* conditions + tiles */
        .mtd-tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: .85rem; }
        .mtd-tile { display: flex; flex-direction: column; gap: .15rem; padding: .85rem 1rem; border: 1px solid var(--line); border-radius: 10px; background: #fafcfb; min-width: 0; }
        .mtd-tile-icon { color: var(--brand); } .mtd-tile-icon .mtd-svg { width: 1.3rem; height: 1.3rem; }
        .mtd-tile-label { font-size: .75rem; font-weight: 400; text-transform: uppercase; letter-spacing: .04em; color: var(--ink-2); }
        .mtd-tile-label em { font-style: normal; text-transform: none; font-weight: 400; color: var(--muted); }
        .mtd-tile strong { font-size: 1.75rem; line-height: 1.15; font-weight: 400; }
        .mtd-tile small { margin-left: .25rem; font-size: .875rem; font-weight: 400; }

        /* charts */
        .mtd-chart { position: relative; height: 280px; }
        .mtd-range { display: inline-flex; border: 1px solid var(--line); border-radius: 8px; overflow: hidden; }
        .mtd-range button { padding: .3rem .75rem; border: 0; background: #fff; color: var(--ink-2); font-size: .8125rem; font-weight: 400; cursor: pointer; }
        .mtd-range button + button { border-left: 1px solid var(--line); }
        .mtd-range button[aria-pressed="true"] { background: var(--brand); color: #fff; }
        .mtd-risk { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 1.5rem; min-height: 240px; }
        .mtd-donut { position: relative; width: 200px; height: 200px; flex: none; }
        .mtd-donut canvas { width: 100% !important; height: 100% !important; }
        .mtd-donut-center { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none; }
        .mtd-donut-center strong { font-size: 2rem; line-height: 1; font-weight: 400; }
        .mtd-donut-center span { margin-top: .2rem; font-size: .75rem; letter-spacing: .04em; font-weight: 400; color: var(--muted); }
        .mtd-risk-list { list-style: none; margin: 0; padding: 0; display: grid; gap: .5rem; min-width: 190px; }
        .mtd-risk-list li { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: .55rem; padding: .4rem .65rem; border-radius: 8px; background: #fafcfb; border: 1px solid var(--line); }
        .mtd-risk-glyph { display: grid; place-items: center; width: 1.4rem; height: 1.4rem; border-radius: 50%; font-size: .75rem; font-weight: 400; color: #fff; }
        .is-low .mtd-risk-glyph { background: #2f9e6e; } .is-mod .mtd-risk-glyph { background: #d9a21b; } .is-high .mtd-risk-glyph { background: #e0714f; } .is-sev .mtd-risk-glyph { background: #b03a2e; }
        .mtd-risk-name { font-size: .875rem; font-weight: 400; } .mtd-risk-list strong { font-size: 1.125rem; }

        /* rows table */
        .mtd-rows { margin: 0 0 1rem; display: grid; }
        .mtd-rows > div { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: .6rem 0; border-bottom: 1px solid var(--line); }
        .mtd-rows dt { font-size: .875rem; color: var(--ink-2); font-weight: 400; } .mtd-rows dd { margin: 0; font-size: 1.125rem; font-weight: 400; text-align: right; }

        /* forecast */
        .mtd-days { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(128px, 1fr); gap: .75rem; overflow-x: auto; padding-bottom: .35rem; }
        .mtd-day { display: flex; flex-direction: column; align-items: center; gap: .25rem; padding: .85rem .6rem; border: 1px solid var(--line); border-radius: 10px; background: #fafcfb; text-align: center; }
        .mtd-day.is-flagged { border-color: var(--warn); background: #fffbea; }
        .mtd-day-head { display: flex; flex-direction: column; } .mtd-day-head strong { font-size: .875rem; font-weight: 400; } .mtd-day-head span { font-size: .8125rem; color: var(--muted); }
        .mtd-day-cond { font-size: .8125rem; font-weight: 400; }
        .mtd-day-vals { list-style: none; margin: 0; padding: 0; font-size: .8125rem; color: var(--ink-2); display: grid; gap: .1rem; }
        .mtd-flag { margin-top: .2rem; padding: .1rem .5rem; border-radius: 999px; background: var(--warn-bg); color: var(--warn-ink); font-size: .75rem; font-weight: 400; }

        /* watch + clear */
        .mtd-watch { list-style: none; margin: 0; padding: 0; display: grid; gap: .6rem; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
        .mtd-watch li { padding: .75rem .9rem; border: 1px solid var(--line); border-radius: 10px; background: #fafcfb; }
        .mtd-watch-main { display: flex; justify-content: space-between; align-items: center; gap: .5rem; } .mtd-watch-main strong { font-size: .875rem; }
        .mtd-watch p { margin-top: .3rem; font-size: .8125rem; color: var(--ink-2); }
        .mtd-badge { display: inline-flex; align-items: center; gap: .3rem; padding: .1rem .55rem; border-radius: 999px; font-size: .75rem; font-weight: 400; letter-spacing: .04em; }
        .mtd-badge.is-high { background: #fde9e1; color: #8a3a1d; } .mtd-badge.is-sev { background: var(--crit-bg); color: var(--crit-ink); }
        .mtd-clear { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; padding: .75rem 1rem; }
        .mtd-clear-body small { display: block; margin-top: .25rem; }
        .mtd-clear .mtd-allgood { padding: 0; background: transparent; }

        /* table */
        .mtd-table-wrap { overflow-x: auto; }
        .mtd-table { width: 100%; border-collapse: collapse; font-size: .8125rem; }
        .mtd-table th { font-weight: 700; padding: .5rem .6rem; text-align: left; font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: var(--ink-2); border-bottom: 2px solid var(--line); white-space: nowrap; }
        .mtd-table td { padding: .6rem; border-bottom: 1px solid var(--line); vertical-align: top; }
        .mtd-table tr:last-child td { border-bottom: 0; }
        .mtd-nowrap { white-space: nowrap; }
        @media (max-width: 767.98px) {
            .mtd-table-cards thead { display: none; }
            .mtd-table-cards, .mtd-table-cards tbody, .mtd-table-cards tr, .mtd-table-cards td { display: block; width: 100%; }
            .mtd-table-cards tr { padding: .5rem 0; border-bottom: 1px solid var(--line); }
            .mtd-table-cards td { border: 0; padding: .15rem 0; }
            .mtd-table-cards td::before { content: attr(data-label) ": "; font-weight: 400; color: var(--muted); }
        }

        .mtd.is-loading [data-slot] { opacity: .55; transition: opacity .15s; }
        .mtd-more { margin-top: .5rem; text-align: center; }
        @media (max-width: 575.98px) { .mtd { padding: 1rem; } .mtd-header h1 { font-size: 1.5rem; } .mtd-tile strong { font-size: 1.5rem; } }
        @media (max-width: 640px) {
            .mtd-tools { position: relative; width: 100%; }
            .mtd-sources { position: static; }
            .mtd-sources-panel { left: 0; right: 0; width: auto; max-width: none; }
        }
        .mtd-info:hover::after, .mtd-info:focus::after, .mtd-info.is-open::after { opacity: 1; }
        .mtd-empty { display: flex; flex-direction: column; justify-content: center; gap: .25rem; min-height: 8rem; padding: 1rem; border: 1px dashed var(--line); border-radius: 10px; background: #fafcfb; color: var(--ink-2); text-align: center; }
        .mtd-empty strong { color: var(--ink); }
        .mtd-bell { position: relative; }
        .mtd-bell-count { display: inline-block; min-width: 1.35rem; padding: 0 .35rem; border-radius: 999px; background: var(--crit); color: #fff; font-size: .75rem; line-height: 1.35rem; text-align: center; }
        .mtd-bell.is-quiet .mtd-bell-count { background: var(--crit); }
        .mtd-btn.is-quiet.mtd-bell { color: var(--brand) !important; }
        @media (prefers-reduced-motion: reduce) { .mtd * { transition: none !important; } }
    </style>

    <div class="mtd" data-mao-dashboard data-live-url="{{ route('mao.dashboard.live') }}">
        <header class="mtd-header">
            <div>
                <h1>MAO Dashboard</h1>
                <div class="mtd-place">Municipal Agriculture Monitoring — Lian, Batangas</div>
                <p class="mtd-lead">Monitor farmers, weather conditions, farm risk, rice yield, and agricultural advisories.</p>
            </div>
            <div class="mtd-tools">
                <span class="mtd-refreshed" role="status" aria-live="polite">Last updated: <span data-mao-refreshed>{{ $mao['generated_at']->format('g:i A') }}</span></span>
                <details class="mtd-sources">
                    <summary>ⓘ Data Sources</summary>
                    <div class="mtd-sources-panel">
                        <ul>
                            @foreach ($mao['sources'] as $source)
                                <li>
                                    <strong><span class="mtd-tag {{ $typeClass[$source['type']] ?? '' }}">{{ $source['type'] }}</span>{{ $source['label'] }}</strong>
                                    <span>{{ $source['source'] }}@if ($source['updated']) · updated {{ $source['updated']->format('M j, g:i A') }}@endif</span>
                                </li>
                            @endforeach
                        </ul>
                        <small>Last synchronized: {{ $mao['generated_at']->format('M j, Y g:i A') }}. Live, forecast, historical, recorded and calculated data are never mixed without a label.</small>
                    </div>
                </details>
                <a class="mtd-btn is-quiet mtd-bell" href="{{ route('notifications.index') }}" data-mao-bell aria-label="Notifications{{ $mao['unread_notifications'] > 0 ? ', '.$mao['unread_notifications'].' unread' : '' }}">Notifications @if ($mao['unread_notifications'] > 0) <span class="mtd-bell-count">{{ $mao['unread_notifications'] > 99 ? '99+' : $mao['unread_notifications'] }}</span>@endif</a>
                <button type="button" class="mtd-btn is-quiet" data-mao-refresh>Refresh</button>
                <a class="mtd-btn" href="{{ route('planting-advisories.create') }}">Create Advisory</a>
                <a class="mtd-btn is-quiet" href="{{ route('reports.index') }}">Open Reports</a>
            </div>
        </header>

        <div class="mtd-row"><div class="mtd-cell" data-slot="kpis">@include('dashboards.mao.kpis')</div></div>
        <div class="mtd-row"><div class="mtd-cell" data-slot="attention">@include('dashboards.mao.attention')</div></div>
        <div class="mtd-row"><div class="mtd-cell" data-slot="conditions">@include('dashboards.mao.conditions')</div></div>

        <div class="mtd-row r-50-50">
                <div class="mtd-cell">
                    @if (! $showYield)
                        <section class="mtd-card" aria-labelledby="mtdYieldTitle" data-mao-yield-empty>
                            <div class="mtd-card-head"><h2 id="mtdYieldTitle">Rice Yield Trend</h2></div>
                            <div class="mtd-empty" role="status">
                                @if (($yield['state'] ?? null) === 'error')<strong>Rice yield history is temporarily unavailable.</strong><span>Please try again shortly.</span>
                                @else<strong>No data available.</strong><span>A yield trend needs recorded yields for at least two years.</span>@endif
                            </div>
                        </section>
                    @else
                        <section class="mtd-card" aria-labelledby="mtdYieldTitle" data-mao-yield>
                            <div class="mtd-card-head">
                                <h2 id="mtdYieldTitle">Rice Yield Trend</h2>
                                @if (count($yieldPoints) > 5)
                                    <div class="mtd-range" role="group" aria-label="Years shown">
                                        <button type="button" data-yield-range="5" aria-pressed="false">5 Years</button>
                                        <button type="button" data-yield-range="all" aria-pressed="true">All</button>
                                    </div>
                                @endif
                            </div>
                            <div class="mtd-chart">
                                <canvas id="riceYieldTrendChart" data-yield="{{ json_encode(['points' => $yieldPoints, 'source' => $yield['source']]) }}"
                                    data-chart-source="{{ $yield['source'] }}" data-chart-type="historical" data-chart-unit="t/ha" data-chart-info-style="icon"
                                    data-chart-note="Historical data, not a forecast. A year with fewer than 12 monthly records is the average of the months on file."
                                    aria-label="Average rice yield by year"></canvas>
                            </div>
                            <p class="mtd-foot">Average rice yield by year, in tons per hectare (historical).@if ($partialYears) Years with fewer than 12 monthly records: {{ $partialYears }}. These years are shown as hollow points with dashed lines.@endif</p>
                        </section>
                    @endif
                </div>
                <div class="mtd-cell" data-slot="risk">@include('dashboards.mao.risk')</div>
            </div>

        <div class="mtd-row r-65-35">
                <div class="mtd-cell">
                    @unless ($climate)
                        <section class="mtd-card" aria-labelledby="mtdClimateTitle" data-mao-climate-empty>
                            <div class="mtd-card-head"><h2 id="mtdClimateTitle">Rainfall &amp; Temperature</h2></div>
                            <div class="mtd-empty" role="status"><strong>No data available.</strong><span>No monthly rainfall or temperature records could be loaded.</span></div>
                        </section>
                    @else
                        <section class="mtd-card" aria-labelledby="mtdClimateTitle" data-mao-climate>
                            <div class="mtd-card-head"><h2 id="mtdClimateTitle">Rainfall &amp; Temperature — {{ $climate['year'] }}</h2></div>
                            <div class="mtd-chart">
                                <canvas id="maoRainTempChart" data-climate="{{ json_encode(['labels' => $climate['chart']['labels'], 'datasets' => $climate['chart']['datasets']]) }}"
                                    data-chart-source="Ambulong monthly climate records (recorded); iClimate monthly model (forecast, dashed)" data-chart-type="mixed"
                                    data-chart-period="{{ $climate['year'] }}, by month" data-chart-info-style="icon" aria-label="Monthly rainfall and temperature"></canvas>
                            </div>
                            <p class="mtd-foot">
                                Solid lines are historical records; dashed lines are model forecasts.
                                @if ($climate['annual_rainfall'] !== null)
                                    Annual rainfall {{ $climate['year'] }}: <strong>{{ number_format($climate['annual_rainfall'], 0) }} mm</strong>
                                    (total of {{ $climate['rainfall_months'] }} recorded {{ $climate['rainfall_months'] === 1 ? 'month' : 'months' }}@if ($climate['rainfall_months'] < 12), not a full year @endif).
                                @endif
                            </p>
                        </section>
                    @endunless
                </div>
                <div class="mtd-cell" data-slot="coverage">@include('dashboards.mao.coverage')</div>
            </div>

        <div class="mtd-row"><div class="mtd-cell" data-slot="forecast" @if (($mao['weather']['state'] ?? null) !== 'ok' || count($mao['weather']['days'] ?? []) === 0) hidden @endif>@include('dashboards.mao.forecast')</div></div>
        <div class="mtd-row"><div class="mtd-cell" data-slot="watch" @if (($mao['watch']['state'] ?? 'hidden') === 'hidden') hidden @endif>@include('dashboards.mao.watch')</div></div>
        <div class="mtd-row"><div class="mtd-cell" data-slot="production" @if (! $mao['production']) hidden @endif>@include('dashboards.mao.production')</div></div>
        <div class="mtd-row"><div class="mtd-cell" data-slot="feedback" @if (! $mao['feedback']) hidden @endif>@include('dashboards.mao.feedback')</div></div>

        @if ($riskOk)
            <div class="mtd-row">
                <div class="mtd-cell">
                    <section class="mtd-card" id="mtdRiskMap" aria-label="Barangay risk map">
                        @include('partials.barangay-risk-map', [
                            'heading' => 'Barangay Agricultural Risk Map',
                            'subheading' => 'All 19 Lian barangays, from live weather and recorded rice yield. Click a barangay for details.',
                            'showRefresh' => true,
                        ])
                    </section>
                </div>
            </div>
        @endif

        <div class="mtd-row"><div class="mtd-cell" data-slot="activity" @if (($mao['activity']['state'] ?? null) !== 'ok') hidden @endif>@include('dashboards.mao.activity')</div></div>

        <p class="mtd-more"><a class="mtd-link" href="{{ route('mao.operations') }}">More MAO tools: field reports, planting calendar, supplies, and reports center →</a></p>
    </div>

    <script>
        (() => {
            const root = document.querySelector('[data-mao-dashboard]');
            if (!root) return;
            const $ = (s) => root.querySelector(s);
            const charts = {};
            const FONT = "Georgia, 'Times New Roman', 'Noto Serif', serif";
            const ready = async () => {
                try { await window.iClimateVisualsReady; } catch (e) { /* handled by callers */ }
                if (window.Chart) { window.Chart.defaults.font.family = FONT; window.Chart.defaults.font.size = 12; window.Chart.defaults.font.weight = 400; window.Chart.defaults.color = '#33443b'; }
                return !!window.Chart;
            };

            async function initDonut() {
                const canvas = $('canvas[data-donut]');
                if (!canvas || !(await ready())) return;
                charts.donut?.destroy();
                const cfg = JSON.parse(canvas.dataset.donut);
                charts.donut = new window.Chart(canvas, {
                    type: 'doughnut',
                    data: { labels: cfg.labels, datasets: [{ data: cfg.counts, backgroundColor: cfg.colors, borderColor: '#fff', borderWidth: 2 }] },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '66%', plugins: { legend: { display: false } } },
                });
            }

            async function initYield(range) {
                const canvas = $('#riceYieldTrendChart');
                if (!canvas || !(await ready())) return;
                const cfg = JSON.parse(canvas.dataset.yield);
                const pts = range === '5' ? cfg.points.slice(-5) : cfg.points;
                charts.yield?.destroy();
                charts.yield = new window.Chart(canvas, {
                    type: 'line',
                    data: { labels: pts.map((p) => String(p.year)), datasets: [{
                        label: 'Average yield (t/ha)', data: pts.map((p) => p.yield), borderColor: '#1f5a46', backgroundColor: 'rgba(31,90,70,.12)', borderWidth: 2.5, tension: .25, fill: true,
                        // A year with fewer than 12 monthly records is drawn differently: hollow point, dashed line into and out of it.
                        pointRadius: pts.map((p) => (p.months < 12 ? 5 : 4)), pointHoverRadius: 7,
                        pointBackgroundColor: pts.map((p) => (p.months < 12 ? '#ffffff' : '#1f5a46')), pointBorderColor: '#1f5a46', pointBorderWidth: 2,
                        segment: { borderDash: (c) => (pts[c.p0DataIndex].months < 12 || pts[c.p1DataIndex].months < 12 ? [6, 4] : undefined) },
                    }] },
                    options: {
                        responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                        scales: {
                            y: { beginAtZero: false, title: { display: true, text: 'Yield (t/ha)', font: { size: 12 } }, ticks: { font: { size: 12 } }, grid: { color: '#eef2ef' } },
                            x: { title: { display: true, text: 'Year', font: { size: 12 } }, ticks: { font: { size: 12 } }, grid: { display: false } },
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: {
                                title: (items) => 'Year ' + items[0].label,
                                label: (c) => 'Average yield: ' + c.parsed.y.toFixed(2) + ' t/ha',
                                afterLabel: (c) => { const p = pts[c.dataIndex]; const rows = ['Records: ' + p.months + ' monthly ' + (p.months === 1 ? 'record' : 'records')]; if (p.months < 12) rows.push('Partial year: average of the ' + p.months + ' months on file'); rows.push('Source: iClimate modeling dataset (historical)'); return rows; },
                            } },
                        },
                    },
                });
            }

            async function initClimate() {
                const canvas = $('#maoRainTempChart');
                if (!canvas || !(await ready())) return;
                const cfg = JSON.parse(canvas.dataset.climate);
                const colors = ['#2b7a9b', '#e0714f'];
                const datasets = cfg.datasets.map((d) => ({
                    label: d.label, data: d.data, yAxisID: d.axis || 'y', borderColor: colors[d.series ?? 0], backgroundColor: colors[d.series ?? 0],
                    borderWidth: 2.5, tension: .3, spanGaps: false, borderDash: d.kind === 'predicted' ? [6, 4] : [],
                    pointStyle: d.kind === 'predicted' ? 'rectRot' : 'circle', pointRadius: d.kind === 'predicted' ? 5 : 3.5,
                })).filter((d) => d.data.some((v) => v !== null));
                charts.climate?.destroy();
                charts.climate = new window.Chart(canvas, {
                    type: 'line', data: { labels: cfg.labels, datasets },
                    options: {
                        responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                        scales: {
                            y: { position: 'left', beginAtZero: true, title: { display: true, text: 'Rainfall (mm)', font: { size: 12 } }, ticks: { font: { size: 12 } }, grid: { color: '#eef2ef' } },
                            y2: { position: 'right', title: { display: true, text: 'Temperature (°C)', font: { size: 12 } }, ticks: { font: { size: 12 } }, grid: { drawOnChartArea: false } },
                            x: { title: { display: true, text: 'Month', font: { size: 12 } }, ticks: { font: { size: 12 } }, grid: { display: false } },
                        },
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 14, font: { size: 12 } } },
                            tooltip: { callbacks: {
                                label: (c) => c.dataset.label + ': ' + (c.parsed.y === null ? 'n/a' : c.parsed.y.toLocaleString(undefined, { maximumFractionDigits: 1 })) + (c.dataset.yAxisID === 'y2' ? ' °C' : ' mm'),
                            } },
                        },
                    },
                });
            }

            // Info tips sit inside a link: a tap opens the tip instead of following the link; Escape / outside tap closes it.
            const closeTips = (except) => root.querySelectorAll('.mtd-info.is-open').forEach((t) => { if (t !== except) t.classList.remove('is-open'); });
            root.addEventListener('click', (e) => {
                const tip = e.target.closest('.mtd-info');
                if (tip) { e.preventDefault(); e.stopPropagation(); closeTips(tip); tip.classList.toggle('is-open'); return; }
                closeTips(null);
                const sources = root.querySelector('.mtd-sources');
                if (sources && sources.open && !e.target.closest('.mtd-sources')) sources.open = false;
            });
            document.addEventListener('keydown', (e) => {
                if (e.key !== 'Escape') return;
                closeTips(null);
                const sources = root.querySelector('.mtd-sources');
                if (sources) sources.open = false;
            });

            root.querySelectorAll('[data-yield-range]').forEach((b) => b.addEventListener('click', () => {
                root.querySelectorAll('[data-yield-range]').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
                initYield(b.dataset.yieldRange);
            }));

            async function refresh(force) {
                const btn = $('[data-mao-refresh]');
                btn.disabled = true; btn.textContent = 'Refreshing...'; root.classList.add('is-loading');
                try {
                    const res = await fetch(root.dataset.liveUrl + (force ? '?refresh=1' : ''), { headers: { Accept: 'application/json' } });
                    if (!res.ok) throw new Error('bad status');
                    const data = await res.json();
                    charts.donut?.destroy(); charts.donut = null;
                    Object.entries(data.slots).forEach(([key, html]) => {
                        const el = root.querySelector('[data-slot="' + key + '"]');
                        if (el) { el.innerHTML = html; el.hidden = html.trim() === ''; }
                    });
                    $('[data-mao-refreshed]').textContent = data.refreshed_at;
                    initDonut();
                } catch (e) {
                    $('[data-mao-refreshed]').textContent = 'could not refresh; showing the last loaded data';
                } finally {
                    btn.disabled = false; btn.textContent = 'Refresh'; root.classList.remove('is-loading');
                }
            }
            $('[data-mao-refresh]').addEventListener('click', () => refresh(true));
            root.addEventListener('click', (e) => { if (e.target.closest('[data-mao-retry]')) refresh(true); });
            // Weather changes through the day; refresh every 5 minutes, only while the tab is visible.
            setInterval(() => { if (document.visibilityState === 'visible') refresh(false); }, 300000);

            const start = () => { initDonut(); initYield('all'); initClimate(); };
            document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();
        })();
    </script>
</x-app-layout>
