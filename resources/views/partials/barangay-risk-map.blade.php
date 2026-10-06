@php
    $heading = $heading ?? 'Lian Municipality Heat Map';
    $subheading = $subheading ?? 'All 19 barangays at a glance. Click any barangay for details.';
    $showRefresh = $showRefresh ?? true;
    // A canonical barangay name (normalize with \App\Support\LianBarangays::canonicalName()) to
    // highlight as the farmer's own. It never hides or filters any of the other 18 barangays.
    $highlightBarangay = $highlightBarangay ?? null;
    // Monthly forecast controls (month selector, summary cards, overview). Only the Heat Map page turns them on.
    $forecast = $forecast ?? false;
    // 'farmer' shows the short plain-language forecast panel; 'staff' (MAO/IT) keeps the detailed one.
    $forecastAudience = $forecastAudience ?? 'staff';
    // Municipal Risk Overview (donut + clickable categories) above the map: always with the forecast controls,
    // and on the MAO dashboard.
    $overview = $overview ?? $forecast;
@endphp

<section class="risk-map-card" data-risk-map-card aria-labelledby="riskMapTitle">
    @if($overview)
        {{-- Municipal Risk Overview first, the map below: the municipality-wide picture, then the places. --}}
        @include('partials.risk-overview', ['overviewAudience' => $forecastAudience])
    @endif
    @if($forecast)
        {{-- Forecast summary for the selected month; the month controls sit in the toolbar right above the map. --}}
        <div class="risk-forecast" data-risk-forecast data-forecast-url="{{ url('/api/lian/risk-forecast') }}" data-forecast-audience="{{ $forecastAudience }}">
            <div class="risk-forecast-overview" data-risk-forecast-overview hidden aria-live="polite"></div>
        </div>
    @endif

    <div class="risk-map-card-heading">
        <div>
            <div class="risk-map-section-label">LIAN MUNICIPALITY HEAT MAP</div>
            <h2 id="riskMapTitle" class="risk-map-title">{{ $heading }}</h2>
            <p class="risk-map-subtitle">{{ $subheading }}</p>
        </div>
        @if($showRefresh)
            <button type="button" class="risk-map-refresh" data-risk-map-refresh>Refresh weather &amp; risk</button>
        @endif
    </div>

    @if($forecast)
        <div class="risk-forecast-info risk-forecast-info--map">
            <details class="risk-forecast-about" data-forecast-about>
                <summary><span class="risk-forecast-info-icon" aria-hidden="true">i</span>ABOUT THIS FORECAST</summary>
                <div class="risk-forecast-about-body">
                    <p>iClimate creates one monthly weather forecast for Lian, Batangas. Barangay colors show how the weather may affect rice farming in each area.</p>
                    <dl class="risk-forecast-facts">
                        <div><dt>Forecast Source</dt><dd>iClimate</dd></div>
                        <div><dt>Coverage</dt><dd>Lian, Batangas</dd></div>
                        <div><dt>Reliability</dt><dd data-forecast-about-confidence>Shown when you pick a forecast month</dd></div>
                    </dl>
                    <p class="risk-forecast-note">For official warnings, follow PAGASA and the Municipal Agriculture Office.</p>
                </div>
            </details>
            <details class="risk-forecast-about">
                <summary><span class="risk-forecast-info-icon" aria-hidden="true">?</span>WHY ARE BARANGAY RISKS DIFFERENT?</summary>
                <div class="risk-forecast-about-body">
                    <p>All barangays use the same Lian weather forecast.</p>
                    <p>Risk may differ because farming conditions are different, such as irrigated and rainfed farms.</p>
                </div>
            </details>
        </div>
    @endif

    <div class="risk-map-stage">
        @if($forecast)
            {{-- One month selector for the page, next to the map so months can be compared without scrolling away. --}}
            <div class="risk-forecast-toolbar" data-risk-map-toolbar>
                <div class="risk-forecast-toolbar-context">
                    <span class="risk-forecast-toolbar-label">Forecast View</span>
                    <p class="risk-forecast-for" data-risk-forecast-for><span class="risk-mode-badge">Current Conditions</span> Today's risk · latest weather</p>
                </div>
                <div class="risk-forecast-toolbar-controls">
                    <div class="risk-forecast-timeline" data-risk-forecast-timeline role="group" aria-label="Forecast months" hidden></div>
                    <div class="risk-forecast-select">
                        <label for="riskForecastMonth">Forecast Month</label>
                        <select id="riskForecastMonth" class="form-select" data-risk-forecast-month disabled>
                            <option value="">Current conditions</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="risk-forecast-empty" data-risk-forecast-empty hidden>
                <strong>Forecast Not Available</strong>
                <span>No forecast is available for this month yet.</span>
            </div>
        @endif
        <div class="risk-map-shell" data-risk-map-shell>
            <div id="barangayRiskMap" class="risk-map-canvas"
                data-risk-map-url="{{ url('/api/lian/risk-map') }}"
                data-boundary-url="{{ url('/api/boundaries/lian-barangays') }}"
                @if($highlightBarangay) data-highlight-barangay="{{ $highlightBarangay }}" @endif
                {{-- Barangay AI Insight: farmers start in their saved English/Filipino choice; staff start in English. --}}
                @auth data-insight-url="{{ url('/heat-map/insight') }}" data-insight-language="{{ auth()->user()->role === \App\Models\User::ROLE_FARMER ? \App\Support\FarmerLanguage::resolve(request()) : 'en' }}" @endauth
                role="application" aria-label="Interactive heat map of all 19 barangays in Lian, Batangas"></div>
            {{-- The first ratings (and forecast) come with the page: see App\Support\RiskMapInitialData. --}}
            <script type="application/json" data-risk-map-initial>@json(\App\Support\RiskMapInitialData::payloads((bool) $forecast))</script>
            <div class="risk-map-loading" data-risk-map-loading aria-live="polite">Loading the 19 barangays…</div>
            <div class="risk-map-error" data-risk-map-error hidden></div>
            @if($forecast)
                {{-- While a forecast month loads, the map turns into a gray placeholder (no spinner). --}}
                <div class="risk-map-spinner" data-risk-map-spinner role="status" hidden>
                    <span class="risk-map-sr">Loading forecast…</span>
                </div>
            @endif
        </div>
    </div>

    <div class="risk-map-legend" id="riskMapLegend" role="group" aria-label="Risk legend">
        <span class="risk-map-legend-title">Farm risk</span>
        @foreach(['Low' => '#2e7d32', 'Moderate' => '#f2b134', 'High' => '#e05a2b', 'Severe' => '#8b0000', 'No Data' => '#8a939b'] as $level => $color)
            <span class="risk-map-legend-item"><span class="risk-map-legend-dot" style="--risk-color: {{ $color }}"></span><span>{{ $level }}</span><strong data-risk-count="{{ $level }}">–</strong></span>
        @endforeach
        @if($highlightBarangay)
            <span class="risk-map-legend-item"><span class="risk-map-legend-dot" style="--risk-color: #0d47a1"></span><span>Your Barangay (blue pin)</span></span>
        @endif
    </div>

    <div class="risk-map-details" data-risk-map-details hidden aria-live="polite"></div>
</section>
