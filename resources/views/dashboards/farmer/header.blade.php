@php
    $w = $f['weather'];
    $ok = $w['state'] === 'ok';
    $now = $f['generated_at'];
@endphp
<header class="mfd-header">
    <div class="mfd-header-main">
        <h1>{{ $f['greeting'] }}</h1>
        <p class="mfd-place">@include('dashboards.mao.icon', ['name' => 'map']){{ $f['place'] }}</p>
        <p class="mfd-date" data-mfd-clock>{{ $now->format('l, F j, Y') }} • {{ $now->format('g:i A') }}</p>
    </div>
    <div class="mfd-fresh">
        @if ($ok)
            <span class="mfd-age" data-mfd-age data-updated="{{ $w['updated_at']?->toIso8601String() }}">{{ $w['age_text'] }}</span>
            @if ($w['stale'])@include('dashboards.farmer._status', ['level' => 'watch', 'label' => __('farmer.header.old_data')])@endif
        @else
            <span class="mfd-age">{{ __('farmer.header.no_update') }}</span>
        @endif
        <button type="button" class="mfd-btn is-quiet" data-mfd-refresh aria-label="{{ __('farmer.header.refresh_label') }}">@include('dashboards.mao.icon', ['name' => 'refresh'])<span data-mfd-refresh-text>{{ __('farmer.header.refresh') }}</span></button>
    </div>
</header>
