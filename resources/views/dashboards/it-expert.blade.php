<x-app-layout>
    @php
        $overall = $it['overall'];
        // Status level -> the shared status pill (colour, icon). The Refresh script swaps the header pill with the same map.
        $icon = fn (string $name): string => view('dashboards.mao.icon', ['name' => $name])->render();
        // "Operational" (warn) is still a working system, so it keeps the green pill; its rows on the System Health card say what to review.
        $pills = ['ok' => ['good', $icon('check')], 'warn' => ['good', $icon('check')], 'crit' => ['watch', $icon('alert')], 'unk' => ['unk', $icon('info')]];
        $pill = $pills[$overall['level']] ?? $pills['unk'];
        $tabs = ['overview' => 'Overview', 'heatmap' => 'Heat Map', 'security' => 'Security', 'users' => 'Users & Usage', 'resources' => 'Resources & Data', 'activity' => 'Activity Log'];
    @endphp

    {{-- Same design system as the farmer dashboard: the wrapper is .mfd, the shared parts are .mfd-* (farmer-dashboard.css)
         and only the IT-only parts (tabs, tables, filters, heatmap) are .itd-* in it-dashboard.css. --}}
    @if (! empty($activityInitial))
        <script type="application/json" id="itdActivityInitial">@json($activityInitial)</script>
    @endif
    <div class="mfd itd" data-it-dashboard data-live-url="{{ route('admin.dashboard.live') }}" data-activity-url="{{ route('admin.dashboard.activity') }}" data-recent-url="{{ route('admin.dashboard.recent') }}" data-logs-url="{{ route('system-logs.index') }}">
        {{-- Header --}}
        <header class="mfd-header">
            <div class="mfd-header-main">
                <span class="mfd-kicker">iClimate System Administration</span>
                <h1>IT Personnel Dashboard</h1>
                <p class="itd-lede">Users, system health and recent activity at a glance</p>
            </div>
            <div class="mfd-fresh">
                <span class="mfd-pill is-{{ $pill[0] }}" data-it-status>{!! $pill[1] !!}<span data-it-status-text>{{ $overall['label'] }}</span></span>
                <span class="mfd-age" role="status" aria-live="polite">Updated at <span data-it-refreshed>{{ $it['checked_at']->format('g:i:s A') }}</span></span>
                <button type="button" class="mfd-btn is-quiet" data-it-refresh aria-label="Refresh the dashboard">@include('dashboards.mao.icon', ['name' => 'refresh'])<span data-it-refresh-text>Refresh</span></button>
            </div>
        </header>

        @if (session('status'))<div class="mfd-note is-good" role="status">@include('dashboards.mao.icon', ['name' => 'check'])<p>{{ session('status') }}</p></div>@endif

        {{-- The five summary cards (always visible) --}}
        <div class="itd-row"><div class="itd-cell mfd-slot" id="itd-kpis" data-slot="kpis">@include('dashboards.it.kpis')</div></div>

        {{-- Tabs --}}
        <div class="itd-tabs" role="tablist" aria-label="Dashboard sections">
            @foreach ($tabs as $key => $label)
                <button type="button" role="tab" class="itd-tab" id="itd-tab-{{ $key }}" data-tab="{{ $key }}" aria-controls="itd-panel-{{ $key }}" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" tabindex="{{ $tab === $key ? '0' : '-1' }}">{{ $label }}</button>
            @endforeach
        </div>

        {{-- Overview --}}
        <div role="tabpanel" id="itd-panel-overview" data-panel="overview" aria-labelledby="itd-tab-overview" @if ($tab !== 'overview') hidden @endif>
            <div class="itd-row"><div class="itd-cell mfd-slot" id="itd-health" data-slot="health">@include('dashboards.it.health')</div></div>
            <div class="itd-row r-60-40">
                <div class="itd-cell" id="itd-activity">
                    <section class="mfd-card itd-card" aria-labelledby="itdActivityTitle" data-it-activity>
                        <div class="mfd-card-head">
                            <div>
                                <h2 id="itdActivityTitle">System Activity</h2>
                                <p class="itd-sub">What happened in the system each day. Click a day to open its logs.</p>
                                <span class="itd-updated"><span data-activity-range-label>Last 30 days</span> · Last updated <span data-activity-updated>…</span></span>
                            </div>
                            <div class="itd-range" role="group" aria-label="Activity period">
                                <button type="button" data-range="7" aria-pressed="false">7 days</button>
                                <button type="button" data-range="30" aria-pressed="true">30 days</button>
                                <button type="button" data-range="90" aria-pressed="false">90 days</button>
                            </div>
                        </div>
                        <figure class="mfd-figure">
                            <div class="mfd-chart itd-chart is-loading" data-activity-chart>
                                <canvas id="itSystemActivityTrendChart" data-chart-unit="events" aria-label="System activity per day. Click a day to open its logs."></canvas>
                                <div class="itd-chart-state" data-activity-state role="status">Loading system activity...</div>
                            </div>
                        </figure>
                    </section>
                </div>
                <div class="itd-cell mfd-slot" id="itd-brief" data-slot="brief">@include('dashboards.it.brief')</div>
            </div>
        </div>

        {{-- Heat Map --}}
        <div role="tabpanel" id="itd-panel-heatmap" data-panel="heatmap" aria-labelledby="itd-tab-heatmap" @if ($tab !== 'heatmap') hidden @endif>
            <div class="itd-row">
                <div class="itd-cell">
                    <section class="itd-card" aria-labelledby="itdHeatMapTitle">
                        <div class="itd-card-head">
                            <div>
                                <h2 id="itdHeatMapTitle">Lian Municipality Heat Map</h2>
                                <p class="itd-sub">All 19 barangays at a glance, using the same live weather and recorded rice yield data used municipality-wide. Click any barangay for details.</p>
                            </div>
                        </div>
                        @include('partials.barangay-risk-map', [
                            'heading' => 'Lian Municipality Heat Map',
                            'subheading' => 'All 19 barangays at a glance, using the same live weather and recorded rice yield data used municipality-wide. Click any barangay for details.',
                            'showRefresh' => true,
                        ])
                    </section>
                </div>
            </div>
        </div>

        {{-- Security --}}
        <div role="tabpanel" id="itd-panel-security" data-panel="security" aria-labelledby="itd-tab-security" @if ($tab !== 'security') hidden @endif>
            <div class="itd-row"><div class="itd-cell mfd-slot" id="itd-security" data-slot="security">@include('dashboards.it.security')</div></div>
        </div>

        {{-- Users & Usage --}}
        <div role="tabpanel" id="itd-panel-users" data-panel="users" aria-labelledby="itd-tab-users" @if ($tab !== 'users') hidden @endif>
            <div class="itd-row r-50-50 is-wide">
                <div class="itd-cell mfd-slot" id="itd-roles" data-slot="roles">@include('dashboards.it.roles')</div>
                <div class="itd-cell mfd-slot" id="itd-ai" data-slot="ai">@include('dashboards.it.ai')</div>
            </div>
            <div class="itd-row"><div class="itd-cell mfd-slot" id="itd-usage" data-slot="usage">@include('dashboards.it.usage')</div></div>
        </div>

        {{-- Resources & Data --}}
        <div role="tabpanel" id="itd-panel-resources" data-panel="resources" aria-labelledby="itd-tab-resources" @if ($tab !== 'resources') hidden @endif>
            <div class="itd-row r-50-50">
                <div class="itd-cell mfd-slot" id="itd-resources" data-slot="resources">@include('dashboards.it.resources')</div>
                <div class="itd-cell mfd-slot" id="itd-integrity" data-slot="integrity">@include('dashboards.it.integrity')</div>
            </div>
            <div class="itd-row"><div class="itd-cell mfd-slot" id="itd-pipeline" data-slot="pipeline">@include('dashboards.it.pipeline')</div></div>
            <div class="itd-row"><div class="itd-cell mfd-slot" id="itd-ops" data-slot="ops">@include('dashboards.it.ops')</div></div>
            <div class="itd-row"><div class="itd-cell mfd-slot" id="itd-jobs" data-slot="jobs">@include('dashboards.it.jobs')</div></div>
        </div>

        {{-- Activity Log --}}
        <div role="tabpanel" id="itd-panel-activity" data-panel="activity" aria-labelledby="itd-tab-activity" @if ($tab !== 'activity') hidden @endif>
            <div class="itd-row"><div class="itd-cell mfd-slot" id="itd-recent" data-slot="recent">@include('dashboards.it.recent')</div></div>
        </div>
    </div>

    <script>
        (() => {
            const root = document.querySelector('[data-it-dashboard]');
            if (!root) return;
            const $ = (s) => root.querySelector(s);
            let activity = null; let range = 30; let activityLoaded = false;

            const ready = async () => { try { await window.iClimateVisualsReady; } catch (e) { /* handled by callers */ } return !!window.Chart; };
            const longDate = (iso) => new Date(iso + 'T00:00:00').toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
            const clock = () => new Date().toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit', second: '2-digit', timeZone: 'Asia/Manila' });

            // ---- tabs (the selected tab lives in the URL as ?tab=...)
            const tabs = [...root.querySelectorAll('[role="tab"]')];
            function showTab(name, replaceUrl = true) {
                if (!root.querySelector('[data-panel="' + name + '"]')) name = 'overview';
                tabs.forEach((t) => { const on = t.dataset.tab === name; t.setAttribute('aria-selected', String(on)); t.tabIndex = on ? 0 : -1; });
                root.querySelectorAll('[data-panel]').forEach((p) => { p.hidden = p.dataset.panel !== name; });
                if (replaceUrl) { const url = new URL(window.location.href); url.searchParams.set('tab', name); history.replaceState(null, '', url); }
                if (name === 'overview') loadActivity();
            }
            tabs.forEach((t, i) => {
                t.addEventListener('click', () => showTab(t.dataset.tab));
                t.addEventListener('keydown', (e) => {
                    const next = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: tabs.length - 1 }[e.key];
                    if (next === undefined) return;
                    e.preventDefault(); const target = tabs[(next + tabs.length) % tabs.length]; target.focus(); showTab(target.dataset.tab);
                });
            });
            function goTo(tab, anchor) {
                showTab(tab);
                const el = anchor ? document.getElementById(anchor) : null;
                if (!el) return;
                el.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
                el.classList.add('is-targeted'); setTimeout(() => el.classList.remove('is-targeted'), 2500);
            }
            // Chips and KPI cards that point at a section open its tab and scroll to it without a reload.
            root.addEventListener('click', (e) => {
                const link = e.target.closest('[data-tab-link]');
                if (!link || e.metaKey || e.ctrlKey) return;
                e.preventDefault(); goTo(link.dataset.tabLink, link.dataset.target);
                history.replaceState(null, '', link.getAttribute('href'));
            });
            // Retry and other confirm-first forms.
            root.addEventListener('submit', (e) => { const msg = e.target.dataset && e.target.dataset.confirm; if (msg && !window.confirm(msg)) e.preventDefault(); });

            // ---- System Activity chart
            function takeInitialActivity() {
                const node = document.getElementById('itdActivityInitial');
                if (!node) return null;
                node.remove();
                try { return JSON.parse(node.textContent) || null; } catch (e) { return null; }
            }

            async function loadActivity() {
                if (activityLoaded && activity) return;
                const state = $('[data-activity-state]'); const box = $('[data-activity-chart]');
                state.hidden = false; box.classList.add('is-loading'); state.textContent = 'Loading system activity...';
                $('[data-activity-range-label]').textContent = 'Last ' + range + ' days';
                root.querySelectorAll('.itd-range button').forEach((b) => b.setAttribute('aria-pressed', String(Number(b.dataset.range) === range)));
                const message = (text) => { box.classList.remove('is-loading'); state.textContent = text; };
                try {
                    // The default 30 days arrive with the page (#itdActivityInitial); other ranges are fetched when chosen.
                    let data = range === 30 ? takeInitialActivity() : null;
                    if (!data) {
                        const res = await fetch(root.dataset.activityUrl + '?range=' + range, { headers: { Accept: 'application/json' } });
                        if (!res.ok) throw new Error('bad status');
                        data = await res.json();
                    }
                    $('[data-activity-updated]').textContent = clock();
                    if (data.state === 'error') { message(data.message); return; }
                    if (data.state === 'empty') { if (activity) { activity.destroy(); activity = null; } message('Activity for this period will show here as people sign in and use the system.'); return; }
                    if (!(await ready())) { message('The chart library could not be loaded.'); return; }
                    state.hidden = true; box.classList.remove('is-loading');
                    const datasets = data.datasets.map((d) => ({ label: d.label, data: d.data, borderColor: d.color, backgroundColor: d.color, borderWidth: 2, tension: .3, pointRadius: data.days <= 30 ? 3 : 1, pointHoverRadius: 6 }));
                    if (activity) { activity.data.labels = data.labels; activity.data.datasets = datasets; activity.$dates = data.dates; activity.update(); activityLoaded = true; return; }
                    activity = new window.Chart($('#itSystemActivityTrendChart'), {
                        type: 'line', data: { labels: data.labels, datasets },
                        options: {
                            responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                            onClick: (evt, _els, chart) => {
                                const hit = chart.getElementsAtEventForMode(evt, 'index', { intersect: false }, true)[0];
                                const day = hit && chart.$dates ? chart.$dates[hit.index] : null;
                                if (day) window.location.href = root.dataset.logsUrl + '?date=' + encodeURIComponent(day);
                            },
                            scales: {
                                y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Events per day' }, grid: { color: '#e3e8e5' } },
                                x: { grid: { display: false }, ticks: { autoSkip: true, maxRotation: 0 }, title: { display: true, text: 'Date' } },
                            },
                            plugins: {
                                legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } },
                                tooltip: { callbacks: {
                                    title: (items) => activity.$dates ? longDate(activity.$dates[items[0].dataIndex]) : items[0].label,
                                    label: (c) => c.dataset.label + ': ' + c.parsed.y,
                                    footer: () => 'Click to open this day\'s logs',
                                } },
                            },
                        },
                    });
                    activity.$dates = data.dates; activityLoaded = true;
                } catch (e) {
                    message('Unable to retrieve system activity.');
                }
            }
            root.querySelectorAll('.itd-range button').forEach((b) => b.addEventListener('click', () => { range = Number(b.dataset.range); activityLoaded = false; loadActivity(); }));

            // ---- Recent activity filters
            async function loadRecent() {
                const form = $('[data-recent-filters]'); const body = $('[data-recent-body]');
                if (!form || !body) return;
                body.style.opacity = '.5';
                try {
                    const res = await fetch(root.dataset.recentUrl + '?' + new URLSearchParams(new FormData(form)), { headers: { Accept: 'application/json' } });
                    if (!res.ok) throw new Error('bad status');
                    body.innerHTML = (await res.json()).html;
                } catch (e) {
                    body.innerHTML = '<div class="mfd-empty is-error" role="alert"><p>Unable to retrieve recent activity.</p></div>';
                } finally { body.style.opacity = ''; }
            }
            root.addEventListener('change', (e) => { if (e.target.closest('[data-recent-filters]')) loadRecent(); });

            // ---- Refresh: each section dims and shimmers (the shared .mfd-slot skeleton) while its new HTML is fetched
            const pills = @json($pills);
            // force: the Refresh button (?refresh=1 rebuilds every section); the 5-minute timer reads the prepared overview.
            async function refresh(force = false) {
                const btn = $('[data-it-refresh]'); const label = $('[data-it-refresh-text]');
                const slots = [...root.querySelectorAll('[data-slot]')];
                btn.disabled = true; label.textContent = 'Refreshing...'; slots.forEach((s) => s.classList.add('is-loading'));
                try {
                    const res = await fetch(root.dataset.liveUrl + (force === true ? '?refresh=1' : ''), { headers: { Accept: 'application/json' } });
                    if (!res.ok) throw new Error('bad status');
                    const data = await res.json();
                    const filters = $('[data-recent-filters]');
                    const filtering = filters ? [...new FormData(filters).values()].some((v) => v !== '') : false;
                    Object.entries(data.slots).forEach(([key, html]) => {
                        if (key === 'recent' && filtering) return;     // keep the table the person is filtering
                        const el = root.querySelector('[data-slot="' + key + '"]'); if (el) el.innerHTML = html;
                    });
                    $('[data-it-refreshed]').textContent = data.refreshed_at;
                    const pill = pills[data.level] || pills.unk; const status = $('[data-it-status]');
                    status.className = 'mfd-pill is-' + pill[0];
                    status.innerHTML = pill[1] + '<span data-it-status-text></span>';
                    status.lastElementChild.textContent = data.status;
                    activityLoaded = false; if (!$('[data-panel="overview"]').hidden) loadActivity();
                } catch (e) {
                    $('[data-it-refreshed]').textContent = 'failed. Showing the last loaded data.';
                } finally {
                    btn.disabled = false; label.textContent = 'Refresh'; slots.forEach((s) => s.classList.remove('is-loading'));
                }
            }
            $('[data-it-refresh]').addEventListener('click', () => refresh(true));
            // Health changes slowly: refresh every 5 minutes, and only while the tab is visible.
            setInterval(() => { if (document.visibilityState === 'visible') refresh(); }, 300000);

            const start = () => {
                const initial = new URL(window.location.href).searchParams.get('tab') || 'overview';
                showTab(initial, false);
                if (window.location.hash) setTimeout(() => goTo(initial, window.location.hash.slice(1)), 150);
            };
            document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();
        })();
    </script>
</x-app-layout>
