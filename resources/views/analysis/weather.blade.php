<x-app-layout>
    <x-slot:title>{{ __('weather_analysis.title') }}</x-slot:title>
    @vite('resources/css/pages/weather-predictions.css')
    @php
        $L = fn (string $key, array $replace = []) => __('weather_analysis.'.$key, $replace);
        $fmt = fn ($v, int $d = 1, string $unit = '') => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, $d), '0'), '.').$unit;
        $monthNames = $L('month_names');
        $shortMonths = array_slice($L('month_short'), 1, 12);
        $sage700 = config('palette.sage.700', '#2d6a4f');
        $sageMuted = '#6b7f74';
    @endphp

    @unless ($isFarmer)
    <div class="mfd itd itp">
        <x-shared.page-header :kicker="$L('kicker')" :title="$L('title')" :lede="$L('intro')" />
    @endunless
    <div class="fad wpx" data-weather-analysis>
        @include('partials.manuscript-tabs', ['group' => 'analysis', 'label' => 'Predictions and analysis'])
        @if ($isFarmer)
            <header class="fad-hero">
                <div class="fad-hero-text">
                    <span class="fad-kicker">{{ $L('kicker') }}</span>
                    <h1>{{ $L('title') }}</h1>
                    <p>{{ $L('intro') }}</p>
                </div>
            </header>
        @endif

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div class="btn-group btn-group-sm" role="group" aria-label="{{ $L('filters') }}" data-wa-range-toggle>
                @foreach (['this_year' => 'range_this_year', 'last_3_years' => 'range_last_3_years', 'all_years' => 'range_all_years'] as $rKey => $rLabel)
                    <a href="{{ route('analysis.weather', array_filter(['range' => $rKey, 'season' => $filters['season'], 'month' => $filters['month']])) }}"
                       @class(['btn', 'btn-success' => $range === $rKey, 'btn-outline-secondary' => $range !== $rKey])
                       @if ($range === $rKey) aria-current="page" @endif>{{ $L($rLabel) }}</a>
                @endforeach
            </div>
        </div>

        @if ($range === 'all_years')
            <form class="fad-card mb-3" method="GET" action="{{ route('analysis.weather') }}" data-weather-analysis-filters>
                <input type="hidden" name="range" value="all_years">
                <div class="row g-2 align-items-end">
                    <div class="col-sm-4">
                        <label class="form-label fw-semibold" for="waYear">{{ $L('year') }}</label>
                        <select class="form-select" id="waYear" name="year">
                            <option value="">{{ $L('all_years', ['from' => $fromYear, 'to' => $toYear]) }}</option>
                            @foreach ($years as $y)<option value="{{ $y }}" @selected($filters['year'] === $y)>{{ $y }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label fw-semibold" for="waSeason">{{ $L('season') }}</label>
                        <select class="form-select" id="waSeason" name="season">
                            <option value="">{{ $L('all') }}</option>
                            <option value="Wet" @selected($filters['season'] === 'Wet')>{{ $L('wet') }}</option>
                            <option value="Dry" @selected($filters['season'] === 'Dry')>{{ $L('dry') }}</option>
                        </select>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label fw-semibold" for="waMonth">{{ $L('month') }}</label>
                        <select class="form-select" id="waMonth" name="month">
                            <option value="">{{ $L('all') }}</option>
                            @foreach (range(1, 12) as $m)<option value="{{ $m }}" @selected($filters['month'] === $m)>{{ $monthNames[$m] }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-sm-1 d-grid"><button class="btn btn-primary" type="submit">{{ $L('apply') }}</button></div>
                </div>
            </form>
        @endif

        {{-- data-live-region: new weather or climate records redraw the figures and charts while open (live-page.js). --}}
        <div data-live-region="weather-analysis" data-live-areas="weather,climate,rice">
        @if ($count === 0)
            <div class="fad-card"><p class="mb-0">{{ $L('no_data') }}</p></div>
        @else
            <div class="row g-3">
                @foreach ([
                    'rainfall' => ['waRainfallChart', 'bar', 'mm', 'rainfall', 'meaning_rainfall'],
                    'temperature' => ['waTemperatureChart', 'line', '°C', 'temperature', 'meaning_temperature'],
                    'humidity' => ['waHumidityChart', 'line', '%', 'humidity', 'meaning_humidity'],
                ] as $series => [$canvasId, $type, $unit, $titleKey, $meaningKey])
                    <div class="col-12 col-lg-6">
                        <section class="fad-card h-100" aria-labelledby="{{ $canvasId }}Title">
                            <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-1">
                                <h2 class="mb-0 fw-bold" id="{{ $canvasId }}Title" style="font-size: 22px; color: var(--sage-800);">
                                    {{ $L($titleKey) }} <span class="fw-normal text-muted" style="font-size: 16px;">· {{ $unit }}</span>
                                </h2>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-muted" data-bs-toggle="collapse" data-bs-target="#{{ $canvasId }}Meaning" aria-expanded="false">{{ $L('meaning_link') }}</button>
                            </div>
                            <p class="small mb-2" style="color: var(--sage-700);" data-wa-summary="{{ $series }}">{{ $summaries[$series] }}</p>
                            <div class="collapse mb-2" id="{{ $canvasId }}Meaning">
                                <p class="small text-muted mb-0 p-2 rounded" style="background: var(--sage-50);">{{ $L($meaningKey) }}</p>
                            </div>
                            <div style="position: relative; height: 240px;">
                                <canvas id="{{ $canvasId }}" data-series="{{ $series }}" data-type="{{ $type }}" data-unit="{{ $unit }}" data-ic-info-bound="true" role="img" aria-label="{{ $L($titleKey) }}"></canvas>
                            </div>
                        </section>
                    </div>
                @endforeach

                @if (! $isFarmer && $windSummary)
                    <div class="col-12 col-lg-6">
                        <section class="fad-card h-100" aria-labelledby="waWindTableTitle" id="waWindTable">
                            <h2 class="mb-1 fw-bold" id="waWindTableTitle" style="font-size: 22px; color: var(--sage-800);">{{ $L('wind_table_title') }}</h2>
                            <div class="table-responsive">
                                <table class="table table-sm mb-2">
                                    <thead><tr><th>{{ $L('wind_band') }}</th><th>{{ $L('wind_months_count') }}</th><th>{{ $L('wind_examples') }}</th></tr></thead>
                                    <tbody>
                                        @foreach (['calm' => 'wind_calm', 'breezy' => 'wind_breezy', 'windy' => 'wind_windy'] as $wKey => $wLabel)
                                            <tr>
                                                <td>{{ $L($wLabel) }}</td>
                                                <td>{{ $windSummary[$wKey]['count'] }}</td>
                                                <td class="small text-muted">{{ $windSummary[$wKey]['months'] === [] ? '—' : implode(', ', $windSummary[$wKey]['months']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if ($stationWind)<p class="small text-muted mb-0">{{ $L('station_wind') }}</p>@endif
                        </section>
                    </div>
                @endif
            </div>

            <section class="fad-card mt-3" aria-labelledby="waCompareTitle" data-season-comparison>
                <h2 class="h5 fw-bold" id="waCompareTitle">{{ $L('compare_title') }}</h2>
                <p class="small text-muted">{{ $L('compare_note') }}</p>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th></th><th>{{ $L('months') }}</th><th>{{ $L('rainfall') }} ({{ $L('per_month') }})</th><th>{{ $L('temperature') }}</th><th>{{ $L('humidity') }}</th>@unless($isFarmer)<th>{{ $L('wind') }}</th>@endunless</tr></thead>
                        <tbody>
                            @foreach ($seasons as $season => $s)
                                <tr><th>{{ $L(strtolower($season)) }}</th><td>{{ $s['months'] }}</td><td>{{ $fmt($s['rainfall'], 1, ' mm') }}</td><td>{{ $fmt($s['temperature'], 1, ' °C') }}</td><td>{{ $fmt($s['humidity'], 1, '%') }}</td>@unless($isFarmer)<td>{{ $fmt($s['wind_speed'], 1) }}</td>@endunless</tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="fad-card mt-3" aria-labelledby="waYearlyTitle">
                <h2 class="h5 fw-bold" id="waYearlyTitle">{{ $L('yearly_title') }}</h2>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ $L('year') }}</th><th>{{ $L('months') }}</th><th>{{ $L('rain_total') }}</th><th>{{ $L('avg_temp') }}</th><th>{{ $L('avg_humidity') }}</th></tr></thead>
                        <tbody>
                            @foreach ($yearly as $y)<tr><td>{{ $y['year'] }}</td><td>{{ $y['months'] }}</td><td>{{ $fmt($y['rain_total'], 1, ' mm') }}</td><td>{{ $fmt($y['temperature'], 1, ' °C') }}</td><td>{{ $fmt($y['humidity'], 1, '%') }}</td></tr>@endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            @if (! $isFarmer)
                {{-- Staff: climate risk signals and climate vs yield. --}}
                <section class="fad-card mt-3" aria-labelledby="waRiskTitle" data-climate-indicators>
                    <h2 class="h5 fw-bold" id="waRiskTitle">Climate risk signals</h2>
                    <p class="small text-muted">Months above or below planning thresholds (not official PAGASA warnings): heavy rain {{ (int) \App\Services\Climate\WeatherAnalysisService::heavyRainMm() }} mm or more, dry below {{ (int) \App\Services\Climate\WeatherAnalysisService::dryMonthMm() }} mm, hot at {{ (int) \App\Services\Climate\WeatherAnalysisService::hotMonthC() }} °C average or more.</p>
                    <div class="row g-3">
                        @foreach (['heavy_rain' => 'Heavy-rain months', 'dry' => 'Dry months', 'hot' => 'Hot months'] as $key => $title)
                            <div class="col-md-4">
                                <div class="fw-semibold">{{ $title }} <span class="badge text-bg-light">{{ count($indicators[$key]) }}</span></div>
                                <p class="small mb-0">{{ $indicators[$key] === [] ? 'None' : implode(', ', array_slice($indicators[$key], -12)) }}@if (count($indicators[$key]) > 12) (latest 12) @endif</p>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="fad-card mt-3" aria-labelledby="waYieldTitle" data-climate-vs-yield>
                    <h2 class="h5 fw-bold" id="waYieldTitle">Climate vs rice yield</h2>
                    @if ($pairs->isEmpty())
                        <p class="mb-0">No month in this choice has both a yield record and weather readings.</p>
                    @else
                        <p class="small text-muted">Each point is a month with a yield record: that month's rainfall against the municipal average yield. <a href="{{ route('analysis.impact') }}">Run a Weather Impact Analysis</a> to see which variable matters most.</p>
                        <div style="position: relative; height: 260px;"><canvas id="waYieldScatterChart" data-ic-info-bound="true" role="img" aria-label="Rainfall against rice yield, one point per month"></canvas></div>
                        <script type="application/json" data-yield-pairs>@json($pairs->map(fn ($p) => ['x' => $p['rainfall'], 'y' => $p['yield'], 'label' => $p['key']])->filter(fn ($p) => $p['x'] !== null)->values())</script>
                    @endif
                </section>
            @endif

            <div class="mt-3 d-flex flex-wrap justify-content-between gap-2 small text-muted">
                <span data-months-shown>{{ trans_choice('weather_analysis.months_shown', $count, ['count' => $count]) }}</span>
                <span data-analysis-source>{{ $L('source', ['source' => $source]) }}</span>
            </div>
            @php
                $chartPayload = [
                    'range' => $range,
                    'focusYear' => $focusYear,
                    'shortMonths' => $shortMonths,
                    'comparison' => $comparison,
                    'timeline' => $chart,
                    'labels' => [
                        'current' => $range === 'last_3_years' ? $L('series_last_3_years') : $L('series_this_year', ['year' => $focusYear]),
                        'usual' => $L('series_usual'),
                        'usualShort' => $L('usual_short'),
                    ],
                ];
            @endphp
            <script type="application/json" data-weather-analysis-chart>@json($chartPayload)</script>
        @endif
        </div>
    </div>
    @unless ($isFarmer)
    </div>
    @endunless

    <script @nonce>
        const drawWeatherAnalysisCharts = (charts) => {
            charts.splice(0).forEach((chart) => chart.destroy());
            const payload = JSON.parse(document.querySelector('[data-weather-analysis-chart]')?.textContent || '{}');
            const isAllYears = payload.range === 'all_years';
            const sage700 = @json($sage700);
            const sageMuted = @json($sageMuted);

            document.querySelectorAll('[data-weather-analysis] canvas[data-series]').forEach((canvas) => {
                const series = canvas.dataset.series;
                const type = canvas.dataset.type;
                const unit = canvas.dataset.unit;

                if (isAllYears) {
                    const timeline = payload.timeline || {};
                    charts.push(new window.Chart(canvas, {
                        type,
                        data: {
                            labels: timeline.labels || [],
                            datasets: [{
                                label: canvas.getAttribute('aria-label'),
                                data: timeline[series] || [],
                                backgroundColor: sage700 + '99',
                                borderColor: sage700,
                                borderWidth: 2,
                                pointRadius: 2,
                                spanGaps: true,
                                tension: .25,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { x: { ticks: { maxTicksLimit: 12, autoSkip: true, maxRotation: 0 } } },
                        },
                    }));
                    return;
                }

                const comp = payload.comparison?.[series] || { current: [], usual: [] };
                const currentDataset = type === 'bar'
                    ? { label: payload.labels.current, type: 'bar', data: comp.current, backgroundColor: sage700, borderColor: sage700, borderRadius: 3, order: 2 }
                    : { label: payload.labels.current, type: 'line', data: comp.current, borderColor: sage700, backgroundColor: sage700, borderWidth: 2.5, pointRadius: 3, spanGaps: true, tension: .25, order: 2 };

                const usualDataset = {
                    label: payload.labels.usual,
                    type: 'line',
                    data: comp.usual,
                    borderColor: sageMuted,
                    backgroundColor: sageMuted,
                    borderWidth: 2,
                    borderDash: [5, 5],
                    pointRadius: 0,
                    spanGaps: true,
                    tension: .25,
                    order: 1,
                };

                charts.push(new window.Chart(canvas, {
                    type: type === 'bar' ? 'bar' : 'line',
                    data: {
                        labels: payload.shortMonths || [],
                        datasets: [currentDataset, usualDataset],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: true, position: 'top', align: 'end', labels: { boxWidth: 14, usePointStyle: false } },
                            tooltip: {
                                callbacks: {
                                    title: (items) => items[0] ? `${items[0].label} ${payload.focusYear}` : '',
                                    label: (ctx) => {
                                        if (ctx.datasetIndex !== 0) return null;
                                        const cur = ctx.parsed.y;
                                        const usu = comp.usual?.[ctx.dataIndex];
                                        const uStr = unit === '°C' ? ' °C' : (unit === '%' ? '%' : ` ${unit}`);
                                        const curTxt = cur === null ? '—' : `${cur}${uStr}`;
                                        const usuTxt = usu === null || usu === undefined ? '' : ` (${payload.labels.usualShort} ${usu}${uStr})`;
                                        return `${ctx.label} ${payload.focusYear}: ${curTxt}${usuTxt}`;
                                    },
                                },
                            },
                        },
                        scales: {
                            x: { ticks: { autoSkip: false, maxRotation: 0, minRotation: 0, font: { size: 10 } } },
                        },
                    },
                }));
            });

            const pairs = document.querySelector('[data-yield-pairs]');
            const scatter = document.getElementById('waYieldScatterChart');
            if (pairs && scatter) {
                const points = JSON.parse(pairs.textContent || '[]');
                charts.push(new window.Chart(scatter, {
                    type: 'line',
                    data: { datasets: [{ label: 'Month', data: points, showLine: false, pointRadius: 4, borderColor: sage700, backgroundColor: 'rgba({{ config('palette.rgb.700') }}, .6)' }] },
                    options: { responsive: true, maintainAspectRatio: false, parsing: { xAxisKey: 'x', yAxisKey: 'y' }, plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => `${ctx.raw.label}: ${ctx.raw.x} mm, ${ctx.raw.y} t/ha` } } }, scales: { x: { type: 'linear', title: { display: true, text: 'Rainfall (mm)' } }, y: { title: { display: true, text: 'Yield (t/ha)' } } } },
                }));
            }
        };
        document.addEventListener('DOMContentLoaded', async () => {
            try { await window.iClimateVisualsReady; } catch (e) { return; }
            if (!window.Chart) return;
            const charts = [];
            drawWeatherAnalysisCharts(charts);
            document.querySelector('[data-weather-analysis]')?.addEventListener('iclimate:live-region-updated', () => drawWeatherAnalysisCharts(charts));
        });
    </script>
</x-app-layout>
