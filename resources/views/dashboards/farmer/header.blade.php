{{-- Greeting, the farm being viewed and its barangay, the language selector and Refresh (which re-reads the saved
     data and asks for the latest weather). The farm is the one picked in the My Farm selector (ActiveFarm). --}}
@php $locale = $f['locale'] ?? app()->getLocale(); @endphp
<header class="mfd-header fh-header">
    <div class="mfd-header-main">
        {{-- Farmer – Naicel: one short greeting line ("Magandang umaga, Naicel!", Manila time), no banner. --}}
        <span class="mfd-kicker">{{ __('farmer.header.kicker') }}</span>
        <h1 class="fh-greeting" data-fh-greeting>{{ $f['greeting'] }}</h1>
        @if (! empty($f['farm']))
            <p class="fh-farm-line" data-viewing-farm="{{ $f['farm']['id'] }}"><span>{{ __('farms.viewing') }}:</span> <strong>{{ $f['farm']['name'] }}</strong></p>
        @endif
        <p class="mfd-place">@include('dashboards.mao.icon', ['name' => 'map']){{ $f['place'] }}</p>
        @if (! ($f['has_farms'] ?? true))
            <p class="fh-farm-line"><a class="mfd-btn is-quiet" href="{{ route('farmer.farms.create') }}">+ {{ __('farms.add_first') }}</a> <span>{{ __('farms.add_first_note') }}</span></p>
        @endif
    </div>
    <div class="mfd-header-side">
        <button type="button" class="mfd-btn is-quiet" data-mfd-refresh aria-label="{{ __('farmer.header.refresh_label') }}">@include('dashboards.mao.icon', ['name' => 'refresh'])<span data-mfd-refresh-text>{{ __('farmer.header.refresh') }}</span></button>
    </div>
</header>
