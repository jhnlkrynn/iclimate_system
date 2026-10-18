<x-app-layout>
    @vite('resources/css/leaflet.css')
    @vite('resources/css/pages/heat-map.css')
    {{-- Heat Map (2026-10, DashboardController::heatMap): staff see the map beside the summary panel. Farmers see
         their live farmer/risk card in a compact top summary row, then the selected-period weekly summary/list beside
         the map; on smaller screens the cards stack above the map. The panel contains the clicked barangay's risk
         level, plain explanation ("Ibig sabihin") and recommended action ("Gawin ngayon"). [This Week] / [2 Weeks]
         come from the saved daily forecast (BarangayWeekRiskService); both ranges are in the page, so
         resources/js/heat-map.js only shows and hides them and colours the map. No pop-up, no request on click.
         data-heat-map-updating: a barangay has no forecast while new weather is on its way, so heat-map.js reads this
         page again a few times until it has. --}}
    @php
        $t = fn (string $key, array $replace = []) => trans('farmer.heat_map.'.$key, $replace, $locale);
        $colors = \App\Services\Risk\HeatMapPanel::colors();   // panel revision 2.1: config/weather_risk.php
        $levels = collect($ranges)->map(fn (array $r) => collect($r['barangays'])->map(fn (array $b) => $b['level'])->all())->all();
        $riskLevels = ['low', 'moderate', 'high', 'severe'];   // panel revision 2.1
        $labels = collect([...$riskLevels, 'nodata'])->mapWithKeys(fn (string $l) => [$l => $t('level.'.$l)])->all();
        $hasNoData = collect($ranges)->except($layerKeys ?? [])->contains(fn (array $r) => $r['counts']['nodata'] > 0);   // Manuscript – Naicel: weekly ranges only
        $mapData = ['levels' => $levels, 'colors' => $colors + ['flood_prone' => '#C62828', 'unavailable' => '#9CA3AF'], 'labels' => $labels, 'yours' => $t('your_barangay')];
        $mapData['versions'] = collect($ranges)->mapWithKeys(fn (array $r, string $key) => [$key => $r['updated_at'] ?? null])->all();
        // Panel revision 2.1: what the map tooltip / tap shows besides the name and level.
        $mapData['details'] = collect($ranges)->map(fn (array $r) => collect($r['barangays'])->map(fn (array $b) => ['factor' => $b['factor'] ?? null, 'impact' => $b['impact'] ?? null])->all())->all();
        $mapData['detailLabels'] = ['factor' => $t('factor_title'), 'impact' => $t('impact_title')];
        // Manuscript – Naicel (2026-10-08): the layer ranges (flood / drought / yield) carry their own level names.
        $layerKeys = $layerKeys ?? [];
        $mapData['layers'] = $layerKeys;
        $mapData['labelsByRange'] = collect($ranges)->only($layerKeys)->map(fn (array $r) => $r['labels'] ?? [])->all();
    @endphp
    <div @class(['hmx', 'is-farmer' => ! $showNumbers]) data-heat-map data-range="{{ $range }}" lang="{{ $locale }}" @if ($updating ?? false) data-heat-map-updating @endif>
        <h1 class="visually-hidden">{{ $t('title') }}</h1>

        {{-- Farmers (2026-10): their own barangay first, in colour and plain words with what to do; the map comes second.
             One card per weekly range; heat-map.js shows the one for [This Week] or [2 Weeks]. --}}
        @if ($ownBarangay && ! $showNumbers)
            <div class="hmx-mine-wrap" data-heat-map-mine>
                @foreach (['week', '2weeks'] as $key)
                    @php $own = $ranges[$key]['barangays'][$ownBarangay] ?? null; @endphp
                    @continue(! $own)
                    <section class="hmx-mine is-{{ $own['level'] }}" data-heat-map-mine-range="{{ $key }}" aria-labelledby="hmxMine-{{ $key }}" @if ($key !== ($range === '2weeks' ? '2weeks' : 'week')) hidden @endif>
                        <span class="hmx-mine-icon" aria-hidden="true">@include('dashboards.mao.icon', ['name' => ['low' => 'check', 'moderate' => 'alert', 'high' => 'danger', 'severe' => 'danger'][$own['level']] ?? 'info'])</span>
                        <div class="hmx-mine-text">
                            <p class="hmx-mine-label">{{ $t('your_barangay') }} · {{ $t('mine_when.'.$key) }}</p>
                            <h2 id="hmxMine-{{ $key }}">{{ $own['name'] }} — {{ $own['level'] === 'nodata' ? $own['label'] : $t('risk_of', ['level' => $own['label']]) }}</h2>
                            <p class="hmx-mine-meaning">{{ $own['meaning'] }}</p>
                            <p class="hmx-mine-action"><strong>{{ $t('action_title') }}:</strong> {{ $own['action'] }}</p>
                            @if (! empty($own['factor']))<p class="hmx-mine-factor"><strong>{{ $t('factor_title') }}:</strong> {{ $own['factor'] }}</p>@endif
                            <p class="hmx-mine-key"><span>{{ $t('mine_key') }}:</span>
                                @foreach ($riskLevels as $level)
                                    <span class="hmx-legend-item"><span class="hmx-dot" style="--hmx-color: {{ $colors[$level] }}"></span>{{ $labels[$level] }}</span>
                                @endforeach
                            </p>
                        </div>
                    </section>
                @endforeach
            </div>
        @endif

        <div class="hmx-map">
            <div id="heatMapCanvas" class="hmx-canvas" role="application" aria-label="{{ $t('map_label') }}"
                data-boundary-url="{{ url('/api/boundaries/lian-barangays') }}"
                data-layer-api-url="{{ url('/api/heat-map') }}"
                data-detail-url-template="{{ route('heat-map.detail', ['range' => '__RANGE__', 'barangay' => '__BARANGAY__']) }}"
                @if ($ownBarangay) data-own-barangay="{{ $ownBarangay }}" @endif></div>
            <script type="application/json" data-heat-map-levels>@json($mapData)</script>
            <p class="hmx-api-status" data-heat-map-api-status role="status" hidden></p>

            <div class="hmx-toggle" role="group" aria-label="{{ $t('range_label') }}">
                @foreach (['week' => 'this_week', '2weeks' => 'two_weeks'] as $key => $label)
                    <button type="button" class="hmx-toggle-btn @if ($range === $key) is-active @endif"
                        data-heat-map-range="{{ $key }}" aria-pressed="{{ $range === $key ? 'true' : 'false' }}">{{ $t($label) }}</button>
                @endforeach
            </div>

            {{-- Manuscript – Naicel (2026-10-08): map layer choice; a layer shows instead of the weekly risk. --}}
            @if ($layerKeys !== [])
                <label class="hmx-layer">
                    <span>{{ $t('layers.label') }}</span>
                    <select data-heat-map-layer>
                        <option value="" @selected(! in_array($range, $layerKeys, true))>{{ $t('legend') }}</option>
                        @foreach ($layerKeys as $layerKey)<option value="{{ $layerKey }}" @selected($range === $layerKey)>{{ $t('layers.'.$layerKey) }}</option>@endforeach
                    </select>
                </label>
            @endif
            {{-- End Manuscript – Naicel --}}
            <div class="hmx-legend" role="group" aria-label="{{ $t('legend') }}" data-heat-map-legend="risk" @if (in_array($range, $layerKeys, true)) hidden @endif>
                <span class="hmx-legend-title">{{ $t('legend') }}</span>
                @foreach ($riskLevels as $level)
                    <span class="hmx-legend-item"><span class="hmx-dot" style="--hmx-color: {{ $colors[$level] }}"></span>{{ $labels[$level] }}</span>
                @endforeach
                @if ($hasNoData)
                    <span class="hmx-legend-item"><span class="hmx-dot" style="--hmx-color: {{ $colors['nodata'] }}"></span>{{ $labels['nodata'] }}</span>
                @endif
            </div>
            {{-- Manuscript – Naicel (2026-10-08): one legend per map layer, shown with its layer. --}}
            @foreach ($layerKeys as $layerKey)
                <div class="hmx-legend" role="group" aria-label="{{ $t('layers.'.$layerKey) }}" data-heat-map-legend="{{ $layerKey }}" @if ($range !== $layerKey) hidden @endif>
                    <span class="hmx-legend-title">{{ $t('layers.'.$layerKey) }}</span>
                    @foreach ($ranges[$layerKey]['labels'] as $level => $levelLabel)
                        <span class="hmx-legend-item"><span class="hmx-dot" style="--hmx-color: {{ $colors[$level] }}"></span>{{ $levelLabel }}</span>
                    @endforeach
                </div>
            @endforeach
        </div>

        <aside class="hmx-panel" aria-live="polite" data-heat-map-panel>
            @foreach ($ranges as $key => $r)
                {{-- Before a click: the municipal summary for the range. --}}
                <section class="hmx-view" data-heat-map-view="{{ $key }}:summary" @if ($key !== $range) hidden @endif>
                    <h2 class="hmx-title">{{ $r['title'] }}</h2>
                    @if ($r['state'] === 'nodata')
                        <x-no-data :label="trans('farmer.home.no_data', [], $locale)" :note="trans('farmer.home.no_data_note', [], $locale)" />
                    @else
                        <div class="hmx-summary">
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
                        </div>
                        <ul class="hmx-list">
                            @foreach (collect($r['barangays'])->sortBy(fn (array $b) => array_search($b['level'], ['severe', 'high', 'moderate', 'low', 'nodata'], true))->values() as $b)
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
                        <p class="hmx-source">{{ $r['source'] ?? $t('source') }}</p>{{-- Manuscript – Naicel: a layer names its own source --}}
                        @if ($key === 'flood' && isset($r['api_summary']['standing_crop_hectares']))<p class="hmx-source">Standing crops in flood-prone barangays: {{ number_format((float) $r['api_summary']['standing_crop_hectares'], 2) }} ha</p>@endif
                        @if ($key === 'flood')<p class="hmx-source">How this is rated: {{ config('weather_risk.flood_layer.note') }}</p>@endif
                    @endif
                </section>

            @endforeach
            <div data-heat-map-detail-container hidden></div>
        </aside>
    </div>
</x-app-layout>
