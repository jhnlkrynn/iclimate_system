{{--
    Shared Lian, Batangas barangay choropleth risk map.

    Included, unmodified, by every role's dashboard (Farmer, MAO, IT
    Expert/Admin) so the map markup exists in exactly one place. All roles
    hit the same read-only endpoints (/api/lian/risk-map,
    /api/boundaries/lian-barangays), backed by BarangayRiskMapService /
    AgriculturalRiskScorer — this partial carries no role-specific data
    fetching or scoring logic of its own.

    Optional variables:
    - $heading (string): panel heading text.
    - $subheading (string): panel sub-text.
    - $showRefresh (bool): show the manual "Refresh" button (management-style
      control). Defaults to true; pass false for view-only roles.
    - $highlightBarangay (string|null): a canonical barangay name (matching
      GeoJSON brgy_name exactly — normalize with
      \App\Support\LianBarangays::canonicalName() before passing) to
      highlight with a stronger border and a "Your Barangay" label. Does not
      hide or filter any of the other 18 barangays.
--}}
@php
    $heading = $heading ?? 'Lian Municipality Risk Map';
    $subheading = $subheading ?? 'Choropleth of all 19 Lian barangays, from live weather and recorded rice yield. Click a barangay for details.';
    $showRefresh = $showRefresh ?? true;
    $highlightBarangay = $highlightBarangay ?? null;
@endphp
<div class="risk-map-header">
    <div>
        <h2 class="risk-map-title">{{ $heading }}</h2>
        <p class="risk-map-subtitle">{{ $subheading }}</p>
    </div>
    @if($showRefresh)
        <button type="button" class="btn btn-sm btn-outline-primary" data-risk-map-refresh>Refresh</button>
    @endif
</div>
<div class="risk-map-shell">
    <div
        id="barangayRiskMap"
        class="risk-map-canvas"
        data-risk-map-url="{{ url('/api/lian/risk-map') }}"
        data-boundary-url="{{ url('/api/boundaries/lian-barangays') }}"
        @if($highlightBarangay) data-highlight-barangay="{{ $highlightBarangay }}" @endif
        role="img"
        aria-label="Barangay agricultural risk choropleth map of Lian, Batangas, all 19 barangays"
    ></div>
    <div class="risk-map-legend" id="riskMapLegend">
        <span class="legend-swatch"><span class="legend-dot" style="background:#2e7d32"></span>Low</span>
        <span class="legend-swatch"><span class="legend-dot" style="background:#f9c74f"></span>Moderate</span>
        <span class="legend-swatch"><span class="legend-dot" style="background:#e05a2b"></span>High</span>
        <span class="legend-swatch"><span class="legend-dot" style="background:#8b0000"></span>Severe</span>
        <span class="legend-swatch"><span class="legend-dot" style="background:repeating-linear-gradient(45deg,#9e9e9e,#9e9e9e 3px,#cfcfcf 3px,#cfcfcf 6px);background-color:#9e9e9e"></span>No Data</span>
        @if($highlightBarangay)
            <span class="legend-swatch"><span class="legend-dot" style="background:#0d47a1"></span>Your Barangay (bold outline)</span>
        @endif
    </div>
</div>
<div id="riskMapDetailPanel" class="risk-map-detail-panel" style="display:none;">
    <div id="riskMapDetailContent"></div>
</div>
