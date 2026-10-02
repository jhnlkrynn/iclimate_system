@php
    $w = $f['weather'];
    $ok = $w['state'] === 'ok';
    $alerts = $f['alerts'];
    $levelLabel = ['danger' => __('farmer.status.danger'), 'watch' => __('farmer.status.watch')];
    $heatLevelFor = fn (?string $l) => match ($l) { 'normal' => 'good', 'caution', 'extreme_caution' => 'watch', 'danger', 'extreme_danger' => 'danger', default => 'info' };
@endphp
<div class="mfd-today">
    {{-- Left: weather now. One reading feeds the condition, the sentence and today's rain. --}}
    @if ($ok)
        <section class="mfd-card mfd-now" aria-labelledby="mfdNowTitle" data-mfd-now>
            <div class="mfd-card-head">
                <h2 id="mfdNowTitle">{{ __('farmer.now.title') }}</h2>
                @if ($w['stale'])@include('dashboards.farmer._status', ['level' => 'watch', 'label' => __('farmer.header.old_data')])@endif
            </div>
            <div class="mfd-now-main">
                <span class="mfd-now-icon">@include('dashboards.mao.icon', ['name' => $w['icon']])</span>
                <div>
                    <div class="mfd-temp"><span>{{ \App\Services\Analytics\FarmerKpiService::formatTemperature($w['temperature']) }}</span><small>°C</small></div>
                    <div class="mfd-cond">{{ $w['condition'] }}</div>
                </div>
            </div>
            <p class="mfd-now-summary">{{ $w['now_summary'] }}</p>
            <ul class="mfd-metrics">
                @if ($w['feels_like'] !== null)
                    <li>
                        <span class="mfd-metric-label">{{ __('farmer.now.feels_like') }}</span>
                        <strong>{{ \App\Services\Analytics\FarmerKpiService::formatTemperature($w['feels_like']) }}°C</strong>
                        <span class="mfd-metric-note">@include('dashboards.farmer._status', ['level' => $heatLevelFor($w['feels_level']), 'label' => __('farmer.heat.'.$w['feels_level'])])</span>
                    </li>
                @endif
                @if ($w['humidity'] !== null)
                    <li>
                        <span class="mfd-metric-label">{{ __('farmer.now.humidity') }}</span>
                        <strong>{{ \App\Services\Analytics\FarmerKpiService::formatPercent($w['humidity']) }}%</strong>
                        <span class="mfd-metric-note">{{ $w['humidity_meaning'] }}</span>
                    </li>
                @endif
                @if ($w['wind'] !== null)
                    <li>
                        <span class="mfd-metric-label">{{ __('farmer.now.wind') }}</span>
                        <strong>{{ number_format($w['wind'], 0) }} km/h</strong>
                        <span class="mfd-metric-note">{{ $w['wind_meaning'] }}</span>
                    </li>
                @endif
                @if ($w['rain_today'] !== null)
                    <li>
                        <span class="mfd-metric-label">{{ __('farmer.now.rain_today') }}</span>
                        <strong>{{ \App\Services\Analytics\FarmerKpiService::formatRain($w['rain_today']) }} mm</strong>
                        <span class="mfd-metric-note">@if ($w['rain_chance'] !== null){{ __('farmer.now.chance', ['percent' => number_format($w['rain_chance'], 0)]) }}@else{{ __('farmer.now.rain_today_note') }}@endif</span>
                    </li>
                @endif
            </ul>
            <p class="mfd-foot">{{ __('farmer.now.source', ['source' => __('farmer.source.weather'), 'time' => $w['updated_label'] ?? '—']) }}</p>
            <a class="mfd-link" href="{{ route('weather-predictions.index', ['tab' => 'weather-forecast']) }}">{{ __('farmer.now.see_full') }}</a>
        </section>
    @else
        @include('dashboards.farmer._error', ['retry' => 'today', 'title' => __('farmer.now.title')])
    @endif

    {{-- Right: what to do today. --}}
    <section class="mfd-card mfd-advice" aria-labelledby="mfdAdviceTitle" data-mfd-advice>
        <div class="mfd-card-head"><h2 id="mfdAdviceTitle">{{ __('farmer.advice.title') }}</h2></div>

        @if (count($alerts) === 0)
            <p class="mfd-warnings">@include('dashboards.farmer._status', ['level' => 'good', 'label' => __('farmer.alerts.none')])</p>
        @else
            <ul class="mfd-alert-list" aria-label="{{ __('farmer.alerts.list_label') }}">
                @foreach ($alerts as $alert)
                    <li class="mfd-alert is-{{ $alert['level'] }}">
                        <div class="mfd-alert-body">
                            @include('dashboards.farmer._status', ['level' => $alert['level'], 'label' => $levelLabel[$alert['level']]])
                            <strong>{{ $alert['title'] }}</strong>
                            <span>{{ $alert['message'] }}</span>
                        </div>
                        <a class="mfd-btn is-quiet" href="{{ $alert['href'] }}">{{ __('farmer.alerts.details') }}</a>
                    </li>
                @endforeach
            </ul>
        @endif

        <ol class="mfd-actions-list">
            @foreach ($f['actions'] as $action)
                <li class="mfd-action">
                    <span class="mfd-action-icon">@include('dashboards.mao.icon', ['name' => $action['icon']])</span>
                    <div>
                        <h3>{{ $action['title'] }}</h3>
                        <p>{{ $action['reason'] }}</p>
                        <button type="button" class="mfd-link is-button" data-ai-prefill="{{ $action['ask'] }}">@include('dashboards.mao.icon', ['name' => 'sparkle']){{ __('farmer.advice.ask') }}</button>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>
</div>
