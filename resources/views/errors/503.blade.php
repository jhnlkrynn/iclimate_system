@php
    $code = '503';
    $title = $title ?? 'Service Unavailable';
    $message = $message ?? 'iClimate is temporarily unavailable. Please check back shortly.';
@endphp
@include('errors.minimal')
