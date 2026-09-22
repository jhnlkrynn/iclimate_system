// Shared Lian, Batangas barangay choropleth risk map.
// Used by every dashboard role (Farmer, MAO, IT Expert/Admin) that renders
// an element with id="barangayRiskMap" — all three reuse this exact module
// so risk-map rendering logic exists in exactly one place, backed by the
// same /api/lian/risk-map + /api/boundaries/lian-barangays endpoints
// (BarangayRiskMapService / AgriculturalRiskScorer server-side).

const RISK_LEVEL_COLORS = {
    'Low': '#2e7d32',
    'Moderate': '#f9c74f',
    'High': '#e05a2b',
    'Severe': '#8b0000',
    'No Data': '#9e9e9e',
};

function riskFillStyle(level, isHighlighted = false) {
    const color = RISK_LEVEL_COLORS[level] || RISK_LEVEL_COLORS['No Data'];
    return {
        fillColor: color,
        color: isHighlighted ? '#0d47a1' : (level === 'No Data' ? '#616161' : '#2b2b2b'),
        weight: isHighlighted ? 4 : (level === 'No Data' ? 1 : 1.2),
        dashArray: level === 'No Data' ? '3,3' : null,
        fillOpacity: level === 'No Data' ? 0.35 : 0.65,
    };
}

function renderRiskDetailPanel(entry, meta) {
    const panel = document.getElementById('riskMapDetailPanel');
    const content = document.getElementById('riskMapDetailContent');
    if (!panel || !content) return;

    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    const rows = [];
    rows.push(`<h3 class="risk-map-detail-title">${esc(entry.barangay)}${entry.is_farmer_barangay ? ' <span class="your-barangay-pill">Your Barangay</span>' : ''}</h3>`);

    if (entry.risk_level === 'No Data') {
        rows.push('<p class="risk-map-row-text"><strong>Risk level:</strong> No Data</p>');
        rows.push(`<p class="risk-map-row-meta">${esc(entry.no_data_reason || 'This barangay currently has no computed risk score. Live weather or the monthly rainfall model may be temporarily unavailable.')}</p>`);
    } else {
        rows.push(`<p class="risk-map-row-text"><strong>Risk level:</strong> ${esc(entry.risk_level)}${entry.risk_score !== null && entry.risk_score !== undefined ? ' (score ' + esc(entry.risk_score) + ')' : ''}</p>`);
    }
    if (entry.updated_at) rows.push(`<p class="risk-map-row-meta">Computed: ${new Date(entry.updated_at).toLocaleString()}</p>`);
    if (meta && meta.source_note) rows.push(`<p class="risk-map-row-meta">Data source: ${esc(meta.source_note)}${meta.generated_at ? ' &middot; as of ' + new Date(meta.generated_at).toLocaleString() : ''}</p>`);

    const w = entry.weather || {};
    if (w.status === 'fresh') {
        rows.push('<p class="risk-map-row-meta">Current weather conditions (context only — humidity/wind are not scored risk factors):</p>');
        if (w.rainfall_24h_mm !== null && w.rainfall_24h_mm !== undefined) rows.push(`<p class="risk-map-row-text">Rainfall (24h, forecast — not the monthly figure used for scoring): ${esc(w.rainfall_24h_mm)} mm</p>`);
        if (w.temperature_c !== null && w.temperature_c !== undefined) rows.push(`<p class="risk-map-row-text">Temperature: ${esc(w.temperature_c)} &deg;C</p>`);
        if (w.humidity_percent !== null && w.humidity_percent !== undefined) rows.push(`<p class="risk-map-row-text">Humidity: ${esc(w.humidity_percent)}%</p>`);
        if (w.wind_speed_kmh !== null && w.wind_speed_kmh !== undefined) rows.push(`<p class="risk-map-row-text">Wind speed: ${esc(w.wind_speed_kmh)} km/h</p>`);
    } else {
        rows.push(`<p class="risk-map-row-text">${esc(w.message || 'No live weather data available for this barangay.')}</p>`);
    }

    if (entry.monthly_rainfall && entry.monthly_rainfall.available !== false && entry.monthly_rainfall.value_mm !== null && entry.monthly_rainfall.value_mm !== undefined) {
        rows.push(`<p class="risk-map-row-text">Monthly rainfall (used for scoring): ${esc(entry.monthly_rainfall.value_mm)} ${esc(entry.monthly_rainfall.unit || 'mm')} (${esc(entry.monthly_rainfall.scope || 'municipal')})</p>`);
    }

    if (entry.last_recorded_yield) {
        rows.push(`<p class="risk-map-row-text">Last recorded yield: ${esc(entry.last_recorded_yield.value)} t/ha (${esc(entry.last_recorded_yield.season || '')} ${esc(entry.last_recorded_yield.year || '')})</p>`);
    }
    if (entry.farm_type_context) {
        rows.push(`<p class="risk-map-row-meta">${esc(entry.farm_type_context)}</p>`);
    }

    if (entry.advisory) {
        if (entry.advisory.planting) rows.push(`<p class="risk-map-row-text"><strong>Planting advisory:</strong> ${esc(entry.advisory.planting)}</p>`);
        if (entry.advisory.irrigation) rows.push(`<p class="risk-map-row-text"><strong>Irrigation:</strong> ${esc(entry.advisory.irrigation)}</p>`);
        if (Array.isArray(entry.advisory.warnings) && entry.advisory.warnings.length) {
            rows.push('<p class="risk-map-row-text"><strong>Warnings:</strong></p><ul class="risk-map-row-text">' + entry.advisory.warnings.map((warn) => `<li>${esc(warn)}</li>`).join('') + '</ul>');
        }
        if (entry.advisory.overall_recommendation) rows.push(`<p class="risk-map-row-text"><strong>Recommendation:</strong> ${esc(entry.advisory.overall_recommendation)}</p>`);
    }

    content.innerHTML = rows.join('');
    panel.style.display = '';
}

