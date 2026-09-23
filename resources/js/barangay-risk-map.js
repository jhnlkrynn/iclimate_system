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

const ESC_MAP = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ESC_MAP[c]);

// Plain-language, one-line meaning for each risk level — shown directly
// under the risk badge so an elderly farmer doesn't have to interpret a
// score or color alone. Wording only; the levels themselves ('Low',
// 'Moderate', 'High', 'Severe', 'No Data') still come straight from
// AgriculturalRiskScorer/BarangayRiskMapService, unmodified.
const RISK_EXPLANATIONS = {
    'Low': 'Safe conditions for normal farm activity.',
    'Moderate': 'Be careful and monitor your farm.',
    'High': 'Take action to protect crops.',
    'Severe': 'Immediate attention needed.',
    'No Data': 'No current weather data available.',
};

// The risk-map API's weather block already carries `icon` (an
// /images/weather/*.svg path) and `condition` (a short label, e.g. "Light
// Rain", "Thunderstorm") — computed server-side by the existing, already
// used-elsewhere App\Services\Weather\WeatherIconMapper from Open-Meteo's
// own weather_code, in BarangayRiskMapService. simplifyConditionLabel() maps
// that (sometimes fairly technical, e.g. "Mainly Clear", "Rain Showers")
// text onto the small, plain-language set an elderly farmer can recognize at
// a glance. This is a presentation-only relabeling — it never changes which
// icon is shown (still WeatherIconMapper's own choice) and never touches
// risk scoring.
function simplifyConditionLabel(condition) {
    const value = String(condition || '');

    if (/thunderstorm/i.test(value)) return 'Thunderstorm';
    if (/partly cloudy/i.test(value)) return 'Partly Cloudy';
    if (/overcast/i.test(value)) return 'Cloudy';
    if (/clear night/i.test(value)) return 'Clear';
    if (/clear/i.test(value)) return 'Sunny';
    if (/foggy/i.test(value)) return 'Foggy';
    if (/rain|drizzle|showers/i.test(value)) return 'Rainy';

    return 'No Weather Data';
}

// "September 22, 2026 • 10:59 AM" — friendlier than the previous raw
// toLocaleString() timestamp for elderly readers.
function formatFriendlyDateTime(isoString) {
    if (!isoString) return null;
    const date = new Date(isoString);
    if (Number.isNaN(date.getTime())) return null;

    const datePart = date.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
    const timePart = date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });

    return `${datePart} • ${timePart}`;
}

function weatherIconMarkup(weather, size = 30) {
    if (!weather || weather.status !== 'fresh' || !weather.icon) {
        return '<span class="weather-icon-fallback">No Weather Data</span>';
    }

    return `<img class="weather-icon" src="${esc(weather.icon)}" alt="${esc(weather.condition || 'Weather icon')}" width="${size}" height="${size}" loading="lazy">`;
}

// Cheap lat/lng-space collision avoidance for the small per-barangay weather
// markers: if a new centroid lands too close to an already-placed one (e.g.
// the cluster of Poblacion barangays near the town center), nudge it a small
// fixed distance outward along a rotating angle until it clears, instead of
// letting icons stack directly on top of each other. Degree-based (not
// pixel-based) so it doesn't need recalculating on zoom/pan.
function findClearWeatherMarkerPosition(centroid, placedCentroids) {
    // Bumped alongside the larger, more-visible marker badge size (see
    // weatherMarkerIcon()) so bigger icons still get enough breathing room
    // in dense clusters like the Poblacion barangays.
    const MIN_SEPARATION_DEG = 0.005;
    const OFFSET_DEG = 0.003;
    let lat = centroid.lat;
    let lng = centroid.lng;
    let angle = 0;
    let attempts = 0;

    const tooClose = () => placedCentroids.some((p) => Math.hypot(lat - p.lat, lng - p.lng) < MIN_SEPARATION_DEG);

    while (tooClose() && attempts < 8) {
        angle += Math.PI / 4;
        lat = centroid.lat + Math.sin(angle) * OFFSET_DEG;
        lng = centroid.lng + Math.cos(angle) * OFFSET_DEG;
        attempts += 1;
    }

    return window.L.latLng(lat, lng);
}

// Small, non-interactive divIcon marker for a barangay's current weather —
// reuses the exact same server-computed weather.icon (/images/weather/*.svg,
// via the existing WeatherIconMapper) as the detail panel/tooltip. Returns
// null when there's no fresh weather to show, so a barangay is simply left
// without a marker rather than showing a misleading/default icon.
function weatherMarkerIcon(weather) {
    if (!weather || weather.status !== 'fresh' || !weather.icon) return null;

    return window.L.divIcon({
        className: 'weather-marker-icon',
        html: `<span class="weather-marker-badge" title="${esc(simplifyConditionLabel(weather.condition))}"><img src="${esc(weather.icon)}" alt="" width="22" height="22"></span>`,
        iconSize: [34, 34],
        iconAnchor: [17, 17],
    });
}

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

