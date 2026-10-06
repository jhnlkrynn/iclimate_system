{{-- Header band: greeting by the Manila clock, the farmer's barangay, today's date and time, when the weather was last
     fetched, the language selector and Refresh. The name and barangay come from the signed-in farmer's profile. --}}
@php
    $w = $f['weather'];
    $ok = $w['state'] === 'ok';
    $now = $f['generated_at'];
    $locale = $f['locale'] ?? app()->getLocale();
@endphp
<header class="mfd-header">
    <div class="mfd-header-main">
        <h1>{{ $f['greeting'] }}</h1>
    </div>
    <div class="mfd-header-top">
        <span class="mfd-kicker">{{ __('farmer.header.kicker') }}</span>
    </div>
    <div class="mfd-header-meta">
        <p class="mfd-place">@include('dashboards.mao.icon', ['name' => 'map']){{ $f['place'] }}</p>
        <span class="mfd-meta-separator" aria-hidden="true">•</span>
        <p class="mfd-date" data-mfd-clock>{{ $now->format('l, F j, Y') }} • {{ $now->format('g:i A') }}</p>
    </div>
    <div class="mfd-header-side">
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
        <p class="mfd-fresh">
            @if ($ok)
                <span class="mfd-fresh-updated" aria-label="{{ __('farmer.header.last_updated', ['time' => $w['updated_label'] ?? '—']) }}">{{ __('farmer.header.updated_at', ['time' => $w['updated_label'] ?? '—']) }}</span>
                <span aria-hidden="true">•</span>
                <span class="mfd-age" data-mfd-age data-updated="{{ $w['updated_at']?->toIso8601String() }}">{{ $w['age_text'] }}</span>
                @if ($w['stale'])@include('dashboards.farmer._status', ['level' => 'watch', 'label' => __('farmer.header.old_data')])@endif
            @else
                <span class="mfd-age">{{ __('farmer.header.no_update') }}</span>
            @endif
        </p>
    </div>
</header>
