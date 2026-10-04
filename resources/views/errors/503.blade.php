@php
    $code = '503';
    $title = $title ?? 'Service Unavailable';
    $message = $message ?? 'iClimate is temporarily unavailable. Please check back shortly.';
    $showDashboardLink = false;
@endphp
@include('errors.minimal')
