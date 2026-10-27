<x-app-layout>
    <x-slot:title>{{ __('mao.dashboard.title') }}</x-slot:title>
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

        // Panel revision 4.1: all registered farmer accounts; labelled "(filtered)" when limited to the MAO user's barangays.
        $fk = $mao['farmer_kpi'] ?? null;
        $farmersFiltered = (bool) ($fk['filtered'] ?? false);
        $farmersNote = $fk
            ? ($farmersFiltered
                ? $t('tiles.farmers.filtered_note', ['all' => $num($fk['all_total'])])
                : $t('tiles.farmers.note')).' · '.$t('tiles.farmers.breakdown', ['active' => $num($fk['active']), 'pending' => $num($fk['pending'])])
            : $t('tiles.farmers.note');
        $tiles = [
            ['key' => 'farmers', 'label' => $farmersFiltered ? $t('tiles.farmers.label_filtered') : $t('tiles.farmers.label'), 'icon' => 'farmers', 'href' => route('farmer-profiles.index'),
                'value' => $num($fk['total'] ?? $mao['farmers']), 'unit' => null, 'note' => $farmersNote],
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
        // Panel revisions 3.1 / 3.2 / 4.2: the full series; the Year range / Season filter is applied in the browser.
        $series = $mao['yield_series'] ?? ['state' => 'nodata'];
        $rainYield = $mao['rain_yield'] ?? ['state' => 'nodata', 'periods' => []];
        $accuracy = $mao['accuracy'] ?? ['state' => 'nodata'];
        $charts = [
            'yield' => $chart['state'] === 'ok' && ($series['state'] ?? '') === 'ok',
            'climate' => $mao['climate']['state'] === 'ok',
            'impact' => $mao['impact']['state'] === 'ok' && ($rainYield['state'] ?? '') === 'ok',
            'planted' => $mao['planted']['state'] === 'ok',
            'farmers' => $byBarangay['state'] === 'ok',
            'accuracy' => ($accuracy['state'] ?? '') === 'ok',
        ];
        $filterYears = $series['years'] ?? [];
        $filterFrom = $chartFilter['from'] ?? null;
        $filterTo = $chartFilter['to'] ?? null;
        // The panel's "Rice Yield by Year (2020–2025)" until the MAO picks another range.
        $rangeFrom = $filterFrom ?? \App\Services\Analytics\MaoHomeService::CHART_FROM_YEAR;
        $rangeTo = $filterTo ?? \App\Services\Analytics\MaoHomeService::CHART_TO_YEAR;
        $filterSeason = $chartFilter['season'] ?? 'all';

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
            'impact' => ['dataset' => $t('charts.impact.dataset'), 'x' => $t('charts.impact.axis_x'), 'y' => $t('charts.impact.axis_y'), 'rain' => $t('charts.impact.tip_rain'),
                'rainSeries' => $t('charts.impact.rain'), 'yieldSeries' => $t('charts.impact.yield'), 'axisRain' => $t('charts.impact.axis_rain'),
                'more' => $t('charts.impact.more'), 'less' => $t('charts.impact.less'), 'none' => $t('charts.impact.none'), 'based' => $t('charts.impact.based')],
            // Panel revisions 3.1 / 4.2
            'filter' => ['invalid' => $t('filter.invalid'), 'avg' => $t('filter.summary_avg'), 'high' => $t('filter.summary_high'), 'low' => $t('filter.summary_low'), 'change' => $t('filter.summary_change'),
                'title' => $t('charts.yield.title', ['from' => ':from', 'to' => ':to'])],
            'accuracy' => ['predicted' => $t('charts.accuracy.predicted'), 'actual' => $t('charts.accuracy.actual'), 'none' => $t('charts.accuracy.none_in_range'),
                'trained' => $t('charts.accuracy.trained'), 'heldOut' => $t('charts.accuracy.held_out'), 'error' => $t('charts.accuracy.error')],
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
                {{-- Panel revision 4.4 B: which barangays the figures below cover. --}}
                @if (! empty($mao['scope']['filtered']))
                    <p class="scope-pill is-filtered" data-mao-scope="filtered">{{ $t('scope.filtered', ['barangays' => implode(', ', $mao['scope']['barangays'])]) }}</p>
                @else
                    <p class="scope-pill" data-mao-scope="all">{{ $t('scope.municipal') }}</p>
                @endif
            </div>
            <div class="mtd-tools">
                <x-updated-at :at="$mao['generated_at']" />
            </div>
        </header>
        {{-- Panel revision 1.4: find a farmer as you type, from the dashboard. --}}
        @include('partials.live-search', ['url' => route('farmers.search'), 'id' => 'maoFarmerFinder', 'label' => 'Find a farmer', 'placeholder' => 'Name, farmer ID, barangay or organization', 'class' => 'mhd-finder'])

        {{-- 1. KPI tiles: up to eight, four in a row on a wide screen --}}
        @if ($tiles !== [])
        <section class="mhd-tiles" aria-label="{{ $t('key_figures') }}" data-mao-kpis style="--mhd-tile-cols: {{ min(4, count($tiles)) }}">
            @foreach ($tiles as $tile)
                <a class="mhd-tile" href="{{ $tile['href'] }}" data-kpi="{{ $tile['key'] }}">
                    <span class="mhd-tile-label">@include('dashboards.mao.icon', ['name' => $tile['icon']]){{ $tile['label'] }}<i class="ic-go" aria-hidden="true"></i></span>
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
                    <script type="application/json" data-heat-map-preview-levels>@json(['levels' => $risk['levels'], 'colors' => \App\Services\Risk\HeatMapPanel::colors()])</script>
                </a>
                <p class="mhd-legend">
                    @foreach (['low', 'moderate', 'high', 'severe'] as $level)   {{-- panel revision 2.1 --}}
                        <span><span class="mhd-dot" style="--mhd-color: {{ \App\Services\Risk\HeatMapPanel::colors()[$level] }}"></span>{{ $t('map.'.$level) }}</span>
                    @endforeach
                    <x-updated-at :at="$risk['updated_at']" />
                </p>
            </section>
            @endif
        </div>
        @endif

        {{-- Panel revisions 3.1 / 3.2 / 4.2: one Year range and Season filter for the yield, rain and prediction charts.
             Works without scripts (a GET form); with scripts the charts and their totals change at once. --}}
        @if ($charts['yield'] || $charts['impact'] || $charts['accuracy'])
        <form class="mtd-card chart-filter" method="GET" action="{{ route('mao.dashboard') }}" data-chart-filter
              data-years="{{ json_encode($filterYears) }}" aria-label="{{ $t('filter.title') }}">
            <div class="chart-filter-fields">
                <label>
                    <span>{{ $t('filter.from') }}</span>
                    <select name="from" data-filter-from>
                        @foreach ($filterYears as $yearOption)<option value="{{ $yearOption }}" @selected((int) $rangeFrom === (int) $yearOption)>{{ $yearOption }}</option>@endforeach
                    </select>
                </label>
                <label>
                    <span>{{ $t('filter.to') }}</span>
                    <select name="to" data-filter-to>
                        @foreach ($filterYears as $yearOption)<option value="{{ $yearOption }}" @selected((int) $rangeTo === (int) $yearOption)>{{ $yearOption }}</option>@endforeach
                    </select>
                </label>
                <label>
                    <span>{{ $t('filter.season') }}</span>
                    <select name="season" data-filter-season>
                        <option value="all" @selected($filterSeason === 'all')>{{ $t('filter.all_seasons') }}</option>
                        <option value="Wet" @selected($filterSeason === 'Wet')>{{ $t('filter.wet') }}</option>
                        <option value="Dry" @selected($filterSeason === 'Dry')>{{ $t('filter.dry') }}</option>
                    </select>
                </label>
                <noscript><button type="submit" class="mtd-btn">{{ $t('filter.apply') }}</button></noscript>
            </div>
            <p class="chart-filter-error" role="alert" data-filter-error @if (empty($chartFilter['error'])) hidden @endif>{{ $chartFilter['error'] ?? '' }}</p>
        </form>
        @endif

        {{-- 4. The charts, all from saved records (MaoHomeService): no provider is called and no model is run. --}}
        @if (in_array(true, $charts, true))
        <div class="mhd-charts">
            {{-- Dashboard redesign – Naicel (2026-10-09): this week's risk rating of the barangays as one half-donut
                 (the same ratings as the Heat Map preview and the High-risk tile). --}}
            @if ($showMap)
            @php
                $riskCounts = array_count_values(array_map('strval', $risk['levels']));
                $riskRated = array_sum(array_intersect_key($riskCounts, ['low' => 1, 'moderate' => 1, 'high' => 1, 'severe' => 1]));
                $riskSegments = array_map(fn (string $level): array => ['value' => (int) ($riskCounts[$level] ?? 0), 'color' => \App\Services\Risk\HeatMapPanel::colors()[$level],
                    'label' => $t('map.'.$level).': '.(int) ($riskCounts[$level] ?? 0)], ['low', 'moderate', 'high', 'severe']);
                $riskLow = $riskRated > 0 ? (int) round(($riskCounts['low'] ?? 0) / $riskRated * 100) : 0;
            @endphp
            @if ($riskRated > 0)
            <section class="mtd-card mhd-gauge-card" aria-labelledby="mhdRiskGaugeTitle" data-mao-risk-gauge>
                <div class="mtd-card-head">
                    <div>
                        <h2 id="mhdRiskGaugeTitle">{{ $t('charts.risk.title') }}</h2>
                        <p class="mtd-sub">{{ $t('charts.risk.sub', ['count' => $riskRated]) }}</p>
                    </div>
                </div>
                @include('partials.half-donut', ['segments' => $riskSegments, 'big' => $riskLow.'%', 'small' => $t('charts.risk.center'),
                    'label' => $t('charts.risk.title').': '.implode(', ', array_column($riskSegments, 'label'))])
            </section>
            @endif
            @endif

            @if ($charts['yield'])
            <section class="mtd-card" aria-labelledby="mhdYieldTitle" data-mao-yield>
                <div class="mtd-card-head">
                    <div>
                        <h2 id="mhdYieldTitle" data-yield-title>{{ $t('charts.yield.title', ['from' => $rangeFrom, 'to' => $rangeTo]) }}</h2>
                        <p class="mtd-sub">{{ $t('charts.yield.sub') }} · {{ $t('filter.municipal') }}</p>
                    </div>
                </div>
                {{-- Panel revision 3.1: totals for the selected years (recomputed in the browser when the range changes). --}}
                <dl class="range-summary" data-yield-summary aria-live="polite">
                    <div><dt>{{ $t('filter.summary_avg') }}</dt><dd data-sum="avg">—</dd></div>
                    <div><dt>{{ $t('filter.summary_high') }}</dt><dd data-sum="high">—</dd></div>
                    <div><dt>{{ $t('filter.summary_low') }}</dt><dd data-sum="low">—</dd></div>
                    <div><dt>{{ $t('filter.summary_change') }}</dt><dd data-sum="change">—</dd></div>
                </dl>
                <div class="mtd-chart">
                    <canvas id="riceYieldTrendChart" data-yield="{{ json_encode($chart) }}" data-series="{{ json_encode($series) }}"
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
                {{-- Panel revision 3.2: rainfall (bars, mm, left axis) and yield (line, t/ha, right axis) per rice season. --}}
                <div class="mtd-chart">
                    <canvas id="maoImpactChart" data-chart="{{ json_encode($rainYield) }}" data-chart-type="historical" data-chart-info-style="icon"
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

        {{-- Panel revision 4.2: predicted vs actual yield for the whole municipality of Lian, with the model's error. --}}
        @if ($charts['accuracy'])
        @php
            $acc = $accuracy;
            $pct = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 1), '0'), '.').'%';
        @endphp
        <section class="mtd-card accuracy-card" aria-labelledby="mhdAccuracyTitle" data-mao-accuracy>
            <div class="mtd-card-head">
                <div>
                    <h2 id="mhdAccuracyTitle">{{ $t('charts.accuracy.title') }}</h2>
                    <p class="mtd-sub">{{ $t('charts.accuracy.sub') }}</p>
                </div>
                <div class="seg" role="group" aria-label="{{ $t('charts.accuracy.by') }}">
                    <button type="button" data-accuracy-by="year" aria-pressed="true">{{ $t('charts.accuracy.by_year') }}</button>
                    <button type="button" data-accuracy-by="season" aria-pressed="false">{{ $t('charts.accuracy.by_season') }}</button>
                </div>
            </div>
            <dl class="range-summary" data-accuracy-summary aria-live="polite">
                <div><dt>{{ $t('charts.accuracy.predicted') }}</dt><dd data-acc="predicted">{{ number_format($acc['overall']['predicted'] ?? 0, 2) }}</dd></div>
                <div><dt>{{ $t('charts.accuracy.actual') }}</dt><dd data-acc="actual">{{ number_format($acc['overall']['actual'] ?? 0, 2) }}</dd></div>
                <div><dt>{{ $t('charts.accuracy.difference') }}</dt><dd data-acc="difference">{{ number_format($acc['overall']['difference'] ?? 0, 2) }} t/ha</dd></div>
                <div><dt>{{ $t('charts.accuracy.mape') }}</dt><dd data-acc="mape">{{ $pct($acc['overall']['mape'] ?? null) }}</dd></div>
                <div><dt>{{ $t('charts.accuracy.accuracy') }}</dt><dd data-acc="accuracy">{{ $pct($acc['overall']['accuracy'] ?? null) }}</dd></div>
            </dl>
            <div class="mtd-chart">
                <canvas id="maoAccuracyChart" data-chart="{{ json_encode(['months' => $acc['months']]) }}" data-chart-type="historical" data-chart-unit="t/ha" data-chart-info-style="icon"
                    aria-label="{{ $t('charts.accuracy.aria') }}"></canvas>
            </div>
            @if (! empty($acc['test']))
                <p class="mhd-reading" data-accuracy-test>{{ $t('charts.accuracy.test_note', ['mape' => rtrim(rtrim(number_format($acc['test']['mape'], 1), '0'), '.'), 'count' => $acc['test']['count']]) }} {{ $t('charts.accuracy.train_note') }}</p>
            @endif
            @if (! empty($acc['model']))<p class="mtd-sub">{{ $t('charts.accuracy.model', ['model' => $acc['model']]) }}</p>@endif

            {{-- The rows behind the chart, for the selected years (filled by the page script; also readable without it). --}}
            <div class="mtd-table-wrap">
                <table class="mtd-table is-stack" data-accuracy-table>
                    <thead><tr><th>{{ $t('charts.accuracy.period') }}</th><th>{{ $t('charts.accuracy.predicted') }}</th><th>{{ $t('charts.accuracy.actual') }}</th><th>{{ $t('survey.difference') }}</th><th>{{ $t('charts.accuracy.error') }}</th><th>{{ $t('charts.accuracy.months') }}</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>

            {{-- Optional drill-down: the same comparison per barangay, from farmers' own recorded harvests. --}}
            <details class="accuracy-drill" data-accuracy-barangays>
                <summary>{{ $t('charts.accuracy.barangay_title') }}</summary>
                @if (empty($acc['barangays']))
                    <p class="mtd-sub">{{ $t('charts.accuracy.barangay_none') }}</p>
                @else
                    <div class="mtd-table-wrap">
                        <table class="mtd-table is-stack">
                            <thead><tr><th>{{ $t('charts.accuracy.barangay') }}</th><th>{{ $t('charts.accuracy.predicted') }}</th><th>{{ $t('charts.accuracy.actual') }}</th><th>{{ $t('survey.difference') }}</th><th>MAPE</th><th>{{ $t('charts.accuracy.count') }}</th></tr></thead>
                            <tbody>
                                @foreach ($acc['barangays'] as $row)
                                    <tr>
                                        <td data-label="{{ $t('charts.accuracy.barangay') }}">{{ $row['barangay'] }}</td>
                                        <td data-label="{{ $t('charts.accuracy.predicted') }}">{{ number_format($row['predicted'], 2) }}</td>
                                        <td data-label="{{ $t('charts.accuracy.actual') }}">{{ number_format($row['actual'], 2) }}</td>
                                        <td data-label="{{ $t('survey.difference') }}">{{ $row['difference'] > 0 ? '+' : '' }}{{ number_format($row['difference'], 2) }}</td>
                                        <td data-label="MAPE">{{ $pct($row['mape']) }}</td>
                                        <td data-label="{{ $t('charts.accuracy.count') }}">{{ $row['count'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </details>
        </section>
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
            const FONT = { size: 18 };
            const T = JSON.parse(document.getElementById('mhdChartText')?.textContent || '{}');
            const axis = (text, extra = {}) => ({ title: { display: true, text, font: { size: 18, weight: '600' } }, ticks: { font: FONT }, grid: { color: '#eef2ef' }, ...extra });
            const read = (id, attr = 'chart') => { const el = document.getElementById(id); return el ? [el, JSON.parse(el.dataset[attr] || '{}')] : [null, {}]; };
            const base = (legend) => ({ responsive: true, maintainAspectRatio: false, plugins: { legend: { display: legend, position: 'bottom', labels: { font: FONT, usePointStyle: true } } } });

            const start = async () => {
                try { await window.iClimateVisualsReady; } catch (e) { /* no chart library: the headings and notes stay */ }
                if (!window.Chart) return;

                // ---------------------------------------------------------------- panel revisions 3.1 / 3.2 / 4.2
                // One Year range / Season filter (the form above the charts) redraws the yield, rain and prediction
                // charts and their totals at once. All yields are t/ha.
                const filterForm = document.querySelector('[data-chart-filter]');
                const allYears = filterForm ? JSON.parse(filterForm.dataset.years || '[]') : [];
                const fromSel = filterForm?.querySelector('[data-filter-from]');
                const toSel = filterForm?.querySelector('[data-filter-to]');
                const seasonSel = filterForm?.querySelector('[data-filter-season]');
                const errorBox = filterForm?.querySelector('[data-filter-error]');
                const f2 = (v) => (v == null || Number.isNaN(v) ? '—' : Number(v).toFixed(2));
                const avg = (list) => { const v = list.filter((x) => x != null); return v.length ? v.reduce((a, b) => a + b, 0) / v.length : null; };
                const state = () => ({
                    from: Number(fromSel?.value ?? allYears[0]),
                    to: Number(toSel?.value ?? allYears[allYears.length - 1]),
                    season: seasonSel?.value || 'all',
                });

                const [yieldEl, y] = read('riceYieldTrendChart', 'yield');
                const ys = yieldEl ? JSON.parse(yieldEl.dataset.series || '{}') : {};
                const yieldChart = yieldEl ? new window.Chart(yieldEl, {
                    type: 'bar',
                    data: { labels: [], datasets: [{ label: T.yield.dataset, data: [], backgroundColor: '#2E7D32', borderRadius: 6, borderSkipped: false, maxBarThickness: 46 }] },
                    options: { ...base(false), scales: { y: axis(T.yield.axis, { beginAtZero: true }), x: { grid: { display: false }, ticks: { font: FONT } } },
                        plugins: { legend: { display: false }, tooltip: { callbacks: {
                            title: (items) => T.yield.year + ' ' + items[0]?.label,
                            label: (c) => T.yield.value + ': ' + (c.parsed.y != null ? c.parsed.y.toFixed(2) : 'n/a') + ' t/ha',
                            afterLabel: (c) => T.yield.records + ': ' + (yieldChart.$months?.[c.dataIndex] || 0) + ' ' + T.yield.months,
                        } } } },
                }) : null;

                const [impactEl, ry] = read('maoImpactChart');
                const impactChart = impactEl ? new window.Chart(impactEl, {
                    type: 'bar',
                    data: { labels: [], datasets: [
                        { type: 'bar', label: T.impact.rainSeries, data: [], yAxisID: 'rain', backgroundColor: 'rgba(0, 56, 168, .55)', borderColor: '#0038A8', borderWidth: 1, borderRadius: 4, maxBarThickness: 34, order: 2 },
                        { type: 'line', label: T.impact.yieldSeries, data: [], yAxisID: 'yield', borderColor: '#2E7D32', backgroundColor: '#2E7D32', borderWidth: 3, tension: .3, pointRadius: 5, pointHoverRadius: 7, order: 1 },
                    ] },
                    options: { ...base(true), interaction: { mode: 'index', intersect: false },
                        scales: {
                            rain: axis(T.impact.axisRain, { position: 'left', beginAtZero: true }),
                            yield: axis(T.yield.axis, { position: 'right', grid: { drawOnChartArea: false } }),
                            x: { grid: { display: false }, ticks: { font: FONT, maxRotation: 0, autoSkip: true } },
                        },
                        plugins: { legend: { display: true, position: 'top', labels: { font: FONT, usePointStyle: true } }, tooltip: { callbacks: {
                            label: (c) => c.dataset.yAxisID === 'rain' ? `${T.impact.rainSeries}: ${c.parsed.y.toFixed(1)} mm` : `${T.impact.yieldSeries}: ${c.parsed.y.toFixed(2)} t/ha`,
                        } } } },
                }) : null;

                const [accEl, acc] = read('maoAccuracyChart');
                const accTable = document.querySelector('[data-accuracy-table] tbody');
                let accBy = 'year';
                const accChart = accEl ? new window.Chart(accEl, {
                    type: 'bar',
                    data: { labels: [], datasets: [
                        { type: 'bar', label: T.accuracy.actual, data: [], backgroundColor: 'rgba(46, 125, 50, .75)', borderRadius: 4, maxBarThickness: 32, order: 2 },
                        { type: 'line', label: T.accuracy.predicted, data: [], borderColor: '#0038A8', backgroundColor: '#0038A8', borderWidth: 3, borderDash: [6, 4], pointRadius: 5, pointStyle: 'rectRot', order: 1 },
                    ] },
                    options: { ...base(true), interaction: { mode: 'index', intersect: false },
                        scales: { y: axis(T.yield.axis, { beginAtZero: false }), x: { grid: { display: false }, ticks: { font: FONT, maxRotation: 0, autoSkip: true } } },
                        plugins: { legend: { display: true, position: 'bottom', labels: { font: FONT, usePointStyle: true } }, tooltip: { callbacks: {
                            label: (c) => `${c.dataset.label}: ${c.parsed.y.toFixed(2)} t/ha`,
                            afterBody: (items) => {
                                const row = accChart.$rows?.[items[0]?.dataIndex];
                                return row ? [`${T.accuracy.error}: ${row.diff > 0 ? '+' : ''}${row.diff.toFixed(2)} t/ha (${row.ape.toFixed(1)}%)`] : [];
                            },
                        } } } },
                }) : null;

                const setSum = (box, key, text) => { const el = box?.querySelector(`[data-sum="${key}"], [data-acc="${key}"]`); if (el) el.textContent = text; };

                const apply = () => {
                    const s = state();
                    if (errorBox) {
                        const bad = s.from > s.to;
                        errorBox.hidden = !bad;
                        if (bad) { errorBox.textContent = T.filter.invalid; return; }   // keep the last valid charts
                    }
                    const inRange = (year) => year >= s.from && year <= s.to;

                    // 3.1 Rice Yield by Year: the selected years, all months or one season.
                    if (yieldChart) {
                        const key = s.season === 'Wet' ? 'wet' : s.season === 'Dry' ? 'dry' : 'all';
                        const idx = (ys.years || []).map((yr, i) => (inRange(yr) ? i : -1)).filter((i) => i >= 0);
                        const labels = idx.map((i) => String(ys.years[i]));
                        const values = idx.map((i) => (ys[key] || [])[i]);
                        yieldChart.data.labels = labels;
                        yieldChart.data.datasets[0].data = values;
                        yieldChart.$months = idx.map((i) => (ys.months || [])[i]);
                        yieldChart.update();
                        const title = document.querySelector('[data-yield-title]');
                        if (title) title.textContent = T.filter.title.replace(':from', s.from).replace(':to', s.to);
                        const box = document.querySelector('[data-yield-summary]');
                        const pairs = labels.map((l, i) => [l, values[i]]).filter((p) => p[1] != null);
                        const hi = pairs.reduce((m, p) => (!m || p[1] > m[1] ? p : m), null);
                        const lo = pairs.reduce((m, p) => (!m || p[1] < m[1] ? p : m), null);
                        const first = pairs[0]?.[1];
                        const last = pairs[pairs.length - 1]?.[1];
                        setSum(box, 'avg', pairs.length ? f2(avg(pairs.map((p) => p[1]))) + ' t/ha' : '—');
                        setSum(box, 'high', hi ? `${f2(hi[1])} t/ha (${hi[0]})` : '—');
                        setSum(box, 'low', lo ? `${f2(lo[1])} t/ha (${lo[0]})` : '—');
                        setSum(box, 'change', pairs.length > 1 && first ? `${last - first >= 0 ? '+' : ''}${f2(last - first)} t/ha (${(((last - first) / first) * 100).toFixed(1)}%)` : '—');
                    }

                    // 3.2 Weather Impact: rainfall bars and yield line per season, same range and season.
                    if (impactChart) {
                        const periods = (ry.periods || []).filter((p) => inRange(p.year) && (s.season === 'all' || p.season === s.season));
                        impactChart.data.labels = periods.map((p) => p.label);
                        impactChart.data.datasets[0].data = periods.map((p) => p.rain);
                        impactChart.data.datasets[1].data = periods.map((p) => p.yield);
                        impactChart.update();
                        const reading = document.querySelector('[data-mao-impact-reading]');
                        if (reading && periods.length >= 3) {
                            const mx = avg(periods.map((p) => p.rain)); const my = avg(periods.map((p) => p.yield));
                            let sxy = 0; let sx = 0; let sy = 0;
                            periods.forEach((p) => { sxy += (p.rain - mx) * (p.yield - my); sx += (p.rain - mx) ** 2; sy += (p.yield - my) ** 2; });
                            const r = sx > 0 && sy > 0 ? sxy / Math.sqrt(sx * sy) : 0;
                            reading.textContent = (r >= 0.3 ? T.impact.more : r <= -0.3 ? T.impact.less : T.impact.none) + ' ' + T.impact.based.replace(':count', periods.length);
                        } else if (reading) {
                            reading.textContent = T.impact.based.replace(':count', periods.length);
                        }
                    }

                    // 4.2 Predicted vs Actual for Lian, per year or season, with the error measures.
                    if (accChart) {
                        const months = (acc.months || []).filter((m) => inRange(m.season_year) && (s.season === 'all' || m.season === s.season));
                        const groups = new Map();
                        months.forEach((m) => {
                            const key = accBy === 'year' ? String(m.year) : `${m.season} ${m.season === 'Dry' ? `${m.season_year}–${m.season_year + 1}` : m.season_year}`;
                            if (!groups.has(key)) groups.set(key, []);
                            groups.get(key).push(m);
                        });
                        const rows = [...groups.entries()].map(([label, list]) => {
                            const p = avg(list.map((m) => m.predicted)); const a = avg(list.map((m) => m.actual));
                            const trained = list.every((m) => m.split === 'TRAIN'); const held = list.every((m) => m.split === 'TEST');
                            return { label, predicted: p, actual: a, diff: p - a, ape: Math.abs(p - a) / a * 100, count: list.length, split: trained ? T.accuracy.trained : held ? T.accuracy.heldOut : '' };
                        });
                        accChart.data.labels = rows.map((r) => r.label);
                        accChart.data.datasets[0].data = rows.map((r) => Number(r.actual.toFixed(2)));
                        accChart.data.datasets[1].data = rows.map((r) => Number(r.predicted.toFixed(2)));
                        accChart.$rows = rows;
                        accChart.update();

                        const box = document.querySelector('[data-accuracy-summary]');
                        const mape = months.length ? avg(months.map((m) => Math.abs(m.predicted - m.actual) / m.actual * 100)) : null;
                        setSum(box, 'predicted', months.length ? f2(avg(months.map((m) => m.predicted))) : '—');
                        setSum(box, 'actual', months.length ? f2(avg(months.map((m) => m.actual))) : '—');
                        setSum(box, 'difference', months.length ? `${f2(avg(months.map((m) => m.predicted - m.actual)))} t/ha` : '—');
                        setSum(box, 'mape', mape == null ? '—' : `${mape.toFixed(1)}%`);
                        setSum(box, 'accuracy', mape == null ? '—' : `${Math.max(0, 100 - mape).toFixed(1)}%`);
                        if (accTable) {
                            const head = [...document.querySelectorAll('[data-accuracy-table] thead th')].map((th) => th.textContent);
                            accTable.innerHTML = rows.length ? rows.map((r) => `<tr>
                                <td data-label="${head[0]}">${r.label}${r.split ? ` <small class="split-tag">${r.split}</small>` : ''}</td>
                                <td data-label="${head[1]}">${f2(r.predicted)}</td>
                                <td data-label="${head[2]}">${f2(r.actual)}</td>
                                <td data-label="${head[3]}">${r.diff > 0 ? '+' : ''}${f2(r.diff)}</td>
                                <td data-label="${head[4]}">${r.ape.toFixed(1)}%</td>
                                <td data-label="${head[5]}">${r.count}</td></tr>`).join('')
                                : `<tr><td colspan="6">${T.accuracy.none}</td></tr>`;
                        }
                    }

                    // Keep the choice in the address, so a refresh or a shared link opens the same view.
                    const url = new URL(window.location.href);
                    url.searchParams.set('from', s.from); url.searchParams.set('to', s.to);
                    s.season === 'all' ? url.searchParams.delete('season') : url.searchParams.set('season', s.season);
                    window.history.replaceState(null, '', url);
                };

                if (filterForm) {
                    [fromSel, toSel, seasonSel].forEach((el) => el && el.addEventListener('change', apply));
                    filterForm.addEventListener('submit', (e) => { e.preventDefault(); apply(); });
                }
                document.querySelectorAll('[data-accuracy-by]').forEach((btn) => btn.addEventListener('click', () => {
                    accBy = btn.dataset.accuracyBy;
                    document.querySelectorAll('[data-accuracy-by]').forEach((b) => b.setAttribute('aria-pressed', b === btn ? 'true' : 'false'));
                    apply();
                }));
                apply();
                // ---------------------------------------------------------------- end panel revisions

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

                // (Weather Impact is drawn above with the Year range / Season filter: panel revision 3.2.)

                const [plantedEl, p] = read('maoPlantedChart');
                if (plantedEl) new window.Chart(plantedEl, {
                    type: 'bar',
                    data: { labels: p.labels || [], datasets: [{ label: T.planted.dataset, data: p.values || [], backgroundColor: '#198754', borderRadius: 999, borderSkipped: false, maxBarThickness: 30 }] },
                    options: { ...base(false), indexAxis: 'y', scales: { x: axis(T.planted.axis, { beginAtZero: true }), y: { grid: { display: false }, ticks: { font: FONT } } },
                        plugins: { legend: { display: false }, tooltip: { callbacks: { label: (b) => b.parsed.x + ' ' + T.planted.tip } } } },
                });

                // MAO Dashboard – Naicel (2026-10-08): registered farmers per barangay (whole numbers only).
                const [farmersEl, f] = read('maoFarmersChart');
                if (farmersEl) new window.Chart(farmersEl, {
                    type: 'bar',
                    data: { labels: f.labels || [], datasets: [{ label: T.farmers.dataset, data: f.values || [], backgroundColor: '#2f9e6b', borderRadius: 999, borderSkipped: false, maxBarThickness: 22 }] },
                    options: { ...base(false), indexAxis: 'y', scales: { x: axis(T.farmers.axis, { beginAtZero: true, ticks: { font: FONT, precision: 0 } }), y: { grid: { display: false }, ticks: { font: FONT, autoSkip: false } } },
                        plugins: { legend: { display: false }, tooltip: { callbacks: { label: (b) => b.parsed.x + ' ' + (b.parsed.x === 1 ? T.farmers.one : T.farmers.many) } } } },
                });
            };
            document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();
        })();
    </script>
</x-app-layout>
