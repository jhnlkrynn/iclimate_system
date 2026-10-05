{{-- "Rice Yield Forecast": the same estimate the Predictions page shows (RiceYieldPredictionService), in the same unit and
     decimals, with what it covers: the month, the usual range, the tons for the farmer's own farm, how it compares with the
     usual Lian yield, and the latest recorded harvest in the barangay (a past record from Rice Production, labelled so). --}}
@php
    $h = $f['harvest'];
    $p = $f['production'];
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
@else
    <section @class(['mfd-card', 'mfd-harvest', 'mfd-card-state' => $h['state'] === 'error']) aria-labelledby="mfdHarvestTitle" data-mfd-harvest @if ($h['state'] === 'error') data-mfd-state="error" @endif>
        <div class="mfd-card-head">
            <h2 id="mfdHarvestTitle">{{ $title }}</h2>
            <a class="mfd-link" href="{{ route('weather-predictions.index', ['tab' => 'rice-yield']) }}">{{ __('farmer.harvest.view') }}</a>
        </div>

        @if ($h['state'] === 'error')
            {{-- No estimate this season yet: say plainly what it waits for, and show the real harvest records meanwhile
                 (the barangay's latest record and Lian's usual yield for this month), each labelled as a record. --}}
            @php
                $usual = $f['usual_yield'] ?? null;
                $service = $h['missing'] === [] && ($h['cause'] ?? 'service') === 'service';
            @endphp
            <p class="mfd-note" role="status">@include('dashboards.mao.icon', ['name' => 'info'])<span><strong>{{ __('farmer.harvest.soon') }}.</strong> {{ $h['reason'] }}</span></p>
            @if ($p || $usual)
                <p class="mfd-muted mfd-yield-records">{{ __('farmer.harvest.meanwhile') }}</p>
                <dl class="mfd-facts">
                    @if ($p)<div><dt>{{ __('farmer.harvest.past', ['barangay' => $f['barangay']]) }}</dt><dd>{{ number_format($p['yield'], 2) }} {{ __('farmer.harvest.unit') }} <span class="mfd-muted">• {{ $p['when'] }}{{ $p['season'] ?? '' }}</span></dd></div>@endif
                    @if ($usual)<div><dt>{{ $usual['same_month'] ? __('farmer.harvest.usual', ['month' => $usual['month_name']]) : __('farmer.harvest.usual_all') }}</dt><dd>{{ number_format($usual['historical'], 2) }} {{ __('farmer.harvest.unit') }} <span class="mfd-muted">• {{ __('farmer.harvest.usual_years', ['from' => $usual['from'], 'to' => $usual['to']]) }}</span></dd></div>@endif
                </dl>
            @endif
            @if (in_array('area', $h['missing'], true) || $service)
                <div class="mfd-card-foot">
                    @if (in_array('area', $h['missing'], true))
                        <a class="mfd-btn" href="{{ route('profile.edit') }}" aria-haspopup="dialog" data-fast-nav-bound="true" @foreach ($farmInfo as $attr => $value) {{ $attr }}="{{ $value }}" @endforeach>{{ __('farmer.harvest.add_area') }}</a>
                    @endif
                    @if ($service)
                        <button type="button" class="mfd-btn is-quiet" data-mfd-retry="harvest">@include('dashboards.mao.icon', ['name' => 'refresh']){{ __('farmer.state.retry') }}</button>
                    @endif
                </div>
            @endif
            @if ($p)<p class="mfd-foot">{{ __('farmer.production.note') }} <a class="mfd-link" href="{{ route('rice-productions.index') }}">{{ __('farmer.production.view') }}</a></p>@endif
        @else
            @php
                $o = $h['outlook'];
                $typical = $h['typical'] ?? null;
                $typicalLevel = ['above' => 'good', 'typical' => 'good', 'below' => 'info'];
            @endphp
            {{-- The t/ha figure itself is in "My Farm Today"; this card says what it covers and how it compares. --}}
            <dl class="mfd-facts">
                <div><dt>{{ __('farmer.harvest.expected') }}</dt><dd>{{ number_format($h['yield'], 2) }} {{ __('farmer.harvest.unit') }}@if ($h['period']) <span class="mfd-muted">• {{ __('farmer.harvest.for_month', ['month' => $h['period']]) }}</span>@endif</dd></div>
                @if ($h['range'])<div><dt>{{ __('farmer.harvest.typical_label') }}</dt><dd>{{ __('farmer.harvest.range', ['low' => number_format($h['range']['low'], 2), 'high' => number_format($h['range']['high'], 2)]) }}</dd></div>@endif
            </dl>
            @if ($h['production'] !== null)
                <p class="mfd-total">@include('dashboards.mao.icon', ['name' => 'yield'])<span>{{ __('farmer.harvest.total', ['tons' => number_format($h['production'], 1), 'area' => number_format($h['area'], 2)]) }}</span></p>
            @else
                <p class="mfd-total is-hint">@include('dashboards.mao.icon', ['name' => 'info'])<span>{{ __('farmer.harvest.total_missing') }} <a class="mfd-link" href="{{ route('profile.edit') }}" data-mfd-popup="farm-size" aria-haspopup="dialog" data-fast-nav-bound="true">{{ __('farmer.harvest.add_area') }}</a></span></p>
            @endif
            @if ($o)
                <div class="mfd-compare-line">
                    @if ($typical)@include('dashboards.farmer._status', ['level' => $typicalLevel[$typical], 'label' => __('farmer.harvest.typical.'.$typical)])@endif
                    <span>{{ __('farmer.harvest.compare', ['scope' => $o['same_month'] ? $o['month_name'] : __('farmer.harvest.scope_all'), 'avg' => number_format($o['historical'], 2)]) }}</span>
                </div>
                @if ($o['own'])<p class="mfd-muted">{{ __('farmer.harvest.own_past', ['avg' => number_format($o['own']['average'], 2), 'count' => $o['own']['count']]) }}</p>@endif
            @endif
            <p class="mfd-foot">{{ __('farmer.harvest.meaning') }} {{ __('farmer.harvest.source', ['time' => $h['ran_at'] ? $h['ran_at']->format('F j, Y, g:i A') : '—']) }}</p>
        @endif

        {{-- A past record from the Rice Production page, never this season's harvest. --}}
        @if ($p && $h['state'] !== 'error')
            <div class="mfd-record">
                <p><strong>{{ __('farmer.production.title', ['barangay' => $f['barangay']]) }}:</strong> {{ __('farmer.production.line', ['yield' => number_format($p['yield'], 2), 'when' => $p['when'], 'season' => $p['season'] ?? '']) }}</p>
                <p class="mfd-muted">{{ __('farmer.production.note') }} <a class="mfd-link" href="{{ route('rice-productions.index') }}">{{ __('farmer.production.view') }}</a></p>
            </div>
        @endif
    </section>
@endif
