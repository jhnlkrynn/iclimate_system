{{-- Hero (Figma: Hero): fills one screen below the nav. The login card posts to the regular login route; a failed
     attempt comes back here with its errors. --}}
{{-- Background: a rice field in Lian (versioned by file time so a replaced photo shows at once). The photo is blurred
     behind the text panel so the text stays readable. --}}
<section id="hero" data-section class="lp-screen relative bg-[#EAF4E4] bg-cover bg-center py-10 lg:py-12" style="background-image: url('{{ asset('images/rice-hero-field.jpg') }}?v={{ filemtime(public_path('images/rice-hero-field.jpg')) }}')">
    <div class="lp-wrap flex flex-col gap-10 lg:flex-row lg:items-center lg:gap-16">
        <div class="flex min-w-0 flex-1 flex-col items-start gap-6 rounded-lg border border-line bg-white/75 p-6 backdrop-blur [container-type:inline-size] sm:p-9">
            <p class="text-base font-semibold leading-[1.4] text-brand sm:text-lg">For the rice farmers of Lian, Batangas</p>
            <h1 class="text-[clamp(2.75rem,min(6vw,20cqi),5.5rem)] font-bold leading-[0.98] tracking-[-0.035em] text-ink">Plan every planting around the weather.</h1>
            <p class="max-w-[36rem] text-[clamp(1.125rem,1.6vw,1.375rem)] leading-[1.5] text-muted">Local forecasts, MAO planting advisories and rice yield outlooks in one place, kept up to date by the Municipal Agriculture Office.</p>
            <div class="flex flex-wrap gap-3 pt-2">
                <a href="#features" class="lp-btn-primary lp-btn-lg" data-hero-features>Explore features</a>
                <a href="#how" class="lp-btn-neutral lp-btn-lg" data-hero-how>How it works</a>
            </div>
        </div>

        {{-- Log in or create an account in place: the Sign in / Create account tabs switch the card without leaving the
             page (resources/js/landing.js); /#register and /#login (the footer and the nav) open it in that mode. --}}
        <div class="w-full shrink-0 rounded-2xl border border-line bg-white px-6 py-8 shadow-[0_8px_24px_rgba(18,26,18,0.06)] sm:px-9 lg:w-[30rem]" data-login-card @guest data-auth-card data-auth-mode="{{ $authMode }}" @endguest>
            @auth
                <h2 class="text-[1.625rem] font-semibold leading-[1.2] tracking-[-0.02em] text-ink">Welcome back, {{ auth()->user()->name }}</h2>
                <p class="mt-1.5 text-base leading-[1.4] text-muted">You are logged in to iClimate.</p>
                <a href="{{ route(auth()->user()->dashboardRoute()) }}" class="lp-btn-primary lp-btn-lg mt-6 w-full" data-go-dashboard>Go to dashboard</a>
                {{-- So a different person on this browser can sign in: logging out comes back to /#login. --}}
                <form method="POST" action="{{ route('logout') }}" class="mt-3 text-center" data-landing-logout>
                    @csrf
                    <button type="submit" class="inline-flex min-h-11 items-center rounded text-base font-semibold text-brand hover:text-ink">Sign out</button>
                </form>
            @else
                {{-- The only switch between the two forms: tabs (click, Enter/Space, or Left/Right/Home/End). --}}
                <div role="tablist" aria-label="Log in or create an account" class="mb-6 grid grid-cols-2 gap-1 rounded-xl border border-[#DCE8D6] bg-[#F3FAF0] p-1" data-auth-toggle>
                    @foreach (['login' => 'Sign in', 'register' => 'Create account'] as $mode => $label)
                        <button type="button" role="tab" id="auth-tab-{{ $mode }}" aria-controls="auth-panel-{{ $mode }}" data-auth-switch="{{ $mode }}"
                                aria-selected="{{ $authMode === $mode ? 'true' : 'false' }}" tabindex="{{ $authMode === $mode ? '0' : '-1' }}"
                                class="flex min-h-11 items-center justify-center rounded-lg text-base font-semibold text-[#4D5C50] hover:bg-[#E4F3DE] hover:text-[#1F3D22] aria-selected:bg-[#A6D79B] aria-selected:text-[#173B1F]">{{ $label }}</button>
                    @endforeach
                </div>
                <p class="sr-only" aria-live="polite" data-auth-status></p>

                <div role="tabpanel" id="auth-panel-login" aria-labelledby="auth-tab-login" data-auth-panel="login" @if ($authMode !== 'login') hidden @endif>
                    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-5" data-login-form>
                        @csrf
                        <div>
                            <h2 class="text-[1.625rem] font-semibold leading-[1.2] tracking-[-0.02em] text-ink">Log in to iClimate</h2>
                            <p class="mt-1.5 text-base leading-[1.4] text-muted">For registered Farmer, MAO Staff and IT Personnel accounts.</p>
                        </div>

                        <div>
                            <label for="login-email" class="lp-label text-lg">Email</label>
                            <input type="email" id="login-email" name="email" value="{{ $authMode === 'login' ? old('email') : '' }}" class="lp-input lp-input-lg @if ($loginErrors->has('email')) border-red-600 @endif" placeholder="juan@gmail.com" autocomplete="username" required
                                   @if ($loginErrors->has('email')) aria-invalid="true" aria-describedby="login-email-error" @endif>
                            @if ($loginErrors->has('email'))<p id="login-email-error" class="lp-error">{{ $loginErrors->first('email') }}</p>@endif
                        </div>

                        <div>
                            <label for="login-password" class="lp-label text-lg">Password</label>
                            <div class="relative">
                                <input type="password" id="login-password" name="password" class="lp-input lp-input-lg pr-12 @if ($loginErrors->has('password')) border-red-600 @endif" placeholder="••••••••" autocomplete="current-password" required
                                       @if ($loginErrors->has('password')) aria-invalid="true" aria-describedby="login-password-error" @endif>
                                <x-password-toggle for="login-password" class="lp-pwd-toggle" />
                            </div>
                            @if ($loginErrors->has('password'))<p id="login-password-error" class="lp-error">{{ $loginErrors->first('password') }}</p>@endif
                        </div>

                        @if ($requiresCaptcha)
                            <div class="rounded-xl bg-surface p-4 text-center">
                                <label for="login-captcha" class="lp-label text-lg">Type the security code</label>
                                {{-- One captcha per session: the picture loads only while this form is showing (landing.js). --}}
                                <img class="mx-auto mb-3 h-14 w-auto rounded-lg border border-line bg-white" width="190" height="64" data-captcha-src="{{ route('captcha.image', ['context' => 'login']) }}"
                                     @if ($authMode === 'login') src="{{ route('captcha.image', ['context' => 'login', 'v' => now()->timestamp]) }}" @endif alt="Security code image">
                                <input type="text" id="login-captcha" name="captcha_answer" class="lp-input lp-input-lg text-center tracking-widest" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="20" required
                                       @if ($loginErrors->has('captcha_answer')) aria-invalid="true" aria-describedby="login-captcha-error" @endif>
                                @if ($loginErrors->has('captcha_answer'))<p id="login-captcha-error" class="lp-error">{{ $loginErrors->first('captcha_answer') }}</p>@endif
                            </div>
                        @endif

                        <div class="flex flex-wrap items-center justify-end gap-x-4 gap-y-2">
                            <a href="{{ route('password.request') }}" class="inline-flex min-h-11 items-center rounded text-base font-semibold text-brand hover:text-ink" data-forgot>Forgot password?</a>
                        </div>

                        <button type="submit" class="lp-btn-primary lp-btn-lg w-full">Log in</button>
                    </form>
                </div>

                <div role="tabpanel" id="auth-panel-register" aria-labelledby="auth-tab-register" data-auth-panel="register" class="lp-reg" @if ($authMode !== 'register') hidden @endif>
                    <h2 class="mb-2 text-[1.625rem] font-semibold leading-[1.2] tracking-[-0.02em] text-ink">Create an account</h2>
                    @include('auth.partials.register-form', [
                        'formErrors' => $registerErrors,
                        'useOld' => $authMode === 'register',
                        'captchaLazy' => $authMode !== 'register',
                    ])
                    <p class="mt-4 text-sm leading-[1.4] text-muted">MAO Staff and IT Personnel accounts are set up by the system administrators. Contact the Municipal Agriculture Office if you need staff access.</p>
                </div>
            @endauth
        </div>
    </div>
</section>
