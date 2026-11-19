{{-- "5 seconds ago" for a stored time (database or source time, never "now"). The server renders the first text;
     resources/js/live-clock.js keeps it current every second without a reload. --}}
@props(['at'])
@php
    $time = $at instanceof \DateTimeInterface ? \Illuminate\Support\Carbon::instance($at) : (filled($at) ? rescue(fn () => \Illuminate\Support\Carbon::parse($at), null, false) : null);
@endphp
@if ($time)<time {{ $attributes->merge(['class' => 'ic-ago']) }} datetime="{{ $time->copy()->utc()->toIso8601String() }}" lang="{{ app()->getLocale() }}" aria-live="off" data-relative-time title="{{ $time->copy()->timezone('Asia/Manila')->format('M j, Y g:i:s A') }} (Philippine time)">{{ \App\Support\RelativeTime::format($time) }}</time>@endif