export function initBarangayRiskMap() {
    const mapEl = document.getElementById('barangayRiskMap');
    if (!mapEl || !window.L) return;

    const riskUrl = mapEl.dataset.riskMapUrl;
    const boundaryUrl = mapEl.dataset.boundaryUrl;
    // Canonicalized (server-side, via LianBarangays::canonicalName()) name of
    // the viewing farmer's own barangay, if any — used only to visually
    // highlight one polygon; every role still sees and can click all 19.
    const highlightBarangay = mapEl.dataset.highlightBarangay || null;

    // Fallback center/zoom, used only until the real municipal boundary loads
    // (Leaflet requires some center/zoom to construct the map), and as a last
    // resort if the boundary fetch fails entirely.
    const FALLBACK_CENTER = [14.02, 120.66];
    const FALLBACK_ZOOM = 12;
    const map = window.L.map(mapEl, { scrollWheelZoom: false }).setView(FALLBACK_CENTER, FALLBACK_ZOOM);
    window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 18,
    }).addTo(map);

    let geoLayer = null;
    let municipalBounds = null;

    const fitToMunicipalBounds = () => {
        if (municipalBounds && municipalBounds.isValid()) {
            try { map.fitBounds(municipalBounds, { padding: [12, 12] }); } catch (e) { /* ignore */ }
            return true;
        }
        return false;
    };

    // Load the real municipal polygon so the map's initial view is derived
    // from the actual Lian, Batangas boundary instead of a hardcoded
    // center/zoom. Falls back to FALLBACK_CENTER/FALLBACK_ZOOM if this fails.
    const loadMunicipalBoundary = async () => {
        try {
            const res = await fetch('/geojson/lian-boundary.geojson');
            if (!res.ok) throw new Error('municipal boundary fetch failed: ' + res.status);
            const municipalGeoJSON = await res.json();
            const outlineLayer = window.L.geoJSON(municipalGeoJSON, {
                style: { color: '#1b5e20', weight: 2, fill: false },
            }).addTo(map);
            municipalBounds = outlineLayer.getBounds();
            fitToMunicipalBounds();
        } catch (error) {
            console.error('iClimate could not load the municipal boundary; using fallback view.', error);
            map.setView(FALLBACK_CENTER, FALLBACK_ZOOM);
        }
    };

    let riskMeta = {};

    // Single fetch of the boundary + risk endpoints per load. `forceRefresh`
    // appends ?refresh=1 to the *same* request instead of issuing a separate
    // priming fetch first, so a manual refresh click never doubles the call
    // to /api/lian/risk-map. This is the one and only place any role's
    // dashboard requests risk-map data — Farmer, MAO, and IT Expert/Admin
    // all call this same function against the same endpoint.
    const load = async (forceRefresh = false) => {
        try {
            const effectiveRiskUrl = forceRefresh
                ? riskUrl + (riskUrl.includes('?') ? '&' : '?') + 'refresh=1'
                : riskUrl;
            const [boundaryRes, riskRes] = await Promise.all([
                fetch(boundaryUrl),
                fetch(effectiveRiskUrl),
            ]);
            const boundary = await boundaryRes.json();
            const risk = await riskRes.json();

            if (!boundary || !Array.isArray(boundary.features) || risk.status === 'unavailable') {
                mapEl.insertAdjacentHTML('afterend', '<p class="risk-map-row-text text-muted">Risk map is temporarily unavailable.</p>');
                return;
            }

            riskMeta = { source_note: risk.source_note, generated_at: risk.generated_at };

            const byName = {};
            (risk.barangays || []).forEach((entry) => { byName[entry.barangay] = entry; });

            if (geoLayer) geoLayer.remove();
            geoLayer = window.L.geoJSON(boundary, {
                style: (feature) => {
                    const name = feature.properties && feature.properties.brgy_name;
                    const entry = byName[name];
                    return riskFillStyle(entry ? entry.risk_level : 'No Data', highlightBarangay !== null && name === highlightBarangay);
                },
                onEachFeature: (feature, layer) => {
                    const name = feature.properties && feature.properties.brgy_name;
                    const entry = byName[name];
                    const level = entry ? entry.risk_level : 'No Data';
                    const scoreLabel = entry && entry.risk_score !== null && entry.risk_score !== undefined ? ` (score ${entry.risk_score})` : '';
                    const isHighlighted = highlightBarangay !== null && name === highlightBarangay;
                    layer.bindTooltip(`${name}${isHighlighted ? ' (Your Barangay)' : ''} — Risk: ${level}${scoreLabel}`, {
                        sticky: true,
                        className: isHighlighted ? 'your-barangay-tooltip' : undefined,
                    });
                    layer.on('click', () => {
                        // Even a barangay with no matching entry (name mismatch or
                        // no computed score) gets a clear "No Data" panel instead
                        // of the click silently doing nothing.
                        renderRiskDetailPanel(Object.assign({}, entry || {
                            barangay: name || 'Unknown barangay',
                            risk_level: 'No Data',
                            risk_score: null,
                            updated_at: null,
                            weather: { status: 'unavailable', message: 'No live weather data available for this barangay.' },
                            last_recorded_yield: null,
                            advisory: null,
                            no_data_reason: 'This barangay has no matching risk entry in the current dataset.',
                        }, { is_farmer_barangay: isHighlighted }), riskMeta);
                    });
                },
            }).addTo(map);

            // Prefer the municipal polygon's bounds (the real boundary); only fall
            // back to the barangay layer's combined bounds if that failed to load.
            if (!fitToMunicipalBounds()) {
                try { map.fitBounds(geoLayer.getBounds(), { padding: [12, 12] }); } catch (e) { /* ignore */ }
            }
        } catch (error) {
            console.error('iClimate could not load the barangay risk map.', error);
        }
    };

    loadMunicipalBoundary();
    load();

    document.querySelector('[data-risk-map-refresh]')?.addEventListener('click', () => {
        load(true);
    });

    // The risk map section may sit inside a collapsible <details> (MAO's
    // dashboard groups). If it's ever collapsed and reopened, Leaflet needs
    // invalidateSize() (the container had zero size while hidden) before
    // fitBounds is re-applied, or the view/zoom breaks.
    const detailsSection = mapEl.closest('details');
    if (detailsSection) {
        detailsSection.addEventListener('toggle', () => {
            if (detailsSection.open) {
                setTimeout(() => {
                    map.invalidateSize();
                    fitToMunicipalBounds();
                }, 0);
            }
        });
    }

    // Leaflet sizes itself to its container at construction time. On pages
    // where the map starts inside a hidden tab panel (MAO's "Heat Map &
    // Weather" tab, Admin's "Risk Map" tab), it needs a resize nudge once
    // that panel actually becomes visible. Listening on every dashboard tab
    // button (rather than one hardcoded data-tab value) keeps this working
    // regardless of which tab name each role uses for the map.
    document.querySelectorAll('.dashboard-tab').forEach((tabButton) => {
        tabButton.addEventListener('click', () => {
            setTimeout(() => map.invalidateSize(), 50);
        });
    });
}

