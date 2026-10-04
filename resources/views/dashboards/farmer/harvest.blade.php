{{-- "Rice Yield Forecast": the same estimate the Predictions page shows (RiceYieldPredictionService), in the same unit and
     decimals. No model details: the number, what month it is for, how it reads against the usual yield, and one comparison. --}}
@php
    $h = $f['harvest'];
    $title = __('farmer.harvest.title');
    $farmInfo = ['data-mfd-popup' => 'farm-size', 'data-mfd-popup-variant' => 'info', 'data-mfd-popup-title' => __('farmer.quick.farm.popup_title'), 'data-mfd-popup-intro' => __('farmer.quick.farm.popup_intro')];
@endphp
@if ($h['state'] === 'loading')
    {{-- The estimate is slow: the page opens first and this card fills in a moment later. --}}
    <section class="mfd-card is-skeleton" aria-labelledby="mfdHarvestTitle" aria-busy="true" data-mfd-harvest data-mfd-load="harvest">
        <div class="mfd-card-head"><h2 id="mfdHarvestTitle">{{ $title }}</h2></div>
        <div class="mfd-skel mfd-skel-big"></div><div class="mfd-skel"></div><div class="mfd-skel mfd-skel-short"></div>
        <p class="mfd-sr-only" role="status">{{ __('farmer.state.loading') }}</p>
        <noscript><p class="mfd-muted">{{ __('farmer.harvest.needs_script') }}</p></noscript>
    </section>
@elseif ($h['state'] === 'error')
    <section class="mfd-card mfd-card-state" aria-labelledby="mfdHarvestTitle" data-mfd-harvest data-mfd-state="error">
        <div class="mfd-card-head"><h2 id="mfdHarvestTitle">{{ $title }}</h2></div>
        <div class="mfd-empty" role="status">
            @include('dashboards.mao.icon', ['name' => 'info'])
            <div>
                <p><strong>{{ __('farmer.harvest.not_ready') }}</strong></p>
                <p class="mfd-muted">{{ $h['reason'] }}</p>
            </div>
            <div class="mfd-empty-actions">
                @if (in_array('area', $h['missing'], true))
                    <a class="mfd-btn" href="{{ route('profile.edit') }}" aria-haspopup="dialog" data-fast-nav-bound="true" @foreach ($farmInfo as $attr => $value) {{ $attr }}="{{ $value }}" @endforeach>{{ __('farmer.harvest.complete') }}</a>
                @elseif (in_array('planting', $h['missing'], true))
                    <a class="mfd-btn" href="{{ route('farmer.planting-schedules.create') }}" data-mfd-popup="planting" aria-haspopup="dialog" data-fast-nav-bound="true">{{ __('farmer.harvest.complete') }}</a>
                @endif
                <button type="button" class="mfd-btn is-quiet" data-mfd-retry="harvest">@include('dashboards.mao.icon', ['name' => 'refresh']){{ __('farmer.state.retry') }}</button>
            </div>
        </div>
    </section>
