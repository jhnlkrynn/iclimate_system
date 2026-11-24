<section id="hero" data-section class="lp-hero relative bg-sage-50 bg-cover bg-center" style="background-image: url('{{ asset('images/rice-hero-field.jpg') }}?v={{ filemtime(public_path('images/rice-hero-field.jpg')) }}')">
    <div class="lp-hero-row lp-wrap flex flex-col gap-8 lg:flex-row lg:items-stretch lg:gap-12">
        <div class="lp-hero-intro flex min-w-0 flex-1 flex-col items-center justify-center gap-5 rounded-lg border border-line bg-white p-6 text-center sm:p-8 lg:p-10">
            <div class="lp-hero-intro-inner flex flex-col items-center gap-5">
                <h1 class="lp-hero-title max-w-[40rem] text-[clamp(2rem,3.4vw,2.875rem)] font-semibold leading-[1.18] tracking-[-0.025em] text-sage-900">{{ __('landing.hero.title') }}</h1>
                <p class="max-w-[36rem] text-[17px] leading-[1.6] text-muted">{{ __('landing.hero.description') }}</p>
                <ul class="lp-hero-offers">
                    @foreach ([
                        ['Barangay forecasts', 'Rain, heat and typhoon outlook for your barangay.'],
                        ['Planting advisories', 'When to plant, from the MAO.'],
                        ['Harvest estimates', 'Expected harvest date for your rice variety.'],
                    ] as [$title, $text])
                        <li><strong>{{ $title }}</strong><span>{{ $text }}</span></li>
                    @endforeach
                </ul>
                <div class="lp-hero-more flex flex-wrap items-center justify-center gap-3">
                    <a href="#features" class="lp-btn-primary lp-btn-lg" data-hero-features data-hero-how>{{ __('landing.hero.button') }}</a>
                </div>
                @guest
                    {{-- Shown only while the card on the right is in Create account mode (landing.css, .lp-hero-row:has(...)). --}}
                    <div class="lp-reg-guide" data-reg-guide>
                        <h2 class="text-xl font-semibold text-ink">How to create an account</h2>
                        <ol class="mt-4 flex flex-col gap-4">
                            <li><span class="lp-reg-step" aria-hidden="true">1</span><div><strong>Enter your name and email</strong><p>Use an email address you can open. It is what you will use to log in.</p></div></li>
                            <li><span class="lp-reg-step" aria-hidden="true">2</span><div><strong>Create a password and enter the code</strong><p>Use at least 8 characters, type the security code, tick the privacy box, then press Create account.</p></div></li>
                            <li><span class="lp-reg-step" aria-hidden="true">3</span><div><strong>Wait for MAO approval</strong><p>The Municipal Agriculture Office reviews each new account. You can log in once it is approved.</p></div></li>
                        </ol>
                        <p class="mt-4 text-sm font-medium text-sage-800">You'll add your barangay and association after you log in.</p>
                        <p class="mt-4 rounded-lg bg-sage-50 px-4 py-3 text-base text-ink">Need help? Visit the Municipal Agriculture Office at the Lian Municipal Hall, or <a href="#contact" class="lp-link">send us a message</a>.</p>
                    </div>
                @endguest
            </div>
        </div>
        <div class="w-full shrink-0 rounded-lg border border-line bg-white px-6 py-6 sm:px-8 lg:w-[27rem]" data-login-card @guest data-auth-card data-auth-mode="{{ $authMode }}" @endguest>
            @auth
                <h2 class="text-[1.625rem] font-semibold leading-[1.2] tracking-[-0.02em] text-ink">Welcome back, {{ auth()->user()->name }}</h2>
                <p class="mt-1.5 text-base leading-[1.4] text-muted">You are logged in to iClimate.</p>
                <a href="{{ route(auth()->user()->dashboardRoute()) }}" class="lp-btn-primary lp-btn-lg mt-6 w-full" data-go-dashboard>Go to dashboard</a>
                <form method="POST" action="{{ route('logout') }}" class="mt-3 text-center" data-landing-logout>@csrf<button type="submit" class="inline-flex min-h-11 items-center rounded text-base font-semibold text-brand hover:text-brand-dark">Sign out</button></form>
            @else
                <div role="tablist" aria-label="Log in or create an account" class="mb-6 grid grid-cols-2 gap-1 rounded-xl border border-sage-100 bg-sage-50 p-1" data-auth-toggle>
                    @foreach (['login' => 'Sign in', 'register' => 'Create account'] as $mode => $label)
                        <button type="button" role="tab" id="auth-tab-{{ $mode }}" aria-controls="auth-panel-{{ $mode }}" data-auth-switch="{{ $mode }}" aria-selected="{{ $authMode === $mode ? 'true' : 'false' }}" tabindex="{{ $authMode === $mode ? '0' : '-1' }}" class="flex min-h-11 items-center justify-center rounded-lg text-base font-semibold text-muted hover:bg-sage-100 hover:text-sage-700 aria-selected:bg-sage-200 aria-selected:text-sage-800">{{ $label }}</button>
                    @endforeach
                </div>
                <p class="sr-only" aria-live="polite" data-auth-status></p>
                <div role="tabpanel" id="auth-panel-login" aria-labelledby="auth-tab-login" data-auth-panel="login" @if ($authMode !== 'login') hidden @endif>
                    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-5" data-login-form>@csrf
                        <div><h2 class="text-[1.625rem] font-semibold leading-[1.2] tracking-[-0.02em] text-ink">Log in to iClimate</h2><p class="mt-1.5 text-base leading-[1.4] text-muted">For registered Farmer and MAO Staff accounts.</p></div>
                        <div><label for="login-email" class="lp-label text-lg">Email</label><input type="email" id="login-email" name="email" value="{{ $authMode === 'login' ? old('email') : '' }}" class="lp-input lp-input-lg @if ($loginErrors->has('email')) border-red-600 @endif" placeholder="juan@gmail.com" autocomplete="username" required @if ($loginErrors->has('email')) aria-invalid="true" aria-describedby="login-email-error" @endif>@if ($loginErrors->has('email'))<p id="login-email-error" class="lp-error">{{ $loginErrors->first('email') }}</p>@endif</div>
                        <div><label for="login-password" class="lp-label text-lg">Password</label><div class="relative"><input type="password" id="login-password" name="password" class="lp-input lp-input-lg pr-12 @if ($loginErrors->has('password')) border-red-600 @endif" placeholder="••••••••" autocomplete="current-password" required @if ($loginErrors->has('password')) aria-invalid="true" aria-describedby="login-password-error" @endif><x-password-toggle for="login-password" class="lp-pwd-toggle" /></div>@if ($loginErrors->has('password'))<p id="login-password-error" class="lp-error">{{ $loginErrors->first('password') }}</p>@endif</div>
                        @if ($requiresCaptcha)<div class="rounded-xl bg-surface p-4 text-center"><label for="login-captcha" class="lp-label text-lg">Type the security code</label><img class="mx-auto mb-3 h-14 w-auto rounded-lg border border-line bg-white" width="190" height="64" data-captcha-src="{{ route('captcha.image', ['context' => 'login']) }}" @if ($authMode === 'login') src="{{ route('captcha.image', ['context' => 'login', 'v' => now()->timestamp]) }}" @endif alt="Security code image"><input type="text" id="login-captcha" name="captcha_answer" class="lp-input lp-input-lg text-center tracking-widest" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="20" required @if ($loginErrors->has('captcha_answer')) aria-invalid="true" aria-describedby="login-captcha-error" @endif>@if ($loginErrors->has('captcha_answer'))<p id="login-captcha-error" class="lp-error">{{ $loginErrors->first('captcha_answer') }}</p>@endif</div>@endif
                        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1"><a href="{{ route('password.request') }}" class="inline-flex min-h-11 items-center rounded text-base font-semibold text-brand hover:text-brand-dark" data-forgot>Forgot password?</a><a href="{{ route('registration.resubmit') }}" class="inline-flex min-h-11 items-center text-sm text-brand">Resubmit a rejected application</a></div><button type="submit" class="lp-btn-primary lp-btn-lg w-full">Log in</button>
                    </form>
                </div>
                <div role="tabpanel" id="auth-panel-register" aria-labelledby="auth-tab-register" data-auth-panel="register" class="lp-reg" @if ($authMode !== 'register') hidden @endif><h2 class="mb-2 text-[1.625rem] font-semibold leading-[1.2] tracking-[-0.02em] text-ink">Create an account</h2>@include('auth.partials.register-form', ['formErrors' => $registerErrors, 'useOld' => $authMode === 'register', 'captchaLazy' => $authMode !== 'register'])<p class="mt-4 text-sm leading-[1.4] text-muted">MAO Staff accounts are set up by the system administrators. Contact the Municipal Agriculture Office if you need staff access.</p></div>
            @endauth
        </div>
    </div>
</section>
