@php
    $w = $f['weather'];
    $ok = $w['state'] === 'ok';
    $alertCount = count($f['alerts']);
    $now = $f['generated_at'];
@endphp
<section class="mfd-hero" data-mfd-hero aria-label="Today's weather">
    <div class="mfd-hero-copy">
        <h1>{{ $f['greeting'] }}</h1>
        <p class="mfd-hero-place">Today in Lian, Batangas</p>
        @if ($ok)
            <p class="mfd-hero-now">{{ $w['condition'] }} • {{ number_format($w['temperature'], 1) }}°C</p>
        @endif
        <p class="mfd-hero-date"><span data-mfd-clock>{{ $now->format('l, F j') }} • {{ $now->format('g:i A') }}</span></p>
        <a href="{{ $alertCount > 0 ? '#mfdAlerts' : route('notifications.index') }}" class="mfd-chip {{ $alertCount > 0 ? 'is-alert' : 'is-clear' }}">
            <span aria-hidden="true">{{ $alertCount > 0 ? '!' : '✓' }}</span>
            {{ $alertCount > 0 ? $alertCount.' active '.\Illuminate\Support\Str::plural('alert', $alertCount) : 'No active weather alerts' }}
        </a>
    </div>

    <div class="mfd-weather" data-mfd-weather>
        <div class="mfd-weather-head">
            <span class="mfd-weather-title">Today's Weather</span>
            <button type="button" class="mfd-btn is-quiet" data-mfd-refresh>Refresh</button>
        </div>
        @if ($ok)
            <div class="mfd-weather-main">
                @if ($w['icon'])<img class="mfd-weather-icon" src="{{ $w['icon'] }}" alt="" width="64" height="64">@endif
                <div>
                    <div class="mfd-temp">{{ number_format($w['temperature'], 1) }}<small>°C</small></div>
                    <div class="mfd-cond">{{ $w['condition'] }}</div>
                </div>
            </div>
            <ul class="mfd-metrics">
                @if ($w['humidity'] !== null)<li><span>Air moisture</span><strong>{{ number_format($w['humidity'], 0) }}%</strong></li>@endif
                @if ($w['rain_today'] !== null)<li><span>Rain expected today</span><strong>{{ number_format($w['rain_today'], 1) }} mm</strong></li>@endif
                @if ($w['wind'] !== null)<li><span>Wind</span><strong>{{ number_format($w['wind'], 1) }} km/h</strong></li>@endif
                @if ($w['feels_like'] !== null)<li><span>Feels like</span><strong>{{ number_format($w['feels_like'], 1) }}°C</strong></li>@endif
            </ul>
            <p class="mfd-weather-foot">
                Weather for Lian, Batangas @if ($w['updated_at']) · Last updated {{ $w['updated_at']->format('g:i A') }}@endif
                @if ($w['stale']) · showing the last saved reading @endif
            </p>
            <a class="mfd-link" href="{{ route('weather-predictions.index', ['tab' => 'weather-forecast']) }}">View Forecast</a>
        @else
            <div class="mfd-soft" role="status">
                <span>We could not load today's weather just now. Please try again in a moment.</span>
                <button type="button" class="mfd-btn is-quiet" data-mfd-retry>Try again</button>
            </div>
        @endif
    </div>
</section>
