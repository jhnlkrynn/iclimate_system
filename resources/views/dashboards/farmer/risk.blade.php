@php
    $risk = $f['risk'];
    $title = __('farmer.risk.title');
    $level = $risk['state'] === 'ok' ? ['low' => 'good', 'moderate' => 'watch', 'high' => 'danger', 'severe' => 'danger'][strtolower($risk['level'])] ?? 'info' : 'info';
@endphp
@if ($risk['state'] === 'error')
    @include('dashboards.farmer._error', ['retry' => 'risk', 'title' => $title])
@else
    <section class="mfd-card" aria-labelledby="mfdRiskTitle" data-mfd-risk>
        <div class="mfd-card-head"><h2 id="mfdRiskTitle">{{ $title }}</h2></div>
        @if ($risk['state'] === 'ok')
            <p class="mfd-risk-level">@include('dashboards.farmer._status', ['level' => $level, 'label' => __('farmer.risk.level_line', ['level' => $risk['level_label']])])</p>
            <p>{{ $risk['meaning'] }}@if ($risk['concern']) {{ __('farmer.risk.concern_line', ['concern' => $risk['concern']]) }}@endif</p>
            @if ($risk['line'] !== $risk['meaning'])<p class="mfd-muted">{{ $risk['line'] }}</p>@endif
            <p class="mfd-foot">{{ __('farmer.risk.foot', ['time' => $risk['updated_at'] ? $risk['updated_at']->format('M j, Y, g:i A') : __('farmer.harvest.time_unknown')]) }}</p>
        @elseif ($risk['state'] === 'nobarangay')
            <div class="mfd-empty">
                @include('dashboards.mao.icon', ['name' => 'map'])
                <div><p><strong>{{ __('farmer.risk.no_barangay') }}</strong></p></div>
                <a class="mfd-btn" href="{{ route('profile.edit') }}" data-mfd-popup="profile" aria-haspopup="dialog" data-fast-nav-bound="true">{{ __('farmer.setup.barangay.cta') }}</a>
            </div>
        @else
            <div class="mfd-empty">
                @include('dashboards.mao.icon', ['name' => 'info'])
                <div><p><strong>{{ __('farmer.risk.no_rating') }}</strong></p><p class="mfd-muted">{{ __('farmer.risk.no_rating_reason') }}</p></div>
                <button type="button" class="mfd-btn is-quiet" data-mfd-retry="risk">@include('dashboards.mao.icon', ['name' => 'refresh']){{ __('farmer.state.retry') }}</button>
            </div>
        @endif
    </section>
@endif
