{{-- 1. "Today's message": the one main message for today (FarmerDashboardService::message), as one big card.
     Good (planting weather) is green with a watering can; heavy rain, strong wind, high risk or "wait" is red with a
     "skip" sign; anything else is a quiet card. The sentence under the headline is the exact advice. --}}
@php
    $m = $f['message'];
    $mood = $m['tone'] === 'good' ? 'good' : (in_array($m['tone'], ['watch', 'danger'], true) ? 'bad' : 'info');
@endphp
<section class="fh-message is-{{ $m['tone'] }} fh-mood-{{ $mood }}" aria-labelledby="fhMessageTitle" data-fh-message="{{ $m['key'] }}">
    <div class="fh-message-art" aria-hidden="true">
        @if ($mood === 'good')
            <svg class="fh-art-water" viewBox="0 0 120 100" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
                <g class="fh-art-can">
                    <path d="M26 26h32v24a7 7 0 0 1-7 7H33a7 7 0 0 1-7-7V26Z"/>
                    <path d="M26 31h-5a7 7 0 0 0 0 14h5"/>
                    <path d="M58 33l22-13"/>
                    <path d="M78 15l7 8"/>
                </g>
                <g class="fh-art-drops">
                    <path class="fh-drop" d="M86 32v5"/>
                    <path class="fh-drop" d="M93 30v5"/>
                    <path class="fh-drop" d="M100 33v5"/>
                </g>
                <g class="fh-art-sprout">
                    <path d="M93 91V74"/>
                    <path d="M93 81c-8 0-12-4.5-12-10.5 7 0 12 4 12 10.5Z"/>
                    <path d="M93 77c0-7 4.5-11 11.5-11 0 6-4 11-11.5 11Z"/>
                    <path d="M79 91h28"/>
                </g>
            </svg>
        @elseif ($mood === 'bad')
            <svg class="fh-art-skip" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="6" stroke-linecap="round">
                <circle cx="32" cy="32" r="23"/>
                <path d="M16 48 48 16"/>
            </svg>
        @else
            @include('dashboards.mao.icon', ['name' => $m['icon']])
        @endif
    </div>
    <div class="fh-message-body">
        <h2 id="fhMessageTitle" class="fh-message-head">{{ __('farmer.home.message.headline_'.$mood) }}</h2>
        @unless ($m['key'] === 'plant_good')
            <p class="fh-message-text">{{ $m['text'] }}</p>
        @endunless
        @if ($mood === 'good' && ($f['weather']['state'] ?? null) === 'ok' && ($f['risk']['state'] ?? null) === 'ok' && ! in_array($f['risk']['key'] ?? null, ['high', 'severe'], true))
            @php
                $goodWeather = $f['weather'];
                $goodRisk = $f['risk'];
                $goodCondition = trim((string) ($goodWeather['rain'] ?? '')) ?: trim((string) ($goodWeather['condition'] ?? ''));
                $goodProvider = trim((string) ($goodWeather['provider'] ?? '')) ?: 'iClimate weather data';
            @endphp
            <p class="fh-message-reason">{{ __('farmer.home.message.good_reason', [
                'condition' => $goodCondition ?: __('farmer.weather.unknown'),
                'temperature' => number_format((float) ($goodWeather['temperature'] ?? 0), 1),
                'risk' => $goodRisk['label'],
            ]) }}</p>
            <p class="fh-message-source"><strong>{{ __('farmer.home.message.source_label') }}:</strong> {{ __('farmer.home.message.source_value', ['source' => $goodProvider]) }}</p>
        @endif
    </div>
</section>
