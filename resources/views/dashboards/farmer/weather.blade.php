{{-- "Weather for Lian, Batangas": the details behind the Weather Now and Rain Today numbers (humidity, wind, one plain
     sentence) and the next few days. The full week is on the weather forecast page. Same reading as the numbers above. --}}
@php
    $w = $f['weather'];
    $week = $f['week'];
    $fmt = \App\Services\Analytics\FarmerKpiService::class;
    $title = __('farmer.weather_card.title');
    $href = route('weather-predictions.index', ['tab' => 'weather-forecast']);
@endphp
@if ($w['state'] !== 'ok')
    @include('dashboards.farmer._error', ['retry' => 'weather', 'title' => $title, 'reason' => __('farmer.weather_card.error')])
@else
    <section class="mfd-card mfd-weather" aria-labelledby="mfdWeatherTitle" data-mfd-weather>
        <div class="mfd-card-head">
            <h2 id="mfdWeatherTitle">{{ $title }}</h2>
            @if ($w['stale'])@include('dashboards.farmer._status', ['level' => 'watch', 'label' => __('farmer.header.old_data')])@endif
        </div>

        {{-- The temperature and today's rain are in "My Farm Today" just above, so this card adds what they leave out. --}}
        <div class="mfd-wx-now">
            <span class="mfd-wx-icon" aria-hidden="true">@include('dashboards.mao.icon', ['name' => $w['icon']])</span>
            <p class="mfd-now-summary">{{ $w['now_summary'] }}</p>
        </div>
        <dl class="mfd-wx-facts">
            @if ($w['humidity'] !== null)<div><dt>{{ __('farmer.weather_card.humidity') }}</dt><dd>{{ $fmt::formatPercent($w['humidity']) }}%</dd><dd class="mfd-wx-note">{{ $w['humidity_meaning'] }}</dd></div>@endif
            @if ($w['wind'] !== null)<div><dt>{{ __('farmer.weather_card.wind') }}</dt><dd>{{ number_format($w['wind'], 0) }} km/h</dd><dd class="mfd-wx-note">{{ $w['wind_meaning'] }}</dd></div>@endif
            @if (($w['heat']['value'] ?? null) !== null)<div><dt>{{ __('farmer.weather_card.heat') }}</dt><dd>{{ $w['heat']['display'] }}°C</dd><dd class="mfd-wx-note">{{ $w['heat']['label'] }}</dd></div>@endif
        </dl>

        <h3 class="mfd-sub">{{ __('farmer.weather_card.next_days') }}</h3>
        @if ($week['state'] === 'ok')
            <ol class="mfd-days">
                @foreach ($week['preview'] as $day)
                    <li @class(['mfd-day', 'is-today' => $day['is_today']])>
                        <strong class="mfd-day-name">{{ $day['is_today'] ? __('farmer.weather_card.today') : $day['date']->format('D') }}</strong>
                        <span class="mfd-day-date">{{ $day['date']->format('M j') }}</span>
                        <span class="mfd-day-icon" aria-hidden="true">@include('dashboards.mao.icon', ['name' => $day['icon']])</span>
                        @if ($day['high'] !== null)
                            <span class="mfd-day-temp"><strong>{{ number_format($day['high'], 0) }}°</strong>@if ($day['low'] !== null) / {{ number_format($day['low'], 0) }}°@endif</span>
                        @endif
                        <span class="mfd-day-cond">{{ $day['condition'] }}</span>
                        @if ($day['chance'] !== null || $day['rain'] !== null)
                            <span class="mfd-day-rain">{{ $day['chance'] !== null ? __('farmer.weather_card.chance_short', ['percent' => number_format($day['chance'], 0)]) : '' }}@if ($day['chance'] !== null && $day['rain'] !== null) • @endif{{ $day['rain'] !== null ? $fmt::formatRain($day['rain']).' mm' : '' }}</span>
                        @endif
                        @if ($day['flags'] !== [] || $day['good'])
                            <span class="mfd-day-tags">
                                @foreach ($day['flags'] as $flag)
                                    <span class="mfd-pill is-watch">@include('dashboards.mao.icon', ['name' => 'alert'])<span>{{ __('farmer.weather_card.flag_'.$flag) }}</span></span>
                                @endforeach
                                @if ($day['good'] && $day['flags'] === [])
                                    <span class="mfd-pill is-good">@include('dashboards.mao.icon', ['name' => 'check'])<span>{{ __('farmer.weather_card.good_day') }}</span></span>
                                @endif
                            </span>
                        @endif
                    </li>
                @endforeach
            </ol>
            <p class="mfd-takeaway">{{ $week['takeaway'] }}</p>
        @else
            <div class="mfd-empty">@include('dashboards.mao.icon', ['name' => 'info'])<p>{{ __('farmer.weather_card.empty') }}</p></div>
        @endif

        {{-- This month in Lian: the Predictions page's monthly estimate, compared with past years by the same rule. --}}
        @if ($c = $f['climate'] ?? null)
            <div class="mfd-climate" data-mfd-climate>
                <h3 class="mfd-sub mfd-climate-head">{{ __('farmer.climate.title') }}</h3>
                <p><strong>{{ __('farmer.climate.rain', ['month' => $c['month'], 'mm' => number_format($c['rain'], 0)]) }}.</strong>
                    @if ($c['rain_status']){{ __('farmer.climate.status.'.$c['rain_status'], ['month' => $c['month'], 'usual' => number_format($c['usual_rain'], 0)]) }}@endif
                    {{ __('farmer.climate.todo.'.($c['rain_status'] ?? 'none')) }}</p>
                <p class="mfd-muted">
                    @if ($c['low'] !== null && $c['high'] !== null){{ __('farmer.climate.temp', ['low' => number_format($c['low'], 0), 'high' => number_format($c['high'], 0)]) }}@endif
                    @if ($c['season']){{ __('farmer.climate.season.'.$c['season']) }}@endif
                </p>
                <p class="mfd-foot">@include('dashboards.mao.icon', ['name' => 'info'])<span>{{ $c['rain_status'] ? __('farmer.climate.basis', ['month' => $c['month'], 'span' => $c['span']]) : __('farmer.climate.basis_plain') }}</span></p>
            </div>
        @endif

        <div class="mfd-card-foot">
            <p class="mfd-foot">@include('dashboards.mao.icon', ['name' => 'info'])<span>{{ __('farmer.weather_card.scope') }} {{ __('farmer.weather_card.source', ['source' => $w['provider'], 'time' => $w['updated_label'] ?? '—']) }}</span></p>
            <a class="mfd-btn is-quiet" href="{{ $href }}">{{ __('farmer.weather_card.view_full') }}</a>
        </div>
    </section>
@endif
