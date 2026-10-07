<x-app-layout>
    {{-- MAO Dashboard (2026-10, MaoHomeService): four KPI tiles, the "Kailangan ng Aksyon" list, a small Heat Map preview
         and one bar chart. Everything is saved data; the pages behind each number are one click away. The former
         Operations page's parts live on their own pages (Advisories, Calendar, Field Reports, Reports). --}}
    @php
        $num = fn ($value, int $decimals = 0) => $value === null ? null : number_format((float) $value, $decimals);
        $y = $mao['yield'];
        $risk = $mao['risk'];
        $chart = $mao['chart'];
        $tiles = [
            ['key' => 'farmers', 'label' => 'Registered farmers', 'icon' => 'farmers', 'href' => route('farmer-profiles.index'),
                'value' => $num($mao['farmers']), 'unit' => null, 'note' => 'Farmer profiles on file'],
            ['key' => 'area', 'label' => 'Area planted this season', 'icon' => 'sprout', 'href' => route('mao.planner.index'),
                'value' => $num($mao['area']['hectares'], 1), 'unit' => 'ha',
                'note' => $mao['area']['hectares'] === null ? 'No planting plans recorded for '.$mao['season'].' yet' : 'From '.$mao['area']['plans'].' planting '.($mao['area']['plans'] === 1 ? 'plan' : 'plans').' · '.$mao['season']],
            ['key' => 'yield', 'label' => 'Expected yield vs last season', 'icon' => 'yield', 'href' => route('weather-predictions.index'),
                'value' => $num($y['expected'], 2), 'unit' => 't/ha',
                'note' => $y['expected'] === null ? 'No saved farmer estimates yet'
                    : ($y['last'] !== null ? $y['last_label'].': '.$num($y['last'], 2).' t/ha' : 'No earlier season on record')],
            ['key' => 'risk', 'label' => 'High-risk barangays', 'icon' => 'alert', 'href' => route('heat-map.index'),
                'value' => $num($risk['high']), 'unit' => $risk['high'] === null ? null : 'of 19',
                'note' => $risk['high'] === null ? 'No saved forecast yet' : ($risk['names'] ? implode(', ', array_slice($risk['names'], 0, 3)).(count($risk['names']) > 3 ? ' +'.(count($risk['names']) - 3).' more' : '') : 'None this week')],
        ];
        $waiting = collect($mao['actions'])->sum(fn ($a) => (int) $a['count']);
    @endphp

    <div class="mtd mhd" data-mao-dashboard>
        <header class="mtd-header">
            <div class="mtd-header-main">
                <span class="mtd-kicker">Municipal Agriculture Office</span>
                <h1>MAO Dashboard</h1>
                <p class="mtd-place">@include('dashboards.mao.icon', ['name' => 'map'])Lian, Batangas · {{ $mao['season'] }}</p>
            </div>
            <div class="mtd-tools">
                <x-updated-at :at="$mao['generated_at']" />
            </div>
        </header>

        {{-- 1. Four KPI tiles --}}
        <section class="mhd-tiles" aria-label="Key figures" data-mao-kpis>
            @foreach ($tiles as $tile)
                <a class="mhd-tile" href="{{ $tile['href'] }}" data-kpi="{{ $tile['key'] }}">
                    <span class="mhd-tile-label">@include('dashboards.mao.icon', ['name' => $tile['icon']]){{ $tile['label'] }}</span>
                    @if ($tile['value'] !== null)
                        <span class="mhd-tile-value"><span data-kpi-value>{{ $tile['value'] }}</span>@if ($tile['unit'])<small>{{ $tile['unit'] }}</small>@endif</span>
                        @if ($tile['key'] === 'yield' && $y['change_percent'] !== null)
                            <span class="mtd-trend {{ $y['change_percent'] > 0 ? 'is-up' : ($y['change_percent'] < 0 ? 'is-down' : 'is-flat') }}" data-kpi-change>
                                <span aria-hidden="true">{{ $y['change_percent'] > 0 ? '↑' : ($y['change_percent'] < 0 ? '↓' : '→') }}</span>
                                <strong>{{ $y['change_percent'] > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($y['change_percent'], 1), '0'), '.') }}%</strong> vs last season
                            </span>
                        @endif
                    @else
                        <span class="mhd-tile-value is-empty" data-kpi-empty>Walang datos pa</span>
                    @endif
                    <span class="mhd-tile-note">{{ $tile['note'] }}</span>
                </a>
            @endforeach
        </section>

        <div class="mhd-grid">
            {{-- 2. Kailangan ng Aksyon --}}
            <section class="mtd-card mhd-actions" aria-labelledby="mhdActionsTitle" data-mao-actions>
                <div class="mtd-card-head">
                    <h2 id="mhdActionsTitle">Kailangan ng Aksyon</h2>
                    @if ($waiting > 0)<span class="mtd-count">{{ $waiting }}</span>@endif
                </div>
                <ul class="mhd-action-list">
                    @foreach ($mao['actions'] as $action)
                        <li @class(['mhd-action', 'is-waiting' => (int) $action['count'] > 0]) data-mao-action="{{ $action['key'] }}">
                            <strong class="mhd-action-count">{{ $action['count'] === null ? '—' : number_format($action['count']) }}</strong>
                            <span class="mhd-action-label">{{ $action['label'] }}</span>
                            <a class="mtd-btn @if ((int) $action['count'] === 0) is-ghost @endif" href="{{ $action['href'] }}">{{ $action['action'] }}</a>
                        </li>
                    @endforeach
                </ul>
                @if ($waiting === 0)
                    <p class="mtd-allgood">@include('dashboards.mao.icon', ['name' => 'check']) Nothing is waiting right now.</p>
                @endif
            </section>

            {{-- 3. Small Heat Map preview: the whole card opens the Heat Map. --}}
            <section class="mtd-card mhd-map" aria-labelledby="mhdMapTitle">
                <div class="mtd-card-head">
                    <div>
                        <h2 id="mhdMapTitle">Heat Map</h2>
                        <p class="mtd-sub">Farm risk this week, by barangay.</p>
                    </div>
                    <a class="mtd-link" href="{{ route('heat-map.index') }}">Open Heat Map →</a>
                </div>
                @if ($risk['state'] === 'ok')
                    <a class="mhd-map-frame" href="{{ route('heat-map.index') }}" aria-label="Open the Heat Map" data-heat-map-preview>
                        <div id="heatMapPreviewCanvas" class="mhd-map-canvas" data-boundary-url="{{ url('/api/boundaries/lian-barangays') }}"></div>
                        <script type="application/json" data-heat-map-preview-levels>@json(['levels' => $risk['levels'], 'colors' => \App\Services\Risk\HeatMapPanel::COLORS])</script>
                    </a>
                    <p class="mhd-legend">
                        @foreach (['low' => 'Low', 'moderate' => 'Moderate', 'high' => 'High'] as $level => $label)
                            <span><span class="mhd-dot" style="--mhd-color: {{ \App\Services\Risk\HeatMapPanel::COLORS[$level] }}"></span>{{ $label }}</span>
                        @endforeach
                        <x-updated-at :at="$risk['updated_at']" />
                    </p>
                @else
                    <x-no-data note="The map appears here after the next weather update." />
                @endif
            </section>
        </div>

        {{-- 4. Four charts, all from saved records (MaoHomeService): no provider is called and no model is run. --}}
        <div class="mhd-charts">
            <section class="mtd-card" aria-labelledby="mhdYieldTitle" data-mao-yield>
                <div class="mtd-card-head">
                    <div>
                        <h2 id="mhdYieldTitle">Rice Yield by Year {{ \App\Services\Analytics\MaoHomeService::CHART_FROM_YEAR }}–{{ \App\Services\Analytics\MaoHomeService::CHART_TO_YEAR }}</h2>
                        <p class="mtd-sub">Average tons per hectare, from past records.</p>
                    </div>
                </div>
                @if ($chart['state'] === 'ok')
                    <div class="mtd-chart">
                        <canvas id="riceYieldTrendChart" data-yield="{{ json_encode($chart) }}"
                            data-chart-source="{{ $chart['source'] }}" data-chart-type="historical" data-chart-unit="t/ha" data-chart-info-style="icon"
                            data-chart-note="Historical data, not a forecast. A year with fewer than 12 monthly records is the average of the months on file."
                            aria-label="Average rice yield by year, {{ $chart['labels'][0] }} to {{ end($chart['labels']) }}"></canvas>
                    </div>
                @else
                    <x-no-data note="The chart needs recorded yields for at least one year." />
                @endif
            </section>

            <section class="mtd-card" aria-labelledby="mhdClimateTitle" data-mao-climate>
                <div class="mtd-card-head">
                    <div>
                        <h2 id="mhdClimateTitle">Rainfall and Temperature by Month</h2>
                        <p class="mtd-sub">The latest 12 months on record. Blue is rain, orange is heat.</p>
                    </div>
                </div>
                @if ($mao['climate']['state'] === 'ok')
                    <div class="mtd-chart">
                        <canvas id="maoClimateChart" data-chart="{{ json_encode($mao['climate']) }}" data-chart-type="historical" data-chart-info-style="icon"
                            aria-label="Monthly rainfall in millimetres and temperature in degrees Celsius, {{ $mao['climate']['labels'][0] }} to {{ end($mao['climate']['labels']) }}"></canvas>
                    </div>
                @else
                    <x-no-data note="The chart needs monthly climate records." />
                @endif
            </section>

            <section class="mtd-card" aria-labelledby="mhdImpactTitle" data-mao-impact>
                <div class="mtd-card-head">
                    <div>
                        <h2 id="mhdImpactTitle">Weather Impact: Rain and Yield</h2>
                        <p class="mtd-sub">Each dot is one rice season.</p>
                    </div>
                </div>
                @if ($mao['impact']['state'] === 'ok')
                    <div class="mtd-chart">
                        <canvas id="maoImpactChart" data-chart="{{ json_encode($mao['impact']) }}" data-chart-type="historical" data-chart-info-style="icon"
                            aria-label="Average monthly rainfall against average rice yield, one point per season"></canvas>
                    </div>
                    <p class="mhd-reading" data-mao-impact-reading>{{ $mao['impact']['reading'] }}</p>
                @else
                    <x-no-data note="The chart needs rainfall and yield records for at least three seasons." />
                @endif
            </section>

            <section class="mtd-card" aria-labelledby="mhdPlantedTitle" data-mao-planted>
                <div class="mtd-card-head">
                    <div>
                        <h2 id="mhdPlantedTitle">Planted Area by Barangay</h2>
                        <p class="mtd-sub">Hectares planted this season ({{ $mao['season'] }}).</p>
                    </div>
                </div>
                @if ($mao['planted']['state'] === 'ok')
                    <div class="mtd-chart">
                        <canvas id="maoPlantedChart" data-chart="{{ json_encode($mao['planted']) }}" data-chart-type="recorded" data-chart-unit="ha" data-chart-info-style="icon"
                            aria-label="Hectares planted this season in each barangay"></canvas>
                    </div>
                @else
                    <x-no-data note="No farmer has saved a planting date for this season yet." />
                @endif
            </section>
        </div>
    </div>

    <script>
        (() => {
            // Large, plain labels: this page is read by older staff.
            const FONT = { size: 14 };
            const axis = (text, extra = {}) => ({ title: { display: true, text, font: { size: 14, weight: '600' } }, ticks: { font: FONT }, grid: { color: '#eef2ef' }, ...extra });
            const read = (id, attr = 'chart') => { const el = document.getElementById(id); return el ? [el, JSON.parse(el.dataset[attr] || '{}')] : [null, {}]; };
            const base = (legend) => ({ responsive: true, maintainAspectRatio: false, plugins: { legend: { display: legend, position: 'bottom', labels: { font: FONT, usePointStyle: true } } } });

            const start = async () => {
                try { await window.iClimateVisualsReady; } catch (e) { /* no chart library: the headings and notes stay */ }
                if (!window.Chart) return;

                const [yieldEl, y] = read('riceYieldTrendChart', 'yield');
                if (yieldEl) new window.Chart(yieldEl, {
                    type: 'bar',
                    data: { labels: y.labels || [], datasets: [{ label: 'Average yield (t/ha)', data: y.values || [], backgroundColor: '#0f5132', borderRadius: 4, maxBarThickness: 64 }] },
                    options: { ...base(false), scales: { y: axis('Yield (t/ha)', { beginAtZero: true }), x: { grid: { display: false }, ticks: { font: FONT } } },
                        plugins: { legend: { display: false }, tooltip: { callbacks: {
                            title: (items) => 'Year ' + items[0]?.label,
                            label: (c) => 'Average yield: ' + (c.parsed.y != null ? c.parsed.y.toFixed(2) : 'n/a') + ' t/ha',
                            afterLabel: (c) => 'Records: ' + ((y.months || [])[c.dataIndex] || 0) + ' of 12 months',
                        } } } },
                });

                const [climateEl, c] = read('maoClimateChart');
                if (climateEl) new window.Chart(climateEl, {
                    type: 'line',
                    data: { labels: c.labels || [], datasets: [
                        { label: 'Rainfall (mm)', data: c.rainfall || [], yAxisID: 'rain', borderColor: '#1f6fb2', backgroundColor: '#1f6fb2', borderWidth: 3, tension: .3, pointRadius: 4 },
                        { label: 'Temperature (°C)', data: c.temperature || [], yAxisID: 'temp', borderColor: '#c2570c', backgroundColor: '#c2570c', borderWidth: 3, borderDash: [6, 4], tension: .3, pointRadius: 4, pointStyle: 'rectRot' },
                    ] },
                    options: { ...base(true), interaction: { mode: 'index', intersect: false },
                        scales: { rain: axis('Rainfall (mm)', { position: 'left', beginAtZero: true }), temp: axis('Temperature (°C)', { position: 'right', grid: { drawOnChartArea: false } }), x: { grid: { display: false }, ticks: { font: FONT, maxRotation: 0, autoSkip: true } } } },
                });

                const [impactEl, i] = read('maoImpactChart');
                if (impactEl) new window.Chart(impactEl, {
                    type: 'line',   // dots only (showLine: false): the scatter controller is not in this app's Chart.js bundle
                    data: { datasets: [{ label: 'Rice season', data: i.points || [], showLine: false, backgroundColor: '#0f5132', borderColor: '#0f5132', pointRadius: 7, pointHoverRadius: 9 }] },
                    options: { ...base(false), scales: { x: axis('Average rainfall per month (mm)', { type: 'linear' }), y: axis('Yield (t/ha)') },
                        plugins: { legend: { display: false }, tooltip: { callbacks: { label: (p) => p.raw.label + ': ' + p.raw.x + ' mm rain, ' + p.raw.y.toFixed(2) + ' t/ha' } } } },
                });

                const [plantedEl, p] = read('maoPlantedChart');
                if (plantedEl) new window.Chart(plantedEl, {
                    type: 'bar',
                    data: { labels: p.labels || [], datasets: [{ label: 'Planted area (ha)', data: p.values || [], backgroundColor: '#198754', borderRadius: 4, maxBarThickness: 48 }] },
                    options: { ...base(false), indexAxis: 'y', scales: { x: axis('Hectares', { beginAtZero: true }), y: { grid: { display: false }, ticks: { font: FONT } } },
                        plugins: { legend: { display: false }, tooltip: { callbacks: { label: (b) => b.parsed.x + ' ha planted' } } } },
                });
            };
            document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();
        })();
    </script>
</x-app-layout>
