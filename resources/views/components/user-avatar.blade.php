@props([
    'user' => null,
    'size' => 40,
])

@php
    $avatarUrl = $user?->avatarUrl();
    $iconSize = max(16, (int) round(((int) $size) * 0.54));
@endphp

<span {{ $attributes->class(['ic-user-avatar'])->style(["--ic-avatar-size: {$size}px"]) }} aria-hidden="true">
    @if ($avatarUrl)
        <img src="{{ $avatarUrl }}" alt="">
    @else
        <span class="ic-user-avatar-icon" style="display:inline-flex;align-items:center;justify-content:center;">
            <svg width="{{ $iconSize }}" height="{{ $iconSize }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="8" r="4"/>
                <path d="M5 20a7 7 0 0 1 14 0"/>
            </svg>
        </span>
    @endif
</span>
