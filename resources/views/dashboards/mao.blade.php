<x-app-layout>
    {{-- MAO Dashboard (2026-10, MaoHomeService): four KPI tiles, the "Kailangan ng Aksyon" list, a small Heat Map preview
         and one bar chart. Everything is saved data; the pages behind each number are one click away. The former
         Operations page's parts live on their own pages (Advisories, Calendar, Reports).
         MAO/IT – Naicel (2026-10-07): a section with no data is not shown at all (no empty card, no placeholder text).
         Data the MAO can add (climate records this month, production this season, advisories, planner activities) is
         asked for in the notification bell instead (App\Services\Mao\MaoMissingDataNotifier). --}}
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
        // Only figures that exist are shown; a tile or an action whose number cannot be worked out is left out.
        $tiles = array_values(array_filter($tiles, fn (array $tile): bool => $tile['value'] !== null));
        $actions = array_values(array_filter($mao['actions'], fn (array $action): bool => $action['count'] !== null));
        $waiting = collect($actions)->sum(fn ($a) => (int) $a['count']);
        $showMap = $risk['state'] === 'ok';
        $charts = [
            'yield' => $chart['state'] === 'ok',
            'climate' => $mao['climate']['state'] === 'ok',
            'impact' => $mao['impact']['state'] === 'ok',
            'planted' => $mao['planted']['state'] === 'ok',
        ];
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
        @if ($tiles !== [])
        <section class="mhd-tiles" aria-label="Key figures" data-mao-kpis style="--mhd-tile-cols: {{ count($tiles) }}">
            @foreach ($tiles as $tile)
                <a class="mhd-tile" href="{{ $tile['href'] }}" data-kpi="{{ $tile['key'] }}">
                    <span class="mhd-tile-label">@include('dashboards.mao.icon', ['name' => $tile['icon']]){{ $tile['label'] }}</span>
                    <span class="mhd-tile-value"><span data-kpi-value>{{ $tile['value'] }}</span>@if ($tile['unit'])<small>{{ $tile['unit'] }}</small>@endif</span>
                    @if ($tile['key'] === 'yield' && $y['change_percent'] !== null)
                        <span class="mtd-trend {{ $y['change_percent'] > 0 ? 'is-up' : ($y['change_percent'] < 0 ? 'is-down' : 'is-flat') }}" data-kpi-change>
                            <span aria-hidden="true">{{ $y['change_percent'] > 0 ? '↑' : ($y['change_percent'] < 0 ? '↓' : '→') }}</span>
                            <strong>{{ $y['change_percent'] > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($y['change_percent'], 1), '0'), '.') }}%</strong> vs last season
                        </span>
                    @endif
                    <span class="mhd-tile-note">{{ $tile['note'] }}</span>
                </a>
            @endforeach
        </section>
        @endif

        @if ($actions !== [] || $showMap)
        <div @class(['mhd-grid', 'is-single' => $actions === [] || ! $showMap])>
            {{-- 2. Kailangan ng Aksyon --}}
            @if ($actions !== [])
            <section class="mtd-card mhd-actions" aria-labelledby="mhdActionsTitle" data-mao-actions>
                <div class="mtd-card-head">
                    <h2 id="mhdActionsTitle">Kailangan ng Aksyon</h2>
                    @if ($waiting > 0)<span class="mtd-count">{{ $waiting }}</span>@endif
                </div>
                <ul class="mhd-action-list">
                    @foreach ($actions as $action)
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
            @endif

            {{-- 3. Small Heat Map preview: the whole card opens the Heat Map. Not shown before the first saved forecast. --}}
            @if ($showMap)
            <section class="mtd-card mhd-map" aria-labelledby="mhdMapTitle">
                <div class="mtd-card-head">
                    <div>
                        <h2 id="mhdMapTitle">Heat Map</h2>
                        <p class="mtd-sub">Farm risk this week, by barangay.</p>
                    </div>
                    <a class="mtd-link" href="{{ route('heat-map.index') }}">Open Heat Map →</a>
                </div>
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
            </section>
            @endif
        </div>
        @endif

        {{-- 4. Four charts, all from saved records (MaoHomeService): no provider is called and no model is run. --}}
        @if (in_array(true, $charts, true))
        <div class="mhd-charts">
            @if ($charts['yield'])
            <section class="mtd-card" aria-labelledby="mhdYieldTitle" data-mao-yield>
                <div class="mtd-card-head">
                    <div>
                        <h2 id="mhdYieldTitle">Rice Yield by Year {{ \App\Services\Analytics\MaoHomeService::CHART_FROM_YEAR }}–{{ \App\Services\Analytics\MaoHomeService::CHART_TO_YEAR }}</h2>
                        <p class="mtd-sub">Average tons per hectare, from past records.</p>
                    </div>
                </div>
                <div class="mtd-chart">
                    <canvas id="riceYieldTrendChart" data-yield="{{ json_encode($chart) }}"
                        data-chart-source="{{ $chart['source'] }}" data-chart-type="historical" data-chart-unit="t/ha" data-chart-info-style="icon"
                        data-chart-note="Historical data, not a forecast. A year with fewer than 12 monthly records is the average of the months on file."
                        aria-label="Average rice yield by year, {{ $chart['labels'][0] }} to {{ end($chart['labels']) }}"></canvas>
                </div>
            </section>
            @endif

            @if ($charts['climate'])
            <section class="mtd-card" aria-labelledby="mhdClimateTitle" data-mao-climate>
                <div class="mtd-card-head">
                    <div>
                        <h2 id="mhdClimateTitle">Rainfall and Temperature by Month</h2>
                        <p class="mtd-sub">The latest 12 months on record. Blue is rain, orange is heat.</p>
                    </div>
                </div>
                <div class="mtd-chart">
                    <canvas id="maoClimateChart" data-chart="{{ json_encode($mao['climate']) }}" data-chart-type="historical" data-chart-info-style="icon"
                        aria-label="Monthly rainfall in millimetres and temperature in degrees Celsius, {{ $mao['climate']['labels'][0] }} to {{ end($mao['climate']['labels']) }}"></canvas>
                </div>
            </section>
            @endif

            @if ($charts['impact'])
            <section class="mtd-card" aria-labelledby="mhdImpactTitle" data-mao-impact>
                <div class="mtd-card-head">
                    <div>
                        <h2 id="mhdImpactTitle">Weather Impact: Rain and Yield</h2>
                        <p class="mtd-sub">Each dot is one rice season.</p>
                    </div>
                </div>
                <div class="mtd-chart">
                    <canvas id="maoImpactChart" data-chart="{{ json_encode($mao['impact']) }}" data-chart-type="historical" data-chart-info-style="icon"
                        aria-label="Average monthly rainfall against average rice yield, one point per season"></canvas>
                </div>
                <p class="mhd-reading" data-mao-impact-reading>{{ $mao['impact']['reading'] }}</p>
            </section>
            @endif

            @if ($charts['planted'])
            <section class="mtd-card" aria-labelledby="mhdPlantedTitle" data-mao-planted>
                <div class="mtd-card-head">
                    <div>
                        <h2 id="mhdPlantedTitle">Planted Area by Barangay</h2>
                        <p class="mtd-sub">Hectares planted this season ({{ $mao['season'] }}).</p>
                    </div>
                </div>
                <div class="mtd-chart">
                    <canvas id="maoPlantedChart" data-chart="{{ json_encode($mao['planted']) }}" data-chart-type="recorded" data-chart-unit="ha" data-chart-info-style="icon"
                        aria-label="Hectares planted this season in each barangay"></canvas>
                </div>
            </section>
            @endif
        </div>
        @endif

        {{-- Survey Results: farmers' answers about the forecast, and predicted against harvested yield. Saved answers only. --}}
        @php $survey = $mao['survey'] ?? ['accuracy' => [], 'yield' => ['count' => 0, 'rows' => collect()]]; @endphp
        @if ($survey['accuracy'] !== [] || $survey['yield']['count'] > 0)
        <div class="mhd-charts" data-mao-survey>
            @if ($survey['accuracy'] !== [])
            <section class="mtd-card" aria-labelledby="mhdSurveyTitle">
                <div class="mtd-card-head">
                    <div>
                        <h2 id="mhdSurveyTitle">Survey Results: Forecast Accuracy</h2>
                        <p class="mtd-sub">What farmers answered after each forecast, by month.</p>
                    </div>
                </div>
                <div class="mtd-table-wrap">
                    <table class="mtd-table">
                        <thead><tr><th>Month</th><th>Answers</th><th>Tama</th><th>Medyo</th><th>Mali</th></tr></thead>
                        <tbody>
                            @foreach ($survey['accuracy'] as $row)
                                <tr><td>{{ $row['month'] }}</td><td>{{ $row['reports'] }}</td><td><strong>{{ $row['tama'] }}%</strong></td><td>{{ $row['medyo'] }}%</td><td>{{ $row['mali'] }}%</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
            @endif

            @if ($survey['yield']['count'] > 0)
            <section class="mtd-card" aria-labelledby="mhdSurveyYieldTitle">
                <div class="mtd-card-head">
                    <div>
                        <h2 id="mhdSurveyYieldTitle">Survey Results: Predicted vs Actual Yield</h2>
                        <p class="mtd-sub">The yield the system predicted and the yield farmers harvested, in tons per hectare.</p>
                    </div>
                </div>
                <p class="mhd-reading mhd-reading-flat">Predicted {{ number_format($survey['yield']['predicted'], 2) }} t/ha · Actual {{ number_format($survey['yield']['actual'], 2) }} t/ha · {{ $survey['yield']['count'] }} {{ $survey['yield']['count'] === 1 ? 'harvest' : 'harvests' }}</p>
                <div class="mtd-table-wrap">
                    <table class="mtd-table">
                        <thead><tr><th>Harvested</th><th>Barangay</th><th>Predicted</th><th>Actual</th><th>Difference</th></tr></thead>
                        <tbody>
                            @foreach ($survey['yield']['rows'] as $row)
                                <tr><td>{{ $row['date'] }}</td><td>{{ $row['barangay'] }}</td><td>{{ number_format($row['predicted'], 2) }}</td><td>{{ number_format($row['actual'], 2) }}</td><td>{{ $row['difference'] > 0 ? '+' : '' }}{{ number_format($row['difference'], 2) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
            @endif
        </div>
        @endif
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
