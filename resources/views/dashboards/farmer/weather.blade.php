{{-- 2. Weather today: icon, words and °C; rain and wind in words (never millimetres). The saved reading with its time.
     2026-11-22: the card opens its summary panel (detail.blade.php) on this page; the button covers the whole card. --}}
@php $w = $f['weather']; @endphp
<section @class(['fh-card', 'fh-card-opens' => $w['state'] === 'ok']) aria-labelledby="fhWeatherTitle" data-fh-weather>
    <h2 id="fhWeatherTitle" class="fh-kicker">{{ __('farmer.home.weather.title') }}</h2>
    @if ($w['state'] === 'ok')
        <div class="fh-weather-now">
            <span class="fh-weather-icon" aria-hidden="true">@include('dashboards.mao.icon', ['name' => $w['icon']])</span>
            <div>
                <p class="fh-big">{{ number_format($w['temperature'], 0) }}°C</p>
                <p class="fh-words"><span class="fh-when">{{ __('farmer.home.weather.now') }}:</span> {{ $w['condition'] }}</p>
            </div>
        </div>
        <ul class="fh-tags">
            @if ($w['rain'])<li>@include('dashboards.mao.icon', ['name' => 'rain'])<span class="fh-when">{{ __('farmer.home.weather.all_day') }}:</span> {{ $w['rain'] }}</li>@endif
            @if ($w['wind'])<li>@include('dashboards.mao.icon', ['name' => 'wind'])<span class="fh-when">{{ __('farmer.home.weather.now') }}:</span> {{ $w['wind'] }}</li>@endif
        </ul>
        <x-updated-at :at="$w['updated_at']" :label="__('farmer.home.updated')" data-weather-fetched="current" />
        @php
            // Today's line of the saved forecast (the all-day words), when the forecast has today.
            $todayForecast = collect($f['week']['days'] ?? [])->first(fn (array $d): bool => $d['is_today'] ?? false);
        @endphp
        <button type="button" class="fh-card-open" data-fh-detail-open="weather" aria-haspopup="dialog"><span class="mfd-sr-only">{{ __('farmer.kpis.detail.open', ['card' => __('farmer.home.weather.title')]) }}</span></button>
        @include('dashboards.farmer.detail', ['key' => 'weather', 'd' => [
            'title' => __('farmer.home.weather.title'),
            'rows' => array_filter([
                __('farmer.kpis.detail.conditions') => $w['condition'],
                __('farmer.kpis.detail.temperature') => number_format($w['temperature'], 0).'°C',
                __('farmer.kpis.detail.all_day') => trim(($todayForecast['condition'] ?? '').(($todayForecast['rain'] ?? null) ? ' · '.$todayForecast['rain'] : '')) ?: ($w['rain'] ?: null),
                __('farmer.kpis.detail.rain') => $w['rain'] ?: __('farmer.home.rain.none'),
                __('farmer.kpis.detail.wind') => $w['wind'] ?: __('farmer.kpis.detail.wind_calm'),
                __('farmer.kpis.detail.source') => $w['provider'] ?? null,
            ], fn ($v): bool => $v !== null && $v !== ''),
            'text' => ($w['stale'] ?? false) ? __('farmer.kpis.detail.weather_stale') : null,
            'updated_at' => $w['updated_at'],
            'link' => route('weather-predictions.index').'#weather',
        ]])
    @else
        <x-no-data :label="__('farmer.home.no_data')" :note="__('farmer.home.no_data_note')" />
    @endif
</section>
