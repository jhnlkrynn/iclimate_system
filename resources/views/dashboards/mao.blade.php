<x-app-layout>
    {{-- MAO Dashboard (2026-10, MaoHomeService): KPI tiles, the "Needs Action" list, a small Heat Map preview and the
         charts. Everything is saved data; the pages behind each number are one click away. The former
         Operations page's parts live on their own pages (Advisories, Calendar, Reports).
         MAO/IT – Naicel (2026-10-07): a section with no data is not shown at all (no empty card, no placeholder text).
         Data the MAO can add (climate records this month, production this season, advisories, planner activities) is
         asked for in the notification bell instead (App\Services\Mao\MaoMissingDataNotifier).
         MAO Dashboard – Naicel (2026-10-08): the page is in English, or in Filipino when the MAO picks it with the
         selector in the header (App\Support\MaoLanguage; every word is in lang/{en,fil}/mao.php, section "dashboard").
         Four more tiles (farmers with a planting plan, harvests due, rice production, advisories in effect) and one
         more chart (registered farmers by barangay). --}}
    @php
        $locale = app()->getLocale();
        $t = fn (string $key, array $replace = []): string => (string) __('mao.dashboard.'.$key, $replace);
        $num = fn ($value, int $decimals = 0) => $value === null ? null : number_format((float) $value, $decimals);
        $seasonText = fn (?array $parts, string $fallback = ''): string => $parts ? $t('season.'.$parts['season'], ['years' => $parts['years']]) : $fallback;
        $monthText = fn (string $ym, string $format): string => \Carbon\CarbonImmutable::parse($ym.'-01')->locale($locale)->translatedFormat($format);

        $season = $seasonText($mao['season_parts'] ?? null, (string) $mao['season']);
        $y = $mao['yield'];
        $risk = $mao['risk'];
        $chart = $mao['chart'];
        $plans = $mao['plans'] ?? null;
        $harvests = $mao['harvests'] ?? null;
        $production = $mao['production'] ?? null;
        $inEffect = $mao['advisories'] ?? null;
        $barangays = count(\App\Support\LianBarangays::all());
        $dueDays = \App\Services\Analytics\MaoHomeService::HARVEST_DUE_DAYS;

        $tiles = [
            ['key' => 'farmers', 'label' => $t('tiles.farmers.label'), 'icon' => 'farmers', 'href' => route('farmer-profiles.index'),
                'value' => $num($mao['farmers']), 'unit' => null, 'note' => $t('tiles.farmers.note')],
            ['key' => 'area', 'label' => $t('tiles.area.label'), 'icon' => 'sprout', 'href' => route('mao.planner.index'),
                'value' => $num($mao['area']['hectares'], 1), 'unit' => 'ha',
                'note' => trans_choice('mao.dashboard.tiles.area.note', (int) $mao['area']['plans'], ['count' => $mao['area']['plans'], 'season' => $season])],
            ['key' => 'yield', 'label' => $t('tiles.yield.label'), 'icon' => 'yield', 'href' => route('weather-predictions.index'),
                'value' => $num($y['expected'], 2), 'unit' => 't/ha',
                'note' => $y['last'] !== null
                    ? $t('tiles.yield.last', ['season' => $seasonText($y['last_parts'] ?? null, (string) $y['last_label']), 'value' => $num($y['last'], 2)])
                    : $t('tiles.yield.no_last')],
            ['key' => 'risk', 'label' => $t('tiles.risk.label'), 'icon' => 'alert', 'href' => route('heat-map.index'),
                'value' => $num($risk['high']), 'unit' => $t('tiles.risk.of', ['total' => $barangays]),
                'note' => $risk['names'] ? implode(', ', array_slice($risk['names'], 0, 3)).(count($risk['names']) > 3 ? ' '.$t('more', ['count' => count($risk['names']) - 3]) : '') : $t('tiles.risk.none')],
            // MAO Dashboard – Naicel (2026-10-08): the second row.
            ['key' => 'plans', 'label' => $t('tiles.plans.label'), 'icon' => 'calendar', 'href' => route('mao.planner.index'),
                'value' => $plans ? $num($plans['with']) : null, 'unit' => $plans ? $t('tiles.plans.of', ['total' => $num($plans['of'])]) : null,
                'note' => $plans ? $t('tiles.plans.note', ['percent' => $plans['percent'], 'season' => $season]) : null],
            ['key' => 'harvests', 'label' => $t('tiles.harvests.label', ['days' => $dueDays]), 'icon' => 'clock', 'href' => route('mao.planner.index'),
                'value' => $harvests ? $num($harvests['count']) : null, 'unit' => $harvests ? trans_choice('mao.dashboard.tiles.harvests.unit', (int) $harvests['count']) : null,
                'note' => $harvests ? ($harvests['count'] > 0 ? $t('tiles.harvests.note', ['hectares' => $num($harvests['hectares'], 1)]) : $t('tiles.harvests.none', ['days' => $dueDays])) : null],
            ['key' => 'production', 'label' => $t('tiles.production.label', ['year' => $production['year'] ?? '']), 'icon' => 'report', 'href' => route('rice-productions.index'),
                'value' => $production ? $num($production['production'], 1) : null, 'unit' => $t('tiles.production.unit'),
                'note' => $production ? ($production['yield'] !== null
                    ? $t('tiles.production.note', ['area' => $num($production['area'], 1), 'yield' => $num($production['yield'], 2), 'count' => $production['barangays'], 'total' => $barangays])
                    : $t('tiles.production.note_no_yield', ['count' => $production['barangays'], 'total' => $barangays])) : null],
            ['key' => 'advisories', 'label' => $t('tiles.advisories.label'), 'icon' => 'advisory', 'href' => route('planting-advisories.index'),
                'value' => $num($inEffect), 'unit' => null,
                'note' => $inEffect === null ? null : ($inEffect > 0 ? $t('tiles.advisories.note') : $t('tiles.advisories.none'))],
        ];
        // Only figures that exist are shown; a tile or an action whose number cannot be worked out is left out.
        $tiles = array_values(array_filter($tiles, fn (array $tile): bool => $tile['value'] !== null));
        $actions = array_values(array_filter($mao['actions'], fn (array $action): bool => $action['count'] !== null));
        $waiting = collect($actions)->sum(fn ($a) => (int) $a['count']);
        $showMap = $risk['state'] === 'ok';
        $byBarangay = $mao['by_barangay'] ?? ['state' => 'nodata'];
        $charts = [
            'yield' => $chart['state'] === 'ok',
            'climate' => $mao['climate']['state'] === 'ok',
            'impact' => $mao['impact']['state'] === 'ok',
            'planted' => $mao['planted']['state'] === 'ok',
            'farmers' => $byBarangay['state'] === 'ok',
        ];

        // The charts' words and month names in the page's language (the figures themselves are shared by every MAO).
        $climate = $mao['climate'];
        if ($charts['climate'] && ! empty($climate['months'])) {
            $climate['labels'] = array_map(fn (string $ym): string => $monthText($ym, 'M Y'), $climate['months']);
        }
        $impact = $mao['impact'];
        if ($charts['impact']) {
            $impact['points'] = array_map(fn (array $p): array => ['label' => strtr((string) $p['label'], ['Wet' => $t('season_short.wet'), 'Dry' => $t('season_short.dry')])] + $p, $impact['points']);
            $impactReading = isset($impact['reading_key'])
                ? $t('charts.impact.'.$impact['reading_key']).' '.$t('charts.impact.based', ['count' => $impact['seasons']])
                : (string) $impact['reading'];
        }
        $text = [
            'yield' => ['dataset' => $t('charts.yield.dataset'), 'axis' => $t('charts.yield.axis'), 'year' => $t('charts.yield.tip_year'), 'value' => $t('charts.yield.tip_value'), 'records' => $t('charts.yield.tip_records'), 'months' => $t('charts.yield.tip_months')],
            'climate' => ['rain' => $t('charts.climate.rain'), 'temp' => $t('charts.climate.temp')],
            'impact' => ['dataset' => $t('charts.impact.dataset'), 'x' => $t('charts.impact.axis_x'), 'y' => $t('charts.impact.axis_y'), 'rain' => $t('charts.impact.tip_rain')],
            'planted' => ['dataset' => $t('charts.planted.dataset'), 'axis' => $t('charts.planted.axis'), 'tip' => $t('charts.planted.tip')],
            'farmers' => ['dataset' => $t('charts.farmers.dataset'), 'axis' => $t('charts.farmers.axis'), 'one' => trans_choice('mao.dashboard.charts.farmers.tip', 1), 'many' => trans_choice('mao.dashboard.charts.farmers.tip', 2)],
        ];
    @endphp

    <div class="mtd mhd" data-mao-dashboard lang="{{ $locale }}">
        <header class="mtd-header">
            <div class="mtd-header-main">
                <span class="mtd-kicker">{{ $t('office') }}</span>
                <h1>{{ $t('title') }}</h1>
                <p class="mtd-place">@include('dashboards.mao.icon', ['name' => 'map']){{ $t('place') }} · {{ $season }}</p>
            </div>
            <div class="mtd-tools">
                {{-- MAO Dashboard – Naicel (2026-10-08): English / Filipino. A normal form post; with scripts it is sent as soon as a language is picked. --}}
                <form class="mhd-lang" method="POST" action="{{ route('mao.dashboard.language') }}" data-mao-language>
                    @csrf
                    <label for="mhdLanguage">{{ $t('language') }}</label>
                    <select id="mhdLanguage" name="language" aria-label="{{ $t('language_label') }}">
                        @foreach (\App\Support\MaoLanguage::SUPPORTED as $code => $label)
                            <option value="{{ $code }}" lang="{{ $code }}" @selected($locale === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <noscript><button type="submit" class="mtd-btn">{{ $t('language_apply') }}</button></noscript>
                </form>
                <x-updated-at :at="$mao['generated_at']" />
            </div>
        </header>

        {{-- 1. KPI tiles: up to eight, four in a row on a wide screen --}}
        @if ($tiles !== [])
        <section class="mhd-tiles" aria-label="{{ $t('key_figures') }}" data-mao-kpis style="--mhd-tile-cols: {{ min(4, count($tiles)) }}">
            @foreach ($tiles as $tile)
                <a class="mhd-tile" href="{{ $tile['href'] }}" data-kpi="{{ $tile['key'] }}">
                    <span class="mhd-tile-label">@include('dashboards.mao.icon', ['name' => $tile['icon']]){{ $tile['label'] }}</span>
                    <span class="mhd-tile-value"><span data-kpi-value>{{ $tile['value'] }}</span>@if ($tile['unit'])<small>{{ $tile['unit'] }}</small>@endif</span>
                    @if ($tile['key'] === 'yield' && $y['change_percent'] !== null)
                        <span class="mtd-trend {{ $y['change_percent'] > 0 ? 'is-up' : ($y['change_percent'] < 0 ? 'is-down' : 'is-flat') }}" data-kpi-change>
                            <span aria-hidden="true">{{ $y['change_percent'] > 0 ? '↑' : ($y['change_percent'] < 0 ? '↓' : '→') }}</span>
                            <strong>{{ $y['change_percent'] > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($y['change_percent'], 1), '0'), '.') }}%</strong> {{ $t('tiles.yield.vs') }}
                        </span>
                    @endif
                    <span class="mhd-tile-note">{{ $tile['note'] }}</span>
                </a>
            @endforeach
        </section>
        @endif

        @if ($actions !== [] || $showMap)
        <div @class(['mhd-grid', 'is-single' => $actions === [] || ! $showMap])>
            {{-- 2. Needs Action ("Kailangan ng Aksyon" in Filipino) --}}
            @if ($actions !== [])
            <section class="mtd-card mhd-actions" aria-labelledby="mhdActionsTitle" data-mao-actions>
                <div class="mtd-card-head">
                    <h2 id="mhdActionsTitle">{{ $t('actions.title') }}</h2>
                    @if ($waiting > 0)<span class="mtd-count">{{ $waiting }}</span>@endif
                </div>
                <ul class="mhd-action-list">
                    @foreach ($actions as $action)
                        <li @class(['mhd-action', 'is-waiting' => (int) $action['count'] > 0]) data-mao-action="{{ $action['key'] }}">
                            <strong class="mhd-action-count">{{ $action['count'] === null ? '—' : number_format($action['count']) }}</strong>
                            <span class="mhd-action-label">{{ $t('actions.'.$action['key']) }}</span>
                            <a class="mtd-btn @if ((int) $action['count'] === 0) is-ghost @endif" href="{{ $action['href'] }}">{{ $t('actions.'.strtolower($action['action'])) }}</a>
                        </li>
                    @endforeach
                </ul>
                @if ($waiting === 0)
                    <p class="mtd-allgood">@include('dashboards.mao.icon', ['name' => 'check']) {{ $t('actions.none') }}</p>
                @endif
            </section>
            @endif

            {{-- 3. Small Heat Map preview: the whole card opens the Heat Map. Not shown before the first saved forecast. --}}
            @if ($showMap)
            <section class="mtd-card mhd-map" aria-labelledby="mhdMapTitle">
                <div class="mtd-card-head">
                    <div>
                        <h2 id="mhdMapTitle">{{ $t('map.title') }}</h2>
                        <p class="mtd-sub">{{ $t('map.sub') }}</p>
                    </div>
                    <a class="mtd-link" href="{{ route('heat-map.index') }}">{{ $t('map.open') }}</a>
                </div>
                <a class="mhd-map-frame" href="{{ route('heat-map.index') }}" aria-label="{{ $t('map.open_label') }}" data-heat-map-preview>
                    <div id="heatMapPreviewCanvas" class="mhd-map-canvas" data-boundary-url="{{ url('/api/boundaries/lian-barangays') }}"></div>
                    <script type="application/json" data-heat-map-preview-levels>@json(['levels' => $risk['levels'], 'colors' => \App\Services\Risk\HeatMapPanel::COLORS])</script>
                </a>
                <p class="mhd-legend">
                    @foreach (['low', 'moderate', 'high'] as $level)
                        <span><span class="mhd-dot" style="--mhd-color: {{ \App\Services\Risk\HeatMapPanel::COLORS[$level] }}"></span>{{ $t('map.'.$level) }}</span>
                    @endforeach
                    <x-updated-at :at="$risk['updated_at']" />
                </p>
            </section>
            @endif
        </div>
        @endif

        {{-- 4. The charts, all from saved records (MaoHomeService): no provider is called and no model is run. --}}
        @if (in_array(true, $charts, true))
        <div class="mhd-charts">
            @if ($charts['yield'])
            <section class="mtd-card" aria-labelledby="mhdYieldTitle" data-mao-yield>
                <div class="mtd-card-head">
                    <div>
                        <h2 id="mhdYieldTitle">{{ $t('charts.yield.title', ['from' => \App\Services\Analytics\MaoHomeService::CHART_FROM_YEAR, 'to' => \App\Services\Analytics\MaoHomeService::CHART_TO_YEAR]) }}</h2>
                        <p class="mtd-sub">{{ $t('charts.yield.sub') }}</p>
                    </div>
                </div>
                <div class="mtd-chart">
                    <canvas id="riceYieldTrendChart" data-yield="{{ json_encode($chart) }}"
                        data-chart-source="{{ $chart['source'] }}" data-chart-type="historical" data-chart-unit="t/ha" data-chart-info-style="icon"
                        data-chart-note="{{ $t('charts.yield.note') }}"
                        aria-label="{{ $t('charts.yield.aria', ['from' => $chart['labels'][0], 'to' => end($chart['labels'])]) }}"></canvas>
                </div>
            </section>
            @endif

            @if ($charts['climate'])
            <section class="mtd-card" aria-labelledby="mhdClimateTitle" data-mao-climate>
                <div class="mtd-card-head">
                    <div>
                        <h2 id="mhdClimateTitle">{{ $t('charts.climate.title') }}</h2>
                        <p class="mtd-sub">{{ $t('charts.climate.sub') }}</p>
                    </div>
                </div>
                <div class="mtd-chart">
                    <canvas id="maoClimateChart" data-chart="{{ json_encode($climate) }}" data-chart-type="historical" data-chart-info-style="icon"
                        aria-label="{{ $t('charts.climate.aria', ['from' => $climate['labels'][0], 'to' => end($climate['labels'])]) }}"></canvas>
                </div>
            </section>
            @endif

            @if ($charts['impact'])
            <section class="mtd-card" aria-labelledby="mhdImpactTitle" data-mao-impact>
                <div class="mtd-card-head">
                    <div>
                        <h2 id="mhdImpactTitle">{{ $t('charts.impact.title') }}</h2>
                        <p class="mtd-sub">{{ $t('charts.impact.sub') }}</p>
                    </div>
                </div>
                <div class="mtd-chart">
                    <canvas id="maoImpactChart" data-chart="{{ json_encode($impact) }}" data-chart-type="historical" data-chart-info-style="icon"
                        aria-label="{{ $t('charts.impact.aria') }}"></canvas>
                </div>
                <p class="mhd-reading" data-mao-impact-reading>{{ $impactReading }}</p>
            </section>
            @endif

            @if ($charts['planted'])
            <section class="mtd-card" aria-labelledby="mhdPlantedTitle" data-mao-planted>
                <div class="mtd-card-head">
                    <div>
                        <h2 id="mhdPlantedTitle">{{ $t('charts.planted.title') }}</h2>
                        <p class="mtd-sub">{{ $t('charts.planted.sub', ['season' => $season]) }}</p>
                    </div>
                </div>
                <div class="mtd-chart">
                    <canvas id="maoPlantedChart" data-chart="{{ json_encode($mao['planted']) }}" data-chart-type="recorded" data-chart-unit="ha" data-chart-info-style="icon"
                        aria-label="{{ $t('charts.planted.aria') }}"></canvas>
                </div>
            </section>
            @endif

            {{-- MAO Dashboard – Naicel (2026-10-08): where the registered farmers are. One bar per barangay that has any. --}}
            @if ($charts['farmers'])
            <section class="mtd-card" aria-labelledby="mhdFarmersTitle" data-mao-farmers>
                <div class="mtd-card-head">
                    <div>
                        <h2 id="mhdFarmersTitle">{{ $t('charts.farmers.title') }}</h2>
                        <p class="mtd-sub">{{ trans_choice('mao.dashboard.charts.farmers.sub', (int) $byBarangay['counted'], ['count' => number_format($byBarangay['counted'])]) }}</p>
                    </div>
                </div>
                <div class="mtd-chart" style="height: {{ max(320, count($byBarangay['labels']) * 30 + 90) }}px">
                    <canvas id="maoFarmersChart" data-chart="{{ json_encode($byBarangay) }}" data-chart-type="recorded" data-chart-info-style="icon"
                        aria-label="{{ $t('charts.farmers.aria') }}"></canvas>
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
                        <h2 id="mhdSurveyTitle">{{ $t('survey.accuracy_title') }}</h2>
                        <p class="mtd-sub">{{ $t('survey.accuracy_sub') }}</p>
                    </div>
                </div>
                <div class="mtd-table-wrap">
                    <table class="mtd-table">
                        <thead><tr><th>{{ $t('survey.month') }}</th><th>{{ $t('survey.answers') }}</th><th>{{ $t('survey.correct') }}</th><th>{{ $t('survey.partly') }}</th><th>{{ $t('survey.wrong') }}</th></tr></thead>
                        <tbody>
                            @foreach ($survey['accuracy'] as $row)
                                <tr><td>{{ isset($row['ym']) ? $monthText($row['ym'], 'F Y') : $row['month'] }}</td><td>{{ $row['reports'] }}</td><td><strong>{{ $row['tama'] }}%</strong></td><td>{{ $row['medyo'] }}%</td><td>{{ $row['mali'] }}%</td></tr>
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
                        <h2 id="mhdSurveyYieldTitle">{{ $t('survey.yield_title') }}</h2>
                        <p class="mtd-sub">{{ $t('survey.yield_sub') }}</p>
                    </div>
                </div>
                <p class="mhd-reading mhd-reading-flat">{{ trans_choice('mao.dashboard.survey.summary', (int) $survey['yield']['count'], ['predicted' => number_format($survey['yield']['predicted'], 2), 'actual' => number_format($survey['yield']['actual'], 2), 'count' => $survey['yield']['count']]) }}</p>
                <div class="mtd-table-wrap">
                    <table class="mtd-table">
                        <thead><tr><th>{{ $t('survey.harvested') }}</th><th>{{ $t('survey.barangay') }}</th><th>{{ $t('survey.predicted') }}</th><th>{{ $t('survey.actual') }}</th><th>{{ $t('survey.difference') }}</th></tr></thead>
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

    {{-- The charts' words in the page's language. --}}
    <script type="application/json" id="mhdChartText">@json($text)</script>
    <script>
        (() => {
            // The language selector: sent as soon as a language is picked (the form also works without scripts).
            const language = document.querySelector('[data-mao-language] select');
            if (language) language.addEventListener('change', () => language.form.submit());

            // Large, plain labels: this page is read by older staff.
            const FONT = { size: 14 };
            const T = JSON.parse(document.getElementById('mhdChartText')?.textContent || '{}');
            const axis = (text, extra = {}) => ({ title: { display: true, text, font: { size: 14, weight: '600' } }, ticks: { font: FONT }, grid: { color: '#eef2ef' }, ...extra });
            const read = (id, attr = 'chart') => { const el = document.getElementById(id); return el ? [el, JSON.parse(el.dataset[attr] || '{}')] : [null, {}]; };
            const base = (legend) => ({ responsive: true, maintainAspectRatio: false, plugins: { legend: { display: legend, position: 'bottom', labels: { font: FONT, usePointStyle: true } } } });

            const start = async () => {
                try { await window.iClimateVisualsReady; } catch (e) { /* no chart library: the headings and notes stay */ }
                if (!window.Chart) return;

                const [yieldEl, y] = read('riceYieldTrendChart', 'yield');
                if (yieldEl) new window.Chart(yieldEl, {
                    type: 'bar',
                    data: { labels: y.labels || [], datasets: [{ label: T.yield.dataset, data: y.values || [], backgroundColor: '#0f5132', borderRadius: 4, maxBarThickness: 64 }] },
                    options: { ...base(false), scales: { y: axis(T.yield.axis, { beginAtZero: true }), x: { grid: { display: false }, ticks: { font: FONT } } },
                        plugins: { legend: { display: false }, tooltip: { callbacks: {
                            title: (items) => T.yield.year + ' ' + items[0]?.label,
                            label: (c) => T.yield.value + ': ' + (c.parsed.y != null ? c.parsed.y.toFixed(2) : 'n/a') + ' t/ha',
                            afterLabel: (c) => T.yield.records + ': ' + ((y.months || [])[c.dataIndex] || 0) + ' ' + T.yield.months,
                        } } } },
                });

                const [climateEl, c] = read('maoClimateChart');
                if (climateEl) new window.Chart(climateEl, {
                    type: 'line',
                    data: { labels: c.labels || [], datasets: [
                        { label: T.climate.rain, data: c.rainfall || [], yAxisID: 'rain', borderColor: '#1f6fb2', backgroundColor: '#1f6fb2', borderWidth: 3, tension: .3, pointRadius: 4 },
                        { label: T.climate.temp, data: c.temperature || [], yAxisID: 'temp', borderColor: '#c2570c', backgroundColor: '#c2570c', borderWidth: 3, borderDash: [6, 4], tension: .3, pointRadius: 4, pointStyle: 'rectRot' },
                    ] },
                    options: { ...base(true), interaction: { mode: 'index', intersect: false },
                        scales: { rain: axis(T.climate.rain, { position: 'left', beginAtZero: true }), temp: axis(T.climate.temp, { position: 'right', grid: { drawOnChartArea: false } }), x: { grid: { display: false }, ticks: { font: FONT, maxRotation: 0, autoSkip: true } } } },
                });

                const [impactEl, i] = read('maoImpactChart');
                if (impactEl) new window.Chart(impactEl, {
                    type: 'line',   // dots only (showLine: false): the scatter controller is not in this app's Chart.js bundle
                    data: { datasets: [{ label: T.impact.dataset, data: i.points || [], showLine: false, backgroundColor: '#0f5132', borderColor: '#0f5132', pointRadius: 7, pointHoverRadius: 9 }] },
                    options: { ...base(false), scales: { x: axis(T.impact.x, { type: 'linear' }), y: axis(T.impact.y) },
                        plugins: { legend: { display: false }, tooltip: { callbacks: { label: (p) => p.raw.label + ': ' + p.raw.x + ' ' + T.impact.rain + ', ' + p.raw.y.toFixed(2) + ' t/ha' } } } },
                });

                const [plantedEl, p] = read('maoPlantedChart');
                if (plantedEl) new window.Chart(plantedEl, {
                    type: 'bar',
                    data: { labels: p.labels || [], datasets: [{ label: T.planted.dataset, data: p.values || [], backgroundColor: '#198754', borderRadius: 4, maxBarThickness: 48 }] },
                    options: { ...base(false), indexAxis: 'y', scales: { x: axis(T.planted.axis, { beginAtZero: true }), y: { grid: { display: false }, ticks: { font: FONT } } },
                        plugins: { legend: { display: false }, tooltip: { callbacks: { label: (b) => b.parsed.x + ' ' + T.planted.tip } } } },
                });

                // MAO Dashboard – Naicel (2026-10-08): registered farmers per barangay (whole numbers only).
                const [farmersEl, f] = read('maoFarmersChart');
                if (farmersEl) new window.Chart(farmersEl, {
                    type: 'bar',
                    data: { labels: f.labels || [], datasets: [{ label: T.farmers.dataset, data: f.values || [], backgroundColor: '#1f6fb2', borderRadius: 4, maxBarThickness: 28 }] },
                    options: { ...base(false), indexAxis: 'y', scales: { x: axis(T.farmers.axis, { beginAtZero: true, ticks: { font: FONT, precision: 0 } }), y: { grid: { display: false }, ticks: { font: FONT, autoSkip: false } } },
                        plugins: { legend: { display: false }, tooltip: { callbacks: { label: (b) => b.parsed.x + ' ' + (b.parsed.x === 1 ? T.farmers.one : T.farmers.many) } } } },
                });
            };
            document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();
        })();
    </script>
</x-app-layout>
