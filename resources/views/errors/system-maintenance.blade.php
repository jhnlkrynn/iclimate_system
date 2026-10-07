{{-- Manuscript – Naicel (2026-10-08), Phase 8: the page everyone but IT sees while Maintenance mode is on. --}}
@php
    $code = '503';
    $title = 'Under maintenance';
    $message = $message ?? 'iClimate is being updated. Please come back in a little while.';
@endphp
@include('errors.minimal')
