{{-- Farmer – Naicel (2026-10): two simple charts from saved data (FarmerHomeCharts), drawn by
     resources/js/farmer-home-charts.js with the Chart.js that app.js already loads. Plain words and big labels; with
     nothing to draw a card shows one short line instead of an empty chart. --}}
@php
    $charts = $f['charts'] ?? [];
    $harvest = $charts['harvest'] ?? ['state' => 'nodata'];
    $weather = $charts['weather'] ?? ['state' => 'nodata'];
    $harvestData = $harvest['state'] === 'ok' ? [
        'labels' => $harvest['labels'], 'mine' => $harvest['mine'], 'average' => $harvest['average'], 'has_average' => $harvest['has_average'],
        'text' => ['mine' => __('farmer.charts.harvest_mine'), 'average' => __('farmer.charts.harvest_barangay'), 'axis' => __('farmer.charts.harvest_axis')],
    ] : null;
    $weatherData = $weather['state'] === 'ok' ? [
        'labels' => $weather['labels'], 'temperature' => $weather['temperature'], 'rain' => $weather['rain'], 'has_rain' => $weather['has_rain'],
        'text' => ['temp' => __('farmer.charts.temp'), 'rain' => __('farmer.charts.rain_chance')],
    ] : null;
    // Farmer – Naicel (2026-10-08): the yield comparison (FarmerHomeKpis), shown only with at least two figures.
    $compare = $f['compare'] ?? ['state' => 'nodata'];
    $compareData = $compare['state'] === 'ok' ? [
        'keys' => $compare['keys'], 'labels' => $compare['labels'], 'values' => $compare['values'],
        'text' => ['axis' => __('farmer.charts.harvest_axis')],
    ] : null;
    $compareWords = $compareData ? collect($compare['labels'])->map(fn ($label, $i) => $label.' '.number_format($compare['values'][$i], 2).' t/ha')->implode(', ') : '';
    // What the harvest chart shows, in words, for screen readers.
    $harvestWords = $harvestData ? collect($harvest['labels'])->map(fn ($label, $i) => $label.' '.number_format($harvest['mine'][$i], 2).' t/ha')->implode(', ') : '';
@endphp
@php
    // Dashboard redesign – Naicel (2026-10-09): two small analytics drawn without a chart library.
    // Rain this week: the chance of rain for the next 7 days, from the same saved forecast as the 14-day chart.
    $rainWeek = [];
    if ($weatherData && $weather['has_rain']) {
        foreach (array_slice($weather['labels'], 0, 7) as $i => $dayLabel) {
            if (($weather['rain'][$i] ?? null) !== null) {
                $rainWeek[] = ['label' => $dayLabel, 'chance' => (int) $weather['rain'][$i], 'today' => $i === 0];
            }
        }
    }
    // Crop progress: how much of the growing time has passed, from the "Days to harvest" figure.
    $cropTile = collect($f['kpis'] ?? [])->first(fn (array $tile): bool => $tile['key'] === 'crop' && ($tile['progress'] ?? null) !== null);