@else
    @php
        $o = $h['outlook'];
        $typical = $h['typical'] ?? null;
        $typicalLevel = ['above' => 'good', 'typical' => 'good', 'below' => 'info'];
    @endphp
    <section class="mfd-card" aria-labelledby="mfdHarvestTitle" data-mfd-harvest>
        <div class="mfd-card-head">
            <h2 id="mfdHarvestTitle">{{ $title }}</h2>
            <a class="mfd-link" href="{{ route('weather-predictions.index', ['tab' => 'rice-yield']) }}">{{ __('farmer.harvest.see_more') }}</a>
        </div>
        <div class="mfd-harvest-main">
            <div class="mfd-harvest-number">
                <span class="mfd-kpi-label">{{ __('farmer.harvest.estimated') }}</span>
                <span class="mfd-kpi-value"><span>{{ number_format($h['yield'], 2) }}</span><small>{{ __('farmer.harvest.unit') }}</small></span>
                @if ($h['range'])<span class="mfd-range">{{ __('farmer.harvest.range', ['low' => number_format($h['range']['low'], 2), 'high' => number_format($h['range']['high'], 2)]) }}</span>@endif
            </div>
            <div class="mfd-harvest-reading">
                <p><strong>{{ $h['period'] ? __('farmer.harvest.for_month', ['month' => $h['period']]) : __('farmer.harvest.expected') }}</strong></p>
                @if ($typical)
                    <p class="mfd-harvest-typical"><span>{{ __('farmer.harvest.typical_label') }}</span> @include('dashboards.farmer._status', ['level' => $typicalLevel[$typical], 'label' => __('farmer.harvest.typical.'.$typical)])</p>
                    @if ($typical === 'below')<p>{{ __('farmer.harvest.typical_below_note') }}</p>@endif
                @endif
                <p class="mfd-muted">{{ __('farmer.harvest.meaning') }}</p>
            </div>
        </div>

        @if ($h['production'] !== null)
            <p class="mfd-total">@include('dashboards.mao.icon', ['name' => 'yield'])<span>{{ __('farmer.harvest.total', ['tons' => number_format($h['production'], 1), 'area' => number_format($h['area'], 2)]) }}</span></p>
        @else
            <p class="mfd-total is-hint">@include('dashboards.mao.icon', ['name' => 'info'])<span>{{ __('farmer.harvest.total_missing') }} <a class="mfd-link" href="{{ route('profile.edit') }}" data-mfd-popup="farm-size" aria-haspopup="dialog" data-fast-nav-bound="true">{{ __('farmer.harvest.add_area') }}</a></span></p>
        @endif

        @if ($o)
            @php $max = max($o['historical'], $o['predicted'], $o['own']['average'] ?? 0); $scope = $o['same_month'] ? __('farmer.harvest.avg_month', ['month' => $o['month_name']]) : __('farmer.harvest.avg_all'); $unit = __('farmer.harvest.unit'); @endphp
            <div class="mfd-compare">
                <h3>{{ __('farmer.harvest.compare_title') }}</h3>
                <div class="mfd-bar-row">
                    <div class="mfd-bar-label">{{ $scope }} <span>{{ number_format($o['historical'], 2) }} {{ $unit }}</span></div>
                    <div class="mfd-bar" role="img" aria-label="{{ $scope }} {{ number_format($o['historical'], 2) }} {{ $unit }}"><i @style(['width: '.round($o['historical'] / $max * 100).'%'])></i></div>
                </div>
                <div class="mfd-bar-row">
                    <div class="mfd-bar-label">{{ __('farmer.harvest.yours') }} <span>{{ number_format($o['predicted'], 2) }} {{ $unit }}</span></div>
                    <div class="mfd-bar is-mine" role="img" aria-label="{{ __('farmer.harvest.yours') }} {{ number_format($o['predicted'], 2) }} {{ $unit }}"><i @style(['width: '.round($o['predicted'] / $max * 100).'%'])></i></div>
                </div>
                @if ($o['own'])
                    <div class="mfd-bar-row">
                        <div class="mfd-bar-label">{{ __('farmer.harvest.own_past', ['count' => $o['own']['count']]) }} <span>{{ number_format($o['own']['average'], 2) }} {{ $unit }}</span></div>
                        <div class="mfd-bar is-own" role="img" aria-label="{{ __('farmer.harvest.own_past', ['count' => $o['own']['count']]) }} {{ number_format($o['own']['average'], 2) }} {{ $unit }}"><i @style(['width: '.min(100, round($o['own']['average'] / $max * 100)).'%'])></i></div>
                    </div>
                @endif
                <p class="mfd-takeaway">{{ $o['difference'] >= 0 ? __('farmer.harvest.above', ['percent' => abs($o['percent']), 'avg' => number_format($o['historical'], 2), 'scope' => $scope]) : __('farmer.harvest.below', ['percent' => abs($o['percent']), 'avg' => number_format($o['historical'], 2), 'scope' => $scope]) }}</p>
                <p class="mfd-foot">{{ __('farmer.harvest.avg_foot', ['from' => $o['from'], 'to' => $o['to']]) }}</p>
            </div>
        @endif

        <p class="mfd-foot">{{ __('farmer.harvest.source', ['time' => $h['ran_at'] ? $h['ran_at']->format('M j, Y, g:i A') : __('farmer.harvest.time_unknown')]) }}</p>
    </section>
@endif
