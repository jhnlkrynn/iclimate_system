{{-- "My Farm": the farmer's own farm details (barangay, size, water source, planting date). Something not saved yet is
     a link that opens its pop-up, and what is still needed is listed with the pop-up that fixes it (dashboards/farmer/popups.blade.php). --}}
@php
    $p = $f['profile'];
    $setup = $f['setup'];
    // The pop-up each missing item's button opens on the dashboard; an item without one (recording a harvest) goes to its page.
    $popups = ['barangay' => 'profile', 'farm_type' => 'profile', 'farm_area' => 'farm-size', 'boundary' => 'farm-map'];
    // Something not saved yet is a button that opens its pop-up, never a bare "Not set".
    $add = fn (string $key, string $popup, ?string $href = null) => '<a class="mfd-link" href="'.e($href ?? route('profile.edit')).'" data-mfd-popup="'.$popup.'" aria-haspopup="dialog" data-fast-nav-bound="true">'.e(__('farmer.my_farm.add.'.$key)).'</a>';
@endphp
<section class="mfd-card mfd-myfarm" aria-labelledby="mfdFarmTitle" data-mfd-farm>
    <div class="mfd-card-head">
        <h2 id="mfdFarmTitle" tabindex="-1">{{ __('farmer.my_farm.title') }}</h2>
        @if ($setup)<span class="mfd-muted">{{ __('farmer.setup.progress', ['done' => $setup['done'], 'total' => $setup['total']]) }}</span>@endif
    </div>
    <dl class="mfd-facts">
        <div data-farm="barangay"><dt>{{ __('farmer.my_farm.barangay') }}</dt><dd>{!! $p['barangay'] ? e($p['barangay']) : $add('barangay', 'profile') !!}</dd></div>
        <div data-farm="size"><dt>{{ __('farmer.my_farm.size') }}</dt><dd>{!! $p['area'] ? e(number_format($p['area'], 2).' ha') : $add('size', 'farm-size') !!}@if ($p['mapped_in'])<span class="mfd-muted"> • {{ __('farmer.my_farm.mapped_in', ['barangay' => $p['mapped_in']]) }}</span>@endif</dd></div>
        <div data-farm="water"><dt>{{ __('farmer.my_farm.water_label') }}</dt><dd>{!! $p['water'] ? e($p['water']) : $add('water', 'profile') !!}</dd></div>
        <div data-farm="planting"><dt>{{ __('farmer.my_farm.planting') }}</dt><dd>{!! $p['planting_date'] ? e($p['planting_date']->format('F j, Y')) : $add('planting', 'planting', route('farmer.planting-schedules.create')) !!}</dd></div>
    </dl>

    @if ($setup)
        <div class="mfd-progress" role="progressbar" aria-label="{{ __('farmer.my_farm.title') }}" aria-valuemin="0" aria-valuemax="{{ $setup['total'] }}" aria-valuenow="{{ $setup['done'] }}"><i @style(['width: '.round($setup['done'] / $setup['total'] * 100).'%'])></i></div>
        <h3 class="mfd-sub">{{ __('farmer.my_farm.still_needed') }}</h3>
        <ul class="mfd-setup">
            @foreach ($setup['items'] as $item)
                <li data-setup="{{ $item['key'] }}">
                    <div><strong>{{ $item['title'] }}</strong> <span class="mfd-muted">{{ $item['benefit'] }}</span></div>
                    <a class="mfd-btn is-quiet is-small" href="{{ $item['href'] }}" @isset($popups[$item['key']]) data-mfd-popup="{{ $popups[$item['key']] }}" aria-haspopup="dialog" data-fast-nav-bound="true" @endisset>{{ $item['cta'] }}</a>
                </li>
            @endforeach
        </ul>
    @else
        <p class="mfd-note is-good">@include('dashboards.mao.icon', ['name' => 'check'])<span>{{ __('farmer.my_farm.done') }}</span></p>
    @endif
</section>
