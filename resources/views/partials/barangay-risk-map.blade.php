@php
    $heading = $heading ?? 'Lian Municipality Risk Map';
    $subheading = $subheading ?? 'All 19 barangays at a glance, using the same live weather and recorded rice yield data used municipality-wide. Click any barangay for details.';
    $showRefresh = $showRefresh ?? true;
    // A canonical barangay name (normalize with \App\Support\LianBarangays::canonicalName()) to
    // highlight as the farmer's own. It never hides or filters any of the other 18 barangays.
    $highlightBarangay = $highlightBarangay ?? null;
@endphp

<section class="risk-map-card" data-risk-map-card aria-labelledby="riskMapTitle">
    <div class="risk-map-card-heading">
        <div>
            <div class="risk-map-section-label">LIAN MUNICIPALITY RISK MAP</div>
            <h2 id="riskMapTitle" class="risk-map-title">{{ $heading }}</h2>
            <p class="risk-map-subtitle">{{ $subheading }}</p>
        </div>
        @if($showRefresh)
            <button type="button" class="risk-map-refresh" data-risk-map-refresh>Refresh data</button>
        @endif
    </div>

    <div class="risk-map-shell" data-risk-map-shell>
        <div id="barangayRiskMap" class="risk-map-canvas"
            data-risk-map-url="{{ url('/api/lian/risk-map') }}"
            data-boundary-url="{{ url('/api/boundaries/lian-barangays') }}"
            @if($highlightBarangay) data-highlight-barangay="{{ $highlightBarangay }}" @endif
            role="application" aria-label="Interactive risk map of all 19 barangays in Lian, Batangas"></div>
        <div class="risk-map-loading" data-risk-map-loading aria-live="polite">Loading the 19 barangays…</div>
        <div class="risk-map-error" data-risk-map-error hidden></div>
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
