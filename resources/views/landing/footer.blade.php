{{-- Footer (Figma: Footer). --}}
<footer class="border-t border-line bg-surface pb-7 pt-14">
    <div class="lp-wrap">
        <div class="flex flex-col gap-10 md:flex-row md:flex-wrap md:gap-16">
            <div class="flex min-w-0 flex-1 flex-col items-start gap-3.5 md:basis-full lg:basis-0">
                <a href="{{ url('/') }}" class="flex gap-2.5 rounded-lg" aria-label="iClimate home" data-footer-home>
                    <img src="{{ asset('images/mao-lian-seal.png') }}" alt="Municipal Agriculture Office logo" width="40" height="40" class="h-10 w-10 object-contain">
                    <img src="{{ asset('images/iclimate-logo.png') }}" alt="" width="46" height="40" class="h-10 w-[46px] object-contain">
                </a>
                <p class="max-w-[320px] text-base leading-[1.4] text-muted">Weather, planting advice and rice yield forecasts for the farmers of Lian, Batangas.</p>
                <div class="flex gap-2">
                    <a href="https://www.facebook.com/profile.php?id=61551743430540" target="_blank" rel="noopener noreferrer" aria-label="iClimate on Facebook (opens in a new tab)"
                       class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-line bg-white text-ink hover:bg-tint" data-footer-facebook>
                        <x-icon name="facebook" class="h-5 w-5" />
                    </a>
                    <a href="mailto:{{ $officeEmail }}" aria-label="Email the Municipal Agriculture Office at {{ $officeEmail }}"
                       class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-line bg-white text-ink hover:bg-tint" data-footer-email>
                        <x-icon name="mail" class="h-5 w-5" />
                    </a>
                </div>
            </div>

            <nav aria-labelledby="footer-explore" class="md:w-[200px]">
                <h2 id="footer-explore" class="text-base font-semibold leading-[1.4] text-ink">Explore</h2>
                <ul class="mt-2.5 flex flex-col gap-2.5 text-base leading-[1.4] text-muted">
                    <li><a href="#about" class="rounded hover:text-ink" data-footer-link="about">About</a></li>
                    <li><a href="#features" class="rounded hover:text-ink" data-footer-link="features">Features</a></li>
                    <li><a href="#roles" class="rounded hover:text-ink" data-footer-link="roles">Who it’s for</a></li>
                    <li><a href="#contact" class="rounded hover:text-ink" data-footer-link="contact">Contact</a></li>
                </ul>
            </nav>

            <nav aria-labelledby="footer-account" class="md:w-[200px]">
                <h2 id="footer-account" class="text-base font-semibold leading-[1.4] text-ink">Account</h2>
                <ul class="mt-2.5 flex flex-col gap-2.5 text-base leading-[1.4] text-muted">
                    <li><a href="{{ route('login') }}" class="rounded hover:text-ink" data-footer-account="login">Log in</a></li>
                    <li><a href="{{ auth()->guest() ? '#register' : route('register') }}" class="rounded hover:text-ink" data-footer-account="register">Create account</a></li>
                    <li><a href="{{ route('password.request') }}" class="rounded hover:text-ink" data-footer-account="password">Forgot password</a></li>
                </ul>
            </nav>

            <div class="md:w-[200px]">
                <h2 class="text-base font-semibold leading-[1.4] text-ink">Visit us</h2>
                <ul class="mt-2.5 flex flex-col gap-2.5 text-base leading-[1.4] text-muted">
                    <li>Municipal Agriculture Office</li>
                    <li>Lian, Batangas, Philippines</li>
                    <li>Mon to Fri, 8 AM to 5 PM</li>
                </ul>
            </div>
        </div>

        <div class="mt-12 flex flex-col gap-3 border-t border-line pt-5 text-sm leading-[1.4] sm:flex-row sm:items-center sm:justify-between">
            <p class="text-muted">&copy; {{ now()->year }} iClimate Research Group · Batangas State University ARASOF-Nasugbu</p>
            <a href="#top" class="inline-flex w-fit items-center gap-1.5 rounded font-semibold text-ink hover:text-brand" data-back-to-top>
                Back to top <x-icon name="arrow-up" class="h-4 w-4" />
            </a>
        </div>
    </div>
</footer>
