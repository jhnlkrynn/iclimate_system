{{-- "Updated: 3:15 PM · 5 minutes ago" (today) or "Updated: Oct 5, 3:15 PM · 2 days ago", in Philippine time,
     beside any stored reading. The time is the stored database/source time; only the "ago" part moves (every
     second, see <x-live-time>). A page shows its last saved data with this label instead of an error when the
     provider or the scheduler is behind. --}}
@props(['at', 'label' => 'Updated'])
@php
    $time = $at instanceof \DateTimeInterface ? \Illuminate\Support\Carbon::instance($at) : (filled($at) ? rescue(fn () => \Illuminate\Support\Carbon::parse($at), null, false) : null);
    $time = $time?->copy()->timezone('Asia/Manila');
@endphp
@if ($time)
    <span {{ $attributes->merge(['class' => 'ic-updated']) }}>{{ $label }}: <span data-updated-clock>{{ $time->isSameDay(now('Asia/Manila')) ? $time->format('g:i A') : $time->format('M j, g:i A') }}</span> · <x-live-time :at="$time" /></span>
@endif
