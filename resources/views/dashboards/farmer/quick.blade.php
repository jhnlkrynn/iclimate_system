{{-- Quick Actions: four things a farmer does from the dashboard. Pages already linked from the cards above (weather,
     heat map, calendar, advisories) are not repeated. The farm tasks open their pop-up (dashboards/farmer/popups.blade.php);
     their wording follows what the farmer has saved: "Not set" until it is set. --}}
@php
    $q = $f['quick'] ?? ['farm' => false, 'map' => false, 'plan' => false];
    $cards = [
        ['key' => 'farm', 'popup' => 'farm-size', 'icon' => 'yield', 'href' => route('farmer.boundary.edit')],
        ['key' => 'plan', 'popup' => 'planting', 'icon' => 'calendar', 'href' => route('calendar.index')],
        ['key' => 'map', 'popup' => 'farm-map', 'icon' => 'map', 'href' => route('farmer.boundary.edit')],
    ];
@endphp
<section class="mfd-card mfd-quickwrap" aria-labelledby="mfdQuickTitle" data-mfd-quick>
    <div class="mfd-card-head"><h2 id="mfdQuickTitle">{{ __('farmer.quick.title') }}</h2></div>
    <ul class="mfd-quick">
        <li>
            <button type="button" class="mfd-quick-card" data-ai-open data-quick="ai">
                <span class="mfd-quick-icon" aria-hidden="true">@include('dashboards.mao.icon', ['name' => 'sparkle'])</span>
                <span class="mfd-quick-text">
                    <span class="mfd-quick-title"><strong>{{ __('farmer.quick.ai.title') }}</strong></span>
                    <span class="mfd-quick-desc">{{ __('farmer.quick.ai.text') }}</span>
                </span>
            </button>
        </li>
        @foreach ($cards as $card)
            @php $done = (bool) ($q[$card['key']] ?? false); @endphp
            <li>
                {{-- The Farm Information card opens the farm size pop-up with its water source field shown and its own title. --}}
                <a class="mfd-quick-card" href="{{ $card['href'] }}" data-mfd-popup="{{ $card['popup'] }}" aria-haspopup="dialog" data-fast-nav-bound="true" data-quick="{{ $card['key'] }}"
                    @if ($card['key'] === 'farm') data-mfd-popup-variant="info" data-mfd-popup-title="{{ __('farmer.quick.farm.popup_title') }}" data-mfd-popup-intro="{{ __('farmer.quick.farm.popup_intro') }}" @endif>
                    <span class="mfd-quick-icon" aria-hidden="true">@include('dashboards.mao.icon', ['name' => $card['icon']])</span>
                    <span class="mfd-quick-text">
                        <span class="mfd-quick-title"><strong>{{ __('farmer.quick.'.$card['key'].'.'.($done ? 'update' : 'add')) }}</strong>@unless ($done) <span class="mfd-quick-state">{{ __('farmer.quick.not_set') }}</span>@endunless</span>
                        <span class="mfd-quick-desc">{{ __('farmer.quick.'.$card['key'].'.text') }}</span>
                    </span>
                </a>
            </li>
        @endforeach
    </ul>
</section>
