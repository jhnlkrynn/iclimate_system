@php
    $w = $f['weather'];
    $ok = $w['state'] === 'ok';
    $alerts = $f['alerts'];
    $levelLabel = ['danger' => __('farmer.status.danger'), 'watch' => __('farmer.status.watch')];
    $fmt = \App\Services\Analytics\FarmerKpiService::class;
    // Today's three numbers, each with one plain word. A number the weather service did not give is left out, never shown as 0.
    $conditions = $ok ? array_values(array_filter([
        ['key' => 'temperature', 'icon' => 'temperature', 'value' => $fmt::formatTemperature($w['temperature']).'°C', 'level' => $w['levels']['temperature']],
        $w['rain_today'] !== null ? ['key' => 'rain', 'icon' => 'rain', 'value' => $fmt::formatRain($w['rain_today']).' mm', 'level' => $w['levels']['rain']] : null,
        $w['humidity'] !== null ? ['key' => 'humidity', 'icon' => 'humidity', 'value' => $fmt::formatPercent($w['humidity']).'%', 'level' => $w['levels']['humidity']] : null,
    ])) : [];
@endphp
<div class="mfd-today">
    {{-- Left: today's conditions. The same reading feeds the Weather tile above, the sentence and today's rain. --}}
    @if ($ok)
        <section class="mfd-card mfd-now" aria-labelledby="mfdNowTitle" data-mfd-now>
            <div class="mfd-card-head">
                <h2 id="mfdNowTitle">{{ __('farmer.now.title') }}</h2>
                @if ($w['stale'])@include('dashboards.farmer._status', ['level' => 'watch', 'label' => __('farmer.header.old_data')])@endif
            </div>
            <ul class="mfd-conditions">
                @foreach ($conditions as $c)
                    <li data-condition="{{ $c['key'] }}">
                        <span class="mfd-metric-label">@include('dashboards.mao.icon', ['name' => $c['icon']]){{ __('farmer.now.'.$c['key']) }}</span>
                        <strong>{{ $c['value'] }}</strong>
                        <span class="mfd-condition-level">{{ __('farmer.now.level.'.$c['key'].'.'.$c['level']) }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="mfd-now-summary" role="img" aria-label="{{ $w['now_summary'] }}">{{ $w['now_summary'] }}</p>
            <p class="mfd-foot">{{ __('farmer.now.source', ['source' => __('farmer.source.weather'), 'time' => $w['updated_label'] ?? '—']) }}</p>
            <a class="mfd-link" href="{{ route('weather-predictions.index', ['tab' => 'weather-forecast']) }}">{{ __('farmer.now.see_full') }}</a>
        </section>
    @else
        @include('dashboards.farmer._error', ['retry' => 'today', 'title' => __('farmer.now.title')])
    @endif

    {{-- Right: what the farmer should know today. Real warnings always come first and are never left out. --}}
    <section class="mfd-card mfd-advice" aria-labelledby="mfdAdviceTitle" data-mfd-advice data-kpi="alerts">
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
