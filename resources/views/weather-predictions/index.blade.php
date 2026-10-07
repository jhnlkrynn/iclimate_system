<x-app-layout>
    @vite('resources/css/pages/weather-predictions.css')
    {{-- One Predictions page for every role (WeatherPredictionController::index): the coming days' weather from the stored
         daily forecast ([This Week] / [Next 2 Weeks]) and this season's expected yield. Farmers read words; MAO and IT also
         see numbers; MAO can pick a barangay; IT can open the technical details. No date or month picker. --}}
    @php
        $staff = ! $isFarmer;
        $num = fn ($value, int $decimals = 0) => $value === null ? '—' : number_format((float) $value, $decimals);
        $rangeUrl = fn (string $to) => route('weather-predictions.index', array_filter(['range' => $to === 'week' ? null : $to, 'barangay' => $barangay && $role === \App\Models\User::ROLE_MAO ? $barangay : null]));
        $y = $yield;
        $t = $technical;
        $noData = $isFarmer ? __('farmer.home.no_data') : 'Walang datos pa';
    @endphp

    {{-- Same page styling as before: farmers get the farmer page header; MAO and IT keep the staff page header. --}}
    @unless ($isFarmer)
    <div class="mfd itd itp">
        <x-it.page-header kicker="Weather and Rice Yield" title="Predictions" lede="The coming days' weather and this season's expected harvest for Lian." />
    @endunless
    <div class="fad wpx" data-predictions>
        @if ($isFarmer)
            <header class="fad-hero">
                <div class="fad-hero-text">
                    <span class="fad-kicker">{{ __('farmer.nav.forecast') }}</span>
                    <h1>{{ __('farmer.predictions.title') }}</h1>
                    <p>{{ __('farmer.predictions.intro') }}</p>
                </div>
            </header>
        @endif

        {{-- Weather: the stored daily forecast, days 1–7 or 1–14. --}}
        <section class="fad-card wpx-panel" id="weather" aria-labelledby="wpxWeatherTitle" data-wpx-weather>
            <div class="wpx-head">
                <div>
                    <h2 id="wpxWeatherTitle">{{ __('farmer.predictions.weather_title') }}</h2>
                    <p class="fad-support">{{ __('farmer.predictions.for_place', ['place' => $forecast['location']]) }}
                        @if ($forecast['updated_at']) · <x-updated-at :at="$forecast['updated_at']" :label="__('farmer.home.updated')" />@endif</p>
                </div>
                <nav class="wpx-range" aria-label="{{ __('farmer.predictions.weather_title') }}">
                    <a href="{{ $rangeUrl('week') }}" @class(['is-active' => $range === 'week']) @if ($range === 'week') aria-current="page" @endif>{{ __('farmer.predictions.this_week') }}</a>
                    <a href="{{ $rangeUrl('2weeks') }}" @class(['is-active' => $range === '2weeks']) @if ($range === '2weeks') aria-current="page" @endif>{{ __('farmer.predictions.two_weeks') }}</a>
                </nav>
            </div>

            @if ($role === \App\Models\User::ROLE_MAO)
                <form class="wpx-filter" method="GET" action="{{ route('weather-predictions.index') }}" data-wpx-filter>
                    <label for="wpxBarangay">{{ __('farmer.predictions.barangay') }}</label>
                    <select id="wpxBarangay" name="barangay">
                        <option value="">{{ __('farmer.predictions.all_lian') }}</option>
                        @foreach ($barangays as $name)
                            <option value="{{ $name }}" @selected($barangay === $name)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @if ($range === '2weeks')<input type="hidden" name="range" value="2weeks">@endif
                    <button type="submit" class="fad-btn is-primary">{{ __('farmer.predictions.show') }}</button>
                </form>
            @endif

            @if ($forecast['state'] !== 'ok')
                <x-no-data :label="$noData" :note="__('farmer.home.no_data_note')" />
            @else
                <ol class="wpx-days" data-days="{{ count($forecast['days']) }}">
                    @foreach ($forecast['days'] as $i => $day)
                        <li @class(['wpx-day', 'is-today' => $i === 0, 'is-estimate' => $day['kind'] === 'estimate', 'is-empty' => $day['kind'] === 'none']) data-kind="{{ $day['kind'] }}">
                            <span class="wpx-day-name">{{ $i === 0 ? __('farmer.predictions.today') : $day['date']->locale(app()->getLocale())->translatedFormat('D, M j') }}</span>
                            @if ($day['kind'] === 'none')
                                <span class="wpx-day-words">{{ $noData }}</span>
                            @else
                                <span class="wpx-day-icon" aria-hidden="true">@include('dashboards.mao.icon', ['name' => $day['icon']])</span>
                                @if ($day['kind'] === 'estimate')<span class="wpx-badge">{{ __('farmer.predictions.estimate_badge') }}</span>@endif
                                <span class="wpx-day-words">{{ $day['kind'] === 'estimate' ? __('farmer.predictions.estimate') : $day['condition'] }}</span>
                                @if ($day['rain_words'])<span class="wpx-day-rain">{{ $day['rain_words'] }}</span>@endif
                                @if ($day['high'] !== null)<span class="wpx-day-temp">{{ $num($day['high']) }}° / {{ $num($day['low']) }}°</span>@endif
                                @if ($isFarmer && $day['wind_words'])<span class="wpx-day-wind">{{ $day['wind_words'] }}</span>@endif
                                @if ($staff)
                                    <dl class="wpx-day-numbers">
                                        <div><dt>{{ __('farmer.predictions.rain') }}</dt><dd>{{ $day['rain_mm'] === null ? '—' : $num($day['rain_mm'], 1).' mm' }}</dd></div>
                                        <div><dt>{{ __('farmer.predictions.chance') }}</dt><dd>{{ $day['chance'] === null ? '—' : $num($day['chance']).'%' }}</dd></div>
                                        <div><dt>{{ __('farmer.predictions.wind') }}</dt><dd>{{ $day['wind_kmh'] === null ? '—' : $num($day['wind_kmh'], 1).' km/h' }}</dd></div>
                                    </dl>
                                @endif
                            @endif
                        </li>
                    @endforeach
                </ol>
                @if ($forecast['estimated'] > 0)
                    <p class="fad-support wpx-note" data-wpx-estimate-note>{{ __('farmer.predictions.estimate_note') }}</p>
                @endif
            @endif
        </section>

        {{-- Expected yield this season: one big t/ha number and Normal / Mas mataas / Mas mababa. --}}
        <section class="fad-card wpx-panel" id="yield" aria-labelledby="wpxYieldTitle" data-wpx-yield>
            <h2 id="wpxYieldTitle">{{ __('farmer.predictions.yield_title') }}</h2>
            <p class="fad-support">{{ $y['season'] }}@if ($staff) · {{ $barangay ? $barangay.', Lian' : __('farmer.predictions.all_lian') }}@endif</p>
            @if ($y['state'] === 'ok')
                <p class="wpx-big">{{ number_format($y['yield'], 2) }} <small>t/ha</small></p>
                @if ($y['compare_label'])
                    <p class="wpx-compare is-{{ ['typical' => 'info', 'above' => 'good', 'below' => 'watch'][$y['compare']] }}">{{ $y['compare_label'] }}</p>
                    <p class="fad-support">{{ __('farmer.home.harvest.compare_note') }}@if ($staff && $y['usual'] !== null) ({{ number_format($y['usual'], 2) }} t/ha)@endif</p>
                @endif
                @if ($isFarmer && $profileFarm && $profileFarm['area'])
                    <p class="fad-support">{{ __('farmer.predictions.yield_profile', ['area' => number_format($profileFarm['area'], 2), 'type' => $profileFarm['type'] ?? '—']) }}
                        <a href="{{ route('profile.edit') }}">{{ __('farmer.predictions.edit_profile') }}</a></p>
                @endif
                @if ($staff)<p class="fad-support">{{ __('farmer.predictions.yield_average', ['count' => $y['count']]) }}</p>@endif
                <x-updated-at :at="$y['ran_at']" :label="__('farmer.home.updated')" />
            @elseif ($y['state'] === 'loading')
                <div class="ic-skel-lines" role="status"><span></span><span></span><span class="visually-hidden">{{ __('farmer.home.harvest.preparing') }}</span></div>
                <p class="fad-support">{{ __('farmer.home.harvest.preparing') }}</p>
            @else
                <x-no-data :label="$noData" :note="$y['reason'] ?? null" />
                @if ($isFarmer && ($y['needs_area'] ?? false))<a class="fad-btn is-quiet" href="{{ route('profile.edit') }}">{{ __('farmer.home.harvest.add_area') }}</a>@endif
            @endif
        </section>

        @if ($t)
            {{-- IT only: what made these numbers; always visible and missing metadata stays visually compact. --}}
            <section class="fad-card wpx-tech" data-wpx-technical aria-labelledby="wpxTechnicalTitle">
                <h2 class="wpx-tech-title" id="wpxTechnicalTitle">Technical Details</h2>
                @foreach (['weather' => __('farmer.predictions.tech_weather'), 'yield' => __('farmer.predictions.tech_yield')] as $key => $heading)
                    @php $m = $t[$key]; $nr = '—'; @endphp
                    <h3>{{ $heading }}</h3>
                    <dl class="wpx-tech-grid">
                        <div><dt>{{ __('farmer.predictions.tech_model') }}</dt><dd>{{ $m['model'] ?? $nr }}</dd></div>
                        <div><dt>{{ __('farmer.predictions.tech_version') }}</dt><dd>{{ $m['version'] ?? $nr }}</dd></div>
                        @if ($key === 'weather')
                            <div><dt>{{ __('farmer.predictions.tech_confidence') }}</dt><dd>{{ $m['confidence'] === null ? $nr : $m['confidence'].'%' }}</dd></div>
                            <div><dt>{{ __('farmer.predictions.tech_training') }}</dt><dd>{{ $m['training'] ?? $nr }}</dd></div>
                        @endif
                    </dl>
                @endforeach
                <p class="fad-support"><a href="{{ route('model-evaluation.index') }}">Model Evaluation</a></p>
            </section>
        @endif
    </div>
    @unless ($isFarmer)
    </div>
    @endunless
</x-app-layout>
