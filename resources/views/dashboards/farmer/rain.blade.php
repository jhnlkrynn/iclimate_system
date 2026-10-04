@php
    $r = $f['rain'];
    $title = __('farmer.rain.title');
    $chart = $r['state'] === 'ok' ? json_encode(['labels' => $r['labels'], 'actual' => $r['actual'], 'normal' => $r['normal'], 'year' => $r['year']]) : null;
@endphp
@if ($r['state'] === 'error')
    @include('dashboards.farmer._error', ['retry' => 'rain', 'title' => $title])
@elseif ($r['state'] === 'empty')
    <section class="mfd-card" aria-labelledby="mfdRainTitle" data-mfd-rainfall>
        <div class="mfd-card-head"><h2 id="mfdRainTitle">{{ $title }}</h2></div>
        <div class="mfd-empty">@include('dashboards.mao.icon', ['name' => 'info'])<p>{{ __('farmer.rain.empty') }}</p></div>
    </section>
@else
    {{-- Past records, not today's conditions: closed until the farmer asks for them. --}}
    <details class="mfd-map mfd-more" data-mfd-rainfall>
        <summary>{{ __('farmer.rain.summary', ['year' => $r['year']]) }}</summary>
        <section class="mfd-map-body" aria-labelledby="mfdRainTitle">
        <div class="mfd-card-head"><h2 id="mfdRainTitle">{{ $title }}</h2></div>
        @if ($r['summary'])<p class="mfd-takeaway">{{ $r['summary'] }}</p>@endif
        <figure class="mfd-figure">
            <figcaption><h3>{{ __('farmer.rain.chart_title', ['year' => $r['year']]) }}</h3></figcaption>
            <div class="mfd-chart is-loading">
                <canvas id="farmerRainfallTrendChart" data-mfd-chart="{{ $chart }}" role="img"
                    aria-label="{{ __('farmer.rain.chart_label', ['year' => $r['year']]) }}"></canvas>
            </div>
            <table class="mfd-sr-only">
                <caption>{{ __('farmer.rain.chart_title', ['year' => $r['year']]) }}</caption>
                <thead><tr><th scope="col">{{ __('farmer.rain.col_month') }}</th><th scope="col">{{ __('farmer.rain.col_year', ['year' => $r['year']]) }}</th><th scope="col">{{ __('farmer.rain.col_usual') }}</th></tr></thead>
                <tbody>@foreach ($r['labels'] as $i => $label)<tr><th scope="row">{{ $label }}</th><td>{{ $r['actual'][$i] !== null ? \App\Services\Analytics\FarmerKpiService::formatRain($r['actual'][$i]).' mm' : '—' }}</td><td>{{ $r['normal'][$i] !== null ? \App\Services\Analytics\FarmerKpiService::formatRain($r['normal'][$i]).' mm' : '—' }}</td></tr>@endforeach</tbody>
            </table>
        </figure>
        <p class="mfd-foot">@include('dashboards.mao.icon', ['name' => 'info']) {{ __('farmer.rain.station') }}</p>
        @if ($r['older'])<p class="mfd-foot">{{ __('farmer.rain.older', ['year' => $r['year']]) }}</p>@endif
        <p class="mfd-foot">{{ __('farmer.rain.usual_note', ['years' => \App\Services\Analytics\FarmerRainAnalyticsService::MIN_YEARS_FOR_AVERAGE]) }}</p>
        </section>
    </details>
@endif
