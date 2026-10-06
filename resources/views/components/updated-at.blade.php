{{-- "Updated: 3:15 PM" (today) or "Updated: Oct 5, 3:15 PM", in Philippine time, beside any stored reading.
     A page shows its last saved data with this label instead of an error when the provider or the scheduler is behind. --}}
@props(['at', 'label' => 'Updated'])
@php
    $time = $at instanceof \DateTimeInterface ? \Illuminate\Support\Carbon::instance($at) : (filled($at) ? rescue(fn () => \Illuminate\Support\Carbon::parse($at), null, false) : null);
    $time = $time?->copy()->timezone('Asia/Manila');
@endphp
@if ($time)
    <span {{ $attributes->merge(['class' => 'ic-updated']) }}>{{ $label }}: {{ $time->isSameDay(now('Asia/Manila')) ? $time->format('g:i A') : $time->format('M j, g:i A') }}</span>
@endif
