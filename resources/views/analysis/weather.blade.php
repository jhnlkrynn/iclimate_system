<x-app-layout>
    <x-slot:title>{{ __('weather_analysis.title') }}</x-slot:title>
    @vite('resources/css/pages/weather-predictions.css')
    {{-- Manuscript – Naicel (2026-10-08), Phase 4: WeatherAnalysisController::weather. A tab of Predictions (Forecast for
         farmers). Farmers read it in their language; MAO also gets climate risk signals and climate vs yield. --}}
    @php
        $L = fn (string $key, array $replace = []) => __('weather_analysis.'.$key, $replace);
        $fmt = fn ($v, int $d = 1, string $unit = '') => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, $d), '0'), '.').$unit;
        $monthNames = __('weather_analysis.month_names');
    @endphp

    @unless ($isFarmer)
    <div class="mfd itd itp">
        <x-it.page-header :kicker="$L('kicker')" :title="$L('title')" :lede="$L('intro')" />
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

        <form class="fad-card mb-3" method="GET" action="{{ route('analysis.weather') }}" data-weather-analysis-filters>
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
            <p class="small text-muted mt-2 mb-0" data-months-shown>{{ trans_choice('weather_analysis.months_shown', $count, ['count' => $count]) }}</p>
        </form>

        @if ($count === 0)
            <div class="fad-card"><p class="mb-0">{{ $L('no_data') }}</p></div>
        @else
            <div class="row g-3">
                @foreach (['rainfall' => ['waRainfallChart', 'bar', '#2b6cb0'], 'temperature' => ['waTemperatureChart', 'line', '#c05621'], 'humidity' => ['waHumidityChart', 'line', '#2f855a'], 'wind_speed' => ['waWindChart', 'line', '#6b46c1']] as $series => [$canvasId, $type, $color])
                    <div class="col-lg-6">
                        <section class="fad-card h-100" aria-labelledby="{{ $canvasId }}Title">
                            <h2 class="h6 fw-bold" id="{{ $canvasId }}Title">{{ $L($series === 'wind_speed' ? 'wind_chart' : $series.'_chart') }}</h2>
                            <div style="position: relative; height: 240px;"><canvas id="{{ $canvasId }}" data-series="{{ $series }}" data-type="{{ $type }}" data-color="{{ $color }}" role="img" aria-label="{{ $L($series === 'wind_speed' ? 'wind_chart' : $series.'_chart') }}"></canvas></div>
                            @if ($series === 'wind_speed' && $stationWind)<p class="small text-muted mt-2 mb-0">{{ $L('station_wind') }}</p>@endif
                        </section>
                    </div>
                @endforeach
            </div>

            <section class="fad-card mt-3" aria-labelledby="waCompareTitle" data-season-comparison>
                <h2 class="h5 fw-bold" id="waCompareTitle">{{ $L('compare_title') }}</h2>
                <p class="small text-muted">{{ $L('compare_note') }}</p>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th></th><th>{{ $L('months') }}</th><th>{{ $L('rainfall') }} ({{ $L('per_month') }})</th><th>{{ $L('temperature') }}</th><th>{{ $L('humidity') }}</th><th>{{ $L('wind') }}</th></tr></thead>
                        <tbody>
                            @foreach ($seasons as $season => $s)
                                <tr><th>{{ $L(strtolower($season)) }}</th><td>{{ $s['months'] }}</td><td>{{ $fmt($s['rainfall'], 1, ' mm') }}</td><td>{{ $fmt($s['temperature'], 1, ' °C') }}</td><td>{{ $fmt($s['humidity'], 1, '%') }}</td><td>{{ $fmt($s['wind_speed'], 1) }}</td></tr>
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
                        <div style="position: relative; height: 260px;"><canvas id="waYieldScatterChart" role="img" aria-label="Rainfall against rice yield, one point per month"></canvas></div>
                        <script type="application/json" data-yield-pairs>@json($pairs->map(fn ($p) => ['x' => $p['rainfall'], 'y' => $p['yield'], 'label' => $p['key']])->filter(fn ($p) => $p['x'] !== null)->values())</script>
                    @endif
                </section>
            @endif

            <p class="small text-muted mt-3" data-analysis-source>{{ $L('source', ['source' => $source]) }}</p>
            <script type="application/json" data-weather-analysis-chart>@json($chart)</script>
        @endif
    </div>
    @unless ($isFarmer)
    </div>
    @endunless

    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            try { await window.iClimateVisualsReady; } catch (e) { return; }
            if (!window.Chart) return;
            const data = JSON.parse(document.querySelector('[data-weather-analysis-chart]')?.textContent || '{}');
            document.querySelectorAll('[data-weather-analysis] canvas[data-series]').forEach((canvas) => {
                const color = canvas.dataset.color;
                new window.Chart(canvas, {
                    type: canvas.dataset.type,
                    data: { labels: data.labels || [], datasets: [{ label: canvas.getAttribute('aria-label'), data: data[canvas.dataset.series] || [], backgroundColor: color + '99', borderColor: color, borderWidth: 2, pointRadius: 2, spanGaps: true, tension: .25 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { ticks: { maxTicksLimit: 12, autoSkip: true } } } },
                });
            });
            const pairs = document.querySelector('[data-yield-pairs]');
            const scatter = document.getElementById('waYieldScatterChart');
            if (pairs && scatter) {
                const points = JSON.parse(pairs.textContent || '[]');
                new window.Chart(scatter, {
                    type: 'line',
                    data: { datasets: [{ label: 'Month', data: points, showLine: false, pointRadius: 4, borderColor: '#14532d', backgroundColor: '#14532d99' }] },
                    options: { responsive: true, maintainAspectRatio: false, parsing: { xAxisKey: 'x', yAxisKey: 'y' }, plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => `${ctx.raw.label}: ${ctx.raw.x} mm, ${ctx.raw.y} t/ha` } } }, scales: { x: { type: 'linear', title: { display: true, text: 'Rainfall (mm)' } }, y: { title: { display: true, text: 'Yield (t/ha)' } } } },
                });
            }
        });
    </script>
</x-app-layout>
