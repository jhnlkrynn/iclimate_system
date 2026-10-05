{{-- "Farm Condition in Your Barangay": the Heat Map's own rating for the farmer's barangay (BarangayRiskMapService, the
     same entry the map shows), with what to watch and when it was checked. No map here: the Heat Map page has it. --}}
@php
    $risk = $f['risk'];
    $title = __('farmer.risk.title');
@endphp
@if ($risk['state'] === 'error')
    @include('dashboards.farmer._error', ['retry' => 'risk', 'title' => $title])
@else
    <section class="mfd-card mfd-risk" aria-labelledby="mfdRiskTitle" data-mfd-risk>
        <div class="mfd-card-head">
            <h2 id="mfdRiskTitle">{{ $title }}</h2>
            <a class="mfd-link" href="{{ route('heat-map.index') }}">{{ __('farmer.risk.view') }}</a>
        </div>
        @if ($risk['state'] === 'ok')
            <div class="mfd-risk-head">
                <span class="mfd-risk-place">@include('dashboards.mao.icon', ['name' => 'map']){{ $risk['barangay'] }}</span>
                @include('dashboards.farmer._status', ['level' => $risk['tone'], 'label' => $risk['level_full']])
            </div>
            <p class="mfd-risk-meaning">{{ $risk['meaning'] }}</p>
            @if ($risk['concern'])
                <p class="mfd-note is-{{ $risk['tone'] === 'good' ? 'info' : 'watch' }}">@include('dashboards.mao.icon', ['name' => 'alert'])<span><strong>{{ __('farmer.risk.watch') }}:</strong> {{ $risk['concern'] }}</span></p>
            @endif
            @if ($risk['line'] !== $risk['meaning'])
                <p class="mfd-risk-advice"><strong>{{ __('farmer.risk.advice') }}:</strong> {{ $risk['line'] }}</p>
            @endif
            @if ($risk['warnings'] !== [])
                <ul class="mfd-bullets">
                    @foreach (array_slice($risk['warnings'], 0, 2) as $warning)<li>{{ $warning }}</li>@endforeach
                </ul>
            @endif
            <p class="mfd-foot">@include('dashboards.mao.icon', ['name' => 'clock'])<span>{{ __('farmer.risk.checked', ['time' => $risk['updated_at'] ? $risk['updated_at']->format('M j • g:i A') : '—']) }}. {{ __('farmer.risk.foot') }}</span></p>
        @elseif (in_array($risk['state'], ['nobarangay', 'unverified'], true))
            <div class="mfd-empty" @if ($risk['state'] === 'unverified') data-mfd-barangay-unverified @endif>
                @include('dashboards.mao.icon', ['name' => 'map'])
                <div><p><strong>{{ $risk['state'] === 'unverified' ? __('farmer.risk.unverified', ['name' => $risk['stored']]) : __('farmer.risk.no_barangay') }}</strong></p></div>
                <a class="mfd-btn" href="{{ route('profile.edit') }}" data-mfd-popup="profile" aria-haspopup="dialog" data-fast-nav-bound="true">{{ __('farmer.risk.update_profile') }}</a>
            </div>
        @else
            <div class="mfd-empty">
                @include('dashboards.mao.icon', ['name' => 'info'])
                <div><p><strong>{{ __('farmer.risk.no_rating') }}</strong></p><p class="mfd-muted">{{ __('farmer.risk.no_rating_reason') }}</p></div>
                <button type="button" class="mfd-btn is-quiet" data-mfd-retry="risk">@include('dashboards.mao.icon', ['name' => 'refresh']){{ __('farmer.state.retry') }}</button>
            </div>
            {{-- No rating is ever guessed; the card still points to what the farmer can plan with meanwhile. --}}
            <p class="mfd-foot" data-mfd-risk-meanwhile>
                <a class="mfd-link" href="{{ route('weather-predictions.index', ['tab' => 'weather-forecast']) }}">{{ __('farmer.risk.no_rating_weather') }}</a>
                · <a class="mfd-link" href="{{ route('planting-advisories.index') }}">{{ __('farmer.risk.no_rating_advice') }}</a>
            </p>
        @endif
    </section>
@endif
