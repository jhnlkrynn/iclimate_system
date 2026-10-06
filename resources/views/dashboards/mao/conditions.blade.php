@php $w = $mao['weather']; @endphp
<section class="mtd-card" aria-labelledby="mtdConditionsTitle" data-mao-conditions data-mao-weather-root>
    <div class="mtd-card-head">
        <div>
            <h2 id="mtdConditionsTitle">Weather Now</h2>
            <p class="mtd-sub">Live weather conditions for {{ $w['state'] === 'ok' ? $w['location'] : 'Lian, Batangas' }}.</p>
        </div>
        <div class="mtd-head-actions">
            @if ($w['state'] === 'ok' && $w['updated_at'])<x-updated-at class="mtd-updated" :at="$w['updated_at']" />@endif
            <a class="mtd-btn is-quiet" href="{{ route('weather-predictions.index') }}">View Weather Forecast</a>
        </div>
    </div>
    @if ($w['state'] !== 'ok')
        {{-- No saved reading at all yet: a calm note (the scheduled refresh stores one every few minutes). --}}
        <x-no-data note="The weather appears here after the next scheduled update." />
    @else
        <div class="mtd-tiles">
            @if ($w['rain'] !== null)
                <div class="mtd-tile"><span class="mtd-tile-icon">@include('dashboards.mao.icon', ['name' => 'rain'])</span><span class="mtd-tile-label">Current Rain</span><strong>{{ number_format($w['rain'], 1) }}<small>mm</small></strong></div>
            @endif
            <div class="mtd-tile"><span class="mtd-tile-icon">@include('dashboards.mao.icon', ['name' => 'temperature'])</span><span class="mtd-tile-label">Temperature</span><strong>{{ number_format($w['temperature'], 1) }}<small>°C</small></strong></div>
            @if ($w['humidity'] !== null)
                <div class="mtd-tile"><span class="mtd-tile-icon">@include('dashboards.mao.icon', ['name' => 'humidity'])</span><span class="mtd-tile-label">Humidity</span><strong>{{ number_format($w['humidity'], 0) }}<small>%</small></strong></div>
            @endif
            @if ($w['wind'] !== null)
                <div class="mtd-tile"><span class="mtd-tile-icon">@include('dashboards.mao.icon', ['name' => 'wind'])</span><span class="mtd-tile-label">Wind Speed</span><strong>{{ number_format($w['wind'], 1) }}<small>km/h</small></strong></div>
            @endif
        </div>
        @if ($w['condition'])<p class="mtd-foot">Right now: {{ $w['condition'] }}.@if ($w['stale']) Showing the last saved reading; the weather service could not be reached.@endif</p>@endif
    @endif
</section>
