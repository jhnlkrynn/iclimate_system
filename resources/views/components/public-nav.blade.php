{{--
    Sticky top nav for the public pages (Figma: Top nav).
    landing: true on the home page (anchors scroll in place, user + bell icons on the right);
             false elsewhere (anchors go to the home page, Log in + Create account on the right).
--}}
@props(['landing' => false])
@php
    $base = $landing ? '' : url('/');
    $links = [
        'hero' => 'Home',
        'about' => 'About',
        'features' => 'Features',
        'roles' => 'Who it’s for',
        'contact' => 'Contact',
    ];
@endphp
<header class="sticky top-0 z-40 border-b border-line bg-white transition-shadow duration-200 [&.is-scrolled]:shadow-[0_1px_0_rgba(17,26,19,0.06),0_6px_16px_rgba(17,26,19,0.06)]" data-public-nav>
    <div class="lp-wrap grid grid-cols-[1fr_auto] items-center gap-5 py-3.5 lg:grid-cols-[1fr_auto_1fr]" data-nav-row>
        <a href="{{ url('/') }}" class="flex min-w-0 items-center gap-2.5 rounded-lg" aria-label="iClimate, Municipal Agriculture Office, Lian, Batangas: home" data-nav-home>
            <img src="{{ asset('images/mao-lian-seal.png') }}" alt="Municipal Agriculture Office logo" width="44" height="44" class="h-[44px] w-[44px] shrink-0 object-contain">
            <img src="{{ asset('images/iclimate-logo.png') }}" alt="" width="50" height="44" class="h-[44px] w-[50px] shrink-0 object-contain">
            <span class="hidden min-w-0 flex-col leading-[1.3] sm:flex">
                <span class="truncate text-sm font-semibold text-ink">Municipal Agriculture Office</span>
                <span class="text-[0.8125rem] text-muted">Lian, Batangas</span>
            </span>
        </a>

        <nav aria-label="Main" class="hidden lg:block" data-nav-links>
            <ul class="flex items-center justify-center gap-1">
                @foreach ($links as $id => $label)
                    <li><a href="{{ $base }}#{{ $id }}" class="lp-nav-link{{ $landing && $id === 'hero' ? ' is-active' : '' }}" data-nav-link="{{ $id }}" @if ($landing && $id === 'hero') aria-current="true" @endif>{{ $label }}</a></li>
                @endforeach
            </ul>
        </nav>

        <div class="flex items-center justify-end gap-2">
            @if ($landing)
                {{-- Live weather for Lian (resources/js/landing.js): a skeleton until /api/weather/current answers, hidden if it
                     fails or takes over 5s. Opens a small panel with the details. --}}
                <div class="relative" data-weather-chip-wrap>
                    <button type="button" class="inline-flex h-[44px] min-w-[88px] items-center justify-center gap-1.5 rounded-full border border-line bg-white px-3 text-sm font-semibold text-ink transition-colors hover:bg-tint lg:min-w-[136px]"
                            aria-expanded="false" aria-controls="weatherPanel" aria-label="Current weather in Lian: loading" data-weather-chip data-state="loading"
                            data-weather-url="{{ route('api.weather.current') }}">
                        <span class="h-4 w-14 rounded-full bg-line lg:w-24" data-weather-skeleton aria-hidden="true"></span>
                        @foreach (['sun', 'cloud', 'cloud-rain', 'cloud-drizzle', 'cloud-lightning', 'wind'] as $weatherIcon)
                            <x-icon :name="$weatherIcon" class="hidden h-5 w-5 text-brand" data-weather-icon="{{ $weatherIcon }}" />
                        @endforeach
                        <span class="hidden" data-weather-chip-text><span data-weather-chip-temp></span><span class="hidden lg:inline"> · Lian</span></span>
                    </button>
                    <div id="weatherPanel" role="dialog" aria-label="Weather in Lian" tabindex="-1" hidden data-weather-panel
                         class="absolute right-0 top-full z-50 mt-2 w-64 max-w-[calc(100vw-2rem)] max-lg:fixed max-lg:inset-x-4 max-lg:top-[var(--nav-h)] max-lg:w-auto max-lg:max-w-none rounded-xl border border-line bg-white p-4 text-sm text-ink shadow-[0_12px_28px_rgba(17,26,19,0.14)] focus:outline-none">
                        <p class="text-base font-semibold" data-weather-panel-condition></p>
                        <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2">
                            <dt class="text-muted">Temperature</dt><dd class="text-right font-semibold" data-weather-panel-temp>--</dd>
                            <dt class="text-muted">Humidity</dt><dd class="text-right font-semibold" data-weather-panel-humidity>--</dd>
                            <dt class="text-muted">Wind speed</dt><dd class="text-right font-semibold" data-weather-panel-wind>--</dd>
                            <dt class="text-muted">Rain today</dt><dd class="text-right font-semibold" data-weather-panel-rain>--</dd>
                        </dl>
                        <p class="mt-3 text-xs text-muted" data-weather-panel-updated></p>
                        {{-- Guests are sent to the login card (no page is remembered for after the login), signed-in users to the heat map. --}}
                        @auth
                            <a href="{{ route('heat-map.index') }}" class="lp-link mt-3 inline-flex min-h-11 items-center" data-weather-heat-map>View heat map</a>
                        @else
                            <a href="#login" class="lp-link mt-3 inline-flex min-h-11 items-center" data-weather-heat-map>Log in to view the heat map</a>
                        @endauth
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="lp-btn-neutral hidden sm:inline-flex" data-nav-login>Log in</a>
                <a href="{{ url('/') }}#register" class="lp-btn-primary hidden sm:inline-flex" data-nav-register>Create account</a>
            @endif
            <button type="button" class="lp-icon-btn lg:hidden" aria-expanded="false" aria-controls="publicNavMenu" aria-label="Open menu" data-nav-toggle>
                <x-icon name="menu" class="h-5 w-5" data-icon-open />
                <x-icon name="x" class="hidden h-5 w-5" data-icon-close />
            </button>
        </div>
    </div>

    <nav id="publicNavMenu" aria-label="Main (mobile)" class="hidden border-t border-line bg-white lg:hidden" data-nav-menu>
        <ul class="lp-wrap flex flex-col gap-1 py-3">
            @foreach ($links as $id => $label)
                <li><a href="{{ $base }}#{{ $id }}" class="lp-nav-link block py-3" data-nav-link="{{ $id }}">{{ $label }}</a></li>
            @endforeach
            @unless ($landing)
                <li class="mt-2 flex gap-2 sm:hidden">
                    <a href="{{ route('login') }}" class="lp-btn-neutral flex-1">Log in</a>
                    <a href="{{ url('/') }}#register" class="lp-btn-primary flex-1">Create account</a>
                </li>
            @endunless
        </ul>
    </nav>
