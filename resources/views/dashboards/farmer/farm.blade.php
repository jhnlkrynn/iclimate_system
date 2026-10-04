{{-- "My Farm at a Glance": four answers a farmer needs first (how big the farm is, whether a crop is planted, the weather
     now, and whether anything needs attention). Something not saved or not available yet is said plainly, with the one
     button that fixes it (dashboards/farmer/popups.blade.php); nothing is shown as 0 or N/A. --}}
@php $farm = $f['farm'] ?? null; @endphp
@if ($farm)
    @php
        $tiles = [
            ['key' => 'size', 'icon' => 'yield', 'popup' => 'farm-size', 'href' => route('farmer.boundary.edit')],
            ['key' => 'planting', 'icon' => 'calendar', 'popup' => 'planting', 'href' => route('calendar.index')],
            ['key' => 'weather', 'icon' => 'weather', 'popup' => null, 'href' => null],
            ['key' => 'condition', 'icon' => 'sprout', 'popup' => null, 'href' => null],
        ];
        $statusIcon = ['good' => 'check', 'watch' => 'alert', 'danger' => 'danger'];
    @endphp
    <section aria-labelledby="mfdFarmTitle" data-mfd-farm>
        <h2 class="mfd-section-title" id="mfdFarmTitle">{{ __('farmer.farm.title') }}</h2>
        <div class="mfd-farm">
            @foreach ($tiles as $tile)
                @php $item = $farm[$tile['key']]; $status = $item['status'] ?? null; @endphp
                <div @class(['mfd-farm-tile', 'is-missing' => $item['value'] === null, 'is-'.$status => $status]) data-farm="{{ $tile['key'] }}">
                    <span class="mfd-farm-label">@include('dashboards.mao.icon', ['name' => $tile['icon']]){{ __('farmer.farm.'.$tile['key'].'.label') }}</span>
                    @if ($item['value'] !== null)
                        <strong class="mfd-farm-value">@if ($status)@include('dashboards.mao.icon', ['name' => $statusIcon[$status]])@endif<span>{{ $item['value'] }}</span></strong>
                        @if (! empty($item['detail']))<span class="mfd-farm-detail">{{ $item['detail'] }}</span>@endif
                        <span class="mfd-farm-note">{{ $item['note'] }}</span>
                    @else
                        <strong class="mfd-farm-value"><span>{{ __('farmer.farm.'.$tile['key'].'.missing') }}</span></strong>
                        <span class="mfd-farm-note">{{ __('farmer.farm.'.$tile['key'].'.why') }}</span>
                        @if ($tile['popup'])
                            <a class="mfd-btn is-quiet" href="{{ $tile['href'] }}" data-mfd-popup="{{ $tile['popup'] }}" aria-haspopup="dialog" data-fast-nav-bound="true">{{ __('farmer.farm.'.$tile['key'].'.cta') }}</a>
                        @endif
                    @endif
                </div>
            @endforeach
        </div>
    </section>
@endif
