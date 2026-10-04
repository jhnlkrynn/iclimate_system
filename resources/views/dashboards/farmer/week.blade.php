@php $week = $f['week']; $title = __('farmer.week.title'); @endphp
@if ($week['state'] === 'error')
    @include('dashboards.farmer._error', ['retry' => 'week', 'title' => $title])
@elseif ($week['state'] === 'empty')
    <section class="mfd-card" aria-labelledby="mfdWeekTitle" data-mfd-week>
        <div class="mfd-card-head"><h2 id="mfdWeekTitle">{{ $title }}</h2></div>
        <div class="mfd-empty">@include('dashboards.mao.icon', ['name' => 'info'])<p>{{ __('farmer.week.empty') }}</p></div>
    </section>
@else
    @php
        $days = $week['days'];
        $rainChart = json_encode($week['rain_chart']);
    @endphp
    <section class="mfd-card" aria-labelledby="mfdWeekTitle" data-mfd-week>
        <div class="mfd-card-head">
            <h2 id="mfdWeekTitle">{{ $title }}</h2>
            <a class="mfd-link" href="{{ route('weather-predictions.index', ['tab' => 'weather-forecast']) }}">{{ __('farmer.week.see_full') }}</a>
        </div>

        {{-- One chart: the rain expected each day, with what it means in one or two short sentences under it. --}}
        <figure class="mfd-figure">
            <figcaption><h3>{{ __('farmer.week.rain_chart_title') }}</h3></figcaption>
            <div class="mfd-chart is-loading">
                <canvas id="farmerWeekRainChart" data-mfd-chart="{{ $rainChart }}" role="img"
                    aria-label="{{ __('farmer.week.rain_chart_label') }}"></canvas>
            </div>
            <table class="mfd-sr-only">
                <caption>{{ __('farmer.week.rain_chart_title') }}</caption>
                <thead><tr><th scope="col">{{ __('farmer.week.col_day') }}</th><th scope="col">{{ __('farmer.week.col_rain') }}</th></tr></thead>
                <tbody>@foreach ($days as $day)<tr><th scope="row">{{ $day['date']->format('l, M j') }}</th><td>{{ $day['rain'] !== null ? \App\Services\Analytics\FarmerKpiService::formatRain($day['rain']).' mm' : '—' }}</td></tr>@endforeach</tbody>
            </table>
            <div class="mfd-figure-reading">
                <p>{{ $week['takeaway'] }}</p>
                @if ($week['temp_takeaway'])<p>{{ $week['temp_takeaway'] }}</p>@endif
            </div>
        </figure>

        {{-- The same seven days one by one, closed until the farmer asks for them. --}}
        <details class="mfd-daily">
            <summary>{{ __('farmer.week.each_day') }}</summary>
            <p class="mfd-legend">@include('dashboards.mao.icon', ['name' => 'check']){{ __('farmer.week.good_legend', ['rain' => (int) config('farmer_dashboard.good_work_day.max_rain_chance_percent'), 'wind' => (int) config('farmer_dashboard.good_work_day.max_wind_kmh')]) }}</p>
            <ol class="mfd-days">
                @foreach ($days as $day)
                    <li class="mfd-day {{ $day['is_today'] ? 'is-today' : '' }} {{ $day['good'] ? 'is-good' : '' }}">
                        <div class="mfd-day-head">
                            <strong>{{ $day['is_today'] ? __('farmer.week.today') : $day['date']->format('D') }}</strong>
                            <span>{{ $day['date']->format('M j') }}</span>
                        </div>
                        <span class="mfd-day-icon">@include('dashboards.mao.icon', ['name' => $day['icon']])</span>
                        <div class="mfd-day-cond">{{ $day['condition'] }}</div>
                        <ul class="mfd-day-vals">
                            @if ($day['high'] !== null)<li><strong>{{ number_format($day['high'], 0) }}°</strong>@if ($day['low'] !== null) / {{ number_format($day['low'], 0) }}°@endif <span class="mfd-muted">{{ __('farmer.week.high_low') }}</span></li>@endif
                            @if ($day['chance'] !== null)<li>{{ __('farmer.week.chance', ['percent' => number_format($day['chance'], 0)]) }}</li>@endif
                            @if ($day['rain'] !== null)<li>{{ \App\Services\Analytics\FarmerKpiService::formatRain($day['rain']) }} mm</li>@endif
                        </ul>
                        <div class="mfd-day-tags">
                            @if ($day['good'])
                                <span class="mfd-pill is-good">@include('dashboards.mao.icon', ['name' => 'check'])<span>{{ __('farmer.week.good_day') }}</span></span>
                            @endif
                            @foreach ($day['flags'] as $flag)
                                <span class="mfd-pill is-watch">@include('dashboards.mao.icon', ['name' => 'alert'])<span>{{ __('farmer.week.flag_'.$flag) }}</span></span>
                            @endforeach
                        </div>
                    </li>
                @endforeach
            </ol>
        </details>
        <p class="mfd-foot">{{ __('farmer.week.foot') }}</p>
    </section>
@endif
