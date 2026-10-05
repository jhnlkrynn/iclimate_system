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
            <div class="mfd-empty" role="status">
                @include('dashboards.mao.icon', ['name' => 'info'])
                <div>
                    <p><strong>{{ __('farmer.harvest.not_ready') }}</strong></p>
                    @if ($h['missing'] !== [])
                        <p class="mfd-muted">{{ __('farmer.harvest.still_need') }}</p>
                        <ul class="mfd-bullets">@foreach ($h['missing'] as $need)<li>{{ __('farmer.harvest.need.'.$need) }}</li>@endforeach</ul>
                    @else
                        <p class="mfd-muted">{{ $h['reason'] }}</p>
                    @endif
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
        @if ($p)
            <div class="mfd-record">
                <p><strong>{{ __('farmer.production.title', ['barangay' => $f['barangay']]) }}:</strong> {{ __('farmer.production.line', ['yield' => number_format($p['yield'], 2), 'when' => $p['when'], 'season' => $p['season'] ?? '']) }}</p>
                <p class="mfd-muted">{{ __('farmer.production.note') }} <a class="mfd-link" href="{{ route('rice-productions.index') }}">{{ __('farmer.production.view') }}</a></p>
            </div>
        @endif
    </section>
@endif
