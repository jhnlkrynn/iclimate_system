{{-- 5. The next 7 days as weather icons (rain in words), and the latest planting advisory for the farmer's area. --}}
@php
    $week = $f['week'];
    $a = $f['advisory'];
@endphp
<section class="fh-card fh-week" aria-labelledby="fhWeekTitle" data-fh-week>
    <h2 id="fhWeekTitle" class="fh-kicker">{{ __('farmer.home.week.title') }}</h2>
    @if ($week['state'] === 'ok')
        <ol class="fh-days">
            @foreach ($week['days'] as $day)
                <li @class(['fh-day', 'is-today' => $day['is_today']])>
                    <span class="fh-day-name">{{ $day['label'] }}</span>
                    <span class="fh-day-icon" aria-hidden="true">@include('dashboards.mao.icon', ['name' => $day['icon']])</span>
                    <span class="fh-day-words">{{ $day['condition'] }}</span>
                    @if ($day['rain'])<span class="fh-day-rain">{{ $day['rain'] }}</span>@endif
                </li>
            @endforeach
        </ol>
    @else
        <x-no-data :label="__('farmer.home.no_data')" :note="__('farmer.home.no_data_note')" />
    @endif

    <div class="fh-advice" data-fh-advice>
        <h3 class="fh-kicker">{{ __('farmer.home.advisory.title') }}</h3>
        @if ($a)
            <p class="fh-advice-title">{{ $a['title'] }}</p>
            <p class="fh-words">{{ $a['line'] }}</p>
        @else
            <p class="fh-words">{{ __('farmer.home.advisory.none') }}</p>
        @endif
        <a class="fh-link" href="{{ route('planting-advisories.index') }}">{{ __('farmer.home.advisory.see_all') }}</a>
    </div>
</section>
