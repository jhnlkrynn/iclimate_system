@php
    $code = '503';
    $title = $title ?? 'Service Unavailable';
    $message = $message ?? (isset($exception) && $exception->getMessage() === \App\Http\Middleware\RequireSchema::MESSAGE
        ? $exception->getMessage()
        : 'iClimate is temporarily unavailable. Please check back shortly.');
@endphp
@include('errors.minimal')
