<x-app-layout>
    {{-- Farmer – Naicel (2026-10-07): the page is called "Farmer Dashboard" in both languages (manuscript name) (farmer.nav.home), sidebar and browser tab. --}}
    <x-slot:title>{{ __('farmer.nav.home') }}</x-slot:title>
    {{-- Farmer Home (2026-10): five plain parts in reading order, all from saved data (FarmerDashboardService).
         1 Mensahe Ngayon, 2 weather today, 3 barangay risk, 4 expected yield this season, 5 the next 7 days and the
         latest planting advice. The Typhoon Safety Check-In appears only while a typhoon advisory is in effect. --}}
    <div class="mfd" data-farmer-dashboard data-dashboard-realtime data-dashboard-user-id="{{ auth()->id() }}" data-dashboard-role="farmer" data-live-url="{{ route('farmer.dashboard.live') }}" lang="{{ $f['locale'] ?? app()->getLocale() }}"
        data-text-refreshing="{{ __('farmer.header.refreshing') }}" data-text-refresh="{{ __('farmer.header.refresh') }}"
        data-text-failed="{{ __('farmer.state.refresh_failed') }}" data-text-language-failed="{{ __('farmer.state.language_failed') }}">

        <div class="mfd-sr-only" role="status" aria-live="polite" data-mfd-live></div>
        <div class="mfd-soft" role="alert" data-mfd-refresh-error hidden><span data-mfd-refresh-error-text>{{ __('farmer.state.refresh_failed') }}</span></div>

        {{-- The "is this farm part of an association?" question is asked on My Farm (farmer-records/my-farm). --}}

        @foreach (\App\Http\Controllers\DashboardController::FARMER_SLOTS as $name)
            @php $html = view('dashboards.farmer.'.$name, ['f' => $f])->render(); @endphp
            <div class="mfd-slot" data-slot="{{ $name }}" @if (trim($html) === '') hidden @endif>{!! $html !!}</div>
        @endforeach

        {{-- Farmer – Naicel: the two simple charts. Not part of the timed refresh (their numbers change with a save). --}}
        @include('dashboards.farmer.charts')

        {{-- Panel revision 4.4 C: the farmer's assigned MAO personnel. --}}
        @include('partials.farmer-mao-contact', ['locale' => $f['locale'] ?? app()->getLocale()])

        {{-- Farmer Survey – Naicel (2026-10-09): the way in to the short survey about the predictions and advice. --}}
        <section class="fh-card fh-survey" aria-labelledby="fhSurveyTitle" data-fh-survey>
            <h2 id="fhSurveyTitle" class="fh-kicker">{{ __('farmer.survey.home_title') }}</h2>
            <p class="fh-words">{{ __('farmer.survey.home_text') }}</p>
            <a class="mfd-btn" href="{{ route('farmer.survey.show') }}" style="align-self: flex-start">{{ __('farmer.survey.open') }}</a>
        </section>

        {{-- Farmer – Naicel (2026-11-22): the one summary panel the cards open. Every card is a <button data-panel="…">
             (kpis, weather, risk and harvest partials). A drawer from the right with a dark overlay (a sheet from the
             bottom on a phone); closes with the X, the overlay or Esc. 2026-11-23 (audit M-8): what a panel shows comes
             from the "panels" slot (dashboards/farmer/panels.blade.php), which the timed refresh updates with the cards,
             so an open panel never shows older numbers than its card after the next refresh. --}}
        <div class="fh-drawer" id="fhDrawer" data-fh-drawer hidden>
            <div class="fh-drawer-backdrop" data-fh-drawer-close></div>
            <div class="fh-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="fhDrawerTitle" tabindex="-1" data-fh-drawer-panel>
                <button type="button" class="fh-drawer-x" data-fh-drawer-close aria-label="{{ __('farmer.kpis.detail.close') }}">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
                <div class="fh-drawer-body" data-fh-drawer-body></div>
            </div>
        </div>
        <script @nonce>
            // The summary panel: a card click opens it with that card's panel; X, the overlay or Esc closes it. On its own
            // (not part of farmer-dashboard.js), so nothing else on the page can stop the cards from working.
            (function () {
                var drawer = document.getElementById('fhDrawer');
                if (!drawer) return;
                document.body.appendChild(drawer);        // out of the animated page sections, so it always covers the screen
                var panel = drawer.querySelector('[data-fh-drawer-panel]');
                var body = drawer.querySelector('[data-fh-drawer-body]');
                var opener = null;
                var timer = null;

                // Fill the drawer from the card's template, which the timed refresh keeps current.
                function fill(key) {
                    var template = document.querySelector('template[data-panel-template="' + key + '"]');
                    if (!template) return false;
                    body.replaceChildren(template.content.cloneNode(true));
                    body.dataset.panel = key;
                    var title = body.querySelector('[data-panel-title]');
                    if (title) title.id = 'fhDrawerTitle';
                    return true;
                }

                function open(key, card) {
                    if (!fill(key)) return;
                    clearTimeout(timer);
                    opener = card;
                    drawer.hidden = false;
                    document.documentElement.classList.add('fh-drawer-lock');
                    requestAnimationFrame(function () { drawer.classList.add('is-open'); panel.focus({ preventScroll: true }); });
                }

                function close() {
                    if (drawer.hidden) return;
                    drawer.classList.remove('is-open');
                    document.documentElement.classList.remove('fh-drawer-lock');
                    timer = setTimeout(function () { drawer.hidden = true; body.replaceChildren(); delete body.dataset.panel; }, 220);
                    // The card may have been redrawn by the timed refresh meanwhile: focus its new copy.
                    var key = opener && opener.dataset.panel;
                    var card = opener && opener.isConnected ? opener : (key ? document.querySelector('[data-panel="' + key + '"]') : null);
                    opener = null;
                    if (card) card.focus({ preventScroll: true });
                }

                document.addEventListener('click', function (e) {
                    var card = e.target.closest('[data-panel]');
                    if (card && !drawer.contains(card)) { e.preventDefault(); open(card.dataset.panel, card); return; }
                    if (e.target.closest('[data-fh-drawer-close]')) close();
                });

                document.addEventListener('keydown', function (e) {
                    if (drawer.hidden) return;
                    if (e.key === 'Escape') { e.preventDefault(); close(); return; }
                    if (e.key !== 'Tab') return;
                    // Keep Tab inside the open panel.
                    var items = Array.prototype.filter.call(panel.querySelectorAll('a[href], button'), function (el) { return el.offsetParent !== null; });
                    if (!items.length) return;
                    var first = items[0], last = items[items.length - 1];
                    if (e.shiftKey && (document.activeElement === first || document.activeElement === panel)) { e.preventDefault(); last.focus(); }
                    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
                });

                // A refresh while a panel is open: show the new numbers in it at once.
                var slot = document.querySelector('.mfd-slot[data-slot=panels]');
                if (slot && 'MutationObserver' in window) {
                    new MutationObserver(function () { if (!drawer.hidden && body.dataset.panel) fill(body.dataset.panel); })
                        .observe(slot, { childList: true });
                }
            })();
        </script>
    </div>
</x-app-layout>
