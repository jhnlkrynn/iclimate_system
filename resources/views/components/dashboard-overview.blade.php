@props([
    'overview' => [],
    'title' => 'Overview',
    'note' => null,
    'kpiTitle' => 'Key Indicators',
    'showFilter' => false,
])

{{--
    Role overview: primary KPIs, then charts, then (IT only) a system status strip.
    $overview comes from App\Services\Analytics\DashboardOverviewService.
    Charts are drawn client-side by the script at the bottom from data-config JSON.
--}}
@once
    <style>
        .dash-overview { margin-bottom: 1.75rem; }
        /* Compact KPI tiles: five fit on one row instead of 4 + 1 orphan. */
        .dash-overview .kpi-grid-primary { grid-template-columns: repeat(auto-fit, minmax(175px, 1fr)); gap: .75rem; }
        .dash-overview .kpi-tile { padding: .8rem .95rem .75rem; gap: .2rem; border-radius: 10px; }
        .dash-overview .kpi-label { font-size: .68rem; }
        .dash-overview .kpi-value { font-size: clamp(1.25rem, 1.5vw, 1.5rem); }
        .dash-overview .kpi-value.is-empty { font-size: .95rem; }
        .dash-overview .kpi-unit { font-size: .74rem; }
        .dash-overview .kpi-note { font-size: .74rem; line-height: 1.35; }
        .dash-overview .kpi-status { font-size: .64rem; padding: .1rem .45rem; }
        .dash-overview .kpi-section { margin-bottom: 1.1rem; }
        .dash-overview .dash-filter { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem .9rem; margin: 0 0 1rem; padding: .7rem 1rem; border: 1px solid #E2E8F0; border-radius: 12px; background: #fff; color: #1E293B; }
        .dash-overview .dash-filter label { font-size: .78rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #475569; margin: 0; }
        .dash-overview .dash-filter select { min-width: 110px; padding: .35rem .6rem; border: 1px solid #CBD5E1; border-radius: 8px; background: #fff; color: #0F172A; font-size: .9rem; }
        .dash-overview .dash-filter small { color: #64748B; font-size: .78rem; }
        .dash-overview .dash-charts { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 290px), 1fr)); gap: .85rem; margin-top: .85rem; }
        .dash-overview .dash-chart { min-width: 0; display: flex; flex-direction: column; padding: .85rem 1rem .8rem; border: 1px solid #E2E8F0; border-radius: 12px; background: #fff; color: #1E293B; box-shadow: 0 1px 3px rgba(15,23,42,.03); }
        .dash-overview .dash-chart h3 { margin: 0; font-size: 1rem; font-weight: 700; color: #0F172A; font-family: inherit; letter-spacing: 0; text-transform: none; }
        .dash-overview .dash-chart-sub { margin: .15rem 0 .75rem; font-size: .78rem; line-height: 1.4; color: #64748B; }
        .dash-overview .dash-chart-body { position: relative; height: 180px; }
        .dash-overview .dash-chart-body canvas { max-width: 100%; }
        .dash-overview .dash-chart-empty { display: flex; align-items: center; justify-content: center; height: 180px; padding: 1rem; border: 1px dashed #CBD5E1; border-radius: 10px; background: #F8FAFC; text-align: center; color: #64748B; font-size: .85rem; line-height: 1.5; }
        .dash-overview .dash-chart-empty strong { display: block; color: #334155; font-size: .92rem; margin-bottom: .2rem; }
        .dash-overview .dash-updated { margin: 0 0 .75rem; font-size: .8rem; color: rgba(255,255,255,.85); }
        .dash-overview .dash-updated a { color: inherit; text-decoration: underline; text-underline-offset: 2px; }
        .dash-overview .dash-chart-head { display: flex; align-items: flex-start; justify-content: space-between; gap: .6rem; }
        .dash-overview .dash-basis { flex: 0 0 auto; font-size: .66rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; padding: .15rem .5rem; border-radius: 999px; background: rgba(31,90,70,.1); color: #2b4a3c; white-space: nowrap; }
        .dash-overview .dash-sources { margin-top: 1rem; border: 1px solid var(--gl-border, #E2E8F0); border-radius: 14px; background: rgba(255,255,255,.9); color: #1E293B; }
        .dash-overview .dash-sources > summary { cursor: pointer; padding: .8rem 1.1rem; font-weight: 700; font-size: .92rem; display: flex; flex-wrap: wrap; gap: .25rem .75rem; align-items: baseline; list-style-position: inside; }
        .dash-overview .dash-sources > summary small { font-weight: 500; color: #55665d; font-size: .78rem; }
        .dash-overview .dash-sources-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 300px), 1fr)); gap: .75rem; padding: 0 1rem 1rem; }
        .dash-overview .dash-source { min-width: 0; padding: .8rem .9rem; border: 1px solid #E2E8F0; border-radius: 12px; background: #fff; font-size: .8rem; line-height: 1.45; }
        .dash-overview .dash-source h4 { margin: 0 0 .35rem; font-size: .9rem; font-weight: 700; color: #0F172A; display: flex; justify-content: space-between; align-items: center; gap: .5rem; font-family: inherit; letter-spacing: 0; text-transform: none; }
        .dash-overview .dash-kind { font-size: .64rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; padding: .12rem .45rem; border-radius: 999px; border: 1px solid currentColor; white-space: nowrap; }
        .dash-overview .dash-kind.is-observed { color: #1F5F86; }
        .dash-overview .dash-kind.is-historical { color: #1F5A46; }
        .dash-overview .dash-kind.is-predicted { color: #8A5A0B; }
        .dash-overview .dash-kind.is-calculated { color: #5B4A99; }
        .dash-overview .dash-kind.is-count { color: #475569; }
        .dash-overview .dash-source dl { margin: 0; display: grid; grid-template-columns: 5.5rem minmax(0, 1fr); gap: .15rem .6rem; }
        .dash-overview .dash-source dt { font-weight: 600; color: #55665d; }
        .dash-overview .dash-source dd { margin: 0; overflow-wrap: anywhere; color: #1E293B; }
        .dash-overview .dash-source .dash-warn { margin: .5rem 0 0; padding: .4rem .55rem; border-radius: 8px; background: #FDF1E3; color: #7A4A08; border-left: 3px solid #C58B2A; }
        .dash-overview .dash-source .dash-note { margin: .45rem 0 0; color: #55665d; }
        .dash-overview .dash-status { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .75rem; margin-top: 1rem; padding: .85rem 1.1rem; border: 1px solid #E2E8F0; border-radius: 12px; background: #fff; color: #1E293B; }
        .dash-overview .dash-status-head { font-weight: 700; font-size: .92rem; margin-right: .5rem; }
        .dash-overview .dash-status-item { display: inline-flex; align-items: center; gap: .35rem; font-size: .78rem; color: #475569; padding: .15rem .55rem; border-radius: 999px; background: #F1F5F9; }
        .dash-overview .dash-status-item::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: #94A3B8; }
        .dash-overview .dash-status-item.is-healthy::before { background: #1F5A46; }
        .dash-overview .dash-status-item.is-attention::before { background: #C58B2A; }
        .dash-overview .dash-status-item.is-unavailable::before { background: #B84A4A; }
        @media (max-width: 575.98px) { .dash-overview .dash-chart-body, .dash-overview .dash-chart-empty { height: 220px; } .dash-overview .dash-chart { padding: .95rem 1rem; } }
    </style>
@endonce

@php
    $kpis = $overview['kpis'] ?? [];
    $charts = $overview['charts'] ?? [];
    $years = $overview['years'] ?? [];
    $year = $overview['year'] ?? null;
    $status = $overview['status'] ?? null;
    $hasCharts = collect($charts)->contains(fn ($chart) => ! $chart['empty']);
    $sources = $overview['sources'] ?? [];
    $tz = config('services.weather.timezone', 'Asia/Manila');
    $stamp = fn ($iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->timezone($tz)->format('M j, Y g:i A') : null;
    $generatedLabel = $stamp($overview['generated_at'] ?? null);
@endphp

<section class="dash-overview" data-dashboard-overview="{{ $overview['role'] ?? '' }}">
    @if ($showFilter && count($years) > 0)
        <form method="GET" class="dash-filter" aria-label="Dashboard year filter">
            @foreach (request()->except('year') as $name => $value)
                @if (is_scalar($value))
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endif
            @endforeach
            <label for="dashYear">Year</label>
            <select id="dashYear" name="year" onchange="this.form.submit()">
                @foreach ($years as $option)
                    <option value="{{ $option }}" @selected($option === $year)>{{ $option }}</option>
                @endforeach
            </select>
            <noscript><button type="submit" class="btn btn-sm btn-outline-secondary">Apply</button></noscript>
            <small>Figures and charts below use records for {{ $year }} (yield trend shows {{ $year }} and earlier).</small>
        </form>
    @endif

    @if ($showFilter && $generatedLabel)
        <p class="dash-updated">Figures last calculated {{ $generatedLabel }} ({{ $tz }}).
            @if (count($sources) > 0)<a href="#dashSources{{ $overview['role'] ?? '' }}">Where does this data come from?</a>@endif
        </p>
    @endif

    <x-kpi-strip :kpis="$kpis" :title="$kpiTitle" :note="$note" />

    @if ($status)
        <div class="dash-status" role="status">
            <span class="dash-status-head">System &amp; data status: {{ $status['headline'] }}</span>
            @foreach ($status['items'] as $item)
                <span class="dash-status-item is-{{ $item['status'] === 'healthy' ? 'healthy' : ($item['status'] === 'unavailable' ? 'unavailable' : 'attention') }}" title="{{ $item['status'] === 'healthy' ? 'Healthy' : ($item['status'] === 'unavailable' ? 'Unavailable' : 'Needs attention') }}">{{ $item['title'] }}</span>
            @endforeach
        </div>
    @endif

    <div class="dash-charts">
        @foreach ($charts as $chart)
            <article class="dash-chart" data-chart-card="{{ $chart['id'] }}">
                <div class="dash-chart-head">
                    <h3>{{ $chart['title'] }}</h3>
                    @if (! empty($chart['basis']))<span class="dash-basis" title="What kind of data this chart shows">{{ $chart['basis'] }}</span>@endif
                </div>
                <p class="dash-chart-sub">{{ $chart['subtitle'] }}</p>
                @if ($chart['empty'])
                    <div class="dash-chart-empty"><div><strong>No verified data available</strong>{{ $chart['empty_message'] }}</div></div>
                @else
                    <div class="dash-chart-body">
                        <canvas data-dash-chart data-config="{{ json_encode($chart) }}" role="img" aria-label="{{ $chart['title'] }}"></canvas>
                    </div>
                @endif
            </article>
        @endforeach
    </div>

    @if (count($sources) > 0)
        <details class="dash-sources" id="dashSources{{ $overview['role'] ?? '' }}">
            <summary>Data sources &amp; last updated <small>{{ count($sources) }} sources · Observed, Historical, Predicted and Calculated data are kept separate</small></summary>
            <div class="dash-sources-grid">
                @foreach ($sources as $source)
                    <article class="dash-source">
                        <h4>{{ $source['label'] }} <span class="dash-kind is-{{ strtolower($source['kind']) }}">{{ $source['kind'] }}</span></h4>
                        <dl>
                            <dt>Source</dt><dd>{{ $source['source'] }}@if (! empty($source['reference'])) <br><small>{{ $source['reference'] }}</small>@endif</dd>
                            @if (! empty($source['period']))<dt>Covers</dt><dd>{{ $source['period'] }}</dd>@endif
                            <dt>Updated</dt><dd>{{ $stamp($source['updated'] ?? null) ?? 'Timestamp not recorded' }}</dd>
                            @if ($source['records'] !== null)<dt>Records</dt><dd>{{ number_format($source['records']) }}</dd>@endif
                        </dl>
                        @if (! empty($source['warning']))<p class="dash-warn"><strong>Data quality:</strong> {{ $source['warning'] }}</p>@endif
                        @if (! empty($source['note']))<p class="dash-note">{{ $source['note'] }}</p>@endif
                    </article>
                @endforeach
            </div>
        </details>
    @endif
</section>

@if ($hasCharts)
    @once
        <script>
            (() => {
                const GREEN = '#1F5A46', BLUE = '#3B7A9E', AMBER = '#C58B2A', SAGE = '#7FB08F';
                const seriesColors = [GREEN, BLUE, AMBER];
                const sliceColors = {
                    low: '#1F5A46', moderate: '#C58B2A', high: '#D9772B', severe: '#B84A4A', critical: '#B84A4A',
                    information: '#7FB08F', info: '#7FB08F',
                };
                const fallbackSlices = ['#1F5A46', '#3B7A9E', '#C58B2A', '#7FB08F', '#8A6FA8', '#B84A4A'];
                const alpha = (hex, a) => {
                    const n = parseInt(hex.slice(1), 16);
                    return `rgba(${n >> 16}, ${(n >> 8) & 255}, ${n & 255}, ${a})`;
                };
                const fmt = (v, unit) => v === null || v === undefined ? 'n/a'
                    : Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 }) + (unit ? ' ' + unit : '');

                function build(canvas, cfg) {
                    if (cfg.type === 'doughnut') {
                        return new Chart(canvas, {
                            type: 'doughnut',
                            data: {
                                labels: cfg.labels,
                                datasets: [{
                                    data: cfg.datasets[0].data,
                                    backgroundColor: cfg.labels.map((l, i) => sliceColors[String(l).toLowerCase()] || fallbackSlices[i % fallbackSlices.length]),
                                    borderColor: '#fff', borderWidth: 2,
                                }],
                            },
                            options: {
                                responsive: true, maintainAspectRatio: false, cutout: '62%',
                                plugins: {
                                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 18 } } },
                                },
                            },
                        });
                    }

                    const horizontal = !!cfg.horizontal;
                    const dual = cfg.datasets.some((d) => d.axis === 'y2');
                    const datasets = cfg.datasets.map((d, i) => {
                        const color = seriesColors[d.series ?? i] || GREEN;
                        const predicted = d.kind === 'predicted';
                        const base = { label: d.label, data: d.data, yAxisID: d.axis || 'y', spanGaps: false };
                        if (cfg.type === 'bar') {
                            return { ...base, backgroundColor: predicted ? alpha(color, .3) : alpha(color, .85), borderColor: color,
                                borderWidth: predicted ? 1.5 : 0, borderDash: predicted ? [5, 4] : [], borderRadius: 4, maxBarThickness: 38 };
                        }
                        return { ...base, borderColor: color, backgroundColor: alpha(color, .12), borderWidth: 2.5, tension: .3,
                            borderDash: predicted ? [6, 4] : [], pointStyle: predicted ? 'rectRot' : 'circle',
                            pointRadius: predicted ? 6 : 3.5, pointHoverRadius: 7, fill: false };
                    });
                    const multi = datasets.length > 1;
                    const scales = {
                        [horizontal ? 'x' : 'y']: { beginAtZero: true, grid: { color: '#EEF2EF' }, ticks: { maxTicksLimit: 6 },
                            title: { display: !!(cfg.axis_titles && cfg.axis_titles[0]) || !!cfg.unit, text: (cfg.axis_titles && cfg.axis_titles[0]) || cfg.unit } },
                        [horizontal ? 'y' : 'x']: { grid: { display: false }, ticks: { autoSkip: true, maxRotation: 0, font: { size: 18 } } },
                    };
                    if (dual) {
                        scales.y2 = { position: 'right', grid: { drawOnChartArea: false }, ticks: { maxTicksLimit: 6 },
                            title: { display: true, text: cfg.axis_titles[1] } };
                    }
                    return new Chart(canvas, {
                        type: cfg.type,
                        data: { labels: cfg.labels, datasets },
                        options: {
                            responsive: true, maintainAspectRatio: false, indexAxis: horizontal ? 'y' : 'x',
                            interaction: { mode: 'index', intersect: false },
                            scales,
                            plugins: {
                                legend: { display: multi, position: 'bottom', labels: { boxWidth: 12, font: { size: 18 },
                                    filter: (item, data) => data.datasets[item.datasetIndex].data.some((v) => v !== null) } },
                                tooltip: { callbacks: {
                                    // Dual-axis charts use the shared tooltip label, which picks each series' own axis unit.
                                    ...(dual ? {} : { label: (c) => `${c.dataset.label}: ${fmt(c.parsed[horizontal ? 'x' : 'y'], cfg.unit)}` }),
                                    afterLabel: (c) => c.datasetIndex === 0
                                        ? Object.entries(cfg.extra || {}).map(([k, v]) => `${k}: ${v[c.dataIndex] ?? 'n/a'}`).join('\n')
                                        : '',
                                } },
                            },
                        },
                    });
                }

                const start = async () => {
                    try { await window.iClimateVisualsReady; } catch (e) { /* handled below */ }
                    document.querySelectorAll('canvas[data-dash-chart]').forEach((canvas) => {
                        if (!window.Chart) {
                            canvas.closest('.dash-chart-body').innerHTML = '<div class="dash-chart-empty"><div><strong>Chart unavailable</strong>The chart library could not be loaded.</div></div>';
                            return;
                        }
                        try { build(canvas, JSON.parse(canvas.dataset.config)); } catch (e) { console.error('Dashboard chart failed', canvas.dataset.config?.slice(0, 60), e); }
                    });
                };
                document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();
            })();
        </script>
    @endonce
@endif