// Renders the elderly-friendly, card-based barangay detail panel. Same
// function/markup for every role (Farmer, MAO, IT Expert/Admin) — there is
// only ever one shared #riskMapDetailPanel per page. Layout: A) barangay +
// large risk badge + plain-language meaning, B) weather condition icon +
// current readings, C) short farming advice bullets, D) last updated, and a
// collapsed-by-default "More Information" section for the technical detail
// (risk score %, data source, monthly rainfall figure, farm type, computed
// timestamp) that used to be shown inline. No risk/weather value shown here
// is computed client-side — everything is read straight off `entry`/`meta`
// exactly as BarangayRiskMapService already returns it.
function renderRiskDetailPanel(entry, meta) {
    const panel = document.getElementById('riskMapDetailPanel');
    const content = document.getElementById('riskMapDetailContent');
    if (!panel || !content) return;

    const riskLevel = entry.risk_level || 'No Data';
    const riskLevelClass = riskLevel.toLowerCase().replace(/\s+/g, '-');
    const isNoData = riskLevel === 'No Data';
    const explanation = RISK_EXPLANATIONS[riskLevel] || RISK_EXPLANATIONS['No Data'];
    const w = entry.weather || {};
    const hasFreshWeather = w.status === 'fresh';
    const updatedFriendly = formatFriendlyDateTime(entry.updated_at);

    const sections = [];

    // A. Barangay & Risk — large, bold, color + text + icon together (never
    // color alone), so the risk is readable at a glance.
    sections.push(`
        <div class="risk-card risk-card-head">
            <h3 class="risk-card-barangay">${esc(entry.barangay)}${entry.is_farmer_barangay ? ' <span class="your-barangay-pill">Your Barangay</span>' : ''}</h3>
            <div class="risk-level-badge risk-level-badge-${esc(riskLevelClass)}">
                <span class="risk-level-badge-text">${isNoData ? 'NO DATA' : esc(riskLevel.toUpperCase())}</span>
            </div>
            <p class="risk-card-explain">${esc(explanation)}</p>
        </div>
    `);

    // B. Current Weather — icon + plain condition name up top, then a
    // compact 2-column grid of only the readings a farmer actually needs.
    if (hasFreshWeather) {
        const tiles = [];
        if (w.rainfall_24h_mm !== null && w.rainfall_24h_mm !== undefined) tiles.push(['Rain', `${esc(w.rainfall_24h_mm)} mm`]);
        if (w.temperature_c !== null && w.temperature_c !== undefined) tiles.push(['Temperature', `${esc(w.temperature_c)}&deg;C`]);
        if (w.wind_speed_kmh !== null && w.wind_speed_kmh !== undefined) tiles.push(['Wind', `${esc(w.wind_speed_kmh)} km/h`]);
        if (w.humidity_percent !== null && w.humidity_percent !== undefined) tiles.push(['Humidity', `${esc(w.humidity_percent)}%`]);

        sections.push(`
            <div class="risk-card">
                <h4 class="risk-card-heading">Current Weather</h4>
                <div class="risk-card-condition">${weatherIconMarkup(w, 40)} <span class="risk-card-condition-name">${esc(simplifyConditionLabel(w.condition))}</span></div>
                ${tiles.length ? `<div class="risk-weather-grid">${tiles.map(([label, value]) => `<div class="risk-weather-tile"><span class="risk-weather-label">${label}</span><span class="risk-weather-value">${value}</span></div>`).join('')}</div>` : ''}
            </div>
        `);
    } else {
        sections.push(`
            <div class="risk-card">
                <h4 class="risk-card-heading">Current Weather</h4>
                <div class="risk-card-condition"><span class="weather-icon-fallback">No Weather Data</span></div>
                <p class="risk-card-body-text">${esc(w.message || 'Weather data is currently unavailable for this barangay.')}</p>
            </div>
        `);
    }

    // C. Farming Advice — short bullets only, plain wording kept exactly as
    // DecisionSupportService/AgriculturalRiskScorer already produced it.
    if (entry.advisory) {
        const advice = [];
        if (entry.advisory.planting) advice.push(entry.advisory.planting);
        if (entry.advisory.irrigation) advice.push(entry.advisory.irrigation);
        if (Array.isArray(entry.advisory.warnings)) advice.push(...entry.advisory.warnings);
        if (entry.advisory.overall_recommendation) advice.push(entry.advisory.overall_recommendation);

        if (advice.length) {
            sections.push(`
                <div class="risk-card">
                    <h4 class="risk-card-heading">Farming Advice</h4>
                    <ul class="risk-advice-list">${advice.map((line) => `<li>${esc(line)}</li>`).join('')}</ul>
                </div>
            `);
        }
    }

    // D. Last Updated — plain date/time, no raw ISO timestamp.
    sections.push(`
        <div class="risk-card risk-card-updated">
            <h4 class="risk-card-heading">Last Updated</h4>
            <p class="risk-card-body-text">${updatedFriendly ? esc(updatedFriendly) : 'Not yet available'}</p>
        </div>
    `);

    // E. More Information — collapsed by default; everything technical
    // (risk score %, data source, monthly rainfall figure, farm type,
    // recorded yield, no-data reason) lives here instead of the main view.
    const moreInfo = [];
    if (entry.risk_score !== null && entry.risk_score !== undefined) {
        moreInfo.push(`<p class="risk-more-line">Risk Score: ${esc(Math.round(entry.risk_score * 100))}%</p>`);
    }
    if (entry.monthly_rainfall && entry.monthly_rainfall.available !== false && entry.monthly_rainfall.value_mm !== null && entry.monthly_rainfall.value_mm !== undefined) {
        moreInfo.push(`<p class="risk-more-line">Monthly Rainfall: ${esc(entry.monthly_rainfall.value_mm)} ${esc(entry.monthly_rainfall.unit || 'mm')} (${esc(entry.monthly_rainfall.scope || 'municipal')})</p>`);
    }
    if (entry.farm_type_context) {
        moreInfo.push(`<p class="risk-more-line">Farm Type: ${esc(entry.farm_type_context)}</p>`);
    }
    if (entry.last_recorded_yield) {
        moreInfo.push(`<p class="risk-more-line">Last Recorded Yield: ${esc(entry.last_recorded_yield.value)} t/ha (${esc(entry.last_recorded_yield.season || '')} ${esc(entry.last_recorded_yield.year || '')})</p>`);
    }
    if (meta && meta.source_note) {
        moreInfo.push(`<p class="risk-more-line">Data Source: ${esc(meta.source_note)}${meta.generated_at ? ' &middot; as of ' + esc(formatFriendlyDateTime(meta.generated_at) || '') : ''}</p>`);
    }
    if (isNoData && entry.no_data_reason) {
        moreInfo.push(`<p class="risk-more-line">${esc(entry.no_data_reason)}</p>`);
    }

    if (moreInfo.length) {
        sections.push(`
            <details class="risk-card risk-more-info">
                <summary>More Information</summary>
                <div class="risk-more-info-body">${moreInfo.join('')}</div>
            </details>
        `);
    }

    content.innerHTML = sections.join('');
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
    let weatherMarkerLayer = null;

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
                    const tooltipWeather = entry ? entry.weather : null;
                    const tooltipIcon = tooltipWeather && tooltipWeather.status === 'fresh' && tooltipWeather.icon
                        ? `<img class="weather-icon weather-icon-tooltip" src="${tooltipWeather.icon}" alt="" width="22" height="22"> `
                        : '';
                    layer.bindTooltip(`${tooltipIcon}${name}${isHighlighted ? ' (Your Barangay)' : ''} — Risk: ${level}${scoreLabel}`, {
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

            // Small per-barangay weather condition markers — purely a visual
            // overlay drawn from the same already-loaded geoLayer/risk data,
            // rebuilt every time the choropleth itself is (initial load and
            // every manual refresh), so they never go stale relative to it.
            if (weatherMarkerLayer) weatherMarkerLayer.remove();
            weatherMarkerLayer = window.L.layerGroup();
            const placedCentroids = [];
            geoLayer.eachLayer((layer) => {
                const feature = layer.feature;
                const name = feature && feature.properties && feature.properties.brgy_name;
                const entry = name ? byName[name] : null;
                const icon = weatherMarkerIcon(entry ? entry.weather : null);
                if (!icon) return;

                let centroid;
                try { centroid = layer.getBounds().getCenter(); } catch (e) { return; }

                const position = findClearWeatherMarkerPosition(centroid, placedCentroids);
                placedCentroids.push(position);

                // interactive: false so the marker never intercepts clicks —
                // they pass straight through to the barangay polygon beneath.
                window.L.marker(position, { icon, interactive: false, keyboard: false }).addTo(weatherMarkerLayer);
            });
            weatherMarkerLayer.addTo(map);

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

