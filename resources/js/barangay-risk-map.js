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

    // Fallback center/zoom, used only until the real barangay GeoJSON loads
    // (Leaflet requires some center/zoom to construct the map), and as a last
    // resort if that fetch fails entirely.
    const FALLBACK_CENTER = [14.02, 120.66];
    const FALLBACK_ZOOM = 12;
    const map = window.L.map(mapEl, { scrollWheelZoom: false, maxBoundsViscosity: 1.0 }).setView(FALLBACK_CENTER, FALLBACK_ZOOM);
    window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 18,
    }).addTo(map);

    let geoLayer = null;

    // Fits the map to, and locks panning/zoom-out to, the bounds of the
    // already-rendered 19-barangay choropleth layer (geoLayer) — the exact
    // same layer fetched once for the risk map itself, not a second/separate
    // boundary request. This is deliberately NOT the outline-only municipal
    // file: geoLayer is guaranteed to exist whenever this is called and is
    // the authoritative "what Lian actually looks like on this map" source.
    //
    // Lian's shape is noticeably taller (north-south) than wide, while the
    // map container is wide-and-short, so a naive fitBounds() alone is
    // constrained by the *height* fit and leaves a lot of spare width on
    // screen — which is exactly what was rendering as visible slices of
    // Nasugbu, Balayan, Calaca, and open sea. Locking maxBounds/minZoom to
    // the fitted view (instead of leaving the map free to zoom/pan further
    // out) stops users from ever *seeing more* of that surrounding area,
    // even though the fitted zoom itself is still bounded by Lian's own
    // aspect ratio.
    const fitAndLockToLianBounds = () => {
        if (!geoLayer) return false;
        const bounds = geoLayer.getBounds();
        if (!bounds.isValid()) return false;
        try {
            map.fitBounds(bounds, { padding: [8, 8] });
            // A small (~8%) pad around the fitted bounds so users can still
            // nudge the view to a polygon's edge, without being able to pan
            // far enough to bring a neighboring municipality into view.
            map.setMaxBounds(bounds.pad(0.08));
            // Never allow zooming out past the initial "all of Lian" fit —
            // this is what actually prevents "zoom out to see the whole
            // Batangas region," independent of container size.
            map.setMinZoom(map.getZoom());
        } catch (e) { /* ignore */ }
        return true;
    };

    // Purely a visual outline overlay (drawn on top of the choropleth) — not
    // used for bounds/zoom calculations, so its own fetch failing never
    // affects the map's viewport.
    const loadMunicipalOutline = async () => {
        try {
            const res = await fetch('/geojson/lian-boundary.geojson');
            if (!res.ok) throw new Error('municipal boundary fetch failed: ' + res.status);
            const municipalGeoJSON = await res.json();
            window.L.geoJSON(municipalGeoJSON, {
                style: { color: '#1b5e20', weight: 2, fill: false },
            }).addTo(map);
        } catch (error) {
            console.error('iClimate could not load the municipal outline overlay.', error);
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

            fitAndLockToLianBounds();
        } catch (error) {
            console.error('iClimate could not load the barangay risk map.', error);
        }
    };

    loadMunicipalOutline();
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
                    fitAndLockToLianBounds();
                }, 0);
            }
        });
    }

    // Leaflet sizes itself to its container at construction time, so a map
    // that starts inside a hidden tab panel (MAO's "Heat Map & Weather" tab,
    // Admin's "Risk Map" tab) computes its initial fit against a zero-size
    // container and needs correcting once that panel actually becomes
    // visible. Scope this to the one tab button that actually reveals this
    // map (found dynamically from the panel's own data-panel value, so it
    // works regardless of which tab name each role uses) rather than every
    // tab button, so switching between *other*, unrelated tabs never resets
    // a user's manual pan/zoom on the map.
    const hostPanel = mapEl.closest('[data-panel]');
    const hostTabButtons = hostPanel && hostPanel.dataset.panel
        ? document.querySelectorAll(`.dashboard-tab[data-tab="${hostPanel.dataset.panel}"]`)
        : document.querySelectorAll('.dashboard-tab');
    hostTabButtons.forEach((tabButton) => {
        tabButton.addEventListener('click', () => {
            setTimeout(() => {
                map.invalidateSize();
                fitAndLockToLianBounds();
            }, 50);
        });
    });
}

