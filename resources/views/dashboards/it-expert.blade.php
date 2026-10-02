<x-app-layout>
    @php
        $glyph = ['ok' => '✓', 'warn' => '!', 'crit' => '✕'];
        $overall = $it['overall'];
    @endphp

    <style>
        /* IT dashboard: light working surface inside the green shell; status colours carry meaning. */
        .itd {
            --itd-ink: #0d1f18; --itd-ink-2: #26382f; --itd-muted: #4a5a52;
            --itd-line: #dde6e0; --itd-surface: #f3f6f4; --itd-card: #fff;
            --itd-ok: #2f9e6e; --itd-ok-bg: #e3f5ec; --itd-ok-ink: #14503a;
            --itd-warn: #d9a21b; --itd-warn-bg: #fff3cd; --itd-warn-ink: #6b4400;
            --itd-crit: #c8483a; --itd-crit-bg: #fde3df; --itd-crit-ink: #8a2a1c;
            --itd-unk: #8a958f; --itd-unk-bg: #eef1ef; --itd-unk-ink: #3d4a43;
            --itd-gap: 1.25rem;
            padding: 1.5rem; border-radius: 16px; background: var(--itd-surface); color: var(--itd-ink);
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif; font-size: .9375rem; line-height: 1.5;
        }
        .itd, .itd * { font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif; }
        .itd h1, .itd h2, .itd h3 { font-weight: 700; letter-spacing: 0; color: var(--itd-ink); margin: 0; }
        .itd p { margin: 0; color: var(--itd-ink-2); font-size: .9375rem; }
        .itd small, .itd .itd-muted { color: var(--itd-muted); font-size: .8125rem; }
        .itd-muted.d-block { display: block; }

        /* header */
        .itd-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: var(--itd-gap); }
        .itd-header h1 { font-size: 1.75rem; line-height: 1.2; }
        .itd-header p { margin-top: .25rem; }
        .itd-tools { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; }
        .itd-refreshed { font-size: .8125rem; color: var(--itd-muted); }
        .itd-pill { display: inline-flex; align-items: center; gap: .4rem; padding: .3rem .8rem; border-radius: 999px; font-size: .8125rem; font-weight: 700; }
        .itd-pill.is-ok, .itd-badge.is-ok { background: var(--itd-ok-bg); color: var(--itd-ok-ink); }
        .itd-pill.is-warn, .itd-badge.is-warn { background: var(--itd-warn-bg); color: var(--itd-warn-ink); }
        .itd-pill.is-crit, .itd-badge.is-crit { background: var(--itd-crit-bg); color: var(--itd-crit-ink); }
        .itd-pill.is-unk, .itd-badge.is-unk, .itd-badge.is-neutral { background: var(--itd-unk-bg); color: var(--itd-unk-ink); }
        .itd-btn { display: inline-flex; align-items: center; justify-content: center; padding: .4rem .9rem; border-radius: 8px; border: 1px solid #1f5a46; background: #1f5a46; color: #fff !important; font-size: .8125rem; font-weight: 600; text-decoration: none; cursor: pointer; white-space: nowrap; }
        .itd-btn:hover { background: #184a3a; }
        .itd-btn.is-quiet { background: #fff; color: #1f5a46 !important; }
        .itd-btn.is-quiet:hover { background: #e8f5ec; }
        .itd-btn:disabled { opacity: .6; cursor: progress; }
        .itd-btn:focus-visible, .itd a:focus-visible, .itd summary:focus-visible, .itd .itd-info:focus-visible { outline: 3px solid rgba(31,90,70,.4); outline-offset: 2px; }
        .itd-link { font-size: .8125rem; font-weight: 600; color: #1f5a46; }

        /* rows: flex, so when a card is hidden its neighbour simply grows to fill the row */
        .itd-row { display: flex; flex-wrap: wrap; gap: var(--itd-gap); margin-bottom: var(--itd-gap); align-items: stretch; }
        .itd-cell { display: flex; flex: 1 1 100%; min-width: 0; }
        .itd-cell[hidden] { display: none; }
        .itd-cell > .itd-card, .itd-cell > .itd-kpis { flex: 1; min-width: 0; }
        @media (min-width: 1100px) {
            .itd-row.r-55-45 > .itd-cell:first-child { flex: 11 1 0; } .itd-row.r-55-45 > .itd-cell:last-child { flex: 9 1 0; }
            .itd-row.r-60-40 > .itd-cell:first-child { flex: 3 1 0; } .itd-row.r-60-40 > .itd-cell:last-child { flex: 2 1 0; }
        }
        /* cards */
        .itd-card { background: var(--itd-card); border: 1px solid var(--itd-line); border-radius: 12px; padding: 1rem; box-shadow: 0 1px 2px rgba(13,31,24,.05); }
        .itd-card-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem; margin-bottom: .75rem; }
        .itd-card-head h2 { font-size: 1.0625rem; display: flex; align-items: center; gap: .4rem; }
        .itd-foot { margin-top: .75rem !important; font-size: .8125rem !important; color: var(--itd-muted) !important; }
        .itd-empty { padding: 1rem; text-align: center; color: var(--itd-muted); background: var(--itd-unk-bg); border-radius: 8px; font-weight: 600; }
        .itd-error { padding: .6rem .8rem; border-radius: 8px; background: var(--itd-crit-bg); color: var(--itd-crit-ink); font-weight: 600; font-size: .875rem; }

        /* KPI row */
        .itd-kpis { display: grid; gap: var(--itd-gap); grid-template-columns: minmax(0, 1fr); }
        @media (min-width: 576px) { .itd-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (min-width: 1000px) { .itd-kpis { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .itd-kpi { display: flex; flex-direction: column; justify-content: flex-start; gap: .15rem; min-height: 9.5rem; padding: 1rem; background: var(--itd-card); border: 1px solid var(--itd-line); border-left: 5px solid var(--itd-line); border-radius: 12px; text-decoration: none; color: var(--itd-ink); box-shadow: 0 1px 2px rgba(13,31,24,.05); min-width: 0; }
        .itd-kpi:hover { border-color: #b9dcc6; box-shadow: 0 4px 12px rgba(13,31,24,.08); }
        .itd-kpi.is-ok { border-left-color: var(--itd-ok); } .itd-kpi.is-warn { border-left-color: var(--itd-warn); }
        .itd-kpi.is-crit { border-left-color: var(--itd-crit); } .itd-kpi.is-unk { border-left-color: var(--itd-unk); }
        .itd-kpi-top { display: flex; align-items: center; gap: .4rem; }
        .itd-kpi-label { font-size: .8125rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--itd-ink-2); }
        .itd-kpi-value { display: flex; align-items: center; gap: .5rem; margin-top: .35rem; font-size: 2.125rem; font-weight: 800; line-height: 1.1; color: var(--itd-ink); overflow-wrap: anywhere; }
        .itd-kpi-value.is-compact { font-size: 1.5rem; line-height: 1.2; }
        .itd-kpi.is-warn .itd-kpi-value { color: var(--itd-warn-ink); } .itd-kpi.is-crit .itd-kpi-value { color: var(--itd-crit-ink); }
        .itd-kpi.is-unk .itd-kpi-value { color: var(--itd-unk-ink); }
        .itd-glyph { display: inline-grid; place-items: center; flex: none; width: 1.5rem; height: 1.5rem; border-radius: 50%; font-size: .85rem; font-weight: 800; color: #fff; background: var(--itd-unk); }
        .is-ok > .itd-kpi-value .itd-glyph { background: var(--itd-ok); } .is-warn > .itd-kpi-value .itd-glyph { background: var(--itd-warn); }
        .is-crit > .itd-kpi-value .itd-glyph { background: var(--itd-crit); }
        .itd-kpi-caption { font-size: .875rem; font-weight: 600; color: var(--itd-ink-2); }
        .itd-kpi-sub { font-size: .8125rem; color: var(--itd-muted); overflow-wrap: anywhere; }

        /* info tooltips */
        .itd-info { position: relative; display: inline-grid; place-items: center; width: 1.05rem; height: 1.05rem; flex: none; border-radius: 50%; border: 1px solid #7d8c84; color: #3d4a43; font-size: .68rem; font-weight: 700; font-style: normal; cursor: help; text-transform: none; letter-spacing: 0; }
        .itd-info::after { content: attr(data-tip); position: absolute; z-index: 30; left: 50%; top: calc(100% + 6px); transform: translateX(-50%); width: max-content; max-width: min(260px, 70vw); padding: .5rem .65rem; border-radius: 8px; background: #0d1f18; color: #fff; font-size: .78rem; font-weight: 500; line-height: 1.4; text-align: left; white-space: normal; opacity: 0; pointer-events: none; transition: opacity .12s; }
        .itd-info:hover::after, .itd-info:focus::after { opacity: 1; }

        /* attention */
        .itd-count { min-width: 1.6rem; padding: .1rem .5rem; border-radius: 999px; text-align: center; font-size: .8125rem; font-weight: 800; background: var(--itd-ok-bg); color: var(--itd-ok-ink); }
        .itd-count.has-items { background: var(--itd-crit-bg); color: var(--itd-crit-ink); }
        .itd-allgood { display: flex; align-items: center; gap: .6rem; padding: .75rem 1rem; border-radius: 8px; background: var(--itd-ok-bg); color: var(--itd-ok-ink) !important; font-weight: 600; }
        .itd-allgood.is-neutral { background: var(--itd-unk-bg); color: var(--itd-unk-ink) !important; }
        .itd-alerts.is-clear { padding: .6rem 1rem; }
        .itd-alert-list { list-style: none; margin: 0; padding: 0; display: grid; gap: .6rem; }
        .itd-alert { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: .9rem; padding: .75rem 1rem; border-radius: 10px; border: 1px solid var(--itd-line); border-left: 5px solid var(--itd-warn); background: #fffdf5; }
        .itd-alert.is-critical { border-left-color: var(--itd-crit); background: #fff7f5; }
        .itd-sev { font-size: .72rem; font-weight: 800; letter-spacing: .06em; padding: .2rem .55rem; border-radius: 6px; background: var(--itd-warn-bg); color: var(--itd-warn-ink); }
        .is-critical .itd-sev { background: var(--itd-crit-bg); color: var(--itd-crit-ink); }
        .itd-alert-body strong { display: block; font-size: .9375rem; color: var(--itd-ink); }
        .itd-alert-body p { font-size: .875rem; }
        @media (max-width: 640px) { .itd-alert { grid-template-columns: minmax(0, 1fr); } .itd-alert .itd-btn { justify-self: start; } }

        /* tables */
        .itd-table-wrap { overflow-x: auto; }
        .itd-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .itd-table th { padding: .5rem .6rem; text-align: left; font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; color: var(--itd-ink-2); border-bottom: 2px solid var(--itd-line); white-space: nowrap; }
        .itd-table td { padding: .6rem; border-bottom: 1px solid var(--itd-line); vertical-align: middle; color: var(--itd-ink); }
        .itd-table tr:last-child td { border-bottom: 0; }
        .itd-nowrap { white-space: nowrap; }
        .itd-badge { display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .6rem; border-radius: 999px; font-size: .78rem; font-weight: 700; white-space: nowrap; }
        @media (max-width: 767.98px) {
            .itd-table-cards thead { display: none; }
            .itd-table-cards, .itd-table-cards tbody, .itd-table-cards tr, .itd-table-cards td { display: block; width: 100%; }
            .itd-table-cards tr { padding: .5rem 0; border-bottom: 1px solid var(--itd-line); }
            .itd-table-cards td { border: 0; padding: .15rem 0; }
            .itd-table-cards td::before { content: attr(data-label) ": "; font-weight: 600; color: var(--itd-muted); font-size: .8125rem; }
        }

        /* distribution */
        .itd-dist { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 1.25rem; }
        .itd-donut { position: relative; width: 190px; height: 190px; flex: none; }
        .itd-donut canvas { width: 100% !important; height: 100% !important; }
        .itd-donut-center { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none; }
        .itd-donut-center strong { font-size: 1.75rem; line-height: 1; font-weight: 800; }
        .itd-donut-center span { margin-top: .2rem; font-size: .68rem; letter-spacing: .08em; font-weight: 700; color: var(--itd-muted); }
        .itd-legend { list-style: none; margin: 0; padding: 0; display: grid; gap: .55rem; min-width: 190px; }
        .itd-legend li { display: grid; grid-template-columns: auto minmax(0, 1fr) auto auto; align-items: center; gap: .5rem; }
        .itd-swatch { width: 12px; height: 12px; border-radius: 3px; }
        .itd-legend strong { font-size: 1rem; }

        /* activity chart */
        .itd-range { display: inline-flex; border: 1px solid var(--itd-line); border-radius: 8px; overflow: hidden; }
        .itd-range button { padding: .35rem .8rem; border: 0; background: #fff; color: var(--itd-ink-2); font-size: .8125rem; font-weight: 600; cursor: pointer; }
        .itd-range button + button { border-left: 1px solid var(--itd-line); }
        .itd-range button[aria-pressed="true"] { background: #1f5a46; color: #fff; }
        .itd-chart { position: relative; height: 280px; }
        .itd-chart-state { position: absolute; inset: 0; display: grid; place-items: center; text-align: center; padding: 1rem; color: var(--itd-muted); font-weight: 600; }
        .itd-chart-state[hidden] { display: none; }

        /* data status + farmers */
        .itd-data { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
        @media (max-width: 575.98px) { .itd-data { grid-template-columns: minmax(0, 1fr); } }
        .itd-data-item { padding: .75rem; border: 1px solid var(--itd-line); border-radius: 10px; background: #fafcfb; min-width: 0; }
        .itd-data-item h3 { font-size: .875rem; margin-bottom: .4rem; }
        .itd-data-item dl { margin: 0; display: grid; gap: .2rem; }
        .itd-data-item dl > div { display: flex; justify-content: space-between; gap: .5rem; font-size: .8125rem; }
        .itd-data-item dt { color: var(--itd-muted); font-weight: 500; } .itd-data-item dd { margin: 0; font-weight: 600; text-align: right; overflow-wrap: anywhere; }
        .itd-rows { margin: 0; display: grid; }
        .itd-rows > div { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: .5rem 0; border-bottom: 1px solid var(--itd-line); font-size: .875rem; }
        .itd-rows > div:last-child { border-bottom: 0; }
        .itd-rows dt { color: var(--itd-ink-2); font-weight: 500; display: flex; align-items: center; gap: .4rem; } .itd-rows dd { margin: 0; font-weight: 700; text-align: right; overflow-wrap: anywhere; }
        .itd-bad { color: var(--itd-crit-ink); }
        .itd-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: .75rem; }
        .itd-stats > div { padding: .75rem; border-radius: 10px; background: #f3f8f4; border: 1px solid #d9eadf; display: flex; flex-direction: column; gap: .15rem; }
        .itd-stat-label { font-size: .78rem; font-weight: 600; color: var(--itd-ink-2); text-transform: uppercase; letter-spacing: .04em; }
        .itd-stats strong { font-size: 1.625rem; line-height: 1.15; font-weight: 800; } .itd-stats small { font-size: .85rem; }

        .itd-data-value { display: block; font-size: 1.375rem; font-weight: 800; line-height: 1.2; margin-bottom: .1rem; }
        .itd-quick { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: .75rem; }
        .itd-quick a { display: flex; align-items: center; gap: .75rem; padding: .75rem 1rem; border: 1px solid var(--itd-line); border-radius: 10px; background: #fafcfb; color: var(--itd-ink); font-weight: 600; text-decoration: none; }
        .itd-quick a:hover { border-color: #b9dcc6; background: #eef7f1; }
        .itd-quick svg { flex: none; width: 1.4rem; height: 1.4rem; color: #1f5a46; }

        .itd.is-loading [data-slot] { opacity: .55; transition: opacity .15s; }
        @media (max-width: 575.98px) { .itd { padding: 1rem; } .itd-header h1 { font-size: 1.5rem; } .itd-stats { grid-template-columns: minmax(0, 1fr); } }
        @media (prefers-reduced-motion: reduce) { .itd * { transition: none !important; } }
    </style>

    <div class="itd" data-it-dashboard data-live-url="{{ route('admin.dashboard.live') }}" data-activity-url="{{ route('admin.dashboard.activity') }}">
        <header class="itd-header">
            <div>
                <h1>IT Dashboard</h1>
                <p>Monitor users, system health, data, and system activity.</p>
            </div>
            <div class="itd-tools">
                <span class="itd-pill is-{{ $overall['level'] }}" data-it-status><span aria-hidden="true">{{ $glyph[$overall['level']] }}</span> System status: {{ $overall['label'] }}</span>
                <span class="itd-refreshed" role="status" aria-live="polite">Last refreshed: <span data-it-refreshed>{{ $it['checked_at']->format('g:i A') }}</span></span>
                <button type="button" class="itd-btn" data-it-refresh>Refresh</button>
            </div>
        </header>

        <div class="itd-row"><div class="itd-cell" data-slot="kpis">@include('dashboards.it.kpis')</div></div>
        <div class="itd-row"><div class="itd-cell" data-slot="alerts">@include('dashboards.it.alerts')</div></div>

        <div class="itd-row r-55-45">
            <div class="itd-cell" data-slot="health">@include('dashboards.it.health')</div>
            <div class="itd-cell" data-slot="distribution">@include('dashboards.it.distribution')</div>
        </div>

        <div class="itd-row r-60-40">
            <div class="itd-cell">
                <section class="itd-card" aria-labelledby="itdActivityTitle" data-it-activity>
                    <div class="itd-card-head">
                        <h2 id="itdActivityTitle">System Activity <span class="itd-muted" data-activity-range-label>— Last 30 Days</span></h2>
                        <div class="itd-range" role="group" aria-label="Activity period">
                            <button type="button" data-range="7" aria-pressed="false">7 Days</button>
                            <button type="button" data-range="30" aria-pressed="true">30 Days</button>
                            <button type="button" data-range="90" aria-pressed="false">90 Days</button>
                        </div>
                    </div>
                    <div class="itd-chart">
                        <canvas id="itSystemActivityTrendChart" data-chart-unit="events" aria-label="System activity over time"></canvas>
                        <div class="itd-chart-state" data-activity-state role="status">Loading system activity...</div>
                    </div>
                </section>
            </div>
            <div class="itd-cell" data-slot="data" @if (count($it['data']) === 0) hidden @endif>@include('dashboards.it.data')</div>
        </div>

        <div class="itd-row"><div class="itd-cell" data-slot="farmers">@include('dashboards.it.farmers')</div></div>
        <div class="itd-row"><div class="itd-cell" data-slot="recent">@include('dashboards.it.recent')</div></div>

        <div class="itd-row">
            <div class="itd-cell">
                <section class="itd-card" aria-label="Quick actions">
                    <div class="itd-card-head"><h2>Quick Actions</h2></div>
                    <div class="itd-quick">
                        <a href="{{ route('users.index') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><path d="M16 5.2a3 3 0 0 1 0 5.6M18.5 14.8c1.6.7 2.5 2.3 2.5 4.2"/></svg>Manage Users</a>
                        <a href="{{ route('system-logs.index') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>View System Logs</a>
                        <a href="{{ route('system-health.index') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12h4l2.5-6 5 12 2.5-6H21"/></svg>System Health</a>
                        <a href="{{ route('reports.index') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3h7l4 4v14H7z"/><path d="M14 3v4h4M10 12h5M10 16h5"/></svg>Generate Report</a>
                    </div>
                </section>
            </div>
        </div>
        @if (! empty($it['reconciliation']))
            <p class="itd-foot" role="note">{{ implode(' ', $it['reconciliation']) }}</p>
        @endif
    </div>

    <script>
        (() => {
            const root = document.querySelector('[data-it-dashboard]');
            if (!root) return;
            const $ = (s) => root.querySelector(s);
            let donut = null; let activity = null; let range = 30;

            const ready = async () => { try { await window.iClimateVisualsReady; } catch (e) { /* handled by callers */ } return !!window.Chart; };

            async function initDonut() {
                const canvas = $('canvas[data-donut]');
                if (!canvas || !(await ready())) return;
                if (donut) { donut.destroy(); donut = null; }
                const cfg = JSON.parse(canvas.dataset.donut);
                donut = new window.Chart(canvas, {
                    type: 'doughnut',
                    data: { labels: cfg.labels, datasets: [{ data: cfg.counts, backgroundColor: cfg.colors, borderColor: '#fff', borderWidth: 2 }] },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { display: false } } },
                });
            }

            const longDate = (iso) => new Date(iso + 'T00:00:00').toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });

            async function loadActivity() {
                const state = $('[data-activity-state]');
                state.hidden = false; state.textContent = 'Loading system activity...';
                $('[data-activity-range-label]').textContent = '— Last ' + range + ' Days';
                root.querySelectorAll('.itd-range button').forEach((b) => b.setAttribute('aria-pressed', String(Number(b.dataset.range) === range)));
                try {
                    const res = await fetch(root.dataset.activityUrl + '?range=' + range, { headers: { Accept: 'application/json' } });
                    if (!res.ok) throw new Error('bad status');
                    const data = await res.json();
                    if (data.state === 'error') { state.textContent = data.message; return; }
                    if (data.state === 'empty') { if (activity) { activity.destroy(); activity = null; } state.textContent = 'No system activity was recorded in this period.'; return; }
                    if (!(await ready())) { state.textContent = 'The chart library could not be loaded.'; return; }
                    state.hidden = true;
                    const datasets = data.datasets.map((d) => ({ label: d.label, data: d.data, borderColor: d.color, backgroundColor: d.color, borderWidth: 2, tension: .3, pointRadius: data.days <= 30 ? 3 : 0, pointHoverRadius: 6 }));
                    if (activity) { activity.data.labels = data.labels; activity.data.datasets = datasets; activity.$dates = data.dates; activity.update(); return; }
                    activity = new window.Chart($('#itSystemActivityTrendChart'), {
                        type: 'line', data: { labels: data.labels, datasets },
                        options: {
                            responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                            scales: { y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Events' }, grid: { color: '#eef2ef' } }, x: { grid: { display: false }, ticks: { autoSkip: true, maxRotation: 0 } } },
                            plugins: {
                                legend: { position: 'bottom', labels: { boxWidth: 12 } },
                                tooltip: { callbacks: {
                                    title: (items) => activity.$dates ? longDate(activity.$dates[items[0].dataIndex]) : items[0].label,
                                    label: (c) => c.dataset.label + ': ' + c.parsed.y,
                                } },
                            },
                        },
                    });
                    activity.$dates = data.dates;
                } catch (e) {
                    state.hidden = false; state.textContent = 'Unable to retrieve system activity.';
                }
            }

            root.querySelectorAll('.itd-range button').forEach((b) => b.addEventListener('click', () => { range = Number(b.dataset.range); loadActivity(); }));

            const glyphs = { ok: '✓', warn: '!', crit: '✕' };
            async function refresh() {
                const btn = $('[data-it-refresh]');
                btn.disabled = true; btn.textContent = 'Refreshing...'; root.classList.add('is-loading');
                try {
                    const res = await fetch(root.dataset.liveUrl, { headers: { Accept: 'application/json' } });
                    if (!res.ok) throw new Error('bad status');
                    const data = await res.json();
                    if (donut) { donut.destroy(); donut = null; }
                    Object.entries(data.slots).forEach(([key, html]) => { const el = root.querySelector('[data-slot="' + key + '"]'); if (el) { el.innerHTML = html; el.hidden = html.trim() === ''; } });
                    $('[data-it-refreshed]').textContent = data.refreshed_at;
                    const pill = $('[data-it-status]');
                    pill.className = 'itd-pill is-' + data.level;
                    pill.textContent = glyphs[data.level] + ' System status: ' + data.status;
                    initDonut(); loadActivity();
                } catch (e) {
                    $('[data-it-refreshed]').textContent = 'failed. Showing the last loaded data.';
                } finally {
                    btn.disabled = false; btn.textContent = 'Refresh'; root.classList.remove('is-loading');
                }
            }
            $('[data-it-refresh]').addEventListener('click', refresh);
            // Health changes slowly, so refresh it every 5 minutes, and only while the tab is visible.
            setInterval(() => { if (document.visibilityState === 'visible') refresh(); }, 300000);

            const start = () => { initDonut(); loadActivity(); };
            document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();
        })();
    </script>
</x-app-layout>
