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

        @if (\App\Support\FarmerProfileCompletion::shouldShowBanner(auth()->user()))
            @include('partials.complete-profile-banner')
        @endif
        @php
            $activeDashFarm = \App\Support\ActiveFarm::available() ? \App\Support\ActiveFarm::current(auth()->user()) : null;
        @endphp
        @if ($activeDashFarm && $activeDashFarm->isPendingVerification())
            <div class="alert alert-warning mb-3" role="status" data-farm-pending-notice>
                {{ app()->getLocale() === 'fil' ? 'Naghihintay ng pagsusuri ng MAO ang iyong bukid.' : 'Your farm is waiting for MAO review.' }}
            </div>
        @elseif ($activeDashFarm && $activeDashFarm->isRejected())
            <div class="alert alert-danger mb-3" role="status" data-farm-rejected-notice>
                {{ app()->getLocale() === 'fil' ? 'Hindi naaprubahan ang iyong bukid.' : 'Your farm was not approved.' }}
                @if ($activeDashFarm->rejection_reason)
                    <strong>{{ $activeDashFarm->rejection_reason }}</strong>
                @endif
                <a class="ms-2 underline" href="{{ route('farmer.farms.edit', $activeDashFarm) }}">{{ app()->getLocale() === 'fil' ? 'Ayusin at ipasa muli' : 'Fix and resubmit' }}</a>
            </div>
        @endif

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
                    $detail = $tile['detail'];
                    $detail['icon'] = $detail['icon'] ?? ($tile['icon'] ?? null);
                    $panels[$panelFor[$tile['key']] ?? $tile['key']] = $detail;
                }
            }
            // Weather today: current conditions, today's all-day forecast, wind, and when it was read.
            $w = $f['weather'] ?? ['state' => 'nodata'];
            if ($w['state'] === 'ok') {
                $todayForecast = collect($f['week']['days'] ?? [])->first(fn (array $d): bool => $d['is_today'] ?? false);
                $panels['weather'] = [
                    'icon' => 'weather',
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
                ];
            }
            // Barangay risk: the Barangay Risk card's rating (the risk tile, when shown, has the same one).
            if (($f['risk']['state'] ?? null) === 'ok' && ! isset($panels['risk'])) {
                $panels['risk'] = \App\Services\Farmer\FarmerHomeKpis::riskDetail($f['risk'], (string) ($f['barangay'] ?? ''));
                $panels['risk']['icon'] = $panels['risk']['icon'] ?? 'alert';
            }
            // Expected harvest: the tile's tons when the farm size is known; otherwise the yield card's t/ha alone.
            $h = $f['harvest'] ?? ['state' => 'nodata'];
            if ($h['state'] === 'ok' && ! isset($panels['expected-harvest'])) {
                $panels['expected-harvest'] = [
                    'icon' => 'yield',
                    'title' => __('farmer.home.harvest.title'),
                    'rows' => array_filter([
                        __('farmer.kpis.detail.yield_per_ha') => number_format($h['yield'], 2).' t/ha',
                        __('farmer.kpis.detail.season') => (string) ($h['season'] ?? ''),
                        __('farmer.kpis.detail.compared') => (string) ($h['compare_label'] ?? ''),
                        __('farmer.kpis.detail.size') => (string) ($h['size_note'] ?? ''),
                    ], fn (string $v): bool => $v !== ''),
                    'text' => __('farmer.kpis.detail.harvest_how'),
                    'updated_at' => $h['ran_at'] ?? null,
                ];
            }
        @endphp
        @php
            $fm = $f['message'] ?? [];
            $weatherAlert = in_array($fm['tone'] ?? null, ['watch', 'danger'], true) ? [
                'id' => (string) ($fm['key'] ?? 'alert'),
                'date' => \Carbon\CarbonImmutable::now(\App\Services\Analytics\FarmerDashboardService::TIMEZONE)->toDateString(),
                'tone' => (string) $fm['tone'],
                'icon' => (string) ($fm['icon'] ?? 'alert'),
                'title' => (string) ($fm['headline'] ?? __('farmer.home.message.headline_bad')),
                'message' => (string) ($fm['text'] ?? ''),
            ] : null;
        @endphp
        <x-weather-alert-modal :alert="$weatherAlert" />
        <div class="fh-drawer" id="fhDrawer" data-fh-drawer hidden>
            <div class="fh-drawer-backdrop" data-fh-drawer-close></div>
            <div class="fh-drawer-panel" role="dialog" aria-modal="true" tabindex="-1" data-fh-drawer-panel>
                <div class="fh-drawer-handle" aria-hidden="true"></div>
                <button type="button" class="fh-drawer-x" data-fh-drawer-close aria-label="{{ __('farmer.kpis.detail.close') }}">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
                @php
                    $weekDays = array_values($f['week']['days'] ?? []);
                    $dayWeatherIcons = [];
                    foreach ($weekDays as $idx => $day) {
                        $icon = $day['icon'] ?? null;
                        if ($icon === 'rain' && ($day['rain_key'] ?? null) === 'none') {
                            $icon = 'sun';
                        }
                        $weekDays[$idx]['_viz_icon'] = $icon;
                        if (empty($icon)) {
                            continue;
                        }
                        if (! empty($day['label'])) {
                            $dayWeatherIcons[(string) $day['label']] = (string) $icon;
                        }
                        if (! empty($day['date']) && $day['date'] instanceof \Carbon\CarbonInterface) {
                            $dayWeatherIcons[$day['date']->locale(app()->getLocale())->translatedFormat('D j')] = (string) $icon;
                        }
                    }
                @endphp
                @foreach ($panels as $key => $d)
                    @php
                        $rowNums = [];
                        foreach ($d['rows'] ?? [] as $rLabel => $rValue) {
                            if (preg_match('/^(-?\d+(?:\.\d+)?)\s*(°C|mm)$/i', trim((string) $rValue), $rm)) {
                                $rowNums[$rLabel] = (float) $rm[1];
                            }
                        }
                        $rowMax = $rowNums !== [] ? max(array_values($rowNums)) : 0.0;
                    @endphp
                    <div class="fh-drawer-body" data-panel-section="{{ $key }}" hidden>
                        <h2 class="fh-drawer-title" id="fhPanel-{{ $key }}">
                            @if (! empty($d['icon']))
                                <span class="fh-drawer-icon" aria-hidden="true">@include('dashboards.mao.icon', ['name' => $d['icon']])</span>
                            @endif
                            <span @if ($key === 'rain') data-rain-modal-title @endif>{{ $d['title'] }}</span>
                        </h2>
                        @if ($key === 'rain' && ! empty($d['ranges']))
                            @php
                                $rainModalTwoWeeks = $d['ranges']['2weeks'] ?? [];
                                $hasTwoWeeksModal = (bool) ($rainModalTwoWeeks['available'] ?? false);
                            @endphp
                            <div class="fh-rain-modal-controls">
                                <span class="fh-rain-range" role="group" aria-label="{{ $d['title'] }}" data-rain-toggle="modal">
                                    <button type="button" class="fh-rain-range-btn is-active" data-rain-range-btn="week" aria-pressed="true">{{ __('farmer.kpis.rain.toggle_7') }}</button>
                                    <button type="button" @class(['fh-rain-range-btn', 'is-disabled' => ! $hasTwoWeeksModal]) data-rain-range-btn="2weeks" aria-pressed="false" @unless ($hasTwoWeeksModal) disabled aria-disabled="true" data-disabled="true" @endunless>{{ __('farmer.kpis.rain.toggle_14') }}</button>
                                </span>
                                @unless ($hasTwoWeeksModal)
                                    <span class="fh-rain-unavail">{{ __('farmer.kpis.rain.not_available') }}</span>
                                @endunless
                            </div>
                            <p class="fh-rain-prediction-note" data-rain-prediction-note>
                                <span aria-hidden="true">@include('dashboards.mao.icon', ['name' => 'info'])</span>
                                <span>{{ __('farmer.kpis.rain.prediction_only') }}</span>
                            </p>
                        @endif
                        @if (! empty($d['badge']['label']))
                            <p class="fh-risk-badge is-{{ $d['badge']['tone'] }}">{{ $d['badge']['label'] }}</p>
                        @endif
                        @if ($key === 'rain' && ! empty($d['ranges']))
                            @foreach (['week', '2weeks'] as $rangeKey)
                                @php $rangeData = $d['ranges'][$rangeKey] ?? null; @endphp
                                @if (! empty($rangeData['available']))
                                    <div data-rain-panel="{{ $rangeKey }}" @if ($rangeKey === '2weeks') hidden @endif>
                                        @if ($rangeKey === '2weeks')
                                            @php
                                                $thisWeekItems = array_values(array_filter($rangeData['items'] ?? [], fn (array $item): bool => ($item['group'] ?? '') === 'this_week'));
                                                $nextWeekItems = array_values(array_filter($rangeData['items'] ?? [], fn (array $item): bool => ($item['group'] ?? '') === 'next_week'));
                                            @endphp
                                            <div class="fh-rain-group-title">{{ __('farmer.kpis.rain.this_week') }}</div>
                                            <dl class="fh-drawer-facts">
                                                @foreach ($thisWeekItems as $item)
                                                    <div>
                                                        <dt>{{ $item['label'] }}</dt>
                                                        <dd>
                                                            <span class="fh-drawer-viz" aria-hidden="true"><span class="fh-drawer-viz-icon">@include('dashboards.mao.icon', ['name' => $item['icon']])</span></span>
                                                            <span>{{ $item['value'] }}</span>
                                                        </dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                            <div class="fh-rain-group-title">{{ __('farmer.kpis.rain.next_week') }} <span class="fh-rain-certain-tag">{{ __('farmer.kpis.rain.less_certain') }}</span></div>
                                            <dl class="fh-drawer-facts">
                                                @foreach ($nextWeekItems as $item)
                                                    <div class="is-less-certain">
                                                        <dt>{{ $item['label'] }}</dt>
                                                        <dd>
                                                            <span class="fh-drawer-viz" aria-hidden="true"><span class="fh-drawer-viz-icon">@include('dashboards.mao.icon', ['name' => $item['icon']])</span></span>
                                                            <span>{{ $item['value'] }}</span>
                                                        </dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        @else
                                            <dl class="fh-drawer-facts">
                                                @foreach ($rangeData['items'] ?? [] as $item)
                                                    <div>
                                                        <dt>{{ $item['label'] }}</dt>
                                                        <dd>
                                                            <span class="fh-drawer-viz" aria-hidden="true"><span class="fh-drawer-viz-icon">@include('dashboards.mao.icon', ['name' => $item['icon']])</span></span>
                                                            <span>{{ $item['value'] }}</span>
                                                        </dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        @endif
                                        @if (! empty($rangeData['note']))
                                            <p class="fh-words mt-2">{{ $rangeData['note'] }}</p>
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        @else
                            @if (! empty($d['rows']))
                                <dl class="fh-drawer-facts">
                                    @foreach ($d['rows'] as $label => $value)
                                        @php
                                            $pct = isset($rowNums[$label]) && $rowMax > 0
                                                ? max(12, min(100, (int) round(($rowNums[$label] / $rowMax) * 100)))
                                                : null;
                                            $rowIcon = $dayWeatherIcons[$label]
                                                ?? (in_array($key, ['hot', 'rain'], true) ? ($weekDays[$loop->index]['_viz_icon'] ?? null) : null)
                                                ?? ($d['icon'] ?? null);
                                        @endphp
                                        <div>
                                            <dt>{{ $label }}</dt>
                                            <dd>
                                                @if (! empty($rowIcon) && ($pct !== null || isset($dayWeatherIcons[$label]) || in_array($key, ['hot', 'rain'], true)))
                                                    <span class="fh-drawer-viz" aria-hidden="true">
                                                        <span class="fh-drawer-viz-icon">@include('dashboards.mao.icon', ['name' => $rowIcon])</span>
                                                        @if ($pct !== null)
                                                            <span class="fh-drawer-viz-track"><span style="width: {{ $pct }}%"></span></span>
                                                        @endif
                                                    </span>
                                                @endif
                                                <span>{{ $value }}</span>
                                            </dd>
                                        </div>
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

                var storageKey = 'iclimate:rain-range:' + @json(auth()->id() ?? 'guest');

                function readStoredRainRange() {
                    try {
                        return localStorage.getItem(storageKey) === '2weeks' ? '2weeks' : 'week';
                    } catch (e) {
                        return 'week';
                    }
                }

                function saveRainRange(range) {
                    try {
                        localStorage.setItem(storageKey, range);
                    } catch (e) {}
                }

                function setRainRange(range, persist) {
                    var rainCard = document.querySelector('[data-rain-ranges]');
                    if (!rainCard || !rainCard.dataset.rainRanges) return;
                    var ranges;
                    try {
                        ranges = JSON.parse(rainCard.dataset.rainRanges);
                    } catch (e) {
                        return;
                    }
                    var chosen = (range === '2weeks' && ranges['2weeks'] && ranges['2weeks'].available) ? '2weeks' : 'week';
                    var data = ranges[chosen];
                    if (!data) return;

                    var labelEl = rainCard.querySelector('[data-rain-card-label]');
                    var valueEl = rainCard.querySelector('[data-rain-card-value]');
                    var unitEl = rainCard.querySelector('[data-rain-card-unit]');
                    var noteEl = rainCard.querySelector('[data-rain-card-note]');
                    if (labelEl) labelEl.textContent = data.label;
                    if (valueEl) valueEl.textContent = String(data.count);
                    if (unitEl) unitEl.textContent = data.of;
                    if (noteEl) noteEl.textContent = data.note;

                    var modalSection = drawer.querySelector('[data-panel-section="rain"]');
                    if (modalSection) {
                        var modalTitle = modalSection.querySelector('[data-rain-modal-title]');
                        if (modalTitle) modalTitle.textContent = data.label;
                        modalSection.querySelectorAll('[data-rain-panel]').forEach(function (el) {
                            el.hidden = el.dataset.rainPanel !== chosen;
                        });
                    }

                    document.querySelectorAll('[data-rain-range-btn]').forEach(function (btn) {
                        var active = btn.dataset.rainRangeBtn === chosen;
                        btn.classList.toggle('is-active', active);
                        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
                    });

                    if (persist) saveRainRange(chosen);
                }

                setRainRange(readStoredRainRange(), false);

                document.addEventListener('click', function (e) {
                    var rainBtn = e.target.closest('[data-rain-range-btn]');
                    if (rainBtn) {
                        e.preventDefault();
                        e.stopPropagation();
                        if (rainBtn.dataset.disabled === 'true' || rainBtn.disabled) return;
                        setRainRange(rainBtn.dataset.rainRangeBtn, true);
                        return;
                    }
                    var card = e.target.closest('[data-panel]');
                    if (card && !drawer.contains(card)) { e.preventDefault(); open(card.dataset.panel, card); return; }
                    if (e.target.closest('[data-fh-drawer-close]')) close();
                });

                document.addEventListener('keydown', function (e) {
                    var rainBtn = e.target.closest && e.target.closest('[data-rain-range-btn]');
                    if (rainBtn && (e.key === 'Enter' || e.key === ' ')) {
                        e.preventDefault();
                        e.stopPropagation();
                        if (rainBtn.dataset.disabled === 'true' || rainBtn.disabled) return;
                        setRainRange(rainBtn.dataset.rainRangeBtn, true);
                        return;
                    }
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
