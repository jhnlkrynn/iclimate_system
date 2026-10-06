@php $w = $mao['weather']; @endphp
@if ($w['state'] === 'ok' && count($w['days']) > 0)
    <section class="mtd-card" aria-labelledby="mtdOutlookTitle" data-mao-forecast>
        <div class="mtd-card-head">
            <h2 id="mtdOutlookTitle">{{ count($w['days']) === 1 ? 'Weather Outlook — Today' : 'Weather Outlook — Next '.count($w['days']).' Days' }}</h2>
            <a class="mtd-link" href="{{ route('weather-predictions.index') }}">Full forecast calendar</a>
        </div>
        <div class="mtd-days" role="list">
            @foreach ($w['days'] as $day)
                <article class="mtd-day {{ $day['flags'] ? 'is-flagged' : '' }}" role="listitem">
                    <div class="mtd-day-head"><strong>{{ strtoupper($day['date']->format('D')) }}</strong><span>{{ $day['date']->format('M j') }}</span></div>
                    @if ($day['icon'])<img class="mtd-day-icon" src="{{ $day['icon'] }}" alt="" width="36" height="36" loading="lazy">@endif
                    <div class="mtd-day-cond">{{ $day['condition'] }}</div>
                    <ul class="mtd-day-vals">
                        @if ($day['rain'] !== null)<li>{{ number_format($day['rain'], 0) }} mm rain</li>@endif
                        @if ($day['high'] !== null)<li>{{ number_format($day['high'], 0) }}°C @if ($day['low'] !== null)<span class="mtd-muted">/ {{ number_format($day['low'], 0) }}°</span>@endif</li>@endif
                        @if ($day['wind'] !== null)<li>{{ number_format($day['wind'], 0) }} km/h wind</li>@endif
                    </ul>
                    @if ($day['flags'])<div class="mtd-flag"><span aria-hidden="true">⚠</span> {{ implode(', ', $day['flags']) }}</div>@endif
                </article>
            @endforeach
        </div>
        <p class="mtd-foot">Days are highlighted when the forecast reaches {{ number_format(\App\Services\Analytics\MaoDashboardService::HEAVY_RAIN_MM, 0) }} mm of rain, {{ number_format(\App\Services\Analytics\MaoDashboardService::STRONG_WIND_KMH, 0) }} km/h wind or {{ number_format(\App\Services\Analytics\MaoDashboardService::VERY_HOT_C, 0) }}°C. A planning guide, not an official PAGASA warning.</p>
        @if (collect($w['days'])->contains(fn ($day) => $day['wind'] === null) && collect($w['days'])->contains(fn ($day) => $day['wind'] !== null))
            {{-- The provider's wind forecast is shorter than its rain and temperature forecast. --}}
            <p class="mtd-foot">Wind speed is shown for the days the forecast provides it.</p>
        @endif
    </section>
@endif