@endphp
<div class="fh-charts" data-fh-charts>
    @if (count($rainWeek) >= 3)
    <section class="fh-card" aria-labelledby="fhRainWeekTitle" data-fh-analytic="rain-week">
        <h2 id="fhRainWeekTitle" class="fh-kicker">{{ __('farmer.kpis.rain_week.title') }}</h2>
        <p class="fh-muted">{{ __('farmer.kpis.rain_week.sub') }}</p>
        <ol class="fh-pills" aria-label="{{ __('farmer.kpis.rain_week.title') }}">
            @foreach ($rainWeek as $day)
                <li @class(['fh-pill', 'is-low' => $day['chance'] < 30, 'is-high' => $day['chance'] >= 70, 'is-today' => $day['today']])>
                    <span class="fh-pill-value">{{ $day['chance'] }}%</span>
                    <span class="fh-pill-track"><span class="fh-pill-fill" style="height: {{ max(8, $day['chance']) }}%"></span></span>
                    <span class="fh-pill-label">{{ $day['label'] }}</span>
                </li>
            @endforeach
        </ol>
        <p class="fh-pill-legend"><span><i class="is-high"></i>{{ __('farmer.kpis.rain_week.high') }}</span><span><i class="is-mid"></i>{{ __('farmer.kpis.rain_week.mid') }}</span><span><i class="is-low"></i>{{ __('farmer.kpis.rain_week.low') }}</span></p>
    </section>
    @endif

    @if ($cropTile)
    <section class="fh-card" aria-labelledby="fhCropProgressTitle" data-fh-analytic="crop-progress">
        <h2 id="fhCropProgressTitle" class="fh-kicker">{{ __('farmer.kpis.progress.title') }}</h2>
        <p class="fh-muted">{{ $cropTile['note'] }}</p>
        @include('partials.half-donut', ['segments' => [['value' => $cropTile['progress'], 'color' => 'var(--brand, var(--sage-700))', 'label' => __('farmer.kpis.progress.done')]], 'total' => 100,
            'big' => $cropTile['progress'].'%', 'small' => __('farmer.kpis.progress.center'),
            'label' => __('farmer.kpis.crop.progress', ['percent' => $cropTile['progress']])])
        <p class="fh-words fh-gauge-note">{{ $cropTile['label'] }}: {{ $cropTile['value'] }} {{ $cropTile['unit'] }}</p>
    </section>
    @endif

    <section class="fh-card" aria-labelledby="fhHarvestChartTitle" data-fh-chart="harvest">
        <h2 id="fhHarvestChartTitle" class="fh-kicker">{{ __('farmer.charts.harvest_title') }}</h2>
        @if ($harvestData)
            <div class="fh-chart-box">
                <canvas id="fhHarvestChart" role="img" aria-label="{{ __('farmer.charts.harvest_title') }}: {{ $harvestWords }}"></canvas>
            </div>
            <script type="application/json" data-fh-chart-data="harvest">@json($harvestData)</script>
        @else
            <p class="fh-words fh-chart-none" data-fh-chart-none>{{ __('farmer.charts.harvest_none') }}</p>
        @endif
    </section>

    <section class="fh-card" aria-labelledby="fhWeatherChartTitle" data-fh-chart="weather">
        <h2 id="fhWeatherChartTitle" class="fh-kicker">{{ __('farmer.charts.weather_title') }}</h2>
        @if ($weatherData)
            <p class="fh-muted">{{ __('farmer.predictions.for_place', ['place' => $weather['location']]) }}</p>
            <div class="fh-chart-box">
                <canvas id="fhWeatherChart" role="img" aria-label="{{ __('farmer.charts.weather_title') }}: {{ __('farmer.charts.temp') }}, {{ __('farmer.charts.rain_chance') }}"></canvas>
            </div>
            <script type="application/json" data-fh-chart-data="weather">@json($weatherData)</script>
        @else
            <p class="fh-words fh-chart-none" data-fh-chart-none>{{ __('farmer.charts.weather_none') }}</p>
        @endif
    </section>

    @if ($compareData)
    <section class="fh-card" aria-labelledby="fhCompareChartTitle" data-fh-chart="compare">
        <h2 id="fhCompareChartTitle" class="fh-kicker">{{ __('farmer.kpis.compare.title') }}</h2>
        <p class="fh-muted">{{ __('farmer.kpis.compare.sub') }}</p>
        <div class="fh-chart-box is-short">
            <canvas id="fhCompareChart" role="img" aria-label="{{ __('farmer.kpis.compare.title') }}: {{ $compareWords }}"></canvas>
        </div>
        <script type="application/json" data-fh-chart-data="compare">@json($compareData)</script>
    </section>
    @endif
</div>
