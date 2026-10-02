@php $w = $mao['weather']; @endphp
<section class="mtd-card" aria-labelledby="mtdConditionsTitle" data-mao-conditions data-mao-weather-root>
    <div class="mtd-card-head">
        <div>
            <h2 id="mtdConditionsTitle">Current Farm Conditions</h2>
            <p class="mtd-sub">Live weather conditions for {{ $w['state'] === 'ok' ? $w['location'] : 'Lian, Batangas' }}.</p>
        </div>
        <div class="mtd-head-actions">
            @if ($w['state'] === 'ok' && $w['updated_at'])<span class="mtd-updated">Last updated: {{ $w['updated_at']->format('g:i A') }}</span>@endif
            <a class="mtd-btn is-quiet" href="{{ route('weather-predictions.index') }}">View Weather Forecast</a>
        </div>
    </div>
    @if ($w['state'] !== 'ok')
        <div class="mtd-soft-error" role="status">
            <span>Live weather is temporarily unavailable.</span>
            <button type="button" class="mtd-btn is-quiet" data-mao-retry>Try again</button>
        </div>
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
