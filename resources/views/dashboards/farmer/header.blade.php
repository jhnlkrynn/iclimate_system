{{-- One short greeting line ("Magandang umaga, Naicel!", by Manila time; no banner), the farmer's barangay, the farm
     selector (only with two or more farms), the language selector and Refresh (which re-reads the saved data and asks
     for the latest weather). The name and barangay come from the signed-in farmer's profile. --}}
@php $locale = $f['locale'] ?? app()->getLocale(); @endphp
<header class="mfd-header fh-header">
    <div class="mfd-header-main">
        <h1 class="fh-greeting" data-fh-greeting>{{ $f['greeting'] }}</h1>
        <p class="mfd-place">@include('dashboards.mao.icon', ['name' => 'map']){{ $f['place'] }}</p>
    </div>
    <div class="mfd-header-side">
        @if (! empty($f['farms']))
            <form class="mfd-lang fh-farm-select" method="GET" action="{{ route('farmer.dashboard') }}">
                <label for="fhFarm">{{ __('farmer.farms.selector') }}</label>
                <select id="fhFarm" name="farm" data-fh-farm-select>
                    @foreach ($f['farms'] as $farm)
                        <option value="{{ $farm['id'] }}" @selected(($f['farm_id'] ?? null) === $farm['id'])>{{ $farm['name'] }}</option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="mfd-btn is-quiet">{{ __('farmer.header.language_apply') }}</button></noscript>
            </form>
        @endif
        {{-- Works without scripts (a normal form post); with scripts it saves and reloads the page in the chosen language. --}}
        <form class="mfd-lang" method="POST" action="{{ route('farmer.dashboard.language') }}" data-mfd-language>
            @csrf
            <label for="mfdLanguage">{{ __('farmer.header.language') }}</label>
            <select id="mfdLanguage" name="language" aria-label="{{ __('farmer.header.language_label') }}">
                @foreach (\App\Support\FarmerLanguage::SUPPORTED as $code => $label)
                    <option value="{{ $code }}" lang="{{ $code }}" @selected($locale === $code)>{{ $label }}</option>
                @endforeach
            </select>
            <noscript><button type="submit" class="mfd-btn is-quiet">{{ __('farmer.header.language_apply') }}</button></noscript>
        </form>
        <button type="button" class="mfd-btn is-quiet" data-mfd-refresh aria-label="{{ __('farmer.header.refresh_label') }}">@include('dashboards.mao.icon', ['name' => 'refresh'])<span data-mfd-refresh-text>{{ __('farmer.header.refresh') }}</span></button>
    </div>
</header>
