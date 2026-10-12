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
        $municipal = ($y['source'] ?? null) === 'municipal_model';
        $noData = $isFarmer ? __('farmer.home.no_data') : ($municipal && ($y['state'] ?? null) === 'error' ? ($y['reason'] ?? __('farmer.predictions.yield_unavailable')) : 'Walang datos pa');
    @endphp

    {{-- Same page styling as before: farmers get the farmer page header; MAO and IT keep the staff page header. --}}
    @unless ($isFarmer)
    <div class="mfd itd itp">
        <x-it.page-header kicker="Weather and Rice Yield" title="Predictions" lede="The coming days' weather and this season's expected harvest for Lian." />
    @endunless
    <div @class(['fad', 'wpx', 'is-farmer' => $isFarmer]) data-predictions>
        {{-- Manuscript – Naicel (2026-10-08): Weather Analysis, Yield Prediction / Impact Analysis, Yield Forecast tabs. --}}
        @include('partials.manuscript-tabs', ['group' => 'analysis', 'label' => 'Predictions and analysis'])
        @if ($isFarmer)
            {{-- Farmers (2026-10): the week's answer first, in one sentence, with what to do (WeatherPredictionController::plainForecast). --}}
            @php $answer = $plain['answer']; @endphp
            <header class="wpx-answer is-{{ $answer['tone'] }}" data-wpx-answer="{{ $answer['key'] }}">
                <span class="wpx-answer-icon" aria-hidden="true">@include('dashboards.mao.icon', ['name' => $answer['icon']])</span>
                <div class="wpx-answer-text">
                    <p class="wpx-answer-place">{{ __('farmer.predictions.for_place', ['place' => $forecast['location']]) }}</p>
                    <h1>{{ $answer['headline'] }}</h1>
                    <p class="wpx-answer-action">{{ $answer['action'] }}</p>
                </div>
            </header>
        @endif

        {{-- Farmer – Naicel (2026-10): "Tama ba ang taya ng panahon kahapon?" — small, can be skipped, one answer per day. --}}
        @if ($isFarmer && ! empty($forecastSurvey))
            @include('weather-predictions.partials.forecast-survey', ['survey' => $forecastSurvey])
        @endif

        {{-- Weather: the stored daily forecast, days 1–7 or 1–14. --}}
        <section class="fad-card wpx-panel" id="weather" aria-labelledby="wpxWeatherTitle" data-wpx-weather>
            <div class="wpx-head">
                <div>
                    <h2 id="wpxWeatherTitle">{{ $isFarmer ? __('farmer.predictions.days_title') : __('farmer.predictions.weather_title') }}</h2>
                    @unless ($isFarmer)
                    <p class="fad-support">{{ __('farmer.predictions.for_place', ['place' => $forecast['location']]) }}
                        @if ($forecast['updated_at']) · <x-updated-at :at="$forecast['updated_at']" :label="__('farmer.home.updated')" />@endif</p>
                    @endunless
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
                @if ($isFarmer)
                    {{-- Farmers: big day cards — day, one icon, one word, one temperature. The numbers are under "More details". --}}
                    <ol class="wpx-days is-plain" data-days="{{ count($forecast['days']) }}">
                        @foreach ($forecast['days'] as $i => $day)
                            @php $plainDay = $plain['days'][$i]; @endphp
                            <li @class(['wpx-day', 'is-today' => $i === 0, 'is-estimate' => $day['kind'] === 'estimate', 'is-empty' => $day['kind'] === 'none']) data-kind="{{ $day['kind'] }}">
                                <span class="wpx-day-name">{{ match ($i) { 0 => __('farmer.predictions.today'), 1 => __('farmer.predictions.tomorrow'), default => $day['date']->locale(app()->getLocale())->translatedFormat('D') } }}</span>
                                <span class="wpx-day-date">{{ $day['date']->locale(app()->getLocale())->translatedFormat('M j') }}</span>
                                <span class="wpx-day-icon is-{{ $plainDay['icon'] }}" aria-hidden="true">@include('dashboards.mao.icon', ['name' => $plainDay['icon']])</span>
                                <span class="wpx-day-words">{{ $plainDay['word'] }}</span>
                                @if ($day['kind'] !== 'none' && $day['high'] !== null)<span class="wpx-day-temp">{{ $num($day['high']) }}°</span>@endif
                                @if ($day['kind'] === 'estimate')<span class="wpx-badge">{{ __('farmer.predictions.estimate_badge') }}</span>@endif
                            </li>
                        @endforeach
                    </ol>
                    <details class="wpx-more" data-wpx-more>
                        <summary>{{ __('farmer.predictions.more') }}</summary>
                        <div class="wpx-more-scroll">
                            <table class="wpx-more-table">
                                <thead><tr>
                                    <th scope="col">{{ __('farmer.predictions.day') }}</th>
                                    <th scope="col">{{ __('farmer.predictions.weather') }}</th>
                                    <th scope="col">{{ __('farmer.predictions.rain') }}</th>
                                    <th scope="col">{{ __('farmer.predictions.chance') }}</th>
                                    <th scope="col">{{ __('farmer.predictions.wind') }}</th>
                                    <th scope="col">{{ __('farmer.predictions.temp') }}</th>
                                </tr></thead>
                                <tbody>
                                    @foreach ($forecast['days'] as $i => $day)
                                        <tr>
                                            <th scope="row">{{ $i === 0 ? __('farmer.predictions.today') : $day['date']->locale(app()->getLocale())->translatedFormat('D, M j') }}</th>
                                            @if ($day['kind'] === 'none')
                                                <td colspan="5">{{ $noData }}</td>
                                            @else
                                                <td>{{ $day['condition'] }}</td>
                                                <td>{{ collect([$day['rain_words'], $day['rain_mm'] === null ? null : $num($day['rain_mm'], 1).' mm'])->filter()->implode(' · ') ?: '—' }}</td>
                                                <td>{{ $day['chance'] === null ? '—' : $num($day['chance']).'%' }}</td>
                                                <td>{{ collect([$day['wind_words'], $day['wind_kmh'] === null ? null : $num($day['wind_kmh'], 1).' km/h'])->filter()->implode(' · ') ?: '—' }}</td>
                                                <td>{{ $day['high'] === null ? '—' : $num($day['high']).'° / '.$num($day['low']).'°' }}</td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if ($forecast['updated_at'])<p class="fad-support"><x-updated-at :at="$forecast['updated_at']" :label="__('farmer.home.updated')" /></p>@endif
                    </details>
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
                @endif
                @if ($forecast['estimated'] > 0)
                    <p class="fad-support wpx-note" data-wpx-estimate-note>{{ __('farmer.predictions.estimate_note') }}</p>
                @endif
            @endif
        </section>

        {{-- Expected yield this season: one big t/ha number and Normal / Mas mataas / Mas mababa. --}}
        <section class="fad-card wpx-panel" id="yield" aria-labelledby="wpxYieldTitle" data-wpx-yield
            @if ($municipal && ($y['state'] === 'loading' || ($y['stale'] ?? false))) data-municipal-yield-poll="{{ route('weather-predictions.municipality-yield-status') }}" @endif>
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
                @if ($staff)
                    <p class="fad-support">
                        {{ ($y['source'] ?? null) === 'municipal_model' ? __('farmer.predictions.yield_model_source') : __('farmer.predictions.yield_average', ['count' => $y['count']]) }}
                    </p>
                    @if (($y['stale'] ?? false))<p class="fad-support">{{ __('farmer.predictions.yield_model_stale') }}</p>@endif
                @endif
                <x-updated-at :at="$y['ran_at']" :label="__('farmer.home.updated')" />
            @elseif ($y['state'] === 'loading')
                <div class="ic-skel-lines" role="status"><span></span><span></span><span class="visually-hidden">{{ __('farmer.home.harvest.preparing') }}</span></div>
                <p class="fad-support" data-municipal-yield-message>{{ ($municipal ? __('farmer.predictions.yield_model_preparing') : ($y['reason'] ?? __('farmer.home.harvest.preparing'))) }}</p>
            @else
                <x-no-data :label="$noData" :note="$municipal ? null : ($y['reason'] ?? null)" />
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

@if ($municipal && ($y['state'] === 'loading' || ($y['stale'] ?? false)))
<script>
(() => {
    const card = document.querySelector('[data-municipal-yield-poll]');
    if (!card) return;
    const url = card.dataset.municipalYieldPoll;
    let timer = null, running = false, stopped = false;
    const stop = () => { stopped = true; if (timer) { clearTimeout(timer); timer = null; } };
    const formatTime = value => value ? new Date(value).toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'}) : '';
    const showReady = data => {
        const loading = card.querySelector('.ic-skel-lines');
        if (loading) loading.remove();
        let value = card.querySelector('.wpx-big');
        if (!value) { value = document.createElement('p'); value.className = 'wpx-big'; card.querySelector('#wpxYieldTitle').after(value); }
        value.textContent = `${Number(data.yield).toFixed(2)} `;
        const unit = document.createElement('small'); unit.textContent = data.unit || 't/ha'; value.append(unit);
        const message = card.querySelector('[data-municipal-yield-message]');
        if (message) message.textContent = `Expected average rice yield${data.generated_at ? ` · Updated ${formatTime(data.generated_at)}` : ''}`;
        if (!data.refreshing) stop();
    };
    const showUnavailable = data => {
        const loading = card.querySelector('.ic-skel-lines');
        if (loading) loading.remove();
        const message = card.querySelector('[data-municipal-yield-message]');
        if (message) message.textContent = data.message || 'Yield forecast is temporarily unavailable.';
        stop();
    };
    const poll = async () => {
        if (stopped || document.hidden || running) return;
        running = true;
        try {
            const response = await fetch(url, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'});
            const data = await response.json();
            if (data.status === 'ready') showReady(data);
            else if (data.status === 'unavailable') showUnavailable(data);
        } catch (_) { /* keep the temporary state; the next check retries */ }
        running = false;
        if (!stopped && !document.hidden) timer = setTimeout(poll, 2000);
    };
    document.addEventListener('visibilitychange', () => { if (!document.hidden && !stopped) poll(); });
    timer = setTimeout(poll, 2000);
    setTimeout(stop, 120000);
})();
</script>
@endif
