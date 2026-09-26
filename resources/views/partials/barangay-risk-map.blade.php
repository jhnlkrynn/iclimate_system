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
    $subheading = $subheading ?? 'Farm risk for all 19 Lian barangays, from live weather and recorded rice harvest. Click a barangay for details.';
    $showRefresh = $showRefresh ?? true;
    $highlightBarangay = $highlightBarangay ?? null;
@endphp
<div class="risk-map-header">
    <div>
        <h2 class="risk-map-title">{{ $heading }}</h2>
        <p class="risk-map-subtitle">{{ $subheading }}</p>
    </div>
    <div class="risk-map-header-actions">
        <label class="risk-map-search">
            <span class="visually-hidden">Find a barangay</span>
            <select class="form-select" data-risk-map-search disabled>
                <option value="">Find a barangay&hellip;</option>
            </select>
        </label>
        @if($showRefresh)
            <button type="button" class="btn btn-sm btn-outline-primary" data-risk-map-refresh>Refresh</button>
        @endif
    </div>
</div>
<div class="risk-map-shell" data-risk-map-shell>
    <div class="risk-map-stage">
        <div
            id="barangayRiskMap"
            class="risk-map-canvas"
            data-risk-map-url="{{ url('/api/lian/risk-map') }}"
            data-boundary-url="{{ url('/api/boundaries/lian-barangays') }}"
            @if($highlightBarangay) data-highlight-barangay="{{ $highlightBarangay }}" @endif
            role="img"
            aria-label="Barangay agricultural risk choropleth map of Lian, Batangas, all 19 barangays"
        ></div>
        <div class="risk-map-status is-loading" data-risk-map-status aria-live="polite">Loading risk map&hellip;</div>
        <div class="risk-map-toolbar" role="toolbar" aria-label="Map tools">
            <button type="button" class="risk-map-tool" data-risk-map-tool="weather" aria-pressed="true" title="Show or hide weather icons">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="M20 12h2"/><path d="m19.07 4.93-1.41 1.41"/><path d="M15.95 12.65a4 4 0 0 0-5.93-4.98"/><path d="M13 22H7a5 5 0 1 1 4.9-6H13a3 3 0 0 1 0 6Z"/></svg>
                <span class="visually-hidden">Weather icons</span>
            </button>
            <button type="button" class="risk-map-tool" data-risk-map-tool="satellite" aria-pressed="false" title="Switch between street map and satellite view">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12.83 2.18 8.58 3.9a1 1 0 0 1 0 1.83l-8.58 3.91a2 2 0 0 1-1.66 0L2.6 7.91a1 1 0 0 1 0-1.83l8.58-3.9a2 2 0 0 1 1.66 0Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/></svg>
                <span class="visually-hidden">Satellite view</span>
            </button>
            <button type="button" class="risk-map-tool" data-risk-map-tool="reset" title="Show all of Lian">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                <span class="visually-hidden">Reset view</span>
            </button>
            <button type="button" class="risk-map-tool" data-risk-map-tool="fullscreen" aria-pressed="false" title="Full screen">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg>
                <span class="visually-hidden">Full screen</span>
            </button>
        </div>
        <div class="risk-map-veil" data-risk-map-veil>
            <div class="risk-map-veil-card">
                <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
                <span data-risk-map-veil-text>Loading the 19 barangays&hellip;</span>
            </div>
        </div>
        <div class="risk-map-scroll-hint" data-risk-map-scroll-hint>Click the map first, then scroll to zoom</div>
    </div>
    <div class="risk-map-legend" id="riskMapLegend" role="group" aria-label="Filter barangays by risk level">
        <button type="button" class="legend-swatch" data-risk-level="Low" aria-pressed="false"><span class="legend-dot" style="background:#2e7d32"></span>Low <span class="legend-count" data-risk-count="Low">&ndash;</span></button>
        <button type="button" class="legend-swatch" data-risk-level="Moderate" aria-pressed="false"><span class="legend-dot" style="background:#f9c74f"></span>Moderate <span class="legend-count" data-risk-count="Moderate">&ndash;</span></button>
        <button type="button" class="legend-swatch" data-risk-level="High" aria-pressed="false"><span class="legend-dot" style="background:#e05a2b"></span>High <span class="legend-count" data-risk-count="High">&ndash;</span></button>
        <button type="button" class="legend-swatch" data-risk-level="Severe" aria-pressed="false"><span class="legend-dot" style="background:#8b0000"></span>Severe <span class="legend-count" data-risk-count="Severe">&ndash;</span></button>
        <button type="button" class="legend-swatch" data-risk-level="No Data" aria-pressed="false"><span class="legend-dot" style="background:repeating-linear-gradient(45deg,#9e9e9e,#9e9e9e 3px,#cfcfcf 3px,#cfcfcf 6px);background-color:#9e9e9e"></span>No Data <span class="legend-count" data-risk-count="No Data">&ndash;</span></button>
        @if($highlightBarangay)
            <span class="legend-swatch"><span class="legend-dot" style="background:#0d47a1"></span>Your Barangay (blue pin)</span>
        @endif
    </div>
    <p class="risk-map-note small text-muted mb-0 mt-2">Tap a risk level above to highlight those barangays. Ratings are a planning guide, not a certified hazard assessment. Grey means No Data, not Low risk.</p>
</div>
<div id="riskMapDetailPanel" class="risk-map-detail-panel" style="display:none;">
    <button type="button" class="risk-map-detail-close" data-risk-map-detail-close aria-label="Close barangay details">&times;</button>
    <div id="riskMapDetailContent"></div>
</div>
