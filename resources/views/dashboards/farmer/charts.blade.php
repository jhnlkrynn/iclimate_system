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
<div class="fh-charts" data-fh-charts>
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
