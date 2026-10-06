{{-- 2. Weather today: icon, words and °C; rain and wind in words (never millimetres). The saved reading with its time. --}}
@php $w = $f['weather']; @endphp
<section class="fh-card" aria-labelledby="fhWeatherTitle" data-fh-weather>
    <h2 id="fhWeatherTitle" class="fh-kicker">{{ __('farmer.home.weather.title') }}</h2>
    @if ($w['state'] === 'ok')
        <div class="fh-weather-now">
            <span class="fh-weather-icon" aria-hidden="true">@include('dashboards.mao.icon', ['name' => $w['icon']])</span>
            <div>
                <p class="fh-big">{{ number_format($w['temperature'], 0) }}°C</p>
                <p class="fh-words">{{ $w['condition'] }}</p>
            </div>
        </div>
        <ul class="fh-tags">
            @if ($w['rain'])<li>@include('dashboards.mao.icon', ['name' => 'rain']){{ $w['rain'] }}</li>@endif
            @if ($w['wind'])<li>@include('dashboards.mao.icon', ['name' => 'wind']){{ $w['wind'] }}</li>@endif
        </ul>
        <x-updated-at :at="$w['updated_at']" :label="__('farmer.home.updated')" />
    @else
        <x-no-data :label="__('farmer.home.no_data')" :note="__('farmer.home.no_data_note')" />
    @endif
</section>
