<x-app-layout>
    @php
        $yield = $mao['yield'];
        $yieldPoints = ($yield['state'] ?? null) === 'ok' ? $yield['points'] : [];
        $showYield = count($yieldPoints) >= 2;                                   // a trend needs at least two years
        $partialYears = collect($yieldPoints)->filter(fn ($p) => $p['months'] < 12)->map(fn ($p) => $p['year'].' ('.$p['months'].' months)')->implode(', ');
        $climate = $mao['climate'];
    @endphp


    {{-- A short municipal monitoring page: summary, weather, the barangay heat map, rice and climate trends, planting
         and farmers. Same design system as the farmer dashboard (resources/css/mao-dashboard.css follows
         farmer-dashboard.css). Information opens in the shared pop-up (resources/js/info-modal.js); office tools such as
         the activity log and reports stay on their own pages. --}}
    <div class="mtd" data-mao-dashboard data-live-url="{{ route('mao.dashboard.live') }}">
        <header class="mtd-header">
            <div class="mtd-header-main">
                <span class="mtd-kicker">Municipal Agriculture Office</span>
                <h1>MAO Dashboard</h1>
                <p class="mtd-place">@include('dashboards.mao.icon', ['name' => 'map'])Municipal Agriculture Monitoring — Lian, Batangas</p>
                <p class="mtd-lead">Today's weather, farm risk, rice yield and planting guidance for Lian's rice farmers.</p>
            </div>
            <div class="mtd-tools">
                <span class="mtd-refreshed" role="status" aria-live="polite" data-mao-status>Last updated: <span data-mao-refreshed>{{ $mao['generated_at']->format('g:i A') }}</span><span data-mao-result></span></span>
                <button type="button" class="mtd-btn is-ghost" data-mao-refresh>@include('dashboards.mao.icon', ['name' => 'refresh'])<span data-mao-refresh-text>Refresh Data</span></button>
                <a class="mtd-btn" href="{{ route('planting-advisories.create') }}">@include('dashboards.mao.icon', ['name' => 'plus'])Create Advisory</a>
            </div>
        </header>

        <section class="mtd-section" aria-labelledby="mtdSummaryTitle">
            <h2 class="mtd-section-title" id="mtdSummaryTitle">Municipal Summary</h2>
            <div class="mtd-row"><div class="mtd-cell" data-slot="kpis">@include('dashboards.mao.kpis')</div></div>
            <div class="mtd-row"><div class="mtd-cell" data-slot="attention">@include('dashboards.mao.attention')</div></div>
        </section>

        <section class="mtd-section" aria-labelledby="mtdTodayTitle">
            <h2 class="mtd-section-title" id="mtdTodayTitle">Weather in Lian</h2>
            <div class="mtd-row"><div class="mtd-cell" data-slot="conditions">@include('dashboards.mao.conditions')</div></div>
            <div class="mtd-row"><div class="mtd-cell" data-slot="forecast" @if (($mao['weather']['state'] ?? null) !== 'ok' || count($mao['weather']['days'] ?? []) === 0) hidden @endif>@include('dashboards.mao.forecast')</div></div>
        </section>

        <section class="mtd-section" aria-labelledby="mtdRiskSectionTitle">
            <h2 class="mtd-section-title" id="mtdRiskSectionTitle">Barangay Heat Map</h2>
            <div class="mtd-row"><div class="mtd-cell">@include('dashboards.mao.risk')</div></div>
            <div class="mtd-row"><div class="mtd-cell" data-slot="watch" @if (($mao['watch']['state'] ?? 'hidden') === 'hidden') hidden @endif>@include('dashboards.mao.watch')</div></div>
        </section>

        <section class="mtd-section" aria-labelledby="mtdTrendsTitle">
            <h2 class="mtd-section-title" id="mtdTrendsTitle">Rice Yield &amp; Climate</h2>
            <div class="mtd-row r-50-50">
                <div class="mtd-cell">
                    @if (! $showYield)
                        <section class="mtd-card" aria-labelledby="mtdYieldTitle" data-mao-yield-empty>
                            <div class="mtd-card-head"><h2 id="mtdYieldTitle">Rice Yield by Year</h2></div>
                            <div class="mtd-empty" role="status">
                                @if (($yield['state'] ?? null) === 'error')<strong>Rice yield history is temporarily unavailable.</strong><span>Please try again shortly.</span>
                                @else<strong>No data available.</strong><span>A yield trend needs recorded yields for at least two years.</span>@endif
                            </div>
                        </section>
                    @else
                        <section class="mtd-card" aria-labelledby="mtdYieldTitle" data-mao-yield>
                            <div class="mtd-card-head">
                                <div>
                                    <h2 id="mtdYieldTitle">Rice Yield by Year</h2>
                                    <p class="mtd-sub">Tons per hectare, from past records.</p>
                                </div>
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
                            @if ($partialYears)<p class="mtd-foot">Hollow points are years with incomplete records. Years with fewer than 12 monthly records: {{ $partialYears }}.</p>@endif
                        </section>
                    @endif
                </div>
                <div class="mtd-cell">
                    @unless ($climate)
                        <section class="mtd-card" aria-labelledby="mtdClimateTitle" data-mao-climate-empty>
                            <div class="mtd-card-head"><h2 id="mtdClimateTitle">Rainfall &amp; Temperature</h2></div>
                            <div class="mtd-empty" role="status"><strong>No data available.</strong><span>No monthly rainfall or temperature records could be loaded.</span></div>
                        </section>
                    @else
                        <section class="mtd-card" aria-labelledby="mtdClimateTitle" data-mao-climate>
                            <div class="mtd-card-head"><div>
                                    <h2 id="mtdClimateTitle">Rainfall &amp; Temperature — {{ $climate['year'] }}</h2>
                                    <p class="mtd-sub">Bars show rainfall each month; the line shows temperature.</p>
                                </div></div>
                            <div class="mtd-chart">
                                <canvas id="maoRainTempChart" data-climate="{{ json_encode(['labels' => $climate['chart']['labels'], 'datasets' => $climate['chart']['datasets']]) }}"
                                    data-chart-source="Ambulong monthly climate records (recorded); iClimate monthly model (forecast, lighter)" data-chart-type="mixed"
                                    data-chart-period="{{ $climate['year'] }}, by month" data-chart-info-style="icon" aria-label="Monthly rainfall and temperature"></canvas>
                            </div>
                            <p class="mtd-foot">
                                Lighter bars and dashed lines are forecasts, not records.
                                @if ($climate['annual_rainfall'] !== null)
                                    Rainfall in {{ $climate['year'] }}: <strong>{{ number_format($climate['annual_rainfall'], 0) }} mm</strong>
                                    (total of {{ $climate['rainfall_months'] }} recorded {{ $climate['rainfall_months'] === 1 ? 'month' : 'months' }}@if ($climate['rainfall_months'] < 12), not a full year @endif).
                                @endif
                            </p>
                        </section>
                    @endunless
                </div>
            </div>
            <div class="mtd-row"><div class="mtd-cell" data-slot="production" @if (! $mao['production']) hidden @endif>@include('dashboards.mao.production')</div></div>
        </section>

        <section class="mtd-section" aria-labelledby="mtdPlantingSectionTitle">
            <h2 class="mtd-section-title" id="mtdPlantingSectionTitle">Planting &amp; Farmers</h2>
            <div class="mtd-row r-50-50">
                <div class="mtd-cell" data-slot="planting" @if (! $mao['planting']) hidden @endif>@include('dashboards.mao.planting')</div>
                <div class="mtd-cell" data-slot="coverage">@include('dashboards.mao.coverage')</div>
            </div>
        </section>

        <p class="mtd-more">
            <a class="mtd-link" href="{{ route('mao.operations') }}">More MAO tools: field reports, planting calendar, supplies, and reports center →</a>
            <button type="button" class="mtd-link" data-ic-template="mtdDataSources" data-ic-title="Data Sources" data-ic-icon="info" data-ic-kicker="MAO Dashboard"
                data-ic-description="Where each part of this dashboard comes from, and when it was last updated." aria-haspopup="dialog"
                data-ic-note="Last synchronized: {{ $mao['generated_at']->format('M j, Y g:i A') }}. Live, forecast, historical, recorded and calculated data are never mixed without a label.">
                @include('dashboards.mao.icon', ['name' => 'info'])Where this data comes from
            </button>
        </p>
        <template id="mtdDataSources">
            <dl class="fad-m-details">
                @foreach ($mao['sources'] as $source)
                    <div>
                        <dt><span class="fad-badge is-type">{{ $source['type'] }}</span></dt>
                        <dd>{{ $source['label'] }}<br><span class="fad-m-small">{{ $source['source'] }}@if ($source['updated']) · updated {{ $source['updated']->format('M j, g:i A') }}@endif</span></dd>
                    </div>
                @endforeach
            </dl>
        </template>
    </div>

    <script>
        (() => {
            const root = document.querySelector('[data-mao-dashboard]');
            if (!root) return;
            const $ = (s) => root.querySelector(s);
            const charts = {};
            const FONT = "Inter, Arial, system-ui, sans-serif";
            const ready = async () => {
                try { await window.iClimateVisualsReady; } catch (e) { /* handled by callers */ }
                if (window.Chart) { window.Chart.defaults.font.family = FONT; window.Chart.defaults.font.size = 13; window.Chart.defaults.color = '#46514b'; }
                return !!window.Chart;
            };

            async function initYield(range) {
                const canvas = $('#riceYieldTrendChart');
                if (!canvas || !(await ready())) return;
                try {
                    const cfg = JSON.parse(canvas.dataset.yield || '{}');
                    const rawPts = Array.isArray(cfg.points) ? cfg.points : [];
                    const pts = range === '5' ? rawPts.slice(-5) : rawPts;
                    if (!pts.length) return;
                    charts.yield?.destroy();
                    charts.yield = new window.Chart(canvas, {
                        type: 'line',
                        data: { labels: pts.map((p) => String(p.year)), datasets: [{
                            label: 'Average yield (t/ha)', data: pts.map((p) => p.yield), borderColor: '#0f5132', backgroundColor: 'rgba(15,81,50,.10)', borderWidth: 2.5, tension: .25, fill: true,
                            pointRadius: pts.map((p) => (p.months < 12 ? 5 : 4)), pointHoverRadius: 7,
                            pointBackgroundColor: pts.map((p) => (p.months < 12 ? '#ffffff' : '#0f5132')), pointBorderColor: '#0f5132', pointBorderWidth: 2,
                            segment: { borderDash: (c) => (pts[c.p0DataIndex]?.months < 12 || pts[c.p1DataIndex]?.months < 12 ? [6, 4] : undefined) },
                        }] },
                        options: {
                            responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                            scales: {
                                y: { beginAtZero: false, title: { display: true, text: 'Yield (t/ha)' }, grid: { color: '#eef2ef' } },
                                x: { grid: { display: false } },
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: { callbacks: {
                                    title: (items) => 'Year ' + items[0]?.label,
                                    label: (c) => 'Average yield: ' + (c.parsed.y != null ? c.parsed.y.toFixed(2) : 'n/a') + ' t/ha',
                                    afterLabel: (c) => { const p = pts[c.dataIndex] || {}; const rows = ['Records: ' + (p.months || 0) + ' monthly ' + (p.months === 1 ? 'record' : 'records')]; if (p.months < 12) rows.push('Partial year: average of the ' + p.months + ' months on file'); rows.push('Source: iClimate modeling dataset (historical)'); return rows; },
                                } },
                            },
                        },
                    });
                } catch (e) {
                    console.warn('Failed to initialize yield chart:', e);
                }
            }

            async function initClimate() {
                const canvas = $('#maoRainTempChart');
                if (!canvas || !(await ready())) return;
                try {
                    const cfg = JSON.parse(canvas.dataset.climate || '{}');
                    const colors = ['#1f6fb2', '#c4471f'];
                    // Rainfall is drawn as bars and temperature as a line; a forecast is lighter (bars) or dashed (line).
                    const datasets = Array.isArray(cfg.datasets) ? cfg.datasets.map((d) => {
                        const color = colors[d.series ?? 0];
                        const predicted = d.kind === 'predicted';
                        const base = { label: d.label, data: Array.isArray(d.data) ? d.data : [], yAxisID: d.axis || 'y', borderColor: color };
                        return (d.axis || 'y') === 'y'
                            ? { ...base, type: 'bar', order: 2, grouped: false, backgroundColor: predicted ? 'rgba(31,111,178,.35)' : color, borderWidth: 0, borderRadius: 3, maxBarThickness: 28 }
                            : { ...base, type: 'line', order: 1, backgroundColor: color, borderWidth: 2.5, tension: .3, spanGaps: false, borderDash: predicted ? [6, 4] : [], pointStyle: predicted ? 'rectRot' : 'circle', pointRadius: predicted ? 5 : 3.5 };
                    }).filter((d) => d.data.some((v) => v !== null)) : [];
                    charts.climate?.destroy();
                    charts.climate = new window.Chart(canvas, {
                        type: 'bar', data: { labels: cfg.labels || [], datasets },
                        options: {
                            responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                            scales: {
                                y: { position: 'left', beginAtZero: true, title: { display: true, text: 'Rainfall (mm)' }, grid: { color: '#eef2ef' } },
                                y2: { position: 'right', beginAtZero: false, title: { display: true, text: 'Temperature (°C)' }, grid: { drawOnChartArea: false } },
                                x: { grid: { display: false }, ticks: { maxRotation: 0 } },
                            },
                            plugins: {
                                legend: { position: 'bottom', labels: { boxWidth: 14 } },
                                tooltip: { callbacks: {
                                    label: (c) => c.dataset.label + ': ' + (c.parsed.y === null ? 'n/a' : c.parsed.y.toLocaleString(undefined, { maximumFractionDigits: 1 })) + (c.dataset.yAxisID === 'y2' ? ' °C' : ' mm'),
                                } },
                            },
                        },
                    });
                } catch (e) {
                    console.warn('Failed to initialize climate chart:', e);
                }
            }

            root.querySelectorAll('[data-yield-range]').forEach((b) => b.addEventListener('click', () => {
                root.querySelectorAll('[data-yield-range]').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
                initYield(b.dataset.yieldRange);
            }));

            // "Barangays Monitored" opens the Municipal Risk Overview's pop-up (same ratings as the map).
            root.addEventListener('click', (e) => {
                const card = e.target.closest('[data-mao-risk-open]');
                if (!card) return;
                document.dispatchEvent(new CustomEvent('iclimate:open-risk-category', { detail: { category: card.dataset.maoRiskOpen, trigger: card } }));
            });
            // The map counts are the source of truth: the KPI follows them whenever the map (re)loads.
            document.addEventListener('iclimate:risk-counts', (e) => {
                if (e.detail?.month) return;              // a forecast month is not today's count
                const value = root.querySelector('[data-kpi="barangays"] [data-kpi-value]');
                if (value) value.textContent = e.detail.monitored;
            });

            // Refresh Data: one loading state for the whole page; the live sections, the KPI counts, the Municipal Risk
            // Overview and the map all reload, then the time and a short result are shown.
            const status = $('[data-mao-status]');
            let refreshing = false;
            const waitForMap = () => new Promise((resolve) => {
                if (!document.getElementById('barangayRiskMap')) { resolve({ ok: true }); return; }
                const done = (e) => { clearTimeout(timer); document.removeEventListener('iclimate:risk-map-rendered', done); resolve(e?.detail || { ok: false }); };
                const timer = setTimeout(() => done(null), 30000);
                document.addEventListener('iclimate:risk-map-rendered', done);
                document.dispatchEvent(new CustomEvent('iclimate:risk-map-refresh'));
            });
            async function refresh(force, quiet) {
                if (refreshing) return;
                refreshing = true;
                const btn = $('[data-mao-refresh]');
                const label = $('[data-mao-refresh-text]');
                btn.disabled = true; label.textContent = 'Refreshing…'; root.classList.add('is-loading');
                const token = quiet ? null : window.showGlobalLoader?.('Refreshing dashboard data…');
                let ok = true;
                try {
                    const [res, map] = await Promise.all([
                        fetch(root.dataset.liveUrl + (force ? '?refresh=1' : ''), { headers: { Accept: 'application/json' }, loader: false }),
                        quiet ? Promise.resolve({ ok: true }) : waitForMap(),
                    ]);
                    if (!res.ok) throw new Error('bad status');
                    const data = await res.json();
                    Object.entries(data.slots).forEach(([key, html]) => {
                        const el = root.querySelector('[data-slot="' + key + '"]');
                        if (el) { el.innerHTML = html; el.hidden = html.trim() === ''; }
                    });
                    $('[data-mao-refreshed]').textContent = data.refreshed_at;
                    ok = map.ok !== false;
                    status.classList.toggle('is-error', !ok);
                    if (!quiet) $('[data-mao-result]').textContent = ok ? (map.refreshQueued ? ' · Updated. New risk ratings are being calculated in the background.' : ' · Updated.') : ' · Updated, but the risk map could not be reloaded.';
                } catch (e) {
                    ok = false;
                    status.classList.add('is-error');
                    $('[data-mao-refreshed]').textContent = 'could not refresh; showing the last loaded data';
                } finally {
                    if (token != null) window.hideGlobalLoader?.(token);
                    btn.disabled = false; label.textContent = ok ? 'Refresh Data' : 'Try Again'; root.classList.remove('is-loading');
                    refreshing = false;
                }
            }
            $('[data-mao-refresh]').addEventListener('click', () => refresh(true, false));
            root.addEventListener('click', (e) => { if (e.target.closest('[data-mao-retry]')) refresh(true, false); });
            // Weather changes through the day; refresh the live sections every 5 minutes, only while the tab is visible.
            setInterval(() => { if (document.visibilityState === 'visible') refresh(false, true); }, 300000);

            const start = () => { initYield('all'); initClimate(); };
            document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();
        })();
    </script>
</x-app-layout>
