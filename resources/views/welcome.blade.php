@php
    // The login card follows the login page: after repeated failed attempts it asks for the security code too.
    $requiresCaptcha = auth()->guest() && app(\App\Services\Security\CaptchaService::class)->requiresCaptcha(request(), 'login');

    // The hero card logs in or creates an account. A sign-up that failed validation comes back here (its form sends
    // _auth_form=register) and reopens in Create account mode; each form shows only its own errors and old input.
    $authMode = old('_auth_form') === 'register' ? 'register' : 'login';
    $noErrors = new \Illuminate\Support\ViewErrorBag;
    $loginErrors = $authMode === 'login' ? $errors : $noErrors;
    $registerErrors = $authMode === 'register' ? $errors : $noErrors;
    if (auth()->guest()) {
        // The same lists RegisteredUserController::create() gives /register.
        try {
            $farmerOrganizations = \App\Services\Farms\FarmAssociations::organizations();
        } catch (\Throwable) {
            $farmerOrganizations = collect();
        }
        $manualBarangays = \App\Support\LianFarmTypes::records()->pluck('barangay')->unique()->sort()->values()->all();
    }

    $officeEmail = \App\Support\MaoContact::EMAIL;
    $directionsUrl = \App\Support\MaoContact::DIRECTIONS_URL;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'iClimate') }} | Weather Impact Analysis for Lian, Batangas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/landing.css', 'resources/js/landing.js'])
    @guest
        {{-- After the Tailwind build, so the sign-up form keeps the same look as on /register. --}}
        <style>
@include('auth.partials.register-form-styles')
        </style>
    @endguest
    @include('partials.global-loader')
</head>
<body id="top">
<x-flash-toast />
<a href="#main" class="sr-only z-50 rounded-lg bg-brand px-4 py-2 text-white focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Skip to main content</a>

<x-public-nav :landing="true" />

<main id="main">
    @include('landing.hero')
    @include('landing.about')
    @include('landing.features')
    @include('landing.numbers')
    @include('landing.how')
    @include('landing.roles')
    @include('landing.questions-contact')
</main>

@include('landing.footer')
@guest
    @include('auth.partials.register-form-script')
@endguest
</body>
</html>
