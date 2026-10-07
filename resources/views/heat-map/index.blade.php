<x-app-layout>
    @vite('resources/css/pages/heat-map.css')
    {{-- Heat Map (2026-10, DashboardController::heatMap): the map opens straight away, nothing above it. Map on the left,
         one panel on the right: the municipal summary first, then the clicked barangay's risk level, plain explanation
         ("Ibig sabihin") and recommended action ("Gawin ngayon"). [This Week] / [2 Weeks] come from the saved daily
         forecast (BarangayWeekRiskService); both ranges are in the page, so resources/js/heat-map.js only shows and
         hides them and colours the map. No pop-up, no request on click. data-heat-map-updating: a barangay has no
         forecast while new weather is on its way, so heat-map.js reads this page again a few times until it has. --}}
    @php
        $t = fn (string $key, array $replace = []) => trans('farmer.heat_map.'.$key, $replace, $locale);
        $colors = \App\Services\Risk\HeatMapPanel::COLORS;
        $levels = collect($ranges)->map(fn (array $r) => collect($r['barangays'])->map(fn (array $b) => $b['level'])->all())->all();
        $labels = collect(['low', 'moderate', 'high', 'nodata'])->mapWithKeys(fn (string $l) => [$l => $t('level.'.$l)])->all();
        $hasNoData = collect($ranges)->contains(fn (array $r) => $r['counts']['nodata'] > 0);
        $mapData = ['levels' => $levels, 'colors' => $colors, 'labels' => $labels, 'yours' => $t('your_barangay')];
    @endphp
    <div class="hmx" data-heat-map data-range="{{ $range }}" lang="{{ $locale }}" @if ($updating ?? false) data-heat-map-updating @endif>
        <h1 class="visually-hidden">{{ $t('title') }}</h1>

        <div class="hmx-map">
            <div id="heatMapCanvas" class="hmx-canvas" role="application" aria-label="{{ $t('map_label') }}"
                data-boundary-url="{{ url('/api/boundaries/lian-barangays') }}"
                @if ($ownBarangay) data-own-barangay="{{ $ownBarangay }}" @endif></div>
            <script type="application/json" data-heat-map-levels>@json($mapData)</script>

            <div class="hmx-toggle" role="group" aria-label="{{ $t('range_label') }}">
                @foreach (['week' => 'this_week', '2weeks' => 'two_weeks'] as $key => $label)
                    <button type="button" class="hmx-toggle-btn @if ($range === $key) is-active @endif"
                        data-heat-map-range="{{ $key }}" aria-pressed="{{ $range === $key ? 'true' : 'false' }}">{{ $t($label) }}</button>
                @endforeach
            </div>

            <div class="hmx-legend" role="group" aria-label="{{ $t('legend') }}">
                <span class="hmx-legend-title">{{ $t('legend') }}</span>
                @foreach (['low', 'moderate', 'high'] as $level)
                    <span class="hmx-legend-item"><span class="hmx-dot" style="--hmx-color: {{ $colors[$level] }}"></span>{{ $labels[$level] }}</span>
                @endforeach
                @if ($hasNoData)
                    <span class="hmx-legend-item"><span class="hmx-dot" style="--hmx-color: {{ $colors['nodata'] }}"></span>{{ $labels['nodata'] }}</span>
                @endif
            </div>
        </div>

        <aside class="hmx-panel" aria-live="polite" data-heat-map-panel>
            @foreach ($ranges as $key => $r)
                {{-- Before a click: the municipal summary for the range. --}}
                <section class="hmx-view" data-heat-map-view="{{ $key }}:summary" @if ($key !== $range) hidden @endif>
                    <h2 class="hmx-title">{{ $r['title'] }}</h2>
                    @if ($r['state'] === 'nodata')
                        <x-no-data :label="trans('farmer.home.no_data', [], $locale)" :note="trans('farmer.home.no_data_note', [], $locale)" />
                    @else
                        <ul class="hmx-counts">
                            @foreach ($r['count_lines'] as $line)
                                <li class="is-{{ $line['level'] }}"><span class="hmx-dot" style="--hmx-color: {{ $colors[$line['level']] }}"></span>{{ $line['text'] }}</li>
                            @endforeach
                        </ul>
                        @if ($ownBarangay && isset($r['barangays'][$ownBarangay]))
                            @php $own = $r['barangays'][$ownBarangay]; @endphp
                            <button type="button" class="hmx-own" data-heat-map-pick="{{ $ownBarangay }}">
                                <span class="hmx-own-label">{{ $t('your_barangay') }}</span>
                                <strong>{{ $own['name'] }}</strong>
                                <span class="hmx-badge is-{{ $own['level'] }}">{{ $own['label'] }}</span>
                            </button>
                        @endif
                        @if ($showNumbers && $r['outlook'] !== [])
                            <h3 class="hmx-sub">{{ $t('outlook_title') }}</h3>
                            <dl class="hmx-numbers" data-heat-map-outlook>
                                @foreach ($r['outlook'] as $label => $value)
                                    <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                                @endforeach
                            </dl>
                        @endif
                        <p class="hmx-hint">{{ $t('pick') }}</p>
                        <h3 class="hmx-sub">{{ $t('all_barangays') }}</h3>
                        <ul class="hmx-list">
                            @foreach (collect($r['barangays'])->sortBy(fn (array $b) => array_search($b['level'], ['high', 'moderate', 'low', 'nodata'], true))->values() as $b)
                                <li><button type="button" class="hmx-row" data-heat-map-pick="{{ $b['barangay'] }}">
                                    <span class="hmx-dot" style="--hmx-color: {{ $colors[$b['level']] }}"></span>
                                    <span class="hmx-row-name">{{ $b['name'] }}</span>
                                    <span class="hmx-row-level">{{ $b['label'] }}</span>
                                </button></li>
                            @endforeach
                        </ul>
                        <x-updated-at :at="$r['updated_at']" :label="trans('farmer.home.updated', [], $locale)" />
                        @if ($r['delayed'])
                            <p class="hmx-source" data-heat-map-delayed>{{ $t('delayed') }}</p>
                        @endif
                        <p class="hmx-source">{{ $t('source') }}</p>
                    @endif
                </section>

                {{-- After a click: that barangay. --}}
                @foreach ($r['barangays'] as $name => $b)
                    <section class="hmx-view" data-heat-map-view="{{ $key }}:{{ $name }}" hidden>
                        <button type="button" class="hmx-back" data-heat-map-pick="">&larr; {{ $t('back') }}</button>
                        <h2 class="hmx-title">{{ $b['name'] }}</h2>
                        <p class="hmx-badge is-{{ $b['level'] }}" data-heat-map-level>{{ $b['level'] === 'nodata' ? $b['label'] : $t('risk_of', ['level' => $b['label']]) }}</p>
                        <p class="hmx-when">{{ $t($key === 'week' ? 'this_week' : 'two_weeks') }}</p>

                        <h3 class="hmx-sub">{{ $t('meaning_title') }}</h3>
                        <p class="hmx-text" data-heat-map-meaning>{{ $b['meaning'] }}</p>
                        @foreach ($b['also'] as $also)
                            <p class="hmx-text">{{ $also }}</p>
                        @endforeach

                        <h3 class="hmx-sub">{{ $t('action_title') }}</h3>
                        <p class="hmx-text hmx-action" data-heat-map-action>{{ $b['action'] }}</p>

                        @if ($showNumbers && $b['numbers'] !== [])
                            <h3 class="hmx-sub">{{ $t('numbers_title') }}</h3>
                            <dl class="hmx-numbers" data-heat-map-numbers>
                                @foreach ($b['numbers'] as $label => $value)
                                    <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                                @endforeach
                            </dl>
                        @endif
                        @if ($b['partial'])
                            <p class="hmx-source">{{ $b['partial'] }}</p>
                        @endif
                        <x-updated-at :at="$r['updated_at']" :label="trans('farmer.home.updated', [], $locale)" />
                        @if ($r['delayed'] && $b['level'] !== 'nodata')
                            <p class="hmx-source">{{ $t('delayed') }}</p>
                        @endif
                    </section>
                @endforeach
            @endforeach
        </aside>
    </div>
</x-app-layout>
