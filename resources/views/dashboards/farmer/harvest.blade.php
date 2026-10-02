@php
    $h = $f['harvest'];
    $title = __('farmer.harvest.title');
@endphp
@if ($h['state'] === 'loading')
    {{-- The harvest model is slow: the page opens first and this card fills in a moment later. --}}
    <section class="mfd-card is-skeleton" aria-labelledby="mfdHarvestTitle" aria-busy="true" data-mfd-harvest data-mfd-load="harvest">
        <div class="mfd-card-head"><h2 id="mfdHarvestTitle">{{ $title }}</h2></div>
        <div class="mfd-skel mfd-skel-big"></div><div class="mfd-skel"></div><div class="mfd-skel mfd-skel-short"></div>
        <p class="mfd-sr-only" role="status">{{ __('farmer.state.loading') }}</p>
        <noscript><p class="mfd-muted">{{ __('farmer.harvest.needs_script') }}</p></noscript>
    </section>
@elseif ($h['state'] === 'error')
    <section class="mfd-card mfd-card-state" aria-labelledby="mfdHarvestTitle" data-mfd-harvest data-mfd-state="error">
        <div class="mfd-card-head"><h2 id="mfdHarvestTitle">{{ $title }}</h2></div>
        <div class="mfd-empty is-error" role="status">
            @include('dashboards.mao.icon', ['name' => 'info'])
            <div>
                <p><strong>{{ __('farmer.harvest.not_ready') }}</strong></p>
                <p class="mfd-muted">{{ $h['reason'] }}</p>
            </div>
            <div class="mfd-empty-actions">
                @if (in_array('area', $h['missing'], true))<a class="mfd-btn" href="{{ route('profile.edit') }}">{{ __('farmer.harvest.add_area') }}</a>@endif
                <button type="button" class="mfd-btn is-quiet" data-mfd-retry="harvest">@include('dashboards.mao.icon', ['name' => 'refresh']){{ __('farmer.state.retry') }}</button>
            </div>
        </div>
    </section>
@else
    @php $o = $h['outlook']; @endphp
    <section class="mfd-card" aria-labelledby="mfdHarvestTitle" data-mfd-harvest>
        <div class="mfd-card-head">
            <h2 id="mfdHarvestTitle">{{ $title }}</h2>
            <a class="mfd-link" href="{{ route('weather-predictions.index', ['tab' => 'rice-yield']) }}">{{ __('farmer.harvest.see_more') }}</a>
        </div>
        <div class="mfd-harvest-main">
            <div class="mfd-harvest-number">
                <span class="mfd-kpi-value"><span>{{ number_format($h['yield'], 1) }}</span><small>t/ha</small></span>
                @if ($h['range'])<span class="mfd-range">{{ __('farmer.harvest.range', ['low' => number_format($h['range']['low'], 1), 'high' => number_format($h['range']['high'], 1)]) }}</span>@endif
            </div>
            <div>
                <p><strong>{{ $h['period'] ? __('farmer.harvest.for_month', ['month' => $h['period']]) : __('farmer.harvest.expected') }}</strong></p>
                <p class="mfd-muted">{{ __('farmer.harvest.meaning') }}</p>
                @if ($h['reliability'])<p class="mfd-muted">{{ __('farmer.harvest.reliability', ['level' => strtolower($h['reliability'])]) }}</p>@endif
            </div>
        </div>

        @if ($h['production'] !== null)
            <p class="mfd-total">@include('dashboards.mao.icon', ['name' => 'yield'])<span>{{ __('farmer.harvest.total', ['tons' => number_format($h['production'], 1), 'area' => number_format($h['area'], 2)]) }}</span></p>
        @else
            <p class="mfd-total is-hint">@include('dashboards.mao.icon', ['name' => 'info'])<span>{{ __('farmer.harvest.total_missing') }} <a class="mfd-link" href="{{ route('profile.edit') }}">{{ __('farmer.harvest.add_area') }}</a></span></p>
        @endif

        @if ($o)
            @php $max = max($o['historical'], $o['predicted'], $o['own']['average'] ?? 0); $scope = $o['same_month'] ? __('farmer.harvest.avg_month', ['month' => $o['month_name']]) : __('farmer.harvest.avg_all'); @endphp
            <div class="mfd-compare">
                <h3>{{ __('farmer.harvest.compare_title') }}</h3>
                <p class="mfd-takeaway">{{ $o['difference'] >= 0 ? __('farmer.harvest.above', ['percent' => abs($o['percent']), 'avg' => number_format($o['historical'], 1), 'scope' => $scope]) : __('farmer.harvest.below', ['percent' => abs($o['percent']), 'avg' => number_format($o['historical'], 1), 'scope' => $scope]) }}</p>
                <div class="mfd-bar-row">
                    <div class="mfd-bar-label">{{ $scope }} <span>{{ number_format($o['historical'], 1) }} t/ha</span></div>
                    <div class="mfd-bar" role="img" aria-label="{{ $scope }} {{ number_format($o['historical'], 1) }} t/ha"><i @style(['width: '.round($o['historical'] / $max * 100).'%'])></i></div>
                </div>
                <div class="mfd-bar-row">
                    <div class="mfd-bar-label">{{ __('farmer.harvest.yours') }} <span>{{ number_format($o['predicted'], 1) }} t/ha</span></div>
                    <div class="mfd-bar is-mine" role="img" aria-label="{{ __('farmer.harvest.yours') }} {{ number_format($o['predicted'], 1) }} t/ha"><i @style(['width: '.round($o['predicted'] / $max * 100).'%'])></i></div>
                </div>
                @if ($o['own'])
                    <div class="mfd-bar-row">
                        <div class="mfd-bar-label">{{ __('farmer.harvest.own_past', ['count' => $o['own']['count']]) }} <span>{{ number_format($o['own']['average'], 1) }} t/ha</span></div>
                        <div class="mfd-bar is-own" role="img" aria-label="{{ __('farmer.harvest.own_past', ['count' => $o['own']['count']]) }} {{ number_format($o['own']['average'], 1) }} t/ha"><i @style(['width: '.min(100, round($o['own']['average'] / $max * 100)).'%'])></i></div>
                    </div>
                @endif
                <p class="mfd-foot">{{ __('farmer.harvest.avg_foot', ['from' => $o['from'], 'to' => $o['to']]) }}</p>
            </div>
        @endif

        <p class="mfd-foot">{{ __('farmer.harvest.source', ['time' => $h['ran_at'] ? $h['ran_at']->format('M j, Y, g:i A') : __('farmer.harvest.time_unknown')]) }}</p>
    </section>
@endif
