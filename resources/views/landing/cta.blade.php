{{-- Closing call to action (dark band). #login and #register open the hero's card in that mode (resources/js/landing.js). --}}
<section id="get-started" aria-labelledby="cta-title" class="lp-section lp-band">
    <div class="lp-band-photo" style="background-image: url('{{ asset('images/rice-hero-field.jpg') }}?v={{ filemtime(public_path('images/rice-hero-field.jpg')) }}')" aria-hidden="true"></div>
    <div class="lp-wrap flex flex-col gap-8 lg:flex-row lg:items-center lg:justify-between">
        <div data-reveal class="max-w-[40rem]">
            <h2 id="cta-title" class="text-[1.875rem] font-bold leading-[1.15] tracking-[-0.02em] sm:text-[2.5rem]">Plan your next planting with iClimate</h2>
            <p class="lp-lead">Log in to your account, or create one with your farm’s details.</p>
        </div>
        <div data-reveal style="--reveal-delay: 80ms" class="flex flex-wrap gap-3">
            @auth
                <a href="{{ route(auth()->user()->dashboardRoute()) }}" class="lp-btn-light lp-btn-lg" data-cta="dashboard">Go to dashboard</a>
            @else
                <a href="#login" class="lp-btn-light lp-btn-lg" data-cta="login">Log in</a>
                <a href="#register" class="lp-btn-outline-light lp-btn-lg" data-cta="register">Create account</a>
            @endauth
        </div>
    </div>
</section>
