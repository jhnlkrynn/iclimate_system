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
             (kpis, weather, risk and harvest partials); clicking one shows only its section below. A drawer from the
             right with a dark overlay (a sheet from the bottom on a phone); closes with the X, the overlay or Esc.
             The sections use only the data this page already has ($f), so opening one asks the server nothing. --}}
        @php
            $panels = [];
            $panelFor = ['farm' => 'farm-size', 'crop' => 'days-to-harvest', 'expected' => 'expected-harvest'];
            // The figures in the tiles: farm size, days to harvest, expected harvest, rain, risk, advisories, ...
            foreach ($f['kpis'] ?? [] as $tile) {
                if (! empty($tile['detail'])) {
                    $panels[$panelFor[$tile['key']] ?? $tile['key']] = $tile['detail'];
                }
            }
            // Weather today: current conditions, today's all-day forecast, wind, and when it was read.
            $w = $f['weather'] ?? ['state' => 'nodata'];
            if ($w['state'] === 'ok') {
                $todayForecast = collect($f['week']['days'] ?? [])->first(fn (array $d): bool => $d['is_today'] ?? false);
                $panels['weather'] = [
                    'title' => __('farmer.home.weather.title'),
                    'rows' => array_filter([
                        __('farmer.kpis.detail.conditions') => $w['condition'],
                        __('farmer.kpis.detail.temperature') => number_format($w['temperature'], 0).'°C',
                        __('farmer.kpis.detail.all_day') => trim(($todayForecast['condition'] ?? '').(($todayForecast['rain'] ?? null) ? ' · '.$todayForecast['rain'] : '')) ?: ($w['rain'] ?: null),
                        __('farmer.kpis.detail.rain') => $w['rain'] ?: __('farmer.home.rain.none'),
                        __('farmer.kpis.detail.wind') => $w['wind'] ?: __('farmer.kpis.detail.wind_calm'),
                        __('farmer.kpis.detail.source') => $w['provider'] ?? null,
                    ], fn ($v): bool => $v !== null && $v !== ''),
                    'text' => ($w['stale'] ?? false) ? __('farmer.kpis.detail.weather_stale') : null,
                    'updated_at' => $w['updated_at'],
                    'link' => route('weather-predictions.index').'#weather',
                ];
            }
            // Barangay risk: the Barangay Risk card's rating (the risk tile, when shown, has the same one).
            if (($f['risk']['state'] ?? null) === 'ok' && ! isset($panels['risk'])) {
                $panels['risk'] = \App\Services\Farmer\FarmerHomeKpis::riskDetail($f['risk'], (string) ($f['barangay'] ?? ''));
            }
            // Expected harvest: the tile's tons when the farm size is known; otherwise the yield card's t/ha alone.
            $h = $f['harvest'] ?? ['state' => 'nodata'];
            if ($h['state'] === 'ok' && ! isset($panels['expected-harvest'])) {
                $panels['expected-harvest'] = [
                    'title' => __('farmer.home.harvest.title'),
                    'rows' => array_filter([
                        __('farmer.kpis.detail.yield_per_ha') => number_format($h['yield'], 2).' t/ha',
                        __('farmer.kpis.detail.season') => (string) ($h['season'] ?? ''),
                        __('farmer.kpis.detail.compared') => (string) ($h['compare_label'] ?? ''),
                        __('farmer.kpis.detail.size') => (string) ($h['size_note'] ?? ''),
                    ], fn (string $v): bool => $v !== ''),
                    'text' => __('farmer.kpis.detail.harvest_how'),
                    'updated_at' => $h['ran_at'] ?? null,
                    'link' => route('weather-predictions.index').'#yield',
                ];
            }
        @endphp
        <div class="fh-drawer" id="fhDrawer" data-fh-drawer hidden>
            <div class="fh-drawer-backdrop" data-fh-drawer-close></div>
            <div class="fh-drawer-panel" role="dialog" aria-modal="true" tabindex="-1" data-fh-drawer-panel>
                <div class="fh-drawer-handle" aria-hidden="true"></div>
                <button type="button" class="fh-drawer-x" data-fh-drawer-close aria-label="{{ __('farmer.kpis.detail.close') }}">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
                @foreach ($panels as $key => $d)
                    <div class="fh-drawer-body" data-panel-section="{{ $key }}" hidden>
                        <h2 class="fh-drawer-title" id="fhPanel-{{ $key }}">{{ $d['title'] }}</h2>
                        @if (! empty($d['badge']['label']))
                            <p class="fh-risk-badge is-{{ $d['badge']['tone'] }}">{{ $d['badge']['label'] }}</p>
                        @endif
                        @if (! empty($d['rows']))
                            <dl class="fh-drawer-facts">
                                @foreach ($d['rows'] as $label => $value)
                                    <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                                @endforeach
                            </dl>
                        @endif
                        @if (($d['progress'] ?? null) !== null)
                            <div class="fh-drawer-progress">
                                <span class="fh-kpi-bar" role="img" aria-label="{{ __('farmer.kpis.crop.progress', ['percent' => $d['progress']]) }}"><span style="width: {{ $d['progress'] }}%"></span></span>
                                <span class="fh-muted">{{ __('farmer.kpis.crop.progress', ['percent' => $d['progress']]) }}</span>
                            </div>
                        @endif
                        @if (! empty($d['text']))
                            <p class="fh-words">{{ $d['text'] }}</p>
                        @endif
                        @if (! empty($d['updated_at']))
                            <x-updated-at :at="$d['updated_at']" :label="__('farmer.home.updated')" />
                        @endif
                        @if (! empty($d['link']))
                            <a class="fh-link fh-drawer-more" href="{{ $d['link'] }}">{{ __('farmer.kpis.detail.view_full') }} <span aria-hidden="true">→</span></a>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        <script @nonce>
            // The summary panel: a card click opens it at that card's section; X, the overlay or Esc closes it. On its own
            // (not part of farmer-dashboard.js), so nothing else on the page can stop the cards from working.
            (function () {
                var drawer = document.getElementById('fhDrawer');
                if (!drawer) return;
                document.body.appendChild(drawer);        // out of the animated page sections, so it always covers the screen
                var panel = drawer.querySelector('[data-fh-drawer-panel]');
                var opener = null;
                var timer = null;

                function open(key, card) {
                    var section = drawer.querySelector('[data-panel-section="' + key + '"]');
                    if (!section) return;
                    clearTimeout(timer);
                    drawer.querySelectorAll('[data-panel-section]').forEach(function (s) { s.hidden = s !== section; });
                    panel.setAttribute('aria-labelledby', 'fhPanel-' + key);
                    opener = card;
                    drawer.hidden = false;
                    document.documentElement.classList.add('fh-drawer-lock');
                    requestAnimationFrame(function () { drawer.classList.add('is-open'); panel.focus({ preventScroll: true }); });
                }

                function close() {
                    if (drawer.hidden) return;
                    drawer.classList.remove('is-open');
                    document.documentElement.classList.remove('fh-drawer-lock');
                    timer = setTimeout(function () { drawer.hidden = true; }, 220);
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
            })();
        </script>
    </div>
</x-app-layout>
