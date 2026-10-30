{{--
    Top nav for the public pages: 72px, aligned to the hero's container (.lp-wrap). Styles: landing.css (.lp-header).
    landing: true on the home page: fixed over the hero photo, transparent with white text on a dark scrim
             (data-nav-state="hero"), then solid white once the hero has scrolled past (data-nav-state="solid").
             Anchors scroll in place; the weather chip and Log in (opens the sign-in card) on the right.
    landing: false elsewhere: sticky and always solid; anchors go to the home page, Log in + Create account on the right.
    Below 1024px the links and Log in move into a drawer opened by the menu button.
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
    $dashboard = auth()->check() ? route(auth()->user()->dashboardRoute()) : null;
@endphp
<header class="lp-header {{ $landing ? 'fixed inset-x-0' : 'sticky' }} top-0 z-40" data-public-nav data-nav-state="{{ $landing ? 'hero' : 'solid' }}">
    <div class="lp-wrap grid h-full grid-cols-[1fr_auto] items-center gap-4 lg:grid-cols-[1fr_auto_1fr]" data-nav-row>
        <a href="{{ url('/') }}" class="flex min-w-0 items-center gap-3 justify-self-start rounded-md" aria-label="iClimate, Municipal Agriculture Office, Lian, Batangas: home" data-nav-home>
            {{-- The seal's picture has a white square behind it: the circle crop keeps that off the photo. --}}
            <img src="{{ asset('images/mao-lian-seal.png') }}" alt="Municipal Agriculture Office logo" width="40" height="40" class="h-10 w-10 shrink-0 rounded-full object-contain">
            <span class="lp-header-divider h-8 w-px shrink-0" aria-hidden="true"></span>
            {{-- The light logo (white lettering) over the hero, the regular one on white. --}}
            <img src="{{ asset('images/iclimate-logo.png') }}" alt="" width="50" height="40" class="lp-logo-on-light h-10 w-[50px] shrink-0 object-contain">
            <img src="{{ asset('images/iclimate-logo-light.png') }}" alt="" width="50" height="40" class="lp-logo-on-dark h-10 w-[50px] shrink-0 object-contain">
            <span class="hidden min-w-0 flex-col leading-tight sm:flex">
                <span class="lp-header-title truncate text-sm font-semibold">Municipal Agriculture Office</span>
                <span class="lp-header-sub text-xs">Lian, Batangas</span>
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
                    <button type="button" class="lp-weather-chip"
                            aria-expanded="false" aria-controls="weatherPanel" aria-label="Current weather in Lian: loading" data-weather-chip data-state="loading"
                            data-weather-url="{{ route('api.weather.current') }}">
                        <span class="lp-weather-skeleton h-3.5 w-12 rounded-full lg:w-20" data-weather-skeleton aria-hidden="true"></span>
                        @foreach (['sun', 'cloud', 'cloud-rain', 'cloud-drizzle', 'cloud-lightning', 'wind'] as $weatherIcon)
                            <x-icon :name="$weatherIcon" class="lp-weather-icon hidden h-[18px] w-[18px]" data-weather-icon="{{ $weatherIcon }}" />
                        @endforeach
                        <span class="hidden" data-weather-chip-text><span data-weather-chip-temp></span><span class="hidden lg:inline"> · Lian</span></span>
                    </button>
                    <div id="weatherPanel" role="dialog" aria-label="Weather in Lian" tabindex="-1" hidden data-weather-panel
                         class="absolute right-0 top-full z-50 mt-2 w-64 max-w-[calc(100vw-2rem)] max-lg:fixed max-lg:inset-x-4 max-lg:top-[var(--nav-h)] max-lg:w-auto max-lg:max-w-none rounded-lg border border-line bg-white p-4 text-sm text-ink shadow-[0_8px_20px_rgba(17,26,19,0.12)] focus:outline-none">
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
                {{-- #login opens the hero's sign-in card in Log in mode and scrolls to it (resources/js/landing.js). --}}
                @if ($dashboard)
                    <a href="{{ $dashboard }}" class="lp-header-btn hidden lg:inline-flex" data-nav-login>Go to dashboard</a>
                @else
                    <a href="#login" class="lp-header-btn hidden lg:inline-flex" data-nav-login>Log in</a>
                @endif
            @else
                <a href="{{ route('login') }}" class="lp-btn-neutral hidden lg:inline-flex" data-nav-login>Log in</a>
                <a href="{{ url('/') }}#register" class="lp-btn-primary hidden lg:inline-flex" data-nav-register>Create account</a>
            @endif
            <button type="button" class="lp-header-icon-btn lg:hidden" aria-expanded="false" aria-controls="publicNavMenu" aria-label="Open menu" data-nav-toggle>
                <x-icon name="menu" class="h-6 w-6" />
            </button>
        </div>
    </div>

    {{-- Small screens: a full-height drawer from the right. Esc, the close button, a tap on the backdrop or a link closes it;
         Tab stays inside while it is open. --}}
    <div id="publicNavMenu" class="fixed inset-0 z-50 lg:hidden" hidden data-nav-menu>
        <div class="absolute inset-0 bg-ink/50" data-nav-backdrop></div>
        <div role="dialog" aria-modal="true" aria-label="Menu" class="absolute inset-y-0 right-0 flex w-[min(20rem,85vw)] flex-col border-l border-line bg-white" data-nav-drawer>
            <div class="flex h-[var(--nav-h)] shrink-0 items-center justify-between border-b border-line px-4">
                <span class="text-base font-semibold text-ink">Menu</span>
                <button type="button" class="lp-icon-btn" aria-label="Close menu" data-nav-close>
                    <x-icon name="x" class="h-6 w-6" />
                </button>
            </div>
            <nav aria-label="Main (mobile)" class="flex-1 overflow-y-auto">
                <ul class="flex flex-col py-2">
                    @foreach ($links as $id => $label)
                        <li><a href="{{ $base }}#{{ $id }}" class="lp-drawer-link{{ $landing && $id === 'hero' ? ' is-active' : '' }}" data-nav-link="{{ $id }}" @if ($landing && $id === 'hero') aria-current="true" @endif>{{ $label }}</a></li>
                    @endforeach
                </ul>
            </nav>
            <div class="flex shrink-0 flex-col gap-2 border-t border-line p-4">
                @if ($landing)
                    @if ($dashboard)
                        <a href="{{ $dashboard }}" class="lp-btn-primary w-full" data-nav-menu-login>Go to dashboard</a>
                    @else
                        <a href="#login" class="lp-btn-primary w-full" data-nav-menu-login>Log in</a>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="lp-btn-primary w-full" data-nav-menu-login>Log in</a>
                    <a href="{{ url('/') }}#register" class="lp-btn-neutral w-full">Create account</a>
                @endif
            </div>
        </div>
    </div>
</header>
@once
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const nav = document.querySelector('[data-public-nav]');
            if (!nav) return;

            // Landing page: transparent over the hero, solid white once the hero has scrolled up behind the nav.
            const hero = nav.classList.contains('fixed') ? document.getElementById('hero') : null;
            if (hero) {
                let queued = false;
                const update = () => {
                    queued = false;
                    nav.dataset.navState = hero.getBoundingClientRect().bottom > nav.offsetHeight ? 'hero' : 'solid';
                };
                const queue = () => { if (!queued) { queued = true; requestAnimationFrame(update); } };
                window.addEventListener('scroll', queue, { passive: true });
                window.addEventListener('resize', queue);
                update();
            }

            // Mobile drawer.
            const toggle = nav.querySelector('[data-nav-toggle]');
            const menu = nav.querySelector('[data-nav-menu]');
            if (!toggle || !menu) return;
            const drawer = menu.querySelector('[data-nav-drawer]');
            const focusables = () => [...drawer.querySelectorAll('a[href], button')].filter((el) => el.offsetParent !== null);
            const setOpen = (open, { restoreFocus = false } = {}) => {
                menu.hidden = !open;
                toggle.setAttribute('aria-expanded', String(open));
                document.documentElement.style.overflow = open ? 'hidden' : '';
                if (open) focusables()[0]?.focus();
                else if (restoreFocus) toggle.focus();
            };
            toggle.addEventListener('click', () => setOpen(true));
            menu.querySelector('[data-nav-close]').addEventListener('click', () => setOpen(false, { restoreFocus: true }));
            menu.querySelector('[data-nav-backdrop]').addEventListener('click', () => setOpen(false, { restoreFocus: true }));
            menu.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setOpen(false)));
            document.addEventListener('keydown', (event) => {
                if (menu.hidden) return;
                if (event.key === 'Escape') {
                    setOpen(false, { restoreFocus: true });
                } else if (event.key === 'Tab') {
                    const items = focusables();
                    const first = items[0];
                    const last = items[items.length - 1];
                    if (event.shiftKey && (document.activeElement === first || !drawer.contains(document.activeElement))) {
                        event.preventDefault();
                        last.focus();
                    } else if (!event.shiftKey && (document.activeElement === last || !drawer.contains(document.activeElement))) {
                        event.preventDefault();
                        first.focus();
                    }
                }
            });
            window.matchMedia('(min-width: 1024px)').addEventListener('change', (event) => { if (event.matches && !menu.hidden) setOpen(false); });
        });
    </script>
@endonce
