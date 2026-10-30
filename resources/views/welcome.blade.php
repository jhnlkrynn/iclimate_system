@php
    $heroClimate = \App\Support\ClimateSnapshot::latest();

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
            $farmerOrganizations = \App\Models\FarmerOrganization::query()->orderBy('id')->get();
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
    {{-- Scroll reveal (resources/js/landing.js): hide [data-reveal] content only when it can be shown again, set before
         the first paint so nothing flashes. If landing.js has not taken over within 2s, everything is shown. --}}
    <script>
        (() => {
            const root = document.documentElement;
            if (! ('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            root.classList.add('js-reveal');
            setTimeout(() => { if (root.dataset.reveal !== 'ready') root.classList.remove('js-reveal'); }, 2000);
        })();
    </script>
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
{{-- The rice field photo behind every section (landing.css .lp-photo), versioned by file time so a replaced photo shows at once. --}}
<div class="lp-photo" aria-hidden="true" style="background-image: url('{{ asset('images/rice-field-harvest.jpg') }}?v={{ filemtime(public_path('images/rice-field-harvest.jpg')) }}')"></div>
<x-flash-toast />
<a href="#main" class="sr-only z-50 rounded-lg bg-brand px-4 py-2 text-white focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Skip to main content</a>

<x-public-nav :landing="true" />

<main id="main">
    @include('landing.hero')
    @include('landing.about')
    @include('landing.features')
    @include('landing.how')
    @include('landing.roles')
    @include('landing.faq')
    @include('landing.contact')
</main>

@include('landing.footer')
@guest
    @include('auth.partials.register-form-script')
@endguest
</body>
</html>
