@php
    $setup = $f['setup'];
    // The pop-up each item's button opens on the dashboard; an item without one (recording a harvest) goes to its page.
    $popups = ['barangay' => 'profile', 'farm_type' => 'profile', 'farm_area' => 'farm-size', 'boundary' => 'farm-map'];
@endphp
@if ($setup)
    <section class="mfd-card" aria-labelledby="mfdSetupTitle" data-mfd-setup>
        <div class="mfd-card-head">
            <h2 id="mfdSetupTitle">{{ __('farmer.setup.title') }}</h2>
            <span class="mfd-muted">{{ __('farmer.setup.progress', ['done' => $setup['done'], 'total' => $setup['total']]) }}</span>
        </div>
        <div class="mfd-progress" role="progressbar" aria-label="{{ __('farmer.setup.title') }}" aria-valuemin="0" aria-valuemax="{{ $setup['total'] }}" aria-valuenow="{{ $setup['done'] }}"><i @style(['width: '.round($setup['done'] / $setup['total'] * 100).'%'])></i></div>
        <ul class="mfd-setup">
            @foreach ($setup['items'] as $item)
                <li>
                    <div><strong>{{ $item['title'] }}</strong> <span class="mfd-muted">{{ $item['benefit'] }}</span></div>
                    <a class="mfd-btn is-quiet" href="{{ $item['href'] }}" @isset($popups[$item['key']]) data-mfd-popup="{{ $popups[$item['key']] }}" aria-haspopup="dialog" data-fast-nav-bound="true" @endisset>{{ $item['cta'] }}</a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
