{{-- Quick Actions: the three farm tasks a farmer does from the dashboard, each one card that opens its pop-up
     (dashboards/farmer/popups.blade.php). Pages that are already in the side menu (advisories, calendar, notifications,
     profile) are not repeated here. Each card's wording follows what the farmer has saved: "Add" until it is set, then "Update". --}}
@php
    $q = $f['quick'] ?? ['farm' => false, 'map' => false, 'plan' => false];
    $cards = [
        ['key' => 'farm', 'popup' => 'farm-size', 'icon' => 'yield', 'href' => route('farmer.boundary.edit')],
        ['key' => 'map', 'popup' => 'farm-map', 'icon' => 'map', 'href' => route('farmer.boundary.edit')],
        ['key' => 'plan', 'popup' => 'planting', 'icon' => 'calendar', 'href' => route('calendar.index')],
    ];
@endphp
<section class="mfd-card mfd-quickwrap" aria-labelledby="mfdQuickTitle" data-mfd-quick>
    <div class="mfd-card-head"><h2 id="mfdQuickTitle">{{ __('farmer.quick.title') }}</h2></div>
    <ul class="mfd-quick">
        @foreach ($cards as $card)
            @php $done = (bool) ($q[$card['key']] ?? false); @endphp
            <li>
                {{-- The Farm Information card opens the farm size pop-up with its farm type field shown and its own title. --}}
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
