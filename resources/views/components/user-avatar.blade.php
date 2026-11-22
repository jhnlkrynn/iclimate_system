@props([
    'user' => null,
    'size' => 40,
])

@php
    $avatarUrl = $user?->avatarUrl();
    $initials = $user ? $user->initials() : 'U';
@endphp

<span {{ $attributes->class(['ic-user-avatar'])->style(["--ic-avatar-size: {$size}px"]) }} aria-hidden="true">
    @if ($avatarUrl)
        <img src="{{ $avatarUrl }}" alt="">
    @else
        <span class="ic-user-avatar-initials">{{ $initials }}</span>
    @endif
</span>
