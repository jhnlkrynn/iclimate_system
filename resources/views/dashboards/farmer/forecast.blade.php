@php
    $w = $f['weather'];
    $days = $w['state'] === 'ok' ? $w['days'] : [];
    $chartDays = collect($days);
    $forecastChart = $chartDays->count() >= 2 ? json_encode([
        'labels' => $chartDays->map(fn ($d) => $d['date']->format('D j'))->all(),
        'rain' => $chartDays->map(fn ($d) => $d['rain'] ?? 0)->all(),
        'high' => $chartDays->map(fn ($d) => $d['high'])->all(),
        'low' => $chartDays->map(fn ($d) => $d['low'])->all(),
    ]) : null;
@endphp
@if (count($days) > 0)
    <section class="mfd-card" aria-labelledby="mfdOutlookTitle" data-mfd-forecast>
        <div class="mfd-card-head">
            <h2 id="mfdOutlookTitle">{{ count($days) === 7 ? 'Weather This Week' : (count($days) === 1 ? 'Weather Today' : 'Weather for the Next '.count($days).' Days') }}</h2>
            <a class="mfd-link" href="{{ route('weather-predictions.index', ['tab' => 'weather-forecast']) }}">Full forecast</a>
        </div>
        <div class="mfd-days" role="list" tabindex="0" aria-label="Daily forecast. Use the left and right arrow keys to scroll.">
            @foreach ($days as $day)
                <article class="mfd-day {{ $day['flags'] ? 'is-flagged' : '' }}" role="listitem">
                    <div class="mfd-day-head"><strong>{{ strtoupper($day['date']->format('D')) }}</strong><span>{{ $day['date']->format('M j') }}</span></div>
                    @if ($day['icon'])<img class="mfd-day-icon" src="{{ $day['icon'] }}" alt="" width="40" height="40" loading="lazy">@endif
                    <div class="mfd-day-cond">{{ $day['condition'] }}</div>
                    <ul class="mfd-day-vals">
                        @if ($day['high'] !== null)<li><strong>{{ number_format($day['high'], 0) }}°</strong>@if ($day['low'] !== null) / {{ number_format($day['low'], 0) }}°@endif</li>@endif
                        @if ($day['chance'] !== null)<li>{{ number_format($day['chance'], 0) }}% chance of rain</li>@endif
                        @if ($day['rain'] !== null && $day['rain'] > 0)<li>{{ number_format($day['rain'], 0) }} mm expected</li>@endif
                        @if ($day['wind'] !== null)<li>{{ number_format($day['wind'], 0) }} km/h wind</li>@endif
                    </ul>
                    @foreach ($day['flags'] as $flag)<div class="mfd-flag"><span aria-hidden="true">⚠</span> {{ strtoupper($flag) }}</div>@endforeach
                </article>
            @endforeach
        </div>
        @if ($forecastChart)
            <div class="mfd-chart-sm is-loading">
                <canvas id="farmerForecastChart" data-mfd-chart="{{ $forecastChart }}" role="img"
                    aria-label="Expected rain in millimetres as bars, with the daily high and low temperature as lines, for the coming days"></canvas>
            </div>
        @endif
        <p class="mfd-foot">Forecast for Lian, Batangas. Only days with heavy rain (25 mm or more), strong wind (35 km/h or more) or high heat (35°C or more) are highlighted.</p>
    </section>
@endif