</header>
@once
    <script>
        // Mobile menu: the button opens and closes it; a link click or Esc closes it.
        document.addEventListener('DOMContentLoaded', () => {
            const nav = document.querySelector('[data-public-nav]');
            const toggle = nav?.querySelector('[data-nav-toggle]');
            const menu = nav?.querySelector('[data-nav-menu]');
            if (!toggle || !menu) return;
            const setOpen = (open) => {
                menu.classList.toggle('hidden', !open);
                toggle.setAttribute('aria-expanded', String(open));
                toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
                toggle.querySelector('[data-icon-open]').classList.toggle('hidden', open);
                toggle.querySelector('[data-icon-close]').classList.toggle('hidden', !open);
            };
            toggle.addEventListener('click', () => setOpen(menu.classList.contains('hidden')));
            menu.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setOpen(false)));
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !menu.classList.contains('hidden')) {
                    setOpen(false);
                    toggle.focus();
                }
            });
            window.matchMedia('(min-width: 1024px)').addEventListener('change', (event) => { if (event.matches) setOpen(false); });

            // A hairline shadow once the page has scrolled past 8px.
            const markScrolled = () => nav.classList.toggle('is-scrolled', window.scrollY > 8);
            window.addEventListener('scroll', markScrolled, { passive: true });
            markScrolled();
        });
    </script>
@endonce
